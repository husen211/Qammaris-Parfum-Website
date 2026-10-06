#!/usr/bin/env bash
set -eu
umask 077
root=/home/u429527638/domains/qammarisparfum.id
private="$root/shared/qammaris-website/storage/app/private"
cd "$root/current"
date -u +%Y-%m-%dT%H:%M:%SZ > "$private/worker-watchdog-last-run"
if flock -n "$private/qammaris-worker.lock" true; then
    # Do not inherit Hostinger's cron lock into the persistent process.
    nohup flock -n "$private/qammaris-worker.lock" php artisan queue:work database --queue=qammaris-app --sleep=1 --timeout=60 --tries=5 < /dev/null 3>&- >> storage/logs/qammaris-app-worker.log 2>&1 &
    printf '%s\n' "$!" > "$private/qammaris-worker.pid"
fi
