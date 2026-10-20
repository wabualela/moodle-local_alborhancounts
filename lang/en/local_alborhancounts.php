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
 * English strings for local_alborhancounts.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['blocknames'] = 'Block types';
$string['blocknames_desc'] = 'Comma-separated block types whose content is searched for count markers. A marker is a span such as <code>&lt;span dir="ltr" data-alborhan-count="199"&gt;+4,700&lt;/span&gt;</code>, where 199 is the course id.';
$string['cachedef_cardcountsrequest'] = 'Course card student counts, kept for one request';
$string['cachedef_studentcounts'] = 'Course card student counts, shared between requests (only when the cache lifetime setting is above 0)';
$string['cardcachettl'] = 'Shared cache lifetime (seconds)';
$string['cardcachettl_desc'] = 'When above 0, a course\'s student count that is the same for every viewer is shared between requests for this many seconds; enrolment and role changes drop it at once. 0 (default) counts on every request, which always gives the exact number.';
$string['cardcounts'] = 'Count students for course cards';
$string['cardcounts_desc'] = 'Used by the patched theme_remui and local_edwiserpagebuilder files (see vendor-patches/ in the plugin repository): the course cards get their student count from one COUNT query instead of loading every student. Untick to make the cards count with the vendor code again, without changing any file.';
$string['cardsheading'] = 'Course card counts (vendor patch)';
$string['cardsheading_desc'] = 'Settings for the Edwiser course cards patched to call this plugin.';
$string['checkvendorpatch'] = 'Edwiser course-card patch';
$string['checkvendorpatch_changed'] = '{$a->label}: the vendor code changed ({$a->path}). Review the patch for this release.';
$string['checkvendorpatch_helperfails'] = 'The count helper fails, so the cards fall back to the slow vendor code: {$a}';
$string['checkvendorpatch_missing'] = 'An Edwiser course-card patch is missing: the course cards count students the slow way again.';
$string['checkvendorpatch_novendor'] = 'No patched Edwiser plugin is installed.';
$string['checkvendorpatch_off'] = 'The patch check is switched off in the plugin settings.';
$string['checkvendorpatch_ok'] = 'The Edwiser course-card patches are in place.';
$string['checkvendorpatch_patched'] = '{$a->label}: patched ({$a->path}).';
$string['checkvendorpatch_switchedoff'] = 'The card counts are switched off in the plugin settings: the vendor code counts.';
$string['checkvendorpatch_unpatched'] = '{$a->label}: not patched ({$a->path}). Probably replaced by a vendor upgrade: re-apply the patch.';
$string['enabled'] = 'Update the counts';
$string['enabled_desc'] = 'When ticked, the scheduled task rewrites the text of every count marker with the rounded number of students enrolled in its course.';
$string['pluginname'] = 'Alborhan course counts';
$string['privacy:metadata'] = 'The Alborhan course counts plugin stores no personal data. It writes per-course totals into block content, caches per-course totals and keeps copies of block settings.';
$string['taskproblems'] = 'The count update finished with problems: {$a}.';
$string['taskupdatecounts'] = 'Update static enrolment counts';
$string['taskvendorpatchmissing'] = 'An Edwiser course-card patch is missing or the count helper fails; see the Edwiser course-card patch check in the system status report.';
$string['taskverifyvendorpatch'] = 'Verify the Edwiser course-card patch';
$string['vendorpatchcheck'] = 'Check the vendor patches';
$string['vendorpatchcheck_desc'] = 'Report in the system status page, and fail the hourly task, when a patched Edwiser file no longer calls this plugin (for example after a theme upgrade).';
$string['vendorpatches'] = 'Expected vendor patches';
$string['vendorpatches_desc'] = 'Comma-separated names of the patches that must be applied: remui-counts (course card counts) and remui-enrolpage-limits (enrol page builds only the cards it shows).';
