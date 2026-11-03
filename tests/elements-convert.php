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
	Registry::set( 'blockquote', $map + array( 'status' => 'ready', 'panel' => 'show' ) );
	check_cv( array() === Panel::document_config( array(), 1 ), 'panel => show lo deja a la vista.' );
	Registry::reset();

	// 5. Cada mapa real: declara todos los controles de PRO y apunta a un widget propio.
	$own = array();
	foreach ( glob( $elements . 'modules/digitalisimo-*/**/*.php' ) ?: array() as $file ) {
		if ( preg_match_all( "/function get_name\(\)\s*\{\s*return\s+'(digitalisimo-[a-z0-9-]+)'/", (string) file_get_contents( $file ), $m ) ) {
			$own = array_merge( $own, $m[1] );
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
		$php = (string) file_get_contents( $files[ $type ] );
		preg_match_all( "/->add_(?:responsive_)?control\(\s*'([a-z0-9_]+)'/", $php, $controls );
		preg_match_all( "/->add_group_control\([^;]*?'name'\s*=>\s*'([a-z0-9_]+)'/s", $php, $grouped );
		$declared = array_merge( array_keys( (array) ( $m['rename'] ?? array() ) ), (array) ( $m['keep'] ?? array() ), array_keys( (array) ( $m['groups'] ?? array() ) ), array_keys( (array) ( $m['drop'] ?? array() ) ), array_keys( (array) ( $m['approx'] ?? array() ) ), array_keys( (array) ( $m['repeaters'] ?? array() ) ), (array) ( $m['ui'] ?? array() ) );
		foreach ( (array) ( $m['repeaters'] ?? array() ) as $spec ) {
			$declared = array_merge( $declared, array_keys( (array) ( $spec['rename'] ?? array() ) ), (array) ( $spec['keep'] ?? array() ), array_keys( (array) ( $spec['drop'] ?? array() ) ), array_keys( (array) ( $spec['approx'] ?? array() ) ), array_keys( (array) ( $spec['groups'] ?? array() ) ) );
		}
		$missing = array_diff( array_unique( array_merge( $controls[1], $grouped[1] ) ), $declared );
		check_cv( ! $missing, "$type: el mapa no declara los controles " . implode( ', ', $missing ) );
	}
	echo 'DIGITALÍSIMO Elements: sustitución de los widgets de PRO (catálogo de ' . count( Catalog::WIDGETS ) . ', traductor, árboles, panel y ' . count( $maps ) . " mapas) validada.\n";
}
