<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Cuadrícula de logos con selección múltiple y detalles nativos opcionales. */
final class Logo_Grid_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-logo-grid'; }
	public function get_title() { return 'Cuadrícula de logotipos'; }
	public function get_icon() { return 'eicon-gallery-grid'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'logos', 'marcas', 'clientes', 'grid' ); }
	public function get_style_depends() { return array( 'digitalisimo-logo-grid' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_logos', array( 'label' => 'Logotipos' ) );
		$this->add_control( 'gallery_images', array( 'label' => 'Seleccionar varios logotipos', 'type' => $c::GALLERY, 'description' => 'Las imágenes seleccionadas se muestran primero y conservan el ALT de Medios.' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'image', array( 'label' => 'Logotipo', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'name', array( 'label' => 'Nombre', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'description', array( 'label' => 'Descripción', 'type' => $c::TEXTAREA, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'logo_tooltip', array( 'label' => 'Mostrar nombre y descripción', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'logo_list', array( 'label' => 'Logotipos con datos individuales', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'title_field' => '{{{ name }}}' ) );
		$this->add_control( 'thumbnail_size', array( 'label' => 'Tamaño de imagen', 'type' => $c::SELECT, 'default' => 'medium', 'options' => array( 'thumbnail' => 'Miniatura', 'medium' => 'Mediana', 'large' => 'Grande', 'full' => 'Original' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_layout', array( 'label' => 'Cuadrícula' ) );
		$this->add_control( 'layout', array( 'label' => 'Diseño', 'type' => $c::SELECT, 'default' => 'box', 'options' => array( 'box' => 'Tarjetas', 'border' => 'Bordes', 'tictactoe' => 'Separadores' ) ) );
		$this->add_responsive_control( 'columns', array( 'label' => 'Columnas', 'type' => $c::SELECT, 'default' => '4', 'tablet_default' => '2', 'mobile_default' => '2', 'options' => array_combine( range( 1, 6 ), range( 1, 6 ) ), 'selectors' => array( '{{WRAPPER}} .digi-logo-grid__list' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr));' ) ) );
		$this->add_responsive_control( 'column_gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-logo-grid__list' => 'gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'height', array( 'label' => 'Altura por logotipo', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 50, 'max' => 500 ) ), 'selectors' => array( '{{WRAPPER}} .digi-logo-grid__card' => 'height:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'logo_size_cover', array( 'label' => 'Cubrir tarjeta', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'grid_bg_color', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-logo-grid__card' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'grid_border_color', array( 'label' => 'Color del borde', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-logo-grid__card' => 'border-color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'item_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-logo-grid__card' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	private static function items( $settings ) {
		$items = array();
		foreach ( array( 'gallery_images', 'logo_list' ) as $key ) {
			foreach ( is_array( $settings[ $key ] ?? null ) ? $settings[ $key ] : array() as $item ) {
				if ( ! is_array( $item ) ) { continue; }
				$image = is_array( $item['image'] ?? null ) ? $item['image'] : $item;
				if ( empty( $image['url'] ) && ! empty( $image['id'] ) ) { $image['url'] = wp_get_attachment_url( absint( $image['id'] ) ); }
				if ( empty( $image['url'] ) ) { continue; }
				$items[] = array( 'image' => $image, 'name' => trim( (string) ( $item['name'] ?? '' ) ), 'description' => trim( (string) ( $item['description'] ?? '' ) ), 'link' => is_array( $item['link'] ?? null ) ? $item['link'] : array(), 'caption' => 'yes' === ( $item['logo_tooltip'] ?? '' ) );
			}
		}
		return $items;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$items = self::items( $settings );
		if ( ! $items ) { return; }
		$layout = in_array( $settings['layout'] ?? '', array( 'box', 'border', 'tictactoe' ), true ) ? $settings['layout'] : 'box';
		$size = in_array( $settings['thumbnail_size'] ?? '', array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $settings['thumbnail_size'] : 'medium';
		$cover = 'yes' === ( $settings['logo_size_cover'] ?? '' ) ? ' digi-logo-grid--cover' : '';
		echo '<div class="digi-logo-grid digi-logo-grid--' . esc_attr( $layout ) . $cover . '"><ul class="digi-logo-grid__list">';
		foreach ( $items as $index => $item ) {
			$image = $item['image'];
			$id = absint( $image['id'] ?? 0 );
			$alt = $id ? trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '';
			if ( '' === $alt ) { $alt = $item['name']; }
			if ( '' === $alt && ! empty( $image['alt'] ) ) { $alt = trim( (string) $image['alt'] ); }
			if ( '' === $alt && $id ) { $alt = trim( (string) get_the_title( $id ) ); }
			$attrs = array( 'class' => 'digi-logo-grid__image', 'alt' => $alt, 'decoding' => 'async' );
			$html = $id ? wp_get_attachment_image( $id, $size, false, $attrs ) : '';
			if ( ! $html ) {
				$dimensions = '';
				if ( ! empty( $image['width'] ) && ! empty( $image['height'] ) ) { $dimensions = ' width="' . absint( $image['width'] ) . '" height="' . absint( $image['height'] ) . '"'; }
				$html = '<img class="digi-logo-grid__image" src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '" decoding="async"' . $dimensions . '>';
			}
			$link = $item['link'];
			$linked = ! empty( $link['url'] );
			$caption = $item['caption'] && ( '' !== $item['name'] || '' !== $item['description'] );
			echo '<li class="digi-logo-grid__item"><figure class="digi-logo-grid__figure">';
			if ( $linked ) {
				$key = 'logo_grid_link_' . (int) $index;
				$this->add_link_attributes( $key, $link );
				if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) ); }
				echo '<a class="digi-logo-grid__card" ' . $this->get_render_attribute_string( $key );
				if ( '' === $alt ) { echo ' aria-label="Visitar marca"'; }
				echo '>';
			} else { echo '<div class="digi-logo-grid__card">'; }
			echo $html;
			echo $linked ? '</a>' : '</div>';
			if ( $caption ) {
				echo '<figcaption class="digi-logo-grid__caption' . ( $linked ? ' digi-logo-grid__caption--tooltip' : '' ) . '">';
				if ( '' !== $item['name'] ) { echo '<strong>' . esc_html( $item['name'] ) . '</strong>'; }
				if ( '' !== $item['description'] ) { echo '<span>' . esc_html( $item['description'] ) . '</span>'; }
				echo '</figcaption>';
			}
			echo '</figure></li>';
		}
		echo '</ul></div>';
	}

	protected function content_template() {
		?>
		<# var items = _.map( settings.gallery_images || [], function( image ) { return { image: image }; } ).concat( settings.logo_list || [] );
		items = _.filter( items, function( item ) { return item && item.image && item.image.url; } );
		var layout = _.contains( ['box','border','tictactoe'], settings.layout ) ? settings.layout : 'box'; #>
		<# if ( items.length ) { #><div class="digi-logo-grid digi-logo-grid--{{ layout }}<# if ( settings.logo_size_cover === 'yes' ) { #> digi-logo-grid--cover<# } #>"><ul class="digi-logo-grid__list">
		<# _.each( items, function( item ) { var image = item.image; var name = item.name || ''; var alt = name || image.alt || ''; var link = item.link && item.link.url ? item.link : null; var caption = item.logo_tooltip === 'yes' && ( name || item.description ); var rel = link ? [link.nofollow ? 'nofollow' : '',link.is_external ? 'noopener noreferrer' : ''].join(' ').trim() : ''; #>
		<li class="digi-logo-grid__item"><figure class="digi-logo-grid__figure">
		<# if ( link ) { #><a class="digi-logo-grid__card" href="{{ link.url }}" <# if ( link.is_external ) { #>target="_blank"<# } #> <# if ( rel ) { #>rel="{{ rel }}"<# } #> <# if ( ! alt ) { #>aria-label="Visitar marca"<# } #>><# } else { #><div class="digi-logo-grid__card"><# } #>
		<img class="digi-logo-grid__image" src="{{ image.url }}" alt="{{ alt }}" decoding="async" <# if ( image.width && image.height ) { #>width="{{ image.width }}" height="{{ image.height }}"<# } #>>
		<# if ( link ) { #></a><# } else { #></div><# } #>
		<# if ( caption ) { #><figcaption class="digi-logo-grid__caption<# if ( link ) { #> digi-logo-grid__caption--tooltip<# } #>"><# if ( name ) { #><strong>{{ name }}</strong><# } #><# if ( item.description ) { #><span>{{ item.description }}</span><# } #></figcaption><# } #>
		</figure></li><# } ); #></ul></div><# } #>
		<?php
	}
}
