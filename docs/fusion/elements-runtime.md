# Informe del frente: lo que DIGITALÍSIMO Elements ya ofrece en runtime, contenido dinámico y plantillas

> Revisión del 16 de octubre de 2026 (Elements 4.3.0.156, núcleo 4.3.5), depurada para el repositorio público. Las rutas son relativas a `digitalisimo-elements/` salvo que digan otra cosa. Donde el plan aprobado decidió otra cosa, manda `docs/FUSION_OBJECTIVE.md`; las diferencias están al final.

Lo más importante primero:

- **Interacciones PRO apagadas.** El módulo PRO de interacciones nunca se activa en Elements, porque `license/admin.php:33-35` devuelve una clave vacía y `modules/interactions/module.php:23` la exige. Por eso hover, click, scrollOn y los efectos personalizados no funcionan aunque el script `assets/js/interactions-pro.js` venga incluido.
- **El editor v4 está apagado en la mayoría de los sitios actuales.** Interacciones, Collection Loop, clases globales, variables y componentes dependen del experimento oculto `e_atomic_elements`. Ese experimento sólo se activa solo en sitios instalados con Elementor 4.0 o posterior.
- **El importador de kits no admite adaptadores externos.** La lista está fija en `processes/import.php:663` y no tiene filtro. Además, el ZIP sólo admite archivos json y xml (`:618`).
- **El importador de plantillas exige `content`.** Un JSON de Divi falla antes de llegar a cualquier filtro. Un JSON de Bricks entra, pero sus elementos se descartan sin aviso.
- **Para importar formatos ajenos hay que preconvertir.** El camino seguro es generar un kit estándar v3.0 o registrar un `Source` propio.

## 1. Qué hay, versión y formato

- **Plugin**: DIGITALÍSIMO Elements 4.3.0.156 (`digitalisimo-elements/pro-elements.php:6`). Define `ELEMENTOR_PRO_VERSION` 4.3.0 (`:64`) e `IS_PRO_ELEMENTS` (`:83`).
- **Núcleo incluido**: Elementor 4.3.5 sin cambios (`elementor/elementor.php:31`).
- **Módulos derivados de PRO**: se cargan desde `core/modules-manager.php:18-89`. Son unos 70 módulos, entre ellos theme-builder, popup, display-conditions, dynamic-tags, query-control, loop-builder, loop-filter, collection-loop, nested-carousel, mega-menu, off-canvas, woocommerce, forms, atomic-form, interactions, custom-code, global-widget y cloud-library.
- **Módulos propios**: se cargan en `pro-elements.php:112-117`. Son Slider Optimizado (`modules/digitalisimo-slider/`), el registro de 51 widgets propios (`modules/digitalisimo-widgets/class-registry.php`) y las condiciones propias (`modules/digitalisimo-conditions/class-module.php`).
- **Licencia PRO**: se resuelve en local como vitalicia, con una lista de funciones (`license/api.php:104-141`). Además, `BC_VALIDATION_CALLBACK` llama a `should_allow_all_features()` (`:74`, `:207-209`), que devuelve verdadero porque no hay niveles de licencia. Así se habilita casi todo, **salvo lo que pide una clave no vacía**: interacciones PRO.
- **Experimentos que condicionan qué existe en cada sitio**:

| Experimento | Valor por defecto | Activo en sitios nuevos desde | Fuente |
|---|---|---|---|
| `container` | inactivo | 3.16 | `elementor/core/experiments/manager.php:372-394` |
| `e_atomic_elements` (oculto) | inactivo | 4.0 | `elementor/modules/atomic-widgets/module.php:240-252` |
| `e_opt_in_v4` | inactivo | 4.0 | `atomic-widgets/opt-in/opt-in.php:11-63` |
| `e_pro_collection_loop` | activo (pero exige atomic) | — | `modules/collection-loop/module.php:39-47` |
| `nested-elements` | activo | — | `elementor/modules/nested-elements/module.php:16-19` |
| `import-export-customization` | activo, no se puede cambiar | — | `elementor/app/app.php:290-300` |

  El opt-in v4 escribe tres opciones: `elementor_experiment-e_opt_in_v4`, `elementor_experiment-container` y `elementor_experiment-e_atomic_elements`.

## 2. Capacidades: completas, parciales y faltantes

### Theme Builder (`modules/theme-builder`)

