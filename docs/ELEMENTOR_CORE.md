# Elementor incluido en DIGITALÍSIMO Elements

Desde 4.3.0.138, DIGITALÍSIMO Elements trae el núcleo gratuito de Elementor dentro del plugin. Un sitio con DIGITALÍSIMO Elements ya no necesita los plugins Elementor, Elementor Pro ni Element Pack Pro: el constructor, las funciones avanzadas (derivadas de PRO Elements) y los widgets DIGITALÍSIMO vienen en el mismo paquete. Los documentos heredados de Element Pack siguen pasando por [DIGITALÍSIMO Migración](ELEMENT_PACK_RETIREMENT.md).

## Qué contiene y cómo funciona

- **Ubicación:** `digitalisimo-elements/elementor/` es una copia sin cambios del plugin Elementor publicado en wordpress.org. Cada archivo se comprueba contra las sumas SHA-256 oficiales al sincronizar.
- **Manifiesto:** `elementor/digitalisimo-core.json` guarda la versión, el ZIP de origen, su SHA-256, la licencia (GPL-3.0-or-later), la fecha y la huella de la carpeta (`files` y `tree_sha256`, calculados con `scripts/elementor-core-tree.py`).
- **Nada ajeno en el paquete:** algunos plugins escriben en la carpeta de Elementor (Element Pack copiaba ahí sus máscaras y separadores), y en una prueba local esa carpeta puede ser la del repositorio. `scripts/build-packages.sh` no crea el ZIP de Elements si la huella de `elementor/` no coincide con la del manifiesto, y `tests/validate-suite.mjs` comprueba la huella y el número de archivos del núcleo dentro del ZIP.
- **No confundir con `core/`:** la carpeta `digitalisimo-elements/core/` es otra cosa, el núcleo derivado de Elementor Pro (`ElementorPro\Core\…`).
- **Qué núcleo manda:** el núcleo incluido se carga sólo si el plugin Elementor no está activo en el sitio ni en la red. Con Elementor activo, manda Elementor y no cambia nada. La petición que activa Elementor tampoco carga el núcleo incluido, para que las dos copias no choquen.
- **Datos:** son los mismos que con el plugin (`_elementor_*`, `elementor_*`, kits, plantillas y CSS generado). No hay conversión ni pasos de datos.
- **Activación:** al activar DIGITALÍSIMO Elements con el núcleo incluido se hace lo mismo que al activar Elementor (kit por defecto, opciones iniciales y reglas de enlaces permanentes). Un sitio nuevo de la red recibe su kit.
- **Rutas antiguas en el contenido:** lo guardado con el plugin puede apuntar a `…/plugins/elementor/assets/…`, por ejemplo a la imagen de ejemplo.
  - Al leer `_elementor_data` y `_elementor_page_settings` esas rutas pasan a las del núcleo incluido. Al volver a guardar desde el editor, la corrección queda grabada.
  - Si aun así llega una petición a la ruta antigua, se redirige con un 301 al mismo archivo del núcleo incluido.
  - La primera vez que un sitio pasa del plugin al núcleo incluido se vacía la caché de CSS de Elementor.
