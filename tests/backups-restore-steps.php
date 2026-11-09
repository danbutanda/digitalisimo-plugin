<?php
/**
 * Restauración robusta (H2, H3, H4): la restauración es un trabajo por pasos del motor de trabajos.
 *
 *   R1  de punta a punta por tramos: verificación, copia previa, base de datos a tablas temporales con el prefijo del
 *       sitio y un solo RENAME TABLE al final (el sitio se sirve de sus tablas hasta entonces), archivos preparados y
 *       movidos, sitio idéntico al respaldo;
 *   R2  lo que el respaldo no tenía no sobrevive dentro de las raíces que gestiona (un .php inyectado en uploads, un
 *       plugin instalado después), pero sí fuera de ellas (otra aplicación, wp-content/otra, robots.txt), wp-config.php y
 *       lo excluido a propósito (un .git dentro de un plugin, que deja esa unidad archivo a archivo); queda en la copia
 *       previa y en el informe;
 *   R3  muerte real del proceso (exit(): ni finally ni el apagado del motor, como SIGKILL) a mitad de cada fase
 *       —verificación, copia previa, base de datos, archivos, movimiento de archivos y justo después del RENAME— y la
 *       operación siguiente reanuda desde su punto de control; mientras tanto el estado avisa y, antes del cambio, el
 *       sitio sigue intacto; al final, idéntico al respaldo;
 *   R4  un fallo después del cambio encola la vuelta a la copia previa, que deja el sitio como antes de restaurar;
 *   R5  cancelar antes del cambio no toca nada (tablas temporales y área de trabajo retiradas, sin mantenimiento);
 *   R6  cada fase avanza por tramos con punto de control (tope de unidades por tramo, como en T2).
 *
 * Una muerte por proceso: sin argumentos, la prueba se lanza a sí misma (PHP del sistema con proc_open, como la CI) con
 * --muerte=<fase> para cada una; con php-wasm, que no lanza procesos, se corre así cada caso aparte:
 *   for m in inspect safety database files move rename; do php tests/backups-restore-steps.php --muerte=$m; done
 * Deben fallar con el código anterior (E1): la restauración no era un trabajo, no retiraba lo ajeno y no se reanudaba.
 */
$death = '';
foreach ( array_slice( $argv ?? array(), 1 ) as $arg ) if ( 0 === strpos( $arg, '--muerte=' ) ) $death = substr( $arg, 9 );
require __DIR__ . '/lib/backups-env.php';

$failures = 0;
function rs_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function rs_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
$B = 'Digitalisimo_Backups';
$J = 'Digitalisimo_Backups_Jobs';
$root = rtrim( ABSPATH, '/' );
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
foreach ( array(
	'wp-content/plugins/demo/demo.php' => "<?php /* Plugin Name: Demo */\n",
	'wp-content/plugins/demo/inc/a.php' => "<?php // a original\n",
	'wp-content/plugins/devplug/devplug.php' => "<?php /* Plugin Name: Dev */\n",
	'wp-content/themes/tema/style.css' => "/* Theme Name: Tema */\n",
	'wp-content/uploads/2026/11/foto.jpg' => str_repeat( "\xff\xd8\xff", 3000 ),
	'wp-content/uploads/2026/11/nota.txt' => "nota original\n",
) as $name => $content ) { @mkdir( dirname( $root . '/' . $name ), 0700, true ); file_put_contents( $root . '/' . $name, $content ); }
@mkdir( $root . '/wp-content/plugins/devplug/.git', 0700, true );
file_put_contents( $root . '/wp-content/plugins/devplug/.git/config', "[core]\n" );
$GLOBALS['bk_options']['blogname'] = 'Sitio original';
$backup = bk_backup( true );
$expected = bk_tree();
unset( $expected['/wp-content/plugins/devplug/.git'], $expected['/wp-content/plugins/devplug/.git/config'] );

