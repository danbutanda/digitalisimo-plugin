# Objetivo: fusión de constructores en DIGITALÍSIMO Elements

Este documento es el punto de entrada para cualquier agente que continúe la fusión. El objetivo es que **DIGITALÍSIMO Elements sea el único constructor** de los sitios: con las capacidades útiles de Divi, Betheme/BeBuilder, GeneratePress (GP Premium), Bricks (sólo por su documentación pública), Element Pack Pro y Prime Slider; con DIGITALÍSIMO Migración convirtiendo los sitios hechos con Element Pack y Prime Slider; con un tema propio mínimo, y con el código PHP propio de cada sitio en un editor de fragmentos de DIGITALÍSIMO Tools.

**Cambio de alcance (2026-10-20).** No se importan ni convierten temas, plantillas, kits ni opciones de Divi, Betheme, Bricks ni GeneratePress, y no hay paso de tema desde ellos: sus **beneficios** se integran en Elements (F7a, F7b y F4-Woo), y los **kits de Elementor**, propios y de terceros, se exportan e importan con todas las mejoras (K). F2, F3, F4 (la conversión), F5 y F6 se eliminan; sus secciones quedan como registro.

Todo sin romper lo existente: sin motores paralelos, sin otro sistema de kits y sin otro plugin de migración. Las reglas permanentes están en `AGENTS.md` (sección «Fusión de constructores»). La evidencia de la revisión (formatos, rutas, líneas y riesgos de cada origen) está en `docs/fusion/`. Si un informe de `docs/fusion/` y este documento no coinciden, manda este documento.

## Decisiones

| Decisión | Elegido | Fecha |
|---|---|---|
| Constructor | DIGITALÍSIMO Elements, único. Los demás se convierten y se retiran | 2026-10-16 |
| Motor de destino | Clásico (v3): contenedores flex o grid y widgets nativos o propios. v4 sólo donde ya esté activo; nunca se activa sin permiso | 2026-10-16 |
| Código personalizado | **Editor de fragmentos PHP en DIGITALÍSIMO Tools**, por sitio y por red. El tema sale **sin hijo** | 2026-10-16 |
| Hooks PHP de GeneratePress | Se importan al editor de Tools traducidos a `digitalisimo_*` y **desactivados**; sólo un administrador con permiso los revisa y activa | 2026-10-16 |
| Distribución | El tema vive en este repositorio y viaja en cada Release: **8 ZIP** (siete plugins y el tema). Su actualizador ofrece las versiones nuevas sin forzar la actualización automática | 2026-10-16 |
| Publicación | Cada etapa se trabaja en una rama `fusion/<n>` y **se publica en cuanto queda validada** («siempre publica») | 2026-10-16 |
| Primer incremento | Tema y base común de conversión, después de F0, F0.1 y F0.2 | 2026-10-16 |
| Muestras | Hay muestras reales de Divi, Betheme, Bricks y GeneratePress. Se guardan fuera de Git; al repositorio sólo llegan fixtures sintéticos o anonimizados | 2026-10-16 |
| Tema al migrar Divi, Betheme o Bricks | «Migrar» deja todo en borrador. En producción el administrador activa el tema tras la vista previa y la tarea termina sola. Fuera de producción el clic hace todo | 2026-10-16 |
| Presets vinculados | Sólo estilo, compilados en el CSS del kit. **Corregida el 2026-10-21** (F7a, fila «F7a · Presets de estilo») | 2026-10-16 |
| Widgets anidados | Un widget **nuevo** (slider) o los anidados del núcleo; nunca se cambia la clase ni el ID de un widget existente | 2026-10-16 |
| Interacciones con destino | Registro propio de acciones (`_digitalisimo_actions`), con lista blanca y sin JS arbitrario | 2026-10-16 |
| Rastro de los plugins de origen | **Ninguno**: ni enlaces, ni vídeos, ni cuentas, ni servicios, ni peticiones (F0.2) | 2026-10-16 |
| Slug del tema | `digitalisimo-theme`, definitivo. `digitalisimo-theme-child` no se distribuye | 2026-10-16 |
| Páginas de GP hechas con bloques del núcleo | Siguen en el editor de bloques | 2026-10-16 |
| Copias del tema que no están cargadas (D1) | Las cubre **DIGITALÍSIMO Tools** (`Theme_Updates`): inactivas, en otra carpeta o en otros sitios de la red. También es el respaldo de la copia cargada. No va en Elements, porque ningún módulo puede exigir otro | 2026-10-17 |
| `style.css` de un hijo de Hello (D2) | **Sólo se informa:** en Tools antes del cambio, y en la bitácora y en `wp digitalisimo-theme status` después. `wp digitalisimo-theme import --child-css` lo copia al CSS adicional sólo si se pide, porque pegarlo cambia su lugar en la cascada | 2026-10-17 |
| Alcance | **Sin importar ni convertir** Divi, Betheme, Bricks ni GeneratePress: sus beneficios se integran en Elements y los kits de Elementor funcionan con todas las mejoras. F2, F3, F4 (la conversión), F5 y F6 se eliminan; siguen F1, F1b, F-front, F7a, F7b, F4-Woo y F8, y se añade K. Las decisiones anteriores sobre esos orígenes (hooks de GP, muestras, tema al migrar, páginas de GP) quedan sin efecto | 2026-10-20 |
| K-D1 · Modo de importación | **«Sólo agregar» siempre por defecto**, también en un sitio vacío. «Nuevo» sólo si el administrador lo pide (pantalla «Kits» o `--mode=fresh`) **y** el sitio está vacío; si no, se rechaza con el motivo. Un Template Kit de Envato entra siempre en «agregar» | 2026-10-20 |
| K-D2 · Kit de destino | Siempre el kit activo, con respaldo; nunca se crea un kit nuevo. Los metadatos nativos quedan inertes, así la reversión nativa del kit no borra nada | 2026-10-20 |
| K-D3 · Personalización fina | Habilitada en la App (casillas por parte); el servidor fuerza siempre `theme` y `experiments` a `false` | 2026-10-20 |
| K-D4 · Flujo heredado 2.0 | La App lleva al importador 3.0. `wp elementor kit` y `wp elementor library import` con un kit o una URL terminan antes de descargar o de cambiar opciones y mandan a `wp digitalisimo-elements kit` | 2026-10-20 |
| K-D5 · Plugins que pide el kit | Nunca se instalan ni se activan: sólo se informan | 2026-10-20 |
| K-D6 · Medios | Dentro del kit por defecto: 20 MB por archivo y, en la App, `min(32 MB, memory_limit / 8)` en total (WP-CLI sin tope total). Sólo adjuntos del sitio actual, nunca por HTTP ni de otro sitio de la red | 2026-10-20 |
| K-D7 · Reimportación | Lo creado por el mismo kit se reconoce por procedencia en cualquier estado: se reutiliza si nada cambió; si no, borrador «actualización» o «conflicto». **Nunca se actualiza en el sitio** | 2026-10-20 |
| K-D8 · Estados en «agregar» | En borrador lo que se ve sin que nadie lo enlace (entradas de tipos visibles, plantillas con ubicación o condiciones, popups y botones flotantes); conservan su estado lo que sólo se ve incrustado, fuentes, iconos, adjuntos y menús. Siempre en borrador, en los dos modos: Custom Code y todo documento con una referencia de acceso sin destino | 2026-10-20 |
| K-D9 · Widget desconocido | Marcador oculto `digitalisimo-unconverted` con el original completo y un motivo (`unknown` o `component`). Sólo se registra si hay marcadores o una importación en curso | 2026-10-20 |
| K-D10 · Playground en CI | No. Unitarias en los dos workflows; `kit-roundtrip.sh` y `brand-audit.sh STATE=kit`, a mano antes de publicar y tras cada `sync-elementor-core.sh`, con su evidencia en `docs/fusion/` | 2026-10-20 |
| K-D11 · Ajustes del kit en «agregar» | No se aplican: los colores y tipografías que usa el contenido se añaden como personalizados; el resto, con «Aplicar los ajustes del kit», que se puede deshacer | 2026-10-20 |
| K-D12 · ZIP de kit en Plantillas → Importar | Se lleva al importador de kits con el kit ya preparado (token de un solo uso) | 2026-10-20 |
| K-D13 · Límites del guardián | 512 MB descomprimido, 5 000 entradas, relación 100:1, JSON ≤ 32 MB y profundidad ≤ 256, XML ≤ 64 MB sin `<!DOCTYPE` ni `<!ENTITY`, un nivel de ZIP anidado; por sesión, 512 MB de descargas y 20 s de red por petición, y ningún medio nuevo pasados 15 s de la petición (filtro `digitalisimo_elements_kit_guard_limits`) | 2026-10-20 |
| K-D14 · Plantillas de red de Tools | Mantienen su remapeo; K no cambia Tools | 2026-10-20 |
| K-D15 · SVG con la subida sin filtrar apagada | Se respeta: los separadores y máscaras propios se escriben saneados por K; los SVG de la biblioteca se omiten y se informan | 2026-10-20 |
| K-D16 · Documentos al revertir | A la papelera, como el núcleo | 2026-10-20 |
| K-D17 · Template Kits reales de Envato | Regresión obligatoria si el propietario aporta 3 o más (fuera de Git); si no, la fila de la matriz queda «sintético» con el riesgo abierto (`docs/fusion/k-envato.md`) | 2026-10-20 |
| K-D18 · Referencias por meta de SEO | SEO las declara en su propia etapa con `scope = post_meta`; K no cambia SEO | 2026-10-20 |
| K-D19 · Plugin Elementor original activo | Las guardas del flujo 2.0 sólo con el núcleo incluido; las mejoras del 3.0 valen con los dos núcleos | 2026-10-20 |
| K-D20 · IDs de elemento | Se conservan al exportar; si un nodo no coincide, ese documento conserva los IDs nuevos y se informa | 2026-10-20 |
| F7a · Presets de estilo | **Sólo estilo, definidos en el sitio y en el kit, aplicados en el CSS del documento que los usa.** Viven en la opción `digitalisimo_elements_presets` del sitio (sin carga automática) y viajan en el kit con la parte `presets`; al generar el CSS de un documento, el preset se aplana en cada elemento vinculado con sus propios selectores, así que lo que el elemento guarda gana siempre. Sustituye a «compilados en el CSS del kit»: sin CSS en el kit, sin selector propio y 0 B en las páginas que no los usan | 2026-10-21 |
| F7a · Condiciones nuevas al volver atrás | **Fallan cerradas:** cada grupo con una condición de F7a lleva un centinela que 4.3.0.163 evalúa como falso; una lista vacía nunca se cumple, y sin WooCommerce las de existencias tampoco. En v4 no se ofrecen | 2026-10-21 |
| F4-Woo · Una sola fuente cuando WooCommerce ya la tiene | **La lista de deseos y las muestras usan los datos de WooCommerce donde existen:** con `product_wishlist` activa, la cuenta usa la Store API de WooCommerce y la lista propia sólo se muda; en un atributo `wc-visual`, las muestras leen y escriben sus metas `color` e `image`, sin campos propios. Nada de WooCommerce se escribe fuera de eso (ni `attribute_type` ni sus opciones) | 2026-10-29 |
| F4-Woo · Nada depende del visitante en el HTML | **Sin cookies nuevas y sin HTML por visitante:** lista de invitado en `localStorage` por sitio y sólo tras el clic; carrito por fragmentos de WooCommerce con un HTML común; lo que depende de la sesión va en línea en el pie según el handle encolado. Los widgets conservan `is_dynamic_content()` del núcleo | 2026-10-29 |
| F4-Woo · Sin motores paralelos ni «venta falsa» | **Filtros sobre el almacén y el refresco de PRO** (tipo propio `digitalisimo_price` en el registro `digitalisimo_elements_loop_filter_types`; coordinador `filters.js` sólo en los bucles con un filtro F4-Woo, excepción declarada), **cupón y barra por `wc-ajax` y fragmentos**, kits por el registro de K. Ningún contador, plazo ni recuento inventado, tampoco como opción | 2026-10-29 |
| F4-Woo · Playground en CI | No, como K-D10 y F7a: unitarias en los dos workflows; `woo-flow.sh` W0–W13, `front-budget.sh WOO=1`, `kit-roundtrip.sh WOO=1` y `brand-audit.sh WOO=1` a mano, con la evidencia en `docs/fusion/f4woo-evidence.json` | 2026-10-29 |

## Arquitectura

1. **Conversores en Elements, orquestación en Migración.** El módulo `digitalisimo-elements/modules/digitalisimo-import/` (espacio de nombres `Digitalisimo\Elements\Import`, autocarga acotada) aloja el importador de kits de K: cero carga en sitios que no importan.
   - Desde el 2026-10-20 no hay lectores de Divi, Betheme, Bricks ni GP, ni la interfaz `Source` ni el árbol neutro que iban con F2.
   - Element Pack y Prime Slider siguen en Migración (F8): la guarda de `scripts/build-packages.sh` aborta si el ZIP de Elements los menciona. Migración puede convertir los marcadores `digitalisimo-unconverted` con el filtro `digitalisimo_elements_unconverted_convert` que publica K.
2. **Importador sin otro sistema de kits (K).** Se usa el importador 3.0 del núcleo, enganchado con filtros públicos y con runners sustituidos por nombre; el ZIP sigue siendo un kit 3.0 y lo propio viaja en la clave y la carpeta `digitalisimo/`.
   - «Sólo agregar» por defecto; «nuevo» sólo explícito y en un sitio vacío. Nunca cambia el tema, los experimentos ni los plugins, ni crea un kit nuevo.
   - Plantillas sueltas: camino nativo de la biblioteca, con el marcador para lo desconocido. Un ZIP de kit subido en la biblioteca va al importador de kits.
   - Las metas `_digitalisimo_*` del kit y `_elementor_elements_default_values` viajan y se preservan (registro de partes).
   - Las instancias de componentes v4 se conservan como marcador mientras el núcleo no admita componentes en kits.
   - Detalle en «K · Kits de Elementor con las mejoras» y en `docs/fusion/k-kits.md`.
3. **Destino clásico.** Contenedores flex o grid y widgets nativos y propios. v4 sólo en sitios que ya lo tienen.
4. **El kit es el sistema de diseño.** Colores y tipografías del origen van al kit con `_id` prefijado; variables v4 donde exista v4.
   - **Presets de estilo (F7a, corregido el 2026-10-21):** se guardan en la opción `digitalisimo_elements_presets` del sitio, sin carga automática, y viajan en el kit con la parte `presets` (sin metas del kit). No hay CSS de presets en el kit: en `elementor/element/before_parse_css`, al generar el CSS de cada documento, las claves del preset que el elemento no guarda se escriben en memoria en el elemento, que genera su CSS con sus propios selectores (RTL, `.woocommerce` y `{{ID}}` incluidos). Nada se guarda y el HTML no cambia.
   - **Vínculo:** el control `digitalisimo_preset` (sin `prefix_class`), que viaja con `map:presets`. Lo local gana siempre porque lo que el elemento guarda nunca se toca, y un control es local o del preset, nunca mezclado: si el elemento guarda cualquier variante responsiva de un control, el preset no aporta ninguna.
   - **Edición:** menú contextual del editor y pestaña «Presets de estilo» de Ajustes del sitio. El saneado reutiliza la cadena `Settings_Sanitizer` de kit-elements-defaults con una lista blanca por tipo de control y un cierre por dependencias. «Guardar como predeterminado» sigue siendo una copia local, y gana. En v4 no hay nada nuevo: el equivalente es la clase global del núcleo.
5. **Un motor de componentes.**
   - v4: Components del núcleo. Slots e import/export quedan como limitación del núcleo.
   - v3: plantilla de biblioteca + widget Template con **sobrescrituras de contenido** por instancia. Se aplican envolviendo `builder_content_data`, con la caché de elementos apagada sólo dentro del envoltorio y claves estables (`_digitalisimo_slot`) en vez de IDs.
   - Global Widget sólo para módulos globales de un solo widget.
   - Comunes: vista de usos, borrado bloqueado si hay usos e IDs HTML únicos por instancia.
6. **Interacciones.**
   - Animaciones: se habilitan las PRO quitando sólo la salida de `modules/interactions/module.php:23-25`. La clave de licencia que viaja a la Kit Library no se toca. Antes se inventarían las páginas con `hover`, `click` o `scrollOn` guardados, que empezarían a animarse.
   - Acciones con destino: registro propio (mostrar u ocultar, alternar clase o atributo, ir a, abrir popup u off-canvas, cargar más) en `_digitalisimo_actions`, con un cargador ligero y diferido para elementos clásicos y atómicos.
   - Absorbe los hashes `elementor-action:`. Lista blanca, listeners idempotentes y manejo de foco. En v3 la animación usa Motion FX o la entrada nativa.
7. **Consultas y filtros en sus módulos.**
   - Consultas guardadas y referenciadas por ID desde query-control y collection-loop, con «desvincular». Contextos actual, padre y raíz con límites de profundidad y resultados, precarga de cachés y `no_found_rows`.
   - `loop-filter` suma búsqueda, meta o rango, precio, atributo, orden y por página, en handlers propios con `defer` (los bundles compilados no se tocan). Incluye `popstate`, debounce, descarte de respuestas viejas, foco y destino inequívoco por instancia. F4-Woo entregó el precio y el atributo con ese coordinador (`assets/js/filters.js`) y el registro de tipos `digitalisimo_elements_loop_filter_types`; los filtros ampliados de F7b se añaden como tipos de ese registro, con el mismo coordinador.
8. **Anidados sin cambiar widgets existentes.**
   - Slider: un widget **nuevo** anidado que comparte las hojas y scripts del Slider Optimizado y del motor de carrusel propio. Sin Swiper; slide, fade y scale en CSS; puntero, teclado y pausa WCAG.
   - Pestañas y acordeones: nested-tabs y nested-accordion del núcleo, con la presentación DIGITALÍSIMO añadida por hooks.
   - «Migrar» convierte cuando procede; los widgets actuales quedan intactos.
9. **Tema clásico mínimo, sin hijo.**
   - Slug `digitalisimo-theme`. Registra las cuatro ubicaciones del núcleo y ubicaciones por hook `digitalisimo_*` con callbacks por defecto en prioridad 10, cada una con su equivalente `generate_*` para la conversión.
   - Cede cabecera y pie sin duplicarlos, con menús `menu-1` y `menu-2`. Modos normal, ancho completo y lienzo, con respaldos sin Elements.
   - `title-tag`, soporte Woo sin plantillas propias, `base.css` con `:where()` (incluye `alignwide` y `alignfull`), sin fuentes, sin JS y sin pantallas de administración.
   - El PHP propio va al editor de fragmentos de Tools (punto 12); el CSS, al CSS adicional o al kit. Un hijo que el administrador cree por su cuenta sigue funcionando.
   - Su API pública (ganchos, filtros, handles y funciones; tabla en F1) es un contrato estable: un cambio pasa por `_deprecated_hook` durante 2 versiones o 12 meses.
10. **Migración v2, mismo botón.**
    - Drivers: `detect, analyze, documents, backup, convert, validate, activate, revert`.
    - Runner con estado por driver y cursor, y `state['v']`: una tarea iniciada con la versión vieja termina con su semántica.
    - El análisis se guarda y se ve antes del clic. Recorre `post_content`, extractos, widgets, términos, ítems de menú, opciones y ACF.
    - Respaldo v2 del documento y del sitio, con un hash por pieza. Borrador en `_digitalisimo_migration_draft`.
    - Validación: widgets registrados, render de prueba, firma de texto, enlaces e imágenes, IDs únicos, referencias y ciclos.
    - Equivalencias en `digitalisimo_migration_idmap`, que reescribe destinos de filtros, interacciones, anclas y CSS.
    - «No pisar»: se reconvierte si cambió el origen; nunca se reescribe ni se revierte lo editado después (hash). La reversión sólo por CLI.
    - `Retire::PLUGINS` gana `phase` (`start`, `end`, `theme`); la red sólo retira `start` al inicio. Element Pack conserva su orden actual como excepción documentada.
11. **Compatibilidad cotidiana en Elements.** La opción `digitalisimo_compat`, que escriben la importación o la migración, activa los CPT del origen con sus slugs, las sidebars como widget Sidebar en plantillas del Theme Builder y las ubicaciones de menú. Sin esa opción, cero consultas.
    - Lo no convertible se marca con un widget oculto `digitalisimo-unconverted`: no pinta nada en el frontal, avisa en el editor y conserva la carga original. Mientras quede alguno bloqueante, no se activa el resultado ni se retira el origen. K lo implementa para kits y biblioteca (`docs/fusion/k-kits.md`, §9); Migración lo usará en F8.
    - K no escribe `digitalisimo_compat`: la conversión que la escribía (F2) se eliminó el 2026-10-20.
12. **Editor de fragmentos PHP en DIGITALÍSIMO Tools** (sustituye al tema hijo). Fragmentos por sitio y por red, guardados en la base de datos, sin archivos, y ejecutados por Tools con `include` por su envoltorio `digitalisimo-php://` en `after_setup_theme` con prioridad `PHP_INT_MIN`: ya cargó el `functions.php` del tema, así que un `remove_action` sobre lo del tema funciona; no se pueden sustituir funciones *pluggable* ni engancharse a `plugins_loaded`.
    - Ninguno se ejecuta sin pasar la revisión de sintaxis, la prueba previa (escritorio y portada, lanzada por el navegador del administrador) y la firma de la versión probada. Un fragmento que provoca un error fatal se desactiva solo (si es de red, se pausa sólo en ese sitio) y queda anotado. Hay modo seguro para arrancar sin fragmentos.
    - Sólo lo usa quien puede editar temas (superadministrador en red); con `DISALLOW_FILE_EDIT` o `DISALLOW_FILE_MODS` los activos siguen y sólo se pueden desactivar o detener (D1).
    - Sin Tools activo no se ejecuta ningún fragmento. Diseño detallado en F1b.

## Reglas transversales

Cada incremento las cumple. Las permanentes están también en `AGENTS.md`.

- **Sin regresiones.** Todo cambio de comportamiento en algo existente es opt-in por instancia, con valor por defecto igual al actual. La regresión sobre documentos existentes (`legacy-ab.sh` y las pruebas de cada widget) es condición para publicar. Los actualizadores fuerzan la actualización de los plugins: lo publicado llega solo a los sitios.
- **Permisos** sobre el Role Manager, sin otro sistema. Capacidades para contenido, estilos globales (kit, presets, clases, variables), estructura, componentes, consultas guardadas, importación (`unfiltered_html` si trae código) y migración. Se aplican en el servidor (guardado, REST, AJAX). Prueba: un rol cliente edita textos e imágenes y no puede tocar el diseño.
- **Entradas seguras.**
  - ZIP sin `..`, rutas absolutas ni symlinks, con límites de entradas, tamaño y ratio. JSON con límites de tamaño y profundidad. `unserialize` con `allowed_classes => false`.
  - Medios con `wp_safe_remote_get` a IP pública, tope de tamaño, verificación de tipo, SVG saneado y deduplicación por hash.
  - Código ejecutable a Código personalizado en borrador, a Tools desactivado o al informe; nunca se ejecuta automáticamente (ni los hooks PHP de GP ni `{echo:}` de Bricks).
