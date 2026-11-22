<?php
/**
 * DIGITALÍSIMO Tools · formularios como un navegador, en sitio individual, en un sitio de la red y en la red (paridad
 * sitio/red, 2026-11-22: «no mezcles sitios, todo debe ser perfecto»). Cada caso pinta la pantalla real, envía su
 * formulario con lo que mandaría un navegador (campos, ocultos, casillas marcadas y el botón pulsado) a la URL de su
 * `action` (admin-post.php del sitio que la pintó: nunca es administración de red), y comprueba lo guardado y lo que
 * se ve al volver a cargar.
 *
 * - «Ajustes del sitio»: guardar y recargar en los tres modos; la red no pinta «Heredar de la red» y lo que guarda lo
 *   heredan los sitios; un sitio que personaliza no toca los valores de la red ni los de otro sitio.
 * - «Plantillas de red»: la configuración de la red y la de una plantilla global se guardan; volver a guardar una
 *   plantilla no borra su «Última sincronización»; con DIGITALÍSIMO Elements activo sólo en un sitio maestro que no es
 *   el principal, la pantalla de red lista sus plantillas y la sincronización en cola sí corre.
 */
namespace {
	error_reporting( E_ALL );
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'OBJECT', 'OBJECT' );
	define( 'MINUTE_IN_SECONDS', 60 );
	require __DIR__ . '/lib/browser-submit.php';

	final class Tf_Redirect extends Exception {}
	final class Tf_Die extends Exception {}
	class WP_Error { public $code; public $message; public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; } public function get_error_message() { return $this->message; } }
	function is_wp_error( $t ) { return $t instanceof WP_Error; }

	$checks = 0;
	function check( $ok, $message ) { global $checks; if ( ! $ok ) { fwrite( STDERR, "FALLA: $message\n" ); exit( 1 ); } $checks++; }

	/* Mundo: red con los sitios 1 (principal), 2 y 3, o sitio individual. */
	function tf_world( $multisite ) {
		$GLOBALS['tf'] = array( 'multisite' => $multisite, 'network_admin' => false, 'blog' => 1, 'stack' => array(), 'options' => array( 1 => array(), 2 => array(), 3 => array() ), 'site' => array(), 'caps' => array( 'manage_options', 'manage_network_options', 'manage_network', 'edit_themes', 'unfiltered_html' ), 'transients' => array(), 'cron' => array(), 'posts' => array(), 'meta' => array(), 'loaded' => false, 'next' => 100 );
	}
	function tf_screen( $blog, $network_admin = false ) { $GLOBALS['tf']['blog'] = $blog; $GLOBALS['tf']['network_admin'] = $network_admin; }
	function tf_home( $blog ) { return $GLOBALS['tf']['multisite'] ? 'https://red.test' . ( 1 === $blog ? '' : '/s' . $blog ) : 'https://sitio.test'; }

	function is_multisite() { return $GLOBALS['tf']['multisite']; }
	function is_network_admin() { return $GLOBALS['tf']['network_admin']; }
	function get_current_blog_id() { return $GLOBALS['tf']['blog']; }
	function get_main_site_id() { return 1; }
	function get_current_user_id() { return 7; }
	function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['tf']['caps'], true ); }
	function switch_to_blog( $id ) { $GLOBALS['tf']['stack'][] = $GLOBALS['tf']['blog']; $GLOBALS['tf']['blog'] = (int) $id; return true; }
	function restore_current_blog() { $GLOBALS['tf']['blog'] = array_pop( $GLOBALS['tf']['stack'] ); return true; }
	function get_sites() { return $GLOBALS['tf']['multisite'] ? array_map( static function ( $id ) { return (object) array( 'blog_id' => (string) $id, 'domain' => 'red.test', 'path' => 1 === $id ? '/' : "/s$id/" ); }, array( 1, 2, 3 ) ) : array(); }

	function get_option( $k, $d = false ) { $o = $GLOBALS['tf']['options'][ get_current_blog_id() ]; return array_key_exists( $k, $o ) ? $o[ $k ] : $d; }
	function update_option( $k, $v, $a = null ) { $GLOBALS['tf']['options'][ get_current_blog_id() ][ $k ] = $v; return true; }
	function get_blog_option( $blog, $k, $d = false ) { $o = $GLOBALS['tf']['options'][ $blog ] ?? array(); return array_key_exists( $k, $o ) ? $o[ $k ] : $d; }
	function get_site_option( $k, $d = false ) { if ( ! is_multisite() ) return get_option( $k, $d ); return array_key_exists( $k, $GLOBALS['tf']['site'] ) ? $GLOBALS['tf']['site'][ $k ] : $d; }
	function update_site_option( $k, $v ) { if ( ! is_multisite() ) return update_option( $k, $v ); $GLOBALS['tf']['site'][ $k ] = $v; return true; }
	function add_site_option( $k, $v ) { if ( false === get_site_option( $k ) ) update_site_option( $k, $v ); return true; }
	function get_transient( $k ) { return $GLOBALS['tf']['transients'][ get_current_blog_id() ][ $k ] ?? false; }
	function set_transient( $k, $v, $e = 0 ) { $GLOBALS['tf']['transients'][ get_current_blog_id() ][ $k ] = $v; return true; }
	function delete_transient( $k ) { unset( $GLOBALS['tf']['transients'][ get_current_blog_id() ][ $k ] ); return true; }
	function get_site_transient( $k ) { return $GLOBALS['tf']['transients']['site'][ $k ] ?? false; }
	function set_site_transient( $k, $v, $e = 0 ) { $GLOBALS['tf']['transients']['site'][ $k ] = $v; return true; }
	function delete_site_transient( $k ) { unset( $GLOBALS['tf']['transients']['site'][ $k ] ); return true; }

	function admin_url( $p = '' ) { return tf_home( get_current_blog_id() ) . '/wp-admin/' . $p; }
	function network_admin_url( $p = '' ) { return 'https://red.test/wp-admin/network/' . $p; }
	function home_url( $p = '', $s = null ) { return tf_home( get_current_blog_id() ) . '/' . ltrim( (string) $p, '/' ); }
	function site_url( $p = '', $s = null ) { return home_url( $p ); }
	function add_query_arg( $args, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
	function wp_safe_redirect( $url ) { throw new Tf_Redirect( $url ); }
	function wp_die( $m = '' ) { throw new Tf_Die( (string) $m ); }
	function add_action() {} function add_filter() {} function did_action( $h ) { return 'elementor/loaded' === $h && $GLOBALS['tf']['loaded']; }
	function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
	function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
	function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
	function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
	function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
	function esc_html__( $t ) { return $t; }
	function checked( $a, $b = true, $echo = true ) { return (string) $a === (string) $b ? " checked='checked'" : ''; }
	function selected( $a, $b = true, $echo = true ) { return (string) $a === (string) $b ? " selected='selected'" : ''; }
	function wp_nonce_field( $a ) { echo '<input type="hidden" id="_wpnonce" name="_wpnonce" value="n:' . $a . ':' . get_current_blog_id() . '"><input type="hidden" name="_wp_http_referer" value="/wp-admin/">'; }
	function check_admin_referer( $a ) { if ( ( $_POST['_wpnonce'] ?? '' ) !== 'n:' . $a . ':' . get_current_blog_id() ) throw new Tf_Die( "nonce $a" ); return 1; }
	function submit_button( $text = 'Guardar cambios', $type = 'primary', $name = 'submit' ) { echo '<p class="submit"><input type="submit" name="' . $name . '" id="' . $name . '" class="button button-primary" value="' . esc_attr( $text ) . '"></p>'; }
	function remove_accents( $v ) { return $v; }
	function sanitize_title_with_dashes( $v ) { return trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $v ) ), '-' ); }
	function get_page_by_path() { return null; }
	function get_term_by() { return false; }
	function wp_date( $f, $t ) { return gmdate( $f, $t ); }
	function current_time() { return '2026-11-22 12:00:00'; }
	function wp_generate_password() { return 'abcdefgh'; }
	function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
	function wp_list_pluck( $list, $f ) { return array_map( static function ( $i ) use ( $f ) { return is_object( $i ) ? $i->$f : $i[ $f ]; }, $list ); }
	function wp_next_scheduled( $h, $args = array() ) { foreach ( $GLOBALS['tf']['cron'][ get_current_blog_id() ] ?? array() as $e ) if ( $e[0] === $h && $e[1] === $args ) return 1; return false; }
	function wp_schedule_single_event( $t, $h, $args = array() ) { $GLOBALS['tf']['cron'][ get_current_blog_id() ][] = array( $h, $args ); return true; }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function wp_slash( $v ) { return $v; }
	function get_posts( $q ) { $blog = get_current_blog_id(); return array_values( array_filter( $GLOBALS['tf']['posts'][ $blog ] ?? array(), static function ( $p ) use ( $q ) { return $p->post_type === ( $q['post_type'] ?? 'post' ) && ( empty( $q['meta_key'] ) || ( $GLOBALS['tf']['meta'][ get_current_blog_id() ][ $p->ID ][ $q['meta_key'] ] ?? null ) === $q['meta_value'] ); } ) ); }
	function get_post( $id ) { return $GLOBALS['tf']['posts'][ get_current_blog_id() ][ (int) $id ] ?? null; }
	function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['tf']['meta'][ get_current_blog_id() ][ (int) $id ][ $k ] ?? ''; }
	function update_post_meta( $id, $k, $v ) { $GLOBALS['tf']['meta'][ get_current_blog_id() ][ (int) $id ][ $k ] = $v; return true; }
	function wp_insert_post( $arr ) { $id = $GLOBALS['tf']['next']++; $GLOBALS['tf']['posts'][ get_current_blog_id() ][ $id ] = (object) array_merge( $arr, array( 'ID' => $id ) ); return $id; }
	function wp_update_post( $arr ) { $GLOBALS['tf']['posts'][ get_current_blog_id() ][ (int) $arr['ID'] ] = (object) $arr; return (int) $arr['ID']; }
	function clean_post_cache() {}
	function do_action() {}
	function tf_post( $blog, $id, $title, $data = '[]' ) {
		$GLOBALS['tf']['posts'][ $blog ][ $id ] = (object) array( 'ID' => $id, 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => sanitize_key( $title ), 'post_excerpt' => '', 'post_content' => '' );
		$GLOBALS['tf']['meta'][ $blog ][ $id ] = array( '_elementor_data' => $data, '_elementor_template_type' => 'section' );
	}
}

