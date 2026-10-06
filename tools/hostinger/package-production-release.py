"""Package tracked application code and built dependencies; never runtime data."""
import os
from pathlib import Path
import re
import subprocess
import tarfile

revision = os.environ.get("RELEASE_REVISION", "")
if not re.fullmatch(r"[a-f0-9]{40}", revision):
    raise SystemExit("Invalid release revision")
if subprocess.check_output(["git", "rev-parse", "HEAD"], text=True).strip() != revision:
    raise SystemExit("Revision does not match checkout")
tracked = subprocess.check_output(["git", "ls-files", "-z"]).decode().split("\0")
prefixes = ("app/", "bootstrap/", "config/", "database/migrations/", "resources/", "routes/", "public/", "tools/hostinger/")
roots = {"artisan", "composer.json", "composer.lock"}
files = {Path(name) for name in tracked if name and (name in roots or name.startswith(prefixes))
         and not name.startswith(("bootstrap/cache/", "public/storage/", "public/build/"))}
for required in [Path("vendor/autoload.php"), Path("public/build/manifest.json")]:
    if not required.is_file():
        raise SystemExit("Missing runtime dependencies or built assets")
for root in [Path("vendor"), Path("public/build")]:
    files.update(path for path in root.rglob("*") if path.is_file())
if any(path.is_symlink() or ".env" in path.parts or ".git" in path.parts for path in files):
    raise SystemExit("Unexpected link or secret file in release")
Path("release-revision.txt").write_text(revision + "\n")
files.add(Path("release-revision.txt"))
with tarfile.open("production-release.tar.gz", "w:gz") as archive:
    for path in sorted(files):
        archive.add(path, arcname=path.as_posix(), recursive=False)
print(f"Release packaged: {len(files)} files; no env, media, sessions, database or accounts")
