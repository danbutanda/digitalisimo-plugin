<?php
/**
 * DIGITALÍSIMO Elements · formularios propios como un navegador, en sitio individual, en un sitio de la red y en la red
 * (paridad sitio/red, 2026-11-22). Pinta la pantalla real, la envía con lo que mandaría un navegador a admin-post.php del
 * sitio que la pintó y comprueba lo guardado y lo que se ve al recargar.
 *
 * - Google Reviews: la clave guardada se pinta como `••••` («deben salir los *****», propietario, 2026-11-22) y nunca
 *   sale en el HTML; enviar sin tocarla la conserva, una nueva la reemplaza y «Quitar la clave» la borra; el sitio
 *   sin clave propia dice que hereda la de la red; guardar en un sitio no toca la red ni otro sitio.
 */
namespace {
	error_reporting( E_ALL );
	define( 'ABSPATH', __DIR__ . '/' );
	require __DIR__ . '/lib/browser-submit.php';
	final class Ef_Redirect extends Exception {}
	final class Ef_Die extends Exception {}
	$checks = 0;
	function check( $ok, $message ) { global $checks; if ( ! $ok ) { fwrite( STDERR, "FALLA: $message\n" ); exit( 1 ); } $checks++; }

	function ef_world( $multisite ) { $GLOBALS['ef'] = array( 'multisite' => $multisite, 'network_admin' => false, 'blog' => 1, 'options' => array( 1 => array(), 2 => array(), 3 => array() ), 'site' => array(), 'caps' => array( 'manage_options', 'manage_network_options' ) ); }
	function ef_home( $blog ) { return $GLOBALS['ef']['multisite'] ? 'https://red.test' . ( 1 === $blog ? '' : '/s' . $blog ) : 'https://sitio.test'; }
	function is_multisite() { return $GLOBALS['ef']['multisite']; }
	function is_network_admin() { return $GLOBALS['ef']['network_admin']; }
	function get_current_blog_id() { return $GLOBALS['ef']['blog']; }
	function current_user_can( $c ) { return in_array( $c, $GLOBALS['ef']['caps'], true ); }
	function get_option( $k, $d = false ) { return $GLOBALS['ef']['options'][ get_current_blog_id() ][ $k ] ?? $d; }
	function update_option( $k, $v, $a = null ) { $GLOBALS['ef']['options'][ get_current_blog_id() ][ $k ] = $v; return true; }
	function delete_option( $k ) { unset( $GLOBALS['ef']['options'][ get_current_blog_id() ][ $k ] ); return true; }
	function get_site_option( $k, $d = false ) { if ( ! is_multisite() ) return get_option( $k, $d ); return $GLOBALS['ef']['site'][ $k ] ?? $d; }
	function update_site_option( $k, $v ) { if ( ! is_multisite() ) return update_option( $k, $v ); $GLOBALS['ef']['site'][ $k ] = $v; return true; }
	function delete_site_option( $k ) { if ( ! is_multisite() ) return delete_option( $k ); unset( $GLOBALS['ef']['site'][ $k ] ); return true; }
	function admin_url( $p = '' ) { return ef_home( get_current_blog_id() ) . '/wp-admin/' . $p; }
	function network_admin_url( $p = '' ) { return 'https://red.test/wp-admin/network/' . $p; }
	function add_action() {} function add_options_page() {} function add_submenu_page() {}
	function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
	function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
	function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
	function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
	function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
	function wp_nonce_field( $a ) { echo '<input type="hidden" id="_wpnonce" name="_wpnonce" value="n:' . $a . ':' . get_current_blog_id() . '">'; }
	function check_admin_referer( $a ) { if ( ( $_POST['_wpnonce'] ?? '' ) !== 'n:' . $a . ':' . get_current_blog_id() ) throw new Ef_Die( "nonce $a" ); return 1; }
	function submit_button( $text = 'Guardar cambios' ) { echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="' . esc_attr( $text ) . '"></p>'; }
	function wp_safe_redirect( $u ) { throw new Ef_Redirect( $u ); }
	function wp_die( $m = '' ) { throw new Ef_Die( (string) $m ); }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-google-reviews-service.php';
	use Digitalisimo\Elements\Google_Reviews_Service as Reviews;
	const EF_KEY = 'digitalisimo_elements_google_places_key';

	function ef_page( $blog, $network_admin = false ) { $GLOBALS['ef']['blog'] = $blog; $GLOBALS['ef']['network_admin'] = $network_admin; $_GET = array(); ob_start(); Reviews::settings_page(); return ob_get_clean(); }
	function ef_submit( $html, array $fill = array() ) {
		$form = browser_form_find( $html, 'digitalisimo_elements_google_reviews_save' );
		check( null !== $form, 'La pantalla tiene su formulario' );
		$sent = browser_form_submit( $form, $fill, array( 'slash' => true ) );
		check( (bool) preg_match( '#^https://[^/]+(?:/s(\d+))?/wp-admin/admin-post\.php$#', $sent['action'], $m ), 'Va a admin-post.php de un sitio: ' . $sent['action'] );
		$GLOBALS['ef']['blog'] = empty( $m[1] ) ? 1 : (int) $m[1]; $GLOBALS['ef']['network_admin'] = false;
		$_POST = $sent['post'];
		check( 'digitalisimo_elements_google_reviews_save' === ( $_POST['action'] ?? '' ), 'Manda la acción de su manejador' );
		try { Reviews::save(); } catch ( Ef_Redirect $r ) { return $r->getMessage(); }
		check( false, 'El guardado no redirige' );
	}
	function ef_key_input( $html ) { return preg_match( '#<input[^>]*id="digi-google-key"[^>]*>#', $html, $m ) ? $m[0] : ''; }

	foreach ( array( 'single' => array( false, 1, false ), 'subsite' => array( true, 2, false ), 'network' => array( true, 1, true ) ) as $mode => list( $ms, $blog, $net ) ) {
		ef_world( $ms );
		$html = ef_page( $blog, $net );
		check( false !== strpos( ef_key_input( $html ), 'value=""' ) && false !== strpos( $html, 'Sin clave configurada' ) && false === strpos( $html, 'Quitar la clave' ), "$mode: sin clave, el campo va vacío" );
		$back = ef_submit( $html, array( 'google_places_key' => 'AIza-primera' ) );
		$read = static function () use ( $net ) { return $net ? get_site_option( EF_KEY, '' ) : get_option( EF_KEY, '' ); };
		$GLOBALS['ef']['blog'] = $blog;
		check( 'AIza-primera' === $read(), "$mode: se guarda la clave escrita" );
		check( $net ? 0 === strpos( $back, 'https://red.test/wp-admin/network/settings.php' ) : 0 === strpos( $back, ef_home( $blog ) . '/wp-admin/options-general.php' ), "$mode: vuelve a su pantalla: $back" );
		$html = ef_page( $blog, $net );
		check( false !== strpos( ef_key_input( $html ), 'value="' . Reviews::MASK . '"' ) && false === strpos( $html, 'AIza-primera' ) && false !== strpos( $html, 'Guardada' ) && false !== strpos( $html, 'Quitar la clave' ), "$mode: al recargar, la máscara y «Guardada», nunca la clave" );
		ef_submit( $html );
		$GLOBALS['ef']['blog'] = $blog;
		check( 'AIza-primera' === $read(), "$mode: enviar sin tocar la máscara la conserva" );
		ef_submit( ef_page( $blog, $net ), array( 'google_places_key' => '' ) );
		$GLOBALS['ef']['blog'] = $blog;
		check( 'AIza-primera' === $read(), "$mode: vaciar el campo la conserva" );
		ef_submit( ef_page( $blog, $net ), array( 'google_places_key' => 'AIza-segunda' ) );
		$GLOBALS['ef']['blog'] = $blog;
		check( 'AIza-segunda' === $read(), "$mode: una nueva la reemplaza" );
		if ( 'subsite' === $mode ) {
			check( ! isset( $GLOBALS['ef']['site'][ EF_KEY ] ) && ! isset( $GLOBALS['ef']['options'][1][ EF_KEY ] ) && ! isset( $GLOBALS['ef']['options'][3][ EF_KEY ] ), 'subsite: no toca la red ni otro sitio' );
		}
		if ( 'network' === $mode ) {
			check( array() === $GLOBALS['ef']['options'][1], 'network: no escribe la opción del sitio principal' );
			$html = ef_page( 3 );
			check( false !== strpos( ef_key_input( $html ), 'value=""' ) && false !== strpos( $html, 'Heredada de la red' ) && false === strpos( $html, 'AIza-segunda' ) && 'AIza-segunda' === Reviews::api_key(), 'network: un sitio sin clave propia hereda la de la red y lo dice' );
		}
		ef_submit( ef_page( $blog, $net ), array( 'remove_key' => true ) );
		$GLOBALS['ef']['blog'] = $blog;
		check( '' === $read(), "$mode: «Quitar la clave» la borra" );
	}

	echo "Elements, formularios en sitio, sitio de red y red: $checks comprobaciones correctas.\n";
}
