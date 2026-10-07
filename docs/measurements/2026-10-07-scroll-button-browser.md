# Botón de desplazamiento en WordPress Playground

Prueba local con Elementor activo y una página publicada que contiene `digitalisimo-scroll-button`, un tramo largo y una sección `id="destino"`.

| Ancho | Destino | CSS | JS | Desbordamiento | Resultado del clic |
| --- | --- | ---: | ---: | --- | --- |
| 1280 px | `#destino` | 1 | 1 | No | URL con `#destino` y foco en la sección |
| 390 px | `#destino` | 1 | 1 | No | URL con `#destino` y foco en la sección |

La portada sin el widget cargó cero recursos `scroll-button`. Elementor mostró el widget y su vista previa, sin errores JavaScript. En móvil se comprobó el desplazamiento con movimiento normal y con `prefers-reduced-motion: reduce`: la sección quedó a 40 px de la parte superior en ambos casos. Se detectó que el tema usa `scroll-behavior: smooth`; el script ahora usa pasos instantáneos para que la duración propia no quede alterada por esa regla global. El enlace de ancla sigue funcionando sin JavaScript.

Esta prueba no acredita equivalencia visual con `bdt-scroll-button` ni funcionamiento en una red Multisite real; el ID anterior permanece sin registrar.
