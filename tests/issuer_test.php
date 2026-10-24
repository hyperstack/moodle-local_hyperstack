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

use GuzzleHttp\Psr7\Response;
use local_hyperstack\api\api_exception;
use local_hyperstack\task\issue_credential;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

#[\PHPUnit\Framework\Attributes\CoversClass(issuer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(issue_credential::class)]
/**
 * Tests for issuing: the completion observer, the issue rows and the background task.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hyperstack\issuer
 * @covers     \local_hyperstack\observer
 * @covers     \local_hyperstack\task\issue_credential
 */
final class issuer_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course;

    /** @var \stdClass */
    private $user;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        set_config('apikey', \core\encryption::encrypt('secret'), 'local_hyperstack');

        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->user = $this->getDataGenerator()->create_and_enrol(
            $this->course,
            'student',
            ['firstname' => 'Ana', 'lastname' => 'Lima', 'email' => 'ana@example.com']
        );
        $DB->insert_record('local_hyperstack_map', (object) [
            'courseid' => $this->course->id, 'groupkey' => 'grp1', 'grouptitle' => 'Python basics',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Marks the course complete for a user and hands the course_completed event to the observer.
     *
     * Tests run inside a database transaction, so Moodle holds back events for observers registered
     * with internal => false; the event is caught and passed on here instead.
     *
     * @param \stdClass $user
     */
    private function complete(\stdClass $user): void {
        $sink = $this->redirectEvents();
        $completion = new \completion_completion(['course' => $this->course->id, 'userid' => $user->id]);
        $completion->mark_complete();
        $events = $sink->get_events();
        $sink->close();
        foreach ($events as $event) {
            if ($event instanceof \core\event\course_completed) {
                observer::course_completed($event);
            }
        }
    }

    /**
     * The only issue row of the test user.
     *
     * @return \stdClass
     */
    private function row(): \stdClass {
        global $DB;
        return $DB->get_record('local_hyperstack_issue', ['userid' => $this->user->id], '*', MUST_EXIST);
    }

    /**
     * Queues an answer from Hyperstack.
     *
     * @param int $code
     * @param array $body
     * @param array|null $history Filled with the requests
     */
    private function hyperstack_answers(int $code, array $body, ?array &$history = null): void {
        $mock = $this->get_mocked_http_client($history)['mock'];
        $mock->append(new Response($code, ['Content-Type' => 'application/json'], json_encode($body)));
    }

    public function test_completion_queues_one_row_and_one_task(): void {
        $this->complete($this->user);

        $row = $this->row();
        $this->assertSame(issuer::QUEUED, $row->status);
        $this->assertSame('Python basics', $row->grouptitle);
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(issue_credential::class));

        // The same completion again adds nothing.
        $this->assertSame(0, issuer::queue_user($this->course->id, $this->user->id));
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(issue_credential::class));
    }

    public function test_nothing_is_queued_without_a_key(): void {
        global $DB;
        unset_config('apikey', 'local_hyperstack');
        $this->complete($this->user);
        $this->assertSame(0, $DB->count_records('local_hyperstack_issue'));
    }

    public function test_task_issues_the_credential(): void {
        $this->complete($this->user);
        $history = [];
        $this->hyperstack_answers(200, ['success' => true, 'document_id' => 'abc-123',
            'document_url' => 'https://hyperstack.id/credential/abc-123'], $history);

        $tasks = \core\task\manager::get_adhoc_tasks(issue_credential::class);
        reset($tasks)->execute();

        $row = $this->row();
        $this->assertSame(issuer::ISSUED, $row->status);
        $this->assertSame('abc-123', $row->credentialid);
        $this->assertSame('https://hyperstack.id/credential/abc-123', $row->credentialurl);
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame(['name' => 'Ana Lima', 'email' => 'ana@example.com'], $body['recipient']);
        $this->assertSame('grp1', $body['group_key']);

        // A finished row is never sent again.
        issuer::process($row->id);
        $this->assertCount(1, $history);
    }

    public function test_a_credential_the_learner_already_has_is_marked_done(): void {
        $this->complete($this->user);
        $this->hyperstack_answers(409, ['error' => 'Credential already exists', 'error_code' => 'credential_exists']);

        issuer::process($this->row()->id);

        $this->assertSame(issuer::EXISTS, $this->row()->status);
    }

    public function test_a_temporary_failure_is_retried_until_the_last_attempt(): void {
        $this->complete($this->user);
        $this->hyperstack_answers(500, []);
        try {
            issuer::process($this->row()->id);
            $this->fail('No exception');
        } catch (api_exception $e) {
            $this->assertTrue($e->retry);
        }
        $this->assertSame(issuer::RETRYING, $this->row()->status);

        $this->hyperstack_answers(402, ['error' => 'Low balance', 'error_code' => 'low_balance']);
        issuer::process($this->row()->id, true);
        $row = $this->row();
        $this->assertSame(issuer::FAILED, $row->status);
        $this->assertSame(get_string('error:lowbalance', 'local_hyperstack'), $row->message);
    }

    public function test_a_refused_call_fails_at_once(): void {
        $this->complete($this->user);
        $this->hyperstack_answers(401, ['detail' => 'Invalid token']);

        issuer::process($this->row()->id);

        $this->assertSame(issuer::FAILED, $this->row()->status);
    }

    public function test_suspended_learners_and_unmapped_groups_are_skipped(): void {
        global $DB;
        $history = [];
        $this->get_mocked_http_client($history);
        $this->complete($this->user);

        $DB->set_field('user', 'suspended', 1, ['id' => $this->user->id]);
        issuer::process($this->row()->id);
        $this->assertSame(issuer::SKIPPED, $this->row()->status);

        $DB->set_field('user', 'suspended', 0, ['id' => $this->user->id]);
        $DB->delete_records('local_hyperstack_map');
        issuer::process($this->row()->id);
        $this->assertSame(get_string('skip:unmapped', 'local_hyperstack'), $this->row()->message);
        $this->assertCount(0, $history);
    }

    public function test_past_completers_are_queued_once(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        // Completed before the site was connected: no row yet.
        unset_config('apikey', 'local_hyperstack');
        $this->complete($this->user);
        $this->complete($other);
        set_config('apikey', \core\encryption::encrypt('secret'), 'local_hyperstack');

        $this->assertSame(2, issuer::queue_past_completers($this->course->id));
        $this->assertSame(0, issuer::queue_past_completers($this->course->id));
    }

    public function test_retry_requeues_a_failed_row_of_the_same_course(): void {
        global $DB;
        $this->complete($this->user);
        $row = $this->row();
        $DB->set_field('local_hyperstack_issue', 'status', issuer::FAILED, ['id' => $row->id]);
        $DB->delete_records('task_adhoc');

        $this->assertFalse(issuer::retry($row->id, $this->course->id + 1));
        $this->assertTrue(issuer::retry($row->id, $this->course->id));
        $this->assertSame(issuer::QUEUED, $this->row()->status);
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(issue_credential::class));

        // A retrying row's task waiting for its delay is brought forward, not queued twice.
        $DB->set_field('local_hyperstack_issue', 'status', issuer::RETRYING, ['id' => $row->id]);
        $DB->set_field('task_adhoc', 'nextruntime', time() + 3600);
        $this->assertTrue(issuer::retry($row->id, $this->course->id));
        $tasks = \core\task\manager::get_adhoc_tasks(issue_credential::class);
        $this->assertCount(1, $tasks);
        $this->assertLessThanOrEqual(time(), reset($tasks)->get_next_run_time());

        // Only failed, retrying or skipped rows can be retried.
        $this->assertFalse(issuer::retry($row->id, $this->course->id));
    }

    public function test_deleting_the_course_or_user_removes_their_rows(): void {
        global $DB;
        $this->complete($this->user);

        delete_user($this->user);
        $this->assertSame(0, $DB->count_records('local_hyperstack_issue'));

        delete_course($this->course, false);
        $this->assertSame(0, $DB->count_records('local_hyperstack_map'));
    }
}
