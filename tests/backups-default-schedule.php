<?php
/**
 * Laboratorio H8: la automatización «Completo mensual» viene activa por defecto, pero en un sitio donde nunca se guardó
 * la pantalla no tenía ningún evento de WP-Cron (`next_run` vacío), así que nunca se ejecutaba. Ahora toda automatización
 * activa sin evento se programa al cargar WordPress (a su hora fija o a las 03:00 del sitio), una sola vez, sólo en el
 * sitio principal de una red, sin tocar lo que ya estaba programado ni las automatizaciones desactivadas.
 */
require __DIR__ . '/backups-coverage/cron-env.php';

$B = 'Digitalisimo_Backups';
/** Una carga de WordPress: el plugin se inicia y corre lo que engancha en `init`. */
$load = function() use ( $B ) {
	$GLOBALS['actions'] = array();
	$B::init();
	foreach ( $GLOBALS['actions'] as list( $hook, $callback ) ) {
		if ( 'init' === $hook && is_array( $callback ) && 0 === strpos( (string) $callback[0], 'Digitalisimo_Backups' ) ) call_user_func( $callback );
	}
};
$local = function( $timestamp ) { return ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( new DateTimeZone( $GLOBALS['tz'] ) ); };
$policy_events = function() { return array_values( array_filter( $GLOBALS['cron'], function( $event ) { return 'digitalisimo_backups_policy' === $event['hook']; } ) ); };

// 1. Sitio nuevo: la automatización por defecto existe, está activa y, sin guardar nada, ya tiene su próxima ejecución.
$load();
$policies = $B::policies();
digi_assert( 1 === count( $policies ) && 'monthly-full' === $policies[0]['id'] && ! empty( $policies[0]['enabled'] ), 'La automatización por defecto no es la mensual activa.' );
$rows = $B::service_automations();
digi_assert( 'monthly-full' === $rows[0]['id'] && true === $rows[0]['enabled'] && '' !== $rows[0]['next_run'], 'La automatización mensual por defecto viene activa sin próxima ejecución: ' . json_encode( $rows[0] ) );
$events = $policy_events();
digi_assert( 1 === count( $events ) && 'monthly' === $events[0]['schedule'] && array( 'monthly-full', 1 ) === $events[0]['args'] && '03:00' === $local( $events[0]['timestamp'] )->format( 'H:i' ) && $events[0]['timestamp'] > time(), 'La mensual no quedó programada a las 03:00 del sitio: ' . json_encode( $events ) );

// 2. Cargas siguientes: nada se duplica ni se mueve.
$first = $events[0]['timestamp'];
$load();
$load();
digi_assert( 1 === count( $policy_events() ) && $first === $policy_events()[0]['timestamp'], 'Cada carga volvió a programar la automatización.' );

// 3. Con hora fija, a esa hora; desactivada o sin frecuencia, nunca; cada hora, en un minuto.
update_option( $B::POLICIES, array(
	array( 'id' => 'con-hora', 'type' => 'full', 'schedule' => 'weekly', 'destination' => 'local', 'retain' => 2, 'enabled' => true, 'time' => '21:45' ),
	array( 'id' => 'apagada', 'type' => 'full', 'schedule' => 'daily', 'destination' => 'local', 'retain' => 2, 'enabled' => false ),
	array( 'id' => 'sin-frecuencia', 'type' => 'full', 'schedule' => 'disabled', 'destination' => 'local', 'retain' => 2, 'enabled' => true ),
	array( 'id' => 'cada-hora', 'type' => 'full', 'schedule' => 'hourly', 'destination' => 'local', 'retain' => 2, 'enabled' => true ),
	array( 'id' => 'parcial', 'type' => 'incremental', 'schedule' => 'daily', 'destination' => 'local', 'retain' => 2, 'enabled' => true ),
) );
$GLOBALS['cron'] = array();
$load();
$by_id = array();
foreach ( $policy_events() as $event ) $by_id[ $event['args'][0] ] = $event;
$ids = array_keys( $by_id );
sort( $ids );
digi_assert( array( 'cada-hora', 'con-hora' ) === $ids, 'Se programaron automatizaciones desactivadas, sin frecuencia o parciales: ' . json_encode( array_keys( $by_id ) ) );
digi_assert( '21:45' === $local( $by_id['con-hora']['timestamp'] )->format( 'H:i' ) && 'weekly' === $by_id['con-hora']['schedule'] && abs( $by_id['cada-hora']['timestamp'] - ( time() + MINUTE_IN_SECONDS ) ) <= 5, 'La hora o la frecuencia de las automatizaciones programadas no es la esperada.' );
// Lo que ya estaba programado no se toca.
$GLOBALS['cron'] = array();
wp_schedule_event( time() + 12345, 'weekly', 'digitalisimo_backups_policy', array( 'con-hora', 1 ) );
$load();
digi_assert( time() + 12345 >= wp_next_scheduled( 'digitalisimo_backups_policy', array( 'con-hora', 1 ) ) && 2 === count( $policy_events() ), 'Se movió una automatización que ya tenía su evento.' );

// 4. Red: sólo el sitio principal programa; un subsitio nunca.
$GLOBALS['multisite'] = true;
$GLOBALS['main'] = false;
$GLOBALS['cron'] = array();
$load();
digi_assert( array() === $policy_events(), 'Un subsitio de la red programó una automatización.' );
$GLOBALS['main'] = true;
$load();
digi_assert( 2 === count( $policy_events() ), 'El sitio principal de la red no programó sus automatizaciones.' );

echo "DIGITALÍSIMO Backups: las automatizaciones activas sin evento (la mensual por defecto) quedan programadas, sin duplicar, sólo en el sitio principal.\n";
