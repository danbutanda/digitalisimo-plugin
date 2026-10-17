# Informe de GeneratePress (GP Premium 2.5.6) para DIGITALÍSIMO

> Revisión del 16 de octubre de 2026, depurada para el repositorio público. GP Premium es GPLv2 o posterior: se estudia y se reescribe, sin copiar su código. Donde el plan aprobado decidió otra cosa, manda `docs/FUSION_OBJECTIVE.md`; las diferencias están al final.

ZIP analizado: `reference/gp-premium.zip`. Las citas `ruta:línea` se refieren a archivos dentro de `gp-premium/`. Sólo se leyó con `unzip -l`, `-Z1` y `-p`; no se extrajo ni se ejecutó nada.

**Ausencias.** En `reference/` no están el tema GeneratePress, GenerateBlocks, GenerateBlocks Pro ni GenerateCloud (F0 trae las copias oficiales del tema y de GenerateBlocks). La búsqueda de `generateblocks`, `generatecloud` y `themes/generatepress` en todos los ZIP de referencia sólo encontró el puente de cabecera y pie de Element Pack con el tema GP; GP Premium sólo hace llamadas condicionales a esos productos (`class_exists`/`function_exists`).

---

## 1. Qué hay, versión y formato

- **Plugin:** GP Premium 2.5.6, de Tom Usborne. Pide WordPress 6.1 y PHP 7.2; licencia GPLv2-or-later (`gp-premium.php:1-26`, `readme.txt`). Son 329 archivos.
- **Activación por módulos.** `generatepress_is_module_active( $option, $constant )` comprueba `get_option( 'generate_package_X' ) === 'activated'` o que la constante esté definida (`gp-premium.php:40-53`). Cada módulo se carga con `require_once` según esa comprobación (`gp-premium.php:56-109`):
  - Se cargan siempre que estén activos: backgrounds, blog, copyright, disable_elements, elements (más `inc/class-register-dynamic-tags.php` e `inc/class-adjacent-posts.php`), secondary_nav, spacing y menu_plus.
  - woocommerce sólo se carga si el plugin WooCommerce está activo.
  - hooks, page_header y sections están obsoletos.
- **Carga diferida que depende de la versión del tema** (`gp-premium.php:117-129`, en `after_setup_theme`):
  - Typography sólo se carga si el tema **no** usa «dynamic typography».
  - Colors sólo se carga si la versión del tema es menor que 3.1.0. En GP 3.1 y posteriores, colores y tipografía viven en el tema.
- **Admin y frontal.** Site Library carga la REST y el helper siempre, y la UI sólo con `is_admin()` (`gp-premium.php:140-151`). Font Library carga cuatro clases (`:153-158`). También carga siempre `general/` (archivo CSS externo, smooth scroll, iconos, enqueue) e `inc/class-dashboard.php`.
- **REST del panel:** namespace `generatepress-pro/v1` con rutas `/modules/`, `/license/`, `/beta/`, `/export/`, `/import/` y `/reset/` (`inc/class-rest.php:29-120`). `update_module` acepta sólo claves de `GeneratePress_Pro_Dashboard::get_modules()` y guarda `'activated'` o `'deactivated'` (`:141-170`).
- **Actualizador** EDD contra generatepress.com, con la licencia en `gen_premium_license_key` (`gp-premium.php:160-185`). Si el tema no es GeneratePress, sólo muestra un aviso y no se bloquea (`:228-268`).

## 2. Capacidades completas, parciales y faltantes

### 2.1 Módulo Elements (`elements/`)

**CPT `gp_elements`** (`class-post-type.php:75-110`):
- Es `public=false`, `show_ui`, `hierarchical`, `show_in_rest` y `can_export`.
- Para los tipos que no son `block`, quita el editor y la REST (`:119-165`).

**Despacho** (`elements.php:25-78`):
- Corre en `wp` y en `current_screen`.
- Hace `get_posts` de hasta 500 ids publicados, en orden ASC y filtrable con `generate_elements_custom_args`.
- Según `_generate_element_type` crea `GeneratePress_Hook`, `GeneratePress_Hero` (sólo uno: `! GeneratePress_Hero::$instances`), `GeneratePress_Site_Layout` o `GeneratePress_Block_Element`.
- Lo que se muestra se registra en `global $generate_elements` (alimenta el menú de la barra de administración, `:211-290`).
- Polylang: un elemento con otro idioma se descarta salvo que tenga `_generate_element_ignore_languages` (`:117-142`).

