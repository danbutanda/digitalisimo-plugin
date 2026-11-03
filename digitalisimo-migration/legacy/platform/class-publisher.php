<?php
namespace Digitalisimo\Elements\Legacy\Platform;

defined( 'ABSPATH' ) || exit;

/**
 * Publicación perezosa de las copias del Theme Builder y del CSS global (F8, frente G; V.5.2, contrato C7).
 *
 * Las copias nacen siempre en borrador. Este publicador las publica en `init` (prioridad 1) de la **primera petición
 * en la que Element Pack ya no está cargado** y las devuelve a borrador si vuelve. Así:
 *
 * - **Nunca dos cabeceras:** con Element Pack cargado, el filtro `get_post_status` devuelve `draft` para toda copia
 *   anotada (también para las de las peticiones que empezaron antes del cambio) y `sync()` las despublica.
 * - **Nunca una petición sin cabecera:** sin Element Pack, el filtro devuelve `publish` para las copias publicables
 *   aunque la base de datos todavía no lo diga (peticiones simultáneas) y `sync()` las publica.
 * - **Nada que limpiar:** sin Migración (desinstalada, desactivada o 1.0.20) el estado guardado es el último que se
 *   aplicó, coherente con Element Pack en ese momento. Reactivar Element Pack sin Migración 1.0.21 sí pintaría las dos
 *   cabeceras: límite declarado (V.18).
 *
 * **Estado** en `digitalisimo_migration_tb_state`, con carga automática, sólo mientras haya copias:
 *
 *     { v:1, allow:bool, published:bool, tb:int[], css:int[], hold:int[] }
 *
 * - `tb`: copias publicables (se publican con el objetivo verdadero); `css`: CSS global de H (se publica revalidado
 *   con `Snippets::revalidate()`); `hold`: copias que no se publican solas (inactivas, en revisión, duplicadas) pero
 *   que tampoco pueden publicarse a mano mientras Element Pack esté cargado (C2-26). Entre las tres, ≤ 150 IDs.
 * - `allow`: lo pone la tarea en `deactivate` (`allow( true )`) si se cumplen las cinco condiciones de V.5.3.
 * - `published`: la base de datos puede tener copias publicadas (lo escribe `sync()` y la publicación a mano).
 *
 * **Objetivo** (`target()`): `allow`, sin Element Pack cargado, sin Elementor Pro ni PRO Elements activos y con el
 * Theme Builder de Elements disponible. Sin Element Pack pero con el objetivo falso (un conflicto, sin permiso) no se
 * toca nada: sólo Element Pack cargado obliga a despublicar.
 *
 * Toda escritura del estado va bajo el cerrojo `digitalisimo_migration_publish_lock` (`INSERT IGNORE` con token, se
 * roba a los 60 s) y relee el valor guardado. Los cambios de estado de las entradas son `UPDATE … WHERE ID = %d AND
 * post_status = %s`: idempotentes, dos peticiones a la vez no hacen daño.
 */
final class Publisher {
	const OPTION   = 'digitalisimo_migration_tb_state';
	const LOCK     = 'digitalisimo_migration_publish_lock';
	const LOCK_TTL = 60;
	const MAX      = 150;
	const KINDS    = array( 'tb', 'css', 'hold' );
	const NOTICE   = 'digitalisimo_migration_tb_notice_';

	const THEME_BUILDER = '\\ElementorPro\\Modules\\ThemeBuilder\\Module';
	const PRIORITY      = '\\Digitalisimo\\Elements\\Design\\Theme_Builder\\Priority';
	const TRANSLATOR    = '\\Digitalisimo\\Elements\\Legacy\\Translator';
	const SNIPPETS      = '\\Digitalisimo\\Elements\\Legacy\\Platform\\Snippets';

