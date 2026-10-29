<?php
/**
 * F7b, frente J · arnés de `tests/elements-f7b-forms.php`: el **Formulario real de PRO** (`Form`, `Form_Base`,
 * `Fields_Repeater`, `Form_Record` y `Ajax_Handler` de `digitalisimo-elements/modules/forms/`) sobre el núcleo real de
 * `tests/elements-design/core.php`, más dobles mínimos de lo que PRO pide a su propio plugin (`ElementorPro\Plugin`,
 * `Core\Utils`, la licencia, `Forms\Module` con un registro de acciones que captura lo que recibe cada acción) y de las
 * piezas del frente A (`F7b\Module`, `F7b\Flags`, `F7b\Hidden_Rule`) con la firma del contrato.
 */
namespace {
	define( 'DOING_AJAX', true ); // Sólo cuenta como envío con `$_POST['action']`; ver forms_submission().
	define( 'ELEMENTOR_PRO_VERSION', '4.3.0' );
	require __DIR__ . '/../elements-design/core.php';

	function stripslashes_deep( $value ) { return map_deep( $value, static function ( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; } ); }
	function wp_kses_post_deep( $value ) { return $value; }
	function wp_title( $sep = '', $display = true ) { return 'Contacto'; }
	function is_home() { return false; }
	function is_singular( $types = '' ) { return true; }
	function wp_script_is( $handle, $status = 'enqueued' ) {
		$script = $GLOBALS['dh']['scripts'][ $handle ] ?? null;
		return 'registered' === $status ? null !== $script : ! empty( $script['enqueued'] );
	}
	function do_shortcode( $text ) { return $text; }
	function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
	function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function sanitize_email( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_EMAIL ); }
	function date_i18n( $format ) { return '2026-10-29'; }
	function wp_send_json_success( $data = null ) { throw new Forms_Sent( true, $data ); }
	function wp_send_json_error( $data = null ) { throw new Forms_Sent( false, $data ); }

	/** Respuesta del envío (`Ajax_Handler::send()`). */
	final class Forms_Sent extends RuntimeException {
		public $success;
		public $data;
		public function __construct( bool $success, $data ) {
			parent::__construct( 'sent' );
			$this->success = $success;
			$this->data    = $data;
		}
	}
}

namespace ElementorPro {
	class Plugin {
		public static function elementor() { return \Elementor\Plugin::$instance; }
		public static function instance() { return new \stdClass(); }
	}
}

namespace ElementorPro\License {
	class API {
		public static function is_license_active() { return true; }
	}
}

namespace ElementorPro\Core {
	class Utils {
		public static function get_current_post_id() { return 10; }
		public static function _unstable_get_super_global_value( $super_global, $key ) { return $super_global[ $key ] ?? null; }
		public static function get_client_ip() { return '127.0.0.1'; }
	}
}

namespace ElementorPro\Core\Utils {
	class Hints {
		public static function is_dismissed( $key ) { return true; }
	}
}

namespace ElementorPro\Modules\Forms {
	/** Acción que guarda lo que recibe: campos, datos enviados, datos con formato y un atajo `[field id]`. */
	final class Forms_Capture_Action {
		public $name;
		public $runs = array();
		public function __construct( string $name ) { $this->name = $name; }
		public function get_name() { return $this->name; }
		public function get_label() { return $this->name; }
		public function register_settings_section( $widget ) {}
		public function on_export( $element ) { return null; }
		public function run( $record, $ajax_handler ) {
			$this->runs[] = array(
				'fields'    => $record->get( 'fields' ),
				'sent_data' => $record->get( 'sent_data' ),
				'formatted' => $record->get_formatted_data(),
				'shortcode' => $record->replace_setting_shortcodes( 'RFC: [field id="rfc"] · Correo: [field id="email"]' ),
			);
		}
	}
	final class Forms_Actions_Registrar {
		public $actions = array();
		public function get() { return $this->actions; }
	}
	class Module {
		private static $instance;
		public $actions_registrar;
		public static function instance() {
			if ( ! self::$instance ) {
				self::$instance                    = new self();
				self::$instance->actions_registrar = new Forms_Actions_Registrar();
			}
			return self::$instance;
		}
		public function get_component( $name ) { return null; }
		public static function find_element_recursive( $elements, $form_id ) {
			foreach ( $elements as $element ) {
				if ( $form_id === $element['id'] ) {
					return $element;
				}
				if ( ! empty( $element['elements'] ) ) {
					$found = self::find_element_recursive( $element['elements'], $form_id );
					if ( $found ) {
						return $found;
					}
				}
			}
			return false;
		}
	}
}

