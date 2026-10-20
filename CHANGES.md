# Changelog

All notable changes to local_alborhancounts.

## [Unreleased]

## [1.0.0] - unreleased (2026102000)
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
