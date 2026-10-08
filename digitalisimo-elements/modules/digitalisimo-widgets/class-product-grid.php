<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Fichas de productos manuales, sin consultas ni datos estructurados inventados. */
final class Product_Grid_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-product-grid'; }
	public function get_title() { return 'Cuadrícula de productos'; }
	public function get_icon() { return 'eicon-products'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'producto', 'precio', 'cuadrícula', 'catálogo' ); }
	public function get_style_depends() { return array( 'digitalisimo-product-grid' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_products', array( 'label' => 'Productos' ) );
		$r = new \Elementor\Repeater();
		$r->add_control( 'image', array( 'label' => 'Imagen', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ) ) );
		$r->add_control( 'title', array( 'label' => 'Nombre', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$r->add_control( 'price', array( 'label' => 'Precio mostrado', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$r->add_control( 'text', array( 'label' => 'Descripción', 'type' => $c::TEXTAREA, 'dynamic' => array( 'active' => true ) ) );
		$r->add_control( 'readmore_link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$r->add_control( 'button_text', array( 'label' => 'Texto del enlace', 'type' => $c::TEXT, 'default' => 'Ver producto' ) );
		$r->add_control( 'badge_text', array( 'label' => 'Distintivo', 'type' => $c::TEXT ) );
		$r->add_control( 'rating_number', array( 'label' => 'Calificación visual (0–5)', 'type' => $c::NUMBER, 'min' => 0, 'max' => 5, 'step' => 0.5, 'default' => 0, 'description' => 'Sólo se muestra como texto; no publica datos estructurados.' ) );
		$r->add_control( 'rating_count', array( 'label' => 'Cantidad de reseñas', 'type' => $c::NUMBER, 'min' => 0, 'default' => 0 ) );
		$r->add_control( 'time', array( 'label' => 'Tiempo o nota', 'type' => $c::TEXT ) );
		$this->add_control( 'product_items', array( 'label' => 'Fichas', 'type' => $c::REPEATER, 'fields' => $r->get_controls(), 'title_field' => '{{{ title }}}' ) );
		$this->add_control( 'image_size', array( 'label' => 'Tamaño de imagen', 'type' => $c::SELECT, 'default' => 'medium_large', 'options' => array( 'thumbnail' => 'Miniatura', 'medium' => 'Mediana', 'medium_large' => 'Mediana grande', 'large' => 'Grande', 'full' => 'Original' ) ) );
		$this->add_control( 'title_tag', array( 'label' => 'Etiqueta del título', 'type' => $c::SELECT, 'default' => 'h3', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'Div' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_layout', array( 'label' => 'Diseño' ) );
		$this->add_responsive_control( 'columns', array( 'label' => 'Columnas', 'type' => $c::SELECT, 'default' => '3', 'tablet_default' => '2', 'mobile_default' => '1', 'options' => array_combine( range( 1, 6 ), range( 1, 6 ) ), 'selectors' => array( '{{WRAPPER}} .digi-product-grid__list' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr));' ) ) );
		$this->add_responsive_control( 'column_gap', array( 'label' => 'Separación horizontal', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-product-grid__list' => 'column-gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'row_gap', array( 'label' => 'Separación vertical', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-product-grid__list' => 'row-gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'card_background', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-product-grid__item' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'title_color', array( 'label' => 'Color del nombre', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-product-grid__title' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'price_color', array( 'label' => 'Color del precio', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-product-grid__price' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$items = is_array( $s['product_items'] ?? null ) ? $s['product_items'] : array();
		$tag = in_array( $s['title_tag'] ?? 'h3', array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), true ) ? $s['title_tag'] : 'h3';
		$size = in_array( $s['image_size'] ?? 'medium_large', array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ), true ) ? $s['image_size'] : 'medium_large';
		$out = '';
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$title = trim( wp_strip_all_tags( (string) ( $item['title'] ?? '' ) ) );
			$image = is_array( $item['image'] ?? null ) ? $item['image'] : array();
			if ( '' === $title && empty( $image['url'] ) ) { continue; }
			$price = trim( wp_strip_all_tags( (string) ( $item['price'] ?? '' ) ) );
			$text = trim( wp_strip_all_tags( (string) ( $item['text'] ?? '' ) ) );
			$badge = trim( wp_strip_all_tags( (string) ( $item['badge_text'] ?? '' ) ) );
			$note = trim( wp_strip_all_tags( (string) ( $item['time'] ?? '' ) ) );
			$link = is_array( $item['readmore_link'] ?? null ) ? $item['readmore_link'] : array();
			$id = absint( $image['id'] ?? 0 );
			$alt = $id ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '';
			if ( '' === $alt && '' === $title ) { $alt = 'Producto'; }
			if ( '' !== $title ) { $alt = ''; }
			$image_html = $id ? wp_get_attachment_image( $id, $size, false, array( 'class' => 'digi-product-grid__image', 'alt' => $alt, 'decoding' => 'async', 'loading' => $index ? 'lazy' : 'eager' ) ) : '';
			if ( ! $image_html && ! empty( $image['url'] ) ) { $image_html = '<img class="digi-product-grid__image" src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '" decoding="async" loading="' . ( $index ? 'lazy' : 'eager' ) . '">'; }
			$out .= '<li class="digi-product-grid__item">';
			if ( $image_html ) { $out .= '<div class="digi-product-grid__visual">' . $image_html . '</div>'; }
			$out .= '<div class="digi-product-grid__body">';
			if ( '' !== $badge ) { $out .= '<span class="digi-product-grid__badge">' . esc_html( $badge ) . '</span>'; }
			if ( '' !== $title ) { $out .= '<' . $tag . ' class="digi-product-grid__title">' . esc_html( $title ) . '</' . $tag . '>'; }
			if ( '' !== $price ) { $out .= '<p class="digi-product-grid__price">' . esc_html( $price ) . '</p>'; }
			if ( '' !== $text ) { $out .= '<p class="digi-product-grid__text">' . nl2br( esc_html( $text ) ) . '</p>'; }
			$rating = (float) ( is_array( $item['rating_number'] ?? null ) ? ( $item['rating_number']['size'] ?? 0 ) : ( $item['rating_number'] ?? 0 ) );
			$rating = max( 0, min( 5, $rating ) );
			$count = absint( $item['rating_count'] ?? 0 );
			if ( $rating > 0 ) { $out .= '<p class="digi-product-grid__rating" aria-label="Calificación: ' . esc_attr( (string) $rating ) . ' de 5">' . esc_html( (string) $rating ) . ' / 5' . ( $count ? ' · ' . esc_html( (string) $count ) : '' ) . '</p>'; }
			if ( '' !== $note ) { $out .= '<p class="digi-product-grid__note">' . esc_html( $note ) . '</p>'; }
			if ( ! empty( $link['url'] ) ) {
				$button = trim( wp_strip_all_tags( (string) ( $item['button_text'] ?? '' ) ) );
				if ( '' === $button ) { $button = '' !== $title ? 'Ver ' . $title : 'Ver producto'; }
				$key = 'product_grid_link_' . (int) $index;
				$this->add_link_attributes( $key, $link );
				$out .= '<a class="digi-product-grid__link" ' . $this->get_render_attribute_string( $key ) . '>' . esc_html( $button ) . '</a>';
			}
			$out .= '</div></li>';
		}
		if ( '' !== $out ) { echo '<div class="digi-product-grid"><ul class="digi-product-grid__list">' . $out . '</ul></div>'; }
	}

	protected function content_template() {
		?>
		<# var tag = _.contains(['h2','h3','h4','h5','h6','div'], settings.title_tag) ? settings.title_tag : 'h3'; #>
		<div class="digi-product-grid"><ul class="digi-product-grid__list"><# _.each(settings.product_items || [], function(item) { if (!item || (!item.title && !(item.image && item.image.url))) return; var rating = Math.min(5, Math.max(0, parseFloat(item.rating_number && (item.rating_number.size || item.rating_number)) || 0)); #><li class="digi-product-grid__item"><# if(item.image && item.image.url) { #><div class="digi-product-grid__visual"><img class="digi-product-grid__image" src="{{ item.image.url }}" alt="{{ item.title ? '' : 'Producto' }}"></div><# } #><div class="digi-product-grid__body"><# if(item.badge_text) { #><span class="digi-product-grid__badge">{{ item.badge_text }}</span><# } #><# if(item.title) { #><{{{ tag }}} class="digi-product-grid__title">{{ item.title }}</{{{ tag }}}><# } #><# if(item.price) { #><p class="digi-product-grid__price">{{ item.price }}</p><# } #><# if(item.text) { #><p class="digi-product-grid__text">{{ item.text }}</p><# } #><# if(rating) { #><p class="digi-product-grid__rating">{{ rating }} / 5</p><# } #><# if(item.time) { #><p class="digi-product-grid__note">{{ item.time }}</p><# } #><# if(item.readmore_link && item.readmore_link.url) { #><a class="digi-product-grid__link" href="{{ item.readmore_link.url }}">{{ item.button_text || 'Ver producto' }}</a><# } #></div></li><# }); #></ul></div>
		<?php
	}
}
