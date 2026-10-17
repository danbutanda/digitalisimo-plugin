# Informe: Element Pack Pro 10.0.1 y Prime Slider Pro 4.7.0 frente a DIGITALÍSIMO Elements

> Revisión del 16 de octubre de 2026, depurada para el repositorio público. Ambos son GPLv3, pero su compatibilidad vive sólo en DIGITALÍSIMO Migración: el ZIP de Elements no puede contenerlos ni nombrarlos. Las copias de `reference/` se leen sin instalarlas (las reglas de uso están en `AGENTS.md`, «Fusión de constructores»). Donde el plan aprobado decidió otra cosa, manda `docs/FUSION_OBJECTIVE.md`; las diferencias están al final.

Lo más importante:
- En la versión 10.0.1 faltan 88 IDs de widgets que el inventario todavía cuenta (el changelog declara 85 movidos a otro plugin, el «Element Pack Addon»; la diferencia sólo se resuelve con su ZIP), y no hay ninguno nuevo.
- La Migración pierde sin avisar 28 extensiones de Element Pack, y tampoco convierte su Theme Builder, sus fuentes e iconos ni su Mega Menu.
- Prime Slider no tiene ningún soporte en el repositorio.
- El Slider Optimizado sólo mueve imágenes en desplazamiento continuo; para contener elementos editables necesita una variante anidada que conserve su ID y sus controles.

## 1. Qué hay, versión y formato

### 1.1 Element Pack Pro 10.0.1 (`reference/bdthemes-element-pack-2.zip`)
- **Versión y requisitos.** Según `bdthemes-element-pack/bdthemes-element-pack.php:1-15`: versión 10.0.1 del 13-oct-2026, licencia GPL3, WordPress ≥6.8, PHP ≥7.4. Probado con Elementor 4.0.0 a 4.3.4; nuestro núcleo es 4.3.5.
- **Contenido.** 2954 entradas y 214 directorios en `modules/`:
  - 177 archivos `modules/*/widgets/*.php` que registran 175 IDs.
  - 39 módulos sin widget: 38 extensiones más `modules/elementor`, que es una pestaña del kit.
- **Funciones fuera de los widgets:**
  - `abilities/`: 25 handlers de MCP y de la WordPress Abilities API, con endpoint `/wp-json/element-pack/mcp`.
  - `includes/builder/`: Theme Builder propio, CPT `bdt-template-builder`.
  - `includes/mega-menu/`: CPT `ep_megamenu_content`.
  - `includes/custom-fonts/`: CPT `ep_custom_font`.
  - `includes/custom-icons/`: CPT `bdt_custom_icons`.
  - `includes/swatches/variation-swatches.php`, `includes/template-library/`, `includes/class-content-guard.php` y `includes/class-asset-combiner.php`.
  - UIkit propio (`bdt-uikit.min.js`).
- **Diferencia con el inventario.** `docs/element-pack-inventory.md` registra 263 IDs de la 9.9.1. El changelog de la 10.0.0 dice: «85 deprecated widgets moved to the new Element Pack Addon plugin, with the same names». La comparación estática da 88 IDs ausentes en 10.0.1 y 0 IDs nuevos:
  - bbPress 12, BuddyPress 3, Charitable 8, Crypto 8, EDD 15, Give 10.
  - Familia de entradas 14: post-block, post-block-modern, post-card, post-comments, post-content, post-featured-image, post-gallery, post-grid, post-grid-tab, post-info, post-list, post-slider, post-title y single-post.
  - Otros 18: age-gate, air-pollution, call-out, carousel, dark-mode, download-monitor, dropbar, everest-forms, fancy-card, honeycombs, image-accordion, layer-slider, member, news-ticker, slinky-vertical-menu, tags-cloud, thumb-gallery y timeline.
  - 7 están en el bloque prioritario: Call Out, Fancy Card, Tags Cloud, Post Grid, Post List, Single Post y Slinky Vertical Menu.
- **El Addon.** Vive en `../bdthemes-element-pack-addon` y no está en `reference/`. Usa los traits de Pro (`traits/global-controls-functions.php:28972-28976`) y se registra con los filtros `element_pack/modules/external_sources` y `element_pack/modules/settings_fields` (`admin/module-settings.php:2887-2930`).
- **Controles: sin renombres.** Todas las claves `defaults` de `digitalisimo-migration/legacy/maps/bdt-*.php` de los 175 IDs que siguen existen en el código 10.0.1 (widget, skins o traits). Sólo faltan claves derivadas de `Group_Control_Image_Size` (`image_size_size`), que es lo esperado.
- **Opciones y figuras sin cambios.** Siguen en uso las opciones que lee la Migración: `element_pack_elementor_extend`, `element_pack_active_modules`, `element_pack_third_party_widget` y `element_pack_api_settings`. Las figuras siguen en `assets/images/mask` (34), `divider` (46) y `shape-divider` (15), donde las busca `class-masks.php:104`.
- **Clases CSS mal mapeadas.** Seis mapas declaran en `classes` nombres que no existen en 10.0.1. Los nombres reales son:

