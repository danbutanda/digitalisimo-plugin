<?php
/**
 * Kits (etapa K): el widget global de un kit nunca se instancia a sí mismo (R3, `tercero-pro3.zip`).
 *
 * Un kit de terceros puede traer instancias ya globales (`widgetType = global` con `templateID`). Al guardarlas,
 * el módulo derivado de PRO les ponía `originalWidgetType = global`, y si su plantilla no existía (su `templateID`
 * no se traducía y la importación del widget global renumeraba los IDs de origen con `array_merge()`), el widget
 * tomaba como original a sí mismo: `get_controls()` se llamaba sin fin y la petición final agotaba la memoria.
 *
 * Sin WordPress: tests/sources/kits/wp-stubs.php, stubs mínimos del widget base y los archivos reales de
 * digitalisimo-elements/ (derivados de PRO y `Kits\Remapper`).
 *  1. `Global_Widget` con `originalWidgetType = global` y plantilla inexistente: no se instancia a sí mismo.
 *  2. Plantilla cuyo contenido es otra instancia global: tampoco.
 *  3. Plantilla normal: el original es su widget y `get_controls()` son los suyos.
 *  4. El guardado de PRO nunca deja `originalWidgetType = global`.
 *  5. La importación del widget global conserva el ID de origen como clave (no `array_merge()`).
 *  6. `Remapper` traduce `templateID` de las instancias globales (también dentro de un marcador) y, sin destino,
 *     lo deja vacío e informa.
 */

namespace Elementor {
	class Widget_Base {
		/** @var int Instancias creadas: un tope detecta la recursión antes de agotar la memoria. */
		public static $created = 0;
		protected $data_store = array();
		public function __construct( $data = array(), $args = null ) {
			if ( ++self::$created > 50 ) {
				throw new \RuntimeException( 'recursión: más de 50 instancias de widget' );
			}
			$this->data_store = is_array( $data ) ? $data : array();
		}
		public function get_name() {
			return 'widget';
		}
		public function get_data( $item = null ) {
			if ( null === $item ) {
				return $this->data_store;
			}
			return isset( $this->data_store[ $item ] ) ? $this->data_store[ $item ] : null;
		}
		public function get_id() {
			return isset( $this->data_store['id'] ) ? $this->data_store['id'] : '';
		}
		public function is_type_instance() {
			return empty( $this->data_store );
		}
		public function get_raw_data( $with_html_content = false ) {
			return $this->data_store;
		}
		public function get_controls( $control_id = null ) {
			return array( 'widget_control' => array( 'type' => 'text' ) );
		}
		public function get_class_name() {
			return get_class( $this );
		}
		public function get_default_args() {
			return array();
		}
	}
	class Heading_Stub extends Widget_Base {
		public function get_name() {
			return 'heading';
		}
		public function get_controls( $control_id = null ) {
			return array( 'title' => array( 'type' => 'text' ) );
		}
	}
	class Plugin {
		public static $instance;
		public $templates_manager;
		public $widgets_manager;
		public $elements_manager;
		public $common;
		public $experiments;
	}
}

namespace Elementor\Core\Base {
	class Document {}
}

namespace Elementor\TemplateLibrary {
	class Source_Local {
		const CPT = 'elementor_library';
	}
}

namespace Elementor\App\Modules\ImportExportCustomization {
	class Utils {
		public static $files = array();
		public static function read_json_file( $path ) {
			return isset( self::$files[ $path ] ) ? self::$files[ $path ] : array();
		}
	}
}

namespace ElementorPro {
	class Plugin {
		public static function elementor() {
			return \Elementor\Plugin::$instance;
		}
	}
}

namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
}

namespace ElementorPro\Modules\GlobalWidget {
	class Module {
		const TEMPLATE_TYPE = 'widget';
	}
}

namespace {
	require __DIR__ . '/sources/kits/wp-stubs.php';
	$E = __DIR__ . '/../digitalisimo-elements';
	require $E . '/modules/global-widget/widgets/global-widget.php';
	require $E . '/modules/global-widget/import-export-customization/import.php';
	require $E . '/modules/digitalisimo-import/class-module.php';

	use ElementorPro\Modules\GlobalWidget\Widgets\Global_Widget;

	$failures = array();
	$check    = static function ( $ok, $message ) use ( &$failures ) {
		if ( ! $ok ) {
			$failures[] = $message;
		}
	};

