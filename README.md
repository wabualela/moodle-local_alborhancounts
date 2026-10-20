# Alborhan course counts (local_alborhancounts)

Keeps the "number of students" shown on page-builder course cards up to date without counting on every page
view.

The Edwiser `[edwiser-fc]` course cards count the enrolled students of every card on every render by loading
the full user rows of each course, guests included. On a front page with a few large courses this costs most of
the page's server time. This plugin lets the cards be static HTML instead: a scheduled task counts the students
once a day and writes the rounded numbers into the stored block HTML.

It also makes the Edwiser course cards cheap everywhere else (enrol page, course archive, front page): with the
small vendor patches in `vendor-patches/`, the cards get their student count from this plugin with one `COUNT`
query instead of loading every student (see "Course card counts" below).

## How it works

1. In the page-builder editor, **delete the whole "Featured Courses" component**, that is the element
   `<div class="edwiser-fc" data-edwiser-dynamic data-shortcode="edwiser-fc" …>`, and add the static cards
   as a separate HTML element (for example the HTML the shortcode rendered). On every save the page builder
   empties each `data-edwiser-dynamic` element and writes the shortcode back, so cards left inside that
   wrapper would be thrown away. The static cards must not use the class `edwiser-fc` or the attributes
   `data-edwiser-dynamic` / `data-shortcode`. Put each number inside a marker:

   ```html
   <span dir="ltr" data-alborhan-count="199">+4,700</span> طالب
   ```

   `199` is the course id. Only the text inside the span is ever changed; spaces around it are kept.
   `dir="ltr"` keeps the "+" on the left of the number on right-to-left pages. Do not put other tags inside
   the span: such a marker is left alone and reported.

   After saving, run the dry run (see Command line) and check that every marker is found and that no
   warning about the Edwiser component is printed.
2. The task **Update static enrolment counts** (`\local_alborhancounts\task\update_counts`, daily at 01:40)
   reads every block of the configured types (default `edwiseradvancedblock`), counts the students of each
   course found in a marker and rewrites the marker text.
3. A block is saved only when one of its texts changes. Each block is read again just before it is
   rewritten and locked while it is saved, and the write is skipped (and retried the next night) if an editor
   saved it in the meantime.
   Before saving, the previous configuration is copied to the table `local_alborhancounts_bak` (the last 10
   copies per block are kept).
4. The task **fails** (so it shows in Site administration → Reports → System status → failed tasks) when a
   marker could not be updated (course deleted, tags inside the marker) or when markers it updated before have
   disappeared from a block it updated before, for example because the page builder put the shortcode back.
   Everything else is still saved first. If the cards are removed on purpose, turn the plugin off.

**What is counted:** users enrolled in the course, active or suspended (including enrolments in disabled
enrolment methods), who have `moodle/course:isincompletionreports` (students), excluding deleted accounts.
This is the population the Edwiser cards show, without their dependence on the viewer's groups (in a course
with separate groups the cards show logged-out visitors no number and students only their own group; the
static text is the same for everyone). Core `count_enrolled_users()` is used: one `COUNT` query per course.

**Rounding:** 100 or more is rounded down to the hundred, 10–99 to the ten, below 10 is shown as is. A "+" is
added when the rounded value is below the real count: 4793 → `+4,700`, 4700 → `4,700`, 98 → `+90`, 7 → `7`.

## Course card counts (vendor patch)

`\local_alborhancounts\local\cards::count_students($course, $context)` returns the number the Edwiser card shows
to the current user: the same population as the vendor's `get_enrolled_students()` (any enrolment, active or not,
users with `moodle/course:isincompletionreports`, not deleted), including its separate-groups rule (a viewer
without `moodle/site:accessallgroups` sees only the students of their own groups, and 0 without a group).

- The patched vendor code calls it through a `class_exists()` guard and falls back to its own count when it
  returns null: plugin not installed, `cardcounts` switched off, or any error.
- A count that is the same for every viewer is memoised for the request (the front page renders its cards twice).
  With `cardcachettl` above 0 it is also shared between requests; enrolment, role and capability events drop it.
  The default 0 always gives the exact number.
- The **Edwiser course-card patch** status check and the hourly task report a patched file that lost its hook,
  for example after a theme upgrade. `cli/verify_cards.php --userid=N` compares the vendor count with ours on
  real data (read-only).
- How to apply, check and roll back the patches: `vendor-patches/README.md`.

## Settings

Site administration → Plugins → Local plugins → Alborhan course counts:

- **Update the counts** (`local_alborhancounts/enabled`, default on): the scheduled task does nothing when off.
- **Block types** (`local_alborhancounts/blocknames`, default `edwiseradvancedblock`): comma-separated block
  types to search.
- **Count students for course cards** (`cardcounts`, default on): untick to make the patched cards count with the
  vendor code again, without changing any file.
- **Shared cache lifetime** (`cardcachettl`, seconds, default 0 = no shared cache).
- **Check the vendor patches** (`vendorpatchcheck`, default on) and **Expected vendor patches** (`vendorpatches`,
  default `remui-counts,remui-enrolpage-limits`).

## Command line

Run as the web server user from the Moodle code root:

```bash
sudo -u www-data php8.4 public/local/alborhancounts/cli/update.php            # dry run: counts and planned changes
sudo -u www-data php8.4 public/local/alborhancounts/cli/update.php --execute  # save
sudo -u www-data php8.4 public/local/alborhancounts/cli/restore.php           # list the saved copies
sudo -u www-data php8.4 public/local/alborhancounts/cli/restore.php --backupid=12
sudo -u www-data php8.4 public/local/alborhancounts/cli/verify_cards.php --userid=2 --courseids=199,725
```

Restoring a copy also undoes any edit made to the block after the copy was taken, so the configuration it
replaces is saved as a new copy first; the script prints its id, and restoring that copy undoes the restore.

## Requirements

Moodle 5.1 or 5.2. No dependency on the Edwiser plugins: the block configuration is read and written the same
way core `block_base::instance_config_save()` does.

## Privacy

No personal data is stored: only per-course totals inside block content or in the cache, and copies of block
settings.

## Development

- `dev/check.sh`: `php -l` and phpcs with the `moodle-extra` standard (`dev/check.sh --install` installs
  moodle-cs into `dev/tools`).
- `dev/test_vendorpatch.sh`: tests for `vendor-patches/vendorpatch.sh` on a synthetic tree.
- Deploy without the development files: `rsync --exclude=.git/ --exclude=.github/ --exclude=dev/
  --exclude=vendor-patches/ --exclude=.gitignore`.
- PHPUnit tests are in `tests/`; CI runs them with moodle-plugin-ci (`.github/workflows/moodle-plugin-ci.yml`).
  Never run them against a production site.

## License

GNU GPL v3 or later. © 2026 Alborhan Academy.
