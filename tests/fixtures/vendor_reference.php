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
 * Test-only copy of the vendor function our helper replaces, so CI can compare without the vendor plugins.
 *
 * Copied unchanged (apart from $this) from theme_remui 5.2.2 classes/coursehandler.php lines 418-478, which is
 * identical to local_edwiserpagebuilder 4.2.26 classes/coursehandler.php lines 765-826.
 * (c) 2023 WisdmLabs, GNU GPL v3 or later. When a vendor release changes that function, update this copy and the
 * helper together.
 *
 * @package    local_alborhancounts
 * @copyright  2023 WisdmLabs (https://wisdmlabs.com/) <support@wisdmlabs.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Vendor reference implementation.
 */
final class local_alborhancounts_vendor_reference {
    /**
     * Get enrolled students in course (vendor code).
     *
     * @param object $course Course
     * @param context $context Course context
     * @param int $userid User id
     * @param bool $admin Is admin
     * @return array Array of users
     */
    public static function get_enrolled_students($course, $context, $userid = 0, $admin = false) {
        global $DB, $USER;

        $groups = [];

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $groups = groups_get_user_groups($course->id, $userid);
        $groups = $groups[0];

        [$esql, $params] = get_enrolled_sql($context, 'moodle/course:isincompletionreports');

        $groupsql = '';

        if (!$admin) {
            if (!has_capability('moodle/site:accessallgroups', $context) && $course->groupmode == 1) {
                if (empty($groups)) {
                    return [];
                }

                [$insql, $inparams] = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'groups', true, true);
                $groupsql = " JOIN {groups_members} gm ON gm.groupid $insql AND gm.userid = u.id";
                $params = array_merge($params, $inparams);
            }
        }

        $fields = implode(', ', [
            'u.id', 'u.confirmed', 'u.policyagreed', 'u.deleted', 'u.suspended', 'u.username', 'u.idnumber',
            'u.firstname', 'u.lastname', 'u.email', 'u.lang', 'u.theme', 'u.firstaccess', 'u.lastaccess', 'u.lastlogin',
            'u.currentlogin', 'u.timecreated', 'u.timemodified', 'u.lastnamephonetic', 'u.firstnamephonetic',
            'u.middlename', 'u.alternatename',
        ]);

        $sql = "SELECT DISTINCT $fields
                FROM {user} u
                $groupsql
                JOIN ($esql) je ON je.id = u.id
                WHERE u.deleted = 0";

        return $DB->get_records_sql($sql, $params);
    }
}
