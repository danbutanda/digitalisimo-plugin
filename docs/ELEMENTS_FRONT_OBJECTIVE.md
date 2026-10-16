# Objetivo: front de DIGITALÍSIMO Elements optimizado al 100 %

Este documento es el punto de entrada para cualquier agente que continúe la optimización del front de `digitalisimo-elements`. El objetivo es que **todo lo que Elements imprime o carga en una página pública** (widgets propios, extensiones propias, widgets derivados de PRO Elements, recursos globales y ajustes del núcleo incluido) cumpla el mismo estándar que fijaron el Slider Optimizado y los módulos de rendimiento y calidad de DIGITALÍSIMO SEO, y que ese cumplimiento quede **medido y vigilado por pruebas** para que no se pierda en versiones siguientes. Se empieza por medir, se continúa por lo que depende sólo de nosotros y se termina por lo que toca el núcleo y PRO, que requiere decisión del propietario.

La compatibilidad no se negocia: no cambian nombres de widgets, ajustes guardados, alias ni mapas de DIGITALÍSIMO Migración; los cambios de HTML son atributos, portadas y envoltorios opcionales, nunca estructura que rompa CSS propio o heredado. Ver `AGENTS.md` (sección DIGITALÍSIMO Elements) y `docs/ELEMENT_PACK_MIGRATION_OBJECTIVE.md`.

## Estándar (criterios verificables)

Cada criterio tiene una comprobación estática (en `tests/`) o de navegador (en `tests/playground/`). Un widget o función «cumple» sólo si pasa las dos.

1. **Recursos sólo donde se usan.** Cada widget declara su hoja y su script por `get_style_depends()`/`get_script_depends()`; el script sólo se declara cuando la opción que lo necesita está activa (patrón de Tabla y Reproductor de video). Nada se encola en todo el front salvo lo que la página usa. Sin Swiper, UIkit ni jQuery en código propio. Scripts registrados en pie de página con `strategy => defer`.
2. **Imágenes por WordPress.** Toda imagen sale de `wp_get_attachment_image()` cuando hay adjunto (`srcset`, `sizes`, ancho, alto, ALT de Medios) y, si sólo hay URL, pasa por el mismo generador con las decisiones de carga de WordPress (`wp_get_loading_optimization_attributes()`): nunca `loading` forzado por índice ni por widget, `decoding="async"` siempre, `fetchpriority` sólo donde WordPress lo decide. `sizes` calculado por columnas y breakpoint en cuadrículas y carruseles. Proporción reservada: ninguna imagen provoca salto de diseño.
3. **HTML semántico y accesible.** Etiqueta de encabezado elegible donde hay título; `nav`, `section`, `figure`, `table` y listas reales; nombres accesibles; teclado y foco visible propio en todo enlace o control; `rel="noopener noreferrer"` en enlaces externos; escape en toda salida.
4. **Movimiento.** Toda animación CSS o JS respeta `prefers-reduced-motion`; el contenido se lee sin JavaScript (`noscript` donde algo queda oculto).
5. **Sin invisibles ni datos estructurados propios.** Nada oculto para buscadores; ningún JSON-LD (lo publica DIGITALÍSIMO SEO); el contenido indexable se pinta en servidor; lo pintado en cliente es ajeno por diseño (Reseñas de Google) o tiene respaldo legible (QR).
6. **Terceros a petición.** Vídeos, mapas y documentos no cargan su iframe hasta que el visitante lo pide (fachada con portada y botón accesible); dominios sin rastreo; `preconnect` sólo en páginas que los usan.
7. **Presupuesto por widget.** CSS ≤ 4 KB y JS ≤ 8 KB por widget salvo excepción documentada (Slider Optimizado); hojas por variante cuando un widget ofrece familias de efectos; `content-visibility` en listas largas.
8. **Núcleo y PRO.** Lo global que Elements no escribió (paquetes de PRO, iconos, ajustes del núcleo) se carga o se fija de modo que una página hecha con el núcleo y widgets propios no pague recursos de widgets que no usa.

## Línea base (auditoría estática del 16 de octubre de 2026, 4.3.0.152)

Widgets propios: 45 (sin contar los siete alias de 4.3.0.152).

