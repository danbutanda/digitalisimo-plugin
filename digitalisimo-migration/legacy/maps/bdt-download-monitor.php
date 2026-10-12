<?php
/** Element Pack Pro 9.9.1 `bdt-download-monitor` → `shortcode` de Elementor (el shortcode del plugin que mostraba). */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'shortcode',
	'classes'   => array(
		'.bdt-dm-count' => '.elementor-shortcode',
		'.bdt-dm-description' => '.elementor-shortcode',
		'.bdt-dm-file' => '.elementor-shortcode',
		'.bdt-dm-meta' => '.elementor-shortcode',
		'.bdt-dm-size' => '.elementor-shortcode',
		'.bdt-dm-title' => '.elementor-shortcode',
	),
	'defaults'  => array(
		'file_type_show' => 'yes',
		'file_size_show' => 'yes',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
	),
	// Ajustes que Elementor anula cuando su control no cumple la condición.
	'conditions' => array(
		'file_type_show' => array(
			'file_id!' => '',
		),
		'file_size_show' => array(
			'file_id!' => '',
		),
		'download_count_show' => array(
			'file_id!' => '',
		),
		'icon_align' => array(
			'download_monitor_icon[value]!' => '',
		),
	),
	'filter'    => static function ( array $out ) {
		$s = $out;
		// Sin el ajuste obligatorio Element Pack mostraba un aviso; aquí no se muestra nada.
		$shortcode = ! empty( $s['file_id'] ) ? Digitalisimo\Elements\Legacy\Translator::shortcode( 'download', array( 'id' => ( $s['file_id'] ?? '' ) ) ) : '';
		return Digitalisimo\Elements\Legacy\Translator::as_shortcode( $out, $shortcode, array( 'file_id', 'file_type_show', 'file_size_show', 'download_count_show', 'alt_title', 'open_new_tab', 'download_monitor_icon', 'icon_align', 'button_hover_animation' ) );
	},
);
