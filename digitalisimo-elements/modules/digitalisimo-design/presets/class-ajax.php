<?php
namespace Digitalisimo\Elements\Design\Presets;

defined( 'ABSPATH' ) || exit;

/**
 * Acciones del editor para los presets (`elementor/ajax/register_actions`; el nonce lo comprueba el núcleo).
 *
 * - `digitalisimo_preset_save` `{ slug, type, label, settings }`: crea o actualiza desde un elemento. Con `slug`
 *   vacío crea uno nuevo y único a partir del nombre.
 * - `digitalisimo_preset_rename` `{ slug, label }`.
 * - `digitalisimo_preset_delete` `{ slug }`: bloqueado si algún documento lo usa (D8).
 * - `digitalisimo_preset_uses` `{ slug }`: documentos que lo usan (bajo demanda).
 *
 * Las cuatro exigen `Store::can_manage()` (D7): `manage_options` y ningún rol «sólo contenido». Sin permiso, o con un
 * error del almacén, lanzan una excepción con su código (403, 400, 404, 409), que el núcleo devuelve como `code` de
 * la respuesta de `elementor_ajax`. Cada respuesta lleva el mapa `presets` de la configuración del editor.
 */
final class Ajax {
	const ACTIONS = array(
		'digitalisimo_preset_save'   => 'save',
		'digitalisimo_preset_rename' => 'rename',
		'digitalisimo_preset_delete' => 'delete',
		'digitalisimo_preset_uses'   => 'uses',
	);