| Mapa | Nombres reales en 10.0.1 |
| --- | --- |
| `bdt-fancy-slider` (mapea `-sub-title` y `-description`) | `-subtitle`, `-text` |
| `bdt-trailer-box` | `-pre-title`, `-desc`, `-text`, `-button` |
| `bdt-advanced-progress-bar` | `-fill`, `-parcentage`, `-name`, `-level` |
| `bdt-animated-card` | `-btn`, `-img`, `-text`, `-sub-title` |
| `bdt-interactive-card` | `-text` |
| `bdt-profile-card` | `-name-info` |

- **Pestaña del kit (desde 9.9.2).** La pestaña `element-pack-global-style` (`modules/elementor/module.php:37-50`) guarda en el `_elementor_page_settings` del kit las claves `element_pack_lightbox_bg`, `element_pack_cb_*`, `element_pack_toolbar_*`, `element_pack_navigation_*` y `element_pack_global_tooltip_*`.

### 1.2 Prime Slider Pro 4.7.0 (`reference/bdthemes-prime-slider.zip`)
- **Versión y requisitos.** 346 archivos, GPLv3, Elementor 4.0.0 a 4.3.3.
- **Necesita la versión Lite, que no está en `reference/`.** Pro espera `bdthemes-prime-slider-lite/bdthemes-prime-slider.php`, la acción `bdthemes_prime_slider_lite/init` y Lite ≥4.5.2 (`BDTPS_CORE_REQUIRED_VERSION`). Lite aporta:
  - Los traits `PrimeSlider\Traits\Global_Widget_Controls` y `QueryControls\GroupQuery\Group_Control_Query`.
  - Los `register_*_controls`, que definen la mayoría de las claves de los repetidores.
  - El handle `bdt-uikit`.
  - Las imágenes de ejemplo `BDTPS_CORE_ASSETS_URL.'images/gallery/item-*.svg'`.
- El `src/` que cita el readme no viene en el ZIP.
- **Widgets.** 34 IDs, todos `extends Widget_Base` (ninguno anidado) y sin `_skin`:

| Motor | IDs |
| --- | --- |
| Swiper de Elementor (`elementorFrontend.utils.swiper`) | avatar, cards-stack, cloud, coddle, escape, event-calendar, `prime-slider-expo-slider`, fortune, knily, marble, material, panorama, super-flow, titanic, woohotspot (monster mezcla Swiper y UIkit) |
| UIkit `bdt-slideshow` | custom, flexure, fluent, paranoia, wooexpand, woostand |
| JS propio, GSAP, anime o demos de Codrops | astoria, crossroad, motion, pandora, pieces, prism, reveal, ring, turnar |
| Controles remotos | remote-arrows, remote-fraction, remote-pagination, remote-thumbs |

- **Extensiones:**
  - Adaptive BG (`modules/adaptive-bg`, claves `ps_widget_abg`, `ps_widget_abg_parent`). Su script apunta a `js/adaptive-bg.js` (`module.php:64,69`), pero el archivo real es `js/modules/ps-adaptive-bg.min.js`: está roto.
  - Advanced Animation y Reveal sobre widgets de Lite (`includes/class-animation-extension.php:29-110`). Ahí aparecen IDs de Lite: `prime-slider-general`, `blog`, `dragon`, `isolate`, `sequester`, `mount`, `flogia`, `woocommerce`, `woolamp`; `adaptive-bg` cita además `pacific`, `mercury`, `tango` y `vertex`.
  - Efectos de Swiper adicionales (`includes/class-swiper-effects.php:17-73`): shutters, slicer, tinder y gl, con 19 sombreadores GL.
- **Librerías incluidas** (`assets/vendor/js`): GSAP 3.12.5, SplitText 3.3.0, anime 3.2.1, RevealFx, Splitting, Charming, Popper, Tippy 6.3.1 y ocho efectos de UI Initiative.

### 1.3 Estado actual del repositorio
- **No hay soporte de Prime Slider.** La única mención es un texto en `digitalisimo-migration/legacy/maps/bdt-marquee.php:38`. Además:
  - `class-translator.php:19` sólo acepta `bdt-*`, `fooevents-calendar` y `lightbox`.
  - Las búsquedas de documentos (`class-migration.php:307`) no buscan `prime-slider-`.
  - `class-retire.php:17-23` no incluye Prime Slider ni el Addon.
  - No hay fixtures de Prime Slider en `tests/legacy/` (193 archivos).
- **Los scripts de análisis leen una carpeta que ya no existe**, `reference/bdthemes-element-pack/`: `scripts/inventory-element-pack.py:10`, `legacy_controls.py:11` y `legacy-styles.py:20`. Si se regenera el inventario con 10.0.1 quedan 175 IDs y falla `tests/elements-legacy-decisions.mjs:10`, que exige `inventory.length === map.length` (263).

