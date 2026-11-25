<?php
/**
 * Vídeos de fondo (`modules/digitalisimo-front/class-background-video.php`, `Background_Video`). Auditoría de
 * digitalisimo.lat (2026-11-24): la cabecera bajaba un MP4 de 8,1 MB también en el celular. Sin WordPress: dobles de
 * opciones (sitio y red), ganchos y elementos del constructor.
 * - Ajustes: predeterminados (`element`, 2 MB), validación (modo de la lista, MB entre 0,1 y 1024, coma decimal), un
 *   valor inválido conserva el guardado, resolución sitio → red → predeterminado con «Heredar de la red» por campo.
 * - Front: con `element` ningún elemento cambia; con `never` el «Reproducir en móvil» de un fondo de vídeo alojado se
 *   apaga (y se olvidan los ajustes ya calculados) y con `always` se enciende; YouTube, otros elementos, el editor y las
 *   peticiones que no son del front no cambian.
 * - Editor: el control informativo (no guarda nada, sólo con «Vídeo») y la configuración del script con el umbral.
 * - Pantalla: la sección «Vídeos de fondo» en el sitio y en la red y su guardado en `Settings_Page`.
 * Falla con el código anterior (no existe la clase).
 */
namespace Elementor {
	class Controls_Manager {
		const RAW_HTML = 'raw_html';
	}
	class Embed {
		public static function is_embed_video( $url ) {
			return (bool) preg_match( '#youtube\.com|youtu\.be|vimeo\.com#', (string) $url );
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '9.9.9' );
	error_reporting( E_ALL );
	set_error_handler(
		static function ( $severity, $message, $file, $line ) {
			fwrite( STDERR, "PHP $severity: $message en $file:$line\n" );
			$GLOBALS['bv_php_errors'] = ( $GLOBALS['bv_php_errors'] ?? 0 ) + 1;
			return true;
		}
	);
	$GLOBALS['bv_failures'] = 0;
	$GLOBALS['bv_checks']   = 0;
	function check( $ok, string $message ) {
		++$GLOBALS['bv_checks'];
		if ( $ok ) {
			echo "ok · $message\n";
		} else {
			++$GLOBALS['bv_failures'];
			echo "FALLA · $message\n";
		}
	}

