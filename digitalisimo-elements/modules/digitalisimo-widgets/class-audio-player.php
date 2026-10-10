<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Reproductor de audio con <audio> nativo: una pista o una lista, con portada, título y artista. */
class Audio_Player_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-audio-player'; }
	public function get_title() { return 'Reproductor de audio'; }
	public function get_icon() { return 'eicon-headphones'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'audio', 'música', 'podcast', 'reproductor', 'mp3' ); }
	public function get_style_depends() { return array( 'digitalisimo-audio-player' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_tracks', array( 'label' => 'Pistas' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'audio', array( 'label' => 'Archivo de audio', 'type' => $c::MEDIA, 'media_types' => array( 'audio' ) ) );
		$repeater->add_control( 'audio_url', array( 'label' => 'O URL del audio', 'type' => $c::URL ) );
		$repeater->add_control( 'title', array( 'label' => 'Título', 'type' => $c::TEXT, 'default' => 'Pista', 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'artist', array( 'label' => 'Artista', 'type' => $c::TEXT, 'default' => '' ) );
		$repeater->add_control( 'cover', array( 'label' => 'Portada', 'type' => $c::MEDIA ) );
		$this->add_control( 'tracks', array( 'label' => 'Pistas', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'title' => 'Pista' ) ), 'title_field' => '{{{ title }}}' ) );
		$this->add_control( 'loop', array( 'label' => 'Repetir', 'type' => $c::SWITCHER ) );
		$this->add_control( 'preload', array( 'label' => 'Precarga', 'type' => $c::SELECT, 'default' => 'metadata', 'options' => array( 'none' => 'Ninguna', 'metadata' => 'Sólo datos', 'auto' => 'Completa' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Reproductor', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'background', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-audio__track' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-audio__track' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'cover_size', array( 'label' => 'Tamaño de la portada', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 40, 'max' => 400 ) ), 'selectors' => array( '{{WRAPPER}} .digi-audio__cover' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	/** URL de la pista: el archivo de la biblioteca o la dirección escrita. */
	public static function source( array $track ) {
		$url = (string) ( $track['audio']['url'] ?? '' );
		return '' !== $url ? $url : (string) ( $track['audio_url']['url'] ?? '' );
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$tracks = is_array( $s['tracks'] ?? null ) ? $s['tracks'] : array();
		$attrs  = 'controls preload="' . esc_attr( in_array( $s['preload'] ?? 'metadata', array( 'none', 'metadata', 'auto' ), true ) ? $s['preload'] : 'metadata' ) . '"' . ( 'yes' === ( $s['loop'] ?? '' ) ? ' loop' : '' );
		$out    = '';
		foreach ( $tracks as $index => $track ) {
			if ( ! is_array( $track ) ) { continue; }
			$src = self::source( $track );
			if ( '' === $src ) { continue; }
			$title  = trim( wp_strip_all_tags( (string) ( $track['title'] ?? '' ) ) );
			$artist = trim( wp_strip_all_tags( (string) ( $track['artist'] ?? '' ) ) );
			$cover  = (string) ( $track['cover']['url'] ?? '' );
			$name   = trim( $title . ( '' !== $artist ? ' — ' . $artist : '' ) );
			$out   .= '<li class="digi-audio__track elementor-repeater-item-' . esc_attr( sanitize_html_class( (string) ( $track['_id'] ?? $index ) ) ) . '">'
				. ( '' !== $cover ? '<img class="digi-audio__cover" src="' . esc_url( $cover ) . '" alt="" loading="lazy">' : '' )
				. '<div class="digi-audio__body">' . ( '' !== $title ? '<p class="digi-audio__title">' . esc_html( $title ) . '</p>' : '' ) . ( '' !== $artist ? '<p class="digi-audio__artist">' . esc_html( $artist ) . '</p>' : '' )
				. '<audio ' . $attrs . ' src="' . esc_url( $src ) . '"' . ( '' !== $name ? ' aria-label="' . esc_attr( $name ) . '"' : '' ) . '></audio></div></li>';
		}
		if ( '' !== $out ) {
			echo '<ul class="digi-audio">' . $out . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada parte se escapa al construirse.
		}
	}
}
