<?php
/**
 * Fachadas (`facades`, Fase 3 de docs/ELEMENTS_FRONT_OBJECTIVE.md; modules/digitalisimo-front/class-facades.php y
 * class-facade-images.php), con dobles de WordPress y del constructor:
 * - la clave nueva cumple la paridad sitio/red y entra en la huella sólo cuando está activa (`v2:` + siete bits sin
 *   ella: ningún sitio que no la use transita al actualizar);
 * - `markup()` y `autoplay()`: botón nativo con nombre accesible, atributos del iframe en `data-*`, `<noscript>` con el
 *   iframe original, sólo HTTPS, `autoplay=1` (y `autoPlay=true` en Wistia) sin duplicar;
 * - `Facade_Images`: nombres sólo de datos validados (nada de rutas), teselas del mapa, agenda única, marca de fallo,
 *   descarga de una portada (recorte a 16:9) y composición real de un mapa con GD, en una carpeta de `uploads`;
 * - los cinco widgets con el interruptor aplicado: fachada sin preconexión ni iframe, dependencias sólo donde se pinta,
 *   y la caché de elementos no guarda una fachada con su imagen pendiente;
 * - el script y la hoja: tamaño, sin jQuery, sólo HTTPS y foco al iframe.
 */
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $type_instance = false;
		public function get_id() { return 'fc1'; }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return $this->get_settings_for_display( $key ); }
		public function is_type_instance() { return $this->type_instance; }
		public function add_render_attribute( $key, $attr, $value = null ) {}
		public function add_link_attributes( $key, $link ) {}
		public function get_render_attribute_string( $key ) { return ''; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '9.9.9' );
	$uploads = sys_get_temp_dir() . '/digi-facades-' . getmypid();
	@mkdir( $uploads, 0777, true );
	$GLOBALS['fc'] = array( 'options' => array(), 'actions' => array(), 'filters' => array(), 'events' => array(), 'http' => array(), 'styles' => array(), 'scripts' => array(), 'uploads' => $uploads, 'webp' => true, 'editor' => array() );

	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['fc']['actions'][ $hook ][] = $callback; }
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['fc']['filters'][ $hook ][] = $callback; }
	function get_option( $name, $default = false ) { return $GLOBALS['fc']['options'][ $name ] ?? $default; }
	function is_multisite() { return false; }
	function is_admin() { return false; }
	function wp_doing_ajax() { return false; }
	function wp_doing_cron() { return false; }
	function is_customize_preview() { return false; }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v, $protocols = null ) { $v = (string) $v; if ( is_array( $protocols ) && ! in_array( strtolower( (string) parse_url( $v, PHP_URL_SCHEME ) ), $protocols, true ) ) { return ''; } return str_replace( '&amp;', '&#038;', htmlspecialchars( $v, ENT_QUOTES, 'UTF-8', false ) ); }
	function esc_url_raw( $v, $protocols = null ) { $v = (string) $v; return is_array( $protocols ) && ! in_array( strtolower( (string) parse_url( $v, PHP_URL_SCHEME ) ), $protocols, true ) ? '' : $v; }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function wp_kses_post( $v ) { return (string) $v; }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function apply_filters( $tag, $value ) { return $value; }
	function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $v ); }
	function wp_get_attachment_url( $id ) { return false; }
	function home_url( $path = '' ) { return 'https://sitio.test' . $path; }
	function plugins_url( $path, $plugin ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . ltrim( $path, '/' ); }
	function wp_style_is( $h, $s = 'enqueued' ) { return isset( $GLOBALS['fc']['styles'][ $h ] ); }
	function wp_script_is( $h, $s = 'enqueued' ) { return isset( $GLOBALS['fc']['scripts'][ $h ] ); }
	function wp_register_style( $h, $src, $deps = array(), $ver = false ) { $GLOBALS['fc']['styles'][ $h ] = $src; return true; }
	function wp_register_script( $h, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['fc']['scripts'][ $h ] = compact( 'src', 'deps', 'args' ); return true; }
	function wp_upload_dir( $time = null, $create = true ) { return array( 'basedir' => $GLOBALS['fc']['uploads'], 'baseurl' => 'https://sitio.test/wp-content/uploads', 'error' => false ); }
	function wp_get_upload_dir() { return wp_upload_dir(); }
	function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
	function wp_next_scheduled( $hook, $args = array() ) { foreach ( $GLOBALS['fc']['events'] as $e ) { if ( $e[0] === $hook && $e[1] === $args ) { return 1; } } return false; }
	function wp_schedule_single_event( $time, $hook, $args = array() ) { $GLOBALS['fc']['events'][] = array( $hook, $args ); return true; }
	function is_wp_error( $v ) { return $v instanceof WP_Error; }
	class WP_Error {}
	function wp_safe_remote_get( $url, $args = array() ) {
		$GLOBALS['fc']['http'][] = array( $url, $args );
		foreach ( $GLOBALS['fc']['responses'] ?? array() as $pattern => $body ) {
			if ( preg_match( $pattern, $url ) ) { return array( 'code' => 200, 'body' => $body ); }
		}
		return array( 'code' => 404, 'body' => '' );
	}
	function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
	function wp_remote_retrieve_body( $r ) { return $r['body']; }
	function wp_image_editor_supports( $args ) { return $GLOBALS['fc']['webp']; }
	function wp_get_image_editor( $file ) { $image = @imagecreatefromstring( (string) file_get_contents( $file ) ); return $image ? new FC_Editor( $image ) : new WP_Error(); }
	function set_url_scheme( $url, $scheme = null ) { return preg_replace( '#^\w+://#', ( $scheme ?: 'https' ) . '://', (string) $url ); }
	function attachment_url_to_postid( $url ) { $GLOBALS['fc']['lookups'][] = $url; return str_ends_with( $url, '/manual.pdf' ) ? 41 : 0; }
	function get_post_mime_type( $id ) { return 41 === $id ? 'application/pdf' : 'image/png'; }
	function wp_get_attachment_image_url( $id, $size ) { return 41 === $id ? 'https://sitio.test/wp-content/uploads/manual-pdf-' . $size . '.jpg' : false; }

	/** Editor de imágenes mínimo con GD (el de WordPress no se carga aquí); anota el recorte pedido. */
	class FC_Editor {
		private $image;
		public function __construct( $image ) { $this->image = $image; }
		public function get_size() { return array( 'width' => imagesx( $this->image ), 'height' => imagesy( $this->image ) ); }
		public function set_quality( $q ) {}
		public function crop( $x, $y, $w, $h, $dw, $dh ) {
			$GLOBALS['fc']['editor'][] = array( $x, $y, $w, $h, $dw, $dh );
			$out = imagecreatetruecolor( $dw, $dh );
			imagecopyresampled( $out, $this->image, 0, 0, $x, $y, $dw, $dh, $w, $h );
			$this->image = $out;
			return true;
		}
		public function save( $file, $mime ) { 'image/webp' === $mime ? imagewebp( $this->image, $file ) : imagejpeg( $this->image, $file ); return array( 'path' => $file ); }
	}

	function check_fc( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	function fc_png( $w, $h ) { $i = imagecreatetruecolor( $w, $h ); imagefill( $i, 0, 0, imagecolorallocate( $i, 30, 120, 200 ) ); ob_start(); imagepng( $i ); return ob_get_clean(); }
	function fc_render( $widget ) { ob_start(); ( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); return ob_get_clean(); }
	function fc_rmdir( $dir ) { foreach ( glob( $dir . '/*' ) ?: array() as $f ) { is_dir( $f ) ? fc_rmdir( $f ) : unlink( $f ); } @rmdir( $dir ); }

	$front = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-front/';
	foreach ( array( 'settings', 'transition', 'lazy-videos', 'facade-images', 'facades' ) as $file ) {
		require $front . 'class-' . $file . '.php';
	}
	$widgets = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/';
	foreach ( array( 'video-player', 'device-slider', 'offcanvas', 'map', 'document-viewer' ) as $file ) {
		require $widgets . 'class-' . $file . '.php';
	}
	use Digitalisimo\Elements\Front\Settings;
	use Digitalisimo\Elements\Front\Transition;
	use Digitalisimo\Elements\Front\Facades;
	use Digitalisimo\Elements\Front\Facade_Images;

	// 1. Ajustes y huella.
	check_fc( 5 === array_search( 'facades', Settings::KEYS, true ) && false === Settings::defaults()['facades'], 'facades es la sexta clave de KEYS (tras lazy_videos) y está apagada por defecto.' );
	check_fc( str_contains( Settings::help( 'facades' ), 'OpenStreetMap' ) && str_contains( Settings::help( 'facades' ), 'una sola vez al servidor' ) && '' !== Settings::label( 'facades' ), 'La ayuda dice qué widgets cambian y que las imágenes se descargan una vez al servidor.' );
	check_fc( true === Settings::sanitize( array( 'facades' => '1' ) )['facades'] && false === Settings::sanitize( array() )['facades'] && true === Settings::save_network( array( 'facades' => 'on' ) )['facades'], 'Sitio y red sanean facades como booleano (paridad).' );
	$neutral = array_fill_keys( Settings::KEYS, false );
	$neutral['pro_conditional'] = true;
	$neutral['font_swap'] = true;
	check_fc( 'v2:1100000' === Transition::fingerprint( $neutral ), 'Sin facades la huella es la de 4.3.0.170 (siete bits): ningún sitio transita al actualizar.' );
	check_fc( 'v2:1100000:facades' === Transition::fingerprint( array( 'facades' => true ) + $neutral ), 'Con facades activa la huella cambia: entrar o salir transita y vacía cachés.' );
	check_fc( array( 'facades' ) === Transition::LATE_KEYS && 2 === Transition::FORMAT, 'facades es una clave tardía y el formato sigue en 2.' );

	// 2. Interruptor: apagado no registra nada ni activa nada.
	Facades::init();
	Facades::init();
	check_fc( 1 === count( $GLOBALS['fc']['filters']['elementor/element/is_dynamic_content'] ?? array() ) && 1 === count( $GLOBALS['fc']['actions'][ Facade_Images::HOOK ] ?? array() ), 'init() engancha la caché de elementos y el evento de las imágenes una sola vez.' );
	Facades::register();
	check_fc( ! Facades::active() && array() === $GLOBALS['fc']['styles'] && array() === $GLOBALS['fc']['scripts'] && array() === Facades::depends( true ), 'Sin aplicar: ni handles ni dependencias.' );
	$GLOBALS['fc']['options'][ Transition::APPLIED ] = array( 'hash' => 'v2:1100000:facades', 'facades' => true );
	Facades::reset();
	Facades::register();
	check_fc( Facades::active() && str_ends_with( $GLOBALS['fc']['styles'][ Facades::HANDLE ], 'modules/digitalisimo-front/assets/facade.css' ) && 'defer' === $GLOBALS['fc']['scripts'][ Facades::HANDLE ]['args']['strategy'] && array() === $GLOBALS['fc']['scripts'][ Facades::HANDLE ]['deps'], 'Aplicado: hoja y script registrados, script diferido y sin dependencias.' );
	check_fc( array( Facades::HANDLE ) === Facades::depends( true ) && array() === Facades::depends( false ), 'depends() sólo devuelve el handle si el widget pinta una fachada.' );
	$_GET['elementor-preview'] = '1';
	Facades::reset();
	check_fc( ! Facades::active(), 'En la vista previa del editor no hay fachadas.' );
	unset( $_GET['elementor-preview'] );
	Facades::reset();

	// 3. Marcado.
	check_fc( 'https://www.youtube-nocookie.com/embed/abc?rel=0&autoplay=1' === Facades::autoplay( 'https://www.youtube-nocookie.com/embed/abc?rel=0' ) && 'https://fast.wistia.net/embed/iframe/x1?autoPlay=true' === Facades::autoplay( 'https://fast.wistia.net/embed/iframe/x1' ) && 'https://y.test/e?autoplay=1&mute=1' === Facades::autoplay( 'https://y.test/e?autoplay=1&mute=1' ), 'autoplay() añade el parámetro de cada servicio una sola vez.' );
	check_fc( '' === Facades::markup( 'embed', array( 'src' => 'http://inseguro.test/x', 'title' => 'X' ), array( 'label' => 'Cargar' ) ) && '' === Facades::markup( 'embed', array( 'src' => 'javascript:alert(1)' ), array() ), 'Sólo HTTPS: otra dirección no pinta fachada.' );
	$html = Facades::markup( 'map', array( 'src' => 'https://mapa.test/embed?a=1&b=2', 'title' => 'Mapa: "Sede"', 'class' => 'digi-map__frame', 'referrer' => 'no-referrer' ), array( 'name' => 'Ver mapa interactivo: Sede', 'label' => 'Ver mapa interactivo', 'image' => 'https://sitio.test/m.webp', 'pin' => true, 'note' => '<a href="#">© OSM</a>' ) );
	check_fc( '<div class="digi-facade digi-facade--map digi-facade--image digi-map__frame" data-digi-facade><img class="digi-facade__image" src="https://sitio.test/m.webp" alt="" width="1280" height="720" decoding="async" loading="lazy"><span class="digi-facade__pin" aria-hidden="true"></span><button type="button" class="digi-facade__button" aria-label="Ver mapa interactivo: Sede" data-digi-facade-src="https://mapa.test/embed?a=1&#038;b=2" data-digi-facade-title="Mapa: &quot;Sede&quot;" data-digi-facade-class="digi-map__frame" data-digi-facade-referrer="no-referrer"><span class="digi-facade__label">Ver mapa interactivo</span></button><span class="digi-facade__note"><a href="#">© OSM</a></span><noscript><iframe class="digi-map__frame" src="https://mapa.test/embed?a=1&#038;b=2" title="Mapa: &quot;Sede&quot;" referrerpolicy="no-referrer"></iframe></noscript></div>' === $html, 'Fachada de mapa: imagen decorativa, marcador, botón con nombre accesible y data-*, atribución y el iframe original en <noscript>.' );
	$video = Facades::markup( 'video', array( 'src' => 'https://v.test/e/1?autoplay=1', 'plain' => 'https://v.test/e/1', 'title' => 'Clip', 'class' => 'c', 'allow' => 'autoplay', 'fullscreen' => true ), array( 'name' => 'Reproducir el vídeo: Clip' ) );
	check_fc( str_contains( $video, '<button type="button" class="digi-facade__button" aria-label="Reproducir el vídeo: Clip" data-digi-facade-src="https://v.test/e/1?autoplay=1"' ) && str_contains( $video, 'data-digi-facade-allow="autoplay" data-digi-facade-fullscreen><span class="digi-facade__play" aria-hidden="true"></span></button>' ) && str_contains( $video, '<noscript><iframe class="c" src="https://v.test/e/1" title="Clip" allow="autoplay" allowfullscreen></iframe></noscript>' ) && ! str_contains( $video, '<img' ) && ! str_contains( $video, 'digi-facade--image' ), 'Fachada de vídeo sin portada: icono de reproducir, autoplay sólo al pulsar y el iframe sin autoplay en <noscript>.' );
	check_fc( ! str_contains( $html . $video, 'rel="preconnect"' ) && 1 === substr_count( $html, '<iframe' ) && str_contains( $html, '<noscript><iframe' ), 'Ninguna preconexión y ningún iframe fuera de <noscript>.' );

	// 4. Imágenes: nombres, teselas, agenda y fallos.
	check_fc( 'youtube-dQw4w9WgXcQ' === Facade_Images::name( Facade_Images::video( 'youtube', 'dQw4w9WgXcQ' ) ) && array() === Facade_Images::video( 'youtube', '../../wp-config' ) && array() === Facade_Images::video( 'otro', 'abc' ) && array() === Facade_Images::video( 'vimeo', '' ), 'Nombre de portada sólo con servicio de la lista e ID [A-Za-z0-9_-].' );
	$map = Facade_Images::map( 19.4326, -99.1332, 15 );
	check_fc( preg_match( '/^map-[0-9a-f]{16}$/', Facade_Images::name( $map ) ) && array() === Facade_Images::map( 95.0, 0.0, 15 ) && array() === Facade_Images::map( 0.0, 0.0, 20 ) && Facade_Images::name( Facade_Images::map( 19.432604, -99.1332, 15 ) ) === Facade_Images::name( $map ), 'Nombre de mapa: hash de coordenadas con cinco decimales y acercamiento; fuera de rango, nada.' );
	$plan = Facade_Images::tiles( 19.4326, -99.1332, 15 );
	$covered = true;
	foreach ( array( array( 0, 0 ), array( 1023, 0 ), array( 0, 575 ), array( 1023, 575 ), array( 512, 288 ) ) as $p ) {
		$hit = false;
		foreach ( $plan['tiles'] as $t ) { $hit = $hit || ( $p[0] >= $t['left'] && $p[0] < $t['left'] + 256 && $p[1] >= $t['top'] && $p[1] < $t['top'] + 256 ); }
		$covered = $covered && $hit;
	}
	check_fc( 16 === $plan['z'] && count( $plan['tiles'] ) <= 20 && count( $plan['tiles'] ) >= 15 && $covered, 'El mapa pide como mucho 20 teselas del nivel zoom + 1 y cubren toda la imagen.' );
	$edge = Facade_Images::tiles( 0.0, 179.9999, 1 );
	check_fc( 2 === $edge['z'] && ! array_filter( $edge['tiles'], static function ( $t ) { return $t['x'] < 0 || $t['x'] > 3 || $t['y'] < 0 || $t['y'] > 3; } ), 'En el antimeridiano la longitud da la vuelta y ninguna tesela sale del mundo.' );
	$job = Facade_Images::video( 'youtube', 'dQw4w9WgXcQ' );
	check_fc( '' === Facade_Images::url( $job ) && '' === Facade_Images::url( $job ) && 1 === count( $GLOBALS['fc']['events'] ) && array( Facade_Images::HOOK, array( $job ) ) === $GLOBALS['fc']['events'][0] && Facade_Images::pending( $job ), 'Sin imagen: url() devuelve "" y agenda la descarga una sola vez; queda pendiente.' );
	check_fc( array() === $GLOBALS['fc']['http'], 'El front nunca descarga nada: sólo agenda.' );

	// Portada: maxresdefault falla, hqdefault (4:3 con bandas) llega y se recorta a 16:9.
	$GLOBALS['fc']['responses'] = array( '#/vi/dQw4w9WgXcQ/hqdefault\.jpg$#' => fc_png( 480, 360 ) );
	check_fc( true === Facade_Images::run( $job ), 'La descarga en cola guarda la portada.' );
	$dir = $uploads . '/digitalisimo-elements/facades/';
	check_fc( 'https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg' === $GLOBALS['fc']['http'][0][0] && 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg' === $GLOBALS['fc']['http'][1][0] && str_starts_with( $GLOBALS['fc']['http'][0][1]['user-agent'], 'DIGITALISIMO-Elements/9.9.9 (+https://sitio.test/)' ) && 3145728 === $GLOBALS['fc']['http'][0][1]['limit_response_size'], 'Pide maxresdefault y luego hqdefault, con el agente del sitio y tope de tamaño.' );
	check_fc( array( array( 0, 45, 480, 270, 480, 270 ) ) === $GLOBALS['fc']['editor'] && is_file( $dir . 'youtube-dQw4w9WgXcQ.webp' ) && array( 480, 270 ) === array_slice( getimagesize( $dir . 'youtube-dQw4w9WgXcQ.webp' ), 0, 2 ) && ! glob( $dir . '*.part*' ) && ! glob( $dir . '*.src.*' ), 'Recorta al centro a 16:9 (480 × 270), guarda WebP y no deja temporales.' );
	check_fc( 'https://sitio.test/wp-content/uploads/digitalisimo-elements/facades/youtube-dQw4w9WgXcQ.webp' === Facade_Images::url( $job ) && ! Facade_Images::pending( $job ), 'Guardada: url() la sirve desde uploads y deja de estar pendiente.' );

	// Vimeo: la miniatura de su oEmbed sólo se acepta de su dominio.
	$GLOBALS['fc']['http'] = array();
	$GLOBALS['fc']['responses'] = array( '#vimeo\.com/api/oembed#' => json_encode( array( 'thumbnail_url' => 'https://evil.test/a.jpg' ) ), '#evil#' => fc_png( 640, 360 ) );
	$vimeo = Facade_Images::video( 'vimeo', '76979871' );
	check_fc( false === Facade_Images::run( $vimeo ) && 1 === count( $GLOBALS['fc']['http'] ) && is_file( $dir . 'vimeo-76979871.failed' ), 'Una miniatura de otro dominio no se descarga y el trabajo queda marcado como fallido.' );
	$before = count( $GLOBALS['fc']['events'] );
	check_fc( '' === Facade_Images::url( $vimeo ) && $before === count( $GLOBALS['fc']['events'] ) && ! Facade_Images::pending( $vimeo ), 'Con un fallo reciente no se vuelve a agendar ni cuenta como pendiente.' );
	touch( $dir . 'vimeo-76979871.failed', time() - Facade_Images::RETRY - 5 );
	check_fc( Facade_Images::pending( $vimeo ), 'Pasado RETRY vuelve a estar pendiente.' );
	$GLOBALS['fc']['responses'] = array( '#vimeo\.com/api/oembed#' => json_encode( array( 'thumbnail_url' => 'https://i.vimeocdn.com/video/1-d_1280' ) ), '#i\.vimeocdn\.com/video/1-d_1280#' => '<?php echo 1;' );
	check_fc( false === Facade_Images::run( $vimeo ), 'Un cuerpo que no es imagen JPEG, PNG o WebP se rechaza.' );
	$GLOBALS['fc']['responses']['#i\.vimeocdn\.com/video/1-d_1280#'] = fc_png( 1920, 1080 );
	check_fc( true === Facade_Images::run( $vimeo ) && ! is_file( $dir . 'vimeo-76979871.failed' ) && array( 1280, 720 ) === array_slice( getimagesize( $dir . 'vimeo-76979871.webp' ), 0, 2 ), 'La de su dominio se guarda a 1 280 × 720 y quita la marca de fallo.' );

	// Mapa: composición real con GD (JPEG sin WebP en el editor no afecta: GD decide por imagewebp()).
	$GLOBALS['fc']['http'] = array();
	$GLOBALS['fc']['responses'] = array( '#^https://tile\.openstreetmap\.org/16/\d+/\d+\.png$#' => fc_png( 256, 256 ) );
	check_fc( true === Facade_Images::run( $map ) && count( $GLOBALS['fc']['http'] ) === count( $plan['tiles'] ) && array( 1024, 576 ) === array_slice( getimagesize( $dir . Facade_Images::name( $map ) . '.webp' ), 0, 2 ), 'El mapa se compone con sus teselas en 1 024 × 576.' );
	$GLOBALS['fc']['http'] = array();
	check_fc( true === Facade_Images::run( $map ) && array() === $GLOBALS['fc']['http'], 'Un trabajo ya hecho no vuelve a descargar nada.' );
	check_fc( false === Facade_Images::run( array( 'kind' => 'video', 'service' => 'youtube', 'id' => '../x' ) ) && false === Facade_Images::run( 'nada' ) && ! glob( $uploads . '/*.webp' ), 'Un trabajo no válido no escribe nada.' );

	// 5. Widgets con el interruptor aplicado.
	$GLOBALS['fc']['events'] = array();
	$player = new \Digitalisimo\Elements\Video_Player_Widget();
	$player->settings = array( 'source' => 'embed', 'embed_url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Tráiler' );
	$single = fc_render( $player );
	check_fc( str_starts_with( $single, '<div class="digi-video-player" data-ratio="16/9"><div class="digi-facade digi-facade--video digi-facade--image digi-video-player__embed" data-digi-facade><img class="digi-facade__image" src="https://sitio.test/wp-content/uploads/digitalisimo-elements/facades/youtube-dQw4w9WgXcQ.webp"' ) && str_contains( $single, 'aria-label="Reproducir el vídeo: Tráiler" data-digi-facade-src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&#038;autoplay=1"' ) && ! str_contains( $single, 'preconnect' ) && 1 === substr_count( $single, '<iframe' ), 'Reproductor: la portada del sitio y el botón, sin preconexión; el iframe sólo en <noscript>.' );
	check_fc( array( 'digitalisimo-video-player', Facades::HANDLE ) === $player->get_style_depends() && array( Facades::HANDLE ) === $player->get_script_depends(), 'Reproductor con un vídeo incrustado: hoja y script de la fachada, sin el de la lista.' );
	$player->settings = array( 'source' => 'url', 'video_url' => array( 'url' => 'https://cdn.test/a.mp4' ) );
	check_fc( str_contains( fc_render( $player ), '<video controls playsinline preload="none"' ) && array( 'digitalisimo-video-player' ) === $player->get_style_depends() && array() === $player->get_script_depends(), 'Un archivo de vídeo no cambia ni carga la fachada.' );
	$player->settings = array( 'playlist_trigger' => 'hover', 'playlist' => array(
		array( 'source' => 'embed', 'embed_url' => 'https://vimeo.com/76979871', 'title' => 'Vimeo' ),
		array( 'source' => 'embed', 'embed_url' => 'https://www.dailymotion.com/video/x7tgad0', 'title' => 'Dailymotion' ),
	) );
	$list = fc_render( $player );
	check_fc( str_contains( $list, 'data-digi-playlist="hover" data-digi-playlist-facade data-ratio="16/9"><div class="digi-video-player__stage"><div class="digi-facade digi-facade--video digi-facade--image digi-video-player__embed"' ) && str_contains( $list, 'vimeo-76979871.webp' ) && str_contains( $list, 'data-digi-facade-src="https://player.vimeo.com/video/76979871?dnt=1&#038;autoplay=1"' ) && str_contains( $list, 'data-kind="embed" data-src="https://www.dailymotion.com/embed/video/x7tgad0"' ) && ! str_contains( $list, 'preconnect' ), 'Lista: el escenario es la fachada del primero, la lista lo sabe y no hay preconexión.' );
	check_fc( array( 'digitalisimo-video-player', Facades::HANDLE ) === $player->get_script_depends(), 'Lista con fachada: el script de la lista y el de la fachada.' );

	$slider = new \Digitalisimo\Elements\Device_Slider_Widget();
	$slider->settings = array( 'navigation' => 'none', 'slides' => array( array( '_id' => 's1', 'background' => 'youtube', 'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Demo' ), array( '_id' => 's2', 'background' => 'color', 'color' => '#fff' ) ) );
	$device = fc_render( $slider );
	check_fc( str_contains( $device, '<div class="digi-facade digi-facade--video digi-facade--image digi-device-slider__video" data-digi-facade>' ) && str_contains( $device, 'data-digi-facade-src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&#038;mute=1&#038;loop=1&#038;playlist=dQw4w9WgXcQ&#038;playsinline=1&#038;rel=0"' ) && str_contains( $device, 'data-digi-facade-allow="autoplay; encrypted-media; picture-in-picture"' ) && ! str_contains( $device, 'preconnect' ), 'Slider de dispositivos: fachada con los mismos parámetros del vídeo de siempre.' );
	check_fc( array( Facades::HANDLE ) === $slider->get_script_depends() && in_array( Facades::HANDLE, $slider->get_style_depends(), true ) && array( Facade_Images::video( 'youtube', 'dQw4w9WgXcQ' ) ) === \Digitalisimo\Elements\Device_Slider_Widget::facade_jobs( $slider->settings ), 'Sus dependencias y su trabajo de portada.' );

	$panel_video = \Digitalisimo\Elements\Offcanvas_Widget::media_content( 'embed', 'https://youtu.be/dQw4w9WgXcQ', 'Vídeo' );
	$panel_map = \Digitalisimo\Elements\Offcanvas_Widget::media_content( 'embed', 'https://www.google.com/maps/embed?pb=1', 'Cómo llegar' );
	check_fc( str_contains( $panel_video, 'digi-facade--video digi-facade--image digi-offcanvas__media digi-offcanvas__media--embed' ) && str_contains( $panel_video, 'data-digi-facade-src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1"' ) && ! str_contains( $panel_video, 'preconnect' ), 'Panel lateral: un vídeo de YouTube es una fachada con su portada.' );
	check_fc( str_contains( $panel_map, '<div class="digi-facade digi-facade--embed digi-offcanvas__media digi-offcanvas__media--embed" data-digi-facade><button type="button" class="digi-facade__button" aria-label="Cargar el contenido de www.google.com: Cómo llegar"' ) && str_contains( $panel_map, '<span class="digi-facade__label">Cargar el contenido de www.google.com</span>' ) && str_contains( $panel_map, 'data-digi-facade-src="https://www.google.com/maps/embed?pb=1"' ), 'Panel lateral: otro contenido nombra su dominio en el botón y no arranca solo.' );
	$offcanvas = new \Digitalisimo\Elements\Offcanvas_Widget();
	$offcanvas->settings = array( 'source' => 'media', 'media_type' => 'embed', 'media_url' => array( 'url' => 'https://vimeo.com/76979871' ) );
	check_fc( array( 'digitalisimo-offcanvas', Facades::HANDLE ) === $offcanvas->get_script_depends() && array( Facade_Images::video( 'vimeo', '76979871' ) ) === \Digitalisimo\Elements\Offcanvas_Widget::facade_jobs( $offcanvas->settings ), 'Panel lateral con contenido insertado: script de la fachada y trabajo de la portada.' );
	$offcanvas->settings = array( 'source' => 'text' );
	check_fc( array( 'digitalisimo-offcanvas' ) === $offcanvas->get_script_depends() && array() === \Digitalisimo\Elements\Offcanvas_Widget::facade_jobs( $offcanvas->settings ), 'Sin contenido insertado, nada de la fachada.' );

	$mapw = new \Digitalisimo\Elements\Map_Widget();
	$mapw->settings = array( 'zoom' => array( 'size' => 15 ), 'show_list' => 'yes', 'markers' => array( array( 'title' => 'Oficina', 'lat' => '19.4326', 'lng' => '-99.1332', 'content' => 'Centro' ) ) );
	$m = fc_render( $mapw );
	check_fc( str_starts_with( $m, '<div class="digi-map"><div class="digi-facade digi-facade--map digi-facade--image digi-map__frame" data-digi-facade><img class="digi-facade__image" src="https://sitio.test/wp-content/uploads/digitalisimo-elements/facades/' . Facade_Images::name( $map ) . '.webp"' ) && str_contains( $m, '<span class="digi-facade__pin" aria-hidden="true"></span>' ) && str_contains( $m, 'aria-label="Ver mapa interactivo: Oficina"' ) && str_contains( $m, 'data-digi-facade-referrer="no-referrer"' ) && str_contains( $m, '<a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap</a>' ) && str_contains( $m, '<strong>Oficina</strong><span>Centro</span>' ) && ! str_contains( $m, 'preconnect' ), 'Mapa: imagen estática del sitio, marcador, atribución, «Ver mapa interactivo» y la lista de siempre.' );
	check_fc( array( Facades::HANDLE ) === $mapw->get_script_depends() && array( 'digitalisimo-map', Facades::HANDLE ) === $mapw->get_style_depends(), 'Mapa: hoja y script de la fachada.' );
	$mapw->settings['zoom'] = array( 'size' => 12 );
	$pending = fc_render( $mapw );
	check_fc( ! str_contains( $pending, '<img' ) && ! str_contains( $pending, 'digi-facade__pin' ) && ! str_contains( $pending, '© OpenStreetMap' ) && str_contains( $pending, 'Ver mapa interactivo' ) && 1 === count( $GLOBALS['fc']['events'] ), 'Mapa sin imagen aún: fachada neutra sin marcador ni atribución, y la imagen queda agendada.' );

	$doc = new \Digitalisimo\Elements\Document_Viewer_Widget();
	$doc->settings = array( 'file_source' => array( 'url' => 'https://sitio.test/wp-content/uploads/2026/11/manual.pdf' ), 'frame_title' => 'Manual' );
	$d = fc_render( $doc );
	check_fc( str_starts_with( $d, '<div class="digi-document-viewer"><div class="digi-facade digi-facade--document digi-facade--image digi-document-viewer__frame" data-digi-facade><img class="digi-facade__image" src="https://sitio.test/wp-content/uploads/manual-pdf-large.jpg"' ) && str_contains( $d, '<span class="digi-facade__label">Ver el documento aquí</span>' ) && str_contains( $d, '<a class="digi-document-viewer__link" href="https://sitio.test/wp-content/uploads/2026/11/manual.pdf"' ) && ! str_contains( $d, 'preconnect' ), 'Visor: la vista previa del PDF de la biblioteca, «Ver el documento aquí» y «Abrir documento».' );
	$lookups = count( $GLOBALS['fc']['lookups'] );
	$doc->settings = array( 'file_source' => array( 'url' => 'https://otro.example/folleto.pdf' ), 'viewer_type' => 'google_docs' );
	$g = fc_render( $doc );
	check_fc( $lookups === count( $GLOBALS['fc']['lookups'] ) && ! str_contains( $g, '<img' ) && str_contains( $g, 'data-digi-facade-src="https://docs.google.com/gview?embedded=1&#038;url=https%3A%2F%2Fotro.example%2Ffolleto.pdf"' ) && array( Facades::HANDLE ) === $doc->get_script_depends(), 'Un documento de otro sitio no consulta la biblioteca; el visor de Google sólo se carga al pulsar.' );

	// 6. Caché de elementos.
	$element = new class() { public $name = 'digitalisimo-map'; public function get_name() { return $this->name; } };
	$filter = $GLOBALS['fc']['filters']['elementor/element/is_dynamic_content'][0];
	check_fc( true === $filter( false, array( 'settings' => $mapw->settings ), $element ), 'Un mapa con la imagen pendiente no entra en la caché de elementos.' );
	$mapw->settings['zoom'] = array( 'size' => 15 );
	check_fc( false === $filter( false, array( 'settings' => $mapw->settings ), $element ), 'Con la imagen guardada, sí.' );
	$element->name = 'heading';
	check_fc( false === $filter( false, array( 'settings' => array() ), $element ) && true === $filter( true, array(), $element ), 'Otro widget no cambia, y lo que ya era dinámico sigue siéndolo.' );
	$resolved = new class() { public function get_name() { return 'digitalisimo-map'; } public function get_settings_for_display() { return array( 'zoom' => array( 'size' => 9 ), 'markers' => array( array( 'lat' => '19.4326', 'lng' => '-99.1332' ) ) ); } };
	check_fc( true === $filter( false, array( 'settings' => array() ), $resolved ), 'Usa los ajustes resueltos del elemento: un Mapa con su marcador por defecto (sin guardar) y la imagen pendiente tampoco entra en la caché.' );

	// 7. Script y hoja.
	$js = (string) file_get_contents( $front . 'assets/facade.js' );
	$css = (string) file_get_contents( $front . 'assets/facade.css' );
	check_fc( strlen( $js ) <= 1024 && ! str_contains( $js, 'jQuery' ) && str_contains( $js, "addEventListener('click'" ) && str_contains( $js, '/^https:\/\//i' ) && str_contains( $js, 'replaceWith(frame)' ) && str_contains( $js, 'frame.focus()' ), 'facade.js: ≤ 1 KB, sin jQuery, sólo HTTPS, sustituye la fachada y pasa el foco al iframe.' );
	check_fc( strlen( $css ) <= 1536 && ! str_contains( $css, '--e-global' ) && ! str_contains( $css, 'url(' ) && str_contains( $css, ':focus-visible' ) && str_contains( $css, '@media (scripting:none)' ), 'facade.css: ≤ 1,5 KB, sin variables del kit ni url(), con foco visible y la vista sin JavaScript.' );

	fc_rmdir( $uploads );
	echo "DIGITALÍSIMO Elements: fachadas de vídeos, mapas y documentos (facades) validadas.\n";
}