| Tipo (`_generate_element_type`) | Subtipo (`_generate_block_type`) | Dónde se pinta | Archivo |
|---|---|---|---|
| `hook` | — | `_generate_hook` o `_generate_custom_hook` (si es `custom`), con la prioridad de `_generate_hook_priority` | `class-hooks.php:114-196` |
| `header` (Page Hero clásico) | — | `generate_after_header` con prioridad 9; filtrable con `generate_page_hero_location` | `class-hero.php:119-121` |
| `layout` | — | Filtros y `remove_action` del tema | `class-layout.php:284-354` |
| `block` | `hook` | Hook libre | `class-block.php:103-143` |
| `block` | `site-header` | `generate_header`; quita `generate_construct_header` | `:112-114,169-171` |
| `block` | `site-footer` | `generate_footer`; quita `generate_construct_footer` | `:116-118,173-175` |
| `block` | `right-sidebar` / `left-sidebar` | `generate_before_{right,left}_sidebar_content`; vacía `sidebar-1` o `sidebar-2` | `:120-126,241-251,286-289` |
| `block` | `content-template` | `generate_before_do_template_part`; `generate_do_template_part` devuelve false | `:128-130,177-179,203-209` |
| `block` | `loop-template` | `generate_before_main_content`; apaga el loop por defecto, las columnas, el infinite scroll y el «leer más» | `:132-134,181-186,258-278` |
| `block` | `search-modal` | `generate_inside_search_modal` | `:136-138,188-190` |
| `block` | `post-meta-template` | `generate_{after,before}_entry_title`, `generate_{after,before}_page_title` o `generate_after_content`, según `_generate_post_meta_location` | `:145-163` |
| `block` | `page-hero` | Hook libre; puede ocultar título, imagen destacada y meta primaria | `:291-324` |
| `block` | `post-navigation-template` / `archive-navigation-template` | Hook libre; envoltorio opcional `.paging-navigation` | `:346-360,443-467` |

**Elementos de bloque, renderizado** (`class-elements-helper.php:130-159`):
- Llama a `do_blocks( post_content )` tras `autoembed`.
- En `site-footer` reemplaza `{{current_year}}`.
- Aplica el filtro `generate_do_block_element_content`, que hace `shortcode_unautop`, `do_shortcode` y `wp_filter_content_tags` (`elements.php:191-209`).
- La plantilla de contenido abre `<article id="post-N" class="dynamic-content-template …">` (o el tag de `_generate_post_loop_item_tagname`) y añade `inside-article` si está `_generate_use_theme_post_container` (`class-block.php:422-441`).
- Las plantillas hijas (`post_parent`, hasta 20, ordenadas por `menu_order`) reemplazan a la padre si cumplen `_generate_post_loop_item_display`, que vale `has-term`, `has-post-meta` o `is-first-post` (`class-block.php:380-420`; `class-elements-helper.php:239-288`).
- Las hijas heredan las Display Rules de la padre (`class-block.php:53-62`).

**Bloques dinámicos propios:**
- `generatepress/dynamic-content`, con `contentType` `post-content`, `post-excerpt`, `term-description` o `author-description`.
- `generatepress/dynamic-image`, con `imageType` `featured-image`, `author-avatar` o `post-meta`, más `linkTo`, `imageSize` y otros (`class-block-elements.php:191-282`).
- Atributos `gpDynamic*` sobre `generateblocks/headline|button|container`. Hay textos `title`, `post-date`, `post-author`, `terms`, `post-meta`, `comments-number`, `user-meta` y `term-meta`; enlaces `single-post`, `author-archives`, `comments`, `post-meta`, `term-archives`, `previous-posts` y `next-posts`; fuentes `current-post`, `next-post` y `previous-post`. Se resuelven con `render_block` y `str_replace` del texto marcador `gpDynamicTextReplace` (`:740-1135`).
- Etiquetas dinámicas para GenerateBlocks 2: `archive_title`, `archive_description`, `term_meta`, `current_year`, `previous_posts_page_url` y `next_posts_page_url`, sólo si existe `GenerateBlocks_Register_Dynamic_Tag` y no está GB Pro (`inc/class-register-dynamic-tags.php:32-100`).

**Hooks** (`class-hooks.php:205-221`):
- `do_shortcode` es opcional.
- El PHP se ejecuta con `eval( '?>' . $content . '<?php ' )` si `_generate_hook_execute_php` está activo y `DISALLOW_FILE_EDIT` no es true (`class-elements-helper.php:112-120`).
- La lista oficial de hooks está en `class-elements-helper.php:297-502`: `wp_head`, `wp_body_open`, `wp_footer`, unos 40 hooks `generate_*` y unos 60 de WooCommerce.
- Hay una lista negra para hooks personalizados: `init`, `wp`, `template_redirect`, `wp_enqueue_scripts`, `the_post`… (`class-metabox.php:1689-1709`).

**Page Hero (`header`)** (`class-hero.php`):
- HTML libre con template tags `{{post_title}}`, `{{post_date}}`, `{{post_author}}`, `{{post_terms.TAX}}` y `{{custom_field.KEY}}` (`:924-1024`).
- Si encuentra un tag, oculta el título, la fecha, el autor y los términos del tema (`:884-913`).
- Envoltorio `div.page-hero > div.inside-page-hero` con `grid-container` según `container` e `inner_container` (`:157-200`).
- Fondo: imagen destacada o imagen propia del elemento (thumbnail), overlay, paralaje, padding de escritorio y móvil, y altura de pantalla completa sólo con `site_header_merge` (`:208-556`).
- «Merge» de la cabecera (cabecera transparente sobre el hero): cambia el logo, el logo de navegación, el logo móvil, los colores de navegación y la ubicación de la navegación (`:634-880`).

**Layout element** (`class-layout.php`):
- Barra lateral y widgets del pie mediante los filtros `generate_sidebar_layout` y `generate_footer_widgets`, salvo que el post tenga su propia meta del tema (`:285-291`).
- Desactiva la cabecera, la cabecera móvil, la top bar, la navegación primaria y la secundaria, la imagen destacada, el título y el pie (`:293-331`).
- `content_area` como clase de body: `full-width-content` o `contained-content` (`:550-560`).
- Ancho de contenido con CSS inline `#content{max-width:Npx;margin:auto}` (`:359-362`) y `--content-width` en el editor (`:392-410`).

