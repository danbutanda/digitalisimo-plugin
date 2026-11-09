<?php
/**
 * WordPress mínimo con WP-Cron de verdad (eventos con sus argumentos y su frecuencia), la zona horaria del sitio
 * (`$GLOBALS['tz']`), opciones en memoria y los handlers admin-post de la pantalla. Lo usan las pruebas de la
 * programación de las automatizaciones (tests/backups-schedule-time.php y tests/backups-default-schedule.php).
 * `$GLOBALS['multisite']` y `$GLOBALS['main']` simulan una red y un subsitio.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
class WP_Error {
	private $code; private $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return array(); }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class Digi_Redirect extends Exception { public $query = array(); public function __construct( $url ) { parent::__construct( $url ); parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $this->query ); } }
$GLOBALS['options'] = array();
$GLOBALS['cron'] = array();
$GLOBALS['actions'] = array();
$GLOBALS['tz'] = 'America/Mexico_City';
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function add_option( $key, $value = '', $deprecated = '', $autoload = null ) { if ( ! array_key_exists( $key, $GLOBALS['options'] ) ) $GLOBALS['options'][ $key ] = $value; return true; }
function get_site_option( $key, $default = false ) { return $default; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }
function is_multisite() { return ! empty( $GLOBALS['multisite'] ); }
function is_main_site() { return ! isset( $GLOBALS['main'] ) || $GLOBALS['main']; }
function is_network_admin() { return false; }
function get_current_blog_id() { return is_main_site() ? 1 : 2; }
function get_main_site_id() { return 1; }
function current_user_can( $capability ) { return ( is_multisite() ? 'manage_network_options' : 'manage_options' ) === $capability; }
function check_admin_referer( $action ) { if ( ( $_REQUEST['_wpnonce'] ?? '' ) !== 'nonce-' . $action ) throw new RuntimeException( 'FALLA: nonce inválido para ' . $action ); return 1; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return $value; }
function wp_generate_uuid4() { return sprintf( '%08x-%04x-4%03x-8%03x-%012x', random_int( 0, 0xffffffff ), random_int( 0, 0xffff ), random_int( 0, 0xfff ), random_int( 0, 0xfff ), random_int( 0, 0xffffffffffff ) ); }
function wp_get_referer() { return ''; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function network_admin_url( $path = '' ) { return 'https://example.test/wp-admin/network/' . $path; }
function add_query_arg( $args, $url ) { $pairs = array(); foreach ( (array) $args as $key => $value ) $pairs[] = $key . '=' . $value; return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $pairs ); }
function wp_safe_redirect( $url ) { throw new Digi_Redirect( $url ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">'; }
function selected( $a, $b, $echo = true ) { return (string) $a === (string) $b ? ' selected="selected"' : ''; }
function checked( $a, $b = true, $echo = true ) { return (string) $a === (string) $b ? ' checked="checked"' : ''; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['actions'][] = array( $hook, $callback, $priority, $args ); return true; }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { return add_action( $hook, $callback, $priority, $args ); }
function wp_timezone() { return new DateTimeZone( $GLOBALS['tz'] ); }
function wp_timezone_string() { return $GLOBALS['tz']; }
// WP-Cron con argumentos y frecuencia, como el de WordPress.
function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) { $GLOBALS['cron'][] = array( 'timestamp' => (int) $timestamp, 'schedule' => $recurrence, 'hook' => $hook, 'args' => array_values( $args ) ); return true; }
function wp_get_scheduled_event( $hook, $args = array() ) { foreach ( $GLOBALS['cron'] as $event ) if ( $hook === $event['hook'] && array_values( $args ) === $event['args'] ) return (object) $event; return false; }
function wp_next_scheduled( $hook, $args = array() ) { $event = wp_get_scheduled_event( $hook, $args ); return $event ? $event->timestamp : false; }
function wp_unschedule_event( $timestamp, $hook, $args = array() ) { $GLOBALS['cron'] = array_values( array_filter( $GLOBALS['cron'], function( $event ) use ( $timestamp, $hook, $args ) { return ! ( $hook === $event['hook'] && (int) $timestamp === $event['timestamp'] && array_values( $args ) === $event['args'] ); } ) ); return true; }
function wp_clear_scheduled_hook( $hook, $args = array() ) { $GLOBALS['cron'] = array_values( array_filter( $GLOBALS['cron'], function( $event ) use ( $hook, $args ) { return ! ( $hook === $event['hook'] && array_values( $args ) === $event['args'] ); } ) ); return 0; }
require __DIR__ . '/../../digitalisimo-backups/includes/class-backups.php';

function digi_assert( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( 'FALLA: ' . $message ); }