| Criterio | Cumplen | Huecos |
|---|---|---|
| Recursos condicionales, sin Swiper ni UIkit | 45 | QR exige jQuery más 25 KB de vendor |
| Sin JavaScript | 27 | — |
| Script sólo si la opción lo necesita | 2 (Tabla, Video) | 16 lo cargan siempre; evitable en Encabezado animado, Horario, Logotipos, Slider destacado, Slider de dispositivos y Botón de desplazamiento |
| Imágenes por WordPress | 12 (más fallback por URL) | 5 sólo por URL: Comparador, Marquesina, Línea de tiempo, Reproductor de audio, Panel lateral |
| `loading` decidido por WordPress | 36 | forzado en Marquesina, Línea de tiempo, Audio; por índice en los dos sliders |
| `decoding="async"` | 36 | faltan 9 |
| Movimiento reducido | 45 | — |
| Iframes diferidos, con título y sin rastreo | 5 de 5 | sin fachada: cargan al entrar en pantalla |
| JSON-LD propio | 0 | — |
| Foco visible propio | la mayoría | Línea de tiempo, Slider Optimizado, Encabezado animado |
| Scripts con `defer` | 0 de 14 | todos en pie de página sin estrategia |

Salto de diseño: el Comparador de imágenes no reserva proporción. Hojas grandes: Enlace animado 11 KB (15 efectos), Botón 4.7 KB, Navegación con iconos 4.6 KB, Logotipos 4.4 KB. Suma de CSS propio 79 KB y de JS propio 62 KB, repartidos por widget.

Extensiones propias (degradado de texto, filtro de fondo, notación, sombra, figuras, enlace de elemento, flotación): cumplen; las cuatro primeras van por selectores de control sin recursos y las otras tres encolan sólo al pintar un elemento que las usa.

Derivados de PRO Elements (95 widgets): Elementor carga sus recursos por widget, pero con sus librerías (Swiper 144 KB en Media carousel, Testimonial carousel, Reviews, Slides, Nested carousel y Loop carousel; Lottie 253 KB; Prism; smartmenus; imagesloaded), y dos paquetes van en toda página con Elementor sin importar qué widgets haya: `pro-elements-handlers` 292 KB (incluye los popups) y `elementor-pro-frontend` 80 KB. El derivado no trae fuentes (`assets/dev` no existe): el paquete de handlers no puede recortarse dentro del plugin, sólo condicionarse.

Núcleo incluido (Elementor 4.3.5, copia sin cambios): Elements no fija sus ajustes de rendimiento. Quedan dos experimentos estables: `e_font_icon_svg` (iconos SVG en línea en vez de Font Awesome; activo sólo en sitios nuevos) y `e_optimized_markup` (un `div` menos por widget; inactivo por defecto). Los demás son ajustes: `elementor_css_print_method`, `elementor_font_display`, `elementor_element_cache_ttl`, `elementor_optimized_image_loading`, `elementor_optimized_gutenberg_loading`, `elementor_load_fa4_shim`.

## Arquitectura y reglas de entrega

- **Un generador de imágenes** (`Image_Markup`, en `modules/digitalisimo-widgets/`) para todos los widgets propios: recibe el valor de un control MEDIA o GALLERY, el tamaño, la clase, el ALT de respaldo y el `sizes` (por columnas y breakpoint, o ancho fijo), y devuelve el `<img>` por `wp_get_attachment_image()` o, sin adjunto, un `<img>` con las mismas decisiones de WordPress. Ningún widget escribe `<img` a mano.
- **Scripts condicionales** por `get_script_depends()` leyendo los ajustes de la instancia; la instancia de registro (`is_type_instance()`) declara todo para el editor. Registro con `strategy => defer` (WordPress 6.3+); el modo de diferido de DIGITALÍSIMO SEO no debe duplicarlo ni contradecirlo.
- **Fachadas** como opción por widget (activa por defecto en contenido nuevo, desactivable), con portada de Medios o de oEmbed cacheada por sitio, botón con nombre accesible y el iframe inyectado al pulsar; sin JavaScript, enlace al vídeo.
- **Carga condicional de PRO**: `pro-elements-handlers` y `elementor-pro-frontend` sólo cuando la página (documento, plantillas del Theme Builder y popups asignados) contiene un widget PRO, un popup, efectos de movimiento o sticky. Se decide antes de imprimir cabeceras y se prueba en Playground con cada combinación.
- **Defaults del núcleo** sólo donde el sitio nunca eligió (opción `elementor_experiment-*` en `default`, ajuste ausente), por sitio y por red, reversibles desde los ajustes de Elementor. «Marcado optimizado» nunca por defecto.
- **Presupuesto en CI**: `tests/elements-front-budget.php` lee cada widget propio y falla si rompe un criterio estático; `tests/playground/front-budget.sh` mide en Chrome cada widget contra una página vacía y guarda bytes, peticiones, CLS, tareas largas y nodos en `tests/playground/front-budget/baseline.json`; el arnés falla si un widget supera su presupuesto o pierde una comprobación.
- Paridad sitio y red en cualquier ajuste nuevo (ver `AGENTS.md`). Cada lote: versión, ZIP sólo de Elements, roadmap y este documento.

