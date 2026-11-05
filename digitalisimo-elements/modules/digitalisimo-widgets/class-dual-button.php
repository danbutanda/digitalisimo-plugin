<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Dos acciones independientes, sin handlers globales ni JavaScript. */
class Dual_Button_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-dual-button'; }
	public function get_title() { return 'Botón doble'; }
	public function get_icon() { return 'eicon-button'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'botón', 'doble', 'acción', 'cta' ); }
	public function get_style_depends() { return array( 'digitalisimo-dual-button' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_buttons', array( 'label' => 'Botones' ) );
		foreach ( array( 'a' => 'Primer botón', 'b' => 'Segundo botón' ) as $key => $label ) {
			$this->add_control( 'button_' . $key . '_text', array( 'label' => 'Texto · ' . $label, 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'default' => 'a' === $key ? 'Conoce más' : 'Contactar', 'label_block' => true ) );
			$this->add_control( 'button_' . $key . '_link', array( 'label' => 'Enlace · ' . $label, 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
			$this->add_control( 'button_' . $key . '_icon', array( 'label' => 'Icono · ' . $label, 'type' => $c::ICONS ) );
		}
		$this->add_control( 'show_middle_text', array( 'label' => 'Mostrar texto intermedio', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'middle_text', array( 'label' => 'Texto intermedio', 'type' => $c::TEXT, 'default' => 'o', 'condition' => array( 'show_middle_text' => 'yes' ) ) );
		$this->add_control( 'size', array( 'label' => 'Tamaño', 'type' => $c::SELECT, 'default' => 'medium', 'options' => array( 'small' => 'Pequeño', 'medium' => 'Mediano', 'large' => 'Grande' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_buttons_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'align', array( 'label' => 'Alineación', 'type' => $c::CHOOSE, 'options' => array( 'flex-start' => array( 'title' => 'Izquierda', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ), 'flex-end' => array( 'title' => 'Derecha', 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .digi-dual-button' => 'justify-content:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}} .digi-dual-button' => 'gap:{{SIZE}}px;' ) ) );
		$this->add_control( 'first_color', array( 'label' => 'Fondo primer botón', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-dual-button__action--a' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'second_color', array( 'label' => 'Fondo segundo botón', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-dual-button__action--b' => 'background-color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	private function button( $settings, $key ) {
		$text = trim( wp_strip_all_tags( (string) ( $settings[ 'button_' . $key . '_text' ] ?? '' ) ) );
		if ( '' === $text ) { return ''; }
		$link = is_array( $settings[ 'button_' . $key . '_link' ] ?? null ) ? $settings[ 'button_' . $key . '_link' ] : array();
		$icon = is_array( $settings[ 'button_' . $key . '_icon' ] ?? null ) ? $settings[ 'button_' . $key . '_icon' ] : array();
		$attrs = '';
		if ( ! empty( $link['url'] ) ) {
			$this->add_link_attributes( 'dual_button_' . $key, $link );
			if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( 'dual_button_' . $key, 'rel', array( 'noopener', 'noreferrer' ) ); }
			$attrs = ' ' . $this->get_render_attribute_string( 'dual_button_' . $key );
		}
		ob_start();
		$tag = $attrs ? 'a' : 'span';
		echo '<' . $tag . ' class="digi-dual-button__action digi-dual-button__action--' . esc_attr( $key ) . '"' . $attrs . '>';
		if ( ! empty( $icon['value'] ) ) { \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ); }
		echo '<span>' . esc_html( $text ) . '</span></' . $tag . '>';
		return ob_get_clean();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$first = $this->button( $s, 'a' );
		$second = $this->button( $s, 'b' );
		if ( ! $first && ! $second ) { return; }
		$size = in_array( $s['size'] ?? 'medium', array( 'small', 'medium', 'large' ), true ) ? ( $s['size'] ?? 'medium' ) : 'medium';
		echo '<div class="digi-dual-button digi-dual-button--' . esc_attr( $size ) . '">' . $first;
		if ( $first && $second && 'yes' === ( $s['show_middle_text'] ?? '' ) && ! empty( $s['middle_text'] ) ) { echo '<span class="digi-dual-button__middle">' . esc_html( wp_strip_all_tags( $s['middle_text'] ) ) . '</span>'; }
		echo $second . '</div>';
	}

	protected function content_template() {
		?>
		<# var size = _.contains( ['small','medium','large'], settings.size ) ? settings.size : 'medium';
		var iconA = elementor.helpers.renderIcon( view, settings.button_a_icon, { 'aria-hidden': true }, 'i', 'object' );
		var iconB = elementor.helpers.renderIcon( view, settings.button_b_icon, { 'aria-hidden': true }, 'i', 'object' );
		var urlA = settings.button_a_link && settings.button_a_link.url ? settings.button_a_link.url : '';
		var urlB = settings.button_b_link && settings.button_b_link.url ? settings.button_b_link.url : '';
		var linkRel = function( link ) { return [ link && link.is_external ? 'noopener noreferrer' : '', link && link.nofollow ? 'nofollow' : '' ].filter( Boolean ).join( ' ' ); };
		var relA = linkRel( settings.button_a_link ), relB = linkRel( settings.button_b_link ); #>
		<div class="digi-dual-button digi-dual-button--{{ size }}">
		<# if ( settings.button_a_text ) { #><# if ( urlA ) { #><a class="digi-dual-button__action digi-dual-button__action--a" href="{{ urlA }}"<# if ( settings.button_a_link.is_external ) { #> target="_blank"<# } #><# if ( relA ) { #> rel="{{ relA }}"<# } #>><# } else { #><span class="digi-dual-button__action digi-dual-button__action--a"><# } #><# if ( iconA && iconA.rendered ) { #>{{{ iconA.value }}}<# } #><span>{{ settings.button_a_text }}</span><# if ( urlA ) { #></a><# } else { #></span><# } #><# } #>
		<# if ( settings.show_middle_text === 'yes' && settings.button_a_text && settings.button_b_text ) { #><span class="digi-dual-button__middle">{{ settings.middle_text }}</span><# } #>
		<# if ( settings.button_b_text ) { #><# if ( urlB ) { #><a class="digi-dual-button__action digi-dual-button__action--b" href="{{ urlB }}"<# if ( settings.button_b_link.is_external ) { #> target="_blank"<# } #><# if ( relB ) { #> rel="{{ relB }}"<# } #>><# } else { #><span class="digi-dual-button__action digi-dual-button__action--b"><# } #><# if ( iconB && iconB.rendered ) { #>{{{ iconB.value }}}<# } #><span>{{ settings.button_b_text }}</span><# if ( urlB ) { #></a><# } else { #></span><# } #><# } #>
		</div>
		<?php
	}
}
