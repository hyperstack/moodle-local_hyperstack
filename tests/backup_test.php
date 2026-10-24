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

namespace local_hyperstack;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests that a course backup carries its credential groups and not its issue rows.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_local_hyperstack_plugin
 * @covers     \restore_local_hyperstack_plugin
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_local_hyperstack_plugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_local_hyperstack_plugin::class)]
final class backup_test extends \advanced_testcase {
    public function test_a_restored_course_keeps_its_credential_groups(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        foreach (['grp1' => 'Python basics', 'grp2' => 'Python badge'] as $key => $title) {
            $DB->insert_record('local_hyperstack_map', (object) [
                'courseid' => $course->id, 'groupkey' => $key, 'grouptitle' => $title,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        $DB->insert_record('local_hyperstack_issue', (object) [
            'userid' => $user->id, 'courseid' => $course->id, 'groupkey' => 'grp1', 'grouptitle' => 'Python basics',
            'status' => 'issued', 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newid = \restore_dbops::create_new_course('Copy', 'copy', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $keys = $DB->get_fieldset_select('local_hyperstack_map', 'groupkey', 'courseid = ?', [$newid]);
        sort($keys);
        $this->assertSame(['grp1', 'grp2'], $keys);
        $this->assertSame(0, $DB->count_records('local_hyperstack_issue', ['courseid' => $newid]));
    }
}
