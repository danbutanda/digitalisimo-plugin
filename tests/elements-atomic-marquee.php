<?php
/**
 * Marquesina atómica (`e-digitalisimo-marquee`, C1 de la auditoría de digitalisimo.lat del 2026-11-24): el MCP del
 * constructor descarta `animation` en los estilos y sólo acepta widgets atómicos, así que la franja de clientes de la
 * landing tuvo que llevar `@keyframes` en el CSS de la página. Comprueba, sin WordPress:
 *   M1  la plantilla Twig (con el Twig del núcleo) pinta el marcado de la marquesina clásica: región con nombre, dos
 *       listas (la copia con aria-hidden e inert), botón «Pausar», duración acotada, dirección, pausa al pasar el cursor
 *       por defecto, separador decorativo, acento sólo con un color válido, desvanecido, duración en el celular y
 *       separación; todo escapado;
 *   M2  la hoja: lo nuevo sólo con sus clases y «reducir movimiento» lo apaga; la clásica no cambia;
 *   M3  el registro: sólo con los elementos atómicos activos, con las dependencias de la clásica, y la clásica igual;
 *   M4  el aviso del MCP: si una herramienta de estilos descarta animation o @keyframes, la respuesta dice qué usar;
 *       si no, sale igual.
 */
namespace Elementor\Modules\AtomicWidgets\Elements\Base {
	abstract class Atomic_Widget_Base {
		public function get_style_depends() { return array( 'base-style' ); }
		public function get_script_depends() { return array( 'base-script' ); }
	}
	trait Has_Template {}
}

namespace Elementor {
	class Widget_Base {}
	class Plugin {
		public static $instance;
	}
}

namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: str_contains y compañía.
	define( 'ABSPATH', __DIR__ . '/' );
	set_error_handler(
		static function ( $severity, $message, $file, $line ) {
			throw new ErrorException( $message . ' en ' . basename( $file ) . ':' . $line, 0, $severity, $file, $line );
		}
	);
	$GLOBALS['am_fail'] = array();
	$GLOBALS['am_count'] = 0;
	function am_check( $ok, string $message ) {
		++$GLOBALS['am_count'];
		if ( ! $ok ) {
			$GLOBALS['am_fail'][] = $message;
		}
	}
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['am_filters'][ $hook ][] = $cb; }
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['am_filters'][ $hook ][] = $cb; }

	$root = dirname( __DIR__ ) . '/digitalisimo-elements';
	$tpl  = $root . '/modules/digitalisimo-widgets/atomic/atomic-marquee.html.twig';
	$cls  = $root . '/modules/digitalisimo-widgets/atomic/class-atomic-marquee.php';
	$hint = $root . '/modules/digitalisimo-mcp/class-motion-hint.php';
	am_check( is_file( $tpl ) && is_file( $cls ) && is_file( $hint ), 'M0 existen la marquesina atómica y el aviso del MCP' );
	if ( ! is_file( $tpl ) || ! is_file( $cls ) || ! is_file( $hint ) ) {
		echo "FALLA: falta la marquesina atómica o el aviso del MCP\n";
		exit( 1 );
	}

	// ---------------------------------------------------------------------------------------------------- M1 Twig.
	$core = $root . '/elementor';
	foreach ( array( 'polyfill-ctype/bootstrap.php', 'polyfill-mbstring/bootstrap.php', 'polyfill-php80/bootstrap.php', 'polyfill-php81/bootstrap.php', 'deprecation-contracts/function.php' ) as $file ) {
		if ( is_file( $core . '/vendor_prefixed/twig/symfony/' . $file ) ) {
			require_once $core . '/vendor_prefixed/twig/symfony/' . $file;
		}
	}
	spl_autoload_register(
		static function ( $class ) use ( $core ) {
			$prefixes = array(
				'ElementorDeps\\Twig\\'               => $core . '/vendor_prefixed/twig/twig/twig/src/',
				'ElementorDeps\\Symfony\\Polyfill\\Ctype\\'     => $core . '/vendor_prefixed/twig/symfony/polyfill-ctype/',
				'ElementorDeps\\Symfony\\Polyfill\\Mbstring\\'  => $core . '/vendor_prefixed/twig/symfony/polyfill-mbstring/',
				'ElementorDeps\\Symfony\\Polyfill\\Php80\\'     => $core . '/vendor_prefixed/twig/symfony/polyfill-php80/',
				'ElementorDeps\\Symfony\\Polyfill\\Php81\\'     => $core . '/vendor_prefixed/twig/symfony/polyfill-php81/',
			);
			foreach ( $prefixes as $prefix => $dir ) {
				if ( 0 === strpos( $class, $prefix ) ) {
					$file = $dir . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
					if ( is_file( $file ) ) {
						require_once $file;
					}
					return;
				}
			}
		}
	);
	// Como `Template_Renderer`: autoescape por nombre, plantillas registradas por nombre.
	$loader = new \ElementorDeps\Twig\Loader\ArrayLoader(
		array(
			'elementor/macros'              => file_get_contents( $core . '/modules/atomic-widgets/elements/base/_macros.html.twig' ),
			'digitalisimo/elements/marquee' => file_get_contents( $tpl ),
		)
	);
	$twig   = new \ElementorDeps\Twig\Environment( $loader, array( 'autoescape' => 'name', 'strict_variables' => false ) );
	$render = static function ( array $settings ) use ( $twig ) {
		return $twig->render(
			'digitalisimo/elements/marquee',
			array(
				'id'             => 'abc1234',
				'interaction_id' => 'abc1234',
				'type'           => 'e-digitalisimo-marquee',
				'settings'       => array_merge( array( 'classes' => array( 'e-abc1234-local' ) ), $settings ),
				'tag'            => 'div',
				'base_styles'    => array(),
			)
		);
	};

	$html = $render( array( 'items' => "PEÑOLES\r\n  TEC DE MONTERREY \n\n<script>alert(1)</script>\nY MUCHOS MÁS", 'duration' => 42 ) );
	am_check( 1 === substr_count( $html, 'role="region"' ) && str_contains( $html, 'aria-label="Marquesina"' ) && str_contains( $html, 'data-digi-marquee' ), 'M1 región con nombre y marca del script' );
	am_check( 2 === substr_count( $html, '<ul class="digi-marquee__list"' ) && 1 === substr_count( $html, 'aria-hidden="true" inert' ), 'M1 dos listas, la copia oculta e inerte' );
	am_check( 8 === substr_count( $html, '<li class="digi-marquee__item">' ), 'M1 cuatro elementos por lista (los renglones vacíos no cuentan): ' . substr_count( $html, '<li class="digi-marquee__item">' ) );
	am_check( str_contains( $html, '<span class="digi-marquee__text">TEC DE MONTERREY</span>' ) && str_contains( $html, '<span class="digi-marquee__text">PEÑOLES</span>' ), 'M1 cada renglón recortado (también con \\r\\n)' );
	am_check( ! str_contains( $html, '<script>' ) && str_contains( $html, '&lt;script&gt;alert(1)&lt;/script&gt;' ), 'M1 el texto sale escapado' );
	am_check( str_contains( $html, 'data-digi-marquee-toggle' ) && str_contains( $html, 'aria-pressed="false"' ) && str_contains( $html, '>Pausar<' ), 'M1 botón de pausa' );
	am_check( str_contains( $html, 'class="e-abc1234-local digi-marquee digi-marquee--left digi-marquee--hover-pause"' ), 'M1 clases: estilo local del MCP, izquierda y pausa al pasar el cursor por defecto · ' . ( preg_match( '/<div class="([^"]*)"/', $html, $m ) ? $m[1] : '' ) );
	am_check( str_contains( $html, 'style="--digi-marquee-duration:42s"' ), 'M1 duración de una vuelta' );
	am_check( ! str_contains( $html, 'digi-marquee__separator' ) && ! str_contains( $html, 'digi-marquee--fade' ) && ! str_contains( $html, 'accent' ) && ! str_contains( $html, 'digi-marquee__label' ), 'M1 sin separador, desvanecido, acento ni rótulo si no se piden' );

	$html = $render( array( 'items' => 'Uno', 'duration' => 1, 'direction' => 'right', 'pause-on-hover' => false ) );
	am_check( str_contains( $html, '--digi-marquee-duration:5s' ) && str_contains( $html, 'digi-marquee--right' ) && ! str_contains( $html, 'hover-pause' ), 'M1 duración mínima 5 s, derecha y sin pausa al pasar el cursor' );
	$html = $render( array( 'items' => 'Uno', 'duration' => 9999, 'direction' => 'arriba' ) );
	am_check( str_contains( $html, '--digi-marquee-duration:300s' ) && str_contains( $html, 'digi-marquee--left' ), 'M1 duración máxima 300 s; una dirección rara queda a la izquierda' );

	$html = $render( array( 'items' => "A\nB", 'label' => 'Han confiado <b>en</b>', 'separator' => '|', 'accent' => '#00FF99', 'accent-last' => true, 'fade-edges' => true, 'duration-mobile' => 26, 'gap' => 0 ) );
	am_check( str_contains( $html, 'aria-label="Han confiado &lt;b&gt;en&lt;/b&gt;"' ) && str_contains( $html, '<span class="digi-marquee__label">Han confiado &lt;b&gt;en&lt;/b&gt;</span>' ), 'M1 rótulo visible y nombre de la región, escapados' );
	am_check( 4 === substr_count( $html, '<span class="digi-marquee__separator" aria-hidden="true">|</span>' ), 'M1 separador decorativo tras cada elemento de las dos listas' );
	am_check( str_contains( $html, 'digi-marquee--fade' ) && str_contains( $html, 'digi-marquee--accent-last' ) && str_contains( $html, 'digi-marquee--mobile-duration' ), 'M1 desvanecido, acento en el último y duración en el celular' );
	am_check( str_contains( $html, 'style="--digi-marquee-duration:30s;--digi-marquee-duration-mobile:26s;--digi-marquee-gap:0px;--digi-marquee-accent:#00FF99"' ), 'M1 variables: duración, celular, separación 0 y acento · ' . ( preg_match( '/style="([^"]*)"/', $html, $m ) ? $m[1] : '' ) );
	foreach ( array( 'red;background:url(x)', 'expression(alert(1))', '#12345g', 'var(--a);x:y', 'rgb(1,2,3);a:b' ) as $bad ) {
		$html = $render( array( 'items' => 'A', 'accent' => $bad, 'accent-last' => true ) );
		am_check( ! str_contains( $html, 'accent' ), 'M1 un acento que no es un color se ignora: ' . $bad );
	}
	foreach ( array( '#0f9', 'rgba(0, 255, 153, .5)', 'hsl(150 100% 50%)', 'var(--e-global-color-accent)' ) as $good ) {
		$html = $render( array( 'items' => 'A', 'accent' => $good ) );
		am_check( str_contains( $html, '--digi-marquee-accent:' . htmlspecialchars( $good, ENT_QUOTES ) ) && ! str_contains( $html, 'accent-last' ), 'M1 acento válido sin pintar el último si no se pide: ' . $good );
	}
	$html = $render( array( 'items' => 'A', 'attributes' => 'data-x="1"', '_cssid' => 'bf-marquesina' ) );
	am_check( str_contains( $html, ' id="bf-marquesina"' ) && str_contains( $html, 'data-x="1"' ), 'M1 ID y atributos propios del elemento' );
	$html = $render( array( 'items' => '' ) );
	am_check( 0 === substr_count( $html, '<li' ) && 2 === substr_count( $html, '<ul' ), 'M1 sin elementos, listas vacías y sin error' );

	// El mismo patrón de color en la clase (validación al guardar) y en la plantilla (al pintar).
	$class_src = file_get_contents( $cls );
	preg_match( "/const COLOR_PATTERN = '([^']+)';/", $class_src, $pc );
	preg_match( "/matches '([^']+)'/", file_get_contents( $tpl ), $pt );
	am_check( isset( $pc[1], $pt[1] ) && str_replace( '\\\\', '\\', $pt[1] ) === $pc[1], 'M1 el patrón de color de la plantilla es el de la clase' );

	// ---------------------------------------------------------------------------------------------------- M2 hoja.
	$css = file_get_contents( $root . '/assets/css/marquee.css' );
	foreach ( array( '.digi-marquee__track{display:flex;width:max-content;animation:digi-marquee var(--digi-marquee-duration) linear infinite}', '.digi-marquee--hover-pause:hover .digi-marquee__track,.digi-marquee:focus-within .digi-marquee__track,.digi-marquee.is-paused .digi-marquee__track{animation-play-state:paused}', '@keyframes digi-marquee{to{transform:translateX(-50%)}}' ) as $rule ) {
		am_check( str_contains( $css, $rule ), 'M2 la regla de la clásica sigue igual: ' . substr( $rule, 0, 50 ) );
	}
	am_check( (bool) preg_match( '/\.digi-marquee--fade \.digi-marquee__viewport\{[^}]*mask-image:linear-gradient/', $css ), 'M2 desvanecido sólo con su clase' );
	am_check( (bool) preg_match( '/@media \(max-width:767px\)\{\.digi-marquee--mobile-duration \.digi-marquee__track\{animation-duration:var\(--digi-marquee-duration-mobile/', $css ), 'M2 duración en el celular sólo con su clase' );
	am_check( (bool) preg_match( '/@media \(prefers-reduced-motion:reduce\)\{[^@]*\.digi-marquee__track\{animation:none\}[^@]*\.digi-marquee--fade \.digi-marquee__viewport\{-webkit-mask-image:none;mask-image:none\}[^@]*\.digi-marquee__list\[aria-hidden="true"\],\.digi-marquee__toggle\{display:none\}/', $css ), 'M2 reducir movimiento: sin animación, sin desvanecido, sin copia ni botón' );
	am_check( str_contains( $css, '.digi-marquee__separator{' ) && str_contains( $css, '.digi-marquee--accent-last .digi-marquee__item:last-child .digi-marquee__text{' ), 'M2 separador y acento del último' );
	foreach ( preg_split( '/(?<=\})/', $css ) as $rule ) {
		if ( str_contains( $rule, 'accent' ) || str_contains( $rule, 'separator' ) || str_contains( $rule, 'mobile-duration' ) || str_contains( $rule, '--fade' ) ) {
			am_check( (bool) preg_match( '/digi-marquee--(fade|accent-last|mobile-duration)|digi-marquee__separator|mask-image:none/', $rule ), 'M2 regla nueva acotada a una clase nueva: ' . trim( $rule ) );
		}
	}

	// ---------------------------------------------------------------------------------------------------- M3 registro.
	require_once $root . '/modules/digitalisimo-widgets/class-registry.php';
	$manager = new class() {
		public $registered = array();
		public function register( $widget ) { $this->registered[] = $widget; }
		public function get_widget_types( $id = null ) { return null; }
	};
	\Elementor\Plugin::$instance = (object) array( 'experiments' => new class() {
		public $active = false;
		public function is_feature_active( $name ) { return 'e_atomic_elements' === $name && $this->active; }
	} );
	am_check( false === \Digitalisimo\Elements\Widget_Registry::atomic_widgets( $manager ) && ! $manager->registered && ! class_exists( '\\Digitalisimo\\Elements\\Atomic_Marquee', false ), 'M3 sin elementos atómicos activos no se registra ni se carga' );
	\Elementor\Plugin::$instance->experiments->active = true;
	am_check( true === \Digitalisimo\Elements\Widget_Registry::atomic_widgets( $manager ) && 1 === count( $manager->registered ), 'M3 con elementos atómicos se registra la marquesina' );
	$widget = $manager->registered[0] ?? null;
	am_check( $widget instanceof \Digitalisimo\Elements\Atomic_Marquee && 'e-digitalisimo-marquee' === \Digitalisimo\Elements\Atomic_Marquee::get_element_type() && 'Marquesina' === $widget->get_title(), 'M3 tipo e-digitalisimo-marquee y título' );
	am_check( $widget && in_array( 'digitalisimo-marquee', $widget->get_style_depends(), true ) && in_array( 'base-style', $widget->get_style_depends(), true ) && in_array( 'digitalisimo-marquee', $widget->get_script_depends(), true ) && in_array( 'base-script', $widget->get_script_depends(), true ), 'M3 hoja y script de la clásica, más los del núcleo' );
	am_check( str_contains( \Digitalisimo\Elements\Atomic_Marquee::$widget_description, 'prefers-reduced-motion' ) && str_contains( \Digitalisimo\Elements\Atomic_Marquee::$widget_description, '@keyframes' ), 'M3 la descripción para el MCP dice cuándo usarla' );
	foreach ( array( "'items'", "'separator'", "'accent'", "'accent-last'", "'duration'", "'duration-mobile'", "'direction'", "'gap'", "'pause-on-hover'", "'fade-edges'", "'attributes'", "'classes'" ) as $prop ) {
		am_check( str_contains( $class_src, $prop . ' ' ) || str_contains( $class_src, $prop . '=' ) || preg_match( '/' . preg_quote( $prop, '/' ) . '\s*=>/', $class_src ), 'M3 prop declarada: ' . $prop );
	}
	$classic = file_get_contents( $root . '/modules/digitalisimo-widgets/class-marquee.php' );
	am_check( str_contains( $classic, "return 'digitalisimo-marquee';" ) && ! str_contains( $classic, 'separator' ), 'M3 la marquesina clásica no cambia' );

	// ---------------------------------------------------------------------------------------------------- M4 MCP.
	require_once $hint;
	\Digitalisimo\Elements\Mcp\Motion_Hint::init();
	$wrap = static function ( $name, $result ) {
		$args = \Digitalisimo\Elements\Mcp\Motion_Hint::wrap_ability( array( 'execute_callback' => static function () use ( $result ) { return $result; } ), $name );
		return call_user_func( $args['execute_callback'], array() );
	};
	$warn   = array( 'success' => true, 'warnings' => array( '[bf-pista] These CSS properties or values are not supported and were not saved: animation:bf-marquesina 42s linear infinite;' ) );
	$out    = $wrap( 'elementor/build-composition', $warn );
	am_check( 2 === count( $out['warnings'] ) && str_contains( $out['warnings'][1], 'e-digitalisimo-marquee' ) && $out['warnings'][0] === $warn['warnings'][0], 'M4 build-composition: la pista va al final de los avisos' );
	$out = $wrap( 'elementor/manage-elements', array( 'results' => array( array( 'warnings' => array( 'Estas propiedades no se guardaron: @keyframes x{to{transform:none}}' ) ) ) ) );
	am_check( isset( $out['hints'][0] ) && str_contains( $out['hints'][0], 'prefers-reduced-motion' ), 'M4 manage-elements: sin warnings de primer nivel, la pista en hints' );
	$out = $wrap( 'elementor/manage-classes', array( 'warnings' => array( 'not saved: -webkit-animation-duration: 2s' ) ) );
	am_check( 2 === count( $out['warnings'] ), 'M4 también animation-* con prefijo' );
	$annotate = static function ( $name, $result, $input ) {
		$args = \Digitalisimo\Elements\Mcp\Motion_Hint::wrap_ability( array( 'execute_callback' => static function ( $in ) use ( $result ) { return $result; } ), $name );
		return call_user_func( $args['execute_callback'], $input );
	};
	$ok_result = array( 'success' => true, 'post_id' => 9 );
	$out       = $annotate( 'elementor/build-composition', $ok_result, array( 'style' => array( 'Texto' => 'color: red; animation: x 3s linear infinite;' ) ) );
	am_check( isset( $out['hints'][0] ) && str_contains( $out['hints'][0], 'e-digitalisimo-marquee' ), 'M4 si el CSS pedido trae animation (aunque se guarde sin aviso), la respuesta lo dice' );
	$out = $annotate( 'elementor/manage-classes', $ok_result, array( 'operations' => array( array( 'action' => 'create', 'css' => '@keyframes x { to { opacity: 0 } }' ) ) ) );
	am_check( isset( $out['hints'][0] ), 'M4 @keyframes en el CSS de una clase' );
	$out = $annotate( 'elementor/build-composition', $ok_result, array( 'element_config' => array( 'Texto' => array( 'paragraph' => 'animation: así se escribe' ) ), 'style' => array( 'Texto' => 'color: red;' ) ) );
	am_check( $out === $ok_result, 'M4 un texto que dice animation fuera del CSS no recibe pista' );
	$plain = array( 'success' => true, 'warnings' => array( 'not saved: transition-animation-thing; content: "animation"' ) );
	am_check( $wrap( 'elementor/build-composition', $plain ) === $plain, 'M4 un aviso que sólo nombra la palabra no recibe pista' );
	$clean = array( 'success' => true, 'warnings' => array( 'not saved: mix-blend-mode: plus-darker' ) );
	am_check( $wrap( 'elementor/build-composition', $clean ) === $clean, 'M4 sin movimiento descartado la respuesta sale igual' );
	am_check( $wrap( 'elementor/get-page-structure', $warn ) === $warn, 'M4 otras herramientas no se envuelven' );
	$error = new \stdClass();
	am_check( $wrap( 'elementor/build-composition', $error ) === $error, 'M4 un error sale igual' );
	$twice = \Digitalisimo\Elements\Mcp\Motion_Hint::annotate( \Digitalisimo\Elements\Mcp\Motion_Hint::annotate( $warn ) );
	am_check( 2 === count( $twice['warnings'] ), 'M4 la pista no se repite' );
	am_check( isset( $GLOBALS['am_filters']['wp_register_ability_args'] ), 'M4 se engancha a wp_register_ability_args' );
	$module = file_get_contents( $root . '/modules/digitalisimo-mcp/class-module.php' );
	am_check( str_contains( $module, "require_once __DIR__ . '/class-motion-hint.php';" ) && str_contains( $module, 'Motion_Hint::init();' ), 'M4 el módulo del MCP lo carga' );

	if ( $GLOBALS['am_fail'] ) {
		foreach ( $GLOBALS['am_fail'] as $failure ) {
			echo 'FALLA: ' . $failure . "\n";
		}
		echo count( $GLOBALS['am_fail'] ) . ' de ' . $GLOBALS['am_count'] . " comprobaciones fallaron.\n";
		exit( 1 );
	}
	echo 'Marquesina atómica: ' . $GLOBALS['am_count'] . " comprobaciones correctas.\n";
}
