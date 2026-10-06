<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Animated Link reconstruido para Elements.
 * Referencia funcional: Element Pack Pro 9.9.1, modules/animated-link (GPLv3).
 * No hereda clases, controles ni recursos globales del plugin original.
 */
class Animated_Link_Widget extends \Elementor\Widget_Base {
	private const STYLES = array( 'carpo', 'carme', 'dia', 'eirene', 'elara', 'ersa', 'helike', 'herse', 'io', 'iocaste', 'kale', 'leda', 'metis', 'mneme', 'thebe' );
	private const SPAN_STYLES = array( 'leda', 'elara', 'ersa', 'eirene', 'helike', 'iocaste', 'herse', 'carme' );

	public function get_name() { return 'digitalisimo-animated-link'; }
	public function get_title() { return 'Enlace animado'; }
	public function get_icon() { return 'eicon-animated-headline'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'digitalisimo', 'enlace', 'animado', 'link' ); }
	public function get_style_depends() { return array( 'digitalisimo-animated-link' ); }
	public function get_script_depends() { return array(); }
	protected function is_legacy_widget() { return false; }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'content', array( 'label' => 'Enlace animado' ) );
		$this->add_control( 'link_style', array( 'label' => 'Estilo', 'type' => $c::SELECT, 'default' => 'metis', 'options' => array_combine( self::STYLES, array_map( 'ucfirst', self::STYLES ) ) ) );
		$this->add_control( 'link_text', array( 'label' => 'Texto', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'default' => 'Enlace animado', 'label_block' => true ) );
		$this->add_control( 'link_url', array( 'label' => 'URL', 'type' => $c::URL, 'dynamic' => array( 'active' => true ), 'show_external' => true, 'default' => array( 'url' => '' ) ) );
		$this->add_responsive_control( 'link_alignment', array(
			'label' => 'Alineación', 'type' => $c::CHOOSE,
			'options' => array(
				'left' => array( 'title' => 'Izquierda', 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ),
				'right' => array( 'title' => 'Derecha', 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}}' => 'text-align: {{VALUE}};' ),
		) );
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'link_text_color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-animated-link' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'link_hover_text_color', array( 'label' => 'Color al enfocar', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-animated-link:is(:hover,:focus-visible)' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'link_style_color', array( 'label' => 'Color del efecto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-animated-link::before, {{WRAPPER}} .digi-animated-link::after' => 'background: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'link_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-animated-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'link_typography', 'selector' => '{{WRAPPER}} .digi-animated-link' ) );
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$text = trim( (string) ( $settings['link_text'] ?? '' ) );
		if ( '' === $text ) {
			return;
		}
		$style = (string) ( $settings['link_style'] ?? 'metis' );
		if ( ! in_array( $style, self::STYLES, true ) ) {
			$style = 'metis';
		}
		$url = isset( $settings['link_url'] ) && is_array( $settings['link_url'] ) ? $settings['link_url'] : array();
		$tag = ! empty( $url['url'] ) ? 'a' : 'span';
		$classes = array( 'digi-animated-link', 'digi-animated-link--' . $style );
		if ( $this->is_legacy_widget() ) {
			// Conserva los selectores generados por Elementor para páginas bdt-* existentes.
			$classes[] = 'bdt-ep-animated-link';
			$classes[] = 'bdt-ep-animated-link--' . $style;
		}
		$this->add_render_attribute( 'link', 'class', $classes );
		if ( 'leda' === $style ) {
			$this->add_render_attribute( 'link', 'data-text', $text );
		}
		if ( 'a' === $tag ) {
			$this->add_link_attributes( 'link', $url );
		}
		echo '<' . $tag . ' ' . $this->get_render_attribute_string( 'link' ) . '>';
		if ( in_array( $style, self::SPAN_STYLES, true ) ) {
			echo '<span>' . esc_html( $text ) . '</span>';
		} else {
			echo esc_html( $text );
		}
		self::graphic( $style );
		echo '</' . $tag . '>';
	}

	private static function graphic( $style ) {
		// Tres formas SVG del módulo original, con atribución GPLv3 arriba; son decorativas.
		if ( 'iocaste' === $style ) {
			echo '<svg class="digi-animated-link__graphic digi-animated-link__graphic--slide" aria-hidden="true" focusable="false" width="300%" height="100%" viewBox="0 0 1200 60" preserveAspectRatio="none"><path d="M0,56.5c0,0,298.666,0,399.333,0C448.336,56.5,513.994,46,597,46c77.327,0,135,10.5,200.999,10.5c95.996,0,402.001,0,402.001,0"></path></svg>';
		} elseif ( 'herse' === $style ) {
			echo '<svg class="digi-animated-link__graphic digi-animated-link__graphic--stroke digi-animated-link__graphic--arc" aria-hidden="true" focusable="false" width="100%" height="18" viewBox="0 0 59 18"><path d="M.945.149C12.3 16.142 43.573 22.572 58.785 10.842" pathLength="1"></path></svg>';
		} elseif ( 'carme' === $style ) {
			echo '<svg class="digi-animated-link__graphic digi-animated-link__graphic--stroke digi-animated-link__graphic--scribble" aria-hidden="true" focusable="false" width="100%" height="9" viewBox="0 0 101 9"><path d="M.426 1.973C4.144 1.567 17.77-.514 21.443 1.48 24.296 3.026 24.844 4.627 27.5 7c3.075 2.748 6.642-4.141 10.066-4.688 7.517-1.2 13.237 5.425 17.59 2.745C58.5 3 60.464-1.786 66 2c1.996 1.365 3.174 3.737 5.286 4.41 5.423 1.727 25.34-7.981 29.14-1.294" pathLength="1"></path></svg>';
		}
	}

	protected function content_template() {
		?>
		<#
		var styles = <?php echo wp_json_encode( self::STYLES ); ?>;
		var withSpan = <?php echo wp_json_encode( self::SPAN_STYLES ); ?>;
		var style = _.contains( styles, settings.link_style ) ? settings.link_style : 'metis';
		var text = settings.link_text || '';
		var url = settings.link_url && settings.link_url.url ? settings.link_url.url : '';
		var tag = url ? 'a' : 'span';
		var classes = 'digi-animated-link digi-animated-link--' + style;
		<?php if ( $this->is_legacy_widget() ) : ?>
		classes += ' bdt-ep-animated-link bdt-ep-animated-link--' + style;
		<?php endif; ?>
		#>
		<# if ( text ) { #>
		<{{{ tag }}} class="{{{ classes }}}" <# if ( url ) { #>href="{{ url }}"<# } #> <# if ( style === 'leda' ) { #>data-text="{{ text }}"<# } #>>
			<# if ( _.contains( withSpan, style ) ) { #><span>{{ text }}</span><# } else { #>{{ text }}<# } #>
			<# if ( style === 'iocaste' ) { #><svg class="digi-animated-link__graphic digi-animated-link__graphic--slide" aria-hidden="true" focusable="false" width="300%" height="100%" viewBox="0 0 1200 60" preserveAspectRatio="none"><path d="M0,56.5c0,0,298.666,0,399.333,0C448.336,56.5,513.994,46,597,46c77.327,0,135,10.5,200.999,10.5c95.996,0,402.001,0,402.001,0"></path></svg><# } #>
			<# if ( style === 'herse' ) { #><svg class="digi-animated-link__graphic digi-animated-link__graphic--stroke digi-animated-link__graphic--arc" aria-hidden="true" focusable="false" width="100%" height="18" viewBox="0 0 59 18"><path d="M.945.149C12.3 16.142 43.573 22.572 58.785 10.842" pathLength="1"></path></svg><# } #>
			<# if ( style === 'carme' ) { #><svg class="digi-animated-link__graphic digi-animated-link__graphic--stroke digi-animated-link__graphic--scribble" aria-hidden="true" focusable="false" width="100%" height="9" viewBox="0 0 101 9"><path d="M.426 1.973C4.144 1.567 17.77-.514 21.443 1.48 24.296 3.026 24.844 4.627 27.5 7c3.075 2.748 6.642-4.141 10.066-4.688 7.517-1.2 13.237 5.425 17.59 2.745C58.5 3 60.464-1.786 66 2c1.996 1.365 3.174 3.737 5.286 4.41 5.423 1.727 25.34-7.981 29.14-1.294" pathLength="1"></path></svg><# } #>
		</{{{ tag }}}>
		<# } #>
		<?php
	}
}

/** Alias de lectura para documentos Elementor que todavía guardan bdt-animated-link. */
final class Legacy_Animated_Link_Widget extends Animated_Link_Widget {
	public function get_name() { return 'bdt-animated-link'; }
	public function get_title() { return 'Enlace animado (compatibilidad)'; }
	protected function is_legacy_widget() { return true; }
}
