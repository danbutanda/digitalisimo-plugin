<?php
/**
 * DIGITALÍSIMO: hojas del tema. En paridad, las tres hojas de Hello sin cambios (con las mismas condiciones), alias
 * de sus handles para que lo que dependía de ellas siga imprimiéndose y, si Hello usaba su cabecera y pie
 * configurables, el CSS del kit en todas las páginas, como hacía Hello; fuera de paridad, sólo base.css.
 * El tema no encola JavaScript.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'digitalisimo_theme_enqueue_styles', 10 ); // El constructor encola en 20.

/** Encola las hojas del tema. */
function digitalisimo_theme_enqueue_styles() {
	$uri     = get_template_directory_uri() . '/assets/css/';
	$version = DIGITALISIMO_THEME_VERSION;
	if ( ! digitalisimo_theme_parity() ) {
		if ( apply_filters( 'digitalisimo_theme_enqueue_style', true, 'digitalisimo-theme' ) ) {
			wp_enqueue_style( 'digitalisimo-theme', $uri . 'base.css', array(), $version );
		}
		return;
	}
	$sheets = array(
		'digitalisimo-theme-parity-reset'         => array( 'parity/reset.css', ! digitalisimo_theme_hello_off( 'hello_style' ) && digitalisimo_theme_hello_filter( 'hello_elementor_enqueue_style', true ) ),
		'digitalisimo-theme-parity'               => array( 'parity/theme.css', ! digitalisimo_theme_hello_off( 'hello_theme' ) && digitalisimo_theme_hello_filter( 'hello_elementor_enqueue_theme_style', true ) ),
		'digitalisimo-theme-parity-header-footer' => array( 'parity/header-footer.css', digitalisimo_theme_show_header_footer( 'header' ) || digitalisimo_theme_show_header_footer( 'footer' ) ),
	);
	foreach ( $sheets as $handle => $sheet ) {
		if ( apply_filters( 'digitalisimo_theme_enqueue_style', (bool) $sheet[1], $handle ) ) {
			wp_enqueue_style( $handle, $uri . $sheet[0], array(), $version );
		}
	}
	// Alias sin archivo: un estilo de terceros que dependa de una hoja de Hello no se descarta (sólo se registran).
	$aliases = array(
		'hello-elementor'               => 'digitalisimo-theme-parity-reset',
		'hello-elementor-theme-style'   => 'digitalisimo-theme-parity',
		'hello-elementor-header-footer' => 'digitalisimo-theme-parity-header-footer',
	);
	foreach ( $aliases as $alias => $handle ) {
		if ( ! wp_style_is( $alias, 'registered' ) ) {
			wp_register_style( $alias, false, wp_style_is( $handle, 'enqueued' ) ? array( $handle ) : array(), $version );
		}
	}
	// Con la cabecera y el pie configurables de Hello, Hello cargaba en todas las páginas el CSS del kit (colores y
	// tipografías globales): se conserva también mientras la conversión está pendiente o con todo oculto.
	$record = digitalisimo_theme_switch_record();
	$kit    = ! empty( $record['header_footer']['experiment'] ) && ! digitalisimo_theme_hello_off( 'header_footer' ) && digitalisimo_theme_hello_filter( 'hello_elementor_header_footer', true );
	if ( apply_filters( 'digitalisimo_theme_enqueue_style', $kit, 'elementor-kit' ) && did_action( 'elementor/loaded' ) && class_exists( 'Elementor\Plugin', false ) && isset( \Elementor\Plugin::$instance->kits_manager ) && is_callable( array( \Elementor\Plugin::$instance->kits_manager, 'frontend_before_enqueue_styles' ) ) ) {
		\Elementor\Plugin::$instance->kits_manager->frontend_before_enqueue_styles();
	}
	// Con la cabecera generada desde la de Hello, su última regla (la altura mínima del contenido corto).
	$generated = $record['generated']['header'] ?? array();
	if ( is_array( $generated ) && ! empty( $generated['id'] ) && empty( $generated['retired'] ) && wp_style_is( 'digitalisimo-theme-parity', 'enqueued' ) ) {
		wp_add_inline_style( 'digitalisimo-theme-parity', '.elementor-location-header:has(>.dynamic-header)+.elementor{min-height:calc(100vh - 320px)}' );
	}
}