- **Procedencia y registro.** `_digitalisimo_import_origin` (origen, versión, id, hash) en todo lo creado. Original y registro de conversión en base de datos privada (autoload off), visibles sólo para administradores. Reimportar no duplica: se reutiliza lo que no cambió y, si cambió, se crea un borrador «actualización» o «conflicto»; nunca se pisa en el sitio lo ya importado (K-D7).
- **Condiciones y caché.** Cada condición nueva se declara cacheable o variable, y `THEME_LOCATIONS` se puede ampliar por filtro. El editor distingue visibilidad, render condicional y autorización: ninguna condición se presenta como protección.
- **Recursos.**
  - Dependencias por elemento, también del contenido que llega por AJAX, popups, quick view y «cargar más». Inicialización idempotente con limpieza.
  - CSS regenerado en cola, con escritura atómica (temporal y renombrado), respaldo en línea si falla, URL versionada por hash, invalidación selectiva y purga de LiteSpeed y CDN.
  - Al renombrar una clase o variable se conserva el alias.
- **Presupuesto del front.** Cada widget o capacidad nueva tiene su fila (CSS ≤ 4 KB, JS ≤ 8 KB) en `docs/ELEMENTS_FRONT_OBJECTIVE.md`. `base.css` no pesa más que Hello.
- **Interfaz.** Sin paneles por origen; las opciones avanzadas aparecen cuando el análisis las detecta. Las pantallas nuevas usan `digitalisimo-ui` y pasan `brand-audit.sh`.
- **Paridad sitio/red.** Cada opción nueva figura en la tabla de «Opciones nuevas» o es automática, sin opción.
- **Sitios nuevos.** Ningún hook de conversión registrado. Una prueba lo comprueba.
- **Documentación por incremento.** Funciones, referencias y versiones, adaptadores, formatos, migraciones de esquema, pruebas, mediciones, limitaciones y pendientes, aquí y en una fila de `docs/PLUGIN_ROADMAP.md`.
- **Pruebas en ambos workflows.** Toda prueba nueva entra en `validate.yml` y en `release.yml`.

## Incrementos y estado

En orden de dependencias. Cada uno tiene su rama, su aceptación propia y se publica en cuanto queda validado. Ninguna aceptación depende de un incremento posterior: lo que falte se declara «parcial» en la matriz.

| Incremento | Contenido | Estado |
|---|---|---|
| F0 | Base, sin cambios de comportamiento | **Publicado** · Migración 1.0.20 · `v2026.10.17.409` |
| F0.1 | Aviso de extensiones de Element Pack y detección del Addon | **Publicado** · Migración 1.0.20 · `v2026.10.17.409` |
| F0.2 | Sin rastro de los plugins de origen ni de sus proveedores | **Publicado** · Elements 4.3.0.157 y los demás plugins · `v2026.10.17.408` (seguimiento F0.2b publicado: Elements 4.3.0.158 · `v2026.10.20.410`) |
| F1 | Tema y publicación | **Publicado** · tema 1.0.0, SEO 1.0.231, Tools 1.0.36 y Elements 4.3.0.161 · `v2026.10.20.415` |
| F1b | Editor de fragmentos PHP en Tools | **Publicado** · Tools 1.0.34 · `v2026.10.20.411` |
| K | Kits de Elementor con las mejoras: exportar e importar kits propios y de terceros, con el registro que usan F7a, F7b y F4-Woo | **Publicado** · Elements 4.3.0.162 · `v2026.10.21.416` |
| F2 | Base común de conversión y Migración v2 | Eliminado (2026-10-20) |
| F-front | Fase 2 de `ELEMENTS_FRONT_OBJECTIVE.md`: funciones avanzadas sólo donde se usan, valores por defecto del núcleo y librerías sin CDN | **Publicado** · Elements 4.3.0.163 y SEO 1.0.232 · `v2026.10.21.417` |
| F7a | Capacidades que Divi y Betheme necesitan: presets de estilo, degradados, máscaras y patrones, condiciones nuevas, prioridad de las plantillas, sticky «al fijarse» y controles de documento | **Publicado** · Elements 4.3.0.164 · `v2026.10.22.418` |
| F3 | Divi (D5 y D4) | Eliminado (2026-10-20) |
| F4 | Conversión de Betheme / BeBuilder | Eliminado (2026-10-20) |
| F4-Woo | WooCommerce visual: widgets nuevos | **Publicado** · Elements @NEXT_ELEMENTS@ |
| F5 | GeneratePress | Eliminado (2026-10-20) |
| F7b | Capacidades que Bricks necesita | Pendiente |
| F6 | Bricks (con las exportaciones del usuario) | Eliminado (2026-10-20) |
| F8 | Element Pack 10.x y Prime Slider en Migración | Pendiente |

### F0 · Base

- Este documento, `docs/fusion/` depurado y `docs/third-party-licenses.md` (Divi con sus dos textos de licencia, GP Premium, Bricks sólo documentación, Hello Elementor, GeneratePress, GenerateBlocks y Betheme sólo formatos).
- Reglas de la fusión en `AGENTS.md`.
- `.gitignore` deja de ignorar cualquier `functions.php` o `style.css`: los patrones se anclan a la raíz y una prueba comprueba que `git ls-files` incluye los del tema.
- Copias oficiales de Hello Elementor 3.5.1, GeneratePress 3.6.1 y GenerateBlocks 2.5.0 en `reference/`, descargadas de wordpress.org, con origen y suma en `reference/OFFICIAL-SOURCES.txt` (fuera de Git).
- `reference/samples/` (ignorado) para las muestras, con inventario de GenerateBlocks, GB Pro y GenerateCloud en ellas. Regla para lo que venga: los fixtures que lleguen a `tests/sources/` serán sintéticos o anonimizados (la carpeta aún no existe).
- Scripts de inventario de Element Pack que leen el ZIP sin extraerlo (9.9.1 + 10.0.1, `moved_to_addon`).
- **Aceptación:** sin cambios de comportamiento; pruebas existentes en verde.

### F0.1 · Arreglo inmediato de Migración (riesgo vivo)

- El análisis y el informe listan, por documento, cada extensión de Element Pack que hoy se descarta sin aviso (`legacy/class-native.php:328-334`).
- Se detecta el Element Pack Addon.
- Lo listado es información, no un asunto pendiente: el constructor conserva al guardar las claves que ningún control declara, así que nadie podría hacerlas desaparecer. Sólo los widgets de Prime Slider que no se ven (plugin sin activar) dejan el sitio «con asuntos por revisar».
- Lo que la conversión retira se lee de la copia original de cada documento: volver a pulsar Migrar o migrar la red no lo borra del informe.
- **Aceptación:** un documento con extensiones descartadas aparece en el informe antes del clic; la conversión no cambia. Prueba: `tests/migration-dropped-extensions.php`.

### F0.2 · Sin rastro de los plugins de origen (publicado)

Hecho en `fusion/f0-2-sin-referencias` (commit a6218fa). Inventario en `docs/fusion/origen-*.md`.

- **Escudo en el servidor** (`digitalisimo-vendor-free.php`), sólo con el núcleo incluido. Responde a lo que el núcleo pide a los dominios del proveedor sin que salga la petición: 200 sintético para la información de la biblioteca (si falla, desaparecen también las plantillas locales); 403 con JSON para Connect, kits, nube e IA (un `WP_Error` ahí provoca `wp_die()`); `WP_Error` para telemetría, notificaciones, assets, Site Planner y Mixpanel.
- Telemetría apagada; cachés remotas vacías; limpieza única de datos, tokens y el cron de telemetría; pantallas de cuenta redirigidas a «Acerca de»; rutas REST de conexión, Angie, permisos de IA y `elementor-one` retiradas.
- Fuera de la interfaz: vídeo y anuncios del inicio rápido, «Mi cuenta», ayuda, novedades, Angie y comentarios; los «Need help?»; vídeos e «i» del Theme Builder; pestañas remotas y de nube de la biblioteca (queda «Mis plantillas»); la Kit Library (lleva a importar un kit en ZIP); la IA del editor; avisos, sugerencias y «Learn more». `vendor-free.js` desactiva cualquier enlace, iframe o ventana hacia esos dominios.
- Lo derivado de PRO sin enlaces a elementor.com ni a otras empresas; sin «Powered by» en Discord y Slack. SEO, IA Tools, Hosting, Backups, Tools y Migración nombran el constructor como DIGITALÍSIMO Elements. Migración ya no escribe los valores de muestra de Element Pack. «Ver detalles» muestra una ficha propia.
- Con el plugin Elementor original activo manda él y nada de esto se aplica.
- **Pruebas:** `tests/elements-vendor-guard.php`, `tests/elements-vendor-static.mjs` (con `tests/elements-vendor-static.allow.json`), `tests/elements-identity.php` corregida y `tests/playground/brand-audit.sh` ampliado (red del navegador, DOM, servidor, cron, sondeo de redirecciones, netlog, `STATE=migrado`, `ORIGINAL=1`, lista única `vendors.json` y excepciones en `allow.json`).
- **Pendiente con aprobación del propietario:** listar y corregir las páginas publicadas que muestran el vídeo de muestra del proveedor por defecto, y los valores por defecto de Element Pack en páginas ya migradas, porque cambian páginas publicadas.
- **Pendiente funcional (no es de marca):** SEO sigue reconociendo las hojas del constructor sólo en `/plugins/elementor(-pro)/` (`class-performance-css.php:48`, `class-performance-debug.php:71`, `class-asset-diagnostics.php:349`). Con el núcleo incluido salen de la carpeta de Elements, así que el diferido de CSS por widget y su diagnóstico no actúan. **Cerrado en F1:** `Digitalisimo_Integrations_Performance_CSS::builder_asset_paths()` toma los prefijos de `ELEMENTOR_ASSETS_URL` y `ELEMENTOR_PRO_ASSETS_URL` más los dos históricos (`tests/seo-bundled-assets.php`).
- **Funciones que se pierden** (exigen una cuenta del proveedor) y su equivalente propio: bloques y páginas remotas → «Mis plantillas» y Plantillas de red de Tools; kits de Elementor → importar o exportar kits en ZIP; nube → exportar e importar, o Plantillas de red; IA del editor → DIGITALÍSIMO IA en sus pantallas; ayuda, novedades y cuenta → página Acerca de.
- **Aceptación:** 0 peticiones, redirecciones, enlaces, iframes y vídeos hacia los dominios de origen en todas las pantallas y estados, en español e inglés, salvo las excepciones declaradas.

### F1 · Tema y publicación

Se hizo en la rama `fusion/f1-tema`, desde `main@15b2b2d`.

- **Versiones:** tema 1.0.0, SEO 1.0.231, Tools 1.0.36 y Elements 4.3.0.161. IA, Hosting, Backups y Migración viajan en la Release con su versión vigente.
- **Precedencia:** si `docs/fusion/tema.md` no coincide con esta sección, manda esta sección. Las diferencias están en «Correcciones al informe del tema».

#### Tema `digitalisimo-theme/`

Lo que no figura en este árbol está prohibido. Lo vigilan `tests/theme-structure.php` y la guarda de `scripts/build-packages.sh`.

```
style.css  theme.json  functions.php  header.php  footer.php  index.php  comments.php  sidebar.php
screenshot.png  readme.txt  license.txt
inc/{setup,template-tags,elements,assets,fallbacks,switch,cli,class-updater}.php
template-parts/{header,footer,single,archive,search,404}.php
fallback/{canvas,full-width}.php
assets/css/base.css  assets/css/parity/{reset,theme,header-footer}.css
languages/{digitalisimo-theme.pot,es_MX.l10n.php,es_ES.l10n.php}
```

- **`index.php`.** Despacha a `elementor_theme_do_location()` (`single` o `archive`) y, si no hay plantilla, a `template-parts/`.
  - No hay `page.php`, `single.php`, `archive.php`, `404.php`, `woocommerce.php`, `sidebar-*.php` ni plantillas de bloques.
  - `sidebar.php` existe y está vacío: WooCommerce llama a `get_sidebar()` y, sin ese archivo, WordPress cargaría `theme-compat/sidebar.php`.
- **Ubicaciones.**
  - `header` y `footer` se registran con `hook` y `remove_hooks`: si el constructor imprime algo, retira el respaldo de prioridad 10.
  - También se registran `archive`, `single` y diez ubicaciones de gancho `digitalisimo_*`.
  - Las condiciones de visitante de Elements las reconocen por el filtro `digitalisimo_conditions_theme_locations`.
- **Sin constructor.**
  - Las plantillas `elementor_canvas` y `elementor_header_footer` caen en `fallback/canvas.php` y `fallback/full-width.php`.
  - Las pantallas Temas y Plugins muestran un aviso.
- **Salto al contenido.** Enlace `skip-link` a `#content`, sin nodos nuevos entre la cabecera y el contenido. El destino es:
  - con el constructor, un atributo del primer envoltorio (`elementor/document/wrapper_attributes`);
  - en WooCommerce, `<span id="content">`;
  - en los respaldos, `<main id="content">`.
- **Textos.**
  - Están en inglés, con `es_MX` y `es_ES` en `.l10n.php`; cualquier otra variante `es_*` usa `es_MX`.
  - Ni «Hello» ni «Elementor» aparecen en cadenas traducibles; `readme.txt` sólo los nombra en «Créditos».
- **WP-CLI.**
  - `wp digitalisimo-theme status`;
  - `import [--from=<stylesheet>] [--dry-run] [--child-css]`;
  - `revert [--yes] [--force]`.

  En una red se ejecuta sitio por sitio con `--url`.

#### Paso desde el tema anterior (`inc/switch.php`)

WordPress dispara `after_switch_theme` en la petición siguiente al cambio de tema; el tema lo escucha con prioridad 20, después de que WordPress traslade los menús.

**Cómo se ejecuta.**
- Corre bajo el cerrojo `digitalisimo_theme_switch_lock`.
- **Regla de escritura idempotente:** sólo escribe un valor si en el destino falta, si es igual al de origen o si su md5 coincide con lo que copió antes. En cualquier otro caso lo anota en `skipped` y deja un aviso.
- Al terminar suelta el cerrojo (también si la importación lanzó una excepción) y dispara `digitalisimo_theme_switched`.
- **Nunca tumba el sitio.** `digitalisimo_theme_after_switch()` y el aviso a Elements van en `try … catch ( Throwable )`: el error queda en la bitácora (`switch_failed` o `switched_event_failed`) y WordPress vacía `theme_switched`, en lugar de repetir el fallo en cada petición. El inventario del hijo salta las subcarpetas ilegibles (`CATCH_GET_CHILD`), igual que la copia de Tools.
- Con `--dry-run` no escribe nada ni dispara nada.

**Pasos.**

| Paso | Qué hace | Quién |
|---|---|---|
| T1 | Copia `custom_logo`, sólo si es un adjunto de imagen | Tema |
| T2 | Copia `nav_menu_locations` de `menu-1` y `menu-2`, sólo con menús existentes. Hace falta porque WordPress no los traslada si el cambio viene del Personalizador | Tema |
| T3 | Copia el CSS adicional, sólo desde Hello. Si el destino ya tiene otro CSS, deja un aviso | Tema |
| T4 | Copia el resto de mods sin pisar nada. Excluye `sidebars_widgets`, `custom_css_post_id`, las claves numéricas, `hello-header-footer` y `wp_classic_sidebars` | Tema |
| T5 | Lee los seis ajustes `hello_elementor_settings_*` | Tema |
| T6 | Calcula el modo de cabecera y pie (`disabled`, `tb`, `tb_partial`, `dynamic`, `hidden` o `classic`) y `pending`. `hidden` sólo existe para la cabecera: con todo apagado, Hello sigue imprimiendo el pie vacío, así que el pie es `dynamic` y su plantilla, un contenedor vacío. Lee opciones y el meta del kit, sin llamar al constructor | Tema |
| T7 | Convierte la cabecera y el pie dinámicos en plantillas del Theme Builder | Elements |
| T8 | Limpia las cachés de CSS del constructor | Elements |
| T9 | Anota si Hello imprimía la meta description y si SEO la imprime; si sólo la imprimía Hello, deja el aviso `description_lost` | Tema |
| T10 | Hace el inventario del hijo (sus `*.php`, los bytes de `style.css`, sus plantillas de página y sus selectores) y de los callbacks de `hello_elementor_*` y `hello-plus-theme/*` | Tema |

**Reversión** (`wp digitalisimo-theme revert`).
- Vuelve a `last.stylesheet` y marca `reverted`.
- Elements retira lo generado: lo pasa a la papelera si no se editó y a borrador si se editó.
- **Dejar el tema desde Apariencia** (o el Personalizador) hace lo mismo sin WP-CLI: Elements escucha `switch_theme` y, si el tema saliente es el DIGITALÍSIMO (por carpeta o por `Update URI`) y el entrante no es él ni un hijo suyo, retira lo generado y marca `reverted`. Es idempotente: la reversión de WP-CLI ya marcó `reverted` antes de cambiar de tema.
- Sin Elements y con plantillas generadas, devuelve `WP_Error`. Con `--force`, el tema las pasa a borrador.
- Nunca escribe en los mods ni en los ajustes de Hello.

#### Actualizador y Release

- **Actualizador del tema.** `Digitalisimo_Theme_Updater` (`inc/class-updater.php`) sólo se carga en la administración, en cron y en WP-CLI.
  - Usa `update_themes_github.com`, con respaldo en `pre_set_site_transient_update_themes` y `site_transient_update_themes` (prioridad 30).
  - Compara con la versión instalada de su `style.css`, no con la constante, que en la petición que actualiza el propio tema sigue siendo la anterior.
  - Sus entradas son siempre arrays.
  - Conserva la carpeta con `$hook_extra['theme']`; si no puede, devuelve `WP_Error`.
  - Ninguna oferta lleva `autoupdate`. Si una versión es incompatible, lleva `disable_autoupdate`.
  - Detalle en `docs/RELEASE_PROCESS.md`.
- **Cobertura en Tools (D1).** `Digitalisimo\Tools\Theme_Updates` trabaja en la prioridad 31.
  - Rehace siempre la entrada de las copias que no están cargadas.
  - Para la copia cargada sólo escribe si no hay una entrada con su `id`.
  - En una red añade «Buscar actualizaciones» a la fila del tema en Temas.
  - Sirve una ficha de detalles propia (`admin-post.php?action=digitalisimo_theme_details`). Hace falta porque «Ver detalles» abre la URL de la oferta en un iframe, y GitHub no deja mostrarse en un iframe.
- **Release.** Lleva 8 ZIP más el índice:
  - `themes.digitalisimo-theme` con `version`, `file`, `url`, `requires` y `requires_php`;
  - `plugins` sigue con 7 entradas.

  Cambian `build-packages.sh` (con la guarda del tema), `changed-modules.sh`, `release-manifest.mjs`, `verify-release.mjs`, `validate-suite.mjs`, `release.yml` y `validate.yml`.

#### Otros módulos

- **Tools · sección «Tema».** La pinta `Theme_Catalog` debajo del catálogo de plugins de SEO y del de Tools, con la acción `digitalisimo_plugin_catalog_after`.
  - **Filas:** una por copia, incluida la cargada, o «No instalado» si no hay ninguna.
  - **Acciones:** Instalar, Actualizar (`Theme_Upgrader::bulk_upgrade()`), Buscar actualizaciones, Ver detalles e «Ir a Temas». **Nunca ofrece «Activar»** ni habilita el tema en la red.
  - **Contextos:** en una red se instala y se actualiza desde la red; en un sitio de la red sólo se ve el estado.
  - **Sin sistema de archivos directo:** enlaza a `update-core.php`.
  - **«Antes de cambiar de tema».** Aparece con Hello o un hijo suyo activo y es de sólo lectura. Muestra:
    - el inventario del hijo;
    - los filtros `hello_elementor_*` con callbacks;
    - si Hello imprime la meta description y SEO no;
    - el modo previsto de cabecera y pie.

    La regla de ese modo es la del tema, duplicada a propósito porque ningún módulo puede exigir otro; una prueba compara las dos salidas.
- **SEO.**
  - En rendimiento acepta el tema propio, también como padre y también en otra carpeta (por su `Update URI`), con `builder_theme()` y el filtro `digitalisimo_performance_builder_theme`.
  - Retira la meta description de Hello (`wp_head`, 10) sólo donde imprime la suya.
  - Reconoce las hojas del núcleo incluido por `ELEMENTOR_ASSETS_URL` y `ELEMENTOR_PRO_ASSETS_URL`, lo que cierra el pendiente de F0.2.
  - Su catálogo dispara `digitalisimo_plugin_catalog_after`.
- **Elements.**
  - **`Theme_Compat`** (`modules/digitalisimo-theme/`):
    - reconoce el tema por su carpeta habitual o por su `Update URI` (una copia en otra carpeta, como la de `theme-network.sh`);
    - hace que el asistente del núcleo considere compatible el tema;
    - con el tema activo, bloquea la activación de Hello por AJAX y por REST;
    - escucha `digitalisimo_theme_switched`, `digitalisimo_theme_reverted` y `switch_theme` (al dejar el tema desde Apariencia).
  - **`Hello_Header_Footer`** convierte la cabecera y el pie dinámicos de Hello en plantillas del Theme Builder.
    - Escribe valores explícitos del kit: logo o título, lema, menú y copyright, con la disposición y la escalera de anchos de Hello.
    - Trabaja con cerrojo propio y sin duplicados (`_digitalisimo_generated`), y calcula el hash después de guardar.
    - Si el cambio de tema ocurre en el front, la conversión se hace en cron.
    - Limpia las cachés del CSS al recibir el aviso y otra vez después de crear, publicar o restaurar plantillas.
    - Al revertir o al dejar el tema, retira lo generado (papelera si no se editó, borrador si se editó) y lo restaura al volver.
  - **Condiciones de visitante:** aceptan ubicaciones nuevas por filtro.

#### Decisiones fijadas en F1

D1 y D2 están en «Decisiones», arriba. Además:

1. **Sin tema hijo.**
2. **`theme.json` copiado de Hello** (v3, 800/1200 y `appearanceTools`): sin él, WordPress encola `classic-theme-styles`.
3. **`overflow-x:clip` en `body`**, como hace Hello. Siguen prohibidos `hidden` y `auto` en `body`, y cualquier `overflow` en `html`.
4. **Cabecera y pie con `hook` y `remove_hooks`.** Así se evita el reemplazo por búfer de salida del constructor.
5. **Ninguna oferta lleva `autoupdate`** y nadie filtra `auto_update_theme`. `disable_autoupdate` sólo sirve para apagarla.
6. **Las entradas de `update_themes` son siempre arrays.**
7. **`files:` de `release.yml` no cambia:** `*.zip` ya incluye el tema.
8. **La compatibilidad con el asistente del núcleo va en Elements** (`elementor/onboarding/is_elementor_theme_active`).
9. **El marcado de los respaldos copia el de Hello 3.5.1:** mismas etiquetas, ids y clases.
10. **La meta description de SEO sale de `head()`** (`wp_head`, 1).
11. **SEO no necesita alias de handles** para su lista diferible. Los alias `hello-elementor*` son para dependencias de terceros.
12. **No se reconstruye lo que no se puede verificar:** `tb_partial`, Hello+ y `hello-biz`/`hello-commerce` se declaran y no se convierten.

