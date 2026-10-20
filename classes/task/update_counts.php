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

namespace local_alborhancounts\task;

use local_alborhancounts\local\updater;

/**
 * Scheduled task that refreshes the static enrolment counts shown in page-builder blocks.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_counts extends \core\task\scheduled_task {
    /**
     * Name shown in the scheduled tasks list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskupdatecounts', 'local_alborhancounts');
    }

    /**
     * Refresh the counts unless the plugin is disabled.
     *
     * @throws \moodle_exception When markers disappeared or some could not be updated.
     */
    public function execute(): void {
        global $DB;

        if (!get_config('local_alborhancounts', 'enabled')) {
            mtrace('local_alborhancounts is disabled in its settings; nothing to do.');
            return;
        }
        $result = (new updater(new \text_progress_trace()))->run();
        mtrace("Blocks with markers: {$result['blocks']}, markers: {$result['markers']}, " .
            "texts changed: {$result['changed']}, blocks saved: " . count($result['updated']) . '.');

        // Fail the task (after all writes) so the problem shows in the failed tasks check, not only in the log.
        $problems = [];
        // Blocks updated before (they have copies) that still exist but no longer hold any marker.
        $known = $DB->get_fieldset_sql(
            'SELECT DISTINCT b.blockinstanceid
               FROM {local_alborhancounts_bak} b
               JOIN {block_instances} bi ON bi.id = b.blockinstanceid'
        );
        $lost = array_diff(array_map('intval', $known), $result['found']);
        if ($lost) {
            $problems[] = 'no count markers found any more in block(s) ' . implode(', ', $lost);
        }
        if ($result['skipped']) {
            $problems[] = 'markers left unchanged for course(s) ' . implode(', ', $result['skipped']);
        }
        if ($problems) {
            throw new \moodle_exception('taskproblems', 'local_alborhancounts', '', implode('; ', $problems));
        }
    }
}
