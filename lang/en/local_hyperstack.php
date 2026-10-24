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
 * English strings.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apierror'] = 'Hyperstack: {$a}';
$string['coursenav'] = 'Hyperstack credentials';
$string['error:groups'] = 'Could not load the credential groups from Hyperstack: {$a}';
$string['error:invalidkey'] = 'Hyperstack did not accept the API key. Check the key in the plugin settings.';
$string['error:lowbalance'] = 'The Hyperstack account has no credits left. Moodle will try again later.';
$string['error:network'] = 'Could not reach Hyperstack: {$a}';
$string['error:notconfigured'] = 'No Hyperstack API key is saved. Add one in the plugin settings.';
$string['error:notconnected'] = 'This site is not connected to Hyperstack yet. A site administrator must add the API key.';
$string['error:server'] = 'Hyperstack answered with an error (HTTP {$a}).';
$string['hyperstack:manage'] = 'Manage Hyperstack credentials in a course';
$string['mapping:gone'] = '{$a} (no longer in Hyperstack)';
$string['mapping:groups'] = 'Credential groups';
$string['mapping:help'] = 'When a learner completes this course, Hyperstack issues a credential from each group you select and emails it to the learner.';
$string['mapping:nogroups'] = 'The Hyperstack account has no credential groups. Create one in Hyperstack first.';
$string['mapping:saved'] = 'Credential groups saved.';
$string['past:button'] = 'Issue to past completers';
$string['past:help'] = '{$a} learners have already completed this course. Learners who already have a credential from these groups through this course are not sent another one.';
$string['past:queued'] = '{$a} credentials queued. They are sent in the next minutes.';
$string['past:title'] = 'Learners who completed earlier';
$string['pluginname'] = 'Hyperstack Credentials';
$string['privacy:metadata:hyperstack'] = 'To issue a credential, the learner\'s details are sent to Hyperstack, which creates the credential and emails it to the learner.';
$string['privacy:metadata:hyperstack:email'] = 'The learner\'s email address, where the credential is sent.';
$string['privacy:metadata:hyperstack:fullname'] = 'The learner\'s full name, shown on the credential.';
$string['privacy:metadata:issue'] = 'The credentials issued to each learner for each course, and how issuing went.';
$string['privacy:metadata:issue:courseid'] = 'The course the learner completed.';
$string['privacy:metadata:issue:credentialurl'] = 'The address of the issued credential.';
$string['privacy:metadata:issue:grouptitle'] = 'The Hyperstack credential group.';
$string['privacy:metadata:issue:message'] = 'Why the credential was not issued, if it was not.';
$string['privacy:metadata:issue:status'] = 'Whether the credential was issued.';
$string['privacy:metadata:issue:timecreated'] = 'When the credential was queued.';
$string['privacy:metadata:issue:timemodified'] = 'When the status last changed.';
$string['privacy:metadata:issue:userid'] = 'The learner.';
$string['privacy:path'] = 'Hyperstack credentials';
$string['report:back'] = 'Back to the credential groups';
$string['report:details'] = 'Details';
$string['report:empty'] = 'No credentials yet. They appear here when learners complete the course.';
$string['report:group'] = 'Credential group';
$string['report:learner'] = 'Learner';
$string['report:open'] = 'See who received a credential';
$string['report:retried'] = 'Queued again.';
$string['report:retry'] = 'Retry';
$string['report:status'] = 'Status';
$string['report:title'] = 'Issued credentials';
$string['report:updated'] = 'Updated';
$string['report:view'] = 'View credential';
$string['settings:apikey'] = 'API key';
$string['settings:apikey_desc'] = 'In Hyperstack, open Settings → Developers and create an API key. It is stored encrypted.';
$string['settings:apiurl'] = 'API address';
$string['settings:apiurl_desc'] = 'Leave this as it is unless Hyperstack support asks you to change it.';
$string['settings:intro'] = 'Issue Hyperstack certificates and badges when learners complete a course. A Hyperstack account is required: sign up at <a href="https://thehyperstack.com" target="_blank">thehyperstack.com</a>. After you save the API key, <a href="{$a}">test the connection</a>. Teachers then choose credential groups in each course under More → Hyperstack credentials. Course completion is calculated by cron, so cron must run.';
$string['settings:open'] = 'Open the plugin settings.';
$string['skip:unmapped'] = 'The credential group is no longer selected for this course.';
$string['skip:userdeleted'] = 'The learner\'s account was deleted.';
$string['skip:usersuspended'] = 'The learner\'s account is suspended.';
$string['status:exists'] = 'Already had it';
$string['status:failed'] = 'Failed';
$string['status:issued'] = 'Issued';
$string['status:queued'] = 'Queued';
$string['status:retrying'] = 'Retrying';
$string['status:skipped'] = 'Skipped';
$string['task:issue'] = 'Issue a Hyperstack credential';
$string['test:failed'] = 'The connection failed. {$a}';
$string['test:ok'] = 'Connected to the Hyperstack account "{$a->name}". Credits left: {$a->credits}.';
$string['test:title'] = 'Test the Hyperstack connection';
$string['warn:completiondisabled'] = 'Completion tracking is off for this course, so no credentials are issued. Turn it on in the course settings.';
$string['warn:nocriteria'] = 'This course has no completion conditions, so no credentials are issued. <a href="{$a}">Set the course completion conditions</a>.';
