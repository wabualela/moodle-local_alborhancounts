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

namespace local_alborhancounts\check;

use core\check\result;
use local_alborhancounts\local\cards;

/**
 * Tests for the vendor patch status check, on fixture files.
 *
 * @package    local_alborhancounts
 * @category   test
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(vendorpatch::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_alborhancounts_status_checks')]
final class vendorpatch_test extends \advanced_testcase {
    /** @var string Hook text the patch adds. */
    private const NEEDLE = '\local_alborhancounts\local\cards::count_students(';

    /** @var string Vendor line the patch replaces. */
    private const VENDORLINE = 'count($this->get_enrolled_students($course, $context))';

    /**
     * Write a fixture file and return a check target for it.
     *
     * @param string $label
     * @param string $code File content.
     * @return array
     */
    private function target(string $label, string $code): array {
        $path = make_request_directory() . "/$label.php";
        file_put_contents($path, $code);
        return [$label, $path, cards::PATCH_MARKER, self::NEEDLE, self::VENDORLINE];
    }

    /**
     * Patched file.
     *
     * @return array
     */
    private function patched(): array {
        return $this->target('patched', "<?php\n// " . cards::PATCH_MARKER . "\n\$n = class_exists(x::class) ? "
            . self::NEEDLE . "\$course, \$context) : null;\n\$n ??= " . self::VENDORLINE . ";\n");
    }

    /**
     * Each kind of file gives the expected status.
     */
    public function test_results(): void {
        $this->resetAfterTest();

        $result = (new vendorpatch([$this->patched()]))->get_result();
        $this->assertSame(result::OK, $result->get_status());
        $this->assertStringContainsString('patched', $result->get_details());

        $unpatched = $this->target('unpatched', "<?php\n\$n = " . self::VENDORLINE . ";\n");
        $result = (new vendorpatch([$this->patched(), $unpatched]))->get_result();
        $this->assertSame(result::WARNING, $result->get_status());
        $this->assertStringContainsString(
            get_string('checkvendorpatch_unpatched', 'local_alborhancounts', (object) ['label' => 'unpatched',
                'path' => $unpatched[1]]),
            $result->get_details()
        );

        $changed = $this->target('changed', "<?php\n\$n = something_else();\n");
        $result = (new vendorpatch([$changed]))->get_result();
        $this->assertSame(result::WARNING, $result->get_status());
        $this->assertStringContainsString('changed', $result->get_details());

        $missing = ['missing', make_request_directory() . '/nofile.php', cards::PATCH_MARKER, self::NEEDLE,
            self::VENDORLINE];
        $this->assertSame(result::WARNING, (new vendorpatch([$missing]))->get_result()->get_status());
    }

    /**
     * Not applicable without vendor files or when switched off; INFO when the card counts are switched off.
     */
    public function test_settings(): void {
        $this->resetAfterTest();

        $result = (new vendorpatch([]))->get_result();
        $this->assertSame(result::NA, $result->get_status());

        // CI has no Edwiser plugin, so the real targets are empty too.
        $this->assertSame(result::NA, (new vendorpatch())->get_result()->get_status());

        set_config('cardcounts', 0, 'local_alborhancounts');
        $result = (new vendorpatch([$this->patched()]))->get_result();
        $this->assertSame(result::INFO, $result->get_status());
        $this->assertStringContainsString(
            get_string('checkvendorpatch_switchedoff', 'local_alborhancounts'),
            $result->get_details()
        );

        set_config('vendorpatchcheck', 0, 'local_alborhancounts');
        $this->assertSame(result::NA, (new vendorpatch([$this->patched()]))->get_result()->get_status());
    }

    /**
     * The expected patches come from the setting; unknown names are ignored.
     */
    public function test_expected_patches(): void {
        $this->resetAfterTest();
        $this->assertSame(['remui-counts', 'remui-enrolpage-limits'], vendorpatch::expected_patches());
        set_config('vendorpatches', ' remui-counts , bogus', 'local_alborhancounts');
        $this->assertSame(['remui-counts'], vendorpatch::expected_patches());
    }

    /**
     * The check is registered for the system status report.
     */
    public function test_registered(): void {
        $checks = array_filter(
            \core\check\manager::get_status_checks(),
            fn($check) => $check instanceof vendorpatch
        );
        $this->assertCount(1, $checks);
        $this->assertSame(get_string('checkvendorpatch', 'local_alborhancounts'), reset($checks)->get_name());
    }
}
