<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Tarjeta visual con enlace explícito, redes sociales opcionales y contenido siempre visible. */
class Fancy_Card_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-fancy-card'; }
	public function get_title() { return 'Tarjeta destacada'; }
	public function get_icon() { return 'eicon-icon-box'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'tarjeta', 'destacada', 'imagen', 'enlace' ); }
	public function get_style_depends() { return array( 'digitalisimo-fancy-card' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_card', array( 'label' => 'Contenido' ) );
		$this->add_control( 'icon_type', array( 'label' => 'Elemento visual', 'type' => $c::SELECT, 'default' => 'image', 'options' => array( 'image' => 'Imagen', 'icon' => 'Icono', 'none' => 'Sin elemento visual' ) ) );
		$this->add_control( 'image', array( 'label' => 'Imagen', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ), 'condition' => array( 'icon_type' => 'image' ) ) );
		$this->add_control( 'selected_icon', array( 'label' => 'Icono', 'type' => $c::ICONS, 'condition' => array( 'icon_type' => 'icon' ) ) );
		$this->add_control( 'badge_text', array( 'label' => 'Distintivo', 'type' => $c::TEXT ) );
		$this->add_control( 'title_text', array( 'label' => 'Título', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'default' => 'Tarjeta destacada' ) );
		$this->add_control( 'title_size', array( 'label' => 'Etiqueta del título', 'type' => $c::SELECT, 'default' => 'h3', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'Div' ) ) );
		$this->add_control( 'description_text', array( 'label' => 'Descripción', 'type' => $c::TEXTAREA, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'button_text', array( 'label' => 'Texto del enlace', 'type' => $c::TEXT, 'default' => 'Conoce más' ) );
		$this->add_control( 'link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_social', array( 'label' => 'Redes sociales' ) );
		$social = new \Elementor\Repeater();
		$social->add_control( 'social_icon', array( 'label' => 'Icono', 'type' => $c::ICONS, 'default' => array( 'value' => 'fab fa-facebook-f', 'library' => 'fa-brands' ) ) );
		$social->add_control( 'social_label', array( 'label' => 'Nombre', 'type' => $c::TEXT, 'default' => 'Facebook', 'description' => 'Lo anuncian los lectores de pantalla; sin icono se muestra como texto.' ) );
		$social->add_control( 'social_link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'social_links', array( 'label' => 'Perfiles', 'type' => $c::REPEATER, 'fields' => $social->get_controls(), 'default' => array(), 'title_field' => '{{{ social_label }}}' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_card_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'background_color', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-card' => 'background-color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-card' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'title_color', array( 'label' => 'Color del título', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-card__title' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'social_color', array( 'label' => 'Color de las redes', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-card__social-link' => 'color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'social_size', array( 'label' => 'Tamaño de las redes', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em' ), 'range' => array( 'px' => array( 'min' => 10, 'max' => 64 ) ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-card__social' => 'font-size:{{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$title = trim( wp_strip_all_tags( (string) ( $s['title_text'] ?? '' ) ) );
		$description = trim( wp_strip_all_tags( (string) ( $s['description_text'] ?? '' ) ) );
		$badge = trim( wp_strip_all_tags( (string) ( $s['badge_text'] ?? '' ) ) );
		$link = is_array( $s['link'] ?? null ) ? $s['link'] : array();
		$button = trim( wp_strip_all_tags( (string) ( $s['button_text'] ?? '' ) ) );
		$visual = '';
		if ( 'image' === ( $s['icon_type'] ?? 'image' ) ) {
			$image = is_array( $s['image'] ?? null ) ? $s['image'] : array();
			$id = absint( $image['id'] ?? 0 );
			$alt = $id ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '';
			if ( ! $alt && ! $title ) { $alt = $description; }
			$visual = $id ? wp_get_attachment_image( $id, 'medium', false, array( 'class' => 'digi-fancy-card__image', 'alt' => $alt, 'decoding' => 'async' ) ) : '';
			if ( ! $visual && ! empty( $image['url'] ) ) { $visual = '<img class="digi-fancy-card__image" src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '" decoding="async">'; }
		} elseif ( 'icon' === ( $s['icon_type'] ?? '' ) && ! empty( $s['selected_icon']['value'] ) ) {
			ob_start(); \Elementor\Icons_Manager::render_icon( $s['selected_icon'], array( 'aria-hidden' => 'true' ) ); $visual = ob_get_clean();
		}
		$social = $this->social_links( is_array( $s['social_links'] ?? null ) ? $s['social_links'] : array() );
		if ( ! $title && ! $description && ! $visual && ! $badge && ! $social ) { return; }
		$tag = in_array( $s['title_size'] ?? 'h3', array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), true ) ? ( $s['title_size'] ?? 'h3' ) : 'h3';
		echo '<article class="digi-fancy-card">';
		if ( $visual ) { echo '<div class="digi-fancy-card__visual">' . $visual . '</div>'; }
		if ( $badge ) { echo '<span class="digi-fancy-card__badge">' . esc_html( $badge ) . '</span>'; }
		if ( $title ) { echo '<' . esc_attr( $tag ) . ' class="digi-fancy-card__title">' . esc_html( $title ) . '</' . esc_attr( $tag ) . '>'; }
		if ( $description ) { echo '<p class="digi-fancy-card__description">' . nl2br( esc_html( $description ) ) . '</p>'; }
		if ( $social ) { echo '<ul class="digi-fancy-card__social">' . $social . '</ul>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada enlace se escapa en social_links().
		if ( $button && ! empty( $link['url'] ) ) {
			$this->add_link_attributes( 'fancy_card_link', $link );
			if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( 'fancy_card_link', 'rel', array( 'noopener', 'noreferrer' ) ); }
			echo '<a class="digi-fancy-card__link" ' . $this->get_render_attribute_string( 'fancy_card_link' ) . '>' . esc_html( $button ) . '</a>';
		}
		echo '</article>';
	}

	/** Enlaces de perfiles: el nombre es el aria-label del icono, o el texto visible si no hay icono. */
	private function social_links( array $rows ) {
		$out = '';
		foreach ( $rows as $index => $row ) {
			$link  = is_array( $row['social_link'] ?? null ) ? $row['social_link'] : array();
			$label = trim( wp_strip_all_tags( (string) ( $row['social_label'] ?? '' ) ) );
			if ( empty( $link['url'] ) ) { continue; }
			$icon = '';
			if ( ! empty( $row['social_icon']['value'] ) ) {
				ob_start(); \Elementor\Icons_Manager::render_icon( $row['social_icon'], array( 'aria-hidden' => 'true' ) ); $icon = (string) ob_get_clean();
			}
			if ( '' === $icon && '' === $label ) { continue; }
			$key = 'social_' . $index;
			$this->add_link_attributes( $key, $link );
			$this->add_render_attribute( $key, 'class', 'digi-fancy-card__social-link elementor-repeater-item-' . sanitize_html_class( (string) ( $row['_id'] ?? $index ) ) );
			if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) ); }
			if ( '' !== $icon && '' !== $label ) { $this->add_render_attribute( $key, 'aria-label', $label ); }
			$out .= '<li><a ' . $this->get_render_attribute_string( $key ) . '>' . ( '' !== $icon ? $icon : esc_html( $label ) ) . '</a></li>';
		}
		return $out;
	}

	protected function content_template() {
		?>
		<# var tag = _.contains(['h2','h3','h4','h5','h6','div'],settings.title_size) ? settings.title_size : 'h3';
		var link = settings.link || {}; var rel = [ link.nofollow ? 'nofollow' : '', link.is_external ? 'noopener noreferrer' : '' ].filter(Boolean).join(' ');
		var icon = elementor.helpers.renderIcon( view, settings.selected_icon, { 'aria-hidden': true }, 'i', 'object' );
		var alt = settings.image && settings.image.alt ? settings.image.alt : ( settings.title_text ? '' : ( settings.description_text || '' ) ); #>
		<article class="digi-fancy-card"><# if (settings.icon_type === 'image' && settings.image && settings.image.url) { #><div class="digi-fancy-card__visual"><img class="digi-fancy-card__image" src="{{ settings.image.url }}" alt="{{ alt }}"></div><# } else if (settings.icon_type === 'icon' && icon && icon.rendered) { #><div class="digi-fancy-card__visual">{{{ icon.value }}}</div><# } #><# if(settings.badge_text){ #><span class="digi-fancy-card__badge">{{ settings.badge_text }}</span><# } #><# if(settings.title_text){ #><{{{ tag }}} class="digi-fancy-card__title">{{ settings.title_text }}</{{{ tag }}}><# } #><# if(settings.description_text){ #><p class="digi-fancy-card__description">{{ settings.description_text }}</p><# } #><# var social = _.filter( settings.social_links || [], function( row ) { return row.social_link && row.social_link.url; } ); if ( social.length ) { #><ul class="digi-fancy-card__social"><# _.each( social, function( row ) { var si = row.social_icon && row.social_icon.value ? elementor.helpers.renderIcon( view, row.social_icon, { 'aria-hidden': true }, 'i', 'object' ) : null; #><li><a class="digi-fancy-card__social-link" href="{{ row.social_link.url }}"<# if ( si && si.rendered ) { #> aria-label="{{ row.social_label }}"<# } #>><# if ( si && si.rendered ) { #>{{{ si.value }}}<# } else { #>{{ row.social_label }}<# } #></a></li><# } ); #></ul><# } #><# if(link.url && settings.button_text){ #><a class="digi-fancy-card__link" href="{{ link.url }}"<# if (link.is_external) { #> target="_blank"<# } #><# if (rel) { #> rel="{{ rel }}"<# } #>>{{ settings.button_text }}</a><# } #></article>
		<?php
	}
}
