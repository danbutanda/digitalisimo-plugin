<?php
/**
 * Tablas apartadas por la migración y su «Deshacer» (revisión de cierre de a7e72bcb: K1, K3), con el entorno de dos
 * instalaciones de tests/backups-migrate.php (base de datos SQLite de verdad en cada sitio):
 *
 *   (k1a) registro: al migrar, el juego de tablas propias que el paquete no trae queda anotado (qué migración, desde
 *         dónde, cuándo, su copia previa y su tamaño) y el aviso dice cuándo se borran; al confirmarse «Deshacer», las
 *         tablas del origen que apartó se borran ya (sus datos no se quedan en el destino) y el registro de la migración
 *         lo anota; las de la migración siguen hasta su plazo;
 *   (k1b) plazo: el vigilante horario del motor de trabajos (Digitalisimo_Backups_Jobs::watchdog()) no las borra antes
 *         de 7 días ni con el filtro `digitalisimo_backups_migration_kept_days` más largo; pasado el plazo, sí, salvo que
 *         su copia previa ya no esté en el almacén (nunca se borra solo lo único que queda de esas tablas); consultar el
 *         estado (GET /migration) nunca borra;
 *   (k1c) nunca otra tabla: las de una restauración en curso esperan; una entrada del registro alterada con tablas vivas,
 *         de otra marca o con una marca inválida no borra nada; una tabla con nombre de apartada fuera del registro se
 *         lista como `unregistered` y nunca se toca;
 *   (k1d) «Borrar ya» por la pantalla (tarjeta «Tablas apartadas», admin-post con nonce), REST (`POST /migration/cleanup`
 *         con `dry_run`) y WP-CLI (`migration cleanup [--dry-run] [--yes]`); en una red, borrar pide el alcance de red
 *         (listar no) y la pantalla de la red lo da;
 *   (k1e) las que apartó una restauración sin registro (1.0.66–1.0.70) se toman de su trabajo (la consulta no escribe; el
 *         vigilante las anota); sin su trabajo, nunca se tocan;
 *   (k3)  durante una migración, el nombre de la fase del paso en curso (el del mensaje de una muerte de PHP) es el de la
 *         migración («Descarga»), no el interno.
 * Cada caso escribe «FALLA (x)» si falla. Con el código de de091e03 fallan todos.
 */
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-migrate/env.php';
require_once __DIR__ . '/../digitalisimo-backups/includes/class-backups-cli.php';

