#!/usr/bin/env bash
# Respaldos reales de Backuply importados y restaurados con Backups (I9) en WordPress Playground (SQLite), por HTTP:
#   origen   http://origen.test con Backuply 1.6.0 de wordpress.org (versión fija, descargada al empezar) y Backups
#            activos (en toda la red si es Multisite), con contenido que lleva las direcciones del sitio. Backuply hace
#            un respaldo completo (base de datos y archivos) con su propio código, tramo a tramo, como sus llamadas a sí
#            mismo; Backups lo detecta (GET /backuply), lo importa (POST /backuply/import y POST /jobs/tick) y verify
#            lo da por «sin firma». Playground no tiene MySQL: la copia de Backuply sólo cambia la elección del
#            controlador de base de datos (ver backups-backuply/backuply-shim.php);
#   destino  http://destino.test con el prefijo dst_ (y el WordPress de Playground con wp_ al lado): el ZIP importado
#            llega como archivo suelto, POST restore con RESTAURAR SIN FIRMA lo restaura por pasos con la reubicación de
#            E1 y, en destino.test, las entradas y sus imágenes cargan sin origen.test, ninguna tabla lo menciona (salvo
#            guid) y se inicia sesión con la cuenta del respaldo.
# Para documentar el formato deja en $OUT/<modo>/formato/ el .tar.gz de Backuply, la lista de sus entradas, el principio
# de softsql.sql y el archivo de información.
# Uso: bash tests/playground/backups-backuply.sh (por el semáforo de corridas pesadas)
#   OUT, TMPDIR   resultados (por defecto /var/tmp/digi-bk/backups-backuply) y temporales de Playground
#   MODES         modos (por defecto «single multisite»)
#   PORT          primer puerto (por defecto 9581)
#   BACKUPLY_ZIP  un backuply.1.6.0.zip ya descargado (si no, se descarga de downloads.wordpress.org)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-backuply"
R="$ROOT/tests/playground/backups-relocate"
export OUT="${OUT:-/var/tmp/digi-bk/backups-backuply}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9581}"
SERVER=''
SERVER_PORT=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$SERVER_PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$SERVER_PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''; SERVER_PORT=''
}
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM

# Backuply 1.6.0 tal como lo publica wordpress.org (archivo no confiable: sólo se monta dentro de Playground).
ZIP="${BACKUPLY_ZIP:-$OUT/backuply.1.6.0.zip}"
[ -s "$ZIP" ] || curl -fsSL -o "$ZIP" https://downloads.wordpress.org/plugin/backuply.1.6.0.zip
rm -rf "$OUT/backuply-src"; mkdir -p "$OUT/backuply-src"
unzip -q "$ZIP" -d "$OUT/backuply-src"
grep -q '^Version: 1.6.0' "$OUT/backuply-src/backuply/backuply.php" || { echo 'El ZIP de Backuply no es la versión 1.6.0'; exit 2; }
echo "== Backuply 1.6.0: sha256 $(sha256sum "$ZIP" | cut -d' ' -f1)"

