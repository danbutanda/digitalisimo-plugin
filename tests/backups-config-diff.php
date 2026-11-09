<?php
/**
 * Lote corto · I10: informe de diferencias de wp-config.php al restaurar un respaldo de otro servidor.
 *
 * La restauración nunca escribe wp-config.php, así que las constantes del origen no viajan. Antes de confirmar
 * (`verify` en REST y WP-CLI, y al subir un respaldo) y después de restaurar (aviso, resultado y correo), Backups dice
 * qué constantes define el respaldo y este servidor no, cuáles sólo define este servidor y cuáles tienen otro valor.
 * Los dos archivos se leen con el tokenizador (nunca se ejecutan) y nunca se muestra un valor secreto (contraseñas,
 * claves, sales, base de datos) ni una ruta del servidor; en el correo, sólo los nombres.
 */
$root = sys_get_temp_dir() . '/digitalisimo-config-diff-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );
function is_email( $email ) { return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false; }
$GLOBALS['mails'] = array();
function wp_mail( $to, $subject, $body ) { $GLOBALS['mails'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body ); return true; }

$B = 'Digitalisimo_Backups';
$D = 'Digitalisimo_Backups_Config_Diff';
$ns = 'digitalisimo-backups/v1';
$secrets = array( 'clave-db-origen-7731', 'clave-db-destino-9902', 'auth-origen-c0ffee', 'auth-destino-beef00', 'licencia-origen-5a5a', '/srv/origen/wp-content', '/var/www/destino/wp-content', 'db-interno.origen.lan' );
$origin = <<<'PHP'
<?php
define( 'DB_NAME', 'wp_origen' );
define( 'DB_PASSWORD', 'clave-db-origen-7731' );
define( 'DB_HOST', 'db-interno.origen.lan' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'AUTH_KEY', 'auth-origen-c0ffee' );
define( 'WP_MEMORY_LIMIT', '256M' );
define( "WP_DEBUG", true );
define( 'WP_CONTENT_DIR', '/srv/origen/wp-content' );
define( 'WP_HOME', 'https://origen.example' );
define( 'DISABLE_WP_CRON', true );
define( 'EMPTY_TRASH_DAYS', 7 );
define( 'MI_PLUGIN_LICENSE', 'licencia-origen-5a5a' );
define( 'WP_REDIS_HOST', getenv( 'REDIS_HOST' ) );
// define( 'COMENTADA', 'no cuenta' );
$table_prefix = 'wp_';
PHP;
$server = <<<'PHP'
<?php
define( 'DB_NAME', 'wp_destino' );
define( 'DB_PASSWORD', 'clave-db-destino-9902' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'AUTH_KEY', 'auth-destino-beef00' );
define( 'WP_MEMORY_LIMIT', '128M' );
define( 'WP_CONTENT_DIR', '/var/www/destino/wp-content' );
define( 'WP_HOME', 'https://destino.example' );
define( 'EMPTY_TRASH_DAYS', 7 );
define( 'WP_CACHE', true );
define( 'WP_REDIS_HOST', getenv( 'REDIS_HOST' ) );
$table_prefix = 'wp_';
PHP;

