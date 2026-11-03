# Element Pack de los arneses de compatibilidad (legacy-ab.sh, migrate.sh y f8/p10.sh). Se carga con «.».
#
# Por defecto, la copia oficial de **Element Pack Lite 10.0.1** (reference/bdthemes-element-pack-lite.10.0.1.zip,
# descargada de https://downloads.wordpress.org/plugin/bdthemes-element-pack-lite.10.0.1.zip y anotada en
# reference/OFFICIAL-SOURCES.txt, que no se versiona). Su sha256 se comprueba contra la anotada y contra la fijada aquí
# antes de extraerla en EP_CACHE (por defecto /var/tmp/digi-f8/pg/ep-lite); se monta en su carpeta real,
# `bdthemes-element-pack-lite/bdthemes-element-pack-lite.php`. Sólo en un Playground efímero: nunca en un sitio real.
#
# Opción: EP_DIR=<carpeta> monta una copia local ya extraída (p. ej. la de 9.9.1 de antes de F8), en la carpeta que
# corresponde a su archivo principal. Element Pack Pro 10.0.1 de reference/ **nunca** se instala (licencia anulada).
#
#   ep_pin <archivo json>   prepara la copia y deja EP_SRC (carpeta que se monta), EP_SLUG (carpeta en plugins/),
#                           EP_PLUGIN (slug/archivo.php), EP_LABEL, EP_VERSION, EP_SHA256 y el JSON con la procedencia
#   ep_mount                argumento --mount=… de la copia
EP_LITE_VERSION=10.0.1
EP_LITE_ZIP_NAME="bdthemes-element-pack-lite.$EP_LITE_VERSION.zip"
EP_LITE_SHA256=476b853d8d936787991b8983c2b5407b84796e37fc33a5da5774ad914e6dd9d7

ep_pin_reference_dir() {
  local root main
  root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
  if [ -n "${REF_DIR:-}" ]; then echo "$REF_DIR"; return 0; fi
  if [ -f "$root/reference/$EP_LITE_ZIP_NAME" ]; then echo "$root/reference"; return 0; fi
  main="$(dirname "$(git -C "$root" rev-parse --path-format=absolute --git-common-dir 2>/dev/null || echo "$root/.git")")"
  echo "$main/reference"
}

ep_pin() {
  local json="$1" ref zip noted actual cache
  if [ -n "${EP_DIR:-}" ]; then
    [ -d "$EP_DIR" ] || { echo "EP_DIR no existe: $EP_DIR"; return 1; }
    if [ -f "$EP_DIR/bdthemes-element-pack-lite.php" ]; then EP_SLUG=bdthemes-element-pack-lite; EP_PLUGIN=bdthemes-element-pack-lite/bdthemes-element-pack-lite.php
    elif [ -f "$EP_DIR/bdthemes-element-pack.php" ]; then EP_SLUG=bdthemes-element-pack; EP_PLUGIN=bdthemes-element-pack/bdthemes-element-pack.php
    else echo "EP_DIR no contiene el archivo principal de Element Pack"; return 1; fi
    EP_SRC="$EP_DIR" EP_SOURCE=local EP_SHA256=''
  else
    ref="$(ep_pin_reference_dir)"
    zip="$ref/$EP_LITE_ZIP_NAME"
    [ -s "$zip" ] || { echo "Falta la copia oficial $zip (o EP_DIR)"; return 1; }
    noted="$(awk -v n="$EP_LITE_ZIP_NAME" '$1 == n { getline; print $2; exit }' "$ref/OFFICIAL-SOURCES.txt" 2>/dev/null || true)"
    [ "$noted" = "$EP_LITE_SHA256" ] || { echo "$EP_LITE_ZIP_NAME: la suma anotada en $ref/OFFICIAL-SOURCES.txt ('$noted') no es la fijada"; return 1; }
    actual="$(sha256sum "$zip" | cut -d' ' -f1)"
    [ "$actual" = "$EP_LITE_SHA256" ] || { echo "$zip: sha256 $actual distinta de la anotada"; return 1; }
    cache="${EP_CACHE:-/var/tmp/digi-f8/pg/ep-lite}"
    if [ ! -f "$cache/.sha256" ] || [ "$(cat "$cache/.sha256")" != "$actual" ] || [ ! -f "$cache/bdthemes-element-pack-lite/bdthemes-element-pack-lite.php" ]; then
      rm -rf "$cache/bdthemes-element-pack-lite" && mkdir -p "$cache"
      unzip -q "$zip" -d "$cache" || { echo "No se pudo extraer $zip"; return 1; }
      echo "$actual" > "$cache/.sha256"
    fi
    EP_SRC="$cache/bdthemes-element-pack-lite" EP_SLUG=bdthemes-element-pack-lite EP_PLUGIN=bdthemes-element-pack-lite/bdthemes-element-pack-lite.php EP_SOURCE=reference EP_SHA256="$actual"
  fi
  EP_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$EP_SRC/$(basename "$EP_PLUGIN")" | head -1)"
  EP_LABEL="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Plugin Name:[[:space:]]*\(.*\)$/\1/p' "$EP_SRC/$(basename "$EP_PLUGIN")" | head -1)"
  [ "$EP_SOURCE" != reference ] || [ "$EP_VERSION" = "$EP_LITE_VERSION" ] || { echo "La copia dice $EP_VERSION y debería ser $EP_LITE_VERSION"; return 1; }
  # La lista de widgets que registra la copia (sus get_name()): legacy-ab.php y migrate/setup.php limitan los casos a ella.
  grep -rhoE "return '(bdt-[a-z0-9-]+)';" "$EP_SRC"/modules/*/widgets/*.php 2>/dev/null | sed -E "s/return '(.*)';/\1/" | sort -u > "$(dirname "$json")/ep-widgets.txt"
  ls -1 "$EP_SRC/modules" > "$(dirname "$json")/ep-modules.txt"
  node -e '
const [file, source, slug, plugin, label, version, sha256, src] = process.argv.slice(1);
require("fs").writeFileSync(file, JSON.stringify({ source, slug, plugin, label, version, sha256: sha256 || null, src, note: source === "reference" ? "copia oficial de wordpress.org con la sha256 anotada en reference/OFFICIAL-SOURCES.txt" : "copia local (EP_DIR)" }, null, 1) + "\n");
' "$json" "$EP_SOURCE" "$EP_SLUG" "$EP_PLUGIN" "$EP_LABEL" "$EP_VERSION" "$EP_SHA256" "$EP_SRC"
  echo "Element Pack: $EP_LABEL $EP_VERSION ($EP_SOURCE) en $EP_SLUG/"
}

ep_mount() {
  printf -- '--mount=%s:/wordpress/wp-content/plugins/%s' "$EP_SRC" "$EP_SLUG"
}
