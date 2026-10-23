#!/usr/bin/env bash
# Recurring deploy only. First public activation remains a separate Owner-approved step.
set -euo pipefail
umask 077
archive="$1"
revision="$2"
[[ "$revision" =~ ^[a-f0-9]{40}$ ]]
root=/home/u429527638/domains/qammarisparfum.id
shared="$root/shared/qammaris-website"
release="$root/releases/qammaris-$revision"
# OPS-DEPLOY-01: a deploy that stops (or rolls back) removes what it created, so the same revision can be re-run after
# the Owner fixes the cause: its own candidate release (never the one `current` points to) and the workflow's
# releases/.deploy-<sha> folder this script is extracted into.
created_release=0
cleanup() {
    if [ "$created_release" = 1 ] && [ "$(readlink -f "$root/current")" != "$release" ]; then
        cd "$root" && rm -rf -- "$release"
    fi
    rm -rf -- "$root/releases/.deploy-$revision"
}
trap cleanup EXIT
test -f "$shared/.production-active"
test -f "$shared/.env"
test -L "$root/current"
exec 9>"$shared/deploy.lock"
flock -n 9
if [ -e "$release" ]; then
    printf '%s\n' "Deployment refused: $release already exists. If it is not current, remove it and re-run." >&2
    exit 1
fi
# Packages are produced by the private GitHub workflow, not arbitrary operator uploads.
php -r '$a=new PharData($argv[1]);foreach(new RecursiveIteratorIterator($a) as $f){$p=$f->getPathName();if(str_contains($p,"/../")||str_contains($p,"/.env")||$f->isLink()){exit(1);}}' "$archive"
mkdir -m 0711 "$release"
created_release=1
tar -xzf "$archive" -C "$release" --no-same-owner --same-permissions
test "$(cat "$release/release-revision.txt")" = "$revision"
ln -s "$shared/.env" "$release/.env"
ln -s "$shared/storage" "$release/storage"
mkdir -p "$release/bootstrap/cache"
ln -s "$shared/storage/app/public" "$release/public/storage"
cd "$release"
php artisan package:discover --no-interaction
# Future schema changes require a separately reviewed migration runbook; do not migrate blindly.
php artisan migrate:status --no-interaction > "$shared/migration-status.candidate"
if grep -q 'Pending' "$shared/migration-status.candidate"; then
    printf '%s\n' 'Deployment refused: pending migrations require Owner-reviewed execution.' >&2
    exit 1
fi
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
test -f public/build/manifest.json
previous="$(readlink -f "$root/current")"
[[ "$previous" == "$root/releases/qammaris-"* ]]
ln -s "$release" "$root/.current-$revision"
mv -Tf "$root/.current-$revision" "$root/current"
if ! curl --fail --silent --show-error --max-time 30 https://qammarisparfum.id/up > /dev/null; then
    ln -s "$previous" "$root/.rollback-$revision"
    mv -Tf "$root/.rollback-$revision" "$root/current"
    printf '%s\n' 'Health check failed; previous code restored. Database/media retained.' >&2
    exit 1
fi
php artisan queue:restart --no-interaction
printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$revision" >> "$shared/deployments.log"

# Each release is ~13k files; unpruned releases filled the account inode quota (OPS-INODE-2026-10).
QAMMARIS_KEEP_RELEASE="$previous" bash "$release/tools/hostinger/prune-production-releases.sh" --apply \
    || printf '%s\n' 'Warning: old releases were not pruned; run tools/hostinger/prune-production-releases.sh.' >&2
