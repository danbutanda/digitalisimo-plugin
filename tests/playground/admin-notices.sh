#!/usr/bin/env bash
# Avisos de DIGITALÍSIMO en WordPress Playground y Chrome (queja del propietario del 2026-11-22: «se están mostrando
# alertas así y son molestas, no poder quitarlas; mándalas a las alertas globales de WP pero no las pongas en el
# contenido»). Los siete plugins activos y la sonda admin-notices/probe.php, que pone el aviso real de Tools
# (Cache_Notice, «la caché de objetos está devolviendo datos de otro sitio») sin escribir nada en wp-config.php. El
# conductor admin-notices/run.cjs comprueba en cada pantalla DIGITALÍSIMO la marca <hr class="wp-header-end">, que el
# aviso queda tras ella y antes del contenido, la X, que cerrado no vuelve al recargar ni en otras pantallas (en la red:
# cerrado en la red, tampoco en el sitio), que vuelve si cambia la situación y al pasar su plazo.
# Uso: bash tests/playground/admin-notices.sh   (en la PC: bash /var/tmp/digi-wt/pc-run.sh --traer-a <dir> <worktree> -- bash tests/playground/admin-notices.sh)
#   PHASES  «single network» (por defecto) · PHP 8.2 (o PHP=7.4) · OUT /var/tmp/digi-an · puertos 9781 (sitio) y 9782 (red)
# Deja $OUT/<fase>/result.txt y capturas; sale 0 si todo cuadra.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/admin-notices"
export OUT="${OUT:-/var/tmp/digi-an}"
export TMPDIR="${TMPDIR:-$OUT/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PHPV="${PHP:-8.2}"
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
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM

fail=0
for phase in ${PHASES:-single network}; do
  if [ "$phase" = network ]; then PORT=9782; host=playground.test; steps='{"step":"enableMultisite"},'; else PORT=9781; host="127.0.0.1:$PORT"; steps=''; fi
  dir="$OUT/$phase"
  rm -rf "$dir"; mkdir -p "$dir"
  cat > "$dir/blueprint.json" <<JSON
{"preferredVersions":{"php":"$PHPV","wp":"latest"},"steps":[$steps
{"step":"runPHP","code":"<?php require '/digi-an-src/setup.php';"}]}
JSON
  if fuser -n tcp "$PORT" >/dev/null 2>&1; then echo "== $phase: el puerto $PORT está ocupado"; fail=1; continue; fi
  MOUNTS=()
  for d in digitalisimo-seo digitalisimo-ia-tools digitalisimo-hosting digitalisimo-backups digitalisimo-tools digitalisimo-elements digitalisimo-migration; do
    site=$d; [ "$d" = digitalisimo-ia-tools ] && site=digitalisimo.chatbot
    MOUNTS+=("--mount=$ROOT/$d:/wordpress/wp-content/plugins/$site")
  done
  begun=$(date +%s)
  $CLI server --php="$PHPV" --port="$PORT" --workers=1 --site-url="http://$host" --blueprint="$dir/blueprint.json" \
    "${MOUNTS[@]}" --mount="$D/probe.php:/wordpress/wp-content/mu-plugins/digi-an-probe.php" \
    --mount="$D:/digi-an-src" --mount="$dir:/digi-an" > "$dir/server.log" 2>&1 &
  SERVER=$!
  ready=0
  for i in $(seq 1 300); do
    sleep 3
    kill -0 "$SERVER" 2>/dev/null || break
    [ -f "$dir/setup-error.txt" ] && break
    if [ -s "$dir/ready.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$PORT/wp-login.php")" = 200 ]; then ready=1; break; fi
  done
  if [ "$ready" != 1 ]; then
    echo "== $phase: el sitio no quedó listo"; cat "$dir/setup-error.txt" 2>/dev/null || true; tail -20 "$dir/server.log"
    stop_server; fail=1; continue
  fi
  echo "== $phase: listo en $(( $(date +%s) - begun )) s · $(cat "$dir/ready.json")"
  status=0
  NODE_PATH="$PUPPETEER_PATH" node "$D/run.cjs" "$PORT" "$host" "$dir" "$phase" || status=1
  [ -s "$dir/fatal.log" ] && { echo "Errores fatales:"; cat "$dir/fatal.log"; status=1; }
  [ "$status" = 0 ] || fail=1
  stop_server
done
exit $fail
