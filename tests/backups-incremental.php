<?php
/**
 * Incrementales confiables (docs/BACKUPS.md, «Incrementales»), con el motor de trabajos y la restauración por pasos
 * reales sobre la instalación simulada de tests/lib/backups-env.php:
 *
 *   I1  cadena: un completo (base) y dos incrementales con archivos nuevos, cambiados (también con el mismo tamaño),
 *       borrados, renombrados, tocados sin cambiar y resucitados, y filas de la base que cambian; cada incremental sólo
 *       guarda lo nuevo o cambiado, su inventario completo (con el eslabón de cada archivo y los borrados) y la base de
 *       datos entera, y nombra a sus antecesores por el SHA-256 de su manifiesto; índice lateral de cada punto;
 *   I2  cada punto se reconstruye entero y es idéntico (por huellas) al sitio de su momento, también en los borrados;
 *   I3  restaurar cada punto (y el completo) deja el sitio exactamente como en ese punto: archivos y base de datos;
 *   I4  cadena rota: eslabón que falta, alterado (archivo y manifiesto), padre equivocado y fila del historial que no
 *       corresponde se rechazan antes de tocar el sitio, con un mensaje que nombra el eslabón; un eslabón suelto no se
 *       restaura como completo; el incremental heredado falla cerrado;
 *   I5  retención: cuenta puntos, nunca borra una base ni un eslabón del que depende un punto conservado, consolida
 *       (un completo cada N o cada D días) y no deja índices huérfanos; borrar a mano una base con dependientes se niega;
 *   I6  cifrado y firma: la cadena cifrada (.dgbk, índice lateral cifrado) se reconstruye firmada y se restaura; cambiar
 *       el cifrado empieza una cadena nueva;
 *   I7  reanudación por eslabón: un incremental cuyo proceso muere a mitad del índice del padre y a mitad de la escritura
 *       termina idéntico; un padre cuyo índice desaparece hace volver a «preflight» y sale un completo;
 *   I8  superficies: historial (tipo, base, espacio y ahorro), automatizaciones (modo) y respaldo manual incremental.
 *
 * Debe fallar con el código anterior (sin Digitalisimo_Backups_Chain: la primera automatización incremental no existe).
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/backups-incremental/helpers.php';
$root = rtrim( ABSPATH, '/' );
$B = 'Digitalisimo_Backups';
$C = 'Digitalisimo_Backups_Chain';
$J = 'Digitalisimo_Backups_Jobs';
$failures = 0;

// El sitio de partida.
foreach ( array(
	'index.php' => "<?php // portada\n",
	'wp-content/plugins/demo/demo.php' => "<?php /* Plugin Name: Demo */\n",
	'wp-content/plugins/demo/inc/a.php' => "<?php // aaaa\n",
	'wp-content/plugins/demo/inc/b.php' => "<?php // b original\n",
	'wp-content/themes/tema/style.css' => "/* Theme Name: Tema */\n",
	'wp-content/themes/tema/functions.php' => "<?php // tema\n",
	'wp-content/uploads/2026/11/foto.jpg' => str_repeat( "\xff\xd8\xff", 40000 ),
	'wp-content/uploads/2026/11/viejo-nombre.txt' => "se renombra\n",
	'wp-content/uploads/2026/11/tocado.txt' => "sólo cambia su fecha\n",
	'wp-content/uploads/2026/11/se-borra.txt' => "se borra en el primer incremental\n",
) as $name => $content ) inc_put( $name, $content );
$GLOBALS['bk_options']['blogname'] = 'Punto 0';
$GLOBALS['wpdb']->posts[] = array( 'ID' => '7', 'post_content' => 'entrada del punto 0' );
$points = array();
$trees = array();
$dbs = array();

inc_case( 'I1 base: la primera automatización incremental es un completo que empieza la cadena, con su índice lateral', function() use ( &$points, &$trees, &$dbs, $C ) {
	$job = inc_run( 'auto-inc' );
	$plan = $C::job_plan( $job );
	inc_check( $plan && 'base' === $plan['mode'] && 'first' === $plan['reason'], 'El primer punto no es la base de una cadena: ' . json_encode( $plan ) );
	$row = inc_row( inc_path( $job ) );
	$chain = $C::row_chain( $row );
	inc_check( $chain && 0 === $chain['sequence'] && 'full' === $row['backup_type'] && $chain['id'] === $chain['backup_id'] && array() === $chain['links'], 'La fila de la base no lleva su cadena: ' . json_encode( $row['chain'] ?? null ) );
	$zip = inc_zip( inc_path( $job ) );
	inc_check( 'full' === $zip['manifest']['type'] && ! isset( $zip['manifest']['chain'] ) && hash( 'sha256', json_encode( 1 ) ) !== $chain['manifest_sha256'], 'La base no es un completo de siempre.' );
	$index = $C::index_path( bk_storage(), $row );
	inc_check( '' !== $index && 0 === substr_count( $index, '.dgbk' ), 'Falta el índice lateral de la base.' );
	$lines = array_values( array_filter( explode( "\n", file_get_contents( $index ) ) ) );
	inc_check( count( $lines ) - 1 === $chain['files'] && $chain['files'] === count( inc_files() ) + 1, 'El índice lateral de la base no tiene todos sus archivos (' . count( $lines ) . ').' );
	$points[0] = inc_path( $job );
	$trees[0] = inc_files();
	$dbs[0] = inc_db();
} );

inc_case( 'I1 incremental 1: sólo lo nuevo o cambiado, inventario completo, borrados, renombrados y base de datos entera', function() use ( &$points, &$trees, &$dbs, $C, $root ) {
	inc_put( 'wp-content/plugins/demo/nuevo.php', "<?php // nuevo en el punto 1\n" );
	inc_put( 'wp-content/plugins/demo/inc/a.php', "<?php // AAAA\n" ); // Mismo tamaño, otro contenido.
	inc_put( 'wp-content/plugins/demo/inc/b.php', "<?php // b cambiado y más largo\n" );
	unlink( $root . '/wp-content/themes/tema/style.css' );
	unlink( $root . '/wp-content/uploads/2026/11/se-borra.txt' );
	rename( $root . '/wp-content/uploads/2026/11/viejo-nombre.txt', $root . '/wp-content/uploads/2026/11/nuevo-nombre.txt' );
	touch( $root . '/wp-content/uploads/2026/11/tocado.txt', time() + 120 ); // Otra fecha, el mismo contenido.
	$GLOBALS['bk_options']['blogname'] = 'Punto 1';
	$GLOBALS['wpdb']->posts[] = array( 'ID' => '8', 'post_content' => 'entrada del punto 1' );
	$job = inc_run( 'auto-inc' );
	$plan = $C::job_plan( $job );
	inc_check( $plan && 'incremental' === $plan['mode'] && 1 === $plan['sequence'], 'El segundo punto no es un incremental: ' . json_encode( $plan ) );
	$row = inc_row( inc_path( $job ) );
	$chain = $C::row_chain( $row );
	$base = $C::row_chain( inc_row( $points[0] ) );
	inc_check( 'incremental' === $row['backup_type'] && 1 === $chain['sequence'] && $base['id'] === $chain['id'] && array( array( 'sequence' => 0, 'file' => basename( $points[0] ), 'backup_id' => $base['backup_id'], 'manifest_sha256' => $base['manifest_sha256'] ) ) === $chain['links'], 'La fila del incremental no nombra su base: ' . json_encode( $chain ) );
	$zip = inc_zip( inc_path( $job ) );
	$stored = array_keys( $zip['entries'] );
	sort( $stored );
	$expected = array( 'database.sql', 'inventory.jsonl', 'manifest.json', 'wordpress/wp-content/plugins/demo/inc/a.php', 'wordpress/wp-content/plugins/demo/inc/b.php', 'wordpress/wp-content/plugins/demo/nuevo.php', 'wordpress/wp-content/uploads/2026/11/nuevo-nombre.txt' );
	inc_check( $expected === $stored, 'El incremental no lleva exactamente lo nuevo o cambiado: ' . implode( ', ', $stored ) );
	$m = $zip['manifest'];
	inc_check( 'incremental' === $m['type'] && 1 === $m['chain']['sequence'] && $chain['links'] === $m['chain']['links'] && 'inventory.jsonl' === $m['chain']['inventory']['entry'], 'El manifiesto del incremental no lleva su cadena.' );
	inc_check( array( 'added' => 2, 'changed' => 2, 'unchanged' => count( inc_files() ) - 3, 'deleted' => 3 ) === array_intersect_key( $m['chain']['stats'], array_flip( array( 'added', 'changed', 'unchanged', 'deleted' ) ) ), 'Recuentos del incremental: ' . json_encode( $m['chain']['stats'] ) );
	$present = array();
	$deleted = array();
	foreach ( $zip['inventory'] as $line ) {
		if ( ! empty( $line['x'] ) ) { $deleted[] = $line['n']; continue; }
		$present[ $line['n'] ] = $line;
	}
	sort( $deleted );
	inc_check( array( 'wordpress/wp-content/themes/tema/style.css', 'wordpress/wp-content/uploads/2026/11/se-borra.txt', 'wordpress/wp-content/uploads/2026/11/viejo-nombre.txt' ) === $deleted, 'Los borrados del incremental: ' . implode( ', ', $deleted ) );
	$files = inc_files();
	$files['wordpress/wp-config.php'] = hash_file( 'sha256', $root . '/wp-config.php' );
	ksort( $files );
	$hashes = array_map( function( $line ) { return $line['h']; }, $present );
	ksort( $hashes );
	inc_check( $files === $hashes, 'El inventario del punto no es el sitio de su momento.' );
	inc_check( 0 === $present['wordpress/wp-content/uploads/2026/11/tocado.txt']['l'] && 0 === $present['wordpress/wp-load.php']['l'] && 1 === $present['wordpress/wp-content/plugins/demo/inc/a.php']['l'], 'El eslabón de cada archivo del inventario no es el que guarda sus bytes.' );
	inc_check( false !== strpos( $zip['sql'], 'Punto 1' ) && false !== strpos( $zip['sql'], 'entrada del punto 0' ) && false !== strpos( $zip['sql'], 'entrada del punto 1' ), 'El incremental no lleva la base de datos entera de su momento.' );
	$points[1] = inc_path( $job );
	$trees[1] = inc_files();
	$dbs[1] = inc_db();
} );

