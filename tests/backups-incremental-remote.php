<?php
/**
 * Incrementales con un destino remoto real (docs/BACKUPS.md, «Incrementales»): un servidor SFTP de OpenSSH que la prueba
 * arranca sin privilegios en 127.0.0.1 (como tests/backups-sftp-phpseclib.php) y el motor de trabajos real.
 *
 *   R1  una automatización incremental hacia SFTP con «sólo en el remoto»: cada eslabón se sube, se verifica en el remoto
 *       y su ZIP local se retira; el siguiente incremental sigue la cadena con el índice lateral (sin el ZIP del padre);
 *   R2  «Traer al almacén» de un punto trae toda su cadena (cada eslabón comprobado por su SHA-256) y el punto se
 *       restaura idéntico al sitio de su momento; antes de traerla, restaurar lo rechaza diciendo qué falta;
 *   R3  la retención en el remoto: las copias remotas de una cadena vieja se borran enteras cuando ya no hay puntos que
 *       conservar, y nunca la de un eslabón del que depende un punto conservado.
 *
 * Sin sshd/ssh-keygen se omite, salvo en la CI (CI=true) o con BK_REQUIRE_SSHD=1. Debe fallar con el código anterior.
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/backups-incremental/helpers.php';
$root = rtrim( ABSPATH, '/' );
$B = 'Digitalisimo_Backups';
$C = 'Digitalisimo_Backups_Chain';
$failures = 0;

$sshd = is_executable( '/usr/sbin/sshd' ) ? '/usr/sbin/sshd' : '';
$keygen = trim( (string) shell_exec( 'command -v ssh-keygen' ) );
if ( '' === $sshd || '' === $keygen ) {
	if ( getenv( 'CI' ) || getenv( 'BK_REQUIRE_SSHD' ) ) { echo "FALLA: sin sshd o ssh-keygen.\n"; exit( 1 ); }
	bk_cleanup();
	echo "OMITIDO: este equipo no tiene sshd o ssh-keygen.\n";
	exit( 0 );
}
$server_root = sys_get_temp_dir() . '/digitalisimo-inc-sftp-' . bin2hex( random_bytes( 4 ) );
$remote = $server_root . '/remote';
$k = $server_root . '/keys';
mkdir( $remote, 0700, true );
mkdir( $k, 0700, true );
$run = function( $command ) { exec( $command . ' 2>&1', $out, $code ); if ( 0 !== $code ) throw new RuntimeException( $command . ': ' . implode( "\n", $out ) ); };
$run( 'ssh-keygen -q -t ed25519 -N "" -f ' . escapeshellarg( $k . '/host_ed25519' ) );
$run( 'ssh-keygen -q -t ed25519 -N "" -C respaldos -f ' . escapeshellarg( $k . '/user_ed25519' ) );
file_put_contents( $k . '/authorized_keys', file_get_contents( $k . '/user_ed25519.pub' ) );
chmod( $k . '/authorized_keys', 0600 );
$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
$port = (int) substr( strrchr( stream_socket_get_name( $socket, false ), ':' ), 1 );
fclose( $socket );
file_put_contents( $k . '/sshd_config', "Port $port\nListenAddress 127.0.0.1\nHostKey $k/host_ed25519\nPidFile $k/sshd.pid\nAuthorizedKeysFile $k/authorized_keys\nPasswordAuthentication no\nKbdInteractiveAuthentication no\nUsePAM no\nStrictModes no\nSubsystem sftp internal-sftp\nLogLevel ERROR\n" );
$server = proc_open( array( $sshd, '-D', '-e', '-f', $k . '/sshd_config' ), array( 0 => array( 'file', '/dev/null', 'r' ), 1 => array( 'file', $k . '/sshd.log', 'a' ), 2 => array( 'file', $k . '/sshd.log', 'a' ) ), $pipes );
register_shutdown_function( function() use ( $server, $server_root ) {
	if ( is_resource( $server ) ) { @proc_terminate( $server ); @proc_close( $server ); }
	bk_remove_tree( $server_root );
} );
for ( $i = 0; $i < 100 && ! @fsockopen( '127.0.0.1', $port, $errno, $errstr, 0.2 ); $i++ ) usleep( 100000 );
$user = function_exists( 'posix_getpwuid' ) ? posix_getpwuid( posix_geteuid() )['name'] : get_current_user();
$fingerprint = 'SHA256:' . rtrim( base64_encode( hash( 'sha256', base64_decode( explode( ' ', trim( file_get_contents( $k . '/host_ed25519.pub' ) ) )[1] ), true ) ), '=' );
// La conexión principal SFTP, como la guarda la pantalla «Destinos» (la clave se cifra al leer los ajustes).
$GLOBALS['bk_options'][ $B::OPTION ] = array( 'destination' => 'local', 'sftp_host' => '127.0.0.1', 'sftp_port' => (string) $port, 'sftp_user' => $user, 'sftp_path' => $remote, 'sftp_engine' => 'auto', 'sftp_auth' => 'key', 'sftp_private_key' => trim( file_get_contents( $k . '/user_ed25519' ) ), 'sftp_public_key' => trim( file_get_contents( $k . '/user_ed25519.pub' ) ), 'sftp_key_passphrase' => '', 'sftp_fingerprint' => $fingerprint );

function rem_run( $policy, $retain = 10, $every = 2 ) {
	$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => $policy, 'destination' => 'sftp', 'remote_only' => true, 'retain' => $retain, 'retention' => 'policy', 'via' => 'test', 'incremental' => array( 'full_every' => $every, 'full_days' => 7 ) ) ) );
	inc_check( empty( $queued['duplicate'] ), 'El trabajo ya estaba en cola.' );
	$id = $queued['job']['id'];
	for ( $i = 0; $i < 400; $i++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
		Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	}
	// El borrado diferido de las copias remotas que retiró la retención (lo hacen el tick y el vigilante).
	for ( $i = 0; $i < 5; $i++ ) Digitalisimo_Backups::job_remote_retention_pending( 20 );
	$job = Digitalisimo_Backups_Jobs::get( $id );
	inc_check( 'success' === $job['status'], 'El respaldo hacia SFTP no terminó: ' . json_encode( $job['error'] ) );
	return $job;
}
function rem_remote_files( $remote ) {
	$out = array();
	foreach ( glob( $remote . '/*' ) ?: array() as $path ) $out[ basename( $path ) ] = hash_file( 'sha256', $path );
	ksort( $out );
	return $out;
}

foreach ( array(
	'wp-content/plugins/demo/demo.php' => "<?php /* Plugin Name: Demo */\n",
	'wp-content/themes/tema/style.css' => "/* Theme Name: Tema */\n",
	'wp-content/uploads/2026/11/foto.jpg' => str_repeat( "\xff\xd8\xff", 60000 ),
	'wp-content/uploads/2026/11/nota.txt' => "nota\n",
) as $name => $content ) inc_put( $name, $content );
$points = array();
$trees = array();

