#!/usr/bin/env bash
# Hallazgos H9, H10 y H16 del ensayo E2 en WordPress Playground (SQLite), sin Chrome, todo por HTTP y con los límites del
# hosting del propietario en cada petición (max_execution_time=30, memory_limit=64M y el búfer de salida de PHP activo y
# sin límite, como output_buffering=On en el PHP de Webuzo):
#   origen   http://origen.test con su almacén privado registrado en /origen-store (como en otro servidor: sin la
#            constante DIGITALISIMO_BACKUPS_STORAGE_DIR) y un respaldo por REST;
#   destino  http://destino.test con su almacén en /digi-store y un respaldo de BIG_MB MiB (más que memory_limit):
#            (h16) «Descargar» desde la pantalla, con su enlace y su nonce: 200, Content-Length y SHA-256 exactos;
#            (h9)  ese respaldo va a SFTP en una petición web (php-wasm no tiene ssh2: la simula el mu-plugin del
#                  ensayo con un envoltorio de flujos hacia una carpeta montada, que recibe cada escritura entera);
#            (h10) el respaldo del origen, suelto en el almacén, se restaura por REST en el otro dominio; en otra
#                  petición, el almacén sigue siendo /digi-store, status no informa problemas del almacén y la copia
#                  previa sale disponible y restaurable.
# Copias de los plugins y almacenes en la carpeta de resultados: una restauración nunca escribe en el repositorio.
# Uso: bash tests/playground/backups-e2fix.sh (en la PC con /var/tmp/digi-wt/pc-run.sh, una corrida pesada a la vez)
#   OUT, TMPDIR   resultados (por defecto /var/tmp/digi-bk/backups-e2fix) y temporales de Playground
#   PORT          primer puerto (por defecto 9491; el destino usa el siguiente)
#   BIG_MB        tamaño del respaldo grande del destino (por defecto 100)
#   H19=1         además, Elements activo con 12 páginas en el origen (visitadas antes del respaldo) y, en el destino tras
#                 restaurar en el otro dominio, dos visitas a cada una y a /wp-json/ con 64 MB: la primera no da 500 (H19)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-e2fix"
export OUT="${OUT:-/var/tmp/digi-bk/backups-e2fix}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9491}"
BIG_MB="${BIG_MB:-100}"
SERVER=''
SERVER_PORT=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$SERVER_PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$SERVER_PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''; SERVER_PORT=''
}
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM
# Arranca un Playground: start_site <dir> <host> <puerto> <modo> <almacén> [montajes extra…]
start_site() {
  local dir="$1" host="$2" port="$3" mode="$4" store="$5"; shift 5
  mkdir -p "$dir/probe" "$dir/store"
  chmod 700 "$dir/store"
  cp "$D/setup.php" "$dir/probe/"
  echo "$mode" > "$dir/probe/mode.txt"
  head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
  if [ -n "${H19:-}" ]; then touch "$dir/probe/h19"; else rm -f "$dir/probe/h19"; fi
  cat > "$dir/blueprint.json" <<'JSON'
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"WP_ENVIRONMENT_TYPE":"local"}},{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
  if fuser -n tcp "$port" >/dev/null 2>&1; then echo "el puerto $port está ocupado; usa PORT=…"; return 1; fi
  $CLI server --port="$port" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
    --mount="$PLUGINS/digitalisimo-backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
    ${H19:+--mount="$PLUGINS/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements"} \
    --mount="$dir/probe:/digi-probe" \
    --mount="$dir/store:$store" "$@" > "$dir/server.log" 2>&1 &
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
rm -rf "$OUT/origin" "$OUT/dest" "$OUT/plugins"
PLUGINS="$OUT/plugins"
mkdir -p "$PLUGINS"
cp -a "$ROOT/digitalisimo-backups" "$PLUGINS/"
[ -n "${H19:-}" ] && cp -a "$ROOT/digitalisimo-elements" "$PLUGINS/"
begun=$(date +%s)
if ! start_site "$OUT/origin" origen.test "$PORT" origin /origen-store; then stop_server; echo 'Ensayo E2FIX con fallos: el origen no arrancó'; exit 1; fi
echo "== origen listo en $(( $(date +%s) - begun )) s (puerto $PORT)"
node "$D/check.mjs" origin "$PORT" origen.test "$OUT/origin" || fail=1
grep -qE 'Fatal error|Uncaught' "$OUT/origin/server.log" && { echo '== errores de PHP en el origen'; grep -E 'Fatal error|Uncaught' "$OUT/origin/server.log" | head -5; fail=1; }
stop_server; sleep 2
file=$(node -e 'console.log(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).file||"")' "$OUT/origin/origin.json" 2>/dev/null || true)
[ -n "$file" ] && [ -f "$OUT/origin/store/$file" ] || { echo '== no hay respaldo del origen'; exit 1; }

# Destino: el respaldo del origen llega como archivo suelto de otro servidor; el grande es de este sitio.
mkdir -p "$OUT/dest/store" "$OUT/dest/probe" "$OUT/dest/sftp/respaldos"; chmod 700 "$OUT/dest/store"
cp "$OUT/origin/store/$file" "$OUT/dest/store/$file"
big="digitalisimo-backup-grande-20261109-120000-0000000a.zip"
head -c $(( BIG_MB * 1048576 )) /dev/urandom > "$OUT/dest/store/$big"
printf '{"file":"%s","size":%d,"sha256":"%s"}\n' "$big" $(( BIG_MB * 1048576 )) "$(sha256sum "$OUT/dest/store/$big" | cut -d' ' -f1)" > "$OUT/dest/probe/big.json"
port=$((PORT + 1))
begun=$(date +%s)
if ! start_site "$OUT/dest" destino.test "$port" dest /digi-store --mount="$OUT/dest/sftp:/digi-sftp"; then stop_server; echo 'Ensayo E2FIX con fallos: el destino no arrancó'; exit 1; fi
echo "== destino listo en $(( $(date +%s) - begun )) s (puerto $port)"
node "$D/check.mjs" dest "$port" destino.test "$OUT/dest" "$OUT/origin" "$OUT/dest/sftp" || fail=1
grep -qE 'Fatal error|Uncaught' "$OUT/dest/server.log" && { echo '== errores de PHP en el destino'; grep -E 'Fatal error|Uncaught' "$OUT/dest/server.log" | head -5; fail=1; }
stop_server; sleep 2
# Lo grande no se queda en la carpeta de resultados.
rm -f "$OUT/dest/store/$big" "$OUT/dest/sftp/respaldos/$big"; rm -rf "$PLUGINS"
[ $fail -eq 0 ] || { echo 'Ensayo E2FIX con fallos'; exit 1; }
echo "Hallazgos H9, H10 y H16 validados en Playground con 30 s y 64 MB en $(( $(date +%s) - start )) s."