## 2. Capacidades completas, parciales y faltantes

### 2.1 Widgets de Element Pack (fuente: `docs/element-pack-decisions.md`)
Las decisiones son 186 ADAPTAR, 14 RECONSTRUIR, 6 COMPARTIR y 57 DESCARTAR. En total, 201 IDs tienen adaptador y 62 no se reproducen.

- **Completas con widget propio `digitalisimo-*`:** accordion, advanced-button, advanced-divider, advanced-heading, advanced-icon-box, animated-heading, animated-link, audio-player, brand-carousel, brand-grid, breadcrumbs, business-hours, call-out, chart, comparison-list, content-switcher, creative-button, custom-gallery, device-slider, document-viewer, dual-button, fancy-card, fancy-icons, fancy-list, fancy-slider, fancy-tabs, featured-box, google-reviews, icon-mobile-menu, iconnav, image-compare, logo-carousel, logo-grid, marquee, news-ticker, notification, offcanvas, open-street-map, product-grid, advanced-progress-bar y progress-pie, qrcode, reading-progress, reading-timer, scroll-button, table, tags-cloud, timeline, total-count, user-register, vertical-menu, video-player y video-gallery.
- **Completas con un widget de PRO o de Elementor:**

| Element Pack | Destino |
| --- | --- |
| slider | `slides` |
| countdown, flip-box | `countdown`, `flip-box` |
| advanced-counter | `counter` |
| post-* | widgets `theme-*` |
| search, social-share | `search-form`, `share-buttons` |
| table-of-content | `table-of-contents` |
| user-login | `login` |
| price-list, price-table | los de PRO |
| lottie | `lottie` |
| navbar | `nav-menu` |
| dynamic-grid, dynamic-carousel | `loop-grid`, `loop-carousel` |
| contact-form, webhook-form, mailchimp | `form` |
| marker | `hotspot` |
| review-card* | `reviews` |
| wc-* | widgets de WooCommerce |
| source-code | `code-highlight` |
| advanced-gmap | `google_maps` |
| 54 integraciones | `shortcode` |

- **Parciales (se convierten a otro widget, con diferencias declaradas):**

| Element Pack | Destino |
| --- | --- |
| animated-card, interactive-card, member, trailer-box | Fancy Card |
| hover-box, image-expand, interactive-tabs, static-grid-tab | Fancy Tabs |
| image-accordion, toggle, edd-tabs | Acordeón |
| panel-slider, slideshow, static-carousel, product-carousel | Fancy Slider |
| carousel, portfolio-*, testimonial-*, thumb-gallery | `posts` en rejilla |
| image-magnifier, scroll-image, svg-image | `image` |
| image-stack | `image-gallery` |
| modal, dropbar, lightbox | Panel lateral |
| step-flow | Caja de icono avanzada |
| scrollnav, sub-menu, slinky | Menú vertical |
| custom-carousel | `media-carousel` |

- **Faltantes que siguen en 10.0.1 (DESCARTAR):** advanced-calculator, barcode, changelog, circle-info, circle-menu, comment, cookie-consent, coupon-code, floating-knowledgebase, helpdesk, horizontal-scroller, instagram, protected-content, remote-arrows/fraction/pagination/thumbs, social-proof, stacker, svg-blob, svg-maps, threesixty-product-viewer, time-zone, weather, twitter-*, facebook-feed*, learnpress-*, tutor-lms-course-carousel, event-* y fooevents-calendar-carousel.
- **COMPARTIR sin conversión:** mega-menu, faq, edd-product, fooevents-calendar-events y tutor-lms-course-grid.

### 2.2 Extensiones de Element Pack
- **Tratadas (12)** según la sección `extensions` de `docs/migration-map.json`: visibility-controls, wrapper-link, backdrop-filter, text-gradient-background, realistic-image-shadow, floating-effects, notation, shape-builder, duplicator, svg-support, image-masking y shape-divider.
- **Sin inventario ni tratamiento (28):** tooltip, transform-effects, parallax-effects, background-parallax, background-image-parallax, image-parallax, background-overlay, background-expand, section-sticky, particles, equal-height, cursor-effects, reveal-effects, ripple-effects, scroll-fill-effect, scroll-box, threed-text, tile-scroll, grid-line, global-badge, hash-link, image-hover-effects, confetti-effects, sound-effects, animated-gradient-background, content-protector, custom-js y adblock-detector.
- **Qué pasa hoy al convertir:**
  - `class-native.php:328-334` borra todas las claves `ep_*`, `element_pack_*` y `bdt_*`. Se pierden sin aviso, por ejemplo, `element_pack_widget_tooltip*`, `element_pack_widget_effect_*`, `ep_parallax_effects_*`, `element_pack_cursor_*`, `ep_threed_*`, `ep_custom_js` y `ep_global_badge_*`.
  - Las claves sin prefijo (`section_sticky_*`, `section_particles_*`, `section_equal_height_*`, `section_parallax_*`) quedan huérfanas y sin efecto.