inc_case( 'R1 cadena hacia SFTP con «sólo en el remoto»: cada eslabón subido y verificado; el siguiente sigue la cadena sin el ZIP local del padre', function() use ( &$points, &$trees, $C, $B, $remote, $root ) {
	foreach ( array( 0, 1, 2 ) as $n ) {
		if ( 1 === $n ) { inc_put( 'wp-content/plugins/demo/nuevo.php', "<?php // punto 1\n" ); unlink( $root . '/wp-content/uploads/2026/11/nota.txt' ); $GLOBALS['bk_options']['blogname'] = 'Remoto 1'; }
		if ( 2 === $n ) { inc_put( 'wp-content/themes/tema/style.css', "/* Theme Name: Tema 2 */\n" ); $GLOBALS['bk_options']['blogname'] = 'Remoto 2'; }
		$job = rem_run( 'remota' );
		$row = inc_row( inc_path( $job ) );
		$chain = $C::row_chain( $row );
		inc_check( $chain && $n === $chain['sequence'], 'El punto ' . $n . ' no está en su lugar de la cadena: ' . json_encode( $row['chain'] ?? null ) );
		inc_check( 'ok' === ( $row['transfer'] ?? '' ) && 'remote-only' === ( $row['local'] ?? '' ) && ! is_file( inc_path( $job ) ), 'El punto ' . $n . ' no quedó sólo en el remoto, verificado: ' . json_encode( array_intersect_key( $row, array_flip( array( 'transfer', 'local', 'transfer_error' ) ) ) ) );
		inc_check( hash_equals( (string) $row['sha256'], (string) ( rem_remote_files( $remote )[ basename( inc_path( $job ) ) ] ?? '' ) ), 'La copia remota del punto ' . $n . ' no es la registrada.' );
		inc_check( '' !== $C::index_path( bk_storage(), $row ), 'El punto ' . $n . ' no dejó su índice lateral.' );
		$points[ $n ] = inc_path( $job );
		$trees[ $n ] = array( inc_files(), inc_db() );
	}
	$summary = array_values( array_filter( $B::service_backups(), function( $row ) use ( &$points ) { return basename( $points[2] ) === $row['file']; } ) )[0];
	inc_check( false === $summary['restorable'] && 3 === count( $summary['chain']['missing'] ), 'El historial no dice que al punto le falta su cadena en el almacén: ' . json_encode( $summary['chain'] ) );
} );

