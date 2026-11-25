<?php
namespace Digitalisimo\Elements\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * La plantilla de página (`_wp_page_template`: lienzo, ancho completo, la del tema…) sólo cambia cuando alguien la pide.
 * Nada de `elementor/` se edita. Detalle en `docs/ELEMENTS_MCP_CSS.md` («Plantilla de página»).
 *
 * Incidente (digitalisimo.lat, 2026-11-13 y 2026-11-23): una landing con lienzo perdió la plantilla al reconstruirla
 * con el MCP y, después, al cambiarle el slug por la REST; salió con la cabecera y el pie del sitio. Reproducido en
 * Playground (`tests/playground/elements-page-template.sh`), con la pila de cada escritura de la meta:
 *
 * 1. Guardar una revisión le cambia la plantilla a su entrada. `wp_update_post()` sobre una revisión (el núcleo de
 *    Elementor lo hace en `DB::save_plain_text()` al guardar los elementos en el autoguardado de una publicada:
 *    `build-composition`, `manage-elements`…) lee `page_template` de la revisión (`WP_Post::to_array()`) y
 *    `wp_insert_post()` lo valida contra las plantillas del tipo `revision`, que no tiene ninguna: la da por inválida y
 *    escribe `default` con `update_post_meta()`, que redirige toda escritura de una revisión a su entrada. Resultado: la
 *    página publicada queda sin lienzo, aunque nadie lo pidió, y la REST lo enseña después (`template: ""`). Con una
 *    plantilla válida para la revisión, escribiría la de la revisión, que puede ser vieja. El núcleo de Elementor ya lo
 *    evita, pero sólo en el autoguardado del editor (`Page_Templates\Module::filter_update_meta()`, acción
 *    `save_builder`); aquí se evita en todos los caminos: mientras `wp_insert_post()` guarda una revisión (desde que la
 *    base confirma la actualización, `clean_post_cache`, hasta su bloque de la plantilla), su entrada conserva
 *    `_wp_page_template`. Se descarta una sola escritura, y la marca se limpia también en `edit_post`, `wp_insert_post`,
 *    la siguiente actualización y `shutdown`: una actualización que termina antes no deja la marca puesta.
 * 2. Publicar desde un autoguardado deshace la plantilla pedida. El autoguardado de una publicada copia la plantilla
 *    de la entrada al crearse (`Document::get_autosave()` → `DB::copy_elementor_meta()`) y `publish-document` la vuelve a
 *    copiar a la entrada (`Revisions_Manager::restore_revision()`): si entre tanto la plantilla de la entrada cambió
 *    (`update-page-settings` con `template`, la REST con `template`, el editor de WordPress), publicar ponía la vieja.
 *    Ahora, cuando cambia la plantilla de una entrada, cada autoguardado suyo que tenía la anterior recibe la nueva; uno
 *    que ya tenía otra (un cambio pendiente del editor) la conserva.
 *
 * Fuera de esos dos casos no hace nada: no lee ni escribe metas, no cambia ninguna plantilla pedida y no toca sitios
 * que no guardan revisiones ni autoguardados.
 */
final class Page_Template {
	const META = '_wp_page_template';

	/** @var bool */
	private static $initialized = false;

	/** @var int[] [ revisión, entrada ] que `wp_insert_post()` empezó a actualizar (`pre_post_update`), o vacío. */
	private static $revision_pending = array();

	/** @var int Entrada de la revisión cuya actualización confirmó la base (`clean_post_cache`); 0: ninguna. */
	private static $revision_parent = 0;


	/** @var array<int, string> Plantilla anterior de cada entrada durante su escritura. */
	private static $previous = array();

