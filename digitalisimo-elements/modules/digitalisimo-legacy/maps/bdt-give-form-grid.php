<?php
/** Element Pack Pro 9.9.1 `bdt-give-form-grid` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-give-form-grid' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'show_title' => 'yes',
		'show_excerpt' => 'yes',
		'excerpt_length' => 16,
		'show_goal' => 'yes',
		'show_featured_image' => 'yes',
		'show_pagination' => 'yes',
		'forms_per_page' => '6',
		'columns' => '3',
		'columns_tablet' => '2',
		'columns_mobile' => '1',
		'items_gap' => array(
			'size' => 20,
		),
		'items_row_gap' => array(
			'size' => 20,
		),
		'orderby' => 'date',
		'order' => 'DESC',
		'pagi_alignment' => 'center',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(),
	'filter'    => static function ( array $out ) {
		$s = $out;
		$shortcode = Digitalisimo\Elements\Legacy\Translator::shortcode( 'give_form_grid', array( 'forms_per_page' => ( $s['forms_per_page'] ?? '' ), 'orderby' => ( $s['orderby'] ?? '' ), 'order' => ( $s['order'] ?? '' ), 'show_title' => ( $s['show_title'] ?? '' ), 'show_goal' => ( $s['show_goal'] ?? '' ), 'show_excerpt' => ( $s['show_excerpt'] ?? '' ), 'excerpt_length' => ( $s['excerpt_length'] ?? '' ), 'show_featured_image' => ( $s['show_featured_image'] ?? '' ), 'display_style' => 'modal_reveal' ) );
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'show_title', 'show_excerpt', 'excerpt_length', 'show_goal', 'show_featured_image', 'show_pagination', 'forms_per_page', 'orderby', 'order' ) );
	},
);