$failures = array();
function kp_case( $label, callable $test ) {
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
const KP_NS = 'digitalisimo-backups/v1';
const KP_STATE = 'digitalisimo_backups_vault_migration';
const KP_CMD = 'digitalisimo-backups migration';
/** Dos sitios nuevos: el origen trae una tabla que el destino no tiene y el destino tiene una que el origen no trae. */
function kp_sites() {
	$GLOBALS['mig']['current'] = '';
	$GLOBALS['mig']['sites'] = array();
	$suffix = '-' . bin2hex( random_bytes( 3 ) );
	mig_site( 'origen', 'origen.test', 'wp_', array( 'wp-content/plugins/solo-origen.php' => "<?php /* Plugin Name: Del origen */\n" ), $suffix );
	mig_site( 'destino', 'destino.test', 'dst_', array(), $suffix );
	$GLOBALS['mig']['sites']['origen']['db']->posts = array( 7 => array( 'ID' => '7', 'post_content' => 'contenido-del-origen', 'guid' => 'https://origen.test/?p=7' ) );
	$GLOBALS['mig']['sites']['destino']['db']->posts = array( 3 => array( 'ID' => '3', 'post_content' => 'Página del destino', 'guid' => 'https://destino.test/?p=3' ) );
	$GLOBALS['mig']['sites']['origen']['db']->pdo->exec( 'CREATE TABLE `wp_solo_origen` (`id` INTEGER PRIMARY KEY, `nota` text)' );
	$GLOBALS['mig']['sites']['origen']['db']->seed( 'wp_solo_origen', array( array( 'id' => 1, 'nota' => 'dato-personal-del-origen' ) ) );
	$GLOBALS['mig']['sites']['destino']['db']->pdo->exec( 'CREATE TABLE `dst_solo_destino` (`id` INTEGER PRIMARY KEY, `nota` text)' );
	$GLOBALS['mig']['sites']['destino']['db']->seed( 'dst_solo_destino', array( array( 'id' => 1, 'nota' => 'dato del destino' ) ) );
	$GLOBALS['bk_filters'] = array( 'digitalisimo_backups_migration_chunk' => 65536, 'digitalisimo_backups_job_backoff' => 0, 'digitalisimo_backups_migration_poll' => 0 );
	$GLOBALS['mig_actions'] = array();
	$GLOBALS['mig']['faults'] = array();
	mig_use( 'origen' );
}
/** Migra del origen al destino hasta terminar y deja el destino en uso. Devuelve el trabajo de la migración. */
function kp_migrated() {
	kp_sites();
	$key = mig_in( 'origen', function() {
		$reply = mig_rest( 'POST', KP_NS . '/migration/keys', array( 'hours' => 24 ) );
		mig_assert( 201 === $reply[0], 'No se generó la clave: ' . json_encode( $reply[1] ) );
		return $reply[1]['key'];
	} );
	mig_use( 'destino' );
	$reply = mig_rest( 'POST', KP_NS . '/migration/pull', array( 'key' => $key, 'confirm' => 'MIGRAR' ) );
	mig_assert( 202 === $reply[0], 'El destino no encoló la migración: ' . json_encode( $reply ) );
	$job = kp_drive( $reply[1]['id'] );
	mig_assert( 'success' === $job['status'] && 'Origen' === get_option( 'blogname' ), 'La migración no terminó: ' . json_encode( $job['error'] ) );
	return $job;
}
function kp_drive( $id, $max = 400 ) {
	for ( $i = 0; $i < $max; $i++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) return $job;
		foreach ( Digitalisimo_Backups_Jobs::active() as $active ) if ( (int) $active['retry_at'] > time() ) { $active['retry_at'] = 0; Digitalisimo_Backups_Job_Store::write( $active ); }
		Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	}
	return Digitalisimo_Backups_Jobs::get( $id );
}
function kp_tables() { return $GLOBALS['wpdb']->names(); }
function kp_aside() { return array_values( preg_grep( '/dgbkk_[0-9a-f]{6}_9[0-9]{3}$/', kp_tables() ) ); }
/** Un valor de una tabla apartada (`…dgbkk_<marca>_9…`), o null. */
function kp_kept_value( $value ) {
	foreach ( kp_aside() as $table ) foreach ( $GLOBALS['wpdb']->rows( $table ) as $row ) if ( in_array( $value, array_map( 'strval', $row ), true ) ) return $table;
	return null;
}
function kp_registry() { $state = get_option( KP_STATE, array() ); return is_array( $state['kept'] ?? null ) ? $state['kept'] : array(); }
/** Hace que el juego $tag se apartara hace $days días. */
function kp_age( $tag, $days ) {
	$state = get_option( KP_STATE, array() );
	$state['kept'][ $tag ]['at'] = time() - (int) round( $days * 86400 );
	update_option( KP_STATE, $state );
}
function kp_restore_of( array $migration ) { return Digitalisimo_Backups_Jobs::get( (string) $migration['result']['restore'] ); }

