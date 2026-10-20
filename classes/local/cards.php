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

use core\context;

/**
 * Student counts for the Edwiser course cards, called by the vendor patch vendor-patches/remui-counts.diff.
 *
 * The patched call sites are theme_remui_coursehandler::get_courses() and
 * local_edwiserpagebuilder\coursehandler::get_courses(). Both used count(get_enrolled_students()), which loads the
 * full user row of every student of the course only to count them. This class returns the same number with one
 * COUNT query.
 *
 * Population (identical to get_enrolled_students($course, $context) with $admin = false in both vendor files):
 * users enrolled in the course by any enrolment (active or suspended, enabled or disabled instance) who have
 * moodle/course:isincompletionreports in the course context, excluding deleted users and the guest account.
 * When the course is in separate groups mode and the current user lacks moodle/site:accessallgroups, only members
 * of the groups returned by groups_get_user_groups($course->id, $userid)[0] are counted, and 0 is returned when
 * that list is empty (as the vendor code returns an empty array).
 *
 * Caching:
 * - per request, always: a course card can be built several times in one request (the front page renders its
 *   [edwiser-fc] shortcodes twice; the enrol page counts the current course again in the related list);
 * - across requests, only when the setting cardcachettl > 0 (default 0 = exact, no shared cache).
 * Both apply only when the number does not depend on the viewer.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cards {
    /** @var string Text the vendor patch writes next to each hook; the vendorpatch check looks for it. */
    public const PATCH_MARKER = 'ALBORHAN-PATCH remui-counts v1';

    /** @var string Capability that identifies the counted students, as in the vendor code. */
    private const STUDENT_CAPABILITY = 'moodle/course:isincompletionreports';

    /** @var int Shared cache lifetime in seconds when the setting has never been saved (0 = no shared cache). */
    public const DEFAULT_TTL = 0;

    /** @var bool Whether a helper failure was already logged in this request. */
    private static bool $failurelogged = false;

    /**
     * Number of students the vendor card shows to the current user, or null to let the vendor code count.
     *
     * Never throws: on any error it logs once per request and returns null, so the vendor code counts instead.
     *
     * @param object $course Course record with at least id and groupmode (as the vendor passes it).
     * @param context $context The course context.
     * @param int $userid User whose groups filter a separate-groups course; 0 means the current user (vendor default).
     *     As in the vendor code, moodle/site:accessallgroups is always checked for the current user.
     * @return int|null Null when the plugin is not installed, the card counts are switched off, or on error.
     */
    public static function count_students(object $course, context $context, int $userid = 0): ?int {
        try {
            if (!self::enabled()) {
                return null;
            }
            if (self::depends_on_viewer($course, $context)) {
                return self::compute($course, $context, $userid);
            }
            // Same number for every viewer: memoise it for this request, and optionally share it.
            $courseid = (int) $course->id;
            $request = \cache::make('local_alborhancounts', 'cardcountsrequest');
            $count = $request->get($courseid);
            if ($count === false) {
                $count = self::cached($courseid, fn(): int => self::query($context, []));
                $request->set($courseid, $count);
            }
            return (int) $count;
        } catch (\Throwable $e) {
            if (!self::$failurelogged) {
                self::$failurelogged = true;
                debugging(
                    'local_alborhancounts: card count failed, vendor code used instead: ' . $e->getMessage(),
                    DEBUG_NORMAL
                );
            }
            return null;
        }
    }

    /**
     * Run the helper once on a real course without catching errors; used by the vendorpatch status check so that
     * a helper that always falls back to the vendor code (and is therefore silently slow) is reported.
     *
     * @return string|null Null when it works, otherwise the error message.
     */
    public static function self_test(): ?string {
        global $DB;
        try {
            \cache::make('local_alborhancounts', 'studentcounts');
            \cache::make('local_alborhancounts', 'cardcountsrequest');
            $courses = $DB->get_records_select('course', 'id <> ?', [SITEID], 'id', 'id, groupmode', 0, 1);
            foreach ($courses as $course) {
                self::query(\core\context\course::instance($course->id), []);
            }
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * The number the vendor card shows to the current user, computed now (no cache, ignores the settings).
     *
     * Used by count_students(), the tests and cli/verify_cards.php.
     *
     * @param object $course Course record with at least id and groupmode.
     * @param context $context The course context.
     * @param int $userid User whose groups filter a separate-groups course; 0 means the current user.
     * @return int
     */
    public static function compute(object $course, context $context, int $userid = 0): int {
        global $USER;

        if (!self::depends_on_viewer($course, $context)) {
            return self::query($context, []);
        }
        $groupids = groups_get_user_groups((int) $course->id, $userid > 0 ? $userid : (int) $USER->id)[0];
        if (empty($groupids)) {
            return 0;
        }
        return self::query($context, array_values($groupids));
    }

    /**
     * Whether the vendor code filters by groups for this course and the current user.
     *
     * Vendor condition: !$admin && !has_capability('moodle/site:accessallgroups', $context) && $course->groupmode == 1.
     *
     * @param object $course
     * @param context $context
     * @return bool
     */
    public static function depends_on_viewer(object $course, context $context): bool {
        return (int) ($course->groupmode ?? NOGROUPS) === SEPARATEGROUPS
            && !has_capability('moodle/site:accessallgroups', $context);
    }

    /**
     * Count the students of a course, optionally only the members of some groups. No cache.
     *
     * With no groups the SQL is the one of core count_enrolled_users($context, STUDENT_CAPABILITY). With groups it
     * adds the vendor's plain groups_members join (no group-visibility rule, unlike groups_get_members_join()).
     *
     * @param context $context The course context.
     * @param int[] $groupids Groups whose members are counted; empty for no group filter.
     * @return int
     */
    public static function query(context $context, array $groupids): int {
        global $DB;

        $capjoin = get_enrolled_with_capabilities_join($context, '', self::STUDENT_CAPABILITY);
        if ($capjoin->cannotmatchanyrows) {
            return 0;
        }
        $params = $capjoin->params;
        $groupjoin = '';
        if ($groupids) {
            [$insql, $inparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'alborhangrp');
            $groupjoin = "JOIN {groups_members} alborhangm ON alborhangm.groupid $insql AND alborhangm.userid = u.id";
            $params = array_merge($params, $inparams);
        }
        $sql = "SELECT COUNT(DISTINCT u.id)
                  FROM {user} u
                $capjoin->joins
                $groupjoin
                 WHERE $capjoin->wheres AND u.deleted = 0";
        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Drop the cached count of one course (shared cache and this request's memo).
     *
     * @param int $courseid
     */
    public static function invalidate(int $courseid): void {
        \cache::make('local_alborhancounts', 'studentcounts')->delete($courseid);
        \cache::make('local_alborhancounts', 'cardcountsrequest')->delete($courseid);
    }

    /**
     * Drop every cached count.
     */
    public static function purge(): void {
        \cache::make('local_alborhancounts', 'studentcounts')->purge();
        \cache::make('local_alborhancounts', 'cardcountsrequest')->purge();
    }

    /**
     * Whether the plugin is installed and the card counts are switched on.
     *
     * The version check keeps the helper inert while the plugin's code is deployed but its install is pending
     * (its cache definitions do not exist yet).
     *
     * @return bool
     */
    private static function enabled(): bool {
        return get_config('local_alborhancounts', 'version') !== false
            && get_config('local_alborhancounts', 'cardcounts') !== '0';
    }

    /**
     * Shared cached count of a course, recomputed when older than the configured lifetime.
     *
     * @param int $courseid
     * @param callable $compute Returns the count.
     * @return int
     */
    private static function cached(int $courseid, callable $compute): int {
        $ttl = get_config('local_alborhancounts', 'cardcachettl');
        $ttl = $ttl === false ? self::DEFAULT_TTL : (int) $ttl;
        if ($ttl <= 0) {
            return $compute();
        }
        $cache = \cache::make('local_alborhancounts', 'studentcounts');
        $now = time();
        $hit = $cache->get($courseid);
        if (is_array($hit) && isset($hit['n'], $hit['t']) && $hit['t'] > $now - $ttl) {
            return (int) $hit['n'];
        }
        $count = $compute();
        $cache->set($courseid, ['n' => $count, 't' => $now]);
        return $count;
    }
}
