<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_hyperstack;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use local_hyperstack\api\api_exception;
use local_hyperstack\api\client;

/**
 * Tests for the Hyperstack API client.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hyperstack\api\client
 */
#[\PHPUnit\Framework\Attributes\CoversClass(client::class)]
final class client_test extends \advanced_testcase {
    /**
     * A JSON answer.
     *
     * @param int $code
     * @param array $body
     * @return Response
     */
    private function answer(int $code, array $body): Response {
        return new Response($code, ['Content-Type' => 'application/json'], json_encode($body));
    }

    public function test_info_sends_the_key_and_returns_the_account(): void {
        $this->resetAfterTest();
        $history = [];
        $mock = $this->get_mocked_http_client($history)['mock'];
        $mock->append($this->answer(200, ['account_name' => 'Acme', 'credit_balance' => 42]));

        $info = (new client('secret', 'https://api.example.com/'))->info();

        $this->assertSame('Acme', $info['account_name']);
        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://api.example.com/v1/info', (string) $request->getUri());
        $this->assertSame('Bearer secret', $request->getHeaderLine('Authorization'));
    }

    public function test_the_saved_key_is_decrypted_before_it_is_sent(): void {
        $this->resetAfterTest();
        set_config('apikey', \core\encryption::encrypt('secret'), 'local_hyperstack');
        $history = [];
        $mock = $this->get_mocked_http_client($history)['mock'];
        $mock->append($this->answer(200, ['account_name' => 'Acme']));

        $this->assertTrue(client::is_configured());
        (new client())->info();

        $this->assertSame('Bearer secret', $history[0]['request']->getHeaderLine('Authorization'));
    }

    public function test_info_without_an_account_is_an_invalid_key(): void {
        $this->resetAfterTest();
        $mock = $this->get_mocked_http_client()['mock'];
        $mock->append($this->answer(200, ['status' => 'live']));

        try {
            (new client('secret'))->info();
            $this->fail('No exception');
        } catch (api_exception $e) {
            $this->assertSame('invalid_key', $e->apicode);
            $this->assertFalse($e->retry);
        }
    }

    public function test_issue_sends_the_recipient_group_and_source(): void {
        $this->resetAfterTest();
        $history = [];
        $mock = $this->get_mocked_http_client($history)['mock'];
        $mock->append($this->answer(200, ['success' => true, 'document_id' => 'abc-123']));

        $answer = (new client('secret'))->issue('grp1', 'Ana Lima', 'ana@example.com');

        $this->assertSame('abc-123', $answer['document_id']);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(client::DEFAULT_URL . '/v1/credential/issue', (string) $request->getUri());
        $this->assertSame([
            'group_key' => 'grp1',
            'recipient' => ['name' => 'Ana Lima', 'email' => 'ana@example.com'],
            'source' => 'moodle',
        ], json_decode((string) $request->getBody(), true));
    }

    public function test_groups_reads_every_page(): void {
        $this->resetAfterTest();
        $history = [];
        $mock = $this->get_mocked_http_client($history)['mock'];
        $first = array_fill(0, client::PAGE_SIZE, ['group_id' => 'a', 'title' => 'A']);
        $mock->append($this->answer(200, ['success' => true, 'total' => client::PAGE_SIZE + 1, 'groups' => $first]));
        $mock->append($this->answer(200, ['success' => true, 'total' => client::PAGE_SIZE + 1,
            'groups' => [['group_id' => 'b', 'title' => 'B']]]));

        $groups = (new client('secret'))->groups();

        $this->assertCount(client::PAGE_SIZE + 1, $groups);
        $this->assertSame('b', end($groups)['group_id']);
        $this->assertSame(2, json_decode((string) $history[1]['request']->getBody(), true)['page']);
    }

    /**
     * Answers that are not a success, and how the client reports them.
     *
     * @return array
     */
    public static function failures_provider(): array {
        return [
            'bad key' => [401, ['detail' => 'Invalid token'], 'invalid_key', false],
            'forbidden' => [403, ['detail' => 'Invalid token'], 'invalid_key', false],
            'no credits' => [402, ['error' => 'Low balance', 'error_code' => 'low_balance'], 'low_balance', true],
            'already issued' => [409, ['error' => 'Credential already exists', 'error_code' => 'credential_exists'],
                'credential_exists', false],
            'unknown group' => [404, ['error' => 'Group Not Found', 'error_code' => 'group_not_found'], 'group_not_found', false],
            'bad email' => [400, ['error' => 'bad email', 'error_code' => 'invalid_email'], 'invalid_email', false],
            'too many calls' => [429, [], 'server', true],
            'server error' => [500, [], 'server', true],
        ];
    }

    /**
     * Each failure has the right code and retry flag.
     *
     * @dataProvider failures_provider
     * @param int $code HTTP status
     * @param array $body Answer body
     * @param string $apicode Expected code
     * @param bool $retry Expected retry flag
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('failures_provider')]
    public function test_failures(int $code, array $body, string $apicode, bool $retry): void {
        $this->resetAfterTest();
        $mock = $this->get_mocked_http_client()['mock'];
        $mock->append($this->answer($code, $body));

        try {
            (new client('secret'))->issue('grp1', 'Ana Lima', 'ana@example.com');
            $this->fail('No exception');
        } catch (api_exception $e) {
            $this->assertSame($code, $e->httpcode);
            $this->assertSame($apicode, $e->apicode);
            $this->assertSame($retry, $e->retry);
            $this->assertNotSame('', $e->get_reason());
        }
    }

    public function test_no_answer_is_retried(): void {
        $this->resetAfterTest();
        $mock = $this->get_mocked_http_client()['mock'];
        $mock->append(new ConnectException('Connection refused', new Request('POST', 'https://api.example.com')));

        try {
            (new client('secret'))->issue('grp1', 'Ana Lima', 'ana@example.com');
            $this->fail('No exception');
        } catch (api_exception $e) {
            $this->assertSame('network', $e->apicode);
            $this->assertTrue($e->retry);
        }
    }

    public function test_no_key_makes_no_call(): void {
        $this->resetAfterTest();
        $history = [];
        $this->get_mocked_http_client($history);

        $this->assertFalse(client::is_configured());
        try {
            (new client())->info();
            $this->fail('No exception');
        } catch (api_exception $e) {
            $this->assertSame('not_configured', $e->apicode);
        }
        $this->assertCount(0, $history);
    }
}
