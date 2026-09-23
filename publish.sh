#!/usr/bin/env bash
# Mirrors this directory to the public repo Packagist reads
# (github.com/netident/netident-enduser) and tags it v<version>. The source of
# truth stays here in the platform repo; never edit the public repo directly.
# Usage: ./publish.sh 0.1.0
set -euo pipefail

VERSION="${1:?usage: publish.sh <version>}"
REMOTE="${REMOTE:-https://github.com/netident/netident-enduser.git}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

git clone -q "$REMOTE" "$WORK/repo"
cd "$WORK/repo"
git checkout -q -B main
git ls-files -z | xargs -0 rm -f
cp -R "$SCRIPT_DIR/." .
git add -A
if git diff --cached --quiet; then
  echo "nothing changed"
else
  git commit -q -m "netident/otel-enduser $VERSION"
fi
if git rev-parse -q --verify "refs/tags/v$VERSION" >/dev/null; then
  echo "tag v$VERSION already exists" >&2
  exit 1
fi
git tag -a "v$VERSION" -m "v$VERSION"
git push -q origin main "v$VERSION"
echo "pushed main + v$VERSION to $REMOTE"
