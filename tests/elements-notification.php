<?php
namespace Elementor { class Widget_Base { public $settings = array(); public function get_settings_for_display() { return $this->settings; } } }
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_notification( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	function wp_kses_post( $v ) { return strip_tags( (string) $v, '<p><strong><a>' ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-notification.php';
	$widget = new \Digitalisimo\Elements\Notification_Widget();
	check_notification( array( 'digitalisimo-notification' ) === $widget->get_script_depends(), 'Debe cargar sólo su script condicional.' );
	$widget->settings = array( 'notification_type' => 'fixed', 'notification_event' => 'click', 'notification_selector' => '#aviso', 'notification_in_delay' => 500, 'notification_timeout' => 9000, 'notification_pos_fixed' => 'top', 'notification_content' => '<p>Oferta <script>x</script><strong>vigente</strong></p>' );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $html = ob_get_clean();
	check_notification( str_contains( $html, 'digi-notification--fixed digi-notification--top' ) && str_contains( $html, 'data-event="click"' ) && str_contains( $html, 'data-selector="#aviso"' ), 'La barra conserva evento, activador y posición.' );
	check_notification( str_contains( $html, 'data-timeout="9000"' ) && ! str_contains( $html, '<script>' ) && str_contains( $html, '<strong>vigente</strong>' ), 'Debe sanear contenido y conservar formato permitido.' );
	$widget->settings = array( 'notification_content' => 'Aviso', 'notification_event' => 'click', 'notification_selector' => 'body script', 'notification_timeout' => 999999 );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $safe = ob_get_clean();
	check_notification( str_contains( $safe, 'data-event="onload"' ) && str_contains( $safe, 'data-timeout="60000"' ) && ! str_contains( $safe, 'body script' ), 'Un selector inválido no debe ejecutarse y los tiempos deben limitarse.' );
	$widget->settings = array( 'notification_content' => '' );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $empty = ob_get_clean();
	check_notification( '' === $empty, 'No debe pintar una notificación vacía.' );
	echo "DIGITALÍSIMO Elements: notificación saneada e independiente.\n";
}
