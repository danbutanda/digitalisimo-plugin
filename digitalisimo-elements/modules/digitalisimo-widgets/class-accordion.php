<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Acordeón de contenido editorial con divulgación nativa y sin JavaScript. */
final class Accordion_Widget extends \Elementor\Widget_Base {
	private static $rendering_templates = array();
	private static $template_options = array();
	public function get_name() { return 'digitalisimo-accordion'; }
	public function get_title() { return 'Acordeón'; }
	public function get_icon() { return 'eicon-accordion'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'acordeón', 'preguntas', 'contenido', 'accordion' ); }
	public function get_style_depends() { return array( 'digitalisimo-accordion' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_items', array( 'label' => 'Elementos' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'tab_title', array( 'label' => 'Título', 'type' => $c::TEXT, 'default' => 'Título', 'dynamic' => array( 'active' => true ) ) );
		$sources = array( 'custom' => 'Contenido personalizado', 'elementor' => 'Plantilla Elementor' );
		if ( function_exists( 'post_type_exists' ) && post_type_exists( 'ae_global_templates' ) ) { $sources['anywhere'] = 'Plantilla Anywhere Elementor'; }
		$repeater->add_control( 'source', array( 'label' => 'Fuente', 'type' => $c::SELECT, 'default' => 'custom', 'options' => $sources ) );
		$repeater->add_control( 'tab_content', array( 'label' => 'Contenido', 'type' => $c::WYSIWYG, 'default' => 'Contenido del elemento.', 'dynamic' => array( 'active' => true ), 'condition' => array( 'source' => 'custom' ) ) );
		$repeater->add_control( 'template_id', array( 'label' => 'Plantilla Elementor', 'type' => $c::SELECT2, 'options' => self::template_options( 'elementor_library' ), 'condition' => array( 'source' => 'elementor' ), 'label_block' => true ) );
		$repeater->add_control( 'anywhere_id', array( 'label' => 'Plantilla Anywhere Elementor', 'type' => $c::SELECT2, 'options' => self::template_options( 'ae_global_templates' ), 'condition' => array( 'source' => 'anywhere' ), 'label_block' => true ) );
		$repeater->add_control( 'repeater_icon', array( 'label' => 'Icono del título', 'type' => $c::ICONS ) );
		$this->add_control( 'tabs', array( 'label' => 'Elementos', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'tab_title' => 'Primer elemento', 'tab_content' => 'Contenido del primer elemento.' ), array( 'tab_title' => 'Segundo elemento', 'tab_content' => 'Contenido del segundo elemento.' ) ), 'title_field' => '{{{ tab_title }}}' ) );
		$this->add_control( 'active_item', array( 'label' => 'Abrir inicialmente', 'type' => $c::NUMBER, 'default' => 1, 'min' => 0, 'max' => 100, 'description' => '0 deja todos cerrados.' ) );
		$this->add_control( 'multiple', array( 'label' => 'Permitir varios abiertos', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'default' => '' ) );
		$this->add_control( 'title_html_tag', array( 'label' => 'Etiqueta del título', 'type' => $c::SELECT, 'default' => 'span', 'options' => array( 'span' => 'Texto', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6' ) ) );
		$this->add_control( 'show_custom_icon', array( 'label' => 'Mostrar iconos de cada título', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'default' => '' ) );
		$this->add_control( 'accordion_icon', array( 'label' => 'Icono cerrado', 'type' => $c::ICONS ) );
		$this->add_control( 'accordion_active_icon', array( 'label' => 'Icono abierto', 'type' => $c::ICONS ) );
		$this->add_control( 'icon_align', array( 'label' => 'Posición del icono de estado', 'type' => $c::SELECT, 'default' => 'right', 'options' => array( 'left' => 'Antes', 'right' => 'Después' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'title_color', array( 'label' => 'Color del título', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-accordion__summary' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'background_color', array( 'label' => 'Fondo del título', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-accordion__summary' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'content_color', array( 'label' => 'Color del contenido', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-accordion__content' => 'color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'item_gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-accordion' => 'gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'item_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-accordion__summary,{{WRAPPER}} .digi-accordion__content' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	private static function title_tag( $value ) {
		return in_array( $value, array( 'span', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $value : 'span';
	}

	private static function template_options( $post_type ) {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() || ! function_exists( 'post_type_exists' ) || ! post_type_exists( $post_type ) ) { return array(); }
		$cache_key = ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 ) . ':' . $post_type;
		if ( isset( self::$template_options[ $cache_key ] ) ) { return self::$template_options[ $cache_key ]; }
		$posts = get_posts( array( 'post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false ) );
		$options = array();
		foreach ( $posts as $post ) { $options[ $post->ID ] = get_the_title( $post->ID ); }
		self::$template_options[ $cache_key ] = $options;
		return self::$template_options[ $cache_key ];
	}

	private static function template_content( $template_id, $post_type ) {
		$id = absint( $template_id );
		if ( ! $id || get_post_type( $id ) !== $post_type || 'publish' !== get_post_status( $id ) || isset( self::$rendering_templates[ $id ] ) ) { return ''; }
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) { return ''; }
		$elementor = \Elementor\Plugin::instance();
		if ( ! isset( $elementor->frontend ) || ! method_exists( $elementor->frontend, 'get_builder_content_for_display' ) ) { return ''; }
		self::$rendering_templates[ $id ] = true;
		try { return (string) $elementor->frontend->get_builder_content_for_display( $id, true ); }
		finally { unset( self::$rendering_templates[ $id ] ); }
	}

	private static function print_icon( $icon ) {
		if ( ! is_array( $icon ) || empty( $icon['value'] ) || ! class_exists( '\\Elementor\\Icons_Manager' ) ) { return false; }
		\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
		return true;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$items = is_array( $settings['tabs'] ?? null ) ? array_values( $settings['tabs'] ) : array();
		if ( ! $items ) { return; }
		$tag = self::title_tag( $settings['title_html_tag'] ?? 'span' );
		$active = isset( $settings['active_item'] ) ? max( 0, (int) $settings['active_item'] ) : 1;
		$exclusive = 'yes' !== ( $settings['multiple'] ?? '' );
		$custom_icons = 'yes' === ( $settings['show_custom_icon'] ?? '' );
		$toggle_icons = ! empty( $settings['accordion_icon']['value'] ) && ! empty( $settings['accordion_active_icon']['value'] );
		$left_icon = 'left' === ( $settings['icon_align'] ?? '' );
		$group = 'digi-accordion-' . esc_attr( $this->get_id() );
		$printed = false;
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$title = trim( (string) ( $item['tab_title'] ?? '' ) );
			$content = trim( (string) ( $item['tab_content'] ?? '' ) );
			$source = in_array( $item['source'] ?? 'custom', array( 'elementor', 'anywhere' ), true ) ? $item['source'] : 'custom';
			if ( '' === $title && '' === $content && 'custom' === $source ) { continue; }
			if ( ! $printed ) { echo '<div class="digi-accordion' . ( $left_icon ? ' digi-accordion--icon-left' : '' ) . '">'; $printed = true; }
			if ( '' === $title ) { $title = 'Elemento ' . ( $index + 1 ); }
			echo '<details class="digi-accordion__item"';
			if ( $exclusive ) { echo ' name="' . $group . '"'; }
			if ( $active === $index + 1 ) { echo ' open'; }
			echo '><summary class="digi-accordion__summary"><span class="digi-accordion__label">';
			if ( $custom_icons && ! empty( $item['repeater_icon']['value'] ) ) { echo '<span class="digi-accordion__custom-icon" aria-hidden="true">'; self::print_icon( $item['repeater_icon'] ); echo '</span>'; }
			echo '<' . $tag . ' class="digi-accordion__title">' . esc_html( $title ) . '</' . $tag . '></span><span class="digi-accordion__marker' . ( $toggle_icons ? ' digi-accordion__marker--custom' : '' ) . '" aria-hidden="true">';
			if ( $toggle_icons ) {
				echo '<span class="digi-accordion__icon-closed">'; self::print_icon( $settings['accordion_icon'] ?? null ); echo '</span>';
				echo '<span class="digi-accordion__icon-open">'; self::print_icon( $settings['accordion_active_icon'] ?? null ); echo '</span>';
			}
			echo '</span></summary><div class="digi-accordion__content">';
			if ( 'custom' === $source ) { echo wp_kses_post( $this->parse_text_editor( $content ) ); }
			else {
				$id = 'anywhere' === $source ? ( $item['anywhere_id'] ?? 0 ) : ( $item['template_id'] ?? 0 );
				$post_type = 'anywhere' === $source ? 'ae_global_templates' : 'elementor_library';
				// Published Elementor templates render their own trusted widget markup.
				echo self::template_content( $id, $post_type );
			}
			echo '</div></details>';
		}
		if ( $printed ) { echo '</div>'; }
	}

	protected function content_template() {
		?>
		<# var items = _.isArray( settings.tabs ) ? settings.tabs : [];
		var tag = _.contains( ['span','h2','h3','h4','h5','h6'], settings.title_html_tag ) ? settings.title_html_tag : 'span';
		var active = settings.active_item === undefined ? 1 : Math.max( 0, parseInt( settings.active_item, 10 ) || 0 );
		var group = 'digi-accordion-' + view.getID();
		var valid = _.some( items, function( item ) { return item && ( item.source === 'elementor' || item.source === 'anywhere' || String( item.tab_title || '' ).trim() || String( item.tab_content || '' ).trim() ); } );
		var closed = settings.accordion_icon && settings.accordion_icon.value ? elementor.helpers.renderIcon( view, settings.accordion_icon, { 'aria-hidden': true }, 'i', 'object' ) : null;
		var opened = settings.accordion_active_icon && settings.accordion_active_icon.value ? elementor.helpers.renderIcon( view, settings.accordion_active_icon, { 'aria-hidden': true }, 'i', 'object' ) : null;
		var customIcons = closed && closed.rendered && opened && opened.rendered; #>
		<# if ( valid ) { #><div class="digi-accordion<# if ( settings.icon_align === 'left' ) { #> digi-accordion--icon-left<# } #>"><# _.each( items, function( item, index ) { if ( ! item || !( item.source === 'elementor' || item.source === 'anywhere' || String( item.tab_title || '' ).trim() || String( item.tab_content || '' ).trim() ) ) return; var title = String( item.tab_title || '' ).trim() || 'Elemento ' + ( index + 1 ); var itemIcon = settings.show_custom_icon === 'yes' && item.repeater_icon && item.repeater_icon.value ? elementor.helpers.renderIcon( view, item.repeater_icon, { 'aria-hidden': true }, 'i', 'object' ) : null; #>
		<details class="digi-accordion__item" <# if ( settings.multiple !== 'yes' ) { #>name="{{ group }}"<# } #> <# if ( active === index + 1 ) { #>open<# } #>>
		<summary class="digi-accordion__summary"><span class="digi-accordion__label"><# if ( itemIcon && itemIcon.rendered ) { #><span class="digi-accordion__custom-icon" aria-hidden="true">{{{ itemIcon.value }}}</span><# } #><{{ tag }} class="digi-accordion__title">{{ title }}</{{ tag }}></span><span class="digi-accordion__marker<# if ( customIcons ) { #> digi-accordion__marker--custom<# } #>" aria-hidden="true"><# if ( customIcons ) { #><span class="digi-accordion__icon-closed"><# if ( closed && closed.rendered ) { #>{{{ closed.value }}}<# } #></span><span class="digi-accordion__icon-open"><# if ( opened && opened.rendered ) { #>{{{ opened.value }}}<# } #></span><# } #></span></summary>
		<div class="digi-accordion__content"><# if ( item.source === 'elementor' || item.source === 'anywhere' ) { #><p>Plantilla #{{ item.source === 'anywhere' ? item.anywhere_id : item.template_id }}</p><# } else { #>{{{ item.tab_content || '' }}}<# } #></div></details><# } ); #></div><# } #>
		<?php
	}
}