namespace Digitalisimo\Tools\Php_Snippets {
	/** La pestaña «Código PHP» tiene su propia prueba (tools-php-snippets.php). */
	final class Admin { public static function visible( $network ) { return false; } public static function page( $network ) {} }
}

namespace {
	foreach ( array( 'modules/site-options/class-hide-login.php', 'modules/site-options/class-site-options.php', 'modules/elementor-network-templates/class-registry.php', 'modules/elementor-network-templates/class-id-mapper.php', 'modules/elementor-network-templates/class-dependency-resolver.php', 'modules/elementor-network-templates/class-elementor-adapter.php', 'modules/elementor-network-templates/class-logger.php', 'modules/elementor-network-templates/class-sync.php', 'modules/elementor-network-templates/class-network-admin.php' ) as $file ) require __DIR__ . '/../digitalisimo-tools/' . $file;
	use Digitalisimo\Tools\Site_Options;
	use Digitalisimo\Tools\Elementor_Network_Templates\Network_Admin;
	use Digitalisimo\Tools\Elementor_Network_Templates\Registry;
	use Digitalisimo\Tools\Elementor_Network_Templates\Sync;

	/** Pinta la pantalla de Tools (pestaña $tab) en el sitio $blog o en la red. */
	function tf_page( $tab, $blog, $network_admin = false, array $get = array() ) {
		tf_screen( $blog, $network_admin );
		$_GET = array_merge( array( 'page' => Network_Admin::PAGE, 'tab' => $tab ), $get );
		$_POST = array();
		ob_start();
		Network_Admin::page();
		return ob_get_clean();
	}

