<?php
/**
 * Reanudación tras una muerte en cada punto: el tick siguiente descarta lo que quedó a medias, sigue desde el punto de
 * control sin repetir lo confirmado, registra una sola fila y nunca presenta un archivo parcial como respaldo.
 */
require __DIR__ . '/backups-jobs/env.php';
bk_no_backoff();
$db = $GLOBALS['wpdb'];
function until_phase( $id, $phase ) {
	for ( $i = 0; $i < 20; $i++ ) {
		$job = bk_job( $id );
		if ( $phase === $job['phase'] ) return $job;
		Digitalisimo_Backups_Jobs::tick( 0 );
		bk_assert_no_unverified_final( bk_job( $id ) );
	}
	bk_fail( 'El trabajo no llegó a la fase ' . $phase );
}
function rows_for( $file ) { return array_values( array_filter( bk_history(), function( $row ) use ( $file ) { return ( $row['file'] ?? '' ) === $file; } ) ); }
/** Todo lo registrado que sigue en el almacén (la retención global rota archivos, no filas) pasa la verificación completa. */
function assert_history_verified() {
	foreach ( bk_history() as $row ) {
		$path = bk_storage() . '/' . $row['file'];
		if ( ! is_file( $path ) ) continue;
		bk_assert( hash_equals( $row['sha256'], hash_file( 'sha256', $path ) ), 'Una fila del historial no corresponde a su archivo: ' . $row['file'] );
		Digitalisimo_Backups::job_verify_package( $path );
	}
}

// Referencia: una corrida sin cortes.
$reference = bk_manual( 'local', 'referencia' );
bk_run_until_done( $reference['id'] );
$expected = bk_zip_entries( bk_storage() . '/' . $reference['spec']['file'] )['entries'];

// A. Muerte durante el empaquetado: la base de datos exportada no se repite.
$a = bk_manual( 'local', 'corte-pack' );
$exports = $db->exports;
until_phase( $a['id'], 'pack' );
bk_assert( $exports + 1 === $db->exports, 'La base de datos no se exportó una vez.' );
$database = bk_job( $a['id'] )['checkpoint']['database'];
bk_kill_in( $a['id'], 'pack', array( 'package.dddddddd.zip' => 'PK a medias', 'package.dddddddd.zip.a1B2c3' => 'temporal de libzip' ) );
Digitalisimo_Backups_Jobs::recover_if_idle();
$job = bk_job( $a['id'] );
bk_assert( 'queued' === $job['status'] && 'pack' === $job['phase'] && 1 === $job['attempts']['pack'] && ! glob( bk_storage() . '/jobs/' . $a['id'] . '/staging/package.*' ), 'La muerte en el empaquetado no se reconoció o dejó el ZIP parcial.' );
bk_assert( is_file( bk_storage() . '/jobs/' . $a['id'] . '/staging/' . $database['file'] ), 'La reanudación borró el SQL ya confirmado.' );
bk_run_until_done( $a['id'] );
$job = bk_job( $a['id'] );
$manifest = bk_zip_entries( bk_storage() . '/' . $a['spec']['file'] );
bk_assert( 'success' === $job['status'] && $exports + 1 === $db->exports && $database === $job['checkpoint']['database'], 'Tras la muerte en el empaquetado se volvió a exportar la base de datos.' );
bk_assert( $expected === $manifest['entries'] && $database['sha256'] === $manifest['entries']['database.sql']['sha256'] && $a['id'] === $manifest['job_id'] && 'ziparchive' === $manifest['packer'], 'El manifiesto reanudado difiere del de una corrida sin cortes.' );
bk_assert( 1 === count( rows_for( $a['spec']['file'] ) ) && $a['id'] === rows_for( $a['spec']['file'] )[0]['job'], 'El respaldo reanudado no tiene una sola fila con su trabajo.' );

// B. Muerte durante la exportación: el SQL parcial se descarta y la exportación se repite entera, una sola vez.
$b = bk_manual( 'local', 'corte-db' );
until_phase( $b['id'], 'database' );
bk_kill_in( $b['id'], 'database', array( 'database.dddddddd.sql.part' => "INSERT INTO `wp_posts` VALUES ('a me" ) );
$exports = $db->exports;
Digitalisimo_Backups_Jobs::recover_if_idle();
bk_assert( 1 === bk_job( $b['id'] )['attempts']['database'] && ! glob( bk_storage() . '/jobs/' . $b['id'] . '/staging/database.*' ), 'El SQL parcial sobrevivió a la reanudación.' );
bk_run_until_done( $b['id'] );
bk_assert( 'success' === bk_job( $b['id'] )['status'] && $exports + 1 === $db->exports, 'La exportación interrumpida no se repitió exactamente una vez.' );
bk_assert( $expected === bk_zip_entries( bk_storage() . '/' . $b['spec']['file'] )['entries'], 'El paquete tras rehacer la base de datos no coincide.' );

// C. Muerte tras el rename y antes de registrar: se reconoce el ZIP verificado y se registra una sola vez.
$c = bk_manual( 'local', 'corte-registro' );
$job = until_phase( $c['id'], 'publish' );
$final = bk_storage() . '/' . $c['spec']['file'];
rename( bk_storage() . '/jobs/' . $c['id'] . '/staging/' . $job['checkpoint']['pack']['file'], $final );
bk_kill_in( $c['id'], 'publish' );
bk_assert( ! rows_for( $c['spec']['file'] ), 'La prueba no reprodujo un ZIP sin registrar.' );
bk_run_until_done( $c['id'] );
bk_assert( 'success' === bk_job( $c['id'] )['status'] && 1 === count( rows_for( $c['spec']['file'] ) ) && 1 === bk_job( $c['id'] )['attempts']['publish'], 'El ZIP renombrado no se registró una sola vez.' );
// Un archivo con el nombre final que no es el verificado nunca se registra.
$d = bk_manual( 'local', 'impostor' );
$job = until_phase( $d['id'], 'publish' );
unlink( bk_storage() . '/jobs/' . $d['id'] . '/staging/' . $job['checkpoint']['pack']['file'] );
file_put_contents( bk_storage() . '/' . $d['spec']['file'], 'otro contenido con el mismo nombre' );
bk_run_until_done( $d['id'] );
$job = bk_job( $d['id'] );
bk_assert( 'failed' === $job['status'] && $job['error']['permanent'] && ! rows_for( $d['spec']['file'] ), 'Se registró un archivo que no era el ZIP verificado.' );
unlink( bk_storage() . '/' . $d['spec']['file'] );

// D. Muerte durante la verificación: se verifica de nuevo el mismo ZIP, sin reconstruirlo.
$e = bk_manual( 'local', 'corte-verify' );
$job = until_phase( $e['id'], 'verify' );
$package = $job['checkpoint']['pack']['file'];
bk_kill_in( $e['id'], 'verify' );
bk_run_until_done( $e['id'] );
$job = bk_job( $e['id'] );
bk_assert( 'success' === $job['status'] && $package === $job['checkpoint']['pack']['file'] && 1 === $job['attempts']['verify'], 'La verificación interrumpida reconstruyó el ZIP.' );
// Un ZIP que cambia en el área de trabajo entre empaquetado y verificación se rehace, no se acepta.
$f = bk_manual( 'local', 'zip-alterado' );
$job = until_phase( $f['id'], 'verify' );
$staged = bk_storage() . '/jobs/' . $f['id'] . '/staging/' . $job['checkpoint']['pack']['file'];
file_put_contents( $staged, 'X', FILE_APPEND );
Digitalisimo_Backups_Jobs::tick( 0 );
$job = bk_job( $f['id'] );
bk_assert( 'pack' === $job['phase'] && 1 === $job['attempts']['verify'] && ! isset( $job['checkpoint']['pack'] ), 'Un ZIP alterado tras empaquetarse no volvió al empaquetado.' );
bk_run_until_done( $f['id'] );
bk_assert( 'success' === bk_job( $f['id'] )['status'], 'El ZIP rehecho no terminó.' );
// La fase de verificación lee de verdad cada entrada: un ZIP dañado por dentro con la misma huella (tamaño y fecha)
// vuelve al empaquetado y nunca llega al nombre final ni al historial.
$v = bk_manual( 'local', 'zip-danado' );
$job = until_phase( $v['id'], 'verify' );
$staged = bk_storage() . '/jobs/' . $v['id'] . '/staging/' . $job['checkpoint']['pack']['file'];
$entry = 'wordpress/wp-content/uploads/2026/11/foto.jpg';
$probe = new ZipArchive();
bk_assert( true === $probe->open( $staged ), 'La prueba no pudo abrir el ZIP del área de trabajo.' );
$compressed = (int) $probe->statName( $entry )['comp_size'];
$probe->close();
$raw = file_get_contents( $staged );
$header = strpos( $raw, $entry ) - 30;
bk_assert( $header >= 0 && "PK\x03\x04" === substr( $raw, $header, 4 ) && $compressed > 2, 'La prueba no encontró la cabecera local de la entrada.' );
$data = $header + 30 + unpack( 'v', substr( $raw, $header + 26, 2 ) )[1] + unpack( 'v', substr( $raw, $header + 28, 2 ) )[1];
$offset = $data + intdiv( $compressed, 2 );
$raw[ $offset ] = chr( ord( $raw[ $offset ] ) ^ 0xff );
$mtime = filemtime( $staged );
file_put_contents( $staged, $raw );
touch( $staged, $mtime );
clearstatcache();
bk_assert( Digitalisimo_Backups_Backup_Job::same_fingerprint( Digitalisimo_Backups_Backup_Job::fingerprint( $staged ), $job['checkpoint']['pack']['fp'] ), 'La prueba no conservó la huella del ZIP dañado.' );
Digitalisimo_Backups_Jobs::tick( 0 );
$job = bk_job( $v['id'] );
bk_assert( 'pack' === $job['phase'] && 1 === $job['attempts']['verify'] && false !== strpos( (string) $job['error']['message'], 'verificación del ZIP falló' ), 'Un ZIP dañado por dentro superó la verificación.' );
bk_assert( ! is_file( bk_storage() . '/' . $v['spec']['file'] ) && ! rows_for( $v['spec']['file'] ), 'Un ZIP dañado llegó al nombre final o al historial.' );
bk_run_until_done( $v['id'] );
bk_assert( 'success' === bk_job( $v['id'] )['status'] && 1 === count( rows_for( $v['spec']['file'] ) ), 'El ZIP rehecho tras el daño no terminó.' );
Digitalisimo_Backups::job_verify_package( bk_storage() . '/' . $v['spec']['file'] );

// E. Muerte durante la transferencia: el ZIP local ya está registrado y verificado; sólo se repite la copia.
update_option( 'digitalisimo_backups_settings', array( 'google_client_id' => 'cliente', 'google_client_secret' => 'secreto', 'google_refresh_token' => 'renovar', 'google_folder_id' => '' ) );
$GLOBALS['bk_remote'] = bk_google_ok();
$g = bk_manual( 'google', 'corte-transfer' );
$exports = $db->exports;
until_phase( $g['id'], 'transfer' );
$row = rows_for( $g['spec']['file'] )[0];
bk_assert( 'local' === $row['destination'] && 'pending' === $row['transfer'] && 'google' === $row['transfer_destination'] && $g['id'] === $row['job'], 'Antes de transferir, el ZIP no quedó registrado como local con la copia pendiente.' );
bk_kill_in( $g['id'], 'transfer' );
$GLOBALS['bk_uploaded'] = '';
bk_run_until_done( $g['id'] );
$rows = rows_for( $g['spec']['file'] );
bk_assert( 'success' === bk_job( $g['id'] )['status'] && 1 === count( $rows ) && 'google' === $rows[0]['destination'] && 'ok' === $rows[0]['transfer'] && 'google' === $rows[0]['remote']['provider'] && 'archivo-remoto' === $rows[0]['remote']['id'], 'La transferencia reanudada no actualizó la fila única.' );
bk_assert( $exports + 1 === $db->exports && 1 === bk_job( $g['id'] )['attempts']['transfer'] && is_file( bk_storage() . '/' . $g['spec']['file'] ), 'La transferencia reanudada rehizo el respaldo o perdió la copia local.' );
bk_assert( $GLOBALS['bk_uploaded'] === file_get_contents( bk_storage() . '/' . $g['spec']['file'] ), 'La copia remota no es idéntica al ZIP local.' );
// Credenciales retiradas durante la transferencia: fallo permanente y el ZIP local se queda registrado.
$h = bk_manual( 'google', 'sin-credenciales' );
until_phase( $h['id'], 'transfer' );
update_option( 'digitalisimo_backups_settings', array( 'google_client_id' => 'cliente', 'google_client_secret' => 'secreto', 'google_refresh_token' => '' ) );
bk_run_until_done( $h['id'] );
$job = bk_job( $h['id'] );
$row = rows_for( $h['spec']['file'] )[0];
bk_assert( 'failed' === $job['status'] && $job['error']['permanent'] && 'local' === $row['destination'] && 'failed' === $row['transfer'] && '' !== $row['transfer_error'], 'Sin credenciales, el trabajo no falló dejando el respaldo local registrado.' );
bk_assert( ! is_dir( bk_storage() . '/jobs/' . $h['id'] . '/staging' ), 'El trabajo fallido conservó su área de trabajo.' );

// Una copia previa (restauración) que muere no se reintenta: falla y limpia su área de trabajo.
$create = new ReflectionMethod( 'Digitalisimo_Backups', 'create_network' );
if ( PHP_VERSION_ID < 80100 ) $create->setAccessible( true );
$safety = $create->invoke( null, 'safety-before-restore-' );
bk_assert( 0 === strpos( basename( $safety ), 'safety-before-restore-digitalisimo-backup-' ) && is_file( $safety ), 'La copia previa no nació con su nombre protegido.' );
Digitalisimo_Backups::job_verify_package( $safety );
unlink( $safety );
$inline = Digitalisimo_Backups_Job_Store::create( array_merge( bk_job( $reference['id'] ), array( 'id' => Digitalisimo_Backups_Job_Store::new_id(), 'status' => 'running', 'phase' => 'pack', 'spec' => array_merge( $reference['spec'], array( 'inline' => true, 'policy' => 'pre-restore' ) ), 'checkpoint' => array(), 'attempts' => array(), 'finished_at' => 0 ) ) );
bk_kill_in( $inline['id'], 'pack', array( 'package.dddddddd.zip' => 'PK' ) );
Digitalisimo_Backups_Jobs::tick();
bk_assert( 'failed' === bk_job( $inline['id'] )['status'] && ! is_dir( bk_storage() . '/jobs/' . $inline['id'] . '/staging' ), 'Una copia previa interrumpida se reintentó o dejó su área de trabajo.' );

// Ningún respaldo intermedio en la raíz del almacén ni SQL fuera de él; todo lo registrado verifica.
bk_assert( ! glob( bk_storage() . '/digitalisimo-*.zip.*' ) && ! glob( sys_get_temp_dir() . '/digitalisimo-sql-*' ), 'Quedaron temporales de libzip o SQL fuera del área de trabajo.' );
assert_history_verified();
bk_assert( ! Digitalisimo_Backups_Jobs::has_active(), 'Quedaron trabajos activos.' );

// G. La retención de otro trabajo nunca borra el ZIP verificado que espera su copia remota (reintento en espera).
$fails = 1;
$google = bk_google_ok();
$GLOBALS['bk_remote'] = function( $method, $url, $args ) use ( &$fails, $google ) {
	if ( 'PUT' === $method && $fails > 0 ) { $fails--; return array( 'response' => array( 'code' => 503 ), 'body' => '' ); }
	return $google( $method, $url, $args );
};
update_option( 'digitalisimo_backups_settings', array( 'google_client_id' => 'cliente', 'google_client_secret' => 'secreto', 'google_refresh_token' => 'renovar', 'google_folder_id' => '' ) );
$waiting = bk_manual( 'google', 'copia-en-espera' );
for ( $i = 0; $i < 20 && ! ( 'transfer' === bk_job( $waiting['id'] )['phase'] && 'queued' === bk_job( $waiting['id'] )['status'] ); $i++ ) Digitalisimo_Backups_Jobs::tick( 0 );
Digitalisimo_Backups_Job_Store::with_lock( function() use ( $waiting ) { $job = Digitalisimo_Backups_Job_Store::read( $waiting['id'] ); $job['retry_at'] = time() + 3600; Digitalisimo_Backups_Job_Store::write( $job ); } );
bk_assert( 'pending' === rows_for( $waiting['spec']['file'] )[0]['transfer'] && 1 === bk_job( $waiting['id'] )['attempts']['transfer'], 'La copia remota no quedó en espera de reintento.' );
foreach ( (array) glob( bk_storage() . '/digitalisimo-*.zip' ) as $older ) touch( $older, time() - 120 );
touch( bk_storage() . '/' . $waiting['spec']['file'], time() - 60 );
$keep_one = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'manual', 'destination' => 'local', 'retain' => 1, 'retention' => 'global', 'via' => 'test' ) ) )['job'];
bk_run_until_done( $keep_one['id'] );
bk_assert( 'success' === bk_job( $keep_one['id'] )['status'] && is_file( bk_storage() . '/' . $keep_one['spec']['file'] ), 'El respaldo con retención 1 no terminó.' );
bk_assert( is_file( bk_storage() . '/' . $waiting['spec']['file'] ), 'La retención de otro trabajo borró el ZIP que esperaba su copia remota.' );
$GLOBALS['bk_uploaded'] = '';
bk_ready( $waiting['id'] );
bk_run_until_done( $waiting['id'] );
$row = rows_for( $waiting['spec']['file'] )[0];
bk_assert( 'success' === bk_job( $waiting['id'] )['status'] && 'ok' === $row['transfer'] && 'google' === $row['remote']['provider'], 'La copia remota en espera no se completó.' );
// Ya sin trabajo que lo necesite, la siguiente poda lo rota como cualquier otro.
touch( bk_storage() . '/' . $keep_one['spec']['file'], time() - 30 );
$after = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'manual', 'destination' => 'local', 'retain' => 1, 'retention' => 'global', 'via' => 'test' ) ) )['job'];
bk_run_until_done( $after['id'] );
bk_assert( ! is_file( bk_storage() . '/' . $waiting['spec']['file'] ) && is_file( bk_storage() . '/' . $after['spec']['file'] ), 'La retención dejó de rotar un ZIP cuyo trabajo ya terminó.' );

