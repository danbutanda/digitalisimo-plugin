<?php
/**
 * Controles del Encabezado del núcleo (`\Elementor\Widget_Heading`, `elementor/includes/widgets/heading.php`), que heredan
 * igual los títulos de tema de PRO (`theme-site-title` y los de `Title_Widget_Base`) y los propios
 * (`modules/digitalisimo-theme-elements/widgets/`). No es un mapa (`Registry` ignora `maps/_*.php`): los mapas de los
 * títulos lo suman a los suyos. Todo pasa con el mismo nombre; `tests/elements-pro-theme-titles-media.php` comprueba que la
 * lista sigue cubriendo los controles del núcleo.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'keep'   => array( 'title', 'link', 'size', 'header_size', 'align', 'blend_mode', 'title_color', 'title_hover_color', 'title_hover_color_transition_duration' ),
	'groups' => array(
		'typography'  => 'typography',
		'text_stroke' => 'text_stroke',
		'text_shadow' => 'text_shadow',
	),
	// Controles sin valor (separador).
	'ui'     => array( 'separator' ),
);
