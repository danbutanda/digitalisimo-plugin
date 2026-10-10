<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Navegación compacta con iconos y títulos legibles sin librería de tooltips. */
class Icon_Mobile_Menu_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-icon-mobile-menu'; }
	public function get_title() { return 'Menú móvil con iconos'; }
	public function get_icon() { return 'eicon-menu-bar'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'menú', 'móvil', 'iconos', 'navegación' ); }
	public function get_style_depends() { return array( 'digitalisimo-icon-mobile-menu' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_menu', array( 'label' => 'Elementos del menú' ) );
		$this->add_control( 'menu_style', array( 'label' => 'Diseño', 'type' => $c::SELECT, 'default' => 'style-1', 'options' => array( 'style-1' => 'Clásico', 'style-2' => 'Con bordes', 'style-3' => 'Sólo iconos', 'style-4' => 'Iconos circulares' ) ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'menu_icon', array( 'label' => 'Icono', 'type' => $c::ICONS ) );
		$repeater->add_control( 'menu_text', array( 'label' => 'Texto y nombre accesible', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'menu_items', array( 'label' => 'Enlaces', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'menu_text' => 'Inicio', 'menu_icon' => array( 'value' => 'fas fa-home', 'library' => 'fa-solid' ) ) ), 'title_field' => '{{{ menu_text }}}' ) );
		$this->add_control( 'menu_tooltip', array( 'label' => 'Mostrar nombre al enfocar o pasar el cursor', 'type' => $c::SWITCHER, 'default' => 'yes', 'condition' => array( 'menu_style' => array( 'style-3', 'style-4' ) ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'icon_size', array( 'label' => 'Tamaño del icono', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 12, 'max' => 64 ) ), 'selectors' => array( '{{WRAPPER}} .digi-icon-mobile-menu__icon' => 'font-size:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Espacio', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 64 ) ), 'selectors' => array( '{{WRAPPER}} .digi-icon-mobile-menu__list' => 'gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'text_color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-icon-mobile-menu__item' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'background_color', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-icon-mobile-menu__item' => 'background-color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$items = is_array( $s['menu_items'] ?? null ) ? $s['menu_items'] : array();
		if ( ! $items ) { return; }
		$allowed = array( 'style-1', 'style-2', 'style-3', 'style-4' );
		$style = in_array( $s['menu_style'] ?? '', $allowed, true ) ? $s['menu_style'] : 'style-1';
		$compact = in_array( $style, array( 'style-3', 'style-4' ), true );
		$tooltip = $compact && 'yes' === ( $s['menu_tooltip'] ?? 'yes' );
		$out = '';
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$name = trim( wp_strip_all_tags( (string) ( $item['menu_text'] ?? '' ) ) );
			$icon = is_array( $item['menu_icon'] ?? null ) ? $item['menu_icon'] : array();
			if ( '' === $name || ( $compact && empty( $icon['value'] ) ) ) { continue; }
			$link = is_array( $item['link'] ?? null ) ? $item['link'] : array();
			$linked = ! empty( $link['url'] );
			$id = sanitize_html_class( (string) ( $item['_id'] ?? $index ) );
			$out .= '<li class="digi-icon-mobile-menu__entry elementor-repeater-item-' . esc_attr( $id ) . '">';
			if ( $linked ) {
				$key = 'icon_mobile_menu_' . (int) $index;
				$this->add_link_attributes( $key, $link );
				if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) ); }
				$out .= '<a class="digi-icon-mobile-menu__item" ' . $this->get_render_attribute_string( $key ) . ( $compact ? ' aria-label="' . esc_attr( $name ) . '"' : '' ) . ( $tooltip ? ' data-tooltip="' . esc_attr( $name ) . '"' : '' ) . '>';
			} else {
				$out .= '<span class="digi-icon-mobile-menu__item"' . ( $compact ? ' role="img" aria-label="' . esc_attr( $name ) . '"' : '' ) . '>';
			}
			if ( ! empty( $icon['value'] ) ) {
				ob_start();
				\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
				$out .= '<span class="digi-icon-mobile-menu__icon">' . (string) ob_get_clean() . '</span>';
			}
			$out .= '<span class="digi-icon-mobile-menu__text">' . esc_html( $name ) . '</span>';
			$out .= $linked ? '</a>' : '</span>';
			$out .= '</li>';
		}
		if ( '' !== $out ) { echo '<nav class="digi-icon-mobile-menu digi-icon-mobile-menu--' . esc_attr( $style ) . '" aria-label="Menú de iconos"><ul class="digi-icon-mobile-menu__list">' . $out . '</ul></nav>'; }
	}

	protected function content_template() {
		?>
		<# var style = ['style-1','style-2','style-3','style-4'].indexOf(settings.menu_style) >= 0 ? settings.menu_style : 'style-1'; var compact = style === 'style-3' || style === 'style-4'; var tooltip = compact && settings.menu_tooltip !== ''; #>
		<nav class="digi-icon-mobile-menu digi-icon-mobile-menu--{{ style }}" aria-label="Menú de iconos"><ul class="digi-icon-mobile-menu__list">
		<# _.each(settings.menu_items || [], function(item) { if (!item || !item.menu_text || (compact && !(item.menu_icon && item.menu_icon.value))) return; var href = item.link && item.link.url ? item.link.url : ''; var icon = item.menu_icon && item.menu_icon.value ? elementor.helpers.renderIcon(view,item.menu_icon,{'aria-hidden':true},'i','object') : null; #>
		<li class="digi-icon-mobile-menu__entry elementor-repeater-item-{{ item._id }}"><# if (href) { var rel = []; if (item.link.nofollow) rel.push('nofollow'); if (item.link.is_external) rel.push('noopener noreferrer'); #><a class="digi-icon-mobile-menu__item" href="{{ href }}" <# if (item.link.is_external) { #>target="_blank"<# } #> <# if (rel.length) { #>rel="{{ rel.join(' ') }}"<# } #> <# if (compact) { #>aria-label="{{ item.menu_text }}"<# } #> <# if (tooltip) { #>data-tooltip="{{ item.menu_text }}"<# } #>><# } else { #><span class="digi-icon-mobile-menu__item" <# if (compact) { #>role="img" aria-label="{{ item.menu_text }}"<# } #>><# } #><# if (icon && icon.rendered) { #><span class="digi-icon-mobile-menu__icon">{{{ icon.value }}}</span><# } #><span class="digi-icon-mobile-menu__text">{{ item.menu_text }}</span><# if (href) { #></a><# } else { #></span><# } #></li>
		<# }); #></ul></nav>
		<?php
	}
}