inc_case( 'I1 incremental 2: hereda de la base y del incremental 1, con un archivo resucitado', function() use ( &$points, &$trees, &$dbs, $C, $root ) {
	inc_put( 'wp-content/plugins/demo/inc/b.php', "<?php // b cambiado otra vez en el punto 2\n" );
	unlink( $root . '/wp-content/plugins/demo/nuevo.php' );
	inc_put( 'wp-content/themes/tema/style.css', "/* Theme Name: Tema */\n" ); // Vuelve con el contenido de la base.
	$GLOBALS['bk_options']['blogname'] = 'Punto 2';
	array_pop( $GLOBALS['wpdb']->posts );
	$job = inc_run( 'auto-inc' );
	$row = inc_row( inc_path( $job ) );
	$chain = $C::row_chain( $row );
	inc_check( 2 === $chain['sequence'] && 2 === count( $chain['links'] ) && basename( $points[1] ) === $chain['links'][1]['file'], 'El tercer punto no continúa la cadena: ' . json_encode( $chain ) );
	$zip = inc_zip( inc_path( $job ) );
	$lines = array();
	foreach ( $zip['inventory'] as $line ) $lines[ $line['n'] ] = $line;
	inc_check( 1 === $lines['wordpress/wp-content/plugins/demo/inc/a.php']['l'] && 0 === $lines['wordpress/wp-load.php']['l'] && 2 === $lines['wordpress/wp-content/plugins/demo/inc/b.php']['l'] && 2 === $lines['wordpress/wp-content/themes/tema/style.css']['l'] && ! empty( $lines['wordpress/wp-content/plugins/demo/nuevo.php']['x'] ), 'El inventario del incremental 2 no hereda de cada eslabón: ' . json_encode( array_slice( $lines, 0, 4 ) ) );
	$points[2] = inc_path( $job );
	$trees[2] = inc_files();
	$dbs[2] = inc_db();
} );

inc_case( 'I2 cada punto se reconstruye entero, idéntico al sitio de su momento (también los borrados)', function() use ( &$points, &$trees, $root ) {
	foreach ( array( 1, 2 ) as $n ) {
		$virtual = inc_materialize( $points[ $n ] );
		$zip = inc_zip( $virtual['path'] );
		$files = $zip['entries'];
		unset( $files['database.sql'], $files['manifest.json'], $files['wordpress/wp-config.php'] );
		ksort( $files );
		inc_check( $trees[ $n ] === $files, 'El punto ' . $n . ' reconstruido no es el sitio de su momento: sobran ' . implode( ', ', array_keys( array_diff_key( $files, $trees[ $n ] ) ) ) . '; faltan ' . implode( ', ', array_keys( array_diff_key( $trees[ $n ], $files ) ) ) );
		inc_check( 'full' === $zip['manifest']['type'] && ! isset( $zip['manifest']['chain'] ) && $n === $zip['manifest']['materialized_from']['sequence'] && 'provenance' === $virtual['trust'] && $n + 1 === $virtual['links'], 'El punto ' . $n . ' reconstruido no es un respaldo completo v3.' );
		$open = Digitalisimo_Backups_Security::open( $virtual['path'], $virtual['item'] );
		$data = Digitalisimo_Backups_Archive_Inspector::manifest( $open['zip'] );
		inc_check( 'provenance' === $open['trust'] && count( $trees[ $n ] ) + 1 === $data['file_count'], 'El punto reconstruido no se abre como un respaldo registrado.' );
		Digitalisimo_Backups_Security::close( $open );
		Digitalisimo_Backups_Security::remove_tree( $virtual['staging'] );
	}
} );