	/** Envía el formulario que contiene $needle como un navegador: admin-post.php del sitio de su `action`. */
	function tf_submit( $html, $needle, array $fill = array(), array $options = array() ) {
		$form = browser_form_find( $html, $needle );
		check( null !== $form, "La pantalla tiene el formulario «{$needle}»" );
		$sent = browser_form_submit( $form, $fill, $options + array( 'slash' => true ) );
		check( 'post' === $sent['method'], 'El formulario se envía por POST' );
		check( (bool) preg_match( '#^(https://[^/]+(?:/s(\d+))?)/wp-admin/admin-post\.php$#', $sent['action'], $m ), 'El formulario va a admin-post.php de un sitio: ' . $sent['action'] );
		$blog = $GLOBALS['tf']['multisite'] ? ( empty( $m[2] ) ? 1 : (int) $m[2] ) : 1;
		tf_screen( $blog, false );
		$_POST = $sent['post'];
		$_GET  = array();
		$hook  = $_POST['action'] ?? '';
		$map   = array( Site_Options::ACTION => array( Site_Options::class, 'save' ), 'digitalisimo_tools_save_settings' => array( Network_Admin::class, 'save_settings' ), 'digitalisimo_tools_save_template' => array( Network_Admin::class, 'save_template' ), 'digitalisimo_tools_sync_template' => array( Network_Admin::class, 'sync_template' ) );
		check( isset( $map[ $hook ] ), "El formulario manda una acción con manejador: «{$hook}»" );
		try {
			call_user_func( $map[ $hook ] );
		} catch ( Tf_Redirect $r ) {
			return $r->getMessage();
		}
		check( false, "El manejador de «{$hook}» no redirige" );
	}

