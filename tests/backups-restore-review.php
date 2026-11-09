<?php
/**
 * DIGITALÍSIMO Backups · restauración por pasos tras la revisión adversaria (/var/tmp/digi-wt/bk-restore-review).
 * Cada caso reproduce un hallazgo con el código de la rama y comprueba lo que debe pasar ahora:
 *   C1  un RENAME TABLE que se ejecuta pero devuelve error: se reconoce por la base de datos y la restauración sigue;
 *       las tablas viejas de una restauración sin confirmar nunca se borran.
 *   C2  la vuelta a la copia previa que falla antes de su propio cambio: el sitio sigue en mantenimiento sin caducar,
 *       el estado es «a medio cambiar», el aviso da el camino de recuperación y «Volver a la copia previa» lo recupera.
 *   A1  el .maintenance deja pasar wp-cron.php (WordPress mínimo) y el tick con testigo; un visitante despierta WP-Cron;
 *       la pantalla no promete lo que no pasa. P1: durante la restauración, WP-Cron sólo corre sus eventos.
 *   A2  este plugin (otra versión en el respaldo) va en el último paso: ningún tick queda con el plugin del respaldo y
 *       la restauración sin terminar.
 *   A3  entre dos ticks nunca quedan la base de datos del respaldo con el código de antes ni el núcleo a medio cambiar.
 *   A4  una unidad apartada que no llega a su sitio vuelve; entre discos, una preparada a medio borrar no pasa por buena.
 *   A5  no se retira wordfence-waf.php mientras un .user.ini lo cargue.
 *   M1  lo creado después de empezar y que no está en la copia previa se aparta en el almacén; el aviso lo dice.
 *   M2  H3 también en cache/, node_modules/ y los .phtml de la raíz.   M3  sin SHA-256 (v2) se reescribe siempre.
 *   M4  entries() con 60 000 entradas no guarda una entrada por archivo en memoria.   B1  el aviso sin rutas de este servidor.
 *   P6  la exportación de un sitio no lleva tablas de una restauración.   P7  WP-CLI restaura con reintentos.
 *   P11 la limpieza siempre avanza aunque el tick llegue sin tiempo.
 * Uso: php tests/backups-restore-review.php (php-wasm: igual).
 */
require __DIR__ . '/lib/backups-env.php';
// Lo que la pantalla necesita y el entorno común no simula.
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }

$failures = 0;
function rr_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function rr_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
$root = rtrim( ABSPATH, '/' );
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
foreach ( array(
	'wp-content/plugins/demo/demo.php' => "<?php /* Plugin Name: Demo */\n",
	'wp-content/plugins/demo/inc/a.php' => "<?php // a original\n",
	'wp-content/themes/tema/style.css' => "/* Theme Name: Tema */\n",
	'wp-content/uploads/2026/11/foto.jpg' => str_repeat( "\xff\xd8\xff", 3000 ),
	'wp-content/uploads/cache/legit.txt' => "cache legítima\n",
	'wp-content/plugins/demo/node_modules/dep.js' => "// dep\n",
) as $name => $content ) { @mkdir( dirname( $root . '/' . $name ), 0700, true ); file_put_contents( $root . '/' . $name, $content ); }
$GLOBALS['bk_options']['blogname'] = 'Del respaldo';
$backup = bk_backup( true );
$expected = bk_tree();

function rr_restore( $file, array $extra = array() ) {
	return Digitalisimo_Backups::service_restore( basename( $file ), $extra + array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false, 'via' => 'test' ) );
}
function rr_finish() {
	for ( $i = 0; $i < 3000; $i++ ) {
		if ( ! Digitalisimo_Backups_Jobs::active() ) return true;
		Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	}
	return false;
}
function rr_restores() {
	$out = array();
	foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( 'restore' === $job['kind'] ) $out[] = $job;
	usort( $out, function( $a, $b ) { return (float) $a['queued_at'] <=> (float) $b['queued_at']; } );
	return $out;
}
function rr_current() {
	$id = Digitalisimo_Backups_Jobs::current_id();
	return '' !== $id ? Digitalisimo_Backups_Jobs::get( $id ) : null;
}
/** Deja el sitio como tras el respaldo, sin trabajos ni mantenimiento (para el caso siguiente). */
function rr_reset( $root, $backup ) {
	foreach ( Digitalisimo_Backups_Job_Store::all() as $job ) Digitalisimo_Backups_Job_Store::remove_tree( Digitalisimo_Backups_Job_Store::directory( $job['id'] ) );
	@unlink( $root . '/.maintenance' );
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'], $GLOBALS['bk_filters']['digitalisimo_backups_request_limit'], $GLOBALS['bk_on_mkdir'] );
	$GLOBALS['wpdb']->fail_on = $GLOBALS['wpdb']->exit_on = $GLOBALS['wpdb']->exit_after = '';
	$GLOBALS['wpdb']->staged = array();
	$GLOBALS['bk_options']['blogname'] = 'Del respaldo';
	unset( $GLOBALS['bk_options']['opcion_intrusa'] );
}
/** Lo que el sitio tiene de un archivo del respaldo, o null. */
function rr_file( $root, $relative ) { $path = $root . '/' . $relative; return is_file( $path ) ? (string) file_get_contents( $path ) : null; }

