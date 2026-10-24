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

/**
 * Restores a course's credential groups.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_hyperstack_plugin extends restore_local_plugin {
    /**
     * The paths this plugin restores.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        return [new restore_path_element('local_hyperstack_map', $this->get_pathfor('/maps/map'))];
    }

    /**
     * Adds one mapping to the restored course, unless the course already has it.
     *
     * @param array $data
     */
    public function process_local_hyperstack_map($data) {
        global $DB;
        $data = (object) $data;
        $courseid = $this->task->get_courseid();
        if ($DB->record_exists('local_hyperstack_map', ['courseid' => $courseid, 'groupkey' => $data->groupkey])) {
            return;
        }
        $now = time();
        $DB->insert_record('local_hyperstack_map', (object) [
            'courseid' => $courseid,
            'groupkey' => $data->groupkey,
            'grouptitle' => $data->grouptitle,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