- **Plataforma sin tratar:** el Theme Builder (CPT `bdt-template-builder`), el contenido del Mega Menu (`ep_megamenu_content`), las fuentes (`ep_custom_font`), los iconos (`bdt_custom_icons`), los swatches, la pestaña del kit y la biblioteca de plantillas. Todas existen desde 9.x según el changelog (9.0.0 fuentes e iconos, 9.4.0 Theme Builder), así que el hueco no es nuevo. La búsqueda en `digitalisimo-migration/` y `docs/` no encuentra ninguna.

### 2.3 Prime Slider frente a lo que ya existe

| Capacidad de Prime Slider | Slider Optimizado | `digi-carousel` (Fancy, Device, Logos) | PRO: `nested-carousel` / `slides` / `media-carousel` |
| --- | --- | --- | --- |
| Diapositivas con plantillas de Elementor (`custom`) o anidadas | No (sólo imágenes) | No | `nested-carousel` anidado; `slides` con `digitalisimo_template_id` (`class-slides-templates.php:13-79`) |
| Transiciones: slide, fade, scale, push, pull (UIkit); fade, cube, coverflow, flip, cards, creative 1-5 (Swiper); shutters, slicer, tinder, GL | Sólo desplazamiento continuo (marquesina CSS) o estático | Sólo scroll-snap | `slides`: slide y fade; `media-carousel`: slide y fade (`media-carousel.php:842`) y la skin coverflow; `nested-carousel`: sólo slide |
| Autoplay, intervalo y pausa al pasar el cursor | Continuo; pausa sólo con hover | No hay | Sí |
| Flechas, puntos, fracción, progreso, miniaturas | No | Sólo flechas | Sí; miniaturas en `media-carousel` (slideshow) |
| Bucle y rewind | Bucle continuo | No | Sí |
| Contenido dinámico: WP_Query (fluent, knily, marble, material), productos (wooexpand, woostand), `tribe_get_events` (`event-calendar.php:1780`) | No | No | `loop-carousel`, `woocommerce-products` |
| Táctil | No aplica | Scroll nativo | Swiper |
| Teclado | No | Scroll nativo del viewport (`tabindex=0`) | Swiper a11y |
| Movimiento reducido | Sí (`slider-optimizado.css`) | Sí | Parcial |
| Botón de pausa (WCAG 2.2.2) | No (la Marquesina sí: `class-marquee.php:80`) | No aplica | No |
| Controles remotos (`remote_id`) | No | No | No (su JS busca cualquier `.swiper`) |
| Texto animado con GSAP o SplitText, Reveal, fondo adaptativo | No | No | Sólo `content_animation` de `slides` |

**Lo que le falta al Slider Optimizado** respecto a Prime Slider: contenido distinto de imágenes (texto, botones, plantillas, contenedores), modo por diapositivas con autoplay e intervalo, transiciones fade y scale, flechas, puntos y fracción, teclado y deslizamiento en modo paginado, contenido dinámico, botón de pausa y pausa con `:focus-within`, y controles remotos.

**Calidad de Prime Slider que no conviene copiar:**
- Ningún CSS ni JS respeta `prefers-reduced-motion` (revisé todos).
- Las flechas son `<div>` sin rol ni foco (`coddle.php:1675`) o `<a href="#">` (`custom.php`).
- Usa `<img src>` sin `srcset`, ancho ni alto (`titanic.php:1245-1246`) y `background-image` sin ALT (crossroad, flexure, paranoia, prism, reveal, coddle, wooexpand, woostand).
- Astoria, crossroad, pandora, prism, reveal y motion no tienen gestos táctiles.

## 3. Formatos de datos exactos

**Prime Slider en `_elementor_data`.** `widgetType` = `prime-slider-<id>`, salvo Expo, que es `prime-slider-expo-slider`. Ejemplo de `custom` (`custom.php:160-187` y `488-560`):
```json
{"elType":"widget","widgetType":"prime-slider-custom","settings":{
 "sliders":[{"_id":"a1","tab_title":"Slide #1","template_id":"1234"}],
 "show_navigation_arrows":"yes","show_navigation_dots":"yes","show_social_icon":"","scroll_to_section":"","section_id":"#x",
 "finite":"yes","autoplay":"yes","autoplay_interval":7000,"pause_on_hover":"","velocity":{"size":1},
 "slider_animations":"slide|fade|scale|push|pull","arrows_prev_position":"center-left","arrows_next_position":"center-right","dots_hv_position":"bottom-center"},"elements":[]}
```
- `finite`="yes" significa bucle: el código lo invierte (`"finite" => $settings["finite"] ? false : true`, alrededor de la línea 1509), y el render imprime `bdt-slideshow` con JSON.
- La plantilla se imprime con `get_builder_content_for_display( template_id )` (`custom.php:1686`).

