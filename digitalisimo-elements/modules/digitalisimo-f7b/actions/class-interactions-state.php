<?php
namespace Digitalisimo\Elements\F7b\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Estado de las interacciones PRO de cada sitio (F7b, frente B; D6, C2-21 y V.3 de la especificación).
 *
 * El módulo derivado `modules/interactions/module.php` registra las interacciones PRO (sólo con los widgets
 * atómicos, v4) cuando `enabled()` es verdadero: esa llamada sustituye a la condición de licencia en una sola línea
 * del derivado. El estado es la opción automática `digitalisimo_elements_interactions_state`:
 *
 * - `pending` (o la opción aún no existe): apagadas. Hasta que el recorrido por lotes (`F7b\Scan`) termine, o mientras
 *   haya documentos con interacciones que empezarían a moverse sin que nadie lo pidiera;
 * - `clear`: el recorrido no encontró ninguna; encendidas;
 * - `ack`: el administrador pulsó «Reproducir las interacciones guardadas»; encendidas.
 *
 * `clear` y `ack` no vuelven a `pending`: lo que se guarda o se importa después ya se hizo con ellas encendidas.
 *
 * Inventario: en `digitalisimo_elements_f7b_scan_document` se anota cada documento con alguna interacción cuyo
 * disparador sólo reproduce PRO (`Presets::ADDITIONAL_TRIGGERS` del núcleo: `hover`, `click`, `scrollOn` y
 * `scrollOut`) o con efecto `custom`; en `digitalisimo_elements_f7b_scan_done` se cierra la lista y un sitio sin ninguno
 * pasa a `clear`. La lista vive en una opción sin carga automática y se muestra en un aviso persistente, sólo en las
 * pantallas de DIGITALÍSIMO (`?page=digitalisimo*`, con el diseño `digitalisimo-ui`) y sólo a quien tiene
 * `manage_options`. No es una opción de ajustes: no tiene paridad de red.
 *
 * Movimiento reducido (C2-21 a): con las interacciones encendidas, un script en línea `after` de `motion-js` (sólo se
 * imprime donde el núcleo encola `motion`) pone `Motion.MotionGlobalConfig.skipAnimations = true` si el visitante
 * prefiere menos movimiento. Los paquetes no se tocan.
 */
final class Interactions_State {
	const OPTION    = 'digitalisimo_elements_interactions_state';
	const INVENTORY = 'digitalisimo_elements_interactions_inventory';
	const ACTION    = 'digitalisimo_elements_interactions_play';
	const NOTICE    = 'digitalisimo_interactions';

	const PENDING = 'pending';
	const CLEAR   = 'clear';
	const ACK     = 'ack';
	const STATES  = array( self::PENDING, self::CLEAR, self::ACK );

	/** Disparadores que sólo reproduce PRO (`elementor/modules/interactions/presets.php:19`). */
	const PRO_TRIGGERS = array( 'scrollOut', 'scrollOn', 'hover', 'click' );

	/** Efectos que sólo reproduce PRO (`presets.php:23`). */
	const PRO_EFFECTS = array( 'custom' );

	/** Documentos que se guardan en la lista; el resto sólo cuenta. */
	const MAX_LIST = 200;

	/** Documentos que muestra el aviso. */
	const SHOWN = 20;

