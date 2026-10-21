# Front de DIGITALÍSIMO Elements · Fase 2 (F-front) · 2026-10-21

Medición de la Fase 2 de `docs/ELEMENTS_FRONT_OBJECTIVE.md` (etapa F-front de `docs/FUSION_OBJECTIVE.md`): la puerta de los paquetes globales de PRO, los valores por defecto del núcleo, las librerías locales y diferidas, y el recálculo del blindaje de fuentes de DIGITALÍSIMO SEO. Procedimiento de §5.7 de la especificación, con el arnés de la rama (`tests/playground/front-budget.sh`, `front-network.sh`, `core-switch.sh`).

## Qué se midió

- **Antes:** el ZIP publicado de DIGITALÍSIMO Elements 4.3.0.162 (Release `v2026.10.21.416`, `digitalisimo-elements-4.3.0.162.zip`, sha256 `61c4137cbd235c781bee2d3c6c5ef199162fa0c27f82fe73891811e8f01e330e`; su contenido es idéntico al de `dist/`), extraído y medido con `ELEMENTS_DIR=… EXPECT=0 BASELINE=write LIGHTHOUSE=1`.
- **Después:** el código de la rama `fusion/f-front` (huella del árbol de `digitalisimo-elements/` sin `elementor/`: `194716dc62a501ee`, la misma en todas las corridas; la cabecera aún dice 4.3.0.162 porque el frente G sube la versión). Modo normal con `BASELINE=write LIGHTHOUSE=1` (línea base nueva en `tests/playground/front-budget/baseline.json`) y cada modo de §5.3 con sus casos por defecto.
- **Entorno:** WordPress Playground `@wp-playground/cli@3.1.56` (WordPress 7.1.3, PHP 8.5, un proceso), núcleo incluido 4.3.5 salvo en `CORE=plugin` (Elementor 4.3.5 de downloads.wordpress.org), Chrome sin interfaz y **sin caché del navegador**, Lighthouse 13.5.0 móvil con limitación simulada. Cada corrida monta una copia fija del plugin tomada al empezar. El equipo estaba cargado (media de 10 a 20 procesos en 8 núcleos): bytes, peticiones y decisiones son deterministas; LCP, TBT y los tiempos son sólo orientativos.
- Playground sirve los archivos **sin compresión**: los bytes de este documento son tamaños reales de archivo, no de transferencia.

## Resultado principal

| Caso | Peticiones antes → después | JS antes → después | Bytes totales antes → después | Paquetes de PRO | Razón de la puerta (después) |
|---|---:|---:|---:|---|---|
| `empty` | 28 → 24 | 707 904 → 325 237 B (−382 667) | 1 077 219 → 692 148 B | sí → no | — |
| `all-own` (doce widgets propios) | 43 → 39 | 721 254 → 338 587 B (−382 667) | 1 134 622 → 749 689 B | sí → no | — |
| `core-only` (widgets del núcleo) | 31 → 27 | 735 501 → 352 834 B | 1 142 380 → 757 309 B | sí → no | — |
| `pro-static` (PRO sin controlador) | 34 → 30 | 707 904 → 325 237 B | 1 169 617 → 784 546 B | sí → no | — |
| `third-party-free` | 26 → 22 | 707 904 → 325 237 B | 1 074 371 → 689 300 B | sí → no | — |
| `icons` | 31 → 27 | 707 904 → 325 237 B | 1 113 084 → 728 013 B | sí → no | — |
| `pro-heavy` (Media carousel, Lottie, Slides) | 39 → 39 | 1 109 721 → 1 109 721 B | 1 670 765 → 1 670 813 B | sí → sí | `class:media-carousel` |
| `pro-popup` | 29 → 29 | 719 526 → 719 526 B | 1 089 122 → 1 089 174 B | sí → sí | `tag:popup`, `doc:63:popup` |
| `pro-light` (cuenta atrás) | 28 → 28 | 707 904 → 707 904 B | 1 112 108 → 1 112 151 B | sí → sí | `class:countdown` |
| `code` (resaltado con copiar) | 37 → 37 | 721 153 → 746 256 B | 1 107 929 → 1 133 385 B | sí → sí | `class:code-highlight` |
| `code-langs` (37 lenguajes) | 66 → 66 | 741 522 → 789 645 B | 1 144 008 → 1 192 205 B | sí → sí | `class:code-highlight` |

