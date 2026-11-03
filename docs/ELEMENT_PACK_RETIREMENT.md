# Migrar un sitio o una red a DIGITALÍSIMO Elements

Guía operativa para dejar de usar Elementor, Elementor Pro, PRO Elements, Element Pack (Pro o Lite), el Element Pack Addon y Prime Slider (Pro o Lite) sin que cambie lo que ven los visitantes. Complementa el [objetivo de migración](ELEMENT_PACK_MIGRATION_OBJECTIVE.md) (qué se adaptó y cómo se validó), la [tabla de decisiones](element-pack-decisions.md) (qué pasa con cada widget de Element Pack), la [de Prime Slider](prime-slider-map.json), el diseño de la etapa F8 ([fusion/f8.md](fusion/f8.md)) y [ELEMENTOR_CORE.md](ELEMENTOR_CORE.md) (el núcleo de Elementor incluido en DIGITALÍSIMO Elements).

DIGITALÍSIMO Elements no contiene ni nombra nada de Element Pack ni de Prime Slider. Toda la compatibilidad y la migración están en **DIGITALÍSIMO Migración** (`digitalisimo-migration`), un plugin que sólo se instala mientras dura la migración. Migración 1.0.21 exige DIGITALÍSIMO Elements 4.3.0.170 o posterior: con uno anterior, «Migrar» no empieza y lo dice.

## Un solo paso: Migrar

1. Instala y activa DIGITALÍSIMO Elements y DIGITALÍSIMO Migración (en una red, los dos en toda la red). Haz un respaldo.
2. Abre la herramienta y lee el **análisis previo**, que se calcula por trozos al abrirla: por documento, qué se convierte, qué se aproxima, qué se crea, qué no tiene equivalente y qué queda para revisar; qué cabeceras, pies y plantillas del Theme Builder de Element Pack se copian; y, en un recuadro, el JavaScript de Element Pack o Prime Slider que dejará de ejecutarse.
3. Pulsa **Migrar**:
   - En un sitio: **Herramientas → Migrar a DIGITALÍSIMO Elements → Migrar**.
   - En una red: **Red → Ajustes → Migrar a DIGITALÍSIMO Elements → Migrar toda la red**. La pantalla dice que modifica todos los sitios de la red.
   - Desde la terminal: `wp digitalisimo-migration --run --url=https://sitio/` o `wp digitalisimo-migration --run --network`.
4. Lee el informe que queda en la misma pantalla. Sólo lo que está en «Pendiente de revisar» pide algo a una persona, con el enlace a cada página.
5. Cuando hayas comprobado el sitio, borra desde Plugins los que el informe da por listos para borrarse y, al final, DIGITALÍSIMO Migración (antes, si quieres volver atrás, revierte: ver «Restaurar»).

No hay más botones ni pasos. Si cierras la pantalla, la migración sigue con el WP-Cron del sitio y la pantalla la retoma al volver. Pulsar Migrar otra vez es seguro: sólo hace lo que quedó pendiente, y un documento ya migrado no se vuelve a convertir.

## Qué hace Migrar en cada sitio

La migración es una tarea por etapas que avanza en peticiones cortas (`legacy/class-runner.php`). Un cerrojo atómico, propio de cada petición y renovado con cada documento, impide que dos pestañas, el WP-Cron o la terminal la ejecuten a la vez. Una etapa que avanza por partes o que espera nunca se salta; una que tumba la petición dos veces seguidas se salta, y ese salto nunca desactiva Element Pack ni da por convertido lo que no se convirtió. Un proceso que arrancó antes de que la migración desactivara plugins se pausa sin trabajar.

1. **Prepara la plataforma de Element Pack** mientras Element Pack sigue activo, sin publicar nada:
   - copia cada plantilla del Theme Builder de Element Pack (cabecera, pie, entrada, página, archivo, búsqueda, 404 y WooCommerce) al Theme Builder de DIGITALÍSIMO Elements, en borrador, con su tipo, sus condiciones traducidas y una prioridad manual que conserva cuál se veía;
   - crea sus fuentes propias con la misma familia y los mismos archivos, y sus conjuntos de iconos con una copia segura (la pestaña vieja sigue funcionando hasta convertir cada documento);
   - prepara el CSS y el JavaScript globales de Element Pack y Prime Slider como Código personalizado en borrador, y guarda la barra lateral.
