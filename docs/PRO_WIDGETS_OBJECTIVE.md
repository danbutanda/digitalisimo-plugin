# Objetivo: DIGITALÍSIMO Elements sin widgets de PRO

Este documento es el punto de entrada para continuar el programa que sustituye los widgets derivados de PRO Elements por widgets propios de DIGITALÍSIMO Elements, con la conversión del contenido existente en DIGITALÍSIMO Migración. Sustituye a la «Fase 4 · Decisiones de producto» de `docs/ELEMENTS_FRONT_OBJECTIVE.md` (ocultar seis widgets de PRO), que queda absorbida aquí; «Marcado optimizado» sigue siendo una decisión aparte de ese documento.

## Decisiones del propietario (2026-11-03)

- **Ningún widget de los plugins de referencia.** Los widgets de PRO Elements que trae Elements (102: 94 con su `get_name()` más 8 del Theme Builder que heredan de una base) pasan, todos, a widgets propios. Theme Builder, popups, condiciones de visualización, efectos de movimiento, fijados y Código personalizado no son widgets: siguen siendo funciones del constructor.
- **«Migrar» deja un sitio existente perfecto:** tema, kit, widgets, plantillas del Theme Builder, popups y bucles, con el mismo botón único de siempre (`docs/ELEMENT_PACK_RETIREMENT.md`, `AGENTS.md`).
- **Tras migrar, los widgets de PRO quedan ocultos del panel pero registrados.** Un sitio sin migrar, una revisión antigua o un kit de terceros (Envato) se siguen viendo, y Migración o el importador de kits los convierten. Ningún widget de PRO se borra del plugin dentro de este programa.
- **Origen de los sitios:** Elementor Pro o PRO Elements, Element Pack y Prime Slider (ya convertidos por F8) y Hello (ya trasladado por el tema, `inc/switch.php`). **El tema se cierra con lo planificado al inicio:** sólo Hello. No se migra ningún otro tema (decisión del 2026-11-03).
- **El tema lo sigue activando el propietario** en Apariencia: «Migrar» no lo activa (regla de `AGENTS.md`, sin cambios).

## Reglas del programa

1. **Widget propio de verdad.** Nombre `digitalisimo-*`, clase propia, marcado y recursos propios que cumplen el estándar de `docs/ELEMENTS_FRONT_OBJECTIVE.md` (recursos sólo donde se usan, `defer`, sin jQuery ni Swiper, imágenes por WordPress, foco visible, movimiento reducido, presupuesto por widget en `tests/elements-front-budget.php`). Puede partir del código GPL de PRO Elements que ya está en Elements, con atribución en `docs/third-party-licenses.md`, pero el resultado no depende de `pro-elements-handlers` ni de `elementor-pro-frontend`.
2. **Un convertidor por widget, en Elements.** La traducción de los ajustes de cada widget de PRO a su widget propio vive en Elements (que ya contiene PRO, así que puede nombrarlo), en un módulo propio y declarativo como los mapas de F8. La usan las dos entradas: **Migración** («Migrar», por sitio y red, reversible con `--revert` por huella) y **K** (al importar un kit, por su filtro de conversión). Migración sólo orquesta; no se crea otro plugin.
3. **Conversión fiel o declarada.** Cada ajuste se traduce, se aproxima con un código en el informe o se descarta con informe por documento; nunca en silencio. El contenido, los enlaces, las imágenes, los textos accesibles y el comportamiento con teclado se conservan.
4. **Ocultar sólo lo sustituido.** Un widget de PRO se oculta del panel (configuración del editor, sin editar su archivo) cuando su equivalente propio y su convertidor están publicados y validados. Hasta entonces sigue a la vista.
5. **Fallar cerrado y reversible.** Lo que no se puede convertir queda como estaba (widget de PRO registrado) y aparece en «Pendiente de revisar». Las mismas garantías de F8: copias, registro, procedencia y `--revert` sin pisar lo editado.
6. **Destinos de F8.** Lo que F8 convierte a widgets de PRO (Carrusel multimedia, Loop Carousel, Productos…) pasa a apuntar al widget propio cuando su familia se publique, y Migración convierte también lo ya migrado.
7. **Paridad, publicación y pruebas** como siempre: versión sólo de lo modificado, pruebas nuevas en `validate.yml` y `release.yml`, fila en `docs/PLUGIN_ROADMAP.md` y estado aquí.

