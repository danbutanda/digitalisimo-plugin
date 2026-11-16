#!/usr/bin/env bash
# El panel de avance de DIGITALÍSIMO Backups en WordPress Playground (SQLite) con Chrome sin interfaz, a 1 280 y 390 px,
# con los límites de la pila del propietario (max_execution_time=30, memory_limit=64M y sin loopback de WP-Cron: la cola
# sólo avanza con los ticks de la pantalla; el laboratorio de backups-restore.sh). Todo desde la pantalla, como un usuario
# (ver backups-restore-progress/drive.mjs): un respaldo con «Respaldar ahora»; una restauración completa desde su fila
# (recargando la página a mitad, con la sesión que el cambio de tablas deja sin valor y entrando otra vez al final); una
# por partes (sólo plugins) a 390 px; y otra con una falla inyectada a mitad del cambio que vuelve sola a la copia previa.
# En cada una, el panel grande aparece, avanza por sus fases y enseña el resultado; capturas en $OUT/shots.
# Uso: bash tests/playground/backups-restore-progress.sh   (en la PC, con pc-run.sh; una corrida pesada a la vez)
#   OUT, TMPDIR     resultados (por defecto /var/tmp/digi-bk/backups-restore-progress) y temporales de Playground
#   MODES           modos (por defecto «single»; «multisite» repite el respaldo y la restauración por partes en la red)
#   PORT            primer puerto (por defecto 9561)
#   TICK_BUDGET     segundos de trabajo de cada tick (por defecto 5; en el hosting, 15): en un sitio pequeño, con 15 s un
#                   respaldo entero cabe en un tick y el panel no tendría avance intermedio que enseñar
#   RESTORE_MEMORY  límite de las peticiones mientras dura cada restauración (por defecto 128M): en php-wasm, con 64M,
#                   la lectura de 1 MiB de la verificación y de la copia previa agota la memoria con 1.0.77 (también main;
#                   MODES=start lo mide con 64M). La primera petición de la primera restauración va siempre con 64M
#   RESPOND_AFTER   segundos que trabaja la petición que empieza una restauración antes de volver a la pantalla (por
#                   defecto 3; en el hosting, 25): en un sitio pequeño, con 25 s la restauración entera cabe en ella
#   BACKUP_MEMORY   límite de las peticiones de «Respaldar ahora» (por defecto 128M, como el respaldo de referencia de
#                   backups-restore-parts.sh: en php-wasm el empaquetado va al límite de 64 MB también con main; las
#                   restauraciones, su copia previa incluida, corren siempre con 64 MB)
#   PUPPETEER_PATH  carpeta node_modules con puppeteer-core (si no, se busca en la caché de npx o se instala 25.12.0)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-restore-progress"
R="$ROOT/tests/playground/backups-restore"
Q="$ROOT/tests/playground/backups-restore-parts"
export OUT="${OUT:-/var/tmp/digi-bk/backups-restore-progress}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9561}"
# Contenido pequeño: aquí cuenta lo que ve el usuario, no el tamaño.
export DIGI_RESTORE_POSTS="${DIGI_RESTORE_POSTS:-120}" DIGI_RESTORE_FILES="${DIGI_RESTORE_FILES:-80}"
SERVER=''
SERVER_PORT=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$SERVER_PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$SERVER_PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''; SERVER_PORT=''
}
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM

if [ -z "${PUPPETEER_PATH:-}" ]; then
  PUPPETEER_PATH=$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done || true)
fi
if [ -z "${PUPPETEER_PATH:-}" ]; then
  mkdir -p "$TMPDIR/pptr"
  npm install --silent --no-audit --no-fund --prefix "$TMPDIR/pptr" puppeteer-core@25.12.0 >/dev/null
  PUPPETEER_PATH="$TMPDIR/pptr/node_modules"
fi
export PUPPETEER_PATH