#### API pública del tema

Es un contrato estable: un cambio pasa por `_deprecated_hook` durante 2 versiones o 12 meses. La columna GP sólo documenta la equivalencia para F5; F1 no tiene código de GeneratePress.

| Gancho (`do_action`) | Dónde | Equivalente GP (F5) |
|---|---|---|
| `digitalisimo_before_header` | `header.php` | `generate_before_header` |
| `digitalisimo_header` (5: plantilla; 10: respaldo) | `header.php` | `generate_header` |
| `digitalisimo_after_header` | `header.php` | `generate_after_header` |
| `digitalisimo_before_main_content` | `template-parts/{single,archive,search,404}.php` | `generate_before_main_content` |
| `digitalisimo_before_entry_title` | `single`, `archive`, `search` | `generate_before_entry_title` |
| `digitalisimo_after_entry_title` | `single`, `archive`, `search` | `generate_after_entry_title` |
| `digitalisimo_after_entry_content` | `single` | `generate_after_entry_content` |
| `digitalisimo_before_comments` | `single` | `generate_before_comments_container` |
| `digitalisimo_after_main_content` | `single`, `archive`, `search`, `404` | `generate_after_main_content` |
| `digitalisimo_before_footer` | `footer.php` | `generate_before_footer` |
| `digitalisimo_footer` (5 / 10) | `footer.php` | `generate_footer` |
| `digitalisimo_after_footer` | `footer.php` | `generate_after_footer` |

- **Filtros:**
  - `digitalisimo_theme_content_width`
  - `digitalisimo_theme_woocommerce_support`
  - `digitalisimo_theme_viewport`
  - `digitalisimo_theme_header_footer( $show, $part )`
  - `digitalisimo_theme_page_title`
  - `digitalisimo_theme_skip_link`
  - `digitalisimo_theme_skip_link_url`
  - `digitalisimo_theme_register_locations`
  - `digitalisimo_theme_hook_locations`
  - `digitalisimo_theme_enqueue_style`
  - `digitalisimo_theme_parity`
- **Handles:** `digitalisimo-theme`, `digitalisimo-theme-parity-reset`, `digitalisimo-theme-parity` y `digitalisimo-theme-parity-header-footer`.
- **Funciones:**
  - `digitalisimo_theme_location()`
  - `digitalisimo_theme_parity()`
  - `digitalisimo_theme_switch_record()`
  - `digitalisimo_theme_hello_settings()`
  - `digitalisimo_theme_show_header_footer( $part )`
  - `digitalisimo_theme_show_title()`
- **Acciones entre módulos:** `digitalisimo_theme_switched( $record, $old )` se dispara con el cerrojo ya suelto, y `digitalisimo_theme_reverted( $record )` al revertir.

Los alias `hello-elementor*` sin `src` son compatibilidad, no API.

#### Matriz Hello 3.5.1 → tema propio

La paridad rige con `origin.family = hello` y sin `reverted`. Fuera de paridad (modo base) sólo se carga `base.css`.

| Aspecto | Paridad | Diferencia o límite declarado |
|---|---|---|
| Hojas | `parity/reset.css`, `theme.css` y `header-footer.css`, idénticas byte a byte. Se cargan con las mismas condiciones: ajustes `hello_style` y `hello_theme`, y filtros `hello_elementor_enqueue_*`. Los alias `hello-elementor*` sin `src` hacen que lo que depende de ellos se siga imprimiendo | Sin variantes `-rtl`, porque Hello no las encola |
| Marcado | Cabecera y pie clásicos, entradas, archivos, búsqueda, 404 y comentarios llevan las etiquetas, ids y clases de Hello. `sidebar.php` vacío | Sin `<link rel="profile">`. `<span id="content">` en WooCommerce |
| Ajustes de Hello | Los seis `hello_elementor_settings_*` se leen al cambiar (`hello` del registro) y apagan lo mismo que en Hello | `hello-plus-theme/settings` no se puentea: sólo alimenta la interfaz de Hello |
| Filtros de Hello | Se aplican los 8 de front: `page_title`, `header_footer`, `enqueue_style`, `enqueue_theme_style`, `viewport_content`, `enable_skip_link`, `skip_link_url` y `content_width`. Orden: valor por defecto, ajuste guardado, filtro de Hello y filtro propio | Un fragmento que llama a funciones `hello_*` sin `function_exists` daría Fatal. El inventario de filtros lo avisa, pero las llamadas no se detectan |
| Título | `hide_title` del documento lo apaga | Sólo en páginas singulares. Hello lo consulta también en archivos, con el primer resultado |
| Ancho | `content_width` 800 y `theme.json` 800/1200 | — |
| Logo, menús, CSS adicional y mods | Se trasladan (T1–T4) de forma idempotente, sin pisar lo editado en destino | El CSS adicional sólo se copia desde Hello. Si el destino ya tiene otro CSS, hay aviso |
| Cabecera y pie | • `tb`: el Theme Builder cede igual.<br>• `dynamic`: plantillas generadas.<br>• `hidden` (sólo la cabecera): no se pinta. El pie con todo oculto se genera como un contenedor vacío, con su fondo y su relleno, porque Hello lo imprime vacío.<br>• `classic` y `disabled`: como en Hello | • `tb_partial`: en las URL excluidas, Hello pintaba su cabecera y el tema pinta la clásica (con aviso).<br>• Cabecera generada: el botón móvil y el desplegable son los del widget Menú (puntos de corte del kit), no `hello-frontend.js`; `smartmenus` pesa 24 109 B y necesita jQuery, frente a 1 830 B.<br>• Son plantillas del constructor: unos 20 nodos más que la de Hello (el envoltorio de cada widget) y, en páginas que no usaban el constructor, sus hojas y scripts (declarado en `theme-switch/known.json`).<br>• Si la primera petición tras el cambio es anónima, se ve la cabecera clásica hasta que corre el cron, que convierte como el administrador que activó el tema.<br>• La vista previa del Personalizador muestra la cabecera clásica |
| Meta description | SEO imprime la suya y retira la de Hello | Sin SEO se pierde, con el aviso `description_lost` en la bitácora y en Tools |
| Hijo de Hello | Inventario antes del cambio (Tools) y después (bitácora) | Su PHP no se carga: va a fragmentos de Tools (F1b). Su `style.css` sólo se copia con `--child-css` (D2) |
| Hello+, `hello-biz`, `hello-commerce` | Familia `other`, con aviso | Sin conversión |
| Versión de Hello | Paridad con 3.5.1 | Con otra versión de origen, la versión queda en el registro y la diferencia se informa |
| `id="content"` | Uno solo por página | Se repite si el contenido ya usa ese id. Se desactiva con `digitalisimo_theme_skip_link_url` |

#### A/B en Playground

Lo hacen dos arneses. En ninguno se monta con escritura nada del repositorio, y Hello se copia desde `reference/`.

- **`tests/playground/theme-switch.sh`:** sitio individual, en Chrome, con `WOO=1`.
- **`tests/playground/theme-network.sh`:** red Multisite, sin Chrome.

**Escenarios de `theme-switch.sh`:**
- **S1** sin Theme Builder, con un fragmento que filtra `hello_elementor_page_title`.
- **S2** con Theme Builder.
- **S3** con cabecera y pie dinámicos:
  - **S3c:** la primera petición es anónima y la conversión se hace por cron;
  - **S3h:** todo oculto: sin cabecera y con el pie vacío, en A y en B.
- **S4** hijo de Hello, con importación `--child-css`.
- **S5** sin SEO.

**Fases:** A (Hello), B (tema), C (reversión), C2 (ida y vuelta), D (modo base), E (sin constructor), F (editor) y F2 (asistente: 403 por AJAX y por REST).

**Comprobaciones.** Son 15 por URL, en tres anchos (360, 768 y 1280):
- estado HTTP;
- firma visible;
- meta description;
- número de `h1`;
- cajas;
- sin desplazamiento horizontal;
- consola y registro de PHP limpios;
- píxeles, con un máximo de 0,5 %;
- bytes de hojas y nodos del DOM;
- origen de las URL;
- `base.css` dentro del techo;
- estilos calculados de la cabecera generada;
- sin `#sidebar` en WooCommerce;
- un solo `#content`;
- el enlace de salto se ve al recibir el foco (también en el modo base, fase D, y sin constructor, fase E).

Las diferencias aceptadas están en `theme-switch/known.json`. Una excepción sin usar hace fallar la prueba.

**Comprobaciones de `theme-network.sh`:**
- **N1:** cobertura sin el tema cargado.
- **N2:** sección «Tema».
- **N3:** actualizar con Tools conservando la carpeta. **N3f:** `move()` falla.
- **N4:** actualización automática y `disable_autoupdate`.
- **N5:** ficha de detalles.
- **N6:** copia activa con su actualizador y respaldo de Tools.

**Resultado:** A/B Hello 3.5.1 frente al tema en verde (S1–S5, S3c, S3h y E con WOO=1 en tres anchos; corrida de aceptación completa del 2026-10-20 y rápida tras las correcciones) · red Multisite N1–N6 y N3f en verde.

#### Medición en staging (pendiente de autorización del propietario)

**Protocolo:** `MODE=measure BASE=<url> bash tests/playground/theme-switch.sh`, contra staging con OpenLiteSpeed, LSCache y CDN.
- 5 corridas en frío y 5 en caliente, con la mediana de TTFB, LCP y CLS.
- Bytes por handle y nodos del DOM.
- Sin `Vary` ni cookies añadidas.

Nunca cambia el tema. El tema no se activa en ningún sitio real.

#### Pruebas

- **En `validate.yml` y `release.yml`:**
  - `tests/theme-structure.php`, `theme-updater.php` y `theme-switch.php`;
  - `release-manifest.mjs`;
  - `tools-theme.php`;
  - `seo-hello-description.php` y `seo-bundled-assets.php`;
  - `elements-theme-compat.php` y `elements-hello-header-footer.php`.
- **Listas unificadas:** `validate.yml` y `release.yml` ejecutan la misma lista (lo comprueba `tests/repo-hygiene.mjs`). `seo-excerpt.php`, `plugin-catalog-manifest.php` y `elements-visitor-content.php`, que antes sólo estaban en `release.yml`, ahora están en las dos.
- **`php -l`:** cubre `digitalisimo-theme`.
- **Regresión:**
  - `conditions.sh`;
  - `CORE=bundled interaction.sh`;
  - `brand-audit.sh`;
  - `front-budget.sh`, que mide también las diez ubicaciones nuevas;
  - `tools-catalog.sh`.

#### Riesgos y límites declarados

Se suman a las diferencias de la matriz.

- **Versiones anteriores.** Con SEO o Tools anteriores a F1, el catálogo no dispara la acción y la sección «Tema» no aparece. Los dos plugins se actualizan solos.
- **Sin Tools.**
  - En una red, sólo se actualiza la copia activa en el sitio principal.
  - En un sitio individual con el tema inactivo, no hay aviso; al activarlo, el tema se atiende solo.
- **Credenciales FTP.** Los botones de Tools no las piden: enlazan a `update-core.php`. Si la cabecera del paquete no se puede leer y la carpeta tiene otro nombre, la actualización se detiene con `WP_Error`, sin daños.
- **Carpeta con punto.** El AJAX nativo de Temas la rompe; para esa copia, el botón de Tools es la vía.
- **«Ver detalles» sin Tools.** Abre la Release en un iframe que se ve en blanco (`x-frame-options: deny`). «Actualizar ahora» funciona.
- **Homónimo en wordpress.org.** `pre_set` lo sustituye: el tema para la copia cargada y Tools para las demás. Hoy el slug no existe.
- **Diez ubicaciones más.** El constructor las recorre al encolar estilos; se mide en `front-budget.sh`.
- **Importar un kit puede cambiar el tema.** La importación de kits del núcleo, con su paso `theme`, puede activar otro tema (o reinstalar Hello) aunque el tema DIGITALÍSIMO esté activo. F1 no lo bloquea: **pendiente de la etapa K**, que importa siempre con `theme=false` (además de sin `experiments` ni `plugins`).

#### Correcciones al informe del tema

Corrigen `docs/fusion/tema.md`; donde no coincidan, manda este documento.

- El tema **lleva `theme.json`** (§6.A decía que no).
- `overflow-x:clip` en `body` **está permitido** (§6.B lo prohibía).
- La paridad sale de `origin.family = hello`, no de `from=hello`, y sobrevive a un rodeo por otro tema. Los ajustes de Hello van en `hello`, no en `hello_settings`.
- El registro se divide en dos: `digitalisimo_theme_switch` (pequeño, con carga automática) y `digitalisimo_theme_switch_log` (sin carga automática).
- Las copias del tema que no están cargadas las cubre Tools (D1), no `digitalisimo-sibling-updates.php`.
- El plan aprobado está fuera del repositorio y no se edita. Donde contradiga «sin hijo» o D1, manda este documento.

### F1b · Editor de fragmentos PHP en Tools

- Pestaña «Código PHP» en Digitalisimo → Herramientas (sitio y red, con la paridad de `AGENTS.md`) para crear, revisar, activar y desactivar fragmentos, con versiones anteriores, registro y avisos. Las pestañas de Tools recargan la página y tienen un formulario cada una (excepción declarada en `AGENTS.md`); la nueva no repite ningún `name`.
- «Guardar y activar» es un solo botón con tres pasos: revisión sin ejecutar, prueba en el escritorio y en la portada **lanzada por el navegador del administrador** (sin loopback del servidor, así que funciona detrás de un WAF o una CDN y con `--workers=1`) y firma HMAC del hash probado. En la red se prueban el escritorio de la red, la portada del principal y hasta cuatro portadas con otro tema; una redirección al mismo host se reintenta una vez.
- Seguridad: `edit_themes` + `unfiltered_html`; nonce por acción, ámbito, sitio y fragmento; con `DISALLOW_FILE_EDIT`/`DISALLOW_FILE_MODS` lo activo sigue y sólo se desactiva o detiene; firma con las claves de `wp-config.php` (una firma inválida detiene el fragmento y obliga a revisarlo y reactivarlo uno por uno, nunca en bloque); autodesactivación ante un fatal atribuido con certeza; **modo reducido** (con la constante o con la cookie del enlace de rescate, la pantalla sólo deja desactivar o detener).
- Recuperación sin FTP: guardián, «Detener todos» (sitio, red o todos los sitios), enlace de rescate personal (por correo al activar o pedido en `/?digitalisimo-php-rescue=request`) y `DIGITALISIMO_TOOLS_PHP_SAFE_MODE` en `wp-config.php`.
- Los fragmentos importados (`digitalisimo_tools_php_import`: GeneratePress en F5, `functions.php` de un hijo) llegan de sitio, desactivados y con revisión obligatoria; `digitalisimo_tools_php_sources` los expone a SEO y Migración sin código salvo con permiso de edición.
- **Pruebas:** `tests/tools-php-snippets.php` (unitaria, también en PHP 7.4 con php-wasm-cli) y `tests/tools-php-guard.php` (guardián en subprocesos) en `validate.yml` y `release.yml`; `tests/playground/tools-php.sh` con los escenarios de sitio individual y de red: un fatal no tumba el sitio, el modo seguro funciona y la red y el sitio quedan aislados.
- **Medición pendiente** en staging con el protocolo de F1: como mucho 1 ms por cada 10 KB de código activo, sin cambios en `Vary`, cookies ni cabeceras para los visitantes.

### K · Kits de Elementor con las mejoras

**Publicado** · Elements 4.3.0.162 · `v2026.10.21.416`. Sólo cambia DIGITALÍSIMO Elements: Migración, Tools, SEO, IA Tools, Hosting, Backups y `digitalisimo-ui` no cambian. Diseño, ganchos y evidencia del núcleo en [docs/fusion/k-kits.md](fusion/k-kits.md); Template Kits de Envato en [docs/fusion/k-envato.md](fusion/k-envato.md).

**Objetivo.** Exportar e importar **kits de Elementor** con todas las mejoras de DIGITALÍSIMO Elements: los propios y los de terceros (Elementor y Elementor Pro en formato 1.x, 2.x o 3.0, y los Template Kits de Envato). Un kit exportado desde un sitio con Elements e importado como «nuevo» en un sitio vacío da el mismo resultado, en datos y en capturas. Un kit de terceros entra en «sólo agregar». En todos los casos:
- sin otro sistema de kits y sin tocar el núcleo incluido;
- sin errores ni peticiones al proveedor;
- sin cambiar el tema, los experimentos ni los plugins;
- sin perder datos: lo que no se puede pintar se conserva y se informa;
- sin ampliar quién ve un contenido.

**Qué cambia para el administrador**
- **Herramientas → Importar/Exportar** abre el importador de kits (`#/import-customization` y `#/export-customization`), siempre en «sólo agregar». La biblioteca de kits del proveedor y las rutas del flujo 2.0 llevan ahí.
- **Pantalla «Kits»** (`admin.php?page=digitalisimo-elements-kits`, sólo en el sitio, con `manage_options` y `digitalisimo-ui`): importar, con la casilla «Este sitio es nuevo» que sólo se habilita si el sitio está vacío (K-D1); lista de importaciones (de las sesiones nativas del núcleo) y su informe; «Aplicar los ajustes del kit» y deshacerlo; «Publicar páginas y plantillas».
- **Informe** de cada importación: el modo y su porqué; «revisar antes de publicar»; lo conservado sin mostrar; lo que el kit pedía y no se aplicó (tema y experimentos sin nombre, plugins por nombre y sin enlace, y los de la familia del constructor como «funciones incluidas en DIGITALÍSIMO Elements»); referencias sin destino; medios; URL del origen que quedan; lo no exportado en el origen; lo que hay que configurar después (formularios, webhooks, Google, ACF, barras laterales); ajustes no aplicados; borradores; la papelera tras revertir.
- **WP-CLI:** `wp digitalisimo-elements kit export|import|revert|report|inspect`. `import` exige `--user` con `manage_options` y acepta `--mode=add|fresh` (por defecto `add`).
- Un **ZIP de kit subido en Plantillas → Importar** se lleva al importador de kits con un token de un solo uso (una hora, por usuario). La biblioteca dentro del editor no se intercepta.

**Frentes.** Rutas relativas a `digitalisimo-elements/modules/digitalisimo-import/` salvo que se diga otra cosa.

| Frente | Qué entrega | Archivos |
|---|---|---|
| E · contrato | Interfaces, registro de partes y de referencias, contextos | `class-module.php` (esqueleto), `kits/{interface-part,interface-early-part,class-parts,class-references,class-part-context,class-export-context,class-import-context,class-revert-context}.php`; `tests/elements-kit-parts.php` §1 |
| A · importador | Ganchos, sesión, contextos, guardián, normalizador, proxy de medios, filtros del WXR, guardas del flujo 2.0, runners y correcciones en derivados de PRO | `class-module.php`, `kits/{class-session,class-context,class-zip-guard,class-inspection,class-normalizer,class-normalized,class-media-proxy,class-wxr-guard,class-legacy-guard,class-cli-guard}.php`, `kits/runners/`, una línea en `pro-elements.php`, los derivados de PRO de `docs/fusion/k-kits.md` §7 con su nota en `DIGITALISIMO_CHANGES.md`, y `digitalisimo-duplicator.php` |
| B · datos propios | Las siete partes y la exportación propia (IDs de elemento, miniatura completa, WXR anonimizado) | `kits/parts/`, `kits/export/` |
| C · remapeo | `Remapper` y las referencias integradas | `kits/class-remapper.php`, `References::builtin()` |
| D · terceros | Template Kits de Envato, marcador y biblioteca | `sources/{class-envato-kit,class-asset-scan}.php`, `kits/class-preserve.php`, `class-unconverted-widget.php`, `kits/class-library.php` |
| F · interfaz | Informe, pantalla «Kits», WP-CLI y guardas de la App | `kits/{class-report,class-report-page,class-cli}.php`, `digitalisimo-elements/assets/digitalisimo/vendor-free.js` y `kits.js` |
| G · pruebas | Unitarias, fixtures y Playground | `tests/elements-kit-*`, `tests/sources/kits/`, `tests/playground/kit-roundtrip*`, rama `kit` de `brand-audit`, `tests/playground/README.md` |
| H · documentación | Esta sección y las demás | `docs/{FUSION_OBJECTIVE,PLUGIN_ROADMAP,ELEMENTOR_CORE,ELEMENTS_FRONT_OBJECTIVE}.md`, `docs/fusion/k-*.md`, `AGENTS.md`, `digitalisimo-elements/readme.txt` |
| I · integración | Correcciones de la primera corrida completa, una sola fuente entre frentes, versión, paquete, workflows y evidencia | `tests/elements-kit-{global-widget,integration}.php`, `pro-elements.php` (versión), `tests/validate-suite.mjs`, `validate.yml` y `release.yml`, `dist/`, `rollback/`, `docs/fusion/k-kits-evidence.json`; lo que cambió en la integración está en `docs/fusion/k-kits.md` §14 |

**Aceptación.** Cada criterio se mide con `tests/playground/kit-roundtrip.sh` o con las pruebas unitarias. La evidencia (`summary.json` del arnés) se guarda como `docs/fusion/k-kits-evidence.json`.

