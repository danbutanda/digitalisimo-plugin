<?php
/**
 * Pasos de ms-settings.sh: ajustes de DIGITALÍSIMO SEO e IA Tools en una red real (Playground, un proceso PHP por paso,
 * WordPress de verdad: update_network_option con sanitize_option, herencia y opciones por blog). Cada paso pinta la
 * pantalla real, la envía como un navegador (tests/lib/browser-form.php) por el camino de WordPress (options.php,
 * network/edit.php o la propia pantalla) y anota lo observado en result.json. Nada de esto se publica.
 */
$digi_step = (string) getenv( 'DIGI_STEP' ); $digi_blog = (int) getenv( 'DIGI_BLOG' );
$digi_network = 0 === strpos( $digi_step, 'net-' );
$_SERVER['HTTP_HOST'] = 'playground.test';
if ( 'setup' !== $digi_step ) { define( 'WP_ADMIN', true ); if ( $digi_network ) define( 'WP_NETWORK_ADMIN', true ); }
$_SERVER['REQUEST_URI'] = $digi_network ? '/wp-admin/network/admin.php' : ( 2 === $digi_blog ? '/sitio2/' : '/' ) . 'wp-admin/admin.php';
register_shutdown_function( static function () use ( $digi_step ) { $e = error_get_last(); if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) file_put_contents( '/wordpress/wp-content/probe/fatal.log', "$digi_step {$e['message']} en {$e['file']}:{$e['line']}\n", FILE_APPEND ); } );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require '/wordpress/wp-content/probe/browser-form.php';
const OPT = 'digitalisimo_integrations_options';
$result_file = '/wordpress/wp-content/probe/result.json';
$result = is_readable( $result_file ) ? json_decode( file_get_contents( $result_file ), true ) : array();
$note = function ( $ok, $what ) use ( &$result, $digi_step ) { $result[] = array( 'step' => $digi_step, 'ok' => (bool) $ok, 'what' => $what ); };
class Digi_Redirect extends Exception {}
add_filter( 'wp_redirect', function ( $to ) { throw new Digi_Redirect( (string) $to ); }, PHP_INT_MAX );
function digi_html( callable $screen, array $get ) { $_GET = $get; $_POST = $_REQUEST = array(); ob_start(); try { $screen(); } catch ( Throwable $e ) {} return ob_get_clean(); }
/** options.php: grupo, nonce real y update_option de cada opción del grupo. */
function digi_options_php( array $post ) { global $pagenow; $pagenow = 'options.php'; $_POST = $_REQUEST = wp_slash( $post ); check_admin_referer( $post['option_page'] . '-options' ); update_option( OPT, wp_unslash( $_POST[ OPT ] ?? null ) ); $_POST = $_REQUEST = array(); $pagenow = 'admin.php'; }
function digi_network_edit( array $post ) { $_POST = $_REQUEST = wp_slash( $post ); try { do_action( 'network_admin_edit_digitalisimo_save_network_seo' ); } catch ( Digi_Redirect $r ) { return $r->getMessage(); } finally { $_POST = $_REQUEST = array(); } return null; }
function digi_ia( $network, $tab, array $fill ) { $screen = $network ? array( 'Digitalisimo_AI', 'network_page' ) : array( 'Digitalisimo_AI', 'page' ); $form = digi_browser_form( digi_html( $screen, array( 'tab' => $tab ) ), $fill, $network ? 'digitalisimo_ai_network_save' : 'digitalisimo_ai_save' ); $_GET = array( 'tab' => $tab ); $_POST = $_REQUEST = wp_slash( $form['post'] ); ob_start(); call_user_func( $screen ); $html = ob_get_clean(); $_POST = $_REQUEST = array(); return array( $form, $html ); }
if ( 'setup' !== $digi_step ) { wp_set_current_user( 1 ); do_action( 'admin_init' ); }
$seo_net = array( 'Digitalisimo_Integrations_SEO_Suite', 'network_page' );
$seo_site = array( 'Digitalisimo_Integrations_SEO_Suite', 'page' );

