<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Acceso al registro nativo; WordPress conserva validación, correo y plugins de seguridad. */
class User_Register_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-user-register'; }
	public function get_title() { return 'Registro de usuarios'; }
	public function get_icon() { return 'eicon-user-circle-o'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'registro', 'usuario', 'cuenta', 'signup' ); }
	public function get_style_depends() { return array( 'digitalisimo-user-register' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_registration', array( 'label' => 'Registro' ) );
		$this->add_control( 'description', array( 'label' => 'Descripción', 'type' => $c::TEXT, 'default' => 'Crea tu cuenta para continuar.', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'button_text', array( 'label' => 'Texto del botón', 'type' => $c::TEXT, 'default' => 'Crear cuenta', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'note', array( 'type' => $c::RAW_HTML, 'raw' => 'Abre el registro nativo de WordPress. La red o el sitio controlan si se permiten nuevas cuentas.' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'button_color', array( 'label' => 'Color del botón', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-user-register__link' => 'background-color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	private static function registration_enabled() {
		return is_multisite()
			? in_array( get_site_option( 'registration' ), array( 'user', 'all' ), true )
			: (bool) get_option( 'users_can_register' );
	}

	protected function render() {
		if ( is_user_logged_in() || ! self::registration_enabled() ) { return; }
		$url = wp_registration_url();
		if ( ! is_string( $url ) || '' === $url ) { return; }
		$settings = $this->get_settings_for_display();
		$description = trim( wp_strip_all_tags( (string) ( $settings['description'] ?? '' ) ) );
		$button = trim( wp_strip_all_tags( (string) ( $settings['button_text'] ?? '' ) ) );
		if ( '' === $button ) { $button = 'Crear cuenta'; }
		echo '<div class="digi-user-register">';
		if ( '' !== $description ) { echo '<p class="digi-user-register__description">' . esc_html( $description ) . '</p>'; }
		echo '<a class="digi-user-register__link" href="' . esc_url( $url ) . '">' . esc_html( $button ) . '</a></div>';
	}

	protected function content_template() {
		?><div class="digi-user-register"><# if (settings.description) { #><p class="digi-user-register__description">{{ settings.description }}</p><# } #><span class="digi-user-register__link">{{ settings.button_text || 'Crear cuenta' }}</span></div><?php
	}
}
