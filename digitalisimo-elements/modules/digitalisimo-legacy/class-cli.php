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
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-elements ep-migrate --url=https://sitio.example/
	 *     wp digitalisimo-elements ep-migrate --convert --url=https://sitio.example/
	 */
	public function __invoke( $args, $assoc ) {
		if ( ! empty( $assoc['revert'] ) ) {
			$count = 0;
			foreach ( Migration::backups() as $post_id ) {
				$count += Migration::revert_document( $post_id ) ? 1 : 0;
			}
			\WP_CLI::success( sprintf( 'Documentos restaurados: %d', $count ) );
			return;
		}
		$rows = array();
		foreach ( Migration::inventory() as $type => $row ) {
			$rows[] = array( 'widget' => $type, 'elementos' => $row['elements'], 'documentos' => $row['documents'], 'adaptador' => $row['map'] ? Translator::target( $type ) : 'pendiente' );
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'widget', 'elementos', 'documentos', 'adaptador' ) );
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
}
