<?php
/** DIGITALÍSIMO: soportes del tema, menús, ancho de contenido y traducciones. */
defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'digitalisimo_theme_content_width', 0 );
add_action( 'after_setup_theme', 'digitalisimo_theme_setup', 10 );
add_filter( 'load_textdomain_mofile', 'digitalisimo_theme_mofile', 10, 2 );

/** Ancho del contenido (800 px, como el tema del que venían los sitios). */
function digitalisimo_theme_content_width() {
	$GLOBALS['content_width'] = (int) apply_filters( 'digitalisimo_theme_content_width', digitalisimo_theme_hello_filter( 'hello_elementor_content_width', 800 ) );
}

/** Soportes: los mismos del tema anterior, para que el contenido y WooCommerce se comporten igual. */
function digitalisimo_theme_setup() {
	load_theme_textdomain( 'digitalisimo-theme', get_template_directory() . '/languages' );
	// Las mismas ubicaciones que el tema anterior: WordPress traslada los menús por nombre (_wp_menus_changed).
	register_nav_menus(
		array(
			'menu-1' => esc_html__( 'Header', 'digitalisimo-theme' ),
			'menu-2' => esc_html__( 'Footer', 'digitalisimo-theme' ),
		)
	);
	add_post_type_support( 'page', 'excerpt' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'script', 'style', 'navigation-widgets' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 100,
			'width'       => 350,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/base.css' );
	if ( apply_filters( 'digitalisimo_theme_woocommerce_support', true ) ) {
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
	}
}

/** Una variante del español sin archivo propio (es_AR, es_CO…) usa la traducción de México. */
function digitalisimo_theme_mofile( $mofile, $domain ) {
	if ( 'digitalisimo-theme' !== $domain ) {
		return $mofile;
	}
	$dir    = trailingslashit( get_template_directory() ) . 'languages/';
	$locale = basename( (string) $mofile, '.mo' );
	if ( 0 !== strpos( (string) $mofile, $dir ) || 0 !== strpos( $locale, 'es_' ) || 'es_MX' === $locale ) {
		return $mofile;
	}
	if ( is_readable( $mofile ) || is_readable( $dir . $locale . '.l10n.php' ) ) {
		return $mofile;
	}
	return $dir . 'es_MX.mo'; // WordPress prueba antes es_MX.l10n.php.
}
