# Marca propia en DIGITALÍSIMO Elements: cero rastro de «elementor»

Objetivo del dueño (2026-11-27): **cero apariciones de «elementor»** (sin distinguir mayúsculas) en todo lo que ve un visitante, el cliente en su escritorio y en el editor, o una IA por MCP: HTML, CSS y JS servidos, URL del front y del escritorio, menús y textos, respuestas REST y MCP y el índice `/wp-json/`, nombres de archivos que se pueden pedir por URL, la carpeta del motor dentro del ZIP y las herramientas de desarrollo del navegador.

**Lo único que se conserva** son los nombres de almacenamiento interno (metas `_elementor_*`, opciones `elementor_*`, el tipo `elementor_library` y sus taxonomías) y los ganchos PHP: nadie los ve sin abrir la base de datos y cambiarlos rompería todos los sitios, kits y plantillas. Las exportaciones que el cliente descarga se renombran al salir y aceptan los dos nombres al entrar.

**Sin bifurcar el motor:** `digitalisimo-elements/elementor/` sigue siendo la copia sin cambios de wordpress.org (sumas oficiales y huella `tree_sha256` intactas). El renombre se hace con filtros, con una capa de marca en la salida y al empaquetar.

## Decisiones (D-1 a D-12)

| # | Decisión |
|---|---|
| D-1 | La regla de identificadores pasa a ser: «se conservan por dentro (base de datos, ganchos PHP) y se renombran en todo lo que sale» (AGENTS.md, `docs/ELEMENTOR_CORE.md`). |
| D-2 | `dg-` para clases, `data-dg-*`, `digitalisimoFrontendConfig`, `digitalisimoProFrontendConfig`, `digitalisimoFrontend`, `digitalisimoModules`; ruta `/wp-json/digitalisimo/mcp`, herramientas `digitalisimo-*`, recursos `digitalisimo://`; CSS generado en `uploads/digitalisimo-elements/css/`. |
| D-3 | Alias para todas las URL visibles del escritorio y del editor (`action=digitalisimo`, `page=digitalisimo-motor…`, `post_type=digitalisimo_library`); la URL vieja sigue funcionando pero ningún enlace la muestra. |
| D-4 | El experimento «Atomic Widgets» se llama «Componentes atómicos DIGITALÍSIMO» (`e_atomic_elements` se conserva). |
| D-5 | Con complementos de terceros que extienden el motor, la marca propia se bloquea por defecto, con la lista y la opción de forzarla bajo responsabilidad del administrador. Con el Elementor original activo no aplica. |
| D-6 | Los datos que devuelve el MCP también se renombran al salir (`elementor_library` → `digitalisimo_library`, `action=elementor` → `action=digitalisimo`, metas `_elementor_*` → `_dg_*`) y al entrar se aceptan los dos nombres. |
| D-7 | El alias `/wp-json/elementor/mcp` se retira el 2027-01-31 si lleva 14 días sin uso (con prórroga de 30 días) y mientras tanto no aparece en el índice REST. |
| D-8 | Se ocultan del índice `/wp-json/` (para todos) los espacios `elementor*`. El paquete no lleva `readme.txt` ni archivos de idioma con «elementor» en el nombre (se renombran al empaquetar con un mapeo de dominio de texto). |
| D-9 | La carpeta del motor cambia sólo en el ZIP: `digitalisimo-elements/motor/`. En el repositorio sigue `elementor/`. |
| D-10 | Sin doble clase: la transición es el interruptor por sitio. |
| D-11 | digitalisimo.mx pasa al tema DIGITALÍSIMO antes de encender la marca propia ahí. |
| D-12 | Lo que haga falta para el editor (globales JS, rutas en las herramientas del navegador) se resuelve con el espejo transformado al empaquetar, no editando `elementor/`. |

## Reglas

