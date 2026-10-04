<?php
/** Las políticas parciales heredadas nunca vuelven a programarse ni a ejecutarse. */
define( 'ABSPATH', __DIR__ );
class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
$GLOBALS['backups_test_options'] = array( 'digitalisimo_backups_policies' => array(
	array( 'id' => 'old-incremental', 'label' => 'Anterior', 'type' => 'incremental', 'enabled' => true, 'schedule' => 'daily' ),
	array( 'id' => 'old-database', 'label' => 'Anterior DB', 'type' => 'database', 'enabled' => true, 'schedule' => 'daily' ),
) );
$GLOBALS['backups_test_cleared'] = array();
function get_option( $key, $default = false ) { return $GLOBALS['backups_test_options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['backups_test_options'][ $key ] = $value; return true; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function wp_clear_scheduled_hook( $hook, $args = array() ) { $GLOBALS['backups_test_cleared'][] = array( $hook, $args ); }
function get_current_blog_id() { return 1; }
function is_multisite() { return false; }
require __DIR__ . '/../digitalisimo-backups/includes/class-backups.php';
Digitalisimo_Backups::activate_default_policies();
if ( 2 !== count( $GLOBALS['backups_test_cleared'] ) ) throw new RuntimeException( 'Las tareas parciales anteriores siguen programadas.' );
foreach ( Digitalisimo_Backups::policies() as $policy ) {
	if ( $policy['enabled'] || 'disabled' !== $policy['schedule'] ) throw new RuntimeException( 'Una política parcial sigue activa.' );
	if ( ! Digitalisimo_Backups::run_policy( $policy['id'] ) instanceof WP_Error ) throw new RuntimeException( 'Se ejecutó una política parcial.' );
}
Digitalisimo_Backups::activate_default_policies();
if ( 2 !== count( $GLOBALS['backups_test_cleared'] ) ) throw new RuntimeException( 'La migración vuelve a modificar cron en cada petición.' );
echo "DIGITALÍSIMO Backups: políticas parciales anteriores desactivadas.\n";
