# Informe técnico de solo lectura: Betheme 29.0.3 (`reference/betheme.zip`) frente a DIGITALÍSIMO Elements y Migración

> Revisión del 16 de octubre de 2026, depurada para el repositorio público. Betheme es **propietario**: aquí no hay código suyo, sólo nombres de archivos, metas, opciones y claves, y los formatos descritos con palabras propias (clean-room). La copia de `reference/` sirve sólo para leer formatos: no se instala, no se ejecuta y no es fuente de demos ni de servicios. Donde el plan aprobado decidió otra cosa, manda `docs/FUSION_OBJECTIVE.md`; las diferencias están al final.

---

## 1. Qué hay, versión y formato

- **Tema:** Betheme 29.0.3 (`style.css`: `Version: 29.0.3`, `License: Betheme License`, `License URI: https://muffingroup.com/betheme/licensing/`).
  - `MFN_THEME_VERSION` está en `functions.php:11`.
  - 3 568 entradas en el ZIP.
  - `readme.txt` sólo remite a una carpeta de documentación que no viene incluida.
- **Constructores que conviven en el tema:**
  - **BeBuilder Live (visual):**
    - `visual-builder/visual-builder.php`.
    - `visual-builder/classes/visual-builder-class.php`.
    - `visual-builder/assets/js/scripts.js`.
    - La acción `post.php?action=mfn-live-builder` la registra `mfn_init_bebuilder()` en `visual-builder.php`.
  - **BeBuilder Blocks / clásico (metabox):** `functions/builder/class-mfn-builder-admin.php` (`save()` en `:2865`).
  - **Ambos guardan el mismo meta:** `mfn-page-items`.
  - **Front común:** `functions/builder/class-mfn-builder-front.php`.
  - **Despacho de items:** `class-mfn-builder-items.php` → `item_{type}()` → `sc_{type}()` en `functions/theme-shortcodes.php` (167 funciones `sc_*`).
- **Integración con Elementor:** `functions/plugins/elementor/`. Registra 57 widgets `mfn_*` (`mfn_accordion` … `mfn_zoom_box`). Su `render()` llama a los `sc_*` del tema, por ejemplo `class-mfn-elementor-widget-accordion.php:186` → `echo sc_accordion( $settings )`.
- **Opciones del tema:**
  - Panel propio en `muffin-options/`.
  - Opción única `betheme` (`muffin-options/options.php:38`, `'opt_name' = 'betheme'`).
  - Lectura con `mfn_opts_get()` (`muffin-options/theme-options.php:12050`).
- **CPT propios:** `template`, `portfolio` (+`portfolio-types`), `offer` (+`offer-types`), `slide` (+`slide-types`), `testimonial` (+`testimonial-types`), `client` (+`client-types`), `layout`, `icons`. Están en `functions/post-types/class-mfn-post-type-*.php`.
- **Importador de demos («Pre-built websites»):**
  - Archivos: `functions/importer/*` y `functions/admin/setup/class-mfn-setup.php`.
  - Manifiesto: `functions/importer/demos.php`, con unas 696 entradas desde `:441`.
- **Secciones prefabricadas e importación de página única:** `functions/builder/pre-built/*`. Contenido remoto bajo licencia.

---

## 2. Capacidades: completas, parciales y faltantes (comparadas con Elements)

| Capacidad Betheme | Ruta Betheme | Equivalente en Elements | Estado |
|---|---|---|---|
| Sección → wrap → item, tamaños por fracción por dispositivo | `class-mfn-builder-front.php:45-109` (mapas de clases), `:1884` (`show_wraps`), `:3271` (`show_items`) | Container flex anidado (núcleo `elementor/`) | Completa (por conversión) |
| Wrap en modo Grid (`grid`, `css_grid_columns`, gaps) | `class-mfn-builder-fields.php:4588+` | Container tipo grid (núcleo) | Completa |
| Wraps anidados (`item_is_wrap`) | `visual-builder-class.php:1034-1060`, front `:2496` | Containers anidados | Completa |
| Sección «Query loop» (posts/terms, grid/masonry/slider, paginación) | Campos `query_*` de sección y wrap; `Mfn_Helper::preparePostUpdate` (`class-mfn-helper.php:41`) | `modules/loop-builder` (loop-grid / loop-carousel) + Loop Item template | Parcial: terms-loop y masonry, a revisar |
| Sección «Tabber» | front `:640-680`, `css/elements/tabber.css` | Nested Tabs (núcleo) | Completa |
| Secciones y wraps globales (`mfn_global_section_id`, `global_wraps_select`) | front `:474-520`, `:1950` | Plantilla de sección (`modules/library`) + widget Template | Completa |
| Clases globales `be_classes` | `visual-builder.php:552` (`mfnvb_saveclasses`), merge en front `:447-455` | Clases globales de Elementor 4 (sólo atómicos) | Parcial: para widgets clásicos hay que incrustar estilos |
| Variables CSS `be_css_variables` | `visual-builder.php:789`, salida en `theme-head.php:769` | `modules/variables` (`_elementor_global_variables`) | Parcial: depende del tipo de valor |
| Lógica condicional por elemento | `visual-builder/classes/conditional-logic.php` | `modules/digitalisimo-conditions/class-visibility-rules.php` | Casi completa |
| Datos dinámicos `{title}`, `{featured_image}`, `{postmeta:x}`, `{termmeta:x}`, `{acf:x}`, `{price}`, `{user:…}` | `functions/modules/class-mfn-dynamic-data.php:133-478` | `modules/dynamic-tags` | Parcial: Betheme permite tags embebidos dentro de texto |
| Efectos GSAP de scroll y mouse (`gsap_*_scroll`) | `visual-builder/classes/helpers/motion/*.php` | `modules/motion-fx`, `modules/interactions` | Parcial |
| Animaciones de entrada (`animate`: `fadeIn…`, `zoomIn…`, `bounceIn…`, `*Large`) | `class-mfn-builder-fields.php:77181` | `_animation` del núcleo | Completa, salvo las variantes `Large` |
| Header builder: plantilla `header` con secciones `ver` = default / `header-sticky` / `header-mobile` | `includes/header-template.php`; campos en `class-mfn-post-type-template.php:774` | Theme Builder header + `modules/sticky` + visibilidad por dispositivo | Parcial: el «header distinto al fijarse» no tiene equivalente 1:1 |
| Footer, megamenú, popup y sidemenu como plantillas | `class-mfn-post-type-template.php:459-500, 945, 1535, 1970, 2105` | Theme Builder footer, `modules/mega-menu`, `modules/popup`, `modules/off-canvas` | Completa o parcial (ver sección 6) |
| Condiciones de plantilla (include/exclude, everywhere/archives/singular/other) | `class-mfn-builder-admin.php:3443, 3791, 3964` | `_elementor_conditions` (`modules/theme-builder/classes/conditions-manager.php`) | Completa |
| Headers clásicos por opciones (`header-style`: classic, modern, plain, stack, creative, fixed, transparent, simple, below, split, overlay, shop…) | `theme-options.php:2469-2490`, `includes/header-*.php` | Ninguno directo | Faltante: hay que generar una plantilla header aproximada |
| Footer clásico por widgets (`footer-layout`, `footer-area-N`) | `theme-options.php:6136+`, `functions/theme-sidebars.php` | Footer de Theme Builder + widget Sidebar | Parcial |
| Subheader / barra de título | opciones `subheader`, metas `mfn-post-hide-title`, `mfn-post-subheader-image` | Contenedor en plantilla Single Page | Parcial |
| WooCommerce: plantillas single-product, archive-product, cart, checkout, thanks | items `product_*`, `shop_*`, `cart_*`, `checkout`, `thankyou_*` | `modules/woocommerce/widgets/*` + documentos `product` y `product-archive` | Casi completa |
| Carrito lateral con AJAX de cantidad, eliminación y cupones | `functions/theme-woocommerce.php:868-1195`, `includes/woocommerce-cart.php` | `woocommerce-menu-cart` (mini carrito off-canvas) | Parcial: falta el cupón y la barra de envío gratis |
| Quick view | `theme-woocommerce.php:1196-1210`, `includes/quickview.php` | — | Faltante |
| Wishlist (cookie `mfn_wishlist`, página `shop-wishlist-page`) | `theme-woocommerce.php:491-530, 1368-1400`, `includes/wishlist.php:15` | — | Faltante |
| Swatches de variaciones (`attribute_type` color/image/label + term meta `mfn_attr_field`) | `theme-woocommerce.php:530-866` | — | Faltante |
| Barra de envío gratis | `theme-woocommerce.php:1766-1836`, bloque `extensions/blocks/free-delivery-progress-bar` | — | Faltante |
| Notificación de «venta falsa» | `theme-woocommerce.php:1837+` | — | Faltante (no se recomienda reponerla) |
| Filtros de atributos por GET (`?color=red,blue` → `tax_query pa_*`) e items `filters` / `active_filters` | `theme-woocommerce.php:1212-1258` | `modules/loop-filter` (taxonomy-filter) | Parcial: faltan precio y atributos |
| Contenido superior e inferior por categoría de producto | `theme-woocommerce.php:1515-1625` | Plantilla de archivo + dynamic tag de term meta | Parcial |
| reCAPTCHA en formularios de WooCommerce | `theme-woocommerce.php:1923+` | — | Faltante |
| SEO propio (`mfn-meta-seo-*`) y analítica en opciones | `class-mfn-post-type-page.php`, `theme-head.php:101` | DIGITALÍSIMO SEO | Importable |
| Revisiones propias del builder | `class-mfn-builder-ajax.php:1397` | Historial de Elementor | No se migran |
| IA del builder, presets y favoritos | metas y opciones `mfn-ai-chat`, `mfn-presets`, `mfn_fav_sections_{user}` | — | Se descartan |