Viven en `digitalisimo-elements/marca/reglas.json`. `node scripts/marca-reglas.mjs` lee los CSS y JS que puede servir el front (núcleo, derivado de PRO y módulos propios), clasifica cada aparición de «elementor» (clases, atributos `data-elementor-*` y su forma `dataset.elementorX`, globales y propiedades JS, eventos, rutas REST, acciones AJAX, acciones de URL, `@keyframes`, variables CSS, ids) y falla si algo no tiene regla o si los bundles cambiaron desde la última derivación (huella). `--escribir` regenera las listas derivadas y conserva lo escrito a mano.

Lo que nunca se toca: `src`, `srcset`, `poster`, el `href` de enlaces (salvo `#elementor-action:`), el `action` de formularios y el texto visible del contenido. Una imagen llamada `elementor-banner.jpg` o un artículo que hable de Elementor salen como están.

### Respuestas del front que devuelven HTML (lista cerrada)

La capa de la fase 2 les aplica las mismas reglas; una respuesta nueva que devuelva HTML entra en `fragmentos.html` de `reglas.json`, o el front mezclaría nombres viejos con CSS nuevo.

| Respuesta | Origen | Qué |
|---|---|---|
| REST `elementor-pro/v1/refresh-loop` | `modules/loop-filter/` | Loop Grid filtrado (taxonomía, F4-Woo, F7b) |
| REST `elementor-pro/v1/refresh-search` | `modules/search/` | resultados en vivo de Búsqueda |
| REST `elementor-pro/v1/collection-loop/loop-preview` | `modules/collection-loop/` | paginación AJAX del bucle de colección |
| AJAX `elementor_menu_cart_fragments` y `wc-ajax` con `woocommerce_add_to_cart_fragments` | `modules/woocommerce/` y WooCommerce | fragmentos del Menú del carrito |
| REST `digitalisimo-elements/v1/woo/quick-view` y `…/wishlist` | `modules/digitalisimo-woo/` | vista rápida y tarjetas de la lista de deseos |
| AJAX `digitalisimo_elements_google_reviews` | `modules/digitalisimo-widgets/` | reseñas de Google |
| La página siguiente («Cargar más», scroll infinito) | `elements-handlers.min.js` | página completa: pasa por el buffer |
| AJAX `elementor_ajax` del editor | núcleo | sólo editor y vista previa (D-12) |

Devuelven JSON sin marcado (no necesitan la capa): los envíos de formularios (`elementor_pro_forms_send_form`, `elementor_pro_atomic_forms_send_form`, cuyo mensaje arma el JS del front), `elementor_send_clicks`, `elementor_woocommerce_checkout_login_user`, `elementor_wc_logout`, `digitalisimo_elements_interactions_play`, la búsqueda de DIGITALÍSIMO Navegación y `submit_stripe_form`.

## Fases

1. **Fase 0 (hecha en `elements/marca-propia`):** la regla nueva (D-1), `reglas.json` derivado de los bundles, la lista cerrada de fragmentos, el prototipo medido (buffer en PHP, espejo con los trozos de webpack, lightbox transformado y ZIP con `motor/`) y la auditoría automática de cero rastro (`tests/brand-zero-trace.mjs` y `tests/playground/brand-zero-trace.sh`), que informa el conteo por capa y todavía falla en lo que cubre la fase 2.
2. **Fase 1 (hecha en `elements/marca-propia`):** MCP y escritorio con filtros, sin cambios visibles en el front. Ver abajo.
3. **Fase 2:** espejo de assets al empaquetar, capa de marca en la salida del front con el interruptor por sitio, copias de `post-N.css` y fuentes, alias AJAX y REST, carpeta `motor/` y archivos de idioma renombrados en el ZIP.
4. **Fase 3:** pruebas antes y después en Playground y en copias de staging, nunca en producción.
5. **Fase 4:** publicación por versiones (A: fase 1; B: fase 2 con el interruptor apagado; C: encendido por defecto sin complementos; D: retiro del MCP viejo).

## Fase 1: MCP y escritorio

### Servidor MCP con nombre propio

