#!/usr/bin/env bash
# Restauración robusta de DIGITALÍSIMO Backups (H1–H4) en WordPress Playground (SQLite), sin Chrome ni WP-CLI: un sitio
# (y una red con un subsitio) con unos 1 500 medios, 1 200 entradas con metadatos, un plugin «demo» y un archivo del
# tema se respalda desde la pantalla, se cambia (base de datos, un .php inyectado en uploads, un plugin nuevo, un archivo
# del tema borrado, otro fuera de las raíces gestionadas) y se restaura por REST con los límites de la pila del
# propietario (max_execution_time=30 y memory_limit=64M, sin elevar), sólo con ticks web (AJAX con el testigo de la
# restauración y wp-cron.php), matando una petición a mitad de la base de datos y otra a mitad de los archivos. El
# inventario (tablas y archivos) debe quedar idéntico al de después del respaldo; en el sitio individual, además, una
# restauración que falla a mitad del cambio vuelve sola a la copia previa (inventario idéntico al de antes de restaurar).
# Uso: bash tests/playground/backups-restore.sh (por el semáforo de corridas pesadas y con systemd-run --user)
#   OUT, TMPDIR   resultados (por defecto /var/tmp/digi-bk/backups-restore) y temporales de Playground
#   MODES         modos (por defecto «single multisite»)
#   PORT          primer puerto (por defecto 9471)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-restore"
export OUT="${OUT:-/var/tmp/digi-bk/backups-restore}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9471}"
SERVER=''
SERVER_PORT=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$SERVER_PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$SERVER_PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''; SERVER_PORT=''
}
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM
start=$(date +%s)
fail=0
port=$PORT
for mode in ${MODES:-single multisite}; do
  case "$mode" in
    single) multi='' ;;
    multisite) multi='{"step":"enableMultisite"},' ;;
    *) echo "Modo desconocido: $mode"; exit 2 ;;
  esac
  dir="$OUT/$mode"
  rm -rf "$dir"; mkdir -p "$dir/probe" "$dir/store" "$dir/ctl"
  chmod 700 "$dir/store"
  cp -a "$ROOT/digitalisimo-backups" "$dir/plugin"
  cp "$D/setup.php" "$D/lab.php" "$dir/probe/"
  head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
  # Un nombre sin puerto (una red de WordPress no admite puertos); las peticiones van a 127.0.0.1 con este Host.
  host="restore-$mode.test"
  cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_MEMORY_LIMIT":"64M","WP_MAX_MEMORY_LIMIT":"64M","WP_ENVIRONMENT_TYPE":"local"}},$multi
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
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
    [ -f "$dir/probe/setup-error.txt" ] && break
    if [ -s "$dir/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$port/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
  done
  if [ "$ready" != 1 ]; then
    echo "== $mode: el sitio no quedó listo (servidor vivo: $(kill -0 "$SERVER" 2>/dev/null && echo sí || echo no))"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"; stop_server; fail=1; port=$((port + 1)); continue
  fi
  echo "== $mode: sitio listo en $(( $(date +%s) - begun )) s: $(cat "$dir/probe/setup.json")"
  node "$D/drive.mjs" "$port" "$host" "$dir" "$mode" || fail=1
  grep -E 'Fatal error|Uncaught' "$dir/server.log" | grep -v 'Allowed memory size' | head -5 && { echo "== $mode: errores de PHP en el servidor"; fail=1; } || true
  stop_server; sleep 2
  rm -rf "$dir/plugin" "$dir/store"/*.zip
  port=$((port + 1))
done
[ $fail -eq 0 ] || { echo 'Ensayo de la restauración robusta con fallos'; exit 1; }
echo "Restauración robusta validada en Playground en $(( $(date +%s) - start )) s."
