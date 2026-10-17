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
  - **Logotipos:** `assets/digitalisimo/identity.css` pone el símbolo propio en el menú, las barras superiores de la administración y del editor, la pantalla de carga, la biblioteca de plantillas, la app de kits, los avisos y los botones «Editar con DIGITALÍSIMO Elements».
  - **Colores:** la interfaz del constructor usa la paleta de los plugins Digitalisimo (tokens de `digitalisimo-ui/`: azul, violeta y rosa, botones primarios con el degradado propio y texto blanco), nunca la de Elementor. `assets/digitalisimo/palette.css` (administración, ventana del editor y App) redefine las variables del núcleo (`--e-a-*`, `--e-one-palette-*`, `--e-context-*`) con `html:root`, que gana a sus `:root` sin depender del orden de impresión; cubre las reglas con color fijo con su mismo selector y más especificidad, y los componentes de su librería de interfaz (MUI, cuyo tema JS no usa variables) por sus clases estables, dentro de la caché de emotion `eui`. `palette-dark.css` lleva el atributo media de la hoja oscura del núcleo y lo sigue cuando la preferencia cambia en vivo; `palette-preview.css` sólo cambia las asas y marcos del lienzo (`--e-p-*`), nunca `--e-global-*` ni el contenido. Las ilustraciones, los paquetes y las Notas derivados de PRO se recolorean en origen. Se conservan los colores semánticos (éxito, aviso, peligro) y los neutros. Cada versión nueva del núcleo puede traer colores nuevos: `tests/playground/brand-audit.sh` mide los colores calculados de cada pantalla y falla si uno es de la paleta de Elementor.
  - **Sin rastro del proveedor** (`digitalisimo-vendor-free.php`, `assets/digitalisimo/vendor-free.js`): no hay enlaces, vídeos, inicios de sesión, servicios ni peticiones hacia el proveedor del núcleo ni hacia los plugins de origen.
    - Un escudo en `pre_http_request` corta toda petición del constructor hacia sus dominios. Sólo actúa sobre lo que pide el constructor: un plugin del proveedor que el administrador instale por su cuenta sigue funcionando. Donde el núcleo necesita una respuesta para no romper, se la da vacía y válida: la información de la biblioteca, sin la cual desaparecerían también las plantillas locales. Las aplicaciones de cuenta reciben un 403, porque un error las llevaría a `wp_die()`.
    - Las cachés de datos remotos del núcleo (inicio rápido, novedades, promociones, telemetría, A/B) se sirven vacías y vigentes, así que nunca se piden. `tests/elements-vendor-guard.php` falla si una versión nueva del núcleo añade otra.
    - Se apagan la telemetría (`elementor_allow_tracking`, Mixpanel sin token, eventos), la IA del constructor, los comentarios, la guía de inicio y el programa beta.
    - Se retiran de la interfaz:
      - la cuenta, la ayuda, las novedades y Angie, tanto en la barra superior como en los menús del editor;
      - los «Need help»;
      - la biblioteca remota y la nube: queda «Mis plantillas»;
      - la biblioteca de kits del proveedor: su ruta lleva a importar un kit en ZIP;
      - los vídeos y la documentación del Theme Builder, los avisos, las sugerencias de sus productos y cualquier «Learn more».
    - Las pantallas ocultas de cuenta, Site Planner, mejoras y apps redirigen a «Acerca de».
    - Herramientas no tiene «Control de versiones» ni «Sincronizar biblioteca»: revertir pediría la lista a wordpress.org e instalaría el plugin Elementor original sobre el núcleo incluido (una petición que llegue igualmente se rechaza). Las versiones del constructor llegan con las de DIGITALÍSIMO Elements.
    - Se borran una vez las cachés remotas guardadas y los tokens de cuenta.
    - En el código derivado de PRO (editable) no queda ninguna dirección del proveedor ni enlaces de ayuda a otras empresas: en el PHP los textos siguen y el enlace se quita; en los bundles compilados la dirección se sustituyó por la marca `#digitalisimo-vendor-off`, que `identity.css` oculta y `vendor-free.js` desactiva (las imágenes quedan sin dirección, para que el navegador no las pida). `tests/elements-vendor-static.mjs` falla si vuelve a aparecer una dirección del proveedor fuera de la guarda.
    - La promoción de Angie en la pestaña Componentes sólo aparece si el administrador instaló Angie por su cuenta.
  - **Inicio rápido propio:** los accesos a ajustes del sitio, logotipo, colores, fuentes, Theme Builder, popups, iconos y fuentes propios tienen iconos propios. No hay bloque de vídeo, promociones ni enlaces externos.
  - **Vídeos de muestra:** el widget Vídeo, el YouTube atómico y la lista de vídeos ya no traen vídeos del proveedor por defecto. Como el núcleo no guarda los valores por defecto, una página que mostraba ese vídeo de muestra deja de mostrarlo.
  - **Lo que no cambia:** los identificadores internos (clases CSS, ganchos, metadatos, rutas como `?action=elementor`, tipos de contenido) y los registros técnicos de «Información del sistema». De ellos dependen el contenido, los kits, las plantillas y los complementos de terceros. Las descripciones de herramientas que el MCP entrega a los agentes de IA tampoco cambian.
  - **Lo que se pierde, porque exige una cuenta del proveedor:**
    - la biblioteca remota de bloques y páginas, sustituida por «Mis plantillas» y las Plantillas de red de DIGITALÍSIMO Tools;
    - los kits del proveedor, sustituidos por la importación y exportación de kits en ZIP;
    - la nube de plantillas y kits, sustituida por exportar e importar;
    - la IA dentro del editor (DIGITALÍSIMO IA queda en sus propias pantallas);
    - la ayuda, las novedades y la cuenta, sustituidas por «Acerca de».
  - **Plugin Elementor activo:** si está activo, manda el plugin con su propia marca y la identidad no se aplica.
  - **Créditos:** se conservan en los archivos (`license.txt`, cabeceras GPL del núcleo, [third-party-licenses.md](third-party-licenses.md)), no en la interfaz.

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
   - Las pruebas habituales de la publicación.
4. Publica DIGITALÍSIMO Elements como cualquier otra versión.
