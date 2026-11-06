#!/usr/bin/env bash
# Seguridad de DIGITALÍSIMO Backups en WordPress Playground (SQLite, sin libsodium nativo: usa el sodium_compat de
# WordPress), sin Chrome. Las claves de ejemplo de wp-config.php no bastan para guardar una frase, así que el blueprint
# define AUTH_KEY y SECURE_AUTH_KEY propias. Cada modo arranca su propio Playground (escenarios en
# backups-security/probe.php):
#   single     sitio individual: S1, S3, S4 y S7; con ENCRYPT=1 también S2 (con sodium_compat en php-wasm cifrar tarda
#              unos 11 s por MiB: S2 suma unos 25 minutos)
#   multisite  red: S1 desde la red y S5 (un subsitio no gestiona la seguridad)
#   constant   DIGITALISIMO_BACKUPS_KEY en wp-config.php: S6
# La carpeta de resultados se monta fuera de /wordpress para que las restauraciones no la reemplacen.
# Uso: bash tests/playground/backups-security.sh (en el servidor de desarrollo, por el semáforo de corridas pesadas
#      y con systemd-run --user, que sobrevive al cierre de la sesión)
#   OUT, TMPDIR   resultados (por defecto /var/tmp/digi-bk/backups-security) y temporales de Playground
#   MODES         modos a correr (por defecto «single multisite constant»)
#   ENCRYPT=1     añade S2 al modo single
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export OUT="${OUT:-/var/tmp/digi-bk/backups-security}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
KEYS='"AUTH_KEY":"playground-auth-key-0123456789abcdef0123456789abcdef","SECURE_AUTH_KEY":"playground-secure-auth-key-0123456789abcdef0123456789"'
start=$(date +%s)
fail=0
for mode in ${MODES:-single multisite constant}; do
  dir="$OUT/$mode"
  rm -rf "$dir"; mkdir -p "$dir"
  cp "$ROOT/tests/playground/backups-security/probe.php" "$dir/probe.php"
  echo "$mode" > "$dir/mode"
  [ -n "${ENCRYPT:-}" ] && [ "$mode" = single ] && : > "$dir/encrypt"
  consts="$KEYS"
  extra=''
  case "$mode" in
    multisite) extra='{"step":"enableMultisite"},' ;;
    # Base64 de 32 bytes «C»: una clave de prueba, nunca una real.
    constant) consts="$KEYS"',"DIGITALISIMO_BACKUPS_KEY":"Q0NDQ0NDQ0NDQ0NDQ0NDQ0NDQ0NDQ0NDQ0NDQ0NDQ0M="' ;;
    single) ;;
    *) echo "Modo desconocido: $mode"; exit 2 ;;
  esac
  cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{$consts}},$extra
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
[ $fail -eq 0 ] || { echo 'Ensayo de seguridad de Backups con fallos'; exit 1; }
echo "Seguridad de Backups validada en Playground en $(( $(date +%s) - start )) s."
