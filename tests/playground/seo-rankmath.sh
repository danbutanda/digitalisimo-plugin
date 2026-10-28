#!/usr/bin/env bash
# Lo que DIGITALÍSIMO SEO incorpora de Rank Math (docs/SEO_RANKMATH_OBJECTIVE.md, RM1–RM6) en un WordPress real, sin Chrome.
#   Red: datos sintéticos de Rank Math en el sitio 1, importación por lotes (y repetirla no duplica), redirecciones en el
#   front (exacta, regex con query, 410), vigilante de enlaces sin bucles, sitemap con imágenes propias, robots
#   avanzados, título con variables, verificación en la portada, pantallas del sitio, pantalla y guardado de red, y
#   herencia en /sub/ sin tablas ni consultas. Sitio individual: la misma importación y las mismas pantallas.
# Uso: bash tests/playground/seo-rankmath.sh   → $OUT/result.jsonl (por defecto /var/tmp/digi-seo-rankmath)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/var/tmp/digi-seo-rankmath}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
STARTED="$(date +%s)"
rm -rf "$OUT" && mkdir -p "$OUT"
cp "$ROOT/tests/playground/seo-rankmath/probe.php" "$OUT/"

run_blueprint() { # $1 archivo, $2 multisite (1/0), resto: pasos «paso:blog»
  local file="$1" multi="$2"; shift 2
  local json='{"steps":['
  [ "$multi" = 1 ] && json="$json"'{"step":"enableMultisite"},'
  local first=1 item step blog
  for item in "$@"; do
    step="${item%%:*}"; blog="${item##*:}"
    [ $first = 1 ] || json="$json,"
    first=0
    json="$json{\"step\":\"runPHP\",\"code\":\"<?php putenv('DIGI_STEP=$step'); putenv('DIGI_BLOG=$blog'); require '/wordpress/wp-content/probe/probe.php';\"}"
  done
  echo "$json]}" > "$OUT/$file"
  $CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/$file" \
    --mount="$ROOT/digitalisimo-seo:/wordpress/wp-content/plugins/digitalisimo-seo" \
    --mount="$OUT:/wordpress/wp-content/probe" ${EXTRA_MOUNT:-} > "$OUT/${file%.json}.log" 2>&1 || { tail -30 "$OUT/${file%.json}.log"; [ -s "$OUT/fatal.log" ] && cat "$OUT/fatal.log"; exit 1; }
}

run_blueprint blueprint-network.json 1 setup:1 seed:1 import:1 front:1 crumbs:1 schema:1 news:1 links:1 analysis:1 bulk:1 abilities:1 schedule:1 templates:1 linkcheck:1 feed:1 screens:1 network:1 inherit:2
# El sitio individual lleva WooCommerce 11.2.1 (copia oficial de reference/, anotada en OFFICIAL-SOURCES.txt).
WOO_DIR="${WOO_DIR:-/var/tmp/digi-woo-full/woocommerce}"
if [ ! -f "$WOO_DIR/woocommerce.php" ]; then mkdir -p "$(dirname "$WOO_DIR")"; unzip -q -o "$ROOT/reference/woocommerce.11.2.1.zip" -d "$(dirname "$WOO_DIR")"; fi
EXTRA_MOUNT="--mount=$WOO_DIR:/wordpress/wp-content/plugins/woocommerce" run_blueprint blueprint-single.json 0 single-setup:1 seed:1 import:1 front:1 crumbs:1 schema:1 news:1 links:1 analysis:1 bulk:1 abilities:1 schedule:1 templates:1 linkcheck:1 feed:1 screens:1
status=0
fails=$(grep -c '"ok":false' "$OUT/result.jsonl" || true)
total=$(wc -l < "$OUT/result.jsonl")
grep '"ok":false' "$OUT/result.jsonl" || true
if [ -s "$OUT/fatal.log" ]; then echo "Avisos o errores de PHP:"; cat "$OUT/fatal.log"; status=1; fi
[ "$fails" = 0 ] || status=1
echo "Comprobaciones: $total, fallidas: $fails · Duración: $(( $(date +%s) - STARTED )) s"
exit $status