**Condiciones** (`class-conditions.php`):
- Catálogo (`:41-142`):
  - `general:site|front_page|blog|singular|archive|author|date|search|no_results|404|is_paged`.
  - `post:{pt}`, `archive:{pt}` (sólo si `has_archive`, salvo `post`), `taxonomy:{tax}` y `{pt}:taxonomy:{tax}`.
- Usuario: `general:all|logged_in|logged_out` y los slugs de rol (`:151-174`).
- Evaluación (`show_data`, `:323-400`): la inclusión es OR entre reglas; luego las exclusiones; luego la intersección con los roles. `{pt}:taxonomy:{tax}` exige un `object` (término) y usa `has_term`.

**Faltantes o parciales en Elements:**
- No hay condiciones por fecha, URL ni dispositivo.
- Las exclusiones no soportan `general:site`.
- La comparación usa `in_array` laxo con el valor del `object` contra todo el array (`:336-343`), una fragilidad que conviene no replicar.

### 2.2 Layout por página (Disable Elements y metas del tema)

`disable-elements/functions/functions.php`:
- Metas por entrada: `_generate-disable-top-bar|header|mobile-header|nav|secondary-nav|post-image|headline|footer`, con valor `"true"`.
- Se aplican de dos maneras: con CSS `display:none` (`:20-71`) y con `remove_action` y filtros del tema (`:243-299`).
- La metabox se integra en la del tema mediante `generate_layout_disable_elements_section` y `generate_layout_meta_box_save` (`:302-410`).

Las metas de **barra lateral, widgets del pie y ancho de contenido por página son del TEMA**. GP Premium sólo consulta `_generate-sidebar-layout-meta` y `_generate-footer-widget-meta` (`class-layout.php:285-289,420-436`). La meta de ancho completo por página no aparece en GP Premium: su clave y sus valores no se pueden verificar en `reference/`.

### 2.3 Generación de CSS

- **`GeneratePress_Pro_CSS`** (`library/class-make-css.php:11-199`). Es un builder minificado:
  - `set_selector()`, `add_property( prop, value, og_default, unit )` (omite valores vacíos o iguales al default, `:90-105`), `start_media_query()`/`stop_media_query()` y `css_output()`.
  - Cada módulo lo usa y lo añade con `wp_add_inline_style( 'generate-style', … )`.
- **Media queries** (`inc/functions.php:18-35`): delegan en `generate_get_media_query()` del tema si existe. Si no, usan desktop `(min-width:1025px)`, tablet `(min-width: 769px) and (max-width: 1024px)`, mobile `(max-width:768px)` y `mobile-menu`, que por defecto es igual a mobile y se puede filtrar.
- **Archivo externo** (`general/class-external-file-css.php`):
  - La opción `generate_settings[css_print_method]` vale `inline` o `file`.
  - Escribe `uploads/generatepress/style{_blog-N}.min.css` con `WP_Filesystem` (`:215-267,328-365`).
  - El contenido sale de `generate_get_dynamic_css()` del **tema** más el filtro `generate_external_dynamic_css_output`, donde los módulos (Menu Plus, Spacing…) concatenan su CSS.
  - Se regenera si falta `generatepress_dynamic_css_data['updated_time']` o cambia la versión del tema o del plugin, como máximo cada 5 s (`:140-169,405-431`). Al guardar el Customizer se borra la marca de tiempo (`:45,380-388`).
  - La caché inline del tema vive en `generate_dynamic_css_output` y `generate_dynamic_css_cached_version`; Site Library e import la borran (`site-library/class-site-library-rest.php:478-487`).

### 2.4 Site Library (sin ejecutar nada)

**Catálogo:**
- `GET generatepress-site-library/v1/get_sites` descarga `https://sites.generatepress.com/wp-json/gp-starter-sites/v1/sites`. Con GenerateBlocks v1 usa `https://gpsites.co/wp-json/wp/v2/sites?per_page=100` (`class-site-library-rest.php:214-283`).
- Lo guarda en caché en `generatepress_sites` con `generatepress_sites_expiration` (+1 día).
- Campos por sitio: `name`, `directory`, `preview_url`, `author_name`, `author_url`, `description`, `page_builder`, `category`, `min_version`, `min_theme_version`, `min_generateblocks_version`, `uploads_url`, `plugins`, `documentation`, `image_width` e `image_height`.

**Archivos remotos por sitio**, en `{directory}/` (`:291-334`):
- `options.json` con `modules`, `plugins`, `mods`, `options`, `site_options` y `custom_css`.
- `content.xml`, un WXR.
- `widgets.wie`, en formato JSON de Widget Importer & Exporter.

**Importación por pasos (REST con nonce):**
1. `import_options` (`:342-523`):
   - Respalda en `_generatepress_site_library_backup`.
   - Borra las 9 opciones `generate_*_settings` y luego `remove_theme_mods()`.
   - Activa sólo los módulos válidos (`premium_modules()`, `class-site-library-helper.php:936-955`).
   - Aplica los `mods` permitidos (`get_theme_mods()`, `:877-910`): las fuentes `font_*_variants|category` y `generate_copyright`.
   - Aplica las `options` permitidas (`get_theme_settings()`, `:857-870`) y hace sideload de las imágenes jpg/png/gif que encuentra en los valores.
   - Mete el CSS entre los marcadores `/* GeneratePress Site CSS */ … /* End GeneratePress Site CSS */`.
