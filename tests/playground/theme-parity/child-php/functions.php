<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'hello-elementor-child-style', get_stylesheet_directory_uri() . '/style.css', array( 'hello-elementor-theme-style' ), '1.0.0' );
}, 20 );

// Lo que no se puede trasladar sin ejecutar PHP: salida propia en la cabecera y el pie y un shortcode.
add_action( 'wp_head', function () {
	echo '<style id="tp-child-php-head">.tp-php-head{color:rgb(150,20,150)}</style>';
} );
add_action( 'wp_footer', function () {
	echo '<script id="tp-child-php-foot">document.documentElement.classList.add("tp-php-foot");</script>';
} );
add_shortcode( 'tp_child_sc', static function () {
	return '<span class="tp-php-head">Contenido del shortcode del hijo</span>';
} );
