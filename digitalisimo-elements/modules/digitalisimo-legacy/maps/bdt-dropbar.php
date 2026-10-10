<?php
/** Element Pack Pro 9.9.1 `bdt-dropbar` → `digitalisimo-offcanvas`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-offcanvas',
	'classes'   => array( '.bdt-drop' => '.digi-offcanvas__bar', '.bdt-dropbar-button' => '.digi-offcanvas__button' ),
	'defaults'  => array(
		'source' => 'custom',
		'content' => 'A wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole heart.',
		'button_text' => 'Open Dropbar',
		'button_align' => '',
		'size' => 'sm',
		'button_icon_align' => 'right',
		'button_icon_indent' => array(
			'size' => 8,
		),
		'button_position' => '',
		'btn_horizontal_offset' => array(
			'size' => 0,
		),
		'btn_vertical_offset' => array(
			'size' => 0,
		),
		'button_rotate' => array(
			'size' => 0,
		),
		'drop_position' => 'bottom-left',
		'drop_mode' => 'hover',
		'stretch' => 'null',
		'drop_offset' => array(
			'size' => 10,
		),
		'drop_animation' => 'bdt-animation-fade',
		'drop_duration' => array(
			'size' => 200,
		),
		'drop_show_delay' => array(
			'size' => 0,
		),
		'drop_hide_delay' => array(
			'size' => 800,
		),
		'content_alignment' => 'left',
	),
	// El desplegable junto al botón pasa a una ventana centrada con el mismo contenido.
	'filter'    => static function ( array $out ) {
		$custom = 'custom' === ( $out['source'] ?? 'custom' );
		$set    = array(
			'trigger'           => 'button',
			'source'            => $custom ? 'text' : 'template',
			'template_id'       => 'elementor' === ( $out['source'] ?? '' ) ? (string) ( $out['template_id'] ?? '' ) : '',
			'content_before'    => $custom ? (string) ( $out['content'] ?? '' ) : '',
			'side'              => 'center',
			'overlay'           => '',
			'close_button'      => 'yes',
			'close_on_overlay'  => 'yes',
			'close_on_escape'   => 'yes',
			'button_size'       => (string) ( $out['size'] ?? 'sm' ),
			'button_icon_align' => 'left' === ( $out['button_icon_align'] ?? 'right' ) ? 'left' : 'right',
		);
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(source|content|template_id|anywhere_id|button_position|drop_.*|stretch|target|boundary|animate_out|hover_animation|size|button_icon_align)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