2. `activate_plugins` (`:530-580`).
3. `import_content` (`:583-645`): usa el WXRImporter empaquetado (`site-library/libs/wxr-importer`, namespace `GeneratePress\WPContentImporter2`), sin autores y con `fetch_attachments`. Guarda el mapeo de IDs en `generatepress_sites_mapped_ids_{slug}` (`class-site-library-helper.php:161-219`) y marca lo importado con `_generatepress_sites_imported_post` o `_generatepress_sites_imported_term`.
4. `import_site_options` (`:653-781`): portada y blog, páginas de WooCommerce, `nav_menu_locations`, `element_locations` y `element_exclusions` (remapea los `object` con los IDs nuevos, `class-site-library-helper.php:507-571`), y `custom_logo`. Las demás opciones se aplican salvo `disallowed_options()` (`:959-980`).
5. `import_widgets` (`:789-815`).
6. `restore_theme_options` y `restore_content` para revertir.

**Exportación para creadores de sitios** (`site-library/class-site-library.php:222-340`): incluye opciones de Elementor (`elementor_active_kit`, `elementor_container_width`, `elementor_cpt_support`…), de Beaver Builder y de varios plugins. Sólo limpia la caché de Elementor; no convierte nada de Elementor.

**Import/export manual** (`inc/class-rest.php:315-447`): un JSON con `{modules, mods, options}`, o bien `{"global-colors": …}` o `{"typography": …, "font-manager": …}`.

### 2.5 Otros módulos

- **Menu Plus** (`menu-plus/functions/generate-menu-plus.php`):
  - Defaults (`:30-55`):

    | Opción | Valores |
    |---|---|
    | `sticky_menu` | `false`, `mobile`, `desktop` o `true` |
    | `sticky_menu_effect` | `fade`, `slide` o `none` |
    | `auto_hide_sticky` | sí o no |
    | `mobile_header` | `enable` o `disable` |
    | `mobile_menu_breakpoint` | `768` |
    | `mobile_header_branding` | `logo` o `title` |
    | `mobile_header_sticky` | sí o no |
    | `slideout_menu` | `false`, `mobile`, `desktop` o `both` |
    | `slideout_menu_style` | `slide` u `overlay` |
    | `slideout_menu_side` | izquierda o derecha |
    | `slideout_close_button` | posición del botón de cierre |
    | `navigation_as_header` | sí o no |
    | `sticky_navigation_logo` | logo |

  - El sticky usa `js/sticky.js`, que depende de `jquery-core` y clona un placeholder (`:900-930`).
  - El off-canvas usa `js/offside.js` (MIT, sin dependencias): enfoca el primer elemento focusable, cierra con Escape y gestiona `aria-expanded` (`offside.js:521-640`).
  - Cabecera móvil: `<nav id="mobile-header">` en `generate_after_header` con prioridad 5 y microdatos `SiteNavigationElement` (`:1069-1228`).
  - Panel lateral: `<nav id="generate-slideout-menu">` en `wp_footer` con prioridad 0, ubicación de menú `slideout` y una zona de widgets (`:1230-1325`).
  - Clases de body `slideout-enabled|mobile|desktop|both` (`:1328-1360`).
- **Typography (heredada)**: `generate_settings[font_*]`, `*_font_weight`, `*_font_transform`, `*_font_size`, `tablet_*` y `mobile_*` (`typography/functions/functions.php:89-760`). Sólo se carga en temas con tipografía heredada. La tipografía moderna está en `generate_settings['typography']` y `['font_manager']` (`inc/class-rest.php:375-405`); su estructura interna está en el tema y no se puede verificar.
- **Colors**: sólo se carga si el tema es anterior a 3.1. Los colores globales modernos están en `generate_settings['global_colors']`, un array de elementos con `slug` y `color` que se usan como `var(--slug)` (`woocommerce/functions/functions.php:1523-1531`). La clave `name` no se puede verificar aquí.
- **Font Library**: CPT `gp_font` (`font-library/class-font-library.php:18`) con las metas `gp_font_variants` (array), `gp_font_family_alias`, `gp_font_display`, `gp_font_source`, `gp_font_fallback`, `gp_font_preview` y `gp_font_variable` (`class-font-library-cpt.php:76-145`). Los archivos van a `uploads/generatepress/fonts/{slug}/` y el CSS a `uploads/generatepress/fonts/fonts.css` (`class-font-library.php:416-472`).
- **Blog**: `generate_blog_settings` (`blog/functions/defaults.php`) con `excerpt_length`, `read_more`, `masonry` (masonry e imagesloaded del core), `infinite_scroll` (Infinite Scroll 3.0.6), imagen destacada por contexto, meta de fecha, autor, categorías, etiquetas y comentarios, y `column_layout` y `columns`. Las columnas son clases `generate-columns`/`grid-N` (`blog/functions/generate-blog.php:12-125`, `columns.php:93-160`).
- **Secondary Nav**: registra el menú `secondary` (`secondary-nav/functions/functions.php:23-25`). `secondary_nav_position_setting` vale `secondary-nav-above-header`, `below-header`, `float-right` o `float-left` (`:478-541`). Tiene colores propios en `generate_secondary_nav_settings`.
- **Copyright**: el theme_mod `generate_copyright` reemplaza `%current_year%` y `%copy%` en `generate_credits` (`copyright/functions/functions.php:109-140`).
- **Spacing**: extiende `generate_spacing_settings` del tema con `mobile_menu_item`, `mobile_menu_item_height`, `sticky_menu_item_height`, `off_canvas_menu_item_height` y `content_element_separator` (`spacing/functions/functions.php:157-182`). Sin `generate_spacing_get_defaults()` del tema no hace nada (`:189-197`).
- **Backgrounds**: `generate_background_settings`, con `{zona}_image|repeat|size|attachment|position` para body, top_bar, header, nav, nav_item…, content, sidebar_widget y footer_widget (`backgrounds/functions/functions.php:23-66`).
- **WooCommerce**: `generate_woocommerce_settings` (`woocommerce/functions/functions.php:17-58`). Incluye carrito en el menú con mini-cart `#wc-mini-cart` (`:641-707`), panel off-canvas al añadir al carrito, panel sticky de añadir al carrito, columnas por dispositivo, botones de cantidad y «distraction free».
- **Obsoletos que pueden aparecer en sitios antiguos:**
  - Hooks: opción `generate_hooks` con claves `generate_{hook}_php` (`hooks/functions/functions.php:19-55`).
  - Page Header: CPT `generate_page_header` con metas `_meta-generate-page-header-*` (unas 45 claves) y `_generate-select-page-header`.
  - Sections: `_generate_use_sections` y `_generate_sections['sections'][]`, con `content`, `box_type`, `inner_box_type`, `custom_classes`, `custom_id`, `top_padding`, `bottom_padding`, `background_color`, `background_image` y `parallax_effect` (`sections/functions/templates/template.php:17-97`).
