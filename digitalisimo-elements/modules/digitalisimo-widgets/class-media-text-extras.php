<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Texto bajo dos widgets de medios: descripción y botón en Lottie, y leyenda en Hotspot (que hereda de
 * Imagen pero no ofrece leyenda). Se añaden al final del HTML del widget y a su vista previa del editor;
 * un widget que no usa estas opciones se pinta igual que antes.
 */
final class Media_Text_Extras {
	public static function init() {
		// Ganchos genéricos de sección: los específicos usan el nombre del widget y no llegan a los widgets
		// registrados con otro nombre sobre la misma clase.
		add_action( 'elementor/element/before_section_end', array( __CLASS__, 'before_section_end' ), 10, 2 );
		add_action( 'elementor/element/after_section_end', array( __CLASS__, 'after_section_end' ), 10, 2 );
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'render_content' ), 10, 2 );
		add_filter( 'elementor/widget/print_template', array( __CLASS__, 'print_template' ), 10, 2 );
	}

	public static function before_section_end( $element, $section_id ) {
		if ( 'section_image' === $section_id && 'hotspot' === self::kind( $element ) ) {
			self::hotspot_controls( $element );
		}
	}

	public static function after_section_end( $element, $section_id ) {
		$kind = self::kind( $element );
		if ( 'lottie' === $kind && 'lottie' === $section_id ) {
			self::lottie_controls( $element );
		} elseif ( 'hotspot' === $kind && 'section_style_image' === $section_id ) {
			self::hotspot_style( $element );
		}
	}

	public static function lottie_controls( $widget ) {
		$c = '\\Elementor\\Controls_Manager';
		$widget->start_controls_section( 'digitalisimo_lottie_text', array( 'label' => 'Texto y botón' ) );
		$widget->add_control( 'digitalisimo_lottie_description', array( 'label' => 'Descripción', 'type' => $c::TEXTAREA, 'dynamic' => array( 'active' => true ) ) );
		$widget->add_control( 'digitalisimo_lottie_button_text', array( 'label' => 'Texto del botón', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$widget->add_control( 'digitalisimo_lottie_button_link', array( 'label' => 'Enlace del botón', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$widget->end_controls_section();
	}

	public static function hotspot_controls( $widget ) {
		$widget->add_control( 'digitalisimo_hotspot_caption', array( 'label' => 'Leyenda', 'type' => \Elementor\Controls_Manager::TEXT, 'dynamic' => array( 'active' => true ), 'description' => 'Texto bajo la imagen.' ) );
	}

	/** Estilo propio de la leyenda: Hotspot quita los controles de leyenda de Imagen. */
	public static function hotspot_style( $widget ) {
		$c = '\\Elementor\\Controls_Manager';
		$widget->start_controls_section( 'digitalisimo_hotspot_caption_style', array( 'label' => 'Leyenda', 'tab' => $c::TAB_STYLE ) );
		$widget->add_responsive_control( 'digitalisimo_hotspot_caption_align', array( 'label' => 'Alineación', 'type' => $c::CHOOSE, 'options' => array( 'left' => array( 'title' => 'Izquierda', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ), 'right' => array( 'title' => 'Derecha', 'icon' => 'eicon-text-align-right' ), 'justify' => array( 'title' => 'Justificado', 'icon' => 'eicon-text-align-justify' ) ), 'selectors' => array( '{{WRAPPER}} .digi-media-caption' => 'text-align: {{VALUE}};' ) ) );
		$widget->add_control( 'digitalisimo_hotspot_caption_color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-media-caption' => 'color: {{VALUE}};' ) ) );
		$widget->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'digitalisimo_hotspot_caption_typography', 'selector' => '{{WRAPPER}} .digi-media-caption' ) );
		$widget->add_responsive_control( 'digitalisimo_hotspot_caption_space', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .digi-media-caption' => 'margin-top: {{SIZE}}{{UNIT}};' ) ) );
		$widget->end_controls_section();
	}

	/**
	 * Tipo de widget por su clase, no por su nombre: así también se reconocen los widgets registrados con
	 * otro nombre sobre la misma clase.
	 */
	public static function kind( $widget ) {
		if ( $widget instanceof \ElementorPro\Modules\Lottie\Widgets\Lottie ) {
			return 'lottie';
		}
		if ( $widget instanceof \ElementorPro\Modules\Hotspot\Widgets\Hotspot ) {
			return 'hotspot';
		}
		return is_object( $widget ) && method_exists( $widget, 'get_name' ) && in_array( $widget->get_name(), array( 'lottie', 'hotspot' ), true ) ? $widget->get_name() : '';
	}

	/**
	 * HTML que se añade al widget: descripción y botón de Lottie, o leyenda de Hotspot.
	 */
	public static function extra( $name, array $s ) {
		if ( 'hotspot' === $name ) {
			$caption = trim( wp_strip_all_tags( (string) ( $s['digitalisimo_hotspot_caption'] ?? '' ) ) );
			return '' !== $caption ? '<p class="digi-media-caption">' . esc_html( $caption ) . '</p>' : '';
		}
		if ( 'lottie' !== $name ) {
			return '';
		}
		$out  = '';
		$text = trim( (string) ( $s['digitalisimo_lottie_description'] ?? '' ) );
		if ( '' !== $text ) {
			$out .= '<div class="digi-media-description">' . wp_kses_post( wpautop( $text ) ) . '</div>';
		}
		$label = trim( wp_strip_all_tags( (string) ( $s['digitalisimo_lottie_button_text'] ?? '' ) ) );
		$link  = is_array( $s['digitalisimo_lottie_button_link'] ?? null ) ? $s['digitalisimo_lottie_button_link'] : array();
		if ( '' !== $label && ! empty( $link['url'] ) ) {
			$rel  = array_filter( array( ! empty( $link['nofollow'] ) ? 'nofollow' : '', ! empty( $link['is_external'] ) ? 'noopener noreferrer' : '' ) );
			$out .= '<a class="digi-media-button elementor-button" href="' . esc_url( $link['url'] ) . '"' . ( ! empty( $link['is_external'] ) ? ' target="_blank"' : '' ) . ( $rel ? ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		return $out;
	}

	public static function render_content( $content, $widget ) {
		$kind = self::kind( $widget );
		return '' === $kind ? $content : $content . self::extra( $kind, (array) $widget->get_settings_for_display() );
	}

	/** Vista previa del editor con las mismas piezas. */
	public static function print_template( $template, $widget ) {
		$kind = '' === (string) $template ? '' : self::kind( $widget );
		if ( 'hotspot' === $kind ) {
			return $template . '<# if ( settings.digitalisimo_hotspot_caption ) { #><p class="digi-media-caption">{{ settings.digitalisimo_hotspot_caption }}</p><# } #>';
		}
		if ( 'lottie' === $kind ) {
			return $template . '<# if ( settings.digitalisimo_lottie_description ) { #><div class="digi-media-description">{{ settings.digitalisimo_lottie_description }}</div><# } #><# var digiLink = settings.digitalisimo_lottie_button_link || {}; if ( settings.digitalisimo_lottie_button_text && digiLink.url ) { #><a class="digi-media-button elementor-button" href="{{ digiLink.url }}">{{ settings.digitalisimo_lottie_button_text }}</a><# } #>';
		}
		return $template;
	}
}
