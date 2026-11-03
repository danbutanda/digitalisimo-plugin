<?php
namespace Digitalisimo\Elements\Legacy\Platform;

use Digitalisimo\Elements\Legacy\Report;

defined( 'ABSPATH' ) || exit;

/**
 * Theme Builder de Element Pack → Theme Builder de DIGITALÍSIMO Elements (F8, frente G; §3.D.1, V.5, contrato C5).
 *
 * Cada `bdt-template-builder` se copia a un `elementor_library` con su tipo de plantilla (`header`, `footer`,
 * `single-post`, `single-page`, `single`, `archive`, `search-results`, `error-404`, `product`, `product-archive`), su
 * `_elementor_data` **tal cual** (lo convierte después la etapa `platform_convert`), sus ajustes de página sin los de
 * Element Pack (y con la vista previa si tenía una entrada de muestra) y `_elementor_conditions` traducido por
 * `Tb_Conditions`. El original nunca se toca: reactivar Element Pack lo vuelve a pintar.
 *
 * - **`prepare()`** (etapa `platform_prepare`, Element Pack puede estar cargado): crea las copias **en borrador** con
 *   `Documents::create()` (`wp_slash()` y huella releída), las anota en el registro y en el publicador (`hold`) y, al
 *   final, decide cuáles son publicables, les da la **prioridad manual** 1..n de F7a en el orden de Element Pack
 *   (cabecera y pie: ID ascendente; el resto: ID descendente, `<cpt>|single` antes que `post|single`), las pasa a `tb`
 *   en el publicador y las mete en la caché de condiciones (que incluye borradores).
 * - **`before_deactivate()`** (etapa `deactivate`): las cinco condiciones de V.5.3. La tarea no desactiva el grupo
 *   `element-pack` si una cabecera o un pie visibles hoy no tienen copia publicable, y sólo permite publicar
 *   (`Publisher::allow( true )`) si se cumplen todas.
 * - **`finish()`** (etapa `platform`, sin Element Pack): una sola regeneración de la caché, prioridades reindexadas e
 *   informe final (publicadas, plantillas que comparten condición).
 * - **`revert()`**: retira cada copia que nadie cambió desde la migración (huella del registro, recalculada tras
 *   `platform_convert`); lo editado se omite y se informa (V.8).
 *
 * La publicación la hace `Publisher` en la primera petición sin Element Pack cargado.
 */
final class Theme_Builder {
	const KIND             = 'tb';
	const CPT              = 'bdt-template-builder';
	const TARGET_CPT       = 'elementor_library';
	const META_TYPE        = '_bdthemes_builder_template_type';
	const META_EDIT_WITH   = '_bdthemes_builder_edit_with';
	const OPTION_PREFIX    = '_bdthemes_builder_';
	const SAMPLE_SETTING   = 'bdt_builder_sample_post_id';
	const PRIORITY_SETTING = 'digitalisimo_tb_priority';
	const CACHE_OPTION     = 'elementor_pro_theme_builder_conditions';
	const BATCH            = 10;
	const MAX_PRIORITY     = 99;
	const MAX_SOURCES      = 500;

	/** Ubicación de cada tipo de plantilla de destino (`location` de cada documento). */
	const LOCATIONS = array(
		'header'          => 'header',
		'footer'          => 'footer',
		'single'          => 'single',
		'single-post'     => 'single',
		'single-page'     => 'single',
		'error-404'       => 'single',
		'product'         => 'single',
		'archive'         => 'archive',
		'search-results'  => 'archive',
		'product-archive' => 'archive',
	);

	/** Tipos de destino con vista previa de una entrada (`preview_type = single/<tipo>`). */
	const PREVIEWABLE = array( 'single', 'single-post', 'single-page', 'product' );

	/** Motivos que dejan la copia sin condiciones (no hay traducción segura o no hay tipo). */
	const NO_CONDITIONS = array( 'tb.review.unknown_type', 'tb.review.woo_page', 'tb.review.conditions', 'tb.review.scope', 'tb.review.no_include' );

	/** @var array<int, array> memoria de la petición por sitio (opciones de Element Pack). */
	private static $memo = array();
	/** @var array|null entorno simulado (pruebas): ep_version, builder, trash_days. */
	private static $env = null;

	/* ================================================================ Contrato C5 */

	/**
	 * Análisis previo, sin escribir nada: por plantilla, qué se hará y qué cambia.
	 *
	 * @return array<int, array{0:string, 1:array}> `[ código, detalle ]`; el detalle lleva `id` (plantilla de origen).
	 */
	public static function analyze(): array {
		$out = array();
		foreach ( self::sources( 0, self::MAX_SOURCES ) as $id ) {
			$info = self::inspect( $id );
			if ( null === $info ) {
				continue;
			}
			$detail = self::detail( $info );
			$out[]  = array( '' === $info['reason'] ? 'tb.created' : $info['reason'], $detail );
			foreach ( $info['items'] as $item ) {
				$out[] = array( $item['code'], self::item_detail( $info, $item ) );
			}
			if ( $info['visible'] && '' !== $info['reason'] ) {
				$out[] = array( 'tb.blocked_retire', $detail );
			} elseif ( ! $info['header'] && $info['active'] && 'publish' === $info['status'] && '' !== $info['reason'] && 'tb.inactive' !== $info['reason'] ) {
				$out[] = array( 'tb.review.type_lost', $detail );
			}
		}
		foreach ( self::site_notes() as $note ) {
			$out[] = $note;
		}
		return $out;
	}