- Las páginas sin funciones avanzadas dejan de cargar `pro-elements-handlers` (291 600 B), `elementor-pro-frontend` (80 169 B) y, con ellos, `wp-i18n` y `wp-hooks`: **−382 667 B de JS y −4 peticiones** en `empty`, `all-own`, en los 45 widgets propios (los 45 con −4 peticiones y −382 667 B; el reproductor de YouTube, −383 861 B por el JS del tercero) y en los casos negativos: 51 de las 74 páginas quedan sin los paquetes. `ElementorProFrontendConfig` queda `undefined`. Ninguna página pierde `elementor-frontend` ni jQuery.
- Las páginas que usan PRO no cambian de bytes (diferencias de 40 a 50 B: el comentario de la sonda y la réplica de contadores no cuentan como JS, y los ID de los documentos son los mismos).
- CLS relativo a `empty` sin cambio en los 74 casos (73 idénticos a la milésima; la tabla de datos, 0,018 → 0,017). El CLS absoluto de `empty` es 0 en las dos corridas (el 0,476 de la medición del 2026-10-16 no aparece con este arnés).
- **Prism local:** `code` y `code-langs` ya no piden nada a `cdnjs.cloudflare.com` (antes, 13 249 y 33 618 B de terceros) y los mismos archivos salen del plugin. En Playground suman 25 456 y 48 197 B más porque se sirven sin compresión y cdnjs los servía comprimidos: los bytes de los archivos son los mismos (frente D, `assets/lib/prism/MANIFEST.json`); en un servidor con gzip o brotli la diferencia desaparece.

### Lighthouse 13.5.0 (móvil, local)

| Página | Antes: puntuación · LCP · TBT · CLS · `total-byte-weight` | Después | Diferencia de bytes |
|---|---|---|---:|
| `empty` | 67 · 5 926 ms · 29 ms · 0 · 958 922 B | 75 · 3 992 ms · 26 ms · 0 · 572 854 B | **−386 068 B (−40,3 %)** |
| `all-own` | 66 · 6 558 ms · 25 ms · 0 · 1 035 546 B | 74 · 4 440 ms · 0 ms · 0 · 649 616 B | **−385 930 B (−37,3 %)** |
| `pro-heavy` | 61 · 6 715 ms · 62 ms · 0 · 1 557 807 B | 57 · 8 664 ms · 125 ms · 0 · 1 557 847 B | +40 B (0,00 %) |

`lhr.lighthouseVersion` 13.5.0 en las seis pasadas y ningún `{error}`. LCP y TBT de `pro-heavy` empeoran por la carga del equipo durante esa pasada (los bytes son los mismos); se dan sólo como información.

## Modos (§5.3)

