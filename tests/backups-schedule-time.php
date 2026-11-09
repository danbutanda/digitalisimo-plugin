<?php
/**
 * Lote corto · hora fija en las automatizaciones (Backuply la tiene).
 *
 * Una automatización diaria, semanal o mensual puede llevar una hora del sitio (HH:MM): su primera ejecución es la
 * próxima vez que el reloj del sitio marca esa hora y, como WP-Cron suma el intervalo en UTC, antes de cada ejecución
 * se realinea a la hora local (cambio de horario de verano, cambio de zona horaria). Sin hora todo sigue como antes, y
 * guardar una automatización ya no adelanta las demás. La hora sale en REST/WP-CLI (`time`) y en la pantalla.
 */
require __DIR__ . '/backups-coverage/cron-env.php';

$B = 'Digitalisimo_Backups';
$post = function( $handler, $action, array $post ) use ( $B ) {
	$_POST = $post;
	$_REQUEST = $post + array( '_wpnonce' => 'nonce-' . $action );
	try { call_user_func( array( $B, $handler ) ); } catch ( Digi_Redirect $redirect ) { return $redirect->query; }
	throw new RuntimeException( 'FALLA: ' . $handler . ' no redirigió.' );
};
$local = function( $timestamp ) { return ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( new DateTimeZone( $GLOBALS['tz'] ) ); };
$event = function( $id ) { return wp_get_scheduled_event( 'digitalisimo_backups_policy', array( $id, 1 ) ); };
$find = function( $schedule ) use ( $B ) { foreach ( $B::policies() as $policy ) if ( $schedule === $policy['schedule'] ) return $policy; return null; };
$at = function( $iso ) { return ( new DateTimeImmutable( $iso ) )->getTimestamp(); };

update_option( $B::POLICIES, array() );
// 1. Diaria a las 03:30 (hora del sitio): la primera ejecución es la próxima vez que el reloj del sitio marca 03:30.
$query = $post( 'create_automation', $B::ACTION_CREATE_AUTOMATION, array( 'digitalisimo_backup_automation' => array( 'type' => 'full', 'schedule' => 'daily', 'destination' => 'local', 'retain' => '4', 'time' => '03:30' ) ) );
digi_assert( 'ok' === $query['backup'], 'No se creó la automatización con hora: ' . json_encode( $query ) );
$daily = $find( 'daily' );
$first = $event( $daily['id'] );
digi_assert( $first && 'daily' === $first->schedule && '03:30' === $local( $first->timestamp )->format( 'H:i' ) && $first->timestamp > time() && $first->timestamp <= time() + DAY_IN_SECONDS + MINUTE_IN_SECONDS, 'La automatización diaria no quedó a las 03:30 del sitio: ' . json_encode( $first ) );
digi_assert( '03:30' === $daily['time'], 'La hora no se guardó en la automatización.' );

// 2. Sin hora, como hasta ahora: en un minuto. Guardar una automatización no adelanta las demás.
$post( 'create_automation', $B::ACTION_CREATE_AUTOMATION, array( 'digitalisimo_backup_automation' => array( 'type' => 'full', 'schedule' => 'weekly', 'destination' => 'local', 'retain' => '2' ) ) );
$weekly = $find( 'weekly' );
$untimed = $event( $weekly['id'] );
digi_assert( $untimed && 'weekly' === $untimed->schedule && abs( $untimed->timestamp - ( time() + MINUTE_IN_SECONDS ) ) <= 5 && '' === $weekly['time'], 'Una automatización sin hora no se programó como antes: ' . json_encode( $untimed ) );
digi_assert( $first->timestamp === $event( $daily['id'] )->timestamp, 'Crear otra automatización movió la que tiene hora.' );
// La semanal ya corrió y su próxima ejecución está dentro de cinco días: guardar la diaria no la adelanta a «ahora».
wp_clear_scheduled_hook( 'digitalisimo_backups_policy', array( $weekly['id'], 1 ) );
$later = time() + 5 * DAY_IN_SECONDS;
wp_schedule_event( $later, 'weekly', 'digitalisimo_backups_policy', array( $weekly['id'], 1 ) );
$query = $post( 'update_automation', $B::ACTION_UPDATE_AUTOMATION, array( 'policy_id' => $daily['id'], 'digitalisimo_backup_automation' => array( 'enabled' => '1', 'schedule' => 'daily', 'destination' => 'local', 'retain' => '4', 'time' => '03:30' ) ) );
digi_assert( 'ok' === $query['backup'] && $later === $event( $weekly['id'] )->timestamp && $first->timestamp === $event( $daily['id'] )->timestamp, 'Guardar una automatización sin cambios movió alguna programación.' );