try {
	// 1. Antes de confirmar: el respaldo «de otro servidor» se verifica aquí (REST y WP-CLI) con su informe.
	file_put_contents( ABSPATH . 'wp-config.php', $origin );
	update_option( $B::OPTION, array( 'retention' => '100' ) );
	$created = digi_backup();
	digi_assert( ! is_wp_error( $created ), 'No se creó el respaldo.' );
	file_put_contents( ABSPATH . 'wp-config.php', $server );
	Digitalisimo_Backups_Rest::register();
	call_user_func( $GLOBALS['digi']['actions']['rest_api_init'][0] );
	Digitalisimo_Backups_Cli::register();
	list( $code, $report ) = digi_rest( 'GET', $ns . '/backups/' . $created['file'] . '/verify' );
	digi_assert( 200 === $code && true === ( $report['wp_config']['available'] ?? null ) && 11 === count( $report['wp_config']['differences'] ) && false !== strpos( $report['wp_config']['text'], 'WP_MEMORY_LIMIT' ), 'GET …/verify no trae el informe de wp-config.php: ' . json_encode( $report['wp_config'] ?? null ) );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups', 'verify', array( $created['file'] ) );
	$cli = implode( "\n", $out );
	digi_assert( 0 === $code && false !== strpos( $cli, 'Warning: wp-config.php no se restaura' ) && false !== strpos( $cli, "WP_MEMORY_LIMIT: respaldo '256M', este servidor '128M'" ) && false !== strpos( $cli, 'DB_PASSWORD: respaldo (valor oculto), este servidor (valor oculto)' ) && false !== strpos( $cli, 'WP_CONTENT_DIR: respaldo (ruta del servidor)' ), 'wp … verify no lista las diferencias: ' . $cli );
	foreach ( $secrets as $secret ) digi_assert( false === strpos( json_encode( $report ) . $cli, $secret ) && false === strpos( json_encode( $report ), str_replace( '/', '\\/', $secret ) ), 'verify expone un valor secreto o una ruta: ' . $secret );

	// 2. Después de restaurar: el aviso, el resultado y el correo (sólo nombres) lo dicen; wp-config.php no cambia.
	Digitalisimo_Backups_Keys::put( Digitalisimo_Backups_Notify::OPTION, array( 'failure' => true, 'success' => true, 'email' => 'avisos@example.test' ) );
	list( $code, $restored ) = digi_rest( 'POST', $ns . '/backups/' . $created['file'] . '/restore', array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( 200 === $code && 11 === count( $restored['wp_config']['differences'] ) && false !== strpos( $restored['notice'], 'wp-config.php no se restaura' ) && false !== strpos( $restored['notice'], 'DISABLE_WP_CRON' ), 'La restauración no informa las diferencias de wp-config.php: ' . json_encode( $restored ) );
	digi_assert( $server === file_get_contents( ABSPATH . 'wp-config.php' ), 'La restauración escribió wp-config.php.' );
	$mail = end( $GLOBALS['mails'] );
	digi_assert( $mail && false !== strpos( $mail['body'], 'WP_MEMORY_LIMIT' ) && false !== strpos( $mail['body'], 'DB_PASSWORD' ) && false === strpos( $mail['body'], '256M' ) && false === strpos( $mail['body'], '128M' ), 'El correo de la restauración no lista los nombres o lleva valores: ' . json_encode( $mail ) );
	foreach ( $secrets as $secret ) digi_assert( false === strpos( json_encode( $restored ) . $mail['body'], $secret ) && false === strpos( json_encode( $restored ), str_replace( '/', '\\/', $secret ) ), 'La restauración expone un valor secreto o una ruta: ' . $secret );

	// 3. Al subir un respaldo también se informa (es el caso habitual de otro servidor).
	$bytes = file_get_contents( $root . '/private/' . $created['file'] );
	$session = $B::service_upload_start( array( 'name' => 'digitalisimo-otro-servidor.zip', 'size' => strlen( $bytes ) ) );
	for ( $offset = 0; $offset < strlen( $bytes ); $offset += $session['chunk_size'] ) $B::service_upload_chunk( $session['id'], $offset, array( 'data' => substr( $bytes, $offset, $session['chunk_size'] ) ) );
	$uploaded = $B::service_upload_finish( $session['id'] );
	digi_assert( ! is_wp_error( $uploaded ) && 11 === count( $uploaded['wp_config']['differences'] ), 'La subida no informa las diferencias de wp-config.php: ' . json_encode( is_wp_error( $uploaded ) ? $uploaded->get_error_message() : $uploaded ) );

	// 4. El análisis: sólo define() con nombre literal, valores literales o «expresión», nunca lo comentado ni un método.
	$parsed = $D::parse( "<?php\ndefine( 'A', 'uno' ); define( \"B\", \"dos\\tdos\" ); define( 'C', -5 ); define( 'D', 1.5 ); define( 'E', FALSE ); define( 'F', null );\ndefine( 'G', 'a' . 'b' ); define( 'H', getenv( 'H' ) ); define( 'I', \"con \$variable\" ); define( 'J2', \"precio \\\$5\" ); define( 'U', \"\\u{41}\" ); define( 'A', 'repetida' );\n// define( 'J', 'comentada' );\n/* define( 'K', 'bloque' ); */\n\$o->define( 'L', 'metodo' ); Clase::define( 'M', 'estatico' ); echo \"define( 'N', 'cadena' )\";\n" );
	digi_assert( array( 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J2', 'U' ) === array_keys( $parsed ), 'El análisis no encontró exactamente las constantes definidas: ' . json_encode( array_keys( $parsed ) ) );
	digi_assert( 'uno' === $parsed['A']['value'] && "dos\tdos" === $parsed['B']['value'] && '-5' === $parsed['C']['value'] && '1.5' === $parsed['D']['value'] && 'false' === $parsed['E']['value'] && 'null' === $parsed['F']['value'], 'Los literales no se leyeron bien: ' . json_encode( $parsed ) );
	digi_assert( true === $parsed['J2']['literal'] && 'precio $5' === $parsed['J2']['value'], 'Un \\$ escapado no se leyó como literal: ' . json_encode( $parsed['J2'] ) );
	foreach ( array( 'G', 'H', 'I', 'U' ) as $name ) digi_assert( false === $parsed[ $name ]['literal'] && null === $parsed[ $name ]['value'], 'Una expresión se tomó por literal: ' . $name );

	// 5. La comparación: qué falta, qué sobra y qué cambia; los secretos y las rutas, sin valor.
	$rows = array();
	foreach ( $D::compare( $origin, $server ) as $row ) $rows[ $row['name'] ] = $row;
	digi_assert( array( 'AUTH_KEY', 'DB_HOST', 'DB_NAME', 'DB_PASSWORD', 'DISABLE_WP_CRON', 'MI_PLUGIN_LICENSE', 'WP_CACHE', 'WP_CONTENT_DIR', 'WP_DEBUG', 'WP_HOME', 'WP_MEMORY_LIMIT' ) === array_keys( $rows ), 'Las diferencias no son las esperadas: ' . json_encode( array_keys( $rows ) ) );
	digi_assert( 'different' === $rows['WP_MEMORY_LIMIT']['change'] && '256M' === $rows['WP_MEMORY_LIMIT']['backup'] && '128M' === $rows['WP_MEMORY_LIMIT']['server'], 'WP_MEMORY_LIMIT no muestra sus dos valores.' );
	digi_assert( 'backup_only' === $rows['WP_DEBUG']['change'] && 'true' === $rows['WP_DEBUG']['backup'] && 'backup_only' === $rows['DISABLE_WP_CRON']['change'] && 'server_only' === $rows['WP_CACHE']['change'], 'Lo que sólo define uno de los dos no se informó.' );
	digi_assert( 'https://origen.example' === $rows['WP_HOME']['backup'] && 'https://destino.example' === $rows['WP_HOME']['server'], 'Una dirección no es una ruta del servidor y debe mostrarse.' );
	foreach ( array( 'DB_PASSWORD' => 'secret', 'DB_NAME' => 'secret', 'DB_HOST' => 'secret', 'AUTH_KEY' => 'secret', 'MI_PLUGIN_LICENSE' => 'secret', 'WP_CONTENT_DIR' => 'path' ) as $name => $reason ) {
		digi_assert( null === ( $rows[ $name ]['backup'] ?? null ) && null === ( $rows[ $name ]['server'] ?? null ) && $reason === ( $rows[ $name ]['backup_hidden'] ?? $rows[ $name ]['server_hidden'] ), 'El valor de ' . $name . ' no se ocultó (' . $reason . ').' );
	}
	digi_assert( ! isset( $rows['EMPTY_TRASH_DAYS'] ) && ! isset( $rows['DB_CHARSET'] ) && ! isset( $rows['WP_REDIS_HOST'] ), 'Una constante igual en los dos (también la misma expresión) se dio por distinta.' );
	$all = json_encode( $D::compare( $origin, $server ) ) . $D::text( $D::compare( $origin, $server ) ) . implode( "\n", $D::lines( $D::compare( $origin, $server ) ) );
	foreach ( $secrets as $secret ) digi_assert( false === strpos( $all, $secret ) && false === strpos( $all, str_replace( '/', '\\/', $secret ) ), 'El informe muestra un valor secreto o una ruta: ' . $secret );
	digi_assert( '' === $D::text( $D::compare( $server, $server ) ), 'Dos wp-config.php iguales dieron diferencias.' );

	echo "DIGITALÍSIMO Backups: informe de diferencias de wp-config.php (sólo define() literales, sin secretos ni rutas) antes de confirmar, al subir y después de restaurar.\n";
} finally {
	digi_rmtree( $root );
}
