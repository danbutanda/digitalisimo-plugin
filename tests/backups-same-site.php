<?php
/**
 * Backups y el candado «mismo sitio» (incidente del 2026-11-20: una caché de objetos compartida entre dos cuentas del
 * servidor sin WP_CACHE_KEY_SALT): con la caché devolviendo el `home` de otro sitio, ni se encola ni se ejecuta nada y
 * no se toca el cron; claim() sólo toma trabajos de esta instalación; tick() con un id sólo corre ése; la restauración
 * encima de este sitio del respaldo de otro `home` u otra instalación se niega sin el dominio de origen escrito.
 */
require __DIR__ . '/backups-jobs/env.php';

/** La base de verdad: `home` y `siteurl` de la tabla de opciones, aparte de lo que dé la caché (get_option()). */
final class Same_Site_Test_DB extends Digitalisimo_Jobs_Test_DB {
	public $options = 'wp_options';
	public $rows = array( 'home' => 'https://ejemplo.test', 'siteurl' => 'https://ejemplo.test' );
	public function get_var( $query ) { return preg_match( "/option_name = '([^']*)'/", (string) $query, $m ) ? ( $this->rows[ $m[1] ] ?? null ) : null; }
}
$GLOBALS['wpdb'] = new Same_Site_Test_DB();
$GLOBALS['ss_ext'] = true;
function wp_using_ext_object_cache() { return ! empty( $GLOBALS['ss_ext'] ); }
update_option( 'home', 'https://ejemplo.test' );
update_option( 'siteurl', 'https://ejemplo.test' );
$collide = function( $on ) {
	update_option( 'home', $on ? 'https://digitalisimo.lat' : 'https://ejemplo.test' );
	update_option( 'siteurl', $on ? 'https://digitalisimo.lat' : 'https://ejemplo.test' );
};

// Sin choque, se encola como siempre y lleva la identidad de esta instalación (sin la dirección).
$first = bk_manual( 'local', 'antes' );
bk_assert( '' !== (string) ( $first['installation'] ?? '' ) && $first['installation'] === Digitalisimo_Backups::installation_owner(), 'Un trabajo nuevo debe llevar la identidad de esta instalación.' );

// Choque: la caché da el home de digitalisimo.lat.
$collide( true );
$ids_before = Digitalisimo_Backups_Job_Store::ids();
$cron_before = $GLOBALS['bk_cron'];
$rev_before = bk_job( $first['id'] )['rev'];
$tick = Digitalisimo_Backups_Jobs::tick( 0 );
bk_assert( 'error' === $tick['result'] && 'same_site' === ( $tick['code'] ?? '' ) && false !== strpos( (string) $tick['message'], 'digitalisimo.lat' ) && false !== strpos( (string) $tick['message'], 'WP_CACHE_KEY_SALT' ), 'tick() debe negarse con la caché de otro sitio: ' . json_encode( $tick ) );
$job = bk_job( $first['id'] );
bk_assert( 'queued' === $job['status'] && empty( $job['lease'] ) && $rev_before === $job['rev'] && ! bk_history(), 'Con la caché de otro sitio, ningún trabajo avanza ni se registra nada.' );
try {
	bk_manual( 'local', 'durante' );
	bk_fail( 'enqueue() aceptó un respaldo con la caché de otro sitio.' );
} catch ( Exception $e ) {
	bk_assert( false !== strpos( $e->getMessage(), 'digitalisimo.lat' ), 'El motivo debe nombrar el otro sitio: ' . $e->getMessage() );
}
bk_assert( $ids_before === Digitalisimo_Backups_Job_Store::ids(), 'No debe crearse ningún trabajo con la caché de otro sitio.' );
Digitalisimo_Backups_Jobs::schedule( true );
bk_assert( $cron_before === $GLOBALS['bk_cron'] && 0 === $GLOBALS['bk_spawned'], 'Con la caché de otro sitio no se toca la opción cron ni se lanza spawn_cron().' );
foreach ( array( 'service_create' => array( array( 'via' => 'test' ) ), 'service_restore' => array( 'x.zip', array( 'confirm' => 'RESTAURAR' ) ), 'service_migrate' => array( 'clave', array( 'confirm' => 'MIGRAR' ) ) ) as $method => $args ) {
	$result = call_user_func_array( array( 'Digitalisimo_Backups', $method ), $args );
	bk_assert( $result instanceof WP_Error && false !== strpos( $result->get_error_message(), 'digitalisimo.lat' ) && false !== strpos( $result->get_error_message(), 'WP_CACHE_KEY_SALT' ), $method . '() debe negarse con la caché de otro sitio: ' . ( $result instanceof WP_Error ? $result->get_error_message() : json_encode( $result ) ) );
}
$described = Digitalisimo_Backups_Relocate::describe( false );
bk_assert( 'https://ejemplo.test' === $described['home'] && 'https://ejemplo.test' === $described['siteurl'], 'describe() debe leer home/siteurl de la base, no de la caché: ' . $described['home'] );

