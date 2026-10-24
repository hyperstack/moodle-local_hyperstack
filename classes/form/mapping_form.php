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

namespace local_hyperstack\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The credential groups a course issues: one checkbox per group.
 *
 * Custom data: choices, a list of ['key' => ..., 'label' => ..., 'mapped' => bool].
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mapping_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $choices = $this->_customdata['choices'];

        $mform->addElement('header', 'groupsheader', get_string('mapping:groups', 'local_hyperstack'));
        $mform->addElement('static', 'groupshelp', '', get_string('mapping:help', 'local_hyperstack'));

        if (!$choices) {
            $mform->addElement('static', 'nogroups', '', get_string('mapping:nogroups', 'local_hyperstack'));
            return;
        }

        foreach ($choices as $i => $choice) {
            $mform->addElement('advcheckbox', 'group' . $i, '', $choice['label'], ['group' => 1], [0, 1]);
            $mform->setDefault('group' . $i, $choice['mapped'] ? 1 : 0);
        }

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * The keys of the groups that are ticked.
     *
     * @param \stdClass $data From get_data()
     * @return string[]
     */
    public function ticked(\stdClass $data): array {
        $keys = [];
        foreach ($this->_customdata['choices'] as $i => $choice) {
            if (!empty($data->{'group' . $i})) {
                $keys[] = $choice['key'];
            }
        }
        return $keys;
    }
}
