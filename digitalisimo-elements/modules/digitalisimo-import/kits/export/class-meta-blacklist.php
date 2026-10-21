<?php
namespace Digitalisimo\Elements\Import\Kits\Export;

defined( 'ABSPATH' ) || exit;

/**
 * Lista negra de metas de un kit (§8.2; CC-1, CR-4, CR-14).
 *
 * Se aplica al exportar (`wxr_export_skip_postmeta`, `wxr_export_skip_termmeta`, la parte `term-meta` y el
 * `metadata` de los documentos) y al importar (`wp_import_post_data_raw`, `wp_import_term_meta` y la parte
 * `term-meta`). Todo lo demás pasa: una lista blanca borraría las metas de WooCommerce, ACF o SEO (V-20).
 *
 * - Cachés y estado efímero: se regeneran en el destino.
 * - Sesión y procedencia: el destino pone las suyas.
 * - Datos del constructor dentro del WXR: el exportador nunca los pone ahí; si llegan, es para saltarse kses.
 * - Plantillas de red de Tools (D14): mantienen su propio remapeo.
 */
final class Meta_Blacklist {
	const KEYS = array(
		// Cachés y estado efímero.
		'_edit_lock',
		'_edit_last',
		'_wp_old_slug',
		'_elementor_css',
		'_elementor_element_cache',
		'_elementor_page_assets',
		'_elementor_controls_usage',
		// Sesión y procedencia.
		'_elementor_import_session_id',
		'_digitalisimo_import_origin',
		'_digitalisimo_unconverted',
		'_digitalisimo_import_conditions',
		// Datos del constructor.
		'_elementor_data',
		'_elementor_page_settings',
		'_elementor_edit_mode',
		'_elementor_template_type',
		'_elementor_conditions',
		'_elementor_version',
		'_elementor_pro_version',
	);

	/** Prefijos vetados: las plantillas de red de DIGITALÍSIMO Tools. */
	const PREFIXES = array( '_digitalisimo_tools_network_' );

	/** ¿Se queda fuera esta meta? */
	public static function blocks( $key ): bool {
		if ( ! is_string( $key ) || '' === $key ) {
			return true;
		}
		if ( in_array( $key, self::KEYS, true ) ) {
			return true;
		}
		foreach ( self::PREFIXES as $prefix ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/** Callback de `wxr_export_skip_postmeta` y `wxr_export_skip_termmeta`: respeta un `true` previo. */
	public static function skip( $skip, $key = '' ): bool {
		return (bool) $skip || self::blocks( $key );
	}

	/**
	 * Un valor de meta tal como lo guarda WordPress, o null si debe quedarse fuera: un serializado que contiene
	 * objetos nunca se reconstruye (`allowed_classes = false`, CR-4).
	 *
	 * @param mixed $value Valor crudo (cadena serializada o no).
	 *
	 * @return array{0: bool, 1: mixed} `[ válido, valor ]`.
	 */
	public static function value( $value ): array {
		if ( is_object( $value ) ) {
			return array( false, null );
		}
		if ( is_string( $value ) && self::is_serialized( $value ) ) {
			$data = @unserialize( trim( $value ), array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			if ( false === $data && 'b:0;' !== trim( $value ) ) {
				return array( false, null );
			}
			return self::has_object( $data ) ? array( false, null ) : array( true, $data );
		}
		return self::has_object( $value ) ? array( false, null ) : array( true, $value );
	}

	/** ¿Contiene un objeto (incluido `__PHP_Incomplete_Class`) a cualquier profundidad? */
	public static function has_object( $value ): bool {
		if ( is_object( $value ) ) {
			return true;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( self::has_object( $item ) ) {
					return true;
				}
			}
		}
		return false;
	}

	private static function is_serialized( string $value ): bool {
		if ( function_exists( 'is_serialized' ) ) {
			return is_serialized( $value );
		}
		$value = trim( $value );
		if ( 'N;' === $value ) {
			return true;
		}
		if ( strlen( $value ) < 4 || ':' !== $value[1] || ! in_array( substr( $value, -1 ), array( ';', '}' ), true ) ) {
			return false;
		}
		return 1 === preg_match( '/^[aOsCbidE]:/', $value );
	}
}