| # | Criterio | Cómo se mide |
|---|---|---|
| A1 | **Ida y vuelta propia (R1).** El sitio A tiene los 44 widgets visibles, el Slider Optimizado y los 7 alias; los rasgos; los tres motores de condiciones; el Theme Builder en cinco ubicaciones; popup; loop con plantilla alterna y filtro de taxonomía; formulario; menú; fuente e iconos propios; kit con globales, valores por defecto y fondo con imagen; separadores `digi-*` y máscaras. Se exporta por REST y por WP-CLI y se importa en B vacío como «nuevo» | Firma de datos con **0 diferencias** fuera de `known.json` (cada entrada con su motivo; una sin usar falla), con los IDs de elemento y la integridad de las referencias. Los autoincrementos de B se desplazan antes: un ID sin traducir cuenta como `unresolved`. export(A) = export(B) normalizado; REST = WP-CLI salvo IDs; medios incrustados, 0 descargas y 0 peticiones al host de A. Píxeles ≤ 0,5 % en 360, 768 y 1280 px; 0 errores JS, 0 respuestas 404 y 0 fatales; mismos sha256 en `uploads/digitalisimo-elements/` |
| A2 | **Origen fuera de línea** | A1 con el host de A bloqueado |
| A3 | **Terceros:** Envato sintético (R2), Pro 3.x en 2.0 (R3) y 1.x (R3b), en «agregar» sobre B vacío | `failed` vacío en cada runner; sin entradas `error`; cada documento cumple el `expect` de su fixture; tras «Aplicar los ajustes del kit», colores y tipografías en el kit activo; las condiciones de Pro, como condiciones del Theme Builder en plantillas en borrador |
| A4 | **Sin pérdida** | Elementos por tipo (un marcador cuenta como su tipo original), documentos por tipo y términos por taxonomía iguales en el kit y en B. La carga de cada marcador se reexporta igual; sin `unfiltered_html`, igual a kses del original, y la diferencia queda en el informe |
| A5 | **Sin peticiones al proveedor** | Mientras dura el contexto de K, 0 peticiones a los dominios de `vendors.json` y a `*.wordpress.org`, con `CORE=bundled` y con `CORE=plugin`; el netlog de Chrome tampoco las tiene |
| A6 | **Sin cambios sin permiso** | «Agregar»: todas las opciones idénticas, salvo transitorios, `cron`, `elementor_import_sessions`, `elementor_revert_sessions`, `digitalisimo_elements_kit_session_*` y `digitalisimo_elements_unconverted`, y los árboles de `wp-content/plugins` y `wp-content/themes` idénticos. «Nuevo»: además pueden cambiar los ajustes del kit activo (conserva su ID), la portada si el kit la trae y, con `WOO=1`, las páginas de WooCommerce. Nunca el tema, los plugins ni los experimentos |
| A7 | **Sólo agregar (R4)** sobre un sitio con contenido de choque | Lo previo conserva el md5 de sus datos, contenido, estado y condiciones. El kit se compara campo a campo: no cambia lo previo de colores, tipografías, CSS, puntos de corte ni estilo del tema, y lo añadido es exactamente el mapa de ajustes. Estados según K-D8. Tras «Publicar», una página importada pinta su fuente y su icono propios |
| A8 | **Reimportación (R5)** | La segunda vez no crea filas en `posts`, `postmeta`, `terms` ni `termmeta` ni archivos en `uploads/`; el número de colores y tipografías no cambia; «reutilizado» = documentos del kit |
| A9 | **Multisite (R6)**, importando en el sitio 2 | Sitios 1 y 3 idénticos (tablas sin transitorios, `uploads/`, `plugins` y `themes`) y `sitemeta` idéntica salvo `_site_transient_*`; archivos en `uploads/sites/2/`; un administrador sin `unfiltered_html` no importa Custom Code; los tipos de archivo bloqueados por la red se avisan; nunca se instala nada |
| A10 | **Reversión (R8)** | B coincide con su instantánea, salvo los documentos que van a la papelera (contados aparte) y los adjuntos de la sesión, que se borran. Se restauran ajustes, metas y datos v4 del kit, las opciones de A6 y `uploads/digitalisimo-elements/` |
| A11 | **Entradas hostiles (R9)** | Todas rechazadas con un mensaje (también rutas REST con otra capitalización y descargas en flujo); nada escrito fuera de las carpetas de K; 0 fatales; un editor recibe 403 |
| A12 | **Marca y regresión** | `brand-audit.sh STATE=kit` con `SCOPE=quick` y `SCOPE=full`; `front-budget.sh`, `conditions.sh`, `CORE=bundled interaction.sh` y `brand-audit.sh`; las unitarias en los dos workflows |
| A13 | **Tiempo por petición** | Con `SLOW=1` (host lento y 100 imágenes), ninguna petición REST dura más de 25 s; lo que no cabe queda «no descargado (tiempo)» |
| A14 | **Sin ampliar visibilidad** | Ninguna referencia sin destino deja un elemento, plantilla o fragmento visible para más usuarios o páginas que en el origen; el documento queda en borrador con «revisar antes de publicar» |

**Regla de cierre (fallar cerrado)**
- En una lista, un valor sin destino se quita y se anota.
- Un valor único, o una lista que queda vacía, sólo se vacía si en ese control vacío **no** significa «cualquiera». Si lo significa (por ejemplo, el usuario de Visibilidad), se usa el valor que nunca coincide que declara la referencia (`never`), que sobrevive a `absint()` sin volverse un ID real.
- Si la referencia decide quién ve algo o dónde se aplica (`gate`), el documento queda **en borrador en los dos modos** con «revisar antes de publicar», y «Publicar» no lo publica mientras su copia local no cambie.
- En el Theme Builder y en Custom Code, una condición sin destino deja el documento **sin condiciones** (no se aplica en ninguna parte) y el informe conserva la lista original.

**Registro extensible.** Es lo que permite a las etapas siguientes añadir sus datos de kit **sin tocar el código de K** (`digitalisimo-elements/modules/digitalisimo-import/kits/`; contrato completo en `docs/fusion/k-kits.md` §10):
- **Partes**, con el filtro `digitalisimo_elements_kit_parts`: una clase que implementa `Part` (`key`, `version`, `kit_meta_keys`, `export`, `import` y `revert`) viaja en `digitalisimo/<key>.json` y anota su versión en el manifiesto. Una `Early_Part` corre además en el runner `plugins`, antes de taxonomías y contenido. Las partes de K son `origin`, `element-defaults`, `kit-settings`, `files`, `media`, `theme-builder` y `term-meta`; la clave `urls` está reservada.
- **Referencias**, con el filtro `digitalisimo_elements_kit_references`: `[ scope, type, path, kind, gate, never ]`, con `scope` `element`, `document`, `condition`, `post_meta` o `term_meta`, y `kind` `post`, `template`, `term`, `menu`, `menu_slug`, `user`, `attachment`, `url`, `media-url`, `css`, `file`, `element`, `conditions`, `note:<código>` o `map:<nombre>`. Los controles URL se derivan solos de su tipo.
- **Contextos:** `Export_Context` (`add_file()`, `budget()`), `Import_Context` (modo, mapas, `add_map()`, `store()`, `staged_path()`, `flag_review()`) y `Revert_Context` (`unchanged()`), sobre la base común `Part_Context` (informe y huella).
- **Ganchos:** además de los dos filtros, `digitalisimo_elements_kit_media_limits`, `digitalisimo_elements_kit_guard_limits`, `digitalisimo_elements_kit_existing_document`, `digitalisimo_elements_kit_imported`, `digitalisimo_elements_unconverted_convert` y `digitalisimo_elements_kit_theme_builder_fields`. F7a añadió `digitalisimo_elements_kit_used_globals_trees( array $trees, Export_Context $context )` en `Kit_Settings::export()`, por el que una parte aporta árboles de ajustes para que sus colores y tipografías globales entren en `used`: sólo suma, y es la única excepción declarada a «sin tocar K» (`docs/fusion/k-kits.md`, §15).

**Obligación de F7a, F7b, F4-Woo y SEO.** Cada dato nuevo que deba viajar en un kit se declara así, con su caso en `tests/playground/kit-roundtrip/cases.json`; toda meta del kit va en `kit_meta_keys()`; `version()` sube al cambiar el formato; ningún CSS generado lleva una URL absoluta de `uploads`. El inventario en tiempo de ejecución falla si un control con aspecto de referencia no está declarado.

| Etapa | Qué añade | Cómo, sin tocar K |
|---|---|---|
| F7a · presets | La opción `digitalisimo_elements_presets` del sitio (no hay meta del kit) y el control `digitalisimo_preset` de cada elemento | Parte `presets` (versión 1, `kit_meta_keys()` vacío), que exporta la opción entera con los presets inertes. «Nuevo» reemplaza; «agregar» fusiona por slug (misma huella reutiliza, otra huella pasa a `slug-k<6 hex>`, slug libre añade) y nunca cambia un preset del sitio. `map:presets` sobre `digitalisimo_preset`, sin `gate` y con la identidad incluida, para todos los slugs del kit. Los globales que sólo usa un preset entran por el filtro `digitalisimo_elements_kit_used_globals_trees`, la única línea de F7a en K |
| F7a · degradados, máscaras y patrones | Ajustes de elemento y las copias saneadas `uploads/digitalisimo-elements/masks/<sha8>.svg` de los SVG del sitio | Nada nuevo que declarar: los colores de las paradas son `__globals__`, el SVG elegido es una referencia `media` derivada de su control y la ruta relativa de `digitalisimo_mask_file` o `digitalisimo_pattern_file` la exporta, sanea y traduce la parte `files`. Las formas de la biblioteca viajan por su slug. Nunca una URL en el CSS |
| F7a · condiciones nuevas | Argumentos con IDs | `condition · digitalisimo_page_visit · pages.* · post` y `condition · digitalisimo_product_stock_of · products.* · post`, las dos con `gate`; el centinela viaja tal cual porque `special_page` no tiene referencia |
| F7a · prioridad del Theme Builder | Un campo por plantilla | Campo `priority` de `digitalisimo_elements_kit_theme_builder_fields`: se escribe si falta y reindexa con el valor recibido |
| F7b · acciones con destino | `_digitalisimo_actions` | Referencias a la entrada (`post`) y al elemento destino (`element`). En los kits propios los IDs de elemento se conservan (K-D20); en los de terceros llegan regenerados y el informe marca el destino que falte |
| F7b · consultas guardadas | CPT con UUID | Parte `saved-queries` y `map:queries` |
| F7b · componentes v3 | Plantilla y `_digitalisimo_slot` | Parte `components` y referencias `template` |
| F4-Woo · widgets nuevos | Controles por ID (`selected_element`, plantilla de la vista rápida) | Referencias declaradas con `digitalisimo_elements_kit_references` (`element`, `template`); el resto son controles `Query` que traduce el núcleo. Hecho (Elements 4.3.0.166) |
| F4-Woo · atributos y muestras | Taxonomías `pa_*`, la configuración de muestras de `digitalisimo_elements_swatches` y las metas de término (propias o `color`/`image` de `wc-visual`) | `woo-attributes`, una `Early_Part` que registra los atributos antes de las taxonomías, más referencias `term_meta` acotadas por taxonomía. Hecho (Elements 4.3.0.166) |
| F4-Woo · variaciones | `product_variation` | Parte propia `woo-variations`; el recuento de «no exportadas» pasa por el punto genérico de `Export_Context::get_not_exported()`, que sólo baja contadores (excepción D27, `docs/fusion/k-kits.md` §16). Hecho (Elements 4.3.0.166) |
| SEO (K-D18) | `_related_pillar`, `_related_location_id` | `scope = post_meta` con `kind` `post` y `term`; hasta entonces viajan sin traducir y el informe las cuenta |

El único límite del registro: los archivos de un kit sólo pueden ser medios por sha256 y SVG de separadores y máscaras. Otro tipo de archivo exige ampliar esas formas y el guardián en K.

**Datos y opciones.** Todas automáticas y sin pantalla de ajustes (ver «Opciones nuevas»). El estado de cada importación sale de las sesiones nativas del núcleo (`elementor_import_sessions` y `elementor_revert_sessions`), unidas a la opción de detalle de K. Los temporales viven en `uploads/digitalisimo-elements/tmp/<64 hex>/`, protegidos, con la ruta guardada relativa a `uploads`, y se borran al terminar y con el evento único `digitalisimo_elements_kits_cleanup` a las 24 h.

**Pruebas**
- **Unitarias**, en `validate.yml` y `release.yml`, tras `tests/elements-palette.php` y en el mismo orden: `tests/elements-kit-{guard,session,settings,remap,preserve,parts,pro-fixes,core-contract,actions}.php` y `tests/elements-kit-{inventory,fixtures,static,app-guard}.mjs`. La prueba del contrato es la sección 1 de `tests/elements-kit-parts.php`. Ninguna se salta sin `ZipArchive`.
- **Fixtures sintéticos** en `tests/sources/kits/` (generador `build.mjs`, `fixtures.lock.json`, hosts `.test`, correos `@example.com`).
- **Playground** (manual, K-D10): `tests/playground/kit-roundtrip.sh`, escenarios R1 (ida y vuelta), R1c (cuerpo exacto de la App), R2 (Envato), R3 y R3b (Pro 3.x y 1.x), R4 y R4v4 (sólo agregar con choque), R5 (reimportar), R6 (red), R7 (sin proveedor), R8 (revertir), R9 (permisos y entradas hostiles) y R10 (biblioteca), con `WOO`, `CORE`, `SLOW`, `QUICK=1` y `KIT_GOLDEN=write`, en los puertos 9461 a 9463; y `brand-audit.sh` con `STATE=kit` en el 9464.

**Límites declarados**
- **Sincronizaciones del núcleo:** nombres de runners, `register()`, ganchos, formato atómico o `IS_IMPORT_EXPORT_SUPPORTED` pueden cambiar. Lo detectan `elements-kit-core-contract.php` y el `golden/` del arnés (ver `docs/ELEMENTOR_CORE.md`).
- **Sesiones:** si Elements se desactiva a mitad de una importación, esa sesión falla y hay que volver a subir el kit. Sólo se revierte la última importación, y sus documentos van a la papelera.
- **Memoria y tiempo:** el tope de medios de la App; los kits grandes, por WP-CLI. Lo que no cabe en el presupuesto de red o en los 15 s de medios de una petición queda «no descargado (tiempo)». Los bucles del núcleo que crean y guardan los documentos corren en una sola petición cada uno; sin revisiones durante la importación y con las medidas de los SVG leídas del archivo (antes el núcleo las pedía por HTTP al propio sitio y esperaba 10 s), un kit de 70 páginas tarda en Playground de 14 a 16,5 s en `elementor-content` y de 12 a 16 s en la petición final (≈0,1 s por documento guardado; `kit-roundtrip.sh` con `SLOW=1`, A13 ok). Un kit de varios cientos de documentos puede pasar de 25 s: repartir esos bucles exigiría cambiar el proceso del núcleo.
- **Kits de terceros:** los IDs de elemento llegan regenerados; las rutas ocupadas reciben `-2` en «agregar»; con contenedores o v4 apagados, el contenido se conserva oculto; un host caído deja el marcador de posición nativo; las fuentes de Google sólo se listan.
- **Ajustes del kit en «agregar»:** las páginas usan el estilo del sitio hasta «Aplicar los ajustes del kit».
- **Componentes v4:** se conservan como marcador `component`, pero ni se exportan ni se pintan: es un límite del núcleo.
- **kses en la red:** para un administrador sin `unfiltered_html`, el núcleo sanea el contenido y la carga de los marcadores; se informa.
- **SVG con la subida sin filtrar apagada:** los de la biblioteca se omiten (K-D15).
- **Envato:** sin kits reales, la conversión sólo está probada con un formato sintético (K-D17).
- **Plugin Elementor original activo:** sus flujos 2.0 siguen como él los hace (K-D19).
- **Pendientes de otras etapas:** metas de SEO con IDs (K-D18), plantillas de red de Tools con su propio remapeo (K-D14). Un kit de K en un sitio sin Elements pierde los widgets propios. Las variaciones de producto las trajo F4-Woo (`woo-variations`).
- **Defectos de K que destapó la validación de F4-Woo** (`docs/fusion/f4woo.md` §7; se corrigen en un incremento propio de K, sin tocar F4-Woo): la exportación por defecto (App sin «Editar contenido», WP-CLI sin `--post-types`) exporta los tipos propios pero sólo las taxonomías de los tipos nativos, así que un kit con productos pierde `product_cat`, `pa_*` y sus metas; en «nuevo», los seis `woocommerce_*_page_id` del kit llegan sin traducir; «nuevo» se rechaza si WooCommerce ya creó sus páginas; y `wc-products` de PRO tiene controles de consulta (`posts_posts_ids`, `posts_authors`, `posts_<taxonomía>_ids`) sin declarar en el inventario.

### F2 · Base común de conversión

**Eliminado el 2026-10-20** (cambio de alcance): se conserva como registro. El importador de kits es K.

- El módulo `digitalisimo-import` (arquitectura, puntos 1 y 2) y Migración v2 (punto 10), con la fachada de Element Pack sin cambio de comportamiento.
- Flujo de tema: borradores + `awaiting_theme` en producción. Al activar el tema propio, la tarea se reanuda: activa por hash, publica las plantillas del Theme Builder y retira el origen. Las plantillas convertidas nunca se publican antes del cambio de tema, porque reemplazarían la cabecera de cualquier tema.
- **Pruebas:** drivers falsos (orden y fases, compuerta de Element Pack, `state['v']`, reversión y reglas de «no pisar», mapa de IDs idempotente, reanudación sin duplicar), `migrate.sh` en verde y regresión con un kit real de Envato.

### F-front · Fase 2 del front

**Publicado** · Elements 4.3.0.163 y SEO 1.0.232 · `v2026.10.21.417`. Sólo cambian DIGITALÍSIMO Elements y DIGITALÍSIMO SEO: Tools, Migración, Backups, Hosting, IA Tools, el tema y `digitalisimo-ui` no cambian. Resultado, cifras, interruptores y presupuesto en [docs/ELEMENTS_FRONT_OBJECTIVE.md](ELEMENTS_FRONT_OBJECTIVE.md) («Fase 2 · resultado»); medición en `docs/measurements/2026-10-21-elements-front-phase2.md`.

**Objetivo.** Controladores de PRO por elemento (Fase 2), para validar cada conversión una sola vez: una página hecha con el núcleo y widgets propios no paga los paquetes globales de PRO, y una que los usa los recibe siempre. Lo que decide el núcleo se mejora con valores por defecto donde el sitio nunca eligió, nunca escribiendo sus ajustes ni sus experimentos.

**Qué cambia**
- **Puerta de PRO** (`modules/digitalisimo-front/class-pro-gate.php`). `plugin.php` encola los dos paquetes como siempre; la puerta recorre los datos de los documentos impresos y, en `wp_footer` 19, los retira si nada los pidió. Decide por la **clase** de cada elemento, así que los adaptadores de Migración (que heredan de un widget de PRO con otro nombre) cargan PRO sin que Elements los nombre. Falla abierta: editor, vistas previas, Personalizador, excepciones y dudas dejan los paquetes. Filtros `digitalisimo_elements_pro_frontend_needed` y `digitalisimo_elements_pro_frontend_third_party`; `Pro_Gate::require()` es la API que usará F7b para «abrir popup u off-canvas» de `_digitalisimo_actions`.
- **Contador de popups.** Sin los paquetes, en sitios con popups, una réplica de `setViewsAndSessions()` (`assets/popup-counter.js`, ≤ 0,5 KB) va en línea en posición `before` de `elementor-frontend`.
- **Valores por defecto del núcleo** (`class-core-defaults.php`, `class-icon-scan.php`), sólo con el núcleo incluido: `swap` por `default_option_elementor_font_display`; iconos en línea por el valor por defecto del experimento, sólo tras el análisis y «Aplicar» (o, heredado de la red, con veredicto `clean`); `disable` y `0` para la caché de elementos y la carga optimizada de imágenes con sus interruptores.
- **Librerías** (`class-lazy-libs.php`, `assets/lib/prism/`): Prism 1.23.0 y clipboard.js 2.0.0 desde el plugin, con los mismos bytes que el CDN; `jshint` de WordPress en el editor de Código personalizado; cargador opt-in `lazy_libs`.
- **Pantalla «Rendimiento»** (`admin.php?page=digitalisimo-elements-front`), en el sitio y en la red, con `digitalisimo-ui` y sin hoja propia; **transición** por sitio (`class-transition.php`) que vacía una vez CSS y cachés del constructor bajo cerrojo `INSERT IGNORE` y sólo después aplica.
- **SEO 1.0.232**: recálculo del blindaje de fuentes con `add_option__elementor_local_google_fonts` y `update_option__elementor_local_google_fonts`, y fila del `font-display` en vigor (sitio) o nota fija (red) en Rendimiento. Sin opciones nuevas en SEO.
- **Derivados de PRO** cambiados, con su nota en `digitalisimo-elements/DIGITALISIMO_CHANGES.md`: `modules/lottie/widgets/lottie.php` y `modules/code-highlight/widgets/code-highlight.php` (sólo `get_script_depends()`), `modules/code-highlight/module.php` (sólo `register_frontend_scripts()`) y `modules/custom-code/custom-code-metabox.php` (sólo el `jshint` del editor). Además, una línea en `pro-elements.php` arranca el módulo.

**Aceptación** (§9 de la especificación; evidencia en la medición)

| # | Criterio | Estado |
|---|---|---|
| 1 | Vacía sin PRO | **Pasa:** `empty` y `all-own` sin paquetes, −4 peticiones y −382 667 B de JS, CLS relativo igual |
| 2 | PRO donde hace falta | **Pasa:** las 23 páginas con paquetes son las esperadas, con su razón y su comprobación funcional, también con `CACHE=1` |
| 3 | Negativos | **Pasa:** `core-only`, `pro-static`, `third-party-free` y `v4-only` sin paquetes; `v4-only` conserva `elementor-frontend` |
| 4 | Migración | **Parcial:** `MIGRATION=1` en verde (menú, formulario y cuenta atrás heredados); `migrate.sh` y `legacy-ab.sh` no se corrieron (copia de Element Pack sin descarga oficial anotada) |
| 5 | Plugin original | **Pasa:** `CORE=plugin` con las mismas decisiones y sin valores por defecto; `core-switch.sh` con sus dos `has_filter` |
| 6 | SEO | **Pasa:** `SEO=1` (diferido sin respaldo) y `SEO=guard` (copia `digitalisimo-` con `swap`) |
| 7 | Valores por defecto | **Pasa:** `DEFAULTS=old`, `old-applied` (con y sin `CACHE=1`), `old-dirty`, `old-inactive` y `off` |
| 8 | Librerías | **Pasa** `code` y `code-langs` (Prism local, ningún CDN, `.token` en los 37, ningún 404) y `LAZY=1` con el tema de bloques (tras el ajuste de `Lazy_Libs::declares()`, cubierto por `tests/elements-local-libs.php`) y con `THEME=1` |
| 9 | Red | **Pasa:** `front-network.sh` N1–N7 |
| 10 | Interfaz | **Pasa:** `shared-ui.sh` (18 pantallas) y `brand-audit.sh SCOPE=full` (194 pantallas) con «Rendimiento» |
| 11 | Lighthouse 13.5.0 | **Pasa:** −386 068 B en `empty`, −385 930 B en `all-own`, `pro-heavy` +0,00 % |
| 12 | Regresión | **Pasa:** `conditions.sh`, `kit-roundtrip.sh` y `form-submit.sh` en verde. `interaction.sh` aplica ahora la transición en su preparación (`interaction/transition.php`) y pasa en los dos modos (29 y 29) |
| 13 | CI | Las doce pruebas nuevas en `validate.yml` y `release.yml`, con listas iguales; sha256 de los cuatro paquetes sin cambio |
| 14 | Paquetes | Al publicar: `build-packages.sh` sin abortar, `verify-release.mjs` con 8 ZIP, `unzip -t` y Release con los 8 assets |

