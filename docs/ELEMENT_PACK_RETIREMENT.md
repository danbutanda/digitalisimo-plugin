# Retirar Element Pack Pro de una red con DIGITALÍSIMO Elements

Guía operativa para desactivar y desinstalar Element Pack Pro sin que cambie lo que ven los visitantes. Complementa el [objetivo de migración](ELEMENT_PACK_MIGRATION_OBJECTIVE.md) (qué se adaptó y cómo se validó) y la [tabla de decisiones](element-pack-decisions.md) (qué pasa con cada widget).

DIGITALÍSIMO Elements no contiene ni nombra nada de Element Pack. Toda la compatibilidad (adaptadores, inventario y conversión) está en **DIGITALÍSIMO Migración** (`digitalisimo-migration`), un plugin que sólo se instala mientras dura la migración.

## Requisitos

- DIGITALÍSIMO Elements activo en la red (o en cada sitio que use Element Pack) y DIGITALÍSIMO Migración activo en la red.
- Los plugins de los que dependen los widgets siguen activos: WooCommerce, Secure Custom Fields o ACF, bbPress, BdThemes Testimonials, Give, Charitable, EDD, FooEvents, los formularios de terceros, etc. La migración reproduce el widget de Element Pack, no el plugin que le daba los datos.
- Los widgets de PRO Elements que sirven de destino no están desactivados en el sitio (Elementor → Ajustes → Funciones) y el CSS personalizado de Elementor está disponible, porque los estilos que sólo existían en Element Pack pasan al CSS personalizado de cada elemento.

## 1. Inventario de la red

En **Red → Ajustes → Migrar Element Pack** (o `wp digitalisimo-migration --network`) se ve, por sitio, cuántos elementos son compatibles, qué widgets no tienen equivalente y cuántos documentos quedan por convertir. Esa pantalla sólo informa: no modifica ningún sitio.

En **Herramientas → Migrar Element Pack** de cada sitio aparece el motivo y la alternativa de cada widget sin equivalente.

## 2. Resolver los widgets sin equivalente

Un elemento sin equivalente deja de mostrarse al desactivar Element Pack. Antes de seguir, en cada página que lo use, sustitúyelo por la alternativa que indica la herramienta o acepta que desaparezca (criptomonedas, redes sociales con API retirada, controles remotos de carruseles de Element Pack…).

## 3. Desactivar Element Pack (sin desinstalarlo)

Desactívalo en la red o en cada sitio. Mientras DIGITALÍSIMO Migración esté activo, sus adaptadores pintan los widgets heredados igual que antes. No lo desinstales todavía: la conversión lee algunos ajustes que Element Pack guardaba (destinatario del formulario de contacto, clave y audiencia de Mailchimp, regla de visibilidad activada en el sitio) y copia de su carpeta las figuras de máscaras y separadores que usa el contenido.

Element Pack añade sus figuras a la máscara nativa de Elementor (`shape-1`…`shape-31`) y al separador de forma (`ep-…`), y copia esos SVG dentro de la carpeta de Elementor; el contenido sólo guarda el nombre. Al retirarlo, una máscara sin archivo oculta la imagen entera y un separador sin archivo desaparece. La herramienta de cada sitio indica cuántos elementos usan esas figuras y avisa si alguna ya no tiene archivo.

## 4. Convertir los documentos

En cada sitio, **Herramientas → Migrar Element Pack → Convertir el siguiente lote** hasta que no queden documentos pendientes, o `wp digitalisimo-migration --convert --url=https://sitio/`. La conversión, que exige Element Pack desactivado:

- Guarda una copia del `_elementor_data` original de cada documento; **Restaurar** la devuelve tal cual.
- Deja cada widget en su destino propio (DIGITALÍSIMO Elements o PRO Elements) sólo con ajustes que ese widget conoce. Los estilos que sólo tenía Element Pack pasan al CSS personalizado del elemento.
- Pasa a rasgos propios lo que el adaptador resolvía en tiempo de ejecución: categorías y etiquetas en Entradas, partes y número de productos en WooCommerce, origen ACF, diapositivas de plantilla, plantillas del Dynamic Grid y Carousel (se duplican como «Loop Item» para el Loop nativo).
- En cualquier elemento, pasa las reglas de visibilidad, las figuras decorativas y el enlace del elemento a sus ajustes propios, y los efectos de las extensiones (filtro de fondo, degradado de texto, sombra de imagen, flotación, anotación) al CSS personalizado. Los contadores de visitas y sesiones de esas reglas empiezan de cero.
- Las reglas de visibilidad se conservan siempre al convertir; si el sitio tenía la extensión Visibility Controls apagada expresamente, quedan guardadas pero desactivadas. Si un documento convertido con Migración 1.0.10 o anterior perdió sus reglas (la opción del sitio no tenía guardada la clave de la extensión), «Recuperar reglas de visibilidad perdidas» en la misma pantalla, o `wp digitalisimo-migration --repair --url=…`, las devuelve desde la copia original; también se ejecuta una vez por sitio al entrar en la administración.
- Copia (saneados) los SVG de las máscaras y separadores de Element Pack que el sitio usa a sus propios archivos (`uploads/digitalisimo-elements/`, en una red cada sitio los suyos). La máscara nativa pasa a «Personalizada» con esa copia, «Image Masking» de Caja de imagen, Carrusel de imágenes y de los widgets de Element Pack pasa al CSS personalizado del elemento, y el separador pasa al separador propio del sitio (`digi-mezquita`, `digi-navidad`…), que DIGITALÍSIMO Elements ofrece también para contenido nuevo. Si una figura ya no tiene archivo, se retira para que la imagen siga visible. La máscara de la imagen atómica del editor v4 (`e-image`) no se traslada: la imagen queda sin máscara.

Tras convertir, el documento no contiene ningún identificador de Element Pack y se pinta sólo con DIGITALÍSIMO Elements.

## 5. Comprobar y desinstalar

- Revisa las páginas con más tráfico en escritorio y móvil, el editor de Elementor sobre alguna de ellas y los formularios heredados con un envío real.
- Si algo se ve mal, **Restaurar** devuelve el documento original (que DIGITALÍSIMO Migración vuelve a pintar).
- Cuando la herramienta de cada sitio diga que ya no queda contenido heredado, desinstala **Element Pack** y **DIGITALÍSIMO Migración**. Las copias del original quedan en la base de datos (`_digitalisimo_ep_backup`) por si hicieran falta.

## Validación disponible en el repositorio

- `bash tests/playground/legacy-ab.sh` (con `FAKE_PLUGINS=1 EXTRA_PLUGINS=woocommerce,secure-custom-fields`, y `EXTRA_PLUGINS=bbpress` aparte): compara cada widget con Element Pack activo y sin él, en el sitio principal y un subsitio. Comprueba también el guardado desde el editor, la conversión, el documento convertido con DIGITALÍSIMO Migración desactivado (fase E) y la restauración.
- `bash tests/playground/interaction.sh`: dieciocho widgets con JavaScript en Chrome real (con WooCommerce instalado).
- `bash tests/playground/form-submit.sh`: envío real de los formularios heredados.
- `bash tests/playground/migration-report.sh`: pantallas de red y de sitio de la herramienta.
