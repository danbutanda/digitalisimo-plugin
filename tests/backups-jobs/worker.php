<?php
/**
 * Trabajador de tests/backups-job-kill.php: corre un tick sobre la raíz de prueba indicada. Con «bloquear-db» se
 * detiene dentro de la instantánea de la exportación, con el cerrojo del almacén tomado, para que lo maten ahí.
 */
$GLOBALS['bk_root'] = (string) ( $argv[1] ?? '' );
if ( '' === $GLOBALS['bk_root'] || ! is_dir( $GLOBALS['bk_root'] ) ) { fwrite( STDERR, "Falta la raíz de prueba.\n" ); exit( 2 ); }
require __DIR__ . '/env.php';
if ( in_array( 'bloquear-db', $argv, true ) ) {
	$GLOBALS['wpdb']->on_select = function() {
		if ( ! $GLOBALS['wpdb']->in_snapshot || file_exists( $GLOBALS['bk_root'] . '/worker-ready' ) ) return;
		file_put_contents( $GLOBALS['bk_root'] . '/worker-ready', (string) getmypid() );
		sleep( 120 );
	};
}
echo json_encode( Digitalisimo_Backups_Jobs::tick() ), "\n";
