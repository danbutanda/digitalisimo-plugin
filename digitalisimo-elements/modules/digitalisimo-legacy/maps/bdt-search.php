<?php
/** Element Pack Pro 9.9.1 `bdt-search` → `search-form`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'search-form',
	// Clases de Element Pack más usadas en sus selectores: .bdt-search, .bdt-search-result, .bdt-search-input, .bdt-search-container, .bdt-search-button, .bdt-modal-close-full, .bdt-search-toggle, .bdt-search-result-header, .bdt-list, .bdt-search-item, .bdt-search-icon, .bdt-search-title, .bdt-search-text, .bdt-search-result-close-btn
	'classes'   => array(
		'.bdt-search .bdt-search-button' => '.elementor-search-form__submit',
		'.bdt-search-input'              => '.elementor-search-form__input',
		'.bdt-search-toggle'             => '.elementor-search-form__toggle',
		'.bdt-search'                    => '.elementor-search-form',
	),
	'defaults'  => array(
		'skin' => 'default',
		'search_query' => 'post',
		'placeholder' => 'Search...',
		'search_icon' => 'yes',
		'search_toggle_icon' => array(
			'value' => 'fas fa-search',
			'library' => 'fa-solid',
		),
		'dropbar_position' => 'bottom-left',
		'dropbar_offset' => array(
			'size' => 0,
		),
		'anchor_target' => 'yes',
		'button_text' => 'Submit',
		'border_radius' => array(
			'size' => 3,
			'unit' => 'px',
		),
	),
	// Buscador en línea, en desplegable o en ventana pasan a las presentaciones del formulario propio.
	'rename'    => array( 'skin' => 'skin' ),
	'values'    => array( 'skin' => array( 'default' => 'classic', 'dropbar' => 'minimal', 'modal' => 'full_screen' ) ),
	'filter'    => static function ( array $out ) {
		$out['button_type'] = 'yes' === ( $out['search_button'] ?? '' ) && '' !== (string) ( $out['button_text'] ?? '' ) ? 'text' : 'icon';
		foreach ( array( 'search_query', 'search_icon', 'search_icon_flip', 'search_toggle_icon', 'dropbar_position', 'dropbar_offset', 'show_ajax_search', 'element_connect', 'element_selector', 'anchor_target', 'search_button', 'button_icon', 'ajax_item_limit' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
