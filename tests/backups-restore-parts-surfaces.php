<?php
/**
 * Restaurar por partes por REST y WP-CLI, con el mismo caso de uso que la pantalla (Digitalisimo_Backups_Service):
 *
 *   S1  verify y restore declaran `parts`, `plugins`, `themes` y `sites` (REST) y `--parts`, `--plugins`, `--themes` y
 *       `--sites` (WP-CLI, en la ayuda);
 *   S2  verify dice qué trae el respaldo, qué reemplazaría la selección, qué no toca y los avisos de coherencia;
 *   S3  POST restore con parts=database: vuelve la base y ningún archivo; la respuesta lleva `parts`;
 *   S4  POST restore con plugins=[example.php]: sólo ese archivo; una selección inválida responde 400 sin tocar nada;
 *   S5  wp … restore --parts=themes: antes avisa qué reemplaza y la coherencia, y restaura sólo los temas;
 *   S6  wp … verify --plugins=… lista los plugins y temas del respaldo y lo que se reemplazaría; --parts=otra, error.
 *
 * Deben fallar con el código anterior: las rutas no declaraban la selección y la restauración reemplazaba todo.
 */
$root = sys_get_temp_dir() . '/digitalisimo-parts-surfaces-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );
if ( ! function_exists( 'get_theme_root' ) ) { function get_theme_root() { return WP_CONTENT_DIR . '/themes'; } }
update_option( Digitalisimo_Backups::OPTION, array( 'retention' => '100' ) );
$ns = 'digitalisimo-backups/v1';
$cmd = 'digitalisimo-backups';
Digitalisimo_Backups_Cli::$sleep = function() {};
$failures = 0;
function sf_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . "\n";
	}
}
/** Huellas de los archivos del sitio. */
function sf_tree() {
	$out = array();
	$base = rtrim( ABSPATH, '/' );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $entry ) if ( $entry->isFile() ) $out[ substr( $entry->getPathname(), strlen( $base ) ) ] = hash_file( 'sha256', $entry->getPathname() );
	ksort( $out );
	return $out;
}
/** Cambia, después del respaldo, la base, el plugin de un archivo y el tema. */
function sf_mutate() {
	$GLOBALS['wpdb']->set( 'blogname', 'Cambiado' );
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado\n" );
	file_put_contents( WP_CONTENT_DIR . '/themes/ejemplo/style.css', "/* Theme Name: Ejemplo cambiado */\n" );
	file_put_contents( ABSPATH . 'wp-load.php', 'núcleo cambiado' );
}
/** Igual que antes salvo $changed (ruta => contenido esperado). */
function sf_same( array $before, array $changed ) {
	$after = sf_tree();
	foreach ( $before as $path => $hash ) {
		if ( isset( $changed[ $path ] ) ) { digi_assert( ( $after[ $path ] ?? null ) === hash( 'sha256', $changed[ $path ] ), 'No volvió al respaldo: ' . $path ); continue; }
		digi_assert( ( $after[ $path ] ?? null ) === $hash, 'Cambió lo que no se eligió: ' . $path );
	}
	foreach ( $after as $path => $hash ) digi_assert( isset( $before[ $path ] ), 'Apareció: ' . $path );
}

