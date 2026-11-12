<?php
/**
 * Hallazgos del ensayo real E5 (/var/tmp/digi-wt/bk-lab/E5.md, «Hallazgos») que no tienen prueba propia:
 *   H27  desde 1.0.73 la retención retira del historial la fila que se queda sin ninguna copia (H22), pero las filas
 *        «Sin copias» que 1.0.71 ya había dejado (sin ZIP en el almacén ni copia remota) seguían ahí tras actualizar;
 *        con meses de automatización diaria serían decenas. La puesta al día de los datos de 1.0.75 las retira una sola
 *        vez al cargar el escritorio, el cron o WP-CLI:
 *          - sólo las filas sin archivo local y sin ninguna copia remota registrada; nunca una con copia en algún sitio
 *            (ZIP, enlace con su nombre, remota verificada o anterior a 1.0.59, copia o borrado remoto pendientes),
 *            bloqueada o con la marca de una migración;
 *          - idempotente: otra carga no cambia nada, y volver a correrla (la marca la borra restaurar un respaldo
 *            anterior) tampoco;
 *          - sin poder comprobarlo (una restauración a medias) no retira nada ni se marca: se reintenta;
 *          - en una red, sólo desde el sitio principal, con la opción y el ámbito del historial (no en sitemeta).
 * (H26, el aviso de la configuración del respaldo pendiente, está en backups-restore-pending-config.php y en
 * backups-simple-screen.php.)
 */
