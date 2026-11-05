<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Bloque de llamada a la acción con contenido y enlace semánticos. */
class Call_Out_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-call-out'; }
	public function get_title() { return 'Llamada a la acción'; }
	public function get_icon() { return 'eicon-call-to-action'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'call out', 'acción', 'cta', 'aviso' ); }
	public function get_style_depends() { return array( 'digitalisimo-call-out' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_call_out', array( 'label' => 'Contenido' ) );
		$this->add_control( 'title', array( 'label' => 'Título', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'default' => 'Hablemos de tu proyecto', 'label_block' => true ) );
		$this->add_control( 'description', array( 'label' => 'Descripción', 'type' => $c::TEXTAREA, 'dynamic' => array( 'active' => true ), 'default' => 'Cuéntanos qué necesitas y te ayudaremos a encontrar una solución.', 'label_block' => true ) );
		$this->add_control( 'title_size', array( 'label' => 'Etiqueta del título', 'type' => $c::SELECT, 'default' => 'h3', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'Div' ) ) );
		$this->add_control( 'button_text', array( 'label' => 'Texto del botón', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'default' => 'Contactar' ) );
		$this->add_control( 'link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'button_icon', array( 'label' => 'Icono del botón', 'type' => $c::ICONS ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_call_out_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'layout', array( 'label' => 'Distribución', 'type' => $c::SELECT, 'default' => 'row', 'options' => array( 'row' => 'Horizontal', 'column' => 'Vertical' ), 'selectors' => array( '{{WRAPPER}} .digi-call-out' => 'flex-direction:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'align', array( 'label' => 'Alineación de texto', 'type' => $c::CHOOSE, 'options' => array( 'left' => array( 'title' => 'Izquierda', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ), 'right' => array( 'title' => 'Derecha', 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .digi-call-out' => 'text-align:{{VALUE}};' ) ) );
		$this->add_control( 'background_color', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-call-out' => 'background-color:{{VALUE}};' ) ) );
		$this->add_control( 'text_color', array( 'label' => 'Texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-call-out' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'button_color', array( 'label' => 'Fondo del botón', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-call-out__button' => 'background-color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$title = trim( wp_strip_all_tags( (string) ( $s['title'] ?? '' ) ) );
		$description = trim( wp_strip_all_tags( (string) ( $s['description'] ?? '' ) ) );
		$button = trim( wp_strip_all_tags( (string) ( $s['button_text'] ?? '' ) ) );
		$link = is_array( $s['link'] ?? null ) ? $s['link'] : array();
		if ( '' === $title && '' === $description && ( '' === $button || empty( $link['url'] ) ) ) { return; }
		$tag = in_array( $s['title_size'] ?? 'h3', array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), true ) ? ( $s['title_size'] ?? 'h3' ) : 'h3';
		echo '<div class="digi-call-out"><div class="digi-call-out__content">';
		if ( $title ) { echo '<' . esc_attr( $tag ) . ' class="digi-call-out__title">' . esc_html( $title ) . '</' . esc_attr( $tag ) . '>'; }
		if ( $description ) { echo '<p class="digi-call-out__description">' . nl2br( esc_html( $description ) ) . '</p>'; }
		echo '</div>';
		if ( $button && ! empty( $link['url'] ) ) {
			$this->add_link_attributes( 'call_out_link', $link );
			if ( ! empty( $link['is_external'] ) ) {
				$this->add_render_attribute( 'call_out_link', 'rel', array( 'noopener', 'noreferrer' ) );
			}
			echo '<a class="digi-call-out__button" ' . $this->get_render_attribute_string( 'call_out_link' ) . '>';
			$icon = is_array( $s['button_icon'] ?? null ) ? $s['button_icon'] : array();
			if ( ! empty( $icon['value'] ) ) { \Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) ); }
			echo '<span>' . esc_html( $button ) . '</span></a>';
		}
		echo '</div>';
	}

	protected function content_template() {
		?>
		<# var tag = _.contains( ['h2','h3','h4','h5','h6','div'], settings.title_size ) ? settings.title_size : 'h3'; #>
		<# var icon = elementor.helpers.renderIcon( view, settings.button_icon, { 'aria-hidden': true }, 'i', 'object' );
		var rel = [ settings.link && settings.link.nofollow ? 'nofollow' : '', settings.link && settings.link.is_external ? 'noopener noreferrer' : '' ].filter( Boolean ).join( ' ' ); #>
		<# if ( settings.title || settings.description || ( settings.button_text && settings.link && settings.link.url ) ) { #>
		<div class="digi-call-out"><div class="digi-call-out__content">
		<# if ( settings.title ) { #><{{{ tag }}} class="digi-call-out__title">{{ settings.title }}</{{{ tag }}}><# } #>
		<# if ( settings.description ) { #><p class="digi-call-out__description">{{ settings.description }}</p><# } #>
		</div><# if ( settings.button_text && settings.link && settings.link.url ) { #><a class="digi-call-out__button" href="{{ settings.link.url }}"<# if ( settings.link.is_external ) { #> target="_blank"<# } #><# if ( rel ) { #> rel="{{ rel }}"<# } #>><# if ( icon && icon.rendered ) { #>{{{ icon.value }}}<# } #><span>{{ settings.button_text }}</span></a><# } #></div><# } #>
		<?php
	}
}