**Completo:**
- Tipos de plantilla: header, footer, single, single-post, single-page, archive, search-results, error-404, section (ubicación personalizada), popup y loop-item. Con WooCommerce se suman product, product-post y product-archive (`modules/woocommerce/module.php:228-243`).
- Ubicaciones núcleo: header, footer, archive y single (`classes/locations-manager.php:640-668`).
- Ubicaciones personalizadas: un tema las registra con `register_location()` (`:546-566`, acción `elementor/theme/{loc}`). Un documento «section» se asigna a cualquier ubicación pública (`documents/section.php:38-80`).
- Condiciones include/exclude con subcondiciones:
  - Generales: `general`, `archive`, `singular`, `front_page`, `not_found404`, `search`, `date`, `author`.
  - Por tipo o taxonomía: `{cpt}`, `{cpt}_archive`, `{cpt}_by_author`, `in_{tax}`, `in_{tax}_children`, `child_of`, `any_child_of`, `child_of_{tax}`, `any_child_of_{tax}`, `by_author`.
  - WooCommerce: `woocommerce`, `shop_page`, `product_archive`, `product_search`.
- Detección de conflictos por AJAX (`classes/conditions-manager.php:98-181`).
- Vista previa forzada con `?theme_template_id=` (`:423-438`) e inspector de depuración (`locations-manager.php:670`).
- Si el tema no declara ubicaciones, Elements sobrescribe `get_header`/`get_footer` (`classes/theme-support.php:36-69`). Hay soporte específico para GeneratePress (`theme-support/generate-press-theme-support.php:17-63`).

**Prioridad (cómo elige plantilla):**
- Número menor gana (`conditions-manager.php:468-487`).
- Base: Condition_Base 100, archive 80, singular 60, taxonomy/author/date/search/cpt_archive 70, post/in_tax/child_of/by_author/Woo 40, front_page 30, not_found404 20.
- Una subcondición resta 10. Un ID concreto resta 10 más. Una subcondición sin hijas resta 5.
- Se ordena con `asort` (`:411`).
- Una ubicación que no es `multiple` pinta sólo la primera (`:532-541`).

**Parcial:**
- No hay prioridad manual. Los empates se resuelven por el orden del array en la opción de caché.
- Un `exclude` que se cumple elimina la plantilla entera, aunque otra regla include más específica de esa misma plantilla también se cumpla (`:401-409`).
- Sólo cuentan las plantillas publicadas (`:385-394`).

**Falta:**
- Condiciones de plantilla por usuario, rol, fecha programada o dispositivo. Esas sólo existen por elemento o en los popups.

### Condiciones de visualización

**Completo:** el motor nativo PRO.
- Se guarda en el ajuste `e_display_conditions`. La interfaz está en la pestaña Avanzado (`modules/display-conditions/module.php:155-180`).
- Se evalúa en `elementor/frontend/before_render`. Si falla, devuelve `should_render=false` y corta los hijos (`:216-245`).
- Estructura: grupos OR de condiciones AND (`classes/or-condition.php`, `and-condition.php:22-48`).
- Condiciones nativas (`classes/conditions-manager.php:23-54`): página, entrada, usuario, fecha y hora, archivos, URL de origen y `dynamic_tags`. Esta última compara cualquier etiqueta dinámica o campo personalizado.
- 14 comparadores (`classes/comparator-provider.php:12-26`).
- Puntos de extensión: `elementor/display_conditions/register_groups` (`:91`) y `elementor/display_conditions/register` (`:201`).
- En v4 usa el prop `display-conditions`, que se transforma al mismo JSON (`modules/atomic-widgets/transformers/display-conditions.php:22-33`).

**Completo (propio):**
- `modules/digitalisimo-conditions/class-module.php:18-83` registra **en el mismo gestor** 12 condiciones generales: usuario, sistema operativo, navegador, idioma, país, parámetro de URL, URL actual, buscador, tipo de contenido, contenido específico, página especial y shortcode.
- Suma 16 de WooCommerce: carrito, compras y producto.
- `class-visitor-content.php` resuelve dos problemas de caché:
  - Caché de elementos: los widgets que incrustan plantillas se marcan dinámicos (`:29`, `:86-104`).
  - Caché de página: decide en `template_redirect` (`:62-67`) leyendo el documento y las plantillas del TB. Las condiciones que no varían entre visitantes anónimos están en `:44-52` y se amplían con el filtro `digitalisimo_elements_visitor_content_cacheable_conditions`.
- Hay un motor heredado de reglas por elemento (`class-visibility.php`, `class-visibility-rules.php:15-17`). Sólo aparece si ya existía; el contenido nuevo usa Display Conditions.

**Falta** frente a Divi 5.15, que trae Cookie, PageVisit, NumberOfViews y DynamicPosts (`reference/Divi.zip:Divi/includes/builder/module/field/display-conditions/*`):
- No hay condición de cookie en el motor nativo.
- No hay condición de «visitó la página X».
- El contador de visitas sólo existe en el motor heredado (`class-visibility-rules.php:307-316`), no como condición nativa.

### Popups (`modules/popup`)

