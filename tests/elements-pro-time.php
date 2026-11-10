<?php
/**
 * Cuenta regresiva (`digitalisimo-countdown`) e Indicador de progreso (`digitalisimo-progress-tracker`), frente D de C2
 * (docs/pro/c2.md), los widgets propios que sustituyen a Countdown y Progress Tracker de PRO, y sus mapas
 * `modules/digitalisimo-convert/maps/countdown.php` y `progress-tracker.php`:
 * - registro y recursos (hoja propia ≤ 4 KB y script ≤ 6 KB sin jQuery ni peticiones; sin `--e-global-*` ni `url()`; sólo
 *   los colores por defecto de PRO; guarda de build-packages.sh);
 * - controles: se ejecutan los `register_controls()` de PRO y de los widgets propios con los mismos dobles y se comparan
 *   uno a uno (nombre, orden, tipo, valor por defecto, opciones, condiciones, dispositivos, unidades, rangos, separadores,
 *   clases, grupos con sus globales, secciones y pestañas);
 * - marcado del servidor: dígitos iniciales, fecha en la zona horaria del sitio, temporizador perpetuo, etiquetas,
 *   acciones al terminar saneadas (y ninguna en el editor), mensaje oculto; barra y círculo con sus datos de seguimiento;
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, completos, `__globals__`, `__dynamic__`, CSS
 *   personalizado con clases de PRO, dispositivos): ningún `pro.unmapped`, cada clave cae en un control propio.
 *
 * - `tests/fixtures/pro-time.html` (la usa tests/elements-pro-time-chrome.mjs) es el marcado del servidor de hoy: con
 *   `--write`, se regenera.
 *
 * Uso: php-wasm-cli tests/elements-pro-time.php [--write]. Se ejecuta con php-wasm: sale con 0 aunque haya un error fatal, así que se juzga por la línea final.
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
		public function get_settings_for_display() { return $this->settings; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; }
		public function end_controls_section() {}
		public function start_controls_tabs( $name ) { $this->tabs[] = $name; }
		public function start_controls_tab( $name, $args ) { $this->tabs[] = $name; }
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args + array( 'responsive' => true ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type ) + $args; }
		public function add_render_attribute( $element, $key = null, $value = null ) {}
		public function print_render_attribute_string( $element ) {}
	}
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SELECT2 = 'select2'; const SWITCHER = 'switcher'; const CHOOSE = 'choose'; const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const NUMBER = 'number'; const URL = 'url'; const DATE_TIME = 'date_time'; const DIMENSIONS = 'dimensions'; const RAW_HTML = 'raw_html';
		const TAB_STYLE = 'style'; const TAB_CONTENT = 'content';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Group_Control_Text_Stroke { public static function get_type() { return 'text-stroke'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Utils { public static function get_timezone_string() { return 'America/Mexico_City'; } }
	class Plugin {
		public static $instance;
		public $editor;
	}
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors { const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_SECONDARY = 'globals/colors?id=secondary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent'; }
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_SECONDARY = 'globals/typography?id=secondary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
}
namespace Elementor\Modules\PageTemplates {
	class Module {}
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
	class Markdown_Utils {}
}
namespace ElementorPro {
	class Plugin {}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
	date_default_timezone_set( 'UTC' );
	$GLOBALS['pt_edit'] = false;
	function __( $v ) { return $v; }
	$GLOBALS['pt_l10n'] = array();
	function esc_html__( $v, $domain = 'default' ) { return $GLOBALS['pt_l10n'][ $domain ][ $v ] ?? $v; }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function is_rtl() { return false; }
	function get_option( $name ) { return 'gmt_offset' === $name ? -6 : false; }
	function wp_timezone_string() { return 'America/Mexico_City'; }
	function wp_timezone() { return new DateTimeZone( 'America/Mexico_City' ); }
	function wp_date( $format, $ts ) { return ( new DateTime( '@' . $ts ) )->setTimezone( wp_timezone() )->format( $format ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return preg_match( '~^(https?://|/|#|mailto:)~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function esc_url_raw( $v ) { return preg_match( '~^(https?://|/|#|mailto:)~', (string) $v ) ? (string) $v : ''; }
	function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $v ) ) ); }
	function wp_kses_post( $v ) { return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ) ); }
	function add_action() {}
	function check_pt( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'FALLA: ' . $message ); } }

	\Elementor\Plugin::$instance         = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->editor = new class() { public function is_edit_mode() { return $GLOBALS['pt_edit']; } };

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/countdown/widgets/countdown.php';
	require $root . 'modules/progress-tracker/widgets/progress-tracker.php';
	require $root . 'modules/digitalisimo-content/class-module.php';
	\Digitalisimo\Elements\Content\Module::init();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Content\Module as Content;
	use Digitalisimo\Elements\Content\Widgets\Countdown;
	use Digitalisimo\Elements\Content\Widgets\Progress_Tracker;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;

	$render = static function ( $widget, array $settings ) {
		$widget->settings = $settings;
		ob_start();
		( digi_test_accessible( new ReflectionMethod( $widget, 'render' ) ) )->invoke( $widget );
		return (string) ob_get_clean();
	};
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	$checks_before = 0;

	$pairs = array(
		'countdown'        => array( 'digitalisimo-countdown', 'Cuenta regresiva', Countdown::class, \ElementorPro\Modules\Countdown\Widgets\Countdown::class, array( '#fff' ) ),
		'progress-tracker' => array( 'digitalisimo-progress-tracker', 'Indicador de progreso', Progress_Tracker::class, \ElementorPro\Modules\ProgressTracker\Widgets\ProgressTracker::class, array( '#61ce70', '#eee' ) ),
	);
	$sizes   = array();
	$widgets = array();
	foreach ( $pairs as $source => list( $name, $title, $class, $pro_class, $colors ) ) {
		// 1. Registro y recursos.
		check_pt( $class === Content::load( $name ), "$name: el módulo de contenido lo carga." );
		$widget = new $class();
		check_pt( $name === $widget->get_name() && $title === $widget->get_title() && in_array( 'digitalisimo', $widget->get_categories(), true ), "$name: nombre, título y categoría propios." );
		check_pt( array( $name ) === $widget->get_style_depends() && array( $name ) === $widget->get_script_depends(), "$name: su hoja y su script, nada más." );
		check_pt( $title === Content::TITLES[ $name ] && 'widgets/class-' . substr( $name, 13 ) . '.php' === Content::WIDGETS[ $name ]['file'], "$name: el módulo lo declara con su archivo y su título." );
		$css = (string) file_get_contents( $root . Content::WIDGETS[ $name ]['css'] );
		$js  = (string) file_get_contents( $root . Content::WIDGETS[ $name ]['js'] );
		$sizes[ $name ] = array( strlen( $css ), strlen( $js ) );
		check_pt( strlen( $css ) > 0 && strlen( $css ) <= 4096, "$name: la hoja pesa " . strlen( $css ) . ' B (tope 4 096).' );
		// Excepción declarada (docs/pro/c2.md, «Correcciones de fidelidad»): la Cuenta regresiva repite el `countdown_expire`
		// de jQuery de PRO sólo si la página ya tiene jQuery; no lo pide ni lo carga.
		$compat = "\t\t\tconst J = W.jQuery, h = host || el;\n\t\t\th.dispatchEvent(new CustomEvent(K + '-expire', { bubbles: true }));\n\t\t\tif (J) J(h).trigger('countdown_expire', J(h));\n";
		check_pt( 'digitalisimo-countdown' !== $name || 1 === substr_count( $js, $compat ), "$name: emite el countdown_expire de jQuery de PRO sólo si la página tiene jQuery, junto al evento propio." );
		$own_js = preg_replace( '#/\*.*?\*/#s', '', str_replace( $compat, '', $js ) );
		check_pt( strlen( $js ) > 0 && strlen( $js ) <= 6144 && ! preg_match( '/jQuery|\$\(|swiper|fetch\(|XMLHttpRequest|sendBeacon|import\(/i', $own_js ), "$name: script propio de " . strlen( $js ) . ' B (tope 6 KB), sin jQuery, Swiper ni peticiones.' );
		check_pt( str_contains( $js, "frontend/element_ready/$name.default" ) && str_contains( $js, 'DOMContentLoaded' ), "$name: el script arranca al cargar y en el editor (frontend/element_ready)." );
		check_pt( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! preg_match( '/elementor-(countdown|scrolling|widget-countdown|widget-progress-tracker)|current-progress/', $css ), "$name: la hoja no usa el kit, url() ni clases de PRO." );
		preg_match_all( '/#[0-9a-f]{3,6}\b/i', $css, $m );
		check_pt( $colors === array_values( array_unique( array_map( 'strtolower', $m[0] ) ) ), "$name: sólo los colores por defecto de PRO: " . implode( ', ', $m[0] ) );
		$source_code = '';
		foreach ( token_get_all( (string) file_get_contents( $root . 'modules/digitalisimo-content/' . Content::WIDGETS[ $name ]['file'] ) ) as $token ) {
			$source_code .= is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token;
		}
		check_pt( ! preg_match( '/jquery|swiper|wp_enqueue_script|wp_register_script|elementor-pro-frontend|ElementorPro\\\\/i', $source_code ), "$name: sin jQuery, scripts encolados a mano ni clases de PRO." );
		foreach ( array( 'modules/digitalisimo-content/' . Content::WIDGETS[ $name ]['file'], Content::WIDGETS[ $name ]['css'], Content::WIDGETS[ $name ]['js'], "modules/digitalisimo-convert/maps/$source.php" ) as $file ) {
			check_pt( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file pasa la guarda de build-packages.sh." );
		}

		// 2. Los controles de PRO, uno a uno, en el widget propio.
		$pro = new $pro_class();
		( digi_test_accessible( new ReflectionMethod( $pro, 'register_controls' ) ) )->invoke( $pro );
		( digi_test_accessible( new ReflectionMethod( $widget, 'register_controls' ) ) )->invoke( $widget );
		check_pt( array_keys( $pro->controls ) === array_keys( $widget->controls ), "$name: los mismos controles en el mismo orden." );
		foreach ( $pro->controls as $control => $a ) {
			$b = $widget->controls[ $control ];
			foreach ( array( 'type', 'default', 'condition', 'conditions', 'responsive', 'size_units', 'range', 'tablet_default', 'mobile_default', 'label_block', 'classes', 'separator', 'multiple', 'render_type', 'content_classes', 'options' ) as $key ) {
				$va = $a[ $key ] ?? null;
				$vb = $b[ $key ] ?? null;
				if ( 'default' === $key && 'due_date' === $control ) {
					check_pt( is_string( $vb ) && 1 === preg_match( '/^\d{4}-\d\d-\d\d \d\d:\d\d$/', $vb ) && abs( strtotime( $va ) - strtotime( $vb ) ) <= 60, "$name.$control: la misma fecha por defecto (dentro de un mes, en la hora del sitio)." );
					continue;
				}
				if ( 'default' === $key && in_array( $a['type'] ?? '', array( 'text', 'textarea' ), true ) && is_string( $va ) && '' !== $va ) {
					check_pt( is_string( $vb ) && '' !== $vb, "$name.$control: texto por defecto propio." );
					continue;
				}
				if ( 'options' === $key && is_array( $va ) ) {
					check_pt( array_keys( $va ) === array_keys( (array) $vb ), "$name.$control: las mismas opciones que PRO." );
					continue;
				}
				check_pt( $va === $vb, "$name.$control: «{$key}» distinto de PRO (" . json_encode( $va ) . ' / ' . json_encode( $vb ) . ').' );
			}
			// La alineación del círculo: en PRO no tenía selectores (sólo la clase del escritorio); aquí, por dispositivo.
			if ( 'progress-tracker' === $source && 'align' === $control ) {
				check_pt( ! isset( $a['selectors'] ) && isset( $b['selectors'], $b['selectors_dictionary'] ) && array( 'left', 'center', 'right' ) === array_keys( $b['selectors_dictionary'] ), "$name.align: la alineación de cada dispositivo, con selectores (diferencia declarada)." );
				continue;
			}
			if ( isset( $a['selectors_dictionary'] ) ) {
				check_pt( array_keys( $a['selectors_dictionary'] ) === array_keys( $b['selectors_dictionary'] ?? array() ), "$name.$control: el mismo diccionario de valores." );
			}
			check_pt( isset( $a['selectors'] ) === isset( $b['selectors'] ) && isset( $a['prefix_class'] ) === isset( $b['prefix_class'] ), "$name.$control: aplica su estilo como PRO." );
			if ( isset( $a['selectors'] ) ) {
				check_pt( count( $a['selectors'] ) === count( $b['selectors'] ) || 'box_spacing' === $control, "$name.$control: tantas reglas como PRO." );
				foreach ( $b['selectors'] as $selector => $rule ) {
					check_pt( ! preg_match( '/elementor-(countdown|scrolling)|current-progress|body\.rtl|body:not/', $selector ), "$name.$control: el selector «{$selector}» es propio." );
				}
				// Las variables de PRO se conservan (el CSS personalizado que las usa sigue valiendo).
				if ( '{{WRAPPER}}' === array_key_first( $a['selectors'] ) ) {
					check_pt( array_values( $a['selectors'] ) === array_values( $b['selectors'] ) && array( '{{WRAPPER}}' ) === array_keys( $b['selectors'] ), "$name.$control: las mismas variables CSS en el envoltorio." );
				}
			}
		}
		check_pt( array_keys( $pro->groups ) === array_keys( $widget->groups ), "$name: los mismos grupos en el mismo orden." );
		foreach ( $pro->groups as $group => $a ) {
			$b = $widget->groups[ $group ];
			foreach ( array( 'type', 'global', 'types', 'exclude', 'condition', 'conditions' ) as $key ) {
				check_pt( ( $a[ $key ] ?? null ) === ( $b[ $key ] ?? null ), "$name.$group: «{$key}» del grupo distinto de PRO." );
			}
			check_pt( array_keys( (array) ( $a['fields_options'] ?? array() ) ) === array_keys( (array) ( $b['fields_options'] ?? array() ) ) && isset( $b['selector'] ) && ! preg_match( '/elementor-(countdown|scrolling)|current-progress/', $b['selector'] ), "$name.$group: mismas opciones de campo y selector propio." );
		}
		check_pt( array_keys( $pro->sections ) === array_keys( $widget->sections ) && $pro->tabs === $widget->tabs, "$name: las mismas secciones y pestañas." );
		foreach ( $pro->sections as $section => $args ) {
			check_pt( ( $args['tab'] ?? null ) === ( $widget->sections[ $section ]['tab'] ?? null ) && ( $args['condition'] ?? null ) === ( $widget->sections[ $section ]['condition'] ?? null ), "$name.$section: la sección está en la misma pestaña con la misma condición." );
		}

		// 3. El mapa: listo, oculta el de PRO, declara cada control y sus valores por defecto son los de PRO.
		$map = require $root . "modules/digitalisimo-convert/maps/$source.php";
		check_pt( $source === $map['source'] && $name === $map['target'] && 'ready' === $map['status'] && 'hide' === $map['panel'], "$source: mapa listo hacia $name, que oculta el widget de PRO." );
		$declared = array_merge( $map['keep'], array_keys( $map['groups'] ), array_keys( $map['approx'] ?? array() ), $map['ui'] );
		foreach ( array_merge( array_keys( $pro->controls ), array_keys( $pro->groups ) ) as $control ) {
			check_pt( in_array( $control, $declared, true ), "$source.$control: el mapa no lo declara." );
		}
		foreach ( $map['ui'] as $control ) {
			check_pt( in_array( $widget->controls[ $control ]['type'], array( 'heading', 'raw_html' ), true ), "$source.$control: sólo del panel." );
		}
		foreach ( $map['keep'] as $control ) {
			check_pt( isset( $widget->controls[ $control ] ), "$source.$control: se conserva en un control propio." );
		}
		foreach ( $map['defaults'] as $control => $value ) {
			$base = preg_replace( '/_(tablet|mobile)$/', '', $control );
			$key  = $base === $control ? 'default' : substr( $control, strlen( $base ) + 1 ) . '_default';
			check_pt( $value === ( $pro->controls[ $base ][ $key ] ?? null ), "$source.$control: el valor por defecto del mapa es el de PRO." );
		}
		foreach ( $map['classes'] as $from => $to ) {
			check_pt( 0 === strpos( $to, '.digi-' ) || ".elementor-widget-$name" === $to, "$source: la clase $from pasa a una propia ($to)." );
		}
		$widgets[ $name ] = $widget;
	}
	$cd = $widgets['digitalisimo-countdown'];
	$pt = $widgets['digitalisimo-progress-tracker'];
	check_pt( array( 'digitalisimo', 'theme-elements-single' ) === $pt->get_categories(), 'El Indicador de progreso también aparece entre los elementos de la plantilla de entrada, como en PRO.' );
	check_pt( str_contains( (string) file_get_contents( $root . Content::WIDGETS['digitalisimo-countdown']['css'] ), '[hidden]{display:none!important}' ), 'La cuenta oculta y el mensaje oculto no se ven aunque la disposición use flex.' );

	// 4. Marcado del servidor: Cuenta regresiva.
	$base = array( 'countdown_type' => 'due_date', 'show_days' => 'yes', 'show_hours' => 'yes', 'show_minutes' => 'yes', 'show_seconds' => 'yes', 'show_labels' => 'yes', 'custom_labels' => '', 'label_days' => 'Days', 'label_hours' => 'Hours', 'label_minutes' => 'Minutes', 'label_seconds' => 'Seconds', 'evergreen_counter_hours' => 47, 'evergreen_counter_minutes' => 59 );
	$due  = ( new DateTime( 'now', wp_timezone() ) )->modify( '+3 days +4 hours +5 minutes +30 seconds' );
	$html = $render( $cd, array( 'due_date' => $due->format( 'Y-m-d H:i:s' ) ) + $base );
	check_pt( str_contains( $html, '<div class="digi-countdown" role="timer" data-digi-date="' . $due->getTimestamp() . '">' ), 'La fecha límite se convierte en la zona horaria del sitio, como PRO: ' . $html );
	check_pt( 1 === preg_match( '#digi-countdown__days">03</span> <span class="digi-countdown__label">Días</span>.*digi-countdown__hours">04<.*digi-countdown__minutes">0[45]<.*digi-countdown__seconds">\d\d<#', $html ), 'El servidor pinta los dígitos iniciales con dos cifras y las etiquetas propias: ' . $html );
	check_pt( 4 === substr_count( $html, 'class="digi-countdown__item"' ) && ! str_contains( $html, 'data-digi-actions' ) && ! str_contains( $html, 'digi-countdown__message' ), 'Cuatro cajas; sin acciones no hay datos de acciones ni mensaje.' );
	$past = $render( $cd, array( 'due_date' => '2020-01-01 10:00', 'expire_actions' => array( 'message', 'hide', 'redirect', 'otra' ), 'message_after_expire' => '¡Terminó! <b>ya</b>', 'expire_redirect_url' => array( 'url' => 'https://destino.test/fin?a=1&b=2' ) ) + $base );
	check_pt( str_contains( $past, 'digi-countdown__days">00<' ) && str_contains( $past, 'digi-countdown__seconds">00<' ), 'Una cuenta vencida pinta ceros.' );
	check_pt( str_contains( $past, 'data-digi-actions="[{&quot;type&quot;:&quot;message&quot;},{&quot;type&quot;:&quot;hide&quot;},{&quot;type&quot;:&quot;redirect&quot;,&quot;redirect_url&quot;:&quot;https://destino.test/fin?a=1&amp;b=2&quot;}]"' ), 'Acciones conocidas, en su orden, con la URL de destino: ' . $past );
	check_pt( str_contains( $past, '<div class="digi-countdown__message" hidden>¡Terminó! &lt;b&gt;ya&lt;/b&gt;</div>' ) && 1 === substr_count( $past, 'digi-countdown__message' ), 'El mensaje se pinta una vez, oculto y escapado como en PRO.' );
	check_pt( 1514800800 === Countdown::due_timestamp( '2018-01-01 04:00' ) && 0 === Countdown::due_timestamp( 'no es una fecha' ) && 0 === Countdown::due_timestamp( '' ), 'La fecha se lee en la zona horaria del sitio; un valor que no es fecha no falla.' );
	foreach ( array( 'javascript:alert(1)', 'https://x.test/javascript/', 'ftp://x.test/', '/relativa', '' ) as $bad ) {
		check_pt( array( array( 'type' => 'hide' ) ) === Countdown::actions( array( 'expire_actions' => array( 'redirect', 'hide' ), 'expire_redirect_url' => array( 'url' => $bad ) ) ), "Una redirección a «{$bad}» se omite (sólo http/https válidas, nunca javascript)." );
	}
	$GLOBALS['pt_edit'] = true;
	$editor             = $render( $cd, array( 'due_date' => '2020-01-01 10:00', 'expire_actions' => array( 'message', 'hide' ), 'message_after_expire' => 'Fin' ) + $base );
	$GLOBALS['pt_edit'] = false;
	check_pt( ! str_contains( $editor, 'data-digi-actions' ) && ! str_contains( $editor, 'digi-countdown__message' ), 'En el editor no se envían las acciones (como PRO): la cuenta no se oculta ni redirige mientras se edita.' );
	$ever = $render( $cd, array( 'countdown_type' => 'evergreen', 'evergreen_counter_hours' => 2, 'evergreen_counter_minutes' => 30 ) + $base );
	check_pt( str_contains( $ever, 'data-digi-evergreen="9000" data-digi-date="0"' ) && str_contains( $ever, 'digi-countdown__days">00<' ) && str_contains( $ever, 'digi-countdown__hours">02<' ) && str_contains( $ever, 'digi-countdown__minutes">30<' ), 'Temporizador perpetuo: el intervalo en segundos y sus dígitos iniciales.' );
	check_pt( 0 === Countdown::evergreen_interval( array( 'evergreen_counter_hours' => '', 'evergreen_counter_minutes' => '' ) ) && 172740 === Countdown::evergreen_interval( array( 'evergreen_counter_hours' => 47, 'evergreen_counter_minutes' => 59 ) ), 'El intervalo perpetuo con los valores por defecto de PRO (47 h 59 min).' );
	$custom = $render( $cd, array( 'due_date' => '2020-01-01 10:00', 'show_days' => '', 'show_seconds' => '', 'custom_labels' => 'yes', 'label_hours' => 'Hrs<script>x()</script>', 'label_minutes' => 'Min' ) + $base );
	check_pt( 2 === substr_count( $custom, 'digi-countdown__item' ) && str_contains( $custom, 'digi-countdown__label">Hrs</span>' ) && str_contains( $custom, '>Min</span>' ) && ! str_contains( $custom, 'days' ) && ! str_contains( $custom, 'script' ), 'Partes ocultas fuera, etiquetas personalizadas filtradas.' );
	// Etiquetas por defecto de lo convertido de PRO (marca del traductor): las de PRO con su msgid y su dominio, o su
	// traducción, como `get_default_countdown_labels()` de PRO ejecutado aquí; lo nuevo sigue con las propias.
	$pro_cd = new \ElementorPro\Modules\Countdown\Widgets\Countdown();
	foreach ( array( array(), array( 'elementor-pro' => array( 'Days' => 'Tage', 'Hours' => 'Stunden', 'Minutes' => 'Minuten', 'Seconds' => 'Sekunden' ) ) ) as $l10n ) {
		$GLOBALS['pt_l10n'] = $l10n;
		$pro_labels         = array_intersect_key( $pro_cd->get_default_countdown_labels(), Countdown::default_labels() );
		( digi_test_accessible( new ReflectionProperty( $pro_cd, '_default_countdown_labels' ) ) )->setValue( $pro_cd, null );
		$converted = $render( $cd, array( 'due_date' => '2020-01-01 10:00', Translator::MARKER => 'countdown' ) + $base );
		preg_match_all( '#<span class="digi-countdown__label">([^<]*)</span>#', $converted, $got );
		check_pt( array_values( $pro_labels ) === $got[1] && $pro_labels === Countdown::pro_labels(), 'Lo convertido de PRO sin «Etiquetas personalizadas» pinta las etiquetas de PRO (' . implode( ' ', $got[1] ) . '), como PRO: ' . implode( ' ', $pro_labels ) );
		$own = $render( $cd, array( 'due_date' => '2020-01-01 10:00' ) + $base );
		check_pt( str_contains( $own, '>Días</span>' ) && str_contains( $own, '>Segundos</span>' ) && ! str_contains( $own, '>Days<' ) && ! str_contains( $own, '>Tage<' ), 'Lo nuevo sigue con las etiquetas propias en español.' );
		$custom_pro = $render( $cd, array( 'due_date' => '2020-01-01 10:00', 'custom_labels' => 'yes', 'label_days' => 'Jours', Translator::MARKER => 'countdown' ) + $base );
		check_pt( str_contains( $custom_pro, '>Jours</span>' ) && str_contains( $custom_pro, '>Hours</span>' ) && ! str_contains( $custom_pro, '>Tage<' ), 'Con «Etiquetas personalizadas», las del control, también en lo convertido.' );
	}
	$GLOBALS['pt_l10n'] = array();
	$bare = $render( $cd, array( 'due_date' => '2020-01-01 10:00', 'show_labels' => '' ) + $base );
	check_pt( ! str_contains( $bare, 'digi-countdown__label' ) && 4 === substr_count( $bare, 'digi-countdown__digits' ), 'Sin etiquetas, sólo los dígitos.' );
	check_pt( array( 'days' => 1, 'hours' => 1, 'minutes' => 1, 'seconds' => 1 ) === Countdown::parts( 90061 ) && array( 'days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 0 ) === Countdown::parts( -5 ), 'Partes de la cuenta, y cero al vencer.' );
	$cd->settings = array( 'countdown_type' => 'evergreen', 'evergreen_counter_hours' => 3, 'evergreen_counter_minutes' => 15 );
	check_pt( str_contains( $cd->render_markdown(), '3 horas, 15 minutos' ), 'Markdown del temporizador perpetuo.' );

	// 5. Marcado del servidor: Indicador de progreso.
	$bar = $render( $pt, array( 'type' => 'horizontal', 'relative_to' => 'entire_page', 'percentage' => 'no' ) );
	check_pt( '<div class="digi-progress-tracker digi-progress-tracker--horizontal" aria-hidden="true" data-digi-relative="entire_page"><div class="digi-progress-tracker__progress"><div class="digi-progress-tracker__percentage"></div></div></div>' === $bar, 'Barra: pista, avance y porcentaje, oculta a los lectores de pantalla: ' . $bar );
	$circle = $render( $pt, array( 'type' => 'circular', 'relative_to' => 'selector', 'selector' => ' #a"b ', 'direction' => 'ltr', 'percentage' => 'yes' ) );
	check_pt( str_contains( $circle, 'class="digi-progress-tracker digi-progress-tracker--circular"' ) && str_contains( $circle, 'data-digi-relative="selector" data-digi-selector="#a&quot;b" data-digi-direction="ltr" data-digi-percentage="yes"' ), 'Círculo con el selector escapado, la dirección y el porcentaje: ' . $circle );
	check_pt( str_contains( $circle, '<svg width="100%" height="100%" focusable="false"><circle class="digi-progress-tracker__track" r="40%" cx="50%" cy="50%"/><circle class="digi-progress-tracker__progress" r="40%" cx="50%" cy="50%"/></svg><div class="digi-progress-tracker__percentage"></div>' ), 'El círculo con las mismas medidas que PRO (r 40 %).' );
	check_pt( str_contains( $render( $pt, array( 'type' => 'horizontal', 'relative_to' => 'otra', 'direction' => 'arriba' ) ), 'data-digi-relative="entire_page">' ), 'Un seguimiento o una dirección desconocidos vuelven a la página entera, sin dirección.' );
	$pt->settings = array( 'relative_to' => 'selector', 'selector' => '#articulo', 'type' => 'circular' );
	check_pt( str_contains( $pt->render_markdown(), '`#articulo`' ) && str_contains( $pt->render_markdown(), 'Círculo' ), 'Markdown del indicador.' );

	// 6. Conversión con los mapas.
	Registry::reset();
	$own_keys = static function ( $widget ) { return array( array_keys( $widget->controls ), array_keys( $widget->groups ) ); };
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
		'cd-defaults' => array( 'countdown', array() ),
		'cd-full'     => array(
			'countdown',
			array(
				'due_date' => '2031-05-01 12:00', 'show_seconds' => '', 'custom_labels' => 'yes', 'label_days' => 'Jours', 'expire_actions' => array( 'message', 'redirect' ), 'message_after_expire' => 'Fin', 'expire_redirect_url' => array( 'url' => 'https://a.test/' ),
				'label_display' => 'inline', 'boxes_alignment' => 'end', 'container_width_tablet' => array( 'unit' => 'px', 'size' => 600 ), 'box_spacing' => array( 'unit' => 'px', 'size' => 24 ), 'box_spacing_mobile' => array( 'unit' => 'px', 'size' => 8 ),
				'box_padding' => array( 'top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10', 'unit' => 'px', 'isLinked' => true ), 'box_background_color' => '#222222', 'box_border_border' => 'solid', 'box_border_width' => array( 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'unit' => 'px' ), 'box_border_color' => '#ff0000', 'box_border_radius' => array( 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px' ),
				'box_shadow_box_shadow_type' => 'yes', 'box_shadow_box_shadow' => array( 'horizontal' => 0, 'vertical' => 2, 'blur' => 6, 'spread' => 0, 'color' => 'rgba(0,0,0,.3)' ),
				'digits_color' => '#ffee00', 'digits_typography_typography' => 'custom', 'digits_typography_font_size' => array( 'unit' => 'px', 'size' => 40 ), 'digits_typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 24 ), 'digits_text_shadow_text_shadow_type' => 'yes',
				'label_color' => '#cccccc', 'label_typography_typography' => 'custom', 'label_typography_text_transform' => 'uppercase', 'text_stroke_text_stroke_type' => 'yes', 'text_stroke_text_stroke' => array( 'unit' => 'px', 'size' => 1 ),
				'align' => 'start', 'align_mobile' => 'center', 'text_color' => '#333333', 'typography_typography' => 'custom', 'typography_font_weight' => '700', 'message_text_shadow_text_shadow_type' => 'yes', 'message_padding' => array( 'top' => '5', 'right' => '5', 'bottom' => '5', 'left' => '5', 'unit' => 'px' ),
				'custom_css' => "selector .elementor-countdown-wrapper .elementor-countdown-item:first-child{border-radius:0}\nselector.elementor-countdown--label-inline .elementor-countdown-digits{opacity:.9}\nselector .elementor-countdown-expire--message{margin-top:1em}",
				'_css_classes' => 'mi-cuenta', '_element_id' => 'cuenta',
			),
		),
		'cd-globals'  => array( 'countdown', array( 'box_background_color' => '', '__globals__' => array( 'box_background_color' => 'globals/colors?id=secondary', 'digits_color' => 'globals/colors?id=accent', 'digits_typography_typography' => 'globals/typography?id=primary', 'text_color' => 'globals/colors?id=text' ) ) ),
		'cd-dynamic'  => array( 'countdown', array( 'expire_actions' => array( 'redirect' ), '__dynamic__' => array( 'due_date' => '[elementor-tag id="d1" name="post-date" settings="%7B%7D"]', 'expire_redirect_url' => '[elementor-tag id="d2" name="post-url" settings="%7B%7D"]', 'message_after_expire' => '[elementor-tag id="d3" name="post-title" settings="%7B%7D"]' ) ) ),
		'pt-defaults' => array( 'progress-tracker', array() ),
		'pt-full'     => array(
			'progress-tracker',
			array(
				'type' => 'circular', 'relative_to' => 'selector', 'selector' => '#articulo', 'direction' => 'rtl', 'percentage' => 'yes', 'circular_size' => array( 'unit' => 'px', 'size' => 120 ), 'circular_size_mobile' => array( 'unit' => 'px', 'size' => 80 ),
				'circular_color' => '#ff0000', 'circular_width' => array( 'unit' => 'px', 'size' => 8 ), 'align' => 'center', 'align_tablet' => 'right', 'circular_background_color' => '#dddddd', 'circular_background_width' => array( 'unit' => 'px', 'size' => 6 ),
				'percentage_color' => '#000000', 'percentage_typography_typography' => 'custom', 'percentage_typography_font_size' => array( 'unit' => 'px', 'size' => 18 ), 'percentage_text_shadow_text_shadow_type' => 'yes',
				'custom_css' => "selector .elementor-scrolling-tracker-circular .current-progress{stroke-linecap:round}\nselector .circle{opacity:.5}\nselector .current-progress-percentage{font-weight:700}",
			),
		),
		'pt-bar'      => array(
			'progress-tracker',
			array(
				'relative_to' => 'post_content', 'percentage' => 'yes', 'percentage_position' => 'rtl', 'percentage_position_mobile' => 'ltr', 'horizontal_color_background' => 'gradient', 'horizontal_color_color' => '#00aa00', 'horizontal_color_color_b' => '#0000aa', 'horizontal_border_style' => 'solid', 'horizontal_border_width' => array( 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px' ), 'horizontal_border_color' => '#111111',
				'horizontal_border_radius' => array( 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px' ), 'horizontal_background_color_background' => 'classic', 'horizontal_background_color_color' => '#f0f0f0', 'horizontal_height' => array( 'unit' => 'px', 'size' => 12 ), 'horizontal_height_mobile' => array( 'unit' => 'px', 'size' => 6 ),
				'horizontal_tracker_border_style' => 'dashed', 'horizontal_tracker_border_width' => array( 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'unit' => 'px' ), 'horizontal_tracker_border_color' => '#999999', 'horizontal_tracker_border_radius' => array( 'top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10', 'unit' => 'px' ),
				'box_shadow_box_shadow_type' => 'yes', 'horizontal_padding' => array( 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'unit' => 'px' ),
				'__globals__' => array( 'horizontal_color_color' => 'globals/colors?id=accent', 'percentage_color' => 'globals/colors?id=secondary', 'percentage_typography_typography' => 'globals/typography?id=accent' ),
				'_position' => 'fixed', '_offset_y' => array( 'unit' => 'px', 'size' => 0 ), 'sticky' => 'top',
			),
		),
	);
	$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => list( $type, $settings ) ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}
	$converted = Converter::tree( $tree, $log, false );
	check_pt( count( $cases ) === count( $log ), 'Con los mapas listos, Converter::tree() convierte cada widget (sin borradores).' );
	$out = array();
	foreach ( $converted[0]['elements'] as $element ) {
		$target = 'cd' === substr( $element['id'], 0, 2 ) ? 'digitalisimo-countdown' : 'digitalisimo-progress-tracker';
		check_pt( $target === $element['widgetType'] && 'widget' === $element['elType'], $element['id'] . ": pasa a $target con el mismo id." );
		list( $controls, $groups ) = $own_keys( $widgets[ $target ] );
		foreach ( $element['settings'] as $key => $value ) {
			check_pt( $lands( $key, $controls, $groups ), $element['id'] . ": la clave $key no cae en un control propio." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_pt( $lands( $key, $controls, $groups ), $element['id'] . ": $bag.$key no cae en un control propio." );
			}
		}
		$out[ $element['id'] ] = $element['settings'];
	}
	$codes = array();
	foreach ( $log as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	check_pt( array( 'cd-full:pro.css:custom_css', 'pt-full:pro.dropped:align_tablet', 'pt-full:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped ni aproximación; sólo el CSS convertido y la alineación de tableta descartada (PRO no la aplicaba): ' . implode( ', ', $codes ) );
	$d = $out['cd-defaults'];
	check_pt( 'due_date' === $d['countdown_type'] && ! isset( $d['due_date'] ) && 'yes' === $d['show_seconds'] && 'Days' === $d['label_days'] && 'block' === $d['label_display'] && 100 === $d['container_width']['size'] && 10 === $d['box_spacing']['size'] && 'countdown' === $d['_digitalisimo_pro'], 'Una cuenta sin cambios llega con los valores por defecto de PRO (la fecha sigue siendo «dentro de un mes»).' );
	check_pt( 4 === substr_count( $render( $cd, $d ), 'digi-countdown__item' ), 'Lo convertido por defecto pinta las cuatro cajas.' );
	check_pt( 1 === preg_match( '#>Days</span>.*>Hours</span>.*>Minutes</span>.*>Seconds</span>#s', $render( $cd, $d ) ), 'Lo convertido por defecto (Converter::tree()) pinta las etiquetas de PRO, no las propias.' );
	$f = $out['cd-full'];
	check_pt( '2031-05-01 12:00' === $f['due_date'] && '' === $f['show_seconds'] && 'Jours' === $f['label_days'] && array( 'message', 'redirect' ) === $f['expire_actions'] && 'https://a.test/' === $f['expire_redirect_url']['url'] && 'inline' === $f['label_display'] && 'end' === $f['boxes_alignment'] && 600 === $f['container_width_tablet']['size'] && 8 === $f['box_spacing_mobile']['size'] && 'solid' === $f['box_border_border'] && 'rgba(0,0,0,.3)' === $f['box_shadow_box_shadow']['color'] && 24 === $f['digits_typography_font_size_mobile']['size'] && 'yes' === $f['text_stroke_text_stroke_type'] && 'center' === $f['align_mobile'] && '700' === $f['typography_font_weight'] && 'cuenta' === $f['_element_id'], 'Ajustes, grupos, dispositivos y Avanzado pasan a su control.' );
	check_pt( "selector .digi-countdown .digi-countdown__item:first-child{border-radius:0}\nselector.digi-countdown--label-inline .digi-countdown__digits{opacity:.9}\nselector .digi-countdown__message{margin-top:1em}" === $f['custom_css'], 'CSS personalizado con las clases propias: ' . $f['custom_css'] );
	$painted = $render( $cd, $f );
	check_pt( str_contains( $painted, '>Jours</span>' ) && str_contains( $painted, 'digi-countdown__message' ) && 3 === substr_count( $painted, 'digi-countdown__item' ), 'Lo convertido se pinta con su etiqueta, su mensaje y sin segundos.' );
	check_pt( array( 'box_background_color' => 'globals/colors?id=secondary', 'digits_color' => 'globals/colors?id=accent', 'digits_typography_typography' => 'globals/typography?id=primary', 'text_color' => 'globals/colors?id=text' ) === $out['cd-globals']['__globals__'], 'Colores y tipografías globales siguen a su control.' );
	check_pt( array( 'due_date', 'expire_redirect_url', 'message_after_expire' ) === array_keys( $out['cd-dynamic']['__dynamic__'] ), 'Las etiquetas dinámicas siguen a su control.' );
	$p = $out['pt-defaults'];
	check_pt( 'horizontal' === $p['type'] && 'entire_page' === $p['relative_to'] && 'no' === $p['percentage'] && 'none' === $p['horizontal_border_style'] && 'progress-tracker' === $p['_digitalisimo_pro'], 'Un indicador sin cambios llega con los valores por defecto de PRO.' );
	$q = $out['pt-full'];
	check_pt( 'circular' === $q['type'] && '#articulo' === $q['selector'] && 'rtl' === $q['direction'] && 80 === $q['circular_size_mobile']['size'] && 'center' === $q['align'] && ! array_key_exists( 'align_tablet', $q ) && 6 === $q['circular_background_width']['size'] && 18 === $q['percentage_typography_font_size']['size'], 'El círculo conserva tamaño, colores, grosores, la alineación de escritorio (la de tableta se descarta: PRO no la aplicaba) y porcentaje.' );
	check_pt( "selector .digi-progress-tracker--circular .digi-progress-tracker__progress{stroke-linecap:round}\nselector .digi-progress-tracker__track{opacity:.5}\nselector .digi-progress-tracker__percentage{font-weight:700}" === $q['custom_css'], 'CSS personalizado con las clases propias: ' . $q['custom_css'] );
	// Alineación por dispositivo: PRO sólo pintaba la de escritorio (como clase, en todos los tamaños), así que la de los
	// demás dispositivos se descarta (con su motivo) y la de escritorio manda en todos, como en PRO.
	$pt_map = require $root . 'modules/digitalisimo-convert/maps/progress-tracker.php';
	list( $al, $al_codes ) = Translator::translate( array( 'type' => 'circular', 'align' => 'left', 'align_mobile' => 'center', 'align_tablet' => 'right', 'align_laptop' => 'center' ), $pt_map );
	check_pt( 'left' === $al['align'] && ! array_intersect( array( 'align_mobile', 'align_tablet', 'align_laptop' ), array_keys( $al ) ), 'La alineación de escritorio se conserva y las de los dispositivos se descartan: ' . json_encode( $al ) );
	check_pt( array( 'pro.dropped:align_mobile', 'pro.dropped:align_tablet', 'pro.dropped:align_laptop' ) === array_map( static function ( $c ) { return $c['code'] . ':' . $c['key']; }, $al_codes ) && 'dropped' === $al_codes[0]['section'] && str_contains( $al_codes[0]['reason'], 'PRO no los aplicaba' ), 'Cada una, en «Descartado» con su motivo: ' . json_encode( $al_codes, JSON_UNESCAPED_UNICODE ) );
	list( $al ) = Translator::translate( array( 'type' => 'circular', 'align_tablet' => 'right' ), $pt_map );
	check_pt( ! isset( $al['align'] ) && ! isset( $al['align_tablet'] ), 'Sin alineación de escritorio, ninguna (como PRO, que sólo leía la de escritorio).' );
	$align_html = $render( $pt, $al + array( 'relative_to' => 'entire_page' ) );
	check_pt( str_contains( $align_html, 'digi-progress-tracker--circular' ), 'Lo convertido se pinta.' );
	$r = $out['pt-bar'];
	check_pt( 'post_content' === $r['relative_to'] && 'ltr' === $r['percentage_position_mobile'] && 'gradient' === $r['horizontal_color_background'] && '#0000aa' === $r['horizontal_color_color_b'] && 'dashed' === $r['horizontal_tracker_border_style'] && 6 === $r['horizontal_height_mobile']['size'] && 'fixed' === $r['_position'] && 'top' === $r['sticky'] && array( 'horizontal_color_color' => 'globals/colors?id=accent', 'percentage_color' => 'globals/colors?id=secondary', 'percentage_typography_typography' => 'globals/typography?id=accent' ) === $r['__globals__'], 'La barra conserva fondos, bordes, alto por dispositivo, globales, posición fija y fijado.' );
	check_pt( $converted === Converter::tree( $converted, $again, false ) && array() === $again, 'Lo convertido no se convierte otra vez.' );

	// 7. Página de prueba de Chrome (tests/elements-pro-time-chrome.mjs): el marcado del servidor de hoy, con los dígitos
	// iniciales a cero (dependen de la hora; el script los pone al día). Con `--write`, se regenera.
	$wrap = static function ( $name, $id, $html, $classes = '', $style = '' ) {
		return '<div class="elementor-element elementor-widget elementor-widget-' . $name . ( $classes ? ' ' . $classes : '' ) . '" data-id="' . $id . '" data-widget_type="' . $name . '.default"' . ( $style ? ' style="' . $style . '"' : '' ) . '><div class="elementor-widget-container">' . preg_replace( '#(digi-countdown__(?:days|hours|minutes|seconds)">)\d+#', '${1}00', $html ) . "</div></div>\n";
	};
	$c        = 'digitalisimo-countdown';
	$t        = 'digitalisimo-progress-tracker';
	$fixture  = "<!doctype html>\n<html lang=\"es\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width\"><title>Tiempo</title>\n";
	$fixture .= "<!-- Generada por tests/elements-pro-time.php (--write); la usa tests/elements-pro-time-chrome.mjs. -->\n";
	$fixture .= "<style>body{margin:0;font:16px/1.5 sans-serif}.elementor-widget{margin:0 0 20px}.tall{height:2400px}#objetivo{height:1200px;background:#eee}.snap,.snap body{height:100vh;margin:0;overflow:hidden}.snap body{overflow:auto}</style>\n";
	$fixture .= "<script>if(/snap/.test(location.search))document.documentElement.className='snap'</script>\n";
	$fixture .= "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/modules/digitalisimo-content/assets/css/countdown.css\">\n<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/modules/digitalisimo-content/assets/css/progress-tracker.css\">\n</head><body>\n";
	$fixture .= $wrap( $c, 'cd1', $render( $cd, array( 'due_date' => '2030-01-04 04:05:06' ) + $base ), 'digi-countdown--label-block' );
	$fixture .= $wrap( $c, 'cd2', $render( $cd, array( 'due_date' => '2029-12-31 17:00', 'expire_actions' => array( 'hide', 'message' ), 'message_after_expire' => 'Terminó' ) + $base ), 'digi-countdown--label-block' );
	$fixture .= $wrap( $c, 'ev1', $render( $cd, array( 'countdown_type' => 'evergreen', 'evergreen_counter_hours' => 2, 'evergreen_counter_minutes' => 30 ) + $base ), 'digi-countdown--label-inline' );
	$fixture .= $wrap( $t, 'pt1', $render( $pt, array( 'type' => 'horizontal', 'relative_to' => 'entire_page', 'percentage' => 'yes' ) ) );
	$fixture .= $wrap( $t, 'pt2', $render( $pt, array( 'type' => 'circular', 'relative_to' => 'selector', 'selector' => '#objetivo', 'direction' => 'ltr', 'percentage' => 'yes' ) ) );
	$fixture .= $wrap( $t, 'pt3', $render( $pt, array( 'type' => 'horizontal', 'relative_to' => 'selector', 'selector' => '###', 'percentage' => 'yes' ) ) );
	$fixture .= $wrap( $t, 'pt4', $render( $pt, array( 'type' => 'horizontal', 'relative_to' => 'post_content' ) ) );
	$fixture .= $wrap( $t, 'pt5', $render( $pt, array( 'type' => 'horizontal', 'relative_to' => 'entire_page', 'direction' => 'rtl', 'percentage' => 'yes' ) ), '', '--direction: rtl' );
	$fixture .= $wrap( $t, 'pt6', $render( $pt, array( 'type' => 'horizontal', 'relative_to' => 'entire_page', 'percentage' => 'yes' ) ), '', 'width:60px' );
	$fixture .= $wrap( $t, 'pt7', $render( $pt, array( 'type' => 'circular', 'relative_to' => 'entire_page', 'percentage' => 'yes' ) ) );
	$fixture .= "<div class=\"tall\"></div>\n<div id=\"objetivo\"></div>\n<div class=\"tall\"></div>\n";
	$fixture .= '<template id="redirigir">' . $wrap( $c, 'cd3', $render( $cd, array( 'due_date' => '2029-12-31 17:00', 'expire_actions' => array( 'redirect' ), 'expire_redirect_url' => array( 'url' => 'https://destino.test/fin' ) ) + $base ) ) . "</template>\n";
	$fixture .= "<script>if(/redirect/.test(location.search))document.body.prepend(document.getElementById('redirigir').content)</script>\n";
	$fixture .= "<script src=\"../../digitalisimo-elements/modules/digitalisimo-content/assets/js/countdown.js\" defer></script>\n<script src=\"../../digitalisimo-elements/modules/digitalisimo-content/assets/js/progress-tracker.js\" defer></script>\n</body></html>\n";
	$path = __DIR__ . '/fixtures/pro-time.html';
	if ( in_array( '--write', $argv ?? array(), true ) ) {
		file_put_contents( $path, $fixture );
	}
	check_pt( is_file( $path ) && file_get_contents( $path ) === $fixture, 'tests/fixtures/pro-time.html es el marcado de hoy (regenerar con --write).' );

	echo 'DIGITALÍSIMO Elements: Cuenta regresiva (' . count( $cd->controls ) . ' controles, ' . count( $cd->groups ) . ' grupos, hoja de ' . $sizes['digitalisimo-countdown'][0] . ' B, script de ' . $sizes['digitalisimo-countdown'][1] . ' B) e Indicador de progreso (' . count( $pt->controls ) . ' controles, ' . count( $pt->groups ) . ' grupos, hoja de ' . $sizes['digitalisimo-progress-tracker'][0] . ' B, script de ' . $sizes['digitalisimo-progress-tracker'][1] . ' B) y sus mapas desde PRO (' . count( $cases ) . " casos) validados.\n";
}
