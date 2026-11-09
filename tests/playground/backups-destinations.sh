#!/usr/bin/env bash
# Destinos remotos de DIGITALÍSIMO Backups en WordPress Playground contra servidores reales (escenarios en
# backups-destinations/probe.php): S3 compatible (MinIO), FTPS explícito y WebDAV sobre HTTPS, con TLS verificado por una
# CA de prueba. Un respaldo real se envía, se verifica, se trae de vuelta, se descarga y se restaura desde cada uno.
# Los servidores los levanta quien corre el arnés (procesos de usuario; ver docs/BACKUPS.md, «Destinos»):
#   DIGI_LIVE_CA=/ruta/ca.pem
#   DIGI_LIVE_S3='https://127.0.0.1:19000|bucket|access|secret|us-east-1'
#   DIGI_LIVE_FTPS='127.0.0.1|12121|explicit|usuario|clave'
#   DIGI_LIVE_WEBDAV='https://127.0.0.1:18443/dav/|usuario|clave'
# Uso: bash tests/playground/backups-destinations.sh (por el semáforo de corridas pesadas y con systemd-run --user)
#   OUT, TMPDIR  resultados (por defecto /var/tmp/digi-bk/backups-destinations) y temporales de Playground
#   MODES        «single» (los tres destinos) y/o «multisite» (MinIO desde la red); por defecto «single multisite»
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export OUT="${OUT:-/var/tmp/digi-bk/backups-destinations}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
: "${DIGI_LIVE_CA:?Falta DIGI_LIVE_CA}" "${DIGI_LIVE_S3:?Falta DIGI_LIVE_S3}" "${DIGI_LIVE_FTPS:?Falta DIGI_LIVE_FTPS}" "${DIGI_LIVE_WEBDAV:?Falta DIGI_LIVE_WEBDAV}"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
KEYS='"AUTH_KEY":"playground-auth-key-0123456789abcdef0123456789abcdef","SECURE_AUTH_KEY":"playground-secure-auth-key-0123456789abcdef0123456789"'
start=$(date +%s)
fail=0
for mode in ${MODES:-single multisite}; do
  dir="$OUT/$mode"
  rm -rf "$dir"; mkdir -p "$dir"
  cp "$ROOT/tests/playground/backups-destinations/probe.php" "$dir/probe.php"
  echo "$mode" > "$dir/mode"
  IFS='|' read -r s3_endpoint s3_bucket s3_access s3_secret s3_region <<< "$DIGI_LIVE_S3"
  IFS='|' read -r ftp_host ftp_port ftp_mode ftp_user ftp_password <<< "$DIGI_LIVE_FTPS"
  IFS='|' read -r dav_url dav_user dav_password <<< "$DIGI_LIVE_WEBDAV"
  python3 -I - "$dir/config.json" "$DIGI_LIVE_CA" <<PY
import json, sys
json.dump({'ca': open(sys.argv[2]).read(),
  's3': {'endpoint': '$s3_endpoint', 'bucket': '$s3_bucket', 'access': '$s3_access', 'secret': '$s3_secret', 'region': '$s3_region'},
  'ftp': {'host': '$ftp_host', 'port': '$ftp_port', 'user': '$ftp_user', 'password': '$ftp_password'},
  'webdav': {'url': '$dav_url', 'user': '$dav_user', 'password': '$dav_password'}}, open(sys.argv[1], 'w'))
PY
  chmod 600 "$dir/config.json"
  extra=''
  [ "$mode" = multisite ] && extra='{"step":"enableMultisite"},'
  cat > "$dir/blueprint.json" <<JSON
{"features":{"networking":true},"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{$KEYS}},$extra
{"step":"runPHP","code":"<?php require '/digitalisimo-probe/probe.php';"}]}
JSON
  begun=$(date +%s)
  $CLI run-blueprint --site-url=http://playground.test --blueprint="$dir/blueprint.json" \
    --mount="$ROOT/digitalisimo-backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
    --mount="$dir:/digitalisimo-probe" > "$dir/playground.log" 2>&1 || { echo "== $mode: Playground terminó con error"; tail -20 "$dir/playground.log"; fail=1; continue; }
  rm -f "$dir/config.json"
  echo "== $mode ($(( $(date +%s) - begun )) s)"
  cat "$dir/result.txt" 2>/dev/null || true
  if ! grep -q '^TERMINADO' "$dir/result.txt" 2>/dev/null || grep -qE '^FALLA|Fatal error|Uncaught' "$dir/result.txt" "$dir/playground.log"; then
    echo "== $mode con fallos"; tail -20 "$dir/playground.log"; fail=1
  fi
done
[ $fail -eq 0 ] || { echo 'Ensayo de destinos de Backups con fallos'; exit 1; }
echo "Destinos de Backups validados en Playground en $(( $(date +%s) - start )) s."
