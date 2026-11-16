<?php
/**
 * mu-plugin del ensayo backups-network-to-single.sh (viaja con la red y lo tiene el destino): sin loopback (un servidor
 * de un solo proceso se esperaría a sí mismo) ni correo; y, sólo con /digi-probe/falla (que el ensayo crea en el destino),
 * una falla después del cambio: en la fase «finish» de una restauración que no es la vuelta a la copia previa, guardar el
 * informe de la reubicación falla una vez (un intento por fase y sin espera), y la restauración vuelve sola a la copia
 * previa. La falla se apaga al dispararse.
 */
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$host = (string) parse_url( (string) $url, PHP_URL_HOST );
	if ( ! in_array( $host, array( parse_url( home_url(), PHP_URL_HOST ), '127.0.0.1', 'localhost' ), true ) ) return $pre;
	return new WP_Error( 'digi_no_loopback', 'Loopback cortado en el ensayo.' );
}, 10, 3 );
add_filter( 'pre_wp_mail', '__return_true' );
if ( is_file( '/digi-probe/falla' ) ) {
	add_filter( 'digitalisimo_backups_job_max_attempts', static function () { return 1; }, 99 );
	add_filter( 'digitalisimo_backups_job_backoff', static function () { return 0; }, 99 );
	add_filter( 'pre_update_option_digitalisimo_backups_last_relocation', static function ( $value ) {
		if ( ! class_exists( 'Digitalisimo_Backups_Jobs' ) || ! is_file( '/digi-probe/falla' ) ) return $value;
		$job = Digitalisimo_Backups_Jobs::get( Digitalisimo_Backups_Jobs::current_id() );
		if ( ! $job || 'restore' !== ( $job['kind'] ?? '' ) || ! empty( $job['spec']['rollback'] ) ) return $value;
		@unlink( '/digi-probe/falla' );
		file_put_contents( '/digi-probe/falla-hecha', (string) $job['id'] );
		throw new RuntimeException( 'falla simulada después del cambio (disco lleno)' );
	} );
}
