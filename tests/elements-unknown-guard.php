<?php
/**
 * F4-Woo · guarda hacia delante (D28, V.10.2) y registro oculto sin WooCommerce (V.10.1), con el **núcleo real**
 * (`tests/elements-design/core.php`: `Widget_Base`, `Element_Base::get_data_for_save()`, contenedor, controles) y el
 * marcador real de K (`Preserve`).
 *
 * 1. Sólo en las peticiones del editor se engancha `elementor/document/load/data` (15, antes del 20 de K).
 * 2. Un widget `digitalisimo-*` desconocido se envuelve al cargar en `digitalisimo-unconverted` con su original
 *    completo; uno conocido o ajeno (`acme-*`) no se toca; sin desconocidos, el árbol sale `===`.
 * 3. Sin la guarda, el núcleo lo descartaría al guardar; con ella, el marcador sobrevive al guardado con su original.
 * 4. Cuando el tipo existe, K lo devuelve tal cual.
 * 5. En `admin-ajax.php?action=elementor_ajax` con marcadores (o pidiendo la configuración de widgets) se registra el
 *    marcador antes de atender la petición.
 * 6. `Inert`: un widget F4-Woo sin WooCommerce, con su clase o con el sustituto, guarda sus ajustes sin perder nada.
 */
namespace {
	require __DIR__ . '/elements-design/core.php';
	require_once __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-import/class-module.php';
	require_once __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-unknown-guard.php';
	require_once __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-woo/class-module.php';
	\Digitalisimo\Elements\Import\Module::register_autoloader();
	\Digitalisimo\Elements\Woo\Module::register_autoloader();

	use Digitalisimo\Elements\Import\Kits\Preserve;
	use Digitalisimo\Elements\Unknown_Guard;
	use Digitalisimo\Elements\Woo\Inert;
	use Digitalisimo\Elements\Woo\Inert_Widget;

	/** Gestor de widgets con `register()` (el del arnés sólo lista). */
	final class Guard_Widgets {
		public $types = array();
		public function register( $widget ) { $this->types[ $widget->get_name() ] = $widget; return true; }
		public function get_widget_types( $name = null ) { return null === $name ? $this->types : ( $this->types[ $name ] ?? null ); }
	}

	/** Un widget propio «de una versión futura»: existe sólo cuando la prueba lo registra. */
	final class Guard_Future_Widget extends \Elementor\Widget_Base {
		public function get_name() { return 'digitalisimo-woo-future'; }
		public function get_title() { return 'Futuro'; }
		protected function register_controls() {
			$this->start_controls_section( 's', array( 'label' => 'S' ) );
			$this->add_control( 'title', array( 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ) );
			$this->end_controls_section();
		}
	}