- **General**: smooth scroll sobre `.smooth-scroll` y `li.smooth-scroll a`, con 800 ms (`general/smooth-scroll.php:28-48`).

## 3. Formatos de datos exactos

**Metas de `gp_elements`** (guardado en `class-metabox.php:1627-1990`; metas REST de bloque en `class-block-elements.php:1435-1700`):

```
_generate_element_type            'hook'|'header'|'layout'|'block'   (sanitize_key)
_generate_block_type              'hook'|'site-header'|'page-hero'|'content-template'|'loop-template'|
                                  'post-meta-template'|'post-navigation-template'|'archive-navigation-template'|
                                  'right-sidebar'|'left-sidebar'|'site-footer'|'search-modal'
_generate_element_content         HTML/PHP crudo (sin filtrar si el autor tiene unfiltered_html; si no, wp_kses_post)
_generate_hook                    'wp_head'|'generate_after_header'|...|'custom'
_generate_custom_hook             texto (lista negra en el guardado)
_generate_hook_priority           entero como string; '0' se guarda como '0'; default 10
_generate_hook_execute_php / _execute_shortcodes / _disable_site_header / _disable_site_footer   'true' o ausente
_generate_element_display_conditions   array( i => array( 'rule' => 'post:page', 'object' => '123' ) )
_generate_element_exclude_conditions   el mismo formato
_generate_element_user_conditions      array( 'general:logged_in', 'administrator', ... )  (lista plana)
_generate_element_ignore_languages, _generate_element_internal_notes
Layout: _generate_sidebar_layout, _generate_footer_widgets ('no-widgets'|n), _generate_disable_{site_header,
        mobile_header,top_bar,primary_navigation,secondary_navigation,featured_image,content_title,footer},
        _generate_content_area ('full-width'|'contained'), _generate_content_width (px, absint)
Hero:   _generate_hero_* (container, inner_container, padding_{top,right,bottom,left}[_unit][_mobile],
        background_image ('featured-image'|'custom-image'), background_overlay, background_position,
        background_parallax, background_color, text_color, link_color, ...), _generate_site_header_merge,
        _generate_site_header_height[_mobile], _generate_site_logo|retina_logo|navigation_logo|mobile_logo (ID),
        _generate_navigation_location, _generate_navigation_*_color[_hover|_current]
Bloque: _generate_post_meta_location ('after-post-title'|'before-post-title'|'after-content'|'custom'),
        _generate_post_loop_item_tagname, _generate_use_theme_post_container,
        _generate_use_archive_navigation_container, _generate_disable_{title,featured_image,primary_post_meta,
        secondary_post_meta,post_navigation,archive_navigation},
        _generate_post_loop_item_display ('has-term'|'has-post-meta'|'is-first-post'),
        _generate_post_loop_item_display_{tax,term (CSV de slugs o IDs),post_meta},
        _generate_block_element_editor_width[_unit]
```

**Ejemplo de condición:**

```php
array(
  array( 'rule' => 'general:site', 'object' => '' ),
  array( 'rule' => 'post:taxonomy:category', 'object' => '7' ),
)
```

El `object` pasa por `sanitize_key`, así que es un string numérico (`class-metabox.php:1896-1936`).

**Opciones globales:**
- Los módulos `generate_package_*` valen `'activated'` o `'deactivated'`.
- `generate_settings` es del tema; la extienden `css_print_method`, `smooth_scroll`, `typography`, `font_manager` y `global_colors`.
- Los demás arrays de módulos: `generate_background_settings`, `generate_blog_settings`, `generate_secondary_nav_settings`, `generate_spacing_settings`, `generate_menu_plus_settings`, `generate_woocommerce_settings`, `generate_page_header_settings` y `generate_hooks`.
- El theme_mod `generate_copyright`.
- `generatepress_dynamic_css_data` con `updated_time`, `theme_version` y `plugin_version`.

