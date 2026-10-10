<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Barra lateral de enlaces con iconos y menú WordPress opcional. */
class Icon_Nav_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-icon-nav'; }
	public function get_title() { return 'Navegación con iconos'; }
	public function get_icon() { return 'eicon-nav-menu'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'icon nav', 'menú', 'navegación', 'barra lateral' ); }
	public function get_style_depends() { return array( 'digitalisimo-icon-nav' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_links', array( 'label' => 'Enlaces con iconos' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'iconnav_icon', array( 'label' => 'Icono', 'type' => $c::ICONS ) );
		$repeater->add_control( 'iconnav_title', array( 'label' => 'Nombre accesible', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'iconnav_link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'iconnavs', array( 'label' => 'Elementos', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'iconnav_title' => 'Inicio', 'iconnav_icon' => array( 'value' => 'fas fa-home', 'library' => 'fa-solid' ) ) ), 'title_field' => '{{{ iconnav_title }}}' ) );
		$this->add_control( 'menu_text', array( 'label' => 'Texto', 'type' => $c::SELECT, 'default' => 'show_as_tooltip', 'options' => array( 'show_as_tooltip' => 'Al enfocar o pasar cursor', 'show_under_icon' => 'Debajo del icono' ) ) );
		$this->add_control( 'iconnav_position', array( 'label' => 'Posición', 'type' => $c::SELECT, 'default' => 'left', 'options' => array( 'left' => 'Inicio', 'right' => 'Final' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_menu', array( 'label' => 'Menú del sitio' ) );
		$menus = array( '0' => 'Sin menú adicional' );
		if ( function_exists( 'wp_get_nav_menus' ) ) {
			foreach ( (array) wp_get_nav_menus() as $menu ) { $menus[ (string) $menu->term_id ] = $menu->name; }
		}
		$this->add_control( 'navbar', array( 'label' => 'Menú', 'type' => $c::SELECT, 'default' => '0', 'options' => $menus ) );
		$this->add_control( 'navbar_level', array( 'label' => 'Niveles', 'type' => $c::SELECT, 'default' => '1', 'options' => array( '1' => '1', '2' => '2', '3' => '3' ), 'condition' => array( 'navbar!' => '0' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_brand', array( 'label' => 'Marca' ) );
		$this->add_control( 'show_branding', array( 'label' => 'Mostrar marca', 'type' => $c::SWITCHER, 'default' => '' ) );
		$this->add_control( 'branding_image', array( 'label' => 'Imagen', 'type' => $c::MEDIA, 'condition' => array( 'show_branding' => 'yes' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'iconnav_width', array( 'label' => 'Ancho', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 44, 'max' => 160 ) ), 'selectors' => array( '{{WRAPPER}} .digi-icon-nav__rail' => 'width:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'icon_size', array( 'label' => 'Tamaño del icono', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 12, 'max' => 64 ) ), 'selectors' => array( '{{WRAPPER}} .digi-icon-nav__icon' => 'font-size:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'background_color', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-icon-nav__rail' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'text_color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-icon-nav__rail' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$items = is_array( $s['iconnavs'] ?? null ) ? $s['iconnavs'] : array();
		$position = 'right' === ( $s['iconnav_position'] ?? '' ) ? 'right' : 'left';
		$show_text = 'show_under_icon' === ( $s['menu_text'] ?? '' );
		$menu_id = absint( $s['navbar'] ?? 0 );
		$menu = $menu_id && function_exists( 'wp_get_nav_menu_object' ) ? wp_get_nav_menu_object( $menu_id ) : false;
		if ( ! $items && ! $menu ) { return; }
		echo '<nav class="digi-icon-nav digi-icon-nav--' . esc_attr( $position ) . ( $show_text ? ' digi-icon-nav--with-text' : '' ) . '" aria-label="Navegación con iconos"><div class="digi-icon-nav__rail">';
		if ( 'yes' === ( $s['show_branding'] ?? '' ) ) {
			$site_name = get_bloginfo( 'name' );
			$image = is_array( $s['branding_image'] ?? null ) ? $s['branding_image'] : array();
			echo '<a class="digi-icon-nav__brand" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr( $site_name ) . '">';
			if ( ! empty( $image['id'] ) ) { echo wp_get_attachment_image( absint( $image['id'] ), 'thumbnail', false, array( 'alt' => $site_name ) ); }
			else { $initials = ''; foreach ( preg_split( '/\s+/u', trim( (string) $site_name ), -1, PREG_SPLIT_NO_EMPTY ) as $word ) { $initials .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 ); } echo '<span>' . esc_html( $initials ) . '</span>'; }
			echo '</a>';
		}
		if ( $menu ) {
			$depth = max( 1, min( 3, absint( $s['navbar_level'] ?? 1 ) ) );
			echo '<details class="digi-icon-nav__menu"><summary aria-label="Abrir menú del sitio"><span aria-hidden="true">☰</span></summary><div class="digi-icon-nav__panel">';
			wp_nav_menu( array( 'menu' => $menu_id, 'depth' => $depth, 'container' => false, 'fallback_cb' => false, 'menu_class' => 'digi-icon-nav__site-menu' ) );
			echo '</div></details>';
		}
		if ( $items ) {
			echo '<ul class="digi-icon-nav__links">';
			foreach ( $items as $index => $item ) {
				if ( ! is_array( $item ) ) { continue; }
				$name = trim( wp_strip_all_tags( (string) ( $item['iconnav_title'] ?? '' ) ) );
				$icon = is_array( $item['iconnav_icon'] ?? null ) ? $item['iconnav_icon'] : array();
				if ( '' === $name || empty( $icon['value'] ) ) { continue; }
				$link = is_array( $item['iconnav_link'] ?? null ) ? $item['iconnav_link'] : array();
				$linked = ! empty( $link['url'] );
				$out = '<li class="digi-icon-nav__entry">';
				if ( $linked ) {
					$key = 'icon_nav_' . (int) $index;
					$this->add_link_attributes( $key, $link );
					if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) ); }
					$out .= '<a class="digi-icon-nav__link" ' . $this->get_render_attribute_string( $key ) . ' aria-label="' . esc_attr( $name ) . '"' . ( $show_text ? '' : ' data-tooltip="' . esc_attr( $name ) . '"' ) . '>';
				} else { $out .= '<span class="digi-icon-nav__link" role="img" aria-label="' . esc_attr( $name ) . '">'; }
				ob_start(); \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
				$out .= '<span class="digi-icon-nav__icon">' . (string) ob_get_clean() . '</span>';
				if ( $show_text ) { $out .= '<span class="digi-icon-nav__text">' . esc_html( $name ) . '</span>'; }
				$out .= $linked ? '</a>' : '</span>';
				echo $out . '</li>';
			}
			echo '</ul>';
		}
		echo '</div></nav>';
	}

	protected function content_template() {
		?>
		<# var side = settings.iconnav_position === 'right' ? 'right' : 'left'; var showText = settings.menu_text === 'show_under_icon'; #>
		<nav class="digi-icon-nav digi-icon-nav--{{ side }}<# if (showText) { #> digi-icon-nav--with-text<# } #>" aria-label="Navegación con iconos"><div class="digi-icon-nav__rail"><# if (settings.show_branding === 'yes') { #><span class="digi-icon-nav__brand"><# if (settings.branding_image && settings.branding_image.url) { #><img src="{{ settings.branding_image.url }}" alt=""><# } else { #>Marca del sitio<# } #></span><# } #><# if (settings.navbar && settings.navbar !== '0') { #><span>Menú del sitio</span><# } #><ul class="digi-icon-nav__links"><# _.each(settings.iconnavs || [], function(item) { if (!item || !item.iconnav_title || !(item.iconnav_icon && item.iconnav_icon.value)) return; var icon = elementor.helpers.renderIcon(view,item.iconnav_icon,{'aria-hidden':true},'i','object'); var href = item.iconnav_link && item.iconnav_link.url ? item.iconnav_link.url : ''; var rel = []; if (item.iconnav_link && item.iconnav_link.nofollow) rel.push('nofollow'); if (item.iconnav_link && item.iconnav_link.is_external) rel.push('noopener noreferrer'); #><li class="digi-icon-nav__entry"><# if (href) { #><a class="digi-icon-nav__link" href="{{ href }}" aria-label="{{ item.iconnav_title }}" <# if (!showText) { #>data-tooltip="{{ item.iconnav_title }}"<# } #> <# if (item.iconnav_link.is_external) { #>target="_blank"<# } #> <# if (rel.length) { #>rel="{{ rel.join(' ') }}"<# } #>><# } else { #><span class="digi-icon-nav__link" role="img" aria-label="{{ item.iconnav_title }}"><# } #><span class="digi-icon-nav__icon"><# if (icon && icon.rendered) { #>{{{ icon.value }}}<# } #></span><# if (showText) { #><span class="digi-icon-nav__text">{{ item.iconnav_title }}</span><# } #><# if (href) { #></a><# } else { #></span><# } #></li><# }); #></ul></div></nav>
		<?php
	}
}
