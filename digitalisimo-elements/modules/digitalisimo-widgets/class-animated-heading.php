<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Encabezado legible desde el HTML inicial con rotación progresiva y opcional. */
class Animated_Heading_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-animated-heading'; }
	public function get_title() { return 'Encabezado animado'; }
	public function get_icon() { return 'eicon-animation-text'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'encabezado', 'animado', 'heading', 'título' ); }
	public function get_style_depends() { return array( 'digitalisimo-animated-heading' ); }
	public function get_script_depends() { return array( 'digitalisimo-animated-heading' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_heading', array( 'label' => 'Contenido' ) );
		$this->add_control( 'before_text', array( 'label' => 'Texto anterior', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$this->add_control( 'animated_text', array( 'label' => 'Frases rotativas', 'type' => $c::TEXTAREA, 'default' => "Ideas\nResultados", 'description' => 'Una frase por línea. La primera aparece desde el inicio; las demás se muestran sólo si caben sin cambiar el diseño.', 'label_block' => true ) );
		$this->add_control( 'after_text', array( 'label' => 'Texto posterior', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true ) );
		$this->add_control( 'heading_tag', array( 'label' => 'Etiqueta HTML', 'type' => $c::SELECT, 'default' => 'h2', 'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'div' => 'Div' ) ) );
		$this->add_control( 'heading_link', array( 'label' => 'Enlace', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'rotate', array( 'label' => 'Rotar frases', 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'interval', array( 'label' => 'Tiempo por frase (ms)', 'type' => $c::NUMBER, 'default' => 3000, 'min' => 1500, 'max' => 15000, 'step' => 100, 'condition' => array( 'rotate' => 'yes' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_heading_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'align', array( 'label' => 'Alineación', 'type' => $c::CHOOSE, 'options' => array( 'left' => array( 'title' => 'Izquierda', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ), 'right' => array( 'title' => 'Derecha', 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .digi-animated-heading' => 'text-align:{{VALUE}};' ) ) );
		$this->add_control( 'heading_color', array( 'label' => 'Color del encabezado', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-animated-heading' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'animated_color', array( 'label' => 'Color de frase', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-animated-heading__phrase' => 'color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'font_size', array( 'label' => 'Tamaño', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'rem', 'em' ), 'range' => array( 'px' => array( 'min' => 10, 'max' => 160 ) ), 'selectors' => array( '{{WRAPPER}} .digi-animated-heading' => 'font-size:{{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	private static function phrases( $value ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
		$phrases = array();
		foreach ( $lines as $line ) {
			$phrase = trim( wp_strip_all_tags( $line ) );
			if ( '' !== $phrase && ! in_array( $phrase, $phrases, true ) ) { $phrases[] = $phrase; }
			if ( count( $phrases ) >= 12 ) { break; }
		}
		return $phrases;
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$before = trim( wp_strip_all_tags( (string) ( $s['before_text'] ?? '' ) ) );
		$after = trim( wp_strip_all_tags( (string) ( $s['after_text'] ?? '' ) ) );
		$phrases = self::phrases( $s['animated_text'] ?? '' );
		if ( '' === $before && '' === $after && ! $phrases ) { return; }
		$tag = in_array( $s['heading_tag'] ?? 'h2', array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), true ) ? ( $s['heading_tag'] ?? 'h2' ) : 'h2';
		$link = is_array( $s['heading_link'] ?? null ) ? $s['heading_link'] : array();
		$interval = min( 15000, max( 1500, absint( $s['interval'] ?? 3000 ) ) );
		$rotates = 'yes' === ( $s['rotate'] ?? 'yes' ) && count( $phrases ) > 1;
		$length = 0;
		foreach ( $phrases as $phrase ) { $length = max( $length, function_exists( 'mb_strlen' ) ? mb_strlen( $phrase ) : strlen( $phrase ) ); }
		// El espacio reservado existe en el primer HTML; JS nunca cambia el tamaño de la caja.
		echo '<' . esc_attr( $tag ) . ' class="digi-animated-heading"';
		if ( $rotates ) { echo ' data-digi-phrases="' . esc_attr( wp_json_encode( $phrases ) ) . '" data-digi-interval="' . esc_attr( (string) $interval ) . '"'; }
		echo '>';
		if ( ! empty( $link['url'] ) ) {
			$this->add_link_attributes( 'animated_heading_link', $link );
			if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( 'animated_heading_link', 'rel', array( 'noopener', 'noreferrer' ) ); }
			echo '<a ' . $this->get_render_attribute_string( 'animated_heading_link' ) . '>';
		}
		if ( $before ) { echo '<span class="digi-animated-heading__before">' . esc_html( $before ) . '</span> '; }
		if ( $phrases ) {
			echo '<span class="digi-animated-heading__slot" style="--digi-phrase-ch:' . esc_attr( (string) min( $length + 2, 100 ) ) . 'ch"><span class="digi-animated-heading__phrase">' . esc_html( $phrases[0] ) . '</span></span>';
		}
		if ( $after ) { echo ' <span class="digi-animated-heading__after">' . esc_html( $after ) . '</span>'; }
		if ( ! empty( $link['url'] ) ) { echo '</a>'; }
		echo '</' . esc_attr( $tag ) . '>';
	}

	protected function content_template() {
		?>
		<# var tag = _.contains( ['h1','h2','h3','h4','h5','h6','div'], settings.heading_tag ) ? settings.heading_tag : 'h2';
		var phrases = ( settings.animated_text || '' ).split(/\r?\n/).map(function(item){ return item.trim(); }).filter(Boolean);
		var maxLength = Math.min(100, Math.max.apply(null, [0].concat(phrases.map(function(item){ return item.length + 2; }))));
		var href = settings.heading_link && settings.heading_link.url ? settings.heading_link.url : '';
		var rel = settings.heading_link ? [ settings.heading_link.is_external ? 'noopener noreferrer' : '', settings.heading_link.nofollow ? 'nofollow' : '' ].filter(Boolean).join(' ') : ''; #>
		<# if ( settings.before_text || settings.after_text || phrases.length ) { #>
		<{{{ tag }}} class="digi-animated-heading">
		<# if ( href ) { #><a href="{{ href }}"<# if ( settings.heading_link.is_external ) { #> target="_blank"<# } #><# if ( rel ) { #> rel="{{ rel }}"<# } #>><# } #>
		<# if ( settings.before_text ) { #><span class="digi-animated-heading__before">{{ settings.before_text }}</span> <# } #>
		<# if ( phrases.length ) { #><span class="digi-animated-heading__slot" style="--digi-phrase-ch:{{ maxLength }}ch"><span class="digi-animated-heading__phrase">{{ phrases[0] }}</span></span><# } #>
		<# if ( settings.after_text ) { #> <span class="digi-animated-heading__after">{{ settings.after_text }}</span><# } #>
		<# if ( href ) { #></a><# } #>
		</{{{ tag }}}><# } #>
		<?php
	}
}