| Modo | Elements · huella | Git | Mediciones | Problemas | Duración | Resultado |
|---|---|---|---:|---:|---:|---|
| normal, antes | 4.3.0.162 · `5b896dbf09ec55b3` | `d73d8fc` | 74 | 0 (sólo mide) | 547 s | paquetes de PRO en todas las páginas |
| normal, después | rama · `194716dc62a501ee` | `d73d8fc` | 74 | 0 | 562 s | cada `expect` y cada comprobación funcional en verde; línea base nueva |
| normal, arnés final | rama · `194716dc62a501ee` | `ed75ea8` | 74 | 0 | 564 s | comparada con la línea base nueva: «Sin regresiones» |
| `CACHE=1` | rama | `d4d2222` | 25 | 0 | 390 s | mismas decisiones con la caché de elementos activa (segunda visita) |
| `CORE=plugin` | rama | `d4d2222` | 26 | 0 | 240 s | Elementor 4.3.5 activo, núcleo incluido sin cargar, mismas decisiones, ningún filtro `default_option_`, `Core_Defaults::active()` falso |
| `SEO=1` | rama | `d73d8fc` | 2 | 0 | 86 s | `defer` en `jquery-core`, `jquery-migrate`, `elementor-frontend-modules` y `elementor-frontend` (`empty`) y en `pro-elements-handlers` y `elementor-pro-frontend` (`pro-popup`); popup visible al pulsar; `digitalisimo_performance_js_fallback` vacía |
| `SEO=guard` | rama | `37fe9ef` | 1 | 0 | 95 s | antes de la transición se sirve `digitalisimo-57be82d382fd6315-lato.css` (`auto`); tras la transición, una visita y dos pasadas de cron, `digitalisimo-c8d78ca5849681e5-lato.css` con `swap` |
| `THEME=1` | rama | `d4d2222` | 10 | 0 | 126 s | `tb-header-core` sin paquetes (275,5 KB de JS) y `tb-header-pro` con ellos (`class:nav-menu`) en página, archivo, búsqueda, 404 y página clásica |
| `MIGRATION=1` | rama | `d4d2222` | 3 | 0 | 94 s | `class:bdt-navbar` (SmartMenus inicializado, submenú abierto), `class:bdt-contact-form` (envío con «Your submission was successful.»), `class:bdt-countdown` (los segundos cambian) |
| `LAZY=1` | rama | `d4d2222` | 6 | **2** | 109 s | falló antes del ajuste: con el tema de bloques por defecto, Lottie y Prism se cargaban al inicio (hallazgo 1) |
| `LAZY=1` (con el ajuste de `declares()`, `NORMAL_FILE` de la corrida normal final) | rama | huella `b48d19524345d3f0` | 6 | 0 | 94 s | con el tema de bloques de Playground: `lottie-below` y `code-below` sin Lottie ni Prism al cargar; `lottie-above` con Lottie y CLS 0,122 → 0,122 frente al modo normal |
| `THEME=1 LAZY=1` | rama | `37fe9ef` | 3 | 0 | 102 s | con un tema clásico el cargador funciona: `lottie-below` sin Lottie hasta desplazar (666 283 B de JS frente a 917 353), `code-below` pide core, autoloader y gramática al acercarse, `lottie-above` con CLS 0 → 0 (referencia `THEME=1`) y primer `svg` a 4 297 ms frente a 3 477 ms |
| `V4=1` | rama | `d73d8fc` | 1 | 0 | 71 s | `v4-only` sin paquetes, con `elementor-frontend` (D19) |
| `DEFAULTS=old` | rama | `d4d2222` | 2 | 0 | 75 s | antes: `display=auto`, Font Awesome en `icons`, `eicons` en `empty`; la primera visita agenda la transición; tras `wp-cron.php`: `swap`, iconos sin cambio, CLS 0 → 0 |
| `DEFAULTS=old-applied` | rama | `d4d2222` | 2 | 0 | 112 s | «Aplicar» por la pantalla (`front_notice=icons-applied`): sin Font Awesome ni `eicons`, 5 iconos SVG; sólo queda el `<i class="fa fa-star">` del icono FA4 sin migrar, que el núcleo pinta así en cualquier modo |
| `DEFAULTS=old-applied CACHE=1` | rama | `2eddea9` | 2 | 0 | 144 s | caché caliente con 5 `<i>` antes de aplicar; después 5 `<svg>` y ningún `<i>` de iconos migrados |
| `DEFAULTS=old-dirty` | rama | `2eddea9` | 1 | 0 | 98 s | red (`http://digi-fb.test`), `svg_icons` heredado: veredicto `findings`, hallazgo `custom_css` «.fa-star{}» en `?digi_probe=state`, no se aplica |
| `DEFAULTS=old-inactive` | rama | `2eddea9` | 2 | 0 | 129 s | experimento guardado `inactive`: «Aplicar» no cambia nada (Font Awesome sigue) |
| `DEFAULTS=off` | rama | `37fe9ef` | 1 | 0 | 58 s | seis interruptores apagados: paquetes en `empty` (`always:pro_conditional`) y `display=auto` |

Git cambia entre corridas porque el respaldo automático de la rama hizo commits del arnés mientras se medía; el código del plugin (la huella) es el mismo en todas.

## Otras corridas

