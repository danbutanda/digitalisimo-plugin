<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-image-markup.php';

/**
 * Mapa de OpenStreetMap (visor oficial, sin librerías ni clave) con la lista de ubicaciones. Con las fachadas de
 * «Rendimiento» (`Front\Facades`) se pinta como imagen estática guardada en el sitio, con el marcador, la atribución y
 * «Ver mapa interactivo»: OpenStreetMap no recibe ninguna petición del visitante hasta que lo pulsa.
 */
class Map_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-map'; }
	public function get_title() { return 'Mapa (OpenStreetMap)'; }
	public function get_icon() { return 'eicon-google-maps'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'mapa', 'openstreetmap', 'ubicación', 'marcador' ); }
	public function get_style_depends() { return array_merge( array( 'digitalisimo-map' ), $this->facade_depends() ); }
	public function get_script_depends() { return $this->facade_depends(); }

	/** ¿Están aplicadas las fachadas en esta petición? */
	private static function facades() {
		return class_exists( '\\Digitalisimo\\Elements\\Front\\Facades' ) && \Digitalisimo\Elements\Front\Facades::active();
	}

	/** Hoja o script de la fachada (con fachadas, todo mapa con una ubicación válida pinta una). */
	private function facade_depends() {
		if ( ! method_exists( $this, 'is_type_instance' ) || $this->is_type_instance() || ! self::facades() ) { return array(); }
		$s = (array) $this->get_settings_for_display();
		return \Digitalisimo\Elements\Front\Facades::depends( (bool) self::points( is_array( $s['markers'] ?? null ) ? $s['markers'] : array() ) );
	}

	/** Trabajo de la imagen del mapa: la primera ubicación con su acercamiento (caché de elementos), o []. */
	public static function facade_jobs( array $settings ) {
		$points = self::points( is_array( $settings['markers'] ?? null ) ? $settings['markers'] : array() );
		if ( ! $points || ! class_exists( '\\Digitalisimo\\Elements\\Front\\Facade_Images' ) ) { return array(); }
		$job = \Digitalisimo\Elements\Front\Facade_Images::map( $points[0][1], $points[0][2], (int) ( $settings['zoom']['size'] ?? 15 ) );
		return $job ? array( $job ) : array();
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_map', array( 'label' => 'Mapa' ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'title', array( 'label' => 'Nombre', 'type' => $c::TEXT, 'default' => 'Ubicación' ) );
		$repeater->add_control( 'lat', array( 'label' => 'Latitud', 'type' => $c::TEXT, 'default' => '19.4326' ) );
		$repeater->add_control( 'lng', array( 'label' => 'Longitud', 'type' => $c::TEXT, 'default' => '-99.1332' ) );
		$repeater->add_control( 'content', array( 'label' => 'Dirección o texto', 'type' => $c::TEXTAREA, 'default' => '' ) );
		$this->add_control( 'markers', array( 'label' => 'Ubicaciones', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'title' => 'Ubicación' ) ), 'title_field' => '{{{ title }}}' ) );
		$this->add_control( 'zoom', array( 'label' => 'Acercamiento', 'type' => $c::SLIDER, 'default' => array( 'size' => 15 ), 'range' => array( 'px' => array( 'min' => 1, 'max' => 19 ) ) ) );
		$this->add_control( 'show_list', array( 'label' => 'Mostrar la lista de ubicaciones', 'type' => $c::SWITCHER, 'default' => 'yes' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Mapa', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'height', array( 'label' => 'Alto', 'type' => $c::SLIDER, 'default' => array( 'size' => 400, 'unit' => 'px' ), 'range' => array( 'px' => array( 'min' => 150, 'max' => 900 ) ), 'selectors' => array( '{{WRAPPER}} .digi-map__frame' => 'height: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	/** Ubicaciones con coordenadas válidas: [nombre, lat, lng, texto]. */
	public static function points( array $markers ) {
		$out = array();
		foreach ( $markers as $marker ) {
			$lat = (float) str_replace( ',', '.', (string) ( $marker['lat'] ?? '' ) );
			$lng = (float) str_replace( ',', '.', (string) ( $marker['lng'] ?? '' ) );
			if ( '' === trim( (string) ( $marker['lat'] ?? '' ) ) || abs( $lat ) > 90 || abs( $lng ) > 180 ) { continue; }
			$out[] = array( trim( wp_strip_all_tags( (string) ( $marker['title'] ?? '' ) ) ), $lat, $lng, trim( wp_strip_all_tags( (string) ( $marker['content'] ?? '' ) ) ) );
		}
		return $out;
	}

	/** URL del visor de OpenStreetMap centrado en un punto con su marcador. */
	public static function embed_url( $lat, $lng, $zoom ) {
		$span = 360 / pow( 2, max( 1, min( 19, (int) $zoom ) ) );
		$bbox = sprintf( '%.6f,%.6f,%.6f,%.6f', $lng - $span, $lat - $span / 2, $lng + $span, $lat + $span / 2 );
		return 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode( $bbox ) . '&layer=mapnik&marker=' . rawurlencode( sprintf( '%.6f,%.6f', $lat, $lng ) );
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$points = self::points( is_array( $s['markers'] ?? null ) ? $s['markers'] : array() );
		if ( ! $points ) { return; }
		$zoom  = (int) ( $s['zoom']['size'] ?? 15 );
		$first = $points[0];
		$title = '' !== $first[0] ? $first[0] : 'Mapa';
		$list  = '';
		if ( 'yes' === ( $s['show_list'] ?? 'yes' ) ) {
			foreach ( $points as $point ) {
				$link  = sprintf( 'https://www.openstreetmap.org/?mlat=%1$.6f&mlon=%2$.6f#map=%3$d/%1$.6f/%2$.6f', $point[1], $point[2], $zoom );
				$list .= '<li class="digi-map__place">' . ( '' !== $point[0] ? '<strong>' . esc_html( $point[0] ) . '</strong>' : '' ) . ( '' !== $point[3] ? '<span>' . esc_html( $point[3] ) . '</span>' : '' ) . '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">Ver en OpenStreetMap</a></li>';
			}
			$list = '<ul class="digi-map__places">' . $list . '</ul>';
		}
		if ( self::facades() ) {
			$job    = \Digitalisimo\Elements\Front\Facade_Images::map( $first[1], $first[2], $zoom );
			$image  = $job ? \Digitalisimo\Elements\Front\Facade_Images::url( $job ) : '';
			$note   = '' !== $image ? '<a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap</a>' : '';
			$facade = \Digitalisimo\Elements\Front\Facades::markup(
				'map',
				array( 'src' => self::embed_url( $first[1], $first[2], $zoom ), 'title' => 'Mapa: ' . $title, 'class' => 'digi-map__frame', 'referrer' => 'no-referrer' ),
				array( 'name' => 'Ver mapa interactivo: ' . $title, 'label' => 'Ver mapa interactivo', 'image' => $image, 'pin' => true, 'note' => $note )
			);
			echo '<div class="digi-map">' . $facade . $list . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup() y la lista escapan cada valor.
			return;
		}
		echo '<div class="digi-map">' . Image_Markup::preconnect( 'https://www.openstreetmap.org/' ) . '<iframe class="digi-map__frame" src="' . esc_url( self::embed_url( $first[1], $first[2], $zoom ) ) . '" title="' . esc_attr( 'Mapa: ' . $title ) . '" loading="lazy" referrerpolicy="no-referrer"></iframe>' . $list . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- lista escapada al construirse.
	}
}
