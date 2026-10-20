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
 * Turns an exact enrolment count into the rounded text shown on the page.
 *
 * Counts of 100 or more are rounded down to the hundred, counts of 10 to 99 to the ten, and smaller counts are
 * shown as they are. A "+" is added when the rounded value is below the real count, so the text stays true
 * while the count grows: 4793 gives "+4,700", 4700 gives "4,700", 98 gives "+90", 7 gives "7".
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class formatter {
    /**
     * Format a count for display.
     *
     * @param int $count Exact number of enrolled students.
     * @return string Rounded text, Western digits with a comma as the thousands separator.
     */
    public static function format(int $count): string {
        $count = max(0, $count);
        if ($count < 10) {
            return (string) $count;
        }
        $step = $count >= 100 ? 100 : 10;
        $rounded = intdiv($count, $step) * $step;
        $text = number_format($rounded, 0, '.', ',');
        return $count > $rounded ? '+' . $text : $text;
    }
}