**`options.json` de Site Library:** `{ "modules": {Nombre: clave}, "plugins": {Nombre: "slug/archivo.php"}, "mods": {...}, "options": {"generate_settings": {...}, ...}, "site_options": {"show_on_front", "page_on_front", "page_for_posts", "nav_menu_locations", "custom_logo": URL, "element_locations": {oldPostID: [cond...]}, "element_exclusions": {...}, "woocommerce_*", "elementor_*"...}, "custom_css": "..." }`.

## 4. Licencia y qué se puede reutilizar

- **GP Premium es GPLv2-or-later.** Por la cláusula «or later» se podría incorporar a nuestro derivado GPLv3 (Elements usa GPL-3.0-or-later). La decisión es estudiar y reescribir; si algún día se reutiliza código, se registran origen, copyright y cambios en `docs/third-party-licenses.md`, como se hizo con Element Pack.
- **Terceros empaquetados:** offside.js 1.3.1 (MIT), Infinite Scroll 3.0.6 (GPLv3 u opción comercial; con GPLv3 es compatible), select2 y selectWoo (MIT), jquery.vide (MIT), wp-color-picker-alpha (GPL) y el WXRImporter (namespace propio de GP, derivado del importador de WordPress, GPL; hay que verificar su cabecera antes de copiarlo).
- **Conviene reutilizar como idea o reescribir:**
  - El modelo de condiciones regla+objeto+roles.
  - El patrón de un elemento por hook con prioridad.
  - El builder minificado de CSS. Nosotros ya tenemos el de Elementor, así que no hay que copiarlo.
  - El remapeo de IDs tras importar un WXR.
  - La lista negra de hooks.
  - La accesibilidad de offside (foco al abrir, Escape, `aria-expanded`).
- **No reutilizar:**
  - El actualizador y licenciamiento EDD (`library/class-plugin-updater.php`).
  - Los endpoints de generatepress.com y gpsites.co.
  - Las marcas «GeneratePress» y «GP» en la interfaz.
  - El `eval` de hooks.
  - El sticky en jQuery: Elementor ya trae sticky y motion-fx.

## 5. Riesgos

1. **Ejecución de PHP:** los Hook elements con `_generate_hook_execute_php` contienen PHP que se ejecuta con `eval` (`class-hooks.php:213-216`). Migrar ese contenido como HTML lo rompe; migrarlo como código ejecutable abre un riesgo de seguridad.
2. **Contenido sin filtrar:** `_generate_element_content` se guarda sin filtrar para administradores. Al convertirlo hay que pasarlo por `wp_kses_post`, salvo `<script>` y `<style>`, que deben ir explícitamente a Custom Code.
3. **Dependencia del tema:** casi todo el render se apoya en hooks y funciones del tema ausente. Hay unas 68 llamadas a `generate_is_using_flexbox`, 59 a `generate_get_option`, 32 a `generate_get_defaults`, 31 a `generate_spacing_get_defaults` y otras a `generate_construct_header` y `generate_construct_footer`. Sin el tema, Layout, Disable Elements, Spacing, Menu Plus y Hero se apagan solos o no hacen nada.
4. **GenerateBlocks:** los Block Elements y las páginas suelen guardar `<!-- wp:generateblocks/... -->` con CSS generado por GB (opción `generateblocks_dynamic_css_posts`, `elements.php:144-189`). Sin GB, no hay estilos ni fuente de verdad del formato.
5. **Breakpoints desfasados en 1 px:** GP usa móvil ≤768 y tableta 769–1024 (`inc/functions.php:24-27`); Elementor usa móvil ≤767 y tableta 768–1024. Un ancho de exactamente 768 px se ve distinto.
6. **Condiciones sin equivalente:** `general:is_paged`, `general:no_results` y la lógica «primer post» o «tiene meta» de las plantillas de contenido no tienen equivalente directo en el Theme Builder.
7. **Typography y Colors heredados frente a los modernos:** el formato depende de la versión del tema del sitio de origen.
8. **Sticky y off-canvas:** el sticky usa jQuery y un clon de la navegación (`#sticky-placeholder`). Si se intentara replicar, duplicaría un motor ya existente en Elements (`modules/sticky`, `modules/off-canvas`).

## 6. Recomendaciones de integración

Rutas base: `digitalisimo-elements/` y `digitalisimo-migration/`.

### 6.1 Elements: Theme Builder y condiciones (sin motor paralelo)

**Ubicaciones con hook.** `modules/theme-builder/classes/locations-manager.php:546-566` ya admite `register_location( $name, ['hook' => ..., 'remove_hooks' => [...]] )`. Hay que crear un `theme-support/digitalisimo-theme-support.php`, hermano de `generate-press-theme-support.php`, y registrarlo en `modules/theme-builder/classes/theme-support.php:21-28`, que hoy sólo conmuta `generatepress` y `elementor-safe`. Ese soporte registraría:
- las ubicaciones core `header` y `footer`;
- ubicaciones públicas para los hooks del tema propio (antes y después de la cabecera, antes del contenido principal, barras laterales, antes del pie).

Así un Hook o un Block element de GP se convierte en una plantilla «Section» del Theme Builder asignada a esa ubicación.

