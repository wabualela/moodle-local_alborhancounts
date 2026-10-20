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
 * Event observers for local_alborhancounts: drop cached card counts when enrolments or roles change.
 *
 * 'internal' => false: run after the transaction of the change commits.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [];
// A course move (course_updated) or a category move (course_category_updated) changes which category-level roles and
// overrides apply, so the count can change too.
foreach (['user_enrolment_created', 'user_enrolment_deleted', 'course_deleted', 'course_updated'] as $name) {
    $observers[] = ['eventname' => "\\core\\event\\$name", 'callback' => '\local_alborhancounts\observer::course_changed',
        'internal' => false];
}
foreach (['role_assigned', 'role_unassigned'] as $name) {
    $observers[] = ['eventname' => "\\core\\event\\$name", 'callback' => '\local_alborhancounts\observer::role_changed',
        'internal' => false];
}
foreach (['capability_assigned', 'capability_unassigned'] as $name) {
    $observers[] = ['eventname' => "\\core\\event\\$name", 'callback' => '\local_alborhancounts\observer::capability_changed',
        'internal' => false];
}
foreach (['role_capabilities_updated', 'role_deleted', 'user_deleted', 'course_category_updated'] as $name) {
    $observers[] = ['eventname' => "\\core\\event\\$name", 'callback' => '\local_alborhancounts\observer::purge',
        'internal' => false];
}
