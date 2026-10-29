<?php
/**
 * Carga diferida de los vídeos alojados que arrancan solos (`lazy_videos`, Fase 3 de docs/ELEMENTS_FRONT_OBJECTIVE.md;
 * modules/digitalisimo-front/class-lazy-videos.php), con dobles de WordPress y del constructor:
 * - los tres ganchos se registran y el script se registra con defer, sin jQuery, sólo si la clave está aplicada;
 * - `targets()`: fondo de vídeo con archivo en contenedor y sección (no YouTube ni Vimeo, no sin vídeo), widget Vídeo
 *   alojado con reproducción automática y sin imagen de portada (no con portada, no YouTube, no sin autoplay);
 * - `rewrite()`: fuera `autoplay`, `preload="none"` y la marca sólo en los dos `<video>` del núcleo; un `<video>` sin
 *   autoplay, de otra clase o el resto del HTML no cambian;
 * - el flujo real de render: con la clave aplicada, el elemento con vídeo pasa por el búfer, sale reescrito y el script
 *   se encola una sola vez; un elemento sin vídeo no entra al búfer; sin la clave aplicada, en el editor o en una
 *   petición que no es del front, el HTML sale byte a byte igual y no se encola nada.
 */
namespace Digitalisimo\Elements\Front {
	class Transition {
		public static $applied = array();
		public static function applied() { return self::$applied; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '9.9.9' );
	$GLOBALS['lv'] = array( 'hooks' => array(), 'registered' => array(), 'enqueued' => array(), 'admin' => false, 'ajax' => false, 'cron' => false );
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['lv']['hooks'][ $hook ][] = $callback; }
	function is_admin() { return $GLOBALS['lv']['admin']; }
	function wp_doing_ajax() { return $GLOBALS['lv']['ajax']; }
	function wp_doing_cron() { return $GLOBALS['lv']['cron']; }
	function wp_script_is( $handle, $status = 'enqueued' ) { return 'registered' === $status ? isset( $GLOBALS['lv']['registered'][ $handle ] ) : in_array( $handle, $GLOBALS['lv']['enqueued'], true ); }
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['lv']['registered'][ $handle ] = compact( 'src', 'deps', 'ver', 'args' ); return true; }
	function wp_enqueue_script( $handle ) { $GLOBALS['lv']['enqueued'][] = $handle; }
	function plugins_url( $path, $plugin ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . ltrim( $path, '/' ); }
	function is_customize_preview() { return false; }
	function is_preview() { return false; }

	function check_lv( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	class LV_Element {
		private $name; private $settings; private $id;
		public function __construct( $name, array $settings, $id = 'e1' ) { $this->name = $name; $this->settings = $settings; $this->id = $id; }
		public function get_name() { return $this->name; }
		public function get_id() { return $this->id; }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
	}

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-front/class-lazy-videos.php';
	use Digitalisimo\Elements\Front\Lazy_Videos;
	use Digitalisimo\Elements\Front\Transition;

	$script = file_get_contents( __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-front/assets/lazy-videos.js' );
	check_lv( is_string( $script ) && strlen( $script ) <= 1536 && false === strpos( $script, 'jQuery' ) && false !== strpos( $script, 'data-digi-lazy-video' ), 'El script existe, cabe en 1,5 KB, no usa jQuery y busca la marca.' );

	// 1. Ganchos y registro del script.
	Lazy_Videos::init();
	Lazy_Videos::init();
	check_lv( 1 === count( $GLOBALS['lv']['hooks']['elementor/frontend/before_render'] ?? array() ) && 1 === count( $GLOBALS['lv']['hooks']['elementor/frontend/after_render'] ?? array() ) && 1 === count( $GLOBALS['lv']['hooks']['elementor/frontend/after_register_scripts'] ?? array() ), 'init() registra los tres ganchos una sola vez.' );
	Transition::$applied = array( 'lazy_videos' => false );
	Lazy_Videos::register();
	check_lv( array() === $GLOBALS['lv']['registered'], 'Sin la clave aplicada no se registra el script.' );
	Transition::$applied = array( 'lazy_videos' => true );
	Lazy_Videos::register();
	$reg = $GLOBALS['lv']['registered'][ Lazy_Videos::HANDLE ] ?? null;
	check_lv( is_array( $reg ) && array() === $reg['deps'] && 'defer' === ( $reg['args']['strategy'] ?? '' ) && ! empty( $reg['args']['in_footer'] ) && false !== strpos( $reg['src'], 'lazy-videos.js' ) && '9.9.9' === $reg['ver'], 'Con la clave aplicada, el script se registra con defer, en el pie, sin dependencias y con la versión del plugin.' );

	// 2. targets().
	$bg_hosted  = new LV_Element( 'container', array( 'background_background' => 'video', 'background_video_link' => 'https://sitio.test/wp-content/uploads/fondo.mp4' ) );
	$sec_hosted = new LV_Element( 'section', array( 'background_background' => 'video', 'background_video_link' => 'https://sitio.test/f.mp4' ) );
	$bg_youtube = new LV_Element( 'container', array( 'background_background' => 'video', 'background_video_link' => 'https://www.youtube.com/watch?v=abc' ) );
	$bg_vimeo   = new LV_Element( 'container', array( 'background_background' => 'video', 'background_video_link' => 'https://vimeo.com/123' ) );
	$bg_none    = new LV_Element( 'container', array( 'background_background' => 'classic', 'background_video_link' => 'https://sitio.test/f.mp4' ) );
	$bg_empty   = new LV_Element( 'container', array( 'background_background' => 'video', 'background_video_link' => '' ) );
	$w_hosted   = new LV_Element( 'video', array( 'video_type' => 'hosted', 'autoplay' => 'yes', 'show_image_overlay' => '' ) );
	$w_overlay  = new LV_Element( 'video', array( 'video_type' => 'hosted', 'autoplay' => 'yes', 'show_image_overlay' => 'yes' ) );
	$w_manual   = new LV_Element( 'video', array( 'video_type' => 'hosted', 'autoplay' => '', 'show_image_overlay' => '' ) );
	$w_youtube  = new LV_Element( 'video', array( 'video_type' => 'youtube', 'autoplay' => 'yes', 'show_image_overlay' => '' ) );
	$heading    = new LV_Element( 'heading', array( 'title' => 'Hola' ) );
	check_lv( Lazy_Videos::targets( $bg_hosted ) && Lazy_Videos::targets( $sec_hosted ) && Lazy_Videos::targets( $w_hosted ), 'targets(): fondo de vídeo con archivo (contenedor y sección) y widget Vídeo alojado con autoplay.' );
	check_lv( ! Lazy_Videos::targets( $bg_youtube ) && ! Lazy_Videos::targets( $bg_vimeo ) && ! Lazy_Videos::targets( $bg_none ) && ! Lazy_Videos::targets( $bg_empty ) && ! Lazy_Videos::targets( $w_overlay ) && ! Lazy_Videos::targets( $w_manual ) && ! Lazy_Videos::targets( $w_youtube ) && ! Lazy_Videos::targets( $heading ) && ! Lazy_Videos::targets( null ), 'targets(): nada para YouTube, Vimeo, sin vídeo, con portada, sin autoplay ni otros widgets.' );

	// 3. rewrite().
	$bg_html = '<div class="elementor-element elementor-element-abc e-con"><div class="elementor-background-video-container elementor-hidden-mobile"><video class="elementor-background-video-hosted" role="presentation" autoplay muted playsinline loop></video></div><p>Texto</p></div>';
	$out     = Lazy_Videos::rewrite( $bg_html );
	check_lv( false !== strpos( $out, '<video class="elementor-background-video-hosted" role="presentation" muted playsinline loop preload="none" data-digi-lazy-video></video>' ) && false === strpos( $out, 'autoplay' ) && str_replace( array( ' autoplay', ' preload="none" data-digi-lazy-video' ), '', $out ) === str_replace( ' autoplay', '', $bg_html ), 'rewrite(): el vídeo de fondo pierde autoplay y gana preload="none" y la marca; lo demás no cambia.' );
	$once = Lazy_Videos::rewrite( '<video class="elementor-background-video-hosted" role="presentation" autoplay muted playsinline></video>' );
	check_lv( '<video class="elementor-background-video-hosted" role="presentation" muted playsinline preload="none" data-digi-lazy-video></video>' === $once, 'rewrite(): sin loop (reproducir una vez), igual: ' . $once );
	$widget = Lazy_Videos::rewrite( '<div class="elementor-video-container"><video class="elementor-video" src="https://sitio.test/v.mp4" autoplay="" loop="" preload="metadata" muted="muted" playsinline=""></video></div>' );
	check_lv( '<div class="elementor-video-container"><video class="elementor-video" src="https://sitio.test/v.mp4" loop="" muted="muted" playsinline="" preload="none" data-digi-lazy-video></video></div>' === $widget, 'rewrite(): el widget Vídeo pierde autoplay="" y su preload pasa a none: ' . $widget );
	$untouched = array(
		'<video class="elementor-video" src="https://sitio.test/v.mp4" controls="" preload="metadata"></video>',
		'<video class="digi-video-player__media" autoplay muted></video>',
		'<video controls playsinline preload="none"><source src="x.mp4"></video>',
		'<p>autoplay</p><div class="elementor-background-video-container"></div>',
	);
	foreach ( $untouched as $html ) {
		check_lv( $html === Lazy_Videos::rewrite( $html ), 'rewrite(): no cambia lo que no es un vídeo del núcleo que arranca solo: ' . $html );
	}

	// 4. Flujo de render con la clave aplicada: búfer sólo para el elemento con vídeo, reescritura y un solo encolado.
	Lazy_Videos::reset();
	Transition::$applied = array( 'lazy_videos' => true );
	$GLOBALS['lv']['enqueued'] = array();
	$render = function ( $element, $html ) {
		$level = ob_get_level();
		ob_start();
		Lazy_Videos::before_render( $element );
		echo $html;
		Lazy_Videos::after_render( $element );
		$out = ob_get_clean();
		check_lv( ob_get_level() === $level, 'Los búferes quedan como estaban.' );
		return $out;
	};
	$plain = $render( $heading, '<h2>Hola</h2>' );
	check_lv( '<h2>Hola</h2>' === $plain && array() === $GLOBALS['lv']['enqueued'], 'Un elemento sin vídeo sale igual y no encola nada.' );
	$out = $render( $bg_hosted, $bg_html );
	check_lv( false !== strpos( $out, 'preload="none" data-digi-lazy-video' ) && false === strpos( $out, 'autoplay' ) && array( Lazy_Videos::HANDLE ) === $GLOBALS['lv']['enqueued'], 'El fondo de vídeo sale reescrito y encola el script.' );
	$out2 = $render( $w_hosted, '<video class="elementor-video" src="v.mp4" autoplay="" muted="muted"></video>' );
	check_lv( '<video class="elementor-video" src="v.mp4" muted="muted" preload="none" data-digi-lazy-video></video>' === $out2 && array( Lazy_Videos::HANDLE ) === $GLOBALS['lv']['enqueued'], 'El segundo vídeo también sale reescrito y el script se encola una sola vez: ' . $out2 );
	// Anidado: un contenedor con vídeo que contiene otro contenedor con vídeo (búferes de fuera hacia dentro).
	$inner = new LV_Element( 'container', array( 'background_background' => 'video', 'background_video_link' => 'https://sitio.test/i.mp4' ), 'inner' );
	ob_start();
	Lazy_Videos::before_render( $bg_hosted );
	echo '<div id="outer"><video class="elementor-background-video-hosted" role="presentation" autoplay muted playsinline loop></video>';
	Lazy_Videos::before_render( $inner );
	echo '<div id="inner"><video class="elementor-background-video-hosted" role="presentation" autoplay muted playsinline></video></div>';
	Lazy_Videos::after_render( $inner );
	echo '</div>';
	Lazy_Videos::after_render( $bg_hosted );
	$nested = ob_get_clean();
	check_lv( 2 === substr_count( $nested, 'data-digi-lazy-video' ) && 0 === substr_count( $nested, 'autoplay' ) && '<div id="outer">' === substr( $nested, 0, 16 ) && '</div></div>' === substr( $nested, -12 ), 'Anidados: los dos vídeos se reescriben y el HTML conserva su orden: ' . $nested );
	// Un after_render de un elemento que no abrió búfer no toca nada.
	$level = ob_get_level();
	ob_start();
	Lazy_Videos::after_render( $heading );
	check_lv( '' === ob_get_clean() && ob_get_level() === $level, 'after_render() de un elemento sin búfer propio no hace nada.' );

	// 5. Sin la clave, en el editor o fuera del front: byte a byte igual y sin encolar.
	foreach ( array(
		'sin la clave aplicada' => function () { Transition::$applied = array( 'lazy_videos' => false ); },
		'en el editor (elementor-preview)' => function () { Transition::$applied = array( 'lazy_videos' => true ); $_GET['elementor-preview'] = '1'; },
		'en la administración' => function () { Transition::$applied = array( 'lazy_videos' => true ); unset( $_GET['elementor-preview'] ); $GLOBALS['lv']['admin'] = true; },
		'en AJAX' => function () { $GLOBALS['lv']['admin'] = false; $GLOBALS['lv']['ajax'] = true; },
	) as $label => $setup ) {
		Lazy_Videos::reset();
		$GLOBALS['lv']['enqueued'] = array();
		$setup();
		$out = $render( $bg_hosted, $bg_html );
		check_lv( $bg_html === $out && array() === $GLOBALS['lv']['enqueued'] && ! Lazy_Videos::active(), "Inactivo {$label}: el HTML sale igual y no se encola nada." );
	}
	$GLOBALS['lv']['ajax'] = false;
	Lazy_Videos::reset();
	Transition::$applied = array( 'lazy_videos' => true );
	check_lv( Lazy_Videos::active(), 'Vuelve a estar activo en el front con la clave aplicada.' );

	echo "DIGITALÍSIMO Elements: carga diferida de los vídeos alojados que arrancan solos (lazy_videos) validada.\n";
}
