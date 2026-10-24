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

namespace local_hyperstack\api;

use core\http_client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Calls to the Hyperstack API (https://api.thehyperstack.com).
 *
 * Every call sends the organization's API key. A call that does not succeed throws api_exception,
 * which says whether trying again later can work.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /** The API address when none is set. */
    const DEFAULT_URL = 'https://api.thehyperstack.com';

    /** Groups asked for per page; the API's largest page. */
    const PAGE_SIZE = 100;

    /** Seconds to wait for Hyperstack. */
    const TIMEOUT = 20;

    /** @var string */
    private string $apikey;

    /** @var string */
    private string $apiurl;

    /** @var bool True when a key is saved but cannot be decrypted. */
    private bool $unreadable = false;

    /**
     * Constructor. Without arguments, the key and address come from the plugin settings.
     *
     * @param string|null $apikey API key
     * @param string|null $apiurl API address
     */
    public function __construct(?string $apikey = null, ?string $apiurl = null) {
        $this->apikey = trim($apikey ?? self::saved_key());
        $this->unreadable = $apikey === null && $this->apikey === '' && self::is_configured();
        $apiurl = trim($apiurl ?? (string) get_config('local_hyperstack', 'apiurl'));
        $this->apiurl = rtrim($apiurl !== '' ? $apiurl : self::DEFAULT_URL, '/');
    }

    /**
     * True when the site admin has saved an API key.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        return trim((string) get_config('local_hyperstack', 'apikey')) !== '';
    }

    /**
     * The API key from the plugin settings, decrypted: the setting is stored encrypted with the site's key.
     * Empty when no key is saved, or when it cannot be decrypted (the site's key file changed or cannot be read).
     *
     * @return string
     */
    private static function saved_key(): string {
        $stored = (string) get_config('local_hyperstack', 'apikey');
        if ($stored === '') {
            return '';
        }
        try {
            return trim(\core\encryption::decrypt($stored));
        } catch (\moodle_exception $e) {
            return '';
        }
    }

    /**
     * The organization the key belongs to.
     *
     * @return array account_name, account_id, credit_balance
     */
    public function info(): array {
        $answer = $this->request('GET', '/v1/info');
        if (empty($answer['account_name'])) {
            throw new api_exception(401, 'invalid_key', get_string('error:invalidkey', 'local_hyperstack'), false);
        }
        return $answer;
    }

    /**
     * Every credential group of the organization.
     *
     * @return array[] Each with group_id, title, group_code and, when the API sends it, archived
     */
    public function groups(): array {
        $groups = [];
        $page = 1;
        do {
            $answer = $this->request('POST', '/v1/group/all', ['page' => $page, 'page_size' => self::PAGE_SIZE]);
            $rows = $answer['groups'] ?? [];
            $groups = array_merge($groups, $rows);
            $total = (int) ($answer['total'] ?? 0);
            $page++;
        } while ($rows && count($groups) < $total);
        return $groups;
    }

    /**
     * Issues and publishes a credential. Hyperstack emails it to the learner.
     *
     * @param string $groupkey Credential group key
     * @param string $name Learner's full name
     * @param string $email Learner's email
     * @return array document_id, document_url and the rest of the credential
     */
    public function issue(string $groupkey, string $name, string $email): array {
        return $this->request('POST', '/v1/credential/issue', [
            'group_key' => $groupkey,
            'recipient' => ['name' => $name, 'email' => $email],
            'source' => 'moodle',
        ]);
    }

    /**
     * Sends one request and returns the decoded answer.
     *
     * @param string $method GET or POST
     * @param string $path Path from the API address
     * @param array|null $body JSON body
     * @return array
     */
    private function request(string $method, string $path, ?array $body = null): array {
        if ($this->unreadable) {
            throw new api_exception(0, 'unreadable_key', get_string('error:unreadablekey', 'local_hyperstack'), false);
        }
        if ($this->apikey === '') {
            throw new api_exception(0, 'not_configured', get_string('error:notconfigured', 'local_hyperstack'), false);
        }

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apikey,
                'Accept' => 'application/json',
                'X-Hyperstack-Client' => 'moodle/' . get_config('local_hyperstack', 'version'),
            ],
            'http_errors' => false,
            'timeout' => self::TIMEOUT,
        ];
        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = \core\di::get(http_client::class)->request($method, $this->apiurl . $path, $options);
        } catch (GuzzleException $e) {
            throw new api_exception(0, 'network', get_string('error:network', 'local_hyperstack'), true, $e->getMessage());
        }

        $code = $response->getStatusCode();
        $answer = json_decode((string) $response->getBody(), true);
        if (!is_array($answer)) {
            $answer = [];
        }
        if ($code >= 200 && $code < 300 && ($answer['success'] ?? true) !== false) {
            return $answer;
        }

        $apicode = (string) ($answer['error_code'] ?? '');
        $reason = (string) ($answer['error'] ?? $answer['detail'] ?? $answer['message'] ?? '');

        if ($code == 401 || $code == 403 || $apicode === 'invalid_token') {
            throw new api_exception($code, 'invalid_key', get_string('error:invalidkey', 'local_hyperstack'), false);
        }
        if ($code == 402 || $apicode === 'low_balance') {
            throw new api_exception($code, 'low_balance', get_string('error:lowbalance', 'local_hyperstack'), true);
        }
        if ($code == 429 || $code >= 500) {
            $reason = $reason !== '' ? $reason : get_string('error:server', 'local_hyperstack', $code);
            throw new api_exception($code, $apicode !== '' ? $apicode : 'server', $reason, true);
        }
        $reason = $reason !== '' ? $reason : get_string('error:server', 'local_hyperstack', $code);
        throw new api_exception($code, $apicode !== '' ? $apicode : 'refused', $reason, false);
    }
}
