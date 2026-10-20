<?php
/**
 * DIGITALÍSIMO Tools · Código PHP: datos, bloqueo, firma, ejecución, modo seguro, permisos, atribución en proceso,
 * conciliación y rescate (núcleo); revisión, prueba previa y criterio, manejador, pantalla de sitio y de red, avisos, escape e
 * importación (segunda parte). Con stubs de WordPress y un $wpdb falso que entiende INSERT IGNORE, `alloptions` y la caché.
 *
 * Se juzga por la última línea («… OK»): php-wasm sale con 0 aunque haya un error fatal.
 */
namespace {
	error_reporting( E_ALL );
	$root = sys_get_temp_dir() . '/digitalisimo-php-snippets-' . bin2hex( random_bytes( 4 ) );
	mkdir( $root . '/wp-includes', 0700, true );
	define( 'ABSPATH', $root . '/' );
	define( 'WPINC', 'wp-includes' );
	define( 'ARRAY_A', 'ARRAY_A' );
	define( 'OBJECT', 'OBJECT' );
	define( 'COOKIEPATH', '/' );
	define( 'SITECOOKIEPATH', '/' );
	define( 'COOKIE_DOMAIN', false );
	define( 'HOUR_IN_SECONDS', 3600 );
	ini_set( 'error_log', $root . '/error.log' );

	$GLOBALS['t'] = array();
	function reset_world( $multisite = false ) {
		$GLOBALS['t'] = array(
			'multisite' => $multisite, 'blog' => 1, 'network' => 1, 'main' => 1, 'blogs' => array( 1, 2, 3 ), 'stack' => array(),
			'db' => array(), 'sitemeta' => array(), 'cache' => array(), 'transients' => array(), 'hooks' => array(), 'filters' => array(),
			'user' => 1, 'caps' => array( 'edit_themes', 'unfiltered_html', 'manage_options', 'manage_network_options' ),
			'salt' => array( 'secure_auth' => 'sal-segura-1', 'auth' => 'sal-auth-1' ), 'get_option' => array(), 'queries' => array(),
			'mails' => array(), 'cron' => array(), 'autoload' => array(), 'logged_in' => true, 'events' => array(),
		);
		foreach ( array( 1, 2, 3 ) as $blog ) $GLOBALS['t']['db'][ table_of( $blog ) ] = array( 'admin_email' => array( 'v' => "admin$blog@sitio.test", 'a' => 'on' ), 'blogname' => array( 'v' => "Sitio $blog", 'a' => 'on' ) );
		$GLOBALS['t']['sitemeta']['admin_email'] = 'red@sitio.test';
		$GLOBALS['pagenow'] = 'index.php';
		$_GET = $_POST = $_COOKIE = $_REQUEST = array();
		$_SERVER['REQUEST_URI'] = '/';
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		\Digitalisimo\Tools\Php_Snippets\Store::forget_key();
		reset_statics();
	}

	class WP_Error {
		public $code; public $message; public $data;
		public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
		public function get_error_data() { return $this->data; }
	}
	function is_wp_error( $v ) { return $v instanceof WP_Error; }

	/* --- Base de datos falsa ------------------------------------------------- */
	function table_of( $blog ) { return 1 === (int) $blog ? 'wp_options' : 'wp_' . (int) $blog . '_options'; }
	final class Fake_WPDB {
		public $prefix = 'wp_';
		/** Como wpdb: se vacía en cada consulta y se rellena si una tabla no existe (un sitio a medio crear o borrado). */
		public $last_error = '';
		public function __get( $name ) { return 'options' === $name ? table_of( $GLOBALS['t']['blog'] ) : null; }
		public function get_blog_prefix( $blog = null ) { $blog = null === $blog ? $GLOBALS['t']['blog'] : (int) $blog; return 1 === $blog ? 'wp_' : 'wp_' . $blog . '_'; }
		public function esc_like( $text ) { return addcslashes( $text, '_%\\' ); }
		public function prepare( $query, ...$args ) {
			if ( 1 === count( $args ) && is_array( $args[0] ) ) $args = $args[0];
			return preg_replace_callback( '/%[sd%]/', function ( $m ) use ( &$args ) {
				if ( '%%' === $m[0] ) return '%';
				$v = array_shift( $args );
				return '%d' === $m[0] ? (string) (int) $v : "'" . addslashes( (string) $v ) . "'";
			}, $query );
		}
		private static function lit( $s ) { return stripslashes( substr( $s, 1, -1 ) ); }
		const LIT = "'((?:[^'\\\\]|\\\\.)*)'";
		public function query( $sql ) {
			$GLOBALS['t']['queries'][] = $sql;
			$db = &$GLOBALS['t']['db'];
			if ( preg_match( '/^INSERT IGNORE INTO `(\w+)` \( option_name, option_value, autoload \) VALUES \( ' . self::LIT . ', ' . self::LIT . ', ' . self::LIT . ' \)$/s', $sql, $m ) ) {
				$name = stripslashes( $m[2] );
				if ( isset( $db[ $m[1] ][ $name ] ) ) return 0;
				$db[ $m[1] ][ $name ] = array( 'v' => stripslashes( $m[3] ), 'a' => stripslashes( $m[4] ) );
				return 1;
			}
			if ( preg_match( '/^UPDATE `(\w+)` SET option_value = ' . self::LIT . ' WHERE option_name = ' . self::LIT . ' AND option_value = ' . self::LIT . '$/s', $sql, $m ) ) {
				$name = stripslashes( $m[3] );
				if ( ! isset( $db[ $m[1] ][ $name ] ) || $db[ $m[1] ][ $name ]['v'] !== stripslashes( $m[4] ) ) return 0;
				$db[ $m[1] ][ $name ]['v'] = stripslashes( $m[2] );
				return 1;
			}
			if ( preg_match( '/^DELETE FROM `(\w+)` WHERE option_name = ' . self::LIT . ' AND option_value = ' . self::LIT . '$/s', $sql, $m ) ) {
				$name = stripslashes( $m[2] );
				if ( ! isset( $db[ $m[1] ][ $name ] ) || $db[ $m[1] ][ $name ]['v'] !== stripslashes( $m[3] ) ) return 0;
				unset( $db[ $m[1] ][ $name ] );
				return 1;
			}
			throw new RuntimeException( 'SQL no previsto: ' . $sql );
		}
		public function get_var( $sql ) {
			$GLOBALS['t']['queries'][] = $sql;
			if ( ! preg_match( '/^SELECT option_value FROM `(\w+)` WHERE option_name = ' . self::LIT . '$/s', $sql, $m ) ) throw new RuntimeException( 'SQL no previsto: ' . $sql );
			return $GLOBALS['t']['db'][ $m[1] ][ stripslashes( $m[2] ) ]['v'] ?? null;
		}
		public function get_results( $sql, $output = OBJECT ) {
			$GLOBALS['t']['queries'][] = $sql;
			$this->last_error = '';
			$rows = array();
			foreach ( explode( ' UNION ALL ', $sql ) as $part ) {
				if ( ! preg_match( '/^SELECT (?:(\d+) AS blog_id, )?option_name, option_value FROM `(\w+)` WHERE option_name LIKE ' . self::LIT . '(?: OR option_name IN \((.*)\))?$/s', $part, $m ) ) throw new RuntimeException( 'SQL no previsto: ' . $part );
				if ( ! isset( $GLOBALS['t']['db'][ $m[2] ] ) ) { $this->last_error = "Table '{$m[2]}' doesn't exist"; return array(); }
				$like = like_regex( stripslashes( $m[3] ) );
				$in   = array();
				if ( ! empty( $m[4] ) ) { preg_match_all( '/' . self::LIT . '/', $m[4], $all ); $in = array_map( 'stripslashes', $all[1] ); }
				foreach ( $GLOBALS['t']['db'][ $m[2] ] ?? array() as $name => $row ) {
					if ( ! preg_match( $like, $name ) && ! in_array( $name, $in, true ) ) continue;
					$out = array( 'option_name' => $name, 'option_value' => $row['v'] );
					if ( '' !== $m[1] ) $out = array( 'blog_id' => $m[1] ) + $out;
					$rows[] = $out;
				}
			}
			return $rows;
		}
	}
	function like_regex( $pattern ) {
		$re = '';
		for ( $i = 0, $n = strlen( $pattern ); $i < $n; $i++ ) {
			$c = $pattern[ $i ];
			if ( '\\' === $c && $i + 1 < $n ) { $re .= preg_quote( $pattern[ ++$i ], '/' ); continue; }
			$re .= '%' === $c ? '.*' : ( '_' === $c ? '.' : preg_quote( $c, '/' ) );
		}
		return '/^' . $re . '$/s';
	}
	$GLOBALS['wpdb'] = new Fake_WPDB();

	/* --- Caché y opciones ------------------------------------------------------ */
	function cache_key_blog( $group ) { return 'site-options' === $group ? 'network' : $GLOBALS['t']['blog']; }
	function wp_cache_delete( $key, $group = '' ) { unset( $GLOBALS['t']['cache'][ cache_key_blog( $group ) ][ $group ][ $key ] ); return true; }
	function cache_get( $key, $group ) { return $GLOBALS['t']['cache'][ cache_key_blog( $group ) ][ $group ][ $key ] ?? null; }
	function cache_set( $key, $value, $group ) { $GLOBALS['t']['cache'][ cache_key_blog( $group ) ][ $group ][ $key ] = $value; }
	function is_serialized( $v ) { if ( ! is_string( $v ) ) return false; $v = trim( $v ); if ( 'N;' === $v || 'b:0;' === $v ) return true; return (bool) preg_match( '/^[adObis]:/', $v ) && false !== @unserialize( $v ); }
	function maybe_unserialize( $v ) { return is_serialized( $v ) ? @unserialize( trim( $v ) ) : $v; }
	function maybe_serialize( $v ) { return ( is_array( $v ) || is_object( $v ) ) ? serialize( $v ) : $v; }
	function wp_autoload_values_to_autoload() { return array( 'yes', 'on', 'auto-on', 'auto' ); }
	function wp_load_alloptions() {
		$all = cache_get( 'alloptions', 'options' );
		if ( null !== $all ) return $all;
		$GLOBALS['t']['queries'][] = 'alloptions';
		$all = array();
		foreach ( $GLOBALS['t']['db'][ table_of( $GLOBALS['t']['blog'] ) ] ?? array() as $name => $row ) if ( in_array( $row['a'], wp_autoload_values_to_autoload(), true ) ) $all[ $name ] = $row['v'];
		cache_set( 'alloptions', $all, 'options' );
		return $all;
	}
	function get_option( $name, $default = false ) {
		$GLOBALS['t']['get_option'][] = $name;
		$all = wp_load_alloptions();
		if ( array_key_exists( $name, $all ) ) return maybe_unserialize( $all[ $name ] );
		$cached = cache_get( $name, 'options' );
		if ( null !== $cached ) return maybe_unserialize( $cached );
		$not = cache_get( 'notoptions', 'options' ) ?: array();
		if ( isset( $not[ $name ] ) ) return $default;
		$GLOBALS['t']['queries'][] = 'get:' . $name;
		$row = $GLOBALS['t']['db'][ table_of( $GLOBALS['t']['blog'] ) ][ $name ] ?? null;
		if ( null === $row ) { $not[ $name ] = true; cache_set( 'notoptions', $not, 'options' ); return $default; }
		cache_set( $name, $row['v'], 'options' );
		return maybe_unserialize( $row['v'] );
	}
	function update_option( $name, $value, $autoload = null ) {
		$table = table_of( $GLOBALS['t']['blog'] );
		$old   = $GLOBALS['t']['db'][ $table ][ $name ] ?? null;
		$GLOBALS['t']['autoload'][ $name ] = $autoload;
		$a = null === $autoload ? ( $old ? $old['a'] : 'auto' ) : ( $autoload ? 'on' : 'off' );
		$GLOBALS['t']['db'][ $table ][ $name ] = array( 'v' => maybe_serialize( $value ), 'a' => $a );
		wp_cache_delete( 'alloptions', 'options' ); wp_cache_delete( $name, 'options' ); wp_cache_delete( 'notoptions', 'options' );
		return true;
	}
	function add_option( $name, $value = '', $deprecated = '', $autoload = null ) { return update_option( $name, $value, $autoload ); }
	function delete_option( $name ) {
		unset( $GLOBALS['t']['db'][ table_of( $GLOBALS['t']['blog'] ) ][ $name ] );
		wp_cache_delete( 'alloptions', 'options' ); wp_cache_delete( $name, 'options' );
		return true;
	}
	function get_site_option( $name, $default = false ) {
		if ( ! is_multisite() ) return get_option( $name, $default );
		$GLOBALS['t']['get_site_option'][] = $name;
		return array_key_exists( $name, $GLOBALS['t']['sitemeta'] ) ? $GLOBALS['t']['sitemeta'][ $name ] : $default;
	}
	function update_site_option( $name, $value ) { if ( ! is_multisite() ) return update_option( $name, $value ); $GLOBALS['t']['sitemeta'][ $name ] = $value; return true; }
	function delete_site_option( $name ) { if ( ! is_multisite() ) return delete_option( $name ); unset( $GLOBALS['t']['sitemeta'][ $name ] ); return true; }
	function set_transient( $name, $value, $ttl = 0 ) { $GLOBALS['t']['transients'][ $GLOBALS['t']['blog'] ][ $name ] = $value; return true; }
	function get_transient( $name ) { return $GLOBALS['t']['transients'][ $GLOBALS['t']['blog'] ][ $name ] ?? false; }
	function set_site_transient( $name, $value, $ttl = 0 ) { $GLOBALS['t']['transients']['network'][ $name ] = $value; return true; }
	function wp_prime_network_option_caches( $network, array $options ) { $GLOBALS['t']['primed'] = array( $network, $options ); }

	/* --- Red y sitios ----------------------------------------------------------- */
	function is_multisite() { return $GLOBALS['t']['multisite']; }
	function get_current_blog_id() { return $GLOBALS['t']['blog']; }
	function get_current_network_id() { return $GLOBALS['t']['network']; }
	function get_main_site_id() { return $GLOBALS['t']['main']; }
	function is_main_site( $id = null ) { return (int) ( null === $id ? $GLOBALS['t']['blog'] : $id ) === $GLOBALS['t']['main']; }
	function switch_to_blog( $id ) { $GLOBALS['t']['stack'][] = $GLOBALS['t']['blog']; $GLOBALS['t']['blog'] = (int) $id; return true; }
	function restore_current_blog() { if ( ! $GLOBALS['t']['stack'] ) return false; $GLOBALS['t']['blog'] = array_pop( $GLOBALS['t']['stack'] ); return true; }
	function ms_is_switched() { return ! empty( $GLOBALS['t']['stack'] ); }
	function get_sites( $args = array() ) {
		$GLOBALS['t']['get_sites'][] = $args;
		$ids = $GLOBALS['t']['blogs'];
		if ( isset( $args['site__in'] ) ) $ids = array_values( array_intersect( $ids, array_map( 'intval', $args['site__in'] ) ) );
		if ( 'ids' === ( $args['fields'] ?? '' ) ) return $ids;
		return array_map( function ( $id ) { return (object) array( 'blog_id' => (string) $id, 'domain' => 'sitio' . $id . '.test', 'path' => '/' ); }, $ids );
	}
	function get_network() { return (object) array( 'site_name' => 'Red <b>Digi</b>' ); }

	/* --- Ganchos ---------------------------------------------------------------- */
	function add_filter( $tag, $cb, $priority = 10, $args = 1 ) { $GLOBALS['t']['hooks'][ $tag ][ $priority ][] = array( $cb, $args ); return true; }
	function add_action( $tag, $cb, $priority = 10, $args = 1 ) { return add_filter( $tag, $cb, $priority, $args ); }
	function apply_filters( $tag, $value, ...$rest ) {
		$hooks = $GLOBALS['t']['hooks'][ $tag ] ?? array();
		ksort( $hooks );
		$GLOBALS['t']['filters'][] = $tag;
		foreach ( $hooks as $list ) foreach ( $list as $h ) $value = call_user_func_array( $h[0], array_slice( array_merge( array( $value ), $rest ), 0, max( 1, $h[1] ) ) );
		array_pop( $GLOBALS['t']['filters'] );
		return $value;
	}
	function do_action( $tag, ...$args ) { apply_filters( $tag, ...( $args ?: array( null ) ) ); }
	function current_filter() { return end( $GLOBALS['t']['filters'] ); }
	function has_hook( $tag, $cb, $priority ) { foreach ( $GLOBALS['t']['hooks'][ $tag ][ $priority ] ?? array() as $h ) if ( $h[0] === $cb ) return true; return false; }

