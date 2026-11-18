#!/usr/bin/env bash
# Conjuntos reales de UpdraftPlus importados y restaurados con DIGITALÍSIMO Backups (brecha #7 del estudio de UpdraftPlus)
# en WordPress Playground (SQLite), con Chrome sin interfaz y por la pantalla:
#   origen   http://origen.test con UpdraftPlus 1.26.14 de wordpress.org (versión fija, descargada al empezar) y
#            contenido que lleva la dirección del sitio; UpdraftPlus hace un respaldo completo con su propio código
#            (boot_backup(), base de datos y archivos) y sus piezas salen de su carpeta `updraft`;
#   destino  http://destino.test con el prefijo dst_ (y el WordPress de Playground con wp_ al lado): con Chrome se suben
#            sus piezas por «Subir un respaldo», se importa el conjunto en Herramientas → «Importar de Backuply o
#            UpdraftPlus», queda en la lista como «Importado» y se restaura desde su fila con RESTAURAR SIN FIRMA; el
#            sitio queda con el contenido del origen y la dirección del destino, y quien restaura sigue dentro.
# Modos (MODES): single (individual → individual), red (una red con el subsitio «uno» → un individual: se restaura su
# sitio principal) y a-red (individual → una red: se importa como un sitio nuevo de la red).
# Capturas en $OUT/shots, el formato real de UpdraftPlus en $OUT/<modo>/formato y el resultado en $OUT/result.txt.
# Uso: bash tests/playground/backups-updraft.sh   (en la PC, con pc-run.sh; una corrida pesada a la vez)
#   OUT, TMPDIR     resultados (por defecto /var/tmp/digi-bk/backups-updraft) y temporales de Playground
#   MODES           modos (por defecto «single red a-red»)
#   PORT            primer puerto (por defecto 9661)
#   UPDRAFT_ZIP     un updraftplus.1.26.14.zip ya descargado (si no, se descarga de downloads.wordpress.org)
#   PUPPETEER_PATH  carpeta node_modules con puppeteer-core (si no, se busca en la caché de npx o se instala 25.12.0)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-updraft"
R="$ROOT/tests/playground/backups-relocate"
export OUT="${OUT:-/var/tmp/digi-bk/backups-updraft}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9661}"
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

# UpdraftPlus 1.26.14 tal como lo publica wordpress.org (archivo no confiable: sólo se monta dentro de Playground).
ZIP="${UPDRAFT_ZIP:-$OUT/updraftplus.1.26.14.zip}"
[ -s "$ZIP" ] || curl -fsSL -o "$ZIP" https://downloads.wordpress.org/plugin/updraftplus.1.26.14.zip
rm -rf "$OUT/updraft-src"; mkdir -p "$OUT/updraft-src"
unzip -q "$ZIP" -d "$OUT/updraft-src"
grep -q 'Version: 1.26.14' "$OUT/updraft-src/updraftplus/updraftplus.php" || { echo 'El ZIP de UpdraftPlus no es la versión 1.26.14'; exit 2; }
echo "== UpdraftPlus 1.26.14: sha256 $(sha256sum "$ZIP" | cut -d' ' -f1)"