**Skins con Swiper** (por ejemplo `coddle.php:1541-1590`):
- Repetidor `slides[]` con `title`, `sub_title`, `title_link{url,is_external,nofollow}`, `image{id,url}`, `text` y `lightbox_link`. El botón viene de un trait de Lite: nombres por confirmar.
- Ajustes: `autoplay`, `autoplay_speed`, `loop`, `rewind`, `speed{size}`, `swiper_effect`, `gl_shader`, `creative_effect` (creative-1 a 5), `mousewheel`, `grab_cursor`, `pauseonhover`, `observer`, `show_navigation_arrows`, `show_navigation_fraction`, `pagination_direction`, `thumbs_show_title`, `title_html_tag`.
- Algunas skins usan claves propias: `expo_slides[]` (`expo_title`, `expo_image`, `expo_link`), `turnar_items[]`, `ring_slides[]`, `list[]` (motion), `cards-stack` (`image_front`, `image_back`).
- Consultas: `posts_limit` más las claves de `Group_Control_Query` de Lite (`fluent.php:1371-1390`).
- Controles remotos: `remote_id` (selector), `loop_status`, `thumbs_type` y `thumbs_list[]`.
- Animación: `animation_status`, `animation_of`, `reveal_effects_enable/color/direction/easing/speed`, `animation_on`, `animation_options`, `anim_perspective/duration/scale/transform_origin`.
- Opciones: `prime_slider_active_modules`, `prime_slider_elementor_extend`, `prime_slider_third_party_widget`, `prime_slider_other_settings` y `prime_slider_license_key_s<crc32>`.

**Theme Builder de Element Pack** (`includes/builder/meta.php:16-27`, `template-conditions.php:101-130`):
- Post type `bdt-template-builder`.
- Meta `_bdthemes_builder_template_type` con valores `themes|header`, `themes|footer`, `post|single`, `page|single`, `post|archive`, `page|search`, `page|404`, `product|single`, `product|shop`, `product|cart`, entre otros.
- Meta `_bdthemes_builder_template_conditions`: JSON de filas `{polarity:include|exclude, condition_a:entire_site|singular|archive|woocommerce, condition_singular, condition_archive, condition_woo, condition_singular_id}`.

**Fuentes de Element Pack:** CPT `ep_custom_font` con meta `ep_font_files` y `ep_font_face_css`. PRO usa `elementor_font` con la taxonomía `elementor_font_type` (`modules/assets-manager/asset-types/fonts-manager.php:29-35`) e iconos en `elementor_icons`.

**Slider Optimizado actual** (`class-widget.php:18-57`):
```json
{"widgetType":"digitalisimo-slider-optimizado","settings":{"mode":"multiple|repeat","gallery_images":[{"id":12,"url":"…"}],
 "images":[{"_id":"x","image":{"id":13},"alt":"","link":{"url":"","is_external":"","nofollow":""}}],"repeat_image":{},"repeat_alt":"","repeat_count":10,
 "mirror":"","label":"","animation":"continuous|off","direction":"left|right","speed":30,"infinite":"yes","pause":"",
 "visible":9,"visible_tablet":6,"visible_mobile":3,"gap":{"size":16,"unit":"px"},"height_mode":"auto|fixed","object_fit":"contain","natural_width":"","image_size":"large","loading":"auto","priority":"auto"},"elements":[]}
```

**`nested-carousel`** (`nested-carousel.php:150-170` y `326-390`): los hijos `elements[]` son contenedores en el mismo orden que el repetidor.
```json
{"widgetType":"nested-carousel","settings":{"carousel_name":"Carousel","carousel_items":[{"_id":"…","slide_title":"Slide #1"}],"slides_to_show":"1","autoplay":"yes","infinite":"yes","arrows":"yes","pagination":"bullets"},
 "elements":[{"elType":"container","settings":{"_title":"Slide #1"},"elements":[…]}]}
```

## 4. Licencias y qué se puede reutilizar
- **Código PHP, CSS y JS de Element Pack y Prime Slider (GPLv3).** Se puede reutilizar con atribución en `docs/third-party-licenses.md`. Hay dos límites:
  - Elements no puede contenerlo ni nombrarlo: `scripts/build-packages.sh:48` aborta ante `bdthemes`, `element pack`, `bdt-` o `bdt_`, y `ep_`. Prime Slider usa clases `bdt-`, así que toda su compatibilidad tiene que vivir en Migración.
  - Nunca se reutilizan el cargador principal ni el licenciamiento de ninguno de los dos.
