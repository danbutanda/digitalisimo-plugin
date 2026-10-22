<?php
namespace Digitalisimo\Elements\Design\Presets;

use Digitalisimo\Elements\Design\Module as Design;

defined( 'ABSPATH' ) || exit;

/**
 * Presets de estilo: el editor y los kits (F7a, frentes D y E; V.2.4 y V.2.5 de la especificación).
 *
 * `init()` corre en `plugins_loaded` (dentro de `Design\Module::init()`), antes de `init`, como pide K:
 * - registra la parte `presets` (`Kit_Part`) y la referencia `element · * · digitalisimo_preset · map:presets`, sin
 *   `gate`: un preset decide el aspecto, no quién ve algo;
 * - aporta a `Kit_Settings::export()` los ajustes de cada preset, para que los globales que sólo usa un preset
 *   viajen en `used` (filtro `digitalisimo_elements_kit_used_globals_trees`);
 * - encola `assets/presets-editor.js` sólo en el editor (`elementor/editor/after_enqueue_scripts`), con los presets
 *   de los tipos registrados en `window.digitalisimoPresets`. El front no lee nada.
 *
 * Guarda (V.9): sin `Store` o con un núcleo anterior a 4.3 no registra nada.
 */
final class Editor {
	const HANDLE = 'digitalisimo-presets-editor';
	const OBJECT = 'digitalisimoPresets';
	const SCRIPT = 'assets/presets-editor.js';

	/** Control del vínculo en cada elemento (el de `Controls::CONTROL`) y mapa del kit que lo traduce. */
	const CONTROL = 'digitalisimo_preset';
	const MAP     = 'presets';

