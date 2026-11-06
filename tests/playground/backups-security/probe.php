<?php
/**
 * Seguridad de DIGITALÍSIMO Backups en un WordPress real (Playground, SQLite, php-wasm sin libsodium nativo: usa el
 * sodium_compat de WordPress). Escribe cada resultado en result.txt; una línea «FALLA» detiene el arnés. Los guardados
 * y las restauraciones pasan por los handlers admin-post reales, con nonce y capacidad; las redirecciones y wp_die()
 * se capturan para seguir comprobando.
 *
 * Modo «single» (sitio individual):
 *   S1  frase guardada por admin-post (con comillas, barra, < y &, sin alterarse), pestaña sin secretos, nonce
 *       inválido rechazado, respaldo firmado, cambio posterior y restauración por admin-post que lo revierte; llavero
 *       conservado y fuera de database.sql.
 *   S3  copia manipulada (un byte del manifiesto): rechazada sin tocar el sitio.
 *   S4  archivo suelto sin firma ni registro: RESTAURAR no basta, «sólo firmados» bloquea y RESTAURAR SIN FIRMA
 *       restaura.
 *   S7  claves de wp-config.php cambiadas (huella de envoltura distinta): con cifrado activo el respaldo falla
 *       cerrado, sin archivos; la pestaña lo avisa y volver a escribir la frase lo arregla.
 *   S8  un respaldo creado con el código anterior (la clase de 1.0.58 que el arnés extrae de dist/ o rollback/, sin
 *       trabajos ni seguridad) y registrado como lo registraba se restaura con el código actual por admin-post.
 *   S2  (ENCRYPT=1) respaldo cifrado y restauración en «otro servidor» (llavero vacío): sin la frase se explica y no
 *       cambia nada; con la frase restaura.
 * Modo «multisite» (red):
 *   S5  S1 desde la red (llavero en sitemeta) y un subsitio que no puede gestionar la seguridad.
 *   S8  como en el sitio individual, con el respaldo de toda la red que crea y registra la clase anterior.
 * Modo «constant» (DIGITALISIMO_BACKUPS_KEY en wp-config.php):
 *   S6  cifrado obligatorio, huella sin el valor, frase rechazada y copia previa firmada con la constante.
 *
 * Cada respaldo se crea como lo crea el administrador: «Crear respaldo» por el handler admin-post real, que lo encola,
 * y la cola avanzada por ticks como lo hace WP-Cron (la sonda da los ticks; ninguna petición de bucle a wp-cron.php).
 */
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', '/var/digitalisimo-backups-probe' );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
// Lo que WordPress carga en sus pantallas de administración (submit_button() y compañía) y runPHP no.
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';
$dir = '/digitalisimo-probe';
$mode = trim( (string) @file_get_contents( $dir . '/mode' ) ) ?: 'single';
$result = $dir . '/result.txt';
$log = function( $line ) use ( $result ) { file_put_contents( $result, $line . "\n", FILE_APPEND ); };
$call = function( $method, ...$args ) { $r = new ReflectionMethod( 'Digitalisimo_Backups', $method ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true ); return $r->invokeArgs( null, $args ); };
$check = function( $ok, $what ) use ( $log ) { if ( ! $ok ) throw new RuntimeException( $what ); $log( 'ok · ' . $what ); };
class Probe_Redirect extends Error { public $url; public function __construct( $url ) { parent::__construct( 'redirect' ); $this->url = $url; } }
class Probe_Die extends Error {}
add_filter( 'wp_redirect', function( $location ) { throw new Probe_Redirect( $location ); }, 1 );
add_filter( 'wp_die_handler', function() { return function( $message ) { throw new Probe_Die( is_string( $message ) ? $message : 'wp_die' ); }; } );
/** Envía un formulario admin-post al handler real; devuelve array( ok, mensaje, url ) de su redirección. */
$post = function( $callback, $action, $fields, $nonce = null ) {
	$_POST = wp_slash( array_merge( array( '_wpnonce' => $nonce ?? wp_create_nonce( $action ), 'action' => $action ), $fields ) );
	$_REQUEST = $_POST;
	try {
		call_user_func( $callback );
	} catch ( Probe_Redirect $redirect ) {
		$query = array();
		parse_str( (string) wp_parse_url( $redirect->url, PHP_URL_QUERY ), $query );
		return array( 'ok' === ( $query['backup'] ?? '' ), rawurldecode( (string) ( $query['message'] ?? '' ) ), $redirect->url );
	} finally {
		$_POST = array();
		$_REQUEST = array();
	}
	throw new RuntimeException( 'El handler ' . $action . ' no redirigió.' );
};
$render = function( $network ) use ( $call ) {
	$_GET['tab'] = 'security';
	if ( $network ) $_REQUEST['digitalisimo_backups_network'] = '1';
	ob_start();
	try { $call( 'render', $network ); } finally { $html = ob_get_clean(); unset( $_GET['tab'], $_REQUEST['digitalisimo_backups_network'] ); }
	return $html;
};
$tree = function( $storage ) { $list = array(); foreach ( array( $storage, $storage . '/.staging' ) as $folder ) foreach ( is_dir( $folder ) ? scandir( $folder ) : array() as $name ) if ( ! in_array( $name, array( '.', '..', '.operation.lock', '.staging' ), true ) ) $list[] = $folder . '/' . $name; sort( $list ); return $list; };
/** «Crear respaldo» por admin-post y ticks de la cola hasta terminar; devuelve la ruta publicada o lanza el mensaje. */
$backup = function( $context ) use ( $post ) {
	$known = array_column( Digitalisimo_Backups_Jobs::all(), 'id' );
	list( $ok, $message ) = $post( array( 'Digitalisimo_Backups', 'run' ), Digitalisimo_Backups::ACTION_RUN, array( 'digitalisimo_backup_scope' => 'installation', 'digitalisimo_backup_destination' => 'local' ) + $context );
	if ( ! $ok ) throw new RuntimeException( $message );
	$job = null;
	foreach ( Digitalisimo_Backups_Jobs::all() as $candidate ) if ( ! in_array( $candidate['id'], $known, true ) ) $job = $candidate;
	if ( ! $job ) throw new RuntimeException( 'El respaldo no quedó en la cola: ' . $message );
	for ( $i = 0; $i < 200 && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ); $i++ ) {
		if ( (int) $job['retry_at'] > time() ) throw new RuntimeException( 'El trabajo espera un reintento: ' . ( $job['error']['message'] ?? '' ) );
		Digitalisimo_Backups_Jobs::tick();
		$job = Digitalisimo_Backups_Jobs::get( $job['id'] );
	}
	if ( 'success' !== ( $job['status'] ?? '' ) ) throw new RuntimeException( 'El trabajo no terminó: ' . ( $job['error']['message'] ?? $job['status'] ?? '' ) );
	return rtrim( Digitalisimo_Backups::storage_directory(), '/' ) . '/' . basename( $job['spec']['file'] );
};
// WP-Cron lo simula la sonda con los ticks: ninguna petición de bucle a wp-cron.php corre en paralelo.
add_filter( 'pre_http_request', function( $pre, $args, $url ) { return false !== strpos( (string) $url, 'wp-cron.php' ) ? new WP_Error( 'digitalisimo_probe_cron', 'WP-Cron lo simula la sonda.' ) : $pre; }, 10, 3 );
$timer = microtime( true );
$elapsed = function() use ( $timer ) { return round( microtime( true ) - $timer ) . ' s'; };
try {
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php', '', is_multisite() );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( $activated->get_error_message() );
	if ( ! class_exists( 'Digitalisimo_Backups' ) ) require_once WP_PLUGIN_DIR . '/digitalisimo-backups/digitalisimo-backups.php';
	if ( ! has_action( 'admin_post_' . Digitalisimo_Backups_Security::ACTION_SAVE ) ) Digitalisimo_Backups::init();
	wp_set_current_user( 1 );
	$log( 'Modo ' . $mode . ' · PHP ' . PHP_VERSION . ' · WordPress ' . get_bloginfo( 'version' ) . ( is_multisite() ? ' · Multisite' : '' ) . ' · libsodium nativo: ' . ( extension_loaded( 'sodium' ) ? 'sí' : 'no' ) . ' · cifrado disponible: ' . ( Digitalisimo_Backups_Sodium::available() ? 'sí' : 'no' ) );
	$K = 'Digitalisimo_Backups_Keys';
	$S = 'Digitalisimo_Backups_Security';
	$network = is_multisite();
	$context = $network ? array( 'digitalisimo_backups_network' => '1' ) : array();
	$save = function( $fields, $nonce = null ) use ( $post, $S, $context ) { return $post( array( $S, 'save' ), $S::ACTION_SAVE, $fields + $context, $nonce ); };
	$restore = function( $file, $confirmation, $secret = '' ) use ( $post, $context ) { return $post( array( 'Digitalisimo_Backups', 'restore_request' ), Digitalisimo_Backups::ACTION_RESTORE, array( 'backup_file' => $file, 'confirmation' => $confirmation, 'backup_secret' => $secret ) + $context ); };

	if ( 'constant' === $mode ) {
		// S6
		$constant_id = $K::key_id( base64_decode( DIGITALISIMO_BACKUPS_KEY ) );
		$check( 'valid' === $K::constant_state() && $S::settings()['encrypt'], 'S6 constante válida: cifrado obligatorio' );
		$plan = $S::plan( true );
		$check( $plan['encrypt'] && 'wp-config' === $plan['descriptor']['source'] && $constant_id === $plan['descriptor']['id'], 'S6 la constante firma y cifra lo nuevo (huella ' . $K::short( $constant_id ) . ')' );
		$html = $render( false );
		$check( false !== strpos( $html, 'wp-config.php · huella ' . $K::short( $constant_id ) ) && false === strpos( $html, DIGITALISIMO_BACKUPS_KEY ) && false === strpos( $html, rtrim( DIGITALISIMO_BACKUPS_KEY, '=' ) ) && false === strpos( $html, 'name="digitalisimo_backups_security[passphrase]"' ), 'S6 la pestaña muestra la huella, nunca el valor, y no pide frase' );
		list( $ok, $message ) = $save( array( 'digitalisimo_backups_security' => array( 'passphrase' => 'una frase cualquiera bastante larga', 'passphrase_repeat' => 'una frase cualquiera bastante larga', 'offsite' => '1' ) ) );
		$check( ! $ok && false !== strpos( $message, 'no se usa una frase' ) && ! $K::keyring()['keys'], 'S6 con la constante no se guarda una frase' );
		list( $ok ) = $save( array( 'digitalisimo_backups_security' => array( 'require_signed' => '' ) ) );
		$check( $ok && $S::settings()['encrypt'], 'S6 desmarcar el cifrado no lo apaga mientras exista la constante' );
		$safety = $call( 'create_network', 'safety-before-restore-' );
		$described = $S::describe( $safety );
		$report = $S::open( $safety, array(), '' );
		$trust = $report['trust'];
		$S::close( $report );
		$check( '.zip' === substr( $safety, -4 ) && $described['signed'] && $constant_id === $described['key_id'] && 'signed' === $trust, 'S6 la copia previa queda en claro y firmada con la constante · ' . $elapsed() );
		unlink( $safety );
		$log( 'TERMINADO · ' . $elapsed() );
		return;
	}

	// S1 (S5 en la red): la frase por admin-post, con caracteres que un saneado alteraría.
	$passphrase = "frase de playground con 'comillas', \\barra, <etiqueta> & más";
	list( $ok, $message, $url ) = $save( array( 'digitalisimo_backups_security' => array( 'passphrase' => $passphrase, 'passphrase_repeat' => $passphrase ) ) );
	$check( ! $ok && false !== strpos( $message, 'fuera de este sitio' ) && ! $K::keyring()['keys'], 'S1 sin «Guardé la frase fuera de este sitio» no se guarda nada' );
	try { $save( array( 'digitalisimo_backups_security' => array( 'passphrase' => $passphrase, 'passphrase_repeat' => $passphrase, 'offsite' => '1' ) ), 'nonce-falso' ); throw new RuntimeException( 'S1 un nonce inválido guardó la frase' ); }
	catch ( Probe_Die $e ) { $check( ! $K::keyring()['keys'], 'S1 nonce inválido rechazado sin cambios' ); }
	list( $ok, $message, $url ) = $save( array( 'digitalisimo_backups_security' => array( 'passphrase' => $passphrase, 'passphrase_repeat' => $passphrase, 'offsite' => '1' ) ) );
	$ring = $K::keyring();
	$key_id = $ring['active'];
	$stored = $network ? get_site_option( $K::KEYRING ) : get_option( $K::KEYRING );
	$check( $ok && $key_id && is_array( $stored ) && ( ! $network || false === get_option( $K::KEYRING ) ), 'S' . ( $network ? '5' : '1' ) . ' frase guardada por admin-post (' . $message . ')' . ( $network ? ' en el llavero de la red' : '' ) );
	$descriptor = $K::descriptor( $key_id, 'passphrase', $ring['keys'][ $key_id ]['kdf'] );
	$check( false !== $K::resolve_with_secret( $descriptor, $passphrase ) && false === $K::resolve_with_secret( $descriptor, wp_unslash( sanitize_text_field( $passphrase ) ) ), 'S1 la frase se guardó sin alterarse' );
	$check( false === strpos( rawurldecode( $url ), 'comillas' ) && false === strpos( wp_json_encode( $stored ), 'comillas' ), 'S1 ni la redirección ni el llavero contienen la frase' );
	$html = $render( $network );
	$check( false !== strpos( $html, 'Frase guardada · huella ' . $K::short( $key_id ) ) && false === strpos( $html, 'comillas' ) && false === strpos( $html, $stored['keys'][ $key_id ]['wrap']['box'] ) && false !== strpos( $html, 'value="" autocomplete="new-password"' ), 'S1 la pestaña muestra la huella sin la frase ni la clave envuelta' );
	update_option( 'blogname', 'Antes del respaldo' );
	$file = $backup( $context );
	$log( 'S1 respaldo: ' . basename( $file ) . ' · ' . size_format( filesize( $file ) ) . ' · ' . $elapsed() );
	$row = get_option( Digitalisimo_Backups::HISTORY )[0];
	$check( ! empty( $row['signed'] ) && $key_id === $row['key_id'] && 'zip' === $row['container'] && 'created' === $row['origin'], 'S1 historial con firma, huella ' . $K::short( $key_id ) . ' y origen' );
	$zip = new ZipArchive();
	$zip->open( $file );
	$sql = $zip->getFromName( 'database.sql' );
	$manifest = json_decode( $zip->getFromName( 'manifest.json' ), true );
	$zip->close();
	$check( false === strpos( $sql, 'digitalisimo_backups_vault_' ) && false !== strpos( $sql, 'Antes del respaldo' ) && in_array( ( $network ? 'sitemeta:' : 'options:' ) . 'digitalisimo_backups_vault_*', $manifest['excluded_secrets'], true ), 'S1 llavero fuera de database.sql y declarado en el manifiesto' );
	update_option( 'blogname', 'Cambiado después' );
	list( $ok, $message ) = $restore( basename( $file ), 'RESTAURAR' );
	wp_cache_flush();
	$check( $ok && 'Antes del respaldo' === get_option( 'blogname' ), 'S1 restauración por admin-post que revierte el cambio · ' . $elapsed() );
	$check( $K::keyring()['active'] === $key_id && $K::can_open( $key_id ) && ( ! $network || is_array( get_site_option( $K::KEYRING ) ) ), 'S1 llavero conservado tras restaurar' );
	$storage = dirname( $file );

	// S8: un respaldo del código anterior, creado y registrado por su propia clase, se restaura con el código actual.
	// Se ensaya en los dos modos: en el sitio individual tras S7 y en la red tras S5.
	$s8 = function() use ( $dir, $log, $check, $S, $K, $key_id, $network, $restore, $elapsed ) {
		$legacy_code = (string) @file_get_contents( $dir . '/legacy-class-backups.php' );
		$legacy_version = trim( (string) @file_get_contents( $dir . '/legacy-version' ) );
		if ( '' === $legacy_code ) {
			$log( 'S8 OMITIDO: falta la clase de la versión anterior (dist/ o rollback/)' );
		} else {
			$legacy_code = str_replace( "defined( 'ABSPATH' ) || exit;", '', preg_replace( '/^<\?php\s*/', '', $legacy_code ) );
			$legacy_code = preg_replace( '/\bfinal class Digitalisimo_Backups\b/', 'final class Digitalisimo_Backups_Probe_Legacy', $legacy_code, 1, $renamed );
			if ( 1 !== $renamed ) throw new RuntimeException( 'S8 no se encontró la clase en la versión ' . $legacy_version );
			eval( $legacy_code ); // phpcs:ignore Squiz.PHP.Eval
			$legacy = function( $method, ...$args ) { $r = new ReflectionMethod( 'Digitalisimo_Backups_Probe_Legacy', $method ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true ); return $r->invokeArgs( null, $args ); };
			$encrypt_before = get_option( $S::OPTION );
			update_option( 'blogname', 'Antes del respaldo de ' . $legacy_version );
			$old = $legacy( 'create_network' );
			$legacy( 'remember', $old, 'local', $network ? 'network' : 'installation', array( 'id' => 'manual', 'type' => 'full' ) );
			$manifest = ( function( $path ) { $z = new ZipArchive(); $z->open( $path ); $m = json_decode( (string) $z->getFromName( 'manifest.json' ), true ); $z->close(); return $m; } )( $old );
			$check( $legacy_version === ( $manifest['plugin_version'] ?? '' ) && ! isset( $manifest['auth'] ) && ! isset( $manifest['job_id'] ), 'S8 respaldo ' . basename( $old ) . ' creado por ' . $legacy_version . ' (sin firma ni trabajo) · ' . $elapsed() );
			update_option( 'blogname', 'Cambiado tras el respaldo de ' . $legacy_version );
			list( $ok, $message ) = $restore( basename( $old ), 'RESTAURAR' );
			wp_cache_flush();
			$check( $ok && 'Antes del respaldo de ' . $legacy_version === get_option( 'blogname' ) && $K::can_open( $key_id ), 'S8 el código actual restaura el respaldo de ' . $legacy_version . ' (registrado en este sitio) y conserva el llavero: ' . $message . ' · ' . $elapsed() );
			if ( false === $encrypt_before ) delete_option( $S::OPTION ); else update_option( $S::OPTION, $encrypt_before, false );
		}
	};

	if ( $network ) {
		// S5: un subsitio no gestiona la seguridad de la red.
		$sub = wpmu_create_blog( get_network()->domain, get_network()->path . 'sub/', 'Subsitio', 1 );
		if ( is_wp_error( $sub ) ) throw new RuntimeException( $sub->get_error_message() );
		switch_to_blog( $sub );
		try {
			list( $ok, $message ) = $post( array( $S, 'save' ), $S::ACTION_SAVE, array( 'digitalisimo_backups_security' => array( 'require_signed' => '1' ) ) );
			ob_start();
			$S::panel( false, array() );
			$panel = ob_get_clean();
		} finally {
			restore_current_blog();
		}
		$check( ! $ok && false !== strpos( $message, 'administración de red' ) && ! $S::settings()['require_signed'] && false === strpos( $panel, '<form' ) && false !== strpos( $panel, 'son de toda la red' ), 'S5 un subsitio ve el aviso y no puede cambiar la seguridad de la red' );
		$s8();
		$log( 'TERMINADO · ' . $elapsed() );
		return;
	}

	// S3
	$tampered = $storage . '/digitalisimo-manipulado.zip';
	copy( $file, $tampered );
	$zip->open( $tampered );
	$zip->addFromString( 'manifest.json', $zip->getFromName( 'manifest.json' ) . ' ' );
	$zip->close();
	update_option( 'blogname', 'Tras S1' );
	$safeties = count( glob( $storage . '/safety-before-restore-*' ) ?: array() );
	list( $ok, $message ) = $restore( basename( $tampered ), 'RESTAURAR SIN FIRMA' );
	wp_cache_flush();
	$check( ! $ok && false !== strpos( $message, 'firma' ) && 'Tras S1' === get_option( 'blogname' ) && count( glob( $storage . '/safety-before-restore-*' ) ?: array() ) === $safeties, 'S3 manipulado rechazado sin copia previa ni cambios: ' . $message );
	unlink( $tampered );
	// S4: sin la clave en el llavero, un archivo suelto es no confiable.
	$saved_ring = get_option( $K::KEYRING );
	delete_option( $K::KEYRING );
	$loose = $storage . '/digitalisimo-suelto.zip';
	copy( $file, $loose );
	list( $ok, $message ) = $restore( basename( $loose ), 'RESTAURAR' );
	$check( ! $ok && false !== strpos( $message, 'RESTAURAR SIN FIRMA' ) && 'Tras S1' === get_option( 'blogname' ), 'S4 RESTAURAR no basta para un archivo sin firma ni registro' );
	update_option( $S::OPTION, array( 'encrypt' => false, 'require_signed' => true ), false );
	list( $ok, $message ) = $restore( basename( $loose ), 'RESTAURAR SIN FIRMA' );
	$check( ! $ok && false !== strpos( $message, 'sólo permite' ) && 'Tras S1' === get_option( 'blogname' ), 'S4 «Restaurar sólo respaldos firmados» bloquea' );
	delete_option( $S::OPTION );
	list( $ok, $message ) = $restore( basename( $loose ), 'RESTAURAR SIN FIRMA' );
	wp_cache_flush();
	$check( $ok && 'Antes del respaldo' === get_option( 'blogname' ), 'S4 restaurado con RESTAURAR SIN FIRMA · ' . $elapsed() );
	update_option( $K::KEYRING, $saved_ring, false );
	unlink( $loose );
	// S7: AUTH_KEY o SECURE_AUTH_KEY cambiaron (la envoltura del llavero ya no corresponde).
	$ring = get_option( $K::KEYRING );
	$ring['keys'][ $key_id ]['wrap']['kw_id'] = str_repeat( 'f', 16 );
	update_option( $K::KEYRING, $ring, false );
	update_option( $S::OPTION, array( 'encrypt' => true, 'require_signed' => false ), false );
	$before = $tree( $storage );
	try { $backup( $context ); throw new LogicException( 'S7 se creó un respaldo con la frase sin abrir' ); }
	catch ( Exception $e ) { $check( false !== strpos( $e->getMessage(), 'Vuelve a escribirla' ) && false !== strpos( $e->getMessage(), 'No se creó un respaldo sin cifrar' ) && $tree( $storage ) === $before, 'S7 con las claves de wp-config.php cambiadas el respaldo cifrado falla cerrado y no deja archivos' ); }
	$html = $render( false );
	$check( false !== strpos( $html, 'vuelve a escribir la frase' ), 'S7 la pestaña pide volver a escribir la frase' );
	list( $ok, $message ) = $save( array( 'digitalisimo_backups_security' => array( 'passphrase' => $passphrase, 'passphrase_repeat' => $passphrase, 'offsite' => '1', 'encrypt' => '1' ) ) );
	$check( $ok && false !== strpos( $message, 'de nuevo' ) && $K::can_open( $key_id ) && $key_id === $K::keyring()['active'], 'S7 volver a escribir la frase la envuelve otra vez con la misma huella' );
	if ( ! file_exists( $dir . '/encrypt' ) ) delete_option( $S::OPTION );
	$log( 'S1, S3, S4 y S7 terminados · ' . $elapsed() );
	$s8();
	// S2
	if ( file_exists( $dir . '/encrypt' ) ) {
		update_option( 'blogname', 'Antes del cifrado' );
		$zips = glob( $storage . '/*.zip' ) ?: array();
		$sealed = $backup( $context );
		$check( '.dgbk' === substr( $sealed, -5 ) && ! glob( $storage . '/.staging/*' ) && ! glob( $storage . '/*.part' ) && ( glob( $storage . '/*.zip' ) ?: array() ) === $zips, 'S2 respaldo cifrado ' . size_format( filesize( $sealed ) ) . ' sin ZIP en claro ni restos · ' . $elapsed() );
		$check( false === strpos( (string) file_get_contents( $sealed, false, null, 0, 1048576 ), 'wp-config' ), 'S2 los nombres de archivo no viajan en claro' );
		update_option( 'blogname', 'Cambiado tras cifrar' );
		delete_option( $K::KEYRING );
		list( $ok, $message ) = $restore( basename( $sealed ), 'RESTAURAR' );
		$check( ! $ok && false !== strpos( $message, 'Escribe su frase o su clave; sin ella no se puede restaurar' ) && 'Cambiado tras cifrar' === get_option( 'blogname' ), 'S2 sin la frase: ' . $message );
		list( $ok, $message ) = $restore( basename( $sealed ), 'RESTAURAR', 'una frase equivocada pero larga' );
		$check( ! $ok && false !== strpos( $message, 'no corresponde a la clave' ) && 'Cambiado tras cifrar' === get_option( 'blogname' ), 'S2 frase equivocada rechazada sin cambios' );
		list( $ok, $message ) = $restore( basename( $sealed ), 'RESTAURAR', $passphrase );
		wp_cache_flush();
		$check( $ok && 'Antes del cifrado' === get_option( 'blogname' ) && ! glob( $storage . '/.staging/*' ), 'S2 restaurado en «otro servidor» con la frase · ' . $elapsed() );
	}
	$log( 'TERMINADO · ' . $elapsed() );
} catch ( Throwable $e ) {
	$log( 'FALLA: ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() );
}