**Dependencias externas:**

- RevSlider y LayerSlider (item `slider_plugin`, meta `mfn-post-slider`).
- Contact Form 7 (item `cf7`).
- WPML y Polylang (sufijos de idioma en opciones y metas).
- Los plugins sugeridos por TGMPA son CF7, Duplicate Post, Elementor, HubSpot y WooCommerce (`functions/admin/tgm/class-mfn-tgmpa.php`).
- Las funciones Woo del tema no dependen de YITH ni de otras extensiones: wishlist, quick view, swatches, carrito lateral y envío gratis son propios.

---

## 3. Formatos de datos exactos

### 3.1 Contenido del builder: meta `mfn-page-items`

**Codificación**, según la opción `builder-storage` (`theme-options.php:2415-2422`):

- `''` (valor por defecto): el árbol se guarda como array en la meta, y WordPress lo serializa.
- `'non-utf-8'`: igual al anterior, pero sin normalizar saltos de línea.
- `'encode'`: el árbol se serializa con PHP y el resultado se codifica en base64 (`class-mfn-builder-admin.php:3309-3315`; `visual-builder.php`, acción de guardado del builder visual).
- **Lectura:** si el valor no llega como array, el tema lo decodifica de base64 y lo deserializa sin admitir objetos (`visual-builder-class.php:962`). El importador prueba primero la deserialización directa y luego la variante base64 (`class-mfn-importer-helper.php:1535-1540`). Nuestro lector hace lo mismo con `unserialize( …, array( 'allowed_classes' => false ) )` y límites de tamaño.

**Metas asociadas por post:**

| Meta | Contenido |
|---|---|
| `mfn-page-items-seo` | Texto o HTML plano extraído para Yoast o Rank Math |
| `mfn-page-object` | JSON plano de todos los elementos. Se guarda (`visual-builder.php`, `updatevbview`) pero ya no se lee; el importador lo excluye |
| `mfn-page-local-style` | JSON con `{custom, dynamic_styles, desktop, laptop, tablet, mobile, fonts, sidemenus}`, tipo selector→reglas |
| `mfn-page-fonts` | JSON con la lista de fuentes |
| `mfn-builder-preview`, `mfn-builder-preview-local-style`, `mfn-builder-preview-fonts` | Datos de la vista previa |
| `mfn-builder-revision-{autosave\|update\|revision\|backup}` | Array `timestamp => base64(serialize)`, máximo 10 (`class-mfn-builder-ajax.php:1397-1430`) |
| `mfn-page-items-backup` | Copia previa a la conversión de formato (`local-css-compability.php:28`) |
| `mfn-template-sidemenu`, `mfn-query-modifiers`, `mfn-tmpl-has-main-query`, `mfn_dynamic_styles`, `mfn-cart-template-data`, `mfn_template_alert`, `mfn_template_perpage`, `mfn_cart_button`, `mfn_template_product_image_zoom`, `mfn-shop-list-*` | Derivados que escriben `class-mfn-helper.php:211-486` y `class-mfn-builder-admin.php:3187-3197` |

**CSS generado:** `uploads/betheme/css/post-{ID}.css`, `post-{ID}-preview.css`, `be_classes.css`, `static.css` (`class-mfn-helper.php:584-706`, `theme-head.php:625-630`). Las fuentes locales van a `uploads/betheme/fonts/` (`class-mfn-builder-ajax.php:274+`).

### 3.2 Estructura y generaciones del formato

**Generación 1 («Muffin Builder 1 / ACM»):** la sección trae `items` directamente, sin `wraps`. Al leerla se normaliza a un único wrap `1/1` (`visual-builder-class.php:983-1008`).

**Generación 2 (Muffin Builder 2/3, Betheme < 27):** los items guardan su configuración en `fields` en vez de `attr`. Los estilos son claves planas con la forma `style:<selector>:<propiedad>`, con sufijo `_laptop`, `_tablet` o `_mobile` para cada dispositivo. El selector usa las clases de sección, wrap e item del builder y el marcador `mfnuidelement`. El valor es un escalar (por ejemplo una alineación) o un array con `top`, `right`, `bottom` y `left` para márgenes y rellenos. El tema trae contenido de muestra en este formato (`functions/admin/setup/assets/content.txt`).

En los wraps antiguos el selector puede llevar `>`, que el tema reescribe a `/` (`visual-builder-class.php:1016-1024`).

**Generación 3 (BeBuilder ≥ 27/28, con la opción `mfn-css-db-update = '1'`):** el árbol es una lista de secciones (hay un ejemplo en `muffin-options/options.php:1236`). Cada nodo lleva `uid`, `jsclass` (`section`, `wrap` o el tipo del item), `title`, `icon` y `attr`:

