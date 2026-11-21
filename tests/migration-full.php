<?php
/**
 * «Migrar» lo hace todo (DIGITALÍSIMO Migración, regla del propietario del 2026-11-18): el mapa de reemplazos, el índice
 * de la Release, la huella de la portada y la tarea completa por etapas con dobles de Backups, SEO, la tarea de
 * contenido (Runner) y el instalador de WordPress. Comprueba: respaldo antes de tocar nada; Elements antes que SEO; un
 * respaldo, una instalación o un importador que fallan no borran nada; un tema no seguro no se cambia; una portada que
 * no responde igual vuelve al tema anterior y no borra nada; lo que aún se necesita no se borra; reanudación a mitad;
 * doble clic y cerrojo. Cada escenario corre en su propio proceso: las clases de un plugin «se cargan» en la primera
 * petición que empieza con él activo, como en un sitio real.
 */
if ( '' === (string) ( $argv[1] ?? '' ) ) {
	$scenarios = array( 'unit', 'network', 'cli_network', 'cli_disallow', 'dependents', 'yoast_gaps', 'updraft_remote', 'updraft_unseen', 'cleanup_crash', 'maintenance', 'cached', 'rerun', 'backup_gone', 'uninstall_file', 'verify_crash', 'slow_boot', 'old_wp', 'happy', 'backup_history', 'backup_fails', 'install_fails', 'import_fails', 'theme_child', 'theme_child_tools', 'theme_child_covered', 'theme_child_ok', 'theme_child_old', 'theme_child_old_fails', 'theme_old_current', 'theme_child_files', 'theme_child_files_only', 'theme_child_risky', 'theme_child_network', 'theme_child_skipped', 'theme_child_paused', 'theme_child_auto', 'theme_child_auto_revert', 'theme_child_auto_refused', 'theme_child_auto_network', 'theme_child_retire', 'theme_child_retire_kept', 'theme_child_retire_refs', 'theme_child_hello_paths', 'theme_child_retire_broken', 'theme_child_retire_resume', 'theme_update_resume', 'theme_update_own_site', 'theme_rest_broken', 'theme_child_recheck', 'theme_warm_fatal', 'theme_warm_fatal_before', 'theme_pre_unreadable', 'theme_other', 'verify_fails', 'verify_styles', 'pending_content', 'resume', 'double_click', 'cli', 'no_loopback' );
	$failed    = 0;
	foreach ( $scenarios as $scenario ) {
		$out = array();
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( $scenario ) . ' 2>&1', $out, $code );
		echo implode( "\n", $out ), "\n";
		$failed += 0 === $code ? 0 : 1;
	}
	echo $failed ? "FALLA migration-full: {$failed} escenarios\n" : "OK migration-full: " . count( $scenarios ) . " escenarios\n";
	exit( $failed ? 1 : 0 );
}

$scenario = (string) $argv[1];
$tmp      = sys_get_temp_dir() . '/digi-migration-full-' . getmypid() . '-' . $scenario;
@mkdir( $tmp . '/wp-admin/includes', 0777, true );
foreach ( array( 'plugin', 'file', 'theme', 'misc', 'class-wp-upgrader' ) as $stub ) {
	file_put_contents( $tmp . '/wp-admin/includes/' . $stub . '.php', '<?php' );
}
@mkdir( $tmp . '/plugins', 0777, true );
@mkdir( $tmp . '/store', 0777, true );
@mkdir( $tmp . '/core', 0777, true );
file_put_contents( $tmp . '/core/elementor.php', '<?php' );
register_shutdown_function( static function () use ( $tmp ) {
	exec( 'rm -rf ' . escapeshellarg( $tmp ) );
} );

define( 'ABSPATH', $tmp . '/' );
define( 'WP_PLUGIN_DIR', $tmp . '/plugins' );
define( 'DIGITALISIMO_MIGRATION_DIR', dirname( __DIR__ ) . '/digitalisimo-migration/' );
define( 'DIGITALISIMO_MIGRATION_VERSION', '1.0.28' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'TMP', $tmp );

$GLOBALS['problems'] = array();
function check( $ok, $message ) {
	if ( ! $ok ) {
		$GLOBALS['problems'][] = $message;
	}
}

/* ---------------------------------------------------------------- WordPress mínimo */