| Corrida | Resultado |
|---|---|
| `front-network.sh` | N1–N7 en verde (115 s): el sitio 2 hereda el apagado de la red y lo aplica con el evento en su blog; el sitio 1 conserva su valor propio; el sitio 3, creado después, hereda y transita; editor, administrador de sitio en la red y nonce de otro sitio terminan en 403; redirecciones a la misma superficie; «Heredar de la red» sólo en la pantalla del sitio; vaciados por sitio `{1: 1, 2: 2, 3: 1}` (uno por transición con cambios, ninguno en los demás); pantallas sin «Elementor», enlaces externos ni dominios de `vendors.json` |
| `core-switch.sh` (Elementor fijado 4.3.5) | 64 páginas iguales con el plugin Elementor y con el núcleo incluido; `default_option_elementor_font_display` ausente con el plugin (antes y después de la transición) y presente, con `swap`, en el núcleo incluido tras la transición, en los dos sitios |
| `shared-ui.sh` | 18 pantallas sin problemas, «Rendimiento» incluida |
| `conditions.sh` | 186 comprobaciones correctas |
| `form-submit.sh` | Contact Form, Webhook Form y Mailchimp heredados en los dos sitios |
| `interaction.sh` (`CORE=bundled` y plugin) | antes del ajuste del arnés fallaba en la rama por una carrera con el vaciado de la transición (hallazgo 2; 4.3.0.162 en verde con el mismo arnés). Con `interaction/transition.php` en la preparación (huella `b48d19524345d3f0`): 29 comprobaciones correctas en cada modo |
| `brand-audit.sh SCOPE=full` | en verde: 194 pantallas en español e inglés, «Rendimiento» incluida desde el menú; marca visible 0; plugins de origen 0 fallas y 0 latentes. Diez avisos del editor (paneles que no terminan de pintarse con el equipo cargado), que no hacen fallar |
| `kit-roundtrip.sh` | en verde (copia fija, núcleo incluido): R1 (3 395 s), R1c, R2, R3, R3b, R4, R4v4, R5, R6, R7, R8, R9 y R10; A1–A11, A13 y A14 en verde, A12 «no medido» (lo cubren los demás arneses); petición REST más lenta 17 834 ms |
| `migrate.sh`, `legacy-ab.sh` | no se corrieron: la copia de Element Pack de `reference/` no figura en `reference/OFFICIAL-SOURCES.txt` y `AGENTS.md` sólo permite instalar copias con su descarga oficial anotada |

## Hallazgos

1. **`LAZY=1` no difería nada con un tema de bloques** (frente D; **resuelto**: `declares()` declara el cargador mientras `wp_enqueue_scripts` no ha corrido, y si su registro falla `HANDLE` queda como alias sin script de las librerías; `tests/elements-local-libs.php` lo cubre y `LAZY=1` pasa). `Lazy_Libs::declares()` (`digitalisimo-elements/modules/digitalisimo-front/class-lazy-libs.php:85`) decide en el front con `wp_script_is( HANDLE, 'registered' )`, y el cargador se registra en `elementor/frontend/after_register_scripts` (`:58`), que el núcleo dispara desde `wp_enqueue_scripts` 5. Un tema de bloques (el de Playground, Twenty Twenty-Five) pinta la plantilla y el contenido **antes** de `wp_head`: en ese momento el handle aún no existe, el widget declara `lottie` o los handles de Prism, y además `_elementor_page_assets` guarda esa lista en el primer pintado, así que la página la conserva. Con el tema DIGITALÍSIMO (clásico) funciona. Hace falta que `declares()` no dependa del orden de pintado (por ejemplo, registrar el cargador antes de pintar el contenido o decidir sólo por el valor aplicado y dejar que `register()` resuelva la configuración más tarde).
2. **`interaction.sh` en la rama** (**resuelto** en el arnés: `tests/playground/interaction/transition.php` aplica la transición en la preparación, y los dos modos pasan) (`CORE=bundled`: `bdt-custom-carousel` con 404 de `uploads/elementor/css/post-11.css`; plugin: `bdt-marquee` con 500 y 404 de `post-5.css`, `base-desktop.css`, `base-mobile.css` y `post-24.css`). Los registros de Playground dicen «Could not read … There is no such file»: la transición (§3.2, D15) se ejecuta por WP-Cron mientras Chrome recorre las páginas y su `files_manager->clear_cache()` borra el CSS generado que una página ya pintada estaba pidiendo. Es el vaciado único declarado en los riesgos de §10, visible porque el arnés mide justo después de activar el plugin. Ese arnés (`tests/playground/interaction/`) no es de este frente: necesita aplicar la transición en su preparación antes de abrir Chrome. Una copia de diagnóstico del arnés con un paso más del blueprint (`Transition::run_now()` tras `setup.php`; aplicó `pro_conditional`) pasa entera con el núcleo incluido y con el plugin Elementor: 29 comprobaciones en cada una, el carrusel de medios y la marquesina incluidos.
3. **El blindaje de SEO en modo `auto` sólo crea copias si hay algo que recortar.** Con Roboto (la tipografía del kit por defecto), Google Fonts sirve hoy una fuente variable: todos los pesos comparten archivo y `kept_files()` los conserva todos, así que no hay copia y el `<link>` es el original. Es el comportamiento seguro y correcto; para ejercitar la ruta de copias, `SEO=guard` cambia el kit a Lato 400/700 (archivos estáticos) y aplica «Optimizar fuentes» por la sonda antes de la transición. El recálculo del frente F se agenda a `time() + 10`: el arnés espera a que venza antes de cada pasada de cron.
4. **`DEFAULTS=old-applied`:** el icono FA4 sin migrar (`icon: "fa fa-star"` sin `__fa4_migrated`) sigue pintándose como `<i class="fa fa-star">` sin Font Awesome: es el riesgo que el análisis de iconos ya detecta (`icon` sin migrar) y por el que los iconos en línea nunca se aplican solos.
5. **Menú de PRO en Chrome sin interfaz:** SmartMenus no trata el puntero simulado como ratón y el submenú no se abre con un *hover* sintético, ni con el menú nativo. La comprobación de `mig-navbar` usa la API de SmartMenus (`itemActivate`), que demuestra que el controlador de PRO inicializó el menú heredado. El adaptador fija `dropdown: none`, así que el menú heredado no tiene conmutador móvil.
6. **`cargar más` y filtros de bucle** dependían de una espera fija: bajo carga, `pro-loop` falló una vez en `CACHE=1` y en `CORE=plugin` (en 4.3.0.162 con caché pasa igual que en la rama al repetir). El arnés espera ahora a que crezca o cambie la lista (`waitCount`, `mark` + `waitChange`).