	/**
	 * Etapa `platform_prepare`: copias en borrador (fase `copy`) y plan de publicación y prioridades (fase `plan`).
	 * Procesa al menos una plantilla por llamada; devuelve false si queda trabajo (avanzó) y true al terminar.
	 *
	 * @param array $cursor   `{ phase: copy|plan|done, after: int }` (≤ 4 KB).
	 * @param float $deadline `microtime( true )` límite.
	 */
	public static function prepare( array &$cursor, float $deadline ): bool {
		$cursor += array( 'phase' => 'copy', 'after' => 0 );
		$did     = false;
		if ( 'copy' === $cursor['phase'] ) {
			while ( true ) {
				$ids = self::sources( (int) $cursor['after'], self::BATCH );
				if ( ! $ids ) {
					$cursor['phase'] = 'plan';
					break;
				}
				foreach ( $ids as $id ) {
					if ( $did && microtime( true ) >= $deadline ) {
						return false;
					}
					self::copy( (int) $id );
					$cursor['after'] = (int) $id;
					$did             = true;
				}
			}
		}
		if ( 'plan' === $cursor['phase'] ) {
			if ( $did && microtime( true ) >= $deadline ) {
				return false;
			}
			self::plan();
			$cursor['phase'] = 'done';
		}
		return true;
	}

	/**
	 * Etapa `deactivate`, justo antes de desactivar el grupo `element-pack` (V.5.3). Informa sus códigos y devuelve:
	 *
	 * - `retire`: ¿puede desactivarse el grupo `element-pack`? No si falla (2) la preparación, (3) una cabecera o un pie
	 *   visibles hoy sin copia publicable, (4) un plugin en conflicto que siga activo o (5) el Theme Builder.
	 * - `allow`: ¿se llama a `Publisher::allow( true )`? Sólo con las cinco condiciones (la 1: algún miembro del grupo
	 *   estaba activo al empezar; si no, todo queda en borrador y se informa `tb.plugin_inactive`).
	 *
	 * @param array $facts `ep_active` (algún miembro del grupo activo en `prepare`, en cualquier ámbito), `prepared`
	 *                     (`platform_prepare` terminó), `conflict_after` (tras esta etapa queda Elementor Pro o PRO
	 *                     Elements activos), `builder` (el Theme Builder de Elements estará cargado). Lo que falte se
	 *                     deduce de la petición; `prepared` falta = falso (fallar cerrado).
	 * @return array{retire:bool, allow:bool, codes:array, blocked:int[]}
	 */
	public static function before_deactivate( array $facts = array() ): array {
		$facts += array(
			'ep_active'      => Publisher::ep_loaded(),
			'prepared'       => false,
			'conflict_after' => Publisher::conflict(),
			'builder'        => self::builder_available(),
		);
		$codes   = array();
		$blocked = array();
		$copies  = Registry::all( self::KIND );
		if ( ! $facts['ep_active'] ) {
			if ( $copies ) {
				$codes[] = array( 'tb.plugin_inactive', array( 'count' => count( $copies ) ) );
			}
			self::report_codes( $codes );
			return array( 'retire' => true, 'allow' => false, 'codes' => $codes, 'blocked' => $blocked );
		}
		$retire = true;
		if ( ! $facts['prepared'] ) {
			$codes[] = array( 'tb.unprepared', array() );
			$retire  = false;
		}
		foreach ( self::visible_sources() as $id => $info ) {
			$row = $copies[ (string) $id ] ?? null;
			if ( ! self::publishable_row( $row ) ) {
				$codes[]   = array( 'tb.blocked_retire', self::detail( $info ) );
				$blocked[] = $id;
				$retire    = false;
				continue;
			}
			if ( '' !== (string) ( $row['extra']['src_hash'] ?? '' ) && $info['src_hash'] !== $row['extra']['src_hash'] ) {
				$codes[] = array( 'tb.stale', self::detail( $info ) + array( 'target' => (string) $row['dest'] ) );
			}
		}
		if ( $facts['conflict_after'] ) {
			$codes[] = array( 'tb.conflict', array() );
			$retire  = false;
		}
		if ( ! $facts['builder'] ) {
			$codes[] = array( 'tb.no_builder', array() );
			$retire  = false;
		}
		self::report_codes( $codes );
		return array( 'retire' => $retire, 'allow' => $retire, 'codes' => $codes, 'blocked' => $blocked );
	}

