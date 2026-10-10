<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Menú vertical con submenús plegables o por paneles. Lee un menú de WordPress o una lista escrita
 * en el widget con niveles; cada submenú se abre con un botón con estado accesible y, sin JavaScript,
 * todos los enlaces quedan visibles.
 */
class Vertical_Menu_Widget extends \Elementor\Widget_Base {
	const MAX_LEVEL = 3;

	public function get_name() { return 'digitalisimo-vertical-menu'; }
	public function get_title() { return 'Menú vertical'; }
	public function get_icon() { return 'eicon-nav-menu'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'menú', 'vertical', 'navegación', 'submenú', 'barra lateral' ); }
	public function get_style_depends() { return array( 'digitalisimo-vertical-menu' ); }
	public function get_script_depends() { return array( 'digitalisimo-vertical-menu' ); }

	private static function menus() {
		$options = array( '' => 'Elegir menú' );
		if ( function_exists( 'wp_get_nav_menus' ) ) {
			foreach ( (array) wp_get_nav_menus() as $menu ) {
				$options[ $menu->slug ] = $menu->name;
			}
		}
		return $options;
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_menu', array( 'label' => 'Menú' ) );
		$this->add_control( 'source', array( 'label' => 'Origen', 'type' => $c::SELECT, 'default' => 'menu', 'options' => array( 'menu' => 'Menú de WordPress', 'static' => 'Elementos escritos aquí' ) ) );
		$this->add_control( 'menu', array( 'label' => 'Menú', 'type' => $c::SELECT, 'default' => '', 'options' => self::menus(), 'condition' => array( 'source' => 'menu' ) ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'item_title', array( 'label' => 'Texto', 'type' => $c::TEXT, 'default' => 'Elemento', 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'item_link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'item_icon', array( 'label' => 'Icono', 'type' => $c::ICONS ) );
		$repeater->add_control( 'item_level', array( 'label' => 'Nivel', 'type' => $c::SELECT, 'default' => '0', 'options' => array( '0' => 'Principal', '1' => 'Submenú', '2' => 'Tercer nivel', '3' => 'Cuarto nivel' ), 'description' => 'Un elemento de nivel mayor queda dentro del anterior de nivel menor.' ) );
		$this->add_control( 'items', array(
			'label'       => 'Elementos',
			'type'        => $c::REPEATER,
			'fields'      => $repeater->get_controls(),
			'default'     => array( array( 'item_title' => 'Inicio' ), array( 'item_title' => 'Servicios' ), array( 'item_title' => 'Diseño web', 'item_level' => '1' ), array( 'item_title' => 'Contacto' ) ),
			'title_field' => '{{{ "—".repeat( parseInt( item_level || 0, 10 ) ) }}} {{{ item_title }}}',
			'condition'   => array( 'source' => 'static' ),
		) );
		$this->add_control( 'mode', array( 'label' => 'Submenús', 'type' => $c::SELECT, 'default' => 'collapse', 'options' => array( 'collapse' => 'Se despliegan debajo', 'drill' => 'Se abren en un panel con «Atrás»' ) ) );
		$this->add_control( 'parent_toggles', array( 'label' => 'El elemento padre sólo abre su submenú', 'type' => $c::SWITCHER, 'description' => 'Sin activar, el padre conserva su enlace y el submenú se abre con la flecha.' ) );
		$this->add_control( 'open_current', array( 'label' => 'Abrir la rama de la página actual', 'type' => $c::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'nav_label', array( 'label' => 'Nombre accesible', 'type' => $c::TEXT, 'default' => 'Menú' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_items', array( 'label' => 'Elementos', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'menu_width', array( 'label' => 'Ancho', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%' ), 'range' => array( 'px' => array( 'min' => 120, 'max' => 800 ) ), 'selectors' => array( '{{WRAPPER}} .digi-vertical-menu' => 'max-width:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'text_align', array( 'label' => 'Alineación', 'type' => $c::CHOOSE, 'options' => array( 'flex-start' => array( 'title' => 'Inicio', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ), 'flex-end' => array( 'title' => 'Fin', 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .digi-vertical-menu__link' => 'justify-content:{{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'item_typography', 'selector' => '{{WRAPPER}} .digi-vertical-menu__link' ) );
		$this->add_responsive_control( 'item_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .digi-vertical-menu__link' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'item_gap', array( 'label' => 'Espacio entre elementos', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .digi-vertical-menu__list' => 'gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'item_radius', array( 'label' => 'Radio', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', '%' ), 'selectors' => array( '{{WRAPPER}} .digi-vertical-menu__row' => 'border-radius:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'icon_spacing', array( 'label' => 'Espacio del icono', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .digi-vertical-menu__link' => 'gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'sub_indent', array( 'label' => 'Sangría del submenú', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}} .digi-vertical-menu--collapse .digi-vertical-menu__sub' => 'padding-inline-start:{{SIZE}}{{UNIT}};' ) ) );
		foreach ( array( '' => array( 'Normal', '.digi-vertical-menu__row' ), '_hover' => array( 'Al pasar', '.digi-vertical-menu__row:hover' ), '_active' => array( 'Actual', '.digi-vertical-menu__item.is-current > .digi-vertical-menu__row' ) ) as $state => $data ) {
			$this->add_control( 'item_color' . $state, array( 'label' => 'Color · ' . $data[0], 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} ' . $data[1] => 'color:{{VALUE}};' ) ) );
			$this->add_control( 'item_background' . $state, array( 'label' => 'Fondo · ' . $data[0], 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} ' . $data[1] => 'background-color:{{VALUE}};' ) ) );
		}
		$this->add_control( 'toggle_color', array( 'label' => 'Color de la flecha', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-vertical-menu__toggle' => 'color:{{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => 'item_border', 'selector' => '{{WRAPPER}} .digi-vertical-menu__row' ) );
		$this->end_controls_section();
	}

	/** Nodos `{title, link, icon, current, children}` desde un menú de WordPress. */
	private function menu_nodes( $slug ) {
		if ( '' === $slug || ! function_exists( 'wp_get_nav_menu_object' ) ) {
			return array();
		}
		$menu  = wp_get_nav_menu_object( $slug );
		$items = $menu ? wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) ) : array();
		if ( ! $items ) {
			return array();
		}
		if ( function_exists( '_wp_menu_item_classes_by_context' ) ) {
			_wp_menu_item_classes_by_context( $items );
		}
		$by_parent = array();
		foreach ( $items as $item ) {
			$by_parent[ (int) $item->menu_item_parent ][] = $item;
		}
		$build = static function ( $parent, $depth ) use ( &$build, $by_parent ) {
			$nodes = array();
			foreach ( $by_parent[ $parent ] ?? array() as $item ) {
				$nodes[] = array(
					'title'    => (string) $item->title,
					'link'     => array( 'url' => (string) $item->url, 'is_external' => '_blank' === $item->target, 'nofollow' => false !== strpos( (string) $item->xfn, 'nofollow' ) ),
					'icon'     => array(),
					'current'  => ! empty( $item->current ),
					'children' => $depth < self::MAX_LEVEL ? $build( (int) $item->ID, $depth + 1 ) : array(),
				);
			}
			return $nodes;
		};
		return $build( 0, 0 );
	}

	/** Convierte la lista con niveles en árbol: un nivel mayor cuelga del último elemento de nivel menor. */
	public static function static_nodes( array $items, $current_url = '' ) {
		$root  = array();
		$stack = array( &$root );
		$level = -1;
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$title = trim( (string) ( $item['item_title'] ?? '' ) );
			if ( '' === $title ) {
				continue;
			}
			$want = max( 0, min( self::MAX_LEVEL, (int) ( $item['item_level'] ?? 0 ) ) );
			$want = min( $want, $level + 1 );
			while ( $level >= $want ) {
				array_pop( $stack );
				--$level;
			}
			$link = is_array( $item['item_link'] ?? null ) ? $item['item_link'] : array();
			$url  = (string) ( $link['url'] ?? '' );
			$list = &$stack[ count( $stack ) - 1 ];
			$list[] = array(
				'title'    => $title,
				'link'     => $link,
				'icon'     => is_array( $item['item_icon'] ?? null ) ? $item['item_icon'] : array(),
				'current'  => '' !== $current_url && '' !== $url && '#' !== $url && untrailingslashit( $url ) === untrailingslashit( $current_url ),
				'children' => array(),
				'_id'      => (string) ( $item['_id'] ?? '' ),
			);
			$stack[] = &$list[ count( $list ) - 1 ]['children'];
			$level   = $want;
			unset( $list );
		}
		return $root;
	}

	private static function has_current( array $node ) {
		foreach ( $node['children'] as $child ) {
			if ( $child['current'] || self::has_current( $child ) ) {
				return true;
			}
		}
		return false;
	}

	private function nodes_html( array $nodes, array $s, $path, $parent_title = '' ) {
		$drill  = 'drill' === $s['mode'];
		$parent = 'yes' === ( $s['parent_toggles'] ?? '' );
		$out    = '';
		if ( '' !== $path && $drill ) {
			$out .= '<li class="digi-vertical-menu__back"><button type="button" class="digi-vertical-menu__back-button" data-digi-menu-back>' . esc_html( $parent_title ) . '</button></li>';
		}
		foreach ( $nodes as $index => $node ) {
			$id       = $this->get_id() . '-' . $path . $index;
			$children = $node['children'];
			$open     = $children && ! $drill && 'yes' === ( $s['open_current'] ?? '' ) && self::has_current( $node );
			$classes  = array( 'digi-vertical-menu__item' );
			if ( $node['current'] ) { $classes[] = 'is-current'; }
			if ( $children ) { $classes[] = 'has-children'; }
			if ( $open ) { $classes[] = 'is-open'; }
			if ( ! empty( $node['_id'] ) ) { $classes[] = 'elementor-repeater-item-' . sanitize_html_class( $node['_id'] ); }
			$icon = '';
			if ( ! empty( $node['icon']['value'] ) ) {
				ob_start();
				\Elementor\Icons_Manager::render_icon( $node['icon'], array( 'aria-hidden' => 'true' ) );
				$icon = '<span class="digi-vertical-menu__icon">' . (string) ob_get_clean() . '</span>';
			}
			$label  = $icon . '<span class="digi-vertical-menu__text">' . esc_html( wp_strip_all_tags( $node['title'] ) ) . '</span>';
			$toggle = 'aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-controls="digi-vm-' . esc_attr( $id ) . '" data-digi-menu-toggle';
			$out   .= '<li class="' . esc_attr( implode( ' ', $classes ) ) . '"><div class="digi-vertical-menu__row">';
			$url    = (string) ( $node['link']['url'] ?? '' );
			// Un padre sin destino real («#» o vacío) sólo puede abrir su submenú.
			if ( $children && ( $parent || '' === $url || '#' === $url ) ) {
				$out .= '<button type="button" class="digi-vertical-menu__link" ' . $toggle . '>' . $label . '<span class="digi-vertical-menu__arrow" aria-hidden="true"></span></button>';
			} else {
				if ( '' !== $url ) {
					$key = 'vm_link_' . $id;
					$this->add_link_attributes( $key, $node['link'] );
					if ( $node['current'] ) { $this->add_render_attribute( $key, 'aria-current', 'page' ); }
					$out .= '<a class="digi-vertical-menu__link" ' . $this->get_render_attribute_string( $key ) . '>' . $label . '</a>';
				} else {
					$out .= '<span class="digi-vertical-menu__link">' . $label . '</span>';
				}
				if ( $children ) {
					$out .= '<button type="button" class="digi-vertical-menu__toggle" ' . $toggle . ' aria-label="' . esc_attr( 'Submenú de ' . wp_strip_all_tags( $node['title'] ) ) . '"><span class="digi-vertical-menu__arrow" aria-hidden="true"></span></button>';
				}
			}
			$out .= '</div>';
			if ( $children ) {
				$out .= '<ul class="digi-vertical-menu__list digi-vertical-menu__sub" id="digi-vm-' . esc_attr( $id ) . '">' . $this->nodes_html( $children, $s, $path . $index . '-', wp_strip_all_tags( $node['title'] ) ) . '</ul>';
			}
			$out .= '</li>';
		}
		return $out;
	}

	protected function render() {
		$s         = $this->get_settings_for_display();
		$s['mode'] = in_array( $s['mode'] ?? '', array( 'collapse', 'drill' ), true ) ? $s['mode'] : 'collapse';
		if ( 'static' === ( $s['source'] ?? 'menu' ) ) {
			$current = function_exists( 'home_url' ) && isset( $_SERVER['REQUEST_URI'] ) ? home_url( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sólo se compara con URLs del widget.
			$nodes   = self::static_nodes( is_array( $s['items'] ?? null ) ? $s['items'] : array(), $current );
		} else {
			$nodes = $this->menu_nodes( (string) ( $s['menu'] ?? '' ) );
		}
		if ( ! $nodes ) {
			return;
		}
		$label = trim( wp_strip_all_tags( (string) ( $s['nav_label'] ?? '' ) ) );
		echo '<nav class="digi-vertical-menu digi-vertical-menu--' . esc_attr( $s['mode'] ) . '" aria-label="' . esc_attr( '' !== $label ? $label : 'Menú' ) . '" data-digi-vertical-menu><ul class="digi-vertical-menu__list">'
			. $this->nodes_html( $nodes, $s, '' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada parte se escapa al construirse.
			. '</ul></nav>';
	}
}