// 3. Cambiar la hora la mueve a la próxima vez que el reloj marca la nueva hora; una hora no válida no cambia nada.
$post( 'update_automation', $B::ACTION_UPDATE_AUTOMATION, array( 'policy_id' => $daily['id'], 'digitalisimo_backup_automation' => array( 'enabled' => '1', 'schedule' => 'daily', 'destination' => 'local', 'retain' => '4', 'time' => '22:15' ) ) );
$moved = $event( $daily['id'] );
digi_assert( '22:15' === $local( $moved->timestamp )->format( 'H:i' ) && $moved->timestamp > time() && $moved->timestamp <= time() + DAY_IN_SECONDS + MINUTE_IN_SECONDS && $later === $event( $weekly['id'] )->timestamp, 'Cambiar la hora no movió la automatización a las 22:15: ' . json_encode( $moved ) );
foreach ( array( '25:00', '7:5', '03:30; DROP', '12:60' ) as $bad ) {
	$query = $post( 'update_automation', $B::ACTION_UPDATE_AUTOMATION, array( 'policy_id' => $daily['id'], 'digitalisimo_backup_automation' => array( 'enabled' => '1', 'schedule' => 'daily', 'destination' => 'local', 'retain' => '4', 'time' => $bad ) ) );
	digi_assert( 'error' === $query['backup'] && '22:15' === $B::policy( $daily['id'] )['time'] && $moved->timestamp === $event( $daily['id'] )->timestamp, 'Una hora no válida (' . $bad . ') se aceptó o movió la programación.' );
}
// Cada hora no usa hora fija: se ejecuta como antes.
$post( 'create_automation', $B::ACTION_CREATE_AUTOMATION, array( 'digitalisimo_backup_automation' => array( 'type' => 'full', 'schedule' => 'hourly', 'destination' => 'local', 'retain' => '3', 'time' => '05:00' ) ) );
$hourly = $find( 'hourly' );
digi_assert( abs( $event( $hourly['id'] )->timestamp - ( time() + MINUTE_IN_SECONDS ) ) <= 5, 'Una automatización cada hora usó la hora fija.' );

// 4. REST y WP-CLI (mismo caso de uso) y la pantalla muestran la hora.
$rows = array();
foreach ( $B::service_automations() as $row ) $rows[ $row['id'] ] = $row;
digi_assert( '22:15' === $rows[ $daily['id'] ]['time'] && '' === $rows[ $weekly['id'] ]['time'] && '' === $rows[ $hourly['id'] ]['time'] && gmdate( 'c', $moved->timestamp ) === $rows[ $daily['id'] ]['next_run'], 'Las automatizaciones no informan su hora: ' . json_encode( $rows ) );
$fields = new ReflectionMethod( $B, 'automation_fields' );
if ( PHP_VERSION_ID < 80100 ) $fields->setAccessible( true );
ob_start();
$fields->invoke( null );
$html = (string) ob_get_clean();
digi_assert( false !== strpos( $html, 'Diario a las 22:15' ) && false !== strpos( $html, 'name="digitalisimo_backup_automation[time]" value="22:15"' ) && false !== strpos( $html, 'America/Mexico_City' ), 'La pantalla no muestra la hora de la automatización.' );

