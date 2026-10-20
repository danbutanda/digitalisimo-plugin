# Objetivo: fusión de constructores en DIGITALÍSIMO Elements

Este documento es el punto de entrada para cualquier agente que continúe la fusión. El objetivo es que **DIGITALÍSIMO Elements sea el único constructor** de los sitios: con las capacidades útiles de Divi, Betheme/BeBuilder, GeneratePress (GP Premium), Bricks (sólo por su documentación pública), Element Pack Pro y Prime Slider; capaz de importar plantillas y kits de esos formatos; con DIGITALÍSIMO Migración convirtiendo sitios completos; con un tema propio mínimo, y con el código PHP propio de cada sitio en un editor de fragmentos de DIGITALÍSIMO Tools.

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
| Presets vinculados | Sólo estilo, compilados en el CSS del kit | 2026-10-16 |
| Widgets anidados | Un widget **nuevo** (slider) o los anidados del núcleo; nunca se cambia la clase ni el ID de un widget existente | 2026-10-16 |
| Interacciones con destino | Registro propio de acciones (`_digitalisimo_actions`), con lista blanca y sin JS arbitrario | 2026-10-16 |
| Rastro de los plugins de origen | **Ninguno**: ni enlaces, ni vídeos, ni cuentas, ni servicios, ni peticiones (F0.2) | 2026-10-16 |
| Slug del tema | `digitalisimo-theme`, definitivo. `digitalisimo-theme-child` no se distribuye | 2026-10-16 |
| Páginas de GP hechas con bloques del núcleo | Siguen en el editor de bloques | 2026-10-16 |
| Copias del tema que no están cargadas (D1) | Las cubre **DIGITALÍSIMO Tools** (`Theme_Updates`): inactivas, en otra carpeta o en otros sitios de la red. También es el respaldo de la copia cargada. No va en Elements, porque ningún módulo puede exigir otro | 2026-10-17 |
| `style.css` de un hijo de Hello (D2) | **Sólo se informa:** en Tools antes del cambio, y en la bitácora y en `wp digitalisimo-theme status` después. `wp digitalisimo-theme import --child-css` lo copia al CSS adicional sólo si se pide, porque pegarlo cambia su lugar en la cascada | 2026-10-17 |

## Arquitectura

1. **Conversores en Elements, orquestación en Migración.** El módulo `digitalisimo-elements/modules/digitalisimo-import/` declara descriptores y carga los lectores por autoload: cero carga en sitios que no importan.
   - Contiene la interfaz `Source` pura (`sniff`, `parse`, `convert` → `Result`), el árbol neutro, el emisor a contenedores y widgets, el validador común y los escritores de kit con los repositorios del núcleo.
   - El **intérprete genérico de mapas** se traslada aquí desde el Translator de Migración.
   - Fuentes: Divi (D5 con `parse_blocks`, D4 con tokenizador propio), Betheme, Bricks y GP.
   - Element Pack y Prime Slider siguen en Migración: la guarda de `scripts/build-packages.sh` aborta si el ZIP de Elements los menciona. Sus mapas se pasan por filtro.
2. **Importador sin otro sistema de kits.** Una sola acción «Importar» que detecta el formato.
   - Plantillas sueltas: un punto de entrada propio llama a `import_template(['source'=>'digitalisimo-import'])`; ese `Source` delega en `get_source('local')->save_item()`.
   - Kits ajenos, demos locales de Betheme y paquetes locales de la Site Library de GP: se convierten a un kit 3.0 sin `theme`, `experiments` ni `plugins` y se importan con el filtro `site-settings/customization` en modo «sólo agregar». Sólo un sitio sin contenido previo del constructor reemplaza los ajustes del sistema.
   - `_digitalisimo_presets` y `_elementor_elements_default_values` se preservan al importar.
   - Se ejecuta en AJAX autenticado o CLI. Si se instancia `Import` directamente, se dispara a mano la acción que registra los runners de PRO.
   - Los componentes v4 se crean con `Components_Repository` después de importar; sus instancias se reinsertan con el mapa de IDs.
