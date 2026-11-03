#!/usr/bin/env bash
# A/B de adaptadores heredados: Element Pack activo frente a sólo DIGITALÍSIMO Elements.
# Element Pack es, por defecto, la copia oficial de Element Pack Lite 10.0.1 (reference/, sha256 comprobada contra
# OFFICIAL-SOURCES.txt; ep-lite-pin.sh), sólo en este Playground efímero; EP_DIR=<carpeta> monta otra copia local.
# Element Pack Pro nunca se instala. Sólo se prueban los casos de lo que la copia registra (widgets y extensiones).
#
# Red de Playground en modo servidor (un worker, PORT, servida como http://playground.test, que Node y Chrome resuelven
# al puerto). legacy-ab-run.mjs pide cada fase por HTTP (A, desactivar Element Pack, B, C, desactivar Migración, E,
# activarla, D) y, entre fases, mide en Chrome el estilo computado de las partes que traduce cada mapa (F8, P10: la
# clase de Element Pack en A y la propia en B, C y E), que legacy-compare.mjs compara.
#
# Uso: bash tests/playground/legacy-ab.sh [bdt-accordion,bdt-otro]   → resultados en $OUT
#   OUT                 /var/tmp/digi-f8/pg/legacy-ab
#   PORT                9510 (bloque de F8; lo comparte f8.sh P0: no correr a la vez)
#   BLOG=1|2            limita las fases a un sitio, en su propia petición
#   STYLES=0            sin navegador (sólo las firmas de contenido)
#   STYLE_ONLY          widgets cuyo estilo se mide (por defecto los tres mapas corregidos por F8 que Lite registra:
#                       bdt-trailer-box, bdt-animated-card y bdt-interactive-card; «all»: todo widget con `classes`)
#   CORE=bundled        sin el plugin Elementor de wordpress.org: todo sobre el núcleo incluido en Elements
#   ELEMENTS_DIR, MIGRATION_DIR   carpetas que se montan (por defecto las del repositorio; f8/p10.sh monta su copia fija)
#   EP_DIR, EXTRA_PLUGINS, FAKE_PLUGINS   como antes
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
. "$ROOT/tests/playground/ep-lite-pin.sh"
OUT="${OUT:-/var/tmp/digi-f8/pg/legacy-ab}"
PORT="${PORT:-9510}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
ONLY="${1:-${ONLY:-}}"
ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
MIGRATION_DIR="${MIGRATION_DIR:-$ROOT/digitalisimo-migration}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-f8/pg/tmp}"
mkdir -p "$OUT" "$TMPDIR"
rm -f "$OUT"/phase*.json "$OUT"/setup-error.txt "$OUT"/ready.txt "$OUT"/fatal*.txt "$OUT"/fatal.log "$OUT"/compare.txt "$OUT"/run.json
ep_pin "$OUT/ep.json" || exit 1
node "$ROOT/tests/playground/brand-audit/vendors.cjs" "$ROOT" > "$OUT/vendors.json"
head -c 18 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$OUT/token.txt"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ "${STYLES:-1}" = 0 ] || [ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH o STYLES=0"; exit 1; }
case "$PORT" in 951[0-9]) ;; *) echo "legacy-ab.sh usa el bloque de F8 (9510–9519): $PORT es de otro arnés"; exit 1;; esac
# Element Pack copia sus máscaras (shape-N) y separadores (ep-*) dentro de la carpeta de Elementor; con CORE=bundled
# y la carpeta del repositorio montada, se borran al terminar (sólo esos nombres y sólo si git no los versiona).
SERVER=''
cleanup() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done
  if [ "$ELEMENTS_DIR" = "$ROOT/digitalisimo-elements" ]; then
    git -C "$ROOT" clean -fq -- "digitalisimo-elements/elementor/assets/mask-shapes/shape-*.svg" "digitalisimo-elements/elementor/assets/shapes/ep-*.svg" >/dev/null 2>&1 || true
  fi
  playground_cleanup
}
trap cleanup EXIT
trap 'exit 130' INT TERM
# EXTRA_PLUGINS=bbpress,otro instala plugins reales de wordpress.org y los activa en la red.
ELEMENTOR_STEP='{"step":"installPlugin","pluginData":{"resource":"wordpress.org/plugins","slug":"elementor"},"options":{"activate":false}},'
[ "${CORE:-}" = "bundled" ] && ELEMENTOR_STEP=''
EXTRA_STEPS=""
EXTRA="${EXTRA_PLUGINS:-}"
for slug in ${EXTRA//,/ }; do
  EXTRA_STEPS="$EXTRA_STEPS{\"step\":\"installPlugin\",\"pluginData\":{\"resource\":\"wordpress.org/plugins\",\"slug\":\"$slug\"},\"options\":{\"activate\":false}},"
done
cat > "$OUT/blueprint.json" <<JSON
{"steps":[{"step":"enableMultisite"},
$ELEMENTOR_STEP
$EXTRA_STEPS
{"step":"runPHP","code":"<?php putenv('DIGI_EXTRA=${EXTRA_PLUGINS:-}'); require '/wordpress/wp-content/digi-tests/legacy-setup.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/digi-tests/legacy-content.php';"}]}
JSON
# FAKE_PLUGINS=1 monta plugins de prueba en las rutas que Element Pack exige para sus widgets de integración.
FAKE_MOUNTS=()
if [ "${FAKE_PLUGINS:-}" = "1" ]; then
  for dir in "$ROOT"/tests/playground/fake-plugins/*/; do
    FAKE_MOUNTS+=("--mount=${dir%/}:/wordpress/wp-content/plugins/$(basename "$dir")")
  done
fi
fuser -n tcp "$PORT" >/dev/null 2>&1 && { echo "El puerto $PORT está ocupado: no se libera (puede ser de f8.sh P0)"; exit 1; }
STARTED="$(date +%s)"
# Una red no admite puertos en su dirección: el sitio es http://playground.test y Node y Chrome lo resuelven al puerto.
$CLI server --port="$PORT" --workers=1 --site-url=http://playground.test --blueprint="$OUT/blueprint.json" "${FAKE_MOUNTS[@]}" \
  --mount="$ELEMENTS_DIR:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$MIGRATION_DIR:/wordpress/wp-content/plugins/digitalisimo-migration" \
  "$(ep_mount)" \
  --mount="$ROOT/tests/playground:/wordpress/wp-content/digi-tests" \
  --mount="$ROOT/tests/legacy:/wordpress/wp-content/digi-legacy-fixtures" \
  --mount="$OUT:/wordpress/wp-content/digi-ab" > "$OUT/playground.log" 2>&1 &
SERVER=$!
for i in $(seq 1 200); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || { tail -20 "$OUT/playground.log"; exit 1; }
  [ -f "$OUT/setup-error.txt" ] && break
  [ -f "$OUT/ready.txt" ] && curl -s -m 30 -o /dev/null -w '%{http_code}' -H 'Host: playground.test' "http://127.0.0.1:$PORT/" 2>/dev/null | grep -q '^200$' && break
done
[ -f "$OUT/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$OUT/setup-error.txt"; exit 1; }
[ -f "$OUT/ready.txt" ] || { echo 'La red de prueba no quedó preparada'; tail -20 "$OUT/playground.log"; exit 1; }
echo "Red lista en $(( $(date +%s) - STARTED )) s"
status=0
OUT="$OUT" PORT="$PORT" ONLY="$ONLY" BLOG="${BLOG:-}" STYLES="${STYLES:-1}" STYLE_ONLY="${STYLE_ONLY:-}" MIGRATION_DIR="$MIGRATION_DIR" NODE_PATH="$PUPPETEER_PATH" \
  node "$ROOT/tests/playground/legacy-ab-run.mjs" || status=$?
[ "$status" = 0 ] || { echo "Las fases no terminaron (ver $OUT/run.json)"; exit 1; }
node "$ROOT/tests/playground/legacy-compare.mjs" "$OUT/phaseA.json" "$OUT/phaseB.json" "$OUT/phaseC.json" "$OUT/phaseD.json" "$OUT/phaseE.json" | tee "$OUT/compare.txt"
cstatus="${PIPESTATUS[0]}"
[ -s "$OUT/fatal.log" ] && { echo "Errores fatales de PHP:"; cat "$OUT/fatal.log"; cstatus=1; }
echo "Duración: $(( $(date +%s) - STARTED )) s · resultados en $OUT"
exit "$cstatus"
