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
 * A course's credential groups: which Hyperstack groups issue when a learner completes the course.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_hyperstack\api\api_exception;
use local_hyperstack\api\client;
use local_hyperstack\form\mapping_form;
use local_hyperstack\issuer;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/hyperstack:manage', $context);

$url = new moodle_url('/local/hyperstack/course.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coursenav', 'local_hyperstack') . moodle_page::TITLE_SEPARATOR . $course->shortname);
$PAGE->set_heading($course->fullname);

if ($action === 'pastcompleters' && confirm_sesskey()) {
    $queued = issuer::queue_past_completers($course->id);
    redirect($url, get_string('past:queued', 'local_hyperstack', $queued), null, \core\output\notification::NOTIFY_SUCCESS);
}

$configured = client::is_configured();
$apierror = null;
$form = null;
$mapped = $DB->get_records('local_hyperstack_map', ['courseid' => $course->id], 'grouptitle', 'groupkey, id, grouptitle');

if ($configured) {
    try {
        $groups = (new client())->groups();
    } catch (api_exception $e) {
        $groups = null;
        $apierror = $e->get_reason();
    }

    if ($groups !== null) {
        // Groups that are not archived, plus any mapped group, even archived or gone from Hyperstack.
        $choices = [];
        $titles = [];
        foreach ($groups as $group) {
            $key = clean_param($group['group_id'] ?? '', PARAM_ALPHANUMEXT);
            if ($key === '' || (!empty($group['archived']) && !isset($mapped[$key]))) {
                continue;
            }
            $label = format_string($group['title'] ?? $key);
            if (!empty($group['group_code'])) {
                $label .= ' (' . format_string($group['group_code']) . ')';
            }
            $choices[] = ['key' => $key, 'label' => $label, 'mapped' => isset($mapped[$key])];
            $titles[$key] = clean_param($group['title'] ?? $key, PARAM_TEXT);
        }
        foreach ($mapped as $key => $map) {
            if (!isset($titles[$key])) {
                $label = get_string('mapping:gone', 'local_hyperstack', format_string($map->grouptitle));
                $choices[] = ['key' => $key, 'label' => $label, 'mapped' => true];
                $titles[$key] = $map->grouptitle;
            }
        }

        $form = new mapping_form($url, ['choices' => $choices]);
        if ($data = $form->get_data()) {
            $ticked = $form->ticked($data);
            $now = time();
            foreach ($ticked as $key) {
                if (!isset($mapped[$key])) {
                    $DB->insert_record('local_hyperstack_map', (object) [
                        'courseid' => $course->id,
                        'groupkey' => $key,
                        'grouptitle' => core_text::substr($titles[$key], 0, 255),
                        'timecreated' => $now,
                        'timemodified' => $now,
                    ]);
                }
            }
            foreach ($mapped as $key => $map) {
                if (!in_array($key, $ticked)) {
                    $DB->delete_records('local_hyperstack_map', ['id' => $map->id]);
                }
            }
            redirect($url, get_string('mapping:saved', 'local_hyperstack'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursenav', 'local_hyperstack'));

if (!$configured) {
    $message = get_string('error:notconnected', 'local_hyperstack');
    if (has_capability('moodle/site:config', context_system::instance())) {
        $settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_hyperstack']);
        $message .= ' ' . html_writer::link($settingsurl, get_string('settings:open', 'local_hyperstack'));
    }
    echo $OUTPUT->notification($message, 'warning');
    echo $OUTPUT->footer();
    die();
}

// Course completion triggers issuing; without it nothing is ever sent.
$completion = new completion_info($course);
if (!$completion->is_enabled()) {
    echo $OUTPUT->notification(get_string('warn:completiondisabled', 'local_hyperstack'), 'warning');
} else if (!$completion->has_criteria()) {
    $criteriaurl = new moodle_url('/course/completion.php', ['id' => $course->id]);
    echo $OUTPUT->notification(get_string('warn:nocriteria', 'local_hyperstack', $criteriaurl->out()), 'warning');
}

if ($apierror !== null) {
    echo $OUTPUT->notification(get_string('error:groups', 'local_hyperstack', s($apierror)), 'error');
}
if ($form) {
    $form->display();
}

if ($mapped) {
    $completed = $DB->count_records_select(
        'course_completions',
        'course = :course AND timecompleted IS NOT NULL',
        ['course' => $course->id]
    );
    echo $OUTPUT->heading(get_string('past:title', 'local_hyperstack'), 3);
    echo html_writer::tag('p', get_string('past:help', 'local_hyperstack', $completed));
    $button = new single_button(
        new moodle_url($url, ['action' => 'pastcompleters']),
        get_string('past:button', 'local_hyperstack'),
        'post'
    );
    $button->disabled = $completed == 0;
    echo $OUTPUT->render($button);
}

echo $OUTPUT->heading(get_string('report:title', 'local_hyperstack'), 3);
echo html_writer::tag('p', html_writer::link(
    new moodle_url('/local/hyperstack/report.php', ['id' => $course->id]),
    get_string('report:open', 'local_hyperstack')
));

echo $OUTPUT->footer();