## Fases y puertas

**Fase 0 · Medición.** Arnés `tests/playground/front-budget.sh`: una página por widget propio con ajustes representativos, una página vacía de referencia y una con los widgets PRO pesados; por página, bytes por tipo, peticiones, jQuery presente, handles de Elements, CLS, tareas largas, nodos, imágenes sin dimensiones o sin `srcset`, iframes sin diferir. Lighthouse 13.5 móvil local para las tres páginas de referencia. Puerta: `docs/measurements/<fecha>-elements-front-budget.md` con la tabla y `baseline.json` guardado.

**Fase 1 · Widgets propios al 100 %.** Generador de imágenes y migración de los 17 widgets con imágenes; proporción en el Comparador; `decoding`; sin `loading` forzado; `sizes` por columnas; scripts condicionales en los seis widgets; `defer`; QR sin jQuery; hoja por efecto en Enlace animado y por variante en Botón, Navegación y Logotipos; foco propio en los tres enlaces; `content-visibility` en Tabla, Línea de tiempo y cuadrículas; `preconnect` por embed; etiqueta de encabezado elegible en Reseñas de Google. Puerta: `tests/elements-front-budget.php` en verde para los 45, arnés de Fase 0 sin regresión y con las cifras nuevas, unitarias existentes, `interaction.sh` en verde.

**Fase 2 · Núcleo y PRO sin decisión de producto.** Carga condicional de los dos paquetes globales de PRO; defaults del núcleo (iconos SVG, `font-display: swap`, caché de elementos, carga optimizada de imágenes) con opción de red y de sitio para desactivarlos; Lottie y Prism sólo al entrar en pantalla o por lenguaje si el derivado lo permite. Puerta: página de referencia vacía sin `pro-elements-handlers`; página con popup o widget PRO con ellos; `core-switch.sh` y `brand-audit.sh` en verde; Lighthouse de Fase 0 repetido.

**Fase 3 · Fachadas.** Reproductor de video, Slider de dispositivos y Panel lateral con fachada para YouTube, Vimeo, Dailymotion y Wistia; Mapa como imagen estática con «Ver mapa interactivo»; Visor de documentos como enlace con iframe al pulsar. Puerta: ninguna petición al dominio del vídeo antes del clic; teclado y lector de pantalla; `interaction.sh` ampliado.

**Fase 4 · Decisiones de producto.** Ocultar del panel los widgets PRO con equivalente propio más ligero (Media carousel, Testimonial carousel, Reviews, Slides, Nested carousel, Lottie), conservándolos registrados para el contenido existente; «Marcado optimizado» como opción. Requiere aprobación del propietario con las cifras de las fases anteriores.

## Riesgos y reversión

- La carga condicional de PRO puede dejar sin script un popup o una plantilla del Theme Builder: se detecta por documento, plantillas asignadas y popups de la página, con un filtro para forzar la carga y pruebas de cada combinación en Playground.
- Las fachadas cambian la experiencia (un clic para reproducir): opción por widget, activa sólo en contenido nuevo.
- Los defaults del núcleo se aplican sólo donde el sitio nunca eligió y se revierten desde Elementor; «Marcado optimizado» queda fuera de los defaults.
- Toda fase se publica como versión de Elements con su ZIP anterior en `rollback/`.

## Registro de avance

- 2026-10-16 · 4.3.0.152 · Objetivo redactado con la línea base de la auditoría estática. Fase 0 en curso.
- 2026-10-16 · 4.3.0.153 · Fase 0 publicada: arnés `tests/playground/front-budget.sh` con `baseline.json` y medición en `docs/measurements/2026-10-16-elements-front-budget.md`. Fase 1 publicada: generador único de imágenes en los 45 widgets, scripts condicionales en seis, `defer` en los catorce registros, QR sin jQuery, hojas por variante (Enlace animado, Botón, Navegación con iconos, Logotipos), foco propio, `content-visibility`, `preconnect` y etiqueta de encabezado en Reseñas; `tests/elements-front-budget.php` vigila el presupuesto en CI. Pendientes de la fase 1: ninguna. Siguiente: Fase 2.
