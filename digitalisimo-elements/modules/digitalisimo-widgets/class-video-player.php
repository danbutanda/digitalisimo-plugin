<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Reproductor de vídeo: archivo con el reproductor HTML nativo (no se solicita hasta que el visitante lo usa)
 * o vídeo incrustado de YouTube, Vimeo, Dailymotion o Wistia (con el dominio sin rastreo cuando el servicio
 * lo ofrece y carga diferida). Con una lista de dos o más vídeos muestra sus títulos y descripciones como
 * botones que cambian el vídeo al pulsarlos o, si se elige, al pasar el cursor o enfocarlos (entonces se
 * reproduce silenciado).
 */
class Video_Player_Widget extends \Elementor\Widget_Base {
	const SOURCES = array( 'media' => 'Biblioteca de Medios', 'url' => 'URL externa', 'embed' => 'YouTube, Vimeo, Dailymotion o Wistia' );

	public function get_name() { return 'digitalisimo-video-player'; }
	public function get_title() { return 'Reproductor de video'; }
	public function get_icon() { return 'eicon-video-camera'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'video', 'reproductor', 'mp4', 'webm', 'youtube', 'vimeo', 'lista' ); }
	public function get_style_depends() { return array( 'digitalisimo-video-player' ); }
	/**
	 * El script sólo hace falta para cambiar de vídeo en una lista, o en la vista previa del editor. La instancia
	 * de registro del widget no tiene ajustes (leerlos provoca un error fatal en Elementor): devuelve el script.
	 */
	public function get_script_depends() {
		if ( $this->is_type_instance() ) {
			return array( 'digitalisimo-video-player' );
		}
		if ( count( self::playlist( (array) $this->get_settings() ) ) > 1 ) {
			return array( 'digitalisimo-video-player' );
		}
		$preview = class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode();
		return $preview ? array( 'digitalisimo-video-player' ) : array();
	}

	private static function source_controls( $stack, $c ) {
		$stack->add_control( 'source', array( 'label' => 'Fuente', 'type' => $c::SELECT, 'default' => 'media', 'options' => self::SOURCES ) );
		$stack->add_control( 'video', array( 'label' => 'Archivo de video', 'type' => $c::MEDIA, 'media_types' => array( 'video' ), 'condition' => array( 'source' => 'media' ) ) );
		$stack->add_control( 'video_url', array( 'label' => 'URL del video', 'type' => $c::URL, 'placeholder' => 'https://ejemplo.com/video.mp4', 'condition' => array( 'source' => 'url' ) ) );
		$stack->add_control( 'embed_url', array( 'label' => 'Enlace del vídeo', 'type' => $c::TEXT, 'placeholder' => 'https://youtu.be/…', 'dynamic' => array( 'active' => true ), 'condition' => array( 'source' => 'embed' ) ) );
		$stack->add_control( 'poster', array( 'label' => 'Imagen de portada', 'type' => $c::MEDIA, 'media_types' => array( 'image' ), 'condition' => array( 'source!' => 'embed' ) ) );
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_video', array( 'label' => 'Video' ) );
		self::source_controls( $this, $c );
		$this->add_control( 'title', array( 'label' => 'Nombre accesible', 'type' => $c::TEXT, 'default' => 'Video', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'ratio', array( 'label' => 'Proporción', 'type' => $c::SELECT, 'default' => '16/9', 'options' => array( '16/9' => '16:9', '4/3' => '4:3', '1/1' => '1:1', 'auto' => 'Automática' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_playlist', array( 'label' => 'Lista de vídeos' ) );
		$item = new \Elementor\Repeater();
		self::source_controls( $item, $c );
		$item->add_control( 'title', array( 'label' => 'Título', 'type' => $c::TEXT, 'default' => 'Video', 'dynamic' => array( 'active' => true ) ) );
		$item->add_control( 'description', array( 'label' => 'Descripción', 'type' => $c::TEXTAREA, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'playlist', array( 'label' => 'Vídeos', 'type' => $c::REPEATER, 'fields' => $item->get_controls(), 'default' => array(), 'title_field' => '{{{ title }}}', 'description' => 'Con dos o más vídeos se muestran sus títulos para elegir y la lista sustituye al video de arriba.' ) );
		$this->add_control( 'playlist_caption', array( 'label' => 'Mostrar título y descripción del vídeo elegido', 'type' => $c::SWITCHER, 'description' => 'Bajo el vídeo; cambian al elegir otro.' ) );
		$this->add_control( 'playlist_trigger', array( 'label' => 'Cambiar de vídeo', 'type' => $c::SELECT, 'default' => 'click', 'options' => array( 'click' => 'Al pulsar su título', 'hover' => 'Al pasar el cursor o enfocar su título' ), 'description' => 'Al pasar el cursor el vídeo se reproduce silenciado.' ) );
		$this->end_controls_section();
	}

	/**
	 * Dirección para incrustar un enlace de YouTube, Vimeo, Dailymotion o Wistia, con el dominio sin rastreo
	 * cuando el servicio lo ofrece; '' si el enlace no es de ninguno.
	 */
	public static function embed_url( $url ) {
		$url = trim( (string) $url );
		if ( preg_match( '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?|shorts)/|.*[?&]v=)|youtu\.be/)([A-Za-z0-9_-]{11})%i', $url, $m ) ) {
			return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0';
		}
		if ( preg_match( '%vimeo\.com/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d+)%i', $url, $m ) ) {
			return 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1';
		}
		if ( preg_match( '%(?:dailymotion\.com/(?:embed/)?video/|dai\.ly/)([A-Za-z0-9]+)%i', $url, $m ) ) {
			return 'https://www.dailymotion.com/embed/video/' . $m[1];
		}
		if ( preg_match( '%(?:wistia\.(?:com|net)/(?:medias|embed/iframe)/|wi\.st/)([A-Za-z0-9]+)%i', $url, $m ) ) {
			return 'https://fast.wistia.net/embed/iframe/' . $m[1];
		}
		return '';
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

	/** Vídeo de unos ajustes: «video» (archivo) o «embed» (servicio) y su dirección; null si no hay uno válido. */
	private static function media( $settings ) {
		$source = (string) ( $settings['source'] ?? 'media' );
		if ( 'embed' === $source || 'url' === $source ) {
			$embed = self::embed_url( 'embed' === $source ? ( $settings['embed_url'] ?? '' ) : ( $settings['video_url']['url'] ?? '' ) );
			if ( '' !== $embed ) {
				return array( 'kind' => 'embed', 'url' => $embed );
			}
			if ( 'embed' === $source ) {
				return null;
			}
		}
		$url = self::source_url( $settings );
		return '' !== $url ? array( 'kind' => 'video', 'url' => $url ) : null;
	}

	private static function poster( $poster ) {
		$id = absint( $poster['id'] ?? 0 );
		$url = $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : (string) ( $poster['url'] ?? '' );
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		return in_array( $scheme, array( 'http', 'https' ), true ) ? $url : '';
	}

	/** Vídeos válidos de la lista: tipo, dirección, portada, título y descripción. */
	private static function playlist( $settings ) {
		$items = array();
		foreach ( is_array( $settings['playlist'] ?? null ) ? $settings['playlist'] : array() as $row ) {
			$media = is_array( $row ) ? self::media( $row ) : null;
			if ( ! $media ) { continue; }
			$title = trim( wp_strip_all_tags( (string) ( $row['title'] ?? '' ) ) );
			$items[] = $media + array(
				'poster'      => 'video' === $media['kind'] ? self::poster( is_array( $row['poster'] ?? null ) ? $row['poster'] : array() ) : '',
				'title'       => '' !== $title ? $title : 'Video ' . ( count( $items ) + 1 ),
				'description' => trim( wp_strip_all_tags( (string) ( $row['description'] ?? '' ) ) ),
			);
		}
		return $items;
	}

	/** Elemento del escenario: <video> nativo o <iframe> del servicio, con nombre accesible. */
	private static function stage( array $item ) {
		if ( 'embed' === $item['kind'] ) {
			return '<iframe class="digi-video-player__embed" src="' . esc_url( $item['url'] ) . '" title="' . esc_attr( $item['title'] ) . '" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>';
		}
		return '<video controls playsinline preload="none" aria-label="' . esc_attr( $item['title'] ) . '"' . ( $item['poster'] ? ' poster="' . esc_url( $item['poster'] ) . '"' : '' ) . '><source src="' . esc_url( $item['url'] ) . '">'
			. esc_html( 'Tu navegador no puede reproducir este video.' ) . ' <a href="' . esc_url( $item['url'] ) . '">' . esc_html( 'Abrir video' ) . '</a></video>';
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$list = self::playlist( $settings );
		if ( count( $list ) > 1 ) {
			$current = $list[0];
		} else {
			$media = self::media( $settings );
			if ( ! $media ) { return; }
			$title = trim( wp_strip_all_tags( (string) ( $settings['title'] ?? '' ) ) );
			$current = $media + array( 'poster' => 'video' === $media['kind'] ? self::poster( is_array( $settings['poster'] ?? null ) ? $settings['poster'] : array() ) : '', 'title' => '' !== $title ? $title : 'Video' );
			$list = array();
		}
		$ratio = (string) ( $settings['ratio'] ?? '16/9' );
		if ( ! in_array( $ratio, array( '16/9', '4/3', '1/1', 'auto' ), true ) ) { $ratio = '16/9'; }
		if ( ! $list ) {
			echo '<div class="digi-video-player" data-ratio="' . esc_attr( $ratio ) . '">' . self::stage( $current ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stage() escapa cada atributo.
			return;
		}
		$trigger = 'hover' === ( $settings['playlist_trigger'] ?? 'click' ) ? 'hover' : 'click';
		echo '<div class="digi-video-player digi-video-player--playlist" data-digi-playlist="' . esc_attr( $trigger ) . '" data-ratio="' . esc_attr( $ratio ) . '"><div class="digi-video-player__stage">' . self::stage( $current ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stage() escapa cada atributo.
		if ( 'yes' === ( $settings['playlist_caption'] ?? '' ) ) {
			echo '<div class="digi-video-player__now" aria-live="polite"><p class="digi-video-player__now-title">' . esc_html( $current['title'] ) . '</p><p class="digi-video-player__now-description"' . ( '' === $current['description'] ? ' hidden' : '' ) . '>' . esc_html( $current['description'] ) . '</p></div>';
		}
		echo '<ul class="digi-video-player__playlist" aria-label="' . esc_attr( 'Lista de vídeos' ) . '">';
		foreach ( $list as $index => $video ) {
			echo '<li><button type="button" class="digi-video-player__item" aria-pressed="' . ( 0 === $index ? 'true' : 'false' ) . '" data-kind="' . esc_attr( $video['kind'] ) . '" data-src="' . esc_url( $video['url'] ) . '" data-poster="' . esc_url( $video['poster'] ) . '" data-title="' . esc_attr( $video['title'] ) . '"><span class="digi-video-player__item-title">' . esc_html( $video['title'] ) . '</span>'
				. ( '' !== $video['description'] ? '<span class="digi-video-player__item-description">' . esc_html( $video['description'] ) . '</span>' : '' ) . '</button></li>';
		}
		echo '</ul></div>';
	}

	protected function content_template() {
		?><# var embed = function (url) { url = String(url || ''); var m;
			if ((m = url.match(/(?:youtube(?:-nocookie)?\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([A-Za-z0-9_-]{11})/i))) { return 'https://www.youtube-nocookie.com/embed/' + m[1] + '?rel=0'; }
			if ((m = url.match(/vimeo\.com\/(?:video\/|channels\/[^\/]+\/|groups\/[^\/]+\/videos\/)?(\d+)/i))) { return 'https://player.vimeo.com/video/' + m[1] + '?dnt=1'; }
			if ((m = url.match(/(?:dailymotion\.com\/(?:embed\/)?video\/|dai\.ly\/)([A-Za-z0-9]+)/i))) { return 'https://www.dailymotion.com/embed/video/' + m[1]; }
			if ((m = url.match(/(?:wistia\.(?:com|net)\/(?:medias|embed\/iframe)\/|wi\.st\/)([A-Za-z0-9]+)/i))) { return 'https://fast.wistia.net/embed/iframe/' + m[1]; }
			return ''; };
		var pick = function (s) { if (s.source === 'embed') { var e = embed(s.embed_url); return e ? { kind: 'embed', url: e } : null; } var u = s.source === 'url' ? (s.video_url && s.video_url.url || '') : (s.video && s.video.url || ''); if (!u) { return null; } var e2 = s.source === 'url' ? embed(u) : ''; return e2 ? { kind: 'embed', url: e2 } : { kind: 'video', url: u }; };
		var list = _.filter(settings.playlist || [], function (row) { return pick(row); }); var current = list.length > 1 ? list[0] : settings; if (list.length < 2) { list = []; }
		var media = pick(current); var ratio = ['16/9','4/3','1/1','auto'].indexOf(settings.ratio) >= 0 ? settings.ratio : '16/9';
		var stage = function (item, m) { return m.kind === 'embed' ? '<iframe class="digi-video-player__embed" src="' + _.escape(m.url) + '" title="' + _.escape(item.title || 'Video') + '" allowfullscreen></iframe>' : '<video controls playsinline preload="none" src="' + _.escape(m.url) + '" poster="' + _.escape(item.poster && item.poster.url || '') + '" aria-label="' + _.escape(item.title || 'Video') + '"></video>'; }; #>
		<# if (!media) { #><div class="digi-video-player" data-ratio="{{ ratio }}"><p>Selecciona un video de la biblioteca o escribe su URL.</p></div><# } else if (!list.length) { #><div class="digi-video-player" data-ratio="{{ ratio }}">{{{ stage(current, media) }}}</div><# } else { #><div class="digi-video-player digi-video-player--playlist" data-ratio="{{ ratio }}"><div class="digi-video-player__stage">{{{ stage(current, media) }}}</div><# if (settings.playlist_caption === 'yes') { #><div class="digi-video-player__now"><p class="digi-video-player__now-title">{{ current.title || 'Video' }}</p><p class="digi-video-player__now-description"<# if (!current.description) { #> hidden<# } #>>{{ current.description || '' }}</p></div><# } #><ul class="digi-video-player__playlist"><# _.each(list, function (row, i) { var m = pick(row); #><li><button type="button" class="digi-video-player__item" aria-pressed="{{ i === 0 ? 'true' : 'false' }}" data-kind="{{ m.kind }}" data-src="{{ m.url }}" data-poster="{{ row.poster && row.poster.url || '' }}" data-title="{{ row.title || 'Video' }}"><span class="digi-video-player__item-title">{{ row.title || 'Video' }}</span><# if (row.description) { #><span class="digi-video-player__item-description">{{ row.description }}</span><# } #></button></li><# }); #></ul></div><# } #><?php
	}
}
