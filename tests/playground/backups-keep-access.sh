#!/usr/bin/env bash
# «Conservar el acceso de quien restaura» de DIGITALÍSIMO Backups en WordPress Playground (SQLite), sin Chrome, por las
# superficies reales: el caso del /wp de digitalisimo.mx del 2026-11-12.
#   red      (run-blueprint: backups-keep-access/network.php) red por subdirectorios red.test con Backups, Tools y
#            DIGITALÍSIMO Elements activos en la red, la ruta privada de acceso de la red en Tools, el usuario 1
#            «digitalisimo» (superadministrador) con contraseña y contraseña de aplicación, y el MCP de Elements
#            encendido; POST /sites/1/extract por la REST (network, super_admins=none, network_secrets).
#   destino  (server en http://127.0.0.1:<puerto>/wp, prefijo wpkx_) un WordPress individual con Backups y «admin» con
#            su contraseña de aplicación; check.mjs sube el paquete por /uploads en fragmentos, lo restaura por REST con
#            addresses=current y sigue el trabajo hasta el final; después comprueba REST, MCP, wp-login.php, la cookie
#            del navegador y el informe.
# Uso: bash tests/playground/backups-keep-access.sh
#   OUT, TMPDIR     resultados (por defecto /var/tmp/digi-bk/backups-keep-access) y temporales de Playground
#   PORT            puerto del destino (por defecto 9491; si está ocupado, el siguiente libre)
#   DEST_BACKUPS    copia de Backups del destino (por defecto la del repositorio): con la de antes del arreglo,
#                   LABEL=anterior reproduce el incidente (el ensayo termina con fallos a propósito)
#   PACKAGE         reutiliza un paquete ya extraído (ruta a su .zip y a la carpeta red/ de un ensayo anterior: RED_DIR)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-keep-access"
export OUT="${OUT:-/var/tmp/digi-bk/backups-keep-access}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9491}"
LABEL="${LABEL:-nuevo}"
SERVER=''
SERVER_PORT=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$SERVER_PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$SERVER_PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''
  SERVER_PORT=''
}
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM
start=$(date +%s)
RED="${RED_DIR:-$OUT/red}"
if [ -z "${PACKAGE:-}" ]; then
  rm -rf "$RED"
  mkdir -p "$RED/probe" "$RED/store"
  chmod 700 "$RED/store" "$RED/probe"
  cp -a "$ROOT/digitalisimo-backups" "$RED/backups"
  for plugin in digitalisimo-tools digitalisimo-elements; do cp -a "$ROOT/$plugin" "$RED/$plugin"; done
  cp "$D/network.php" "$RED/probe/"
  steps=''
  for step in setup extract; do steps="$steps,{\"step\":\"runPHP\",\"code\":\"<?php putenv('DIGI_STEP=$step'); require '/digi-probe/network.php';\"}"; done
  cat > "$RED/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local","AUTH_KEY":"red-auth-key-0123456789abcdef0123456789abcdef","SECURE_AUTH_KEY":"red-secure-auth-key-0123456789abcdef0123456789"}},
{"step":"enableMultisite"}$steps]}
JSON
  begun=$(date +%s)
  $CLI run-blueprint --site-url=http://red.test --blueprint="$RED/blueprint.json" \
    --mount="$RED/backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
    --mount="$RED/digitalisimo-tools:/wordpress/wp-content/plugins/digitalisimo-tools" \
    --mount="$RED/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
    --mount="$RED/probe:/digi-probe" \
    --mount="$RED/store:/digi-store" > "$RED/playground.log" 2>&1 || { echo "== red: Playground terminó con error"; tail -30 "$RED/playground.log"; exit 1; }
  echo "== red ($(( $(date +%s) - begun )) s)"
  node -e '
const r = require(process.argv[1]); let bad = 0;
for (const [step, out] of Object.entries(r)) { for (const p of out.problems) { console.log(`FALLA · ${step}: ${p}`); bad++; } if (!out.problems.length) console.log(`ok · ${step} ${JSON.stringify(out.data).slice(0, 300)}`); }
for (const s of ["setup", "extract"]) if (!r[s]) { console.log(`FALLA · falta el paso ${s}`); bad++; }
process.exit(bad ? 1 : 0);' "$RED/probe/result.json" || { cat "$RED/probe/fatal.log" 2>/dev/null || true; exit 1; }
  [ ! -s "$RED/probe/fatal.log" ] || { cat "$RED/probe/fatal.log"; exit 1; }
  PACKAGE="$RED/store/$(node -e 'console.log(require(process.argv[1]).package)' "$RED/probe/state.json")"
fi
DEST="$OUT/destino-$LABEL"
rm -rf "$DEST"
mkdir -p "$DEST/probe" "$DEST/store" "$DEST/package"
chmod 700 "$DEST/store" "$DEST/probe"
cp -a "${DEST_BACKUPS:-$ROOT/digitalisimo-backups}" "$DEST/backups"
cp "$PACKAGE" "$DEST/package/"
basename "$PACKAGE" > "$DEST/probe/package.txt"
echo wpkx_ > "$DEST/probe/prefix.txt"
cp "$D/prefix.php" "$D/destination.php" "$DEST/probe/"
echo "· paquete $(basename "$PACKAGE") ($(du -h "$PACKAGE" | cut -f1))"
cat > "$DEST/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local","AUTH_KEY":"destino-auth-key-0123456789abcdef0123456789abcdef","SECURE_AUTH_KEY":"destino-secure-auth-key-0123456789abcdef0123456789"}},
{"step":"runPHP","code":"<?php require '/digi-probe/prefix.php';"},{"step":"runPHP","code":"<?php require '/digi-probe/destination.php';"}]}
JSON
for try in $(seq "$PORT" $(( PORT + 40 ))); do fuser -n tcp "$try" >/dev/null 2>&1 || { PORT="$try"; break; }; done
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "== destino: no hay un puerto libre desde $PORT; usa PORT=…"; exit 1; fi
begun=$(date +%s)
$CLI server --port="$PORT" --workers=1 --site-url="http://127.0.0.1:$PORT/wp" --blueprint="$DEST/blueprint.json" \
  --mount="$DEST/backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
  --mount="$DEST/probe:/digi-probe" \
  --mount="$DEST/store:/digi-store" > "$DEST/server.log" 2>&1 &
SERVER=$!
SERVER_PORT="$PORT"
ready=0
for i in $(seq 1 400); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$DEST/probe/setup-error.txt" ] && break
  if [ -s "$DEST/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 120 "http://127.0.0.1:$PORT/wp/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
done
if [ "$ready" != 1 ]; then
  echo "== destino: no quedó listo"; cat "$DEST/probe/setup-error.txt" 2>/dev/null || true; tail -30 "$DEST/server.log"; exit 1
fi
echo "== destino listo en $(( $(date +%s) - begun )) s (puerto $PORT, /wp)"
fail=0
node "$D/check.mjs" "$PORT" "$DEST" "$RED" "$LABEL" || fail=1
grep -qE 'Fatal error|Uncaught' "$DEST/server.log" && { echo "== destino: errores de PHP en el servidor"; grep -E 'Fatal error|Uncaught' "$DEST/server.log" | head -5; fail=1; }
stop_server
echo "· paquete reutilizable: PACKAGE=$PACKAGE RED_DIR=$RED"
[ $fail -eq 0 ] || { echo "Ensayo de «conservar el acceso» ($LABEL) con fallos"; exit 1; }
echo "«Conservar el acceso de quien restaura» ($LABEL) validado en Playground en $(( $(date +%s) - start )) s."