	// Constructor mínimo: plantillas por ID, tipos de widget y sin AJAX del editor.
	$templates = array();
	$plugin    = new \Elementor\Plugin();
	$plugin->templates_manager = new class( $templates ) {
		public $templates;
		public function __construct( &$templates ) {
			$this->templates = &$templates;
		}
		public function get_template_data( $args ) {
			$id = (int) $args['template_id'];
			return isset( $this->templates[ $id ] ) ? array( 'content' => $this->templates[ $id ] ) : new WP_Error( 'template_error', 'Template not found.' );
		}
	};
	$plugin->widgets_manager = new class() {
		public function get_widget_types( $name = null ) {
			if ( 'global' === $name ) {
				return new Global_Widget();
			}
			if ( 'heading' === $name ) {
				return new \Elementor\Heading_Stub();
			}
			return null;
		}
	};
	$plugin->common      = null;
	$plugin->experiments = new class() {
		public function is_feature_active( $name ) {
			return false;
		}
	};
	\Elementor\Plugin::$instance = $plugin;

	$build = static function ( array $data ) {
		\Elementor\Widget_Base::$created = 0;
		try {
			$widget = new Global_Widget( $data, array() );
			return array( $widget, $widget->get_controls(), '' );
		} catch ( \Throwable $error ) {
			return array( null, null, $error->getMessage() );
		}
	};

	// 1. La forma que dejaba el guardado: instancia global, `originalWidgetType = global` y plantilla inexistente.
	list( $widget, $controls, $error ) = $build(
		array(
			'id'                 => 'a71b005',
			'elType'             => 'widget',
			'widgetType'         => 'global',
			'originalWidgetType' => 'global',
			'templateID'         => 605,
			'settings'           => array(),
			'elements'           => array(),
		)
	);
	$check( '' === $error, '1: el widget global sin plantilla no debe instanciarse a sí mismo (' . $error . ').' );
	$check( array() === $controls, '1: sin original, sin controles.' );
	$check( null !== $widget && null === $widget->get_original_element_instance(), '1: sin original.' );

	// 2. Una plantilla cuyo contenido es otra instancia global.
	$templates[700] = array(
		array(
			'id'         => 'b0000001',
			'elType'     => 'widget',
			'widgetType' => 'global',
			'templateID' => 700,
			'settings'   => array(),
		),
	);
	list( $widget, $controls, $error ) = $build(
		array(
			'id'         => 'a71b006',
			'elType'     => 'widget',
			'widgetType' => 'global',
			'templateID' => 700,
			'settings'   => array(),
		)
	);
	$check( '' === $error, '2: una plantilla que contiene un widget global no debe instanciarse sin fin (' . $error . ').' );
	$check( array() === $controls, '2: sin original válido, sin controles.' );

	// 3. Una plantilla normal: el original es su widget.
	$templates[701] = array(
		array(
			'id'         => 'b0000002',
			'elType'     => 'widget',
			'widgetType' => 'heading',
			'settings'   => array( 'title' => 'Hola' ),
		),
	);
	list( $widget, $controls, $error ) = $build(
		array(
			'id'                 => 'a71b007',
			'elType'             => 'widget',
			'widgetType'         => 'global',
			'originalWidgetType' => 'heading',
			'templateID'         => 701,
			'settings'           => array(),
		)
	);
	$check( '' === $error && array( 'title' => array( 'type' => 'text' ) ) === $controls, '3: con su plantilla, los controles son los del original: ' . $error . ' ' . json_encode( $controls ) );
	$original = null === $widget ? null : $widget->get_original_element_instance();
	$check( $original instanceof \Elementor\Heading_Stub && 'a71b007' === $original->get_id(), '3: el original es el widget de la plantilla, con el ID de la instancia.' );

	// Sin plantilla pero con su tipo original de verdad: el original se resuelve por el tipo.
	list( $widget, $controls, $error ) = $build(
		array(
			'id'                 => 'a71b008',
			'elType'             => 'widget',
			'widgetType'         => 'global',
			'originalWidgetType' => 'heading',
			'templateID'         => 999,
			'settings'           => array(),
		)
	);
	$check( '' === $error && isset( $controls['title'] ), '3: sin plantilla, el tipo original real se sigue usando.' );

	// 4. El guardado de PRO no deja `originalWidgetType = global` (el cierre de `get_document_data()`).
	$module = (string) file_get_contents( $E . '/modules/global-widget/module.php' );
	$check( 1 === preg_match( "/'global'\s*!==\s*\\\$element\['widgetType'\]\s*\)\s*\{\s*\\\$element\['originalWidgetType'\]\s*=\s*\\\$element\['widgetType'\]/", $module ), '4: el guardado sólo copia el tipo original si no es ya «global».' );
	$check( false !== strpos( $module, "unset( \$element['originalWidgetType'] )" ), '4: un `originalWidgetType = global` previo se quita al guardar.' );

