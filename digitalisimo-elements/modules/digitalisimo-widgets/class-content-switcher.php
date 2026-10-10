<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Paneles con pestañas accesibles y controles independientes por instancia. */
class Content_Switcher_Widget extends \Elementor\Widget_Base {
	private static $fallback_printed = false;
	private static $rendering_templates = array();
	private static $template_options = array();
	public function get_name() { return 'digitalisimo-content-switcher'; }
	public function get_title() { return 'Alternador de contenido'; }
	public function get_icon() { return 'eicon-toggle'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'switcher', 'alternador', 'pestañas', 'contenido' ); }
	public function get_style_depends() { return array( 'digitalisimo-content-switcher' ); }
	public function get_script_depends() { return array( 'digitalisimo-content-switcher' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_switcher', array( 'label' => 'Contenido' ) );
		$items = new \Elementor\Repeater();
		$items->add_control( 'title', array( 'label' => 'Título', 'type' => $c::TEXT, 'default' => 'Opción' ) );
		$items->add_control( 'content_type', array( 'label' => 'Tipo de contenido', 'type' => $c::SELECT, 'default' => 'content', 'options' => array( 'content' => 'Texto', 'template' => 'Plantilla de Elementor', 'link_section' => 'Sección o widget de la página' ) ) );
		$items->add_control( 'content', array( 'label' => 'Contenido', 'type' => $c::WYSIWYG, 'default' => 'Escribe aquí el contenido.', 'condition' => array( 'content_type' => 'content' ) ) );
		$items->add_control( 'template_id', array( 'label' => 'Plantilla publicada', 'type' => $c::SELECT2, 'options' => self::template_options(), 'condition' => array( 'content_type' => 'template' ) ) );
		$items->add_control( 'link_target', array( 'label' => 'ID CSS del elemento', 'type' => $c::TEXT, 'placeholder' => 'mi-seccion', 'description' => 'El elemento con ese ID se muestra sólo con esta opción activa. Sin JavaScript todos permanecen visibles.', 'condition' => array( 'content_type' => 'link_section' ) ) );
		$items->add_control( 'switcher_active', array( 'label' => 'Activa al cargar', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$items->add_control( 'switcher_icon', array( 'label' => 'Icono', 'type' => $c::ICONS ) );
		$this->add_control( 'switcher_items', array( 'label' => 'Opciones', 'type' => $c::REPEATER, 'fields' => $items->get_controls(), 'default' => array( array( 'title' => 'Primera', 'content' => 'Contenido de la primera opción.' ), array( 'title' => 'Segunda', 'content' => 'Contenido de la segunda opción.' ) ), 'title_field' => '{{{ title }}}' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_switcher_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'active_color', array( 'label' => 'Color activo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-content-switcher__tab[aria-selected="true"]' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'active_background', array( 'label' => 'Fondo activo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-content-switcher__tab[aria-selected="true"]' => 'background-color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	private static function template_options() {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() || ! function_exists( 'post_type_exists' ) || ! post_type_exists( 'elementor_library' ) ) { return array(); }
		$key = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1;
		if ( ! isset( self::$template_options[ $key ] ) ) {
			self::$template_options[ $key ] = array();
			foreach ( get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) ) as $post ) {
				self::$template_options[ $key ][ $post->ID ] = get_the_title( $post->ID );
			}
		}
		return self::$template_options[ $key ];
	}

	private static function template_content( $template_id ) {
		$id = absint( $template_id );
		if ( ! $id || 'elementor_library' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || isset( self::$rendering_templates[ $id ] ) || ! class_exists( '\\Elementor\\Plugin' ) ) { return ''; }
		self::$rendering_templates[ $id ] = true;
		try { return (string) \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $id, true ); }
		finally { unset( self::$rendering_templates[ $id ] ); }
	}

	private static function target( $item ) {
		return 'link_section' === ( $item['content_type'] ?? '' ) ? sanitize_html_class( ltrim( trim( (string) ( $item['link_target'] ?? '' ) ), '#' ) ) : '';
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$items = is_array( $s['switcher_items'] ?? null ) ? array_values( array_filter( $s['switcher_items'], function ( $item ) { return is_array( $item ) && ( ! empty( $item['title'] ) || ! empty( $item['content'] ) || ! empty( $item['template_id'] ) || '' !== self::target( $item ) ); } ) ) : array();
		if ( ! $items ) { return; }
		$items = array_slice( $items, 0, 12 );
		$selected = 0;
		foreach ( $items as $index => $item ) { if ( 'yes' === ( $item['switcher_active'] ?? '' ) ) { $selected = $index; break; } }
		$id = 'digi-switcher-' . sanitize_html_class( (string) $this->get_id() );
		echo '<div class="digi-content-switcher" data-digi-content-switcher data-digi-selected="' . esc_attr( (string) $selected ) . '"><div class="digi-content-switcher__tabs" role="tablist" aria-label="Alternar contenido">';
		foreach ( $items as $index => $item ) {
			$active = $selected === $index;
			echo '<button type="button" class="digi-content-switcher__tab" id="' . esc_attr( $id . '-tab-' . $index ) . '" role="tab" aria-controls="' . esc_attr( $id . '-panel-' . $index ) . '" aria-selected="' . ( $active ? 'true' : 'false' ) . '" tabindex="' . ( $active ? '0' : '-1' ) . '">';
			$icon = is_array( $item['switcher_icon'] ?? null ) ? $item['switcher_icon'] : array();
			if ( ! empty( $icon['value'] ) ) { \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ); }
			echo '<span>' . esc_html( wp_strip_all_tags( $item['title'] ?? 'Opción ' . ( $index + 1 ) ) ) . '</span></button>';
		}
		echo '</div>';
		foreach ( $items as $index => $item ) {
			$type = $item['content_type'] ?? 'content';
			$target = self::target( $item );
			echo '<div class="digi-content-switcher__panel" id="' . esc_attr( $id . '-panel-' . $index ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $id . '-tab-' . $index ) . '" tabindex="0"' . ( '' !== $target ? ' data-digi-target="' . esc_attr( $target ) . '"' : '' ) . ( $selected !== $index ? ' hidden' : '' ) . '>';
			if ( 'template' === $type ) { echo self::template_content( $item['template_id'] ?? 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor renderiza la plantilla publicada.
			} elseif ( 'link_section' !== $type ) { echo wp_kses_post( $item['content'] ?? '' ); }
			echo '</div>';
		}
		echo '</div>';
		if ( ! self::$fallback_printed ) { echo '<noscript><style>.digi-content-switcher__panel[hidden]{display:block!important}</style></noscript>'; self::$fallback_printed = true; }
	}

	protected function content_template() {
		?>
		<# var items = (settings.switcher_items || []).filter( function(item){ return item && (item.title || item.content || item.template_id || item.link_target); } ).slice( 0, 12 );
		var id = 'digi-switcher-' + view.model.id; var selected = 0; _.find( items, function( item, index ) { if ( item.switcher_active === 'yes' ) { selected = index; return true; } } ); #><# if ( items.length ) { #>
		<div class="digi-content-switcher" data-digi-content-switcher><div class="digi-content-switcher__tabs" role="tablist" aria-label="Alternar contenido"><# _.each( items, function(item,index){ var icon = elementor.helpers.renderIcon( view, item.switcher_icon, { 'aria-hidden': true }, 'i', 'object' ); #><button type="button" class="digi-content-switcher__tab" id="{{ id }}-tab-{{ index }}" role="tab" aria-controls="{{ id }}-panel-{{ index }}" aria-selected="{{ index === selected ? 'true' : 'false' }}" tabindex="{{ index === selected ? '0' : '-1' }}"><# if ( icon && icon.rendered ) { #>{{{ icon.value }}}<# } #><span>{{ item.title || 'Opción ' + ( index + 1 ) }}</span></button><# }); #></div><# _.each( items, function(item,index){ #><div class="digi-content-switcher__panel" id="{{ id }}-panel-{{ index }}" role="tabpanel" aria-labelledby="{{ id }}-tab-{{ index }}" tabindex="0"<# if ( index !== selected ) { #> hidden<# } #>><# if ( item.content_type === 'template' ) { #><p>Plantilla de Elementor #{{ item.template_id }}</p><# } else if ( item.content_type === 'link_section' ) { #><p>Muestra el elemento #{{ item.link_target }} de la página.</p><# } else { #>{{{ item.content || '' }}}<# } #></div><# }); #></div><# } #>
		<?php
	}
}