	const MOTION_HANDLE = 'motion-js';
	const MOTION_SCRIPT = "if(matchMedia('(prefers-reduced-motion: reduce)').matches&&window.Motion&&Motion.MotionGlobalConfig)Motion.MotionGlobalConfig.skipAnimations=true";

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_action( 'digitalisimo_elements_f7b_scan_document', array( __CLASS__, 'scan_document' ), 10, 2 );
		add_action( 'digitalisimo_elements_f7b_scan_done', array( __CLASS__, 'scan_done' ), 10, 0 );
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
			add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
			add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_play' ) );
		} elseif ( self::enabled() ) {
			add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'motion' ), 30 );
		}
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'digitalisimo-elements interactions', Interactions_Cli::class );
		}
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Estado.

	/** ¿Se reproducen las interacciones PRO en este sitio? Sólo lee `wp_load_alloptions()`: ninguna consulta. */
	public static function enabled(): bool {
		return in_array( self::state(), array( self::CLEAR, self::ACK ), true );
	}

	/** `pending`, `clear` o `ack`; `pending` si la opción no existe o no tiene carga automática. */
	public static function state(): string {
		$all = function_exists( 'wp_load_alloptions' ) ? wp_load_alloptions() : array();
		$raw = is_array( $all ) && isset( $all[ self::OPTION ] ) ? (string) $all[ self::OPTION ] : '';
		return in_array( $raw, self::STATES, true ) ? $raw : self::PENDING;
	}

	/** Cambia el estado (con carga automática). */
	public static function set_state( string $state ): bool {
		if ( ! in_array( $state, self::STATES, true ) ) {
			return false;
		}
		if ( false === get_option( self::OPTION, false ) ) {
			return add_option( self::OPTION, $state, '', true );
		}
		$done = update_option( self::OPTION, $state, true );
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( self::OPTION, true );
		}
		return $done || self::state() === $state;
	}

	/** `admin_init`: la opción existe siempre (como `Flags::ensure()`), con carga automática. */
	public static function admin_init(): void {
		$all = function_exists( 'wp_load_alloptions' ) ? wp_load_alloptions() : array();
		if ( is_array( $all ) && array_key_exists( self::OPTION, $all ) ) {
			return;
		}
		$current = get_option( self::OPTION, false );
		if ( false === $current ) {
			add_option( self::OPTION, self::PENDING, '', true );
		} elseif ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( self::OPTION, true );
		}
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Inventario.

	/** ¿Tiene este árbol de elementos alguna interacción que sólo reproduce PRO? */
	public static function uses_pro( $elements, int $depth = 0 ): bool {
		if ( ! is_array( $elements ) || $depth > 64 ) {
			return false;
		}
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( isset( $element['interactions'] ) && self::interactions_use_pro( $element['interactions'] ) ) {
				return true;
			}
			if ( ! empty( $element['elements'] ) && self::uses_pro( $element['elements'], $depth + 1 ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Interacciones de un elemento (cadena JSON o arreglo con `items`, como `Interactions\Parser`): ¿alguna con un
	 * disparador o un efecto de PRO? Admite los valores con forma de propiedad (`{ $$type, value }`) y planos.
	 *
	 * @param mixed $interactions Valor guardado.
	 */
	public static function interactions_use_pro( $interactions ): bool {
		if ( is_string( $interactions ) ) {
			$interactions = json_decode( $interactions, true );
		}
		if ( ! is_array( $interactions ) ) {
			return false;
		}
		$items = isset( $interactions['items'] ) && is_array( $interactions['items'] ) ? $interactions['items'] : array();
		foreach ( $items as $item ) {
			$value = self::unwrap( $item );
			if ( ! is_array( $value ) ) {
				continue;
			}
			$trigger = self::unwrap( $value['trigger'] ?? null );
			if ( is_string( $trigger ) && in_array( $trigger, self::PRO_TRIGGERS, true ) ) {
				return true;
			}
			$animation = self::unwrap( $value['animation'] ?? null );
			if ( is_array( $animation ) ) {
				$effect = self::unwrap( $animation['effect'] ?? null );
				if ( ( is_string( $effect ) && in_array( $effect, self::PRO_EFFECTS, true ) ) || ! empty( $animation['custom_effect'] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * `digitalisimo_elements_f7b_scan_document`: anota o quita el documento de la lista en curso.
	 *
	 * @param mixed $post_id  Documento.
	 * @param mixed $elements Árbol de elementos de `_elementor_data`.
	 */
	public static function scan_document( $post_id, $elements = array() ): void {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		$inventory = self::inventory();
		$found     = $inventory['running'];
		$uses      = self::uses_pro( $elements );
		if ( $uses === isset( $found[ $post_id ] ) ) {
			return;
		}
		if ( $uses ) {
			$found[ $post_id ] = 1;
		} else {
			unset( $found[ $post_id ] );
		}
		$inventory['running'] = $found;
		self::save_inventory( $inventory );
	}

	/** `digitalisimo_elements_f7b_scan_done`: cierra la lista y, sin documentos, `pending` pasa a `clear`. */
	public static function scan_done(): void {
		$inventory          = self::inventory();
		$ids                = array_map( 'intval', array_keys( $inventory['running'] ) );
		sort( $ids );
		$inventory['docs']    = array_slice( $ids, 0, self::MAX_LIST );
		$inventory['total']   = count( $ids );
		$inventory['done']    = true;
		$inventory['running'] = array();
		self::save_inventory( $inventory );
		if ( self::PENDING === self::state() ) {
			self::set_state( $ids ? self::PENDING : self::CLEAR );
		}
	}

	/** Lista guardada: `docs` (IDs), `total`, `done` y la lista del recorrido en curso. */
	public static function inventory(): array {
		$raw = get_option( self::INVENTORY, array() );
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'docs'    => array_values( array_filter( array_map( 'intval', (array) ( $raw['docs'] ?? array() ) ) ) ),
			'total'   => max( 0, (int) ( $raw['total'] ?? 0 ) ),
			'done'    => ! empty( $raw['done'] ),
			'running' => is_array( $raw['running'] ?? null ) ? $raw['running'] : array(),
		);
	}

	private static function save_inventory( array $inventory ): void {
		if ( false === get_option( self::INVENTORY, false ) ) {
			add_option( self::INVENTORY, $inventory, '', false );
			return;
		}
		update_option( self::INVENTORY, $inventory, false );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Aviso y acción.

	/** ¿Pantalla de DIGITALÍSIMO (`?page=digitalisimo*`)? */
	public static function own_screen(): bool {
		if ( function_exists( 'digitalisimo_ui_screen' ) ) {
			return (bool) digitalisimo_ui_screen();
		}
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide si se muestra el aviso.
		return 0 === strpos( $page, 'digitalisimo' );
	}

	/** Aviso persistente mientras el estado sea `pending` y el inventario tenga documentos. */
	public static function notice(): void {
		if ( ! self::own_screen() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo muestra el resultado de la acción.
		if ( isset( $_GET[ self::NOTICE ] ) && 'played' === $_GET[ self::NOTICE ] && self::ACK === self::state() ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Las interacciones guardadas ya se reproducen en este sitio. Con movimiento reducido no se animan.', 'pro-elements' ) . '</p></div>';
			return;
		}
		if ( self::PENDING !== self::state() ) {
			return;
		}
		$inventory = self::inventory();
		if ( ! $inventory['done'] || ! $inventory['docs'] ) {
			return;
		}
		echo self::notice_html( $inventory ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en notice_html().
	}

	/** HTML del aviso: texto, lista de documentos con enlace y el formulario con su nonce. */
	public static function notice_html( array $inventory ): string {
		$total = max( count( $inventory['docs'] ), (int) $inventory['total'] );
		$html  = '<div class="notice notice-warning digitalisimo-interactions-notice"><p><strong>' . esc_html__( 'Interacciones guardadas en pausa', 'pro-elements' ) . '</strong></p>';
		$html .= '<p>' . esc_html(
			sprintf(
				/* translators: %d: número de documentos. */
				_n(
					'%d documento tiene interacciones (al pasar el puntero, al hacer clic o al desplazarse) que esta versión de DIGITALÍSIMO Elements puede reproducir. Siguen en pausa en este sitio hasta que lo decidas: revísalo y pulsa «Reproducir las interacciones guardadas».',
					'%d documentos tienen interacciones (al pasar el puntero, al hacer clic o al desplazarse) que esta versión de DIGITALÍSIMO Elements puede reproducir. Siguen en pausa en este sitio hasta que lo decidas: revísalos y pulsa «Reproducir las interacciones guardadas».',
					$total,
					'pro-elements'
				),
				$total
			)
		) . '</p><ul>';
		foreach ( array_slice( $inventory['docs'], 0, self::SHOWN ) as $id ) {
			$title = get_the_title( $id );
			$title = '' === trim( (string) $title ) ? sprintf( /* translators: %d: ID. */ __( 'Documento %d', 'pro-elements' ), $id ) : $title;
			$edit  = current_user_can( 'edit_post', $id ) ? add_query_arg(
				array(
					'post'   => $id,
					'action' => 'elementor',
				),
				admin_url( 'post.php' )
			) : '';
			$html .= '<li>' . ( '' !== $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . '</li>';
		}
		$html .= '</ul>';
		if ( $total > self::SHOWN ) {
			$html .= '<p>' . esc_html( sprintf( /* translators: %d: documentos que no se listan. */ __( 'Y %d más.', 'pro-elements' ), $total - self::SHOWN ) ) . '</p>';
		}
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		$html .= wp_nonce_field( self::nonce_action(), '_wpnonce', true, false );
		$html .= '<p><button type="submit" class="button button-primary">' . esc_html__( 'Reproducir las interacciones guardadas', 'pro-elements' ) . '</button></p></form></div>';
		return $html;
	}

	/** Nonce de la acción, por sitio. */
	public static function nonce_action(): string {
		return self::ACTION . '|' . ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 );
	}

	/**
	 * Valida y aplica «Reproducir las interacciones guardadas» sin redirigir (lo prueban las pruebas).
	 *
	 * @param array  $request Datos del formulario (`_wpnonce`).
	 * @param string $method  Método de la petición.
	 * @return string `played`, `forbidden`, `nonce` o `method`.
	 */
	public static function process_play( array $request, string $method ): string {
		if ( 'POST' !== strtoupper( $method ) ) {
			return 'method';
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return 'forbidden';
		}
		$nonce = isset( $request['_wpnonce'] ) && is_string( $request['_wpnonce'] ) ? $request['_wpnonce'] : '';
		if ( ! wp_verify_nonce( $nonce, self::nonce_action() ) ) {
			return 'nonce';
		}
		self::set_state( self::ACK );
		return 'played';
	}

	/** `admin-post.php`: aplica y vuelve a la pantalla de origen (si es de DIGITALÍSIMO) con el resultado. */
	public static function handle_play(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET';
		$result = self::process_play( wp_unslash( $_POST ), $method ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- process_play() verifica el nonce.
		if ( 'played' !== $result ) {
			wp_die( esc_html( 'forbidden' === $result ? __( 'No tienes permiso para cambiar esto.', 'pro-elements' ) : __( 'El formulario caducó. Vuelve a la pantalla e inténtalo de nuevo.', 'pro-elements' ) ), '', array( 'response' => 'forbidden' === $result ? 403 : 400 ) );
		}
		$back = wp_get_referer();
		$home = admin_url( 'admin.php?page=digitalisimo-elements-front' );
		$back = is_string( $back ) && false !== strpos( $back, 'page=digitalisimo' ) ? $back : $home;
		wp_safe_redirect( add_query_arg( self::NOTICE, 'played', $back ), 303, 'DIGITALÍSIMO Elements' );
		exit;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Movimiento reducido.

	/** Script en línea `after` de `motion-js`, sólo si el núcleo lo registró. */
	public static function motion(): void {
		if ( function_exists( 'wp_script_is' ) && wp_script_is( self::MOTION_HANDLE, 'registered' ) ) {
			wp_add_inline_script( self::MOTION_HANDLE, self::MOTION_SCRIPT, 'after' );
		}
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
	}

	/** `{ $$type, value }` → `value`; cualquier otro valor, tal cual. */
	private static function unwrap( $value ) {
		if ( is_array( $value ) && array_key_exists( 'value', $value ) && array_key_exists( '$$type', $value ) ) {
			return $value['value'];
		}
		return $value;
	}
}