- Un campo de estilo de `attr` es un objeto con tres claves: `val` (escalar, o un objeto por dispositivo `desktop`/`laptop`/`tablet`/`mobile` cuyos valores pueden ser a su vez objetos de cuatro lados), `selector` (con el marcador `mfnuidelement`) y `style` (la propiedad CSS).
- Un campo de contenido es un escalar o un objeto por dispositivo (por ejemplo la posición de la navegación de un tabber).
- La sección lleva además `ver` (`default` en contenido; variantes en cabeceras) y metadatos del editor (`vb_postid`, `rwd`).
- Los wraps llevan `size` y `laptop_size`/`tablet_size`/`mobile_size` como fracciones (`1/2`, `1/1`) y su lista `items`.
- Un valor puede contener datos dinámicos entre llaves (por ejemplo la imagen destacada del sitio).

**Claves por nivel:**

- **Sección:**
  - Estructura: `uid`, `attr`, `wraps`, `mfn_global_section_id`, `ver` (en headers: `default` | `header-sticky` | `header-mobile`), `be_classes[]`, `jsclass`, `title`, `icon`.
  - IDs de `attr` (lista completa en `set_section`, `class-mfn-builder-fields.php:182-4588`):
    - Tipo y loop: `type` (`''` / `query` / `tabber`), `query_*`, `tabber_*`.
    - Separadores: `shape_divider_type_top|bottom` y sus `css_shape_divider_*`.
    - Disposición y fondo: `style` (presets `no-margin-h`, `no-margin-v`, `dark`, `full-width`, `full-width-ex-mobile`, `highlight-left|right`, `full-screen`, `equal-height`, `equal-height-wrap`; ver `theme-options.php:637`), `width_switcher` (`''` / `full` / `custom`) + `css_advanced_max_width`, `height_switcher` (`''` / `full-screen` / `custom`), `bg_video_mp4`, `navigation`.
    - Visibilidad y extras: `hide`, `collapse`, `visibility`, `closeable`, `scroll-visibility`, `conditional_logic`, `classes`, `custom_id`, `custom_css`, `css_advanced_*`, `gsap_*`.
    - Obsoletos: `bg_color`, `bg_image`, `bg_position`, `bg_size`, `padding_top|bottom|horizontal`, `class`, `section_id`.
- **Wrap:**
  - Estructura: `uid`, `size`, `laptop_size`, `tablet_size`, `mobile_size`, `attr`, `items`, `be_classes`, `item_is_wrap` (anidado), `tablet_resized`.
  - IDs de `attr` (`set_wrap`, `:4588-8043`): `grid`, `grid_columns_switcher`, `css_grid_columns[_custom]`, `css_grid_columns_gap`, `css_grid_rows_gap`, `sticky` + `sticky_offset` (por dispositivo en v3; en v2 `sticky_laptop|tablet|mobile`), `type=query` + `query_*`, `move_up`, `padding`, `column_margin`, `vertical_align`, `reverse_order` (1 = column-reverse, 2 = column-reverse-rows), `bg_*`, `css_advanced_*`, `visibility`, `css_advanced_hide_under` / `show_under`, `conditional_logic`, `animate`, `global_wraps_select`.
- **Item:**
  - Estructura: `type`, `uid`, `size`, `laptop_size`, `tablet_size`, `mobile_size`, `used_fonts`, `attr` (en v2: `fields`), `be_classes`, `jsclass`, `title`, `icon`.
  - En v3 los campos de estilo son `css_<id>` = `{selector, style, val}`, donde `val` es un escalar o `{desktop, laptop, tablet, mobile}` y cada valor puede ser escalar u objeto (`top/right/bottom/left`, tipografía `font-family`/`font-size`/…, `string` para gradientes y transformaciones).
  - Los repetidores van en `attr.tabs = [ {title, content, …}, … ]` (`class-mfn-builder-admin.php:3110-3140`).
  - Campos comunes avanzados (`set_advanced`, `:77217`): `width_switcher` (`default` / `inline` / `custom`) + `css_advanced_flex`, `height_switcher`, `css_advanced_padding|margin|position|top|…|z_index|order|transition`, `background_switcher` + `css_advanced_background_*` y `gradient` (normal y hover), overlay, `border_*`, `box_shadow`, `backdrop_filter`, `visibility`, `conditional_logic`, `classes`, `custom_id`, `custom_css`, `gsap_*`, `animate`.

**Selectores con marcador:** `mfnuidelement`, que `Mfn_Helper::mfnLocalStyle()` sustituye por el `uid` (`class-mfn-helper.php:501-580`). También aplica prefijos y sufijos: `background-image` → `url(…)`, `flex` → `0 0 …`, `transform-string`.

**Breakpoints del CSS local** (`class-mfn-helper.php:640-665`): laptop `max-width:1440px`, tablet `max-width:959px`, móvil `max-width:767px`. Las reglas `_custom` producen `@media(max-width:X)` de `hide-under` / `show-under`.

**Medianil por defecto** (`css/be.css:14-17, 919`): 0 arriba, 40 px abajo y 12 px a cada lado, en las variables `--mfn-column-gap-*`, aplicado como margen del contenedor interior de cada columna (`.mcb-column-inner`). `.section_wrapper` tiene `max-width` = `grid-width` (por defecto 1240).

**Conversores internos de Betheme entre versiones:**

- `MfnVisualBuilder::loadExistedElements()` / `normalize*()` (`visual-builder-class.php:924-1100`): pasa de v1 a v2/3, de `fields` a `attr`, cambia `>` por `/`, genera los `uid` y vuelve a guardar.
- `MfnLocalCssCompability` (`visual-builder/classes/helpers/local-css-compability.php`): convierte `old_id` (`style:…`) en `css_*` con `{selector, style, val}`, agrupa sufijos `_device` y regenera el CSS. Se activa por lotes con `Mfn_Helper::bebuilder_data_updater()` (`class-mfn-helper.php:729`), usando la opción `mfn-css-db-update` (`pending` → `1`).
- Cada campo nuevo declara en `class-mfn-builder-fields.php` su `old_id`: el nombre de la clave `style:…` de la generación 2 que sustituye. Esa equivalencia `old_id → id` es lo que necesita nuestro normalizador; se reconstruye como dato propio desde las muestras y la especificación de este informe, nunca copiando la tabla del tema.

**Exportación del builder:** el árbol `mfn_items` codificado en JSON (`class-mfn-builder-ajax.php`, método `_export`). La importación espera ese JSON y regenera los `uid` (`:1192-1225`).

### 3.3 Inventario de items

Lista canónica de métodos `item_*` en `class-mfn-builder-items.php`, agrupados por la categoría `cat` del editor:

