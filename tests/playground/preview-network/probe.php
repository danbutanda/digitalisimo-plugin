<?php
/**
 * Pasos de preview-network.sh (Playground multisitio, un proceso PHP por paso). DIGI_STEP:
 *   setup                 sitios 2 (/sub/) y 3 (/otro/), Elements activo en la red, el MCP de cada sitio y la misma
 *                         página (mismo ID) en los dos
 *   emit:<sitio>          el administrador ejecuta `elementor/create-preview-link` en ese sitio; guarda el token
 *   open:<sitio>:<de>     abre en el front de <sitio> el enlace emitido en <de>: debe verse sólo si son el mismo
 * El estado (IDs y tokens) vive en result.json («state»); cada paso deja ahí sus problemas. Nada de esto se publica.
 */
$digi_step = (string) getenv( 'DIGI_STEP' );
$digi_dir  = '/wordpress/wp-content/probe';
$parts     = explode( ':', $digi_step );
$paths     = array( 1 => '/', 2 => '/sub/', 3 => '/otro/' );
$state     = is_file( "$digi_dir/result.json" ) ? ( json_decode( (string) file_get_contents( "$digi_dir/result.json" ), true )['state'] ?? array() ) : array();
$_SERVER['HTTP_HOST'] = 'playground.test';
$_SERVER['REQUEST_URI'] = '/';
if ( 'emit' === $parts[0] ) {
	define( 'WP_ADMIN', true );
	$_SERVER['REQUEST_URI'] = $paths[ (int) $parts[1] ] . 'wp-admin/admin.php';
} elseif ( 'open' === $parts[0] ) {
	$token = (string) ( $state[ 'token' . $parts[2] ] ?? '' );
	$_GET  = $_REQUEST = array( 'elementor_preview_token' => $token );
	$_SERVER['REQUEST_URI'] = $paths[ (int) $parts[1] ] . '?elementor_preview_token=' . rawurlencode( $token );
	$_SERVER['QUERY_STRING'] = 'elementor_preview_token=' . rawurlencode( $token );
}
if ( 'emit' === $parts[0] ) $GLOBALS['wp_filter']['determine_current_user'][ PHP_INT_MAX ][] = array( 'function' => static function () { return 1; }, 'accepted_args' => 1 );
register_shutdown_function( static function () use ( $digi_step, $digi_dir ) {
	$e = error_get_last();
	if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) file_put_contents( "$digi_dir/fatal.log", "$digi_step {$e['message']} en {$e['file']}:{$e['line']}\n", FILE_APPEND );
} );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

function digi_save( $key, $value ) {
	$file = '/wordpress/wp-content/probe/result.json';
	$all  = is_file( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
	$all[ $key ] = $value;
	file_put_contents( $file, json_encode( $all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
}
$problems = array();

if ( 'setup' === $digi_step ) {
	foreach ( array( 2 => 'Sub', 3 => 'Otro' ) as $n => $title ) if ( ! get_blog_id_from_url( 'playground.test', $paths[ $n ] ) ) wpmu_create_blog( 'playground.test', $paths[ $n ], $title, 1 );
	$r = activate_plugin( 'digitalisimo-elements/pro-elements.php', '', true );
	if ( is_wp_error( $r ) ) $problems[] = 'Elements: ' . $r->get_error_message();
	$ids = array();
	foreach ( array( 2, 3 ) as $blog ) {
		switch_to_blog( $blog );
		try {
			update_option( 'elementor_mcp_enabled', true );
			update_option( 'permalink_structure', '' );
			$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Página de la vista previa', 'post_content' => '' ) );
			update_post_meta( $id, '_elementor_edit_mode', 'builder' );
			update_post_meta( $id, '_elementor_template_type', 'wp-page' );
			update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'a1b2c3d', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'd4e5f6a', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => "Sitio $blog" ), 'elements' => array() ) ) ) ) ) ) );
			$ids[ $blog ] = (int) $id;
		} finally {
			restore_current_blog();
		}
	}
	if ( $ids[2] !== $ids[3] ) $problems[] = 'la página no tiene el mismo ID en los dos sitios: ' . json_encode( $ids );
	$state['page'] = $ids[2];
	digi_save( 'state', $state );
	digi_save( 'setup', $problems );
	return;
}

if ( 'emit' === $parts[0] ) {
	$blog = (int) $parts[1];
	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'elementor/create-preview-link' ) : null;
	if ( ! $ability ) {
		$problems[] = 'la herramienta elementor/create-preview-link no está registrada en el sitio ' . $blog;
	} else {
		$result = $ability->execute( array( 'post_id' => (int) $state['page'] ) );
		if ( is_wp_error( $result ) || empty( $result['url'] ) ) {
			$problems[] = 'la herramienta falló: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $result ) );
		} else {
			parse_str( (string) wp_parse_url( $result['url'], PHP_URL_QUERY ), $q );
			$state[ 'token' . $blog ]    = (string) ( $q['elementor_preview_token'] ?? '' );
			$state[ 'revision' . $blog ] = (int) $result['revision_id'];
			if ( 0 !== strpos( $result['url'], home_url( '/' ) ) ) $problems[] = 'el enlace no es de este sitio: ' . $result['url'];
			$marks = (array) get_metadata( 'post', (int) $result['revision_id'], '_digitalisimo_preview_site', false );
			if ( ! in_array( $blog . ':' . (int) $result['expires_at_unix'], $marks, true ) ) $problems[] = 'la revisión no quedó marcada con este sitio y la caducidad: ' . json_encode( $marks );
		}
	}
	digi_save( 'state', $state );
	digi_save( $digi_step, $problems );
	return;
}

if ( 'open' === $parts[0] ) {
	$here = (int) $parts[1]; $from = (int) $parts[2];
	if ( get_current_blog_id() !== $here ) $problems[] = 'la petición no cayó en el sitio ' . $here;
	if ( $state[ 'revision2' ] !== $state[ 'revision3' ] ) $problems[] = 'nota: las revisiones no coinciden entre sitios (' . $state['revision2'] . ' y ' . $state['revision3'] . '); el caso del clon no se ensaya completo';
	wp();
	$vars    = (array) $GLOBALS['wp']->query_vars;
	$core    = (int) ( $vars['p'] ?? 0 ) === (int) $state['page'] && is_array( $vars['post_status'] ?? null );
	$ours    = class_exists( '\\Digitalisimo\\Elements\\Mcp\\Preview_Link' ) ? (int) ( \Digitalisimo\Elements\Mcp\Preview_Link::state()['post'] ?? 0 ) : -1;
	$visible = $core && (int) $state['page'] === $ours;
	if ( $here === $from && ! $visible ) $problems[] = "el enlace del sitio $from no se ve en su sitio (núcleo: " . ( $core ? 'sí' : 'no' ) . ", Preview_Link: $ours)";
	if ( $here !== $from && ( $core || $ours > 0 || isset( $_GET['elementor_preview_token'] ) ) ) $problems[] = "el enlace del sitio $from se ve en el sitio $here (núcleo: " . ( $core ? 'sí' : 'no' ) . ", Preview_Link: $ours)";
	digi_save( $digi_step, $problems );
}
