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
namespace Elementor {
	class Plugin {
		public static $instance;
		public $widgets_manager;
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['cv'] = array( 'filters' => array() );
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['cv']['filters'][ $hook ][] = $cb; }
	function check_cv( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

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
		return $files;
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
		// Cadenas con variables (`"prefijo_$lado"`, C8) como concatenación, y los valores de `$var = 'literal';`.
		$php    = (string) preg_replace_callback( '/"([a-z0-9_]*)\$([a-z_]+)([a-z0-9_]*)"/', static function ( $m ) { return "'" . $m[1] . "' . \$" . $m[2] . " . '" . $m[3] . "'"; }, $php );
		$values = array();
		preg_match_all( "/\\$([a-z_]+)\s*=\s*'([a-z0-9_-]+)'\s*;/", $php, $literals, PREG_SET_ORDER );
		foreach ( $literals as $literal ) {
			$values[ $literal[1] ][] = $literal[2];
		}
		preg_match_all( "/foreach\s*\(\s*\[\s*((?:'[a-z0-9_-]+'\s*,?\s*)+)\]\s*as\s*\\$([a-z_]+)\s*\)/", $php, $loops, PREG_SET_ORDER );
		foreach ( $loops as $loop ) {
			preg_match_all( "/'([a-z0-9_-]+)'/", $loop[1], $lits );
			$values[ $loop[2] ] = array_merge( $values[ $loop[2] ] ?? array(), $lits[1] );
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
		return array_values( array_unique( $names ) );
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

	// 3. Árboles y recuento.
	Registry::set( 'blockquote', $map + array( 'status' => 'draft' ) );
	$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'elements' => array(
		array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'blockquote', 'settings' => array( 'tag' => 'h3' ) ),
		array( 'id' => 'c2', 'elType' => 'container', 'elements' => array( array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'blockquote', 'settings' => array() ), array( 'id' => 'w3', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array() ) ) ),
		array( 'id' => 'w4', 'elType' => 'widget', 'widgetType' => 'woocommerce-cart', 'settings' => array() ),
	) ) );
	check_cv( $tree === Converter::tree( $tree ), 'Un mapa en borrador no convierte nada fuera de las pruebas.' );
	$converted = Converter::tree( $tree, $log, true );
	check_cv( 'digitalisimo-prueba' === $converted[0]['elements'][0]['widgetType'] && 'w1' === $converted[0]['elements'][0]['id'] && 'h3' === $converted[0]['elements'][0]['settings']['html_tag'] && 'digitalisimo-prueba' === $converted[0]['elements'][1]['elements'][0]['widgetType'] && 'heading' === $converted[0]['elements'][1]['elements'][1]['widgetType'] && 'woocommerce-cart' === $converted[0]['elements'][2]['widgetType'], 'tree() convierte a cualquier profundidad, conserva ids y deja lo que no tiene mapa.' );
	check_cv( 2 === count( $log ) && 'w2' === $log[1]['element'] && 'blockquote' === $log[0]['source'], 'El registro dice qué elemento cambió y de qué a qué.' );
	check_cv( array( 'blockquote' => 2, 'woocommerce-cart' => 1 ) === Converter::count( $tree ) && array( 'woocommerce-cart' => 1 ) === Converter::count( $converted ), 'count() cuenta los widgets de PRO a cualquier profundidad.' );

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
	echo 'DIGITALÍSIMO Elements: sustitución de los widgets de PRO (catálogo de ' . count( Catalog::WIDGETS ) . ', traductor, árboles, panel y ' . count( $maps ) . " mapas) validada.\n";
}
