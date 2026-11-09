#!/usr/bin/env bash
# Migración en un clic de DIGITALÍSIMO Backups entre dos WordPress Playground (SQLite, PHP 8.5 sin libsodium nativo: el
# paquete se cifra y se descifra con el sodium_compat de WordPress), sin Chrome. Por cada modo arranca dos servidores en
# dos puertos: A (origen) y B (destino), cada uno con su copia del plugin, su almacén privado y sus propias AUTH_KEY y
# SECURE_AUTH_KEY, y check.mjs hace por HTTP lo que haría el administrador con el navegador (ver check.mjs):
#   single     sitio individual en http://127.0.0.1:<puerto> (A con DIGITALÍSIMO Elements y una página suya, si
#              ELEMENTS incluye «single»)
#   multisite  red por subdirectorios en http://origen.test y http://destino.test (servidas en dos puertos; un mu-plugin
#              de laboratorio resuelve origen.test hacia el puerto de A), A con el subsitio «uno»
# Los dos sitios usan «Permitir HTTP» (laboratorio). La carpeta de resultados se monta fuera de /wordpress, y el plugin
# y Elements son copias: una restauración nunca escribe en el repositorio.
# Uso: bash tests/playground/backups-migrate.sh (en el servidor de desarrollo, por el semáforo de corridas pesadas y
#      con systemd-run --user)
#   OUT, TMPDIR   resultados (por defecto /var/tmp/digi-bk/backups-migrate) y temporales de Playground
#   MODES         modos a correr (por defecto «single multisite»)
#   ELEMENTS      modos en los que A lleva Elements (por defecto «single»: con sodium_compat, sus ~32 MB comprimidos
#                 suman unos 10 minutos de cifrado y otros tantos de descifrado)
#   PORT          primer puerto (por defecto 9481; cada modo usa dos)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
D="$ROOT/tests/playground/backups-migrate"
export OUT="${OUT:-/var/tmp/digi-bk/backups-migrate}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PORT="${PORT:-9481}"
ELEMENTS="${ELEMENTS:-single}"
PIDS=()
PORTS=()
stop_servers() {
  for pid in "${PIDS[@]:-}"; do [ -n "$pid" ] && kill "$pid" 2>/dev/null || true; done
  for port in "${PORTS[@]:-}"; do [ -n "$port" ] && for i in 1 2 3; do fuser -k -n tcp "$port" >/dev/null 2>&1 || break; sleep 1; done; done
  PIDS=(); PORTS=()
}
trap 'stop_servers; playground_cleanup' EXIT
trap 'exit 130' INT TERM

