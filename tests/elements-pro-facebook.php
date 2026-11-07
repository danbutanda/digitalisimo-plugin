<?php
/**
 * Widgets de Facebook propios (C9, frente R de docs/pro/c9.md): Botón, Comentarios, Publicación y Página de Facebook
 * (`digitalisimo-facebook-*`, modules/digitalisimo-services/), que sustituyen a los de PRO (modules/social/), y sus mapas
 * `modules/digitalisimo-convert/maps/facebook-*.php`:
 * - registro y recursos: hoja y script compartidos (`digitalisimo-facebook`), hoja ≤ 4 KB sin `url()` ni `--e-global-*`,
 *   script ≤ 6 KB sin jQuery que sólo pide el SDK dentro del manejador del clic; guarda de build-packages.sh;
 * - controles: se ejecutan los `register_controls()` de PRO (con `Facebook_SDK_Manager::add_app_id_control()` real) y los
 *   propios con los mismos dobles y se comparan uno a uno (nombre, orden, tipo, valor por defecto, opciones, condiciones,
 *   `dynamic`, unidades, rangos, límites, separador, `multiple`, tipo de aviso), con y sin App ID;
 * - marcado: fachada (botón con nombre en español, nota, `<template>` con el plugin) y, dentro, el mismo `div` del plugin
 *   que pinta PRO (mismos `data-*`; la clase propia en lugar de `elementor-facebook-widget`); ninguna URL de Facebook
 *   fuera de `data-href`, ni `<script>`, `<iframe>` ni `<img>`; mismas validaciones (avisos en español). El Botón y los
 *   Comentarios, que Meta retiró, sólo el `div` del plugin, como PRO, sin fachada ni script (corrección de fidelidad);
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, completos, `__dynamic__`, CSS personalizado con
 *   clases de PRO): ningún `pro.unmapped` y cada clave cae en un control propio.
 *
 * Uso: php-wasm-cli tests/elements-pro-facebook.php [--write]. Con `--write` regenera tests/fixtures/pro-facebook.html (la
 * usa tests/elements-pro-facebook-chrome.mjs). php-wasm sale con 0 aunque haya un error fatal: se juzga por la última línea.
 */
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $sections = array();
		public $attrs    = array();
		public $id       = 'fb01';
		public function get_id() { return $this->id; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings_for_display( $key = null ) { return $this->get_settings( $key ); }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; }
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_render_attribute( $element, $key = null, $value = null ) {
			foreach ( is_array( $key ) ? $key : array( $key => $value ) as $k => $v ) {
				$this->attrs[ $element ][ $k ] = $v;
			}
		}
		public function get_render_attribute_string( $element ) {
			$out = '';
			foreach ( $this->attrs[ $element ] ?? array() as $k => $v ) {
				$out .= ' ' . $k . '="' . esc_attr( is_array( $v ) ? implode( ' ', $v ) : $v ) . '"';
			}
			return ltrim( $out );
		}
		public function print_render_attribute_string( $element ) { echo $this->get_render_attribute_string( $element ); }
	}
	class Controls_Manager {
		const TEXT = 'text'; const SELECT = 'select'; const SELECT2 = 'select2'; const SWITCHER = 'switcher'; const NUMBER = 'number'; const SLIDER = 'slider'; const ALERT = 'alert';
		const TAB_CONTENT = 'content';
	}
	class Settings {
		const PAGE_ID = 'elementor';
		const TAB_INTEGRATIONS = 'integrations';
		public static function get_settings_tab_url( $tab ) { return 'https://sitio.test/wp-admin/admin.php?page=elementor-settings#tab-' . $tab; }
	}
	class Plugin {
		public static $instance;
		public $editor;
	}
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
	class Module_Base {}
}
namespace ElementorPro\Core {
	class Utils {}
}
namespace ElementorPro {
	class Plugin {
		public static function elementor() { return \Elementor\Plugin::$instance; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['fb_edit']    = false;
	$GLOBALS['fb_options'] = array();
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return $v; }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return preg_match( '~^(https?://|/|#)~', (string) $v ) ? str_replace( '&', '&#038;', (string) $v ) : ''; }
	function get_option( $name, $default = false ) { return $GLOBALS['fb_options'][ $name ] ?? $default; }
	function get_locale() { return 'es_MX'; }
	function get_the_ID() { return 42; }
	function get_permalink( $id ) { return 'https://sitio.test/entrada-' . $id . '/'; }
	function home_url() { return 'https://sitio.test/'; }
	function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value; }
	function add_action() {}
	function add_filter() {}
	function check_fb( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'FALLA: ' . $message ); } }

	\Elementor\Plugin::$instance         = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->editor = new class() { public function is_edit_mode() { return $GLOBALS['fb_edit']; } };

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/social/module.php';
	require $root . 'modules/social/classes/facebook-sdk-manager.php';
	foreach ( array( 'button', 'comments', 'embed', 'page' ) as $kind ) {
		require $root . 'modules/social/widgets/facebook-' . $kind . '.php';
	}
	require $root . 'modules/digitalisimo-services/class-module.php';
	\Digitalisimo\Elements\Services\Module::init();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Services\Module as Services;
	use Digitalisimo\Elements\Convert\Converter;

	$checks = 0;
	$check  = static function ( $ok, $message ) use ( &$checks ) { ++$checks; check_fb( $ok, $message ); };
	$render = static function ( $widget, array $settings ) {
		$widget->settings = $settings;
		$widget->attrs    = array();
		ob_start();
		( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		return (string) ob_get_clean();
	};
	$controls = static function ( $widget ) {
		$widget->controls = array();
		$widget->sections = array();
		( new ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
		return $widget->controls;
	};
	// Atributos del primer `div` con la clase del plugin del servicio.
	$plugin_attrs = static function ( string $html ) {
		if ( ! preg_match( '/<div ([^>]*class="(?:[^"]*\s)?fb-[a-z-]+(?:\s[^"]*)?"[^>]*)>/', $html, $m ) ) {
			return null;
		}
		preg_match_all( '/([a-z-]+)="([^"]*)"/', $m[1], $pairs, PREG_SET_ORDER );
		$out = array();
		foreach ( $pairs as $p ) {
			$out[ $p[1] ] = html_entity_decode( $p[2], ENT_QUOTES );
		}
		return $out;
	};
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';

	$pairs = array(
		'facebook-button'   => array( 'Botón de Facebook', \ElementorPro\Modules\Social\Widgets\Facebook_Button::class ),
		'facebook-comments' => array( 'Comentarios de Facebook', \ElementorPro\Modules\Social\Widgets\Facebook_Comments::class ),
		'facebook-embed'    => array( 'Publicación de Facebook', \ElementorPro\Modules\Social\Widgets\Facebook_Embed::class ),
		'facebook-page'     => array( 'Página de Facebook', \ElementorPro\Modules\Social\Widgets\Facebook_Page::class ),
	);
	$css_file = $root . 'modules/digitalisimo-services/assets/css/facebook.css';
	$js_file  = $root . 'modules/digitalisimo-services/assets/js/facebook.js';
	$css      = (string) file_get_contents( $css_file );
	$js       = (string) file_get_contents( $js_file );

	// 1. Recursos compartidos.
	$check( strlen( $css ) > 0 && strlen( $css ) <= 4096 && ! preg_match( '/url\(|--e-global|@import/i', $css ), 'facebook.css: ≤ 4 KB, sin url(), sin --e-global-* ni @import (' . strlen( $css ) . ' B).' );
	$check( str_contains( $css, 'outline:2px solid' ) && str_contains( $css, 'outline-offset:2px' ) && str_contains( $css, 'min-height:24px' ) && str_contains( $css, 'min-width:24px' ), 'facebook.css: foco de 2 px a 2 px y objetivo de 24 × 24 px.' );
	$check( ! preg_match( '/@keyframes|animation:|transition:/', $css ), 'facebook.css: sin animaciones (nada que reducir).' );
	$check( strlen( $js ) > 0 && strlen( $js ) <= 6144 && ! preg_match( '/jQuery|\$\(|fetch\(|XMLHttpRequest|sendBeacon|import\(/', $js ), 'facebook.js: ≤ 6 KB, sin jQuery ni peticiones propias (' . strlen( $js ) . ' B).' );
	$click = strpos( $js, "addEventListener('click'" );
	$check( false !== $click && 1 === substr_count( $js, 'connect.facebook.net' ) && 1 === substr_count( $js, 'createElement(' ) && strpos( $js, 'connect.facebook.net' ) > 0, 'facebook.js: una sola URL del SDK y un solo elemento creado (el <script>).' );
	$check( 1 === substr_count( $js, 'sdk(lang, app, box)' ) && strpos( $js, 'sdk(lang, app, box)' ) > $click, 'facebook.js: el SDK sólo se pide desde el manejador del clic.' );
	$check( str_contains( $js, "version: 'v2.10'" ) && str_contains( $js, 'xfbml: false' ) && str_contains( $js, 'FB.XFBML.parse' ), 'facebook.js: FB.init como PRO (v2.10, sin xfbml) y FB.XFBML.parse del widget.' );
	foreach ( array( $css_file, $js_file, $root . 'modules/digitalisimo-services/class-facebook.php' ) as $file ) {
		$check( ! preg_match( $guard, (string) file_get_contents( $file ) ), basename( $file ) . ': guarda de build-packages.sh.' );
	}

	$fixture = array();
	foreach ( $pairs as $source => list( $title, $pro_class ) ) {
		$name = 'digitalisimo-' . $source;
		// 2. Registro.
		$class = Services::load( $name );
		$check( '' !== $class, "$name: el módulo lo carga." );
		$own = new $class();
		$pro = new $pro_class();
		$check( $name === $own->get_name() && $title === $own->get_title() && $title === Services::TITLES[ $name ] && array( 'digitalisimo' ) === $own->get_categories(), "$name: nombre, título y categoría propios." );
		// Los que Meta retiró (Botón y Comentarios) no tienen fachada ni piden el SDK: sólo la hoja (corrección de fidelidad).
		$retired = in_array( $name, \Digitalisimo\Elements\Services\Facebook::DISCONTINUED, true );
		$check( array( 'digitalisimo-facebook' ) === $own->get_style_depends() && ( $retired ? array() : array( 'digitalisimo-facebook' ) ) === $own->get_script_depends(), "$name: sólo la hoja y el script compartidos (sin script, los retirados)." );
		$file = $root . 'modules/digitalisimo-services/' . Services::WIDGETS[ $name ]['file'];
		$check( ! preg_match( $guard, (string) file_get_contents( $file ) ) && ! preg_match( $guard, (string) file_get_contents( $root . 'modules/digitalisimo-convert/maps/' . $source . '.php' ) ), "$name: guarda de build-packages.sh (widget y mapa)." );

		// 3. Controles, sin App ID y con App ID.
		foreach ( array( '', '1234567890' ) as $app ) {
			$GLOBALS['fb_options'] = '' === $app ? array() : array( 'elementor_pro_facebook_app_id' => $app );
			$cp = $controls( $pro );
			$co = $controls( $own );
			$check( array_keys( $cp ) === array_keys( $co ), "$name: mismos controles y en el mismo orden (" . implode( ',', array_keys( $co ) ) . ' / ' . implode( ',', array_keys( $cp ) ) . ').' );
			$check( array_keys( $pro->sections ) === array_keys( $own->sections ), "$name: mismas secciones." );
			foreach ( $cp as $key => $a ) {
				$b = $co[ $key ];
				foreach ( array( 'type', 'default', 'condition', 'dynamic', 'size_units', 'range', 'min', 'max', 'separator', 'multiple', 'label_block', 'alert_type' ) as $prop ) {
					$check( ( $a[ $prop ] ?? null ) === ( $b[ $prop ] ?? null ), "$name.$key: «{$prop}» como en PRO (" . json_encode( $a[ $prop ] ?? null ) . ' / ' . json_encode( $b[ $prop ] ?? null ) . ').' );
				}
				$check( array_keys( (array) ( $a['options'] ?? array() ) ) === array_keys( (array) ( $b['options'] ?? array() ) ), "$name.$key: mismas opciones." );
				if ( isset( $b['content'] ) ) {
					$check( ! preg_match( '/[A-Za-z]+ (the|your|will|has been|only|for) /', strip_tags( $b['content'] ) ), "$name.$key: aviso en español." );
				}
			}
			if ( '' !== $app && isset( $co['app_id'] ) ) {
				$check( str_contains( $co['app_id']['content'], $app ) && 'info' === $co['app_id']['alert_type'], "$name: con App ID, el aviso lo nombra." );
			}
		}
		$GLOBALS['fb_options'] = array( 'elementor_pro_facebook_app_id' => '1234567890' );
	}

	// 4. Marcado: el mismo plugin que PRO dentro de la fachada.
	$scenarios = array(
		'facebook-button'   => array(
			array(),
			array( 'type' => 'recommend', 'layout' => 'box_count', 'size' => 'large', 'color_scheme' => 'dark', 'show_share' => 'yes', 'show_faces' => 'yes', 'url_type' => 'current_page', 'url_format' => 'pretty' ),
			array( 'url_type' => 'custom', 'url' => 'https://ejemplo.test/oferta?a=1&b=2' ),
		),
		'facebook-comments' => array(
			array(),
			array( 'comments_number' => '25', 'order_by' => 'reverse_time', 'url_type' => 'current_page', 'url_format' => 'pretty' ),
			array( 'url_type' => 'custom', 'url' => 'https://ejemplo.test/hilo' ),
		),
		'facebook-embed'    => array(
			array( 'type' => 'post', 'post_url' => 'https://www.facebook.com/pagina/posts/123' ),
			array( 'type' => 'post', 'post_url' => 'https://www.facebook.com/pagina/posts/123', 'show_text' => 'yes' ),
			array( 'type' => 'video', 'video_url' => 'https://www.facebook.com/pagina/videos/456', 'show_text' => 'yes', 'video_allowfullscreen' => 'yes', 'video_autoplay' => 'yes', 'video_show_captions' => 'yes' ),
			array( 'type' => 'comment', 'comment_url' => 'https://www.facebook.com/pagina/posts/123?comment_id=789', 'include_parent' => 'yes' ),
		),
		'facebook-page'     => array(
			array( 'url' => 'https://www.facebook.com/pagina/', 'tabs' => array( 'timeline' ), 'show_cover' => 'yes', 'show_facepile' => 'yes', 'show_cta' => 'yes', 'height' => array( 'unit' => 'px', 'size' => 500 ) ),
			array( 'url' => 'https://www.facebook.com/pagina/', 'tabs' => array( 'timeline', 'events', 'messages' ), 'small_header' => 'yes', 'show_cover' => '', 'show_facepile' => '', 'show_cta' => '', 'height' => array( 'unit' => 'em', 'size' => 30 ) ),
		),
	);
	$defaults = array(
		'facebook-button'   => array( 'type' => 'like', 'layout' => 'standard', 'size' => 'small', 'color_scheme' => 'light', 'show_share' => '', 'show_faces' => '', 'url_type' => 'current_page', 'url_format' => 'plain', 'url' => '' ),
		'facebook-comments' => array( 'comments_number' => '10', 'order_by' => 'social', 'url_type' => 'current_page', 'url_format' => 'plain', 'url' => '' ),
		'facebook-embed'    => array( 'type' => 'post', 'post_url' => '', 'video_url' => '', 'comment_url' => '', 'include_parent' => '', 'show_text' => '', 'video_allowfullscreen' => '', 'video_autoplay' => '', 'video_show_captions' => '' ),
		'facebook-page'     => array(),
	);
	$labels = array( 'button' => 'Mostrar el botón «Me gusta» de Facebook', 'comments' => 'Ver los comentarios de Facebook', 'post' => 'Ver la publicación de Facebook', 'video' => 'Ver el vídeo de Facebook', 'comment' => 'Ver el comentario de Facebook', 'page' => 'Ver la página de Facebook' );
	foreach ( $scenarios as $source => $list ) {
		$name      = 'digitalisimo-' . $source;
		$own_class = Services::load( $name );
		$pro_class = $pairs[ $source ][1];
		foreach ( $list as $i => $settings ) {
			$settings = $settings + $defaults[ $source ];
			$a        = $render( new $pro_class(), $settings );
			$ow       = new $own_class();
			$ow->id   = 'f' . substr( md5( $name . $i ), 0, 6 );
			$b        = $render( $ow, $settings );
			$pa       = $plugin_attrs( $a );
			$pb       = $plugin_attrs( $b );
			$check( null !== $pa && null !== $pb, "$name #$i: PRO y el propio pintan el plugin." );
			$check( str_contains( $pa['class'], 'elementor-facebook-widget' ) && str_replace( 'elementor-facebook-widget', 'digi-fb__plugin', $pa['class'] ) === $pb['class'], "$name #$i: misma clase del servicio ({$pa['class']} / {$pb['class']})." );
			unset( $pa['class'], $pb['class'] );
			ksort( $pa );
			ksort( $pb );
			$check( $pa === $pb, "$name #$i: mismos data-* que PRO: " . json_encode( array_diff_assoc( $pa, $pb ) + array_diff_assoc( $pb, $pa ), JSON_UNESCAPED_SLASHES ) );
			// Retirados por Meta (Botón y Comentarios): como PRO, sólo el `div` del plugin, vacío, sin fachada ni SDK.
			if ( in_array( $name, \Digitalisimo\Elements\Services\Facebook::DISCONTINUED, true ) ) {
				$check( 1 === preg_match( '#^<div class="digi-fb__plugin fb-(like|comments)"[^>]*></div>$#', $b ) && ! str_contains( $b, 'digi-fb__load' ) && ! str_contains( $b, '<template' ), "$name #$i: sólo el div del plugin, como PRO: $b" );
				$fixture[ $name . '-' . $i ] = $b;
				continue;
			}
			// Fachada: el plugin sólo dentro del <template>; antes, el botón y la nota.
			$check( 1 === preg_match( '#^<div class="digi-fb digi-fb--[a-z -]+" data-digi-fb data-digi-fb-lang="es_MX" data-digi-fb-app="1234567890"( style="[^"]*")?><button type="button" class="digi-fb__load" aria-describedby="digi-fb-note-(f[0-9a-f]{6})"><span class="digi-fb__service" aria-hidden="true">Facebook</span><span class="digi-fb__label">[^<]+</span></button><span class="digi-fb__note" id="digi-fb-note-\\2">Al pulsar se carga contenido de Facebook\.</span><template class="digi-fb__embed"><div [^>]+></div></template></div>$#', $b ), "$name #$i: fachada con botón, nota y <template>: $b" );
			$outside = preg_replace( '#<template.*?</template>#s', '', $b );
			$check( ! preg_match( '/facebook\.com|fbcdn|connect\.facebook|<script|<iframe|<img|<link|https?:/i', $outside ), "$name #$i: fuera del <template> no hay ninguna URL, script, iframe ni imagen." );
			$kind = 'facebook-embed' === $source ? $settings['type'] : substr( $source, 9 );
			$want = 'facebook-button' === $source && 'recommend' === $settings['type'] ? 'Mostrar el botón «Recomendar» de Facebook' : $labels[ $kind ];
			$check( str_contains( $b, '<span class="digi-fb__label">' . esc_html( $want ) . '</span>' ), "$name #$i: botón «{$want}»." );
			if ( 'facebook-page' === $source ) {
				$h = $settings['height']['size'] . $settings['height']['unit'];
				$check( str_contains( $b, 'style="--digi-fb-height:' . $h . '"' ), "$name #$i: la fachada reserva el alto del plugin ($h)." );
			}
			$fixture[ $name . '-' . $i ] = $b;
		}
	}

	// 5. Validaciones como PRO.
	$own = Services::load( 'digitalisimo-facebook-button' );
	$GLOBALS['fb_edit'] = false;
	$check( '' === trim( $render( new $own(), array( 'url_type' => 'custom', 'url' => 'no es una url' ) + $defaults['facebook-button'] ) ), 'Botón: URL no válida fuera del editor, nada (como PRO).' );
	$GLOBALS['fb_edit'] = true;
	$check( 'Botón de Facebook: introduce una URL válida.' === trim( $render( new $own(), array( 'url_type' => 'custom', 'url' => 'no es una url' ) + $defaults['facebook-button'] ) ), 'Botón: URL no válida en el editor, aviso en español.' );
	$GLOBALS['fb_edit'] = false;
	$own = Services::load( 'digitalisimo-facebook-comments' );
	$check( 'Comentarios de Facebook: introduce una URL válida.' === trim( $render( new $own(), array( 'url_type' => 'custom', 'url' => '' ) + $defaults['facebook-comments'] ) ), 'Comentarios: URL personalizada vacía, aviso (como PRO).' );
	$own = Services::load( 'digitalisimo-facebook-embed' );
	$check( 'Introduce una URL válida.' === trim( $render( new $own(), $defaults['facebook-embed'] ) ), 'Publicación: sin URL, aviso.' );
	$check( 'Elige el tipo de contenido que quieres insertar.' === trim( $render( new $own(), array( 'type' => '' ) + $defaults['facebook-embed'] ) ), 'Publicación: sin tipo, aviso.' );
	$own = Services::load( 'digitalisimo-facebook-page' );
	$check( 'Página de Facebook: introduce una URL válida.' === trim( $render( new $own(), array( 'url' => '' ) ) ), 'Página: sin URL, aviso (por defecto).' );
	$check( str_contains( $render( new $own(), array( 'url' => 'https://www.facebook.com/p/', 'height' => array( 'unit' => 'custom', 'size' => '1px;background:red' ) ) ), '--digi-fb-height:500px' ), 'Página: un alto «custom» extraño no llega al estilo.' );
	$check( str_contains( $render( new $own(), array( 'url' => 'javascript:alert(1)', 'height' => array( 'unit' => 'px', 'size' => 300 ) ) ), 'data-href=""' ), 'Página: una URL no web no llega a data-href.' );

	// 6. Conversión de ajustes reales: cada clave cae en un control propio, sin pro.unmapped.
	$samples = array(
		'facebook-button'   => array( array(), array( 'type' => 'recommend', 'layout' => 'button_count', 'size' => 'large', 'color_scheme' => 'dark', 'show_share' => 'yes', 'show_faces' => 'yes', 'url_type' => 'custom', 'url' => 'https://ejemplo.test/', '_margin' => array( 'top' => '10' ), 'custom_css' => 'selector .elementor-facebook-widget { opacity: .9 }' ) ),
		'facebook-comments' => array( array(), array( 'comments_number' => '20', 'order_by' => 'time', 'url_type' => 'current_page', 'url_format' => 'pretty', '__dynamic__' => array( 'comments_number' => '[elementor-tag id="a1" name="post-custom-field" settings="%7B%7D"]' ) ) ),
		'facebook-embed'    => array( array( 'post_url' => 'https://www.facebook.com/x/posts/1' ), array( 'type' => 'video', 'video_url' => '', 'show_text' => 'yes', 'video_allowfullscreen' => 'yes', 'video_autoplay' => 'yes', 'video_show_captions' => 'yes', '__dynamic__' => array( 'video_url' => '[elementor-tag id="a2" name="post-url" settings="%7B%7D"]' ) ), array( 'type' => 'comment', 'comment_url' => 'https://www.facebook.com/x/posts/1?comment_id=2', 'include_parent' => 'yes' ) ),
		'facebook-page'     => array( array(), array( 'url' => 'https://www.facebook.com/pagina/', 'tabs' => array( 'events' ), 'small_header' => 'yes', 'show_cover' => '', 'show_facepile' => '', 'show_cta' => '', 'height' => array( 'unit' => 'px', 'size' => 300 ), 'height_tablet' => array( 'unit' => 'px', 'size' => 400 ), 'custom_css' => '.elementor-widget-facebook-page .elementor-facebook-widget{margin:0}' ) ),
	);
	foreach ( $samples as $source => $list ) {
		$name     = 'digitalisimo-' . $source;
		$own      = Services::load( $name );
		$own_keys = array_keys( $controls( new $own() ) );
		foreach ( $list as $i => $settings ) {
			$log  = array();
			$tree = Converter::tree( array( array( 'id' => 'c1', 'elType' => 'container', 'elements' => array( array( 'id' => 'w' . $i, 'elType' => 'widget', 'widgetType' => $source, 'settings' => $settings ) ) ) ), $log, true );
			$w    = $tree[0]['elements'][0];
			$check( $name === $w['widgetType'] && 'w' . $i === $w['id'] && 1 === count( $log ), "$name #$i: convertido con el mismo id." );
			foreach ( $log[0]['codes'] as $code ) {
				$check( 'pro.unmapped' !== $code['code'] && 'pro.dropped' !== $code['code'], "$name #$i: código inesperado {$code['code']} {$code['key']}." );
			}
			foreach ( $w['settings'] as $key => $value ) {
				if ( in_array( $key, array( '_digitalisimo_pro', '__dynamic__', 'custom_css' ), true ) || '_' === $key[0] ) {
					continue;
				}
				$check( in_array( preg_replace( '/_(tablet|mobile)$/', '', $key ), $own_keys, true ), "$name #$i: «{$key}» es un control propio." );
			}
			foreach ( $settings as $key => $value ) {
				if ( '__dynamic__' !== $key && 'custom_css' !== $key ) {
					$check( array_key_exists( $key, $w['settings'] ) && $w['settings'][ $key ] === $value, "$name #$i: «{$key}» pasa sin cambios." );
				}
			}
			if ( isset( $settings['__dynamic__'] ) ) {
				$check( $settings['__dynamic__'] === $w['settings']['__dynamic__'], "$name #$i: etiquetas dinámicas intactas." );
			}
			if ( isset( $settings['custom_css'] ) ) {
				$check( ! str_contains( $w['settings']['custom_css'], 'elementor-facebook-widget' ) && str_contains( $w['settings']['custom_css'], '.digi-fb__plugin' ), "$name #$i: el CSS personalizado pasa a la clase propia." );
			}
		}
	}

	if ( in_array( '--write', $argv ?? array(), true ) ) {
		$html = "<!doctype html>\n<html lang=\"es\"><head><meta charset=\"utf-8\"><title>Fachadas de Facebook</title>\n<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/modules/digitalisimo-services/assets/css/facebook.css\">\n</head><body>\n";
		foreach ( $fixture as $key => $markup ) {
			$html .= '<div class="elementor-element" data-id="' . esc_attr( $key ) . '">' . $markup . "</div>\n";
		}
		$html .= "<script src=\"../../digitalisimo-elements/modules/digitalisimo-services/assets/js/facebook.js\" defer></script>\n</body></html>\n";
		file_put_contents( __DIR__ . '/fixtures/pro-facebook.html', $html );
		echo "fixture escrita: tests/fixtures/pro-facebook.html\n";
	}
	echo "OK: widgets de Facebook ($checks comprobaciones; hoja " . strlen( $css ) . ' B, script ' . strlen( $js ) . " B).\n";
}
