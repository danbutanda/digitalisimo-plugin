<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-carousel-engine.php';
require_once __DIR__ . '/class-widget-alias.php';

/**
 * Logotipos de marcas o clientes en cuadrícula o en carrusel. Reúne Carrusel de marcas, Carrusel de
 * logotipos, Cuadrícula de marcas y Cuadrícula de logotipos, que siguen registrados como alias ocultos
 * de este widget (misma clase PHP, mismo nombre y mismas clases CSS) para el contenido ya guardado.
 *
 * Cada logotipo lleva imagen, nombre, descripción, enlace y texto del enlace. En cuadrícula, si se
 * muestra el texto del enlace éste es el enlace y la imagen no se enlaza (un solo destino por logotipo);
 * sin texto, la tarjeta de la imagen es el enlace. El motor de carrusel sólo se carga en carrusel.
 */
class Logos_Widget extends \Elementor\Widget_Base {
	use Legacy_Classes;

	const CSS_PREFIX = 'digi-logos';
	const LAYOUTS    = array( 'grid', 'carousel' );
	const STYLES     = array( 'plain', 'box', 'border', 'tictactoe' );
	const DETAILS    = array( 'always', 'overlay', 'tooltip', 'click' );
	const SIZES      = array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' );
	const TAGS       = array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'strong' );

	public function get_name() { return 'digitalisimo-logos'; }
	public function get_title() { return 'Logotipos'; }
	public function get_icon() { return 'eicon-logo'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'logos', 'logotipos', 'marcas', 'clientes', 'carrusel', 'cuadrícula', 'grid' ); }

	/** La instancia de registro (sin ajustes) declara todo; cada instancia sólo lo que su presentación usa. */
	private function carousel() {
		if ( ! method_exists( $this, 'is_type_instance' ) || $this->is_type_instance() ) {
			return true;
		}
		$settings = (array) $this->get_settings();
		return 'carousel' === ( $settings['layout'] ?? 'grid' );
	}

	public function get_style_depends() {
		return $this->carousel() ? array( 'digitalisimo-carousel-engine', 'digitalisimo-logos' ) : array( 'digitalisimo-logos' );
	}

	public function get_script_depends() {
		return $this->carousel() ? array( 'digitalisimo-carousel-engine' ) : array();
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_logos', array( 'label' => 'Logotipos' ) );
		$this->add_control( 'gallery_images', array( 'label' => 'Seleccionar varios logotipos', 'type' => $c::GALLERY, 'description' => 'Se muestran primero; su texto alternativo se toma de Medios.' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'image', array( 'label' => 'Logotipo', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'name', array( 'label' => 'Nombre', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$repeater->add_control( 'description', array( 'label' => 'Descripción', 'type' => $c::TEXTAREA, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'link_text', array( 'label' => 'Texto del enlace', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$repeater->add_control( 'caption', array( 'label' => 'Mostrar nombre y descripción', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ) );
		$this->add_control( 'items', array( 'label' => 'Logotipos con datos individuales', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'title_field' => '{{{ name }}}' ) );
		$this->add_control( 'image_size', array( 'label' => 'Tamaño de imagen', 'type' => $c::SELECT, 'default' => 'medium', 'options' => array( 'thumbnail' => 'Miniatura', 'medium' => 'Mediana', 'medium_large' => 'Mediana grande', 'large' => 'Grande', 'full' => 'Original' ) ) );
		$this->add_control( 'label', array( 'label' => 'Nombre accesible', 'type' => $c::TEXT, 'default' => 'Logotipos', 'description' => 'Lo anuncian los lectores de pantalla.' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_layout', array( 'label' => 'Presentación' ) );
		$this->add_control( 'layout', array( 'label' => 'Presentación', 'type' => $c::SELECT, 'default' => 'grid', 'options' => array( 'grid' => 'Cuadrícula', 'carousel' => 'Carrusel' ) ) );
		$this->add_responsive_control( 'columns', array( 'label' => 'Logotipos por fila', 'type' => $c::SELECT, 'default' => '4', 'tablet_default' => '2', 'mobile_default' => '1', 'options' => array_combine( range( 1, 6 ), range( 1, 6 ) ), 'selectors' => array( '{{WRAPPER}} .digi-logos__list' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr));', '{{WRAPPER}} .digi-carousel' => '--digi-carousel-visible:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-logos__list' => 'gap:{{SIZE}}{{UNIT}};', '{{WRAPPER}} .digi-carousel' => '--digi-carousel-gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'row_gap', array( 'label' => 'Separación vertical', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'condition' => array( 'layout' => 'grid' ), 'selectors' => array( '{{WRAPPER}} .digi-logos__list' => 'row-gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'style', array( 'label' => 'Tarjetas', 'type' => $c::SELECT, 'default' => 'box', 'options' => array( 'plain' => 'Sin tarjeta', 'box' => 'Tarjetas', 'border' => 'Bordes', 'tictactoe' => 'Separadores' ), 'condition' => array( 'layout' => 'grid' ) ) );
		$this->add_control( 'details', array( 'label' => 'Nombre y descripción', 'type' => $c::SELECT, 'default' => 'always', 'options' => array( 'always' => 'Siempre visibles', 'overlay' => 'Sobre el logotipo al apuntar o enfocar', 'tooltip' => 'Como globo al apuntar o enfocar', 'click' => 'Al abrir' ), 'condition' => array( 'layout' => 'grid' ) ) );
		$this->add_responsive_control( 'height', array( 'label' => 'Altura', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 50, 'max' => 500 ) ), 'selectors' => array( '{{WRAPPER}} .digi-logos--grid .digi-logos__card' => 'height:{{SIZE}}{{UNIT}};', '{{WRAPPER}} .digi-logos--carousel .digi-logos__image' => 'max-height:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'cover', array( 'label' => 'Cubrir la tarjeta', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'condition' => array( 'layout' => 'grid' ) ) );
		$this->add_control( 'navigation', array( 'label' => 'Navegación', 'type' => $c::SELECT, 'default' => 'arrows', 'options' => array( 'arrows' => 'Flechas', 'none' => 'Desactivada' ), 'condition' => array( 'layout' => 'carousel' ) ) );
		$this->add_control( 'match_height', array( 'label' => 'Igualar altura', 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'condition' => array( 'layout' => 'carousel' ) ) );
		$this->add_control( 'show_name', array( 'label' => 'Mostrar nombre', 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'name_tag', array( 'label' => 'Etiqueta del nombre', 'type' => $c::SELECT, 'default' => 'h3', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'Div', 'span' => 'Span', 'strong' => 'Negrita' ), 'condition' => array( 'show_name' => 'yes' ) ) );
		$this->add_control( 'show_description', array( 'label' => 'Mostrar descripción', 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'show_link_text', array( 'label' => 'Mostrar texto del enlace', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'description' => 'En cuadrícula, el texto es el enlace y la imagen no se enlaza; vacío, dice «Visitar» y el nombre.' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'item_background', array( 'label' => 'Fondo de la tarjeta', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-logos__card' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'border_color', array( 'label' => 'Color del borde', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-logos__card' => 'border-color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'item_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-logos__card' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'name_color', array( 'label' => 'Color del nombre', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-logos__name' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'text_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-logos__content, {{WRAPPER}} .digi-logos__card' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	private static function choice( $value, $allowed, $fallback ) { return in_array( $value, $allowed, true ) ? $value : $fallback; }

	/** Logotipos válidos: primero la selección múltiple, después los elementos con datos. */
	public static function items( array $settings ) {
		$items = array();
		foreach ( is_array( $settings['gallery_images'] ?? null ) ? $settings['gallery_images'] : array() as $image ) {
			if ( is_array( $image ) && ( ! empty( $image['id'] ) || ! empty( $image['url'] ) ) ) {
				$items[] = array( 'image' => $image, 'name' => '', 'description' => '', 'link' => array(), 'link_text' => '', 'caption' => true, '_id' => '' );
			}
		}
		foreach ( is_array( $settings['items'] ?? null ) ? $settings['items'] : array() as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$image = is_array( $item['image'] ?? null ) ? $item['image'] : array();
			if ( empty( $image['url'] ) && ! empty( $image['id'] ) && function_exists( 'wp_get_attachment_url' ) ) { $image['url'] = (string) wp_get_attachment_url( absint( $image['id'] ) ); }
			$name = trim( wp_strip_all_tags( (string) ( $item['name'] ?? '' ) ) );
			if ( empty( $image['url'] ) && empty( $image['id'] ) && '' === $name ) { continue; }
			$items[] = array(
				'image'       => $image,
				'name'        => $name,
				'description' => trim( wp_strip_all_tags( (string) ( $item['description'] ?? '' ) ) ),
				'link'        => is_array( $item['link'] ?? null ) ? $item['link'] : array(),
				'link_text'   => trim( wp_strip_all_tags( (string) ( $item['link_text'] ?? '' ) ) ),
				'caption'     => 'yes' === ( $item['caption'] ?? 'yes' ),
				'_id'         => (string) ( $item['_id'] ?? '' ),
			);
		}
		return $items;
	}

	/** Texto alternativo: el de Medios, el nombre, el ALT guardado o el título del adjunto. */
	private static function alt( array $image, $name ) {
		$id  = absint( $image['id'] ?? 0 );
		$alt = $id ? trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '';
		if ( '' === $alt ) { $alt = (string) $name; }
		if ( '' === $alt && ! empty( $image['alt'] ) ) { $alt = trim( wp_strip_all_tags( (string) $image['alt'] ) ); }
		if ( '' === $alt && $id ) { $alt = trim( (string) get_the_title( $id ) ); }
		return $alt;
	}

	private function image_html( array $image, $alt, $size ) {
		$id    = absint( $image['id'] ?? 0 );
		$attrs = array( 'class' => $this->cls( 'image' ), 'alt' => $alt, 'decoding' => 'async' );
		$html  = $id ? wp_get_attachment_image( $id, $size, false, $attrs ) : '';
		if ( $html ) { return $html; }
		if ( empty( $image['url'] ) ) { return ''; }
		$dimensions = ! empty( $image['width'] ) && ! empty( $image['height'] ) ? ' width="' . absint( $image['width'] ) . '" height="' . absint( $image['height'] ) . '"' : '';
		return '<img class="' . esc_attr( $attrs['class'] ) . '" src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '" decoding="async"' . $dimensions . '>';
	}

	private function link_open( $key, array $link, $class, $label = '' ) {
		$this->add_link_attributes( $key, $link );
		if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) ); }
		return '<a class="' . esc_attr( $class ) . '" ' . $this->get_render_attribute_string( $key ) . ( '' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '' ) . '>';
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$items = self::items( $s );
		if ( ! $items ) { return; }
		$layout = self::choice( $s['layout'] ?? 'grid', self::LAYOUTS, 'grid' );
		$size   = self::choice( $s['image_size'] ?? 'medium', self::SIZES, 'medium' );
		$tag    = self::choice( $s['name_tag'] ?? 'h3', self::TAGS, 'h3' );
		$label  = trim( wp_strip_all_tags( (string) ( $s['label'] ?? '' ) ) );
		$label  = '' !== $label ? $label : 'Logotipos';
		$show   = array( 'name' => 'yes' === ( $s['show_name'] ?? 'yes' ), 'description' => 'yes' === ( $s['show_description'] ?? 'yes' ), 'link_text' => 'yes' === ( $s['show_link_text'] ?? '' ) );
		if ( 'carousel' === $layout ) {
			$this->render_carousel( $items, $s, $size, $tag, $label, $show );
			return;
		}
		$style   = self::choice( $s['style'] ?? 'box', self::STYLES, 'box' );
		$details = self::choice( $s['details'] ?? 'always', self::DETAILS, 'always' );
		$mods    = array( 'grid', $style, $details );
		if ( 'yes' === ( $s['cover'] ?? '' ) ) { $mods[] = 'cover'; }
		echo '<div class="' . esc_attr( $this->cls( '', $mods ) ) . '" aria-label="' . esc_attr( $label ) . '"><ul class="' . esc_attr( $this->cls( 'list' ) ) . '">';
		foreach ( $items as $index => $item ) {
			$name         = $item['name'];
			$description  = $show['description'] ? $item['description'] : '';
			$linked       = ! empty( $item['link']['url'] ) && 'click' !== $details;
			$text_link    = $linked && $show['link_text'];
			$image_linked = $linked && ! $text_link;
			$shown_name   = $show['name'] && '' !== $name && $item['caption'];
			$caption      = $item['caption'] && ( $shown_name || '' !== $description ) || $text_link;
			$alt          = self::alt( $item['image'], $name );
			if ( $shown_name && ! $image_linked ) { $alt = ''; }
			$image = $this->image_html( $item['image'], $alt, $size );
			if ( '' === $image && '' === $name ) { continue; }
			$item_mods = $linked ? array( 'linked' ) : array();
			echo '<li class="' . esc_attr( $this->cls( 'item', $item_mods ) . ( '' !== $item['_id'] ? ' elementor-repeater-item-' . sanitize_html_class( $item['_id'] ) : '' ) ) . '">';
			if ( 'click' === $details ) {
				echo '<details class="' . esc_attr( $this->cls( 'details' ) ) . '"><summary class="' . esc_attr( $this->cls( 'summary' ) ) . '" aria-label="' . esc_attr( '' !== $name ? $name : 'Mostrar logotipo' ) . '"><span class="' . esc_attr( $this->cls( 'card' ) ) . '">' . $image . '</span></summary>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- imagen de WordPress o escapada.
				echo '<div class="' . esc_attr( $this->cls( 'content' ) ) . '">' . $this->caption_html( $item, $index, $tag, $shown_name, $description, true, $text_link, $name ) . '</div></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada parte se escapa al construirse.
			} else {
				echo '<figure class="' . esc_attr( $this->cls( 'figure' ) ) . '">';
				if ( $image_linked ) { echo $this->link_open( 'logo_' . $index, $item['link'], $this->cls( 'card' ), '' === $alt && '' === $name ? 'Visitar marca' : '' ) . $image . '</a>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos de Elementor e imagen escapada.
				else { echo '<div class="' . esc_attr( $this->cls( 'card' ) ) . '">' . $image . '</div>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- imagen de WordPress o escapada.
				if ( $caption ) {
					echo '<figcaption class="' . esc_attr( $this->cls( 'content', 'tooltip' === $details && $linked ? array( 'tooltip' ) : array() ) ) . '">' . $this->caption_html( $item, $index, $tag, $shown_name, $description, false, $text_link, $name ) . '</figcaption>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada parte se escapa al construirse.
				}
				echo '</figure>';
			}
			echo '</li>';
		}
		echo '</ul></div>';
	}

	private function caption_html( array $item, $index, $tag, $shown_name, $description, $click, $text_link, $name ) {
		$out = '';
		if ( $shown_name ) { $out .= '<' . $tag . ' class="' . esc_attr( $this->cls( 'name' ) ) . '">' . esc_html( $name ) . '</' . $tag . '>'; }
		if ( '' !== $description && $item['caption'] ) { $out .= '<span class="' . esc_attr( $this->cls( 'description' ) ) . '">' . esc_html( $description ) . '</span>'; }
		$url = (string) ( $item['link']['url'] ?? '' );
		if ( ( $text_link || $click ) && '' !== $url ) {
			$text = '' !== $item['link_text'] ? $item['link_text'] : ( '' !== $name ? 'Visitar ' . $name : 'Visitar sitio' );
			$out .= $this->link_open( 'logo_link_' . $index, $item['link'], $this->cls( 'link' ) ) . esc_html( $text ) . '</a>';
		}
		return $out;
	}

	private function render_carousel( array $items, array $s, $size, $tag, $label, array $show ) {
		$stretch = 'yes' === ( $s['match_height'] ?? 'yes' );
		echo '<div class="' . esc_attr( $this->cls( '', array( 'carousel' ) ) ) . '">';
		Carousel_Engine::open( $label, count( $items ) );
		foreach ( $items as $index => $item ) {
			$name   = $item['name'];
			$alt    = self::alt( $item['image'], $name );
			$image  = $this->image_html( $item['image'], $alt, $size );
			$linked = ! empty( $item['link']['url'] ) && ( '' !== $alt || '' !== $name );
			if ( '' === $image && '' === $name ) { continue; }
			echo '<li class="digi-carousel__slide ' . esc_attr( $this->cls( 'item' ) . ( '' !== $item['_id'] ? ' elementor-repeater-item-' . sanitize_html_class( $item['_id'] ) : '' ) ) . '">';
			$card = $this->cls( 'card', $stretch ? array( 'stretch' ) : array() );
			echo $linked ? $this->link_open( 'logo_' . $index, $item['link'], $card ) : '<div class="' . esc_attr( $card ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos de Elementor.
			echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- imagen de WordPress o escapada.
			if ( $show['name'] && '' !== $name && $item['caption'] ) { echo '<' . $tag . ' class="' . esc_attr( $this->cls( 'name' ) ) . '">' . esc_html( $name ) . '</' . $tag . '>'; }
			if ( $show['description'] && '' !== $item['description'] && $item['caption'] ) { echo '<span class="' . esc_attr( $this->cls( 'description' ) ) . '">' . esc_html( $item['description'] ) . '</span>'; }
			if ( $linked && $show['link_text'] && '' !== $item['link_text'] ) { echo '<span class="' . esc_attr( $this->cls( 'link-text' ) ) . '">' . esc_html( $item['link_text'] ) . '</span>'; }
			echo $linked ? '</a>' : '</div>';
			echo '</li>';
		}
		Carousel_Engine::close( 'none' !== ( $s['navigation'] ?? 'arrows' ) && count( $items ) > 1 );
		echo '</div>';
	}

	protected function content_template() {
		?>
		<# <?php echo $this->cls_template_js(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON de clases fijas. ?>
		var layouts = ['grid','carousel'], styles = ['plain','box','border','tictactoe'], detailsList = ['always','overlay','tooltip','click'], tags = ['h2','h3','h4','h5','h6','div','span','strong'];
		var layout = _.contains( layouts, settings.layout ) ? settings.layout : 'grid';
		var style = _.contains( styles, settings.style ) ? settings.style : 'box';
		var details = _.contains( detailsList, settings.details ) ? settings.details : 'always';
		var tag = _.contains( tags, settings.name_tag ) ? settings.name_tag : 'h3';
		var label = String( settings.label || '' ).trim() || 'Logotipos';
		var show = { name: settings.show_name !== '', description: settings.show_description !== '', linkText: settings.show_link_text === 'yes' };
		var items = _.map( settings.gallery_images || [], function ( image ) { return { image: image, name: '', description: '', link: {}, link_text: '', caption: true, _id: '' }; } ).concat( _.map( settings.items || [], function ( item ) { return { image: item.image || {}, name: String( item.name || '' ).trim(), description: String( item.description || '' ).trim(), link: item.link || {}, link_text: String( item.link_text || '' ).trim(), caption: item.caption !== '', _id: item._id || '' }; } ) );
		items = _.filter( items, function ( item ) { return item.image && item.image.url || item.name; } );
		var rel = function ( link ) { return [ link && link.is_external ? 'noopener noreferrer' : '', link && link.nofollow ? 'nofollow' : '' ].filter( Boolean ).join( ' ' ); };
		var open = function ( link, klass, label ) { return '<a class="' + klass + '" href="' + _.escape( link.url ) + '"' + ( link.is_external ? ' target="_blank"' : '' ) + ( rel( link ) ? ' rel="' + rel( link ) + '"' : '' ) + ( label ? ' aria-label="' + _.escape( label ) + '"' : '' ) + '>'; };
		var img = function ( item, alt ) { return item.image && item.image.url ? '<img class="' + cls( 'image' ) + '" src="' + _.escape( item.image.url ) + '" alt="' + _.escape( alt ) + '" decoding="async">' : ''; };
		var captionHtml = function ( item, shownName, description, textLink ) { var out = ''; if ( shownName ) { out += '<' + tag + ' class="' + cls( 'name' ) + '">' + _.escape( item.name ) + '</' + tag + '>'; } if ( description ) { out += '<span class="' + cls( 'description' ) + '">' + _.escape( description ) + '</span>'; } if ( textLink && item.link.url ) { out += open( item.link, cls( 'link' ) ) + _.escape( item.link_text || ( item.name ? 'Visitar ' + item.name : 'Visitar sitio' ) ) + '</a>'; } return out; };
		#>
		<# if ( items.length && layout === 'carousel' ) { var stretch = settings.match_height !== ''; #>
		<div class="{{ cls( '', ['carousel'] ) }}"><section class="digi-carousel" role="region" aria-roledescription="carrusel" aria-label="{{ label }}" style="--digi-carousel-count:{{ items.length }}"><div class="digi-carousel__viewport" tabindex="0" data-digi-carousel-track><ul class="digi-carousel__slides">
		<# _.each( items, function ( item, index ) { var alt = item.image.alt || item.name || ''; var linked = !! item.link.url && ( alt || item.name ); var card = cls( 'card', stretch ? ['stretch'] : [] ); #>
		<li class="digi-carousel__slide {{ cls( 'item' ) }}<# if ( item._id ) { #> elementor-repeater-item-{{ item._id }}<# } #>">{{{ linked ? open( item.link, card ) : '<div class="' + card + '">' }}}{{{ img( item, alt ) }}}<# if ( show.name && item.name && item.caption ) { #><{{{ tag }}} class="{{ cls( 'name' ) }}">{{ item.name }}</{{{ tag }}}><# } #><# if ( show.description && item.description && item.caption ) { #><span class="{{ cls( 'description' ) }}">{{ item.description }}</span><# } #><# if ( linked && show.linkText && item.link_text ) { #><span class="{{ cls( 'link-text' ) }}">{{ item.link_text }}</span><# } #>{{{ linked ? '</a>' : '</div>' }}}</li>
		<# } ); #>
		</ul></div><# if ( settings.navigation !== 'none' && items.length > 1 ) { #><div class="digi-carousel__controls"><button class="digi-carousel__button" type="button" data-digi-carousel-prev aria-label="Anterior" disabled aria-disabled="true">&#x2039;</button><button class="digi-carousel__button" type="button" data-digi-carousel-next aria-label="Siguiente">&#x203a;</button></div><# } #></section></div>
		<# } else if ( items.length ) { var mods = ['grid', style, details]; if ( settings.cover === 'yes' ) { mods.push( 'cover' ); } #>
		<div class="{{ cls( '', mods ) }}" aria-label="{{ label }}"><ul class="{{ cls( 'list' ) }}">
		<# _.each( items, function ( item, index ) {
			var description = show.description ? item.description : '';
			var linked = !! item.link.url && details !== 'click';
			var textLink = linked && show.linkText;
			var imageLinked = linked && ! textLink;
			var shownName = show.name && item.name && item.caption;
			var caption = item.caption && ( shownName || description ) || textLink;
			var alt = item.image.alt || item.name || '';
			if ( shownName && ! imageLinked ) { alt = ''; }
			var image = img( item, alt );
			if ( ! image && ! item.name ) { return; }
		#>
		<li class="{{ cls( 'item', linked ? ['linked'] : [] ) }}<# if ( item._id ) { #> elementor-repeater-item-{{ item._id }}<# } #>">
		<# if ( details === 'click' ) { #><details class="{{ cls( 'details' ) }}"><summary class="{{ cls( 'summary' ) }}" aria-label="{{ item.name || 'Mostrar logotipo' }}"><span class="{{ cls( 'card' ) }}">{{{ image }}}</span></summary><div class="{{ cls( 'content' ) }}">{{{ captionHtml( item, shownName, description, true ) }}}</div></details>
		<# } else { #><figure class="{{ cls( 'figure' ) }}">{{{ imageLinked ? open( item.link, cls( 'card' ), ! alt && ! item.name ? 'Visitar marca' : '' ) + image + '</a>' : '<div class="' + cls( 'card' ) + '">' + image + '</div>' }}}<# if ( caption ) { #><figcaption class="{{ cls( 'content', details === 'tooltip' && linked ? ['tooltip'] : [] ) }}">{{{ captionHtml( item, shownName, description, textLink ) }}}</figcaption><# } #></figure><# } #>
		</li>
		<# } ); #>
		</ul></div>
		<# } #>
		<?php
	}
}