**Límites declarados**
- `migrate.sh` y `legacy-ab.sh` quedan pendientes hasta tener una copia de Element Pack con su descarga oficial anotada en `reference/OFFICIAL-SOURCES.txt`.
- Prism 1.23.0 tiene avisos publicados (CVE-2024-53382, corregido en 1.30.0): su subida es una etapa propia. Un lenguaje añadido por un tercero que no esté en el subconjunto queda sin resaltar.
- La transición vacía CSS y cachés del constructor una vez por sitio al actualizar; un plugin de caché de página que no escuche `elementor/core/files/clear_cache` necesita una purga manual.
- Con un tema de bloques el contenido se pinta antes de `wp_head`: `Lazy_Libs::declares()` declara el cargador mientras `wp_enqueue_scripts` no ha corrido y, si el registro falla, `HANDLE` queda como alias de las librerías.
- Fallar abierto tiene coste: un elemento PRO oculto por condiciones, o una cabecera con el Menú de PRO o sticky, cargan los paquetes; el contenido con widgets PRO que un tercero inyecte por AJAX necesita el filtro `…_needed` o el interruptor apagado.
- Una lectura de `sitemeta` por petición en una red sin caché de objetos.
- Volver a 4.3.0.162 exige «Vaciar archivos y datos» en Herramientas del constructor.

### F7a · Capacidades que Divi y Betheme necesitan

**Publicado** · Elements 4.3.0.164 · `v2026.10.22.418`. Sólo cambia DIGITALÍSIMO Elements, desde 4.3.0.163. El tema sigue en 1.0.0 sin que cambie ningún archivo de `digitalisimo-theme/`, y SEO, Tools, Migración, Backups, Hosting, IA Tools y `digitalisimo-ui` no cambian. Diseño, hechos del núcleo, ganchos, formatos y desviaciones en [docs/fusion/f7a.md](fusion/f7a.md).

**Objetivo.** Integrar en Elements, sin importar nada de los orígenes y sin motores paralelos, lo que Divi y Betheme ofrecían y el constructor no tenía. Todo es opt-in por instancia o por documento, con el valor por defecto igual al de 4.3.0.163, y cuesta 0 B en las páginas que no lo usan. Es clean-room: la capacidad se inspira en el comportamiento descrito; el código y el catálogo de formas son propios (`docs/third-party-licenses.md`).

**Qué cambia para el administrador**
- **Presets de estilo** (`modules/digitalisimo-design/presets/`), en widgets v3 y contenedores:
  - control «Preset de estilo»;
  - menú contextual con «Guardar estilo como preset…», «Usar sólo el preset» y «Desvincular y copiar estilos»;
  - vista previa en el editor;
  - pestaña «Presets de estilo» en Ajustes del sitio (grupo «Sistema de diseño») con los usos de cada preset; los de un tipo ausente aparecen como «tipo no disponible», sin acciones.

  Crear, cambiar, renombrar o borrar exige `manage_options` sin la restricción `design` del Role Manager. Vincular, poder diseñar. Borrar un preset en uso se rechaza.
- **Degradados** (`backgrounds/class-gradient.php`), sobre el grupo de fondo del núcleo: hasta 8 paradas intermedias, cónico (con ángulo y posición propios) y repetido. Valen en sección, columna, contenedor y widget, en normal, hover y superposición; las paradas A y B siguen siendo las del núcleo.
- **Máscaras de recorte** (`backgrounds/class-mask.php`) en contenedores, secciones y columnas, con 12 formas propias o un SVG de Medios. Los widgets conservan la máscara del núcleo.
- **Patrones** (`backgrounds/class-pattern.php`) en los cuatro: una tesela coloreada y repetida sobre el fondo, con 12 teselas propias o un SVG de Medios, y «Elevar sobre lo siguiente», activado por defecto.
- **Condiciones nuevas** (`modules/digitalisimo-conditions/`), en el gestor nativo:
  - variables: `digitalisimo_cookie`, `digitalisimo_page_visit` y `digitalisimo_product_stock_of`;
  - cacheables: `digitalisimo_field`, `digitalisimo_user_field`, `digitalisimo_author` y `digitalisimo_search`.

  Cada una lleva el aviso de que no protege contenido.
- **Prioridad de las plantillas** (`theme-builder/class-priority.php`): «Prioridad» de 1 a 99 en las plantillas del Theme Builder de ubicación no múltiple. Gana el número menor, con la escala de PRO.
- **Sticky «al fijarse»** (`sticky/`): «Al fijarse» en los descendientes de un contenedor fijo, con «Sólo mientras está fijado», «Oculto mientras está fijado» y sus variantes tras el desplazamiento de efectos. Sólo CSS.
- **Controles de documento** (`document/class-document-controls.php`):
  - «Ocultar imagen destacada», con cualquier tema;
  - «Ocultar cabecera» y «Ocultar pie», sólo con el tema DIGITALÍSIMO; con otro tema, una nota remite a las condiciones de la plantilla.
- **Pantalla «Rendimiento» → «Privacidad»:** «Registrar visitas sin gestor de consentimiento» (`page_visits_no_consent`), con paridad sitio/red y apagada por defecto.

**Decisiones** (sección V de la especificación, que sustituye a la v1 donde chocan)

| Tema | Decisión |
|---|---|
| Presets: datos | Opción del sitio `digitalisimo_elements_presets`, sin carga automática, con hasta 200 presets, 16 KB por preset y 128 KB en total (filtro `digitalisimo_elements_preset_limits`). Un preset de un tipo no registrado se guarda `inert`, no se aplica ni se edita, viaja en los kits y vuelve cuando el tipo existe |
| Presets: aplicación | **Aplanados en el CSS del documento que los usa** (`elementor/element/before_parse_css`): las claves del preset que el elemento no guarda se escriben en memoria y el elemento genera su CSS con sus propios selectores. 0 B en el CSS del kit, sin selector propio ni `:where()`; el `_elementor_data` y el HTML no cambian. Cada preset se valida aparte antes de aplanarse; uno con error no se aplica y el editor lo marca |
| Presets: qué es estilo | Semilla de controles con `selectors` (fuera MEDIA, GALLERY, REPEATER, URL, WYSIWYG, CODE, clases, ID, CSS propio, `prefix_class`, `__dynamic__`), cierre por dependencias (conmutadores, `condition`, `{{otro.X}}`, POPOVER_TOGGLE) y lista blanca por tipo de control. Una comprobación final con `\`, `//`, `url(`, `image-set(`, `@`, llaves, `<` o `expression(` descarta el preset entero |
| Presets: lo local gana | Un control es local o del preset, nunca mezclado: si el elemento guarda cualquier variante responsiva, el preset no aporta ninguna. En v4, nada nuevo |
| Presets: invalidación | Un `clear_cache()` por petición, en `shutdown`. Renombrar no vacía nada |
| Degradados | Controles propios `digi_stops`, `digi_kind`, `digi_conic_from`, `digi_conic_at` y `digi_repeat` por prefijo de grupo. Reglas en los mismos selectores y `@media` que el núcleo, con `add_rules()` en array. Sólo la pila `common` en widgets. Sin controles nuevos, el CSS es byte a byte el del núcleo |
| Máscaras y patrones | SVG codificado en porcentaje, sin `;` ni base64, en una variable `--digi-mask-<slug>` o `--digi-tile-<slug>` por documento. Un SVG del sitio se sanea (saneador del núcleo, lista de rechazo y tope de 8 KB) y se copia a `uploads/digitalisimo-elements/masks/<sha8>.svg`, comprobado con `realpath`; el CSS lee esa copia, nunca el adjunto ni una URL. El patrón va en `::after` con `display: block`, el elemento lleva `isolation: isolate` y, con «Elevar», `z-index: 1`. Catálogo propio, sin `diagonal`, `arco` ni `ola` |
| Condiciones: fallar cerrado | Centinela `{ condition: digitalisimo_special_page, comparator: is_one_of, pages: [ digitalisimo_f7a ] }` al final de cada grupo con una condición de F7a, en v3 y en el prop v4. 4.3.0.164 lo cumple; 4.3.0.163 no reconoce esa página y el grupo falla. Se retira al cargar en el editor. Siempre registradas: sin WooCommerce, las de existencias dan `false`; una lista vacía nunca se cumple |
| Condiciones: v4 | No se ofrecen en el control de v4. Si un documento v4 las trae, el centinela va en el prop. Un elemento v4 con condiciones es dinámico para la caché de elementos, y sus condiciones variables sacan la página de la caché de página: una corrección que vale para todas |
| Cookie | Nombre normalizado como PHP. Vetos por fragmento (`wordpress_`, `wp-postpass_`, `wp-settings-`, `woocommerce_`, `comment_author_`, `sess`, `auth`, `token`, `login`, `pass`, `secret`, `csrf`, `nonce`, `key`, `hash`…), ampliables con `digitalisimo_elements_cookie_condition_denied`. Sólo existe, no existe, es y no es. Configurarla exige `unfiltered_html` (en una red, el superadministrador), comprobado en el servidor |
| Campos | Meta del contenido o del término consultado, salvo la protegida; usuario por lista blanca (`digitalisimo_elements_field_condition_user_keys`); nunca opciones. Claves vetadas ampliables con `digitalisimo_elements_field_condition_denied` |
| «Visitó la página» | Cookie `digi_seen` (o `digi_seen_<blog_id>` en una red), con la ruta de `home_url( '/' )`, hasta 50 IDs en base 36, 30 días, `SameSite=Lax` y `Secure` en HTTPS. La escribe un script en línea (menos de 400 B) sólo en las vistas individuales rastreadas, con el consentimiento `preferences` de la WP Consent API o, sin ella, con `page_visits_no_consent`; el filtro `digitalisimo_elements_page_visit_tracking` lo apaga. El índice cuenta sólo documentos publicados |
| Prioridad | La escala de PRO, de 1 a 99; vacío es automática. Sustituye la prioridad calculada en cualquier `include` que se cumpla y un `exclude` sigue mandando. A igual número gana la manual, y entre manuales iguales, el ID más alto. Sin manuales entre las candidatas, el array sale idéntico (`===`). Viaja como ajuste del documento y como campo de kit |
| Sticky | Sin pila de ancestros: la hoja reconoce al ancestro fijo por su propio `data-settings` (`"sticky":"top"` o `"bottom"`) y el hijo lleva su clase por `prefix_class` (`digi-sticky-`). Funciona con la caché de elementos y sin destello |
| Documento | La cabecera y el pie se ocultan con `skip_doc_in_location()` para las plantillas y con el filtro público `digitalisimo_theme_header_footer` para el respaldo, sólo en esa petición; los ganchos `digitalisimo_before_*` y `after_*` siguen. La imagen destacada se oculta sólo en el widget del contenido consultado, nunca en un bucle; `og:image` no cambia. El tema no cambia |
| Guardas | Cada submódulo comprueba sus símbolos del núcleo (`class_exists`, `method_exists`) y `ELEMENTOR_VERSION` ≥ 4.3. Sin ellos no registra nada, y el editor avisa a quien puede administrar |
| Playground en CI | No (como K-D10). Unitarias en los dos workflows; `design.sh` y las ampliaciones, a mano antes de publicar y tras cada `sync-elementor-core.sh` |

**Frentes.** Rutas relativas a `digitalisimo-elements/modules/digitalisimo-design/` salvo que se diga otra cosa.

| Frente | Qué entrega | Archivos |
|---|---|---|
| A · base | Arranque, guardas, utilidades CSS y cerrojo compartido `Digitalisimo\Elements\Lock` (lo usa también la conversión de la cabecera y el pie de Hello) | `class-module.php`, `class-css.php`, `class-lock.php`, una línea en `pro-elements.php`; `tests/elements-design-css.php` |
| B · almacén | `Store` (saneado, límites, inertes, usos), acciones AJAX y `Guard` (tipo y restricción `design`) | `presets/class-{store,ajax,guard,module}.php`; `tests/elements-design-presets.php` |
| C · aplanado | Control `digitalisimo_preset`, `Compiler` y pestaña del kit | `presets/class-{controls,compiler,kit-tab}.php`; `tests/sources/design/presets.json` |
| D · editor | `presets-editor.js` (opciones por tipo, vista previa por `editor/style/styleText`, menú contextual) y su configuración | `presets/class-editor.php`, `presets/assets/presets-editor.js`; `tests/elements-design-presets-editor.mjs` |
| E · kit | Parte `presets`, referencia `map:presets` y el filtro de globales en K | `presets/class-kit-part.php`, `digitalisimo-import/kits/parts/class-kit-settings.php` (una línea); `tests/elements-design-presets-kit.php`, `elements-kit-inventory.mjs`, `elements-kit-integration.php` |
| F · degradados | Paradas, cónico y repetido; vista previa | `backgrounds/class-gradient.php`, `backgrounds/assets/design-editor.js`; `tests/elements-design-gradient.{php,mjs}`, `tests/sources/design/gradients.json` |
| G · máscaras y patrones | Biblioteca propia, máscaras, patrones y copias de los SVG del sitio | `backgrounds/class-{library,mask,pattern}.php`, `library.php`, `MANIFEST.json`, `svg/`; `scripts/design-library.mjs`; `tests/elements-design-backgrounds.php` |
| H · condiciones | Las siete condiciones, el centinela, la guarda de la cookie y `Visitor_Content` | `digitalisimo-conditions/class-conditions-{more,guard}.php`, `class-module.php`, `class-visitor-content.php`, `class-conditions.php` (sólo `Special_Page_Condition`); `tests/elements-conditions-more.php`, `elements-visitor-content.php` |
| I · visitas | Cookie por sitio, consentimiento, índice, purga, privacidad y `page_visits_no_consent` | `digitalisimo-conditions/class-page-visits.php`, `digitalisimo-front/class-settings{,-page}.php`; `tests/elements-page-visits.{php,mjs}`, `elements-front-settings.php` |
| J · prioridad | Control, índice y la línea del derivado de PRO | `theme-builder/class-priority.php`, `modules/theme-builder/classes/conditions-manager.php` (una línea); `tests/elements-tb-priority.php` |
| K · sticky | Control y hoja | `sticky/class-sticky-state.php`, `sticky/assets/sticky-state.css`; `tests/elements-sticky-state.php`, `elements-front-budget.php` |
| L · documento | Cabecera, pie e imagen destacada | `document/class-document-controls.php`; `tests/elements-document-controls.php` |
| M · Playground | `design.sh` (DS1–DS13) y las ampliaciones de `front-budget.sh`, `conditions.sh`, `kit-roundtrip.sh` y `brand-audit.sh` | `tests/playground/design*`, `tests/playground/README.md`, `docs/fusion/f7a-evidence.json` |
| N · publicación | Revisión estática, documentación, versión y paquete | `tests/elements-design-static.mjs`, `tests/sources/design/pro-pinned.json`, `tests/validate-suite.mjs`, los documentos, `DIGITALISIMO_CHANGES.md` y `readme.txt` |

**Aceptación** (V.13 de la especificación). Evidencia en `docs/fusion/f7a-evidence.json`.

| # | Criterio | Estado |
|---|---|---|
| 1 | **Sin regresión:** un documento sin controles de F7a pinta el mismo HTML y CSS que con 4.3.0.163 | Unitarias existentes en verde, también con los cambios de F7a. Playground: `front-budget.sh` igual a 4.3.0.163 caso a caso (74/74 normal, 25/25 `CACHE=1`, 26/26 plugin; sólo cambian los *nonces* y la hora del horario, ningún CSS); `conditions.sh` 278 comprobaciones con y sin WooCommerce; `kit-roundtrip.sh` R1; `theme-switch.sh` rápido S1–S5 y E (margen de nodos de S3 de 22 a 29 por F-front); `form-submit.sh` en los dos modos; `interaction.sh` 28/28 widgets en los dos modos. El caso `editor` de `interaction.sh` falla igual con 4.3.0.163 en condiciones idénticas (tiempo del cliente REST con un worker): entorno, no F7a |
| 2 | **Coste cero donde no se usa:** mismos bytes, peticiones, handles y `$wpdb->num_queries` en `empty` y `all-own` | Unitarias: la opción de presets no se lee sin vínculos; el índice de visitas y el de prioridad sólo se leen si están en `wp_load_alloptions()`; los scripts del editor no se encolan en el front. Playground: `num_queries` iguales a 4.3.0.163 (150/141, 114/116 y 153/148 en los tres modos); ningún script del editor en las 146 cargas del front |
| 3 | **Presets que se aplican** (R-B1, V-B1, V-B2) | `elements-design-presets.php` compila los casos obligatorios de V.2.2 con el núcleo real. Playground: DS1 en verde en normal, `CACHE=1`, plugin 4.3.0/4.3.5 y `WOO=1` (161/161) |
| 4 | **Lo local gana** | Unitarias (R-M10 en PHP y en el editor). Playground: DS1 en tres anchos y píxeles ≤ 0,5 % |
| 5 | **Sólo estilo y permisos** | Las unitarias rechazan `\75 rl(`, `//`, `image-set(` y `@`, y borrar con usos. Playground: DS1 y R9 (`code` 403 de `elementor_ajax` para la editora) |
| 6 | **Presets en kits** | `elements-design-presets-kit.php`: «nuevo», «agregar», renombrado, identidad, inertes, globales que sólo usa un preset y revertir. Playground: `kit-roundtrip.sh` R1–R10 en verde (choque `tarjeta-k<hex>`, mapa de identidad sin `map.miss`, inerte conservado, color global traducido, reversión limpia) |
| 7 | **Degradados:** cadenas idénticas en PHP y JS | `elements-design-gradient.{php,mjs}` con las 24 fixtures (también el cónico con `from` y `at`) y el `addRules` real del editor. Playground: DS2 (las 2 diferencias conocidas del radial son del núcleo, no de F7a) |
| 8 | **Máscaras y patrones** | `elements-design-backgrounds.php`: `data:` en porcentaje por el `Stylesheet` real, SVG hostil sin regla, una variable por slug y documento con 20 usos, copias `masks/<sha8>.svg`. Playground: DS3, DS4 (desplegable visible sobre el contenedor siguiente), R4 con la subida sin filtrar apagada y `front-budget.sh` `design-pattern` en verde |
| 9 | **Condiciones correctas y clasificadas** | `elements-conditions-more.php` y `elements-visitor-content.php` en verde: vetos, restauración sin permiso, lista vacía, sin WooCommerce, cacheables y variables. Playground: `conditions.sh` 278 comprobaciones, con la caché apagada y a 24 h, con y sin WooCommerce |
| 10 | **Fallar cerrado** | La unitaria carga el `class-conditions.php` de 4.3.0.163 (sha256 comprobada) con las clases reales de PRO: con el centinela el grupo falla, también en v4. Playground: DS13 con el asset 4.3.0.163 (lo condicionado no se ve), R4 con un B en 4.3.0.163 (6 páginas sin lo condicionado) y DS11 |
| 11 | **Ninguna condición como protección** | Cada condición lleva el aviso (unitarias). Playground: DS5 y `conditions.sh` (ningún valor en el HTML) |
| 12 | **Visita de página** | `elements-page-visits.{php,mjs}`: consentimiento, opción, índice sólo de publicados, purga, script de 350 a 381 B. Playground: DS5 (con y sin la WP Consent API, y la opción) y DS12 (red de subdirectorios sin cruce) |
| 13 | **Prioridad manual** | `elements-tb-priority.php`: sin manuales el array sale `===`, orden de D20, `exclude`, reindexado y la línea del derivado. Playground: DS6 |
| 14 | **Sticky** | `elements-sticky-state.php` y una comprobación en Chrome con la hoja real (estados, espaciador, editor, sin ancestro). Playground: DS8 y DS9 con un Logotipo del sitio dinámico y la caché a 24 h |
| 15 | **Controles de documento** | `elements-document-controls.php`, con el `Locations_Manager` real de PRO, Hello y bucles; el árbol de `digitalisimo-theme/` es byte a byte el de 1.0.0. Playground: DS7 (tema DIGITALÍSIMO, Hello y bucles) |
| 16 | **Caché, plugin original, v4 y red** | Playground: DS9 (446/448), DS10 con 4.0.0, 4.3.0 y 4.3.5, DS11 (7/7) y DS12 (13/13) |
| 17 | **Guardas** | Unitarias: con un símbolo ausente, cada submódulo no registra nada y no hay ningún fatal |
| 18 | **Marca y diseño** | `tests/elements-design-static.mjs` en verde. Playground: `brand-audit.sh SCOPE=full` (220 pantallas, marca visible 0, proveedores 0) y `shared-ui.sh` (18 pantallas) |
| 19 | **Presupuesto** | Archivos dentro de su techo (`docs/ELEMENTS_FRONT_OBJECTIVE.md`). Playground: los 7 casos nuevos de `front-budget.sh` dentro de su techo en los tres modos |
| 20 | **CI y paquete** | Las catorce pruebas nuevas en `validate.yml` y `release.yml`, con listas iguales (`repo-hygiene.mjs`); `plugin.php`, los CSS y los paquetes JS de PRO y la huella de `elementor/` sin cambios. Al publicar: `build-packages.sh`, `verify-release.mjs` con 8 ZIP, `unzip -t` y la Release con los 8 assets y el índice |
| 21 | **Excepciones declaradas** | `migrate.sh` y `legacy-ab.sh` siguen pendientes de la copia oficial de Element Pack, como en F-front. En `kit-roundtrip.sh`, A13 (petición más lenta 25,7 s frente a 25 s) también falla con 4.3.0.163 con el mismo contenido (26,2 s): crece con los 14 documentos nuevos del arnés y F7a añade un 3 % por documento; la mejora es importar el contenido por lotes (etapa propia). A1 de la corrida final falló sólo por una captura del visor de PDF tomada antes de pintar (la corrida anterior pasó) |

**Límites declarados**
- **Volver a 4.3.0.163:** los ajustes quedan guardados y sin efecto. Tras «Vaciar archivos y datos» en Herramientas del constructor:
  - los elementos vinculados vuelven a sus valores locales y por defecto;
  - el degradado queda en A → B, y máscaras y patrones desaparecen;
  - los elementos «al fijarse» se ven siempre;
  - lo condicionado por una condición de F7a **no** se ve (centinela);
  - el Theme Builder vuelve al orden automático;
  - la cabecera, el pie y la imagen destacada se ven.
- **Presets:**
  - En una pila optimizada (front y WP-CLI) el núcleo quita `options` y `size_units` de cada control, así que ahí una opción se valida por su forma. Un valor bien formado escrito por WP-CLI que no está entre las opciones se aplica en el front y no en la vista previa.
  - Un preset no lleva las paradas intermedias de un degradado (son un repetidor) ni el SVG de una máscara o un patrón.
  - En la vista previa, si un control responsivo tiene un valor por defecto en tableta y el preset sólo define escritorio, gana el preset en tableta; en el front, no.
  - Las plantillas de red de Tools pueden copiar un elemento vinculado a un preset que el sitio destino no tiene: se ve con su estilo local.
- **Condiciones:**
  - Las de F7a no se ofrecen en v4, y el prop v4 `display-conditions` sigue sin `kind` en K: sus IDs no se traducen al importar un kit (otra etapa).
  - «Visitó la página» no hace nada sin gestor de consentimiento ni la opción, y lo dice la ayuda.
  - La unión de páginas rastreadas se corta en 200 sin aviso.
  - Las plantillas incrustadas sin `_elementor_conditions` no activan el rastreo.
  - La purga por entrada cubre `clean_post_cache()` y `litespeed_purge_post`; las demás cachés de página, a mano.
