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
 * List the saved block configuration copies, or restore one.
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['backupid' => 0, 'help' => false], ['h' => 'help']);
if ($unrecognised) {
    cli_error('Unknown option(s): ' . implode(', ', $unrecognised));
}
if ($options['help']) {
    echo "List or restore the block configuration copies taken before each count update.

Without options the script lists the copies (newest first). With --backupid it puts that copy back into its
block. Restoring also undoes any edit made to the block after the copy was taken, so the configuration it
replaces is saved as a new copy first (restore that one to undo). The next run of the scheduled task writes
the current counts again unless the plugin is disabled.

Options:
  --backupid=ID  Restore this copy.
  -h, --help     Show this help.

Example (run as the web server user, from the Moodle code root):
  sudo -u www-data php8.4 public/local/alborhancounts/cli/restore.php
  sudo -u www-data php8.4 public/local/alborhancounts/cli/restore.php --backupid=12
";
    exit(0);
}

if ($options['backupid'] !== 0) {
    if (!is_string($options['backupid']) || !ctype_digit($options['backupid']) || (int) $options['backupid'] < 1) {
        cli_error('--backupid needs a copy id from the list, for example --backupid=12');
    }
    $backup = $DB->get_record('local_alborhancounts_bak', ['id' => (int) $options['backupid']], 'id, blockinstanceid');
    if (!$backup) {
        cli_error("There is no copy {$options['backupid']}.");
    }
    if (!$DB->record_exists('block_instances', ['id' => $backup->blockinstanceid])) {
        cli_error("The block of copy {$options['backupid']} no longer exists.");
    }
    [$instanceid, $copyid] = \local_alborhancounts\local\updater::restore((int) $options['backupid']);
    cli_writeln("Copy {$options['backupid']} restored into block $instanceid. " .
        "The configuration it replaced was saved as copy $copyid (restore that copy to undo).");
    exit(0);
}

$rows = $DB->get_records_sql(
    'SELECT b.id, b.blockinstanceid, b.timecreated, bi.id AS existing
       FROM {local_alborhancounts_bak} b
  LEFT JOIN {block_instances} bi ON bi.id = b.blockinstanceid
   ORDER BY b.id DESC'
);
if (!$rows) {
    cli_writeln('No copies saved yet.');
    exit(0);
}
foreach ($rows as $row) {
    $note = $row->existing ? '' : '  (block no longer exists)';
    $when = userdate($row->timecreated, '%Y-%m-%d %H:%M');
    cli_writeln(sprintf('%6d  block %-8d %s%s', $row->id, $row->blockinstanceid, $when, $note));
}