3. **Destino clásico.** Contenedores flex o grid y widgets nativos y propios. v4 sólo en sitios que ya lo tienen.
4. **El kit es el sistema de diseño.** Colores y tipografías del origen van al kit con `_id` prefijado; variables v4 donde exista v4.
   - **Presets de estilo:** se guardan en la meta del kit y compilan su CSS en el del kit (`elementor/css-file/post/parse`, `add_controls_stack_style_rules()` sobre una instancia de muestra).
   - **Vínculo:** la instancia lleva una clase por `prefix_class`, con selector `.elementor-kit-N .elementor-widget-{tipo}.digi-preset-{slug}`. Su especificidad (0,2,0) siempre pierde ante lo local (0,3,0).
   - **Edición:** se reutilizan la extracción, el saneador y el import/export de kit-elements-defaults. Con «Guardar como predeterminado» se define qué gana. En v4, un preset es una clase global.
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
   - `loop-filter` suma búsqueda, meta o rango, precio, atributo, orden y por página, en handlers propios con `defer` (los bundles compilados no se tocan). Incluye `popstate`, debounce, descarte de respuestas viejas, foco y destino inequívoco por instancia.
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
    - Lo no convertible se marca con un widget oculto `digitalisimo-unconverted`: no pinta nada en el frontal, avisa en el editor y conserva la carga original. Mientras quede alguno bloqueante, no se activa el resultado ni se retira el origen.
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
- **Procedencia y registro.** `_digitalisimo_import_origin` (origen, versión, id, hash) en todo lo creado. Original y registro de conversión en base de datos privada (autoload off), visibles sólo para administradores. Reimportar no duplica: se omite, se actualiza o se marca conflicto.
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
| F1 | Tema y publicación | **Publicado** · tema 1.0.0, SEO 1.0.231, Tools 1.0.36 y Elements 4.3.0.161 |
| F1b | Editor de fragmentos PHP en Tools | **Publicado** · Tools 1.0.34 · `v2026.10.20.411` |
| F2 | Base común de conversión y Migración v2 | Pendiente |
| F-front | Fase 2 de `ELEMENTS_FRONT_OBJECTIVE.md` | Pendiente |
| F7a | Capacidades que Divi y Betheme necesitan | Pendiente |
| F3 | Divi (D5 y D4) | Pendiente |
| F4 | Betheme / BeBuilder + WooCommerce visual | Pendiente |
| F5 | GeneratePress | Pendiente |
| F7b | Capacidades que Bricks necesita | Pendiente |
| F6 | Bricks (con las exportaciones del usuario) | Pendiente |
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

### F2 · Base común de conversión

- El módulo `digitalisimo-import` (arquitectura, puntos 1 y 2) y Migración v2 (punto 10), con la fachada de Element Pack sin cambio de comportamiento.
- Flujo de tema: borradores + `awaiting_theme` en producción. Al activar el tema propio, la tarea se reanuda: activa por hash, publica las plantillas del Theme Builder y retira el origen. Las plantillas convertidas nunca se publican antes del cambio de tema, porque reemplazarían la cabecera de cualquier tema.
- **Pruebas:** drivers falsos (orden y fases, compuerta de Element Pack, `state['v']`, reversión y reglas de «no pisar», mapa de IDs idempotente, reanudación sin duplicar), `migrate.sh` en verde y regresión con un kit real de Envato.

### F-front · Fase 2 del front

- Handlers PRO por elemento (`docs/ELEMENTS_FRONT_OBJECTIVE.md`, Fase 2), para validar cada conversión una sola vez.

### F7a · Capacidades que Divi y Betheme necesitan

- Presets de estilo en el kit.
- Degradados de N paradas, máscaras y patrones en el runtime de Elements (clean-room, sin recursos de origen).
- Condiciones nuevas (cookie, visita de página, campo, autor, stock, búsqueda), clasificadas para la caché.
- Prioridad manual en el Theme Builder.
- Sticky con «mostrar u ocultar al fijarse».
- Controles de documento para ocultar cabecera, pie e imagen destacada.

### F3 · Divi (D5 y D4)

- Lectores D5 y D4 con tablas generadas desde `conversion-outline.json` y presets aplanados o vinculados. Unos 60 módulos automáticos; los parciales se declaran.
- Customizer y `gcid-*` al kit; `et_template` al Theme Builder con su tabla de condiciones; `et_pb_layout` y globales a plantillas vinculadas.
- Formularios: campos, validación y acciones hacia `modules/forms`. Las credenciales nunca se copian.
- Importación JSON (`et_builder`, `et_builder_layouts`, `et_theme_builder`) con imágenes base64.
- CPT `project`; menús de Divi a `menu-1`/`menu-2` o Nav Menu por ID; iconos por `iconList.json` y un marcador sin equivalente.
- **Aceptación:** un layout real importado, editado, guardado, reabierto y publicado sin Divi; un sitio migrado con Migración desactivada.

### F4 · Betheme / BeBuilder + WooCommerce visual

- Tres generaciones de `mfn-page-items`, `old_id → css_*`, `be_classes`, globales, medianil y breakpoints.
- CPT `template` (cabecera con sticky y móvil, pie, single, archivo, producto, tienda, popup, megamenú) al Theme Builder; opción `betheme` al kit; metas SEO al importador de SEO; los 57 widgets `mfn_*`; demos locales.
- Widgets Woo nuevos, clean-room: wishlist (con exportación y borrado de datos personales), quick view, swatches con term meta propia, barra de envío gratis y cupón en el mini carrito pintados por fragmentos, filtros de precio y atributo. Se excluye la «venta falsa». La matriz indica si cada función la da WooCommerce, el constructor o una extensión.
- **Aceptación:** páginas comerciales y plantillas de producto representativas sin Betheme, con flujo Woo en modo de prueba.

