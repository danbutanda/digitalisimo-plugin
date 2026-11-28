<?php
/**
 * Marca propia: sólo DIGITALÍSIMO la enciende (N-1 de la segunda revisión, docs/MARCA_PROPIA.md), sin WordPress:
 * - P1 sin `DIGITALISIMO_ELEMENTS_MARCA_PANEL` la sección «Marca propia en el sitio» no aparece (ni en el sitio ni en la
 *   red) y `handle_save` / `handle_save_network` rechazan el envío sin guardar nada;
 * - P2 con la constante en true aparece y se guarda;
 * - P3 guardar «Encendida» sin paquete transformado no purga las cachés de página ni marca originales para retirar
 *   (`Package::remove_originals()`), en un sitio suelto ni en una red, y `Package::network_all_on()` no recorre la red ni
 *   escribe el transitorio de complementos en cada sitio;
 * - P4 `DIGITALISIMO_ELEMENTS_MARCA` (`on` u `off`) manda sobre la opción del sitio y la de la red (en procesos hijos:
 *   una constante no se puede redefinir).
 * Falla con el código anterior: la sección aparece y se guarda sin la constante y guardar «Encendida» purga las cachés.
 */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', 'test' );
	$tmp = sys_get_temp_dir() . '/digi-marca-panel-' . getmypid();
	define( 'WP_CONTENT_DIR', $tmp . '/wp-content' );
	error_reporting( E_ALL );
	set_error_handler(
		static function ( $no, $str, $file, $line ) {
			echo "FALLA aviso de PHP: $str ($file:$line)\n";
			$GLOBALS['fails']++;
			return true;
		}
	);
	$GLOBALS['fails']  = 0;
	$GLOBALS['checks'] = 0;
	function check( $ok, $what, $detail = '' ) {
		$GLOBALS['checks']++;
		if ( $ok ) {
			echo "ok · $what\n";
		} else {
			$GLOBALS['fails']++;
			echo "FALLA · $what" . ( '' !== $detail ? ' · ' . substr( is_string( $detail ) ? $detail : json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), 0, 900 ) : '' ) . "\n";
		}
	}

	$GLOBALS['wp_filter']  = array();
	$GLOBALS['options']    = array();
	$GLOBALS['site']       = array();
	$GLOBALS['multisite']  = false;
	$GLOBALS['transients'] = array();
	$GLOBALS['actions']    = array();
	$GLOBALS['switched']   = 0;
	$GLOBALS['sites_read'] = 0;
	function add_filter( $tag, $cb, $prio = 10, $args = 1 ) {
		$GLOBALS['wp_filter'][ $tag ][ $prio ][] = array( 'function' => $cb, 'accepted_args' => $args );
		return true;
	}
	function add_action( $tag, $cb, $prio = 10, $args = 1 ) {
		return add_filter( $tag, $cb, $prio, $args );
	}
	function apply_filters( $tag, $value ) {
		return $value;
	}
	function do_action( $tag ) {
		$GLOBALS['actions'][] = $tag;
	}
	function did_action( $t ) {
		return 1;
	}
	function is_admin() {
		return true;
	}
	function get_option( $k, $d = false ) {
		return array_key_exists( $k, $GLOBALS['options'] ) ? $GLOBALS['options'][ $k ] : $d;
	}
	function wp_load_alloptions() {
		return null;
	}
	function update_option( $k, $v, $a = null ) {
		$GLOBALS['options'][ $k ] = $v;
		return true;
	}
	function get_site_option( $k, $d = false ) {
		return array_key_exists( $k, $GLOBALS['site'] ) ? $GLOBALS['site'][ $k ] : $d;
	}
	function update_site_option( $k, $v ) {
		$GLOBALS['site'][ $k ] = $v;
		return true;
	}
	function is_multisite() {
		return $GLOBALS['multisite'];
	}
	function get_sites( $a = array() ) {
		$GLOBALS['sites_read']++;
		return array( 1, 2, 3 );
	}
	function switch_to_blog( $id ) {
		$GLOBALS['switched']++;
	}
	function restore_current_blog() {}
	function get_transient( $k ) {
		return $GLOBALS['transients'][ $k ] ?? false;
	}
	function set_transient( $k, $v, $e = 0 ) {
		$GLOBALS['transients'][ $k ] = $v;
		$GLOBALS['transient_writes'] = ( $GLOBALS['transient_writes'] ?? 0 ) + 1;
		return true;
	}
	function delete_transient( $k ) {
		unset( $GLOBALS['transients'][ $k ] );
		return true;
	}
	function get_template() {
		return 'digitalisimo-theme';
	}
	function get_stylesheet() {
		return 'digitalisimo-theme';
	}
	function wp_json_encode( $v, $f = 0 ) {
		return json_encode( $v, $f );
	}
	function esc_html( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES );
	}
	function esc_attr( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES );
	}
	function esc_url( $t ) {
		return (string) $t;
	}
	function admin_url( $p = '' ) {
		return 'http://ensayo.test/wp-admin/' . ltrim( $p, '/' );
	}
	function network_admin_url( $p = '' ) {
		return 'http://ensayo.test/wp-admin/network/' . ltrim( $p, '/' );
	}
	function rest_url( $p = '' ) {
		return 'http://ensayo.test/wp-json/' . ltrim( $p, '/' );
	}
	function content_url( $p = '' ) {
		return 'http://ensayo.test/wp-content/' . $p;
	}
	function add_query_arg( $args, $url ) {
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
	}
	function wp_nonce_field( $a ) {
		echo '<input type="hidden" name="_wpnonce" value="n">';
	}
	function selected( $a, $b, $echo = true ) {
		return (string) $a === (string) $b ? ' selected="selected"' : '';
	}
	function checked( $a, $b, $echo = true ) {
		return $a == $b ? ' checked="checked"' : ''; // phpcs:ignore
	}
	function get_current_blog_id() {
		return 1;
	}
	function sanitize_key( $k ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $k ) );
	}
	function sanitize_text_field( $v ) {
		return trim( (string) $v );
	}
	function wp_unslash( $v ) {
		return $v;
	}
	function current_user_can( $c ) {
		return true;
	}
	function wp_verify_nonce( $n, $a ) {
		return 'n' === $n;
	}
	final class Redirected extends \Exception {}
	function wp_safe_redirect( $url ) {
		throw new Redirected( $url );
	}

	final class Digitalisimo_Elements_Core {
		public static function bundled() {
			return true;
		}
	}

	$el = dirname( __DIR__ ) . '/digitalisimo-elements';
	require $el . '/modules/digitalisimo-marca/class-module.php';
	require $el . '/modules/digitalisimo-mcp/class-brand-server.php';
	require $el . '/modules/digitalisimo-mcp/class-legacy-route.php';

	use Digitalisimo\Elements\Marca\Package;
	use Digitalisimo\Elements\Marca\Settings_Section;
	use Digitalisimo\Elements\Marca\Toggle;

	// P4 (proceso hijo): con la constante DIGITALISIMO_ELEMENTS_MARCA, ¿qué decide Toggle? Escribe una línea JSON.
	if ( isset( $argv[1] ) && 0 === strpos( $argv[1], '--marca=' ) ) {
		$value = substr( $argv[1], 8 );
		define( 'DIGITALISIMO_ELEMENTS_MARCA', 'true' === $value ? true : $value );
		$out = array();
		foreach ( array( 'off', 'on' ) as $stored ) {
			$GLOBALS['multisite'] = false;
			$GLOBALS['options']   = array( Toggle::OPTION => array( 'mode' => $stored, 'force' => false ) );
			$GLOBALS['site']      = array();
			Toggle::reset();
			$out[ 'sitio-' . $stored ] = array( Toggle::values()['mode'], Toggle::values()['origin'], Toggle::active() );
			$GLOBALS['multisite'] = true;
			$GLOBALS['options']   = array();
			$GLOBALS['site']      = array( Toggle::NETWORK_OPTION => array( 'mode' => $stored, 'force' => false ) );
			Toggle::reset();
			$out[ 'red-' . $stored ] = array( Toggle::values()['mode'], Toggle::values()['origin'], Toggle::active() );
		}
		echo json_encode( $out ) . "\n";
		exit( 0 );
	}

	/** Pinta la sección y devuelve el HTML. */
	function mp_render( bool $network ): string {
		ob_start();
		Settings_Section::render( $network );
		return (string) ob_get_clean();
	}

	/** Envía el formulario a un manejador y devuelve la URL de la redirección. */
	function mp_post( string $handler, array $fields ): string {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = array(
			'_wpnonce'   => 'n',
			Toggle::OPTION => $fields,
		);
		try {
			call_user_func( array( Settings_Section::class, $handler ) );
		} catch ( Redirected $r ) {
			return $r->getMessage();
		} finally {
			$_POST = array();
		}
		return '';
	}

	// Una carpeta de originales de otra versión: si algo los «retira», le escribe `.retirar`.
	$base = WP_CONTENT_DIR . '/uploads/digitalisimo-elements/base-0.9';
	@mkdir( $base, 0777, true );

	// P1 -----------------------------------------------------------------------------------------------------------------
	check( method_exists( Toggle::class, 'panel' ) && ! Toggle::panel(), 'P1 sin la constante, Toggle::panel() es falso' );
	check( '' === mp_render( false ) && '' === mp_render( true ), 'P1 sin DIGITALISIMO_ELEMENTS_MARCA_PANEL, la sección no aparece en el sitio ni en la red', mp_render( false ) );
	$url = mp_post( 'handle_save', array( 'mode' => 'on', 'force' => '1' ) );
	check( false !== strpos( $url, 'marca_notice=forbidden' ) && ! array_key_exists( Toggle::OPTION, $GLOBALS['options'] ), 'P1 sin la constante, handle_save rechaza el envío y no guarda', array( $url, $GLOBALS['options'] ) );
	$GLOBALS['multisite'] = true;
	$url                  = mp_post( 'handle_save_network', array( 'mode' => 'on' ) );
	check( false !== strpos( $url, 'marca_notice=forbidden' ) && array() === $GLOBALS['site'], 'P1 sin la constante, handle_save_network rechaza el envío y no guarda', array( $url, $GLOBALS['site'] ) );
	$GLOBALS['multisite'] = false;
	check( array() === $GLOBALS['actions'] && ! is_file( $base . '/.retirar' ), 'P1 y no purga nada' );

	// P2 -----------------------------------------------------------------------------------------------------------------
	define( 'DIGITALISIMO_ELEMENTS_MARCA_PANEL', true );
	check( method_exists( Toggle::class, 'panel' ) && Toggle::panel(), 'P2 con la constante en true, Toggle::panel() es verdadero' );
	$html = mp_render( false );
	check( false !== strpos( $html, 'id="digitalisimo-marca"' ) && false !== strpos( $html, 'action" value="digitalisimo_elements_marca_save"' ) && false === strpos( $html, 'digitalisimo-marca-constant' ), 'P2 con la constante, la sección aparece con su formulario', $html );

	// P3 -----------------------------------------------------------------------------------------------------------------
	check( ! Package::transformed(), 'P3 el repositorio no trae el paquete transformado (ZIP de siempre)' );
	$url = mp_post( 'handle_save', array( 'mode' => 'on', 'force' => '0' ) );
	check( false !== strpos( $url, 'marca_notice=saved' ) && array( 'mode' => 'on', 'force' => false ) === ( $GLOBALS['options'][ Toggle::OPTION ] ?? null ), 'P2 con la constante, handle_save guarda', array( $url, $GLOBALS['options'] ) );
	check( ! in_array( 'litespeed_purge_all', $GLOBALS['actions'], true ) && ! is_file( $base . '/.retirar' ), 'P3 guardar «Encendida» sin paquete transformado no purga las cachés ni retira originales (class-settings-section.php:140)', $GLOBALS['actions'] );
	$GLOBALS['multisite']        = true;
	$GLOBALS['options']          = array();
	$GLOBALS['transient_writes'] = 0;
	mp_post( 'handle_save', array( 'mode' => 'on', 'force' => '0', 'inherit' => '0' ) );
	check( 0 === $GLOBALS['sites_read'] && 0 === $GLOBALS['switched'] && ! in_array( 'litespeed_purge_all', $GLOBALS['actions'], true ), 'P3 en una red, guardar «Encendida» en un sitio no recorre los sitios ni purga', array( $GLOBALS['sites_read'], $GLOBALS['switched'], $GLOBALS['actions'] ) );
	$url = mp_post( 'handle_save_network', array( 'mode' => 'on' ) );
	check( false !== strpos( $url, 'marca_notice=network-saved' ) && 'on' === ( $GLOBALS['site'][ Toggle::NETWORK_OPTION ]['mode'] ?? '' ) && 0 === $GLOBALS['switched'], 'P2 en la red, con la constante se guarda (sin recorrer los sitios)', array( $url, $GLOBALS['site'] ) );
	$GLOBALS['transient_writes'] = 0;
	check( false === Package::network_all_on() && 0 === $GLOBALS['sites_read'] && 0 === $GLOBALS['switched'] && 0 === $GLOBALS['transient_writes'], 'P3 network_all_on() sin paquete transformado no recorre la red ni escribe transitorios', array( $GLOBALS['sites_read'], $GLOBALS['switched'], $GLOBALS['transient_writes'] ) );
	check( false === Package::remove_originals() && ! is_file( $base . '/.retirar' ) && ! in_array( 'litespeed_purge_all', $GLOBALS['actions'], true ), 'P3 remove_originals() sin paquete transformado no hace nada' );
	$GLOBALS['multisite'] = false;

	// P4 -----------------------------------------------------------------------------------------------------------------
	$run = static function ( $value ) {
		$out = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( '--marca=' . $value ) . ' 2>&1' );
		$row = json_decode( trim( (string) $out ), true );
		return is_array( $row ) ? $row : (string) $out;
	};
	$off = $run( 'off' );
	check( is_array( $off ) && array( 'off', 'constant', false ) === $off['sitio-on'] && array( 'off', 'constant', false ) === $off['red-on'], 'P4 DIGITALISIMO_ELEMENTS_MARCA=off apaga aunque el sitio o la red la tengan encendida', $off );
	$on = $run( 'on' );
	check( is_array( $on ) && array( 'on', 'constant', true ) === $on['sitio-off'] && array( 'on', 'constant', true ) === $on['red-off'], 'P4 DIGITALISIMO_ELEMENTS_MARCA=on enciende aunque la opción diga apagada', $on );
	$bool = $run( 'true' );
	check( is_array( $bool ) && array( 'on', 'constant', true ) === $bool['sitio-off'], 'P4 también con true', $bool );
	$junk = $run( 'quizá' );
	check( is_array( $junk ) && array( 'on', 'site', true ) === $junk['sitio-on'] && array( 'off', 'site', false ) === $junk['sitio-off'], 'P4 un valor no válido no cuenta: decide la opción', $junk );
	$GLOBALS['options'] = array( Toggle::OPTION => array( 'mode' => 'off', 'force' => false ) );
	Toggle::reset();
	check( array( 'off', 'site' ) === array( Toggle::values()['mode'], Toggle::values()['origin'] ), 'P4 sin la constante, decide la opción' );

	if ( is_file( $base . '/.retirar' ) ) {
		unlink( $base . '/.retirar' );
	}
	@rmdir( $base );
	@rmdir( dirname( $base ) );
	@rmdir( WP_CONTENT_DIR . '/uploads' );
	@rmdir( WP_CONTENT_DIR );
	@rmdir( $tmp );
	echo "\n$GLOBALS[fails] fallos de $GLOBALS[checks] comprobaciones\n";
	exit( $GLOBALS['fails'] ? 1 : 0 );
}