start_site() {   # start_site <dir> <host> <puerto> <pasos del blueprint> [backuply]
  local dir="$1" host="$2" port="$3" steps="$4" with="${5:-}" extra=()
  mkdir -p "$dir/probe" "$dir/web" "$dir/store" "$dir/backuply"
  chmod 700 "$dir/store"
  cp "$D"/*.php "$R/prefix.php" "$R/destination.php" "$dir/probe/"
  cp "$R/db.php" "$D/backuply-run.php" "$D/backuply-state.php" "$dir/web/"
  head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/web/token.txt"
  cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local"}},$steps]}
JSON
  if [ -n "$with" ]; then
    # La copia de Backuply del ensayo: sólo su elección de controlador de base de datos cambia (ver backuply-shim.php).
    rm -rf "$dir/backuply-plugin"; cp -a "$OUT/backuply-src/backuply" "$dir/backuply-plugin"
    sed -i "s/extension_loaded('mysqli')/digi_backuply_mysqli()/g" "$dir/backuply-plugin/backup_ins.php"
    extra=(--mount="$dir/backuply-plugin:/wordpress/wp-content/plugins/backuply" --mount="$dir/backuply:/wordpress/wp-content/backuply")
  fi
  if fuser -n tcp "$port" >/dev/null 2>&1; then echo "el puerto $port está ocupado; usa PORT=…"; return 1; fi
  $CLI server --port="$port" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
    --mount="$PLUGINS/digitalisimo-backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
    "${extra[@]}" \
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
  cp -a "$ROOT/digitalisimo-backups" "$PLUGINS/"
  begun=$(date +%s)
  if ! start_site "$dir/origin" origen.test "$port" "$multi"'{"step":"runPHP","code":"<?php require '"'"'/digi-probe/origin.php'"'"';"}' backuply; then stop_server; fail=1; port=$((port + 2)); continue; fi
  echo "== $mode: origen listo en $(( $(date +%s) - begun )) s (puerto $port)"
  node "$D/check.mjs" origin "$port" origen.test "$dir/origin" || fail=1
  grep -qE 'Fatal error|Uncaught' "$dir/origin/server.log" && { echo "== $mode: errores de PHP en el origen"; grep -E 'Fatal error|Uncaught' "$dir/origin/server.log" | head -5; fail=1; }
  grep -E 'digi-backuply-shim' "$dir/origin/server.log" | head -5 || true
  stop_server; sleep 2
  # El formato real de Backuply, para la documentación.
  mkdir -p "$dir/formato"
  tgz=$(ls "$dir"/origin/backuply/backups-*/*.tar.gz 2>/dev/null | head -1 || true)
  if [ -n "$tgz" ]; then
    cp "$tgz" "$dir/formato/"
    tar -tzvf "$tgz" > "$dir/formato/entradas.txt" 2>&1 || true
    tar -xzOf "$tgz" softsql.sql 2>/dev/null | head -c 6000 > "$dir/formato/softsql-inicio.sql" || true
    tar -xzOf "$tgz" softsql.sql 2>/dev/null | grep -E '^(INSERT|CREATE|DROP|SET|/\*!|ALTER|DELIMITER)' | cut -c1-160 | head -60 > "$dir/formato/softsql-sentencias.txt" || true
    tar -xzOf "$tgz" softver.txt softperms.txt > "$dir/formato/softver-softperms.txt" 2>/dev/null || true
    cp "$dir"/origin/backuply/backups_info-*/*.php "$dir/formato/" 2>/dev/null || true
    python3 -I -c 'import sys,gzip,zlib
d=open(sys.argv[1],"rb").read(); n=0; i=0
while i<len(d):
  o=zlib.decompressobj(31); o.decompress(d[i:]); n+=1; i=len(d)-len(o.unused_data)
print("miembros gzip:",n)' "$tgz" > "$dir/formato/gzip.txt" 2>&1 || true
  fi
  file=$(node -e 'console.log(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).file||"")' "$dir/origin/origin.json" 2>/dev/null || true)
  [ -n "$file" ] && [ -f "$dir/origin/store/$file" ] || { echo "== $mode: no hay respaldo importado en el origen"; fail=1; port=$((port + 2)); continue; }
  mkdir -p "$dir/dest/store" "$dir/dest/probe"; chmod 700 "$dir/dest/store"
  echo "dst_" > "$dir/dest/probe/prefix.txt"
  cp "$dir/origin/store/$file" "$dir/dest/store/$file"
  port=$((port + 1))
  begun=$(date +%s)
  if ! start_site "$dir/dest" destino.test "$port" '{"step":"runPHP","code":"<?php require '"'"'/digi-probe/prefix.php'"'"';"},'"$multi"'{"step":"runPHP","code":"<?php require '"'"'/digi-probe/destination.php'"'"';"}'; then stop_server; fail=1; port=$((port + 1)); continue; fi
  echo "== $mode: destino listo en $(( $(date +%s) - begun )) s (puerto $port)"
  node "$D/check.mjs" dest "$port" destino.test "$dir/dest" "$dir/origin" origen.test || fail=1
  grep -qE 'Fatal error|Uncaught' "$dir/dest/server.log" && { echo "== $mode: errores de PHP en el destino"; grep -E 'Fatal error|Uncaught' "$dir/dest/server.log" | head -5; fail=1; }
  stop_server; sleep 2
  port=$((port + 1))
  rm -rf "$PLUGINS" "$dir/origin/backuply-plugin" "$dir/dest/backuply-plugin"
done
[ $fail -eq 0 ] || { echo 'Ensayo de Backuply con fallos'; exit 1; }
echo "Respaldos reales de Backuply importados y restaurados en Playground en $(( $(date +%s) - start )) s."
