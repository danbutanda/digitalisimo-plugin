<?php
/**
 * Códigos del informe de los widgets de PRO (`pro.`, docs/PRO_WIDGETS_OBJECTIVE.md): la conversión de cada widget de
 * PRO con mapa listo a su widget propio de DIGITALÍSIMO Elements (Pro). Formato en Report::catalog(). Sólo `review`
 * deja el sitio «con asuntos por revisar».
 *
 * `pro.dropped`, `pro.approx`, `pro.unmapped` y `pro.css` los da el traductor de Elements (`Convert\Translator`, con la
 * misma sección) y llevan `widget`, `target`, `name` (la clave del ajuste), `note` (el motivo declarado en el mapa) y
 * `count`; `pro.converted`, `pro.target_missing`, `pro.global` y `pro.global_missing` (widget global, C9) los escribe Pro. Los escriben la tarea y el análisis previo.
 * `pro.css_external` (con `count` en un documento, o `name` = `kit`/`customizer` en el sitio) y `pro.css_reverted` (al
 * revertir) los escribe Pro con el CSS que nombra los widgets de PRO desde fuera.
 *
 * `pro.inert` (ajustes que no son del widget de PRO y pasan tal cual) y `pro.condition_kept` (condición encendida de otro
 * plugin: el elemento se queda como widget de PRO) también los da el traductor. `pro.target_dependency` (el widget propio
 * sólo se registra con otro plugin, `name`) y los de la recuperación de lo que una versión anterior no pasó
 * (`pro.recovered`, `pro.recover_skipped`, `pro.condition_restored`, `pro.condition_lost`) los escribe Pro.
 */
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

return array(
	'pro.converted'      => array( 'section' => 'converted', 'label' => __( 'Widget de PRO convertido a su widget propio de DIGITALÍSIMO Elements, con el mismo contenido, enlaces e imágenes.', 'digitalisimo-migration' ) ),
	'pro.css'            => array( 'section' => 'converted', 'label' => __( 'Las clases del widget de PRO en el CSS personalizado del elemento se pasaron a las del widget propio.', 'digitalisimo-migration' ) ),
	'pro.css_external'   => array( 'section' => 'converted', 'label' => __( 'CSS de contenedores, de la página, del kit o del Personalizador que nombra widgets de PRO convertidos: cada regla lleva ahora también su copia con las clases del widget propio (la original se conserva).', 'digitalisimo-migration' ) ),
	'pro.css_reverted'   => array( 'section' => 'converted', 'label' => __( 'CSS restaurado al revertir: sin las copias con las clases de los widgets propios.', 'digitalisimo-migration' ) ),
	'pro.approx'         => array( 'section' => 'approx', 'label' => __( 'Ajuste del widget de PRO traducido de forma aproximada en el widget propio: revisa que se vea como esperas.', 'digitalisimo-migration' ) ),
	'pro.dropped'        => array( 'section' => 'dropped', 'label' => __( 'Ajuste del widget de PRO sin equivalente en el widget propio: se descartó por el motivo indicado.', 'digitalisimo-migration' ) ),
	'pro.unmapped'       => array( 'section' => 'review', 'label' => __( 'Ajuste guardado del widget de PRO que la conversión no reconoce: no pasó al widget propio. Revisa el elemento en el editor.', 'digitalisimo-migration' ) ),
	'pro.inert'          => array( 'section' => 'converted', 'label' => __( 'Ajuste guardado que no es del widget de PRO (de otro plugin, de un contenedor o de una versión antigua) y que no cambiaba nada de lo que se ve: pasa tal cual al widget propio, por el motivo indicado.', 'digitalisimo-migration' ) ),
	'pro.condition_kept' => array( 'section' => 'review', 'label' => __( 'Condición de visualización de otro plugin encendida que el widget propio no sabe aplicar: este elemento no se convirtió y sigue siendo el widget de PRO, que se ve y se oculta igual que antes. Si quieres el widget propio, rehaz la condición con las de DIGITALÍSIMO Elements.', 'digitalisimo-migration' ) ),
	'pro.recovered'      => array( 'section' => 'converted', 'label' => __( 'Ajuste que una versión anterior de la migración no pasó al widget propio, recuperado de la copia guardada antes de convertir. Sólo se añadió donde faltaba: nada editado después cambió.', 'digitalisimo-migration' ) ),
	'pro.recover_skipped' => array( 'section' => 'dropped', 'label' => __( 'Ajuste de una fila que una versión anterior no pasó y que no se recuperó porque esa fila ya no está (se borró o se rehízo después). No cambiaba nada de lo que se ve.', 'digitalisimo-migration' ) ),
	'pro.condition_restored' => array( 'section' => 'converted', 'label' => __( 'Condición de visualización de otro plugin encendida que una versión anterior perdió al convertir: el elemento vuelve a ser el widget de PRO de la copia guardada, así que se oculta otra vez donde se ocultaba. El documento no se había editado desde la migración.', 'digitalisimo-migration' ) ),
	'pro.condition_lost' => array( 'section' => 'review', 'label' => __( 'Condición de visualización de otro plugin encendida que una versión anterior perdió al convertir, en un elemento editado después: no se recuperó para no pisar lo editado, así que puede verse donde antes se ocultaba. Revisa el elemento en el editor y vuelve a poner su condición.', 'digitalisimo-migration' ) ),
	'pro.target_dependency' => array( 'section' => 'review', 'label' => __( 'El widget propio que sustituye a este widget de PRO sólo se registra con el plugin indicado, que no está activo en este sitio: el documento no se convirtió y se queda como estaba (sin ese plugin el widget de PRO tampoco se pinta). No hace falta actualizar nada: si activas el plugin, vuelve a pulsar Migrar.', 'digitalisimo-migration' ) ),
	'pro.global'         => array( 'section' => 'converted', 'label' => __( 'Instancia del widget global pasada al widget global propio: sigue vinculada a su plantilla, así que editar la plantilla cambia todas las páginas que la usan (con una versión de DIGITALÍSIMO Elements sin él, se sustituye por el elemento guardado en su plantilla). La plantilla se conserva en la biblioteca.', 'digitalisimo-migration' ) ),
	'pro.global_missing' => array( 'section' => 'review', 'label' => __( 'Instancia del widget global cuya plantilla no existe, está en la papelera o no guarda un widget: se quedó como estaba (tampoco se pintaba). Bórrala o elige otra plantilla en el editor.', 'digitalisimo-migration' ) ),
	'pro.target_missing' => array( 'section' => 'review', 'label' => __( 'El widget propio que sustituye a este widget de PRO no está registrado en este sitio: el documento no se convirtió y se sigue viendo igual. Actualiza DIGITALÍSIMO Elements y vuelve a pulsar Migrar.', 'digitalisimo-migration' ) ),
);
