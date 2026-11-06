#!/usr/bin/env bash
# Cobertura de DIGITALÍSIMO Backups en WordPress Playground (SQLite), sin Chrome. Lo que las pruebas php-wasm no pueden
# probar con stubs, en un WordPress real servido por HTTP:
#   - la API REST con la autenticación de WordPress: sin sesión (401), cookie sin nonce (anónima, 401), nonce falso
#     (403), cookie con nonce `wp_rest` (200), contraseñas de aplicación de una editora (403) y de admin (200) y, en la
#     red, de un administrador del sitio principal que no administra la red (403);
#   - respaldo, verificación y restauración reales por REST (un cambio posterior vuelve atrás);
#   - WP-CLI real (wp-cli.phar dentro de Playground): ayuda generada del PHPDoc, --confirm obligatorio, status, list,
#     verify, automation list y create; en la red, --network y el sitio principal.
# Modos: single (sitio individual) y multisite (red con el subsitio «uno»). La copia del plugin, la carpeta de WP-CLI y
# el almacén privado se montan desde la carpeta de resultados: una restauración nunca escribe en el repositorio.
# Uso: bash tests/playground/backups-coverage.sh (en el servidor de desarrollo, por el semáforo de corridas pesadas y
#      con systemd-run --user)
#   OUT, TMPDIR   resultados (por defecto /var/tmp/digi-bk/backups-coverage) y temporales de Playground
#   MODES         modos a correr (por defecto «single multisite»)
#   PORT          puerto del modo single (por defecto 9461); multisite usa PORT+1
#   WP_CLI_PHAR   wp-cli.phar local; si no, se descarga una vez de playground.wordpress.net (la copia que usa Playground)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-coverage"
export OUT="${OUT:-/var/tmp/digi-bk/backups-coverage}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9461}"
SERVER=''
SERVER_PORT=''
# Sólo se libera el puerto que este arnés ocupó: antes de arrancar se comprueba que estaba libre.
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$SERVER_PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$SERVER_PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''; SERVER_PORT=''
}
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM

if [ -n "${WP_CLI_PHAR:-}" ]; then
  PHAR="$WP_CLI_PHAR"
else
  PHAR="$OUT/wp-cli.phar"
  [ -s "$PHAR" ] || { curl -fsSL -m 120 -o "$PHAR.part" https://playground.wordpress.net/wp-cli.phar && mv "$PHAR.part" "$PHAR"; }
fi
[ -s "$PHAR" ] || { echo "Falta wp-cli.phar"; exit 1; }

start=$(date +%s)
fail=0
offset=0
for mode in ${MODES:-single multisite}; do
  port=$((PORT + offset)); offset=$((offset + 1))
  case "$mode" in
    single) host="127.0.0.1:$port"; extra='' ;;
    multisite) host='playground.test'; extra='{"step":"enableMultisite"},' ;;
    *) echo "Modo desconocido: $mode"; exit 2 ;;
  esac
  dir="$OUT/$mode"
  rm -rf "$dir"; mkdir -p "$dir/probe" "$dir/web" "$dir/store"
  chmod 700 "$dir/store"
  cp -a "$ROOT/digitalisimo-backups" "$dir/plugin"
  cp "$D/setup.php" "$dir/probe/"
  cp "$D/cli.php" "$dir/web/"
  cp "$PHAR" "$dir/web/wp-cli.phar"
  head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/web/token.txt"
  cat > "$dir/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local"}},$extra
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
  if fuser -n tcp "$port" >/dev/null 2>&1; then echo "== $mode: el puerto $port está ocupado por otro proceso; usa PORT=…"; fail=1; continue; fi
  begun=$(date +%s)
  $CLI server --port="$port" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
    --mount="$dir/plugin:/wordpress/wp-content/plugins/digitalisimo-backups" \
    --mount="$dir/web:/wordpress/wp-content/digi-web" \
    --mount="$dir/probe:/digi-probe" \
    --mount="$dir/store:/digi-store" > "$dir/server.log" 2>&1 &
  SERVER=$!
  SERVER_PORT="$port"
  ready=0
  for i in $(seq 1 400); do
    sleep 3
    kill -0 "$SERVER" 2>/dev/null || break
    [ -f "$dir/probe/setup-error.txt" ] && break
    if [ -s "$dir/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$port/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
  done
  if [ "$ready" != 1 ]; then
    echo "== $mode: el sitio no quedó listo"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"
    stop_server; fail=1; continue
  fi
  echo "== $mode: sitio listo en $(( $(date +%s) - begun )) s (puerto $port)"
  node "$D/check.mjs" "$mode" "$port" "$host" "$dir" || fail=1
  grep -qE 'Fatal error|Uncaught' "$dir/server.log" && { echo "== $mode: errores de PHP en el servidor"; grep -E 'Fatal error|Uncaught' "$dir/server.log" | head -5; fail=1; }
  ls -la "$dir/store" > "$dir/store.txt"
  stop_server
  sleep 2
done
[ $fail -eq 0 ] || { echo 'Ensayo de cobertura de Backups con fallos'; exit 1; }
echo "Cobertura de Backups validada en Playground en $(( $(date +%s) - start )) s."
