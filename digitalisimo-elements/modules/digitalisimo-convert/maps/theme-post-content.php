<?php
/**
 * Post Content de PRO → Contenido de la entrada (`digitalisimo-post-content`,
 * modules/digitalisimo-theme-elements/widgets/class-post-content.php).
 *
 * El Contenido de la entrada repite los controles del de PRO (alineación, color y tipografía) con el mismo nombre, valor
 * por defecto y selectores, así que todo pasa con `keep`. Su envoltorio conserva la clase
 * `elementor-widget-theme-post-content` además de la propia (la buscan el Indicador de progreso y la regla del núcleo de
 * las figuras), así que el CSS personalizado del elemento no necesita cambios. Formato: cabecera de
 * `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'   => 'theme-post-content',
	'target'   => 'digitalisimo-post-content',
	'status'   => 'draft',
	'panel'    => 'hide',
	'keep'     => array( 'align', 'text_color' ),
	'groups'   => array(
		'typography' => 'typography',
	),
);
