<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-translator.php';

/**
 * Figuras decorativas que el Shape Builder de Element Pack guardó en widgets, secciones y
 * contenedores. Se registran sus mismos controles para que Elementor regenere el CSS guardado
 * (tamaño, posición, giro, filtros) y se pintan las figuras con trazados propios equivalentes; una
 * figura subida se muestra con el SVG saneado de Elementor. La sección sólo aparece donde ya había
 * figuras: para contenido nuevo está la figura decorativa propia del título.
 */
final class Shapes {
	const ENABLE = 'bdt_shape_builder_enable';
	const LIST   = 'bdt_shape_builder_list';
	const STYLE  = 'digitalisimo-legacy-shapes';
	const SCRIPT = 'digitalisimo-legacy-shapes';

	private static $buffers = array();

	/** Trazados propios: mismas proporciones y caja que las figuras de Element Pack, no sus trazados. */
	public static function shapes() {
		$badge = array();
		for ( $i = 0; $i < 32; $i++ ) {
			$r       = 0 === $i % 2 ? 200 : 178;
			$a       = M_PI * 2 * $i / 32 - M_PI / 2;
			$badge[] = round( 200 + $r * cos( $a ), 1 ) . ',' . round( 200 + $r * sin( $a ), 1 );
		}
		return array(
			'circle'       => array( '0 0 100 100', '<circle cx="50" cy="50" r="40"/>' ),
			'square'       => array( '0 0 100 100', '<rect x="10" y="10" width="80" height="80"/>' ),
			'triangle'     => array( '0 0 100 100', '<polygon points="10,10 90,10 50,90"/>' ),
			'corner'       => array( '0 0 200 200', '<path d="M0 0H200C200 40 160 60 120 70C70 82 40 120 30 200H0Z"/>' ),
			'epblob'       => array( '0 0 200 200', '<path d="M150 30C185 55 195 110 175 150C155 190 100 200 60 180C20 160 5 115 20 75C35 35 75 10 110 12C125 13 140 22 150 30Z"/>' ),
			'oval'         => array( '0 0 300 300', '<ellipse cx="150" cy="150" rx="145" ry="105" transform="rotate(-12 150 150)"/>' ),
			'verify-badge' => array( '0 0 400 400', '<polygon points="' . implode( ' ', $badge ) . '"/>' ),
		);
	}

