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
- **Identidad propia:** con el núcleo incluido, nadie ve el nombre ni el logotipo de Elementor; el constructor se presenta como DIGITALÍSIMO Elements. Lo hace `digitalisimo-identity.php` en tiempo de ejecución, sin editar el núcleo:
  - **Textos:** los de PHP pasan por los filtros `gettext` y los de JavaScript por los filtros `i18n.*` de `wp.hooks`, en cualquier idioma y sólo para los dominios `elementor` y `elementor-pro`. «Elementor», «Elementor Pro» y «Elementor One» pasan a «DIGITALÍSIMO Elements». La IA del editor, un servicio externo con su propia cuenta, se llama sólo «IA».
  - **Logotipos:** `assets/digitalisimo/identity.css` pone el símbolo propio en el menú, las barras superiores de la administración y del editor, la pantalla de carga, la biblioteca de plantillas, la app de kits, los avisos y los botones «Editar con DIGITALÍSIMO Elements».
  - **Contenido remoto y promocional:** se retiran la meta `generator`, las novedades y anuncios remotos, las noticias del blog en el panel del escritorio, la pantalla «Home» (su dirección lleva al inicio rápido) y el asistente de bienvenida. Del inicio rápido salen el vídeo, la valoración, las mejoras de plan y los enlaces a la comunidad. Los accesos a ajustes del sitio, logotipo, colores y fuentes se conservan.
  - **Lo que no cambia:** los identificadores internos (clases CSS, ganchos, metadatos, rutas como `?action=elementor`, tipos de contenido) y los registros técnicos de «Información del sistema». De ellos dependen el contenido, los kits, las plantillas y los complementos de terceros. Las descripciones de herramientas que el MCP entrega a los agentes de IA tampoco cambian.
  - **Integración que se mantiene:** la biblioteca de kits y de plantillas, la conexión de cuenta y la IA del editor siguen funcionando con los servicios del proveedor; sus propias páginas (inicio de sesión, cuenta) son externas.
  - **Plugin Elementor activo:** si está activo, manda el plugin con su propia marca y la identidad no se aplica.
  - **Créditos:** se conservan en los archivos (`license.txt`, cabeceras GPL del núcleo, [third-party-licenses.md](third-party-licenses.md)), no en la interfaz.

## Pasar un sitio o una red al núcleo incluido

1. Actualiza DIGITALÍSIMO Elements a 4.3.0.138 o posterior y haz un respaldo.
2. En **Plugins** (o **Red → Plugins**) un aviso indica qué versión de Elementor incluye DIGITALÍSIMO Elements. Desactiva el plugin **Elementor** donde esté activo (en la red o en cada sitio).
3. Revisa páginas, plantillas, Theme Builder y el editor de alguna página.
4. Si todo está bien, borra el plugin Elementor. Si algo falla, vuelve a activarlo: manda de nuevo y no se pierde nada.

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
