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

use core\check\check;
use core\check\result;
use local_alborhancounts\local\cards;

/**
 * Status check: are the vendor files still patched to call local_alborhancounts?
 *
 * A RemUI or page-builder upgrade replaces the patched files and silently brings back the slow card counts. This
 * check reads each target file and reports WARNING when our hook is missing. It shows under Site administration >
 * Reports > System status and in "php admin/cli/checks.php --filter=local_alborhancounts" (exit status 1).
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vendorpatch extends check {
    /**
     * The patches and their files: patch name => [[component, file in the component, marker, hook needle,
     * original vendor line], ...]. The marker and the needle must both be present; the vendor line still being there
     * (and no marker) means "not patched"; neither means "vendor code changed".
     */
    public const PATCHES = [
        'remui-counts' => [
            ['theme_remui', 'classes/coursehandler.php', cards::PATCH_MARKER,
                '\local_alborhancounts\local\cards::count_students(',
                'count($this->get_enrolled_students($course, $context))'],
            ['local_edwiserpagebuilder', 'classes/coursehandler.php', cards::PATCH_MARKER,
                '\local_alborhancounts\local\cards::count_students(',
                'count($this->get_enrolled_students($course, $context))'],
        ],
        'remui-enrolpage-limits' => [
            ['theme_remui', 'classes/EnrolmentPageHandler.php', 'ALBORHAN-PATCH remui-enrolpage v1',
                '$limitto = 5;',
                "\$context['relatedcourses'] = \$this->get_related_courses();"],
        ],
    ];

    /**
     * Constructor.
     *
     * @param array|null $targets For tests: [[label, absolute path, marker, needle, vendor line], ...].
     *     Null for the real vendor files of the expected patches.
     */
    public function __construct(
        /** @var array|null Files to check, or null for the real vendor files. */
        private readonly ?array $targets = null,
    ) {
    }

    /**
     * Names of the patches that must be applied (setting vendorpatches, comma-separated).
     *
     * @return string[]
     */
    public static function expected_patches(): array {
        $setting = get_config('local_alborhancounts', 'vendorpatches');
        $setting = $setting === false ? 'remui-counts,remui-enrolpage-limits' : (string) $setting;
        return array_values(array_intersect(array_map('trim', explode(',', $setting)), array_keys(self::PATCHES)));
    }

    /**
     * Files that must carry a hook, for installed components only.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    protected function targets(): array {
        if ($this->targets !== null) {
            return $this->targets;
        }
        $targets = [];
        foreach (self::expected_patches() as $patch) {
            foreach (self::PATCHES[$patch] as [$component, $file, $marker, $needle, $vendorline]) {
                $dir = \core_component::get_component_directory($component);
                if ($dir !== null && get_config($component, 'version') !== false) {
                    $release = (string) get_config($component, 'release');
                    $targets[] = ["$patch: $component $release", "$dir/$file", $marker, $needle, $vendorline];
                }
            }
        }
        return $targets;
    }

    /**
     * Link to the plugin settings.
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        return new \action_link(
            new \core\url('/admin/settings.php', ['section' => 'local_alborhancounts']),
            get_string('pluginname', 'local_alborhancounts')
        );
    }

    /**
     * Check every target file.
     *
     * @return result
     */
    public function get_result(): result {
        if (get_config('local_alborhancounts', 'vendorpatchcheck') === '0') {
            return new result(result::NA, get_string('checkvendorpatch_off', 'local_alborhancounts'));
        }
        $targets = $this->targets();
        if (!$targets) {
            return new result(result::NA, get_string('checkvendorpatch_novendor', 'local_alborhancounts'));
        }
        $status = result::OK;
        $details = [];
        foreach ($targets as [$label, $path, $marker, $needle, $vendorline]) {
            $code = is_readable($path) ? (string) file_get_contents($path) : '';
            $a = (object) ['label' => $label, 'path' => $path];
            if (str_contains($code, $marker) && str_contains($code, $needle)) {
                $details[] = get_string('checkvendorpatch_patched', 'local_alborhancounts', $a);
                continue;
            }
            $status = result::WARNING;
            $key = str_contains($code, $vendorline) ? 'checkvendorpatch_unpatched' : 'checkvendorpatch_changed';
            $details[] = get_string($key, 'local_alborhancounts', $a);
        }
        if (get_config('local_alborhancounts', 'cardcounts') === '0') {
            // Hooks switched off: the vendor code counts. Not an error, but say so.
            $status = $status === result::OK ? result::INFO : $status;
            $details[] = get_string('checkvendorpatch_switchedoff', 'local_alborhancounts');
        } else if (($error = cards::self_test()) !== null) {
            // The hooks would always fall back to the slow vendor code.
            $status = result::WARNING;
            $details[] = get_string('checkvendorpatch_helperfails', 'local_alborhancounts', $error);
        }
        $summary = get_string(
            $status === result::WARNING ? 'checkvendorpatch_missing' : 'checkvendorpatch_ok',
            'local_alborhancounts'
        );
        return new result($status, $summary, \html_writer::alist(array_map('s', $details)));
    }
}