	/** Sigue la redirección como el navegador: misma pantalla, mismo sitio o red. */
	function tf_follow( $url ) {
		$parts = parse_url( $url );
		parse_str( $parts['query'] ?? '', $get );
		$network = false !== strpos( $parts['path'], '/wp-admin/network/' );
		$blog    = preg_match( '#^/s(\d+)/#', $parts['path'], $m ) ? (int) $m[1] : 1;
		return tf_page( $get['tab'] ?? '', $blog, $network, $get );
	}

	function tf_field_value( $html, $id ) {
		return preg_match( '#<input[^>]*id="' . preg_quote( $id, '#' ) . '"[^>]*>#', $html, $m ) ? $m[0] : '';
	}

	/* ---------------------------------------------------------------- Ajustes del sitio */

	// 1. Sitio individual: escribir la ruta, activar el bloqueo de desplazamiento, guardar y recargar.
	tf_world( false );
	$html = tf_page( 'site', 1 );
	check( false === strpos( $html, 'Heredar de la red' ), 'Sitio individual: sin «Heredar de la red»' );
	$back = tf_submit( $html, Site_Options::ACTION, array( 'values[login_slug]' => 'Acceso Equipo', 'values[mobile_scroll]' => true, 'values[redirect_attachments]' => false ) );
	check( 0 === strpos( $back, 'https://sitio.test/wp-admin/admin.php?' ) && false !== strpos( $back, 'tab=site' ), 'Sitio individual: vuelve a la pestaña en el escritorio del sitio: ' . $back );
	check( array( 'login_slug' => 'acceso-equipo', 'redirect_attachments' => 0, 'mobile_scroll' => 1 ) === Site_Options::site_data()['values'], 'Sitio individual: se guarda lo escrito' );
	$html = tf_follow( $back );
	check( false !== strpos( $html, 'Ajustes guardados.' ) && false !== strpos( tf_field_value( $html, 'dt_login_slug' ), 'value="acceso-equipo"' ) && false !== strpos( tf_field_value( $html, 'dt_mobile_scroll' ), 'checked' ) && false === strpos( tf_field_value( $html, 'dt_redirect_attachments' ), 'checked' ), 'Sitio individual: al recargar se ve lo guardado' );
	tf_submit( $html, Site_Options::ACTION );
	check( array( 'login_slug' => 'acceso-equipo', 'redirect_attachments' => 0, 'mobile_scroll' => 1 ) === Site_Options::site_data()['values'], 'Sitio individual: guardar otra vez sin tocar nada no cambia nada' );
	check( array() === $GLOBALS['tf']['site'], 'Sitio individual: nada en sitemeta' );