inc_case( 'I3 restaurar cada punto deja el sitio exactamente como en ese punto (archivos y base de datos)', function() use ( &$points, &$trees, &$dbs, $root ) {
	foreach ( array( 1, 0, 2, 1 ) as $n ) {
		inc_put( 'wp-content/plugins/demo/inyectado.php', "<?php // después de todos los puntos\n" );
		$GLOBALS['bk_options']['blogname'] = 'Cambiado';
		$result = inc_restore( $points[ $n ] );
		inc_check( ! is_wp_error( $result ), 'La restauración del punto ' . $n . ' se rechazó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
		inc_check( inc_last_restore_ok(), 'La restauración del punto ' . $n . ' no terminó bien.' );
		$files = inc_files();
		inc_check( $trees[ $n ] === $files, 'Tras restaurar el punto ' . $n . ' el sitio no es el de su momento: sobran ' . implode( ', ', array_keys( array_diff_key( $files, $trees[ $n ] ) ) ) . '; faltan ' . implode( ', ', array_keys( array_diff_key( $trees[ $n ], $files ) ) ) . '; distintos ' . implode( ', ', array_keys( array_diff_assoc( array_intersect_key( $files, $trees[ $n ] ), $trees[ $n ] ) ) ) );
		inc_check( $dbs[ $n ] === inc_db(), 'Tras restaurar el punto ' . $n . ' la base de datos no es la de su momento.' );
		if ( $n > 0 ) inc_check( false !== strpos( (string) ( $result['notice'] ?? '' ), 'reconstruido entero' ), 'El informe no dice que el punto se reconstruyó: ' . json_encode( $result['notice'] ?? '' ) );
	}
} );

inc_case( 'I4 cadena rota: falta, alterada, padre equivocado o fila que no corresponde → rechazo sin tocar nada', function() use ( &$points, $C, $B ) {
	$history = $GLOBALS['bk_options'][ $B::HISTORY ];
	$expect = function( $label, $code, $needle ) use ( &$points ) {
		$before = bk_tree();
		$db = inc_db();
		$result = inc_restore( $points[2] );
		inc_check( is_wp_error( $result ) && 'digitalisimo_backups_' . $code === $result->get_error_code() && false !== strpos( $result->get_error_message(), $needle ) && false !== strpos( $result->get_error_message(), 'No se cambió nada del sitio' ), $label . ': no se rechazó como debía: ' . ( is_wp_error( $result ) ? $result->get_error_code() . ' ' . $result->get_error_message() : json_encode( $result ) ) );
		inc_check( bk_tree() === $before && inc_db() === $db, $label . ': el rechazo tocó el sitio.' );
		$verify = Digitalisimo_Backups::service_verify( basename( $points[2] ) );
		inc_check( is_wp_error( $verify ), $label . ': la verificación lo dio por bueno.' );
	};
	// Falta el incremental 1 en el almacén.
	rename( $points[1], $points[1] . '.aparte' );
	$expect( 'eslabón que falta', 'chain_incomplete', basename( $points[1] ) );
	rename( $points[1] . '.aparte', $points[1] );
	// Alterado: un byte del incremental 1 (su SHA-256 ya no es el registrado).
	$original = file_get_contents( $points[1] );
	file_put_contents( $points[1], substr( $original, 0, 200 ) . chr( ord( $original[200] ) ^ 1 ) . substr( $original, 201 ) );
	$expect( 'eslabón alterado', 'chain_broken', 'eslabón 2 de 3' );
	// Alterado de verdad (otro manifiesto) y con la fila del historial puesta al día: el punto 2 nombra otro manifiesto.
	$copy = sys_get_temp_dir() . '/inc-otro-' . bin2hex( random_bytes( 3 ) ) . '.zip';
	file_put_contents( $copy, $original );
	$zip = new ZipArchive();
	$zip->open( $copy );
	$zip->addFromString( 'manifest.json', str_replace( '"plugin_version"', '"plugin_version" ', (string) $zip->getFromName( 'manifest.json' ) ) );
	$zip->close();
	rename( $copy, $points[1] );
	foreach ( $GLOBALS['bk_options'][ $B::HISTORY ] as &$row ) if ( basename( $points[1] ) === $row['file'] ) $row['sha256'] = hash_file( 'sha256', $points[1] );
	unset( $row );
	$expect( 'manifiesto alterado', 'chain_broken', 'su manifiesto no es el que nombra el punto' );
	file_put_contents( $points[1], $original );
	$GLOBALS['bk_options'][ $B::HISTORY ] = $history;
	// Padre equivocado: otro incremental (de otra cadena) con el nombre y el registro del incremental 1.
	$other = inc_run( 'otra-cadena' );
	inc_put( 'wp-content/uploads/2026/11/otra.txt', 'otra cadena' );
	$other = inc_run( 'otra-cadena' );
	copy( inc_path( $other ), $points[1] );
	foreach ( $GLOBALS['bk_options'][ $B::HISTORY ] as &$row ) if ( basename( $points[1] ) === $row['file'] ) $row['sha256'] = hash_file( 'sha256', $points[1] );
	unset( $row );
	$expect( 'padre equivocado', 'chain_broken', 'eslabón 2 de 3' );
	file_put_contents( $points[1], $original );
	$GLOBALS['bk_options'][ $B::HISTORY ] = $history;
	// La fila del punto nombra otro manifiesto de su base: el eslabón no es el del historial.
	foreach ( $GLOBALS['bk_options'][ $B::HISTORY ] as &$row ) if ( basename( $points[2] ) === $row['file'] ) $row['chain']['links'][0]['manifest_sha256'] = str_repeat( 'a', 64 );
	unset( $row );
	try {
		$expect( 'fila que no corresponde', 'chain_broken', 'no está en el historial' );
	} finally {
		$GLOBALS['bk_options'][ $B::HISTORY ] = $history;
	}
	// Un eslabón suelto nunca se restaura como completo: la restauración de siempre lo rechaza por su tipo.
	$zip = new ZipArchive();
	$zip->open( $points[1] );
	$message = bk_expect_failure( 'eslabón suelto', function() use ( $zip ) { bk_call( 'Digitalisimo_Backups', 'restore_precheck', $zip ); }, 'cadena' );
	$zip->close();
	// El incremental heredado (anterior a 1.0.38) falla cerrado.
	bk_expect_failure( 'incremental heredado', function() { bk_call( 'Digitalisimo_Backups', 'create_incremental_site', 1, array( 'id' => 'x' ) ); }, 'se retiró' );
	// Y la cadena íntegra vuelve a restaurarse.
	$result = inc_restore( $points[2] );
	inc_check( ! is_wp_error( $result ) && inc_last_restore_ok(), 'La cadena íntegra no se restauró tras los rechazos: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
} );

inc_case( 'I14 recuperación ante desastre: sin historial ni índices, el punto se verifica y se restaura por sus manifiestos («sin firma»)', function() use ( $B, $root ) {
	inc_put( 'wp-content/uploads/2026/12/desastre.txt', 'desastre 0' );
	inc_run( 'desastre' );
	inc_put( 'wp-content/uploads/2026/12/desastre.txt', 'desastre 1' );
	inc_put( 'wp-content/plugins/demo/desastre.php', "<?php // punto 1\n" );
	$i1 = inc_path( inc_run( 'desastre' ) );
	unlink( $root . '/wp-content/plugins/demo/desastre.php' );
	$GLOBALS['bk_options']['blogname'] = 'Antes del desastre';
	$i2 = inc_path( inc_run( 'desastre' ) );
	$tree = inc_files();
	$db = inc_db();
	// El desastre: la base de datos (historial) y los índices laterales se pierden; los archivos siguen (o vuelven de un destino).
	$history = $GLOBALS['bk_options'][ $B::HISTORY ];
	$GLOBALS['bk_options'][ $B::HISTORY ] = array();
	bk_remove_tree( rtrim( bk_storage(), '/' ) . '/chains' );
	@unlink( rtrim( bk_storage(), '/' ) . '/.installation-id' );
	inc_put( 'wp-content/plugins/demo/otro-sitio.php', "<?php // el sitio nuevo\n" );
	$GLOBALS['bk_options']['blogname'] = 'Sitio nuevo';
	$verify = $B::service_verify( basename( $i2 ) );
	inc_check( ! is_wp_error( $verify ) && 3 === $verify['chain']['links'] && 'untrusted' === $verify['trust'] && 'RESTAURAR SIN FIRMA' === $verify['confirmation'], 'La verificación sin historial no reconoció la cadena: ' . ( is_wp_error( $verify ) ? $verify->get_error_message() : json_encode( $verify['chain'] ?? null ) ) );
	$before = bk_tree();
	$result = inc_restore( $i2 );
	inc_check( is_wp_error( $result ) && 'digitalisimo_backups_confirm_unsigned' === $result->get_error_code() && bk_tree() === $before, 'Sin historial ni firma, «RESTAURAR» no pidió «RESTAURAR SIN FIRMA»: ' . ( is_wp_error( $result ) ? $result->get_error_code() . ' ' . $result->get_error_message() : 'aceptado' ) );
	rename( $i1, $i1 . '.aparte' );
	// Subido o traído suelto: se comprueba entero como un respaldo y dice qué archivos de su cadena faltan.
	$inspected = bk_call( 'Digitalisimo_Backups', 'upload_inspect', $i2, '' );
	inc_check( ! is_wp_error( $inspected ) && ! empty( $inspected['verified'] ) && array( basename( $i1 ) ) === ( $inspected['chain']['missing'] ?? null ) && false === $inspected['compatible'], 'Un incremental subido no se comprobó o no dijo qué falta de su cadena: ' . ( is_wp_error( $inspected ) ? $inspected->get_error_message() : json_encode( $inspected['chain'] ?? null ) ) );
	$result = inc_restore( $i2, 'RESTAURAR SIN FIRMA' );
	inc_check( is_wp_error( $result ) && 'digitalisimo_backups_chain_incomplete' === $result->get_error_code() && false !== strpos( $result->get_error_message(), basename( $i1 ) ) && false !== strpos( $result->get_error_message(), 'Súbelos o tráelos' ) && bk_tree() === $before, 'Sin un eslabón no dijo cuál subir o traer: ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'aceptado' ) );
	rename( $i1 . '.aparte', $i1 );
	$result = inc_restore( $i2, 'RESTAURAR SIN FIRMA' );
	inc_check( ! is_wp_error( $result ) && inc_last_restore_ok(), 'El punto no se restauró sin historial: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $result ) ) );
	inc_check( $tree === inc_files() && $db === inc_db(), 'Sin historial, el punto restaurado no es el sitio de su momento.' );
	$GLOBALS['bk_options'][ $B::HISTORY ] = array_merge( (array) $GLOBALS['bk_options'][ $B::HISTORY ], (array) $history );
} );

inc_case( 'I15 la reconstrucción de un punto va por tramos reanudables en su trabajo y cuenta el SQL sin comprimir en el espacio', function() use ( $B, $C, $root ) {
	// Una base de datos que comprime mucho: el espacio debe contar su tamaño sin comprimir, no el del ZIP del punto.
	$GLOBALS['wpdb']->posts[] = array( 'ID' => '90', 'post_content' => str_repeat( 'texto que se comprime muy bien. ', 20000 ) );
	inc_put( 'wp-content/uploads/2026/12/tramos.txt', 'tramos 0' );
	inc_run( 'tramos' );
	for ( $i = 0; $i < 8; $i++ ) inc_put( 'wp-content/uploads/2026/12/tramo-' . $i . '.txt', str_repeat( 'tramo ' . $i, 300 ) );
	$point = inc_path( inc_run( 'tramos' ) );
	$tree = inc_files();
	$row = inc_row( $point );
	$GLOBALS['bk_filters']['digitalisimo_backups_free_space'] = function( $free ) use ( $row ) { return (float) ( (int) $row['chain']['bytes'] + Digitalisimo_Backups_Chain_Restore::MARGIN + 200000 ); };
	$result = inc_restore( $point );
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_free_space'] );
	inc_check( is_wp_error( $result ) && false !== strpos( $result->get_error_message(), 'No hay espacio para reconstruir' ), 'El espacio no contó la base de datos sin comprimir: ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'aceptado' ) );
	inc_put( 'wp-content/uploads/2026/12/tramos.txt', 'después' );
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = function() { return 1; };
	// La respuesta sale enseguida (como una petición web cortada): el resto lo hacen los ticks.
	$GLOBALS['bk_filters']['digitalisimo_backups_restore_respond_after'] = function() { return 0; };
	$result = Digitalisimo_Backups::service_restore( basename( $point ), array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false, 'via' => 'test' ) );
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_restore_respond_after'] );
	inc_check( ! is_wp_error( $result ) && ! empty( $result['restoring'] ) && 'chain' === ( $result['job']['kind'] ?? '' ), 'La restauración del punto no empezó por su trabajo de reconstrucción: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( array_intersect_key( $result, array_flip( array( 'restoring', 'job' ) ) ) ) ) );
	$chain = $result['job']['id'];
	$slices = 0;
	$killed = false;
	for ( $i = 0; $i < 4000; $i++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $chain );
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
		if ( 'build' === $job['phase'] && ! empty( $job['checkpoint']['build']['resume']['files'] ) ) {
			$slices++;
			if ( ! $killed && $job['checkpoint']['build']['resume']['files'] >= 3 ) { $killed = true; inc_kill( $chain ); }
		}
		Digitalisimo_Backups_Jobs::tick( 0 );
	}
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	$job = Digitalisimo_Backups_Jobs::get( $chain );
	inc_check( 'success' === $job['status'] && $killed && $slices >= 5 && '' !== (string) ( $job['result']['restore_job'] ?? '' ), 'La reconstrucción no fue por tramos reanudables: ' . json_encode( array( $job['status'], $job['error'], $slices, $killed ) ) );
	for ( $i = 0; $i < 4000 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	inc_check( inc_last_restore_ok() && $tree === inc_files(), 'Tras la reconstrucción por tramos, el punto restaurado no es el de su momento.' );
	array_pop( $GLOBALS['wpdb']->posts );
} );

inc_case( 'I16 un incremental heredado (v2, anterior a 1.0.38) conserva su mensaje de paquete parcial', function() {
	$path = rtrim( bk_storage(), '/' ) . '/digitalisimo-site-1-example-test-incremental-20200101-000000.zip';
	$zip = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$zip->addFromString( 'database.sql', "SET NAMES utf8mb4;\n" );
	$zip->addFromString( 'manifest.json', json_encode( array( 'format' => 'digitalisimo-backup/v2', 'scope' => 'site', 'type' => 'incremental', 'policy' => 'vieja' ) ) );
	$zip->close();
	$zip = new ZipArchive();
	$zip->open( $path );
	$message = bk_expect_failure( 'incremental v2', function() use ( $zip ) { bk_call( 'Digitalisimo_Backups', 'inspect_package', $zip, true, false ); }, 'paquete parcial' );
	$zip->close();
	unlink( $path );
	inc_check( false === strpos( $message, 'eslabón' ), 'El incremental heredado recibe el texto de las cadenas: ' . $message );
} );

inc_case( 'I5 retención consciente de cadenas: cuenta puntos, nunca rompe una cadena, consolida y no deja índices huérfanos', function() use ( $C, $B ) {
	$made = array();
	for ( $i = 0; $i < 7; $i++ ) {
		inc_put( 'wp-content/uploads/2026/11/retencion.txt', 'vuelta ' . $i );
		$job = inc_run( 'ret', 2, 7, 3 );
		$made[] = array( basename( inc_path( $job ) ), $C::job_plan( $job )['sequence'] );
		$history = (array) $GLOBALS['bk_options'][ $B::HISTORY ];
		foreach ( $history as $row ) {
			if ( 'ret' !== ( $row['policy'] ?? '' ) ) continue;
			foreach ( $C::files( $row ) as $file ) inc_check( is_file( rtrim( bk_storage(), '/' ) . '/' . $file ) && inc_row( $file ), 'La retención rompió la cadena de ' . $row['file'] . ': falta ' . $file );
		}
	}
	inc_check( array( 0, 1, 2, 0, 1, 2, 0 ) === array_column( $made, 1 ), 'La consolidación no hizo un completo cada 2 incrementales: ' . json_encode( array_column( $made, 1 ) ) );
	$kept = array_values( array_map( function( $row ) { return $row['file']; }, array_filter( (array) $GLOBALS['bk_options'][ $B::HISTORY ], function( $row ) { return 'ret' === ( $row['policy'] ?? '' ); } ) ) );
	sort( $kept );
	$expected = array( $made[3][0], $made[4][0], $made[5][0], $made[6][0] );
	sort( $expected );
	inc_check( $expected === $kept, 'Con 3 puntos que conservar quedan los 3 últimos y la base de la que dependen; la cadena vieja, entera fuera: ' . json_encode( $kept ) );
	foreach ( array( 0, 1, 2 ) as $gone ) inc_check( ! is_file( rtrim( bk_storage(), '/' ) . '/' . $made[ $gone ][0] ), 'Quedó un archivo de la cadena retirada.' );
	$indexes = glob( rtrim( bk_storage(), '/' ) . '/chains/*' );
	$named = array();
	foreach ( (array) $GLOBALS['bk_options'][ $B::HISTORY ] as $row ) { $chain = $C::row_chain( $row ); if ( $chain ) $named[] = $chain['index']['file']; }
	foreach ( $indexes as $index ) inc_check( in_array( basename( $index ), $named, true ), 'Quedó un índice lateral huérfano: ' . basename( $index ) );
	// Por días: una base de hace 8 días (con 7 de tope) hace que el siguiente punto sea un completo.
	foreach ( $GLOBALS['bk_options'][ $B::HISTORY ] as &$row ) if ( $made[6][0] === $row['file'] ) $row['chain']['base_at'] = time() - 8 * 86400;
	unset( $row );
	$job = inc_run( 'ret', 2, 7, 3 );
	inc_check( 'base' === $C::job_plan( $job )['mode'] && 'full_days' === $C::job_plan( $job )['reason'], 'Una base de más días que el tope no consolidó.' );
	// Borrar a mano una base con dependientes se niega.
	$job = inc_run( 'ret', 2, 7, 3 );
	$base = $C::row_chain( inc_row( inc_path( $job ) ) )['links'][0]['file'];
	list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups', 'delete_backup' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_DELETE, 'file' => $base ) );
	inc_check( ! $ok && false !== strpos( $message, 'dependen de este respaldo' ) && is_file( rtrim( bk_storage(), '/' ) . '/' . $base ), 'Se borró a mano una base con dependientes: ' . $message );
} );

inc_case( 'I6 cifrado y firma: cadena .dgbk con índice cifrado, reconstruida firmada y restaurada; cambiar el cifrado empieza otra', function() use ( $C, $B, $root ) {
	$K = 'Digitalisimo_Backups_Keys';
	$S = 'Digitalisimo_Backups_Security';
	$K::set_passphrase( 'frase de la cadena cifrada bastante larga' );
	$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => true, 'require_signed' => false );
	$first = inc_run( 'cifrada' );
	inc_put( 'wp-content/plugins/demo/cifrado.php', "<?php // sólo en el punto cifrado\n" );
	$GLOBALS['bk_options']['blogname'] = 'Punto cifrado';
	$job = inc_run( 'cifrada' );
	$tree = inc_files();
	$db = inc_db();
	$row = inc_row( inc_path( $job ) );
	inc_check( '.dgbk' === substr( inc_path( $job ), -5 ) && 1 === $C::row_chain( $row )['sequence'] && ! empty( $row['chain']['index']['encrypted'] ), 'La cadena cifrada no salió cifrada: ' . json_encode( $row['chain'] ?? null ) );
	foreach ( array( $first, $job ) as $point ) {
		$index = $C::index_path( bk_storage(), inc_row( inc_path( $point ) ) );
		inc_check( '.dgbk' === substr( $index, -5 ) && false === strpos( file_get_contents( $index ), 'wp-content' ), 'El índice lateral de una cadena cifrada deja nombres en claro.' );
	}
	$virtual = inc_materialize( inc_path( $job ) );
	inc_check( 'signed' === $virtual['trust'], 'La cadena cifrada no se reconstruyó firmada: ' . $virtual['trust'] );
	$open = $S::open( $virtual['path'], $virtual['item'], $virtual['secret'] );
	inc_check( 'signed' === $open['trust'], 'El punto reconstruido no se abre firmado.' );
	$S::close( $open );
	$S::remove_tree( $virtual['staging'] );
	inc_put( 'wp-content/plugins/demo/cifrado.php', "<?php // cambiado después\n" );
	$GLOBALS['bk_options']['blogname'] = 'Después';
	$result = inc_restore( inc_path( $job ) );
	inc_check( ! is_wp_error( $result ) && inc_last_restore_ok() && 'signed' === ( $result['trust'] ?? '' ), 'El punto cifrado no se restauró: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $result['trust'] ?? '' ) ) );
	inc_check( $tree === inc_files() && $db === inc_db(), 'El punto cifrado restaurado no es el de su momento.' );
	$GLOBALS['bk_options'][ $S::OPTION ] = array( 'encrypt' => false, 'require_signed' => false );
	$plain = inc_run( 'cifrada' );
	inc_check( 'base' === $C::job_plan( $plain )['mode'] && 'security' === $C::job_plan( $plain )['reason'], 'Cambiar el cifrado no empezó una cadena nueva.' );
} );

inc_case( 'I7 reanudación por eslabón: muerte a mitad del índice del padre y de la escritura; padre sin índice → completo', function() use ( $C, $B, $root ) {
	inc_run( 'reanuda' );
	for ( $i = 0; $i < 30; $i++ ) inc_put( 'wp-content/uploads/2026/12/archivo-' . $i . '.txt', str_repeat( 'contenido ' . $i, 50 ) );
	inc_run( 'reanuda' );
	inc_put( 'wp-content/uploads/2026/12/archivo-3.txt', 'cambiado' );
	inc_put( 'wp-content/uploads/2026/12/otro.txt', 'nuevo' );
	unlink( $root . '/wp-content/uploads/2026/12/archivo-5.txt' );
	$expected = inc_files();
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = function() { return 1; };
	$GLOBALS['bk_filters']['digitalisimo_backups_chain_index_batch'] = function() { return 5; };
	$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'reanuda', 'destination' => 'local', 'retain' => 20, 'retention' => 'policy', 'via' => 'test', 'incremental' => array( 'full_every' => 6 ) ) ) );
	$id = $queued['job']['id'];
	$killed = array();
	for ( $i = 0; $i < 4000; $i++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
		$resume = $job['checkpoint']['pack']['resume'] ?? null;
		if ( is_array( $resume ) && ! isset( $killed['index'] ) && 'index' === ( $resume['chain']['step'] ?? '' ) && $resume['chain']['records'] > 0 ) { $killed['index'] = true; inc_kill( $id ); }
		if ( is_array( $resume ) && ! isset( $killed['write'] ) && 'write' === $resume['stage'] && $resume['done'] > 5 ) { $killed['write'] = true; inc_kill( $id ); }
		Digitalisimo_Backups_Jobs::tick( 0 );
	}
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'], $GLOBALS['bk_filters']['digitalisimo_backups_chain_index_batch'] );
	$job = Digitalisimo_Backups_Jobs::get( $id );
	inc_check( 'success' === $job['status'] && isset( $killed['index'], $killed['write'] ), 'El incremental no terminó tras dos muertes: ' . json_encode( array( $job['status'], $job['error'], array_keys( $killed ) ) ) );
	inc_check( 'incremental' === $C::job_plan( $job )['mode'], 'Tras las muertes el punto dejó de ser incremental.' );
	$virtual = inc_materialize( inc_path( $job ) );
	$files = inc_zip( $virtual['path'] )['entries'];
	unset( $files['database.sql'], $files['manifest.json'], $files['wordpress/wp-config.php'] );
	ksort( $files );
	Digitalisimo_Backups_Security::remove_tree( $virtual['staging'] );
	inc_check( $expected === $files, 'El incremental retomado no es el sitio de su momento.' );
	// El índice del padre desaparece entre «preflight» y «pack»: el trabajo vuelve a empezar y sale un completo.
	inc_put( 'wp-content/uploads/2026/12/otro.txt', 'otra vez' );
	$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'reanuda', 'destination' => 'local', 'retain' => 20, 'retention' => 'policy', 'via' => 'test', 'incremental' => array( 'full_every' => 6 ) ) ) );
	$id = $queued['job']['id'];
	for ( $i = 0; $i < 40; $i++ ) {
		$current = Digitalisimo_Backups_Jobs::get( $id );
		if ( 'database' === $current['phase'] || Digitalisimo_Backups_Job_Store::is_terminal( $current ) ) break;
		Digitalisimo_Backups_Jobs::tick( 0.000001 );
	}
	$parent = inc_row( inc_path( $job ) );
	@unlink( $C::index_path( bk_storage(), $parent ) );
	for ( $i = 0; $i < 400; $i++ ) {
		$current = Digitalisimo_Backups_Jobs::get( $id );
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $current ) ) break;
		Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	}
	$current = Digitalisimo_Backups_Jobs::get( $id );
	inc_check( 'success' === $current['status'] && 'base' === $C::job_plan( $current )['mode'] && 'index' === $C::job_plan( $current )['reason'], 'Sin el índice del padre no salió un completo: ' . json_encode( array( $current['status'], $current['error'], $C::job_plan( $current ) ) ) );
} );