## Criterios de §9

| # | Criterio | Estado |
|---|---|---|
| 1 | Vacía sin PRO | **Pasa:** `empty` y `all-own` sin paquetes, `config:'undefined'`, −4 peticiones, −382 667 B de JS (`empty` 325 237 B ≤ 336 135), sin errores, CLS relativo igual |
| 2 | PRO donde hace falta | **Pasa:** las 23 páginas con paquetes son exactamente las esperadas, cada caso positivo con su razón y su comprobación funcional, también con `CACHE=1` |
| 3 | Negativos | **Pasa:** `core-only`, `pro-static`, `third-party-free` y `v4-only` sin paquetes; `v4-only` con `elementorFrontend` |
| 4 | Migración | **Parcial:** `MIGRATION=1` en verde; `migrate.sh` y `legacy-ab.sh` no se corrieron (copia de Element Pack sin descarga oficial anotada) |
| 5 | Plugin original | **Pasa:** `CORE=plugin` con las mismas decisiones y sin valores por defecto; `core-switch.sh` con sus dos `has_filter` |
| 6 | SEO | **Pasa:** `SEO=1` y `SEO=guard` |
| 7 | Valores por defecto | **Pasa:** `old`, `old-applied` (con y sin `CACHE=1`), `old-dirty`, `old-inactive` y `off` |
| 8 | Librerías | **Pasa:** `code` y `code-langs` (Prism local, ningún CDN, `.token` en los 37, ningún 404); `LAZY=1` con el tema de bloques tras el ajuste del hallazgo 1 (`lottie-above` 0,122 → 0,122) y `THEME=1 LAZY=1` |
| 9 | Red | **Pasa:** N1–N7 |
| 10 | Interfaz | **Pasa:** `shared-ui.sh` (18 pantallas) y `brand-audit.sh SCOPE=full` (194 pantallas) con la pantalla nueva |
| 11 | Lighthouse | **Pasa:** −386 068 B en `empty`, −385 930 B en `all-own`, `pro-heavy` +0,00 %, 13.5.0 sin errores |
| 12 | Regresión | **Pasa:** `CORE=bundled interaction.sh` e `interaction.sh` (29 y 29, con la transición en la preparación del arnés, hallazgo 2), `conditions.sh`, `kit-roundtrip.sh` y `form-submit.sh` |
| 13 | CI | `tests/elements-front-cases.php` en verde (801 comprobaciones, 30 recorridos con `Pro_Gate::walk()`, PHP 7.4, 8.2 y 8.5); las doce pruebas nuevas en `validate.yml` y `release.yml`, con listas iguales |
| 14 | Paquetes | Al publicar: `build-packages.sh`, `verify-release.mjs` con 8 ZIP y `unzip -t` |

## Para la integración

- **Hecho en la integración:** el ajuste de `Lazy_Libs::declares()` (hallazgo 1) y el paso de transición de `tests/playground/interaction.sh` (hallazgo 2).
- **Pendiente:** `migrate.sh` y `legacy-ab.sh` necesitan una copia de Element Pack con su descarga oficial anotada en `reference/OFFICIAL-SOURCES.txt`.
- La línea base nueva (`tests/playground/front-budget/baseline.json`) sale de la corrida normal «después» y la corrida con el arnés final la confirma sin regresiones.