	// 5. La importación del widget global conserva los IDs de origen como claves del resultado.
	add_filter(
		'digitalisimo_elements_kit_existing_document',
		static function ( $id, $type = '', $old = 0 ) {
			return 'widget' === $type && '605' === (string) $old ? 777 : $id;
		},
		10,
		3
	);
	\Elementor\App\Modules\ImportExportCustomization\Utils::$files['/kit/templates/605'] = array( 'content' => array() );
	$result = ( new \ElementorPro\Modules\GlobalWidget\ImportExportCustomization\Import() )->add_global_widgets_to_import(
		array(
			'templates' => array(
				'succeed'         => array( 601 => 11 ),
				'failed'          => array(),
				'succeed_summary' => array( 'header' => 1 ),
			),
		),
		array(
			'extracted_directory_path' => '/kit/',
			'manifest'                 => array(
				'templates' => array(
					601 => array( 'doc_type' => 'header', 'title' => 'Cabecera' ),
					605 => array( 'doc_type' => 'widget', 'title' => 'Widget global' ),
				),
			),
		),
		null,
		null
	);
	$check( array( 601 => 11, 605 => 777 ) === $result['templates']['succeed'], '5: el ID de origen 605 sigue siendo la clave: ' . json_encode( $result['templates']['succeed'] ) );
	$check( array( 'header' => 1, 'widget' => 1 ) === $result['templates']['succeed_summary'], '5: el resumen suma el widget.' );

	// 6. `Remapper` traduce `templateID` (en el árbol y dentro de un marcador) y, sin destino, lo vacía e informa.
	\Digitalisimo\Elements\Import\Module::register_autoloader();
	$context = new \Digitalisimo\Elements\Import\Kits\Import_Context(
		array(
			'session'     => 'kitglobal01',
			'mode'        => 'add',
			'kit'         => str_repeat( 'a', 40 ),
			'max_post_id' => 100,
			'maps'        => array(
				'template' => array( 605 => 100003 ),
				'post'     => array( 605 => 100003 ),
			),
		)
	);
	$context->begin_part( 'remap' );
	$remapped = ( new \Digitalisimo\Elements\Import\Kits\Remapper(
		$context,
		array(
			'post_type' => static function ( $id ) {
				return 'elementor_library';
			},
			'exists'    => static function () {
				return false;
			},
		)
	) )->apply_elements(
		array(
			array(
				'id'       => 'c0000001',
				'elType'   => 'container',
				'settings' => array(),
				'elements' => array(
					array( 'id' => 'c0000002', 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 605, 'settings' => array(), 'elements' => array() ),
					array( 'id' => 'c0000003', 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => '605', 'settings' => array(), 'elements' => array() ),
					array( 'id' => 'c0000004', 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 606, 'settings' => array(), 'elements' => array() ),
					array(
						'id'         => 'c0000005',
						'elType'     => 'widget',
						'widgetType' => 'digitalisimo-unconverted',
						'settings'   => array(
							'digitalisimo_unconverted' => array(
								'v'       => 1,
								'type'    => 'acme/',
								'reason'  => 'unknown',
								'source'  => 'kit',
								'element' => array( 'id' => 'c0000006', 'elType' => 'acme', 'settings' => array(), 'elements' => array( array( 'id' => 'c0000007', 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 605, 'settings' => array(), 'elements' => array() ) ) ),
							),
						),
						'elements'   => array(),
					),
				),
			),
		),
		150
	);
	$context->end_part();
	$inner = $remapped[0]['elements'];
	$check( 100003 === $inner[0]['templateID'], '6: templateID entero traducido: ' . json_encode( $inner[0]['templateID'] ) );
	$check( '100003' === $inner[1]['templateID'], '6: templateID en texto traducido y en texto: ' . json_encode( $inner[1]['templateID'] ) );
	$check( '' === $inner[2]['templateID'], '6: templateID sin destino queda vacío: ' . json_encode( $inner[2]['templateID'] ) );
	$check( 100003 === $inner[3]['settings']['digitalisimo_unconverted']['element']['elements'][0]['templateID'], '6: dentro de la carga de un marcador también se traduce.' );
	$unresolved = array_values(
		array_filter(
			$context->get_report()['entries'],
			static function ( $entry ) {
				return 'unresolved' === $entry['code'] && 'templateID' === $entry['data']['path'];
			}
		)
	);
	$check( 1 === count( $unresolved ) && array( 606 ) === $unresolved[0]['data']['values'], '6: el templateID sin destino se informa una vez: ' . json_encode( $unresolved ) );

	if ( $failures ) {
		// Una excepción y no `exit( 1 )`: php-wasm sale con 0 aunque el script termine con error.
		throw new \RuntimeException( 'elements-kit-global-widget: FALLA en ' . count( $failures ) . " comprobación(es)\n - " . implode( "\n - ", $failures ) );
	}
	echo "elements-kit-global-widget: ok\n";
}
