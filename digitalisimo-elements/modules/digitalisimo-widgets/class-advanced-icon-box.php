<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Tarjeta informativa con icono o imagen; no requiere JavaScript. */
final class Advanced_Icon_Box_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-advanced-icon-box'; }
	public function get_title() { return 'Caja de icono avanzada'; }
	public function get_icon() { return 'eicon-icon-box'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'icono', 'imagen', 'servicio', 'tarjeta', 'icon box' ); }
	public function get_style_depends() { return array( 'digitalisimo-advanced-icon-box' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_icon_box', array( 'label' => 'Contenido' ) );
		$this->add_control( 'icon_type', array( 'label' => 'Elemento visual', 'type' => $c::SELECT, 'default' => 'icon', 'options' => array( 'icon' => 'Icono', 'image' => 'Imagen' ) ) );
		$this->add_control( 'selected_icon', array( 'label' => 'Icono', 'type' => $c::ICONS, 'default' => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ), 'condition' => array( 'icon_type' => 'icon' ) ) );
		$this->add_control( 'image', array( 'label' => 'Imagen', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ), 'condition' => array( 'icon_type' => 'image' ) ) );
		$this->add_control( 'image_alt', array( 'label' => 'Texto alternativo si no hay título', 'type' => $c::TEXT, 'condition' => array( 'icon_type' => 'image' ), 'description' => 'Si queda vacío, se utiliza el ALT de la biblioteca de Medios.' ) );
		$this->add_control( 'title_text', array( 'label' => 'Título', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'default' => 'Título de la tarjeta', 'label_block' => true ) );
		$this->add_control( 'title_size', array( 'label' => 'Etiqueta del título', 'type' => $c::SELECT, 'default' => 'h3', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'Div' ) ) );
		$this->add_control( 'show_sub_title', array( 'label' => 'Mostrar subtítulo', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'sub_title_text', array( 'label' => 'Subtítulo', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'condition' => array( 'show_sub_title' => 'yes' ) ) );
		$this->add_control( 'description_text', array( 'label' => 'Descripción', 'type' => $c::WYSIWYG, 'dynamic' => array( 'active' => true ), 'default' => 'Describe aquí el servicio o la característica.' ) );
		$this->add_control( 'position', array( 'label' => 'Posición del icono', 'type' => $c::SELECT, 'default' => 'top', 'options' => array( 'top' => 'Arriba', 'bottom' => 'Abajo', 'left' => 'Izquierda', 'right' => 'Derecha' ) ) );
		$this->add_control( 'icon_inline', array( 'label' => 'Icono junto al título', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'condition' => array( 'position' => array( 'left', 'right' ) ) ) );
		$this->add_control( 'show_separator', array( 'label' => 'Separador bajo el título', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_icon_box_links', array( 'label' => 'Enlaces y distintivo' ) );
		$this->add_control( 'global_link', array( 'label' => 'Enlace de toda la tarjeta', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'description' => 'Los enlaces del título y «Leer más» se omiten cuando se activa el enlace de toda la tarjeta.' ) );
		$this->add_control( 'global_link_url', array( 'label' => 'URL de la tarjeta', 'type' => $c::URL, 'dynamic' => array( 'active' => true ), 'condition' => array( 'global_link' => 'yes' ) ) );
		$this->add_control( 'title_link', array( 'label' => 'Enlazar el título', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'condition' => array( 'global_link!' => 'yes' ) ) );
		$this->add_control( 'title_link_url', array( 'label' => 'URL del título', 'type' => $c::URL, 'dynamic' => array( 'active' => true ), 'condition' => array( 'title_link' => 'yes', 'global_link!' => 'yes' ) ) );
		$this->add_control( 'readmore', array( 'label' => 'Mostrar «Leer más»', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'condition' => array( 'global_link!' => 'yes' ) ) );
		$this->add_control( 'readmore_text', array( 'label' => 'Texto del enlace', 'type' => $c::TEXT, 'default' => 'Leer más', 'condition' => array( 'readmore' => 'yes', 'global_link!' => 'yes' ) ) );
		$this->add_control( 'readmore_link', array( 'label' => 'URL de «Leer más»', 'type' => $c::URL, 'dynamic' => array( 'active' => true ), 'condition' => array( 'readmore' => 'yes', 'global_link!' => 'yes' ) ) );
		$this->add_control( 'badge', array( 'label' => 'Mostrar distintivo', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'badge_text', array( 'label' => 'Texto del distintivo', 'type' => $c::TEXT, 'condition' => array( 'badge' => 'yes' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_icon_box_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'text_align', array( 'label' => 'Alineación', 'type' => $c::CHOOSE, 'options' => array( 'left' => array( 'title' => 'Izquierda', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ), 'right' => array( 'title' => 'Derecha', 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-icon-box' => 'text-align:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'icon_size', array( 'label' => 'Tamaño del icono', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 16, 'max' => 160 ) ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-icon-box__visual' => '--digi-icon-size:{{SIZE}}px;' ) ) );
		$this->add_responsive_control( 'icon_gap', array( 'label' => 'Separación del icono', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-icon-box' => '--digi-icon-gap:{{SIZE}}px;' ) ) );
		$this->add_responsive_control( 'box_padding', array( 'label' => 'Relleno', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 120 ) ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-icon-box' => 'padding:{{SIZE}}px;' ) ) );
		$this->add_responsive_control( 'box_radius', array( 'label' => 'Radio de borde', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-icon-box' => 'border-radius:{{SIZE}}px;' ) ) );
		$this->add_control( 'box_background', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-icon-box' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'icon_color', array( 'label' => 'Color del icono', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-icon-box__visual' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'title_color', array( 'label' => 'Color del título', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-icon-box__title' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'description_color', array( 'label' => 'Color de la descripción', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-icon-box__description' => 'color:{{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'title_typography', 'label' => 'Tipografía del título', 'selector' => '{{WRAPPER}} .digi-advanced-icon-box__title' ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'subtitle_typography', 'label' => 'Tipografía del subtítulo', 'selector' => '{{WRAPPER}} .digi-advanced-icon-box__subtitle', 'condition' => array( 'show_sub_title' => 'yes' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'description_typography', 'label' => 'Tipografía de la descripción', 'selector' => '{{WRAPPER}} .digi-advanced-icon-box__description' ) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array( 'name' => 'box_shadow', 'label' => 'Sombra de la tarjeta', 'selector' => '{{WRAPPER}} .digi-advanced-icon-box' ) );
		$this->end_controls_section();
	}

	private static function tag( $value ) { return in_array( $value, array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), true ) ? $value : 'h3'; }
	private static function position( $value ) { return in_array( $value, array( 'top', 'bottom', 'left', 'right' ), true ) ? $value : 'top'; }
	private static function link_attributes( $value ) {
		$url = is_array( $value ) ? esc_url( $value['url'] ?? '' ) : '';
		if ( '' === $url ) { return ''; }
		$external = ! empty( $value['is_external'] );
		$rel = array();
		if ( $external ) { $rel[] = 'noopener'; $rel[] = 'noreferrer'; }
		if ( ! empty( $value['nofollow'] ) ) { $rel[] = 'nofollow'; }
		return ' href="' . $url . '"' . ( $external ? ' target="_blank"' : '' ) . ( $rel ? ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"' : '' );
	}

	private static function image_markup( $settings, $title ) {
		$image = is_array( $settings['image'] ?? null ) ? $settings['image'] : array();
		$id = absint( $image['id'] ?? 0 );
		$alt = '' !== $title ? '' : trim( (string) ( $settings['image_alt'] ?? '' ) );
		if ( '' === $alt && $id && '' === $title ) { $alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true ); }
		if ( $id ) {
			return wp_get_attachment_image( $id, 'medium', false, array( 'alt' => $alt, 'decoding' => 'async' ) ) ?: '';
		}
		$url = esc_url( $image['url'] ?? '' );
		return $url ? '<img src="' . $url . '" alt="' . esc_attr( $alt ) . '" decoding="async">' : '';
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$title = trim( (string) ( $s['title_text'] ?? '' ) );
		$sub = 'yes' === ( $s['show_sub_title'] ?? '' ) ? trim( (string) ( $s['sub_title_text'] ?? '' ) ) : '';
		$description = trim( (string) ( $s['description_text'] ?? '' ) );
		$badge = 'yes' === ( $s['badge'] ?? '' ) ? trim( (string) ( $s['badge_text'] ?? '' ) ) : '';
		$visual = '';
		if ( 'image' === ( $s['icon_type'] ?? 'icon' ) ) {
			$visual = self::image_markup( $s, $title );
		} elseif ( ! empty( $s['selected_icon']['value'] ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $s['selected_icon'], array( 'aria-hidden' => 'true' ) );
			$visual = ob_get_clean();
		}
		$readmore = 'yes' === ( $s['readmore'] ?? '' ) ? trim( (string) ( $s['readmore_text'] ?? '' ) ) : '';
		$global = 'yes' === ( $s['global_link'] ?? '' ) ? self::link_attributes( $s['global_link_url'] ?? array() ) : '';
		if ( '' === $title && '' === $sub && '' === $description && '' === $visual && '' === $badge && '' === $readmore ) { return; }
		$position = self::position( $s['position'] ?? 'top' );
		$inline = 'yes' === ( $s['icon_inline'] ?? '' ) && in_array( $position, array( 'left', 'right' ), true );
		$tag = self::tag( $s['title_size'] ?? 'h3' );
		$accessible_name = $title ?: ( $sub ?: ( trim( (string) ( $s['image_alt'] ?? '' ) ) ?: ( $badge ?: 'Abrir tarjeta' ) ) );
		echo '<div class="digi-advanced-icon-box digi-advanced-icon-box--' . esc_attr( $position ) . ( $inline ? ' digi-advanced-icon-box--inline' : '' ) . '">';
		if ( $global ) { echo '<a class="digi-advanced-icon-box__overlay"' . $global . ' aria-label="' . esc_attr( $accessible_name ) . '"></a>'; }
		if ( $badge ) { echo '<span class="digi-advanced-icon-box__badge">' . esc_html( $badge ) . '</span>'; }
		if ( $visual ) { echo '<div class="digi-advanced-icon-box__visual" aria-hidden="' . ( 'image' === ( $s['icon_type'] ?? 'icon' ) && '' === $title ? 'false' : 'true' ) . '">' . $visual . '</div>'; }
		echo '<div class="digi-advanced-icon-box__content">';
		if ( $title ) {
			echo '<' . $tag . ' class="digi-advanced-icon-box__title">';
			$title_link = ! $global && 'yes' === ( $s['title_link'] ?? '' ) ? self::link_attributes( $s['title_link_url'] ?? array() ) : '';
			if ( $title_link ) { echo '<a' . $title_link . '>'; }
			echo esc_html( $title );
			if ( $title_link ) { echo '</a>'; }
			echo '</' . $tag . '>';
		}
		if ( $sub ) { echo '<div class="digi-advanced-icon-box__subtitle">' . esc_html( $sub ) . '</div>'; }
		if ( 'yes' === ( $s['show_separator'] ?? '' ) ) { echo '<span class="digi-advanced-icon-box__separator" aria-hidden="true"></span>'; }
		if ( $description ) { echo '<div class="digi-advanced-icon-box__description">' . wp_kses_post( $description ) . '</div>'; }
		if ( $readmore && ! $global ) {
			$link = self::link_attributes( $s['readmore_link'] ?? array() );
			echo $link ? '<a class="digi-advanced-icon-box__more"' . $link . '>' . esc_html( $readmore ) . '</a>' : '<span class="digi-advanced-icon-box__more">' . esc_html( $readmore ) . '</span>';
		}
		echo '</div></div>';
	}

	protected function content_template() {
		?>
		<# var tag = _.contains( ['h2','h3','h4','h5','h6','div'], settings.title_size ) ? settings.title_size : 'h3';
		var position = _.contains( ['top','bottom','left','right'], settings.position ) ? settings.position : 'top';
		var inline = settings.icon_inline === 'yes' && ( position === 'left' || position === 'right' );
		var icon = elementor.helpers.renderIcon( view, settings.selected_icon, { 'aria-hidden': true }, 'i', 'object' );
		var visual = settings.icon_type === 'image' ? ( settings.image && settings.image.url ? '<img src="' + _.escape( settings.image.url ) + '" alt="' + _.escape( settings.title_text ? '' : ( settings.image_alt || '' ) ) + '">' : '' ) : ( icon && icon.rendered ? icon.value : '' );
		var safeUrl = function( link ) { var url = link && link.url ? String( link.url ) : ''; return /^(https?:\/\/|\/|#|mailto:|tel:)/i.test( url ) ? url : ''; };
		var linkRel = function( link ) { return [ link && link.is_external ? 'noopener noreferrer' : '', link && link.nofollow ? 'nofollow' : '' ].filter( Boolean ).join( ' ' ); };
		var globalUrl = settings.global_link === 'yes' ? safeUrl( settings.global_link_url ) : '';
		var global = !!globalUrl;
		var titleUrl = !global && settings.title_link === 'yes' ? safeUrl( settings.title_link_url ) : '';
		var moreUrl = !global ? safeUrl( settings.readmore_link ) : '';
		var subtitle = settings.show_sub_title === 'yes' ? settings.sub_title_text : '';
		var badge = settings.badge === 'yes' ? settings.badge_text : '';
		var more = settings.readmore === 'yes' && ! global ? settings.readmore_text : ''; #>
		<# if ( settings.title_text || subtitle || settings.description_text || visual || badge || more ) { #>
		<div class="digi-advanced-icon-box digi-advanced-icon-box--{{ position }} <# if ( inline ) { #>digi-advanced-icon-box--inline<# } #>">
		<# if ( global ) { #><a class="digi-advanced-icon-box__overlay" href="{{ globalUrl }}"<# if ( settings.global_link_url.is_external ) { #> target="_blank"<# } #><# if ( linkRel( settings.global_link_url ) ) { #> rel="{{ linkRel( settings.global_link_url ) }}"<# } #> aria-label="{{ settings.title_text || subtitle || settings.image_alt || badge || 'Abrir tarjeta' }}"></a><# } #>
		<# if ( badge ) { #><span class="digi-advanced-icon-box__badge">{{ badge }}</span><# } #>
		<# if ( visual ) { #><div class="digi-advanced-icon-box__visual" aria-hidden="{{ settings.icon_type === 'image' && !String( settings.title_text || '' ).trim() ? 'false' : 'true' }}">{{{ visual }}}</div><# } #>
		<div class="digi-advanced-icon-box__content">
		<# if ( settings.title_text ) { #><{{{ tag }}} class="digi-advanced-icon-box__title"><# if ( titleUrl ) { #><a href="{{ titleUrl }}"<# if ( settings.title_link_url.is_external ) { #> target="_blank"<# } #><# if ( linkRel( settings.title_link_url ) ) { #> rel="{{ linkRel( settings.title_link_url ) }}"<# } #>><# } #>{{ settings.title_text }}<# if ( titleUrl ) { #></a><# } #></{{{ tag }}}><# } #>
		<# if ( subtitle ) { #><div class="digi-advanced-icon-box__subtitle">{{ subtitle }}</div><# } #>
		<# if ( settings.show_separator === 'yes' ) { #><span class="digi-advanced-icon-box__separator" aria-hidden="true"></span><# } #>
		<# if ( settings.description_text ) { #><div class="digi-advanced-icon-box__description">{{{ settings.description_text }}}</div><# } #>
		<# if ( more ) { #><# if ( moreUrl ) { #><a class="digi-advanced-icon-box__more" href="{{ moreUrl }}"<# if ( settings.readmore_link.is_external ) { #> target="_blank"<# } #><# if ( linkRel( settings.readmore_link ) ) { #> rel="{{ linkRel( settings.readmore_link ) }}"<# } #>>{{ more }}</a><# } else { #><span class="digi-advanced-icon-box__more">{{ more }}</span><# } #><# } #>
		</div></div><# } #>
		<?php
	}
}
