# Vendor patches (Edwiser theme_remui and local_edwiserpagebuilder)

Small, reversible patches to Edwiser code that make the course cards cheap to render. They are kept here because
the Moodle core git repository does not track third-party plugins, so nothing else records them. These files are
not deployed with the plugin (leave `vendor-patches/` out of the copy on the server).

| Patch | Files | What it changes |
|---|---|---|
| `remui-enrolpage-limits` | `theme/remui/classes/EnrolmentPageHandler.php` | The enrol page builds the related and latest course lists only when they can be shown (setting on, or editing), and only as many cards as it shows: 5 related instead of 10, `latestcoursecount` + 2 latest instead of 20. Output is identical. Needs nothing installed. |
| `remui-counts` | `theme/remui/classes/coursehandler.php`, `local/edwiserpagebuilder/classes/coursehandler.php` | The course cards get their student count from `\local_alborhancounts\local\cards::count_students()` (one `COUNT` query, the same number) instead of loading every student. Falls back to the vendor code when the plugin is missing, switched off (`cardcounts`) or fails. |

Each patch line is marked `ALBORHAN-PATCH <name> v1`. The `.sha256` files pin the exact vendor release the patch
was written and tested for (theme_remui 5.2.2, local_edwiserpagebuilder 4.2.26): `pre-patch post-patch path`.

## vendorpatch.sh

```bash
VP=/home/wabualela/local_alborhancounts/vendor-patches
sudo bash $VP/vendorpatch.sh status [PATCH]     # exit 0 applied, 1 not applied, 3 unknown (vendor changed)
sudo bash $VP/vendorpatch.sh apply  PATCH       # hash check, staged patch + php -l + hash check, backup, atomic swap
sudo bash $VP/vendorpatch.sh revert PATCH       # reverse, verifies the original hashes
sudo bash $VP/vendorpatch.sh restore BACKUPDIR  # put back the files saved by apply/revert
```

- PHP-FPM picks a changed file up within 60 s (`opcache.revalidate_freq=60`). Do not reload PHP-FPM.
- `apply` refuses (and changes nothing) when a file matches neither hash, when the patch is half applied, or when
  the patched file has a syntax error.
- Tests: `dev/test_vendorpatch.sh` (also run in CI).

## After an Edwiser upgrade

An upgrade replaces the patched files. The plugin reports it three ways: the **Edwiser course-card patch** check in
Site administration → Reports → System status (also `admin/cli/checks.php --filter=local_alborhancounts`), the
hourly task **Verify the Edwiser course-card patch** failing, and `vendorpatch.sh status` exiting 1 or 3. Add
`vendorpatch.sh status` to the Edwiser upgrade checklist. To re-apply on a new release: update the `.diff` against
the new vendor files, record the new hashes in the `.sha256`, check `tests/fixtures/vendor_reference.php` still
matches the vendor function, run the tests, release, then `apply`.

## measure-endpoints.sh

Aggregate latency (count, p50, p90, p95 of upstream time) of the pages the patches change, from the nginx access
log, for before/after comparisons. Prints no IP address, user agent or URL.

```bash
sudo bash $VP/measure-endpoints.sh "2026-10-19 08:00" "2026-10-19 23:00"
```
