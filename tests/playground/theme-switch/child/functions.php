<?php
/**
 * Hijo sintético de Hello para la prueba A/B (S4). Su PHP no se carga con el tema DIGITALÍSIMO: la prueba comprueba
 * que esa pérdida se informa antes (Tools) y después (bitácora) del cambio.
 */
defined( 'ABSPATH' ) || exit;
add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'digi-hello-child', get_stylesheet_uri(), array( 'hello-elementor-theme-style' ), '1.0.0' );
		wp_enqueue_style( 'digi-hello-child-extra', get_stylesheet_directory_uri() . '/extra.css', array( 'hello-elementor-theme-style' ), '1.0.0' );
	},
	20
);
add_shortcode(
	'digi_child_sc',
	static function () {
		return '<p class="digi-child-box">Contenido del shortcode del hijo</p>';
	}
);
