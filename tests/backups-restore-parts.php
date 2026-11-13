<?php
/**
 * Restaurar por partes (decisión del propietario del 2026-11-13): de un respaldo completo vuelve sólo lo elegido, con la
 * misma restauración por pasos que la completa (verificación, copia previa completa, mantenimiento, reanudación y vuelta a
 * la copia previa). Con huellas de cada archivo y de las opciones antes y después:
 *
 *   P1  la selección (partes, alias, plugins y temas concretos, medios de un sitio de la red), sus errores, la parte de
 *       cada ruta, qué carpetas se recorren, qué se retira, el inventario de un ZIP y los avisos de coherencia;
 *   P2  sólo la base de datos: vuelve la base del respaldo (un solo RENAME) y ningún archivo cambia; la copia previa es
 *       completa (trae también lo que no se restauró);
 *   P3  sólo un plugin concreto: su carpeta vuelve entera; los demás plugins, uno instalado después, la base y lo demás,
 *       idénticos;
 *   P4  sólo los medios sin la base: vuelven los del respaldo, se conservan los subidos después y se retira un .php
 *       inyectado; ni una tabla cambia (ningún RENAME);
 *   P5  sólo los temas: vuelve el tema y se retira uno instalado después; los plugins, idénticos;
 *   P6  base de datos y plugins enteros: un plugin instalado después se retira; temas y medios, idénticos; y el núcleo,
 *       los mu-plugins y el resto de wp-content (idiomas, drop-ins) sin tocar plugins, temas, medios ni la base;
 *   P7  una falla a mitad del cambio (plugins concretos, uno que ya no existe en el sitio) vuelve sola a la copia previa
 *       y repone sólo esas partes: el sitio queda idéntico a antes de restaurar (también lo no elegido);
 *   P8  lo elegido que el respaldo no trae, una parte desconocida o nada elegido se rechazan sin tocar nada;
 *   P9  la pantalla: «Qué restaurar» plegado con «Todo» marcado, las partes, los plugins y temas del respaldo y los
 *       avisos; el handler restaura sólo lo marcado y con «Todo» marcado, todo;
 *   P10 regresión: sin selección, la restauración completa de siempre (todo vuelve al respaldo).
 *
 * Deben fallar con el código anterior: no existía Digitalisimo_Backups_Restore_Parts y `parts` se ignoraba (se
 * restauraba todo).
 */
require __DIR__ . '/lib/backups-env.php';
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }

$failures = 0;
function pt_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function pt_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
$B = 'Digitalisimo_Backups';
$P = 'Digitalisimo_Backups_Restore_Parts';
$root = rtrim( ABSPATH, '/' );
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
if ( ! class_exists( $P ) ) {
	echo "FALLA · no existe Digitalisimo_Backups_Restore_Parts: la restauración por partes no está.\n";
	bk_cleanup();
	exit( 1 );
}

// El sitio del respaldo: algo en cada parte.
foreach ( array(
	'wp-content/plugins/demo/demo.php' => "<?php /* Plugin Name: Demo */\n",
	'wp-content/plugins/demo/inc/a.php' => "<?php // a original\n",
	'wp-content/plugins/otro/otro.php' => "<?php /* Plugin Name: Otro */\n",
	'wp-content/plugins/borrado/borrado.php' => "<?php /* Plugin Name: Borrado después */\n",
	'wp-content/plugins/index.php' => "<?php // Silence is golden.\n",
	'wp-content/themes/tema/style.css' => "/* Theme Name: Tema */\n",
	'wp-content/themes/tema/functions.php' => "<?php // funciones originales\n",
	'wp-content/themes/segundo/style.css' => "/* Theme Name: Segundo */\n",
	'wp-content/uploads/2026/11/foto.jpg' => str_repeat( "\xff\xd8\xff", 3000 ),
	'wp-content/uploads/2026/11/nota.txt' => "nota original\n",
	'wp-content/mu-plugins/mu.php' => "<?php // mu original\n",
	'wp-content/languages/es_MX.mo' => "mo original\n",
	'wp-content/object-cache.php' => "<?php // drop-in original\n",
) as $name => $content ) { @mkdir( dirname( $root . '/' . $name ), 0700, true ); file_put_contents( $root . '/' . $name, $content ); }
$GLOBALS['bk_options']['blogname'] = 'Sitio del respaldo';
$GLOBALS['bk_options']['active_plugins'] = array( 'demo/demo.php', 'otro/otro.php' );
$backup = bk_backup( true );
$backup_tree = bk_tree();
$backup_db = null;
/** Las opciones del sitio (sin las de Backups, que la restauración misma escribe). */
function pt_db() {
	$out = array();
	foreach ( $GLOBALS['bk_options'] as $name => $value ) if ( 0 !== strpos( (string) $name, 'digitalisimo_backups' ) && 'cron' !== $name ) $out[ $name ] = $value;
	ksort( $out );
	return $out;
}
$backup_db = pt_db();
$snapshot_options = $GLOBALS['bk_options'];
$snapshot = sys_get_temp_dir() . '/digitalisimo-parts-snapshot-' . bin2hex( random_bytes( 4 ) );
function pt_copy( $from, $to ) {
	if ( is_file( $from ) ) { @mkdir( dirname( $to ), 0700, true ); copy( $from, $to ); return; }
	@mkdir( $to, 0700, true );
	foreach ( scandir( $from ) as $name ) if ( '.' !== $name && '..' !== $name ) pt_copy( $from . '/' . $name, $to . '/' . $name );
}
pt_copy( $root, $snapshot );