## Aceptación de cada widget

- **A/B en Playground** (arnés nuevo de C0, sobre la base de `tests/playground/f8/` y `front-budget.sh`): la misma página con el widget de PRO y con el convertido, a 375, 768 y 1 280 px; mismos textos, enlaces, imágenes y nombres accesibles; diferencia visual dentro del umbral del arnés, o declarada; interacciones con ratón y teclado; sin errores ni peticiones a terceros nuevas.
- **Front:** la página con sólo widgets propios no carga los paquetes de PRO (puerta de la Fase 2), ni Swiper, ni jQuery propio; el widget cabe en su presupuesto.
- **Ida y vuelta de kits:** exportar e importar una página con el widget propio no pierde nada (`kit-roundtrip.sh`), y un kit con el widget de PRO llega convertido.
- **Escala y red:** la conversión por lotes de F8 (miles de documentos, red con barrera) sigue en verde.

## Etapas

| Etapa | Widgets de PRO |
|---|---:|
| C0 · Inventario, método y arnés | — |
| C1 · Carruseles | 5 |
| C2 · Contenido | 15 |
| C3 · Navegación y búsqueda | 4 |
| C4 · Theme Builder y elementos de tema | 16 |
| C5 · Entradas y bucles | 5 |
| C6 · Formularios | 2 |
| C7 · WooCommerce | 30 |
| C8 · Botones flotantes y Link in Bio | 17 |
| C9 · Otros | 8 |

- **C0 · Inventario, método y arnés.** «Analizar» de Migración cuenta, por sitio y red, cada widget de PRO usado (también dentro de plantillas, popups, Loop Items y revisiones), los tipos de plantilla del Theme Builder y el kit: así se ordenan las familias por uso real. Se crean el módulo de convertidores de Elements, el mecanismo para ocultar del panel, el arnés A/B y la plantilla de etapa. El orden de C1–C9 se revisa con esos datos.
- **C1 · Carruseles.** Los cinco, sobre un motor de carrusel propio (`digi-crs`, derivado del slider anidado de F7b), sin Swiper. El Slider Optimizado (marquesina de logotipos) y el Slider anidado ligero no eran equivalentes completos y no cambian: Slides y Nested carousel tienen también widget propio nuevo. Incluye el punto de extensión de K que convierte los widgets de PRO al importar un kit. Es la familia que más pesa en el front (Swiper, 144 KB). Contrato y resultado en `docs/pro/c1.md`.
- **C2 · Contenido.** Quince widgets sueltos; cinco tienen equivalente total o parcial. Lottie necesita su librería (253 KB): el widget propio la carga al acercarse a la pantalla, como hace hoy `lazy_libs`. Los catorce que quedaban tras la Cita de C0 tienen widget propio nuevo; los equivalentes parciales que ya existían (Encabezado animado, Llamada a la acción, Tarjeta destacada, Galería personalizada, Progreso de lectura y Reproductor de video) no cambian. Contrato y resultado en `docs/pro/c2.md`.
- **C3 · Navegación y búsqueda.** Menú y Mega Menu (los más usados en cabeceras), el Off-Canvas de contenedor anidado y la búsqueda.
- **C4 · Theme Builder y elementos de tema.** Logo, título, contenido, extracto, imagen destacada, títulos de archivo, entradas del archivo, información, navegación, autor, comentarios y mapa del sitio: los widgets de las plantillas de cabecera, pie, entrada y archivo.
- **C5 · Entradas y bucles.** Posts, Portfolio, Loop Grid, Loop Carousel y el filtro de taxonomías, con las consultas guardadas y los filtros de F7b y los Loop Items.
- **C6 · Formularios.** Formulario (campos, acciones, envíos guardados, integraciones y campos condicionales de F7b) e inicio de sesión. Es la familia con más riesgo de datos: ninguna acción se pierde ni se duplica.
- **C7 · WooCommerce.** Treinta widgets de tienda, carrito, pago, cuenta y ficha, sobre lo que ya añadió F4-Woo.
- **C8 · Botones flotantes y Link in Bio.** Diecisiete variantes de dos widgets; se agrupan en dos widgets propios con presentaciones.
- **C9 · Otros.** Los cuatro de Facebook (con fachada, como la Fase 3), PayPal y Stripe (sus claves nunca vuelven al navegador), el widget global (se convierte en su contenido) y el widget Plantilla (función del constructor, con las sobrescrituras de F7b).
- **Tema.** Cerrado con lo planificado al inicio: Hello se traslada al activar el tema DIGITALÍSIMO (tema 1.0.0). No hay etapa para otros temas.

