<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Galería de imágenes de Medios con URLs y ALT del sitio actual. */
final class Custom_Gallery_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-custom-gallery'; }
	public function get_title() { return 'Galería personalizada'; }
	public function get_icon() { return 'eicon-gallery-grid'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'galería', 'imágenes', 'fotos', 'grid' ); }
	public function get_style_depends() { return array( 'digitalisimo-custom-gallery' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_gallery', array( 'label' => 'Imágenes' ) );
		$this->add_control( 'gallery_images', array( 'label' => 'Seleccionar varias imágenes', 'type' => $c::GALLERY, 'description' => 'Conserva el ALT y pie de foto de la biblioteca de Medios.' ) );
		$items = new \Elementor\Repeater();
		$items->add_control( 'gallery_image', array( 'label' => 'Imagen', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ) ) );
		$items->add_control( 'image_title', array( 'label' => 'Título', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$items->add_control( 'image_text', array( 'label' => 'Descripción', 'type' => $c::TEXTAREA, 'dynamic' => array( 'active' => true ) ) );
		$items->add_control( 'image_link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'gallery_items', array( 'label' => 'Imágenes con datos individuales', 'type' => $c::REPEATER, 'fields' => $items->get_controls(), 'title_field' => '{{{ image_title }}}' ) );
		$this->add_control( 'image_size', array( 'label' => 'Tamaño de archivo', 'type' => $c::SELECT, 'default' => 'large', 'options' => array( 'medium' => 'Mediano', 'large' => 'Grande', 'full' => 'Original' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_gallery_style', array( 'label' => 'Diseño', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'columns', array( 'label' => 'Columnas', 'type' => $c::SELECT, 'default' => '3', 'tablet_default' => '2', 'mobile_default' => '1', 'options' => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6' ), 'selectors' => array( '{{WRAPPER}} .digi-custom-gallery__list' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr));' ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}} .digi-custom-gallery__list' => 'gap:{{SIZE}}px;' ) ) );
		$this->add_control( 'fit', array( 'label' => 'Ajuste de imagen', 'type' => $c::SELECT, 'default' => 'cover', 'options' => array( 'cover' => 'Cubrir', 'contain' => 'Completa' ), 'selectors' => array( '{{WRAPPER}} .digi-custom-gallery__image' => 'object-fit:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	private static function items( $settings ) {
		$items = array();
		foreach ( is_array( $settings['gallery_images'] ?? null ) ? $settings['gallery_images'] : array() as $image ) {
			if ( is_array( $image ) && ( ! empty( $image['id'] ) || ! empty( $image['url'] ) ) ) { $items[] = array( 'image' => $image, 'title' => '', 'description' => '', 'link' => array() ); }
		}
		foreach ( is_array( $settings['gallery_items'] ?? null ) ? $settings['gallery_items'] : array() as $item ) {
			if ( ! is_array( $item ) || ! is_array( $item['gallery_image'] ?? null ) ) { continue; }
			$items[] = array( 'image' => $item['gallery_image'], 'title' => $item['image_title'] ?? '', 'description' => $item['image_text'] ?? '', 'link' => is_array( $item['image_link'] ?? null ) ? $item['image_link'] : array() );
		}
		return $items;
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$items = self::items( $s );
		if ( ! $items ) { return; }
		$size = in_array( $s['image_size'] ?? 'large', array( 'medium', 'large', 'full' ), true ) ? ( $s['image_size'] ?? 'large' ) : 'large';
		echo '<div class="digi-custom-gallery"><ul class="digi-custom-gallery__list">';
		foreach ( $items as $index => $item ) {
			$image = $item['image'];
			$id = absint( $image['id'] ?? 0 );
			$alt = $id ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '';
			$title = trim( wp_strip_all_tags( (string) $item['title'] ) );
			if ( '' === $alt && $title ) { $alt = $title; }
			$markup = $id ? wp_get_attachment_image( $id, $size, false, array( 'class' => 'digi-custom-gallery__image', 'alt' => $alt, 'decoding' => 'async' ) ) : '';
			if ( ! $markup && ! empty( $image['url'] ) ) { $markup = '<img class="digi-custom-gallery__image" src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '" decoding="async">'; }
			if ( ! $markup ) { continue; }
			echo '<li class="digi-custom-gallery__item"><figure>';
			$link = $item['link'];
			if ( ! empty( $link['url'] ) ) {
				$key = 'gallery_link_' . $index;
				$this->add_link_attributes( $key, $link );
				echo '<a ' . $this->get_render_attribute_string( $key ) . ( $title ? ' aria-label="' . esc_attr( $title ) . '"' : '' ) . '>' . $markup . '</a>';
			} else { echo $markup; }
			$description = trim( wp_strip_all_tags( (string) $item['description'] ) );
			if ( ! $title && ! $description && $id ) { $description = (string) wp_get_attachment_caption( $id ); }
			if ( $title || $description ) { echo '<figcaption>' . ( $title ? '<strong>' . esc_html( $title ) . '</strong>' : '' ) . ( $description ? '<span>' . esc_html( $description ) . '</span>' : '' ) . '</figcaption>'; }
			echo '</figure></li>';
		}
		echo '</ul></div>';
	}

	protected function content_template() {
		?>
		<# var items = (settings.gallery_images || []).map(function(image){ return { image:image }; }).concat((settings.gallery_items || []).map(function(item){ return {image:item.gallery_image,title:item.image_title,description:item.image_text,link:item.image_link}; })); #>
		<# if ( items.length ) { #><div class="digi-custom-gallery"><ul class="digi-custom-gallery__list"><# _.each(items,function(item){ if (!item.image || !item.image.url) return; #><li class="digi-custom-gallery__item"><figure><# if (item.link && item.link.url) { #><a href="{{ item.link.url }}"><# } #><img class="digi-custom-gallery__image" src="{{ item.image.url }}" alt="{{ item.title || '' }}"><# if (item.link && item.link.url) { #></a><# } #><# if (item.title || item.description) { #><figcaption><# if (item.title) { #><strong>{{ item.title }}</strong><# } #><# if (item.description) { #><span>{{ item.description }}</span><# } #></figcaption><# } #></figure></li><# }); #></ul></div><# } #>
		<?php
	}
}
