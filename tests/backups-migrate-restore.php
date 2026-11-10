<?php
/**
 * La migración aplica el paquete con el motor de «Restaurar» de 1.0.64 (restauración por pasos), sin camino propio
 * (revisión adversaria de 220b2419: H1, H2, H4, H7 y P1), con el entorno de dos instalaciones de
 * tests/backups-migrate.php (base de datos SQLite de verdad en cada sitio):
 *
 *   (h1) con un respaldo a medias en el destino, la migración no reemplaza el sitio: espera (sin gastar intentos) a que
 *        termine, así que el respaldo que entra en el historial es coherente (la base de datos y los archivos de antes);
 *   (h2) «Deshacer la migración» deja el destino idéntico a su copia previa: sin el mu-plugin, el drop-in de wp-content,
 *        el plugin ni el medio del origen, con su medio propio de vuelta; y (P1) en tablas: sin la tabla que sólo tenía el
 *        origen y con la que sólo tenía el destino (que la migración retiró, porque el sitio se reemplaza por completo);
 *   (h4) una migración cuya restauración falla después del cambio vuelve sola a la copia previa y nunca se ofrece para
 *        «Deshacer» (lo hecho después no se pierde);
 *   (h7) «Deshacer» mientras la restauración de la migración sigue (o se reanuda tras una caída) responde 409 con la
 *        salida (esperar), no 404; cancelar la migración en esa fase lo explica con su propio texto; al terminar, se deshace.
 * Cada caso escribe «FALLA (x)» si falla. Con el código de 220b2419 fallan h1, h2, h4 y h7.
 */
require __DIR__ . '/backups-migrate/env.php';

$failures = array();
function rs_case( $label, callable $test ) {
	$only = (string) getenv( 'HARD_ONLY' );
	if ( '' !== $only && 0 !== strpos( $label, $only . ' ' ) ) return;
	try {
		$test();
		echo 'ok   ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures'][] = $label;
		echo 'FALLA (' . $label . '): ' . $e->getMessage() . "\n";
		if ( getenv( 'MIG_TRACE' ) ) echo $e->getTraceAsString() . "\n";
	}
}
const RS_NS = 'digitalisimo-backups/v1';
/** Dos sitios nuevos para cada caso (cada uno parte de cero: su base de datos, sus archivos y su almacén). */
function rs_sites( array $origin_extra = array(), array $destination_extra = array() ) {
	$GLOBALS['mig']['current'] = '';
	$GLOBALS['mig']['sites'] = array();
	$suffix = '-' . bin2hex( random_bytes( 3 ) );
	mig_site( 'origen', 'origen.test', 'wp_', array(
		'wp-content/uploads/2026/11/foto-origen.jpg' => random_bytes( 200 * 1024 ),
		'wp-content/plugins/solo-origen.php' => "<?php /* Plugin Name: Del origen */\n",
	) + $origin_extra, $suffix );
	mig_site( 'destino', 'destino.test', 'dst_', array( 'wp-content/uploads/2026/11/solo-destino.txt' => 'del destino' ) + $destination_extra, $suffix );
	$GLOBALS['mig']['sites']['origen']['options'] += array( 'admin_email' => 'yo@origen.test' );
	$GLOBALS['mig']['sites']['destino']['options'] += array( 'admin_email' => 'yo@destino.test' );
	$GLOBALS['mig']['sites']['origen']['db']->posts = array( 7 => array( 'ID' => '7', 'post_content' => 'contenido-del-origen <img src="https://origen.test/wp-content/uploads/2026/11/foto-origen.jpg">', 'guid' => 'https://origen.test/?p=7' ) );
	$GLOBALS['mig']['sites']['destino']['db']->posts = array( 3 => array( 'ID' => '3', 'post_content' => 'Página del destino', 'guid' => 'https://destino.test/?p=3' ) );
	$GLOBALS['bk_filters']['digitalisimo_backups_migration_chunk'] = 65536;
	$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
	$GLOBALS['bk_filters']['digitalisimo_backups_migration_poll'] = 0;
	$GLOBALS['mig_actions'] = array();
	$GLOBALS['mig']['faults'] = array();
	mig_use( 'origen' );
}
function rs_key() {
	return mig_in( 'origen', function() {
		$reply = mig_rest( 'POST', RS_NS . '/migration/keys', array( 'hours' => 24, 'network' => is_multisite() ) );
		mig_assert( 201 === $reply[0], 'No se generó la clave: ' . json_encode( $reply[1] ) );
		return $reply[1]['key'];
	} );
}
function rs_pull( $key ) {
	mig_use( 'destino' );
	$reply = mig_rest( 'POST', RS_NS . '/migration/pull', array( 'key' => $key, 'confirm' => 'MIGRAR' ) );
	mig_assert( 202 === $reply[0], 'El destino no encoló la migración: ' . json_encode( $reply ) );
	return $reply[1]['id'];
}
/** Avanza la cola del destino hasta $until( $job ) o hasta que la migración termina (sin esperar los reintentos de ella). */
function rs_drive_until( $id, callable $until, $max = 400, $budget = PHP_INT_MAX ) {
	for ( $i = 0; $i < $max; $i++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job ) || $until( $job ) ) return $job;
		foreach ( Digitalisimo_Backups_Jobs::active() as $active ) if ( (int) $active['retry_at'] > time() ) { $active['retry_at'] = 0; Digitalisimo_Backups_Job_Store::write( $active ); }
		Digitalisimo_Backups_Jobs::tick( $budget );
	}
	return Digitalisimo_Backups_Jobs::get( $id );
}
function rs_restores() { return array_values( array_filter( Digitalisimo_Backups_Jobs::all(), function( $job ) { return Digitalisimo_Backups_Jobs::is_restore( $job ); } ) ); }
function rs_tables() { return $GLOBALS['wpdb']->names(); }

