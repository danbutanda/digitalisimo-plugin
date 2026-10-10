<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Línea de tiempo como lista ordenada: elementos escritos o las últimas entradas. */
class Timeline_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-timeline'; }
	public function get_title() { return 'Línea de tiempo'; }
	public function get_icon() { return 'eicon-time-line'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'línea de tiempo', 'historia', 'hitos', 'cronología' ); }
	public function get_style_depends() { return array( 'digitalisimo-timeline' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_items', array( 'label' => 'Hitos' ) );
		$this->add_control( 'source', array( 'label' => 'Origen', 'type' => $c::SELECT, 'default' => 'items', 'options' => array( 'items' => 'Hitos escritos', 'posts' => 'Últimas entradas' ) ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'date', array( 'label' => 'Fecha', 'type' => $c::TEXT, 'default' => '2026' ) );
		$repeater->add_control( 'title', array( 'label' => 'Título', 'type' => $c::TEXT, 'default' => 'Hito', 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'text', array( 'label' => 'Texto', 'type' => $c::TEXTAREA, 'default' => '' ) );
		$repeater->add_control( 'image', array( 'label' => 'Imagen', 'type' => $c::MEDIA ) );
		$repeater->add_control( 'link', array( 'label' => 'Enlace', 'type' => $c::URL ) );
		$this->add_control( 'items', array( 'label' => 'Hitos', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'date' => '2024', 'title' => 'Inicio' ), array( 'date' => '2026', 'title' => 'Hoy' ) ), 'title_field' => '{{{ date }}} · {{{ title }}}', 'condition' => array( 'source' => 'items' ) ) );
		$this->add_control( 'posts_count', array( 'label' => 'Número de entradas', 'type' => $c::NUMBER, 'default' => 4, 'min' => 1, 'max' => 30, 'condition' => array( 'source' => 'posts' ) ) );
		$this->add_control( 'align', array( 'label' => 'Disposición', 'type' => $c::SELECT, 'default' => 'center', 'options' => array( 'center' => 'Alternada', 'left' => 'A la izquierda', 'right' => 'A la derecha' ) ) );
		$this->add_control( 'title_tag', array( 'label' => 'Etiqueta del título', 'type' => $c::SELECT, 'default' => 'h3', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'p' ) ) );
		$this->add_control( 'show_image', array( 'label' => 'Mostrar imágenes', 'type' => $c::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'read_more', array( 'label' => 'Texto del enlace', 'type' => $c::TEXT, 'default' => 'Leer más' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Línea', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'line_color', array( 'label' => 'Color de la línea', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-timeline' => '--digi-timeline-line: {{VALUE}};' ) ) );
		$this->add_control( 'dot_color', array( 'label' => 'Color del punto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-timeline' => '--digi-timeline-dot: {{VALUE}};' ) ) );
		$this->add_control( 'card_background', array( 'label' => 'Fondo del hito', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-timeline__card' => 'background-color: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	private function entries( array $s ) {
		if ( 'posts' === ( $s['source'] ?? 'items' ) ) {
			$entries = array();
			foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => max( 1, min( 30, (int) ( $s['posts_count'] ?? 4 ) ) ), 'no_found_rows' => true ) ) as $post ) {
				$entries[] = array( 'date' => get_the_date( '', $post ), 'title' => get_the_title( $post ), 'text' => wp_trim_words( get_the_excerpt( $post ), 20 ), 'image' => array( 'url' => (string) get_the_post_thumbnail_url( $post, 'medium' ) ), 'link' => array( 'url' => get_permalink( $post ) ) );
			}
			return $entries;
		}
		return is_array( $s['items'] ?? null ) ? $s['items'] : array();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$tag  = in_array( $s['title_tag'] ?? 'h3', array( 'h2', 'h3', 'h4', 'p' ), true ) ? $s['title_tag'] : 'h3';
		$more = trim( wp_strip_all_tags( (string) ( $s['read_more'] ?? '' ) ) );
		$out  = '';
		foreach ( $this->entries( $s ) as $entry ) {
			if ( ! is_array( $entry ) ) { continue; }
			$title = trim( wp_strip_all_tags( (string) ( $entry['title'] ?? '' ) ) );
			$date  = trim( wp_strip_all_tags( (string) ( $entry['date'] ?? '' ) ) );
			$text  = trim( wp_strip_all_tags( (string) ( $entry['text'] ?? '' ) ) );
			if ( '' === $title && '' === $text ) { continue; }
			$image = 'yes' === ( $s['show_image'] ?? 'yes' ) ? (string) ( $entry['image']['url'] ?? '' ) : '';
			$url   = (string) ( $entry['link']['url'] ?? '' );
			$out  .= '<li class="digi-timeline__item"><div class="digi-timeline__card">'
				. ( '' !== $date ? '<p class="digi-timeline__date">' . esc_html( $date ) . '</p>' : '' )
				. ( '' !== $image ? '<img class="digi-timeline__image" src="' . esc_url( $image ) . '" alt="" loading="lazy">' : '' )
				. ( '' !== $title ? '<' . $tag . ' class="digi-timeline__title">' . esc_html( $title ) . '</' . $tag . '>' : '' )
				. ( '' !== $text ? '<p class="digi-timeline__text">' . esc_html( $text ) . '</p>' : '' )
				. ( '' !== $url && '' !== $more ? '<a class="digi-timeline__link" href="' . esc_url( $url ) . '">' . esc_html( $more ) . ( '' !== $title ? '<span class="screen-reader-text">: ' . esc_html( $title ) . '</span>' : '' ) . '</a>' : '' )
				. '</div></li>';
		}
		if ( '' === $out ) { return; }
		$align = in_array( $s['align'] ?? 'center', array( 'center', 'left', 'right' ), true ) ? $s['align'] : 'center';
		echo '<ol class="digi-timeline digi-timeline--' . esc_attr( $align ) . '">' . $out . '</ol>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada hito se escapa al construirse.
	}
}
