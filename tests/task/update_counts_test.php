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

namespace local_alborhancounts\task;

/**
 * Tests for the scheduled task.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(update_counts::class)]
final class update_counts_test extends \advanced_testcase {
    /**
     * The task is registered as a daily night task.
     */
    public function test_registered(): void {
        $this->resetAfterTest();
        $task = \core\task\manager::get_scheduled_task(update_counts::class);
        $this->assertInstanceOf(update_counts::class, $task);
        $this->assertSame('40', $task->get_minute());
        $this->assertSame('1', $task->get_hour());
        $this->assertNotEmpty($task->get_name());
    }

    /**
     * The task updates the markers when enabled and does nothing when disabled.
     */
    public function test_execute(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('blocknames', 'html', 'local_alborhancounts');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $generator->enrol_user($generator->create_user()->id, $course->id, 'student');
        $html = "<span data-alborhan-count=\"{$course->id}\">0</span>";
        $config = (object) ['text' => $html, 'format' => FORMAT_HTML];
        $instanceid = $DB->insert_record('block_instances', (object) [
            'blockname' => 'html',
            'parentcontextid' => \context_system::instance()->id,
            'showinsubcontexts' => 0,
            'requiredbytheme' => 0,
            'pagetypepattern' => 'site-index',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => base64_encode(serialize($config)),
            'timecreated' => 1000,
            'timemodified' => 1000,
        ]);
        $text = fn() => unserialize_object(base64_decode(
            $DB->get_field('block_instances', 'configdata', ['id' => $instanceid])
        ))->text;

        set_config('enabled', 0, 'local_alborhancounts');
        $this->expectOutputRegex('/disabled.*Course \d+: 1 students.*blocks saved: 1/s');
        (new update_counts())->execute();
        $this->assertSame($html, $text());

        set_config('enabled', 1, 'local_alborhancounts');
        (new update_counts())->execute();
        $this->assertSame("<span data-alborhan-count=\"{$course->id}\">1</span>", $text());
    }

    /**
     * Insert an html block holding the given text.
     *
     * @param string $html
     * @return int Block instance id.
     */
    private function create_block(string $html): int {
        global $DB;
        return $DB->insert_record('block_instances', (object) [
            'blockname' => 'html',
            'parentcontextid' => \context_system::instance()->id,
            'showinsubcontexts' => 0,
            'requiredbytheme' => 0,
            'pagetypepattern' => 'site-index',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => base64_encode(serialize((object) ['text' => $html, 'format' => FORMAT_HTML])),
            'timecreated' => 1000,
            'timemodified' => 1000,
        ]);
    }

    /**
     * The task fails, after saving what it can, when a marker could not be updated.
     */
    public function test_execute_fails_on_skipped_markers(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('blocknames', 'html', 'local_alborhancounts');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $generator->enrol_user($generator->create_user()->id, $course->id, 'student');
        $instanceid = $this->create_block("<span data-alborhan-count=\"{$course->id}\">0</span>"
            . '<span data-alborhan-count="999999">5</span>');

        ob_start();
        try {
            (new update_counts())->execute();
            $this->fail('The task should have failed.');
        } catch (\moodle_exception $e) {
            $this->assertSame('taskproblems', $e->errorcode);
            $this->assertStringContainsString('999999', $e->getMessage());
        } finally {
            ob_end_clean();
        }
        $config = unserialize_object(base64_decode($DB->get_field('block_instances', 'configdata', ['id' => $instanceid])));
        $this->assertStringContainsString("data-alborhan-count=\"{$course->id}\">1</span>", $config->text);
    }

    /**
     * The task fails when markers it updated before have disappeared.
     */
    public function test_execute_fails_when_markers_vanish(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('blocknames', 'html', 'local_alborhancounts');
        $instanceid = $this->create_block('<p>[edwiser-fc courseid="5"]</p>');
        $DB->insert_record('local_alborhancounts_bak', (object) ['blockinstanceid' => $instanceid, 'configdata' => 'x',
            'timecreated' => 1]);

        ob_start();
        try {
            (new update_counts())->execute();
            $this->fail('The task should have failed.');
        } catch (\moodle_exception $e) {
            $this->assertSame('taskproblems', $e->errorcode);
        } finally {
            $output = ob_get_clean();
        }
        $this->assertStringContainsString('No count markers found', $output);
    }

    /**
     * The task fails when one of several updated blocks loses its markers.
     */
    public function test_execute_fails_when_one_block_loses_markers(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('blocknames', 'html', 'local_alborhancounts');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $generator->enrol_user($generator->create_user()->id, $course->id, 'student');
        $first = $this->create_block("<span data-alborhan-count=\"{$course->id}\">0</span>");
        $second = $this->create_block("<span data-alborhan-count=\"{$course->id}\">0</span>");
        ob_start();
        (new update_counts())->execute();
        ob_end_clean();
        $shortcodeonly = base64_encode(serialize((object) ['text' => '[edwiser-fc courseid="1"]', 'format' => FORMAT_HTML]));
        $DB->set_field('block_instances', 'configdata', $shortcodeonly, ['id' => $first]);

        ob_start();
        try {
            (new update_counts())->execute();
            $this->fail('The task should have failed.');
        } catch (\moodle_exception $e) {
            $this->assertSame('taskproblems', $e->errorcode);
            $this->assertStringContainsString("block(s) $first", $e->getMessage());
            $this->assertStringNotContainsString((string) $second, $e->getMessage());
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Without markers and without earlier updates (plugin installed, cards not converted yet) the task succeeds.
     */
    public function test_execute_without_markers(): void {
        $this->resetAfterTest();
        $this->expectOutputRegex('/No count markers found/');
        (new update_counts())->execute();
    }
}
