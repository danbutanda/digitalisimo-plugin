# DIGITALÍSIMO Tools

`DIGITALÍSIMO Tools` es un plugin independiente para herramientas internas de WordPress. Incluye **Plantillas Elementor de Red** en Multisite, **Optimizar imágenes WebP** y el widget **Slider Optimizado** en sitios individuales o instalaciones sin Multisite.

## Uso

1. Activa el plugin para la red.
2. En **Administrador de la red → DIGITALÍSIMO Tools**, elige el sitio maestro.
3. Agrega una Saved Template de Elementor, asígnale un identificador global inmutable como `portfolio-coahuila-1000` y define el alcance.
4. Usa **Sincronizar ahora**. Las tareas se encolan con WP-Cron; una plantilla padre espera a que sus dependencias estén en cada sitio destino.

La edición sucede siempre en el sitio maestro. Una copia bloqueada muestra un aviso y no permite abrir el editor Elementor a administradores locales; un superadministrador conserva acceso.

## Arquitectura

- `Registry` guarda en opciones de red el UUID, maestro, destino, colección, alcance, bloqueo y último resultado.
- `Id_Mapper` marca cada copia local con `_digitalisimo_tools_network_template_uuid`, más los metadatos de procedencia. Nunca usa el título ni el ID maestro como identidad.
- `Dependency_Resolver` recorre recursivamente el JSON de `_elementor_data`. Busca claves de referencia de plantilla y referencias en shortcodes para poder añadir futuros detectores sin depender de un solo widget.
- `Sync` resuelve primero las dependencias, protege contra ciclos y reentradas, y usa `switch_to_blog()` con `try/finally` para restaurar siempre el contexto.
- `Elementor_Adapter` exporta sólo los metadatos de documento necesarios, remapea IDs internos al destino, limpia caché y llama a la API pública disponible de Elementor para limpiar CSS.

El módulo de plantillas no hace consultas, JavaScript, REST, iframes, shortcodes ni solicitudes entre sitios durante el frontend. Los medios se preservan por URL; la duplicación de medios queda fuera de esta versión.

## Slider Optimizado

En Elementor, abre la categoría **DIGITALÍSIMO** y arrastra **Slider Optimizado**. Admite imágenes múltiples con ALT y enlace, o una imagen repetida con espejo alternado. Los controles permiten animación continua o desactivada, dirección, duración, pausa, loop, cantidad visible por dispositivo, separación, altura, tamaño y carga de imágenes. Con movimiento reducido, el contenido queda visible sin animación.

En altura **Automática**, el widget usa las dimensiones reales del archivo para reservar la proporción correcta, incluso si faltan metadatos del adjunto. Si no puede determinar sus dimensiones, carga esa imagen sin diferirla para evitar un espacio provisional excesivo.

En **Estilo → Elementos visibles**, selecciona entre 1 y 9 para escritorio, tableta y móvil mediante los iconos responsive del control. Los valores iniciales son 9, 6 y 3; cada vista se puede cambiar por separado. Los sliders ya guardados conservan su configuración hasta que la edites.

En el modo de imágenes múltiples, **Seleccionar varias imágenes** abre la biblioteca para elegirlas y ordenarlas de una vez. Se usa el ALT guardado en cada adjunto. Los elementos individuales siguen disponibles para imágenes que necesiten un ALT o enlace propio; aparecen después de la selección múltiple. Los sliders guardados con elementos individuales no requieren migración.

El modo continuo usa únicamente CSS; el widget no declara scripts ni Swiper. Su CSS se registra como dependencia del widget y Elementor lo carga sólo donde se usa. No modifica el carrusel nativo ni páginas existentes.

## Imágenes WebP

La herramienta recorre adjuntos JPG y PNG del sitio actual; desde la administración de red, recorre todos los sitios. Los adjuntos que ya son WebP no se procesan. Cada conversión guarda un WebP a calidad 75, cambia el archivo principal y el tipo del adjunto, regenera los tamaños y comprueba que WordPress haya guardado los metadatos nuevos. Los JPG/PNG que una versión anterior marcó como optimizados vuelven a ser elegibles. Los errores se muestran y pueden reintentarse en otra pasada. Los archivos antiguos permanecen en el servidor para conservar enlaces directos que ya existan; las URL fijas insertadas en contenido o plantillas no cambian automáticamente.

En la Biblioteca de Medios, cada adjunto convertido por Tools muestra **Optimizada · WebP** como estado en la vista de lista y como insignia en la cuadrícula. Un WebP subido ya optimizado no lleva la marca de Tools.

## Compatibilidad y pruebas pendientes

El módulo de plantillas no ejecuta sincronizaciones si no está activo Elementor y sólo aparece en Multisite; la pantalla WebP sí está disponible en WordPress individual. La sincronización de plantillas debe validarse en una red de pruebas con la versión real de Elementor antes de activarla automáticamente: plantilla simple, datos responsive, medios, widget Template, Tabs/Nested Tabs, dependencias anidadas, borrado de una copia, cambio de título, exclusiones, bloqueo y regeneración de CSS. La conversión WebP requiere una prueba funcional en WordPress con GD o Imagick y una biblioteca de medios real.