	$GLOBALS['bv'] = array( 'options' => array(), 'site_options' => array(), 'multisite' => false, 'admin' => false, 'hooks' => array(), 'scripts' => array(), 'inline' => array() );
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['bv']['hooks'][ $hook ][] = array( $callback, $priority );
		return true;
	}
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		return add_action( $hook, $callback, $priority, $args );
	}
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['bv']['options'] ) ? $GLOBALS['bv']['options'][ $name ] : $default;
	}
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['bv']['options'][ $name ] = $value;
		return true;
	}
	function get_site_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['bv']['site_options'] ) ? $GLOBALS['bv']['site_options'][ $name ] : $default;
	}
	function update_site_option( $name, $value ) {
		$GLOBALS['bv']['site_options'][ $name ] = $value;
		return true;
	}
	function is_multisite() {
		return $GLOBALS['bv']['multisite'];
	}
	function is_admin() {
		return $GLOBALS['bv']['admin'];
	}
	function wp_doing_ajax() {
		return false;
	}
	function wp_doing_cron() {
		return false;
	}
	function is_customize_preview() {
		return false;
	}
	function is_preview() {
		return false;
	}
	function esc_html( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
	function esc_attr( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
	function plugins_url( $path, $plugin ) {
		return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . ltrim( $path, '/' );
	}
	function home_url() {
		return 'https://sitio.test';
	}
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
	function wp_json_encode( $v ) {
		return json_encode( $v );
	}
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) {
		$GLOBALS['bv']['scripts'][ $handle ] = $src;
	}
	function wp_add_inline_script( $handle, $code, $position = 'after' ) {
		$GLOBALS['bv']['inline'][ $handle ] = $code;
	}

	/** Un contenedor, sección o widget con los métodos de `Controls_Stack` que importan. */
	class BV_Element {
		public $name;
		public $settings;
		public $resets   = 0;
		public $controls = array();
		public function __construct( string $name, array $settings ) {
			$this->name     = $name;
			$this->settings = $settings;
		}
		public function get_name() {
			return $this->name;
		}
		/** @var array Lo que resuelven las etiquetas dinámicas (`__dynamic__`), clave => valor. */
		public $dynamic = array();
		public function get_settings( $key = null ) {
			return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null );
		}
		public function get_settings_for_display( $key = null ) {
			$all = array_merge( $this->settings, $this->dynamic );
			return null === $key ? $all : ( $all[ $key ] ?? null );
		}
		public function set_settings( $key, $value = null ) {
			$this->settings[ $key ] = $value;
		}
		public function reset_render_state() {
			++$this->resets;
		}
		public function add_control( $id, $args ) {
			$this->controls[ $id ] = $args;
		}
	}
	function bv_video( string $mobile = 'yes', string $link = 'https://sitio.test/wp-content/uploads/fondo.mp4', string $name = 'container' ): BV_Element {
		return new BV_Element( $name, array( 'background_background' => 'video', 'background_video_link' => $link, 'background_play_on_mobile' => $mobile ) );
	}
	function bv_reset( array $site = array(), array $network = array(), bool $multisite = false ): void {
		$GLOBALS['bv']['options']      = $site ? array( 'digitalisimo_elements_background_video' => $site ) : array();
		$GLOBALS['bv']['site_options'] = $network ? array( 'digitalisimo_elements_background_video_defaults' => $network ) : array();
		$GLOBALS['bv']['multisite']    = $multisite;
		if ( class_exists( '\Digitalisimo\Elements\Front\Background_Video', false ) ) {
			\Digitalisimo\Elements\Front\Background_Video::reset();
		}
	}

	$root  = dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-front';
	$file  = $root . '/class-background-video.php';
	$fixed = is_file( $file );
	require $root . '/class-lazy-videos.php';
	if ( $fixed ) {
		require $file;
	}
	check( $fixed, 'existe Background_Video (modules/digitalisimo-front/class-background-video.php)' );
	$cls    = '\Digitalisimo\Elements\Front\Background_Video';
	$module = (string) file_get_contents( $root . '/class-module.php' );
	check( (bool) preg_match( "/const BOOT = array\([^)]*'Background_Video'/", $module ), 'el módulo del front la arranca (BOOT)' );

	if ( $fixed ) {
		// Ajustes.
		bv_reset();
		check( 'element' === $cls::get( 'mobile' ) && 2.0 === $cls::get( 'max_mb' ), 'predeterminados: lo decide cada elemento y 2 MB' );
		check( 'never' === $cls::clean( 'mobile', 'never' ) && null === $cls::clean( 'mobile', 'nunca' ) && null === $cls::clean( 'mobile', array() ), 'el modo sólo acepta los de la lista' );
		check( 1.5 === $cls::clean( 'max_mb', '1,5' ) && 8.0 === $cls::clean( 'max_mb', 8 ) && null === $cls::clean( 'max_mb', '0' ) && null === $cls::clean( 'max_mb', '2000' ) && null === $cls::clean( 'max_mb', 'mucho' ), 'los MB: coma decimal, entre 0,1 y 1024' );
		$cls::save_site( array( 'mobile' => 'never', 'max_mb' => '3' ) );
		check( 'never' === $cls::get( 'mobile' ) && 3.0 === $cls::get( 'max_mb' ), 'sitio único: lo guardado rige' );
		$cls::save_site( array( 'mobile' => 'otra', 'max_mb' => 'x' ) );
		check( 'never' === $cls::get( 'mobile' ) && 3.0 === $cls::get( 'max_mb' ), 'un envío inválido conserva lo guardado' );
		check( true === $cls::save_site( array( 'mobile' => 'always', 'max_mb' => '3' ) ) && false === $cls::save_site( array( 'mobile' => 'always', 'max_mb' => '4' ) ), 'save_site dice si cambió la política del celular (para vaciar cachés)' );
		// Red.
		bv_reset( array( 'mobile' => 'always', 'max_mb' => 5, 'own' => array() ), array( 'mobile' => 'never', 'max_mb' => 1 ), true );
		check( 'never' === $cls::get( 'mobile' ) && 1.0 === $cls::get( 'max_mb' ) && 'network' === $cls::origin( 'mobile' ), 'red: un sitio que hereda toma los valores de la red' );
		bv_reset( array( 'mobile' => 'always', 'max_mb' => 5, 'own' => array( 'mobile' ) ), array( 'mobile' => 'never', 'max_mb' => 1 ), true );
		check( 'always' === $cls::get( 'mobile' ) && 1.0 === $cls::get( 'max_mb' ) && 'site' === $cls::origin( 'mobile' ), 'red: «Heredar de la red» por campo' );
		bv_reset( array(), array(), true );
		check( 'element' === $cls::get( 'mobile' ) && 'default' === $cls::origin( 'mobile' ), 'red sin valores: el predeterminado' );
		$GLOBALS['bv']['multisite'] = true;
		$saved = $cls::sanitize( array( 'mobile' => 'never', 'max_mb' => '2', 'inherit' => array( 'mobile' => '0', 'max_mb' => '1' ) ) );
		check( array( 'mobile' ) === $saved['own'], 'envío del sitio en una red: own son los campos sin «Heredar de la red»' );
		$net = $cls::save_network( array( 'mobile' => 'always', 'max_mb' => 'x' ) );
		check( 'always' === $net['mobile'] && ! isset( $net['max_mb'] ) && 'always' === get_site_option( 'digitalisimo_elements_background_video_defaults' )['mobile'], 'envío de red: misma validación, lo inválido no se guarda' );

		// Front.
		$cls::init();
		$cls::init();
		check( 1 === count( $GLOBALS['bv']['hooks']['elementor/frontend/before_render'] ?? array() ) && PHP_INT_MIN === $GLOBALS['bv']['hooks']['elementor/frontend/before_render'][0][1], 'init() idempotente; before_render antes que nadie' );
		bv_reset();
		$el = bv_video( 'yes' );
		$cls::before_render( $el );
		check( 'yes' === $el->settings['background_play_on_mobile'] && 0 === $el->resets, 'element: el «Reproducir en móvil» del elemento se respeta' );
		bv_reset( array( 'mobile' => 'never' ) );
		$el = bv_video( 'yes' );
		$cls::before_render( $el );
		check( '' === $el->settings['background_play_on_mobile'] && 1 === $el->resets, 'never: se apaga aunque el elemento lo pida, y se recalculan sus ajustes' );
		$el = bv_video( '' );
		$cls::before_render( $el );
		check( '' === $el->settings['background_play_on_mobile'] && 0 === $el->resets, 'never: uno que ya no lo pide no se toca' );
		$el = bv_video( 'yes', '', 'section' );
		$cls::before_render( $el );
		check( 'yes' === $el->settings['background_play_on_mobile'], 'never: sin vídeo no se toca' );
		$el = bv_video( 'yes', 'https://www.youtube.com/watch?v=abc' );
		$cls::before_render( $el );
		check( 'yes' === $el->settings['background_play_on_mobile'], 'never: YouTube no se toca' );
		$el = bv_video( 'yes', 'https://sitio.test/fondo.mp4', 'video' );
		$cls::before_render( $el );
		check( 'yes' === $el->settings['background_play_on_mobile'], 'never: sólo contenedores y secciones' );
		$el = bv_video( 'yes', 'https://sitio.test/fondo.mp4', 'section' );
		$cls::before_render( $el );
		check( '' === $el->settings['background_play_on_mobile'], 'never: también en secciones' );
		$_GET['elementor-preview'] = '1';
		$el = bv_video( 'yes' );
		$cls::before_render( $el );
		unset( $_GET['elementor-preview'] );
		check( 'yes' === $el->settings['background_play_on_mobile'], 'never: en el editor y su vista previa no cambia' );
		$GLOBALS['bv']['admin'] = true;
		$el                     = bv_video( 'yes' );
		$cls::before_render( $el );
		$GLOBALS['bv']['admin'] = false;
		check( 'yes' === $el->settings['background_play_on_mobile'], 'never: fuera del front (administración) no cambia' );
		// El enlace del vídeo con una etiqueta dinámica (vacío en lo guardado): se resuelve antes de decidir.
		$el          = bv_video( 'yes', '' );
		$el->dynamic = array( 'background_video_link' => 'https://sitio.test/wp-content/uploads/dinamico.mp4' );
		$cls::before_render( $el );
		check( '' === $el->settings['background_play_on_mobile'], 'never: un enlace de vídeo con etiqueta dinámica se resuelve antes de aplicar la política' );
		$el          = bv_video( 'yes', 'https://sitio.test/fondo.mp4' );
		$el->dynamic = array( 'background_video_link' => 'https://www.youtube.com/watch?v=abc' );
		$cls::before_render( $el );
		check( 'yes' === $el->settings['background_play_on_mobile'], 'never: y si resuelve a YouTube no se toca' );
		bv_reset( array( 'mobile' => 'always' ) );
		$el = bv_video( '' );
		$cls::before_render( $el );
		check( 'yes' === $el->settings['background_play_on_mobile'] && 1 === $el->resets, 'always: se enciende aunque el elemento no lo pida' );

		// Editor.
		$el = new BV_Element( 'container', array() );
		$cls::add_notice_control( $el );
		$c = $el->controls['digitalisimo_background_video_weight'] ?? array();
		check( 'raw_html' === ( $c['type'] ?? '' ) && array( 'background_background' => 'video' ) === ( $c['condition'] ?? null ) && false !== strpos( $c['raw'] ?? '', 'digitalisimo-bg-video-weight' ), 'el control del aviso es informativo (RAW_HTML) y sólo con «Vídeo»' );
		check( isset( $GLOBALS['bv']['hooks']['elementor/element/container/section_background/before_section_end'], $GLOBALS['bv']['hooks']['elementor/element/section/section_background/before_section_end'] ), 'el control se agrega a contenedores y secciones' );
		bv_reset( array( 'max_mb' => 2.5 ) );
		$cls::enqueue_editor();
		$config = $cls::editor_config();
		check( 2621440 === $config['maxBytes'] && 'element' === $config['mobile'], 'el script del editor recibe el umbral en bytes y la política' );
		check( false !== strpos( $GLOBALS['bv']['scripts'][ $cls::HANDLE ] ?? '', 'assets/background-video-weight.js' ) && false !== strpos( $GLOBALS['bv']['inline'][ $cls::HANDLE ] ?? '', '"maxBytes":2621440' ), 'el script y su configuración se encolan en el editor' );

		// Pantalla.
		bv_reset();
		ob_start();
		$cls::render_fields( false );
		$site_html = ob_get_clean();
		check( false !== strpos( $site_html, 'name="digitalisimo_elements_background_video[mobile]"' ) && false !== strpos( $site_html, 'name="digitalisimo_elements_background_video[max_mb]"' ) && false !== strpos( $site_html, 'value="element" selected="selected"' ), 'sitio: el desplegable y los MB, con el predeterminado elegido' );
		check( false === strpos( $site_html, 'Heredar de la red' ), 'sitio único: sin «Heredar de la red»' );
		bv_reset( array(), array(), true );
		ob_start();
		$cls::render_fields( false );
		$multi_html = ob_get_clean();
		ob_start();
		$cls::render_fields( true );
		$net_html = ob_get_clean();
		check( 2 === substr_count( $multi_html, 'Heredar de la red' ), 'sitio de una red: «Heredar de la red» en cada campo' );
		check( false !== strpos( $net_html, 'name="digitalisimo_elements_background_video_defaults[mobile]"' ) && false === strpos( $net_html, 'Heredar de la red' ), 'red: sus campos, sin «Heredar de la red»' );
	}
	$page = (string) file_get_contents( $root . '/class-settings-page.php' );
	check( 1 === substr_count( $page, 'Background_Video::render_fields( false );' ) && 1 === substr_count( $page, 'Background_Video::render_fields( true );' ), 'la pantalla «Rendimiento» pinta la sección en el sitio y en la red' );
	check( false !== strpos( $page, 'Background_Video::save_site(' ) && false !== strpos( $page, 'Background_Video::save_network(' ) && false !== strpos( $page, 'self::clear_generated();' ), 'y la guarda en las dos superficies, vaciando lo generado si cambia la política' );

	echo "\n{$GLOBALS['bv_checks']} comprobaciones, {$GLOBALS['bv_failures']} fallas" . ( empty( $GLOBALS['bv_php_errors'] ) ? '' : ", {$GLOBALS['bv_php_errors']} avisos de PHP" ) . "\n";
	exit( $GLOBALS['bv_failures'] || ! empty( $GLOBALS['bv_php_errors'] ) ? 1 : 0 );
}
