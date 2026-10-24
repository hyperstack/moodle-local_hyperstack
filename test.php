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
 * Tests the saved API key against Hyperstack.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_hyperstack\api\api_exception;
use local_hyperstack\api\client;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_hyperstack_test');

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_hyperstack']);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('test:title', 'local_hyperstack'));

if (!client::is_configured()) {
    echo $OUTPUT->notification(get_string('error:notconfigured', 'local_hyperstack'), 'warning');
} else {
    try {
        $info = (new client())->info();
        echo $OUTPUT->notification(get_string('test:ok', 'local_hyperstack', [
            'name' => s($info['account_name']),
            'credits' => s($info['credit_balance'] ?? '?'),
        ]), 'success');
    } catch (api_exception $e) {
        echo $OUTPUT->notification(get_string('test:failed', 'local_hyperstack', s($e->get_reason())), 'error');
    }
}

echo $OUTPUT->continue_button($settingsurl);
echo $OUTPUT->footer();
