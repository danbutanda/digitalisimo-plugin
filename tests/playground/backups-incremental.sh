#!/usr/bin/env bash
# Incrementales de DIGITALÍSIMO Backups en WordPress Playground (SQLite, php-wasm), en un WordPress real: escenarios en
# backups-incremental/probe.php.
#   single  completo → cambios (añadir, modificar y borrar archivos y filas) → incremental → más cambios → incremental;
#           restaurar cada punto (y el completo) por la pantalla y comprobar con huellas de archivos y tablas que el sitio
#           queda exactamente como en ese punto, también los borrados; un eslabón que falta o alterado se rechaza sin
#           tocar nada; retención tras varios días simulados; espacio ahorrado frente a respaldos completos.
#   multisite  la cadena de la red completa (P1-P3) desde la administración de red.
#   remote  la misma cadena hacia un SFTP real (OpenSSH sin privilegios que levanta este arnés en 127.0.0.1) con «sólo
#           en el remoto»; «Traer al almacén» del último punto trae su cadena y se restaura idéntico.
# Uso: bash tests/playground/backups-incremental.sh (en la PC, por pc-run.sh; nunca en producción)
#   OUT, TMPDIR  resultados (por defecto /var/tmp/digi-bk/backups-incremental) y temporales de Playground
#   MODES        «single», «multisite» y/o «remote» (por defecto los tres)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export OUT="${OUT:-/var/tmp/digi-bk/backups-incremental}"
export TMPDIR="${TMPDIR:-/var/tmp/digi-bk/tmp}"
mkdir -p "$OUT" "$TMPDIR"
. "$ROOT/tests/playground/playground-cleanup.sh"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
KEYS='"AUTH_KEY":"playground-auth-key-0123456789abcdef0123456789abcdef","SECURE_AUTH_KEY":"playground-secure-auth-key-0123456789abcdef0123456789"'
SSHD_PID=''
stop_sshd() { [ -n "$SSHD_PID" ] && kill "$SSHD_PID" 2>/dev/null || true; SSHD_PID=''; }
trap 'stop_sshd; playground_cleanup' EXIT
start=$(date +%s)
fail=0
for mode in ${MODES:-single multisite remote}; do
  dir="$OUT/$mode"
  rm -rf "$dir"; mkdir -p "$dir/probe"
  cp -a "$ROOT/digitalisimo-backups" "$dir/plugin"
  cp "$ROOT/tests/playground/backups-incremental/probe.php" "$dir/probe/probe.php"
  echo "$mode" > "$dir/probe/mode"
  if [ "$mode" = remote ]; then
    if [ ! -x /usr/sbin/sshd ] || ! command -v ssh-keygen >/dev/null; then echo "== remote: sin sshd o ssh-keygen"; fail=1; continue; fi
    k="$dir/sshd"; mkdir -p "$k" "$dir/remote"; chmod 700 "$k" "$dir/remote"
    ssh-keygen -q -t ed25519 -N '' -f "$k/host_ed25519"
    ssh-keygen -q -t ed25519 -N '' -C respaldos -f "$k/user_ed25519"
    cp "$k/user_ed25519.pub" "$k/authorized_keys"; chmod 600 "$k/authorized_keys"
    port=$(python3 -c 'import socket; s=socket.socket(); s.bind(("127.0.0.1",0)); print(s.getsockname()[1]); s.close()')
    printf 'Port %s\nListenAddress 127.0.0.1\nHostKey %s/host_ed25519\nPidFile %s/sshd.pid\nAuthorizedKeysFile %s/authorized_keys\nPasswordAuthentication no\nKbdInteractiveAuthentication no\nUsePAM no\nStrictModes no\nSubsystem sftp internal-sftp\nLogLevel ERROR\n' "$port" "$k" "$k" "$k" > "$k/sshd_config"
    /usr/sbin/sshd -D -e -f "$k/sshd_config" > "$k/sshd.log" 2>&1 &
    SSHD_PID=$!
    for i in $(seq 1 50); do (exec 3<>/dev/tcp/127.0.0.1/$port) 2>/dev/null && break; sleep 0.2; done
    fp="SHA256:$(awk '{print $2}' "$k/host_ed25519.pub" | base64 -d | openssl dgst -sha256 -binary | base64 | tr -d '=')"
    python3 -I - "$dir/probe/config.json" "$port" "$(id -un)" "$dir/remote" "$k/user_ed25519" "$fp" <<'PY'
import json, sys
out, port, user, remote, key, fp = sys.argv[1:7]
json.dump({'host': '127.0.0.1', 'port': port, 'user': user, 'path': remote, 'key': open(key).read(), 'pub': open(key + '.pub').read(), 'fingerprint': fp}, open(out, 'w'))
PY
    chmod 600 "$dir/probe/config.json"
  fi
  multi=''
  [ "$mode" = multisite ] && multi='{"step":"enableMultisite"},'
  cat > "$dir/blueprint.json" <<JSON
{"features":{"networking":true},"steps":[{"step":"defineWpConfigConsts","method":"rewrite-wp-config","consts":{$KEYS,"WP_ENVIRONMENT_TYPE":"local"}},$multi
{"step":"runPHP","code":"<?php require '/digitalisimo-probe/probe.php';"}]}
JSON
  begun=$(date +%s)
  $CLI run-blueprint --site-url=http://incremental.test --blueprint="$dir/blueprint.json" \
    --mount="$dir/plugin:/wordpress/wp-content/plugins/digitalisimo-backups" \
    --mount="$dir/probe:/digitalisimo-probe" > "$dir/playground.log" 2>&1 || { echo "== $mode: Playground terminó con error"; tail -20 "$dir/playground.log"; fail=1; }
  stop_sshd
  rm -f "$dir/probe/config.json"
  echo "== $mode ($(( $(date +%s) - begun )) s)"
  cat "$dir/probe/result.txt" 2>/dev/null || true
  if ! grep -q '^TERMINADO' "$dir/probe/result.txt" 2>/dev/null || grep -qE '^FALLA|Fatal error|Uncaught' "$dir/probe/result.txt" "$dir/playground.log"; then
    echo "== $mode con fallos"; tail -20 "$dir/playground.log"; fail=1
  fi
  rm -rf "$dir/plugin"
done
[ $fail -eq 0 ] || { echo 'Ensayo de incrementales con fallos'; exit 1; }
echo "Incrementales validados en Playground en $(( $(date +%s) - start )) s."
