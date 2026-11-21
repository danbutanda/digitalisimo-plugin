#!/usr/bin/env bash
# Rendimiento del escritorio de WordPress (wp-admin) con los plugins DIGITALÍSIMO: sin ellos, cada uno solo, los de la
# rama (Backups, Tools y el tema) y los siete con el tema a la vez, en un sitio individual y en una red, con contenido
# realista (300 entradas, 40 páginas, 40 imágenes, 30 usuarios, comentarios), WP_DEBUG y SAVEQUERIES. La sonda
# (admin-perf-all/probe.php, mu-plugin) elige el conjunto de cada petición por cookie y anota tiempo, hitos, consultas,
# memoria pico, HTTP saliente (GitHub y api.wordpress.org con datos fijos, sin red), avisos de PHP, scripts y hojas
# encolados y, con el perfil, el tiempo de cada callback de los plugins. Chrome recorre las pantallas (mediana de
# LOADS cargas tras una de calentamiento) y anota los errores de JavaScript; además mide heartbeat, la REST del editor,
# la consulta de 30 s de Backups, los actualizadores con la caché vacía, llena y con GitHub caído, una vuelta de WP-Cron,
# y comprueba guardar una entrada, subir una imagen y actualizar un plugin. Resumen en $OUT/<etiqueta>/summary.md.
#
# Uso: bash tests/playground/admin-perf-all.sh
#   MODES="single multisite"   modos (por defecto los dos)
#   PHP=8.2                    versión de PHP de Playground (7.4 para la corrida de compatibilidad)
#   SETS=none,…,all            conjuntos (por defecto todos; ver probe.php)
#   LOADS=3                    cargas medidas por pantalla y conjunto
#   LABEL=antes|despues        etiqueta de la corrida (carpeta en $OUT)
#   UPD_SETS=all,mine          conjuntos de los escenarios de actualizadores y de WP-Cron
#   FUNCTIONAL=0               sin las acciones (guardar, subir, actualizar)
#   SRC=<carpeta>              de dónde se montan los plugins y el tema (por defecto, este árbol; p. ej. una
#                              copia de `git archive` de la versión anterior para medir «antes»)
# Puertos 9671 y 9672 (bloque 9670–9679). Se corre en la PC con pc-run.sh.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
D="$ROOT/tests/playground/admin-perf-all"
OUT="${OUT:-/tmp/digi-admin-perf-all}"
LABEL="${LABEL:-medida}"
MODES="${MODES:-single multisite}"
PHP="${PHP:-8.2}"
PORT="${PORT:-9671}"
SRC="$(cd "${SRC:-$ROOT}" && pwd)"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
if [ -z "${PUPPETEER_PATH:-}" ]; then
  PUPPETEER_PATH=$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done || true)
fi
if [ -z "${PUPPETEER_PATH:-}" ]; then
  mkdir -p "${TMPDIR:-/tmp}/pptr"
  npm install --silent --no-audit --no-fund --prefix "${TMPDIR:-/tmp}/pptr" puppeteer-core@25.12.0 >/dev/null
  PUPPETEER_PATH="${TMPDIR:-/tmp}/pptr/node_modules"
fi
export PUPPETEER_PATH
SERVER=''
stop_server() { [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; SERVER=''; }
trap 'stop_server; playground_cleanup' EXIT
fail=0
for mode in $MODES; do
  case "$mode" in
    single) multi=''; host='admin-perf.test' ;;
    multisite) multi='{"step":"enableMultisite"},'; host='admin-perf-red.test' ;;
    *) echo "Modo desconocido: $mode"; exit 2 ;;
  esac
  run="$OUT/$LABEL/$mode-php$PHP"
  rm -rf "$run"; mkdir -p "$run/probe" "$run/store"; chmod 700 "$run/store"
  cp "$D/setup.php" "$D/probe.php" "$D/commands.php" "$run/probe/"
  # Plugins y tema del árbol (en la PC, pc-run.sh trae una copia temporal: el ensayo nunca escribe en el repositorio).
  MOUNTS=()
  for dir in digitalisimo-seo digitalisimo-ia-tools digitalisimo-hosting digitalisimo-backups digitalisimo-tools digitalisimo-elements digitalisimo-migration; do
    site=$dir; [ "$dir" = digitalisimo-ia-tools ] && site=digitalisimo.chatbot
    MOUNTS+=("--mount=$SRC/$dir:/wordpress/wp-content/plugins/$site")
  done
  MOUNTS+=("--mount=$SRC/digitalisimo-theme:/wordpress/wp-content/themes/digitalisimo-theme")
  cat > "$run/blueprint.json" <<JSON
{"preferredVersions":{"php":"$PHP","wp":"latest"},"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"WP_DEBUG":true,"WP_DEBUG_LOG":"/wordpress/wp-content/probe/debug.log","WP_DEBUG_DISPLAY":false,"SAVEQUERIES":true,"DISABLE_WP_CRON":true,"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local"}},$multi
{"step":"setSiteLanguage","language":"es_MX"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}]}
JSON
  if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "el puerto $PORT está ocupado; usa PORT=…"; exit 1; fi
  begun=$(date +%s)
  $CLI server --port="$PORT" --workers=1 --php="$PHP" --site-url="http://$host" --blueprint="$run/blueprint.json" \
    "${MOUNTS[@]}" --mount="$run/probe:/wordpress/wp-content/probe" --mount="$run/store:/digi-store" > "$run/server.log" 2>&1 &
  SERVER=$!
  ready=0
  for i in $(seq 1 400); do
    sleep 3
    kill -0 "$SERVER" 2>/dev/null || break
    [ -f "$run/probe/setup-error.txt" ] && break
    if [ -s "$run/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$PORT/wp-login.php")" = 200 ]; then ready=1; break; fi
  done
  if [ "$ready" != 1 ]; then
    echo "== $mode: el sitio no quedó listo"; cat "$run/probe/setup-error.txt" 2>/dev/null || true; tail -30 "$run/server.log"
    stop_server; fail=1; continue
  fi
  grep -q "\"php\":\"$PHP" "$run/probe/setup.json" || { echo "== $mode: Playground no corre PHP $PHP: $(cat "$run/probe/setup.json")"; stop_server; fail=1; continue; }
  echo "== $mode (PHP $PHP): sitio listo en $(( $(date +%s) - begun )) s: $(cat "$run/probe/setup.json")"
  NODE_PATH="$PUPPETEER_PATH" node "$D/run.cjs" "$mode" "$PORT" "$host" "$run" || fail=1
  stop_server; sleep 2
  node "$D/report.cjs" "$run" > "$run/summary.md" || fail=1
  [ -f "$run/probe/debug.log" ] && cp "$run/probe/debug.log" "$run/debug.log" || true
  if grep -E 'PHP Fatal error|Uncaught' "$run/server.log" "$run/debug.log" 2>/dev/null | head -5 | grep -q .; then echo "== $mode: errores fatales de PHP"; grep -hE 'PHP Fatal error|Uncaught' "$run/server.log" "$run/debug.log" 2>/dev/null | head -5; fail=1; fi
  # Lo que pesa y no hace falta para el informe.
  rm -rf "$run/store"
  echo "== $mode: resumen en $run/summary.md"
done
exit $fail
