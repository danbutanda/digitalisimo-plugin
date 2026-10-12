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
	 *     wp digitalisimo-migration --network
	 */
	public function __invoke( $args, $assoc ) {
		if ( ! empty( $assoc['network'] ) ) {
			if ( ! empty( $assoc['convert'] ) || ! empty( $assoc['revert'] ) ) {
				\WP_CLI::error( 'La conversión y la restauración se lanzan sitio por sitio con --url.' );
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
		if ( ! empty( $assoc['revert'] ) ) {
			$count = 0;
			foreach ( Migration::backups() as $post_id ) {
				$count += Migration::revert_document( $post_id ) ? 1 : 0;
			}
			\WP_CLI::success( sprintf( 'Documentos restaurados: %d', $count ) );
			return;
		}
		\WP_CLI\Utils\format_items( 'table', self::rows(), array( 'widget', 'elementos', 'documentos', 'adaptador' ) );
		if ( empty( $assoc['convert'] ) ) {
			return;
		}
		if ( Translator::element_pack_active() ) {
			\WP_CLI::error( 'Desactiva Element Pack en este sitio antes de convertir: sus controles ocupan los mismos nombres.' );
		}
		$totals = array( 'native' => 0, 'documents' => 0 );
		foreach ( Migration::pending() as $post_id ) {
			$stats = Migration::convert_document( $post_id );
			if ( ! empty( $stats['native'] ) ) {
				$totals['documents']++;
				$totals['native'] += $stats['native'];
			}
		}
		\WP_CLI::success( sprintf( '%d documentos convertidos (%d elementos): ya sólo usan widgets y ajustes propios.', $totals['documents'], $totals['native'] ) );
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