**Completo:**
- Documento `popup` con ubicación `multiple` (`module.php:205-215`), impreso en `wp_footer` (`:217-219`).
- Condiciones del Theme Builder.
- Disparadores (`display-settings/triggers.php:31-126`): page_load, scrolling, scrolling_to (selector), click, inactivity, exit_intent y adblock_detection.
- Reglas de tiempo y alcance (`display-settings/timing.php:31-244`): page_views, sessions, times, url, sources, logged_in, devices, browsers y schedule.
- Etiqueta-acción open/close/toggle mediante `create_action_hash` (`tag.php:49-125`, `elementor/includes/frontend.php:1371-1373`).
- Acción de formulario propia (`form-action.php`).

**Riesgo vigente (Fase 2 pendiente):** el paquete `pro-elements-handlers` (292 KB, incluye popups) se carga en toda página (`docs/ELEMENTS_FRONT_OBJECTIVE.md`, línea base).

### Interacciones y movimiento

**Completo (clásico):**
- Motion FX: translateY, translateX, opacity, blur, rotateZ, scale, mouseTrack y tilt, con rango y dispositivos (`modules/motion-fx/controls-group.php:145-419`).
- Sticky con `sticky_parent` (`modules/sticky/module.php:48-193`).
- Animaciones de entrada del núcleo, page-transitions y scroll-snap.

**Parcial (v4 atómico):**
- Esquema `interactions.items[]` (`elementor/modules/interactions/parser.php:33-98`). Cada interacción tiene `trigger` y `animation`, con `effect`, `type`, `direction`, `timing_config`, `config` y `custom_effect` (`props/interaction-item-prop-type.php:21-24`, `animation-preset-prop-type.php:21-26`, `animation-config-prop-type.php:23-29`).
- Disparadores base: `load` y `scrollIn`. Disparadores PRO: `scrollOut`, `scrollOn`, `hover` y `click` (`presets.php:18-19`, `validators/trigger-value.php`).
- Caché en el meta `elementor-interactions-cache` (`cache/interactions-postmeta.php:12`). Se imprime como JSON en el pie (`interactions-frontend-handler.php:74-90`).
- **La acción siempre es una animación sobre el mismo elemento. No hay destino ajeno.**

**Bloqueado:** lo PRO de interacciones (`modules/interactions/module.php:22-32,41`). Eso incluye hover, click, scrollOn, easing adicionales, repeat, relativeTo y custom.

**Falta:** el patrón genérico evento→acción→destino de Bricks y Divi 5: mostrar u ocultar, alternar clase o atributo, ir a un punto, llamar una función o guardar en storage sobre un selector. Lo único equivalente son los hashes `elementor-action:`, que cubren popup (`popup:open`), off-canvas (`off_canvas:{acción}`, `modules/off-canvas/tag.php:78`) y lightbox.

### Etiquetas dinámicas (`modules/dynamic-tags`)

**Completo:**
- 35 etiquetas de entrada, archivo, sitio, autor y usuario (`module.php:97-139`). User Info expone id, display_name, login, first_name, last_name, description, email, url y meta (`tags/user-info.php:76-96`).
- Request_Parameter, Shortcode, Lightbox, Contact_URL y Post_Custom_Field.
- ACF: text, number, url, image, gallery, file, color y date-time.
- Toolset y Pods, que se cargan sólo si el plugin existe (`:44-54`).
- WooCommerce: 15 etiquetas (`modules/woocommerce/module.php:183-193`).
- En v4 se mapean con `elementor/modules/atomic-widgets/dynamic-tags/*`.

**Parcial:** ACF repetidor y galería como origen de elementos sólo en 5 widgets propios (`modules/digitalisimo-widgets/class-acf-source.php:7-13`). No existe un bucle de repetidor ACF en Loop Grid.

### Consultas, bucles y filtros

- **Query control (completo):** origen por tipo, «Current Query» (`current_query`) o manual (`by_id`), con include/exclude por términos, autores o manual, y offset (`controls/group-control-query.php:40-219`). Existe una consulta relacionada (`classes/elementor-related-query.php`). Los filtros `elementor/query/query_args` (`classes/elementor-post-query.php:156`) y `elementor/query/{query_id}` (`:408`) dan ganchos de código.
- **Loop Builder (completo):**
  - Documento `loop-item` con meta `_elementor_source` (`documents/loop.php:25,85`).
  - Loop Grid y Loop Carousel con skins post, post_taxonomy, product y product_taxonomy (`module.php:24-34`, `woocommerce/module.php:1502-1505`).
  - Masonry y altura igual (`widgets/loop-grid.php:98-123`).
  - Paginación por números, «cargar más» por clic e infinita (`widgets/base.php:210-239`).
  - Plantillas alternas (`traits/alternate-templates-trait.php`).
  - CSS dinámico por elemento del bucle (`files/css/loop-dynamic-css.php`).
