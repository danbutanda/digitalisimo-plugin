<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Reproductor HTML nativo; no solicita el vídeo hasta que el visitante lo usa. */
class Video_Player_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-video-player'; }
	public function get_title() { return 'Reproductor de video'; }
	public function get_icon() { return 'eicon-video-camera'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'video', 'reproductor', 'mp4', 'webm' ); }
	public function get_style_depends() { return array( 'digitalisimo-video-player' ); }
	public function get_script_depends() { return array(); }

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

	protected function render() {
		$settings = $this->get_settings_for_display();
		$url = self::source_url( $settings );
		if ( '' === $url ) { return; }
		$title = trim( wp_strip_all_tags( (string) ( $settings['title'] ?? '' ) ) );
		if ( '' === $title ) { $title = 'Video'; }
		$poster_id = absint( $settings['poster']['id'] ?? 0 );
		$poster = $poster_id ? wp_get_attachment_image_url( $poster_id, 'full' ) : '';
		$ratio = (string) ( $settings['ratio'] ?? '16/9' );
		if ( ! in_array( $ratio, array( '16/9', '4/3', '1/1', 'auto' ), true ) ) { $ratio = '16/9'; }
		echo '<div class="digi-video-player" data-ratio="' . esc_attr( $ratio ) . '"><video controls playsinline preload="none" aria-label="' . esc_attr( $title ) . '"';
		if ( $poster ) { echo ' poster="' . esc_url( $poster ) . '"'; }
		echo '><source src="' . esc_url( $url ) . '">';
		echo esc_html( 'Tu navegador no puede reproducir este video.' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html( 'Abrir video' ) . '</a></video></div>';
	}

	protected function content_template() {
		?><# var source = settings.source === 'url' ? (settings.video_url && settings.video_url.url || '') : (settings.video && settings.video.url || ''); var ratio = ['16/9','4/3','1/1','auto'].indexOf(settings.ratio) >= 0 ? settings.ratio : '16/9'; #><div class="digi-video-player" data-ratio="{{ ratio }}"><# if (source) { #><video controls playsinline preload="none" src="{{ source }}" poster="{{ settings.poster && settings.poster.url || '' }}" aria-label="{{ settings.title || 'Video' }}"></video><# } else { #><p>Selecciona un video de la biblioteca o escribe su URL.</p><# } #></div><?php
	}
}