	// 2. Red: la administración de red define los valores que heredan los sitios, sin «Heredar de la red».
	tf_world( true );
	$html = tf_page( 'site', 1, true );
	check( false === strpos( $html, 'Heredar de la red' ) && false !== strpos( $html, 'Guardar valores de red' ), 'Red: sin «Heredar de la red» y con su botón' );
	$back = tf_submit( $html, Site_Options::ACTION, array( 'values[login_slug]' => 'entrar-red', 'values[mobile_scroll]' => true ) );
	check( 0 === strpos( $back, 'https://red.test/wp-admin/network/admin.php?' ), 'Red: vuelve a la administración de red: ' . $back );
	check( array( 'login_slug' => 'entrar-red', 'redirect_attachments' => 1, 'mobile_scroll' => 1 ) === $GLOBALS['tf']['site'][ Site_Options::NETWORK ], 'Red: se guarda en sitemeta' );
	check( ! isset( $GLOBALS['tf']['options'][1][ Site_Options::SITE ] ) || array() === Site_Options::site_data()['values'], 'Red: no escribe los valores del sitio principal' );
	$html = tf_follow( $back );
	check( false !== strpos( tf_field_value( $html, 'dt_login_slug' ), 'value="entrar-red"' ) && false !== strpos( tf_field_value( $html, 'dt_mobile_scroll' ), 'checked' ), 'Red: al recargar se ve lo guardado' );