- **Loop Filter (parcial):**
  - Sólo el widget de taxonomía (`widgets/taxonomy-filter.php:74-411`): multiselección, lógica AND/OR y jerarquía.
  - Estado en la URL `e-filter-{id7}-{taxonomía}=t1,t2` (`module.php:171-233`).
  - AJAX por `elementor-pro/v1/loop-filter/refresh-loop` (`data/endpoints/refresh-loop.php:19-90`, `core/data/controller.php:17`).
  - El historial usa `history.pushState` (`assets/js/elements-handlers.js:9899`; paginación en `:8256`), **pero no hay manejo de `popstate`**: el botón «Atrás» cambia la URL y no vuelve a pintar el bucle.
  - **Faltan** búsqueda, rango, meta, orden, valoración, resumen de resultados y filtros activos. El propio código lo admite: «non-generic, should be refactored» (`module.php:104`).
- **Collection Loop v4 (parcial):** `e-collection-loop` con `loop-query` (`template_type`, `posts_per_page`, `query_id`, `query/loop-query.php:99-123`). Sólo admite filtros estáticos terms, authors, manual_selection y current_post (`elements/collection-loop/query-filters.php:9-48`). Se pueden registrar tipos de plantilla por acción (`module.php:97`). Exige la licencia `atomic-loop` y el editor atómico (`:84-92`).
- **Búsqueda en vivo (completo):** widget `search` con `live_results` y `template_id` (`modules/search/widgets/search.php:305-317`).

### Elementos anidados

**Completo:**
- Núcleo: `nested-tabs` y `nested-accordion`.
- PRO: nested-carousel, mega-menu y off-canvas. Exigen `container` (`modules/nested-carousel/module.php:32`, `mega-menu/module.php:38`, `off-canvas/module.php:32`).
- v4: `e-tabs`, y `e-accordion` y `e-list` sólo en experimentos de desarrollo (`atomic-widgets/module.php:255-303`).

### Slider Optimizado

**Completo:**
- Widget `digitalisimo-slider-optimizado` con CSS y JS propios y `defer` (`modules/digitalisimo-slider/class-widget.php:7-13`, `class-module.php:45`).
- Se conservan los nombres de controles ([`docs/SLIDER_OPTIMIZADO.md`](../SLIDER_OPTIMIZADO.md)).
- Tiene una excepción documentada en el presupuesto del front (`tests/elements-front-budget.php:27`).

### Constructor WooCommerce

**Completo:**
- Ficha y archivo con 19 widgets de producto.
- Carrito, Checkout, Mi cuenta (con plantilla de escritorio, `widgets/my-account.php:2138-2158`), Purchase Summary (página `elementor_woocommerce_purchase_summary_page_id`), Notices y Menu Cart (mini carrito `wc-templates/cart/mini-cart.php`).
- Sincroniza las páginas Woo con el kit (`module.php:51-56`, `:920-925`).
- Tipos de plantilla v4 para producto y taxonomía de producto (`woocommerce/collection-loop/*`).

**Falta:** plantilla de «carrito vacío» y de «pedido recibido» como tipo del Theme Builder.

### Formularios

**Completo:**
- Campos clásicos (`modules/forms/widgets/form.php:112-127`): text, email, textarea, url, tel, radio, select, checkbox, acceptance, number, date, time, upload, password, html y hidden, más step (multipaso).
- Acciones: email, email2, redirect, webhook, Mailchimp, ActiveCampaign, GetResponse, ConvertKit, MailerLite, Drip, MailPoet, Slack, Discord, cf7db, activity-log y guardar en la base de datos (tablas `e_submissions`, `_values` y `_actions_log`, `submissions/database/query.php:25-27`).
- Login, honeypot, reCAPTCHA v2/v3 y Akismet.
- v4 `e-form`: acciones email, webhook y collect-submissions (`modules/atomic-form/actions/*`).

**Falta:** lógica condicional de campos, cálculos y repetidores. Una búsqueda de `conditional_logic` no encuentra nada.

### Recursos y CSS

- **CSS por documento (completo):** `uploads/elementor/css/post-{id}.css` con el meta `_elementor_css` (estado file, inline o empty, `elementor/core/files/css/post.php:28-30`, `base.php:38-52`). El modo de impresión lo fija `elementor_css_print_method` (`base.php:120`). El Theme Builder encola el CSS de cada plantilla asignada (`locations-manager.php:169-211`).
- **CSS atómico (completo):** archivos por estilo y por breakpoint con `media` (`atomic-widgets/styles/css-files-manager.php:9,198-209`, `atomic-styles-manager.php`).
- **Recursos por elemento (completo para el núcleo y lo propio):**
  - `get_style_depends()`/`get_script_depends()` más la propiedad `assets` con `conditions` en cada control. Se guarda en `_elementor_page_assets` (`core/base/elements-iteration-actions/assets.php:13,28-60,80-92`).
  - CSS `widget-{name}` por widget (`elementor/includes/frontend.php:567-578`, 98 hojas).
  - Para las plantillas del TB se reutiliza `_elementor_page_assets` (`locations-manager.php:219-231`).
