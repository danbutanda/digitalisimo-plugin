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
/** Un método privado (las pruebas de la cola no cargan tests/lib/backups-env.php). */
function ss_call( $class, $method, ...$args ) {
	$reflection = new ReflectionMethod( $class, $method );
	if ( PHP_VERSION_ID < 80100 ) $reflection->setAccessible( true );
	return $reflection->invokeArgs( null, $args );
}
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
// A1/N3 · Con una restauración propia a medias, el candado no «pasa» por vaciar la caché ni acepta el origen: sólo, desde
// su cambio de tablas, que la caché siga dando la dirección de este sitio de antes (la base ya tiene la del respaldo).
$restore = Digitalisimo_Backups_Jobs::create_restore( array( 'type' => 'restore', 'kind' => '', 'policy' => 'restore', 'destination' => 'local', 'file' => 'x.zip', 'tag' => '', 'expect_hosts' => array( 'https://origen.test' ) ) );
bk_assert( array( 'ejemplo.test' ) === $restore['expect'], 'La restauración sólo espera las direcciones de este sitio, nunca las del origen: ' . json_encode( $restore['expect'] ?? null ) );
$tick = Digitalisimo_Backups_Jobs::tick( 0 );
bk_assert( 'same_site' === ( $tick['code'] ?? '' ) && 'https://digitalisimo.lat' === get_option( 'home' ) && 0 === (int) bk_job( $restore['id'] )['started_at'], 'Con una restauración a medias y la caché de otro sitio, tick() se niega sin vaciar la caché: ' . json_encode( $tick ) );
$phase = function( $name ) use ( $restore ) { Digitalisimo_Backups_Job_Store::with_lock( function() use ( $restore, $name ) { $job = Digitalisimo_Backups_Job_Store::read( $restore['id'] ); $job['phase'] = $name; $job['lease'] = null; $job['status'] = 'queued'; $job['retry_at'] = 0; Digitalisimo_Backups_Job_Store::write( $job ); } ); };
// La base ya tiene la dirección del respaldo (origen.test) y la caché la de antes (ejemplo.test).
$GLOBALS['wpdb']->rows['home'] = $GLOBALS['wpdb']->rows['siteurl'] = 'https://origen.test';
update_option( 'home', 'https://ejemplo.test' );
update_option( 'siteurl', 'https://ejemplo.test' );
$tick = Digitalisimo_Backups_Jobs::tick( 0 );
bk_assert( 'same_site' === ( $tick['code'] ?? '' ), 'Antes de su cambio de tablas no se acepta nada distinto de la base: ' . json_encode( $tick ) );
$phase( 'switch' );
$ran = false;
Digitalisimo_Backups_Jobs::$phase_runner = function( $phase, $job ) use ( &$ran ) { $ran = true; throw new Digitalisimo_Backups_Permanent_Error( 'Sólo se prueba que el paso corre.' ); };
$tick = Digitalisimo_Backups_Jobs::tick( 0 );
bk_assert( 'same_site' !== ( $tick['code'] ?? '' ) && $ran, 'Desde su cambio de tablas, la dirección de este sitio de antes en la caché se acepta: ' . json_encode( $tick ) );
// El origen en la caché (el sitio que la comparte) nunca, ni después del cambio.
$phase( 'switch' );
$GLOBALS['wpdb']->rows['home'] = $GLOBALS['wpdb']->rows['siteurl'] = 'https://ejemplo.test';
update_option( 'home', 'https://origen.test' );
update_option( 'siteurl', 'https://origen.test' );
$ran = false;
$tick = Digitalisimo_Backups_Jobs::tick( 0 );
Digitalisimo_Backups_Jobs::$phase_runner = null;
bk_assert( 'same_site' === ( $tick['code'] ?? '' ) && ! $ran, 'El origen en la caché sigue siendo un choque: ' . json_encode( $tick ) );
Digitalisimo_Backups_Job_Store::with_lock( function() use ( $restore ) { $job = Digitalisimo_Backups_Job_Store::read( $restore['id'] ); $job['status'] = 'cancelled'; $job['lease'] = null; $job['finished_at'] = time(); Digitalisimo_Backups_Job_Store::write( $job ); } );
// N1 · Una invalidación que quedó a medias sigue en el próximo tick hasta terminar.
$collide( false );
ss_call( 'Digitalisimo_Backups', 'cache_pending_save', array( array( 'do' => 'transients' ) ) );
bk_assert( Digitalisimo_Backups::cache_pending(), 'Lo pendiente se guarda en el almacén privado.' );
$GLOBALS['bk_cron'] = array();
Digitalisimo_Backups_Jobs::schedule();
bk_assert( in_array( Digitalisimo_Backups_Jobs::HOOK_TICK, $GLOBALS['bk_cron'], true ), 'Con invalidación pendiente, se programa un tick.' );
Digitalisimo_Backups_Jobs::tick( 0 );
bk_assert( ! Digitalisimo_Backups::cache_pending(), 'El tick siguiente termina la invalidación pendiente.' );
// F2 · Los archivos de la caché llevan la identidad de la instalación, y dos restauraciones no se pisan.
$tag = substr( Digitalisimo_Backups::installation_owner(), 0, 16 );
$pending_path = (string) ss_call( 'Digitalisimo_Backups', 'cache_pending_path' );
$before_a = (string) ss_call( 'Digitalisimo_Backups', 'cache_before_path' );
$before_b = (string) ss_call( 'Digitalisimo_Backups', 'cache_before_path' );
bk_assert( false !== strpos( basename( $pending_path ), $tag ) && false !== strpos( basename( $before_a ), $tag ) && $before_a !== $before_b, 'F2: los nombres llevan la identidad y cada instantánea es suya: ' . basename( $pending_path ) . ' ' . basename( $before_a ) . ' ' . basename( $before_b ) );
ss_call( 'Digitalisimo_Backups', 'cache_pending_save', array( array( 'do' => 'range', 'blog' => 0, 'groups' => array( 'users' ), 'next' => 5 ), array( 'do' => 'file', 'file' => $before_a, 'at' => 0 ) ) );
ss_call( 'Digitalisimo_Backups', 'cache_pending_save', array( array( 'do' => 'file', 'file' => $before_b, 'at' => 0 ), array( 'do' => 'range', 'blog' => 0, 'groups' => array( 'users' ), 'next' => 9 ) ), true );
$merged = json_decode( (string) file_get_contents( $pending_path ), true );
bk_assert( array( 'file', 'file', 'range', 'range' ) === array_column( (array) $merged['tasks'], 'do' ) && $before_a === $merged['tasks'][0]['file'] && $before_b === $merged['tasks'][1]['file'], 'F2: la segunda restauración junta su plan con el pendiente (las claves exactas de las dos antes que los rangos): ' . json_encode( $merged ) );
@unlink( $pending_path );
file_put_contents( dirname( $pending_path ) . '/.cache-pending.json', json_encode( array( 'v' => 1, 'tasks' => array( array( 'do' => 'transients' ) ) ) ) );
bk_assert( Digitalisimo_Backups::cache_pending() && is_file( $pending_path ) && ! is_file( dirname( $pending_path ) . '/.cache-pending.json' ), 'F2: lo pendiente de la versión anterior pasa al archivo con la identidad.' );
Digitalisimo_Backups::cache_pending_resume();
bk_assert( ! Digitalisimo_Backups::cache_pending(), 'Y se termina.' );
$collide( true );
// A1 · Lo que se repone tras restaurar se lee de la base, no de la caché.
$names = ss_call( 'Digitalisimo_Backups', 'keep_config_options' );
$name = (string) reset( $names );
$GLOBALS['wpdb']->rows[ $name ] = serialize( array( 'de' => 'la base' ) );
update_option( $name, array( 'de' => 'la caché de otro sitio' ) );
$snapshot = ss_call( 'Digitalisimo_Backups', 'keep_config_snapshot' );
bk_assert( array( 'de' => 'la base' ) === ( $snapshot['site'][ $name ]['value'] ?? null ), 'La foto de la configuración debe leerse de la base: ' . json_encode( $snapshot['site'][ $name ] ?? null ) );
$described = Digitalisimo_Backups_Relocate::describe( false );
bk_assert( 'https://ejemplo.test' === $described['home'] && 'https://ejemplo.test' === $described['siteurl'], 'describe() debe leer home/siteurl de la base, no de la caché: ' . $described['home'] );

