<?php
/**
 * Sustitución de los widgets de PRO (C0 de docs/PRO_WIDGETS_OBJECTIVE.md; modules/digitalisimo-convert/):
 * - el catálogo tiene exactamente los widgets de PRO que registra Elements (los nombres de `get_name()` de cada
 *   `modules/<pro>/widgets/`), así que una sincronización con un widget nuevo no pasa en silencio;
 * - el traductor: defaults (también parciales), `keep`, `rename` con dispositivos, `__dynamic__` y `__globals__`,
 *   grupos, valores, fijos, comunes, `drop` y `approx` sólo si cambiaron, claves no declaradas a «Pendiente de
 *   revisar», repetidores con su `_id`, clases del CSS personalizado y la marca que impide traducir dos veces;
 * - la conversión de árboles (cualquier profundidad, mismo `id`, sólo mapas listos salvo borradores) y el recuento;
 * - el panel oculta sólo los mapas listos con `panel => hide` cuyo widget propio está registrado;
 * - cada mapa de `maps/` declara todos los controles de su widget de PRO y apunta a un widget propio existente.
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Plugin {
		public static $instance;
		public $widgets_manager;
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['cv'] = array( 'filters' => array(), 'locale' => array( 'current' => 'en_US', 'stack' => array(), 'switches' => 0 ) );
	// Idioma (c7#8 de docs/pro/c7.md): el sitio en es_ES y quien convierte en en_US. Como el de WordPress (que filtra
	// también `determine_locale()`), `switch_to_locale()` no hace nada si ya se está en ese idioma.
	function get_locale() { return 'es_ES'; }
	function switch_to_locale( $locale ) {
		if ( $locale === $GLOBALS['cv']['locale']['current'] ) {
			return false;
		}
		$GLOBALS['cv']['locale']['stack'][] = $GLOBALS['cv']['locale']['current'];
		$GLOBALS['cv']['locale']['current'] = $locale;
		++$GLOBALS['cv']['locale']['switches'];
		return true;
	}
	function restore_previous_locale() { $GLOBALS['cv']['locale']['current'] = array_pop( $GLOBALS['cv']['locale']['stack'] ); return $GLOBALS['cv']['locale']['current']; }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['cv']['filters'][ $hook ][] = $cb; }
	function check_cv( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	function get_post_meta( $id, $key = "", $single = false ) { return $GLOBALS['cv']['meta'][ (int) $id ][ $key ] ?? ""; }

	/** Archivos de PRO de los que salen los controles de un widget: él, su clase padre de carrusel y el trait. */
	function pro_sources( string $file, string $elements ): array {
		$php   = (string) file_get_contents( $file );
		$files = array( $file );
		if ( preg_match( '/namespace ElementorPro\\\\Modules\\\\Carousel\\\\Widgets;/', $php ) && preg_match( '/\bextends\s+Base\b/', $php ) ) {
			$files[] = $elements . 'modules/carousel/widgets/base.php';
		}
		if ( preg_match( '/\buse\s+Base_Carousel_Trait\s*;/', $php ) ) {
			$files[] = $elements . 'base/base-carousel-trait.php';
		}
		// C7: los padres de los widgets de WooCommerce (columnas de `Base_Widget`, cuadrícula de `Products_Base` y los de
		// archivo que heredan de `Products`). La presentación clásica de `wc-products` prefija sus controles con su id
		// (`classic_columns`): la cubre `tests/elements-pro-woo-products.php`.
		if ( preg_match( '/namespace ElementorPro\\\\Modules\\\\Woocommerce\\\\Widgets;/', $php ) ) {
			$woo = $elements . 'modules/woocommerce/';
			if ( preg_match( '/\bextends\s+(Products_Base|Products)\b/', $php ) ) {
				$files[] = $woo . 'widgets/products-base.php';
			}
			if ( preg_match( '/\bextends\s+Products\b/', $php ) ) {
				$files[] = $woo . 'widgets/products.php';
			}
			$all = '';
			foreach ( $files as $source ) { $all .= (string) file_get_contents( $source ); }
			if ( preg_match( '/->add_columns_responsive_control\(\)/', $all ) ) {
				$files[] = $woo . 'widgets/base-widget.php';
			}
		}
		// Botones de pago de PRO (C9): heredan los controles de su base y del trait del botón del núcleo.
		if ( preg_match( '/\bextends\s+Payment_Button\b/', $php ) ) {
			$files[] = $elements . 'modules/payments/classes/payment-button.php';
			$files[] = $elements . 'elementor/includes/widgets/traits/button-trait.php';
		}
		return array_values( array_unique( $files ) );
	}

	/** Valor de una concatenación PHP de literales y variables (`'a_' . $x . '_b'`) para cada combinación de valores. */
	function pro_concat( string $expr, array $values ): array {
		$parts = array_map( 'trim', preg_split( '/\s*\.\s*(?=(?:[^\']*\'[^\']*\')*[^\']*$)/', trim( $expr ) ) );
		$out   = array( '' );
		foreach ( $parts as $part ) {
			if ( preg_match( "/^'([a-z0-9_-]*)'$/", $part, $m ) ) {
				$opts = array( $m[1] );
			} elseif ( preg_match( '/^\$([a-z_]+)$/', $part, $m ) && ! empty( $values[ $m[1] ] ) ) {
				$opts = $values[ $m[1] ];
			} else {
				throw new RuntimeException( "Nombre de control armado sin valores conocidos: $expr" );
			}
			$next = array();
			foreach ( $out as $prefix ) {
				foreach ( $opts as $opt ) {
					$next[] = $prefix . $opt;
				}
			}
			$out = $next;
		}
		return $out;
	}

	/**
	 * Nombres de los controles (y de los grupos) de un widget de PRO, con los heredados y los concatenados expandidos.
	 * Los valores de cada variable salen de `foreach ( [ 'a', 'b' ] as $var )`, de los literales que recibe en las
	 * llamadas al método que la declara como parámetro y de las asignaciones `$var = <concatenación>;`.
	 */
	function pro_controls( string $file, string $elements ): array {
		$php = '';
		foreach ( pro_sources( $file, $elements ) as $source ) {
			$php .= "\n" . (string) file_get_contents( $source );
		}
		// Controles que PRO quita con `remove_control()` (la base de pagos quita los del botón del núcleo; Stripe, los de la
		// base que no usa): no existen en el widget salvo que el mismo archivo los vuelva a añadir después de quitarlos.
		$removed = array();
		foreach ( pro_sources( $file, $elements ) as $source ) {
			$text = (string) file_get_contents( $source );
			preg_match_all( "/->remove_(?:responsive_)?control\(\s*'([a-z0-9_]+)'\s*\)/", $text, $quits, PREG_OFFSET_CAPTURE );
			foreach ( $quits[1] as $quit ) {
				if ( ! preg_match( "/->add_(?:responsive_)?control\(\s*'" . $quit[0] . "'\s*,/", substr( $text, $quit[1] ) ) ) {
					$removed[] = $quit[0];
				}
			}
		}
		// Valores de `$var = 'literal';` y, sólo para esas variables, cadenas con variables (`"prefijo_$lado"`, C8) como
		// concatenación. Las demás cadenas dobles se dejan como estaban: una variable que toma valores distintos en métodos
		// distintos (`$tab_id` del Buscador de C3: `hover` en uno, `focus` en otro) daría nombres cruzados que no existen.
		$values = array();
		preg_match_all( "/\\$([a-z_]+)\s*=\s*'([a-z0-9_-]+)'\s*;/", $php, $literals, PREG_SET_ORDER );
		foreach ( $literals as $literal ) {
			$values[ $literal[1] ][] = $literal[2];
		}
		$php = (string) preg_replace_callback( '/"([a-z0-9_]*)\$([a-z_]+)([a-z0-9_]*)"/', static function ( $m ) use ( $values ) { return isset( $values[ $m[2] ] ) ? "'" . $m[1] . "' . \$" . $m[2] . " . '" . $m[3] . "'" : $m[0]; }, $php );
		preg_match_all( "/foreach\s*\(\s*\[\s*((?:'[a-z0-9_-]+'\s*,?\s*)+)\]\s*as\s*\\$([a-z_]+)\s*\)/", $php, $loops, PREG_SET_ORDER );
		foreach ( $loops as $loop ) {
			preg_match_all( "/'([a-z0-9_-]+)'/", $loop[1], $lits );
			$values[ $loop[2] ] = array_merge( $values[ $loop[2] ] ?? array(), $lits[1] );
		}
		// `foreach ( $lista as $clave => $valor )` sobre un arreglo literal con claves (`$query_type_strings` de
		// `modules/woocommerce/widgets/products.php`, C7): la variable toma las claves del arreglo.
		preg_match_all( "/\\$([a-z_]+)\s*=\s*\[(.*?)\];/s", $php, $assoc, PREG_SET_ORDER );
		$lists = array();
		foreach ( $assoc as $list ) {
			if ( preg_match_all( "/^\s*'([a-z0-9_-]+)'\s*=>/m", $list[2], $keys ) ) {
				$lists[ $list[1] ] = $keys[1];
			}
		}
		preg_match_all( "/foreach\s*\(\s*\\$([a-z_]+)\s+as\s+\\$([a-z_]+)\s*=>/", $php, $keyed, PREG_SET_ORDER );
		foreach ( $keyed as $loop ) {
			if ( isset( $lists[ $loop[1] ] ) ) {
				$values[ $loop[2] ] = array_merge( $values[ $loop[2] ] ?? array(), $lists[ $loop[1] ] );
			}
		}
		preg_match_all( '/function\s+([a-z_]+)\s*\(([^)]*)\)/', $php, $functions, PREG_SET_ORDER );
		foreach ( $functions as $function ) {
			preg_match_all( '/\$([a-z_]+)/', $function[2], $params );
			foreach ( $params[1] as $position => $param ) {
				preg_match_all( '/->' . $function[1] . '\(([^;]*)\);/', $php, $calls );
				foreach ( $calls[1] as $args ) {
					$args = array_map( 'trim', explode( ',', $args ) );
					if ( isset( $args[ $position ] ) && preg_match( "/^'([a-z0-9_-]+)'$/", $args[ $position ], $lit ) ) {
						$values[ $param ][] = $lit[1];
					}
				}
			}
		}
		preg_match_all( "/\\$([a-z_]+)\s*=\s*((?:'[a-z0-9_-]*'|\\$[a-z_]+)(?:\s*\.\s*(?:'[a-z0-9_-]*'|\\$[a-z_]+))+)\s*;/", $php, $assigns, PREG_SET_ORDER );
		foreach ( $assigns as $assign ) {
			try {
				$values[ $assign[1] ] = pro_concat( $assign[2], $values );
			} catch ( RuntimeException $unknown ) {
				continue; // Una asignación que no da nombre a un control puede usar variables sin valores literales.
			}
		}
		$values = array_map( 'array_unique', $values );
		// Nombres que PRO arma con datos del sitio (post-navigation: `$post_type . '_taxonomy'`, uno por tipo de entrada
		// público con taxonomías): se comprueban con los de una instalación limpia.
		$values += array( 'post_type' => array( 'post' ) );
		$names  = array();
		preg_match_all( "/->add_(?:responsive_)?control\(\s*((?:'[a-z0-9_]*'|\\$[a-z_]+)(?:\s*\.\s*(?:'[a-z0-9_]*'|\\$[a-z_]+))*)\s*,/", $php, $controls );
		preg_match_all( "/->add_group_control\([^;]*?'name'\s*=>\s*((?:'[a-z0-9_]*'|\\$[a-z_]+)(?:\s*\.\s*(?:'[a-z0-9_]*'|\\$[a-z_]+))*)\s*,/s", $php, $grouped );
		foreach ( array_merge( $controls[1], $grouped[1] ) as $expr ) {
			$names = array_merge( $names, pro_concat( $expr, $values ) );
		}
		return array_values( array_diff( array_unique( $names ), $removed ) );
	}

	$elements = __DIR__ . '/../digitalisimo-elements/';
	require $elements . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Convert\Catalog;
	use Digitalisimo\Elements\Convert\Translator;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Panel;

	// 1. Catálogo frente al código de PRO.
	$found = array();
	$files = array();
	foreach ( glob( $elements . 'modules/*/widgets/*.php' ) as $file ) {
		if ( 0 === strpos( basename( dirname( $file, 2 ) ), 'digitalisimo-' ) ) {
			continue;
		}
		$php = (string) file_get_contents( $file );
		if ( preg_match( '/abstract\s+class/', $php ) || ! preg_match( "/function get_name\(\)[^{]*\{(?:\s*\/\/[^\n]*)*\s*return\s+'([a-z0-9-]+)'/", $php, $m ) ) {
			continue;
		}
		// Un widget propio que vive en un módulo derivado de PRO (el widget global propio, `digitalisimo-global`, en
		// `modules/global-widget/`) no es de PRO.
		if ( 0 === strpos( $m[1], 'digitalisimo-' ) ) {
			continue;
		}
		$found[ $m[1] ] = basename( dirname( $file, 2 ) );
		$files[ $m[1] ] = $file;
	}
	ksort( $found );
	$catalog = array();
	foreach ( Catalog::WIDGETS as $name => $row ) {
		$catalog[ $name ] = $row[1];
	}
	ksort( $catalog );
	check_cv( count( $found ) >= 100 && $found === $catalog, 'El catálogo debe tener exactamente los widgets de PRO del código (nombre y módulo). Sobran: ' . implode( ', ', array_keys( array_diff_key( $catalog, $found ) ) ) . ' · faltan: ' . implode( ', ', array_keys( array_diff_key( $found, $catalog ) ) ) );
	check_cv( 'C2' === Catalog::family( 'blockquote' ) && 'C7' === Catalog::family( 'woocommerce-cart' ) && '' === Catalog::family( 'heading' ) && ! Catalog::is_pro( 'digitalisimo-blockquote' ), 'Familias del catálogo; los widgets del núcleo y los propios no son de PRO.' );
	foreach ( Catalog::WIDGETS as $name => $row ) {
		check_cv( isset( Catalog::FAMILIES[ $row[0] ] ), "$name: familia desconocida {$row[0]}" );
	}

	// 2. Traductor.
	$map = array(
		'source'    => 'blockquote',
		'target'    => 'digitalisimo-prueba',
		'defaults'  => array( 'skin' => 'border', 'link' => array( 'url' => '', 'is_external' => '' ), 'tag' => 'h2', 'gone' => 'x', 'near' => 'a' ),
		'keep'      => array( 'link' ),
		'rename'    => array( 'skin' => 'style', 'gap' => 'spacing', 'tag' => 'html_tag' ),
		'groups'    => array( 'content_typography' => 'text_typography' ),
		'values'    => array( 'style' => array( 'border' => 'line' ), 'html_tag' => static function ( $value ) { return 'h1' === $value ? 'h2' : $value; } ),
		'set'       => array( 'fixed' => 'yes' ),
		'drop'      => array( 'gone' => 'El widget propio no tiene esta opción.' ),
		'approx'    => array( 'near' => 'Se aproxima con el estilo más cercano.' ),
		'classes'   => array( '.elementor-blockquote' => '.digi-quote', '.elementor-blockquote__content' => '.digi-quote__text' ),
		'repeaters' => array( 'items' => array( 'target' => 'rows', 'defaults' => array( 'label' => 'Uno' ), 'rename' => array( 'label' => 'text' ), 'drop' => array( 'old' => 'Sin equivalente.' ) ) ),
	);
	$source = array(
		'gap'            => array( 'size' => 4, 'unit' => 'px' ),
		'gap_tablet'     => array( 'size' => 2, 'unit' => 'px' ),
		'gap_mobile'     => array( 'size' => 1, 'unit' => 'px' ),
		'link'           => array( 'url' => 'https://a.test/' ),
		'tag'            => 'h1',
		'content_typography_typography' => 'custom',
		'content_typography_font_size_tablet' => array( 'size' => 20, 'unit' => 'px' ),
		'near'           => 'b',
		'mystery'        => 'zzz',
		'_margin'        => array( 'top' => '10' ),
		'hide_mobile'    => 'hidden-mobile',
		'motion_fx_motion_fx_scrolling' => 'yes',
		'digitalisimo_tooltip' => 'yes',
		'custom_css'     => 'selector .elementor-blockquote__content{color:red} selector .elementor-blockquote{margin:0} .elementor-blockquote-x{}',
		'__dynamic__'    => array( 'gap' => '[elementor-tag x]', 'mystery' => '[elementor-tag y]', 'gone' => '[elementor-tag z]' ),
		'__globals__'    => array( 'content_typography_typography' => 'globals/typography?id=primary', '_background_color' => 'globals/colors?id=accent' ),
		'items'          => array( array( '_id' => 'r1', 'label' => 'Hola', 'old' => 'q' ), array( '_id' => 'r2' ) ),
	);
	list( $out, $codes ) = Translator::translate( $source, $map );
	$by = static function ( $code ) use ( $codes ) {
		return array_values( array_map( static function ( $c ) { return $c['key']; }, array_filter( $codes, static function ( $c ) use ( $code ) { return $c['code'] === $code; } ) ) );
	};
	check_cv( 'line' === $out['style'] && ! isset( $out['skin'] ), 'Un default no guardado se completa, se renombra y se convierte su valor.' );
	check_cv( array( 'size' => 4, 'unit' => 'px' ) === $out['spacing'] && array( 'size' => 2, 'unit' => 'px' ) === $out['spacing_tablet'] && array( 'size' => 1, 'unit' => 'px' ) === $out['spacing_mobile'] && ! isset( $out['gap'] ), 'rename alcanza cada dispositivo.' );
	check_cv( array( 'url' => 'https://a.test/', 'is_external' => '' ) === $out['link'], 'keep conserva la clave y completa las partes que faltan de un control de valor múltiple.' );
	check_cv( 'h2' === $out['html_tag'], 'Un valor por función recibe el valor ya renombrado.' );
	check_cv( 'custom' === $out['text_typography_typography'] && array( 'size' => 20, 'unit' => 'px' ) === $out['text_typography_font_size_tablet'], 'Los grupos pasan con todas sus subclaves.' );
	check_cv( 'yes' === $out['fixed'] && array( 'top' => '10' ) === $out['_margin'] && 'hidden-mobile' === $out['hide_mobile'] && 'yes' === $out['motion_fx_motion_fx_scrolling'] && 'yes' === $out['digitalisimo_tooltip'], 'Fijos y comunes (Avanzado, visibilidad, movimiento, rasgos propios) pasan.' );
	check_cv( array( 'spacing' => '[elementor-tag x]' ) === $out['__dynamic__'] && array( 'text_typography_typography' => 'globals/typography?id=primary', '_background_color' => 'globals/colors?id=accent' ) === $out['__globals__'], '__dynamic__ y __globals__ siguen a su clave renombrada o común.' );
	check_cv( ! isset( $out['gone'] ) && ! isset( $out['mystery'] ) && ! isset( $out['near'] ), 'Lo descartado, lo aproximado sin destino y lo no declarado no pasan.' );
	check_cv( array( 'gone', 'items.old' ) === $by( 'pro.dropped' ) && array( 'near' ) === $by( 'pro.approx' ) && array( 'mystery', 'mystery' ) === $by( 'pro.unmapped' ), 'Códigos: descartado sólo con valor (aquí, dinámico), aproximado si cambió, no declarado (valor y dinámico) a revisar.' );
	check_cv( 'selector .digi-quote__text{color:red} selector .digi-quote{margin:0} .elementor-blockquote-x{}' === $out['custom_css'] && array( 'custom_css' ) === $by( 'pro.css' ), 'El CSS personalizado cambia sólo las clases completas, la más larga primero.' );
	check_cv( array( array( 'text' => 'Hola', '_id' => 'r1' ), array( 'text' => 'Uno', '_id' => 'r2' ) ) === $out['rows'] && ! isset( $out['items'] ) && in_array( 'items.old', $by( 'pro.dropped' ), true ), 'Repetidores: filas traducidas con su _id y sus defaults, y sus descartes con el prefijo del repetidor.' );
	check_cv( 'blockquote' === $out[ Translator::MARKER ] && array( $out, array() ) === Translator::translate( $out, $map ), 'Lo ya traducido lleva la marca y no se traduce otra vez.' );
	list( , $quiet ) = Translator::translate( array( 'gone' => 'x', 'near' => 'a' ), $map );
	check_cv( array() === $quiet, 'Un descarte o una aproximación con el valor por defecto no se informan.' );

	// `drop` con el sufijo de un dispositivo: descarta sólo ese dispositivo aunque `keep` o `rename` conserven la clave
	// (también en `__dynamic__`), con su código en «Descartado» sólo si cambió del valor por defecto.
	$device_map = array(
		'source'   => 'blockquote',
		'target'   => 'digitalisimo-prueba',
		'defaults' => array( 'align_mobile' => '' ),
		'keep'     => array( 'align' ),
		'rename'   => array( 'gap' => 'spacing' ),
		'drop'     => array( 'align_tablet' => 'PRO no la aplicaba.', 'align_mobile' => 'PRO no la aplicaba.', 'gap_mobile' => 'Sin equivalente en el móvil.' ),
	);
	list( $dv, $dv_codes ) = Translator::translate( array( 'align' => 'left', 'align_tablet' => 'right', 'align_laptop' => 'center', 'gap' => 3, 'gap_tablet' => 2, 'gap_mobile' => 1, '__dynamic__' => array( 'gap_mobile' => '[t]', 'gap_tablet' => '[u]' ) ), $device_map );
	check_cv( 'left' === $dv['align'] && 'center' === $dv['align_laptop'] && ! array_key_exists( 'align_tablet', $dv ) && ! array_key_exists( 'align_mobile', $dv ) && 3 === $dv['spacing'] && 2 === $dv['spacing_tablet'] && ! array_key_exists( 'spacing_mobile', $dv ) && ! array_key_exists( 'gap_mobile', $dv ) && array( 'spacing_tablet' => '[u]' ) === $dv['__dynamic__'], 'drop por dispositivo: sólo ese dispositivo se descarta; los demás siguen a keep y rename: ' . json_encode( $dv ) );
	$dv_keys = array_map( static function ( $c ) { return $c['code'] . ':' . $c['key']; }, $dv_codes );
	sort( $dv_keys );
	check_cv( array( 'pro.dropped:align_tablet', 'pro.dropped:gap_mobile', 'pro.dropped:gap_mobile' ) === $dv_keys, 'Cada descarte por dispositivo con su clave completa (el del móvil también por su valor dinámico): ' . implode( ', ', $dv_keys ) );
	list( , $dv_quiet ) = Translator::translate( array( 'align' => 'left', 'align_mobile' => '' ), $device_map );
	check_cv( array() === $dv_quiet, 'Un dispositivo descartado con el valor por defecto no se informa.' );

	// 3. Árboles y recuento.
	Registry::set( 'blockquote', $map + array( 'status' => 'draft' ) );
	$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'elements' => array(
		array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'blockquote', 'settings' => array( 'tag' => 'h3' ) ),
		array( 'id' => 'c2', 'elType' => 'container', 'elements' => array( array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'blockquote', 'settings' => array() ), array( 'id' => 'w3', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array() ) ) ),
		// 'global' nunca tiene mapa: el ejemplo de lo que queda sin convertir no cambia al llegar las etapas.
		array( 'id' => 'w4', 'elType' => 'widget', 'widgetType' => 'global', 'settings' => array() ),
	) ) );
	check_cv( $tree === Converter::tree( $tree ), 'Un mapa en borrador no convierte nada fuera de las pruebas.' );
	$converted = Converter::tree( $tree, $log, true );
	check_cv( 'digitalisimo-prueba' === $converted[0]['elements'][0]['widgetType'] && 'w1' === $converted[0]['elements'][0]['id'] && 'h3' === $converted[0]['elements'][0]['settings']['html_tag'] && 'digitalisimo-prueba' === $converted[0]['elements'][1]['elements'][0]['widgetType'] && 'heading' === $converted[0]['elements'][1]['elements'][1]['widgetType'] && 'global' === $converted[0]['elements'][2]['widgetType'], 'tree() convierte a cualquier profundidad, conserva ids y deja lo que no tiene mapa.' );
	check_cv( 2 === count( $log ) && 'w2' === $log[1]['element'] && 'blockquote' === $log[0]['source'], 'El registro dice qué elemento cambió y de qué a qué.' );
	check_cv( array( 'blockquote' => 2, 'global' => 1 ) === Converter::count( $tree ) && array( 'global' => 1 ) === Converter::count( $converted ), 'count() cuenta los widgets de PRO a cualquier profundidad.' );

	// 4. Panel.
	Registry::set( 'blockquote', $map + array( 'status' => 'ready', 'panel' => 'hide' ) );
	check_cv( 1 === count( $GLOBALS['cv']['filters']['elementor/document/config'] ?? array() ), 'El módulo engancha la configuración del documento.' );
	$manager = new class() { public $known = array(); public function get_widget_types( $name = null ) { return in_array( $name, $this->known, true ) ? new stdClass() : null; } };
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->widgets_manager = $manager;
	$glob_known = array_keys( Registry::all() );
	check_cv( ! in_array( 'blockquote', Panel::hidden(), true ), 'Sin el widget propio registrado no se oculta el de PRO.' );
	$manager->known = array( 'digitalisimo-prueba' );
	$config = Panel::document_config( array( 'widgets' => array( 'blockquote' => array( 'title' => 'Q' ) ) ), 1 );
	check_cv( false === $config['widgets']['blockquote']['show_in_panel'] && 'Q' === $config['widgets']['blockquote']['title'], 'Con el propio registrado, el de PRO sale del panel sin perder su configuración.' );
	check_cv( false === ( $config['panel']['widgets_settings']['blockquote']['show_in_panel'] ?? null ), 'También sale de `panel.widgets_settings`, que las plantillas de PRO aplican después (C4: el contenido de la entrada).' );
	$config = Panel::document_config( array( 'panel' => array( 'widgets_settings' => array( 'blockquote' => array( 'show_in_panel' => true ), 'heading' => array( 'show_in_panel' => true ) ) ) ), 1 );
	check_cv( false === $config['panel']['widgets_settings']['blockquote']['show_in_panel'] && true === $config['panel']['widgets_settings']['heading']['show_in_panel'], 'Un `widgets_settings` que vuelve a mostrar el de PRO queda en falso; los demás no cambian.' );
	Registry::set( 'blockquote', $map + array( 'status' => 'ready', 'panel' => 'show' ) );
	check_cv( array() === Panel::document_config( array(), 1 ), 'panel => show lo deja a la vista.' );
	// C5: en una plantilla de Elemento de bucle, los bucles propios salen de su panel como PRO quita los suyos.
	$GLOBALS['cv']['meta'][77]['_elementor_template_type'] = 'loop-item';
	$manager->known = array( 'digitalisimo-loop-grid', 'digitalisimo-loop-carousel' );
	Registry::set( 'blockquote', null );
	$loop_cfg = Panel::document_config( array(), 77 );
	check_cv( false === ( $loop_cfg['panel']['widgets_settings']['digitalisimo-loop-grid']['show_in_panel'] ?? null ) && false === ( $loop_cfg['panel']['widgets_settings']['digitalisimo-loop-carousel']['show_in_panel'] ?? null ), 'Plantilla de Elemento de bucle: la Cuadrícula y el Carrusel de bucle propios no se ofrecen dentro (como los de PRO): ' . json_encode( $loop_cfg['panel'] ?? null ) );
	$other_cfg = Panel::document_config( array(), 1 );
	check_cv( ! isset( $other_cfg['panel']['widgets_settings']['digitalisimo-loop-grid'] ) && ! isset( $other_cfg['panel']['widgets_settings']['digitalisimo-loop-carousel'] ), 'En otro documento, los bucles propios siguen en el panel.' );
	Registry::reset();

	// Idioma de los mapas (c7#8): `all()` (el panel del editor, en cada documento) pasa una sola vez al idioma del sitio
	// para todos los mapas que faltan, no una por mapa (cada cambio recarga todas las traducciones), y ninguna si ya
	// están leídos; un mapa suelto también se lee en el idioma del sitio. Siempre vuelve al de la persona.
	$GLOBALS['cv']['locale']['switches'] = 0;
	$loaded = count( Registry::all( true ) );
	check_cv( $loaded > 90 && 1 === $GLOBALS['cv']['locale']['switches'] && 'en_US' === $GLOBALS['cv']['locale']['current'] && array() === $GLOBALS['cv']['locale']['stack'], "Registry::all(): un solo cambio al idioma del sitio para los $loaded mapas ({$GLOBALS['cv']['locale']['switches']}) y vuelta al de la persona." );
	Registry::all();
	Registry::map( 'blockquote' );
	check_cv( 1 === $GLOBALS['cv']['locale']['switches'], 'Con los mapas ya leídos, ningún cambio de idioma más.' );
	Registry::reset();
	check_cv( null !== Registry::map( 'blockquote', true ) && 2 === $GLOBALS['cv']['locale']['switches'] && 'en_US' === $GLOBALS['cv']['locale']['current'] && array() === $GLOBALS['cv']['locale']['stack'], 'Un mapa suelto también se lee en el idioma del sitio, y vuelve el de la persona.' );
	Registry::reset();

	// 5. Cada mapa real: declara todos los controles de PRO y apunta a un widget propio. Los controles de un widget de PRO
	// incluyen los de su clase padre (`carousel/widgets/base.php` cuando extiende `Base`) y los de `Base_Carousel_Trait`
	// cuando lo usa, y los nombres armados por concatenación (`'arrow_' . $state . '_color'`) se expanden con los valores
	// que reciben en el código (literales de un `foreach` o de las llamadas al método que los declara como parámetro).
	$nested = pro_controls( $files['nested-carousel'], $elements );
	$media  = pro_controls( $files['media-carousel'], $elements );
	foreach ( array( 'slides_to_show', 'equal_height', 'offset_width', 'navigation_previous_icon_vertical_position', 'arrow_normal_color', 'arrow_hover_color', 'arrows_normal_background', 'arrows_hover_border_type', 'arrows_hover_box_shadow', 'dots_custom_position', 'heading_dots_pagination_horizontal', 'heading_dotspagination_vertical', 'fraction_horizontal_offset', 'fraction_vertical_position', 'progressbar_hover_color', 'carousel_items', 'slide_title' ) as $name ) {
		check_cv( in_array( $name, $nested, true ), "Cobertura: falta $name entre los controles del carrusel anidado de PRO (trait y concatenados)." );
	}
	foreach ( array( 'slides_name', 'slides_per_view', 'space_between', 'pagination_color', 'image_size', 'lazyload', 'skin', 'image_link_to' ) as $name ) {
		check_cv( in_array( $name, $media, true ), "Cobertura: falta $name entre los controles del carrusel multimedia de PRO (con su clase padre)." );
	}
	check_cv( ! preg_grep( '/(^_|_$)/', array_merge( $nested, $media ) ) && 1 === count( pro_sources( $files['blockquote'], $elements ) ), 'Cobertura: ningún nombre a medias (`arrow_`, `heading_`) y un widget sin padre ni trait sólo aporta los suyos.' );
	$own = array();
	foreach ( glob( $elements . 'modules/digitalisimo-*/**/*.php' ) ?: array() as $file ) {
		$source = (string) file_get_contents( $file );
		if ( preg_match_all( "/function get_name\(\)\s*\{\s*return\s+'(digitalisimo-[a-z0-9-]+)'/", $source, $m ) ) {
			$own = array_merge( $own, $m[1] );
		}
		// También `return self::NAME;` con `const NAME = 'digitalisimo-…';` en la misma clase.
		if ( preg_match( '/function get_name\(\)\s*\{\s*return\s+self::NAME;/', $source ) && preg_match( "/const NAME\s*=\s*'(digitalisimo-[a-z0-9-]+)'/", $source, $m ) ) {
			$own[] = $m[1];
		}
	}
	foreach ( glob( $elements . 'modules/digitalisimo-*/*.php' ) ?: array() as $file ) {
		if ( preg_match_all( "/function get_name\(\)\s*\{\s*return\s+'(digitalisimo-[a-z0-9-]+)'/", (string) file_get_contents( $file ), $m ) ) {
			$own = array_merge( $own, $m[1] );
		}
	}
	$maps = Registry::all( true );
	foreach ( $maps as $type => $m ) {
		check_cv( in_array( $m['target'], $own, true ), "$type: el destino {$m['target']} no es un widget propio" );
		check_cv( in_array( $m['status'] ?? 'draft', array( 'ready', 'draft' ), true ) && in_array( $m['panel'] ?? 'show', array( 'hide', 'show' ), true ), "$type: status o panel no válidos" );
		$pro_controls = pro_controls( $files[ $type ], $elements );
		$declared = array_merge( array_keys( (array) ( $m['rename'] ?? array() ) ), (array) ( $m['keep'] ?? array() ), array_keys( (array) ( $m['groups'] ?? array() ) ), array_keys( (array) ( $m['drop'] ?? array() ) ), array_keys( (array) ( $m['approx'] ?? array() ) ), array_keys( (array) ( $m['repeaters'] ?? array() ) ), (array) ( $m['ui'] ?? array() ) );
		foreach ( (array) ( $m['repeaters'] ?? array() ) as $spec ) {
			$declared = array_merge( $declared, array_keys( (array) ( $spec['rename'] ?? array() ) ), (array) ( $spec['keep'] ?? array() ), array_keys( (array) ( $spec['drop'] ?? array() ) ), array_keys( (array) ( $spec['approx'] ?? array() ) ), array_keys( (array) ( $spec['groups'] ?? array() ) ) );
		}
		$missing = array_diff( $pro_controls, $declared );
		check_cv( ! $missing, "$type: el mapa no declara los controles " . implode( ', ', $missing ) );
	}
	// Corrección de fidelidad de C1 (docs/pro/c1.md): las clases que en PRO eran un fondo CSS y en el widget propio son o
	// contienen un `<img>` (`img_classes`): `background-size`/`background-position` del CSS personalizado pasan a
	// `object-fit`/`object-position` del `<img>` en los cuatro mapas (antes no tenían efecto).
	$bg_cases = array(
		'media-carousel' => array( '.elementor-carousel-image', 'selector .digi-media-carousel__image img' ),
		'slides'         => array( '.swiper-slide-bg', 'selector .digi-slides__bg img' ),
		'call-to-action' => array( '.elementor-cta__bg', 'selector .digi-cta__bg img' ),
		'gallery'        => array( '.e-gallery-image', 'selector .digi-gallery__image:is(img)' ),
	);
	foreach ( $bg_cases as $type => list( $pro_class, $target ) ) {
		check_cv( isset( $maps[ $type ]['img_classes'] ), "$type: declara sus clases de imagen (img_classes)." );
		list( $bg_out, $bg_codes ) = Translator::translate( array( 'custom_css' => "selector {$pro_class}{background-size:cover;background-position:30% 0}\nselector .x{background-size:contain}" ), $maps[ $type ] );
		check_cv( str_contains( $bg_out['custom_css'], "\n{$target}{object-fit:cover;object-position:30% 0}" ) && ! str_contains( $bg_out['custom_css'], 'selector .x img' ), "$type: el fondo de {$pro_class} pasa al <img> ({$target}): " . $bg_out['custom_css'] );
		check_cv( array( 'pro.css' ) === array_column( $bg_codes, 'code' ), "$type: un solo código pro.css." );
	}
	$lost = false;
	$kept = Translator::background_to_object( "selector .a{background-size:50% auto;background-position-x:left}\nselector .a::before{background-size:cover}\n@font-face{font-family:x}\nselector .b,selector .a .c{background-position:center}", array( '.a' => ' img', '.c' => '' ), $lost );
	check_cv( $lost && "selector .a{background-size:50% auto;background-position-x:left}\nselector .a::before{background-size:cover}\n@font-face{font-family:x}\nselector .b,selector .a .c{background-position:center}\nselector .a .c{object-position:center}" === $kept, 'Lo que no tiene equivalente (medidas, -x/-y) se informa y no se traduce; un pseudoelemento no es la imagen; sólo los selectores que terminan en la clase: ' . $kept );
	// El atajo `background` en PRO también fijaba el tamaño y la posición: se informa. Los comentarios (también uno con
	// llaves abierto antes de la regla) no pasan a la regla nueva, que de otro modo empezaría por un `*/` suelto.
	$lost = false;
	$short = Translator::background_to_object( 'selector .a{background:center/contain no-repeat}', array( '.a' => ' img' ), $lost );
	check_cv( $lost && 'selector .a{background:center/contain no-repeat}' === $short, 'El atajo background se informa como aproximación: ' . $short );
	$lost = false;
	$commented = Translator::background_to_object( "/* a{b} */ selector .a{/* c */ background-size:contain}", array( '.a' => ' img' ), $lost );
	check_cv( ! $lost && "/* a{b} */ selector .a{/* c */ background-size:contain}\nselector .a img{object-fit:contain}" === $commented, 'Los comentarios no pasan a la regla del <img>: ' . $commented );
	list( , $lost_codes ) = Translator::translate( array( 'custom_css' => 'selector .elementor-carousel-image{background-size:100px}' ), $maps['media-carousel'] );
	check_cv( array( 'pro.css', 'pro.approx' ) === array_column( $lost_codes, 'code' ), 'Un tamaño de fondo con medidas queda como aproximación en el informe.' );
	// `filter` recibe un tercer argumento para dejar códigos en el informe.
	list( $f_out, $f_codes ) = Translator::translate( array( 'a' => 1 ), array( 'source' => 'x', 'target' => 'digitalisimo-x', 'keep' => array( 'a' ), 'filter' => static function ( array $out, array $src, callable $report ): array { $report( 'pro.dropped', 'a', 'Motivo.', 'dropped' ); unset( $out['a'] ); return $out; } ) );
	check_cv( ! isset( $f_out['a'] ) && array( array( 'code' => 'pro.dropped', 'key' => 'a', 'reason' => 'Motivo.', 'section' => 'dropped' ) ) === $f_codes, 'filter deja sus códigos en el informe.' );

	echo 'DIGITALÍSIMO Elements: sustitución de los widgets de PRO (catálogo de ' . count( Catalog::WIDGETS ) . ', traductor, árboles, panel y ' . count( $maps ) . " mapas) validada.\n";
}