- **Máscaras y patrones:**
  - Si en el destino ya existe `masks/<sha8>.svg` con otro contenido, K renombra la copia a `<sha8>-k<hex>` pero no reescribe la ruta relativa del control, que sigue apuntando a la copia que ya había, también saneada. Sólo ocurre con 8 hex iguales y contenido distinto; arreglarlo exige traducir esas rutas en K (otra etapa).
  - La máscara recorta todo su contenido, también los desplegables. Con «Elevar» apagado, un desplegable puede quedar bajo el contenedor siguiente.
- **Sticky:**
  - Depende del formato de `data-settings`, que vigilan la prueba y `docs/ELEMENTOR_CORE.md`; la alternativa preparada es un ajuste oculto calculado al guardar.
  - En los dispositivos donde no se fija, «Sólo mientras está fijado» queda oculto.
- **Documento:**
  - Si un sitio fuerza la caché de elementos en el widget «Imagen destacada», la decisión queda en la caché de la plantilla.
  - En la página de entradas y en la tienda, leer los ajustes puede costar una consulta.
- **Prioridad:**
  - El aviso de conflictos del núcleo sigue apareciendo.
  - Las ubicaciones múltiples (popups y ganchos) no se reordenan.
  - Sin manuales, el desempate de PHP 7.4 sigue como hoy.

### F3 · Divi (D5 y D4)

**Eliminado el 2026-10-20** (cambio de alcance): se conserva como registro.

- Lectores D5 y D4 con tablas generadas desde `conversion-outline.json` y presets aplanados o vinculados. Unos 60 módulos automáticos; los parciales se declaran.
- Customizer y `gcid-*` al kit; `et_template` al Theme Builder con su tabla de condiciones; `et_pb_layout` y globales a plantillas vinculadas.
- Formularios: campos, validación y acciones hacia `modules/forms`. Las credenciales nunca se copian.
- Importación JSON (`et_builder`, `et_builder_layouts`, `et_theme_builder`) con imágenes base64.
- CPT `project`; menús de Divi a `menu-1`/`menu-2` o Nav Menu por ID; iconos por `iconList.json` y un marcador sin equivalente.
- **Aceptación:** un layout real importado, editado, guardado, reabierto y publicado sin Divi; un sitio migrado con Migración desactivada.

### F4 · Betheme / BeBuilder + WooCommerce visual

**La conversión de Betheme se eliminó el 2026-10-20** (cambio de alcance) y se conserva como registro. Los widgets WooCommerce nuevos siguen como F4-Woo.

- Tres generaciones de `mfn-page-items`, `old_id → css_*`, `be_classes`, globales, medianil y breakpoints.
- CPT `template` (cabecera con sticky y móvil, pie, single, archivo, producto, tienda, popup, megamenú) al Theme Builder; opción `betheme` al kit; metas SEO al importador de SEO; los 57 widgets `mfn_*`; demos locales.
- Widgets Woo nuevos, clean-room: wishlist (con exportación y borrado de datos personales), quick view, swatches con term meta propia, barra de envío gratis y cupón en el mini carrito pintados por fragmentos, filtros de precio y atributo. Se excluye la «venta falsa». La matriz indica si cada función la da WooCommerce, el constructor o una extensión.
- **Aceptación:** páginas comerciales y plantillas de producto representativas sin Betheme, con flujo Woo en modo de prueba.

### F4-Woo · WooCommerce visual

**Publicado** · Elements @NEXT_ELEMENTS@ (rama `fusion/f4-woo`; la etiqueta de la Release está en `docs/PLUGIN_ROADMAP.md`). Sólo cambia DIGITALÍSIMO Elements, desde 4.3.0.165; el tema, SEO, Tools, Migración, Backups, Hosting e IA Tools no cambian. `plugin.php`, los paquetes compilados de PRO y `elementor/` conservan su sha256. Diseño y evidencia en `docs/fusion/f4woo.md` y `docs/fusion/f4woo-evidence.json`.

**Objetivo.** Dar a las tiendas con WooCommerce lo que Betheme describía y el constructor no tenía, como widgets y rasgos propios, clean-room y opt-in, con coste 0 donde no se usan y **sin motor de filtros, sistema de kits ni mini carrito paralelos**: lista de deseos, vista rápida, muestras de variación, barra de envío gratis, cupón en el Menú del carrito y filtros de precio y de atributo. Sin WooCommerce (o con uno anterior a 10.0) no se registra nada en el front, en REST, en cron ni en WP-CLI; en el editor los widgets quedan ocultos y lo guardado se conserva. Se excluye la «venta falsa», también como opción.

**Qué cambia para el administrador**
- **Siete widgets** en la categoría DIGITALÍSIMO (`modules/digitalisimo-woo/`, `Woo\Registry::WIDGETS`): Botón de lista de deseos, Lista de deseos, Vista rápida, Muestras de variación (en el bucle), Barra de envío gratis, Filtro de precio y Filtro de atributo.
- **Lista de deseos:** de invitado en el almacenamiento del navegador (`digi_wishlist`, por sitio), escrita sólo tras el clic; con sesión, en la cuenta del sitio (`update_user_option`), con exportador y borrador de datos personales; al iniciar sesión, un aviso accesible ofrece «Añadir» la del navegador a la cuenta o «Descartar». Con la lista de deseos experimental de WooCommerce activa, la cuenta usa la de WooCommerce (una sola fuente) y la propia sólo se muda. Contador en la cabecera.
- **Vista rápida:** `<dialog>` nativo, un solo diálogo por página; «Diseño propio» por defecto (imagen y galería, título, valoración, precio, extracto, el formulario de compra de WooCommerce y «Ver la ficha completa») o, opt-in, «Plantilla de producto» (carga los paquetes de PRO en cada página con el botón: el editor lo dice y se mide). Un producto variable elige su combinación dentro del diálogo. Sin JavaScript, el enlace abre la ficha.
- **Muestras:** por atributo, en la pantalla de atributos de WooCommerce («Tipo de muestra»: desplegable, visual —color o imagen por término— o etiqueta; forma y tamaño), guardadas en la opción del sitio `digitalisimo_elements_swatches` y en metas de término propias (`digitalisimo_swatch_color`, `_image`, `_label`…); en un atributo `wc-visual` de WooCommerce se usan **sus** metas `color` e `image`, sin campos duplicados. El `<select>` del formulario clásico se conserva byte a byte y se oculta tras un `radiogroup` nativo; sección de estilo «Muestras» en los widgets «Añadir al carrito».
- **Carrito:** la barra de envío gratis sólo con el umbral **real** del método «Envío gratuito» de la zona del cliente y las tarifas que WooCommerce calcula para el paquete; el cupón del Menú del carrito usa `wc-ajax=apply_coupon`/`remove_coupon` con los nonces del fragmento. Los dos van por los fragmentos de WooCommerce, con un HTML común y los textos de cada instancia en `data-*`.
- **Filtros:** el de atributo es el tipo `taxonomy` del almacén de PRO; el de precio, el tipo propio `digitalisimo_price` (`digi_price_min`/`_max`), con la regla de WooCommerce para «Ocultar productos agotados» y el JOIN de `wc_product_meta_lookup`. Destino «Loop Grid de este documento» (AJAX, con el coordinador) o «Tienda» (parámetros nativos de WooCommerce sobre la consulta principal, con aviso de lo que no filtra).
- **Widgets de PRO:** tres partes nuevas en Productos (`quickview`, `wishlist`, `swatches`, fuera del valor por defecto) y el rasgo opt-in «Barra de envío gratis» y «Cupón» en el Menú del carrito.
- **Privacidad:** el texto de Ajustes → Privacidad de Elements gana el elemento de la lista de deseos (`digitalisimo_elements_privacy_items`) y, con la WP Consent API 2.1.0, la clave se declara como almacenamiento local en `functional`.
- **Kits:** los atributos con sus muestras (`woo-attributes`, `Early_Part`), las variaciones (`woo-variations`) y las referencias de los widgets viajan en el kit; `has_archives` cerrado en «agregar»; la tabla de búsqueda se refresca para cada producto creado.

**Decisiones** (sección V de la especificación; prefijo `F4W-D`)

| # | Tema | Decisión |
|---|---|---|
| F4W-D1 | Dónde vive | Módulo propio `modules/digitalisimo-woo/` (`Digitalisimo\Elements\Woo`), con su registro y una línea de arranque en `pro-elements.php` tras `Design\Module`; nada en `class-registry.php` |
| F4W-D2 | Sin WooCommerce | En el front, REST, cron y WP-CLI no se registra nada (0 consultas); en las peticiones del editor, los widgets se registran ocultos con el aviso «Necesita WooCommerce» y lo guardado se conserva (`Inert`). La importación por REST y WP-CLI deja marcadores de K, no `Inert` |
| F4W-D3 | Versiones | WooCommerce ≥ 10.0 (`WC_VERSION`) y guardas de símbolos por submódulo; sólo 11.2.1 está probado (`@since` comprobados en el frente 0) |
| F4W-D4 | Lista de invitado | `localStorage` con `digi_wishlist` (`digi_wishlist_<sitio>` en una red), sólo tras el clic; nunca una cookie: nada viaja al servidor ni cambia la caché. Servicio pedido expresamente, declarado en la política y en la WP Consent API |
| F4W-D5 | Lista con sesión | `update_user_option( $uid, 'digitalisimo_wishlist' )`, sólo para miembros del sitio; fusión sólo con «Añadir» en un aviso accesible, «Descartar» borra la copia local sin enviarla; hasta 100 productos; `woo/cards` por `POST` |
| F4W-D6 | Qué no hace la lista | Sin compartir, sin listas públicas, sin recuentos de popularidad ni avisos de bajada de precio |
| F4W-D7 | Vista rápida | «Diseño propio» por defecto; «Plantilla de producto» opt-in (`digitalisimo_template_id`, que la puerta de PRO ya recorre); `<dialog>` nativo con `showModal()`, uno por página; el botón es un enlace a la ficha con `no-prefetch` |
| F4W-D9 | Comprar desde la vista rápida | El formulario nativo de WooCommerce, con `woocommerce_add_to_cart_form_action` a la página de origen validada; sin AJAX propio de carrito; `wc_load_cart()` en las rutas REST propias |
| F4W-D10 | Muestras: activación | Por atributo en la pantalla de atributos de WooCommerce, en `digitalisimo_elements_swatches` (nunca en `attribute_type`); con un `wc-visual`, sus metas y sin campos propios |
| F4W-D11 | Muestras: valores | Metas de término propias; la imagen es la meta `image` de WooCommerce (ID de adjunto del sitio) elegida en el modal de Medios: excepción declarada en `AGENTS.md` a la regla del campo `media` |
| F4W-D12 | Muestras: formulario | El `<select>` byte a byte igual, oculto en un envoltorio, y un `radiogroup` de `<input type="radio">`; el estado se lee del `<select>` con `MutationObserver`; puente jQuery sólo en el modo AJAX de WooCommerce |
| F4W-D13 | Umbral de la barra | Sólo el `min_amount` real del método «Envío gratuito» de la zona del cliente; los estados salen de las tarifas que WooCommerce calcula para el paquete; sin destino, con varios paquetes o con un filtro ajeno, nunca «Te faltan» |
| F4W-D14 | Fragmentos | Dos nodos de estado comunes (`div.digi-ship__state`, `div.digi-coupon__state`) con el mismo HTML para todas las instancias, servidos por `woocommerce_add_to_cart_fragments` sólo cuando la bandera `digitalisimo_elements_woo_fragments` está encendida (documentos publicados que los usan) |
| F4W-D15 | Cupón | `wc-ajax=apply_coupon`/`remove_coupon` con los nonces del fragmento (nunca en el HTML de la página); formulario en el envoltorio persistente; sólo con `wc_coupons_enabled()`; opt-in por instancia |
| F4W-D16 | Filtros: mecanismo | Atributo = tipo `taxonomy` del almacén de PRO; precio = tipo propio `digitalisimo_price` registrado con `digitalisimo_elements_loop_filter_types`, con la regla de `Filterer` sobre `wc_product_attributes_lookup` y el alias `wc_product_meta_lookup` con la guarda de `ProductUtil` copiada |
| F4W-D17 | Filtros: coordinador | `filters.js` sustituye por herencia `refreshLoopWidget()` del filtro de taxonomía de PRO sólo en los bucles con un filtro F4-Woo (petición propia con `AbortController`, *debounce*, foco, `aria-busy`, `popstate`, destino desde el documento del filtro); se engancha al aviso `elementor-pro/modules/init/before`, porque `hooks` del núcleo nace en `init()`. Excepción declarada en `DIGITALISIMO_CHANGES.md`; nombres vigilados por `tests/elements-pro-inventory.mjs` |
| F4W-D18 | Opciones con pantalla | Ninguna: todo es por instancia o dato del sitio; las opciones automáticas van en «Opciones nuevas» |
| F4W-D19 | Playground en CI | No: unitarias en los dos workflows; `woo-flow.sh`, `front-budget.sh WOO=1`, `kit-roundtrip.sh WOO=1` y `brand-audit.sh WOO=1` a mano, con la evidencia en `docs/fusion/f4woo-evidence.json` |
| F4W-D20 | WooCommerce fijado | 11.2.1 de wordpress.org con su sha256 en `reference/OFFICIAL-SOURCES.txt`, sólo leído y montado en Playground efímero (`WOO_REQUIRE=1`) |
| F4W-D21 | Caché de elementos | El HTML de los widgets nunca depende del visitante; lo que depende (nonce, lista con sesión, configuración de variaciones) va en línea en el pie según el handle encolado, nunca en `render()`; `assets` en los controles de las partes y del rasgo |
| F4W-D22 | Texto de privacidad | Uno solo para Elements: encabezado genérico de `Page_Visits::privacy_text()` y el filtro `digitalisimo_elements_privacy_items` |
| F4W-D23 | «Venta falsa» | Excluida, también como opción; la revisión estática prohíbe esos textos |
| F4W-D24 / D25 | Productos y Menú del carrito de PRO | Tres partes fuera del valor por defecto; rasgo opt-in `Mini_Cart_Extras` «Arriba» o «Abajo» del panel |
| F4W-D26 | Volver a 4.3.0.165 | Declarado y probado (W13): los widgets nuevos no pintan nada; al guardar en el editor se borran (límite del núcleo), con recuperación por revisiones; desde 4.3.0.166 la guarda hacia delante (`class-unknown-guard.php`, API de `Preserve`) conserva los widgets futuros |
| F4W-D27 | Variaciones en K | Punto de extensión genérico en `Export_Context::get_not_exported()`, que sólo puede **bajar** contadores, declarado en `docs/fusion/k-kits.md` §16; la parte `woo-variations` sólo deja las de padres no exportados |

**Frentes.** Rutas relativas a `digitalisimo-elements/modules/digitalisimo-woo/` salvo que se diga otra cosa.

| Frente | Qué entrega | Archivos |
|---|---|---|
| 0 · verificación | Hechos de WooCommerce 11.2.1 y de la WP Consent API 2.1.0 con archivo y línea, matriz de V.2 corregida, fixtures de `wc-visual` | `docs/fusion/f4woo.md` §1; `tests/fixtures/woo-visual-11.2.1.json` |
| A · base y contrato | Arranque, autocarga, guardas, registro tolerante, recursos con `defer`, base REST, banderas que existen siempre, privacidad, `Inert` y la guarda hacia delante | `class-{module,registry,assets,rest,flags,privacy,inert,inert-widget,widget,products}.php`, `modules/digitalisimo-widgets/class-unknown-guard.php`; `tests/elements-woo-module.php`, `elements-unknown-guard.php` |
| B · lista de deseos | Widgets, almacén propio, modo Store API y mudanza, fusión con confirmación, exportador y borrador | `wishlist/`, `assets/{css,js}/wishlist.*`; `tests/elements-woo-wishlist.{php,mjs}` |
| C · vista rápida | Widget, diálogo, ruta, variables, plantilla, compra | `quick-view/`, `assets/{css,js}/quick-view.*`; `tests/elements-woo-quick-view.{php,mjs}` |
| D · muestras | Pantalla de atributos, formulario, bucle, `Atc_Styles`, convivencia con `wc-visual` | `swatches/`, `assets/{css,js}/swatches*.*`; `tests/elements-woo-swatches.{php,mjs}` |
| E · carrito | Estado, fragmentos, barra, `Mini_Cart_Extras`, cupón | `cart/`, `assets/{css,js}/cart-extras.*`; `tests/elements-woo-cart.{php,mjs}` |
| F · filtros | Tipos, consulta, widgets, coordinador, formulario, script del editor, «Tienda» y las tres correcciones del derivado | `filters/`, `assets/{css,js}/filters*.*`; `modules/loop-filter/module.php`, `query/query-types/hierarchy-or-query.php`; `tests/elements-woo-filters.{php,mjs}`, `elements-loop-filter-parse.php` |
| G · Productos y Menú del carrito | Tres partes nuevas con `assets`; el rasgo | `modules/digitalisimo-widgets/class-products-parts.php`, `cart/class-mini-cart-extras.php`; `tests/elements-woo-products-parts.php` |
| H · kits | `woo-attributes`, `woo-variations`, tabla de búsqueda, referencias, el punto genérico de K y los casos | `kit/`; `modules/digitalisimo-import/kits/{class-export-context,class-references}.php`, `kits/parts/class-term-meta.php`; `tests/elements-woo-kit.php`, `elements-kit-extensions.php`, `tests/playground/kit-roundtrip/cases.json` |
| I · Playground | `woo-flow.sh` (W0–W13, un archivo por escenario en `woo-flow/{scenarios,setup,ops}/`), `front-budget.sh WOO=1` (12 casos), `kit-roundtrip.sh WOO=1`, `brand-audit.sh WOO=1` | `tests/playground/woo-flow*`, `front-budget/*`, `tests/elements-front-cases.php`, `elements-front-budget.php`; `docs/fusion/f4woo-evidence.json` |
| J · publicación | Revisión estática, inventario de PRO, páginas de WooCommerce en el kit sin permiso, documentación, versión y paquete | `tests/elements-woo-static.mjs`, `elements-pro-inventory.mjs`, `elements-woo-kit-page-sync.php`, `tests/validate-suite.mjs`, los dos workflows, los documentos, `DIGITALISIMO_CHANGES.md` y `readme.txt` |

**Aceptación** (V.17 de la especificación). Evidencia en `docs/fusion/f4woo-evidence.json`.

| # | Criterio | Estado |
|---|---|---|
| B1–B2 | Una sola fuente de lista de deseos; privacidad de la lista (ningún ID de producto en una URL; fusión sólo con «Añadir») | `elements-woo-wishlist.{php,mjs}`; W1, W2 y W12 en verde, también `NET=1` |
| B3–B4 | Atributos con guion; sin fatal de PHP 8 con sólo `digi_price_min` | `elements-loop-filter-parse.php` (render real de PRO); W9 en verde, también `CORE=plugin` |
| B5 | Coordinador independiente del paquete: petición propia, `post_id` del documento del filtro, un bucle sin filtros F4-Woo como en 4.3.0.165 | `elements-woo-filters.mjs` (también el arranque real, caso 1b); W9.8a–c |
| B6 | Vista rápida de un variable en una página sin variables | `elements-woo-quick-view.{php,mjs}`; W5 en verde |
| 1–2 | Sin regresión y coste cero donde no se usa | `front-budget.sh WOO=1`: `woo-empty`, `all-own`, `woo-loop-filter-pro` y `woo-pro-existing` con el mismo HTML y CSS que 4.3.0.165 en normal, `CACHE=1`, `CORE=plugin` y `V4=1`; los tres primeros también con los mismos bytes, peticiones, handles y consultas. **Diferencia declarada:** en `woo-pro-existing`, la respuesta de `get_refreshed_fragments` lleva +168 B (los dos nodos de estado) porque el sitio de prueba usa la barra en otras páginas y la bandera de fragmentos está encendida; la página en sí no cambia, y un sitio que no usa la barra ni el cupón no la paga |
| 3–4 | Sin WooCommerce, inocuo; guardas de versión | `elements-woo-module.php`; W0 en verde, con los pasos sin WooCommerce (W0.8 editor y guardado, W0.9 kit por los tres caminos) |
| 5–7 | Lista de invitado, con sesión, en red; privacidad, Consent API y borrado | W1, W2 (`NET=1`), W12 en verde; `elements-page-visits.php` |
| 8 | Caché de página simulada | W3 en verde (6 comprobaciones) |
| 9–11 | Vista rápida accesible, modo plantilla medido, compra sin reenvío | W4 y W5 en verde; `woo-quick-view-template` mide los 371 769 B de PRO declarados |
| 12–14 | Muestras con los datos de WooCommerce, sincronizadas, convivencia con `wc-visual` | `elements-woo-swatches.{php,mjs}`; W6 en verde (también con el tema de bloques) |
| 15–16 | Barra real; cupón | `elements-woo-cart.{php,mjs}`; W7 y W8 en verde (el hash del carrito sigue al destino: corrección de W11) |
| 17–19 | Filtros iguales a la tienda nativa (con y sin tabla, con «Ocultar agotados»); `popstate`, *debounce*, foco, destino; «Tienda» sólo donde filtra | W9 (59 comprobaciones) y W10 en verde, también `CORE=plugin` |
| 20 | Controles en widgets existentes sin `default` de estilo y con `assets` | `elements-woo-static.mjs` (12 controles), `elements-woo-products-parts.php`; `woo-pro-existing` |
| 21–22 | Kits con `WOO=1`; el punto genérico de K sin efecto cuando no se usa | `elements-woo-kit.php`, `elements-kit-extensions.php`; `kit-roundtrip.sh WOO=1`: R1 (pantalla y WP-CLI), R4, R4w (B en 4.3.0.165), R5, R6 y R8 en verde en lo propio de F4-Woo; R1 sin WooCommerce igual a 4.3.0.165. Los fallos restantes son de K o del entorno (A1, A13; `docs/fusion/f4woo.md` §7) |
| 23 | Vuelta atrás y guarda hacia delante | `elements-unknown-guard.php`; W13 en verde (15 comprobaciones) |
| 24–25 | Flujo Woo en modo de prueba; plugin Elementor original y v4 | W11 en verde con el núcleo incluido y con `CORE=plugin`, pago clásico y de bloques; W4, W5, W9 y W10 con `CORE=plugin`; `front-budget.sh V4=1 WOO=1` |
| 26–28 | Seguridad, SEO, marca y sin «venta falsa» | W12 en verde; `elements-woo-static.mjs` (654 frases, 5 `nofollow`); `brand-audit.sh WOO=1` y `shared-ui.sh` (pendientes) |
| 29 | CI y paquete | Las pruebas nuevas en los dos workflows con listas iguales; sha256 de PRO y de `elementor/` sin cambios; 8 ZIP y el índice en la Release (al publicar) |
| 30 | Excepción declarada: `migrate.sh` y `legacy-ab.sh` | Pendientes de la copia oficial de Element Pack, como en F-front y F7a |