/** Lo que cambia después del respaldo: base de datos y archivos, dentro y fuera de las raíces gestionadas. */
function rs_mutate( $root ) {
	$GLOBALS['bk_options']['blogname'] = 'Cambiado después del respaldo';
	$GLOBALS['bk_options']['opcion_intrusa'] = 'no estaba en el respaldo';
	file_put_contents( $root . '/wp-load.php', "<?php // cambiado\n" );
	file_put_contents( $root . '/wp-content/plugins/demo/inc/a.php', "<?php // a cambiado\n" );
	@unlink( $root . '/wp-content/themes/tema/style.css' );
	file_put_contents( $root . '/wp-content/uploads/2026/11/inyectado.php', "<?php system( \$_GET['c'] );\n" );
	@mkdir( $root . '/wp-content/plugins/nuevo', 0700, true );
	file_put_contents( $root . '/wp-content/plugins/nuevo/nuevo.php', "<?php /* Plugin Name: Nuevo */\n" );
	file_put_contents( $root . '/wp-content/plugins/devplug/extra.php', "<?php // ajeno en una unidad con .git\n" );
	@mkdir( $root . '/otra-app', 0700, true );
	file_put_contents( $root . '/otra-app/index.php', "<?php // otra aplicación\n" );
	@mkdir( $root . '/wp-content/otra', 0700, true );
	file_put_contents( $root . '/wp-content/otra/notas.txt', "fuera de las raíces gestionadas\n" );
	file_put_contents( $root . '/robots.txt', "User-agent: *\n" );
	file_put_contents( $root . '/wp-config.php', "<?php define( 'DB_NAME', 'otra' ); // de este servidor\n" );
}
/** Lo que debe quedar: el respaldo en las raíces gestionadas, lo de fuera intacto, wp-config.php de este servidor. */
function rs_assert_restored( $root, array $expected ) {
	rs_check( 'Sitio original' === ( $GLOBALS['bk_options']['blogname'] ?? '' ) && ! isset( $GLOBALS['bk_options']['opcion_intrusa'] ), 'La base de datos no volvió al respaldo.' );
	$tree = bk_tree();
	foreach ( $expected as $path => $hash ) {
		if ( '/wp-config.php' === $path ) continue;
		rs_check( ( $tree[ $path ] ?? null ) === $hash, 'No volvió al respaldo: ' . $path );
	}
	foreach ( array( '/wp-content/uploads/2026/11/inyectado.php', '/wp-content/plugins/nuevo/nuevo.php', '/wp-content/plugins/devplug/extra.php' ) as $gone ) rs_check( ! isset( $tree[ $gone ] ), 'Sobrevivió lo que el respaldo no tenía: ' . $gone );
	foreach ( array( '/otra-app/index.php', '/wp-content/otra/notas.txt', '/robots.txt', '/wp-content/plugins/devplug/.git/config' ) as $kept ) rs_check( isset( $tree[ $kept ] ), 'Se retiró algo fuera de las raíces gestionadas o excluido a propósito: ' . $kept );
	rs_check( false !== strpos( file_get_contents( $root . '/wp-config.php' ), 'de este servidor' ), 'Se restauró wp-config.php.' );
	rs_check( ! file_exists( $root . '/.maintenance' ), 'El sitio quedó en mantenimiento.' );
	rs_check( ! glob( $root . '/.digitalisimo-restore-*' ), 'Quedó el área de trabajo de los archivos junto a WordPress.' );
	rs_check( ! preg_grep( '/dgbk[ro]_/', array_keys( $GLOBALS['wpdb']->staged ) ), 'Quedaron tablas temporales o viejas: ' . implode( ', ', array_keys( $GLOBALS['wpdb']->staged ) ) );
}
/** Vuelve a dejar el sitio como tras el respaldo (para el caso siguiente). */
function rs_reset( $root, $backup ) {
	foreach ( Digitalisimo_Backups_Jobs::active() as $job ) Digitalisimo_Backups_Job_Store::remove_tree( Digitalisimo_Backups_Job_Store::directory( $job['id'] ) );
	@unlink( $root . '/.maintenance' );
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'], $GLOBALS['bk_on_mkdir'] );
	$GLOBALS['wpdb']->exit_on = $GLOBALS['wpdb']->exit_after = '';
}
/** La restauración por la pantalla (o REST): por tramos, con reintentos, como en la web. */
function rs_restore( $file ) {
	return Digitalisimo_Backups::service_restore( basename( $file ), array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false, 'via' => 'test' ) );
}
/** Hace avanzar la cola hasta que la restauración (o su vuelta a la copia previa) termina. */
function rs_finish() {
	for ( $i = 0; $i < 2000; $i++ ) {
		$active = Digitalisimo_Backups_Jobs::active();
		if ( ! $active ) return;
		Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	}
	throw new RuntimeException( 'La restauración no terminó.' );
}
function rs_last_restore() {
	$last = null;
	foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( 'restore' === $job['kind'] && ( ! $last || (float) $job['queued_at'] >= (float) $last['queued_at'] ) ) $last = $job;
	return $last;
}
/**
 * SIGKILL dentro de este proceso: el apagado del motor (registrado antes por el primer respaldo) no debe anotar nada, así
 * que se vacía su paso en curso y se sale sin finally; el arrendamiento queda en el estado del trabajo.
 */