## Inventario (2026-11-03, Elements 4.3.0.171)

«Existe»: hay un widget propio con la misma función. «Parcial»: lo hay, pero le faltan opciones. En los dos casos el widget propio suele tener muchas menos opciones que el de PRO (el Progreso de lectura, 4 controles frente a 27; el Encabezado animado no tiene las formas de resaltado ni los ocho tipos de rotación): para una migración fiel, cada familia lo amplía con controles opcionales que no cambian su comportamiento por defecto. «Nuevo»: hay que crearlo. «Contenido» y «Función»: no se sustituye por un widget.

**Método que dejó C1:** cuando el widget propio existente es mucho más simple que el de PRO, la sustitución fiel es un widget propio nuevo que repite los controles de PRO con el mismo nombre (mapa casi todo `keep`), no ampliar el existente; el existente sigue igual. Así se hizo en C1 con Slides y Nested carousel, que el inventario daba como «Existe». Cada familia lo decide antes de empezar, con este criterio.

| Etapa | Módulo de PRO | Widget (`get_name()`) | Título en PRO | Equivalente propio | Nota |
|---|---|---|---|---|---|
| C1 | `carousel` | `media-carousel` | Media Carousel | **Sustituido** | Carrusel multimedia (`digitalisimo-media-carousel`), C1 |
| C1 | `carousel` | `reviews` | Reviews | **Sustituido** | Reseñas (`digitalisimo-reviews`), C1 |
| C1 | `carousel` | `testimonial-carousel` | Testimonial Carousel | **Sustituido** | Carrusel de testimonios (`digitalisimo-testimonial-carousel`), C1 |
| C1 | `nested-carousel` | `nested-carousel` | Carousel | **Sustituido** | Carrusel anidado (`digitalisimo-nested-carousel`), C1 |
| C1 | `slides` | `slides` | Slides | **Sustituido** | Diapositivas (`digitalisimo-slides`), C1 |
| C2 | `animated-headline` | `animated-headline` | Animated Headline | **Sustituido** | Titular animado (`digitalisimo-animated-headline`), C2; el Encabezado animado no cambia |
| C2 | `blockquote` | `blockquote` | Blockquote | **Sustituido** | Cita (`digitalisimo-blockquote`), C0 |
| C2 | `call-to-action` | `call-to-action` | Call to Action | **Sustituido** | Llamada a la acción con imagen (`digitalisimo-call-to-action`), C2 |
| C2 | `code-highlight` | `code-highlight` | Code Highlight | **Sustituido** | Código resaltado (`digitalisimo-code-highlight`), C2 |
| C2 | `countdown` | `countdown` | Countdown | **Sustituido** | Cuenta regresiva (`digitalisimo-countdown`), C2 |
| C2 | `flip-box` | `flip-box` | Flip Box | **Sustituido** | Tarjeta giratoria (`digitalisimo-flip-box`), C2 |
| C2 | `gallery` | `gallery` | Gallery | **Sustituido** | Galería avanzada (`digitalisimo-gallery`), C2; la Galería personalizada no cambia |
| C2 | `hotspot` | `hotspot` | Hotspot | **Sustituido** | Puntos de interés (`digitalisimo-hotspot`), C2 |
| C2 | `lottie` | `lottie` | Lottie | **Sustituido** | Animación Lottie (`digitalisimo-lottie`), C2 |
| C2 | `pricing` | `price-list` | Price List | **Sustituido** | Lista de precios (`digitalisimo-price-list`), C2 |
| C2 | `pricing` | `price-table` | Price Table | **Sustituido** | Tabla de precios (`digitalisimo-price-table`), C2 |
| C2 | `progress-tracker` | `progress-tracker` | Progress Tracker | **Sustituido** | Indicador de progreso (`digitalisimo-progress-tracker`), C2; el Progreso de lectura no cambia |
| C2 | `share-buttons` | `share-buttons` | Share Buttons | **Sustituido** | Botones para compartir (`digitalisimo-share-buttons`), C2 |
| C2 | `table-of-contents` | `table-of-contents` | Table of Contents | **Sustituido** | Tabla de contenido (`digitalisimo-table-of-contents`), C2 |
| C2 | `video-playlist` | `video-playlist` | Video Playlist | **Sustituido** | Lista de videos (`digitalisimo-video-playlist`), C2; el Reproductor de video no cambia |
| C3 | `mega-menu` | `mega-menu` | Menu | Nuevo |  |
| C3 | `nav-menu` | `nav-menu` | WordPress Menu | Nuevo |  |
| C3 | `off-canvas` | `off-canvas` | Off-Canvas | Parcial | Panel lateral (PRO usa un contenedor anidado) |
| C3 | `search` | `search` | Search | Nuevo |  |
| C4 | `theme-builder` | `archive-posts` | Archive Posts | Nuevo |  |
| C4 | `theme-builder` | `theme-archive-title` | Archive Title | Nuevo |  |
| C4 | `theme-builder` | `theme-page-title` | Page Title | Nuevo |  |
| C4 | `theme-builder` | `theme-post-content` | Post Content | Nuevo |  |
| C4 | `theme-builder` | `theme-post-excerpt` | Post Excerpt | Nuevo |  |
| C4 | `theme-builder` | `theme-post-featured-image` | Featured Image | Nuevo |  |
| C4 | `theme-builder` | `theme-post-title` | Post Title | Nuevo |  |
| C4 | `theme-builder` | `theme-site-logo` | Site Logo | Nuevo |  |
| C4 | `theme-builder` | `theme-site-title` | Site Title | Nuevo |  |
| C4 | `theme-elements` | `author-box` | Author Box | Parcial | extras propios sobre el de PRO (`class-author-box-extras.php`) |
| C4 | `theme-elements` | `breadcrumbs` | Breadcrumbs | Existe | Ruta de navegación |
| C4 | `theme-elements` | `post-comments` | Post Comments | Nuevo |  |
| C4 | `theme-elements` | `post-info` | Post Info | Nuevo |  |
| C4 | `theme-elements` | `post-navigation` | Post Navigation | Nuevo |  |
| C4 | `theme-elements` | `search-form` | Search Form | Nuevo |  |
| C4 | `theme-elements` | `sitemap` | Sitemap | Nuevo |  |
| C5 | `loop-builder` | `loop-carousel` | Loop Carousel | Nuevo |  |
| C5 | `loop-builder` | `loop-grid` | Loop Grid | Nuevo |  |
| C5 | `loop-filter` | `taxonomy-filter` | Taxonomy Filter | Nuevo |  |
| C5 | `posts` | `portfolio` | Portfolio | Nuevo |  |
| C5 | `posts` | `posts` | Posts | Nuevo |  |
| C6 | `forms` | `form` | Form | Nuevo |  |
| C6 | `forms` | `login` | Login | Nuevo |  |
| C7 | `woocommerce` | `wc-add-to-cart` | Custom Add To Cart | Nuevo |  |
| C7 | `woocommerce` | `wc-archive-products` | Archive Products | Nuevo |  |
| C7 | `woocommerce` | `wc-categories` | Product Categories | Nuevo |  |
| C7 | `woocommerce` | `wc-elements` | WooCommerce Pages | Nuevo |  |
| C7 | `woocommerce` | `wc-products` | Woo - Products- | Parcial | Cuadrícula de productos y partes de F4-Woo |
| C7 | `woocommerce` | `wc-single-elements` | Woo - Single Elements | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-archive-description` | Archive Description | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-archive-products` | Archive Products (deprecated) | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-breadcrumb` | WooCommerce Breadcrumbs | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-cart` | Cart | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-category-image` | Category Image | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-checkout-page` | Checkout | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-menu-cart` | Menu Cart | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-my-account` | My Account | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-notices` | WooCommerce Notices | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-product-add-to-cart` | Add To Cart | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-product-additional-information` | Additional Information | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-product-content` | Product Content | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-product-data-tabs` | Product Data Tabs | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-product-images` | Product Images | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-product-meta` | Product Meta | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-product-price` | Product Price | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-product-rating` | Product Rating | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-product-related` | Product Related | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-product-short-description` | Short Description | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-product-stock` | Product Stock | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-product-title` | Product Title | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-product-upsell` | Upsells | Nuevo |  |
| C7 | `woocommerce` | `woocommerce-products` | Products | Parcial | Cuadrícula de productos y partes de F4-Woo |
| C7 | `woocommerce` | `woocommerce-purchase-summary` | Purchase Summary | Nuevo |  |
| C8 | `floating-buttons` | `contact-buttons-var-1` | Multi Chat | Nuevo |  |
| C8 | `floating-buttons` | `contact-buttons-var-10` | Interactive | Nuevo |  |
| C8 | `floating-buttons` | `contact-buttons-var-3` | Info Button Box | Nuevo |  |
| C8 | `floating-buttons` | `contact-buttons-var-4` | Vertical Links | Nuevo |  |
| C8 | `floating-buttons` | `contact-buttons-var-5` | Classic | Nuevo |  |
| C8 | `floating-buttons` | `contact-buttons-var-6` | Quick Access Bar | Nuevo |  |
| C8 | `floating-buttons` | `contact-buttons-var-7` | Mobile Bar | Nuevo |  |
| C8 | `floating-buttons` | `contact-buttons-var-8` | Resource Box | Nuevo |  |
| C8 | `floating-buttons` | `contact-buttons-var-9` | Animated Classic | Nuevo |  |
| C8 | `floating-buttons` | `floating-bars-var-2` | Ticker | Nuevo |  |
| C8 | `floating-buttons` | `floating-bars-var-3` | Coupon | Nuevo |  |
| C8 | `link-in-bio` | `link-in-bio-var-2` | Classic | Nuevo |  |
| C8 | `link-in-bio` | `link-in-bio-var-3` | Showcase | Nuevo |  |
| C8 | `link-in-bio` | `link-in-bio-var-4` | Links | Nuevo |  |
| C8 | `link-in-bio` | `link-in-bio-var-5` | Services | Nuevo |  |
| C8 | `link-in-bio` | `link-in-bio-var-6` | Portfolio Bio | Nuevo |  |
| C8 | `link-in-bio` | `link-in-bio-var-7` | Business Card | Nuevo |  |
| C9 | `global-widget` | `global` | Global | Contenido | se convierte en su contenido |
| C9 | `library` | `template` | Template | Función | Plantilla (sobrescrituras de F7b) |
| C9 | `payments` | `paypal-button` | PayPal Button | Nuevo |  |
| C9 | `payments` | `stripe-button` | Stripe Button | Nuevo |  |
| C9 | `social` | `facebook-button` | Facebook Button | Nuevo |  |
| C9 | `social` | `facebook-comments` | Facebook Comments | Nuevo |  |
| C9 | `social` | `facebook-embed` | Facebook Embed | Nuevo |  |
| C9 | `social` | `facebook-page` | Facebook Page | Nuevo |  |

