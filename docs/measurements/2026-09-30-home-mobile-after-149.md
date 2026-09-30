# Portada móvil con SEO 1.0.149 instalado

Medición de laboratorio local con Lighthouse y Chromium headless el 2026-09-30. La API autenticada de WordPress reportó DIGITALÍSIMO SEO 1.0.149 activo en la red. Se usó el mismo comando y navegador que en la [medición previa](2026-09-30-home-mobile-before.md).

| Métrica | Antes (SEO 1.0.147) | Con SEO 1.0.149 |
| --- | ---: | ---: |
| Performance | 73 | 92 |
| FCP | 1.6 s | 1.7 s |
| LCP | 2.1 s | 2.1 s |
| TBT | 960 ms | 275 ms |
| CLS | 0.005 | 0.003 |
| Speed Index | 5.1 s | 2.4 s |
| Respuesta documento raíz | 1,052 ms | 296 ms |

Son dos corridas puntuales, separadas en el tiempo; el TTFB varió mucho y la portada no tenía `wp-block-library-css` antes de la actualización. Por eso la diferencia de puntuación **no se atribuye** al módulo de rendimiento. Falta medir repetidamente con condiciones comparables y probar otras páginas.

Prueba funcional con Chromium a 390×844 y 1440×900: H1 correcto, nueve instancias Swiper activas, un formulario, fuentes cargadas, ningún error JavaScript, ningún 404 y sin desbordamiento horizontal. El menú no aparece en el HTML de esta portada y queda pendiente de probar en otra página. También faltan pruebas de blog/Gutenberg, editor y preview de Elementor, Elementor Pro y WooCommerce en un entorno que disponga de esas páginas.