- **Efectos de UI Initiative** (shutters, swiper-gl, slicer, tinder, cards-stack, expo, panorama, material): llevan «UI Initiative Regular License» y el readme dice «licensed to BdThemes… distributed only with this paid plugin». No son redistribuibles; no se pueden usar.
- **GSAP 3.12.5 y SplitText:** licencia estándar de GreenSock, no GPL. Las condiciones actuales parecen limitar su uso en herramientas que compiten con Webflow (constructores visuales); no lo verifiqué. Además añadiría otra librería, así que no se usa.
- **Licencias MIT:** anime.js, Tippy, Popper, Splitting y Charming (RevealFx viene de Codrops, sin cabecera; hay que verificarlo). Ninguna hace falta.
- **Swiper 8.4.5 (MIT)** ya viene en el núcleo (`elementor/assets/lib/swiper/v8/swiper.min.js`) con fade, cube, coverflow, flip, cards, creative, parallax, thumbs, keyboard, a11y y controller. Usarlo no añade librería, pero pesa 144 KB según `docs/ELEMENTS_FRONT_OBJECTIVE.md:42`.
- **Las imágenes de ejemplo de Prime Slider** salen de la carpeta de Lite: al retirar el plugin dan 404.

## 5. Riesgos
1. **Cabeceras y pies de página desaparecen.** Un sitio con el Theme Builder de Element Pack se queda sin header o footer al desactivar Element Pack. Nada lo convierte.
2. **Efectos que se pierden sin aviso.** Sticky, transform, parallax, tooltip, gradiente animado, etc., por la línea `class-native.php:328-334`.
3. **Fuentes e iconos propios.** Si se usan `ep_custom_font` o `bdt_custom_icons`, la tipografía cae al respaldo y los iconos se quedan vacíos.
4. **Sitios con Element Pack 10.x.** Sin el Addon instalado, los documentos con los widgets que faltan en 10.0.1 ya se ven rotos antes de migrar. Con el Addon, `Retire` no lo desactiva. Por el nombre, el Addon parecería quedar inactivo sin Pro, pero no está verificado.
5. **CSS personalizado sin reescribir.** Las reglas que apuntan a las clases reales de Element Pack en los seis mapas de §1.1 no se reescriben.
6. **El A/B de regresión es de la 9.9.1** (`docs/ELEMENT_PACK_MIGRATION_OBJECTIVE.md:147`). Nadie probó la 10.0.1, cuyo changelog anuncia «Accessibility improvements for all widgets»; podría haber cambios de marcado sin cambios de controles.
7. **Prime Slider:**
   - La Migración no lo detecta.
   - Si se retira Elementor, Prime Slider sigue activo sobre el núcleo incluido: no se rompe, pero carga UIkit y su CSS.
   - Las claves de Lite no se pueden mapear sin una copia de Lite.
8. **Slider Optimizado anidado:**
   - El experimento `container` está inactivo por defecto en sitios instalados antes de la 3.16 (`elementor/core/experiments/manager.php:372-387`), y `nested-elements` depende de él. Cambiar la clase base sin una variante de respaldo podría romper el registro en esos sitios.
   - Si un sitio con hijos anidados pierde `nested-elements`, `Widget_Base` no pinta los hijos: se dejan de ver, aunque los datos se conservan.
   - Clonar el contenido para el bucle duplica IDs y enlaces enfocables, y hace que los buscadores lean el contenido dos veces.

## 6. Recomendaciones de integración

### 6.1 Element Pack 10.x, todo en DIGITALÍSIMO Migración
- **Inventario.** No regenerar desde la 10.0.1 sola. Añadir a `docs/element-pack-inventory.json` el campo `moved_to_addon: true` en los 88 IDs. Hacer que `scripts/inventory-element-pack.py`, `legacy_controls.py` y `legacy-styles.py` lean el ZIP directamente (con `zipfile`, sin extraer) y unan 9.9.1 y 10.0.1.
- **Retirada.** En `legacy/class-retire.php:17` añadir `'bdthemes-element-pack-addon.php' => ['name'=>'/^Element Pack/i','label'=>'Element Pack Addon']` (el nombre del archivo está por confirmar), desactivado antes que Pro.
- **Clases.** Corregir `classes` en los seis mapas con los nombres de §1.1.
- **Extensiones.** Añadir una sección a `docs/migration-map.json` (`extensions`) y a `legacy/class-native.php` (`ELEMENT_KEYS` y `element_settings()`):

| Extensión | Tratamiento |
| --- | --- |
| section-sticky | Automático a `sticky`, `sticky_offset`, `sticky_on` y `sticky_effects_offset` de PRO (`modules/sticky`) |
| transform-effects | Automático a `_transform_*` del núcleo (`element-base.php:870-931`), normal y hover |
| parallax-effects, background-parallax | Aproximado a `motion_fx_*` de `modules/motion-fx` |
| equal-height | CSS personalizado |
| background-overlay | Superposición nativa del contenedor |
| animated-gradient-background | CSS personalizado con `@keyframes` |
| tooltip | Nuevo rasgo genérico `Element_Tooltip` en `modules/digitalisimo-widgets/`, siguiendo el patrón de `class-element-link.php` |
| custom-js | Nunca automático (seguridad) |
| Resto | DESCARTAR con motivo |

  Todo lo que se pierda debe aparecer en el informe de `class-runner.php:571` antes de convertir, no borrarse en silencio.