kp_case( 'k1a · registro al migrar y, al confirmarse «Deshacer», las tablas del origen se borran ya', function() {
	$migration = kp_migrated();
	mig_assert( method_exists( 'Digitalisimo_Backups_Migrate', 'kept_tables' ), 'No hay registro de tablas apartadas.' );
	$restore = kp_restore_of( $migration );
	$aside = kp_aside();
	mig_assert( 1 === count( $aside ) && null !== kp_kept_value( 'dato del destino' ) && ! in_array( 'dst_solo_destino', kp_tables(), true ), 'La migración no apartó la tabla propia del destino: ' . implode( ', ', kp_tables() ) );
	$status = mig_rest( 'GET', KP_NS . '/migration' );
	$kept = $status[1]['kept_tables'] ?? null;
	mig_assert( 200 === $status[0] && is_array( $kept ) && 1 === count( $kept['sets'] ) && 1 === $kept['count'], 'GET /migration no lista las tablas apartadas: ' . json_encode( $kept ) );
	$set = $kept['sets'][0];
	$at = strtotime( $set['at'] );
	mig_assert( 'migration' === $set['kind'] && false !== strpos( $set['label'], 'origen.test' ) && array( array( 'table' => $aside[0], 'was' => 'dst_solo_destino' ) ) === $set['tables'], 'El juego no dice qué migración lo apartó ni qué tablas: ' . json_encode( $set ) );
	mig_assert( (string) $restore['spec']['safety'] === $set['safety'] && Digitalisimo_Backups_Migrate::safety_of( (string) $migration['id'] ) === $set['safety'], 'El juego no anota la copia previa de su migración: ' . json_encode( $set ) );
	mig_assert( is_int( $set['bytes'] ) && $set['bytes'] >= strlen( '1dato del destino' ) && abs( $at - time() ) < 600 && strtotime( $set['expires_at'] ) === $at + 7 * 86400, 'El juego no anota su tamaño, su fecha o su plazo de 7 días: ' . json_encode( $set ) );
	$warnings = implode( ' ', (array) $migration['warnings'] );
	mig_assert( false !== strpos( $warnings, 'Se borran solas en 7 días' ) && false !== strpos( $warnings, 'migration cleanup' ) && false === strpos( $warnings, 'bórralas cuando no las necesites' ), 'El aviso de la migración no dice cuándo se borran las tablas apartadas: ' . $warnings );
	// «Deshacer»: aparta la tabla que trajo el origen (con sus datos) y la borra en cuanto el deshacer termina.
	$undo = mig_rest( 'POST', KP_NS . '/migration/undo', array( 'confirm' => 'DESHACER' ) );
	mig_assert( 200 === $undo[0] && true === $undo[1]['undone'] && 'Destino' === get_option( 'blogname' ), 'No se deshizo: ' . json_encode( $undo ) );
	mig_assert( null === kp_kept_value( 'dato-personal-del-origen' ) && ! in_array( 'dst_solo_origen', kp_tables(), true ), 'Tras «Deshacer», los datos del origen siguen en el destino: ' . implode( ', ', kp_tables() ) );
	mig_assert( false !== strpos( (string) $undo[1]['notice'], 'se borraron al terminar el deshacer' ), 'El aviso del deshacer no dice que borró las tablas del origen: ' . $undo[1]['notice'] );
	// Las de la migración siguen hasta su plazo, y el registro sólo las tiene a ellas.
	$kept = Digitalisimo_Backups_Migrate::kept_tables();
	mig_assert( array( $aside[0] ) === kp_aside() && 1 === count( $kept['sets'] ) && 'migration' === $kept['sets'][0]['kind'] && 1 === count( kp_registry() ), 'Tras «Deshacer», el registro no es el de la migración: ' . json_encode( array( kp_registry(), kp_aside() ) ) );
	$events = array_column( Digitalisimo_Backups_Migrate::public_log( 50 ), 'event' );
	mig_assert( in_array( 'kept_dropped', $events, true ), 'El registro de la migración no anota el borrado de las tablas del origen: ' . implode( ', ', $events ) );
} );

