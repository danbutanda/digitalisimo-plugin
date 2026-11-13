#!/usr/bin/env bash
# «Restaurar por partes» de DIGITALÍSIMO Backups en WordPress Playground (SQLite), sin Chrome ni WP-CLI, con los límites
# de la pila del propietario (max_execution_time=30, memory_limit=64M; el laboratorio de backups-restore.sh): el respaldo
# completo se crea desde la pantalla y cada restauración por partes se pide por REST y avanza sólo con ticks web. Con
# huellas de cada tabla y de cada archivo, antes y después (ver backups-restore-parts/drive.mjs):
#   single     sólo la base de datos, sólo el plugin «demo», sólo los medios, sólo los temas y los plugins con una falla a
#              mitad del cambio que vuelve sola a la copia previa;
#   multisite  red por subdirectorios: sólo los medios del subsitio y sólo la base de datos de la red;
#   relocate   otro sitio (otra dirección) restaura sólo la base de datos del respaldo de «single» (reubicación de E1).
# Uso: bash tests/playground/backups-restore-parts.sh (por el semáforo de corridas pesadas y con systemd-run --user)
#   OUT, TMPDIR   resultados (por defecto /var/tmp/digi-bk/backups-restore-parts) y temporales de Playground
#   MODES         modos (por defecto «single multisite relocate»; relocate necesita el respaldo de single)
#   PORT          primer puerto (por defecto 9531)
#   BACKUP_MEMORY límite de las peticiones que crean el respaldo de referencia (por defecto 128M; 64M = el del propietario)
#   LAB_MEMORY    límite de todas las peticiones, también las de las restauraciones (sin él, 64 MB): en php-wasm la
#                 lectura de 1 MiB por archivo del empaquetado y de la verificación va al límite con 64 MB también con
#                 el código de main (docs/BACKUPS.md, «Restaurar por partes» → «Memoria»)
#   ONLY_BACKUP   sólo crea el respaldo de referencia y anota la memoria de cada petición (diagnóstico)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-restore-parts"
R="$ROOT/tests/playground/backups-restore"
export OUT="${OUT:-/var/tmp/digi-bk/backups-restore-parts}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9531}"
# Contenido más pequeño que el de backups-restore.sh: aquí cuenta qué se toca, no el tamaño.
export DIGI_RESTORE_POSTS="${DIGI_RESTORE_POSTS:-150}" DIGI_RESTORE_FILES="${DIGI_RESTORE_FILES:-120}"
SERVER=''
SERVER_PORT=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$SERVER_PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$SERVER_PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''; SERVER_PORT=''
}
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM
# start_site <dir> <host> <puerto> <multi> <sitio a|b>
start_site() {
  local dir="$1" host="$2" port="$3" multi="$4" site="$5"
  rm -rf "$dir"; mkdir -p "$dir/probe" "$dir/store" "$dir/ctl"
  chmod 700 "$dir/store"
  cp -a "$ROOT/digitalisimo-backups" "$dir/plugin"
  cp "$R/setup.php" "$R/lab.php" "$dir/probe/"
  cp "$D/setup.php" "$dir/probe/parts-setup.php"
  echo "$site" > "$dir/probe/site.txt"
  head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
  cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_MEMORY_LIMIT":"64M","WP_MAX_MEMORY_LIMIT":"64M","WP_ENVIRONMENT_TYPE":"local","DIGITALISIMO_BACKUPS_RESTORE_MU_PLUGINS":true}},$multi
{"step":"runPHP","code":"<?php putenv('DIGI_RESTORE_POSTS=$DIGI_RESTORE_POSTS'); putenv('DIGI_RESTORE_FILES=$DIGI_RESTORE_FILES'); require '/digi-probe/setup.php';"},
{"step":"runPHP","code":"<?php require '/digi-probe/parts-setup.php';"}]}
JSON
  if fuser -n tcp "$port" >/dev/null 2>&1; then echo "el puerto $port está ocupado; usa PORT=…"; return 1; fi
  $CLI server --port="$port" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
    --mount="$dir/plugin:/wordpress/wp-content/plugins/digitalisimo-backups" \
    --mount="$dir/probe:/digi-probe" \
    --mount="$dir/ctl:/digi-ctl" \
    --mount="$dir/store:/digi-store" > "$dir/server.log" 2>&1 &
  SERVER=$!
  SERVER_PORT="$port"
  for i in $(seq 1 400); do
    sleep 3
    kill -0 "$SERVER" 2>/dev/null || break
    [ -f "$dir/probe/setup-error.txt" ] || [ -f "$dir/probe/parts-error.txt" ] && break
    if [ -s "$dir/probe/parts.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$port/?rest_route=%2F")" = 200 ]; then return 0; fi
  done
  echo "el sitio $host no quedó listo"; cat "$dir/probe/setup-error.txt" "$dir/probe/parts-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"
  return 1
}
errors() { grep -E 'Fatal error|Uncaught' "$1/server.log" | grep -v 'Allowed memory size' | head -5; }

start=$(date +%s)
fail=0
port=$PORT
for mode in ${MODES:-single multisite relocate}; do
  case "$mode" in
    single|relocate) multi='' ;;
    multisite) multi='{"step":"enableMultisite"},' ;;
    *) echo "Modo desconocido: $mode"; exit 2 ;;
  esac
  dir="$OUT/$mode"
  host="parts-$mode.test"
  begun=$(date +%s)
  if [ "$mode" = relocate ]; then
    ref="$OUT/single/reference.json"
    [ -s "$ref" ] || { echo "== relocate: falta el respaldo de single ($ref)"; fail=1; continue; }
    file=$(node -e 'console.log(require(process.argv[1]).file)' "$ref")
    if ! start_site "$dir" "$host" "$port" "$multi" b; then stop_server; fail=1; port=$((port + 1)); continue; fi
    cp "$OUT/single/store/$file" "$dir/store/$file"
    cp "$ref" "$dir/reference.json"
  else
    if ! start_site "$dir" "$host" "$port" "$multi" a; then stop_server; fail=1; port=$((port + 1)); continue; fi
    file=''
  fi
  echo "== $mode: sitio listo en $(( $(date +%s) - begun )) s (puerto $port)"
  node "$D/drive.mjs" "$port" "$host" "$dir" "$mode" $file || fail=1
  if errors "$dir" | grep -q .; then echo "== $mode: errores de PHP en el servidor"; errors "$dir"; fail=1; fi
  stop_server; sleep 2
  rm -rf "$dir/plugin"
  port=$((port + 1))
done
[ $fail -eq 0 ] || { echo 'Ensayo de restaurar por partes con fallos'; exit 1; }
echo "Restaurar por partes validado en Playground en $(( $(date +%s) - start )) s."