	/**
	 * Etapa `platform` (Element Pack ya no está cargado y el contenido de las copias ya se convirtió): caché de
	 * condiciones regenerada una sola vez, prioridades reindexadas e informe final.
	 */
	public static function finish( array &$cursor, float $deadline ): bool {
		$rows = Registry::all( self::KIND );
		$ids  = array();
		foreach ( $rows as $row ) {
			if ( (int) $row['dest'] > 0 && self::copy_exists( (int) $row['dest'] ) ) {
				$ids[] = (int) $row['dest'];
			}
		}
		if ( ! $ids ) {
			return true;
		}
		self::ensure_cache( $ids );
		$manager = self::conditions_manager();
		if ( null !== $manager && method_exists( $manager, 'get_cache' ) ) {
			try {
				$manager->get_cache()->regenerate();
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}
		foreach ( $rows as $row ) {
			$dest = (int) $row['dest'];
			if ( $dest <= 0 || empty( $row['extra']['publishable'] ) || ! self::copy_exists( $dest ) ) {
				continue;
			}
			self::reindex( $dest );
			if ( 'publish' === get_post_status( $dest ) ) {
				self::report( $dest, 'tb.published', array( 'type' => (string) ( $row['extra']['pro'] ?? '' ), 'count' => (int) ( $row['extra']['priority'] ?? 0 ) ) );
			}
			$shared = self::shared_conditions( $manager, $dest, (string) ( $row['extra']['location'] ?? '' ) );
			if ( $shared ) {
				self::report( $dest, 'tb.shared', array( 'count' => $shared ) );
			}
		}
		return true;
	}

	/**
	 * `--revert`: retira cada copia que sigue como la dejó la migración (a la papelera si `EMPTY_TRASH_DAYS` > 0; si
	 * no, a borrador), la saca del publicador y borra su fila. Lo editado después se omite y se informa, salvo con
	 * `$force`. Nunca toca los originales.
	 *
	 * @return array<int, array{0:string, 1:array}>
	 */
	public static function revert( bool $force = false ): array {
		$out = array();
		foreach ( Registry::all( self::KIND ) as $source => $row ) {
			$dest = (int) $row['dest'];
			if ( $dest <= 0 || ! self::copy_exists( $dest ) ) {
				if ( $dest > 0 ) {
					Publisher::untrack( $dest );
				}
				Registry::forget( self::KIND, (string) $source );
				continue;
			}
			if ( ! $force && ! Documents::unchanged( $dest, (string) $row['hash'] ) ) {
				$out[] = array( 'core.revert_skipped', array( 'type' => self::TARGET_CPT, 'id' => $dest, 'name' => self::title( $dest ) ) );
				continue;
			}
			Publisher::untrack( $dest );
			$days  = null !== self::$env && isset( self::$env['trash_days'] ) ? (int) self::$env['trash_days'] : ( defined( 'EMPTY_TRASH_DAYS' ) ? (int) EMPTY_TRASH_DAYS : 30 );
			$trash = $days > 0; // Con 0, wp_trash_post() borraría para siempre: a borrador.
			if ( $trash && function_exists( 'wp_trash_post' ) ) {
				wp_trash_post( $dest );
			} else {
				wp_update_post( array( 'ID' => $dest, 'post_status' => 'draft' ) );
			}
			self::reindex( $dest );
			Registry::forget( self::KIND, (string) $source );
			$out[] = array( 'tb.reverted', array( 'id' => $dest, 'name' => self::title( $dest ), 'note' => $trash ? 'a la papelera' : 'a borrador' ) );
		}
		return $out;
	}

	/* ================================================================ Copia */

	/** Una plantilla de origen: crea su copia en borrador si aún no existe (reanudar no duplica). */
	private static function copy( int $id ): void {
		$row = Registry::get( self::KIND, (string) $id );
		if ( null !== $row && (int) $row['dest'] > 0 && self::copy_exists( (int) $row['dest'] ) ) {
			return;
		}
		$info = self::inspect( $id );
		if ( null === $info ) {
			return;
		}
		$dest   = 0;
		$failed = false;
		if ( null !== $row && 'pending' === $row['state'] ) {
			$dest = self::orphan( $id ); // Creada por una petición que murió antes de registrarla.
		}
		if ( $dest <= 0 ) {
			Registry::put( self::KIND, (string) $id, array( 'dest' => 0, 'hash' => '', 'state' => 'pending', 'extra' => array( 'type' => $info['raw_type'] ) ) );
			$dest = Documents::create( $info['pro'], self::post_fields( $info ), self::meta( $info ), array( 'part' => self::KIND, 'source' => (string) $id ) );
			if ( $dest <= 0 && Documents::$last_failed > 0 ) {
				$dest   = (int) Documents::$last_failed;
				$failed = true;
			}
		}
		if ( $dest > 0 && ! $failed && '' !== $info['src_hash'] && Documents::hash( $dest ) !== $info['src_hash'] ) {
			$failed = true;
		}
		$extra = self::extra( $info );
		if ( $dest <= 0 ) {
			Registry::put( self::KIND, (string) $id, array( 'dest' => 0, 'hash' => '', 'state' => 'failed', 'extra' => array( 'reason' => 'tb.copy_failed' ) + $extra ) );
			self::report_site( 'tb.copy_failed', self::detail( $info ) );
			return;
		}
		if ( $failed ) {
			$extra['reason'] = 'tb.copy_failed';
		}
		Registry::put( self::KIND, (string) $id, array( 'dest' => $dest, 'hash' => Documents::hash( $dest ), 'state' => $failed ? 'failed' : 'draft', 'extra' => $extra ) );
		Publisher::track( 'hold', $dest );
		self::report( $dest, 'tb.created', array( 'type' => $info['raw_type'], 'target' => $info['pro'], 'id' => $id, 'name' => $info['title'] ) );
		foreach ( $info['items'] as $item ) {
			self::report( $dest, $item['code'], self::item_detail( $info, $item ) );
		}
		if ( '' !== $extra['reason'] ) {
			self::report( $dest, $extra['reason'], self::reason_detail( $info, $extra['reason'] ) );
		}
	}

	/** Campos de la copia. Con el editor de bloques se conserva el contenido para no perderlo. */
	private static function post_fields( array $info ): array {
		$post = get_post( $info['id'] );
		return array(
			'post_title'   => (string) $info['title'],
			'post_type'    => self::TARGET_CPT,
			'post_status'  => 'draft',
			'post_author'  => $post ? (int) $post->post_author : 0,
			'post_content' => $info['gutenberg'] && $post ? (string) $post->post_content : '',
		);
	}

	/** Metas de la copia (Documents::create() les aplica `wp_slash()` y releerá la huella). */
	private static function meta( array $info ): array {
		$meta = array();
		if ( is_string( $info['data'] ) ) {
			$meta['_elementor_data'] = $info['data'];
		}
		$settings = self::page_settings( $info );
		if ( $settings ) {
			$meta['_elementor_page_settings'] = $settings;
		}
		if ( $info['conditions'] ) {
			$meta['_elementor_conditions'] = $info['conditions'];
		}
		return $meta;
	}

	/** Ajustes de página sin los del Theme Builder de Element Pack, con la vista previa de su entrada de muestra. */
	private static function page_settings( array $info ): array {
		$settings = get_post_meta( $info['id'], '_elementor_page_settings', true );
		$settings = is_array( $settings ) ? $settings : array();
		$sample   = isset( $settings[ self::SAMPLE_SETTING ] ) ? (int) $settings[ self::SAMPLE_SETTING ] : 0;
		foreach ( array_keys( $settings ) as $key ) {
			if ( 0 === strpos( (string) $key, 'bdt_builder_' ) || self::PRIORITY_SETTING === $key ) {
				unset( $settings[ $key ] );
			}
		}
		if ( $sample > 0 && in_array( $info['pro'], self::PREVIEWABLE, true ) ) {
			$post = get_post( $sample );
			if ( $post && 'trash' !== $post->post_status ) {
				$settings['preview_type'] = 'single/' . $post->post_type;
				$settings['preview_id']   = $sample;
			}
		}
		return $settings;
	}

	/** Lo que el registro guarda de cada copia (pequeño: las condiciones viven en la copia). */
	private static function extra( array $info ): array {
		return array(
			'type'        => $info['raw_type'],
			'canonical'   => $info['type'],
			'pro'         => $info['pro'],
			'location'    => $info['location'],
			'rank'        => $info['rank'],
			'active'      => $info['active'],
			'status'      => $info['status'],
			'visible'     => $info['visible'],
			'header'      => $info['header'],
			'reason'      => $info['reason'],
			'full'        => $info['full'],
			'fallback'    => $info['fallback'],
			'src_hash'    => $info['src_hash'],
			'publishable' => false,
			'priority'    => 0,
		);
	}

	/** Copia de esta plantilla creada por una petición que murió antes de registrarla, o 0. */
	private static function orphan( int $source ): int {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return 0;
		}
		$source = (string) $source;
		$like   = '%' . $wpdb->esc_like( 's:6:"source";s:' . strlen( $source ) . ':"' . $source . '";' ) . '%';
		$ids    = (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 5", Documents::ORIGIN_META, $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $ids as $id ) {
			$origin = Documents::origin( (int) $id );
			if ( null !== $origin && self::KIND === ( $origin['part'] ?? '' ) && $source === (string) ( $origin['source'] ?? '' ) && self::copy_exists( (int) $id ) ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/* ================================================================ Plan */

	/**
	 * Decide qué copias son publicables (la ganadora de `page|404`, ≤ 99 por ubicación), les da la prioridad manual en
	 * el orden de Element Pack, las pasa a `tb` en el publicador y mete todas en la caché de condiciones.
	 */
	private static function plan(): void {
		$rows       = Registry::all( self::KIND );
		$candidates = array();
		$winner_404 = null;
		foreach ( $rows as $source => $row ) {
			$extra = $row['extra'];
			if ( 'draft' !== $row['state'] || (int) $row['dest'] <= 0 || '' !== (string) ( $extra['reason'] ?? 'x' ) || ! self::copy_exists( (int) $row['dest'] ) ) {
				continue;
			}
			if ( 'page|404' === ( $extra['canonical'] ?? '' ) ) {
				$winner_404 = $winner_404 ?? self::winner( 'page|404' );
				if ( (int) $source !== $winner_404 ) {
					self::set_reason( (string) $source, 'tb.duplicate' );
					continue;
				}
			}
			$candidates[ (string) ( $extra['location'] ?? '' ) ][ (int) $source ] = $row;
		}
		$published = 0;
		foreach ( $candidates as $location => $group ) {
			$order = self::order( $location, $group );
			$n     = 0;
			foreach ( $order as $source ) {
				$row  = $group[ $source ];
				$dest = (int) $row['dest'];
				if ( ++$n > self::MAX_PRIORITY ) {
					self::set_reason( (string) $source, 'tb.priority_overflow' );
					continue;
				}
				self::write_priority( $dest, $n );
				Publisher::track( 'tb', $dest );
				if ( 'tb' !== Publisher::tracked( $dest ) ) {
					self::set_reason( (string) $source, 'tb.too_many' );
					continue;
				}
				$fresh                         = Registry::get( self::KIND, (string) $source ) ?? $row;
				$fresh['extra']['publishable'] = true;
				$fresh['extra']['priority']    = $n;
				Registry::put( self::KIND, (string) $source, $fresh );
				++$published;
			}
		}
		if ( $published ) {
			self::report_site( 'tb.priority', array( 'count' => $published ) );
		}
		// Respaldo de Element Pack sin condiciones (`get_template_id()`): la copia sólo conserva sus condiciones.
		foreach ( self::fallback_losses() as $dest ) {
			self::report( $dest, 'tb.fallback_lost', array() );
		}
		$ids = array();
		foreach ( Registry::all( self::KIND ) as $row ) {
			if ( (int) $row['dest'] > 0 ) {
				$ids[] = (int) $row['dest'];
			}
		}
		self::ensure_cache( $ids );
		foreach ( self::site_notes() as $note ) {
			self::report_site( $note[0], $note[1] );
		}
	}

	/**
	 * Orden de Element Pack dentro de una ubicación: cabecera y pie por ID ascendente (gana la más antigua,
	 * `activator.php:147-170`); el resto por rango (en `single`, `<cpt>|single` antes que `post|single`; en `archive`,
	 * la búsqueda, después `<cpt>|archive` y después `post|archive`) y por ID descendente (gana la más nueva,
	 * `builder-template-helper.php:344-367`).
	 *
	 * @param array<int, array> $group origen => fila.
	 * @return int[] orígenes en orden.
	 */
	public static function order( string $location, array $group ): array {
		$ids = array_keys( $group );
		usort(
			$ids,
			static function ( $a, $b ) use ( $location, $group ): int {
				if ( 'header' === $location || 'footer' === $location ) {
					return (int) $a <=> (int) $b;
				}
				$ra = (int) ( $group[ $a ]['extra']['rank'] ?? 1 );
				$rb = (int) ( $group[ $b ]['extra']['rank'] ?? 1 );
				return $ra !== $rb ? $ra <=> $rb : (int) $b <=> (int) $a;
			}
		);
		return array_map( 'intval', $ids );
	}

	private static function set_reason( string $source, string $reason ): void {
		$row = Registry::get( self::KIND, $source );
		if ( null === $row ) {
			return;
		}
		$row['extra']['reason']      = $reason;
		$row['extra']['publishable'] = false;
		Registry::put( self::KIND, $source, $row );
		if ( (int) $row['dest'] > 0 ) {
			Publisher::track( 'hold', (int) $row['dest'] );
			self::report( (int) $row['dest'], $reason, array() );
		}
	}

	/** Prioridad manual en los ajustes de la copia (con `wp_slash()`: los ajustes pueden llevar barras invertidas). */
	private static function write_priority( int $dest, int $priority ): void {
		$settings = get_post_meta( $dest, '_elementor_page_settings', true );
		$settings = is_array( $settings ) ? $settings : array();
		if ( isset( $settings[ self::PRIORITY_SETTING ] ) && (int) $settings[ self::PRIORITY_SETTING ] === $priority ) {
			return;
		}
		$settings[ self::PRIORITY_SETTING ] = $priority;
		update_post_meta( $dest, '_elementor_page_settings', wp_slash( $settings ) );
		self::reindex( $dest );
	}

	/** Copias que Element Pack usaba también de respaldo para todo su tipo y cuyas condiciones no lo cubren. */
	private static function fallback_losses(): array {
		$out   = array();
		$rows  = Registry::all( self::KIND );
		$types = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['extra']['fallback'] ) && ! empty( $row['extra']['publishable'] ) ) {
				$types[ (string) $row['extra']['type'] ] = true;
			}
		}
		foreach ( array_keys( $types ) as $type ) {
			$winner = self::winner( $type );
			$row    = $rows[ (string) $winner ] ?? null;
			if ( null !== $row && ! empty( $row['extra']['publishable'] ) && empty( $row['extra']['full'] ) ) {
				$out[] = (int) $row['dest'];
			}
		}
		return $out;
	}

