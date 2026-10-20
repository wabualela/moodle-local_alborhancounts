#!/usr/bin/env bash
# Apply, check and roll back the Alborhan patches to Edwiser vendor code (theme_remui, local_edwiserpagebuilder).
#
# Usage (from anywhere; apply/revert/restore need root because the code tree belongs to www-data):
#   vendorpatch.sh status  [PATCH]          state of each patched file; exit 0 = applied, 1 = not applied, 3 = unknown
#   vendorpatch.sh apply   [PATCH]          check hashes, dry run, back up, patch, keep owner/mode, php -l, verify
#   vendorpatch.sh revert  [PATCH]          reverse the patch (patch -R), verify the original hashes
#   vendorpatch.sh restore BACKUPDIR        copy the files saved by "apply" back (when revert is not possible)
#
# PATCH is the name of a .diff file next to this script without the extension (default: remui-counts).
#   remui-counts           card counts through local_alborhancounts (theme_remui + local_edwiserpagebuilder)
#   remui-enrolpage-limits enrol page computes only the cards it shows (theme_remui)
#
# Environment:
#   MOODLE_PUBLIC  Moodle public directory (default /var/www/alborhan/html/public)
#   BACKUP_ROOT    where "apply" saves the original files (default /var/backups/alborhan-vendor-patches)
#   PHP            PHP binary for the syntax check (default php8.4, the version of the site)
#
# Every target file must match the hash recorded in PATCH.sha256 (format: "<pre> <post> <path>"), so the patch is
# applied only to the exact vendor release it was written and tested for. After a vendor upgrade "apply" refuses:
# update the .diff and .sha256 in the repository, test, and release them first.

set -euo pipefail

here=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
public=${MOODLE_PUBLIC:-/var/www/alborhan/html/public}
backuproot=${BACKUP_ROOT:-/var/backups/alborhan-vendor-patches}
php=${PHP:-php8.4}

usage() { sed -n '2,/^$/{s/^# \{0,1\}//;p}' "${BASH_SOURCE[0]}"; }
die() { echo "vendorpatch: $*" >&2; exit 2; }

cmd=${1:-}
[ -n "$cmd" ] || { usage; exit 2; }
case "$cmd" in -h|--help) usage; exit 0 ;; esac

