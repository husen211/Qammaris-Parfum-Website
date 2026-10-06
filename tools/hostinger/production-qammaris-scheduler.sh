#!/usr/bin/env bash
set -eu
umask 077
root=/home/u429527638/domains/qammarisparfum.id
private="$root/shared/qammaris-website/storage/app/private"
cd "$root/current"
date -u +%Y-%m-%dT%H:%M:%SZ > "$private/scheduler-last-run"
flock -n "$private/qammaris-scheduler.lock" php artisan schedule:run --no-interaction >> storage/logs/qammaris-scheduler.log 2>&1
