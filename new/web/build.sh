#!/usr/bin/env bash
# Builds the SPA and copies dist/ back into this directory.
#
# Why this exists: on this repo's WSL host, new/web lives on a 9p-mounted
# Windows drive. Node's fs.copyFileSync/chmod calls fail with EPERM against
# that filesystem (see docs/modernization-spec.md §3/§9 — dist/ must be
# committed since the deploy host has no Node), so `npm run build` cannot
# run directly here. This script mirrors sources to a native-fs scratch dir,
# builds there, and copies dist/ back with plain `cp` (which works fine
# against the 9p mount, unlike Node's copy syscalls).
#
# If your checkout isn't on a 9p/DrvFs mount, you don't need this — just run
# `npm install && npm run build` directly.
set -euo pipefail

WEB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NATIVE_DIR="${WMFFL_WEB_NATIVE_DIR:-$HOME/.cache/wmffl-draft-web-native}"

mkdir -p "$NATIVE_DIR"
find "$WEB_DIR" -mindepth 1 -maxdepth 1 \
  -not -name node_modules -not -name dist \
  -exec cp -r {} "$NATIVE_DIR/" \;

cd "$NATIVE_DIR"
npm install
npm run build

rm -rf "$WEB_DIR/dist"
mkdir -p "$WEB_DIR/dist"
cp -r "$NATIVE_DIR/dist/." "$WEB_DIR/dist/"

echo "Built and copied dist/ into $WEB_DIR/dist"
