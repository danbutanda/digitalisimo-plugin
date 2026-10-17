# Informe: tema propio DIGITALÍSIMO

> Revisión del 16 de octubre de 2026, depurada para el repositorio público. El 16 de octubre se decidió que el tema sale **sin hijo** y que el código PHP propio va a un editor de fragmentos de DIGITALÍSIMO Tools; este informe ya lo refleja. Lo que diga sobre Hello Elementor se verifica con su copia oficial en `reference/` (F0). Donde el plan aprobado decidió otra cosa, manda `docs/FUSION_OBJECTIVE.md`.

## 1. Qué hay, versión y formato

- **No hay ningún tema en el repositorio.** Las únicas pistas sobre Hello Elementor son:
  - La versión 3.5.1 usada en las pruebas de Playground (`docs/PERFORMANCE_MULTISITE_OBJECTIVE.md:125`).
  - Una condición de SEO sólo para Hello: `digitalisimo-seo/includes/performance/class-performance-manager.php:50` (`if ( 'hello-elementor' !== get_template() ) return false;`).
  - Pruebas que usan el handle `hello-theme-frontend` (`tests/performance-javascript.php:43-55`) y `hello-theme` (`tests/quality-audit.php:90`).
- **Lo que Elements espera de un tema** sale del código derivado de PRO, no de `elementor/`:
  - `digitalisimo-elements/modules/theme-builder/` (Theme Builder, ubicaciones y condiciones).
  - `elementor/modules/page-templates/` (plantillas Canvas y Ancho completo).
- **Hello Elementor no estaba en `reference/`** durante la revisión. Lo que aquí se dice de Hello (funciones, opciones y archivos, versión 3.x) sale de conocimiento previo y se verifica contra su copia oficial y con `wp option list --search='hello_*'` en un sitio real.
- **`reference/dokan.zip`**: tema Dokan 2.3.7, GPLv2 o posterior. Sólo sirve como antiejemplo:
  - `header.php:16` escribe el `<title>` a mano con `wp_title()`, sin `title-tag`, y no llama a `wp_body_open()`.
  - Fuerza `body_class( 'woocommerce' )`.
  - Sobrescribe plantillas de Woo con versiones viejas: `archive-product.php` @version 3.4.0, `single-product.php` 1.6.4, `woocommerce/cart/mini-cart.php` 3.7.0, `myaccount/form-login.php` 4.1.0, `loop/add-to-cart.php` 3.3.1.
  - Carga Bootstrap y Google Fonts (`functions.php:203-208`).
  - Declara el soporte Woo completo (`functions.php:123-128`).
- **Otros temas en `reference/`** (sólo para la etapa de cambio de tema de otros frentes):
  - La cabecera de Divi 5.15.0 dice «GNU General Public License v2»; su `LICENSE.md` añade «o (a tu elección) cualquier versión posterior», que se toma como vigente. Sus menús son `primary-menu`, `secondary-menu` y `footer-menu` (`Divi/functions.php:80-84`).
  - Betheme 29.0.3 tiene licencia «Betheme License», que es propietaria.

## 2. Capacidades completas, parciales y faltantes

**Completas en Elements, para reutilizar:**

- **Ubicaciones del Theme Builder.**
  - `classes/locations-manager.php:640-668` define header, footer, archive y single como ubicaciones del núcleo. `archive` lleva `overwrite => true` (`:657`).
  - `register_all_core_location()` (`:540`) registra las cuatro.
  - `do_location()` (`:388`) devuelve `false` si no hay documentos.
  - `api.php` expone `elementor_theme_do_location()` y `elementor_location_exits( $loc, $check_match )`.
- **Elección de plantilla** (`locations-manager.php:233-342`):
  - Una plantilla de página que no sea `default` corta el proceso (`:238-247`).
  - La tienda se trata como `archive`; el 404 y los términos inexistentes como `single` (`:256-264`).
  - Si la ubicación no está registrada o tiene `overwrite`, se fuerza `elementor_header_footer` (`:305-321`).
  - El filtro `elementor/theme/need_override_location` lo usa Woo para forzar la ficha de producto (`modules/woocommerce/module.php:488-493,1423`).
