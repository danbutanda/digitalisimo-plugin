# F7b · Capacidades que Bricks necesita, en DIGITALÍSIMO Elements

Evidencia y contratos de la etapa F7b de `docs/FUSION_OBJECTIVE.md` (DIGITALÍSIMO Elements 4.3.0.169). La especificación vive fuera del repositorio; este documento recoge lo verificado (§1) y lo construido por cada frente (§2–§14), con sus formatos y sus desviaciones. Si este documento y `docs/FUSION_OBJECTIVE.md` no coinciden, manda el objetivo.

## 1. Hechos del núcleo y de PRO verificados (frente 0)

Rutas relativas a `digitalisimo-elements/`, en el árbol de Elements 4.3.0.168 (núcleo 4.3.5 incluido, derivado de PRO Elements 4.3.0). Nada de Bricks se lee ni se instala: `docs/fusion/bricks.md` describe sus comportamientos con palabras propias.

### 1.1 Acciones de URL e interacciones

| Hecho | Dónde | Uso en F7b |
|---|---|---|
| `elementorFrontend.utils.urlActions.runAction( url, ...args )` ejecuta una acción registrada a partir de un *hash* `#elementor-action:action=<nombre>&settings=<base64 JSON>`; `runLinkAction( event )` lo hace para un enlace; `createActionHash( action, settings )` construye el *hash* | `elementor/assets/js/frontend.js:2316, 2329, 2339` (selector de enlaces en `:2293`) | C1: `popup_open`, `popup_close` y `offcanvas_toggle` llaman a `runAction()` con el *hash* que construye `createActionHash()`; nunca se reimplementan popups ni off-canvas |
| PRO registra `popup:open`, `popup:close`, `off_canvas:open`, `off_canvas:close`, `off_canvas:toggle` y `reload-page` con `urlActions.addAction()` | `assets/js/elements-handlers.js:3656-3657, 10537-10540, 807` | Nombres vigilados por `tests/elements-pro-inventory.mjs` (ya lo hace: `EH_URL_ACTIONS`) |
| Las interacciones atómicas se guardan en `$element['interactions']` del documento y el núcleo las analiza y valida al guardar | `elementor/modules/interactions/parser.php:33-34`, `validation.php:58`, `interactions-frontend-handler.php:104` | D6: el inventario recorre `_elementor_data` buscando `interactions` con disparadores `hover`, `click` o `scrollOn` |
| El módulo derivado de PRO sale si no hay licencia activa con la función `pro-interactions`, o si el experimento de widgets atómicos está apagado | `modules/interactions/module.php:24-44` (`is_supported_by_current_license()`, `is_experiment_active()`) | D6: se quita sólo la condición de licencia; la del experimento se conserva (sólo v4) |

### 1.2 Consultas

| Hecho | Dónde | Uso en F7b |
|---|---|---|
| El constructor de consultas de PRO aplica `apply_filters( 'elementor/query/query_args', $this->query_args, $this->widget )` al final, y `elementor/query/get_query_args/current_query` para «Consulta actual» | `modules/query-control/classes/elementor-post-query.php:156, 128` | C2: con una consulta guardada elegida, `Queries\Runtime` **fusiona** a prioridad 5 sólo las claves de selección de la consulta, resueltas con el contexto; paginación, `ignore_sticky_posts` y los filtros de `loop-filter` (prioridad 10) quedan como están (crítica 1, C1-5; §4) |
| La sección de consulta se llama `section_query` en Posts (`modules/posts/widgets/posts.php:112`), Portfolio (`:210`) y en la base del Loop Grid y el Loop Carousel (`modules/loop-builder/widgets/base.php:165`); Galería no usa el grupo de PRO; Productos de WooCommerce usa su rasgo propio (`modules/woocommerce/traits/products-trait.php`) | — | D8: el control «Consulta guardada» se añade con `elementor/element/{posts,portfolio,loop-grid,loop-carousel}/section_query/before_section_end` |
| El Loop Grid recorre sus entradas con el bucle de WordPress y pinta cada una con `the_post()` → `render_post()` → `render_post_content()`, que lee `get_the_ID()` y llama a `$document->print_content()` de la plantilla de bucle | `modules/loop-builder/skins/skin-loop-base.php:115-135, 201-220` | D9: dentro de una plantilla de bucle, `current` = `get_the_ID()`; `root` = `get_queried_object()` de la consulta principal. `Queries` apila `get_the_ID()` en `elementor/widget/before_render_content` (`elementor/includes/base/widget-base.php:628`) de los widgets de bucle y lo desapila en `elementor/frontend/widget/after_render` (`element-base.php:572`; `elementor/widget/after_render_content` no existe, crítica 1, C1-4), con poda por nivel de búfer (§4.4). La pila da el `current` del bucle interior y la profundidad. El contexto «padre» se **retiró**: con profundidad ≤ 2 coincidía siempre con `current` o con `root` |

### 1.3 Filtros

| Hecho | Dónde | Uso en F7b |
|---|---|---|
| Registro de tipos de F4-Woo: `digitalisimo_elements_loop_filter_types` (`:117`), `digitalisimo_elements_loop_filter_query` (`:151`) y `digitalisimo_elements_loop_filter_param_type` (`:251`) | `modules/loop-filter/module.php` | C3: los cuatro tipos nuevos se declaran ahí, sin tocar el derivado |
| `refresh-loop` pasa `widget_filters` de la petición, sin validar, a `register_widget_filter()` y pinta el bucle desde la base de datos con `post_id` = el documento del bucle, fuera de la consulta principal; atiende a cualquiera en documentos publicados que contengan el bucle | `modules/loop-filter/data/endpoints/refresh-loop.php:23-49`; `core/data/endpoints/refresh-base.php:20-44` | C3: el servidor sólo aplica lo que declara el widget filtro guardado (`<fid>` en el nombre); C2: `digi_ctx` validado con `is_post_publicly_viewable()` (crítica 2, C2-1 y C2-13) |
| `Element_Base::is_dynamic_content()` devuelve `true` por defecto y los widgets de bucle no lo cambian: su HTML nunca entra en la caché de elementos | `elementor/includes/base/element-base.php:308, 595-612` | C2: `data-digi-ctx` puede pintarse en el bucle sin envenenar la caché |
| El coordinador `assets/js/filters.js` (8 161 B) se engancha en `elementor-pro/modules/init/before` y `assets/js/filters-form.js` (5 673 B) registra los formularios en `element_ready` | `docs/fusion/f4woo.md` §3 | C3 reutiliza los dos; sólo `filters-form.js` crece si los controles nuevos lo exigen (tope 6 KB) |

### 1.4 Plantillas, Template y caché de elementos

| Hecho | Dónde | Uso en F7b |
|---|---|---|
| `apply_filters( 'elementor/frontend/builder_content_data', $data, $post_id )` antes de pintar un documento | `elementor/includes/frontend.php:1173` | C4: durante `get_builder_content_for_display( $template_id )` del widget Template con sobrescrituras, un filtro acotado a ese `$post_id` sustituye los ajustes de los elementos con la clave |
| El widget Template pinta `get_builder_content_for_display( $template_id )` si la plantilla está publicada; `on_import()` traduce `template_id` | `modules/library/widgets/template.php:87-102, 44-45` | C4: las sobrescrituras se registran en `elementor/widget/before_render_content` (`widget-base.php:628`) del widget `template` y se retiran en `elementor/widget/render_content` (`:663`) o en `elementor/frontend/widget/after_render` (`element-base.php:572`), lo primero que llegue (crítica 1, C1-3; §6) |
| La caché de elementos es **por documento**: `Document::print_elements()` lee y escribe el HTML del documento en la meta `_elementor_element_cache` salvo que `get_option( 'elementor_element_cache_ttl' )` valga `'disable'`; los elementos dinámicos quedan como shortcode `[elementor-element k="<secreto>" data="…"]`, cuyo `k` es el secreto de `elementor/element_cache/unique_id` y el manejador rechaza cualquier otro (no es una clave de caché) | `elementor/core/base/document.php:1834-1880`; `elementor/includes/base/element-base.php:481-487`; `elementor/modules/element-cache/module.php:54` | C4: durante el render de un Template con sobrescrituras, `pre_option_elementor_element_cache_ttl` → `'disable'` sólo para su plantilla; el widget Template es dinámico y la página de fuera puede seguir en caché (corrección de la crítica 1, C1-1) |

### 1.5 Anidados y slider

| Hecho | Dónde | Uso en F7b |
|---|---|---|
| `Widget_Nested_Base` exige `get_default_children_elements()` y `get_default_repeater_title_setting_key()`, y ofrece `get_default_children_title()`, `get_default_children_placeholder_selector()` y `get_default_children_container_placeholder_selector()` | `elementor/modules/nested-elements/base/widget-nested-base.php:25-62` | C5: `digitalisimo-nested-slider` las implementa |
| `nested-tabs`: secciones `section_tabs` (`:111`), `section_tabs_responsive` (`:375`), `section_tabs_style` (`:448`), `section_title_style` (`:717`), `icon_section_style` (`:851`), `section_box_style` (`:994`) | `elementor/modules/nested-tabs/widgets/nested-tabs.php` | C6: el control «Presentación» entra tras `section_tabs_style`; en `nested-accordion`, tras `section_accordion_style` («Acordeón», `elementor/modules/nested-accordion/widgets/nested-accordion.php:399`), su primera sección de Estilo (§8) |
| PRO ya tiene un carrusel anidado, `nested-carousel`, con Swiper | `modules/nested-carousel/widgets/nested-carousel.php` | C5: el slider propio existe porque no carga Swiper; nombre y palabras clave distintos en el panel |
| El núcleo no tiene ninguna regla `[hidden]`, y `.e-con,.e-con>.e-con-inner{display:var(--display)}` y `.elementor-column{display:flex;…}` le ganan a la hoja del navegador | `elementor/assets/css/frontend.min.css` | C1, C5, C9: regla propia `:is(.elementor-element,.elementor-column)[hidden]{display:none!important}` sólo donde se usa (crítica 2, C2-2) |
| El Slider Optimizado declara los handles `digitalisimo-slider-optimizado` (hoja y script); su hoja es una marquesina continua sin reglas de diapositivas, flechas ni puntos | `modules/digitalisimo-slider/class-widget.php:12-13` | C5: hoja y motor propios; el Slider Optimizado no cambia (crítica 2, C2-22) |

### 1.6 Etiquetas dinámicas y formularios

| Hecho | Dónde | Uso en F7b |
|---|---|---|
| El gestor del núcleo instancia etiquetas por nombre: `create_tag( $tag_id, $tag_name, $settings )` y `get_tag_data_content( $tag_id, $tag_name, $settings )`; `parse_tags_text()` sustituye cada `[elementor-tag …]` de un texto | `elementor/core/dynamic-tags/manager.php:211, 236, 61` | C8: la sintaxis corta `{grupo:nombre}` se resuelve con `get_tag_data_content()` de la etiqueta equivalente; no se inventa otro motor ni se usa la sintaxis interna en el texto del usuario |
| Los tipos de campo de PRO añaden controles al repetidor con `add_action( 'elementor/element/form/section_form_fields/before_section_end', [ $this, 'update_controls' ] )` | `modules/forms/fields/field-base.php:108-109` | C9: los controles de las reglas entran por el mismo gancho con `update_control( 'form_fields', [ 'fields' => … ] )` |
| `Form_Record` ofrece `get( $property )`, `set()`, `get_field( $args )`, `remove_field( $id )`, `update_field( $field_id, $property, $value )` y `validate( $ajax_handler )`; `validate()` añade el error de obligatorio vacío (`:110-111`) **antes** de `do_action( 'elementor_pro/forms/validation' )` (`:142`), y `Ajax_Handler::add_error()` pone el éxito a falso (`ajax-handler.php:248-256`; `$errors` es pública, `:21`) | `modules/forms/classes/form-record.php:44-144, 186-214` | C9: en `elementor_pro/forms/validation` (prioridad 5), los campos ocultos por sus reglas se quitan del registro con `remove_field()`, su error con `unset( $ajax_handler->errors[ $id ] )` y, sin errores, `set_success( true )` (crítica 1, C1-2) |
| El editor v3 ya tiene selección múltiple (Ctrl/Cmd + clic: `request:edit` con `append`) y «Pegar estilo» sobre lo seleccionado, con un solo registro de historial; `document/elements/paste-style` acepta `containers` y `storageKey`, y decide qué es estilo con `isStyleTransferControl()`: `style_transfer` si el control lo declara; si no, todo lo que no esté en la pestaña Contenido o tenga `selectors` o `prefix_class` | `elementor/assets/js/editor.js:10335, 9611, 14807-14839, 9758-9760` | C7: «Aplicar a todos» = `paste-style` con una clave propia; todo control nuevo que no sea de estilo lleva `style_transfer => false` (la semilla de F7a lo respeta, `modules/digitalisimo-design/presets/assets/presets-editor.js:70`) |
| La etiqueta de parámetros de la petición se llama `request-arg`; `acf-text` admite campos `password` y `email`, y ACF lee páginas de opciones con `options:CAMPO` | `modules/dynamic-tags/tags/request-parameter.php:15`; `modules/dynamic-tags/acf/tags/acf-text.php:103-109` | C8: `{query:…}` no usa `request-arg` (imprime el valor de una lista del administrador) y `{acf:…}` excluye opciones y campos sensibles |
| `motion` expone `MotionGlobalConfig.skipAnimations`, que las interacciones de PRO leen de `window.Motion` | `elementor/assets/lib/motion/motion.js:56, 5527-5528` | D6: con movimiento reducido, un script en línea lo activa sin tocar los paquetes |

