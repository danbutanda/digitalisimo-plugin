<?php
/**
 * Pasos de forms-network.sh (Playground, un proceso PHP por paso). DIGI_STEP:
 *   setup                       red con el sitio 2 en /sub/ (o sitio individual), Tools y Elements activos
 *   screen:<pantalla>:<net|site>:<blog>  pinta la pantalla real, comprueba lo que toca en ese punto del plan y guarda en
 *                               result.json («pending») lo que enviaría un navegador
 *   post                        entrega ese envío a admin-post.php del sitio que pintó el formulario
 *   check:<caso>                lee la base y comprueba que nada se cruzó entre la red y los sitios
 * Cada paso deja en result.json sus problemas (lista vacía = en verde). Nada de esto se publica.
 */
$digi_step = (string) getenv( 'DIGI_STEP' );
$digi_dir  = '/wordpress/wp-content/probe';
$parts     = explode( ':', $digi_step );
$_SERVER['HTTP_HOST'] = 'playground.test';
$_SERVER['REQUEST_URI'] = '/';
// El envío pendiente vive en result.json (clave «pending»): en el sistema de archivos de Playground, volver a crear un
// archivo borrado en la carpeta montada falla a veces con «No such file or directory».
$pending = is_file( "$digi_dir/result.json" ) ? ( json_decode( (string) file_get_contents( "$digi_dir/result.json" ), true )['pending'] ?? null ) : null;
if ( 'screen' === $parts[0] ) {
	define( 'WP_ADMIN', true );
	if ( 'net' === $parts[2] ) define( 'WP_NETWORK_ADMIN', true );
	$_SERVER['REQUEST_URI'] = 'net' === $parts[2] ? '/wp-admin/network/admin.php' : ( '2' === $parts[3] ? '/sub' : '' ) . '/wp-admin/admin.php';
} elseif ( 'post' === $parts[0] ) {
	define( 'WP_ADMIN', true );
	$_SERVER['REQUEST_URI'] = (string) parse_url( (string) ( $pending['action'] ?? '' ), PHP_URL_PATH );
	$_SERVER['REQUEST_METHOD'] = 'POST';
}
// El administrador (usuario 1) con sesión desde el principio, como en un navegador: con la ruta privada de acceso
// activa, Tools manda a la portada a quien abre el escritorio sin sesión.
if ( 'setup' !== $digi_step ) $GLOBALS['wp_filter']['determine_current_user'][ PHP_INT_MAX ][] = array( 'function' => static function () { return 1; }, 'accepted_args' => 1 );
register_shutdown_function( static function () use ( $digi_step, $digi_dir ) {
	$e = error_get_last();
	if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) file_put_contents( "$digi_dir/fatal.log", "$digi_step {$e['message']} en {$e['file']}:{$e['line']}\n", FILE_APPEND );
} );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( is_admin() ) require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once $digi_dir . '/browser-submit.php';