### F5 · GeneratePress

- `gp_elements` sin PHP a Código personalizado o a plantillas Section en ubicaciones `digitalisimo_*`; cabecera, pie y hero al Theme Builder; condiciones y roles con las reglas existentes; layout por página a los ajustes del documento.
- Hooks con PHP al editor de fragmentos de Tools, traducidos de `generate_*` a `digitalisimo_*`, desactivados y con su origen. Migración nunca activa PHP.
- GenerateBlocks se convierte si las muestras y su copia oficial lo permiten. Los bloques del núcleo se quedan en el editor de bloques.

### F7b · Capacidades que Bricks necesita

- Registro de acciones con destino; interacciones PRO habilitadas tras el inventario; consultas guardadas y contextos; filtros ampliados.
- Sobrescrituras de contenido en Template; vista de usos y bloqueo de borrado.
- Slider anidado nuevo; presentación DIGITALÍSIMO sobre nested-tabs y nested-accordion.
- Edición múltiple y «aplicar a todos los de este tipo».
- Etiquetas dinámicas dentro de texto, con escape por contexto y lista blanca de campos de usuario.
- Lógica condicional de formularios.

### F6 · Bricks (con las exportaciones del usuario)

- Árbol plano, clases (incrustadas en v3 o globales en v4), variables, paleta, theme styles, componentes (los slots pierden el vínculo y se declara), plantillas y condiciones, interacciones, datos dinámicos, query loop y filtros.
- Mapa de IDs de 6 a 7 caracteres.
- Primero se cierran con las muestras las claves no documentadas (`docs/fusion/bricks.md`, §6.4).

### F8 · Element Pack 10.x y Prime Slider en Migración

- Element Pack: el Addon entra en `Retire`; se corrigen las clases de 6 mapas; las extensiones convertibles pasan a ser nativas en Elements (tooltip como rasgo, sticky, transform, parallax aproximado) y las que se descarten necesitan aprobación del propietario; se convierten su Theme Builder, fuentes e iconos.
- Prime Slider: se detecta y se convierte, por defecto al Slider Optimizado ampliado. Swiper sólo con necesidad demostrada en la matriz. Sin la versión Lite, sus valores por defecto quedan pendientes.

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

Cada opción nueva se añade aquí antes de darse por terminada.

## Matriz de validación

Origen → versión → formato → capacidades probadas → resultado → limitaciones. «Pendiente» significa sin conversión ni prueba todavía.

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
| Betheme | 29.0.3 | CPT `template` + `mfn_template_conditions` | — | Pendiente (F4) | «Cabecera distinta al fijarse» sin equivalente 1:1 hasta F7a |
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
| Kits de Elementor (Envato y propios) | 3.0 | ZIP de kit | — | Regresión pendiente (F2) | El importador no debe cambiar tema, experimentos ni plugins |

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
| Dos filtros independientes en una página | Pendiente |
| Envío real de formularios | Pendiente |
| Enlaces y menús | Pendiente |
| Sliders | Pendiente |
| Flujo Woo en modo de prueba | Pendiente |
| Permisos por rol | Pendiente |
| Corte y reanudación | Pendiente |
| Reversión | Pendiente |
| CSS y JS por handle | Pendiente |
| Precedencia frente a CSS sin capa, en línea, `!important` y `@layer bricks` | Pendiente |
| Sitio con v4 apagado | Pendiente |
| Sitio con el experimento `container` inactivo | Pendiente |
| Sin rastro de los plugins de origen (`brand-audit.sh`) | Publicado (F0.2); F0.2b cierra lo visible restante |

## Riesgos

- **Breakpoints distintos** (Divi 980; Betheme 959/1440; Bricks 991/767/478; GP 768). El kit sólo se cambia en sitios cuyo contenido viene íntegro del origen; en los demás se declara la diferencia.
- **Contenido y CSS que dependen del origen.** Shortcodes y CSS de usuario contra clases del origen conservan clases de compatibilidad y se informan.
- **El tema de origen pinta el contenido.** Divi, Betheme y Bricks son temas: cambiar de tema antes de convertir deja shortcodes crudos o páginas vacías. Por eso se convierte a borrador y el tema cambia al final.
- **Plantillas publicadas antes de tiempo.** Una plantilla del Theme Builder publicada reemplaza la cabecera de cualquier tema: las convertidas no se publican antes del cambio de tema.
- **Importar un kit** crea un kit nuevo, puede cambiar tema, experimentos y plugins, y borra instancias de componentes v4. Se mitiga con el modo «sólo agregar» y el kit sin `theme`, `experiments` ni `plugins`.
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

## Evidencia

`docs/fusion/README.md` indexa los informes depurados: un informe por origen (Divi, Betheme, GP, Bricks, Element Pack y Prime Slider), los de Elements, Migración y el tema, las verificaciones del plan y los cuatro inventarios de F0.2.

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