rs_case( 'h1 · con un respaldo a medias la migración espera y el respaldo queda coherente', function() {
	rs_sites();
	$key = rs_key();
	// La primera pregunta al origen agota su tiempo: la migración espera 30 s (como en el ensayo con un solo proceso).
	$steps = 0;
	$GLOBALS['mig']['faults'] = array( function( $method, $route, $args, $response ) use ( &$steps ) {
		return null === $response && false !== strpos( $route, '/migrate/source/step' ) && 1 === ++$steps ? new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 600000 milliseconds' ) : null;
	} );
	$migration = rs_pull( $key );
	mig_use( 'destino' );
	for ( $i = 0; $i < 20; $i++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $migration );
		if ( 'remote' === $job['phase'] && (int) $job['retry_at'] > time() ) break;
		Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	}
	mig_assert( 'remote' === $job['phase'] && (int) $job['retry_at'] > time(), 'La migración no quedó esperando al origen.' );
	// Mientras tanto empieza un respaldo en el destino y exporta su base de datos (la de antes de migrar).
	$backup = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'manual', 'via' => 'cron' ) ) )['job'];
	for ( $i = 0; $i < 10 && in_array( Digitalisimo_Backups_Jobs::get( $backup['id'] )['phase'], array( 'preflight', 'database' ), true ); $i++ ) Digitalisimo_Backups_Jobs::tick( 0 );
	mig_assert( ! Digitalisimo_Backups_Job_Store::is_terminal( Digitalisimo_Backups_Jobs::get( $backup['id'] ) ), 'El respaldo no quedó a medias (la prueba lo necesita).' );
	// La migración vuelve a estar lista: llega a la comprobación y espera su turno, sin restaurar.
	$GLOBALS['mig']['faults'] = array();
	$job = Digitalisimo_Backups_Jobs::get( $migration );
	$job['retry_at'] = 0;
	Digitalisimo_Backups_Job_Store::write( $job );
	for ( $i = 0; $i < 200; $i++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $migration );
		if ( 'check' === $job['phase'] && ! empty( $job['checkpoint']['check']['waiting'] ) || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
		// Sólo la migración avanza (el respaldo se queda a medias, como si su proceso estuviera ocupado).
		if ( (int) $job['retry_at'] > time() ) { $job['retry_at'] = 0; Digitalisimo_Backups_Job_Store::write( $job ); }
		$held = Digitalisimo_Backups_Jobs::get( $backup['id'] );
		$held['retry_at'] = time() + 3600;
		Digitalisimo_Backups_Job_Store::write( $held );
		Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	}
	mig_assert( 'check' === $job['phase'] && ! empty( $job['checkpoint']['check']['waiting'] ) && ! rs_restores() && 'Destino' === get_option( 'blogname' ), 'La migración no esperó al respaldo a medias: fase ' . $job['phase'] . ', restauraciones ' . count( rs_restores() ) . ', sitio ' . get_option( 'blogname' ) );
	mig_assert( (int) $job['retry_at'] > time() && empty( $job['attempts']['check'] ) && false !== strpos( (string) $job['progress']['message'], 'Esperando a que termine el respaldo' ), 'La espera gastó intentos o no se explicó: ' . json_encode( array( $job['attempts'], $job['progress']['message'] ) ) );
	// El respaldo termina primero; después la migración.
	$held = Digitalisimo_Backups_Jobs::get( $backup['id'] );
	$held['retry_at'] = 0;
	Digitalisimo_Backups_Job_Store::write( $held );
	$done = mig_drive( $backup['id'], 200 );
	mig_assert( 'success' === $done['status'], 'El respaldo no terminó: ' . json_encode( $done['error'] ) );
	$row = null;
	foreach ( (array) get_option( 'digitalisimo_backups_history', array() ) as $item ) if ( 'manual' === ( $item['policy'] ?? '' ) ) $row = $item;
	$zip = new ZipArchive();
	mig_assert( $row && true === $zip->open( mig_storage() . '/' . $row['file'] ), 'El respaldo no quedó en el historial.' );
	$sql = (string) $zip->getFromName( 'database.sql' );
	$foreign = $zip->locateName( 'wordpress/wp-content/plugins/solo-origen.php' );
	$zip->close();
	mig_assert( false !== strpos( $sql, 'Página del destino' ) && false === strpos( $sql, 'contenido-del-origen' ) && false === $foreign, 'El respaldo registrado mezcla la base de datos de antes de migrar con los archivos del origen.' );
	$job = rs_drive_until( $migration, function() { return false; } );
	mig_assert( 'success' === $job['status'] && 'Origen' === get_option( 'blogname' ), 'La migración no terminó después del respaldo: ' . json_encode( $job['error'] ) );
} );

