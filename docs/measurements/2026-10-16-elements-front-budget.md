# Presupuesto del front de DIGITALÍSIMO Elements · 2026-10-16

Medición de `tests/playground/front-budget.sh` (Fase 0 de `docs/ELEMENTS_FRONT_OBJECTIVE.md`) sobre WordPress Playground con el núcleo incluido, sin el plugin Elementor ni Element Pack, en Chrome sin interfaz y **sin caché del navegador**: una página por widget propio con ajustes representativos, una página vacía de referencia y dos páginas compuestas. Cada página lleva el mismo encabezado de referencia, así que la fuente del kit no cuenta como coste del widget. Las columnas son la diferencia con la página vacía: peticiones, KB del propio sitio (todo menos terceros), CSS, JS, KB de terceros, CLS y imágenes sin dimensiones. Los valores «antes» salen de la primera medición de 4.3.0.152 (sin el encabezado común en las páginas de widget, por lo que sus CSS y peticiones quedaban unos 0.8 KB y una petición por debajo); los «después» son de 4.3.0.153 y constituyen la línea base guardada en `tests/playground/front-budget/baseline.json`.

Lo que cambió entre las dos mediciones (Fase 1): generador único de imágenes con `srcset`, dimensiones y `sizes` por columnas; sin `loading` forzado; scripts condicionales; `defer`; hojas por variante; QR sin jQuery; foco, `content-visibility` y `preconnect`.

| Widget | Peticiones | KB propios antes → después | CSS antes → después | JS antes → después | Terceros KB | CLS | Imágenes sin dimensiones antes → después |
|---|---:|---:|---:|---:|---:|---:|---:|
| Acordeón | -1 | -1.4 → -0.1 | 1.0 → 1.8 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Botón | -1 | 1.7 → 0.6 | 4.0 → 2.4 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Separador avanzado | -1 | -1.4 → -0.2 | 1.3 → 2.1 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Encabezado avanzado | -1 | -2.6 → -1.4 | 0.2 → 0.9 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Caja de icono avanzada | -1 | -0.5 → 0.7 | 1.8 → 2.6 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Encabezado animado | 0 | 0.5 → 1.9 | -0.2 → 0.6 | 3.4 → 3.4 | 0.0 | 0.000 | 0 → 0 |
| Enlace animado | 0 | 7.2 → -0.3 | 10.2 → 2.1 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Reproductor de audio | 0 | 3.0 → 2.5 | -0.3 → 0.5 | 0.0 → 0.0 | 0.0 | 0.000 | 1 → 0 |
| Ruta de navegación | -1 | -3.1 → -1.9 | -0.4 → 0.4 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Horario | 0 | 3.8 → 5.1 | 0.2 → 1.0 | 5.8 → 5.8 | 0.0 | 0.000 | 0 → 0 |
| Llamada a la acción | -1 | -2.8 → -1.5 | 0.1 → 0.9 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Gráfico | -1 | -1.8 → -0.5 | -0.1 → 0.7 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Lista comparativa | -1 | -2.5 → -1.3 | -0.1 → 0.6 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Galería personalizada | 0 | 17.3 → 12.8 | 0.3 → 1.1 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Slider de dispositivos | 2 | 23.8 → 13.1 | 2.2 → 2.9 | 3.7 → 3.7 | 0.0 | 0.000 | 0 → 0 |
| Visor de documentos | 1 | -3.2 → -1.9 | -0.4 → 0.3 | 1.9 → 1.9 | 1.9 | 0.000 | 0 → 0 |
| Botón doble | -1 | -3.0 → -1.7 | -0.1 → 0.6 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Tarjeta destacada | 0 | 2.5 → 3.7 | 0.5 → 1.2 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Iconos destacados | -1 | -2.1 → -0.9 | 0.4 → 1.1 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Lista destacada | 0 | 3.5 → 4.8 | 1.2 → 1.9 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Slider destacado | 2 | 23.7 → 19.2 | 2.3 → 3.1 | 3.7 → 3.7 | 0.0 | 0.000 | 0 → 0 |
| Pestañas | 0 | 1.9 → 3.2 | 1.4 → 2.2 | 2.2 → 2.2 | 0.0 | 0.000 | 0 → 0 |
| Caja destacada | 0 | 17.9 → 7.1 | 2.0 → 2.8 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Reseñas de Google | 0 | -0.0 → 1.2 | 0.6 → 1.4 | 1.8 → 1.8 | 0.0 | 0.000 | 0 → 0 |
| Navegación con iconos | -1 | 2.3 → 1.2 | 3.7 → 2.1 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Comparador de imágenes | 1 | 5.1 → 7.3 | 0.5 → 1.3 | 0.9 → 0.9 | 0.0 | 0.000 | 2 → 0 |
| Logotipos | 2 | 12.6 → 12.2 | 4.3 → 3.2 | 3.7 → 3.7 | 0.0 | 0.000 | 0 → 0 |
| Mapa | 12 | -3.1 → -1.8 | 8.4 → 9.2 | 769.5 → 769.5 | 1090.2 | 0.000 | 0 → 0 |
| Marquesina | 1 | 5.2 → 5.2 | 0.7 → 1.5 | 0.5 → 0.5 | 0.0 | 0.000 | 2 → 0 |
| Notificación | 0 | 0.1 → 1.4 | 0.5 → 1.3 | 2.1 → 2.1 | 0.0 | 0.000 | 0 → 0 |
| Panel lateral | 0 | 5.0 → 6.3 | 1.8 → 2.6 | 4.7 → 4.7 | 0.0 | 0.000 | 0 → 0 |
| Cuadrícula de productos | 0 | 11.6 → 13.0 | 0.7 → 1.5 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Barras de progreso | -1 | -1.5 → -0.2 | 0.8 → 1.5 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Código QR | 1 | 23.9 → 22.3 | -0.1 → 0.7 | 26.3 → 23.4 | 0.0 | 0.000 | 0 → 0 |
| Progreso de lectura | 0 | -2.0 → -0.8 | -0.4 → 0.4 | 1.1 → 1.1 | 0.0 | 0.000 | 0 → 0 |
| Tiempo de lectura | -1 | -3.6 → -2.3 | -0.6 → 0.2 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Botón de desplazamiento | -1 | -1.1 → -1.2 | 0.3 → 1.2 | 1.4 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Tabla de datos | 0 | 5.5 → 6.8 | 1.1 → 1.9 | 7.1 → 7.1 | 0.0 | 0.003 | 0 → 0 |
| Nube de etiquetas | -1 | -3.1 → -1.8 | -0.3 → 0.4 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Línea de tiempo | 0 | 4.7 → 4.3 | 1.2 → 2.1 | 0.0 → 0.0 | 0.0 | 0.000 | 1 → 0 |
| Contador total | -1 | -3.4 → -2.1 | -0.4 → 0.4 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Registro de usuarios | -1 | -3.2 → -1.9 | -0.3 → 0.5 | 0.0 → 0.0 | 0.0 | 0.000 | 0 → 0 |
| Menú vertical | 0 | 3.1 → 4.4 | 2.2 → 3.0 | 2.7 → 2.7 | 0.0 | 0.002 | 0 → 0 |
| Reproductor de video (YouTube) | 13 | -1.5 → -0.2 | 61.5 → 59.7 | 956.8 → 955.6 | 1195.0 | 0.000 | 0 → 0 |
| Slider Optimizado | 1 | 27.2 → 28.5 | 2.9 → 3.8 | 10.2 → 10.2 | 0.0 | 0.000 | 0 → 0 |
| Doce widgets propios | 15 | 76.3 → 56.0 | 25.5 → 22.3 | 13.0 → 13.0 | 0.0 | 0.000 | 1 → 0 |
| PRO pesado (Media carousel, Lottie, Slides) | 11 | 545.0 → 546.2 | 30.9 → 31.6 | 392.4 → 392.4 | 33.4 | 0.000 | 0 → 0 |

