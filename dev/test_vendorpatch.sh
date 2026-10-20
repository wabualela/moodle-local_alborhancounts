#!/usr/bin/env bash
# Tests for vendor-patches/vendorpatch.sh on a synthetic Moodle tree (no root, no real vendor files).
#
# Usage: dev/test_vendorpatch.sh        (needs bash, GNU patch, diff, sha256sum and php)
# Environment: PHP  PHP binary used for the syntax check (default: php)
#
# Exit status: 0 when every check passes, 1 otherwise.

set -uo pipefail

plugindir=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
php=${PHP:-php}
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
fails=0
checks=0

ok() { checks=$((checks + 1)); echo "ok   $1"; }
bad() { checks=$((checks + 1)); fails=$((fails + 1)); echo "FAIL $1"; }
expect_rc() { # expected, actual, name
    if [ "$1" = "$2" ]; then ok "$3 (exit $2)"; else bad "$3: exit $2, expected $1"; fi
}
same_file() { # file, file, name
    if cmp -s "$1" "$2"; then ok "$3"; else bad "$3"; fi
}

# Synthetic vendor files, their patched versions, a patch and its hash list.
mkdir -p "$work/a/theme/x/classes" "$work/a/local/y/classes"
printf '<?php\n$n = count($this->get_enrolled_students($course, $context));\n' > "$work/a/theme/x/classes/one.php"
printf '<?php\nfunction two() {\n    return 2;\n}\n' > "$work/a/local/y/classes/two.php"
cp -r "$work/a" "$work/b"
printf '<?php\n// ALBORHAN-PATCH test v1\n$n = 1;\n' > "$work/b/theme/x/classes/one.php"
printf '<?php\nfunction two() {\n    return 22;\n}\n' > "$work/b/local/y/classes/two.php"
mkdir -p "$work/vp"
cp "$plugindir/vendor-patches/vendorpatch.sh" "$work/vp/"
(cd "$work" && diff -ruN a b) > "$work/vp/test.diff"
{
    echo "# pre post path"
    for f in theme/x/classes/one.php local/y/classes/two.php; do
        echo "$(sha256sum "$work/a/$f" | cut -d' ' -f1) $(sha256sum "$work/b/$f" | cut -d' ' -f1) $f"
    done
} > "$work/vp/test.sha256"

# A patch that produces invalid PHP, with correct hashes, to test the syntax gate.
cp -r "$work/a" "$work/c"
printf '<?php\n$n = ;\n' > "$work/c/theme/x/classes/one.php"
(cd "$work" && diff -ruN a/theme c/theme) > "$work/vp/broken.diff"
f=theme/x/classes/one.php
echo "$(sha256sum "$work/a/$f" | cut -d' ' -f1) $(sha256sum "$work/c/$f" | cut -d' ' -f1) $f" > "$work/vp/broken.sha256"

reset_tree() {
    rm -rf "$work/public" "$work/backups"
    cp -r "$work/a" "$work/public"
    chmod 664 "$work/public/theme/x/classes/one.php" "$work/public/local/y/classes/two.php"
}
vp() {
    MOODLE_PUBLIC="$work/public" BACKUP_ROOT="$work/backups" ALLOW_NONROOT=1 PHP="$php" \
        bash "$work/vp/vendorpatch.sh" "$@"
}

reset_tree
vp status test >/dev/null; expect_rc 1 $? "status on original files"

out=$(vp apply test 2>&1); expect_rc 0 $? "apply"
same_file "$work/public/theme/x/classes/one.php" "$work/b/theme/x/classes/one.php" "apply: first file patched"
same_file "$work/public/local/y/classes/two.php" "$work/b/local/y/classes/two.php" "apply: second file patched"
[ "$(stat -c %a "$work/public/theme/x/classes/one.php")" = 664 ] && ok "apply keeps the file mode" || bad "apply keeps the file mode"
[ -z "$(find "$work/public" -name '*.vpnew')" ] && ok "apply leaves no temporary file" || bad "apply leaves no temporary file"
backup=$(echo "$out" | sed -n 's/^saved the current files in //p')
[ -f "$backup/public/theme/x/classes/one.php" ] && ok "apply saves a backup" || bad "apply saves a backup"
vp status test >/dev/null; expect_rc 0 $? "status after apply"

