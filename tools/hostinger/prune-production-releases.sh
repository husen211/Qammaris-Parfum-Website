#!/usr/bin/env bash
# Keep the live release, the release before it (deploy rollback) and one more; remove older release directories,
# leftover upload archives and deploy temp dirs. Releases are code only: database, media, .env and storage live in
# shared/ and are never touched. Default is a dry run; pass --apply to delete.
#
#   bash prune-production-releases.sh            # show what would be removed and the inodes it frees
#   bash prune-production-releases.sh --apply    # remove it
set -euo pipefail
root="${QAMMARIS_ROOT:-/home/u429527638/domains/qammarisparfum.id}"
keep_extra="${QAMMARIS_KEEP_EXTRA:-1}"
# The deploy passes the release it switched away from so a newer failed release cannot take its place.
keep_release="${QAMMARIS_KEEP_RELEASE:-}"
apply=false
[[ "${1:-}" == "--apply" ]] && apply=true

releases_dir="$root/releases"
test -d "$releases_dir"
current="$(readlink -f "$root/current")"
[[ "$current" == "$releases_dir/qammaris-"* ]] || { echo "current does not point at a release; nothing removed" >&2; exit 1; }

# Newest first. Only exact release names are considered.
mapfile -t releases < <(find "$releases_dir" -mindepth 1 -maxdepth 1 -type d -regextype posix-extended -regex '.*/qammaris-[a-f0-9]{40}' -printf '%T@ %p\n' | sort -rn | cut -d' ' -f2-)

remove=()
kept_extra=0
seen_previous=false
for dir in "${releases[@]}"; do
    if [[ "$dir" == "$current" || "$dir" == "$keep_release" ]]; then
        continue
    fi
    # Without an explicit rollback target, the newest other release is treated as one.
    if [[ "$seen_previous" == false && -z "$keep_release" ]]; then
        seen_previous=true
        continue
    fi
    if (( kept_extra < keep_extra )); then
        kept_extra=$((kept_extra + 1))
        continue
    fi
    remove+=("$dir")
done
# Upload archives and deploy temp dirs are only needed during a deploy.
while IFS= read -r leftover; do
    remove+=("$leftover")
done < <(find "$releases_dir" -mindepth 1 -maxdepth 1 \( -name '.incoming-*.tar.gz' -o -name '.deploy-*' \) -print)

total=0
for target in "${remove[@]}"; do
    count="$(find "$target" | wc -l)"
    total=$((total + count))
    printf '%8s inodes  %s\n' "$count" "$target"
done
echo "live: $current"
echo "to remove: ${#remove[@]} entries, ${total} inodes"
if [[ "$apply" == true && ${#remove[@]} -gt 0 ]]; then
    for target in "${remove[@]}"; do
        [[ "$target" == "$releases_dir/"* && "$target" != "$current" && "$target" != "$keep_release" ]] || { echo "refusing $target" >&2; exit 1; }
        rm -rf -- "$target"
    done
    echo "removed."
elif [[ "$apply" == false ]]; then
    echo "dry run: nothing removed (use --apply)."
fi
