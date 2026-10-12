# Elementor incluido en DIGITALÍSIMO Elements

Desde 4.3.0.138, DIGITALÍSIMO Elements trae el núcleo gratuito de Elementor dentro del plugin. Un sitio con DIGITALÍSIMO Elements ya no necesita los plugins Elementor, Elementor Pro ni Element Pack Pro: el constructor, las funciones avanzadas (derivadas de PRO Elements) y los widgets DIGITALÍSIMO vienen en el mismo paquete. Los documentos heredados de Element Pack siguen pasando por [DIGITALÍSIMO Migración](ELEMENT_PACK_RETIREMENT.md).

## Qué contiene y cómo funciona

- **Ubicación:** `digitalisimo-elements/elementor/` es una copia sin cambios del plugin Elementor publicado en wordpress.org. Cada archivo se comprueba contra las sumas SHA-256 oficiales al sincronizar.
- **Manifiesto:** `elementor/digitalisimo-core.json` guarda la versión, el ZIP de origen, su SHA-256, la licencia (GPL-3.0-or-later) y la fecha.
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
- **Interfaz:** el editor y la administración siguen mostrando «Elementor». Los identificadores internos (clases, ganchos, metadatos) no pueden cambiar sin romper el contenido y los complementos.

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
   - `CORE=bundled bash tests/playground/interaction.sh`: widgets en Chrome y el editor de Elementor sobre el núcleo incluido.
   - Las pruebas habituales de la publicación.
4. Publica DIGITALÍSIMO Elements como cualquier otra versión.