2. **Desactiva los plugins sustituidos**, sin borrar nada:
   - en una sola escritura y antes que nada, el grupo de Element Pack: el Addon junto a Element Pack (Pro o Lite), para que el Addon nunca quede activo sin él. Un plugin que parece el Addon y no se reconoce deja Element Pack activo y lo dice;
   - Elementor Pro, PRO Elements y Elementor (éste sólo si el núcleo incluido puede sustituirlo; si el plugin Elementor era más nuevo, el informe lo dice);
   - Element Pack **no** se desactiva si la preparación no terminó, si una cabecera o un pie que se ven hoy no tienen una copia que pueda publicarse o si el Theme Builder de DIGITALÍSIMO Elements no está disponible: se desactiva lo demás y el informe dice por qué. El sitio sigue como estaba. Con Elementor Pro o PRO Elements activos en el sitio, Migrar los desactiva primero y sigue sola en la petición siguiente, cuando el Theme Builder ya carga.
   - Prime Slider **sigue activo**: sus widgets se convierten con sus propios valores por defecto y se retira al final.
   - Justo antes, el widget «Plantilla» de la barra lateral pasa al widget Biblioteca. La petición que desactiva termina ahí.
3. **Publica las copias del Theme Builder** solas, en la primera petición sin Element Pack (si Element Pack vuelve, vuelven a borrador): ninguna página se queda sin cabecera ni muestra dos. Las convierte antes que nada.
4. **Recupera conversiones antiguas**: páginas sobrescritas por Migración 1.0.13 o anterior y, una vez por sitio, reglas de visibilidad perdidas con 1.0.10 o anterior.
5. **Convierte el contenido** de cada documento al formato propio (`Native`), con copia del original en `_digitalisimo_ep_backup` (y de los ajustes de la página en `_digitalisimo_ep_backup_page`):
   - los widgets de Element Pack, a su destino propio o de PRO Elements sólo con ajustes que ese destino conoce; los estilos que sólo tenía Element Pack, al CSS personalizado del elemento. Un widget cuyo destino no está registrado queda guardado en un marcador oculto y va a «Pendiente de revisar»;
   - **18 extensiones** de Element Pack: información emergente y distintivo a los rasgos de DIGITALÍSIMO Elements; transformaciones, paralaje, fijar al desplazar, revelar, superposición, igualar alturas y las demás, a controles nativos o al CSS del elemento; el JavaScript de la página, a un borrador de Código personalizado que nunca se ejecuta solo. Una conversión nunca pisa un ajuste que el elemento ya tenía. Las **10 descartadas** (background-image-parallax, particles, cursor-effects, ripple-effects, tile-scroll, hash-link, confetti-effects, sound-effects, content-protector y adblock-detector) se retiran y se informan por documento; sus datos quedan en la copia;
   - los **57 widgets de Prime Slider**: el contenido fijo, al slider anidado de DIGITALÍSIMO Elements (o a Diapositivas si el sitio no tiene contenedores anidados), al carrusel de Logotipos, al Carrusel multimedia, a Pestañas destacadas o a contenedores a pantalla completa; las redes y el botón «Scroll Down», como hermanos del slider; entradas, productos y eventos, al Loop Carousel con una plantilla Loop Item que la migración crea, o al widget Productos en carrusel. Sus imágenes de demostración se copian a Medios. Ningún perfil, vídeo ni enlace del proveedor llega a lo convertido;
   - iconos de Element Pack al conjunto nuevo, etiquetas dinámicas con equivalente exacto a las del constructor (las demás quedan con su valor fijo), reglas de visibilidad, figuras decorativas, enlace del elemento, máscaras y separadores como hasta ahora.
   - Un documento migrado con Migración 1.0.20 o anterior sólo recibe reparaciones de referencias (iconos, etiquetas dinámicas, marcadores); sus restos se informan.