	/* --- Usuarios, permisos y utilidades ------------------------------------------ */
	function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['t']['caps'], true ); }
	function get_current_user_id() { return $GLOBALS['t']['logged_in'] ? $GLOBALS['t']['user'] : 0; }
	function is_user_logged_in() { return $GLOBALS['t']['logged_in']; }
	function wp_create_nonce( $action ) { return 'nonce|' . $action . '|' . get_current_user_id(); }
	function wp_verify_nonce( $nonce, $action ) { return $nonce === wp_create_nonce( $action ) ? 1 : false; }
	function wp_salt( $scheme ) { return $GLOBALS['t']['salt'][ $scheme ]; }
	function __( $text ) { return 'put your unique phrase here' === $text ? 'pon aquí tu frase única' : $text; }
	function sanitize_text_field( $v ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $v ) ) ); }
	function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
	function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return esc_html( $v ); }
	function esc_url( $v ) { return esc_html( $v ); }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $v ) ) ); }
	function wp_specialchars_decode( $v, $q = ENT_NOQUOTES ) { return htmlspecialchars_decode( (string) $v, $q ); }
	function is_email( $v ) { return false !== filter_var( $v, FILTER_VALIDATE_EMAIL ) ? $v : false; }
	function wp_mail( $to, $subject, $body, $headers = array() ) { $free = ! isset( $GLOBALS['t']['db']['wp_options']['digitalisimo_tools_php_site.lock'] ) && ! isset( $GLOBALS['t']['db'][ table_of( $GLOBALS['t']['blog'] ) ]['digitalisimo_tools_php_site.lock'] ); $GLOBALS['t']['mails'][] = compact( 'to', 'subject', 'body', 'headers', 'free' ); return true; }
	function wp_date( $format, $t ) { return gmdate( $format, $t ); }
	function home_url( $path = '/' ) { return 'https://sitio' . $GLOBALS['t']['blog'] . '.test' . $path; }
	function get_home_url( $blog, $path = '/' ) { $GLOBALS['t']['url_switches'] = ( $GLOBALS['t']['url_switches'] ?? 0 ) + 1; return 'https://sitio' . $blog . '.test' . $path; }
	function wp_login_url( $redirect = '' ) { return 'https://sitio' . $GLOBALS['t']['blog'] . '.test/acceso-equipo/'; }
	function site_url( $path = '' ) { return 'https://sitio' . $GLOBALS['t']['blog'] . '.test/' . ltrim( $path, '/' ); }
	function admin_url( $path = '' ) { return 'https://sitio' . $GLOBALS['t']['blog'] . '.test/wp-admin/' . $path; }
	function network_admin_url( $path = '' ) { return 'https://sitio1.test/wp-admin/network/' . $path; }
	function add_query_arg( $key, $value = null, $url = null ) {
		if ( is_array( $key ) ) { $url = $value; $args = $key; } else { $args = array( $key => $value ); }
		foreach ( $args as $k => $v ) $url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
		return $url;
	}
	function is_admin() { return ! empty( $GLOBALS['t']['is_admin'] ); }
	function wp_installing() { return false; }
	function is_ssl() { return true; }
	function wp_next_scheduled( $hook ) { return $GLOBALS['t']['cron'][ $GLOBALS['t']['blog'] ][ $hook ] ?? false; }
	function wp_schedule_event( $time, $recurrence, $hook ) { $GLOBALS['t']['cron'][ $GLOBALS['t']['blog'] ][ $hook ] = $time; return true; }
	function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['t']['cron'][ $GLOBALS['t']['blog'] ][ $hook ] ); return 1; }
	function wp_convert_hr_to_bytes( $v ) { return (int) $v * ( 'M' === strtoupper( substr( $v, -1 ) ) ? 1048576 : 1 ); }
	function wp_is_file_mod_allowed( $context ) { return apply_filters( 'file_mod_allowed', ! defined( 'DISALLOW_FILE_MODS' ) || ! DISALLOW_FILE_MODS, $context ); }
	function is_protected_endpoint() { return false; }
	function remove_accents( $v ) { return $v; }
	function sanitize_title_with_dashes( $v ) { return trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $v ) ), '-' ); }
	function get_page_by_path() { return null; }
	function get_term_by() { return false; }
	function untrailingslashit( $v ) { return rtrim( $v, '/\\' ); }
	function trailingslashit( $v ) { return untrailingslashit( $v ) . '/'; }

	function check( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FALLO: $message\n" ); exit( 1 ); } }
	function private_prop( $class, $name, $value = null, $set = false ) {
		$p = new ReflectionProperty( $class, $name );
		if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true );
		if ( $set ) $p->setValue( null, $value );
		return $p->getValue();
	}
	function reset_statics() {
		if ( ! class_exists( 'Digitalisimo\\Tools\\Php_Snippets\\Runner', false ) ) return;
		foreach ( array( 'started' => false, 'skipped' => null, 'loaded' => array() ) as $k => $v ) private_prop( 'Digitalisimo\\Tools\\Php_Snippets\\Runner', $k, $v, true );
		foreach ( array( 'handled' => false, 'ran' => array(), 'culprit' => null, 'test' => null, 'result' => array() ) as $k => $v ) private_prop( 'Digitalisimo\\Tools\\Php_Snippets\\Guard', $k, $v, true );
		\Digitalisimo\Tools\Php_Snippets\Guard::$running = null;
		private_prop( 'Digitalisimo\\Tools\\Php_Snippets\\Store', 'flagged', array(), true );
	}
	function row( $name, $blog = null ) { return $GLOBALS['t']['db'][ table_of( null === $blog ? $GLOBALS['t']['blog'] : $blog ) ][ $name ] ?? null; }
	function count_queries( $needle ) { return count( array_filter( $GLOBALS['t']['queries'], function ( $q ) use ( $needle ) { return false !== strpos( $q, $needle ); } ) ); }

	/* --- Stubs de la segunda parte (Tester, Admin, Import) --------------------------- */
	function delete_transient( $name ) { unset( $GLOBALS['t']['transients'][ $GLOBALS['t']['blog'] ][ $name ] ); return true; }
	function get_site_transient( $name ) { return is_multisite() ? ( $GLOBALS['t']['transients']['network'][ $name ] ?? false ) : get_transient( $name ); }
	function delete_site_transient( $name ) { if ( ! is_multisite() ) return delete_transient( $name ); unset( $GLOBALS['t']['transients']['network'][ $name ] ); return true; }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function remove_query_arg( $key, $url ) { $parts = explode( '?', $url, 2 ); if ( ! isset( $parts[1] ) ) return $url; parse_str( $parts[1], $q ); unset( $q[ $key ] ); return $parts[0] . ( $q ? '?' . http_build_query( $q ) : '' ); }
	function remove_filter( $tag, $cb, $priority = 10 ) { foreach ( $GLOBALS['t']['hooks'][ $tag ][ $priority ] ?? array() as $i => $h ) if ( $h[0] === $cb ) unset( $GLOBALS['t']['hooks'][ $tag ][ $priority ][ $i ] ); return true; }
	function wp_validate_redirect( $location, $fallback = '' ) { $host = parse_url( $location, PHP_URL_HOST ); if ( ! $host ) return $location; return in_array( $host, apply_filters( 'allowed_redirect_hosts', array( parse_url( home_url(), PHP_URL_HOST ) ), '' ), true ) ? $location : $fallback; }
	function esc_textarea( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
	function absint( $v ) { return abs( (int) $v ); }
	function get_userdata( $uid ) { return isset( $GLOBALS['t']['users'][ $uid ] ) ? (object) $GLOBALS['t']['users'][ $uid ] : false; }
	function user_can( $uid, $cap ) { return in_array( $cap, $GLOBALS['t']['users'][ $uid ]['caps'] ?? array(), true ); }
	function wp_get_current_user() { $u = get_userdata( get_current_user_id() ); return $u ? $u : (object) array( 'ID' => 0, 'user_email' => '', 'display_name' => '', 'user_login' => '' ); }
	function wp_text_diff( $l, $r, $args = array() ) { return $l === $r ? '' : '<table class="diff"><tr><td>' . esc_html( $l ) . '</td><td>' . esc_html( $r ) . '</td></tr></table>'; }
	function get_admin_url( $blog, $path = '' ) { $GLOBALS['t']['url_switches'] = ( $GLOBALS['t']['url_switches'] ?? 0 ) + 1; return 'https://sitio' . (int) $blog . '.test/wp-admin/' . $path; }
	function network_home_url( $path = '/' ) { return 'https://sitio1.test' . $path; }
	function nocache_headers() { $GLOBALS['t']['nocache'] = true; }
	function wp_doing_cron() { return false; }
	function wp_doing_ajax() { return ! empty( $GLOBALS['t']['ajax'] ); }
	function is_network_admin() { return ! empty( $GLOBALS['t']['network_admin'] ); }
	function plugin_basename( $file ) { return 'digitalisimo-tools/digitalisimo-tools.php'; }
	function plugins_url( $path, $file ) { return 'https://sitio1.test/wp-content/plugins/digitalisimo-tools/' . $path; }
	function wp_enqueue_script( $handle ) { $GLOBALS['t']['scripts'][] = $handle; }
	function wp_add_inline_script( $handle, $js, $position = 'after' ) { $GLOBALS['t']['inline'][ $handle ] = $js; return true; }
	function wp_enqueue_code_editor( $args ) { return array( 'codemirror' => array( 'mode' => $args['type'] ) ); }
	function get_blog_option( $blog, $name, $default = false ) { switch_to_blog( $blog ); $v = get_option( $name, $default ); restore_current_blog(); return $v; }

	require __DIR__ . '/../digitalisimo-tools/modules/site-options/class-hide-login.php';
	require __DIR__ . '/../digitalisimo-tools/modules/site-options/class-site-options.php';
	require __DIR__ . '/../digitalisimo-tools/modules/php-snippets/class-store.php';
	require __DIR__ . '/../digitalisimo-tools/modules/php-snippets/class-stream.php';
	require __DIR__ . '/../digitalisimo-tools/modules/php-snippets/class-guard.php';
	require __DIR__ . '/../digitalisimo-tools/modules/php-snippets/class-runner.php';
	require __DIR__ . '/../digitalisimo-tools/modules/php-snippets/class-tester.php';
	require __DIR__ . '/../digitalisimo-tools/modules/php-snippets/class-import.php';
	require __DIR__ . '/../digitalisimo-tools/modules/php-snippets/class-admin.php';

	$S = 'Digitalisimo\\Tools\\Php_Snippets\\Store';
	$A = 'Digitalisimo\\Tools\\Php_Snippets\\Access';
	$R = 'Digitalisimo\\Tools\\Php_Snippets\\Runner';
	$G = 'Digitalisimo\\Tools\\Php_Snippets\\Guard';
	$W = 'Digitalisimo\\Tools\\Php_Snippets\\Stream';
	$X = 'Digitalisimo\\Tools\\Php_Snippets\\Rescue';

	/** Crea y activa un fragmento con el Store real; devuelve su id. */
	function make( $scope, $title, $code ) {
		$S  = 'Digitalisimo\\Tools\\Php_Snippets\\Store';
		$id = $S::create( $scope, $title, $code, 1 );
		check( is_string( $id ), 'se crea el fragmento ' . $title );
		$r = $S::activate( $scope, $id, hash( 'sha256', $code ), array( 'by' => 1 ) );
		check( is_array( $r ), 'se activa el fragmento ' . $title . ( is_wp_error( $r ) ? ': ' . $r->get_error_message() : '' ) );
		return $id;
	}
	function run_now() {
		reset_statics();
		$GLOBALS['ran'] = array();
		ob_start();
		\Digitalisimo\Tools\Php_Snippets\Runner::run();
		return ob_get_clean();
	}

	/* =================================================================== *
	 * Arranque
	 * =================================================================== */
	reset_world( true );
	$_GET[ $X::PARAM ] = 'request';
	$R::boot();
	check( has_hook( 'after_setup_theme', array( $R, 'run' ), PHP_INT_MIN ), 'run en after_setup_theme con PHP_INT_MIN' );
	check( has_hook( 'plugins_loaded', array( $R, 'prime' ), 0 ) && has_hook( 'plugins_loaded', array( $X, 'handle' ), 2 ), 'precarga de red en plugins_loaded:0 y rescate en :2' );
	check( has_hook( $S::CRON, array( $S, 'cron' ), 10 ), 'cron horario registrado' );
	$R::prime();
	check( array( 1, array( 'digitalisimo_tools_site_defaults', $S::NETWORK_RUN ) ) === $GLOBALS['t']['primed'], 'una sola precarga para la opción de red y la de Ajustes del sitio' );

	/* =================================================================== *
	 * Saneamiento
	 * =================================================================== */
	reset_world();
	$in = $S::sanitize_item( array( 'title' => str_repeat( 'á', 130 ) . '<script>x</script>', 'code_b64' => base64_encode( "\xEF\xBB\xBF<?php\r\necho 1;\r\n?>\n\n " ) ), 'site' );
	check( 120 === mb_strlen( $in['title'] ) && false === strpos( $in['title'], '<' ), 'título saneado y recortado a 120' );
	check( "<?php\necho 1;\n" === $in['code'], 'quita BOM, \r\n y el ?> final' );
	check( is_wp_error( $S::sanitize_item( array( 'code_b64' => 'no es base64 ***' ), 'site' ) ), 'base64 estricto' );
	check( "<?php\necho 'a\\'b';\n" === $S::sanitize_item( array( 'code' => addslashes( "<?php\necho 'a\\'b';\n" ) ), 'network' )['code'], 'sin JavaScript el código llega con wp_unslash' );
	check( is_wp_error( $S::sanitize_item( array( 'code' => str_repeat( 'x', $S::MAX_SAVE + 1 ) ), 'site' ) ), 'tope de guardado' );
	check( "<?php\necho '?>';\n" === $S::normalize_code( "<?php\necho '?>';\n" ), 'un ?> dentro del código no se toca' );

	/* =================================================================== *
	 * Store: borrador, historial, registro, topes, bloqueo, indicador
	 * =================================================================== */
	$v1 = "<?php\n\$GLOBALS['ran'][] = 'v1';\n";
	$id = make( 'site', 'Mi fragmento', $v1 );
	check( '1' === get_option( $S::FLAG ) && 'on' === row( $S::FLAG )['a'], 'el indicador se crea con autoload explícito' );
	check( 'off' === row( $S::SITE_RUN )['a'] && false === $GLOBALS['t']['autoload'][ $S::SITE_RUN ], '_run sin autoload (false explícito)' );
	check( isset( $GLOBALS['t']['cron'][1][ $S::CRON ] ), 'el cron horario se programa con el indicador' );
	check( 'active' === $S::state( 'site', $id ), 'estado activo' );
	$v2 = "<?php\n\$GLOBALS['ran'][] = 'v2';\n";
	$saved = $S::save( 'site', $id, 'Mi fragmento', $v2, 1 );
	check( true === $saved['changed'] && hash( 'sha256', $v1 ) === $saved['active_hash'], 'guardar sin activar conserva la versión activa' );
	check( 'changed' === $S::state( 'site', $id ) && $v1 === $S::run( 'site' )['items'][ $id ]['code'], 'activo · cambios sin activar; sigue corriendo la versión firmada' );
	$item = $S::item( 'site', $id );
	check( 2 === count( $item['history'] ) && $item['history'][1]['activated'] && '' !== $item['history'][1]['sig'] && ! $item['history'][0]['activated'], 'historial: la versión activada guarda su firma' );
	$S::save( 'site', $id, 'Mi fragmento', $v2, 1 );
	check( 2 === count( $S::item( 'site', $id )['history'] ), 'el historial no duplica un hash' );
	for ( $i = 0; $i < 12; $i++ ) $S::save( 'site', $id, 'Mi fragmento', "<?php\n// v$i\n", 1 );
	check( 10 === count( $S::item( 'site', $id )['history'] ), 'el historial guarda como mucho 10 versiones' );
	for ( $i = 0; $i < 6; $i++ ) $S::save( 'site', $id, 'Mi fragmento', "<?php\n// " . str_repeat( (string) $i, 60000 ) . "\n", 1 );
	check( $S::history_bytes( $S::item( 'site', $id )['history'] ) <= $S::MAX_HISTORY_BYTES, 'el historial guarda como mucho 256 KB' );
	check( 'activated' === $S::push_history( array( array( 'code' => 'a', 'hash' => hash( 'sha256', 'a' ), 'event' => 'saved' ) ), array( 'code' => 'a', 'event' => 'activated', 'sig' => 's' ) )[0]['event'], 'activar un hash guardado lo marca activado' );
	check( true === $S::discard( 'site', $id, 1 ) && 'active' === $S::state( 'site', $id ), 'descartar devuelve el borrador a la versión activa' );
	check( is_wp_error( $S::delete( 'site', $id ) ), 'no se elimina un fragmento activo' );

	// Topes.
	check( is_wp_error( $S::check_limits( 'site', 'aaaaaaaa', str_repeat( 'x', $S::MAX_CODE + 1 ) ) ), 'tope de 64 KB por fragmento' );
	$big = "<?php\n// " . str_repeat( 'b', 60000 ) . "\n";
	make( 'site', 'Grande 1', $big );
	$big2 = make( 'site', 'Grande 2', $big . ' ' );
	check( is_wp_error( $S::check_limits( 'site', 'cccccccc', str_repeat( 'x', 20000 ) ) ), 'tope de 128 KB activos por ámbito' );
	check( true === $S::check_limits( 'site', $big2, $big . '  ' ), 'el candidato cuenta en lugar de su versión activa' );

	// Registro.
	for ( $i = 0; $i < 205; $i++ ) $S::log( 'site', 'saved', array( 'id' => $id, 'msg' => "n$i" ) );
	$log = $S::log_entries( 'site' );
	check( 200 === count( $log ) && 'n204' === $log[0]['msg'], 'registro de 200 entradas, el más reciente primero' );
	check( ! isset( $log[0]['ip'] ), 'la IP sólo se guarda en los eventos de seguridad' );
	$S::deactivate( 'site', $big2, 1 );
	check( '203.0.113.7' === $S::log_entries( 'site', 1 )[0]['ip'], 'desactivar guarda la IP' );
	$elog = (string) @file_get_contents( ABSPATH . 'error.log' );
	check( false !== strpos( $elog, 'evento=deactivated ámbito=site sitio=1 id=' . $big2 ) && false === strpos( $elog, 'n204' ) && false === strpos( $elog, '203.0.113.7' ), 'error_log mínimo: sin mensajes ni IP' );

	// Bloqueo.
	$GLOBALS['t']['db']['wp_options'][ $S::SITE_LOCK ] = array( 'v' => time() . '|otro', 'a' => 'off' );
	$busy = $S::mutate( 'site', function () { return 'no'; } );
	check( is_wp_error( $busy ) && $S::LOCKED_MESSAGE === $busy->get_error_message(), 'bloqueo ocupado' );
	$GLOBALS['t']['db']['wp_options'][ $S::SITE_LOCK ] = array( 'v' => ( time() - 31 ) . '|otro', 'a' => 'off' );
	check( 'sí' === $S::mutate( 'site', function () { return 'sí'; } ), 'un bloqueo caducado se roba' );
	check( null === row( $S::SITE_LOCK ), 'el bloqueo se suelta' );
	check( 'dentro' === $S::mutate( 'site', function () use ( $S ) { return $S::mutate( 'site', function () { return 'dentro'; } ); } ), 'mutate es reentrante' );
	add_action( $S::EVENT, function ( $event ) use ( $S ) { $GLOBALS['t']['events'][] = array( $event, null === row( $S::SITE_LOCK ) ); }, 10, 1 );
	$S::log( 'site', 'conflict', array() );
	check( array( 'conflict', true ) === end( $GLOBALS['t']['events'] ), 'el evento se dispara después de soltar el bloqueo' );

	// Indicador borrado con _run.
	foreach ( array_keys( $S::run( 'site' )['items'] ) as $active ) $S::deactivate( 'site', $active, 1 );
	check( null === row( $S::FLAG ) && null === row( $S::SITE_RUN ) && ! isset( $GLOBALS['t']['cron'][1][ $S::CRON ] ), 'sin fragmentos activos se borran _run, el indicador y el cron' );
	check( true === $S::delete( 'site', $big2 ) && null === $S::item( 'site', $big2 ), 'eliminar un fragmento inactivo' );

	/* =================================================================== *
	 * Firma
	 * =================================================================== */
	reset_world( true );
	$code = "<?php\n\$GLOBALS['ran'][] = 'firmado';\n";
	$id   = make( 'site', 'Firmado', $code );
	$e    = array_merge( $S::run( 'site' )['items'][ $id ], array( 'scope' => 'site', 'id' => $id ) );
	check( $S::verify( $e ), 'firma válida' );
	check( ! $S::verify( array_merge( $e, array( 'code' => $code . ' ' ) ) ), 'código alterado' );
	check( ! $S::verify( array_merge( $e, array( 'code' => $code . ' ', 'hash' => hash( 'sha256', $code . ' ' ) ) ) ), 'código y hash alterados' );
	check( ! $S::verify( array_merge( $e, array( 'id' => 'deadbeef' ) ) ), 'id alterado' );
	switch_to_blog( 2 ); check( ! $S::verify( $e ), 'la fila copiada a otro sitio no vale' ); restore_current_blog();
	$GLOBALS['t']['network'] = 2; check( ! $S::verify( $e ), 'la fila copiada a otra red no vale' ); $GLOBALS['t']['network'] = 1;
	$nid  = make( 'network', 'De red', $code );
	$ne   = array_merge( $S::run( 'network' )['items'][ $nid ], array( 'scope' => 'network', 'id' => $nid ) );
	check( $S::verify( $ne ) && ! $S::verify( array_merge( $ne, array( 'rev' => 2 ) ) ), 'la firma de red liga el rev' );
	switch_to_blog( 3 ); check( $S::verify( $ne ), 'un fragmento de red vale en todos los sitios' ); restore_current_blog();
	check( 'database' === $S::bad_signature_kind( $S::run( 'site' ) ), 'kid válido con firma inválida: la BD cambió fuera de Tools' );
	$GLOBALS['t']['salt']['secure_auth'] = 'sal-segura-2';
	$S::forget_key();
	check( ! $S::verify( $e ) && 'keys' === $S::bad_signature_kind( $S::run( 'site' ) ), 'claves cambiadas: kid distinto' );
	$GLOBALS['t']['salt']['secure_auth'] = 'sal-segura-1';
	$S::forget_key();
	$session = array( 'token' => hash( 'sha256', 'tok' ), 'kind' => 'admin', 'scope' => 'site', 'network' => 1, 'blog' => 1, 'candidates' => array( array( 'scope' => 'site', 'id' => $id, 'hash' => hash( 'sha256', $code ) ) ), 'user' => 1, 'exp' => time() + 120 );
	$sig     = $S::session_signature( $session );
	check( $S::verify_session( $session, $sig ), 'sesión de prueba firmada' );
	foreach ( array( 'user' => 2, 'exp' => time() + 999, 'blog' => 2, 'kind' => 'front', 'candidates' => array( array( 'scope' => 'site', 'id' => $id, 'hash' => str_repeat( 'a', 64 ) ) ) ) as $k => $v ) check( ! $S::verify_session( array_merge( $session, array( $k => $v ) ), $sig ), 'sesión de prueba forjada: ' . $k );
	// signature_effective() con las reglas de wp_salt().
	$good = array( 'SECURE_AUTH_KEY' => 'k-unica', 'SECURE_AUTH_SALT' => 's-unica' );
	check( $S::signature_effective( array() ) === false, 'sin constantes: clave y sal en la BD' );
	check( $S::signature_effective( array( 'SECURE_AUTH_KEY' => '', 'SECURE_AUTH_SALT' => '' ) ) === false, 'constantes vacías' );
	check( $S::signature_effective( array( 'SECURE_AUTH_KEY' => 'put your unique phrase here', 'SECURE_AUTH_SALT' => 'put your unique phrase here' ) ) === false, 'frase de muestra' );
	check( $S::signature_effective( array( 'SECURE_AUTH_KEY' => 'pon aquí tu frase única', 'SECURE_AUTH_SALT' => 'pon aquí tu frase única' ) ) === false, 'frase de muestra traducida' );
	check( $S::signature_effective( array( 'SECURE_AUTH_KEY' => 'igual', 'AUTH_KEY' => 'igual', 'SECURE_AUTH_SALT' => 'igual2', 'NONCE_SALT' => 'igual2' ) ) === false, 'constantes duplicadas no cuentan' );
	check( $S::signature_effective( $good ) && 'SECURE_AUTH_KEY' === $S::signature_sources( $good )['key'], 'clave y sal propias' );
	check( $S::signature_effective( array( 'SECRET_KEY' => 'secreta' ) ) && 'SECRET_KEY' === $S::signature_sources( array( 'SECRET_KEY' => 'secreta' ) )['key'], 'la clave puede venir de SECRET_KEY' );
	check( $S::signature_effective( array( 'SECURE_AUTH_SALT' => 's-unica' ) ), 'sólo la sal en wp-config.php: la clave sigue siendo secreta' );
	check( array( 'key' => 'database', 'salt' => 'SECURE_AUTH_SALT' ) === $S::signature_sources( array( 'SECURE_AUTH_KEY' => 'x', 'LOGGED_IN_KEY' => 'x', 'SECURE_AUTH_SALT' => 's' ) ), 'mezcla de clave duplicada y sal válida' );

	/* =================================================================== *
	 * Runner
	 * =================================================================== */
	reset_world();
	update_option( 'stylesheet', 'tema' );
	$GLOBALS['t']['get_option'] = array();
	run_now();
	check( array() === $GLOBALS['t']['get_option'] && array() === $R::loaded(), 'sin indicador no hay ninguna llamada a get_option' );
	$a = make( 'site', 'A', "<?php\n\$GLOBALS['ran'][] = 'A';\n\$local = 'no global';\n" );
	$b = make( 'site', 'B', "<?php\necho 'salida descartada';\n\$GLOBALS['ran'][] = 'B';\n" );
	$out = run_now();
	check( array( 'A', 'B' ) === $GLOBALS['ran'] && '' === $out, 'corren en orden de creación y la salida al cargarse se descarta' );
	check( ! isset( $GLOBALS['local'] ), 'las variables de nivel superior no son globales' );
	check( false === @file_get_contents( $W::path( 'site', $a, hash( 'sha256', "<?php\n\$GLOBALS['ran'][] = 'A';\n\$local = 'no global';\n" ) ) ), 'el envoltorio olvida el código tras el include' );
	// Un búfer que el fragmento deja abierto no se cierra ni se mide.
	$c   = make( 'site', 'C', "<?php\nob_start();\n\$GLOBALS['ran'][] = 'C';\n" );
	$lvl = ob_get_level();
	reset_statics(); $GLOBALS['ran'] = array();
	$R::run();
	check( ob_get_level() === $lvl + 2 && array( 'A', 'B', 'C' ) === $GLOBALS['ran'], 'un búfer abierto por el fragmento no se toca' );
	ob_end_clean(); ob_end_clean();
	$S::deactivate( 'site', $c, 1 );
	// Throwable al cargarse: fila off en ese momento, y sigue el siguiente.
	$boom = make( 'site', 'Boom', "<?php\nthrow new RuntimeException( 'falla al cargar' );\n" );
	$d    = make( 'site', 'D', "<?php\n\$GLOBALS['ran'][] = 'D';\n" );
	run_now();
	$off  = row( $S::off_name( 'site', $boom, hash( 'sha256', "<?php\nthrow new RuntimeException( 'falla al cargar' );\n" ) ) );
	check( $off && 'on' === $off['a'] && in_array( 'D', $GLOBALS['ran'], true ), 'un Throwable al cargarse escribe la fila off y sigue con el siguiente' );
	check( 2 === unserialize( $off['v'] )['line'] && false !== strpos( unserialize( $off['v'] )['msg'], 'falla al cargar' ), 'la fila off guarda la línea y el mensaje' );
	run_now();
	check( array( 'A', 'B', 'D' ) === $GLOBALS['ran'], 'con su fila off, el fragmento ya no se carga' );
	// Conciliación de la fila off_s.
	$r = $S::reconcile( 'site' );
	check( array( $boom ) === $r['failed'] && 'failed' === $S::state( 'site', $boom ) && ! isset( $S::run( 'site' )['items'][ $boom ] ) && null === row( $off ? $S::off_name( 'site', $boom, hash( 'sha256', "<?php\nthrow new RuntimeException( 'falla al cargar' );\n" ) ) : '' ), 'la conciliación pasa a failed y borra la fila' );
	$mail = end( $GLOBALS['t']['mails'] );
	check( false !== strpos( $mail['subject'], 'Se desactivó un fragmento PHP por un error' ) && false === strpos( $mail['body'], 'throw new' ) && in_array( 'Content-Type: text/plain; charset=UTF-8', $mail['headers'], true ), 'correo de fallo en texto plano y sin el código' );
	check( $mail['free'] && 'admin1@sitio.test' === $mail['to'], 'el correo sale al admin_email del sitio, ya fuera del bloqueo' );
	check( 'failed' === $S::log_entries( 'site', 1 )[0]['event'], 'el fallo queda en el registro' );
	// Detener en el sitio.
	$S::set_safe( 'site', 'safe', true, 1 );
	run_now();
	check( array() === $GLOBALS['ran'], '«Detener todos» en el sitio' );
	$S::set_safe( 'site', 'safe', false, 1 );
	// Firma inválida: se omite y badsig se escribe una sola vez.
	$run  = get_option( $S::SITE_RUN );
	$run['items'][ $a ]['code'] = "<?php\n\$GLOBALS['ran'][] = 'ALTERADO';\n";
	$run['items'][ $a ]['hash'] = hash( 'sha256', $run['items'][ $a ]['code'] );
	update_option( $S::SITE_RUN, $run, false );
	$GLOBALS['t']['queries'] = array();
	run_now(); run_now();
	check( ! in_array( 'ALTERADO', $GLOBALS['ran'], true ) && in_array( 'B', $GLOBALS['ran'], true ), 'una firma inválida no se ejecuta' );
	check( 1 === count_queries( $S::BADSIG_SITE ) && true === maybe_unserialize( row( $S::BADSIG_SITE )['v'] )['kid_ok'], 'badsig se escribe una sola vez, con kid_ok' );
	$r = $S::reconcile( 'site' );
	check( array( $a ) === $r['signature'] && 'signature' === $S::state( 'site', $a ) && null === row( $S::BADSIG_SITE ), 'la conciliación detiene lo que tiene firma inválida' );
	check( 'unsigned' === $S::item( 'site', $a )['history'][0]['event'], 'el código sin firma entra al historial como unsigned' );
	check( false !== strpos( end( $GLOBALS['t']['mails'] )['body'], 'La base de datos se modificó fuera de DIGITALÍSIMO Tools' ), 'correo de firma inválida con la misma clave' );
	// Esquema ajeno: no corre nada.
	stream_wrapper_unregister( 'digitalisimo-php' );
	class Foreign_Wrapper { public $context; public function stream_open() { return false; } }
	stream_wrapper_register( 'digitalisimo-php', 'Foreign_Wrapper' );
	private_prop( $W, 'registered', false, true );
	run_now();
	check( array() === $GLOBALS['ran'] && 'foreign' === $W::problem() && '' !== $W::problem_message(), 'con un esquema ajeno no corre ningún fragmento' );
	stream_wrapper_unregister( 'digitalisimo-php' );
	run_now();
	check( array( 'B', 'D' ) === $GLOBALS['ran'], 'el envoltorio propio vuelve a registrarse' );

	// Red: orden, safe, safe_sites, off_n por rev, pausa manual.
	reset_world( true );
	update_option( 'stylesheet', 'tema' );
	$n1 = make( 'network', 'N1', "<?php\n\$GLOBALS['ran'][] = 'N1';\n" );
	$n2 = make( 'network', 'N2', "<?php\n\$GLOBALS['ran'][] = 'N2';\n" );
	switch_to_blog( 2 );
	$s1 = make( 'site', 'S1', "<?php\n\$GLOBALS['ran'][] = 'S1';\n" );
	run_now();
	check( array( 'N1', 'N2', 'S1' ) === $GLOBALS['ran'], 'primero los de red y después los del sitio' );
	restore_current_blog();
	switch_to_blog( 3 );
	run_now();
	check( array( 'N1', 'N2' ) === $GLOBALS['ran'], 'el fragmento del sitio 2 no corre en el 3' );
	$S::set_safe( 'network', 'safe', true, 1 );
	run_now();
	check( array() === $GLOBALS['ran'], '«Detener los fragmentos de red»' );
	$S::set_safe( 'network', 'safe', false, 1 );
	restore_current_blog(); switch_to_blog( 2 );
	$S::set_safe( 'network', 'safe_sites', true, 1 );
	run_now();
	check( array( 'N1', 'N2' ) === $GLOBALS['ran'], '«Detener los fragmentos de todos los sitios»' );
	$S::set_safe( 'network', 'safe_sites', false, 1 );
	$S::set_safe( 'site', 'safe', true, 1 );
	run_now();
	check( array() === $GLOBALS['ran'] && 'here_stopped' === $S::network_state_here( $n1 )['state'], '«Detener todos» en un sitio detiene también lo de red en ese sitio' );
	$S::set_safe( 'site', 'safe', false, 1 );
	$h1 = hash( 'sha256', "<?php\n\$GLOBALS['ran'][] = 'N1';\n" );
	$S::insert_row( 2, $S::off_name( 'network', $n1, $h1, 1 ), array( 't' => time() ), true );
	run_now();
	check( array( 'N2', 'S1' ) === $GLOBALS['ran'], 'la fila off_n pausa el fragmento de red sólo en este sitio' );
	restore_current_blog(); switch_to_blog( 3 );
	run_now();
	check( array( 'N1', 'N2' ) === $GLOBALS['ran'], 'en el otro sitio sigue corriendo' );
	restore_current_blog(); switch_to_blog( 2 );
	check( true === $S::pause_here( $n2, 1 ), 'pausa manual en el sitio' );
	$S::resume_everywhere( $n1, $h1, 1 );
	$S::resume_everywhere( $n2, hash( 'sha256', "<?php\n\$GLOBALS['ran'][] = 'N2';\n" ), 1 );
	run_now();
	check( array( 'N1', 'S1' ) === $GLOBALS['ran'], 'con un rev nuevo la fila off_n vieja no aplica y la pausa manual sobrevive' );
	check( 'here_manual' === $S::network_state_here( $n2 )['state'] && 'here_active' === $S::network_state_here( $n1 )['state'], 'estado de los fragmentos de red en este sitio' );
	$r = $S::reconcile( 'site' );
	check( null === row( $S::off_name( 'network', $n1, $h1, 1 ) ), 'la conciliación borra la fila off_n de un rev viejo' );
	$S::insert_row( 2, $S::off_name( 'network', $n1, $h1, 2 ), array( 't' => time(), 'msg' => 'x' ), true );
	$S::reconcile( 'site' ); $S::reconcile( 'site' );
	check( 1 === maybe_unserialize( row( $S::off_name( 'network', $n1, $h1, 2 ) )['v'] )['l'] && 1 === count( array_filter( $S::log_entries( 'site' ), function ( $l ) use ( $n1 ) { return $l['id'] === $n1 && 'failed' === $l['event']; } ) ), 'la pausa vigente se queda y se anota una sola vez' );
	check( 'here_error' === $S::network_state_here( $n1 )['state'], 'pausado aquí por un error' );
	$inv = $S::network_inventory();
	check( array( 2 ) === array_column( $inv['paused'][ $n1 ]['error'], 'blog_id' ) && array( 2 ) === $inv['paused'][ $n2 ]['manual'] && array( 2 => 1 ) === $inv['sites'], 'inventario de red con UNION' );
	restore_current_blog();
	$GLOBALS['t']['mails'] = array();
	$S::network_sweep(); $S::network_sweep();
	check( 1 === count( $GLOBALS['t']['mails'] ) && false !== strpos( $GLOBALS['t']['mails'][0]['body'], 'https://sitio2.test/' ), 'un solo correo por versión pausada, desde el principal' );
	switch_to_blog( 2 );
	check( true === $S::resume_here( $n1, $h1, 1 ) && null === row( $S::off_name( 'network', $n1, $h1, 2 ) ), '«Reanudar en este sitio» borra la fila off_n' );
	// El candidato de la prueba sustituye a su versión en su posición; uno nuevo va al final de su ámbito.
	$site_run = get_option( $S::SITE_RUN ); $net_run = get_site_option( $S::NETWORK_RUN );
	$cand     = function ( $scope, $id, $tag ) { $code = "<?php\n\$GLOBALS['ran'][] = '$tag';\n"; return array( 'scope' => $scope, 'id' => $id, 'code' => $code, 'hash' => hash( 'sha256', $code ), 'title' => $tag ); };
	$plan     = $R::plan( $site_run, $net_run, wp_load_alloptions(), array( 'candidates' => array( $cand( 'network', $n2, 'N2*' ), $cand( 'network', 'abcdef01', 'NEW*' ), $cand( 'site', $s1, 'S1*' ) ) ) );
	check( array( 'N1', 'N2*', 'NEW*', 'S1*' ) === array_column( $plan, 'title' ), 'el candidato ocupa la posición de su versión (aunque esté pausado) y uno nuevo va al final de su ámbito' );
	check( array( false, true, true, true ) === array_column( $plan, 'candidate' ), 'sólo los candidatos se marcan' );
	$bad = $cand( 'site', $s1, 'S1*' ); $bad['code'] .= ' ';
	check( array( 'N1', 'S1' ) === array_column( $R::plan( $site_run, $net_run, wp_load_alloptions(), array( 'candidates' => array( $bad ) ) ), 'title' ), 'un candidato cuyo código no coincide con su hash no se carga' );
	$S::deactivate( 'network', $n2, 1 );
	$S::reconcile( 'site' );
	check( isset( $S::run( 'site' )['paused'][ $n2 ] ), 'desactivar en la red no borra la pausa manual del sitio' );
	$S::delete( 'network', $n2, 1 );
	$S::reconcile( 'site' );
	check( ! isset( $S::run( 'site' )['paused'][ $n2 ] ), 'la pausa manual de un fragmento de red eliminado se limpia' );
	restore_current_blog();

	/* =================================================================== *
	 * skip(): cookie de rescate y modo seguro de Elementor
	 * =================================================================== */
	reset_world( true );
	update_option( 'stylesheet', 'tema' );
	make( 'site', 'Corre', "<?php\n\$GLOBALS['ran'][] = 'corre';\n" );
	$valid = function ( $uid, $exp = null ) use ( $X ) { return $X::cookie_value( $uid, null === $exp ? time() + 600 : $exp ); };
	$GLOBALS['pagenow'] = 'wp-login.php';
	$_COOKIE[ $X::cookie_name() ] = $valid( 1 );
	run_now();
	check( array() === $GLOBALS['ran'] && 'cookie' === $R::skipped() && $R::reduced(), 'cookie válida en el acceso: sin fragmentos y modo reducido' );
	$_COOKIE[ $X::cookie_name() ] = substr( $valid( 1 ), 0, -1 ) . ( '0' === substr( $valid( 1 ), -1 ) ? '1' : '0' );
	run_now();
	check( array( 'corre' ) === $GLOBALS['ran'] && null === $R::skipped(), 'cookie falsificada' );
	$_COOKIE[ $X::cookie_name() ] = $valid( 1, time() - 1 );
	run_now();
	check( array( 'corre' ) === $GLOBALS['ran'], 'cookie caducada' );
	switch_to_blog( 2 ); $other = $X::cookie_value( 1, time() + 600 ); restore_current_blog();
	$_COOKIE[ $X::cookie_name() ] = $other;
	run_now();
	check( array( 'corre' ) === $GLOBALS['ran'], 'cookie de otro sitio' );
	$_COOKIE[ $X::cookie_name() ] = $valid( 1 );
	$X::revoke( 1 );
	run_now();
	check( array( 'corre' ) === $GLOBALS['ran'], 'cookie con epoch viejo' );
	$GLOBALS['pagenow'] = 'admin.php'; $GLOBALS['t']['is_admin'] = true;
	$_GET = array( 'page' => $S::PAGE, 'tab' => 'php' );
	$_COOKIE[ $X::cookie_name() ] = $valid( 5 );
	run_now();
	check( array( 'corre' ) === $GLOBALS['ran'], 'cookie de otro usuario en la pantalla' );
	$_COOKIE[ $X::cookie_name() ] = $valid( 1 );
	run_now();
	check( array() === $GLOBALS['ran'] && $R::reduced(), 'cookie de su usuario en la pantalla «Código PHP»' );
	$_COOKIE[ $X::cookie_name() ] = $valid( 0 );
	run_now();
	check( array() === $GLOBALS['ran'], 'cookie de un enlace pedido a la dirección de administración (uid 0)' );
	$GLOBALS['t']['caps'] = array( 'manage_options' );
	run_now();
	check( array( 'corre' ) === $GLOBALS['ran'], 'sin can_stop la cookie no hace nada' );
	$GLOBALS['t']['caps'] = array( 'edit_themes', 'unfiltered_html', 'manage_options', 'manage_network_options' );
	$GLOBALS['pagenow'] = 'admin-ajax.php'; $_GET = array(); $_REQUEST = array( 'action' => 'digitalisimo_tools_php' );
	run_now();
	check( array() === $GLOBALS['ran'], 'también en el manejador por admin-ajax.php' );
	$GLOBALS['pagenow'] = 'index.php'; $GLOBALS['t']['is_admin'] = false; $_REQUEST = array();
	run_now();
	check( array( 'corre' ) === $GLOBALS['ran'] && null === $R::skipped(), 'fuera del acceso y de la pantalla la cookie no hace nada' );
	$_COOKIE = array();
	update_option( 'stylesheet', 'elementor-safe' );
	run_now();
	check( array() === $GLOBALS['ran'] && 'elementor' === $R::skipped() && ! $R::reduced(), 'modo seguro del editor de Elementor' );

	/* =================================================================== *
	 * Permisos
	 * =================================================================== */
	reset_world( true );
	check( $A::can_edit( 'site' ) && $A::can_edit( 'network' ) && $A::can_stop(), 'superadministrador' );
	$GLOBALS['t']['caps'] = array( 'manage_options' );
	check( ! $A::can_edit( 'site' ) && ! $A::can_stop( 'site' ), 'un administrador de subsitio no edita ni detiene' );
	$GLOBALS['t']['caps'] = array( 'unfiltered_html', 'manage_options', 'manage_network_options' );
	check( ! $A::can_edit( 'site' ) && $A::can_stop() && $A::allowed( 'deactivate', 'site' ) && ! $A::allowed( 'save', 'site' ), 'sin edit_themes (DISALLOW_FILE_EDIT, DISALLOW_FILE_MODS o file_mod_allowed) sólo se desactiva o detiene' );
	$GLOBALS['t']['caps'] = array( 'edit_themes', 'manage_options', 'manage_network_options' );
	check( ! $A::can_edit( 'network' ), 'sin unfiltered_html (DISALLOW_UNFILTERED_HTML) no se edita' );
	reset_world( false );
	$GLOBALS['t']['caps'] = array( 'edit_themes', 'unfiltered_html', 'manage_options' );
	check( $A::can_edit( 'site' ) && $A::can_stop() && 'site' === $S::scope( 'network' ), 'sitio individual: manage_options' );
	reset_world( true );
	$nonce = wp_create_nonce( $A::nonce_action( 'save', 'site', 'abcdef01', 1 ) );
	check( true === $A::authorize( 'save', 'site', 1, 'abcdef01', $nonce ), 'nonce del ámbito, el sitio y el id' );
	check( 403 === $A::authorize( 'save', 'site', 2, 'abcdef01', $nonce )->get_error_data()['status'], 'un blog_id ajeno da 403' );
	check( is_wp_error( $A::authorize( 'save', 'site', 1, 'abcdef02', $nonce ) ) && is_wp_error( $A::authorize( 'save', 'network', 1, 'abcdef01', $nonce ) ) && is_wp_error( $A::authorize( 'activate', 'site', 1, 'abcdef01', $nonce ) ), 'el nonce está ligado a la acción, al ámbito y al id' );
	check( 400 === $A::authorize( 'save', 'site', 1, '../x', $nonce )->get_error_data()['status'] && 400 === $A::authorize( 'eval', 'site', 1, 'new', $nonce )->get_error_data()['status'], 'id y acción validados' );
	$GLOBALS['t']['logged_in'] = false;
	check( 401 === $A::authorize( 'save', 'site', 1, 'abcdef01', $nonce )->get_error_data()['status'], 'sin sesión' );
	$GLOBALS['t']['logged_in'] = true;
	private_prop( $R, 'skipped', 'cookie', true );
	check( $A::REDUCED_MESSAGE === $A::authorize( 'save', 'site', 1, 'abcdef01', $nonce )->get_error_message(), 'el modo reducido rechaza todo lo que añade código' );
	foreach ( array( 'prepare', 'activate', 'resume', 'resume_site', 'resume_all', 'resume_sites', 'rescue_new', 'rescue_revoke', 'delete', 'purge_history', 'discard' ) as $do ) check( ! $A::allowed( $do, 'site' ), 'modo reducido: ' . $do );
	foreach ( $A::STOP_ACTIONS as $do ) check( true === $A::authorize( $do, 'site', 1, '', wp_create_nonce( $A::nonce_action( $do, 'site', '', 1 ) ) ), 'modo reducido permite ' . $do );
	private_prop( $R, 'skipped', null, true );
	check( 'digitalisimo_tools_php:stop:network:n1:0' === $A::nonce_action( 'stop', 'network' ), 'nonce de red' );
	check( ! $S::valid_id( "abcdef01\n" ) && $S::valid_id( 'abcdef01' ) && ! $S::valid_id( 'ABCDEF01' ), 'id de 8 hexadecimales, sin salto de línea final' );

	/* =================================================================== *
	 * Atribución en proceso (sin fatales)
	 * =================================================================== */
	reset_world();
	update_option( 'stylesheet', 'tema' );
	$decl = "<?php\nnamespace Digi\\Prueba;\nfunction declarada() {}\nclass Declarada {}\ninterface Contrato {}\n";
	$sid  = make( 'site', 'Declara', $decl );
	run_now();
	$path = $W::path( 'site', $sid, hash( 'sha256', $decl ) );
	check( $path === ( new ReflectionFunction( 'Digi\\Prueba\\declarada' ) )->getFileName(), 'Reflection devuelve la ruta del envoltorio' );
	$at = function ( $type, $message, $file = '/var/www/wp-content/plugins/otro/otro.php', $extra = array() ) use ( $G ) { return $G::attribute( array_merge( array( 'type' => $type, 'message' => $message, 'file' => $file, 'line' => 7 ), $extra ) ); };
	check( 'redeclare' === $at( E_COMPILE_ERROR, 'Cannot redeclare Digi\\Prueba\\declarada() (previously declared in /otro/sitio.php:9)' )['how'], 'redeclaración de función, formato 7.4-8.3' );
	check( 3 === $at( E_COMPILE_ERROR, 'Cannot redeclare function Digi\\Prueba\\declarada() (previously declared in /otro.php:1)' )['line'], 'formato 8.4, con la línea de Reflection' );
	check( $sid === $at( E_COMPILE_ERROR, 'Cannot declare class Digi\\Prueba\\Declarada, because the name is already in use' )['entry']['id'], 'clase, formato anterior a 8.4' );
	check( $sid === $at( E_COMPILE_ERROR, 'Cannot redeclare class Digi\\Prueba\\Declarada (previously declared in /x.php:1)' )['entry']['id'], 'clase, formato 8.4' );
	check( $sid === $at( E_COMPILE_ERROR, 'Cannot declare interface Digi\\Prueba\\Contrato, because the name is already in use' )['entry']['id'], 'interfaz' );
	check( $sid === $at( E_ERROR, 'Cannot redeclare Digi\\Prueba\\declarada() (previously declared in /x.php:1)' )['entry']['id'], 'redeclaración condicional (E_ERROR)' );
	check( null === $at( E_COMPILE_ERROR, 'Cannot redeclare check() (previously declared in ' . $path . ':3)' ), 'un símbolo de otro archivo no se atribuye aunque el mensaje nombre la ruta' );
	check( null === $at( E_COMPILE_ERROR, 'Cannot redeclare Digi\\Prueba\\Declarada::metodo()' ), 'un método redeclarado no se atribuye por la clase' );
	check( null === $at( E_PARSE, 'Cannot redeclare Digi\\Prueba\\declarada()' ), 'la redeclaración sólo cuenta con E_COMPILE_ERROR o E_ERROR' );
	check( 'file' === $at( E_ERROR, 'Uncaught Error: x', $path )['how'], 'error en la ruta del fragmento' );
	check( null === $at( E_ERROR, 'Allowed memory size of 1 bytes exhausted', $path ), 'memoria fuera de la carga: sin culpable' );
	check( null === $at( E_ERROR, 'Maximum execution time of 30 seconds exceeded', $path ), 'tiempo fuera de la carga: sin culpable' );
	check( null === $at( E_ERROR, 'x', 'digitalisimo-php://site-1/' . $sid . '@000000000000' ), 'h12 viejo: nada' );
	check( null === $at( E_ERROR, 'x', 'digitalisimo-php://site-2/' . $sid . '@' . substr( hash( 'sha256', $decl ), 0, 12 ) ), 'ruta de otro sitio: nada' );
	check( null === $at( E_ERROR, 'x', 'digitalisimo-php://site-1/deadbeef@' . substr( hash( 'sha256', $decl ), 0, 12 ) ), 'fragmento que no corrió: nada' );
	$core = ABSPATH . 'wp-includes/class-wp-hook.php';
	$trace = function ( $frames, $msg = 'boom' ) use ( $core ) { return 'Uncaught Exception: ' . $msg . ' in ' . $core . ":10\nStack trace:\n" . implode( "\n", $frames ) . "\n#9 {main}\n  thrown"; };
	check( 5 === $at( E_ERROR, $trace( array( "#0 $core(324): fn()", '#1 [internal function]: cb()', "#2 $path(5): do_action('x')", '#3 /wp-content/plugins/p/p.php(1): y()' ) ), $core )['line'], 'traza: decide el primer marco ajeno al núcleo (el fragmento)' );
	check( null === $at( E_ERROR, $trace( array( "#0 $core(324): fn()", '#1 /var/www/wp-content/plugins/p/p.php(10): x()', "#2 $path(5): y()" ) ), $core ), 'traza: un plugin antes que el fragmento, sin culpable' );
	check( null === $at( E_ERROR, $trace( array( "#0 $core(324): fn()", '#1 /var/www/wp-content/plugins/p/p.php(10): x()' ), "falso\nStack trace:\n#0 $path(5): fake()" ), $core ), 'una traza falsificada en el mensaje no cuenta' );
	check( null === $at( E_ERROR, $trace( array( "#0 $path(5): fn()" ) ), '/var/www/wp-content/plugins/p/p.php' ), 'un Throwable lanzado en un plugin no se atribuye por la traza' );
	check( 4 === $at( E_ERROR, 'Uncaught Exception: z', $core, array( 'trace' => array( array( 'file' => $core, 'line' => 1 ), array( 'file' => $path, 'line' => 4 ) ) ) )['line'], 'error[trace] de PHP 8.5' );
	$G::enter( array( 'scope' => 'site', 'id' => $sid, 'hash' => hash( 'sha256', $decl ), 'title' => 'x' ), $path );
	check( 'running' === $at( E_ERROR, 'Allowed memory size of 1 bytes exhausted', $path )['how'], 'durante la carga, también memoria y tiempo' );
	$G::leave();

	/* =================================================================== *
	 * Escape
	 * =================================================================== */
	$hostile = '<script>alert(1)</script>"><img src=x onerror=alert(1)></textarea>';
	$G::enter( array( 'scope' => 'site', 'id' => $sid, 'hash' => hash( 'sha256', $decl ), 'title' => $hostile ), $path );
	$G::leave();
	$_SERVER['REQUEST_URI'] = "/pagina/\x07<script>" . str_repeat( 'x', 300 ) . '?a=<b>#frag';
	$msg = $G::message( '<p>Error del núcleo.</p>', array( 'type' => E_ERROR, 'message' => 'Uncaught Error: ' . $hostile, 'file' => $path, 'line' => 3 ) );
	check( false === strpos( $msg, '<script>' ) && false === strpos( $msg, '<img' ) && false !== strpos( $msg, '&lt;script&gt;' ) && 0 === strpos( $msg, '<p>Error del núcleo.</p>' ), 'el mensaje del guardián escapa el título' );
	$row = maybe_unserialize( row( $S::off_name( 'site', $sid, hash( 'sha256', $decl ) ) )['v'] );
	check( 200 === strlen( $row['path'] ) && false === strpos( $row['path'], "\x07" ) && false === strpos( $row['path'], '?' ) && false === strpos( $row['msg'], ABSPATH ), 'la fila off guarda la ruta sin consulta ni control (≤200) y el mensaje sin ABSPATH' );
	check( 'Uncaught Error: ' . $hostile === $row['msg'], 'el dato se guarda tal cual: se escapa al pintarlo' );
	$S::reconcile( 'site' );
	$mail = end( $GLOBALS['t']['mails'] );
	check( false === strpos( $mail['body'], '<script>' ) && false === strpos( $mail['body'], '<img' ) && false === strpos( $mail['body'], 'function declarada' ), 'el correo quita las etiquetas y nunca lleva el código' );
	$_SERVER['REQUEST_URI'] = '/';

	/* =================================================================== *
	 * Rescate
	 * =================================================================== */
	reset_world();
	update_option( 'permalink_structure', '/%postname%/' );
	update_option( 'blogname', 'Mi <b>sitio</b>' );
	$link = $X::create( 5 );
	check( is_string( $link ) && preg_match( '/digitalisimo-php-rescue=5\.([0-9a-f]{32})$/', $link, $m ), 'el enlace lleva <uid>.<32 hex>' );
	$key = '5.' . $m[1];
	check( false === strpos( serialize( $GLOBALS['t']['db'] ), $m[1] ) && false === strpos( serialize( $GLOBALS['t']['transients'] ), $m[1] ), 'la clave nunca se guarda en claro' );
	check( array( 'uid' => 5, 'network' => false ) === $X::check_key( $key ) && $X::status( 5 ) > time() + 6 * 86400, 'clave vigente durante 7 días' );
	check( null === $X::check_key( '5.' . str_repeat( 'a', 32 ) ) && null === $X::check_key( '6.' . $m[1] ) && null === $X::check_key( $key . 'x' ), 'clave incorrecta, de otro usuario o mal formada' );
	$link2 = $X::create( 5 );
	check( null === $X::check_key( $key ), 'una clave nueva sustituye a la anterior del mismo usuario' );
	preg_match( '/=(5\.[0-9a-f]{32})$/', $link2, $m2 );
	$link6 = $X::create( 6 );
	check( null !== $X::check_key( $m2[1] ), 'las claves son por usuario' );
	$GLOBALS['t']['logged_in'] = false;
	$target = $X::confirm( $m2[1] );
	check( null === $X::check_key( $m2[1] ) && null === $X::confirm( $m2[1] ), 'un solo uso' );
	update_option( 'digitalisimo_tools_site_options', array( 'values' => array( 'login_slug' => 'acceso-equipo' ), 'inherit' => array() ) );
	$target = $X::target( false, false );
	check( 0 === strpos( $target, 'https://sitio1.test/acceso-equipo/?redirect_to=' ) && false !== strpos( $target, rawurlencode( 'page=' . $S::PAGE ) ), 'sin sesión va a la ruta privada de acceso con redirect_to (no al 404)' );
	check( 'https://sitio1.test/wp-admin/admin.php?page=' . $S::PAGE . '&tab=php' === $X::target( false, true ), 'con sesión va a la pantalla «Código PHP»' );
	check( 5 === $X::cookie_user() && 'rescue_used' === $S::log_entries( 'site', 1 )[0]['event'] && '203.0.113.7' === $S::log_entries( 'site', 1 )[0]['ip'], 'confirmar crea la cookie y anota rescue_used con la IP' );
	$data = get_option( $S::RESCUE );
	$data['keys'][6]['exp'] = time() - 1;
	update_option( $S::RESCUE, $data, false );
	check( null === $X::check_key( substr( $link6, -34 ) ), 'una clave caducada no vale' );
	for ( $u = 10; $u < 35; $u++ ) $X::create( $u );
	check( $X::MAX_KEYS === count( get_option( $S::RESCUE )['keys'] ), 'como mucho 20 claves vigentes' );
	// Petición pública limitada a una cada 15 minutos.
	$GLOBALS['t']['mails'] = array();
	check( true === $X::send_request() && false === $X::send_request() && 1 === count( $GLOBALS['t']['mails'] ), 'la petición pública envía un correo y la segunda, antes de 15 minutos, no' );
	check( 'off' === row( $S::RESCUE_REQ )['a'] && 'admin1@sitio.test' === $GLOBALS['t']['mails'][0]['to'], 'al admin_email del sitio, sin autoload' );
	preg_match( '/digitalisimo-php-rescue=(0\.[0-9a-f]{32})/', $GLOBALS['t']['mails'][0]['body'], $m0 );
	check( array( 'uid' => 0, 'network' => false ) === $X::check_key( $m0[1] ) && null !== $X::consume( $m0[1] ) && null === $X::consume( $m0[1] ), 'la clave 0.<hex> vale una vez' );
	$req = maybe_unserialize( row( $S::RESCUE_REQ )['v'] );
	$req['at'] -= 901;
	$GLOBALS['t']['db']['wp_options'][ $S::RESCUE_REQ ]['v'] = serialize( $req );
	check( true === $X::send_request() && 2 === count( $GLOBALS['t']['mails'] ), 'pasados 15 minutos se puede pedir otro' );
	// Página sin eco del parámetro.
	$_GET[ $X::PARAM ] = '5.<script>alert(1)</script>';
	foreach ( array( 'confirm', 'used', 'request', 'requested' ) as $kind ) {
		$html = $X::render( $kind );
		check( false === strpos( $html, 'script>' ) && false === strpos( $html, 'digitalisimo-php-rescue' ) && false === strpos( $html, 'http' ), 'la página de rescate no repite el parámetro ni carga recursos: ' . $kind );
	}
	check( false !== strpos( $X::render( 'confirm' ), 'name="' . $X::CONFIRM_FIELD . '"' ) && false !== strpos( $X::render( 'confirm' ), '<form method="post">' ), 'un solo botón POST «Entrar sin fragmentos»' );
	// Revocar: sube epoch, borra claves y deja sin valor la cookie.
	check( null !== $X::cookie_user(), 'cookie vigente antes de revocar' );
	$X::revoke( 1 );
	check( null === $X::cookie_user() && array() === get_option( $S::RESCUE )['keys'] && null === row( $S::RESCUE_REQ ), 'revocar invalida cookies, claves y el enlace pedido' );
	$X::set_cookie( 7 );
	check( 7 === $X::cookie_user() && $X::cookie_expires() > time() + 3500, 'cookie de una hora' );
	$X::clear_cookie();
	check( null === $X::cookie_user(), '«Salir del modo seguro» borra la cookie' );

	/* =================================================================== *
	 * Segunda parte · Revisión sin ejecutar (Tester::check)
	 * =================================================================== */
	reset_world();
	$T = 'Digitalisimo\\Tools\\Php_Snippets\\Tester';
	$D = 'Digitalisimo\\Tools\\Php_Snippets\\Admin';
	$I = 'Digitalisimo\\Tools\\Php_Snippets\\Import';
	$c = $T::check( "<?php\nfunction digi_ok() { return 1; }\n" );
	check( $c['ok'] && array() === $c['warnings'] && PHP_VERSION === $c['php'] && hash( 'sha256', "<?php\nfunction digi_ok() { return 1; }\n" ) === $c['hash'], 'revisión: código válido, sin avisos' );
	$c = $T::check( "<?php\n\$a = 1;\nif ( \$a ) {\n\techo 'x'\n}\n" );
	check( ! $c['ok'] && 5 === $c['line'] && 0 === strpos( $c['message'], 'No se activó: error de sintaxis en la línea 5: ' ), 'revisión: ParseError con su línea' );
	check( ! $T::check( "echo 1;\n" )['ok'] && ! $T::check( "  <?php\necho 1;\n" )['ok'] && ! $T::check( '<?phpecho 1;' )['ok'] && 1 === $T::check( "echo 1;\n" )['line'], 'revisión: debe empezar exactamente por <?php' );
	check( ! $T::check( "<?php\necho '\xC3\x28';\n" )['ok'] && false !== strpos( $T::check( "<?php\necho '\xC3\x28';\n" )['message'], 'UTF-8' ), 'revisión: UTF-8 inválido' );
	check( false !== strpos( $T::check( "<?php\n//" . str_repeat( 'x', $S::MAX_CODE ) . "\n" )['message'], '64 KB' ), 'revisión: más de 64 KB' );
	check( $T::check( "<?php\nif (\n", false )['ok'] && array() === $T::check( "<?php\nif (\n", false )['warnings'], 'revisión: sin tokenizer se omite la sintaxis (la detecta la prueba)' );
	$seven = <<<'PHPCODE'
<?php
require 'inc/extra.php';
$content_width = 800;
global $theme_flag;
$theme_flag = 1;
set_error_handler( 'digi_handler' );
exit;
ob_start();
echo "<script>gtag('config', 'G-ABC123');</script>";
add_filter( 'user_has_cap', 'digi_caps' );
function digi_inside( $arg ) { $local = $arg; return $local; }
add_action( 'init', function ( $p ) use ( $content_width ) { $inner = $p; } );
add_action( 'init', fn( $q ) => $q );
class Digi_Foo { public $prop; public function bar( $arg2 ) { $y = $arg2; } }
echo Digi_Foo::class;
echo "{$content_width}";
PHPCODE;
	$w = $T::check( $seven . "\n" );
	check( $w['ok'] && 7 === count( $w['warnings'] ), 'revisión: los 7 avisos (' . count( $w['warnings'] ) . ')' );
	$vars = implode( ' ', array_filter( $w['warnings'], function ( $x ) { return 0 === strpos( $x, 'Las variables' ); } ) );
	check( false !== strpos( $vars, '$content_width' ) && false !== strpos( $vars, "\$GLOBALS['content_width']" ), 'aviso de variables de nivel superior con su ejemplo' );
	foreach ( array( '$local', '$arg', '$inner', '$p', '$q', '$prop', '$y', '$arg2', '$theme_flag' ) as $not ) check( false === strpos( $vars, $not . ',' ) && false === strpos( $vars, $not . '.' ) && false === strpos( $vars, $not . ' ' ), 'no avisa de ' . $not );
	check( preg_grep( '/G-ABC123.*SEO → Rendimiento/', $w['warnings'] ) && preg_grep( '/^set_error_handler\(\) interfiere/', $w['warnings'] ) && preg_grep( '/^exit o die/', $w['warnings'] ) && preg_grep( '/^ob_start\(\)/', $w['warnings'] ) && preg_grep( '/no protege/', $w['warnings'] ) && preg_grep( '/^__FILE__/', $w['warnings'] ), 'cada aviso con su texto' );
	check( preg_grep( '/^__FILE__/', $T::check( "<?php\nrequire __DIR__ . '/x.php';\n" )['warnings'] ) && array() === $T::check( "<?php\nrequire ABSPATH . 'x.php';\nrequire '/srv/x.php';\nob_start();\n\$GLOBALS['b'] = ob_get_clean();\n" )['warnings'], 'include relativo o __DIR__ avisa; absoluto y un búfer cerrado no' );
	check( preg_grep( '/no protege/', $T::check( "<?php\ndefine( 'DISALLOW_FILE_EDIT', true );\n" )['warnings'] ), 'define( DISALLOW_… ) avisa' );

	/* =================================================================== *
	 * Prueba en un sitio: prepare → el navegador → activate
	 * =================================================================== */
	/** Simula la petición del navegador a una URL de prueba: Runner::run, pie de página y cierre del guardián. */
	function test_request( $url, $kind, $footer = true, $after = null ) {
		$G = 'Digitalisimo\\Tools\\Php_Snippets\\Guard';
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
		reset_statics();
		private_prop( $G, 'armed', false, true );
		$_GET                       = $q;
		$GLOBALS['t']['logged_in']  = 'admin' === $kind;
		$GLOBALS['t']['is_admin']   = 'admin' === $kind;
		$GLOBALS['pagenow']         = 'admin' === $kind ? 'profile.php' : 'index.php';
		$GLOBALS['ran']             = array();
		ob_start();
		\Digitalisimo\Tools\Php_Snippets\Runner::run();
		if ( $after ) $after();
		if ( $footer ) do_action( 'admin' === $kind ? 'admin_footer' : 'wp_footer' );
		ob_end_clean();
		$armed = $G::testing();
		$G::shutdown();
		if ( $armed ) restore_error_handler();
		$_GET                      = array();
		$GLOBALS['t']['logged_in'] = true;
		$GLOBALS['t']['is_admin']  = false;
		$GLOBALS['pagenow']        = 'index.php';
		return $GLOBALS['ran'];
	}
	function post_for( $do, $id, array $extra = array(), $scope = 'site' ) {
		$A = 'Digitalisimo\\Tools\\Php_Snippets\\Access';
		return array_merge( array( 'do' => $do, 'scope' => $scope, 'blog_id' => (string) get_current_blog_id(), 'id' => $id, 'nonce' => wp_create_nonce( $A::nonce_action( $do, $scope, $id, get_current_blog_id() ) ) ), $extra );
	}
	function users_world() {
		$GLOBALS['t']['users'] = array(
			1 => array( 'ID' => 1, 'display_name' => 'Ana <b>Admin</b>', 'user_login' => 'ana', 'user_email' => 'ana@sitio.test', 'roles' => array( 'administrator' ), 'caps' => array( 'edit_themes', 'unfiltered_html' ) ),
			2 => array( 'ID' => 2, 'display_name' => '<script>alert(2)</script>Edi', 'user_login' => 'edi', 'user_email' => 'edi@sitio.test', 'roles' => array( 'editor' ), 'caps' => array( 'unfiltered_html' ) ),
		);
	}
	reset_world();
	users_world();
	update_option( 'stylesheet', 'tema' );
	$code = "<?php\n\$GLOBALS['ran'][] = 'cargado';\nadd_action( 'init', static function () {} );\n";
	$r    = $D::dispatch( post_for( 'prepare', 'new', array( 'title' => 'Prueba', 'code_b64' => base64_encode( $code ) ) ), true );
	check( $r['ok'] && preg_match( '/^[0-9a-f]{32}$/D', $r['data']['sid'] ) && 2 === count( $r['data']['urls'] ), 'prepare: guarda el borrador y crea la sesión con dos destinos' );
	$id  = $r['data']['id'];
	$sid = $r['data']['sid'];
	check( $S::valid_id( $id ) && ! ctype_digit( $id ) && 'inactive' === $S::state( 'site', $id ) && isset( $r['data']['nonces']['activate'] ), 'prepare: el fragmento nace inactivo y llegan sus nonces' );
	check( 'admin' === $r['data']['urls'][0]['kind'] && preg_match( '#^https://sitio1\.test/wp-admin/profile\.php\?digitalisimo-php-test=[0-9a-f]{32}$#D', $r['data']['urls'][0]['url'] ) && preg_match( '#^https://sitio1\.test/\?digitalisimo-php-test=[0-9a-f]{32}$#D', $r['data']['urls'][1]['url'] ), 'destinos: profile.php y la portada, con un token cada uno' );
	check( false === strpos( serialize( $GLOBALS['t']['transients'] ), substr( $r['data']['urls'][0]['url'], -32 ) ), 'el token nunca se guarda en claro' );
	// Paso 3 antes de que el navegador abra los destinos: pendiente, y después falla sin activar.
	$early = $D::dispatch( post_for( 'activate', $id, array( 'sid' => $sid ) ), true );
	check( ! $early['ok'] && ! empty( $early['data']['pending'] ) && false !== strpos( $early['message'], 'no respondió a la prueba en 30 segundos' ), 'sin resultado: se espera una vez' );
	check( array( 'cargado' ) === test_request( $r['data']['urls'][0]['url'], 'admin' ) && array( 'cargado' ) === test_request( $r['data']['urls'][1]['url'], 'front' ), 'la prueba carga el candidato en el escritorio y en la portada' );
	check( ! isset( $S::run( 'site' )['items'][ $id ] ) && array() === test_request( 'https://sitio1.test/', 'front' ), 'un visitante nunca ejecuta el candidato' );
	check( null === $T::claim( substr( $r['data']['urls'][1]['url'], -32 ) ), 'el token es de un solo uso' );
	$GLOBALS['t']['mails'] = array();
	$done = $D::dispatch( post_for( 'activate', $id, array( 'sid' => $sid ) ), true );
	check( $done['ok'] && 0 === strpos( $done['message'], 'Fragmento activado. Se probó en el escritorio y en la portada sin errores (' ) && 'active' === $S::state( 'site', $id ), 'activate: pasa el criterio y activa el hash probado' );
	check( preg_match( '/digitalisimo-php-rescue=1\.([0-9a-f]{32})$/D', $done['data']['rescue'], $rm ) && false === strpos( serialize( $GLOBALS['t']['db'] ) . serialize( $GLOBALS['t']['transients'] ), $rm[1] ), 'el enlace de rescate se devuelve una vez y nunca se guarda en claro' );
	check( 2 === count( $GLOBALS['t']['mails'] ) && 'ana@sitio.test' === $GLOBALS['t']['mails'][0]['to'] && false !== strpos( $GLOBALS['t']['mails'][0]['body'], $rm[1] ) && 'admin1@sitio.test' === $GLOBALS['t']['mails'][1]['to'] && false === strpos( $GLOBALS['t']['mails'][1]['body'], 'digitalisimo-php-rescue' ) && false !== strpos( $GLOBALS['t']['mails'][1]['body'], 'Lo hizo Ana Admin.' ) && false === strpos( $GLOBALS['t']['mails'][1]['body'], '(ana)' ), 'correo de activación: el enlace a quien activa y el aviso, sin enlace ni nombre de usuario, al admin_email' );
	check( false === strpos( $GLOBALS['t']['mails'][1]['body'], '<b>' ) && false === strpos( $GLOBALS['t']['mails'][0]['body'], 'GLOBALS' ), 'el correo es texto plano y nunca lleva el código' );
	check( array() === array_filter( array_keys( $GLOBALS['t']['transients'][1] ?? array() ), function ( $k ) { return 0 === strpos( $k, 'digitalisimo_tools_php_session_' ) || 0 === strpos( $k, 'digitalisimo_tools_php_result_' ); } ), 'al terminar se borran la sesión y los resultados' );
	check( 'activated' === $S::item( 'site', $id )['history'][0]['event'] && '' !== $S::item( 'site', $id )['history'][0]['sig'], 'el historial guarda la versión activada con su firma' );

	// Una prueba que falla deja el código guardado y no activa nada.
	$bad = "<?php\necho 'hola';\n";
	$r   = $D::dispatch( post_for( 'prepare', $id, array( 'title' => 'Prueba', 'code_b64' => base64_encode( $bad ) ) ), true );
	test_request( $r['data']['urls'][0]['url'], 'admin' );
	test_request( $r['data']['urls'][1]['url'], 'front' );
	$f = $D::dispatch( post_for( 'activate', $id, array( 'sid' => $r['data']['sid'] ) ), true );
	check( ! $f['ok'] && false !== strpos( $f['message'], 'imprime 4 caracteres al cargarse' ) && hash( 'sha256', $code ) === $S::run( 'site' )['items'][ $id ]['hash'] && $bad === $S::item( 'site', $id )['code'], 'salida al cargarse: no se activa, sigue la versión anterior y el borrador queda guardado' );
	check( 'test_failed' === $S::log_entries( 'site', 1 )[0]['event'], 'la prueba fallida queda en el registro' );
	// Sintaxis: responde con la línea y saved:true.
	$s = $D::dispatch( post_for( 'prepare', $id, array( 'title' => 'Prueba', 'code_b64' => base64_encode( "<?php\n\nfunction ( {\n}\n" ) ) ), true );
	check( ! $s['ok'] && ! empty( $s['data']['saved'] ) && 3 === $s['data']['line'] && 0 === strpos( $s['message'], 'No se activó: error de sintaxis en la línea 3' ) && 'check_failed' === $S::log_entries( 'site', 1 )[0]['event'], 'error de sintaxis: rechazado con su línea y guardado' );
	// Cambio a mitad de la prueba.
	$r = $D::dispatch( post_for( 'prepare', $id, array( 'title' => 'Prueba', 'code_b64' => base64_encode( $code . "// v2\n" ) ) ), true );
	test_request( $r['data']['urls'][0]['url'], 'admin' );
	test_request( $r['data']['urls'][1]['url'], 'front' );
	$S::save( 'site', $id, 'Prueba', $code . "// v3\n", 1 );
	$m = $D::dispatch( post_for( 'activate', $id, array( 'sid' => $r['data']['sid'] ) ), true );
	check( ! $m['ok'] && $S::CHANGED_MESSAGE === $m['message'], 'el fragmento cambió durante la prueba' );
	// Guardar sin activar.
	$sv = $D::dispatch( post_for( 'save', $id, array( 'title' => 'Prueba', 'code' => addslashes( $code . "// sin JS\n" ) ) ) );
	check( $sv['ok'] && 'Cambios guardados sin activar. Sigue ejecutándose la versión anterior.' === $sv['message'] && $code . "// sin JS\n" === $S::item( 'site', $id )['code'] && false !== strpos( $sv['redirect'], 'edit=' . $id ), 'guardar sin activar (sin JavaScript) conserva la versión activa' );
	$nw = $D::dispatch( post_for( 'save', 'new', array( 'title' => 'Otro', 'code_b64' => base64_encode( "<?php\n" ) ) ), true );
	check( $nw['ok'] && 'Guardado sin activar.' === $nw['message'] && $S::valid_id( $nw['data']['id'] ), 'guardar uno nuevo' );
	// Revisión obligatoria de una versión sin firma válida: el código «unsigned» de la BD.
	$runv = get_option( $S::SITE_RUN );
	$runv['items'][ $id ]['code'] = "<?php\n// alterado\n";
	$runv['items'][ $id ]['hash'] = hash( 'sha256', $runv['items'][ $id ]['code'] );
	update_option( $S::SITE_RUN, $runv, false );
	$S::reconcile( 'site' );
	check( 'signature' === $S::state( 'site', $id ) && $T::needs_review( 'site', $id, hash( 'sha256', $S::item( 'site', $id )['code'] ) ), 'firma no válida: activar exige la casilla' );
	$nr = $D::dispatch( post_for( 'prepare', $id, array( 'title' => 'Prueba', 'code_b64' => base64_encode( $code ) ) ), true );
	check( ! $nr['ok'] && $D::REVIEW_MISSING === $nr['message'], 'sin la casilla no se prueba' );
	$yr = $D::dispatch( post_for( 'prepare', $id, array( 'title' => 'Prueba', 'code_b64' => base64_encode( $code ), 'reviewed' => '1' ) ), true );
	check( $yr['ok'] && isset( $yr['data']['sid'] ), 'con la casilla sí' );
	test_request( $yr['data']['urls'][0]['url'], 'admin' );
	test_request( $yr['data']['urls'][1]['url'], 'front' );
	check( $D::dispatch( post_for( 'activate', $id, array( 'sid' => $yr['data']['sid'] ) ), true )['ok'] && 'active' === $S::state( 'site', $id ), 'revisado y probado, se reactiva uno por uno' );
	check( $T::needs_review( 'site', $id, hash( 'sha256', "<?php\n// alterado\n" ) ) && ! $T::needs_review( 'site', $id, hash( 'sha256', $code ) ), 'la versión cambiada fuera de Tools exige revisión aunque se restaure; la firmada no' );

	/* =================================================================== *
	 * Criterio (Tester::judge) y sesión
	 * =================================================================== */
	$target = array( 'kind' => 'front', 'url' => 'https://sitio1.test/', 'label' => 'la portada', 'user' => 0 );
	$cands  = array( array( 'scope' => 'site', 'id' => 'abcdef01', 'hash' => str_repeat( 'a', 64 ) ) );
	$okr    = array( 'loaded' => $cands, 'footer' => 'wp_footer', 'output' => 0, 'status' => 200, 'user' => 0, 'notices' => array(), 'caught' => array(), 'fatal' => null, 'redirect' => '', 'buffer_left' => array() );
	$j      = function ( $changes, $t = null, $retry = true ) use ( $T, $target, $cands, $okr ) { return $T::judge( $t ?: $target, null === $changes ? null : array_merge( $okr, $changes ), $cands, $retry ); };
	check( 'pass' === $j( array() )['status'], 'criterio: pasa' );
	check( 'pending' === $j( null )['status'], 'criterio: sin resultado nunca pasa' );
	$v = $j( array( 'fatal' => array( 'attributed' => true, 'id' => 'abcdef01', 'message' => "Uncaught Error: Call to undefined function x()\nStack trace", 'line' => 8, 'file' => 'digitalisimo-php://site-1/abcdef01@aaaaaaaaaaaa' ) ) );
	check( 'fail' === $v['status'] && 8 === $v['line'] && 0 === strpos( $v['message'], 'No se activó: al probarlo en la portada falló en la línea 8: Uncaught Error: Call to undefined function x()' ), 'criterio: fatal atribuido, con la línea' );
	$v = $j( array( 'fatal' => array( 'attributed' => false, 'message' => 'Allowed memory size exhausted', 'line' => 3, 'file' => 'wp-content/plugins/otro/otro.php' ) ) );
	check( 'fail' === $v['status'] && false !== strpos( $v['message'], 'que no viene de este fragmento: Allowed memory size exhausted en wp-content/plugins/otro/otro.php:3' ) && 0 === $v['line'], 'criterio: fatal ajeno' );
	check( false !== strpos( $j( array( 'caught' => array( array( 'id' => 'abcdef01', 'class' => 'RuntimeException', 'message' => 'x', 'line' => 4 ) ) ) )['message'], 'falló en la línea 4: RuntimeException: x' ), 'criterio: Throwable al cargarse' );
	check( false !== strpos( $j( array( 'output' => 12 ) )['message'], 'imprime 12 caracteres' ), 'criterio: salida al cargarse' );
	check( false !== strpos( $j( array( 'footer' => '' ) )['message'], 'la portada no terminó de pintarse (no llegó a wp_footer)' ), 'criterio: sin wp_footer' );
	$v = $j( array( 'footer' => '', 'status' => 503 ) );
	check( 'pass' === $v['status'] && $v['maintenance'], 'criterio: un 503 sin fatal pasa con aviso' );
	$v = $j( array( 'footer' => '', 'redirect' => '/inicio/?digitalisimo-php-test=' . str_repeat( 'b', 32 ) ) );
	check( 'retry' === $v['status'] && 'https://sitio1.test/inicio/' === $v['retry_url'], 'criterio: redirección al mismo host, un reintento sin el token viejo' );
	check( 'fail' === $j( array( 'footer' => '', 'redirect' => 'https://sitio1.test/inicio/' ), null, false )['status'], 'criterio: no hay segundo reintento' );
	check( false !== strpos( $j( array( 'footer' => '', 'redirect' => 'https://otro.test/' ) )['message'], 'la portada redirige a otro.test' ), 'criterio: redirección a otro dominio' );
	$admin = array( 'kind' => 'admin', 'url' => 'https://sitio1.test/wp-admin/profile.php', 'label' => 'el escritorio', 'user' => 1 );
	check( false !== strpos( $j( array( 'user' => 0, 'footer' => '', 'redirect' => 'https://sitio1.test/wp-login.php' ), $admin )['message'], 'no pudo entrar al escritorio con tu sesión' ), 'criterio: otro usuario o sin sesión en el escritorio' );
	check( false !== strpos( $j( array( 'user' => 1, 'footer' => '', 'redirect' => 'https://sitio1.test/wp-admin/upgrade.php?_wp_http_referer=x' ), $admin )['message'], 'actualizar la base de datos' ), 'criterio: base de datos pendiente' );
	check( 'pass' === $j( array( 'user' => 1, 'footer' => 'admin_footer' ), $admin )['status'], 'criterio: escritorio con la sesión' );
	check( false !== strpos( $j( array( 'loaded' => array() ) )['message'], 'no llegó a cargar el fragmento' ), 'criterio: candidato no cargado' );
	$v = $j( array( 'notices' => array( array( 'line' => 2, 'message' => 'Undefined variable $x' ) ), 'buffer_left' => array( 'abcdef01' ) ) );
	check( 'pass' === $v['status'] && 2 === count( $v['warnings'] ) && 0 === strpos( $v['warnings'][0], 'Aviso de PHP en la línea 2' ), 'criterio: avisos de PHP y búfer abierto, sin fallar' );
	// Sesión real: un reintento para una redirección, y no dos.
	$rid  = $S::create( 'site', 'Redirige', "<?php\n", 1 );
	$hash = hash( 'sha256', "<?php\n" );
	$ses  = $T::start( array( 'mode' => 'activate', 'scope' => 'site', 'id' => $rid, 'store' => 'site', 'candidates' => array( array( 'scope' => 'site', 'id' => $rid, 'hash' => $hash, 'source' => 'draft' ) ), 'targets' => array( array( 'url' => home_url( '/' ), 'kind' => 'front', 'blog' => 1, 'label' => 'la portada' ) ) ) );
	// Una redirección sin pintar la plantilla: Guard la anota con el filtro wp_redirect.
	test_request( $ses['urls'][0]['url'], 'front', false, function () { apply_filters( 'wp_redirect', '/portada/', 302 ); } );
	$e1 = $T::evaluate( $ses['sid'] );
	check( 'retry' === $e1['status'] && 1 === count( $e1['retry'] ) && preg_match( '#^https://sitio1\.test/portada/\?digitalisimo-php-test=[0-9a-f]{32}$#D', $e1['retry'][0]['url'] ), 'evaluate: un reintento con un token nuevo para la dirección de la redirección' );
	test_request( $e1['retry'][0]['url'], 'front', false, function () { apply_filters( 'wp_redirect', '/otra-vez/', 302 ); } );
	check( 'fail' === $T::evaluate( $ses['sid'] )['status'], 'evaluate: una segunda redirección ya no se reintenta' );
	// Sesión y token forjados.
	$ses = $T::start( array( 'mode' => 'activate', 'scope' => 'site', 'id' => $rid, 'store' => 'site', 'candidates' => array( array( 'scope' => 'site', 'id' => $rid, 'hash' => $hash, 'source' => 'draft' ) ), 'targets' => $T::targets( false ) ) );
	$sname = $S::TRANSIENT . 'session_' . hash( 'sha256', $ses['sid'] );
	$forged = get_transient( $sname );
	$forged['candidates'][0]['hash'] = str_repeat( 'c', 64 );
	set_transient( $sname, $forged );
	check( null === $T::session( $ses['sid'] ) && 'fail' === $T::evaluate( $ses['sid'] )['status'], 'una sesión alterada en la BD no vale' );
	$tok   = substr( $ses['urls'][1]['url'], -32 );
	$tname = $S::TRANSIENT . 'test_' . hash( 'sha256', $tok );
	$tdata = get_transient( $tname );
	$GLOBALS['t']['logged_in'] = false;
	set_transient( $tname, array_merge( $tdata, array( 'candidates' => array( array( 'scope' => 'site', 'id' => $rid, 'hash' => str_repeat( 'd', 64 ), 'source' => 'draft' ) ) ) ) );
	check( null === $T::claim( $tok ), 'un token alterado no vale' );
	$sigd = $tdata;
	$sigd['exp'] = time() - 1;
	$sigd['sig'] = $S::session_signature( array( 'token' => hash( 'sha256', $tok ), 'kind' => $sigd['kind'], 'scope' => $sigd['store'], 'network' => $sigd['network'], 'blog' => $sigd['blog'], 'candidates' => $sigd['candidates'], 'user' => $sigd['user'], 'exp' => $sigd['exp'] ) );
	set_transient( $tname, $sigd );
	check( null === $T::claim( $tok ), 'un token caducado no vale' );
	set_transient( $tname, $tdata );
	$GLOBALS['t']['logged_in'] = true;
	check( null === $T::claim( $tok ), 'la portada no se prueba con una sesión iniciada' );
	$atok  = substr( $ses['urls'][0]['url'], -32 );
	$aname = $S::TRANSIENT . 'test_' . hash( 'sha256', $atok );
	$adata = get_transient( $aname );
	$GLOBALS['t']['user'] = 5;
	$actx = $T::claim( $atok );
	check( is_array( $actx ) && 'admin' === $actx['kind'] && array() === $actx['candidates'], 'el escritorio con la sesión de otro usuario no carga el borrador (contexto sin candidatos: el resultado dirá que no pudo entrar con tu sesión)' );
	$GLOBALS['t']['user'] = 1;
	$GLOBALS['t']['logged_in'] = false;
	set_transient( $aname, $adata );
	$actx = $T::claim( $atok );
	check( is_array( $actx ) && array() === $actx['candidates'], 'una URL del escritorio filtrada, sin sesión, no ejecuta el borrador y gasta el token' );
	$GLOBALS['t']['logged_in'] = true;
	set_transient( $aname, $adata );
	$actx = $T::claim( $atok );
	check( is_array( $actx ) && 1 === count( $actx['candidates'] ), 'con la sesión de quien pidió la prueba, el escritorio carga el borrador' );
	$GLOBALS['t']['logged_in'] = false;
	set_transient( $tname, $tdata );
	$ctx = $T::claim( $tok );
	check( is_array( $ctx ) && 'front' === $ctx['kind'] && 'site' === $ctx['store'] && 1 === count( $ctx['candidates'] ) && "<?php\n" === $ctx['candidates'][0]['code'] && defined( 'WP_SANDBOX_SCRAPING' ) && defined( 'DONOTCACHEPAGE' ) && ! empty( $GLOBALS['t']['nocache'] ), 'claim: contexto con el código, WP_SANDBOX_SCRAPING, DONOTCACHEPAGE y nocache_headers()' );
	$GLOBALS['t']['logged_in'] = true;
	check( null === $T::claim( 'no-es-un-token' ) && null === $T::claim( str_repeat( 'e', 32 ) ), 'claim: token mal formado o inexistente' );
	reset_statics();

	/* =================================================================== *
	 * Red: muestra, prueba en varios sitios y «Reanudar»
	 * =================================================================== */
	reset_world( true );
	users_world();
	foreach ( array( 1 => 'tema', 2 => 'tema', 3 => 'otro-tema' ) as $blog => $sheet ) { switch_to_blog( $blog ); update_option( 'stylesheet', $sheet ); restore_current_blog(); }
	check( array( 3 ) === $T::network_sample(), 'muestra de red: sólo los sitios con otra stylesheet, con una consulta UNION' );
	$GLOBALS['t']['network_admin'] = true;
	$ncode = "<?php\n\$GLOBALS['ran'][] = 'red-' . get_current_blog_id();\n";
	$r     = $D::dispatch( post_for( 'prepare', 'new', array( 'title' => 'De red', 'code_b64' => base64_encode( $ncode ) ), 'network' ), true );
	check( $r['ok'] && 3 === count( $r['data']['urls'] ) && false !== strpos( $r['data']['urls'][0]['url'], '/wp-admin/network/profile.php?' ) && 0 === strpos( $r['data']['urls'][2]['url'], 'https://sitio3.test/?' ), 'red: el escritorio de la red, la portada del principal y la muestra' );
	check( ! empty( $GLOBALS['t']['transients']['network'] ) && empty( $GLOBALS['t']['transients'][1] ), 'red: la sesión y los tokens son transitorios de red' );
	$nid = $r['data']['id'];
	test_request( $r['data']['urls'][0]['url'], 'admin' );
	test_request( $r['data']['urls'][1]['url'], 'front' );
	switch_to_blog( 3 );
	check( array( 'red-3' ) === test_request( $r['data']['urls'][2]['url'], 'front' ), 'red: el candidato se prueba en la portada del sitio de la muestra' );
	restore_current_blog();
	$done = $D::dispatch( post_for( 'activate', $nid, array( 'sid' => $r['data']['sid'] ), 'network' ), true );
	check( $done['ok'] && false !== strpos( $done['message'], 'en el escritorio de la red y en la portada de 2 sitios sin errores' ) && 'active' === $S::state( 'network', $nid ), 'red: activado tras probar la muestra' );
	check( false !== strpos( $done['data']['rescue'], 'https://sitio1.test/?digitalisimo-php-rescue=1.' ) && $X::check_key( substr( $done['data']['rescue'], -34 ) )['network'], 'red: el enlace de rescate vive en el sitio principal y abre la pantalla de red' );
	$GLOBALS['t']['network_admin'] = false;
	// Pausa en el sitio 3 por un error y «Reanudar en este sitio» con prueba en ese sitio.
	switch_to_blog( 3 );
	$S::insert_row( 3, $S::off_name( 'network', $nid, hash( 'sha256', $ncode ), 1 ), array( 't' => time(), 'msg' => 'x', 'path' => '/' ), true );
	check( array() === test_request( 'https://sitio3.test/', 'front' ), 'pausado en el sitio 3' );
	$rs = $D::dispatch( post_for( 'resume_site', $nid ), true );
	check( $rs['ok'] && 2 === count( $rs['data']['urls'] ) && 0 === strpos( $rs['data']['urls'][0]['url'], 'https://sitio3.test/wp-admin/profile.php?' ), 'reanudar en este sitio: prueba en el escritorio y la portada del sitio' );
	check( array( 'red-3' ) === test_request( $rs['data']['urls'][1]['url'], 'front' ), 'reanudar en este sitio: el candidato se carga aunque tenga fila off' );
	test_request( $rs['data']['urls'][0]['url'], 'admin' );
	$ra = $D::dispatch( post_for( 'activate', $nid, array( 'sid' => $rs['data']['sid'] ) ), true );
	check( $ra['ok'] && 0 === strpos( $ra['message'], 'Fragmento reanudado en este sitio.' ) && array( 'red-3' ) === test_request( 'https://sitio3.test/', 'front' ), 'reanudar en este sitio: borra la pausa' );
	$S::insert_row( 3, $S::off_name( 'network', $nid, hash( 'sha256', $ncode ), 1 ), array( 't' => time(), 'msg' => '<script>x</script>', 'path' => '/' ), true );
	restore_current_blog();
	$GLOBALS['t']['network_admin'] = true;
	$rv = $D::dispatch( post_for( 'resume_all', $nid, array(), 'network' ), true );
	foreach ( $rv['data']['urls'] as $u ) {
		if ( 'admin' === $u['kind'] ) { test_request( $u['url'], 'admin' ); continue; }
		$blog = (int) preg_replace( '#^https://sitio(\d+)\.test/.*$#', '$1', $u['url'] );
		switch_to_blog( $blog ); test_request( $u['url'], 'front' ); restore_current_blog();
	}
	$rd = $D::dispatch( post_for( 'activate', $nid, array( 'sid' => $rv['data']['sid'] ), 'network' ), true );
	check( $rd['ok'] && 0 === strpos( $rd['message'], 'Fragmento reanudado en todos los sitios.' ) && 2 === $S::run( 'network' )['items'][ $nid ]['rev'], 'reanudar en todos los sitios: prueba la muestra y sube rev' );
	switch_to_blog( 3 );
	check( array( 'red-3' ) === test_request( 'https://sitio3.test/', 'front' ), 'con el rev nuevo la pausa vieja deja de aplicar' );
	restore_current_blog();
	$GLOBALS['t']['network_admin'] = false;

	/* =================================================================== *
	 * Manejador: permisos, ámbito, nonce y modo reducido
	 * =================================================================== */
	reset_world( true );
	users_world();
	update_option( 'stylesheet', 'tema' );
	$pid = make( 'site', 'Activo', "<?php\n\$GLOBALS['ran'][] = 'activo';\n" );
	$GLOBALS['t']['caps'] = array( 'manage_options' );
	$sub = $D::dispatch( post_for( 'save', $pid, array( 'code' => "<?php\n" ) ) );
	check( 403 === $sub['status'] && ! $sub['ok'] && 403 === $D::dispatch( post_for( 'deactivate', $pid ) )['status'], 'un administrador de subsitio no edita ni desactiva (403)' );
	check( ! $D::visible( false ), 'un administrador de subsitio no ve la pestaña' );
	$GLOBALS['t']['caps'] = array( 'unfiltered_html', 'manage_options', 'manage_network_options' );
	add_filter( 'file_mod_allowed', function () { return false; } );
	$blk = $D::dispatch( post_for( 'save', $pid, array( 'code' => "<?php\n" ) ) );
	check( 403 === $blk['status'] && false !== strpos( $blk['message'], 'DISALLOW_FILE_EDIT o DISALLOW_FILE_MODS' ) && 'blocked' === $D::mode( 'site' ), 'con la edición bloqueada, editar da 403' );
	check( $D::dispatch( post_for( 'deactivate', $pid ) )['ok'] && ! isset( $S::run( 'site' )['items'][ $pid ] ), 'con la edición bloqueada, «Desactivar» funciona' );
	$GLOBALS['t']['hooks']['file_mod_allowed'] = array();
	$GLOBALS['t']['caps'] = array( 'edit_themes', 'unfiltered_html', 'manage_options', 'manage_network_options' );
	$pid = make( 'site', 'Activo 2', "<?php\n" );
	$foreign = post_for( 'deactivate', $pid );
	$foreign['blog_id'] = '2';
	check( 403 === $D::dispatch( $foreign )['status'], 'un blog_id ajeno da 403' );
	$wrong = post_for( 'deactivate', $pid );
	$wrong['nonce'] = wp_create_nonce( $A::nonce_action( 'deactivate', 'site', 'abcdef01', 1 ) );
	check( 403 === $D::dispatch( $wrong )['status'], 'el nonce está ligado al fragmento' );
	check( 400 === $D::dispatch( post_for( 'deactivate', 'new' ) )['status'] && 400 === $D::dispatch( post_for( 'stop', 'abcdef01' ) )['status'], 'id validado por acción' );
	private_prop( $R, 'skipped', 'cookie', true );
	check( $A::REDUCED_MESSAGE === $D::dispatch( post_for( 'save', $pid, array( 'code' => "<?php\n" ) ) )['message'] && 'reduced' === $D::mode( 'site' ), 'modo reducido: guardar se rechaza' );
	check( $D::dispatch( post_for( 'stop', '' ) )['ok'] && $S::run( 'site' )['safe'], 'modo reducido: «Detener todos» funciona' );
	ob_start(); $D::page( false ); $html = ob_get_clean();
	check( false !== strpos( $html, $D::REDUCED_TEXT ) && false === strpos( $html, 'Agregar fragmento' ) && false === strpos( $html, '<textarea' ) && false === strpos( $html, 'Registro</summary>' ) && false === strpos( $html, 'Crear enlace de rescate' ) && false !== strpos( $html, 'Desactivar' ), 'modo reducido: la lista sin código, sin editor ni registro, y con «Desactivar»' );
	$_GET = array( 'edit' => $pid );
	ob_start(); $D::page( false ); $html = ob_get_clean();
	$_GET = array();
	check( false === strpos( $html, '<textarea' ), 'modo reducido: ni con ?edit se pinta el código' );
	private_prop( $R, 'skipped', null, true );
	$S::set_safe( 'site', 'safe', false, 1 );
	$rn = $D::dispatch( post_for( 'rescue_new', '' ) );
	check( $rn['ok'] && preg_match( '/digitalisimo-php-rescue=1\.[0-9a-f]{32}$/D', $rn['data']['link'] ) && false !== strpos( $rn['page'], esc_html( $rn['data']['link'] ) ), '«Crear enlace de rescate» lo devuelve una vez (y sin JavaScript, en una página propia)' );
	check( $D::dispatch( post_for( 'rescue_revoke', '' ) )['ok'] && 0 === $X::status( 1 ), '«Revocar enlaces y modos seguros»' );

	/* =================================================================== *
	 * Pantalla, avisos y escape
	 * =================================================================== */
	reset_world();
	users_world();
	update_option( 'stylesheet', 'tema' );
	$hostile = '<script>alert(1)</script>"><img src=x onerror=alert(1)></textarea>';
	$hid     = make( 'site', $hostile, "<?php\n\$GLOBALS['ran'][] = 'h';\n" );
	$S::insert_row( 1, $S::off_name( 'site', $hid, hash( 'sha256', "<?php\n\$GLOBALS['ran'][] = 'h';\n" ) ), array( 't' => time(), 'type' => E_ERROR, 'line' => 2, 'msg' => 'Uncaught Error: ' . $hostile, 'path' => '/p/' . $hostile ), true );
	$msgs = $D::notice_messages( 'site' );
	check( preg_grep( '/^DIGITALÍSIMO Tools desactivó el fragmento PHP «<script>/', array_column( $msgs, 'text' ) ), 'aviso de un fallo aún sin conciliar (sólo lee alloptions)' );
	check( null !== row( $S::off_name( 'site', $hid, hash( 'sha256', "<?php\n\$GLOBALS['ran'][] = 'h';\n" ) ) ), 'los avisos no concilian ni escriben' );
	$GLOBALS['t']['is_admin'] = true;
	ob_start(); $D::notices(); $html = ob_get_clean();
	check( '' !== $html && false === strpos( $html, '<script>' ) && false === strpos( $html, '<img' ) && false !== strpos( $html, '&lt;script&gt;' ), 'el aviso del escritorio escapa el título, el mensaje y la ruta' );
	$_GET = array( 'page' => $S::PAGE, 'tab' => 'php' );
	ob_start(); $D::notices(); $tabless = ob_get_clean();
	check( '' === $tabless, 'en la pestaña no se repiten los avisos generales' );
	ob_start(); $D::page( false ); $html = ob_get_clean();
	check( 'failed' === $S::state( 'site', $hid ) && false === strpos( $html, '<script>' ) && false === strpos( $html, '<img' ) && false !== strpos( $html, 'Desactivado por error' ) && false !== strpos( $html, '&lt;script&gt;alert(1)&lt;/script&gt;' ), 'la lista concilia al abrir y escapa título y error' );
	check( false !== strpos( $html, 'Registro</summary>' ) && false !== strpos( $html, 'Si algo deja de funcionar</summary>' ) && false !== strpos( $html, 'digitalisimo-secret is-empty' ) && false !== strpos( $html, '?digitalisimo-php-rescue=request' ) && false !== strpos( $html, 'DIGITALISIMO_TOOLS_PHP_SAFE_MODE' ), 'la lista con registro y «Si algo deja de funcionar»' );
	check( array() === array_filter( $D::notice_messages( 'site' ), function ( $m ) { return 'error' === $m['type']; } ), 'al abrir la pestaña el aviso de error desaparece' );
	$_GET = array( 'page' => $S::PAGE, 'tab' => 'php', 'edit' => $hid );
	$S::save( 'site', $hid, $hostile, "<?php\n// " . $hostile . "\n", 1 );
	ob_start(); $D::page( false ); $html = ob_get_clean();
	check( false === strpos( $html, '<script>' ) && false === strpos( $html, '<img' ) && substr_count( $html, '<textarea' ) === substr_count( $html, '</textarea>' ) && false !== strpos( $html, '&lt;/textarea&gt;' ), 'el editor escapa el código y el título (esc_textarea)' );
	check( false !== strpos( $html, 'id="digitalisimo-php-activate" disabled' ) && false !== strpos( $html, '<noscript>' ) && false !== strpos( $html, 'Versiones anteriores' ), 'el editor: «Guardar y activar» lo habilita el JavaScript, con <noscript> y versiones' );
	$_GET = array( 'page' => $S::PAGE, 'tab' => 'php', 'edit' => $hid, 'version' => '1' );
	ob_start(); $D::page( false ); $html = ob_get_clean();
	check( false !== strpos( $html, 'Estás viendo la versión del' ), '?version=N carga una versión anterior con su aviso' );
	// Firma no válida: aviso «la base de datos se modificó» y ningún botón en bloque.
	$aid  = make( 'site', 'Firma', "<?php\n" );
	$runv = get_option( $S::SITE_RUN );
	$runv['items'][ $aid ]['code'] = "<?php\n// fuera de Tools\n";
	$runv['items'][ $aid ]['hash'] = hash( 'sha256', $runv['items'][ $aid ]['code'] );
	update_option( $S::SITE_RUN, $runv, false );
	$_GET = array( 'page' => $S::PAGE, 'tab' => 'php' );
	ob_start(); $D::page( false ); $html = ob_get_clean();
	check( false !== strpos( $html, 'La base de datos se modificó fuera de DIGITALÍSIMO Tools: 1 fragmento PHP cambió sin pasar por esta pantalla' ) && false !== strpos( $html, 'Detenido · firma no válida' ) && false === stripos( $html, 'todos los fragmentos detenidos' ) && false === stripos( $html, 'reactivar todos' ), 'firma no válida: aviso y sin reactivación en bloque' );
	$_GET = array( 'page' => $S::PAGE, 'tab' => 'php', 'edit' => $aid );
	ob_start(); $D::page( false ); $html = ob_get_clean();
	check( false !== strpos( $html, 'name="reviewed"' ) && false !== strpos( $html, 'Cambios respecto de la última versión con firma válida' ), 'firma no válida: casilla de revisión y diferencias con la última versión firmada' );
	$_GET = array();
	// Configuración del JavaScript y recursos sólo en la pestaña.
	define( 'DIGITALISIMO_TOOLS_FILE', dirname( __DIR__ ) . '/digitalisimo-tools/digitalisimo-tools.php' );
	define( 'DIGITALISIMO_TOOLS_VERSION', 'test' );
	$js = $D::config_script( array( 'x' => '</script><script>alert(1)</script>&\'"' ) );
	check( false === strpos( $js, '<' ) && false === strpos( $js, '&' ) && false === strpos( $js, "'" ) && false !== strpos( $js, chr( 92 ) . 'u003C' . chr( 92 ) . '/script' . chr( 92 ) . 'u003E' ), 'la configuración usa JSON_HEX_*' );
	$GLOBALS['t']['scripts'] = array();
	$D::assets();
	check( empty( $GLOBALS['t']['scripts'] ), 'fuera de la pestaña no se encola nada' );
	$_GET = array( 'page' => $S::PAGE, 'tab' => 'php', 'edit' => $aid );
	$D::assets();
	check( array( 'digitalisimo-tools-php' ) === $GLOBALS['t']['scripts'] && false !== strpos( $GLOBALS['t']['inline']['digitalisimo-tools-php'], '"editor":{"codemirror":{"mode":"application\/x-httpd-php"}}' ) && false !== strpos( $GLOBALS['t']['inline']['digitalisimo-tools-php'], '"activate":"nonce|digitalisimo_tools_php:activate:site:b1:' . $aid ), 'en el editor: el script, el editor de código y los nonces del fragmento' );
	$_GET = array();
	$GLOBALS['t']['is_admin'] = false;
	make( 'site', 'Activo', "<?php\n" );
	check( array( 'x', '<span>Hay 1 fragmento PHP activo: dejará de ejecutarse si desactivas DIGITALÍSIMO Tools.</span>' ) === $D::row_meta( array( 'x' ), 'digitalisimo-tools/digitalisimo-tools.php' ) && array( 'x' ) === $D::row_meta( array( 'x' ), 'otro/otro.php' ), 'fila del plugin con los fragmentos activos' );
	// Avisos de red: pausas en n sitios (del registro de correos) y claves en la BD.
	reset_world( true );
	$nid = make( 'network', 'Red <b>x</b>', "<?php\n" );
	update_site_option( $S::NETWORK_MAILED, array( $nid . '_' . substr( hash( 'sha256', "<?php\n" ), 0, 12 ) . '_1' => array( 't' => time(), 'n' => 2 ) ) );
	$texts = array_column( $D::notice_messages( 'network' ), 'text' );
	check( in_array( 'El fragmento de red «Red <b>x</b>» se pausó en 2 sitios por un error.', $texts, true ) && in_array( $D::KEYS_DB_TEXT, $texts, true ), 'avisos de red: pausas y claves en la BD' );
	switch_to_blog( 2 );
	$S::insert_row( 2, $S::off_name( 'network', $nid, hash( 'sha256', "<?php\n" ), 1 ), array( 't' => time() ), true );
	check( preg_grep( '/se pausó en este sitio por un error/', array_column( $D::notice_messages( 'site' ), 'text' ) ), 'aviso en el sitio donde se pausó el fragmento de red' );
	restore_current_blog();
	$GLOBALS['t']['network_admin'] = true;
	ob_start(); $D::page( true ); $html = ob_get_clean();
	check( false !== strpos( $html, 'Código PHP de toda la red.' ) && false !== strpos( $html, 'Pausado en 1 sitio por un error · 0 a mano' ) && false !== strpos( $html, 'data-digitalisimo-php-resume="resume_all"' ) && false !== strpos( $html, 'Detener los fragmentos de todos los sitios' ) && false === strpos( $html, 'Red <b>' ), 'pantalla de red: «En los sitios», «Reanudar en todos los sitios», «Detener los fragmentos de todos los sitios» y escape' );
	$GLOBALS['t']['network_admin'] = false;
	switch_to_blog( 2 );
	ob_start(); $D::page( false ); $html = ob_get_clean();
	check( false !== strpos( $html, 'De la red en este sitio' ) && false !== strpos( $html, 'Pausado aquí por un error' ) && false !== strpos( $html, 'data-digitalisimo-php-resume="resume_site"' ), 'sitio de la red: «De la red en este sitio» con «Reanudar en este sitio»' );
	restore_current_blog();

	/* =================================================================== *
	 * Importación (§8)
	 * =================================================================== */
	reset_world();
	users_world();
	$events = array();
	add_action( $I::READY, function () use ( &$events ) { $events[] = 'ready'; } );
	check( true === $I::ready() && false === $I::ready() && array( 'ready' ) === $events, 'digitalisimo_tools_php_ready una vez por sitio, al crear el índice' );
	$I::init();
	$item = function ( $hash, $code = null, $extra = array() ) {
		return array_merge( array( 'title' => 'Hook de GP', 'status' => 'active', 'code' => null === $code ? "<?php\n// GP 123\n" : $code, 'original' => '<?php echo "</textarea><script>alert(1)</script>"; ?>', 'origin' => array( 'source' => 'generatepress', 'version' => '2.5.6', 'id' => '123', 'site' => 'https://origen.test', 'hook' => 'generate_before_header', 'hash' => $hash, 'author_id' => 2, 'last_editor_id' => 1, 'executed_in_origin' => false, 'reason' => 'DISALLOW_FILE_EDIT' ), 'notes' => array( 'Nota <script>x</script>' ) ), $extra );
	};
	check( null === apply_filters( 'digitalisimo_tools_php_otra', null ), 'el filtro devuelve null si nadie lo atiende' );
	$res = apply_filters( $I::FILTER, null, array( $item( 'h1' ) ), array() );
	check( array( 'generatepress:123' => 'created' ) === $res, 'importación: created' );
	$gid = array_keys( $S::index( 'site' )['items'] )[0];
	check( 'review' === $S::state( 'site', $gid ) && ! isset( $S::run( 'site' )['items'][ $gid ] ) && $S::index( 'site' )['items'][ $gid ]['review'], 'importado: siempre de sitio, inactivo y sin revisar, aunque llegue status=active' );
	$o = $S::item( 'site', $gid )['origin'];
	check( false === $o['author']['can_code'] && true === $o['last_editor']['can_code'] && 1 === $o['by'] && 'imported' === $S::log_entries( 'site', 1 )[0]['event'], 'can_code lo calcula Tools; origin.by es quien importa' );
	check( array( 'generatepress:123' => 'unchanged' ) === apply_filters( $I::FILTER, null, array( $item( 'h1' ) ), array() ), 'importación: unchanged' );
	check( array( 'generatepress:123' => 'updated' ) === apply_filters( $I::FILTER, null, array( $item( 'h2', "<?php\n// GP 123 v2\n" ) ), array() ) && "<?php\n// GP 123 v2\n" === $S::item( 'site', $gid )['code'], 'importación: updated si el fragmento está intacto e inactivo' );
	$S::save( 'site', $gid, 'Hook de GP', "<?php\n// editado a mano\n", 1 );
	check( array( 'generatepress:123' => 'conflict' ) === apply_filters( $I::FILTER, null, array( $item( 'h3', "<?php\n// GP 123 v3\n" ) ), array() ) && "<?php\n// editado a mano\n" === $S::item( 'site', $gid )['code'] && 'origin' === $S::state( 'site', $gid ), 'importación: conflict no toca el código y marca «el origen cambió»' );
	check( 'origin' === $S::item( 'site', $gid )['history'][0]['event'] && $T::needs_review( 'site', $gid, hash( 'sha256', "<?php\n// GP 123 v3\n" ) ), 'la «Nueva versión del origen» entra al historial y exige la casilla' );
	$_GET = array( 'page' => $S::PAGE, 'tab' => 'php', 'edit' => $gid );
	$GLOBALS['t']['is_admin'] = true;
	ob_start(); $D::page( false ); $html = ob_get_clean();
	check( false === strpos( $html, '<script>' ) && substr_count( $html, '<textarea' ) === substr_count( $html, '</textarea>' ) && false !== strpos( $html, 'Origen</summary>' ) && false !== strpos( $html, 'que no tenía permiso para código PHP' ) && false !== strpos( $html, 'En el origen este PHP no se ejecutaba (DISALLOW_FILE_EDIT)' ) && false !== strpos( $html, $D::REVIEW_TEXT ), 'editor de un importado: origen escapado, autor sin permiso, PHP que no se ejecutaba y la casilla' );
	$_GET = array();
	$GLOBALS['t']['is_admin'] = false;
	$sus = apply_filters( $I::FILTER, null, array( $item( 'h9', "<?php\n// 9\n", array( 'origin' => array( 'source' => 'generatepress', 'id' => '9', 'hook' => "x\n?><?php evil();", 'hash' => 'h9' ) ) ) ), array() );
	$sid9 = '';
	foreach ( $S::index( 'site' )['items'] as $k => $meta ) if ( 'generatepress:9' === $meta['origin_key'] ) $sid9 = (string) $k;
	check( array( 'generatepress:9' => 'created' ) === $sus && $I::SUSPICIOUS_NOTE === $S::item( 'site', $sid9 )['origin']['notes'][0] && '' === $S::item( 'site', $sid9 )['origin']['hook'], 'metadatos sospechosos: nota y gancho descartado; nunca entran al código' );
	$S::delete( 'site', $sid9, 1 );
	check( array( 'generatepress:9' => 'skipped' ) === apply_filters( $I::FILTER, null, array( $item( 'h10', "<?php\n// 10\n", array( 'origin' => array( 'source' => 'generatepress', 'id' => '9', 'hash' => 'h10' ) ) ) ), array() ), 'eliminar un importado lo añade a gone: no vuelve' );
	$many = array();
	for ( $i = 0; $i < 500; $i++ ) $many[] = array( 'code' => '', 'origin' => array( 'source' => 'gp', 'id' => (string) $i ) );
	$many[] = array( 'code' => "<?php\n", 'origin' => array( 'source' => 'gp', 'id' => 'ultimo' ) );
	check( 'skipped' === apply_filters( $I::FILTER, null, $many, array() )['gp:ultimo'], 'tope de 500 elementos por importación' );
	$src = apply_filters( $I::SOURCES, array(), array() );
	check( 1 === count( $src ) && isset( $src[0]['code'] ) && array( 'scope', 'id', 'title', 'active', 'hash', 'code' ) === array_keys( $src[0] ), 'sources: con can_edit entrega el código' );
	$GLOBALS['t']['caps'] = array( 'manage_options' );
	$src = apply_filters( $I::SOURCES, array(), array() );
	check( ! isset( $src[0]['code'] ), 'sources: sin can_edit, sin código' );
	$GLOBALS['t']['caps'] = array( 'edit_themes', 'unfiltered_html', 'manage_options', 'manage_network_options' );

	/* =================================================================== *
	 * Revisión adversarial y de fallos de F1b
	 * =================================================================== */
	// Borrador cambiado directamente en la BD: pierde la firma; el editor avisa, muestra las diferencias y la casilla, y Tools
	// nunca lo firma como un guardado (tampoco al segundo intento).
	reset_world();
	users_world();
	update_option( 'stylesheet', 'tema' );
	$code = "<?php\n\$GLOBALS['ran'][] = 'firmado';\n";
	$fid  = make( 'site', 'Firmado', $code );
	$iname = $S::option_name( 'site', 'item', $fid );
	check( 'saved' === $S::draft_status( 'site', $fid ) && ! $T::needs_review( 'site', $fid, hash( 'sha256', $code ) ), 'el borrador que guarda Tools lleva su firma' );
	$evil = "<?php\n\$GLOBALS['ran'][] = 'malo';\n";
	$raw  = get_option( $iname );
	$raw['code'] = $evil;
	update_option( $iname, $raw, false );
	check( 'database' === $S::draft_status( 'site', $fid ) && 'active' === $S::state( 'site', $fid ) && $T::needs_review( 'site', $fid, hash( 'sha256', $evil ) ), 'un borrador cambiado en la BD pierde la firma y activarlo exige la casilla' );
	$GLOBALS['t']['is_admin'] = true;
	$_GET = array( 'page' => $S::PAGE, 'tab' => 'php', 'edit' => $fid );
	ob_start(); $D::page( false ); $html = ob_get_clean();
	$_GET = array();
	check( false !== strpos( $html, 'El borrador cambió fuera de DIGITALÍSIMO Tools' ) && false !== strpos( $html, 'name="reviewed"' ) && false !== strpos( $html, 'Cambios respecto de la última versión con firma válida' ), 'editor: aviso, casilla y diferencias con la última versión firmada' );
	$p1 = $D::dispatch( post_for( 'prepare', $fid, array( 'title' => 'Firmado', 'code_b64' => base64_encode( $evil ) ) ), true );
	check( ! $p1['ok'] && $D::REVIEW_MISSING === $p1['message'] && ! empty( $p1['data']['review'] ), 'sin la casilla no se prueba el código alterado (y la respuesta pide la casilla)' );
	$p2 = $D::dispatch( post_for( 'prepare', $fid, array( 'title' => 'Firmado', 'code_b64' => base64_encode( $evil ) ) ), true );
	check( ! $p2['ok'] && $D::REVIEW_MISSING === $p2['message'], 'tampoco al segundo intento: el código alterado nunca se firma como un guardado' );
	$hist = $S::item( 'site', $fid )['history'];
	check( 'unsigned' === $hist[0]['event'] && hash( 'sha256', $evil ) === $hist[0]['hash'] && $S::history_entry_valid( 'site', $fid, $hist[0] ) && 'unsigned' === $S::draft_status( 'site', $fid ), 'el código alterado entra al historial como «sin firma» y el borrador conserva esa procedencia' );
	check( in_array( 'bad_signature_seen', array_column( $S::log_entries( 'site', 5 ), 'event' ), true ), 'el registro anota el borrador alterado' );
	$p3 = $D::dispatch( post_for( 'prepare', $fid, array( 'title' => 'Firmado', 'code_b64' => base64_encode( $evil ), 'reviewed' => '1' ) ), true );
	check( $p3['ok'] && isset( $p3['data']['sid'] ), 'con la casilla sí se prueba' );
	// Si se envía otro código, la casilla se exige en la petición que encontró el borrador alterado.
	$raw = get_option( $iname );
	$raw['code'] = "<?php\n// otra vez\n";
	update_option( $iname, $raw, false );
	$mine = "<?php\n\$GLOBALS['ran'][] = 'mío';\n";
	$p4 = $D::dispatch( post_for( 'prepare', $fid, array( 'title' => 'Firmado', 'code_b64' => base64_encode( $mine ) ) ), true );
	check( ! $p4['ok'] && $D::REVIEW_MISSING === $p4['message'] && $T::needs_review( 'site', $fid, hash( 'sha256', "<?php\n// otra vez\n" ) ), 'el borrador alterado exige la casilla aunque se envíe otro código, y su código queda marcado' );
	$p5 = $D::dispatch( post_for( 'prepare', $fid, array( 'title' => 'Firmado', 'code_b64' => base64_encode( $mine ) ) ), true );
	check( $p5['ok'], 'el código propio, ya guardado con firma, se prueba sin la casilla' );
	$sv = $D::dispatch( post_for( 'save', $fid, array( 'title' => 'Firmado', 'code_b64' => base64_encode( $mine ) ) ), true );
	check( $sv['ok'] && false === strpos( $sv['message'], 'había cambiado fuera' ), 'guardar un borrador firmado no avisa' );
	// Una versión del historial alterada en la BD.
	$raw = get_option( $iname );
	foreach ( $raw['history'] as $i => $h ) if ( 'saved' === $h['event'] ) { $raw['history'][ $i ]['code'] = "<?php\n// historia alterada\n"; $raw['history'][ $i ]['hash'] = hash( 'sha256', $raw['history'][ $i ]['code'] ); break; }
	update_option( $iname, $raw, false );
	check( $T::needs_review( 'site', $fid, hash( 'sha256', "<?php\n// historia alterada\n" ) ), 'una versión del historial alterada en la BD exige la casilla al restaurarla' );
	// Un importado: quitar en la BD la revisión y el historial, o hacerlo pasar por un guardado, no evita la casilla.
	$gpc = "<?php\n// gp\n";
	$iid = $S::create( 'site', 'Importado', $gpc, 0, array( 'index' => array( 'origin_key' => 'gp:1', 'review' => true ), 'event' => 'origin', 'log' => false ) );
	check( 'origin' === $S::draft_status( 'site', $iid ), 'un importado nace con la procedencia «origin»' );
	$idx = get_option( $S::SITE_INDEX );
	$idx['items'][ $iid ]['review'] = false;
	update_option( $S::SITE_INDEX, $idx, false );
	$raw = get_option( $S::option_name( 'site', 'item', $iid ) );
	$raw['history'] = array();
	update_option( $S::option_name( 'site', 'item', $iid ), $raw, false );
	check( $T::needs_review( 'site', $iid, hash( 'sha256', $gpc ) ), 'quitar en la BD la revisión y el historial de un importado no evita la casilla' );
	$raw['dev'] = 'saved';
	update_option( $S::option_name( 'site', 'item', $iid ), $raw, false );
	check( 'database' === $S::draft_status( 'site', $iid ) && $T::needs_review( 'site', $iid, hash( 'sha256', $gpc ) ), 'ni cambiar su procedencia a «guardado»' );
	$GLOBALS['t']['salt']['secure_auth'] = 'sal-segura-2';
	$S::forget_key();
	check( 'keys' === $S::draft_status( 'site', $fid ), 'con otras claves, el borrador se clasifica como «cambiaron las claves»' );
	$_GET = array( 'page' => $S::PAGE, 'tab' => 'php', 'edit' => $fid );
	ob_start(); $D::page( false ); $html = ob_get_clean();
	$_GET = array();
	check( false !== strpos( $html, 'cambiaron las claves de wp-config.php' ) && false !== strpos( $html, 'name="reviewed"' ), 'editor: con otras claves, aviso y casilla' );
	$GLOBALS['t']['salt']['secure_auth'] = 'sal-segura-1';
	$S::forget_key();
	$GLOBALS['t']['is_admin'] = false;

	// Portada privada: una redirección al acceso o un 401/403 sin fatal pasan con aviso; el escritorio no.
	$target = array( 'kind' => 'front', 'url' => 'https://sitio1.test/', 'label' => 'la portada', 'user' => 0 );
	$cands  = array( array( 'scope' => 'site', 'id' => 'abcdef01', 'hash' => str_repeat( 'a', 64 ) ) );
	$base   = array( 'loaded' => $cands, 'footer' => '', 'output' => 0, 'status' => 302, 'user' => 0, 'notices' => array(), 'caught' => array(), 'fatal' => null, 'redirect' => 'https://sitio1.test/acceso-equipo/?redirect_to=x', 'redirect_login' => true, 'buffer_left' => array() );
	$v = $T::judge( $target, $base, $cands );
	check( 'pass' === $v['status'] && ! empty( $v['private'] ) && preg_grep( '/^La portada no es pública \(redirige al acceso\)/', $v['warnings'] ), 'portada que redirige al acceso: pasa con aviso, sin reintento' );
	$v = $T::judge( $target, array_merge( $base, array( 'redirect' => '', 'redirect_login' => false, 'status' => 403 ) ), $cands );
	check( 'pass' === $v['status'] && preg_grep( '/no es pública \(responde 403\)/', $v['warnings'] ), 'portada que responde 403 sin fatal: pasa con aviso' );
	check( 'fail' === $T::judge( $target, array_merge( $base, array( 'fatal' => array( 'attributed' => true, 'id' => 'abcdef01', 'message' => 'x', 'line' => 2 ) ) ), $cands )['status'] && 'fail' === $T::judge( $target, array_merge( $base, array( 'loaded' => array() ) ), $cands )['status'] && 'fail' === $T::judge( $target, array_merge( $base, array( 'output' => 3 ) ), $cands )['status'], 'una portada privada sigue fallando con un fatal, sin cargar el candidato o con salida' );
	$adm = array( 'kind' => 'admin', 'url' => 'https://sitio1.test/wp-admin/profile.php', 'label' => 'el escritorio', 'user' => 1 );
	check( 'pass' !== $T::judge( $adm, array_merge( $base, array( 'user' => 1 ) ), $cands )['status'] && 'fail' === $T::judge( $adm, array_merge( $base, array( 'user' => 1, 'redirect' => '', 'redirect_login' => false, 'status' => 403 ) ), $cands )['status'], 'el escritorio nunca pasa sin terminar de pintarse' );
	$lid = $S::create( 'site', 'Acceso', "<?php\n", 1 );
	$ses = $T::start( array( 'mode' => 'activate', 'scope' => 'site', 'id' => $lid, 'store' => 'site', 'candidates' => array( array( 'scope' => 'site', 'id' => $lid, 'hash' => hash( 'sha256', "<?php\n" ), 'source' => 'draft' ) ), 'targets' => array( array( 'url' => home_url( '/' ), 'kind' => 'front', 'blog' => 1, 'label' => 'la portada' ) ) ) );
	test_request( $ses['urls'][0]['url'], 'front', false, function () { apply_filters( 'wp_redirect', 'https://sitio1.test/acceso-equipo/?redirect_to=%2F', 302 ); } );
	$e1 = $T::evaluate( $ses['sid'] );
	check( 'pass' === $e1['status'] && $e1['private'], 'el guardián reconoce la ruta de acceso (wp_login_url()) en la redirección' );
	$ses = $T::start( array( 'mode' => 'activate', 'scope' => 'site', 'id' => $lid, 'store' => 'site', 'candidates' => array( array( 'scope' => 'site', 'id' => $lid, 'hash' => hash( 'sha256', "<?php\n" ), 'source' => 'draft' ) ), 'targets' => array( array( 'url' => home_url( '/' ), 'kind' => 'front', 'blog' => 1, 'label' => 'la portada' ) ) ) );
	test_request( $ses['urls'][0]['url'], 'front', false, function () { apply_filters( 'wp_redirect', 'https://sitio1.test/wp-login.php?redirect_to=%2F', 302 ); } );
	check( 'pass' === $T::evaluate( $ses['sid'] )['status'], 'y wp-login.php' );
	// Autenticación del servidor (staging): WordPress no arranca; el 401 que vio el navegador hace pasar la portada sin probar.
	$r = $D::dispatch( post_for( 'prepare', $lid, array( 'title' => 'Acceso', 'code_b64' => base64_encode( "<?php\n" ) ) ), true );
	test_request( $r['data']['urls'][0]['url'], 'admin' );
	$ftok = substr( $r['data']['urls'][1]['url'], -32 );
	$pend = $D::dispatch( post_for( 'activate', $lid, array( 'sid' => $r['data']['sid'], 'seen' => $ftok . ':200' ) ), true );
	check( ! $pend['ok'] && ! empty( $pend['data']['pending'] ), 'una portada sin resultado que respondió 200 sigue pendiente' );
	$priv = $D::dispatch( post_for( 'activate', $lid, array( 'sid' => $r['data']['sid'], 'seen' => $ftok . ':401,zz:1' ) ), true );
	check( $priv['ok'] && false !== strpos( $priv['message'], 'alguna portada no es pública' ) && preg_grep( '/pide usuario y contraseña del servidor \(HTTP 401\)/', $priv['data']['warnings'] ) && 'active' === $S::state( 'site', $lid ), 'tras la autenticación del servidor (401 visto por el navegador): se activa con aviso porque el escritorio pasó' );

	// Un Throwable en el callback de A durante la carga de B culpa a A.
	reset_world();
	update_option( 'stylesheet', 'tema' );
	$acode = "<?php\nadd_filter( 'digi_x', function ( \$v ) { throw new \\RuntimeException( 'fallo en A' ); } );\n";
	$bcode = "<?php\napply_filters( 'digi_x', 1 );\n";
	$ga = make( 'site', 'A', $acode );
	$gb = make( 'site', 'B', $bcode );
	run_now();
	check( null !== row( $S::off_name( 'site', $ga, hash( 'sha256', $acode ) ) ) && null === row( $S::off_name( 'site', $gb, hash( 'sha256', $bcode ) ) ), 'un fallo en el callback de A durante la carga de B desactiva A, no B' );
	// PHP 7.4: un mensaje recortado a log_errors_max_len no aporta marcos (un «Stack trace» falso quedaría como el último).
	if ( PHP_VERSION_ID < 80000 ) {
		ini_set( 'log_errors_max_len', '1024' );
		$fake = substr( 'Uncaught RuntimeException: ' . str_repeat( 'A', 900 ) . "\nStack trace:\n#0 digitalisimo-php://site-1/abcd1234@0123456789ab(3): x()\n#1 {main}\n  thrown in " . str_repeat( 'B', 200 ), 0, 1024 );
		check( array() === $G::frames( array( 'message' => $fake ) ), 'PHP 7.4: un mensaje recortado no aporta marcos' );
	}
	check( 1 === count( $G::frames( array( 'message' => "Uncaught Error: x\nStack trace:\n#0 /srv/wp/wp-content/plugins/p/p.php(3): f()\n#1 {main}" ) ) ), 'un mensaje completo sí' );

	// Red: get_sites() siempre con el network_id de esta red, un sitio sin tabla no deja sin datos a los demás, y la lista
	// de red muestra como mucho 20 sitios sin cambiar de sitio.
	reset_world( true );
	users_world();
	foreach ( array( 1 => 'tema', 2 => 'tema', 3 => 'otro-tema' ) as $blog => $sheet ) { switch_to_blog( $blog ); update_option( 'stylesheet', $sheet ); restore_current_blog(); }
	$GLOBALS['t']['blogs'] = array( 1, 2, 4, 3 );
	$GLOBALS['t']['get_sites'] = array();
	check( array( 3 ) === $T::network_sample(), 'muestra de red: un sitio sin tabla de opciones no deja sin muestra a los demás' );
	$nid = make( 'network', 'Muchos', "<?php\n" );
	$S::insert_row( 3, $S::off_name( 'network', $nid, hash( 'sha256', "<?php\n" ), 1 ), array( 't' => time(), 'path' => '/', 'msg' => 'x' ), true );
	check( array( 3 ) === array_column( $S::network_inventory()['paused'][ $nid ]['error'], 'blog_id' ), 'inventario: un sitio roto no deja sin datos a los demás' );
	check( count( $GLOBALS['t']['get_sites'] ) >= 2 && array() === array_filter( $GLOBALS['t']['get_sites'], function ( $a ) { return 1 !== ( $a['network_id'] ?? null ); } ), 'get_sites() siempre con el network_id de esta red' );
	$GLOBALS['t']['blogs'] = range( 1, 25 );
	foreach ( range( 4, 25 ) as $b ) $GLOBALS['t']['db'][ table_of( $b ) ] = array( 'digitalisimo_tools_php' => array( 'v' => serialize( array( 'items' => array( 'abcdef0' . ( $b % 10 ) => array( 'title' => 'x' ) ) ) ), 'a' => 'off' ) );
	foreach ( range( 2, 25 ) as $b ) $S::insert_row( $b, $S::off_name( 'network', $nid, hash( 'sha256', "<?php\n" ), 1 ), array( 't' => time(), 'path' => '/', 'msg' => 'x' ), true );
	$GLOBALS['t']['url_switches'] = 0;
	$GLOBALS['t']['network_admin'] = true;
	$GLOBALS['t']['is_admin'] = true;
	ob_start(); $D::page( true ); $html = ob_get_clean();
	$GLOBALS['t']['network_admin'] = false;
	$GLOBALS['t']['is_admin'] = false;
	check( false !== strpos( $html, 'Pausado en 24 sitios por un error · 0 a mano' ) && 20 === substr_count( $html, ' · error el ' ) && false !== strpos( $html, 'y 4 más con error.' ) && false !== strpos( $html, 'Sitios con fragmentos propios (22)' ) && false !== strpos( $html, 'y 2 más.' ) && false !== strpos( $html, 'href="https://sitio21.test/wp-admin/admin.php?page=' . $S::PAGE . '&amp;tab=php">https://sitio21.test/</a>' ), 'lista de red: 20 sitios por lista y «y n más», con enlaces a cada pestaña' );
	check( 0 === $GLOBALS['t']['url_switches'], 'lista de red: ningún get_home_url() ni get_admin_url() (no cambia de sitio)' );
	// Reactivar un fragmento de red sigue el último rev: un «Reanudar en todos» no se deshace.
	$S::resume_everywhere( $nid, hash( 'sha256', "<?php\n" ), 1 );
	$S::deactivate( 'network', $nid, 1 );
	check( is_array( $S::activate( 'network', $nid, hash( 'sha256', "<?php\n" ), array( 'by' => 1 ) ) ) && 2 === $S::run( 'network' )['items'][ $nid ]['rev'], 'reactivar un fragmento de red sigue el último rev: las pausas del rev ya reanudado no vuelven' );
	// Correos de un fragmento de sitio en una red: a la dirección de la red, nunca al administrador del subsitio.
	$GLOBALS['t']['blogs'] = array( 1, 2, 3 );
	switch_to_blog( 2 );
	$mcode = "<?php\n\$GLOBALS['ran'][] = 'm';\n";
	$mid   = make( 'site', 'Del sitio 2', $mcode );
	$S::insert_row( 2, $S::off_name( 'site', $mid, hash( 'sha256', $mcode ) ), array( 't' => time(), 'msg' => 'API_KEY=sk_live_123', 'path' => '/' ), true );
	$GLOBALS['t']['mails'] = array();
	$S::reconcile( 'site' );
	check( 1 === count( $GLOBALS['t']['mails'] ) && 'red@sitio.test' === $GLOBALS['t']['mails'][0]['to'] && 'red@sitio.test' === $S::mail_to( 'site' ), 'red: el correo de fallo de un fragmento de sitio va a la dirección de la red, no a la del subsitio' );
	// La petición pública de rescate: una vez cada 15 minutos en toda la red.
	$GLOBALS['t']['mails'] = array();
	check( true === $X::send_request(), 'red: la petición pública del sitio 2 envía el correo' );
	restore_current_blog();
	switch_to_blog( 3 );
	check( false === $X::send_request() && 1 === count( $GLOBALS['t']['mails'] ), 'red: otro sitio, antes de 15 minutos, no envía otro (límite de toda la red)' );
	$GLOBALS['t']['db']['wp_options'][ $S::RESCUE_REQ_NETWORK ]['v'] = serialize( array( 'at' => time() - 901 ) );
	check( true === $X::send_request() && 2 === count( $GLOBALS['t']['mails'] ) && 'red@sitio.test' === $GLOBALS['t']['mails'][1]['to'], 'red: pasados 15 minutos, sí' );
	restore_current_blog();

	// digitalisimo_tools_php_ready: sólo en el escritorio, sin AJAX y con can_stop; después, ninguna consulta.
	reset_world();
	users_world();
	$GLOBALS['t']['logged_in'] = false;
	check( false === $I::ready() && null === row( $S::SITE_INDEX ), 'ready: un visitante sin sesión no crea el índice' );
	$GLOBALS['t']['logged_in'] = true;
	$GLOBALS['t']['ajax'] = true;
	check( false === $I::ready() && null === row( $S::SITE_INDEX ), 'ready: tampoco en AJAX' );
	$GLOBALS['t']['ajax'] = false;
	$GLOBALS['t']['caps'] = array( 'read' );
	check( false === $I::ready(), 'ready: tampoco sin can_stop' );
	$GLOBALS['t']['caps'] = array( 'edit_themes', 'unfiltered_html', 'manage_options', 'manage_network_options' );
	check( true === $I::ready() && null !== row( $S::SITE_INDEX ) && 'on' === row( $I::READY_FLAG )['a'], 'ready: con sesión y permiso crea el índice; la marca va con autoload' );
	$GLOBALS['t']['queries'] = array();
	check( false === $I::ready() && 0 === count_queries( 'digitalisimo_tools_php' ), 'ready: después, ninguna consulta' );

	/* =================================================================== *
	 * Constante (al final: no se puede deshacer)
	 * =================================================================== */
	reset_world();
	update_option( 'stylesheet', 'tema' );
	make( 'site', 'Con constante', "<?php\n\$GLOBALS['ran'][] = 'constante';\n" );
	define( 'DIGITALISIMO_TOOLS_PHP_SAFE_MODE', true );
	run_now();
	check( array() === $GLOBALS['ran'] && 'constant' === $R::skipped() && $R::reduced() && $A::reduced(), 'la constante: no corre nada y la pantalla entra en modo reducido' );
	check( 'reduced' === $D::mode( 'site' ) && $A::REDUCED_MESSAGE === $D::dispatch( post_for( 'prepare', 'new', array( 'code_b64' => base64_encode( "<?php\n" ) ) ), true )['message'], 'con la constante, la pantalla sólo reduce' );
	$GLOBALS['t']['is_admin'] = true;
	$_GET = array( 'page' => $S::PAGE, 'tab' => 'php', 'edit' => 'new' );
	ob_start(); $D::page( false ); $html = ob_get_clean();
	check( false !== strpos( $html, $D::CONSTANT_TEXT ) && false === strpos( $html, '<textarea' ) && false !== strpos( $html, 'Desactivar' ), 'con la constante: aviso, lista sin código y «Desactivar»' );
	$_GET = array();
	define( 'DISALLOW_FILE_EDIT', true );
	check( $A::editing_blocked(), 'DISALLOW_FILE_EDIT bloquea la edición' );

	array_map( 'unlink', glob( $root . '/*.log' ) );
	@rmdir( $root . '/wp-includes' );
	@rmdir( $root );
	echo "Tools · Código PHP: OK\n";
}