// C1: MySQL hace el RENAME pero PHP recibe un error (conexión perdida, o un RENAME que esperaba un bloqueo).
class Rr_Lossy_DB extends Bk_Test_DB {
	public $lose = 0;
	public function query( $sql ) {
		if ( 0 === strpos( $sql, 'RENAME TABLE ' ) && preg_match_all( '/`([^`]+)` TO `([^`]+)`/', $sql, $pairs, PREG_SET_ORDER ) ) {
			// Como MySQL: el origen debe existir y el destino no (en el orden de la sentencia).
			$exists = array_fill_keys( array_merge( $this->tables(), array_keys( $this->staged ) ), true );
			foreach ( $pairs as $pair ) {
				if ( ! isset( $exists[ $pair[1] ] ) ) { $this->queries[] = $sql; $this->last_error = "Table '" . $pair[1] . "' doesn't exist"; return false; }
				if ( isset( $exists[ $pair[2] ] ) && ! in_array( $pair[2], array( 'wp_options', 'wp_posts' ), true ) ) { $this->queries[] = $sql; $this->last_error = "Table '" . $pair[2] . "' already exists"; return false; }
				unset( $exists[ $pair[1] ] );
				$exists[ $pair[2] ] = true;
			}
			$done = parent::query( $sql );
			if ( $this->lose > 0 ) { $this->lose--; $this->last_error = 'Lost connection to MySQL server during query'; return false; }
			return $done;
		}
		return parent::query( $sql );
	}
}
rr_case( 'C1 un RENAME hecho que devuelve error se reconoce por la base de datos y la restauración termina', function() use ( $root, $backup, $expected ) {
	rr_reset( $root, $backup );
	$real = $GLOBALS['wpdb'];
	$lossy = new Rr_Lossy_DB();
	$lossy->posts = $real->posts;
	$lossy->extra_tables = $real->extra_tables;
	$GLOBALS['wpdb'] = $lossy;
	try {
		$GLOBALS['bk_options']['blogname'] = 'Antes de restaurar';
		file_put_contents( $root . '/wp-content/plugins/demo/inc/a.php', "<?php // a cambiado despues del respaldo\n" );
		$lossy->lose = 1;
		$result = rr_restore( $backup );
		rr_finish();
		$jobs = rr_restores();
		rr_check( 1 === count( $jobs ) && 'success' === $jobs[0]['status'], 'La restauración no terminó (o hizo falta volver a la copia previa): ' . json_encode( array_map( function( $j ) { return array( $j['status'], $j['phase'], $j['error']['message'] ?? '' ); }, $jobs ) ) );
		rr_check( in_array( 'swap_confirmed', array_column( Digitalisimo_Backups_Job_Store::events( $jobs[0]['id'] ), 'type' ), true ), 'No se anotó que el RENAME con error sí se hizo.' );
		rr_check( 'Del respaldo' === ( $GLOBALS['bk_options']['blogname'] ?? '' ) && "<?php // a original\n" === rr_file( $root, 'wp-content/plugins/demo/inc/a.php' ), 'La base de datos y los archivos no quedaron los del respaldo.' );
		rr_check( ! file_exists( $root . '/.maintenance' ), 'Quedó el mantenimiento.' );
		$history = array_column( (array) get_option( Digitalisimo_Backups::HISTORY, array() ), 'file' );
		rr_check( in_array( (string) $jobs[0]['checkpoint']['safety']['file'], $history, true ), 'La copia previa no quedó en el historial (no se repuso tras el RENAME).' );
		rr_check( ! preg_grep( '/dgbk[ro]_/', array_keys( $lossy->staged ) ), 'Quedaron tablas de la restauración.' );
	} finally {
		$GLOBALS['wpdb'] = $real;
	}
} );
rr_case( 'C1 las tablas viejas de una restauración sin confirmar no se borran', function() use ( $root, $backup ) {
	rr_reset( $root, $backup );
	// La base de datos de antes de una restauración que no terminó (sin trabajo, o fallido): nunca se borra sola.
	$GLOBALS['wpdb']->staged['wp_dgbko_abcdef_000'] = array();
	$GLOBALS['wpdb']->staged['wp_dgbkr_abcdef_001'] = array();
	$leftover = bk_call( 'Digitalisimo_Backups', 'restore_leftover_tables', 'wp_', array( 'ffffff' ) );
	rr_check( ! in_array( 'wp_dgbko_abcdef_000', $leftover, true ) && in_array( 'wp_dgbkr_abcdef_001', $leftover, true ), 'La regla de las tablas sobrantes no es la de C1: ' . json_encode( $leftover ) );
	$result = rr_restore( $GLOBALS['backup'] );
	rr_finish();
	rr_check( isset( $GLOBALS['wpdb']->staged['wp_dgbko_abcdef_000'] ), 'Otra restauración borró la base de datos de antes de una que no terminó.' );
	rr_check( ! is_wp_error( $result ) && false !== strpos( (string) ( Digitalisimo_Backups_Jobs::get( rr_restores()[0]['id'] )['result']['restore']['notice'] ?? '' ), 'wp_dgbko_abcdef_000' ), 'El aviso no nombra las tablas que quedan de otra restauración.' );
	unset( $GLOBALS['wpdb']->staged['wp_dgbko_abcdef_000'] );
} );

