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

use local_alborhancounts\check\vendorpatch;
use local_alborhancounts\local\cards;

/**
 * Tests for the hourly vendor patch task.
 *
 * @package    local_alborhancounts
 * @category   test
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(verify_vendor_patch::class)]
final class verify_vendor_patch_test extends \advanced_testcase {
    /**
     * The task with its check replaced by one on a fixture file.
     *
     * @param string $code Content of the fixture file.
     * @return verify_vendor_patch
     */
    private function task_for(string $code): verify_vendor_patch {
        $path = make_request_directory() . '/coursehandler.php';
        file_put_contents($path, $code);
        $check = new vendorpatch([['fixture', $path, cards::PATCH_MARKER, '\local_alborhancounts\local\cards::count_students(',
            'count($this->get_enrolled_students($course, $context))']]);
        return new class ($check) extends verify_vendor_patch {
            /**
             * Constructor.
             *
             * @param vendorpatch $check
             */
            public function __construct(
                /** @var vendorpatch The check to run. */
                private readonly vendorpatch $check,
            ) {
            }

            #[\Override]
            protected function get_check(): vendorpatch {
                return $this->check;
            }
        };
    }

    /**
     * The task is registered to run every hour.
     */
    public function test_registered(): void {
        $this->resetAfterTest();
        $task = \core\task\manager::get_scheduled_task(verify_vendor_patch::class);
        $this->assertInstanceOf(verify_vendor_patch::class, $task);
        $this->assertSame('17', $task->get_minute());
        $this->assertSame('*', $task->get_hour());
        $this->assertNotEmpty($task->get_name());
    }

    /**
     * Passes when the check is not applicable (CI has no Edwiser plugin) or OK.
     */
    public function test_passes(): void {
        $this->resetAfterTest();
        $this->expectOutputRegex('/' . preg_quote(get_string('checkvendorpatch_ok', 'local_alborhancounts'), '/') . '/');
        (new verify_vendor_patch())->execute();
        $this->task_for("<?php\n// " . cards::PATCH_MARKER . "\n\\local_alborhancounts\\local\\cards::count_students(\$c, \$x);\n")
            ->execute();
    }

    /**
     * Fails when a vendor file lost the hook.
     */
    public function test_fails_when_unpatched(): void {
        $this->resetAfterTest();
        ob_start();
        try {
            $this->task_for("<?php\n\$n = count(\$this->get_enrolled_students(\$course, \$context));\n")->execute();
            $this->fail('The task should have failed.');
        } catch (\moodle_exception $e) {
            $this->assertSame('taskvendorpatchmissing', $e->errorcode);
        } finally {
            $output = ob_get_clean();
        }
        $this->assertStringContainsString(get_string('checkvendorpatch_missing', 'local_alborhancounts'), $output);
    }
}
