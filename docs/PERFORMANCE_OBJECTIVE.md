# Objetivo: rendimiento conservador de DIGITALÍSIMO SEO

## Alcance y línea base

Optimizar recursos de WordPress, Elementor/Pro, fuentes y terceros de forma contextual, reversible por opción y compatible con Multisite. La referencia aportada por el propietario es móvil: Performance 61, FCP 3.1 s, LCP 7.4 s, TBT 220 ms, CLS 0, Speed Index 6.6 s y TTFB ~70 ms. El LCP es el H1 de portada; el retraso de render atribuido es ~2.51 s. Estos valores no se consideran una medición posterior ni autorizan retirar bundles compartidos.

No se modifican WordPress, Elementor ni Elementor Pro; no se combinan/minifican bundles, no se retrasa todo JS ni se retira jQuery. Se preservan editores, vistas previas, menús, formularios, tabs, sticky, animaciones, sliders, WooCommerce, imágenes originales, `srcset`, `sizes` y CLS.

## Arquitectura y reglas de entrega

- Módulo dentro de `digitalisimo-seo/includes/performance/`, iniciado desde el bootstrap de SEO. Configuración por sitio y red con resolución existente `sitio → red → default`, capacidad, nonce y sanitización; cada control debe tener reversión independiente.
- Menú `Digitalisimo → SEO → Rendimiento` en sitio y red; secciones WordPress, Elementor, WooCommerce, Fuentes, Externos, Diagnóstico, Reglas, Exclusiones, Modo seguro y Medición.
- Modo seguro activo por defecto. Ni Elementor frontend/core, Elementor Pro, Swiper, WooCommerce crítico, jQuery ni dependencias en uso se descargan por heurísticas. Un estado “Revisar” o “Desconocido” sólo produce diagnóstico.
- Cualquier regla de descarga se evalúa en la página real, sobre handles encolados y dependencias, con exclusión de administración, REST de edición, AJAX, editor, vista previa, Customizer y usuarios administradores editando. Registrar razón, URL y dependencias sólo si `DIGITALISIMO_PERFORMANCE_DEBUG` o filtro equivalente está activo.
- Se incrementa y publica únicamente la versión de SEO por cambio de SEO. Mover ZIP anterior a `rollback/`, actualizar expectativa en `tests/validate-suite.mjs`, validar paquete y Release.

## Fases y puertas

| Fase | Trabajo | Estado | Puerta para avanzar |
| --- | --- | --- | --- |
| 1 | Arquitectura, ajustes, modo seguro, limpieza conservadora Gutenberg/emoji/embed/Dashicons, font-display, diagnóstico de assets y logging | En desarrollo | Pruebas de hooks, editor/preview/admin intactos y páginas blog con bloques intactas |
| 2 | Diagnóstico Elementor/Swiper/widgets y WooCommerce; reglas manuales sin descargas inseguras | Iniciado: diagnóstico de widgets y contextos | Dependencias y widgets comprobados en páginas reales; pruebas de WooCommerce |
| 3 | Descarga contextual explícita, retraso opt-in de analítica, preload selectivo y dimensiones conocidas | Pendiente | Comparativa visual y funcional antes/después, consola sin errores nuevos, CLS estable y reversión probada |

## Matriz de verificación en sitio real

Por fase se comprueba HOME desktop/móvil, menú responsive, sliders, carruseles, animaciones y botones; listado/categoría/single de blog con bloques y embeds; Elementor editor, guardar, preview y frontend; Pro form/nav/sticky/tabs/popups/carruseles; WooCommerce shop/product/add-to-cart/cart/checkout/account/minicart cuando esté activo. Revisar consola, 404, imágenes/fuentes, responsive, lazy loading y CLS. Repetir en sitio individual de Multisite, administración de red y WordPress individual. Guardar medición manual antes/después de Performance, FCP, LCP, TBT y CLS. Una fase no se declara completa con pruebas de código solamente.

## Registro de avance