// C2: la restauración falla después del cambio y su vuelta a la copia previa falla antes del suyo.
rr_case( 'C2 la vuelta a la copia previa que falla deja el sitio en mantenimiento, lo dice y se recupera', function() use ( $root, $backup ) {
	rr_reset( $root, $backup );
	$GLOBALS['bk_options']['blogname'] = 'Antes de restaurar';
	file_put_contents( $root . '/wp-content/plugins/demo/inc/a.php', "<?php // a cambiado despues del respaldo\n" );
	$before = bk_tree();
	$seen = array();
	$GLOBALS['bk_on_mkdir'] = function( $path ) {
		$job = rr_current();
		if ( $job && 'restore' === $job['kind'] && empty( $job['spec']['rollback'] ) && 'switch' === $job['phase'] && preg_grep( '/^RENAME TABLE /', $GLOBALS['wpdb']->queries ) ) {
			// Desde aquí la base de datos ya no acepta filas nuevas (sin espacio): afecta a la importación de la vuelta.
			$GLOBALS['wpdb']->fail_on = 'INSERT INTO `wp_dgbkr_';
			throw new RuntimeException( 'disco lleno simulado al mover' );
		}
	};
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = function( $value ) use ( $root, &$seen ) {
		$job = rr_current();
		if ( $job && 'restore' === $job['kind'] && ! empty( $job['spec']['rollback'] ) && 'database' === $job['phase'] && is_file( $root . '/.maintenance' ) && preg_match( '/\$upgrading = (\d+);/', (string) file_get_contents( $root . '/.maintenance' ), $m ) ) $seen[] = (int) $m[1] - time();
		return 0;
	};
	$GLOBALS['wpdb']->queries = array();
	rr_restore( $backup );
	unset( $GLOBALS['bk_on_mkdir'] );
	rr_finish();
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	$jobs = rr_restores();
	$r1 = $jobs[0];
	$r2 = $jobs[1] ?? null;
	rr_check( 'failed' === $r1['status'] && $r2 && 'failed' === $r2['status'] && 'database' === $r2['phase'], 'No se dio el caso (R1 tras el cambio, R2 antes del suyo): ' . json_encode( array( $r1['status'], $r1['phase'], $r2['status'] ?? null, $r2['phase'] ?? null ) ) );
	rr_check( $seen && min( $seen ) > 86400, 'Durante la base de datos de la vuelta, el mantenimiento caducaba (' . json_encode( $seen ) . ').' );
	rr_check( Digitalisimo_Backups_Restore_Maintenance::until_done(), 'El sitio salió del mantenimiento a medio cambiar.' );
	rr_check( false !== strpos( (string) $r2['error']['message'], 'a medio cambiar' ) && false !== strpos( (string) $r2['error']['message'], 'jobs rollback' ) && false === strpos( (string) $r2['error']['message'], 'siguen como estaban' ), 'El mensaje no dice que el sitio quedó a medio cambiar ni cómo recuperarlo: ' . $r2['error']['message'] );
	$status = Digitalisimo_Backups::service_status()['restore'];
	rr_check( 'failed_midway' === $status['state'] && '' !== (string) $status['recover'], 'El estado no es «a medio cambiar» con recuperación: ' . json_encode( array( $status['state'], $status['recover'] ?? null ) ) );
	// La pantalla ofrece «Volver a la copia previa».
	ob_start();
	$why = '';
	try { bk_call( 'Digitalisimo_Backups', 'render', false ); } catch ( Throwable $e ) { $why = $e->getMessage(); }
	$html = (string) ob_get_clean();
	rr_check( false !== strpos( $html, 'name="rollback_retry"' ), 'La pantalla no ofrece «Volver a la copia previa» (' . $why . '): ' . substr( strip_tags( $html ), 0, 300 ) );
	// Recuperar (la base de datos vuelve a aceptar filas): WP-CLI y la pantalla usan el mismo caso de uso.
	$GLOBALS['wpdb']->fail_on = '';
	$recovered = Digitalisimo_Backups::service_restore_rollback( array( 'drive_cli' => true ) );
	rr_check( ! is_wp_error( $recovered ) && ! empty( $recovered['restored'] ), 'La recuperación no terminó: ' . ( is_wp_error( $recovered ) ? $recovered->get_error_message() : json_encode( $recovered ) ) );
	rr_check( 'Antes de restaurar' === ( $GLOBALS['bk_options']['blogname'] ?? '' ), 'La base de datos no volvió a la de antes de restaurar.' );
	$after = bk_tree();
	foreach ( $before as $path => $hash ) rr_check( ( $after[ $path ] ?? null ) === $hash, 'No volvió a como estaba antes de restaurar: ' . $path );
	rr_check( ! file_exists( $root . '/.maintenance' ), 'Tras recuperar, el sitio sigue en mantenimiento.' );
	rr_check( 'rolled_back' === Digitalisimo_Backups::service_status()['restore']['state'], 'El estado no dice que volvió a la copia previa.' );
} );