inc_case( 'I8 superficies: historial con tipo, base y ahorro; automatizaciones con su modo; respaldo manual incremental', function() use ( &$points, $C, $B ) {
	$list = $B::service_backups();
	$rows = array();
	foreach ( $list as $row ) $rows[ $row['file'] ] = $row;
	$p2 = $rows[ basename( $points[2] ) ];
	inc_check( 'incremental' === $p2['type'] && true === $p2['restorable'] && 2 === $p2['chain']['sequence'] && basename( $points[0] ) === $p2['chain']['base'] && 3 === $p2['chain']['links'] && $p2['chain']['saved'] > 0 && true === $p2['chain']['complete'], 'El historial no presenta el punto incremental: ' . json_encode( $p2 ) );
	inc_check( 'base' === $rows[ basename( $points[0] ) ]['chain']['role'] && 'full' === $rows[ basename( $points[0] ) ]['type'], 'El historial no presenta la base.' );
	$status = $B::service_status();
	inc_check( $status['incremental']['points'] > 0 && $status['incremental']['saved'] > 0, 'El estado no dice cuánto ahorran los incrementales: ' . json_encode( $status['incremental'] ?? null ) );
	// Automatización incremental por el formulario real y su modo en la lista.
	list( $ok, $message ) = bk_post( array( $B, 'create_automation' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_CREATE_AUTOMATION, 'digitalisimo_backup_automation' => array( 'type' => 'full', 'schedule' => 'daily', 'destination' => 'local', 'retain' => 7, 'mode' => 'incremental', 'full_every' => 6, 'full_days' => 7 ) ) );
	inc_check( $ok, 'No se creó la automatización incremental: ' . $message );
	$automations = array_values( array_filter( $B::service_automations(), function( $row ) { return 'incremental' === $row['mode']; } ) );
	inc_check( 1 === count( $automations ) && 6 === $automations[0]['full_every'] && 7 === $automations[0]['full_days'], 'La automatización incremental no quedó con su modo: ' . json_encode( $B::service_automations() ) );
	// Guardarla sin los campos del modo (un formulario anterior) no la vuelve completa.
	list( $ok, $message ) = bk_post( array( $B, 'update_automation' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_UPDATE_AUTOMATION, 'policy_id' => $automations[0]['id'], 'digitalisimo_backup_automation' => array( 'schedule' => 'weekly', 'destination' => 'local', 'retain' => 7, 'enabled' => 1 ) ) );
	inc_check( $ok, 'No se guardó la automatización: ' . $message );
	$after = array_values( array_filter( $B::service_automations(), function( $row ) use ( $automations ) { return $row['id'] === $automations[0]['id']; } ) );
	inc_check( 'incremental' === $after[0]['mode'] && 'weekly' === $after[0]['schedule'], 'Guardar la automatización sin los campos del modo la volvió completa.' );
	// El respaldo manual sigue siendo completo salvo que se pida incremental.
	$view = $B::service_create( array( 'destination' => 'local', 'via' => 'test' ) );
	$job = Digitalisimo_Backups_Jobs::get( $view['id'] );
	inc_check( ! isset( $job['spec']['incremental'] ), 'El respaldo manual dejó de ser completo.' );
	Digitalisimo_Backups_Jobs::cancel( $view['id'] );
	$view = $B::service_create( array( 'destination' => 'local', 'via' => 'test', 'incremental' => true ) );
	$job = Digitalisimo_Backups_Jobs::get( $view['id'] );
	inc_check( array( 'full_every' => 6, 'full_days' => 7 ) === ( $job['spec']['incremental'] ?? null ), 'El respaldo manual incremental no fijó su cadena: ' . json_encode( $job['spec']['incremental'] ?? null ) );
	Digitalisimo_Backups_Jobs::cancel( $view['id'] );
	// La copia previa de una restauración y la extracción nunca son incrementales.
	$spec = Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'pre-restore', 'inline' => true, 'incremental' => array( 'full_every' => 2 ) ) );
	inc_check( ! isset( $spec['incremental'] ) && ! $C::job_enabled( $spec ), 'Una copia previa sería incremental.' );
} );

