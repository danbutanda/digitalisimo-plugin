<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-image-markup.php';
require_once __DIR__ . '/class-widget-alias.php';

/**
 * Navegación con iconos: barra lateral fija (con menú de WordPress y marca opcionales) o barra
 * horizontal con cuatro estilos. Reúne Navegación con iconos y Menú móvil con iconos, que sigue
 * registrado como alias oculto de éste (misma clase PHP, mismo nombre y mismas clases CSS).
 */
class Icon_Nav_Widget extends \Elementor\Widget_Base {
	use Legacy_Classes;

	const CSS_PREFIX = 'digi-icon-nav';
	const STYLES     = array( 'style-1', 'style-2', 'style-3', 'style-4' );

	public function get_name() { return 'digitalisimo-icon-nav'; }
	public function get_title() { return 'Navegación con iconos'; }
	public function get_icon() { return 'eicon-nav-menu'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'icon nav', 'menú', 'navegación', 'barra lateral', 'móvil', 'iconos' ); }
	/** Barra lateral o barra horizontal: cada instancia carga sólo su hoja. */
	public function get_style_depends() {
		if ( ! method_exists( $this, 'is_type_instance' ) || $this->is_type_instance() || ! method_exists( $this, 'get_settings' ) ) { return array( 'digitalisimo-icon-nav', 'digitalisimo-icon-nav-bar' ); }
		$s = (array) $this->get_settings();
		return 'bar' === ( $s['layout'] ?? 'rail' ) ? array( 'digitalisimo-icon-nav-bar' ) : array( 'digitalisimo-icon-nav' );
	}
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_links', array( 'label' => 'Enlaces con iconos' ) );
		$this->add_control( 'layout', array( 'label' => 'Disposición', 'type' => $c::SELECT, 'default' => 'rail', 'options' => array( 'rail' => 'Barra lateral fija', 'bar' => 'Barra horizontal' ) ) );
		$this->add_control( 'menu_style', array( 'label' => 'Diseño', 'type' => $c::SELECT, 'default' => 'style-1', 'options' => array( 'style-1' => 'Clásico', 'style-2' => 'Con bordes', 'style-3' => 'Sólo iconos', 'style-4' => 'Iconos circulares' ), 'condition' => array( 'layout' => 'bar' ) ) );
		$this->add_control( 'menu_tooltip', array( 'label' => 'Mostrar nombre al enfocar o pasar el cursor', 'type' => $c::SWITCHER, 'default' => 'yes', 'condition' => array( 'layout' => 'bar', 'menu_style' => array( 'style-3', 'style-4' ) ) ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'iconnav_icon', array( 'label' => 'Icono', 'type' => $c::ICONS ) );
		$repeater->add_control( 'iconnav_title', array( 'label' => 'Texto y nombre accesible', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'iconnav_link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'iconnavs', array( 'label' => 'Elementos', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'iconnav_title' => 'Inicio', 'iconnav_icon' => array( 'value' => 'fas fa-home', 'library' => 'fa-solid' ) ) ), 'title_field' => '{{{ iconnav_title }}}' ) );
		$this->add_control( 'menu_text', array( 'label' => 'Texto', 'type' => $c::SELECT, 'default' => 'show_as_tooltip', 'options' => array( 'show_as_tooltip' => 'Al enfocar o pasar cursor', 'show_under_icon' => 'Debajo del icono' ), 'condition' => array( 'layout' => 'rail' ) ) );
		$this->add_control( 'iconnav_position', array( 'label' => 'Posición', 'type' => $c::SELECT, 'default' => 'left', 'options' => array( 'left' => 'Inicio', 'right' => 'Final' ), 'condition' => array( 'layout' => 'rail' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_menu', array( 'label' => 'Menú del sitio', 'condition' => array( 'layout' => 'rail' ) ) );
		$menus = array( '0' => 'Sin menú adicional' );
		if ( function_exists( 'wp_get_nav_menus' ) ) {
			foreach ( (array) wp_get_nav_menus() as $menu ) { $menus[ (string) $menu->term_id ] = $menu->name; }
		}
		$this->add_control( 'navbar', array( 'label' => 'Menú', 'type' => $c::SELECT, 'default' => '0', 'options' => $menus ) );
		$this->add_control( 'navbar_level', array( 'label' => 'Niveles', 'type' => $c::SELECT, 'default' => '1', 'options' => array( '1' => '1', '2' => '2', '3' => '3' ), 'condition' => array( 'navbar!' => '0' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_brand', array( 'label' => 'Marca', 'condition' => array( 'layout' => 'rail' ) ) );
		$this->add_control( 'show_branding', array( 'label' => 'Mostrar marca', 'type' => $c::SWITCHER, 'default' => '' ) );
		$this->add_control( 'branding_image', array( 'label' => 'Imagen', 'type' => $c::MEDIA, 'condition' => array( 'show_branding' => 'yes' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'iconnav_width', array( 'label' => 'Ancho', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 44, 'max' => 160 ) ), 'condition' => array( 'layout' => 'rail' ), 'selectors' => array( '{{WRAPPER}} .digi-icon-nav__rail' => 'width:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Espacio', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 64 ) ), 'condition' => array( 'layout' => 'bar' ), 'selectors' => array( '{{WRAPPER}} .digi-icon-nav--bar .digi-icon-nav__links' => 'gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'icon_size', array( 'label' => 'Tamaño del icono', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 12, 'max' => 64 ) ), 'selectors' => array( '{{WRAPPER}} .digi-icon-nav__icon' => 'font-size:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'background_color', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-icon-nav__rail, {{WRAPPER}} .digi-icon-nav--bar .digi-icon-nav__link' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'text_color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-icon-nav__rail, {{WRAPPER}} .digi-icon-nav--bar .digi-icon-nav__link' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	/** Un enlace con icono; en la barra compacta el nombre va como etiqueta accesible y tooltip opcional. */
	private function entry( array $item, $index, $show_text, $tooltip, $require_icon, $label = false ) {
		$name = trim( wp_strip_all_tags( (string) ( $item['iconnav_title'] ?? '' ) ) );
		$icon = is_array( $item['iconnav_icon'] ?? null ) ? $item['iconnav_icon'] : array();
		if ( '' === $name || ( $require_icon && empty( $icon['value'] ) ) ) { return ''; }
		$link   = is_array( $item['iconnav_link'] ?? null ) ? $item['iconnav_link'] : array();
		$linked = ! empty( $link['url'] );
		$id     = (string) ( $item['_id'] ?? '' );
		$out    = '<li class="' . esc_attr( $this->cls( 'entry' ) . ( '' !== $id ? ' elementor-repeater-item-' . sanitize_html_class( $id ) : '' ) ) . '">';
		if ( $linked ) {
			$key = 'icon_nav_' . (int) $index;
			$this->add_link_attributes( $key, $link );
			if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) ); }
			$out .= '<a class="' . esc_attr( $this->cls( 'link' ) ) . '" ' . $this->get_render_attribute_string( $key ) . ( $show_text && ! $label ? '' : ' aria-label="' . esc_attr( $name ) . '"' ) . ( $tooltip ? ' data-tooltip="' . esc_attr( $name ) . '"' : '' ) . '>';
		} else {
			$out .= '<span class="' . esc_attr( $this->cls( 'link' ) ) . '"' . ( $show_text && ! $label ? '' : ' role="img" aria-label="' . esc_attr( $name ) . '"' ) . '>';
		}
		if ( ! empty( $icon['value'] ) ) {
			ob_start(); \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
			$out .= '<span class="' . esc_attr( $this->cls( 'icon' ) ) . '">' . (string) ob_get_clean() . '</span>';
		}
		if ( $show_text ) { $out .= '<span class="' . esc_attr( $this->cls( 'text' ) ) . '">' . esc_html( $name ) . '</span>'; }
		$out .= $linked ? '</a>' : '</span>';
		return $out . '</li>';
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$items = is_array( $s['iconnavs'] ?? null ) ? $s['iconnavs'] : array();
		if ( 'bar' === ( $s['layout'] ?? 'rail' ) ) {
			$style   = in_array( $s['menu_style'] ?? '', self::STYLES, true ) ? $s['menu_style'] : 'style-1';
			$compact = in_array( $style, array( 'style-3', 'style-4' ), true );
			$tooltip = $compact && 'yes' === ( $s['menu_tooltip'] ?? 'yes' );
			$out     = '';
			foreach ( $items as $index => $item ) {
				if ( is_array( $item ) ) { $out .= $this->entry( $item, $index, true, $tooltip, $compact, $compact ); }
			}
			if ( '' === $out ) { return; }
			echo '<nav class="' . esc_attr( $this->cls( '', array( 'bar', $style ) ) ) . '" aria-label="Menú de iconos"><ul class="' . esc_attr( $this->cls( 'links' ) ) . '">' . $out . '</ul></nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada parte se escapa al construirse.
			return;
		}
		$position  = 'right' === ( $s['iconnav_position'] ?? '' ) ? 'right' : 'left';
		$show_text = 'show_under_icon' === ( $s['menu_text'] ?? '' );
		$menu_id   = absint( $s['navbar'] ?? 0 );
		$menu      = $menu_id && function_exists( 'wp_get_nav_menu_object' ) ? wp_get_nav_menu_object( $menu_id ) : false;
		if ( ! $items && ! $menu ) { return; }
		echo '<nav class="' . esc_attr( $this->cls( '', array_merge( array( $position ), $show_text ? array( 'with-text' ) : array() ) ) ) . '" aria-label="Navegación con iconos"><div class="' . esc_attr( $this->cls( 'rail' ) ) . '">';
		if ( 'yes' === ( $s['show_branding'] ?? '' ) ) {
			$site_name = get_bloginfo( 'name' );
			$image = is_array( $s['branding_image'] ?? null ) ? $s['branding_image'] : array();
			echo '<a class="' . esc_attr( $this->cls( 'brand' ) ) . '" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr( $site_name ) . '">';
			if ( ! empty( $image['id'] ) || ! empty( $image['url'] ) ) { echo Image_Markup::img( $image, array( 'size' => 'thumbnail', 'alt' => $site_name ) ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generado y escapado por Image_Markup.
			else { $initials = ''; foreach ( preg_split( '/\s+/u', trim( (string) $site_name ), -1, PREG_SPLIT_NO_EMPTY ) as $word ) { $initials .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 ); } echo '<span>' . esc_html( $initials ) . '</span>'; }
			echo '</a>';
		}
		if ( $menu ) {
			$depth = max( 1, min( 3, absint( $s['navbar_level'] ?? 1 ) ) );
			echo '<details class="' . esc_attr( $this->cls( 'menu' ) ) . '"><summary aria-label="Abrir menú del sitio"><span aria-hidden="true">☰</span></summary><div class="' . esc_attr( $this->cls( 'panel' ) ) . '">';
			wp_nav_menu( array( 'menu' => $menu_id, 'depth' => $depth, 'container' => false, 'fallback_cb' => false, 'menu_class' => 'digi-icon-nav__site-menu' ) );
			echo '</div></details>';
		}
		if ( $items ) {
			$out = '';
			foreach ( $items as $index => $item ) {
				if ( is_array( $item ) ) { $out .= $this->entry( $item, $index, $show_text, ! $show_text, true ); }
			}
			if ( '' !== $out ) { echo '<ul class="' . esc_attr( $this->cls( 'links' ) ) . '">' . $out . '</ul>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada parte se escapa al construirse.
		}
		echo '</div></nav>';
	}

	protected function content_template() {
		?>
		<# <?php echo $this->cls_template_js(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON de clases fijas. ?>
		var bar = settings.layout === 'bar';
		var style = ['style-1','style-2','style-3','style-4'].indexOf( settings.menu_style ) >= 0 ? settings.menu_style : 'style-1';
		var compact = bar && ( style === 'style-3' || style === 'style-4' );
		var showText = bar ? true : settings.menu_text === 'show_under_icon';
		var tooltip = bar ? ( compact && settings.menu_tooltip !== '' ) : ! showText;
		var side = settings.iconnav_position === 'right' ? 'right' : 'left';
		var entries = _.filter( settings.iconnavs || [], function ( item ) { return item && item.iconnav_title && ( ! ( compact || ! bar ) || ( item.iconnav_icon && item.iconnav_icon.value ) ); } );
		var entry = function ( item ) {
			var icon = item.iconnav_icon && item.iconnav_icon.value ? elementor.helpers.renderIcon( view, item.iconnav_icon, { 'aria-hidden': true }, 'i', 'object' ) : null;
			var href = item.iconnav_link && item.iconnav_link.url ? item.iconnav_link.url : '';
			var rel = []; if ( item.iconnav_link && item.iconnav_link.nofollow ) { rel.push( 'nofollow' ); } if ( item.iconnav_link && item.iconnav_link.is_external ) { rel.push( 'noopener noreferrer' ); }
			var out = '<li class="' + cls( 'entry' ) + ( item._id ? ' elementor-repeater-item-' + item._id : '' ) + '">';
			if ( href ) { out += '<a class="' + cls( 'link' ) + '" href="' + _.escape( href ) + '"' + ( showText && ! compact ? '' : ' aria-label="' + _.escape( item.iconnav_title ) + '"' ) + ( tooltip ? ' data-tooltip="' + _.escape( item.iconnav_title ) + '"' : '' ) + ( item.iconnav_link.is_external ? ' target="_blank"' : '' ) + ( rel.length ? ' rel="' + rel.join( ' ' ) + '"' : '' ) + '>'; }
			else { out += '<span class="' + cls( 'link' ) + '"' + ( showText && ! compact ? '' : ' role="img" aria-label="' + _.escape( item.iconnav_title ) + '"' ) + '>'; }
			if ( icon && icon.rendered ) { out += '<span class="' + cls( 'icon' ) + '">' + icon.value + '</span>'; }
			if ( showText ) { out += '<span class="' + cls( 'text' ) + '">' + _.escape( item.iconnav_title ) + '</span>'; }
			return out + ( href ? '</a>' : '</span>' ) + '</li>';
		}; #>
		<# if ( bar ) { #><# if ( entries.length ) { #><nav class="{{ cls( '', ['bar', style] ) }}" aria-label="Menú de iconos"><ul class="{{ cls( 'links' ) }}"><# _.each( entries, function ( item ) { #>{{{ entry( item ) }}}<# } ); #></ul></nav><# } #>
		<# } else { #><nav class="{{ cls( '', showText ? [side, 'with-text'] : [side] ) }}" aria-label="Navegación con iconos"><div class="{{ cls( 'rail' ) }}"><# if ( settings.show_branding === 'yes' ) { #><span class="{{ cls( 'brand' ) }}"><# if ( settings.branding_image && settings.branding_image.url ) { #><img src="{{ settings.branding_image.url }}" alt=""><# } else { #>Marca del sitio<# } #></span><# } #><# if ( settings.navbar && settings.navbar !== '0' ) { #><span>Menú del sitio</span><# } #><ul class="{{ cls( 'links' ) }}"><# _.each( entries, function ( item ) { #>{{{ entry( item ) }}}<# } ); #></ul></div></nav><# } #>
		<?php
	}
}
