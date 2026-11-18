<?php
namespace Digitalisimo\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * `wp digitalisimo-migration` con Migración sola (sin DIGITALÍSIMO Elements todavía): sólo `--run`, que lo instala y
 * sigue en un proceso nuevo con la orden completa (legacy/class-cli.php).
 */
final class Cli {
	/**
	 * Migración completa, como el botón Migrar: respaldo, instalación de los plugins DIGITALÍSIMO, contenido,
	 * importación, tema y limpieza.
	 *
	 * ## OPTIONS
	 *
	 * [--run]
	 * : Migración completa del sitio de --url o, con --network, de toda la red.
	 *
	 * [--network]
	 * : Con --run, toda la red.
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-migration --run
	 *     wp digitalisimo-migration --run --network
	 */
	public function __invoke( $args, $assoc ) {
		if ( empty( $assoc['run'] ) ) {
			\WP_CLI::error( 'Sin DIGITALÍSIMO Elements sólo está disponible --run, que lo instala y migra todo.' );
		}
		Full::cli( ! empty( $assoc['network'] ) );
	}
}
