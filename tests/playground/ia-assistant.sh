#!/usr/bin/env bash
# Asistente IA de DIGITALÍSIMO IA Tools (docs/ASSISTANT.md) de punta a punta en WordPress Playground y Chrome, con
# DIGITALÍSIMO Elements y su MCP activos, primero en un sitio individual y después en una red (sitio 2 en /sitio2/).
# La sonda (ia-assistant/probe.php, mu-plugin) contesta por api.openai.com con el formato real de chat/completions y
# llamadas a herramientas, y registra tres abilities de prueba (leer, crear y borrar). El conductor
# (ia-assistant/run.cjs) comprueba:
#   T  que el Asistente lista exactamente las herramientas del MCP (tools/list de /wp-json/elementor/mcp más lo que
#      descubre el servidor genérico del adaptador, por JSON-RPC con contraseña de aplicación) y que son las que recibe
#      el modelo;
#   C  en Chrome: menú, nada del Asistente en el Escritorio, respuesta simulada, leer directo como dato y sin claves,
#      crear con confirmación y en borrador, borrar con confirmación explícita, bitácora, apariencia Modern y oscura que
#      se conserva, la conversación al recargar, un atajo /herramienta sin modelo y la consola sin errores;
#   O  digitalisimo-ia/open-assistant por MCP (URL de ese sitio, mensaje escrito y nunca enviado);
#   N  en la red: sin menú en el escritorio de la red, el sitio 2 con su MCP, sus herramientas y su historial.
# Uso: bash tests/playground/ia-assistant.sh   (en la PC: bash /var/tmp/digi-wt/pc-run.sh <worktree> -- bash tests/playground/ia-assistant.sh)
#   PHPS     versiones de PHP de Playground (por defecto «8.2»; p. ej. PHPS="7.4 8.2 8.5")
#   PHASES   fases (por defecto «single network»)
#   OUT      resultados (por defecto /var/tmp/digi-asis) · PORT 9741 (sitio) y 9742 (red): el bloque 9740–9749 es de este arnés
# Deja $OUT/<fase>-php<versión>/result.txt y result.json, y $OUT/summary.json. Al terminar borra la contraseña de
# aplicación del ensayo.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/ia-assistant"
export OUT="${OUT:-/var/tmp/digi-asis}"
export TMPDIR="${TMPDIR:-$OUT/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done || true)}"
if [ -z "$PUPPETEER_PATH" ]; then
  mkdir -p "$TMPDIR/pptr"
  npm install --silent --no-audit --no-fund --prefix "$TMPDIR/pptr" puppeteer-core@25.12.0 >/dev/null 2>&1 && PUPPETEER_PATH="$TMPDIR/pptr/node_modules"
fi
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
SERVER=''
PORT=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  [ -n "$PORT" ] && for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''
}
trap 'stop_server; rm -f "$OUT"/*/probe/secrets.json "$OUT"/*/probe/token.txt; playground_cleanup' EXIT
trap 'exit 130' INT TERM

fail=0
summary='[]'
start=$(date +%s)
for php in ${PHPS:-8.2}; do
  for phase in ${PHASES:-single network}; do
    if [ "$phase" = network ]; then PORT=9742; host=playground.test; steps='{"step":"enableMultisite"},'; else PORT=9741; host="127.0.0.1:$PORT"; steps=''; fi
    dir="$OUT/$phase-php$php"
    rm -rf "$dir"; mkdir -p "$dir/probe"
    head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$dir/probe/token.txt"
    cat > "$dir/blueprint.json" <<JSON
{"preferredVersions":{"php":"$php","wp":"latest"},"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"WP_ENVIRONMENT_TYPE":"local"}},$steps
{"step":"runPHP","code":"<?php require '/digi-asis-src/setup.php';"}]}
JSON
    if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "== $phase PHP $php: el puerto $PORT está ocupado"; fail=1; continue; fi
    begun=$(date +%s)
    $CLI server --php="$php" --port="$PORT" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
      --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
      --mount="$ROOT/digitalisimo-ia-tools:/wordpress/wp-content/plugins/digitalisimo.chatbot" \
      --mount="$D/probe.php:/wordpress/wp-content/mu-plugins/digi-asis-probe.php" \
      --mount="$D:/digi-asis-src" \
      --mount="$dir/probe:/digi-asis" > "$dir/server.log" 2>&1 &
    SERVER=$!
    ready=0
    for i in $(seq 1 300); do
      sleep 3
      kill -0 "$SERVER" 2>/dev/null || break
      [ -f "$dir/probe/setup-error.txt" ] && break
      if [ -s "$dir/probe/ready.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$PORT/wp-login.php")" = 200 ]; then ready=1; break; fi
    done
    if [ "$ready" != 1 ]; then
      echo "== $phase PHP $php: el sitio no quedó listo"; cat "$dir/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"
      stop_server; fail=1; continue
    fi
    echo "== $phase PHP $php: listo en $(( $(date +%s) - begun )) s · $(cat "$dir/probe/ready.json")"
    status=0
    NODE_PATH="$PUPPETEER_PATH" node "$D/run.cjs" "$PORT" "$host" "$dir" "$phase" || status=1
    if grep -qE 'PHP Fatal error|Uncaught' "$dir/server.log"; then echo "== errores de PHP en el servidor"; grep -E 'PHP Fatal error|Uncaught' "$dir/server.log" | head -5; status=1; fi
    if [ -s "$dir/probe/php-errors.log" ]; then echo "== avisos de PHP de IA Tools"; sort "$dir/probe/php-errors.log" | uniq -c | head -10; status=1; else echo "== ningún aviso de PHP de IA Tools"; fi
    [ "$status" = 0 ] || fail=1
    summary="$(node -e '
const [list, phase, php, file, seconds, status] = process.argv.slice(1);
const all = JSON.parse(list); let r = {}; try { r = JSON.parse(require("fs").readFileSync(file, "utf8")); } catch {}
all.push({ phase, php, status: status === "0" ? "ok" : "falla", checks: r.checks || 0, failures: r.failures ?? null, seconds: Number(seconds) });
console.log(JSON.stringify(all));' "$summary" "$phase" "$php" "$dir/result.json" "$(( $(date +%s) - begun ))" "$status")"
    stop_server
    sleep 2
  done
done
node -e 'require("fs").writeFileSync(process.argv[1] + "/summary.json", JSON.stringify({ runs: JSON.parse(process.argv[2]), seconds: Number(process.argv[3]) }, null, 1))' "$OUT" "$summary" "$(( $(date +%s) - start ))"
[ $fail -eq 0 ] || { echo 'Asistente IA en Playground: ensayo con fallos'; exit 1; }
echo "Asistente IA validado en Playground y Chrome en $(( $(date +%s) - start )) s."
