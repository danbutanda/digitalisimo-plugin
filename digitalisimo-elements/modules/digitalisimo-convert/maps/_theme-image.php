<?php
/**
 * Controles de la Imagen del núcleo (`\Elementor\Widget_Image`, `elementor/includes/widgets/image.php`), que heredan igual
 * el Logotipo del sitio y la Imagen destacada de PRO y los propios (`modules/digitalisimo-theme-elements/widgets/`). No es
 * un mapa (`Registry` ignora `maps/_*.php`): los dos mapas lo suman a los suyos. Todo pasa con el mismo nombre; el grupo
 * `image` lleva `image_size`, `image_custom_dimension` y las claves `image_border_*`, `image_box_shadow_*` e
 * `image_border_radius`. `tests/elements-pro-theme-titles-media.php` comprueba que la lista sigue cubriendo los controles del
 * núcleo.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'keep'   => array( 'image', 'caption_source', 'caption', 'link_to', 'link', 'open_lightbox', 'align', 'width', 'space', 'height', 'object-fit', 'object-position', 'opacity', 'opacity_hover', 'background_hover_transition', 'hover_animation', 'image_border_radius', 'caption_align', 'text_color', 'caption_background_color', 'caption_space' ),
	'groups' => array(
		'image'               => 'image',
		'css_filters'         => 'css_filters',
		'css_filters_hover'   => 'css_filters_hover',
		'image_border'        => 'image_border',
		'image_box_shadow'    => 'image_box_shadow',
		'caption_typography'  => 'caption_typography',
		'caption_text_shadow' => 'caption_text_shadow',
	),
	// Controles sin valor (separador del panel).
	'ui'     => array( 'separator_panel_style' ),
);
