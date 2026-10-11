<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-migration.php';

/**
 * Migra documentos de Element Pack del sitio indicado con --url (uno por ejecución en Multisite).
 */
final class Cli {
	/**
	 * Muestra el inventario o traduce/restaura los documentos del sitio.
	 *
	 * ## OPTIONS
	 *
	 * [--convert]
	 * : Traduce todos los documentos pendientes, con copia del original.
	 *
	 * [--revert]
	 * : Restaura los documentos traducidos a su versión original.
	 *
	 * [--network]
	 * : En Multisite, sólo muestra el inventario de todos los sitios (no traduce ni restaura).
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-elements ep-migrate --url=https://sitio.example/
	 *     wp digitalisimo-elements ep-migrate --convert --url=https://sitio.example/
	 *     wp digitalisimo-elements ep-migrate --network
	 */
	public function __invoke( $args, $assoc ) {
		if ( ! empty( $assoc['network'] ) ) {
			if ( ! empty( $assoc['convert'] ) || ! empty( $assoc['revert'] ) ) {
				\WP_CLI::error( 'La traducción y la restauración se lanzan sitio por sitio con --url.' );
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
		$totals = array( 'native' => 0, 'adapter' => 0, 'documents' => 0 );
		foreach ( Migration::documents() as $post_id ) {
			$stats = Migration::convert_document( $post_id );
			if ( ! empty( $stats['native'] ) || ! empty( $stats['adapter'] ) ) {
				$totals['documents']++;
				$totals['native']  += $stats['native'];
				$totals['adapter'] += $stats['adapter'];
			}
		}
		\WP_CLI::success( sprintf( '%d documentos traducidos: %d elementos al widget propio y %d en su adaptador.', $totals['documents'], $totals['native'], $totals['adapter'] ) );
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
