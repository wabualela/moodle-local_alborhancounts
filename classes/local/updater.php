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

use context_course;
use progress_trace;
use stdClass;

/**
 * Refreshes the count markers stored in block configurations.
 *
 * The counted population is the one the Edwiser course cards show: users enrolled in the course (active or not,
 * including enrolments in disabled instances) who have moodle/course:isincompletionreports, i.e. students,
 * excluding deleted accounts. Unlike the cards, the count does not depend on the viewer's groups.
 *
 * A block configuration is written only when at least one marker text changes. Each block is read again just
 * before it is rewritten, and the write is skipped if the block changed in the meantime, so an editor's save is
 * never overwritten. Before writing, the previous configuration is copied to the local_alborhancounts_bak table
 * (the last BACKUPS_KEPT copies per block are kept).
 *
 * @package    local_alborhancounts
 * @copyright  2026 Alborhan Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class updater {
    /** @var string Block types scanned when the blocknames setting is empty. */
    public const DEFAULT_BLOCKNAMES = 'edwiseradvancedblock';

    /** @var int Number of configuration backups kept per block instance. */
    public const BACKUPS_KEPT = 10;

    /** @var string Capability that identifies the counted students, as in the Edwiser course cards. */
    private const STUDENT_CAPABILITY = 'moodle/course:isincompletionreports';

    /** @var string Signs of the Edwiser course-card component, which the page builder regenerates on save. */
    private const DYNAMIC_PATTERN = '~\[edwiser-fc\b|data-shortcode\s*=\s*["\']?edwiser-fc~i';

    /**
     * Constructor.
     *
     * @param progress_trace $trace Where progress messages go.
     * @param bool $dryrun True to report the changes without saving anything.
     */
    public function __construct(
        /** @var progress_trace Where progress messages go. */
        private readonly progress_trace $trace,
        /** @var bool True to report the changes without saving anything. */
        private readonly bool $dryrun = false,
    ) {
    }

    /**
     * Block types to scan, from the plugin setting.
     *
     * @return string[]
     */
    public static function blocknames(): array {
        $setting = (string) get_config('local_alborhancounts', 'blocknames');
        if (trim($setting) === '') {
            $setting = self::DEFAULT_BLOCKNAMES;
        }
        $names = array_filter(array_map(fn($n) => clean_param(trim($n), PARAM_PLUGIN), explode(',', $setting)));
        return array_values(array_unique($names));
    }

    /**
     * Refresh every marker.
     *
     * @return array{blocks: int, found: int[], markers: int, updated: int[], changed: int, skipped: int[],
     *     conflicts: int[], warnings: int[]} Number and ids of blocks with markers, markers found, ids of block
     *     instances saved (or that would be saved in a dry run), marker texts changed, course ids whose markers could
     *     not be updated, ids of blocks not saved because they changed during the run, ids of blocks that still
     *     contain the Edwiser course-card component.
     */
    public function run(): array {
        global $DB;

        $result = ['blocks' => 0, 'found' => [], 'markers' => 0, 'updated' => [], 'changed' => 0, 'skipped' => [],
            'conflicts' => [], 'warnings' => []];
        $names = self::blocknames();
        if (!$names) {
            $this->trace->output('No block types configured.');
            return $result;
        }

        // First pass: find the blocks with markers and the courses they reference.
        $blockids = [];
        $courseids = [];
        [$insql, $params] = $DB->get_in_or_equal($names);
        $rs = $DB->get_recordset_select('block_instances', "blockname $insql", $params, 'id', 'id, configdata');
        foreach ($rs as $instance) {
            $config = self::decode($instance->configdata);
            if ($config === null) {
                continue;
            }
            $ids = [];
            $markers = 0;
            $dynamic = false;
            self::walk($config, function (string $text) use (&$ids, &$markers, &$dynamic): string {
                $ids = array_merge($ids, markers::course_ids($text));
                $markers += markers::count($text);
                $dynamic = $dynamic || preg_match(self::DYNAMIC_PATTERN, $text);
                return $text;
            });
            if (!$ids) {
                continue;
            }
            $blockids[] = (int) $instance->id;
            $courseids = array_merge($courseids, $ids);
            $result['markers'] += $markers;
            if ($dynamic) {
                $result['warnings'][] = (int) $instance->id;
                $this->trace->output("Warning: block {$instance->id} has count markers and also the Edwiser course-card " .
                    "component ([edwiser-fc]). If the static cards sit inside that component, the page builder replaces " .
                    "them with the shortcode on the next save.");
            }
        }
        $rs->close();
        $result['blocks'] = count($blockids);
        $result['found'] = $blockids;
        if (!$blockids) {
            $this->trace->output('No count markers found.');
            return $result;
        }

        // Count each referenced course once.
        $texts = [];
        foreach (array_unique($courseids) as $courseid) {
            $count = self::count_students($courseid);
            if ($count === null) {
                $this->trace->output("Course $courseid does not exist; its markers are left unchanged.");
                continue;
            }
            $texts[$courseid] = formatter::format($count);
            $this->trace->output("Course $courseid: $count students, shown as \"{$texts[$courseid]}\".");
        }

        // Second pass: rewrite and save the blocks whose texts change, starting from their latest saved version.
        foreach ($blockids as $instanceid) {
            $current = $DB->get_field('block_instances', 'configdata', ['id' => $instanceid]);
            $config = is_string($current) ? self::decode($current) : null;
            if ($config === null) {
                $this->trace->output("Block $instanceid was deleted or emptied during the run; skipped.");
                continue;
            }
            $changed = 0;
            $skipped = [];
            $config = self::walk($config, function (string $text) use ($texts, &$changed, &$skipped): string {
                [$text, $n, $s] = markers::replace($text, $texts);
                $changed += $n;
                $skipped = array_merge($skipped, $s);
                return $text;
            });
            $result['skipped'] = array_merge($result['skipped'], $skipped);
            if ($skipped) {
                $this->trace->output("Block $instanceid: markers left unchanged for course(s) " .
                    implode(', ', array_unique($skipped)) . '.');
            }
            if ($changed === 0) {
                $this->trace->output("Block $instanceid: up to date.");
                continue;
            }
            if ($this->dryrun) {
                $result['changed'] += $changed;
                $result['updated'][] = $instanceid;
                $this->trace->output("Block $instanceid: $changed marker(s) would change (dry run, nothing saved).");
                continue;
            }
            if (!$this->save($instanceid, $current, $config)) {
                $result['conflicts'][] = $instanceid;
                $this->trace->output("Block $instanceid changed during the run; skipped, the next run will update it.");
                continue;
            }
            $result['changed'] += $changed;
            $result['updated'][] = $instanceid;
            $this->trace->output("Block $instanceid: $changed marker(s) updated.");
        }
        $result['skipped'] = array_values(array_unique($result['skipped']));
        return $result;
    }

    /**
     * Number of students counted for a course, or null when the course does not exist.
     *
     * @param int $courseid
     * @return int|null
     */
    public static function count_students(int $courseid): ?int {
        global $DB;
        if ($courseid == SITEID || !$DB->record_exists('course', ['id' => $courseid])) {
            return null;
        }
        return count_enrolled_users(context_course::instance($courseid), self::STUDENT_CAPABILITY);
    }

    /**
     * Restore a block configuration from a backup, keeping a copy of the configuration it replaces.
     *
     * @param int $backupid Id in local_alborhancounts_bak.
     * @return array{0: int, 1: int} Id of the restored block instance, id of the copy of the replaced configuration.
     */
    public static function restore(int $backupid): array {
        global $DB;
        $backup = $DB->get_record('local_alborhancounts_bak', ['id' => $backupid], '*', MUST_EXIST);
        $transaction = $DB->start_delegated_transaction();
        $current = self::read_locked((int) $backup->blockinstanceid);
        if ($current === false) {
            throw new \dml_missing_record_exception('block_instances');
        }
        $copyid = self::write((int) $backup->blockinstanceid, (string) $current, $backup->configdata);
        $transaction->allow_commit();
        return [(int) $backup->blockinstanceid, $copyid];
    }

    /**
     * Save a block configuration unless it changed since it was read.
     *
     * @param int $instanceid Block instance id.
     * @param string $previous Configdata the new configuration was built from, as stored.
     * @param stdClass $config New configuration.
     * @return bool False when the stored configuration no longer matches $previous (nothing is written).
     */
    private function save(int $instanceid, string $previous, stdClass $config): bool {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        // Compare in PHP: an SQL comparison would follow the column's case-insensitive collation on MySQL/MariaDB.
        if (self::read_locked($instanceid) !== $previous) {
            $transaction->allow_commit();
            return false;
        }
        // The same encoding as block_base::instance_config_save(), without needing the block plugin's class.
        self::write($instanceid, $previous, base64_encode(serialize($config)));
        $transaction->allow_commit();
        return true;
    }

    /**
     * Read a block's configdata and lock the row until the end of the transaction, so no save can slip in between.
     *
     * Must be called inside a transaction. FOR UPDATE works on MySQL, MariaDB and PostgreSQL.
     *
     * @param int $instanceid Block instance id.
     * @return string|false The stored configdata, or false when the block does not exist.
     */
    private static function read_locked(int $instanceid): string|false {
        global $DB;
        $value = $DB->get_field_sql('SELECT configdata FROM {block_instances} WHERE id = ? FOR UPDATE', [$instanceid]);
        return $value === false ? false : (string) $value;
    }

    /**
     * Copy a block's current configuration to the backup table, write the new one and prune old copies.
     *
     * Must be called inside a transaction.
     *
     * @param int $instanceid Block instance id.
     * @param string $current Configdata being replaced, as stored.
     * @param string $new Configdata to store.
     * @return int Id of the copy of $current.
     */
    private static function write(int $instanceid, string $current, string $new): int {
        global $DB;
        $copyid = $DB->insert_record('local_alborhancounts_bak', (object) [
            'blockinstanceid' => $instanceid,
            'configdata' => $current,
            'timecreated' => time(),
        ]);
        $DB->update_record('block_instances', (object) [
            'id' => $instanceid,
            'configdata' => $new,
            'timemodified' => time(),
        ]);
        $ids = $DB->get_fieldset_sql(
            'SELECT id FROM {local_alborhancounts_bak} WHERE blockinstanceid = ? ORDER BY id DESC',
            [$instanceid],
        );
        $old = array_slice($ids, self::BACKUPS_KEPT);
        if ($old) {
            $DB->delete_records_list('local_alborhancounts_bak', 'id', $old);
        }
        return $copyid;
    }

    /**
     * Decode a stored block configuration.
     *
     * @param string|null $configdata
     * @return stdClass|null Null when there is no configuration.
     */
    private static function decode(?string $configdata): ?stdClass {
        if ($configdata === null || $configdata === '') {
            return null;
        }
        return unserialize_object(base64_decode($configdata));
    }

    /**
     * Apply a callback to every string inside a configuration, at any depth.
     *
     * @param mixed $value Configuration object, array or scalar.
     * @param callable $callback Receives a string, returns the string to store.
     * @return mixed The value with its strings replaced.
     */
    private static function walk(mixed $value, callable $callback): mixed {
        if (is_string($value)) {
            return $callback($value);
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::walk($item, $callback);
            }
            return $value;
        }
        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $key => $item) {
                $value->$key = self::walk($item, $callback);
            }
        }
        return $value;
    }
}