- **Respaldo cuando el tema no registra cabecera o pie** (`classes/theme-support.php:36-69`):
  - Engancha `get_header`/`get_footer` (`:71-130`), imprime su propio `<head>` (`views/theme-support-header.php`) y descarta el `header.php` del tema con `ob_start` más `remove_all_actions( 'wp_head' )` (`:83-87`).
  - Corrige la barra de administración en `wp_body_open` (`:97-112`).
  - Es frágil: un tema propio debe evitarlo registrando sus ubicaciones.
- **Soporte específico por tema:** GeneratePress (`theme-support/generate-press-theme-support.php`) y modo seguro (`safe-mode-theme-support.php`, que usa los hooks de Canvas).
- **Plantillas de página** (`elementor/modules/page-templates/module.php:30-40,82-105,143-161,220-232`):
  - `elementor_canvas` usa `templates/canvas.php`, que llama a `wp_head`, `wp_body_open` y `wp_footer` sin `get_header()`.
  - `elementor_header_footer` usa `templates/header-footer.php`, que llama a `get_header()`/`get_footer()` del tema, así que todo depende del `header.php`/`footer.php` del tema.
  - Si la página usa `default`, se aplica `default_page_template` del kit (`core/kits/documents/tabs/settings-layout.php:187`).
- **Ocultar título:** `hide_title` produce `--page-title-display:none` (`core/document-types/page-base.php:85-106`) y se aplica al selector `page_title_selector`, por defecto `h1.entry-title` (`settings-layout.php:137-151`).
- **Custom Code:** se imprime en `wp_head`, `wp_body_open` y `wp_footer` (prioridad 21) (`modules/custom-code/module.php:156-166`).
- **Otros elementos que dependen de los hooks estándar:**
  - Ventanas emergentes en `wp_footer` (`modules/popup/module.php:51`).
  - Transiciones de página en `wp_body_open` (`modules/page-transitions/module.php:871`).
  - Widget de comentarios con `comments_template()` (`modules/theme-elements/widgets/post-comments.php:118`).
- **Woo:** el widget de imágenes y el documento de producto consultan `current_theme_supports( 'wc-product-gallery-zoom|lightbox|slider' )` (`modules/woocommerce/widgets/product-images.php:199-205`, `documents/product.php:70-79`). Elements trae su propio mini-carrito (`module.php:514-530`).

**Parciales:**

- **El logotipo depende del tema.** `get_theme_mod( 'custom_logo' )` (`modules/dynamic-tags/tags/site-logo.php:29`, kit en `settings-site-identity.php:36,153`) se guarda por tema. Al cambiar de tema, el widget Logo del sitio queda vacío si no se copia ese valor.
- **SEO sólo optimiza con Hello.** La optimización de Gutenberg está atada a `hello-elementor` (`class-performance-manager.php:50`).
- **El asistente de inicio puede activar Hello.**
  - `elementor/app/modules/onboarding/module.php:384-396` llama a `switch_theme( 'hello-elementor' )`.
  - `onboarding-new/module.php:304-310` detecta temas `hello-*`; ahí existe el filtro `elementor/onboarding/is_elementor_theme_active`.
  - `install-theme.php:14` sólo permite instalar `hello-elementor`.

**Faltantes:**

- El tema y su actualizador.
- La clave `themes` en `digitalisimo-plugins.json`.
- La etapa de cambio de tema en Migración.
- El traslado de las asignaciones de Hello.
- Una prueba A/B en Playground que compare Hello con el tema propio.

## 3. Formatos de datos exactos

