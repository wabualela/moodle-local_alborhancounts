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
 * Tests for the count formatter.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(formatter::class)]
final class formatter_test extends \basic_testcase {
    /**
     * Each count gives the expected text.
     *
     * @param int $count
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('format_provider')]
    public function test_format(int $count, string $expected): void {
        $this->assertSame($expected, formatter::format($count));
    }

    /**
     * Data provider for test_format().
     *
     * @return array
     */
    public static function format_provider(): array {
        return [
            'negative is zero' => [-5, '0'],
            'zero' => [0, '0'],
            'single digit exact' => [7, '7'],
            'nine' => [9, '9'],
            'ten exact' => [10, '10'],
            'tens rounded down' => [98, '+90'],
            'tens exact' => [90, '90'],
            'hundred exact' => [100, '100'],
            'hundreds rounded down' => [153, '+100'],
            'below a thousand' => [999, '+900'],
            'thousands with separator' => [4793, '+4,700'],
            'thousands exact' => [4700, '4,700'],
            'large' => [1234567, '+1,234,500'],
        ];
    }
}