kp_case( 'k1b · el vigilante las borra pasado el plazo y sólo con su copia previa en el almacén', function() {
	kp_migrated();
	$tag = (string) key( kp_registry() );
	$aside = kp_aside();
	mig_assert( '' !== $tag && 1 === count( $aside ), 'La migración no dejó su juego en el registro.' );
	Digitalisimo_Backups_Jobs::watchdog();
	kp_age( $tag, 6.9 );
	Digitalisimo_Backups_Jobs::watchdog();
	mig_assert( $aside === kp_aside() && isset( kp_registry()[ $tag ] ), 'Se borraron antes del plazo.' );
	// El filtro alarga el plazo.
	$GLOBALS['bk_filters']['digitalisimo_backups_migration_kept_days'] = 30;
	kp_age( $tag, 8 );
	Digitalisimo_Backups_Jobs::watchdog();
	mig_assert( $aside === kp_aside() && 30 === Digitalisimo_Backups_Migrate::kept_tables()['days'], 'El filtro digitalisimo_backups_migration_kept_days no alargó el plazo.' );
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_migration_kept_days'] );
	// Sin su copia previa en el almacén, nunca se borran solas (y se dice por qué).
	$safety = mig_storage() . '/' . kp_registry()[ $tag ]['safety'];
	mig_assert( is_file( $safety ), 'La copia previa de la migración no está en el almacén (la prueba la necesita).' );
	rename( $safety, $safety . '.fuera' );
	Digitalisimo_Backups_Jobs::watchdog();
	$set = Digitalisimo_Backups_Migrate::kept_tables()['sets'][0] ?? array();
	mig_assert( $aside === kp_aside() && '' === ( $set['expires_at'] ?? null ) && false !== strpos( (string) ( $set['note'] ?? '' ), 'ya no está en el almacén' ), 'Sin su copia previa, las tablas apartadas se borraron solas o no se dijo por qué: ' . json_encode( $set ) );
	rename( $safety . '.fuera', $safety );
	$status = mig_rest( 'GET', KP_NS . '/migration' );
	mig_assert( 200 === $status[0] && $aside === kp_aside() && 1 === $status[1]['kept_tables']['count'], 'Consultar el estado borró tablas apartadas.' );
	Digitalisimo_Backups_Jobs::watchdog();
	mig_assert( ! kp_aside() && ! kp_registry() && 'Origen' === get_option( 'blogname' ) && in_array( 'dst_solo_origen', kp_tables(), true ), 'Pasado el plazo, el vigilante no borró sólo las tablas apartadas: ' . implode( ', ', kp_tables() ) );
	$events = array_column( Digitalisimo_Backups_Migrate::public_log( 50 ), 'event' );
	mig_assert( in_array( 'kept_dropped', $events, true ), 'El registro de la migración no anota el borrado por plazo.' );
} );

