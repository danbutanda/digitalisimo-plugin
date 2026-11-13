# El CSS tras cada escritura del MCP de DIGITALÍSIMO Elements

El servidor MCP del núcleo incluido (`POST /wp-json/elementor/mcp`, ver `docs/ELEMENTS_MCP_WOO.md` para conectarse)
escribe documentos con `build-composition`, `manage-elements`, `update-page-settings`, `manage-component` y
`publish-document`. Desde la versión @NEXT_ELEMENTS@, cada escritura deja el CSS de ese documento listo para
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
   borrador que abría su administradora mostraba el HTML del enlace viejo).

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
  HTML de la instantánea quedaba 24 h en la caché de la publicada) y la lista de componentes se invalida antes y
  después. Si la página está **publicada** (o privada), sus hojas `frontend` locales y de clases globales se congelan:
  las que el núcleo regeneraría (inválidas, o válidas sin el archivo que deberían tener) se marcan válidas sólo para esa
  petición, con `should_exist` según haya archivo, así el núcleo sirve los archivos tal cual o ninguno y no escribe
  nada; los estilos de la instantánea (sus clases globales en el orden del núcleo y sus estilos locales, con
  `Styles_Renderer` del núcleo) se imprimen **en línea** detrás; al terminar, lo congelado vuelve a inválido (nunca a
  válido). Abrir el enlace no cambia ningún archivo ni ningún `ver` de la publicada, y el HTML que guarda una caché de
  página conserva sus reglas. Si es un **borrador** (nadie guarda su HTML), sus hojas se invalidan antes y después,
  como antes de la revisión.
- **Claves del núcleo**: `'local'`, `'global'`, `component-styles-related-posts`, `_elementor_element_cache`,
  `_elementor_data` y `elementor_preview_token` salen de `Atomic_Widget_Styles::STYLES_KEY`,
  `Atomic_Global_Styles::STYLES_KEY`, `Component_Styles::CACHE_ROOT_KEY`, `Document::CACHE_META_KEY`,
  `Document::ELEMENTOR_DATA_META_KEY` y `Preview_Token::QUERY_ARG` (`Css_Sync::key()`), con el valor de 4.3.5 de
  respaldo; la unitaria comprueba que coinciden.
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
  anidadas, una que falla a medias, el proxy, enlaces firmados reales (`Preview_Token` del núcleo: publicada congelada,
  borrador invalidado, caché HTML apagada), la lista de componentes (`Cache_Validity` del núcleo), las claves frente a
  las constantes del núcleo y las listas de familias con el transformador real del núcleo. Falla con el código anterior
  (no existe el módulo).
- `tests/validate-suite.mjs` exige los cinco archivos de `modules/digitalisimo-mcp/` en el ZIP de Elements.
- `tests/playground/elements-mcp-css.sh` (manual, en la PC): Playground con WordPress 7.1, una contraseña de aplicación
  y JSON-RPC real contra `/wp-json/elementor/mcp`, en PHP 7.4, 8.2 y 8.5. Tras cada escritura abre el borrador como su
  administradora, un enlace firmado nuevo como visitante y la página publicada, y comprueba que cada clase `e-…` y global
  del HTML tiene regla en el CSS cargado y que el `ver` cambió. Los escenarios R1–R6 son los de la revisión adversarial:
  lo que recibe el HTML ya entregado de una publicada (el de una caché de página) tras abrir un enlace firmado, un enlace
  viejo abierto después de publicar, el mismo enlace abierto varias veces, un proceso largo tras una herramienta que
  falla, un componente usado por otra página y los ganchos de purga de la promoción. Con
  `ELEMENTS_ZIP=dist/digitalisimo-elements-4.3.0.185.zip EXPECT_FAIL=1` comprueba que con el código anterior fallan
  exactamente las comprobaciones esperadas.

## Límites

- Las herramientas del núcleo `manage-elements`, `manage-classes`, `reorder-classes` y `manage-global-variable` siguen
  purgando el CSS de todo el sitio, como hace el núcleo: la llamada está dentro de su `execute()` y no tiene filtro. El
  módulo no añade ninguna purga general.
- El enlace firmado de una página publicada con cambios pendientes muestra esos cambios: en el ensayo,
  `create-preview-link` firmó el autoguardado (`<id>-autosave-v1`, la última revisión por ID cuando
  `wp_save_post_revision()` no crea una nueva). Sale con su propio CSS en línea y la publicada no se entera.
- En ese enlace de una publicada, el CSS en línea de la instantánea va detrás de las hojas de la publicada: si una clase
  conserva su ID y la instantánea le quitó una propiedad, la de la publicada sigue aplicándose en la vista previa (sólo
  ahí). Ningún archivo ni `ver` de la publicada cambia.
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
