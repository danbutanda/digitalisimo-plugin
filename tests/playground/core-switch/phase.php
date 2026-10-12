<?php
/**
 * Cambio de núcleo: las mismas páginas pintadas con el plugin Elementor (fase «elementor») y, tras borrarlo,
 * con el núcleo incluido en DIGITALÍSIMO Elements (fase «bundled»). Guarda en /wordpress/wp-content/digi-core
 * la firma (texto, enlaces, imágenes) y el CSS de cada página, y las comprobaciones de la fase. Cada sitio se
 * pinta en su propia petición (DIGI_CORE_BLOG), como una visita real: Elementor guarda en caché los documentos
 * por ID sin distinguir el sitio.
 */
// Los errores se muestran tal cual: el gestor de errores fatales de WordPress los ocultaría.
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
ini_set( 'display_errors', '1' );
error_reporting( E_ALL );
// Si el paso falla, su último error queda en digi-core para poder leerlo.
register_shutdown_function( static function () {
	$error = error_get_last();
	if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
		file_put_contents( '/wordpress/wp-content/digi-core/error-phase' . getenv( 'DIGI_CORE_PHASE' ) . '.json', json_encode( $error ) );
	}
} );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once '/wordpress/wp-content/digi-tests/legacy-signature.php';

$phase = (string) getenv( 'DIGI_CORE_PHASE' );
$only  = (int) getenv( 'DIGI_CORE_BLOG' );
$out   = '/wordpress/wp-content/digi-core/' . $phase . '-' . $only . '.json';
$cases = array(
	'heading'          => array( 'title' => 'Título de prueba', 'link' => array( 'url' => 'https://example.com/titulo' ) ),
	'text-editor'      => array( 'editor' => '<p>Texto con <a href="https://example.com/a">enlace</a>.</p>' ),
	'image'            => array( 'image' => array( 'url' => '__PLACEHOLDER__' ), 'caption_source' => 'custom', 'caption' => 'Leyenda' ),
	'button'           => array( 'text' => 'Comprar', 'link' => array( 'url' => 'https://example.com/comprar' ) ),
	'icon-list'        => array(),
	'icon-box'         => array(),
	'image-box'        => array( 'image' => array( 'url' => '__PLACEHOLDER__' ) ),
	'divider'          => array(),
	'counter'          => array( 'ending_number' => 120, 'title' => 'Clientes' ),
	'progress'         => array( 'title' => 'Avance' ),
	'testimonial'      => array( 'testimonial_content' => 'Excelente servicio.', 'testimonial_name' => 'Ana' ),
	'social-icons'     => array(),
	'video'            => array(),
	'google_maps'      => array( 'address' => 'Ciudad de México' ),
	'tabs'             => array(),
	'accordion'        => array(),
	'toggle'           => array(),
	'alert'            => array( 'alert_title' => 'Aviso', 'alert_description' => 'Detalle del aviso.' ),
	'star-rating'      => array( 'rating' => 4, 'title' => 'Valoración' ),
	'form'             => array(),
	'call-to-action'   => array(),
	'flip-box'         => array(),
	'price-table'      => array(),
	'posts'            => array(),
	'slides'           => array(),
	'animated-headline' => array(),
	'blockquote'       => array(),
	'share-buttons'    => array(),
	'digitalisimo-accordion'      => array(),
	'digitalisimo-fancy-card'     => array( 'title_text' => 'Tarjeta' ),
	'digitalisimo-business-hours' => array(),
	'digitalisimo-table'          => array(),
);

$checks = array(
	'core_bundled'   => defined( 'DIGITALISIMO_ELEMENTS_CORE' ) ? DIGITALISIMO_ELEMENTS_CORE : '',
	'elementor'      => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
	'elementor_path' => defined( 'ELEMENTOR_PATH' ) ? str_replace( WP_PLUGIN_DIR, '', ELEMENTOR_PATH ) : '',
	'pro_loaded'     => defined( 'ELEMENTOR_PRO_VERSION' ),
	'global_lang'    => array_map( 'basename', (array) glob( WP_LANG_DIR . '/plugins/elementor-es_MX.*' ) ),
	'rollback'       => apply_filters( 'elementor/settings/rollback/versions', array( '4.3.3' ) ),
);
// Un complemento que declara «Requires Plugins: elementor» debe poder activarse.
WP_Plugin_Dependencies::initialize();
$checks['addon_unmet'] = WP_Plugin_Dependencies::has_unmet_dependencies( 'digi-fake-addon/digi-fake-addon.php' );
$activated             = activate_plugin( 'digi-fake-addon/digi-fake-addon.php' );
$checks['addon_activate'] = is_wp_error( $activated ) ? $activated->get_error_message() : 'ok';
deactivate_plugins( 'digi-fake-addon/digi-fake-addon.php', true );