### 1.7 Lo que el frente 0 corrige en la especificación v1

- **Widgets con «Consulta guardada»:** Posts, Portfolio, Loop Grid y Loop Carousel (no Galería, que no usa el grupo de PRO; Productos de WooCommerce queda fuera: tiene su propio rasgo de consulta).
- **Contexto «padre»:** no existe una pila en PRO; la aporta `Queries`, que apila en `elementor/widget/before_render_content` y desapila en `elementor/frontend/widget/after_render` de los widgets de bucle (§1.2). Después se **retiró** `parent` de las consultas guardadas: los contextos son `none`, `current` y `root` (§4.3).
- **Acciones de popup y off-canvas:** con `createActionHash()` + `runAction()` del núcleo, no construyendo el *hash* a mano.
- **Sobrescrituras y caché:** no con el identificador único del shortcode (rompería los elementos dinámicos), sino apagando la caché de la plantilla durante su render (§1.4).
- **Formularios condicionales:** hay que quitar también el error de obligatorio que el formulario ya añadió (§1.6).
- **Filtros, acciones, slider y etiquetas** (crítica 2): ver la sección V de la especificación; aquí sólo quedan los hechos de código en que se apoyan (§1.3, §1.5 y §1.6).

## 2. Módulo, arranque, contadores, usos y borrado (frente A)

Rutas relativas a `digitalisimo-elements/modules/digitalisimo-f7b/` salvo que se diga otra cosa.