	/** @var bool */
	private static $initialized = false;
	/** @var bool ganchos de las copias puestos (sólo con estado). */
	private static $hooked = false;
	/** @var bool cambio de estado propio en curso (el gancho de transiciones no lo cuenta como manual). */
	private static $syncing = false;
	/** @var array<int, array|null> estado leído, por sitio. */
	private static $memo = array();
	/** @var array<int, array<string, array<int,bool>>> IDs por clase, por sitio. */
	private static $sets = array();
	/** @var array|null entorno simulado (pruebas): ep, conflict, builder, switched. */
	private static $env = null;

	/* ================================================================ Arranque */

	/**
	 * Engancha `sync()` en `init` (prioridad 1) en toda petición y, si el sitio tiene copias, el filtro
	 * `get_post_status`, la guarda de publicación y el seguimiento de cambios hechos a mano.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'init', array( __CLASS__, 'sync' ), 1 );
		if ( null !== self::state() ) {
			self::hook();
		}
	}

	/** Ganchos de las copias: sólo en sitios con copias (sin ellos, ni una llamada de más en `get_post_status`). */
	private static function hook(): void {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;
		add_filter( 'get_post_status', array( __CLASS__, 'filter_status' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'guard' ), 10, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'on_deleted' ), 10, 1 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/* ================================================================ Contrato C7 */

	/** Anota un ID: `tb` (copia publicable), `css` (CSS global) u `hold` (copia que sólo se protege). */
	public static function track( string $kind, int $id ): void {
		if ( $id <= 0 || ! in_array( $kind, self::KINDS, true ) || self::pure() ) {
			return;
		}
		self::write(
			static function ( array $state ) use ( $kind, $id ): array {
				foreach ( self::KINDS as $other ) {
					$state[ $other ] = array_values( array_diff( $state[ $other ], array( $id ) ) );
				}
				if ( self::count( $state ) < self::MAX ) {
					$state[ $kind ][] = $id;
					sort( $state[ $kind ] );
				}
				return $state;
			}
		);
		self::hook();
	}

	/** Deja de seguir un ID (revertido, borrado). */
	public static function untrack( int $id ): void {
		if ( $id <= 0 || self::pure() || null === self::state() ) {
			return;
		}
		self::write(
			static function ( array $state ) use ( $id ): array {
				foreach ( self::KINDS as $kind ) {
					$state[ $kind ] = array_values( array_diff( $state[ $kind ], array( $id ) ) );
				}
				return $state;
			}
		);
	}

	/**
	 * Permite (o retira) la publicación. La tarea lo llama en `deactivate`, justo antes de desactivar Element Pack, si
	 * se cumplen las cinco condiciones de V.5.3 (`Theme_Builder::before_deactivate()`).
	 */
	public static function allow( bool $on ): void {
		if ( self::pure() || null === self::state() ) {
			return;
		}
		self::write(
			static function ( array $state ) use ( $on ): array {
				$state['allow'] = $on;
				return $state;
			}
		);
	}

	/** ¿Deben verse publicadas las copias en esta petición? */
	public static function target(): bool {
		$state = self::state();
		return null !== $state && $state['allow'] && ! self::ep_loaded() && ! self::conflict() && self::builder();
	}

	/**
	 * `init` (prioridad 1), en toda petición: lleva la base de datos al objetivo si aún no lo está.
	 *
	 * - Con Element Pack cargado y `published`: `publish → draft` en todas las copias anotadas.
	 * - Con el objetivo y sin `published`: `draft → publish` en `tb` (y en `css` si `Snippets::revalidate()` lo
	 *   aprueba; si no, sale de la lista y queda en `denied`), las copias entran en la caché de condiciones y se
	 *   reindexa su prioridad manual.
	 * - Si no, nada (ni una consulta).
	 */
	public static function sync(): void {
		if ( self::switched() ) {
			return;
		}
		$state = self::state();
		if ( null === $state ) {
			return;
		}
		$ep = self::ep_loaded();
		if ( $ep && $state['published'] ) {
			self::$syncing = true;
			try {
				foreach ( array_merge( $state['tb'], $state['hold'], $state['css'] ) as $id ) {
					self::set_status( (int) $id, 'publish', 'draft' );
				}
			} finally {
				self::$syncing = false;
			}
			self::write(
				static function ( array $fresh ): array {
					$fresh['published'] = false;
					return $fresh;
				},
				false
			);
			return;
		}
		if ( $ep || $state['published'] || ! self::target() ) {
			return;
		}
		$denied = array();
		self::$syncing = true;
		try {
			// Primero en la caché de condiciones: una copia publicada que el Theme Builder no conoce no se pinta.
			if ( $state['tb'] && class_exists( __NAMESPACE__ . '\\Theme_Builder' ) ) {
				Theme_Builder::ensure_cache( $state['tb'] );
			}
			foreach ( $state['tb'] as $id ) {
				if ( self::set_status( (int) $id, 'draft', 'publish' ) ) {
					self::reindex( (int) $id );
				}
			}
			foreach ( $state['css'] as $id ) {
				if ( self::css_allowed( (int) $id ) ) {
					self::set_status( (int) $id, 'draft', 'publish' );
				} else {
					$denied[] = (int) $id;
				}
			}
		} finally {
			self::$syncing = false;
		}
		self::write(
			static function ( array $fresh ) use ( $denied ): array {
				$fresh['published'] = true;
				if ( $denied ) {
					$fresh['css']    = array_values( array_diff( $fresh['css'], $denied ) );
					$fresh['denied'] = array_values( array_unique( array_merge( $fresh['denied'], $denied ) ) );
				}
				return $fresh;
			},
			false
		);
	}

	/* ================================================================ Ganchos */

	/**
	 * `get_post_status`: cubre las peticiones simultáneas en los dos sentidos. Con Element Pack cargado, `draft` para
	 * toda copia anotada; con el objetivo, `publish` para las copias publicables. Sólo `draft`/`publish` de entrada.
	 *
	 * @param string       $status Estado leído.
	 * @param \WP_Post|int $post   Entrada.
	 */
	public static function filter_status( $status, $post = null ) {
		if ( 'draft' !== $status && 'publish' !== $status ) {
			return $status;
		}
		$id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : ( is_numeric( $post ) ? (int) $post : 0 );
		if ( $id <= 0 || self::switched() ) {
			return $status;
		}
		$sets = self::sets();
		if ( ! $sets || ( ! isset( $sets['tb'][ $id ] ) && ! isset( $sets['hold'][ $id ] ) ) ) {
			return $status;
		}
		if ( self::ep_loaded() ) {
			return 'draft';
		}
		if ( isset( $sets['tb'][ $id ] ) && self::target() ) {
			return 'publish';
		}
		return $status;
	}

	/**
	 * `wp_insert_post_data`: con Element Pack cargado, una copia anotada no puede publicarse (a mano, desde el editor,
	 * por REST o por WP-CLI): se guarda en borrador y queda un aviso para quien lo intentó.
	 */
	public static function guard( $data, $postarr = array() ) {
		$id = is_array( $postarr ) && isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $id <= 0 || ! is_array( $data ) || ! in_array( $data['post_status'] ?? '', array( 'publish', 'future', 'private' ), true ) || self::switched() ) {
			return $data;
		}
		$sets = self::sets();
		if ( ! $sets || ( ! isset( $sets['tb'][ $id ] ) && ! isset( $sets['hold'][ $id ] ) && ! isset( $sets['css'][ $id ] ) ) || ! self::ep_loaded() ) {
			return $data;
		}
		$data['post_status'] = 'draft';
		$user                = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user > 0 && function_exists( 'set_transient' ) ) {
			set_transient( self::NOTICE . $user, $id, 10 * MINUTE_IN_SECONDS );
		}
		return $data;
	}

	/**
	 * Cambios hechos a mano (no por `sync()`): publicar una copia la vuelve publicable (`tb`) y marca `published`;
	 * despublicarla o llevarla a la papelera la saca de las que se publican solas (`hold`), así una copia retirada a
	 * propósito no vuelve sola.
	 */
	public static function on_transition( $new_status, $old_status, $post ): void {
		if ( self::$syncing || $new_status === $old_status || ! is_object( $post ) || empty( $post->ID ) || self::switched() ) {
			return;
		}
		$id   = (int) $post->ID;
		$sets = self::sets();
		if ( ! $sets || ( ! isset( $sets['tb'][ $id ] ) && ! isset( $sets['hold'][ $id ] ) ) ) {
			return;
		}
		if ( 'publish' === $new_status ) {
			self::write(
				static function ( array $state ) use ( $id ): array {
					$state['hold'] = array_values( array_diff( $state['hold'], array( $id ) ) );
					if ( ! in_array( $id, $state['tb'], true ) ) {
						$state['tb'][] = $id;
						sort( $state['tb'] );
					}
					$state['published'] = true;
					return $state;
				}
			);
		} elseif ( 'publish' === $old_status && isset( $sets['tb'][ $id ] ) ) {
			self::write(
				static function ( array $state ) use ( $id ): array {
					$state['tb'] = array_values( array_diff( $state['tb'], array( $id ) ) );
					if ( ! in_array( $id, $state['hold'], true ) ) {
						$state['hold'][] = $id;
						sort( $state['hold'] );
					}
					return $state;
				}
			);
		}
	}

	/** Una copia borrada del todo ya no se sigue. */
	public static function on_deleted( $post_id ): void {
		$id   = (int) $post_id;
		$sets = self::sets();
		if ( $sets && ( isset( $sets['tb'][ $id ] ) || isset( $sets['hold'][ $id ] ) || isset( $sets['css'][ $id ] ) ) ) {
			self::untrack( $id );
		}
	}

	/** Aviso, una vez, a quien intentó publicar una copia con Element Pack activo. */
	public static function notice(): void {
		$user = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user <= 0 || ! function_exists( 'get_transient' ) ) {
			return;
		}
		$id = (int) get_transient( self::NOTICE . $user );
		if ( $id <= 0 ) {
			return;
		}
		delete_transient( self::NOTICE . $user );
		$title = function_exists( 'get_the_title' ) ? (string) get_the_title( $id ) : '';
		/* translators: %s: título de la plantilla. */
		$text = sprintf( __( '«%s» sigue en borrador: es una copia de una plantilla de Element Pack y se publicará sola cuando Element Pack deje de estar activo. Publicarla ahora mostraría dos cabeceras o dos pies.', 'digitalisimo-migration' ), '' !== $title ? $title : '#' . $id );
		echo '<div class="notice notice-warning"><p>' . esc_html( $text ) . '</p></div>';
	}

	/* ================================================================ Estado */

	/**
	 * Estado del sitio en curso (normalizado) o null sin copias.
	 *
	 * @return array{v:int, allow:bool, published:bool, tb:int[], css:int[], hold:int[], denied:int[]}|null
	 */
	public static function state(): ?array {
		$blog = self::blog();
		if ( ! array_key_exists( $blog, self::$memo ) ) {
			self::$memo[ $blog ] = self::normalize( function_exists( 'get_option' ) ? get_option( self::OPTION, null ) : null );
			unset( self::$sets[ $blog ] );
		}
		return self::$memo[ $blog ];
	}

	/** CSS global que no se publicó porque quien lanzó la tarea ya no puede (lo informa H). */
	public static function denied(): array {
		$state = self::state();
		return null === $state ? array() : $state['denied'];
	}

	/** Clase en la que está anotado un ID (`tb`, `css`, `hold`) o ''. */
	public static function tracked( int $id ): string {
		$sets = self::sets();
		foreach ( self::KINDS as $kind ) {
			if ( isset( $sets[ $kind ][ $id ] ) ) {
				return $kind;
			}
		}
		return '';
	}

	/** IDs por clase, como conjuntos (para el filtro, que se llama muchas veces). */
	private static function sets(): array {
		$blog = self::blog();
		if ( ! isset( self::$sets[ $blog ] ) ) {
			$state = self::state();
			$sets  = array();
			if ( null !== $state ) {
				foreach ( self::KINDS as $kind ) {
					$sets[ $kind ] = array_fill_keys( $state[ $kind ], true );
				}
			}
			self::$sets[ $blog ] = $sets;
		}
		return self::$sets[ $blog ];
	}

	private static function normalize( $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$state = array(
			'v'         => 1,
			'allow'     => ! empty( $raw['allow'] ),
			'published' => ! empty( $raw['published'] ),
			'tb'        => array(),
			'css'       => array(),
			'hold'      => array(),
			'denied'    => array(),
		);
		$seen = array();
		foreach ( array( 'tb', 'css', 'hold', 'denied' ) as $kind ) {
			foreach ( (array) ( $raw[ $kind ] ?? array() ) as $id ) {
				$id = is_numeric( $id ) ? (int) $id : 0;
				if ( $id <= 0 || ( 'denied' !== $kind && isset( $seen[ $id ] ) ) ) {
					continue;
				}
				if ( 'denied' !== $kind ) {
					$seen[ $id ] = true;
				}
				$state[ $kind ][] = $id;
			}
		}
		return $state;
	}

	private static function count( array $state ): int {
		return count( $state['tb'] ) + count( $state['css'] ) + count( $state['hold'] );
	}

	/**
	 * Cambia el estado guardado bajo el cerrojo, releyendo el valor de la base de datos. Sin copias, la opción se
	 * borra. `$wait`: la tarea espera el cerrojo (hasta 2 s); `sync()` no (otra petición ya lo está escribiendo).
	 */
	private static function write( callable $change, bool $wait = true ): bool {
		$token = self::lock( $wait );
		if ( '' === $token ) {
			return false;
		}
		try {
			$fresh = self::normalize( self::read_fresh() );
			$base  = null === $fresh ? self::normalize( array() ) : $fresh;
			$next  = self::normalize( $change( $base ) );
			if ( null === $next || ( ! $next['tb'] && ! $next['css'] && ! $next['hold'] ) ) {
				delete_option( self::OPTION );
			} elseif ( $next !== $fresh ) {
				update_option( self::OPTION, $next, true );
			}
		} finally {
			self::unlock( $token );
			self::forget();
		}
		return true;
	}

	/** Valor guardado, sin la memoria de opciones de la petición. */
	private static function read_fresh() {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( self::OPTION, 'options' );
		}
		return get_option( self::OPTION, null );
	}

	/** Olvida lo leído en la petición. */
	public static function forget(): void {
		self::$memo = array();
		self::$sets = array();
	}

	/* ================================================================ Cerrojo */

	/** `INSERT IGNORE` con token; se roba si lleva más de LOCK_TTL segundos. Devuelve el valor guardado o ''. */
	private static function lock( bool $wait ): string {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return 'nolock';
		}
		$token = function_exists( 'wp_generate_password' ) ? wp_generate_password( 20, false ) : bin2hex( random_bytes( 10 ) );
		$tries = $wait ? 40 : 1;
		for ( $i = 0; $i < $tries; $i++ ) {
			$value = time() . '|' . $token;
			$taken = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )", self::LOCK, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( 1 === $taken ) {
				return $value;
			}
			$held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( '' !== $held && time() - (int) $held >= self::LOCK_TTL ) {
				if ( 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, self::LOCK, $held ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					return $value;
				}
			}
			if ( $i + 1 < $tries ) {
				usleep( 50000 );
			}
		}
		return '';
	}

	/** Suelta el cerrojo propio; nunca el de otra petición. */
	private static function unlock( string $value ): void {
		global $wpdb;
		if ( 'nolock' === $value || ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/* ================================================================ Entradas */

	/**
	 * `UPDATE … WHERE ID = %d AND post_status = %s`; si la fila cambió, `clean_post_cache()` y
	 * `wp_transition_post_status()`, como `wp_publish_post()`, sin `save_post`.
	 */
	private static function set_status( int $id, string $from, string $to ): bool {
		global $wpdb;
		if ( $id <= 0 || ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return false;
		}
		$changed = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_status = %s WHERE ID = %d AND post_status = %s", $to, $id, $from ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( 1 !== $changed ) {
			return false;
		}
		if ( function_exists( 'clean_post_cache' ) ) {
			clean_post_cache( $id );
		}
		$post = get_post( $id );
		if ( $post && function_exists( 'wp_transition_post_status' ) ) {
			wp_transition_post_status( $to, $from, $post );
		}
		return true;
	}

	/** Prioridad manual de F7a al publicar (el índice no se mantuvo si el PRO de Elements no estaba cargado). */
	private static function reindex( int $id ): void {
		$class = self::PRIORITY;
		if ( class_exists( $class ) && method_exists( $class, 'reindex' ) ) {
			try {
				$class::reindex( $id );
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}
	}

	/** El CSS global sólo se publica si H lo revalida (quien lanzó la tarea y el código guardado, V.7). */
	private static function css_allowed( int $id ): bool {
		$class = self::SNIPPETS;
		if ( ! class_exists( $class ) || ! method_exists( $class, 'revalidate' ) ) {
			return false;
		}
		try {
			return (bool) $class::revalidate( $id );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/* ================================================================ Entorno */

	/** ¿Element Pack (Pro o Lite) cargado en esta petición? */
	public static function ep_loaded(): bool {
		if ( null !== self::$env ) {
			return ! empty( self::$env['ep'] );
		}
		$translator = self::TRANSLATOR;
		if ( class_exists( $translator, false ) && method_exists( $translator, 'element_pack_active' ) ) {
			return (bool) $translator::element_pack_active();
		}
		return defined( 'BDTEP_VER' ) || class_exists( '\\ElementPack\\Element_Pack_Loader', false );
	}

	/** ¿Elementor Pro o PRO Elements activos (el PRO de Elements no carga)? */
	public static function conflict(): bool {
		if ( null !== self::$env ) {
			return ! empty( self::$env['conflict'] );
		}
		return function_exists( 'digitalisimo_elements_conflicting_plugin_active' ) && (bool) digitalisimo_elements_conflicting_plugin_active();
	}

	/** ¿Theme Builder disponible? */
	public static function builder(): bool {
		if ( null !== self::$env ) {
			return ! empty( self::$env['builder'] );
		}
		return class_exists( self::THEME_BUILDER );
	}

	/** En una red, una petición que cambió de sitio no decide por el otro sitio: cada sitio en sus peticiones. */
	private static function switched(): bool {
		if ( null !== self::$env && isset( self::$env['switched'] ) ) {
			return ! empty( self::$env['switched'] );
		}
		return function_exists( 'ms_is_switched' ) && ms_is_switched();
	}

	/** Conversión pura (editor, análisis): nada se escribe. */
	private static function pure(): bool {
		$context = '\\Digitalisimo\\Elements\\Legacy\\Context';
		return class_exists( $context, false ) && $context::pure();
	}

	private static function blog(): int {
		return function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
	}

	/**
	 * Entorno simulado para las pruebas (`ep`, `conflict`, `builder`, `switched`); null vuelve al real.
	 *
	 * @internal Sólo para pruebas.
	 */
	public static function set_environment( ?array $env ): void {
		self::$env = $env;
		self::forget();
	}

	/**
	 * Olvida el arranque y los ganchos (una «petición» nueva en las pruebas).
	 *
	 * @internal Sólo para pruebas.
	 */
	public static function reset(): void {
		self::$initialized = false;
		self::$hooked      = false;
		self::$syncing     = false;
		self::forget();
	}
}
