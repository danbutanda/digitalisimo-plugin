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
- la ruta REST `elementor-pro/v1/refresh-loop` y `ElementorProFrontendConfig.urls.rest`.

`assets/js/filters.js` sustituye por herencia `refreshLoopWidget()`, con prioridad 20, sólo en los bucles que tienen un filtro de F4-Woo. Es la excepción declarada en `DIGITALISIMO_CHANGES.md`. Si falta algo de esta lista, no sustituye nada y los filtros de F4-Woo funcionan por GET.

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
   - Las pruebas habituales de la publicación.
4. Publica DIGITALÍSIMO Elements como cualquier otra versión.
