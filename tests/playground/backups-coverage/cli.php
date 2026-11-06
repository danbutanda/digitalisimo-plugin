<?php
/**
 * WP-CLI real dentro del Playground de backups-coverage.sh, como el paso «wp-cli» de los blueprints (y como
 * kit-roundtrip/cli.php): no carga WordPress; arma argv y deja que wp-cli.phar lo haga con --path=/wordpress. Devuelve
 * JSON con stdout, stderr y el código de salida (WP-CLI termina con exit()).
 *
 * Uso: POST /wp-content/digi-web/cli.php?token=<token> con args=<lista JSON de argumentos, sin «wp»>
 */
$digi_dir = __DIR__;
$digi_token = trim( (string) @file_get_contents( $digi_dir . '/token.txt' ) );
if ( '' === $digi_token || ! hash_equals( $digi_token, (string) ( $_GET['token'] ?? '' ) ) ) {
	http_response_code( 403 );
	exit;
}
$digi_args = json_decode( (string) ( $_POST['args'] ?? '[]' ), true );
$digi_phar = $digi_dir . '/wp-cli.phar';
if ( ! is_array( $digi_args ) || ! $digi_args || ! is_file( $digi_phar ) ) {
	http_response_code( 400 );
	echo json_encode( array( 'error' => 'args debe ser una lista JSON y wp-cli.phar debe existir' ) );
	exit;
}
// Fuera de /wordpress: un respaldo en curso no debe ver archivos que cambian mientras los inventaría.
@mkdir( '/digi-probe/cli-runs', 0700, true );
$digi_run = '/digi-probe/cli-runs/run-' . bin2hex( random_bytes( 4 ) );
$GLOBALS['digi_cli_files'] = array( $digi_run . '.out', $digi_run . '.err' );
putenv( 'SHELL_PIPE=0' );
putenv( 'WP_CLI_CACHE_DIR=/digi-probe/wp-cli-cache' );
$GLOBALS['argv'] = array_merge( array( $digi_phar, '--path=/wordpress', '--no-color' ), array_map( 'strval', $digi_args ) );
$_SERVER['argv'] = $GLOBALS['argv'];
$GLOBALS['argc'] = count( $GLOBALS['argv'] );
define( 'STDIN', fopen( 'php://memory', 'rb' ) );
define( 'STDOUT', fopen( $GLOBALS['digi_cli_files'][0], 'wb' ) );
define( 'STDERR', fopen( $GLOBALS['digi_cli_files'][1], 'wb' ) );
register_shutdown_function(
	static function () {
		list( $out, $err ) = $GLOBALS['digi_cli_files'];
		$error = error_get_last();
		$buffer = '';
		while ( ob_get_level() ) $buffer = (string) ob_get_clean() . $buffer;
		$buffer = preg_replace( '/\A#![^\n]*\n/', '', $buffer );
		// Avisos de PHP en HTML (los de wp-cli.phar con PHP 8.5, por ejemplo) van aparte para no ensuciar la salida.
		$notices = array();
		$buffer = preg_replace_callback(
			'#(?:<br />\s*)?<b>(?:Deprecated|Warning|Notice|Fatal error|Parse error)</b>:.*?(?:<br />|$)\s*#s',
			static function ( $m ) use ( &$notices ) {
				$notices[] = trim( html_entity_decode( preg_replace( '/\s+/', ' ', strip_tags( $m[0] ) ), ENT_QUOTES, 'UTF-8' ) );
				return '';
			},
			$buffer
		);
		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode(
			array(
				'stdout' => (string) @file_get_contents( $out ) . $buffer,
				'stderr' => (string) @file_get_contents( $err ),
				'notices' => $notices,
				'fatal' => $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ? $error['message'] . ' @ ' . $error['file'] . ':' . $error['line'] : '',
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
		);
		@unlink( $out );
		@unlink( $err );
	}
);
ob_start();
require $digi_phar;