start=$(date +%s)
fail=0
offset=0
TOKEN="$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')"
for mode in ${MODES:-single multisite}; do
  dir="$OUT/$mode"
  rm -rf "$dir"; mkdir -p "$dir"
  port_a=$((PORT + offset)); port_b=$((PORT + offset + 1)); offset=$((offset + 2))
  with_elements=0; case " $ELEMENTS " in *" $mode "*) with_elements=1 ;; esac
  case "$mode" in
    single) host_a="127.0.0.1:$port_a"; host_b="127.0.0.1:$port_b"; extra=''; hosts='{}' ;;
    multisite) host_a='origen.test'; host_b='destino.test'; extra='{"step":"enableMultisite"},'; hosts="{\"origen.test\":\"127.0.0.1:$port_a\",\"destino.test\":\"127.0.0.1:$port_b\"}" ;;
    *) echo "Modo desconocido: $mode"; exit 2 ;;
  esac
  printf '{"token":"%s","A":{"port":%s,"host":"%s","home":"http://%s","password":"origen-admin-%s"},"B":{"port":%s,"host":"%s","home":"http://%s","password":"destino-admin-%s"}}\n' \
    "$TOKEN" "$port_a" "$host_a" "$host_a" "${TOKEN:0:8}" "$port_b" "$host_b" "$host_b" "${TOKEN:8:8}" > "$dir/conf.json"
  ok=1
  for site in A B; do
    sd="$dir/$site"
    mkdir -p "$sd/probe" "$sd/web" "$sd/store"
    chmod 700 "$sd/store"
    cp -a "$ROOT/digitalisimo-backups" "$sd/plugin"
    cp "$D/setup.php" "$sd/probe/"
    cp "$D/probe.php" "$sd/web/"
    echo "$TOKEN" > "$sd/web/token.txt"
    echo "$hosts" > "$sd/probe/hosts.json"
    role=origen; [ "$site" = B ] && role=destino
    elements=false; mounts=()
    if [ "$site" = A ] && [ "$with_elements" = 1 ]; then
      cp -a "$ROOT/digitalisimo-elements" "$sd/elements"
      elements=true
      mounts=(--mount="$sd/elements:/wordpress/wp-content/plugins/digitalisimo-elements")
    fi
    password="$(node -e "console.log(require('$dir/conf.json').$site.password)")"
    printf '{"role":"%s","password":"%s","elements":%s}\n' "$role" "$password" "$elements" > "$sd/probe/site.json"
    salt="$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')"
    cat > "$sd/blueprint.json" <<JSON
{"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{"DIGITALISIMO_BACKUPS_STORAGE_DIR":"/digi-store","WP_ENVIRONMENT_TYPE":"local","AUTH_KEY":"auth-$site-$salt","SECURE_AUTH_KEY":"secure-$site-$salt"}},$extra
{"step":"runPHP","code":"<?php require '/digi-probe/setup.php';"}]}
JSON
    port=$port_a; host=$host_a; [ "$site" = B ] && { port=$port_b; host=$host_b; }
    if fuser -n tcp "$port" >/dev/null 2>&1; then echo "== $mode: el puerto $port está ocupado; usa PORT=…"; ok=0; break; fi
    $CLI server --port="$port" --workers=1 --site-url="http://$host" --blueprint="$sd/blueprint.json" \
      --mount="$sd/plugin:/wordpress/wp-content/plugins/digitalisimo-backups" \
      --mount="$sd/web:/wordpress/wp-content/digi-web" \
      --mount="$sd/probe:/digi-probe" \
      --mount="$sd/store:/digi-store" "${mounts[@]}" > "$sd/server.log" 2>&1 &
    PIDS+=($!); PORTS+=("$port")
  done
  [ "$ok" = 1 ] || { stop_servers; fail=1; continue; }
  begun=$(date +%s)
  for site in A B; do
    sd="$dir/$site"; port=$port_a; host=$host_a; [ "$site" = B ] && { port=$port_b; host=$host_b; }
    ready=0
    for i in $(seq 1 400); do
      sleep 3
      [ -f "$sd/probe/setup-error.txt" ] && break
      if [ -s "$sd/probe/setup.json" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' -m 60 -H "Host: $host" "http://127.0.0.1:$port/?rest_route=%2F")" = 200 ]; then ready=1; break; fi
    done
    if [ "$ready" != 1 ]; then echo "== $mode: $site no quedó listo"; cat "$sd/probe/setup-error.txt" 2>/dev/null || true; tail -20 "$sd/server.log"; ok=0; fi
  done
  if [ "$ok" = 1 ]; then
    echo "== $mode: A y B listos en $(( $(date +%s) - begun )) s (puertos $port_a y $port_b; Elements en A: $with_elements)"
    node "$D/check.mjs" "$mode" "$dir" || fail=1
    for site in A B; do
      grep -qE 'Fatal error|Uncaught' "$dir/$site/server.log" && { echo "== $mode: errores de PHP en $site"; grep -E 'Fatal error|Uncaught' "$dir/$site/server.log" | head -5; fail=1; }
      ls -laR "$dir/$site/store" > "$dir/$site/store.txt" 2>&1 || true
    done
  else
    fail=1
  fi
  stop_servers
  sleep 2
done
[ $fail -eq 0 ] || { echo 'Ensayo de migración de Backups con fallos'; exit 1; }
echo "Migración de Backups validada en Playground en $(( $(date +%s) - start )) s."
