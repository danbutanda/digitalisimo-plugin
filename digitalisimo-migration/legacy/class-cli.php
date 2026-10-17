<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-migration.php';
require_once __DIR__ . '/class-runner.php';
Runner::load();

/**
 * Migrar en un clic desde la línea de órdenes (--run, en el sitio de --url o, con --network, en toda la red), y el
 * inventario, la conversión y las recuperaciones de Element Pack del sitio indicado con --url.
 */
final class Cli {
	/**
	 * Muestra el inventario o convierte/restaura los documentos del sitio.
	 *
	 * ## OPTIONS
	 *
	 * [--run]
	 * : Migración completa, como el botón Migrar: desactiva Element Pack, Elementor Pro, PRO Elements y Elementor,
	 * convierte el contenido de Element Pack y el de los widgets fusionados, vacía las cachés y muestra el informe. Con
	 * --network, lo mismo en toda la red (cada sitio en su propio proceso).
	 *
	 * [--convert]
	 * : Convierte todos los documentos pendientes al formato propio, con copia del original (Element Pack desactivado).
	 *
	 * [--revert]
	 * : Restaura los documentos convertidos a su versión original.
	 *
	 * [--network]
	 * : En Multisite: con --run, migra toda la red (cada sitio en su propio proceso); sin --run, sólo muestra el
	 * inventario de todos los sitios (no convierte ni restaura).
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-migration --run --url=https://sitio.example/
	 *     wp digitalisimo-migration --run --network
	 *     wp digitalisimo-migration --url=https://sitio.example/
	 *     wp digitalisimo-migration --convert --url=https://sitio.example/
	 *     wp digitalisimo-migration --repair --url=https://sitio.example/   (reglas de visibilidad perdidas, desde la copia original)
	 *     wp digitalisimo-migration --recover --url=https://sitio.example/  (páginas sobrescritas por la conversión de una revisión, desde su última revisión guardada)
	 *     wp digitalisimo-migration --network
	 */
	public function __invoke( $args, $assoc ) {
		if ( ! empty( $assoc['run'] ) ) {
			empty( $assoc['network'] ) ? self::run_site() : self::run_network();
			return;
		}
		if ( ! empty( $assoc['network'] ) ) {
			if ( ! empty( $assoc['convert'] ) || ! empty( $assoc['revert'] ) || ! empty( $assoc['repair'] ) || ! empty( $assoc['recover'] ) ) {
				\WP_CLI::error( 'La conversión, la reparación, la recuperación y la restauración se lanzan sitio por sitio con --url.' );
			}
			$rows    = array();
			$without = array();
			foreach ( is_multisite() ? get_sites( array( 'number' => 0, 'fields' => 'ids' ) ) : array( get_current_blog_id() ) as $site_id ) {
				switch_to_blog( (int) $site_id );
				foreach ( self::rows() as $row ) {
					$rows[] = array( 'sitio' => home_url( '/' ) ) + $row;
				}
				// Lo que la migración no convierte en ese sitio, como en la columna «Sin equivalente» de la pantalla de red.
				$line = Dropped::summary( Dropped::analyze(), Dropped::kept() );
				if ( '' !== $line ) {
					$without[] = home_url( '/' ) . ': ' . $line;
				}
				restore_current_blog();
			}
			\WP_CLI\Utils\format_items( 'table', $rows, array( 'sitio', 'widget', 'elementos', 'documentos', 'adaptador' ) );
			foreach ( $without as $line ) {
				\WP_CLI::warning( 'Sin equivalente en ' . $line . '. Detalle con --url en cada sitio.' );
			}
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
				\WP_CLI::warning( sprintf( '%d páginas sin revisión guardada ni copia original distinta de la que las sobrescribió: revísalas en el editor (Herramientas → Migrar a DIGITALÍSIMO Elements las lista).', $left ) );
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
		// Lo que Migrar no convierte, con el mismo listado que la herramienta del sitio.
		$dropped = Dropped::analyze();
		if ( $dropped['docs'] || $dropped['omitted'] ) {
			\WP_CLI::warning( self::text( Dropped::render( Dropped::describe( $dropped ), $dropped['omitted'], Dropped::INTRO ) ) );
		}
		$kept = Dropped::kept_messages( Dropped::kept() );
		foreach ( array_merge( $kept['pending'], $kept['info'] ) as $message ) {
			\WP_CLI::warning( $message );
		}
		if ( $kept['idle'] ) {
			\WP_CLI::log( Dropped::idle_message( $kept['idle'] ) );
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

	/**
	 * Migración del sitio en curso. Tras desactivar plugins, este proceso aún los tiene cargados: la tarea continúa en un
	 * proceso nuevo de WP-CLI (con los mismos parámetros globales, --url incluido), que ya arranca sin ellos.
	 */
	private static function run_site() {
		$state = Runner::run( true, true );
		if ( ! empty( $state['busy'] ) ) {
			\WP_CLI::error( 'Otra petición está ejecutando la migración de este sitio: espera a que termine.' );
		}
		if ( ! empty( $state['pause'] ) ) {
			\WP_CLI::log( 'Plugins desactivados: ' . implode( ', ', (array) $state['counts']['deactivated'] ) . '. La migración continúa en un proceso nuevo.' );
			// Si el proceso nuevo falla, esta orden termina con su mismo código de salida.
			\WP_CLI::runcommand( 'digitalisimo-migration --run', array( 'launch' => true, 'exit_error' => true, 'return' => false ) );
			return;
		}
		\WP_CLI::log( self::text( Runner::render( $state ) ) );
		if ( empty( $state['done'] ) ) {
			\WP_CLI::error( 'La migración no terminó: vuelve a ejecutar la orden; sigue donde quedó.' );
		}
		\WP_CLI::success( Runner::has_pending( $state ) ? 'Migración completada, con asuntos por revisar (arriba).' : 'Migración completada.' );
	}

	/** Migración de la red: desactiva lo activado en toda la red, encola cada sitio y migra cada uno en su propio proceso. */
	private static function run_network() {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'Esta instalación no es una red: usa --run sin --network.' );
		}
		$job    = Runner::network_start( true );
		$failed = array();
		foreach ( (array) ( $job['status'] ?? array() ) as $site_id => $row ) {
			if ( 'running' !== $row['status'] ) {
				continue;
			}
			\WP_CLI::log( sprintf( '— %s (%s)', $row['name'], $row['url'] ) );
			// Con la salida en directo; si el proceso de un sitio falla, su estado final lo dice.
			\WP_CLI::runcommand( \WP_CLI\Utils\esc_cmd( 'digitalisimo-migration --run --url=%s', $row['url'] ), array( 'launch' => true, 'exit_error' => false, 'return' => false ) );
		}
		$job = Runner::network_summary();
		foreach ( (array) ( $job['status'] ?? array() ) as $row ) {
			if ( 'done' !== $row['status'] ) {
				$failed[] = $row['url'];
			}
		}
		\WP_CLI::log( self::text( Runner::render_network( $job ) ) );
		if ( $failed || empty( $job['done'] ) ) {
			\WP_CLI::error( 'La migración de la red no terminó' . ( $failed ? ' (falló en ' . implode( ', ', $failed ) . ')' : '' ) . ': vuelve a ejecutar la orden; cada sitio sigue donde quedó.' );
		}
		\WP_CLI::success( 'Migración de la red completada.' );
	}

	/** El informe en texto para la terminal. */
	private static function text( $html ) {
		$text = str_replace( array( '</p>', '</li>', '</tr>', '</h3>', '</div>' ), "\n", (string) $html );
		$text = html_entity_decode( wp_strip_all_tags( str_replace( '</td>', ' · ', $text ) ), ENT_QUOTES, 'UTF-8' );
		return trim( (string) preg_replace( "/\n\s*\n+/", "\n", $text ) );
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
