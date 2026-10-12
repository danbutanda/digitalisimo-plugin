<?php
/** Element Pack Pro 9.9.1 `bdt-give-donor-wall` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-give-donor-wall' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'show_avatar' => 'yes',
		'show_name' => 'yes',
		'show_total' => 'yes',
		'show_time' => 'yes',
		'anonymous' => 'yes',
		'comment_length' => '30',
		'donors_per_page' => '6',
		'all_forms' => 'yes',
		'columns' => '3',
		'columns_tablet' => '2',
		'columns_mobile' => '1',
		'items_gap' => array(
			'size' => 20,
		),
		'orderby' => 'post_date',
		'order' => 'desc',
		'loadmore_text' => 'Load More',
		'readmore_text' => 'Read More',
		'loadmore_alignment' => 'center',
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(
		'comment_length' => array(
			'show_comments' => 'yes',
		),
		'form_id' => array(
			'all_forms' => 'no',
		),
		'readmore_text' => array(
			'show_comments' => 'yes',
		),
	),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ( ! empty( $s['form_id'] ) || 'yes' === ( $s['all_forms'] ?? '' ) ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'give_donor_wall', array( 'form_id' => ( $s['form_id'] ?? '' ), 'donors_per_page' => ( $s['donors_per_page'] ?? '' ), 'anonymous' => ( $s['anonymous'] ?? '' ), 'show_avatar' => ( $s['show_avatar'] ?? '' ), 'show_name' => ( $s['show_name'] ?? '' ), 'show_total' => ( $s['show_total'] ?? '' ), 'show_time' => ( $s['show_time'] ?? '' ), 'order' => ( $s['order'] ?? '' ), 'orderby' => ( $s['orderby'] ?? '' ), 'loadmore_text' => esc_html( (string) ( $s['loadmore_text'] ?? '' ) ), 'readmore_text' => esc_html( (string) ( $s['readmore_text'] ?? '' ) ), 'show_comments' => 'yes' === ( $s['show_comments'] ?? '' ), 'only_comments' => 'yes' === ( $s['only_comments'] ?? '' ), 'comment_length' => ( $s['comment_length'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'show_avatar', 'show_name', 'show_total', 'show_time', 'anonymous', 'only_comments', 'show_comments', 'comment_length', 'donors_per_page', 'all_forms', 'form_id', 'orderby', 'order', 'loadmore_text', 'readmore_text' ) );
	},
);
