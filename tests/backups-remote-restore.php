<?php
/**
 * B1b, B2 e I13: restaurar desde un destino remoto y sincronizar los respaldos de un destino, también los de otro sitio.
 *
 * Con el motor real (trabajos, almacén, Seguridad, restauración por pasos) y un servidor WebDAV simulado con `Range`
 * (RFC 7233):
 *   1. traer al almacén la copia remota de un respaldo de este sitio es un trabajo `fetch` por fragmentos: un corte de
 *      red a mitad se reanuda desde lo ya recibido (nunca vuelve a pedir el principio) y el archivo sale idéntico;
 *   2. una copia remota alterada no entra en el almacén (SHA-256 del historial);
 *   3. sincronizar lista sólo los respaldos de DIGITALÍSIMO del destino (ni temporales `.part-`, ni la prueba de
 *      conexión, ni otros archivos), distingue los de este sitio de los de otro y no guarda identificadores remotos;
 *   4. un respaldo de otro sitio se trae como archivo no confiable: entra «sin registro», nunca en el historial, pide
 *      `RESTAURAR SIN FIRMA` y se restaura con la restauración por pasos; uno hostil se descarta sin reintentos;
 *   5. REST, WP-CLI (`sync`, `destinations backups`, `destinations fetch --from`, `restore --from`) y la pantalla usan
 *      los mismos casos de uso, sin secretos en ninguna salida; en una red, traer pide el alcance de red;
 *   6. el contrato: un fragmento distinto del pedido nunca se acepta, y un destino sin rangos no reanuda a mitad.
 */
