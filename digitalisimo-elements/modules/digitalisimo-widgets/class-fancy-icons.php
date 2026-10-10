<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Cuadrícula de enlaces con icono o texto, sin scripts ni enlaces vacíos. */
final class Fancy_Icons_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-fancy-icons'; }
	public function get_title() { return 'Iconos destacados'; }
	public function get_icon() { return 'eicon-social-icons'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'iconos', 'redes', 'enlaces', 'fancy icons' ); }
	public function get_style_depends() { return array( 'digitalisimo-fancy-icons' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_icons', array( 'label' => 'Iconos destacados' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'social_type', array( 'label' => 'Contenido', 'type' => $c::SELECT, 'default' => 'icon', 'options' => array( 'icon' => 'Icono', 'text' => 'Texto' ) ) );
		$repeater->add_control( 'social_icon', array( 'label' => 'Icono', 'type' => $c::ICONS, 'condition' => array( 'social_type' => 'icon' ) ) );
		$repeater->add_control( 'social_name', array( 'label' => 'Nombre accesible / texto', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$repeater->add_control( 'social_link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'icon_color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}} .digi-fancy-icons__content' => 'color:{{VALUE}};' ) ) );
		$repeater->add_control( 'icon_background_color', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} {{CURRENT_ITEM}}' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'share_items', array( 'label' => 'Elementos', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'social_type' => 'icon', 'social_name' => 'Red social' ) ), 'title_field' => '{{{ social_name }}}' ) );
		$this->add_responsive_control( 'columns', array( 'label' => 'Columnas', 'type' => $c::SELECT, 'default' => '2', 'tablet_default' => '2', 'mobile_default' => '1', 'options' => array_combine( range( 1, 6 ), range( 1, 6 ) ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-icons' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr));' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-icons' => 'gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'min_height', array( 'label' => 'Altura mínima', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 30, 'max' => 400 ) ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-icons__item' => 'min-height:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-icons__item' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'text_color', array( 'label' => 'Color general', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-icons__content' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'background_color', array( 'label' => 'Fondo general', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-icons__item' => 'background-color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$items = is_array( $s['share_items'] ?? null ) ? $s['share_items'] : array();
		if ( ! $items ) { return; }
		$out = '';
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$name = trim( wp_strip_all_tags( (string) ( $item['social_name'] ?? '' ) ) );
			$type = 'text' === ( $item['social_type'] ?? '' ) ? 'text' : 'icon';
			$icon = is_array( $item['social_icon'] ?? null ) ? $item['social_icon'] : array();
			if ( '' === $name || ( 'icon' === $type && empty( $icon['value'] ) ) ) { continue; }
			$link = is_array( $item['social_link'] ?? null ) ? $item['social_link'] : array();
			$linked = ! empty( $link['url'] );
			$id = sanitize_html_class( (string) ( $item['_id'] ?? $index ) );
			$out .= '<li class="digi-fancy-icons__item elementor-repeater-item-' . esc_attr( $id ) . '">';
			if ( $linked ) {
				$key = 'fancy_icon_link_' . (int) $index;
				$this->add_link_attributes( $key, $link );
				if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) ); }
				$out .= '<a class="digi-fancy-icons__content" ' . $this->get_render_attribute_string( $key );
				if ( 'icon' === $type && '' !== $name ) { $out .= ' aria-label="' . esc_attr( $name ) . '"'; }
				$out .= '>';
			} else {
				$out .= '<span class="digi-fancy-icons__content"';
				if ( 'icon' === $type && '' !== $name ) { $out .= ' role="img" aria-label="' . esc_attr( $name ) . '"'; }
				$out .= '>';
			}
			if ( 'text' === $type ) { $out .= '<span>' . esc_html( $name ) . '</span>'; }
			else {
				ob_start();
				\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
				$out .= (string) ob_get_clean();
			}
			$out .= $linked ? '</a>' : '</span>';
			$out .= '</li>';
		}
		if ( '' !== $out ) { echo '<ul class="digi-fancy-icons">' . $out . '</ul>'; }
	}

	protected function content_template() {
		?>
		<ul class="digi-fancy-icons">
		<# _.each( settings.share_items || [], function( item ) {
			if ( ! item ) return;
			var name = item.social_name || '';
			var type = item.social_type === 'text' ? 'text' : 'icon';
			if ( ! name || ( type === 'icon' && !( item.social_icon && item.social_icon.value ) ) ) return;
			var href = item.social_link && item.social_link.url ? item.social_link.url : '';
			var rel = item.social_link ? [item.social_link.is_external ? 'noopener noreferrer' : '', item.social_link.nofollow ? 'nofollow' : ''].join(' ').trim() : '';
		#>
		<li class="digi-fancy-icons__item elementor-repeater-item-{{ item._id }}">
		<# if ( href ) { #><a class="digi-fancy-icons__content" href="{{ href }}" <# if ( type === 'icon' ) { #>aria-label="{{ name }}"<# } #> <# if ( item.social_link.is_external ) { #>target="_blank"<# } #> <# if ( rel ) { #>rel="{{ rel }}"<# } #>><# } else { #><span class="digi-fancy-icons__content" <# if ( type === 'icon' ) { #>role="img" aria-label="{{ name }}"<# } #>><# } #>
		<# if ( type === 'text' ) { #><span>{{ name }}</span><# } else { var icon = elementor.helpers.renderIcon( view, item.social_icon, { 'aria-hidden': true }, 'i', 'object' ); if ( icon && icon.rendered ) { #>{{{ icon.value }}}<# } } #>
		<# if ( href ) { #></a><# } else { #></span><# } #>
		</li>
		<# } ); #>
		</ul>
		<?php
	}
}
