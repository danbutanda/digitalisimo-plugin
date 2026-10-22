<?php
namespace Digitalisimo\Elements\Woo\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * «Lista de deseos»: la página de la lista. El servidor pinta un contenedor vacío, igual para todos; el navegador pide
 * las fichas por `POST woo/cards` con los productos del visitante (de su navegador o de su cuenta).
 */
class List_Widget extends \Digitalisimo\Elements\Woo\Widget {
	const TITLE_TAGS = array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div' );

	private static $count = 0;

	public function get_name() { return 'digitalisimo-woo-wishlist'; }
	public function get_title() { return 'Lista de deseos'; }
	public function get_icon() { return 'eicon-heart'; }
	public function get_keywords() { return array( 'lista de deseos', 'favoritos', 'guardados', 'woocommerce' ); }
	public function get_style_depends() { return array( 'digitalisimo-woo-wishlist' ); }
	public function get_script_depends() { return array( 'digitalisimo-woo-wishlist' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_content', array( 'label' => 'Lista de deseos' ) );
		$this->add_control( 'layout', array( 'label' => 'Presentación', 'type' => $c::SELECT, 'default' => 'list', 'options' => array( 'list' => 'Lista', 'grid' => 'Rejilla' ) ) );
		$this->add_responsive_control( 'columns', array( 'label' => 'Columnas', 'type' => $c::SELECT, 'default' => '3', 'tablet_default' => '2', 'mobile_default' => '1', 'options' => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6' ), 'selectors' => array( '{{WRAPPER}} .digi-wishlist' => '--digi-wl-cols: {{VALUE}};' ), 'condition' => array( 'layout' => 'grid' ) ) );
		$this->add_control( 'title', array( 'label' => 'Título', 'type' => $c::TEXT, 'default' => '', 'description' => 'Vacío: el título sólo lo anuncian los lectores de pantalla.' ) );
		$this->add_control( 'title_tag', array( 'label' => 'Etiqueta del título', 'type' => $c::SELECT, 'default' => 'h2', 'options' => array_combine( self::TITLE_TAGS, self::TITLE_TAGS ) ) );
		$this->add_control( 'item_tag', array( 'label' => 'Etiqueta del nombre', 'type' => $c::SELECT, 'default' => Cards::DEFAULTS['item_tag'], 'options' => array_combine( Cards::TAGS, Cards::TAGS ) ) );
		$this->add_control( 'parts', array( 'label' => 'Partes', 'type' => $c::SELECT2, 'multiple' => true, 'label_block' => true, 'default' => Cards::DEFAULTS['parts'], 'options' => array( 'image' => 'Imagen', 'title' => 'Nombre', 'price' => 'Precio', 'stock' => 'Disponibilidad', 'cart' => 'Añadir al carrito', 'remove' => 'Quitar' ) ) );
		$this->add_control( 'image_size', array( 'label' => 'Tamaño de imagen', 'type' => $c::SELECT, 'default' => Cards::DEFAULTS['image_size'], 'options' => array( 'woocommerce_thumbnail' => 'Miniatura de la tienda', 'woocommerce_gallery_thumbnail' => 'Miniatura de galería', 'thumbnail' => 'Miniatura', 'medium' => 'Mediano' ) ) );
		$this->add_control( 'empty_text', array( 'label' => 'Texto sin productos', 'type' => $c::TEXTAREA, 'default' => 'Tu lista de deseos está vacía.' ) );
		$this->add_control( 'clear', array( 'label' => 'Botón «Vaciar lista»', 'type' => $c::SWITCHER, 'default' => '' ) );
		$this->add_control( 'help', array( 'type' => $c::RAW_HTML, 'raw' => 'Es una página distinta para cada visitante: márcala con «Evitar indexación» en DIGITALÍSIMO SEO. Los productos se piden al abrirla y la lista nunca va en la dirección.', 'content_classes' => 'elementor-descriptor' ) );
		if ( class_exists( Module::class ) && class_exists( Source::class ) && ! \Elementor\Core\Frontend\Performance::should_optimize_controls() ) {
			$notice = Module::store_notice();
			if ( '' !== $notice ) {
				$this->add_control( 'store_notice', array( 'type' => $c::RAW_HTML, 'raw' => esc_html( $notice ), 'content_classes' => 'elementor-descriptor' ) );
			}
		}
		$this->end_controls_section();

		$this->start_controls_section( 'section_style', array( 'label' => 'Lista', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}} .digi-wishlist' => '--digi-wl-gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'title_typography', 'label' => 'Tipografía del título', 'selector' => '{{WRAPPER}} .digi-wishlist__title' ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'name_typography', 'label' => 'Tipografía del nombre', 'selector' => '{{WRAPPER}} .digi-wishlist__name' ) );
		$this->add_control( 'item_background', array( 'label' => 'Fondo de la ficha', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-wishlist__item' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'item_radius', array( 'label' => 'Radio de la ficha', 'type' => $c::SLIDER, 'size_units' => array( 'px' ), 'selectors' => array( '{{WRAPPER}} .digi-wishlist__item' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'image_width', array( 'label' => 'Ancho de la imagen (lista)', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 40, 'max' => 320 ) ), 'selectors' => array( '{{WRAPPER}} .digi-wishlist' => '--digi-wl-img: {{SIZE}}{{UNIT}};' ), 'condition' => array( 'layout' => 'list' ) ) );
		$this->end_controls_section();
	}

	protected function render_widget() {
		$s      = $this->get_settings_for_display();
		$layout = 'grid' === ( $s['layout'] ?? 'list' ) ? 'grid' : 'list';
		$tag    = in_array( $s['title_tag'] ?? 'h2', self::TITLE_TAGS, true ) ? $s['title_tag'] : 'h2';
		$title  = trim( wp_strip_all_tags( (string) ( $s['title'] ?? '' ) ) );
		$id     = 'digi-wl-' . $this->get_id() . ( self::$count++ ? '-' . self::$count : '' );
		$doc    = 0;
		if ( isset( \Elementor\Plugin::$instance->documents ) && ( $current = \Elementor\Plugin::$instance->documents->get_current() ) ) {
			$doc = (int) $current->get_main_id();
		}
		$doc  = $doc ? $doc : (int) get_the_ID();
		$html = '<section class="digi-wishlist digi-wishlist--' . $layout . '" aria-labelledby="' . esc_attr( $id ) . '" data-digi-wishlist data-post="' . $doc . '" data-widget="' . esc_attr( $this->get_id() ) . '">';
		if ( 'div' === $tag && '' !== $title ) {
			$html .= '<div id="' . esc_attr( $id ) . '" class="digi-wishlist__title" role="heading" aria-level="2" tabindex="-1">' . esc_html( $title ) . '</div>';
		} else {
			$html .= '<' . ( 'div' === $tag ? 'h2' : $tag ) . ' id="' . esc_attr( $id ) . '" class="digi-wishlist__title' . ( '' === $title ? ' screen-reader-text' : '' ) . '" tabindex="-1">' . esc_html( '' === $title ? 'Lista de deseos' : $title ) . '</' . ( 'div' === $tag ? 'h2' : $tag ) . '>';
		}
		$html .= '<ul class="digi-wishlist__items" data-digi-wish-items></ul>';
		$html .= '<p class="digi-wishlist__empty" data-digi-wish-empty hidden>' . esc_html( trim( (string) ( $s['empty_text'] ?? '' ) ) ) . '</p>';
		if ( 'yes' === ( $s['clear'] ?? '' ) ) {
			$html .= '<button type="button" class="digi-wishlist__clear" data-digi-wish-clear hidden>Vaciar lista</button>';
		}
		$html .= '<noscript><p class="digi-wishlist__noscript">Activa JavaScript para ver tu lista de deseos.</p></noscript></section>';
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
