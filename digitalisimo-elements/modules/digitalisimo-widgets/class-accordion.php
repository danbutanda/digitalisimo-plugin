<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Acordeón de contenido editorial con divulgación nativa y sin JavaScript. */
final class Accordion_Widget extends \Elementor\Widget_Base {
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
		$repeater->add_control( 'tab_content', array( 'label' => 'Contenido', 'type' => $c::WYSIWYG, 'default' => 'Contenido del elemento.', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'tabs', array( 'label' => 'Elementos', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'tab_title' => 'Primer elemento', 'tab_content' => 'Contenido del primer elemento.' ), array( 'tab_title' => 'Segundo elemento', 'tab_content' => 'Contenido del segundo elemento.' ) ), 'title_field' => '{{{ tab_title }}}' ) );
		$this->add_control( 'active_item', array( 'label' => 'Abrir inicialmente', 'type' => $c::NUMBER, 'default' => 1, 'min' => 0, 'max' => 100, 'description' => '0 deja todos cerrados.' ) );
		$this->add_control( 'multiple', array( 'label' => 'Permitir varios abiertos', 'type' => $c::SWITCHER, 'return_value' => 'yes', 'default' => '' ) );
		$this->add_control( 'title_html_tag', array( 'label' => 'Etiqueta del título', 'type' => $c::SELECT, 'default' => 'span', 'options' => array( 'span' => 'Texto', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6' ) ) );
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

	protected function render() {
		$settings = $this->get_settings_for_display();
		$items = is_array( $settings['tabs'] ?? null ) ? array_values( $settings['tabs'] ) : array();
		if ( ! $items ) { return; }
		$tag = self::title_tag( $settings['title_html_tag'] ?? 'span' );
		$active = isset( $settings['active_item'] ) ? max( 0, (int) $settings['active_item'] ) : 1;
		$exclusive = 'yes' !== ( $settings['multiple'] ?? '' );
		$group = 'digi-accordion-' . esc_attr( $this->get_id() );
		$printed = false;
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$title = trim( (string) ( $item['tab_title'] ?? '' ) );
			$content = trim( (string) ( $item['tab_content'] ?? '' ) );
			if ( '' === $title && '' === $content ) { continue; }
			if ( ! $printed ) { echo '<div class="digi-accordion">'; $printed = true; }
			if ( '' === $title ) { $title = 'Elemento ' . ( $index + 1 ); }
			echo '<details class="digi-accordion__item"';
			if ( $exclusive ) { echo ' name="' . $group . '"'; }
			if ( $active === $index + 1 ) { echo ' open'; }
			echo '><summary class="digi-accordion__summary"><' . $tag . ' class="digi-accordion__title">' . esc_html( $title ) . '</' . $tag . '><span class="digi-accordion__marker" aria-hidden="true"></span></summary>';
			echo '<div class="digi-accordion__content">' . wp_kses_post( $this->parse_text_editor( $content ) ) . '</div></details>';
		}
		if ( $printed ) { echo '</div>'; }
	}

	protected function content_template() {
		?>
		<# var items = _.isArray( settings.tabs ) ? settings.tabs : [];
		var tag = _.contains( ['span','h2','h3','h4','h5','h6'], settings.title_html_tag ) ? settings.title_html_tag : 'span';
		var active = settings.active_item === undefined ? 1 : Math.max( 0, parseInt( settings.active_item, 10 ) || 0 );
		var group = 'digi-accordion-' + view.getID();
		var valid = _.some( items, function( item ) { return item && ( String( item.tab_title || '' ).trim() || String( item.tab_content || '' ).trim() ); } ); #>
		<# if ( valid ) { #><div class="digi-accordion"><# _.each( items, function( item, index ) { if ( ! item || !( String( item.tab_title || '' ).trim() || String( item.tab_content || '' ).trim() ) ) return; var title = String( item.tab_title || '' ).trim() || 'Elemento ' + ( index + 1 ); #>
		<details class="digi-accordion__item" <# if ( settings.multiple !== 'yes' ) { #>name="{{ group }}"<# } #> <# if ( active === index + 1 ) { #>open<# } #>>
		<summary class="digi-accordion__summary"><{{ tag }} class="digi-accordion__title">{{ title }}</{{ tag }}><span class="digi-accordion__marker" aria-hidden="true"></span></summary>
		<div class="digi-accordion__content">{{{ item.tab_content || '' }}}</div></details><# } ); #></div><# } #>
		<?php
	}
}