Página vacía: 28 peticiones, 1061.4 KB (JS 691.3 KB, CSS 183.3 KB), jQuery sí, handlers PRO sí, 247 nodos.
Lighthouse móvil local · empty: puntuación 68, LCP 5921 ms, TBT 9 ms, CLS 0.000, 945.9 KB.
Lighthouse móvil local · all-own: puntuación 66, LCP 6770 ms, TBT 22 ms, CLS 0.000, 1020.7 KB.
Lighthouse móvil local · pro-heavy: puntuación 61, LCP 6713 ms, TBT 77 ms, CLS 0.000, 1530.8 KB.

## Lectura

- Ningún widget propio pasa de 4 KB de CSS ni (salvo Slider Optimizado y la tabla interactiva) de 8 KB de JS; las hojas por variante bajan Enlace animado de 10.2 a 2.1 KB, Botón de 4.0 a 2.4, Navegación con iconos de 3.7 a 2.1 y Logotipos de 4.3 a 3.2 (base más cuadrícula o carrusel).
- Las imágenes salen de WordPress con `srcset` y dimensiones en los 45 widgets: cero imágenes sin dimensiones (antes Comparador, Marquesina, Línea de tiempo y Audio) y menos bytes de imagen donde `sizes` se calcula por columnas (Caja destacada 17.9 → 7.1 KB, Galería 17.3 → 12.8, Slider de dispositivos 23.8 → 13.1).
- CLS relativo cero en todos los widgets; el Comparador reserva su proporción.
- Botón de desplazamiento ya no carga script sin desplazamiento vertical (1.4 → 0 KB); Código QR dibuja sin jQuery con un vendor de 21 KB (antes 25 KB más la dependencia).
- Lo que no cambia y pertenece a las fases siguientes: la página vacía paga 1,061 KB (691 KB de JS: jQuery, núcleo y los dos paquetes globales de PRO) sin que haya ningún widget PRO; el mapa de OpenStreetMap suma 1,090 KB de terceros y un vídeo de YouTube 1,195 KB (Fase 3, fachadas); la página con widgets PRO pesados añade 546 KB (Swiper y Lottie; Fase 4).
- Lighthouse móvil local sirve sólo como comparación relativa: Playground responde en más de 5 s y el LCP lo domina el servidor, no el front.