- **Recursos por elemento en PRO (parcial):** `pro-elements-handlers` (292 KB) y `elementor-pro-frontend` (80 KB) se cargan siempre. Es la Fase 2 de `docs/ELEMENTS_FRONT_OBJECTIVE.md`.
- **Caché de elementos (completo):**
  - Meta `_elementor_element_cache` (`core/base/document.php:48,1964-2001`).
  - Activa salvo `elementor_element_cache_ttl=disable` (`:1835`).
  - Control `_element_cache` por elemento (`modules/element-cache/module.php:93-113`).
  - Los elementos dinámicos se reenvían con el shortcode `[elementor-element k data]` (`:48-74`), según `elementor/element/is_dynamic_content` (`includes/base/element-base.php:590-612`).
- **Presupuesto (completo para lo propio):** `tests/elements-front-budget.php` revisa 45 widgets: defer, sin jQuery, Image_Markup, CSS ≤ 4 KB y JS ≤ 8 KB.

### Importador, biblioteca y kits

- **App de kits (completo):**
  - Formato 3.0 (`app/modules/import-export-customization/module.php:31`).
  - Runners en orden fijo (`processes/import.php:261-269`): site-settings, plugins, templates, taxonomies, elementor-content, wp-content (WXR mediante el `WP_Import` del núcleo, `runners/import/wp-content.php:80-86`) y floating-elements.
  - Sesiones con reversión (`:395-445`).
  - WP-CLI `wp elementor kit import|export` (`wp-cli.php:71-99`).
  - Variante legacy 2.0 (`app/modules/import-export/module.php:27`).
- **Biblioteca de plantillas (completo):**
  - JSON o ZIP de JSON (`includes/template-library/sources/local.php:926-976`).
  - Filtros `elementor/template_library/sources/local/import/elements` y `elementor/template_library/import/process_content`, que reciben el `$data` crudo (`sources/base.php:464-513`).
  - `register_source()` (`manager.php:113`). `import_template()` respeta `$data['source']` (`:603-633`).
  - Los medios se descargan por URL (`classes/class-import-images.php:133`).
- **Biblioteca en la nube y kit library (parcial):** dependen de Elementor Connect. No es un servicio propio.
- **Diseño global:**
  - Kit en la opción `elementor_active_kit` (`core/kits/manager.php:22`).
  - Clases globales v4 en el CPT `e_global_class` (`global-classes/global-class-post-type.php:10`).
  - Variables en el meta del kit `_elementor_global_variables` (`variables/storage/constants.php:13`).
  - Componentes v4 en el CPT `elementor_component` (`components/documents/component.php:18`).
  - Global widget clásico: `widgetType:"global"` con `templateID` (`modules/global-widget/widgets/global-widget.php:35-120`).
  - Plantilla incrustada con `[elementor-template id]` (`modules/library/classes/shortcode.php:13,73`).
- **Código personalizado (completo):** CPT `elementor_snippet` con ubicación `elementor_head`, `elementor_body_start` o `elementor_body_end` y prioridad. Usa las condiciones del TB (`modules/custom-code/module.php:32-34`, `custom-code-metabox.php:13-24`).

### Matriz pedida (Bricks / Divi)

| Capacidad | Estado | Evidencia |
|---|---|---|
| Consultas globales reutilizables por ID | **Falta** (sólo un gancho PHP `elementor/query/{id}`, no una consulta guardada) | `elementor-post-query.php:408`, `collection-loop/query/loop-query-runner.php:28` |
| Contextos padre o raíz en bucles anidados | **Falta**. Parcial: el bucle usa el post actual, «Current Query» usa la consulta raíz y Related usa el post actual | `group-control-query.php:43-45`, `collection-loop/utils/loop-iteration-context.php` (sólo dice si hay bucle activo) |
| Filtros con estado en URL e historial | **Parcial** (taxonomía, pushState sin popstate) | `loop-filter/module.php:171-233`, `elements-handlers.js:9899` |
| Condiciones declarativas | **Completa** por elemento (JSON OR/AND, 40+ condiciones). **Parcial** en plantillas (sin usuario ni fecha) | sección «Condiciones de visualización» |
| Interacciones evento→acción→destino | **Parcial** (v4: animaciones sobre sí mismo, PRO bloqueado; clásico: hashes de popup y off-canvas) | `interactions/presets.php:18-19`, `modules/interactions/module.php:23` |
| Recursos por elemento | **Completa** en núcleo y propios. **Parcial** en PRO | `assets.php:13`, `ELEMENTS_FRONT_OBJECTIVE.md` Fase 2 |

## 3. Formatos de datos exactos

