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
 * Backs up a course's credential groups, so a copy or restore of the course issues the same credentials.
 *
 * Issue rows are not backed up: they belong to the learners of the original course.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_hyperstack_plugin extends backup_local_plugin {
    /**
     * The course's mappings.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $maps = new backup_nested_element('maps');
        $map = new backup_nested_element('map', ['id'], ['groupkey', 'grouptitle']);

        $plugin->add_child($wrapper);
        $wrapper->add_child($maps);
        $maps->add_child($map);

        $map->set_source_table('local_hyperstack_map', ['courseid' => backup::VAR_COURSEID]);
        return $plugin;
    }
}
