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

namespace local_hyperstack\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * The plugin keeps one row per learner, course and credential group (local_hyperstack_issue), and sends
 * the learner's full name and email to Hyperstack, which issues and emails the credential.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * The data the plugin stores and sends out.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_hyperstack_issue', [
            'userid' => 'privacy:metadata:issue:userid',
            'courseid' => 'privacy:metadata:issue:courseid',
            'grouptitle' => 'privacy:metadata:issue:grouptitle',
            'status' => 'privacy:metadata:issue:status',
            'credentialurl' => 'privacy:metadata:issue:credentialurl',
            'message' => 'privacy:metadata:issue:message',
            'timecreated' => 'privacy:metadata:issue:timecreated',
            'timemodified' => 'privacy:metadata:issue:timemodified',
        ], 'privacy:metadata:issue');

        $collection->add_external_location_link('hyperstack', [
            'fullname' => 'privacy:metadata:hyperstack:fullname',
            'email' => 'privacy:metadata:hyperstack:email',
        ], 'privacy:metadata:hyperstack');

        return $collection;
    }

    /**
     * The course contexts where the user has issue rows.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_hyperstack_issue} i ON i.courseid = ctx.instanceid AND ctx.contextlevel = :level
                 WHERE i.userid = :userid";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['level' => CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * The users with issue rows in a course context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {local_hyperstack_issue} WHERE courseid = :courseid',
            ['courseid' => $context->instanceid]
        );
    }

    /**
     * Exports the user's issue rows, one list per course.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $rows = $DB->get_records('local_hyperstack_issue', ['userid' => $userid, 'courseid' => $context->instanceid], 'id');
            if (!$rows) {
                continue;
            }
            $credentials = [];
            foreach ($rows as $row) {
                $credentials[] = (object) [
                    'group' => format_string($row->grouptitle),
                    'status' => get_string('status:' . $row->status, 'local_hyperstack'),
                    'credentialurl' => $row->credentialurl,
                    'message' => $row->message,
                    'timecreated' => transform::datetime($row->timecreated),
                    'timemodified' => transform::datetime($row->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:path', 'local_hyperstack')],
                (object) ['credentials' => $credentials]
            );
        }
    }

    /**
     * Deletes every issue row of a course.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof \context_course) {
            $DB->delete_records('local_hyperstack_issue', ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Deletes the user's issue rows in the approved courses.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                $DB->delete_records('local_hyperstack_issue', ['userid' => $userid, 'courseid' => $context->instanceid]);
            }
        }
    }

    /**
     * Deletes the issue rows of several users in one course.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_course || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $DB->delete_records_select('local_hyperstack_issue', "courseid = :courseid AND userid $insql", $params);
    }
}