inc_case( 'I10 borrar el padre de un incremental en curso se niega; si desaparece igual, «publish» vuelve a «preflight» y sale un completo', function() use ( $C, $B ) {
	inc_put( 'wp-content/uploads/2026/12/d.txt', 'd0' );
	inc_run( 'borra-padre' );
	inc_put( 'wp-content/uploads/2026/12/d.txt', 'd1' );
	$i1 = inc_path( inc_run( 'borra-padre' ) );
	inc_put( 'wp-content/uploads/2026/12/d.txt', 'd2' );
	$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'borra-padre', 'destination' => 'local', 'retain' => 20, 'retention' => 'policy', 'via' => 'test', 'incremental' => array( 'full_every' => 6 ) ) ) );
	$id = $queued['job']['id'];
	for ( $i = 0; $i < 60; $i++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( 'verify' === $job['phase'] || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
		Digitalisimo_Backups_Jobs::tick( 0.000001 );
	}
	inc_check( 'verify' === $job['phase'] && 'incremental' === $C::job_plan( $job )['mode'], 'El incremental no llegó a «verify».' );
	list( $ok, $message ) = bk_post( array( 'Digitalisimo_Backups', 'delete_backup' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_DELETE, 'file' => basename( $i1 ) ) );
	inc_check( ! $ok && false !== strpos( $message, 'trabajo en curso' ) && is_file( $i1 ), 'Se borró el padre de un incremental en curso: ' . $message );
	// Desaparece igual (a mano, por SSH): el punto no puede nacer roto.
	$row = inc_row( $i1 );
	bk_call( 'Digitalisimo_Backups', 'remove_history', basename( $i1 ) );
	unlink( $i1 );
	for ( $i = 0; $i < 400; $i++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
		Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	}
	// El trabajo vuelve a «preflight»: el punto nace sobre lo que queda de su cadena (la base), nunca sobre el padre borrado.
	$plan = $C::job_plan( $job );
	$point = inc_row( inc_path( $job ) );
	inc_check( 'success' === $job['status'] && ! in_array( basename( $i1 ), $C::files( $point ), true ) && array() === $B::chain_missing( $point ), 'Sin su padre el punto nació roto: ' . json_encode( array( $job['status'], $plan['mode'] ?? '', $C::files( $point ) ) ) );
	unset( $row );
} );

