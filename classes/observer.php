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

namespace local_alborhancounts;

use local_alborhancounts\local\cards;

/**
 * Drops cached card counts when enrolments or roles change. Registered with 'internal' => false, so it runs after
 * the database transaction of the change commits and a concurrent request cannot re-cache the old number.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * A user was enrolled in or unenrolled from a course, or the course was deleted.
     *
     * @param \core\event\base $event
     */
    public static function course_changed(\core\event\base $event): void {
        if (!empty($event->courseid) && $event->courseid != SITEID) {
            cards::invalidate((int) $event->courseid);
        }
    }

    /**
     * A role was assigned or unassigned. Course context: that course. System or category: every course.
     *
     * @param \core\event\base $event
     */
    public static function role_changed(\core\event\base $event): void {
        // The level and instance are stored in the event data, so no context lookup is needed (get_context() can
        // return false for an event that only carries a contextid of a deleted context).
        if ($event->contextlevel == CONTEXT_COURSE) {
            cards::invalidate((int) $event->contextinstanceid);
        } else if ($event->contextlevel == CONTEXT_SYSTEM || $event->contextlevel == CONTEXT_COURSECAT) {
            cards::purge();
        }
    }

    /**
     * A capability was set or removed in a role definition or override: drop everything if it is the student one.
     *
     * @param \core\event\base $event
     */
    public static function capability_changed(\core\event\base $event): void {
        if (($event->other['capability'] ?? '') === 'moodle/course:isincompletionreports') {
            cards::purge();
        }
    }

    /**
     * A role was deleted, a role definition was saved, or a user was deleted: drop everything (rare events).
     *
     * @param \core\event\base $event
     */
    public static function purge(\core\event\base $event): void {
        cards::purge();
    }
}