kp_case( 'k1c · nunca otra tabla: ni las de una restauración en curso, ni un registro alterado, ni las que no están en él', function() {
	$migration = kp_migrated();
	$tag = (string) key( kp_registry() );
	$aside = kp_aside();
	// La restauración que las apartó «sigue»: se dejan.
	$restore = kp_restore_of( $migration );
	$restore['status'] = 'running';
	Digitalisimo_Backups_Job_Store::write( $restore );
	$report = Digitalisimo_Backups_Migrate::kept_cleanup( true );
	mig_assert( 0 === $report['count'] && $aside === kp_aside() && 1 === count( $report['kept'] ) && false !== strpos( $report['kept'][0]['reason'], 'sigue en curso' ), 'Se borraron las tablas de una restauración en curso: ' . json_encode( $report ) );
	$restore = Digitalisimo_Backups_Jobs::get( (string) $restore['id'] );
	$restore['status'] = 'success';
	Digitalisimo_Backups_Job_Store::write( $restore );
	// Una tabla con nombre de apartada fuera del registro, otra ajena y un registro alterado.
	$db = $GLOBALS['wpdb'];
	$db->pdo->exec( 'CREATE TABLE `dst_dgbkk_abcdef_9000` (`id` INTEGER PRIMARY KEY)' );
	$db->pdo->exec( 'CREATE TABLE `dst_ajena` (`id` INTEGER PRIMARY KEY)' );
	$state = get_option( KP_STATE );
	$state['kept']['fedcba'] = array( 'kind' => 'undo', 'restore' => '', 'at' => 1, 'safety' => '', 'tables' => array( 'a' => 'dst_posts', 'b' => 'dst_ajena', 'c' => 'dst_dgbkk_abcdef_9000', 'd' => 'dst_options', 'e' => 'dst_dgbkk_fedcba' ) );
	$state['kept']['zz'] = array( 'kind' => 'undo', 'at' => 1, 'tables' => array( 'x' => 'dst_posts' ) );
	$state['kept'][ $tag ]['tables']['dst_posts'] = 'dst_posts';
	update_option( KP_STATE, $state );
	$before = kp_tables();
	$report = Digitalisimo_Backups_Migrate::kept_cleanup( true );
	$left = array_values( array_diff( $before, kp_tables() ) );
	mig_assert( $aside === $left && $aside === $report['tables'], 'La limpieza borró algo fuera de las tablas apartadas del registro: ' . json_encode( array( $left, $report ) ) );
	mig_assert( array( 'dst_dgbkk_abcdef_9000' ) === $report['unregistered'] && in_array( 'dst_posts', kp_tables(), true ) && in_array( 'dst_options', kp_tables(), true ) && in_array( 'dst_ajena', kp_tables(), true ) && 'Origen' === get_option( 'blogname' ), 'La limpieza tocó una tabla viva, ajena o sin registro: ' . json_encode( $report ) );
	mig_assert( ! kp_registry() && array( 'dst_dgbkk_abcdef_9000' ) === Digitalisimo_Backups_Migrate::kept_tables()['unregistered'], 'El registro alterado no quedó limpio o la tabla sin registro no se lista: ' . json_encode( kp_registry() ) );
	mig_assert( false !== strpos( Digitalisimo_Backups_Migrate::kept_message( $report ), 'no están en el registro y no se tocan' ), 'El mensaje no dice que las tablas sin registro no se tocan.' );
} );

