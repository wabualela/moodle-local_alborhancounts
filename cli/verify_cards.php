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

/**
 * Compare the vendor card count with ours, course by course, as seen by one user. Read-only.
 *
 * Runs the vendor's slow get_enrolled_students() once per course: use it at a quiet hour.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_alborhancounts\local\cards;

[$options, $unrecognised] = cli_get_params(['userid' => 0, 'courseids' => '', 'help' => false], ['h' => 'help']);
if ($unrecognised) {
    cli_error('Unknown option(s): ' . implode(', ', $unrecognised));
}
if ($options['help']) {
    echo "Compare the vendor course-card student count with local_alborhancounts, for each course.

Options:
  --userid=N        Viewer whose groups and capabilities apply (default 0: not logged in).
  --courseids=1,2   Only these courses (default: every course).
  -h, --help        Show this help.

Exit status: 0 when every count matches, 1 when one differs, 2 when no vendor class is installed.

Example (run as the web server user, from the Moodle code root):
  sudo -u www-data php8.4 public/local/alborhancounts/cli/verify_cards.php --userid=2 --courseids=199,725
";
    exit(0);
}

$vendors = [];
if (class_exists('theme_remui_coursehandler')) {
    $vendors['theme_remui'] = new theme_remui_coursehandler();
}
if (class_exists('\local_edwiserpagebuilder\coursehandler')) {
    $vendors['local_edwiserpagebuilder'] = new \local_edwiserpagebuilder\coursehandler();
}
if (!$vendors) {
    cli_error('Neither theme_remui nor local_edwiserpagebuilder is installed.', 2);
}

if ($options['userid']) {
    \core\session\manager::set_user(core_user::get_user((int) $options['userid'], '*', MUST_EXIST));
}
$where = 'id <> :siteid';
$params = ['siteid' => SITEID];
if ($options['courseids'] !== '') {
    [$insql, $inparams] = $DB->get_in_or_equal(array_map('intval', explode(',', $options['courseids'])), SQL_PARAMS_NAMED);
    $where .= " AND id $insql";
    $params += $inparams;
}

$mismatches = 0;
$checked = 0;
$rs = $DB->get_recordset_select('course', $where, $params, 'id', 'id, groupmode, shortname');
foreach ($rs as $course) {
    $context = context_course::instance($course->id);
    $ours = cards::compute($course, $context);
    foreach ($vendors as $component => $handler) {
        $theirs = count($handler->get_enrolled_students($course, $context));
        $checked++;
        if ($theirs !== $ours) {
            $mismatches++;
            cli_writeln("MISMATCH course {$course->id} ({$component}, groupmode {$course->groupmode}): vendor $theirs, ours $ours");
        }
    }
}
$rs->close();
cli_writeln("Checked $checked course/vendor pairs for user {$USER->id}: $mismatches mismatch(es).");
exit($mismatches ? 1 : 0);