**`_elementor_data`** (postmeta, JSON con `wp_slash`). Va acompañado de `_elementor_edit_mode=builder`, `_elementor_template_type`, `_elementor_version` y `_elementor_page_settings`:

```json
[{"id":"a1b2c3d","elType":"container","isInner":false,
  "settings":{"__globals__":{"background_color":"globals/colors?id=primary"},
    "e_display_conditions":["[[{\"id\":\"x1\",\"condition\":\"login_status\",\"comparator\":\"is\",\"status\":\"logged_in\"}],[{\"condition\":\"digitalisimo_country\"}]]"]},
  "elements":[{"id":"b2c3d4e","elType":"widget","widgetType":"heading",
    "settings":{"title":"Hola","__dynamic__":{"title":"[elementor-tag id=\"7f3e2a1\" name=\"post-title\" settings=\"%7B%7D\"]"}},"elements":[]}]}]
```

- `__dynamic__` y `elementor-tag`: `core/dynamic-tags/manager.php:16,22,113-160`.
- Globales: `core/kits/documents/tabs/global-colors.php:14-16`. La variable CSS es `--e-global-color-{id}` (`:66`). Los arrays son `system_colors`/`custom_colors` y `system_typography`/`custom_typography` (`:106,128`; `global-typography.php:165,187`).
- Elemento v4: `elType:"e-heading"` con props `{"$$type":"string","value":…}` y `styles{id:{variants:[{meta:{breakpoint,state},props}]}}` (`atomic-widgets/parsers/style-parser.php:57-87`). Interacciones: `interactions:{items:[{interaction_id,trigger,animation,breakpoints}],version:1}`.

**Theme Builder:**
- `_elementor_conditions` es un arreglo de cadenas `tipo/nombre/subnombre/subid`. Ejemplos: `"include/general"`, `"include/singular/page/42"`, `"exclude/singular/in_category/5"`, `"include/archive/product_cat"`. Se analiza en `conditions-manager.php:507-511`.
- `_elementor_location` contiene header, footer, single, archive, popup o una ubicación personalizada (`documents/theme-document.php:22`).
- La caché es la opción `elementor_pro_theme_builder_conditions` = `{ubicación:{idPlantilla:[cadenas]}}` (`classes/conditions-cache.php:15,29-36`). **Sólo se regenera con `save_conditions()`** (`conditions-manager.php:300-326`).

**Popup:**
- Meta `_elementor_popup_display_settings` = `{triggers:{…}, timing:{…}}` (`document.php:22`).
- Claves con el formato `{grupo}_{campo}` (`display-settings/base.php:59-61`). Ejemplos: `page_load_delay`, `scrolling_direction`, `scrolling_to_selector`, `click_times`, `inactivity_time`, `times_times`, `times_period`, `url_url`, `logged_in_roles`, `devices_devices` y `schedule_start_date`.
- Formato de exportación: `display_settings` (`document.php:137-155`).
- Enlace de acción: `#elementor-action%3Aaction%3Dpopup%3Aopen%26settings%3D<base64({"id":"123","toggle":false})>`.

**Loop:**
- Plantilla `loop-item` con `_elementor_source`.
- El widget guarda `_skin` (post, post_taxonomy, product…), `template_id` y la consulta con el prefijo `{skin}_query_*`, por ejemplo `post_query_post_type` y `post_query_query_id` (`loop-builder/widgets/base.php:33-35`).

**Kit ZIP 3.0:**
- `manifest.json` con name, title, description, author, version 3.0, elementor_version, created, thumbnail, site, más `templates{id:{doc_type,title,…}}`, `content{post_type:{id:{…,terms,show_on_front}}}`, `wp-content`, `site-settings`, `plugins` y `taxonomies` (`processes/export.php:305-322`).
- Otros archivos: `templates/{id}.json`, `content/{cpt}/{id}.json`, `wp-content/{cpt}/{cpt}.xml` y `site-settings.json`.
- Las condiciones de plantilla viajan en `import_settings.conditions` (`theme-builder/documents/theme-document.php:250-283`).
- Plantilla JSON suelta: `{content:[…], page_settings:{…}, version, title, type}`, con `global_classes` y `global_variables` opcionales (`sources/local.php:1578-1600`, `base.php:568-578`).

**Otros:**
- `_elementor_page_assets` = `{styles:[handles], scripts:[handles]}`.
- `_elementor_element_cache` es JSON con caducidad.

## 4. Licencia y qué se puede reutilizar

