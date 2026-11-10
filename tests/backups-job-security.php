<?php
/**
 * Seguridad dentro del motor de trabajos (unión de «Trabajos» y «Seguridad»): la especificación fija el cifrado y la
 * huella de la clave al encolarse, sin guardar la clave; «pack» firma con esa clave aunque después se active otra;
 * «verify» comprueba la firma y sella el DGBK dentro del área de trabajo; «publish» lo publica con rename y retira el
 * ZIP en claro. Reanudación tras una muerte durante el sellado y tras el rename, restauración del DGBK hecho por un
 * trabajo, clave que deja de abrirse a mitad (falla cerrado y no deja nada), respaldo sin cifrar cuya clave se pierde
 * (vuelve a empaquetar sin firma), nombre que no corresponde al cifrado y copia previa firmada y nunca cifrada.
 */
require __DIR__ . '/lib/backups-env.php';
$K = 'Digitalisimo_Backups_Keys';
$S = 'Digitalisimo_Backups_Security';
$J = 'Digitalisimo_Backups_Jobs';
$T = 'Digitalisimo_Backups_Job_Store';
$E = 'Digitalisimo_Backups_Encrypted_File';
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
$storage = bk_storage();
$check = function( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); };
$enqueue = function() use ( $J ) {
	$queued = $J::enqueue( $J::spec( array( 'policy' => 'manual', 'destination' => 'local', 'retention' => 'none', 'via' => 'test' ) ) );
	return $queued['job']['id'];
};
// Un paso por tick: con un presupuesto mínimo el bucle del tick se corta tras la primera fase.
$step = function( $id ) use ( $J ) { $J::tick( 0.000001 ); return $J::get( $id ); };
$until = function( $id, $phase ) use ( $J, $T, $step ) {
	for ( $i = 0; $i < 30; $i++ ) {
		$job = $J::get( $id );
		if ( $T::is_terminal( $job ) || $phase === $job['phase'] ) return $job;
		$step( $id );
	}
	throw new RuntimeException( 'El trabajo no llegó a la fase ' . $phase . '.' );
};
$finish = function( $id ) use ( $J, $T, $step ) {
	for ( $i = 0; $i < 30; $i++ ) {
		$job = $J::get( $id );
		if ( $T::is_terminal( $job ) ) return $job;
		$step( $id );
	}
	throw new RuntimeException( 'El trabajo no terminó.' );
};
$root_files = function() use ( $storage ) {
	$files = array_map( 'basename', array_merge( glob( $storage . '/digitalisimo-*' ) ?: array(), glob( $storage . '/safety-before-restore-*' ) ?: array(), glob( $storage . '/*.part' ) ?: array(), glob( $storage . '/.staging/*' ) ?: array() ) );
	sort( $files );
	return $files;
};
$staged = function( $id, $pattern ) use ( $T ) { return glob( $T::directory( $id ) . '/' . $T::STAGING . '/' . $pattern ) ?: array(); };
$manifest_of = function( $path ) {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) throw new RuntimeException( 'No se pudo abrir ' . basename( $path ) . '.' );
	$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	$zip->close();
	return $manifest;
};

// 1. La especificación fija el cifrado, la huella de la clave y el nombre .dgbk; nunca la clave ni la frase.
$passphrase = 'frase del trabajo bastante larga';
$key_a = $K::set_passphrase( $passphrase )['key_id'];
$root_a = $K::resolve_by_id( $key_a );
$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => true, 'require_signed' => false );
$id = $enqueue();
$job = $J::get( $id );
$check( array( 'encrypt' => true, 'key_id' => $key_a ) === $job['spec']['security'] && '.dgbk' === substr( $job['spec']['file'], -5 ), 'La especificación no fijó el cifrado, la huella y el nombre del respaldo cifrado.' );
$raw = file_get_contents( $T::directory( $id ) . '/' . $T::STATE );
foreach ( array( $passphrase, bin2hex( $root_a ), base64_encode( $root_a ), $K::b64url_encode( $root_a ) ) as $secret ) $check( false === strpos( $raw, $secret ), 'El estado del trabajo guarda la frase o la clave.' );

// 2. Clave rotada a mitad del trabajo: el respaldo se firma y se cifra con la que fijó al encolarse.
$until( $id, 'pack' );
$key_b = $K::set_passphrase( 'otra frase del trabajo bastante larga' )['key_id'];
$check( $key_b !== $key_a && $key_b === $K::keyring()['active'], 'La rotación no activó la clave nueva.' );
$job = $until( $id, 'verify' );
$zips = $staged( $id, 'package.*.zip' );
$check( 1 === count( $zips ), 'El empaquetado no dejó un ZIP en el área de trabajo.' );
$manifest = $manifest_of( $zips[0] );
$check( $key_a === ( $manifest['auth']['key']['id'] ?? '' ) && true === ( $manifest['auth']['encrypted'] ?? null ) && $id === ( $manifest['job_id'] ?? '' ), 'El manifiesto no lleva la firma de la clave fijada o su trabajo.' );
$check( array() === $root_files(), 'Antes de verificar ya había algo en el almacén.' );

// 3. Muerte durante el sellado: el DGBK y su .part a medias se rehacen sin volver a empaquetar.
Digitalisimo_Backups_Backup_Job::run_phase( 'verify', $job ); // Sella y «muere» antes de guardar el punto de control.
$sealed = $staged( $id, 'package.*.dgbk' );
$check( 1 === count( $sealed ), 'El sellado no dejó el DGBK en el área de trabajo.' );
file_put_contents( $sealed[0], 'mitad de un DGBK' );
file_put_contents( $sealed[0] . '.part', 'otra mitad' );
$pack_fp = $J::get( $id )['checkpoint']['pack']['fp'];
$job = $step( $id );
$check( 'publish' === $job['phase'] && 'verifying' === $job['status'], 'La verificación no se reanudó tras la muerte durante el sellado.' );
$check( $pack_fp === $job['checkpoint']['pack']['fp'] && 1 === count( $staged( $id, 'package.*.zip' ) ) && ! $staged( $id, '*.part' ), 'La reanudación volvió a empaquetar o dejó un .part.' );
$sealed = $staged( $id, 'package.*.dgbk' );
$check( 1 === count( $sealed ) && $E::is_encrypted( $sealed[0] ) && basename( $sealed[0] ) === $job['checkpoint']['verify']['file'], 'El DGBK resellado no es el del punto de control.' );
$check( array() === $root_files(), 'Antes de publicar ya había algo en el almacén.' );

// 4. Muerte tras el rename y antes de registrar: se registra una sola vez y el ZIP en claro sale del área de trabajo.
$final = $storage . '/' . $job['spec']['file'];
$check( rename( $sealed[0], $final ), 'No se pudo simular el rename de la publicación.' );
$job = $step( $id );
$check( 'prune' === $job['phase'], 'La publicación no se reanudó tras el rename.' );
$check( ! $staged( $id, 'package.*' ), 'El ZIP en claro o el DGBK siguen en el área de trabajo tras publicar.' );
$history = $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ];
$rows = array_values( array_filter( $history, function( $row ) use ( $final ) { return basename( $final ) === ( $row['file'] ?? '' ); } ) );
$check( 1 === count( $rows ) && 'dgbk' === $rows[0]['container'] && $key_a === $rows[0]['key_id'] && $rows[0]['signed'] && 'created' === $rows[0]['origin'] && hash_file( 'sha256', $final ) === $rows[0]['sha256'] && $id === ( $rows[0]['job'] ?? '' ), 'El historial no registró una vez el DGBK con su clave y su SHA-256.' );
$job = $finish( $id );
$check( 'success' === $job['status'] && array( basename( $final ) ) === $root_files() && ! is_dir( $T::directory( $id ) . '/' . $T::STAGING ), 'El trabajo no terminó limpio con sólo el DGBK en el almacén.' );
$header = $E::read_header( $final )['header'];
$check( $key_a === $header['key']['id'] && $manifest['backup_id'] === $header['backup_id'], 'La cabecera del DGBK no lleva la clave fijada o el backup_id del manifiesto.' );
$inner = $storage . '/inner-check.zip';
$E::decrypt( $final, $root_a, $inner );
$inner_manifest = $manifest_of( $inner );
unlink( $inner );
$check( $key_a === $inner_manifest['auth']['key']['id'] && true === $inner_manifest['auth']['encrypted'] && $id === $inner_manifest['job_id'], 'El ZIP interior no es el que se firmó.' );

// 5. El DGBK de un trabajo se restaura con la clave retirada del llavero; la copia previa se firma y no se cifra.
$GLOBALS['wpdb']->posts[0]['post_content'] = 'cambiado después del respaldo';
$GLOBALS['wpdb']->queries = array();
list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups', 'restore_request' ), array( '_wpnonce' => 'nonce-' . Digitalisimo_Backups::ACTION_RESTORE, 'backup_file' => basename( $final ), 'confirmation' => 'RESTAURAR' ) );
$check( $ok && preg_grep( '/punto y coma/', bk_inserts_into( 'wp_posts' ) ), 'No se restauró el DGBK hecho por un trabajo: ' . $message );
$safety = glob( $storage . '/safety-before-restore-*' ) ?: array();
$check( 1 === count( $safety ) && '.zip' === substr( $safety[0], -4 ) && ! $E::is_encrypted( $safety[0] ), 'La copia previa no es un ZIP sin cifrar.' );
$described = $S::describe( $safety[0] );
$check( $described['signed'] && $key_b === $described['key_id'] && ! glob( $storage . '/.staging/*' ) && ! $J::has_active(), 'La copia previa no se firmó con la clave activa o la restauración dejó restos.' );
$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => true, 'require_signed' => false ); // El SQL restaurado no traía la seguridad.

// 6. La clave fijada deja de abrirse a mitad de un trabajo cifrado: falla cerrado, sin publicar nada ni dejar ZIP en claro.
$before = $root_files();
$history_before = $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ];
$id = $enqueue();
$check( $key_b === $J::get( $id )['spec']['security']['key_id'], 'El trabajo nuevo no fijó la clave activa.' );
$until( $id, 'verify' );
$wrap = $GLOBALS['bk_options'][ $K::KEYRING ]['keys'][ $key_b ]['wrap'];
$GLOBALS['bk_options'][ $K::KEYRING ]['keys'][ $key_b ]['wrap']['kw_id'] = str_repeat( 'f', 16 );
$job = $finish( $id );
$check( 'failed' === $job['status'] && ! empty( $job['error']['permanent'] ) && false !== strpos( $job['error']['message'], 'No se creó un respaldo sin cifrar' ), 'Sin su clave, el trabajo cifrado no falló cerrado: ' . json_encode( $job['error'] ) );
$check( $before === $root_files() && ! is_dir( $T::directory( $id ) . '/' . $T::STAGING ) && $history_before === $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ], 'El fallo cerrado dejó archivos, área de trabajo o filas.' );
$GLOBALS['bk_options'][ $K::KEYRING ]['keys'][ $key_b ]['wrap'] = $wrap;

// 7. Sin cifrado, si la clave deja de abrirse entre el empaquetado y la verificación, se vuelve a empaquetar sin firma.
$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => false, 'require_signed' => false );
$id = $enqueue();
$spec = $J::get( $id )['spec'];
$check( array( 'encrypt' => false, 'key_id' => $key_b ) === $spec['security'] && '.zip' === substr( $spec['file'], -4 ), 'Sin cifrado la especificación debía fijar sólo la clave de la firma.' );
$until( $id, 'verify' );
$check( isset( $manifest_of( $staged( $id, 'package.*.zip' )[0] )['auth'] ), 'El empaquetado sin cifrado no firmó con la clave disponible.' );
$GLOBALS['bk_options'][ $K::KEYRING ]['keys'][ $key_b ]['wrap']['kw_id'] = str_repeat( 'f', 16 );
$job = $finish( $id );
$plain = $storage . '/' . $spec['file'];
$check( 'success' === $job['status'] && 1 === (int) ( $job['attempts']['verify'] ?? 0 ) && is_file( $plain ) && ! $S::describe( $plain )['signed'] && ! isset( $manifest_of( $plain )['auth'] ), 'Sin la clave, el respaldo sin cifrar no se volvió a empaquetar sin firma.' );
$GLOBALS['bk_options'][ $K::KEYRING ]['keys'][ $key_b ]['wrap'] = $wrap;

// 8. Un nombre que no corresponde al cifrado fijado es un error permanente de las comprobaciones.
$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => true, 'require_signed' => false );
$spec = $J::spec( array( 'policy' => 'manual', 'destination' => 'local', 'retention' => 'none', 'via' => 'test' ) );
$spec['file'] = substr( $spec['file'], 0, -5 ) . '.zip';
$id = $J::enqueue( $spec )['job']['id'];
$job = $finish( $id );
$check( 'failed' === $job['status'] && ! empty( $job['error']['permanent'] ) && false !== strpos( $job['error']['message'], 'nombre del respaldo no es válido' ), 'Un respaldo cifrado con nombre .zip no se rechazó en las comprobaciones.' );

// 9. Con el cifrado activo y sin clave no se encola nada; la copia previa (nunca cifrada) sí se crea, sin firma.
$GLOBALS['bk_options'][ $K::KEYRING ]['keys'][ $key_b ]['wrap']['kw_id'] = str_repeat( 'f', 16 );
$jobs_before = count( $J::all() );
bk_expect_failure( 'encolar sin clave', function() use ( $enqueue ) { $enqueue(); }, 'No se creó un respaldo sin cifrar' );
$check( count( $J::all() ) === $jobs_before, 'Sin clave se encoló un trabajo.' );
$copy = bk_call( 'Digitalisimo_Backups', 'create_network', 'safety-before-restore-' );
$check( '.zip' === substr( $copy, -4 ) && ! $E::is_encrypted( $copy ) && ! $S::describe( $copy )['signed'], 'La copia previa sin clave abierta no es un ZIP sin cifrar ni firma.' );

bk_cleanup();
echo "DIGITALÍSIMO Backups: cifrado y firma dentro de los trabajos, con la clave fijada al encolarse, reanudación del sellado y de la publicación, restauración del DGBK, fallo cerrado sin clave y copia previa sin cifrar verificados.\n";