// Sin choque vuelve a trabajar.
$collide( false );
$tick = Digitalisimo_Backups_Jobs::tick( 0 );
bk_assert( 'worked' === $tick['result'] && 0 < (int) bk_job( $first['id'] )['started_at'], 'Sin choque, tick() vuelve a tomar el trabajo: ' . json_encode( $tick ) );
Digitalisimo_Backups_Job_Store::with_lock( function() use ( $first ) { $job = Digitalisimo_Backups_Job_Store::read( $first['id'] ); $job['status'] = 'cancelled'; $job['lease'] = null; $job['finished_at'] = time(); Digitalisimo_Backups_Job_Store::write( $job ); } );

// claim() ignora los trabajos de otra instalación (un almacén compartido).
$foreign = bk_manual( 'local', 'ajeno' );
Digitalisimo_Backups_Job_Store::with_lock( function() use ( $foreign ) { $job = Digitalisimo_Backups_Job_Store::read( $foreign['id'] ); $job['installation'] = str_repeat( 'f', 64 ); Digitalisimo_Backups_Job_Store::write( $job ); } );
$tick = Digitalisimo_Backups_Jobs::tick( 0 );
bk_assert( 'idle' === $tick['result'] && 0 === (int) bk_job( $foreign['id'] )['started_at'], 'claim() no debe tomar el trabajo de otra instalación: ' . json_encode( $tick ) );
bk_assert( ! Digitalisimo_Backups_Jobs::owned( bk_job( $foreign['id'] ) ), 'owned() debe rechazar la identidad de otra instalación.' );

// tick() con un id (Migrar) sólo corre ese trabajo.
$a = bk_manual( 'local', 'politica-a' );
$b = bk_manual( 'local', 'politica-b' );
Digitalisimo_Backups_Jobs::tick( 0, $b['id'] );
bk_assert( 0 < (int) bk_job( $b['id'] )['started_at'] && 0 === (int) bk_job( $a['id'] )['started_at'], 'tick() con el id de Migrar debe correr sólo ese trabajo.' );

// La restauración encima de este sitio: el respaldo de otro home u otra instalación sólo con su dominio escrito.
$mine = Digitalisimo_Backups::installation_stamp();
$other = array( 'home_url' => 'https://digitalisimo.lat', 'origin' => array( 'home' => 'https://digitalisimo.lat' ), 'installation' => array( 'id' => str_repeat( 'a', 64 ), 'home' => 'https://digitalisimo.lat' ) );
$problem = Digitalisimo_Backups::restore_origin_problem( '', $other, array() );
bk_assert( false !== strpos( $problem, 'digitalisimo.lat' ) && false !== strpos( $problem, 'Dominio de origen' ), 'Un manifiesto de otro home debe negarse sin el dominio escrito: ' . $problem );
bk_assert( '' !== Digitalisimo_Backups::restore_origin_problem( '', $other, array( 'origin_domain' => 'otro.com' ) ), 'Otro dominio escrito no vale.' );
bk_assert( '' === Digitalisimo_Backups::restore_origin_problem( '', $other, array( 'origin_domain' => ' WWW.Digitalisimo.lat ' ) ), 'Con el dominio de origen escrito a mano, sí.' );
$same = array( 'home_url' => 'https://ejemplo.test', 'origin' => array( 'home' => 'https://ejemplo.test' ), 'installation' => array( 'id' => (string) $mine['id'], 'home' => 'https://ejemplo.test' ) );
bk_assert( '' === Digitalisimo_Backups::restore_origin_problem( '', $same, array() ), 'El respaldo de esta instalación y esta dirección se restaura sin escribir nada más.' );
$moved = array( 'origin' => array( 'home' => 'https://antes.test' ) ) + $same;
bk_assert( '' !== Digitalisimo_Backups::restore_origin_problem( '', $moved, array() ), 'La misma instalación con otro home también pide el dominio escrito.' );
$stranger = array( 'origin' => array( 'home' => 'https://ejemplo.test' ), 'installation' => array( 'id' => str_repeat( 'b', 64 ) ) ) + $same;
bk_assert( '' !== Digitalisimo_Backups::restore_origin_problem( '', $stranger, array() ), 'Otra instalación con el mismo home también pide el dominio escrito.' );

echo "OK backups-same-site\n";
