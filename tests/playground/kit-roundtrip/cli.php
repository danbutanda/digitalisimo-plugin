<?php
/**
 * WP-CLI dentro del Playground de kit-roundtrip.sh, como hace el paso «wp-cli» de los blueprints: este archivo no
 * carga WordPress; arma argv y deja que wp-cli.phar lo haga (--path=/wordpress). La salida va a archivos de la carpeta
 * del sitio y se devuelve como JSON al terminar el proceso (WP-CLI sale con exit()).
 *
 * Uso: GET /wp-content/probe/cli.php?token=<token>&args=<JSON con la lista de argumentos, sin «wp»>
 * Ejemplo: args=["digitalisimo-elements","kit","import","/wordpress/wp-content/digi-shared/exports/a-rest.zip","--mode=fresh","--user=admin","--format=json"]
 */

$digi_dir  = __DIR__;
$digi_opts = json_decode( (string) @file_get_contents( $digi_dir . '/options.json' ), true ) ?: array();
if ( empty( $digi_opts['token'] ) || ! hash_equals( (string) $digi_opts['token'], (string) ( $_GET['token'] ?? '' ) ) ) {
	http_response_code( 403 );
	exit;
}
$digi_args = json_decode( (string) ( $_GET['args'] ?? $_POST['args'] ?? '[]' ), true );
if ( ! is_array( $digi_args ) || ! $digi_args ) {
	http_response_code( 400 );
	echo json_encode( array( 'error' => 'args debe ser una lista JSON' ) );
	exit;
}
$digi_phar = '/wordpress/wp-content/digi-shared/wp-cli.phar';
if ( ! is_file( $digi_phar ) ) {
	http_response_code( 500 );
	echo json_encode( array( 'error' => 'falta wp-cli.phar en digi-shared' ) );
	exit;
}
$digi_run = 'cli-' . substr( md5( microtime( true ) . mt_rand() ), 0, 8 );
$GLOBALS['digi_cli_files'] = array( $digi_dir . '/' . $digi_run . '.out', $digi_dir . '/' . $digi_run . '.err' );
putenv( 'SHELL_PIPE=0' );
putenv( 'WP_CLI_CACHE_DIR=/tmp/wp-cli-cache' );
$GLOBALS['argv'] = array_merge( array( $digi_phar, '--path=/wordpress' ), array_map( 'strval', $digi_args ) );
$_SERVER['argv'] = $GLOBALS['argv'];
$GLOBALS['argc'] = count( $GLOBALS['argv'] );
define( 'STDIN', fopen( 'php://memory', 'rb' ) );
define( 'STDOUT', fopen( $GLOBALS['digi_cli_files'][0], 'wb' ) );
define( 'STDERR', fopen( $GLOBALS['digi_cli_files'][1], 'wb' ) );
$GLOBALS['digi_cli_start'] = microtime( true );
/** Texto de un aviso en HTML de PHP, sin depender de WordPress (puede no haber cargado). */
function digi_cli_notice_text( $html ) {
	return html_entity_decode( preg_replace( '/\s+/', ' ', strip_tags( (string) $html ) ), ENT_QUOTES, 'UTF-8' );
}
register_shutdown_function(
	static function () {
		list( $out, $err ) = $GLOBALS['digi_cli_files'];
		$error = error_get_last();
		// Lo que WP-CLI escribe con echo (y, en php-wasm, también lo que manda a STDOUT) queda en el búfer: se une a
		// stdout sin la línea «#!» del phar ni los avisos en HTML de PHP, que van aparte.
		$buffer = '';
		while ( ob_get_level() ) {
			$buffer = (string) ob_get_clean() . $buffer;
		}
		$notices = array();
		$buffer  = preg_replace( '/\A#![^\n]*\n/', '', $buffer );
		$buffer  = preg_replace_callback(
			'#(?:<br />\s*)?<b>(?:Deprecated|Warning|Notice|Fatal error|Parse error)</b>:.*?(?:<br />|$)\s*#s',
			static function ( $m ) use ( &$notices ) {
				$notices[] = trim( digi_cli_notice_text( $m[0] ) );
				return '';
			},
			$buffer
		);
		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode(
			array(
				'stdout' => (string) @file_get_contents( $out ) . $buffer,
				'notices' => $notices,
				'stderr' => (string) @file_get_contents( $err ),
				'fatal'  => $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ? $error['message'] . ' @ ' . $error['file'] . ':' . $error['line'] : '',
				'ms'     => (int) round( ( microtime( true ) - $GLOBALS['digi_cli_start'] ) * 1000 ),
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
		);
		@unlink( $out );
		@unlink( $err );
	}
);
// La línea «#!» del phar, lo que WP-CLI escribe y los avisos de PHP quedan en el búfer: la función de cierre lo recoge
// antes de mandar las cabeceras y el JSON.
ob_start();
require $digi_phar;