class WP_Error {
	private $m;
	public function __construct( $c = '', $m = '' ) { $this->m = $m; }
	public function get_error_message() { return $this->m; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function blog() { return (int) ( $GLOBALS['t']['blog'] ?? 1 ); }
function get_option( $k, $d = false ) { $o = $GLOBALS['t']['options'][ blog() ] ?? array(); return array_key_exists( $k, $o ) ? $o[ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['t']['options'][ blog() ][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['t']['options'][ blog() ][ $k ] ); return true; }
function get_blog_option( $id, $k, $d = false ) { $o = $GLOBALS['t']['options'][ (int) $id ] ?? array(); return array_key_exists( $k, $o ) ? $o[ $k ] : $d; }
// Fuera de una red, las opciones «de red» son las del sitio, como en WordPress.
function get_site_option( $k, $d = false ) { if ( ! is_multisite() ) { return get_option( $k, $d ); } return array_key_exists( $k, $GLOBALS['t']['site'] ?? array() ) ? $GLOBALS['t']['site'][ $k ] : $d; }
function update_site_option( $k, $v ) { if ( ! is_multisite() ) { return update_option( $k, $v ); } $GLOBALS['t']['site'][ $k ] = $v; return true; }
function delete_site_option( $k ) { if ( ! is_multisite() ) { return delete_option( $k ); } unset( $GLOBALS['t']['site'][ $k ] ); return true; }
function switch_to_blog( $id ) { $GLOBALS['t']['stack'][] = blog(); $GLOBALS['t']['blog'] = (int) $id; return true; }
function restore_current_blog() { $GLOBALS['t']['blog'] = array_pop( $GLOBALS['t']['stack'] ); return true; }
function get_site( $id ) { return (object) array( 'archived' => 0, 'spam' => 0, 'deleted' => 0 ); }
function get_sites( $args = array() ) { return array( 1, 2 ); }
function get_current_network_id() { return 1; }
function get_networks( $args = array() ) { return $GLOBALS['t']['networks'] ?? array( 1 ); }
function get_bloginfo( $k ) { return 'Sitio ' . blog(); }
function wp_is_file_mod_allowed( $context ) { return empty( $GLOBALS['t']['no_file_mods'] ); }
$GLOBALS['wp_version'] = '6.9';
function get_site_transient( $k ) { return $GLOBALS['t']['transients'][ $k ] ?? false; }
function set_site_transient( $k, $v, $e = 0 ) { $GLOBALS['t']['transients'][ $k ] = $v; return true; }
function is_multisite() { return ! empty( $GLOBALS['t']['multisite'] ); }
function is_main_site() { return 1 === blog(); }
function get_main_site_id() { return 1; }
function get_current_blog_id() { return blog(); }
function get_current_user_id() { return 1; }
function current_user_can( $cap ) { return true; }
function is_super_admin() { return true; }
function wp_generate_password( $n = 12, $s = true ) { return substr( bin2hex( random_bytes( 16 ) ), 0, $n ); }
function apply_filters( $tag, $value, ...$args ) {
	foreach ( (array) ( $GLOBALS['t']['filters'][ $tag ] ?? array() ) as $callback ) {
		$value = $callback( $value, ...$args );
	}
	return $value;
}
function add_action() {}
function add_filter() {}
function has_filter( $tag = '' ) { return ! empty( $GLOBALS['t']['filters'][ $tag ] ); }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/\\' ); }
function get_theme_root( $s = '' ) { return TMP . '/themes'; }
function get_post_type( $id ) { return 'page'; }
/** Sin uploads en la prueba: la copia del hijo del tema propio no se puede hacer (y queda incompleta, con su motivo). */
function wp_upload_dir( $time = null, $create = true ) { return array( 'error' => 'sin uploads en la prueba' ); }
function wp_mkdir_p( $dir ) { return false; }
/** Lo que deja el tema propio al cambiar desde un hijo cuya hoja carga: sin la copia completa en uploads. */
function child_not_copied( $slug ) {
	update_option( 'digitalisimo_theme_child', array( 'v' => 1, 'child' => $slug, 'parent' => 'hello-elementor', 'assets' => array( array( 'type' => 'style', 'handle' => 'hello-elementor-child-style', 'file' => 'style.css', 'from' => 'child' ) ) ) );
}
function get_the_title( $id ) { return 'Inicio'; }
/** La carpeta del tema propio ya instalado, en disco (la copia propia de Migrar la necesita para actualizarlo). */
function old_theme_dir( $version = '1.0.0' ) {
	@mkdir( TMP . '/themes/digitalisimo-theme', 0777, true );
	file_put_contents( TMP . '/themes/digitalisimo-theme/style.css', "/*\nTheme Name: DIGITALÍSIMO\nVersion: {$version}\n*/\n" );
}
if ( ! defined( 'MB_IN_BYTES' ) ) {
	define( 'MB_IN_BYTES', 1048576 );
}
function wp_next_scheduled( $hook ) { return $GLOBALS['t']['cron'][ $hook ] ?? false; }
function wp_schedule_single_event( $time, $hook ) { $GLOBALS['t']['cron'][ $hook ] = $time; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['t']['cron'][ $hook ] ); }
function home_url( $path = '' ) { return 'http://example.test' . ( 1 === blog() ? '' : '/dos' ) . $path; }
function get_posts( $args ) { return array( 10 ); }
function get_permalink( $id ) { return home_url( '/sobre/' ); }
function add_query_arg( $k, $v, $url ) { return $url; }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function get_filesystem_method() { return 'direct'; }
function wp_timezone_string() { return 'UTC'; }
function current_time( $type ) { return gmdate( 'Y-m-d H:i:s' ); }
function wp_clean_plugins_cache( $x = true ) {}
function wp_clean_themes_cache( $x = true ) {}
function get_plugins() { return $GLOBALS['t']['plugins']; }
// Transitorios por sitio, guardados como opciones (como WordPress sin caché de objetos).
function set_transient( $k, $v, $e = 0 ) { return update_option( '_transient_' . $k, $v ); }
function get_transient( $k ) { return get_option( '_transient_' . $k, false ); }
function delete_transient( $k ) { return delete_option( '_transient_' . $k ); }
function activate_plugin( $file, $r = '', $network = false ) {
	if ( ! isset( $GLOBALS['t']['plugins'][ $file ] ) ) {
		return new WP_Error( 'x', 'no instalado' );
	}
	$GLOBALS['t']['log'][] = 'activate:' . $file . ( $network ? ':red' : '' );
	// El gancho de activación del plugin (`activate_<archivo>`), en el sitio en curso, como WordPress.
	if ( isset( $GLOBALS['t']['on_activate'][ $file ] ) ) {
		( $GLOBALS['t']['on_activate'][ $file ] )( $network );
	}
	if ( $network ) {
		$GLOBALS['t']['site']['active_sitewide_plugins'][ $file ] = time();
		return null;
	}
	$active   = (array) get_option( 'active_plugins', array() );
	$active[] = $file;
	update_option( 'active_plugins', array_values( array_unique( $active ) ) );
	return null;
}
function deactivate_plugins( $files, $silent = false, $network = null ) {
	foreach ( (array) $files as $file ) {
		$GLOBALS['t']['log'][] = 'deactivate:' . $file . ( $network ? ':red' : '' );
		if ( $network ) {
			unset( $GLOBALS['t']['site']['active_sitewide_plugins'][ $file ] );
		}
	}
	if ( ! $network ) {
		update_option( 'active_plugins', array_values( array_diff( (array) get_option( 'active_plugins', array() ), (array) $files ) ) );
	}
}
function delete_plugins( $files ) {
	foreach ( (array) $files as $file ) {
		$GLOBALS['t']['log'][] = 'delete:' . $file;
		unset( $GLOBALS['t']['plugins'][ $file ] );
		@unlink( WP_PLUGIN_DIR . '/' . $file );
		// Una petición que muere a mitad del borrado (después de borrar este).
		if ( ( $GLOBALS['t']['crash_on'] ?? '' ) === $file ) {
			unset( $GLOBALS['t']['crash_on'] );
			throw new RuntimeException( 'la petición murió borrando' );
		}
	}
	return true;
}
class WP_Theme {
	public static function network_enable_theme( $s ) { $GLOBALS['t']['log'][] = 'network_enable_theme:' . $s; }
	public $slug;
	public function __construct( $slug ) { $this->slug = $slug; }
	public function exists() { return isset( $GLOBALS['t']['themes'][ $this->slug ] ); }
	public function get( $key ) { return $GLOBALS['t']['themes'][ $this->slug ][ $key ] ?? ''; }
	public function get_template() { return $GLOBALS['t']['themes'][ $this->slug ]['Template'] ?? $this->slug; }
	public function get_stylesheet() { return $this->slug; }
	public function get_stylesheet_directory() { return TMP . '/themes/' . $this->slug; }
	public function parent() { return $this->get_template() !== $this->slug ? new WP_Theme( $this->get_template() ) : false; }
	public function get_post_templates() { return array(); }
}
/** Un tema hijo de Hello en disco: su functions.php (sólo se lee). */
function child_theme( $slug, $name, $functions ) {
	$GLOBALS['t']['themes'][ $slug ] = array( 'Name' => $name, 'Template' => 'hello-elementor', 'Version' => '2.0.0' );
	@mkdir( TMP . '/themes/' . $slug, 0777, true );
	file_put_contents( TMP . '/themes/' . $slug . '/style.css', "/*\nTheme Name: {$name}\nTemplate: hello-elementor\n*/\nbody{background:#fafafa}\n" );
	file_put_contents( TMP . '/themes/' . $slug . '/functions.php', $functions );
	update_option( 'stylesheet', $slug );
}
const OFFICIAL_CHILD = "<?php\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\ndefine( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );\nfunction hello_elementor_child_scripts_styles() {\n\twp_enqueue_style( 'hello-elementor-child-style', get_stylesheet_directory_uri() . '/style.css', [ 'hello-elementor-theme-style' ], HELLO_ELEMENTOR_CHILD_VERSION );\n}\nadd_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );\n";
const PHP_CHILD      = OFFICIAL_CHILD . "add_action( 'wp_head', function () {\n\techo '<style>.x{color:red}</style>';\n} );\nadd_action( 'wp_footer', function () {\n\techo '<script>1</script>';\n} );\n";
/** Tools activo en el sitio, con su importación cargada (contesta $answer). */
function tools_here( $answer = 'created' ) {
	$GLOBALS['t']['filters']['digitalisimo_tools_php_import'][] = static function ( $result, $items ) use ( $answer ) {
		$GLOBALS['t']['imports'][] = $items;
		return array( 'digitalisimo-theme:' . $items[0]['origin']['id'] => $answer );
	};
	update_option( 'active_plugins', array_merge( (array) get_option( 'active_plugins', array() ), array( \Digitalisimo\Migration\Full::TOOLS_FILE ) ) );
}
/** El functions.php del hijo («Hello Hijo», PHP_CHILD), con la condición, activo en Tools salvo si está `paused`. */
function snippet_active() {
	$GLOBALS['t']['filters']['digitalisimo_tools_php_sources'][] = static function ( $rows ) {
		$wrap = function_exists( 'digitalisimo_theme_child_wrap_php' ) ? digitalisimo_theme_child_wrap_php( PHP_CHILD, 'Hello Hijo' ) : '';
		return array_merge( $rows, array( array( 'scope' => 'site', 'id' => 'a1', 'active' => empty( $GLOBALS['t']['paused'] ), 'hash' => hash( 'sha256', $wrap ) ) ) );
	};
}
/**
 * Tools con su vía para Migrar (Import::activate_import() y ::deactivate_import()): el importado del hijo «Hello Hijo»
 * empieza sin revisar; activarlo lo deja activo (salvo que el guardián lo pause: `paused`) y desactivarlo lo apaga.
 */
function tools_auto() {
	tools_here();
	$GLOBALS['t']['filters']['digitalisimo_tools_php_activate_import'][] = static function ( $result, $key, $context ) {
		$GLOBALS['t']['log'][] = 'snippet_activate:' . $key;
		$GLOBALS['t']['snippet_hash'] = (string) ( $context['hash'] ?? '' );
		if ( ! empty( $GLOBALS['t']['snippet_refuse'] ) ) {
			return $GLOBALS['t']['snippet_refuse'];
		}
		$was                          = ! empty( $GLOBALS['t']['snippet_on'] );
		$GLOBALS['t']['snippet_on'] = true;
		return $was ? 'active' : 'activated';
	};
	$GLOBALS['t']['filters']['digitalisimo_tools_php_deactivate_import'][] = static function ( $result, $key, $context ) {
		$GLOBALS['t']['log'][]        = 'snippet_deactivate:' . $key . ':' . ( $context['hash'] ?? '' );
		$GLOBALS['t']['snippet_on'] = false;
		return true;
	};
	$GLOBALS['t']['filters']['digitalisimo_tools_php_sources'][] = static function ( $rows ) {
		if ( empty( $GLOBALS['t']['snippet_on'] ) ) {
			return $rows;
		}
		$wrap = digitalisimo_theme_child_wrap_php( PHP_CHILD, 'Hello Hijo' );
		return array_merge( $rows, array( array( 'scope' => 'site', 'id' => 'a1', 'active' => empty( $GLOBALS['t']['paused'] ), 'hash' => hash( 'sha256', $wrap ) ) ) );
	};
}
function wp_get_theme( $slug = null ) { return new WP_Theme( null === $slug ? get_option( 'stylesheet' ) : $slug ); }
function wp_get_themes() {
	$out = array();
	foreach ( array_keys( $GLOBALS['t']['themes'] ) as $slug ) {
		$out[ $slug ] = new WP_Theme( $slug );
	}
	return $out;
}
function get_template() { return (string) get_option( 'template' ); }
function get_stylesheet() { return (string) get_option( 'stylesheet' ); }
function switch_theme( $stylesheet ) {
	$GLOBALS['t']['log'][] = 'switch_theme:' . $stylesheet;
	update_option( 'stylesheet', $stylesheet );
	update_option( 'template', $GLOBALS['t']['themes'][ $stylesheet ]['Template'] ?? $stylesheet );
}
function delete_theme( $slug ) {
	$GLOBALS['t']['log'][] = 'delete_theme:' . $slug;
	unset( $GLOBALS['t']['themes'][ $slug ] );
	return true;
}

/** $wpdb mínimo para los cerrojos con INSERT IGNORE y token. */
final class Fake_Wpdb {
	public $options  = 'wp_options';
	public $posts    = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public function get_blog_prefix( $id = null ) { return 'wp_'; }
	public function esc_like( $s ) { return $s; }
	public function prepare( $sql, ...$args ) {
		$i = 0;
		return preg_replace_callback( '/%[sd]/', static function ( $m ) use ( $args, &$i ) {
			$v = $args[ $i++ ];
			return '%d' === $m[0] ? (string) (int) $v : "'" . addslashes( (string) $v ) . "'";
		}, $sql );
	}
	private function &rows() {
		if ( ! isset( $GLOBALS['t']['rows'] ) ) {
			$GLOBALS['t']['rows'] = array();
		}
		return $GLOBALS['t']['rows'];
	}
	public function query( $sql ) {
		$rows = &$this->rows();
		if ( preg_match( "/INSERT IGNORE INTO \S+ \( option_name, option_value, autoload \) VALUES \( '([^']*)', '([^']*)'/", $sql, $m ) ) {
			if ( isset( $rows[ $m[1] ] ) ) {
				return 0;
			}
			$rows[ $m[1] ] = $m[2];
			return 1;
		}
		if ( preg_match( "/UPDATE \S+ SET option_value = '([^']*)' WHERE option_name = '([^']*)' AND option_value (=|LIKE) '([^']*)'/", $sql, $m ) ) {
			$held = $rows[ $m[2] ] ?? null;
			$ok   = null !== $held && ( '=' === $m[3] ? $held === $m[4] : substr( $held, -strlen( ltrim( $m[4], '%' ) ) ) === ltrim( $m[4], '%' ) );
			if ( $ok ) {
				$rows[ $m[2] ] = $m[1];
			}
			return $ok ? 1 : 0;
		}
		if ( preg_match( "/DELETE FROM \S+ WHERE option_name = '([^']*)' AND option_value LIKE '([^']*)'/", $sql, $m ) ) {
			$held = $rows[ $m[1] ] ?? null;
			if ( null !== $held && substr( $held, -strlen( ltrim( $m[2], '%' ) ) ) === ltrim( $m[2], '%' ) ) {
				unset( $rows[ $m[1] ] );
				return 1;
			}
			return 0;
		}
		return 0;
	}
	public function get_var( $sql ) {
		$rows = &$this->rows();
		// Búsqueda de rutas del tema hijo (Full::child_references()): `refs` dice qué contesta cada columna.
		if ( preg_match( "/WHERE (post_content|meta_value|option_value) LIKE '%themes\/([^\/']+)\/%'/", $sql, $m ) ) {
			$GLOBALS['t']['log'][] = 'refs:' . $m[1] . ':' . $m[2];
			return $GLOBALS['t']['refs'][ $m[1] ] ?? null;
		}
		return preg_match( "/WHERE option_name = '([^']*)'/", $sql, $m ) ? ( $rows[ $m[1] ] ?? null ) : null;
	}
}
$GLOBALS['wpdb'] = new Fake_Wpdb();

/* ---------------------------------------------------------------- Plugins que «se cargan» al activarlos */

const BACKUPS  = 'digitalisimo-backups/digitalisimo-backups.php';
const ELEMENTS = 'digitalisimo-elements/pro-elements.php';
const SEO      = 'digitalisimo-seo/digitalisimo-integrations.php';

/** Clases de cada plugin DIGITALÍSIMO: se definen sólo si el plugin estaba activo al empezar la petición. */
spl_autoload_register( static function ( $class ) {
	$loaded = (array) ( $GLOBALS['t']['loaded'] ?? array() );
	$groups = array(
		BACKUPS  => array( 'Digitalisimo_Backups_Jobs', 'Digitalisimo_Backups_Job_Store', 'Digitalisimo_Backups', 'Digitalisimo_Backups_Backuply_Import' ),
		SEO      => array( 'Digitalisimo_Integrations_RankMath_Import', 'Digitalisimo_Integrations_SEO' ),
		ELEMENTS => array( 'Digitalisimo\\Elements\\Legacy\\Runner', 'Digitalisimo_Elements_Core', 'ElementorPro\\Modules\\ThemeBuilder\\Module' ),
	);
	foreach ( $groups as $file => $classes ) {
		if ( in_array( ltrim( $class, '\\' ), $classes, true ) && in_array( $file, $loaded, true ) ) {
			define_fakes( $file );
		}
	}
} );

function define_fakes( $file ) {
	static $done = array();
	if ( isset( $done[ $file ] ) ) {
		return;
	}
	$done[ $file ] = true;
	if ( BACKUPS === $file ) {
		eval( <<<'PHP'
final class Digitalisimo_Backups_Jobs {
	public static function spec( array $a ) { $GLOBALS['t']['specs'][] = $a; return $a; }
	public static function enqueue( array $spec ) {
		$GLOBALS['t']['log'][] = 'backup:enqueue';
		$GLOBALS['t']['job'] = array( 'id' => 'job1', 'status' => 'queued', 'ticks' => 0, 'progress' => array( 'percent' => 0 ), 'spec' => $spec );
		return array( 'duplicate' => false, 'job' => $GLOBALS['t']['job'] );
	}
	public static function schedule( $spawn = false ) {}
	public static function get( $id ) {
		// Con `job_vanishes`, el registro de trabajos ya retiró el trabajo terminado: sólo queda el historial.
		if ( ! empty( $GLOBALS['t']['job_vanishes'] ) && 'success' === ( $GLOBALS['t']['job']['status'] ?? '' ) ) { return null; }
		return $GLOBALS['t']['job'] ?? null;
	}
	public static function tick( $budget = null ) {
		$job = &$GLOBALS['t']['job'];
		if ( ++$job['ticks'] < 2 ) { $job['progress']['percent'] = 40; return array( 'result' => 'worked' ); }
		if ( ! empty( $GLOBALS['t']['backup_fails'] ) ) { $job['status'] = 'failed'; $job['error'] = array( 'message' => 'sin espacio en disco' ); return array( 'result' => 'worked' ); }
		$body = "PK\x03\x04" . str_repeat( 'x', 64 ) . "PK\x05\x06" . str_repeat( "\0", 18 );
		file_put_contents( TMP . '/store/backup-1.zip', $body );
		$job['status'] = 'success';
		$job['result'] = array( 'file' => 'backup-1.zip', 'size' => strlen( $body ), 'sha256' => hash( 'sha256', $body ) );
		update_option( 'digitalisimo_backups_history', array( array( 'date' => gmdate( 'Y-m-d H:i:s' ), 'file' => 'backup-1.zip', 'size' => strlen( $body ), 'sha256' => hash( 'sha256', $body ), 'policy' => 'pre-migration', 'backup_type' => 'full', 'origin' => 'created' ) ) );
		$GLOBALS['t']['log'][] = 'backup:success';
		return array( 'result' => 'worked' );
	}
}
final class Digitalisimo_Backups_Job_Store { public static function is_terminal( $job ) { return in_array( $job['status'] ?? '', array( 'success', 'failed', 'cancelled' ), true ); } }
final class Digitalisimo_Backups {
	public static function storage_directory() { return TMP . '/store'; }
	/** Como Backups: el lector de UpdraftPlus sólo se carga cuando se pide. */
	public static function load_updraft_import() {
		if ( ! class_exists( 'Digitalisimo_Backups_Updraft_Import', false ) ) {
			eval( 'final class Digitalisimo_Backups_Updraft_Import { public static function sources() { return array( array( "id" => "a" ), array( "id" => "b" ) ); } }' );
		}
	}
}
final class Digitalisimo_Backups_Backuply_Import { public static function sources() { return array(); } }
PHP
		);
	}
	if ( SEO === $file ) {
		eval( <<<'PHP'
final class Digitalisimo_Integrations_RankMath_Import {
	public static function detect() { return array( 'any' => true ); }
	public static function start() { $GLOBALS['t']['log'][] = 'rankmath:start'; update_option( 'rm_job', array( 'status' => 'running', 'started' => time() ) ); }
	public static function job() { return (array) get_option( 'rm_job', array() ); }
	public static function run_batch() {
		$job = self::job();
		$job['status'] = ! empty( $GLOBALS['t']['import_fails'] ) ? 'error' : 'done';
		$job['error'] = 'la tabla de redirecciones no se pudo leer';
		$job['done'] = array( 'posts' => 7, 'options' => 3 );
		update_option( 'rm_job', $job );
	}
}
final class Digitalisimo_Integrations_SEO {
	public static function import_legacy( array $sources, $after = 0, $limit = 0 ) { return array( 'imported' => 4, 'found' => 2, 'next' => 0, 'done' => true ); }
}
PHP
		);
	}
	if ( ELEMENTS === $file ) {
		eval( <<<'PHP'
namespace ElementorPro\Modules\ThemeBuilder { class Module {} }
namespace {
	final class Digitalisimo_Elements_Core { public static function dir() { return TMP . '/core/'; } public static function version() { return '3.30.0'; } }
}
namespace Digitalisimo\Elements\Legacy {
	/** Tarea de contenido simulada: desactiva la familia de Elementor (y pide una petición nueva) y después convierte. */
	final class Runner {
		const FAMILY = array( 'elementor/elementor.php', 'bdthemes-element-pack/bdthemes-element-pack.php' );
		public static function load() {}
		public static function requirements() { return null; }
		public static function state() { $s = get_option( 'fake_runner_job', null ); return is_array( $s ) ? $s : null; }
		public static function report() { $r = get_option( 'fake_runner_report', null ); return is_array( $r ) ? $r : null; }
		public static function has_pending( $report ) { return ! empty( $report['report']['ep_needed'] ); }
		public static function render_network( $job ) { return ''; }
		public static function network_state() { $j = get_site_option( 'fake_net_job', null ); return is_array( $j ) ? $j : null; }
		/** Red: desactiva la familia de Elementor (en la red y en cada sitio) y, con `net_stuck`, nunca termina. */
		public static function network_start( $capable = null ) {
			$GLOBALS['t']['log'][] = 'content:network_start';
			deactivate_plugins( array_values( array_intersect( self::FAMILY, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) ) ), false, true );
			foreach ( array( 1, 2 ) as $id ) {
				switch_to_blog( $id );
				deactivate_plugins( array_values( array_intersect( self::FAMILY, (array) get_option( 'active_plugins', array() ) ) ) );
				restore_current_blog();
			}
			update_site_option( 'fake_net_job', array( 'started' => time(), 'done' => false, 'sites' => array( 1, 2 ) ) );
			return array();
		}
		public static function network_release() { return (array) self::network_state(); }
		public static function network_pending_sites( $job ) { return array(); }
		public static function network_summary( $finish = true ) {
			$job = self::network_state();
			if ( ! $job || ! empty( $GLOBALS['t']['net_stuck'] ) ) { return (array) $job; }
			foreach ( array( 1, 2 ) as $id ) {
				switch_to_blog( $id );
				update_option( 'fake_runner_report', array( 'done' => true, 'run' => 'red', 'finished' => time(), 'report' => array( 'ep_needed' => false, 'late_kept' => false, 'blocked' => array(), 'failed' => array(), 'review' => array() ) ) );
				restore_current_blog();
			}
			$job['done'] = true;
			update_site_option( 'fake_net_job', $job );
			$GLOBALS['t']['log'][] = 'content:done';
			return $job;
		}

		public static function render( $state ) { return '<p>contenido</p>'; }
		/** Partes del informe para el de la tarea completa: con `content_attention`, un asunto pendiente. */
		public static function report_parts( array $state, $where = 'site', $level = 3, $plugins = false ) {
			$attention = ! empty( $GLOBALS['t']['content_attention'] ) ? array( 'Revisa <a href="#">Portada</a>.' ) : array();
			return array( 'summary' => 'contenido convertido', 'plugins' => '', 'attention' => $attention, 'detail' => '<p>detalle del contenido</p>', 'pending' => (bool) $attention );
		}
		public static function network_parts( $job, $where = 'site', $level = 3, $plugins = false ) {
			return array( 'summary' => 'contenido de la red', 'plugins' => '', 'attention' => array(), 'detail' => '<p>detalle de la red</p>', 'pending' => false );
		}
		public static function site_step( $start = false, $budget = null, $capable = null, $run = '' ) {
			$GLOBALS['t']['runner_calls'] = (int) ( $GLOBALS['t']['runner_calls'] ?? 0 ) + 1;
			$job = self::state();
			if ( $start && ! $job ) { $job = array( 'stage' => 'deactivate', 'run' => $run ); }
			if ( ! $job ) { return array( 'idle' => true ); }
			if ( 'deactivate' === $job['stage'] ) {
				deactivate_plugins( array_values( array_intersect( self::FAMILY, (array) get_option( 'active_plugins', array() ) ) ) );
				$job['stage'] = 'convert';
				$job['deactivated_at'] = microtime( true );
				update_option( 'fake_runner_job', $job );
				return $job + array( 'pause' => true );
			}
			$report = array( 'done' => true, 'run' => $run, 'finished' => time(), 'report' => array( 'ep_needed' => ! empty( $GLOBALS['t']['ep_needed'] ), 'late_kept' => false, 'blocked' => array(), 'failed' => array(), 'review' => array() ) );
			update_option( 'fake_runner_report', $report );
			delete_option( 'fake_runner_job' );
			$GLOBALS['t']['log'][] = 'content:done';
			return $report;
		}
	}
	/** `--run --network` de siempre: cada sitio en su proceso (aquí sólo cuenta las rondas). */
	final class Cli {
		public static function network_content() { $GLOBALS['t']['net_rounds'] = (int) ( $GLOBALS['t']['net_rounds'] ?? 0 ) + 1; }
	}
}
PHP
		);
	}
}

/* ---------------------------------------------------------------- Sitio de partida */

function plugin( $file, $name ) {
	$GLOBALS['t']['plugins'][ $file ] = array( 'Name' => $name, 'Version' => '1.0', 'Author' => '' );
	@mkdir( dirname( WP_PLUGIN_DIR . '/' . $file ), 0777, true );
	file_put_contents( WP_PLUGIN_DIR . '/' . $file, '<?php' );
}

$GLOBALS['t'] = array( 'options' => array(), 'plugins' => array(), 'themes' => array(), 'log' => array(), 'transients' => array() );
plugin( 'elementor/elementor.php', 'Elementor' );
plugin( 'bdthemes-element-pack/bdthemes-element-pack.php', 'Element Pack Pro' );
plugin( 'seo-by-rank-math/rank-math.php', 'Rank Math SEO' );
plugin( 'updraftplus/updraftplus.php', 'UpdraftPlus - Backup/Restore' );
plugin( 'digitalisimo-migration/digitalisimo-migration.php', 'DIGITALÍSIMO Migración' );
update_option( 'active_plugins', array_keys( $GLOBALS['t']['plugins'] ) );
$GLOBALS['t']['themes'] = array( 'hello-elementor' => array( 'Name' => 'Hello Elementor' ) );
update_option( 'template', 'hello-elementor' );
update_option( 'stylesheet', 'hello-elementor' );

$entries = array();
foreach ( array( 'digitalisimo-backups' => '1.0.81', 'digitalisimo-elements' => '4.3.0.187', 'digitalisimo-seo' => '1.0.254' ) as $slug => $version ) {
	$entries[ $slug ] = array( 'version' => $version, 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.11.18.999/' . $slug . '-' . $version . '.zip' );
}
set_site_transient( 'digitalisimo_plugins_manifest', array( 'plugins' => $entries, 'themes' => array( 'digitalisimo-theme' => array( 'version' => '1.0.1', 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.11.18.999/digitalisimo-theme-1.0.1.zip' ) ) ) );

require DIGITALISIMO_MIGRATION_DIR . 'includes/class-full.php';

use Digitalisimo\Migration\Front;
use Digitalisimo\Migration\Full;
use Digitalisimo\Migration\Packages;
use Digitalisimo\Migration\Replacements;

Packages::$installer = static function ( $slug, $package, $type ) {
	$GLOBALS['t']['log'][] = 'install:' . $slug;
	if ( in_array( $slug, (array) ( $GLOBALS['t']['install_fails'] ?? array() ), true ) ) {
		return new WP_Error( 'x', 'el ZIP no se pudo descargar' );
	}
	if ( 'theme-update' === $type && in_array( 'theme-update', (array) ( $GLOBALS['t']['install_fails'] ?? array() ), true ) ) {
		// WordPress restaura la copia anterior (temp_backup): la carpeta queda como estaba.
		return new WP_Error( 'x', 'la descarga del paquete falló' );
	}
	if ( 'theme' === $type || 'theme-update' === $type ) {
		$GLOBALS['t']['themes'][ $slug ] = array( 'Name' => 'DIGITALÍSIMO', 'UpdateURI' => Replacements::THEME_URI, 'Version' => preg_replace( '/^.*-(\d+(?:\.\d+)+)\.zip$/', '$1', $package ) );
		if ( empty( $GLOBALS['t']['old_theme'] ) ) {
			@mkdir( TMP . '/themes/' . $slug . '/inc', 0777, true );
			copy( dirname( __DIR__ ) . '/digitalisimo-theme/inc/child.php', TMP . '/themes/' . $slug . '/inc/child.php' );
		}
		return true;
	}
	plugin( Replacements::own_file( $slug ), Replacements::own_label( $slug ) );
	return true;
};
$page = '<html><head><title>T</title></head><body><header>Vivero Las Flores</header><main>Plantas de interior, macetas artesanales y envíos a toda la ciudad cada semana</main><footer>Contacto y horarios</footer></body></html>';
/**
 * La portada de cada sitio (el 2 vive en /dos): enlaza la hoja del tema activo de ese sitio. Modos: `break` (error con el
 * tema propio), `maintenance` (todas las URL dan la misma página), `cached` (siempre el HTML de antes, con Hello) y
 * `changed` (otro texto, como un sitio ya migrado que se ve distinto).
 */
Front::$fetcher = static function ( $url ) use ( $page ) {
	$blog  = false !== strpos( $url, '/dos' ) ? 2 : 1;
	if ( ! empty( $GLOBALS['t']['fail_in_theme_stage'] ) && 'theme' === ( Full::load( false )['stage'] ?? '' ) ) {
		return new WP_Error( 'x', 'sin respuesta' );
	}
	$theme = ! empty( $GLOBALS['t']['cached'] ) ? 'hello-elementor' : (string) get_blog_option( $blog, 'stylesheet' );
	if ( ! empty( $GLOBALS['t']['needs_child_dir'] ) && 'digitalisimo-theme' === $theme && ! is_dir( TMP . '/themes/' . $GLOBALS['t']['needs_child_dir'] ) ) {
		return array( 500, '<p>There has been a critical error on this website.</p>' ); // Sin la carpeta del hijo, algo se rompe.
	}
	if ( ! empty( $GLOBALS['t']['rest_broken'] ) && false !== strpos( $url, 'wp-json' ) && 'digitalisimo-theme' === $theme ) {
		return array( 500, '<p>There has been a critical error on this website.</p>' );
	}
	if ( ! empty( $GLOBALS['t']['own_breaks_after_update'] ) && 'digitalisimo-theme' === $theme && 1 === $blog && '1.0.1' === ( $GLOBALS['t']['themes']['digitalisimo-theme']['Version'] ?? '' ) ) {
		return array( 500, '<p>There has been a critical error on this website.</p>' );
	}
	if ( ! empty( $GLOBALS['t']['pause_in_theme_stage'] ) && 'theme' === ( Full::load( false )['stage'] ?? '' ) ) {
		$GLOBALS['t']['paused'] = true; // El fragmento se pausa mientras se toman las huellas de justo antes.
	}
	if ( 'digitalisimo-theme' === $theme && ! empty( $GLOBALS['t']['pause_on_switch'] ) ) {
		$GLOBALS['t']['paused'] = true; // El guardián de Tools lo pausa en la primera petición con el tema propio.
	}
	if ( 'digitalisimo-theme' === $theme && ! empty( $GLOBALS['t']['warm_break'] ) ) {
		$GLOBALS['t']['warm_break'] = false; // Sólo la primera petición con el tema propio falla; las demás responden.
		return array( 500, '<p>There has been a critical error on this website.</p>' );
	}
	if ( ! empty( $GLOBALS['t']['verify_throws'] ) && 'digitalisimo-theme' === $theme ) {
		throw new RuntimeException( 'la comprobación se cortó' );
	}
	if ( ! empty( $GLOBALS['t']['break'] ) && 'digitalisimo-theme' === $theme ) {
		return array( 500, '<p>There has been a critical error on this website.</p>' );
	}
	if ( ! empty( $GLOBALS['t']['maintenance'] ) ) {
		return array( 200, '<html><body><h1>Volvemos pronto</h1><p>Estamos haciendo mejoras en el sitio web</p></body></html>' );
	}
	if ( ! empty( $GLOBALS['t']['fatal_text'] ) && false === strpos( $url, 'sobre' ) ) {
		$page = str_replace( '<main>', '<main>Cómo arreglar un Fatal error en WordPress. ', $page ); // Una entrada, no un error.
	}
	$body = ! empty( $GLOBALS['t']['changed'] ) ? str_replace( 'Plantas de interior, macetas artesanales y envíos a toda la ciudad cada semana', 'Página distinta con otro contenido completamente nuevo y ajeno', $page ) : $page;
	// Cada página tiene además su propio texto (si no, todas iguales parecerían una página de mantenimiento).
	$own  = false !== strpos( $url, 'sobre' ) ? 'Conoce nuestra historia familiar' : 'Bienvenidos a la portada principal';
	$body = str_replace( '<footer>', '<section>' . $own . '</section><footer>', $body );
	// Hojas como las imprime WordPress: las de Hello (o su equivalente del tema propio), las del hijo (que el tema propio
	// vuelve a cargar desde su carpeta si `carry`), una del constructor (`drop_sheet` la quita tras el cambio), el CSS
	// adicional y la clase del kit.
	$own_theme = 'digitalisimo-theme' === $theme;
	$sheets    = $own_theme ? '<link rel=\'stylesheet\' id=\'digitalisimo-theme-parity-reset-css\' href=\'http://example.test/wp-content/themes/digitalisimo-theme/assets/css/parity/reset.css?ver=1\' media=\'all\' />' : '<link rel=\'stylesheet\' id=\'hello-elementor-css\' href=\'http://example.test/wp-content/themes/hello-elementor/assets/css/reset.css?ver=3.5.1\' media=\'all\' />';
	$child     = (string) ( $GLOBALS['t']['child_sheet'] ?? '' );
	if ( '' !== $child && ( $child === $theme || ( $own_theme && ! empty( $GLOBALS['t']['carry'] ) ) ) ) {
		$sheets .= '<link rel=\'stylesheet\' id=\'hello-elementor-child-style-css\' href=\'http://example.test/wp-content/themes/' . $child . '/style.css?ver=2.0.0\' media=\'all\' />';
	}
	if ( ! ( $own_theme && ! empty( $GLOBALS['t']['drop_sheet'] ) ) ) {
		$sheets .= '<link rel=\'stylesheet\' id=\'elementor-post-5-css\' href=\'http://example.test/wp-content/uploads/elementor/css/post-5.css?ver=1\' media=\'all\' />';
	}
	$sheets .= '<style id="wp-custom-css">.tp{color:blue}</style>';
	$body    = str_replace( '<body>', '<body class="home elementor-kit-5">', $body );
	return array( 200, str_replace( '<head>', '<head><link rel="stylesheet" href="http://example.test/wp-content/themes/' . $theme . '/style.css">' . $sheets, $body ) );
};

/** Doble de WP-CLI: error() lanza, runcommand() anota el proceso que se relanzaría. */
function fake_wp_cli() {
	define( 'WP_CLI', true );
	eval( 'final class WP_CLI {
		public static function log( $m ) {}
		public static function success( $m ) { $GLOBALS["t"]["cli_done"] = $m; }
		public static function error( $m ) { throw new RuntimeException( $m ); }
		public static function runcommand( $command, $options = array() ) { $GLOBALS["t"]["relaunch"][] = $command; }
	}' );
}

/** «Procesos» de WP-CLI sucesivos: cada uno con el `--run-id` que le pasó el anterior. Devuelve el error, si lo hubo. */
function cli_processes( $network, $max = 30 ) {
	$run = '';
	for ( $i = 0; $i < $max && empty( $GLOBALS['t']['cli_done'] ); $i++ ) {
		usleep( 2000 );
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
		$GLOBALS['t']['loaded']        = loaded_now();
		$before                        = count( $GLOBALS['t']['relaunch'] ?? array() );
		try {
			Full::cli( $network, $run );
		} catch ( RuntimeException $e ) {
			return $e->getMessage();
		}
		if ( count( $GLOBALS['t']['relaunch'] ?? array() ) === $before ) {
			break;
		}
		$run = preg_match( '/--run-id=(\S+)/', end( $GLOBALS['t']['relaunch'] ), $m ) ? $m[1] : '';
	}
	return '';
}

/** Red de dos sitios: Element Pack, Elementor y UpdraftPlus en toda la red; Rank Math y Hello en cada sitio. */
function make_network() {
	$GLOBALS['t']['multisite'] = true;
	$GLOBALS['t']['site']['active_sitewide_plugins'] = array_fill_keys( array( 'elementor/elementor.php', 'bdthemes-element-pack/bdthemes-element-pack.php', 'updraftplus/updraftplus.php', 'digitalisimo-migration/digitalisimo-migration.php' ), 1 );
	foreach ( array( 1, 2 ) as $id ) {
		$GLOBALS['t']['options'][ $id ]['active_plugins'] = array( 'seo-by-rank-math/rank-math.php' );
		$GLOBALS['t']['options'][ $id ]['template']       = 'hello-elementor';
		$GLOBALS['t']['options'][ $id ]['stylesheet']     = 'hello-elementor';
	}
	$GLOBALS['t']['site']['digitalisimo_plugins_manifest_unused'] = 1;
}

/** Plugins cargados al empezar una petición: los activos en el sitio y en toda la red. */
function loaded_now() {
	return array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) ( $GLOBALS['t']['site']['active_sitewide_plugins'] ?? array() ) ) );
}

/**
 * Una petición nueva: carga los plugins activos y avanza la tarea. Con `slow_boot`, WordPress tarda 3,5 s en arrancar:
 * la petición empieza después del último cambio de plugins (como en la realidad) y la tarea trabaja 3,5 s más tarde.
 */
function request( $start = false, $run = 'clic1' ) {
	usleep( 2000 );
	$boot = ! empty( $GLOBALS['t']['slow_boot'] ) ? 3.5 : 0.0;
	if ( $boot ) {
		$job     = get_option( Full::JOB );
		$changed = is_array( $job ) ? (float) ( $job['changed_at'] ?? 0 ) : 0.0;
		while ( microtime( true ) - $boot <= $changed ) {
			usleep( 100000 );
		}
	}
	$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - $boot;
	$GLOBALS['t']['loaded']        = loaded_now();
	return Full::step( ! empty( $GLOBALS['t']['multisite'] ), $start, 30, $run );
}

/** Pulsa Migrar y sigue pidiendo pasos (como migrate.js) hasta terminar. */
function drive( $max = 80, $until = null ) {
	$state = request( true );
	for ( $i = 0; $i < $max && empty( $state['done'] ); $i++ ) {
		if ( null !== $until && $until === ( $state['stage'] ?? '' ) ) {
			return $state;
		}
		$state = request();
	}
	return $state;
}

function active( $file ) { return in_array( $file, (array) get_option( 'active_plugins', array() ), true ); }
function installed_now( $file ) { return isset( $GLOBALS['t']['plugins'][ $file ] ); }
function logged( $prefix ) {
	return array_values( array_filter( $GLOBALS['t']['log'], static function ( $line ) use ( $prefix ) {
		return 0 === strpos( $line, $prefix );
	} ) );
}
function position( $line ) { $at = array_search( $line, $GLOBALS['t']['log'], true ); return false === $at ? -1 : $at; }
function nothing_deleted() {
	check( ! logged( 'delete' ), 'No se borra nada: ' . implode( ', ', logged( 'delete' ) ) );
}

/* ---------------------------------------------------------------- Escenarios */

switch ( $scenario ) {
	case 'unit':
		// Mapa: familia de Elementor por Retire, Rank Math con su PRO en una fila, Yoast bloqueado por Premium, AIOSEO sin reemplazo.
		$rows = Replacements::rows(
			array(
				'seo-by-rank-math/rank-math.php'         => array( 'Name' => 'Rank Math SEO' ),
				'seo-by-rank-math-pro/rank-math-pro.php' => array( 'Name' => 'Rank Math SEO PRO' ),
				'wordpress-seo/wp-seo.php'               => array( 'Name' => 'Yoast SEO' ),
				'wordpress-seo-premium/wp-seo-premium.php' => array( 'Name' => 'Yoast SEO Premium' ),
				'all-in-one-seo-pack/all_in_one_seo_pack.php' => array( 'Name' => 'All in One SEO' ),
				'updraftplus/updraftplus.php'            => array( 'Name' => 'UpdraftPlus - Backup/Restore' ),
				'backuply/backuply.php'                  => array( 'Name' => 'Backuply' ),
				'otro/rank-math.php'                     => array( 'Name' => 'Rank Math SEO' ),
				'mi-plugin/backuply.php'                 => array( 'Name' => 'Mi copia' ),
			),
			array( 'elementor/elementor.php' => array( 'group' => 'core', 'label' => 'Elementor' ), 'bdthemes-element-pack/bdthemes-element-pack.php' => array( 'group' => 'element-pack', 'label' => 'Element Pack Pro' ), 'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php' => array( 'group' => 'element-pack', 'label' => 'Element Pack Addon' ) )
		);
		check( array( 'elementor', 'element-pack', 'rank-math', 'yoast', 'yoast-premium', 'aioseo', 'updraftplus', 'backuply' ) === array_keys( $rows ), 'Filas del mapa: ' . implode( ',', array_keys( $rows ) ) );
		check( 2 === count( $rows['element-pack']['files'] ) && 'content' === $rows['element-pack']['delete'], 'Element Pack y su Addon en una fila, borrables tras el contenido.' );
		check( 2 === count( $rows['rank-math']['files'] ) && 'digitalisimo-seo' === $rows['rank-math']['by'] && 'import' === $rows['rank-math']['delete'], 'Rank Math con su PRO en una fila (y no la copia en otra carpeta).' );
		check( '' === $rows['yoast']['by'] && false !== strpos( $rows['yoast']['reason'], 'Premium' ), 'Yoast SEO Premium deja a Yoast como está.' );
		check( '' === $rows['aioseo']['by'], 'All in One SEO no tiene reemplazo con importador.' );
		check( 'kept_data' === $rows['updraftplus']['delete'] && 'never' === $rows['backuply']['delete'] && 1 === count( $rows['backuply']['files'] ), 'UpdraftPlus se borra si conserva sus datos; Backuply nunca (y una copia con otro nombre no cuenta).' );
		check( array( 'digitalisimo-backups', 'digitalisimo-elements', 'digitalisimo-seo' ) === Replacements::needs( $rows ), 'Backups primero, Elements antes que SEO: ' . implode( ',', Replacements::needs( $rows ) ) );
		check( array( 'digitalisimo-backups' ) === Replacements::needs( array() ), 'Sin nada que reemplazar sólo hace falta Backups (el respaldo).' );
		check( array( 'digitalisimo-backups', 'digitalisimo-elements' ) === Replacements::needs( array(), true ), 'Hello necesita Elements (cabecera y pie al Theme Builder).' );
		// Reglas de borrado.
		$facts = array( 'active' => false, 'by_active' => true, 'imported' => true, 'content' => array( 'done' => true ), 'keeps_data' => true, 'uninstall_safe' => true, 'core_ready' => true );
		check( Replacements::deletable( $rows['rank-math'], $facts )[0], 'Rank Math importado y desactivado se borra.' );
		check( ! Replacements::deletable( $rows['rank-math'], array( 'imported' => false ) + $facts )[0], 'Sin importación terminada no se borra.' );
		check( ! Replacements::deletable( $rows['rank-math'], array( 'active' => true ) + $facts )[0], 'Activo en algún sitio no se borra.' );
		check( ! Replacements::deletable( $rows['rank-math'], array( 'by_active' => false ) + $facts )[0], 'Sin su reemplazo activo no se borra.' );
		check( ! Replacements::deletable( $rows['backuply'], $facts )[0], 'Backuply nunca se borra.' );
		check( ! Replacements::deletable( $rows['updraftplus'], array( 'keeps_data' => false ) + $facts )[0], 'UpdraftPlus que borraría su carpeta propia no se borra.' );
		check( ! Replacements::deletable( $rows['yoast'], $facts )[0], 'Yoast con Premium no se borra.' );
		check( ! Replacements::deletable( $rows['element-pack'], array( 'content' => array( 'done' => true, 'ep_needed' => true ) ) + $facts )[0], 'Element Pack que aún hace falta no se borra.' );
		check( ! Replacements::deletable( $rows['element-pack'], array( 'content' => array( 'done' => true, 'blocked' => 1 ) ) + $facts )[0], 'Element Pack con widgets sin equivalente no se borra.' );
		check( ! Replacements::deletable( $rows['element-pack'], array( 'uninstall_safe' => false ) + $facts )[0], 'Con una rutina de desinstalación que cargaría su código no se borra.' );
		check( ! Replacements::deletable( $rows['elementor'], array( 'core_ready' => false ) + $facts )[0], 'Elementor sin el núcleo incluido no se borra.' );
		check( Replacements::deletable( $rows['elementor'], $facts )[0], 'Elementor con el núcleo incluido se borra tras el contenido.' );
		// Índice de la Release: sólo ZIP del repositorio oficial con su slug y su versión.
		$ok  = 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/digitalisimo-seo-1.0.2.zip';
		$idx = Packages::validate( array( 'plugins' => array( 'digitalisimo-seo' => array( 'version' => '1.0.2', 'url' => $ok ), 'digitalisimo-elements' => array( 'version' => '4.3', 'url' => 'https://evil.example/digitalisimo-elements-4.3.zip' ), 'digitalisimo-tools' => array( 'version' => '1.0', 'url' => 'https://github.com/otro/digitalisimo-plugin/releases/download/v1/digitalisimo-tools-1.0.zip' ), 'digitalisimo-backups' => array( 'version' => '1.0.81', 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/digitalisimo-seo-1.0.81.zip' ) ) ) );
		check( array( 'digitalisimo-seo' ) === array_keys( $idx['plugins'] ), 'El índice sólo acepta ZIP oficiales de su slug: ' . implode( ',', array_keys( $idx['plugins'] ) ) );
		check( ! Packages::package_ok( 'digitalisimo-seo', '1.0.2', $ok . '?x' ) && ! Packages::package_ok( '../x', '1', $ok ), 'Una dirección con añadidos o un slug raro no valen.' );
		// Huella de la portada.
		$a = Front::fingerprint( 200, $page );
		check( ! $a['fatal'] && in_array( 'macetas', $a['words'], true ) && ! in_array( 't', $a['words'], true ), 'La huella toma las palabras visibles del cuerpo.' );
		check( true === Front::compare( $a, Front::fingerprint( 200, str_replace( '<header>', '<header class="x">', $page ) ) )[0], 'Otro marcado con el mismo texto responde igual.' );
		check( false === Front::compare( $a, Front::fingerprint( 200, '<p>There has been a critical error</p>' ) )[0], 'Un error crítico no responde igual.' );
		check( false === Front::compare( $a, Front::fingerprint( 404, $page ) )[0], 'Un 404 no responde igual.' );
		check( false === Front::compare( $a, Front::fingerprint( 200, '<body><main>Plantas</main></body>' ) )[0], 'Perder el texto visible no responde igual.' );
		check( null === Front::compare( array( 'status' => 0, 'error' => 'sin red' ), $a )[0], 'Lo que antes no respondía no se puede comparar.' );
		check( ! Full::verified( array( 'verify' => array( array( 'site' => 1, 'url' => 'x', 'ok' => null, 'reason' => '' ) ) ) ), 'Sin ninguna URL comparable no está verificado.' );
		check( ! Full::verified( array( 'verify' => array() ) ), 'Sin comprobaciones no está verificado.' );
		// Seguridad del tema.
		$hello = array( 'own' => false, 'hello' => true, 'child' => false, 'name' => 'Hello Elementor' );
		$ready = array( 'builder' => true, 'conflict' => false, 'can' => true );
		check( Full::theme_safety( $hello, $ready )[0], 'Hello sin hijo con el Theme Builder cargado es seguro.' );
		check( ! Full::theme_safety( array( 'child' => true ) + $hello, $ready )[0], 'Un hijo de Hello sin leer no es seguro.' );
		$hijo = array( 'child' => true, 'name' => 'Hijo' ) + $hello;
		check( Full::theme_safety( $hijo, $ready + array( 'child' => array( 'analyzed' => true, 'blockers' => '' ) ) )[0], 'Un hijo cuyo PHP sólo encola hojas es seguro.' );
		$kept = Full::theme_safety( $hijo, $ready + array( 'child' => array( 'analyzed' => true, 'blockers' => "functions.php, línea 9: add_action( 'wp_head' )" ) ) );
		check( ! $kept[0] && false !== strpos( $kept[1], "add_action( 'wp_head' )" ) && false !== strpos( $kept[1], 'activa DIGITALÍSIMO Tools en este sitio y vuelve a pulsar Migrar' ), 'Un hijo con PHP que no se traslada se queda, con qué es y qué hacer: ' . $kept[1] );
		$kept = Full::theme_safety( $hijo, $ready + array( 'child' => array( 'analyzed' => true, 'blockers' => 'x', 'imported' => 'created', 'wrap' => true, 'tools' => true ) ) );
		check( ! $kept[0] && false !== strpos( $kept[1], 'Migrar lo dejó ahí, inactivo' ) && false !== strpos( $kept[1], 'puedes activarlo ya, con el hijo activo' ) && false !== strpos( $kept[1], 'vuelve a pulsar Migrar' ), 'Importado con la condición: activarlo ya y volver a pulsar Migrar: ' . $kept[1] );
		$kept = Full::theme_safety( $hijo, $ready + array( 'child' => array( 'analyzed' => true, 'blockers' => 'x', 'imported' => 'created', 'wrap' => false, 'tools' => true ) ) );
		check( ! $kept[0] && false !== strpos( $kept[1], '1) en Apariencia activa el tema DIGITALÍSIMO' ) && strpos( $kept[1], '1) en Apariencia' ) < strpos( $kept[1], '2) enseguida revisa' ) && false !== strpos( $kept[1], 'no antes' ), 'Sin la condición posible, el orden a mano: ' . $kept[1] );
		check( false !== strpos( Full::theme_safety( $hijo, $ready + array( 'child' => array( 'analyzed' => true, 'blockers' => 'x', 'tools' => true ) ) )[1], 'en DIGITALÍSIMO Tools → Código PHP crea un fragmento' ), 'Con Tools activo y sin importar, dice que cree el fragmento.' );
		check( Full::theme_safety( $hijo, $ready + array( 'child' => array( 'analyzed' => true, 'blockers' => 'x', 'covered' => true ) ) )[0], 'Con ese PHP ya activo en Tools, el hijo puede cambiar.' );
		$kept = Full::theme_safety( $hijo, $ready + array( 'child' => array( 'analyzed' => true, 'blockers' => "functions.php, línea 9: add_action( 'wp_head' )", 'files' => 'header.php: plantilla que sustituye la del tema; template-landing.php: plantilla de página que usan 12 contenidos', 'covered' => true, 'tools' => true ) ) );
		check( ! $kept[0] && false !== strpos( $kept[1], 'plantillas o archivos que el tema DIGITALÍSIMO no traslada: header.php' ) && false !== strpos( $kept[1], 'usan 12 contenidos' ) && false !== strpos( $kept[1], "Además, su functions.php tiene PHP que no se traslada solo: functions.php, línea 9" ), 'Con el fragmento activo, las plantillas del hijo lo siguen dejando: ' . $kept[1] );
		$kept = Full::theme_safety( $hijo, $ready + array( 'child' => array( 'analyzed' => true, 'blockers' => '', 'files' => 'theme.json: estilos globales propios (theme.json)', 'tools' => true, 'wrap' => true ) ) );
		check( ! $kept[0] && false !== strpos( $kept[1], 'theme.json' ) && false === strpos( $kept[1], 'no admite la condición' ) && false === strpos( $kept[1], 'activa DIGITALÍSIMO Tools' ), 'Sólo con archivos del hijo, su propio motivo (ni la condición ni Tools): ' . $kept[1] );
		$kept = Full::theme_safety( $hijo, $ready + array( 'child' => array( 'analyzed' => true, 'blockers' => 'x', 'risks' => 'functions.php, línea 4: usa la constante HELLO_ELEMENTOR_VERSION de Hello', 'covered' => true, 'tools' => true, 'wrap' => true ) ) );
		check( ! $kept[0] && false !== strpos( $kept[1], 'con el tema DIGITALÍSIMO haría otra cosa: functions.php, línea 4: usa la constante HELLO_ELEMENTOR_VERSION' ), 'Con un riesgo, aunque esté activo, se queda y lo dice: ' . $kept[1] );
		$kept = Full::theme_safety( $hijo, $ready + array( 'child' => array( 'analyzed' => true, 'blockers' => 'x', 'imported' => 'skipped', 'tools' => true, 'filter' => true, 'wrap' => true ) ) );
		check( ! $kept[0] && false !== strpos( $kept[1], 'se borró' ) && false === strpos( $kept[1], 'no admite la condición' ), 'Fragmento borrado en Tools: lo dice: ' . $kept[1] );
		$kept = Full::theme_safety( $hijo, $ready + array( 'child' => array( 'analyzed' => true, 'blockers' => 'x', 'imported' => '', 'tools' => true, 'filter' => false, 'wrap' => true ) ) );
		check( ! $kept[0] && false !== strpos( $kept[1], 'Migrar corre desde otro sitio de la red' ) && false === strpos( $kept[1], 'no admite la condición' ), 'Tools activo en el sitio pero no cargado en la petición: lo dice: ' . $kept[1] );
		// La primera petición con el tema propio.
		check( 'respondió HTTP 500.' === Front::broken( array( 500, 'x' ) ) && 'mostró un error de PHP.' === Front::broken( array( 200, '<p>There has been a critical error on this website.</p>' ) ) && '' === Front::broken( array( 200, '<p>Hola</p>' ) ) && '' === Front::broken( new WP_Error( 'x', 'y' ) ), 'Una primera petición con 500 o con un error de PHP falla.' );
		// Hojas combinadas por un plugin de caché: un motivo propio, no «faltan hojas».
		$cached = '<html><head><link rel="stylesheet" id="autoptimize-css" href="/wp-content/cache/autoptimize/css/autoptimize_abc.css"></head><body class="elementor-kit-7">Texto</body></html>';
		$r      = Front::compare_styles( Front::fingerprint( 200, $cached ), Front::fingerprint( 200, str_replace( 'abc', 'def', str_replace( 'id="autoptimize-css" ', '', $cached ) ) ) );
		check( false === $r[0] && false !== strpos( $r[1], 'hojas combinadas por un plugin de caché' ), 'Hojas combinadas: motivo claro: ' . $r[1] );
		// El bloque de estilo de un hijo cuenta (sólo se excluyen los de Hello).
		$child_inline = '<html><head><style id="hello-elementor-child-style-inline-css">.x{}</style><style id="hello-elementor-inline-css">.y{}</style></head><body>Texto</body></html>';
		$r            = Front::compare_styles( Front::fingerprint( 200, $child_inline ), Front::fingerprint( 200, '<html><head></head><body>Texto</body></html>' ) );
		check( false === $r[0] && false !== strpos( $r[1], 'hello-elementor-child-style-inline-css' ) && false === strpos( $r[1], 'hello-elementor-inline-css,' ), 'El bloque de estilo del hijo cuenta; el de Hello no: ' . $r[1] );
		check( false !== strpos( Full::theme_safety( $hijo, $ready + array( 'child' => array( 'analyzed' => false ) ) )[1], 'anterior a 1.0.1' ), 'Sin lector del hijo (tema viejo), se queda y dice que hay que actualizarlo.' );
		check( ! Full::theme_safety( $hijo, array( 'can' => false ) + $ready + array( 'child' => array( 'analyzed' => true, 'blockers' => '' ) ) )[0], 'Sin permiso para cambiar temas, un hijo tampoco cambia.' );
		// Huella de estilo: hojas (con su handle), bloques de estilo, CSS adicional y kit.
		$styled = '<html><head><link rel="stylesheet" id="hello-elementor-css" href="https://a.test/wp-content/themes/hello-elementor/assets/css/reset.css?ver=3.5.1" media="all" /><link rel=\'stylesheet\' id=\'hijo-css\' href=\'//a.test/wp-content/themes/hijo/style.css?ver=2\' /><link rel="preload" href="x.css"><link rel="stylesheet" href="/wp-content/uploads/x.css"><style id="global-styles-inline-css">:root{}</style><style id="wp-custom-css">.a{color:red}</style></head><body class="home elementor-kit-7 x">Texto</body></html>';
		$st     = Front::styles( $styled );
		check( array( array( 'hello-elementor', '/wp-content/themes/hello-elementor/assets/css/reset.css' ), array( 'hijo', '/wp-content/themes/hijo/style.css' ), array( '', '/wp-content/uploads/x.css' ) ) === $st['sheets'] && array( 'global-styles-inline-css', 'wp-custom-css' ) === $st['inline'] && md5( '.a{color:red}' ) === $st['custom'] && 'elementor-kit-7' === $st['kit'], 'La huella de estilo lee hojas (sin dominio ni versión), bloques, CSS adicional y kit: ' . json_encode( $st ) );
		$pre    = Front::fingerprint( 200, $styled );
		$same   = str_replace( array( 'id="hello-elementor-css" href="https://a.test/wp-content/themes/hello-elementor/assets/css/reset.css?ver=3.5.1"' ), array( 'id="digitalisimo-theme-parity-reset-css" href="https://a.test/wp-content/themes/digitalisimo-theme/assets/css/parity/reset.css?ver=1.0.1"' ), $styled );
		check( true === Front::compare_styles( $pre, Front::fingerprint( 200, $same ) )[0], 'Con la hoja equivalente del tema propio y lo demás igual, se pinta igual.' );
		$cases = array(
			'sin la equivalente de Hello' => array( str_replace( 'digitalisimo-theme-parity-reset-css', 'otra-css', $same ), 'hello-elementor (su equivalente digitalisimo-theme-parity-reset)' ),
			'sin la hoja del hijo'        => array( str_replace( "<link rel='stylesheet' id='hijo-css' href='//a.test/wp-content/themes/hijo/style.css?ver=2' />", '', $same ), 'faltan hojas de estilo que se cargaban antes: hijo' ),
			'sin una hoja sin id'         => array( str_replace( '<link rel="stylesheet" href="/wp-content/uploads/x.css">', '', $same ), '/wp-content/uploads/x.css' ),
			'sin el CSS adicional'        => array( str_replace( '<style id="wp-custom-css">.a{color:red}</style>', '', $same ), 'falta el CSS adicional' ),
			'otro CSS adicional'          => array( str_replace( '.a{color:red}', '.a{color:blue}', $same ), 'el CSS adicional no es el mismo' ),
			'sin un bloque de estilo'     => array( str_replace( '<style id="global-styles-inline-css">:root{}</style>', '', $same ), 'faltan bloques de estilo: global-styles-inline-css' ),
			'sin el kit'                  => array( str_replace( 'elementor-kit-7 ', '', $same ), 'elementor-kit-7 → ninguno' ),
		);
		foreach ( $cases as $label => $case ) {
			$r = Front::compare_styles( $pre, Front::fingerprint( 200, $case[0] ) );
			check( false === $r[0] && false !== strpos( $r[1], $case[1] ), "Estilo {$label}: no se pinta igual y lo dice ({$r[1]})." );
		}
		check( true === Front::compare_styles( $pre, Front::fingerprint( 200, str_replace( '?ver=2', '?ver=3', $same ) ) )[0], 'Otra versión de la misma hoja no cuenta.' );
		$copied = str_replace( "href='//a.test/wp-content/themes/hijo/style.css?ver=2'", "href='//a.test/wp-content/uploads/digitalisimo-theme/child/hijo/style.css?ver=2'", $same );
		check( $copied !== $same && true === Front::compare_styles( $pre, Front::fingerprint( 200, $copied ) )[0], 'La hoja del hijo servida desde su copia en uploads (tema 1.0.2) es la misma hoja.' );
		check( false === Front::compare_styles( $pre, Front::fingerprint( 200, str_replace( "href='//a.test/wp-content/themes/hijo/style.css?ver=2'", "href='//a.test/wp-content/uploads/digitalisimo-theme/child/otro/style.css?ver=2'", $same ) ) )[0], 'La copia de otro hijo no cuenta.' );
		$moved = Front::compare_styles( $pre, Front::fingerprint( 200, str_replace( '//a.test/wp-content/themes/hijo/style.css', '//a.test/wp-content/themes/digitalisimo-theme/style.css', $same ) ) );
		check( false === $moved[0] && false !== strpos( $moved[1], 'faltan hojas de estilo que se cargaban antes: hijo' ), 'El mismo handle desde otra ruta (la carpeta del tema propio) no es la misma hoja: ' . $moved[1] );
		check( null === Front::compare_styles( array( 'status' => 0, 'error' => 'x' ), $pre )[0] && null === Front::compare_styles( array(), $pre )[0], 'Sin huella de antes, no comparable.' );
		check( ! Full::theme_safety( array( 'hello' => false, 'name' => 'Astra' ) + $hello, $ready )[0], 'Otro tema no es seguro.' );
		check( ! Full::theme_safety( $hello, array( 'builder' => false ) + $ready )[0], 'Sin el Theme Builder no es seguro.' );
		check( ! Full::theme_safety( $hello, array( 'conflict' => true ) + $ready )[0], 'Con Elementor Pro activo no es seguro.' );
		// Barreras de la limpieza: respaldo verificado, ninguna etapa fallida, portada verificada y permiso para borrar.
		$green = array( 'backup' => array( 'ok' => true ), 'failed' => '', 'verified' => true, 'caps' => array( 'delete' => true ) );
		check( '' === Full::cleanup_gate( $green ), 'Con todo en verde se puede borrar.' );
		check( '' !== Full::cleanup_gate( array( 'backup' => array() ) + $green ), 'Sin respaldo verificado no se borra.' );
		check( '' !== Full::cleanup_gate( array( 'failed' => 'import' ) + $green ), 'Con una etapa fallida no se borra.' );
		check( '' !== Full::cleanup_gate( array( 'verified' => false ) + $green ), 'Sin la portada verificada no se borra.' );
		check( '' !== Full::cleanup_gate( array( 'caps' => array( 'delete' => false ) ) + $green ), 'Sin permiso para borrar no se borra.' );
		// Dependientes de terceros (Requires Plugins) y verificación más estricta.
		check( array( 'Complemento' ) === Replacements::dependents( $rows['rank-math'], array( 'x/x.php' => array( 'Name' => 'Complemento', 'RequiresPlugins' => 'woocommerce, seo-by-rank-math' ), 'y/y.php' => array( 'Name' => 'Otro', 'RequiresPlugins' => 'woocommerce' ) ) ), 'Dependientes: sólo los que piden un plugin de la fila.' );
		$uniform = Front::mark_uniform( array( 'a' => Front::fingerprint( 200, '<body>Volvemos pronto</body>' ), 'b' => Front::fingerprint( 200, '<body>Volvemos pronto</body>' ) ) );
		check( null === Front::compare( $uniform['a'], $uniform['a'] )[0], 'Todas las URL con el mismo texto: no comparable.' );
		$wall = Front::compare( array( 'status' => 302, 'words' => array(), 'login' => true ), $a );
		check( null === $wall[0] && false !== strpos( $wall[1], 'sesión' ), 'Un muro de acceso: no comparable, por pedir sesión: ' . $wall[1] );
		check( null === Front::compare( Front::fingerprint( 200, '<body><img src="x"></body>' ), $a )[0], 'Sin texto visible: no comparable (antes daba por buena cualquier cosa).' );
		check( false === Front::compare( $a, $a, 'digitalisimo-theme' )[0], 'Tras cambiar de tema, un HTML sin el tema nuevo viene de caché.' );
		$many = '<body>' . implode( ' ', array_map( static function ( $i ) { return 'palabra' . $i; }, range( 1, 900 ) ) ) . '</body>';
		$fp   = Front::fingerprint( 200, $many );
		check( 400 === count( $fp['words'] ) && count( preg_grep( '/^palabra[1-4]\d\d$/', $fp['words'] ) ) < 380, 'La muestra de palabras no se sesga por orden alfabético.' );
		// UpdraftPlus conserva sus datos por defecto; con «borrar al desinstalar» y carpeta propia, no.
		check( Full::updraft_keeps_data(), 'UpdraftPlus conserva sus datos al desinstalarse por defecto.' );
		update_option( 'updraftplus_deinstall_option', 'yes' );
		update_option( 'updraft_dir', '/srv/respaldos-propios' );
		check( ! Full::updraft_keeps_data(), 'Con «borrar al desinstalar» y su carpeta propia, la perdería.' );
		update_option( 'updraft_dir', 'updraft' );
		check( Full::updraft_keeps_data(), 'Con la carpeta habitual, Backups la sigue encontrando.' );
		break;

	case 'happy':
		// Como el núcleo incluido en Elements (elementor/includes/maintenance.php): su activación deja la redirección a
		// su bienvenida para la próxima carga del escritorio.
		$GLOBALS['t']['on_activate'][ ELEMENTS ] = static function () {
			$GLOBALS['t']['log'][] = 'activation_hook:' . blog();
			set_transient( 'elementor_activation_redirect', true, 60 );
		};
		$state = drive();
		$last  = Full::last( false );
		check( ! empty( $state['done'] ) && '' === $last['failed'], 'Termina sin fallos: ' . ( $last['error'] ?? '' ) );
		check( position( 'backup:success' ) > -1 && position( 'backup:success' ) < position( 'install:digitalisimo-elements' ), 'El respaldo termina antes de instalar nada más.' );
		check( position( 'install:digitalisimo-backups' ) < position( 'backup:enqueue' ), 'Backups se instala para respaldar.' );
		check( array( 'install:digitalisimo-backups', 'install:digitalisimo-elements', 'install:digitalisimo-seo', 'install:digitalisimo-theme' ) === logged( 'install:' ), 'Instalación en orden: ' . implode( ',', logged( 'install:' ) ) );
		check( position( 'activate:' . ELEMENTS ) < position( 'activate:' . SEO ), 'Elements se activa antes que SEO.' );
		check( position( 'content:done' ) < position( 'rankmath:start' ), 'El contenido se migra antes de importar.' );
		check( active( BACKUPS ) && active( ELEMENTS ) && active( SEO ), 'Los plugins DIGITALÍSIMO quedan activos.' );
		check( 'digitalisimo-theme' === get_option( 'stylesheet' ), 'El tema propio queda activo.' );
		foreach ( array( 'elementor/elementor.php', 'bdthemes-element-pack/bdthemes-element-pack.php', 'seo-by-rank-math/rank-math.php', 'updraftplus/updraftplus.php' ) as $file ) {
			check( ! installed_now( $file ), "Borrado: {$file}" );
			check( position( 'deactivate:' . $file ) > -1 && position( 'deactivate:' . $file ) < position( 'delete:' . $file ), "Desactivado antes de borrarse: {$file}" );
		}
		check( ! isset( $GLOBALS['t']['themes']['hello-elementor'] ), 'Hello se borra.' );
		check( position( 'switch_theme:digitalisimo-theme' ) < position( 'delete_theme:hello-elementor' ), 'Hello se borra después de cambiar de tema.' );
		check( installed_now( 'digitalisimo-migration/digitalisimo-migration.php' ), 'Migración no se borra a sí misma.' );
		check( 1 === count( $GLOBALS['t']['specs'] ) && 'pre-migration' === $GLOBALS['t']['specs'][0]['policy'] && 'local' === $GLOBALS['t']['specs'][0]['destination'], 'Un solo respaldo completo, local, con su política.' );
		check( ! empty( $last['backup']['ok'] ) && 'backup-1.zip' === $last['backup']['file'], 'El informe guarda el respaldo con el que volver.' );
		check( ! empty( $last['verified'] ) && 2 === count( $last['verify'] ), 'La portada y una página responden igual.' );
		$html = Full::render( false, $last );
		foreach ( array( 'Respaldo', 'Instalado y activado', 'Importado', 'Reemplazado', 'Borrado', 'Tema', 'restaura el respaldo' ) as $needle ) {
			check( false !== strpos( $html, $needle ), "El informe dice «{$needle}»." );
		}
		// El informe corto: el contenido en una fila, «Nada necesita tu atención» en una línea y el detalle plegado.
		check( false !== strpos( $html, '<th scope="row">Contenido</th><td>contenido convertido</td>' ) && false !== strpos( $html, 'Nada necesita tu atención.' ) && false !== strpos( $html, '<details class="digi-migrate__detail"><summary>Detalle</summary><h3>Contenido</h3><p>detalle del contenido</p></details>' ), 'El contenido va en una fila, sin asuntos lo dice en una línea y el detalle va plegado.' );
		check( false !== strpos( $html, 'Responden igual: 2 de 2.' ), 'La portada dice cuántas responden igual.' );
		$GLOBALS['t']['content_attention'] = true;
		$html = Full::render( false, $last );
		check( false !== strpos( $html, '<h2 class="digi-migrate__attention-title">Necesita tu atención</h2><ul class="digi-migrate__attention" style="list-style:disc;margin-left:20px"><li>Revisa <a href="#">Portada</a>.</li></ul>' ) && strpos( $html, 'Necesita tu atención' ) < strpos( $html, '<details' ) && false === strpos( $html, 'Nada necesita' ), 'Lo que pide algo a una persona va antes del detalle, con su enlace.' );
		$cli = Full::text( Full::render( false, $last, 'cli' ) );
		check( false === strpos( $cli, '<' ) && false !== strpos( $cli, "Necesita tu atención\nRevisa Portada." ) && false !== strpos( $cli, 'Respaldo · backup-1.zip' ), 'WP-CLI da el mismo resumen en texto: ' . $cli );
		unset( $GLOBALS['t']['content_attention'] );
		check( ! get_option( Full::JOB ) && empty( $GLOBALS['t']['cron'][ Full::CRON ] ), 'La tarea se cierra y se quita su continuación.' );
		check( in_array( 'activation_hook:1', $GLOBALS['t']['log'], true ) && false === get_transient( 'elementor_activation_redirect' ), 'Migrar no deja la redirección de activación de Elements: la próxima pantalla no salta a su bienvenida.' );
		break;

	case 'backup_history':
		$GLOBALS['t']['job_vanishes'] = true;
		drive();
		$last = Full::last( false );
		check( '' === $last['failed'] && ! empty( $last['backup']['ok'] ) && 'backup-1.zip' === $last['backup']['file'], 'Un respaldo terminado que ya no está en el registro de trabajos se toma del historial de Backups: ' . $last['error'] );
		break;

	case 'backup_fails':
		$GLOBALS['t']['backup_fails'] = true;
		drive();
		$last = Full::last( false );
		check( 'backup' === $last['failed'] && false !== strpos( $last['error'], 'sin espacio' ), 'Se detiene en el respaldo con su motivo.' );
		check( array( 'install:digitalisimo-backups' ) === logged( 'install:' ), 'Sólo se instaló Backups.' );
		check( ! logged( 'deactivate:' ) && active( 'elementor/elementor.php' ) && active( 'seo-by-rank-math/rank-math.php' ), 'No se desactiva nada.' );
		check( 'hello-elementor' === get_option( 'stylesheet' ), 'No cambia el tema.' );
		nothing_deleted();
		check( false !== strpos( Full::render( false, $last ), 'No se borró nada' ), 'El informe dice que no se borró nada.' );
		check( false !== strpos( Full::render( false, $last ), '<li>Resuelve lo que dice el aviso de arriba y vuelve a pulsar Migrar' ) && false === strpos( Full::render( false, $last ), 'Nada necesita' ), 'Una migración detenida pide atención: nunca dice que no hay nada.' );
		break;

	case 'install_fails':
		$GLOBALS['t']['install_fails'] = array( 'digitalisimo-seo' );
		drive();
		$last = Full::last( false );
		check( 'install' === $last['failed'] && false !== strpos( $last['error'], 'DIGITALÍSIMO SEO' ), 'Se detiene en la instalación de SEO: ' . $last['error'] );
		check( ! active( ELEMENTS ) && active( 'elementor/elementor.php' ), 'No se activa nada a medias ni se migra el contenido.' );
		nothing_deleted();
		break;

	case 'import_fails':
		$GLOBALS['t']['import_fails'] = true;
		drive();
		$last = Full::last( false );
		check( 'import' === $last['failed'], 'Se detiene en la importación.' );
		check( active( 'seo-by-rank-math/rank-math.php' ), 'Rank Math sigue activo.' );
		check( 'hello-elementor' === get_option( 'stylesheet' ), 'No cambia el tema.' );
		nothing_deleted();
		break;

	case 'theme_child':
		// Hijo con PHP que no se traslada (wp_head y wp_footer, como muchos reales): se queda y el informe dice qué es.
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		$GLOBALS['t']['child_sheet'] = 'hello-hijo';
		drive();
		$last = Full::last( false );
		check( '' === $last['failed'], 'Termina: ' . $last['error'] );
		check( 'hello-hijo' === get_option( 'stylesheet' ) && ! logged( 'switch_theme' ), 'Un hijo de Hello con PHP que no se traslada no se cambia.' );
		check( false !== strpos( $last['theme'][1]['reason'], 'tema hijo' ) && false !== strpos( $last['theme'][1]['reason'], "functions.php, línea 10: add_action( 'wp_head' )" ) && false !== strpos( $last['theme'][1]['reason'], "línea 13: add_action( 'wp_footer' )" ) && false !== strpos( $last['theme'][1]['reason'], 'activa DIGITALÍSIMO Tools en este sitio' ), 'El informe dice qué lo impide, en qué línea y el orden para hacerlo: ' . $last['theme'][1]['reason'] );
		check( isset( $GLOBALS['t']['themes']['digitalisimo-theme'] ) && in_array( 'install:digitalisimo-theme', logged( 'install:' ), true ), 'El tema propio se instala para leer el hijo con su propio lector.' );
		check( isset( $GLOBALS['t']['themes']['hello-elementor'] ), 'Hello no se borra.' );
		check( ! installed_now( 'seo-by-rank-math/rank-math.php' ), 'Lo demás sí se reemplaza.' );
		$html = Full::render( false, $last );
		check( false !== strpos( $html, 'El tema DIGITALÍSIMO quedó instalado sin activar' ), 'El informe dice que el tema propio quedó instalado sin activar.' );
		check( false !== strpos( (string) strstr( $html, 'digi-migrate__attention-title' ), 'Tema: El sitio usa un tema hijo de Hello' ) && false !== strpos( $html, 'se queda Hello Hijo (ver «Necesita tu atención»).' ) && false !== strpos( $html, 'notice-warning' ) && false === strpos( $html, 'Nada necesita' ), 'El tema hijo que se queda, con su acción, va en «Necesita tu atención».' );
		break;

	case 'theme_child_tools':
		// Con Tools activo, Migrar deja el functions.php del hijo en Código PHP, inactivo, y lo dice.
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		$GLOBALS['t']['child_sheet']                                       = 'hello-hijo';
		$GLOBALS['t']['filters']['digitalisimo_tools_php_import'][]        = static function ( $result, $items ) {
			$GLOBALS['t']['imports'][] = $items;
			return array( 'digitalisimo-theme:hello-hijo' => 'created' );
		};
		// Tools activo en este sitio (en una red, el filtro puede estar cargado aunque el sitio no lo use: ver abajo).
		update_option( 'active_plugins', array_merge( (array) get_option( 'active_plugins', array() ), array( Full::TOOLS_FILE ) ) );
		drive();
		$last = Full::last( false );
		check( 'hello-hijo' === get_option( 'stylesheet' ) && ! logged( 'switch_theme' ), 'Sigue sin cambiar: el PHP importado está inactivo.' );
		check( 1 === count( (array) ( $GLOBALS['t']['imports'] ?? array() ) ) && 'hello-hijo' === $GLOBALS['t']['imports'][0][0]['origin']['id'] && false !== strpos( $GLOBALS['t']['imports'][0][0]['code'], "if ( function_exists( 'digitalisimo_theme_parity' ) ) {\n" . substr( PHP_CHILD, 6 ) ), 'Su functions.php va a Tools una vez, tal cual, dentro de la condición del tema propio.' );
		check( false !== strpos( $last['theme'][1]['reason'], 'revisa «PHP del tema hijo Hello Hijo» y actívalo (Migrar lo dejó ahí, inactivo; va dentro de una condición' ), 'Y el informe dice dónde quedó y que puede activarse ya: ' . $last['theme'][1]['reason'] );
		// La persona lo activa (con el hijo todavía activo) y vuelve a pulsar Migrar: el tema cambia y el PHP sigue.
		$wrapped = $GLOBALS['t']['imports'][0][0]['code'];
		$GLOBALS['t']['filters']['digitalisimo_tools_php_sources'][] = static function ( $rows ) use ( $wrapped ) {
			return array_merge( $rows, array( array( 'scope' => 'site', 'id' => 'a1', 'active' => true, 'hash' => hash( 'sha256', $wrapped ) ) ) );
		};
		$GLOBALS['t']['carry'] = true;
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
		$state = request( true, 'clic2' );
		for ( $i = 0; $i < 80 && empty( $state['done'] ); $i++ ) {
			$state = request();
		}
		$last = Full::last( false );
		check( 'digitalisimo-theme' === get_option( 'stylesheet' ) && ! empty( $last['theme'][1]['switched'] ) && ! empty( $last['theme'][1]['child_facts']['covered'] ) && 1 === count( $GLOBALS['t']['imports'] ), 'Con el fragmento activo, la siguiente pasada cambia el tema sin volver a importar: ' . json_encode( $last['theme'][1] ) );
		break;

	case 'theme_child_covered':
		// Tools no activo en el sitio aunque su filtro esté cargado (una red con Tools sólo en el principal): no se importa
		// nada y el informe pide activarlo; y aunque hubiera una fila activa con su código, el hijo no cambia solo.
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		$GLOBALS['t']['child_sheet']                                  = 'hello-hijo';
		$GLOBALS['t']['filters']['digitalisimo_tools_php_import'][]   = static function ( $result, $items ) {
			$GLOBALS['t']['imports'][] = $items;
			return array( 'digitalisimo-theme:hello-hijo' => 'created' );
		};
		$GLOBALS['t']['filters']['digitalisimo_tools_php_sources'][] = static function ( $rows ) {
			return array_merge( $rows, array( array( 'scope' => 'site', 'id' => 'a1', 'active' => true, 'hash' => hash( 'sha256', PHP_CHILD ) ) ) );
		};
		drive();
		$last = Full::last( false );
		check( 'hello-hijo' === get_option( 'stylesheet' ) && empty( $last['theme'][1]['switched'] ) && array() === (array) ( $GLOBALS['t']['imports'] ?? array() ) && false !== strpos( $last['theme'][1]['reason'], 'activa DIGITALÍSIMO Tools en este sitio y vuelve a pulsar Migrar' ), 'Sin Tools activo en el sitio no se importa (ni cuenta como cubierto) y el hijo se queda: ' . json_encode( $last['theme'][1] ) );
		break;

	case 'theme_child_ok':
		// Hijo típico (plantilla oficial): pasa al tema propio, la portada se pinta con su hoja y Hello no se borra.
		child_theme( 'hello-hijo-ok', 'Hello Hijo OK', OFFICIAL_CHILD );
		child_not_copied( 'hello-hijo-ok' ); // Sin uploads, la copia del tema propio queda incompleta.
		$GLOBALS['t']['child_sheet'] = 'hello-hijo-ok';
		$GLOBALS['t']['carry']       = true;
		$state                       = drive();
		$last                        = Full::last( false );
		check( '' === $last['failed'] && 'digitalisimo-theme' === get_option( 'stylesheet' ) && ! empty( $last['theme'][1]['switched'] ), 'El hijo típico pasa al tema propio: ' . json_encode( $last['theme'][1] ) );
		check( array( 'blockers' => '', 'files' => '', 'risks' => '', 'covered' => false, 'carried' => 1, 'tools' => false, 'filter' => false, 'wrap' => true, 'imported' => '', 'activate' => '', 'hello_paths' => false ) === $last['theme'][1]['child_facts'], 'Se lee: una hoja que se trae, sin bloqueos: ' . json_encode( $last['theme'][1]['child_facts'] ) );
		check( ! empty( $last['verified'] ) && 2 === count( $last['verify'] ), 'La portada y la página responden y se pintan igual.' );
		check( isset( $GLOBALS['t']['themes']['hello-elementor'], $GLOBALS['t']['themes']['hello-hijo-ok'] ) && false !== strpos( (string) ( $last['kept']['hello-elementor'] ?? '' ), 'depende de Hello' ), 'Hello y el hijo se quedan (el tema propio carga la hoja del hijo desde su carpeta): ' . json_encode( $last['kept'] ) );
		break;

	case 'theme_child_files':
		// Plantilla del hijo + PHP en functions.php ya activo en Tools: el fragmento cubre functions.php, no la plantilla.
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		file_put_contents( TMP . '/themes/hello-hijo/header.php', "<?php\necho '<header>Hijo</header>';\n" );
		tools_here();
		$GLOBALS['t']['filters']['digitalisimo_tools_php_sources'][] = static function ( $rows ) {
			$wrap = function_exists( 'digitalisimo_theme_child_wrap_php' ) ? digitalisimo_theme_child_wrap_php( PHP_CHILD, 'Hello Hijo' ) : '';
			return array_merge( $rows, array( array( 'scope' => 'site', 'id' => 'a1', 'active' => true, 'hash' => hash( 'sha256', $wrap ) ) ) );
		};
		drive();
		$last = Full::last( false );
		check( 'hello-hijo' === get_option( 'stylesheet' ) && ! logged( 'switch_theme' ) && false !== strpos( (string) $last['theme'][1]['reason'], 'header.php: plantilla que sustituye la del tema' ) && array() === (array) ( $GLOBALS['t']['imports'] ?? array() ), 'Con el fragmento activo, la plantilla del hijo lo deja y lo dice: ' . json_encode( $last['theme'][1] ) );
		break;

	case 'theme_child_files_only':
		// Sólo archivos del hijo (functions.php típico): su motivo, sin importar nada ni mandar al camino a mano.
		child_theme( 'hello-hijo-ok', 'Hello Hijo OK', OFFICIAL_CHILD );
		file_put_contents( TMP . '/themes/hello-hijo-ok/theme.json', '{"version":2}' );
		tools_here();
		drive();
		$last = Full::last( false );
		$why  = (string) $last['theme'][1]['reason'];
		check( 'hello-hijo-ok' === get_option( 'stylesheet' ) && false !== strpos( $why, 'theme.json' ) && false !== strpos( $why, 'plantillas o archivos' ) && false === strpos( $why, 'no admite la condición' ) && array() === (array) ( $GLOBALS['t']['imports'] ?? array() ), 'Sólo archivos del hijo: se queda con su propio motivo: ' . $why );
		break;

	case 'theme_child_risky':
		// Su PHP usa una constante de Hello que el tema no declara (tema 1.0.2: sí declara HELLO_ELEMENTOR_VERSION): dentro de
		// Tools, con el tema propio, sería un error fatal. No se importa.
		child_theme( 'hello-hijo', 'Hello Hijo', OFFICIAL_CHILD . "add_action( 'wp_head', function () {\n\techo '<meta name=\"v\" content=\"' . HELLO_PLUS_VERSION . '\">';\n} );\n" );
		tools_here();
		drive();
		$last = Full::last( false );
		$why  = (string) $last['theme'][1]['reason'];
		check( 'hello-hijo' === get_option( 'stylesheet' ) && false !== strpos( $why, 'línea 11: usa la constante HELLO_PLUS_VERSION de Hello' ) && array() === (array) ( $GLOBALS['t']['imports'] ?? array() ), 'Con un riesgo se queda, sin importar, y dice cuál: ' . $why );
		break;

	case 'theme_child_network':
		// Tools activo en el sitio, pero su importación no está cargada en la petición de Migrar (otro sitio de la red).
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		update_option( 'active_plugins', array_merge( (array) get_option( 'active_plugins', array() ), array( Full::TOOLS_FILE ) ) );
		drive();
		$why = (string) Full::last( false )['theme'][1]['reason'];
		check( 'hello-hijo' === get_option( 'stylesheet' ) && false !== strpos( $why, 'Migrar corre desde otro sitio de la red' ) && false === strpos( $why, 'no admite la condición' ), 'Lo dice así, no como si no admitiera la condición: ' . $why );
		break;

	case 'theme_child_skipped':
		// El fragmento que Migrar dejó se borró en Tools (la importación contesta skipped).
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		tools_here( 'skipped' );
		drive();
		$why = (string) Full::last( false )['theme'][1]['reason'];
		check( 'hello-hijo' === get_option( 'stylesheet' ) && false !== strpos( $why, 'se borró' ) && false === strpos( $why, 'no admite la condición' ), 'Dice que el fragmento se borró: ' . $why );
		break;

	case 'theme_child_auto':
		// Pedido del propietario del 2026-11-20 («quedamos que migrar debe hacer todo»): un hijo con PHP de wp_head y
		// wp_footer y el tema propio 1.0.0 instalado. Un solo Migrar actualiza el tema, deja el functions.php del hijo en
		// Tools, lo activa, cambia de tema y verifica.
		$GLOBALS['t']['themes']['digitalisimo-theme'] = array( 'Name' => 'DIGITALÍSIMO', 'UpdateURI' => Replacements::THEME_URI, 'Version' => '1.0.0' );
		old_theme_dir();
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		$GLOBALS['t']['child_sheet'] = 'hello-hijo';
		$GLOBALS['t']['carry']       = true;
		tools_auto();
		drive();
		$last = Full::last( false );
		$wrap = digitalisimo_theme_child_wrap_php( PHP_CHILD, 'Hello Hijo' );
		check( 'digitalisimo-theme' === get_option( 'stylesheet' ) && ! empty( $last['theme'][1]['switched'] ) && ! empty( $last['verified'] ) && '' === $last['failed'], 'Un solo Migrar termina con el tema propio y verificado: ' . json_encode( $last['theme'][1], JSON_UNESCAPED_UNICODE ) );
		check( ! empty( $GLOBALS['t']['snippet_on'] ) && hash( 'sha256', $wrap ) === ( $last['theme'][1]['snippet'] ?? '' ) && in_array( 'snippet_activate:digitalisimo-theme:hello-hijo', $GLOBALS['t']['log'], true ), 'El fragmento del hijo queda activo y Migrar recuerda cuál activó.' );
		check( position( 'snippet_activate:digitalisimo-theme:hello-hijo' ) < position( 'switch_theme:digitalisimo-theme' ), 'Se activa antes del cambio de tema.' );
		check( hash( 'sha256', $wrap ) === ( $GLOBALS['t']['snippet_hash'] ?? '' ), 'Migrar pasa a Tools el hash del functions.php del hijo leído del disco (Tools sólo activa ese código).' );
		check( ! logged( 'snippet_deactivate' ), 'Con todo bien, no se desactiva.' );
		$html = Full::render( false, $last );
		check( false !== strpos( $html, 'Migrar activó «PHP del tema hijo Hello Hijo»' ) && false !== strpos( $html, 'pulsa «Desactivar» en esa fila' ), 'El informe dice que lo activó y cómo desactivarlo.' );
		break;

	case 'theme_child_retire':
		// Con las hojas del hijo ya copiadas por el tema propio, al final se borran el hijo y Hello (están en el respaldo).
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		$GLOBALS['t']['child_sheet']   = 'hello-hijo';
		$GLOBALS['t']['carry']         = true;
		tools_auto();
		drive();
		$last = Full::last( false );
		check( 'digitalisimo-theme' === get_option( 'stylesheet' ) && ! empty( $last['verified'] ), 'Cambia y verifica.' );
		check( ! isset( $GLOBALS['t']['themes']['hello-hijo'], $GLOBALS['t']['themes']['hello-elementor'] ) && position( 'delete_theme:hello-hijo' ) < position( 'delete_theme:hello-elementor' ), 'El hijo y después Hello se borran: ' . json_encode( $last['kept'], JSON_UNESCAPED_UNICODE ) );
		check( false !== strpos( Full::render( false, $last ), 'Tema hijo «Hello Hijo»' ), 'El informe dice que se borró (y cómo volver: el respaldo).' );
		check( logged( 'refs:post_content:hello-hijo' ) && logged( 'refs:meta_value:hello-hijo' ) && logged( 'refs:option_value:hello-hijo' ) && ! is_dir( TMP . '/themes/hello-hijo' . Full::RETIRING ) && empty( $last['deleting_theme'] ), 'Antes busca enlaces a su carpeta en el contenido, los datos y las opciones, y no deja nada apartado.' );
		break;

	case 'theme_child_retire_kept':
		// El tema propio todavía carga las hojas desde la carpeta del hijo: el hijo y Hello se quedan, con el motivo.
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		$GLOBALS['t']['child_sheet']   = 'hello-hijo';
		$GLOBALS['t']['carry']         = true;
		child_not_copied( 'hello-hijo' );
		tools_auto();
		drive();
		$last = Full::last( false );
		check( 'digitalisimo-theme' === get_option( 'stylesheet' ) && ! empty( $last['verified'] ), 'Cambia y verifica.' );
		check( isset( $GLOBALS['t']['themes']['hello-hijo'], $GLOBALS['t']['themes']['hello-elementor'] ) && ! logged( 'delete_theme' ) && false !== strpos( (string) ( $last['kept']['child:hello-hijo'] ?? '' ), 'carga sus hojas' ), 'No se borra ningún tema: ' . json_encode( $last['kept'], JSON_UNESCAPED_UNICODE ) );
		break;

	case 'theme_child_hello_paths':
		// El PHP del hijo usa HELLO_THEME_PATH dentro de una función (cubierta por el tema propio): el hijo se retira, pero
		// Hello se queda, con el motivo.
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD . "add_action( 'wp_footer', function () {\n\techo esc_html( HELLO_THEME_PATH );\n} );\n" );
		$GLOBALS['t']['child_sheet'] = 'hello-hijo';
		$GLOBALS['t']['carry']       = true;
		tools_auto();
		$GLOBALS['t']['filters']['digitalisimo_tools_php_sources'] = array( static function ( $rows ) {
			if ( empty( $GLOBALS['t']['snippet_on'] ) ) {
				return $rows;
			}
			$code = (string) file_get_contents( TMP . '/themes/hello-hijo/functions.php' );
			return array_merge( $rows, array( array( 'scope' => 'site', 'id' => 'a1', 'active' => true, 'hash' => hash( 'sha256', digitalisimo_theme_child_wrap_php( $code, 'Hello Hijo' ) ) ) ) );
		} );
		drive();
		$last = Full::last( false );
		check( 'digitalisimo-theme' === get_option( 'stylesheet' ) && ! empty( $last['theme'][1]['child_facts']['hello_paths'] ), 'Cambia y anota que usa rutas de Hello: ' . json_encode( $last['theme'][1], JSON_UNESCAPED_UNICODE ) );
		check( isset( $GLOBALS['t']['themes']['hello-elementor'] ) && false !== strpos( (string) ( $last['kept']['hello-elementor'] ?? '' ), 'constantes de ruta de Hello' ), 'Hello se queda, con el motivo: ' . json_encode( $last['kept'], JSON_UNESCAPED_UNICODE ) );
		break;

	case 'theme_child_retire_refs':
		// Una imagen del hijo enlazada por su ruta en los datos de Elementor: el hijo y Hello se quedan, con el motivo.
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		$GLOBALS['t']['child_sheet']   = 'hello-hijo';
		$GLOBALS['t']['carry']         = true;
		$GLOBALS['t']['refs']          = array( 'meta_value' => '12' );
		tools_auto();
		drive();
		$last = Full::last( false );
		check( 'digitalisimo-theme' === get_option( 'stylesheet' ) && isset( $GLOBALS['t']['themes']['hello-hijo'], $GLOBALS['t']['themes']['hello-elementor'] ) && ! logged( 'delete_theme' ), 'Con enlaces a su carpeta no se borra ningún tema.' );
		check( false !== strpos( (string) ( $last['kept']['child:hello-hijo'] ?? '' ), 'Todavía se enlazan archivos de su carpeta (wp-content/themes/hello-hijo/) en los datos de «Inicio» (ID 12' ) && false !== strpos( Full::render( false, $last ), 'Todavía se enlazan archivos' ), 'Y el informe dice dónde: ' . json_encode( $last['kept'], JSON_UNESCAPED_UNICODE ) );
		break;

	case 'theme_child_retire_broken':
		// Sin la carpeta del hijo la portada se rompe: vuelve a su sitio y no se borra.
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		$GLOBALS['t']['child_sheet']     = 'hello-hijo';
		$GLOBALS['t']['carry']           = true;
		$GLOBALS['t']['assets_copied']   = true;
		$GLOBALS['t']['needs_child_dir'] = 'hello-hijo';
		tools_auto();
		drive();
		$last = Full::last( false );
		check( is_dir( TMP . '/themes/hello-hijo' ) && ! is_dir( TMP . '/themes/hello-hijo' . Full::RETIRING ) && isset( $GLOBALS['t']['themes']['hello-hijo'] ) && ! logged( 'delete_theme' ), 'La carpeta vuelve a su sitio y no se borra nada.' );
		check( 0 === strpos( (string) ( $last['kept']['child:hello-hijo'] ?? '' ), 'Sin su carpeta, ' ), 'El motivo lo dice: ' . json_encode( $last['kept'], JSON_UNESCAPED_UNICODE ) );
		break;

	case 'theme_child_retire_resume':
		// La petición murió con la carpeta del hijo apartada: la siguiente pasada la devuelve y lo dice.
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		rename( TMP . '/themes/hello-hijo', TMP . '/themes/hello-hijo' . Full::RETIRING );
		$m = new ReflectionMethod( Full::class, 'cleanup_theme' );
		$m->setAccessible( true );
		$out = $m->invoke( null, array( 'deleting_theme' => 'hello-hijo', 'theme' => array(), 'kept' => array(), 'deleted' => array(), 'caps' => array() ) );
		check( is_dir( TMP . '/themes/hello-hijo' ) && empty( $out['deleting_theme'] ) && false !== strpos( (string) ( $out['kept']['child:hello-hijo'] ?? '' ), 'se interrumpió' ), 'La carpeta vuelve y el informe lo dice: ' . json_encode( $out['kept'] ?? array(), JSON_UNESCAPED_UNICODE ) );
		break;

	case 'theme_update_resume':
		// Una actualización del tema se cortó a mitad (quedó la copia propia y una carpeta incompleta): la carpeta vuelve a la
		// copia y se actualiza de nuevo.
		$GLOBALS['t']['themes']['digitalisimo-theme'] = array( 'Name' => 'DIGITALÍSIMO', 'UpdateURI' => Replacements::THEME_URI, 'Version' => '1.0.0' );
		old_theme_dir();
		@mkdir( Packages::copy_dir(), 0777, true );
		file_put_contents( Packages::copy_dir() . '/style.css', "/*\nTheme Name: DIGITALÍSIMO\nVersion: 1.0.0 copia\n*/\n" );
		unlink( TMP . '/themes/digitalisimo-theme/style.css' ); // A medio copiar.
		child_theme( 'hello-hijo-ok', 'Hello Hijo OK', OFFICIAL_CHILD );
		$GLOBALS['t']['child_sheet'] = 'hello-hijo-ok';
		$GLOBALS['t']['carry']       = true;
		drive();
		$last = Full::last( false );
		check( false !== strpos( (string) file_get_contents( TMP . '/themes/digitalisimo-theme/style.css' ), '1.0.0 copia' ) && ! is_dir( Packages::copy_dir() ), 'La carpeta vuelve a ser la copia y la copia se borra.' );
		check( false !== strpos( implode( ' ', (array) $last['notes'] ), 'se cortó a mitad' ) && '1.0.1' === $GLOBALS['t']['themes']['digitalisimo-theme']['Version'] && 'digitalisimo-theme' === get_option( 'stylesheet' ), 'Lo dice, actualiza de nuevo y cambia: ' . json_encode( $last['notes'], JSON_UNESCAPED_UNICODE ) );
		break;

	case 'theme_update_own_site':
		// En una red, el sitio 1 ya usaba el tema propio (1.0.0) y el 2 cambia desde Hello: tras actualizar, la portada del
		// sitio 1 se rompe. Se detecta, no se borra nada y el informe lo dice.
		make_network();
		$GLOBALS['t']['themes']['digitalisimo-theme'] = array( 'Name' => 'DIGITALÍSIMO', 'UpdateURI' => Replacements::THEME_URI, 'Version' => '1.0.0' );
		old_theme_dir();
		$GLOBALS['t']['options'][1]['stylesheet']     = 'digitalisimo-theme';
		$GLOBALS['t']['options'][1]['template']       = 'digitalisimo-theme';
		$GLOBALS['t']['own_breaks_after_update']      = true;
		drive( 120 );
		$last = Full::last( true );
		check( '1.0.1' === $GLOBALS['t']['themes']['digitalisimo-theme']['Version'] && empty( $last['verified'] ), 'Se actualiza y la red no queda verificada.' );
		check( false !== strpos( json_encode( $last['verify'], JSON_UNESCAPED_UNICODE ), 'ya usaba el tema DIGITALÍSIMO y, después de actualizarlo' ) && isset( $GLOBALS['t']['themes']['hello-elementor'] ), 'Lo dice y no borra nada: ' . json_encode( $last['verify'], JSON_UNESCAPED_UNICODE ) );
		break;

	case 'theme_rest_broken':
		// La portada responde, pero la API REST da un 500 con el tema propio: vuelve atrás.
		$GLOBALS['t']['rest_broken'] = true;
		drive();
		$last = Full::last( false );
		check( 'hello-elementor' === get_option( 'stylesheet' ) && empty( $last['verified'] ) && false !== strpos( json_encode( $last['verify'], JSON_UNESCAPED_UNICODE ), 'la API REST' ), 'Con la API REST rota, vuelve a Hello: ' . json_encode( $last['verify'], JSON_UNESCAPED_UNICODE ) );
		break;

	case 'theme_child_auto_revert':
		// El guardián de Tools pausa el fragmento en la primera petición con el tema propio: vuelve al hijo y Migrar
		// desactiva el fragmento que activó (sólo ese código).
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		$GLOBALS['t']['child_sheet']     = 'hello-hijo';
		$GLOBALS['t']['carry']           = true;
		$GLOBALS['t']['pause_on_switch'] = true;
		tools_auto();
		drive();
		$last = Full::last( false );
		$wrap = digitalisimo_theme_child_wrap_php( PHP_CHILD, 'Hello Hijo' );
		check( 'hello-hijo' === get_option( 'stylesheet' ) && ! empty( $last['theme'][1]['reverted'] ) && empty( $last['verified'] ), 'Con el fragmento pausado, vuelve al hijo: ' . json_encode( $last['theme'][1], JSON_UNESCAPED_UNICODE ) );
		check( in_array( 'snippet_deactivate:digitalisimo-theme:hello-hijo:' . hash( 'sha256', $wrap ), $GLOBALS['t']['log'], true ) && empty( $GLOBALS['t']['snippet_on'] ) && 'off' === ( $last['theme'][1]['snippet_off'] ?? '' ), 'Y desactiva el fragmento que había activado.' );
		check( false !== strpos( Full::render( false, $last ), 'quedó otra vez desactivado y sin revisar' ), 'El informe lo dice.' );
		nothing_deleted();
		break;

	case 'theme_child_auto_refused':
		// Tools no lo activa (se desactivó antes, a mano o en una vuelta atrás): el hijo se queda y el motivo lo explica.
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		$GLOBALS['t']['child_sheet']    = 'hello-hijo';
		$GLOBALS['t']['snippet_refuse'] = 'changed';
		tools_auto();
		drive();
		$last = Full::last( false );
		check( 'hello-hijo' === get_option( 'stylesheet' ) && ! logged( 'switch_theme' ) && false !== strpos( (string) $last['theme'][1]['reason'], 'Migrar no lo activó solo porque ya no está como lo dejó' ), 'Sin activar, el hijo se queda con su motivo: ' . $last['theme'][1]['reason'] );
		break;

	case 'theme_child_auto_network':
		// En una red, «Migrar toda la red» activa el fragmento en el sitio 2 (el del hijo, con Tools activo) y cambia los
		// dos sitios; la regla de habilitar se respeta (sólo el tema propio se habilita en la red).
		make_network();
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		$GLOBALS['t']['options'][2]['stylesheet'] = 'hello-hijo';
		$GLOBALS['t']['options'][1]['stylesheet'] = 'hello-elementor';
		$GLOBALS['t']['child_sheet']              = 'hello-hijo';
		$GLOBALS['t']['carry']                    = true;
		tools_auto();
		$GLOBALS['t']['options'][2]['active_plugins'][] = Full::TOOLS_FILE;
		$GLOBALS['t']['options'][1]['active_plugins']   = array_values( array_diff( (array) $GLOBALS['t']['options'][1]['active_plugins'], array( Full::TOOLS_FILE ) ) );
		drive( 120 );
		$last = Full::last( true );
		check( $last && '' === $last['failed'] && ! empty( $last['verified'] ), 'La red termina verificada: ' . json_encode( $last['theme'] ?? array(), JSON_UNESCAPED_UNICODE ) );
		check( 'digitalisimo-theme' === get_blog_option( 1, 'stylesheet' ) && 'digitalisimo-theme' === get_blog_option( 2, 'stylesheet' ) && ! empty( $last['theme'][2]['snippet'] ) && empty( $last['theme'][1]['snippet'] ), 'Los dos sitios cambian y el fragmento se activa sólo en el del hijo.' );
		check( array( 'network_enable_theme:digitalisimo-theme' ) === logged( 'network_enable_theme' ), 'Sólo el tema propio se habilita en la red.' );
		break;

	case 'theme_child_paused':
		// El fragmento estaba activo; en la primera petición con el tema propio el guardián de Tools lo pausa (un error):
		// las demás páginas responden igual, pero el PHP del hijo ya no corre. Vuelve al hijo.
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		$GLOBALS['t']['child_sheet'] = 'hello-hijo';
		$GLOBALS['t']['carry']       = true;
		tools_here();
		snippet_active();
		$GLOBALS['t']['pause_on_switch'] = true;
		drive();
		$last = Full::last( false );
		check( logged( 'switch_theme' ) && 'hello-hijo' === get_option( 'stylesheet' ) && ! empty( $last['theme'][1]['reverted'] ) && empty( $last['verified'] ), 'Con el fragmento pausado tras el cambio, vuelve al hijo: ' . json_encode( $last['theme'][1] ) );
		check( false !== strpos( json_encode( $last['verify'], JSON_UNESCAPED_UNICODE ), 'ya no está activo en DIGITALÍSIMO Tools' ), 'Y dice por qué: ' . json_encode( $last['verify'], JSON_UNESCAPED_UNICODE ) );
		nothing_deleted();
		break;

	case 'theme_child_recheck':
		// El fragmento estaba activo al planear, pero se pausa mientras se toman las huellas: justo antes del cambio se
		// vuelve a mirar y el tema no cambia.
		child_theme( 'hello-hijo', 'Hello Hijo', PHP_CHILD );
		$GLOBALS['t']['child_sheet'] = 'hello-hijo';
		tools_here();
		snippet_active();
		$GLOBALS['t']['pause_in_theme_stage'] = true;
		drive();
		$last = Full::last( false );
		check( ! logged( 'switch_theme' ) && 'hello-hijo' === get_option( 'stylesheet' ) && empty( $last['theme'][1]['switched'] ) && empty( $last['theme'][1]['child_facts']['covered'] ), 'Pausado antes del cambio: no cambia: ' . json_encode( $last['theme'][1] ) );
		break;

	case 'theme_warm_fatal':
		// La primera petición con el tema propio da un error fatal (500); las siguientes responden: vuelve a Hello.
		$GLOBALS['t']['warm_break'] = true;
		drive();
		$last = Full::last( false );
		check( 'hello-elementor' === get_option( 'stylesheet' ) && ! empty( $last['theme'][1]['reverted'] ) && empty( $last['verified'] ) && false !== strpos( json_encode( $last['verify'], JSON_UNESCAPED_UNICODE ), 'La primera petición después de cambiar el tema respondió HTTP 500.' ), 'Un 500 en la primera petición cuenta como falla: ' . json_encode( $last['verify'], JSON_UNESCAPED_UNICODE ) );
		nothing_deleted();
		break;

	case 'theme_warm_fatal_before':
		// La portada ya hablaba de un «Fatal error» antes del cambio (una entrada del blog): no es un error del tema.
		$GLOBALS['t']['fatal_text'] = true;
		drive();
		$last = Full::last( false );
		check( 'digitalisimo-theme' === get_option( 'stylesheet' ) && ! empty( $last['theme'][1]['switched'] ) && ! empty( $last['verified'] ), 'El texto que ya estaba antes no cuenta como falla: ' . json_encode( $last['verify'], JSON_UNESCAPED_UNICODE ) );
		break;

	case 'theme_child_old':
		// Caso real del 2026-11-20: el tema propio 1.0.0 ya instalado (sin lector del hijo) y un hijo de Hello. Migrar lo
		// actualiza en la misma pasada, desde la Release, sin activarlo, y después cambia de tema.
		$GLOBALS['t']['themes']['digitalisimo-theme'] = array( 'Name' => 'DIGITALÍSIMO', 'UpdateURI' => Replacements::THEME_URI, 'Version' => '1.0.0' );
		old_theme_dir();
		child_theme( 'hello-hijo-ok', 'Hello Hijo OK', OFFICIAL_CHILD );
		$GLOBALS['t']['child_sheet'] = 'hello-hijo-ok';
		$GLOBALS['t']['carry']       = true;
		drive();
		$last = Full::last( false );
		check( in_array( 'install:digitalisimo-theme', logged( 'install:' ), true ) && '1.0.1' === $GLOBALS['t']['themes']['digitalisimo-theme']['Version'], 'El tema propio 1.0.0 se actualiza a 1.0.1: ' . json_encode( $GLOBALS['t']['themes']['digitalisimo-theme'] ) );
		check( 'digitalisimo-theme' === get_option( 'stylesheet' ) && ! empty( $last['theme'][1]['switched'] ) && ! empty( $last['verified'] ), 'Y el hijo se cambia por el tema propio en la misma pasada: ' . ( $last['theme'][1]['reason'] ?? '' ) );
		check( array( 'from' => '1.0.0', 'to' => '1.0.1' ) === ( $last['theme_updated_now'] ?? null ) && false !== strpos( Full::render( false, $last ), 'se actualizó de 1.0.0 a 1.0.1' ), 'El informe dice que se actualizó.' );
		check( 1 === count( array_filter( $GLOBALS['t']['log'], static function ( $l ) { return 0 === strpos( $l, 'switch_theme:' ); } ) ), 'Sólo un cambio de tema (el de Migrar).' );
		break;

	case 'theme_child_old_fails':
		// La actualización del tema propio falla: el hijo se queda, con el motivo y la acción, y el tema no cambia.
		$GLOBALS['t']['themes']['digitalisimo-theme'] = array( 'Name' => 'DIGITALÍSIMO', 'UpdateURI' => Replacements::THEME_URI, 'Version' => '1.0.0' );
		$GLOBALS['t']['install_fails']                = array( 'theme-update' );
		old_theme_dir();
		child_theme( 'hello-hijo-ok', 'Hello Hijo OK', OFFICIAL_CHILD );
		drive();
		$last   = Full::last( false );
		$reason = (string) ( $last['theme'][1]['reason'] ?? '' );
		check( 'hello-hijo-ok' === get_option( 'stylesheet' ) && ! logged( 'switch_theme' ) && '1.0.0' === $GLOBALS['t']['themes']['digitalisimo-theme']['Version'], 'Sin actualizar, no se cambia el tema y la copia sigue en 1.0.0.' );
		check( false !== strpos( $reason, 'No se pudo actualizar el tema DIGITALÍSIMO instalado (1.0.0)' ) && false !== strpos( $reason, 'la descarga del paquete falló' ) && false !== strpos( $reason, 'Qué hacer: actualiza el tema DIGITALÍSIMO' ), 'El motivo dice qué falló y qué hacer: ' . $reason );
		check( false !== strpos( Full::render( false, $last ), 'No se pudo actualizar el tema DIGITALÍSIMO' ) && empty( $last['theme_updated_now'] ), 'Y llega al informe.' );
		check( false !== strpos( (string) file_get_contents( TMP . '/themes/digitalisimo-theme/style.css' ), 'Version: 1.0.0' ) && ! is_dir( Packages::copy_dir() ), 'La carpeta queda como estaba y la copia propia se borra.' );
		break;

	case 'theme_old_current':
		// Una copia que ya está al día no se vuelve a instalar.
		$GLOBALS['t']['themes']['digitalisimo-theme'] = array( 'Name' => 'DIGITALÍSIMO', 'UpdateURI' => Replacements::THEME_URI, 'Version' => '1.0.1' );
		@mkdir( TMP . '/themes/digitalisimo-theme/inc', 0777, true );
		copy( dirname( __DIR__ ) . '/digitalisimo-theme/inc/child.php', TMP . '/themes/digitalisimo-theme/inc/child.php' );
		child_theme( 'hello-hijo-ok', 'Hello Hijo OK', OFFICIAL_CHILD );
		$GLOBALS['t']['child_sheet'] = 'hello-hijo-ok';
		$GLOBALS['t']['carry']       = true;
		drive();
		$last = Full::last( false );
		check( ! in_array( 'install:digitalisimo-theme', logged( 'install:' ), true ) && empty( $last['theme_updated_now'] ) && 'digitalisimo-theme' === get_option( 'stylesheet' ), 'Al día: no se reinstala y el tema cambia.' );
		break;

	case 'theme_pre_unreadable':
		// Justo antes del cambio la portada no responde (la huella de estilo no se puede tomar): el tema no se cambia.
		$GLOBALS['t']['fail_in_theme_stage'] = true;
		drive();
		$last = Full::last( false );
		check( 'hello-elementor' === get_option( 'stylesheet' ) && ! logged( 'switch_theme' ) && false !== strpos( (string) $last['theme'][1]['reason'], 'Justo antes de cambiar el tema la portada no se pudo leer' ), 'Sin la huella de justo antes, no se cambia el tema: ' . json_encode( $last['theme'][1] ) );
		break;

	case 'verify_styles':
		// Con el tema propio, la portada pierde una hoja del constructor: el texto sigue igual, pero no se pinta igual.
		$GLOBALS['t']['drop_sheet'] = true;
		drive();
		$last = Full::last( false );
		check( empty( $last['verified'] ) && 'hello-elementor' === get_option( 'stylesheet' ) && ! empty( $last['theme'][1]['reverted'] ), 'Sin una hoja que se cargaba antes, vuelve a Hello: ' . json_encode( $last['theme'] ) );
		check( false !== strpos( (string) ( $last['verify'][0]['reason'] ?? '' ), 'faltan hojas de estilo que se cargaban antes: elementor-post-5' ), 'Y dice qué falta: ' . json_encode( $last['verify'] ) );
		nothing_deleted();
		break;

	case 'theme_other':
		$GLOBALS['t']['themes']['astra'] = array( 'Name' => 'Astra' );
		update_option( 'template', 'astra' );
		update_option( 'stylesheet', 'astra' );
		drive();
		$last = Full::last( false );
		check( 'astra' === get_option( 'stylesheet' ) && ! logged( 'install:digitalisimo-theme' ), 'Otro tema no se cambia ni se instala el propio.' );
		check( false !== strpos( $last['theme'][1]['reason'], 'Astra' ), 'El informe nombra el tema y el motivo.' );
		check( isset( $GLOBALS['t']['themes']['hello-elementor'] ), 'Hello, inactivo, no se toca.' );
		break;

	case 'verify_fails':
		$GLOBALS['t']['break'] = true;
		drive();
		$last = Full::last( false );
		check( empty( $last['verified'] ), 'La portada no responde igual.' );
		check( 'hello-elementor' === get_option( 'stylesheet' ) && ! empty( $last['theme'][1]['reverted'] ), 'Vuelve a Hello.' );
		nothing_deleted();
		check( installed_now( 'seo-by-rank-math/rank-math.php' ) && ! active( 'seo-by-rank-math/rank-math.php' ), 'Lo reemplazado queda desactivado, sin borrar.' );
		check( false !== strpos( (string) ( $last['kept']['rank-math'] ?? '' ), 'portada' ), 'El informe dice por qué no se borró.' );
		// La portada que no responde igual pide atención: el aviso no es verde y nada dice que no haya nada pendiente.
		$html = Full::render( false, $last );
		check( false === strpos( $html, 'Nada necesita' ) && false === strpos( $html, 'notice-success' ) && false !== strpos( $html, 'notice-warning' ) && false !== strpos( $html, 'Migración terminada sin borrar nada: la portada o alguna página no responde igual y se volvió al tema anterior.' ), 'Con la portada que no responde igual, el aviso es amarillo y lo dice: ' . strip_tags( substr( $html, 0, 300 ) ) );
		$attention = (string) strstr( $html, 'digi-migrate__attention-title' );
		check( false !== strpos( $attention, 'No responden igual después de migrar: http://example.test/' ) && false !== strpos( $attention, 'se volvió a Hello Elementor' ) && false !== strpos( $attention, 'No se borró nada' ), 'Y «Necesita tu atención» lista la página con el motivo, la vuelta del tema y que no se borró nada.' );
		check( false !== strpos( $attention, 'Desactivado, sin borrar hasta que la portada responda igual: Element Pack, Elementor, Rank Math SEO, UpdraftPlus.' ) && false === strpos( $attention, 'así que no se borró nada' ), 'Lo desactivado sin borrar, en la misma lista sin repetir el motivo: ' . strip_tags( $attention ) );
		break;

	case 'pending_content':
		$GLOBALS['t']['ep_needed'] = true;
		drive();
		$last = Full::last( false );
		check( installed_now( 'bdthemes-element-pack/bdthemes-element-pack.php' ), 'Element Pack, que aún hace falta, no se borra.' );
		check( false !== strpos( (string) ( $last['kept']['element-pack'] ?? '' ), 'Element Pack' ), 'El informe dice por qué.' );
		$html = Full::render( false, $last );
		check( false !== strpos( (string) strstr( $html, 'digi-migrate__attention-title' ), 'Desactivado, sin borrar: Element Pack' ) && false === strpos( $html, '<th scope="row">Desactivado, sin borrar</th>' ) && false === strpos( $html, 'Nada necesita' ), 'Lo desactivado sin borrar va en «Necesita tu atención», no en la tabla.' );
		check( ! installed_now( 'seo-by-rank-math/rank-math.php' ), 'Lo que no depende de él sí se reemplaza.' );
		break;

	case 'resume':
		// Tras importar, la misma petición desactiva lo reemplazado y pide otra: la tarea queda en `theme`.
		$state = drive( 80, 'theme' );
		check( 'theme' === ( $state['stage'] ?? '' ), 'Llega al cambio de tema.' );
		// La pantalla se cierra y el proceso muere: la tarea guardada sigue en otra petición (la del WP-Cron).
		$saved = get_option( Full::JOB );
		check( is_array( $saved ) && 'theme' === $saved['stage'] && ! empty( $GLOBALS['t']['cron'][ Full::CRON ] ), 'La tarea queda guardada con su continuación programada.' );
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
		$GLOBALS['t']['loaded']        = loaded_now();
		unset( $GLOBALS['t']['cron'][ Full::CRON ] );
		Full::cron();
		for ( $i = 0; $i < 40 && get_option( Full::JOB ); $i++ ) {
			request();
		}
		$last = Full::last( false );
		check( $last && '' === $last['failed'] && 'digitalisimo-theme' === get_option( 'stylesheet' ), 'Termina tras reanudar.' );
		check( 1 === count( $GLOBALS['t']['specs'] ) && 1 === count( logged( 'install:digitalisimo-elements' ) ) && 1 === count( logged( 'rankmath:start' ) ), 'Nada se repite al reanudar.' );
		// Una etapa que tumba la petición tres veces seguidas falla sin borrar nada.
		update_option( Full::JOB, array_merge( Full::fresh( false, 'x' ), array( 'stage' => 'backup', 'attempts' => 3 ) ) );
		$GLOBALS['t']['log'] = array();
		request();
		$last = Full::last( false );
		check( 'backup' === $last['failed'] && false !== strpos( $last['error'], 'se cortó' ), 'Tres caídas seguidas detienen la tarea: ' . $last['error'] );
		nothing_deleted();
		break;

	case 'double_click':
		// Otra petición tiene el cerrojo: no se crea ninguna tarea.
		$GLOBALS['t']['rows'][ Full::LOCK ] = time() . '|ajena';
		$state                              = request( true );
		check( ! empty( $state['busy'] ) && ! get_option( Full::JOB ), 'Con el cerrojo tomado, el clic no crea otra tarea.' );
		unset( $GLOBALS['t']['rows'][ Full::LOCK ] );
		// Un cerrojo abandonado (petición muerta) se roba pasado su plazo.
		$GLOBALS['t']['rows'][ Full::LOCK ] = ( time() - Full::STALE - 5 ) . '|muerta';
		$state                              = request( true );
		check( empty( $state['busy'] ) && get_option( Full::JOB ), 'Un cerrojo abandonado se recupera.' );
		$started = get_option( Full::JOB )['started'];
		// Un segundo clic con otro identificador mientras corre sigue la misma tarea.
		request( true, 'clic2' );
		check( get_option( Full::JOB )['started'] === $started && 'clic1' === get_option( Full::JOB )['run'], 'Un segundo clic sigue la tarea en marcha.' );
		for ( $i = 0; $i < 80 && get_option( Full::JOB ); $i++ ) {
			request();
		}
		$finished = Full::last( false )['finished'];
		// Reintento del mismo clic (se perdió la respuesta): no lanza otra pasada.
		$state = request( true, 'clic1' );
		check( ! empty( $state['idle'] ) && ! get_option( Full::JOB ) && Full::last( false )['finished'] === $finished, 'El mismo clic repetido no lanza otra migración.' );
		check( 1 === count( $GLOBALS['t']['specs'] ), 'Un solo respaldo.' );
		check( ! isset( $GLOBALS['t']['rows'][ Full::LOCK ] ), 'El cerrojo se suelta.' );
		break;

	case 'no_loopback':
		// Un servidor que no puede pedirse a sí mismo: sin portada legible antes de empezar, el tema no se cambia y no
		// se borra nada (no hay verificación), aunque todo lo demás termine.
		Front::$fetcher = static function ( $url ) {
			return new WP_Error( 'x', 'cURL error 7: Failed to connect' );
		};
		drive();
		$last = Full::last( false );
		check( '' === $last['failed'] && empty( $last['verified'] ), 'Termina sin verificación: ' . $last['error'] );
		check( ! logged( 'switch_theme' ) && 'hello-elementor' === get_option( 'stylesheet' ), 'El tema no se cambia sin poder comprobarlo.' );
		check( false !== strpos( (string) $last['theme'][1]['reason'], 'no se pudo leer' ), 'El informe dice por qué: ' . $last['theme'][1]['reason'] );
		nothing_deleted();
		check( installed_now( 'seo-by-rank-math/rank-math.php' ) && ! active( 'seo-by-rank-math/rank-math.php' ), 'Lo reemplazado queda desactivado, sin borrar.' );
		break;

	case 'cli':
		// `wp digitalisimo-migration --run`: la misma tarea; tras cambiar plugins o el tema sigue en un proceso nuevo, que
		// recibe el clic del primero (`--run-id`).
		fake_wp_cli();
		$error = cli_processes( false );
		$last  = Full::last( false );
		check( '' === $error && 'Migración completada.' === ( $GLOBALS['t']['cli_done'] ?? '' ) && '' === $last['failed'], 'WP-CLI termina la migración: ' . $error . ( $last['error'] ?? '' ) );
		$runs = array_values( array_unique( array_map( static function ( $c ) { return preg_replace( '/^.*--run-id=/', '', $c ); }, $GLOBALS['t']['relaunch'] ?? array() ) ) );
		check( count( $GLOBALS['t']['relaunch'] ?? array() ) >= 3 && 1 === count( $runs ) && 0 === strpos( $GLOBALS['t']['relaunch'][0], 'digitalisimo-migration --run --run-id=' ), 'Sigue en procesos nuevos con el mismo clic: ' . implode( ' | ', $GLOBALS['t']['relaunch'] ?? array() ) );
		check( ! installed_now( 'seo-by-rank-math/rank-math.php' ) && 'digitalisimo-theme' === get_option( 'stylesheet' ) && 1 === count( $GLOBALS['t']['specs'] ), 'Hace lo mismo que el botón (un respaldo, reemplazo y tema).' );
		// El WP-Cron terminó esa misma pasada justo cuando WP-CLI relanzaba: el proceso nuevo muestra el informe, no empieza otra.
		$GLOBALS['t']['cli_done'] = '';
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
		Full::cli( false, $runs[0] );
		check( 'Migración completada.' === $GLOBALS['t']['cli_done'] && ! get_option( Full::JOB ) && 1 === count( $GLOBALS['t']['specs'] ), 'Un proceso relanzado tras terminar la pasada no lanza otra migración.' );
		break;

	case 'cli_disallow':
		// DISALLOW_FILE_MODS: WP-CLI no tiene cuenta, pero no puede instalar ni borrar.
		fake_wp_cli();
		$GLOBALS['t']['no_file_mods'] = true;
		$error = cli_processes( false );
		check( false !== strpos( $error, 'no puede instalar' ) && ! logged( 'install:' ), 'Sin escritura, WP-CLI no instala nada: ' . $error );
		nothing_deleted();
		break;

	case 'cli_network':
		// `--run --network` cuyo contenido no termina: tres rondas y se para (antes, hasta 7 200 vueltas lanzando procesos).
		fake_wp_cli();
		make_network();
		$GLOBALS['t']['net_stuck'] = true;
		$error = cli_processes( true, 40 );
		check( false !== strpos( $error, 'tres rondas' ) && 3 === (int) ( $GLOBALS['t']['net_rounds'] ?? 0 ), 'Tres rondas de contenido y se para: ' . $error . ' (' . (int) ( $GLOBALS['t']['net_rounds'] ?? 0 ) . ')' );
		nothing_deleted();
		break;

	case 'dependents':
		// Un complemento que declara `Requires Plugins: seo-by-rank-math`: Rank Math no se desactiva ni se borra.
		plugin( 'rank-math-addon/rank-math-addon.php', 'Complemento de Rank Math' );
		$GLOBALS['t']['plugins']['rank-math-addon/rank-math-addon.php']['RequiresPlugins'] = 'seo-by-rank-math';
		// Un complemento de Elementor: el contenido se migra (sigue funcionando con el núcleo incluido), pero Elementor no se borra.
		plugin( 'elementor-addon/elementor-addon.php', 'Complemento de Elementor' );
		$GLOBALS['t']['plugins']['elementor-addon/elementor-addon.php']['RequiresPlugins'] = 'elementor';
		drive();
		$last = Full::last( false );
		check( active( 'seo-by-rank-math/rank-math.php' ) && installed_now( 'seo-by-rank-math/rank-math.php' ), 'Rank Math sigue activo e instalado.' );
		check( false !== strpos( (string) ( $last['kept']['rank-math'] ?? '' ), 'Complemento de Rank Math' ), 'El informe nombra al dependiente: ' . ( $last['kept']['rank-math'] ?? '' ) );
		check( ! installed_now( 'updraftplus/updraftplus.php' ) && ! installed_now( 'bdthemes-element-pack/bdthemes-element-pack.php' ), 'Lo que no tiene dependientes sí se reemplaza.' );
		check( in_array( 'content:done', $GLOBALS['t']['log'], true ) && installed_now( 'elementor/elementor.php' ) && ! active( 'elementor/elementor.php' ), 'Con un complemento de Elementor, el contenido se migra y Elementor queda desactivado, sin borrar.' );
		check( false !== strpos( (string) ( $last['kept']['elementor'] ?? '' ), 'Complemento de Elementor' ), 'El informe nombra al complemento: ' . ( $last['kept']['elementor'] ?? '' ) );
		break;

	case 'yoast_gaps':
		// Yoast quitaba la base de las categorías: sin él, sus URL darían 404. Se queda activo, sin importar.
		plugin( 'wordpress-seo/wp-seo.php', 'Yoast SEO' );
		update_option( 'active_plugins', array_merge( (array) get_option( 'active_plugins' ), array( 'wordpress-seo/wp-seo.php' ) ) );
		update_option( 'wpseo_titles', array( 'stripcategorybase' => true ) );
		drive();
		$last = Full::last( false );
		check( active( 'wordpress-seo/wp-seo.php' ) && installed_now( 'wordpress-seo/wp-seo.php' ), 'Yoast sigue activo.' );
		check( false !== strpos( (string) ( $last['kept']['yoast'] ?? '' ), 'base de las categorías' ), 'El informe dice qué no se importa: ' . ( $last['kept']['yoast'] ?? '' ) );
		break;

	case 'updraft_remote':
		// UpdraftPlus con destino remoto y respaldos diarios: se desactiva, no se borra, y se avisa de la programación.
		update_option( 'updraft_service', array( 's3' ) );
		update_option( 'updraft_interval', 'daily' );
		drive();
		$last = Full::last( false );
		check( installed_now( 'updraftplus/updraftplus.php' ) && ! active( 'updraftplus/updraftplus.php' ), 'UpdraftPlus queda desactivado, sin borrar.' );
		check( false !== strpos( (string) ( $last['kept']['updraftplus'] ?? '' ), 'destino remoto' ), 'Con su motivo: ' . ( $last['kept']['updraftplus'] ?? '' ) );
		check( (bool) preg_grep( '/daily/', (array) $last['notes'] ), 'Avisa de su programación.' );
		check( false !== strpos( Full::render( false, $last ), 'Desactivado, sin borrar' ), 'El informe lo pone en «Desactivado, sin borrar».' );
		break;

	case 'updraft_unseen':
		// Su historial tiene 3 respaldos y Backups sólo ve 2 en su carpeta: no se borra.
		update_option( 'updraft_backup_history', array( 1 => array(), 2 => array(), 3 => array() ) );
		drive();
		$last = Full::last( false );
		check( installed_now( 'updraftplus/updraftplus.php' ) && false !== strpos( (string) ( $last['kept']['updraftplus'] ?? '' ), 'no ve' ), 'Respaldos que Backups no ve: no se borra (' . ( $last['kept']['updraftplus'] ?? '' ) . ').' );
		break;

	case 'cleanup_crash':
		// La petición muere justo después de borrar Rank Math: el informe no lo pierde ni dice «no se borró nada».
		$GLOBALS['t']['crash_on'] = 'seo-by-rank-math/rank-math.php';
		drive();
		$last = Full::last( false );
		check( 'cleanup' === $last['failed'], 'Se detiene en la limpieza.' );
		check( isset( $last['deleted']['rank-math'], $last['deleted']['element-pack'] ), 'Lo borrado, también lo de la vuelta cortada, queda en el informe: ' . implode( ', ', array_keys( (array) $last['deleted'] ) ) );
		$html = Full::render( false, $last );
		check( false === strpos( $html, 'No se borró nada' ) && false !== strpos( $html, 'Ya se había borrado' ), 'El título no miente sobre lo borrado.' );
		check( empty( $last['deleting'] ), 'Sin borrado en curso pendiente.' );
		break;

	case 'maintenance':
		// Modo mantenimiento: todas las URL dan la misma página. No es comparable: ni tema ni borrado.
		$GLOBALS['t']['maintenance'] = true;
		drive();
		$last = Full::last( false );
		check( ! logged( 'switch_theme' ) && empty( $last['verified'] ), 'Sin portada comparable, no cambia el tema ni verifica.' );
		nothing_deleted();
		break;

	case 'cached':
		// Una caché devuelve el HTML de antes (con Hello) después de cambiar de tema: no es «responde igual».
		$GLOBALS['t']['cached'] = true;
		drive();
		$last = Full::last( false );
		check( empty( $last['verified'] ) && 'hello-elementor' === get_option( 'stylesheet' ), 'La respuesta de caché no verifica, y se vuelve a Hello.' );
		check( (bool) array_filter( (array) $last['verify'], static function ( $r ) { return false !== strpos( (string) $r['reason'], 'caché' ); } ), 'El motivo dice caché.' );
		nothing_deleted();
		break;

	case 'rerun':
		// Primera pasada: la portada no responde igual; no se borra nada y su huella queda de referencia.
		$GLOBALS['t']['break'] = true;
		drive();
		check( empty( Full::last( false )['verified'] ) && Full::baseline( false ), 'Primera pasada sin verificar: queda la referencia.' );
		nothing_deleted();
		// El sitio ya se ve distinto (está a medio migrar): una segunda pasada no puede tomarlo como referencia.
		$GLOBALS['t']['break']   = false;
		$GLOBALS['t']['changed'] = true;
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
		$state = request( true, 'clic2' );
		for ( $i = 0; $i < 80 && empty( $state['done'] ); $i++ ) {
			$state = request();
		}
		$second = Full::last( false );
		check( empty( $second['verified'] ) && (bool) preg_grep( '/primera pasada/', (array) $second['notes'] ), 'La segunda pasada compara con la portada de antes de la primera.' );
		nothing_deleted();
		// Con el sitio como antes, una tercera pasada verifica, borra y retira la referencia.
		$GLOBALS['t']['changed'] = false;
		$state = request( true, 'clic3' );
		for ( $i = 0; $i < 80 && empty( $state['done'] ); $i++ ) {
			$state = request();
		}
		$third = Full::last( false );
		check( ! empty( $third['verified'] ) && ! installed_now( 'seo-by-rank-math/rank-math.php' ) && ! Full::baseline( false ), 'La tercera verifica, borra y retira la referencia: ' . $third['error'] );
		break;

	case 'backup_gone':
		// El respaldo desaparece del almacén antes de la limpieza: no se borra nada.
		drive( 80, 'verify' );
		@unlink( TMP . '/store/backup-1.zip' );
		for ( $i = 0; $i < 40 && get_option( Full::JOB ); $i++ ) {
			request();
		}
		$last = Full::last( false );
		check( false !== strpos( (string) ( $last['kept']['rank-math'] ?? '' ), 'respaldo' ), 'Sin respaldo entero, no se borra: ' . ( $last['kept']['rank-math'] ?? '' ) );
		nothing_deleted();
		break;

	case 'uninstall_file':
		// Element Pack con un uninstall.php propio: su código correría junto al núcleo incluido. No se borra.
		file_put_contents( WP_PLUGIN_DIR . '/bdthemes-element-pack/uninstall.php', '<?php' );
		drive();
		$last = Full::last( false );
		check( installed_now( 'bdthemes-element-pack/bdthemes-element-pack.php' ) && false !== strpos( (string) ( $last['kept']['element-pack'] ?? '' ), 'desinstalación' ), 'Element Pack con uninstall.php no se borra.' );
		check( ! installed_now( 'seo-by-rank-math/rank-math.php' ), 'Lo demás sí.' );
		break;

	case 'slow_boot':
		// Un servidor lento: WordPress tarda 5 s en arrancar cada petición. El importador de Rank Math igual corre.
		$GLOBALS['t']['slow_boot'] = true;
		drive( 60 );
		$last = Full::last( false );
		check( $last && '' === $last['failed'] && ! installed_now( 'seo-by-rank-math/rank-math.php' ), 'Con un arranque lento la migración termina (antes el importador no corría nunca).' );
		break;

	case 'verify_crash':
		// La comprobación falla con el tema ya cambiado: la tarea se detiene y vuelve a Hello (no deja el tema sin comprobar).
		$GLOBALS['t']['verify_throws'] = true;
		drive();
		$last = Full::last( false );
		check( 'verify' === $last['failed'] && 'hello-elementor' === get_option( 'stylesheet' ) && ! empty( $last['theme'][1]['reverted'] ), 'Fallo en la comprobación: vuelve a Hello (' . get_option( 'stylesheet' ) . ').' );
		nothing_deleted();
		break;

	case 'old_wp':
		// WordPress 6.6 con Elements (6.8) por instalar: Migrar no empieza ni toca nada.
		$GLOBALS['wp_version'] = '6.6.2';
		$state = request( true );
		check( ! empty( $state['idle'] ) && false !== strpos( (string) ( $state['refused'] ?? '' ), '6.8' ) && ! logged( 'install:' ) && ! get_option( Full::JOB ), 'Con WordPress anterior al que piden los paquetes, no empieza: ' . ( $state['refused'] ?? '' ) );
		break;

	case 'network':
		make_network();
		drive( 120 );
		$last = Full::last( true );
		check( $last && '' === $last['failed'] && ! empty( $last['verified'] ), 'La red termina verificada: ' . ( $last['error'] ?? '' ) );
		check( in_array( 'activate:' . ELEMENTS . ':red', $GLOBALS['t']['log'], true ) && in_array( 'activate:' . SEO . ':red', $GLOBALS['t']['log'], true ), 'Los plugins DIGITALÍSIMO se activan en toda la red.' );
		check( in_array( 'network_enable_theme:digitalisimo-theme', $GLOBALS['t']['log'], true ) && 'digitalisimo-theme' === get_blog_option( 1, 'stylesheet' ) && 'digitalisimo-theme' === get_blog_option( 2, 'stylesheet' ), 'El tema propio se habilita en la red y se activa en los dos sitios.' );
		check( 4 === count( $last['verify'] ), 'Portada y una página de cada sitio comparadas (' . count( $last['verify'] ) . ').' );
		foreach ( array( 'bdthemes-element-pack/bdthemes-element-pack.php', 'seo-by-rank-math/rank-math.php', 'updraftplus/updraftplus.php', 'elementor/elementor.php' ) as $file ) {
			check( ! installed_now( $file ), "Borrado en la red: {$file}" );
		}
		check( ! isset( $GLOBALS['t']['themes']['hello-elementor'] ), 'Hello se borra cuando ningún sitio lo usa.' );
		check( 'done' === ( $GLOBALS['t']['options'][2]['rm_job']['status'] ?? '' ) && 'done' === ( $GLOBALS['t']['options'][1]['rm_job']['status'] ?? '' ), 'Rank Math se importa en cada sitio.' );
		check( ! get_site_option( Full::BASELINE ) , 'Verificada la red, no queda referencia de portada.' );
		break;
}

if ( $GLOBALS['problems'] ) {
	echo "FALLA [{$scenario}]\n  - " . implode( "\n  - ", $GLOBALS['problems'] ) . "\n";
	exit( 1 );
}
echo "ok [{$scenario}]\n";
