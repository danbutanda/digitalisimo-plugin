<?php
/**
 * Auditoría de marca: ejecuta los eventos programados de los plugins (y la telemetría del núcleo aunque no esté
 * programada) como lo haría wp-cron, para que probe-http.php registre lo que cada uno pide al servidor.
 * Los eventos de WordPress (actualizaciones, limpieza, salud del sitio) no se ejecutan: no hablan con los plugins
 * de origen y algunos harían peticiones de vuelta al propio sitio, que con un solo worker se bloquean.
 * run.cjs lo pide por HTTP al terminar cada idioma; devuelve JSON con lo ejecutado.
 */
define( 'DOING_CRON', true );
require '/wordpress/wp-load.php';
wp_set_current_user( 0 );

function digi_cron_from_plugin( $hook ) {
	global $wp_filter;
	if ( empty( $wp_filter[ $hook ] ) ) {
		return false;
	}
	foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
		foreach ( $callbacks as $cb ) {
			$fn = $cb['function'];
			try {
				if ( is_string( $fn ) && false !== strpos( $fn, '::' ) ) {
					$fn = explode( '::', $fn, 2 );
				}
				$ref = is_array( $fn ) ? new ReflectionMethod( $fn[0], $fn[1] ) : new ReflectionFunction( $fn );
				if ( preg_match( '#/wp-content/plugins/#', (string) $ref->getFileName() ) ) {
					return true;
				}
			} catch ( Throwable $e ) {
				continue;
			}
		}
	}
	return false;
}

$ran     = array();
$skipped = array();
$errors  = array();
$run     = static function ( $hook, $args ) use ( &$ran, &$errors ) {
	$GLOBALS['digi_cron_hook'] = $hook;
	try {
		do_action_ref_array( $hook, (array) $args );
		$ran[] = $hook;
	} catch ( Throwable $e ) {
		$errors[] = $hook . ': ' . $e->getMessage();
	}
	$GLOBALS['digi_cron_hook'] = '';
};
foreach ( (array) _get_cron_array() as $events ) {
	foreach ( (array) $events as $hook => $instances ) {
		foreach ( (array) $instances as $event ) {
			if ( digi_cron_from_plugin( $hook ) ) {
				$run( $hook, $event['args'] ?? array() );
			} else {
				$skipped[] = $hook;
			}
		}
	}
}
// La telemetría del núcleo se programa a diario: se fuerza aunque aún no figure en la lista.
foreach ( array( 'elementor/tracker/send_event' ) as $hook ) {
	if ( ! in_array( $hook, $ran, true ) ) {
		$run( $hook, array() );
	}
}
header( 'Content-Type: application/json' );
echo wp_json_encode( array( 'ran' => array_values( array_unique( $ran ) ), 'skipped' => array_values( array_unique( $skipped ) ), 'errors' => $errors ) );