rs_case( 'h2 · deshacer deja el destino idéntico a su copia previa, en archivos y en tablas', function() {
	rs_sites( array(
		'wp-content/mu-plugins/del-origen.php' => "<?php // mu-plugin del origen: WordPress lo carga siempre\n",
		'wp-content/object-cache.php' => "<?php // drop-in del origen (p. ej. Redis): WordPress lo carga siempre que exista\n",
	) );
	$GLOBALS['mig']['sites']['origen']['db']->pdo->exec( 'CREATE TABLE `wp_solo_origen` (`id` INTEGER PRIMARY KEY, `nota` text)' );
	$GLOBALS['mig']['sites']['origen']['db']->seed( 'wp_solo_origen', array( array( 'id' => 1, 'nota' => 'pedido del origen' ) ) );
	$GLOBALS['mig']['sites']['destino']['db']->pdo->exec( 'CREATE TABLE `dst_solo_destino` (`id` INTEGER PRIMARY KEY, `nota` text)' );
	$GLOBALS['mig']['sites']['destino']['db']->seed( 'dst_solo_destino', array( array( 'id' => 1, 'nota' => 'dato del destino' ) ) );
	$real = $GLOBALS['mig']['sites']['destino']['real'];
	$foreign = array( 'wp-content/mu-plugins/del-origen.php', 'wp-content/object-cache.php', 'wp-content/plugins/solo-origen.php', 'wp-content/uploads/2026/11/foto-origen.jpg' );
	$before = mig_in( 'destino', function() { return array( get_option( 'blogname' ), $GLOBALS['wpdb']->posts ); } );
	$migration = rs_pull( rs_key() );
	$job = rs_drive_until( $migration, function() { return false; } );
	mig_assert( 'success' === $job['status'], 'La migración no terminó: ' . json_encode( $job['error'] ) );
	mig_use( 'destino' );
	$left = array_values( array_filter( $foreign, function( $file ) use ( $real ) { return ! file_exists( $real . '/' . $file ); } ) );
	mig_assert( 'Origen' === get_option( 'blogname' ) && ! $left, 'La migración no llevó al destino el contenido y los archivos del origen: ' . implode( ', ', $left ) );
	// El sitio se reemplaza por completo: la tabla y el medio propios del destino se retiran (están en la copia previa).
	$tables = rs_tables();
	mig_assert( in_array( 'dst_solo_origen', $tables, true ) && ! in_array( 'dst_solo_destino', $tables, true ) && ! file_exists( $real . '/wp-content/uploads/2026/11/solo-destino.txt' ), 'Tras migrar, el destino no tiene exactamente las tablas y los medios del origen: ' . implode( ', ', $tables ) );
	mig_assert( ! preg_grep( '/dgbk[ro]_/', $tables ), 'La migración dejó tablas temporales o apartadas: ' . implode( ', ', preg_grep( '/dgbk[ro]_/', $tables ) ) );
	$undo = mig_rest( 'POST', RS_NS . '/migration/undo', array( 'confirm' => 'DESHACER' ) );
	mig_assert( 200 === $undo[0] && true === $undo[1]['undone'], 'No se deshizo: ' . json_encode( $undo ) );
	mig_assert( $before === array( get_option( 'blogname' ), $GLOBALS['wpdb']->posts ), 'Deshacer no devolvió la base de datos del destino.' );
	$left = array_values( array_filter( $foreign, function( $file ) use ( $real ) { return file_exists( $real . '/' . $file ); } ) );
	mig_assert( ! $left, 'Tras «Deshacer la migración» siguen en el destino archivos del origen: ' . implode( ', ', $left ) );
	mig_assert( 'del destino' === (string) @file_get_contents( $real . '/wp-content/uploads/2026/11/solo-destino.txt' ), 'Deshacer no devolvió el medio propio del destino.' );
	$tables = rs_tables();
	mig_assert( ! in_array( 'dst_solo_origen', $tables, true ) && in_array( 'dst_solo_destino', $tables, true ) && 'dato del destino' === $GLOBALS['wpdb']->value( 'dst_solo_destino', 'nota', 'id = 1' ), 'Tras deshacer, las tablas no son las de la copia previa: ' . implode( ', ', $tables ) );
	mig_assert( ! preg_grep( '/dgbk[ro]_/', $tables ), 'Deshacer dejó tablas temporales o apartadas: ' . implode( ', ', preg_grep( '/dgbk[ro]_/', $tables ) ) );
	mig_assert( null === Digitalisimo_Backups_Migrate::undo_target(), 'La migración deshecha se sigue ofreciendo para deshacer.' );
} );

