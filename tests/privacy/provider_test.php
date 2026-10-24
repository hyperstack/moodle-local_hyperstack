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

namespace local_hyperstack\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Tests for the privacy provider.
 *
 * @package    local_hyperstack
 * @copyright  2026 Hyperstack Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_hyperstack\privacy\provider
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /** @var \stdClass[] */
    private $courses;

    /** @var \stdClass[] */
    private $users;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->courses = [$generator->create_course(), $generator->create_course()];
        $this->users = [$generator->create_user(), $generator->create_user()];
        foreach ($this->courses as $course) {
            foreach ($this->users as $user) {
                $DB->insert_record('local_hyperstack_issue', (object) [
                    'userid' => $user->id, 'courseid' => $course->id, 'groupkey' => 'grp1',
                    'grouptitle' => 'Python basics', 'status' => 'issued',
                    'credentialurl' => 'https://hyperstack.id/credential/x', 'timecreated' => time(), 'timemodified' => time(),
                ]);
            }
        }
    }

    /**
     * Rows left for a user.
     *
     * @param \stdClass $user
     * @return int
     */
    private function rows(\stdClass $user): int {
        global $DB;
        return $DB->count_records('local_hyperstack_issue', ['userid' => $user->id]);
    }

    public function test_metadata_declares_the_table_and_hyperstack(): void {
        $items = provider::get_metadata(new collection('local_hyperstack'))->get_collection();
        $names = array_map(fn($item) => $item->get_name(), $items);
        $this->assertContains('local_hyperstack_issue', $names);
        $this->assertContains('hyperstack', $names);
    }

    public function test_contexts_and_users(): void {
        $contexts = provider::get_contexts_for_userid($this->users[0]->id)->get_contextids();
        $this->assertCount(2, $contexts);

        $context = \context_course::instance($this->courses[0]->id);
        $userlist = new userlist($context, 'local_hyperstack');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->users[0]->id, $this->users[1]->id], $userlist->get_userids());
    }

    public function test_export(): void {
        $context = \context_course::instance($this->courses[0]->id);
        $this->export_context_data_for_user($this->users[0]->id, $context, 'local_hyperstack');

        $data = writer::with_context($context)->get_data([get_string('privacy:path', 'local_hyperstack')]);
        $this->assertCount(1, $data->credentials);
        $this->assertSame('Python basics', $data->credentials[0]->group);
    }

    public function test_delete_for_one_user(): void {
        $context = \context_course::instance($this->courses[0]->id);
        provider::delete_data_for_user(new approved_contextlist($this->users[0], 'local_hyperstack', [$context->id]));

        $this->assertSame(1, $this->rows($this->users[0]));
        $this->assertSame(2, $this->rows($this->users[1]));
    }

    public function test_delete_for_users_and_whole_context(): void {
        $context = \context_course::instance($this->courses[0]->id);
        provider::delete_data_for_users(new approved_userlist($context, 'local_hyperstack', [$this->users[1]->id]));
        $this->assertSame(1, $this->rows($this->users[1]));
        $this->assertSame(2, $this->rows($this->users[0]));

        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(1, $this->rows($this->users[0]));
    }
}