- **Idioma:** se incluyen las traducciones en español de wordpress.org (es_MX, es_ES y las demás variantes es_*), en PHP y en los JSON de los scripts del editor. Se usan si WordPress no tiene las del plugin Elementor, que se borran al eliminarlo. Para otros idiomas hace falta el paquete global de Elementor.
- **Complementos de terceros:** los que declaran `Requires Plugins: elementor` quedan satisfechos con DIGITALÍSIMO Elements. DIGITALÍSIMO Migración declara `Requires Plugins: digitalisimo-elements`.
- **Versión fija:** la herramienta de reversión de versiones y el canal beta de Elementor quedan desactivados, porque actualizarían la carpeta del plugin entero. La versión del núcleo la decide cada publicación de DIGITALÍSIMO Elements.
- **Figuras propias del sitio:** los SVG guardados en `uploads/digitalisimo-elements/shapes/` de cada sitio aparecen en el separador de forma como `digi-<nombre>` (con su versión negativa si existe `<nombre>-negative.svg`). Viven en los archivos del sitio y no en la carpeta del plugin, así que una actualización no los borra. DIGITALÍSIMO Migración deja ahí los separadores de Element Pack al convertir.
- **Identidad propia:** con el núcleo incluido, nadie ve el nombre ni el logotipo de Elementor; el constructor se presenta como DIGITALÍSIMO Elements. La única excepción es el plugin Elementor (o Elementor Pro) original que siga instalado: sus cabeceras se muestran tal cual en toda la administración (lista de plugins, Actualizaciones, borrado, correos), con una nota de que no hace falta, porque ese plugin no es el nuestro y nunca se manipula. Lo hace `digitalisimo-identity.php` en tiempo de ejecución, sin editar el núcleo:
  - **Textos:** los de PHP pasan por los filtros `gettext` y los de JavaScript por los filtros `i18n.*` de `wp.hooks`, en cualquier idioma y sólo para los dominios `elementor` y `elementor-pro`. «Elementor», «Elementor Pro» y «Elementor One» pasan a «DIGITALÍSIMO Elements». La IA del editor, un servicio externo con su propia cuenta, se llama sólo «IA».
  - **Logotipos:** `assets/digitalisimo/identity.css` pone el símbolo propio en el menú, las barras superiores de la administración y del editor, la pantalla de carga, la biblioteca de plantillas, la app de kits (también la cabecera de importar y exportar, cuyo logotipo es un SVG en línea que se reconoce por su trazo), los avisos y los botones «Editar con DIGITALÍSIMO Elements».
  - **Colores:** la interfaz del constructor usa la paleta de los plugins Digitalisimo (tokens de `digitalisimo-ui/`: azul, violeta y rosa, botones primarios con el degradado propio y texto blanco), nunca la de Elementor. `assets/digitalisimo/palette.css` (administración, ventana del editor y App) redefine las variables del núcleo (`--e-a-*`, `--e-one-palette-*`, `--e-context-*`) con `html:root`, que gana a sus `:root` sin depender del orden de impresión; cubre las reglas con color fijo con su mismo selector y más especificidad, y los componentes de su librería de interfaz (MUI, cuyo tema JS no usa variables) por sus clases estables, dentro de la caché de emotion `eui`. `palette-dark.css` lleva el atributo media de la hoja oscura del núcleo y lo sigue cuando la preferencia cambia en vivo; `palette-preview.css` sólo cambia las asas y marcos del lienzo (`--e-p-*`), nunca `--e-global-*` ni el contenido. La App de kits también: el enlace «Click to upload» al importar (un enlace MUI sin clase de color, cubierto por su `data-testid`) y el subrayado de los campos al exportar. Las ilustraciones, los paquetes y las Notas derivados de PRO se recolorean en origen. Se conservan los colores semánticos (éxito, aviso, peligro) y los neutros. Cada versión nueva del núcleo puede traer colores nuevos: `tests/playground/brand-audit.sh` mide los colores calculados de cada pantalla y falla si uno es de la paleta de Elementor.
  - **Sin rastro del proveedor** (`digitalisimo-vendor-free.php`, `assets/digitalisimo/vendor-free.js`): no hay enlaces, vídeos, inicios de sesión, servicios ni peticiones hacia el proveedor del núcleo ni hacia los plugins de origen.
    - Un escudo en `pre_http_request` corta toda petición del constructor hacia sus dominios. Sólo actúa sobre lo que pide el constructor: un plugin del proveedor que el administrador instale por su cuenta sigue funcionando. Donde el núcleo necesita una respuesta para no romper, se la da vacía y válida: la información de la biblioteca, sin la cual desaparecerían también las plantillas locales. Las aplicaciones de cuenta reciben un 403, porque un error las llevaría a `wp_die()`.
    - Las cachés de datos remotos del núcleo (inicio rápido, novedades, promociones, telemetría, A/B) se sirven vacías y vigentes, así que nunca se piden. `tests/elements-vendor-guard.php` falla si una versión nueva del núcleo añade otra.
    - Se apagan la telemetría (`elementor_allow_tracking`, Mixpanel sin token, eventos), la IA del constructor, los comentarios, la guía de inicio y el programa beta (también su ventana, que pedía el correo para el boletín del proveedor).
    - Se retiran de la interfaz:
      - la cuenta, la ayuda, las novedades y Angie, tanto en la barra superior como en los menús del editor (la ventana «My account», con la conexión y los planes del proveedor, tampoco se ve si llega a abrirse);
      - los «Need help»;
      - la biblioteca remota y la nube: queda «Mis plantillas», y al exportar un kit no se ofrece «Save to library»;
      - la biblioteca de kits del proveedor: su ruta lleva al importador de kits (`#/import-customization`), igual que las rutas del importador heredado `#/import` y `#/export`;
      - el asistente de configuración del núcleo (`#/onboarding` en la App: cuenta, planes y tema del proveedor). La marca `elementor_onboarded` lo da por completado, así que el núcleo no redirige a él tras activarse; una redirección que lo pida y su ruta en la App (`vendor-free.js`) llevan al Inicio rápido propio;
      - los vídeos y la documentación del Theme Builder, los avisos, las sugerencias de sus productos y cualquier «Learn more».
    - Las pantallas ocultas de cuenta, Site Planner, mejoras y apps redirigen a «Acerca de», y también una redirección del constructor hacia el proveedor. Como en el escudo, sólo la del constructor: la de otro plugin o del frontal (un enlace de afiliado enmascarado, por ejemplo) llega a su destino.
    - Herramientas no tiene «Control de versiones» ni «Sincronizar biblioteca»: revertir pediría la lista a wordpress.org e instalaría el plugin Elementor original sobre el núcleo incluido (una petición que llegue igualmente se rechaza). Las versiones del constructor llegan con las de DIGITALÍSIMO Elements.
    - Se borran una vez las cachés remotas guardadas y los tokens de cuenta.
    - Ninguna dirección latente: lo que el núcleo entrega al navegador llega sin enlaces, mejoras, planes, Site Planner ni vídeos del proveedor, aunque no se pinte. Se limpia en origen:
      - la configuración común, la de la administración y la del editor, con sus filtros (`elementor/common/localize_settings`, `elementor/admin/localize_settings`, `elementor/editor/localize_settings`), y la de la App y su cargador (sus ajustes y los de cada componente, antes de imprimirse);
      - los datos de `wp_localize_script()` que no tienen filtro (promociones de los widgets Pro y barra lateral), reescritos ya encolados y con la misma forma;
      - las plantillas `<script type="text/template">` del núcleo con enlaces (lista `TEMPLATES`), en su lista y justo antes de imprimirse. Ninguna otra plantilla ni salida se toca, y `tests/elements-vendor-guard.php` falla si una versión nueva del núcleo añade otra con una dirección del proveedor.

      En un texto con HTML, un «Learn more» sale con su enlace, un enlace dentro de una frase deja su texto y cualquier otra dirección queda vacía. Lo del usuario no cambia: su contenido, los ajustes de la página, los disparadores y reglas de un popup, las condiciones de una plantilla del Theme Builder, sus preferencias y el historial de kits importados (`EDITOR_USER_DATA`, `APP_USER_DATA`; el editor los reenvía al guardar, así que uno vaciado se perdería), ni las direcciones del propio sitio aunque esté alojado en un subdominio del proveedor.
    - Ninguna petición del navegador: algunos bundles del núcleo pintan imágenes con una dirección del proveedor escrita en el código (la pestaña «Editor atómico» de Ajustes y las ventanas de activación del editor atómico), y el navegador las pediría antes de que `vendor-free.js` las desactivara. `assets/digitalisimo/vendor-early.js` se carga en la cabecera (en la App, antes que sus scripts) y no deja asignar esa dirección a una imagen, vídeo o marco (`src`, `srcset`, `poster`, como atributo o como propiedad; no cubre el HTML insertado con `innerHTML`): el elemento queda sin ella y oculto. Las del propio sitio pasan siempre. No se carga en el frontal.
    - En el código derivado de PRO (editable) no queda ninguna dirección del proveedor ni enlaces de ayuda a otras empresas: en el PHP los textos siguen y el enlace se quita; en los bundles compilados la dirección se sustituyó por la marca `#digitalisimo-vendor-off`, que `identity.css` oculta y `vendor-free.js` desactiva (las imágenes quedan sin dirección, para que el navegador no las pida). `tests/elements-vendor-static.mjs` falla si vuelve a aparecer una dirección del proveedor fuera de la guarda.
    - La promoción de Angie en la pestaña Componentes sólo aparece si el administrador instaló Angie por su cuenta.
  - **Inicio rápido propio:** los accesos a ajustes del sitio, logotipo, colores, fuentes, Theme Builder, popups, iconos y fuentes propios tienen iconos propios. No hay bloque de vídeo, promociones ni enlaces externos.
  - **Vídeos de muestra:** el widget Vídeo, el YouTube atómico y la lista de vídeos ya no traen vídeos del proveedor por defecto, y los fondos con vídeo no lo sugieren como ejemplo (tampoco queda en la configuración del editor). Como el núcleo no guarda los valores por defecto, una página que mostraba ese vídeo de muestra deja de mostrarlo.
  - **Lo que no cambia:** los identificadores internos (clases CSS, ganchos, metadatos, rutas como `?action=elementor`, tipos de contenido) y los registros técnicos de «Información del sistema». De ellos dependen el contenido, los kits, las plantillas y los complementos de terceros. Las descripciones de herramientas que el MCP entrega a los agentes de IA tampoco cambian.
  - **Lo que se pierde, porque exige una cuenta del proveedor:**
    - la biblioteca remota de bloques y páginas, sustituida por «Mis plantillas» y las Plantillas de red de DIGITALÍSIMO Tools;
    - los kits del proveedor, sustituidos por la importación y exportación de kits en ZIP;
    - la nube de plantillas y kits, sustituida por exportar e importar;
    - la IA dentro del editor (DIGITALÍSIMO IA queda en sus propias pantallas);
    - la ayuda, las novedades y la cuenta, sustituidas por «Acerca de».
  - **Plugin Elementor activo:** si está activo, manda el plugin con su propia marca y la identidad no se aplica.
  - **Créditos:** se conservan en los archivos (`license.txt`, cabeceras GPL del núcleo, [third-party-licenses.md](third-party-licenses.md)), no en la interfaz.

