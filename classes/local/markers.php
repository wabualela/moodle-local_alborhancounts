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
 * Finds and rewrites count markers in stored HTML.
 *
 * A marker is a span with a data-alborhan-count attribute holding a course id, for example
 * <span dir="ltr" data-alborhan-count="199">+4,700</span>. Only the plain text between the opening and the
 * closing tag is ever replaced, and spaces or non-breaking spaces around that text are kept. A marker whose
 * content contains other tags is left alone and reported, so an editor's change is never overwritten with
 * something the editor did not expect.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class markers {
    /** @var string One character of a tag's attribute list; quoted values are consumed whole, so a '>' in them is safe. */
    private const ATTRIBUTE = '(?:[^>"\']|"[^"]*"|\'[^\']*\')';

    /** @var string Any span carrying the attribute; group 2 = course id, group 3 = content up to the next tag. */
    private const OPENING = '~<span\b' . self::ATTRIBUTE . '*?\sdata-alborhan-count\s*=\s*(["\']?)(\d+)\1(?=[\s>/])'
        . self::ATTRIBUTE . '*>([^<]*)(<[^>]*>)?~i';

    /** @var string Splits marker content into leading spaces, the text, and trailing spaces (plain or non-breaking). */
    private const EDGES = '~^((?:\s|&nbsp;|&#160;|\xC2\xA0)*)(.*?)((?:\s|&nbsp;|&#160;|\xC2\xA0)*)$~si';

    /**
     * Course ids referenced by markers in the HTML.
     *
     * @param string $html
     * @return int[] Unique course ids in order of first appearance.
     */
    public static function course_ids(string $html): array {
        if (stripos($html, 'data-alborhan-count') === false) {
            return [];
        }
        if (preg_match_all(self::OPENING, $html, $matches) === false) {
            throw new \coding_exception('local_alborhancounts markers: ' . preg_last_error_msg());
        }
        return array_values(array_unique(array_map('intval', $matches[2])));
    }

    /**
     * Number of markers in the HTML.
     *
     * @param string $html
     * @return int
     */
    public static function count(string $html): int {
        if (stripos($html, 'data-alborhan-count') === false) {
            return 0;
        }
        $count = preg_match_all(self::OPENING, $html);
        if ($count === false) {
            throw new \coding_exception('local_alborhancounts markers: ' . preg_last_error_msg());
        }
        return $count;
    }

    /**
     * Replace marker content with the given texts.
     *
     * @param string $html Stored HTML.
     * @param array $texts Course id => new text. Courses missing from the array are left unchanged.
     * @return array{0: string, 1: int, 2: int[]} New HTML, number of markers whose text changed, course ids of
     *     markers that could not be updated (no text for the course, or tags inside the marker).
     */
    public static function replace(string $html, array $texts): array {
        $changed = 0;
        $skipped = [];
        if (stripos($html, 'data-alborhan-count') === false) {
            return [$html, 0, []];
        }
        $new = preg_replace_callback(self::OPENING, function (array $m) use ($texts, &$changed, &$skipped): string {
            $courseid = (int) $m[2];
            $nexttag = $m[4] ?? '';
            if (!isset($texts[$courseid]) || !preg_match('~^</span\s*>$~i', $nexttag)) {
                $skipped[] = $courseid;
                return $m[0];
            }
            $text = s($texts[$courseid]);
            if (preg_match(self::EDGES, $m[3], $parts) !== 1) {
                $skipped[] = $courseid;
                return $m[0];
            }
            if ($parts[2] === $text) {
                return $m[0];
            }
            $changed++;
            $opening = substr($m[0], 0, strlen($m[0]) - strlen($m[3]) - strlen($nexttag));
            return $opening . $parts[1] . $text . $parts[3] . $nexttag;
        }, $html);
        if ($new === null) {
            // A PCRE limit was hit: fail loudly rather than leave the markers silently untouched.
            throw new \coding_exception('local_alborhancounts markers: ' . preg_last_error_msg());
        }
        return [$new, $changed, array_values(array_unique($skipped))];
    }
}
