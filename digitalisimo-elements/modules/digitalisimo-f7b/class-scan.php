<?php
namespace Digitalisimo\Elements\F7b;

defined( 'ABSPATH' ) || exit;

/**
 * Recorrido por lotes de los documentos del constructor (V.6).
 *
 * - **Cuándo:** al cambiar la versión de DIGITALÍSIMO Elements (`admin_init` compara la del estado con
 *   `DIGITALISIMO_ELEMENTS_VERSION`) y al terminar una importación de K (`digitalisimo_elements_kit_imported`,
 *   `kits/runners/class-finish.php`). `schedule()` vuelve a empezar desde el primer documento.
 * - **Cómo:** un evento de cron único (`HOOK`) procesa 50 documentos con `_elementor_data` (por ID ascendente, sin
 *   revisiones, autoguardados ni `auto-draft`; la papelera sí, por si se restaura) y programa el siguiente lote. Por
 *   documento: reescribe `_digitalisimo_uses` (`Usages::sync()`) y dispara
 *   `do_action( 'digitalisimo_elements_f7b_scan_document', int $post_id, array $elements )` (el inventario de
 *   interacciones de V.3 lo escucha). Al terminar, recalcula `digitalisimo_elements_f7b_counts` (`Flags::refresh()`)
 *   y dispara `do_action( 'digitalisimo_elements_f7b_scan_done' )`.
 * - **Estado:** la opción `digitalisimo_elements_f7b_scan` (carga automática, menos de 200 B): `version`, `status`
 *   (`running` o `done`), `after` (último ID), `started`, `updated`. Mientras dura, `running()` es verdadero: la
 *   columna «Usos» dice «Calculando…» y el borrado no se bloquea (como antes de F7b).
 * - **Sin cron que corra:** si un lote lleva más de `STALL` segundos sin avanzar y no hay evento programado, la
 *   siguiente visita a la administración lo vuelve a programar.
 */
final class Scan {
	const HOOK            = 'digitalisimo_elements_f7b_scan';
	const STATE           = 'digitalisimo_elements_f7b_scan';
	const BATCH           = 50;
	const STALL           = 600;
	const ACTION_DOCUMENT = 'digitalisimo_elements_f7b_scan_document';
	const ACTION_DONE     = 'digitalisimo_elements_f7b_scan_done';
	const RUNNING         = 'running';
	const DONE            = 'done';

	/** @var bool */
	private static $initialized = false;

	/** Engancha el evento, la importación de K y la comprobación de versión. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( self::HOOK, array( __CLASS__, 'run' ), 10, 0 );
		add_action( 'digitalisimo_elements_kit_imported', array( __CLASS__, 'schedule' ), 20, 0 );
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'maybe_schedule' ), 20, 0 );
		}
	}

	/** Versión que dispara el recorrido. */
	public static function version(): string {
		return defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? (string) DIGITALISIMO_ELEMENTS_VERSION : '';
	}

	/**
	 * Estado del recorrido, leído sólo de `wp_load_alloptions()`.
	 *
	 * @return array{version:string,status:string,after:int,started:int,updated:int}
	 */
	public static function state(): array {
		$state = array(
			'version' => '',
			'status'  => '',
			'after'   => 0,
			'started' => 0,
			'updated' => 0,
		);
		$all = function_exists( 'wp_load_alloptions' ) ? wp_load_alloptions() : array();
		if ( ! is_array( $all ) || ! array_key_exists( self::STATE, $all ) ) {
			return $state;
		}
		$value = function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $all[ self::STATE ] ) : $all[ self::STATE ];
		if ( ! is_array( $value ) ) {
			return $state;
		}
		foreach ( array( 'version', 'status' ) as $key ) {
			$state[ $key ] = isset( $value[ $key ] ) && is_string( $value[ $key ] ) ? $value[ $key ] : '';
		}
		foreach ( array( 'after', 'started', 'updated' ) as $key ) {
			$state[ $key ] = isset( $value[ $key ] ) ? max( 0, (int) $value[ $key ] ) : 0;
		}
		return $state;
	}

	/** ¿Está calculando? Mientras tanto no se bloquea ningún borrado. */
	public static function running(): bool {
		return self::RUNNING === self::state()['status'];
	}

	/** `admin_init`: versión nueva → recorrido; recorrido parado sin evento → se vuelve a programar. */
	public static function maybe_schedule(): void {
		$state = self::state();
		if ( self::version() !== $state['version'] ) {
			self::schedule();
			return;
		}
		if ( self::RUNNING === $state['status'] && self::now() - $state['updated'] > self::STALL && function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::HOOK ) ) {
			self::queue();
		}
	}

	/** Contrato del brief: empieza (o vuelve a empezar) el recorrido por lotes por cron. */
	public static function schedule(): void {
		$now = self::now();
		self::write(
			array(
				'version' => self::version(),
				'status'  => self::RUNNING,
				'after'   => 0,
				'started' => $now,
				'updated' => $now,
			)
		);
		self::queue();
	}

	/** Evento de cron: un lote, si el recorrido sigue en marcha. */
	public static function run(): void {
		$state = self::state();
		if ( self::RUNNING !== $state['status'] ) {
			return;
		}
		self::batch( $state );
	}

	/**
	 * Procesa un lote desde `$state['after']` y programa el siguiente, o termina.
	 *
	 * @param array $state Estado actual.
	 * @return int Documentos procesados.
	 */
	public static function batch( array $state ): int {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return 0;
		}
		$sql  = "SELECT p.ID AS id, pm.meta_value AS data FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_elementor_data' WHERE p.ID > %d AND p.post_type <> 'revision' AND p.post_status NOT IN ('auto-draft','inherit') ORDER BY p.ID ASC LIMIT %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, (int) $state['after'], self::BATCH ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = is_array( $rows ) ? $rows : array();
		$last = (int) $state['after'];
		foreach ( $rows as $row ) {
			$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( $id <= $last ) {
				continue;
			}
			$last     = $id;
			$elements = isset( $row['data'] ) && is_string( $row['data'] ) ? json_decode( $row['data'], true ) : null;
			$elements = is_array( $elements ) ? $elements : array();
			Usages::sync( $id, $elements );
			do_action( self::ACTION_DOCUMENT, $id, $elements );
		}
		if ( count( $rows ) < self::BATCH ) {
			self::finish( $state );
			return count( $rows );
		}
		$state['after']   = $last;
		$state['updated'] = self::now();
		self::write( $state );
		self::queue();
		return count( $rows );
	}

	/** Último lote: contadores, estado `done` y el aviso público. */
	private static function finish( array $state ): void {
		Flags::refresh();
		Usages::forget();
		$state['status']  = self::DONE;
		$state['updated'] = self::now();
		self::write( $state );
		do_action( self::ACTION_DONE );
	}

	/** Programa el siguiente lote si no hay ya uno. */
	private static function queue(): void {
		if ( ! function_exists( 'wp_schedule_single_event' ) ) {
			return;
		}
		if ( function_exists( 'wp_next_scheduled' ) && wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		wp_schedule_single_event( self::now(), self::HOOK );
	}

	private static function write( array $state ): void {
		update_option(
			self::STATE,
			array(
				'version' => (string) $state['version'],
				'status'  => (string) $state['status'],
				'after'   => (int) $state['after'],
				'started' => (int) $state['started'],
				'updated' => (int) $state['updated'],
			),
			true
		);
	}

	private static function now(): int {
		return time();
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
	}
}