## Kits

Desde la etapa K, exportar e importar kits usa el importador 3.0 del núcleo (el de la App, por REST) con las mejoras de DIGITALÍSIMO Elements, sin otro sistema de kits y sin editar `elementor/`. El diseño completo está en [fusion/k-kits.md](fusion/k-kits.md) y el estado en [FUSION_OBJECTIVE.md](FUSION_OBJECTIVE.md), sección K.

- **Dónde:** Herramientas → Importar/Exportar, la pantalla «Kits» de DIGITALÍSIMO Elements (sólo en el sitio, con su informe) y `wp digitalisimo-elements kit export|import|revert|report|inspect`. Un ZIP de kit subido en Plantillas → Importar se lleva al importador de kits.
- **Qué nunca hace:** instalar, activar o desactivar plugins o temas, cambiar experimentos ni crear un kit nuevo. Por defecto importa en «sólo agregar»; «nuevo» sólo si el administrador lo pide y el sitio está vacío.
- **Flujo heredado 2.0:** con el núcleo incluido, la App, el AJAX, `wp elementor kit`, `wp elementor library import` con un kit o una URL y la reversión heredada se cortan antes de descargar o de cambiar opciones, y mandan al importador 3.0 o a `wp digitalisimo-elements kit`. **Con el plugin Elementor original activo, sus flujos 2.0 siguen como él los hace**; las mejoras del 3.0 valen con los dos núcleos.
- **Ganchos del núcleo de los que depende K.** Todos son públicos, y `tests/elements-kit-core-contract.php` comprueba que siguen existiendo con el mismo nombre, firma y prioridad:
  - las acciones `elementor/import-export-customization/{import,export,revert}-kit` y `…/import-kit/runner/after-run`, los nombres de los runners por defecto y que `register()` sustituye por nombre en los dos procesos;
  - que `run_runner()` sólo guarda en el último runner y que `prevent_saving_elements_on_post_creation` sigue en `elementor/document/save/data` con prioridad 10 (K envuelve los desconocidos en 5);
  - `elementor/import/kit/result/<origen>`, `elementor/import/get_default_settings_conflicts`, `elementor/kit/meta_to_preserve_on_kit_import`, `elementor/document/load/data`, `elementor/frontend/section/should_render`, `elementor/template_library/sources/local/import/elements`, `elementor/templates/collect_media_url` y `elementor/import-export-customization/export/wp-content/query-args/customization`;
  - los filtros del importador WXR (`wp_import_posts`, `wp_import_post_data_raw`, `wp_import_post_data_processed`, `wp_import_post_meta`, `wp_import_term_meta`, `wp_import_post_comments`, `wp_import_insert_post`, `import_attachment_size_limit`) y del exportador (`wxr_export_skip_postmeta`, `wxr_export_skip_termmeta`), y que `fetch_remote_file()` sigue pidiendo los adjuntos en flujo (`stream`);
  - los métodos públicos de los runners que K extiende, con la misma firma;
  - **`Components\Module::IS_IMPORT_EXPORT_SUPPORTED`**, hoy `false`: mientras lo sea, las instancias de componentes v4 se conservan como marcador con el motivo `component`. Si una versión nueva lo cambia, la prueba falla con «revisar el motivo `component`».

## Diseño y condiciones (F7a)

Desde 4.3.0.164, presets de estilo, degradados, máscaras, patrones, sticky «al fijarse», controles de documento y prioridad del Theme Builder viven en `modules/digitalisimo-design/`; las condiciones nuevas y «Visitó una página», en `modules/digitalisimo-conditions/`. Diseño completo en [fusion/f7a.md](fusion/f7a.md) y estado en [FUSION_OBJECTIVE.md](FUSION_OBJECTIVE.md), sección F7a.

**Guardas.** Cada submódulo comprueba con `Design\Module::requires()` los símbolos de esta lista y `ELEMENTOR_VERSION` ≥ 4.3. Si falta uno, ese submódulo no registra controles ni ganchos, lo guardado queda inerte, nunca hay un error fatal y el editor avisa a quien puede administrar. Un símbolo que cambie de nombre en una versión nueva apaga en silencio su capacidad: por eso se repasan en cada sincronización.

