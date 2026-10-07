<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Encabezado semántico con una sola etiqueta de título y decoración opcional. */
final class Advanced_Heading_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-advanced-heading'; }
	public function get_title() { return 'Encabezado avanzado'; }
	public function get_icon() { return 'eicon-t-letter'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'encabezado', 'título', 'heading', 'hero' ); }
	public function get_style_depends() { return array( 'digitalisimo-advanced-heading' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_content_heading', array( 'label' => 'Contenido' ) );
		$this->add_control( 'sub_heading', array( 'label' => 'Antetítulo', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$this->add_control( 'main_heading', array( 'label' => 'Título', 'type' => $c::TEXTAREA, 'dynamic' => array( 'active' => true ), 'default' => 'Encabezado avanzado', 'label_block' => true ) );
		$this->add_control( 'split_main_heading', array( 'label' => 'Destacar un fragmento', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'split_text', array( 'label' => 'Texto destacado', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'condition' => array( 'split_main_heading' => 'yes' ) ) );
		$this->add_control( 'link', array( 'label' => 'Enlace del título', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'header_size', array( 'label' => 'Etiqueta HTML', 'type' => $c::SELECT, 'default' => 'h2', 'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'Div', 'span' => 'Span' ) ) );
		$this->add_responsive_control( 'align', array( 'label' => 'Alineación', 'type' => $c::CHOOSE, 'default' => 'center', 'options' => array( 'left' => array( 'title' => 'Izquierda', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ), 'right' => array( 'title' => 'Derecha', 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-heading' => 'text-align: {{VALUE}};' ) ) );
		$this->add_control( 'advanced_heading_visibility', array( 'label' => 'Mostrar texto decorativo', 'type' => $c::SWITCHER, 'default' => '', 'return_value' => 'yes' ) );
		$this->add_control( 'advanced_heading', array( 'label' => 'Texto decorativo', 'type' => $c::TEXTAREA, 'dynamic' => array( 'active' => true ), 'condition' => array( 'advanced_heading_visibility' => 'yes' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_heading_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'title_color', array( 'label' => 'Color del título', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-heading__title' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'split_color', array( 'label' => 'Color destacado', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-heading__split' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'sub_color', array( 'label' => 'Color del antetítulo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-heading__sub' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'decoration_color', array( 'label' => 'Color decorativo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-heading__decoration' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'title_size', array( 'label' => 'Tamaño del título', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'rem', 'em' ), 'range' => array( 'px' => array( 'min' => 10, 'max' => 160 ) ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-heading__title' => 'font-size: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	private static function tag( $tag ) {
		return in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span' ), true ) ? $tag : 'h2';
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$title = trim( (string) ( $settings['main_heading'] ?? '' ) );
		$sub = trim( (string) ( $settings['sub_heading'] ?? '' ) );
		$split = 'yes' === ( $settings['split_main_heading'] ?? '' ) ? trim( (string) ( $settings['split_text'] ?? '' ) ) : '';
		if ( '' === $title && '' === $sub && '' === $split ) { return; }
		$tag = self::tag( $settings['header_size'] ?? 'h2' );
		$link = is_array( $settings['link'] ?? null ) ? $settings['link'] : array();
		$decor = 'yes' === ( $settings['advanced_heading_visibility'] ?? '' ) ? trim( (string) ( $settings['advanced_heading'] ?? '' ) ) : '';
		echo '<div class="digi-advanced-heading">';
		if ( '' !== $decor ) { echo '<span class="digi-advanced-heading__decoration" aria-hidden="true">' . esc_html( $decor ) . '</span>'; }
		if ( '' !== $sub ) { echo '<span class="digi-advanced-heading__sub">' . esc_html( $sub ) . '</span>'; }
		if ( '' !== $title || '' !== $split ) {
			echo '<' . $tag . ' class="digi-advanced-heading__title">';
			if ( ! empty( $link['url'] ) ) { $this->add_link_attributes( 'heading_link', $link ); echo '<a ' . $this->get_render_attribute_string( 'heading_link' ) . '>'; }
			if ( '' !== $title ) { echo '<span class="digi-advanced-heading__main">' . esc_html( $title ) . '</span>'; }
			if ( '' !== $split ) { echo ' <span class="digi-advanced-heading__split">' . esc_html( $split ) . '</span>'; }
			if ( ! empty( $link['url'] ) ) { echo '</a>'; }
			echo '</' . $tag . '>';
		}
		echo '</div>';
	}

	protected function content_template() {
		?>
		<# var tag = _.contains( ['h1','h2','h3','h4','h5','h6','div','span'], settings.header_size ) ? settings.header_size : 'h2';
		var split = settings.split_main_heading === 'yes' ? settings.split_text : '';
		var href = settings.link && settings.link.url ? settings.link.url : '';
		var rel = settings.link ? [ settings.link.is_external ? 'noopener noreferrer' : '', settings.link.nofollow ? 'nofollow' : '' ].join(' ').trim() : ''; #>
		<# if ( settings.main_heading || settings.sub_heading || split ) { #>
		<div class="digi-advanced-heading">
		<# if ( settings.advanced_heading_visibility === 'yes' && settings.advanced_heading ) { #><span class="digi-advanced-heading__decoration" aria-hidden="true">{{ settings.advanced_heading }}</span><# } #>
		<# if ( settings.sub_heading ) { #><span class="digi-advanced-heading__sub">{{ settings.sub_heading }}</span><# } #>
		<# if ( settings.main_heading || split ) { #><{{{ tag }}} class="digi-advanced-heading__title">
		<# if ( href ) { #><a href="{{ href }}" <# if ( settings.link.is_external ) { #>target="_blank"<# } #> <# if ( rel ) { #>rel="{{ rel }}"<# } #>><# } #>
		<# if ( settings.main_heading ) { #><span class="digi-advanced-heading__main">{{ settings.main_heading }}</span><# } #>
		<# if ( split ) { #> <span class="digi-advanced-heading__split">{{ split }}</span><# } #>
		<# if ( href ) { #></a><# } #></{{{ tag }}}><# } #>
		</div><# } #>
		<?php
	}
}