final class Digi_Forms_Redirect extends Exception {}
function digi_result( $key, array $problems, $numbered = true ) {
	$file = '/wordpress/wp-content/probe/result.json';
	$all  = is_file( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
	if ( ! $numbered ) { $all[ $key ] = $problems; file_put_contents( $file, json_encode( $all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); return; }
	$n    = 1; while ( isset( $all[ "$key#$n" ] ) ) $n++;
	$all[ "$key#$n" ] = array_values( $problems );
	file_put_contents( $file, json_encode( $all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
}
function digi_input( $html, $id ) { return preg_match( '#<input[^>]*id="' . preg_quote( $id, '#' ) . '"[^>]*>#', $html, $m ) ? $m[0] : ''; }

$problems = array();
$multi    = is_multisite();
if ( 'setup' === $digi_step ) {
	if ( $multi ) {
		if ( ! get_blog_id_from_url( 'playground.test', '/sub/' ) ) wpmu_create_blog( 'playground.test', '/sub/', 'Sub', 1 );
		foreach ( array( 'digitalisimo-tools/digitalisimo-tools.php', 'digitalisimo-elements/pro-elements.php' ) as $p ) { $r = activate_plugin( $p, '', true ); if ( is_wp_error( $r ) ) $problems[] = "$p: " . $r->get_error_message(); }
	} else {
		foreach ( array( 'digitalisimo-tools/digitalisimo-tools.php', 'digitalisimo-elements/pro-elements.php' ) as $p ) { $r = activate_plugin( $p ); if ( is_wp_error( $r ) ) $problems[] = "$p: " . $r->get_error_message(); }
	}
	@unlink( "$digi_dir/count-" . ( $multi ? 'net' : 'single' ) );
	digi_result( 'setup', $problems );
	return;
}
wp_set_current_user( 1 );

if ( 'screen' === $parts[0] ) {
	$count_file = "$digi_dir/count-" . ( $multi ? 'net' : 'single' );
	$n          = (int) @file_get_contents( $count_file ) + 1;
	file_put_contents( $count_file, (string) $n );
	$plan  = $multi ? array(
		1 => 'tools-net-save', 2 => 'tools-site2-own', 3 => 'tools-site2-again', 4 => 'tools-site2-reload',
		5 => 'google-net-save', 6 => 'google-site2-inherits', 7 => 'google-site2-mask',
	) : array( 1 => 'tools-single-save', 2 => 'tools-single-reload', 3 => 'google-single-save', 4 => 'google-single-mask' );
	$case  = $plan[ $n ] ?? "sin-plan-$n";
	$_GET  = 'tools' === $parts[1] ? array( 'page' => 'digitalisimo-tools-elementor-templates', 'tab' => 'site' ) : array( 'page' => 'digitalisimo-elements-google-reviews' );
	ob_start();
	try {
		if ( 'tools' === $parts[1] ) \Digitalisimo\Tools\Elementor_Network_Templates\Network_Admin::page();
		else \Digitalisimo\Elements\Google_Reviews_Service::settings_page();
	} catch ( Throwable $e ) { $problems[] = 'la pantalla falla: ' . $e->getMessage(); }
	$html   = (string) ob_get_clean();
	$needle = 'tools' === $parts[1] ? 'digitalisimo_tools_save_site_options' : 'digitalisimo_elements_google_reviews_save';
	$form   = browser_form_find( $html, $needle );
	if ( ! $form ) $problems[] = "sin el formulario $needle";
	$fill = array(); $options = array();
	$login = digi_input( $html, 'dt_login_slug' );
	$key   = digi_input( $html, 'digi-google-key' );
	switch ( $case ) {
		case 'tools-net-save':
			if ( false !== strpos( $html, 'Heredar de la red' ) ) $problems[] = 'la red pinta «Heredar de la red»';
			$fill = array( 'values[login_slug]' => 'entrar-red', 'values[mobile_scroll]' => true );
			break;
		case 'tools-site2-own':
			if ( 3 !== substr_count( $html, 'Heredar de la red' ) ) $problems[] = 'el sitio 2 no pinta «Heredar de la red» en cada ajuste';
			if ( false === strpos( $login, 'value="entrar-red"' ) || false === strpos( $login, 'disabled' ) ) $problems[] = "el sitio 2 no muestra la ruta heredada deshabilitada: $login";
			$fill = array( 'inherit[login_slug]' => false, 'values[login_slug]' => 'acceso-dos' ); $options = array( 'enable' => array( 'values[login_slug]' ) );
			break;
		case 'tools-site2-again':
		case 'tools-site2-reload':
			if ( false === strpos( $login, 'value="acceso-dos"' ) || false !== strpos( $login, 'disabled' ) ) $problems[] = "al recargar, el sitio 2 no muestra su ruta: $login";
			if ( false === strpos( digi_input( $html, 'dt_mobile_scroll' ), 'checked' ) ) $problems[] = 'el sitio 2 no hereda el bloqueo de desplazamiento de la red';
			break;
		case 'tools-single-save':
			if ( false !== strpos( $html, 'Heredar de la red' ) ) $problems[] = 'el sitio individual pinta «Heredar de la red»';
			$fill = array( 'values[login_slug]' => 'acceso-uno', 'values[mobile_scroll]' => true );
			break;
		case 'tools-single-reload':
			if ( false === strpos( $login, 'value="acceso-uno"' ) || false === strpos( digi_input( $html, 'dt_mobile_scroll' ), 'checked' ) ) $problems[] = "al recargar no se ve lo guardado: $login";
			break;
		case 'google-net-save':
		case 'google-single-save':
			if ( false === strpos( $key, 'value=""' ) ) $problems[] = "sin clave, el campo no va vacío: $key";
			$fill = array( 'google_places_key' => 'net' === $parts[2] ? 'AIza-red' : 'AIza-uno' );
			break;
		case 'google-site2-inherits':
			if ( false === strpos( $key, 'value=""' ) || false === strpos( $html, 'Heredada de la red' ) || false !== strpos( $html, 'AIza-red' ) ) $problems[] = 'el sitio 2 no dice que hereda la clave de la red (o la imprime)';
			$fill = array( 'google_places_key' => 'AIza-dos' );
			break;
		case 'google-site2-mask':
		case 'google-single-mask':
			if ( false === strpos( $key, 'value="' . \Digitalisimo\Elements\Google_Reviews_Service::MASK . '"' ) || false === strpos( $html, 'Guardada' ) || preg_match( '/AIza-/', $html ) ) $problems[] = "la clave guardada no sale como máscara (o se imprime): $key";
			break;
		default:
			$problems[] = "paso sin plan: $case";
	}
	if ( $form ) {
		$sent = browser_form_submit( $form, $fill, $options );
		$json = json_encode( array( 'case' => $case, 'action' => $sent['action'], 'post' => $sent['post'], 'network' => 'net' === $parts[2] ) );
		if ( false === $json ) $problems[] = 'no se pudo guardar el envío: ' . json_last_error_msg();
		else digi_result( 'pending', json_decode( $json, true ), false );
	}
	digi_result( $case, $problems );
	return;
}

if ( 'post' === $digi_step ) {
	$case  = (string) ( $pending['case'] ?? '?' );
	$_POST = wp_slash( (array) ( $pending['post'] ?? array() ) );
	$_REQUEST = $_POST;
	$hook  = 'admin_post_' . ( $_POST['action'] ?? '' );
	if ( ! has_action( $hook ) ) $problems[] = "nadie atiende $hook";
	add_filter( 'wp_redirect', static function ( $url ) { throw new Digi_Forms_Redirect( $url ); }, PHP_INT_MAX );
	$to = '';
	try { do_action( $hook ); $problems[] = 'el guardado no redirige'; } catch ( Digi_Forms_Redirect $r ) { $to = $r->getMessage(); } catch ( Throwable $e ) { $problems[] = 'el guardado falla: ' . $e->getMessage(); }
	$want = ! empty( $pending['network'] ) ? network_admin_url() : admin_url();
	if ( '' !== $to && 0 !== strpos( $to, $want ) ) $problems[] = "vuelve a otra superficie: $to (esperaba $want)";
	digi_result( 'pending', array(), false );
	digi_result( "post $case", $problems );
	return;
}

if ( 'check' === $parts[0] ) {
	$tools = 'digitalisimo_tools_site_options';
	$key   = 'digitalisimo_elements_google_places_key';
	if ( 'tools-net' === $parts[1] ) {
		$net = (array) get_site_option( 'digitalisimo_tools_site_defaults', array() );
		if ( 'entrar-red' !== ( $net['login_slug'] ?? '' ) || 1 !== (int) ( $net['mobile_scroll'] ?? 0 ) ) $problems[] = 'la red no guardó sus valores: ' . json_encode( $net );
		$two = (array) get_blog_option( 2, $tools, array() );
		if ( 'acceso-dos' !== ( $two['values']['login_slug'] ?? '' ) || ! empty( $two['inherit']['login_slug'] ) || empty( $two['inherit']['mobile_scroll'] ) ) $problems[] = 'el sitio 2 no quedó personalizado sólo en la ruta: ' . json_encode( $two );
		$one = (array) get_blog_option( 1, $tools, array() );
		if ( ! empty( $one['values']['login_slug'] ) ) $problems[] = 'el sitio principal recibió una ruta: ' . json_encode( $one );
		switch_to_blog( 2 );
		if ( 'acceso-dos' !== \Digitalisimo\Tools\Hide_Login::slug() ) $problems[] = 'la ruta en vigor del sitio 2 no es la suya';
		restore_current_blog();
		if ( 'entrar-red' !== \Digitalisimo\Tools\Hide_Login::slug() ) $problems[] = 'el sitio principal no hereda la ruta de la red';
	} elseif ( 'google-net' === $parts[1] ) {
		if ( 'AIza-red' !== get_site_option( $key ) ) $problems[] = 'la red no guardó su clave';
		if ( 'AIza-dos' !== get_blog_option( 2, $key ) ) $problems[] = 'el sitio 2 no guardó la suya';
		if ( false !== get_blog_option( 1, $key, false ) ) $problems[] = 'el sitio principal recibió una clave';
	} else {
		$one = (array) get_option( $tools, array() );
		if ( 'acceso-uno' !== ( $one['values']['login_slug'] ?? '' ) ) $problems[] = 'el sitio individual no guardó su ruta';
		if ( 'AIza-uno' !== get_option( $key ) ) $problems[] = 'el sitio individual no guardó su clave';
	}
	digi_result( $digi_step, $problems );
}
