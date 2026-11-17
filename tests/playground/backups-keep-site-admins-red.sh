#!/usr/bin/env bash
# «Los administradores de este sitio siguen entrando» de DIGITALÍSIMO Backups en una red (pedido del propietario del
# 2026-11-16 y brecha 3 del estudio de UpdraftPlus: lo conservado con el prefijo final y los superadministradores en
# `site_admins` de `sitemeta`), en WordPress Playground (SQLite), por REST y wp-login.php:
#   origen   red-a.test, red por subdirectorios con prefijo wp_: su superadministrador «dueno» y el subsitio «uno»; crea
#            un respaldo de la red por REST.
#   destino  red-b.test, otra red con prefijo dst_ (instalada junto al wp_ de Playground): superadministradores «admin» y
#            «red-soporte», administrador del sitio principal «sitio-admin», editor «colaborador» y el subsitio «dos» con
#            su administrador «otro-admin»; el respaldo de A llega como archivo suelto y «admin» lo restaura por REST.
# Ver backups-keep-site-admins/check-red.mjs. Resultado en $OUT/result.txt.
# Uso: bash tests/playground/backups-keep-site-admins-red.sh   (en la PC, con pc-run.sh; una corrida pesada a la vez)
#   OUT, TMPDIR     resultados (por defecto /var/tmp/digi-bk/backups-keep-site-admins-red) y temporales de Playground
#   PORT            primer puerto (por defecto 9671; cada servidor usa el siguiente)
#   B_BACKUPS       copia de Backups de la red B (por defecto la del repositorio); con la de antes del arreglo y
#                   LABEL=anterior, reproduce lo que pasaba (termina con fallos a propósito)
#   ORIGIN_DIR      reutiliza la red A de una corrida anterior (su carpeta, con store/ y probe/): sin fase de origen
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-keep-site-admins"
export OUT="${OUT:-/var/tmp/digi-bk/backups-keep-site-admins-red}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9671}"
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
# start_site <dir> <host> <puerto> <red a|b> <carpeta de Backups> <pasos del blueprint>
start_site() {
  local dir="$1" host="$2" port="$3" site="$4" plugin="$5" steps="$6"
  mkdir -p "$dir/probe" "$dir/store"
  chmod 700 "$dir/store" "$dir/probe"
  rm -rf "$dir/plugin"; cp -a "$plugin" "$dir/plugin"
  cp "$D/red.php" "$D/probe.php" "$ROOT/tests/playground/backups-relocate/prefix.php" "$dir/probe/"
  echo "$site" > "$dir/probe/site.txt"
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
  echo "la red $host no quedó lista"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"
  return 1
}
errors() { grep -E 'Fatal error|Uncaught' "$1/server.log" | head -5; }
red='{"step":"runPHP","code":"<?php require '"'"'/digi-probe/red.php'"'"';"}'
start=$(date +%s)
fail=0
port=$PORT
origin="${ORIGIN_DIR:-$OUT/red-a}"
if [ -z "${ORIGIN_DIR:-}" ]; then
  rm -rf "$origin"
  begun=$(date +%s)
  if ! start_site "$origin" red-a.test "$port" a "$ROOT/digitalisimo-backups" '{"step":"enableMultisite"},'"$red"; then stop_server; exit 1; fi
  echo "== origen listo en $(( $(date +%s) - begun )) s (puerto $port)"
  node "$D/check-red.mjs" origin "$port" red-a.test "$origin" "$OUT" "$LABEL" || fail=1
  if errors "$origin" | grep -q .; then echo "== origen: errores de PHP"; errors "$origin"; fail=1; fi
  cp "$origin/server.log" "$OUT/server-red-a.log"
  stop_server; sleep 2
fi
file=$(node -e 'console.log(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).file||"")' "$origin/origin.json" 2>/dev/null || true)
[ -n "$file" ] && [ -f "$origin/store/$file" ] || { echo "== no hay respaldo de la red A"; exit 1; }
dest="$OUT/red-b-$LABEL"
rm -rf "$dest"; mkdir -p "$dest/store" "$dest/probe"; chmod 700 "$dest/store"
echo dst_ > "$dest/probe/prefix.txt"
cp "$origin/store/$file" "$dest/store/$file"
port=$((port + 1))
begun=$(date +%s)
if ! start_site "$dest" red-b.test "$port" b "${B_BACKUPS:-$ROOT/digitalisimo-backups}" '{"step":"runPHP","code":"<?php require '"'"'/digi-probe/prefix.php'"'"';"},{"step":"enableMultisite"},'"$red"; then stop_server; exit 1; fi
echo "== destino ($LABEL) listo en $(( $(date +%s) - begun )) s (puerto $port)"
node "$D/check-red.mjs" dest "$port" red-b.test "$dest" "$OUT" "$LABEL" "$origin" || fail=1
if errors "$dest" | grep -q .; then echo "== destino: errores de PHP"; errors "$dest"; fail=1; fi
cp "$dest/server.log" "$OUT/server-red-b-$LABEL.log"
stop_server; sleep 2
rm -rf "$dest"
[ $fail -eq 0 ] || { echo "Ensayo de red de «los administradores de este sitio siguen entrando» ($LABEL) con fallos"; exit 1; }
echo "Los administradores de la red siguen entrando ($LABEL): validado en Playground en $(( $(date +%s) - start )) s."
