# WooCommerce fijado para los arneses de Playground (F4-Woo, V.16.1). Se carga con «.» desde el arnés, después de
# definir ROOT. La copia oficial `reference/woocommerce.11.2.1.zip` (fuera de Git; en un worktree, la del repositorio
# principal, como Hello en theme-switch.sh) se comprueba con la sha256 anotada en `reference/OFFICIAL-SOURCES.txt`, se
# extrae en una carpeta del arnés y se monta como plugin: nada se descarga de wordpress.org.
#
#   woo_pin <carpeta> [<archivo json>]   prepara WooCommerce y deja:
#       WOO_SOURCE   reference (copia local comprobada) o wordpress.org (sin copia local: la última versión, como antes)
#       WOO_DIR      carpeta extraída que se monta en /wordpress/wp-content/plugins/woocommerce (vacía con wordpress.org)
#       WOO_VERSION  versión de la cabecera de woocommerce.php (vacía con wordpress.org: se sabrá al instalar)
#       WOO_SHA256   sha256 del ZIP montado
#     y escribe <archivo json> (por defecto <carpeta>/woocommerce.json) con esos datos y la nota, para el resumen.
#   woo_step true|false   paso del blueprint: activar el plugin montado (o nada si no se activa) o, sin copia local,
#                         el installPlugin de wordpress.org de siempre
#   woo_mount             argumento --mount=… del plugin montado (vacío con wordpress.org)
#
# Variables:
#   WOO_ZIP        ZIP de WooCommerce (por defecto reference/woocommerce.11.2.1.zip del repositorio o del principal).
#                  Si se da y no existe, el arnés se detiene; si no se da y no hay copia, se usa wordpress.org.
#   WOO_SOURCES    archivo de sumas (por defecto OFFICIAL-SOURCES.txt junto al ZIP)
#   WOO_REQUIRE=1  sin copia local, el arnés se detiene (woo-flow.sh lo exige: W0 comprueba la versión fijada)
# Una suma distinta, un ZIP sin anotar o una cabecera que no coincide con el nombre detienen el arnés siempre.

WOO_PIN_VERSION=11.2.1
# Copia de la suma de reference/OFFICIAL-SOURCES.txt (que no se versiona), también en tests/playground/README.md.
WOO_PIN_SHA256=5317434cb381b5fdc7c1dd6ee258324f08c390976aaf8d1199f2a0e9a3ab8586
WOO_SOURCE='' WOO_DIR='' WOO_VERSION='' WOO_SHA256='' WOO_NOTE=''

woo_pin_default_zip() {
  local name="woocommerce.$WOO_PIN_VERSION.zip" main_root
  if [ -f "$ROOT/reference/$name" ]; then echo "$ROOT/reference/$name"; return 0; fi
  main_root="$(git -C "$ROOT" rev-parse --path-format=absolute --git-common-dir 2>/dev/null || true)"
  [ -n "$main_root" ] && main_root="$(dirname "$main_root")"
  if [ -n "$main_root" ] && [ -f "$main_root/reference/$name" ]; then echo "$main_root/reference/$name"; return 0; fi
  return 1
}

# Suma anotada para <nombre> en el archivo de sumas: la línea «sha256 …» que sigue a la del nombre.
woo_pin_recorded_sum() {
  awk -v f="$1" 'found { if ($1 == "sha256") print $2; exit } $1 == f { found = 1 }' "$2"
}

woo_pin() {
  local dest="$1" json="${2:-}" zip name sources recorded actual header expected
  [ -n "$json" ] || json="$dest/woocommerce.json"
  if [ -n "${WOO_ZIP:-}" ]; then
    zip="$WOO_ZIP"
    [ -f "$zip" ] || { echo "WOO_ZIP no existe: $zip"; return 1; }
  else
    zip="$(woo_pin_default_zip || true)"
  fi
  mkdir -p "$dest"
  if [ -z "$zip" ]; then
    if [ "${WOO_REQUIRE:-0}" = 1 ]; then
      echo "Falta reference/woocommerce.$WOO_PIN_VERSION.zip (o WOO_ZIP): este arnés exige WooCommerce $WOO_PIN_VERSION"
      return 1
    fi
    WOO_SOURCE=wordpress.org WOO_DIR='' WOO_VERSION='' WOO_SHA256=''
    WOO_NOTE="sin reference/woocommerce.$WOO_PIN_VERSION.zip: se instala la última versión de wordpress.org (la corrida no vale para F4-Woo)"
  else
    name="$(basename "$zip")"
    sources="${WOO_SOURCES:-$(dirname "$zip")/OFFICIAL-SOURCES.txt}"
    [ -f "$sources" ] || { echo "Falta $sources: no se puede comprobar la suma de $name"; return 1; }
    recorded="$(woo_pin_recorded_sum "$name" "$sources")"
    [ -n "$recorded" ] || { echo "$name no está anotado (con su sha256) en $sources"; return 1; }
    actual="$(sha256sum "$zip" | cut -d' ' -f1)"
    [ "$actual" = "$recorded" ] || { echo "$zip: sha256 $actual distinta de la anotada en $sources ($recorded)"; return 1; }
    if [ "$name" = "woocommerce.$WOO_PIN_VERSION.zip" ] && [ "$actual" != "$WOO_PIN_SHA256" ]; then
      echo "$zip: sha256 $actual distinta de la fijada en tests/playground/woocommerce-pin.sh ($WOO_PIN_SHA256)"; return 1
    fi
    rm -rf "$dest/plugin" && mkdir -p "$dest/plugin"
    unzip -q "$zip" -d "$dest/plugin" || { echo "No se pudo extraer $zip"; return 1; }
    [ -f "$dest/plugin/woocommerce/woocommerce.php" ] || { echo "$zip no contiene woocommerce/woocommerce.php"; return 1; }
    header="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$dest/plugin/woocommerce/woocommerce.php" | head -1)"
    expected="$(printf '%s' "$name" | sed -n 's/^woocommerce\.\([0-9.]*[0-9]\)\.zip$/\1/p')"
    [ -z "$expected" ] || [ "$header" = "$expected" ] || { echo "$zip: la cabecera dice $header y el nombre $expected"; return 1; }
    WOO_SOURCE=reference WOO_DIR="$dest/plugin/woocommerce" WOO_VERSION="$header" WOO_SHA256="$actual"
    WOO_NOTE="WooCommerce $header desde $zip (sha256 comprobada contra $sources), montado"
  fi
  node -e '
const [file, source, version, sha256, zip, dir, note] = process.argv.slice(1);
require("fs").writeFileSync(file, JSON.stringify({ source, version: version || null, sha256: sha256 || null, zip: zip || null, dir: dir || null, pinned: source === "reference", note }, null, 1) + "\n");
' "$json" "$WOO_SOURCE" "$WOO_VERSION" "$WOO_SHA256" "$zip" "$WOO_DIR" "$WOO_NOTE"
  echo "WooCommerce: $WOO_NOTE"
}

woo_step() {
  local activate="${1:-true}"
  if [ "$WOO_SOURCE" = reference ]; then
    [ "$activate" = true ] && printf '%s' '{"step":"activatePlugin","pluginPath":"woocommerce/woocommerce.php"}'
  else
    printf '{"step":"installPlugin","pluginData":{"resource":"wordpress.org/plugins","slug":"woocommerce"},"options":{"activate":%s}}' "$activate"
  fi
  return 0
}

woo_mount() {
  [ "$WOO_SOURCE" = reference ] && printf -- '--mount=%s:/wordpress/wp-content/plugins/woocommerce' "$WOO_DIR"
  return 0
}
