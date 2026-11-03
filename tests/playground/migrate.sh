#!/usr/bin/env bash
# «Migrar toda la red» de punta a punta en Chrome real: red de Playground con Elementor (plugin) activado en toda la
# red y Element Pack activado en cada sitio; se firma cada página, se pulsa el único botón de Red → Ajustes → Migrar a
# DIGITALÍSIMO Elements y se comprueba que cada sitio queda sin esos plugins, sobre el núcleo incluido, con el contenido
# de Element Pack y los alias convertidos (salvo el alias cuyo CSS del sitio lo impide) y con las páginas iguales.
# Element Pack es, por defecto, la copia oficial de Element Pack Lite 10.0.1 (ep-lite-pin.sh: sha256 comprobada contra
# reference/OFFICIAL-SOURCES.txt), sólo en este Playground efímero; EP_DIR=<carpeta> monta otra copia local. Con Lite
# (F8, P10), el sitio principal tiene además Trailer Box, Animated Card, Interactive Card y las 8 extensiones de Lite
# que la migración convierte.
# Uso: bash tests/playground/migrate.sh   → resultados en $OUT (por defecto /var/tmp/digi-f8/pg/migrate)
#   PORT            9510 (bloque de F8; lo comparten f8.sh P0 y legacy-ab.sh: no correr a la vez)
#   MIGRATE_SET     all (por defecto), base (las páginas de antes de F8) o lite (sólo las de Lite y el acordeón):
#                   una corrida completa puede pasar de 10 min y se parte en dos
#   ELEMENTS_DIR, MIGRATION_DIR   carpetas que se montan (por defecto las del repositorio; f8/p10.sh monta su copia fija)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
. "$ROOT/tests/playground/ep-lite-pin.sh"
OUT="${OUT:-/var/tmp/digi-f8/pg/migrate}"
PORT="${PORT:-9510}"
ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
MIGRATION_DIR="${MIGRATION_DIR:-$ROOT/digitalisimo-migration}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-f8/pg/tmp}"
case "${MIGRATE_SET:-all}" in all|base|lite) ;; *) echo "MIGRATE_SET debe ser all, base o lite"; exit 1;; esac
case "$PORT" in 951[0-9]) ;; *) echo "migrate.sh usa el bloque de F8 (9510–9519): $PORT es de otro arnés"; exit 1;; esac
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
mkdir -p "$OUT" "$TMPDIR" && rm -f "$OUT"/*.json "$OUT/setup-error.txt" "$OUT/fatal.log"
ep_pin "$OUT/ep.json" || exit 1
echo "${MIGRATE_SET:-all}" > "$OUT/set.txt"
cp "$ROOT/tests/playground/migrate/setup.php" "$OUT/setup.php"
# Una red no admite puertos en su dirección: el sitio es http://playground.test y Chrome lo resuelve al puerto local.
cat > "$OUT/blueprint.json" <<'JSON'
{"steps":[{"step":"enableMultisite"},
{"step":"installPlugin","pluginData":{"resource":"wordpress.org/plugins","slug":"elementor"},"options":{"activate":false}},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}]}
JSON
fuser -n tcp "$PORT" >/dev/null 2>&1 && { echo "El puerto $PORT está ocupado: no se libera (puede ser de f8.sh P0 o de legacy-ab.sh)"; exit 1; }
STARTED="$(date +%s)"
# Un solo proceso: con varios, Playground deja sin servir archivos que Elementor vuelve a generar tras vaciar su caché
# (pasa igual con Elementor solo), y la comparación de páginas mediría eso en lugar de la migración.
$CLI server --port="$PORT" --site-url="http://playground.test" --workers=1 --blueprint="$OUT/blueprint.json" \
  --mount="$ELEMENTS_DIR:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$MIGRATION_DIR:/wordpress/wp-content/plugins/digitalisimo-migration" \
  "$(ep_mount)" \
  --mount="$ROOT/tests/legacy:/wordpress/wp-content/digi-legacy-fixtures" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
# Element Pack copia sus máscaras (shape-N) y separadores (ep-*) dentro de la carpeta del Elementor que manda. Mientras
# sigue activo en un sitio y el núcleo incluido ya manda, esa carpeta es la del repositorio si se monta: se borran al
# terminar (sólo esos nombres y sólo si git no los versiona), como en legacy-ab.sh.
cleanup() {
  stop_server
  if [ "$ELEMENTS_DIR" = "$ROOT/digitalisimo-elements" ]; then
    git -C "$ROOT" clean -fq -- "digitalisimo-elements/elementor/assets/mask-shapes/shape-*.svg" "digitalisimo-elements/elementor/assets/shapes/ep-*.svg" >/dev/null 2>&1 || true
  fi
  playground_cleanup
}
trap cleanup EXIT
for i in $(seq 1 200); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then tail -20 "$OUT/server.log"; exit 1; fi
  [ -f "$OUT/pages.json" ] && curl -s -m 30 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/sub/digi-mg-accordion/" 2>/dev/null | grep -q '^200$' && break
done
[ -f "$OUT/pages.json" ] || { echo 'La red de prueba no quedó preparada'; tail -20 "$OUT/server.log"; exit 1; }
[ -f "$OUT/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$OUT/setup-error.txt"; exit 1; }
echo "Red lista en $(( $(date +%s) - STARTED )) s"
status=0
OUT="$OUT" NODE_PATH="$PUPPETEER_PATH" node "$ROOT/tests/playground/migrate/run.cjs" "$PORT" || status=$?
if [ -s "$OUT/fatal.log" ]; then echo "Errores fatales de PHP:"; cat "$OUT/fatal.log"; status=1; fi
echo "Duración: $(( $(date +%s) - STARTED )) s · resultados en $OUT"
exit $status