out=$(vp apply test 2>&1); expect_rc 0 $? "apply again"
echo "$out" | grep -q "already patched" && ok "apply again does nothing" || bad "apply again does nothing"

vp revert test >/dev/null 2>&1; expect_rc 0 $? "revert"
same_file "$work/public/theme/x/classes/one.php" "$work/a/theme/x/classes/one.php" "revert: first file original"
same_file "$work/public/local/y/classes/two.php" "$work/a/local/y/classes/two.php" "revert: second file original"
vp status test >/dev/null; expect_rc 1 $? "status after revert"

# Restore from the backup taken by apply, after the live file was damaged.
vp apply test >/dev/null 2>&1
echo "damaged" >> "$work/public/theme/x/classes/one.php"
vp restore "$backup" >/dev/null 2>&1; expect_rc 0 $? "restore"
same_file "$work/public/theme/x/classes/one.php" "$work/a/theme/x/classes/one.php" "restore puts the saved file back"

# A file that matches neither hash: status says unknown, apply refuses and changes nothing.
reset_tree
echo "// local edit" >> "$work/public/theme/x/classes/one.php"
cp "$work/public/theme/x/classes/one.php" "$work/edited.php"
vp status test >/dev/null; expect_rc 3 $? "status on an edited file"
vp apply test >/dev/null 2>&1; expect_rc 2 $? "apply refuses an edited file"
same_file "$work/public/theme/x/classes/one.php" "$work/edited.php" "refused apply leaves the edited file"
same_file "$work/public/local/y/classes/two.php" "$work/a/local/y/classes/two.php" "refused apply leaves the other file"

# Half applied: one file patched by hand, the other original.
reset_tree
cp "$work/b/theme/x/classes/one.php" "$work/public/theme/x/classes/one.php"
vp apply test >/dev/null 2>&1; expect_rc 2 $? "apply refuses a half-applied state"
same_file "$work/public/local/y/classes/two.php" "$work/a/local/y/classes/two.php" "half-applied: other file untouched"

# A patch that breaks PHP syntax is never put live.
reset_tree
vp apply broken >/dev/null 2>&1; expect_rc 2 $? "apply refuses a patch with a PHP syntax error"
same_file "$work/public/theme/x/classes/one.php" "$work/a/theme/x/classes/one.php" "syntax error: live file untouched"

# Without root and without ALLOW_NONROOT, changes are refused.
reset_tree
MOODLE_PUBLIC="$work/public" BACKUP_ROOT="$work/backups" bash "$work/vp/vendorpatch.sh" apply test >/dev/null 2>&1
rc=$?
if [ "$(id -u)" -eq 0 ]; then ok "root check skipped (running as root)"; else expect_rc 2 $rc "apply refuses without root"; fi

# Unknown patch name.
vp status nosuchpatch >/dev/null 2>&1; expect_rc 2 $? "unknown patch name"

# The real patch files are well formed: every path in each .sha256 appears in its .diff.
for sums in "$plugindir"/vendor-patches/*.sha256; do
    diff="${sums%.sha256}.diff"
    missing=0
    while read -r pre post path; do
        [ -n "${path:-}" ] && [ "${pre:0:1}" != "#" ] || continue
        grep -q "^+++ b/$path" "$diff" || missing=1
        [ ${#pre} -eq 64 ] && [ ${#post} -eq 64 ] || missing=1
    done < "$sums"
    [ "$missing" = 0 ] && ok "$(basename "$sums") matches $(basename "$diff")" || bad "$(basename "$sums") matches $(basename "$diff")"
done

echo "$((checks - fails)) passed, $fails failed"
[ "$fails" -eq 0 ]
