#!/usr/bin/env bash
# Restaurar en otro dominio y con otro prefijo de tablas (E1) en WordPress Playground (SQLite), sin Chrome, todo por HTTP:
#   origen   http://origen.test, prefijo wp_, Backups y Elements activos, una página de Elements con imagen de fondo,
#            enlace interno y una opción serializada (en una red, también en el subsitio «uno»); su CSS generado se
#            comprueba y se crea un respaldo por REST (POST /backups y POST /jobs/tick);
#   destino  http://destino.test, otro WordPress instalado con el prefijo dst_ junto al de Playground (wp_, que debe
#            quedar intacto); el respaldo del origen llega como archivo suelto de otro servidor, verify dice «Origen →
#            este sitio» y POST restore (RESTAURAR SIN FIRMA, addresses=current) lo restaura. Después, en destino.test: la
#            página y su CSS regenerado sin origen.test, ninguna tabla dst_ con origen.test (salvo guid), inicio de sesión
#            con la cuenta del respaldo y el escritorio con sus permisos.
# Modos: single y multisite (red por subdirectorios con el subsitio «uno»). Copias de los plugins y almacenes en la
# carpeta de resultados: una restauración nunca escribe en el repositorio.
# Uso: bash tests/playground/backups-relocate.sh (por el semáforo de corridas pesadas y con systemd-run --user)
#   OUT, TMPDIR   resultados (por defecto /var/tmp/digi-bk/backups-relocate) y temporales de Playground
#   MODES         modos (por defecto «single multisite»)
#   PORT          primer puerto (por defecto 9481; cada servidor usa el siguiente)
#   DEST_PREFIX   prefijo de tablas del destino (por defecto dst_; con wp_ no hay «otro WordPress» que comprobar)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-relocate"
export OUT="${OUT:-/var/tmp/digi-bk/backups-relocate}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9481}"
SERVER=''
SERVER_PORT=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$SERVER_PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$SERVER_PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''; SERVER_PORT=''
}
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM
# Arranca un Playground: start_site <dir> <host> <puerto> <pasos del blueprint>
start_site() {
  local dir="$1" host="$2" port="$3" steps="$4"
  mkdir -p "$dir/probe" "$dir/web" "$dir/store"
  chmod 700 "$dir/store"
  cp "$D"/*.php "$dir/probe/"
  cp "$D/db.php" "$dir/web/"
  head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/web/token.txt"
  cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local"}},$steps]}
JSON
  if fuser -n tcp "$port" >/dev/null 2>&1; then echo "el puerto $port está ocupado; usa PORT=…"; return 1; fi
  $CLI server --port="$port" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
    --mount="$PLUGINS/digitalisimo-backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
    --mount="$PLUGINS/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
    --mount="$dir/web:/wordpress/wp-content/digi-web" \
    --mount="$dir/probe:/digi-probe" \
    --mount="$dir/store:/digi-store" > "$dir/server.log" 2>&1 &
  SERVER=$!
  SERVER_PORT="$port"
  for i in $(seq 1 600); do
    sleep 3
    kill -0 "$SERVER" 2>/dev/null || break
    [ -f "$dir/probe/setup-error.txt" ] && break
    if [ -s "$dir/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 120 -H "Host: $host" "http://127.0.0.1:$port/?rest_route=%2F")" = 200 ]; then return 0; fi
  done
  echo "el sitio $host no quedó listo"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"
  return 1
}

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
  rm -rf "$dir"; mkdir -p "$dir"
  PLUGINS="$dir/plugins"
  mkdir -p "$PLUGINS"
  cp -a "$ROOT/digitalisimo-backups" "$ROOT/digitalisimo-elements" "$PLUGINS/"
  begun=$(date +%s)
  if ! start_site "$dir/origin" origen.test "$port" "$multi"'{"step":"runPHP","code":"<?php require '"'"'/digi-probe/origin.php'"'"';"}'; then stop_server; fail=1; port=$((port + 2)); continue; fi
  echo "== $mode: origen listo en $(( $(date +%s) - begun )) s (puerto $port)"
  node "$D/check.mjs" origin "$port" origen.test "$dir/origin" || fail=1
  grep -qE 'Fatal error|Uncaught' "$dir/origin/server.log" && { echo "== $mode: errores de PHP en el origen"; grep -E 'Fatal error|Uncaught' "$dir/origin/server.log" | head -5; fail=1; }
  stop_server; sleep 2
  file=$(node -e 'console.log(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).file||"")' "$dir/origin/origin.json" 2>/dev/null || true)
  [ -n "$file" ] && [ -f "$dir/origin/store/$file" ] || { echo "== $mode: no hay respaldo del origen"; fail=1; port=$((port + 2)); continue; }
  mkdir -p "$dir/dest/store" "$dir/dest/probe"; chmod 700 "$dir/dest/store"
  echo "${DEST_PREFIX:-dst_}" > "$dir/dest/probe/prefix.txt"
  cp "$dir/origin/store/$file" "$dir/dest/store/$file"
  port=$((port + 1))
  begun=$(date +%s)
  if ! start_site "$dir/dest" destino.test "$port" '{"step":"runPHP","code":"<?php require '"'"'/digi-probe/prefix.php'"'"';"},'"$multi"'{"step":"runPHP","code":"<?php require '"'"'/digi-probe/destination.php'"'"';"}'; then stop_server; fail=1; port=$((port + 1)); continue; fi
  echo "== $mode: destino listo en $(( $(date +%s) - begun )) s (puerto $port)"
  node "$D/check.mjs" dest "$port" destino.test "$dir/dest" "$dir/origin" origen.test || fail=1
  grep -qE 'Fatal error|Uncaught' "$dir/dest/server.log" && { echo "== $mode: errores de PHP en el destino"; grep -E 'Fatal error|Uncaught' "$dir/dest/server.log" | head -5; fail=1; }
  stop_server; sleep 2
  port=$((port + 1))
  rm -rf "$PLUGINS"
done
[ $fail -eq 0 ] || { echo 'Ensayo de restaurar en otro dominio con fallos'; exit 1; }
echo "Restaurar en otro dominio y con otro prefijo validado en Playground en $(( $(date +%s) - start )) s."