namespace Digitalisimo\Elements\F7b {
	/** Doble de `F7b\Module` (frente A): guarda, contexto del editor. */
	final class Module {
		public static $editing = false;
		public static $missing = array();
		public static function requires( array $symbols, string $who ): bool {
			foreach ( $symbols as $symbol ) {
				$symbol = ltrim( (string) $symbol, '\\' );
				if ( '()' === substr( $symbol, -2 ) ) {
					$ok = function_exists( substr( $symbol, 0, -2 ) );
				} elseif ( false !== strpos( $symbol, '::' ) ) {
					list( $class, $method ) = explode( '::', $symbol, 2 );
					$ok = class_exists( $class ) && method_exists( $class, $method );
				} else {
					$ok = class_exists( $symbol ) || interface_exists( $symbol );
				}
				if ( ! $ok ) {
					self::$missing[ $who ][] = $symbol;
					return false;
				}
			}
			return true;
		}
		public static function editing(): bool { return self::$editing; }
	}
	/** Doble de `F7b\Flags` (frente A). */
	final class Flags {
		public static $on = array();
		public static function has( string $key ): bool { return ! empty( self::$on[ $key ] ); }
		public static function active( string $key ): bool { return self::has( $key ) || Module::editing(); }
	}
	/** Doble de `F7b\Hidden_Rule` (frente A): la regla una sola vez por CSS de documento. */
	final class Hidden_Rule {
		const RULE = ':is(.elementor-element,.elementor-column)[hidden]{display:none!important}';
		public static $calls = 0;
		private static $done = array();
		public static function add( $post_css ): void {
			++self::$calls;
			$key = spl_object_id( $post_css );
			if ( isset( self::$done[ $key ] ) ) {
				return;
			}
			self::$done[ $key ] = true;
			$post_css->get_stylesheet()->add_raw_css( self::RULE );
		}
	}
}

namespace {
	$pro = __DIR__ . '/../../digitalisimo-elements/';
	foreach ( array( 'base/base-widget-trait.php', 'base/on-import-trait.php', 'base/base-widget.php', 'modules/forms/controls/fields-repeater.php', 'modules/forms/classes/form-base.php', 'modules/forms/widgets/form.php', 'modules/forms/classes/form-record.php', 'modules/forms/classes/ajax-handler.php', 'modules/forms/fields/field-base.php', 'modules/forms/fields/number.php', 'modules/forms/fields/acceptance.php' ) as $file ) {
		require_once $pro . $file;
	}
	foreach ( array( 'rules', 'controls', 'render', 'validation', 'module' ) as $file ) {
		require_once $pro . 'modules/digitalisimo-f7b/forms/class-' . $file . '.php';
	}