**Límites declarados**
- **Experimental en WooCommerce:** la lista de deseos (`product_wishlist`) y `wc-visual` pueden cambiar; se detectan por símbolos y por ruta registrada, y si faltan, el modo propio (lista) o nada (muestras visuales). Sólo 11.2.1 está probado.
- **Coordinador:** depende de nombres del paquete compilado de PRO (`docs/ELEMENTOR_CORE.md`); si falta uno, los filtros van por GET.
- **Presupuesto:** los filtros cargan `filters.js` (8 161 B, el coordinador, una vez por página) y `filters-form.js` (5 673 B, el formulario): 13 834 B por página con filtros, por encima de los 8 KB por widget del criterio 7. **Excepción declarada** en `docs/ELEMENTS_FRONT_OBJECTIVE.md`: cada archivo cumple su techo y el coordinador se comparte entre todos los filtros y bucles de la página.
- **Vista rápida en modo plantilla:** carga PRO en cada página con el botón (opt-in, avisado y medido).
- **«Tienda» y «Consulta actual»:** un Loop Grid con «Consulta actual» no se filtra de forma fiable con el destino «Tienda» (sin la tabla de atributos WooCommerce mete el atributo en la consulta principal y el bucle lo hereda; el precio, nunca); el editor lo dice.
- **Vuelta a 4.3.0.165:** abrir y guardar en el editor un documento con los widgets nuevos los borra (límite del núcleo); se recuperan por revisiones. Desde 4.3.0.166 la guarda hacia delante lo evita hacia el futuro.
- **DS13 de `design.sh` (F7a):** tras la vuelta a 4.3.0.163, la comprobación de presets no encuentra los elementos en la página (24/25); falla igual desde `main` con 4.3.0.165 publicado, así que es previo a F4-Woo y queda anotado para F7a (sospechoso: WordPress 7.1.4 en Playground).
- **Playground:** `W0`–`W13` juntos no caben en el tope de 10 min de un escenario con la máquina cargada; se corren por partes. Desde el 22 de octubre Playground sirve WordPress 7.1.4, así que la referencia es una corrida de 4.3.0.165 hecha en las mismas condiciones, no una línea base guardada.

### F5 · GeneratePress

**Eliminado el 2026-10-20** (cambio de alcance): se conserva como registro. Lo importado a Tools sigue siendo posible desde el `functions.php` de un tema hijo.

- `gp_elements` sin PHP a Código personalizado o a plantillas Section en ubicaciones `digitalisimo_*`; cabecera, pie y hero al Theme Builder; condiciones y roles con las reglas existentes; layout por página a los ajustes del documento.
- Hooks con PHP al editor de fragmentos de Tools, traducidos de `generate_*` a `digitalisimo_*`, desactivados y con su origen. Migración nunca activa PHP.
- GenerateBlocks se convierte si las muestras y su copia oficial lo permiten. Los bloques del núcleo se quedan en el editor de bloques.

### F7b · Capacidades que Bricks necesita

- Registro de acciones con destino; interacciones PRO habilitadas tras el inventario; consultas guardadas y contextos; filtros ampliados (como tipos del registro `digitalisimo_elements_loop_filter_types` que estrenó F4-Woo, con su mismo coordinador `filters.js`: ningún motor nuevo).
- Sobrescrituras de contenido en Template; vista de usos y bloqueo de borrado.
- Slider anidado nuevo; presentación DIGITALÍSIMO sobre nested-tabs y nested-accordion.
- Edición múltiple y «aplicar a todos los de este tipo».
- Etiquetas dinámicas dentro de texto, con escape por contexto y lista blanca de campos de usuario.
- Lógica condicional de formularios.
- **Datos de kit:** las acciones con destino (`_digitalisimo_actions`: entrada como `post`, elemento destino como `element`), las consultas guardadas (parte `saved-queries` y `map:queries`) y los componentes v3 (parte `components` y referencias `template`) se declaran en el registro de K, sin tocar K.

### F6 · Bricks (con las exportaciones del usuario)

**Eliminado el 2026-10-20** (cambio de alcance): se conserva como registro.

- Árbol plano, clases (incrustadas en v3 o globales en v4), variables, paleta, theme styles, componentes (los slots pierden el vínculo y se declara), plantillas y condiciones, interacciones, datos dinámicos, query loop y filtros.
- Mapa de IDs de 6 a 7 caracteres.
- Primero se cierran con las muestras las claves no documentadas (`docs/fusion/bricks.md`, §6.4).

### F8 · Element Pack 10.x y Prime Slider en Migración

- Element Pack: el Addon entra en `Retire`; se corrigen las clases de 6 mapas; las extensiones convertibles pasan a ser nativas en Elements (tooltip como rasgo, sticky, transform, parallax aproximado) y las que se descarten necesitan aprobación del propietario; se convierten su Theme Builder, fuentes e iconos.
- Prime Slider: se detecta y se convierte, por defecto al Slider Optimizado ampliado. Swiper sólo con necesidad demostrada en la matriz. Sin la versión Lite, sus valores por defecto quedan pendientes.
- Un kit o una plantilla con widgets de Element Pack o de Prime Slider entra en Elements como marcador `digitalisimo-unconverted` (K). Migración los convertirá con el filtro `digitalisimo_elements_unconverted_convert`, que K ya publica y que sólo corre en el editor y en Migración, nunca en el front.

## Opciones nuevas (paridad sitio/red)

| Opción | Sitio | Red | Sanitización | Estado |
|---|---|---|---|---|
| `digitalisimo_compat` | La escriben la importación o la migración de ese sitio | — (automática, sin pantalla) | Lista cerrada de CPT, sidebars y ubicaciones del origen | Pendiente (F2) |
| `digitalisimo_theme_switch` | Registro del paso al tema en ese sitio: `origin`, `last`, `hello`, `header_footer` y `generated`. Carga automática y menos de 1 KB, porque el front lo lee | — (automática, sin pantalla) | Escrita sólo por el tema, bajo cerrojo; `generated`, `header_footer.pending` y `blocked` los escribe Elements | Publicado (F1) |
| `digitalisimo_theme_switch_log` | Bitácora del paso en ese sitio: hashes de lo copiado, lo omitido, el CSS adicional, el hijo, los filtros, la descripción y hasta 50 avisos sin repetir. **Sin carga automática** | — (automática, sin pantalla) | Escrita sólo por el tema | Publicado (F1) |
| `digitalisimo_theme_switch_user` | Id del administrador que activó el tema en ese sitio. Elements lo anota en `switch_theme` (el tema aún no está cargado en esa petición) y la conversión lo usa como autor cuando el registro no trae usuario. **Sin carga automática** | — (automática, sin pantalla) | Escrita sólo por Elements | Publicado (F1) |
| `digitalisimo_theme_switch_lock` y `digitalisimo_theme_convert_lock` | Cerrojos temporales del tema y de Elements: `INSERT IGNORE` con token; se roban pasados 300 s | — (automáticos) | Sin entrada del usuario | Publicado (F1) |
| `digitalisimo_theme_release` y `digitalisimo_tools_theme_release` (transitorios); `digitalisimo_theme_last_release` y `digitalisimo_tools_theme_last_release` (opciones) | — | Cachés de red del tema y de Tools (automáticas, sin pantalla) | Entrada del índice validada: versión, URL del repositorio y requisitos | Publicado (F1) |
| Fragmentos PHP | Fragmentos del sitio | Fragmentos de red, que corren en todos los sitios (Tools es de red) salvo donde estén pausados o detenidos | `Store::sanitize_item()` igual en las dos superficies; revisión de sintaxis, prueba previa, firma, capacidad y nonce | Publicado (F1b) · Tools 1.0.34 |
| `digitalisimo_elements_kit_session_<sesión>` | Detalle de cada importación del sitio: modo, usuario, origen, mapas, análisis, respaldo de los ajustes y metas del kit (también v4) e informe (≤ 64 KB, listas ≤ 200). **Sin carga automática**; se conservan las 10 últimas. Si está importada o revertida lo dicen las sesiones nativas (`elementor_import_sessions`, `elementor_revert_sessions`) | — (automática, sin pantalla: un kit siempre se importa en un sitio) | Escrita sólo por el importador, tras exigir `manage_options`; la carpeta temporal se guarda relativa a `uploads` y se comprueba con `realpath` | Publicado (K) · Elements 4.3.0.162 |
| `digitalisimo_elements_unconverted` | Número de marcadores `digitalisimo-unconverted` del sitio: un entero con carga automática. Con 0, ni el widget ni los ganchos de lectura se registran | — (automática, sin pantalla) | Entero ≥ 0, recalculado al importar y al guardar un documento con marcadores | Publicado (K) · Elements 4.3.0.162 |
| `digitalisimo_elements_kit_notice` (meta de usuario) | Aviso único «importación terminada» del administrador que importó; se lee de la caché de metas del usuario, sin consultas | — (automática, sin pantalla) | Escrita y borrada sólo por el importador | Publicado (K) · Elements 4.3.0.162 |
| Metas `_digitalisimo_import_origin` (entradas, términos y adjuntos), `_digitalisimo_unconverted` (documentos con marcadores), `_digitalisimo_original_type` (plantilla `section` que guarda un tipo ausente) y `_digitalisimo_unconverted_defaults` (kit: valores por defecto de tipos desconocidos) | En el contenido de ese sitio | — (automáticas, sin pantalla) | Escritas sólo por el importador. `_digitalisimo_import_origin` y `_digitalisimo_unconverted` nunca viajan en un kit (lista negra) ni las copia el duplicador | Publicado (K) · Elements 4.3.0.162 |
| `digitalisimo_elements_front` (sitio) y `digitalisimo_elements_front_defaults` (red) | Los seis booleanos de abajo y `own` (las claves que el sitio personaliza, siempre dentro de las seis); carga automática, < 200 B. Pantalla «Rendimiento» de DIGITALÍSIMO Elements, con «Heredar de la red» por clave sólo en un sitio de una red | Los seis booleanos en `sitemeta`, en Red → «Rendimiento» de Elements, sin «Heredar de la red»; una lectura por petición en una red sin caché de objetos | `Settings::sanitize()` en el sitio y `Settings::save_network()` en la red, clave por clave (`! empty()`), sin saneado genérico; `Settings::defaults()` y `Settings::network_fields()` completan los cuatro puntos. Nonce y capacidad por acción (`manage_options`; `manage_network_options` en la red) | Publicado (F-front) · Elements 4.3.0.163 |
| `pro_conditional` | Funciones avanzadas sólo donde se usan (puerta de PRO). Por defecto activado | Valor de red heredable; rige de inmediato en los sitios que no lo personalizaron | Booleano en las dos superficies | Publicado (F-front) · Elements 4.3.0.163 |
| `font_swap` | `font-display: swap` por defecto para las fuentes de Google del constructor donde el sitio nunca lo eligió. Por defecto activado; sólo con el núcleo incluido | Ídem | Booleano en las dos superficies | Publicado (F-front) · Elements 4.3.0.163 |
| `svg_icons` | Iconos en línea (SVG): «Analizar», «Aplicar» y «Revertir» en el sitio. Por defecto apagado; sólo con el núcleo incluido | «Aplicar los iconos en línea (SVG) en los sitios cuyo análisis salga limpio»: un sitio que lo hereda sólo lo aplica con veredicto `clean` | Booleano en las dos superficies; «Aplicar» y «Revertir» lo guardan como elección del sitio | Publicado (F-front) · Elements 4.3.0.163 |
| `lazy_libs` | Cargar Lottie y el resaltado de código al acercarse a la pantalla. Por defecto apagado | Valor de red heredable | Booleano en las dos superficies | Publicado (F-front) · Elements 4.3.0.163 |
| `element_cache_off` | Desactivar la caché de elementos donde el sitio no la eligió. Por defecto apagado; sólo con el núcleo incluido | Valor de red heredable | Booleano en las dos superficies | Publicado (F-front) · Elements 4.3.0.163 |
| `image_loading_off` | Desactivar la carga optimizada de imágenes donde el sitio no la eligió. Por defecto apagado; sólo con el núcleo incluido | Valor de red heredable | Booleano en las dos superficies | Publicado (F-front) · Elements 4.3.0.163 |
| `digitalisimo_elements_front_applied` | Valores en vigor, huella, veredicto de iconos y fecha; carga automática. Es lo único que leen la puerta, los valores por defecto y el cargador en el front | — (automática, sin pantalla: cada sitio transita en sus peticiones) | Sólo la escribe la transición, al final y bajo cerrojo | Publicado (F-front) · Elements 4.3.0.163 |
| `digitalisimo_elements_icon_scan` | Estado, cursor y hasta 50 hallazgos (fuente, id y extracto ≤ 120 caracteres) del análisis de iconos. **Sin carga automática** | — (automática, sin pantalla) | Sólo la escribe el análisis; los extractos se muestran con `esc_html()` | Publicado (F-front) · Elements 4.3.0.163 |
| `digitalisimo_elements_swatches` | Tipo, forma y tamaño de muestra por atributo (`pa_*`) de ese sitio; se escribe desde la pantalla de atributos de WooCommerce. **Existe siempre** con WooCommerce (se crea en `admin_init`) y el front sólo la lee si está en `wp_load_alloptions()` | — (dato del sitio, sin pantalla propia; en una red cada sitio tiene la suya) | Lista cerrada de tipos (`select`, `visual`, `label`), formas (`circle`, `rounded`, `pill`) y tamaños; por `manage_product_terms` y con el nonce de la pantalla de WooCommerce; viaja en el kit dentro de `woo-attributes` | Publicado (F4-Woo) · Elements @NEXT_ELEMENTS@ |
| `digitalisimo_elements_woo_fragments` | Bandera 0/1 con carga automática: algún documento publicado del constructor usa la barra de envío o el cupón. Con 0, `woocommerce_add_to_cart_fragments` no se engancha y la respuesta de fragmentos no cambia | — (automática, sin pantalla) | Sólo la recalculan las transiciones de estado de documentos del constructor y la importación de kits | Publicado (F4-Woo) · Elements @NEXT_ELEMENTS@ |
| Opción de usuario `digitalisimo_wishlist` (con el prefijo del sitio) y metas de término `digitalisimo_swatch_{type,shape,size,color,image,label}` | Lista de deseos de un miembro de ese sitio (hasta 100 productos) y muestras de cada término de ese sitio | — (datos del sitio) | IDs enteros de productos del sitio; color `#rrggbb`/`#rrggbbaa`, imagen = adjunto del sitio, etiqueta ≤ 24 caracteres. Exportador y borrador de datos personales para la lista | Publicado (F4-Woo) · Elements @NEXT_ELEMENTS@ |
| `digitalisimo_elements_front_lock` | Cerrojo de la transición: `INSERT IGNORE` con token; se roba pasados 300 s | — (automático) | Sin entrada del usuario | Publicado (F-front) · Elements 4.3.0.163 |
| `digitalisimo_elements_front_snippets` | Booleano con carga automática: algún fragmento publicado de Código personalizado nombra los globales de PRO. Se recalcula al guardar, borrar o cambiar de estado un fragmento y en la transición | — (automática, sin pantalla) | Sin entrada del usuario | Publicado (F-front) · Elements 4.3.0.163 |
| Evento `digitalisimo_elements_front_transition` | Evento único por sitio cuando el valor efectivo difiere del aplicado; en `admin_init` se ejecuta si sigue pendiente (redes con `DISABLE_WP_CRON`) | — | — | Publicado (F-front) · Elements 4.3.0.163 |
| `digitalisimo_elements_presets` | Presets de estilo del sitio: `{ v: 1, presets: { slug: { type, label, settings, hash, updated, inert } } }`. **Sin carga automática**: sólo se lee al generar el CSS de un documento con elementos vinculados, en el editor y en las acciones. Hasta 200 presets, 16 KB por preset y 128 KB en total. Sin presets, no existe | — (cada sitio tiene los suyos; viajan en los kits con la parte `presets`) | `Store::sanitize_settings()` (lista blanca por tipo de control, cierre por dependencias y comprobación final) dentro de `set_use_style_controls( true )`; escrita bajo el cerrojo `digitalisimo_elements_lock_presets`, con `manage_options` sin la restricción `design`, nonce del editor y capacidad comprobada en el servidor | Publicado (F7a) · Elements 4.3.0.164 |
| `page_visits_no_consent` en `digitalisimo_elements_front` (sitio) y `digitalisimo_elements_front_defaults` (red) | «Registrar visitas sin gestor de consentimiento», en «Rendimiento» → «Privacidad», con «Heredar de la red» sólo en un sitio de una red. Por defecto apagada. Sin la WP Consent API, es lo único que permite escribir la cookie de «Visitó la página» | El valor que heredan los sitios, en Red → «Rendimiento» → «Privacidad», sin «Heredar de la red» | Booleano en las dos superficies: los cuatro puntos de paridad (`Settings::defaults()`, `sanitize()`, `network_privacy_fields()` y `save_network()`). Fuera de `KEYS`: no entra en la huella de la transición ni vacía cachés | Publicado (F7a) · Elements 4.3.0.164 |
| `digitalisimo_elements_tracked_pages` | Índice de «Visitó la página»: `{ docs: { id: [ids] }, union: [ids] }`, con la unión (≤ 200) calculada al escribir. Carga automática; **existe siempre** (`[]`, creada en `admin_init`) y el front sólo la lee si está en `wp_load_alloptions()`. Cuentan sólo documentos `publish` (plantillas con condiciones), nunca revisiones, borradores ni marcadores. No hay meta por documento: nada viaja en el WXR | — (automática, sin pantalla) | Enteros de entradas publicadas; se recalcula en `elementor/document/after_save`, `transition_post_status`, `deleted_post` y `digitalisimo_elements_kit_imported`, y purga lo que entra y lo que sale | Publicado (F7a) · Elements 4.3.0.164 |
| `digitalisimo_elements_tb_priority` | Índice de las plantillas con prioridad manual. Carga automática; **existe siempre** (`[]`, creada en `admin_init`) y sólo se lee si está en `wp_load_alloptions()`: 0 consultas sin ella | — (automática, sin pantalla) | Enteros de 1 a 99; se reindexa al guardar, al mover a la papelera, al restaurar, al borrar y al cambiar `_elementor_page_settings` de una plantilla | Publicado (F7a) · Elements 4.3.0.164 |
| `digitalisimo_elements_lock_*` | Cerrojos del cerrojo compartido `Digitalisimo\Elements\Lock` (hoy `digitalisimo_elements_lock_presets`, que se roba a los 30 s): `INSERT IGNORE` con token, sin carga automática. Los cerrojos que ya existían conservan su opción (`digitalisimo_theme_convert_lock`, 300 s) | — (automáticos) | Sin entrada del usuario | Publicado (F7a) · Elements 4.3.0.164 |
| Claves de `_elementor_page_settings`: `digitalisimo_tb_priority` (plantillas del Theme Builder), `digitalisimo_hide_header`, `digitalisimo_hide_footer` y `digitalisimo_hide_featured_image` (páginas y entradas) | Ajustes de cada documento, en el panel del editor; viajan con el documento (la prioridad, también como campo de kit) | — | Los del núcleo para los ajustes de documento; la prioridad, entero de 1 a 99 o vacío | Publicado (F7a) · Elements 4.3.0.164 |
| Cookie `digi_seen` (`digi_seen_<blog_id>` en una red) | Páginas que abrió el visitante (≤ 50 IDs en base 36, 30 días, ruta del sitio), escrita sólo por el script de las páginas rastreadas y con consentimiento. Declarada con `wp_add_privacy_policy_content()` y, con la WP Consent API, con `wp_add_cookie_info()` en `preferences` | — | El servidor no la escribe; al leerla sólo acepta IDs en base 36 | Publicado (F7a) · Elements 4.3.0.164 |
| Copias `uploads/digitalisimo-elements/masks/<sha8>.svg` | El SVG de Medios elegido para una máscara o un patrón, saneado y copiado al guardar; la ruta relativa va en `digitalisimo_mask_file` o `digitalisimo_pattern_file` | — | Saneador SVG del núcleo, lista de rechazo y tope de 8 KB; sólo adjuntos SVG del sitio actual; ruta comprobada con `realpath` | Publicado (F7a) · Elements 4.3.0.164 |

Cada opción nueva se añade aquí antes de darse por terminada.

## Matriz de validación

Origen → versión → formato → capacidades probadas → resultado → limitaciones. «Pendiente» significa sin conversión ni prueba todavía.

Desde el 2026-10-20 las filas de Divi, Betheme, GeneratePress, GenerateBlocks y Bricks quedan como registro de la revisión: no se importan ni convierten (F3, F4, F5 y F6 eliminados), y su «Pendiente» ya no tiene etapa. Los kits de Elementor tienen sus propias filas (K).

