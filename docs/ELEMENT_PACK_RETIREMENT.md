# Retirar Element Pack Pro de una red con DIGITALÍSIMO Elements

Guía operativa para desactivar y desinstalar Element Pack Pro sin que cambie lo que ven los visitantes. Complementa el [objetivo de migración](ELEMENT_PACK_MIGRATION_OBJECTIVE.md) (qué se adaptó y cómo se validó) y la [tabla de decisiones](element-pack-decisions.md) (qué hace cada widget sin Element Pack).

## Requisitos

- DIGITALÍSIMO Elements 4.3.0.129 o posterior activo en la red (o en cada sitio que use Element Pack).
- Los plugins de los que dependen los widgets siguen activos: WooCommerce, Secure Custom Fields o ACF, bbPress, BdThemes Testimonials, Give, Charitable, EDD, FooEvents, los formularios de terceros, etc. Los adaptadores reproducen el widget de Element Pack, no el plugin que le daba los datos.
- Los widgets de PRO Elements que sirven de destino no están desactivados en el sitio (Elementor → Ajustes → Funciones). Si uno está desactivado, su adaptador no se registra y la herramienta lo avisa.

## 1. Inventario de la red

En **Red → Ajustes → Migrar Element Pack** (o `wp digitalisimo-elements ep-migrate --network`) se ve, por sitio, cuántos elementos son compatibles, qué widgets no tienen adaptador y cuántos documentos quedan por traducir. Esa pantalla sólo informa: no modifica ningún sitio.

En **Herramientas → Migrar Element Pack** de cada sitio aparece el motivo y la alternativa de cada widget sin adaptador (generados desde `docs/migration-map.json`).

## 2. Resolver los widgets sin adaptador

Un elemento sin adaptador deja de mostrarse al desactivar Element Pack. Antes de seguir, en cada página que lo use:

- Sustituirlo por la alternativa que indica la tabla (bloques o shortcodes del plugin de origen, un widget propio o de PRO Elements), o
- Aceptar que desaparezca (criptomonedas, clima, redes sociales con API retirada, controles remotos de carruseles de Element Pack…).

La herramienta del sitio lista exactamente en qué documentos está cada uno.

## 3. Traducir los documentos antes de desinstalar

Con Element Pack **todavía instalado** (activo o no), ejecutar en cada sitio **Traducir el siguiente lote** hasta que no queden documentos pendientes, o `wp digitalisimo-elements ep-migrate --convert --url=https://sitio/`. La traducción:

- Guarda una copia del `_elementor_data` original de cada documento (**Restaurar** la devuelve tal cual).
- Fija en el documento los valores que Element Pack guardaba en sus ajustes y que algunos adaptadores leen al traducir: el destinatario del formulario de contacto, la clave y la audiencia de Mailchimp, la clave de Google Maps. Element Pack no borra sus opciones al desinstalarse (versión 9.9.1 de referencia), pero traducir antes evita depender de ellas; sin traducir, el documento tendría que configurarse a mano si esas opciones desaparecieran.
- Deja cada elemento en el widget propio si todos sus ajustes existen allí, o en su adaptador si conserva estilos de Element Pack. Los widgets ACF y los que necesitan controles propios se quedan siempre en su adaptador: siguen leyendo ACF en cada visita.

Las reglas de visibilidad heredadas (`class-legacy-rules.php`) respetan la opción `element_pack_elementor_extend` del sitio, que Element Pack conserva al desactivarse; no hace falta tocarla.

## 4. Desactivar Element Pack y comprobar

Desactivar Element Pack en la red (o en cada sitio). Los adaptadores sólo se registran cuando Element Pack no está cargado, así que el cambio es inmediato. Comprobar:

- Las páginas con más tráfico que usaban widgets `bdt-*`, en escritorio y móvil.
- El editor de Elementor sobre alguna de ellas: abrir y guardar no cambia el documento ni convierte el widget (validado en el A/B con `ajax_save()`).
- Los formularios heredados (contacto, webhook, Mailchimp) con un envío real.
- Si algo se ve mal, reactivar Element Pack devuelve el comportamiento anterior sin perder nada, y **Restaurar** deshace la traducción.

## 5. Desinstalar

Cuando todos los sitios estén traducidos y revisados, desinstalar Element Pack. La carpeta `digitalisimo-elements/bdthemes-element-pack/` del repositorio es sólo referencia de análisis: no se instala, no se ejecuta y no viaja en el ZIP.

## Validación disponible en el repositorio

- `bash tests/playground/legacy-ab.sh` (con `FAKE_PLUGINS=1 EXTRA_PLUGINS=woocommerce,secure-custom-fields`, y `EXTRA_PLUGINS=bbpress` aparte): compara cada widget con Element Pack activo y sin él, en el sitio principal y un subsitio, incluido el guardado desde el editor, la traducción y su reversión.
- `bash tests/playground/interaction.sh`: once widgets con JavaScript en Chrome real.
- `bash tests/playground/form-submit.sh`: envío real de los formularios heredados.
- `bash tests/playground/migration-report.sh`: pantallas de red y de sitio de la herramienta.
