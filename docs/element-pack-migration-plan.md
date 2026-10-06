# Reconstrucción funcional de widgets de Element Pack

El inventario se genera con `python3 scripts/inventory-element-pack.py` desde una copia local de referencia. Se identificaron 263 IDs Elementor y dos archivos auxiliares sin `get_name()`. La inspección estática no ejecuta ni instala Element Pack. Sus resultados viven en `element-pack-inventory.json`, `element-pack-framework-inventory.json`, `element-pack-dependency-graph.json` y `migration-map.json`. Las categorías y prioridades son hipótesis que requieren revisión funcional.

## Decisión arquitectónica

`Widget → Module Registry → Dependencies → Asset Manager`. El registro debe ser propio de DIGITALÍSIMO Elements, con clases y controles propios, y ningún `require` o autoload del directorio de referencia. Cada widget declara los handles concretos que utiliza. Los recursos se registran de forma reutilizable, pero sólo se encolan cuando Elementor renderiza el widget. Las dependencias propias del editor pueden diferir de las del frontend. UIkit no se carga globalmente; una función concreta de UIkit sólo se reemplaza tras identificar su uso real.

Los nueve grupos actuales del inventario son un punto de partida, no la cifra final de motores. Se prioriza un motor de carrusel compartido para slides y carouseles, el motor de consultas y formularios existente en Elements para widgets compatibles, y componentes pequeños de interacción y medios. No se crean bases con herencia profunda. Los widgets de terceros sólo se registran cuando su plugin requerido está activo en el sitio actual, incluida la activación de red.

## Compatibilidad y migración de páginas

1. Separar el ID nuevo `digitalisimo-*` del adaptador `bdt-*`. Un adaptador legacy sólo se ofrece para un widget ya reconstruido y verificado.
2. Si el Element Pack original está activo, no registrar el mismo ID legacy; ofrecer un diagnóstico de conflicto. No cargar su bootstrap ni llamar su sistema de licencias.
3. Para migrar contenido permanente, convertir configuración de `_elementor_data` mediante un mapa explícito de controles, repeaters, valores responsivos y skins; conservar copia de seguridad, operar por sitio y permitir vista previa y reversión. Un mapeo vacío en `migration-map.json` significa que el widget sigue pendiente.
4. Una coincidencia de nombre con un widget de PRO Elements sólo señala posible duplicación. Deben compararse controles, contenido y salida antes de compartir o descartar. El inventario detecta 14 coincidencias exactas de nombres sin el prefijo `bdt-`; `post-content` y `post-title` requieren revisión adicional.

## Orden técnico de migración

1. Probar primero un widget de contenido simple para validar registro, estilos condicionales, editor, sitio individual y Multisite.
2. Extraer el motor compartido de carrusel a partir de nuestro Slider Optimizado y un widget de referencia como `bdt-testimonial-slider`. Mantener Swiper sólo donde esté justificado por tamaño y comportamiento. Preservar el Slider Optimizado existente.
3. Migrar variantes de carrusel como adaptadores ligeros de datos, plantilla, controles y configuración. No duplicar inicialización ni lógica responsiva.
4. Migrar consultas y componentes de contenido sobre las capacidades existentes de PRO Elements. Evitar motores paralelos de Forms, WooCommerce, Theme Builder y Loop cuando cubran el caso.
5. Incorporar integraciones de terceros de forma opcional. Cada endpoint AJAX/REST, carga de archivo y consulta remota necesita auditoría de autorización, nonce cuando aplique, validación, sanitización y escape.
6. Resolver widgets duplicados, obsoletos y de bajo valor mediante decisión documentada `RECONSTRUIR`, `COMPARTIR`, `ADAPTAR`, `MIGRAR` o `DESCARTAR`. No se deduce esa decisión únicamente del nombre.

## Criterio de aceptación por widget

Una ficha pasa de `pending` a `complete` sólo con: licencia y procedencia verificadas; controles y configuraciones responsivas probadas; salida semántica y accesible; recursos frontend condicionales; ninguna clase o recurso original como dependencia; editor y frontend probados; casos de ausencia de datos y errores; activación individual y de red; compatibilidad con plugin original; equivalencia visual por capturas escritorio/tableta/móvil; y medidas comparables de nodos DOM, KB de CSS/JS transferidos y cargados, solicitudes, CLS y tiempo de render. La meta es equivalencia funcional y visual con menor costo medido, no HTML idéntico.

El plugin SEO puede aplicar optimizaciones adicionales al frontend, pero cada widget debe conservar por sí mismo una arquitectura eficiente y salida válida. No se difieren estilos o scripts necesarios para el primer viewport sólo para mejorar una métrica aislada.

## Primer piloto: Enlace animado

`digitalisimo-animated-link` está implementado con controles de Elementor y un único CSS de 15 variantes. No carga JS propio ni UIkit. Los nombres de nueve controles coinciden con los del widget de referencia. `bdt-animated-link` se registra como alias de lectura solamente cuando Element Pack no está activo y nadie más registró ese ID. Conserva los selectores antiguos de la página. La conversión permanente de `_elementor_data` requiere una prueba visual en Elementor antes de habilitarse. `migration-map.json` documenta el destino y mantiene ese estado pendiente de verificación.

## Segundo piloto: Lista destacada

`digitalisimo-fancy-list` reconstruye el contenido principal de Fancy List mediante un repetidor de Elementor con texto, imagen, icono y enlace. Las tres presentaciones usan una sola hoja específica, sin JavaScript ni UIkit. Las columnas, la separación y varios estilos de tarjeta, texto e imagen tienen controles responsivos o nativos; la vista previa del editor refleja el HTML del frontend. Todavía faltan controles avanzados del original y equivalencia visual verificada; por eso **no** se registra `bdt-fancy-list` ni se convierten documentos existentes. La prueba automática cubre escape de texto, etiqueta segura, ALT de imagen, carga diferida, elementos vacíos, numeración y registro de controles.

## Tercer piloto: Visor de documentos

`digitalisimo-document-viewer` usa un iframe con carga diferida, un enlace alternativo y una hoja CSS pequeña. Acepta sólo URL HTTP(S), comprueba el host exacto antes de aplicar el modo de vista previa de Google Docs y evita enviar direcciones locales o privadas a Google: en ese caso utiliza el visor nativo del navegador. El uso de Google Docs para archivos públicos exige una elección explícita. No carga JavaScript ni UIkit. `bdt-document-viewer` no se registra hasta comprobar el comportamiento y aspecto en WordPress real.

## Cuarto piloto: Carrusel de marcas

`digitalisimo-brand-carousel` usa un motor de desplazamiento nativo con CSS scroll snap y un pequeño controlador de navegación compartible. Los controles permiten repetir logos, nombres y enlaces; las columnas y separaciones son responsivas. Los recursos se cargan únicamente cuando Elementor utiliza este widget. La vista previa reproduce la estructura del frontend. Se conserva el Slider Optimizado existente. El ID `bdt-brand-carousel` permanece libre porque todavía faltan los controles avanzados, la equivalencia visual y las pruebas reales en Elementor, sitio individual y Multisite.

## Quinto piloto: Carrusel de logotipos

`digitalisimo-logo-carousel` reutiliza el motor anterior y añade selección múltiple de la biblioteca o un repetidor con enlaces individuales. Toma el ALT del adjunto y admite un nombre accesible; un enlace sin nombre accesible se presenta como imagen sin enlace. El CSS propio es pequeño y condicional. No registra `bdt-logo-carousel`, cuyo autoplay, tooltips, máscaras y estilos avanzados requieren evaluación separada antes de una migración compatible.
