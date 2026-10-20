# Changelog

All notable changes to local_alborhancounts.

## [Unreleased]

## [1.1.0] - 2026-10-20 (2026102100)
### Added
- `\local_alborhancounts\local\cards::count_students()`: the Edwiser course-card student count with one `COUNT`
  query, the same number as the vendor's `get_enrolled_students()` for every viewer (separate groups included).
  Request memo always; optional shared cache (`cardcachettl`, default 0 = off) dropped by enrolment, role and
  capability events. Never throws: returns null so the vendor code counts instead.
- `vendor-patches/`: `remui-enrolpage-limits` (the enrol page builds only the cards it shows) and `remui-counts`
  (theme_remui and local_edwiserpagebuilder call the helper), with sha256 pins and `vendorpatch.sh`
  (status/apply/revert/restore) plus `measure-endpoints.sh`.
- Status check **Edwiser course-card patch** and hourly task **Verify the Edwiser course-card patch**: report a
  patched file that lost its hook, a helper that fails, or the counts switched off.
- `cli/verify_cards.php`: compares the vendor count with ours on real data for one viewer (read-only).
- Settings `cardcounts`, `cardcachettl`, `vendorpatchcheck`, `vendorpatches`.
- Tests: helper against a verbatim copy of the vendor function, caches and invalidation, status check, task, and
  `dev/test_vendorpatch.sh` (run in CI).

### Review fixes (before release)
- `vendorpatch.sh` replaces files as their owner (runuser) through a random temporary name and an atomic rename, and
  refuses symbolic links, so root never writes inside the www-data-writable code tree.
- `vendorpatch.sh restore` refuses a backup from another vendor release (saved `version.php`), unless `--force`;
  keeps a copy of the files it replaces; verifies the result.
- Course moves (`course_updated`) and category moves (`course_category_updated`) drop the shared cache.
- The status check shows the vendor release from `version.php`.
- `measure-endpoints.sh` reads the latency from the last log field and reports unreadable log files.
- Tests no longer depend on the Edwiser plugins being absent from the code tree.

## [1.0.0] - not released separately (2026102000; included in 1.1.0)
### Added
- Daily scheduled task that rewrites `data-alborhan-count` markers in page-builder blocks with the rounded number
  of students of each course (hundreds, tens, or exact below 10, with "+" when rounded down).
- A block configuration is saved only when a text changes; the previous configuration is copied first (last 10
  copies per block kept).
- Settings to turn the task off and to choose the block types searched.
- CLI scripts: `cli/update.php` (dry run by default, `--execute` to save) and `cli/restore.php` (list or restore
  copies).
- Each block is re-read before it is rewritten and skipped if it changed during the run.
- The task fails, after saving what it can, when markers cannot be updated or have disappeared, so the problem
  shows in the failed tasks check.
- Warning when a block with markers still contains the Edwiser course-card component, which the page builder
  regenerates on save.
- Restoring a copy first saves the configuration it replaces.
- PHPUnit tests for the formatter, the marker rewriting, the updater and the task.
