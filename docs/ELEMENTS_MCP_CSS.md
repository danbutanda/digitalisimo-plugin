# El CSS tras cada escritura del MCP de DIGITALÍSIMO Elements

El servidor MCP del núcleo incluido (`POST /wp-json/elementor/mcp`, ver `docs/ELEMENTS_MCP_WOO.md` para conectarse)
escribe documentos con `build-composition`, `manage-elements`, `update-page-settings`, `manage-component` y
`publish-document`. Desde la versión 4.3.0.186, cada escritura deja el CSS de ese documento listo para
regenerarse, como al guardar y publicar en el editor, sin borrar la caché de todo el sitio y sin editar nada de
`digitalisimo-elements/elementor/`. El código vive en `digitalisimo-elements/modules/digitalisimo-mcp/`.

## El incidente (2026-11-13)

Página 14586 de digitalisimo.lat (V4 atómica, plantilla lienzo, Elements 4.3.0.185 con el núcleo 4.3.5) reconstruida con
`build-composition` (`replace_children`). La primera reconstrucción del día se vio bien; un minuto después, otra con
cambios de estilo respondió `success` y `_elementor_data` quedó con las clases nuevas, pero
`uploads/elementor/css/local-14586-frontend-desktop.css` (y tablet y celular) seguía con el `Last-Modified`, el `ver` y
las clases de la primera. La página y el enlace firmado de `create-preview-link` salían sin estilos.
`update-page-settings` no lo arregló; «Regenerar CSS» (`DELETE /wp-json/elementor/v1/cache`, todo el sitio) sí, en cada
reconstrucción.

## Causa (núcleo 4.3.5, rutas bajo `digitalisimo-elements/elementor/`)

1. **Dónde vive el CSS.** Los estilos locales de los elementos atómicos se sirven desde
   `uploads/elementor/css/local-<id>-<contexto>-<punto>.css` (`modules/atomic-widgets/styles/atomic-styles-manager.php`
   y `css-files-manager.php`). Su validez está en la opción `elementor_atomic_cache_validity__local`, un árbol
   `[<id>][<contexto>][<punto>]` (`styles/cache-validity/`). Mientras la hoja es válida, `CSS_Files_Manager::get()`
   sirve el archivo tal cual; el `ver` de la URL es la meta de `[local, <id>, <contexto>]`, un `uniqid()` que sólo se
   renueva cuando esa hoja estaba inválida al pintar. Las clases globales usan lo mismo con la raíz `global`
   (`modules/global-classes/atomic-global-styles.php`).
2. **Dos contextos.** `Atomic_Widget_Styles::register_styles()` (`modules/atomic-widgets/styles/atomic-widget-styles.php`)
   usa `preview` si `is_editor_or_preview()` (la vista previa de WordPress, que incluye un borrador abierto por quien
   puede editarlo) y `frontend` en otro caso: el visitante de una página publicada y el **enlace firmado de
   `create-preview-link`**, cuyo manejador (`modules/mcp/preview/public-preview-handler.php`) marca la entrada como
   publicada y pinta la instantánea con las hojas y la caché HTML de la página.
3. **Qué invalida un guardado.** En `elementor/document/after_save`, `Atomic_Widget_Styles::register_hooks()` invalida
   `[local, <id principal>]` entero sólo si el documento guardado está publicado (`Utils::is_post_published()`, que mira
   el estado del propio documento); si no, sólo `[local, <id>, preview]`. El MCP guarda (`Composition_Persister::
   insert_and_save()` → `Document_Mutation_Save::elements_preserving_live_status()` →
   `Document_Mutator::save_as_draft( …, true )`, en `core/utils/document/document-mutator.php`) en el propio documento
   si es un borrador y en su autoguardado (estado `inherit`) si está publicado: en los dos casos la hoja `frontend`
   **queda válida**.
4. **Por qué la primera sí y las siguientes no.** La hoja `frontend` de la página no existía o estaba inválida (página
   nueva, o una purga de todo el sitio anterior: «Regenerar CSS», la actualización del plugin —`upgrader_process_complete`
   llama a `clear_cache()`—, o una llamada a `manage-elements`, `manage-classes` o `manage-global-variable`, que la
   hacen). La primera visita en contexto `frontend` (el enlace firmado o la página publicada) la generó con los datos de
   la primera reconstrucción y la marcó válida con su `ver`. Las reconstrucciones siguientes sólo invalidaron la hoja
   `preview`: el enlace firmado y la página siguieron recibiendo el mismo archivo, con el mismo `ver` y las clases
   viejas, mientras el HTML ya traía las nuevas. `update-page-settings` guarda el documento y cae en la misma regla.
   «Regenerar CSS» (`Files\Manager::clear_cache()` → `elementor/core/files/clear_cache`) invalida todas las hojas del
   sitio: por eso lo arreglaba cada vez.
