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
 * Navigation callbacks.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds "Hyperstack credentials" to the course's More menu for teachers who can manage it.
 *
 * @param navigation_node $navigation The course node
 * @param stdClass $course
 * @param context $context The course context
 */
function local_hyperstack_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context) {
    if ($course->id == SITEID || !has_capability('local/hyperstack:manage', $context)) {
        return;
    }
    $navigation->add(
        get_string('coursenav', 'local_hyperstack'),
        new moodle_url('/local/hyperstack/course.php', ['id' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_hyperstack',
        new pix_icon('i/badge', '')
    );
}
