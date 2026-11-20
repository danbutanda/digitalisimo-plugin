<?php
/**
 * Theme functions and definitions (plantilla del hijo de Hello, como la oficial, más una hoja y una fuente propias).
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );

/**
 * Load child theme scripts & styles.
 *
 * @return void
 */
function hello_elementor_child_scripts_styles() {

	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'hello-elementor-theme-style',
		],
		HELLO_ELEMENTOR_CHILD_VERSION
	);

	wp_enqueue_style( 'tp-child-fonts', get_stylesheet_directory_uri() . '/fonts/fonts.css', array(), HELLO_ELEMENTOR_CHILD_VERSION );
	wp_enqueue_style( 'tp-child-extra', get_stylesheet_directory_uri() . '/assets/extra.css', array( 'hello-elementor-child-style' ), HELLO_ELEMENTOR_CHILD_VERSION );
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );
