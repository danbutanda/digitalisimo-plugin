<?php
/**
 * Laboratorio H7: `wp help digitalisimo-backups` mostraba literalmente `/** Automatizaciones de respaldo completo. *\/`
 * y el de la cola de trabajos. WP-CLI saca la ayuda de los docblocks con su DocParser, que sólo quita las marcas de un
 * docblock que empieza con `/**` y un salto de línea. Esta prueba aplica las mismas reglas (WP-CLI 2.x,
 * `WP_CLI\DocParser::remove_decorations()` y `get_shortdesc()`) a cada comando y subcomando registrado.
 */
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../digitalisimo-backups/includes/class-backups-cli.php';

function digi_assert( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( 'FALLA: ' . $message ); }
/** Lo que WP-CLI muestra de un docblock: sin las marcas del comentario. */
function digi_cli_doc( $comment ) {
	$comment = preg_replace( '|^/\*\*[\r\n]+|', '', (string) $comment );
	$comment = preg_replace( '|\n[\t ]*\*/$|', '', $comment );
	return preg_replace( '|^[\t ]*\* ?|m', '', $comment );
}
function digi_cli_shortdesc( $doc ) { return preg_match( '|^([^@][^\n]+)\n*|', $doc, $matches ) ? $matches[1] : ''; }

// Los comandos que registra Digitalisimo_Backups_Cli::register() y sus subcomandos (métodos públicos no estáticos).
$commands = array( 'digitalisimo-backups' => 'Digitalisimo_Backups_Cli', 'digitalisimo-backups automation' => 'Digitalisimo_Backups_Cli_Automation', 'digitalisimo-backups jobs' => 'Digitalisimo_Backups_Cli_Jobs', 'digitalisimo-backups upload' => 'Digitalisimo_Backups_Cli_Upload', 'digitalisimo-backups backuply' => 'Digitalisimo_Backups_Cli_Backuply' );
$register = file_get_contents( __DIR__ . '/../digitalisimo-backups/includes/class-backups-cli.php' );
foreach ( $commands as $name => $class ) digi_assert( false !== strpos( $register, "WP_CLI::add_command( '" . $name . "', " ), 'El comando ' . $name . ' ya no se registra como espera la prueba.' );
$checked = 0;
foreach ( $commands as $name => $class ) {
	$reflection = new ReflectionClass( $class );
	$entries = array( $name => $reflection->getDocComment() );
	foreach ( $reflection->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
		if ( $method->isStatic() || 0 === strpos( $method->getName(), '__' ) ) continue;
		$entries[ $name . ' ' . rtrim( $method->getName(), '_' ) ] = $method->getDocComment();
	}
	foreach ( $entries as $label => $comment ) {
		digi_assert( is_string( $comment ) && '' !== $comment, 'El comando «wp ' . $label . '» no tiene ayuda.' );
		$doc = digi_cli_doc( $comment );
		$short = digi_cli_shortdesc( $doc );
		digi_assert( '' !== trim( $short ) && false === strpos( $doc, '/**' ) && false === strpos( $doc, '*/' ), 'La ayuda de «wp ' . $label . '» muestra el comentario literal: ' . $short );
		$checked++;
	}
}
digi_assert( $checked >= 12, 'Se comprobaron menos comandos de los esperados: ' . $checked );
echo "DIGITALÍSIMO Backups: la ayuda de WP-CLI de los {$checked} comandos y subcomandos sale sin las marcas del comentario.\n";
