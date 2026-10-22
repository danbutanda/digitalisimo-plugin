<?php
namespace Digitalisimo\Elements\Woo\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * «Botón de lista de deseos»: guarda el producto del bucle o de la ficha (o uno elegido), o enlaza con la página de la
 * lista y su contador. El HTML es igual para todos los visitantes; el estado llega en el navegador.
 */
class Button_Widget extends \Digitalisimo\Elements\Woo\Widget {
	public function get_name() { return 'digitalisimo-woo-wishlist-button'; }
	public function get_title() { return 'Botón de lista de deseos'; }
	public function get_icon() { return 'eicon-heart-o'; }
	public function get_keywords() { return array( 'lista de deseos', 'favoritos', 'guardar', 'woocommerce', 'producto' ); }
	public function get_style_depends() { return array( 'digitalisimo-woo-wishlist' ); }
	public function get_script_depends() { return array( 'digitalisimo-woo-wishlist' ); }

	protected function register_controls() {
		$c     = '\\Elementor\\Controls_Manager';
		$query = class_exists( '\\ElementorPro\\Modules\\QueryControl\\Module' ) ? \ElementorPro\Modules\QueryControl\Module::QUERY_CONTROL_ID : $c::NUMBER;
		$this->start_controls_section( 'section_content', array( 'label' => 'Lista de deseos' ) );
		$this->add_control( 'mode', array( 'label' => 'Modo', 'type' => $c::SELECT, 'default' => 'save', 'options' => array( 'save' => 'Guardar este producto', 'link' => 'Enlace a la lista con contador' ) ) );
		$this->add_control( 'product_source', array( 'label' => 'Producto', 'type' => $c::SELECT, 'default' => 'current', 'options' => array( 'current' => 'Actual (el del bucle o la ficha)', 'custom' => 'Uno concreto' ), 'condition' => array( 'mode' => 'save' ) ) );
		$this->add_control( 'product_id', array( 'label' => 'Producto concreto', 'type' => $query, 'label_block' => true, 'options' => array(), 'autocomplete' => array( 'object' => 'post', 'query' => array( 'post_type' => array( 'product' ), 'post_status' => array( 'publish' ) ) ), 'condition' => array( 'mode' => 'save', 'product_source' => 'custom' ) ) );
		$this->add_control( 'wishlist_page', array( 'label' => 'Página de la lista', 'type' => $query, 'label_block' => true, 'options' => array(), 'autocomplete' => array( 'object' => 'post', 'query' => array( 'post_type' => array( 'page' ), 'post_status' => array( 'publish' ) ) ), 'description' => 'La página con el widget «Lista de deseos». Sin página, el enlace no se muestra.', 'condition' => array( 'mode' => 'link' ) ) );
		$this->add_control( 'text', array( 'label' => 'Texto', 'type' => $c::TEXT, 'default' => 'Guardar', 'description' => 'Vacío: sólo el icono; el nombre accesible sigue diciendo qué se guarda.', 'condition' => array( 'mode' => 'save' ) ) );
		$this->add_control( 'link_text', array( 'label' => 'Texto', 'type' => $c::TEXT, 'default' => 'Lista de deseos', 'condition' => array( 'mode' => 'link' ) ) );
		$this->add_control( 'show_icon', array( 'label' => 'Icono', 'type' => $c::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'guest', array( 'label' => 'Invitados', 'type' => $c::SELECT, 'default' => 'browser', 'options' => array( 'browser' => 'Guardar en este navegador', 'login' => 'Pedir iniciar sesión' ), 'description' => 'Si en la página hay otra instancia que pide iniciar sesión, gana esa.', 'condition' => array( 'mode' => 'save' ) ) );
		if ( class_exists( Module::class ) && class_exists( Source::class ) && ! \Elementor\Core\Frontend\Performance::should_optimize_controls() ) {
			$notice = Module::store_notice();
			if ( '' !== $notice ) {
				$this->add_control( 'store_notice', array( 'type' => $c::RAW_HTML, 'raw' => esc_html( $notice ), 'content_classes' => 'elementor-descriptor' ) );
			}
		}
		$this->end_controls_section();

		$this->start_controls_section( 'section_style', array( 'label' => 'Botón', 'tab' => $c::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'typography', 'selector' => '{{WRAPPER}} .digi-wish, {{WRAPPER}} .digi-wish-link' ) );
		$this->add_control( 'color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-wish, {{WRAPPER}} .digi-wish-link' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'pressed_color', array( 'label' => 'Color guardado', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-wish[aria-pressed="true"]' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'background', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-wish, {{WRAPPER}} .digi-wish-link' => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'icon_size', array( 'label' => 'Tamaño del icono', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 10, 'max' => 64 ) ), 'selectors' => array( '{{WRAPPER}} .digi-wish__icon' => 'font-size: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .digi-wish, {{WRAPPER}} .digi-wish-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'radius', array( 'label' => 'Radio del borde', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%' ), 'selectors' => array( '{{WRAPPER}} .digi-wish, {{WRAPPER}} .digi-wish-link' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render_widget() {
		$s    = $this->get_settings_for_display();
		$icon = 'yes' === ( $s['show_icon'] ?? 'yes' );
		if ( 'link' === ( $s['mode'] ?? 'save' ) ) {
			echo Button::link( (int) ( $s['wishlist_page'] ?? 0 ), array( 'text' => (string) ( $s['link_text'] ?? '' ), 'icon' => $icon ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		$id = 'custom' === ( $s['product_source'] ?? 'current' ) ? (int) ( $s['product_id'] ?? 0 ) : Button::current_product();
		echo Button::markup( $id, array( 'text' => (string) ( $s['text'] ?? '' ), 'icon' => $icon, 'guest' => (string) ( $s['guest'] ?? 'browser' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
