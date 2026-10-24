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

/**
 * Event observers.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * A learner completed a course: queue a credential for each group mapped to it.
     *
     * Runs after the completion is saved. The API call happens in a background task,
     * so a slow or unreachable Hyperstack never holds up Moodle.
     *
     * @param \core\event\course_completed $event
     */
    public static function course_completed(\core\event\course_completed $event): void {
        issuer::queue_user($event->courseid, $event->relateduserid);
    }

    /**
     * A course was deleted: forget its mappings and issue rows.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $DB->delete_records('local_hyperstack_map', ['courseid' => $event->courseid]);
        $DB->delete_records('local_hyperstack_issue', ['courseid' => $event->courseid]);
    }

    /**
     * A user was deleted: forget their issue rows. Credentials already issued stay in Hyperstack.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records('local_hyperstack_issue', ['userid' => $event->objectid]);
    }
}
