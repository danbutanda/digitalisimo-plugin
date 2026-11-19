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
	$scenarios = array( 'unit', 'happy', 'backup_history', 'backup_fails', 'install_fails', 'import_fails', 'theme_child', 'theme_other', 'verify_fails', 'pending_content', 'resume', 'double_click', 'cli', 'no_loopback' );
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
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['t']['options'] ) ? $GLOBALS['t']['options'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['t']['options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['t']['options'][ $k ] ); return true; }
function get_site_option( $k, $d = false ) { return get_option( $k, $d ); }
function update_site_option( $k, $v ) { return update_option( $k, $v ); }
function delete_site_option( $k ) { return delete_option( $k ); }
function get_site_transient( $k ) { return $GLOBALS['t']['transients'][ $k ] ?? false; }
function set_site_transient( $k, $v, $e = 0 ) { $GLOBALS['t']['transients'][ $k ] = $v; return true; }
function is_multisite() { return false; }
function is_main_site() { return true; }
function get_main_site_id() { return 1; }
function get_current_blog_id() { return 1; }
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
function home_url( $path = '' ) { return 'http://example.test' . $path; }
function get_posts( $args ) { return array( 10 ); }
function get_permalink( $id ) { return 'http://example.test/sobre/'; }
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
function activate_plugin( $file, $r = '', $network = false ) {
	if ( ! isset( $GLOBALS['t']['plugins'][ $file ] ) ) {
		return new WP_Error( 'x', 'no instalado' );
	}
	$GLOBALS['t']['log'][] = 'activate:' . $file;
	$active                = (array) get_option( 'active_plugins', array() );
	$active[]              = $file;
	update_option( 'active_plugins', array_values( array_unique( $active ) ) );
	return null;
}
function deactivate_plugins( $files, $silent = false, $network = null ) {
	foreach ( (array) $files as $file ) {
		$GLOBALS['t']['log'][] = 'deactivate:' . $file;
	}
	update_option( 'active_plugins', array_values( array_diff( (array) get_option( 'active_plugins', array() ), (array) $files ) ) );
}
function delete_plugins( $files ) {
	foreach ( (array) $files as $file ) {
		$GLOBALS['t']['log'][] = 'delete:' . $file;
		unset( $GLOBALS['t']['plugins'][ $file ] );
		@unlink( WP_PLUGIN_DIR . '/' . $file );
	}
	return true;
}
class WP_Theme {
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
Front::$fetcher = static function ( $url ) use ( $page ) {
	if ( ! empty( $GLOBALS['t']['break'] ) && 'digitalisimo-theme' === get_option( 'stylesheet' ) ) {
		return array( 500, '<p>There has been a critical error on this website.</p>' );
	}
	return array( 200, $page );
};

/** Una petición nueva: carga los plugins activos y avanza la tarea. */
function request( $start = false, $run = 'clic1' ) {
	usleep( 2000 );
	$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
	$GLOBALS['t']['loaded']        = (array) get_option( 'active_plugins', array() );
	return Full::step( false, $start, 30, $run );
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
		// UpdraftPlus conserva sus datos por defecto; con «borrar al desinstalar» y carpeta propia, no.
		check( Full::updraft_keeps_data(), 'UpdraftPlus conserva sus datos al desinstalarse por defecto.' );
		update_option( 'updraftplus_deinstall_option', 'yes' );
		update_option( 'updraft_dir', '/srv/respaldos-propios' );
		check( ! Full::updraft_keeps_data(), 'Con «borrar al desinstalar» y su carpeta propia, la perdería.' );
		update_option( 'updraft_dir', 'updraft' );
		check( Full::updraft_keeps_data(), 'Con la carpeta habitual, Backups la sigue encontrando.' );
		break;

	case 'happy':
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
		$GLOBALS['t']['loaded']        = (array) get_option( 'active_plugins', array() );
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
		// `wp digitalisimo-migration --run`: la misma tarea; tras cambiar plugins o el tema sigue en un proceso nuevo.
		define( 'WP_CLI', true );
		eval( 'final class WP_CLI {
			public static function log( $m ) {}
			public static function success( $m ) { $GLOBALS["t"]["cli_done"] = $m; }
			public static function error( $m ) { throw new RuntimeException( $m ); }
			public static function runcommand( $command, $options = array() ) { $GLOBALS["t"]["relaunch"][] = $command; }
		}' );
		for ( $i = 0; $i < 30 && empty( $GLOBALS['t']['cli_done'] ); $i++ ) {
			usleep( 2000 );
			$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
			$GLOBALS['t']['loaded']        = (array) get_option( 'active_plugins', array() );
			Full::cli( false );
		}
		$last = Full::last( false );
		check( 'Migración completada.' === ( $GLOBALS['t']['cli_done'] ?? '' ) && '' === $last['failed'], 'WP-CLI termina la migración: ' . ( $last['error'] ?? '' ) );
		check( count( $GLOBALS['t']['relaunch'] ?? array() ) >= 3 && array( 'digitalisimo-migration --run' ) === array_values( array_unique( $GLOBALS['t']['relaunch'] ) ), 'Sigue en procesos nuevos tras cada cambio: ' . implode( ' | ', $GLOBALS['t']['relaunch'] ?? array() ) );
		check( ! installed_now( 'seo-by-rank-math/rank-math.php' ) && 'digitalisimo-theme' === get_option( 'stylesheet' ) && 1 === count( $GLOBALS['t']['specs'] ), 'Hace lo mismo que el botón (un respaldo, reemplazo y tema).' );
		break;
}

if ( $GLOBALS['problems'] ) {
	echo "FALLA [{$scenario}]\n  - " . implode( "\n  - ", $GLOBALS['problems'] ) . "\n";
	exit( 1 );
}
echo "ok [{$scenario}]\n";
