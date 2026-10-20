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

use core\check\result;
use local_alborhancounts\check\vendorpatch;

/**
 * Hourly task that fails while the vendor patch is missing, so a lost patch is pushed to the admins.
 *
 * A failing task is retried after 60 s, then with a doubling delay capped at 24 h. tool_task's "max fail delay"
 * status check turns WARNING about 7 minutes after the first failure and ERROR after about 4 hours; core sends the
 * failedtaskmaxdelay notification (e-mail and popup) to the site admins once the cap is reached (about 34 hours),
 * then daily.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class verify_vendor_patch extends \core\task\scheduled_task {
    /**
     * Name shown in the scheduled tasks list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskverifyvendorpatch', 'local_alborhancounts');
    }

    /**
     * The status check to run (a separate method so tests can supply fixture files).
     *
     * @return vendorpatch
     */
    protected function get_check(): vendorpatch {
        return new vendorpatch();
    }

    /**
     * Run the status check and fail when it warns.
     *
     * @throws \moodle_exception When a vendor file lost the hook.
     */
    public function execute(): void {
        $result = $this->get_check()->get_result();
        mtrace(html_to_text($result->get_summary() . "\n" . $result->get_details(), 0, false));
        if (in_array($result->get_status(), [result::WARNING, result::ERROR, result::CRITICAL], true)) {
            throw new \moodle_exception('taskvendorpatchmissing', 'local_alborhancounts');
        }
    }
}