	/** Un widget F4-Woo que sigue el contrato de `Woo\Widget`. */
	final class Guard_Woo_Widget extends \Digitalisimo\Elements\Woo\Widget {
		public function get_name() { return 'digitalisimo-woo-wishlist'; }
		public function get_title() { return 'Lista de deseos'; }
		protected function register_controls() {
			$this->start_controls_section( 's', array( 'label' => 'S' ) );
			$this->add_control( 'empty_text', array( 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Vacía' ) );
			$this->end_controls_section();
		}
		protected function render_widget() { echo 'LISTA'; }
	}

	function guard_boot( array $get, bool $admin, bool $ajax_post = false ): Guard_Widgets {
		design_wp_reset();
		design_boot( array( 'admin' => $admin ) );
		$manager        = new Guard_Widgets();
		$manager->types = \Elementor\Plugin::$instance->widgets_manager->types; // common, common-optimized, heading…
		\Elementor\Plugin::$instance->widgets_manager = $manager;
		$_GET     = $get;
		$_POST    = $ajax_post ? array( 'action' => 'elementor_ajax' ) : array();
		$_REQUEST = array_merge( $get, $_POST );
		Unknown_Guard::reset();
		Preserve::reset();
		return $manager;
	}

	/** Árbol con un contenedor y, dentro, un widget conocido, uno propio desconocido, uno ajeno y uno anidado. */
	function guard_tree(): array {
		return array(
			array(
				'id'       => 'c000001',
				'elType'   => 'container',
				'settings' => array( 'content_width' => 'full' ),
				'elements' => array(
					array( 'id' => 'h000001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Hola' ), 'elements' => array() ),
					array( 'id' => 'f000001', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-future', 'settings' => array( 'title' => 'Futuro', 'digi_new' => array( 'a' => 1, 'b' => array( 2, 3 ) ) ), 'elements' => array() ),
					array( 'id' => 'a000001', 'elType' => 'widget', 'widgetType' => 'acme-thing', 'settings' => array( 'x' => 1 ), 'elements' => array() ),
					array(
						'id'       => 'c000002',
						'elType'   => 'container',
						'isInner'  => true,
						'settings' => array(),
						'elements' => array(
							array( 'id' => 'f000002', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-otro', 'settings' => array( 'n' => 5 ), 'elements' => array() ),
						),
					),
				),
			),
		);
	}

	/**
	 * Lo que guardaría el núcleo de cada widget del árbol: `create_element_instance()` (null si el tipo no existe, y el
	 * núcleo lo descarta) y `get_data_for_save()`. Los contenedores del arnés no instancian hijos, así que se guarda
	 * widget por widget: es la misma llamada que hace el contenedor con cada hijo.
	 */
	function guard_save( array $tree ): array {
		$out = array();
		foreach ( guard_index( $tree ) as $data ) {
			if ( 'widget' !== ( $data['elType'] ?? '' ) ) {
				continue;
			}
			$element = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $data );
			if ( $element ) {
				$out[] = $element->get_data_for_save();
			}
		}
		return $out;
	}

	/** Todos los elementos de un árbol por ID. */
	function guard_index( array $tree, array &$out = array() ): array {
		foreach ( $tree as $element ) {
			if ( isset( $element['id'] ) ) {
				$out[ $element['id'] ] = $element;
			}
			if ( ! empty( $element['elements'] ) ) {
				guard_index( $element['elements'], $out );
			}
		}
		return $out;
	}

	// 1. Dónde se engancha.
	foreach ( array(
		'front'                  => array( array(), false, false, false ),
		'front con ?action=elementor' => array( array( 'action' => 'elementor' ), false, false, false ),
		'otra pantalla de la admin' => array( array( 'action' => 'edit' ), true, false, false ),
		'REST con vista previa'   => array( array( 'elementor-preview' => '7', 'rest_route' => '/wp/v2/pages' ), false, false, false ),
		'pantalla del editor'     => array( array( 'action' => 'elementor', 'post' => '7' ), true, false, true ),
		'vista previa'            => array( array( 'elementor-preview' => '7' ), false, false, true ),
	) as $label => $case ) {
		guard_boot( $case[0], $case[1] );
		Unknown_Guard::init();
		$hooked = (bool) has_filter( 'elementor/document/load/data', array( Unknown_Guard::class, 'filter_load_data' ) );
		check_design( $case[3] === $hooked, "Guarda en {$label}: " . ( $case[3] ? 'enganchada' : 'nada' ) . '.' );
		if ( $hooked ) {
			check_design( 15 === has_filter( 'elementor/document/load/data', array( Unknown_Guard::class, 'filter_load_data' ) ), 'Prioridad 15, antes de la 20 de K.' );
		}
	}

	// 2. Envolver al cargar en el editor.
	$manager = guard_boot( array( 'action' => 'elementor', 'post' => '7' ), true );
	Unknown_Guard::init();
	$tree    = guard_tree();
	$loaded  = apply_filters( 'elementor/document/load/data', $tree, null );
	$index   = guard_index( $loaded );
	check_design( isset( $manager->types['digitalisimo-unconverted'] ), 'El marcador de K queda registrado (ensure_widget).' );
	foreach ( array( 'f000001' => 'digitalisimo-woo-future', 'f000002' => 'digitalisimo-woo-otro' ) as $id => $type ) {
		$marker = $index[ $id ] ?? array();
		$info   = Preserve::info( $marker );
		$orig   = guard_index( $tree )[ $id ];
		check_design( 'digitalisimo-unconverted' === ( $marker['widgetType'] ?? '' ) && null !== $info && $orig === $info['element'] && 'unknown' === $info['reason'] && 'library' === $info['source'] && 'widget/' . $type === $info['type'], "{$type}: envuelto en el marcador con su original completo." );
	}
	check_design( $tree[0]['elements'][0] === $index['h000001'] && $tree[0]['elements'][2] === $index['a000001'] && 'c000002' === $loaded[0]['elements'][3]['id'], 'Lo conocido y lo ajeno (acme-*) no se tocan; los contenedores siguen en su sitio.' );
	$plain = array( $tree[0]['elements'][0] );
	check_design( $plain === apply_filters( 'elementor/document/load/data', $plain, null ), 'Sin widgets desconocidos, el árbol sale idéntico.' );
	$GLOBALS['dh']['users'][1]['caps'] = array( 'manage_options' );
	check_design( $tree === apply_filters( 'elementor/document/load/data', $tree, null ), 'Sin edit_posts no se envuelve nada.' );
	$GLOBALS['dh']['users'][1]['caps'] = array( 'manage_options', 'edit_posts', 'unfiltered_html', 'edit_theme_options' );

	// 3. Guardar: sin la guarda el núcleo descarta el widget; con ella, el marcador sobrevive.
	$saved_raw = guard_index( guard_save( $tree ) );
	check_design( ! isset( $saved_raw['f000001'] ) && ! isset( $saved_raw['f000002'] ) && isset( $saved_raw['h000001'] ), 'Sin la guarda, guardar borra los widgets desconocidos (lo que pasaba en 4.3.0.164).' );
	$saved = guard_index( guard_save( $loaded ) );
	foreach ( array( 'f000001', 'f000002' ) as $id ) {
		$info = Preserve::info( $saved[ $id ] ?? array() );
		check_design( null !== $info && md5( json_encode( guard_index( $tree )[ $id ] ) ) === md5( json_encode( $info['element'] ) ), "{$id}: el marcador guardado lleva el mismo original (md5)." );
	}

	// 4. Cuando el tipo existe, K lo devuelve.
	$manager->register( new Guard_Future_Widget( array(), null ) );
	Preserve::reset();
	$back = guard_index( Preserve::restore( guard_save( $loaded ) ) );
	check_design( guard_index( $tree )['f000001'] === $back['f000001'] && 'digitalisimo-unconverted' === $back['f000002']['widgetType'], 'Con el tipo registrado, K devuelve el original; el que sigue sin existir queda en el marcador.' );
	Unknown_Guard::reset();
	Unknown_Guard::init();
	check_design( $tree[0]['elements'][1] === guard_index( apply_filters( 'elementor/document/load/data', array( $tree[0]['elements'][1] ), null ) )['f000001'], 'Un digitalisimo-* registrado no se envuelve.' );

	// 5. AJAX del editor: el marcador se registra antes de atender el guardado.
	foreach ( array(
		'guardar con marcadores'      => array( '{"save_builder":{"action":"save_builder","data":{"elements":[{"widgetType":"digitalisimo-unconverted"}]}}}', true ),
		'pedir la configuración'      => array( '{"get_widgets_config":{"action":"get_widgets_config"}}', true ),
		'guardar sin marcadores'      => array( '{"save_builder":{"action":"save_builder","data":{"elements":[]}}}', false ),
	) as $label => $case ) {
		$manager = guard_boot( array( 'action' => 'elementor_ajax' ), true, true );
		$GLOBALS['dh']['ajax'] = true;
		$_POST['actions'] = $case[0];
		Unknown_Guard::reset();
		check_design( Unknown_Guard::ajax_needs_marker() === $case[1], "AJAX del editor, {$label}: " . ( $case[1] ? 'registra el marcador' : 'no hace falta' ) . '.' );
		Unknown_Guard::register_marker();
		check_design( isset( $manager->types['digitalisimo-unconverted'] ) && Preserve::should_register(), "AJAX del editor, {$label}: register_marker() registra el marcador y K lo espera." );
	}
	$_POST = array();

	// 6. Inert con el núcleo real: guardar no borra ajustes, con la clase del widget o con el sustituto.
	$manager  = guard_boot( array( 'action' => 'elementor', 'post' => '7' ), true );
	$property = new ReflectionProperty( Inert::class, 'active' );
	if ( PHP_VERSION_ID < 80100 ) {
		$property->setAccessible( true );
	}
	$property->setValue( null, true );
	$manager->register( new Guard_Woo_Widget( array(), null ) );
	$manager->register( new Inert_Widget( array(), array( Inert_Widget::ARG_NAME => 'digitalisimo-woo-swatches', Inert_Widget::ARG_TITLE => 'Muestras de variación' ) ) );
	check_design( Inert::cooperates( Guard_Woo_Widget::class ) && ! Inert::cooperates( Guard_Future_Widget::class ), 'cooperates(): sólo las subclases de Woo\\Widget que no redefinen render().' );
	check_design( false === $manager->types['digitalisimo-woo-wishlist']->show_in_panel() && false === $manager->types['digitalisimo-woo-swatches']->show_in_panel() && 'digitalisimo-woo-swatches' === $manager->types['digitalisimo-woo-swatches']->get_name(), 'Ocultos del panel; el sustituto conserva el nombre.' );
	$settings = array( 'empty_text' => 'Nada aún', 'digi_columns' => array( 'size' => 3, 'unit' => 'px' ), 'digi_parts' => array( 'image', 'price' ), 'digi_free' => 'libre' );
	$docs     = array(
		array( 'id' => 'c100001', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			array( 'id' => 'w100001', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-wishlist', 'settings' => $settings, 'elements' => array() ),
			array( 'id' => 'w100002', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-swatches', 'settings' => array( 'attribute' => 'pa_color', 'max' => 4, 'digi_any' => array( 'x' => array( 'y' => 'z' ) ) ), 'elements' => array() ),
		) ),
	);
	$saved = guard_index( guard_save( $docs ) );
	check_design( $settings === $saved['w100001']['settings'] && 'digitalisimo-woo-wishlist' === $saved['w100001']['widgetType'], 'Con su clase (mismos controles), el widget guarda todos sus ajustes, también los que no tienen control.' );
	check_design( $docs[0]['elements'][1]['settings'] === $saved['w100002']['settings'] && 'digitalisimo-woo-swatches' === $saved['w100002']['widgetType'], 'Con el sustituto, sin controles, los ajustes se guardan tal cual.' );
	\Elementor\Plugin::$instance->editor->on = true;
	$instance = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $docs[0]['elements'][0] );
	$render = new ReflectionMethod( $instance, 'render' );
	if ( PHP_VERSION_ID < 80100 ) {
		$render->setAccessible( true );
	}
	ob_start();
	$render->invoke( $instance );
	$html = (string) ob_get_clean();
	check_design( false !== strpos( $html, 'Necesita WooCommerce' ) && false === strpos( $html, 'LISTA' ), 'En el editor, el widget sin WooCommerce pinta el aviso, nunca su salida.' );
	\Elementor\Plugin::$instance->editor->on = false;
	$property->setValue( null, false );

	echo "F4-Woo · guarda hacia delante: sólo en el editor, digitalisimo-* desconocidos al marcador de K con su original, guardado sin pérdidas y vuelta al existir; Inert guarda sin perder ajustes, en verde.\n";
}