5. **Por qué `manage-elements` no fallaba.** `Manage_Elements_Ability::execute()` llama a
   `Plugin::$instance->files_manager->clear_cache()` tras guardar: purga el CSS de todo el sitio. `build-composition` no.
6. **`publish-document`.** `Publish_Document_Ability` (`modules/mcp/abilities/publish-document-ability.php`) promueve el
   autoguardado con `Revisions_Manager::restore_revision()` (copia las metas y regenera sólo el CSS clásico,
   `post-<id>.css`) y publica con `wp_update_post()`: no pasa por `Document::save()`, así que no invalida las hojas
   atómicas ni borra la caché HTML del documento (`_elementor_element_cache`, 24 h por defecto). Tras la promoción, el
   visitante recibía el HTML **y** el CSS anteriores; si alguien había abierto antes un enlace firmado, el CSS de la
   instantánea.
7. **El enlace firmado escribe en la página.** Al pintar la instantánea con la hoja `frontend` y la caché HTML de la
   página, si esa hoja estaba inválida la genera con el CSS de la instantánea, y guarda el HTML de la instantánea en la
   caché del documento (`Document::print_elements()` → `set_document_cache()`, sobre el ID de la página): un enlace
   viejo abierto después de otra escritura dejaba a la página con el CSS y el HTML viejos (en el ensayo, hasta el
   borrador que abría su administradora mostraba el HTML del enlace viejo). Y tras una purga general (la que hacen `manage-elements`,
   `manage-classes` y `manage-global-variable`), `Post_CSS` regenera `post-<id>.css` y `_elementor_css` de la página con
   los ajustes de la instantánea (`_elementor_page_settings` de la revisión) y `Assets` guarda en ella los
   `_elementor_page_assets` de la instantánea: el siguiente visitante de la publicada recibía, por ejemplo, el fondo
   pendiente.

## El arreglo (`modules/digitalisimo-mcp/`, cargado desde `pro-elements.php`)

- **`Css_Sync`** (`class-css-sync.php`). Sólo mientras corre una herramienta del constructor (`elementor/…`): su
  `execute_callback` se envuelve con `wp_register_ability_args` y abre y cierra el ámbito en un `try/finally` (también
  si devuelve un `WP_Error`, cuando WordPress no dispara `wp_after_execute_ability`, o lanza una excepción; sin ese
  filtro, los ganchos `wp_before/after_execute_ability` de respaldo), y la ruta REST del proxy del editor
  (`/elementor/v1/mcp-proxy`) abre el mismo ámbito. Anota el documento vivo que cambió: su propio `Document::save()` (no
  el de un autoguardado), `_elementor_data` escrito en él (la promoción de `publish-document`) o su paso a `publish`. Al
  cerrar la herramienta (o al terminar la petición) invalida, sólo de esos documentos: los nodos `[local, <id>]` y
  `[global, <id>]` de `Cache_Validity` (todos los contextos y puntos), la lista de componentes incrustados y la caché
  HTML. Los archivos no se borran: el siguiente render los reescribe de forma atómica con un `ver` nuevo. Si la entrada
  está publicada y en el ámbito no pasó por `save_post` (la promoción sólo copia metas), le hace un
  `wp_update_post( [ 'ID' => <id> ] )` sin cambios: el guardado normal que hace el editor al publicar, con `save_post`,
  `edit_post`, `post_updated`, `clean_post_cache` y `wp_after_insert_post`, que es lo que escuchan las cachés de página
  (LSCache, WP Rocket, W3TC, Cloudflare APO…) para purgar esa URL. Se eligió frente a disparar `clean_post_cache` y
  `edit_post`/`save_post` a mano porque no simula un guardado a medias (con `save_post` falso, los complementos que
  guardan sus cajas leerían un `$_POST` vacío) y llega a todas las cachés sea cual sea el gancho que escuchen; cuesta
  lo mismo que publicar en el editor (una revisión más y `post_modified`). Un cambio que el MCP deja en el autoguardado
  de una página publicada no toca la publicada (sigue sirviendo la versión anterior hasta publicar, como en el editor).
