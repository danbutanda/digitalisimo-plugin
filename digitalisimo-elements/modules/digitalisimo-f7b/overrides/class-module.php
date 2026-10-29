<?php
namespace Digitalisimo\Elements\F7b\Overrides;

defined( 'ABSPATH' ) || exit;

/**
 * Sobrescrituras de contenido en el widget Plantilla (F7b, frente E; C4, D12, D13 y V.2 de la especificación).
 *
 * Arranque (`F7b\Module::BOOT`): sin las piezas del núcleo que usa (`F7b\Module::requires()`), o con
 * `F7b\Flags::active( 'overrides' )` falso (ningún documento publicado usa sobrescrituras y no es el editor), no
 * engancha nada. Si no:
 *
 * - siempre, `Render` (el gancho `elementor/widget/before_render_content` y el del Markdown);
 * - sólo en el editor (`F7b\Module::editing()`): los controles (`Controls`), las acciones AJAX de sólo lectura
 *   (`Ajax`), el script `assets/js/overrides-editor.js` (`elementor/editor/after_enqueue_scripts`) y la marca
 *   `digitalisimoOverrides.library` en la configuración de cada documento (`elementor/document/config`), con la que el
 *   script muestra la clave sólo en las plantillas de la biblioteca.
 *
 * La regla del saneado, la tabla de ajustes por tipo y los límites viven en `Apply`; la caché, en `Render`.
 */
final class Module {
	const FLAG = 'overrides';

	const SYMBOLS = array( 'Elementor\\Plugin', 'Elementor\\Controls_Manager', 'Elementor\\Widget_Base' );

	const HANDLE  = 'digitalisimo-f7b-overrides-editor';
	const SCRIPT  = 'assets/js/overrides-editor.js';
	const OBJECT  = 'digitalisimoOverrides';
	const DOC_KEY = 'digitalisimoOverrides';

	const F7B   = '\\Digitalisimo\\Elements\\F7b\\Module';
	const FLAGS = '\\Digitalisimo\\Elements\\F7b\\Flags';

	/** @var bool */
	private static $initialized = false;

	/** Idempotente; comprueba sus guardas antes de enganchar nada. */
	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		$f7b   = self::F7B;
		$flags = self::FLAGS;
		if ( ! class_exists( $f7b ) || ! class_exists( $flags ) ) {
			return;
		}
		if ( ! call_user_func( array( $f7b, 'requires' ), self::SYMBOLS, 'overrides' ) ) {
			return;
		}
		if ( ! call_user_func( array( $flags, 'active' ), self::FLAG ) ) {
			return;
		}
		self::$initialized = true;
		foreach ( array( 'apply', 'render', 'controls', 'ajax' ) as $file ) {
			require_once __DIR__ . '/class-' . $file . '.php';
		}
		Render::init();
		if ( ! call_user_func( array( $f7b, 'editing' ) ) ) {
			return;
		}
		Controls::init();
		Ajax::init();
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'elementor/document/config', array( __CLASS__, 'document_config' ), 10, 2 );
	}

	/**
	 * `elementor/document/config`: ¿es el documento una plantilla de la biblioteca?
	 *
	 * @param mixed $config  Configuración adicional del documento.
	 * @param mixed $post_id Documento.
	 * @return mixed
	 */
	public static function document_config( $config, $post_id = 0 ) {
		if ( ! is_array( $config ) ) {
			return $config;
		}
		$config[ self::DOC_KEY ] = array( 'library' => Render::LIBRARY === get_post_type( absint( $post_id ) ) );
		return $config;
	}

	/** Script del editor y su configuración. Nunca en el front: el gancho es del editor. */
	public static function enqueue(): void {
		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_enqueue_script( self::HANDLE, plugins_url( self::SCRIPT, dirname( __DIR__ ) . '/class-module.php' ), array( 'elementor-editor' ), $version, true );
		wp_add_inline_script( self::HANDLE, 'window.' . self::OBJECT . '=' . wp_json_encode( self::config() ) . ';', 'before' );
	}

	/** Configuración de `overrides-editor.js`: nombres, tipos, `kind` por tipo y textos (todos traducibles aquí). */
	public static function config(): array {
		return array(
			'slot'    => Apply::SLOT,
			'pattern' => '^[a-z0-9_-]{1,40}$',
			'docKey'  => self::DOC_KEY,
			'types'   => array(
				'heading'     => __( 'Encabezado', 'pro-elements' ),
				'text-editor' => __( 'Editor de texto', 'pro-elements' ),
				'image'       => __( 'Imagen', 'pro-elements' ),
				'button'      => __( 'Botón', 'pro-elements' ),
				'icon'        => __( 'Icono', 'pro-elements' ),
				'video'       => __( 'Vídeo', 'pro-elements' ),
				'icon-box'    => __( 'Caja de icono', 'pro-elements' ),
			),
			'kinds'   => Apply::kinds_map(),
			'actions' => array(
				'slots' => Ajax::SLOTS,
				'uses'  => Ajax::USES,
			),
			'i18n'    => array(
				'choose'     => __( 'Elige una clave', 'pro-elements' ),
				'orphanOpt'  => __( '%s (sin destino)', 'pro-elements' ),
				'repeated'   => __( '%1$s (×%2$d)', 'pro-elements' ),
				'orphans1'   => __( '1 sobrescritura sin destino: su clave ya no está en la plantilla.', 'pro-elements' ),
				'orphans'    => __( '%d sobrescrituras sin destino: su clave ya no está en la plantilla.', 'pro-elements' ),
				'wrongKind1' => __( '1 sobrescritura no se aplica al tipo de su elemento.', 'pro-elements' ),
				'wrongKind'  => __( '%d sobrescrituras no se aplican al tipo de su elemento.', 'pro-elements' ),
				'noSlots'    => __( 'La plantilla elegida no tiene claves: añádelas en sus elementos con «Clave de sobrescritura».', 'pro-elements' ),
				'loadError'  => __( 'No se pudieron leer las claves de la plantilla.', 'pro-elements' ),
				'invalid'    => __( 'Clave no válida: usa de 1 a 40 minúsculas, números, «-» o «_». Mientras tanto se ignora.', 'pro-elements' ),
				'duplicate'  => __( 'Esta clave se repite en %d elementos de la plantilla: cada sobrescritura los cambia todos.', 'pro-elements' ),
				'uses1'      => __( '1 instancia sobrescribe esta clave: si la cambias o la borras, quedará sin destino.', 'pro-elements' ),
				'uses'       => __( '%d instancias sobrescriben esta clave: si la cambias o la borras, quedarán sin destino.', 'pro-elements' ),
			),
		);
	}
}