inc_case( 'I11 un eslabón cuyo contenido no es el que exige el inventario firmado del punto se rechaza (SHA-256 de cada archivo copiado)', function() use ( $C, $B ) {
	inc_put( 'wp-content/uploads/2026/12/m5.txt', 'm5 base' );
	inc_run( 'm5' );
	inc_put( 'wp-content/uploads/2026/12/m5.txt', 'm5 punto 1' );
	$point = inc_path( inc_run( 'm5' ) );
	// El inventario del punto dice otro contenido (mismo tamaño) para un archivo que hereda de la base; el manifiesto y la
	// fila del historial se ponen al día, como lo haría un índice lateral equivocado.
	$zip = new ZipArchive();
	$zip->open( $point );
	$lines = explode( "\n", trim( (string) $zip->getFromName( 'inventory.jsonl' ) ) );
	foreach ( $lines as &$line ) {
		$record = json_decode( $line, true );
		if ( 'wordpress/wp-load.php' === ( $record['n'] ?? '' ) ) { $record['h'] = hash( 'sha256', 'otro contenido' ); $line = json_encode( $record, JSON_UNESCAPED_SLASHES ); }
	}
	unset( $line );
	$inventory = implode( "\n", $lines ) . "\n";
	$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	$manifest['entries']['inventory.jsonl'] = array( 'size' => strlen( $inventory ), 'sha256' => hash( 'sha256', $inventory ) );
	$bytes = json_encode( $manifest, JSON_PRETTY_PRINT );
	$zip->addFromString( 'inventory.jsonl', $inventory );
	$zip->addFromString( 'manifest.json', $bytes );
	// Si el sitio firma, se vuelve a firmar con su clave: sólo la comprobación del contenido de cada archivo lo detecta.
	if ( isset( $manifest['auth']['key']['id'] ) ) {
		$active = Digitalisimo_Backups_Keys::active();
		$zip->setArchiveComment( Digitalisimo_Backups_Manifest_Auth::comment( $active[0], $bytes ) );
	}
	$zip->close();
	foreach ( $GLOBALS['bk_options'][ $B::HISTORY ] as &$row ) if ( basename( $point ) === $row['file'] ) { $row['sha256'] = hash_file( 'sha256', $point ); $row['chain']['manifest_sha256'] = hash( 'sha256', $bytes ); }
	unset( $row );
	$message = bk_expect_failure( 'contenido distinto del inventario', function() use ( $point ) { inc_materialize( $point ); }, 'con el contenido que el punto exige' );
	inc_check( false !== strpos( $message, 'wp-load.php' ), 'El rechazo no nombra el archivo: ' . $message );
} );