`modules/digitalisimo-mcp/class-brand-server.php` crea en `mcp_adapter_init` un segundo servidor del adaptador con las mismas herramientas, recursos y prompts del registro compartido del núcleo, el mismo interruptor del MCP del sitio (`McpSettingsController::is_enabled()`), el mismo transporte y los mismos permisos:

- id `digitalisimo-mcp-server`, ruta `/wp-json/digitalisimo/mcp`, nombre «DIGITALÍSIMO MCP» y descripción propia;
- herramientas `digitalisimo-<x>` (la herramienta `elementor-create-page` del servidor viejo es `digitalisimo-create-page`), recursos `digitalisimo://…`;
- descripciones, títulos, esquemas y respuestas pasan por el mapa de marca (`modules/digitalisimo-marca/class-map.php`): al salir, «Elementor» → «DIGITALÍSIMO Elements», `elementor://` → `digitalisimo://`, `elementor/<herramienta>` y `elementor-<herramienta>` → `digitalisimo-<herramienta>`, tipos y taxonomías `elementor_*` registrados → `digitalisimo_*`, metas `_elementor_*` → `_dg_*`, `action=elementor` → `action=digitalisimo`, `page=elementor…` → `page=digitalisimo-motor…`; al entrar se aceptan los dos nombres (el mapa inverso sólo deshace lo que el de salida produce, nunca toca `_digitalisimo_*` ni `digitalisimo_*` propios).

El servidor viejo (`/wp-json/elementor/mcp`, nombres `elementor-*`) sigue igual hasta su retiro (D-7): no aparece en el índice REST, su `initialize` agrega una nota con la ruta nueva y la fecha de retiro, y cada uso (herramientas, lecturas de recursos, prompts e `initialize`) queda contado en la opción `digitalisimo_elements_mcp_legacy` (fecha del último uso y conteo, sin datos personales). La fecha se calcula desde ya (el 2027-01-31 si lleva 14 días sin uso; si sigue en uso, una prórroga de 30 días que «Rendimiento» avisa), pero **esta versión no la retira**: quitar sus rutas lo habilita la versión D de la fase 4 (`Legacy_Route::retire_enabled()`, filtro `digitalisimo_elements_mcp_legacy_retire`), para que ningún cliente que no cambió la dirección se quede sin MCP por una fecha.

El servidor nuevo sólo se construye cuando la petición es a su ruta (o en WP-CLI y peticiones REST internas): el adaptador arma sus servidores en cada `rest_api_init` y así no se duplica el trabajo en las llamadas a la API de los visitantes. En las respuestas, la marca en el texto («Elementor» → «DIGITALÍSIMO Elements») sólo se aplica a las herramientas del motor y a sus claves de mensajes (`message`, `error`, `hint`, `instructions`…); las herramientas propias (tienda, kit, Hosting) y el contenido del cliente sólo cambian identificadores.

### Escritorio

- **Alias de URL (D-3):** con el núcleo incluido, `post.php?post=N&action=digitalisimo`, `admin.php?page=digitalisimo-motor…` y `edit.php?post_type=digitalisimo_library` funcionan igual que las viejas: la petición se traduce al cargar el archivo principal de Elements, justo después del núcleo y antes de que el núcleo, sus guardas de widgets (`Unknown_Guard`, `Inert`) o su App lean `action` y `page` (el modo seguro del núcleo, un mu-plugin, decide antes y sólo reconoce la URL vieja). El script de los alias corre después de `load`, cuando los scripts del núcleo ya leyeron la dirección. Con la marca propia encendida, los enlaces del escritorio y del editor y la barra de direcciones muestran sólo los alias.
- **Experimento (D-4):** «Atomic Widgets» (y su traducción) se presenta como «Componentes atómicos DIGITALÍSIMO».
- **Script de identidad:** deja de imprimirse para los visitantes del front (sólo escritorio, editor, vista previa y barra de administración).
- **Índice REST (D-8):** con la marca propia encendida, `/wp-json/` no lista los espacios `elementor*`; las rutas siguen existiendo para el editor.

### Interruptor y complementos de terceros (D-5)

