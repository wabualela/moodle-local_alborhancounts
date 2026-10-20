<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_alborhancounts\local;

use core\context\course as context_course;

/**
 * Tests for the card count helper: the same numbers as the vendor code, the caches and their invalidation.
 *
 * The vendor function is compared through a verbatim copy (tests/fixtures/vendor_reference.php), because CI has no
 * Edwiser plugins.
 *
 * @package    local_alborhancounts
 * @category   test
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cards::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_alborhancounts\observer::class)]
final class cards_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/alborhancounts/tests/fixtures/vendor_reference.php');
        parent::setUpBeforeClass();
    }

    /**
     * Vendor count for the current user.
     *
     * @param \stdClass $course
     * @return int
     */
    private function vendor(\stdClass $course): int {
        return count(\local_alborhancounts_vendor_reference::get_enrolled_students(
            $course,
            context_course::instance($course->id)
        ));
    }

    /**
     * Our count, uncached, for the current user.
     *
     * @param \stdClass $course
     * @return int
     */
    private function ours(\stdClass $course): int {
        return cards::compute($course, context_course::instance($course->id));
    }

    /**
     * Our count through the cached entry point, as the patched vendor code calls it.
     *
     * @param \stdClass $course
     * @return int|null
     */
    private function hook(\stdClass $course): ?int {
        return cards::count_students($course, context_course::instance($course->id));
    }

    /**
     * Forget this request's memo, as if a new request started.
     */
    private function new_request(): void {
        \cache::make('local_alborhancounts', 'cardcountsrequest')->purge();
    }

    /**
     * Course without groups: active, suspended, disabled-instance, double and deleted enrolments; teacher not counted.
     */
    public function test_same_as_vendor_without_groups(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => NOGROUPS]);
        $active = $generator->create_and_enrol($course, 'student');
        $generator->create_and_enrol($course, 'student', null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $generator->create_and_enrol($course, 'editingteacher');
        $deleted = $generator->create_and_enrol($course, 'student');
        // A deleted account whose enrolment and role were left behind.
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);
        // A second enrolment of the same student, in a disabled self enrolment instance.
        $self = enrol_get_plugin('self');
        $selfid = $DB->get_field('enrol', 'id', ['courseid' => $course->id, 'enrol' => 'self']);
        if (!$selfid) {
            $selfid = $self->add_instance($course);
        }
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $selfid]);
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $self->enrol_user($DB->get_record('enrol', ['id' => $selfid], '*', MUST_EXIST), $active->id, $studentrole);

        foreach ([0, $active->id, get_admin()->id] as $viewer) {
            $this->setUser($viewer);
            $this->assertSame(2, $this->ours($course), "viewer $viewer");
            $this->assertSame($this->vendor($course), $this->ours($course), "viewer $viewer");
        }
        $this->setGuestUser();
        $this->assertSame($this->vendor($course), $this->ours($course));
    }

    /**
     * Separate groups: the viewer's groups filter the count unless the viewer can access all groups.
     */
    public function test_same_as_vendor_with_separate_groups(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $a = $generator->create_group(['courseid' => $course->id]);
        $b = $generator->create_group(['courseid' => $course->id, 'visibility' => GROUPS_VISIBILITY_MEMBERS]);
        $ina = $generator->create_and_enrol($course, 'student');
        $inb = $generator->create_and_enrol($course, 'student');
        $inab = $generator->create_and_enrol($course, 'student');
        $nogroup = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $nonediting = $generator->create_and_enrol($course, 'teacher');
        foreach ([[$ina, $a], [$inb, $b], [$inab, $a], [$inab, $b], [$nonediting, $a]] as [$user, $group]) {
            $generator->create_group_member(['userid' => $user->id, 'groupid' => $group->id]);
        }
        $expected = [
            0 => 0,
            $ina->id => 2,
            $inb->id => 2,
            $inab->id => 3,
            $nogroup->id => 0,
            $teacher->id => 4,
            $nonediting->id => 2,
            get_admin()->id => 4,
        ];
        foreach ($expected as $viewer => $count) {
            $this->setUser($viewer);
            $this->assertSame($count, $this->ours($course), "viewer $viewer");
            $this->assertSame($this->vendor($course), $this->ours($course), "viewer $viewer");
            $this->new_request();
            $this->assertSame($count, $this->hook($course), "viewer $viewer through the hook");
        }
        $this->setGuestUser();
        $this->assertSame(0, $this->ours($course));
        $this->assertSame($this->vendor($course), $this->ours($course));
    }

    /**
     * A count that depends on the viewer is never memoised or shared, so one viewer's number never reaches another.
     */
    public function test_no_leak_between_viewers(): void {
        $this->resetAfterTest();
        set_config('cardcachettl', 600, 'local_alborhancounts');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $group = $generator->create_group(['courseid' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $generator->create_group_member(['userid' => $student->id, 'groupid' => $group->id]);
        $generator->create_and_enrol($course, 'student');
        $generator->create_and_enrol($course, 'student');

        $this->setAdminUser();
        $this->assertSame(3, $this->hook($course));
        $this->setUser($student);
        $this->assertSame(1, $this->hook($course));
        $this->setUser(0);
        $this->assertSame(0, $this->hook($course));
    }

    /**
     * A second call in the same request reads nothing from the database; an enrolment drops the memo at once.
     */
    public function test_request_memo(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $generator->create_and_enrol($course, 'student');
        $this->assertSame(1, $this->hook($course));

        $reads = $DB->perf_get_reads();
        $this->assertSame(1, $this->hook($course));
        $this->assertSame($reads, $DB->perf_get_reads());

        $user = $generator->create_and_enrol($course, 'student');
        $this->assertSame(2, $this->hook($course));
        $manual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($manual, $user->id);
        $this->assertSame(1, $this->hook($course));
    }

    /**
     * Shared cache: off by default; with a lifetime it keeps the number until an event or the lifetime ends.
     */
    public function test_shared_cache_lifetime(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $users = [];
        for ($i = 0; $i < 3; $i++) {
            $users[] = $generator->create_and_enrol($course, 'student');
        }

        // Default 0: a change made without any event is seen by the next request.
        $this->assertSame(3, $this->hook($course));
        $DB->delete_records('user_enrolments', ['userid' => $users[0]->id]);
        $this->new_request();
        $this->assertSame(2, $this->hook($course));

        // With a lifetime the shared value is kept across requests...
        set_config('cardcachettl', 600, 'local_alborhancounts');
        $this->new_request();
        $this->assertSame(2, $this->hook($course));
        $DB->delete_records('user_enrolments', ['userid' => $users[1]->id]);
        $this->new_request();
        $this->assertSame(2, $this->hook($course));

        // ...until it expires.
        \cache::make('local_alborhancounts', 'studentcounts')->set($course->id, ['n' => 2, 't' => time() - 601]);
        $this->new_request();
        $this->assertSame(1, $this->hook($course));
    }

    /**
     * A role given at category level, and a prohibit override of the student capability, drop the cached numbers.
     */
    public function test_role_and_capability_changes(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $generator->create_and_enrol($course, 'student');
        // Enrolled without any role: not counted until a role gives the capability.
        $norole = $generator->create_and_enrol($course, null);
        $this->assertSame(1, $this->hook($course));

        $studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        role_assign($studentrole, $norole->id, \core\context\coursecat::instance($category->id)->id);
        $this->assertSame(2, $this->hook($course));

        $context = context_course::instance($course->id);
        assign_capability('moodle/course:isincompletionreports', CAP_PROHIBIT, $studentrole, $context->id);
        $this->assertSame(0, $this->hook($course));
        $this->assertSame($this->vendor($course), $this->ours($course));
    }

    /**
     * A course record without groupmode is counted as a course without groups.
     */
    public function test_record_without_groupmode(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $generator->create_and_enrol($course, 'student');
        $generator->create_and_enrol($course, 'student');
        $this->setUser(0);
        $this->assertSame(2, cards::compute((object) ['id' => $course->id], context_course::instance($course->id)));
    }

    /**
     * Switched off, or plugin not installed yet: null, so the vendor code counts.
     */
    public function test_switched_off_returns_null(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        set_config('cardcounts', 0, 'local_alborhancounts');
        $this->assertNull($this->hook($course));

        set_config('cardcounts', 1, 'local_alborhancounts');
        $this->assertSame(0, $this->hook($course));
        unset_config('version', 'local_alborhancounts');
        $this->new_request();
        $this->assertNull($this->hook($course));
    }

    /**
     * The self test reports a working helper.
     */
    public function test_self_test(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_course();
        $this->assertNull(cards::self_test());
    }
}