	const FILTER_TREES = 'digitalisimo_elements_kit_used_globals_trees';
	const PART         = '\Digitalisimo\Elements\Import\Kits\Part';

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_filter' ) ) {
			return;
		}
		$store = __NAMESPACE__ . '\Store';
		if ( ! Design::requires( 'presets', array( $store . '::all', $store . '::sanitize_settings', $store . '::replace', $store . '::can_manage', 'wp_add_inline_script()' ) ) ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'digitalisimo_elements_kit_parts', array( __CLASS__, 'kit_parts' ) );
		add_filter( 'digitalisimo_elements_kit_references', array( __CLASS__, 'kit_references' ) );
		add_filter( self::FILTER_TREES, array( __CLASS__, 'kit_used_trees' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * La parte `presets` detrás de las de K (las ordena `Parts::all()`).
	 *
	 * @param mixed $parts Partes registradas.
	 * @return mixed
	 */
	public static function kit_parts( $parts ) {
		if ( is_array( $parts ) && self::part_loaded() ) {
			$parts[] = new Kit_Part();
		}
		return $parts;
	}

	/**
	 * El vínculo de cada elemento se traduce con el mapa `presets` que publica la parte.
	 *
	 * @param mixed $refs Referencias registradas.
	 * @return mixed
	 */
	public static function kit_references( $refs ) {
		if ( is_array( $refs ) ) {
			$refs[] = array(
				'scope' => 'element',
				'type'  => '*',
				'path'  => self::CONTROL,
				'kind'  => 'map:' . self::MAP,
				'gate'  => false,
			);
		}
		return $refs;
	}

	/**
	 * Árboles con los ajustes de los presets para `Kit_Settings::export()`.
	 *
	 * @param mixed $trees Árboles de las demás partes.
	 * @return mixed
	 */
	public static function kit_used_trees( $trees ) {
		return self::part_loaded() ? Kit_Part::used_trees( $trees ) : $trees;
	}

	/** Script del editor y su configuración. Nunca en el front: el gancho es del editor. */
	public static function enqueue(): void {
		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_enqueue_script( self::HANDLE, plugins_url( self::SCRIPT, __FILE__ ), array( 'elementor-editor' ), $version, true );
		wp_add_inline_script( self::HANDLE, 'window.' . self::OBJECT . '=' . wp_json_encode( self::config() ) . ';', 'before' );
	}

	/**
	 * Configuración de `presets-editor.js`.
	 *
	 * `presets`: slug => `{ type, label, settings, error }` de los presets que no son `inert`. Los ajustes son los de
	 * `Compiler::validated()`, los mismos que aplana el CSS del documento: así la vista previa coincide con el front.
	 * Un preset que hoy no pasa el saneado va con `error` y sin ajustes, y nunca se aplica (V-I3). Las acciones de
	 * `Ajax` devuelven este mismo mapa.
	 */
	public static function config(): array {
		$presets = array();
		foreach ( Store::all() as $slug => $preset ) {
			if ( ! is_array( $preset ) || ! empty( $preset['inert'] ) || ! isset( $preset['type'], $preset['label'] ) ) {
				continue;
			}
			$clean = self::validated( (string) $slug, $preset );

			$presets[ (string) $slug ] = array(
				'type'     => (string) $preset['type'],
				'label'    => (string) $preset['label'],
				'settings' => $clean ? $clean : new \stdClass(),
				'error'    => null === $clean,
			);
		}
		return array(
			'control'   => self::CONTROL,
			'presets'   => $presets ? $presets : new \stdClass(),
			'canManage' => (bool) Store::can_manage(),
			'canDesign' => self::can_design(),
			'i18n'      => self::strings(),
		);
	}

	/** Textos del editor. Sin nombres de otros constructores. */
	public static function strings(): array {
		return array(
			'none'     => __( 'Sin preset', 'pro-elements' ),
			'save'     => __( 'Guardar estilo como preset…', 'pro-elements' ),
			'update'   => __( 'Actualizar el preset con este estilo', 'pro-elements' ),
			'only'     => __( 'Usar sólo el preset', 'pro-elements' ),
			'unlink'   => __( 'Desvincular y copiar estilos', 'pro-elements' ),
			'name'     => __( 'Nombre del preset', 'pro-elements' ),
			'ok'       => __( 'Guardar', 'pro-elements' ),
			'cancel'   => __( 'Cancelar', 'pro-elements' ),
			'remove'   => __( '¿Borrar este preset? Los elementos que lo usan conservan su estilo local.', 'pro-elements' ),
			'saved'    => __( 'Preset guardado.', 'pro-elements' ),
			'left'     => __( 'Siguen como valores locales:', 'pro-elements' ),
			'covered1' => __( '1 valor local tapa el preset.', 'pro-elements' ),
			/* translators: %d: número de valores locales. */
			'covered'  => __( '%d valores locales tapan el preset.', 'pro-elements' ),
			'error'    => __( 'con error', 'pro-elements' ),
			'failed'   => __( 'No se pudo completar la acción.', 'pro-elements' ),
		);
	}

	/** ¿Puede vincular y desvincular? Sin la restricción `design` del gestor de roles en ninguno de sus roles (D7). */
	private static function can_design(): bool {
		return ! method_exists( __NAMESPACE__ . '\Store', 'design_restricted' ) || ! Store::design_restricted();
	}

	/**
	 * Ajustes validados de un preset (`Compiler::validated()`, con su memoria por petición), o null si hoy no se aplica.
	 *
	 * @param string $slug   Slug.
	 * @param array  $preset Preset guardado.
	 */
	private static function validated( string $slug, array $preset ): ?array {
		if ( method_exists( __NAMESPACE__ . '\Compiler', 'validated' ) ) {
			return Compiler::validated( $slug );
		}
		$raw   = isset( $preset['settings'] ) && is_array( $preset['settings'] ) ? $preset['settings'] : array();
		$clean = $raw ? Store::sanitize_settings( (string) $preset['type'], $raw ) : array();
		return $clean ? $clean : null;
	}

	/** Carga `Kit_Part` sólo si el contrato de K está disponible. */
	private static function part_loaded(): bool {
		return interface_exists( self::PART ) && class_exists( __NAMESPACE__ . '\Kit_Part' );
	}
}