	public static function init() {
		if ( Translator::element_pack_active() ) {
			return;
		}
		foreach ( array( 'section/section_advanced/after_section_end', 'container/section_layout/after_section_end', 'common/_section_style/after_section_end' ) as $place ) {
			add_action( 'elementor/element/' . $place, array( __CLASS__, 'register_controls' ) );
		}
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'elementor/widget/render_content', array( __CLASS__, 'widget_content' ), 10, 2 );
		}
		add_action( 'elementor/frontend/before_render', array( __CLASS__, 'before_render' ) );
		add_action( 'elementor/frontend/after_render', array( __CLASS__, 'after_render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style( self::STYLE, plugins_url( 'modules/digitalisimo-legacy/assets/legacy-shapes.css', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION );
		}
		if ( ! wp_script_is( self::SCRIPT, 'registered' ) ) {
			wp_register_script( self::SCRIPT, plugins_url( 'modules/digitalisimo-legacy/assets/legacy-shapes.js', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION, true );
		}
	}

	public static function register_controls( $element ) {
		$c   = '\\Elementor\\Controls_Manager';
		$sel = '{{WRAPPER}} .digi-legacy-shape{{CURRENT_ITEM}}';
		$element->start_controls_section( 'digitalisimo_legacy_shapes', array(
			'label'     => 'Figuras de Element Pack',
			'tab'       => $c::TAB_ADVANCED,
			'condition' => array( self::ENABLE => 'yes' ),
		) );
		$element->add_control( self::ENABLE, array( 'label' => 'Mostrar figuras', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'default' => '' ) );
		$r     = new \Elementor\Repeater();
		$names = array( 'circle' => 'Círculo', 'square' => 'Cuadrado', 'triangle' => 'Triángulo', 'corner' => 'Esquina', 'epblob' => 'Mancha', 'oval' => 'Óvalo', 'verify-badge' => 'Insignia', 'custom' => 'SVG propio' );
		$r->add_control( 'shape_type', array( 'label' => 'Figura', 'type' => $c::SELECT, 'default' => 'circle', 'options' => $names ) );
		$r->add_control( 'custom_shape_upload', array( 'label' => 'SVG', 'type' => $c::MEDIA, 'media_types' => array( 'svg' ), 'default' => array( 'url' => '', 'id' => '' ), 'condition' => array( 'shape_type' => 'custom' ) ) );
		$r->add_control( 'custom_shape_color_popover', array( 'label' => 'Colores del SVG', 'type' => $c::POPOVER_TOGGLE, 'condition' => array( 'shape_type' => 'custom' ) ) );
		$r->start_popover();
		$r->add_control( 'custom_shape_fill_color', array( 'label' => 'Relleno', 'type' => $c::COLOR, 'condition' => array( 'custom_shape_color_popover' => 'yes', 'shape_type' => 'custom' ), 'selectors' => array( '{{WRAPPER}} .digi-legacy-shape.digi-legacy-shape--custom{{CURRENT_ITEM}} svg *' => 'fill: {{VALUE}};color: {{VALUE}};' ) ) );
		$r->add_control( 'custom_shape_stroke_color', array( 'label' => 'Trazo', 'type' => $c::COLOR, 'condition' => array( 'custom_shape_color_popover' => 'yes', 'shape_type' => 'custom' ), 'selectors' => array( '{{WRAPPER}} .digi-legacy-shape.digi-legacy-shape--custom{{CURRENT_ITEM}} svg *' => 'stroke: {{VALUE}};' ) ) );
		$r->end_popover();
		$r->add_control( 'shape_color_popover', array( 'label' => 'Color', 'type' => $c::POPOVER_TOGGLE, 'condition' => array( 'shape_type!' => 'custom' ) ) );
		$r->start_popover();
		$fill = array( 'shape_color_popover' => 'yes', 'shape_type!' => 'custom' );
		$r->add_control( 'shape_fill_type', array( 'label' => 'Relleno', 'type' => $c::SELECT, 'default' => 'solid', 'options' => array( 'solid' => 'Color', 'gradient' => 'Degradado' ), 'condition' => $fill ) );
		$r->add_control( 'shape_color', array( 'label' => 'Color', 'type' => $c::COLOR, 'default' => '#000000', 'condition' => $fill + array( 'shape_fill_type' => 'solid' ) ) );
		$r->add_control( 'shape_gradient_color_1', array( 'label' => 'Primer color', 'type' => $c::COLOR, 'default' => '#08AEEC', 'condition' => $fill + array( 'shape_fill_type' => 'gradient' ) ) );
		$r->add_control( 'shape_gradient_location_1', array( 'label' => 'Posición', 'type' => $c::SLIDER, 'size_units' => array( '%' ), 'default' => array( 'unit' => '%', 'size' => 0 ), 'condition' => $fill + array( 'shape_fill_type' => 'gradient' ) ) );
		$r->add_control( 'shape_gradient_color_2', array( 'label' => 'Segundo color', 'type' => $c::COLOR, 'default' => '#20E2AD', 'condition' => $fill + array( 'shape_fill_type' => 'gradient' ) ) );
		$r->add_control( 'shape_gradient_location_2', array( 'label' => 'Posición', 'type' => $c::SLIDER, 'size_units' => array( '%' ), 'default' => array( 'unit' => '%', 'size' => 100 ), 'condition' => $fill + array( 'shape_fill_type' => 'gradient' ) ) );
		$r->add_control( 'shape_gradient_type', array( 'label' => 'Tipo', 'type' => $c::SELECT, 'default' => 'linear', 'options' => array( 'linear' => 'Lineal', 'radial' => 'Radial' ), 'condition' => $fill + array( 'shape_fill_type' => 'gradient' ) ) );
		$r->add_control( 'shape_gradient_angle', array( 'label' => 'Ángulo', 'type' => $c::SLIDER, 'size_units' => array( 'deg' ), 'default' => array( 'unit' => 'deg', 'size' => 90 ), 'range' => array( 'deg' => array( 'min' => 0, 'max' => 360 ) ), 'condition' => $fill + array( 'shape_fill_type' => 'gradient', 'shape_gradient_type' => 'linear' ) ) );
		$r->end_popover();
		$r->add_responsive_control( 'shape_z_index', array( 'label' => 'Capa (z-index)', 'type' => $c::SLIDER, 'default' => array( 'size' => 1 ), 'range' => array( 'px' => array( 'min' => -10, 'max' => 100 ) ), 'selectors' => array( $sel => 'z-index: {{SIZE}};' ) ) );
		$r->add_responsive_control( 'shape_gap', array( 'label' => 'Tamaño', 'type' => $c::GAPS, 'size_units' => array( 'px', '%', 'em', 'vw' ), 'default' => array( 'unit' => 'px', 'row' => 200, 'column' => 200 ), 'selectors' => array( $sel . ' svg' => 'width: {{COLUMN}}{{UNIT}}; height: {{ROW}}{{UNIT}};' ) ) );
		$r->add_control( 'shape_position_popover', array( 'label' => 'Posición', 'type' => $c::POPOVER_TOGGLE ) );
		$r->start_popover();
		$pos = array( 'shape_position_popover' => 'yes' );
		$r->add_control( 'shape_position_horizontal', array( 'label' => 'Horizontal', 'type' => $c::SELECT, 'default' => 'start', 'options' => array( 'start' => 'Inicio', 'end' => 'Fin' ), 'condition' => $pos ) );
		$r->add_responsive_control( 'shape_position_horizontal_offset_start', array( 'label' => 'Desde el inicio', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%', 'vw' ), 'range' => array( 'px' => array( 'min' => -1000, 'max' => 1000 ) ), 'default' => array( 'size' => 0 ), 'condition' => $pos + array( 'shape_position_horizontal' => 'start' ), 'selectors' => array( $sel => 'inset-inline-start: {{SIZE}}{{UNIT}}' ) ) );
		$r->add_responsive_control( 'shape_position_horizontal_offset_end', array( 'label' => 'Desde el fin', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%', 'vw' ), 'range' => array( 'px' => array( 'min' => -1000, 'max' => 1000 ) ), 'default' => array( 'size' => 0 ), 'condition' => $pos + array( 'shape_position_horizontal' => 'end' ), 'selectors' => array( $sel => 'inset-inline-end: {{SIZE}}{{UNIT}}; inset-inline-start: auto;' ) ) );
		$r->add_control( 'shape_position_vertical', array( 'label' => 'Vertical', 'type' => $c::SELECT, 'default' => 'start', 'options' => array( 'start' => 'Arriba', 'end' => 'Abajo' ), 'condition' => $pos ) );
		$r->add_responsive_control( 'shape_position_vertical_offset_start', array( 'label' => 'Desde arriba', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%', 'vh' ), 'range' => array( 'px' => array( 'min' => -1000, 'max' => 1000 ) ), 'default' => array( 'size' => 0 ), 'condition' => $pos + array( 'shape_position_vertical' => 'start' ), 'selectors' => array( $sel => 'top: {{SIZE}}{{UNIT}}' ) ) );
		$r->add_responsive_control( 'shape_position_vertical_offset_end', array( 'label' => 'Desde abajo', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%', 'vh' ), 'range' => array( 'px' => array( 'min' => -1000, 'max' => 1000 ) ), 'default' => array( 'size' => 0 ), 'condition' => $pos + array( 'shape_position_vertical' => 'end' ), 'selectors' => array( $sel => 'bottom: {{SIZE}}{{UNIT}}; top: auto;' ) ) );
		$r->end_popover();
		$r->add_control( 'shape_builder_animation_popover', array( 'label' => 'Animación de entrada', 'type' => $c::POPOVER_TOGGLE ) );
		$r->start_popover();
		$anim = array( 'shape_builder_animation_popover' => 'yes' );
		$r->add_control( 'animation_trigger_type', array( 'label' => 'Al', 'type' => $c::SELECT, 'default' => 'on-load', 'options' => array( 'on-load' => 'Aparecer', 'on-hover' => 'Pasar el cursor' ), 'condition' => $anim ) );
		$r->add_control( 'animation_name', array( 'label' => 'Efecto', 'type' => $c::SELECT, 'default' => 'fade-in', 'options' => array( 'fade-in' => 'Aparecer', 'fade-in-up' => 'Subir', 'fade-in-down' => 'Bajar', 'fade-in-left' => 'Desde la izquierda', 'fade-in-right' => 'Desde la derecha', 'zoom-in' => 'Acercar', 'zoom-out' => 'Alejar', 'rotate-in' => 'Girar' ), 'condition' => $anim ) );
		$r->add_control( 'animation_duration', array( 'label' => 'Duración', 'type' => $c::SLIDER, 'size_units' => array( 's' ), 'default' => array( 'unit' => 's', 'size' => 1 ), 'range' => array( 's' => array( 'min' => 0.1, 'max' => 10, 'step' => 0.1 ) ), 'condition' => $anim ) );
		$r->add_control( 'animation_delay', array( 'label' => 'Retraso', 'type' => $c::SLIDER, 'size_units' => array( 's' ), 'default' => array( 'unit' => 's', 'size' => 0 ), 'range' => array( 's' => array( 'min' => 0, 'max' => 10, 'step' => 0.1 ) ), 'condition' => $anim ) );
		$r->end_popover();
		$r->add_group_control( \Elementor\Group_Control_Css_Filter::get_type(), array( 'name' => 'css_filters', 'selector' => $sel ) );
		$r->add_control( 'shape_offset_popover', array( 'label' => 'Desplazamiento y giro', 'type' => $c::POPOVER_TOGGLE ) );
		$r->start_popover();
		$off = array( 'shape_offset_popover' => 'yes' );
		$r->add_responsive_control( 'shape_position_x', array( 'label' => 'X', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%' ), 'range' => array( 'px' => array( 'min' => -500, 'max' => 500 ) ), 'default' => array( 'unit' => 'px', 'size' => 0 ), 'condition' => $off, 'selectors' => array( $sel => '--shape-position-x: {{SIZE}}{{UNIT}};' ) ) );
		$r->add_responsive_control( 'shape_position_y', array( 'label' => 'Y', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%' ), 'range' => array( 'px' => array( 'min' => -500, 'max' => 500 ) ), 'default' => array( 'unit' => 'px', 'size' => 0 ), 'condition' => $off, 'selectors' => array( $sel => '--shape-position-y: {{SIZE}}{{UNIT}};' ) ) );
		$r->add_responsive_control( 'shape_rotate', array( 'label' => 'Giro', 'type' => $c::SLIDER, 'size_units' => array( 'deg' ), 'range' => array( 'deg' => array( 'min' => -360, 'max' => 360 ) ), 'default' => array( 'size' => 0, 'unit' => 'deg' ), 'condition' => $off, 'selectors' => array( $sel => '--shape-rotate: {{SIZE}}{{UNIT}};' ) ) );
		$r->end_popover();
		$r->add_group_control( \Elementor\Group_Control_Css_Filter::get_type(), array( 'name' => 'css_filters_hover', 'selector' => '{{WRAPPER}}:hover .digi-legacy-shape{{CURRENT_ITEM}}' ) );
		$r->add_control( 'shape_offset_hover_popover', array( 'label' => 'Al pasar el cursor', 'type' => $c::POPOVER_TOGGLE ) );
		$r->start_popover();
		$hov = array( 'shape_offset_hover_popover' => 'yes' );
		$r->add_responsive_control( 'shape_position_x_hover', array( 'label' => 'X', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%' ), 'range' => array( 'px' => array( 'min' => -500, 'max' => 500 ) ), 'default' => array( 'unit' => 'px', 'size' => 0 ), 'condition' => $hov, 'selectors' => array( $sel => '--shape-position-hover-x: {{SIZE}}{{UNIT}};' ) ) );
		$r->add_responsive_control( 'shape_position_y_hover', array( 'label' => 'Y', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%' ), 'range' => array( 'px' => array( 'min' => -500, 'max' => 500 ) ), 'default' => array( 'unit' => 'px', 'size' => 0 ), 'condition' => $hov, 'selectors' => array( $sel => '--shape-position-hover-y: {{SIZE}}{{UNIT}};' ) ) );
		$r->add_responsive_control( 'shape_rotate_hover', array( 'label' => 'Giro', 'type' => $c::SLIDER, 'size_units' => array( 'deg' ), 'range' => array( 'deg' => array( 'min' => -360, 'max' => 360 ) ), 'default' => array( 'size' => 0, 'unit' => 'deg' ), 'condition' => $hov, 'selectors' => array( $sel => '--shape-rotate-hover: {{SIZE}}{{UNIT}};' ) ) );
		$r->end_popover();
		$element->add_control( self::LIST, array(
			'label'       => 'Figuras',
			'type'        => $c::REPEATER,
			'fields'      => $r->get_controls(),
			'default'     => array( array( 'shape_type' => 'circle' ) ),
			'title_field' => '{{{ shape_type }}}',
			'condition'   => array( self::ENABLE => 'yes' ),
		) );
		$element->end_controls_section();
	}

	/** Marcado de las figuras de un elemento. */
	public static function markup( array $settings, $element_id ) {
		if ( 'yes' !== ( $settings[ self::ENABLE ] ?? '' ) || ! is_array( $settings[ self::LIST ] ?? null ) ) {
			return '';
		}
		$shapes = self::shapes();
		$out    = '';
		foreach ( $settings[ self::LIST ] as $index => $shape ) {
			if ( ! is_array( $shape ) ) {
				continue;
			}
			$type  = (string) ( $shape['shape_type'] ?? 'circle' );
			$item  = sanitize_html_class( (string) ( $shape['_id'] ?? $element_id . '-' . $index ) );
			$attrs = '';
			if ( 'yes' === ( $shape['shape_builder_animation_popover'] ?? '' ) && 'on-load' === ( $shape['animation_trigger_type'] ?? 'on-load' ) ) {
				$name  = preg_replace( '/[^a-z-]/', '', (string) ( $shape['animation_name'] ?? 'fade-in' ) );
				$attrs = ' data-digi-shape-animation="' . esc_attr( '' !== $name ? $name : 'fade-in' ) . '" style="' . esc_attr( '--digi-shape-duration:' . (float) ( $shape['animation_duration']['size'] ?? 1 ) . 's;--digi-shape-delay:' . (float) ( $shape['animation_delay']['size'] ?? 0 ) . 's' ) . '"';
			}
			if ( 'custom' === $type ) {
				$id  = absint( $shape['custom_shape_upload']['id'] ?? 0 );
				$svg = $id && class_exists( '\\Elementor\\Core\\Files\\File_Types\\Svg' ) ? (string) \Elementor\Core\Files\File_Types\Svg::get_inline_svg( $id ) : '';
				if ( '' !== $svg ) {
					$out .= '<div class="digi-legacy-shape digi-legacy-shape--custom elementor-repeater-item-' . esc_attr( $item ) . '" aria-hidden="true"' . $attrs . '>' . $svg . '</div>';
				}
				continue;
			}
			if ( ! isset( $shapes[ $type ] ) ) {
				continue;
			}
			$fill = '';
			$defs = '';
			if ( 'gradient' === ( $shape['shape_fill_type'] ?? 'solid' ) ) {
				$grad   = 'digi-shape-' . $item;
				$stops  = '<stop offset="' . (float) ( $shape['shape_gradient_location_1']['size'] ?? 0 ) . '%" stop-color="' . esc_attr( self::color( $shape['shape_gradient_color_1'] ?? '', '#08AEEC' ) ) . '"/>'
					. '<stop offset="' . (float) ( $shape['shape_gradient_location_2']['size'] ?? 100 ) . '%" stop-color="' . esc_attr( self::color( $shape['shape_gradient_color_2'] ?? '', '#20E2AD' ) ) . '"/>';
				$defs   = 'radial' === ( $shape['shape_gradient_type'] ?? 'linear' )
					? '<defs><radialGradient id="' . esc_attr( $grad ) . '">' . $stops . '</radialGradient></defs>'
					: '<defs><linearGradient id="' . esc_attr( $grad ) . '" gradientTransform="rotate(' . (float) ( $shape['shape_gradient_angle']['size'] ?? 90 ) . ')">' . $stops . '</linearGradient></defs>';
				$fill   = 'url(#' . $grad . ')';
			} else {
				$fill = self::color( $shape['shape_color'] ?? '', '#000000' );
			}
			$out .= '<div class="digi-legacy-shape elementor-repeater-item-' . esc_attr( $item ) . '" aria-hidden="true"' . $attrs . '>'
				. '<svg viewBox="' . esc_attr( $shapes[ $type ][0] ) . '" preserveAspectRatio="none" focusable="false"><g fill="' . esc_attr( $fill ) . '">' . $defs . $shapes[ $type ][1] . '</g></svg></div>';
		}
		if ( '' !== $out ) {
			self::register_assets();
			wp_enqueue_style( self::STYLE );
			if ( false !== strpos( $out, 'data-digi-shape-animation' ) ) {
				wp_enqueue_script( self::SCRIPT );
			}
		}
		return $out;
	}

	/** Colores de Elementor: hexadecimal, rgb/hsl o variable global. */
	private static function color( $value, $fallback ) {
		$value = trim( (string) $value );
		return preg_match( '/^(#[0-9a-fA-F]{3,8}|(rgb|hsl)a?\([0-9.,\s%deg]+\)|var\(--[a-zA-Z0-9_-]+\)|currentColor)$/', $value ) ? $value : $fallback;
	}

	public static function widget_content( $content, $widget ) {
		if ( ! is_string( $content ) || ! is_object( $widget ) || ! method_exists( $widget, 'get_settings_for_display' ) ) {
			return $content;
		}
		return $content . self::markup( (array) $widget->get_settings_for_display(), (string) $widget->get_id() );
	}

	public static function before_render( $element ) {
		if ( ! in_array( $element->get_type(), array( 'section', 'container', 'column' ), true ) || 'yes' !== $element->get_settings( self::ENABLE ) ) {
			return;
		}
		self::$buffers[ spl_object_id( $element ) ] = true;
		ob_start();
	}

	/** Las figuras de secciones y contenedores van dentro de su envoltorio, como hacía el script de Element Pack. */
	public static function after_render( $element ) {
		$key = spl_object_id( $element );
		if ( ! isset( self::$buffers[ $key ] ) ) {
			return;
		}
		unset( self::$buffers[ $key ] );
		$html   = (string) ob_get_clean();
		$shapes = self::markup( (array) $element->get_settings_for_display(), (string) $element->get_id() );
		if ( '' !== $shapes && preg_match( '/<[a-zA-Z][^>]*>/', $html, $match, PREG_OFFSET_CAPTURE ) ) {
			$at   = $match[0][1] + strlen( $match[0][0] );
			$html = substr( $html, 0, $at ) . $shapes . substr( $html, $at );
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML de Elementor y figuras escapadas.
	}
}
