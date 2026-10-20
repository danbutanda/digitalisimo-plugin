#!/usr/bin/env bash
# A/B del tema DIGITALÍSIMO frente a Hello Elementor 3.5.1 con el mismo contenido (Playground en modo servidor, sitio
# simple, Chrome sin interfaz). Por escenario (S1 clásico, S2 constructor de temas, S3 cabecera dinámica con S3c por
# cron y S3h todo oculto, sin cabecera y con el pie vacío, S4 hijo de Hello, S5 sin SEO): A captura con Hello, B cambia
# al tema y compara cada URL en tres anchos (estado, firma, meta description, h1, cajas, desplazamiento, consola,
# píxeles, hojas, nodos, proveedores, estilos calculados, barra lateral, enlace de salto y su visibilidad con el
# foco), C revierte y exige las firmas, mods y ajustes de A, y C2 vuelve
# al tema. S1 añade D (modo base) y F2 (el asistente no reinstala Hello); S2 y S3, F (vista previa del editor); al
# final, E desactiva el constructor. Diferencias aceptadas: theme-switch/known.json. Resultado: $OUT/theme-switch.json
# y capturas $OUT/shots/{A,B,D}-<escenario>-<url>-<ancho>.png.
# El tema nunca se monta desde el repositorio: se construye con scripts/build-packages.sh y se monta la copia extraída.
# Uso: bash tests/playground/theme-switch.sh
#   WOO=1|0         WooCommerce instalado (1 por defecto y obligatorio para la aceptación; 0 sólo para corridas rápidas)
#   SCENARIOS=S1,S3 escenarios (por defecto S1,S2,S3,S3c,S3h,S4,S5); SKIP_E=1 omite la fase E
#   QUICK=1         menos URL y anchos (iteración local; no vale para la aceptación)
#   OUT, PORT       carpeta de resultados (/tmp/digi-theme-switch) y puerto del servidor (9453)
#   HELLO_ZIP       ZIP de Hello 3.5.1 (por defecto reference/hello-elementor.3.5.1.zip, también el del worktree principal)
#   MODE=measure BASE=<url> [PATHS=/,/blog/] [RUNS=5]   medición en staging: sólo con autorización, sin Playground
#                   y sin cambiar el tema (TTFB, LCP y CLS en mediana, en frío y en caliente; bytes por recurso; nodos)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/tmp/digi-theme-switch}"
PORT="${PORT:-9453}"
WOO="${WOO:-1}"
MODE="${MODE:-ab}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
T="$ROOT/tests/playground/theme-switch"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
mkdir -p "$OUT"
# Playground copia el sitio en $TMPDIR (unos 400 MB) y Chrome guarda allí su perfil: con /tmp en tmpfs casi lleno las
# escrituras fallan con ENOSPC/EDQUOT («Unknown system error -122», «errno=28») y la corrida da falsos fallos.
for dir in "${TMPDIR:-/tmp}" "$OUT"; do
  free_kb="$(df -Pk "$dir" 2>/dev/null | awk 'NR == 2 { print $4 }')"
  [ -z "$free_kb" ] || [ "$free_kb" -ge 2097152 ] || echo "Aviso: $dir tiene $((free_kb / 1024)) MB libres; exportar TMPDIR y OUT en un disco con 2 GB o más."
done

if [ "$MODE" = measure ]; then
  [ -n "${BASE:-}" ] || { echo 'MODE=measure exige BASE=<url del sitio de staging> (y autorización expresa)'; exit 1; }
  trap playground_cleanup EXIT
  OUT="$OUT" ROOT="$ROOT" MODE=measure BASE="$BASE" PATHS="${PATHS:-/}" RUNS="${RUNS:-5}" NODE_PATH="$PUPPETEER_PATH" node "$T/run.cjs"
  exit $?
fi
[ "$MODE" = ab ] || { echo 'MODE debe ser ab o measure'; exit 1; }

