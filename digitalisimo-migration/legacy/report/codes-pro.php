<?php
/**
 * Códigos del informe de los widgets de PRO (`pro.`, docs/PRO_WIDGETS_OBJECTIVE.md): la conversión de cada widget de
 * PRO con mapa listo a su widget propio de DIGITALÍSIMO Elements (Pro). Formato en Report::catalog(). Sólo `review`
 * deja el sitio «con asuntos por revisar».
 *
 * `pro.dropped`, `pro.approx`, `pro.unmapped` y `pro.css` los da el traductor de Elements (`Convert\Translator`, con la
 * misma sección) y llevan `widget`, `target`, `name` (la clave del ajuste), `note` (el motivo declarado en el mapa) y
 * `count`; `pro.converted` y `pro.target_missing` los escribe Pro. Los escriben la tarea y el análisis previo.
 */
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

return array(
	'pro.converted'      => array( 'section' => 'converted', 'label' => __( 'Widget de PRO convertido a su widget propio de DIGITALÍSIMO Elements, con el mismo contenido, enlaces e imágenes.', 'digitalisimo-migration' ) ),
	'pro.css'            => array( 'section' => 'converted', 'label' => __( 'Las clases del widget de PRO en el CSS personalizado del elemento se pasaron a las del widget propio.', 'digitalisimo-migration' ) ),
	'pro.approx'         => array( 'section' => 'approx', 'label' => __( 'Ajuste del widget de PRO traducido de forma aproximada en el widget propio: revisa que se vea como esperas.', 'digitalisimo-migration' ) ),
	'pro.dropped'        => array( 'section' => 'dropped', 'label' => __( 'Ajuste del widget de PRO sin equivalente en el widget propio: se descartó por el motivo indicado.', 'digitalisimo-migration' ) ),
	'pro.unmapped'       => array( 'section' => 'review', 'label' => __( 'Ajuste guardado del widget de PRO que la conversión no reconoce: no pasó al widget propio. Revisa el elemento en el editor.', 'digitalisimo-migration' ) ),
	'pro.target_missing' => array( 'section' => 'review', 'label' => __( 'El widget propio que sustituye a este widget de PRO no está registrado en este sitio: el documento no se convirtió y se sigue viendo igual. Actualiza DIGITALÍSIMO Elements y vuelve a pulsar Migrar.', 'digitalisimo-migration' ) ),
);