// I. Puntos de control del registro y del empaquetado.
// Un ZIP que cambia entre la verificación y el registro vuelve al empaquetado: nunca llega al nombre final.
$changed = bk_manual( 'local', 'cambio-tras-verificar' );
$job = until_phase( $changed['id'], 'publish' );
file_put_contents( bk_storage() . '/jobs/' . $changed['id'] . '/staging/' . $job['checkpoint']['pack']['file'], 'X', FILE_APPEND );
Digitalisimo_Backups_Jobs::tick( 0 );
$job = bk_job( $changed['id'] );
bk_assert( 'pack' === $job['phase'] && 1 === $job['attempts']['publish'] && ! isset( $job['checkpoint']['verify'] ) && ! is_file( bk_storage() . '/' . $changed['spec']['file'] ) && ! rows_for( $changed['spec']['file'] ), 'Un ZIP alterado después de verificarse llegó al nombre final o al historial.' );
bk_run_until_done( $changed['id'] );
bk_assert( 'success' === bk_job( $changed['id'] )['status'] && 1 === count( rows_for( $changed['spec']['file'] ) ), 'El ZIP rehecho tras el cambio no terminó.' );
// Un archivo que ya ocupa el nombre final (otro respaldo) nunca se sobrescribe.
$taken = bk_manual( 'local', 'nombre-ocupado' );
until_phase( $taken['id'], 'publish' );
file_put_contents( bk_storage() . '/' . $taken['spec']['file'], 'otro respaldo con el mismo nombre' );
bk_run_until_done( $taken['id'] );
$job = bk_job( $taken['id'] );
bk_assert( 'failed' === $job['status'] && $job['error']['permanent'] && 'otro respaldo con el mismo nombre' === file_get_contents( bk_storage() . '/' . $taken['spec']['file'] ) && ! rows_for( $taken['spec']['file'] ), 'El registro sobrescribió un archivo que ya tenía el nombre final.' );
unlink( bk_storage() . '/' . $taken['spec']['file'] );
// Muerte después de registrar y antes de guardar el avance: al repetir el registro queda una sola fila.
$twice = bk_manual( 'local', 'registro-doble' );
$job = until_phase( $twice['id'], 'publish' );
$final = bk_storage() . '/' . $twice['spec']['file'];
rename( bk_storage() . '/jobs/' . $twice['id'] . '/staging/' . $job['checkpoint']['pack']['file'], $final );
Digitalisimo_Backups::job_register( $final, $twice['spec'], $twice['id'] );
// Mientras tanto, el administrador lo bloquea desde otra petición.
bk_external_update_option( Digitalisimo_Backups::HISTORY, array_map( function( $row ) use ( $twice ) { if ( $twice['spec']['file'] === $row['file'] ) $row['locked'] = true; return $row; }, bk_options_load()[ Digitalisimo_Backups::HISTORY ] ) );
bk_kill_in( $twice['id'], 'publish' );
bk_run_until_done( $twice['id'] );
bk_assert( 'success' === bk_job( $twice['id'] )['status'] && 1 === count( rows_for( $twice['spec']['file'] ) ), 'El registro repetido tras una muerte duplicó la fila del historial.' );
bk_assert( ! empty( rows_for( $twice['spec']['file'] )[0]['locked'] ), 'El registro repetido tras una muerte quitó el bloqueo del respaldo.' );
// Un SQL que cambia en el área de trabajo después de exportarse se exporta otra vez antes de empaquetar.
$sql_changed = bk_manual( 'local', 'sql-alterado' );
$job = until_phase( $sql_changed['id'], 'pack' );
$exports = $db->exports;
file_put_contents( bk_storage() . '/jobs/' . $sql_changed['id'] . '/staging/' . $job['checkpoint']['database']['file'], "-- añadido\n", FILE_APPEND );
Digitalisimo_Backups_Jobs::tick( 0 );
$job = bk_job( $sql_changed['id'] );
bk_assert( 'database' === $job['phase'] && 1 === $job['attempts']['pack'] && ! isset( $job['checkpoint']['database'] ), 'Un SQL alterado tras exportarse no volvió a la exportación.' );
bk_run_until_done( $sql_changed['id'] );
bk_assert( 'success' === bk_job( $sql_changed['id'] )['status'] && $exports + 1 === $db->exports && $expected === bk_zip_entries( bk_storage() . '/' . $sql_changed['spec']['file'] )['entries'], 'El paquete tras exportar otra vez el SQL alterado no coincide.' );