	// 3. Sitio de la red: hereda; al personalizar la ruta sólo cambia ese sitio.
	$html = tf_page( 'site', 2 );
	check( 3 === substr_count( $html, 'Heredar de la red' ), 'Sitio de la red: «Heredar de la red» en cada ajuste' );
	check( false !== strpos( tf_field_value( $html, 'dt_login_slug' ), 'value="entrar-red"' ) && false !== strpos( tf_field_value( $html, 'dt_login_slug' ), 'disabled' ), 'Sitio de la red: muestra el valor heredado de la red, deshabilitado' );
	tf_screen( 2 );
	check( 'entrar-red' === Site_Options::get( 'login_slug' ) && 1 === Site_Options::get( 'mobile_scroll' ), 'Sitio de la red: aplica lo de la red' );
	// El script deshabilita el campo al desmarcar «Heredar de la red»; aquí se emula desmarcando y escribiendo.
	$back = tf_submit( $html, Site_Options::ACTION, array( 'inherit[login_slug]' => false, 'values[login_slug]' => 'acceso-dos' ), array( 'enable' => array( 'values[login_slug]' ) ) );
	check( 0 === strpos( $back, 'https://red.test/s2/wp-admin/admin.php?' ), 'Sitio de la red: vuelve al escritorio del mismo sitio: ' . $back );
	tf_screen( 2 );
	check( 'acceso-dos' === Site_Options::get( 'login_slug' ) && 1 === Site_Options::get( 'mobile_scroll' ) && false === Site_Options::inherits( 'login_slug' ) && Site_Options::inherits( 'mobile_scroll' ), 'Sitio de la red: personaliza la ruta y sigue heredando lo demás' );
	check( 'entrar-red' === $GLOBALS['tf']['site'][ Site_Options::NETWORK ]['login_slug'], 'Sitio de la red: no toca el valor de la red' );
	tf_screen( 3 );
	check( 'entrar-red' === Site_Options::get( 'login_slug' ) && ! isset( $GLOBALS['tf']['options'][3][ Site_Options::SITE ]['values']['login_slug'] ), 'Sitio de la red: otro sitio sigue con lo de la red' );
	$html = tf_follow( $back );
	check( false !== strpos( tf_field_value( $html, 'dt_login_slug' ), 'value="acceso-dos"' ) && false === strpos( tf_field_value( $html, 'dt_login_slug' ), 'disabled' ), 'Sitio de la red: al recargar se ve su ruta, editable' );
	// Volver a guardar sin tocar nada conserva todo (la ruta propia y la herencia de lo demás).
	tf_submit( $html, Site_Options::ACTION );
	tf_screen( 2 );
	check( 'acceso-dos' === Site_Options::get( 'login_slug' ) && Site_Options::inherits( 'mobile_scroll' ) && 1 === Site_Options::get( 'mobile_scroll' ), 'Sitio de la red: guardar otra vez no borra nada' );
	// La red cambia un valor heredado: el sitio lo ve sin guardar nada.
	$GLOBALS['tf']['site'][ Site_Options::NETWORK ]['mobile_scroll'] = 0;
	check( 0 === Site_Options::get( 'mobile_scroll' ), 'Sitio de la red: lo heredado sigue a la red' );
	// Un sitio sin permiso de red no puede escribir la red mandando scope=network.
	$GLOBALS['tf']['caps'] = array( 'manage_options' );
	$html = tf_page( 'site', 3 );
	$form = browser_form_find( $html, Site_Options::ACTION );
	$sent = browser_form_submit( $form, array( 'scope' => 'network', 'values[login_slug]' => 'robada' ) );
	tf_screen( 3 ); $_POST = $sent['post'];
	try { Site_Options::save(); check( false, 'scope=network sin permiso de red debía rechazarse' ); } catch ( Tf_Die $e ) { check( 'entrar-red' === $GLOBALS['tf']['site'][ Site_Options::NETWORK ]['login_slug'], 'Un administrador de sitio no escribe la red' ); }

	/* ---------------------------------------------------------------- Plantillas de red */

