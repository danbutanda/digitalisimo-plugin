#!/usr/bin/env bash
# «Los administradores de este sitio siguen entrando» de DIGITALÍSIMO Backups (pedido del propietario del 2026-11-16:
# «respeta los usuarios existentes para no romper accesos admin») en WordPress Playground (SQLite), con Chrome sin
# interfaz y por la pantalla, en dos sitios individuales:
#   a   sitio-a.test: su administrador «dueno» y su editora «editora»; «dueno» hace un respaldo con «Respaldar ahora».
#   b   sitio-b.test, recién instalado: los administradores «admin» y «soporte» y el editor «colaborador»; «admin» sube el
#       de A, lo restaura con RESTAURAR y después «admin», «soporte» y «dueno» entran con su contraseña como
#       administradores, el contenido es el de A y nada se rompe. Ver backups-keep-site-admins/drive.mjs.
# Capturas en $OUT/shots y resultado en $OUT/result.txt.
# Uso: bash tests/playground/backups-keep-site-admins.sh   (en la PC, con pc-run.sh; una corrida pesada a la vez)
#   OUT, TMPDIR     resultados (por defecto /var/tmp/digi-bk/backups-keep-site-admins) y temporales de Playground
#   PORT            primer puerto (por defecto 9641)
#   B_BACKUPS       copia de Backups del sitio B (por defecto la del repositorio): con la de antes del arreglo y
#                   LABEL=anterior, el ensayo reproduce lo que pasaba (termina con fallos a propósito)
#   PACKAGE         reutiliza el respaldo del sitio A de una corrida anterior (con SECRETS_A, su secrets.json): sin fase a
#   PUPPETEER_PATH  carpeta node_modules con puppeteer-core (si no, se busca en la caché de npx o se instala 25.12.0)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-keep-site-admins"
export OUT="${OUT:-/var/tmp/digi-bk/backups-keep-site-admins}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9641}"
LABEL="${LABEL:-nuevo}"
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

# start_site <dir> <host> <puerto> <sitio a|b> <carpeta de Backups>
start_site() {
  local dir="$1" host="$2" port="$3" site="$4" plugin="$5"
  rm -rf "$dir"; mkdir -p "$dir/probe" "$dir/store"
  chmod 700 "$dir/store" "$dir/probe"
  cp -a "$plugin" "$dir/plugin"
  cp "$D/setup.php" "$D/probe.php" "$dir/probe/"
  echo "$site" > "$dir/probe/site.txt"
  head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
  cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local","DIGITALISIMO_BACKUPS_RESTORE_MU_PLUGINS":true}},
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
  if fuser -n tcp "$port" >/dev/null 2>&1; then echo "el puerto $port está ocupado; usa PORT=…"; return 1; fi
  $CLI server --port="$port" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
    --mount="$dir/plugin:/wordpress/wp-content/plugins/digitalisimo-backups" \
    --mount="$dir/probe:/digi-probe" \
    --mount="$dir/store:/digi-store" > "$dir/server.log" 2>&1 &
  SERVER=$!
  SERVER_PORT="$port"
  for i in $(seq 1 400); do
    sleep 3
    kill -0 "$SERVER" 2>/dev/null || break
    [ -f "$dir/probe/setup-error.txt" ] && break
    if [ -s "$dir/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$port/?rest_route=%2F")" = 200 ]; then return 0; fi
  done
  echo "el sitio $host no quedó listo"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"
  return 1
}
errors() { grep -E 'Fatal error|Uncaught' "$1/server.log" | head -5; }

start=$(date +%s)
fail=0
port=$PORT
ZIP_A="${PACKAGE:-$OUT/sitio-a.zip}"
SECRETS_A="${SECRETS_A:-$OUT/secrets-a.json}"
phases="a b"
[ -n "${PACKAGE:-}" ] && phases="b"
for site in $phases; do
  dir="$OUT/sitio-$site-$LABEL"
  host="sitio-$site.test"
  plugin="$ROOT/digitalisimo-backups"
  [ "$site" = b ] && plugin="${B_BACKUPS:-$ROOT/digitalisimo-backups}"
  begun=$(date +%s)
  if [ "$site" = b ] && [ ! -s "$ZIP_A" ]; then echo "== b: falta el respaldo del sitio A"; fail=1; break; fi
  if ! start_site "$dir" "$host" "$port" "$site" "$plugin"; then stop_server; fail=1; break; fi
  echo "== $site ($LABEL): sitio listo en $(( $(date +%s) - begun )) s (puerto $port)"
  if [ "$site" = a ]; then cp "$dir/probe/secrets.json" "$SECRETS_A"; chmod 600 "$SECRETS_A"; fi
  node "$D/drive.mjs" "$site" "$port" "$host" "$dir" "$OUT" "$LABEL" "$ZIP_A" "$SECRETS_A" || fail=1
  if errors "$dir" | grep -q .; then echo "== $site: errores de PHP en el servidor"; errors "$dir"; fail=1; fi
  cp "$dir/server.log" "$OUT/server-$site-$LABEL.log"
  stop_server; sleep 2
  rm -rf "$dir"
  port=$((port + 1))
done
[ $fail -eq 0 ] || { echo "Ensayo «los administradores de este sitio siguen entrando» ($LABEL) con fallos"; exit 1; }
echo "Los administradores de este sitio siguen entrando ($LABEL): validado en Playground con Chrome en $(( $(date +%s) - start )) s ($OUT/shots)."