inc_case( 'R2 «Traer al almacén» trae la cadena entera y el punto se restaura idéntico; antes, restaurar dice qué falta', function() use ( &$points, &$trees, $B ) {
	$result = inc_restore( $points[2] );
	inc_check( is_wp_error( $result ) && 'digitalisimo_backups_chain_incomplete' === $result->get_error_code() && false !== strpos( $result->get_error_message(), 'Traer al almacén' ), 'Restaurar sin la cadena en el almacén no lo rechazó con qué traer: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $result ) ) );
	$fetched = $B::service_fetch( basename( $points[2] ), array( 'via' => 'test', 'drive_cli' => true ) );
	inc_check( ! is_wp_error( $fetched ) && ! empty( $fetched['fetched'] ) && 3 === count( $fetched['chain'] ), 'No se trajo la cadena: ' . ( is_wp_error( $fetched ) ? $fetched->get_error_message() : json_encode( $fetched ) ) );
	foreach ( $points as $point ) inc_check( is_file( $point ) && hash_equals( (string) inc_row( $point )['sha256'], hash_file( 'sha256', $point ) ), 'Un eslabón traído no es el registrado: ' . basename( $point ) );
	inc_put( 'wp-content/plugins/demo/despues.php', "<?php // después\n" );
	$GLOBALS['bk_options']['blogname'] = 'Después';
	$result = inc_restore( $points[2] );
	inc_check( ! is_wp_error( $result ) && inc_last_restore_ok(), 'El punto traído no se restauró: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	inc_check( array( inc_files(), inc_db() ) === $trees[2], 'El punto traído y restaurado no es el sitio de su momento.' );
} );

inc_case( 'R3 retención en el remoto: una cadena vieja se borra entera del SFTP y nunca un eslabón del que depende un punto conservado', function() use ( &$points, $C, $B, $remote ) {
	$made = array();
	for ( $i = 0; $i < 4; $i++ ) {
		inc_put( 'wp-content/uploads/2026/11/vuelta.txt', 'vuelta ' . $i );
		$job = rem_run( 'remota', 2 );
		$made[] = basename( inc_path( $job ) );
		$history = (array) $GLOBALS['bk_options'][ $B::HISTORY ];
		$files = rem_remote_files( $remote );
		foreach ( $history as $row ) {
			if ( 'remota' !== ( $row['policy'] ?? '' ) ) continue;
			foreach ( $C::files( $row ) as $file ) {
				$link = inc_row( $file );
				inc_check( $link && ( isset( $files[ $file ] ) || is_file( rtrim( bk_storage(), '/' ) . '/' . $file ) ), 'La retención dejó sin ninguna copia un eslabón de ' . $row['file'] . ': ' . $file );
			}
		}
	}
	// Puntos: base, 1, 2 (los traídos) | base, 1, 2 | base. Con 2 que conservar quedan el último completo y el incremental
	// anterior con su base; la primera cadena salió entera del remoto y del almacén.
	$files = rem_remote_files( $remote );
	foreach ( $points as $point ) inc_check( ! isset( $files[ basename( $point ) ] ) && ! is_file( $point ) && ! inc_row( $point ), 'La primera cadena no salió entera: ' . basename( $point ) );
	$kept = array_values( array_map( function( $row ) { return $row['file']; }, array_filter( (array) $GLOBALS['bk_options'][ $B::HISTORY ], function( $row ) { return 'remota' === ( $row['policy'] ?? '' ); } ) ) );
	sort( $kept );
	$expected = array( $made[0], $made[1], $made[2], $made[3] );
	sort( $expected );
	inc_check( $expected === $kept, 'La retención no conservó los puntos y su cadena: ' . json_encode( $kept ) );
	foreach ( $kept as $file ) inc_check( isset( $files[ $file ] ), 'Falta en el remoto la copia de ' . $file );
} );

inc_case( 'R4 recuperación ante desastre desde el SFTP: sin historial ni almacén, «Traer al almacén» del último punto trae su cadena y se restaura idéntico', function() use ( $C, $B, $remote, $root ) {
	inc_put( 'wp-content/uploads/2026/11/ultimo.txt', 'el último punto' );
	$GLOBALS['bk_options']['blogname'] = 'Último punto';
	$job = rem_run( 'remota', 2 );
	$last = basename( inc_path( $job ) );
	inc_check( 1 === $C::row_chain( inc_row( $last ) )['sequence'], 'El último punto no es un incremental.' );
	$tree = inc_files();
	$db = inc_db();
	// El desastre: se pierden la base de datos (historial, índices) y el almacén; queda el SFTP. Un sitio «nuevo».
	$GLOBALS['bk_options'][ $B::HISTORY ] = array();
	foreach ( glob( rtrim( bk_storage(), '/' ) . '/*' ) as $path ) if ( is_file( $path ) ) unlink( $path );
	bk_remove_tree( rtrim( bk_storage(), '/' ) . '/chains' );
	@unlink( rtrim( bk_storage(), '/' ) . '/.installation-id' );
	inc_put( 'wp-content/plugins/demo/sitio-nuevo.php', "<?php // otro contenido\n" );
	$GLOBALS['bk_options']['blogname'] = 'Sitio nuevo';
	$listed = $B::service_remote_backups( 'sftp' );
	inc_check( ! is_wp_error( $listed ) && in_array( $last, array_column( (array) ( $listed['backups'] ?? $listed ), 'name' ), true ), 'El SFTP no lista el último punto: ' . json_encode( $listed ) );
	$fetched = $B::service_remote_fetch( 'sftp', $last, array( 'via' => 'test', 'drive_cli' => true ) );
	inc_check( ! is_wp_error( $fetched ) && ! empty( $fetched['fetched'] ) && 1 === count( (array) ( $fetched['chain'] ?? array() ) ) && ! empty( $fetched['chain'][0]['fetched'] ), '«Traer al almacén» del punto no trajo su cadena: ' . ( is_wp_error( $fetched ) ? $fetched->get_error_message() : json_encode( $fetched ) ) );
	$result = inc_restore( $last );
	inc_check( is_wp_error( $result ) && 'digitalisimo_backups_confirm_unsigned' === $result->get_error_code(), 'Sin historial ni firma, «RESTAURAR» no pidió «RESTAURAR SIN FIRMA».' );
	$result = inc_restore( $last, 'RESTAURAR SIN FIRMA' );
	inc_check( ! is_wp_error( $result ) && inc_last_restore_ok(), 'El punto traído sin historial no se restauró: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $result ) ) );
	inc_check( $tree === inc_files() && $db === inc_db(), 'El punto traído sin historial y restaurado no es el sitio de su momento.' );
} );

bk_cleanup();
echo $failures ? "FALLA: incrementales con destino remoto, {$failures} caso(s) fallido(s).\n" : "DIGITALÍSIMO Backups: cadena de incrementales hacia SFTP real, «Traer al almacén» de la cadena entera y retención remota consciente de cadenas.\n";
exit( $failures ? 1 : 0 );