6. **Convierte las revisiones y los autoguardados**, cada uno en su propio registro y con su propia copia.
7. **Convierte los marcadores** que DIGITALÍSIMO Elements conservaba con contenido de Element Pack o Prime Slider (kits importados sin esos plugins). El editor ya los mostraba convertidos, sin crear nada.
8. **Termina la plataforma**: plantillas con el contenido de cada desplegable del Mega Menu (el widget pasa al Menú con el mismo menú de WordPress), ajustes del kit de Element Pack retirados con respaldo, CSS global publicado si quien pulsó Migrar tiene permiso de HTML sin filtrar y el CSS pasa la validación (el JavaScript, siempre en borrador), muestras de variación y el inventario de lo que queda (contraseñas de aplicación de la IA de Element Pack y claves de servicios, sólo informadas; shortcodes; residuos).
9. **Actualiza los widgets fusionados** de DIGITALÍSIMO Elements (alias) como hasta ahora, salvo el tipo cuyas clases nombra otro CSS del sitio.
10. **Retira Prime Slider** si ya no queda ningún widget suyo sin convertir.
11. **Vacía las cachés** de Elementor y deja el informe.

Tras migrar, cada documento convertido no contiene ningún identificador de Element Pack, de Prime Slider ni de un alias y se pinta sólo con DIGITALÍSIMO Elements.

### Lo que queda para una persona («Pendiente de revisar»)

- **Widgets sin equivalente** de Element Pack: dejan de mostrarse sin él (criptomonedas, redes sociales con API retirada…). El informe lista cada uno con su motivo, su alternativa y las páginas que lo usan.
- **Plantillas del Theme Builder que no pudieron copiarse** con su alcance exacto (quedan en borrador sin condiciones), cabeceras o pies que impiden desactivar Element Pack y lo que cambia por tipo (entrada, archivo, búsqueda, 404) si su copia queda en borrador.
- **Mega Menu:** la navegación se conserva; los paneles con contenido hay que rehacerlos con las plantillas creadas.
- **JavaScript** de Element Pack y Prime Slider en borrador, CSS global que no se pudo publicar y superposiciones con imagen.
- **Conflictos** de una extensión con un ajuste propio del elemento, y diferencias de Prime Slider que cambian una página publicada (imágenes de demostración que no se pudieron copiar, respaldos).
- **Páginas sobrescritas sin origen** del que recuperarlas, y **alias que se quedan** por CSS ajeno.
- **Plugins que siguen activos**, con el motivo. Element Pack no se da por borrable mientras quede contenido suyo por convertir ni, en una red, hasta que el resumen de la red lo diga; Prime Slider, mientras quede un widget suyo sin destino.

Requisitos que la migración no puede cubrir: los plugins de los que dependen los widgets siguen activos (WooCommerce, Secure Custom Fields o ACF, The Events Calendar para el calendario de eventos de Prime Slider, bbPress, BdThemes Testimonials, Give, Charitable, EDD, FooEvents, los formularios de terceros…); y los widgets de PRO Elements que sirven de destino no están desactivados en Elementor → Ajustes → Funciones.

## En una red

Cada petición de WordPress carga los plugins del sitio que la atiende, así que cada sitio se convierte en sus propias peticiones, con sus plugins. **Migrar toda la red**:

1. Si DIGITALÍSIMO Elements está activado en toda la red (si no, el informe lo explica y no toca nada de la red): desactiva en toda la red, al inicio, todo lo retirable salvo Element Pack (en la liberación) y Prime Slider (al final). Elementor Pro y PRO Elements nunca conviven con copias publicables. Los sitios archivados, marcados como spam o borrados quedan fuera y se listan.
2. Encola la migración de cada sitio con DIGITALÍSIMO Elements, autorizada por quien la lanzó.
3. **Barrera:** con Element Pack activado en toda la red, cada sitio prepara sus plantillas y espera («Esperando a la migración de la red»). La red desactiva Element Pack, en una sola escritura con el Addon, cuando todos los sitios con algo que preparar están listos. La liberan el paso de la pantalla de red, WP-CLI, el paso de cualquier sitio en espera y un evento del WP-Cron del sitio principal cada 5 minutos, así que la pantalla puede cerrarse.
   - Si ningún sitio avanza en 15 minutos (o se llega al tope de una hora, o 2 minutos por sitio si es más), la red libera igual y lista los sitios que no prepararon: ésos se quedan sin la cabecera de Element Pack hasta su turno.
   - Si un sitio tiene una cabecera o un pie visibles sin copia publicable, la red **se detiene sin desactivar nada** y enlaza a la herramienta de ese sitio: corrígelo y vuelve a pulsar.
   - El Addon nunca queda cargado sin Element Pack Pro, aunque uno esté activado en la red y el otro en un sitio: el informe lo dice cuando no puede desactivarse.