rs_case( 'h4 · una migración que vuelve sola a la copia previa nunca se ofrece para deshacer', function() {
	// El tema del origen es otro: su carpeta de código cambia entera en el paso del cambio (donde se simula el fallo).
	rs_sites( array( 'wp-content/themes/tema/functions.php' => "<?php // tema del origen\n" ) );
	$migration = rs_pull( rs_key() );
	// Después del cambio de tablas, al poner el código en su sitio, el disco falla (siempre): la restauración agota sus
	// intentos y vuelve sola a la copia previa.
	$GLOBALS['mig_actions']['digitalisimo_backups_restore_move_unit'] = function( $unit, $job ) {
		if ( empty( $job['spec']['rollback'] ) ) throw new RuntimeException( 'disco lleno simulado al mover ' . $unit );
	};
	$job = rs_drive_until( $migration, function() { return false; } );
	$GLOBALS['mig_actions'] = array();
	mig_use( 'destino' );
	mig_assert( 'failed' === $job['status'] && 'restore' === $job['phase'] && false !== strpos( (string) $job['error']['message'], 'volvió solo a su copia previa' ), 'La migración no dijo que el sitio volvió solo a su copia previa: ' . json_encode( array( $job['status'], $job['phase'], $job['error'] ) ) );
	mig_assert( 'Destino' === get_option( 'blogname' ) && 'Página del destino' === ( $GLOBALS['wpdb']->posts[3]['post_content'] ?? '' ), 'El destino no volvió a su copia previa.' );
	mig_assert( null === Digitalisimo_Backups_Migrate::undo_target(), 'Se ofrece «Deshacer» de una migración que no se aplicó.' );
	$status = mig_rest( 'GET', RS_NS . '/migration' );
	mig_assert( 200 === $status[0] && null === $status[1]['undo'], 'El estado ofrece deshacer una migración que no se aplicó: ' . json_encode( $status[1]['undo'] ) );
	// Lo hecho después (una entrada nueva) no se pierde: «Deshacer» responde 404.
	$posts = $GLOBALS['wpdb']->posts;
	$posts[4] = array( 'ID' => '4', 'post_content' => 'Escrita después', 'guid' => 'https://destino.test/?p=4' );
	$GLOBALS['wpdb']->posts = $posts;
	$undo = mig_rest( 'POST', RS_NS . '/migration/undo', array( 'confirm' => 'DESHACER' ) );
	mig_assert( 404 === $undo[0] && isset( $GLOBALS['wpdb']->posts[4] ), 'Deshacer una migración que no se aplicó deshizo el trabajo posterior: ' . json_encode( $undo ) );
	$row = null;
	foreach ( (array) get_option( 'digitalisimo_backups_history', array() ) as $item ) if ( is_array( $item['migration'] ?? null ) ) $row = $item;
	mig_assert( $row && 'rolled_back' === $row['migration']['state'], 'La copia previa no quedó marcada como vuelta atrás: ' . json_encode( $row['migration'] ?? null ) );
} );

