<?php
/**
 * Casos de la puerta de PRO en CI (F-front, frente E, §6.1 y V14): recorre `tests/playground/front-budget/cases.json`
 * con `Pro_Gate::walk()` —los mismos árboles que crea `setup.php` en Playground, construidos con
 * `front-budget/lib.php`, plantillas de `extra` incluidas— y exige en cada caso de la puerta el `expect` del modo
 * (`proHandlers` y su `reason`) que `front-budget.sh` comprueba en Chrome.
 *
 * Cada tipo de elemento de los casos se clasifica sin cargar el constructor:
 * - `HANDLED` y los widgets de PRO por el `get_name()` literal de `digitalisimo-elements/modules/<módulo>/widgets/`;
 * - los adaptadores de Migración, leídos de `digitalisimo-migration/legacy/class-adapters.php` (clase y padre);
 * - el núcleo (`elementor/includes/widgets/`, elementos y atómicos), los widgets propios (`digitalisimo-`) y el
 *   complemento falso de terceros (`front-budget/fake-addon/`, cuyo filtro declara libre uno de sus widgets).
 * Con esos datos se declaran clases mínimas con la misma herencia, así `walk()` decide por clase como en el sitio. Un
 * tipo sin clasificar hace fallar. Lo que sólo decide la petición (un filtro forzado, una dependencia de la cola) se
 * marca `runtime` en el caso y aquí se omite con su motivo.
 *
 * Además valida el esquema de los casos: modos, instantáneas, acciones, tipos de `extra` y marcadores resolubles.
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	$root     = dirname( __DIR__ );
	$failures = array();
	$checks   = 0;
	function check_cases( $ok, $message ) {
		global $failures, $checks;
		$checks++;
		if ( ! $ok ) {
			$failures[] = $message;
		}
	}

	// Ganchos mínimos: el complemento falso registra su filtro de terceros y la puerta lo aplica.
	$GLOBALS['hooks'] = array();
	function add_filter( $name, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['hooks'][ $name ][ $priority ][] = array( $callback, $args );
		return true;
	}
	function add_action( $name, $callback, $priority = 10, $args = 1 ) {
		return add_filter( $name, $callback, $priority, $args );
	}
	function apply_filters( $name, $value, ...$rest ) {
		if ( empty( $GLOBALS['hooks'][ $name ] ) ) {
			return $value;
		}
		$by_priority = $GLOBALS['hooks'][ $name ];
		ksort( $by_priority );
		foreach ( $by_priority as $callbacks ) {
			foreach ( $callbacks as $entry ) {
				$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $rest ), 0, $entry[1] ) );
			}
		}
		return $value;
	}

	$elements = $root . '/digitalisimo-elements';
	require $root . '/tests/playground/front-budget/lib.php';
	require $root . '/tests/playground/front-budget/fake-addon/digi-fb-fake-addon.php';
	require $elements . '/modules/digitalisimo-front/class-pro-gate.php';

	// --- Índice de clases ------------------------------------------------------------------------------------------
	/** Clases de un archivo: [ fqcn => [ 'parent' => fqcn|'', 'name' => get_name literal|'' ] ]. */
	function digi_fc_classes( $file, $name_pattern ) {
		$code = (string) file_get_contents( $file );
		$ns   = preg_match( '/^namespace\s+([^;{\s]+)/m', $code, $m ) ? $m[1] : '';
		$uses = array();
		if ( preg_match_all( '/^use\s+([\\\\\w]+)(?:\s+as\s+(\w+))?\s*;/m', $code, $um, PREG_SET_ORDER ) ) {
			foreach ( $um as $use ) {
				$alias          = isset( $use[2] ) && '' !== $use[2] ? $use[2] : substr( strrchr( '\\' . $use[1], '\\' ), 1 );
				$uses[ $alias ] = ltrim( $use[1], '\\' );
			}
		}
		$resolve = static function ( $name ) use ( $ns, $uses ) {
			if ( '\\' === $name[0] ) {
				return ltrim( $name, '\\' );
			}
			$first = strtok( $name, '\\' );
			if ( isset( $uses[ $first ] ) ) {
				return $uses[ $first ] . substr( $name, strlen( $first ) );
			}
			return ( '' !== $ns ? $ns . '\\' : '' ) . $name;
		};
		$out = array();
		if ( preg_match_all( '/^\s*(?:abstract\s+|final\s+)*class\s+(\w+)(?:\s+extends\s+([\\\\\w]+))?/m', $code, $cm, PREG_SET_ORDER ) ) {
			$name = preg_match( $name_pattern, $code, $nm ) ? $nm[1] : '';
			foreach ( $cm as $index => $class ) {
				$out[ ( '' !== $ns ? $ns . '\\' : '' ) . $class[1] ] = array(
					'parent' => isset( $class[2] ) && '' !== $class[2] ? $resolve( $class[2] ) : '',
					'name'   => 0 === $index ? $name : '',
					'file'   => $file,
				);
			}
		}
		return $out;
	}

	$get_name    = '/function\s+get_name\s*\(\s*\)\s*(?::\s*string\s*)?\{\s*(?:\/\/[^\n]*\n\s*)*return\s+\'([^\']+)\'\s*;/';
	$get_type    = '/function\s+get_element_type\s*\(\s*\)\s*(?::\s*string\s*)?\{\s*return\s+\'([^\']+)\'\s*;/';
	$classes     = array(); // fqcn => [ parent, name, file ].
	$by_name     = array(); // nombre de widget => [ fqcn, origen ].
	$sources     = array(
		'pro'      => array( glob( $elements . '/modules/*/widgets/*.php' ), $get_name ),
		'pro-sub'  => array( glob( $elements . '/modules/*/widgets/*/*.php' ), $get_name ),
		'own'      => array( array_merge( glob( $elements . '/modules/digitalisimo-widgets/*.php' ), glob( $elements . '/modules/digitalisimo-*/class-widget*.php' ), glob( $elements . '/modules/digitalisimo-woo/*/class-*widget*.php' ) ), $get_name ),
		'core'     => array( glob( $elements . '/elementor/includes/widgets/*.php' ), $get_name ),
		'atomic'   => array( glob( $elements . '/elementor/modules/atomic-widgets/elements/*/*.php' ), $get_type ),
	);
	foreach ( $sources as $origin => $source ) {
		foreach ( (array) $source[0] as $file ) {
			foreach ( digi_fc_classes( $file, $source[1] ) as $fqcn => $info ) {
				$classes[ $fqcn ] = $info;
				if ( '' !== $info['name'] && ! isset( $by_name[ $info['name'] ] ) ) {
					$by_name[ $info['name'] ] = array( $fqcn, 'pro-sub' === $origin ? 'pro' : $origin );
				}
			}
		}
	}
	check_cases( isset( $by_name['countdown'], $by_name['nav-menu'], $by_name['heading'], $by_name['global'], $by_name['digitalisimo-accordion'], $by_name['e-heading'] ), 'el índice de widgets no encontró countdown, nav-menu, heading, global, digitalisimo-accordion o e-heading' );

	// Adaptadores de Migración: `final class Bdt_X extends \Padre { … const LEGACY_ID = 'bdt-x'; }`.
	$adapters_file = $root . '/digitalisimo-migration/legacy/class-adapters.php';
	$adapter_code  = (string) file_get_contents( $adapters_file );
	$adapters      = array();
	preg_match_all( '/^\s*(?:abstract\s+|final\s+)*class\s+(\w+)\s+extends\s+([\\\\\w]+)\s*\{(?:[^\n]*?LEGACY_ID\s*=\s*\'([^\']+)\')?/m', $adapter_code, $am, PREG_SET_ORDER );
	foreach ( $am as $adapter ) {
		$fqcn             = 'Digitalisimo\\Elements\\Legacy\\' . $adapter[1];
		$parent           = '\\' === $adapter[2][0] ? ltrim( $adapter[2], '\\' ) : 'Digitalisimo\\Elements\\Legacy\\' . $adapter[2];
		$classes[ $fqcn ] = array( 'parent' => $parent, 'name' => '', 'file' => $adapters_file );
		if ( ! empty( $adapter[3] ) ) {
			$adapters[ $adapter[3] ] = $fqcn;
		}
	}
	check_cases( isset( $adapters['bdt-navbar'], $adapters['bdt-countdown'], $adapters['bdt-contact-form'] ), 'class-adapters.php: no se leyeron bdt-navbar, bdt-countdown y bdt-contact-form' );

	// Complemento falso: sus clases fuera de los espacios propios.
	$classes['Digi_Fb_Fake_Addon\\Card']      = array( 'parent' => 'Elementor\\Widget_Base', 'name' => 'digi-fb-fake-card', 'file' => '' );
	$classes['Digi_Fb_Fake_Addon\\Free_Card'] = array( 'parent' => 'Digi_Fb_Fake_Addon\\Card', 'name' => 'digi-fb-fake-free', 'file' => '' );
	check_cases( array_keys( DIGI_FB_FAKE_WIDGETS ) === array( 'digi-fb-fake-card', 'digi-fb-fake-free' ), 'el complemento falso declara digi-fb-fake-card y digi-fb-fake-free' );

	// Elementos que no son widgets.
	$element_classes = array(
		'container' => 'Elementor\\Includes\\Elements\\Container',
		'section'   => 'Elementor\\Element_Section',
		'column'    => 'Elementor\\Element_Column',
	);

	/** Declara una clase mínima y sus padres (primero los padres), con los nombres y la herencia reales. */
	function digi_fc_define( $fqcn, array &$classes, array $seen = array() ) {
		if ( '' === $fqcn || class_exists( $fqcn, false ) || isset( $seen[ $fqcn ] ) ) {
			return;
		}
		$seen[ $fqcn ] = true;
		$parent        = isset( $classes[ $fqcn ] ) ? (string) $classes[ $fqcn ]['parent'] : '';
		if ( '' !== $parent ) {
			digi_fc_define( $parent, $classes, $seen );
		}
		$pos   = strrpos( $fqcn, '\\' );
		$ns    = false === $pos ? '' : substr( $fqcn, 0, $pos );
		$short = false === $pos ? $fqcn : substr( $fqcn, $pos + 1 );
		$code  = ( '' !== $ns ? 'namespace ' . $ns . '; ' : '' ) . 'class ' . $short . ( '' !== $parent && class_exists( $parent, false ) ? ' extends \\' . $parent : '' ) . ' {}';
		eval( $code ); // phpcs:ignore Squiz.PHP.Eval -- clases vacías de prueba, nombres leídos del repositorio.
	}

	$unclassified = array();
	$categories   = array();
	/** Clase y categoría de un tipo de elemento, o '' si no se puede clasificar. */
	$classify = static function ( $el_type, $widget_type, $record = true ) use ( &$classes, $by_name, $adapters, $element_classes, &$unclassified, &$categories ) {
		$el_type     = (string) $el_type;
		$widget_type = (string) $widget_type;
		$fqcn        = '';
		$category    = '';
		if ( 'widget' !== $el_type ) {
			if ( isset( $element_classes[ $el_type ] ) ) {
				$fqcn     = $element_classes[ $el_type ];
				$category = 'núcleo';
			} elseif ( isset( $by_name[ $el_type ] ) && 'atomic' === $by_name[ $el_type ][1] ) {
				$fqcn     = $by_name[ $el_type ][0];
				$category = 'atómico';
			}
		} elseif ( isset( $adapters[ $widget_type ] ) ) {
			$fqcn     = $adapters[ $widget_type ];
			$category = 'adaptador';
		} elseif ( isset( DIGI_FB_FAKE_WIDGETS[ $widget_type ] ) ) {
			$fqcn     = DIGI_FB_FAKE_WIDGETS[ $widget_type ];
			$category = 'terceros';
		} elseif ( isset( $by_name[ $widget_type ] ) ) {
			$fqcn     = $by_name[ $widget_type ][0];
			$origin   = $by_name[ $widget_type ][1];
			$category = 'pro' === $origin ? ( in_array( $widget_type, \Digitalisimo\Elements\Front\Pro_Gate::HANDLED, true ) ? 'PRO con controlador' : 'PRO sin controlador' ) : ( 'own' === $origin ? 'propio' : ( 'atomic' === $origin ? 'atómico' : 'núcleo' ) );
			if ( 'own' === $origin && 0 !== strpos( $widget_type, 'digitalisimo-' ) ) {
				$category = '';
			}
		}
		if ( '' === $fqcn ) {
			if ( $record ) {
				$unclassified[ $el_type . '/' . $widget_type ] = true;
			}
			return '';
		}
		digi_fc_define( $fqcn, $classes );
		if ( $record ) {
			$categories[ $category ][ 'widget' === $el_type ? $widget_type : $el_type ] = true;
		}
		return $fqcn;
	};

	function wp_json_encode_for_cases( $value ) {
		return (string) json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	// --- Esquema de los casos --------------------------------------------------------------------------------------
	$cases = json_decode( (string) file_get_contents( $root . '/tests/playground/front-budget/cases.json' ), true );
	check_cases( is_array( $cases ) && count( $cases ) > 48, 'cases.json debe ser JSON válido y conservar los casos de la medición anterior' );
	$cases       = is_array( $cases ) ? $cases : array();
	$modes       = array( 'normal', 'cache', 'plugin', 'seo', 'guard', 'theme', 'migration', 'lazy', 'v4', 'woo', 'old', 'old-applied', 'old-dirty', 'old-inactive', 'off' );
	// Combinaciones que un caso nombra tal cual: los casos de WOO=1 sólo corren con WooCommerce (F4-Woo, V.16.3).
	$combos      = array( 'old-applied+cache', 'woo+cache', 'plugin+woo', 'v4+woo' );
	$expect_keys = array_merge( array( '*', 'pre', 'warm', 'before', 'after', 'guard+old' ), $combos, $modes );
	$actions     = array( 'click', 'scroll', 'wait', 'waitFor', 'waitCount', 'mark', 'waitChange', 'hover', 'openSubmenu', 'viewport', 'fill', 'fillAll', 'reload', 'visit' );
	// `product`: plantilla de «Producto individual» (la vista rápida de F4-Woo en «Plantilla de producto»).
	$extra_types = array( 'popup', 'section', 'page', 'container', 'widget', 'loop-item', 'header', 'footer', 'product' );
	foreach ( $cases as $slug => $case ) {
		check_cases( preg_match( '/^[a-z0-9-]+$/', (string) $slug ) && is_array( $case ) && isset( $case['title'] ), "$slug: nombre o título inválidos" );
		check_cases( isset( $case['widgets'] ) || isset( $case['elements'] ) || ! empty( $case['classic'] ), "$slug: necesita widgets, elements o classic" );
		foreach ( (array) ( $case['modes'] ?? array() ) as $mode ) {
			check_cases( in_array( $mode, $modes, true ) || in_array( $mode, $combos, true ), "$slug: modo desconocido $mode" );
		}
		foreach ( array_keys( (array) ( $case['expect'] ?? array() ) ) as $key ) {
			check_cases( in_array( $key, $expect_keys, true ), "$slug: clave de expect desconocida $key" );
		}
		foreach ( (array) ( $case['actions'] ?? array() ) as $action ) {
			check_cases( in_array( $action['do'] ?? '', $actions, true ), "$slug: acción desconocida " . ( $action['do'] ?? '?' ) );
		}
		$json  = wp_json_encode_for_cases( $case );
		$types = array_map( static function ( $extra ) { return (string) ( $extra['type'] ?? '' ); }, (array) ( $case['extra'] ?? array() ) );
		foreach ( $types as $type ) {
			check_cases( in_array( $type, $extra_types, true ), "$slug: tipo de extra desconocido $type" );
		}
		check_cases( false === strpos( $json, '__POPUP__' ) || in_array( 'popup', $types, true ), "$slug: usa __POPUP__ sin un extra popup" );
		check_cases( false === strpos( $json, '__LOOP__' ) || in_array( 'loop-item', $types, true ), "$slug: usa __LOOP__ sin un extra loop-item" );
		check_cases( false === strpos( $json, '__TEMPLATE__' ) || array_diff( $types, array( 'popup', 'loop-item', 'header', 'footer' ) ), "$slug: usa __TEMPLATE__ sin una plantilla" );
		check_cases( empty( $case['sequence'] ) || ! array_diff( (array) $case['sequence'], array_keys( $cases ) ), "$slug: su secuencia nombra casos que no existen" );
	}

	// --- Recorrido de cada caso ------------------------------------------------------------------------------------
	$page_id   = 1000;
	$extra_id  = 5000;
	$docs      = array();
	$built     = array();
	$walk_load = static function ( $id ) use ( &$docs ) {
		return isset( $docs[ (int) $id ] ) ? $docs[ (int) $id ] : null;
	};
	foreach ( $cases as $slug => $case ) {
		$self   = ++$page_id;
		$extras = array();
		foreach ( (array) ( $case['extra'] ?? array() ) as $extra ) {
			$extras[ (string) $extra['key'] ] = array( 'id' => ++$extra_id, 'type' => (string) $extra['type'], 'def' => $extra );
		}
		$markers = digi_fb_markers( $self, $extras );
		$markers += array( '__MENU__' => 'digi-fb-menu', '__SITE__' => 'https://example.test' );
		$tree    = digi_fb_page_tree( $slug, $case );
		$tree    = null === $tree ? null : json_decode( json_encode( digi_fb_replace( $tree, $markers ) ), true );
		foreach ( $extras as $key => $extra ) {
			$docs[ $extra['id'] ] = json_decode( json_encode( digi_fb_replace( digi_fb_extra_tree( $slug, $extra['def'] ), $markers ) ), true );
		}
		$built[ $slug ] = array( 'self' => $self, 'tree' => $tree, 'extras' => $extras, 'markers' => $markers );
	}
	// Todos los tipos de todos los casos deben clasificarse (también los de modos que no se recorren aquí).
	$collect = static function ( $tree ) use ( &$collect, $classify ) {
		foreach ( (array) $tree as $element ) {
			if ( is_array( $element ) && isset( $element['elType'] ) ) {
				$classify( $element['elType'], $element['widgetType'] ?? '' );
				$collect( $element['elements'] ?? array() );
			}
		}
	};
	foreach ( $built as $slug => $item ) {
		$collect( $item['tree'] );
		foreach ( $item['extras'] as $extra ) {
			$collect( $docs[ $extra['id'] ] );
		}
	}
	check_cases( ! $unclassified, 'tipos sin clasificar en cases.json: ' . implode( ', ', array_keys( $unclassified ) ) );

	$class_of = static function ( $el_type, $widget_type ) use ( $classify ) {
		return $classify( $el_type, $widget_type, false );
	};
	$walked  = 0;
	$skipped = array();
	$rows    = array();
	foreach ( $built as $slug => $item ) {
		$case = $cases[ $slug ];
		if ( ! empty( $case['pending'] ) ) {
			$skipped[] = "$slug (pendiente: " . $case['pending'] . ')';
			continue;
		}
		foreach ( array( 'normal', 'migration', 'theme', 'v4', 'woo' ) as $mode ) {
			if ( ! in_array( $mode, (array) ( $case['modes'] ?? array( 'normal' ) ), true ) ) {
				continue;
			}
			$expect = digi_fb_expect( $case, array( $mode ) );
			if ( ! array_key_exists( 'proHandlers', $expect ) && ! isset( $expect['reason'] ) ) {
				continue;
			}
			if ( ! empty( $case['runtime'] ) ) {
				$skipped[] = "$slug ($mode, lo decide la petición: " . $case['runtime'] . ')';
				continue;
			}
			// Razones como en la petición: el documento de la página y los que la URL imprime (popups por condición o
			// abiertos por su etiqueta, cabeceras del Theme Builder).
			$reasons = null === $item['tree'] ? array() : \Digitalisimo\Elements\Front\Pro_Gate::walk( $item['tree'], $class_of, $walk_load );
			$page    = json_encode( $item['tree'] );
			foreach ( $item['extras'] as $key => $extra ) {
				$by_tag  = false !== strpos( (string) $page, '%22popup%22%3A%22' . $extra['id'] . '%22' );
				$printed = $by_tag || digi_fb_printed_on_self( $extra['def'] );
				if ( ! $printed ) {
					continue;
				}
				if ( 'popup' === $extra['type'] ) {
					$reasons[] = 'doc:' . $extra['id'] . ':popup';
				} else {
					$reasons = array_merge( $reasons, \Digitalisimo\Elements\Front\Pro_Gate::walk( $docs[ $extra['id'] ], $class_of, $walk_load ) );
				}
			}
			$walked++;
			$rows[] = sprintf( '%-22s %-9s %s', $slug, $mode, $reasons ? implode( ' ', $reasons ) : '—' );
			if ( array_key_exists( 'proHandlers', $expect ) ) {
				check_cases( (bool) $expect['proHandlers'] === (bool) $reasons, "$slug ($mode): proHandlers " . json_encode( $expect['proHandlers'] ) . ' esperado, razones [' . implode( ', ', $reasons ) . ']' );
			}
			if ( isset( $expect['reason'] ) ) {
				$found = false;
				foreach ( $reasons as $reason ) {
					foreach ( (array) $expect['reason'] as $want ) {
						$found = $found || 0 === strpos( $reason, $want );
					}
				}
				check_cases( $found, "$slug ($mode): razón " . implode( '|', (array) $expect['reason'] ) . ' esperada, razones [' . implode( ', ', $reasons ) . ']' );
			}
			// Ninguna razón de duda: los casos son datos bien formados.
			foreach ( $reasons as $reason ) {
				check_cases( 0 !== strpos( $reason, 'doubt:' ), "$slug ($mode): razón de duda $reason" );
			}
		}
	}
	check_cases( $walked >= 25, "se recorrieron sólo $walked casos de la puerta" );
	// El caso de los widgets propios y el vacío nunca piden PRO; los cuatro de PRO sin controlador tampoco.
	foreach ( array( 'empty', 'all-own', 'core-only', 'pro-static', 'third-party-free', 'v4-only' ) as $negative ) {
		check_cases( isset( $cases[ $negative ] ), "falta el caso negativo $negative" );
	}
	foreach ( array( 'price-table', 'blockquote', 'call-to-action', 'flip-box' ) as $static ) {
		check_cases( isset( $categories['PRO sin controlador'][ $static ] ), "$static debe clasificarse como PRO sin controlador" );
	}
	foreach ( array( 'countdown', 'nav-menu', 'form', 'loop-grid', 'lottie', 'code-highlight', 'media-carousel', 'slides' ) as $handled ) {
		check_cases( isset( $categories['PRO con controlador'][ $handled ] ), "$handled debe clasificarse como PRO con controlador" );
	}

	foreach ( $categories as $category => $types ) {
		ksort( $types );
		echo str_pad( $category, 20 ) . ' ' . implode( ', ', array_keys( $types ) ) . "\n";
	}
	echo implode( "\n", $rows ) . "\n";
	foreach ( $skipped as $line ) {
		echo "omitido: $line\n";
	}
	if ( $failures ) {
		echo "FALLA elements-front-cases (" . count( $failures ) . " de $checks)\n - " . implode( "\n - ", $failures ) . "\n";
		exit( 1 );
	}
	echo "OK elements-front-cases: $checks comprobaciones, $walked recorridos con Pro_Gate::walk()\n";
}
