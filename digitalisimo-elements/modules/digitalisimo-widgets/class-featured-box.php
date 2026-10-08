<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Caja editorial con imagen y contenido visible, sin efectos ni scripts globales. */
final class Featured_Box_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-featured-box'; }
	public function get_title() { return 'Caja destacada'; }
	public function get_icon() { return 'eicon-featured-image'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'destacado', 'imagen', 'servicio', 'caja' ); }
	public function get_style_depends() { return array( 'digitalisimo-featured-box' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_featured', array( 'label' => 'Contenido' ) );
		$this->add_control( 'layout', array( 'label' => 'Diseño', 'type' => $c::SELECT, 'default' => 'overlay', 'options' => array( 'overlay' => 'Sobre imagen', 'split' => 'Dividido' ) ) );
		$this->add_control( 'image', array( 'label' => 'Imagen', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'image_size', array( 'label' => 'Tamaño de imagen', 'type' => $c::SELECT, 'default' => 'large', 'options' => array( 'medium' => 'Mediano', 'large' => 'Grande', 'full' => 'Original' ) ) );
		$this->add_control( 'title_text', array( 'label' => 'Título', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$this->add_control( 'title_size', array( 'label' => 'Etiqueta del título', 'type' => $c::SELECT, 'default' => 'h3', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'Div' ) ) );
		$this->add_control( 'title_link_url', array( 'label' => 'Enlace del título', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'show_sub_title', array( 'label' => 'Mostrar subtítulo', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'sub_title_text', array( 'label' => 'Subtítulo', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'condition' => array( 'show_sub_title' => 'yes' ) ) );
		$this->add_control( 'description_text', array( 'label' => 'Descripción', 'type' => $c::TEXTAREA, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'badge', array( 'label' => 'Mostrar distintivo', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'badge_text', array( 'label' => 'Texto del distintivo', 'type' => $c::TEXT, 'condition' => array( 'badge' => 'yes' ) ) );
		$this->add_control( 'readmore', array( 'label' => 'Mostrar botón', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'readmore_text', array( 'label' => 'Texto del botón', 'type' => $c::TEXT, 'condition' => array( 'readmore' => 'yes' ) ) );
		$this->add_control( 'readmore_link', array( 'label' => 'Enlace del botón', 'type' => $c::URL, 'dynamic' => array( 'active' => true ), 'condition' => array( 'readmore' => 'yes' ) ) );
		$this->add_control( 'advanced_readmore_icon', array( 'label' => 'Icono del botón', 'type' => $c::ICONS, 'condition' => array( 'readmore' => 'yes' ) ) );
		$this->add_control( 'content_position', array( 'label' => 'Posición del texto', 'type' => $c::SELECT, 'default' => 'bottom-left', 'options' => array( 'top-left' => 'Arriba izquierda', 'top-right' => 'Arriba derecha', 'center-left' => 'Centro izquierda', 'center' => 'Centro', 'center-right' => 'Centro derecha', 'bottom-left' => 'Abajo izquierda', 'bottom-right' => 'Abajo derecha' ), 'condition' => array( 'layout' => 'overlay' ) ) );
		$this->add_control( 'skin_content_position', array( 'label' => 'Texto dividido', 'type' => $c::SELECT, 'default' => 'left', 'options' => array( 'left' => 'Izquierda', 'right' => 'Derecha' ), 'condition' => array( 'layout' => 'split' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'text_align', array( 'label' => 'Alineación', 'type' => $c::CHOOSE, 'options' => array( 'left' => array( 'title' => 'Izquierda', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ), 'right' => array( 'title' => 'Derecha', 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .digi-featured-box__content' => 'text-align:{{VALUE}};' ) ) );
		$this->add_control( 'text_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-featured-box__content' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'background_color', array( 'label' => 'Fondo del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-featured-box__content' => 'background-color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'content_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-featured-box__content' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$title = trim( wp_strip_all_tags( (string) ( $s['title_text'] ?? '' ) ) );
		$description = trim( wp_strip_all_tags( (string) ( $s['description_text'] ?? '' ) ) );
		$image = is_array( $s['image'] ?? null ) ? $s['image'] : array();
		if ( '' === $title && '' === $description && empty( $image['id'] ) && empty( $image['url'] ) ) { return; }
		$layout = 'split' === ( $s['layout'] ?? '' ) ? 'split' : 'overlay';
		$position = in_array( $s['content_position'] ?? 'bottom-left', array( 'top-left', 'top-right', 'center-left', 'center', 'center-right', 'bottom-left', 'bottom-right' ), true ) ? ( $s['content_position'] ?? 'bottom-left' ) : 'bottom-left';
		$side = 'right' === ( $s['skin_content_position'] ?? 'left' ) ? 'right' : 'left';
		$tag = in_array( $s['title_size'] ?? 'h3', array( 'h2', 'h3', 'h4', 'div' ), true ) ? ( $s['title_size'] ?? 'h3' ) : 'h3';
		echo '<article class="digi-featured-box digi-featured-box--' . esc_attr( $layout ) . ' digi-featured-box--' . esc_attr( $layout === 'split' ? $side : $position ) . '">';
		$image_id = absint( $image['id'] ?? 0 );
		$size = in_array( $s['image_size'] ?? 'large', array( 'medium', 'large', 'full' ), true ) ? ( $s['image_size'] ?? 'large' ) : 'large';
		$alt = $image_id ? (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '';
		$markup = $image_id ? wp_get_attachment_image( $image_id, $size, false, array( 'class' => 'digi-featured-box__image', 'alt' => $alt, 'decoding' => 'async' ) ) : '';
		if ( ! $markup && ! empty( $image['url'] ) ) { $markup = '<img class="digi-featured-box__image" src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '" decoding="async">'; }
		if ( $markup ) { echo '<figure class="digi-featured-box__media">' . $markup . '</figure>'; }
		$badge = trim( wp_strip_all_tags( (string) ( $s['badge_text'] ?? '' ) ) );
		if ( 'yes' === ( $s['badge'] ?? '' ) && $badge ) { echo '<span class="digi-featured-box__badge">' . esc_html( $badge ) . '</span>'; }
		echo '<div class="digi-featured-box__content">';
		$subtitle = trim( wp_strip_all_tags( (string) ( $s['sub_title_text'] ?? '' ) ) );
		if ( 'yes' === ( $s['show_sub_title'] ?? '' ) && $subtitle ) { echo '<span class="digi-featured-box__subtitle">' . esc_html( $subtitle ) . '</span>'; }
		if ( $title ) {
			echo '<' . $tag . ' class="digi-featured-box__title">';
			$link = is_array( $s['title_link_url'] ?? null ) ? $s['title_link_url'] : array();
			if ( ! empty( $link['url'] ) ) { $this->add_link_attributes( 'featured_title_link', $link ); echo '<a ' . $this->get_render_attribute_string( 'featured_title_link' ) . '>' . esc_html( $title ) . '</a>'; }
			else { echo esc_html( $title ); }
			echo '</' . $tag . '>';
		}
		if ( $description ) { echo '<p class="digi-featured-box__description">' . esc_html( $description ) . '</p>'; }
		$button = trim( wp_strip_all_tags( (string) ( $s['readmore_text'] ?? '' ) ) );
		$link = is_array( $s['readmore_link'] ?? null ) ? $s['readmore_link'] : array();
		if ( 'yes' === ( $s['readmore'] ?? '' ) && $button && ! empty( $link['url'] ) ) {
			$this->add_link_attributes( 'featured_button_link', $link );
			echo '<a class="digi-featured-box__button" ' . $this->get_render_attribute_string( 'featured_button_link' ) . '>' . esc_html( $button );
			$icon = is_array( $s['advanced_readmore_icon'] ?? null ) ? $s['advanced_readmore_icon'] : array();
			if ( ! empty( $icon['value'] ) ) { echo '<span aria-hidden="true">'; \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ); echo '</span>'; }
			echo '</a>';
		}
		echo '</div></article>';
	}

	protected function content_template() {
		?>
		<# var layout = settings.layout === 'split' ? 'split' : 'overlay'; var side = layout === 'split' ? (settings.skin_content_position === 'right' ? 'right' : 'left') : (settings.content_position || 'bottom-left'); var tag = _.contains(['h2','h3','h4','div'],settings.title_size) ? settings.title_size : 'h3'; var image = settings.image && settings.image.url ? settings.image.url : ''; #>
		<article class="digi-featured-box digi-featured-box--{{ layout }} digi-featured-box--{{ side }}"><# if(image){ #><figure class="digi-featured-box__media"><img class="digi-featured-box__image" src="{{ image }}" alt=""></figure><# } #><# if(settings.badge === 'yes' && settings.badge_text){ #><span class="digi-featured-box__badge">{{ settings.badge_text }}</span><# } #><div class="digi-featured-box__content"><# if(settings.show_sub_title === 'yes' && settings.sub_title_text){ #><span class="digi-featured-box__subtitle">{{ settings.sub_title_text }}</span><# } #><# if(settings.title_text){ #><{{{ tag }}} class="digi-featured-box__title">{{ settings.title_text }}</{{{ tag }}}><# } #><# if(settings.description_text){ #><p class="digi-featured-box__description">{{ settings.description_text }}</p><# } #><# if(settings.readmore === 'yes' && settings.readmore_text && settings.readmore_link && settings.readmore_link.url){ #><a class="digi-featured-box__button" href="{{ settings.readmore_link.url }}">{{ settings.readmore_text }}</a><# } #></div></article>
		<?php
	}
}