/** Lo que cambia después del respaldo, en cada parte (también la base de datos). */
function pt_mutate( $root ) {
	$GLOBALS['bk_options']['blogname'] = 'Cambiado después del respaldo';
	$GLOBALS['bk_options']['opcion_nueva'] = 'no estaba en el respaldo';
	$GLOBALS['bk_options']['active_plugins'] = array( 'demo/demo.php', 'otro/otro.php', 'nuevo/nuevo.php' );
	$files = array(
		'wp-load.php' => "<?php // núcleo cambiado\n",
		'wp-admin/index.php' => "<?php // wp-admin cambiado\n",
		'wp-content/plugins/demo/inc/a.php' => "<?php // a cambiado\n",
		'wp-content/plugins/demo/inc/extra.php' => "<?php // añadido al plugin después\n",
		'wp-content/plugins/otro/otro.php' => "<?php /* Plugin Name: Otro 2 */\n",
		'wp-content/plugins/example.php' => "<?php // plugin de un archivo cambiado\n",
		'wp-content/plugins/nuevo/nuevo.php' => "<?php /* Plugin Name: Nuevo */\n",
		'wp-content/themes/tema/style.css' => "/* Theme Name: Tema 2 */\n",
		'wp-content/themes/nuevo-tema/style.css' => "/* Theme Name: Nuevo tema */\n",
		'wp-content/uploads/2026/11/foto.jpg' => str_repeat( "\x00\x01", 500 ),
		'wp-content/uploads/2026/12/nueva.jpg' => str_repeat( "\xff\xd8", 700 ),
		'wp-content/uploads/2026/11/inyectado.php' => "<?php system( \$_GET['c'] );\n",
		'wp-content/mu-plugins/mu.php' => "<?php // mu cambiado\n",
		'wp-content/languages/es_MX.mo' => "mo cambiado\n",
		'wp-content/object-cache.php' => "<?php // drop-in cambiado\n",
	);
	foreach ( $files as $name => $content ) { @mkdir( dirname( $root . '/' . $name ), 0700, true ); file_put_contents( $root . '/' . $name, $content ); }
	@unlink( $root . '/wp-content/uploads/2026/11/nota.txt' );
	bk_remove_tree( $root . '/wp-content/plugins/borrado' );
}
/** Deja el sitio como tras el respaldo, con los cambios de después; devuelve array( árbol, opciones ). */
function pt_prepare( $root, $snapshot, array $options ) {
	foreach ( Digitalisimo_Backups_Jobs::active() as $job ) Digitalisimo_Backups_Job_Store::remove_tree( Digitalisimo_Backups_Job_Store::directory( $job['id'] ) );
	foreach ( scandir( $root ) as $name ) if ( '.' !== $name && '..' !== $name ) bk_remove_tree( $root . '/' . $name );
	pt_copy( $snapshot, $root );
	$GLOBALS['bk_options'] = $options;
	unset( $GLOBALS['bk_on_mkdir'] );
	pt_mutate( $root );
	$GLOBALS['wpdb']->queries = array();
	return array( bk_tree(), pt_db() );
}
function pt_restore( $file, array $args ) {
	return Digitalisimo_Backups::service_restore( basename( $file ), $args + array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false, 'via' => 'test' ) );
}
function pt_finish() {
	for ( $i = 0; $i < 3000; $i++ ) {
		if ( ! Digitalisimo_Backups_Jobs::active() ) return;
		Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	}
	throw new RuntimeException( 'La restauración no terminó.' );
}
function pt_last( $rollback = false ) {
	$last = null;
	foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( 'restore' === $job['kind'] && $rollback === ! empty( $job['spec']['rollback'] ) && ( ! $last || (float) $job['queued_at'] >= (float) $last['queued_at'] ) ) $last = $job;
	return $last;
}
/**
 * Huellas: cada archivo de $restored vuelve al respaldo, cada uno de $removed ya no está y todo lo demás (lo no elegido)
 * queda idéntico a $before. Nunca .maintenance ni carpetas de una restauración.
 */
function pt_assert_tree( array $before, array $backup, array $restored, array $removed ) {
	$after = bk_tree();
	foreach ( $restored as $path ) pt_check( isset( $backup[ $path ] ) && ( $after[ $path ] ?? null ) === $backup[ $path ], 'No volvió al respaldo: ' . $path );
	foreach ( $removed as $path ) pt_check( ! isset( $after[ $path ] ), 'No se retiró: ' . $path );
	$skip = array_flip( array_merge( $restored, $removed ) );
	foreach ( $before as $path => $hash ) {
		if ( 'dir' === $hash || isset( $skip[ $path ] ) || '/wp-config.php' === $path ) continue;
		pt_check( ( $after[ $path ] ?? null ) === $hash, 'Cambió lo que no se eligió: ' . $path . ( isset( $after[ $path ] ) ? '' : ' (desapareció)' ) );
	}
	foreach ( $after as $path => $hash ) {
		if ( 'dir' === $hash || isset( $before[ $path ] ) || in_array( $path, $restored, true ) ) continue;
		pt_check( false, 'Apareció lo que no se eligió: ' . $path );
	}
	pt_check( ! file_exists( ABSPATH . '.maintenance' ), 'El sitio quedó en mantenimiento.' );
	foreach ( array_keys( $after ) as $path ) pt_check( false === strpos( $path, '/.digitalisimo-' ), 'Quedó una carpeta o área de la restauración: ' . $path );
}
function pt_renames() { return count( preg_grep( '/^RENAME TABLE /', $GLOBALS['wpdb']->queries ) ); }
$layout = bk_call( $B, 'restore_layout' );

