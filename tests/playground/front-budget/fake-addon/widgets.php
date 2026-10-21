<?php
namespace Digi_Fb_Fake_Addon;

defined( 'ABSPATH' ) || exit;

/** Tarjeta de terceros: sin scripts propios; la puerta no sabe qué necesita y carga los paquetes por precaución. */
class Card extends \Elementor\Widget_Base {
	public function get_name() {
		return 'digi-fb-fake-card';
	}

	public function get_title() {
		return 'Tarjeta de prueba';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => 'Contenido' ) );
		$this->add_control( 'title', array( 'label' => 'Título', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Tarjeta' ) );
		$this->end_controls_section();
	}

	protected function render() {
		echo '<div class="digi-fb-fake-card">' . esc_html( (string) $this->get_settings_for_display( 'title' ) ) . '</div>';
	}
}

/** La misma tarjeta, declarada libre por el filtro de la puerta. */
class Free_Card extends Card {
	public function get_name() {
		return 'digi-fb-fake-free';
	}

	public function get_title() {
		return 'Tarjeta libre de prueba';
	}
}