try {
	Digitalisimo_Backups_Rest::register();
	call_user_func( $GLOBALS['digi']['actions']['rest_api_init'][0] );
	Digitalisimo_Backups_Cli::register();
	$backup = digi_backup();
	digi_assert( ! is_wp_error( $backup ), 'No se creó el respaldo: ' . ( is_wp_error( $backup ) ? $backup->get_error_message() : '' ) );
	$file = $backup['file'];

	sf_case( 'S1 REST y WP-CLI declaran la selección', function() use ( $ns ) {
		foreach ( array( '/verify', '/restore' ) as $route ) {
			$args = $GLOBALS['digi']['routes'][ $ns . '/backups/' . Digitalisimo_Backups_Rest::FILE . $route ][0]['args'] ?? array();
			foreach ( array( 'parts', 'plugins', 'themes', 'sites' ) as $name ) digi_assert( array_key_exists( $name, $args ) && null === $args[ $name ]['default'] && '' !== (string) ( $args[ $name ]['description'] ?? '' ), 'La ruta ' . $route . ' no declara ' . $name . '.' );
		}
		foreach ( array( 'restore', 'verify' ) as $method ) {
			$doc = (string) ( new ReflectionMethod( 'Digitalisimo_Backups_Cli', $method ) )->getDocComment();
			foreach ( array( '[--parts=<parts>]', '[--plugins=<plugins>]', '[--themes=<themes>]', '[--sites=<sites>]' ) as $option ) digi_assert( false !== strpos( $doc, "\t * " . $option . "\n\t * : " ), $method . ' no declara ' . $option );
		}
	} );

	sf_case( 'S2 verify dice qué trae el respaldo y qué reemplazaría', function() use ( $ns, $file ) {
		list( $code, $report ) = digi_rest( 'GET', $ns . '/backups/' . $file . '/verify', array(), true, array( 'parts' => 'database,themes' ) );
		digi_assert( 200 === $code && true === $report['compatible'], 'verify con parts falló: ' . json_encode( $report ) );
		$parts = $report['parts'];
		digi_assert( array( 'example.php' ) === $parts['available']['plugins'] && array( 'ejemplo' ) === $parts['available']['themes'], 'verify no lista los plugins y temas del respaldo: ' . json_encode( $parts['available'] ) );
		digi_assert( array( 'parts' => array( 'database', 'themes' ), 'plugins' => array(), 'themes' => array(), 'sites' => array() ) === $parts['selection'] && 'base de datos y temas' === $parts['label'], 'verify no normalizó la selección: ' . json_encode( $parts['selection'] ) );
		digi_assert( 2 === count( $parts['replaces'] ) && in_array( 'plugins', $parts['untouched'], true ) && in_array( 'database_without_plugins', array_column( $parts['coherence'], 'code' ), true ), 'verify no dice qué reemplaza, qué no toca o qué conviene elegir junto: ' . json_encode( $parts ) );
		list( $code, $report ) = digi_rest( 'GET', $ns . '/backups/' . $file . '/verify' );
		digi_assert( 200 === $code && null === $report['parts']['selection'] && 'todo' === $report['parts']['label'] && array() === $report['parts']['coherence'], 'verify sin selección no es «todo».' );
	} );

	sf_case( 'S3 POST restore con parts=database: vuelve la base y ningún archivo', function() use ( $ns, $file ) {
		sf_mutate();
		$before = sf_tree();
		list( $code, $restored ) = digi_rest( 'POST', $ns . '/backups/' . $file . '/restore', array( 'confirm' => 'RESTAURAR', 'parts' => array( 'database' ) ) );
		digi_assert( 200 === $code && true === $restored['restored'], 'POST restore con parts no restauró: ' . json_encode( $restored ) );
		digi_assert( 'Sitio de prueba' === $GLOBALS['wpdb']->value( 'blogname' ), 'La base de datos no volvió al respaldo.' );
		sf_same( $before, array() );
		digi_assert( false === $restored['parts']['all'] && array( 'base de datos' ) === $restored['parts']['restored'] && in_array( 'plugins', $restored['parts']['not_restored'], true ), 'La respuesta no dice qué partes se restauraron: ' . json_encode( $restored['parts'] ) );
	} );

	sf_case( 'S4 POST restore con un plugin concreto; una selección inválida, 400', function() use ( $ns, $file ) {
		sf_mutate();
		$before = sf_tree();
		list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $file . '/restore', array( 'confirm' => 'RESTAURAR', 'parts' => 'medios,nada' ) );
		digi_assert( 400 === $code && 'digitalisimo_backups_parts' === $error['code'], 'Una parte desconocida no respondió 400: ' . json_encode( $error ) );
		list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $file . '/restore', array( 'confirm' => 'RESTAURAR', 'plugins' => array( 'no-existe' ) ) );
		digi_assert( 422 === $code && false !== strpos( $error['message'], 'no-existe' ), 'Un plugin que el respaldo no trae no respondió 422: ' . json_encode( $error ) );
		sf_same( $before, array() );
		list( $code, $restored ) = digi_rest( 'POST', $ns . '/backups/' . $file . '/restore', array( 'confirm' => 'RESTAURAR', 'plugins' => array( 'example.php' ) ) );
		digi_assert( 200 === $code && true === $restored['restored'], 'POST restore con plugins no restauró: ' . json_encode( $restored ) );
		sf_same( $before, array( '/wp-content/plugins/example.php' => "<?php // plugin de ejemplo\n" ) );
		digi_assert( 'Cambiado' === $GLOBALS['wpdb']->value( 'blogname' ), 'La base de datos cambió sin elegirla.' );
		digi_assert( false !== strpos( $restored['notice'], 'Restauración por partes: se restauró el plugin example.php' ), 'El aviso no dice qué se restauró: ' . $restored['notice'] );
	} );

	sf_case( 'S5 wp … restore --parts=themes avisa y restaura sólo los temas', function() use ( $cmd, $file ) {
		$GLOBALS['wpdb']->set( 'blogname', 'Cambiado otra vez' );
		file_put_contents( WP_CONTENT_DIR . '/themes/ejemplo/style.css', "/* Theme Name: Ejemplo cambiado */\n" );
		$before = sf_tree();
		list( $code, $out ) = digi_wp( $cmd, 'restore', array( $file ), array( 'confirm' => 'RESTAURAR', 'parts' => 'themes' ) );
		$text = implode( "\n", $out );
		digi_assert( 0 === $code, 'restore --parts no terminó: ' . $text );
		digi_assert( false !== strpos( $text, 'Se restaura sólo: temas.' ) && false !== strpos( $text, 'Warning: Temas sin la base de datos' ) && false !== strpos( $text, 'No se toca: base de datos' ), 'restore --parts no avisó antes qué reemplaza y la coherencia: ' . $text );
		sf_same( $before, array( '/wp-content/themes/ejemplo/style.css' => "/* Theme Name: Ejemplo */\n" ) );
		digi_assert( 'Cambiado otra vez' === $GLOBALS['wpdb']->value( 'blogname' ), 'La base de datos cambió sin elegirla.' );
		digi_assert( false !== strpos( end( $out ), 'Restauración por partes: se restauró temas' ), 'El resultado no dice qué se restauró: ' . end( $out ) );
	} );

	sf_case( 'S6 wp … verify con la selección; una parte desconocida es un error', function() use ( $cmd, $file ) {
		list( $code, $out ) = digi_wp( $cmd, 'verify', array( $file ), array( 'plugins' => 'example.php' ) );
		$text = implode( "\n", $out );
		digi_assert( 0 === $code && false !== strpos( $text, 'Plugins (1): example.php.' ) && false !== strpos( $text, 'Temas (1): ejemplo.' ) && false !== strpos( $text, 'Se restaura sólo: el plugin example.php.' ) && false !== strpos( $text, 'Warning: Plugins sin la base de datos' ), 'verify --plugins no dijo qué trae y qué reemplazaría: ' . $text );
		$before = sf_tree();
		list( $code, $out ) = digi_wp( $cmd, 'restore', array( $file ), array( 'confirm' => 'RESTAURAR', 'parts' => 'otra' ) );
		digi_assert( 1 === $code && false !== strpos( end( $out ), 'Parte desconocida' ), 'restore --parts=otra no se detuvo: ' . implode( "\n", $out ) );
		sf_same( $before, array() );
	} );
} catch ( Throwable $e ) {
	$failures++;
	echo 'FALLA · ' . $e->getMessage() . "\n";
} finally {
	digi_rmtree( $root );
}
echo $failures ? "FALLA: {$failures} caso(s) de restaurar por partes por REST o WP-CLI.\n" : "DIGITALÍSIMO Backups: restaurar por partes por REST y WP-CLI con el caso de uso de la pantalla.\n";
exit( $failures ? 1 : 0 );
