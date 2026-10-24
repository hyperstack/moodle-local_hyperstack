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
 * Site settings: the Hyperstack API key.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_hyperstack', new lang_string('pluginname', 'local_hyperstack'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $testurl = new moodle_url('/local/hyperstack/test.php');
        $settings->add(new admin_setting_heading(
            'local_hyperstack/intro',
            '',
            new lang_string('settings:intro', 'local_hyperstack', $testurl->out())
        ));

        // Stored encrypted with the site's key, not as plain text.
        $settings->add(new admin_setting_encryptedpassword(
            'local_hyperstack/apikey',
            new lang_string('settings:apikey', 'local_hyperstack'),
            new lang_string('settings:apikey_desc', 'local_hyperstack')
        ));

        $settings->add(new admin_setting_configtext(
            'local_hyperstack/apiurl',
            new lang_string('settings:apiurl', 'local_hyperstack'),
            new lang_string('settings:apiurl_desc', 'local_hyperstack'),
            \local_hyperstack\api\client::DEFAULT_URL,
            PARAM_URL
        ));
    }

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_hyperstack_test',
        new lang_string('test:title', 'local_hyperstack'),
        new moodle_url('/local/hyperstack/test.php'),
        'moodle/site:config',
        true
    ));
}