function rs_kill( $phase ) {
	file_put_contents( $GLOBALS['bk_root'] . '-muerte.json', json_encode( array( 'phase' => $phase, 'job' => Digitalisimo_Backups_Jobs::current_id() ) ) );
	$current = new ReflectionProperty( 'Digitalisimo_Backups_Jobs', 'current' );
	if ( PHP_VERSION_ID < 80100 ) $current->setAccessible( true );
	$current->setValue( null, null );
	exit;
}
/** Muere el proceso en la unidad $after de la fase $phase (exit(): sin finally ni apagado del motor). */
function rs_die_in( $phase, $after = 2, $step = '' ) {
	$count = 0;
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = function( $value ) use ( $phase, $after, $step, &$count ) {
		$id = Digitalisimo_Backups_Jobs::current_id();
		$job = '' !== $id ? Digitalisimo_Backups_Jobs::get( $id ) : null;
		if ( ! $job || 'restore' !== $job['kind'] || $phase !== $job['phase'] ) return 4;
		if ( '' !== $step && $step !== (string) ( $job['checkpoint'][ $phase ]['step'] ?? $job['checkpoint'][ $phase ]['sub'] ?? '' ) ) return 4;
		if ( ++$count >= $after ) rs_kill( $phase );
		return 4;
	};
}

if ( '' !== $death ) {
	// Un caso de muerte: el proceso muere y la operación siguiente (otra petición) la reanuda.
	rs_mutate( $root );
	$GLOBALS['wpdb']->queries = array();
	register_shutdown_function( function() use ( $death, $root, $expected, $J ) {
		rs_case( 'muerte a mitad de «' . $death . '» y reanudación', function() use ( $death, $root, $expected, $J ) {
			rs_check( is_file( $GLOBALS['bk_root'] . '-muerte.json' ), 'El proceso no murió donde debía (' . $death . ').' );
			unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'], $GLOBALS['bk_filters']['digitalisimo_backups_job_heartbeat'], $GLOBALS['bk_on_exit'] );
			$job = rs_last_restore();
			rs_check( $job && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) && ! empty( $job['lease'] ), 'El trabajo de la restauración no quedó con el arrendamiento del proceso muerto.' );
			$status = Digitalisimo_Backups::service_status();
			rs_check( ! empty( $status['restoring'] ) && '' !== $status['restore']['notice'] && false !== strpos( implode( ' ', $status['problems'] ), 'restauración en curso' ), 'El estado no avisa de la restauración sin terminar: ' . json_encode( $status['restore'] ) );
			if ( in_array( $death, array( 'inspect', 'safety', 'database', 'files' ), true ) ) {
				rs_check( 'Cambiado después del respaldo' === ( $GLOBALS['bk_options']['blogname'] ?? '' ) && is_file( $root . '/wp-content/uploads/2026/11/inyectado.php' ), 'Antes del cambio, la muerte dejó el sitio tocado.' );
				rs_check( ! preg_grep( '/^RENAME TABLE /', $GLOBALS['wpdb']->queries ), 'Hubo un RENAME antes del cambio.' );
			}
			if ( in_array( $death, array( 'database', 'files' ), true ) ) rs_check( is_file( $root . '/.maintenance' ) && false !== strpos( file_get_contents( $root . '/.maintenance' ), 'DIGITALISIMO_BACKUPS_RESTORE' ), 'Durante la base de datos y los archivos el sitio no está en mantenimiento.' );
			rs_finish();
			$job = Digitalisimo_Backups_Jobs::get( $job['id'] );
			rs_check( 'success' === $job['status'], 'La restauración no terminó tras la muerte: ' . json_encode( $job['error'] ) );
			rs_check( preg_grep( '/^(interrupted|claimed)$/', array_column( Digitalisimo_Backups_Job_Store::events( $job['id'] ), 'type' ) ) && in_array( 'interrupted', array_column( Digitalisimo_Backups_Job_Store::events( $job['id'] ), 'type' ), true ), 'El detector no reconoció la muerte.' );
			rs_assert_restored( $root, $expected );
			rs_check( 1 === count( preg_grep( '/^RENAME TABLE /', $GLOBALS['wpdb']->queries ) ), 'Las tablas no cambiaron con un solo RENAME TABLE.' );
			if ( 'rename' === $death ) {
				rs_check( in_array( 'dgbk-restore-test-history', array_column( (array) $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ], 'file' ), true ), 'El diario no devolvió el historial tras morir después del RENAME.' );
			}
		} );
		bk_cleanup();
		@unlink( $GLOBALS['bk_root'] . '-muerte.json' );
		echo $GLOBALS['failures'] ? "FALLA: restauración con muerte en «{$death}».\n" : "DIGITALÍSIMO Backups: restauración muerta en «{$death}» reanudada desde su punto de control e idéntica al respaldo.\n";
	} );
	if ( 'rename' === $death ) {
		// Muere justo después del RENAME TABLE, antes de reponer historial, llavero y credenciales: lo hace el diario.
		$GLOBALS['bk_options'][ $B::HISTORY ][] = array( 'file' => 'dgbk-restore-test-history', 'scope' => 'installation', 'destination' => 'local' );
		$GLOBALS['wpdb']->exit_after = 'RENAME TABLE ';
		$GLOBALS['bk_on_exit'] = function() { rs_kill( 'rename' ); };
	} elseif ( 'move' === $death ) {
		rs_die_in( 'switch', 2, 'move' );
	} else {
		rs_die_in( $death, 2 );
	}
	if ( 'safety' === $death ) {
		// La muerte cae dentro de la exportación de la base de datos de la copia previa (su latido), con el SQL a medias.
		$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 4;
		$GLOBALS['bk_filters']['digitalisimo_backups_job_heartbeat'] = function( $value ) {
			$job = Digitalisimo_Backups_Jobs::get( Digitalisimo_Backups_Jobs::current_id() );
			if ( $job && 'restore' === $job['kind'] && 'safety' === $job['phase'] && 'database' === ( $job['checkpoint']['safety']['sub'] ?? '' ) ) rs_kill( 'safety' );
			return $value;
		};
	}
	$result = rs_restore( $backup );
	echo 'FALLA: la restauración no murió en «' . $death . '»: ' . json_encode( is_wp_error( $result ) ? $result->get_error_message() : $result ) . "\n";
	exit;
}

