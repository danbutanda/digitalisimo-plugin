<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Reproductor HTML nativo; no solicita el vídeo hasta que el visitante lo usa. Con una lista de dos o más
 * vídeos muestra sus títulos como botones que cambian el vídeo al pulsarlos o, si se elige, al pasar el
 * cursor o enfocarlos (entonces se reproduce silenciado).
 */
class Video_Player_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-video-player'; }
	public function get_title() { return 'Reproductor de video'; }
	public function get_icon() { return 'eicon-video-camera'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'video', 'reproductor', 'mp4', 'webm' ); }
	public function get_style_depends() { return array( 'digitalisimo-video-player' ); }
	/** El script sólo hace falta para cambiar de vídeo en una lista, o en la vista previa del editor. */
	public function get_script_depends() {
		if ( count( self::playlist( (array) $this->get_settings() ) ) > 1 ) {
			return array( 'digitalisimo-video-player' );
		}
		$preview = class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode();
		return $preview ? array( 'digitalisimo-video-player' ) : array();
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_video', array( 'label' => 'Video' ) );
		$this->add_control( 'source', array( 'label' => 'Fuente', 'type' => $c::SELECT, 'default' => 'media', 'options' => array( 'media' => 'Biblioteca de Medios', 'url' => 'URL externa' ) ) );
		$this->add_control( 'video', array( 'label' => 'Archivo de video', 'type' => $c::MEDIA, 'media_types' => array( 'video' ), 'condition' => array( 'source' => 'media' ) ) );
		$this->add_control( 'video_url', array( 'label' => 'URL del video', 'type' => $c::URL, 'placeholder' => 'https://ejemplo.com/video.mp4', 'condition' => array( 'source' => 'url' ) ) );
		$this->add_control( 'poster', array( 'label' => 'Imagen de portada', 'type' => $c::MEDIA, 'media_types' => array( 'image' ) ) );
		$this->add_control( 'title', array( 'label' => 'Nombre accesible', 'type' => $c::TEXT, 'default' => 'Video', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'ratio', array( 'label' => 'Proporción', 'type' => $c::SELECT, 'default' => '16/9', 'options' => array( '16/9' => '16:9', '4/3' => '4:3', '1/1' => '1:1', 'auto' => 'Automática' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_playlist', array( 'label' => 'Lista de vídeos' ) );
		$item = new \Elementor\Repeater();
		$item->add_control( 'source', array( 'label' => 'Fuente', 'type' => $c::SELECT, 'default' => 'media', 'options' => array( 'media' => 'Biblioteca de Medios', 'url' => 'URL externa' ) ) );
		$item->add_control( 'video', array( 'label' => 'Archivo de video', 'type' => $c::MEDIA, 'media_types' => array( 'video' ), 'condition' => array( 'source' => 'media' ) ) );
		$item->add_control( 'video_url', array( 'label' => 'URL del video', 'type' => $c::URL, 'placeholder' => 'https://ejemplo.com/video.mp4', 'condition' => array( 'source' => 'url' ) ) );
		$item->add_control( 'poster', array( 'label' => 'Imagen de portada', 'type' => $c::MEDIA, 'media_types' => array( 'image' ) ) );
		$item->add_control( 'title', array( 'label' => 'Título', 'type' => $c::TEXT, 'default' => 'Video', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'playlist', array( 'label' => 'Vídeos', 'type' => $c::REPEATER, 'fields' => $item->get_controls(), 'default' => array(), 'title_field' => '{{{ title }}}', 'description' => 'Con dos o más vídeos se muestran sus títulos para elegir y la lista sustituye al video de arriba.' ) );
		$this->add_control( 'playlist_trigger', array( 'label' => 'Cambiar de vídeo', 'type' => $c::SELECT, 'default' => 'click', 'options' => array( 'click' => 'Al pulsar su título', 'hover' => 'Al pasar el cursor o enfocar su título' ), 'description' => 'Al pasar el cursor el vídeo se reproduce silenciado.' ) );
		$this->end_controls_section();
	}

	private static function source_url( $settings ) {
		if ( 'url' === ( $settings['source'] ?? 'media' ) ) {
			$url = (string) ( $settings['video_url']['url'] ?? '' );
		} else {
			$id = absint( $settings['video']['id'] ?? 0 );
			$mime = $id ? (string) get_post_mime_type( $id ) : '';
			if ( 0 !== strpos( $mime, 'video/' ) ) { return ''; }
			$url = (string) wp_get_attachment_url( $id );
		}
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		return in_array( $scheme, array( 'http', 'https' ), true ) ? $url : '';
	}

	private static function poster( $poster ) {
		$id = absint( $poster['id'] ?? 0 );
		$url = $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : (string) ( $poster['url'] ?? '' );
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		return in_array( $scheme, array( 'http', 'https' ), true ) ? $url : '';
	}

	/** Vídeos válidos de la lista: url, portada y título. */
	private static function playlist( $settings ) {
		$items = array();
		foreach ( is_array( $settings['playlist'] ?? null ) ? $settings['playlist'] : array() as $row ) {
			$url = is_array( $row ) ? self::source_url( $row ) : '';
			if ( '' === $url ) { continue; }
			$title = trim( wp_strip_all_tags( (string) ( $row['title'] ?? '' ) ) );
			$items[] = array( 'url' => $url, 'poster' => self::poster( is_array( $row['poster'] ?? null ) ? $row['poster'] : array() ), 'title' => '' !== $title ? $title : 'Video ' . ( count( $items ) + 1 ) );
		}
		return $items;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$list = self::playlist( $settings );
		if ( count( $list ) > 1 ) {
			$current = $list[0];
		} else {
			$title = trim( wp_strip_all_tags( (string) ( $settings['title'] ?? '' ) ) );
			$current = array( 'url' => self::source_url( $settings ), 'poster' => self::poster( is_array( $settings['poster'] ?? null ) ? $settings['poster'] : array() ), 'title' => '' !== $title ? $title : 'Video' );
			$list = array();
		}
		if ( '' === $current['url'] ) { return; }
		$ratio = (string) ( $settings['ratio'] ?? '16/9' );
		if ( ! in_array( $ratio, array( '16/9', '4/3', '1/1', 'auto' ), true ) ) { $ratio = '16/9'; }
		$trigger = 'hover' === ( $settings['playlist_trigger'] ?? 'click' ) ? 'hover' : 'click';
		echo '<div class="digi-video-player' . ( $list ? ' digi-video-player--playlist" data-digi-playlist="' . esc_attr( $trigger ) : '' ) . '" data-ratio="' . esc_attr( $ratio ) . '"><video controls playsinline preload="none" aria-label="' . esc_attr( $current['title'] ) . '"';
		if ( $current['poster'] ) { echo ' poster="' . esc_url( $current['poster'] ) . '"'; }
		echo '><source src="' . esc_url( $current['url'] ) . '">';
		echo esc_html( 'Tu navegador no puede reproducir este video.' ) . ' <a href="' . esc_url( $current['url'] ) . '">' . esc_html( 'Abrir video' ) . '</a></video>';
		if ( $list ) {
			echo '<ul class="digi-video-player__playlist" aria-label="' . esc_attr( 'Lista de vídeos' ) . '">';
			foreach ( $list as $index => $video ) {
				echo '<li><button type="button" class="digi-video-player__item" aria-pressed="' . ( 0 === $index ? 'true' : 'false' ) . '" data-src="' . esc_url( $video['url'] ) . '" data-poster="' . esc_url( $video['poster'] ) . '">' . esc_html( $video['title'] ) . '</button></li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	protected function content_template() {
		?><# var pick = function (s) { return s.source === 'url' ? (s.video_url && s.video_url.url || '') : (s.video && s.video.url || ''); };
		var list = _.filter(settings.playlist || [], function (row) { return pick(row); }); var current = list.length > 1 ? list[0] : settings; if (list.length < 2) { list = []; }
		var source = pick(current); var ratio = ['16/9','4/3','1/1','auto'].indexOf(settings.ratio) >= 0 ? settings.ratio : '16/9'; #><div class="digi-video-player<# if (list.length) { #> digi-video-player--playlist<# } #>" data-ratio="{{ ratio }}"><# if (source) { #><video controls playsinline preload="none" src="{{ source }}" poster="{{ current.poster && current.poster.url || '' }}" aria-label="{{ current.title || 'Video' }}"></video><# if (list.length) { #><ul class="digi-video-player__playlist"><# _.each(list, function (row, i) { #><li><button type="button" class="digi-video-player__item" aria-pressed="{{ i === 0 ? 'true' : 'false' }}" data-src="{{ pick(row) }}" data-poster="{{ row.poster && row.poster.url || '' }}">{{ row.title || 'Video' }}</button></li><# }); #></ul><# } #><# } else { #><p>Selecciona un video de la biblioteca o escribe su URL.</p><# } #></div><?php
	}
}