# start_site <dir> <host> <puerto> <pasos del blueprint> [updraft]
start_site() {
  local dir="$1" host="$2" port="$3" steps="$4" with="${5:-}" extra=()
  mkdir -p "$dir/probe" "$dir/store" "$dir/updraft"
  chmod 700 "$dir/store"
  cp -a "$ROOT/digitalisimo-backups" "$dir/plugin"
  cp "$D"/*.php "$R/prefix.php" "$R/destination.php" "$dir/probe/"
  head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
  cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local","DIGITALISIMO_BACKUPS_RESTORE_MU_PLUGINS":true}},$steps]}
JSON
  [ -n "$with" ] && extra=(--mount="$OUT/updraft-src/updraftplus:/wordpress/wp-content/plugins/updraftplus" --mount="$dir/updraft:/wordpress/wp-content/updraft")
  if fuser -n tcp "$port" >/dev/null 2>&1; then echo "el puerto $port está ocupado; usa PORT=…"; return 1; fi
  $CLI server --port="$port" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
    --mount="$dir/plugin:/wordpress/wp-content/plugins/digitalisimo-backups" \
    "${extra[@]}" \
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
errors() { grep -E 'Fatal error|Uncaught' "$1/server.log" | head -5; }

start=$(date +%s)
fail=0
rm -f "$OUT/result.txt"
port=$PORT
for mode in ${MODES:-single red a-red}; do
  case "$mode" in
    single) origin_multi=''; dest_multi='' ;;
    red) origin_multi='{"step":"enableMultisite"},'; dest_multi='' ;;
    a-red) origin_multi=''; dest_multi='{"step":"enableMultisite"},' ;;
    *) echo "Modo desconocido: $mode"; exit 2 ;;
  esac
  dir="$OUT/$mode"
  rm -rf "$dir"; mkdir -p "$dir"
  begun=$(date +%s)
  # 1. Origen: UpdraftPlus hace su respaldo completo.
  if ! start_site "$dir/origin" origen.test "$port" "$origin_multi"'{"step":"runPHP","code":"<?php require '"'"'/digi-probe/origin.php'"'"';"}' updraft; then stop_server; fail=1; port=$((port + 2)); continue; fi
  echo "== $mode: origen listo en $(( $(date +%s) - begun )) s (puerto $port)"
  token=$(cat "$dir/origin/probe/token.txt")
  curl -s -m 1800 -H "Host: origen.test" "http://127.0.0.1:$port/?digi_ud=backup&token=$token" > "$dir/origin/backup.json" || true
  node -e 'const r=JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")); console.log("== UpdraftPlus:", r.error||"", "booted="+r.booted, r.seconds+" s", JSON.stringify(r.files||{}))' "$dir/origin/backup.json" || { echo "== $mode: la sonda del origen no respondió"; head -c 2000 "$dir/origin/backup.json"; }
  if errors "$dir/origin" | grep -q .; then echo "== $mode: errores de PHP en el origen"; errors "$dir/origin"; fail=1; fi
  stop_server; sleep 2
  # El formato real de UpdraftPlus, para la documentación (y el conjunto que se sube al destino).
  mkdir -p "$dir/formato" "$dir/set"
  set_id=$(ls "$dir/origin/updraft" | sed -n 's/^\(backup_[0-9-]*_.*_[0-9a-f]\{12\}\)-db\.gz$/\1/p' | head -1)
  if [ -z "$set_id" ]; then echo "== $mode: UpdraftPlus no dejó la base de datos de su respaldo"; ls -la "$dir/origin/updraft"; fail=1; port=$((port + 2)); continue; fi
  cp "$dir/origin/updraft/$set_id"-* "$dir/set/"
  ls -la "$dir/set" > "$dir/formato/piezas.txt"
  zcat "$dir/set/$set_id-db.gz" | head -c 4000 > "$dir/formato/db-inicio.sql" || true
  zcat "$dir/set/$set_id-db.gz" | grep -E '^(INSERT|CREATE|DROP|SET|/\*!|DELIMITER|# Table|LOCK)' | cut -c1-160 | head -60 > "$dir/formato/db-sentencias.txt" || true
  for z in "$dir/set"/*.zip; do unzip -l "$z" | head -12; done > "$dir/formato/zip-entradas.txt" 2>&1 || true
  python3 -I -c 'import sys,zlib
d=open(sys.argv[1],"rb").read(); n=0; i=0
while i<len(d):
  o=zlib.decompressobj(31); o.decompress(d[i:]); n+=1; i=len(d)-len(o.unused_data)
print("miembros gzip:",n)' "$dir/set/$set_id-db.gz" > "$dir/formato/gzip.txt" 2>&1 || true
  [ "${PHASES:-all}" = origin ] && { port=$((port + 2)); continue; }
  # 2. Destino: subir, importar y restaurar con Chrome.
  port=$((port + 1))
  mkdir -p "$dir/dest/probe"; echo "dst_" > "$dir/dest/probe/prefix.txt"
  begun=$(date +%s)
  if ! start_site "$dir/dest" destino.test "$port" '{"step":"runPHP","code":"<?php require '"'"'/digi-probe/prefix.php'"'"';"},'"$dest_multi"'{"step":"runPHP","code":"<?php require '"'"'/digi-probe/destination.php'"'"';"},{"step":"runPHP","code":"<?php require '"'"'/digi-probe/dest.php'"'"';"}'; then stop_server; fail=1; port=$((port + 1)); continue; fi
  echo "== $mode: destino listo en $(( $(date +%s) - begun )) s (puerto $port)"
  node "$D/drive.mjs" "$mode" "$port" destino.test "$dir/dest" "$OUT" "$dir/set" "$set_id" || fail=1
  if errors "$dir/dest" | grep -q .; then echo "== $mode: errores de PHP en el destino"; errors "$dir/dest"; fail=1; fi
  cp "$dir/dest/server.log" "$OUT/server-$mode.log"
  stop_server; sleep 2
  # Lo pesado no hace falta después (queda el formato en $dir/formato y las capturas).
  rm -rf "$dir/origin/plugin" "$dir/dest/plugin" "$dir/dest/store" "$dir/set" "$dir/origin/updraft" "$dir/origin/store"
  port=$((port + 1))
done
rm -rf "$OUT/updraft-src"
[ $fail -eq 0 ] || { echo 'Ensayo de UpdraftPlus con fallos'; exit 1; }
echo "Conjuntos reales de UpdraftPlus importados y restaurados en Playground con Chrome en $(( $(date +%s) - start )) s ($OUT/shots)."