- **Arranque.** Dos líneas en `pro-elements.php`: `Filters\Module::init()` **antes** de `Woo\Module::init()` (los filtros de F4-Woo extienden la base neutra, §5) y `F7b\Module::init()` después. `class-module.php` registra la autocarga acotada a `Digitalisimo\Elements\F7b\` (minúsculas, `_` → `-`, prefijos `class-`, `interface-` o `trait-`, hasta tres subespacios; otro nombre nunca llega al disco), inicia la base (`BASE`: `Flags`, `Usages`, `Scan`, `Deletion`) y, en este orden, cada submódulo de `BOOT` cuyo archivo exista: `Actions`, `Queries`, `Overrides`, `Nested`, `Editor`, `Inline_Tags`, `Forms` y `Kit`. Los widgets nuevos se registran con registros propios (`Filters\Registry::WIDGETS` y `Nested\Module::WIDGETS`, con el formato de `Widget_Registry::WIDGETS`), no en `Widget_Registry`.
- **Guardas.** `Module::requires( array $symbols, string $who )` acepta clases, métodos (`Clase::metodo`), funciones (`f()`) y constantes (`const:X`). Un símbolo ausente apaga sólo ese submódulo y deja un aviso en el editor, una vez y sólo para `manage_options`. `symbol_exists()` y `call()` capturan `\Throwable`: una clase que hereda de `Elementor\Widget_Base` no se puede autocargar en `plugins_loaded`, porque el núcleo declara `Widget_Base` en `init` (`elementor/includes/managers/widgets.php:213-214, 747-748`). Por eso la guarda del slider anidado corre al registrar los widgets (§7).
- **`editing()`.** Verdadero en el editor, en su vista previa, en `admin-ajax.php?action=elementor_ajax` y en la REST `elementor/v1/…` y `elementor-pro/v1/…`; memorizado, sin consultas. Sólo mira la petición: donde decide datos (el contexto de una consulta, §4.3) se confirma con `Editor::is_edit_mode()` o `Preview::is_preview_mode()`.
- **Contadores** (`Flags`, opción `digitalisimo_elements_f7b_counts`, carga automática). Seis enteros, cada uno con la aguja que busca en `_elementor_data`:

  | Clave | Aguja | Submódulo que despierta |
  |---|---|---|
  | `actions` | `"_digitalisimo_actions_on":"yes"` | Acciones (con `actions.js`) |
  | `hidden` | `"_digitalisimo_hidden_initial":"yes"` | Acciones (sólo controles y render, sin script) |
  | `overrides` | `"digitalisimo_overrides":[{` | Sobrescrituras |
  | `saved_queries` | `"digitalisimo_saved_query":"` | Consultas guardadas |
  | `inline_tags` | `"digitalisimo_inline_tags":"yes"` | Etiquetas en el texto |
  | `form_conditions` | `"digitalisimo_conditional":"yes"` | Formularios condicionales |

  `has()` sólo lee `wp_load_alloptions()`; `active()` = `has() || editing()`. Con 0 en una capacidad, su submódulo no engancha nada en el front. Se recalculan como `Woo\Flags` (`elementor/document/before_save` y `after_save`, las metas de `_elementor_data` fuera del editor con el valor anterior de la caché, `transition_post_status`, `before_delete_post`, `deleted_post` y `digitalisimo_elements_kit_imported`), sin revisiones ni autoguardados y sólo cuando un documento empieza o deja de usar algo: **una sola consulta** para las seis claves (`SUM(CASE WHEN … LIKE …)`), una vez por petición en `shutdown`. `ensure()` crea la opción en `admin_init`.
- **Regla `[hidden]`** (`Hidden_Rule::RULE`): `:is(.elementor-element,.elementor-column)[hidden]{display:none!important}`, 73 B. `Hidden_Rule::add( $post_css )` la añade una vez por hoja de documento desde `elementor/element/parse_css` (idempotente por `get_stylesheet()`, con `spl_object_id` y `WeakReference`; la hoja guarda el CSS crudo en una propiedad privada, `elementor/includes/stylesheet.php:202`). La usan las acciones que muestran u ocultan, «Oculto al cargar» y los formularios condicionales; 0 peticiones, como los presets de F7a.
- **Usos** (`Usages`, meta multivalor `_digitalisimo_uses`): una fila por referencia, `t:<id>` por cada widget `template` y `q:<id>` por cada `digitalisimo_saved_query`, reescrita sólo si cambia (`elementor/document/after_save` y metas de `_elementor_data` fuera del editor; sin revisiones ni autoguardados). `Usages::of( $id, $kind )` devuelve `count` y `posts` (`publish`, `future` y `private`) y aparte `drafts` (`draft` y `pending`), sin la papelera ni el propio objeto, memorizado; `prime()` resuelve una página de una lista con una consulta. La búsqueda inversa (`meta_key = '_digitalisimo_uses' AND meta_value = 't:123'`) usa el índice de `postmeta` y sólo corre en la administración. La meta no viaja en los kits: un `wxr_export_skip_postmeta` propio que respeta un `true` previo, como `Meta_Blacklist::skip()` (`modules/digitalisimo-import/kits/export/class-meta-blacklist.php:63`); los metadatos de documento del núcleo ya omiten las protegidas (`elementor/core/base/document.php:1795`).
- **Recorrido por lotes** (`Scan`, evento `digitalisimo_elements_f7b_scan`): 50 documentos del constructor por lote, por ID, sin revisiones ni `auto-draft` (la papelera sí), al cambiar `DIGITALISIMO_ELEMENTS_VERSION` (en `admin_init`) y al terminar una importación de K (`digitalisimo_elements_kit_imported`, que dispara `kits/runners/class-finish.php:493`). Por documento llama a `Usages::sync()` y a `do_action( 'digitalisimo_elements_f7b_scan_document', $post_id, $elements )`; al final, `Flags::refresh()` y `do_action( 'digitalisimo_elements_f7b_scan_done' )`. Estado en la opción `digitalisimo_elements_f7b_scan` (carga automática, menos de 200 B); si se para más de 10 minutos (sin cron), `admin_init` lo reprograma. Mientras corre, la columna «Usos» dice «Calculando…» y el borrado no se bloquea, como antes de F7b.
- **Borrado bloqueado** (`Deletion`):
  - `pre_trash_post` y `pre_delete_post` devuelven `false` para una plantilla (`elementor_library`) o una consulta (`digitalisimo_query`) con usos, con un aviso de administración (lista de lo que el usuario puede editar y enlace; meta de usuario `digitalisimo_elements_f7b_notice`). Nunca `wp_die()`.
  - En `edit.php`, la fila muestra «En uso (N)» enlazado en lugar de «Papelera» o «Borrar permanentemente», y la columna «Usos» dice «N documentos · M borradores» (una raya con texto para lectores de pantalla si no hay). Las acciones en lote (`post[]`, `ids=` y «Vaciar papelera») se filtran en `load-edit.php` con el nonce `bulk-posts` y lo avisan; los enlaces directos de `post.php` (`admin_action_trash`, `admin_action_delete`) vuelven a la lista con el aviso.
  - Pantalla oculta de usos `admin.php?page=digitalisimo-elements-uses&kind=t|q&id=N` (con `digitalisimo-ui`), con los documentos que el usuario puede editar; la enlazan la biblioteca y la pantalla «Consultas» (`Deletion::uses_url()`).
  - En la biblioteca del editor, las acciones AJAX `delete_template` y `bulk_delete_templates` del núcleo se sustituyen por nombre en `elementor/ajax/register_actions` (prioridad 20): sin usos llaman a la función original, leída del registro privado del gestor AJAX (`elementor/core/common/modules/ajax/module.php:35, 116`); con usos, excepción 409 con la lista. Sólo se calcula para quien tiene `delete_post` sobre esa plantilla.
  - **Sin bloqueo, sólo aviso:** durante una sesión de importación o reversión de K (`Kits\Context::active()` y los ganchos `import-kit`/`revert-kit` 3.0 y 2.0), dentro de `wp_delete_user()` y `wpmu_delete_user()`, en WP-CLI (`WP_CLI::warning`) y con la constante `DIGITALISIMO_FORCE_DELETE`.

**Desviaciones del frente A**

| Punto | Lo que decía la especificación | Lo que hay y por qué |
|---|---|---|
| Contadores | Sólo documentos publicados, como `Woo\Flags` | Cuentan también `draft` y `pending`: así la vista previa de un borrador con acciones funciona aunque ningún documento publicado las use |
| Recálculo | En cada transición | Diferido a `shutdown`: una consulta por petición aunque Migración o K cambien muchos documentos (`flush()` lo fuerza) |
| Biblioteca del editor | Sustituir `delete_template` | También `bulk_delete_templates`: sin ella, el lote llamaría a `wp_delete_post()` sin mirar el resultado y el editor daría por borradas plantillas que siguen ahí |
| Función original del núcleo | — | El núcleo no la expone; se lee de su registro privado. `tests/elements-f7b-usages.php` vigila ese contrato: si cambia, no se sustituye nada y `pre_delete_post` sigue bloqueando, con el error genérico de la biblioteca |
| «Sólo aviso» en las excepciones | Una línea en el informe | Un aviso de administración o `WP_CLI::warning`: una línea del informe de K exigiría un código nuevo en `Report::SECTION_PREFIXES` |
| Lista de usos | «En uso (N)» con enlace | Pantalla oculta propia, porque WordPress no tiene una lista de documentos de tipos mezclados |

## 3. Acciones con destino e interacciones PRO (frente B)

### 3.1 Controles

Sección «Acciones» (`digitalisimo_actions_section`, pestaña Avanzado), tras `_section_responsive` en `common` (el núcleo repite ese gancho para `common-optimized`, `elementor/includes/base/controls-stack.php:1717-1720`; se registra una sola vez), `container`, `section` y `column`. **Sólo elementos v3:** los atómicos no la tienen.

- `_digitalisimo_hidden_initial` («Oculto al cargar: lo muestra una acción»), en cualquier elemento.
- `_digitalisimo_actions_on` (interruptor, sin `default`), que muestra el repetidor y lleva `assets` → `digitalisimo-f7b-actions`: la caché de elementos encola `actions.js` como cualquier otro recurso declarado.
- `_digitalisimo_actions` (repetidor, hasta 20 filas): `trigger`, `action`, `target_kind` (`element` o `self`), `target` (lo llena el editor), `popup_id` (el control de consulta de PRO con los popups publicados; sin él, un número), `class_name`, `attribute`, `attribute_value`, `delay` (0 a 10 000 ms, sólo «al cargar»), `animation` (el control de animación de entrada del núcleo) y `once`.

Todos con `style_transfer => false`, sin `default`, sin `selectors`, `prefix_class` ni `frontend_available`, y con `render_type => 'none'`. Los textos avisan de la vuelta atrás (§3.6). `assets/js/actions-editor.js` (4 790 B, sólo en el editor) llena «Elemento» con los elementos del documento abierto desde el modelo del editor (`elementor.documents.getCurrent().container.children`, recursivo, sin AJAX), marca «Elemento que ya no está…» para un destino guardado que falta y quita «Al hacer clic» de las filas nuevas cuando el elemento tiene varios descendientes interactivos.

### 3.2 Listas cerradas y formato

`actions/class-rules.php` (funciones puras) y `assets/js/actions.js` llevan las mismas listas; el navegador vuelve a validar cada fila, porque el dato de un kit o de otra versión no se confía.

| Lista | Valores |
|---|---|
| Disparadores | `click`, `hover`, `scroll_in`, `load` |
| Acciones | `show`, `hide`, `toggle`, `toggle_class`, `set_attribute`, `scroll_to`, `popup_open`, `popup_close`, `offcanvas_toggle`, `load_more` |
| No cambian el flujo (las únicas con `scroll_in` y `load`) | `toggle_class`, `set_attribute`, `scroll_to`, `popup_open`, `popup_close`, `offcanvas_toggle` |
| `toggle` | Sólo con `click` y `hover` |
| Clase | `[a-z0-9_-]{1,40}`, sin `elementor-`, `e-`, `digi-` ni `wp-` al principio |
| `set_attribute` | `aria-pressed` (`true\|false\|mixed`), `aria-current` (`true\|false\|page\|step\|location\|date\|time`), `aria-label` (1–150 caracteres sin `<`, `>` ni controles) y `data-digi-state` (`[a-z0-9_-]{1,40}`). `hidden` y `aria-expanded` los gestionan mostrar y ocultar |
| Destino | Un elemento **del documento que se pinta** o el propio elemento; un popup publicado de tipo `popup`. Sin destino válido, la fila no se registra |

**`data-digi-actions`** es un arreglo JSON compacto, escapado, de hasta 20 filas: `t` disparador, `a` acción, `g` ID del elemento destino, `p` ID del popup, `c` clase, `n` atributo, `v` valor, `d` retraso en ms, `m` animación y `o` «sólo la primera vez»; sólo se escriben las claves con valor. El `data-settings` del núcleo no se toca.

### 3.3 Render

- `elementor/frontend/before_render` (prioridad 5): sanea las filas con los destinos del documento actual (`documents->get_current()->get_elements_data()`, memorizado por documento), encola la hoja `e-animation-<nombre>` de una acción animada (la caché del documento guarda los handles encolados al pintar, `elementor/core/base/document.php:1873-1874`), llama a `Pro_Gate::require( 'f7b-action' )` y, con un popup publicado, a `Popup\Module::add_popup_to_location()`; abre un búfer.
- `elementor/frontend/after_render` (prioridad 15): retoca sólo la etiqueta de apertura del envoltorio (`data-id="<id>"`): `data-digi-actions`, `role="button" tabindex="0"` cuando corresponde y `hidden` con «Oculto al cargar», más el `<noscript><style>.elementor-element.elementor-element-<id>[hidden]{display:<valor>!important}</style></noscript>` como **último hijo** del envoltorio, con `var(--display)` en contenedores, `flex` en columnas y `block` en el resto (112 a 123 B). Sus búferes se abren antes y se cierran después que los de prioridad 10 (enlace del elemento, condiciones, vídeos diferidos), así que se anidan bien.
- `elementor/frontend/builder_content_data`: en **cada documento impreso**, también cuando sale de la caché de elementos (donde no se vuelve a pintar ningún elemento ni corre `before_render`), recoge las filas de popup y panel lateral y llama a la puerta de PRO y a `add_popup_to_location()`, una vez por documento y petición (`elementor/includes/frontend.php:1173`).
- `elementor/element/parse_css`: `Hidden_Rule::add()` sólo para el elemento con «Oculto al cargar» o con una acción que muestra u oculta.
- En el editor y en su vista previa no cambia nada. Ninguna acción hace dinámico el elemento.

**Regla de `click`**

| El elemento contiene | Disparador |
|---|---|
| Un único `a` o `button` | Ese control. Un `a` sin `href` ni `tabindex` (el Botón sin enlace) recibe `tabindex="0"` y, si no lo tiene, `role="button"` |
| Ningún descendiente interactivo | El envoltorio, convertido en botón (`role="button" tabindex="0"`; clic, Intro y Espacio). Un envoltorio con su propio `role` o `tabindex` no se convierte |
| Varios, o un único control que no es enlace ni botón | Ninguno: el editor no ofrece `click` y el servidor no registra esas filas |

Cuentan como interactivos `a` y `button` (con o sin `href`), `select`, `textarea`, `summary`, `iframe`, un `input` que no es oculto, `video` y `audio` con `controls` y cualquier etiqueta con `tabindex` no negativo o `contenteditable` (`Rules::interactive()`; la misma regla en `actions.js`). Un elemento dinámico dentro del disparador sale como shortcode en una página en caché y el servidor puede no verlo: el cliente lo corrige (quita `role` y `tabindex` y usa el único control, o descarta `click`).

### 3.4 `actions.js`

5 960 B (2 933 B en gzip -9), `defer`, depende de `elementor-frontend`, sin jQuery. Arranca tras `elementor/frontend/init` (o en el acto si el núcleo ya arrancó) y en `frontend/element_ready/global`, para lo que llega después (Cargar más, filtros por REST, popups); marca `data-digi-actions-ready` y delega clic, Intro, Espacio y Escape en `document`. Nada en el editor.

- **`hover`:** puntero (`pointerType` distinto de `touch`) **o** foco; el destino sigue visible sobre el disparador o el destino con 300 ms de margen y Escape lo cierra sin reabrirlo al devolver el foco (WCAG 1.4.13). En táctil, o con un clic sin `pointerType` en un dispositivo `(hover: none)`, alterna como un clic. Al salir se deshace: mostrar ↔ ocultar, clase, atributo restaurado.
- **Estado y foco:** `aria-expanded` y `aria-controls` sólo en el control que dispara. Si el destino no tiene `id`, recibe `digi-t-<id del elemento>` (y `-2`, `-3`… si un bucle repite el ID): es el único atributo que el cliente añade a un elemento ajeno. Antes de ocultar lo que contiene el foco, el foco vuelve al disparador o, si el disparador queda dentro (un «Cerrar» dentro del panel), a un control que lo abre. `scroll_to` pone `tabindex="-1"` al destino si hace falta, lo enfoca con `preventScroll` (sólo con `click`) y desplaza con `behavior: 'auto'` con movimiento reducido. Sin diálogos propios: un diálogo es un popup de PRO.
- **`scroll_in`:** una vez, con un único `IntersectionObserver` por página. **`load`:** con retraso de hasta 10 000 ms.
- **Popups y paneles:** `elementorFrontend.utils.urlActions.createActionHash()` + `runAction()` del núcleo (`elementor/assets/js/frontend.js:2316, 2339`); sin ellos, un aviso en la consola. Nunca arma el *hash* a mano (`tests/elements-pro-inventory.mjs` lo vigila).
- **`load_more`:** pulsa el botón «Cargar más» de PRO que sigue a `.e-load-more-anchor` en el bucle destino.
- **Ámbito:** el documento (`closest('[data-elementor-id]')`), así cada elemento de un bucle actúa sobre el suyo.

### 3.5 Interacciones PRO

- **La línea del derivado.** En `modules/interactions/module.php` (línea 41), `! $this->is_supported_by_current_license()` se sustituye por `! ( class_exists( '\Digitalisimo\Elements\F7b\Actions\Interactions_State' ) && …::enabled() )`, con el comentario `// DIGITALÍSIMO (F7b)`. La condición del experimento de widgets atómicos se conserva (sólo v4); sin el módulo F7b falla cerrada. El resto del archivo es el de 4.3.0.168 byte a byte (`tests/elements-f7b-interactions.php` comprueba la sha256).
- **Estado del sitio** (`actions/class-interactions-state.php`): opción `digitalisimo_elements_interactions_state` (`pending`, `clear` o `ack`; carga automática; `enabled()` sólo lee `wp_load_alloptions()`). El inventario escucha `digitalisimo_elements_f7b_scan_document` y `…_scan_done` del recorrido de §2 y busca `interactions` con `hover`, `click`, `scrollOn` o `scrollOut`, o con el efecto `custom` (los cinco son sólo de PRO, `elementor/modules/interactions/presets.php:19, 23`); la lista va en `digitalisimo_elements_interactions_inventory` (sin carga automática; 200 IDs y el total). Sin ninguno, `clear`; con alguno, `pending`. `clear` y `ack` no vuelven a `pending`.
- **Aviso persistente** en las pantallas `?page=digitalisimo*` para `manage_options`: la lista (20 y «Y N más», con enlace al editor de cada documento si se puede editar) y «Reproducir las interacciones guardadas», un formulario a `admin-post.php` (acción `digitalisimo_elements_interactions_play`, POST, nonce por sitio y `manage_options`) que pasa a `ack` y vuelve a la pantalla de origen. Con componentes de `digitalisimo-ui`, sin hoja propia. En WP-CLI: `wp digitalisimo-elements interactions status|play` (en una red, por sitio con `--url`).
- **Movimiento reducido:** con el estado encendido y fuera de la administración, `wp_add_inline_script( 'motion-js', …, 'after' )` en `elementor/frontend/after_register_scripts` (prioridad 30), sólo si el núcleo registró `motion-js`: `if(matchMedia('(prefers-reduced-motion: reduce)').matches&&window.Motion&&Motion.MotionGlobalConfig)Motion.MotionGlobalConfig.skipAnimations=true` (145 B; `elementor/assets/lib/motion/motion.js:56, 5527-5528`). Sólo se imprime donde el núcleo encola `motion`.

### 3.6 Desviaciones del frente B

| Punto | Lo que decía la especificación | Lo que hay y por qué |
|---|---|---|
| `<noscript>` | A continuación del destino | Como último hijo del envoltorio: un hermano más desplazaría `:nth-child()` del orden invertido de columnas y el `:last-child` del espaciado de widgets (`frontend.min.css`). Es igual de determinista y entra en la caché |
| Popups y paneles | Al pintar el elemento | También en `builder_content_data`: con la caché de elementos, una visita en caché no vuelve a pintar el elemento y la puerta de PRO retiraría los paquetes en `wp_footer` 19 |
| `hover` | «Puntero fino» | Por el `pointerType` del evento: en equipos híbridos el tipo primario no dice qué puntero se usa, y Chrome sin interfaz informa `pointer: none` |
| `scroll_to` | Mueve el foco | Sólo con `click`: con `load`, `scroll_in` o `hover` mover el foco sin que se pida desorienta (2.4.3); sólo desplaza |
| Inventario | `hover`, `click` y `scrollOn` | También `scrollOut` y el efecto `custom`, que también son sólo de PRO |
| Tamaño de `actions.js` | Objetivo 5 KB | 5 960 B, dentro del techo de 6 144 B: teclado, 1.4.13, foco devuelto, validación completa en el cliente y ámbito de bucle no caben en 5 KB. Quedan 184 B de margen |
| Movimiento reducido | Con el resto del módulo | No depende de los contadores (las interacciones no son un contador): sólo del estado encendido |

## 4. Consultas guardadas y contextos (frente C)

### 4.1 Formato

| Dato | Forma |
|---|---|
| Tipo `digitalisimo_query` | `public`, `show_ui`, `show_in_rest`, `rewrite` y `query_var` en falso; `supports => [ title, revisions ]`; todas las capacidades = `edit_theme_options` (`map_meta_cap => false`); `can_export` para K |
| `_digitalisimo_query_uid` | UUID v4 en minúsculas, fijo desde que se crea: K reutiliza por él |
| `_digitalisimo_query` | Los ajustes del grupo de consulta de PRO **sin prefijo** (`post_type`, `include`, `exclude`, `include_term_ids`, `exclude_term_ids`, `include_authors`, `exclude_authors`, `exclude_ids`, fechas, `orderby`, `order`, `offset`…). Los términos son `term_taxonomy_id`, como en el grupo de PRO |
| `_digitalisimo_query_context` | `none`, `current` o `root`. **Sin `parent`** |
| `_digitalisimo_query_relation` | `none`, `author`, `terms:<taxonomía>`, `children` o `meta:<clave>` |
| `_digitalisimo_query_limits` | `per_page` (1–100) y `depth` (1–2) |

Las metas se registran con `register_post_meta` (con revisiones, sin REST, con `auth_callback`) y su `sanitize_callback` es el mismo saneado de `Store`: lo que escribe un kit también pasa por él. El saneado usa listas cerradas de claves y de valores: `post_type` ∈ `Utils::get_public_post_types()` (sin `by_id`, `current_query` ni `related`), IDs enteros (≤ 100), fechas válidas, `orderby` de la lista del grupo de PRO, `offset` ≤ 500, una taxonomía pública que existe en `terms:<tax>` y una clave sin `_` inicial y fuera de la lista negra (`Filters\Query::DENIED`, por fragmentos) en `meta:<clave>`. Al guardar desde la pantalla, términos, autores y entradas deben existir y un «incluir por» vacío se quita; al consultar sólo se comprueba la forma. `post_status` es siempre `publish`, y `Store::get()` sólo devuelve consultas **publicadas**: un borrador nunca se aplica.

### 4.2 Control y editor

- **Control** `digitalisimo_saved_query` (select sin `default`, guardado como cadena, `style_transfer => false`), inyectado delante del origen del grupo en `section_query` de Posts, Portfolio, Loop Grid y Loop Carousel (en los Loop, sólo con `_skin = post`). Galería no usa el grupo de PRO y Productos de WooCommerce tiene su propio rasgo.
- **Grupo oculto sin borrar valores** (D24): los 22 campos del grupo (`Group_Control_Query` y `Related`) y la nota de archivo de PRO llevan la condición `digitalisimo_saved_query => ''`. `posts_per_page`, `ignore_sticky_posts` y `query_id` siguen siendo del widget.
- **Resumen** de sólo lectura («Entradas · Noticias · 6 · contexto actual · …») y enlace «Editar en Consultas (N usos)» para quien tiene `edit_theme_options`, pintados por `assets/js/queries-editor.js` (6 733 B, sólo en el editor).
- **«Desvincular»:** la acción AJAX de sólo lectura devuelve los ajustes de la consulta y el cliente los copia con un único `$e.run( 'document/elements/settings' )` (con su «Deshacer») y pone el select en «Ninguna». Si la consulta usa contexto o relación, la confirmación avisa de que los resultados cambiarán. También baja la cantidad del widget al tope de la consulta si era mayor (o «todas»), para que los resultados no crezcan al desvincular.
- **AJAX del editor** por `elementor/ajax/register_actions`: `digitalisimo_f7b_queries` (lista) y `digitalisimo_f7b_query_settings` (lectura), las dos con `current_user_can( 'edit_post', editor_post_id )`. El select sólo consulta sus opciones en la administración; en el front basta «Ninguna», porque el núcleo no valida el valor contra las opciones.

### 4.3 Aplicación y contextos

- **Fusión a prioridad 5** en `elementor/query/query_args`: se quitan del widget las claves de selección (`post_type`, `post__in`, `post__not_in`, `tax_query`, `author__in`, `author__not_in`, `date_query`, `orderby`, `order`, `meta_query`) y se ponen las de la consulta. `paged`, el `offset` de las plantillas alternas, `ignore_sticky_posts`, `has_custom_pagination` y los filtros de `loop-filter` (prioridad 10) quedan como están; `posts_per_page` es el del widget con el `per_page` de la consulta como tope. Los argumentos los construye **el código real de PRO**: `Queries\Args` extiende `Elementor_Post_Query` y llama a sus métodos en el orden de `get_query_args()`.
- **Contextos:** `current` es la entrada del bucle dentro de un bucle (`get_the_ID()`) y, en el nivel superior, la entrada consultada (`get_queried_object()` si es un `WP_Post`; en un archivo, `$post` sería la primera entrada de la consulta principal). `root` es `get_queried_object()`. En el render del editor, el documento, sólo si el núcleo confirma el editor o la vista previa de quien puede editar. Los contextos son siempre entradas: en un archivo de término o de autor no hay contexto.
- **Relaciones:** `author` → `author__in`; `terms:<tax>` → `tax_query` con los términos del contexto; `children` → `post_parent`; `meta:<clave>` → `meta_query` con el valor del contexto. En `author`, `terms` y `meta` el contexto se quita de los resultados, como «Relacionadas» de PRO.
- **Fallar cerrado** (`post__in => [0]`): consulta inexistente, en borrador o no válida (también un `parent` guardado o un contexto desconocido); valor del control que no es un ID (por ejemplo el `never` de un kit), que no vuelve a los ajustes del widget porque siguen ocultos; sin contexto o con un contexto sin el dato; profundidad o presupuesto superados; una inclusión imposible (términos borrados, «por autores» sin autores). Donde PRO amplía (con términos incluidos que ya no existen, el grupo omite el `tax_query`, `elementor-post-query.php:257-262`), la consulta guardada devuelve vacío.
- **Presupuesto por petición:** ≤ 50 ejecuciones, ≤ 300 entradas pintadas (el `posts_per_page` se limita a lo que queda), profundidad ≤ 2. `no_found_rows` salvo paginación o plantillas alternas; como los skins de Posts y Portfolio no pintan nada con `found_posts = 0` (`modules/posts/skins/skin-base.php:913`, `modules/posts/widgets/portfolio.php:562`), `elementor/query/query_results` pone `found_posts = post_count` y `max_num_pages = 1`. El `offset` de la consulta se aplica con la misma regla que PRO (`fix_query_offset()` y `fix_query_found_posts()`, `elementor-post-query.php:62-63`) en `pre_get_posts` y `found_posts`, sólo para las consultas marcadas. Las metas y los términos se precargan sólo si la plantilla del bucle los usa (lectura de su `_elementor_data`, ya en caché; ante la duda, sí).
- **Por REST:** el envoltorio del bucle de nivel superior con contexto `current` o `root` pinta `data-digi-ctx="<ID>"` si la entrada es pública (el bucle es dinámico, `element-base.php:308`, así que el atributo no entra en la caché de elementos). `filters.js` lo envía como `digi_ctx` en `refresh-loop` y el servidor lo lee en `rest_request_before_callbacks` y lo acepta sólo con `is_post_publicly_viewable()`; si no vale, el documento sólo para quien lo puede editar y, si no, falla cerrado.
- Con `Flags::active( 'saved_queries' )` falso no se engancha nada en el front, ni siquiera el tipo. El tipo se registra en la administración, en WP-CLI, en cron y donde las consultas están activas.

### 4.4 Pila de bucles

`Runtime` apila `get_the_ID()` en `elementor/widget/before_render_content` de los widgets de bucle y desapila en `elementor/frontend/widget/after_render`. `refresh-loop.php:47` y el render del editor llaman a `render_content()` sin `print_element()`, así que `after_render` (`element-base.php:572`) no llega y la pila crecería: también se poda por **nivel de búfer** (`ob_get_level()`), porque lo de dentro de un bucle siempre se pinta en un búfer más hondo que él; al apilar se descartan las entradas de nivel igual o mayor.

### 4.5 Pantalla «Consultas»

`admin.php?page=digitalisimo-elements-queries`, bajo Plantillas (o junto a las pantallas de DIGITALÍSIMO si Plantillas no existe), con `edit_theme_options` y `digitalisimo-ui`. Lista con estado, resumen y usos («Calculando…» mientras corre el recorrido de §2; «En uso (N)» enlazado a la pantalla de usos); «Nueva» y «Editar» con los campos del grupo de PRO, contexto, relación y topes; búsqueda AJAX (`digitalisimo_f7b_queries_search`, nonce y `edit_theme_options`) de términos, autores (**sólo `display_name`**, sólo con entradas publicadas) y entradas; «Probar» por POST con nonce (las 10 primeras, con el tiempo y una entrada de contexto opcional); «Borrar» sólo sin usos. Guardar, borrar y probar van por `admin-post.php` (`digitalisimo_f7b_query_save`, `_delete` y `_test`). La búsqueda la hace `queries/assets/queries-screen.js` (4 255 B, sólo en la pantalla, `defer`). En una red, cada sitio tiene sus consultas y no hay pantalla de red.

### 4.6 Desviaciones del frente C

| Punto | Lo que decía la especificación | Lo que hay y por qué |
|---|---|---|
| Contexto `parent` | `none`, `current`, `parent` y `root` | **Retirado** por la integración: con profundidad ≤ 2 coincidía siempre con `current` o con `root`. Un `parent` guardado se lee como `none` y, con relación, la consulta queda no válida y falla cerrada; desde el formulario es un error |
| «Memoria por argumentos idénticos» | Caché propia | La de WordPress: la consulta va con `cache_results` y `WP_Query` guarda sus resultados en el grupo `post-queries` de la caché de objetos, que sin caché persistente dura la petición (WordPress ≥ 6.1). Una caché propia duplicaría la del núcleo. La lectura de la consulta sí se memoriza (`Store::$memo`) |
| `current` en el nivel superior | `get_the_ID()` | La entrada consultada; en un archivo, `$post` sería arbitrario |
| Pila | Desapilar en `after_render` | También poda por nivel de búfer (§4.4) |
| `no_found_rows` | Siempre salvo paginación | Exige corregir `found_posts` para Posts y Portfolio (§4.3) y no se usa con plantillas alternas |
| Lista negra de `meta:<clave>` | Copia propia | Una sola fuente: `Store::DENIED` es `Filters\Query::DENIED` y `Store::denied()` delega en `Filters\Query::denied()` (filtro `digitalisimo_elements_inline_tags_denied`) |
| Archivos | `queries-editor.js` | Además `queries/assets/queries-screen.js` y una búsqueda de entradas (para «Excluir: selección manual» y la entrada de contexto de «Probar») |

## 5. Filtros ampliados (frente D)

### 5.1 Módulo neutro

`modules/digitalisimo-filters/` (`Digitalisimo\Elements\Filters`, autocarga acotada) carga siempre, con o sin WooCommerce, y antes que `Woo\Module`: `Types` (el registro que era de F4-Woo, sin cambios), `Query` (los cuatro tipos, el destino «Archivo», el orden por precio y la guarda del JOIN), el rasgo `Filter_Form` (lo común de los widgets filtro, con el código de 4.3.0.168), la base `Filter_Widget`, `Registry`, `Editor` y los cuatro widgets. Las clases de F4-Woo quedan sin cambio de comportamiento: `Woo\Filters\Types extends Filters\Types` (sólo conserva sus cuatro constantes), `Woo\Filters\Query extends Filters\Query` (`first_term()` y `append_join()` pasan a la base con el mismo código) y `Woo\Filters\Filter_Widget` **usa el rasgo** `Filter_Form`, porque `Woo\Inert::cooperates()` exige `is_subclass_of( $class, Woo\Widget::class )` (`modules/digitalisimo-woo/class-inert.php:100`) y PHP sólo admite una clase madre. Sin WooCommerce, el módulo registra (sin encolar) el coordinador, `filters.css` y `filters-form.js` con los mismos handles y argumentos que F4-Woo, con prioridad posterior a la de F4-Woo (con WooCommerce no hace nada), más su hoja `digitalisimo-filters`.

### 5.2 Tipos y nombres

| Tipo (`digitalisimo_elements_loop_filter_types`) | Nombre en el almacén de PRO | Qué aplica |
|---|---|---|
| `digitalisimo_search` | `digi_s_<fid>` | `s`, de 2 a 100 caracteres (`sanitize_text_field`); conserva el orden del bucle |
| `digitalisimo_meta` | `digi_m_<fid>` y `digi_m_<fid>-max` | `meta_query` igual, en lista o rango, de la clave del widget, con tipo `NUMERIC`, `DECIMAL` (`DECIMAL(20,6)`), `DATE` o `CHAR`; el rango sólo con números o fechas |
| `digitalisimo_orderby` | `digi_o_<fid>` | `orderby` y `order` de la lista del widget: fecha, título, modificación, precio (con WooCommerce, en bucles o archivos de productos) o el campo numérico de su clave. **Sin `rand`** |
| `digitalisimo_per_page` | `digi_pp_<fid>` | `posts_per_page` de la lista del widget, ≤ 100 |

`<fid>` es el ID del widget filtro (`[a-z0-9]{7,8}`). `digi_m_<fid>-max` llega porque el derivado parte el nombre por el primer guion tras el ID del bucle (`modules/loop-filter/module.php:208`, `explode( '-', $filter, 2 )`, corrección de F4-Woo).

### 5.3 La petición nunca decide

El servidor busca `<fid>` en el `_elementor_data` del **documento del bucle** (índice memorizado por petición; `strpos` antes de decodificar), comprueba que es uno de los cuatro widgets con el tipo de ese prefijo, su destino y que su `selected_element` es este bucle, y aplica **sólo** su clave, su tipo de dato, su lista de valores y sus topes. Un nombre sin widget válido, de otro documento o de otro bucle, o con el tipo cambiado (`digi_s_` bajo `digitalisimo_meta`) se descarta en silencio. El documento del bucle es, al pintar, el documento actual del núcleo (`get_builder_content()` cambia a él, `elementor/includes/frontend.php:1146`) y, en `refresh-loop`, el `post_id` de la petición capturado en `rest_request_before_callbacks` (sólo en REST), que el `permission_callback` de PRO ya valida (`core/data/endpoints/refresh-base.php:20-49`; `refresh-loop.php:24-28` pasa `widget_filters` tal cual). Sin documento, falla cerrado.

**Topes:** un `meta_query` por widget y 4 widgets por petición (el mismo widget no gasta otro cupo; ordenar por campo cuenta como uno), listas de valores ≤ 50, órdenes ≤ 12, cantidades ≤ 10 y ≤ 20 `<fid>` por tipo y petición.

### 5.4 Destino «Archivo»

`digi_f=<documento>-<widget>` en la URL. `pre_get_posts` (prioridad 20) sólo se engancha si la URL lo trae, y sólo actúa en la consulta principal de un archivo, de la portada del blog o de la búsqueda, nunca en la administración ni en un feed. El documento debe estar publicado y el widget de `digi_f` ser uno de los cuatro con destino «Archivo»; cada parámetro propio se comprueba contra **su** widget del mismo documento, así dos filtros del mismo archivo conviven. Sin nonce: determinista y cacheable por URL. Las listas viajan separadas por comas. La búsqueda con destino «Archivo» es el buscador nativo (`s` hacia `home_url( '/' )`): nunca `s` sobre un archivo de término, que cambiaría la jerarquía de plantillas. Con WooCommerce, el destino «Tienda» de F4-Woo sigue mandando en los archivos de productos.

### 5.5 Widgets y editor

- **Widgets** (categoría DIGITALÍSIMO; destino «Loop Grid de este documento» o «Archivo»): «Buscador del Loop Grid» (`digitalisimo-loop-search`), «Filtro por campo» (`digitalisimo-loop-meta-filter`: un valor, varios con casillas, radios, botones o desplegable, o rango numérico, decimal o de fechas), «Orden del Loop Grid» (`digitalisimo-loop-orderby`: repetidor de la lista cerrada; por defecto recientes, A-Z y Z-A) y «Resultados por página» (`digitalisimo-loop-per-page`: por defecto 12, 24 y 48). En la URL viaja la **ficha** de cada valor (`sanitize_title`; dos valores con la misma ficha pasan a `v<n>`), nunca el valor libre: reordenar la lista no cambia las URL guardadas.
- **Accesibilidad:** el buscador va en `form[role=search]` con `aria-label` y `<label for>` visible (u oculta sólo a la vista), `minlength`/`maxlength`, envío con botón (no busca al teclear) y «Limpiar la búsqueda» con nombre propio; los grupos de botones son `role="button"` con `aria-pressed` dentro de `fieldset`/`legend`; los desplegables, con `aria-label`. El anuncio y `aria-busy` del coordinador se heredan.
- **Editor:** «Loop Grid» lista los bucles **de este documento** (no los de una plantilla incrustada, que el servidor descartaría) y la lista de claves; `assets/filters-editor.js` (2 720 B, sólo en el editor). Las claves vienen de la acción AJAX `digitalisimo_filters_meta_keys` (`elementor/ajax/register_actions`, `edit_post` sobre el documento del editor, tipo público distinto de adjuntos): dos consultas acotadas (las 100 entradas publicadas más recientes del tipo y `DISTINCT meta_key … WHERE post_id IN (…) LIMIT 200`), sin `_*`, sin `is_protected_meta()` ni la lista negra, en el transitorio `digi_filters_keys_<tipo>` de una hora. El servidor valida la clave igual al filtrar.
- **Archivos de F4-Woo que cambian:** `assets/js/filters.js` envía `digi_ctx` (§4.3) sólo si el envoltorio del bucle lleva un `data-digi-ctx` numérico (8 161 → 8 184 B; la cabecera se acortó para caber en 8 192 B) y `assets/js/filters-form.js` reconoce los formularios nuevos (tipo por `data-type`, rango igual que el precio, campo `type=search`, `element_ready` de los cuatro widgets; 5 673 → 5 884 B). La hoja propia `assets/f7b-filters.css` pesa 570 B, sin colores ni `url()`.

### 5.6 Desviaciones del frente D

| Punto | Lo que decía la especificación | Lo que hay y por qué |
|---|---|---|
| Base de los widgets de F4-Woo | Extender `Filters\Filter_Widget` | Usan el rasgo `Filter_Form` (§5.1); `Types` y `Query` sí se extienden |
| Orden por campo | `meta_key` + `meta_value_num` | Una cláusula con nombre `EXISTS` (`DECIMAL(20,6)`) en un grupo `OR` con `NOT EXISTS` y `orderby => [ nombre => orden ]`: el mismo orden sin perder las entradas que no tienen el campo (un `INNER JOIN` las quitaría). El precio usa `wc_product_meta_lookup` con el orden de `WC_Query` y la guarda del JOIN de F4-Woo |
| Tipos de dato | `NUMERIC`, `DATE`, `CHAR` | También `DECIMAL`: `NUMERIC` de WordPress convierte a entero |
| Relevancia de la búsqueda | Sin `orderby`, por relevancia | El Loop Grid siempre trae `orderby` (`set_order_args()`), así que la búsqueda conserva el orden del bucle |
| «Archivo» con varios widgets | Un widget por URL | Cada parámetro contra su widget del mismo documento; filtros repartidos en dos documentos sólo aplican los del documento del último envío |
| «Consulta actual» | — | `Elementor_Post_Query` no aplica `elementor/query/query_args` con «Consulta actual» (`get_query_args()`): esos bucles no se filtran |

## 6. Sobrescrituras de contenido en el widget Plantilla (frente E)

### 6.1 Clave estable

`_digitalisimo_slot` («Clave de sobrescritura», texto sin `default`, `style_transfer => false`, `render_type => 'none'`) al final de la primera sección de contenido de Encabezado (`section_title`), Editor de texto (`section_editor`), Imagen (`section_image`), Botón (`section_button`), Icono (`section_icon`), Vídeo (`section_video`) y Caja de icono (`section_icon`). Sólo se ve al editar una plantilla de la biblioteca: el núcleo no tiene condiciones por documento y las pilas de controles son por tipo de widget (`elementor/includes/managers/widgets.php:390-409`), así que `elementor/document/config` marca `digitalisimoOverrides.library` y `assets/js/overrides-editor.js` pone `digi-ovr-lib` en el `body`. Validación `[a-z0-9_-]{1,40}`: una clave con otra forma nunca coincide al pintar y el editor la marca. El aviso «Esta clave se repite en N elementos…» se calcula en el cliente con el modelo del documento abierto, y «N instancias sobrescriben esta clave» con la acción AJAX `digitalisimo_f7b_slot_uses` (lee los documentos de `Usages::of( $plantilla, 't' )`).

### 6.2 Repetidor

`digitalisimo_overrides` en el widget Plantilla, tras `template_id` (`elementor/element/template/section_template/before_section_end`), sin filas por defecto y con la condición «hay plantilla»; `style_transfer => false` en él y en cada campo. Campos: `slot` (un select que llena `digitalisimo_f7b_slots` con clave, tipo, recuento y un extracto ≤ 40 caracteres; una clave guardada que ya no existe sale como «clave (sin destino)»), `kind` (`text`, `html`, `image`, `link` o `hide`, con `default => text` y `save_default`), `text`, `html` (WYSIWYG), `image` (medios, sólo imágenes) y `link` (URL con `is_external` y `nofollow`, nunca `custom_attributes`). Avisos: «N sobrescrituras sin destino», «N sobrescrituras no se aplican al tipo de su elemento», «La plantilla elegida no tiene claves…» y, con `hide`, el de la vuelta atrás. Las dos acciones AJAX (`elementor/ajax/register_actions`) exigen `edit_post` sobre el documento del editor; `digitalisimo_f7b_slots` exige además una plantilla `elementor_library` publicada o editable por el usuario y responde el mismo 403 en cualquier otro caso. Nada de la petición decide claves, tipos ni topes (≤ 200 claves, ≤ 50 filas).

### 6.3 Aplicación

- El único gancho permanente en el front es `elementor/widget/before_render_content` (`widget-base.php:628`). Si el widget es `template`, el modo es normal, su plantilla está publicada y es de la biblioteca (la misma condición con la que pinta, `modules/library/widgets/template.php:87-102`) y tiene filas válidas, se apila un marco `{ widget, template, rows }` y se enganchan `elementor/frontend/builder_content_data` (sólo para el `$post_id` del marco más interno aún sin aplicar) y `pre_option_elementor_element_cache_ttl` → `'disable'`, sólo mientras el documento en curso (`documents->get_current()`, que `get_builder_content()` cambia a la plantilla) es la plantilla de un marco aplicado. Se retiran en `elementor/widget/render_content` (`:663`) o en `elementor/frontend/widget/after_render` (`element-base.php:572`), lo primero que llegue.
- Con `'disable'`, `Document::print_elements()` toma la rama de `elementor/core/base/document.php:1835-1848`: pinta y ni lee ni escribe la meta `_elementor_element_cache` de esa plantilla. Una instancia sin sobrescrituras pinta **byte a byte** como sin el módulo y usa la caché de la plantilla; la caché de la plantilla nunca contiene lo sobrescrito. La página guarda el Plantilla como shortcode (es dinámico, `element-base.php:308`), así que se resuelve en cada visita; con su caché en «Activa» (`_element_cache = no`, `element-base.php:595-596`) la página guarda la instancia ya resuelta, lo que es correcto porque las sobrescrituras no dependen del visitante.
- Un Plantilla dentro de otro: cada instancia sobrescribe sólo su plantilla, y una interior sin sobrescrituras usa su caché.
- **Sin sufijos** en los IDs HTML (crítica 1, C1-7): dos instancias de la misma plantilla repiten IDs, como en 4.3.0.168.
- **Sin kses al pintar** (C1-24): `html` se confía como el núcleo, que ya aplica `Utils::kses_post_deep()` al guardar a quien no tiene `unfiltered_html` (`document.php:856-858`). El saneado es de forma: `kind` de la lista, `esc_url_raw()` en las URL, `absint()` en el adjunto y `esc_html()` en `text`.
- Valores vacíos (texto, HTML, imagen sin ID ni URL, enlace sin URL) no hacen nada; para quitar algo está `hide`, que quita el elemento de los datos (el contenedor queda). Un `template_id` que no es de la biblioteca pinta como hoy.
- **Markdown** (experimento `markdown_rendering` del núcleo, inactivo por defecto): el Markdown del Plantilla (`template.php:110-125`) no pasa por `builder_content_data`, así que `elementor/markdown/element_output` (`markdown-renderer.php:122`) lo rehace con los datos sobrescritos.
- Con `Flags::active( 'overrides' )` falso no se engancha nada. Los controles sólo se registran en el editor: el front lee los ajustes crudos (`get_data( 'settings' )` conserva las claves sin control).

**Tabla de sustitución** (`Apply::TARGETS`). Cada fila quita además la etiqueta dinámica del ajuste, que si no taparía el valor. `hide` vale para los siete tipos; un `kind` que el tipo no declara se ignora y el editor lo avisa. `link` cambia `url`, `is_external` y `nofollow` y **conserva** los `custom_attributes` del elemento de la plantilla. Varias filas de la misma clave se suman (texto y enlace de un botón) y una clave repetida cambia todos sus elementos.

| Tipo | `text` | `html` | `image` | `link` |
|---|---|---|---|---|
| Encabezado | `title` | `title` | — | `link` |
| Editor de texto | `editor` | `editor` | — | — |
| Imagen | `caption` (+ `caption_source = custom`) | — | `image` | `link` (+ `link_to = custom`) |
| Botón | `text` | — | — | `link` |
| Icono | — | — | — | `link` |
| Vídeo | — | — | `image_overlay` | URL de la fuente (`<fuente>_url`; alojado: `insert_url = yes` + `external_url`) |
| Caja de icono | `title_text` | `description_text` | — | `link` |

### 6.4 Hechos y desviaciones del frente E

- `Document::render_element()` (`document.php:387-403`) y `Widget_Base::get_raw_data( true )` (`widget-base.php:783-799`, el `htmlCache` del editor) llaman a `render_content()` sin `print_element()`: por eso la retirada también se hace en `elementor/widget/render_content`, que corre después de pintar la plantilla.
- El editor no guarda los valores por defecto, tampoco en las filas de un repetidor (`removeDataDefaults`, `elementor/assets/js/editor-modules.js:1172-1186`): por eso `kind` lleva `save_default`, y el servidor trata una fila sin `kind` como `text`. Es un campo nuevo de un repetidor nuevo: no cambia ningún HTML.
- El Editor de texto es dinámico (no redefine `is_dynamic_content()`): dentro de una plantilla en caché queda como shortcode con sus datos, ya sobrescritos si la instancia los cambió.
- `Pro_Gate::capture_data()` (`builder_content_data` a `PHP_INT_MAX`) ve los datos ya sobrescritos.
- La guarda del submódulo (`Overrides\Module::SYMBOLS`) sólo pide `Elementor\Plugin` y `Elementor\Controls_Manager`, porque corre en `plugins_loaded` (§2).
- El select dinámico del repetidor usa piezas internas del editor (la configuración `fields` del repetidor, que `mergeControlsSettings()` sustituye al crear cada fila, `editor.js:27474, 53391-53397`, y `getChildControlView()`): una sincronización del núcleo puede romper la lista, nunca lo guardado ni el front.

## 7. Slider anidado ligero (frente F)

- **Widget** `digitalisimo-nested-slider` («Slider anidado ligero», `nested/class-slider-widget.php`, `F7b\Nested\Slider_Widget extends Widget_Nested_Base`), junto al `nested-carousel` de PRO, que no se toca: el propio existe porque no carga Swiper ni su hoja. Palabras clave sin ninguna en común con las de PRO (la prueba lo comprueba leyendo `nested-carousel.php`) y una descripción en el panel que remite a «Carrusel» para ver varias diapositivas a la vez.
- **API de anidados**, como `nested-tabs`: tres contenedores `full` por defecto; repetidor `slides` (`Control_Nested_Repeater`) con `slide_title`, que sólo usa el editor; los hijos en `.digi-nslider__track`; `get_initial_config()` con `support_improved_repeaters`, `target_container => ['.digi-nslider__dots']` y `node => 'button'` (cada fila del repetidor es un punto, sin intercalado). `print_child()` añade a cada hijo `role="group"`, `aria-roledescription="diapositiva"` y `aria-label="N de M"` con el mismo filtro `elementor/frontend/container/should_render` que `nested-tabs.php:1130-1152`. `content_template()` da el mismo marcado que `render()` (la prueba compila la plantilla con los delimitadores del editor y la compara) y `content_template_single_repeater_item()` pinta el punto nuevo.
- **Marcado:** `section.digi-nslider[aria-roledescription="carrusel"][aria-label]` con, en este orden, el botón «Pausar»/«Reanudar» (primer hijo, sale `hidden` del servidor y lo muestra el script; es una capa en la esquina superior final que no desplaza nada), los controles (flechas y puntos «Ir a la diapositiva N» con `aria-current="true"`) y la pista. Región viva `aria-live` en `off` mientras rota y `polite` en cualquier otro caso. Las diapositivas que no se ven llevan `inert`.
- **Controles:** transición (`slide`, `fade` o `scale`), duración (sólo `fade` y `scale`, como variable CSS), navegación («Flechas y puntos», «Sólo flechas» o «Sólo puntos»: no hay «ninguna», y un valor forjado vuelve a «ambos» en el servidor y en la plantilla), flechas debajo o a los lados, bucle, reproducción automática (2 000 a 20 000 ms, acotada en el servidor y en la plantilla), pausa al pasar el puntero, nombre accesible («Diapositivas» por defecto, traducible) y altura fija responsiva (vacía, la de la más alta). Estilo: color, fondo, tamaño de los botones (nunca menos de 24 px), tamaño de los puntos y separación, sin `default`, como variables `--digi-ns-*`. Todo control que no es de estilo lleva `style_transfer => false`.
- **Primero CSS** (`assets/css/nested-slider.css`, 3 066 B): `slide` es una pista `display:flex` con `overflow-x:auto` y `scroll-snap-type:x mandatory`, diapositivas `flex:0 0 100%` con `scroll-snap-align:start` estiradas a la más alta y sin arrastre propio; `fade` y `scale`, con el script listo (`digi-nslider--stack`), se apilan con `grid-area:1/1` y la altura de la más alta. Las transiciones empiezan tras el primer pase (`--anim`) y no hay ninguna con movimiento reducido. Lleva la regla `[hidden]` de §2 y `.digi-nslider [hidden]`; sólo clases `digi-`, sin paleta propia, foco visible con `currentColor`, y el punto actual se distingue por relleno y tamaño.
- **Motor propio** (`assets/js/nested-slider.js`, 5 119 B, `defer`, sin jQuery ni dependencias): arranca con la carga, en `elementor/frontend/init` y en `frontend/element_ready/digitalisimo-nested-slider.default`, idempotente. Sólo mueve botones, reproducción automática y estado. Teclado (flechas, Inicio y Fin) sólo con el foco en un control del slider; desde un punto, el foco acompaña. Pausa con el puntero (sólo ratón), con el foco (salvo en el propio botón de pausa), fuera de pantalla (un único `IntersectionObserver` por página) y con la pestaña oculta; una pausa con el botón persiste. Sin reproducción automática con `prefers-reduced-motion` (el botón ofrece «Reanudar»). En `slide` sigue la pista cuando se asienta (120 ms) y, si el foco estaba en la diapositiva que pasa a ser inerte, lo lleva al punto nuevo. En el editor no hay reproducción automática; `data-digi-start` conserva la diapositiva que se edita y el evento `digi-nslider-go` la muestra.
- **Editor** (`assets/js/nested-slider-editor.js`, 3 862 B, sólo en el editor y detrás de `nested-elements`): registra el tipo sobre `NestedElementBase` en `elementor/nested-element-type-loaded`, como `nested-tabs.js`, con una vista que extiende `NestedView`; etiqueta los hijos «N de M» con los mismos textos que el servidor (`wp_add_inline_script`), lleva `activeItemIndex` al lienzo y renumera los puntos tras cada cambio del repetidor (`elementor/nested-container/atomic-repeater`).
- **Guarda al registrar:** `Nested\Module::init()` sólo engancha; `ready()` corre en `elementor/widgets/register` (prioridad 20) y en el script del editor, memorizada, y comprueba el experimento `nested-elements` y `Widget_Nested_Base` y `Control_Nested_Repeater` con `F7b\Module::requires()` (§2). La hoja y el script sólo se registran (`elementor/frontend/after_register_*`) y los encola el núcleo por `get_style_depends()` y `get_script_depends()`: coste 0 donde no se usa. El Slider Optimizado no cambia ni un byte.

**Desviaciones del frente F**

| Punto | Lo que decía la especificación | Lo que hay y por qué |
|---|---|---|
| Motor | Compartido con el Slider Optimizado si se podía | Hoja y motor propios: la hoja del Slider Optimizado es una marquesina sin reglas de diapositivas, flechas ni puntos (§1.5) |
| `fade` y `scale` sin JavaScript | Apilados en CSS | Son la misma pista con `scroll-snap`: apilar sin script dejaría inalcanzable todo menos la primera diapositiva. El apilado entra con el script sin desplazar nada (misma caja y misma diapositiva visible, medido en Chrome) |
| Flechas ← → dentro de una diapositiva | — | En `slide` no desplazan la pista (salvo en campos y editables): lo nativo dejaba el foco en una diapositiva inerte. Un desplazador horizontal propio dentro de una diapositiva no responde a esas flechas |
| Duración en `slide` | Para las tres transiciones | No aplica: el desplazamiento suave lo pone el navegador |
| Bucle | En todo | Sólo flechas y teclado; la reproducción automática siempre vuelve a la primera |
| Diapositivas por vista | — | Una; para varias, «Carrusel» de PRO, y el panel lo dice |
| Envoltorio de diapositiva | `div.digi-nslider__slide` | Los contenedores hijos son las diapositivas, como los paneles de `nested-tabs`; variables cortas `--digi-ns-*` para el objetivo de 3 KB |

## 8. Presentación de pestañas y acordeón anidados (frente G)

- **Control** `digitalisimo_presentation` en una sección de Estilo «Presentación» tras `section_tabs_style` (`nested-tabs.php:448`) y tras `section_accordion_style` (`nested-accordion.php:399`): «Ninguno» (vacío), `pills`, `underline`, `boxed` o `minimal`; sin `default` ni `selectors`; `prefix_class` `digi-nested-` (con `none` neutro por `classes_dictionary`, `element-base.php:808-809` y `editor.js:9882-9883`); `render_type => 'ui'`, así el editor cambia la clase en vivo (`editor.js:9868-9886, 9941-9947`); `style_transfer` verdadero, porque es estilo. La hoja `digitalisimo-f7b-nested-presentation` va en `assets` con una condición `in` sobre los cuatro estilos y se registra en `elementor/frontend/after_register_styles`; en la vista previa del editor se encola siempre.
- **Hoja** `assets/css/nested-presentation.css` (2 550 B): las variables del núcleo se fijan en el envoltorio con especificidad 0,2,0 y todo lo demás va dentro de `:where()`. Los valores por defecto del núcleo están en el propio envoltorio con 0,1,0 (`widget-nested-tabs.min.css`), así que una regla 0,0,0 nunca los ganaría; 0,2,0 los gana sin depender del orden de carga y pierde con cualquier control de Estilo (`{{WRAPPER}}` = 0,3,0). El activo se marca con algo más que color en los cuatro estilos (anillo interior, raya inferior gruesa, recuadro con barra superior, subrayado del texto); indicadores con `currentColor`; `:focus-visible` con contorno sólido de 2 px; títulos de 24 px de alto como mínimo; en colores forzados, el activo queda subrayado. Sólo `currentColor` y `transparent`: sin paleta propia. Las pestañas anidadas dentro no heredan nada (selectores con hijos directos).
- **Desviaciones:** la clase va por `prefix_class` y no por `elementor/frontend/widget/before_render`, que no llega al editor; «Ninguno» es el valor vacío, que el panel muestra con su nombre en lugar de un desplegable en blanco. El contraste ≥ 3:1 del indicador es el del texto del título sobre su fondo: con los colores por defecto del núcleo sin kit (blanco sobre `#61ce70`, 2,1:1) el propio texto ya no llega, y no se añade paleta propia.

## 9. «Aplicar este estilo a todos los de este tipo» (frente H)

- `F7b\Editor\Module` encola `assets/js/apply-all-editor.js` (3 722 B, ES2017, sin jQuery ni textos propios: todos llegan de PHP) sólo en el editor (`elementor/editor/after_enqueue_scripts`, dependencia `elementor-editor`). Sin AJAX ni opciones.
- **Menú contextual** de los widgets v3, delante de «Borrar»: «Aplicar este estilo a todos los de este tipo (N)», por el filtro `elements/widget/contextMenuGroups` (recibe la vista, `editor.js:9492`; el genérico `elements/context-menu/groups` sólo recibe los grupos y el tipo, `:9654`), el mismo que usa F7a. Sólo con `elementor.userCan( 'design' )` (`:53307`); un elemento bloqueado o sin otros del mismo tipo deja la entrada desactivada.
- **Mecánica:** guarda el origen en `elementorCommon.storage` con la clave `digitalisimo-apply-all` y el formato de «Copiar» (`{ type: 'elementor', siteurl, elements: [ model.toJSON( { copyHtmlCache: true } ) ] }`, `editor.js:13609-13616`), ejecuta **un** `$e.run( 'document/elements/paste-style', { containers, storageKey } )` sobre los widgets del mismo tipo del documento (también anidados; sin el origen ni los globales con `templateID`, que se anuncian aparte) y borra la clave en `finally` con `storage.set( clave, undefined )`, porque el almacén sólo tiene `get`, `set` y `save` y guarda con JSON (`common.js:246-300`). El portapapeles del usuario no se toca. Un registro de historial nativo: el `getHistory()` de «Pegar estilo» (`editor.js:14822`).
- **Qué se pega:** exactamente lo que pega «Pegar estilo», según `isStyleTransferControl()` (`editor.js:9758-9760`): todo lo que no es de la pestaña Contenido (también `_css_classes` y otros de Avanzado sin `style_transfer`) y los controles de Contenido con `selectors` o `prefix_class`. La confirmación lo dice con el recuento. Que el contenido, los enlaces, las acciones, las claves y los vínculos no cambien depende de que cada control nuevo declare `style_transfer => false`; `tests/elements-f7b-apply-all.mjs` ejecuta el código real de «Pegar estilo» extraído de `editor.js` y lo comprueba con los controles de F7b. Sólo widgets v3: en v4, `paste-style` va por otro camino (`editor-canvas.js:9601-9640`).

## 10. Etiquetas dinámicas dentro del texto (frente I)

- **Dónde:** interruptor `digitalisimo_inline_tags` (guarda `yes`, sin `default`, `style_transfer => false`, `render_type => 'none'`, con el aviso de la vuelta atrás) y, debajo, «Insertar dato». La sustitución se hace en los **ajustes** de los controles de texto declarados, en `elementor/widget/before_render_content`, nunca en el HTML pintado ni en una URL:

  | Widget | Controles |
  |---|---|
  | Encabezado (`heading`) | `title` |
  | Editor de texto (`text-editor`) | `editor` |
  | Botón (`button`) y Botón propio (`digitalisimo-advanced-button`) | `text` |
  | Caja de icono (`icon-box`) | `title_text`, `description_text` |
  | Caja de icono avanzada (`digitalisimo-advanced-icon-box`) | `title_text`, `sub_title_text`, `description_text` |
  | Llamada a la acción de PRO (`call-to-action`) | `title`, `description`, `button` |
  | Llamada a la acción propia (`digitalisimo-call-out`) | `title`, `description`, `button_text` |

  Dentro de un texto con HTML sólo se sustituye en los nodos de texto, nunca dentro de una etiqueta (atributos, `href`) ni en `<script>`, `<style>` o `<textarea>`. En el editor y en su vista previa no se sustituye: así la edición en línea nunca guarda un valor resuelto en lugar de la etiqueta.
- **Sintaxis y lista blanca**, decididas por `Inline_Tags\Engine::parse()` **antes** de instanciar nada; sólo lo aceptado llega a `Plugin::$instance->dynamic_tags->get_tag_data_content()` (`elementor/core/dynamic-tags/manager.php:236`) con las etiquetas de PRO por nombre:

  | Sintaxis | Etiqueta | Reglas |
  |---|---|---|
  | `{post:title}`, `{post:excerpt}`, `{post:author}`, `{post:date}` y `{post:date:<formato>}` | `post-title`, `post-excerpt`, `author-name`, `post-date` | Sin formato, el del sitio |
  | `{post:meta:<clave>}` | `post-custom-field` | Sin `_` inicial, fuera de la lista negra y nunca un campo de ACF de un tipo no permitido |
  | `{acf:<campo>}` | `acf-text` | Sólo la entrada del contexto, nunca `options:` (ni ningún `:`); tipos `text`, `textarea`, `number`, `url`, `select`, `radio`, `true_false` y `date_picker`; la lista negra también para el nombre escrito y el real |
  | `{user:display_name\|first_name\|last_name\|nickname\|description\|url}` y `{user:first_name\|visitante}` | `user-info` (`nickname`, como meta) | Respaldo de hasta 60 caracteres sin `\|`; nunca correo, login ni roles |
  | `{site:name}`, `{site:tagline}`, `{date:<formato>}` | `site-title`, `site-tagline`, `current-date-time` | — |
  | `{query:<parámetro>\|<valor1>,<valor2>…}` | Ninguna (no usa `request-arg`) | Imprime el valor de la lista que coincide exactamente con el parámetro GET (tras `wp_unslash`), o nada; sin lista queda literal; prohibidos `utm_*`, `mc_*`, `gclid`, `fbclid` y los de la lista negra |

  Lo que no encaja, lo bloqueado y lo que el sitio no tiene (ACF inactivo) queda **literal**: nunca se borra texto del usuario. `{term:name}` no existe.
- **Escape:** el valor de cada etiqueta sale con `esc_html( wp_strip_all_tags( … ) )` (las etiquetas de PRO devuelven HTML con `wp_kses_post`, que con sólo `esc_html` se vería literal) y con `[ ] { }` como entidades, porque el Editor de texto ejecuta shortcodes (`parse_text_editor()`, `elementor/includes/widgets/text-editor.php:594`). El texto del usuario conserva su contexto: el núcleo ya lo saneó al guardar.
- **Lista negra** `digitalisimo_elements_inline_tags_denied` (filtro), con una sola fuente: `Filters\Query::DENIED` (`pass`, `password`, `pwd`, `token`, `secret`, `key`, `hash`, `salt`, `nonce`, `email`, `mail`, `phone`, `tel`, `address`, `dni`, `rfc`, `curp`, `ssn`, `iban`, `card`, `name`, `apellido`, `nombre`, `telefono`, `celular`, `movil`, `correo`, `contrasena`, `clave`, `direccion`, `domicilio`, `tarjeta`, `nss`). Coincide por fragmentos separados por `_` o `-`, sin distinguir mayúsculas: `api_key` y `Api-Key` se bloquean, `keywords` y `passage` no. Se aplica a `post:meta`, a `acf` y al parámetro de `query`, no a la lista cerrada de `user`. La usan también la relación `meta:<clave>` de las consultas (§4.1) y las claves de los filtros (§5.5).
- **ACF:** `acf-text` lee el objeto consultado fuera de los bucles (`modules/dynamic-tags/acf/dynamic-value-provider.php`), que puede ser un término: durante su render se fuerza la entrada del contexto con `acf/pre_load_post_id` (prioridad 99). Si ACF no encuentra el campo entre los que la entrada guardó, la definición se toma con `acf_get_field()` y un campo permitido pero vacío imprime vacío. La etiqueta sólo existe si se carga el módulo ACF de PRO (`modules/dynamic-tags/module.php:44`).
- **Caché:** con el interruptor y alguna etiqueta en un control declarado, el elemento es dinámico (`elementor/element/is_dynamic_content`); `Visitor_Content::flag_page()` sólo con `{user:…}` y sólo si `is_user_logged_in()`. `post`, `site`, `date` y `query` nunca tocan la caché de página; con `{query:…}` la página varía por URL.
- **Memoria de ajustes:** `get_settings_for_display()` memoriza (`controls-stack.php:1176-1186, 1274-1279`) y otro gancho puede haberla leído antes; la única forma pública de vaciarla, `reset_render_state()` (`:1188-1193`), borra también los atributos, así que se guardan antes y se reponen.
- **«Insertar dato»** (`assets/js/inline-tags-editor.js`, 2 863 B): la lista blanca agrupada, el argumento con su pista y el destino (si el widget tiene varios textos); inserta con `document/elements/settings` (un «Deshacer»; dentro del último `</p>` en los textos con párrafos) y muestra la vista previa en `role="status"`. La vista previa la da la acción AJAX `digitalisimo_f7b_inline_preview` (`edit_post` sobre el documento del editor; sólo lee; estados `ok`, `empty`, `blocked`, `unknown` y `url`; nunca saca la página de la caché).
- Con `Flags::active( 'inline_tags' )` falso no se engancha nada en el front; con él, dos ganchos (sustitución y caché de elementos). Los controles, el AJAX y el script, sólo en la administración o el editor: en el front se lee el ajuste crudo (`element-base.php:675-693`).

## 11. Lógica condicional de formularios (frente J)

- **Controles** en el repetidor `form_fields` por `elementor/element/form/section_form_fields/before_section_end` + `update_control( 'form_fields', [ 'fields' => … ] )` (el camino de los tipos de campo de PRO, `modules/forms/fields/field-base.php:108-109`), con prioridad 20, al final de la pestaña Contenido del campo: `digitalisimo_conditional` («Mostrar sólo si…», guarda `yes`), `digitalisimo_logic` (`''` = todas, `any` = alguna; sin `default`) y `digitalisimo_rules`, un repetidor **dentro del repetidor** con `field` (el ID del campo), `operator` (`is`, `is_not`, `contains`, `not_contains`, `empty`, `not_empty`, `greater`, `less`; `default => is` con `save_default`) y `value`. Todos con `style_transfer => false`. No se ofrecen en `recaptcha`, `recaptcha_v3`, `honeypot`, `step` ni `hidden` (en la condición de los controles y en `Rules::from_item()`). Avisos fijos («los valores de las reglas se ven en el código de la página» y el de la vuelta atrás) y, por fila, regla hacia un campo **posterior**, hacia el propio campo, hacia un ID inexistente, sin ID o sin reglas, que pinta `forms/assets/forms-conditions-editor.js` (2 758 B, sólo en el editor, lee el modelo sin peticiones).
- **Semántica común** (PHP `Rules` = JS): texto recortado con los espacios de `String.prototype.trim` y en minúsculas (`mb_strtolower` / `toLocaleLowerCase('und')`); casillas y `select` múltiples como lista; `is`, `contains`, `greater` y `less` = «algún elemento», `is_not` y `not_contains` = su negación; `is ''` = vacío; números con coma o punto decimal (`1.000` es 1,0; `1.000,5` no es número); lo no numérico nunca cumple `greater` ni `less`; una regla sin campo o con operador desconocido nunca se cumple; sin reglas, el campo se oculta (falla cerrado); un campo oculto cuenta como vacío para quien lo mira (punto fijo de n + 1 pasadas, el mismo recorrido en los dos lados); un ID que no existe cuenta como vacío. Los vectores compartidos están en `tests/fixtures/f7b-form-rules.json` (13 de normalización, 15 de números, 35 de reglas y 15 de formularios con cascada y ciclos).
- **Formato** `data-digi-cond` en el grupo del campo: JSON compacto `{"i":"<id>","l":"any|","r":[[campo,operador,valor]]}`, escapado por `add_render_attribute`.
- **Servidor** (`forms/class-validation.php`): en `elementor_pro/forms/validation`, prioridad 5, recalcula las reglas con lo enviado (`raw_value`; en las subidas, los nombres de los archivos de `$_FILES` en sus dos formas, `fields/upload.php:154-184`) y, por cada campo oculto, `remove_field( $id )`, `unset( $ajax_handler->errors[ $id ] )` y lo quita de `sent_data`; si no quedan errores **ni** mensajes de error, `set_success( true )`. Ningún campo oculto llega a las acciones (correo, webhook, guardado): `process_fields()` ya no lo recorre, una subida oculta no se guarda y `[field id]` queda vacío. Se registra en toda petición de envío (`elementor_pro_forms_send_form`) aunque los contadores estén a 0: la validación no puede depender de que estén al día; sin campos condicionales no hace nada.
- **Estado inicial** pintado por el servidor (`elementor_pro/forms/render/item`, prioridad 20) con los valores por defecto (`Rules::defaults()` imita `Form_Base`/`Form`): `hidden` en el grupo y `disabled` en sus controles (`input<n>`, `textarea<n>`, `select<n>` y cada opción de radios y casillas). Sin parpadeo. `Hidden_Rule::add()` desde `elementor/element/parse_css` del formulario con algún campo condicional, una vez por documento.
- **Navegador** (`assets/js/forms-conditions.js`, 2 555 B, sin jQuery ni dependencias): al ocultar, `hidden` en el grupo, `disabled` en todos sus controles (no se envían, no se validan, no reciben foco), fuera las clases `elementor-field-required elementor-mark-required` (guardadas en `data-digi-required` del grupo) y fuera `required` (marcado con `data-digi-required` en cada control que lo tenía); al mostrar se restaura exactamente lo guardado. Reevalúa en `input`/`change` y tras el reinicio de PRO (el `reset` nativo que dispara `form.reset()`, en un `setTimeout`). Arranca al cargar, en `elementor/frontend/init` y en `frontend/element_ready/form.default` (popups y contenido que llega después), idempotente (`data-digi-cond-ready`). Se encola al pintar un campo condicional, no por `assets`: la caché de elementos guarda los handles encolados al pintar (`document.php:1858-1874`).
- **Hechos verificados:**
  - PRO pone `required` en **cada** opción de un grupo de radios, no sólo en la primera (`modules/forms/classes/form-base.php:180-182`, dentro del `foreach`): el script guarda y restaura exactamente los controles que lo tenían.
  - El núcleo sólo lee `assets` de los controles de primer nivel (`elementor/core/base/elements-iteration-actions/assets.php:126-131`), no de un campo de un repetidor.
  - Un repetidor dentro de un repetidor está admitido en PHP (`Control_Repeater::get_value()`, `elementor/includes/controls/repeater.php:92-110`; `get_active_settings()`, `parse_dynamic_settings()` y `sanitize_settings()`, `controls-stack.php:1238-1242, 1315-1325, 2536-2547`) y en el editor (`handleRepeaterData()` y `handleRepeaterChildren()`, `editor-modules.js:1008-1013, 2049-2097`; `document/repeater/insert|remove`, `editor.js:34614-34640, 34806-34835`). Como las filas de `form_fields` usan los campos crudos (`editor-modules.js:997-1006`), `digitalisimo_rules` lleva escritos `is_repeater`, `item_actions`, `prevent_empty` y `max_items`.
  - `Form_Record::get( 'sent_data' )` lo lee la respuesta al remitente del correo (`modules/forms/actions/email.php:513-516`): por eso también se limpia.
  - PRO dispara `$form.trigger( 'reset' )` tras un envío correcto (`assets/js/elements-handlers.js:1435-1437`), que llama al `form.reset()` nativo.
  - Con un mensaje de error general y sin errores de campo, PRO sigue con las acciones y responde error (`ajax-handler.php:123-130, 273-279`): F7b no fuerza el éxito en ese caso.
- **Desviaciones:** el `required` de las radios (arriba); el servidor no imprime `data-digi-required`, deja `required` y las clases como las pinta PRO (invisibles con el grupo oculto y `disabled`) y el script las convierte al arrancar; recursos encolados al pintar en vez de `assets`; `operator` con `save_default` (el editor quita los valores iguales al `default`, `editor-modules.js:1172-1184`); en el lienzo del editor se ven todos los campos (el Formulario se pinta allí con su `content_template()`): la lógica se ve en el front y en «Vista previa».

## 12. Kits (frente K)

El código de K no cambia: todo va por `digitalisimo_elements_kit_parts`, `digitalisimo_elements_kit_references` y `digitalisimo_elements_kit_report_labels`, desde `modules/digitalisimo-f7b/kit/` (`Kit\Module`, que sólo engancha esos tres filtros: coste 0 en el front).

**Referencias declaradas**

| Ámbito · tipo | Ruta | `kind` | `gate` |
|---|---|---|---|
| element · `*` | `digitalisimo_saved_query` | `map:queries` | Sí |
| element · `*` | `_digitalisimo_actions.*.target` | `element` | No |
| element · `*` | `_digitalisimo_actions.*.popup_id` | `post` | No |
| element · `template` | `digitalisimo_overrides.*.image` | `media` | No |
| element · `template` | `digitalisimo_overrides.*.link.url` | `url` | No |
| element · `template` | `digitalisimo_overrides.*.html` | `html` | No |
| element · `digitalisimo-loop-search`, `-meta-filter`, `-orderby`, `-per-page` | `selected_element` | `element` | No |

No se declara lo que ya traduce el núcleo: `template_id` del Plantilla (sus sobrescrituras viajan dentro, con claves, no IDs) y del Loop Grid. El núcleo **no** traduce `popup_id` dentro del repetidor (el rasgo de PRO sólo mira controles de primer nivel por nombre, `base/on-import-trait.php:53-108`), así que no hay doble traducción; la referencia declarada (`post`) gana a la derivada de su control de consulta (`template`) en `References::matching()`. La imagen, la URL y el HTML de las sobrescrituras se declaran porque sus controles sólo se registran en el editor: sin ellos, una importación por WP-CLI conservaría el ID del sitio de origen. Las reglas de un formulario miran IDs de campo del mismo formulario y las etiquetas en el texto son texto: no son referencias.

**Parte `saved-queries`** (`kit/class-queries-part.php`, `Part`, `version()` = 1, `kit_meta_keys()` vacío), en `digitalisimo/saved-queries.json`:

```
{ v: 1, queries: [ { id, uid, title, status, settings, context, relation, limits, terms: { <term_taxonomy_id>: { term, taxonomy } } } ] }
```

- **Exportar:** las consultas `publish` y `draft` (nunca la papelera), con los ajustes saneados y, por cada `term_taxonomy_id`, su par `{ term, taxonomy }`. El tipo es privado y el exportador del núcleo sólo lleva tipos públicos. Una consulta sin UUID exporta uno v4 derivado, estable, sin escribir en el origen.
- **Importar** en el runner final (`Part`, no `Early_Part`: los mapas de términos y entradas deben existir, y el núcleo guarda y K remapea los documentos después, en la misma petición): `Store::register()` antes de escribir; **reutiliza por UUID** (`Store::find_by_uid()`) sin escribir nada; si no, crea con `wp_insert_post` + `meta_input` y el `sanitize_callback` de `Store` sanea lo que llega. Términos por su `term_id` (mapa de K) y de vuelta a `term_taxonomy_id` con `get_term()` en la misma taxonomía; autores como `user` (nunca resuelven); `exclude_ids` como `post`. El mapa `queries` lleva el ID del sitio **como cadena**, que es como lo guarda el control y lo que busca la aguja de `Flags`.
- **Fallar cerrado:** la consulta entra **en borrador** (`draft.saved-query`, con el motivo) si pierde algo de una lista que usa (`terms`, `authors`, `posts`), si su tipo no es público en el destino, si su relación no vale o si su contexto no vale (por ejemplo, el retirado `parent`). `Store::get()` sólo aplica publicadas: los widgets que la usan quedan vacíos hasta revisarla. Las listas que la consulta no usa se traducen en silencio. Una referencia a una consulta que no viajó deja el control vacío (el widget vuelve a sus propios ajustes) y, por el `gate`, el documento en borrador con «revisar antes de publicar».
- **Reimportar (R5):** 0 filas y ninguna escritura; si la del sitio difiere de la del kit, se conserva la del sitio y se informa `requested.saved-query` (también en «nuevo»).
- **Revertir:** borra lo creado que sigue igual («no pisar» con `Part_Context::hash()`) y no usa ningún documento del sitio (`Usages::of()`); conserva y anota lo editado o usado.
- **Informe:** códigos `draft.saved-query`, `reference.saved-query-failed`, `reference.saved-query-invalid`, `requested.saved-query`, `requested.saved-queries-unavailable`, `revert.saved-query-edited`, `revert.saved-query-in-use` y `revert.saved-queries`, todos en secciones existentes de `Report::SECTION_PREFIXES`.
- **`_digitalisimo_uses`** no viaja (está en `not-exported.json` del arnés) y se recalcula en el destino al guardar cada documento importado y con el recorrido de §2, que se programa en `digitalisimo_elements_kit_imported`. Tras importar, el CSS de los documentos con ocultar o con formularios condicionales lleva la regla `[hidden]`: K vacía la caché de archivos al terminar y `Flags` se recalcula antes de la siguiente visita.
- **Casos** en `tests/playground/kit-roundtrip/cases.json`: páginas `digi-k-f7b-acciones`, `digi-k-f7b-consultas`, `digi-k-f7b-sobrescrituras`, `digi-k-f7b-filtros` y `digi-k-nested-slider`; la plantilla `digi-k-f7b-plantilla`; los cuatro filtros y el slider anidado en `widgets`; los rasgos `form-conditions` e `inline-tags`; la parte `saved-queries` y la sección `queries`. El caso del slider va sin reproducción automática, para que las capturas de A y B muestren la misma diapositiva.

**Desviaciones del frente K**

| Punto | Lo que decía la especificación | Lo que hay y por qué |
|---|---|---|
| `map:queries` | Destino ausente: el widget vuelve a sus ajustes | Además con `gate`: sin él, `Remapper::map_value()` conserva el ID del origen (`kits/class-remapper.php:911-935`), que en el destino puede ser otra consulta. Con `gate` queda `''` y el documento entra en borrador: más estricto, nunca más abierto. El texto de K para ese aviso habla de «una regla de acceso» (`class-report.php:94`) |
| `Early_Part` | Si hace falta | `Part`: `Early_Part` correría antes de que existan los términos y las entradas |
| Reimportar con otros ajustes | Borrador «actualización» | Reutiliza la del sitio y lo informa, como `woocommerce.attribute-differs` de F4-Woo: una segunda consulta con el mismo UUID rompería la identidad que usa `Store::find_by_uid()` |
| Arnés | `cases.json` | También `setup-a.php` (crea las consultas), `signature.php` (nombra los IDs de `_digitalisimo_uses` y `_digitalisimo_query`), `known.json` (opciones de `Flags` y `Scan` y la diferencia declarada de «Consulta con autora»), `not-exported.json` y `scenarios.cjs` (`f7bRoundtrip()` en R1 y las consultas en R8) |

## 13. Ganchos, acciones y comandos nuevos

- **Acciones de WordPress:** `digitalisimo_elements_f7b_scan_document( int $post_id, array $elements )` y `digitalisimo_elements_f7b_scan_done()` (recorrido por lotes, §2); evento de cron `digitalisimo_elements_f7b_scan`.
- **Filtros:** `digitalisimo_elements_inline_tags_denied` (la lista negra única, §10). Los tipos de filtro nuevos usan el registro existente `digitalisimo_elements_loop_filter_types` y sus dos filtros (`…_query`, `…_param_type`).
- **Constante:** `DIGITALISIMO_FORCE_DELETE` (borrar aunque haya usos).
- **AJAX del editor** (`elementor/ajax/register_actions`, todas con `edit_post` sobre el documento del editor): `digitalisimo_f7b_queries`, `digitalisimo_f7b_query_settings`, `digitalisimo_filters_meta_keys`, `digitalisimo_f7b_slots`, `digitalisimo_f7b_slot_uses` y `digitalisimo_f7b_inline_preview`; `delete_template` y `bulk_delete_templates` sustituidas por nombre (§2).
- **Administración:** `admin-post.php` con `digitalisimo_f7b_query_save`, `digitalisimo_f7b_query_delete`, `digitalisimo_f7b_query_test` y `digitalisimo_elements_interactions_play`; `admin-ajax.php` con `digitalisimo_f7b_queries_search`. Pantallas `digitalisimo-elements-queries` y `digitalisimo-elements-uses` (oculta).
- **WP-CLI:** `wp digitalisimo-elements interactions status|play`.
- **Atributos del front:** `data-digi-actions`, `data-digi-actions-ready`, `data-digi-ctx`, `data-digi-cond`, `data-digi-cond-ready`, `data-digi-required`, `data-digi-start`; parámetros `digi_s_<fid>`, `digi_m_<fid>`, `digi_m_<fid>-max`, `digi_o_<fid>`, `digi_pp_<fid>`, `digi_f` y `digi_ctx`.

## 14. Presupuesto medido

Tamaños del árbol de 4.3.0.169 (gzip -9 sin nombre de archivo, `gzip -9 < archivo`). Las filas del presupuesto y su vigilancia están en `docs/ELEMENTS_FRONT_OBJECTIVE.md`; la medición en Playground, en `docs/fusion/f7b-evidence.json`.

| Recurso | Bytes | gzip -9 | Techo / objetivo |
|---|---:|---:|---|
| `actions.js` | 5 960 | 2 933 | 6 KB / 5 KB (no alcanzado, §3.6) |
| Regla `[hidden]` (en el CSS del documento) | 73 | — | ≈ 80 B |
| `<noscript>` de «Oculto al cargar», por destino | 112–123 | — | ≈ 130 B |
| Script de movimiento reducido (en línea) | 145 | — | ≈ 150 B |
| `nested-slider.css` / `nested-slider.js` | 3 066 / 5 119 | 983 / 2 241 | 4 KB / 6 KB; objetivo 3 KB / 5 KB cumplido |
| `nested-presentation.css` | 2 550 | 497 | 4 KB; objetivo 2,5 KB cumplido |
| `forms-conditions.js` | 2 555 | 1 412 | 3 KB; objetivo 2,5 KB cumplido |
| `f7b-filters.css` | 570 | 329 | 2 KB; objetivo 1,5 KB cumplido |
| `filters.js` / `filters-form.js` (de F4-Woo) | 8 184 / 5 884 | 3 676 / 2 504 | 8 KB / 6 KB |
| Editor: `actions-editor.js`, `queries-editor.js`, `overrides-editor.js`, `inline-tags-editor.js`, `apply-all-editor.js`, `nested-slider-editor.js`, `forms-conditions-editor.js`, `filters-editor.js` | 4 790, 6 733, 7 831, 2 863, 3 722, 3 862, 2 758, 2 720 | — | 8 KB cada uno, sólo en el editor |
| Pantalla «Consultas»: `queries-screen.js` | 4 255 | 1 504 | Sólo en la pantalla |

## 15. Pruebas

Veinticinco pruebas nuevas en `validate.yml` y `release.yml`, con listas iguales (las `*-chrome.mjs` se saltan solas sin Chrome o sin `puppeteer-core`, como en CI, y no corren bajo `ulimit -v`). Dobles compartidos en `tests/fixtures/f7b-wp.php`; HTML de las pruebas en Chrome en `tests/fixtures/f7b-*.html`.

| Prueba | Qué cubre | Comprobaciones |
|---|---|---|
| `elements-f7b-module.php` | Arranque, autocarga, guardas, `editing()`, `Flags`, `Hidden_Rule`, front con los contadores a 0 (ningún gancho de render en los submódulos) y la línea de `pro-elements.php` | 399 |
| `elements-f7b-usages.php` | Meta de usos, recorrido por lotes, bloqueo y excepciones, `edit.php`, pantalla de usos, AJAX sustituido, suscriptor y autor ajeno en cada acción, contrato del registro AJAX del núcleo | 88 |
| `elements-f7b-static.mjs` | Revisión estática: textos en español sin proveedores, `style_transfer => false` en los controles que no son de estilo, sin `default` de estilo en widgets existentes, `defer` sin jQuery, topes, `assets` y clases `digi-` | — |
| `elements-f7b-actions.{php,mjs}`, `elements-f7b-actions-chrome.mjs` | Listas, destinos, regla de `click`, `<noscript>`, recogida de popups, `actions.js` y `actions-editor.js`; en Chrome, `display:none` en flex, grid, columna y widget (y que sin la regla no), teclado, árbol de accesibilidad, `hover` con foco y ratón, popup por `runAction()`, `scroll_in` y movimiento reducido | 271 / 80 / 43 |
| `elements-f7b-interactions.php` | Estados, inventario, aviso, la línea del derivado y la sha256 del resto, con el constructor real y su `hooks.php` | 74 |
| `elements-f7b-queries.{php,mjs}` | CPT y metas, saneado, fusión frente al grupo real de PRO en seis combinaciones, contextos y relaciones con bucle en bucle, `parent` fallando cerrado, presupuesto, `digi_ctx`, permisos, «Desvincular», pantalla y scripts | 349 / 57 |
| `elements-f7b-filters.{php,mjs}` | Tipos, nombres y `-max` con el método real del derivado, mismos argumentos que `WP_Query`, topes, petición forjada, «Archivo», convivencia con F4-Woo, AJAX, widgets y formularios con `filters-form.js` y el coordinador | 485 / 68 |
| `elements-f7b-overrides.{php,mjs}` | Clave, cada `kind` en los siete tipos, dos instancias en los dos órdenes y en segunda visita, caché apagada sólo para esa plantilla (con el núcleo y el Plantilla reales), Markdown, AJAX y el script del editor | 209 / 55 |
| `elements-f7b-nested.php`, `elements-f7b-nested-slider{,-chrome}.mjs` | Registro, guardas, marcado y ARIA (también con valores forjados), plantilla del editor = servidor, teclado, reproducción automática y pausas, movimiento reducido; en Chrome, tres anchos, foco, CLS 0 y árbol de accesibilidad | 105 / 110 / 66 |
| `elements-f7b-presentation.{php,mjs}`, `elements-f7b-presentation-chrome.mjs` | Control y hoja; `nested-tabs` y `nested-accordion` sin presentación idénticos a 4.3.0.168 (con el ZIP de comparación); especificidad, contraste y que el usuario gana | 65 / 74 / 56 |
| `elements-f7b-apply-all.mjs` | El código real de «Pegar estilo» extraído de `editor.js`: un comando, un registro de historial, clave borrada también tras un fallo y ningún control de F7b copiado | 97 |
| `elements-f7b-inline-tags.php` | Sintaxis, escape, sólo nodos de texto, lista blanca antes de instanciar, fragmentos, ACF, `{query:…}`, `{user:…}`, `flag_page()` sólo con sesión, el enlace del Botón intacto, permisos | 96 |
| `elements-f7b-forms.{php,mjs}`, `elements-f7b-forms-chrome.mjs` | Vectores compartidos, controles, estado inicial, envío real con el `Ajax_Handler` de PRO («obligatorio, oculto y vacío» se envía; un campo oculto con valor no llega a las acciones), DOM y Chrome | 220 / 198 / 45 |
| `elements-f7b-kit.php` | Referencias, remapeo real de un documento, parte `saved-queries` (importar, borrador, R5, revertir, exportar) y que K no nombra nada de F7b | 119 |

Ampliadas: `elements-pro-inventory.mjs` (`createActionHash()`, `runAction()` y `urlActions` del núcleo; `showPopup`, `closePopup` y `handleDisplayToggle` de PRO), `elements-kit-inventory.mjs` (lee `Filters\Registry::WIDGETS` y `Nested\Module::WIDGETS`) y `elements-kit-integration.php` (recorre también `modules/digitalisimo-f7b/`). Playground, a mano: `tests/playground/f7b.sh` (B1–B12, puertos 9500 a 9509) y las ampliaciones de `front-budget.sh`, `kit-roundtrip.sh`, `interaction.sh` y `brand-audit.sh`; la evidencia está en `docs/fusion/f7b-evidence.json`.
