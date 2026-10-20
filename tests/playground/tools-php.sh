#!/usr/bin/env bash
# «Código PHP» de DIGITALÍSIMO Tools en un WordPress real (Playground, modo servidor, un solo proceso), primero en un sitio
# individual con la ruta privada de acceso y después en una red con los sitios 2 y 3. Un conductor en Node hace lo que haría
# el navegador del administrador con un tarro de cookies (los tres pasos de «Guardar y activar», sin loopback del servidor)
# y comprueba los escenarios de §11.4: cabecera de un fragmento válido, sintaxis, fatal al cargarse, fatal diferido y
# conciliación, salida al cargarse, portada que redirige (plugin falso), la constante, el enlace de rescate con la ruta
# privada, la red (aislamiento, pausa en un sitio, un solo correo, «Reanudar en todos los sitios»), una alteración directa
# de la BD, DISALLOW_FILE_EDIT, las consultas sin fragmentos, la importación y el escape.
# Uso: bash tests/playground/tools-php.sh   → resultados en $OUT (por defecto /tmp/digi-tools-php)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/tmp/digi-tools-php}"
PORT="${PORT:-9451}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
SERVER=""
stop_server() { [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; SERVER=""; }
trap 'stop_server; playground_cleanup' EXIT
status=0

run_phase() {
  local phase="$1" site="$2" dir="$OUT/$1"
  rm -rf "$dir" && mkdir -p "$dir"
  cp "$ROOT/tests/playground/tools-php/setup.php" "$dir/setup.php"
  if [ "$phase" = network ]; then
    printf '%s' '{"steps":[{"step":"enableMultisite"},{"step":"runPHP","code":"<?php require '"'"'/wordpress/wp-content/probe/setup.php'"'"';"}]}' > "$dir/blueprint.json"
  else
    printf '%s' '{"steps":[{"step":"runPHP","code":"<?php require '"'"'/wordpress/wp-content/probe/setup.php'"'"';"}]}' > "$dir/blueprint.json"
  fi
  stop_server
  sleep 1
  # Una red no admite puertos en su dirección: el sitio es http://playground.test y el conductor lo pide al puerto local.
  $CLI server --port="$PORT" --workers=1 --site-url="$site" --blueprint="$dir/blueprint.json" \
    --mount="$ROOT/digitalisimo-tools:/wordpress/wp-content/plugins/digitalisimo-tools" \
    --mount="$ROOT/tests/playground/fake-plugins/digitalisimo-php-redirect:/wordpress/wp-content/plugins/digitalisimo-php-redirect" \
    --mount="$ROOT/tests/playground/tools-php/probe.php:/wordpress/wp-content/mu-plugins/digi-php-probe.php" \
    --mount="$dir:/wordpress/wp-content/probe" > "$dir/server.log" 2>&1 &
  SERVER=$!
  for i in $(seq 1 200); do
    sleep 3
    if ! kill -0 "$SERVER" 2>/dev/null; then tail -20 "$dir/server.log"; return 1; fi
    [ -f "$dir/setup-error.txt" ] && break
    [ -f "$dir/ready.json" ] && curl -s -m 20 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/" 2>/dev/null | grep -q '^200$' && break
  done
  [ -f "$dir/setup-error.txt" ] && { echo "Preparación incompleta ($phase):"; cat "$dir/setup-error.txt"; return 1; }
  [ -f "$dir/ready.json" ] || { echo "El sitio no terminó de prepararse ($phase)"; tail -20 "$dir/server.log"; return 1; }
  OUT="$dir" node "$ROOT/tests/playground/tools-php/run.mjs" "http://127.0.0.1:$PORT" "$site" "$phase"
}

run_phase single "http://127.0.0.1:$PORT" || status=1
run_phase network "http://playground.test" || status=1
stop_server
echo
[ "$status" = 0 ] && echo "Código PHP en Playground: todos los escenarios de sitio individual y de red pasan." || echo "Código PHP en Playground: hay escenarios que fallan (detalle en $OUT/*/result.json)."
exit $status
