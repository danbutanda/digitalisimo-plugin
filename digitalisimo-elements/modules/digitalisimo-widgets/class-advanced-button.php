<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Botón con icono, distintivo y estilos de interacción sin JavaScript. */
final class Advanced_Button_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-advanced-button'; }
	public function get_title() { return 'Botón avanzado'; }
	public function get_icon() { return 'eicon-button'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'botón', 'button', 'enlace', 'cta' ); }
	public function get_style_depends() { return array( 'digitalisimo-advanced-button' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_button', array( 'label' => 'Botón' ) );
		$this->add_control( 'text', array( 'label' => 'Texto', 'type' => $c::TEXT, 'default' => 'Conoce más', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ), 'placeholder' => 'https://' ) );
		$this->add_control( 'button_size', array( 'label' => 'Tamaño', 'type' => $c::SELECT, 'default' => 'md', 'options' => array( 'xs' => 'Muy pequeño', 'sm' => 'Pequeño', 'md' => 'Mediano', 'lg' => 'Grande', 'xl' => 'Muy grande' ) ) );
		$this->add_responsive_control( 'align', array( 'label' => 'Alineación', 'type' => $c::SELECT, 'default' => 'left', 'options' => array( 'left' => 'Izquierda', 'center' => 'Centro', 'right' => 'Derecha', 'justify' => 'Ancho completo' ), 'prefix_class' => 'elementor%s-align-', 'selectors' => array( '{{WRAPPER}} .digi-advanced-button__wrap' => 'text-align:{{VALUE}};' ) ) );
		$this->add_control( 'button_icon', array( 'label' => 'Icono', 'type' => $c::ICONS ) );
		$this->add_control( 'icon_align', array( 'label' => 'Posición del icono', 'type' => $c::SELECT, 'default' => 'right', 'options' => array( 'left' => 'Antes', 'right' => 'Después', 'top' => 'Arriba', 'bottom' => 'Abajo' ), 'condition' => array( 'button_icon[value]!' => '' ) ) );
		$this->add_responsive_control( 'icon_indent', array( 'label' => 'Separación del icono', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ), 'em' => array( 'min' => 0, 'max' => 8, 'step' => 0.1 ) ), 'default' => array( 'size' => 8, 'unit' => 'px' ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-button__content' => 'gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'show_button_badge', array( 'label' => 'Mostrar distintivo', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'default' => '' ) );
		$this->add_control( 'badge_text', array( 'label' => 'Texto del distintivo', 'type' => $c::TEXT, 'default' => 'Nuevo', 'dynamic' => array( 'active' => true ), 'condition' => array( 'show_button_badge' => 'yes' ) ) );
		$this->add_control( 'badge_align', array( 'label' => 'Lado del distintivo', 'type' => $c::SELECT, 'default' => 'right', 'options' => array( 'left' => 'Antes', 'right' => 'Después' ), 'condition' => array( 'show_button_badge' => 'yes' ) ) );
		$this->add_control( 'button_css_id', array( 'label' => 'ID del botón', 'type' => $c::TEXT, 'description' => 'Debe ser único en la página y no incluir #.' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'button_effect', array( 'label' => 'Efecto', 'type' => $c::SELECT, 'default' => 'a', 'options' => array( 'a' => 'Fundido', 'b' => 'Desde arriba', 'c' => 'Desde un lado', 'd' => 'Desde el centro', 'e' => 'Diagonal', 'f' => 'Desde el centro vertical', 'g' => 'Elevación', 'h' => 'Barrido diagonal', 'i' => 'Apertura' ) ) );
		$this->add_control( 'advanced_button_text_color', array( 'label' => 'Color de texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-button' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'button_background_color', array( 'label' => 'Color de fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-button' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'advanced_button_hover_text_color', array( 'label' => 'Texto al pasar el cursor', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-button:hover, {{WRAPPER}} .digi-advanced-button:focus-visible' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'button_hover_background_color', array( 'label' => 'Fondo del efecto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-button::before' => 'background-color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'advanced_button_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-button' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'advanced_button_radius', array( 'label' => 'Radio', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-button' => 'border-radius:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'badge_color', array( 'label' => 'Color del distintivo', 'type' => $c::COLOR, 'condition' => array( 'show_button_badge' => 'yes' ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-button__badge' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'badge_background_color', array( 'label' => 'Fondo del distintivo', 'type' => $c::COLOR, 'condition' => array( 'show_button_badge' => 'yes' ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-button__badge' => 'background-color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	private static function choice( $value, $allowed, $fallback ) { return in_array( $value, $allowed, true ) ? $value : $fallback; }

	protected function render() {
		$s = $this->get_settings_for_display();
		$text = trim( (string) ( $s['text'] ?? '' ) );
		if ( '' === $text ) { return; }
		$size = self::choice( $s['button_size'] ?? '', array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'md' );
		$effect = self::choice( $s['button_effect'] ?? '', array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i' ), 'a' );
		$position = self::choice( $s['icon_align'] ?? '', array( 'left', 'right', 'top', 'bottom' ), 'right' );
		$icon = is_array( $s['button_icon'] ?? null ) ? $s['button_icon'] : array();
		$badge = 'yes' === ( $s['show_button_badge'] ?? '' ) ? trim( (string) ( $s['badge_text'] ?? '' ) ) : '';
		$badge_align = 'left' === ( $s['badge_align'] ?? '' ) ? 'left' : 'right';
		$url = esc_url( (string) ( $s['link']['url'] ?? '' ) );
		$tag = '' !== $url ? 'a' : 'span';
		$css_id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $s['button_css_id'] ?? '' ) );
		echo '<div class="digi-advanced-button__wrap"><' . $tag . ' class="digi-advanced-button digi-advanced-button--' . esc_attr( $size ) . ' digi-advanced-button--effect-' . esc_attr( $effect ) . ' digi-advanced-button--icon-' . esc_attr( $position ) . '"';
		if ( $css_id ) { echo ' id="' . esc_attr( $css_id ) . '"'; }
		if ( 'a' === $tag ) {
			echo ' href="' . $url . '"';
			if ( ! empty( $s['link']['is_external'] ) ) { echo ' target="_blank"'; }
			$rel = array();
			if ( ! empty( $s['link']['is_external'] ) ) { $rel = array( 'noopener', 'noreferrer' ); }
			if ( ! empty( $s['link']['nofollow'] ) ) { $rel[] = 'nofollow'; }
			if ( $rel ) { echo ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"'; }
		}
		echo '><span class="digi-advanced-button__content">';
		if ( '' !== $badge && 'left' === $badge_align ) { echo '<span class="digi-advanced-button__badge">' . esc_html( $badge ) . '</span>'; }
		if ( ! empty( $icon['value'] ) && class_exists( '\\Elementor\\Icons_Manager' ) && in_array( $position, array( 'left', 'top' ), true ) ) { echo '<span class="digi-advanced-button__icon" aria-hidden="true">'; \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ); echo '</span>'; }
		echo '<span class="digi-advanced-button__text">' . esc_html( $text ) . '</span>';
		if ( ! empty( $icon['value'] ) && class_exists( '\\Elementor\\Icons_Manager' ) && in_array( $position, array( 'right', 'bottom' ), true ) ) { echo '<span class="digi-advanced-button__icon" aria-hidden="true">'; \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ); echo '</span>'; }
		if ( '' !== $badge && 'right' === $badge_align ) { echo '<span class="digi-advanced-button__badge">' . esc_html( $badge ) . '</span>'; }
		echo '</span></' . $tag . '></div>';
	}

	protected function content_template() {
		?>
		<# var text = String( settings.text || '' ).trim();
		var size = _.contains( ['xs','sm','md','lg','xl'], settings.button_size ) ? settings.button_size : 'md';
		var effect = _.contains( ['a','b','c','d','e','f','g','h','i'], settings.button_effect ) ? settings.button_effect : 'a';
		var position = _.contains( ['left','right','top','bottom'], settings.icon_align ) ? settings.icon_align : 'right';
		var badge = settings.show_button_badge === 'yes' ? String( settings.badge_text || '' ).trim() : '';
		var icon = settings.button_icon && settings.button_icon.value ? elementor.helpers.renderIcon( view, settings.button_icon, { 'aria-hidden': true }, 'i', 'object' ) : null;
		var url = settings.link && settings.link.url ? String( settings.link.url ) : '';
		var hasUrl = /^(https?:\/\/|\/|#|mailto:|tel:)/i.test( url );
		var tag = hasUrl ? 'a' : 'span'; #>
		<# if ( text ) { #><div class="digi-advanced-button__wrap"><{{ tag }} class="digi-advanced-button digi-advanced-button--{{ size }} digi-advanced-button--effect-{{ effect }} digi-advanced-button--icon-{{ position }}" <# if ( hasUrl ) { #>href="{{ url }}"<# } #>><span class="digi-advanced-button__content">
		<# if ( badge && settings.badge_align === 'left' ) { #><span class="digi-advanced-button__badge">{{ badge }}</span><# } #>
		<# if ( icon && icon.rendered && ( position === 'left' || position === 'top' ) ) { #><span class="digi-advanced-button__icon" aria-hidden="true">{{{ icon.value }}}</span><# } #>
		<span class="digi-advanced-button__text">{{ text }}</span>
		<# if ( icon && icon.rendered && ( position === 'right' || position === 'bottom' ) ) { #><span class="digi-advanced-button__icon" aria-hidden="true">{{{ icon.value }}}</span><# } #>
		<# if ( badge && settings.badge_align !== 'left' ) { #><span class="digi-advanced-button__badge">{{ badge }}</span><# } #>
		</span></{{ tag }}></div><# } #>
		<?php
	}
}