// Sin choque vuelve a trabajar.
$collide( false );
$tick = Digitalisimo_Backups_Jobs::tick( 0 );
bk_assert( 'worked' === $tick['result'] && 0 < (int) bk_job( $first['id'] )['started_at'], 'Sin choque, tick() vuelve a tomar el trabajo: ' . json_encode( $tick ) );
Digitalisimo_Backups_Job_Store::with_lock( function() use ( $first ) { $job = Digitalisimo_Backups_Job_Store::read( $first['id'] ); $job['status'] = 'cancelled'; $job['lease'] = null; $job['finished_at'] = time(); Digitalisimo_Backups_Job_Store::write( $job ); } );

// M1 · Sin caché persistente, el tick se programa aunque site_url() venga de un filtro (mapeo de dominios).
$GLOBALS['ss_ext'] = false;
$GLOBALS['wpdb']->rows['home'] = $GLOBALS['wpdb']->rows['siteurl'] = 'https://interno.test';
$queued = bk_manual( 'local', 'mapeado' );
$GLOBALS['bk_cron'] = array();
Digitalisimo_Backups_Jobs::schedule();
bk_assert( in_array( Digitalisimo_Backups_Jobs::HOOK_TICK, $GLOBALS['bk_cron'], true ), 'Sin caché externa, schedule() debe programar el tick aunque site_url() no sea el de la base.' );
$GLOBALS['wpdb']->rows['home'] = $GLOBALS['wpdb']->rows['siteurl'] = 'https://ejemplo.test';
$GLOBALS['ss_ext'] = true;
Digitalisimo_Backups_Job_Store::with_lock( function() use ( $queued ) { $job = Digitalisimo_Backups_Job_Store::read( $queued['job']['id'] ?? $queued['id'] ); $job['status'] = 'cancelled'; $job['finished_at'] = time(); Digitalisimo_Backups_Job_Store::write( $job ); } );

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
// M2 · El propio respaldo hecho cuando el sitio estaba en http (o con www): misma instalación, no pide el dominio.
$id = (string) ss_call( 'Digitalisimo_Backups', 'installation_id' );
$root = (string) ss_call( 'Digitalisimo_Backups', 'journal_owner' );
$http = array( 'home_url' => 'http://www.ejemplo.test', 'origin' => array( 'home' => 'http://www.ejemplo.test' ), 'installation' => array( 'id' => hash( 'sha256', $id . "\n" . $root . "\n" . 'http://www.ejemplo.test' ), 'home' => 'http://www.ejemplo.test' ) );
bk_assert( '' === Digitalisimo_Backups::restore_origin_problem( '', $http, array() ), 'El propio respaldo de antes de pasar a https no debe pedir el dominio: ' . Digitalisimo_Backups::restore_origin_problem( '', $http, array() ) );
$stranger = array( 'origin' => array( 'home' => 'https://ejemplo.test' ), 'installation' => array( 'id' => str_repeat( 'b', 64 ) ) ) + $same;
bk_assert( '' !== Digitalisimo_Backups::restore_origin_problem( '', $stranger, array() ), 'Otra instalación con el mismo home también pide el dominio escrito.' );

echo "OK backups-same-site\n";
