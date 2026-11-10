<?php
/**
 * Botones para compartir (`digitalisimo-share-buttons`, C2 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c2.md,
 * frente A), el widget propio que sustituye al Share Buttons de PRO, y su mapa
 * `modules/digitalisimo-convert/maps/share-buttons.php`:
 * - registro y recursos: hoja propia ≤ 4 KB (sin `--e-global-*`, `url()`, clases de PRO ni colores fuera de los de PRO;
 *   foco visible, objetivos de 24 px y movimiento reducido), script de imprimir mínimo sólo con ese botón, hojas de
 *   iconos del núcleo como PRO;
 * - controles: se ejecutan los `register_controls()` de PRO y del widget propio con los mismos dobles y se comparan
 *   uno a uno (nombre, tipo, valor por defecto, opciones, condiciones, dispositivos, unidades, rangos, clases de
 *   prefijo, selectores), también los campos del repetidor y el grupo de tipografía;
 * - marcado: enlaces normales con la URL de compartir de cada red codificada (página actual de una entrada, de un
 *   archivo y sin consulta; URL personalizada), correo, imprimir y Mix, vistas, etiquetas, nombre accesible, red obsoleta
 *   saltada, lista, iconos (SVG del núcleo, fuente, FA4) y escape;
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, completos, `__dynamic__` y `__globals__`, CSS
 *   personalizado con clases de PRO, responsivos): ningún `pro.unmapped`, cada clave cae en un control propio;
 * - guarda de `scripts/build-packages.sh`.
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $tabs     = array();
		public $type     = false;
		public function get_settings_for_display() { return $this->settings; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function is_type_instance() { return $this->type; }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; }
		public function end_controls_section() {}
		public function start_controls_tabs( $name, $args = array() ) { $this->tabs[] = array( $name, $args ); }
		public function start_controls_tab( $name, $args ) { $this->tabs[] = array( $name, $args['label'] ? 'tab' : '' ); }
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args + array( 'responsive' => true ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type ) + $args; }
	}
	class Repeater extends Widget_Base {
		public function get_controls() { return $this->controls; }
	}
	class Controls_Manager { const TEXT = 'text'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose'; const COLOR = 'color'; const SLIDER = 'slider'; const TAB_STYLE = 'style'; const URL = 'url'; const REPEATER = 'repeater'; const DIMENSIONS = 'dimensions'; }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Icons_Manager {
		public static $migrated = true;
		public static function is_migration_allowed() { return self::$migrated; }
		public static function render_font_icon( $icon, $attrs = array() ) { return '<svg aria-hidden="true" class="e-font-icon-svg e-' . str_replace( ' ', '-', $icon['value'] ) . '" data-library="' . $icon['library'] . '" viewBox="0 0 24 24"><path d="M3 3h18v18H3z"/></svg>'; }
	}
	class Plugin { public static $instance; public $experiments; }
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
	class Module_Base {}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	$GLOBALS['sb'] = array( 'singular' => true, 'svg' => false, 'request' => '', 'term' => false, 'id' => 5 );
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function _x( $v ) { return $v; }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8', false ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8', false ); }
	function esc_url( $v, $protocols = null ) {
		$protocols = $protocols ?: array( 'http', 'https', 'mailto' );
		if ( ! preg_match( '~^(' . implode( '|', $protocols ) . '):~', (string) $v ) ) {
			return '';
		}
		return str_replace( '&amp;', '&#038;', htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) );
	}
	function wp_strip_all_tags( $v ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $v ) ) ); }
	function wp_kses_post( $v ) { return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ) ); }
	function add_action() {}
	function is_singular() { return $GLOBALS['sb']['singular']; }
	function is_category() { return (bool) $GLOBALS['sb']['term']; }
	function is_tag() { return false; }
	function is_tax() { return false; }
	function is_author() { return false; }
	function is_post_type_archive() { return false; }
	function is_front_page() { return ! $GLOBALS['sb']['singular'] && ! $GLOBALS['sb']['term']; }
	function is_home() { return false; }
	function get_the_ID() { return $GLOBALS['sb']['singular'] ? $GLOBALS['sb']['id'] : false; }
	function get_queried_object_id() { return $GLOBALS['sb']['id']; }
	function get_queried_object() { return (object) array( 'term_id' => 3 ); }
	function get_term_link( $term ) { return 'https://sitio.test/categoria/noticias/'; }
	function get_permalink( $id ) { return 'https://sitio.test/hola-mundo/'; }
	function wp_get_document_title() { return $GLOBALS['sb']['singular'] ? 'Hola &amp; adiós #1 &#8211; Sitio' : 'Noticias &#8211; Sitio'; }
	function get_post( $id ) { return (object) array( 'ID' => $id, 'post_excerpt' => 'Un extracto: 50% y ?' ); }
	function get_the_post_thumbnail_url( $post, $size ) { return 'large' === $size ? false : 'https://sitio.test/wp-content/uploads/foto.jpg'; }
	function get_the_archive_description() { return '<p>Todo sobre <b>noticias</b></p>'; }
	function home_url( $path = '' ) { return 'https://sitio.test' . ( '' === $path ? '' : '/' . ltrim( $path, '/' ) ); }
	function user_trailingslashit( $v ) { return rtrim( $v, '/' ) . '/'; }
	function check_sb( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	$GLOBALS['wp'] = (object) array( 'request' => '' );

	\Elementor\Plugin::$instance              = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return 'e_font_icon_svg' === $f && $GLOBALS['sb']['svg']; } };

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/share-buttons/module.php';
	require $root . 'modules/share-buttons/widgets/share-buttons.php';
	require $root . 'modules/digitalisimo-content/class-module.php';
	\Digitalisimo\Elements\Content\Module::init();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Content\Module as Content;
	use Digitalisimo\Elements\Content\Widgets\Share_Buttons;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;

	check_sb( Share_Buttons::class === Content::load( 'digitalisimo-share-buttons' ), 'El módulo de contenido carga los Botones para compartir.' );
	$render = static function ( array $settings ) {
		$widget           = new Share_Buttons();
		$widget->settings = $settings;
		ob_start();
		( digi_test_accessible( new ReflectionMethod( $widget, 'render' ) ) )->invoke( $widget );
		return (string) ob_get_clean();
	};

	// 1. Registro y recursos.
	$widget = new Share_Buttons();
	check_sb( 'digitalisimo-share-buttons' === $widget->get_name() && 'Botones para compartir' === $widget->get_title() && array( 'digitalisimo' ) === $widget->get_categories() && 'eicon-share' === $widget->get_icon(), 'Nombre, título, categoría e icono propios.' );
	check_sb( 'Botones para compartir' === Content::TITLES['digitalisimo-share-buttons'] && 'widgets/class-share-buttons.php' === Content::WIDGETS['digitalisimo-share-buttons']['file'], 'El módulo lo declara con su archivo y su título.' );
	check_sb( array( 'digitalisimo-share-buttons', 'elementor-icons-fa-solid', 'elementor-icons-fa-brands' ) === $widget->get_style_depends(), 'Hoja propia y las de iconos del núcleo, como PRO.' );
	\Elementor\Icons_Manager::$migrated = false;
	check_sb( array( 'digitalisimo-share-buttons' ) === $widget->get_style_depends(), 'Sin la migración a FA5, sólo la hoja propia (como PRO).' );
	\Elementor\Icons_Manager::$migrated = true;
	$widget->settings = array( 'share_buttons' => array( array( 'button' => 'facebook' ) ) );
	check_sb( array( 'digitalisimo-share-buttons' ) === $widget->get_script_depends(), 'Con un botón con enlace, el script (la URL del navegador y la ventana emergente al pulsar, como PRO).' );
	$widget->settings = array( 'share_buttons' => array( array( 'button' => 'mix' ), array( 'button' => 'google' ) ) );
	check_sb( array() === $widget->get_script_depends(), 'Sólo con Mix o redes obsoletas (sin enlace ni botón), ningún script.' );
	$widget->settings = array( 'share_buttons' => array() );
	check_sb( array() === $widget->get_script_depends(), 'Sin botones, ningún script.' );
	$widget->settings = array( 'share_buttons' => array( array( 'button' => 'print' ) ) );
	check_sb( array( 'digitalisimo-share-buttons' ) === $widget->get_script_depends(), 'Con el botón de imprimir, su script.' );
	$widget->settings = array();
	$widget->type     = true;
	check_sb( array( 'digitalisimo-share-buttons' ) === $widget->get_script_depends(), 'La instancia de registro (editor) declara el script.' );
	$widget = new Share_Buttons();
	$js     = (string) file_get_contents( $root . Content::WIDGETS['digitalisimo-share-buttons']['js'] );
	$js_code = preg_replace( '#/\*.*?\*/#s', '', $js );
	check_sb( strlen( $js ) > 0 && strlen( $js ) <= 2048 && str_contains( $js_code, 'W.print()' ) && str_contains( $js_code, "'data-digi-share-print'" ) && ! preg_match( '/jQuery|fetch\(|XMLHttpRequest|sendBeacon|https?:\/\/|javascript:/i', $js_code ), 'Script de ' . strlen( $js ) . ' B (tope 2 KB), sin dependencias, peticiones ni direcciones.' );
	check_sb( str_contains( $js_code, "'toolbar=0,status=0,width=640,height=480,top=' + (screen.height / 2 - 240) + ',left=' + (screen.width / 2 - 320)" ) && str_contains( $js_code, 'if (w) w.opener = null;' ) && str_contains( $js_code, "p.get('u')" ) && str_contains( $js_code, "'data-digi-share-url'" ), 'Como PRO: la URL del navegador sin un «u» de otro dominio y la ventana emergente centrada de 640 × 480 (sin acceso a la página).' );
	$css = (string) file_get_contents( $root . Content::WIDGETS['digitalisimo-share-buttons']['css'] );
	check_sb( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	check_sb( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! str_contains( $css, 'elementor-share' ) && ! str_contains( $css, '--e-share' ), 'La hoja no usa el kit, url() ni clases o variables de PRO.' );
	preg_match_all( '/#[0-9a-f]{3,6}\b/i', $css, $colors );
	check_sb( array( '#ea4335', '#fff' ) === array_values( array_unique( array_map( 'strtolower', $colors[0] ) ) ), 'Sólo los colores por defecto de PRO: ' . implode( ', ', array_unique( $colors[0] ) ) );
	check_sb( str_contains( $css, '.digi-share .digi-share__btn:focus-visible{outline:2px solid var(--p);outline-offset:2px}' ) && str_contains( $css, 'min-height:24px;min-width:24px' ) && str_contains( $css, '@media (prefers-reduced-motion:reduce)' ), 'Foco visible de 2 px con separación de 2 px, objetivos de 24 px y movimiento reducido.' );
	foreach ( array( 'gradient', 'minimal', 'framed', 'boxed', 'flat' ) as $skin ) {
		check_sb( str_contains( $css, '.digi-share--skin-' . $skin ), "La piel $skin tiene su estilo." );
	}
	foreach ( array( '--shape-rounded', '--shape-circle', '--view-icon', '--view-text', '--color-custom', '--color-official' ) as $mod ) {
		check_sb( str_contains( $css, '.digi-share' . $mod ), "El modificador $mod tiene su estilo." );
	}
	$source = '';
	foreach ( token_get_all( (string) file_get_contents( $root . 'modules/digitalisimo-content/widgets/class-share-buttons.php' ) ) as $token ) {
		$source .= is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token;
	}
	check_sb( ! preg_match( '/jquery|wp_enqueue_script|wp_register_script|ElementorPro\\\\|share-link|javascript:|elementor-share/i', $source ), 'Sin jQuery, scripts encolados a mano, clases de PRO, share-link.js ni enlaces javascript:.' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	foreach ( array( 'modules/digitalisimo-content/widgets/class-share-buttons.php', 'modules/digitalisimo-content/assets/css/share-buttons.css', 'modules/digitalisimo-content/assets/js/share-buttons.js', 'modules/digitalisimo-convert/maps/share-buttons.php' ) as $file ) {
		check_sb( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file pasa la guarda de build-packages.sh." );
	}

	// 2. Los controles de PRO, uno a uno, en el widget propio.
	$pro = new \ElementorPro\Modules\ShareButtons\Widgets\Share_Buttons();
	( digi_test_accessible( new ReflectionMethod( $pro, 'register_controls' ) ) )->invoke( $pro );
	( digi_test_accessible( new ReflectionMethod( $widget, 'register_controls' ) ) )->invoke( $widget );
	$prefix = array( 'elementor-share-buttons--view-' => 'digi-share--view-', 'elementor-share-buttons--skin-' => 'digi-share--skin-', 'elementor-share-buttons--shape-' => 'digi-share--shape-', 'elementor-share-buttons--color-' => 'digi-share--color-', 'elementor-grid%s-' => 'elementor-grid%s-' );
	$same   = static function ( string $where, string $name, array $a, array $b ) use ( $prefix ) {
		foreach ( array( 'type', 'default', 'condition', 'conditions', 'responsive', 'size_units', 'range', 'tablet_default', 'mobile_default', 'show_label', 'label_block', 'separator', 'render_type', 'return_value', 'dynamic', 'multiple' ) as $key ) {
			check_sb( ( $a[ $key ] ?? null ) === ( $b[ $key ] ?? null ), "$where$name: «{$key}» distinto de PRO (" . json_encode( $a[ $key ] ?? null ) . ' / ' . json_encode( $b[ $key ] ?? null ) . ').' );
		}
		check_sb( array_keys( (array) ( $a['options'] ?? array() ) ) === array_keys( (array) ( $b['options'] ?? array() ) ), "$where$name: las mismas opciones que PRO." );
		check_sb( ( isset( $a['prefix_class'] ) ? $prefix[ $a['prefix_class'] ] : null ) === ( $b['prefix_class'] ?? null ), "$where$name: la clase de prefijo equivalente." );
		check_sb( count( (array) ( $a['selectors'] ?? array() ) ) === count( (array) ( $b['selectors'] ?? array() ) ), "$where$name: aplica su estilo como PRO." );
		foreach ( array_keys( (array) ( $a['selectors'] ?? array() ) ) as $i => $selector ) {
			$own = array_keys( (array) $b['selectors'] )[ $i ];
			check_sb( preg_replace( '/\{\{WRAPPER\}\}.*$/', '', $selector ) === preg_replace( '/\{\{WRAPPER\}\}.*$/', '', $own ), "$where$name: el selector $i está en el mismo dispositivo." );
		}
		check_sb( array_key_exists( 'options', $a ) === array_key_exists( 'options', $b ), "$where$name: «options» como PRO." );
	};
	foreach ( $pro->controls as $name => $args ) {
		check_sb( isset( $widget->controls[ $name ] ), "$name: sin control propio." );
		if ( 'share_buttons' === $name ) {
			check_sb( $args['default'] === $widget->controls[ $name ]['default'] && 'repeater' === $widget->controls[ $name ]['type'], 'share_buttons: el repetidor con las mismas tres redes por defecto.' );
			continue;
		}
		$same( '', $name, $args, $widget->controls[ $name ] );
	}
	check_sb( array_keys( $pro->controls ) === array_keys( $widget->controls ), 'Los mismos controles en el mismo orden.' );
	check_sb( array_keys( $pro->groups ) === array_keys( $widget->groups ) && $pro->groups['typography']['exclude'] === $widget->groups['typography']['exclude'] && ! isset( $widget->groups['typography']['global'] ), 'El mismo grupo de tipografía (sin la altura de línea).' );
	check_sb( array_keys( $pro->sections ) === array_keys( $widget->sections ) && $pro->tabs === $widget->tabs && $pro->sections['section_buttons_style']['tab'] === $widget->sections['section_buttons_style']['tab'], 'Las mismas secciones y pestañas (la de colores personalizados, con su condición).' );
	$pro_fields = $pro->controls['share_buttons']['fields'];
	$own_fields = $widget->controls['share_buttons']['fields'];
	check_sb( array_keys( $pro_fields ) === array_keys( $own_fields ), 'Los mismos campos en el repetidor.' );
	foreach ( $pro_fields as $name => $args ) {
		$same( 'share_buttons.', $name, $args, $own_fields[ $name ] );
	}
	check_sb( array_keys( \ElementorPro\Modules\ShareButtons\Module::get_networks() ) === array_keys( Share_Buttons::NETWORKS ), 'Las mismas redes que PRO, en su orden.' );
	foreach ( \ElementorPro\Modules\ShareButtons\Module::get_networks() as $name => $network ) {
		check_sb( in_array( $name, array( 'email', 'print' ), true ) ? 'Email' === $network['title'] || 'Print' === $network['title'] : $network['title'] === Share_Buttons::NETWORKS[ $name ][0], "$name: el mismo título (salvo Correo e Imprimir, en español)." );
		check_sb( preg_match( '/^#[0-9a-f]{3,6}$/', Share_Buttons::NETWORKS[ $name ][1] ) && str_contains( (string) file_get_contents( $root . 'assets/css/widget-share-buttons.min.css' ), '.elementor-share-btn_' . $name . '{background-color:' . Share_Buttons::NETWORKS[ $name ][1] . '}' ), "$name: el color oficial de la hoja de PRO." );
	}
	$map = require $root . 'modules/digitalisimo-convert/maps/share-buttons.php';
	$declared = array_merge( $map['keep'], array_keys( $map['groups'] ), array_keys( $map['drop'] ), array_keys( $map['repeaters'] ) );
	foreach ( array_merge( array_keys( $pro->controls ), array_keys( $pro->groups ) ) as $name ) {
		check_sb( in_array( $name, $declared, true ), "$name: el mapa no lo declara." );
	}
	check_sb( array( 'button', 'text' ) === $map['repeaters']['share_buttons']['keep'] && $pro->controls['share_buttons']['default'] === $map['repeaters']['share_buttons']['default_rows'], 'El repetidor se conserva y, sin guardar, llega con las filas por defecto de PRO.' );
	foreach ( $map['defaults'] as $name => $value ) {
		check_sb( $value === ( $pro->controls[ $name ]['default'] ?? null ) && $value === ( $widget->controls[ $name ]['default'] ?? null ), "$name: el valor por defecto del mapa es el de PRO y el del control propio." );
	}

	// 3. Marcado del servidor.
	check_sb( '' === $render( array( 'share_buttons' => array() ) ) && '' === $render( array() ), 'Sin botones no se pinta nada.' );
	$defaults = array( 'view' => 'icon-text', 'show_label' => 'yes', 'share_url_type' => 'current_page', 'share_buttons' => $pro->controls['share_buttons']['default'] );
	$html     = $render( $defaults );
	$u        = 'https%3A%2F%2Fsitio.test%2Fhola-mundo%2F';
	check_sb( '<div class="elementor-grid digi-share" role="list" data-digi-share-url="' . $u . '"><div class="elementor-grid-item" role="listitem"><a class="digi-share__btn digi-share__btn--facebook" style="--digi-share-o:#3b5998" aria-label="Compartir en Facebook" href="https://www.facebook.com/sharer.php?u=' . $u . '" target="_blank" rel="noopener noreferrer"><span class="digi-share__icon"><i class="fab fa-facebook" aria-hidden="true"></i></span><span class="digi-share__text"><span class="digi-share__title">Facebook</span></span></a></div>' === substr( $html, 0, strpos( $html, '</div>' ) + 6 ), 'Primer botón: enlace con destino, color oficial, nombre accesible en español, icono y título: ' . substr( $html, 0, 600 ) );
	check_sb( str_contains( $html, 'href="https://x.com/intent/tweet?text=Un%20extracto%3A%2050%25%20y%20%3F%20' . $u . '"' ), 'X: el extracto y la URL, todo codificado.' );
	check_sb( str_contains( $html, 'href="https://www.linkedin.com/shareArticle?mini=true&#038;url=' . $u . '&#038;title=Hola%20%26%20adi%C3%B3s%20%231%20%E2%80%93%20Sitio&#038;summary=Un%20extracto%3A%2050%25%20y%20%3F&#038;source=' . $u . '"' ), 'LinkedIn: título del documento decodificado y codificado para la URL, con & y # dentro.' );
	check_sb( 3 === substr_count( $html, 'target="_blank" rel="noopener noreferrer"' ) && ! str_contains( $html, 'role="button"' ) && ! str_contains( $html, '<script' ), 'Tres enlaces que abren otra pestaña, sin role="button" ni scripts.' );
	$all = array();
	foreach ( array_keys( Share_Buttons::NETWORKS ) as $name ) {
		$all[] = array( 'button' => $name );
	}
	$every = $render( array( 'share_buttons' => $all ) + $defaults );
	check_sb( 20 === substr_count( $every, 'role="listitem"' ) && 17 === substr_count( $every, 'target="_blank"' ), 'Todas las redes: 20 botones, 17 enlaces a otra pestaña.' );
	check_sb( str_contains( $every, '<a class="digi-share__btn digi-share__btn--email" style="--digi-share-o:#ea4335" aria-label="Compartir por correo" href="mailto:?subject=Hola%20%26%20adi%C3%B3s%20%231%20%E2%80%93%20Sitio&#038;body=Un%20extracto%3A%2050%25%20y%20%3F%0A' . $u . '"><span class="digi-share__icon"><i class="fas fa-envelope" aria-hidden="true"></i></span><span class="digi-share__text"><span class="digi-share__title">Correo</span></span></a>' ), 'Correo: mailto sin otra pestaña, «Correo» y su icono.' );
	check_sb( str_contains( $every, '<button type="button" class="digi-share__btn digi-share__btn--print" style="--digi-share-o:#aaa" aria-label="Imprimir la página" data-digi-share-print><span class="digi-share__icon"><i class="fas fa-print" aria-hidden="true"></i></span><span class="digi-share__text"><span class="digi-share__title">Imprimir</span></span></button>' ), 'Imprimir: un botón con su script, sin javascript: en un enlace.' );
	check_sb( str_contains( $every, '<span class="digi-share__btn digi-share__btn--mix" style="--digi-share-o:#f3782b" aria-label="Compartir en Mix">' ) && ! str_contains( $every, 'mix.com' ), 'Mix (sin URL de compartir en PRO): sin enlace.' );
	check_sb( str_contains( $every, 'https://www.pinterest.com/pin/create/button/?url=' . $u . '&#038;media=https%3A%2F%2Fsitio.test%2Fwp-content%2Fuploads%2Ffoto.jpg' ) && str_contains( $every, 'https://api.whatsapp.com/send?text=*Hola%20%26%20adi%C3%B3s%20%231%20%E2%80%93%20Sitio*%0AUn%20extracto%3A%2050%25%20y%20%3F%0A' . $u ) && str_contains( $every, 'https://telegram.me/share/url?url=' . $u . '&#038;text=' ) && str_contains( $every, 'https://connect.ok.ru/offer?url=' ) && str_contains( $every, '<i class="fab fa-get-pocket"' ), 'Pinterest con la imagen destacada (tamaño completo sin «large»), WhatsApp, Telegram, OK y el icono de Pocket.' );
	foreach ( array_keys( Share_Buttons::NETWORKS ) as $name ) {
		check_sb( str_contains( $every, 'digi-share__btn--' . $name . '"' ), "$name se pinta." );
	}
	$custom = $render( array( 'share_url_type' => 'custom', 'share_url' => array( 'url' => 'https://otro.test/a?b=1&c=2' ), 'share_buttons' => array( array( 'button' => 'twitter' ), array( 'button' => 'email' ), array( 'button' => 'google' ) ) ) + $defaults );
	check_sb( str_contains( $custom, 'href="https://twitter.com/intent/tweet?text=%20https%3A%2F%2Fotro.test%2Fa%3Fb%3D1%26c%3D2"' ) && str_contains( $custom, 'href="mailto:?body=%0Ahttps%3A%2F%2Fotro.test%2Fa%3Fb%3D1%26c%3D2"' ), 'URL personalizada: sólo ella (sin título ni extracto, como PRO) y el correo sin asunto vacío.' );
	check_sb( 2 === substr_count( $custom, 'role="listitem"' ) && str_contains( $custom, 'role="list"' ) && ! str_contains( $custom, 'google' ), 'Una red obsoleta no se pinta (la lista cuenta las filas, como PRO).' );
	check_sb( ! str_contains( $custom, 'data-digi-share-url' ), 'Con la URL personalizada, el script no cambia la URL (como PRO).' );
	// En lo convertido de PRO (marca del traductor), «Email» y «Print» sin traducir, como PRO; lo nuevo, en español.
	$mail_print = array( 'share_buttons' => array( array( 'button' => 'email' ), array( 'button' => 'print' ), array( 'button' => 'facebook' ) ) ) + $defaults;
	$pro_mp     = $render( $mail_print + array( Translator::MARKER => 'share-buttons' ) );
	$own_mp     = $render( $mail_print );
	check_sb( str_contains( $pro_mp, '<span class="digi-share__title">Email</span>' ) && str_contains( $pro_mp, '<span class="digi-share__title">Print</span>' ) && ! str_contains( $pro_mp, '>Correo<' ) && ! str_contains( $pro_mp, '>Imprimir<' ), 'Lo convertido de PRO dice «Email» y «Print», como PRO.' );
	check_sb( str_contains( $own_mp, '<span class="digi-share__title">Correo</span>' ) && str_contains( $own_mp, '<span class="digi-share__title">Imprimir</span>' ), 'Lo nuevo sigue diciendo «Correo» e «Imprimir».' );
	check_sb( str_replace( array( '>Email<', '>Print<' ), array( '>Correo<', '>Imprimir<' ), $pro_mp ) === $own_mp, 'Sólo cambia el título: el nombre accesible y el resto del marcado son los mismos.' );
	$pro_custom = $render( array( 'share_buttons' => array( array( 'button' => 'email', 'text' => 'Escríbenos' ) ), Translator::MARKER => 'share-buttons' ) + $defaults );
	check_sb( str_contains( $pro_custom, '>Escríbenos</span>' ) && ! str_contains( $pro_custom, '>Email<' ), 'Con texto propio, el texto (también en lo convertido).' );
	$one = $render( array( 'share_buttons' => array( array( 'button' => 'reddit' ) ) ) + $defaults );
	check_sb( ! str_contains( $one, 'role="list' ) && str_contains( $one, '<div class="elementor-grid-item">' ), 'Con un solo botón, sin rol de lista (como PRO).' );
	$icon = $render( array( 'view' => 'icon', 'show_label' => null ) + $defaults );
	check_sb( 3 === substr_count( $icon, 'digi-share__icon' ) && ! str_contains( $icon, 'digi-share__text' ) && str_contains( $icon, 'aria-label="Compartir en LinkedIn"' ), 'Vista «Icono»: sólo iconos, con nombre accesible.' );
	$text = $render( array( 'view' => 'text', 'show_label' => null ) + $defaults );
	check_sb( ! str_contains( $text, 'digi-share__icon' ) && 3 === substr_count( $text, 'digi-share__title' ), 'Vista «Texto»: sólo los títulos.' );
	check_sb( ! str_contains( $render( array( 'show_label' => '' ) + $defaults ), 'digi-share__text' ), 'Icono y texto sin etiqueta: sólo iconos.' );
	$label = $render( array( 'share_buttons' => array( array( 'button' => 'facebook', 'text' => 'Compártelo <em>ya</em><script>alert(1)</script>' ), array( 'button' => 'vk', 'text' => '  ' ) ) ) + $defaults );
	check_sb( str_contains( $label, '<span class="digi-share__title">Compártelo <em>ya</em></span>' ) && ! str_contains( $label, '<script' ) && str_contains( $label, 'style="--digi-share-o:#3b5998" href=' ), 'Texto personalizado con el HTML permitido: es el nombre accesible (sin aria-label).' );
	check_sb( str_contains( $label, 'aria-label="Compartir en VK"' ) && str_contains( $label, '>VK</span>' ), 'Un texto personalizado vacío deja el título de la red.' );
	$evil = $render( array( 'share_url_type' => 'custom', 'share_url' => array( 'url' => 'javascript:alert(1)"><x>' ), 'share_buttons' => array( array( 'button' => 'facebook' ), array( 'button' => 'x"y' ) ) ) + $defaults );
	check_sb( str_contains( $evil, 'href="https://www.facebook.com/sharer.php?u=javascript%3Aalert%281%29%22%3E%3Cx%3E"' ) && ! str_contains( $evil, '<x>' ) && ! str_contains( $evil, 'x"y' ), 'Una URL personalizada maliciosa sólo viaja codificada como parámetro; una red desconocida no se pinta.' );
	$GLOBALS['sb']['svg'] = true;
	check_sb( str_contains( $render( $defaults ), '<span class="digi-share__icon"><svg aria-hidden="true" class="e-font-icon-svg e-fab-fa-facebook" data-library="fa-brands" viewBox="0 0 24 24"><path d="M3 3h18v18H3z"/></svg></span>' ), 'Con iconos SVG del núcleo, el SVG en línea de Font Awesome (los mismos datos que PRO).' );
	$GLOBALS['sb']['svg']               = false;
	\Elementor\Icons_Manager::$migrated = false;
	check_sb( str_contains( $render( array( 'share_buttons' => array( array( 'button' => 'email' ), array( 'button' => 'tumblr' ) ) ) + $defaults ), '<i class="fa fa-envelope" aria-hidden="true"></i>' ) && 'fa fa-tumblr' === Share_Buttons::icon_data( 'tumblr' )['value'], 'Sin la migración a FA5, las clases de Font Awesome 4 (como PRO).' );
	\Elementor\Icons_Manager::$migrated = true;
	// Página actual de un archivo y de una ruta sin entrada: sin consulta, con la descripción del archivo.
	$GLOBALS['sb'] = array( 'singular' => false, 'svg' => false, 'term' => true, 'id' => 3 );
	$archive       = Share_Buttons::share_data( array() );
	check_sb( array( 'url' => 'https://sitio.test/categoria/noticias/', 'title' => 'Noticias – Sitio', 'text' => 'Todo sobre noticias', 'image' => '' ) === $archive, 'Archivo: su enlace, su título y su descripción sin etiquetas, sin imagen: ' . json_encode( $archive, JSON_UNESCAPED_UNICODE ) );
	$GLOBALS['sb']['term'] = false;
	$GLOBALS['wp']         = (object) array( 'request' => 'tienda/zapatos' );
	check_sb( 'https://sitio.test/tienda/zapatos/' === Share_Buttons::current_url(), 'Una ruta sin objeto: el sitio con esa ruta, sin la consulta de la petición.' );
	$GLOBALS['wp'] = (object) array( 'request' => '' );
	check_sb( 'https://sitio.test/' === Share_Buttons::current_url(), 'La portada.' );
	$GLOBALS['sb'] = array( 'singular' => true, 'svg' => false, 'term' => false, 'id' => 5 );

	// Fixture del navegador (`tests/fixtures/pro-share-buttons.html`, para elements-pro-share-buttons-chrome.mjs): sus
	// botones salen de este mismo render (iconos SVG del núcleo); `--write-fixture` lo regenera.
	$GLOBALS['sb']['svg'] = true;
	$five    = array( array( 'button' => 'facebook' ), array( 'button' => 'x-twitter' ), array( 'button' => 'linkedin' ), array( 'button' => 'email' ), array( 'button' => 'print' ) );
	$widgets = array(
		's1' => array( 'icon-text digi-share--skin-gradient digi-share--shape-square digi-share--color-official elementor-grid-0', array( 'share_buttons' => $five ) + $defaults ),
		's2' => array( 'icon-text digi-share--skin-flat digi-share--shape-rounded digi-share--color-custom elementor-grid-2', array( 'share_buttons' => array( array( 'button' => 'whatsapp' ), array( 'button' => 'telegram', 'text' => 'Mándalo' ) ) ) + $defaults ),
		's3' => array( 'icon digi-share--skin-boxed digi-share--shape-rounded digi-share--color-official elementor-grid-3', array( 'view' => 'icon', 'show_label' => null, 'share_buttons' => array( array( 'button' => 'pinterest' ), array( 'button' => 'reddit' ), array( 'button' => 'tumblr' ) ) ) + $defaults ),
		's4' => array( 'text digi-share--skin-framed digi-share--shape-circle digi-share--color-custom elementor-grid-0', array( 'view' => 'text', 'show_label' => null, 'share_buttons' => array( array( 'button' => 'vk' ), array( 'button' => 'xing' ) ) ) + $defaults ),
		's5' => array( 'icon-text digi-share--skin-minimal digi-share--shape-circle digi-share--color-official elementor-grid-0', array( 'share_buttons' => array( array( 'button' => 'threads' ), array( 'button' => 'mix' ) ) ) + $defaults ),
		's6' => array( 'icon digi-share--skin-flat digi-share--shape-square digi-share--color-official elementor-grid-0', array( 'view' => 'icon', 'show_label' => null, 'share_buttons' => array( array( 'button' => 'digg' ), array( 'button' => 'skype' ) ) ) + $defaults ),
	);
	$body = '';
	foreach ( $widgets as $id => $w ) {
		$body .= '<div class="elementor-element elementor-element-' . $id . ' elementor-widget elementor-widget-digitalisimo-share-buttons digi-share--view-' . $w[0] . '" data-id="' . $id . '"><div class="elementor-widget-container">' . $render( $w[1] ) . "</div></div>\n";
	}
	$GLOBALS['sb']['svg'] = false;
	$page    = "<!doctype html>\n<html lang=\"es\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>Botones para compartir</title>\n"
		. "<!-- Generado por tests/elements-pro-share-buttons.php --write-fixture: no editar a mano. Lo usa elements-pro-share-buttons-chrome.mjs. -->\n"
		. "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/elementor/assets/css/frontend.min.css\">\n<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/modules/digitalisimo-content/assets/css/share-buttons.css\">\n"
		. "<style>body{margin:0;font:16px/1.5 system-ui,sans-serif;color:#1a1a1a;background:#fff}.digi-test-page{padding:16px}.elementor-element{margin-bottom:24px}.elementor-widget-digitalisimo-share-buttons{--grid-side-margin:10px;--grid-column-gap:10px;--grid-row-gap:10px;--grid-bottom-margin:10px}.elementor-element-s3 .digi-share__btn,.elementor-element-s4 .digi-share__btn{border-width:2px}.elementor-element-s2,.elementor-element-s4{--digi-share-primary:#112233;--digi-share-secondary:#ffeedd}.elementor-element-s2 .digi-share__btn:hover{--digi-share-primary:#334455}.elementor-element-s6 .digi-share__btn{font-size:calc(0.5px * 10)}</style>\n"
		. "<script src=\"../../digitalisimo-elements/modules/digitalisimo-content/assets/js/share-buttons.js\" defer></script>\n</head>\n<body>\n<div class=\"digi-test-page\">\n" . $body . "</div>\n</body>\n</html>\n";
	$fixture = __DIR__ . '/fixtures/pro-share-buttons.html';
	if ( in_array( '--write-fixture', $argv ?? array(), true ) ) {
		file_put_contents( $fixture, $page );
		echo "Fixture escrito: tests/fixtures/pro-share-buttons.html\n";
	}
	check_sb( is_file( $fixture ) && file_get_contents( $fixture ) === $page, 'El fixture del navegador es exactamente el marcado actual (regenerar con --write-fixture).' );

	// 4. Conversión de Share Buttons de PRO con el mapa.
	Registry::reset();
	check_sb( null !== Registry::map( 'share-buttons', true ) && 'digitalisimo-share-buttons' === $map['target'] && 'hide' === $map['panel'], 'Mapa del Share Buttons de PRO hacia el widget propio, que oculta el de PRO.' );
	$own_keys = array_keys( $widget->controls );
	$lands    = static function ( $key, array $controls, array $groups ) {
		$base = Translator::base( (string) $key );
		if ( in_array( $base, $controls, true ) || Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', Translator::MARKER ), true ) ) {
			return true;
		}
		foreach ( $groups as $group ) {
			if ( 0 === strpos( (string) $key, $group . '_' ) ) {
				return true;
			}
		}
		return false;
	};
	$cases = array(
		'defaults' => array(),
		'full'     => array(
			'share_buttons' => array( array( '_id' => 'r1', 'button' => 'whatsapp', 'text' => 'Envíalo' ), array( '_id' => 'r2', 'button' => 'email' ), array( '_id' => 'r3', 'button' => 'print' ) ),
			'view' => 'icon', 'skin' => 'framed', 'shape' => 'circle', 'columns' => '3', 'columns_mobile' => '1', 'alignment' => 'center', 'alignment_tablet' => 'justify',
			'share_url_type' => 'custom', 'share_url' => array( 'url' => 'https://otro.test/', 'is_external' => '', 'nofollow' => '' ),
			'column_gap' => array( 'unit' => 'px', 'size' => 4 ), 'row_gap_tablet' => array( 'unit' => 'em', 'size' => 1 ), 'button_size' => array( 'unit' => 'px', 'size' => 1.2 ), 'icon_size' => array( 'unit' => 'em', 'size' => 2 ), 'icon_size_mobile' => array( 'unit' => 'px', 'size' => 18 ),
			'button_height' => array( 'unit' => 'px', 'size' => 50 ), 'border_size' => array( 'unit' => 'px', 'size' => 3 ), 'color_source' => 'custom', 'primary_color' => '#112233', 'secondary_color' => '#ffeedd', 'primary_color_hover' => '#334455', 'secondary_color_hover' => '#000000',
			'typography_typography' => 'custom', 'typography_font_size' => array( 'unit' => 'px', 'size' => 14 ), 'typography_font_weight' => '400', 'text_padding' => array( 'top' => '0', 'right' => '10', 'bottom' => '0', 'left' => '10', 'unit' => 'px', 'isLinked' => false ),
			'custom_css' => "selector .elementor-share-btn_whatsapp .elementor-share-btn__icon{opacity:.9}\nselector.elementor-share-buttons--skin-framed .elementor-share-btn:hover{border-color:red}\n.elementor-widget-share-buttons .elementor-share-btn__title{letter-spacing:1px}",
			'_css_classes' => 'mis-botones',
		),
		'globals'  => array( 'primary_color' => '', '__globals__' => array( 'primary_color' => 'globals/colors?id=primary', 'secondary_color_hover' => 'globals/colors?id=accent', 'typography_typography' => 'globals/typography?id=text' ) ),
		'dynamic'  => array(
			'share_url_type' => 'custom', '__dynamic__' => array( 'share_url' => '[elementor-tag id="d1" name="post-url" settings="%7B%7D"]' ),
			'share_buttons'  => array( array( '_id' => 'd2', 'button' => 'facebook', '__dynamic__' => array( 'text' => '[elementor-tag id="d3" name="post-title" settings="%7B%7D"]' ) ) ),
		),
	);
	$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'share-buttons', 'settings' => $settings, 'elements' => array() );
	}
	$converted = Converter::tree( $tree, $log, true );
	check_sb( count( $cases ) === count( $log ), 'Se convierte cada Share Buttons del árbol.' );
	$out = array();
	foreach ( $converted[0]['elements'] as $element ) {
		check_sb( 'digitalisimo-share-buttons' === $element['widgetType'], $element['id'] . ': pasa a los Botones para compartir con el mismo id.' );
		$out[ $element['id'] ] = $element['settings'];
		foreach ( $element['settings'] as $key => $value ) {
			if ( 'share_buttons' !== $key ) {
				check_sb( $lands( $key, $own_keys, array_keys( $widget->groups ) ), $element['id'] . ": la clave $key no cae en un control propio." );
			}
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_sb( $lands( $key, $own_keys, array_keys( $widget->groups ) ), $element['id'] . ": $bag.$key no cae en un control propio." );
			}
		}
		foreach ( (array) ( $element['settings']['share_buttons'] ?? array() ) as $row ) {
			foreach ( array_keys( $row ) as $key ) {
				check_sb( in_array( $key, array( '_id', 'button', 'text', '__dynamic__' ), true ), $element['id'] . ": share_buttons.$key no cae en un campo propio." );
			}
		}
	}
	$codes = array();
	foreach ( $log as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	check_sb( array( 'full:pro.dropped:text_padding', 'full:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped; sólo el relleno sin efecto en PRO y el CSS convertido: ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	check_sb( $pro->controls['share_buttons']['default'] === $d['share_buttons'] && 'gradient' === $d['skin'] && 'official' === $d['color_source'] && 'share-buttons' === $d['_digitalisimo_pro'], 'Un Share Buttons sin cambios llega con las tres redes y la piel por defecto de PRO.' );
	check_sb( str_contains( $render( $d ), 'digi-share__btn--x-twitter' ), 'Lo convertido se pinta con las redes por defecto.' );
	$f = $out['full'];
	check_sb( array( '_id' => 'r1', 'button' => 'whatsapp', 'text' => 'Envíalo' ) == $f['share_buttons'][0] && 'icon' === $f['view'] && 'framed' === $f['skin'] && 'circle' === $f['shape'] && '1' === $f['columns_mobile'] && 'justify' === $f['alignment_tablet'] && 'https://otro.test/' === $f['share_url']['url'] && 18 === $f['icon_size_mobile']['size'] && 1 === $f['row_gap_tablet']['size'] && '#334455' === $f['primary_color_hover'] && '400' === $f['typography_font_weight'] && ! isset( $f['text_padding'] ) && 'mis-botones' === $f['_css_classes'], 'Filas con su id, ajustes, dispositivos, colores y tipografía pasan a su control; el relleno sin efecto no.' );
	check_sb( "selector .digi-share__btn--whatsapp .digi-share__icon{opacity:.9}\nselector.digi-share--skin-framed .digi-share__btn:hover{border-color:red}\n.elementor-widget-digitalisimo-share-buttons .digi-share__title{letter-spacing:1px}" === $f['custom_css'], 'CSS personalizado con las clases propias: ' . $f['custom_css'] );
	check_sb( array( 'primary_color' => 'globals/colors?id=primary', 'secondary_color_hover' => 'globals/colors?id=accent', 'typography_typography' => 'globals/typography?id=text' ) === $out['globals']['__globals__'], 'Colores y tipografía globales siguen a su control.' );
	check_sb( array( 'share_url' ) === array_keys( $out['dynamic']['__dynamic__'] ) && array( 'text' ) === array_keys( $out['dynamic']['share_buttons'][0]['__dynamic__'] ), 'Las etiquetas dinámicas del enlace y del texto de una fila siguen a su control.' );
	check_sb( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );

	echo 'DIGITALÍSIMO Elements: Botones para compartir (' . count( $widget->controls ) . ' controles, ' . count( $widget->groups ) . ' grupo, ' . count( Share_Buttons::NETWORKS ) . ' redes, hoja de ' . strlen( $css ) . ' B, script de ' . strlen( $js ) . " B) y su mapa desde el Share Buttons de PRO validados.\n";
}