inc_case( 'I12 un eslabón sin ninguna copia no se encadena: el siguiente punto es un completo', function() use ( $C ) {
	inc_put( 'wp-content/uploads/2026/12/m6.txt', 'm6 base' );
	$base = inc_path( inc_run( 'm6' ) );
	inc_put( 'wp-content/uploads/2026/12/m6.txt', 'm6 punto 1' );
	inc_run( 'm6' );
	unlink( $base ); // Su fila sigue, sin ZIP local ni copia remota.
	inc_put( 'wp-content/uploads/2026/12/m6.txt', 'm6 punto 2' );
	$plan = $C::job_plan( inc_run( 'm6' ) );
	inc_check( 'base' === $plan['mode'] && 'missing_link' === $plan['reason'], 'Se encadenó sobre un eslabón sin copia: ' . json_encode( $plan ) );
} );

inc_case( 'I13 los borrados salen del inventario confirmado aunque un tramo muerto haya marcado archivos que después desaparecen', function() use ( $C, $root ) {
	for ( $i = 0; $i < 12; $i++ ) inc_put( 'wp-content/uploads/2026/12/v-' . $i . '.txt', 'visto ' . $i );
	inc_run( 'vistos' );
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = function() { return 1; };
	$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'vistos', 'destination' => 'local', 'retain' => 20, 'retention' => 'policy', 'via' => 'test', 'incremental' => array( 'full_every' => 6 ) ) ) );
	$id = $queued['job']['id'];
	$gone = '';
	for ( $i = 0; $i < 4000; $i++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
		$resume = $job['checkpoint']['pack']['resume'] ?? null;
		if ( '' === $gone && is_array( $resume ) && 'write' === $resume['stage'] && $resume['done'] >= 2 ) {
			// Lo que dejaría un tramo muerto: todos los archivos del padre marcados como «vistos»; y uno que aún no se
			// procesó desaparece antes del tramo siguiente.
			$staging = bk_storage() . '/jobs/' . $id . '/staging/package.' . $resume['tag'];
			file_put_contents( $staging . '.parent.seen', str_repeat( "\1", (int) $resume['chain']['count'] ) );
			$rest = substr( file_get_contents( $staging . '.files.jsonl' ), (int) $resume['files_read'] );
			foreach ( explode( "\n", $rest ) as $line ) if ( 0 === strpos( $line, 'wp-content/uploads/2026/12/v-' ) ) { $gone = $line; break; }
			inc_check( '' !== $gone, 'No quedaba ningún archivo por procesar para la prueba.' );
			unlink( $root . '/' . $gone );
		}
		Digitalisimo_Backups_Jobs::tick( 0 );
	}
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	$job = Digitalisimo_Backups_Jobs::get( $id );
	inc_check( 'success' === $job['status'] && '' !== $gone, 'El incremental no terminó: ' . json_encode( $job['error'] ) );
	$zip = inc_zip( inc_path( $job ) );
	$deleted = array();
	foreach ( $zip['inventory'] as $line ) if ( ! empty( $line['x'] ) ) $deleted[] = $line['n'];
	inc_check( array( 'wordpress/' . $gone ) === $deleted && 1 === $zip['manifest']['chain']['stats']['deleted'], 'El borrado no figura en el inventario: ' . json_encode( $deleted ) );
} );

inc_case( 'I17 un respaldo que una automatización encola mientras se reconstruye el punto no tumba la restauración confirmada', function() use ( $C ) {
	inc_put( 'wp-content/uploads/2026/12/cola.txt', 'cola 0' );
	inc_run( 'cola' );
	for ( $i = 0; $i < 6; $i++ ) inc_put( 'wp-content/uploads/2026/12/cola-' . $i . '.txt', str_repeat( 'cola ' . $i, 100 ) );
	$point = inc_path( inc_run( 'cola' ) );
	$tree = inc_files();
	inc_put( 'wp-content/uploads/2026/12/cola.txt', 'después' );
	$GLOBALS['bk_filters']['digitalisimo_backups_restore_respond_after'] = function() { return 0; };
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = function() { return 1; };
	$result = Digitalisimo_Backups::service_restore( basename( $point ), array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false, 'via' => 'test' ) );
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_restore_respond_after'] );
	inc_check( ! is_wp_error( $result ) && 'chain' === ( $result['job']['kind'] ?? '' ), 'La restauración no empezó por su reconstrucción.' );
	$chain = $result['job']['id'];
	Digitalisimo_Backups_Jobs::tick( 0 );
	Digitalisimo_Backups_Jobs::tick( 0 );
	// WP-Cron encola una automatización mientras tanto.
	$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'cola-cron', 'destination' => 'local', 'retain' => 20, 'retention' => 'policy', 'via' => 'cron' ) ) );
	inc_check( empty( $queued['duplicate'] ), 'No se encoló el respaldo de la automatización.' );
	for ( $i = 0; $i < 4000 && Digitalisimo_Backups_Jobs::active(); $i++ ) {
		$backup = Digitalisimo_Backups_Jobs::get( $queued['job']['id'] );
		$job = Digitalisimo_Backups_Jobs::get( $chain );
		inc_check( 'queued' === $backup['status'] || Digitalisimo_Backups_Job_Store::is_terminal( $job ), 'El respaldo empezó mientras se reconstruía el punto.' );
		Digitalisimo_Backups_Jobs::tick( 0 );
	}
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	$job = Digitalisimo_Backups_Jobs::get( $chain );
	inc_check( 'success' === $job['status'] && inc_last_restore_ok() && $tree === inc_files(), 'La restauración confirmada cayó por el respaldo encolado: ' . json_encode( array( $job['status'], $job['error'] ) ) );
	inc_check( 'success' === Digitalisimo_Backups_Jobs::get( $queued['job']['id'] )['status'], 'El respaldo encolado no se hizo después de la restauración.' );
} );

inc_case( 'I18 si el proceso muere justo después de encolar la restauración, la reconstrucción no dice que no cambió nada', function() {
	inc_put( 'wp-content/uploads/2026/12/muere.txt', 'muere 0' );
	inc_run( 'muere' );
	inc_put( 'wp-content/uploads/2026/12/muere.txt', 'muere 1' );
	$point = inc_path( inc_run( 'muere' ) );
	$tree = inc_files();
	inc_put( 'wp-content/uploads/2026/12/muere.txt', 'después' );
	$GLOBALS['bk_filters']['digitalisimo_backups_restore_respond_after'] = function() { return 0; };
	$result = Digitalisimo_Backups::service_restore( basename( $point ), array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false, 'via' => 'test' ) );
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_restore_respond_after'] );
	$chain = $result['job']['id'];
	for ( $i = 0; $i < 200; $i++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $chain );
		if ( 'handoff' === $job['phase'] || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
		Digitalisimo_Backups_Jobs::tick( 0 );
	}
	inc_check( 'handoff' === $job['phase'], 'La reconstrucción no llegó a la entrega.' );
	// La entrega encola la restauración y el proceso muere antes de anotarlo.
	$plan = json_decode( file_get_contents( Digitalisimo_Backups_Job_Store::staging( $chain ) . '/plan.json' ), true );
	Digitalisimo_Backups::chain_handoff( $job, $plan );
	inc_kill( $chain );
	for ( $i = 0; $i < 4000 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	$job = Digitalisimo_Backups_Jobs::get( $chain );
	inc_check( 'success' === $job['status'] && '' !== (string) ( $job['result']['restore_job'] ?? '' ) && inc_last_restore_ok() && $tree === inc_files(), 'Tras morir en la entrega, la reconstrucción no reconoce la restauración que ya hizo: ' . json_encode( array( $job['status'], $job['error']['message'] ?? '' ) ) );
} );

