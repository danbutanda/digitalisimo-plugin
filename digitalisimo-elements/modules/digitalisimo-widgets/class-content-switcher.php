<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Paneles con pestañas accesibles y controles independientes por instancia. */
final class Content_Switcher_Widget extends \Elementor\Widget_Base {
	private static $fallback_printed = false;
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
		$items->add_control( 'content', array( 'label' => 'Contenido', 'type' => $c::WYSIWYG, 'default' => 'Escribe aquí el contenido.' ) );
		$items->add_control( 'switcher_icon', array( 'label' => 'Icono', 'type' => $c::ICONS ) );
		$this->add_control( 'switcher_items', array( 'label' => 'Opciones', 'type' => $c::REPEATER, 'fields' => $items->get_controls(), 'default' => array( array( 'title' => 'Primera', 'content' => 'Contenido de la primera opción.' ), array( 'title' => 'Segunda', 'content' => 'Contenido de la segunda opción.' ) ), 'title_field' => '{{{ title }}}' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_switcher_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'active_color', array( 'label' => 'Color activo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-content-switcher__tab[aria-selected="true"]' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'active_background', array( 'label' => 'Fondo activo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-content-switcher__tab[aria-selected="true"]' => 'background-color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$items = is_array( $s['switcher_items'] ?? null ) ? array_values( array_filter( $s['switcher_items'], function ( $item ) { return is_array( $item ) && ( ! empty( $item['title'] ) || ! empty( $item['content'] ) ); } ) ) : array();
		if ( ! $items ) { return; }
		$items = array_slice( $items, 0, 12 );
		$id = 'digi-switcher-' . sanitize_html_class( (string) $this->get_id() );
		echo '<div class="digi-content-switcher" data-digi-content-switcher><div class="digi-content-switcher__tabs" role="tablist" aria-label="Alternar contenido">';
		foreach ( $items as $index => $item ) {
			$active = 0 === $index;
			echo '<button type="button" class="digi-content-switcher__tab" id="' . esc_attr( $id . '-tab-' . $index ) . '" role="tab" aria-controls="' . esc_attr( $id . '-panel-' . $index ) . '" aria-selected="' . ( $active ? 'true' : 'false' ) . '" tabindex="' . ( $active ? '0' : '-1' ) . '">';
			$icon = is_array( $item['switcher_icon'] ?? null ) ? $item['switcher_icon'] : array();
			if ( ! empty( $icon['value'] ) ) { \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ); }
			echo '<span>' . esc_html( wp_strip_all_tags( $item['title'] ?? 'Opción ' . ( $index + 1 ) ) ) . '</span></button>';
		}
		echo '</div>';
		foreach ( $items as $index => $item ) {
			echo '<div class="digi-content-switcher__panel" id="' . esc_attr( $id . '-panel-' . $index ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $id . '-tab-' . $index ) . '" tabindex="0"' . ( $index ? ' hidden' : '' ) . '>' . wp_kses_post( $item['content'] ?? '' ) . '</div>';
		}
		echo '</div>';
		if ( ! self::$fallback_printed ) { echo '<noscript><style>.digi-content-switcher__panel[hidden]{display:block!important}</style></noscript>'; self::$fallback_printed = true; }
	}

	protected function content_template() {
		?>
		<# var items = (settings.switcher_items || []).filter( function(item){ return item && (item.title || item.content); } ).slice( 0, 12 );
		var id = 'digi-switcher-' + view.model.id; #><# if ( items.length ) { #>
		<div class="digi-content-switcher" data-digi-content-switcher><div class="digi-content-switcher__tabs" role="tablist" aria-label="Alternar contenido"><# _.each( items, function(item,index){ var icon = elementor.helpers.renderIcon( view, item.switcher_icon, { 'aria-hidden': true }, 'i', 'object' ); #><button type="button" class="digi-content-switcher__tab" id="{{ id }}-tab-{{ index }}" role="tab" aria-controls="{{ id }}-panel-{{ index }}" aria-selected="{{ index === 0 ? 'true' : 'false' }}" tabindex="{{ index === 0 ? '0' : '-1' }}"><# if ( icon && icon.rendered ) { #>{{{ icon.value }}}<# } #><span>{{ item.title || 'Opción ' + ( index + 1 ) }}</span></button><# }); #></div><# _.each( items, function(item,index){ #><div class="digi-content-switcher__panel" id="{{ id }}-panel-{{ index }}" role="tabpanel" aria-labelledby="{{ id }}-tab-{{ index }}" tabindex="0"<# if ( index ) { #> hidden<# } #>>{{{ item.content || '' }}}</div><# }); #></div><# } #>
		<?php
	}
}
