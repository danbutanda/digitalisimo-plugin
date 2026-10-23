<?php
namespace Digitalisimo\Elements\Woo\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * «Barra de envío gratis»: cuánto falta para el envío gratis o que ya lo tiene, con las tarifas reales de WooCommerce
 * (V.7.1). Sin control de umbral: el umbral es el del método «Envío gratuito» de la zona del cliente. El HTML de la
 * página es neutro y oculto; el estado llega por los fragmentos del carrito. Si no se puede saber, no se muestra nada.
 */
class Shipping_Bar_Widget extends \Digitalisimo\Elements\Woo\Widget {
	public function get_name() { return 'digitalisimo-woo-shipping-bar'; }
	public function get_title() { return 'Barra de envío gratis'; }
	public function get_icon() { return 'eicon-progress-tracker'; }
	public function get_keywords() { return array( 'envío', 'gratis', 'carrito', 'barra', 'woocommerce' ); }
	public function get_style_depends() { return array( 'digitalisimo-woo-cart-extras' ); }
	public function get_script_depends() { return array( 'digitalisimo-woo-cart-extras' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_content', array( 'label' => 'Barra de envío gratis' ) );
		$this->add_control( 'text_progress', array( 'label' => 'Texto con lo que falta', 'type' => $c::TEXT, 'default' => Markup::T_PROGRESS, 'label_block' => true, 'description' => '{restante} es lo que falta y {umbral}, el mínimo del envío gratuito.' ) );
		$this->add_control( 'text_done', array( 'label' => 'Texto con envío gratis', 'type' => $c::TEXT, 'default' => Markup::T_DONE, 'label_block' => true ) );
		$this->add_control( 'help', array( 'type' => $c::RAW_HTML, 'raw' => 'Sólo se muestra cuando WooCommerce ya ofrece el envío gratis o cuando puede saberse cuánto falta: destino conocido, un solo paquete y el método «Envío gratuito» con un importe mínimo. En cualquier otro caso no aparece.', 'content_classes' => 'elementor-descriptor' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style', array( 'label' => 'Barra', 'tab' => $c::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'typography', 'selector' => '{{WRAPPER}} .digi-ship__text' ) );
		$this->add_control( 'text_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-ship__text' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'bar_color', array( 'label' => 'Color de la barra', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-ship' => '--digi-ship-fill: {{VALUE}};' ) ) );
		$this->add_control( 'track_color', array( 'label' => 'Fondo de la barra', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-ship' => '--digi-ship-track: {{VALUE}};' ) ) );
		$this->add_control( 'bar_height', array( 'label' => 'Grosor', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 2, 'max' => 24 ) ), 'selectors' => array( '{{WRAPPER}} .digi-ship' => '--digi-ship-height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'bar_radius', array( 'label' => 'Radio', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 24 ) ), 'selectors' => array( '{{WRAPPER}} .digi-ship' => '--digi-ship-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render_widget() {
		$s      = $this->get_settings_for_display();
		$editor = isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
		echo Markup::bar( (string) ( $s['text_progress'] ?? '' ), (string) ( $s['text_done'] ?? '' ), $editor ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