// 5. Horario de verano (Europe/Madrid): WP-Cron suma 24 h en UTC y la hora local se desplaza; el realineado la devuelve.
$GLOBALS['tz'] = 'Europe/Madrid';
$cases = array(
	// [frecuencia, hora, ejecución en curso (UTC), lo que WP-Cron dejó programado (UTC), lo esperado (UTC)]
	array( 'daily', '03:00', '2026-10-24T01:00:20Z', '2026-10-25T01:00:00Z', '2026-10-25T02:00:00Z' ), // fin del verano: 02:00 → 03:00 CET
	array( 'daily', '03:00', '2026-03-28T02:00:20Z', '2026-03-29T02:00:00Z', '2026-03-29T01:00:00Z' ), // inicio: 04:00 → 03:00 CEST
	array( 'weekly', '03:00', '2026-10-21T01:00:20Z', '2026-10-28T01:00:00Z', '2026-10-28T02:00:00Z' ), // el mismo miércoles
	array( 'monthly', '03:00', '2026-10-10T01:00:20Z', '2026-11-09T01:00:00Z', '2026-11-09T02:00:00Z' ),
	array( 'daily', '03:00', '2026-07-10T01:00:20Z', '2026-07-11T01:00:00Z', '2026-07-11T01:00:00Z' ), // sin cambio de hora: nada que mover
);
foreach ( $cases as $i => list( $schedule, $time, $now, $wp_next, $expected ) ) {
	$id = 'automation-dst-' . $i;
	update_option( $B::POLICIES, array( array( 'id' => $id, 'label' => '', 'type' => 'full', 'schedule' => $schedule, 'destination' => 'local', 'retain' => 4, 'enabled' => true, 'time' => $time ) ) );
	$GLOBALS['cron'] = array();
	wp_schedule_event( $at( $wp_next ), $schedule, 'digitalisimo_backups_policy', array( $id, 1 ) );
	$B::realign_policy( $id, 1, $at( $now ) );
	$next = $event( $id );
	digi_assert( $next && $at( $expected ) === $next->timestamp && $schedule === $next->schedule && 1 === count( $GLOBALS['cron'] ), 'El realineado (' . $schedule . ', ' . $now . ') no dejó la ejecución a las ' . $time . ' locales: ' . json_encode( $next ? gmdate( 'c', $next->timestamp ) : null ) );
}
// Sin hora fija, el realineado no toca nada.
update_option( $B::POLICIES, array( array( 'id' => 'sin-hora', 'type' => 'full', 'schedule' => 'daily', 'destination' => 'local', 'retain' => 4, 'enabled' => true ) ) );
$GLOBALS['cron'] = array();
wp_schedule_event( $at( '2026-10-25T01:00:00Z' ), 'daily', 'digitalisimo_backups_policy', array( 'sin-hora', 1 ) );
$B::realign_policy( 'sin-hora', 1, $at( '2026-10-24T01:00:20Z' ) );
digi_assert( $at( '2026-10-25T01:00:00Z' ) === $event( 'sin-hora' )->timestamp, 'El realineado movió una automatización sin hora fija.' );
// Una hora que no existe ese día (el salto de primavera) pasa a la siguiente que sí.
digi_assert( '03:30' === $local( $B::next_fixed_run( '02:30', $at( '2026-03-28T23:00:00Z' ) ) )->format( 'H:i' ), 'Una hora inexistente por el horario de verano no pasó a la siguiente.' );

// 6. Cambiar la zona horaria del sitio mueve las automatizaciones con hora a su hora local; las demás no se tocan.
$GLOBALS['tz'] = 'America/Mexico_City';
update_option( $B::POLICIES, array(
	array( 'id' => 'con-hora', 'type' => 'full', 'schedule' => 'daily', 'destination' => 'local', 'retain' => 4, 'enabled' => true, 'time' => '04:00' ),
	array( 'id' => 'sin-hora', 'type' => 'full', 'schedule' => 'weekly', 'destination' => 'local', 'retain' => 4, 'enabled' => true ),
) );
$GLOBALS['cron'] = array();
$timed = $B::next_fixed_run( '04:00', time() + 60 );
wp_schedule_event( $timed, 'daily', 'digitalisimo_backups_policy', array( 'con-hora', 1 ) );
wp_schedule_event( $later, 'weekly', 'digitalisimo_backups_policy', array( 'sin-hora', 1 ) );
$GLOBALS['tz'] = 'Asia/Tokyo';
$B::timezone_changed();
digi_assert( '04:00' === $local( $event( 'con-hora' )->timestamp )->format( 'H:i' ) && $timed !== $event( 'con-hora' )->timestamp && $later === $event( 'sin-hora' )->timestamp, 'Cambiar la zona horaria no llevó la automatización a las 04:00 locales o movió otra.' );

// 7. El realineado corre antes de cada ejecución del evento, y los cambios de zona horaria lo vuelven a programar.
$GLOBALS['actions'] = array();
$B::init();
$hooks = array();
foreach ( $GLOBALS['actions'] as list( $hook, $callback, $priority ) ) if ( is_array( $callback ) && $B === $callback[0] ) $hooks[ $hook . '@' . $callback[1] ] = $priority;
digi_assert( 5 === ( $hooks['digitalisimo_backups_policy@realign_policy'] ?? null ) && 10 === ( $hooks['digitalisimo_backups_policy@scheduled_policy'] ?? null ) && isset( $hooks['update_option_timezone_string@timezone_changed'], $hooks['update_option_gmt_offset@timezone_changed'] ), 'El realineado no está enganchado antes de la ejecución o el cambio de zona horaria no reprograma: ' . json_encode( $hooks ) );

echo "DIGITALÍSIMO Backups: hora fija del sitio en las automatizaciones diaria, semanal y mensual, realineada con el horario de verano y sin mover las demás.\n";
