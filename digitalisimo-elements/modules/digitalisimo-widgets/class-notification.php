<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Aviso accesible con activación puntual, sin infraestructura global de ventanas. */
class Notification_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-notification'; }
	public function get_title() { return 'Notificación'; }
	public function get_icon() { return 'eicon-alert'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'notificación', 'aviso', 'popup', 'alerta' ); }
	public function get_style_depends() { return array( 'digitalisimo-notification' ); }
	public function get_script_depends() { return array( 'digitalisimo-notification' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_notification', array( 'label' => 'Notificación' ) );
		$this->add_control( 'notification_type', array( 'label' => 'Presentación', 'type' => $c::SELECT, 'default' => 'popup', 'options' => array( 'popup' => 'Tarjeta flotante', 'fixed' => 'Barra fija' ) ) );
		$this->add_control( 'notification_event', array( 'label' => 'Mostrar', 'type' => $c::SELECT, 'default' => 'onload', 'options' => array( 'onload' => 'Al cargar', 'click' => 'Al hacer clic', 'mouseover' => 'Al pasar el cursor', 'inDelay' => 'Después de una pausa' ) ) );
		$this->add_control( 'notification_selector', array( 'label' => 'ID o clase del activador', 'type' => $c::TEXT, 'placeholder' => '#boton o .boton', 'condition' => array( 'notification_event' => array( 'click', 'mouseover' ) ) ) );
		$this->add_control( 'notification_in_delay', array( 'label' => 'Demora en milisegundos', 'type' => $c::NUMBER, 'default' => 500, 'min' => 0, 'max' => 30000, 'condition' => array( 'notification_event' => 'inDelay' ) ) );
		$this->add_control( 'notification_timeout', array( 'label' => 'Cerrar automáticamente (ms; 0 = nunca)', 'type' => $c::NUMBER, 'default' => 0, 'min' => 0, 'max' => 60000 ) );
		$this->add_control( 'notification_position', array( 'label' => 'Posición', 'type' => $c::SELECT, 'default' => 'bottom-right', 'options' => array( 'top-left' => 'Arriba izquierda', 'top-right' => 'Arriba derecha', 'bottom-left' => 'Abajo izquierda', 'bottom-right' => 'Abajo derecha' ), 'condition' => array( 'notification_type' => 'popup' ) ) );
		$this->add_control( 'notification_pos_fixed', array( 'label' => 'Ubicación de barra', 'type' => $c::SELECT, 'default' => 'bottom', 'options' => array( 'top' => 'Arriba', 'bottom' => 'Abajo' ), 'condition' => array( 'notification_type' => 'fixed' ) ) );
		$this->add_control( 'notification_content', array( 'label' => 'Contenido', 'type' => $c::WYSIWYG, 'default' => 'Escribe tu aviso.', 'dynamic' => array( 'active' => true ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'background_color', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-notification__panel' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'text_color', array( 'label' => 'Texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-notification__panel' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$content = wp_kses_post( (string) ( $s['notification_content'] ?? '' ) );
		if ( '' === trim( wp_strip_all_tags( $content ) ) ) { return; }
		$type = 'fixed' === ( $s['notification_type'] ?? '' ) ? 'fixed' : 'popup';
		$allowed_events = array( 'onload', 'click', 'mouseover', 'inDelay' );
		$event = in_array( $s['notification_event'] ?? '', $allowed_events, true ) ? $s['notification_event'] : 'onload';
		$selector = trim( (string) ( $s['notification_selector'] ?? '' ) );
		if ( in_array( $event, array( 'click', 'mouseover' ), true ) && 1 !== preg_match( '/^[#.][A-Za-z][A-Za-z0-9_-]{0,80}$/D', $selector ) ) { $event = 'onload'; $selector = ''; }
		$positions = array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' );
		$position = 'fixed' === $type ? ( 'top' === ( $s['notification_pos_fixed'] ?? '' ) ? 'top' : 'bottom' ) : ( in_array( $s['notification_position'] ?? '', $positions, true ) ? $s['notification_position'] : 'bottom-right' );
		$delay = max( 0, min( 30000, absint( $s['notification_in_delay'] ?? 500 ) ) );
		$timeout = max( 0, min( 60000, absint( $s['notification_timeout'] ?? 0 ) ) );
		echo '<aside class="digi-notification digi-notification--' . esc_attr( $type ) . ' digi-notification--' . esc_attr( $position ) . '" data-digi-notification data-event="' . esc_attr( $event ) . '" data-selector="' . esc_attr( $selector ) . '" data-delay="' . (int) $delay . '" data-timeout="' . (int) $timeout . '" hidden>';
		echo '<div class="digi-notification__panel" role="status" aria-live="polite"><div class="digi-notification__content">' . $content . '</div><button type="button" class="digi-notification__close" aria-label="Cerrar notificación">×</button></div></aside>';
	}

	protected function content_template() {
		?>
		<# var type = settings.notification_type === 'fixed' ? 'fixed' : 'popup'; var place = type === 'fixed' ? (settings.notification_pos_fixed || 'bottom') : (settings.notification_position || 'bottom-right'); #>
		<aside class="digi-notification digi-notification--{{ type }} digi-notification--{{ place }}"><div class="digi-notification__panel" role="status"><div class="digi-notification__content">{{{ settings.notification_content }}}</div><button type="button" class="digi-notification__close" aria-label="Cerrar notificación">×</button></div></aside>
		<?php
	}
}