- **typography:** `heading`, `column` (texto HTML), `content` (contenido WP), `visual`, `image`, `image_gallery`, `button`, `blockquote`, `readmore`.
- **boxes:** `article_box`, `banner_box`, `before_after`, `chart`, `countdown`, `countdown_2`, `counter`, `feature_box`, `flat_box`, `hover_box`, `icon_2`, `icon_box`, `photo_box`, `progress_bars`, `promo_box`, `quick_fact`, `sliding_box`, `spacer`, `story_box`, `trailer_box`, `zoom_box`, `filters`, `active_filters`.
- **blocks:** `accordion`, `faq`, `helper`, `list`, `list_2`, `pricing_item`, `slider`, `tabs`, `tag_cloud`, `toggle`.
- **elements:** `call_to_action`, `contact_box`, `fancy_divider`, `fancy_heading`, `feature_list`, `hover_color`, `how_it_works`, `html`, `info_box`, `lottie`, `map`, `map_basic`, `our_team`, `our_team_list`, `opening_hours`, `timeline`, `video`, `megamenu_menu`.
- **loops:** `blog`, `blog_news`, `blog_slider`, `blog_teaser`, `clients`, `clients_slider`, `media_carousel`, `offer`, `offer_thumb`, `portfolio`, `portfolio_grid`, `portfolio_photo`, `portfolio_slider`, `shop`, `shop_slider`, `testimonials`, `testimonials_list`.
- **Otros:** `code`, `divider`, `divider_2`, `hotspot`, `marquee_text`, `livesearch`, `breadcrumbs`, `share`, `table_of_contents`, `plain_text`, `payment_methods`, `placeholder`, `sidebar_widget`, `cf7`, `slider_plugin`, `header_currency_switcher`, `header_language_switcher`, `progress_icons`.
- **Header y footer:** `header_logo`, `header_menu`, `header_icon` (cart / wishlist / account / search), `header_burger`, `header_search`, `header_promo_bar`, `footer_logo`, `footer_menu`, `sidemenu_menu`, `popup_exit`.
- **Single y archive:** `post_content`, `post_comments` y variantes de `heading` / `image` / `icon_box_2` / `tag_cloud` / `blog` / `portfolio` con plantillas `post_*` y `archive_*`.
- **WooCommerce:** `shop_products`, `shop_categories`, `shop_title`, `product_title|images|brands|price|cart_button|reviews|rating|stock|meta|breadcrumbs|short_description|additional_information|upsells|related|content|tabs`, `cart_table`, `cart_totals`, `cart_cross_sells`, `checkout`, `thankyou_order`, `thankyou_overview`, `order_steps`, `woo_alert`, `search_results`.

**Campos de contenido de items frecuentes** (sin contar los `css_*`):

| Item | Campos |
|---|---|
| `image` | `src`, `size`, `stretch`, `lazy_load`, `link_type`, `popup_id`, `link_image`, `link`, `rel`, `target`, `link_title`, `hover`, `alt`, `caption`, `mask_shape_type`, `mask_shape_size`, `mask_shape_position`, `align`, `width`, `height`, `greyscale` |
| `button` | `title`, `link`, `target`, `icon`, `icon_position`, `size`, `full_width`, `button_style`, `button_function` (`popup`, `copy_to_clipboard`, `read_more`, `go_to`, `open-sidebar`), `button_id`, `download`, `rel`, `onclick`, `align` |
| `heading` | `title`, `header_tag`, `link_type`, `link`, `target` + `css_txt_align`, `css_color`, `css_typography`, `css_text_shadow`, `css_stroke_*`, `css_bg_img` |
| `accordion` | `title`, `tabs[]`, `icon`, `icon_active`, `open1st`, `openAll`, `style` |
| `tabs` | `title`, `tabs[]`, `type` |
| `video` | `video`, `parameters`, `mp4`, `ogv`, `placeholder`, `object_fit` |
| `counter` | `icon`, `image`, `prefix`, `number`, `label`, `duration`, `thousands_separator`, `type` |

### 3.4 CPT `template`, tipos y condiciones

- **Meta de tipo:** `mfn_template_type` (`class-mfn-post-type-template.php:459-500`).
  - Valores: `default`, `section`, `wrap`, `header`, `footer`, `megamenu`, `sidemenu`, `popup`, `single-post`, `blog` / `archive-post`, `portfolio` / `archive-portfolio`, `single-portfolio`, `search`, `single-{cpt}`, `archive-{cpt}`.
  - Con WooCommerce además: `shop-archive` / `archive-product`, `single-product`, `cart`, `checkout`, `thanks`.
  - Los tipos `shop-archive`, `portfolio` y `blog` están obsoletos y se reescriben (`visual-builder.php`, `updatevbview`).
- **Condiciones:** meta `mfn_template_conditions` en JSON, como lista de `{rule: include|exclude, var: everywhere|archives|singular|other, archives: '<pt>' | '<pt>:<term_id>' | '', singular: '<pt>' | '<pt>:<term_id>' | '', other: 'search-page'}`. Se materializan en opciones y metas desnormalizadas:
  - Header y footer: `mfn_{header|footer}{_lang}_{pt}_single`, `_{pt}_arch`, `_single_excluded`, `_arch_excluded`, `_search_page`. Por post: `mfn_{type}{_lang}_post` / `_post_excluded`. Por término: `mfn_{type}_term_excluded` (`class-mfn-builder-admin.php:3964-4170`).
  - Popups: `mfn_popup_addons_archives` / `_singular` (`:3443-3790`).
  - Single y archive: `mfn_single-post_template`, `mfn_archive-product_template` y similares (`:3791`). Se leen en `front-template-view.php:37, 137, 290, 353`.
  - Cart, checkout, thanks, search: opción `mfn_{cart|checkout|thanks|search}_template_used`.
  - Sobrescritura por post: `mfn_header_template`, `mfn_footer_template` y, en productos, `mfn_single_product_template`, `mfn_product_template`. Por término: `mfn_shop_template`.
  - Publicación: `mfn_publication_options` en JSON.
- **Opciones del header** (`class-mfn-post-type-template.php:774-945`): `header_position` (default/absolute/fixed), `body_offset_header`, `header_width`, `header_content_on_submenu` (blur/gray/overlay) + color y blur, `header_sticky` (enabled/disabled) + `header_sticky_width`, `header_mobile` + `mobile_header_position` + `mobile_body_offset_header`.
- **Opciones del popup** (`:945-1535`): `popup_position`, `popup_display` (+`_delay`, `_scroll`, `_scroll_element`), `popup_entrance_animation`, `popup_display_visibility` (+`_cookie_days`), `popup_display_referer`, `popup_hide` (+`_delay`), `popup_close_button_*`, `popup_close_on_overlay_click`, `popup_body_scroll`, `popup_width` y los `css_*`.
- **Megamenú:** metas del item de menú `mfn_menu_item_megamenu` (ID de plantilla), `mfn_menu_item_megamenu_display`, `mfn_menu_item_icon`, `mfn_menu_item_icon_img`, `menu-item-mfn-megamenu` (antiguo).
- **Resolución en el front:** `mfn_global()` (`functions.php:84-118`) y `mfn_template_part_ID()` (`functions/theme-functions.php:863`).

### 3.5 Opciones del tema y layout por página