- El núcleo es GPL-3.0-or-later de Elementor Ltd. Se distribuye sin cambios y **no se edita**: sólo se sincroniza con `scripts/sync-elementor-core.sh` (`docs/third-party-licenses.md`, `AGENTS.md`).
- Lo derivado de PRO Elements es GPLv3 y **sí es editable** (`core/`, `modules/`, `plugin.php`, `license/`). Hay que conservar `COPYING`, `license.txt` y `DIGITALISIMO_CHANGES.md`. Eso permite tocar `modules/interactions/module.php`, `license/admin.php`, `modules/loop-filter/*` y `modules/theme-builder/*`.
- Los JS compilados de PRO (`assets/js/*.min.js`, `assets/js/packages/*`) no traen fuentes, porque `assets/dev` no existe. Se pueden reemplazar por scripts nuevos, pero no conviene parchearlos.
- Divi 5.15.0: la cabecera de `Divi/style.css` dice GPLv2 y `Divi/LICENSE.md`, GPLv2 o posterior (discrepancia registrada en `docs/third-party-licenses.md`). Sirve para estudiar formatos y tablas de datos con atribución; sus bundles compilados no se usan.

## 5. Riesgos

1. **Interacciones PRO**: `get_license_key()` devuelve `""` (`license/admin.php:33-35`). Cualquier conversión de hover o click a interacciones v4 no se ejecutará.
2. **v4 apagado**: los sitios instalados antes de 4.0 no tienen `e_atomic_elements`. Convertir a elementos `e-*` o activar el opt-in cambia el editor de todo el sitio. El contenido convertido debe ir a widgets clásicos con container.
3. **Caché de condiciones**: escribir `_elementor_conditions` con `update_post_meta` no actualiza la opción de caché. La plantilla no se aplica hasta un `regenerate()`.
4. **Empates en el Theme Builder**: a igual prioridad gana el orden de la caché. Una conversión desde Divi o Bricks con reglas iguales puede cambiar qué cabecera se ve.
5. **Kits y tema**: el runner de site-settings instala desde wordpress.org y cambia el tema (`runners/import/site-settings.php:272-340`). Un kit que nombra hello-elementor lo instala. El tema propio, que no está en wordpress.org, fallaría.
6. **Importador cerrado**: los adaptadores están fijos (`processes/import.php:663`) y el ZIP sólo admite json y xml (`:618`). Divi sin `content` falla en `sources/base.php:471-475`. Los elementos de Bricks sin `elType` se descartan en `process_export_import_content` antes de los filtros.
7. **Medios**: se descargan por URL. Si el sitio de origen está caído, no hay imágenes.
8. **Caché de página**: una condición nueva que no se declare en `Visitor_Content` (cookie, visitas) deja la página en caché con el resultado del primer visitante.
9. **Coste del front**: cada widget PRO convertido arrastra Swiper (144 KB), Lottie, smartmenus y los paquetes globales.

## 6. Recomendaciones de integración

1. **Una sola librería de conversión en Elements.** Ubicarla en `digitalisimo-elements/modules/digitalisimo-import/`, con un conversor por formato: árbol de origen → `_elementor_data` clásico (container y widgets nativos o propios), `e_display_conditions`, `_elementor_conditions` y globales del kit. No dibuja nada en el sitio: sólo genera datos con formato nativo. Así cumple la regla de no dejar renderizadores sólo en Migración. Migración la llama por nombre de clase.
2. **Plantillas sueltas.** Registrar un `Source` propio con `Plugin::$instance->templates_manager->register_source()` (`manager.php:113`) y su `import_template()`. Detecta el formato, convierte y llama a `Source_Local::save_item()`. Las rutas existentes ya respetan `$data['source']` (`manager.php:625`). No sirve depender de `import/process_content`, porque llega tarde para Divi.
3. **Kits ajenos.** Preconvertir a un ZIP de kit 3.0 estándar en un directorio temporal y llamar a `Module::import_kit()` (`module.php:384-400`). Así se reutilizan todos los runners, la reversión, las condiciones (`import_settings.conditions`), el WXR y la reasignación de IDs (`collection-loop/import/loop-query-import-remap.php`, `digitalisimo-tools/modules/elementor-network-templates/class-dependency-resolver.php:15`). En `site-settings` hay que omitir `theme`. No hace falta otro sistema de kits.
4. **Migración de sitios completos.** Añadir una etapa antes de `convert` en `digitalisimo-migration/legacy/class-runner.php:45` y ampliar `Migration::documents()`, que hoy sólo busca marcadores de Element Pack (`legacy/class-migration.php:307`), con los marcadores de cada constructor. Conviene reutilizar el respaldo y reversión de `convert_document()` (`:428-450`). Las claves de origen las define cada frente: Divi `_et_pb_use_builder`/`et_template`, Bricks `_bricks_page_content_2`/`bricks_template`, Betheme `mfn-page-items`, GP `gp_elements`/`_generate_element_*` (a confirmar).
5. **Condiciones.** Se convierten automáticamente a las condiciones nativas y propias. Las que faltan (cookie, visitó la página, número de visitas) van como clases nuevas en `modules/digitalisimo-conditions/class-conditions.php` y se registran en `condition_classes()`. **No** deben añadirse a `CACHEABLE_CONDITIONS`. Las condiciones de plantilla por usuario o rol (GP Elements, Divi TB) se registran con `elementor/theme/register_conditions` (`conditions-manager.php:297`). Las de usuario y rol no rompen la caché de página; las de fecha y dispositivo no deben llevarse a plantillas.
6. **Hooks de GP Elements y áreas de Divi.** Se cubren con ubicaciones personalizadas del tema propio (`register_location`, `public=>true`) y documentos «section». El código pasa a `elementor_snippet` y las ubicaciones head/body a custom-code. El tema propio debe llamar a `register_all_core_location()` y a `elementor_theme_do_location()`, como hace Hello.
7. **Interacciones.**
   - Primero: habilitar lo PRO quitando sólo la salida de `modules/interactions/module.php:23-25`. No hace falta tocar la licencia (`should_allow_all_features()` ya devuelve verdadero) y **no** se toca `get_license_key()`, que viaja a la Kit Library remota. Antes se inventarían las páginas con `hover`, `click` o `scrollOn` guardados, que empezarían a animarse. Sólo sirve en sitios v4.
   - Para el contenido clásico, lo automático se mapea a lo nativo: entrada (`_animation`), motion_fx, sticky, disparadores de popup (`click_*`, `scrolling_to_selector`) y hashes `popup:*`/`off_canvas:*`.
   - Las acciones sobre un selector (alternar clase o atributo, mostrar u ocultar) no tienen equivalente. **Decidido en el plan:** un registro propio de acciones con destino en la clave `_digitalisimo_actions`, con un cargador ligero y diferido que sirve a elementos clásicos y atómicos, absorbe los hashes `elementor-action:`, usa lista blanca (sin JS arbitrario), listeners idempotentes y manejo de foco (F7b). En v4 la propiedad propia se reinyecta después del validador del núcleo, que descarta todo ítem sin animación.
