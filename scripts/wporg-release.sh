#!/usr/bin/env bash
set -euo pipefail

WPORG_USER="${WPORG_USER:-}"
WPORG_SLUG="${WPORG_SLUG:-wb-smart-order-tracking-for-woocommerce}"
WPORG_VERSION="${WPORG_VERSION:-1.0.0}"
WPORG_WORKDIR="${WPORG_WORKDIR:-$HOME/.wporg-svn/$WPORG_SLUG}"
SVN_URL="https://plugins.svn.wordpress.org/${WPORG_SLUG}/"

if [[ -z "$WPORG_USER" ]]; then
  echo "Missing WPORG_USER. Export it first."
  exit 1
fi

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "Using:"
echo "  User:    $WPORG_USER"
echo "  Slug:    $WPORG_SLUG"
echo "  Version: $WPORG_VERSION"
echo "  SVN dir: $WPORG_WORKDIR"

mkdir -p "$WPORG_WORKDIR"

if [[ ! -d "$WPORG_WORKDIR/.svn" ]]; then
  svn checkout "$SVN_URL" "$WPORG_WORKDIR" --username "$WPORG_USER"
else
  svn update "$WPORG_WORKDIR"
fi

mkdir -p "$WPORG_WORKDIR/trunk" "$WPORG_WORKDIR/tags"

rsync -a --delete \
  --exclude '.git/' \
  --exclude 'tests/' \
  --exclude 'node_modules/' \
  --exclude '*.log' \
  --exclude '.DS_Store' \
  "$ROOT_DIR/" "$WPORG_WORKDIR/trunk/"

rm -rf "$WPORG_WORKDIR/tags/$WPORG_VERSION"
cp -R "$WPORG_WORKDIR/trunk" "$WPORG_WORKDIR/tags/$WPORG_VERSION"

pushd "$WPORG_WORKDIR" >/dev/null
svn status
svn add --force . >/dev/null 2>&1 || true
svn rm --force $(svn status | awk '/^!/ {print $2}') >/dev/null 2>&1 || true
svn commit -m "Release $WPORG_VERSION" --username "$WPORG_USER"
popd >/dev/null

echo "Release complete: $WPORG_SLUG $WPORG_VERSION"