	/** Código de cada error del almacén. */
	const STATUS = array(
		'invalid_slug'  => 400,
		'invalid_type'  => 400,
		'invalid_label' => 400,
		'empty'         => 400,
		'unsafe'        => 400,
		'too_large'     => 400,
		'limit'         => 400,
		'total'         => 400,
		'type_mismatch' => 409,
		'inert'         => 409,
		'in_use'        => 409,
		'busy'          => 409,
		'not_found'     => 404,
	);

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/ajax/register_actions', array( __CLASS__, 'register' ) );
	}

	/** @param object $ajax Módulo AJAX del núcleo. */
	public static function register( $ajax ): void {
		if ( ! is_object( $ajax ) || ! method_exists( $ajax, 'register_ajax_action' ) ) {
			return;
		}
		foreach ( self::ACTIONS as $action => $method ) {
			$ajax->register_ajax_action( $action, array( __CLASS__, $method ) );
		}
	}

	/**
	 * @param mixed $data `{ slug, type, label, settings }`.
	 * @throws \Exception Sin permiso o con un error.
	 */
	public static function save( $data ): array {
		self::authorize();
		$data     = is_array( $data ) ? $data : array();
		$label    = self::text( $data, 'label' );
		$slug     = self::text( $data, 'slug' );
		$slug     = '' === $slug ? Store::unique_slug( '' !== $label ? $label : self::text( $data, 'type' ) ) : $slug;
		$settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
		$preset   = self::check( Store::save( $slug, self::text( $data, 'type' ), $label, $settings ) );
		$local    = isset( $preset['local'] ) ? $preset['local'] : array();
		unset( $preset['local'], $preset['slug'] );
		return self::response( $slug ) + array(
			'preset' => $preset,
			'local'  => $local,
		);
	}

	/**
	 * @param mixed $data `{ slug, label }`.
	 * @throws \Exception Sin permiso o con un error.
	 */
	public static function rename( $data ): array {
		self::authorize();
		$data = is_array( $data ) ? $data : array();
		$slug = self::text( $data, 'slug' );
		self::check( Store::rename( $slug, self::text( $data, 'label' ) ) );
		return self::response( $slug );
	}

	/**
	 * @param mixed $data `{ slug }`.
	 * @throws \Exception Sin permiso, con usos o con un error.
	 */
	public static function delete( $data ): array {
		self::authorize();
		$data   = is_array( $data ) ? $data : array();
		$slug   = self::text( $data, 'slug' );
		$result = Store::delete( $slug );
		if ( is_wp_error( $result ) && 'in_use' === $result->get_error_code() ) {
			$error = $result->get_error_data();
			$uses  = self::describe( isset( $error['uses'] ) ? (array) $error['uses'] : array() );
			$names = array_slice( array_map( function ( $use ) { return $use['title']; }, $uses ), 0, 10 );
			throw new \Exception( esc_html( $result->get_error_message() . ' ' . implode( ', ', $names ) ), 409 );
		}
		self::check( $result );
		return self::response( $slug );
	}

	/**
	 * @param mixed $data `{ slug }`.
	 * @throws \Exception Sin permiso.
	 */
	public static function uses( $data ): array {
		self::authorize();
		$data = is_array( $data ) ? $data : array();
		$slug = self::text( $data, 'slug' );
		if ( null === Store::get( $slug ) ) {
			throw new \Exception( esc_html__( 'Ese preset ya no existe.', 'pro-elements' ), 404 );
		}
		$limit = Store::limits()['uses'];
		$ids   = Store::uses( $slug, $limit );
		return self::response( $slug ) + array(
			'uses'  => self::describe( $ids ),
			'count' => count( $ids ),
			'more'  => count( $ids ) >= $limit,
		);
	}

	/**
	 * Lo común de cada respuesta: el mapa `presets` del editor y los mapas de opciones y tipos.
	 */
	public static function response( string $slug ): array {
		return array(
			'ok'      => true,
			'slug'    => $slug,
			'presets' => self::presets(),
			'options' => Store::options(),
			'types'   => Store::types(),
			'total'   => Store::total(),
		);
	}

	/** El mapa `presets` de la configuración del editor (`Editor::config()`), o el equivalente sin el frente D. */
	public static function presets() {
		if ( class_exists( __NAMESPACE__ . '\Editor' ) && method_exists( __NAMESPACE__ . '\Editor', 'config' ) ) {
			$config = Editor::config();
			if ( isset( $config['presets'] ) ) {
				return $config['presets'];
			}
		}
		$out = array();
		foreach ( Store::all() as $slug => $preset ) {
			if ( ! empty( $preset['inert'] ) ) {
				continue;
			}
			$clean        = Compiler::validated( (string) $slug );
			$out[ $slug ] = array(
				'type'     => $preset['type'],
				'label'    => $preset['label'],
				'settings' => null === $clean ? new \stdClass() : ( $clean ? $clean : new \stdClass() ),
				'error'    => null === $clean,
			);
		}
		return $out ? $out : new \stdClass();
	}

	/** @throws \Exception 403 sin permiso. */
	private static function authorize(): void {
		if ( ! Store::can_manage() ) {
			throw new \Exception( esc_html__( 'No tienes permiso para gestionar los presets de estilo.', 'pro-elements' ), 403 );
		}
	}

	/**
	 * Lanza el error del almacén como excepción con su código.
	 *
	 * @param mixed $result Resultado del almacén.
	 * @return mixed El resultado si no es un error.
	 * @throws \Exception Con el mensaje y el código del error.
	 */
	private static function check( $result ) {
		if ( is_wp_error( $result ) ) {
			$code = (string) $result->get_error_code();
			throw new \Exception( esc_html( $result->get_error_message() ), isset( self::STATUS[ $code ] ) ? self::STATUS[ $code ] : 400 );
		}
		return $result;
	}

	/**
	 * Documentos de unos IDs: `{ id, title, edit }`.
	 *
	 * @param int[] $ids IDs.
	 */
	private static function describe( array $ids ): array {
		$kit = isset( \Elementor\Plugin::$instance->kits_manager ) ? (int) \Elementor\Plugin::$instance->kits_manager->get_active_id() : 0;
		$out = array();
		foreach ( $ids as $id ) {
			$id    = (int) $id;
			$title = $id === $kit ? __( 'Valores por defecto del kit', 'pro-elements' ) : ( function_exists( 'get_the_title' ) ? (string) get_the_title( $id ) : '' );
			$out[] = array(
				'id'    => $id,
				'title' => '' !== $title ? $title : '#' . $id,
				'edit'  => admin_url( 'post.php?post=' . $id . '&action=elementor' ),
			);
		}
		return $out;
	}

	private static function text( array $data, string $key ): string {
		return isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? trim( (string) $data[ $key ] ) : '';
	}
}
