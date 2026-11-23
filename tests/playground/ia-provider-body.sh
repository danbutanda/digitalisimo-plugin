#!/usr/bin/env bash
# El cuerpo EXACTO que el Asistente IA de DIGITALÍSIMO IA Tools manda a Anthropic, OpenAI y Google en un sitio de cliente
# con tienda (WooCommerce 11.2.1 fijado, DIGITALÍSIMO Elements con su MCP y el de WooCommerce en sólo lectura, IA Tools
# y los demás plugins del repositorio), en WordPress Playground y sin red: la sonda (ia-provider-body/probe.php) manda
# «Que puedes hacer por mi en este sitio web ?» por la ruta real del Asistente y captura en pre_http_request lo que
# saldría hacia el proveedor. ia-provider-body/validate.mjs lo valida contra las reglas de cada API (Anthropic:
# input_schema objeto JSON Schema 2020-12 sin oneOf/anyOf/allOf en la raíz, nombres de propiedad, required como lista,
# items y additionalProperties como objeto o booleano, enum no vacío, tipos válidos y nombres de herramienta únicos).
# Regresión del «Claude no respondió (HTTP 400).» de plomerito (IA Tools 1.0.70). Después comprueba el reintento: el
# proveedor contesta 400 sobre una herramienta y el Asistente la quita, reintenta y responde.
# Uso: bash tests/playground/ia-provider-body.sh   (en la PC: bash /var/tmp/digi-wt/pc-run.sh <worktree> -- bash tests/playground/ia-provider-body.sh)
#   OUT, TMPDIR  resultados (por defecto /var/tmp/digi-ia-pb) · PORT 9751 (bloque 9750–9759 de este arnés)
#   WOO_ZIP      WooCommerce (tests/playground/woocommerce-pin.sh); obligatorio
# Deja $OUT/<proveedor>.json (cuerpo, eventos y esquemas originales) y $OUT/result.txt.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/ia-provider-body"
export OUT="${OUT:-/var/tmp/digi-ia-pb}"
export TMPDIR="${TMPDIR:-$OUT/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9751}"
case "$PORT" in 975[0-9]) ;; *) echo "ia-provider-body.sh usa el bloque 9750–9759: $PORT es de otro arnés"; exit 1;; esac
SERVER=''
stop_server() {
  [ -n "$SERVER" ] && kill "$SERVER" 2>/dev/null || true
  for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done
  SERVER=''
}
trap 'stop_server; rm -rf "$OUT/probe/token.txt" "$OUT/woocommerce"; playground_cleanup' EXIT
trap 'exit 130' INT TERM

. "$ROOT/tests/playground/woocommerce-pin.sh"
WOO_REQUIRE=1 woo_pin "$OUT/woocommerce" "$OUT/woocommerce.json" || exit 1
host="127.0.0.1:$PORT"
rm -rf "$OUT/probe"; mkdir -p "$OUT/probe"
cp "$D/setup.php" "$D/probe.php" "$OUT/probe/"
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n' > "$OUT/probe/token.txt"
cat > "$OUT/blueprint.json" <<JSON
{"preferredVersions":{"php":"${PHP_VERSION_PG:-8.2}","wp":"latest"},"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"WP_ENVIRONMENT_TYPE":"local"}},
{"step":"runPHP","code":"<?php require '/digi-pb/setup.php';"}]}
JSON
if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "El puerto $PORT está ocupado"; exit 1; fi
start=$(date +%s)
mounts=()
for p in digitalisimo-elements digitalisimo-seo digitalisimo-tools digitalisimo-backups; do
  mounts+=("--mount=$ROOT/$p:/wordpress/wp-content/plugins/$p")
done
$CLI server --port="$PORT" --workers=1 --site-url="http://$host" --blueprint="$OUT/blueprint.json" \
  "${mounts[@]}" \
  --mount="$ROOT/digitalisimo-ia-tools:/wordpress/wp-content/plugins/digitalisimo.chatbot" \
  --mount="$OUT/probe:/digi-pb" \
  "$(woo_mount)" > "$OUT/server.log" 2>&1 &
SERVER=$!
ready=0
for i in $(seq 1 400); do
  sleep 3
  kill -0 "$SERVER" 2>/dev/null || break
  [ -f "$OUT/probe/setup-error.txt" ] && break
  if [ -s "$OUT/probe/ready.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 "http://$host/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
done
if [ "$ready" != 1 ]; then
  echo "El sitio no quedó listo"; cat "$OUT/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$OUT/server.log"; exit 1
fi
echo "Sitio listo en $(( $(date +%s) - start )) s · $(cat "$OUT/probe/ready.json")"
token="$(cat "$OUT/probe/token.txt")"
for provider in anthropic openai google; do
  curl -s -m 300 -H "X-Digi-PB: $token" "http://$host/?rest_route=%2Fdigi-pb%2Fv1%2Fbody&provider=$provider" > "$OUT/$provider.json"
done
curl -s -m 300 -H "X-Digi-PB: $token" "http://$host/?rest_route=%2Fdigi-pb%2Fv1%2Fbody&provider=anthropic&fail=1" > "$OUT/anthropic-retry.json"
status=0
node "$D/validate.mjs" "$OUT" | tee "$OUT/result.txt" || status=1
if grep -qE 'PHP Fatal error|Uncaught' "$OUT/server.log"; then echo "Errores de PHP en el servidor"; grep -E 'PHP Fatal error|Uncaught' "$OUT/server.log" | head -5; status=1; fi
[ $status -eq 0 ] || { echo 'Cuerpo del Asistente IA: ensayo con fallos'; exit 1; }
echo "Cuerpo del Asistente IA validado en Playground en $(( $(date +%s) - start )) s."
