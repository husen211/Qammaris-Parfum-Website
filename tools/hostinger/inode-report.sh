#!/usr/bin/env bash
# Read-only inode report for the Hostinger account: where the files are. Prints paths and counts only, never contents.
#   bash inode-report.sh
set -uo pipefail
home="${HOME:-/home/u429527638}"
count() { find "$1" -xdev 2>/dev/null | wc -l; }

echo "== account total"
printf '%8s  %s\n' "$(count "$home")" "$home"
echo "== top level"
for dir in "$home"/* "$home"/.[!.]*; do [[ -e "$dir" ]] && printf '%8s  %s\n' "$(count "$dir")" "$dir"; done | sort -rn | head -20
for site in "$home"/domains/*; do
    [[ -d "$site" ]] || continue
    echo "== $site"
    for dir in "$site"/* "$site"/.[!.]*; do [[ -e "$dir" ]] && printf '%8s  %s\n' "$(count "$dir")" "$dir"; done | sort -rn | head -15
done
root="$home/domains/qammarisparfum.id"
if [[ -d "$root/releases" ]]; then
    echo "== production releases"
    echo "live: $(readlink -f "$root/current")"
    echo "release dirs: $(find "$root/releases" -mindepth 1 -maxdepth 1 -type d -name 'qammaris-*' | wc -l)"
    echo "upload archives: $(find "$root/releases" -mindepth 1 -maxdepth 1 -name '.incoming-*.tar.gz' | wc -l)  deploy temp dirs: $(find "$root/releases" -mindepth 1 -maxdepth 1 -name '.deploy-*' | wc -l)"
fi
shared="$root/shared/qammaris-website/storage"
if [[ -d "$shared" ]]; then
    echo "== production shared storage"
    for dir in "$shared"/logs "$shared"/framework/* "$shared"/app/*; do [[ -e "$dir" ]] && printf '%8s  %s\n' "$(count "$dir")" "$dir"; done | sort -rn
fi