| Submódulo | Símbolos (sin el prefijo `Elementor\` salvo los de PRO) |
|---|---|
| Presets (`Presets\Module::SYMBOLS`) | `Controls_Stack::get_data`, `Controls_Stack::get_controls`, `Core\Base\Base_Object::set_settings`, `Core\Frontend\Performance::set_use_style_controls` y `::is_use_style_controls`, `Modules\KitElementsDefaults\Utils\Settings_Sanitizer::remove_invalid_settings` y `Core\Files\CSS\Post` |
| Degradados | `Controls_Manager`, `Stylesheet`, `Core\Files\CSS\Post`, `Controls_Stack::start_injection` y `Controls_Stack::get_current_section` |
| Máscaras | `Controls_Manager`, `Stylesheet`, `Core\Files\CSS\Post` y `Core\Utils\Svg\Svg_Sanitizer` |
| Patrones | Los de máscaras y `Controls_Stack::start_popover` |
| Sticky | `Controls_Manager`, `Controls_Stack::start_injection`, `Controls_Stack::get_controls` y `ElementorPro\Modules\Sticky\Module` |
| Documento | `Controls_Manager`, `Core\DocumentTypes\PageBase`, `Modules\Library\Documents\Page`, `Controls_Stack::start_injection`, `ElementorPro\Modules\ThemeBuilder\Module::instance`, `ElementorPro\…\Classes\Locations_Manager::skip_doc_in_location` y `ElementorPro\…\Classes\Conditions_Manager::get_documents_for_location` |
| Prioridad | `Controls_Manager`, `Core\Base\Document::start_injection`, `ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document::get_location` y `ElementorPro\Modules\ThemeBuilder\Module::instance` |

**Ganchos y comportamientos del núcleo de los que depende F7a:**
- **CSS:**
  - `elementor/element/before_parse_css` sigue corriendo **antes** de `render_element_styles()` (`core/files/css/post.php`). Es donde se aplanan los presets.
  - `elementor/element/parse_css` y `elementor/files/css/selectors`.
  - `Stylesheet::add_rules()` y el `addRules` del editor siguen partiendo por `;`. La firma `this.addRules = function(selector, styleRules, query)` de `assets/js/editor.js` la lee `tests/elements-design-gradient.mjs`.
- **Controles:**
  - `elementor/element/common/_section_style/before_section_start`, y que el núcleo lo sigue repitiendo para `common-optimized` (`should_manually_trigger_common_action()`), porque F7a sólo engancha `common`;
  - `elementor/element/container/section_layout/before_section_start`;
  - `…/{section_background,section_style,section_background_overlay,_section_background}/before_section_end`;
  - `{section,column,container}/section_effects/after_section_end` y `{section,container,common}/section_effects/before_section_end` (detrás de `sticky_divider`);
  - `elementor/documents/register_controls`, con `post_status` y `post_title` como puntos de inyección y `hide_title` inyectado después.
- **Pilas optimizadas:** `Controls_Stack::add_control()` sigue quitando `options` y `size_units` en las pilas optimizadas, y `Performance::should_optimize_controls()` sigue igual. Los presets validan por la forma fuera de la administración.
- **Fondos:**
  - los controles de anclaje del grupo de fondo (`gradient_type`, `gradient_angle`, `gradient_position`, `color`, `color_stop`, `color_b` y `color_b_stop`) y sus valores por defecto, que se copian en `Gradient::CORE_DEFAULTS`;
  - que `gradient_angle` sólo aparece con `linear` y `gradient_position` sólo con `radial`;
  - `Svg_Sanitizer::sanitize()`.
- **Kit de ajustes:** la cadena `Settings_Sanitizer` (`for()`, `using()`, `remove_invalid_settings()`, `kses_deep()`).
- **Documentos y editor:**
  - `elementor/document/save/data`, `elementor/document/load/data` (que también corre en el front, `includes/frontend.php`) y `elementor/document/after_save`;
  - `Editor::is_edit_mode()`, `elementor/ajax/register_actions` y `elementor/kit/register_tabs`;
  - `elementor/editor/after_enqueue_scripts`, `elementor/preview/enqueue_styles` y `elementor/frontend/after_register_styles`;
  - la declaración `assets` de los controles, que encola la hoja del sticky.
- **Render y caché:** `elementor/frontend/widget/should_render` y `elementor/element/is_dynamic_content`.
- **JS del editor:**
  - los filtros `editor/style/styleText` y `elements/{tipo}/contextMenuGroups`;
  - `view.controlsCSSParser` y su constructor, y el selector `.elementor-{doc} .elementor-element.elementor-element-{id}`;
  - `elementorFrontend.config.responsive.activeBreakpoints`;
  - los comandos `document/elements/settings`, `document/globals/settings` y `document/dynamic/disable`;
  - `elementor.config.controls`, `elementor.notifications.showToast( { message, buttons } )`, `elementor.selection.getElements()`, `elementor.helpers.isAtomicWidget()` y `elementor.documents.getCurrent().config.type`;
  - que `prefix_class` con `render_type => 'ui'` repinta la clase en vivo.
- **Sticky:**
  - el formato de `data-settings`: `wp_json_encode()` sin opciones en `includes/base/element-base.php`, con `get_frontend_settings()` quitando `''` y `null`, así que `"sticky":"top"` o `"sticky":"bottom"` sólo aparece en un elemento fijo;
  - las clases `elementor-sticky--active`, `elementor-sticky--effects` y `elementor-sticky__spacer`;
  - que el espaciador se clona antes de `--active`.

  Si cambia, la alternativa preparada es un ajuste oculto calculado al guardar ([fusion/f7a.md](fusion/f7a.md), §10).
- **Condiciones** (derivado de PRO, pero se comprueba igual):
  - una condición desconocida se sigue dando por cumplida (`modules/display-conditions/classes/and-condition.php`), que es lo que el centinela neutraliza;
  - la forma de `elementor.config.displayConditions`;
  - el evento `elementor/display-conditions/open` con `detail.props.conditionsConfig`;
  - el formato del prop v4 `display-conditions`: condiciones como cadenas JSON dentro de un `condition-group`.
- **Theme Builder y bucles** (derivado de PRO): el `asort( $conditions_priority )` de `Conditions_Manager::get_location_templates()` con la línea del filtro `digitalisimo_elements_theme_builder_priorities` justo detrás; `$wp_query->is_loop_widget` y el tipo de documento `loop-item`.

## WooCommerce visual (F4-Woo)

Desde 4.3.0.166, los widgets y rasgos de WooCommerce viven en `modules/digitalisimo-woo/`: lista de deseos, vista rápida, muestras de variación, barra de envío gratis, cupón en el Menú del carrito, y filtros de precio y de atributo. El diseño está en [fusion/f4woo.md](fusion/f4woo.md). Sin WooCommerce, sólo se registran en el editor, ocultos, para que guardar no borre sus datos.

**Dependencias del núcleo:**
- `elementor/document/load/data` (prioridad 15, antes de la 20 de K) y `elementor/ajax/register_actions`, para la guarda hacia delante de `modules/digitalisimo-widgets/class-unknown-guard.php`. Sólo usa la API pública de K (`Preserve::wrap()`, `Preserve::expect()` y `Preserve::ensure_widget()`).
- La declaración `assets` de los controles (`_elementor_page_assets`), con la que las partes de Productos, el Menú del carrito y «Añadir al carrito» encolan sus recursos también con la caché de elementos.
- Que el núcleo guarda el `default` de todos los controles y genera el CSS con ellos (`includes/base/controls-stack.php`, `core/files/css/post.php`). Por eso los controles de estilo añadidos a widgets existentes no llevan `default` (`tests/elements-woo-static.mjs`).
- `Performance::should_optimize_controls()` y la pila optimizada, que quita `options` en el front. Las opciones que dependen de WooCommerce sólo se construyen fuera de ella.
- `elementorFrontend.elementsHandler.runReadyTrigger()`, que el coordinador de filtros llama tras insertar el HTML de un bucle.

**Dependencias del paquete compilado de PRO** (`assets/js/elements-handlers.js` y su `.min.js`, que no se editan), vigiladas por `tests/elements-pro-inventory.mjs` junto a su sha256:
- el filtro `elementor-pro/frontend/handlers` y su clave `taxonomyFilter`;
- los métodos `refreshLoopWidget()`, `getQueryStringInObjectForm()`, `setFilterDataForLoopWidget()` y `removeFilterFromLoopWidget()` del filtro de taxonomía;
- el marcado `.e-filter-item[data-filter][aria-pressed]`;
- la ruta REST `elementor-pro/v1/refresh-loop` y `ElementorProFrontendConfig.urls.rest`;
- el aviso `elementor-pro/modules/init/before`, que `assets/js/frontend.js` lanza en `initModules()` justo antes de aplicar el filtro `elementor-pro/frontend/handlers` (el propio `elements-handlers.js` se engancha así). `hooks` del núcleo nace en `elementorFrontend.init()`, en el *DOM ready*, así que un script evaluado antes no puede registrar el filtro al cargarse; en Chrome, el oyente jQuery de PRO en `elementor/frontend/init` corre antes que cualquier oyente nativo del mismo aviso. `filters.js` registra su filtro al recibir ese aviso (o de inmediato si `hooks` ya existe), y `filters-form.js` espera a `elementor/frontend/init` para sus `element_ready`. `tests/elements-woo-filters.mjs` (caso 1b) reproduce los dos órdenes de arranque.

`assets/js/filters.js` sustituye por herencia `refreshLoopWidget()`, con prioridad 20, sólo en los bucles que tienen un filtro de F4-Woo. Es la excepción declarada en `DIGITALISIMO_CHANGES.md`. Si falta algo de esta lista, no sustituye nada y los filtros de F4-Woo funcionan por GET.

## Capacidades de Bricks (F7b)

Desde 4.3.0.169, acciones con destino, interacciones PRO, consultas guardadas, sobrescrituras del Template, slider anidado, presentación de anidados, «Aplicar a todos», etiquetas en el texto y formularios condicionales viven en `modules/digitalisimo-f7b/`; los filtros ampliados, en el módulo neutro `modules/digitalisimo-filters/`, que también usan los de F4-Woo. Diseño completo en [fusion/f7b.md](fusion/f7b.md) y estado en [FUSION_OBJECTIVE.md](FUSION_OBJECTIVE.md), sección F7b.

**Guardas.** Cada submódulo comprueba sus símbolos con `F7b\Module::requires()`. Si falta uno, ese submódulo no registra nada, lo guardado queda inerte y nunca hay un error fatal (la base captura `\Throwable` al comprobar y al arrancar); el editor avisa a quien puede administrar. Una clase que hereda de `Widget_Base` no se comprueba en `plugins_loaded`, porque el núcleo la declara en `init`.

| Submódulo | Símbolos (sin el prefijo `Elementor\` salvo los de PRO) |
|---|---|
| Acciones | `Controls_Manager` y `Plugin` |
| Consultas guardadas | `ElementorPro\Modules\QueryControl\Classes\Elementor_Post_Query` (que `Queries\Args` extiende) y `Digitalisimo\Elements\Filters\Query::denied` |
| Sobrescrituras y etiquetas en el texto | `Plugin` y `Controls_Manager` |
| Slider anidado (en `elementor/widgets/register`) | El experimento `nested-elements`, `Modules\NestedElements\Base\Widget_Nested_Base` y `Modules\NestedElements\Controls\Control_Nested_Repeater` |
| Presentación | `Controls_Manager` |
| Formularios condicionales | `Controls_Manager` y `Plugin`; al enviar, `ElementorPro\Modules\Forms\Classes\Form_Record::remove_field` y `Ajax_Handler::set_success` |
| Kit | La interfaz `Part` de K y `Queries\Store` |

**Ganchos y comportamientos del núcleo de los que depende F7b:**
- **Render y caché:**
  - `elementor/widget/before_render_content` (`includes/base/widget-base.php`), `elementor/widget/render_content` y `elementor/frontend/widget/after_render` (`includes/base/element-base.php`); `elementor/widget/after_render_content` no existe;
  - `elementor/frontend/builder_content_data` con `( $data, $post_id )` (`includes/frontend.php`) y que `get_builder_content()` cambia al documento que pinta antes de llamarlo;
  - que `Document::print_elements()` no lee ni escribe `_elementor_element_cache` cuando `get_option( 'elementor_element_cache_ttl' )` vale `'disable'`, y que la caché del documento guarda los handles encolados al pintar y los vuelve a encolar en la visita servida desde ella (`core/base/document.php`): por eso el script de los formularios condicionales se registra en `elementor/frontend/after_register_scripts` y no al pintar el campo;
  - que `Document::render_element()` y `Widget_Base::get_raw_data( true )` llaman a `render_content()` sin `print_element()` (editor y `htmlCache`);
  - `Element_Base::is_dynamic_content()` verdadero por defecto, el filtro `elementor/element/is_dynamic_content` y `_element_cache`;
  - `elementor/frontend/before_render`, `elementor/frontend/after_render`, `elementor/frontend/container/should_render` y `elementor/element/parse_css`;
  - que el núcleo conserva al pintar los ajustes sin control y que `get_settings_for_display()` memoriza, con `reset_render_state()` como única forma pública de vaciarlo (`includes/base/controls-stack.php`);
  - `elementor/markdown/element_output` (`markdown-renderer.php`), y que el Markdown del Plantilla no pasa por `builder_content_data`;
  - `parse_text_editor()` del Editor de texto, que ejecuta shortcodes.
- **Controles:**
  - `elementor/element/{common,container,section,column}/_section_responsive/after_section_end` (y que el núcleo repite el de `common` para `common-optimized`);
  - `…/{heading/section_title,text-editor/section_editor,image/section_image,button/section_button,icon/section_icon,video/section_video,icon-box/section_icon}/before_section_end` (clave de sobrescritura y etiquetas en el texto) y `call-to-action/section_content/before_section_end` (PRO, etiquetas en el texto), con los nombres de sus controles de texto (`title`, `editor`, `text`, `title_text`, `description_text`, `description`, `button`);
  - `elementor/element/nested-tabs/section_tabs_style/after_section_end` y `elementor/element/nested-accordion/section_accordion_style/after_section_end`;
  - `prefix_class` con `classes_dictionary` en `Element_Base` y su repintado en vivo con `render_type => 'ui'`;
  - un repetidor dentro de las filas de otro (`Control_Repeater::get_value()`, `get_active_settings()`, `parse_dynamic_settings()`, `sanitize_settings()`), y que la declaración `assets` sólo se lee en los controles de primer nivel (`core/base/elements-iteration-actions/assets.php`);
  - la API de `Widget_Nested_Base` (`get_default_children_elements()`, `get_default_repeater_title_setting_key()`, `print_child()`, `content_template_single_repeater_item()`, `get_initial_config()` con `support_improved_repeaters`, `target_container` y `node`).
- **Documentos, AJAX y editor PHP:**
  - `elementor/document/before_save`, `elementor/document/after_save` y `elementor/document/config`;
  - `elementor/ajax/register_actions` y el registro **privado** de acciones del gestor AJAX (`core/common/modules/ajax/module.php`), del que se lee la función original de `delete_template` y `bulk_delete_templates` (`includes/template-library/manager.php`);
  - `elementor/editor/after_enqueue_scripts`, `elementor/preview/enqueue_styles`, `elementor/frontend/after_register_styles` y `elementor/frontend/after_register_scripts`;
  - `Utils::kses_post_deep()` al guardar para quien no tiene `unfiltered_html`, y que los metadatos del documento omiten las metas protegidas al exportar;
  - `Plugin::$instance->dynamic_tags->get_tag_data_content()` (`core/dynamic-tags/manager.php`).
- **JS del editor:**
  - `isStyleTransferControl()`, `document/elements/paste-style` con `containers` y `storageKey`, su `getHistory()`, y el formato de `document/elements/copy`;
  - el filtro `elements/widget/contextMenuGroups` (con la vista), `elementor.userCan( 'design' )` y `elementorCommon.storage` (`get`, `set`, `save`);
  - `document/elements/settings`, `document/repeater/insert` y `remove`, y `removeDataDefaults()`, que no guarda los valores por defecto ni en las filas de un repetidor; con `options.external`, `document/elements/settings` refresca los controles abiertos (por eso «Desvincular» escribe sin él y reabre el panel con `panel/editor/open`);
  - `elementorModules.editor.elements.models.BaseSettings.prototype.convertRepeaterValueToCollection()` y `handleRepeaterData()` (`assets/js/editor-modules.js`): `forms-conditions-editor.js` envuelve el primero para copiar cada fila de un repetidor con otro repetidor dentro, porque el núcleo escribe la colección interior en el objeto crudo y PRO (`editor-templates-extended`) la recorre sin fin. **Si cambia de nombre, el arreglo se apaga sin error y el editor de una página con un formulario condicional vuelve a quedarse cargando**: se comprueba en cada sincronización abriendo esa página (`tests/playground/f7b.sh`, B10);
  - `NestedElementBase`, `NestedView`, `elementor/nested-element-type-loaded` y `elementor/nested-container/atomic-repeater` (slider anidado);
  - la configuración `fields` de un repetidor, que `mergeControlsSettings()` sustituye al crear cada fila, y `getChildControlView()` (select de las sobrescrituras: si cambia, sólo se pierde la lista del editor).
- **JS y CSS del front:**
  - `elementorFrontend.utils.urlActions.createActionHash()` y `runAction()` (`assets/js/frontend.js`), `elementor/frontend/init`, `frontend/element_ready/*` y `elementsHandler.runReadyTrigger()`;
  - que `frontend.min.css` no tiene ninguna regla `[hidden]` y que `.e-con{display:var(--display)}` y `.elementor-column{display:flex}` le ganan a la hoja del navegador (por eso la regla propia); que el orden invertido de columnas usa `:nth-child()` y el espaciado de widgets `:last-child` (por eso el `<noscript>` va dentro del envoltorio);
  - las variables de `widget-nested-tabs.min.css` y de su par del acordeón, en el propio envoltorio con especificidad 0,1,0;
  - `MotionGlobalConfig.skipAnimations` de `assets/lib/motion/motion.js` y el handle `motion-js`.

**Dependencias del código derivado de PRO** (se comprueban igual en cada sincronización, porque PRO se sincroniza con el núcleo):
- **Consultas:** `elementor/query/query_args` al final de `Elementor_Post_Query::get_query_args()` (y que «Consulta actual» sale antes), `elementor/query/query_results`, `fix_query_offset()` y `fix_query_found_posts()`, la sección `section_query` de Posts, Portfolio y la base del Loop Grid, y que los skins de Posts y Portfolio no pintan nada con `found_posts = 0`.
- **Bucles y filtros:** `refresh-loop` (`modules/loop-filter/data/endpoints/refresh-loop.php`) y su `permission_callback` (`core/data/endpoints/refresh-base.php`); los tres filtros del derivado `loop-filter` (`digitalisimo_elements_loop_filter_types`, `…_query`, `…_param_type`) y su corte por el primer guion.
- **Plantilla:** `modules/library/widgets/template.php` (`render()` sólo con una plantilla publicada, `on_import()` de `template_id`, el Markdown).
- **Formularios:** el gancho `elementor/element/form/section_form_fields/before_section_end` con `update_control( 'form_fields' )`, `elementor_pro/forms/render/item`, `elementor_pro/forms/validation` **después** del error de obligatorio de `Form_Record::validate()`, `Ajax_Handler::$errors` pública, `add_error()` y `set_success()`, `required` en cada opción de radios, la respuesta al remitente que lee `sent_data`, y el `reset` tras un envío correcto.
- **Popups:** `Popup\Module::add_popup_to_location()` y las acciones de URL `popup:open`, `popup:close` y `off_canvas:*`, con `showPopup`, `closePopup` y `handleDisplayToggle` en `elements-handlers.min.js` (vigilados por `tests/elements-pro-inventory.mjs`), y que `showModal()` vuelve a crear el popup desde su HTML en cada apertura (por eso `actions.js` decide por el elemento enlazado y no por su marca).
- **Etiquetas dinámicas:** los nombres `post-title`, `post-excerpt`, `author-name`, `post-date`, `post-custom-field`, `acf-text`, `user-info`, `site-title`, `site-tagline` y `current-date-time`; la carga del módulo ACF; y que el rasgo `on_import` sólo traduce controles de primer nivel (por eso K declara `popup_id`).
- **Interacciones:** `modules/interactions/module.php`, con la línea declarada en `DIGITALISIMO_CHANGES.md` (`tests/elements-f7b-interactions.php` comprueba la sha256 del resto), sus `hooks.php` y el formato `interactions` de los elementos atómicos con sus disparadores y efectos de `elementor/modules/interactions/presets.php`.

## Rasgos de F8 y lo que Migración usa del núcleo y de PRO

Desde 4.3.0.170, «Información emergente» (`modules/digitalisimo-widgets/class-tooltip.php`), «Distintivo» (`class-badge.php`), el contador opcional del slider anidado (`modules/digitalisimo-f7b/nested/`) y `Export_Skip` (`class-export-skip.php`) son código propio de Elements, arrancado en la lista de rasgos de `class-registry.php`. DIGITALÍSIMO Migración 1.0.21 escribe sus claves al convertir Element Pack y Prime Slider, y además depende de símbolos del núcleo y de PRO para crear y publicar lo que convierte. Diseño completo en [fusion/f8.md](fusion/f8.md) y estado en [FUSION_OBJECTIVE.md](FUSION_OBJECTIVE.md), sección F8.

**Ganchos y comportamientos del núcleo de los que dependen los rasgos y el contador:**
- `elementor/element/common/_section_style/after_section_end` (y que el núcleo lo repite para `common-optimized`), `elementor/element/{section/section_advanced,container/section_layout,column/section_advanced}/after_section_end`;
- `elementor/frontend/widget/before_render`, `elementor/frontend/{widget,section,column,container}/before_render` y `after_render` (el distintivo usa un búfer, como «Enlace del elemento»), y `elementor/widget/render_content`; que el lienzo del editor pinta el envoltorio en JavaScript (por eso los rasgos no se ven en el lienzo) y que el Markdown no pasa por `render_content`;
- la declaración `assets` en los controles de primer nivel (`core/base/elements-iteration-actions/assets.php`), que encola las hojas y los scripts también con la caché de elementos; y que la vista previa del editor no aplica los recursos de página, por lo que el contador se encola en `elementor/preview/enqueue_styles` y `…_scripts`;
- que `frontend.min.css` ya pone `position: relative` en `.elementor-widget`, `.elementor-section`, `.elementor-column` y `.e-con` (los rasgos no la repiten);
- la rejilla de `nested-slider.css` (3 columnas, `--digi-ns-fg`) y que el motor del slider anidado mueve el `aria-current` de los puntos aunque estén ocultos: el contador lo observa;
- el filtro `wxr_export_skip_postmeta` de WordPress y `Meta_Blacklist::skip()` de K, que respeta un `true` previo.

**Del núcleo y de PRO de los que depende DIGITALÍSIMO Migración** (se revisan en cada sincronización, porque PRO se sincroniza con el núcleo; si uno cambia, se adapta Migración, nunca el núcleo):

| Área | Símbolos y comportamientos |
|---|---|
| Documentos | `Plugin::$instance->documents->create()` con `meta_input` (`core/documents-manager.php`), el documento `loop-item` con `_elementor_template_type`, su término y `_elementor_source` (`modules/loop-builder/documents/loop.php`), `_elementor_page_settings` como array |
| Theme Builder | Los tipos de plantilla de `modules/theme-builder/classes/templates-types-manager.php` y de `modules/woocommerce/documents/`; las condiciones (`Singular`, `Archive`, `Post`, `Post_Type_Archive` y las de WooCommerce) y `get_condition()`; que `conditions-manager.php` y `locations-manager.php` leen el estado de cada plantilla **sólo** con `get_post_status()` (la publicación perezosa lo filtra); que la caché de condiciones incluye los borradores y `Conditions_Cache::regenerate()`; el filtro `digitalisimo_elements_theme_builder_priorities` y `Priority::reindex()` (`modules/digitalisimo-design/theme-builder/class-priority.php`) |
| Fuentes | `Custom_Fonts::generate_font_face()` (`modules/assets-manager/asset-types/fonts/custom-fonts.php`, que interpola el título de la fuente sin escapar: por eso Migración rechaza familias inseguras), las opciones `elementor_fonts_manager_fonts` y `_font_types` y la taxonomía `elementor_font_type` |
| Iconos | `Custom_Icons::is_icon_set_dir()`, los filtros `elementor_pro/icons_manager/custom_icons/{dir,url}`, la meta `elementor_custom_icon_set_config`, la opción `elementor_custom_icon_sets_config` y el filtro `elementor/icons_manager/additional_tabs` del núcleo (pestaña de alias) |
| Código personalizado | El tipo `elementor_snippet` (`code_snippet`), las metas `_elementor_location`, `_elementor_priority`, `_elementor_code` y `_elementor_conditions`, y que el módulo lee los fragmentos publicados con `get_posts()` (`modules/custom-code/module.php`) |
| Widgets de destino | `nav-menu` (control `menu` = slug), `elementor-library` (barra lateral, sólo plantillas publicadas), `loop-carousel` (`post_query_*`, `product_query_*`, `slides_to_show*`, `image_spacing_custom*`, `autoplay_speed`, `infinite`, `pagination`), `woocommerce-products` (`query_*`), `media-carousel`, `hotspot`, `theme-post-title`, `theme-post-excerpt`, `post-info`, `woocommerce-product-{title,price,add-to-cart}`; que `Pro_Gate::HANDLED` incluye `loop-carousel`, `hotspot` y `woocommerce-product-add-to-cart`; `has_widget_inner_wrapper()` de cada destino (marcado optimizado) |
| Contenedores anidados | Que el editor guarda los contenedores hijos con `isInner` e `isLocked` (`assets/js/editor.js`, `assets/js/nested-elements.js`) |
| Etiquetas dinámicas | El formato de `Manager::tag_to_text()` (`core/dynamic-tags/manager.php`), los nombres `post-title`, `site-title`, `site-tagline`, `archive-title`, `archive-description`, `post-id`, `author-name`, `post-url`, `site-url`, `archive-url`, `post-featured-image`, `site-logo` y `woocommerce-product-title-tag` |
| Medios | El saneador SVG del núcleo, la opción de subida sin filtrar del constructor y la imagen de relleno `Utils::get_placeholder_image_src()` |
| Kits (K) | El filtro `digitalisimo_elements_unconverted_convert`, `Preserve::info()`, `marker()`, `recount_document()`, `should_register()` e `is_editor_context()` (`modules/digitalisimo-import/kits/class-preserve.php`) |
| Otros | `digitalisimo_elements_conflicting_plugin_active()` (el PRO de Elements no carga con Elementor Pro o PRO Elements activos), la constante `DIGITALISIMO_ELEMENTS_VERSION` (Migración exige 4.3.0.170), `Utils::kses_post_deep()` al guardar sin `unfiltered_html` |

## Pasar un sitio o una red al núcleo incluido

Con DIGITALÍSIMO Migración activo, **Migrar** (Herramientas → Migrar a DIGITALÍSIMO Elements, o Red → Ajustes → Migrar a DIGITALÍSIMO Elements → Migrar toda la red) hace todo esto en un clic: desactiva Elementor (sólo si el núcleo incluido puede sustituirlo), Elementor Pro, PRO Elements y Element Pack, convierte el contenido y deja el informe ([ELEMENT_PACK_RETIREMENT.md](ELEMENT_PACK_RETIREMENT.md)). A mano:

1. Actualiza DIGITALÍSIMO Elements a 4.3.0.138 o posterior y haz un respaldo.
2. En **Plugins** (o **Red → Plugins**) un aviso indica qué versión de Elementor incluye DIGITALÍSIMO Elements. Desactiva el plugin **Elementor** donde esté activo (en la red o en cada sitio).
3. Revisa páginas, plantillas, Theme Builder y el editor de alguna página.
4. Si todo está bien, borra el plugin Elementor. Si algo falla, vuelve a activarlo: manda de nuevo y no se pierde nada. Mientras siga instalado y desactivado, la lista de plugins lo muestra con sus propias cabeceras («Elementor», por «Elementor.com») y una nota de que es el original y no hace falta: sus cabeceras no pasan por los filtros de identidad, así que no se confunde con DIGITALÍSIMO Elements (igual con Elementor Pro). Borrarlo con el núcleo incluido cargado no ejecuta su rutina de desinstalación (cargaría su archivo principal sobre el núcleo): sólo se van sus archivos.

Elementor Pro y PRO Elements deben quedar desactivados, como ya exigía DIGITALÍSIMO Elements. Element Pack Pro se retira siguiendo [ELEMENT_PACK_RETIREMENT.md](ELEMENT_PACK_RETIREMENT.md).

## Mantenimiento: actualizar Elementor

Elementor publica versiones con frecuencia, a veces con correcciones de seguridad. Mientras los sitios usen el núcleo incluido, esas correcciones sólo les llegan con una versión nueva de DIGITALÍSIMO Elements:

1. Consulta la versión publicada: `curl -s https://api.wordpress.org/plugins/info/1.0/elementor.json`.
2. Sincroniza: `bash scripts/sync-elementor-core.sh X.Y.Z`. Comprueba las sumas, copia el núcleo y sus traducciones en español y actualiza el manifiesto. Nunca se editan a mano los archivos de `elementor/`.
3. Valida:
   - `bash tests/playground/core-switch.sh`: las mismas páginas con el plugin Elementor y con el núcleo incluido en una red, tras borrar Elementor.
   - `CORE=bundled bash tests/playground/interaction.sh`: widgets en Chrome y el editor sobre el núcleo incluido.
   - `bash tests/playground/conditions.sh`: condiciones de visualización en páginas reales con los tres motores, caché de elementos, plantillas incrustadas y guardado desde el editor.
   - `bash tests/playground/brand-audit.sh`: recorre en Chrome, en español y en inglés, la administración, el editor, la biblioteca, los kits y el frontal; falla si se ve «Elementor» o su logotipo. Una versión nueva del núcleo puede traer pantallas o textos fijos nuevos.
   - `CORE=bundled FAKE_PLUGINS=1 EXTRA_PLUGINS=woocommerce,secure-custom-fields bash tests/playground/legacy-ab.sh` (y bbPress aparte): la migración desde Element Pack completa sobre el núcleo incluido.
   - `CORE=bundled bash tests/playground/form-submit.sh` y `CORE=bundled bash tests/playground/migration-report.sh`.
   - **Kits (K):**
     - `php tests/elements-kit-core-contract.php` (con php-wasm, juzgando por la salida completa): falla si cambió un gancho, un nombre de runner, una firma o `IS_IMPORT_EXPORT_SUPPORTED`. Si falla, se adapta K antes de publicar, nunca el núcleo.
     - `bash tests/playground/kit-roundtrip.sh` completo (con `WOO=1`, `CORE=plugin` y `SLOW=1`) y `PORT=9464 SCOPE=quick STATE=kit bash tests/playground/brand-audit.sh` (también con `SCOPE=full`). Son obligatorios a mano, porque Playground no corre en CI; el `summary.json` se copia a `docs/fusion/k-kits-evidence.json`.
     - Si la firma de R1 difiere de `tests/playground/kit-roundtrip/golden/` sólo por un cambio legítimo del formato del núcleo, se revisa la diferencia y se regenera con `KIT_GOLDEN=write SCENARIOS=R1 bash tests/playground/kit-roundtrip.sh`; cualquier otra diferencia es una regresión.
     - El inventario en tiempo de ejecución de `kit-roundtrip.sh` falla si un widget del núcleo trae un control con aspecto de referencia (un ID, un menú, una plantilla) que el registro de K no declara: se declara en `References::builtin()` o se anota con su motivo en `tests/sources/kits/no-reference.json`.
   - **Diseño y condiciones (F7a):**
     - las unitarias de F7a (con php-wasm, juzgando por la salida completa), que usan el núcleo incluido real: `tests/elements-design-{css,presets,presets-kit,gradient,backgrounds}.php`, `tests/elements-design-{presets-editor,gradient}.mjs`, `tests/elements-conditions-more.php`, `tests/elements-page-visits.{php,mjs}`, `tests/elements-tb-priority.php`, `tests/elements-sticky-state.php`, `tests/elements-document-controls.php` y `tests/elements-design-static.mjs`;
     - con un símbolo de la tabla de «Diseño y condiciones (F7a)» que ya no existe, se adapta el submódulo antes de publicar, nunca el núcleo;
     - `bash tests/playground/design.sh` (DS1–DS13, también con `CACHE=1`, `CORE=plugin`, `V4=1` y `NET=1`), a mano porque Playground no corre en CI.
   - **Capacidades de Bricks (F7b):**
     - las unitarias de F7b (con php-wasm, juzgando por la salida completa), que usan el núcleo y los derivados reales de PRO: `tests/elements-f7b-{module,usages,actions,interactions,queries,filters,overrides,nested,presentation,inline-tags,forms,kit}.php`, `tests/elements-f7b-{static,actions,queries,filters,overrides,nested-slider,presentation,apply-all,forms}.mjs` y, con Chrome, `tests/elements-f7b-{actions,nested-slider,presentation,forms}-chrome.mjs`. `elements-f7b-apply-all.mjs` extrae de `elementor/assets/js/editor.js` el código de «Pegar estilo» y falla si se movió; `elements-f7b-presentation.php` compara los archivos de las pestañas y el acordeón anidados con los de la versión anterior;
     - con un símbolo, un gancho o un comportamiento de la lista de «Capacidades de Bricks (F7b)» que cambió, se adapta el submódulo antes de publicar, nunca el núcleo; si cambian `builder_content_data` o la caché por documento, las sobrescrituras dejan de aplicarse (el Plantilla pinta la plantilla sin sobrescribir);
     - `bash tests/playground/f7b.sh` (B1–B12, también con `WOO=1` para B7), a mano porque Playground no corre en CI.
   - **Rasgos de F8 y Migración:**
     - las unitarias de Elements `tests/elements-{tooltip,badge}.php`, `elements-tooltip.mjs`, `elements-export-skip.php` y, con Chrome, `elements-{tooltip,badge}-chrome.mjs` y `elements-f7b-nested-slider-chrome.mjs`;
     - las de Migración que leen el núcleo y PRO incluidos: `tests/migration-{theme-builder,platform,prime-slider,prime-slider-dynamic,markers}.php`. Con un símbolo de la tabla de «Rasgos de F8 y lo que Migración usa del núcleo y de PRO» que cambió, se adapta Migración antes de publicar, nunca el núcleo; si cambia cómo `conditions-manager.php` o `locations-manager.php` leen el estado de una plantilla, la publicación perezosa deja de proteger las peticiones simultáneas;
     - `bash tests/playground/f8.sh` (P0–P10), a mano porque Playground no corre en CI.
   - Las pruebas habituales de la publicación.
4. Publica DIGITALÍSIMO Elements como cualquier otra versión.