- **Opción `betheme`:** un array (`options.php:38,77`). La exportación es un JSON con `mfn-opts-backup=1` (`options.php:~876`); la importación acepta JSON, serialize o base64+serialize (`:1128-1160`). Secciones que afectan al diseño (`theme-options.php`):
  - **general (`:945`):** `layout` (full-width / boxed), `grid-width` (1240), `section-padding[-laptop|-tablet]`, `style`.
  - **palette (`:8831`):** `color-palette-1…14`.
  - **colors-general (`:7720`):** `skin` (custom / one / …), `color-one`, `background-html`, `background-body`, `background-archives-*`, además de `colors-header`, `colors-menu`, `colors-shop`, `colors-footer`, `colors-shortcodes`, `colors-forms`.
  - **headings (`:8759`):** `color-h1…h6`.
  - **font-family (`:9381`):** `font-content`, `font-lead`, `font-menu`, `font-title`, `font-headings`, `font-headings-small`, `font-blockquote`, `font-decorative`, `font-weight`, `font-subset`.
  - **font-size (`:9548`):** `font-size-{content|big|menu|title|single-intro|h1…h6}[-tablet|-mobile]`, como `{size, line_height, weight_style, letter_spacing}`.
  - **buttons (`:1281`):** `button-*`, `button-highlighted-*`, `button-shop-*`, `button-action-*`.
  - **header (`:2511`):** `header-style`, `header-fw`, `header-height`, `sticky-header`, `sticky-header-style`.
  - **footer (`:6136`):** `footer-layout`, `footer-style` (fixed / sliding / stick / hide), `footer-padding`, `footer-options`.
  - **responsive (`:6648`):** `responsive`, `mobile-grid-width`, `mobile-site-padding`, `builder-wrap-moveup`, `responsive-boxed2fw`.
  - **advanced (`:2177`):** `display-order` (builder antes o después del editor), `builder-storage`, `builder-blocks`, `post-type-disable`, `theme-disable`.
  - **performance (`:11606`):** `static-css`, `local-styles-location` (inline/file), `css-location`.
  - **CSS y JS propios (`:11988`, `:12013`):** `custom-css`, `custom-js`.
- **CSS global:** se genera en PHP con `style.php`, `style-responsive.php`, `style-colors.php` y `style-one.php` (`theme-head.php:1378-1405`). Va inline en `mfn-dynamic`, o a `static.css` si `static-css` está activo. Las variables `be_css_variables` (JSON `[{title:'--x', value:'…'}]`) se imprimen como `body{--x:…}` (`theme-head.php:769-790`).
- **Clases globales** (`be_classes` en JSON): `{builder:[{uid, title, jsclass, type:'be_class', attr:{css_…}}], css:{…}}`. El JS que las crea está en `visual-builder/assets/js/scripts.js:6443-6470`. Opciones relacionadas: `be_classes_fonts`, `mfn-presets`.
- **Layout por página** (`class-mfn-post-type-page.php`):
  - `mfn-post-layout` (no-sidebar / left / right / both / offcanvas), `mfn-post-sidebar`, `mfn-post-sidebar2`.
  - `mfn-post-slider`, `-slider-layer`, `-slider-shortcode`, `mfn-post-subheader-image`.
  - `mfn-post-one-page`, `mfn-post-full-width` (0 / site / content), `mfn-post-hide-title`, `mfn-post-remove-padding`.
  - `mfn-post-custom-layout` (ID de un CPT `layout`, que puede sobrescribir skin, header, logos, fondo y colores), `mfn-post-menu`, `mfn-post-hide-content`.
  - `mfn-meta-seo-title|description|keywords|og-image`, `mfn-post-css`, `mfn-post-js`.
  - El portfolio añade `mfn-post-template`, `-header-bg`, `-video`, `-client`, `-link`, `-task`, `-size`, `-intro`, `-bg`, `-bg-hover`.

### 3.6 Paquete de demo (formato del importador)

- **Manifiesto** `demos.php`:
  - `$categories` (`:3`), `$layouts` (`:27`: `one`, `por`, `box`, `blo`, `dar`, `lig`, `sho`, `ele`), `$plugins` (`:38`: `cf7`, `ele`, `rev`, `woo`).
  - `$demos[slug]` con `name`, `url`, `layouts`, `categories`, `plugins`, `revslider[]`, `wrapper` (ancho), `theme_version`, `elementor{flexbox}`, `pages{page_for_posts, woocommerce_shop_page_id, page_on_front}`, `media{*_size_w|h}`.
- **Paquete local:** el importador del tema deja cada demo descomprimido en `uploads/betheme/websites/<slug>/`. Nosotros sólo leemos un paquete que el usuario ya tenga; nunca se descarga nada del proveedor.
- **Contenido del ZIP:**
  - `content.xml.gz`: WXR comprimido.
  - `options.txt`: base64+serialize con `{betheme, conditions{…}, be_classes, be_classes_fonts, be_css_variables, map_menus, attr_transient, attr_transient_on_sale}`.
  - `menu.txt`: base64+serialize de ubicación → slug de menú.
  - `widget_data.json` y los ZIP de RevSlider.
- **Pasos AJAX** (`class-mfn-setup.php:806-1062`): `mfn_setup_download` → `_content` → `_options` (+ menú y widgets si la importación es completa) → `_slider` → `_settings` (`set_pages`, `regenerate_CSS`, borrado del temporal).
- **Sustitución de URL:**
  - `get_demo_url()` (`class-mfn-importer-helper.php:1284`) devuelve la URL del demo en el dominio del proveedor, en http y https; esas URL se sustituyen por las del sitio.
  - `replace_builder()` (`:1492`) recorre recursivamente `mfn-page-items`, incluidos los `tabs`, y reemplaza en `mfn-page-local-style` por SQL.
  - `replace_elementor()` (`:1457`) hace `REPLACE` sobre `_elementor_data` con barras escapadas.
  - Las opciones del tema se reemplazan sólo cuando el valor es string (`:611-620`).
  - Multisite se trata con `replace_multisite()` (`:1436`).
- **IDs:** `custom_import()` (`:238`) intenta conservar el ID original. Si ya existe, WordPress asigna otro **sin reasignar las referencias** (`:381-382`). Sólo se reasignan los menús por slug (`map_menus`, `:760-975`), las plantillas por título (condiciones, megamenú) y las páginas por título (`set_pages`, `:1118`). Los adjuntos son opcionales.
- **Ajustes de Elementor en demos `_el`** (`:1345-1390`): `elementor_container_width` = wrapper, `elementor_viewport_lg` = `960`, `elementor_cpt_support` = post/page/product/portfolio, y se crea el kit por defecto. Los demos de Elementor **también usan plantillas BeBuilder** para header y footer (comentario en `:214`).

---

## 4. Licencia y qué se puede reutilizar

