#!/usr/bin/env bash
# Packages this composer package as a zip for distribution via a composer
# "artifact" repository (see README.md). The zip is served from
# web/public/sdk/ and downloaded by a customer's build once, into a local
# "artifact" repo directory — composer never talks to us at install time.
set -euo pipefail

PKG_VERSION="0.1.1"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUT_DIR="${OUT_DIR:-$SCRIPT_DIR/../../web/public/sdk}"
ZIP_NAME="netident-otel-enduser-${PKG_VERSION}.zip"

mkdir -p "$OUT_DIR"

STAGE_DIR="$(mktemp -d)"
trap 'rm -rf "$STAGE_DIR"' EXIT

cp "$SCRIPT_DIR/composer.json" "$STAGE_DIR/"
# A composer "artifact" repository needs "version" inside composer.json;
# the source keeps none, because Packagist takes it from the git tag.
php -r '$f=$argv[1]; $j=json_decode(file_get_contents($f), true); $j=array_merge(array_slice($j,0,1),["version"=>$argv[2]],array_slice($j,1)); file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");' \
  "$STAGE_DIR/composer.json" "$PKG_VERSION" >/dev/null
cp "$SCRIPT_DIR/README.md" "$STAGE_DIR/"
cp -R "$SCRIPT_DIR/src" "$STAGE_DIR/src"

rm -f "$OUT_DIR/$ZIP_NAME"

(
  cd "$STAGE_DIR"
  zip -r -X -q "$OUT_DIR/$ZIP_NAME" composer.json README.md src
)

echo "Built $OUT_DIR/$ZIP_NAME"