- **Plantilla de página** (meta `_wp_page_template`): `elementor_canvas`, `elementor_header_footer`, `elementor_theme`, `default` o la ruta de un archivo del tema.
  - Ancho y relleno de contenedor del kit: `container_width` y `container_padding` en `_elementor_page_settings`, dentro del documento del kit (opción `elementor_active_kit`).
  - Clase en `<body>`: `elementor-kit-{ID}` (`core/kits/manager.php:391-395`).
- **Theme Builder:**
  - Metas: `_elementor_template_type` (header, footer, single-post, single-page, archive, search-results, error-404, product, product-archive), `_elementor_location` (`documents/theme-document.php:22`) y `_elementor_conditions` (por ejemplo `['include/general','exclude/singular/page/12']`).
  - Caché en la opción `elementor_pro_theme_builder_conditions` (`classes/conditions-cache.php:15`), con forma `[ 'header' => [ 123 => [condiciones] ], … ]`.
  - El contenedor del documento puede ser `main`, `article`, `header`, `footer`, `section`, `aside` o `nav` (`theme-document.php:471-482`).
  - Las plantillas añaden al `<body>` la clase `elementor-page-{ID}` (`documents/theme-page-document.php:105-121`).
- **Custom Code:** tipo de contenido `elementor_snippet` con estas metas:
  - `_elementor_location`: `elementor_head`, `elementor_body_start` o `elementor_body_end` (`custom-code-metabox.php:18-20`).
  - `_elementor_priority`: de 1 a 10.
  - `_elementor_extra_options`: por ejemplo `ensure_jquery`.
  - `_elementor_code` y `_elementor_conditions`.
- **Valores que WordPress guarda por tema** (opción `theme_mods_{stylesheet}`): `nav_menu_locations` (`['menu-1'=>ID,'menu-2'=>ID]` en Hello), `custom_logo`, `custom_css_post_id` y `sidebars_widgets` (copia de seguridad que hace WordPress al cambiar de tema).
  - El CSS adicional es una entrada `custom_css` cuyo `post_name` es el nombre de la carpeta del tema.
- **Hello** (de memoria; hay que verificarlo):
  - Opciones con valor `'true'`: `hello_elementor_settings_description_meta_tag`, `_skip_link`, `_header_footer`, `_page_title`, `_hello_style` y `_hello_theme`. También `hello_theme_version`.
  - Ajustes del kit `hello_header_*` y `hello_footer_*`; el experimento `elementor_experiment-hello-theme-header-footer`.
  - Menús `menu-1` y `menu-2`; `$content_width = 800`; `add_post_type_support('page','excerpt')`.
  - Handles: `hello-elementor` (reset.css), `hello-elementor-theme-style` (theme.css), `hello-elementor-header-footer` y `hello-theme-frontend`.
- **Índice de la Release** (`scripts/release-manifest.mjs:12-18`): `{tag, release_url, generated, plugins:{slug:{version,file,url}}}`. Exige exactamente 7 entradas (`:18`).

## 4. Licencia y qué se puede reutilizar

- Elements es GPLv3 (`digitalisimo-elements/license.txt`). Hello Elementor es GPLv3 o posterior.
  - Se pueden copiar de forma literal `reset.css`, `theme.css` y `header-footer.css` de Hello si el tema DIGITALÍSIMO se publica como **GPL-3.0-or-later**.
  - Hay que conservar el aviso © Elementor Ltd. y anotar la procedencia en `docs/third-party-licenses.md`.
  - El nombre, la descripción y la captura del tema no pueden decir «Hello» ni «Elementor».
- Dokan (GPLv2 o posterior) es compatible, pero no conviene reutilizarlo.
- Divi: `LICENSE.md` dice GPLv2 o posterior (compatible con GPLv3), aunque la cabecera de `style.css` diga v2; aun así sólo se usan formatos y tablas de datos con atribución. Betheme es propietario: sólo se pueden leer sus formatos para migrar.

## 5. Riesgos