- 2026-09-30: objetivo y línea base documentados tras inspeccionar el bootstrap, ajustes, resolutor sitio/red y navegación SEO. Se confirmó que los actualizadores consultan Releases de forma independiente. No hay medición posterior ni validación funcional del sitio todavía.
- 2026-09-30: se inició la fase 1 en SEO con controles por sitio/red y diagnóstico de colas por URL pública. `perf_gutenberg` viene activado, pero sólo actúa sobre `wp-block-library` y `wp-block-library-theme` en páginas Elementor con tema base Hello, sin bloques/shortcodes ni dependientes; `global-styles` y `classic-theme-styles` se conservan. Emojis, `wp-embed`, Dashicons y `font-display: swap` están apagados inicialmente. La descarga usa `wp_print_styles` prioridad 20 para esperar enqueues normales; `wp-embed` usa `wp_enqueue_scripts` prioridad 100. Diagnóstico captura en `wp_print_styles` prioridad 25 y footer prioridad 25. No se toca infraestructura central de Elementor/Pro/Swiper/WooCommerce.
- 2026-09-30: inspección HTTP de la portada pública: ~44 handles CSS, ~18 JS y ningún `wp-block-library-css` visible antes de esta versión. Las hojas locales Inter y Source Serif 4 contienen 126 y 96 reglas `@font-face` respectivamente, ninguna con `font-display`. El switch de fuentes usa `pre_option_elementor_font_display` para las fuentes Google administradas por Elementor y el filtro oficial `elementor_pro/custom_fonts/font_display` para fuentes personalizadas; los CSS existentes requieren regeneración/caché de Elementor. No se afirma aún una mejora del H1/LCP.
- 2026-09-30: el diagnóstico registra `elementor/frontend/widget/before_render`, obtiene `get_script_depends()`/`get_style_depends()` de los widgets efectivamente renderizados y señala si alguno declara Swiper. También identifica los contextos WooCommerce básicos y widgets de carrito; la ausencia de detección se comunica como recomendación de revisión, nunca como autorización para descargar.
- 2026-09-30: el diagnóstico inspecciona únicamente hojas CSS locales encoladas bajo `wp-content`/uploads, con límite de 512 KiB por archivo y comprobación de ruta real. Agrupa reglas `@font-face` por familia, pesos, formato y declaración `font-display`, sin inventar uso above-the-fold. La prueba automatizada verifica que no pueda leer CSS fuera de `wp-content` por traversal.
- 2026-09-30: se agregó una comparativa manual por URL con Performance, FCP, LCP, TBT y CLS antes/después. La red identifica el sitio por URL y guarda los datos en la opción de ese blog mediante `switch_to_blog()`/`restore_current_blog()`; se conservan como máximo 25 URLs por sitio. No se consulta PageSpeed API.
- 2026-09-30: se midió la portada pública con Lighthouse local antes de instalar SEO 1.0.148: Performance 73, FCP 1.6 s, LCP 2.1 s, TBT 960 ms y CLS 0.005. [Detalle y captura](measurements/2026-09-30-home-mobile-before.md). La API PageSpeed respondió HTTP 429; la medición local es la referencia reproducible para la comparación posterior.
- 2026-09-30: el diagnóstico complementa los handles de WordPress con scripts externos declarados directamente en HTML y preloads de fuentes. No pretende representar scripts que terceros inyecten dinámicamente después de cargar la página; éstos requieren inspección de red en navegador.
- 2026-09-30: el diagnóstico revisa también hasta 100 etiquetas `<img>` publicadas, mostrando dimensiones HTML, `srcset`, `sizes` y recomendaciones cuando falta información. No calcula tamaño visual ni modifica imágenes originales o metadatos.
- 2026-09-30: la detección de Swiper se reforzó con dependencias transitivas declaradas, nombres de widgets tipo carrusel y markup público. Es conservadora: prefiere indicar «conservar» ante incertidumbre. SEO 1.0.149 publica estas mejoras de diagnóstico sin cambiar las descargas automáticas de la fase 1.

### Riesgo y reversión de fase 1

Cada control puede apagarse en SEO → Rendimiento del sitio o de red. Si una página Elementor usa CSS de bloques mediante un mecanismo que no se ve en el contenido ni en dependencias declaradas, desactivar `perf_gutenberg` restaura los estilos. En modo seguro no hay reglas avanzadas. Los filtros de fuentes sólo influyen al regenerar CSS de Elementor; desactivarlos y regenerar restaura el valor anterior. El diagnóstico depende de que el servidor pueda solicitar su propia URL y de que la caché respete el token de prueba. El efecto esperado en PageSpeed es menos CSS de bloques en páginas Elementor y, cuando se habilite swap, menos espera visual del texto; todavía no existe una medición posterior.

Referencias técnicas: [cola frontend WordPress](https://developer.wordpress.org/reference/hooks/wp_enqueue_scripts/), [descarga de CSS](https://developer.wordpress.org/reference/functions/wp_dequeue_style/), [dependencias de widgets Elementor](https://developers.elementor.com/docs/widgets/widget-dependencies/) y [font-display de Elementor Pro](https://developers.elementor.com/docs/hooks/font-display).