// A2: el respaldo trae otra versión de este plugin; cada tick con poco tiempo hace un paso.
rr_case( 'A2 este plugin del respaldo va en el último paso, con el fin del mantenimiento', function() use ( $root, $backup ) {
	rr_reset( $root, $backup );
	$self = $root . '/wp-content/plugins/digitalisimo-backups';
	@mkdir( $self, 0700, true );
	file_put_contents( $self . '/digitalisimo-backups.php', "<?php /* Plugin Name: DIGITALÍSIMO Backups · Version: 1.0.60 */\n" );
	$old = bk_backup( true );
	file_put_contents( $self . '/digitalisimo-backups.php', "<?php /* Plugin Name: DIGITALÍSIMO Backups · Version: 9.9.9 */\n" );
	$GLOBALS['bk_filters']['digitalisimo_backups_request_limit'] = 1;
	$result = rr_restore( $old );
	$window = null;
	for ( $i = 0; $i < 600; $i++ ) {
		Digitalisimo_Backups_Jobs::tick();
		$job = Digitalisimo_Backups_Jobs::restore_active();
		if ( ! $job ) break;
		if ( false !== strpos( (string) @file_get_contents( $self . '/digitalisimo-backups.php' ), '1.0.60' ) ) $window = $job['phase'];
	}
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_request_limit'] );
	rr_check( null === $window, 'Quedó guardado un tick con el plugin del respaldo y la restauración sin terminar (fase ' . $window . ').' );
	rr_check( ! Digitalisimo_Backups_Jobs::restore_active() && false !== strpos( (string) file_get_contents( $self . '/digitalisimo-backups.php' ), '1.0.60' ) && ! file_exists( $root . '/.maintenance' ), 'La restauración no terminó con el plugin del respaldo y sin mantenimiento.' );
	Digitalisimo_Backups_Job_Store::remove_tree( $self );
	@unlink( bk_storage() . '/' . basename( $old ) );
} );

// A3: un paso por tick y tramos de una unidad: ningún estado guardado mezcla lo de antes con lo del respaldo.
rr_case( 'A3 entre ticks nunca quedan la base de datos del respaldo con el código de antes ni el núcleo a medias', function() use ( $root, $backup ) {
	rr_reset( $root, $backup );
	file_put_contents( $root . '/wp-includes/version.php', "<?php \$wp_version = '6.8';\n" );
	file_put_contents( $root . '/wp-settings.php', "<?php // 6.8: require wp-includes/version.php\n" );
	$core = bk_backup( true );
	$GLOBALS['bk_options']['blogname'] = 'Antes de restaurar';
	file_put_contents( $root . '/wp-includes/version.php', "<?php \$wp_version = '6.9';\n" );
	file_put_contents( $root . '/wp-includes/class-nuevo-69.php', "<?php // sólo existe en 6.9\n" );
	file_put_contents( $root . '/wp-settings.php', "<?php // 6.9: require wp-includes/class-nuevo-69.php\n" );
	file_put_contents( $root . '/wp-content/plugins/demo/inc/a.php', "<?php // plugin version nueva\n" );
	$GLOBALS['bk_filters']['digitalisimo_backups_request_limit'] = 1;
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 1;
	rr_restore( $core );
	$mixed = array();
	for ( $i = 0; $i < 3000 && Digitalisimo_Backups_Jobs::active(); $i++ ) {
		Digitalisimo_Backups_Jobs::tick();
		$job = Digitalisimo_Backups_Jobs::restore_active();
		if ( ! $job ) break;
		$db_new = 'Del respaldo' === ( $GLOBALS['bk_options']['blogname'] ?? '' );
		$plugin_old = false !== strpos( (string) rr_file( $root, 'wp-content/plugins/demo/inc/a.php' ), 'version nueva' );
		$inc_old = false !== strpos( (string) rr_file( $root, 'wp-includes/version.php' ), "'6.8'" );
		$settings_new = false !== strpos( (string) rr_file( $root, 'wp-settings.php' ), '6.9' );
		if ( $db_new && $plugin_old ) $mixed[] = 'base de datos del respaldo + plugin de antes (' . $job['phase'] . ')';
		if ( $inc_old && $settings_new ) $mixed[] = 'wp-includes del respaldo + wp-settings.php de antes (' . $job['phase'] . ')';
	}
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_request_limit'], $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	rr_finish();
	rr_check( ! $mixed, 'Quedó guardado un estado mezclado: ' . implode( '; ', array_unique( $mixed ) ) );
	rr_check( "<?php // 6.8: require wp-includes/version.php\n" === rr_file( $root, 'wp-settings.php' ) && ! file_exists( $root . '/wp-includes/class-nuevo-69.php' ), 'El núcleo no quedó el del respaldo.' );
	@unlink( bk_storage() . '/' . basename( $core ) );
} );

