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
   caché del documento: un enlace viejo abierto después de otra escritura dejaba a la página con el CSS y el HTML viejos.

## El arreglo (`modules/digitalisimo-mcp/`, cargado desde `pro-elements.php`)

- **`Css_Sync`** (`class-css-sync.php`). Sólo mientras corre una herramienta del constructor (`elementor/…`): las
  acciones `wp_before_execute_ability` y `wp_after_execute_ability` de la Abilities API de WordPress (servidor MCP, REST
  de abilities) o la ruta REST del proxy del editor (`/elementor/v1/mcp-proxy`, que no pasa por ellas). Anota el
  documento vivo que cambió: su propio `Document::save()` (no el de un autoguardado), `_elementor_data` escrito en él (la
  promoción de `publish-document`) o su paso a `publish`. Al cerrar la herramienta (o al terminar la petición, si falló
  a medias) invalida, sólo de esos documentos: los nodos `[local, <id>]` y `[global, <id>]` de `Cache_Validity` (todos
  los contextos y puntos), la lista de componentes incrustados (`component-styles-related-posts`) y la caché HTML. Los
  archivos no se borran: el siguiente render los reescribe de forma atómica con un `ver` nuevo. Un cambio que el MCP
  deja en el autoguardado de una página publicada no toca la publicada (sigue sirviendo la versión anterior hasta
  publicar, como en el editor). Cada apertura de un enlace firmado válido (firma, vigencia y revisión de esa página,
  comprobadas con `Preview_Token` del núcleo) invalida la página antes de pintar (la instantánea sale con su propio
  CSS) y al terminar (la página no se queda con el CSS ni el HTML de la instantánea).
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

- `tests/elements-mcp-css.php` (en `validate.yml` y `release.yml`; PHP 7.4, 8.2 y 8.5): ámbito de las herramientas,
  autoguardado y promoción, herramientas anidadas, una que falla a medias, el proxy, enlaces firmados reales
  (`Preview_Token` del núcleo), la lista de componentes (`Cache_Validity` del núcleo) y las listas de familias con el
  transformador real del núcleo. Falla con el código anterior (no existe el módulo).
- `tests/playground/elements-mcp-css.sh` (manual, en la PC): Playground con WordPress 7.1, una contraseña de aplicación
  y JSON-RPC real contra `/wp-json/elementor/mcp`, en PHP 7.4, 8.2 y 8.5. Tras cada escritura abre el borrador como su
  administradora, un enlace firmado nuevo como visitante y la página publicada, y comprueba que cada clase `e-…` y global
  del HTML tiene regla en el CSS cargado y que el `ver` cambió. Con `ELEMENTS_ZIP=dist/digitalisimo-elements-4.3.0.185.zip
  EXPECT_FAIL=1` comprueba que con el código anterior fallan exactamente las comprobaciones del incidente.

## Límites

- Las herramientas del núcleo `manage-elements`, `manage-classes`, `reorder-classes` y `manage-global-variable` siguen
  purgando el CSS de todo el sitio, como hace el núcleo: la llamada está dentro de su `execute()` y no tiene filtro. El
  módulo no añade ninguna purga general.
- El enlace firmado de una página publicada muestra la publicada, no lo que el MCP dejó en su autoguardado
  (`create-preview-link` toma su instantánea de la entrada principal): para ver esos cambios hay que publicarlos
  (`publish-document`) o abrir la vista previa del editor.
- Un guardado hecho fuera del MCP (el editor) sigue la regla del núcleo: un borrador sólo invalida su hoja `preview`.
- En el lienzo del editor (JavaScript) una lista de familias sigue saliendo como la pinta el núcleo; la corrección es
  del CSS que se sirve. Con una lista, la primera familia no se encola sola desde Google Fonts (el núcleo encola el
  valor completo como nombre de fuente, que no existe): debe cargarla el kit o el tema.
- Con el plugin Elementor original activo el módulo sigue funcionando (usa sólo ganchos y clases públicas del núcleo,
  comprobando que existen); no se probó con versiones del núcleo anteriores a 4.3.5.