**Condiciones.** Las de GP se mapean al formato `_elementor_conditions` (cadenas `include|exclude/...`, `classes/conditions-manager.php:301-320`; nombres en `modules/theme-builder/conditions/*.php`):

| GP | Theme Builder |
|---|---|
| `general:site` | `include/general` |
| `general:front_page` | `include/singular/front_page` |
| `general:blog` | `include/archive/post_archive` (cubre `is_home`, `post-type-archive.php:71`) |
| `general:singular` / `general:archive` | `include/singular` / `include/archive` |
| `general:author` / `date` / `search` | `include/archive/author` / `date` / `search` |
| `general:404` | `include/singular/not_found404` |
| `post:{pt}` sin objeto o con objeto N | `include/singular/{pt}` o `include/singular/{pt}/N` |
| `archive:{pt}` | `include/archive/{pt}_archive` |
| `taxonomy:{tax}` sin objeto o con objeto N | `include/archive/{tax}` o `include/archive/{tax}/N` |
| `{pt}:taxonomy:{tax}` con objeto N | `include/singular/in_{tax}/N` |
| `general:no_results`, `general:is_paged` | **sin equivalente** (se informa y se aproxima con `search` o se deja sin condición) |

Las exclusiones usan el mismo mapeo con el prefijo `exclude/`.

**Roles y sesión.** No existen en las condiciones del Theme Builder. Se trasladan al contenedor raíz de la plantilla con nuestras reglas `digitalisimo_visibility_*`, que ya incluyen `authentication` y `role` (`modules/digitalisimo-conditions/class-visibility-rules.php:15,49-73`). Así se reutiliza lo existente y no se crea otro motor.

**Hooks de `wp_head`, `wp_body_open` y `wp_footer`.** Van a Custom Code (`modules/custom-code`, CPT `elementor_snippet`):
- metas `_elementor_location`, que vale `elementor_head`, `elementor_body_start` o `elementor_body_end`;
- `_elementor_priority`, de 1 a 10 (la prioridad de GP se reduce a ese rango);
- `_elementor_code`;
- `_elementor_conditions`.

### 6.2 Layout por página

- **Cabecera y pie desactivados.** `_generate-disable-header` y `_generate-disable-footer` juntos pasan a `_wp_page_template = elementor_canvas` (`elementor/modules/page-templates/module.php:35`).
- **Ancho completo.** El ancho completo del tema (meta no verificable aquí) o un Layout element con `content_area=full-width` pasan a `elementor_header_footer` (`:40`).
- **Título desactivado.** `_generate-disable-headline` y `_generate_disable_content_title` pasan a `_elementor_page_settings['hide_title']='yes'` (`elementor/core/document-types/page-base.php:94`). Esto sólo aplica a páginas editadas con Elements; en las demás debe respetarlo el tema propio.
- **Ancho de contenido.** `_generate_content_width` no tiene equivalente por página en Elementor. El tema propio debe leer su propia meta (por ejemplo, `_digitalisimo_layout`) o las variables del kit.
- **Imagen destacada, top bar y navegación:** las respeta el tema DIGITALÍSIMO con su propia meta.

Para no inventar otro sistema, el tema expondría una sola meta `_digitalisimo_layout` (array) y Migración la llenaría.

### 6.3 Migración (nueva etapa o adaptador; sin otro plugin)

Añadir a `legacy/class-runner.php` (`STAGES`, `:45`) una etapa en bucle `generatepress`, entre `convert` y `revisions`, implementada en un `legacy/class-generatepress.php` (en el mismo estilo que `class-adapters.php`).

**Conversión automática:**

| Origen GP | Destino en Elements |
|---|---|
| `gp_elements` tipo `hook` sin PHP | Custom Code (wp_head, wp_body_open, wp_footer) o plantilla Section con un widget HTML en la ubicación equivalente del tema propio (tabla `generate_*` → `digitalisimo_*`) |
| Condiciones, exclusiones y roles | Tabla 6.1 |
| Tipo `layout` | Para cada objeto que cumpla las condiciones: `_wp_page_template`, `hide_title` y `_digitalisimo_layout`. Si la condición es global, va a la configuración del tema o del kit |
| Metas Disable Elements por entrada | Tabla 6.2 |
| Hero clásico simple (h1 o p con `{{post_title}}` y similares) | Plantilla Section en la ubicación «después de la cabecera», con Heading y Text que usan las etiquetas dinámicas `post-title`, `post-date`, `author-name`, `post-terms` y `post-custom-field` (`modules/dynamic-tags/tags/`). El padding de escritorio y móvil va a los controles responsivos. El fondo va al contenedor |
| Block elements sólo con bloques core (paragraph, heading, image, buttons, group, columns) | Plantilla del Theme Builder de tipo header, footer o section, con widgets nativos |
| Kit | `global_colors` (`slug`, `color`) → `custom_colors` del kit. La tipografía heredada (`font_body`, `body_font_size`…) → `system_typography` |
| Menú off-canvas | Ubicación `slideout` → widget de Off-Canvas de Elements |
| Copyright | `generate_copyright` → Text Editor en la plantilla del pie, con el año como etiqueta `current-date-time` |