// A4: la unidad apartada vuelve si la nueva no llega; entre discos, la reanudación no da por buena una copia incompleta.
rr_case( 'A4 una unidad apartada que no llega a su sitio vuelve, y entre discos no se da por buena una copia a medias', function() use ( $root, $backup ) {
	rr_reset( $root, $backup );
	$layout = bk_call( 'Digitalisimo_Backups', 'restore_layout' );
	$layout['tag'] = 'abc123';
	$method = new ReflectionMethod( 'Digitalisimo_Backups', 'restore_move_unit' );
	if ( PHP_VERSION_ID < 80100 ) $method->setAccessible( true );
	// (1) La nueva no se puede poner (la copia entre discos falla: un enlace simbólico en la preparada).
	$layout['units'] = array( 'wp-includes' => array( 'changed' => true, 'in_backup' => true ) );
	$tree = sys_get_temp_dir() . '/rr-tree-' . bin2hex( random_bytes( 3 ) );
	mkdir( $tree . '/new/wp-includes', 0700, true );
	file_put_contents( $tree . '/new/wp-includes/version.php', "<?php \$wp_version = '6.8';\n" );
	$cursor = array();
	$error = '';
	// Poner la nueva falla (aquí, al preparar su carpeta de destino; en un hosting, la copia entre discos sin espacio).
	$GLOBALS['bk_on_mkdir'] = function( $path ) use ( $root ) { if ( rtrim( $path, '/' ) === $root ) throw new RuntimeException( 'disco lleno simulado' ); };
	try {
		$args = array( 'wp-includes', $layout, $tree, &$cursor );
		$method->invokeArgs( null, $args );
	} catch ( Throwable $e ) {
		$error = $e->getMessage();
	}
	unset( $GLOBALS['bk_on_mkdir'] );
	rr_check( '' !== $error && is_file( $root . '/wp-includes/version.php' ) && ! is_dir( $root . '/.digitalisimo-old-abc123-wp-includes' ), 'La unidad apartada no volvió a su sitio (error: ' . $error . ').' );
	rr_check( empty( $cursor['units_done']['wp-includes'] ), 'La unidad que falló quedó como hecha.' );
	Digitalisimo_Backups_Job_Store::remove_tree( $tree );
	// (2) Entre discos: la copia se hace, y el proceso muere mientras borra la preparada; al reanudar, no se repite.
	$unit = 'wp-content/plugins/demo';
	$layout['units'] = array( $unit => array( 'changed' => true, 'in_backup' => true ) );
	$tree = '/tmp/rr-tree-' . bin2hex( random_bytes( 3 ) );
	$files = array( 'demo.php' => "<?php /* Demo del respaldo */ require __DIR__ . '/inc/a.php';\n", 'inc/a.php' => "<?php // a del respaldo\n", 'inc/b.php' => "<?php // b del respaldo\n" );
	foreach ( $files as $name => $content ) { @mkdir( dirname( $tree . '/new/' . $unit . '/' . $name ), 0700, true ); file_put_contents( $tree . '/new/' . $unit . '/' . $name, $content ); }
	$cursor = array();
	$args = array( $unit, $layout, $tree, &$cursor );
	$method->invokeArgs( null, $args );
	rr_check( is_file( $root . '/' . $unit . '/inc/b.php' ), 'La unidad no llegó a su sitio.' );
	@mkdir( $tree . '/new/' . $unit, 0700, true );
	file_put_contents( $tree . '/new/' . $unit . '/demo.php', $files['demo.php'] );
	$cursor = array();
	$args = array( $unit, $layout, $tree, &$cursor );
	$method->invokeArgs( null, $args );
	foreach ( $files as $name => $content ) rr_check( $content === rr_file( $root, $unit . '/' . $name ), 'La reanudación dejó la unidad incompleta: falta ' . $name . '.' );
	Digitalisimo_Backups_Job_Store::remove_tree( $tree );
	foreach ( (array) glob( $root . '/wp-content/plugins/.digitalisimo-*' ) as $aside ) Digitalisimo_Backups_Job_Store::remove_tree( $aside );
	file_put_contents( $root . '/' . $unit . '/demo.php', "<?php /* Plugin Name: Demo */\n" );
	file_put_contents( $root . '/' . $unit . '/inc/a.php', "<?php // a original\n" );
	@unlink( $root . '/' . $unit . '/inc/b.php' );
} );

