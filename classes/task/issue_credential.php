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

namespace local_hyperstack\task;

use local_hyperstack\issuer;

/**
 * Issues one credential through the Hyperstack API.
 *
 * Custom data: issueid, a row of local_hyperstack_issue. A failed task is retried by Moodle
 * with a growing delay; issuer::process gives up on the last attempt.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issue_credential extends \core\task\adhoc_task {
    /**
     * Task name shown in the task logs.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:issue', 'local_hyperstack');
    }

    /**
     * Runs the task.
     */
    public function execute() {
        $data = $this->get_custom_data();
        issuer::process((int) $data->issueid, $this->get_attempts_available() <= 1);
    }
}