pt_case( 'P1 la selección, las rutas, el inventario y los avisos de coherencia', function() use ( $P, $layout, $backup ) {
	pt_check( null === $P::normalize( array() ) && null === $P::normalize( array( 'parts' => '' ) ) && null === $P::normalize( array( 'parts' => 'all' ) ) && null === $P::normalize( array( 'parts' => 'database,core,plugins,themes,uploads,mu-plugins,content' ) ), 'Sin selección (o con todas las partes) no es «todo».' );
	$sel = $P::normalize( array( 'parts' => 'media, DB', 'plugins' => 'demo,otro', 'themes' => array( 'tema' ) ) );
	pt_check( array( 'database', 'uploads' ) === $sel['parts'] && array( 'demo', 'otro' ) === $sel['plugins'] && array( 'tema' ) === $sel['themes'] && array() === $sel['sites'], 'La selección no se normalizó: ' . json_encode( $sel ) );
	pt_check( array() === $P::normalize( array( 'parts' => 'plugins', 'plugins' => 'demo' ) )['plugins'], 'Un plugin concreto con los plugins enteros no se descartó.' );
	foreach ( array( array( 'parts' => 'todo-mal' ), array( 'parts' => array() ), array( 'plugins' => '../wp-config.php' ), array( 'themes' => '.oculto' ), array( 'plugins' => "a\nb" ), array( 'sites' => '2' ), array( 'sites' => 'x' ) ) as $bad ) {
		try { $P::normalize( $bad ); pt_check( false, 'Se aceptó una selección inválida: ' . json_encode( $bad ) ); } catch ( InvalidArgumentException $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	// Parte de cada ruta, con la raíz más larga.
	foreach ( array( 'wp-load.php' => 'core', 'wp-admin/index.php' => 'core', '.htaccess' => 'core', 'otra-app/x.php' => 'core', 'wp-content/plugins/demo/a.php' => 'plugins', 'wp-content/plugins/example.php' => 'plugins', 'wp-content/themes/tema/style.css' => 'themes', 'wp-content/uploads/2026/x.jpg' => 'uploads', 'wp-content/mu-plugins/mu.php' => 'mu-plugins', 'wp-content/object-cache.php' => 'content', 'wp-content/languages/es_MX.mo' => 'content' ) as $path => $part ) pt_check( $part === $P::classify( $path, $layout )[0], 'Parte equivocada de ' . $path . ': ' . $P::classify( $path, $layout )[0] );
	pt_check( array( 'plugins', 'demo' ) === $P::classify( 'wp-content/plugins/demo/inc/a.php', $layout ) && array( 'plugins', 'example.php' ) === $P::classify( 'wp-content/plugins/example.php', $layout ), 'El plugin concreto de una ruta no es su carpeta o su archivo.' );
	pt_check( array( 'uploads', 3 ) === $P::classify( 'wp-content/uploads/sites/3/2026/x.jpg', $layout ) && array( 'uploads', 1 ) === $P::classify( 'wp-content/uploads/2026/x.jpg', $layout ), 'Los medios de un sitio de la red no se reconocen.' );
	$demo = $P::normalize( array( 'plugins' => 'demo' ) );
	pt_check( $P::selected( $demo, 'wp-content/plugins/demo/inc/a.php', $layout ) && ! $P::selected( $demo, 'wp-content/plugins/demo2/a.php', $layout ) && ! $P::selected( $demo, 'wp-content/plugins/otro/otro.php', $layout ) && ! $P::selected( $demo, 'wp-load.php', $layout ), 'Un plugin concreto elige de más o de menos.' );
	pt_check( $P::relevant_dir( $demo, 'wp-content', $layout ) && $P::relevant_dir( $demo, 'wp-content/plugins', $layout ) && $P::relevant_dir( $demo, 'wp-content/plugins/demo/inc', $layout ) && ! $P::relevant_dir( $demo, 'wp-content/plugins/otro', $layout ) && ! $P::relevant_dir( $demo, 'wp-content/uploads', $layout ) && ! $P::relevant_dir( $demo, 'wp-admin', $layout ), 'El recorrido entra donde no se eligió nada (o no llega a lo elegido).' );
	$GLOBALS['bk_multisite'] = true;
	$site1 = $P::normalize( array( 'sites' => '1,3' ) );
	$GLOBALS['bk_multisite'] = false;
	pt_check( $P::selected( $site1, 'wp-content/uploads/2026/a.jpg', $layout ) && $P::selected( $site1, 'wp-content/uploads/sites/3/a.jpg', $layout ) && ! $P::selected( $site1, 'wp-content/uploads/sites/2/a.jpg', $layout ), 'Los medios de un sitio eligen los de otro.' );
	pt_check( $P::relevant_dir( $site1, 'wp-content/uploads/sites', $layout ) && $P::relevant_dir( $site1, 'wp-content/uploads/sites/3/2026', $layout ) && ! $P::relevant_dir( $site1, 'wp-content/uploads/sites/2', $layout ), 'El recorrido de los medios de un sitio entra en los de otro.' );
	$media = $P::normalize( array( 'parts' => 'uploads' ) );
	pt_check( $P::removable( $media, 'wp-content/uploads/x.php', $layout ) && ! $P::removable( $media, 'wp-content/uploads/x.jpg', $layout ) && $P::removable( $P::normalize( array( 'parts' => 'uploads,database' ) ), 'wp-content/uploads/x.jpg', $layout ), 'Sin la base de datos, un medio subido después se retira (o un .php se conserva).' );
	// Inventario de un ZIP real: plugins (carpetas y de un archivo, sin el index.php), temas y partes.
	$zip = new ZipArchive();
	pt_check( true === $zip->open( $backup ), 'No abre el respaldo.' );
	$inventory = $P::inventory( $zip, $layout );
	$zip->close();
	pt_check( array( 'borrado', 'demo', 'example.php', 'otro' ) === $inventory['plugins'] && array( 'segundo', 'tema' ) === $inventory['themes'] && array( 'database', 'core', 'plugins', 'themes', 'uploads', 'mu-plugins', 'content' ) === $inventory['parts'], 'El inventario del respaldo no es el esperado: ' . json_encode( $inventory ) );
	pt_check( array( 'el plugin «nada»', 'el tema «x»' ) === $P::missing( $P::normalize( array( 'plugins' => 'demo,nada', 'themes' => 'x' ) ), $inventory ), 'Lo elegido que el respaldo no trae no se detecta.' );
	// Coherencia: avisa sin bloquear y nunca con todo.
	$codes = function( $input ) use ( $P ) { return array_column( $P::coherence( $P::normalize( $input ) ), 'code' ); };
	pt_check( array() === $P::coherence( null ), 'Con todo no debe haber avisos de coherencia.' );
	pt_check( array( 'plugins_without_database' ) === $codes( array( 'plugins' => 'demo' ) ), 'Un plugin sin la base de datos no avisa: ' . json_encode( $codes( array( 'plugins' => 'demo' ) ) ) );
	pt_check( array( 'database_without_plugins', 'database_without_themes', 'database_without_core', 'database_without_uploads' ) === $codes( array( 'parts' => 'database' ) ), 'La base sola no avisa de lo que conviene elegir junto: ' . json_encode( $codes( array( 'parts' => 'database' ) ) ) );
	pt_check( array( 'uploads_without_database' ) === $codes( array( 'parts' => 'uploads' ) ) && array() === $codes( array( 'parts' => 'database,core,plugins,themes,uploads,mu-plugins' ) ), 'Los avisos de los medios o de casi todo no son los esperados.' );
	pt_check( 'base de datos, el plugin demo y los temas segundo y tema' === $P::label( $P::normalize( array( 'parts' => 'database', 'plugins' => 'demo', 'themes' => 'tema,segundo' ) ) ), 'La etiqueta no dice lo elegido: ' . $P::label( $P::normalize( array( 'parts' => 'database', 'plugins' => 'demo', 'themes' => 'tema,segundo' ) ) ) );
	$described = $P::describe( $P::normalize( array( 'parts' => 'plugins,themes' ) ), $inventory, array( 'plugins' => array( 'demo', 'nuevo' ), 'themes' => array( 'tema', 'activo' ), 'active_plugins' => array( 'nuevo' ), 'active_themes' => array( 'activo' ) ) );
	pt_check( false !== strpos( implode( ' ', $described['replaces'] ), '(1: nuevo)' ) && 2 === count( $described['warnings'] ) && false !== strpos( $described['warnings'][1], 'tema activo' ), 'La confirmación no dice qué plugins y temas activos se retiran: ' . json_encode( $described ) );
} );

pt_case( 'P2 sólo la base de datos: ningún archivo cambia y la copia previa es completa', function() use ( $root, $snapshot, $snapshot_options, $backup, $backup_tree, $backup_db, $P ) {
	list( $before ) = pt_prepare( $root, $snapshot, $snapshot_options );
	$result = pt_restore( $backup, array( 'parts' => array( 'database' ) ) );
	pt_check( ! is_wp_error( $result ), 'Se rechazó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	pt_finish();
	$job = pt_last();
	pt_check( 'success' === $job['status'], 'No terminó: ' . json_encode( $job['error'] ) );
	pt_check( $backup_db === pt_db(), 'La base de datos no volvió al respaldo.' );
	pt_check( 1 === pt_renames(), 'Las tablas no cambiaron con un solo RENAME TABLE.' );
	pt_assert_tree( $before, $backup_tree, array(), array() );
	$report = $job['result']['restore']['parts'];
	pt_check( false === $report['all'] && array( 'base de datos' ) === $report['restored'] && in_array( 'medios', $report['not_restored'], true ) && false !== strpos( $job['result']['restore']['notice'], 'Restauración por partes: se restauró base de datos' ), 'El informe no dice qué se restauró y qué no: ' . json_encode( $report ) );
	// La copia previa es un respaldo completo: también trae los medios y plugins de ahora, que no se restauraron.
	$safety = rtrim( bk_storage(), '/' ) . '/' . $job['result']['restore']['safety_backup'];
	$zip = new ZipArchive();
	pt_check( is_file( $safety ) && true === $zip->open( $safety ), 'La copia previa no está en el almacén.' );
	pt_check( false !== $zip->locateName( 'wordpress/wp-content/uploads/2026/12/nueva.jpg' ) && false !== $zip->locateName( 'wordpress/wp-content/plugins/nuevo/nuevo.php' ) && false !== $zip->locateName( 'database.sql' ), 'La copia previa no es completa.' );
	$zip->close();
	$item = bk_call( 'Digitalisimo_Backups', 'history_item', basename( $safety ) );
	pt_check( ! empty( $item['locked'] ) && 'pre-restore' === ( $item['policy'] ?? '' ), 'La copia previa no quedó bloqueada en el historial.' );
	pt_check( in_array( 'database_without_plugins', array_column( $result['parts']['coherence'] ?? array(), 'code' ), true ), 'La respuesta no lleva los avisos de coherencia.' );
} );

pt_case( 'P3 sólo un plugin concreto: lo demás idéntico', function() use ( $root, $snapshot, $snapshot_options, $backup, $backup_tree ) {
	list( $before, $db ) = pt_prepare( $root, $snapshot, $snapshot_options );
	$result = pt_restore( $backup, array( 'plugins' => 'demo' ) );
	pt_check( ! is_wp_error( $result ), 'Se rechazó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	pt_finish();
	$job = pt_last();
	pt_check( 'success' === $job['status'], 'No terminó: ' . json_encode( $job['error'] ) );
	pt_assert_tree( $before, $backup_tree, array( '/wp-content/plugins/demo/demo.php', '/wp-content/plugins/demo/inc/a.php' ), array( '/wp-content/plugins/demo/inc/extra.php' ) );
	pt_check( $db === pt_db() && 0 === pt_renames(), 'La base de datos cambió sin elegirla.' );
	pt_check( ! preg_grep( '/dgbkr_/', $GLOBALS['wpdb']->queries ), 'Se crearon tablas temporales sin elegir la base de datos.' );
	pt_check( array( 'plugins: demo' ) === $job['result']['restore']['parts']['restored'], 'El informe no nombra el plugin: ' . json_encode( $job['result']['restore']['parts'] ) );
} );

pt_case( 'P4 sólo los medios sin la base: se conservan los subidos después y se retira un .php', function() use ( $root, $snapshot, $snapshot_options, $backup, $backup_tree ) {
	list( $before, $db ) = pt_prepare( $root, $snapshot, $snapshot_options );
	$result = pt_restore( $backup, array( 'parts' => 'uploads' ) );
	pt_check( ! is_wp_error( $result ), 'Se rechazó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	pt_finish();
	pt_check( 'success' === pt_last()['status'], 'No terminó: ' . json_encode( pt_last()['error'] ) );
	pt_assert_tree( $before, $backup_tree, array( '/wp-content/uploads/2026/11/foto.jpg', '/wp-content/uploads/2026/11/nota.txt' ), array( '/wp-content/uploads/2026/11/inyectado.php' ) );
	pt_check( is_file( $root . '/wp-content/uploads/2026/12/nueva.jpg' ), 'Se retiró un medio subido después sin restaurar la base de datos.' );
	pt_check( $db === pt_db() && 0 === pt_renames(), 'La base de datos cambió sin elegirla.' );
} );

pt_case( 'P5 sólo los temas: vuelve el tema y sale uno instalado después', function() use ( $root, $snapshot, $snapshot_options, $backup, $backup_tree ) {
	list( $before, $db ) = pt_prepare( $root, $snapshot, $snapshot_options );
	$result = pt_restore( $backup, array( 'parts' => 'themes' ) );
	pt_check( ! is_wp_error( $result ), 'Se rechazó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	pt_finish();
	pt_check( 'success' === pt_last()['status'], 'No terminó: ' . json_encode( pt_last()['error'] ) );
	pt_assert_tree( $before, $backup_tree, array( '/wp-content/themes/tema/style.css' ), array( '/wp-content/themes/nuevo-tema/style.css' ) );
	pt_check( $db === pt_db() && 0 === pt_renames(), 'La base de datos cambió sin elegirla.' );
} );

pt_case( 'P6 base de datos y plugins enteros', function() use ( $root, $snapshot, $snapshot_options, $backup, $backup_tree, $backup_db ) {
	list( $before ) = pt_prepare( $root, $snapshot, $snapshot_options );
	$result = pt_restore( $backup, array( 'parts' => 'database,plugins' ) );
	pt_check( ! is_wp_error( $result ), 'Se rechazó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	pt_finish();
	pt_check( 'success' === pt_last()['status'], 'No terminó: ' . json_encode( pt_last()['error'] ) );
	pt_assert_tree( $before, $backup_tree, array( '/wp-content/plugins/demo/demo.php', '/wp-content/plugins/demo/inc/a.php', '/wp-content/plugins/otro/otro.php', '/wp-content/plugins/example.php', '/wp-content/plugins/borrado/borrado.php' ), array( '/wp-content/plugins/demo/inc/extra.php', '/wp-content/plugins/nuevo/nuevo.php' ) );
	pt_check( $backup_db === pt_db() && 1 === pt_renames(), 'La base de datos no volvió al respaldo.' );
} );

pt_case( 'P6b núcleo, mu-plugins y resto de wp-content: plugins, temas, medios y base, idénticos', function() use ( $root, $snapshot, $snapshot_options, $backup, $backup_tree ) {
	list( $before, $db ) = pt_prepare( $root, $snapshot, $snapshot_options );
	$result = pt_restore( $backup, array( 'parts' => 'core, mu-plugins, content' ) );
	pt_check( ! is_wp_error( $result ), 'Se rechazó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	pt_finish();
	$job = pt_last();
	pt_check( 'success' === $job['status'], 'No terminó: ' . json_encode( $job['error'] ) );
	pt_assert_tree( $before, $backup_tree, array( '/wp-load.php', '/wp-admin/index.php', '/wp-content/mu-plugins/mu.php', '/wp-content/languages/es_MX.mo', '/wp-content/object-cache.php' ), array() );
	pt_check( $db === pt_db() && 0 === pt_renames(), 'La base de datos cambió sin elegirla.' );
	pt_check( array( 'núcleo de WordPress', 'mu-plugins', 'resto de wp-content' ) === $job['result']['restore']['parts']['restored'], 'El informe no nombra las partes: ' . json_encode( $job['result']['restore']['parts'] ) );
} );

pt_case( 'P7 una falla a mitad del cambio vuelve a la copia previa sólo en lo elegido', function() use ( $root, $snapshot, $snapshot_options, $backup ) {
	list( $before, $db ) = pt_prepare( $root, $snapshot, $snapshot_options );
	// La segunda carpeta de código que se pone en su sitio falla (disco lleno): la primera ya cambió.
	$calls = 0;
	$GLOBALS['bk_on_mkdir'] = function( $path ) use ( &$calls ) {
		$job = Digitalisimo_Backups_Jobs::get( Digitalisimo_Backups_Jobs::current_id() );
		if ( $job && 'restore' === $job['kind'] && empty( $job['spec']['rollback'] ) && 'switch' === $job['phase'] && false !== strpos( (string) $path, '/wp-content/plugins' ) && ++$calls >= 2 ) throw new RuntimeException( 'disco lleno simulado al mover' );
	};
	// «borrado» está en el respaldo y ya no en el sitio: la copia previa no lo tiene, y la vuelta lo retira.
	$result = pt_restore( $backup, array( 'plugins' => 'demo,borrado' ) );
	// En esta petición puede terminar todo (la falla y la vuelta): entonces responde 500 con lo que se recuperó.
	pt_check( ! is_wp_error( $result ) || ( 'digitalisimo_backups_restore_failed' === $result->get_error_code() && false !== strpos( $result->get_error_message(), 'Se recuperó lo que se estaba restaurando (los plugins borrado y demo; lo demás no se tocó)' ) ), 'La respuesta no dice qué se recuperó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $result ) ) );
	pt_finish();
	unset( $GLOBALS['bk_on_mkdir'] );
	pt_check( $calls >= 2, 'La falla simulada no ocurrió (' . $calls . ').' );
	$failed = pt_last();
	$rollback = pt_last( true );
	pt_check( 'failed' === $failed['status'] && false !== strpos( (string) $failed['error']['message'], 'copia previa' ), 'La restauración no falló anunciando la vuelta: ' . json_encode( $failed['error'] ) );
	pt_check( $rollback && 'success' === $rollback['status'] && $failed['id'] === $rollback['spec']['rollback']['of'], 'La vuelta a la copia previa no terminó: ' . json_encode( $rollback['error'] ?? null ) );
	pt_check( $failed['spec']['parts'] === $rollback['spec']['parts'], 'La vuelta a la copia previa no repone las mismas partes.' );
	pt_assert_tree( $before, $before, array(), array() );
	pt_check( $db === pt_db() && 0 === pt_renames(), 'La base de datos cambió (ni la restauración ni la vuelta la eligieron).' );
	$status = Digitalisimo_Backups::service_status()['restore'];
	pt_check( 'rolled_back' === $status['state'], 'El estado no dice que volvió a la copia previa: ' . json_encode( $status ) );
	pt_check( false !== strpos( (string) $rollback['progress']['message'], 'lo restaurado' ), 'El mensaje de la vuelta no dice que sólo repuso lo restaurado: ' . (string) $rollback['progress']['message'] );
} );

pt_case( 'P8 lo que no trae el respaldo, una parte desconocida o nada elegido se rechazan sin tocar nada', function() use ( $root, $snapshot, $snapshot_options, $backup ) {
	list( $before, $db ) = pt_prepare( $root, $snapshot, $snapshot_options );
	$jobs = count( Digitalisimo_Backups_Jobs::all() );
	$missing = pt_restore( $backup, array( 'plugins' => 'no-existe' ) );
	pt_check( is_wp_error( $missing ) && 422 === ( $missing->get_error_data()['status'] ?? 0 ) && false !== strpos( $missing->get_error_message(), 'no-existe' ), 'Un plugin que el respaldo no tiene no se rechazó con 422: ' . ( is_wp_error( $missing ) ? $missing->get_error_message() : json_encode( $missing ) ) );
	foreach ( array( array( 'parts' => 'otra-cosa' ), array( 'parts' => array() ), array( 'plugins' => '../x' ) ) as $bad ) {
		$error = pt_restore( $backup, $bad );
		pt_check( is_wp_error( $error ) && 'digitalisimo_backups_parts' === $error->get_error_code() && 400 === ( $error->get_error_data()['status'] ?? 0 ), 'Una selección inválida no respondió 400: ' . json_encode( $bad ) );
	}
	pt_check( count( Digitalisimo_Backups_Jobs::all() ) === $jobs, 'Un rechazo creó un trabajo.' );
	pt_assert_tree( $before, $before, array(), array() );
	pt_check( $db === pt_db(), 'Un rechazo tocó la base de datos.' );
	// verify dice lo mismo antes de confirmar, con lo que trae el respaldo.
	$verify = Digitalisimo_Backups::service_verify( basename( $backup ), array( 'plugins' => 'demo,no-existe' ) );
	pt_check( ! is_wp_error( $verify ) && false === $verify['compatible'] && array( 'borrado', 'demo', 'example.php', 'otro' ) === $verify['parts']['available']['plugins'] && array( 'el plugin «no-existe»' ) === $verify['parts']['missing'], 'verify no dice qué trae el respaldo ni qué falta: ' . json_encode( is_wp_error( $verify ) ? $verify->get_error_message() : $verify['parts'] ) );
} );

pt_case( 'P9 la pantalla: «Qué restaurar» con «Todo» marcado y el handler', function() use ( $root, $snapshot, $snapshot_options, $backup, $backup_tree, $P ) {
	$html = bk_call( 'Digitalisimo_Backups', 'restore_parts_field', $backup, true, '' );
	pt_check( preg_match( '#^<details class="digitalisimo-backups-restore-parts" data-restore-parts><summary>Qué restaurar: <span data-restore-parts-label>todo</span></summary>#', $html ), 'El campo no empieza plegado con «Qué restaurar: todo»: ' . substr( $html, 0, 200 ) );
	pt_check( preg_match( '#name="restore_all" value="1" checked#', $html ) && 7 === preg_match_all( '#name="restore_parts\[\]" value="[a-z-]+" checked#', $html ), '«Todo» y las siete partes no salen marcados.' );
	foreach ( array( 'demo', 'otro', 'borrado', 'example.php' ) as $plugin ) pt_check( false !== strpos( $html, 'name="restore_plugins[]" value="' . $plugin . '"' ), 'Falta el plugin ' . $plugin . ' en la lista del respaldo.' );
	pt_check( false === strpos( $html, 'value="index.php"' ) && false !== strpos( $html, 'name="restore_themes[]" value="tema"' ), 'La lista de plugins o temas no es la del respaldo.' );
	pt_check( count( $P::RULES ) === preg_match_all( '#data-coherence="#', $html ), 'Faltan avisos de coherencia en el campo.' );
	$_POST = array( 'restore_parts_shown' => '1', 'restore_all' => '1', 'restore_parts' => array( 'database' ) );
	pt_check( array() === bk_call( 'Digitalisimo_Backups', 'restore_request_parts' ), 'Con «Todo» marcado no se restaura todo.' );
	$_POST = array( 'restore_parts_shown' => '1', 'restore_parts' => array( 'uploads' ), 'restore_plugins' => array( 'demo' ) );
	pt_check( array( 'parts' => array( 'uploads' ), 'plugins' => array( 'demo' ), 'themes' => array(), 'sites' => array() ) === bk_call( 'Digitalisimo_Backups', 'restore_request_parts' ), 'El handler no lee las partes marcadas.' );
	$_POST = array();
	// El handler real (admin-post): sólo el tema marcado.
	list( $before ) = pt_prepare( $root, $snapshot, $snapshot_options );
	list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups', 'restore_request' ), array( '_wpnonce' => 'nonce-' . Digitalisimo_Backups::ACTION_RESTORE, 'backup_file' => basename( $backup ), 'confirmation' => 'RESTAURAR', 'restore_parts_shown' => '1', 'restore_themes' => array( 'tema' ) ) );
	pt_check( $ok, 'El handler no restauró: ' . $message );
	pt_finish();
	pt_check( 'success' === pt_last()['status'], 'No terminó: ' . json_encode( pt_last()['error'] ) );
	pt_assert_tree( $before, $backup_tree, array( '/wp-content/themes/tema/style.css' ), array() );
	// Todo desmarcado y nada marcado: se dice, y nada cambia.
	list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups', 'restore_request' ), array( '_wpnonce' => 'nonce-' . Digitalisimo_Backups::ACTION_RESTORE, 'backup_file' => basename( $backup ), 'confirmation' => 'RESTAURAR', 'restore_parts_shown' => '1' ) );
	pt_check( ! $ok && false !== strpos( $message, 'al menos una parte' ), 'Sin nada marcado no se rechazó: ' . $message );
} );

pt_case( 'P10 regresión: sin selección, todo vuelve al respaldo', function() use ( $root, $snapshot, $snapshot_options, $backup, $backup_tree, $backup_db ) {
	pt_prepare( $root, $snapshot, $snapshot_options );
	$result = pt_restore( $backup, array() );
	pt_check( ! is_wp_error( $result ), 'Se rechazó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	pt_finish();
	$job = pt_last();
	pt_check( 'success' === $job['status'] && null === ( $job['spec']['parts'] ?? null ) && true === $job['result']['restore']['parts']['all'], 'La restauración completa no terminó como completa.' );
	$after = bk_tree();
	foreach ( $backup_tree as $path => $hash ) if ( 'dir' !== $hash && '/wp-config.php' !== $path ) pt_check( ( $after[ $path ] ?? null ) === $hash, 'No volvió al respaldo: ' . $path );
	foreach ( array( '/wp-content/plugins/nuevo/nuevo.php', '/wp-content/uploads/2026/11/inyectado.php', '/wp-content/themes/nuevo-tema/style.css', '/wp-content/uploads/2026/12/nueva.jpg' ) as $gone ) pt_check( ! isset( $after[ $gone ] ), 'Sobrevivió lo que el respaldo no tenía: ' . $gone );
	pt_check( $backup_db === pt_db(), 'La base de datos no volvió al respaldo.' );
	pt_check( false === strpos( (string) $job['result']['restore']['notice'], 'por partes' ), 'El informe de la completa habla de partes.' );
} );

bk_remove_tree( $snapshot );
bk_cleanup();
echo $failures ? "FALLA: {$failures} caso(s) de restaurar por partes.\n" : "DIGITALÍSIMO Backups: restaurar por partes (base de datos, núcleo, plugins, temas, medios, mu-plugins, resto y concretos) con la misma seguridad que la completa.\n";
exit( $failures ? 1 : 0 );