8. **Filtros.** Ampliar `modules/loop-filter`, que es código PRO editable: añadir tipos en `register_widget_filter()` y `filter_loop_query()` (`module.php:78-131`), con widgets nuevos (búsqueda, meta/rango, orden) que usen el mismo endpoint `refresh-loop` y el patrón `e-filter-*`. Sumar un script propio con `defer` para `popstate`. La conversión de filtros de Bricks o Divi sólo es automática para taxonomía.
9. **Consultas globales y contexto padre.** No convertir automáticamente. Una consulta global se aplana copiando sus argumentos en cada Loop Grid, con su `query_id` como gancho opcional. El contexto padre no tiene equivalente y debe quedar en el informe de Migración.
10. **Tras cada conversión**: llamar a `Conditions_Manager::save_conditions()`, borrar `_elementor_css` y `_elementor_page_assets` (o `files_manager->clear_cache()`), y aprobar `tests/elements-front-budget.php` y `tests/playground/conditions.sh`.

**Qué se convierte solo y qué no:**
- **Automático**: estructura a container, widgets con equivalente nativo o propio, globales del kit, condiciones de plantilla include/exclude, condiciones de elemento con equivalente, popups (disparadores y tiempo), animaciones de entrada y movimiento, código head/body, menús y WooCommerce por plantilla.
- **No automático (al informe)**: consultas globales y contextos padre, interacciones hacia destinos ajenos, filtros que no son de taxonomía, lógica condicional de formularios, módulos sin equivalente y CSS escrito contra clases del origen.

---

## Decisiones posteriores (plan aprobado)

- **Importador sin otro sistema de kits.** Una sola acción «Importar» que detecta el formato. Plantillas sueltas: un punto de entrada propio llama a `import_template(['source'=>'digitalisimo-import'])` y ese `Source` delega en `get_source('local')->save_item()` (no extiende `Source_Local`). Kits ajenos y paquetes locales: kit 3.0 sin `theme`, `experiments` ni `plugins`, en modo «sólo agregar». Ejecución en AJAX autenticado o CLI; si se instancia `Import` directamente, se dispara a mano la acción que registra los runners de PRO.
- **Condiciones (F7a):** cookie, visita de página, campo, autor, stock y búsqueda, cada una clasificada como cacheable o variable; `THEME_LOCATIONS` ampliable por filtro; prioridad manual en el Theme Builder.
- **Consultas y filtros (F7b):** consultas guardadas por ID con «desvincular», contextos actual/padre/raíz con topes, y `loop-filter` ampliado en handlers propios con `defer` y `popstate` (los bundles compilados no se tocan).
- **Recursos:** dependencias por elemento también para el contenido que llega por AJAX, popups, quick view y «cargar más»; inicialización idempotente.
- **Front:** la Fase 2 de `ELEMENTS_FRONT_OBJECTIVE.md` (handlers PRO por elemento) va como incremento propio (F-front) antes de F7a.