- **Tema:** «Betheme License», propietaria, no GPL (`style.css`). El ZIP no trae ningún archivo LICENSE o COPYING del tema; sólo `functions/admin/templates/parts/new-license.php`, que es la interfaz de compra. Ni el código PHP/JS/CSS ni las imágenes, SVG (`muffin-options/svg`, formas de separador, máscaras), fuentes `mfn` ni demos pueden copiarse. Sólo se interpretan los **formatos de datos**: nombres de metas, claves y estructuras. Es interoperabilidad, no copia.
- **Componentes con licencia propia identificada dentro del ZIP** (si alguna vez hicieran falta, se toman de su fuente oficial, nunca de esta copia):
  - GPL-2.0: TGM Plugin Activation (`functions/admin/tgm/class-tgm-plugin-activation.php`).
  - GPLv2+: WordPress Importer (`functions/importer/wordpress-importer/`).
  - GPLv2: wp-color-picker-alpha 2.1.4 (`muffin-options/fields/color/field_color.js`).
  - GPL-2.0: enshrined/svg-sanitize 0.19 (`functions/modules/svg-sanitizer-019/`).
  - MIT: Swiper, slick, waypoints, magnific-popup, rangy, debouncedresize, Pickr, medium-editor, list.js.
  - MIT o GPL: jQuery UI Touch Punch.
  - Font Awesome (`fonts/fontawesome/`).
  - GSAP 3.14.2 y plugins: «GreenSock Standard License», no GPL (`js/plugins/gsap/*.min.js`).
  - Ninguno hace falta: Elements ya tiene Swiper en el núcleo, motion-fx/interactions y sanitizado SVG.
- **Implicaciones:**
  - Nuestros conversores serán código propio escrito desde la especificación de formato de este informe (*clean-room*).
  - Los mapas pueden conservar **nombres de claves** de Betheme como datos de entrada.
  - Los widgets nuevos (wishlist, quick view, swatches, barra de envío) se escriben desde cero, igual que ya se documentó para Element Pack.
  - El contenido de demos y secciones prefabricadas es remoto y con licencia: no se aloja ni se redistribuye. Sólo se importa lo que el propio usuario exporte de su sitio (JSON de BeBuilder, backup JSON de opciones, WXR).

---

## 5. Riesgos

1. **La copia de referencia no se instala ni se ejecuta**, tampoco en un sitio de prueba: sólo se leen sus formatos.
2. **Las páginas se quedan en blanco al cambiar de tema.** El contenido vive en `mfn-page-items` y lo pinta el tema. Sin Betheme activo, `the_content()` sólo muestra `post_content`, que suele estar vacío o desactualizado. Por eso el cambio de tema tiene que ser **posterior** a una conversión verificada.
3. **CPT que desaparecen:** `portfolio`, `testimonial`, `client`, `offer`, `slide`, `layout`, `template` e `icons` dejan de estar registrados. Se rompen permalinks y desaparecen fuentes de datos de sliders.
4. **Shortcodes en el contenido** (`[one_second]`, `[button]`, `[divider]`, `[accordion]`… unos 100 en `theme-shortcodes.php`) quedarían como texto literal. Los widgets `Mfn_Widget_*` de los sidebars desaparecen.
5. **Sitios Betheme + Elementor:** los 57 widgets `mfn_*` no pintan nada sin el tema, porque llaman a `sc_*`. Además, esos sitios tienen header y footer en BeBuilder.
6. **Formatos mezclados:** tres generaciones, `style:` frente a `css_`, `val` escalar o por dispositivo, wraps anidados, items con `fields`, `be_classes` que se fusionan al pintar y secciones globales por ID. Si no se normaliza igual que el tema, se pierden estilos.
7. **Breakpoints y medianil distintos:**
   - Betheme: tablet ≤ 959, laptop ≤ 1440, móvil ≤ 767, con margen interno por item `0 12px 40px 12px`.
   - Elementor por defecto: tablet ≤ 1024 y laptop desactivado.
   - Sin compensarlo hay saltos visuales.
8. **IDs no reasignados** en sitios que vinieron de un demo: `popup_id`, `menu_display`, `mfn_global_section_id`, adjuntos por URL. La conversión debe validar cada ID y anotar en el informe los que no resuelvan.
9. **Condiciones desnormalizadas** en muchas opciones y metas, con sufijos WPML/Polylang. La fuente de verdad es `mfn_template_conditions` más las sobrescrituras por post y término.
10. **Deserialización:** el modo `encode` y `options.txt`/`menu.txt` exigen `unserialize(..., ['allowed_classes'=>false])` y límites de tamaño.
11. **SEO duplicado:** Betheme imprime sus propias metas (`theme-head.php:101`, `mfn_seo()`). Al migrar deben pasar a DIGITALÍSIMO SEO, nunca coexistir.
12. **WooCommerce:**
    - Betheme escribe `attribute_type` = color/image/label directamente en `wc_attribute_taxonomies` (`theme-woocommerce.php:574-600`) y guarda `mfn_attr_field` en el término. Sin un sustituto se pierden los swatches.
    - La wishlist vive en cookies, así que no hay datos migrables en el servidor.
13. **Datos dinámicos embebidos en texto** (`"Hola {title}"`) y fondos `var(--mfn-featured-image)`: Elementor sólo admite dynamic tags por campo completo.

---

## 6. Recomendaciones de integración concretas

### 6.1 Migración (un solo botón, sin pasos nuevos para el usuario)

En `digitalisimo-migration/legacy/` (propuesta original; el plan aprobado la reubica, ver «Decisiones posteriores»):

- **`class-runner.php`:** mantener `STAGES`. En `stage_prepare` detectar Betheme: `get_template() === 'betheme'` o posts con `mfn-page-items` o `_elementor_data` que contenga `"widgetType":"mfn_`. En `stage_convert` despachar por origen: los documentos de Element Pack siguen igual y los Betheme van a un conversor nuevo. Las etiquetas de `LABELS` pasan a ser genéricas («Convirtiendo el contenido del constructor anterior»).
- **Archivos nuevos** (código propio):
  - **`class-betheme-reader.php`:**
    - Decodifica según `builder-storage`.
    - Normaliza a la generación 3 con las reglas descritas en 3.2: crear un wrap `1/1`, `fields` → `attr`, `>` → `/` y `old_id` → `css_*`. La equivalencia `old_id → id` es un dato propio reconstruido desde muestras (ver 3.2).
    - Incrusta `be_classes`: los atributos de la clase se fusionan con los del elemento, que ganan (es lo que hace el front en `:447-455`).
    - Resuelve secciones y wraps globales.
  - **`class-betheme-layout.php`:**
    - Sección → Container raíz: `width_switcher` → `content_width` boxed/full + `boxed_width`; `height_switcher full-screen` → `min_height 100vh`; fondos, overlay, bordes, sombras, `shape_divider` (al más cercano de Elementor, sin copiar SVG), `bg_video_mp4` → fondo de vídeo; `visibility` / `hide` → `hide_desktop|tablet|mobile`; `conditional_logic` → reglas de `digitalisimo-conditions`.
    - Wrap → Container hijo: `width` = fracción × 100 % con `_laptop`, `_tablet` y `_mobile`; `grid` → `container_type grid`; `sticky` → `modules/sticky` con el offset de cada dispositivo; `move_up` → margen superior negativo; `vertical_align` / `reverse_order` → `align_items` / `flex_direction_{device}`.
    - Item → widget con `_element_width` personalizado según su fracción.
    - El medianil Betheme se replica como `gap` del contenedor o margen del widget, de forma determinista.
  - **`maps/mfn-<item>.php` y `maps/mfn-<item>.styles.php`:** mismo formato que `maps/bdt-*.php` (`target`, `defaults`, `rename`, `values`, `drop`, `repeaters`), más un mapa `css_<id>` → control de Elementor. Para sitios Betheme + Elementor también `maps/mfn_accordion.php` … (los 57 `widgetType mfn_*`), con el mismo conversor que Element Pack.
  - **`class-betheme-templates.php`:** CPT `template` → `elementor_library`:
    - `_elementor_template_type`: header, footer, single-post, single, archive, product, product-archive, search-results, popup, section.
    - `mfn_template_conditions` → `_elementor_conditions` (`include/general`, `include/singular/<pt>[/id]`, `include/archive/<tax>/<term>`, `exclude/...`) y reconstrucción de `elementor_pro_theme_builder_conditions`.
    - `header_sticky` / `header_mobile` + secciones `ver` → contenedores con sticky y visibilidad por dispositivo.
    - Disparadores del popup (`popup_display*`, `popup_hide*`, cookie) → triggers y reglas del módulo `popup`.
    - Cart, checkout y thanks → contenido Elementor de las páginas WooCommerce (no hay tipo de documento en Theme Builder).
    - Megamenú → `modules/mega-menu` (parcial).
    - Sobrescrituras por post y término → condiciones `include/singular/<pt>/<ID>`.
  - **`class-betheme-kit.php`:** opción `betheme` → kit activo (`_elementor_page_settings` del post `elementor_active_kit`), usando las claves de `elementor/core/kits/documents/tabs/`:
    - `grid-width` → `container_width`; `section-padding` → `container_padding`.
    - `color-palette-1…14` → `custom_colors`; `skin` / `background-body` / `color-one` → `system_colors` y fondo de body.
    - `font-*` + `font-size-*` (con tablet y móvil) → `system_typography`, `custom_typography`, `h1…h6_typography_*`.
    - `button-*` → `theme-style-buttons`.
    - `custom-css` → CSS del kit.
    - `be_css_variables` → `_elementor_global_variables` si el valor es color, tamaño o fuente; si no, CSS del kit `body{--x:…}`.
    - En sitios sólo BeBuilder: `viewport_tablet` = 959, activar `viewport_laptop` = 1440. No toca otro contenido porque no hay contenido Elementor previo.
