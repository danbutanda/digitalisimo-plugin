<?php
/**
 * F8 · frente I: marcadores de K (`digitalisimo-unconverted`) con un widget de Element Pack o de Prime Slider dentro
 * (F8-D19, §3.F, V.12, V.14 C13, V.7 «filtro de K puro»).
 *
 * Cubre:
 * - sin marcador → marcador: la conversión envuelve lo que no puede convertir (sin adaptador, destino no registrado) y
 *   lo recuenta (meta del documento y opción del sitio), así que el editor lo conserva al guardar; nunca en revisiones;
 * - los tres estados de `Markers::classify()` por el mapa y por `target_ready()`: convertible, bloqueado y declarado,
 *   en Element Pack y en Prime Slider, y lo que leen `Native`, `Migration` y `Runner::prime_slider_left()`;
 * - la etapa `markers` por la aguja del marcador (una búsqueda por etapa, revisiones incluidas, lista borrada al
 *   cerrar), con copia, marca F8, recuento e informe (`mk.*`), y el salto de un documento que tumba la petición;
 * - el contexto del documento: título del documento y padre real en la tarea, `editor_post_id` en el editor;
 * - la pureza del filtro en el editor: sin entradas, adjuntos, registro, opciones ni informe (también con la barrera
 *   contra un convertidor que intente crear algo), `edit_post`, nunca en el front ni en el cron, y Elements ≥ 4.3.0.170;
 * - el contrato del filtro de K (`Preserve::convert()` real): resultado previo, tipo manipulado, resultado que es otro
 *   marcador, fragmentos.
 *
 * Se ejecuta con php-wasm (`node …/php-wasm-cli tests/migration-markers.php`): sale con 0 aunque haya un error fatal, así
 * que se juzga por la línea final.
 */

namespace Elementor {
	if ( ! class_exists( Plugin::class, false ) ) {
		/** Sin el constructor: `$instance` nulo (destinos «desconocidos», que cuentan como disponibles). */
		class Plugin {
			public static $instance;
		}
	}
}

namespace {
	/* -------------------------------------------------------------- Dobles que el WordPress mínimo de A no trae */

