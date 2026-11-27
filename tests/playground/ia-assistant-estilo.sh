#!/usr/bin/env bash
# Capturas del Asistente IA junto a «Herramientas» de Tools y los ajustes de IA Tools, para comparar el estilo (pedido
# del propietario del 2026-11-26: «al asistente dale el mismo estilo de los plugins en colores y todo para que se vea
# igual todo»). WordPress Playground con IA Tools y Tools y Chrome en modo claro: el Asistente con una conversación, su
# bienvenida, a 390 px y con el menú plegado. No va en la CI; no llama a ninguna IA.
# Uso: PREFIX=antes|despues bash tests/playground/ia-assistant-estilo.sh   (OUT, por defecto /var/tmp/digi-st; PORT 9786)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/ia-assistant-estilo"
export OUT="${OUT:-/var/tmp/digi-st}"
export TMPDIR="${TMPDIR:-$OUT/tmp}"
PREFIX="${PREFIX:-captura}"
PORT="${PORT:-9786}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done || true)}"
if [ -z "$PUPPETEER_PATH" ]; then
  mkdir -p "$TMPDIR/pptr"
  npm install --silent --no-audit --no-fund --prefix "$TMPDIR/pptr" puppeteer-core@25.12.0 >/dev/null 2>&1 && PUPPETEER_PATH="$TMPDIR/pptr/node_modules"
fi
dir="$OUT"
rm -rf "$dir/probe"; mkdir -p "$dir/probe"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
SERVER=''
stop_server() { [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; SERVER=''; }
trap 'stop_server; rm -f "$dir/probe/token.txt"; playground_cleanup' EXIT
trap 'exit 130' INT TERM
cat > "$dir/blueprint.json" <<JSON
{"preferredVersions":{"php":"8.2","wp":"latest"},"steps":[{"step":"runPHP","code":"<?php require '/digi-st-src/setup.php';"}]}
JSON
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "El puerto $PORT está ocupado; usa PORT=…"; exit 1; fi
host="127.0.0.1:$PORT"
$CLI server --port="$PORT" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
  --mount="$ROOT/digitalisimo-ia-tools:/wordpress/wp-content/plugins/digitalisimo.chatbot" \
  --mount="$ROOT/digitalisimo-tools:/wordpress/wp-content/plugins/digitalisimo-tools" \
  --mount="$D/probe.php:/wordpress/wp-content/mu-plugins/digi-st-probe.php" \
  --mount="$D:/digi-st-src" --mount="$dir/probe:/digi-st" > "$dir/server.log" 2>&1 &
SERVER=$!
ready=0
for i in $(seq 1 300); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$dir/probe/setup-error.txt" ] && break
  if [ -s "$dir/probe/ready.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 "http://$host/wp-login.php")" = 200 ]; then ready=1; break; fi
done
[ "$ready" = 1 ] || { echo "== el sitio no quedó listo"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"; exit 1; }
NODE_PATH="${PUPPETEER_PATH:-}" node "$D/run.cjs" "$PORT" "$host" "$dir" "$PREFIX"
if grep -qE 'PHP Fatal error|Uncaught' "$dir/server.log"; then echo "== errores de PHP"; grep -E 'PHP Fatal error|Uncaught' "$dir/server.log" | head -5; exit 1; fi
echo "Capturas «$PREFIX» listas en $dir"