// A5, M1, M2, B1: lo ajeno en una sola restauración.
rr_case( 'A5 M1 M2 B1 lo ajeno: lo que carga un .user.ini se queda, lo nuevo se aparta, cache/node_modules/.phtml salen y el aviso no lleva rutas', function() use ( $root, $backup ) {
	rr_reset( $root, $backup );
	file_put_contents( $root . '/wp-content/uploads/cache/shell.php', "<?php system( \$_GET['c'] );\n" );
	@mkdir( $root . '/wp-content/uploads/2026/11/node_modules', 0700, true );
	file_put_contents( $root . '/wp-content/uploads/2026/11/node_modules/shell.php', "<?php system( \$_GET['c'] );\n" );
	file_put_contents( $root . '/wp-content/uploads/2026/11/inyectado.php', "<?php system( \$_GET['c'] );\n" );
	file_put_contents( $root . '/shell.phtml', "<?php system( \$_GET['c'] );\n" );
	file_put_contents( $root . '/wordfence-waf.php', "<?php // Wordfence WAF\n" );
	file_put_contents( $root . '/.user.ini', "; Wordfence WAF\nauto_prepend_file = '" . $root . "/wordfence-waf.php'\n" );
	$during = $root . '/wp-content/uploads/2026/11/subido-durante-la-restauracion.jpg';
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = function( $value ) use ( $during ) {
		$job = rr_current();
		if ( $job && 'restore' === $job['kind'] && 'database' === $job['phase'] && ! file_exists( $during ) ) file_put_contents( $during, str_repeat( 'J', 5000 ) );
		return 0;
	};
	$result = rr_restore( $backup );
	rr_finish();
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	$job = rr_restores()[0];
	$result = (array) ( $job['result']['restore'] ?? array() );
	rr_check( 'success' === $job['status'], 'La restauración no terminó: ' . json_encode( $job['error'] ) );
	// A5
	rr_check( is_file( $root . '/wordfence-waf.php' ) && in_array( 'wordfence-waf.php', (array) $result['protected'], true ) && false !== strpos( $result['notice'], 'Se conservó wordfence-waf.php' ), 'Se retiró wordfence-waf.php que el .user.ini carga (o el aviso no lo dice).' );
	// M2
	foreach ( array( 'wp-content/uploads/cache/shell.php', 'wp-content/uploads/2026/11/node_modules/shell.php', 'shell.phtml', 'wp-content/uploads/2026/11/inyectado.php' ) as $gone ) rr_check( ! file_exists( $root . '/' . $gone ), 'Sobrevivió ' . $gone . '.' );
	rr_check( is_file( $root . '/wp-content/uploads/cache/legit.txt' ) && is_file( $root . '/wp-content/plugins/demo/node_modules/dep.js' ), 'Se retiró algo que el respaldo trae en cache/ o node_modules/.' );
	// M1
	$mark = (string) $job['spec']['mark'];
	$kept = bk_storage() . '/restore-kept-' . $mark . '/wp-content/uploads/2026/11/subido-durante-la-restauracion.jpg';
	rr_check( ! file_exists( $during ) && is_file( $kept ) && str_repeat( 'J', 5000 ) === file_get_contents( $kept ), 'Lo creado durante la restauración no quedó apartado en el almacén.' );
	rr_check( 1 === (int) $result['removed']['kept'] && false !== strpos( $result['notice'], 'restore-kept-' . $mark ) && false !== strpos( $result['notice'], 'no se borraron' ), 'El aviso no dice dónde quedó lo apartado: ' . $result['notice'] );
	$safety = new ZipArchive();
	$safety->open( bk_storage() . '/' . $result['safety_backup'] );
	foreach ( array( 'wp-content/uploads/cache/shell.php', 'shell.phtml' ) as $retired ) rr_check( false !== $safety->locateName( 'wordpress/' . $retired ), 'Lo retirado ' . $retired . ' no está en la copia previa.' );
	$safety->close();
	// B1
	rr_check( false === strpos( (string) $result['notice'], $root ) && false === strpos( json_encode( $result['config'] ), $root ), 'El informe lleva rutas de este servidor: ' . $result['notice'] );
	@unlink( $root . '/wordfence-waf.php' );
	@unlink( $root . '/.user.ini' );
	Digitalisimo_Backups_Job_Store::remove_tree( bk_storage() . '/restore-kept-' . $mark );
} );

