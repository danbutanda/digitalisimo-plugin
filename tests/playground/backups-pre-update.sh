#!/usr/bin/env bash
# Respaldo antes de actualizar (I1) de DIGITALÍSIMO Backups en WordPress Playground, con los actualizadores reales de
# WordPress (escenarios en backups-pre-update/probe.php): una actualización manual de un plugin con Plugin_Upgrader se
# rechaza hasta que la cola termina el respaldo previo y después actualiza; `wp_maybe_auto_update` real retiene la
# actualización automática, la cola hace el respaldo, se vuelve a programar `wp_maybe_auto_update` y la siguiente
# pasada actualiza; con el respaldo fallido queda pospuesta, con aviso por correo, y no se relanza. Sin red: los
# paquetes son ZIP locales y toda petición HTTP se corta.
# Uso: bash tests/playground/backups-pre-update.sh (por el semáforo de corridas pesadas)
#   OUT, TMPDIR  resultados (por defecto /var/tmp/digi-bk/backups-pre-update) y temporales de Playground
#   MODES        «single» y/o «multisite»; por defecto los dos
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export OUT="${OUT:-/var/tmp/digi-bk/backups-pre-update}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
KEYS='"AUTH_KEY":"playground-auth-key-0123456789abcdef0123456789abcdef","SECURE_AUTH_KEY":"playground-secure-auth-key-0123456789abcdef0123456789"'
start=$(date +%s)
fail=0
for mode in ${MODES:-single multisite}; do
  dir="$OUT/$mode"
  rm -rf "$dir"; mkdir -p "$dir"
  cp "$ROOT/tests/playground/backups-pre-update/probe.php" "$dir/probe.php"
  echo "$mode" > "$dir/mode"
  extra=''
  [ "$mode" = multisite ] && extra='{"step":"enableMultisite"},'
  cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{$KEYS}},$extra
{"step":"runPHP","code":"<?php require '/digitalisimo-probe/probe.php';"}]}
JSON
  begun=$(date +%s)
  $CLI run-blueprint --site-url=http://playground.test --blueprint="$dir/blueprint.json" \
    --mount="$ROOT/digitalisimo-backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
    --mount="$dir:/digitalisimo-probe" > "$dir/playground.log" 2>&1 || { echo "== $mode: Playground terminó con error"; tail -20 "$dir/playground.log"; fail=1; continue; }
  echo "== $mode ($(( $(date +%s) - begun )) s)"
  cat "$dir/result.txt" 2>/dev/null || true
  if ! grep -q '^TERMINADO' "$dir/result.txt" 2>/dev/null || grep -qE '^FALLA|Fatal error|Uncaught' "$dir/result.txt" "$dir/playground.log"; then
    echo "== $mode con fallos"; tail -20 "$dir/playground.log"; fail=1
  fi
done
[ $fail -eq 0 ] || { echo 'Ensayo del respaldo antes de actualizar con fallos'; exit 1; }
echo "Respaldo antes de actualizar validado en Playground en $(( $(date +%s) - start )) s."