	tf_world( true );
	// El sitio maestro es el 2 (no el principal) y DIGITALÍSIMO Elements sólo está activo ahí.
	$GLOBALS['tf']['site'][ Registry::SETTINGS ] = array( 'master_blog_id' => 1, 'log_limit' => 200 );
	$html = tf_page( 'elementor', 1, true );
	$back = tf_submit( $html, 'digitalisimo_tools_save_settings', array( 'master_blog_id' => '2', 'log_limit' => '300' ) );
	check( array( 'master_blog_id' => 2, 'log_limit' => 300 ) === $GLOBALS['tf']['site'][ Registry::SETTINGS ] && 0 === strpos( $back, 'https://red.test/wp-admin/network/admin.php?' ), 'Plantillas: la configuración de red se guarda y vuelve a la red' );
	$GLOBALS['tf']['options'][2]['active_plugins'] = array( 'digitalisimo-elements/pro-elements.php' );
	tf_post( 2, 11, 'Cabecera' );
	tf_post( 2, 12, 'Pie', '[{"settings":{"template_id":"11"}}]' );
	$html = tf_follow( $back );
	check( false !== strpos( $html, "value=\"2\" selected='selected'" ), 'Plantillas: al recargar, el sitio maestro guardado' );
	check( false === strpos( $html, 'debe estar activo en el sitio maestro' ), 'Plantillas: con Elements activo en el sitio maestro no dice que falta' );
	check( false !== strpos( $html, 'Cabecera (#11)' ) && false !== strpos( $html, 'Pie (#12)' ), 'Plantillas: lista las plantillas del sitio maestro' );
	$back = tf_submit( $html, 'digitalisimo_tools_save_template', array( 'master_post_id' => '11', 'uuid' => 'cabecera-red', 'collection' => 'Marca', 'auto_sync' => true, 'target_mode' => 'selected', 'include[]' => true ) );
	$item = Registry::get( 'cabecera-red' );
	check( $item && 11 === $item['master_post_id'] && 2 === $item['master_blog_id'] && 'Marca' === $item['collection'] && true === $item['auto_sync'] && false === $item['locked'] && true === $item['active'] && 'selected' === $item['targets']['mode'], 'Plantillas: la plantilla global se guarda con lo marcado' );
	check( array( 1, 3 ) === $item['targets']['include'], 'Plantillas: los destinos marcados viajan' );
	// Sincronizar: el trabajo en cola corre aunque el sitio de la petición (el principal) no cargue Elements.
	$html = tf_follow( $back );
	tf_submit( $html, 'digitalisimo_tools_sync_template', array( 'blog_id' => '3' ) );
	check( array( array( Sync::CRON, array( 'cabecera-red', 3 ) ) ) === $GLOBALS['tf']['cron'][1], 'Plantillas: «Sincronizar» deja el trabajo en la cola del sitio que atiende la petición' );
	tf_screen( 1 );
	Sync::run_job( 'cabecera-red', 3 );
	$copies = array_values( $GLOBALS['tf']['posts'][3] ?? array() );
	check( 1 === count( $copies ) && 'Cabecera' === $copies[0]->post_title, 'Plantillas: el trabajo copia la plantilla en el sitio 3 aunque el sitio principal no tenga Elements' );
	check( isset( $GLOBALS['tf']['site'][ Registry::OPTION ]['cabecera-red']['last_sync'][3] ), 'Plantillas: queda la fecha de la sincronización' );
	check( array( 1 ) === $GLOBALS['tf']['stack'] || array() === $GLOBALS['tf']['stack'], 'Plantillas: la copia vuelve a su sitio' );
	// Volver a guardar la configuración de la plantilla no borra su «Última sincronización» ni sus copias.
	$html = tf_page( 'elementor', 1, true, array( 'edit' => 'cabecera-red' ) );
	check( false !== strpos( $html, 'Configurar plantilla global' ) && false === strpos( $html, '>Pendiente<' ), 'Plantillas: la fila dice que está sincronizada' );
	tf_submit( $html, 'digitalisimo_tools_save_template', array( 'locked' => true ) );
	$item = Registry::get( 'cabecera-red' );
	check( true === $item['locked'] && isset( $item['last_sync'][3], $item['sites'][3] ), 'Plantillas: guardar la configuración conserva «Última sincronización» y las copias' );
	$html = tf_page( 'elementor', 1, true );
	check( false === strpos( $html, '>Pendiente<' ) && false !== strpos( $html, '>Sincronizada<' ), 'Plantillas: tras guardar, sigue «Sincronizada»' );
	// Sin Elements en el sitio maestro, sí lo dice.
	$GLOBALS['tf']['options'][2]['active_plugins'] = array();
	$html = tf_page( 'elementor', 1, true );
	check( false !== strpos( $html, 'debe estar activo en el sitio maestro' ), 'Plantillas: sin Elements en el sitio maestro, lo avisa' );
	// Elements activo en toda la red también cuenta.
	$GLOBALS['tf']['site']['active_sitewide_plugins'] = array( 'mi-carpeta/pro-elements.php' => time() );
	$html = tf_page( 'elementor', 1, true );
	check( false === strpos( $html, 'debe estar activo en el sitio maestro' ) && false !== strpos( $html, 'Cabecera (#11)' ), 'Plantillas: Elements activo en la red cuenta, en cualquier carpeta' );
	// Sitio individual: no hay plantillas de red; la pestaña por defecto es Optimizar medios (no se pinta aquí).
	tf_world( false );
	check( ! Registry::destinations( array( 'targets' => array(), 'master_blog_id' => 1 ) ), 'Sitio individual: sin destinos de red' );

	echo "Tools, formularios en sitio, sitio de red y red: $checks comprobaciones correctas.\n";
}