kp_case( 'k1d · «Borrar ya» por la pantalla, REST y WP-CLI, con el alcance de red en una red', function() {
	$M = 'Digitalisimo_Backups_Migrate';
	kp_migrated();
	$aside = kp_aside();
	// REST: dry_run lista sin borrar.
	$reply = mig_rest( 'POST', KP_NS . '/migration/cleanup', array( 'dry_run' => true ) );
	mig_assert( 200 === $reply[0] && 1 === $reply[1]['count'] && $aside === $reply[1]['tables'] && true === $reply[1]['dry_run'] && $aside === kp_aside() && false !== strpos( $reply[1]['message'], 'Se borrarían 1' ), 'POST /migration/cleanup con dry_run no listó sin borrar: ' . json_encode( $reply ) );
	// WP-CLI: --dry-run lista; sin --yes pregunta y no borra.
	Digitalisimo_Backups_Cli::register();
	mig_assert( isset( WP_CLI::$commands[ KP_CMD ] ) && method_exists( 'Digitalisimo_Backups_Cli_Migration', 'cleanup' ) && false !== strpos( (string) ( new ReflectionMethod( 'Digitalisimo_Backups_Cli_Migration', 'cleanup' ) )->getDocComment(), '[--dry-run]' ), 'WP-CLI no tiene `migration cleanup [--dry-run]`.' );
	list( $code, $out ) = digi_wp( KP_CMD, 'cleanup', array(), array( 'dry-run' => true ) );
	$text = implode( "\n", $out );
	mig_assert( 0 === $code && false !== strpos( $text, $aside[0] ) && false !== strpos( $text, 'dst_solo_destino' ) && false !== strpos( end( $out ), 'Se borrarían 1' ) && $aside === kp_aside(), 'migration cleanup --dry-run no listó: ' . $text );
	list( $code, $out ) = digi_wp( KP_CMD, 'cleanup' );
	mig_assert( 1 === $code && false !== strpos( implode( "\n", $out ), '[y/n] n' ) && $aside === kp_aside(), 'migration cleanup sin --yes no pidió confirmación o borró: ' . implode( "\n", $out ) );
	// Pantalla: la tarjeta con el juego y «Borrar ya»; sin nonce, nada.
	ob_start();
	$M::panel( false, mig_call( 'Digitalisimo_Backups', 'migration_status_data' ) );
	$html = ob_get_clean();
	mig_assert( false !== strpos( $html, 'Tablas apartadas' ) && false !== strpos( $html, $aside[0] ) && false !== strpos( $html, 'value="' . $M::ACTION_CLEANUP . '"' ) && false !== strpos( $html, 'Borrar ya las tablas apartadas' ), 'La pestaña «Migrar» no muestra las tablas apartadas con «Borrar ya».' );
	try { mig_post( array( $M, 'cleanup_request' ), array() ); throw new RuntimeException( 'FALLA: se borraron sin nonce.' ); } catch ( Mig_Die $die ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	$GLOBALS['bk_caps'] = array();
	try { mig_post( array( $M, 'cleanup_request' ), array( '_wpnonce' => 'nonce-' . $M::ACTION_CLEANUP ) ); throw new RuntimeException( 'FALLA: se borraron sin permiso.' ); } catch ( Mig_Die $die ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	$GLOBALS['bk_caps'] = array( 'manage_options' => true, 'manage_network_options' => true );
	mig_assert( $aside === kp_aside(), 'Sin nonce o sin permiso se borraron tablas apartadas.' );
	list( $ok, $message, $url ) = mig_post( array( $M, 'cleanup_request' ), array( '_wpnonce' => 'nonce-' . $M::ACTION_CLEANUP ) );
	mig_assert( $ok && false !== strpos( $message, 'Se borraron 1 tabla(s) apartadas' ) && false !== strpos( $url, 'tab=migrate' ) && ! kp_aside() && ! kp_registry(), '«Borrar ya» de la pantalla no las borró: ' . $message );
	ob_start();
	$M::panel( false, mig_call( 'Digitalisimo_Backups', 'migration_status_data' ) );
	mig_assert( false === strpos( ob_get_clean(), 'id="digitalisimo-backups-migrate-kept"' ), 'La tarjeta sigue sin tablas apartadas.' );
	// WP-CLI con --yes y REST, cada uno con su migración.
	kp_migrated();
	Digitalisimo_Backups_Cli::register();
	list( $code, $out ) = digi_wp( KP_CMD, 'cleanup', array(), array( 'yes' => true ) );
	mig_assert( 0 === $code && false !== strpos( end( $out ), 'Se borraron 1' ) && ! kp_aside(), 'migration cleanup --yes no las borró: ' . implode( "\n", $out ) );
	kp_migrated();
	$reply = mig_rest( 'POST', KP_NS . '/migration/cleanup', array() );
	mig_assert( 200 === $reply[0] && 1 === $reply[1]['count'] && ! kp_aside() && array() === $reply[1]['kept_tables']['sets'], 'POST /migration/cleanup no las borró: ' . json_encode( $reply ) );
	// En una red: listar sin el alcance de red sí; borrar, sólo con él (REST, WP-CLI) y desde la pantalla de la red.
	kp_migrated();
	$aside = kp_aside();
	$state = get_option( KP_STATE );
	$GLOBALS['mig']['sites']['destino']['multisite'] = true;
	mig_use( 'origen' );
	mig_use( 'destino' );
	try {
		$GLOBALS['bk_site_options'][ KP_STATE ] = $state;
		$reply = mig_rest( 'POST', KP_NS . '/migration/cleanup', array() );
		mig_assert( 400 === $reply[0] && 'digitalisimo_backups_scope' === $reply[1]['code'] && $aside === kp_aside(), 'En una red se borraron sin el alcance de red: ' . json_encode( $reply ) );
		$reply = mig_rest( 'POST', KP_NS . '/migration/cleanup', array( 'dry_run' => true ) );
		mig_assert( 200 === $reply[0] && 1 === $reply[1]['count'], 'En una red, listar pidió el alcance de red: ' . json_encode( $reply ) );
		list( $code, $out ) = digi_wp( KP_CMD, 'cleanup', array(), array( 'yes' => true ) );
		mig_assert( 1 === $code && $aside === kp_aside(), 'En una red, WP-CLI borró sin --network: ' . implode( "\n", $out ) );
		list( $ok, $message ) = mig_post( array( $M, 'cleanup_request' ), array( '_wpnonce' => 'nonce-' . $M::ACTION_CLEANUP ) );
		mig_assert( $ok && ! kp_aside(), 'La pantalla de la red no las borró: ' . $message );
	} finally {
		$GLOBALS['mig']['sites']['destino']['multisite'] = false;
		mig_use( 'origen' );
		mig_use( 'destino' );
	}
} );

kp_case( 'k1e · las que apartó una restauración sin registro se toman de su trabajo; sin trabajo, no se tocan', function() {
	$migration = kp_migrated();
	$aside = kp_aside();
	// Como una restauración de 1.0.66–1.0.70: el registro no las tiene.
	$state = get_option( KP_STATE );
	unset( $state['kept'] );
	update_option( KP_STATE, $state );
	$kept = Digitalisimo_Backups_Migrate::kept_tables();
	mig_assert( 1 === count( $kept['sets'] ) && ! empty( $kept['sets'][0]['adopted'] ) && array( array( 'table' => $aside[0], 'was' => '' ) ) === $kept['sets'][0]['tables'] && 'migration' === $kept['sets'][0]['kind'] && ! $kept['unregistered'] && ! kp_registry(), 'Las tablas apartadas sin registro no se tomaron de su trabajo (o la consulta escribió): ' . json_encode( $kept ) );
	Digitalisimo_Backups_Jobs::watchdog();
	mig_assert( 1 === count( kp_registry() ) && $aside === kp_aside(), 'El vigilante no las anotó o las borró antes del plazo.' );
	// Sin registro y sin su trabajo: se listan como `unregistered` y nunca se tocan.
	update_option( KP_STATE, $state );
	$restore = (string) $migration['result']['restore'];
	Digitalisimo_Backups_Job_Store::remove_tree( Digitalisimo_Backups_Job_Store::directory( $restore ) );
	$report = Digitalisimo_Backups_Migrate::kept_cleanup( true );
	mig_assert( 0 === $report['count'] && $aside === $report['unregistered'] && $aside === kp_aside(), 'Se borró una tabla apartada sin registro ni trabajo: ' . json_encode( $report ) );
} );

kp_case( 'k3 · el paso en curso de una migración lleva el nombre de su fase', function() {
	kp_sites();
	$key = mig_in( 'origen', function() { return mig_rest( 'POST', KP_NS . '/migration/keys', array( 'hours' => 24 ) )[1]['key']; } );
	mig_use( 'destino' );
	$reply = mig_rest( 'POST', KP_NS . '/migration/pull', array( 'key' => $key, 'confirm' => 'MIGRAR' ) );
	$seen = array();
	$current = new ReflectionProperty( 'Digitalisimo_Backups_Jobs', 'current' );
	if ( PHP_VERSION_ID < 80100 ) $current->setAccessible( true );
	$GLOBALS['mig']['faults'] = array( function( $method, $route, $args, $response ) use ( &$seen, $current ) {
		$step = $current->getValue();
		if ( null === $response && false !== strpos( $route, '/migrate/source/download' ) && is_array( $step ) ) $seen[] = Digitalisimo_Backups_Jobs::phase_label( (string) $step['phase'], $step );
		return null;
	} );
	$job = kp_drive( $reply[1]['id'] );
	$GLOBALS['mig']['faults'] = array();
	mig_assert( 'success' === $job['status'] && $seen && array( 'Descarga' ) === array_values( array_unique( $seen ) ), 'El paso en curso de la descarga no lleva el nombre de su fase: ' . json_encode( $seen ) );
} );

echo $failures ? 'Tablas apartadas de la migración con fallos: ' . implode( ', ', $failures ) . "\n" : "Tablas apartadas de la migración: todo correcto.\n";
mig_remove_tree( $GLOBALS['mig_root'] );
exit( $failures ? 1 : 0 );