	/**
	 * Núcleo nuevo con el control `form-fields-repeater` y el widget `form` registrados, sin ganchos de F7b. `$editing`
	 * fija el contexto del editor y `$flag` la bandera `form_conditions`.
	 */
	function forms_boot( bool $editing = false, bool $flag = true ): void {
		design_wp_reset();
		$GLOBALS['dh']['options']['blogname'] = array( 'value' => 'Sitio', 'autoload' => 'on' );
		add_action(
			'elementor/controls/register',
			static function ( $manager ) {
				$manager->register( new \ElementorPro\Modules\Forms\Controls\Fields_Repeater() );
			}
		);
		// Tipos de campo de PRO que pinta la fixture (sus ganchos de pintado, validación y controles).
		new \ElementorPro\Modules\Forms\Fields\Number();
		new \ElementorPro\Modules\Forms\Fields\Acceptance();
		design_frontend( ! $editing );
		design_boot( array( 'admin' => $editing ) );
		$plugin     = \Elementor\Plugin::$instance;
		$plugin->db = new class() {
			public function switch_to_post( $id ) {}
		};
		$plugin->widgets_manager->types['form'] = new \ElementorPro\Modules\Forms\Widgets\Form( array(), null );
		\Digitalisimo\Elements\F7b\Module::$editing  = $editing;
		\Digitalisimo\Elements\F7b\Module::$missing  = array();
		\Digitalisimo\Elements\F7b\Flags::$on        = $flag ? array( 'form_conditions' => true ) : array();
		\Digitalisimo\Elements\F7b\Forms\Module::reset();
		\ElementorPro\Modules\Forms\Module::instance()->actions_registrar->actions = array();
		$_POST  = array();
		$_FILES = array();
	}

	/** Instancia real del Formulario con estos campos. */
	function forms_widget( array $fields, array $settings = array(), string $id = 'f0001' ) {
		return design_element(
			array(
				'id'         => $id,
				'elType'     => 'widget',
				'widgetType' => 'form',
				'settings'   => $settings + array(
					'form_name'      => 'Contacto',
					'form_fields'    => $fields,
					'submit_actions' => array( 'email' ),
					'form_metadata'  => array(),
					'mark_required'  => 'yes',
					'show_labels'    => 'yes',
				),
			)
		);
	}

	/** HTML del Formulario pintado por PRO (`render()`), con los ganchos que haya. */
	function forms_render( $widget ): string {
		$method = new ReflectionMethod( $widget, 'render' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		ob_start();
		$method->invoke( $widget );
		return (string) ob_get_clean();
	}

	/**
	 * Envío real por `Ajax_Handler::ajax_send_form()` del documento 10 con este formulario. Devuelve la respuesta y
	 * lo que recibió la acción `email`.
	 */
	function forms_submit( array $fields, array $sent, array $files = array() ): array {
		$element = array(
			'id'         => 'f0001',
			'elType'     => 'widget',
			'widgetType' => 'form',
			'settings'   => array(
				'form_name'      => 'Contacto',
				'form_fields'    => $fields,
				'submit_actions' => array( 'email' ),
				'form_metadata'  => array(),
			),
			'elements'   => array(),
		);
		\Elementor\Plugin::$instance->documents->docs[10] = new Design_Document( 10, array( array( 'id' => 'c0001', 'elType' => 'container', 'settings' => array(), 'elements' => array( $element ) ) ) );
		$action = new \ElementorPro\Modules\Forms\Forms_Capture_Action( 'email' );
		\ElementorPro\Modules\Forms\Module::instance()->actions_registrar->actions = array( $action );
		$_POST  = array( 'action' => 'elementor_pro_forms_send_form', 'post_id' => '10', 'form_id' => 'f0001', 'form_fields' => $sent );
		$_FILES = $files;
		$handler = new \ElementorPro\Modules\Forms\Classes\Ajax_Handler();
		// PRO llama a `add_error( $record->get( 'errors' ) )` con null al fallar (`ajax-handler.php:122-123`), que PHP 8.5
		// anuncia como obsoleto: es del derivado y no cambia nada aquí.
		$level = error_reporting( E_ALL & ~E_DEPRECATED );
		try {
			$handler->ajax_send_form();
		} catch ( Forms_Sent $sent_response ) {
			return array( 'success' => $sent_response->success, 'data' => $sent_response->data, 'runs' => $action->runs, 'handler' => $handler );
		} finally {
			error_reporting( $level );
		}
		throw new RuntimeException( 'El envío no respondió.' );
	}

	/** Ganchos registrados (como `design_hooks()`), sin los del arnés. */
	function forms_hooks(): array {
		$hooks = design_hooks();
		unset( $hooks['elementor/controls/register'] );
		return $hooks;
	}
}