- **Reversibilidad:** no se borra nada (`mfn-page-items`, revisiones, CSS en `uploads/betheme`). Se escriben `_elementor_data`, `_elementor_edit_mode=builder`, `_elementor_version`, `_elementor_page_settings` (con `mfn-post-css` → `custom_css`) y `_wp_page_template` (`elementor_header_footer` cuando la página es full width). Se marca con `_digitalisimo_betheme_backup`, análogo a `Migration::BACKUP`. Los shortcodes de `post_content` se convierten a contenedores y widgets; los desconocidos quedan en un widget Shortcode y se listan en el informe.
- **Tema:** ampliar `class-retire.php` con un paso «cambiar al tema DIGITALÍSIMO» que sólo corre en `stage_finish` si todos los documentos convirtieron sin error. Betheme nunca se borra, sólo deja de estar activo.
- **SEO:** pasar `mfn-meta-seo-title`, `-description`, `-keywords` y `-og-image` al importador de DIGITALÍSIMO SEO, junto a Yoast y Rank Math. Las opciones de analítica de `betheme` (`google-gtag-*`, `facebook-pixel`) sólo se reportan.

### 6.2 Elements (sólo lo que no existe; nada de pintar `mfn-page-items` en el día a día)

- **CPT heredados:** en Elements (opción `digitalisimo_compat`, ver «Decisiones posteriores»), registrar `portfolio`/`portfolio-types` (y los demás CPT sólo si tienen posts) a partir de una opción que deja Migración, con los slugs originales (opciones `portfolio-slug` y `portfolio-tax` de `betheme`). Así el contenido no depende ni del tema ni de Migración. `testimonial`, `client`, `offer` y `slide` pueden registrarse privados como fuente de los widgets loop.
- **Widgets Woo nuevos** en `modules/digitalisimo-widgets/` (o un submódulo Woo), escritos desde cero:
  - Wishlist (con opción de importar la página `shop-wishlist-page`).
  - Quick view.
  - Swatches de variación (que lean `attribute_type` color/image/label y el valor que Migración copie de `mfn_attr_field` a una meta propia).
  - Barra de envío gratis para carrito y mini carrito, con un umbral importado de `free-delivery-sum`.
  - Cupón dentro del mini carrito.
  - Filtro de atributos y precio para `loop-filter`.
- **Sticky:** añadir a `modules/sticky` la opción genérica «mostrar sólo al fijarse / ocultar al fijarse», para cubrir el `header-sticky`. Sirve también para otros orígenes.
- **Dynamic tags:** cubrir `{termmeta:x}` y `{user:x}` si faltan en `modules/dynamic-tags`. Los tags embebidos en texto se convierten al tag del campo cuando el texto es sólo el tag; si no, se dejan como texto y se reportan.

### 6.3 Tema DIGITALÍSIMO

El tema mínimo (sin tema hijo) aporta:

- Soporte de ubicaciones de Theme Builder y WooCommerce.
- Los modos normal, ancho completo (`elementor_header_footer`) y lienzo (`elementor_canvas`), con respaldos sin Elements.

Las sidebars `footer-area-1…5` y `sidebar-*` no las registra el tema: pasan a widgets Sidebar dentro de plantillas del Theme Builder, registradas por Elements con la opción `digitalisimo_compat`.

Nada del layout de Betheme (subheader, `mfn-post-layout`) se reimplementa en el tema: se convierte a contenedores y plantillas.

### 6.4 Qué se convierte automáticamente y qué no

- **Automático:**
  - Estructura (las tres generaciones), tamaños y responsive, grid y flex, wraps anidados, fondos, bordes, sombras, tipografías, colores, márgenes y padding.
  - Unos 70 items con equivalente directo (ver mapeo en 6.5).
  - Clases globales (incrustadas) y secciones globales.
  - Condiciones de plantillas y de elementos, kit, breakpoints y medianil.
  - Popups (triggers básicos), plantillas Woo, metas SEO, CSS por página y shortcodes de columnas y básicos.
- **Semiautomático, con aviso en el informe:**
  - Query loop → Loop Grid (se genera una plantilla Loop Item a partir de los wraps).
  - Tabber, megamenú, sidemenu, separadores de forma, GSAP → motion-fx, animaciones `*Large`, datos dinámicos embebidos.
  - Header clásico por opciones (`header-style`) → plantilla genérica logo + menú (+ top bar), y footer por `footer-layout` → columnas con Sidebar.
- **Sin conversión:**
  - Swatches, wishlist y quick view hasta que existan los widgets propios.
  - «Fake sale», «content overlay» del submenú, `mfn-post-custom-layout` (CPT `layout`), `post_love`, `payment_methods` (pasa a imagen), selectores de moneda e idioma (shortcode del plugin de idioma), `mfn-post-js` (podría ir a `modules/custom-code` con condición de página).
  - Revisiones del builder, IA, presets y favoritos.
  - Demos y secciones remotas de Muffin.

### 6.5 Mapeo Betheme → Elements

