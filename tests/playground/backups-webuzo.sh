#!/usr/bin/env bash
# Respaldos de cuenta de Webuzo importados y restaurados con DIGITALÍSIMO Backups en WordPress Playground (SQLite), con
# Chrome sin interfaz y por la pantalla:
#   origen   (modo fixture) http://origen.test con el prefijo `dig` y el WordPress de Playground (`wp_`) en la misma base;
#            arma, con sus propios archivos y su base, el respaldo de cuenta que haría el panel de Webuzo
#            (backups-webuzo/origin.php: dos tramos `./`, backuply-data con un mysqldump de MariaDB 11, el home).
#   destino  http://destino.test con el prefijo dst_: con Chrome se sube el .tar.gz por «Subir un respaldo» (se reconoce
#            como de Webuzo), «Importarlo ahora» (o, si trae varios WordPress, «Importar este WordPress»), queda en la lista
#            como «Importado» y se restaura desde su fila con RESTAURAR SIN FIRMA; el sitio queda con el contenido del
#            origen y la dirección del destino, la portada carga y quien restaura sigue dentro.
# Modos (MODES): fixture (el origen sintético de arriba) y real (WEBUZO_TGZ: un .tar.gz real del panel, sólo en la PC;
# SITE elige su WordPress si trae varios).
# Capturas en $OUT/shots y el resultado en $OUT/result.txt.
# Uso: bash tests/playground/backups-webuzo.sh   (en la PC, con pc-run.sh; una corrida pesada a la vez)
#   OUT, TMPDIR     resultados (por defecto /var/tmp/digi-bk/backups-webuzo) y temporales de Playground
#   MODES           modos (por defecto «fixture»; «real» con WEBUZO_TGZ)
#   PORT            primer puerto (por defecto 9681)
#   PUPPETEER_PATH  carpeta node_modules con puppeteer-core (si no, se busca en la caché de npx o se instala 25.12.0)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-webuzo"
U="$ROOT/tests/playground/backups-updraft"
R="$ROOT/tests/playground/backups-relocate"
export OUT="${OUT:-/var/tmp/digi-bk/backups-webuzo}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9681}"
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

# start_site <dir> <host> <puerto> <pasos del blueprint>
start_site() {
  local dir="$1" host="$2" port="$3" steps="$4"
  mkdir -p "$dir/probe" "$dir/store"
  chmod 700 "$dir/store"
  cp -a "$ROOT/digitalisimo-backups" "$dir/plugin"
  cp "$D"/*.php "$U/probe.php" "$U/dest.php" "$R/prefix.php" "$R/destination.php" "$dir/probe/"
  head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
  cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local","DIGITALISIMO_BACKUPS_RESTORE_MU_PLUGINS":true}},$steps]}
JSON
  if fuser -n tcp "$port" >/dev/null 2>&1; then echo "el puerto $port está ocupado; usa PORT=…"; return 1; fi
  $CLI server --port="$port" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
    --mount="$dir/plugin:/wordpress/wp-content/plugins/digitalisimo-backups" \
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
run_php() { printf '{"step":"runPHP","code":"<?php require '"'"'/digi-probe/%s'"'"';"}' "$1"; }

start=$(date +%s)
fail=0
rm -f "$OUT/result.txt"
port=$PORT
for mode in ${MODES:-fixture}; do
  dir="$OUT/$mode"
  rm -rf "$dir"; mkdir -p "$dir/archive"
  begun=$(date +%s)
  case "$mode" in
    fixture)
      # 1. Origen: arma el respaldo de cuenta con su WordPress y su base.
      mkdir -p "$dir/origin/probe"; echo "dig" > "$dir/origin/probe/prefix.txt"
      if ! start_site "$dir/origin" origen.test "$port" "$(run_php prefix.php),$(run_php origin.php)"; then stop_server; fail=1; port=$((port + 2)); continue; fi
      echo "== fixture: origen listo en $(( $(date +%s) - begun )) s (puerto $port)"
      node -e 'const r=JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")); console.log("== respaldo de cuenta:", r.archive, r.size+" B", r.files+" archivos", "tablas:", r.tables.join(","))' "$dir/origin/probe/setup.json"
      if errors "$dir/origin" | grep -q .; then echo "== fixture: errores de PHP en el origen"; errors "$dir/origin"; fail=1; fi
      stop_server; sleep 2
      cp "$dir/origin/probe/out/"*.tar.gz "$dir/archive/"
      rm -rf "$dir/origin/plugin" "$dir/origin/store"
      port=$((port + 1))
      ;;
    real)
      [ -s "${WEBUZO_TGZ:-}" ] || { echo "== real: falta WEBUZO_TGZ (un .tar.gz del panel)"; fail=1; continue; }
      ln -s "$(cd "$(dirname "$WEBUZO_TGZ")" && pwd)/$(basename "$WEBUZO_TGZ")" "$dir/archive/$(basename "$WEBUZO_TGZ")"
      ;;
    *) echo "Modo desconocido: $mode"; exit 2 ;;
  esac
  archive=$(ls "$dir/archive" | head -1)
  # 2. Destino: subir, importar y restaurar con Chrome.
  mkdir -p "$dir/dest/probe"; echo "dst_" > "$dir/dest/probe/prefix.txt"
  begun=$(date +%s)
  if ! start_site "$dir/dest" destino.test "$port" "$(run_php prefix.php),$(run_php destination.php),$(run_php dest.php)"; then stop_server; fail=1; port=$((port + 1)); continue; fi
  echo "== $mode: destino listo en $(( $(date +%s) - begun )) s (puerto $port)"
  node "$D/drive.mjs" "$mode" "$port" destino.test "$dir/dest" "$OUT" "$dir/archive/$archive" "${SITE:-}" || fail=1
  if errors "$dir/dest" | grep -q .; then echo "== $mode: errores de PHP en el destino"; errors "$dir/dest"; fail=1; fi
  cp "$dir/dest/server.log" "$OUT/server-$mode.log"
  stop_server; sleep 2
  rm -rf "$dir/dest/plugin" "$dir/dest/store" "$dir/archive"
  port=$((port + 1))
done
[ $fail -eq 0 ] || { echo 'Ensayo de Webuzo con fallos'; exit 1; }
echo "Respaldos de cuenta de Webuzo importados y restaurados en Playground con Chrome en $(( $(date +%s) - start )) s ($OUT/shots)."
