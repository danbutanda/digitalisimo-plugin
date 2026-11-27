<?php
/**
 * Un solo H1 en las fichas de producto (`Woo\Single\Single_H1`, B2 de la auditoría de digitalisimo.lat del 2026-11-24:
 * siete fichas con dos H1, el de WooCommerce y el Encabezado del encabezado del sitio con «Título de la página» en H1).
 * Sin WordPress:
 *   H1  el control opcional sólo en los encabezados derivados del Encabezado, justo tras «Etiqueta HTML», vacío por
 *       defecto y sin H1 entre sus opciones;
 *   H2  sin elegirlo nada cambia, en ninguna página; elegido, sólo en una ficha de producto del front (no en el editor
 *       ni en su vista previa, ni en otras páginas) el elemento usa esa etiqueta; un valor raro se ignora;
 *   H3  con la opción elegida el elemento sale de la caché de elementos; sin ella, la decisión del núcleo no cambia;
 *   H4  arranca sólo con WooCommerce (está en `Woo\Module::BOOT`).
 */
namespace Elementor {
	class Plugin {
		public static $instance;
	}
}

namespace {
	require_once __DIR__ . '/fixtures/php74.php';
	define( 'ABSPATH', __DIR__ . '/' );
	set_error_handler(
		static function ( $severity, $message, $file, $line ) {
			throw new ErrorException( $message . ' en ' . basename( $file ) . ':' . $line, 0, $severity, $file, $line );
		}
	);
	$GLOBALS['h1_fail']    = array();
	$GLOBALS['h1_count']   = 0;
	$GLOBALS['h1_hooks']   = array();
	$GLOBALS['is_product'] = false;
	function h1_check( $ok, string $message ) {
		++$GLOBALS['h1_count'];
		if ( ! $ok ) {
			$GLOBALS['h1_fail'][] = $message;
		}
	}
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['h1_hooks'][ $hook ][] = $cb; }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['h1_hooks'][ $hook ][] = $cb; }
	function is_product() { return $GLOBALS['is_product']; }

	/** Un widget del constructor, con lo justo: controles con inyección, ajustes y reinicio del render. */
	class H1_Widget {
		public $name;
		public $settings;
		public $controls = array( 'title' => array( 'type' => 'text' ), 'header_size' => array( 'type' => 'select' ) );
		public $order    = array( 'title', 'header_size', 'align' );
		public $injection = null;
		public $resets    = 0;
		public function __construct( $name, $settings = array() ) { $this->name = $name; $this->settings = $settings; }
		public function get_name() { return $this->name; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function set_settings( $key, $value = null ) { $this->settings[ $key ] = $value; }
		public function reset_render_state() { ++$this->resets; }
		public function get_controls( $id = null ) { return null === $id ? $this->controls : ( $this->controls[ $id ] ?? null ); }
		public function start_injection( $args ) { $this->injection = $args; }
		public function end_injection() { $this->injection = null; }
		public function add_control( $id, $args ) {
			$this->controls[ $id ] = $args;
			if ( $this->injection && 'header_size' === $this->injection['of'] && 'after' === $this->injection['at'] ) {
				array_splice( $this->order, array_search( 'header_size', $this->order, true ) + 1, 0, array( $id ) );
			} else {
				$this->order[] = $id;
			}
		}
	}

	$root = dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-woo';
	$file = $root . '/single/class-single-h1.php';
	h1_check( is_file( $file ), 'H0 existe Woo\\Single\\Single_H1' );
	if ( ! is_file( $file ) ) {
		echo "FALLA: no existe Woo\\Single\\Single_H1\n";
		exit( 1 );
	}
	require $file;
	$S = '\\Digitalisimo\\Elements\\Woo\\Single\\Single_H1';
	$S::init();
	h1_check( isset( $GLOBALS['h1_hooks']['elementor/element/before_section_end'], $GLOBALS['h1_hooks']['elementor/frontend/widget/before_render'], $GLOBALS['h1_hooks']['elementor/element/is_dynamic_content'] ), 'H0 ganchos del control, del render y de la caché' );

	// ------------------------------------------------------------------------------------------------- H1 control.
	foreach ( array( 'heading', 'theme-page-title', 'theme-post-title', 'woocommerce-product-title', 'digitalisimo-woo-product-title' ) as $name ) {
		$w = new H1_Widget( $name );
		$S::register_control( $w, 'section_title', array() );
		h1_check( array( 'title', 'header_size', 'digitalisimo_product_tag', 'align' ) === $w->order, 'H1 control tras «Etiqueta HTML» en ' . $name . ': ' . implode( ',', $w->order ) );
		h1_check( null === $w->injection, 'H1 la inyección se cierra en ' . $name );
	}
	$control = $w->controls['digitalisimo_product_tag'];
	h1_check( '' === $control['default'] && 'select' === $control['type'] && 'Etiqueta en las fichas de producto' === $control['label'], 'H1 vacío por defecto' );
	h1_check( array( '', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' ) === array_keys( $control['options'] ) && ! isset( $control['options']['h1'] ), 'H1 opciones sin H1' );
	h1_check( str_contains( $control['description'], 'un solo H1' ) && str_contains( $control['description'], 'en las demás páginas sigue igual' ), 'H1 la ayuda explica para qué y dónde aplica' );
	$w = new H1_Widget( 'text-editor' );
	$S::register_control( $w, 'section_title', array() );
	$w2 = new H1_Widget( 'heading' );
	$S::register_control( $w2, 'section_style', array() );
	h1_check( ! isset( $w->controls['digitalisimo_product_tag'] ) && ! isset( $w2->controls['digitalisimo_product_tag'] ), 'H1 nada en otros widgets ni en otras secciones' );
	$w3 = new H1_Widget( 'heading' );
	unset( $w3->controls['header_size'] );
	$S::register_control( $w3, 'section_title', array() );
	h1_check( isset( $w3->controls['digitalisimo_product_tag'] ) && null === $w3->injection, 'H1 sin «Etiqueta HTML», el control va al final de la sección' );

	// ------------------------------------------------------------------------------------------------- H2 render.
	$plain = new H1_Widget( 'heading', array( 'header_size' => 'h1' ) );
	$GLOBALS['is_product'] = true;
	$S::before_render( $plain );
	h1_check( 'h1' === $plain->settings['header_size'] && 0 === $plain->resets, 'H2 sin elegirlo, en una ficha de producto sigue en H1' );
	$opted = new H1_Widget( 'heading', array( 'header_size' => 'h1', 'digitalisimo_product_tag' => 'p' ) );
	$GLOBALS['is_product'] = false;
	$S::before_render( $opted );
	h1_check( 'h1' === $opted->settings['header_size'] && 0 === $opted->resets, 'H2 elegido, fuera de una ficha de producto sigue en H1' );
	$GLOBALS['is_product'] = true;
	$S::before_render( $opted );
	h1_check( 'p' === $opted->settings['header_size'] && 1 === $opted->resets, 'H2 elegido, en una ficha de producto pasa a p y se recalcula el render' );
	$S::before_render( $opted );
	h1_check( 1 === $opted->resets, 'H2 ya con la etiqueta elegida no se recalcula otra vez' );
	foreach ( array( 'h1', 'script', 'H2', array( 'h2' ), 1 ) as $bad ) {
		$weird = new H1_Widget( 'heading', array( 'header_size' => 'h1', 'digitalisimo_product_tag' => $bad ) );
		$S::before_render( $weird );
		h1_check( 'h1' === $weird->settings['header_size'], 'H2 un valor raro se ignora: ' . json_encode( $bad ) );
	}
	$other = new H1_Widget( 'text-editor', array( 'header_size' => 'h1', 'digitalisimo_product_tag' => 'h2' ) );
	$S::before_render( $other );
	h1_check( 'h1' === $other->settings['header_size'], 'H2 otro widget con la clave no cambia' );
	\Elementor\Plugin::$instance = (object) array(
		'editor'  => new class() { public $edit = true; public function is_edit_mode() { return $this->edit; } },
		'preview' => new class() { public $preview = false; public function is_preview_mode() { return $this->preview; } },
	);
	$editing = new H1_Widget( 'heading', array( 'header_size' => 'h1', 'digitalisimo_product_tag' => 'h2' ) );
	$S::before_render( $editing );
	h1_check( 'h1' === $editing->settings['header_size'], 'H2 en el editor no cambia' );
	\Elementor\Plugin::$instance->editor->edit       = false;
	\Elementor\Plugin::$instance->preview->preview   = true;
	$S::before_render( $editing );
	h1_check( 'h1' === $editing->settings['header_size'], 'H2 en la vista previa del editor no cambia' );
	\Elementor\Plugin::$instance->preview->preview = false;
	$S::before_render( $editing );
	h1_check( 'h2' === $editing->settings['header_size'], 'H2 en el front sí' );
	$S::before_render( null );
	$S::before_render( new stdClass() );
	h1_check( true, 'H2 algo que no es un widget no rompe' );

	// ------------------------------------------------------------------------------------------------- H3 caché.
	h1_check( true === $S::is_dynamic_content( false, array( 'widgetType' => 'heading', 'settings' => array( 'digitalisimo_product_tag' => 'h2' ) ) ), 'H3 elegido: fuera de la caché de elementos' );
	h1_check( false === $S::is_dynamic_content( false, array( 'widgetType' => 'heading', 'settings' => array( 'digitalisimo_product_tag' => '' ) ) ), 'H3 sin elegir: la caché decide como siempre' );
	h1_check( false === $S::is_dynamic_content( false, array( 'widgetType' => 'heading', 'settings' => array() ) ), 'H3 sin la clave: igual' );
	h1_check( false === $S::is_dynamic_content( false, array( 'widgetType' => 'button', 'settings' => array( 'digitalisimo_product_tag' => 'h2' ) ) ), 'H3 otro widget: igual' );
	h1_check( true === $S::is_dynamic_content( true, array() ) && false === $S::is_dynamic_content( false, 'x' ), 'H3 una decisión previa se respeta' );

	// ------------------------------------------------------------------------------------------------- H4 arranque.
	$module = file_get_contents( $root . '/class-module.php' );
	h1_check( (bool) preg_match( "/const BOOT = array\([^;]*'Single\\\\\\\\Single_H1'/s", $module ), 'H4 en Woo\\Module::BOOT (sólo con WooCommerce)' );

	if ( $GLOBALS['h1_fail'] ) {
		foreach ( $GLOBALS['h1_fail'] as $failure ) {
			echo 'FALLA: ' . $failure . "\n";
		}
		echo count( $GLOBALS['h1_fail'] ) . ' de ' . $GLOBALS['h1_count'] . " comprobaciones fallaron.\n";
		exit( 1 );
	}
	echo 'Un solo H1 en las fichas de producto: ' . $GLOBALS['h1_count'] . " comprobaciones correctas.\n";
}
