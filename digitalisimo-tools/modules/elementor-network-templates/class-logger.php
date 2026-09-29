<?php
namespace Digitalisimo\Tools\Elementor_Network_Templates;
defined( 'ABSPATH' ) || exit;
final class Logger {
	const OPTION = 'digitalisimo_tools_network_template_log';
	public static function add( $uuid, $origin, $destination, $result, $duration = 0, $error = '' ) { $rows = (array) get_site_option( self::OPTION, array() ); array_unshift( $rows, array( 'date' => current_time( 'mysql' ), 'uuid' => sanitize_key( $uuid ), 'origin' => absint( $origin ), 'destination' => absint( $destination ), 'result' => sanitize_key( $result ), 'duration' => round( (float) $duration, 3 ), 'error' => sanitize_text_field( $error ) ) ); update_site_option( self::OPTION, array_slice( $rows, 0, max( 10, absint( Registry::settings()['log_limit'] ) ) ) ); }
	public static function all() { return (array) get_site_option( self::OPTION, array() ); }
}