4. La pantalla conduce los sitios de dos en dos con peticiones a la administración de cada uno. Un sitio que el navegador no alcanza (dominio propio) completa su migración con su WP-Cron o al abrir su herramienta.
5. Al terminar, retira Prime Slider de la red si ningún sitio lo necesita, y el resumen dice qué plugins ya no están activos en ningún sitio y pueden borrarse desde Red → Plugins.

`wp digitalisimo-migration --run --network` hace lo mismo: arranca la red, migra cada sitio, libera la barrera (y vuelve a lanzar una vez los sitios sin preparar), migra cada sitio otra vez y cierra. La herramienta de cada sitio sigue disponible: «Migrar» de un solo sitio con Element Pack activado en toda la red convierte lo que puede y deja Element Pack para la red.

## Restaurar

`wp digitalisimo-migration --revert --url=https://sitio/` deshace la migración en orden inverso: primero la plataforma (copias del Theme Builder, fuentes, iconos, Código personalizado, plantillas del Mega Menu, muestras, kit y barra lateral), después las plantillas Loop Item y las imágenes de demostración que creó la migración, y por último cada documento a su copia original, también las revisiones convertidas. Exige Element Pack activo (si no, el sitio se quedaría sin cabecera): reactívalo antes. Lo que alguien editó después de migrar no se toca y se informa; `--force` lo restaura igualmente. Lo creado va a la papelera, o a borrador si la papelera está desactivada; nunca se borra un archivo ni un original.

**Antes de desinstalar DIGITALÍSIMO Migración:** si reactivas Element Pack sin Migración 1.0.21 cargada, se pintan a la vez las plantillas de Element Pack y sus copias publicadas. Si quieres volver a Element Pack, revierte primero. Si algo del núcleo falla, reactivar el plugin Elementor hace que vuelva a mandar él sin perder nada.

## Validación disponible en el repositorio

- Unitarias de Migración (con php-wasm o PHP, en los dos workflows): `tests/migration-runner.php` (etapas, protocolo de avance, salto seguro, pausas, tareas de 1.0.20), `migration-network.php` (barrera y dependencias entre ámbitos), `migration-theme-builder.php` (copias, condiciones, publicación perezosa y «exactamente una cabecera»), `migration-platform.php`, `migration-effects.php`, `migration-prime-slider{,-decisions,-dynamic}.php`, `migration-markers.php`, `migration-report.php`, `migration-end-to-end.php`, `migration-scale.php` y `migration-legacy-baseline.php` (los 193 fixtures iguales a 1.0.20).
- `bash tests/playground/f8.sh` (puertos 9510–9519): A/B de Prime Slider Lite 4.7.0 oficial, Prime Slider Pro y Element Pack sintéticos, sondeo de la portada en cada paso (exactamente una cabecera), red con barrera (también con la pantalla cerrada, un sitio que no responde, uno bloqueado y 27 sitios), marcadores, presupuesto, interacción, marca, vuelta atrás, reversión y A/B de Element Pack Lite 10.0.1 oficial. Los 18 escenarios en verde en la publicación de 1.0.21; evidencia en `docs/fusion/f8-evidence.json`.
- `bash tests/playground/migrate.sh` y `bash tests/playground/legacy-ab.sh`: montan Element Pack Lite 10.0.1 oficial (el escenario P10 de `f8.sh`, en verde: widgets, las 8 extensiones de Lite y la red, con la firma de estilos computados). Element Pack Pro, el Addon y Prime Slider Pro no se instalan (sus copias de `reference/` tienen la licencia anulada): su A/B es una excepción declarada.
- `bash tests/playground/interaction.sh`, `bash tests/playground/form-submit.sh` y `bash tests/playground/migration-report.sh`: widgets con JavaScript, formularios heredados y pantallas de red y de sitio.