	function wp_load_alloptions() {
		$blog = $GLOBALS['wp']['blog'];
		$out  = array();
		foreach ( (array) ( $GLOBALS['wp']['options'][ $blog ] ?? array() ) as $key => $value ) {
			if ( ! empty( $GLOBALS['wp']['autoload'][ $blog ][ $key ] ) ) {
				$out[ $key ] = is_scalar( $value ) ? (string) $value : $value;
			}
		}
		return $out;
	}
	function get_the_ID() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
		return (int) ( $GLOBALS['wp']['the_id'] ?? 0 );
	}
	function post_type_exists( $type ) {
		return in_array( $type, (array) ( $GLOBALS['wp']['post_types'] ?? array( 'post', 'page' ) ), true );
	}
	function determine_locale() {
		return 'es_ES';
	}
	/** Como el de A, más `edit_post` denegado sobre entradas concretas (`$GLOBALS['wp']['deny_edit']`). */
	function current_user_can( $cap, ...$args ) {
		if ( 'edit_post' === $cap && in_array( (int) ( $args[0] ?? 0 ), (array) ( $GLOBALS['wp']['deny_edit'] ?? array() ), true ) ) {
			return false;
		}
		return user_can( get_current_user_id(), $cap, ...$args );
	}
	/**
	 * Como el de A, más `wp_insert_post_empty_content` de WordPress (lo que usa la barrera de pureza) y un registro de
	 * cada inserción.
	 */
	function wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
		$postarr = is_object( $postarr ) ? get_object_vars( $postarr ) : (array) $postarr;
		if ( apply_filters( 'wp_insert_post_empty_content', false, $postarr ) ) {
			$GLOBALS['wp']['refused'][] = $postarr;
			return $wp_error ? new WP_Error( 'empty_content', 'Contenido vacío.' ) : 0;
		}
		$blog   = $GLOBALS['wp']['blog'];
		$update = ! empty( $postarr['ID'] );
		$id     = $update ? (int) $postarr['ID'] : ++$GLOBALS['wp']['next_id'][ $blog ];
		$data   = wp_unslash( array_intersect_key( $postarr, f8_post_defaults( array() ) ) );
		if ( ! isset( $data['post_status'] ) && ! $update ) {
			$data['post_status'] = 'draft';
		}
		if ( ! isset( $data['post_type'] ) && ! $update ) {
			$data['post_type'] = 'post';
		}
		$data = apply_filters( 'wp_insert_post_data', $data, $postarr, $postarr, $update );
		$old  = $update ? ( $GLOBALS['wp']['posts'][ $blog ][ $id ] ?? array() ) : array();
		$row  = f8_post_defaults( array_merge( $old, $data, array( 'ID' => $id ) ) );
		$GLOBALS['wp']['posts'][ $blog ][ $id ] = $row;
		if ( ! $update ) {
			$GLOBALS['wp']['inserted'][] = $id;
		}
		foreach ( (array) ( $postarr['meta_input'] ?? array() ) as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		if ( $fire_after_hooks ) {
			do_action( 'save_post', $id, (object) $row, $update );
			do_action( 'wp_insert_post', $id, (object) $row, $update );
		}
		return $id;
	}

	require __DIR__ . '/fixtures/f8-wp.php';

	// K real: el marcador y su conversión (`Preserve::convert()`), con las constantes de su módulo.
	$ele = dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-import/';
	require_once $ele . 'class-module.php';
	require_once $ele . 'kits/class-preserve.php';

	f8_reset();
	f8_load_migration( array( 'translator', 'context', 'report', 'native', 'migration', 'retire', 'aliases', 'runner', 'markers' ) );

	use Digitalisimo\Elements\Import\Kits\Preserve;
	use Digitalisimo\Elements\Legacy\Context;
	use Digitalisimo\Elements\Legacy\Markers;
	use Digitalisimo\Elements\Legacy\Migration;
	use Digitalisimo\Elements\Legacy\Native;
	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Runner;
	use Digitalisimo\Elements\Legacy\Prime_Slider\Dynamic;
	use Digitalisimo\Elements\Legacy\Prime_Slider\Loop_Item;

	$check = 'f8_check';
	$root  = dirname( __DIR__ );

	/* -------------------------------------------------------------- Ayudas */

	function w( string $type, array $settings = array(), string $id = 'a1b2c3d' ): array {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}
	function mk( array $element, string $source = 'kit' ): array {
		return Preserve::marker( $element, 'unknown', $source );
	}
	function box( string $type, array $children, string $id, bool $inner = false ): array {
		return array( 'id' => $id, 'elType' => $type, 'settings' => 'column' === $type ? array( '_column_size' => 100 ) : array(), 'elements' => $children, 'isInner' => $inner );
	}
	/** Un documento con esos elementos (en una columna de una sección). */
	function doc( array $widgets, string $title = 'Página', array $post = array() ): int {
		static $n = 0;
		++$n;
		$tree = array( box( 'section', array( box( 'column', $widgets, 'c' . $n ) ), 's' . $n ) );
		return f8_post( array( 'post_type' => 'page', 'post_title' => $title ) + $post, array( '_elementor_data' => wp_json_encode( $tree ) ) );
	}
	function tree( int $id ): array {
		$raw = get_metadata( 'post', $id, '_elementor_data', true );
		$out = is_string( $raw ) ? json_decode( $raw, true ) : null;
		return is_array( $out ) ? $out : array();
	}
	/** `widgetType` de los widgets del árbol (sin entrar en las cargas de los marcadores). */
	function types( array $elements ): array {
		$out = array();
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			if ( 'widget' === ( $el['elType'] ?? '' ) ) {
				$out[] = (string) $el['widgetType'];
			}
			$out = array_merge( $out, types( (array) ( $el['elements'] ?? array() ) ) );
		}
		return $out;
	}
	function find( array $elements, string $id ): ?array {
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			if ( (string) ( $el['id'] ?? '' ) === $id && 'digitalisimo-unconverted' !== ( $el['widgetType'] ?? '' ) ) {
				return $el;
			}
			$hit = find( (array) ( $el['elements'] ?? array() ), $id );
			if ( $hit ) {
				return $hit;
			}
		}
		return null;
	}
	/** Códigos del informe guardado y en memoria para un documento. */
	function doc_codes( int $id ): array {
		Report::flush();
		$out = array();
		foreach ( (array) ( Report::stored()['docs'][ $id ]['items'] ?? array() ) as $item ) {
			$out[] = $item['code'] . ':' . (string) ( $item['detail']['widget'] ?? $item['detail']['type'] ?? '' );
		}
		return $out;
	}
	/** Gestor de widgets mínimo: sólo los tipos indicados existen. */
	class Test_Manager {
		public $known;
		public function __construct( array $known ) {
			$this->known = $known;
		}
		public function get_widget_types( $name = null ) {
			if ( null === $name ) {
				return array();
			}
			return in_array( $name, $this->known, true ) ? new stdClass() : null;
		}
	}
	function manager( ?array $known ): void {
		\Elementor\Plugin::$instance = null === $known ? null : (object) array( 'widgets_manager' => new Test_Manager( $known ) );
		Markers::reset();
	}
	function fresh(): void {
		f8_reset();
		$GLOBALS['wp']['post_types'] = array( 'post', 'page', 'product' );
		$GLOBALS['wp']['inserted']   = array();
		$GLOBALS['wp']['refused']    = array();
		$_REQUEST                    = array();
		manager( null );
		Context::reset();
		Report::clear();
		Report::begin( 'run' );
		Dynamic::set_env( array( 'ready' => array(), 'features' => array( 'container' => true, 'loop-item' => true, 'nested' => true ), 'tags' => array(), 'woocommerce' => false, 'events' => false ) );
		Loop_Item::reset();
		Markers::reset();
		Preserve::reset();
		remove_all_filters( Markers::FILTER );
		add_filter( Markers::FILTER, array( Markers::class, 'convert' ), 10, 3 );
	}
	/** Recorre la etapa `markers` como el runner (stage_markers), hasta que pasa a la siguiente. */
	function run_markers_stage( array $state = array() ): array {
		$state = $state + Runner::fresh( true, 0, 1 );
		$state['stage'] = 'markers';
		$stage = new ReflectionMethod( Runner::class, 'stage_markers' );
		if ( PHP_VERSION_ID < 80100 ) {
			$stage->setAccessible( true );
		}
		for ( $i = 0; $i < 50 && 'markers' === $state['stage']; $i++ ) {
			$state = $stage->invoke( null, $state, microtime( true ) + 30 );
		}
		return $state;
	}
	/** Foto de lo que una conversión pura no debe tocar. */
	function snapshot(): string {
		return md5( serialize( array( $GLOBALS['wp']['posts'], $GLOBALS['wp']['meta'], $GLOBALS['wp']['options'], $GLOBALS['wp']['object_terms'] ) ) );
	}
	function editor_request( int $post_id ): void {
		$_REQUEST                    = array( 'action' => 'elementor_ajax', 'editor_post_id' => (string) $post_id );
		$GLOBALS['wp']['admin']      = true;
		$GLOBALS['wp']['cron_request'] = false;
	}

	/* ================================================================ Catálogo y arranque */

	Report::reset_catalog();
	$catalog = Report::catalog();
	$sections = array( 'mk.convertible' => 'converted', 'mk.converted' => 'converted', 'mk.blocked' => 'review', 'mk.declared' => 'dropped', 'mk.fragment' => 'approx' );
	foreach ( $sections as $code => $section ) {
		$check( isset( $catalog[ $code ] ) && $section === $catalog[ $code ]['section'], 'Código ' . $code . ' en su catálogo, sección ' . $section . '.' );
	}
	$codes_file = (string) file_get_contents( $root . '/digitalisimo-migration/legacy/report/codes-markers.php' );
	$check( false !== strpos( $codes_file, 'namespace Digitalisimo\\Elements\\Legacy;' ) && 5 === substr_count( $codes_file, "'digitalisimo-migration' )" ), 'El catálogo declara el espacio de nombres y sus textos son traducibles con el dominio de Migración.' );
	$plugin_file = (string) file_get_contents( $root . '/digitalisimo-migration/includes/class-plugin.php' );
	$check( false !== strpos( $plugin_file, "add_filter( 'digitalisimo_elements_unconverted_convert', array( '\\\\Digitalisimo\\\\Elements\\\\Legacy\\\\Markers', 'convert' ), 10, 3 )" ), 'El arranque engancha el filtro de K a Markers::convert con tres argumentos.' );
	$check( 'digitalisimo_elements_unconverted_convert' === \Digitalisimo\Elements\Import\Module::FILTER_UNCONVERTED_CONVERT && Markers::WIDGET === \Digitalisimo\Elements\Import\Module::WIDGET && Markers::SETTING === Preserve::SETTING && Markers::WRAPPER_SETTING === Preserve::WRAPPER_SETTING, 'Los nombres de Markers coinciden con los de K (filtro, widget, carga y envoltorio).' );
	$html = Report::render( array( 'v' => 1, 'docs' => array(), 'omitted' => 0, 'site' => array( 'items' => array( array( 'code' => 'mk.blocked', 'detail' => array( 'widget' => '<img src=x onerror=alert(1)>', 'count' => 2 ) ) ), 'more' => 0 ) ), 'site' );
	$check( false === strpos( $html, '<img' ) && false !== strpos( $html, '&lt;img' ), 'El informe escapa el detalle de un código mk.' );

	/* ================================================================ Tres estados */

	fresh();
	$groups = Markers::classify(
		array(
			mk( w( 'bdt-accordion', array(), 'e1' ) ),
			mk( w( 'bdt-age-gate', array(), 'e2' ), 'migration' ),
			mk( w( 'bdt-zz-sin-mapa', array(), 'e3' ) ),
			mk( w( 'prime-slider-general', array(), 'p1' ) ),
			mk( w( 'prime-slider-remote-arrows', array(), 'p2' ), 'migration' ),
			mk( w( 'prime-slider-nada', array(), 'p3' ) ),
			mk( w( 'otro-plugin-widget', array(), 'o1' ) ),
			mk( box( 'container', array( w( 'bdt-accordion', array(), 'dentro' ) ), 'k1' ) ),
			box( 'section', array( box( 'column', array( mk( w( 'bdt-tabs', array(), 'e4' ) ) ), 'cx' ) ), 'sx' ),
		)
	);
	$check( array( 'bdt-accordion', 'prime-slider-general', 'bdt-tabs' ) === $groups['convertible'], 'Convertible: Element Pack con mapa (también anidado en el árbol) y Prime Slider con mapa y destino.' );
	$check( array( 'bdt-zz-sin-mapa', 'prime-slider-nada' ) === $groups['blocked'], 'Bloqueado: un ID sin mapa ni decisión, y Prime Slider sin mapa.' );
	$check( array( 'bdt-age-gate', 'prime-slider-remote-arrows' ) === $groups['declared'], 'Declarado: sin adaptador en unsupported.php y el descarte de Prime Slider (familia M).' );
	$check( 7 === count( array_merge( ...array_values( $groups ) ) ) && ! in_array( 'otro-plugin-widget', array_merge( ...array_values( $groups ) ), true ), 'Los marcadores de otros plugins y los de un contenedor (sin entrar en su carga) no cuentan.' );
	manager( array( 'digitalisimo-tabs' ) );
	$groups = Markers::classify( array( mk( w( 'bdt-accordion' ) ) ) );
	$check( array( 'bdt-accordion' ) === $groups['blocked'] && ! $groups['convertible'], 'Con el destino no registrado en el sitio, Element Pack queda bloqueado (target_ready() = false).' );
	manager( null );
	Dynamic::set_env( array( 'ready' => array(), 'features' => array( 'container' => true, 'loop-item' => true, 'nested' => true ), 'tags' => array(), 'woocommerce' => false, 'events' => false ) );
	Markers::reset();
	$check( array( 'prime-slider-event-calendar' ) === Markers::classify( array( mk( w( 'prime-slider-event-calendar' ) ) ) )['declared'], 'event-calendar sin The Events Calendar: descarte declarado (Dynamic::state()).' );
	Dynamic::set_env( array( 'ready' => array(), 'features' => array( 'container' => true, 'loop-item' => true, 'nested' => true ), 'tags' => array(), 'woocommerce' => false, 'events' => true ) );
	Markers::reset();
	$check( array( 'prime-slider-event-calendar' ) === Markers::classify( array( mk( w( 'prime-slider-event-calendar' ) ) ) )['convertible'], 'Con The Events Calendar, convertible.' );
	Dynamic::set_env( array( 'ready' => array( 'loop-carousel' => false, 'posts' => false ), 'features' => array( 'container' => true, 'loop-item' => true, 'nested' => true ), 'tags' => array(), 'woocommerce' => false, 'events' => false ) );
	Markers::reset();
	$check( array( 'prime-slider-blog' ) === Markers::classify( array( mk( w( 'prime-slider-blog' ) ) ) )['blocked'], 'Prime Slider dinámico sin Loop Carousel ni Entradas: bloqueado.' );
	fresh();

	// Lo que leen los demás frentes.
	$conv  = mk( w( 'bdt-accordion' ) );
	$decl  = mk( w( 'bdt-age-gate' ), 'migration' );
	$block = mk( w( 'bdt-zz-sin-mapa' ) );
	$check( Native::is_legacy( $conv ) && Native::is_convertible( $conv ), 'Native: un marcador convertible es heredado y convertible.' );
	$check( Native::is_legacy( $block ) && ! Native::is_convertible( $block ), 'Native: uno bloqueado es heredado (no queda invisible) y no convertible.' );
	$check( ! Native::is_legacy( $decl ) && ! Native::is_convertible( $decl ), 'Native: un descarte declarado no cuenta como pendiente.' );
	$d_conv  = doc( array( $conv ) );
	$d_block = doc( array( $block ) );
	$d_decl  = doc( array( $decl ) );
	$groups  = Migration::classify();
	$check( in_array( $d_conv, $groups['convertible'], true ) && in_array( $d_block, $groups['blocked'], true ) && ! in_array( $d_decl, array_merge( $groups['convertible'], $groups['blocked'] ), true ), 'Migration::classify(): convertible, bloqueado o fuera (descarte declarado).' );
	$ps_block = doc( array( mk( w( 'prime-slider-nada', array(), 'pz' ) ) ) );
	$ps_decl  = doc( array( mk( w( 'prime-slider-remote-arrows', array(), 'py' ), 'migration' ) ) );
	$check( array( 0, 1 ) === Runner::prime_slider_left( $ps_block ) && array( 0, 0 ) === Runner::prime_slider_left( $ps_decl ), 'retire_late: un marcador de Prime Slider sin destino retiene la retirada; un descarte declarado no.' );

	// Análisis previo: los tres códigos por documento, sin escribir nada.
	$analyze = new ReflectionMethod( Migration::class, 'analyze_document' );
	if ( PHP_VERSION_ID < 80100 ) {
		$analyze->setAccessible( true );
	}
	Report::begin( 'analysis' );
	$before = snapshot();
	foreach ( array( $d_conv, $d_block, $d_decl ) as $id ) {
		$analyze->invoke( null, $id, array() );
	}
	$analysis = Report::take();
	$found    = array();
	foreach ( array( $d_conv => 'mk.convertible', $d_block => 'mk.blocked', $d_decl => 'mk.declared' ) as $id => $code ) {
		foreach ( (array) ( $analysis['docs'][ $id ]['items'] ?? array() ) as $item ) {
			if ( $code === $item['code'] && 1 === (int) ( $item['detail']['count'] ?? 0 ) ) {
				$found[] = $code;
			}
		}
	}
	$check( array( 'mk.convertible', 'mk.blocked', 'mk.declared' ) === $found, 'Análisis previo: mk.convertible, mk.blocked y mk.declared por documento, con el tipo y el recuento.' );
	$check( $before === snapshot(), 'El análisis previo no escribe nada.' );
	Report::begin( 'run' );

	/* ================================================================ Sin marcador → marcador, y recuento */

	fresh();
	$plain = doc( array( w( 'bdt-age-gate', array( 'title' => 'Edad' ), 'g1' ) ) );
	$res   = Migration::convert_document( $plain );
	$data  = tree( $plain );
	$m     = $data[0]['elements'][0]['elements'][0];
	$info  = Preserve::info( $m );
	$check( ! empty( $res['native'] ) && 'digitalisimo-unconverted' === $m['widgetType'] && 'g1' === $m['id'], 'Un widget de Element Pack sin adaptador pasa a un marcador con su mismo ID.' );
	$check( 'migration' === $info['source'] && 'widget/bdt-age-gate' === $info['type'] && 'Edad' === $info['element']['settings']['title'], 'La carga conserva el original completo, con origen «migration».' );
	$check( 1 === (int) get_post_meta( $plain, '_digitalisimo_unconverted', true ) && 1 === (int) get_option( 'digitalisimo_elements_unconverted' ) && true === f8_autoload( 'digitalisimo_elements_unconverted' ), 'Recuento al crearlo: meta del documento y total del sitio con carga automática.' );
	$check( 1 === Preserve::count() && Preserve::should_register(), 'Con el recuento, K registra el marcador: el editor no lo borra al guardar.' );
	$check( array( 'bdt-age-gate' ) === Markers::classify( $data )['declared'], 'El marcador creado es un descarte declarado.' );
	manager( array() );
	$missing = doc( array( w( 'bdt-accordion', array(), 'h1' ) ) );
	Migration::convert_document( $missing );
	$check( 'digitalisimo-unconverted' === tree( $missing )[0]['elements'][0]['elements'][0]['widgetType'] && in_array( 'core.target_missing:bdt-accordion', doc_codes( $missing ), true ), 'Con el destino no registrado, el widget pasa a un marcador (core.target_missing).' );
	$check( array( 'bdt-accordion' ) === Markers::classify( tree( $missing ) )['blocked'] && 2 === (int) get_option( 'digitalisimo_elements_unconverted' ), 'Queda bloqueado y el total del sitio sube a 2.' );
	$rev = f8_post( array( 'post_type' => 'revision', 'post_parent' => $plain, 'post_status' => 'inherit' ), array( '_elementor_data' => wp_json_encode( array( box( 'section', array( box( 'column', array( w( 'bdt-age-gate', array(), 'g2' ) ), 'cr' ) ), 'sr' ) ) ) ) );
	Migration::convert_document( $rev );
	$check( 'digitalisimo-unconverted' === tree( $rev )[0]['elements'][0]['elements'][0]['widgetType'] && '' === get_post_meta( $rev, '_digitalisimo_unconverted', true ) && 2 === (int) get_option( 'digitalisimo_elements_unconverted' ), 'En una revisión se envuelve igual, pero nunca se recuenta.' );

	/* ================================================================ Etapa `markers`: aguja, conversión, recuento */

	fresh();
	$kit     = doc( array( mk( w( 'bdt-accordion', array( 'tabs' => array( array( '_id' => 't1', 'tab_title' => 'Uno', 'tab_content' => 'Texto' ) ) ), 'k1' ) ), mk( w( 'otro-plugin-widget', array(), 'o1' ) ) ), 'Kit importado' );
	Preserve::recount_document( $kit, tree( $kit ) );
	$kit_rev = f8_post( array( 'post_type' => 'revision', 'post_parent' => $kit, 'post_status' => 'inherit' ), array( '_elementor_data' => get_metadata( 'post', $kit, '_elementor_data', true ) ) );
	$stays   = doc( array( mk( w( 'bdt-zz-sin-mapa', array(), 'z1' ) ), mk( w( 'bdt-age-gate', array(), 'z2' ), 'migration' ) ) );
	Preserve::recount_document( $stays, tree( $stays ) );
	$loose      = doc( array( w( 'bdt-accordion', array(), 'l1' ) ) );
	$loose_raw  = get_metadata( 'post', $loose, '_elementor_data', true );
	$other      = doc( array( mk( w( 'otro-plugin-widget', array(), 'o2' ) ) ) );
	$other_raw  = get_metadata( 'post', $other, '_elementor_data', true );
	$check( 4 === (int) get_option( 'digitalisimo_elements_unconverted' ), 'Antes de la etapa: 4 marcadores en el sitio (2 + 2).' );
	$GLOBALS['wpdb']->queries = array();
	$ids = Markers::documents();
	$sql = implode( "\n", $GLOBALS['wpdb']->queries );
	$check( in_array( $kit, $ids, true ) && in_array( $kit_rev, $ids, true ) && in_array( $stays, $ids, true ), 'documents(): por la aguja del marcador, también las revisiones.' );
	$check( false !== strpos( $sql, 'meta_key = %s AND meta_value LIKE %s AND ( meta_value LIKE %s' ) && false === stripos( $sql, 'DISTINCT' ) && 1 === count( $GLOBALS['wpdb']->queries ), 'Una sola consulta: aguja del marcador Y del original de Element Pack o Prime Slider, sin DISTINCT.' );
	$check( false !== strpos( Markers::NEEDLE, '"widgetType":"digitalisimo-unconverted"' ) && array( '"widgetType":"bdt-', '"widgetType":"fooevents-calendar"', '"widgetType":"lightbox"', '"widgetType":"prime-slider-' ) === Markers::PAYLOAD_NEEDLES, 'Las agujas son las del JSON guardado tal cual (carga anidada, sin doble codificación).' );

	$GLOBALS['wpdb']->queries = array();
	$state = run_markers_stage();
	$searches = count( array_filter( $GLOBALS['wpdb']->queries, static function ( $q ) {
		return false !== strpos( $q, 'AND ( meta_value LIKE %s' );
	} ) );
	$check( 'platform' === $state['stage'] && ! isset( $state['markers_cursor'] ) && ! isset( $state['markers_done'] ) && ! isset( $state['markers_current'] ), 'La etapa termina y deja paso a «platform» sin restos de su estado.' );
	$check( 1 === $searches, 'La búsqueda LIKE corre una vez en toda la etapa (lista guardada por Runner::ids()).' );
	$check( false === strpos( implode( ' ', array_keys( $GLOBALS['wp']['options'][1] ) ), Runner::IDS . 'markers' ), 'La lista de IDs de la etapa se borra al cerrarla.' );
	$after = tree( $kit );
	$conv  = find( $after, 'k1' );
	$check( $conv && 'digitalisimo-accordion' === $conv['widgetType'] && 'Uno' === ( $conv['settings']['tabs'][0]['tab_title'] ?? '' ), 'El marcador de Element Pack pasa a su widget propio, con su ID y sus ajustes traducidos.' );
	$check( array( 'otro-plugin-widget' ) === array_map( static function ( $m ) {
		return substr( (string) $m['type'], 7 );
	}, Preserve::markers( $after ) ), 'El marcador de otro plugin se queda tal cual.' );
	$check( 1 === (int) get_post_meta( $kit, '_digitalisimo_unconverted', true ) && 3 === (int) get_option( 'digitalisimo_elements_unconverted' ), 'Recuento al resolverlo: el documento baja a 1 y el sitio a 3.' );
	$backup = json_decode( (string) get_metadata( 'post', $kit, Migration::BACKUP, true ), true );
	$check( is_array( $backup ) && false !== strpos( (string) $backup['data'], '"widgetType":"digitalisimo-unconverted"' ) && is_array( Migration::f8_mark( $kit ) ), 'Copia original con el marcador (para revertir) y marca F8.' );
	$rev_tree = tree( $kit_rev );
	$check( find( $rev_tree, 'k1' ) && 'digitalisimo-accordion' === find( $rev_tree, 'k1' )['widgetType'] && '' === get_post_meta( $kit_rev, '_digitalisimo_unconverted', true ), 'La revisión se convierte en su propio registro, sin recuento.' );
	$check( $loose_raw === get_metadata( 'post', $loose, '_elementor_data', true ) && $other_raw === get_metadata( 'post', $other, '_elementor_data', true ), 'Sin marcador de Element Pack o Prime Slider, la etapa no toca el documento (aunque la búsqueda de prueba lo devuelva).' );
	$stays_tree = tree( $stays );
	$check( 2 === count( Preserve::markers( $stays_tree ) ) && ! isset( $GLOBALS['wp']['meta'][1][ $stays ][ Migration::BACKUP ] ), 'Bloqueados y declarados se quedan en su marcador y el documento no se reescribe.' );
	$check( 2 === (int) $state['counts']['markers'] && 0 === count( (array) $state['counts']['failed'] ), 'counts.markers: documentos con marcadores convertidos (el documento y su revisión).' );
	$codes = doc_codes( $kit );
	$check( in_array( 'mk.converted:bdt-accordion', $codes, true ) && ! in_array( 'mk.converted:otro-plugin-widget', $codes, true ), 'Informe: mk.converted con el widget.' );
	$check( ! doc_codes( $kit_rev ), 'Una revisión no repite el informe de su documento.' );
	Report::begin( 'run' );
	Migration::convert_document( $stays );
	$codes = doc_codes( $stays );
	$check( in_array( 'mk.blocked:bdt-zz-sin-mapa', $codes, true ) && in_array( 'mk.declared:bdt-age-gate', $codes, true ), 'Informe: lo que queda, en mk.blocked (Pendiente de revisar) y mk.declared (Sin equivalente).' );
	$reverted = Migration::revert_document( $kit );
	$check( $reverted && 'digitalisimo-unconverted' === tree( $kit )[0]['elements'][0]['elements'][0]['widgetType'], 'Revertir devuelve el marcador original.' );

	// Una caída con un documento: se salta y la etapa sigue.
	fresh();
	$a = doc( array( mk( w( 'bdt-accordion', array(), 'x1' ) ) ) );
	$b = doc( array( mk( w( 'bdt-accordion', array(), 'x2' ) ) ) );
	$state = Runner::fresh( true, 0, 1 );
	$state = Markers::stage( $state + array( 'markers_attempts' => 1 ), microtime( true ) + 30 );
	$check( 0 === $state['markers_cursor'] && $a === $state['markers_current'] && 2 === $state['total'] && empty( $state['markers_done'] ), 'La primera entrada sólo calcula la lista y señala el documento siguiente.' );
	$a_raw = get_metadata( 'post', $a, '_elementor_data', true );
	$state['markers_attempts'] = 2;
	$state = Markers::stage( $state, microtime( true ) + 30 );
	$check( array( $a ) === $state['counts']['failed'] && $a === $state['markers_cursor'] && $b === $state['markers_current'] && $a_raw === get_metadata( 'post', $a, '_elementor_data', true ), 'Si la entrada anterior se cortó con un documento, se salta y queda en «failed» (core.document_failed).' );
	$state['markers_attempts'] = 1;
	$state = Markers::stage( $state, microtime( true ) + 30 );
	$check( ! empty( $state['markers_done'] ) && 'digitalisimo-accordion' === find( tree( $b ), 'x2' )['widgetType'], 'La etapa sigue con el siguiente y termina.' );

	/* ================================================================ Contexto del documento */

	fresh();
	$ps = doc( array( mk( w( 'prime-slider-general', array(), 'g9' ) ) ), 'Portada de prueba' );
	run_markers_stage();
	$slider = find( tree( $ps ), 'g9' );
	$check( $slider && 'digitalisimo-nested-slider' === $slider['widgetType'] && 'Portada de prueba' === ( $slider['settings']['carousel_label'] ?? '' ), 'Prime Slider en la tarea: slider anidado nombrado con el título de su documento (Context::post_id()).' );
	$check( 0 === Context::depth(), 'La pila de contexto queda vacía.' );
	$in_container = f8_post( array( 'post_type' => 'page', 'post_title' => 'Pantallas' ), array( '_elementor_data' => wp_json_encode( array( box( 'container', array( mk( w( 'prime-slider-multiscroll', array(), 'ms' ) ) ), 'cn' ) ) ) ) );
	$in_column    = doc( array( mk( w( 'prime-slider-multiscroll', array(), 'mc' ) ) ), 'Columna' );
	run_markers_stage();
	$screens = find( tree( $in_container ), 'ms' );
	$column  = find( tree( $in_column ), 'mc' );
	$check( $screens && 'container' === $screens['elType'] && $column && 'widget' === $column['elType'], 'El padre real decide la familia K: pantallas en un contenedor, slider en una columna.' );
	$check( ! Preserve::markers( tree( $in_container ) ) && ! Preserve::markers( tree( $in_column ) ), 'Sin marcadores tras convertir.' );
	// Un documento migrado por ≤ 1.0.20 (modo restringido): el marcador se convierte entero igual.
	$old = doc( array( mk( w( 'bdt-accordion', array( 'ep_display_conditions_enable' => 'yes', 'ep_display_conditions' => array() ), 'r1' ) ) ) );
	add_metadata( 'post', $old, Migration::BACKUP, wp_slash( wp_json_encode( array( 'time' => 1, 'data' => '[]' ) ) ), true );
	$check( Migration::restricted( $old ), 'Documento en modo restringido (copia de 1.0.20 sin marca F8).' );
	Migration::convert_document( $old );
	$r1 = find( tree( $old ), 'r1' );
	$check( $r1 && 'digitalisimo-accordion' === $r1['widgetType'] && ! isset( $r1['settings']['ep_display_conditions_enable'] ) && isset( $r1['settings']['digitalisimo_visibility_enable'] ) && false === Native::$restricted, 'Modo restringido: el marcador se convierte entero (también su visibilidad) y Native vuelve a su modo.' );
	// legacy_style_css() y el resto de la conversión con el documento 0 (editor sin documento conocido).
	list( $zero, $zero_changes ) = Native::element( w( 'bdt-accordion', array(), 'zz' ), 0 );
	$check( $zero_changes > 0 && 'digitalisimo-accordion' === $zero['widgetType'], 'Native::element() con el documento 0 funciona (legacy_style_css sin documento).' );

	/* ================================================================ Pureza del filtro en el editor */

	fresh();
	$page = doc( array( mk( w( 'bdt-accordion', array(), 'ed' ) ) ), 'Editor' );
	Preserve::recount_document( $page, tree( $page ) );
	editor_request( $page );
	$before   = snapshot();
	$original = w( 'bdt-accordion', array( 'tabs' => array( array( '_id' => 't1', 'tab_title' => 'Dos' ) ) ), 'ed' );
	$out      = apply_filters( Markers::FILTER, null, $original, 'widget/bdt-accordion' );
	$check( is_array( $out ) && 'digitalisimo-accordion' === $out['widgetType'] && 'ed' === $out['id'] && 'Dos' === $out['settings']['tabs'][0]['tab_title'], 'Editor: el filtro convierte el original con el mismo convertidor (Native) y su ID.' );
	$check( $before === snapshot() && ! Report::take()['docs'] && 0 === Context::depth(), 'Editor: nada escrito (entradas, metas, opciones, términos) ni informado, y el contexto vuelve a vacío.' );
	$converted = null;
	$tree      = Preserve::convert( tree( $page ), $converted );
	$check( array( 'widget/bdt-accordion' ) === $converted && 'digitalisimo-accordion' === find( $tree, 'ed' )['widgetType'] && $before === snapshot(), 'Por Preserve::convert() de K (lo que hace load/data en el editor): convertido en memoria, sin escribir.' );
	$ps_doc = doc( array(), 'Título del editor' );
	editor_request( $ps_doc );
	$before = snapshot();
	$slider = apply_filters( Markers::FILTER, null, w( 'prime-slider-general', array(), 'pe' ), 'widget/prime-slider-general' );
	$check( is_array( $slider ) && 'digitalisimo-nested-slider' === $slider['widgetType'] && 'Título del editor' === ( $slider['settings']['carousel_label'] ?? '' ) && $before === snapshot(), 'Editor: Prime Slider con el documento de editor_post_id (contexto), sólo el principal y sin escribir nada.' );
	// Lo que necesitaría crear algo se queda para la etapa.
	$before = snapshot();
	$blog   = apply_filters( Markers::FILTER, null, w( 'prime-slider-blog', array(), 'pb' ), 'widget/prime-slider-blog' );
	$check( null === $blog && $before === snapshot() && ! get_option( 'digitalisimo_migration_reg_loop' ), 'Editor: Prime Slider que necesita un Loop Item nuevo devuelve null, sin crearlo ni registrarlo.' );
	$template = f8_post( array( 'post_type' => 'elementor_library', 'post_title' => 'Tarjeta' ), array( '_elementor_template_type' => 'section', '_elementor_data' => '[]' ) );
	$before   = snapshot();
	$grid     = apply_filters( Markers::FILTER, null, w( 'bdt-dynamic-grid', array( 'template_id' => (string) $template ), 'dg' ), 'widget/bdt-dynamic-grid' );
	$check( null === $grid && 1 === count( $GLOBALS['wp']['refused'] ) && $before === snapshot() && ! get_post_meta( $template, '_digitalisimo_loop_item', true ), 'Editor: un convertidor que intenta crear una entrada (copia Loop Item) choca con la barrera y devuelve null.' );
	$check( false === has_filter( 'wp_insert_post_empty_content' ) && false === has_filter( 'wp_handle_sideload_prefilter' ), 'La barrera se retira al terminar.' );
	$GLOBALS['wp']['refused'] = array();
	$check( 0 < wp_insert_post( array( 'post_title' => 'fuera de la conversión' ) ), 'Fuera de la conversión del editor, las inserciones siguen funcionando.' );
	// En la tarea, lo mismo sí se crea; después, el editor reutiliza lo creado (sólo lectura).
	$run_doc = doc( array( mk( w( 'prime-slider-blog', array(), 'pb' ) ), mk( w( 'bdt-dynamic-grid', array( 'template_id' => (string) $template ), 'dg' ) ) ), 'Blog' );
	$_REQUEST = array();
	run_markers_stage();
	$loop_carousel = find( tree( $run_doc ), 'pb' );
	$loop_grid     = find( tree( $run_doc ), 'dg' );
	$loop_id       = (int) ( $loop_carousel['settings']['template_id'] ?? 0 );
	$check( $loop_carousel && 'loop-carousel' === $loop_carousel['widgetType'] && $loop_id > 0 && 'loop-item' === get_post_meta( $loop_id, '_elementor_template_type', true ), 'Tarea: Prime Slider con su Loop Item creado.' );
	$check( $loop_grid && 'loop-grid' === $loop_grid['widgetType'] && (int) $loop_grid['settings']['template_id'] === (int) get_post_meta( $template, '_digitalisimo_loop_item', true ), 'Tarea: la copia Loop Item de la plantilla se crea y se usa.' );
	editor_request( $page );
	$before = snapshot();
	$reused = apply_filters( Markers::FILTER, null, w( 'prime-slider-blog', array(), 'pb2' ), 'widget/prime-slider-blog' );
	$check( is_array( $reused ) && $loop_id === (int) ( $reused['settings']['template_id'] ?? 0 ) && $before === snapshot(), 'Editor: con el Loop Item ya creado, lo reutiliza sin escribir nada.' );

	// Quién y dónde.
	$GLOBALS['wp']['users'][2] = array( 'ID' => 2, 'display_name' => 'Colaborador', 'user_login' => 'colab', 'caps' => array( 'edit_posts' => false, 'read' => true ), 'caps_by_blog' => array(), 'super' => false, 'blogs' => null );
	wp_set_current_user( 2 );
	$check( null === apply_filters( Markers::FILTER, null, $original, 'widget/bdt-accordion' ), 'Sin permiso de edición: null.' );
	wp_set_current_user( 1 );
	$GLOBALS['wp']['deny_edit'] = array( $page );
	$check( null === apply_filters( Markers::FILTER, null, $original, 'widget/bdt-accordion' ), 'Con edit_posts pero sin edit_post sobre ese documento: null.' );
	$GLOBALS['wp']['deny_edit'] = array();
	$_REQUEST = array();
	$check( null === apply_filters( Markers::FILTER, null, $original, 'widget/bdt-accordion' ), 'Sin documento conocido (editor_post_id, post o get_the_ID()): null.' );
	$_REQUEST = array( 'post' => (string) $page, 'action' => 'elementor' );
	$check( is_array( apply_filters( Markers::FILTER, null, $original, 'widget/bdt-accordion' ) ), 'Con post.php?post=…&action=elementor, el documento es el de `post`.' );
	$_REQUEST                = array();
	$GLOBALS['wp']['the_id'] = $page;
	$check( is_array( apply_filters( Markers::FILTER, null, $original, 'widget/bdt-accordion' ) ), 'Y si no, get_the_ID().' );
	$GLOBALS['wp']['admin'] = false;
	$check( null === apply_filters( Markers::FILTER, null, $original, 'widget/bdt-accordion' ), 'Nunca en el front.' );
	$GLOBALS['wp']['admin']        = true;
	$GLOBALS['wp']['cron_request'] = true;
	$check( null === apply_filters( Markers::FILTER, null, $original, 'widget/bdt-accordion' ), 'Nunca en el cron.' );
	$GLOBALS['wp']['cron_request'] = false;
	unset( $GLOBALS['wp']['the_id'] );

	// Contrato del filtro.
	editor_request( $page );
	$prev = array( 'id' => 'q', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array(), 'elements' => array() );
	$check( $prev === apply_filters( Markers::FILTER, $prev, $original, 'widget/bdt-accordion' ) && $prev === Markers::convert( $prev, $original, 'widget/bdt-accordion' ), 'Un resultado de otro callback se devuelve tal cual.' );
	$check( null === Markers::convert( null, $original, 'widget/bdt-tabs' ), 'Tipo de la carga distinto del original (manipulado): null.' );
	$check( null === Markers::convert( null, w( 'heading' ), 'widget/heading' ) && null === Markers::convert( null, 'x', 'widget/bdt-accordion' ), 'Lo que no es de Element Pack ni de Prime Slider: null.' );
	$check( null === Markers::convert( null, w( 'bdt-age-gate' ), 'widget/bdt-age-gate' ) && null === Markers::convert( null, w( 'prime-slider-remote-arrows' ), 'widget/prime-slider-remote-arrows' ), 'Descartes declarados: null (el marcador se queda).' );
	manager( array() );
	$check( null === Markers::convert( null, $original, 'widget/bdt-accordion' ), 'Destino no registrado: el resultado sería otro marcador → null.' );
	manager( null );
	$check( is_array( Markers::convert( null, $original, '' ) ), 'Sin tipo en la carga, decide el original.' );

	// Elements anterior a 4.3.0.170: el filtro no convierte (al final: la constante no se puede retirar).
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.169' );
	$check( null === apply_filters( Markers::FILTER, null, $original, 'widget/bdt-accordion' ), 'Con DIGITALÍSIMO Elements anterior a 4.3.0.170, el editor no convierte.' );

	f8_done( 'migration-markers', 60 );
}
