#!/usr/bin/env bash
# Latency of the pages the card-count patches change, from the nginx "perf" access log (urt = upstream time).
# Prints only aggregates (count, p50, p90, p95 of urt in seconds); no IP address, user agent or URL is printed.
#
# Usage: sudo bash measure-endpoints.sh "YYYY-MM-DD HH:MM" "YYYY-MM-DD HH:MM" [LOGFILE...]
#   Default log files: /var/log/nginx/access-moodle.log.1 and access-moodle.log; .gz files are read too.
# Example (before/after windows of the same weekday and hours):
#   sudo bash measure-endpoints.sh "2026-10-19 08:00" "2026-10-19 23:00"
set -euo pipefail
[ $# -ge 2 ] || { sed -n '2,8{s/^# \{0,1\}//;p}' "$0"; exit 2; }
from=$(date -d "$1" +%s) || exit 2
to=$(date -d "$2" +%s) || exit 2
shift 2
[ $# -gt 0 ] || set -- /var/log/nginx/access-moodle.log.1 /var/log/nginx/access-moodle.log
for f in "$@"; do [ -r "$f" ] || { echo "cannot read $f (run with sudo?)" >&2; exit 2; }; done
zcat -f -- "$@" | gawk -v from="$from" -v to="$to" '
BEGIN {
    split("Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec", m, " ")
    for (i = 1; i <= 12; i++) mon[m[i]] = i
}
{
    # [20/Oct/2026:04:27:01 +0300]
    if (!match($0, /\[([0-9]+)\/([A-Za-z]+)\/([0-9]+):([0-9]+):([0-9]+):([0-9]+) /, t)) next
    ts = mktime(t[3] " " mon[t[2]] " " t[1] " " t[4] " " t[5] " " t[6])
    if (ts < from || ts >= to) next
    if (!match($0, /"([A-Z]+) ([^ "]*) [^"]*" ([0-9]{3}) /, r)) next
    # urt is the last field of the "perf" format; anchor after the last quoted field so a URL, referer or user agent
    # containing "urt=" is never read as a latency.
    if (!match($0, / rt=[0-9.]+ urt=([0-9.]+)[^"]*$/, u)) next
    method = r[1]; uri = r[2]; status = r[3]; urt = u[1] + 0
    ep = ""
    if (method == "GET" && status == "200" && uri ~ /^\/enrol\/index\.php(\?|$)/) ep = "GET /enrol/index.php 200"
    else if (method == "GET" && status == "200" && (uri == "/" || uri ~ /^\/\?/ || uri ~ /^\/index\.php(\?|$)/)) ep = "GET / 200"
    else if (method == "POST" && status == "200" && uri ~ /^\/lib\/ajax\/service\.php/ && uri ~ /theme_remui_get_courses/) ep = "AJAX theme_remui_get_courses 200"
    else if (method == "POST" && status == "200" && uri ~ /^\/lib\/ajax\/service\.php/ && uri ~ /local_edwiserpagebuilder_get_courses/) ep = "AJAX local_edwiserpagebuilder_get_courses 200"
    if (ep == "") next
    n[ep]++
    v[ep, n[ep]] = urt
}
function pct(ep, p,    k, a, i) {
    k = n[ep]
    for (i = 1; i <= k; i++) a[i] = v[ep, i]
    asort(a)
    i = int(p * k + 0.999999); if (i < 1) i = 1; if (i > k) i = k
    return a[i]
}
END {
    printf "%-45s %7s %7s %7s %7s\n", "endpoint (urt, seconds)", "n", "p50", "p90", "p95"
    for (ep in n) printf "%-45s %7d %7.3f %7.3f %7.3f\n", ep, n[ep], pct(ep, 0.50), pct(ep, 0.90), pct(ep, 0.95)
}'
