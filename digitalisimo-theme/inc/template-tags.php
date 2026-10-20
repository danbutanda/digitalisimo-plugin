<?php
/**
 * DIGITALÍSIMO: piezas de plantilla (viewport, enlace de salto, cabecera y pie de respaldo, título).
 *
 * En paridad (el sitio venía de Hello) cada valor sigue el mismo orden: el valor por defecto, el ajuste que Hello
 * tenía guardado, el filtro `hello_elementor_*` que un fragmento o un plugin pudiera usar y, por último, el filtro
 * propio, que siempre tiene la última palabra. Fuera de paridad los filtros de Hello no influyen.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'digitalisimo_header', 'digitalisimo_theme_header', 10 );
add_action( 'digitalisimo_footer', 'digitalisimo_theme_footer', 10 );
foreach ( array( 'elementor/page_templates/header-footer/before_content', 'elementor/theme/before_do_single', 'elementor/theme/before_do_archive' ) as $digitalisimo_hook ) {
	add_action( $digitalisimo_hook, 'digitalisimo_theme_arm_skip_target' );
}
foreach ( array( 'elementor/page_templates/header-footer/after_content', 'elementor/theme/after_do_single', 'elementor/theme/after_do_archive' ) as $digitalisimo_hook ) {
	add_action( $digitalisimo_hook, 'digitalisimo_theme_disarm_skip_target' );
}
unset( $digitalisimo_hook );
add_filter( 'elementor/document/wrapper_attributes', 'digitalisimo_theme_skip_target_attributes' );
add_action( 'woocommerce_before_main_content', 'digitalisimo_theme_woocommerce_skip_target', 5 );

/** Puente con un filtro de Hello: sólo en paridad. */
function digitalisimo_theme_hello_filter( $hook, $value ) {
	return digitalisimo_theme_parity() ? apply_filters( $hook, $value ) : $value;
}

/** ¿Hello tenía apagada esta función en sus ajustes? Sólo cuenta en paridad. */
function digitalisimo_theme_hello_off( $key ) {
	if ( ! digitalisimo_theme_parity() ) {
		return false;
	}
	$settings = digitalisimo_theme_hello_settings();
	return ! empty( $settings[ $key ] );
}

/** `<meta name="viewport">`; un filtro que devuelva '' lo retira. */
function digitalisimo_theme_viewport() {
	$content = digitalisimo_theme_hello_filter( 'hello_elementor_viewport_content', 'width=device-width, initial-scale=1' );
	$content = (string) apply_filters( 'digitalisimo_theme_viewport', $content );
	if ( '' !== $content ) {
		echo '<meta name="viewport" content="' . esc_attr( $content ) . '">' . "\n";
	}
}

/** ¿Se pinta la cabecera (o el pie) de respaldo? */
function digitalisimo_theme_show_header_footer( $part = 'header' ) {
	$part = 'footer' === $part ? 'footer' : 'header';
	$show = ! digitalisimo_theme_hello_off( 'header_footer' );
	$show = (bool) digitalisimo_theme_hello_filter( 'hello_elementor_header_footer', $show );
	// Hello no pintaba su cabecera configurable con todo apagado (sólo con el constructor cargado). El pie sí: lo
	// imprimía vacío, y ese contenedor vacío es la plantilla que genera DIGITALÍSIMO Elements.
	if ( $show && 'header' === $part && digitalisimo_theme_parity() && did_action( 'elementor/loaded' ) ) {
		$record = digitalisimo_theme_switch_record();
		$show   = 'hidden' !== ( $record['header_footer']['header'] ?? '' );
	}
	return (bool) apply_filters( 'digitalisimo_theme_header_footer', $show, $part );
}

/** Cabecera de respaldo (gancho `digitalisimo_header`, 10): el constructor la retira si imprime su plantilla. */
function digitalisimo_theme_header() {
	if ( digitalisimo_theme_show_header_footer( 'header' ) ) {
		get_template_part( 'template-parts/header' );
	}
}

/** Pie de respaldo (gancho `digitalisimo_footer`, 10). */
function digitalisimo_theme_footer() {
	if ( digitalisimo_theme_show_header_footer( 'footer' ) ) {
		get_template_part( 'template-parts/footer' );
	}
}

/**
 * ¿Se pinta el título en los respaldos? «Ocultar título» del documento sólo se consulta en contenidos individuales
 * (Hello también lo leía en archivos, con el primer resultado).
 */