	/** Idempotente. */
	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_filter' ) ) {
			return;
		}
		self::$initialized = true;
		add_action( 'pre_post_update', array( __CLASS__, 'post_updating' ), PHP_INT_MIN, 2 );
		add_action( 'clean_post_cache', array( __CLASS__, 'post_stored' ), PHP_INT_MIN, 1 );
		add_action( 'edit_post', array( __CLASS__, 'post_updated' ), PHP_INT_MIN, 1 );
		add_action( 'wp_insert_post', array( __CLASS__, 'post_updated' ), PHP_INT_MIN, 1 );
		add_action( 'shutdown', array( __CLASS__, 'post_updated' ), PHP_INT_MIN, 0 );
		add_filter( 'update_post_metadata', array( __CLASS__, 'before_write' ), PHP_INT_MAX, 3 );
		add_filter( 'add_post_metadata', array( __CLASS__, 'before_write' ), PHP_INT_MAX, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'written' ), 10, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'written' ), 10, 4 );
	}

	/**
	 * `pre_post_update` (dentro de `wp_insert_post()`, antes de escribir la entrada y su plantilla): si lo que se guarda
	 * es una revisión, su entrada queda protegida hasta `edit_post`. Cualquier otra actualización la libera.
	 */
	public static function post_updating( $post_id, $data = array() ): void {
		self::post_updated();
		if ( ! is_array( $data ) || 'revision' !== ( $data['post_type'] ?? '' ) || ! function_exists( 'wp_is_post_revision' ) ) {
			return;
		}
		$parent = (int) wp_is_post_revision( (int) $post_id );
		if ( $parent <= 0 && ! empty( $data['post_parent'] ) ) {
			$parent = (int) $data['post_parent'];
		}
		if ( $parent > 0 ) {
			self::$revision_pending = array( (int) $post_id, $parent );
		}
	}

	/**
	 * `clean_post_cache` de esa revisión: la base guardó la actualización (si falló, `wp_insert_post()` termina antes y
	 * nunca llega aquí) y lo siguiente es el bloque de la plantilla: desde ahora, su entrada queda protegida.
	 */
	public static function post_stored( $post_id = 0 ): void {
		if ( self::$revision_pending && (int) $post_id === self::$revision_pending[0] ) {
			self::$revision_parent  = self::$revision_pending[1];
			self::$revision_pending = array();
		}
	}

	/** `edit_post`, `wp_insert_post` y `shutdown` (y el inicio de otra actualización): fin de la protección. */
	public static function post_updated( $post_id = 0 ): void {
		self::$revision_parent  = 0;
		self::$revision_pending = array();
	}

	/**
	 * `update_post_metadata` / `add_post_metadata`, con la última prioridad: mientras se guarda una revisión, su entrada
	 * conserva la plantilla (la escritura se da por hecha sin cambiar nada). Si no, anota la plantilla anterior de la
	 * entrada para que sus autoguardados la sigan.
	 */
	public static function before_write( $check, $object_id = 0, $meta_key = '' ) {
		if ( self::META !== $meta_key || null !== $check ) {
			return $check;
		}
		$object_id = (int) $object_id;
		if ( self::$revision_parent && $object_id === self::$revision_parent ) {
			// La única escritura del bloque de la plantilla de esa revisión: se descarta y la marca se limpia.
			self::post_updated();
			return true;
		}
		if ( $object_id > 0 && ! self::is_revision( $object_id ) ) {
			self::$previous[ $object_id ] = self::read( $object_id );
		}
		return $check;
	}

	/**
	 * `updated_post_meta` / `added_post_meta`: la plantilla de una entrada cambió; cada autoguardado suyo que tenía la
	 * anterior recibe la nueva (`publish-document` lo copia entero a la entrada).
	 */
	public static function written( $meta_id, $object_id = 0, $meta_key = '', $value = null ): void {
		$object_id = (int) $object_id;
		if ( self::META !== $meta_key || ! array_key_exists( $object_id, self::$previous ) ) {
			return;
		}
		$old = self::$previous[ $object_id ];
		unset( self::$previous[ $object_id ] );
		$new = self::normalize( $value );
		if ( $new === $old || ! function_exists( 'get_posts' ) ) {
			return;
		}
		foreach ( self::autosaves( $object_id ) as $autosave ) {
			if ( self::read( $autosave ) === $old ) {
				update_metadata( 'post', $autosave, self::META, $new );
			}
		}
	}

	/** Para las pruebas: vuelve al estado anterior a `init()`. */
	public static function reset(): void {
		self::$initialized      = false;
		self::$revision_parent  = 0;
		self::$revision_pending = array();
		self::$previous         = array();
	}

	/** La plantilla guardada (de la base, sin filtros de lectura), con vacío y `default` como `default`. */
	private static function read( int $id ): string {
		$value = function_exists( 'get_metadata_raw' ) ? get_metadata_raw( 'post', $id, self::META, true ) : get_metadata( 'post', $id, self::META, true );
		return self::normalize( $value );
	}

	private static function normalize( $value ): string {
		return is_string( $value ) && '' !== $value ? $value : 'default';
	}

	private static function is_revision( int $id ): bool {
		return function_exists( 'wp_is_post_revision' ) && false !== wp_is_post_revision( $id );
	}

	/** @return int[] Los autoguardados de una entrada (`<id>-autosave-v1`, uno por usuario). */
	private static function autosaves( int $id ): array {
		$found = get_posts(
			array(
				'post_type'        => 'revision',
				'post_status'      => 'inherit',
				'post_parent'      => $id,
				'name'             => $id . '-autosave-v1',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'no_found_rows'    => true,
			)
		);
		return array_values( array_filter( array_map( 'intval', (array) $found ) ) );
	}
}
