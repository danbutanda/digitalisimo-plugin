<?php
/**
 * Incrementales de DIGITALÍSIMO Backups en un WordPress real (Playground, SQLite, php-wasm). Escribe cada resultado en
 * result.txt; una línea «FALLA» detiene el arnés. Todo pasa por los handlers admin-post reales con nonce y capacidad,
 * el motor de trabajos y la restauración por pasos.
 *
 * single:
 *   P1  una automatización incremental creada por el formulario real (completo cada 3, conservar 6);
 *   P2  completo → cambios (archivos añadidos, cambiados y borrados; entradas, metadatos y opciones nuevas, cambiadas y
 *       borradas) → incremental → más cambios → incremental: cada incremental sólo guarda lo nuevo o cambiado;
 *   P3  restaurar cada punto (y el completo) por la pantalla deja archivos y tablas con las huellas de su momento;
 *   P4  con un eslabón que falta o alterado, restaurar se rechaza con un mensaje claro y el sitio no cambia;
 *   P5  retención tras días simulados: consolida, cuenta puntos y nunca rompe una cadena;
 *   P6  espacio: lo que ocupan los puntos frente a un completo por punto.
 * remote: la cadena hacia un SFTP real con «sólo en el remoto», «Traer al almacén» del último punto (trae su cadena) y su
 * restauración idéntica.
 */
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', '/var/digitalisimo-backups-incremental' );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';
$dir = '/digitalisimo-probe';
$mode = trim( (string) @file_get_contents( $dir . '/mode' ) ) ?: 'single';
$result = $dir . '/result.txt';
@unlink( $result );
$log = function( $line ) use ( $result ) { file_put_contents( $result, $line . "\n", FILE_APPEND ); };
$call = function( $method, ...$args ) { $r = new ReflectionMethod( 'Digitalisimo_Backups', $method ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true ); return $r->invokeArgs( null, $args ); };
$check = function( $ok, $what ) use ( $log ) { if ( ! $ok ) throw new RuntimeException( $what ); $log( 'ok · ' . $what ); };
class Probe_Redirect extends Error { public $url; public function __construct( $url ) { parent::__construct( 'redirect' ); $this->url = $url; } }
class Probe_Die extends Error {}
add_filter( 'wp_redirect', function( $location ) { throw new Probe_Redirect( $location ); }, 1 );
add_filter( 'wp_die_handler', function() { return function( $message ) { throw new Probe_Die( is_string( $message ) ? $message : 'wp_die' ); }; } );
add_filter( 'pre_http_request', function( $pre, $args, $url ) { return false !== strpos( (string) $url, 'wp-cron.php' ) ? new WP_Error( 'digitalisimo_probe_cron', 'WP-Cron lo simula la sonda.' ) : $pre; }, 10, 3 );
$submit = function( $callback, $action, $fields ) {
	// En una red, los formularios de Backups son los de la administración de red (alcance de red explícito).
	if ( is_multisite() ) $fields['digitalisimo_backups_network'] = '1';
	$_POST = wp_slash( array_merge( array( '_wpnonce' => wp_create_nonce( $action ), 'action' => $action ), $fields ) );
	$_REQUEST = $_POST;
	try {
		call_user_func( $callback );
	} catch ( Probe_Redirect $redirect ) {
		$query = array();
		parse_str( (string) wp_parse_url( $redirect->url, PHP_URL_QUERY ), $query );
		return array( 'ok' === ( $query['backup'] ?? '' ), rawurldecode( (string) ( $query['message'] ?? '' ) ) );
	} finally {
		$_POST = array();
		$_REQUEST = array();
	}
	throw new RuntimeException( 'El handler ' . $action . ' no redirigió.' );
};
$timer = microtime( true );
$elapsed = function() use ( $timer ) { return round( microtime( true ) - $timer ) . ' s'; };
/** Hace avanzar la cola hasta que no queda nada activo. */
$drain = function() {
	for ( $i = 0; $i < 5000; $i++ ) {
		$active = Digitalisimo_Backups_Jobs::active();
		if ( ! $active ) return;
		Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	}
	throw new RuntimeException( 'La cola no terminó.' );
};
/** Huellas de los archivos que gestiona un respaldo (sin wp-config.php ni el plugin montado) y de las tablas. */
$files = function() {
	$out = array();
	$root = untrailingslashit( ABSPATH );
	foreach ( array( 'wp-admin', 'wp-includes', 'wp-content/themes', 'wp-content/plugins', 'wp-content/uploads' ) as $base ) {
		if ( ! is_dir( $root . '/' . $base ) ) continue;
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $base, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $entry ) {
			$relative = substr( $entry->getPathname(), strlen( $root ) + 1 );
			if ( 0 === strpos( $relative, 'wp-content/plugins/digitalisimo-backups/' ) || ! $entry->isFile() ) continue;
			$out[ $relative ] = md5_file( $entry->getPathname() );
		}
	}
	foreach ( glob( $root . '/*.php' ) as $path ) if ( 'wp-config.php' !== basename( $path ) ) $out[ basename( $path ) ] = md5_file( $path );
	ksort( $out );
	return $out;
};
$tables = function() {
	global $wpdb;
	wp_cache_flush();
	return array(
		'posts' => md5( json_encode( $wpdb->get_results( "SELECT ID, post_title, post_content, post_status, post_type FROM {$wpdb->posts} ORDER BY ID", ARRAY_N ) ) ),
		'postmeta' => md5( json_encode( $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key NOT LIKE '\\_edit\\_%' ORDER BY meta_id", ARRAY_N ) ) ),
		'options' => md5( json_encode( $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name NOT LIKE 'digitalisimo\\_backups%' AND option_name NOT LIKE '%transient%' AND option_name NOT IN ( 'cron', 'recently_activated', 'active_plugins', 'rewrite_rules' ) ORDER BY option_name", ARRAY_N ) ) ),
		'terms' => md5( json_encode( $wpdb->get_results( "SELECT term_id, name, slug FROM {$wpdb->terms} ORDER BY term_id", ARRAY_N ) ) ),
	);
};
$diff = function( array $expected, array $now ) {
	$out = array();
	foreach ( array_diff_key( $expected, $now ) as $path => $hash ) $out[] = 'falta ' . $path;
	foreach ( array_diff_key( $now, $expected ) as $path => $hash ) $out[] = 'sobra ' . $path;
	foreach ( array_intersect_key( $now, $expected ) as $path => $hash ) if ( $expected[ $path ] !== $hash ) $out[] = 'distinto ' . $path;
	return implode( ', ', array_slice( $out, 0, 6 ) ) . ( count( $out ) > 6 ? '…' : '' );
};
$put = function( $relative, $content ) {
	$path = ABSPATH . $relative;
	wp_mkdir_p( dirname( $path ) );
	file_put_contents( $path, $content );
};
$history = function( $file ) use ( $call ) { wp_cache_flush(); return $call( 'history_item', basename( $file ) ); };
$storage = function( $file = '' ) { return rtrim( Digitalisimo_Backups::storage_directory(), '/' ) . ( '' !== $file ? '/' . basename( $file ) : '' ); };
/** Ejecuta ahora una automatización por la pantalla («Ejecutar ahora») y la termina por ticks. Devuelve el trabajo. */
$run = function( $id ) use ( $submit, $drain ) {
	$known = array_column( Digitalisimo_Backups_Jobs::all(), 'id' );
	list( $ok, $message ) = $submit( array( 'Digitalisimo_Backups', 'run_policy_request' ), Digitalisimo_Backups::ACTION_RUN_POLICY, array( 'digitalisimo_backup_policy' => $id ) );
	if ( ! $ok ) throw new RuntimeException( 'No se encoló la automatización: ' . $message );
	$drain();
	for ( $i = 0; $i < 5; $i++ ) Digitalisimo_Backups::job_remote_retention_pending( 20 );
	$job = null;
	foreach ( Digitalisimo_Backups_Jobs::all() as $candidate ) if ( ! in_array( $candidate['id'], $known, true ) && 'restore' !== $candidate['kind'] ) $job = $candidate;
	if ( ! $job || 'success' !== $job['status'] ) throw new RuntimeException( 'La automatización no terminó: ' . json_encode( $job['error'] ?? null ) );
	return $job;
};
/** Restaura por la pantalla («Restaurar» con RESTAURAR) y termina la restauración por ticks. Devuelve array( ok, mensaje ). */
$restore = function( $file ) use ( $submit, $drain ) {
	list( $ok, $message ) = $submit( array( 'Digitalisimo_Backups', 'restore_request' ), Digitalisimo_Backups::ACTION_RESTORE, array( 'backup_file' => basename( $file ), 'confirmation' => 'RESTAURAR' ) );
	if ( $ok ) $drain();
	wp_cache_flush();
	wp_set_current_user( 1 );
	$last = null;
	foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( 'restore' === $job['kind'] && ( ! $last || (float) $job['queued_at'] >= (float) $last['queued_at'] ) ) $last = $job;
	return array( $ok && $last && 'success' === $last['status'], $message . ( $last && 'success' !== $last['status'] && $ok ? ' · ' . json_encode( $last['error'] ) : '' ) );
};
/** Una automatización por el formulario real de «Herramientas → Automatizaciones». */
$automation = function( array $fields ) use ( $submit ) {
	$before = array_column( Digitalisimo_Backups::policies(), 'id' );
	list( $ok, $message ) = $submit( array( 'Digitalisimo_Backups', 'create_automation' ), Digitalisimo_Backups::ACTION_CREATE_AUTOMATION, array( 'digitalisimo_backup_automation' => $fields + array( 'type' => 'full', 'schedule' => 'daily' ) ) );
	if ( ! $ok ) throw new RuntimeException( 'No se creó la automatización: ' . $message );
	foreach ( Digitalisimo_Backups::policies() as $policy ) if ( ! in_array( $policy['id'], $before, true ) ) return $policy;
	throw new RuntimeException( 'La automatización creada no aparece.' );
};

try {
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php', '', is_multisite() );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( $activated->get_error_message() );
	if ( ! class_exists( 'Digitalisimo_Backups' ) ) require_once WP_PLUGIN_DIR . '/digitalisimo-backups/digitalisimo-backups.php';
	if ( ! has_action( 'admin_post_' . Digitalisimo_Backups::ACTION_RESTORE ) ) Digitalisimo_Backups::init();
	wp_set_current_user( 1 );
	$log( 'Modo ' . $mode . ' · PHP ' . PHP_VERSION . ' · WordPress ' . get_bloginfo( 'version' ) . ' · sodium ' . ( extension_loaded( 'sodium' ) ? 'nativo' : 'compat' ) );
	// Un sitio con contenido: un plugin, un tema, unos 120 medios y 40 entradas con metadatos.
	$put( 'wp-content/plugins/demo/demo.php', "<?php /* Plugin Name: Demo */\n" );
	for ( $i = 0; $i < 12; $i++ ) $put( 'wp-content/plugins/demo/inc/parte-' . $i . '.php', "<?php // parte $i\n" . str_repeat( "// línea de relleno del plugin demo $i\n", 200 ) );
	$put( 'wp-content/themes/tema-demo/style.css', "/* Theme Name: Tema demo */\n" );
	$put( 'wp-content/themes/tema-demo/index.php', "<?php // tema\n" );
	for ( $i = 0; $i < 120; $i++ ) $put( 'wp-content/uploads/2026/11/medio-' . $i . '.jpg', "\xff\xd8\xff\xe0" . random_bytes( 6000 + 37 * $i ) );
	$ids = array();
	for ( $i = 0; $i < 40; $i++ ) {
		$ids[ $i ] = wp_insert_post( array( 'post_title' => 'Entrada ' . $i, 'post_content' => str_repeat( 'Contenido de la entrada ' . $i . '. ', 30 ), 'post_status' => 'publish' ) );
		update_post_meta( $ids[ $i ], 'demo_meta', 'valor ' . $i );
	}
	update_option( 'demo_opcion', 'punto 0' );

	if ( 'single' === $mode || 'multisite' === $mode ) {
		// P1: la automatización incremental por su formulario.
		$policy = $automation( array( 'destination' => 'local', 'retain' => 6, 'mode' => 'incremental', 'full_every' => 3, 'full_days' => 7 ) );
		$check( 'incremental' === ( $policy['mode'] ?? '' ) && 3 === (int) $policy['full_every'], 'P1 automatización incremental creada por el formulario (completo cada 3, conservar 6)' );
		// P2: completo, cambios, incremental, más cambios, incremental.
		$points = array();
		$snap = array();
		$job = $run( $policy['id'] );
		$points[0] = $storage( $job['spec']['file'] );
		$snap[0] = array( $files(), $tables() );
		$row = $history( $points[0] );
		$check( 'full' === $row['backup_type'] && 0 === (int) $row['chain']['sequence'], 'P2 punto 0: completo que empieza la cadena (' . size_format( filesize( $points[0] ) ) . ', ' . (int) $row['chain']['files'] . ' archivos)' );
		// Cambios 1: añadir, modificar (también con el mismo tamaño) y borrar archivos; entradas, metadatos y opciones.
		$put( 'wp-content/plugins/demo/nuevo.php', "<?php // nuevo en el punto 1\n" );
		$put( 'wp-content/plugins/demo/inc/parte-1.php', str_replace( 'parte 1', 'PARTE 1', file_get_contents( ABSPATH . 'wp-content/plugins/demo/inc/parte-1.php' ) ) );
		$put( 'wp-content/themes/tema-demo/index.php', "<?php // tema cambiado en el punto 1\n" );
		unlink( ABSPATH . 'wp-content/uploads/2026/11/medio-5.jpg' );
		unlink( ABSPATH . 'wp-content/plugins/demo/inc/parte-7.php' );
		$put( 'wp-content/uploads/2026/12/nuevo-medio.jpg', "\xff\xd8\xff\xe0" . random_bytes( 9000 ) );
		wp_update_post( array( 'ID' => $ids[3], 'post_title' => 'Entrada 3 cambiada' ) );
		wp_delete_post( $ids[4], true );
		$ids['n1'] = wp_insert_post( array( 'post_title' => 'Nueva del punto 1', 'post_content' => 'nueva', 'post_status' => 'publish' ) );
		update_post_meta( $ids[6], 'demo_meta', 'cambiado en el punto 1' );
		update_option( 'demo_opcion', 'punto 1' );
		add_option( 'demo_opcion_1', 'sólo desde el punto 1' );
		$job = $run( $policy['id'] );
		$points[1] = $storage( $job['spec']['file'] );
		$snap[1] = array( $files(), $tables() );
		$row = $history( $points[1] );
		$zip = new ZipArchive();
		$zip->open( $points[1] );
		$names = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) $names[] = $zip->getNameIndex( $i );
		$zip->close();
		sort( $names );
		$expected = array( 'database.sql', 'inventory.jsonl', 'manifest.json', 'wordpress/wp-content/plugins/demo/inc/parte-1.php', 'wordpress/wp-content/plugins/demo/nuevo.php', 'wordpress/wp-content/themes/tema-demo/index.php', 'wordpress/wp-content/uploads/2026/12/nuevo-medio.jpg' );
		$check( 'incremental' === $row['backup_type'] && 1 === (int) $row['chain']['sequence'] && $expected === $names && 2 === (int) $row['chain']['deleted'], 'P2 punto 1: incremental con sólo lo nuevo o cambiado (' . count( $names ) . ' entradas, ' . (int) $row['chain']['deleted'] . ' borrados, ' . size_format( filesize( $points[1] ) ) . ')' . ( $expected === $names ? '' : ': ' . implode( ', ', $names ) ) );
		// Cambios 2: el archivo nuevo se borra, otro cambia otra vez, uno borrado vuelve, más filas.
		unlink( ABSPATH . 'wp-content/plugins/demo/nuevo.php' );
		$put( 'wp-content/themes/tema-demo/index.php', "<?php // tema cambiado otra vez en el punto 2\n" );
		$put( 'wp-content/plugins/demo/inc/parte-7.php', "<?php // parte 7 vuelve distinta\n" );
		wp_delete_post( $ids['n1'], true );
		wp_update_post( array( 'ID' => $ids[8], 'post_content' => 'contenido nuevo del punto 2' ) );
		delete_option( 'demo_opcion_1' );
		update_option( 'demo_opcion', 'punto 2' );
		$job = $run( $policy['id'] );
		$points[2] = $storage( $job['spec']['file'] );
		$snap[2] = array( $files(), $tables() );
		$row = $history( $points[2] );
		$check( 2 === (int) $row['chain']['sequence'] && 2 === count( $row['chain']['links'] ), 'P2 punto 2: incremental sobre los anteriores (' . size_format( filesize( $points[2] ) ) . ', ' . (int) $row['chain']['added'] . ' nuevos, ' . (int) $row['chain']['changed'] . ' cambiados, ' . (int) $row['chain']['deleted'] . ' borrados)' );
		// P3: restaurar cada punto por la pantalla.
		foreach ( array( 1, 0, 2, 1 ) as $n ) {
			$put( 'wp-content/plugins/demo/intruso.php', "<?php // después de todos los puntos\n" );
			update_option( 'demo_opcion', 'cambiado antes de restaurar' );
			$started = microtime( true );
			list( $ok, $message ) = $restore( $points[ $n ] );
			$now = array( $files(), $tables() );
			$check( $ok && $snap[ $n ][0] === $now[0], 'P3 restaurar el punto ' . $n . ': archivos idénticos (' . count( $now[0] ) . ' huellas)' . ( $ok ? '' : ': ' . $message ) . ( $snap[ $n ][0] === $now[0] ? '' : ': ' . $diff( $snap[ $n ][0], $now[0] ) ) );
			$check( $snap[ $n ][1] === $now[1], 'P3 restaurar el punto ' . $n . ': tablas idénticas (entradas, metadatos, opciones y términos) · ' . round( microtime( true ) - $started ) . ' s' . ( $snap[ $n ][1] === $now[1] ? '' : ': ' . json_encode( array_keys( array_diff_assoc( $snap[ $n ][1], $now[1] ) ) ) ) );
		}
		if ( 'multisite' === $mode ) {
			// En una red: la cadena es de la red entera (todos sus sitios); con P1-P3 basta.
			$check( is_multisite() && 'network' === $history( $points[2] )['scope'], 'P2 en una red, los puntos son de la red completa' );
			$log( 'TERMINADO · ' . $elapsed() );
			return;
		}
		// P4: un eslabón que falta o alterado: rechazo claro y el sitio sin tocar.
		$put( 'wp-content/plugins/demo/intruso.php', "<?php // sigue aquí si nada cambia\n" );
		$before = array( $files(), $tables() );
		rename( $points[1], $points[1] . '.aparte' );
		list( $ok, $message ) = $restore( $points[2] );
		$check( ! $ok && false !== strpos( $message, 'faltan en el almacén' ) && false !== strpos( $message, 'No se cambió nada del sitio' ) && $before === array( $files(), $tables() ), 'P4 eslabón que falta (sin otra copia): rechazado sin tocar el sitio («' . mb_substr( $message, 0, 90 ) . '…»)' );
		rename( $points[1] . '.aparte', $points[1] );
		$bytes = file_get_contents( $points[1] );
		file_put_contents( $points[1], substr( $bytes, 0, 300 ) . chr( ord( $bytes[300] ) ^ 1 ) . substr( $bytes, 301 ) );
		list( $ok, $message ) = $restore( $points[2] );
		$check( ! $ok && false !== strpos( $message, 'eslabón 2 de 3' ) && $before === array( $files(), $tables() ), 'P4 eslabón alterado: rechazado sin tocar el sitio («' . mb_substr( $message, 0, 90 ) . '…»)' );
		file_put_contents( $points[1], $bytes );
		// P5: retención tras días simulados (completo cada 2 o cada 2 días, conservar 2).
		$ret = $automation( array( 'destination' => 'local', 'retain' => 2, 'mode' => 'incremental', 'full_every' => 2, 'full_days' => 2 ) );
		$made = array();
		for ( $day = 0; $day < 6; $day++ ) {
			$put( 'wp-content/uploads/2026/12/dia.txt', 'día ' . $day );
			update_option( 'demo_dia', $day );
			if ( 4 === $day ) {
				// Dos días después: la base de la cadena en curso «tiene» 3 días.
				$rows = (array) get_option( Digitalisimo_Backups::HISTORY, array() );
				foreach ( $rows as &$r ) if ( $ret['id'] === ( $r['policy'] ?? '' ) && is_array( $r['chain'] ?? null ) ) $r['chain']['base_at'] -= 3 * DAY_IN_SECONDS;
				unset( $r );
				update_option( Digitalisimo_Backups::HISTORY, $rows, false );
			}
			$job = $run( $ret['id'] );
			$made[] = (int) Digitalisimo_Backups_Chain::job_plan( $job )['sequence'];
			wp_cache_flush();
			foreach ( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) as $r ) {
				if ( $ret['id'] !== ( $r['policy'] ?? '' ) ) continue;
				foreach ( Digitalisimo_Backups_Chain::files( $r ) as $f ) if ( ! is_file( $storage( $f ) ) ) throw new RuntimeException( 'P5 la retención rompió la cadena de ' . $r['file'] . ': falta ' . $f );
			}
		}
		$kept = count( array_filter( (array) get_option( Digitalisimo_Backups::HISTORY, array() ), function( $r ) use ( $ret ) { return $ret['id'] === ( $r['policy'] ?? '' ); } ) );
		$check( array( 0, 1, 2, 0, 0, 1 ) === $made && $kept <= 4, 'P5 retención por días: puntos ' . implode( ',', $made ) . ' (consolida cada 2 y al pasar 2 días), ' . $kept . ' archivos conservados y ninguna cadena rota' );
		// P6: espacio frente a un completo por punto (el tamaño de un completo de ahora).
		$view = Digitalisimo_Backups::service_create( array( 'destination' => 'local', 'via' => 'test', 'spawn' => false ) );
		$drain();
		$full = filesize( $storage( Digitalisimo_Backups_Jobs::get( $view['id'] )['spec']['file'] ) );
		$chain = filesize( $points[0] ) + filesize( $points[1] ) + filesize( $points[2] );
		$saving = Digitalisimo_Backups::chain_saving( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) );
		$check( $chain < 3 * $full && $saving['saved'] > 0, 'P6 espacio: 3 puntos ocupan ' . size_format( $chain ) . ' en vez de ' . size_format( 3 * $full ) . ' (ahorro ' . round( 100 * ( 1 - $chain / ( 3 * $full ) ) ) . ' %; completo actual ' . size_format( $full ) . '); el estado dice que los incrementales ahorran ' . size_format( $saving['saved'] ) );
	} else {
		$config = json_decode( (string) file_get_contents( $dir . '/config.json' ), true );
		update_option( Digitalisimo_Backups::OPTION, array( 'destination' => 'local', 'sftp_host' => $config['host'], 'sftp_port' => (string) $config['port'], 'sftp_user' => $config['user'], 'sftp_path' => $config['path'], 'sftp_engine' => 'auto', 'sftp_auth' => 'key', 'sftp_private_key' => trim( $config['key'] ), 'sftp_public_key' => trim( $config['pub'] ), 'sftp_key_passphrase' => '', 'sftp_fingerprint' => $config['fingerprint'] ), false );
		$ready = $call( 'manual_destinations', $call( 'settings' ) );
		$check( isset( $ready['sftp'] ), 'R1 conexión SFTP real lista (OpenSSH en 127.0.0.1)' );
		$policy = $automation( array( 'destination' => 'sftp', 'retain' => 6, 'mode' => 'incremental', 'full_every' => 3, 'full_days' => 7, 'remote_only' => 1 ) );
		$points = array();
		$snap = array();
		foreach ( array( 0, 1, 2 ) as $n ) {
			if ( 1 === $n ) { $put( 'wp-content/plugins/demo/nuevo.php', "<?php // punto 1\n" ); unlink( ABSPATH . 'wp-content/uploads/2026/11/medio-9.jpg' ); update_option( 'demo_opcion', 'remoto 1' ); }
			if ( 2 === $n ) { $put( 'wp-content/themes/tema-demo/index.php', "<?php // remoto 2\n" ); wp_delete_post( $ids[2], true ); update_option( 'demo_opcion', 'remoto 2' ); }
			$started = microtime( true );
			$job = $run( $policy['id'] );
			$points[ $n ] = $storage( $job['spec']['file'] );
			$snap[ $n ] = array( $files(), $tables() );
			$row = $history( $points[ $n ] );
			$check( $n === (int) $row['chain']['sequence'] && 'ok' === ( $row['transfer'] ?? '' ) && 'remote-only' === ( $row['local'] ?? '' ) && ! is_file( $points[ $n ] ), 'R2 punto ' . $n . ': subido al SFTP, verificado allí y sin ZIP local (' . size_format( (int) $row['size'] ) . ', ' . round( microtime( true ) - $started ) . ' s)' );
		}
		$put( 'wp-content/plugins/demo/intruso.php', "<?php // después\n" );
		update_option( 'demo_opcion', 'después' );
		list( $ok, $message ) = $restore( $points[2] );
		$check( ! $ok && false !== strpos( $message, 'Traer al almacén' ), 'R3 restaurar sin la cadena en el almacén dice qué traer' );
		list( $ok, $message ) = $submit( array( 'Digitalisimo_Backups', 'fetch_request' ), Digitalisimo_Backups::ACTION_FETCH, array( 'file' => basename( $points[2] ) ) );
		$drain();
		$here = 0;
		foreach ( $points as $point ) if ( is_file( $point ) && hash_equals( (string) $history( $point )['sha256'], hash_file( 'sha256', $point ) ) ) $here++;
		$check( $ok && 3 === $here, 'R3 «Traer al almacén» trajo la cadena entera verificada (' . $message . ')' );
		list( $ok, $message ) = $restore( $points[2] );
		$now = array( $files(), $tables() );
		$check( $ok && $snap[2] === $now, 'R4 el punto traído del SFTP se restauró idéntico (archivos y tablas)' . ( $ok ? '' : ': ' . $message ) . ( $snap[2][0] === $now[0] ? '' : ': ' . $diff( $snap[2][0], $now[0] ) ) );
		// R5: recuperación ante desastre. Se pierden la base de datos de Backups (historial, índices, identidad) y el almacén:
		// queda el SFTP. Un sitio «nuevo» trae del destino el último punto, que trae su cadena, y lo restaura idéntico.
		delete_option( Digitalisimo_Backups::HISTORY );
		foreach ( glob( $storage() . '/*' ) as $path ) if ( is_file( $path ) ) unlink( $path );
		foreach ( (array) glob( $storage() . '/chains/*' ) as $path ) @unlink( $path );
		@unlink( $storage() . '/.installation-id' );
		$put( 'wp-content/plugins/demo/sitio-nuevo.php', "<?php // otro contenido\n" );
		update_option( 'demo_opcion', 'sitio nuevo' );
		$started = microtime( true );
		list( $ok, $message ) = $submit( array( 'Digitalisimo_Backups', 'remote_fetch_request' ), Digitalisimo_Backups::ACTION_REMOTE_FETCH, array( 'destination' => 'sftp', 'name' => basename( $points[2] ) ) );
		$drain();
		$here = 0;
		foreach ( $points as $point ) if ( is_file( $point ) ) $here++;
		$check( $ok && 3 === $here, 'R5 sin historial ni almacén, «Traer al almacén» del último punto del SFTP trajo su cadena (' . $here . ' de 3 archivos; ' . mb_substr( $message, 0, 120 ) . '…)' );
		list( $ok, $message ) = $restore( $points[2] );
		$check( ! $ok && false !== strpos( $message, 'RESTAURAR SIN FIRMA' ), 'R5 sin historial ni firma, restaurar pide «RESTAURAR SIN FIRMA»' );
		list( $ok, $message ) = $submit( array( 'Digitalisimo_Backups', 'restore_request' ), Digitalisimo_Backups::ACTION_RESTORE, array( 'backup_file' => basename( $points[2] ), 'confirmation' => 'RESTAURAR SIN FIRMA' ) );
		if ( $ok ) $drain();
		wp_cache_flush();
		wp_set_current_user( 1 );
		$now = array( $files(), $tables() );
		$check( $ok && $snap[2] === $now, 'R5 el último punto, traído a un sitio sin historial y restaurado, deja el sitio idéntico (' . round( microtime( true ) - $started ) . ' s)' . ( $ok ? '' : ': ' . $message ) . ( $snap[2][0] === $now[0] ? '' : ': ' . $diff( $snap[2][0], $now[0] ) ) );
	}
	$log( 'TERMINADO · ' . $elapsed() );
} catch ( Throwable $e ) {
	$log( 'FALLA · ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() );
}
