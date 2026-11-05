<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Metas de DIGITALÍSIMO Migración fuera de los WXR (F8, V.11 y contrato C17; C2-25).
 *
 * Migración guarda en cada documento la copia anterior a la conversión (`_digitalisimo_ep_backup`,
 * `_digitalisimo_ep_backup_page` y `_digitalisimo_ep_backup_f8`), la huella de lo que escribió (`_digitalisimo_ep_f8`) y
 * sus propias metas `_digitalisimo_migration_*` (imágenes de demostración, procedencia…). Son datos de la reversión en
 * ese sitio, que sólo sirven junto a sus opciones de registro (que no viajan), y la copia de los ajustes de página puede
 * llevar el código de la página: ningún kit ni WXR debe llevarlas.
 *
 * La lista negra de los kits (`Import\Kits\Export\Meta_Blacklist`) no se toca: este callback de
 * `wxr_export_skip_postmeta` se suma a ella y, como `Meta_Blacklist::skip()` y `F7b\Usages::skip_export()`, respeta un
 * `true` previo. Los metadatos de los documentos que exporta el núcleo ya omiten las metas protegidas (`_*`,
 * `elementor/core/base/document.php`), así que el WXR era la única vía. Vale también para el exportador de WordPress:
 * fuera de su sitio, estas copias no se pueden revertir.
 */
final class Export_Skip {
	/** Metas exactas. */
	const KEYS = array(
		'_digitalisimo_ep_backup',
		'_digitalisimo_ep_backup_page',
		'_digitalisimo_ep_backup_f8',
		'_digitalisimo_ep_f8',
	);

	/** Prefijos: todo lo que Migración guarde con su nombre. */
	const PREFIXES = array( '_digitalisimo_migration_' );

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'wxr_export_skip_postmeta', array( __CLASS__, 'skip' ), 10, 2 );
	}

	/** ¿Es una meta de Migración? */
	public static function blocks( $key ): bool {
		if ( ! is_string( $key ) || '' === $key ) {
			return false;
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

	/**
	 * `wxr_export_skip_postmeta` (10): `true` para las metas de Migración; respeta un `true` previo.
	 *
	 * @param mixed  $skip Decisión anterior.
	 * @param string $key  Clave de la meta.
	 */
	public static function skip( $skip, $key = '' ): bool {
		return (bool) $skip || self::blocks( $key );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
	}
}
