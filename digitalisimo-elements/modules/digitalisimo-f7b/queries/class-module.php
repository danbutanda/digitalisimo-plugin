<?php
namespace Digitalisimo\Elements\F7b\Queries;

use Digitalisimo\Elements\F7b\Flags;
use Digitalisimo\Elements\F7b\Module as F7b;

defined( 'ABSPATH' ) || exit;

/**
 * Consultas guardadas y contextos (F7b, frente C; C2 de la especificación, V.4).
 *
 * Arranque (lo llama `F7b\Module` desde su `BOOT`; estático e idempotente):
 * - sin el constructor de consultas de PRO (`Elementor_Post_Query`) o sin las piezas del frente A, no registra nada;
 * - el tipo `digitalisimo_query` se registra en `init` en la administración, en WP-CLI, en el cron y donde las
 *   consultas están activas; K puede llamar a `Store::register()` antes de importar;
 * - con `Flags::active( 'saved_queries' )` (algún documento las usa, o se está editando): controles y aplicación;
 *   con él falso, **nada enganchado en el front** (criterio 2);
 * - en la administración: la pantalla «Consultas», las acciones AJAX del editor y su script.
 */
final class Module {
	const FLAG = 'saved_queries';

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		$symbols = array(
			'ElementorPro\Modules\QueryControl\Classes\Elementor_Post_Query',
			'Digitalisimo\Elements\F7b\Flags',
			__NAMESPACE__ . '\Store',
			__NAMESPACE__ . '\Args',
			__NAMESPACE__ . '\Runtime',
			__NAMESPACE__ . '\Controls',
			__NAMESPACE__ . '\Screen',
			'add_action',
		);
		if ( ! F7b::requires( $symbols, 'queries' ) ) {
			return;
		}
		self::$initialized = true;

		$admin  = is_admin() || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'WP_CLI' ) && WP_CLI );
		$active = Flags::active( self::FLAG );
		if ( $admin || $active ) {
			if ( did_action( 'init' ) ) {
				Store::register();
			} else {
				add_action( 'init', array( Store::class, 'register' ), 5 );
			}
		}
		if ( $active ) {
			Controls::init();
			Runtime::init();
		}
		if ( is_admin() ) {
			Controls::init_editor();
			Screen::init();
		}
	}
}