1. **Cambios visuales.** El reset de Hello define color de enlaces (#c36), botones, campos, márgenes de títulos y la tipografía del sistema en `body`. Donde el kit no define Theme Style, quitarlo cambia el diseño.
2. **Datos que se pierden al cambiar de tema:** `custom_logo` (deja vacío el Logo del sitio en las cabeceras), `nav_menu_locations` y el CSS adicional.
3. **Dependencias del tema hijo de Hello:**
   - La dependencia de estilo `hello-elementor-theme-style`: si falta, WordPress **no imprime** el CSS del hijo, sin aviso.
   - Funciones `hello_*`, constantes `HELLO_*`, clases `HelloTheme\*` y `include get_template_directory().'/includes/...'`.
   - `header.php` o `template-parts/*.php` sobrescritos en el hijo.
4. **Multisitio:**
   - Un hijo compartido por varios sitios no puede modificarse en su lugar: afectaría a sitios que aún no se migraron.
   - El tema debe habilitarse para la red.
   - En la red, la comprobación de actualizaciones corre en el sitio principal: si allí el tema no está activo, su actualizador no se ejecuta.
5. **Rendimiento:** SEO dejaría de retirar `wp-block-library` (`class-performance-manager.php:50`).
6. **Publicación:**
   - Un ZIP `digitalisimo-theme-x.zip` cumple el patrón de nombre de plugin y rompe la cuenta de 7 (`release-manifest.mjs:13-18`, `verify-release.mjs`).
   - Los catálogos recorren `manifest['plugins']` e instalarían el tema con `Plugin_Upgrader` (`digitalisimo-tools/includes/class-plugin-catalog.php:60,76`, `digitalisimo-seo/includes/class-plugin-catalog.php:93`).
7. **Meta description duplicada (posible, a verificar).** Hello imprime una meta description desde el extracto (`hello_elementor_add_description_meta_tag`) y SEO imprime la suya (`digitalisimo-seo/includes/class-seo.php:71`). SEO no retira la de Hello, así que puede haber dos ya hoy en sitios con Hello.
8. **Plugins y funciones atados a Hello (a verificar):**
   - El plugin Hello+ y sus widgets `ehp-*` exigen un tema Hello.
   - La cabecera dinámica de Hello (ajustes `hello_header_*`) se usa en sitios sin cabecera del Theme Builder.
9. **El nombre del tema no se puede cambiar después.** De él dependen `theme_mods_{slug}`, el CSS adicional y las opciones `template`/`stylesheet`.
10. **Error fatal al activar** el tema: toda llamada a Elements va protegida y se comprueba la portada antes de dar por terminado el cambio.

## 6. Recomendaciones de integración

### A. Archivos y hooks mínimos del padre `digitalisimo-theme/`

Es un tema clásico, no de bloques: con un tema de bloques el núcleo deja de retirar los estilos de Gutenberg (`elementor/modules/gutenberg/module.php:153-168,212-233`). No lleva `theme.json`, salvo que la paridad con Hello lo exija.

- **`style.css`:** sólo la cabecera, en formato ` * Version: 1.0.0` para que funcione el `sed` de `build-packages.sh`. Campos: `Theme Name: DIGITALÍSIMO`, `Requires at least: 6.8`, `Requires PHP: 7.4`, `License: GPL-3.0-or-later`, `Text Domain: digitalisimo-theme`, `Update URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme`. Sin CSS dentro: el padre nunca debe usar `get_stylesheet_uri()`.
- **`functions.php`:** sólo carga `inc/setup.php`, `inc/elements.php`, `inc/assets.php`, `inc/fallbacks.php`, `inc/class-updater.php` y, si existe la marca de migración, `inc/compat-hello.php`.
- **`inc/setup.php`** (en `after_setup_theme`), con paridad con Hello:
  - `register_nav_menus` con `menu-1` (Cabecera) y `menu-2` (Pie). Los mismos nombres hacen que `wp_map_nav_menu_locations` traslade los menús solo.
  - `add_post_type_support('page','excerpt')`: SEO usa el extracto.
  - `add_theme_support`: `title-tag` (obligatorio para `pre_get_document_title` de SEO, `class-seo-suite.php:25`), `post-thumbnails`, `automatic-feed-links`, `html5` (search-form, comment-form, comment-list, gallery, caption, script, style, navigation-widgets), `custom-logo` (100×350, flex), `align-wide`, `responsive-embeds`, `editor-styles`, `woocommerce` y `wc-product-gallery-zoom/lightbox/slider`.
  - `$GLOBALS['content_width']=800` con un filtro propio.
- **`inc/elements.php`:**
  ```php
  add_action( 'elementor/theme/register_locations', function ( $manager ) {
      if ( apply_filters( 'digitalisimo_theme_register_locations', true ) ) $manager->register_all_core_location();
  } );
  function digitalisimo_theme_location( $l ) { return function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( $l ); }
  ```
  Debe incluir también un `digitalisimo_theme_show_title()` que respete `hide_title` del documento, con la guarda `did_action( 'elementor/loaded' )`.
- **`header.php`:** `<!doctype html>`, `<html <?php language_attributes(); ?>>`, `<meta charset>`, `<meta name="viewport" content="width=device-width, initial-scale=1">`, `wp_head()`, `<body <?php body_class(); ?>>`, `wp_body_open()` **una sola vez**, el enlace para saltar al contenido (`#content`, con filtro) y `if ( ! digitalisimo_theme_location('header') ) get_template_part('template-parts/header');`.
  - Sin `<title>` y sin contenedores `#page`: un contenedor rompe sticky y las secciones estiradas.
- **`footer.php`:** `if ( ! digitalisimo_theme_location('footer') ) get_template_part('template-parts/footer'); wp_footer();` y luego `</body></html>`.
- **`index.php`, el despachador** (igual que Hello):
  - `is_singular()` llama a `single`.
  - `is_archive()||is_home()` llama a `archive`.
  - `is_search()` llama a `archive`.
  - En cualquier otro caso llama a `single`; así cubre el 404, que el gestor de ubicaciones trata como `single`.
  - Si la ubicación devuelve `false`, carga `template-parts/{single,archive,search,404}.php`.
  - **No** debe envolver la salida de la ubicación en `.site-main`.
- **`comments.php`:** lo necesita el widget de comentarios.
- **`template-parts/`:**
  - `header.php`/`footer.php` de respaldo: `the_custom_logo()`, nombre del sitio y `wp_nav_menu( menu-1|menu-2, fallback_cb=>false )`, con las clases `site-header` y `site-footer`.
  - `single.php` con `<main id="content" class="site-main">`, `.page-header` + `h1.entry-title` (hace funcionar `page_title_selector`), `.page-content`, `the_content()`, `wp_link_pages()` y `comments_template()`.
  - También `archive.php`, `search.php` y `404.php`.
- **Archivos que no deben existir:** `page.php`, `single.php`, `archive.php`, `404.php`, `woocommerce.php` (Woo lo usaría para todas sus páginas), la carpeta `woocommerce/`, `sidebar.php` y plantillas de página propias.
  - Una plantilla propia anula `single` (`locations-manager.php:238-247`) y rompe lo que supone `class-visitor-content.php:221-233`.
- **WooCommerce:** con declarar el soporte basta.
  - No tocar `woocommerce_output_content_wrapper`: su `main.site-main` hereda la regla de ancho igual que con Hello.
  - Las plantillas de producto y de archivo del Theme Builder ya se fuerzan desde Elements.
- **Canvas y Ancho completo:** salen de Elements. Al tema sólo le toca que `header.php`/`footer.php` funcionen solos y no abran contenedores.

### B. Estilos base y cómo acotarlos

- **Prioridad de carga:** encolar en `wp_enqueue_scripts` con prioridad 10. Elements encola en 20 (`includes/frontend.php:38,237`), así que el kit y los widgets siempre quedan por encima.
- **Modo nuevo (`base.css`):**
  - Reglas de elementos dentro de `:where()`, con especificidad 0: `box-sizing`, `body{margin:0}` e `img{max-width:100%;height:auto}`.
  - `.screen-reader-text` y `.skip-link:focus`.
  - Anchos sólo en `body:not([class*="elementor-page-"]) .site-main` y en los respaldos `.site-header`/`.site-footer`.
  - Prohibido: `overflow` en `html`/`body` (rompe sticky; ya lo resuelve Tools con `overflow-x:clip`, `class-site-options.php:108-111`), fuentes, `--e-global-*`, `.elementor*`, `.e-con*`, `.swiper*` y JavaScript.
- **CSS de cabecera y pie sólo cuando se imprime el respaldo:** `if ( ! function_exists('elementor_location_exits') || ! elementor_location_exits( 'header', true ) )`. Hello lo carga siempre.
- **Modo paridad (`assets/css/legacy-hello/`):**
  - Copia literal de `reset.css`, `theme.css` y `header-footer.css` de Hello 3.5.1, en el mismo orden y con los mismos selectores, sin `:where()`.
  - Se deriva del registro `digitalisimo_theme_switch` cuando `from=hello`; no es otra opción.
  - Respeta las opciones copiadas de Hello: `hello_style` apaga el reset y `hello_theme` apaga `theme.css`.

### C. Ceder ubicaciones sin duplicar

- Registrar las cuatro ubicaciones del núcleo con prioridad 10. Así `Theme_Support::after_register_locations` (prioridad 99) no pone su reemplazo con `ob_start` (`theme-support.php:41-68`).
- En cada punto hay que elegir: la ubicación o el respaldo, nunca los dos. Nunca envolver la cabecera del Theme Builder en `<header>`: el documento ya trae su etiqueta.
- `archive` siempre se fuerza a Ancho completo cuando hay plantilla (`:657`). La rama de archivo del tema sólo se usa si no hay plantilla.
- Ventanas emergentes, Custom Code, el GTM de SEO (`class-performance-tracking.php:88-89`), la barra de administración y las transiciones sólo necesitan `wp_head`, `wp_body_open` y `wp_footer`, una vez cada uno.
- No hace falta tocar `Theme_Support::init`: el tema también funciona con Elementor Pro original.

### D. Si Elements se desactiva

- No debe haber errores fatales: toda llamada a Elementor va protegida con `function_exists` o `did_action`.
- Se imprimen la cabecera y el pie de respaldo. El contenido sale de `post_content`, donde Elementor guarda una versión en texto plano (`includes/db.php:225-242`).
- `inc/fallbacks.php`, sólo si no ocurrió `elementor/loaded`:
  - Un `template_include` que lleve `elementor_canvas` a una plantilla propia sin cabecera ni pie, y `elementor_header_footer` a una de ancho completo.
  - Un aviso a quien tenga `activate_plugins`, sin promoción de Elementor.
- Hay que avisar en la documentación que Custom Code (rastreo) y los CSS del kit dejan de imprimirse.

### E. Empaquetado, versión y actualización

- **`scripts/build-packages.sh`:** añadir `digitalisimo-theme) build_package digitalisimo-theme style.css "$package" ;;`.
- **Workflows:** `changed-modules.sh` debe reconocer `digitalisimo-theme/*`. En `release.yml`: `paths:`, `php -l` y `files:`. En `validate.yml`, el mismo `php -l`.
- **Índice de la Release (`release-manifest.mjs`):** separar `THEMES = ['digitalisimo-theme']` en una clave nueva `themes:{...}` con la misma forma `{version,file,url}`. `plugins` sigue en 7.
  - Los actualizadores antiguos ignoran la clave nueva.
  - `verify-release.mjs` y `tests/validate-suite.mjs` necesitan su propia lista de temas.
  - La Release siempre incluye el ZIP vigente del tema, por la misma razón que los plugins: sólo se leen las 50 más recientes.
- **`inc/class-updater.php` en el tema** (WordPress ≥ 6.1 lee `Update URI` en temas). Copia la lógica de `digitalisimo-updater.php:156-212`, con la misma caché `digitalisimo_plugins_manifest`:
  ```php
  add_filter( 'update_themes_github.com', function ( $u, $data, $stylesheet, $locales ) {
      if ( 'digitalisimo-theme' !== $stylesheet ) return $u;
      $r = digitalisimo_theme_release(); // manifest['themes']['digitalisimo-theme'], validando la URL como :168
      return $r ? array( 'theme' => $stylesheet, 'version' => $r['version'], 'url' => $r['url'], 'package' => $r['package'], 'requires' => '6.8', 'requires_php' => '7.4' ) : $u; // ofrece, no fuerza
  }, 10, 4 );
  ```
  - Respaldo en `pre_set_site_transient_update_themes`: la entrada de `response['digitalisimo-theme']` es un **array** (`theme`, `new_version`, `url`, `package`), no un objeto como en los plugins.
  - `upgrader_source_selection` con `$hook_extra['theme']`, para conservar la carpeta. No se engancha `auto_update_theme`: la versión nueva se **ofrece**, sin forzar la actualización automática (plan aprobado).
- **Temas instalados pero inactivos, y la red:** ampliar `digitalisimo-sibling-updates.php` a `wp_get_themes()` filtrando por `UpdateURI` con el prefijo de este repositorio.
- **Catálogo de Tools:** una fila «Tema» con `Theme_Upgrader` leída sólo de `manifest['themes']`. En la red se instala desde la red; en un sitio de la red sólo se ve el estado.
- **Pruebas:** `tests/theme-structure.php`, `tests/theme-updater.php` y `tests/playground/theme-switch.sh`, copiada de `core-switch.sh`: Hello frente al tema DIGITALÍSIMO con el mismo contenido, sobre cabecera y pie, single, archivo, 404, búsqueda, Canvas, Ancho completo, tienda, producto, carrito y sliders, en tres anchos.

### F. Sin tema hijo (decisión del 2026-10-16)

El tema sale **sin hijo**. La propuesta original de este informe (un `digitalisimo-theme-child` con `style.css` y `functions.php` mínimos, y el traslado de un hijo de Hello copiando su carpeta) queda descartada:

- **El PHP propio del sitio** va al editor de fragmentos PHP de DIGITALÍSIMO Tools (F1b): por sitio o por red, guardado fuera de las carpetas del tema y del plugin, con revisión de sintaxis, prueba antes de activar, autodesactivación ante un error fatal, modo seguro, permiso de editar temas (superadministrador en red), `DISALLOW_FILE_EDIT`, nonce, historial y registro.
- **El CSS propio** va al CSS adicional o al kit.
- **Un tema hijo que el administrador cree por su cuenta** sigue funcionando; la API pública del tema (hooks `digitalisimo_*`, filtros y handles) es un contrato estable con `_deprecated_hook`.
- **Un hijo de Hello existente** no se traslada con el paso desde Hello. Cómo ofrecer su código como fragmentos desactivados (inventario sin ejecutar con `token_get_all( $code, TOKEN_PARSE )`, clasificación en resuelto, bloqueante o informativo) queda para el diseño de F1b.

Dónde va cada tipo de código, con o sin hijo:

| Código | Dónde va |
|---|---|
| PHP propio del sitio (hooks, filtros, funciones) | Editor de fragmentos de DIGITALÍSIMO Tools |
| Title, meta, canonical, schema, favicon, breadcrumbs | SEO |
| Píxeles y scripts de rastreo | Código personalizado (`elementor_snippet`), donde `class-performance-migration.php:50-55` los audita |
| CSS de widgets | CSS del elemento o del kit |
| Tipos de contenido, taxonomías y shortcodes usados en el contenido | Fragmentos de Tools o un plugin del sitio: deben sobrevivir a un cambio de tema |
| Cambios de acceso y redirecciones | Tools |
| Retiradas de estilos y scripts | SEO Rendimiento |
| Prioridades de `wp_head` entre 0 y 1 | Reservadas para SEO y Tools; usar 2 o más |

- **Snippets de terceros** (Code Snippets, WPCode): no dependen del tema. Basta revisarlos, sólo leyendo, por si llaman a `hello_*`.

### G. Asignaciones de Hello que hay que trasladar, de forma reversible

- **Qué copiar** de `theme_mods_{Hello o su hijo}` a `theme_mods_digitalisimo-theme`:
  - Todo el array, salvo `sidebars_widgets` y `custom_css_post_id`.
  - El CSS adicional se crea como entrada nueva con `wp_update_custom_css_post( $css, ['stylesheet'=>'digitalisimo-theme'] )`.
- **Opciones `hello_elementor_settings_*`:** se conservan en el registro `digitalisimo_theme_switch` (`hello_settings`) y el tema las respeta, con las mismas equivalencias (sin reset, sin `theme.css`, sin cabecera y pie, sin título, sin enlace para saltar). La meta description no se traslada: la publica sólo SEO.
- **Modo paridad:** el registro `digitalisimo_theme_switch` con `from=hello` activa la capa de compatibilidad y el modo paridad; no hay otra opción.
- **Cabecera dinámica de Hello:** si no hay cabecera ni pie en el Theme Builder (`get_documents_for_location()` vacío) y la cabecera de Hello estaba activa, se genera una plantilla header/footer.
  - Usa los widgets Logo del sitio, Título y Menú con `menu-1`/`menu-2`, y los ajustes `hello_header_*`/`hello_footer_*` del kit.
  - Lleva la meta `_digitalisimo_generated = hello_header` y se regenera la caché de condiciones.
- **Registro de la migración** (opción por sitio `digitalisimo_theme_switch`): `{time, from:{template,stylesheet,version}, to:{…}, mods_copied, custom_css:{from,to}, hello_settings, generated_templates}`.
- **Reversión:**
  - `switch_theme( from.stylesheet )`: los valores de Hello nunca se tocaron y WordPress devuelve los widgets solo.
  - Mandar a la papelera `generated_templates` y regenerar la caché de condiciones.
  - Marcar el registro como revertido.
  - Exponerla también por CLI.
- **Encaje (plan aprobado):** el paso desde Hello se ejecuta al activar el tema, de forma idempotente y también por CLI; no es una etapa del runner de Migración ni un botón nuevo.
  - En la red: la habilitación del tema es de alcance de red; el cambio de tema, en cada sitio.
  - Hello no se borra.
  - Al terminar, purgar cachés con `Compatibility::clear_3rd_party_cache()` (`elementor/includes/compatibility.php:57`). SEO invalida su auditoría de CSS solo (`class-performance-css-audit.php:19`).

### Cambios fuera del tema

| Plugin | Cambio |
|---|---|
| SEO | `class-performance-manager.php:50` acepta `digitalisimo-theme` mediante un filtro (y se actualiza `tests/performance-runtime.php`). SEO quita `hello_elementor_add_description_meta_tag` mientras Hello siga activo. |
| Elements | Filtro `elementor/onboarding/is_elementor_theme_active` que devuelve `true` con el tema DIGITALÍSIMO; cobertura de temas en `digitalisimo-sibling-updates.php`. |
| Tools | Fila del tema en el catálogo. |

**Qué es automático y qué no:**

- **Automático:** menús, logotipo, CSS adicional, opciones de Hello, soporte Woo, plantillas Canvas y Ancho completo, y la cabecera dinámica convertida en plantilla.
- **Pendiente (F1b):** el código propio de un hijo de Hello, que se ofrecería como fragmentos desactivados en Tools.
