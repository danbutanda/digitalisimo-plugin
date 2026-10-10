<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Panel lateral que se abre con su botón o con cualquier elemento indicado por un selector. Muestra
 * una plantilla publicada, una barra lateral de widgets o texto; es un diálogo con foco atrapado,
 * cierre con Escape, con el fondo o con su botón, y devuelve el foco al elemento que lo abrió.
 */
class Offcanvas_Widget extends \Elementor\Widget_Base {
	private static $template_options = array();
	private static $rendering        = array();

	public function get_name() { return 'digitalisimo-offcanvas'; }
	public function get_title() { return 'Panel lateral'; }
	public function get_icon() { return 'eicon-sidebar'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'panel', 'lateral', 'offcanvas', 'menú', 'cajón' ); }
	public function get_style_depends() { return array( 'digitalisimo-offcanvas' ); }
	public function get_script_depends() { return array( 'digitalisimo-offcanvas' ); }

	private static function template_options() {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() || ! function_exists( 'post_type_exists' ) || ! post_type_exists( 'elementor_library' ) ) { return array(); }
		$key = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1;
		if ( ! isset( self::$template_options[ $key ] ) ) {
			self::$template_options[ $key ] = array();
			foreach ( get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) ) as $post ) {
				self::$template_options[ $key ][ $post->ID ] = get_the_title( $post->ID );
			}
		}
		return self::$template_options[ $key ];
	}

	private static function sidebar_options() {
		$options = array( '' => 'Elegir barra lateral' );
		foreach ( (array) ( $GLOBALS['wp_registered_sidebars'] ?? array() ) as $id => $sidebar ) {
			$options[ $id ] = (string) ( $sidebar['name'] ?? $id );
		}
		return $options;
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_trigger', array( 'label' => 'Apertura' ) );
		$this->add_control( 'trigger', array( 'label' => 'Se abre con', 'type' => $c::SELECT, 'default' => 'button', 'options' => array( 'button' => 'Su propio botón', 'selector' => 'Otros elementos de la página' ) ) );
		$this->add_control( 'trigger_selector', array( 'label' => 'Selector de los elementos', 'type' => $c::TEXT, 'placeholder' => '#abrir-panel', 'description' => 'Selector CSS, por ejemplo #menu-movil o .abrir-panel. Cada elemento que coincida abre el panel.', 'condition' => array( 'trigger' => 'selector' ) ) );
		$this->add_control( 'button_text', array( 'label' => 'Texto del botón', 'type' => $c::TEXT, 'default' => 'Menú', 'dynamic' => array( 'active' => true ), 'condition' => array( 'trigger' => 'button' ) ) );
		$this->add_control( 'button_icon', array( 'label' => 'Icono', 'type' => $c::ICONS, 'default' => array( 'value' => 'fas fa-bars', 'library' => 'fa-solid' ), 'condition' => array( 'trigger' => 'button' ) ) );
		$this->add_control( 'button_icon_align', array( 'label' => 'Posición del icono', 'type' => $c::SELECT, 'default' => 'left', 'options' => array( 'left' => 'Antes', 'right' => 'Después' ), 'condition' => array( 'trigger' => 'button' ) ) );
		$this->add_control( 'button_size', array( 'label' => 'Tamaño', 'type' => $c::SELECT, 'default' => 'sm', 'options' => array( 'xs' => 'Muy pequeño', 'sm' => 'Pequeño', 'md' => 'Mediano', 'lg' => 'Grande', 'xl' => 'Muy grande' ), 'condition' => array( 'trigger' => 'button' ) ) );
		$this->add_responsive_control( 'button_align', array( 'label' => 'Alineación', 'type' => $c::CHOOSE, 'options' => array( 'flex-start' => array( 'title' => 'Inicio', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ), 'flex-end' => array( 'title' => 'Fin', 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .digi-offcanvas__trigger' => 'justify-content:{{VALUE}};' ), 'condition' => array( 'trigger' => 'button' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_content', array( 'label' => 'Contenido del panel' ) );
		$this->add_control( 'source', array( 'label' => 'Origen', 'type' => $c::SELECT, 'default' => 'template', 'options' => array( 'template' => 'Plantilla publicada', 'sidebar' => 'Barra lateral de widgets', 'text' => 'Sólo texto' ) ) );
		$this->add_control( 'template_id', array( 'label' => 'Plantilla', 'type' => $c::SELECT2, 'options' => self::template_options(), 'condition' => array( 'source' => 'template' ) ) );
		$this->add_control( 'sidebar', array( 'label' => 'Barra lateral', 'type' => $c::SELECT, 'default' => '', 'options' => self::sidebar_options(), 'condition' => array( 'source' => 'sidebar' ) ) );
		$this->add_control( 'content_before', array( 'label' => 'Texto antes', 'type' => $c::WYSIWYG, 'default' => '' ) );
		$this->add_control( 'content_after', array( 'label' => 'Texto después', 'type' => $c::WYSIWYG, 'default' => '' ) );
		$this->add_control( 'panel_label', array( 'label' => 'Nombre accesible del panel', 'type' => $c::TEXT, 'default' => '' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_behavior', array( 'label' => 'Comportamiento' ) );
		$this->add_control( 'side', array( 'label' => 'Lado', 'type' => $c::SELECT, 'default' => 'left', 'options' => array( 'left' => 'Izquierda', 'right' => 'Derecha' ) ) );
		$this->add_control( 'animation', array( 'label' => 'Animación', 'type' => $c::SELECT, 'default' => 'slide', 'options' => array( 'slide' => 'Deslizar', 'none' => 'Sin animación' ) ) );
		$this->add_control( 'overlay', array( 'label' => 'Oscurecer el fondo', 'type' => $c::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'close_button', array( 'label' => 'Botón de cerrar', 'type' => $c::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'close_text', array( 'label' => 'Texto del botón de cerrar', 'type' => $c::TEXT, 'default' => '', 'condition' => array( 'close_button' => 'yes' ) ) );
		$this->add_control( 'close_on_overlay', array( 'label' => 'Cerrar al pulsar fuera', 'type' => $c::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'close_on_escape', array( 'label' => 'Cerrar con Escape', 'type' => $c::SWITCHER, 'default' => 'yes' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_panel', array( 'label' => 'Panel', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'panel_width', array( 'label' => 'Ancho', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'vw' ), 'range' => array( 'px' => array( 'min' => 200, 'max' => 900 ) ), 'selectors' => array( '{{WRAPPER}} .digi-offcanvas__bar' => 'width:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'panel_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .digi-offcanvas__bar' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'panel_background', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-offcanvas__bar' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'panel_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-offcanvas__bar' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'overlay_color', array( 'label' => 'Color del fondo oscurecido', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-offcanvas__overlay' => 'background-color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	/** Plantilla publicada de la biblioteca, sin repetirse dentro de sí misma. */
	public static function template_content( $template_id ) {
		$id = absint( $template_id );
		if ( ! $id || 'elementor_library' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || isset( self::$rendering[ $id ] ) || ! class_exists( '\\Elementor\\Plugin' ) ) { return ''; }
		self::$rendering[ $id ] = true;
		try { return (string) \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $id, true ); }
		finally { unset( self::$rendering[ $id ] ); }
	}

	private static function sidebar_content( $sidebar ) {
		if ( '' === $sidebar || ! function_exists( 'is_active_sidebar' ) || ! is_active_sidebar( $sidebar ) ) { return ''; }
		ob_start();
		dynamic_sidebar( $sidebar );
		return (string) ob_get_clean();
	}

	protected function render() {
		$s        = $this->get_settings_for_display();
		$id       = 'digi-offcanvas-' . $this->get_id();
		$trigger  = 'selector' === ( $s['trigger'] ?? 'button' ) ? 'selector' : 'button';
		$selector = trim( wp_strip_all_tags( (string) ( $s['trigger_selector'] ?? '' ) ) );
		$source   = (string) ( $s['source'] ?? 'template' );
		$body     = 'template' === $source ? self::template_content( $s['template_id'] ?? 0 ) : ( 'sidebar' === $source ? self::sidebar_content( (string) ( $s['sidebar'] ?? '' ) ) : '' );
		$before   = trim( (string) ( $s['content_before'] ?? '' ) );
		$after    = trim( (string) ( $s['content_after'] ?? '' ) );
		$text     = trim( wp_strip_all_tags( (string) ( $s['button_text'] ?? '' ) ) );
		$label    = trim( wp_strip_all_tags( (string) ( $s['panel_label'] ?? '' ) ) );
		$label    = '' !== $label ? $label : ( '' !== $text ? $text : 'Panel' );
		$config   = array(
			'selector' => 'selector' === $trigger ? $selector : '',
			'overlay'  => 'yes' === ( $s['close_on_overlay'] ?? 'yes' ),
			'escape'   => 'yes' === ( $s['close_on_escape'] ?? 'yes' ),
		);
		echo '<div class="digi-offcanvas" data-digi-offcanvas="' . esc_attr( wp_json_encode( $config ) ) . '">';
		if ( 'button' === $trigger ) {
			$size  = in_array( $s['button_size'] ?? 'sm', array( 'xs', 'sm', 'md', 'lg', 'xl' ), true ) ? $s['button_size'] : 'sm';
			$icon  = '';
			if ( ! empty( $s['button_icon']['value'] ) ) {
				ob_start();
				\Elementor\Icons_Manager::render_icon( $s['button_icon'], array( 'aria-hidden' => 'true' ) );
				$icon = '<span class="digi-offcanvas__icon">' . (string) ob_get_clean() . '</span>';
			}
			$inner = 'right' === ( $s['button_icon_align'] ?? 'left' ) ? '<span class="digi-offcanvas__text">' . esc_html( $text ) . '</span>' . $icon : $icon . '<span class="digi-offcanvas__text">' . esc_html( $text ) . '</span>';
			echo '<div class="digi-offcanvas__trigger"><button type="button" class="elementor-button elementor-size-' . esc_attr( $size ) . ' digi-offcanvas__button" aria-controls="' . esc_attr( $id ) . '" aria-expanded="false"' . ( '' === $text ? ' aria-label="' . esc_attr( $label ) . '"' : '' ) . ' data-digi-offcanvas-open>' . $inner . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icono de Elementor y texto escapado.
		}
		$side = 'right' === ( $s['side'] ?? 'left' ) ? 'right' : 'left';
		echo '<div class="digi-offcanvas__panel digi-offcanvas__panel--' . esc_attr( $side ) . ( 'none' === ( $s['animation'] ?? 'slide' ) ? ' digi-offcanvas__panel--static' : '' ) . '" id="' . esc_attr( $id ) . '" role="dialog" aria-modal="true" aria-label="' . esc_attr( $label ) . '" hidden>';
		echo '<div class="digi-offcanvas__overlay' . ( 'yes' === ( $s['overlay'] ?? 'yes' ) ? '' : ' digi-offcanvas__overlay--clear' ) . '" data-digi-offcanvas-backdrop></div>';
		echo '<div class="digi-offcanvas__bar" tabindex="-1">';
		if ( 'yes' === ( $s['close_button'] ?? 'yes' ) ) {
			$close = trim( wp_strip_all_tags( (string) ( $s['close_text'] ?? '' ) ) );
			echo '<button type="button" class="digi-offcanvas__close" data-digi-offcanvas-close' . ( '' === $close ? ' aria-label="Cerrar"' : '' ) . '>' . ( '' !== $close ? '<span>' . esc_html( $close ) . '</span>' : '<span class="digi-offcanvas__x" aria-hidden="true"></span>' ) . '</button>';
		}
		if ( '' !== $before ) { echo '<div class="digi-offcanvas__before">' . wp_kses_post( $before ) . '</div>'; }
		if ( '' !== $body ) { echo '<div class="digi-offcanvas__content">' . $body . '</div>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor o WordPress renderizan la plantilla o los widgets.
		if ( '' !== $after ) { echo '<div class="digi-offcanvas__after">' . wp_kses_post( $after ) . '</div>'; }
		echo '</div></div></div>';
	}
}