$root = sys_get_temp_dir() . '/digitalisimo-remote-restore-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-coverage/env.php';
require __DIR__ . '/lib/destination-fakes.php';
digi_make_wordpress( $root . '/public' );
// Un archivo que no se comprime: el respaldo ocupa varios fragmentos.
file_put_contents( $root . '/public/wp-content/foto.bin', random_bytes( 400000 ) );
// Lo que la pantalla necesita y el entorno de cobertura no trae.
function absint( $value ) { return abs( (int) $value ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">'; }
function wp_delete_file( $file ) { return @unlink( $file ); }
function size_format( $bytes ) { return $bytes . ' B'; }
function selected( $a, $b, $echo = true ) { return (string) $a === (string) $b ? ' selected="selected"' : ''; }
function checked( $a, $b = true, $echo = true ) { return (string) $a === (string) $b ? ' checked="checked"' : ''; }
function disabled( $a, $b = true, $echo = true ) { return $a === $b ? ' disabled="disabled"' : ''; }
$GLOBALS['filters'] = array();
function apply_filters( $hook, $value, ...$args ) { return isset( $GLOBALS['filters'][ $hook ] ) ? call_user_func( $GLOBALS['filters'][ $hook ], $value, ...$args ) : $value; }
// Fragmentos de 64 KiB (el mínimo): la descarga necesita varios.
$GLOBALS['filters']['digitalisimo_backups_fetch_chunk'] = function() { return 65536; };

$B = 'Digitalisimo_Backups';
$ns = 'digitalisimo-backups/v1';
$store = $root . '/private';
$password = 'WebDav-Clave-Remota-7';
$dav = new Bk_Fake_Webdav( 'https://dav.ejemplo.test/remote.php/dav/files/ana/', 'ana', $password );
Digitalisimo_Backups_Http::$transport = $dav;
Digitalisimo_Backups_Http::$resolver = function( $host ) { return array( '203.0.113.10' ); };
/** Peticiones GET con `Range` al servidor: el byte en que empieza cada una. */
$ranges = function() use ( $dav ) {
	$out = array();
	foreach ( $dav->log as $entry ) if ( 'GET' === $entry['method'] && preg_match( '/^bytes=(\d+)-(\d+)$/', (string) ( $entry['headers']['Range'] ?? '' ), $m ) ) $out[] = (int) $m[1];
	return $out;
};
$staged = function() use ( $store ) { return array_merge( glob( $store . '/jobs/*/staging/remote*' ) ?: array(), glob( $store . '/jobs/*/staging/piece.tmp' ) ?: array() ); };
$history_files = function() use ( $B ) { return array_map( 'strval', array_column( (array) get_option( $B::HISTORY, array() ), 'file' ) ); };
/** Sin secretos: ni la contraseña de WebDAV ni su cabecera Basic en $text. */
$clean = function( $text, $where ) use ( $password ) {
	digi_assert( false === strpos( (string) $text, $password ) && false === strpos( (string) $text, base64_encode( 'ana:' . $password ) ), 'La contraseña del destino aparece en ' . $where . '.' );
};
$panel = function( $history = null ) use ( $B ) {
	$method = new ReflectionMethod( $B, 'restore_panel' );
	if ( PHP_VERSION_ID < 80100 ) $method->setAccessible( true );
	ob_start();
	$method->invoke( null, null === $history ? array_slice( (array) get_option( $B::HISTORY, array() ), 0, 10 ) : $history );
	return (string) ob_get_clean();
};

try {
	Digitalisimo_Backups_Rest::register();
	call_user_func( $GLOBALS['digi']['actions']['rest_api_init'][0] );
	Digitalisimo_Backups_Cli::register();
	$query = digi_admin_post( 'save', $B::ACTION_SAVE, array( 'digitalisimo_backups' => array( 'destination' => 'local', 'retention' => '100', 'webdav_url' => 'https://dav.ejemplo.test/remote.php/dav/files/ana/', 'webdav_user' => 'ana', 'webdav_password' => $password, 'webdav_path' => '/respaldos' ) ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ), 'No se guardó la conexión WebDAV: ' . json_encode( $query ) );

	// 0. Respaldo con copia remota verificada en WebDAV.
	$created = digi_backup( array( 'destination' => 'webdav' ) );
	digi_assert( ! is_wp_error( $created ), 'No se creó el respaldo hacia WebDAV: ' . ( is_wp_error( $created ) ? $created->get_error_message() : '' ) );
	$file = $created['file'];
	$bytes = file_get_contents( $store . '/' . $file );
	$sha = hash( 'sha256', $bytes );
	/** Un respaldo con su propio contenido (otro SHA-256), sin fila ni archivo en este sitio: el «de otro sitio». */
	$foreign = function() use ( $B, $store ) {
		$made = digi_backup();
		digi_assert( ! is_wp_error( $made ), 'No se creó un respaldo auxiliar.' );
		$data = file_get_contents( $store . '/' . $made['file'] );
		update_option( $B::HISTORY, array_values( array_filter( (array) get_option( $B::HISTORY, array() ), function( $row ) use ( $made ) { return $made['file'] !== $row['file']; } ) ) );
		unlink( $store . '/' . $made['file'] );
		return $data;
	};
	$other_bytes = $foreign();
	$third_bytes = $foreign();
	$last_bytes = $foreign();
	digi_assert( ( $dav->files[ 'respaldos/' . $file ] ?? '' ) === $bytes && strlen( $bytes ) > 4 * 65536, 'La copia remota no es el ZIP local o es demasiado pequeña para la prueba (' . strlen( $bytes ) . ' bytes).' );

	// 1. B1b: «Traer al almacén» es un trabajo por fragmentos que se reanuda tras un corte desde lo ya recibido.
	unlink( $store . '/' . $file );
	$dav->log = array();
	$gets = 0;
	$cut_at = null;
	$dav->faults['get'] = function( $request, $data ) use ( &$gets, &$cut_at, $store ) {
		if ( 3 !== ++$gets ) return null;
		$parts = glob( $store . '/jobs/*/staging/remote.part' );
		clearstatcache();
		$cut_at = $parts ? filesize( $parts[0] ) : -1;
		throw new Exception( 'Conexión reiniciada por el servidor' );
	};
	$first = $B::service_fetch( $file, array( 'drive_cli' => false ) );
	digi_assert( is_array( $first ) && ! empty( $first['running'] ) && false === $first['fetched'] && ! is_file( $store . '/' . $file ), 'Un corte a mitad no dejó la descarga en espera para reanudarla: ' . json_encode( $first ) );
	$job_id = $first['job']['id'];
	$job = Digitalisimo_Backups_Job_Store::read( $job_id );
	digi_assert( 'fetch' === $job['spec']['kind'] && 'receive' === $job['phase'] && $job['retry_at'] > time() && empty( $job['attempts']['receive'] ) && $cut_at > 0 && is_file( $store . '/jobs/' . $job_id . '/staging/remote.part' ) && $cut_at === filesize( $store . '/jobs/' . $job_id . '/staging/remote.part' ), 'El corte no conservó lo recibido para reanudar (o gastó un intento): ' . json_encode( array( 'phase' => $job['phase'], 'attempts' => $job['attempts'], 'cut_at' => $cut_at ) ) );
	digi_assert( 'fetch' === $first['job']['kind'] && 'Descarga del destino' === $first['job']['phase_label'] && 'desde WebDAV' === $first['job']['destination_label'], 'La tarjeta «Trabajos» no presenta la descarga: ' . json_encode( $first['job'] ) );
	$before = count( $ranges() );
	$job['retry_at'] = 0;
	Digitalisimo_Backups_Job_Store::write( $job );
	$done = $B::service_fetch( $file );
	$after = array_slice( $ranges(), $before );
	digi_assert( ! is_wp_error( $done ) && true === $done['fetched'] && $file === $done['file'] && $job_id === $done['job']['id'] && $sha === hash_file( 'sha256', $store . '/' . $file ), 'La descarga reanudada no dejó el respaldo verificado: ' . json_encode( is_wp_error( $done ) ? $done->get_error_message() : $done ) );
	digi_assert( $after && $cut_at === $after[0] && min( $after ) >= $cut_at, 'Tras el corte se volvió a pedir lo ya recibido: ' . json_encode( array( 'cut_at' => $cut_at, 'after' => $after ) ) );
	digi_assert( count( $ranges() ) >= 3 && 0 === $ranges()[0], 'La descarga no fue por fragmentos con Range: ' . json_encode( $ranges() ) );
	digi_assert( ! $staged() && 0600 === ( fileperms( $store . '/' . $file ) & 0777 ), 'La descarga dejó temporales o el archivo no es privado.' );
	unset( $dav->faults['get'] );
	$again = $B::service_fetch( $file );
	digi_assert( ! is_wp_error( $again ) && false === $again['fetched'] && false !== strpos( $again['message'], 'ya está' ), 'Traer un respaldo que ya está en el almacén lo descargó otra vez.' );

	// 2. Una copia remota alterada no entra en el almacén: el SHA-256 del historial lo impide, sin reintentos.
	unlink( $store . '/' . $file );
	$dav->files[ 'respaldos/' . $file ][ 70000 ] = chr( ord( $dav->files[ 'respaldos/' . $file ][ 70000 ] ) ^ 1 );
	$bad = $B::service_fetch( $file );
	digi_assert( is_wp_error( $bad ) && 502 === ( $bad->get_error_data()['status'] ?? 0 ) && false !== strpos( $bad->get_error_message(), 'SHA-256' ) && ! is_file( $store . '/' . $file ) && ! $staged(), 'Una copia remota alterada entró en el almacén: ' . ( is_wp_error( $bad ) ? $bad->get_error_message() : json_encode( $bad ) ) );
	$failed = Digitalisimo_Backups_Job_Store::read( $bad->get_error_data()['job'] );
	digi_assert( 'failed' === $failed['status'] && 'examine' === $failed['phase'] && ! empty( $failed['error']['permanent'] ), 'La copia alterada se reintentó como un fallo pasajero: ' . json_encode( $failed['error'] ) );
	$dav->files[ 'respaldos/' . $file ] = $bytes;
	digi_assert( ! is_wp_error( $B::service_fetch( $file ) ) && $sha === hash_file( 'sha256', $store . '/' . $file ), 'Con la copia íntegra, «Traer al almacén» no funcionó.' );

	// 3. B2: sincronizar lista sólo los respaldos de DIGITALÍSIMO y distingue los de este sitio de los de otro.
	$other = 'digitalisimo-backup-otro-sitio-test-20261101-101010-0a1b2c3d.zip';
	$third = 'digitalisimo-backup-tercero-test-20261103-030303-0c1d2e3f.zip';
	$dav->files[ 'respaldos/' . $other ] = $other_bytes;
	$dav->files[ 'respaldos/' . $third ] = $third_bytes;
	$dav->files[ 'respaldos/' . $file . '.part-0123456789ab' ] = 'temporal';
	$dav->files[ 'respaldos/digitalisimo-backups-prueba-20261101-101010-0a1b2c3d.txt' ] = 'prueba';
	$dav->files[ 'respaldos/notas.zip' ] = 'otro archivo';
	$dav->files[ 'respaldos/digitalisimo-backup-x.zip.php' ] = '<?php';
	$GLOBALS['digi']['caps'] = array();
	list( $code ) = digi_rest( 'POST', $ns . '/destinations/sync', array(), false );
	digi_assert( 401 === $code, 'Sincronizar sin sesión respondió ' . $code . '.' );
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	list( $code, $synced ) = digi_rest( 'POST', $ns . '/destinations/sync' );
	digi_assert( 200 === $code && 1 === count( $synced['destinations'] ) && 'webdav' === $synced['destinations'][0]['destination'] && 3 === $synced['destinations'][0]['count'] && 2 === $synced['destinations'][0]['importable'] && 1 === $synced['destinations'][0]['registered'] && 2 === $synced['destinations'][0]['other_sites'] && '' === $synced['destinations'][0]['error'], 'El resumen de la sincronización no es el esperado: ' . json_encode( $synced['destinations'] ?? $synced ) );
	$by = array();
	foreach ( $synced['items'] as $row ) $by[ $row['name'] ] = $row;
	digi_assert( array( $file, $other, $third ) == array_keys( $by ) || ( 3 === count( $by ) && isset( $by[ $file ], $by[ $other ], $by[ $third ] ) ), 'La sincronización listó otros archivos: ' . json_encode( array_keys( $by ) ) );
	digi_assert( 'local' === $by[ $file ]['state'] && true === $by[ $file ]['registered'] && 'this' === $by[ $file ]['origin'], 'El respaldo de este sitio no sale como suyo: ' . json_encode( $by[ $file ] ) );
	digi_assert( 'importable' === $by[ $other ]['state'] && false === $by[ $other ]['registered'] && 'other' === $by[ $other ]['origin'] && 'otro-sitio-test' === $by[ $other ]['site'] && '2026-11-01 10:10:10' === $by[ $other ]['date'] && strlen( $other_bytes ) === $by[ $other ]['size'] && false !== strpos( $by[ $other ]['label'], 'sin verificar' ), 'El respaldo de otro sitio no sale como importable sin verificar: ' . json_encode( $by[ $other ] ) );
	$index = get_option( $B::REMOTE_INDEX );
	digi_assert( is_array( $index ) && isset( $index['webdav'] ) && 3 === count( $index['webdav']['items'] ), 'La sincronización no guardó el índice.' );
	digi_assert( false === strpos( json_encode( array( $index, $synced ) ), 'respaldos/' ) && false === strpos( json_encode( $index ), '"path"' ), 'El índice o la respuesta guardan rutas remotas.' );
	$clean( json_encode( array( $index, $synced ) ), 'la sincronización' );
	$calls = count( $dav->log );
	list( $code, $cached ) = digi_rest( 'GET', $ns . '/destinations/sync' );
	digi_assert( 200 === $code && count( $dav->log ) === $calls && 3 === count( $cached['items'] ) && $synced['destinations'] === $cached['destinations'], 'GET /destinations/sync llamó al destino o no devolvió lo sincronizado: ' . json_encode( $cached ) );
	list( $code, $live ) = digi_rest( 'GET', $ns . '/destinations/webdav/backups' );
	digi_assert( 200 === $code && 3 === count( $live ) && count( $dav->log ) > $calls, 'GET /destinations/webdav/backups no listó el destino: ' . json_encode( $live ) );
	list( $code, $missing ) = digi_rest( 'GET', $ns . '/destinations/s3/backups' );
	digi_assert( 409 === $code, 'Un destino sin conexión se listó: ' . json_encode( $missing ) );

	// 4. Un respaldo de otro sitio: archivo no confiable, «sin registro», nunca en el historial, RESTAURAR SIN FIRMA.
	$rows = count( (array) get_option( $B::HISTORY, array() ) );
	list( $code, $imported ) = digi_rest( 'POST', $ns . '/destinations/webdav/backups/' . $other . '/fetch' );
	digi_assert( 200 === $code && $other === $imported['file'] && true === $imported['fetched'] && false === $imported['registered'] && 'untrusted' === $imported['trust'] && 'RESTAURAR SIN FIRMA' === $imported['confirmation'] && true === $imported['compatible'] && 'fetch' === $imported['job']['kind'], 'Traer el respaldo de otro sitio no dio el resultado esperado: ' . json_encode( $imported ) );
	digi_assert( hash( 'sha256', $other_bytes ) === hash_file( 'sha256', $store . '/' . $other ) && $rows === count( (array) get_option( $B::HISTORY, array() ) ) && ! in_array( $other, $history_files(), true ), 'El respaldo de otro sitio entró en el historial o no es el del destino.' );
	$html = $panel();
	digi_assert( preg_match( '#<optgroup label="Archivos del almacén sin registro">.*' . preg_quote( $other, '#' ) . '.*</optgroup>#s', $html ), 'El respaldo traído no aparece en «Restaurar» como «sin registro».' );
	$refused = $B::service_restore( $other, array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( is_wp_error( $refused ) && false !== strpos( $refused->get_error_message(), 'RESTAURAR SIN FIRMA' ), 'Un respaldo de otro sitio se restauró como si fuera de este.' );
	$GLOBALS['wpdb']->set( 'blogname', 'Cambiado después del respaldo' );
	$restored = $B::service_restore( $other, array( 'confirm' => 'RESTAURAR SIN FIRMA' ) );
	digi_assert( ! is_wp_error( $restored ) && 'untrusted' === $restored['trust'] && 'Sitio de prueba' === $GLOBALS['wpdb']->value( 'blogname' ), 'El respaldo traído de otro sitio no se restauró por pasos: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : json_encode( $restored ) ) );
	// Uno hostil con nombre de respaldo se descarta sin llegar al almacén y sin reintentos.
	$hostile = 'digitalisimo-backup-otro-sitio-test-20261102-101010-0b1c2d3e.zip';
	$dav->files[ 'respaldos/' . $hostile ] = "PK\x03\x04" . random_bytes( 70000 );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups destinations', 'fetch', array( $hostile ), array( 'from' => 'webdav' ) );
	digi_assert( 1 === $code && false !== strpos( implode( "\n", $out ), 'se descartó' ) && ! is_file( $store . '/' . $hostile ) && ! $staged(), 'Un archivo hostil del destino no se descartó: ' . implode( "\n", $out ) );
	$jobs = array_filter( Digitalisimo_Backups_Job_Store::all(), function( $job ) use ( $hostile ) { return $hostile === ( $job['spec']['fetch']['name'] ?? '' ); } );
	$job = reset( $jobs );
	digi_assert( $job && 'failed' === $job['status'] && 'examine' === $job['phase'] && empty( $job['attempts']['examine'] ), 'El archivo hostil se reintentó: ' . json_encode( $job ? $job['attempts'] : null ) );
	$dav->files[ 'respaldos/' . $hostile ] = str_repeat( 'basura ', 20000 );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups destinations', 'fetch', array( $hostile ), array( 'from' => 'webdav' ) );
	digi_assert( 1 === $code && false !== strpos( implode( "\n", $out ), 'no es un ZIP' ) && ! is_file( $store . '/' . $hostile ), 'Un archivo que no es un ZIP entró en el almacén: ' . implode( "\n", $out ) );

	// 5. WP-CLI: sync, destinations backups y restore --from (traer y restaurar en una orden).
	list( $code, $out ) = digi_wp( 'digitalisimo-backups', 'sync', array(), array( 'format' => 'json' ) );
	$listed = json_decode( (string) end( $out ), true );
	digi_assert( 0 === $code && 4 === count( $listed ) && in_array( $third, array_column( $listed, 'name' ), true ), 'wp … sync no listó los respaldos del destino: ' . implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups destinations', 'backups', array( 'webdav' ), array( 'format' => 'json' ) );
	digi_assert( 0 === $code && 4 === count( (array) json_decode( (string) end( $out ), true ) ), 'wp … destinations backups no listó el destino: ' . implode( "\n", $out ) );
	$clean( json_encode( $out ), 'WP-CLI' );
	$GLOBALS['wpdb']->set( 'blogname', 'Otra vez cambiado' );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups', 'restore', array( $third ), array( 'from' => 'webdav', 'confirm' => 'RESTAURAR SIN FIRMA' ) );
	digi_assert( 0 === $code && false !== strpos( implode( "\n", $out ), 'Restauración terminada desde ' . $third ) && 'Sitio de prueba' === $GLOBALS['wpdb']->value( 'blogname' ) && is_file( $store . '/' . $third ), 'wp … restore --from no trajo y restauró el respaldo: ' . implode( "\n", $out ) );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups destinations', 'fetch', array( 'notas.zip' ), array( 'from' => 'webdav' ) );
	digi_assert( 1 === $code && ! is_file( $store . '/notas.zip' ), 'Se trajo un archivo que no es un respaldo de DIGITALÍSIMO.' );

	// 6. Pantalla: «Sincronizar», la lista del índice y «Traer al almacén» (admin-post, nonce propio), sin secretos.
	$query = digi_admin_post( 'sync_request', $B::ACTION_SYNC, array( 'destination' => '' ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ) && false !== strpos( rawurldecode( (string) $query['message'] ), 'WebDAV: 4 respaldo(s)' ), '«Sincronizar» no respondió lo sincronizado: ' . json_encode( $query ) );
	$html = $panel();
	digi_assert( false !== strpos( $html, 'Respaldos en los destinos remotos' ) && false !== strpos( $html, 'value="' . $B::ACTION_SYNC . '"' ) && false !== strpos( $html, 'name="name" value="' . $hostile . '"' ) && false === strpos( $html, 'name="name" value="' . $other . '"' ), 'La pantalla no ofrece traer lo que falta (o lo ofrece de algo que ya está): ' . $html );
	$clean( $html, 'la pantalla' );
	$dav->files[ 'respaldos/' . $hostile ] = $last_bytes;
	$query = digi_admin_post( 'remote_fetch_request', $B::ACTION_REMOTE_FETCH, array( 'destination' => 'webdav', 'name' => $hostile ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ) && false !== strpos( rawurldecode( (string) $query['message'] ), 'RESTAURAR SIN FIRMA' ) && is_file( $store . '/' . $hostile ), '«Traer al almacén» de la pantalla no trajo el respaldo: ' . json_encode( $query ) );
	try {
		$_POST = array( 'destination' => 'webdav', 'name' => $third );
		$_REQUEST = array_merge( $_POST, array( '_wpnonce' => 'nonce-otra-accion' ) );
		$B::remote_fetch_request();
		throw new RuntimeException( 'FALLA: «Traer al almacén» aceptó otro nonce.' );
	} catch ( Digi_Die $die ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement

	// 7. Red: traer escribe en el almacén de toda la instalación y pide el alcance de red.
	$GLOBALS['digi']['multisite'] = true;
	try {
		$scope = $B::service_remote_fetch( 'webdav', $third );
		digi_assert( is_wp_error( $scope ) && 'digitalisimo_backups_scope' === $scope->get_error_code(), 'En una red se trajo un respaldo sin confirmar el alcance de red.' );
	} finally { $GLOBALS['digi']['multisite'] = false; }

	// 8. Ningún secreto en los trabajos ni en sus registros.
	foreach ( glob( $store . '/jobs/*/*' ) ?: array() as $path ) if ( is_file( $path ) ) $clean( file_get_contents( $path ), basename( dirname( $path ) ) . '/' . basename( $path ) );
	list( , $jobs ) = digi_rest( 'GET', $ns . '/jobs', array(), true, array( 'limit' => 50 ) );
	$clean( json_encode( $jobs ), 'GET /jobs' );

	// 9. El contrato: un fragmento distinto del pedido nunca se acepta; sin rangos no se reanuda a mitad.
	$webdav = new Digitalisimo_Backups_Destination_Webdav( array(), array( 'connection' => 'webdav' ) );
	$reply = Closure::bind( function( ...$args ) { return static::range_reply( ...$args ); }, $webdav, 'Digitalisimo_Backups_Destination_Webdav' );
	$piece = $root . '/pieza.bin';
	file_put_contents( $piece, str_repeat( 'x', 10 ) );
	digi_assert( 10 === $reply( array( 'code' => 206, 'headers' => array( 'content-range' => 'bytes 10-19/100' ) ), $piece, 10, 10 ), 'Un fragmento exacto no se aceptó.' );
	foreach ( array( 'otro principio' => array( array( 'code' => 206, 'headers' => array( 'content-range' => 'bytes 0-9/100' ) ), 10, 10 ), 'más largo que lo pedido' => array( array( 'code' => 206, 'headers' => array( 'content-range' => 'bytes 10-19/100' ) ), 10, 5 ), 'sin Content-Range' => array( array( 'code' => 206, 'headers' => array() ), 10, 10 ), 'el archivo entero a mitad' => array( array( 'code' => 200, 'headers' => array() ), 10, 10 ), 'incompleto' => array( array( 'code' => 206, 'headers' => array( 'content-range' => 'bytes 10-29/100' ) ), 10, 20 ) ) as $label => $case ) {
		try {
			$reply( $case[0], $piece, $case[1], $case[2] );
			throw new RuntimeException( 'FALLA: se aceptó un fragmento ' . $label . '.' );
		} catch ( RuntimeException $e ) {
			throw $e;
		} catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	$ftp = new Digitalisimo_Backups_Destination_Ftp( array( 'ftp_host' => 'ftp.ejemplo.test', 'ftp_user' => 'u', 'ftp_password' => 'p' ), array( 'connection' => 'ftp' ) );
	digi_assert( false === $ftp->ranged() && true === $webdav->ranged(), 'FTP dice leer por rangos o WebDAV no.' );
	try {
		$ftp->get_range( array( 'provider' => 'ftp', 'path' => '/x.zip' ), $piece, 1024, 1024 );
		throw new RuntimeException( 'FALLA: FTP reanudó una descarga a mitad.' );
	} catch ( Digitalisimo_Backups_Permanent_Error $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement

	echo "OK: restaurar desde un destino remoto y sincronizar (B1b, B2, I13)\n";
} finally {
	digi_rmtree( $root );
}
