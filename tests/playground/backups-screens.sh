#!/usr/bin/env bash
# Pantallas de administración de DIGITALÍSIMO Backups (y, como referencia, de Backuply 1.6.0) en WordPress Playground,
# capturadas con Chrome sin interfaz, para la ronda «interfaz simple». Cada modo arranca su Playground:
#   backuply   Backuply 1.6.0 de wordpress.org (versión fija) en un sitio individual: cada pestaña y cada ventana sin
#              respaldos, un respaldo completo hecho con su propio código (el controlador de base de datos del ensayo de
#              Backuply, ver backups-backuply/backuply-shim.php) y las mismas pantallas con el respaldo;
#   single     DIGITALÍSIMO Backups en un sitio individual: cada pestaña sin respaldos, un respaldo creado desde la
#              pantalla (con su progreso) y otro por REST, y cada pestaña con respaldos;
#   multisite  lo mismo en una red: la pantalla de la red, la del sitio principal y la de un subsitio.
# Cada captura es de página completa a 1280 px de ancho; metrics.json anota, por pantalla, alto, bloques, botones,
# campos, palabras y los textos más largos. Sin aserciones de diseño: sólo falla si una pantalla no carga o hay un
# error de PHP.
# Uso: bash tests/playground/backups-screens.sh   (en la PC, con pc-run.sh; una corrida pesada a la vez)
#   OUT, TMPDIR     resultados (por defecto /var/tmp/digi-bk/backups-screens) y temporales de Playground
#   MODES           modos (por defecto «backuply single multisite»)
#   LABEL           subcarpeta de las capturas (por defecto «actual»; p. ej. «antes» o «despues»)
# El sitio lleva AUTH_KEY y SECURE_AUTH_KEY propias, como uno real: las capturas no llevan el aviso de credenciales.
#   PORT            primer puerto (por defecto 9591)
#   BACKUPLY_ZIP    un backuply.1.6.0.zip ya descargado (si no, se descarga de downloads.wordpress.org)
#   PUPPETEER_PATH  carpeta node_modules con puppeteer-core (si no, se busca en la caché de npx o se instala 25.12.0)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-screens"
B="$ROOT/tests/playground/backups-backuply"
export OUT="${OUT:-/var/tmp/digi-bk/backups-screens}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9591}"
LABEL="${LABEL:-actual}"
MODES="${MODES:-backuply single multisite}"
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

if [[ " $MODES " == *" backuply "* ]]; then
  # Backuply 1.6.0 tal como lo publica wordpress.org (archivo no confiable: sólo se monta dentro de Playground).
  ZIP="${BACKUPLY_ZIP:-$TMPDIR/backuply.1.6.0.zip}"
  [ -s "$ZIP" ] || curl -fsSL -o "$ZIP" https://downloads.wordpress.org/plugin/backuply.1.6.0.zip
  rm -rf "$TMPDIR/backuply-src"; mkdir -p "$TMPDIR/backuply-src"
  unzip -q "$ZIP" -d "$TMPDIR/backuply-src"
  grep -q '^Version: 1.6.0' "$TMPDIR/backuply-src/backuply/backuply.php" || { echo 'El ZIP de Backuply no es la versión 1.6.0'; exit 2; }
  echo "== Backuply 1.6.0: sha256 $(sha256sum "$ZIP" | cut -d' ' -f1)"
fi

start=$(date +%s)
fail=0
port=$PORT
for mode in $MODES; do
  case "$mode" in
    backuply|single) multi=''; host="pantallas-$mode.test" ;;
    multisite) multi='{"step":"enableMultisite"},'; host='pantallas-red.test' ;;
    *) echo "Modo desconocido: $mode"; exit 2 ;;
  esac
  dir="$OUT/$mode"
  rm -rf "$dir/run"; mkdir -p "$dir/run/probe" "$dir/run/web" "$dir/run/store" "$dir/shots/$LABEL"
  chmod 700 "$dir/run/store"
  echo "$mode" > "$dir/run/probe/mode.txt"
  cp "$D/setup.php" "$dir/run/probe/"
  extra=()
  if [ "$mode" = backuply ]; then
    cp "$B/backuply-shim.php" "$dir/run/probe/"
    cp "$B/backuply-run.php" "$B/backuply-state.php" "$dir/run/web/"
    head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/run/web/token.txt"
    # La copia de Backuply del ensayo: sólo su elección de controlador de base de datos cambia (ver backuply-shim.php).
    cp -a "$TMPDIR/backuply-src/backuply" "$dir/run/backuply-plugin"
    sed -i "s/extension_loaded('mysqli')/digi_backuply_mysqli()/g" "$dir/run/backuply-plugin/backup_ins.php"
    mkdir -p "$dir/run/backuply"
    extra=(--mount="$dir/run/backuply-plugin:/wordpress/wp-content/plugins/backuply" --mount="$dir/run/backuply:/wordpress/wp-content/backuply" --mount="$dir/run/web:/wordpress/wp-content/digi-web")
  else
    cp -a "$ROOT/digitalisimo-backups" "$dir/run/plugin"
    extra=(--mount="$dir/run/plugin:/wordpress/wp-content/plugins/digitalisimo-backups" --mount="$dir/run/store:/digi-store")
  fi
  cat > "$dir/run/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local"}},$multi
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
  if fuser -n tcp "$port" >/dev/null 2>&1; then echo "el puerto $port está ocupado; usa PORT=…"; exit 1; fi
  begun=$(date +%s)
  $CLI server --port="$port" --workers=1 --site-url="http://$host" --blueprint="$dir/run/blueprint.json" \
    "${extra[@]}" --mount="$dir/run/probe:/digi-probe" > "$dir/run/server.log" 2>&1 &
  SERVER=$!
  SERVER_PORT="$port"
  ready=0
  for i in $(seq 1 400); do
    sleep 3
    kill -0 "$SERVER" 2>/dev/null || break
    [ -f "$dir/run/probe/setup-error.txt" ] && break
    if [ -s "$dir/run/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$port/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
  done
  if [ "$ready" != 1 ]; then
    echo "== $mode: el sitio no quedó listo"; cat "$dir/run/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/run/server.log"
    stop_server; fail=1; port=$((port + 1)); continue
  fi
  echo "== $mode: sitio listo en $(( $(date +%s) - begun )) s: $(cat "$dir/run/probe/setup.json" | sed 's/"password":"[^"]*"/"password":"…"/')"
  node "$D/shoot.mjs" "$mode" "$port" "$host" "$dir" "$LABEL" || fail=1
  if grep -E 'Fatal error|Uncaught' "$dir/run/server.log" | head -5 | grep -q .; then echo "== $mode: errores de PHP en el servidor"; grep -E 'Fatal error|Uncaught' "$dir/run/server.log" | head -5; fail=1; fi
  cp "$dir/run/server.log" "$dir/server-$LABEL.log"
  stop_server; sleep 2
  rm -rf "$dir/run"
  port=$((port + 1))
done
[ $fail -eq 0 ] || { echo 'Ensayo de pantallas con fallos'; exit 1; }
echo "Pantallas capturadas en Playground en $(( $(date +%s) - start )) s ($OUT/*/shots/$LABEL)."
