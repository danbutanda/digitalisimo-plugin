<?php
/**
 * Theme functions and definitions (plantilla oficial del hijo de Hello, con funciones con nombre) más PHP que no se
 * puede trasladar sin ejecutarlo: salida propia en la cabecera y el pie y un shortcode, como muchos hijos reales.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );

function hello_elementor_child_scripts_styles() {
	wp_enqueue_style( 'hello-elementor-child-style', get_stylesheet_directory_uri() . '/style.css', [ 'hello-elementor-theme-style' ], HELLO_ELEMENTOR_CHILD_VERSION );
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );

function tp_child_php_head() {
	echo '<style id="tp-child-php-head">.tp-php-head{color:rgb(150,20,150)}</style>';
}
add_action( 'wp_head', 'tp_child_php_head' );

function tp_child_php_foot() {
	echo '<script id="tp-child-php-foot">document.documentElement.classList.add("tp-php-foot");</script>';
}
add_action( 'wp_footer', 'tp_child_php_foot' );

function tp_child_php_shortcode() {
	return '<span class="tp-php-head">Contenido del shortcode del hijo</span>';
}
add_shortcode( 'tp_child_sc', 'tp_child_php_shortcode' );
