#!/usr/bin/env bash
# T2 de DIGITALÍSIMO Backups en WordPress Playground (SQLite), sin Chrome ni WP-CLI: un sitio de unos 200 MB (medios
# generados aquí: JPEG y MP4 aleatorios, que se guardan sin comprimir, textos comprimibles y miles de archivos pequeños
# en muchas carpetas, más unas 3 000 entradas con metadatos) se respalda con los límites de la pila del propietario
# (max_execution_time=30 y memory_limit=64M, también para admin y WP-Cron) y sólo con los ticks del navegador y las
# llamadas a wp-cron.php (como un cron del sistema por HTTP). El ensayo mata tres peticiones a mitad de un tramo (base
# de datos, empaquetado y verificación) y comprueba que el trabajo sigue desde su punto de control, que ninguna
# petición pasó de 30 s ni de 64 MB, que las fases avanzaron por tramos y, con unzip -t y el zipfile de Python, que el
# ZIP es íntegro y coincide con su manifiesto.
# Uso: bash tests/playground/backups-steps.sh (en el servidor de desarrollo, por el semáforo de corridas pesadas y con
#      systemd-run --user)
#   OUT, TMPDIR   resultados (por defecto /var/tmp/digi-bk/backups-steps) y temporales de Playground
#   PORT          puerto (por defecto 9463)
#   SIZE_MB       megabytes de medios generados (por defecto 170; con WordPress, unos 200 MB)
# Los tramos son de como mucho 2 s (setup.php), para que en php-wasm también la verificación se parta en varios.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-steps"
export OUT="${OUT:-/var/tmp/digi-bk/backups-steps}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9463}"
SIZE_MB="${SIZE_MB:-170}"
SERVER=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''
}
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM

host="127.0.0.1:$PORT"
dir="$OUT/single"
rm -rf "$dir"; mkdir -p "$dir/probe" "$dir/store" "$dir/ctl" "$dir/uploads"
chmod 700 "$dir/store"
cp -a "$ROOT/digitalisimo-backups" "$dir/plugin"
cp "$D/setup.php" "$dir/probe/"
cp "$D/zipcheck.py" "$dir/"
# Medios: 45 % JPEG aleatorios de 2 MB, un MP4 de 48 MB (más de un tramo), 20 % textos comprimibles de 10 MB y el
# resto en archivos de 4 KB repartidos en 40 carpetas.
jpeg_mb=$(( SIZE_MB * 45 / 100 )); text_mb=$(( SIZE_MB * 20 / 100 )); small_mb=$(( SIZE_MB - jpeg_mb - text_mb - 48 ))
[ "$small_mb" -gt 0 ] || small_mb=8
mkdir -p "$dir/uploads/2026/11" "$dir/uploads/video" "$dir/uploads/logs"
for i in $(seq 1 $(( jpeg_mb / 2 ))); do head -c 2097152 /dev/urandom > "$dir/uploads/2026/11/foto-$i.jpg"; done
head -c $(( 48 * 1048576 )) /dev/urandom > "$dir/uploads/video/presentacion.mp4"
python3 -I -c '
import sys
root, count = sys.argv[1], int(sys.argv[2])
for i in range(1, count + 1):
    line = ("registro %d del ensayo T2: texto que se comprime bien con deflate\n" % i).encode()
    with open("%s/registro-%d.txt" % (root, i), "wb") as f:
        f.write((line * (10 * 1048576 // len(line) + 1))[: 10 * 1048576])
' "$dir/uploads/logs" $(( text_mb / 10 > 0 ? text_mb / 10 : 1 ))
files=$(( small_mb * 256 ))
for d in $(seq 1 40); do mkdir -p "$dir/uploads/miniaturas/carpeta-$d"; done
python3 -I -c '
import os, sys
root, count = sys.argv[1], int(sys.argv[2])
for i in range(count):
    with open(os.path.join(root, "carpeta-%d" % (i % 40 + 1), "pequeno-%05d.txt" % i), "wb") as f:
        f.write(os.urandom(2048) + b"x" * 2048)
' "$dir/uploads/miniaturas" "$files"
echo "== medios generados: $(du -sm "$dir/uploads" | cut -f1) MB en $(find "$dir/uploads" -type f | wc -l) archivos"

cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_MEMORY_LIMIT":"64M","WP_MAX_MEMORY_LIMIT":"64M","WP_ENVIRONMENT_TYPE":"local"}},
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "El puerto $PORT está ocupado por otro proceso; usa PORT=…"; exit 1; fi
start=$(date +%s)
$CLI server --port="$PORT" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
  --mount="$dir/plugin:/wordpress/wp-content/plugins/digitalisimo-backups" \
  --mount="$dir/uploads:/wordpress/wp-content/uploads" \
  --mount="$dir/probe:/digi-probe" \
  --mount="$dir/ctl:/digi-ctl" \
  --mount="$dir/store:/digi-store" > "$dir/server.log" 2>&1 &
SERVER=$!
ready=0
for i in $(seq 1 400); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$dir/probe/setup-error.txt" ] && break
  if [ -s "$dir/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 "http://$host/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
done
if [ "$ready" != 1 ]; then
  echo "El sitio no quedó listo"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"; exit 1
fi
echo "== sitio listo en $(( $(date +%s) - start )) s: $(cat "$dir/probe/setup.json")"
fail=0
node "$D/drive.mjs" "$PORT" "$host" "$dir" || fail=1
grep -qE 'Fatal error|Uncaught' "$dir/server.log" && { echo "== errores de PHP en el servidor"; grep -E 'Fatal error|Uncaught' "$dir/server.log" | head -5; fail=1; }
stop_server
# El sitio y los medios generados pesan: se conservan sólo los resultados.
rm -rf "$dir/uploads" "$dir/plugin" "$dir/store"/*.zip
[ $fail -eq 0 ] || { echo 'Ensayo T2 de Backups con fallos'; exit 1; }
echo "T2 de Backups validado en Playground en $(( $(date +%s) - start )) s."