- **`Preview_Link`** (`class-preview-link.php`): cada apertura de un enlace firmado válido de `create-preview-link`
  (firma, vigencia y revisión de esa página, comprobadas con `Preview_Token` del núcleo). Durante esa petición la caché
  HTML del documento se apaga (`pre_option_elementor_element_cache_ttl` → `disable`: ni se lee ni se guarda; antes, el
  HTML de la instantánea quedaba 24 h en la caché de la publicada). Si la página está **publicada** (o privada), la
  petición no escribe nada que vean otros, ni opciones, ni metas, ni archivos, ni caché:
  - **Validez de las hojas, sólo en memoria.** Las cinco opciones de `Cache_Validity` que escribe el render de una
    página (`elementor_atomic_cache_validity__local`, `__global`, `__global_related`, `__global_related-reverse` y
    `__component-styles-related-posts`) se leen y se escriben en una copia en memoria (`pre_option_<opción>`,
    `pre_update_option_<opción>` y `pre_update_site_option_<opción>` devuelven la copia o lo anterior), que se descarta
    al terminar. En esa copia el contexto `frontend` de la página queda válido y cada hoja suya, local y global, válida
    sin archivo (`should_exist` falso): el núcleo no sirve ni regenera ninguna `local-<id>-frontend-*.css` ni
    `global-<id>-frontend-*.css`, y la lista de componentes de la instantánea se calcula ahí. Un visitante que llega
    mientras tanto lee la base de datos tal cual (no hay ventana: antes el congelado se escribía en la base durante los
    3-5 s de la petición). Una purga real de todo el sitio durante la petición se respeta y la página se vuelve a
    congelar en la copia.
  - **CSS atómico de la instantánea, en línea y en el orden del núcleo.** Sus clases globales (también las de lo que
    incrusta, `elementor/document/related_posts`, en el orden del kit) y sus estilos locales se parten por punto como
    `Atomic_Styles_Manager::group_by_breakpoint()` y se pintan con `Styles_Renderer`: una hoja en línea por punto y
    raíz (`digitalisimo-mcp-preview-<global|local>-<punto>`), colocada en la cola de estilos detrás de las del núcleo
    de ese punto y esa raíz (escritorio y después de mayor a menor; en cada punto `base`, `default`, globales y
    locales). Así un `padding: 60px; @media(--mobile){padding: 6px}`, que el MCP guarda como [celular, escritorio],
    se ve a 375 px con 6px, como la página publicada (antes, 60px).
  - **CSS de página y metas.** Ninguna meta `_elementor_*` de la página se guarda, se añade ni se borra
    (`add/update/delete_post_metadata`, salvo el borrado de una meta en todas las entradas de una purga general): ni
    `_elementor_css`, ni `_elementor_page_assets`, ni el uso de clases globales. Su `_elementor_css` se lee `empty`, así
    `Post_CSS` del núcleo no sirve ni regenera `post-<id>.css` (antes, tras una purga general, lo regeneraba con el
    fondo de la instantánea y un visitante lo recibía), y el CSS de página de la instantánea (ajustes de la página,
    estilos clásicos, CSS propio) va en línea donde el núcleo encola esa hoja (`Snapshot_Post_Css`,
    `class-snapshot-post-css.php`: el `Post_CSS` del núcleo en memoria y sin archivo, como su `Post_Preview` del
    editor). Sus assets se calculan con la instantánea (`_elementor_page_assets` se lee vacío) sin guardarse.

  Abrir el enlace de una publicada no cambia ninguna opción, meta ni archivo de la publicada (ni sus hojas atómicas, ni
  `post-<id>.css`, ni su caché HTML), y el HTML que guarda una caché de página conserva sus reglas. Si es un
  **borrador** (nadie guarda su HTML), sus hojas y su lista de componentes se invalidan antes y después, como antes de
  la revisión.
