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
	$scenarios = array( 'unit', 'network', 'cli_network', 'cli_disallow', 'dependents', 'yoast_gaps', 'updraft_remote', 'updraft_unseen', 'cleanup_crash', 'maintenance', 'cached', 'rerun', 'backup_gone', 'uninstall_file', 'verify_crash', 'slow_boot', 'old_wp', 'happy', 'backup_history', 'backup_fails', 'install_fails', 'import_fails', 'theme_child', 'theme_other', 'verify_fails', 'pending_content', 'resume', 'double_click', 'cli', 'no_loopback' );
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
function apply_filters( $tag, $value ) { return $value; }
function add_action() {}
function add_filter() {}
function has_filter() { return false; }
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
	public $options = 'wp_options';
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
set_site_transient( 'digitalisimo_plugins_manifest', array( 'plugins' => $entries, 'themes' => array( 'digitalisimo-theme' => array( 'version' => '1.0.0', 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.11.18.999/digitalisimo-theme-1.0.0.zip' ) ) ) );

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
	if ( 'theme' === $type ) {
		$GLOBALS['t']['themes'][ $slug ] = array( 'Name' => 'DIGITALÍSIMO', 'UpdateURI' => Replacements::THEME_URI );
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
	$theme = ! empty( $GLOBALS['t']['cached'] ) ? 'hello-elementor' : (string) get_blog_option( $blog, 'stylesheet' );
	if ( ! empty( $GLOBALS['t']['verify_throws'] ) && 'digitalisimo-theme' === $theme ) {
		throw new RuntimeException( 'la comprobación se cortó' );
	}
	if ( ! empty( $GLOBALS['t']['break'] ) && 'digitalisimo-theme' === $theme ) {
		return array( 500, '<p>There has been a critical error on this website.</p>' );
	}
	if ( ! empty( $GLOBALS['t']['maintenance'] ) ) {
		return array( 200, '<html><body><h1>Volvemos pronto</h1><p>Estamos haciendo mejoras en el sitio web</p></body></html>' );
	}
	$body = ! empty( $GLOBALS['t']['changed'] ) ? str_replace( 'Plantas de interior, macetas artesanales y envíos a toda la ciudad cada semana', 'Página distinta con otro contenido completamente nuevo y ajeno', $page ) : $page;
	// Cada página tiene además su propio texto (si no, todas iguales parecerían una página de mantenimiento).
	$own  = false !== strpos( $url, 'sobre' ) ? 'Conoce nuestra historia familiar' : 'Bienvenidos a la portada principal';
	$body = str_replace( '<footer>', '<section>' . $own . '</section><footer>', $body );
	return array( 200, str_replace( '<head>', '<head><link rel="stylesheet" href="http://example.test/wp-content/themes/' . $theme . '/style.css">', $body ) );
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
		check( ! Full::theme_safety( array( 'child' => true ) + $hello, $ready )[0], 'Un hijo de Hello no es seguro.' );
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
		$GLOBALS['t']['themes']['hello-hijo'] = array( 'Name' => 'Hello Hijo', 'Template' => 'hello-elementor' );
		update_option( 'stylesheet', 'hello-hijo' );
		drive();
		$last = Full::last( false );
		check( '' === $last['failed'], 'Termina: ' . $last['error'] );
		check( 'hello-hijo' === get_option( 'stylesheet' ) && ! logged( 'switch_theme' ), 'Un hijo de Hello no se cambia.' );
		check( false !== strpos( $last['theme'][1]['reason'], 'tema hijo' ), 'El informe dice por qué: ' . $last['theme'][1]['reason'] );
		check( isset( $GLOBALS['t']['themes']['hello-elementor'] ), 'Hello no se borra.' );
		check( ! installed_now( 'seo-by-rank-math/rank-math.php' ), 'Lo demás sí se reemplaza.' );
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
		break;

	case 'pending_content':
		$GLOBALS['t']['ep_needed'] = true;
		drive();
		$last = Full::last( false );
		check( installed_now( 'bdthemes-element-pack/bdthemes-element-pack.php' ), 'Element Pack, que aún hace falta, no se borra.' );
		check( false !== strpos( (string) ( $last['kept']['element-pack'] ?? '' ), 'Element Pack' ), 'El informe dice por qué.' );
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
		// La activación en toda la red corre el gancho en el sitio principal; ahí queda (y se borra) la redirección.
		$GLOBALS['t']['on_activate'][ ELEMENTS ] = static function ( $network ) {
			$GLOBALS['t']['log'][] = 'activation_hook:' . blog() . ( $network ? ':red' : '' );
			switch_to_blog( 1 );
			set_transient( 'elementor_activation_redirect', true, 60 );
			restore_current_blog();
		};
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
		check( in_array( 'activation_hook:1:red', $GLOBALS['t']['log'], true ) && false === get_blog_option( 1, '_transient_elementor_activation_redirect', false ) && false === get_blog_option( 2, '_transient_elementor_activation_redirect', false ), 'En la red tampoco queda la redirección de activación de Elements (sitio principal).' );
		break;
}

if ( $GLOBALS['problems'] ) {
	echo "FALLA [{$scenario}]\n  - " . implode( "\n  - ", $GLOBALS['problems'] ) . "\n";
	exit( 1 );
}
echo "ok [{$scenario}]\n";
