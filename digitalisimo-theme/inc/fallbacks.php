<?php
/**
 * DIGITALÍSIMO: respaldos mientras DIGITALÍSIMO Elements no está activo. Las páginas con las plantillas del
 * constructor («lienzo» y «ancho completo») se siguen viendo, y Temas y Plugins avisan de que falta el constructor.
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'theme_templates', 'digitalisimo_theme_fallback_templates', 10, 3 );
add_filter( 'template_include', 'digitalisimo_theme_fallback_template', 11 );
add_action( 'admin_notices', 'digitalisimo_theme_fallback_notice' );

/** Plantillas de página del constructor que la página ya usa: se conservan en el selector y al guardar. */
function digitalisimo_theme_fallback_templates( $templates, $theme = null, $post = null ) {
	if ( did_action( 'elementor/loaded' ) || ! $post ) {
		return $templates;
	}
	if ( in_array( get_page_template_slug( $post ), array( 'elementor_canvas', 'elementor_header_footer' ), true ) ) {
		$templates                            = (array) $templates;
		$templates['elementor_canvas']        = __( 'Canvas', 'digitalisimo-theme' );
		$templates['elementor_header_footer'] = __( 'Full width', 'digitalisimo-theme' );
	}
	return $templates;
}

/** Sin constructor, esas plantillas usan los respaldos del tema. */
function digitalisimo_theme_fallback_template( $template ) {
	if ( did_action( 'elementor/loaded' ) || ! is_singular() ) {
		return $template;
	}
	$map  = array(
		'elementor_canvas'        => 'canvas',
		'elementor_header_footer' => 'full-width',
	);
	$slug = (string) get_page_template_slug( get_queried_object_id() );
	if ( isset( $map[ $slug ] ) ) {
		$file = get_template_directory() . '/fallback/' . $map[ $slug ] . '.php';
		if ( is_readable( $file ) ) {
			return $file;
		}
	}
	return $template;
}

/** Aviso en Temas y Plugins mientras falta el constructor. */
function digitalisimo_theme_fallback_notice() {
	if ( did_action( 'elementor/loaded' ) || ! current_user_can( 'activate_plugins' ) || ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'themes', 'plugins' ), true ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'The DIGITALÍSIMO theme shows simple fallbacks until DIGITALÍSIMO Elements is active.', 'digitalisimo-theme' ) . '</p></div>';
}
