# F7b · Capacidades que Bricks necesita, en DIGITALÍSIMO Elements

Evidencia y contratos de la etapa F7b de `docs/FUSION_OBJECTIVE.md`. La especificación vive fuera del repositorio; este documento recoge lo verificado. Cada frente añade su sección.

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
| El constructor de consultas de PRO aplica `apply_filters( 'elementor/query/query_args', $this->query_args, $this->widget )` al final, y `elementor/query/get_query_args/current_query` para «Consulta actual» | `modules/query-control/classes/elementor-post-query.php:156, 128` | C2: con una consulta guardada elegida, el filtro sustituye los argumentos por los de la consulta, resueltos con el contexto |
| La sección de consulta se llama `section_query` en Posts (`modules/posts/widgets/posts.php:112`), Portfolio (`:210`) y en la base del Loop Grid y el Loop Carousel (`modules/loop-builder/widgets/base.php:165`); Galería no usa el grupo de PRO; Productos de WooCommerce usa su rasgo propio (`modules/woocommerce/traits/products-trait.php`) | — | D8: el control «Consulta guardada» se añade con `elementor/element/{posts,portfolio,loop-grid,loop-carousel}/section_query/before_section_end` |
| El Loop Grid recorre sus entradas con el bucle de WordPress y pinta cada una con `the_post()` → `render_post()` → `render_post_content()`, que lee `get_the_ID()` y llama a `$document->print_content()` de la plantilla de bucle | `modules/loop-builder/skins/skin-loop-base.php:115-135, 201-220` | D9: dentro de una plantilla de bucle, `current` = `get_the_ID()`; un Loop Grid anidado conoce a su «padre» porque, al empezar a pintarse, el global `$post` es la entrada del bucle exterior: `Queries` apila `get_the_ID()` en `elementor/widget/before_render_content` de los widgets de bucle y lo desapila en `elementor/widget/after_render_content` (`parent` = tope anterior de la pila; `root` = `get_queried_object()` de la consulta principal) |

### 1.3 Filtros

| Hecho | Dónde | Uso en F7b |
|---|---|---|
| Registro de tipos de F4-Woo: `digitalisimo_elements_loop_filter_types` (`:117`), `digitalisimo_elements_loop_filter_query` (`:151`) y `digitalisimo_elements_loop_filter_param_type` (`:251`) | `modules/loop-filter/module.php` | C3: los cuatro tipos nuevos se declaran ahí, sin tocar el derivado |
| El coordinador `assets/js/filters.js` (8 161 B) se engancha en `elementor-pro/modules/init/before` y `assets/js/filters-form.js` (5 673 B) registra los formularios en `element_ready` | `docs/fusion/f4woo.md` §3 | C3 reutiliza los dos; sólo `filters-form.js` crece si los controles nuevos lo exigen (tope 6 KB) |

### 1.4 Plantillas, Template y caché de elementos

| Hecho | Dónde | Uso en F7b |
|---|---|---|
| `apply_filters( 'elementor/frontend/builder_content_data', $data, $post_id )` antes de pintar un documento | `elementor/includes/frontend.php:1173` | C4: durante `get_builder_content_for_display( $template_id )` del widget Template con sobrescrituras, un filtro acotado a ese `$post_id` sustituye los ajustes de los elementos con la clave |
| El widget Template pinta `get_builder_content_for_display( $template_id )` si la plantilla está publicada; `on_import()` traduce `template_id` | `modules/library/widgets/template.php:87-102, 44-45` | C4: las sobrescrituras se aplican desde `elementor/widget/render_content` del widget `template` |
| Con la caché de elementos, `print_element()` imprime `[elementor-element k="<id único>" data="…"]` y el ID único pasa por `apply_filters( 'elementor/element_cache/unique_id', '' )` | `elementor/includes/base/element-base.php:481-487` | C4: el Template con sobrescrituras añade `digi-ovr-<hash>` al ID único, así cada combinación tiene su caché |

### 1.5 Anidados y slider

| Hecho | Dónde | Uso en F7b |
|---|---|---|
| `Widget_Nested_Base` exige `get_default_children_elements()` y `get_default_repeater_title_setting_key()`, y ofrece `get_default_children_title()`, `get_default_children_placeholder_selector()` y `get_default_children_container_placeholder_selector()` | `elementor/modules/nested-elements/base/widget-nested-base.php:25-62` | C5: `digitalisimo-nested-slider` las implementa |
| `nested-tabs`: secciones `section_tabs` (`:111`), `section_tabs_responsive` (`:375`), `section_tabs_style` (`:448`), `section_title_style` (`:717`), `icon_section_style` (`:851`), `section_box_style` (`:994`) | `elementor/modules/nested-tabs/widgets/nested-tabs.php` | C6: el control «Presentación» entra tras `section_tabs_style` (en `nested-accordion`, su equivalente; frente G lo anota) |
| El Slider Optimizado declara los handles `digitalisimo-slider-optimizado` (hoja y script) | `modules/digitalisimo-slider/class-widget.php:12-13` | C5: el slider anidado reutiliza la hoja; si el script se puede compartir sin cambiar la salida del Slider Optimizado, se extrae su motor; si no, motor propio y se declara |

### 1.6 Etiquetas dinámicas y formularios

| Hecho | Dónde | Uso en F7b |
|---|---|---|
| El gestor del núcleo instancia etiquetas por nombre: `create_tag( $tag_id, $tag_name, $settings )` y `get_tag_data_content( $tag_id, $tag_name, $settings )`; `parse_tags_text()` sustituye cada `[elementor-tag …]` de un texto | `elementor/core/dynamic-tags/manager.php:211, 236, 61` | C8: la sintaxis corta `{grupo:nombre}` se resuelve con `get_tag_data_content()` de la etiqueta equivalente; no se inventa otro motor ni se usa la sintaxis interna en el texto del usuario |
| Los tipos de campo de PRO añaden controles al repetidor con `add_action( 'elementor/element/form/section_form_fields/before_section_end', [ $this, 'update_controls' ] )` | `modules/forms/fields/field-base.php:108-109` | C9: los controles de las reglas entran por el mismo gancho con `update_control( 'form_fields', [ 'fields' => … ] )` |
| `Form_Record` ofrece `get( $property )`, `set()`, `get_field( $args )`, `remove_field( $id )`, `update_field( $field_id, $property, $value )` y `validate( $ajax_handler )` | `modules/forms/classes/form-record.php:186-214, 44` | C9: en `elementor_pro/forms/validation`, los campos ocultos por sus reglas se quitan del registro con `remove_field()` antes de validar y de las acciones |

### 1.7 Lo que el frente 0 corrige en la especificación v1

- **Widgets con «Consulta guardada»:** Posts, Portfolio, Loop Grid y Loop Carousel (no Galería, que no usa el grupo de PRO; Productos de WooCommerce queda fuera: tiene su propio rasgo de consulta).
- **Contexto «padre»:** no existe una pila en PRO; la aporta `Queries` apilando en `before_render_content`/`after_render_content` de los widgets de bucle (§1.2).
- **Acciones de popup y off-canvas:** con `createActionHash()` + `runAction()` del núcleo, no construyendo el *hash* a mano.