«Rendimiento» → «Marca propia en el sitio» (por sitio, con valor por defecto de red), **apagado por defecto**. Estados:

- **No disponible** con el Elementor original activo (manda su marca) o sin el núcleo incluido.
- **Bloqueada** si hay complementos de terceros activos que extienden el motor (cabeceras `Elementor tested up to`, `Elementor Pro tested up to` o `Requires Plugins: elementor`, o funciones de otro plugin enganchadas a `elementor/widgets/register`, `elementor/init` o `elementor/frontend/after_register_scripts`): se muestra la lista y el administrador puede **forzarla** bajo su responsabilidad.
- **Encendida.**

En la fase 1 el interruptor gobierna sólo lo visible de la fase 1: los enlaces y la barra de direcciones del escritorio y el índice REST. El MCP nuevo y los alias que aceptan las URL nuevas funcionan siempre.

## Auditoría de cero rastro

`tests/brand-zero-trace.mjs` (sin Playground: el paquete, los nombres de archivos que se pueden pedir por URL, el espejo y el MCP por PHP) y `tests/playground/brand-zero-trace.sh` (Playground con la marca propia encendida: front, escritorio, editor, MCP nuevo con lista de herramientas, recursos y respuestas, `/wp-json/` y URL directas) cuentan las apariciones de «elementor» por capa y fallan si encuentran una fuera de `excepciones_internas` de `reglas.json`. Mientras la fase 2 no esté hecha, fallan en el front, en los archivos del paquete y en el ZIP: el conteo dice cuánto falta.

## Fase 2: paquete transformado (opción A de DECISIONES)

Con `MARCA_PAQUETE=1`, `scripts/build-packages.sh` transforma una copia ya verificada con `scripts/marca-empaquetar.mjs`. La opción está apagada hasta la Fase 3.
- `elementor/` pasa a `motor/`.
- Lo no PHP con «elementor» se publica transformado y renombrado (`elementor` → `dg` en cada tramo de la ruta).
- Los originales van a `marca/originales.php`, que empieza con `<?php exit;`, así que no se sirven.
- El paquete lleva `marca/indice.php`, `marca/reglas.php` y `marca/arbol.php`, con la huella del árbol transformado.

En el sitio (`modules/digitalisimo-marca/class-package.php`):
- **Marca apagada o bloqueada:** los originales se despliegan una vez por versión en `uploads/digitalisimo-elements/base-<versión>/` y las URL del núcleo y de lo cambiado apuntan ahí.
- **Marca encendida:** las URL salen con el nombre publicado y los originales desplegados se borran.

Con el paquete transformado, la marca propia no se enciende hasta que se valide la capa del escritorio y del editor (D-12). Se puede probar con el filtro `digitalisimo_elements_marca_editor_ready`. El 2026-11-27 pasó en Playground el recorrido del editor con la marca encendida y el paquete transformado (abrir, editar, guardar, publicar un borrador con su plantilla, contenido del cliente intacto, sin errores de consola) y el mismo recorrido con la marca apagada; sigue apagada de fábrica hasta la Fase 3.

Con la marca apagada, el núcleo calcula `ELEMENTOR_URL` al incluirse (antes de que el paquete filtre `plugins_url`): la salida del front, del escritorio y de AJAX cambia `…/motor/assets/` por la carpeta de los originales desplegados (`Package::off_urls()`), así los trozos de webpack y las librerías que arma su configuración también salen de los originales.

### Una sola transformación para el JS, el HTML y los datos

El JS se transforma **por contexto** (`scripts/marca-transform.mjs`, analizador léxico): los identificadores de código (`elementor` → `digitalisimo`, `elementorFrontend` → `digitalisimoFrontend`), las cadenas, plantillas y expresiones regulares con las reglas de clases y datos (`.elementor-x` → `.dg-x`, `elementor/…` y `elementor-pro/…` → `digitalisimo/…`, `elementor_x` → `digitalisimo_x`, dominios de texto `elementor` → `dg`), `el.dataset.elementorX` → `dgX` (el atributo `data-dg-*`) y los alias de URL del escritorio. El servidor (`Transform::str`, `ident`, `lex`) aplica exactamente las mismas reglas al JS en línea, a la configuración del editor (`var X = {json}` como datos: el contenido de `elements`, títulos, textos y CSS del cliente no cambia) y a las respuestas de AJAX y de la API. `tests/elements-marca-paridad.mjs` compara las 1 900 cadenas reales de los bundles en Node y en PHP: tienen que dar lo mismo, o el JS busca un nombre y el HTML trae otro.

