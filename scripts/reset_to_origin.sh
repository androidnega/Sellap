#!/usr/bin/env bash
# When cPanel git says: "local changes would be overwritten by merge"
# Run from repo:  bash scripts/reset_to_origin.sh
# Or non-interactive:  bash scripts/reset_to_origin.sh --force
# One-liner (server):  cd /home3/USER/sellapp.store && git fetch origin && git reset --hard origin/master
# Tracked file edits on the server are DISCARDED. .env and other untracked files are kept.
set -euo pipefail
REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"
REMOTE="origin"
BRANCH="master"
FORCE=0
for a in "$@"; do
  if [ "$a" = "--force" ] || [ "$a" = "-f" ]; then
    FORCE=1
  fi
done
if [ "$FORCE" -ne 1 ]; then
  printf 'Repo: %s | will reset to %s/%s (loses uncommitted changes to tracked files)\n' "$REPO_ROOT" "$REMOTE" "$BRANCH"
  read -r -p 'Type YES to continue: ' ok
  if [ "$ok" != "YES" ]; then
    echo 'Aborted.'
    exit 1
  fi
fi
git fetch "$REMOTE"
git reset --hard "$REMOTE/$BRANCH"
git status
echo "OK. To avoid this: do not use File Manager to edit files that are in git; use git pull on a clean tree, or re-run this after accidents."