## Estado

| Etapa | Estado |
|---|---|
| C0 | **Publicado** · Elements 4.3.0.172 y Migración 1.0.22 · `v2026.11.03.448`. Núcleo de conversión, inventario y etapa `pro` de «Migrar», arnés A/B (`tests/playground/pro-ab.sh`) y de punta a punta (`pro-migrate.sh`), y el piloto: Blockquote → Cita, listo y oculto del panel. Detalle en `docs/pro/c0.md` |
| C1 | **Publicado** · Elements 4.3.0.173 y Migración 1.0.23 · `v2026.11.04.449`. Motor `digi-crs` y los cinco carruseles propios (Carrusel anidado, Carrusel multimedia, Carrusel de testimonios, Reseñas y Diapositivas) con sus mapas listos y fuera del panel; los kits convierten los widgets de PRO al importar (§17 de `docs/fusion/k-kits.md`). Detalle en `docs/pro/c1.md` |
| C2 | **Publicado** · Elements 4.3.0.174 y Migración 1.0.24 · `v2026.11.05.450`. Catorce widgets de contenido propios en `modules/digitalisimo-content/` (Titular animado, Código resaltado, Tabla de contenido, Botones para compartir, Llamada a la acción con imagen, Tarjeta giratoria, Lista de precios, Tabla de precios, Galería avanzada, Puntos de interés, Animación Lottie, Lista de videos, Cuenta regresiva e Indicador de progreso) con sus mapas listos y fuera del panel; con la Cita de C0, la familia C2 queda sustituida entera. Detalle en `docs/pro/c2.md` |
| C3–C9 | Pendientes |
| Tema | Cerrado: sólo Hello, hecho en el tema 1.0.0 |
