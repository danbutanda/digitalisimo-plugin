#!/usr/bin/env bash
# Restauración real entre MySQL 8.0 y MariaDB 10.11 (tests/backups-db-compat-live/check.php) con servidores de verdad,
# sin root ni instalar nada: descarga los paquetes del servidor de la distribución (apt-get download), los desempaca en
# un temporal, arranca cada servidor con su socket y lo borra todo al terminar. Si no se pueden descargar (sin red o
# sin apt), lo dice y sale con 0: la prueba unitaria (tests/backups-db-compat.php) cubre la lógica sin servidores.
#
# Uso: bash tests/backups-db-compat-live.sh   (PHP_BIN elige el PHP; por defecto, php)
set -uo pipefail
PHP_BIN=${PHP_BIN:-php}
repo=$(cd "$(dirname "$0")/.." && pwd)
work=$(mktemp -d "${TMPDIR:-/tmp}/dc-live-XXXXXX")
pids=()
limpiar() {
  for pid in "${pids[@]}"; do kill "$pid" 2>/dev/null; done
  for pid in "${pids[@]}"; do for i in $(seq 1 20); do kill -0 "$pid" 2>/dev/null || break; sleep 0.5; done; done
  rm -rf "$work"
}
trap limpiar EXIT
cd "$work" || exit 1
if ! apt-get download mariadb-server-core mysql-server-core-8.0 libevent-pthreads-2.1-7t64 libprotobuf-lite32t64 >download.log 2>&1; then
  echo "OMITIDA: no se pudieron descargar MariaDB y MySQL (apt-get download): $(tail -1 download.log)"; exit 0
fi
for f in *.deb; do dpkg-deb -x "$f" root || exit 1; done
export LD_LIBRARY_PATH="$work/root/usr/lib/x86_64-linux-gnu${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
usr="$work/root/usr"
mkdir -p maria
"$usr/sbin/mariadbd" --no-defaults --basedir="$usr" --datadir="$work/maria" --socket="$work/maria.sock" --skip-networking --skip-grant-tables --lc-messages-dir="$usr/share/mariadb" >maria.log 2>&1 &
pids+=($!)
"$usr/sbin/mysqld" --no-defaults --initialize-insecure --basedir="$usr" --datadir="$work/mysql" --lc-messages-dir="$usr/share/mysql" >mysql-init.log 2>&1 || { echo "FALLA: no se pudo preparar MySQL"; tail -5 mysql-init.log; exit 1; }
"$usr/sbin/mysqld" --no-defaults --basedir="$usr" --datadir="$work/mysql" --socket="$work/mysql.sock" --skip-networking --mysqlx=OFF --secure-file-priv="$work" --pid-file="$work/mysql.pid" --lc-messages-dir="$usr/share/mysql" >mysql.log 2>&1 &
pids+=($!)
for i in $(seq 1 60); do [ -S "$work/maria.sock" ] && [ -S "$work/mysql.sock" ] && break; sleep 1; done
[ -S "$work/maria.sock" ] || { echo "FALLA: MariaDB no arrancó"; tail -5 maria.log; exit 1; }
[ -S "$work/mysql.sock" ] || { echo "FALLA: MySQL no arrancó"; tail -5 mysql.log; exit 1; }
cd "$repo" || exit 1
DC_MYSQL_SOCK="$work/mysql.sock" DC_MARIA_SOCK="$work/maria.sock" "$PHP_BIN" tests/backups-db-compat-live/check.php