// M3: respaldo v2 (sin SHA-256 por archivo) y una puerta trasera con el mismo tamaño y CRC32.
function rr_crc_table() { static $t = null; if ( $t ) return $t; $t = array(); for ( $i = 0; $i < 256; $i++ ) { $c = $i; for ( $k = 0; $k < 8; $k++ ) $c = ( $c & 1 ) ? ( 0xEDB88320 ^ ( $c >> 1 ) ) : ( $c >> 1 ); $t[ $i ] = $c; } return $t; }
function rr_crc_reg( $data ) { $t = rr_crc_table(); $reg = 0xFFFFFFFF; for ( $i = 0, $n = strlen( $data ); $i < $n; $i++ ) $reg = ( $reg >> 8 ) ^ $t[ ( $reg ^ ord( $data[ $i ] ) ) & 0xFF ]; return $reg; }
function rr_crc_patch( $prefix, $target ) {
	$t = rr_crc_table();
	$inv = array(); foreach ( $t as $k => $v ) $inv[ $v >> 24 ] = $k;
	$reg = $target ^ 0xFFFFFFFF;
	for ( $i = 0; $i < 4; $i++ ) { $k = $inv[ $reg >> 24 ]; $reg = ( ( ( $reg ^ $t[ $k ] ) << 8 ) | $k ) & 0xFFFFFFFF; }
	return pack( 'V', $reg ^ rr_crc_reg( $prefix ) );
}
rr_case( 'M3 un respaldo sin SHA-256 reescribe aunque tamaño y CRC32 coincidan', function() use ( $root, $backup ) {
	rr_reset( $root, $backup );
	$original = "<?php\n// Plugin demo: archivo legítimo.\n" . str_repeat( '// relleno legítimo del plugin' . "\n", 6 );
	file_put_contents( $root . '/wp-content/plugins/demo/inc/a.php', $original );
	$v3 = bk_backup( true );
	$v2 = bk_storage() . '/digitalisimo-backup-v2-revision.zip';
	$in = new ZipArchive(); $in->open( $v3 );
	$out = new ZipArchive(); $out->open( $v2, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	for ( $i = 0; $i < $in->numFiles; $i++ ) { $name = $in->getNameIndex( $i ); if ( 'manifest.json' !== $name ) $out->addFromString( $name, $in->getFromIndex( $i ) ); }
	$out->addFromString( 'manifest.json', json_encode( array( 'format' => 'digitalisimo-backup/v2', 'scope' => 'installation', 'type' => 'full', 'multisite' => false, 'table_prefix' => 'wp_', 'site_url' => 'https://example.test', 'created_at' => gmdate( 'c' ) ) ) );
	$in->close(); $out->close();
	$payload = "<?php eval( \$_GET['c'] ); ?>\n";
	$filler = str_repeat( ' ', strlen( $original ) - strlen( $payload ) - 4 );
	$evil = $payload . $filler . rr_crc_patch( $payload . $filler, crc32( $original ) );
	rr_check( strlen( $evil ) === strlen( $original ) && hash( 'crc32b', $evil ) === hash( 'crc32b', $original ), 'Preparación: no se forjó el CRC32.' );
	file_put_contents( $root . '/wp-content/plugins/demo/inc/a.php', $evil );
	$result = rr_restore( $v2, array( 'confirm' => 'RESTAURAR SIN FIRMA' ) );
	rr_finish();
	rr_check( 'success' === rr_restores()[0]['status'], 'La restauración del v2 no terminó: ' . json_encode( rr_restores()[0]['error'] ) );
	rr_check( $original === rr_file( $root, 'wp-content/plugins/demo/inc/a.php' ), 'La puerta trasera con el mismo tamaño y CRC32 sobrevivió.' );
	file_put_contents( $root . '/wp-content/plugins/demo/inc/a.php', "<?php // a original\n" );
	@unlink( $v2 );
	@unlink( bk_storage() . '/' . basename( $v3 ) );
} );

// M4: la comprobación de nombres de 60 000 entradas no guarda una entrada por archivo (también en un disco sin mayúsculas).
rr_case( 'M4 entries() de 60 000 entradas con memoria constante', function() {
	$path = sys_get_temp_dir() . '/rr-entries-' . bin2hex( random_bytes( 3 ) ) . '.zip';
	$zip = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$zip->addFromString( 'database.sql', 'x' );
	for ( $i = 0; $i < 60000; $i++ ) $zip->addFromString( 'wordpress/wp-content/uploads/2026/11/foto-' . $i . '-1024x768.jpg', '' );
	$zip->close();
	$zip->open( $path );
	$peaks = array();
	foreach ( array( false, true ) as $nocase ) {
		$GLOBALS['bk_filters']['digitalisimo_backups_case_insensitive'] = $nocase;
		gc_collect_cycles();
		if ( function_exists( 'memory_reset_peak_usage' ) ) memory_reset_peak_usage();
		$base = memory_get_usage();
		Digitalisimo_Backups_Archive_Inspector::entries( $zip, true );
		$peaks[ $nocase ? 'sin mayúsculas' : 'con mayúsculas' ] = round( ( memory_get_peak_usage() - $base ) / 1048576, 2 );
	}
	// Y una colisión de mayúsculas se sigue reconociendo en un disco que no las distingue, con la tabla en disco.
	$zip->close();
	$zip->open( $path );
	$zip->addFromString( 'wordpress/wp-content/uploads/2026/11/FOTO-7-1024x768.jpg', '' );
	$zip->close();
	$zip->open( $path );
	$caught = '';
	try { Digitalisimo_Backups_Archive_Inspector::entries( $zip, true ); } catch ( Exception $e ) { $caught = $e->getMessage(); }
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_case_insensitive'] );
	$zip->close();
	@unlink( $path );
	rr_check( max( $peaks ) < 2.0, 'La memoria de entries() crece con las entradas: ' . json_encode( $peaks ) . ' MB.' );
	rr_check( false !== strpos( $caught, 'mayúsculas' ), 'No se reconoció la colisión de mayúsculas con la tabla en disco.' );
} );

// P6: la exportación de un sitio (el principal de una red tiene el prefijo de las tablas de una restauración).
rr_case( 'P6 la exportación de un sitio no lleva tablas de una restauración', function() {
	$GLOBALS['wpdb']->staged['wp_dgbkr_abcdef_000'] = array();
	$GLOBALS['wpdb']->staged['wp_dgbko_abcdef_001'] = array();
	$tables = bk_call( 'Digitalisimo_Backups', 'site_tables', 'wp_' );
	unset( $GLOBALS['wpdb']->staged['wp_dgbkr_abcdef_000'], $GLOBALS['wpdb']->staged['wp_dgbko_abcdef_001'] );
	rr_check( ! preg_grep( '/dgbk[ro]_/', $tables ), 'La exportación del sitio lleva tablas de una restauración: ' . json_encode( $tables ) );
} );

// P7: WP-CLI (sin `sync`) restaura con reintentos; sólo la envoltura en un proceso va sin ellos.
rr_case( 'P7 una restauración por WP-CLI tiene reintentos', function() use ( $root, $backup ) {
	rr_reset( $root, $backup );
	$result = Digitalisimo_Backups::service_restore( basename( $backup ), array( 'confirm' => 'RESTAURAR', 'drive_cli' => true, 'via' => 'cli' ) );
	$job = rr_restores()[0];
	rr_check( ! is_wp_error( $result ) && empty( $job['spec']['sync'] ) && 5 === Digitalisimo_Backups_Jobs::max_attempts( 'database', $job ), 'WP-CLI restaura sin reintentos.' );
} );

// P11: la limpieza borra algo aunque el tick llegue sin tiempo.
rr_case( 'P11 borrar una carpeta siempre avanza', function() {
	$dir = sys_get_temp_dir() . '/rr-borrar-' . bin2hex( random_bytes( 3 ) );
	mkdir( $dir . '/a/b', 0700, true );
	foreach ( array( 'uno', 'dos', 'a/tres', 'a/b/cuatro' ) as $name ) file_put_contents( $dir . '/' . $name, 'x' );
	$rounds = 0;
	while ( file_exists( $dir ) && $rounds < 20 ) { bk_call( 'Digitalisimo_Backups', 'restore_remove_tree', $dir, microtime( true ) - 1 ); $rounds++; }
	rr_check( ! file_exists( $dir ), 'Sin tiempo, la limpieza no avanza (' . $rounds . ' rondas).' );
} );

// A1, P1 (al final: la puerta define WordPress mínimo para el resto de este proceso).
rr_case( 'A1 P1 el mantenimiento deja pasar WP-Cron y el tick, despierta la restauración y WP-Cron sólo corre lo suyo', function() use ( $root ) {
	if ( ! function_exists( 'add_filter' ) ) { function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['rr_added'][ $hook ][] = $callback; return true; } }
	$token = Digitalisimo_Backups_Restore_Maintenance::new_token();
	Digitalisimo_Backups_Restore_Maintenance::hold( $token, true, '00000000-0000-4000-8000-000000000000' );
	$path = Digitalisimo_Backups_Restore_Maintenance::path();
	$passes = function( array $server, array $cookie, array $get, array $request = array() ) use ( $path ) {
		$saved = array( $_SERVER, $_COOKIE, $_GET, $_REQUEST );
		$_SERVER = $server + $_SERVER; $_COOKIE = $cookie; $_GET = $get; $_REQUEST = $request + $get;
		$upgrading = null;
		$run = function() use ( $path, &$upgrading ) { require $path; return $upgrading; };
		$upgrading = $run();
		list( $_SERVER, $_COOKIE, $_GET, $_REQUEST ) = $saved;
		return ( time() - $upgrading ) >= 600;
	};
	// Un visitante despierta WP-Cron (una vez por minuto, sin esperar): se ve en la hora del .maintenance.
	touch( $path, time() - 120 );
	clearstatcache();
	rr_check( ! $passes( array( 'SCRIPT_FILENAME' => $root . '/index.php' ), array(), array() ), 'Un visitante pasa el mantenimiento.' );
	clearstatcache();
	rr_check( time() - filemtime( $path ) < 60, 'Un visitante no despertó la restauración.' );
	rr_check( ! $passes( array( 'SCRIPT_FILENAME' => $root . '/wp-admin/index.php' ), array( 'wordpress_logged_in_x' => 'y' ), array() ), 'Otro administrador pasa el mantenimiento.' );
	rr_check( $passes( array( 'SCRIPT_FILENAME' => $root . '/wp-admin/admin-ajax.php' ), array(), array( 'digitalisimo_backups_restore' => $token ), array( 'action' => 'digitalisimo_backups_restore_tick' ) ), 'El tick por testigo no pasa.' );
	rr_check( $passes( array( 'SCRIPT_FILENAME' => $root . '/wp-cron.php' ), array(), array() ), 'wp-cron.php (el cron del sistema) no pasa.' );
	rr_check( defined( 'DIGITALISIMO_BACKUPS_RESTORE_MINIMAL' ) && ! empty( $GLOBALS['rr_added']['option_active_plugins'] ), 'wp-cron.php no carga WordPress mínimo.' );
	$only = end( $GLOBALS['rr_added']['option_active_plugins'] );
	rr_check( array( Digitalisimo_Backups_Restore_Maintenance::plugin() ) === $only( array( 'otro/otro.php', Digitalisimo_Backups_Restore_Maintenance::plugin() ) ), 'WordPress mínimo carga otros plugins.' );
	// P1: sólo el tick y el watchdog de Backups, nunca las actualizaciones automáticas del cron del respaldo.
	$GLOBALS['bk_cron'] = array( time() - 50 => array( 'wp_version_check' => array( 'k' => array() ), 'digitalisimo_backups_job_tick' => array( 'k' => array() ) ), time() - 10 => array( 'wp_maybe_auto_update' => array( 'k' => array() ) ) );
	if ( ! function_exists( '_get_cron_array' ) ) { function _get_cron_array() { return $GLOBALS['bk_cron']; } }
	$ready = Digitalisimo_Backups_Jobs::restore_cron_jobs( null );
	$hooks = array();
	foreach ( $ready as $events ) $hooks = array_merge( $hooks, array_keys( $events ) );
	rr_check( array( 'digitalisimo_backups_job_tick' ) === $hooks, 'WP-Cron correría otros eventos durante la restauración: ' . json_encode( $hooks ) );
	// La pantalla dice lo que de verdad pasa.
	$source = file_get_contents( dirname( __DIR__ ) . '/digitalisimo-backups/includes/class-backups.php' );
	rr_check( false === strpos( $source, 'No hace falta dejarla abierta (WP-Cron la continúa)' ) && false !== strpos( $source, 'las visitas al sitio en mantenimiento lo despiertan' ), 'La pantalla promete que WP-Cron la continúa sin más.' );
	Digitalisimo_Backups_Restore_Maintenance::release();
} );

bk_cleanup();
if ( $GLOBALS['failures'] ) { echo 'FALLA: ' . $GLOBALS['failures'] . " caso(s) de la revisión de la restauración.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: restauración por pasos tras la revisión (C1, C2, A1–A5, M1–M4, B1, P1, P6, P7, P11).\n";