- **Theme Builder, fuentes, iconos y kit.** Nueva etapa `stage_theme_builder` en `class-runner.php` y nuevo `legacy/class-theme-builder.php`:
  - Convertir `bdt-template-builder` en `elementor_library` con `_elementor_template_type` (header, footer, single-post, single-page, archive, search-results, error-404, product, product-archive) y `_elementor_conditions` (`include/general`, `include/singular/...`, `include/archive`, `include/product_archive`), con respaldo y reversión. Es automático para las filas estándar.
  - `ep_custom_font` pasa a `elementor_font` (automático, conviene verificar).
  - `bdt_custom_icons` pasa a `elementor_icons` (hay que verificar los paquetes).
  - `ep_megamenu_content` y los menús: sólo informe.
  - Borrar las claves `element_pack_*` del kit (son huérfanas).
- **Validación.** Repetir `tests/playground/legacy-ab.sh` con la 10.0.1 y con el Addon.

### 6.2 Prime Slider en Migración (no se instala otro plugin)
- **Detección:**
  - `class-translator.php:19`: añadir `prime-slider-[a-z0-9-]+` y una función `prime_slider_active()` (`defined('BDTPS_PRO_VER')` o la constante de Lite, por confirmar).
  - `class-migration.php:307`: añadir las búsquedas `"widgetType":"prime-slider-`, `"ps_widget_abg"`, `"animation_status"` y `"reveal_effects_enable"`.
  - `class-retire.php`: añadir `'bdthemes-prime-slider.php' => ['name'=>'/^Prime Slider/i']`. Pro y Lite comparten el nombre del archivo, así que una entrada cubre los dos.
- **Mapas.** `maps/prime-slider-*.php` con el formato de `bdt-slider.php`. Primero hay que añadir Lite a `reference/` para sacar sus valores por defecto.
- **Destinos:**

| Prime Slider | Destino | Conversión |
| --- | --- | --- |
| `custom` | `slides` con `digitalisimo_template_id` (rasgo existente; slide y fade) | Automático; scale, push y pull pasan a fade |
| Héroes con repetidor (astoria, avatar, coddle, escape, fortune, monster, paranoia, pieces, titanic, flexure, reveal, prism, crossroad) | `slides` (title→heading, text→description, image→background, title_link→link) | Automático; los efectos 3D, GL y GSAP no se trasladan |
| Carruseles con varias diapositivas visibles (cloud, panorama, expo, cards-stack, super-flow) | `media-carousel` (coverflow) o `digitalisimo-fancy-slider` | Automático |
| coddle y knily con miniaturas | `media-carousel` en skin slideshow | Automático |
| Entradas (fluent, knily, marble, material) | `loop-carousel` con una plantilla «Loop Item» generada (precedente del lote 30) | Automático |
| wooexpand, woostand | `woocommerce-products` en carrusel | Automático |
| woohotspot | `nested-carousel` con un `hotspot` por diapositiva | Parcial |
| event-calendar | `loop-carousel` sobre `tribe_events` | Parcial |
| ring, turnar, motion, pandora | Fancy Slider | Aproximado |
| Remotos y Adaptive BG | DESCARTAR, con informe | — |

- Las clases `bdt-*` de Prime Slider van sólo en estos mapas, nunca en Elements.

### 6.3 Slider Optimizado con elementos editables (sin otra librería)

> **Superado en parte por el plan.** No se cambia la clase ni el ID del Slider Optimizado: una variante anidada con el mismo nombre no es viable (el soporte de anidación se decide por tipo de widget y los documentos guardados no tienen hijos). El contenido anidado va a un widget **nuevo** que comparte las hojas y scripts del Slider Optimizado y del motor de carrusel propio (F7b). Siguen valiendo el render del modo contenido, el modo por diapositivas, la accesibilidad y las pruebas de esta sección.
1. **Dos variantes con el mismo ID y los mismos controles.**
   - Extraer controles e imágenes de `modules/digitalisimo-slider/class-widget.php` a un trait (`trait-slider-optimizado.php`).
   - Mantener `Elementor_Slider_Widget extends \Elementor\Widget_Base` como respaldo.
   - Añadir `class-widget-nested.php` con `Elementor_Slider_Nested_Widget extends \Elementor\Modules\NestedElements\Base\Widget_Nested_Base`. Es una clase del núcleo, así que no depende de PRO.
   - `class-module.php:48-57` registra la variante anidada sólo si `experiments->is_feature_active('nested-elements')`.
   - Los datos tienen la misma forma (`elements: []` en los modos de imagen), así que los documentos guardados no cambian.
