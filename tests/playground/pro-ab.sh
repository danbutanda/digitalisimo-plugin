#!/usr/bin/env bash
# A/B de los widgets de PRO frente a sus widgets propios (programa «sin widgets de PRO», C0; aceptación de cada widget
# en docs/PRO_WIDGETS_OBJECTIVE.md). Arranca Playground en modo servidor con el núcleo incluido (sin el plugin
# Elementor), y por cada caso de pro-ab/cases.json crea dos páginas con la plantilla «Lienzo»: A con el widget de PRO y
# sus ajustes guardados, y B con los mismos datos convertidos por `Converter::tree( $data, $log, true )` (mapas en
# borrador incluidos), con el CSS de cada página ya generado y el registro de la conversión (códigos del informe).
# Después mide en Chrome las dos páginas a 375, 768 y 1280 px:
#
#   - firma del widget: palabras visibles, enlaces con su destino, imágenes, nombre accesible y rol de enlaces y
#     botones, y estilos calculados de los nodos de texto y de la caja del widget (color, tamaño, peso, estilo,
#     alineación, fondo, bordes, radio, relleno); toda diferencia sin motivo declarado (en el caso o, con `code`, en
#     `drop`/`approx` del mapa) falla;
#   - captura de cada widget y diferencia de píxeles entre A y B (en un canvas de Chrome, sin dependencias nuevas),
#     con umbral configurable, informada por caso y ancho, con la imagen de la diferencia;
#   - hover y foco con teclado del primer enlace o botón del widget (estilos y píxeles);
#   - B no carga `pro-elements-handlers`, `elementor-pro-frontend` ni la hoja del widget de PRO
#     (`widget-<widget>.min.css`, o la lista `forbid` del caso), y ninguna página tiene errores de JavaScript, 404 del
#     mismo origen ni peticiones a terceros nuevas en B;
#   - una clave que el mapa no declara (`pro.unmapped`) falla.
#
# El sitio es uno recién instalado: WP-Cron no se dispara desde las visitas (como en front-budget.sh) y setup.php aplica
# los ajustes de «Rendimiento» (`Transition::run_now()`), así la puerta de PRO actúa; sin ella la corrida falla. Los
# enlaces se comparan por su destino canónico (la consulta decodificada: `+` y `%20` son el mismo espacio), la página
# propia se normaliza (`pro-ab-<caso>-X`) y el navegador no sale del Playground (lo de otros orígenes se corta y se
# anota). Una hoja o un script del sitio servido con redirección o como HTML repite la medición de esa página.
#
# Casos: pro-ab/cases.json más pro-ab/cases/*.json (uno por familia, con `cases` y, opcional, `declared` globales).
# Un caso puede traer `elements` (hijos del widget, para los widgets anidados), imágenes de prueba en sus ajustes
# (`{"$image": n}` da `{id, url}` del adjunto n; `{"$gallery": [n, …]}`, la lista; `"$image-url:n"`, la URL), `ready`
# (`{a, b}`: selectores que deben existir dentro del widget antes de medir; por defecto se espera a que Swiper y el motor
# `digi-crs` hayan arrancado) y `actions` (pulsar o teclear tras medir cada ancho, con firma y píxeles después de cada
# una; ver run.cjs).
# C2: `before`/`after` (elementos hermanos del widget en su contenedor: los encabezados de una tabla de contenido; se
# convierten en B como el resto del árbol, así que un hermano de PRO también pasa al propio) y `reducedMotion` (A y B con
# «reducir movimiento», para comparar el estado final de una animación).
# También `clock` (fecha ISO: el reloj del navegador fijado, para una cuenta regresiva) y, en `actions`, `scroll` (píxeles o
# `end`: desplaza la página en lugar de pulsar, para un indicador de progreso) y `shape` (motivo: el widget sólo dibuja una forma
# al cargar; basta con que su caja tenga alto).
# Umbrales de píxeles, siempre con `reason`: `pixels.max` (todo el caso), `pixels.widths` (`{"<ancho>": {max, reason}}`:
# sólo ese ancho) y `pixels.states` (`{hover|focus: {max, reason} | {skip: true, reason}}`: umbral propio de un estado, u
# omitir sus píxeles cuando sólo B tiene algo que enfocar o que lleva el puntero fuera del widget). Medios de Lottie en los
# ajustes: `{"$lottie": 1}` da `{id, url}` de un adjunto JSON de prueba y `"$lottie-url"`, su URL.
# C3: `{"$menu": "nombre"}` en los ajustes da el slug de un menú de WordPress de prueba con submenús de dos niveles
# (`pro-ab-<nombre>`), `"$loop:nombre"` el ID de una plantilla de bucle con tres entradas (resultados en vivo de una
# búsqueda), y en `before` (disparadores fuera del widget), `{id}` es el `id` del widget del caso y
# `"$action:<acción>:<modo>"` el enlace de acción del núcleo que lo nombra (`off_canvas:open`, codificado como la etiqueta).
# En `actions`: `hover` (pasar el puntero en lugar de pulsar), `stay` (no mover el puntero ni quitar el foco tras la acción),
# `page` (el selector es de la página, no del widget), `type` (texto que se teclea en el elemento) y `focus` (anota dónde
# queda el foco y lo compara entre A y B). En el caso: `shot: "viewport"` (captura de la ventana: paneles fijos y
# desplegables que salen de la caja del widget), `control: {a, b}` (control medido en hover y foco cuando el primero del
# widget no es el comparable) y `empty` (motivo: el widget no pinta nada hasta una acción, como un panel cerrado).
# C4 (elementos de tema) añade, todos opcionales y sin efecto en los casos que no los traen:
#   - `context`: mide A y B dentro de una consulta, como la vista previa del Theme Builder (`switch_to_query()` en el
#     primer elemento de nivel superior): `"post"` o `{"post": "full"}` (entrada completa: autora con biografía,
#     categoría y etiqueta, extracto, bloques, imagen destacada con leyenda, comentarios con respuesta, anterior y
#     siguiente), `{"post": "bare"}` (sin extracto, imagen, etiqueta ni comentarios), `"page"` o
#     `{"archive": "category"|"tag"|"author"|"date"|"search"}`. Los datos de prueba (fijos e idempotentes) y el
#     mu-plugin `digi-ab-context.php` sólo se crean si algún caso de la corrida trae `context`; los avatares salen de
#     una imagen local. La consulta queda en conversion.json y pro-ab.json;
#   - `blank` (con su motivo): un widget que por diseño no pinta texto ni imágenes (el buscador con botón de icono) se
#     acepta si A tiene controles enfocables;
#   - `"yoast": true`: activate.php escribe el mu-plugin `digi-ab-yoast.php`, con `WPSEO_Breadcrumbs::breadcrumb()` y
#     `yoast_breadcrumb()` de prueba (una ruta fija en el formato de Yoast), para que PRO registre sus migas.
# C5: entradas de prueba (`"posts": n` en el caso o `{"$posts": n}` en los ajustes, que da sus IDs: 9 entradas
# deterministas con categorías «Noticias», «Guías» y «Casos», etiquetas «par»/«impar», extracto e imagen destacada),
# plantilla de Elemento de bucle (`{"$loop_item": "card"|"alt"|"title"|"term"}`, publicada una vez y con su ID como
# cadena), consulta de archivo (`"archive": {variables de WP_Query}`: las páginas A y B listan esa consulta en el widget
# de archivo, con la página `/N/` como `paged`) y, en los hermanos `before`/`after`, `"$self"` (el ID del widget medido:
# un filtro que apunta al bucle del caso). En `actions`, `page: true` (el selector es relativo a la página, no al widget:
# el filtro hermano) y `scroll: true` (lleva el selector a la vista en lugar de pulsarlo: desplazamiento infinito).
# C6 (formularios: `cases/form.json` y `cases/login.json`) no añade opciones: los pasos del Formulario de PRO se arman en
# el navegador (`ready.a`) y el propio los pinta el servidor; el Inicio de sesión se mide sin sesión iniciada. Los envíos
# (ninguno se pierde ni se duplica, con las acciones guardar, correo y webhook) no se miden aquí, sino de punta a punta
# con tests/playground/pro-form-e2e.sh.
# C7 (WooCommerce) añade, también opcional y sin efecto en los casos que no lo usan (detalle en setup.php,
# `digi_ab_woo_fixtures()` y `digi_ab_woo_context()`):
#   - tienda: si algún caso de la corrida trae `woo`, un contexto de tienda o es un widget de tienda de PRO
#     (`woocommerce-*`, `wc-*`), o con WOO=1, se monta WooCommerce fijado (woocommerce-pin.sh: 11.2.1 de reference/, con su
#     sha256; sin la copia, la corrida se detiene), activate.php lo activa antes que el constructor y setup.php crea la
#     tienda de prueba (productos simple, variable, en oferta, agotado y con reseñas; categorías con imagen; clienta con
#     su pedido; tarifa plana y contra reembolso). Los ID quedan en conversion.json (`woo`) y en `"$woo:clave"` y
#     `{"$woo-ids": [...]}` de los ajustes;
#   - `context` de tienda: `{"product": "simple"|"variable"|"sale"|"outofstock"|"reviews"}`, `"shop"`,
#     `{"archive": "product_cat"|"product_subcat"|"product_tag"}`, `"cart"` y `"checkout"` (la página del caso hace de
#     carrito o de pago, con artículos), `"account"` (de «Mi cuenta», con la clienta dentro) y `{"order": "received"}`
#     (pedido recibido de la clienta);
#   - `woo` (objeto): `cart` (`items`|`empty`), `user` (`customer`|`guest`), `endpoint` (`orders`, `view-order`,
#     `downloads`, `edit-address`, `edit-account`…; también en `$wp_query->query`, como una petición real) y `notices`
#     (`true`: avisos de éxito, información y error). En las páginas del A/B, los fragmentos del carrito esperan 30 s
#     (`request_timeout` de `wc-cart-fragments`, igual en A y B);
#   - `container` (objeto): ajustes que se suman a los del contenedor común del caso (p. ej. `_css_classes: woocommerce`,
#     la clase de la página de un producto, que usan los selectores `.woocommerce {{WRAPPER}} …`);
#   - `register_pro` (`{"file", "class"}`): registra en el sitio del A/B un widget de PRO que PRO no registra (la Imagen de
#     la categoría no figura en el mapa de widgets de su módulo de WooCommerce, así que A no pintaría nada).
#
# Uso: bash tests/playground/pro-ab.sh                 todos los casos
#      CASES=bq-defaults,bq-boxed bash ...             sólo esos casos
#      KEEP=1 bash ...                                 prepara el sitio y lo deja arrancado (depuración; Ctrl+C)
#
# Variables:
#   OUT              resultados (por defecto /var/tmp/digi-pro-ab): pro-ab.json, shots/ (A, B y la diferencia de cada
#                    caso, ancho y estado), conversion.json (registro de setup.php) y server.log
#   PORT             puerto del servidor (por defecto 9701; el arnés usa 9701–9720)
#   TMPDIR           temporales de Playground (por defecto /var/tmp: /tmp es tmpfs)
#   CASES=a,b        casos a medir (por defecto, todos los que no tienen `pending`)
#   PIXEL_MAX        proporción máxima de píxeles distintos entre A y B (por defecto la de cases.json, 0.02); un caso
#                    puede subirla con `pixels.max` y su `reason`
#   PIXEL_TOLERANCE  diferencia por canal (0–255) a partir de la cual un píxel cuenta como distinto (por defecto 48)
#   ELEMENTS_DIR     carpeta del plugin (por defecto digitalisimo-elements/ del repositorio)
#   MAP              mapa de prueba (archivo PHP que devuelve un mapa): setup.php lo fija con `Registry::set()` antes de
#                    convertir, sin tocar maps/ (sólo para depurar la mecánica del arnés)
#   LIVE=1           monta las carpetas tal cual; por defecto se mide una copia fija tomada al empezar ($OUT/src)
#   WOO=1            monta y activa WooCommerce aunque ningún caso lo pida (WOO_ZIP: otro ZIP anotado; ver woocommerce-pin.sh)
# Resultado: $OUT/pro-ab.json; sale con 1 si hay alguna falla.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
STARTED="$(date +%s)"
export TMPDIR="${TMPDIR:-/var/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/var/tmp/digi-pro-ab}"
PORT="${PORT:-9701}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
ELEMENTS_DIR="${ELEMENTS_DIR:-$ROOT/digitalisimo-elements}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
[ -f "$ELEMENTS_DIR/pro-elements.php" ] || { echo "ELEMENTS_DIR no contiene pro-elements.php: $ELEMENTS_DIR"; exit 1; }
case "$OUT" in /tmp/*) echo "OUT no puede estar en /tmp (tmpfs): usar /var/tmp"; exit 1;; esac
if [ -n "${MAP:-}" ] && [ ! -f "$MAP" ]; then echo "MAP no existe: $MAP"; exit 1; fi

# Puertos: nunca se mata un proceso que no sea un Playground de este arnés (el que sirve este mismo OUT).
free_port() {
  local port="$1" pids pid cmd
  pids="$(fuser -n tcp "$port" 2>/dev/null | tr -s ' ' || true)"
  [ -z "${pids// /}" ] && return 0
  for pid in $pids; do
    cmd="$(tr '\0' ' ' < "/proc/$pid/cmdline" 2>/dev/null || true)"
    case "$cmd" in
      *playground*"$OUT"*) ;;
      *) echo "El puerto $port está ocupado por otro proceso ($pid: ${cmd:0:120}); no se libera"; return 1;;
    esac
  done
  fuser -k -n tcp "$port" >/dev/null 2>&1 || true
  sleep 1
}
free_port "$PORT"

mkdir -p "$OUT" "$TMPDIR"
rm -rf "$OUT/shots" "$OUT/probe" "$OUT/html"
rm -f "$OUT/pro-ab.json" "$OUT/server.log"
PROBE="$OUT/probe"
mkdir -p "$PROBE"
cp "$ROOT/tests/playground/pro-ab/"{activate.php,setup.php} "$PROBE/"
# cases.json más los casos de cada familia (pro-ab/cases/*.json: `cases` y, opcional, `declared` globales).
node -e '
const fs = require("fs"), path = require("path");
const [dir, out] = process.argv.slice(1);
const all = JSON.parse(fs.readFileSync(path.join(dir, "cases.json"), "utf8"));
const extra = path.join(dir, "cases");
for (const f of fs.existsSync(extra) ? fs.readdirSync(extra).filter((x) => x.endsWith(".json")).sort() : []) {
  const part = JSON.parse(fs.readFileSync(path.join(extra, f), "utf8"));
  for (const [slug, c] of Object.entries(part.cases || {})) {
    if (all.cases[slug]) { console.error(`Caso repetido: ${slug} (${f})`); process.exit(1); }
    all.cases[slug] = c;
  }
  all.declared = (all.declared || []).concat(part.declared || []);
}
fs.writeFileSync(out, JSON.stringify(all, null, 1));
' "$ROOT/tests/playground/pro-ab" "$PROBE/cases.json"
[ -n "${MAP:-}" ] && cp "$MAP" "$PROBE/map-override.php"
printf '%s\n' "${CASES:-}" > "$PROBE/only.txt"
# Tienda (C7): ¿algún caso elegido pide WooCommerce? (`woo`, un contexto de tienda o un widget de tienda de PRO).
NEED_WOO="$(node -e '
const fs = require("fs");
const [file, only] = process.argv.slice(1);
const all = JSON.parse(fs.readFileSync(file, "utf8")).cases || {};
const list = fs.readFileSync(only, "utf8").split(",").map((x) => x.trim()).filter(Boolean);
const shop = (c) => { const x = c.context; if (typeof x === "string") return ["shop", "cart", "checkout", "account"].includes(x); if (!x || typeof x !== "object") return false; const k = Object.keys(x)[0]; return ["product", "shop", "cart", "checkout", "account", "order"].includes(k) || (k === "archive" && /^product_/.test(String(x[k]))); };
const need = Object.entries(all).some(([slug, c]) => (list.length ? list.includes(slug) : !c.pending) && (c.woo || shop(c) || /^(woocommerce-|wc-)/.test(String(c.widget || ""))));
process.stdout.write(need ? "1" : "");
' "$PROBE/cases.json" "$PROBE/only.txt")"
[ "${WOO:-}" = 1 ] && NEED_WOO=1
WOO_MOUNT=()
rm -f "$OUT/woocommerce.json"
if [ -n "$NEED_WOO" ]; then
  . "$ROOT/tests/playground/woocommerce-pin.sh"
  WOO_REQUIRE=1 woo_pin "$OUT/woocommerce" "$OUT/woocommerce.json" || exit 1
  cp "$OUT/woocommerce.json" "$PROBE/woo.json"
  WOO_MOUNT=("$(woo_mount)")
fi

# Copia fija de lo que se mide: un cambio a mitad de corrida (otro agente editando) no mezcla versiones.
if [ "${LIVE:-0}" = 1 ]; then
  SRC_ELEMENTS="$ELEMENTS_DIR"
else
  rm -rf "$OUT/src" && mkdir -p "$OUT/src"
  cp -a "$ELEMENTS_DIR" "$OUT/src/digitalisimo-elements"
  SRC_ELEMENTS="$OUT/src/digitalisimo-elements"
fi
ELEMENTS_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$SRC_ELEMENTS/pro-elements.php" | head -1)"
GIT_SHA="$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo '')"

cat > "$PROBE/blueprint.json" <<'JSON'
{"steps":[
 {"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/activate.php';"},
 {"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}
]}
JSON

echo "pro-ab · Elements $ELEMENTS_VERSION · puerto $PORT · $OUT"
$CLI server --port="$PORT" --workers=1 --site-url="http://127.0.0.1:$PORT" --blueprint="$PROBE/blueprint.json" \
  --mount="$SRC_ELEMENTS:/wordpress/wp-content/plugins/digitalisimo-elements" --mount="$PROBE:/wordpress/wp-content/probe" "${WOO_MOUNT[@]}" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
trap 'exit 130' INT TERM
ping_site() { curl -s -m 30 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/pro-ab-ready/" 2>/dev/null; }
for i in $(seq 1 200); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then echo 'Playground terminó antes de tiempo:'; tail -20 "$OUT/server.log"; exit 1; fi
  [ -f "$PROBE/setup-error.txt" ] && break
  [ -f "$PROBE/ready.json" ] && ping_site | grep -q '^200$' && break
done
[ -f "$PROBE/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$PROBE/setup-error.txt"; exit 1; }
ping_site | grep -q '^200$' || { echo 'El servidor no sirvió las páginas de prueba'; tail -20 "$OUT/server.log"; exit 1; }
cp "$PROBE/conversion.json" "$OUT/conversion.json"
if [ "${KEEP:-0}" = 1 ]; then
  echo "KEEP=1: sitio listo en http://127.0.0.1:$PORT (páginas /pro-ab-<caso>-a/ y -b/); Ctrl+C para terminar"
  while kill -0 $SERVER 2>/dev/null; do sleep 5; done
  exit 0
fi
status=0
OUT="$OUT" PROBE="$PROBE" BASE="http://127.0.0.1:$PORT" PIXEL_MAX="${PIXEL_MAX:-}" PIXEL_TOLERANCE="${PIXEL_TOLERANCE:-}" \
  ELEMENTS_VERSION="$ELEMENTS_VERSION" GIT_SHA="$GIT_SHA" STARTED="$STARTED" MAP="${MAP:-}" \
  NODE_PATH="$PUPPETEER_PATH" node "$ROOT/tests/playground/pro-ab/run.cjs" || status=$?
[ -s "$PROBE/fatal.log" ] && { echo "Errores fatales de PHP:"; cat "$PROBE/fatal.log"; status=1; }
echo "Duración: $(( $(date +%s) - STARTED )) s"
exit $status