switch ( $digi_step ) {
	case 'setup':
		$id = wpmu_create_blog( 'playground.test', '/sitio2/', 'Sitio dos', 1 );
		activate_plugin( 'digitalisimo-seo/digitalisimo-integrations.php', '', true );
		activate_plugin( 'digitalisimo.chatbot/digitalisimo-chatbot.php', '', true );
		// El sitio principal apaga su SEO propio y tiene sus claves; el sitio 2 lo hereda todo.
		update_option( OPT, array( 'enable_seo' => 0, 'seo_organization_name' => 'PRINCIPAL', 'openai_api_key' => 'sk-PRINCIPAL', 'ai_providers' => array( 'anthropic' => array( 'enabled' => 1, 'key' => 'sk-ant-PRINCIPAL', 'url' => '' ) ) ) );
		update_option( 'digitalisimo_seo_network_inherit', array( 'enable_seo' => 0, 'seo_organization_name' => 0 ), false );
		// Una red que el guardado antiguo ya contaminó con las claves del principal: la limpieza única debe quitarlas.
		update_site_option( OPT, array( 'openai_api_key' => 'sk-PRINCIPAL', 'ai_providers' => get_option( OPT )['ai_providers'] ) );
		$note( 2 === (int) $id && is_plugin_active_for_network( 'digitalisimo-seo/digitalisimo-integrations.php' ), 'red con el sitio 2 y los plugins activos en la red' );
		break;
	case 'net-seo':
		$form = digi_browser_form( digi_html( $seo_net, array( 'page' => 'digitalisimo-network-seo', 'tab' => 'general' ) ), array( 'digitalisimo_network[seo_organization_name]' => 'RED' ), null, 'digitalisimo_save_network_seo' );
		$to = digi_network_edit( $form['post'] );
		$net = (array) get_site_option( OPT, array() );
		$note( null !== $to, 'Red → SEO guarda aunque el sitio principal tenga el SEO apagado' );
		$note( array( 'openai_api_key', 'ai_providers' ) === ( get_site_option( 'digitalisimo_seo_network_secrets_cleaned' )['removed'] ?? null ), 'la limpieza única quitó de la red las claves copiadas del principal y lo registró' );
		$note( 'RED' === ( $net['seo_organization_name'] ?? '' ), 'la red guardó el nombre de la organización' );
		$note( false === strpos( wp_json_encode( $net ), 'PRINCIPAL' ) && ! array_key_exists( 'enable_seo', $net ), 'la red no copió ajustes ni claves del sitio principal: ' . wp_json_encode( array_intersect_key( $net, array_flip( array( 'enable_seo', 'openai_api_key', 'ai_providers' ) ) ) ) );
		break;
	case 'site-seo':
		$get = array( 'page' => 'digitalisimo-seo-general' );
		$form = digi_browser_form( digi_html( $seo_site, $get ), array( OPT . '[network_inherit][seo_organization_name]' => '', OPT . '[network_inherit][seo_organization_url]' => '' ), null, 'options.php' );
		digi_options_php( $form['post'] );
		$note( 'RED' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_name' ), 'dejar de heredar conserva el valor visto (el de la red)' );
		$form = digi_browser_form( digi_html( $seo_site, $get ), array( OPT . '[seo_organization_name]' => 'DOS', OPT . '[seo_organization_url]' => 'https://playground.test/sitio2/' ), null, 'options.php' );
		digi_options_php( $form['post'] );
		$form = digi_browser_form( digi_html( array( 'Digitalisimo_Integrations_Settings', 'keyword_settings_page' ), array( 'page' => 'digitalisimo-keyword-settings' ) ), array(), null, 'options.php' );
		digi_options_php( $form['post'] );
		break;
	case 'check-seo':
		$note( 'DOS' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_name' ) && 'https://playground.test/sitio2/' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_url' ), 'el sitio 2 personalizó nombre y URL (recargado): ' . Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_name' ) . ' ' . Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_url' ) );
		$note( 1 === (int) Digitalisimo_Integrations_SEO_Resolver::option( 'enable_seo' ), 'guardar «Ajustes de keywords» no apagó el SEO del sitio 2' );
		switch_to_blog( 1 ); $note( 'PRINCIPAL' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_name' ), 'el sitio principal conserva lo suyo' ); restore_current_blog();
		$note( 'RED' === ( get_site_option( OPT )['seo_organization_name'] ?? '' ), 'la red conserva lo suyo' );
		break;
	case 'net-ia':
		list( , $html ) = digi_ia( true, 'providers', array( 'providers[openai][key]' => 'sk-RED', 'providers[openai][enabled]' => '1' ) );
		list( , $html ) = digi_ia( true, 'providers', array() );
		$o = (array) get_site_option( 'digitalisimo_ai_options', array() );
		$note( 'sk-RED' === ( $o['providers']['openai']['key'] ?? '' ) && false !== strpos( $html, 'value="••••••••••••"' ) && false === strpos( $html, 'sk-RED' ), 'IA de red: la clave se guarda, se ve como •••• y guardar sin tocarla la conserva' );
		break;
	case 'site-ia':
		list( $form ) = digi_ia( false, 'providers', array( 'providers[anthropic][key]' => 'sk-escrita' ) );
		$note( ! $form['pressed'] && false === get_option( 'digitalisimo_ai_options' ), 'IA, sitio que hereda: Proveedores no envía nada' );
		digi_ia( false, 'general', array( 'ai[inherit_network]' => '' ) );
		digi_ia( false, 'providers', array( 'providers[anthropic][key]' => 'sk-DOS', 'providers[anthropic][enabled]' => '1' ) );
		break;
	case 'check-ia':
		$o = (array) get_option( 'digitalisimo_ai_options', array() );
		$note( 'sk-DOS' === ( $o['providers']['anthropic']['key'] ?? '' ) && 'sk-DOS' === ( Digitalisimo_AI::get( 'providers' )['anthropic']['key'] ?? '' ), 'IA, sitio 2 personalizado: su clave quedó guardada y es la efectiva (recargado)' );
		$note( 'sk-RED' === ( get_site_option( 'digitalisimo_ai_options' )['providers']['openai']['key'] ?? '' ) && empty( get_site_option( 'digitalisimo_ai_options' )['providers']['anthropic']['key'] ), 'IA: la red conserva su clave y no recibió la del sitio' );
		break;
}
file_put_contents( $result_file, wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