### El editor (D-12)

`Editor_Layer`, sólo con el paquete transformado y la marca en vigor:
- **entra**: la acción AJAX `digitalisimo_<x>` → `elementor_<x>` si el motor la registró; las rutas `digitalisimo…/…` → el espacio del motor que las produce; las metas `_digitalisimo_x`/`_dg_x` de `wp/v2` y la plantilla de página; claves y valores con su forma exacta en el vocabulario (`marca/vocabulario.json`, derivado del JS por `scripts/marca-vocabulario.mjs`, con su prueba). Nunca el texto libre ni `elements`;
- **sale**: las respuestas de los espacios del motor, de AJAX y de `wp/v2` (claves de `meta`, plantilla) con las reglas; el índice `/wp-json/` y los `OPTIONS` con sus esquemas renombrados; la vista previa abre con `dg-preview`.

### Lo que el servidor lee para pintar

Se publica **el contenido original, con la palabra enmascarada** en una forma que su lector decodifica igual, así sirve con la marca encendida y apagada sin desplegar nada:
- SVG de separadores y HTML: `&#101;lementor` (la capa lo reconoce en las clases);
- Twig de los componentes atómicos: en el código, la cadena partida `('elemen' ~ 'tor/macros')` (Twig y Twing la unen igual); en el HTML, la entidad; los comentarios con la palabra se quitan;
- JSON del kit de ventas: `\u0065lementor` (`json_decode` da lo de siempre);
- guías Markdown del MCP: un separador invisible (U+200D) dentro de la palabra, que el mapa del MCP, la ruta vieja y el proxy del editor quitan al responder.

Las licencias se conservan enteras (la GPL pide no quitar los avisos) en un `.php` que pedido por URL no devuelve nada. Las cuatro fuentes con la palabra en los nombres de glifos (`eicons` y `fa-brands`, `.ttf` y `.eot`) se corrigen con una palabra del mismo largo y las sumas de comprobación recalculadas. Los idiomas `elementor-<idioma>.l10n.php` salen como `dg-<idioma>.l10n.php` (`lang_dir_for_domain`, `load_translation_file`); con la marca apagada, las traducciones de los scripts se leen del original (sus claves son las del JS original).

### Redes

La carpeta de originales es una por instalación (`wp-content/uploads/digitalisimo-elements/base-<versión>/`), compartida por todos los sitios porque los archivos del plugin también lo son. Se borra al encender la marca, en un sitio o en la red, sólo si todos los sitios quedan encendidos (`Package::network_all_on()`, hasta 500 sitios). Si después un sitio la apaga o queda bloqueado, su primera petición la vuelve a desplegar. Mientras algún sitio de la red la necesite, sus archivos se pueden pedir por URL desde cualquier sitio de la red.

### Exportaciones

Con la marca en vigor: la plantilla suelta sale como `dg-<id>-<fecha>.json` con la plantilla de página propia; el WXR de Herramientas → Exportar con `digitalisimo_library`, sus taxonomías y `_dg_*`; al importar se aceptan los dos nombres, y escribir `digitalisimo_canvas` en `_wp_page_template` guarda `elementor_canvas`. Pendientes: el ZIP de varias plantillas y los kits.

### Peso del ZIP

El paquete transformado no publica el JS ni el CSS sin minificar del motor que tienen su `.min` (sólo se cargan con `SCRIPT_DEBUG`; la URL sirve el `.min`). Medido el 2026-11-27: ZIP normal 31,8 MB, transformado 35,2 MB (antes de la poda, 52 MB).