function digitalisimo_theme_show_title() {
	$show = ! digitalisimo_theme_hello_off( 'page_title' );
	if ( $show && is_singular() && did_action( 'elementor/loaded' ) && class_exists( 'Elementor\Plugin', false ) && isset( \Elementor\Plugin::$instance->documents ) ) {
		$document = \Elementor\Plugin::$instance->documents->get( get_the_ID() );
		if ( $document && 'yes' === $document->get_settings( 'hide_title' ) ) {
			$show = false;
		}
	}
	$show = (bool) digitalisimo_theme_hello_filter( 'hello_elementor_page_title', $show );
	return (bool) apply_filters( 'digitalisimo_theme_page_title', $show );
}

/** Destino del enlace de salto. */
function digitalisimo_theme_skip_link_url() {
	$url = digitalisimo_theme_hello_filter( 'hello_elementor_skip_link_url', '#content' );
	return (string) apply_filters( 'digitalisimo_theme_skip_link_url', $url );
}

/** ¿Se imprime el enlace de salto? */
function digitalisimo_theme_skip_link_enabled() {
	$enabled = ! digitalisimo_theme_hello_off( 'skip_link' );
	$enabled = (bool) digitalisimo_theme_hello_filter( 'hello_elementor_enable_skip_link', $enabled );
	return (bool) apply_filters( 'digitalisimo_theme_skip_link', $enabled );
}

/** Enlace «Saltar al contenido». */
function digitalisimo_theme_skip_link() {
	if ( ! digitalisimo_theme_skip_link_enabled() ) {
		return;
	}
	echo '<a class="skip-link screen-reader-text" href="' . esc_url( digitalisimo_theme_skip_link_url() ) . '">' . esc_html__( 'Skip to content', 'digitalisimo-theme' ) . '</a>' . "\n";
}

/* ------------------------------------------------------------------ Destino del salto sin nodos nuevos */

/** Estado de la petición: `armed` (el próximo envoltorio del constructor es el contenido) y `printed` (ya hay #content). */
function digitalisimo_theme_skip_state( $key, $value = null ) {
	static $state = array(
		'armed'   => false,
		'printed' => false,
	);
	if ( null !== $value ) {
		$state[ $key ] = (bool) $value;
	}
	return ! empty( $state[ $key ] );
}

/** Los respaldos (con `<main id="content">`) marcan que el destino ya existe. */
function digitalisimo_theme_skip_target_printed( $mark = false ) {
	if ( $mark ) {
		digitalisimo_theme_skip_state( 'printed', true );
	}
	return digitalisimo_theme_skip_state( 'printed' );
}

/** ¿Hace falta un destino #content? */
function digitalisimo_theme_skip_target_wanted() {
	return ! digitalisimo_theme_skip_target_printed() && digitalisimo_theme_skip_link_enabled() && '#content' === digitalisimo_theme_skip_link_url();
}

/** Antes del contenido del constructor (plantilla de página «Cabecera y pie», ubicaciones single y archive). */
function digitalisimo_theme_arm_skip_target() {
	digitalisimo_theme_skip_state( 'armed', digitalisimo_theme_skip_target_wanted() );
}

/** Después de ese contenido: si no hubo envoltorio, la marca no pasa al pie. */
function digitalisimo_theme_disarm_skip_target() {
	digitalisimo_theme_skip_state( 'armed', false );
}

/** El primer envoltorio tras la marca recibe id="content" si no tenía id; la marca se consume. */
function digitalisimo_theme_skip_target_attributes( $attributes ) {
	if ( ! digitalisimo_theme_skip_state( 'armed' ) ) {
		return $attributes;
	}
	digitalisimo_theme_skip_state( 'armed', false );
	if ( is_array( $attributes ) && empty( $attributes['id'] ) && ! digitalisimo_theme_skip_target_printed() ) {
		$attributes['id'] = 'content';
		digitalisimo_theme_skip_target_printed( true );
	}
	return $attributes;
}

/** WooCommerce pinta su propio `<main id="main">`: el destino va justo antes, si la petición aún no tiene uno. */
function digitalisimo_theme_woocommerce_skip_target() {
	if ( digitalisimo_theme_skip_target_wanted() ) {
		echo '<span id="content"></span>';
		digitalisimo_theme_skip_target_printed( true );
	}
}