- **Claves del núcleo**: `'local'`, `'global'`, `'related'`, `'related-reverse'`, `component-styles-related-posts`,
  `elementor_atomic_cache_validity__`, `_elementor_element_cache`, `_elementor_css`, `_elementor_page_assets`,
  `_elementor_data` y `elementor_preview_token` salen de `Atomic_Widget_Styles::STYLES_KEY`,
  `Atomic_Global_Styles::STYLES_KEY`, `::RELATED_KEY` y `::RELATED_REVERSE_KEY`, `Component_Styles::CACHE_ROOT_KEY`,
  `Cache_Validity_Item::CACHE_KEY_PREFIX`, `Document::CACHE_META_KEY`, `Post_CSS::META_KEY`,
  `Assets::ASSETS_META_KEY`, `Document::ELEMENTOR_DATA_META_KEY` y `Preview_Token::QUERY_ARG` (`Css_Sync::key()`),
  con el valor de 4.3.5 de respaldo; la unitaria comprueba que coinciden.
- **Lo que cambia el kit** (clases globales, variables, estilos predeterminados) ya lo regenera el núcleo:
  `manage-classes`, `reorder-classes` y `manage-global-variable` purgan el CSS de todo el sitio
  (`files_manager->clear_cache()`), y `manage-default-styles` dispara `elementor/default_styles/update`, que invalida la
  hoja `default`. El módulo no añade nada ahí (ver «Límites»).
- **`Font_Stack`** (`class-font-stack.php`, `class-font-family-transformer.php`): ver la observación secundaria.

## Observación secundaria (verificada)

- **`font-family` con comillas anidadas: error del núcleo, corregido aquí.** `Font_Family_Transformer`
  (`modules/atomic-widgets/props-resolver/transformers/styles/font-family-transformer.php`) modela una sola familia: si
  el valor no empieza **y** termina con comillas y tiene un espacio, lo envuelve entero. El MCP guarda la lista que
  recibe tal cual (`String_Property_Converter`, `css-converter/converters/string-property-converter.php`), así que
  `font-family: "Geist Mono", ui-monospace, monospace` salía `font-family:""Geist Mono", ui-monospace, monospace";`,
  una declaración que el navegador descarta (Chrome calculaba la fuente del tema). `Font_Stack` envuelve el
  transformador del núcleo (acción `elementor/atomic-widgets/styles/transformers/register`, prioridad 20): sólo cuando el
  valor es una lista (comas fuera de comillas y paréntesis) escribe cada familia como en CSS (la que trae comillas igual,
  la de varias palabras entre comillas, la de una palabra, genérica o `var(…)` sin ellas); una sola familia sigue en el
  núcleo, sin cambios. No se reescribe lo guardado: lo ya escrito sale bien al regenerarse su CSS. Normalizar la
  entrada del MCP no bastaba: ninguna forma de guardar la lista sobrevive al transformador del núcleo sin perder las
  familias de respaldo.
- **`\n` literal antes de la llave: del núcleo, inofensivo, documentado.** `Styles_Renderer::custom_css_to_css_string()`
  (`modules/atomic-widgets/styles/styles-renderer.php`, línea 192) añade `'\n'` entre comillas simples (una barra y una
  `n`, no un salto de línea) tras el CSS propio de cada variante (`custom_css`, donde el MCP pone los selectores anidados
  como `& strong { … }`). El navegador lo lee como un identificador suelto antes de `}` y lo descarta: Chrome aplica la
  regla anidada (`font-weight: 800` en el `<strong>`). Sólo dañaría una última declaración sin `;`, y el MCP nunca la
  escribe así (`Css_Converter::convert()` termina cada declaración con `;` y `Style_Variants_Merger` cierra cada bloque
  anidado con `}`). El texto lo pinta el renderizador del núcleo y no tiene filtro: quitarlo exigiría reescribir los
  archivos generados, así que se deja.

## Pruebas

- `tests/elements-mcp-css.php` (en `validate.yml` y `release.yml`; PHP 7.4, 8.2 y 8.5): ámbito de las herramientas
  (también envuelto con `try/finally`), autoguardado y promoción (con el aviso a la caché de página), herramientas
  anidadas, una que falla a medias, el proxy, enlaces firmados reales (`Preview_Token` del núcleo: publicada congelada
  sólo en memoria sin cambiar nada guardado ni a mitad de la petición, con escrituras del núcleo y una purga durante
  ella; borrador invalidado; caché HTML apagada), el CSS en línea de la instantánea por punto y en la posición de las
  hojas del núcleo, las metas `_elementor_*` de la publicada que no se escriben y su CSS de página en línea, la lista de
  componentes (`Cache_Validity` del núcleo), las claves frente a las constantes del núcleo y las listas de familias con
  el transformador real del núcleo. Falla con el código anterior (no existe el módulo).