require __DIR__ . '/lib/backups-env.php';
if ( ! function_exists( 'is_admin' ) ) { function is_admin() { return true; } }
function e5_check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( 'FALLA: ' . $message ); }
$B = 'Digitalisimo_Backups';
try {
	e5_check( is_callable( array( $B, 'upgrade_data_on_load' ) ) && is_callable( array( $B, 'upgrade_data' ) ), 'H27: Backups no tiene puesta al día de los datos al actualizar: las filas «Sin copias» que dejó 1.0.71 se quedan en el historial.' );
	// La carga del plugin la engancha a `init` (escritorio, cron y WP-CLI), como las credenciales.
	$init = new ReflectionMethod( $B, 'init' );
	$source = implode( '', array_slice( file( $init->getFileName() ), $init->getStartLine() - 1, $init->getEndLine() - $init->getStartLine() + 1 ) );
	e5_check( false !== strpos( $source, "add_action( 'init', array( __CLASS__, 'upgrade_data_on_load' ) )" ), 'H27: init() no engancha la puesta al día de los datos.' );

	$store = rtrim( bk_storage(), '/' );
	$own = basename( bk_backup( true ) );
	$row_of = function( $file ) use ( $B ) { foreach ( (array) get_option( $B::HISTORY, array() ) as $row ) if ( $file === ( $row['file'] ?? '' ) ) return $row; return null; };
	$template = $row_of( $own );
	e5_check( is_array( $template ), 'El respaldo de la prueba no quedó en el historial.' );
	// Filas como las dejó 1.0.71: «sólo en el remoto» (sin ZIP local) cuya copia SFTP borró la retención.
	$legacy = function( $name, array $changes = array() ) use ( $template ) {
		return array_merge( $template, array( 'file' => $name, 'date' => '2026-11-10 01:31:48', 'policy' => 'automation-e4', 'locked' => false, 'transfer_destination' => 'sftp', 'transfer' => 'pruned', 'remote' => array(), 'transfer_error' => '', 'remote_pruned_at' => time() - 86400, 'remote_only' => true ), $changes );
	};
	$prefix = preg_replace( '/-\d{8}-\d{6}-[a-f0-9]+\.zip$/', '', $own );
	$name = function( $tag ) use ( $prefix ) { return $prefix . '-20261110-' . $tag . '.zip'; };
	$rows = array(
		// Se retiran: sin ninguna copia.
		'sin-copias' => $legacy( $name( '013148-c232f77e' ) ),
		'sin-copias-2' => $legacy( $name( '013043-aad7196d' ) ),
		'local-borrado' => $legacy( $name( '010000-0a0a0a0a' ), array( 'policy' => 'monthly-full', 'transfer_destination' => '', 'transfer' => '', 'remote_only' => false ) ),
		// Se quedan: alguna copia, bloqueada, marca de migración o archivo con su nombre.
		'con-zip' => $legacy( $name( '020000-0b0b0b0b' ) ),
		'bloqueada' => $legacy( $name( '020100-0c0c0c0c' ), array( 'locked' => true ) ),
		'remota-ok' => $legacy( $name( '020200-0d0d0d0d' ), array( 'transfer' => 'ok', 'remote' => array( 'provider' => 'sftp', 'path' => '/respaldos/x.zip', 'sha256' => str_repeat( 'a', 64 ) ) ) ),
		'remota-1.0.58' => $legacy( $name( '020300-0e0e0e0e' ), array( 'destination' => 'sftp', 'transfer_destination' => '', 'transfer' => '' ) ),
		'copia-pendiente' => $legacy( $name( '020400-0f0f0f0f' ), array( 'transfer' => 'pending' ) ),
		'borrado-pendiente' => $legacy( $name( '020500-10101010' ), array( 'remote_prune' => array( 'state' => 'failed', 'attempts' => 3, 'next' => 0, 'error' => 'x', 'at' => time() ) ) ),
		'migracion' => $legacy( $name( '020600-11111111' ), array( 'policy' => 'pre-restore', 'migration' => array( 'state' => 'migrated', 'job' => str_repeat( '1', 32 ) ) ) ),
		'enlace' => $legacy( $name( '020700-12121212' ) ),
	);
	copy( $store . '/' . $own, $store . '/' . $rows['con-zip']['file'] );
	$link = @symlink( $store . '/' . $own, $store . '/' . $rows['enlace']['file'] );
	if ( ! $link ) unset( $rows['enlace'] );
	$history = array_merge( array( $template ), array_values( $rows ) );
	update_option( $B::HISTORY, $history );
	$gone = array( 'sin-copias', 'sin-copias-2', 'local-borrado' );
	$expected = array_values( array_filter( $history, function( $row ) use ( $rows, $gone ) { foreach ( $gone as $key ) if ( $rows[ $key ]['file'] === $row['file'] ) return false; return true; } ) );

	// Sin poder comprobarlo (una restauración a medias en el almacén), no retira nada ni se marca.
	file_put_contents( $store . '/' . $B::RESTORE_JOURNAL, '{}' );
	$B::upgrade_data_on_load();
	e5_check( $history === get_option( $B::HISTORY ) && false === get_option( $B::DATA_VERSION, false ), 'H27: con una restauración a medias se tocó el historial o se marcó la puesta al día.' );
	unlink( $store . '/' . $B::RESTORE_JOURNAL );

	// Una red, desde un subsitio: nada (el historial vive en el sitio principal).
	$GLOBALS['bk_multisite'] = true;
	$GLOBALS['bk_main_site'] = false;
	try { $B::upgrade_data_on_load(); } finally { $GLOBALS['bk_main_site'] = true; }
	e5_check( $history === get_option( $B::HISTORY ) && false === get_option( $B::DATA_VERSION, false ), 'H27: desde un subsitio de la red se tocó el historial o se marcó la puesta al día.' );

	// La red, desde el sitio principal: retira las tres filas sin copias y nada más, en la opción del historial.
	$B::upgrade_data_on_load();
	$after = get_option( $B::HISTORY );
	$left = array_map( function( $row ) { return $row['file']; }, (array) $after );
	e5_check( $expected === $after, 'H27: la puesta al día no retiró exactamente las filas sin ninguna copia. Quedan: ' . implode( ', ', $left ) );
	foreach ( $gone as $key ) e5_check( null === $row_of( $rows[ $key ]['file'] ), 'H27: sigue la fila «Sin copias» ' . $key );
	e5_check( '1.0.75' === get_option( $B::DATA_VERSION, false ) && ! isset( $GLOBALS['bk_site_options'][ $B::DATA_VERSION ] ) && ! isset( $GLOBALS['bk_site_options'][ $B::HISTORY ] ), 'H27: la marca de la puesta al día o el historial no quedaron en el ámbito del historial (opción del sitio principal): ' . json_encode( array( get_option( $B::DATA_VERSION, false ), array_keys( $GLOBALS['bk_site_options'] ) ) ) );
	e5_check( is_file( $store . '/' . $rows['con-zip']['file'] ) && is_file( $store . '/' . $own ), 'H27: la puesta al día borró un ZIP del almacén.' );
	$GLOBALS['bk_multisite'] = false;

	// Idempotente: otra carga no cambia nada (ni con una fila sin copias nueva: eso ya es cosa de la retención, H22)…
	$again = array_merge( $after, array( $legacy( $name( '030000-13131313' ) ) ) );
	update_option( $B::HISTORY, $again );
	$B::upgrade_data_on_load();
	e5_check( $again === get_option( $B::HISTORY ), 'H27: la puesta al día volvió a correr con la marca ya puesta.' );
	// …y volver a correrla (restaurar un respaldo anterior a 1.0.75 trae el SQL sin la marca) sólo retira filas sin copias.
	update_option( $B::HISTORY, $after );
	delete_option( $B::DATA_VERSION );
	$B::upgrade_data();
	e5_check( $after === get_option( $B::HISTORY ) && '1.0.75' === get_option( $B::DATA_VERSION, false ), 'H27: volver a correr la puesta al día cambió filas con copia.' );

	echo "DIGITALÍSIMO Backups: hallazgos del ensayo E5 (H27: las filas «Sin copias» de 1.0.71 se retiran una vez al actualizar, sólo las que no tienen ninguna copia) OK\n";
} finally {
	bk_cleanup();
}
