<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Botón con efectos visuales CSS y enlace nativo. */
final class Creative_Button_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-creative-button'; }
	public function get_title() { return 'Botón creativo'; }
	public function get_icon() { return 'eicon-button'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'botón', 'creativo', 'efecto', 'enlace' ); }
	public function get_style_depends() { return array( 'digitalisimo-creative-button' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_creative_button', array( 'label' => 'Contenido' ) );
		$this->add_control( 'text', array( 'label' => 'Texto', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'default' => 'Conoce más' ) );
		$this->add_control( 'link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'icon', array( 'label' => 'Icono', 'type' => $c::ICONS ) );
		$this->add_control( 'effect', array( 'label' => 'Efecto', 'type' => $c::SELECT, 'default' => 'fill', 'options' => array( 'fill' => 'Relleno', 'outline' => 'Contorno', 'lift' => 'Elevación', 'underline' => 'Subrayado', 'glow' => 'Resplandor' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_creative_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'align', array( 'label' => 'Alineación', 'type' => $c::CHOOSE, 'options' => array( 'left' => array( 'title' => 'Izquierda', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ), 'right' => array( 'title' => 'Derecha', 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .digi-creative-button__wrap' => 'text-align:{{VALUE}};' ) ) );
		$this->add_control( 'button_color', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-creative-button' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'text_color', array( 'label' => 'Texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-creative-button' => 'color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-creative-button' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$text = trim( wp_strip_all_tags( (string) ( $s['text'] ?? '' ) ) );
		if ( '' === $text ) { return; }
		$effect = in_array( $s['effect'] ?? 'fill', array( 'fill', 'outline', 'lift', 'underline', 'glow' ), true ) ? $s['effect'] : 'fill';
		$link = is_array( $s['link'] ?? null ) ? $s['link'] : array();
		$has_link = ! empty( $link['url'] );
		echo '<div class="digi-creative-button__wrap">';
		if ( $has_link ) { $this->add_link_attributes( 'creative_button_link', $link ); echo '<a class="digi-creative-button digi-creative-button--' . esc_attr( $effect ) . '" ' . $this->get_render_attribute_string( 'creative_button_link' ) . '>'; }
		else { echo '<span class="digi-creative-button digi-creative-button--' . esc_attr( $effect ) . '">'; }
		$icon = is_array( $s['icon'] ?? null ) ? $s['icon'] : array();
		if ( ! empty( $icon['value'] ) ) { \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ); }
		echo '<span>' . esc_html( $text ) . '</span>';
		echo $has_link ? '</a>' : '</span>';
		echo '</div>';
	}

	protected function content_template() {
		?>
		<# var effect = _.contains( ['fill','outline','lift','underline','glow'], settings.effect ) ? settings.effect : 'fill'; var href = settings.link && settings.link.url ? settings.link.url : ''; #>
		<# if ( settings.text ) { #><div class="digi-creative-button__wrap"><# if ( href ) { #><a class="digi-creative-button digi-creative-button--{{ effect }}" href="{{ href }}"><# } else { #><span class="digi-creative-button digi-creative-button--{{ effect }}"><# } #><span>{{ settings.text }}</span><# if ( href ) { #></a><# } else { #></span><# } #></div><# } #>
		<?php
	}
}