| Betheme | Elements |
|---|---|
| Sección / wrap / wrap anidado | Container / Container hijo / Container anidado |
| Wrap grid | Container grid |
| Sección o wrap tipo query | `loop-grid` / `loop-carousel` |
| Tabber | Nested Tabs |
| Global section o wrap | Plantilla `section` + widget Template |
| `heading`, `fancy_heading` | `heading` / `digitalisimo-advanced-heading` |
| `column`, `visual`, `plain_text` | `text-editor` |
| `content`, `post_content` | `theme-post-content` |
| `image` | `image` |
| `image_gallery` | `gallery` / `digitalisimo-custom-gallery` |
| `button` | `button` / `digitalisimo-advanced-button` |
| `icon_box`, `icon_box_2`, `icon_2` | `icon-box` / `digitalisimo-advanced-icon-box` |
| `accordion`, `faq`, `toggle` | `digitalisimo-accordion` / nested-accordion |
| `tabs` | nested-tabs / `digitalisimo-fancy-tabs` |
| `list`, `list_2`, `feature_list` | `icon-list` / `digitalisimo-fancy-list` |
| `divider`, `divider_2`, `fancy_divider` | `divider` / `digitalisimo-advanced-divider` |
| `spacer` | `spacer` |
| `counter`, `quick_fact` | `counter` / `digitalisimo-total-count` |
| `countdown`, `countdown_2` | `countdown` |
| `progress_bars`, `chart` | `digitalisimo-progress-bars` / `digitalisimo-chart` |
| `video` | `video` / `digitalisimo-video-player` |
| `map`, `map_basic` | `google-maps` / `digitalisimo-map` |
| `before_after` | `digitalisimo-image-compare` |
| `slider`, `offer`, `offer_thumb`, `*_slider` | Slider Optimizado (`digitalisimo-slider-optimizado`) / `loop-carousel` / `testimonial-carousel` |
| `clients`, `clients_slider` | `digitalisimo-logos` |
| `blog*`, `portfolio*` | `posts` / `portfolio` / `loop-grid` |
| `pricing_item` | `price-table` |
| `call_to_action`, `promo_box`, `trailer_box`, `sliding_box`, `story_box`, `zoom_box`, `hover_box`, `flat_box`, `photo_box`, `feature_box`, `info_box`, `article_box`, `banner_box` | `call-to-action` / `flip-box` / `digitalisimo-featured-box` / `digitalisimo-fancy-card` (parcial visual) |
| `our_team*` | `digitalisimo-fancy-card` / `image-box` (parcial) |
| `blockquote` | `blockquote` |
| `code`, `html` | `html` / `code-highlight` |
| `opening_hours` | `digitalisimo-business-hours` |
| `how_it_works`, `timeline` | `digitalisimo-timeline` |
| `lottie` | `lottie` |
| `hotspot` | `hotspot` |
| `marquee_text` | `digitalisimo-marquee` |
| `table_of_contents` | `table-of-contents` |
| `share` | `share-buttons` |
| `breadcrumbs` | `digitalisimo-breadcrumbs` |
| `livesearch`, `header_search` | `search` |
| `tag_cloud` | `digitalisimo-tags-cloud` |
| `sidebar_widget` | `sidebar` |
| `cf7`, `slider_plugin` | `shortcode` |
| `header_logo`, `footer_logo` | `theme-site-logo` |
| `header_menu`, `footer_menu`, `megamenu_menu` | `nav-menu` / `mega-menu` |
| `header_burger`, `sidemenu_menu` | `off-canvas` / `digitalisimo-icon-mobile-menu` / `digitalisimo-vertical-menu` |
| `header_icon` cart | `woocommerce-menu-cart` |
| `header_promo_bar`, sección `closeable` | `digitalisimo-notification` (parcial) |
| `post_*` | `theme-post-title`, `theme-post-featured-image`, `post-info`, `author-box`, `post-comments`, `post-navigation` |
| `archive_*` | `archive-posts` / `theme-archive-title` |
| `shop_products` | `woocommerce-archive-products` |
| `shop_title`, `shop_cat_desc` | `woocommerce-archive-description` |
| `shop_categories` | `wc-categories` |
| `shop`, `shop_slider` | `wc-products` / `digitalisimo-product-grid` |
| `product_*` | `woocommerce-product-*` (title, images, price, add-to-cart, meta, short-description, data-tabs, additional-information, rating, stock, upsell, related, content) + `woocommerce-breadcrumb` |
| `product_brands` | Sin equivalente (post-info con taxonomía) |
| `cart_table` + `cart_totals` + `cart_cross_sells` | `woocommerce-cart` (se fusionan) |
| `checkout` | `woocommerce-checkout-page` |
| `thankyou_*` | `woocommerce-purchase-summary` |
| `woo_alert` | `woocommerce-notices` |
| `filters`, `active_filters` | `taxonomy-filter` (parcial) |
| `order_steps`, `payment_methods`, `post_love`, `header_language_switcher`, `header_currency_switcher`, `progress_icons` | Sin equivalente directo |
| Opciones `betheme` + `be_css_variables` + `be_classes` | Kit activo + `modules/variables` + estilos incrustados |
| Plantillas `header`, `footer`, `single-*`, `archive-*`, `search` | Theme Builder (`modules/theme-builder/documents/*`) |
| Plantillas `single-product`, `archive-product` | `modules/woocommerce/documents/product.php` y `product-archive.php` |
| Plantilla `popup` | `modules/popup` |
| `mfn_template_conditions` | `_elementor_conditions` |

---

## Decisiones posteriores (plan aprobado)

- **Ubicación.** Lector, normalizador y mapas viven en `digitalisimo-elements/modules/digitalisimo-import/sources/betheme/`; Migración v2 sólo orquesta con su driver. Los 57 widgets `mfn_*` de sitios Betheme + Elementor también se convierten desde ahí: no llevan nombres prohibidos en el ZIP de Elements.
- **Destino clásico (v3).** `be_classes` se incrustan en cada instancia; los presets de estilo del kit (F7a) sirven para lo repetido.
- **Tema.** «Migrar» deja documentos y plantillas en borrador; en producción el administrador activa el tema tras la vista previa y la tarea termina sola (activa por hash, publica las plantillas del Theme Builder y retira el origen). Betheme nunca se borra.
- **Sin tema hijo.** El código propio va al editor de fragmentos PHP de DIGITALÍSIMO Tools (F1b); `mfn-post-js` va a Código personalizado en borrador.
- **Capacidades previas (F7a):** degradados de N paradas, máscaras y patrones en clean-room (sin SVG ni recursos de Betheme), condiciones nuevas, prioridad manual en el Theme Builder, sticky con «mostrar u ocultar al fijarse» y controles de documento para ocultar cabecera, pie e imagen destacada (sustituyen a `mfn-post-hide-title` y similares).
- **WooCommerce (F4):** wishlist (con exportación y borrado de datos personales), quick view, swatches con term meta propia, barra de envío gratis y cupón en el mini carrito pintados por fragmentos, filtros de precio y atributo. Todo clean-room. Se excluye la «venta falsa».
- **Demos y Site Library:** sólo paquetes locales convertidos a un kit 3.0 sin `theme`, `experiments` ni `plugins`, importados en modo «sólo agregar».
- **Lo no convertible** se marca con el widget oculto `digitalisimo-unconverted` (conserva la carga original) en vez de dejar un shortcode en crudo.