- `tests/validate-suite.mjs` exige los cinco archivos de `modules/digitalisimo-mcp/` en el ZIP de Elements.
- `tests/playground/elements-mcp-css.sh` (manual, en la PC): Playground con WordPress 7.1, una contraseña de aplicación
  y JSON-RPC real contra `/wp-json/elementor/mcp`, en PHP 7.4, 8.2 y 8.5. Tras cada escritura abre el borrador como su
  administradora, un enlace firmado nuevo como visitante y la página publicada, y comprueba que cada clase `e-…` y global
  del HTML tiene regla en el CSS cargado y que el `ver` cambió. Los escenarios R1–R6 son los de la revisión adversarial:
  lo que recibe el HTML ya entregado de una publicada (el de una caché de página) tras abrir un enlace firmado, un enlace
  viejo abierto después de publicar, el mismo enlace abierto varias veces, un proceso largo tras una herramienta que
  falla, un componente usado por otra página y los ganchos de purga de la promoción. R7a y R7b son los de la segunda
  revisión, sin y con una purga general antes: la sonda toma la huella de la publicada directo de la base y del disco
  (sus nodos en las cinco opciones de validez, todas sus metas, sus archivos CSS y su fila) antes, a mitad (`shutdown`
  de la petición firmada) y después de abrir el enlace, y deben ser iguales; un visitante con la validez de mitad de
  la petición recibe la publicada con todas sus reglas y sin el fondo pendiente; el enlace muestra la instantánea sin
  ninguna hoja de la publicada, con su fondo y con la regla de celular después de la de escritorio (Chrome calcula
  6px a 375 px). Con
  `ELEMENTS_ZIP=dist/digitalisimo-elements-4.3.0.185.zip EXPECT_FAIL=1` comprueba que con el código anterior fallan
  exactamente las comprobaciones esperadas.

## Límites

- Las herramientas del núcleo `manage-elements`, `manage-classes`, `reorder-classes` y `manage-global-variable` siguen
  purgando el CSS de todo el sitio, como hace el núcleo: la llamada está dentro de su `execute()` y no tiene filtro. El
  módulo no añade ninguna purga general.
- El enlace firmado de una página publicada con cambios pendientes muestra esos cambios: en el ensayo,
  `create-preview-link` firmó el autoguardado (`<id>-autosave-v1`, la última revisión por ID cuando
  `wp_save_post_revision()` no crea una nueva). Sale con su propio CSS en línea y la publicada no se entera.
- En ese enlace de una publicada, el CSS atómico de la página va en línea en la posición de sus hojas, pero cada hoja
  en línea va detrás de las de otros documentos del mismo punto y raíz (cabecera, pie, componentes): las reglas de una
  clase global salen iguales en todas (son del kit) y las locales son de elementos distintos, así que la cascada es la
  del núcleo. Si la cola de estilos no tiene la forma del núcleo (otra hoja en medio de las atómicas), las hojas en
  línea van al final. Las demás hojas que pinta esa petición (las de otros documentos, si estaban inválidas) se
  escriben como en cualquier visita, con los datos de esos documentos, y su validez no se guarda: la siguiente visita
  las vuelve a escribir.
- El enlace firmado de un **borrador** no carga la hoja del documento (`post-<id>.css`: el fondo y los demás ajustes
  clásicos de la página): el manejador lee `_elementor_css` de la revisión firmada y el ensayo la encontró en `empty`.
  Es igual con 4.3.0.185 y no depende de las escrituras del MCP; no se corrige aquí. El borrador que abre su
  administradora y la página publicada sí la cargan.
- Un guardado hecho fuera del MCP (el editor) sigue la regla del núcleo: un borrador sólo invalida su hoja `preview`.
- En el lienzo del editor (JavaScript) una lista de familias sigue saliendo como la pinta el núcleo; la corrección es
  del CSS que se sirve. Con una lista, la primera familia no se encola sola desde Google Fonts (el núcleo encola el
  valor completo como nombre de fuente, que no existe): debe cargarla el kit o el tema.
- Con el plugin Elementor original activo el módulo sigue funcionando (usa sólo ganchos y clases públicas del núcleo,
  comprobando que existen); no se probó con versiones del núcleo anteriores a 4.3.5.
