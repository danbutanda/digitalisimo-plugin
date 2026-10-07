<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Enlace a una sección de la página con desplazamiento opcional. */
final class Scroll_Button_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-scroll-button'; }
	public function get_title() { return 'Botón de desplazamiento'; }
	public function get_icon() { return 'eicon-arrow-up'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'desplazar', 'ancla', 'subir', 'scroll' ); }
	public function get_style_depends() { return array( 'digitalisimo-scroll-button' ); }
	public function get_script_depends() { return array( 'digitalisimo-scroll-button' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_button', array( 'label' => 'Botón' ) );
		$this->add_control( 'scroll_button_text', array( 'label' => 'Texto', 'type' => $c::TEXT, 'default' => 'Ir a la sección', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'section_id', array( 'label' => 'ID de la sección', 'type' => $c::TEXT, 'default' => 'inicio', 'description' => 'Escribe el ID de una sección de esta página, sin #.', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'button_icon', array( 'label' => 'Icono', 'type' => $c::ICONS, 'default' => array( 'value' => '', 'library' => '' ) ) );
		$this->add_control( 'icon_align', array( 'label' => 'Posición del icono', 'type' => $c::SELECT, 'default' => 'left', 'options' => array( 'left' => 'Antes', 'right' => 'Después' ) ) );
		$this->add_control( 'duration', array( 'label' => 'Duración (ms)', 'type' => $c::SLIDER, 'default' => array( 'size' => 400, 'unit' => 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 5000, 'step' => 50 ) ) ) );
		$this->add_control( 'offset', array( 'label' => 'Desplazamiento vertical (px)', 'type' => $c::SLIDER, 'default' => array( 'size' => 0, 'unit' => 'px' ), 'range' => array( 'px' => array( 'min' => -200, 'max' => 200 ) ) ) );
		$this->add_control( 'scroll_button_position', array( 'label' => 'Ubicación', 'type' => $c::SELECT, 'default' => 'inline', 'options' => array( 'inline' => 'En el contenido', 'bottom-right' => 'Fijo abajo a la derecha', 'bottom-left' => 'Fijo abajo a la izquierda' ) ) );
		$this->add_responsive_control( 'scroll_button_align', array( 'label' => 'Alineación', 'type' => $c::SELECT, 'default' => 'center', 'options' => array( 'left' => 'Izquierda', 'center' => 'Centro', 'right' => 'Derecha' ), 'selectors' => array( '{{WRAPPER}} .digi-scroll-button' => 'text-align:{{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'text_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-scroll-button__link' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'background_color', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-scroll-button__link' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'hover_color', array( 'label' => 'Color al pasar el cursor', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-scroll-button__link:hover' => 'color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'button_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-scroll-button__link' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'border_radius', array( 'label' => 'Radio del borde', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-scroll-button__link' => 'border-radius:{{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	private static function target_id( $raw ) {
		$id = ltrim( trim( (string) $raw ), '#' );
		return preg_match( '/^[^\s#<>"\']+$/uD', $id ) ? $id : '';
	}

	private static function number( $setting, $min, $max, $default ) {
		$value = is_array( $setting ) && isset( $setting['size'] ) && is_numeric( $setting['size'] ) ? (float) $setting['size'] : $default;
		return (int) max( $min, min( $max, $value ) );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$id = self::target_id( $settings['section_id'] ?? '' );
		if ( '' === $id ) { return; }
		$position = in_array( $settings['scroll_button_position'] ?? '', array( 'bottom-right', 'bottom-left' ), true ) ? $settings['scroll_button_position'] : 'inline';
		$duration = self::number( $settings['duration'] ?? null, 0, 5000, 400 );
		$offset = self::number( $settings['offset'] ?? null, -200, 200, 0 );
		$text = trim( (string) ( $settings['scroll_button_text'] ?? '' ) );
		if ( '' === $text ) { $text = 'Ir a la sección'; }
		$icon = is_array( $settings['button_icon'] ?? null ) ? $settings['button_icon'] : array();
		$has_icon = ! empty( $icon['value'] ) && class_exists( '\\Elementor\\Icons_Manager' );
		$align = 'right' === ( $settings['icon_align'] ?? '' ) ? 'right' : 'left';
		echo '<div class="digi-scroll-button digi-scroll-button--' . esc_attr( $position ) . '">';
		echo '<a class="digi-scroll-button__link" href="#' . esc_attr( $id ) . '" data-duration="' . esc_attr( $duration ) . '" data-offset="' . esc_attr( $offset ) . '">';
		if ( $has_icon && 'left' === $align ) { echo '<span class="digi-scroll-button__icon" aria-hidden="true">'; \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ); echo '</span>'; }
		echo '<span class="digi-scroll-button__text">' . esc_html( $text ) . '</span>';
		if ( $has_icon && 'right' === $align ) { echo '<span class="digi-scroll-button__icon" aria-hidden="true">'; \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ); echo '</span>'; }
		echo '</a></div>';
	}

	protected function content_template() {
		?>
		<# var id = String( settings.section_id || '' ).trim().replace( /^#+/, '' );
		var valid = id && !/[\s#<>"']/.test( id );
		var position = _.contains( ['bottom-right','bottom-left'], settings.scroll_button_position ) ? settings.scroll_button_position : 'inline';
		var text = String( settings.scroll_button_text || '' ).trim() || 'Ir a la sección';
		var duration = settings.duration && settings.duration.size !== undefined ? Math.max( 0, Math.min( 5000, Number( settings.duration.size ) || 0 ) ) : 400;
		var offset = settings.offset && settings.offset.size !== undefined ? Math.max( -200, Math.min( 200, Number( settings.offset.size ) || 0 ) ) : 0;
		var icon = settings.button_icon && settings.button_icon.value ? elementor.helpers.renderIcon( view, settings.button_icon, { 'aria-hidden': true }, 'i', 'object' ) : null; #>
		<# if ( valid ) { #><div class="digi-scroll-button digi-scroll-button--{{ position }}"><a class="digi-scroll-button__link" href="#{{ id }}" data-duration="{{ duration }}" data-offset="{{ offset }}">
		<# if ( icon && icon.rendered && settings.icon_align !== 'right' ) { #><span class="digi-scroll-button__icon" aria-hidden="true">{{{ icon.value }}}</span><# } #>
		<span class="digi-scroll-button__text">{{ text }}</span>
		<# if ( icon && icon.rendered && settings.icon_align === 'right' ) { #><span class="digi-scroll-button__icon" aria-hidden="true">{{{ icon.value }}}</span><# } #>
		</a></div><# } #>
		<?php
	}
}
