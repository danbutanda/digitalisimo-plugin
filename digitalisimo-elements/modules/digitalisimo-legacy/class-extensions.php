<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-translator.php';

/**
 * Mantiene los efectos que las extensiones de Element Pack guardaron en cualquier elemento.
 *
 * Elementor conserva en los ajustes las claves sin control, así que basta registrar los mismos
 * controles (nombres, clases de prefijo y plantillas CSS) para que regenere el CSS guardado. Wrapper
 * Link se reproduce con un enlace superpuesto; Backdrop Filter con una hoja propia que lee sus
 * variables. Text Gradient, Realistic Image Shadow, Floating Effects y Notation se aproximan con CSS.
 * La sección sólo aparece en elementos que ya usaban alguna extensión.
 */
final class Extensions {
	const SECTION = 'digitalisimo_legacy_extensions';
	const STYLE   = 'digitalisimo-legacy-extensions';
	const LINK    = 'element_pack_wrapper_link';

	/** Interruptores de Element Pack que delatan el uso de una extensión. */
	const SWITCHES = array( 'element_pack_backdrop_filter', 'element_pack_tgb_enable', 'element_pack_ris_enable', 'ep_floating_effects_show', 'ep_notation_active' );

	private static $links = array();

	public static function init() {
		if ( Translator::element_pack_active() ) {
			return;
		}
		$places = array(
			'common/_section_style/after_section_end',
			'section/section_advanced/after_section_end',
			'container/section_layout/after_section_end',
			'column/section_advanced/after_section_end',
		);
		foreach ( $places as $place ) {
			add_action( 'elementor/element/' . $place, array( __CLASS__, 'register_controls' ) );
		}
		add_action( 'elementor/frontend/before_render', array( __CLASS__, 'before_render' ) );
		add_action( 'elementor/frontend/after_render', array( __CLASS__, 'after_render' ) );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_style' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_style' ) );
	}

	public static function register_style() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style( self::STYLE, plugins_url( 'modules/digitalisimo-legacy/assets/legacy-extensions.css', DIGITALISIMO_ELEMENTS_FILE ), array(), DIGITALISIMO_ELEMENTS_VERSION );
		}
	}

	public static function register_controls( $element ) {
		$c     = '\\Elementor\\Controls_Manager';
		$terms = array( array( 'name' => self::LINK . '[url]', 'operator' => '!==', 'value' => '' ) );
		foreach ( self::SWITCHES as $key ) {
			$terms[] = array( 'name' => $key, 'operator' => '===', 'value' => 'yes' );
		}
		$element->start_controls_section( self::SECTION, array(
			'label'      => 'Efectos de Element Pack',
			'tab'        => $c::TAB_ADVANCED,
			'conditions' => array( 'relation' => 'or', 'terms' => $terms ),
		) );
		$element->add_control( 'digitalisimo_legacy_extensions_notice', array(
			'type'            => $c::RAW_HTML,
			'raw'             => 'Efectos creados con Element Pack. Siguen aplicándose; para contenido nuevo usa las opciones propias de Elements.',
			'content_classes' => 'elementor-descriptor',
		) );
		$element->add_control( self::LINK, array( 'label' => 'Enlace del elemento', 'type' => $c::URL, 'default' => array( 'url' => '' ), 'dynamic' => array( 'active' => true ), 'render_type' => 'none' ) );

		$element->add_control( 'element_pack_backdrop_filter', array( 'label' => 'Filtro de fondo', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'prefix_class' => 'bdt-backdrop-filter-' ) );
		$element->add_control( 'element_pack_backdrop_filter_type', array( 'label' => 'Tipo de filtro', 'type' => $c::SELECT, 'default' => 'backdrop_filter', 'options' => array( 'backdrop_filter' => 'Filtro', 'liquid_glass' => 'Cristal' ), 'prefix_class' => 'bdt-filter-', 'condition' => array( 'element_pack_backdrop_filter' => 'yes' ) ) );
		$filters = array( 'blur' => array( 'Desenfoque', 'px' ), 'brightness' => array( 'Brillo', '%' ), 'contrast' => array( 'Contraste', '' ), 'grayscale' => array( 'Escala de grises', '' ), 'invert' => array( 'Invertir', '' ), 'opacity' => array( 'Opacidad', '' ), 'sepia' => array( 'Sepia', '' ), 'saturate' => array( 'Saturación', '' ), 'hue_rotate' => array( 'Tono', 'deg' ) );
		foreach ( $filters as $name => $filter ) {
			$element->add_control( 'element_pack_bf_' . $name, array(
				'label'     => $filter[0],
				'type'      => $c::SLIDER,
				'condition' => array( 'element_pack_backdrop_filter' => 'yes' ),
				'selectors' => array( '{{WRAPPER}}' => '--ep-backdrop-filter-' . str_replace( '_', '-', $name ) . ': {{SIZE}}' . $filter[1] . ';' ),
			) );
		}
		$element->add_control( 'element_pack_liquid_glass_effects_blur', array( 'label' => 'Desenfoque del cristal', 'type' => $c::SLIDER, 'condition' => array( 'element_pack_backdrop_filter' => 'yes', 'element_pack_backdrop_filter_type' => 'liquid_glass' ), 'selectors' => array( '{{WRAPPER}}' => '--ep-liquid-glass-effects-blur: {{SIZE}}px;' ) ) );

		$element->add_control( 'element_pack_tgb_enable', array( 'label' => 'Degradado en el texto', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'prefix_class' => 'digi-legacy-tgb-' ) );
		$element->add_group_control( \Elementor\Group_Control_Background::get_type(), array(
			'name'      => 'element_pack_tgb_background',
			'types'     => array( 'classic', 'gradient' ),
			'selector'  => '{{WRAPPER}} .elementor-heading-title, {{WRAPPER}} .elementor-widget-container > :is(h1,h2,h3,h4,h5,h6,p)',
			'condition' => array( 'element_pack_tgb_enable' => 'yes' ),
		) );

		$element->add_control( 'element_pack_ris_enable', array( 'label' => 'Sombra de imagen', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		foreach ( array( 'x' => 'Desplazamiento X', 'y' => 'Desplazamiento Y', 'opacity' => 'Opacidad' ) as $name => $label ) {
			$element->add_control( 'element_pack_ris_' . $name, array( 'label' => $label, 'type' => $c::SLIDER, 'condition' => array( 'element_pack_ris_enable' => 'yes' ) ) );
		}
		$element->add_control( 'element_pack_ris_blur', array(
			'label'     => 'Desenfoque',
			'type'      => $c::SLIDER,
			'condition' => array( 'element_pack_ris_enable' => 'yes' ),
			'selectors' => array( '{{WRAPPER}} img' => 'filter: drop-shadow({{element_pack_ris_x.SIZE}}px {{element_pack_ris_y.SIZE}}px {{SIZE}}px rgba(0,0,0,{{element_pack_ris_opacity.SIZE}}));' ),
		) );

		$element->add_control( 'ep_floating_effects_show', array( 'label' => 'Efecto flotante', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'prefix_class' => 'digi-legacy-float-' ) );

		$element->add_control( 'ep_notation_active', array( 'label' => 'Anotación', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'prefix_class' => 'digi-legacy-notation-' ) );
		$element->add_control( 'ep_notation_type', array( 'label' => 'Tipo de anotación', 'type' => $c::SELECT, 'default' => 'underline', 'options' => array( 'underline' => 'Subrayado', 'box' => 'Recuadro', 'circle' => 'Círculo', 'highlight' => 'Resaltado', 'strike-through' => 'Tachado', 'crossed-off' => 'Cruzado', 'bracket' => 'Corchetes' ), 'prefix_class' => 'digi-legacy-notation-type-', 'condition' => array( 'ep_notation_active' => 'yes' ) ) );
		$element->add_control( 'ep_notation_color', array( 'label' => 'Color de la anotación', 'type' => $c::COLOR, 'condition' => array( 'ep_notation_active' => 'yes' ), 'selectors' => array( '{{WRAPPER}}' => '--digi-legacy-notation-color: {{VALUE}};' ) ) );
		$element->end_controls_section();
	}

	private static function link_settings( $element ) {
		$link = $element->get_settings_for_display( self::LINK );
		if ( ! is_array( $link ) || empty( $link['url'] ) ) {
			return null;
		}
		$link['url'] = esc_url( (string) $link['url'], array( 'http', 'https', 'tel', 'mailto', 'sms' ) );
		return '' === $link['url'] ? null : $link;
	}

	public static function before_render( $element ) {
		$settings = $element->get_settings();
		foreach ( self::SWITCHES as $key ) {
			if ( 'yes' === ( $settings[ $key ] ?? '' ) ) {
				self::register_style();
				wp_enqueue_style( self::STYLE );
				break;
			}
		}
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}
		$link = self::link_settings( $element );
		if ( $link ) {
			self::$links[ spl_object_id( $element ) ] = $link;
			ob_start();
		}
	}

	/** Un enlace que cubre todo el elemento, como el que Element Pack insertaba al renderizar. */
	public static function after_render( $element ) {
		$id = spl_object_id( $element );
		if ( ! isset( self::$links[ $id ] ) ) {
			return;
		}
		$link = self::$links[ $id ];
		unset( self::$links[ $id ] );
		$markup = (string) ob_get_clean();
		$rel    = array_filter( array( ! empty( $link['is_external'] ) ? 'noopener noreferrer' : '', ! empty( $link['nofollow'] ) ? 'nofollow' : '' ) );
		$anchor = '<a class="digi-legacy-element-link" href="' . $link['url'] . '" aria-label="Abrir enlace" style="position:absolute;inset:0;z-index:1"'
			. ( ! empty( $link['is_external'] ) ? ' target="_blank"' : '' ) . ( $rel ? ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"' : '' ) . '></a>';
		if ( preg_match( '/<[a-zA-Z][^>]*>/', $markup, $match, PREG_OFFSET_CAPTURE ) ) {
			$tag = $match[0][0];
			$new = false === stripos( $tag, 'position:' ) ? preg_replace( '/^<([a-zA-Z][\w-]*)/', '<$1 data-digi-legacy-link', $tag ) : $tag;
			$at  = $match[0][1] + strlen( $tag );
			$markup = substr( $markup, 0, $match[0][1] ) . $new . $anchor . substr( $markup, $at );
			self::register_style();
			wp_enqueue_style( self::STYLE );
		}
		echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ya renderizado por Elementor más un enlace escapado.
	}
}