if [ "$cmd" = restore ]; then
    dir=${2:-}
    [ -d "$dir" ] || die "restore needs the backup directory printed by apply"
    [ "$(id -u)" -eq 0 ] || [ "${ALLOW_NONROOT:-0}" = 1 ] || die "run with sudo"
    (cd "$dir/public" && find . -type f -print) | while IFS= read -r f; do
        f=${f#./}
        # Copy next to the target, then rename: a PHP worker never reads a half-written file.
        cp -a "$dir/public/$f" "$public/$f.vpnew" && mv -f "$public/$f.vpnew" "$public/$f"
        echo "restored $public/$f"
    done
    exit 0
fi

name=${2:-remui-counts}
diff="$here/$name.diff"
sums="$here/$name.sha256"
[ -f "$diff" ] || die "no patch $diff"
[ -f "$sums" ] || die "no hash list $sums"
[ -d "$public" ] || die "no Moodle public directory $public"

# State of one file: original, patched, or unknown (vendor changed, or edited by hand).
state() {
    local pre=$1 post=$2 path=$3 sum
    [ -f "$public/$path" ] || { echo missing; return; }
    sum=$(sha256sum "$public/$path" | cut -d' ' -f1)
    if [ "$sum" = "$pre" ]; then echo original
    elif [ "$sum" = "$post" ]; then echo patched
    else echo unknown
    fi
}

states() {
    while read -r pre post path; do
        [ -n "${path:-}" ] && [ "${pre:0:1}" != "#" ] || continue
        echo "$(state "$pre" "$post" "$path") $path"
    done < "$sums"
}

case "$cmd" in
    status)
        rc=0
        while read -r st path; do
            component_dir=$(echo "$path" | cut -d/ -f1-2)
            release=$(sed -n "s/^\$plugin->release *= *'\(.*\)';.*/\1/p" "$public/$component_dir/version.php" 2>/dev/null || true)
            marker=$(grep -c 'ALBORHAN-PATCH' "$public/$path" 2>/dev/null || true)
            printf '%-9s %-55s release %-8s markers %s\n' "$st" "$path" "${release:-?}" "${marker:-0}"
            case "$st" in
                patched) ;;
                original) [ "$rc" -eq 3 ] || rc=1 ;;
                *) rc=3 ;;
            esac
        done < <(states)
        exit "$rc"
        ;;
    apply|revert)
        [ "$(id -u)" -eq 0 ] || [ "${ALLOW_NONROOT:-0}" = 1 ] || die "run with sudo"
        command -v patch >/dev/null || die "GNU patch is not installed"
        want=original; done_state=patched; rev=()
        if [ "$cmd" = revert ]; then want=patched; done_state=original; rev=(-R); fi
        files=()
        all_done=1
        while read -r st path; do
            files+=("$path")
            if [ "$st" = "$done_state" ]; then continue; fi
            all_done=0
            [ "$st" = "$want" ] || die "$path is '$st', expected '$want': vendor release changed or file edited; nothing done"
        done < <(states)
        if [ "$all_done" = 1 ]; then echo "$name: already $done_state, nothing to do"; exit 0; fi
        # Refuse a half-applied state rather than guessing.
        while read -r st path; do
            [ "$st" = "$want" ] || die "$path is already '$st' while others are not: fix by hand (status/restore)"
        done < <(states)
        # Patch and lint a staged copy first; the live files are only replaced once everything is verified.
        stage=$(mktemp -d)
        trap 'rm -rf "$stage"' EXIT
        for f in "${files[@]}"; do (cd "$public" && cp -a --parents "$f" "$stage/"); done
        patch "${rev[@]}" -p1 -N -F0 --dry-run -d "$stage" < "$diff" >/dev/null || die "dry run failed; nothing done"
        patch "${rev[@]}" -p1 -N -F0 --no-backup-if-mismatch -d "$stage" < "$diff" >/dev/null || die "patch failed; nothing done"
        for f in "${files[@]}"; do
            "$php" -l "$stage/$f" >/dev/null || die "syntax error in patched $f; nothing done"
        done
        while read -r pre post path; do
            [ -n "${path:-}" ] && [ "${pre:0:1}" != "#" ] || continue
            sum=$(sha256sum "$stage/$path" | cut -d' ' -f1)
            if [ "$cmd" = apply ]; then wantsum=$post; else wantsum=$pre; fi
            [ "$sum" = "$wantsum" ] || die "staged $path has hash $sum, expected $wantsum; nothing done"
        done < "$sums"
        backup="$backuproot/$(date -u +%Y%m%dT%H%M%SZ)-$name-$cmd"
        mkdir -p "$backup/public"
        for f in "${files[@]}"; do (cd "$public" && cp -a --parents "$f" "$backup/public/"); done
        echo "saved the current files in $backup"
        for f in "${files[@]}"; do
            # Same directory as the target, so mv is an atomic rename; keep the vendor owner and mode.
            cp "$stage/$f" "$public/$f.vpnew"
            chown --reference="$public/$f" "$public/$f.vpnew" 2>/dev/null || true
            chmod --reference="$public/$f" "$public/$f.vpnew"
            mv -f "$public/$f.vpnew" "$public/$f"
        done
        while read -r st path; do
            [ "$st" = "$done_state" ] || { echo "vendorpatch: $path is '$st' after $cmd; restore with: $0 restore $backup" >&2; exit 2; }
        done < <(states)
        echo "$name: $cmd done. PHP-FPM picks the change up within 60 s (opcache.revalidate_freq). Do not reload FPM."
        echo "roll back with: $0 $([ "$cmd" = apply ] && echo revert || echo apply) $name   (or: $0 restore $backup)"
        ;;
    *) usage; exit 2 ;;
esac