2. **Controles nuevos sin tocar los existentes.**
   - Opción `mode` = `content` («Contenido editable»), con `multiple` como valor por defecto.
   - `content_items` de tipo `Control_Nested_Repeater::CONTROL_TYPE` (`elementor/modules/nested-elements/controls/control-nested-repeater.php:16`) con el campo `item_title`, condición `mode=content`.
   - `get_default_repeater_title_setting_key()` devuelve `item_title`.
   - Probar en el editor si `get_default_children_elements()` puede devolver `[]`. Si el editor necesita hijos por defecto, que la variante anidada sólo aparezca en el panel cuando se elige el modo contenido.
3. **Render en el modo contenido:**
   - `print_child($i)` dentro de cada `.digi-slider__item` del primer grupo.
   - El clon del bucle lo crea `assets/js/slider-optimizado.js` con `cloneNode`, `inert` y `aria-hidden`, quitando `id`, `for` y `aria-controls`. El servidor no lo genera.
   - En el editor no hay clon ni animación (clase `digi-slider--editing` con scroll), para que los hijos se puedan editar.
   - `get_initial_config()` con `target_container => ['.digi-slider__group']`, `node => 'div'`, `is_interlaced => true` y `support_improved_repeaters => true`, más `content_template()` y `content_template_single_repeater_item()`, siguiendo `nested-carousel.php:383-474`.
   - `fit()` (`slider-optimizado.js:50-88`) ya funciona con hijos de cualquier tipo; `data-digi-sources` = número de hijos.
4. **Modo «Por diapositivas»** como nuevo valor de `animation`.
   - Reutilizar el marcado de `Carousel_Engine` (`class-carousel-engine.php:7-21`).
   - Ampliar `assets/js/carousel-engine.js` con opciones que sólo se activan por atributos `data-digi-carousel-*`: autoplay con intervalo, pausa al pasar el cursor, al enfocar y con la pestaña oculta, botón Pausar, puntos (`button` con `aria-current`), fracción, bucle, flechas izquierda y derecha en el viewport, y fade (CSS con rejilla apilada y gesto con pointer).
   - Hoja de fade aparte, como las de `Widget_Registry::VARIANT_STYLES` (`class-registry.php:243`).
   - Los scripts se declaran por instancia, según la regla de `docs/ELEMENTS_FRONT_OBJECTIVE.md:11`.
   - Logos, Fancy Slider, Device Slider y Productos no cambian, porque las opciones nuevas sólo se activan si el atributo está presente.
5. **Accesibilidad:**
   - Control `pause_button` (WCAG 2.2.2), con el patrón de `class-marquee.php:80`.
   - Pausa con `:focus-within`.
   - `role=group` y `aria-label="n de N"` por diapositiva, como `nested-carousel.php:358-369`.
6. **Pruebas.** Mantener `tests/elements-slider-registration.php`, `elements-slider-loading.php`, `elements-slider-multiple.mjs` y `tools-slider.php`. Añadir:
   - Comprobación de que un documento guardado sale igual al leerse y volver a guardarse (`get_raw_data()`).
   - Sitio con `container` inactivo.
   - Varias instancias en la misma página con espejo y bucle.
   - Contenido con formularios o anclas en el clon.
   - Ampliar `tests/elements-carousel-engine.mjs` y `tests/playground/interaction.sh`.

Con esto el Slider Optimizado cubriría de Prime Slider las diapositivas de plantilla o contenido, slide y fade, autoplay, flechas, puntos y fracción, bucle, teclado, deslizamiento, movimiento reducido y pausa. Seguirían fuera, como diferencias declaradas: cube, coverflow, flip, cards, creative, GL, shutters, slicer y tinder, el texto animado con GSAP o SplitText, Reveal, el fondo adaptativo y los controles remotos.

---

## Decisiones posteriores (plan aprobado)

- **F0.1 (riesgo vivo, inmediato):** el análisis y el informe de Migración listan, por documento, cada extensión de Element Pack que hoy se descarta, y se detecta el Element Pack Addon.
- **F0:** los scripts de inventario leen el ZIP sin extraerlo y unen 9.9.1 y 10.0.1 con `moved_to_addon`.
- **F8:** el Addon entra en `Retire` con `phase => 'start'`, junto a Element Pack; se corrigen las clases de los seis mapas; las extensiones convertibles pasan a ser nativas en Elements (tooltip como rasgo, sticky, transform, parallax aproximado) y las que se descarten necesitan aprobación del propietario; se convierten su Theme Builder, fuentes e iconos.
- **Prime Slider:** se detecta y se convierte, por defecto al Slider Optimizado ampliado (y al widget anidado nuevo). Swiper sólo con necesidad demostrada en la matriz. Sin la versión Lite, sus valores por defecto quedan pendientes. Retirada con `phase => 'end'` (o `start` si tiene adaptadores).
- **Orden de Element Pack:** se mantiene «desactivar antes de convertir», documentado como excepción (los adaptadores mantienen visibles los documentos). Los orígenes nuevos van siempre por borrador → validar → activar.
