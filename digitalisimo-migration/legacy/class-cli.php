<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-migration.php';

/**
 * Inventario y conversión de documentos de Element Pack del sitio indicado con --url (uno por ejecución en Multisite).
 */
final class Cli {
	/**
	 * Muestra el inventario o convierte/restaura los documentos del sitio.
	 *
	 * ## OPTIONS
	 *
	 * [--convert]
	 * : Convierte todos los documentos pendientes al formato propio, con copia del original (Element Pack desactivado).
	 *
	 * [--revert]
	 * : Restaura los documentos convertidos a su versión original.
	 *
	 * [--network]
	 * : En Multisite, sólo muestra el inventario de todos los sitios (no convierte ni restaura).
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-migration --url=https://sitio.example/
	 *     wp digitalisimo-migration --convert --url=https://sitio.example/
	 *     wp digitalisimo-migration --repair --url=https://sitio.example/   (reglas de visibilidad perdidas, desde la copia original)
	 *     wp digitalisimo-migration --recover --url=https://sitio.example/  (páginas sobrescritas por la conversión de una revisión, desde su última revisión guardada)
	 *     wp digitalisimo-migration --network
	 */
	public function __invoke( $args, $assoc ) {
		if ( ! empty( $assoc['network'] ) ) {
			if ( ! empty( $assoc['convert'] ) || ! empty( $assoc['revert'] ) || ! empty( $assoc['repair'] ) || ! empty( $assoc['recover'] ) ) {
				\WP_CLI::error( 'La conversión, la reparación, la recuperación y la restauración se lanzan sitio por sitio con --url.' );
			}
			$rows = array();
			foreach ( is_multisite() ? get_sites( array( 'number' => 0, 'fields' => 'ids' ) ) : array( get_current_blog_id() ) as $site_id ) {
				switch_to_blog( (int) $site_id );
				foreach ( self::rows() as $row ) {
					$rows[] = array( 'sitio' => home_url( '/' ) ) + $row;
				}
				restore_current_blog();
			}
			\WP_CLI\Utils\format_items( 'table', $rows, array( 'sitio', 'widget', 'elementos', 'documentos', 'adaptador' ) );
			return;
		}
		if ( ! empty( $assoc['repair'] ) ) {
			$totals = Migration::repair_all();
			\WP_CLI::success( sprintf( 'Reglas de visibilidad recuperadas en %d documentos (%d elementos).', $totals['documents'], $totals['elements'] ) );
			return;
		}
		if ( ! empty( $assoc['recover'] ) ) {
			$count = 0;
			foreach ( Migration::overwritten_parents() as $parent_id => $info ) {
				$count += '' !== Migration::recover_from_revision( $parent_id, $info['revision'] ) ? 1 : 0;
			}
			\WP_CLI::success( sprintf( 'Páginas recuperadas desde su última revisión guardada o su copia original: %d', $count ) );
			$left = count( Migration::overwritten_parents() );
			if ( $left ) {
				\WP_CLI::warning( sprintf( '%d páginas sin revisión guardada ni copia original distinta de la que las sobrescribió: revísalas en el editor (Herramientas → Migrar Element Pack las lista).', $left ) );
			}
			return;
		}
		if ( ! empty( $assoc['revert'] ) ) {
			$count = 0;
			foreach ( Migration::backups() as $post_id ) {
				$count += Migration::revert_document( $post_id ) ? 1 : 0;
			}
			\WP_CLI::success( sprintf( 'Documentos restaurados: %d', $count ) );
			return;
		}
		\WP_CLI\Utils\format_items( 'table', self::rows(), array( 'widget', 'elementos', 'documentos', 'adaptador' ) );
		$figures = Migration::figures();
		if ( $figures['elements'] ) {
			\WP_CLI::log( sprintf( 'Máscaras y separadores de Element Pack: %d elementos en %d documentos.', $figures['elements'], $figures['documents'] ) );
		}
		if ( $figures['missing'] ) {
			\WP_CLI::warning( 'Sin archivo (la conversión los retira): ' . implode( ', ', $figures['missing'] ) . '. Mantén Element Pack instalado y desactivado hasta convertir.' );
		}
		if ( empty( $assoc['convert'] ) ) {
			return;
		}
		if ( Translator::element_pack_active() ) {
			\WP_CLI::error( 'Desactiva Element Pack en este sitio antes de convertir: sus controles ocupan los mismos nombres.' );
		}
		$totals = array( 'native' => 0, 'documents' => 0 );
		foreach ( Migration::convertible() as $post_id ) {
			$stats = Migration::convert_document( $post_id );
			if ( ! empty( $stats['native'] ) ) {
				$totals['documents']++;
				$totals['native'] += $stats['native'];
			}
		}
		\WP_CLI::success( sprintf( '%d documentos convertidos (%d elementos): ya sólo usan widgets y ajustes propios.', $totals['documents'], $totals['native'] ) );
		$blocked = count( Migration::classify()['blocked'] );
		if ( $blocked ) {
			\WP_CLI::warning( sprintf( '%d documentos conservan sólo widgets sin adaptador y no cambian: sustitúyelos en el editor por la alternativa que indica la tabla.', $blocked ) );
		}
	}

	/** Inventario del sitio en curso; un widget sin adaptador muestra su motivo. */
	private static function rows() {
		$rows = array();
		foreach ( Migration::inventory() as $type => $row ) {
			$rows[] = array( 'widget' => $type, 'elementos' => $row['elements'], 'documentos' => $row['documents'], 'adaptador' => $row['map'] ? Translator::target( $type ) : 'sin adaptador: ' . Migration::reason( $type ) );
		}
		return $rows;
	}
}