start=$(date +%s)
fail=0
port=$PORT
for mode in ${MODES:-single}; do
  case "$mode" in
    single|start) multi='' ;;
    multisite) multi='{"step":"enableMultisite"},' ;;
    *) echo "Modo desconocido: $mode"; exit 2 ;;
  esac
  dir="$OUT/$mode"
  host="progreso-$mode.test"
  rm -rf "$dir"; mkdir -p "$dir/probe" "$dir/store" "$dir/ctl" "$dir/shots"
  chmod 700 "$dir/store"
  cp -a "$ROOT/digitalisimo-backups" "$dir/plugin"
  cp "$R/setup.php" "$R/lab.php" "$dir/probe/"
  # El de «restaurar por partes»: sin los archivos de 1 MiB del laboratorio y con el límite de memoria del respaldo.
  cp "$Q/setup.php" "$dir/probe/parts-setup.php"
  cp "$D/lab.php" "$dir/probe/progress-lab.php"
  echo a > "$dir/probe/site.txt"
  echo "${TICK_BUDGET:-5}" > "$dir/ctl/tick-budget"
  echo "${RESPOND_AFTER:-3}" > "$dir/ctl/respond-after"
  head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
  cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_MEMORY_LIMIT":"64M","WP_MAX_MEMORY_LIMIT":"64M","WP_ENVIRONMENT_TYPE":"local","DIGITALISIMO_BACKUPS_RESTORE_MU_PLUGINS":true,"WP_DISABLE_FATAL_ERROR_HANDLER":true}},$multi
{"step":"runPHP","code":"<?php putenv('DIGI_RESTORE_POSTS=$DIGI_RESTORE_POSTS'); putenv('DIGI_RESTORE_FILES=$DIGI_RESTORE_FILES'); require '/digi-probe/setup.php';"},
{"step":"runPHP","code":"<?php require '/digi-probe/parts-setup.php';"},
{"step":"runPHP","code":"<?php copy( '/digi-probe/progress-lab.php', '/wordpress/wp-content/mu-plugins/digi-progress-lab.php' );"}]}
JSON
  if fuser -n tcp "$port" >/dev/null 2>&1; then echo "el puerto $port está ocupado; usa PORT=…"; exit 1; fi
  begun=$(date +%s)
  $CLI server --port="$port" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
    --mount="$dir/plugin:/wordpress/wp-content/plugins/digitalisimo-backups" \
    --mount="$dir/probe:/digi-probe" \
    --mount="$dir/ctl:/digi-ctl" \
    --mount="$dir/store:/digi-store" > "$dir/server.log" 2>&1 &
  SERVER=$!
  SERVER_PORT="$port"
  ready=0
  for i in $(seq 1 400); do
    sleep 3
    kill -0 "$SERVER" 2>/dev/null || break
    [ -f "$dir/probe/setup-error.txt" ] || [ -f "$dir/probe/parts-error.txt" ] && break
    if [ -s "$dir/probe/parts.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$port/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
  done
  if [ "$ready" != 1 ]; then
    echo "== $mode: el sitio no quedó listo"; cat "$dir/probe/setup-error.txt" "$dir/probe/parts-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"
    stop_server; fail=1; port=$((port + 1)); continue
  fi
  echo "== $mode: sitio listo en $(( $(date +%s) - begun )) s (puerto $port)"
  node "$D/drive.mjs" "$port" "$host" "$dir" "$mode" || fail=1
  if grep -E 'Fatal error|Uncaught' "$dir/server.log" | grep -v 'Allowed memory size' | head -5 | grep -q .; then echo "== $mode: errores de PHP en el servidor"; grep -E 'Fatal error|Uncaught' "$dir/server.log" | head -5; fail=1; fi
  # Memoria y duración de cada petición que hizo avanzar la cola (el laboratorio las anota).
  [ -s "$dir/ctl/requests.jsonl" ] && node -e 'const l=require("fs").readFileSync(process.argv[1],"utf8").split("\n").filter(Boolean).map(JSON.parse);const by={};for(const r of l){const k=r.kind;by[k]=by[k]||{n:0,peak:0,secs:0};by[k].n++;by[k].peak=Math.max(by[k].peak,r.peak_mb);by[k].secs=Math.max(by[k].secs,r.seconds);}console.log("== '"$mode"': peticiones "+JSON.stringify(by));' "$dir/ctl/requests.jsonl" || true
  stop_server; sleep 2
  rm -rf "$dir/plugin"
  port=$((port + 1))
done
[ $fail -eq 0 ] || { echo 'Ensayo del panel de avance con fallos'; exit 1; }
echo "Panel de avance validado en Playground con Chrome en $(( $(date +%s) - start )) s ($OUT/*/shots)."
