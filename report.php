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
 * A course's credentials: who received what, and why a credential was not sent.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_hyperstack\issuer;

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$retry = optional_param('retry', 0, PARAM_INT);
$perpage = 50;

$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/hyperstack:manage', $context);

$url = new moodle_url('/local/hyperstack/report.php', ['id' => $course->id]);
$PAGE->set_url($url, ['page' => $page]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('report:title', 'local_hyperstack') . moodle_page::TITLE_SEPARATOR . $course->shortname);
$PAGE->set_heading($course->fullname);

if ($retry && confirm_sesskey()) {
    if (issuer::retry($retry, $course->id)) {
        redirect($url, get_string('report:retried', 'local_hyperstack'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($url);
}

$counts = $DB->get_records_sql_menu(
    'SELECT status, COUNT(1) FROM {local_hyperstack_issue} WHERE courseid = :course GROUP BY status',
    ['course' => $course->id]
);
$total = array_sum($counts);

$namefields = implode(', ', array_map(fn($field) => 'u.' . $field, \core_user\fields::get_name_fields()));
$rows = $DB->get_records_sql(
    "SELECT i.*, u.email, $namefields
       FROM {local_hyperstack_issue} i
       JOIN {user} u ON u.id = i.userid
      WHERE i.courseid = :course
   ORDER BY i.timemodified DESC, i.id DESC",
    ['course' => $course->id],
    $page * $perpage,
    $perpage
);

$showemail = has_capability('moodle/site:viewuseridentity', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report:title', 'local_hyperstack'));
echo html_writer::tag('p', html_writer::link(
    new moodle_url('/local/hyperstack/course.php', ['id' => $course->id]),
    get_string('report:back', 'local_hyperstack')
));

if (!$total) {
    echo $OUTPUT->notification(get_string('report:empty', 'local_hyperstack'), 'info');
    echo $OUTPUT->footer();
    die();
}

$summary = [];
foreach ([issuer::ISSUED, issuer::EXISTS, issuer::QUEUED, issuer::RETRYING, issuer::FAILED, issuer::SKIPPED] as $status) {
    if (!empty($counts[$status])) {
        $summary[] = get_string('status:' . $status, 'local_hyperstack') . ': ' . $counts[$status];
    }
}
echo html_writer::tag('p', implode(' · ', $summary));

$table = new html_table();
$table->head = [
    get_string('report:learner', 'local_hyperstack'),
    get_string('report:group', 'local_hyperstack'),
    get_string('report:status', 'local_hyperstack'),
    get_string('report:details', 'local_hyperstack'),
    get_string('report:updated', 'local_hyperstack'),
    '',
];
// Background utilities that Bootstrap 4 (Moodle 4.5) and Bootstrap 5 (Moodle 5.x) both have.
$badges = [
    issuer::ISSUED => 'bg-success text-white', issuer::EXISTS => 'bg-success text-white',
    issuer::QUEUED => 'bg-secondary text-white', issuer::RETRYING => 'bg-warning text-dark',
    issuer::FAILED => 'bg-danger text-white', issuer::SKIPPED => 'bg-secondary text-white',
];
foreach ($rows as $row) {
    $learner = html_writer::link(
        new moodle_url('/user/view.php', ['id' => $row->userid, 'course' => $course->id]),
        fullname($row)
    );
    if ($showemail) {
        $learner .= html_writer::div(s($row->email), 'small text-muted');
    }

    $status = html_writer::span(
        get_string('status:' . $row->status, 'local_hyperstack'),
        'badge ' . ($badges[$row->status] ?? 'bg-secondary text-white')
    );

    $details = '';
    if ($row->credentialurl) {
        $details = html_writer::link(
            $row->credentialurl,
            get_string('report:view', 'local_hyperstack'),
            ['target' => '_blank', 'rel' => 'noopener']
        );
    } else if ($row->message) {
        $details = s($row->message);
    }

    $actions = '';
    if (in_array($row->status, issuer::RETRYABLE)) {
        $actions = $OUTPUT->single_button(
            new moodle_url($url, ['retry' => $row->id]),
            get_string('report:retry', 'local_hyperstack'),
            'post'
        );
    }

    $table->data[] = [$learner, format_string($row->grouptitle), $status, $details, userdate($row->timemodified), $actions];
}

echo html_writer::table($table);
echo $OUTPUT->paging_bar($total, $page, $perpage, $url);
echo $OUTPUT->footer();