	/* ================================================================ Origen */

	/**
	 * Todo lo que importa de una plantilla de origen, sin escribir nada.
	 *
	 * @return array|null null si no es una plantilla del Theme Builder de Element Pack.
	 */
	public static function inspect( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post || self::CPT !== $post->post_type ) {
			return null;
		}
		$raw_type  = trim( (string) get_post_meta( $id, self::META_TYPE, true ) );
		$type      = Tb_Conditions::canonical( $raw_type );
		$kind      = Tb_Conditions::kind( $type );
		$gutenberg = 'gutenberg' === (string) get_post_meta( $id, self::META_EDIT_WITH, true );
		$data      = get_post_meta( $id, '_elementor_data', true );
		$empty     = ! is_string( $data ) || in_array( trim( $data ), array( '', '[]' ), true );
		$blank     = $gutenberg ? '' === trim( (string) $post->post_content ) : $empty;
		$version   = self::ep_version();
		$rows      = Tb_Conditions::rows( $id, $raw_type, $version );
		$tr        = null !== $kind ? Tb_Conditions::translate( $type, $rows, $version ) : array( 'conditions' => array(), 'items' => array(), 'fail' => 'type', 'effective' => false, 'full' => false );
		$header    = in_array( $type, array( 'themes|header', 'themes|footer' ), true );
		$active    = self::is_active( $id, $raw_type, $header );
		$status    = (string) $post->post_status;
		$woo_lost  = null !== $kind && $kind['woo'] && ! Tb_Conditions::woo();

