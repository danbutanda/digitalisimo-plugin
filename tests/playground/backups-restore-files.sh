#!/usr/bin/env bash
# Archivos y lo de después al restaurar en otra instalación (DIGITALÍSIMO Backups; brechas 5, 6, 9, 12, 19 y 22 del estudio
# de UpdraftPlus) en WordPress Playground (SQLite), por REST y con Chrome:
#   origen   http://127.0.0.1:<puerto>: Backups, un plugin con CSS y JS, un tema clásico propio, una imagen, una página
#            interna con enlaces permanentes y los drop-ins de su servidor (object-cache.php que muere sin su servidor,
#            advanced-cache.php); respaldo por REST.
#   destino  http://127.0.0.1:<puerto+1>, otro WordPress con su advanced-cache.php, una caché de páginas vieja y sin
#            .htaccess; el respaldo del origen llega como archivo suelto de otro servidor y se restaura por REST. Después:
#            drop-ins, caché, .htaccess, permisos y, en Chrome, CSS/JS/imagen en 200 aplicados en la portada y la página
#            interna; luego, sin el tema del origen, restaurar sólo la base de datos deja el tema por defecto.
# Uso: bash tests/playground/backups-restore-files.sh
#   OUT, TMPDIR   resultados (por defecto /var/tmp/digi-bk/backups-restore-files) y temporales de Playground
#   PORT          primer puerto (por defecto 9531)
#   BACKUPS_DIR   copia de Backups de los dos sitios (por defecto la del repositorio): con la de antes del arreglo, el
#                 ensayo termina con fallos a propósito
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-restore-files"
export OUT="${OUT:-/var/tmp/digi-bk/backups-restore-files}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9531}"
SRC="${BACKUPS_DIR:-$ROOT/digitalisimo-backups}"
SERVER=''
SERVER_PORT=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$SERVER_PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$SERVER_PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''; SERVER_PORT=''
}
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM
TOKEN="$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')"
# Arranca un Playground: start_site <dir> <papel> <puerto> <montajes extra>
start_site() {
  local dir="$1" role="$2" port="$3" extra="${4:-}"
  mkdir -p "$dir/probe" "$dir/web" "$dir/store"
  chmod 700 "$dir/store"
  cp -a "$SRC" "$dir/backups"
  cp "$D/setup.php" "$dir/probe/"
  cp "$D/probe.php" "$dir/web/"
  echo "$TOKEN" > "$dir/web/token.txt"
  cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local","DIGITALISIMO_BACKUPS_RESTORE_MU_PLUGINS":true}},
{"step":"runPHP","code":"<?php putenv('DIGI_ROLE=$role'); require '/digi-probe/setup.php';"}]}
JSON
  if fuser -n tcp "$port" >/dev/null 2>&1; then echo "el puerto $port está ocupado; usa PORT=…"; return 1; fi
  # shellcheck disable=SC2086
  $CLI server --port="$port" --workers=1 --site-url="http://127.0.0.1:$port" --blueprint="$dir/blueprint.json" \
    --mount="$dir/backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
    --mount="$dir/web:/wordpress/wp-content/digi-web" \
    --mount="$dir/probe:/digi-probe" \
    --mount="$dir/store:/digi-store" $extra > "$dir/server.log" 2>&1 &
  SERVER=$!
  SERVER_PORT="$port"
  for i in $(seq 1 600); do
    sleep 3
    kill -0 "$SERVER" 2>/dev/null || break
    [ -f "$dir/probe/setup-error.txt" ] && break
    if [ -s "$dir/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 120 "http://127.0.0.1:$port/?rest_route=%2F")" = 200 ]; then return 0; fi
  done
  echo "el sitio $role no quedó listo"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"
  return 1
}
start=$(date +%s)
fail=0
rm -rf "$OUT/origen" "$OUT/destino"
mkdir -p "$OUT/origen/only"
touch "$OUT/origen/only/ok"
begun=$(date +%s)
start_site "$OUT/origen" origin "$PORT" "--mount=$OUT/origen/only:/digi-origin-only" || { stop_server; exit 1; }
echo "== origen listo en $(( $(date +%s) - begun )) s (puerto $PORT)"
node "$D/check.mjs" origin "$PORT" "$OUT/origen" || fail=1
grep -qE 'Fatal error|Uncaught' "$OUT/origen/server.log" && { echo "== errores de PHP en el origen"; grep -E 'Fatal error|Uncaught' "$OUT/origen/server.log" | head -5; fail=1; }
stop_server; sleep 2
file=$(node -e 'console.log(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).file||"")' "$OUT/origen/origin.json" 2>/dev/null || true)
[ -n "$file" ] && [ -f "$OUT/origen/store/$file" ] || { echo "== no hay respaldo del origen"; exit 1; }
mkdir -p "$OUT/destino/store"; chmod 700 "$OUT/destino/store"
cp "$OUT/origen/store/$file" "$OUT/destino/store/$file"
port=$((PORT + 1))
begun=$(date +%s)
start_site "$OUT/destino" dest "$port" || { stop_server; exit 1; }
echo "== destino listo en $(( $(date +%s) - begun )) s (puerto $port)"
node "$D/check.mjs" dest "$port" "$OUT/destino" "$OUT/origen" || fail=1
grep -qE 'Fatal error|Uncaught' "$OUT/destino/server.log" && { echo "== errores de PHP en el destino"; grep -E 'Fatal error|Uncaught' "$OUT/destino/server.log" | head -5; fail=1; }
stop_server
[ $fail -eq 0 ] || { echo 'Ensayo de archivos y lo de después al restaurar con fallos'; exit 1; }
echo "Archivos y lo de después al restaurar validados en Playground en $(( $(date +%s) - start )) s."
