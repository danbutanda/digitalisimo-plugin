<?php
/** Element Pack Pro 9.9.1 `bdt-table-of-content` → `table-of-contents`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'table-of-contents',
	// Clases de Element Pack más usadas en sus selectores: .bdt-toggle-button-wrapper, .bdt-toggle-button, .bdt-nav, .bdt-table-of-content-header, .bdt-table-of-content, .bdt-active, .bdt-offcanvas-bar, .bdt-card-secondary, .bdt-button-icon-align-right, .bdt-button-icon-align-left
	'classes'   => array(
		'.bdt-toggle-button-wrapper a.bdt-toggle-button' => '.elementor-toc__toggle-button',
	),
	'defaults'  => array(
		'layout' => 'offcanvas',
		'index_align' => 'left',
		'fixed_position' => 'top-left',
		'selectors' => array( 'h2', 'h3', 'h4' ),
		'fixed_index_horizontal_offset' => array(
			'size' => 0,
		),
		'fixed_index_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'fixed_index_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'fixed_index_vertical_offset' => array(
			'size' => 0,
		),
		'fixed_index_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'fixed_index_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'offset' => array(
			'size' => 10,
		),
		'button_text' => 'Table Of Index',
		'table_button_icon' => array(
			'value' => 'fas fa-book',
			'library' => 'fa-solid',
		),
		'button_icon_align' => 'right',
		'button_icon_indent' => array(
			'size' => 8,
		),
		'button_position' => 'top-left',
		'btn_horizontal_offset' => array(
			'size' => 0,
		),
		'btn_horizontal_offset_tablet' => array(
			'size' => 0,
		),
		'btn_horizontal_offset_mobile' => array(
			'size' => 0,
		),
		'btn_vertical_offset' => array(
			'size' => 0,
		),
		'btn_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'btn_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'button_rotate' => array(
			'size' => 0,
		),
		'button_rotate_tablet' => array(
			'size' => 0,
		),
		'button_rotate_mobile' => array(
			'size' => 0,
		),
		'drop_position' => 'bottom-left',
		'drop_mode' => 'hover',
		'drop_offset' => array(
			'size' => 0,
		),
		'drop_animation' => 'fade',
		'drop_duration' => array(
			'size' => 200,
		),
		'drop_show_delay' => array(
			'size' => 0,
		),
		'drop_hide_delay' => array(
			'size' => 800,
		),
		'context' => '.elementor',
		'extend_page' => 'yes',
		'toc_sticky_offset' => array(
			'size' => 0,
		),
		'toc_sub_indent' => array(
			'size' => 5,
			'unit' => 'px',
		),
	),
	// El índice flotante, desplegable o lateral de Element Pack pasa a la tabla de contenidos en línea.
	'filter'    => static function ( array $out ) {
		$tags = array_values( array_intersect( (array) ( $out['selectors'] ?? array( 'h2', 'h3', 'h4' ) ), array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ) );
		$out['headings_by_tags'] = $tags ? $tags : array( 'h2', 'h3', 'h4' );
		$out['title']            = (string) ( $out['button_text'] ?? 'Table Of Index' );
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(layout|index_align|fixed_|selectors$|offset$|button_|table_button_icon|btn_|drop_|context|auto_collapse|history|hash_navigation|extend_page|toc_)/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