// Revisión M5 (PD) de backups/restore-progress: la restauración de un punto pedida desde la pantalla usa el testigo que la
// pantalla ya mandó en la cookie (antes de empezar): la reconstrucción y la restauración que la sigue llevan ese mismo.
inc_case( 'I20 el testigo que da la pantalla es el de la reconstrucción del punto y el de su restauración', function() {
	inc_put( 'wp-content/uploads/2026/12/testigo.txt', 'testigo 0' );
	inc_run( 'testigo' );
	inc_put( 'wp-content/uploads/2026/12/testigo.txt', 'testigo 1' );
	$point = inc_path( inc_run( 'testigo' ) );
	$given = str_repeat( 'e7', 32 );
	$GLOBALS['bk_filters']['digitalisimo_backups_restore_respond_after'] = function() { return 0; };
	$result = Digitalisimo_Backups::service_restore( basename( $point ), array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false, 'via' => 'admin-post', 'token' => $given ) );
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_restore_respond_after'] );
	inc_check( ! is_wp_error( $result ) && 'chain' === ( $result['job']['kind'] ?? '' ), 'La restauración del punto no empezó por su reconstrucción.' );
	$chain = $result['job']['id'];
	inc_check( $given === (string) ( Digitalisimo_Backups_Jobs::get( $chain )['spec']['token'] ?? '' ), 'La reconstrucción del punto no lleva el testigo de la pantalla (el de su cookie).' );
	for ( $i = 0; $i < 4000 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	$job = Digitalisimo_Backups_Jobs::get( $chain );
	$restore = Digitalisimo_Backups_Jobs::get( (string) ( $job['result']['restore_job'] ?? '' ) );
	inc_check( 'success' === $job['status'] && $restore && $given === (string) ( $restore['spec']['token'] ?? '' ), 'La restauración que sigue al punto no lleva el testigo de la pantalla: ' . json_encode( array( $job['status'], $restore['spec']['token'] ?? null ) ) );
	inc_check( true === Digitalisimo_Backups_Jobs::screen_view( $restore )['from_point'] && empty( Digitalisimo_Backups_Jobs::screen_view( Digitalisimo_Backups_Jobs::get( $chain ) )['from_point'] ), 'La vista de pantalla no dice que la restauración viene del punto reconstruido (su tramo de la barra).' );
} );

inc_case( 'I19 una cadena de más eslabones que el tope no se acepta; «Conservar» recortado se avisa', function() use ( $C, $B ) {
	$links = array();
	for ( $i = 0; $i <= $C::MAX_FULL_EVERY; $i++ ) $links[] = array( 'sequence' => $i, 'file' => 'digitalisimo-backup-x-' . $i . '.zip', 'backup_id' => '00000000-0000-4000-8000-' . sprintf( '%012d', $i ), 'manifest_sha256' => str_repeat( 'a', 64 ) );
	$data = array( 'format' => 'digitalisimo-backup/v3', 'type' => 'incremental', 'chain' => array( 'version' => 1, 'id' => '00000000-0000-4000-8000-000000000000', 'sequence' => count( $links ), 'links' => $links ) );
	inc_check( null === $C::manifest_chain( $data ), 'Se aceptó una cadena de ' . count( $links ) . ' eslabones (el tope es ' . $C::MAX_FULL_EVERY . ').' );
	array_pop( $data['chain']['links'] );
	$data['chain']['sequence']--;
	inc_check( null !== $C::manifest_chain( $data ), 'Se rechazó una cadena dentro del tope.' );
	list( $ok, $message ) = bk_post( array( $B, 'create_automation' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_CREATE_AUTOMATION, 'digitalisimo_backup_automation' => array( 'type' => 'full', 'schedule' => 'daily', 'destination' => 'local', 'retain' => 100, 'mode' => 'incremental', 'full_every' => 30 ) ) );
	inc_check( $ok && false !== strpos( $message, '«Conservar» quedó en 70' ), 'El recorte de «Conservar» no se avisó: ' . $message );
} );

inc_case( 'I9 tope del historial: nunca saca la fila de un eslabón del que depende un punto que se queda; «Conservar 100» con un completo cada 6 cabe entero', function() use ( $C, $B ) {
	// La pantalla no deja que una automatización incremental desborde el historial: Conservar + N ≤ 100.
	list( $ok, $message ) = bk_post( array( $B, 'create_automation' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_CREATE_AUTOMATION, 'digitalisimo_backup_automation' => array( 'type' => 'full', 'schedule' => 'daily', 'destination' => 'local', 'retain' => 100, 'mode' => 'incremental', 'full_every' => 6 ) ) );
	$cien = array_values( array_filter( $B::policies(), function( $p ) { return 'incremental' === ( $p['mode'] ?? '' ) && 6 === (int) ( $p['full_every'] ?? 0 ) && (int) $p['retain'] > 50; } ) );
	inc_check( $ok && $cien && 94 === (int) $cien[0]['retain'], 'Conservar 100 con un completo cada 6 no se acotó a 94: ' . json_encode( $cien ) );
	// Una cadena (base, 1, 2) y, después, 98 respaldos de otra automatización: la base queda más allá de la fila 100.
	inc_put( 'wp-content/uploads/2026/12/tope.txt', 't0' );
	$base = inc_path( inc_run( 'tope' ) );
	inc_put( 'wp-content/uploads/2026/12/tope.txt', 't1' );
	inc_run( 'tope' );
	inc_put( 'wp-content/uploads/2026/12/tope.txt', 't2' );
	$head = inc_path( inc_run( 'tope' ) );
	$GLOBALS['bk_options'][ $B::HISTORY ] = array_values( array_filter( (array) $GLOBALS['bk_options'][ $B::HISTORY ], function( $row ) { return 'tope' === ( $row['policy'] ?? '' ); } ) );
	for ( $i = 0; $i < 98; $i++ ) {
		$dummy = rtrim( bk_storage(), '/' ) . '/digitalisimo-backup-example-test-relleno-' . $i . '.zip';
		file_put_contents( $dummy, 'relleno ' . $i );
		bk_call( 'Digitalisimo_Backups', 'remember', $dummy, 'local', 'installation', array( 'id' => 'relleno', 'type' => 'full' ), array(), 0, false, array() );
	}
	$history = (array) $GLOBALS['bk_options'][ $B::HISTORY ];
	inc_check( 101 === count( $history ) && inc_row( $base ) && array() === $B::chain_missing( inc_row( $head ) ), 'El tope del historial sacó la base de una cadena que se conserva: ' . count( $history ) . ' filas.' );
	$virtual = inc_materialize( $head );
	Digitalisimo_Backups_Security::remove_tree( $virtual['staging'] );
	// Si aun así la fila de un eslabón sale del historial (a mano, o de una versión anterior), su archivo en el almacén
	// sigue sirviendo: se busca por nombre y se comprueba por su manifiesto.
	bk_call( 'Digitalisimo_Backups', 'remove_history', basename( $base ) );
	inc_check( array() === $B::chain_missing( inc_row( $head ) ), 'Un eslabón sin fila pero en el almacén cuenta como que falta.' );
	$verify = $B::service_verify( basename( $head ) );
	inc_check( ! is_wp_error( $verify ) && 3 === $verify['chain']['links'], 'Un punto cuyo completo salió del historial no se verificó por su manifiesto: ' . ( is_wp_error( $verify ) ? $verify->get_error_message() : json_encode( $verify['chain'] ) ) );
	// «Conservar 94» (lo que queda de 100) con un completo cada 6: 102 puntos, todos con su cadena y sin ZIP sin fila.
	$GLOBALS['bk_options'][ $B::HISTORY ] = array();
	foreach ( glob( rtrim( bk_storage(), '/' ) . '/digitalisimo-*' ) as $old ) unlink( $old );
	for ( $i = 0; $i < 102; $i++ ) {
		inc_put( 'wp-content/uploads/2026/12/cien.txt', 'punto ' . $i );
		inc_run( 'cien', 6, 7, 94 );
	}
	$history = (array) $GLOBALS['bk_options'][ $B::HISTORY ];
	$named = array_flip( array_column( $history, 'file' ) );
	foreach ( glob( rtrim( bk_storage(), '/' ) . '/digitalisimo-*' ) as $file ) inc_check( isset( $named[ basename( $file ) ] ), 'Quedó un ZIP sin fila: ' . basename( $file ) );
	foreach ( $history as $row ) inc_check( array() === $B::chain_missing( $row ), 'Un punto conservado perdió su cadena: ' . $row['file'] );
	inc_check( count( $history ) <= 100 && count( $history ) >= 94, 'Puntos conservados: ' . count( $history ) );
} );

bk_cleanup();
echo $failures ? "FALLA: incrementales con {$failures} caso(s) fallido(s).\n" : "DIGITALÍSIMO Backups: incrementales con cadena íntegra, puntos completos, verificación de la cadena, retención consciente y cifrado.\n";
exit( $failures ? 1 : 0 );
