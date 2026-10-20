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

/**
 * Tests for the block updater.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(updater::class)]
final class updater_test extends \advanced_testcase {
    /**
     * Create a course with the given number of students.
     *
     * @param int $students
     * @return \stdClass
     */
    private function course_with_students(int $students): \stdClass {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        for ($i = 0; $i < $students; $i++) {
            $generator->enrol_user($generator->create_user()->id, $course->id, 'student');
        }
        return $course;
    }

    /**
     * Insert a block instance whose configuration looks like an Edwiser advanced block.
     *
     * @param string $html Block HTML.
     * @param string $blockname Block type.
     * @return int Block instance id.
     */
    private function create_block(string $html, string $blockname = 'html'): int {
        global $DB;
        $config = (object) [
            'html' => ['text' => $html, 'format' => FORMAT_HTML],
            'css' => ['text' => '.x { color: red; }', 'format' => FORMAT_HTML],
            'js' => ['text' => '', 'format' => FORMAT_HTML],
        ];
        return $DB->insert_record('block_instances', (object) [
            'blockname' => $blockname,
            'parentcontextid' => \context_system::instance()->id,
            'showinsubcontexts' => 0,
            'requiredbytheme' => 0,
            'pagetypepattern' => 'site-index',
            'subpagepattern' => null,
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => base64_encode(serialize($config)),
            'timecreated' => 1000,
            'timemodified' => 1000,
        ]);
    }

    /**
     * Read back the HTML of a block created by create_block().
     *
     * @param int $instanceid
     * @return string
     */
    private function block_html(int $instanceid): string {
        global $DB;
        $config = unserialize_object(base64_decode($DB->get_field('block_instances', 'configdata', ['id' => $instanceid])));
        return $config->html['text'];
    }

    /**
     * Run the updater without printing its progress.
     *
     * @param bool $dryrun
     * @return array
     */
    private function run_updater(bool $dryrun = false): array {
        return (new updater(new \null_progress_trace(), $dryrun))->run();
    }

    /**
     * Markers get the rounded counts, other content and settings are kept, and the old configuration is copied.
     */
    public function test_run_updates_markers(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('blocknames', 'html', 'local_alborhancounts');
        $big = $this->course_with_students(12);
        $small = $this->course_with_students(3);
        $html = "<div><span dir=\"ltr\" data-alborhan-count=\"{$big->id}\">4k</span> طالب</div>"
            . "<div><span data-alborhan-count=\"{$small->id}\">1</span></div>"
            . '<div><span data-alborhan-count="999999">5</span></div>';
        $instanceid = $this->create_block($html);
        $before = $DB->get_field('block_instances', 'configdata', ['id' => $instanceid]);

        $result = $this->run_updater();

        $this->assertSame("<div><span dir=\"ltr\" data-alborhan-count=\"{$big->id}\">+10</span> طالب</div>"
            . "<div><span data-alborhan-count=\"{$small->id}\">3</span></div>"
            . '<div><span data-alborhan-count="999999">5</span></div>', $this->block_html($instanceid));
        $this->assertSame(1, $result['blocks']);
        $this->assertSame([$instanceid], $result['found']);
        $this->assertSame(3, $result['markers']);
        $this->assertSame(2, $result['changed']);
        $this->assertSame([$instanceid], $result['updated']);
        $this->assertSame([999999], $result['skipped']);
        $this->assertSame([], $result['conflicts']);
        $this->assertSame([], $result['warnings']);

        $config = unserialize_object(base64_decode($DB->get_field('block_instances', 'configdata', ['id' => $instanceid])));
        $this->assertSame('.x { color: red; }', $config->css['text']);
        $this->assertGreaterThan(1000, $DB->get_field('block_instances', 'timemodified', ['id' => $instanceid]));

        $backups = $DB->get_records('local_alborhancounts_bak', ['blockinstanceid' => $instanceid]);
        $this->assertCount(1, $backups);
        $this->assertSame($before, reset($backups)->configdata);
    }

    /**
     * A second run with unchanged counts writes nothing.
     */
    public function test_run_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('blocknames', 'html', 'local_alborhancounts');
        $course = $this->course_with_students(4);
        $instanceid = $this->create_block("<span data-alborhan-count=\"{$course->id}\">0</span>");
        $this->run_updater();
        $DB->set_field('block_instances', 'timemodified', 2000, ['id' => $instanceid]);

        $result = $this->run_updater();

        $this->assertSame([], $result['updated']);
        $this->assertSame(0, $result['changed']);
        $this->assertEquals(2000, $DB->get_field('block_instances', 'timemodified', ['id' => $instanceid]));
        $this->assertSame(1, $DB->count_records('local_alborhancounts_bak'));
    }

    /**
     * A dry run reports the change but saves nothing.
     */
    public function test_dry_run(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('blocknames', 'html', 'local_alborhancounts');
        $course = $this->course_with_students(2);
        $html = "<span data-alborhan-count=\"{$course->id}\">0</span>";
        $instanceid = $this->create_block($html);

        $result = $this->run_updater(true);

        $this->assertSame([$instanceid], $result['updated']);
        $this->assertSame(1, $result['changed']);
        $this->assertSame($html, $this->block_html($instanceid));
        $this->assertSame(0, $DB->count_records('local_alborhancounts_bak'));
    }

    /**
     * Only the configured block types are touched; the default is the Edwiser advanced block.
     */
    public function test_block_types(): void {
        $this->resetAfterTest();
        $course = $this->course_with_students(2);
        $html = "<span data-alborhan-count=\"{$course->id}\">0</span>";
        $edwiser = $this->create_block($html, 'edwiseradvancedblock');
        $other = $this->create_block($html, 'html');

        $this->run_updater();

        $this->assertSame("<span data-alborhan-count=\"{$course->id}\">2</span>", $this->block_html($edwiser));
        $this->assertSame($html, $this->block_html($other));
        $this->assertSame(['edwiseradvancedblock'], updater::blocknames());

        set_config('blocknames', ' html , edwiseradvancedblock,,bad name! ', 'local_alborhancounts');
        $this->assertSame(['html', 'edwiseradvancedblock'], updater::blocknames());
    }

    /**
     * The count matches the Edwiser cards: students only, suspended enrolments included, deleted users excluded.
     */
    public function test_count_students(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $students = [];
        for ($i = 0; $i < 3; $i++) {
            $students[$i] = $generator->create_user();
            $generator->enrol_user($students[$i]->id, $course->id, 'student');
        }
        $generator->enrol_user($generator->create_user()->id, $course->id, 'editingteacher');
        $generator->enrol_user($generator->create_user()->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        delete_user($students[0]);
        $this->assertSame(3, updater::count_students($course->id));

        // An account flagged deleted while its enrolment remains is not counted either.
        $DB->set_field('user', 'deleted', 1, ['id' => $students[1]->id]);
        $this->assertSame(2, updater::count_students($course->id));
        $this->assertNull(updater::count_students(SITEID));
        $this->assertNull(updater::count_students($course->id + 1000));
    }

    /**
     * Only the newest copies are kept per block.
     */
    public function test_backups_pruned(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('blocknames', 'html', 'local_alborhancounts');
        $course = $this->course_with_students(1);
        $instanceid = $this->create_block("<span data-alborhan-count=\"{$course->id}\">0</span>");
        $otherid = $this->create_block('<p>no markers</p>');
        for ($i = 0; $i < updater::BACKUPS_KEPT + 2; $i++) {
            $DB->insert_record('local_alborhancounts_bak', (object) ['blockinstanceid' => $instanceid, 'configdata' => "old$i",
                'timecreated' => $i]);
        }
        $DB->insert_record('local_alborhancounts_bak', (object) ['blockinstanceid' => $otherid, 'configdata' => 'x',
            'timecreated' => 1]);

        $this->run_updater();

        $kept = $DB->count_records('local_alborhancounts_bak', ['blockinstanceid' => $instanceid]);
        $this->assertSame(updater::BACKUPS_KEPT, $kept);
        // The configdata column is TEXT, which DML does not allow in conditions: compare the values instead.
        $values = $DB->get_fieldset_select('local_alborhancounts_bak', 'configdata', 'blockinstanceid = ?', [$instanceid]);
        $this->assertNotContains('old0', $values);
        $this->assertNotContains('old2', $values);
        $this->assertContains('old3', $values);
        $this->assertSame(1, $DB->count_records('local_alborhancounts_bak', ['blockinstanceid' => $otherid]));
    }

    /**
     * A copy can be put back into its block.
     */
    public function test_restore(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('blocknames', 'html', 'local_alborhancounts');
        $course = $this->course_with_students(2);
        $html = "<span data-alborhan-count=\"{$course->id}\">0</span>";
        $instanceid = $this->create_block($html);
        $this->run_updater();
        $backup = $DB->get_record('local_alborhancounts_bak', ['blockinstanceid' => $instanceid], '*', MUST_EXIST);

        $updated = $DB->get_field('block_instances', 'configdata', ['id' => $instanceid]);

        [$restoredid, $copyid] = updater::restore($backup->id);

        $this->assertSame($instanceid, $restoredid);
        $this->assertSame($html, $this->block_html($instanceid));
        $copy = $DB->get_record('local_alborhancounts_bak', ['id' => $copyid], '*', MUST_EXIST);
        $this->assertEquals($instanceid, $copy->blockinstanceid);
        $this->assertSame($updated, $copy->configdata);
    }

    /**
     * A block changed after it was read is not overwritten.
     */
    public function test_save_skips_changed_block(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->create_block('<span data-alborhan-count="1">0</span>');
        $stale = $DB->get_field('block_instances', 'configdata', ['id' => $instanceid]);
        $edited = base64_encode(serialize((object) ['html' => ['text' => 'edited', 'format' => FORMAT_HTML]]));
        $DB->set_field('block_instances', 'configdata', $edited, ['id' => $instanceid]);
        $save = new \ReflectionMethod(updater::class, 'save');

        $updater = new updater(new \null_progress_trace());
        $saved = $save->invoke($updater, $instanceid, $stale, (object) ['html' => ['text' => 'x']]);

        $this->assertFalse($saved);
        $this->assertSame($edited, $DB->get_field('block_instances', 'configdata', ['id' => $instanceid]));
        $this->assertSame(0, $DB->count_records('local_alborhancounts_bak'));
    }

    /**
     * Blocks that still hold the Edwiser course-card component are reported.
     */
    public function test_warns_about_dynamic_component(): void {
        $this->resetAfterTest();
        set_config('blocknames', 'html', 'local_alborhancounts');
        $course = $this->course_with_students(1);
        $instanceid = $this->create_block('<div class="edwiser-fc" data-edwiser-dynamic="" data-shortcode="edwiser-fc">'
            . "[edwiser-fc courseid=\"{$course->id}\"]<span data-alborhan-count=\"{$course->id}\">0</span></div>");
        $plain = $this->create_block("<span data-alborhan-count=\"{$course->id}\">0</span>");

        $result = $this->run_updater(true);

        $this->assertSame([$instanceid], $result['warnings']);
        $this->assertSame([$instanceid, $plain], $result['updated']);
    }
}
