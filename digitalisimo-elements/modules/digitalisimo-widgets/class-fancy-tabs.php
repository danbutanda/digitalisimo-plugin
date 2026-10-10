<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Pestañas de contenido con navegación por teclado y panel inicial visible. */
final class Fancy_Tabs_Widget extends \Elementor\Widget_Base {
	private static $fallback_printed = false;
	public function get_name() { return 'digitalisimo-fancy-tabs'; }
	public function get_title() { return 'Pestañas destacadas'; }
	public function get_icon() { return 'eicon-tabs'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'pestañas', 'iconos', 'tabs', 'contenido' ); }
	public function get_style_depends() { return array( 'digitalisimo-fancy-tabs' ); }
	public function get_script_depends() { return array( 'digitalisimo-content-switcher' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_tabs', array( 'label' => 'Pestañas destacadas' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'icon_type', array( 'label' => 'Tipo de icono', 'type' => $c::SELECT, 'default' => 'icon', 'options' => array( 'icon' => 'Icono', 'image' => 'Imagen', 'none' => 'Ninguno' ) ) );
		$repeater->add_control( 'selected_icon', array( 'label' => 'Icono', 'type' => $c::ICONS, 'condition' => array( 'icon_type' => 'icon' ) ) );
		$repeater->add_control( 'image', array( 'label' => 'Imagen', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ), 'condition' => array( 'icon_type' => 'image' ) ) );
		$repeater->add_control( 'tab_title', array( 'label' => 'Título', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$repeater->add_control( 'tab_sub_title', array( 'label' => 'Subtítulo', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'tab_content', array( 'label' => 'Contenido', 'type' => $c::WYSIWYG, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'tabs_button', array( 'label' => 'Botón', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'button_link', array( 'label' => 'Enlace del botón', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'tabs', array( 'label' => 'Pestañas', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'title_field' => '{{{ tab_title }}}' ) );
		$this->add_responsive_control( 'columns', array( 'label' => 'Columnas de iconos', 'type' => $c::SELECT, 'default' => '2', 'tablet_default' => '2', 'mobile_default' => '1', 'options' => array_combine( range( 1, 6 ), range( 1, 6 ) ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-tabs__tabs' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr));' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'active_color', array( 'label' => 'Color activo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-tabs__tab[aria-selected="true"]' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'active_background', array( 'label' => 'Fondo activo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-tabs__tab[aria-selected="true"]' => 'background-color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-tabs__tabs' => 'gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$tabs = array_values( array_filter( is_array( $s['tabs'] ?? null ) ? $s['tabs'] : array(), function ( $item ) { return is_array( $item ) && '' !== trim( wp_strip_all_tags( (string) ( $item['tab_title'] ?? '' ) ) ); } ) );
		if ( ! $tabs ) { return; }
		$tabs = array_slice( $tabs, 0, 12 );
		$id = 'digi-fancy-tabs-' . sanitize_html_class( (string) $this->get_id() );
		echo '<div class="digi-fancy-tabs" data-digi-content-switcher><div class="digi-fancy-tabs__tabs" role="tablist" aria-label="Pestañas destacadas">';
		foreach ( $tabs as $index => $item ) {
			echo '<button class="digi-fancy-tabs__tab" type="button" id="' . esc_attr( $id . '-tab-' . $index ) . '" role="tab" aria-controls="' . esc_attr( $id . '-panel-' . $index ) . '" aria-selected="' . ( $index ? 'false' : 'true' ) . '" tabindex="' . ( $index ? '-1' : '0' ) . '">';
			$type = $item['icon_type'] ?? 'icon';
			if ( 'icon' === $type && ! empty( $item['selected_icon']['value'] ) ) { echo '<span class="digi-fancy-tabs__icon" aria-hidden="true">'; \Elementor\Icons_Manager::render_icon( $item['selected_icon'], array( 'aria-hidden' => 'true' ) ); echo '</span>'; }
			elseif ( 'image' === $type && ! empty( $item['image']['url'] ) ) {
				$image = $item['image']; $image_id = absint( $image['id'] ?? 0 );
				$markup = $image_id ? wp_get_attachment_image( $image_id, 'thumbnail', false, array( 'class' => 'digi-fancy-tabs__icon', 'alt' => '' ) ) : '';
				if ( ! $markup ) { $markup = '<img class="digi-fancy-tabs__icon" src="' . esc_url( $image['url'] ) . '" alt="">'; }
				echo $markup;
			}
			echo '<span class="digi-fancy-tabs__label"><strong>' . esc_html( wp_strip_all_tags( $item['tab_title'] ) ) . '</strong>';
			$subtitle = trim( wp_strip_all_tags( (string) ( $item['tab_sub_title'] ?? '' ) ) );
			if ( $subtitle ) { echo '<span>' . esc_html( $subtitle ) . '</span>'; }
			echo '</span></button>';
		}
		echo '</div><div class="digi-fancy-tabs__panels">';
		foreach ( $tabs as $index => $item ) {
			echo '<div class="digi-fancy-tabs__panel" id="' . esc_attr( $id . '-panel-' . $index ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $id . '-tab-' . $index ) . '" tabindex="0"' . ( $index ? ' hidden' : '' ) . '>';
			if ( ! empty( $item['tab_content'] ) ) { echo wp_kses_post( $item['tab_content'] ); }
			$button = trim( wp_strip_all_tags( (string) ( $item['tabs_button'] ?? '' ) ) );
			$link = is_array( $item['button_link'] ?? null ) ? $item['button_link'] : array();
			if ( $button && ! empty( $link['url'] ) ) { $key = 'fancy_tab_button_' . $index; $this->add_link_attributes( $key, $link ); if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) ); } echo '<a class="digi-fancy-tabs__button" ' . $this->get_render_attribute_string( $key ) . '>' . esc_html( $button ) . '</a>'; }
			echo '</div>';
		}
		echo '</div></div>';
		if ( ! self::$fallback_printed ) { echo '<noscript><style>.digi-fancy-tabs__panel[hidden]{display:block!important}</style></noscript>'; self::$fallback_printed = true; }
	}

	protected function content_template() {
		?>
		<# var tabs = (settings.tabs || []).filter(function(item){ return item && String(item.tab_title || '').trim(); }).slice(0,12);
		var id = 'digi-fancy-tabs-' + view.model.id;
		var linkRel = function(link){ return [ link && link.nofollow ? 'nofollow' : '', link && link.is_external ? 'noopener noreferrer' : '' ].filter(Boolean).join(' '); }; #>
		<# if ( tabs.length ) { #><div class="digi-fancy-tabs" data-digi-content-switcher><div class="digi-fancy-tabs__tabs" role="tablist" aria-label="Pestañas destacadas"><# _.each(tabs,function(item,index){ var icon = elementor.helpers.renderIcon( view, item.selected_icon, { 'aria-hidden': true }, 'i', 'object' ); #><button class="digi-fancy-tabs__tab" type="button" id="{{ id }}-tab-{{ index }}" role="tab" aria-controls="{{ id }}-panel-{{ index }}" aria-selected="{{ index === 0 ? 'true' : 'false' }}" tabindex="{{ index === 0 ? '0' : '-1' }}"><# if (item.icon_type === 'icon' && icon && icon.rendered) { #><span class="digi-fancy-tabs__icon" aria-hidden="true">{{{ icon.value }}}</span><# } else if (item.icon_type === 'image' && item.image && item.image.url) { #><img class="digi-fancy-tabs__icon" src="{{ item.image.url }}" alt=""><# } #><span class="digi-fancy-tabs__label"><strong>{{ item.tab_title }}</strong><# if(item.tab_sub_title){ #><span>{{ item.tab_sub_title }}</span><# } #></span></button><# }); #></div><div class="digi-fancy-tabs__panels"><# _.each(tabs,function(item,index){ var link = item.button_link || {}; var rel = linkRel(link); #><div class="digi-fancy-tabs__panel" id="{{ id }}-panel-{{ index }}" role="tabpanel" aria-labelledby="{{ id }}-tab-{{ index }}" tabindex="0"<# if(index){ #> hidden<# } #>>{{{ item.tab_content || '' }}}<# if(item.tabs_button && link.url){ #><a class="digi-fancy-tabs__button" href="{{ link.url }}"<# if(link.is_external){ #> target="_blank"<# } #><# if(rel){ #> rel="{{ rel }}"<# } #>>{{ item.tabs_button }}</a><# } #></div><# }); #></div></div><# } #>
		<?php
	}
}