rs_case( 'h7 · deshacer mientras la restauración de la migración sigue responde 409 con la salida', function() {
	rs_sites();
	$migration = rs_pull( rs_key() );
	// La restauración de la migración va por los archivos (el sitio aún no cambió) cuando se pide deshacer.
	$job = rs_drive_until( $migration, function() { foreach ( rs_restores() as $restore ) if ( 'files' === $restore['phase'] ) return true; return false; }, 400, 0 );
	mig_assert( ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) && rs_restores(), 'La restauración de la migración no quedó a medias.' );
	mig_use( 'destino' );
	$undo = mig_rest( 'POST', RS_NS . '/migration/undo', array( 'confirm' => 'DESHACER' ) );
	mig_assert( 409 === $undo[0] && false !== strpos( (string) $undo[1]['message'], 'restauración en curso' ) && false !== strpos( (string) $undo[1]['message'], 'espera a que termine' ), 'Deshacer durante la restauración no explicó la salida: ' . json_encode( $undo ) );
	// La fase de la migración que espera su restauración no se cancela, y lo dice con su propio texto.
	$cancel = Digitalisimo_Backups_Jobs::cancel( $migration );
	mig_assert( empty( $cancel['ok'] ) && false !== strpos( (string) $cancel['message'], 'restauración por pasos' ) && false === strpos( (string) $cancel['message'], 'respaldo ya está verificado' ), 'Cancelar la migración en su restauración no lo explicó: ' . json_encode( $cancel ) );
	// Una caída a mitad (el proceso de la restauración murió con su arrendamiento): la restauración se reanuda y termina.
	foreach ( rs_restores() as $restore ) {
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $restore ) ) continue;
		$restore['lease'] = array( 'owner' => 'muerto', 'host' => 'otro-servidor', 'pid' => 0, 'heartbeat_at' => time() - 3600, 'expires_at' => time() - 3000 );
		$restore['status'] = 'running';
		Digitalisimo_Backups_Job_Store::write( $restore );
	}
	$job = rs_drive_until( $migration, function() { return false; } );
	mig_assert( 'success' === $job['status'], 'La migración no terminó tras la caída de su restauración: ' . json_encode( $job['error'] ) );
	$undo = mig_rest( 'POST', RS_NS . '/migration/undo', array( 'confirm' => 'DESHACER' ) );
	mig_assert( 200 === $undo[0] && true === $undo[1]['undone'] && 'Destino' === get_option( 'blogname' ), 'Tras terminar, no se pudo deshacer: ' . json_encode( $undo ) );
} );

echo $failures ? 'Migración con la restauración por pasos con fallos: ' . implode( ', ', $failures ) . "\n" : "Migración con la restauración por pasos: todo correcto.\n";
mig_remove_tree( $GLOBALS['mig_root'] );
exit( $failures ? 1 : 0 );