// R1–R2, R6: de punta a punta, por tramos de 3 unidades.
rs_case( 'R1/R2/R6 restauración por tramos, tablas temporales con un RENAME y archivos ajenos retirados sólo en las raíces gestionadas', function() use ( $root, $backup, $expected, $J ) {
	rs_mutate( $root );
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = 3;
	$GLOBALS['wpdb']->queries = array();
	$result = rs_restore( $backup );
	rs_check( ! is_wp_error( $result ) && ! empty( $result['restored'] ), 'La restauración no terminó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $result ) ) );
	rs_assert_restored( $root, $expected );
	$queries = $GLOBALS['wpdb']->queries;
	$renames = array_values( preg_grep( '/^RENAME TABLE /', $queries ) );
	rs_check( 1 === count( $renames ) && preg_match( '/`wp_options` TO `wp_dgbko_[0-9a-f]{6}_\d{3}`/', $renames[0] ) && preg_match( '/`wp_dgbkr_[0-9a-f]{6}_\d{3}` TO `wp_options`/', $renames[0] ) && preg_match( '/`wp_dgbkr_[0-9a-f]{6}_\d{3}` TO `wp_posts`/', $renames[0] ), 'Las tablas no cambiaron de una vez con un solo RENAME TABLE: ' . json_encode( $renames ) );
	$at = array_search( $renames[0], $queries, true );
	foreach ( array_slice( $queries, 0, $at ) as $query ) rs_check( ! preg_match( '/^(INSERT INTO|DROP TABLE IF EXISTS|CREATE TABLE) `wp_(options|posts)`/', $query ), 'Antes del cambio se escribió en una tabla del sitio: ' . substr( $query, 0, 80 ) );
	rs_check( preg_grep( '/^INSERT INTO `wp_dgbkr_/', $queries ), 'La base de datos no se importó a tablas temporales con el prefijo del sitio.' );
	// Lo retirado está en el informe y en la copia previa.
	$removed = (array) ( $result['removed']['sample'] ?? array() );
	foreach ( array( 'wp-content/uploads/2026/11/inyectado.php', 'wp-content/plugins/nuevo/nuevo.php', 'wp-content/plugins/devplug/extra.php' ) as $path ) rs_check( in_array( $path, $removed, true ), 'El informe no nombra lo retirado: ' . $path . ' · ' . json_encode( $removed ) );
	rs_check( ! array_filter( $removed, function( $path ) { return 0 === strpos( $path, 'otra-app/' ) || 'robots.txt' === $path || 0 === strpos( $path, 'wp-content/otra/' ) || false !== strpos( $path, '.git' ); } ), 'Se retiró algo fuera de las raíces gestionadas.' );
	$safety = new ZipArchive();
	rs_check( true === $safety->open( bk_storage() . '/' . $result['safety_backup'] ) && false !== $safety->locateName( 'wordpress/wp-content/uploads/2026/11/inyectado.php' ) && false !== $safety->locateName( 'wordpress/wp-content/plugins/nuevo/nuevo.php' ), 'Lo retirado no quedó en la copia previa.' );
	$safety->close();
	rs_check( false !== strpos( (string) $result['notice'], 'Se retiraron 3 archivo' ), 'El aviso no dice cuántos archivos se retiraron: ' . $result['notice'] );
	// Cada fase avanzó por tramos con su punto de control.
	$job = rs_last_restore();
	$slices = array();
	foreach ( Digitalisimo_Backups_Job_Store::events( $job['id'] ) as $event ) if ( 'phase' === $event['type'] && ( $event['phase'] ?? '' ) === ( $event['next'] ?? '' ) ) $slices[ $event['phase'] ] = ( $slices[ $event['phase'] ] ?? 0 ) + 1;
	foreach ( array( 'inspect', 'safety', 'database', 'files', 'switch' ) as $phase ) rs_check( ( $slices[ $phase ] ?? 0 ) >= 2, 'La fase «' . $phase . '» no avanzó por tramos: ' . json_encode( $slices ) );
	rs_check( 'none' === Digitalisimo_Backups::service_status()['restore']['state'], 'Tras una restauración terminada, el estado sigue avisando.' );
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
} );

// R4: un fallo después del cambio (al mover los archivos) vuelve a la copia previa.
rs_case( 'R4 un fallo tras el cambio vuelve a la copia previa entera', function() use ( $root, $backup, $J ) {
	rs_reset( $root, $backup );
	rs_mutate( $root );
	$before = bk_tree();
	$options = $GLOBALS['bk_options'];
	$GLOBALS['wpdb']->queries = array();
	$GLOBALS['bk_on_mkdir'] = function( $path ) {
		$job = Digitalisimo_Backups_Jobs::get( Digitalisimo_Backups_Jobs::current_id() );
		if ( $job && 'restore' === $job['kind'] && empty( $job['spec']['rollback'] ) && 'switch' === $job['phase'] && 'move' === ( $job['checkpoint']['switch']['step'] ?? '' ) ) throw new RuntimeException( 'disco lleno simulado al mover' );
	};
	$result = rs_restore( $backup );
	unset( $GLOBALS['bk_on_mkdir'] );
	rs_finish();
	$jobs = array_values( array_filter( Digitalisimo_Backups_Jobs::all(), function( $job ) { return 'restore' === $job['kind']; } ) );
	$failed = null; $rollback = null;
	foreach ( $jobs as $job ) { if ( 'failed' === $job['status'] && empty( $job['spec']['rollback'] ) ) $failed = $job; if ( ! empty( $job['spec']['rollback'] ) ) $rollback = $job; }
	rs_check( $failed && false !== strpos( (string) $failed['error']['message'], 'Se está restaurando la copia previa' ), 'La restauración fallida no anunció la vuelta a la copia previa.' );
	rs_check( $rollback && 'success' === $rollback['status'] && $failed['id'] === $rollback['spec']['rollback']['of'], 'La vuelta a la copia previa no terminó: ' . json_encode( $rollback['error'] ?? null ) );
	rs_check( 2 === count( preg_grep( '/^RENAME TABLE /', $GLOBALS['wpdb']->queries ) ), 'La vuelta a la copia previa no cambió las tablas otra vez.' );
	rs_check( 'Cambiado después del respaldo' === ( $GLOBALS['bk_options']['blogname'] ?? '' ) && 'no estaba en el respaldo' === ( $GLOBALS['bk_options']['opcion_intrusa'] ?? '' ), 'La base de datos no volvió a la de antes de restaurar.' );
	$after = bk_tree();
	foreach ( $before as $path => $hash ) rs_check( ( $after[ $path ] ?? null ) === $hash, 'El archivo no volvió a como estaba antes de restaurar: ' . $path );
	rs_check( ! file_exists( $root . '/.maintenance' ), 'Tras volver a la copia previa el sitio quedó en mantenimiento.' );
	$status = Digitalisimo_Backups::service_status()['restore'];
	rs_check( 'none' === $status['state'] || 'failed_midway' === $status['state'], 'El estado no describe la restauración fallida: ' . json_encode( $status ) );
	$view = Digitalisimo_Backups::service_job_view( $failed );
	rs_check( $rollback['id'] === $view['restore']['followed_by'], 'La vista del trabajo fallido no nombra su vuelta a la copia previa.' );
	$GLOBALS['bk_options'] = $options;
} );

// R5: cancelar en la base de datos no toca nada.
rs_case( 'R5 cancelar antes del cambio no toca el sitio', function() use ( $root, $backup, $J ) {
	rs_reset( $root, $backup );
	$before = bk_tree();
	$options = $GLOBALS['bk_options'];
	$cancelled = false;
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = function( $value ) use ( &$cancelled ) {
		$id = Digitalisimo_Backups_Jobs::current_id();
		$job = '' !== $id ? Digitalisimo_Backups_Jobs::get( $id ) : null;
		if ( ! $cancelled && $job && 'restore' === $job['kind'] && 'database' === $job['phase'] ) { $cancelled = true; Digitalisimo_Backups_Job_Store::request_cancel( $id ); }
		return 2;
	};
	$GLOBALS['wpdb']->queries = array();
	$result = rs_restore( $backup );
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	$job = rs_last_restore();
	rs_check( $cancelled && 'cancelled' === $job['status'] && is_wp_error( $result ), 'La restauración no se canceló: ' . $job['status'] );
	rs_check( ! preg_grep( '/^RENAME TABLE /', $GLOBALS['wpdb']->queries ) && ! preg_grep( '/dgbk[ro]_/', array_keys( $GLOBALS['wpdb']->staged ) ), 'Cancelar dejó tablas temporales o cambió las del sitio.' );
	rs_check( bk_tree() === $before && $GLOBALS['bk_options']['blogname'] === $options['blogname'] && ! file_exists( $root . '/.maintenance' ) && ! is_dir( Digitalisimo_Backups_Job_Store::directory( $job['id'] ) . '/tree' ), 'Cancelar tocó el sitio o dejó el área de trabajo.' );
	rs_check( false !== strpos( Digitalisimo_Backups_Jobs::cancel( $job['id'] )['message'], 'ya terminó' ), 'Un trabajo cancelado se pudo cancelar otra vez.' );
} );

if ( ! $GLOBALS['failures'] && function_exists( 'proc_open' ) && '' !== PHP_BINARY && 'Unknown' !== PHP_OS_FAMILY ) {
	// R3: cada muerte en su proceso (exit() no deja seguir en el mismo).
	foreach ( array( 'inspect', 'safety', 'database', 'files', 'move', 'rename' ) as $case ) {
		$process = @proc_open( array( PHP_BINARY, __FILE__, '--muerte=' . $case ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		if ( ! is_resource( $process ) ) { echo "R3 OMITIDA: no se pudo lanzar otro proceso de PHP.\n"; break; }
		$output = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		proc_close( $process );
		echo $output;
		if ( preg_match( '/FALLA|Fatal error|Uncaught/', $output ) ) $GLOBALS['failures']++;
	}
} elseif ( ! $GLOBALS['failures'] ) {
	echo "R3 (muertes) se corre aparte con php-wasm: --muerte=inspect|safety|database|files|move|rename\n";
}
bk_cleanup();
if ( $GLOBALS['failures'] ) { echo 'FALLA: ' . $GLOBALS['failures'] . " caso(s) de la restauración robusta.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: restauración por pasos con tablas temporales y un RENAME, archivos ajenos retirados sólo en las raíces gestionadas, vuelta a la copia previa, cancelación y tramos con punto de control.\n";