$result = array( 'checks' => $checks, 'pages' => array() );
foreach ( array( $only ) as $blog ) {
	switch_to_blog( $blog );
	// Traducciones de Elementor en el idioma del sitio principal (PHP y JSON de los scripts del editor).
	if ( 1 === $blog ) {
		$result['checks']['php_text'] = __( 'Edit with Elementor', 'elementor' );
		wp_register_script( 'digi-core-common', ELEMENTOR_ASSETS_URL . 'js/common.min.js', array(), ELEMENTOR_VERSION, true );
		$json = load_script_textdomain( 'digi-core-common', 'elementor' );
		$result['checks']['js_text'] = is_string( $json ) && false !== strpos( $json, '"locale_data"' ) ? 'ok' : 'sin traducción';
	}
	$result['checks'][ 'kit_' . $blog ] = (int) get_option( 'elementor_active_kit' ) > 0;
	foreach ( $cases as $widget => $settings ) {
		$slug = 'digi-core-' . $widget;
		$doc_post  = get_page_by_path( $slug );
		if ( 'elementor' === $phase && ! $doc_post ) {
			array_walk_recursive( $settings, static function ( &$value ) {
				if ( '__PLACEHOLDER__' === $value ) {
					$value = \Elementor\Utils::get_placeholder_image_src();
				}
			} );
			$id   = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ), true );
			if ( is_wp_error( $id ) || ! $id ) {
				$result['pages'][ $blog ][ $widget ] = array( 'error' => 'wp_insert_post: ' . ( is_wp_error( $id ) ? $id->get_error_code() . ' ' . $id->get_error_message() : 'devolvió 0' ) );
				continue;
			}
			$hash = substr( md5( $slug . $blog ), 0, 6 );
			$data = array( array( 'id' => 'c' . $hash, 'elType' => 'container', 'settings' => (object) array(), 'elements' => array(
				array( 'id' => 'w' . $hash, 'elType' => 'widget', 'widgetType' => $widget, 'settings' => (object) $settings, 'elements' => array() ),
			) ) );
			update_post_meta( $id, '_elementor_edit_mode', 'builder' );
			update_post_meta( $id, '_elementor_template_type', 'wp-page' );
			update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
			update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
			$doc_post = get_post( $id );
		}
		if ( ! $doc_post ) {
			$result['pages'][ $blog ][ $widget ] = array( 'missing' => true );
			continue;
		}
		try {
			$GLOBALS['wp_query']     = new WP_Query( array( 'page_id' => $doc_post->ID ) );
			$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
			$GLOBALS['post']         = $doc_post;
			setup_postdata( $doc_post );
			delete_post_meta( $doc_post->ID, '_elementor_element_cache' );
			delete_post_meta( $doc_post->ID, '_elementor_css' );
			$html = \Elementor\Plugin::$instance->frontend->get_builder_content( $doc_post->ID, false );
			$css  = \Elementor\Core\Files\CSS\Post::create( $doc_post->ID );
			$css->update();
			// Las URLs de los recursos cambian de carpeta (plugins/elementor → plugins/digitalisimo-elements/elementor).
			$content = preg_replace( '#/plugins/(?:elementor|digitalisimo-elements/elementor)/#', '/NUCLEO/', (string) $css->get_content() );
			file_put_contents( '/wordpress/wp-content/digi-core/css-' . $phase . '-' . $blog . '-' . $widget . '.css', $content );
			if ( 'image' === $widget ) {
				preg_match( '#<img[^>]+src="([^"]+)"#', $html, $img );
				$result['checks'][ 'image_src_' . $blog ] = (string) wp_parse_url( $img[1] ?? '', PHP_URL_PATH );
			}
			$result['pages'][ $blog ][ $widget ] = array(
				'signature' => digi_ab_signature( preg_replace( '#/plugins/(?:elementor|digitalisimo-elements/elementor)/#', '/NUCLEO/', $html ) ),
				'bytes'     => strlen( $html ),
				'id'        => $doc_post->ID,
				'css'       => md5( $content ),
				'css_bytes' => strlen( $content ),
			);
		} catch ( \Throwable $e ) {
			$result['pages'][ $blog ][ $widget ] = array( 'error' => $e->getMessage(), 'doc_type' => (string) get_post_meta( $doc_post->ID, '_elementor_template_type', true ), 'document' => is_object( \Elementor\Plugin::$instance->documents->get( $doc_post->ID, false ) ) ? 'ok' : 'false', 'post_type' => get_post_type( $doc_post->ID ), 'blog' => get_current_blog_id(), 'page_id' => $doc_post->ID );
		}
	}
	restore_current_blog();
}
// Un sitio nuevo de la red recibe su kit por defecto también con el núcleo incluido.
if ( 'bundled' === $phase && 1 === $only ) {
	$new = wpmu_create_blog( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . 'nuevo/', 'Nuevo', 1 );
	switch_to_blog( (int) $new );
	$result['checks']['kit_new_site'] = (int) get_option( 'elementor_active_kit' ) > 0;
	restore_current_blog();
}
file_put_contents( $out, wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
