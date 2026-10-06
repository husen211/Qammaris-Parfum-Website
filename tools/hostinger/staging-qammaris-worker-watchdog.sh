#!/usr/bin/env bash
set -eu
umask 077
cd /home/u429527638/domains/staging.qammarisparfum.id/public_html
date -u +%Y-%m-%dT%H:%M:%SZ > storage/app/private/p8-03-worker-watchdog-last-run
if flock -n storage/app/private/p8-03-worker.lock true; then
    # Hostinger passes its cron lock on fd 3; the persistent child must not retain it.
    nohup flock -n storage/app/private/p8-03-worker.lock php artisan queue:work database --queue=qammaris-app --sleep=1 --timeout=60 --tries=5 < /dev/null 3>&- >> storage/logs/qammaris-app-worker.log 2>&1 &
    printf '%s\n' "$!" > storage/app/private/p8-03-worker.pid
fi