		if ( null === $kind || $woo_lost ) {
			$reason = 'tb.review.unknown_type';
		} elseif ( '' !== $kind['woo_page'] ) {
			$reason = 'tb.review.woo_page';
		} elseif ( $gutenberg ) {
			$reason = 'tb.review.gutenberg';
		} elseif ( $empty ) {
			$reason = 'tb.review.empty';
		} elseif ( 'conditions' === $tr['fail'] ) {
			$reason = 'tb.review.conditions';
		} elseif ( 'scope' === $tr['fail'] || 'scope_size' === $tr['fail'] ) {
			$reason = 'tb.review.scope';
		} elseif ( 'no_include' === $tr['fail'] ) {
			$reason = 'tb.review.no_include';
		} elseif ( 'publish' !== $status || ! $active ) {
			$reason = 'tb.inactive';
		} else {
			$reason = '';
		}
		$pro = null === $kind || $woo_lost ? 'page' : $kind['pro'];
		return array(
			'id'         => $id,
			'title'      => (string) $post->post_title,
			'raw_type'   => $raw_type,
			'type'       => $type,
			'pro'        => $pro,
			'location'   => self::LOCATIONS[ $pro ] ?? '',
			'rank'       => null !== $kind ? (int) $kind['rank'] : 1,
			'fallback'   => null !== $kind && $kind['fallback'],
			'woo_page'   => null !== $kind ? (string) $kind['woo_page'] : '',
			'header'     => $header,
			'gutenberg'  => $gutenberg,
			'data'       => is_string( $data ) ? $data : null,
			'src_hash'   => is_string( $data ) && '' !== $data ? md5( $data ) : '',
			'status'     => $status,
			'active'     => $active,
			'visible'    => $header && $active && 'publish' === $status && ! $blank && ! empty( $tr['effective'] ),
			'conditions' => in_array( $reason, self::NO_CONDITIONS, true ) ? array() : (array) $tr['conditions'],
			'items'      => (array) $tr['items'],
			'full'       => ! empty( $tr['full'] ),
			'reason'     => $reason,
		);
	}

	/** Plantillas de origen (todas salvo papelera), por ID ascendente a partir de `$after`. */
	public static function sources( int $after, int $limit ): array {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array();
		}
		$ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' ) AND ID > %d ORDER BY ID ASC LIMIT %d", self::CPT, $after, $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map( 'intval', $ids );
	}

	/** Cabeceras y pies visibles hoy (para la condición 3 de V.5.3), con su inspección. */
	private static function visible_sources(): array {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array();
		}
		$ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = %s AND p.post_status = 'publish' AND m.meta_value IN ( 'themes|header', 'themes|footer' ) ORDER BY p.ID ASC", self::META_TYPE, self::CPT ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out = array();
		foreach ( $ids as $id ) {
			$info = self::inspect( (int) $id );
			if ( null !== $info && $info['visible'] ) {
				$out[ (int) $id ] = $info;
			}
		}
		return $out;
	}

	/**
	 * ¿Activa en Element Pack? Cabecera y pie: su opción `_bdthemes_builder_<tipo>__<ID>` o la heredada
	 * `_bdthemes_builder_<tipo>` (`activator.php:183-189`). El resto: su ID entre los valores de las opciones de su tipo
	 * (`get_active_template_post_ids()`, `builder-template-helper.php:315-335`).
	 */
	public static function is_active( int $id, string $raw_type, bool $header ): bool {
		if ( '' === $raw_type ) {
			return false;
		}
		if ( $header ) {
			return (bool) get_option( strtolower( self::OPTION_PREFIX . $raw_type ) . '__' . $id, false ) || (bool) get_option( self::OPTION_PREFIX . $raw_type, false );
		}
		return in_array( $id, self::option_values( $raw_type ), true );
	}

	/** Valores (IDs) de las opciones `_bdthemes_builder_<tipo>__*`, en el orden de la consulta de Element Pack. */
	private static function option_values( string $raw_type ): array {
		$pattern = strtolower( self::OPTION_PREFIX . $raw_type . '__' );
		$out     = array();
		foreach ( self::options() as $row ) {
			if ( false !== stripos( (string) $row['name'], $pattern ) ) {
				$out[] = absint( $row['value'] );
			}
		}
		return $out;
	}

	/**
	 * Plantilla que Element Pack elige sin mirar condiciones (`get_template_id()`: `page|404`, las cinco de WooCommerce
	 * y el respaldo de los demás tipos): la última fila de la misma consulta de la versión instalada. 10.x: prefijo
	 * sobre `option_name` y sólo publicadas; 9.9.1: `%patrón%` sin mirar el estado.
	 */
	public static function winner( string $raw_type ): int {
		$pattern = strtolower( self::OPTION_PREFIX . $raw_type . '__' );
		$values  = array();
		if ( Tb_Conditions::is_old( self::ep_version() ) ) {
			global $wpdb;
			if ( isset( $wpdb ) && is_object( $wpdb ) ) {
				$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", '%' . $wpdb->esc_like( $pattern ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				foreach ( $rows as $row ) {
					$values[] = absint( is_object( $row ) ? $row->option_value : ( $row['option_value'] ?? 0 ) );
				}
			}
		} else {
			foreach ( self::option_values( $raw_type ) as $value ) {
				if ( 'publish' === get_post_status( $value ) ) {
					$values[] = $value;
				}
			}
		}
		$last = end( $values );
		return false === $last ? 0 : (int) $last;
	}

	/** Opciones del Theme Builder de Element Pack, con la consulta por prefijo de 10.x (una por petición y sitio). */
	private static function options(): array {
		$blog = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
		if ( isset( self::$memo[ $blog ]['options'] ) ) {
			return self::$memo[ $blog ]['options'];
		}
		global $wpdb;
		$out = array();
		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::OPTION_PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( $rows as $row ) {
				$out[] = array(
					'name'  => (string) ( is_object( $row ) ? $row->option_name : ( $row['option_name'] ?? '' ) ),
					'value' => (string) ( is_object( $row ) ? $row->option_value : ( $row['option_value'] ?? '' ) ),
				);
			}
		}
		self::$memo[ $blog ]['options'] = $out;
		return $out;
	}

	/** Versión de Element Pack instalada ('' si no se sabe: se lee como la actual). */
	public static function ep_version(): string {
		if ( null !== self::$env && isset( self::$env['ep_version'] ) ) {
			return (string) self::$env['ep_version'];
		}
		if ( defined( 'BDTEP_VER' ) ) {
			return (string) BDTEP_VER;
		}
		$retire = '\\Digitalisimo\\Elements\\Legacy\\Retire';
		if ( class_exists( $retire, false ) && method_exists( $retire, 'status' ) ) {
			try {
				$lite = '';
				foreach ( (array) $retire::status() as $plugin ) {
					if ( empty( $plugin['element_pack'] ) || '' === (string) ( $plugin['version'] ?? '' ) ) {
						continue;
					}
					if ( false === stripos( (string) ( $plugin['label'] ?? '' ), 'lite' ) ) {
						return (string) $plugin['version'];
					}
					$lite = (string) $plugin['version'];
				}
				return $lite;
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}
		return '';
	}

	/* ================================================================ Destino */

	/**
	 * Mete las copias con condiciones en la caché del Theme Builder (`elementor_pro_theme_builder_conditions`, que
	 * incluye borradores), como `Conditions_Cache::add()` + `save()`, y refresca la caché cargada en la petición.
	 *
	 * @param int[] $ids Copias.
	 */
	public static function ensure_cache( array $ids ): void {
		$cache   = get_option( self::CACHE_OPTION, array() );
		$cache   = is_array( $cache ) ? $cache : array();
		$changed = false;
		foreach ( $ids as $id ) {
			$id         = (int) $id;
			$conditions = get_post_meta( $id, '_elementor_conditions', true );
			$location   = self::LOCATIONS[ (string) get_post_meta( $id, '_elementor_template_type', true ) ] ?? '';
			if ( $id <= 0 || '' === $location || ! is_array( $conditions ) || ! $conditions || ! self::copy_exists( $id ) ) {
				continue;
			}
			if ( ! isset( $cache[ $location ] ) || ! is_array( $cache[ $location ] ) ) {
				$cache[ $location ] = array();
			}
			if ( ( $cache[ $location ][ $id ] ?? null ) !== $conditions ) {
				$cache[ $location ][ $id ] = $conditions;
				$changed                   = true;
			}
		}
		if ( ! $changed ) {
			return;
		}
		update_option( self::CACHE_OPTION, $cache );
		$manager = self::conditions_manager();
		if ( null !== $manager && method_exists( $manager, 'get_cache' ) ) {
			try {
				$manager->get_cache()->refresh();
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}
	}

	/** Gestor de condiciones del Theme Builder cargado, o null. */
	private static function conditions_manager() {
		$class = Publisher::THEME_BUILDER;
		if ( ! class_exists( $class, false ) || ! method_exists( $class, 'instance' ) ) {
			return null;
		}
		try {
			$module  = $class::instance();
			$manager = is_object( $module ) && method_exists( $module, 'get_conditions_manager' ) ? $module->get_conditions_manager() : null;
		} catch ( \Throwable $e ) {
			return null;
		}
		return is_object( $manager ) ? $manager : null;
	}

	/** Cuántas plantillas comparten alguna inclusión con la copia (`get_conditions_conflicts_by_location()`). */
	private static function shared_conditions( $manager, int $dest, string $location ): int {
		if ( null === $manager || '' === $location || ! method_exists( $manager, 'get_conditions_conflicts_by_location' ) ) {
			return 0;
		}
		$shared = array();
		foreach ( (array) get_post_meta( $dest, '_elementor_conditions', true ) as $condition ) {
			if ( 0 !== strpos( (string) $condition, 'include/' ) ) {
				continue;
			}
			try {
				foreach ( (array) $manager->get_conditions_conflicts_by_location( (string) $condition, $location, $dest ) as $other => $unused ) {
					$shared[ (string) $other ] = true;
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}
		return count( $shared );
	}

	/** ¿Estará cargado el Theme Builder de Elements cuando Element Pack ya no esté? */
	private static function builder_available(): bool {
		if ( null !== self::$env && isset( self::$env['builder'] ) ) {
			return (bool) self::$env['builder'];
		}
		if ( Publisher::builder() && ! Publisher::conflict() ) {
			return true;
		}
		// Con Elementor Pro activo el PRO de Elements no carga en esta petición: basta con que exista.
		return defined( 'DIGITALISIMO_ELEMENTS_FILE' ) && is_readable( dirname( (string) DIGITALISIMO_ELEMENTS_FILE ) . '/modules/theme-builder/module.php' );
	}

	private static function reindex( int $id ): void {
		$class = Publisher::PRIORITY;
		if ( class_exists( $class ) && method_exists( $class, 'reindex' ) ) {
			try {
				$class::reindex( $id );
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}
	}

	/* ================================================================ Utilidades */

	private static function copy_exists( int $id ): bool {
		$post = $id > 0 ? get_post( $id ) : null;
		return $post && 'trash' !== $post->post_status;
	}

	private static function publishable_row( ?array $row ): bool {
		if ( null === $row || 'draft' !== $row['state'] || empty( $row['extra']['publishable'] ) ) {
			return false;
		}
		$dest = (int) $row['dest'];
		return $dest > 0 && self::copy_exists( $dest ) && 'tb' === Publisher::tracked( $dest );
	}

	private static function title( int $id ): string {
		$post = get_post( $id );
		return $post ? (string) $post->post_title : '';
	}

	/** Avisos del sitio: WPML elegía por idioma; el tema sustituye las carcasas de Element Pack. */
	private static function site_notes(): array {
		$out = array();
		if ( defined( 'ICL_LANGUAGE_CODE' ) || function_exists( 'icl_object_id' ) ) {
			$out[] = array( 'tb.wpml', array() );
		}
		foreach ( array_unique( array_filter( array( function_exists( 'get_stylesheet_directory' ) ? get_stylesheet_directory() : '', function_exists( 'get_template_directory' ) ? get_template_directory() : '' ) ) ) as $dir ) {
			if ( is_dir( $dir . '/bdthemes-element-pack' ) ) {
				$out[] = array( 'tb.theme_override', array() );
				break;
			}
		}
		return $out;
	}

	private static function detail( array $info ): array {
		return array( 'id' => (int) $info['id'], 'name' => (string) $info['title'], 'type' => (string) $info['raw_type'], 'target' => (string) $info['pro'] );
	}

	private static function item_detail( array $info, array $item ): array {
		$note = (string) $item['row'] . ( '' !== (string) $item['note'] ? ': ' . $item['note'] : '' );
		return array( 'id' => (int) $info['id'], 'type' => 'include' === $item['polarity'] ? 'incluir' : 'excluir', 'note' => $note, 'count' => (int) $item['count'] );
	}

	private static function reason_detail( array $info, string $reason ): array {
		$detail = array( 'id' => (int) $info['id'], 'type' => (string) $info['raw_type'] );
		if ( 'tb.review.woo_page' === $reason ) {
			$pages          = array( 'cart' => 'carrito', 'checkout' => 'pago', 'myaccount' => 'cuenta' );
			$detail['note'] = 'asignar a mano: página de ' . ( $pages[ $info['woo_page'] ] ?? $info['woo_page'] );
		}
		return $detail;
	}

	private static function report( int $post_id, string $code, array $detail ): void {
		if ( class_exists( Report::class, false ) ) {
			Report::add( $post_id, $code, $detail );
		}
	}

	private static function report_site( string $code, array $detail ): void {
		if ( class_exists( Report::class, false ) ) {
			Report::site( $code, $detail );
		}
	}

	private static function report_codes( array $codes ): void {
		foreach ( $codes as $code ) {
			self::report_site( $code[0], $code[1] );
		}
	}

	/**
	 * Entorno simulado para las pruebas (`ep_version`, `builder`, `trash_days`); null vuelve al real.
	 *
	 * @internal Sólo para pruebas.
	 */
	public static function set_environment( ?array $env ): void {
		self::$env  = $env;
		self::$memo = array();
	}

	/** Olvida la memoria de la petición (opciones de Element Pack). */
	public static function reset(): void {
		self::$memo = array();
	}
}
