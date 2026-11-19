#!/usr/bin/env bash
# Auditoría del escritorio (wp-admin) de DIGITALÍSIMO Elements y DIGITALÍSIMO Migración: un WordPress con contenido
# realista (cientos de entradas, medios, usuarios y comentarios, páginas y plantillas del constructor), WP_DEBUG y
# SAVEQUERIES, la sonda admin-perf/probe.php como mu-plugin y, por petición, el conjunto de plugins que se mide
# (cabecera X-Digi-Ap-Set: none, el = Elements, elmig = Elements + Migración; con ANTES=1 además el-antes y
# elmig-antes, las copias del código base montadas al lado). Mide cada pantalla del escritorio y cada pantalla
# propia (mediana de REPS cargas, alternando el orden de los conjuntos), el heartbeat, la REST del editor de bloques,
# guardar una entrada, subir un medio, actualizar un plugin, los eventos de WP-Cron propios, las peticiones
# salientes (GitHub simulado: ok y caído), las opciones con carga automática, avisos de PHP y errores de JavaScript
# (Chrome), y abre y guarda el editor del constructor. Informe: $OUT/report.md y $OUT/report.json.
# Uso: bash tests/playground/admin-perf.sh
#   PHP=8.2|7.4…  versión de PHP (8.2 por defecto) · MULTI=1 red Multisite (Elements y Migración activos en la red)
#   REPS=5  cargas medidas por pantalla y conjunto (más una de calentamiento) · SETS=none,el,elmig
#   PHASES=…  fases de run.mjs (todas por defecto) · ANTES_ELEMENTS=… ANTES_MIGRATION=…  copias del código base
#   NATIVE=1  PHP nativo (php$PHP) con OPcache y SQLite servido con `php -S` (admin-perf/native.sh), sin Playground
#   OUT, PORT  carpeta de resultados y puerto
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/tmp/digi-admin-perf}"
PORT="${PORT:-9471}"
PHP="${PHP:-8.2}"
MULTI="${MULTI:-0}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
A="$ROOT/tests/playground/admin-perf"
mkdir -p "$OUT"
rm -f "$OUT"/requests.jsonl "$OUT"/ready.txt "$OUT"/setup-error.txt "$OUT"/fatal.log "$OUT"/debug.log "$OUT"/measures.json "$OUT"/chrome.json
cp "$A/probe.php" "$A/setup.php" "$OUT/"
printf '{"posts":%s,"pages":%s,"media":%s,"users":%s,"comments":%s,"builder":%s}\n' "${POSTS:-400}" "${PAGES:-60}" "${MEDIA:-120}" "${USERS:-40}" "${COMMENTS:-200}" "${BUILDER:-12}" > "$OUT/options.json"
MS=''
[ "$MULTI" = 1 ] && MS='{"step":"enableMultisite"},'
cat > "$OUT/blueprint.json" <<JSON
{"preferredVersions":{"php":"$PHP","wp":"latest"},"steps":[${MS}{"step":"setSiteLanguage","language":"es_MX"},
{"step":"defineWpConfigConsts","consts":{"WP_DEBUG":true,"WP_DEBUG_DISPLAY":false,"WP_DEBUG_LOG":"/wordpress/wp-content/probe/debug.log","SAVEQUERIES":true,"DISABLE_WP_CRON":true}},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}]}
JSON
MOUNTS=("--mount=${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}:/wordpress/wp-content/plugins/digitalisimo-elements"
  "--mount=${MIGRATION_DIR:-$ROOT/digitalisimo-migration}:/wordpress/wp-content/plugins/digitalisimo-migration")
[ -n "${ANTES_ELEMENTS:-}" ] && MOUNTS+=("--mount=$ANTES_ELEMENTS:/wordpress/wp-content/plugins/digitalisimo-elements-antes")
[ -n "${ANTES_MIGRATION:-}" ] && MOUNTS+=("--mount=$ANTES_MIGRATION:/wordpress/wp-content/plugins/digitalisimo-migration-antes")
fuser -k -n tcp "$PORT" >/dev/null 2>&1 || true
sleep 1
STARTED=$(date +%s)
# Una red Multisite no admite puertos: el sitio se llama admin-perf-red.test y el conductor resuelve ese nombre al puerto.
SITE_URL="http://127.0.0.1:$PORT"; RESOLVE=''
[ "$MULTI" = 1 ] && SITE_URL="http://admin-perf-red.test" && RESOLVE="127.0.0.1:$PORT"
if [ "${NATIVE:-0}" = 1 ]; then
  # PHP nativo con OPcache y SQLite (admin-perf/native.sh): mismo sitio, mismo conductor; sin red ni idioma.
  . "$A/native.sh"
  trap 'kill $SERVER 2>/dev/null; rm -rf "$WP"' EXIT
  for i in $(seq 1 60); do sleep 1; curl -s -m 30 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/wp-login.php" | grep -q '^200$' && break; done
else
$CLI server --port="$PORT" --php="$PHP" --workers=1 --site-url="$SITE_URL" --blueprint="$OUT/blueprint.json" \
  "${MOUNTS[@]}" --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
fi
for i in $(seq 1 400); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then tail -30 "$OUT/server.log"; exit 1; fi
  [ -f "$OUT/setup-error.txt" ] && break
  curl -s -m 30 -o /dev/null -w '%{http_code}' -H "Host: ${SITE_URL#http://}" "http://127.0.0.1:$PORT/wp-login.php" 2>/dev/null | grep -q '^200$' && [ -f "$OUT/ready.txt" ] && break
done
[ -f "$OUT/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$OUT/setup-error.txt"; exit 1; }
[ -f "$OUT/ready.txt" ] || { echo 'El sitio no terminó de prepararse'; tail -30 "$OUT/server.log"; exit 1; }
echo "Sitio listo en $(( $(date +%s) - STARTED )) s (PHP $PHP, multisitio=$MULTI, nativo=${NATIVE:-0})"
status=0
OUT="$OUT" MULTI="$MULTI" RESOLVE="${RESOLVE:-}" node "$A/run.mjs" "${SITE_URL:-http://127.0.0.1:$PORT}" || status=$?
OUT="$OUT" node "$A/report.mjs" || status=$?
[ -s "$OUT/fatal.log" ] && { echo "Errores fatales:"; cat "$OUT/fatal.log"; status=1; }
echo "Duración: $(( $(date +%s) - STARTED )) s"
exit $status
