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
 * Refresh the count markers from the command line. Dry run unless --execute is given.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['execute' => false, 'help' => false], ['h' => 'help']);
if ($unrecognised) {
    cli_error('Unknown option(s): ' . implode(', ', $unrecognised));
}
if ($options['execute'] !== false && $options['execute'] !== true) {
    cli_error('--execute takes no value');
}
if ($options['help']) {
    echo "Refresh the static enrolment counts in page-builder blocks.

Without --execute nothing is saved: the script prints each course count and the blocks that would change.
This works even when the plugin is disabled in its settings.

Options:
  --execute     Save the changes (a copy of each changed block configuration is kept first).
  -h, --help    Show this help.

Example (run as the web server user):
  sudo -u www-data php local/alborhancounts/cli/update.php
  sudo -u www-data php local/alborhancounts/cli/update.php --execute
";
    exit(0);
}

$dryrun = !$options['execute'];
$result = (new \local_alborhancounts\local\updater(new text_progress_trace(), $dryrun))->run();
cli_writeln(($dryrun ? 'Dry run. ' : '') . "Blocks with markers: {$result['blocks']}, markers: {$result['markers']}, " .
    "texts changed: {$result['changed']}, blocks " . ($dryrun ? 'that would be saved' : 'saved') . ': ' .
    (count($result['updated']) ? implode(', ', $result['updated']) : 'none') . '.');
