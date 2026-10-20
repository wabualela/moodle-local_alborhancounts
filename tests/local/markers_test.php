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
 * Tests for finding and rewriting count markers.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(markers::class)]
final class markers_test extends \basic_testcase {
    /**
     * Course ids are found in any attribute order and quoting, once each.
     */
    public function test_course_ids(): void {
        $html = '<div><span data-alborhan-count="199">4k</span> طالب</div>'
            . '<p><span class="count" dir="ltr" data-alborhan-count=\'89\'>1k</span></p>'
            . '<span data-alborhan-count=715>153</span><span data-alborhan-count="199">x</span>'
            . '<span data-alborhan-countx="5">no</span><span data-other="1">no</span>';
        $this->assertSame([199, 89, 715], markers::course_ids($html));
        $this->assertSame([], markers::course_ids('<p>No markers here</p>'));
    }

    /**
     * Only the text between the tags changes; everything else is kept byte for byte.
     */
    public function test_replace_keeps_surroundings(): void {
        $html = '<div class="card"><img src="a.png">'
            . '<span class="c" dir="ltr" data-alborhan-count="199" title="t">4k</span>'
            . ' طالب</div><span data-alborhan-count="89">1k</span>';
        [$new, $changed, $skipped] = markers::replace($html, [199 => '+4,700', 89 => '+1,100']);
        $this->assertSame('<div class="card"><img src="a.png">'
            . '<span class="c" dir="ltr" data-alborhan-count="199" title="t">+4,700</span>'
            . ' طالب</div><span data-alborhan-count="89">+1,100</span>', $new);
        $this->assertSame(2, $changed);
        $this->assertSame([], $skipped);
    }

    /**
     * A marker that already shows the text is not counted as a change.
     */
    public function test_replace_unchanged(): void {
        $html = '<span data-alborhan-count="199"> +4,700 </span>';
        [$new, $changed, $skipped] = markers::replace($html, [199 => '+4,700']);
        $this->assertSame($html, $new);
        $this->assertSame(0, $changed);
        $this->assertSame([], $skipped);
    }

    /**
     * Markers without a text, or with tags inside, are left alone and reported.
     */
    public function test_replace_skips(): void {
        $html = '<span data-alborhan-count="5">7</span><span data-alborhan-count="6"><b>9</b></span>'
            . '<span data-alborhan-count="7">3</span>';
        [$new, $changed, $skipped] = markers::replace($html, [6 => '10', 7 => '12']);
        $this->assertSame('<span data-alborhan-count="5">7</span><span data-alborhan-count="6"><b>9</b></span>'
            . '<span data-alborhan-count="7">12</span>', $new);
        $this->assertSame(1, $changed);
        $this->assertSame([5, 6], $skipped);
    }

    /**
     * Spaces and non-breaking spaces around the text are kept, so the number does not stick to the next word.
     */
    public function test_replace_keeps_edges(): void {
        $html = '<span data-alborhan-count="1">+4,700&nbsp;</span>طالب<span data-alborhan-count="2"> 90 </span>';
        [$new, $changed] = markers::replace($html, [1 => '+4,700', 2 => '+90']);
        $expected = '<span data-alborhan-count="1">+4,700&nbsp;</span>طالب<span data-alborhan-count="2"> +90 </span>';
        $this->assertSame($expected, $new);
        $this->assertSame(1, $changed);

        [$new, $changed] = markers::replace('<span data-alborhan-count="1">&#160;</span>', [1 => '5']);
        $this->assertSame('<span data-alborhan-count="1">&#160;5</span>', $new);
        $this->assertSame(1, $changed);
    }

    /**
     * A '>' inside a quoted attribute value does not end the tag early.
     */
    public function test_replace_gt_in_attribute(): void {
        $html = '<span title="a > b" data-alborhan-count="5" data-x=\'c>d\'>+10</span> طالب';
        [$new, $changed, $skipped] = markers::replace($html, [5 => '+20']);
        $this->assertSame('<span title="a > b" data-alborhan-count="5" data-x=\'c>d\'>+20</span> طالب', $new);
        $this->assertSame(1, $changed);
        $this->assertSame([], $skipped);
        $this->assertSame([5], markers::course_ids($html));
    }

    /**
     * An attribute name inside another attribute's value is not a marker.
     */
    public function test_marker_name_inside_value(): void {
        $html = '<span title="see data-alborhan-count=7">x</span>';
        $this->assertSame([], markers::course_ids($html));
        $this->assertSame([$html, 0, []], markers::replace($html, [7 => '1']));
    }

    /**
     * Every marker occurrence is counted, including repeated course ids.
     */
    public function test_count(): void {
        $html = '<span data-alborhan-count="1">a</span><span data-alborhan-count="1">b</span>'
            . '<span data-alborhan-count="2"><b>c</b></span>';
        $this->assertSame(3, markers::count($html));
        $this->assertSame(0, markers::count('<span>none</span>'));
    }

    /**
     * The new text is escaped.
     */
    public function test_replace_escapes(): void {
        [$new] = markers::replace('<span data-alborhan-count="1">0</span>', [1 => '<b>&']);
        $this->assertSame('<span data-alborhan-count="1">&lt;b&gt;&amp;</span>', $new);
    }

    /**
     * HTML without markers is returned as it is.
     */
    public function test_replace_no_markers(): void {
        $html = '<p>[edwiser-fc layout="x"]</p>';
        $this->assertSame([$html, 0, []], markers::replace($html, [1 => '5']));
    }
}
