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
$string['enabled'] = 'Update the counts';
$string['enabled_desc'] = 'When ticked, the scheduled task rewrites the text of every count marker with the rounded number of students enrolled in its course.';
$string['pluginname'] = 'Alborhan course counts';
$string['privacy:metadata'] = 'The Alborhan course counts plugin stores no personal data. It writes per-course totals into block content and keeps copies of block settings.';
$string['taskproblems'] = 'The count update finished with problems: {$a}.';
$string['taskupdatecounts'] = 'Update static enrolment counts';
