<?php
/**
 * Acciones de terceros del Formulario (C6, docs/pro/c6.md, «Correcciones de fidelidad»): el mapa
 * `modules/digitalisimo-convert/maps/form.php` conserva con `keep` los controles que una acción de envío de otro plugin
 * (MailPoet, FluentCRM…) añade a los dos formularios, también en una petición que empieza convirtiendo, antes de que
 * nada haya registrado los widgets (un tic de «Migrar» por AJAX o WP-Cron que empieza en la etapa `pro`, o un kit).
 * - antes de `elementor/init` no puede conocerlas: el mapa lleva `retry` y `Registry` no lo guarda en caché;
 * - después, si `elementor/widgets/register` aún no corrió, el mapa registra los widgets (`get_widget_types()`) y la
 *   clave de la acción de terceros, también la de cada campo, pasa con `keep`;
 * - con los widgets ya registrados, igual, sin volver a registrarlos.
 * Con el código anterior, la clave quedaba como `pro.unmapped` y la integración dejaba de recibir envíos.
 *
 * Uso: php-wasm-cli tests/elements-convert-form-live.php. php-wasm sale con 0 aunque haya un error fatal: se juzga por la
 * línea final.
 */
namespace Elementor {
	class Plugin {
		public static $instance;
		public $widgets_manager;
	}
}
namespace Digitalisimo\Elements\Forms {
	/** El módulo de formularios: carga el Formulario propio. */
	final class Module {
		public static function load( string $name ): string { return 'digitalisimo-form' === $name ? Widgets\Form::class : ''; }
	}
}
namespace Digitalisimo\Elements\Forms\Widgets {
	/** El Formulario propio: sus controles incluyen los de las acciones registradas (la de terceros, si lo está). */
	final class Form {
		public function get_controls(): array {
			$controls = array(
				'form_name'   => array( 'type' => 'text' ),
				'form_fields' => array( 'type' => 'form-fields-repeater', 'fields' => array( 'field_type' => array( 'name' => 'field_type' ) ) ),
			);
			if ( $GLOBALS['fl']['third_party'] ) {
				$controls['digi_fake_list']                          = array( 'type' => 'select' );
				$controls['form_fields']['fields']['digi_fake_attr'] = array( 'name' => 'digi_fake_attr' );
			}
			return $controls;
		}
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['fl'] = array( 'did' => array(), 'third_party' => false, 'registered' => 0 );
	function did_action( $hook ) { return count( array_keys( $GLOBALS['fl']['did'], $hook, true ) ); }
	function do_action( $hook ) {
		$GLOBALS['fl']['did'][] = $hook;
		// Al registrar los widgets, el constructor registra las acciones de envío; la de terceros entre ellas.
		if ( 'elementor/widgets/register' === $hook ) {
			$GLOBALS['fl']['third_party'] = true;
		}
	}
	function check_fl( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'FALLA: ' . $message ); } }

	\Elementor\Plugin::$instance                  = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->widgets_manager = new class() {
		private $types;
		public function get_widget_types( $name = null ) {
			if ( null === $this->types ) {
				$this->types = array();
				$GLOBALS['fl']['registered']++;
				do_action( 'elementor/widgets/register' );
				$this->types = array( 'form' => 1, 'digitalisimo-form' => 1 );
			}
			return null === $name ? $this->types : ( $this->types[ $name ] ?? null );
		}
	};

	$root = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-convert/';
	require $root . 'class-catalog.php';
	require $root . 'class-registry.php';
	use Digitalisimo\Elements\Convert\Registry;

	// 1. Antes de `elementor/init`: sin las acciones de terceros, y el mapa no se guarda en caché.
	$early = Registry::map( 'form' );
	check_fl( is_array( $early ) && ! in_array( 'digi_fake_list', $early['keep'], true ) && true === $early['retry'] && 0 === $GLOBALS['fl']['registered'], 'Antes de elementor/init el mapa no registra nada y pide volver a leerse.' );

	// 2. Con el constructor listo y los widgets sin registrar: el mapa los registra y conserva la clave de terceros.
	do_action( 'elementor/init' );
	$map = Registry::map( 'form' );
	$row = $map['repeaters']['form_fields']['keep'] ?? array();
	check_fl( in_array( 'digi_fake_list', $map['keep'], true ) && in_array( 'digi_fake_attr', $row, true ) && false === $map['retry'], 'La acción de terceros pasa con keep, también su clave de cada campo: ' . implode( ',', array_slice( $map['keep'], -3 ) ) );
	check_fl( 1 === $GLOBALS['fl']['registered'] && 1 === did_action( 'elementor/widgets/register' ), 'Los widgets se registran una sola vez.' );
	check_fl( $map === Registry::map( 'form' ) && 1 === $GLOBALS['fl']['registered'], 'El mapa completo sí se guarda en caché.' );

	// 3. Con los widgets ya registrados, el mapa no los vuelve a registrar.
	Registry::reset();
	check_fl( in_array( 'digi_fake_list', Registry::map( 'form' )['keep'], true ) && 1 === $GLOBALS['fl']['registered'], 'Con los widgets registrados, la misma lista sin registrarlos otra vez.' );
	check_fl( isset( Registry::all()['form'] ) && in_array( 'digi_fake_list', Registry::all()['form']['keep'], true ), 'Registry::all() (lo que lee Pro::ready() en Migración) da el mapa completo.' );

	echo "DIGITALÍSIMO Elements: acciones de terceros del Formulario en su mapa (antes y después de registrar los widgets) validadas.\n";
}
