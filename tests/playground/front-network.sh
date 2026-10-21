#!/usr/bin/env bash
# Ajustes «Rendimiento» de DIGITALÍSIMO Elements en una red Multisite (Playground con run-blueprint, sin Chrome; §5.5
# de la especificación F-front): sitio 1, sitio 2 en /sub/ y un sitio 3 creado después del cambio de la red.
#   N1  un valor de red apagado lo hereda el sitio 2 y lo aplica en su siguiente petición (evento en ese blog), no el 1
#   N2  el sitio 1 personaliza y conserva su valor tras cambiar la red
#   N3  el sitio creado después hereda y transita
#   N4  guardados por admin-post.php: nonce, capacidad (un editor no guarda; un administrador de sitio no guarda la red)
#       y redirección a la misma superficie (capturada en wp_redirect)
#   N5  network_fields() y la pantalla de red sin «Heredar de la red»; la del sitio con él; un sitio único sin él
#   N6  elementor/core/files/clear_cache una vez por sitio que cambia y nunca en los que no
#   N7  la pantalla de red y la del sitio sin «Elementor» ni enlaces externos (ni dominios de brand-audit/vendors.json)
# Cada paso es una petición PHP propia (front, wp-cron o admin-post) en el sitio que le toca (front-network/probe.php).
# Uso: bash tests/playground/front-network.sh   → $OUT/result.json (por defecto /tmp/digi-front-network)
#      ELEMENTS_DIR=…  mide otra copia del plugin · LIVE=1 monta el árbol sin copiar
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/tmp/digi-front-network}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
N="$ROOT/tests/playground/front-network"
STARTED="$(date +%s)"
rm -rf "$OUT" && mkdir -p "$OUT"
if [ "${LIVE:-0}" = 1 ]; then SRC="$ELEMENTS_DIR"; else SRC="$OUT/src/digitalisimo-elements"; mkdir -p "$OUT/src"; cp -a "$ELEMENTS_DIR" "$SRC"; fi
cp "$N/probe.php" "$OUT/"
cp "$ROOT/tests/playground/brand-audit/vendors.json" "$OUT/vendors.json"

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
    --mount="$SRC:/wordpress/wp-content/plugins/digitalisimo-elements" \
    --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/${file%.json}.log" 2>&1 || { tail -30 "$OUT/${file%.json}.log"; [ -s "$OUT/fatal.log" ] && cat "$OUT/fatal.log"; exit 1; }
}

STEPS=(setup:1 visit-1a:1 cron-1a:1 visit-2a:2 cron-2a:2
  post-editor:2 post-siteadmin-net:2 post-bad-nonce:2 post-site1-own:1 post-network-off:1
  visit-2b:2 cron-2b:2 visit-1b:1 cron-1b:1
  create-3:1 visit-3a:3 cron-3a:3
  screen-site:2 screen-network:1)
run_blueprint blueprint-network.json 1 "${STEPS[@]}"
run_blueprint blueprint-single.json 0 single-setup:1 single:1
status=0
OUT="$OUT" node "$N/report.cjs" || status=$?
if [ -s "$OUT/fatal.log" ]; then echo "Errores fatales:"; cat "$OUT/fatal.log"; status=1; fi
echo "Duración: $(( $(date +%s) - STARTED )) s"
exit $status