**Conversión parcial o revisión manual** (se marca en el informe):
- Hero con HTML complejo o «merge» de cabecera. Las etiquetas dinámicas sólo ocupan un control entero, así que no se pueden mezclar con texto libre.
- Plantillas `content-template` y `loop-template`: se pasan a Loop Item más Loop Grid (`modules/loop-builder`). Las hijas condicionales (`has-term`, `is-first-post`) no tienen equivalente directo.
- `post-meta-template` y la navegación de entradas y archivo.

**Sin conversión** por faltar el tema o GenerateBlocks:
- Bloques `generateblocks/*` y atributos `gpDynamic*`, porque no está el formato de GB.
- Hooks con PHP: se importan al editor de fragmentos PHP de DIGITALÍSIMO Tools, traducidos de `generate_*` a `digitalisimo_*`, **desactivados** y con su origen; sólo un administrador con permiso los revisa y activa (decisión del 2026-10-16; Migración nunca ejecuta ni activa PHP).
- CSS generado por GB.
- Opciones `generate_settings` del tema moderno (`typography`, `font_manager`), cuyo formato no se puede verificar.
- Sticky y cabecera móvil de Menu Plus: se recrean con Sticky y Off-Canvas de Elements, a mano o con un header de plantilla.

### 6.4 Importar kits o plantillas desde Site Library

**No es viable en línea:** depende de servidores de GP y de GB. Sólo como importación de archivos locales:
- `content.xml` va al importador WXR de WordPress más el remapeo de `element_locations` (el patrón de `class-site-library-helper.php:507-571`).
- `options.json` va a la conversión de opciones descrita en 6.3.

El plan aprobado lo pone en el importador de Elements: el paquete local se convierte a un kit 3.0 sin `theme`, `experiments` ni `plugins` y se importa en modo «sólo agregar».

## 7. Principios para el tema DIGITALÍSIMO (lienzo mínimo)

1. **Cabecera y pie mínimos.** En `header.php` y `footer.php`, el tema hace `do_action( 'digitalisimo_header' )` y `do_action( 'digitalisimo_footer' )`, con funciones por defecto que el Theme Builder retira con `remove_hooks`. Es el patrón `generate_header`/`generate_construct_header`, que Elements ya sabe manejar (`generate-press-theme-support.php:30-44`).
2. **Hooks estables y documentados**, equivalentes a los que se usan en `get_available_hooks()`: `before_header`, `after_header`, `before_main_content`, `after_main_content`, `before_content`, `after_entry_title`, `after_content`, `before_footer` y `after_footer`, más los de las barras laterales. Con prefijo `digitalisimo_` y con una tabla de equivalencias `generate_*` → `digitalisimo_*` para Migración.
3. **HTML semántico y accesible:**
   - un enlace «Saltar al contenido»;
   - `<header role="banner">`, `<nav aria-label>`, `<main id="content">`, `<aside>` y `<footer>`;
   - sin los microdatos `itemtype` que GP inyecta (el SEO ya emite JSON-LD).
4. **CSS mínimo** (`base.css` con `:where()`), sin opción `*_settings` propia y sin tocar `--e-global-*`, que es el kit del sitio. No hay que crear otro sistema de kits ni CSS dinámico propio: el diseño lo genera Elements desde el kit.
5. **Layout por página en los ajustes del documento de Elements** (plan aprobado, F7a): controles para ocultar cabecera, pie, título e imagen destacada. El tema sólo lee un filtro documentado y los aplica con `remove_action`, nunca con `display:none`, que es lo que hace Disable Elements en `:20-71`. Esto sustituye a la meta `_digitalisimo_layout` propuesta en 6.2 y 6.3.
6. **Breakpoints:** usar los de Elementor (≤767 y ≤1024).
7. **Sin tema hijo** (decisión del 2026-10-16): el PHP propio vive en el editor de fragmentos de DIGITALÍSIMO Tools, que es también el destino de los hooks con PHP de GP. Un hijo que el administrador cree por su cuenta sigue funcionando.
8. **Sin Site Library, sin actualizador propio fuera del actualizador común y sin módulos activables tipo `generate_package_*`.** Las funciones de Menu Plus, Secondary Nav, Blog y Woo ya las cubren los widgets y plantillas de Elements (Nav Menu, Off-Canvas, Sticky, Loop Builder, WooCommerce), así que el tema no debe reimplementarlas.

---

## Decisiones posteriores (plan aprobado)

- **Conversor** en `digitalisimo-elements/modules/digitalisimo-import/sources/gp/`; Migración v2 orquesta con su driver (no una etapa `generatepress` fija en `STAGES`).
- **Hooks sin PHP** a Código personalizado o a plantillas Section en ubicaciones `digitalisimo_*` del tema propio (tabla `generate_*` → `digitalisimo_*`); cabecera, pie y hero al Theme Builder; condiciones y roles con las reglas existentes.
- **Hooks con PHP** al editor de fragmentos de Tools (F1b), desactivados.
- **Layout por página** a los ajustes del documento (F7a), no a una meta del tema.
- **Retirada:** GP Premium lleva `phase => 'theme'`: se retira sólo cuando el sitio deja GeneratePress. GenerateBlocks y GB Pro nunca se retiran.
- **Bloques:** las páginas hechas sólo con bloques del núcleo siguen en el editor de bloques. GenerateBlocks se convierte si las muestras y su copia oficial lo permiten.
- **Breakpoints:** GP usa móvil ≤ 768; se cambia el kit sólo en sitios cuyo contenido viene íntegro de GP.
