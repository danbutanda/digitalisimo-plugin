#!/usr/bin/env bash
# Copia de phpseclib 3 (SFTP sin la extensión ssh2, I5) dentro de DIGITALÍSIMO Backups, con prefijo propio.
#
# - Versiones fijadas por commit y SHA-256 del zipball: phpseclib 3.0.58 (MIT; PHP >= 5.6, así que vale con el mínimo
#   7.4 del plugin) y paragonie/constant_time_encoding 2.8.2 (MIT; la rama 3.x exige PHP 8). random_compat no hace
#   falta con PHP 7.
# - Prefijo: `phpseclib3\` → `DigitalisimoBackups\Vendor\phpseclib3\` y `ParagonIE\ConstantTime\` →
#   `DigitalisimoBackups\Vendor\ParagonIE\ConstantTime\`, también dentro de las cadenas con que phpseclib arma nombres
#   de clase (formatos de clave, curvas, motores de BigInteger). Así otro plugin que cargue su propio phpseclib (otra
#   versión, otro autoload de Composer) nunca choca con éste ni lo sustituye.
# - Resultado en digitalisimo-backups/includes/lib/ con sus licencias; lo carga includes/lib/autoload.php sólo cuando
#   un destino SFTP lo necesita.
#
# Uso: bash scripts/vendor-phpseclib.sh [carpeta-con-los-zip-ya-descargados]
set -euo pipefail
root=$(cd "$(dirname "$0")/.." && pwd)
dest="$root/digitalisimo-backups/includes/lib"
PHPSECLIB_COMMIT=91f3a6436bb84040ac0cf49e761977f17d16a39b   # 3.0.58
PHPSECLIB_SHA256=9e05556b94a76d22b3c87b84d25207b56603c3779114030b648a6c5bce94cb29
CTE_COMMIT=e30811f7bc69e4b5b6d5783e712c06c8eabf0226         # v2.8.2
CTE_SHA256=25f4c99241957349f2b5a1698d2099fc0be348cd3f2670b9cd3cb60004b52cdd

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
if [ -n "${1:-}" ]; then
  cp "$1/phpseclib.zip" "$1/cte.zip" "$work/"
else
  curl -fsSL -o "$work/phpseclib.zip" "https://api.github.com/repos/phpseclib/phpseclib/zipball/$PHPSECLIB_COMMIT"
  curl -fsSL -o "$work/cte.zip" "https://api.github.com/repos/paragonie/constant_time_encoding/zipball/$CTE_COMMIT"
fi
echo "$PHPSECLIB_SHA256  $work/phpseclib.zip" | sha256sum -c --quiet
echo "$CTE_SHA256  $work/cte.zip" | sha256sum -c --quiet
unzip -q "$work/phpseclib.zip" -d "$work/p"
unzip -q "$work/cte.zip" -d "$work/c"

rm -rf "$dest/phpseclib3" "$dest/ParagonIE"
mkdir -p "$dest/phpseclib3" "$dest/ParagonIE/ConstantTime"
cp -R "$work"/p/*/phpseclib/. "$dest/phpseclib3/"
cp "$work"/p/*/LICENSE "$dest/phpseclib3/LICENSE"
cp -R "$work"/c/*/src/. "$dest/ParagonIE/ConstantTime/"
cp "$work"/c/*/LICENSE.txt "$dest/ParagonIE/ConstantTime/LICENSE.txt"
# bootstrap.php sólo rechaza mbstring.func_overload (PHP < 8); el autoload propio hace esa comprobación.
rm -f "$dest/phpseclib3/bootstrap.php"

# Las constantes globales de configuración (registro de paquetes, colas) también llevan prefijo: un NET_SSH2_LOGGING
# que defina otro plugin no puede hacer que este cliente guarde en memoria cada paquete de un respaldo.
# Nombres en código y en cadenas: `phpseclib3\…`, `\phpseclib3\…` y, en cadenas con barras dobles, `\\phpseclib3\\…`.
find "$dest/phpseclib3" "$dest/ParagonIE" -name '*.php' -print0 | xargs -0 perl -pi -e '
  s/(?<![A-Za-z0-9_\\])(\\?)phpseclib3(\\|;)/$1DigitalisimoBackups\\Vendor\\phpseclib3$2/g;
  s/(?<![A-Za-z0-9_\\])(\\?)ParagonIE\\ConstantTime(\\|;)/$1DigitalisimoBackups\\Vendor\\ParagonIE\\ConstantTime$2/g;
  s/(?<=\x27)\\\\phpseclib3\\\\/\\\\DigitalisimoBackups\\\\Vendor\\\\phpseclib3\\\\/g;
  s/(?<![A-Za-z0-9_])NET_(SSH2_LOGGING|SSH2_LOG_REALTIME_FILENAME|SFTP_LOGGING|SFTP_QUEUE_SIZE|SFTP_UPLOAD_QUEUE_SIZE|SFTP_STREAM_LOGGING)(?![A-Za-z0-9_])/DIGITALISIMO_BACKUPS_NET_$1/g;
'
# Ningún nombre sin prefijo puede quedar (ni en código ni en cadenas); los comentarios no cuentan.
left=$(grep -rnE 'phpseclib3|ParagonIE' --include='*.php' "$dest/phpseclib3" "$dest/ParagonIE" \
  | grep -vE 'Vendor\\{1,2}(phpseclib3|ParagonIE)' | grep -vE '^[^:]+:[0-9]+:\s*(\*|//|/\*)' || true)
if [ -n "$left" ]; then printf '%s\n' "$left" >&2; echo "Quedan nombres sin prefijo" >&2; exit 1; fi
# Las propiedades privadas de SSH2 de las que dependen las protecciones del cliente (sin reconexión automática, clave
# del servidor comprobada en cada operación; revisión N3). El plugin también lo comprueba al cargar y falla cerrado.
for prop in bad_key_size_fix server_public_host_key exchange_hash signature_validated; do
  grep -qE "(private|protected) \\\$$prop\b" "$dest/phpseclib3/Net/SSH2.php" || { echo "Falta la propiedad $prop en Net/SSH2.php: revisa Digitalisimo_Backups_Sftp_Client" >&2; exit 1; }
done
echo "phpseclib 3.0.58 y constant_time_encoding 2.8.2 copiados con prefijo en $dest"
