<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-widget-alias.php';

/**
 * Botón con icono, distintivo y efectos de interacción sin JavaScript. Reúne el Botón avanzado y el
 * Botón creativo, que sigue registrado como alias oculto de éste (misma clase PHP, mismo nombre y
 * mismas clases CSS) para el contenido ya guardado.
 */
class Advanced_Button_Widget extends \Elementor\Widget_Base {
	use Legacy_Classes;

	const CSS_PREFIX = 'digi-advanced-button';
	const EFFECTS    = array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'fill', 'outline', 'underline', 'glow' );

	public function get_name() { return 'digitalisimo-advanced-button'; }
	public function get_title() { return 'Botón'; }
	public function get_icon() { return 'eicon-button'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'botón', 'button', 'enlace', 'cta', 'creativo', 'efecto' ); }
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
		$this->add_control( 'button_effect', array( 'label' => 'Efecto', 'type' => $c::SELECT, 'default' => 'a', 'options' => array( 'a' => 'Fundido', 'b' => 'Desde arriba', 'fill' => 'Desde abajo', 'c' => 'Desde un lado', 'd' => 'Desde el centro', 'e' => 'Diagonal', 'f' => 'Desde el centro vertical', 'g' => 'Elevación', 'h' => 'Barrido diagonal', 'i' => 'Apertura', 'outline' => 'Contorno', 'underline' => 'Subrayado', 'glow' => 'Resplandor' ) ) );
		$this->add_control( 'advanced_button_text_color', array( 'label' => 'Color de texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-button' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'button_background_color', array( 'label' => 'Color de fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-button' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'advanced_button_hover_text_color', array( 'label' => 'Texto al pasar el cursor', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-button:hover, {{WRAPPER}} .digi-advanced-button:focus-visible' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'button_hover_background_color', array( 'label' => 'Fondo del efecto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-button::before' => 'background-color:{{VALUE}};', '{{WRAPPER}} .digi-advanced-button--effect-outline:hover, {{WRAPPER}} .digi-advanced-button--effect-outline:focus-visible' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'button_border_style', array( 'label' => 'Estilo del borde', 'type' => $c::SELECT, 'default' => '', 'options' => array( '' => 'Predeterminado', 'none' => 'Sin borde', 'solid' => 'Sólido', 'dotted' => 'Punteado', 'dashed' => 'Rayado', 'groove' => 'Ranurado' ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-button' => 'border-style:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'button_border_width', array( 'label' => 'Grosor del borde', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px' ), 'condition' => array( 'button_border_style!' => 'none' ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-button' => 'border-width:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'button_border_color', array( 'label' => 'Color del borde', 'type' => $c::COLOR, 'condition' => array( 'button_border_style!' => 'none' ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-button' => 'border-color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'advanced_button_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-button' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'advanced_button_radius', array( 'label' => 'Radio', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-button' => 'border-radius:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'button_width', array( 'label' => 'Ancho máximo', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%' ), 'range' => array( 'px' => array( 'min' => 80, 'max' => 1000 ), '%' => array( 'min' => 10, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-button' => 'max-width:{{SIZE}}{{UNIT}};width:100%;' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'advanced_button_typography', 'selector' => '{{WRAPPER}} .digi-advanced-button' ) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array( 'name' => 'advanced_button_shadow', 'selector' => '{{WRAPPER}} .digi-advanced-button' ) );
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
		$effect = self::choice( $s['button_effect'] ?? '', self::EFFECTS, 'a' );
		$position = self::choice( $s['icon_align'] ?? '', array( 'left', 'right', 'top', 'bottom' ), 'right' );
		$icon = is_array( $s['button_icon'] ?? null ) ? $s['button_icon'] : array();
		$badge = 'yes' === ( $s['show_button_badge'] ?? '' ) ? trim( (string) ( $s['badge_text'] ?? '' ) ) : '';
		$badge_align = 'left' === ( $s['badge_align'] ?? '' ) ? 'left' : 'right';
		$url = esc_url( (string) ( $s['link']['url'] ?? '' ) );
		$tag = '' !== $url ? 'a' : 'span';
		$css_id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $s['button_css_id'] ?? '' ) );
		echo '<div class="' . esc_attr( $this->cls( 'wrap' ) ) . '"><' . $tag . ' class="' . esc_attr( $this->cls( '', array( $size, 'effect-' . $effect, 'icon-' . $position ) ) ) . '"';
		if ( $css_id ) { echo ' id="' . esc_attr( $css_id ) . '"'; }
		if ( 'a' === $tag ) {
			echo ' href="' . $url . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapada arriba.
			if ( ! empty( $s['link']['is_external'] ) ) { echo ' target="_blank"'; }
			$rel = array();
			if ( ! empty( $s['link']['is_external'] ) ) { $rel = array( 'noopener', 'noreferrer' ); }
			if ( ! empty( $s['link']['nofollow'] ) ) { $rel[] = 'nofollow'; }
			if ( $rel ) { echo ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"'; }
		}
		echo '><span class="' . esc_attr( $this->cls( 'content' ) ) . '">';
		if ( '' !== $badge && 'left' === $badge_align ) { echo '<span class="' . esc_attr( $this->cls( 'badge' ) ) . '">' . esc_html( $badge ) . '</span>'; }
		if ( ! empty( $icon['value'] ) && class_exists( '\\Elementor\\Icons_Manager' ) && in_array( $position, array( 'left', 'top' ), true ) ) { echo '<span class="' . esc_attr( $this->cls( 'icon' ) ) . '" aria-hidden="true">'; \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ); echo '</span>'; }
		echo '<span class="' . esc_attr( $this->cls( 'text' ) ) . '">' . esc_html( $text ) . '</span>';
		if ( ! empty( $icon['value'] ) && class_exists( '\\Elementor\\Icons_Manager' ) && in_array( $position, array( 'right', 'bottom' ), true ) ) { echo '<span class="' . esc_attr( $this->cls( 'icon' ) ) . '" aria-hidden="true">'; \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ); echo '</span>'; }
		if ( '' !== $badge && 'right' === $badge_align ) { echo '<span class="' . esc_attr( $this->cls( 'badge' ) ) . '">' . esc_html( $badge ) . '</span>'; }
		echo '</span></' . $tag . '></div>';
	}

	protected function content_template() {
		?>
		<# <?php echo $this->cls_template_js(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON de clases fijas. ?>
		var text = String( settings.text || '' ).trim();
		var size = _.contains( ['xs','sm','md','lg','xl'], settings.button_size ) ? settings.button_size : 'md';
		var effect = _.contains( <?php echo json_encode( self::EFFECTS ); ?>, settings.button_effect ) ? settings.button_effect : 'a';
		var position = _.contains( ['left','right','top','bottom'], settings.icon_align ) ? settings.icon_align : 'right';
		var badge = settings.show_button_badge === 'yes' ? String( settings.badge_text || '' ).trim() : '';
		var icon = settings.button_icon && settings.button_icon.value ? elementor.helpers.renderIcon( view, settings.button_icon, { 'aria-hidden': true }, 'i', 'object' ) : null;
		var url = settings.link && settings.link.url ? String( settings.link.url ) : '';
		var hasUrl = /^(https?:\/\/|\/|#|mailto:|tel:)/i.test( url );
		var tag = hasUrl ? 'a' : 'span';
		var buttonId = String( settings.button_css_id || '' ).replace( /[^A-Za-z0-9_-]/g, '' );
		var external = hasUrl && settings.link && settings.link.is_external;
		var rel = hasUrl ? [ external ? 'noopener noreferrer' : '', settings.link && settings.link.nofollow ? 'nofollow' : '' ].filter( Boolean ).join( ' ' ) : ''; #>
		<# if ( text ) { #><div class="{{ cls( 'wrap' ) }}"><{{ tag }} class="{{ cls( '', [ size, 'effect-' + effect, 'icon-' + position ] ) }}"<# if ( buttonId ) { #> id="{{ buttonId }}"<# } #><# if ( hasUrl ) { #> href="{{ url }}"<# } #><# if ( external ) { #> target="_blank"<# } #><# if ( rel ) { #> rel="{{ rel }}"<# } #>><span class="{{ cls( 'content' ) }}">
		<# if ( badge && settings.badge_align === 'left' ) { #><span class="{{ cls( 'badge' ) }}">{{ badge }}</span><# } #>
		<# if ( icon && icon.rendered && ( position === 'left' || position === 'top' ) ) { #><span class="{{ cls( 'icon' ) }}" aria-hidden="true">{{{ icon.value }}}</span><# } #>
		<span class="{{ cls( 'text' ) }}">{{ text }}</span>
		<# if ( icon && icon.rendered && ( position === 'right' || position === 'bottom' ) ) { #><span class="{{ cls( 'icon' ) }}" aria-hidden="true">{{{ icon.value }}}</span><# } #>
		<# if ( badge && settings.badge_align !== 'left' ) { #><span class="{{ cls( 'badge' ) }}">{{ badge }}</span><# } #>
		</span></{{ tag }}></div><# } #>
		<?php
	}
}