# Hello 3.5.1 de referencia (no se versiona): se copia al sitio de prueba, nunca se instala en el repositorio.
HELLO_ZIP="${HELLO_ZIP:-$ROOT/reference/hello-elementor.3.5.1.zip}"
if [ ! -f "$HELLO_ZIP" ]; then
  main_root="$(dirname "$(git -C "$ROOT" rev-parse --path-format=absolute --git-common-dir)")"
  HELLO_ZIP="$main_root/reference/hello-elementor.3.5.1.zip"
fi
[ -f "$HELLO_ZIP" ] || { echo "Falta reference/hello-elementor.3.5.1.zip (o HELLO_ZIP)"; exit 1; }
sha256sum "$HELLO_ZIP" | grep -q '^c166fdcb091a' || { echo "$HELLO_ZIP no es Hello Elementor 3.5.1"; exit 1; }

rm -rf "$OUT/shots" "$OUT/pkg" "$OUT/themes" "$OUT/child"
rm -f "$OUT"/*.jsonl "$OUT/fatal.log" "$OUT/setup-error.txt" "$OUT/theme-switch.json" "$OUT/token"
# Paquete del tema con su guarda (THEME PACKAGE CHECK) y copia extraída fuera del repositorio (RL-6).
bash "$ROOT/scripts/build-packages.sh" "$OUT/pkg" digitalisimo-theme > "$OUT/build.log" 2>&1 || { cat "$OUT/build.log"; exit 1; }
grep -q 'THEME PACKAGE CHECK: PASS' "$OUT/build.log" || { echo 'El paquete del tema no pasó su guarda:'; cat "$OUT/build.log"; exit 1; }
THEME_ZIP="$(ls "$OUT"/pkg/digitalisimo-theme-*.zip | head -1)"
mkdir -p "$OUT/themes" && unzip -q "$THEME_ZIP" -d "$OUT/themes"
cp "$HELLO_ZIP" "$OUT/hello.zip"
cp -r "$T/child" "$OUT/child"
cp "$T/setup.php" "$T/mu-ab.php" "$T/mu-fragment.php" "$T/dep.css" "$OUT/"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$OUT/token"
TOKEN="$(cat "$OUT/token")"

WOO_STEP=''
[ "$WOO" = 1 ] && WOO_STEP='{"step":"installPlugin","pluginData":{"resource":"wordpress.org/plugins","slug":"woocommerce"},"options":{"activate":true}},'
cat > "$OUT/blueprint.json" <<JSON
{"steps":[$WOO_STEP
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}]}
JSON
# El servidor anterior puede seguir escuchando (el proceso hijo de npx sobrevive a su padre): se libera el puerto.
fuser -k -n tcp "$PORT" >/dev/null 2>&1 || true
sleep 1
$CLI server --port="$PORT" --workers=1 --site-url="http://127.0.0.1:$PORT" --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$ROOT/digitalisimo-seo:/wordpress/wp-content/plugins/digitalisimo-seo" \
  --mount="$ROOT/digitalisimo-tools:/wordpress/wp-content/plugins/digitalisimo-tools" \
  --mount="$OUT/themes/digitalisimo-theme:/wordpress/wp-content/themes/digitalisimo-theme" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
ready() { curl -s -m 30 "http://127.0.0.1:$PORT/?digi-ab=ping&token=$TOKEN" 2>/dev/null | grep -q '"ready":[1-9]'; }
for i in $(seq 1 240); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then tail -20 "$OUT/server.log"; exit 1; fi
  [ -f "$OUT/setup-error.txt" ] && break
  ready && break
done
[ -f "$OUT/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$OUT/setup-error.txt"; exit 1; }
ready || { echo 'El servidor no terminó la preparación'; tail -20 "$OUT/server.log"; exit 1; }
status=0
OUT="$OUT" ROOT="$ROOT" WOO="$WOO" SCENARIOS="${SCENARIOS:-}" QUICK="${QUICK:-}" SKIP_E="${SKIP_E:-}" NODE_PATH="$PUPPETEER_PATH" \
  node "$T/run.cjs" "http://127.0.0.1:$PORT" || status=$?
[ -s "$OUT/fatal.log" ] && { echo "Errores fatales:"; cat "$OUT/fatal.log"; status=1; }
exit $status
