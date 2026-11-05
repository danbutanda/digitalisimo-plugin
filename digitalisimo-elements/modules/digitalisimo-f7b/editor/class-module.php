<?php
namespace Digitalisimo\Elements\F7b\Editor;

defined( 'ABSPATH' ) || exit;

/**
 * «Aplicar a todos los de este tipo» (F7b, frente H; V.7 de la especificación, que sustituye a D17).
 *
 * Sólo en el editor: encola `assets/js/apply-all-editor.js`, que añade una entrada al menú contextual de los widgets
 * v3. La entrada guarda el elemento de origen en `elementorCommon.storage` con la clave propia `STORAGE_KEY` (el mismo
 * formato que `document/elements/copy`, así no pisa el portapapeles del usuario), ejecuta una sola vez
 * `$e.run( 'document/elements/paste-style', { containers, storageKey } )` sobre los widgets del mismo tipo del
 * documento (sin el origen ni los widgets globales) y borra la clave. «Pegar estilo» del núcleo decide qué es estilo
 * con `isStyleTransferControl()` (`elementor/assets/js/editor.js:9758-9760`) y deja un único registro de historial
 * (`getHistory()`, `:14823-14830`): un «Deshacer» nativo y ninguna semilla propia.
 *
 * Nada en el front ni en el servidor: no hay acciones AJAX ni opciones. Fuera de la administración, `init()` no
 * engancha nada.
 */
final class Module {
	const HANDLE      = 'digitalisimo-f7b-apply-all';
	const OBJECT      = 'digitalisimoApplyAll';
	const SCRIPT      = 'assets/js/apply-all-editor.js';
	const STORAGE_KEY = 'digitalisimo-apply-all';

	/** Símbolos que necesita (V.0: el submódulo comprueba los suyos). */
	const SYMBOLS = array( 'wp_enqueue_script', 'wp_add_inline_script', 'wp_json_encode', 'plugins_url' );

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		if ( ! self::requires( self::SYMBOLS ) || ! self::editor_context() ) {
			return;
		}
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/** Script del editor y su configuración. El gancho sólo existe en el editor. */
	public static function enqueue(): void {
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_enqueue_script( self::HANDLE, plugins_url( self::SCRIPT, dirname( __DIR__ ) . '/class-module.php' ), array( 'elementor-editor' ), $version, true );
		wp_add_inline_script( self::HANDLE, 'window.' . self::OBJECT . '=' . wp_json_encode( self::config() ) . ';', 'before' );
	}

	/** Configuración de `apply-all-editor.js`: la clave del almacén y los textos. */
	public static function config(): array {
		return array(
			'key'  => self::STORAGE_KEY,
			'i18n' => self::strings(),
		);
	}

	/** Textos visibles. Todos en español y traducibles; ninguno nombra a otro constructor. */
	public static function strings(): array {
		return array(
			/* translators: %d: número de widgets del mismo tipo en la página. */
			'menu'    => __( 'Aplicar este estilo a todos los de este tipo (%d)', 'pro-elements' ),
			/* translators: %s: nombre del widget, por ejemplo «Encabezado». */
			'title'   => __( 'Aplicar el estilo a todos los «%s»', 'pro-elements' ),
			/* translators: 1: número de widgets; 2: nombre del widget. */
			'message' => __( 'Se pegará todo el estilo de este elemento en los otros %1$d «%2$s» de esta página, como hace «Pegar estilo»: lo de las pestañas Estilo y Avanzado sustituye a lo que ya tuvieran, también si lo habían cambiado. El contenido, los enlaces y las acciones no cambian. Puedes deshacerlo con el historial del editor.', 'pro-elements' ),
			'global1' => __( 'Un widget global queda fuera: cambiarlo afectaría a todo el sitio.', 'pro-elements' ),
			/* translators: %d: número de widgets globales. */
			'globals' => __( '%d widgets globales quedan fuera: cambiarlos afectaría a todo el sitio.', 'pro-elements' ),
			'ok'      => __( 'Aplicar', 'pro-elements' ),
			'cancel'  => __( 'Cancelar', 'pro-elements' ),
			/* translators: %d: número de widgets modificados. */
			'done'    => __( 'Estilo aplicado a %d elementos.', 'pro-elements' ),
		);
	}

	/** ¿Petición de la administración o del editor? El front nunca engancha nada. */
	private static function editor_context(): bool {
		$f7b = 'Digitalisimo\\Elements\\F7b\\Module';
		if ( class_exists( $f7b ) && method_exists( $f7b, 'editing' ) && $f7b::editing() ) {
			return true;
		}
		return function_exists( 'is_admin' ) && is_admin();
	}

	/**
	 * Guarda de símbolos: la de `F7b\Module` (frente A) si existe; si no, la misma comprobación aquí.
	 *
	 * @param string[] $symbols Clases o funciones.
	 */
	private static function requires( array $symbols ): bool {
		$f7b = 'Digitalisimo\\Elements\\F7b\\Module';
		if ( class_exists( $f7b ) && method_exists( $f7b, 'requires' ) ) {
			return (bool) $f7b::requires( $symbols, 'editor' );
		}
		foreach ( $symbols as $symbol ) {
			if ( ! function_exists( $symbol ) && ! class_exists( $symbol ) ) {
				return false;
			}
		}
		return true;
	}
}