| Origen | Versión | Formato | Capacidades probadas | Resultado | Limitaciones |
|---|---|---|---|---|---|
| Element Pack Pro | 9.9.1 | `_elementor_data` con `bdt-*` y 12 extensiones | 201 IDs con adaptador, conversión y revisiones; A/B Multisite con el núcleo incluido | **Validado** (562 combinaciones iguales en las fases A–E) | 57 IDs descartados con motivo; 28 extensiones se pierden sin aviso (F0.1, F8); Theme Builder, fuentes, iconos y Mega Menu sin convertir (F8) |
| Element Pack Pro + Addon | 10.0.1 | Ídem; 88 IDs ausentes en 10.0.1 (el changelog declara 85 movidos al Addon) | Extensiones sin equivalente listadas por documento; Addon y Prime Slider detectados (F0.1) | Pendiente (F8) | Clases de 6 mapas sin corregir; A/B no repetido con 10.0.1 |
| Prime Slider Pro | 4.7.0 | `_elementor_data` con `prime-slider-*` | — | Pendiente (F8) | Sin la versión Lite; efectos de UI Initiative y GSAP no redistribuibles |
| Divi | 5.15.0 | D5: bloques `divi/*` en `post_content` | — | Pendiente (F3) | Breakpoint de tableta 980; presets no guardados en el contenido |
| Divi | 5.15.0 | D4: shortcodes `et_pb_*` | — | Pendiente (F3) | Extensiones D4 de terceros en `divi/shortcode-module` |
| Divi | 5.15.0 | JSON `et_builder`, `et_builder_layouts`, `et_theme_builder` | — | Pendiente (F3) | Imágenes en base64 |
| Divi | 5.15.0 | Theme Builder (`et_template`, `_et_use_on`) | — | Pendiente (F3) | Archivo de autor por rol sin equivalente |
| Betheme | 29.0.3 | `mfn-page-items`, generaciones 1, 2 y 3 | — | Pendiente (F4) | Sólo formatos (clean-room); medianil y breakpoints propios |
| Betheme | 29.0.3 | CPT `template` + `mfn_template_conditions` | — | Pendiente (F4) | «Cabecera distinta al fijarse»: desde F7a la cubre «Al fijarse» del sticky (sin conversión) |
| Betheme | 29.0.3 | Opción `betheme`, `be_classes`, `be_css_variables` | — | Pendiente (F4) | Clases incrustadas en v3 |
| Betheme | 29.0.3 | Demo local (WXR, `options.txt`, `menu.txt`) | — | Pendiente (F4) | Sólo paquetes que el usuario ya tenga |
| Betheme + Elementor | 29.0.3 | `_elementor_data` con 57 widgets `mfn_*` | — | Pendiente (F4) | Sus cabeceras y pies están en BeBuilder |
| GP Premium | 2.5.6 | `gp_elements` y metas `_generate_*` | — | Pendiente (F5) | Hooks con PHP sólo como fragmentos desactivados |
| GP Premium | 2.5.6 | Site Library local (`options.json`, WXR, `.wie`) | — | Pendiente (F5) | Sin los servidores de GP |
| GeneratePress (tema) | 3.6.1 | `generate_settings`, metas por página | — | Pendiente (F5) | Copia oficial en `reference/` (GPLv2 o posterior) |
| GenerateBlocks, GB Pro, GenerateCloud | GenerateBlocks 2.5.0 | Bloques `generateblocks/*` | — | Pendiente (F5) | Copia oficial de GenerateBlocks en `reference/` (GPLv2 o posterior); GB Pro y GenerateCloud sólo por las muestras |
| Bricks | 2.x (esquema 2.4.3) | `_bricks_page_*_2`, `_bricks_template_*`, opciones `bricks_*` | — | Pendiente (F6) | Sin código; claves no documentadas exigen muestras |
| Bricks | 2.x | Exportación global (ZIP + `manifest.json`) | — | Pendiente (F6) | Claves no documentadas |
| Hello Elementor → tema propio | 3.5.1 | `theme_mods`, `hello_*`, cabecera y pie dinámicos | Paso T1–T10; paridad de hojas, marcado y 8 filtros; cabecera y pie dinámicos al Theme Builder; reversión e ida y vuelta; A/B S1–S5 en tres anchos con WooCommerce; red N1–N6 | **Publicado** (F1) · A/B Hello 3.5.1 frente al tema en verde (S1–S5, S3c, S3h y E con WOO=1 en tres anchos; corrida de aceptación completa del 2026-10-20 y rápida tras las correcciones) | `tb_partial`, Hello+, `hello-biz`/`hello-commerce` y el PHP del hijo, sin convertir; diferencias declaradas en F1. Copia oficial en `reference/` (GPLv3 o posterior) |
| Kit propio (DIGITALÍSIMO Elements) | Kit 3.0 con la parte `digitalisimo` v1 | ZIP de kit | R1 ida y vuelta en «nuevo» (pantalla «Kits» y `--mode=fresh`, REST = WP-CLI), A2 sin el origen, R4 y R4v4 sólo agregar con choque, R5 reimportación, R6 red, R8 reversión, R9 entradas hostiles | **Publicado** (K) · Elements 4.3.0.162 | Componentes v4 conservados como marcador, sin viajar ni pintarse (límite del núcleo); sólo se revierte la última importación. El importador ya no cambia tema, experimentos ni plugins |
| Kits de Elementor y Elementor Pro (terceros) | 3.0, 2.0 y 1.x (Pro 3.x) | ZIP de kit | R3 (2.0 con tema, experimentos y plugins pedidos, colores que chocan, Theme Builder, popup, loop, formulario con webhook, contenedor desconocido, `e-component`, fuente, iconos con URL externa y WXR hostil), R3b (1.x) y R7 sin proveedor, en «agregar» | **Publicado** (K) · Elements 4.3.0.162 | IDs de elemento regenerados por su exportador; Google Fonts sólo listadas; tema, experimentos y plugins pedidos sólo informados |
| Template Kit de Envato | Formato de Envato (`manifest_version`) | ZIP de marketplace | R2 en «agregar» y «Aplicar los ajustes del kit» | **Publicado, sintético** (K-D17) · Elements 4.3.0.162 | Sin kits reales: lecturas «a confirmar» en `docs/fusion/k-envato.md` |
| Plantilla suelta | JSON de plantilla o ZIP de plantillas | Biblioteca → Importar | Marcador para los tipos desconocidos; un ZIP de kit subido ahí va al importador de kits (R10) | **Publicado** (K) · Elements 4.3.0.162 | La biblioteca dentro del editor no se intercepta |
| Capacidades de Divi y Betheme en Elements (clean-room) | Elements 4.3.0.164 | Ajustes de elemento y de documento, opción de presets del sitio y parte `presets` del kit | Presets, degradados, máscaras, patrones, siete condiciones, prioridad de las plantillas, sticky «al fijarse» y controles de documento: 14 unitarias nuevas con el núcleo y las clases de PRO reales donde se pueden cargar; `design.sh` DS1–DS13 y las ampliaciones de `front-budget.sh`, `conditions.sh`, `kit-roundtrip.sh` y `brand-audit.sh`: en verde | **Publicado** (F7a) · Elements 4.3.0.164 | Nada se importa de los orígenes. Las condiciones de F7a no se ofrecen en v4 y el prop v4 `display-conditions` no se traduce en los kits; hueco de nombres de K con `masks/<sha8>.svg` (ver F7a, «Límites declarados») |
| Capacidades de WooCommerce en Elements (clean-room) | Elements @NEXT_ELEMENTS@ | Widgets `digitalisimo-woo-*`, partes de Productos y rasgo del Menú del carrito; opción de muestras del sitio, metas de término y lista de deseos del usuario; partes `woo-attributes` y `woo-variations` del kit | Lista de deseos, vista rápida, muestras, barra de envío gratis, cupón, filtros de precio y de atributo | `woo-flow.sh` W0–W13 (también `NET=1` y `CORE=plugin`), `front-budget.sh WOO=1` en cuatro modos, `kit-roundtrip.sh WOO=1`, `brand-audit.sh WOO=1` | Sólo WooCommerce 11.2.1 probado; la lista de deseos y `wc-visual` de WooCommerce son experimentales; un Loop Grid con «Consulta actual» no se filtra con el destino «Tienda» (ver F4-Woo, «Límites declarados») |

### Recorridos

- **A · Sitio nuevo:** sin ningún hook de conversión; tema propio y Elements solos.
- **B · Plantilla importada:** un archivo de cada formato importado, editado, guardado, reabierto y publicado.
- **C · Sitio migrado:** Migración desactivada y origen retirado.

Divi y GP se aceptan con A/B en Playground (`--workers=1`) cuando su copia tiene la procedencia oficial anotada en `reference/OFFICIAL-SOURCES.txt` (regla de `AGENTS.md`); sin ella, como Betheme y Bricks, que nunca se ejecutan, se aceptan contra capturas y HTML de los sitios reales en tres anchos. Lo mismo vale para Element Pack en `legacy-ab.sh` y `migrate.sh`.

### Casos explícitos

| Caso | Estado |
|---|---|
| Documentos anteriores con render idéntico (`legacy-ab.sh`) | Pendiente |
| Relaciones globales y sobrescrituras | Pendiente |
| Componentes repetidos con IDs únicos | Pendiente |
| Consultas anidadas | Pendiente |
| Dos filtros independientes en una página | Publicado · Elements @NEXT_ELEMENTS@ (F4-Woo: W9.3b, dos filtros F4-Woo y el de PRO en el mismo bucle; `woo-filters` en el presupuesto) |
| Envío real de formularios | Pendiente |
| Enlaces y menús | Pendiente |
| Sliders | Pendiente |
| Flujo Woo en modo de prueba | Publicado · Elements @NEXT_ELEMENTS@ (F4-Woo: W11 con el pago clásico y el de bloques, núcleo incluido y plugin Elementor) |
| Permisos por rol | Pendiente |
| Corte y reanudación | Pendiente |
| Reversión | Pendiente |
| CSS y JS por handle | Pendiente |
| Precedencia frente a CSS sin capa, en línea, `!important` y `@layer bricks` | Parcial (F7a, presets): un preset no tiene selector ni capa propios, porque se aplana en los selectores del elemento; frente a otro CSS se comporta como el estilo local. `@layer bricks`, pendiente |
| Sitio con v4 apagado | Pendiente |
| Sitio con el experimento `container` inactivo | Pendiente |
| Sin rastro de los plugins de origen (`brand-audit.sh`) | Publicado (F0.2); F0.2b cierra lo visible restante |
| Kits: ida y vuelta, sólo agregar, reimportación, red, reversión, permisos y entradas hostiles (`kit-roundtrip.sh`, R1 a R10) | Publicado (K) · Elements 4.3.0.162 |

## Riesgos

- **Breakpoints distintos** (Divi 980; Betheme 959/1440; Bricks 991/767/478; GP 768). El kit sólo se cambia en sitios cuyo contenido viene íntegro del origen; en los demás se declara la diferencia.
- **Contenido y CSS que dependen del origen.** Shortcodes y CSS de usuario contra clases del origen conservan clases de compatibilidad y se informan.
- **El tema de origen pinta el contenido.** Divi, Betheme y Bricks son temas: cambiar de tema antes de convertir deja shortcodes crudos o páginas vacías. Por eso se convierte a borrador y el tema cambia al final.
- **Plantillas publicadas antes de tiempo.** Una plantilla del Theme Builder publicada reemplaza la cabecera de cualquier tema: las convertidas no se publican antes del cambio de tema.
- **Importar un kit** con el importador del núcleo creaba un kit nuevo, podía cambiar el tema, los experimentos y los plugins, y borraba las instancias de componentes v4. **Mitigado en parte en K:** el kit nuevo, el tema, los experimentos y los plugins quedan cerrados (se importa siempre en el kit activo, el servidor fuerza `theme` y `experiments` a `false`, los plugins sólo se informan y los flujos 2.0 se cortan con el núcleo incluido). Las instancias de componentes v4 se conservan como marcador, pero no viajan ni se pintan: es un límite del núcleo (`IS_IMPORT_EXPORT_SUPPORTED`), y en esa parte el riesgo sigue abierto.
- **Interacciones PRO.** Al habilitarlas, el contenido v4 con `hover`, `click` o `scrollOn` guardados empezaría a animarse: se inventaría antes.
- **Actualizaciones automáticas.** Lo publicado llega solo a todos los sitios: por eso todo cambio de comportamiento es opt-in por instancia y el runner guarda `state['v']`.
- **Núcleo.** Cada `sync-elementor-core.sh` puede cambiar formatos atómicos: se repiten las pruebas.
- **Multisite.**
  - Un fragmento PHP de red corre en todos los sitios (Tools es de red) salvo donde esté pausado o detenido; uno de sitio, sólo en ese sitio.
  - `allowedthemes` sólo podría cambiarlo «Migrar toda la red», como excepción declarada en `tests/theme-structure.php`; hoy ningún paquete lo escribe.
  - Las copias del tema que no están cargadas (inactivas, en otra carpeta o en otro sitio de la red) las actualiza DIGITALÍSIMO Tools (D1). Sin Tools en la red, sólo se actualiza la copia activa en el sitio principal. Tools nunca activa el tema ni lo habilita en la red.
- **Repositorio público.** Nada de código de Betheme, de Bricks ni de bundles de Divi; ninguna muestra real ni dato de clientes.

## Supuestos

- El código PHP propio vive en Tools, por red o por sitio; sin Tools activo no se ejecuta ninguno.
- Las muestras reales quedan fuera de Git; al repositorio sólo llegan fixtures anonimizados.
- Las páginas de GP hechas sólo con bloques del núcleo siguen en el editor de bloques.
- El slug `digitalisimo-theme` es definitivo.

## Lo que no se hará

- No se editará `digitalisimo-elements/elementor/` ni los bundles compilados de PRO.
- No se copiará código de Betheme, de Bricks ni de los bundles de Divi.
- No se ejecutarán instaladores, demos ni scripts de las referencias, ni se instalará una copia sin procedencia oficial anotada (y nunca fuera de un Playground efímero de pruebas).
- No habrá biblioteca remota para pintar páginas.
- No se activarán el tema ni v4 en producción sin autorización.
- No se tocarán pedidos, clientes, inventario ni envíos de formularios.
- No se importarán ni convertirán temas, plantillas, kits ni opciones de Divi, Betheme, Bricks ni GeneratePress (2026-10-20).
- No habrá otro formato ni otro sistema de kits. Importar un kit no instalará, activará ni desactivará plugins o temas, no cambiará experimentos, no creará kits y no actualizará en el sitio lo ya importado.
- No se importarán kits por URL, desde la Kit Library ni desde la nube, ni se aceptará `process-media`.
- No se codificará la carga de un marcador para esquivar kses.

## Evidencia

`docs/fusion/README.md` indexa los informes depurados: un informe por origen (Divi, Betheme, GP, Bricks, Element Pack y Prime Slider), los de Elements, Migración y el tema, las verificaciones del plan y los cuatro inventarios de F0.2. Los de K son `docs/fusion/k-kits.md` (diseño, ganchos y hechos del núcleo), `docs/fusion/k-envato.md` (Template Kits de Envato) y `docs/fusion/k-kits-evidence.json` (resumen de `kit-roundtrip.sh`). Los de F7a son `docs/fusion/f7a.md` (hechos del núcleo, ganchos, formatos, centinela, consentimiento y desviaciones) y `docs/fusion/f7a-evidence.json` (resumen de `design.sh` y de las ampliaciones).

## Registro de avance

- 2026-10-16 · Revisión de los orígenes (9 informes y 3 críticas) y plan aprobado.
- 2026-10-16 · Decidido: sin tema hijo; editor de fragmentos PHP en DIGITALÍSIMO Tools (F1b); 8 ZIP por Release; hooks PHP de GeneratePress importados desactivados.
- 2026-10-16 · Decidido: cada etapa se publica en cuanto queda validada («siempre publica»).
- 2026-10-16 · F0.2 hecho en la rama `fusion/f0-2-sin-referencias` (commit a6218fa): escudo en el servidor, interfaz sin servicios del proveedor, textos propios en los siete plugins. `elements-vendor-guard.php`, `elements-vendor-static.mjs` y `elements-identity.php` en verde; `brand-audit.sh` ampliado. Pendiente con aprobación: vídeos de muestra en páginas publicadas.
- 2026-10-16 · F0 y F0.1 en curso en la rama `fusion/f0-base`.
- 2026-10-17 · F0.2 publicado en `v2026.10.17.408` (Elements 4.3.0.157, SEO 1.0.228, IA 1.0.58, Hosting 1.0.29, Backups 1.0.57, Tools 1.0.33, Migración 1.0.19), con Herramientas sin «Control de versiones» ni «Sincronizar biblioteca». La auditoría ampliada no encontró peticiones, enlaces ni vídeos del proveedor; F0.2b retira lo visible restante (asistente de configuración, paleta de importar/exportar personalizado, texto de Elementor One) y las URL latentes.
- 2026-10-17 · F1b en la rama `fusion/f1b-codigo-php`: pestaña «Código PHP» de Tools (sitio y red) con revisión, prueba desde el navegador, firma, guardián, conciliación, rescate, modo reducido e importación desactivada. `tools-php-snippets.php` y `tools-php-guard.php` en verde en PHP 7.4, 8.2, 8.4 y 8.5; `tools-php.sh` en Playground (sitio individual y red).
- 2026-10-17 · F0: esta guía, la evidencia depurada en `docs/fusion/` (14 informes y un índice), las referencias de la fusión en `docs/third-party-licenses.md` y las reglas en `AGENTS.md`.
- 2026-10-17 · Revisión adversarial de F0 y F0.1: lo que no tiene equivalente pasa a ser información (un sitio ya no queda «con asuntos por revisar» para siempre), se lee de la copia original al volver a migrar y no cuenta dos veces lo que no cupo; la guarda del empaquetado cubre Prime Slider; reglas de `AGENTS.md` acotadas (rastro de proveedores, uso de `reference/`, activación del tema, acciones con destino).
- 2026-10-19 · F1 en la rama `fusion/f1-tema`:
  - tema `digitalisimo-theme` 1.0.0, clásico, sin hijo y con actualizador propio;
  - Release de 8 ZIP, con `themes` en el índice;
  - en Tools, cobertura de las copias no cargadas (D1) y sección «Tema»; el `style.css` de un hijo de Hello sólo se informa (D2);
  - SEO acepta el tema propio en rendimiento, retira la meta description de Hello y reconoce las hojas del núcleo incluido (cierra el pendiente de F0.2);
  - Elements gana compatibilidad con el asistente, el bloqueo de Hello y la conversión de la cabecera y el pie dinámicos.

  Publicado con SEO 1.0.231, Tools 1.0.36 y Elements 4.3.0.161. La medición en staging queda pendiente de autorización y el tema no se activó en ningún sitio real.

- 2026-10-20 · Cambio de alcance: no se importan ni convierten Divi, Betheme, Bricks ni GeneratePress; sus beneficios se integran en Elements y los kits de Elementor funcionan con todas las mejoras. F2, F3, F4 (la conversión), F5 y F6 eliminados; nuevos K y F4-Woo. `AGENTS.md` reescrito en consecuencia.
- 2026-10-20 · K en la rama `fusion/k-kits`, con su contrato (registro de partes y de referencias, contextos y `Part_Context`, prueba en `tests/elements-kit-parts.php` §1):
  - importador 3.0 en «sólo agregar» por defecto, siempre en el kit activo y sin cambiar tema, experimentos ni plugins; flujos 2.0 cortados con el núcleo incluido;
  - datos propios que se perdían (valores por defecto, condiciones completas del Theme Builder, separadores y máscaras, medios incrustados, metas de término, IDs de elemento), remapeo cerrado de IDs y URL, procedencia y reimportación sin duplicar;
  - kits de terceros (Elementor y Pro 1.x, 2.x y 3.0, Envato sintético) y marcador `digitalisimo-unconverted`;
  - pantalla «Kits», informe y `wp digitalisimo-elements kit`.

  Publicado con Elements 4.3.0.162 · `v2026.10.21.416`. Evidencia en `docs/fusion/k-kits-evidence.json`; Envato real pendiente (K-D17).
- 2026-10-21 · F-front en la rama `fusion/f-front` (Fase 2 de `docs/ELEMENTS_FRONT_OBJECTIVE.md`):
  - puerta de PRO por clase, con `plugin.php` y los paquetes compilados intactos, y réplica del contador de popups;
  - valores por defecto del núcleo sin escribir sus ajustes ni sus experimentos: `swap`, iconos en línea con análisis y «Aplicar», «Desactivar» para la caché de elementos y la carga optimizada de imágenes;
  - Prism 1.23.0 y clipboard.js 2.0.0 desde el plugin, `jshint` de WordPress y cargador opt-in `lazy_libs`;
  - pantalla «Rendimiento» con seis interruptores, paridad sitio/red y transición por sitio;
  - SEO recalcula el blindaje de fuentes y muestra el `font-display` en vigor.

  Publicado con Elements 4.3.0.163 y SEO 1.0.232 · `v2026.10.21.417`. `empty`: 28 → 24 peticiones y −382 667 B de JS; Lighthouse −386 068 B. Medición en `docs/measurements/2026-10-21-elements-front-phase2.md`; `migrate.sh` y `legacy-ab.sh` pendientes de una copia oficial de Element Pack.
- 2026-10-21 · F7a en la rama `fusion/f7a`. La especificación v1 se revisó con dos críticas adversariales (reglas y verificación contra el código: 7 bloqueantes, 27 importantes y 35 menores) y su sección V sustituyó lo que chocaba:
  - **Presets:** definidos en el sitio y en el kit, aplicados en el CSS del documento que los usa. Es la decisión corregida del 2026-10-16, que los compilaba en el CSS del kit.
  - **Condiciones:** fallan cerradas, con el centinela y la lista vacía; en v4 no se ofrecen.
  - **Cookie de visitas** por sitio y con consentimiento.
  - **Sticky** sólo con CSS, sin pila de ancestros.
  - **Máscaras y patrones** codificados en porcentaje, con catálogo propio y las copias de los SVG del sitio que transporta K.
  - **Cabecera y pie** sólo con el tema DIGITALÍSIMO.
  - **Guardas** de símbolos por submódulo.

  Doce frentes de código (A–L) con 14 pruebas nuevas en los dos workflows, más la revisión estática (N). Las correcciones que trajo la integración están en `docs/fusion/f7a.md`: `Store::replace()` conserva los presets del sitio, `Visitor_Content` lee el prop v4 y el control del preset repinta la vista previa. Una sola línea en un derivado de PRO (`conditions-manager.php`) y una en K (`digitalisimo_elements_kit_used_globals_trees`). Publicado con Elements 4.3.0.164. Playground en verde, con la evidencia en `docs/fusion/f7a-evidence.json`: `design.sh` DS1–DS13 en sus ocho modos, `front-budget.sh` (igual a 4.3.0.163 caso a caso), `conditions.sh` (278), `kit-roundtrip.sh` R1–R10, `brand-audit.sh SCOPE=full` (220 pantallas, marca 0), `theme-switch.sh`, `shared-ui.sh`, `core-switch.sh`, `front-network.sh`, `form-submit.sh` e `interaction.sh` (el caso `editor` falla igual con 4.3.0.163: entorno). Límites: A13 de K (25,7 s, también con 4.3.0.163) y la configuración del editor, 127 KB mayor por los controles nuevos. Como en F-front, `migrate.sh` y `legacy-ab.sh` siguen pendientes de una copia oficial de Element Pack.
- 2026-10-29 · F4-Woo en la rama `fusion/f4-woo` (Elements 4.3.0.166; la etiqueta de la Release queda en `docs/PLUGIN_ROADMAP.md`). Siete widgets `digitalisimo-woo-*`, tres partes de Productos y el rasgo del Menú del carrito, con WooCommerce 11.2.1 fijado y verificado archivo por archivo (`docs/fusion/f4woo.md`). La validación corrió en siete frentes paralelos y dejó cuatro correcciones de producto antes de publicar: el coordinador de filtros no arrancaba en un navegador real (`hooks` del núcleo nace en `init()`; ahora se engancha en `elementor-pro/modules/init/before`), la parte `woo-variations` no exportaba ninguna variación (formato de `get_posts( 'fields' => 'id=>parent' )`), el mini carrito seguía sin barra tras dar la dirección (el *hash* del carrito no cambiaba y `cart-fragments.js` reutilizaba los fragmentos guardados; ahora `woocommerce_cart_hash` sigue al destino) y el botón de la lista de deseos movía la página al mostrarse (CLS 0,018 y 0,029; ahora reserva su hueco). Además, la configuración de variaciones de la vista rápida bajó de 1 034 B a 921 B y el módulo derivado de WooCommerce de PRO ya no termina en un 500 cuando WooCommerce copia sus páginas al kit sin un usuario con permiso. `woo-flow.sh` W0–W13 en verde (W1, W2, W6 y W12 también `NET=1`; W4, W5, W9, W10 y W11 también `CORE=plugin`), `front-budget.sh WOO=1` sin regresión en lo existente en los cuatro modos, `kit-roundtrip.sh WOO=1` en verde en lo propio de F4-Woo, `brand-audit.sh WOO=1` nuevo. Hallazgos de K anotados para un incremento propio (exportación por defecto sin las taxonomías de los tipos propios, páginas de WooCommerce sin traducir en «nuevo», `site_is_empty()` con las páginas de WooCommerce, controles de `wc-products` sin declarar). Excepciones declaradas: los dos scripts de los filtros (13 834 B por página) y los +168 B de la respuesta de fragmentos en los sitios que usan la barra o el cupón. Siguiente: F7b.
