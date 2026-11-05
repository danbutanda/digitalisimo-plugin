<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Tiempo de lectura estimado de la entrada actual, calculado en el servidor. */
class Reading_Time_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-reading-time'; }
	public function get_title() { return 'Tiempo de lectura'; }
	public function get_icon() { return 'eicon-clock-o'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'lectura', 'tiempo', 'minutos', 'entrada' ); }
	public function get_style_depends() { return array( 'digitalisimo-reading-time' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_content', array( 'label' => 'Tiempo de lectura' ) );
		$this->add_control( 'words_per_minute', array( 'label' => 'Palabras por minuto', 'type' => $c::NUMBER, 'default' => 200, 'min' => 50, 'max' => 1000 ) );
		$this->add_control( 'before', array( 'label' => 'Texto antes', 'type' => $c::TEXT, 'default' => '' ) );
		$this->add_control( 'minute_text', array( 'label' => 'Texto de minutos', 'type' => $c::TEXT, 'default' => 'min de lectura' ) );
		$this->add_control( 'icon', array( 'label' => 'Icono', 'type' => $c::ICONS ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Texto', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-reading-time' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'typography', 'selector' => '{{WRAPPER}} .digi-reading-time' ) );
		$this->end_controls_section();
	}

	/** Minutos (al menos uno) para un texto. */
	public static function minutes( $content, $wpm ) {
		$text  = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $content ) ) );
		$words = '' === $text ? 0 : count( preg_split( '/\s/u', $text ) );
		return max( 1, (int) ceil( $words / max( 50, (int) $wpm ) ) );
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$post = get_post();
		if ( ! $post ) { return; }
		$minutes = self::minutes( strip_shortcodes( (string) $post->post_content ), (int) ( $s['words_per_minute'] ?? 200 ) );
		$icon    = '';
		if ( ! empty( $s['icon']['value'] ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $s['icon'], array( 'aria-hidden' => 'true' ) );
			$icon = '<span class="digi-reading-time__icon">' . (string) ob_get_clean() . '</span>';
		}
		$before = trim( wp_strip_all_tags( (string) ( $s['before'] ?? '' ) ) );
		echo '<p class="digi-reading-time">' . $icon . ( '' !== $before ? esc_html( $before ) . ' ' : '' ) . '<span class="digi-reading-time__value">' . esc_html( (string) $minutes ) . '</span> ' . esc_html( trim( (string) ( $s['minute_text'] ?? '' ) ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icono de Elementor.
	}
}
