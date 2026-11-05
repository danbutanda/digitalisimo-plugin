<?php
/**
 * Template de PRO (`modules/library/widgets/template.php`) → Plantilla (`digitalisimo-template`,
 * modules/digitalisimo-services/widgets/class-template.php).
 *
 * Un solo control con el mismo nombre (`template_id`), así que el mapa es `keep`. Las sobrescrituras de F7b
 * (`digitalisimo_overrides`, con sus filas) son un rasgo propio y pasan sin cambios: `F7b\Overrides` reconoce la
 * Plantilla propia como al widget de PRO. El envoltorio del contenido es propio (`digi-template`). Formato: cabecera de
 * `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'  => 'template',
	'target'  => 'digitalisimo-template',
	'status'  => 'ready',
	'panel'   => 'hide',
	'keep'    => array( 'template_id' ),
	'classes' => array(
		'.elementor-widget-template' => '.elementor-widget-digitalisimo-template',
		'.elementor-template'        => '.digi-template',
	),
);
