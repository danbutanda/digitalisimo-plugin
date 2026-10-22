<?php
namespace Digitalisimo\Elements\Woo\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Kits de F4-Woo (frente H, V.11 de la especificación): lo que WooCommerce necesita para viajar en un kit, declarado
 * con el registro de K (`docs/fusion/k-kits.md` §10), sin otro sistema de kits.
 *
 * - **Partes** (`digitalisimo_elements_kit_parts`): `woo-attributes` (`Attributes_Part`, `Early_Part`: los atributos
 *   globales y su configuración de muestras, creados antes del runner de taxonomías) y `woo-variations`
 *   (`Variations_Part`: las variaciones de los productos variables exportados y, al importar, la tabla de búsqueda de
 *   los productos que creó la sesión).
 * - **Referencias** (`digitalisimo_elements_kit_references`): los IDs de los widgets `digitalisimo-woo-*` y la imagen de
 *   las muestras (`image` acotada a los términos `pa_*`, V.6.6), ninguna con `gate`.
 * - **Contadores** (`digitalisimo_elements_kit_not_exported`, el punto genérico de K de V.11.4): `variations` baja a
 *   las variaciones cuyo padre no se exportó.
 * - **Medios**: un runner de exportación (`Media_Runner`, antes del final de K) anota las imágenes de las variaciones
 *   como adjuntos exportados, para que la parte `media` de K las incruste.
 *
 * Arranca desde `Woo\Module` (`BOOT`) sólo con WooCommerce compatible. `init_inactive()` (sin WooCommerce) sólo
 * registra las partes, que entonces no hacen nada salvo informar `woocommerce.inactive` si el kit trae sus datos.
 */
final class Module {
	/** Símbolos de WooCommerce que usan las partes sin detección propia (D3). */
	const SYMBOLS = array(
		'wc_get_attribute_taxonomies()',
		'wc_create_attribute()',
		'wc_get_attribute()',
		'wc_delete_attribute()',
		'wc_attribute_taxonomy_name()',
		'wc_attribute_taxonomy_id_by_name()',
		'wc_get_product()',
		'wc_delete_product_transients()',
		'WC_Product_Variation',
		'WC_Product_Variable',
		'WC_Data_Store',
	);

	/** Acción del núcleo en la que K registra sus runners de exportación (20) y el final (`PHP_INT_MAX`). */
	const EXPORT_ACTION = 'elementor/import-export-customization/export-kit';

	/** Después de los runners de K y antes del final, que exporta las partes (y con ellas `media`). */
	const EXPORT_PRIORITY = 30;

	const FILTER_PARTS        = 'digitalisimo_elements_kit_parts';
	const FILTER_REFERENCES   = 'digitalisimo_elements_kit_references';
	const FILTER_NOT_EXPORTED = 'digitalisimo_elements_kit_not_exported';

	/** Valor que nunca coincide (`References::NEVER_ID`), por si K aún no está cargado al declarar. */
	const NEVER_ID = 2147483647;

	/** @var bool */
	private static $initialized = false;

	/** @var bool */
	private static $parts = false;

	/** Con WooCommerce compatible (desde `Woo\Module`). Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		self::register_parts();
		if ( ! self::guard() ) {
			return;
		}
		add_filter( self::FILTER_REFERENCES, array( __CLASS__, 'references' ) );
		add_filter( self::FILTER_NOT_EXPORTED, array( __CLASS__, 'not_exported' ), 10, 2 );
		add_action( self::EXPORT_ACTION, array( __CLASS__, 'export_runner' ), self::EXPORT_PRIORITY );
	}

	/**
	 * Sin WooCommerce (o con uno incompatible): sólo las partes, para que el informe diga `woocommerce.inactive` cuando
	 * el kit trae atributos o variaciones. Un solo `add_filter`, que sólo corre cuando K arma sus partes.
	 */
	public static function init_inactive(): void {
		if ( function_exists( 'add_filter' ) ) {
			self::register_parts();
		}
	}

	/** Guarda del frente A (`Woo\Module::requires()`); sin él, la comprobación local de los mismos símbolos. */
	public static function guard(): bool {
		$module = '\\Digitalisimo\\Elements\\Woo\\Module';
		if ( class_exists( $module ) && method_exists( $module, 'requires' ) ) {
			return (bool) $module::requires( 'kit', self::SYMBOLS );
		}
		foreach ( self::SYMBOLS as $symbol ) {
			$exists = '()' === substr( $symbol, -2 ) ? function_exists( substr( $symbol, 0, -2 ) ) : class_exists( $symbol );
			if ( ! $exists ) {
				return false;
			}
		}
		return true;
	}