// H. Una copia remota que sube completa pero no supera la verificación se retira del destino en cada intento: los
// reintentos no dejan allí copias sin verificar con el nombre del respaldo, y el ZIP local verificado se conserva.
$cloud = array();
$GLOBALS['bk_remote'] = function( $method, $url, $args ) use ( &$cloud ) {
	if ( 'POST' === $method && false !== strpos( $url, 'oauth2' ) ) return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'access_token' => 'token-de-prueba' ) ) );
	if ( 'POST' === $method ) return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'location' => 'https://upload.test/session/' . count( $cloud ) ), 'body' => '' );
	if ( 'PUT' === $method ) { $id = 'copia-' . count( $cloud ); $cloud[ $id ] = true; return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'id' => $id ) ) ); }
	if ( 'GET' === $method ) { file_put_contents( $args['filename'], 'otra cosa' ); return array( 'response' => array( 'code' => 200 ) ); }
	if ( 'DELETE' === $method && preg_match( '#/drive/v3/files/([^/?]+)$#', $url, $m ) && 'Bearer token-de-prueba' === ( $args['headers']['Authorization'] ?? '' ) ) unset( $cloud[ $m[1] ] );
	return array( 'response' => array( 'code' => 204 ), 'body' => '' );
};
update_option( 'digitalisimo_backups_settings', array( 'google_client_id' => 'cliente', 'google_client_secret' => 'secreto', 'google_refresh_token' => 'renovar', 'google_folder_id' => '' ) );
$mismatch = bk_manual( 'google', 'copia-distinta' );
for ( $i = 0; $i < 40 && ! Digitalisimo_Backups_Job_Store::is_terminal( bk_job( $mismatch['id'] ) ); $i++ ) { bk_ready( $mismatch['id'] ); Digitalisimo_Backups_Jobs::tick( 0 ); }
$job = bk_job( $mismatch['id'] );
$row = rows_for( $mismatch['spec']['file'] )[0];
bk_assert( 'failed' === $job['status'] && 'transfer' === $job['phase'] && 'local' === $row['destination'] && 'failed' === $row['transfer'] && is_file( bk_storage() . '/' . $mismatch['spec']['file'] ), 'La copia remota distinta no dejó el respaldo local registrado.' );
bk_assert( ! $cloud && false !== strpos( $job['error']['message'], 'Se retiró del destino la copia sin verificar' ), 'Quedaron en el destino ' . count( $cloud ) . ' copias sin verificar.' );

// J. Un proceso largo (WP-CLI o varios trabajos seguidos en la misma petición) lee el historial de la base de datos, no
// de su caché: un «Bloquear» pulsado mientras tanto en otra petición no se pierde y la retención no borra ese respaldo.
$long_spec = function( $policy ) { return Digitalisimo_Backups_Jobs::spec( array( 'policy' => $policy, 'destination' => 'local', 'retain' => 1, 'retention' => 'global', 'via' => 'test' ) ); };
$kept = Digitalisimo_Backups_Jobs::enqueue( $long_spec( 'largo-1' ) )['job'];
bk_run_until_done( $kept['id'] );
bk_assert( rows_for( $kept['spec']['file'] ) && is_file( bk_storage() . '/' . $kept['spec']['file'] ), 'El primer respaldo del proceso largo no terminó.' );
$stored = bk_options_load()[ Digitalisimo_Backups::HISTORY ];
foreach ( $stored as &$stored_row ) if ( $kept['spec']['file'] === $stored_row['file'] ) $stored_row['locked'] = true;
unset( $stored_row );
bk_external_update_option( Digitalisimo_Backups::HISTORY, $stored );
touch( bk_storage() . '/' . $kept['spec']['file'], time() - 600 );
$next = Digitalisimo_Backups_Jobs::enqueue( $long_spec( 'largo-2' ) )['job'];
bk_run_until_done( $next['id'] );
$stored = array_values( array_filter( bk_options_load()[ Digitalisimo_Backups::HISTORY ], function( $row ) use ( $kept ) { return $kept['spec']['file'] === $row['file']; } ) );
bk_assert( 'success' === bk_job( $next['id'] )['status'] && 1 === count( $stored ) && ! empty( $stored[0]['locked'] ), 'El proceso largo escribió sobre el bloqueo hecho en otra petición.' );
bk_assert( is_file( bk_storage() . '/' . $kept['spec']['file'] ), 'La retención del proceso largo borró un respaldo bloqueado en otra petición.' );

bk_cleanup();
echo "DIGITALÍSIMO Backups: reanudación tras una muerte en exportación, empaquetado, verificación, registro y transferencia sin rehacer lo confirmado.\n";
