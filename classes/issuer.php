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

use local_hyperstack\api\api_exception;
use local_hyperstack\api\client;

/**
 * Issuing credentials: one row per learner, course and group in local_hyperstack_issue.
 *
 * A row is added once and never again, so a learner never receives the same credential twice
 * from the same course. A background task makes the API call and saves the outcome on the row.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issuer {
    /** Waiting for the background task. */
    const QUEUED = 'queued';
    /** The last attempt failed; Moodle tries again later. */
    const RETRYING = 'retrying';
    /** Issued and emailed by Hyperstack. */
    const ISSUED = 'issued';
    /** The learner already had a credential in this group. */
    const EXISTS = 'exists';
    /** Gave up: see the message. */
    const FAILED = 'failed';
    /** Not sent: the learner was suspended or deleted, or the group was unmapped. */
    const SKIPPED = 'skipped';

    /** Statuses a teacher can retry. */
    const RETRYABLE = [self::FAILED, self::RETRYING, self::SKIPPED];

    /**
     * Queues a credential for each group mapped to the course that the learner does not have a row for.
     *
     * @param int $courseid
     * @param int $userid
     * @return int Rows queued
     */
    public static function queue_user(int $courseid, int $userid): int {
        global $DB;
        if (!client::is_configured()) {
            return 0;
        }
        $queued = 0;
        foreach ($DB->get_records('local_hyperstack_map', ['courseid' => $courseid], 'id') as $map) {
            $where = ['userid' => $userid, 'courseid' => $courseid, 'groupkey' => $map->groupkey];
            if ($DB->record_exists('local_hyperstack_issue', $where)) {
                continue;
            }
            $now = time();
            $id = $DB->insert_record('local_hyperstack_issue', (object) ($where + [
                'grouptitle' => $map->grouptitle,
                'status' => self::QUEUED,
                'timecreated' => $now,
                'timemodified' => $now,
            ]));
            self::queue_task($id);
            $queued++;
        }
        return $queued;
    }

    /**
     * Queues every learner who has already completed the course.
     *
     * @param int $courseid
     * @return int Rows queued
     */
    public static function queue_past_completers(int $courseid): int {
        global $DB;
        $userids = $DB->get_fieldset_select(
            'course_completions',
            'userid',
            'course = :course AND timecompleted IS NOT NULL',
            ['course' => $courseid]
        );
        $queued = 0;
        foreach ($userids as $userid) {
            $queued += self::queue_user($courseid, (int) $userid);
        }
        return $queued;
    }

    /**
     * Queues a failed, retrying or skipped row again.
     *
     * @param int $issueid
     * @param int $courseid The row must belong to this course
     * @return bool False when the row is not one that can be retried
     */
    public static function retry(int $issueid, int $courseid): bool {
        global $DB;
        $row = $DB->get_record('local_hyperstack_issue', ['id' => $issueid, 'courseid' => $courseid]);
        if (!$row || !in_array($row->status, self::RETRYABLE)) {
            return false;
        }
        self::save($row, self::QUEUED, null);
        // A retrying row still has its task queued, waiting for its delay: run it now instead.
        $task = new task\issue_credential();
        $task->set_custom_data(['issueid' => (int) $row->id]);
        $task->set_next_run_time(time());
        \core\task\manager::reschedule_or_queue_adhoc_task($task);
        return true;
    }

    /**
     * Makes the API call for one row. Called by the background task.
     *
     * When Hyperstack cannot take the call now (no answer, server error, no credits), the row is marked
     * retrying and the exception is thrown again so Moodle retries the task later, unless this is
     * the last attempt.
     *
     * @param int $issueid
     * @param bool $lastattempt True when Moodle will not try again after this
     */
    public static function process(int $issueid, bool $lastattempt = false): void {
        global $DB;
        $row = $DB->get_record('local_hyperstack_issue', ['id' => $issueid]);
        if (!$row || $row->status === self::ISSUED || $row->status === self::EXISTS) {
            return;
        }

        $user = \core_user::get_user($row->userid);
        if (!$user || $user->deleted) {
            self::save($row, self::SKIPPED, get_string('skip:userdeleted', 'local_hyperstack'));
            return;
        }
        if ($user->suspended) {
            self::save($row, self::SKIPPED, get_string('skip:usersuspended', 'local_hyperstack'));
            return;
        }
        if (!$DB->record_exists('local_hyperstack_map', ['courseid' => $row->courseid, 'groupkey' => $row->groupkey])) {
            self::save($row, self::SKIPPED, get_string('skip:unmapped', 'local_hyperstack'));
            return;
        }

        try {
            $answer = (new client())->issue($row->groupkey, fullname($user), $user->email);
        } catch (api_exception $e) {
            if ($e->apicode === 'credential_exists') {
                self::save($row, self::EXISTS, get_string('exists', 'local_hyperstack'));
            } else if ($e->retry && !$lastattempt) {
                self::save($row, self::RETRYING, $e->get_reason());
                throw $e;
            } else {
                self::save($row, self::FAILED, $e->get_reason());
            }
            return;
        }

        $row->credentialid = clean_param($answer['document_id'] ?? '', PARAM_ALPHANUMEXT);
        $row->credentialurl = clean_param($answer['document_url'] ?? '', PARAM_URL);
        self::save($row, self::ISSUED, null);
    }

    /**
     * Saves a row's status and message.
     *
     * @param \stdClass $row
     * @param string $status
     * @param string|null $message
     */
    private static function save(\stdClass $row, string $status, ?string $message): void {
        global $DB;
        $row->status = $status;
        $row->message = $message;
        $row->timemodified = time();
        $DB->update_record('local_hyperstack_issue', $row);
    }

    /**
     * Queues the background task for one row; a task already queued for it is not queued twice.
     *
     * @param int $issueid
     */
    private static function queue_task(int $issueid): void {
        $task = new task\issue_credential();
        $task->set_custom_data(['issueid' => $issueid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
