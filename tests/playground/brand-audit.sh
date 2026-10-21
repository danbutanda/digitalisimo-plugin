#!/usr/bin/env bash
# Auditoría de marca: un sitio con DIGITALÍSIMO Elements y su núcleo incluido (sin el plugin Elementor), en español,
# recorrido en Chrome en español y en inglés: pantallas del constructor en la administración, escritorio, plugins,
# páginas, editor de bloques, kits, el editor del constructor (con su menú y la biblioteca de plantillas) y el frontal.
# Falla si un usuario ve «Elementor» (texto, atributos accesibles, títulos, imágenes o logotipos), si el HTML público
# lo anuncia en la meta generator, o si algo apunta a un plugin de origen (vendors.json): una petición del navegador o
# del servidor, un enlace, iframe, vídeo o recurso en el DOM, una ventana nueva, una redirección o un correo.
# Capturas y detalle en $OUT (brand-audit.json, vendor-urls.json, http.jsonl).
# Uso: bash tests/playground/brand-audit.sh
#   LOCALES=es_MX       un solo idioma (por defecto es_MX,en_US)
#   SCOPE=quick|full    quick: las pantallas de siempre más la red; full (por defecto): además pestañas, barra superior,
#                       biblioteca y paneles del editor, app de kits, Theme Builder, pantallas de WordPress y Migración
#   STATE=nuevo|migrado|kit  migrado: sitio que viene de Elementor (telemetría, beta, cuenta conectada y cachés remotas);
#                       kit: el kit sintético KIT_FIXTURE (tercero-pro3 por defecto) importado por «wp digitalisimo-elements
#                       kit import» (etapa K):
#                       con cualquier SCOPE recorre además la app de importación (#/import-customization, /content y
#                       /complete), la pantalla «Kits» con su informe y las páginas importadas. Puerto 9464 por defecto.
#   ORIGINAL=1          el plugin Elementor de wordpress.org instalado e inactivo (su fila queda fuera, con prueba)
#   LATENT=fail         los datos localizados y plantillas con URL del proveedor también hacen fallar
#   PORT, OUT           puerto del servidor y carpeta de resultados
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/tmp/digi-brand-audit}"
SCOPE="${SCOPE:-full}"
STATE="${STATE:-nuevo}"
# STATE=kit corre en paralelo con los arneses de K: su puerto es el 9464 (los de kit-roundtrip son 9461 a 9463).
PORT="${PORT:-$([ "$STATE" = kit ] && echo 9464 || echo 9441)}"
ORIGINAL="${ORIGINAL:-}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
case "$SCOPE" in quick|full) ;; *) echo "SCOPE debe ser quick o full"; exit 1;; esac
case "$STATE" in nuevo|migrado|kit) ;; *) echo "STATE debe ser nuevo, migrado o kit"; exit 1;; esac
mkdir -p "$OUT" && rm -rf "$OUT"/*.png "$OUT"/*.bars.html "$OUT/brand-audit.json" "$OUT/vendor-urls.json" "$OUT/setup-error.txt" \
  "$OUT/setup-warnings.txt" "$OUT/ids.json" "$OUT/fatal.log" "$OUT/http.jsonl" "$OUT/netlog.json" "$OUT/cron-"*.json "$OUT/migrado" \
  "$OUT/kits" "$OUT/kit.json"
A="$ROOT/tests/playground/brand-audit"
cp "$A/setup.php" "$A/probe-http.php" "$A/cron.php" "$A/migrado.json" "$OUT/"
# Lista de proveedores con los vídeos de muestra regenerados desde el código (el núcleo cambia en cada versión).
node "$A/vendors.cjs" "$ROOT" > "$OUT/vendors.json"
printf '{"scope":"%s","state":"%s","original":%s}\n' "$SCOPE" "$STATE" "$([ "$ORIGINAL" = 1 ] && echo true || echo false)" > "$OUT/audit-options.json"
# STATE=migrado: las cachés remotas se descargan aquí, en el equipo de prueba; el sitio nunca sale a buscarlas.
if [ "$STATE" = migrado ]; then
  mkdir -p "$OUT/migrado"
  node -e 'for (const c of require(process.argv[1]).caches) console.log(c.option + " " + c.url)' "$A/migrado.json" | while read -r option url; do
    curl -fsS -m 20 -A 'WordPress/6.8; http://localhost' -o "$OUT/migrado/$option.json" "$url" 2>/dev/null || rm -f "$OUT/migrado/$option.json"
  done
fi
STEPS='{"step":"setSiteLanguage","language":"es_MX"},'
[ "$ORIGINAL" = 1 ] && STEPS='{"step":"installPlugin","pluginData":{"resource":"wordpress.org/plugins","slug":"elementor"},"options":{"activate":false}},'"$STEPS"
LIBRARIES=''
KIT_STEPS=''
# STATE=kit: un kit sintético de terceros (tests/sources/kits/, determinista; KIT_FIXTURE, por defecto tercero-pro3)
# se importa con el WP-CLI propio de K con el sitio ya en marcha, como lo haría un administrador; después setup.php
# anota la sesión y las páginas importadas.
KIT_FIXTURE="${KIT_FIXTURE:-tercero-pro3}"
if [ "$STATE" = kit ]; then
  [ -f "$ROOT/tests/sources/kits/spec/$KIT_FIXTURE.json" ] || { echo "KIT_FIXTURE: no existe tests/sources/kits/spec/$KIT_FIXTURE.json"; exit 1; }
  node "$ROOT/tests/sources/kits/build.mjs" --check
  node "$ROOT/tests/sources/kits/build.mjs" --out "$OUT/kits"
  # WP-CLI de una copia local (la misma caché que kit-roundtrip.sh): sin «extraLibraries», Playground no descarga el
  # phar en cada arranque, una descarga que a veces se cuelga o agota la pila del servidor.
  KIT_CACHE="${KIT_CACHE:-/var/tmp/digi-k/cache}"
  mkdir -p "$KIT_CACHE"
  if [ -n "${WP_CLI_PHAR:-}" ]; then cp "$WP_CLI_PHAR" "$OUT/wp-cli.phar"
  else
    if [ ! -s "$KIT_CACHE/wp-cli.phar" ]; then
      curl -fsSL -m 120 -o "$KIT_CACHE/wp-cli.phar.part" https://playground.wordpress.net/wp-cli.phar && mv "$KIT_CACHE/wp-cli.phar.part" "$KIT_CACHE/wp-cli.phar"
    fi
    cp "$KIT_CACHE/wp-cli.phar" "$OUT/wp-cli.phar"
  fi
  # La importación corre con el sitio ya en marcha (kit-cli.php, como kit-roundtrip/cli.php), no como paso «wp-cli»
  # del blueprint: durante el arranque ese paso se queda esperando y el servidor deja de responder.
  head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$OUT/kit-token.txt"
  cp "$A/kit-cli.php" "$OUT/kit-cli.php"
fi
cat > "$OUT/blueprint.json" <<JSON
{$LIBRARIES"steps":[$STEPS
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}$KIT_STEPS]}
JSON
MOUNTS=(--mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" --mount="$OUT:/wordpress/wp-content/probe")
[ "$SCOPE" = full ] && MOUNTS+=(--mount="$ROOT/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration")
fuser -k -n tcp "$PORT" >/dev/null 2>&1 || true
sleep 1
$CLI server --port="$PORT" --workers=1 --site-url="http://127.0.0.1:$PORT" --blueprint="$OUT/blueprint.json" "${MOUNTS[@]}" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
for i in $(seq 1 200); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then tail -20 "$OUT/server.log"; exit 1; fi
  curl -s -m 20 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/digi-marca/" 2>/dev/null | grep -q '^200$' && break
done
curl -s -m 20 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/digi-marca/" | grep -q '^200$' || { echo 'El servidor no sirvió la página de prueba'; tail -20 "$OUT/server.log"; exit 1; }
if [ "$STATE" = kit ]; then
  KIT_TOKEN="$(cat "$OUT/kit-token.txt")"
  KIT_ARGS='["digitalisimo-elements","kit","import","/wordpress/wp-content/probe/kits/'"$KIT_FIXTURE"'.zip","--user=admin","--format=json"]'
  curl -s -m 900 -o "$OUT/kit-import.json" --get --data-urlencode "token=$KIT_TOKEN" --data-urlencode "args=$KIT_ARGS" "http://127.0.0.1:$PORT/wp-content/probe/kit-cli.php"
  python3 -c 'import json,sys; r=json.load(open(sys.argv[1])); print("Importación del kit:", r.get("ms"), "ms"); sys.exit(1 if r.get("fatal") else 0)' "$OUT/kit-import.json" \
    || { echo "STATE=kit: la importación por WP-CLI falló:"; cat "$OUT/kit-import.json"; exit 1; }
  curl -s -m 120 -o /dev/null --get --data-urlencode "token=$KIT_TOKEN" --data-urlencode "phase=kit" "http://127.0.0.1:$PORT/wp-content/probe/kit-cli.php"
  [ -s "$OUT/kit.json" ] || { echo "STATE=kit: setup.php no anotó la sesión ni las páginas del kit"; cat "$OUT/setup-error.txt" 2>/dev/null; exit 1; }
fi
[ -f "$OUT/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$OUT/setup-error.txt"; exit 1; }
[ -f "$OUT/setup-warnings.txt" ] && { echo "Avisos de la preparación:"; cat "$OUT/setup-warnings.txt"; }
status=0
OUT="$OUT" SCOPE="$SCOPE" STATE="$STATE" ORIGINAL="$ORIGINAL" NODE_PATH="$PUPPETEER_PATH" node "$A/run.cjs" "http://127.0.0.1:$PORT" || status=$?
[ -s "$OUT/fatal.log" ] && { echo "Errores fatales:"; cat "$OUT/fatal.log"; status=1; }
# Red de seguridad, independiente de run.cjs: cualquier salida del servidor, redirección o correo al proveedor falla.
if [ -s "$OUT/http.jsonl" ]; then
  bad="$(grep -cE '"kind":"(salió|redirección|correo)"' "$OUT/http.jsonl" || true)"
  [ "$bad" -gt 0 ] && { echo "Servidor: $bad registros «salió», «redirección» o «correo» hacia un plugin de origen (detalle en $OUT/http.jsonl)."; status=1; }
fi
exit $status