	/** Engancha el filtro de partes una sola vez. */
	private static function register_parts(): void {
		if ( self::$parts ) {
			return;
		}
		self::$parts = true;
		add_filter( self::FILTER_PARTS, array( __CLASS__, 'parts' ) );
	}

	/**
	 * `digitalisimo_elements_kit_parts`: las dos partes, por nombre de clase (K las instancia una vez por petición).
	 * Se cargan aquí, cuando K ya está en marcha: sus interfaces son de K.
	 *
	 * @param mixed $parts Partes de K y de otras etapas.
	 * @return array
	 */
	public static function parts( $parts ): array {
		$parts = is_array( $parts ) ? $parts : array();
		if ( ! interface_exists( '\\Digitalisimo\\Elements\\Import\\Kits\\Early_Part' ) ) {
			return $parts;
		}
		require_once __DIR__ . '/class-attributes-part.php';
		require_once __DIR__ . '/class-variations-part.php';
		$parts[] = Attributes_Part::class;
		$parts[] = Variations_Part::class;
		return $parts;
	}

	/**
	 * Referencias de F4-Woo (V.4.4, V.6.6 y v1 §6.3). Ninguna decide quién ve algo (`gate` falso): sin destino, el
	 * botón y la vista rápida quedan sin producto (`never`), la vista rápida en «Diseño propio», el filtro sin bucle y
	 * la muestra sin imagen; nada se muestra de más. `product_id` es un control de consulta sin el sufijo que traduce
	 * el núcleo: esta referencia es su única traducción.
	 *
	 * @param int $never Valor de cierre (`References::NEVER_ID`).
	 * @return array[]
	 */
	public static function references_list( int $never = self::NEVER_ID ): array {
		return array(
			array( 'element', 'digitalisimo-woo-wishlist-button', 'product_id', 'post', false, $never ),
			array( 'element', 'digitalisimo-woo-wishlist-button', 'wishlist_page', 'post' ),
			array( 'element', 'digitalisimo-woo-quick-view', 'product_id', 'post', false, $never ),
			array( 'element', 'digitalisimo-woo-quick-view', 'digitalisimo_template_id', 'template' ),
			array( 'element', 'digitalisimo-woo-price-filter', 'selected_element', 'element' ),
			array( 'element', 'digitalisimo-woo-attribute-filter', 'selected_element', 'element' ),
			// Imagen de una muestra (meta `image` de WooCommerce): sólo en los términos de atributos (V.6.6, V.11.4).
			array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => array( 'pa_*' ) ),
		);
	}

	/**
	 * `digitalisimo_elements_kit_references`.
	 *
	 * @param mixed $refs Referencias de K y de otras etapas.
	 * @return array
	 */
	public static function references( $refs ): array {
		$refs  = is_array( $refs ) ? $refs : array();
		$class = '\\Digitalisimo\\Elements\\Import\\Kits\\References';
		$never = class_exists( $class ) && defined( $class . '::NEVER_ID' ) ? (int) constant( $class . '::NEVER_ID' ) : self::NEVER_ID;
		foreach ( self::references_list( $never ) as $ref ) {
			$refs[] = $ref;
		}
		return $refs;
	}

	/**
	 * `digitalisimo_elements_kit_not_exported` (V.11.4): delega en `Variations_Part`, que sólo baja `variations`.
	 *
	 * @param mixed $map     Motivo => número.
	 * @param mixed $context Contexto de exportación.
	 * @return mixed
	 */
	public static function not_exported( $map, $context = null ) {
		if ( ! class_exists( __NAMESPACE__ . '\\Variations_Part', false ) ) {
			return $map;
		}
		return Variations_Part::not_exported( $map, $context );
	}

	/**
	 * `elementor/import-export-customization/export-kit` (30): con el contexto de exportación de K activo, registra
	 * el runner que anota las imágenes de las variaciones antes de que el runner final de K exporte las partes.
	 *
	 * @param mixed $export Proceso de exportación del núcleo.
	 */
	public static function export_runner( $export ): void {
		$context = '\\Digitalisimo\\Elements\\Import\\Kits\\Context';
		$base    = '\\Elementor\\App\\Modules\\ImportExportCustomization\\Runners\\Export\\Export_Runner_Base';
		if ( ! is_object( $export ) || ! method_exists( $export, 'register' ) || ! class_exists( $context ) || null === $context::export_context() || ! class_exists( $base ) ) {
			return;
		}
		require_once __DIR__ . '/class-variations-part.php';
		require_once __DIR__ . '/class-media-runner.php';
		$export->register( new Media_Runner() );
	}

	/**
	 * Olvida el arranque.
	 *
	 * @internal Sólo para pruebas.
	 */
	public static function reset(): void {
		self::$initialized = false;
		self::$parts       = false;
	}
}
