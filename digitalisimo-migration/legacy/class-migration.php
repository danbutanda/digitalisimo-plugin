<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-translator.php';
require_once __DIR__ . '/class-native.php';

/**
 * Migración de documentos de Element Pack en el sitio actual.
 *
 * Herramientas → Migrar Element Pack muestra qué widgets heredados usa cada documento del sitio y
 * los convierte por lotes al formato propio (`Native`): widgets de DIGITALÍSIMO Elements o de PRO
 * Elements, rasgos propios y CSS personalizado del elemento, sin identificadores de Element Pack.
 * Antes de tocar un documento guarda su `_elementor_data` original y permite restaurarlo. Nunca toca
 * otros sitios de la red: en Multisite, Red → Ajustes → Migrar Element Pack sólo informa del estado
 * de cada sitio y enlaza a su herramienta.
 */
final class Migration {
	const PAGE   = 'digitalisimo-ep-migration';
	const ACTION = 'digitalisimo_ep_migration';
	const BACKUP = '_digitalisimo_ep_backup';
	const BATCH  = 20;

	/** Opción del sitio que recuerda que las reglas perdidas ya se recuperaron una vez. */
	const REPAIRED = 'digitalisimo_ep_conditions_repaired';
	/** Último documento revisado por la recuperación automática (reanudable) y momento de la actualización que la estrenó. */
	const REPAIR_CURSOR = 'digitalisimo_ep_conditions_repair_cursor';
	const REPAIR_CUTOFF = 'digitalisimo_ep_conditions_repair_cutoff';
	/** Resultado acumulado para avisar al terminar. */
	const REPAIR_TOTALS = 'digitalisimo_ep_conditions_repair_totals';
	/** Documentos por petición en la recuperación automática. */
	const REPAIR_BATCH = 25;
	/** Por revisión heredada, si su conversión coincide con el contenido actual de su página (ver overwritten_parents()). */
	const REVISION_CHECK = 'digitalisimo_ep_revision_check';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_init', array( __CLASS__, 'repair_once' ) );
		add_action( 'admin_notices', array( __CLASS__, 'repair_notice' ) );
	}

	/**
	 * Hasta 1.0.10 la conversión descartaba las reglas de visibilidad cuando la opción de Element Pack del sitio no
	 * tenía guardada la clave de la extensión (que viene activa por defecto). Se recuperan desde la copia original de
	 * cada documento convertido antes de la actualización, por lotes en cada carga de una pantalla de administración
	 * (nunca en AJAX, REST ni cron, y sólo para quien administra el sitio) hasta terminar; el botón de la herramienta
	 * avanza la misma pasada y, terminada, la reinicia; `wp digitalisimo-migration --repair` la hace entera.
	 */
	public static function repair_once() {
		if ( '1' === (string) get_option( self::REPAIRED, '' ) || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		self::repair_step( self::REPAIR_BATCH );
	}

	/**
	 * Un lote de la pasada de recuperación, reanudable: el cursor es el mismo para la recuperación automática y para el
	 * botón, así que cada lote continúa donde quedó el anterior. Con $restart, una pasada ya terminada vuelve a empezar
	 * (reparar de nuevo no cambia lo ya reparado).
	 *
	 * @return array{documents:int, elements:int, remaining:int, done:bool} lo reparado en este lote y lo que queda.
	 */
	public static function repair_step( $limit, $restart = false ) {
		if ( $restart && '1' === (string) get_option( self::REPAIRED, '' ) ) {
			delete_option( self::REPAIRED );
			delete_option( self::REPAIR_CURSOR );
			delete_option( self::REPAIR_TOTALS );
		}
		$cutoff = (int) get_option( self::REPAIR_CUTOFF, 0 );
		if ( ! $cutoff ) {
			$cutoff = time();
			update_option( self::REPAIR_CUTOFF, $cutoff, false );
		}
		$cursor    = (int) get_option( self::REPAIR_CURSOR, 0 );
		$totals    = (array) get_option( self::REPAIR_TOTALS, array( 'documents' => 0, 'elements' => 0 ) );
		$step      = array( 'documents' => 0, 'elements' => 0, 'remaining' => 0, 'done' => true );
		$processed = 0;
		foreach ( self::backups() as $post_id ) {
			if ( $post_id <= $cursor ) {
				continue;
			}
			if ( $limit && $processed >= $limit ) {
				$step['done'] = false;
				++$step['remaining'];
				continue;
			}
			$count = self::repair_conditions( $post_id, $cutoff );
			if ( $count ) {
				++$step['documents'];
				$step['elements']   += $count;
				$totals['documents'] = (int) ( $totals['documents'] ?? 0 ) + 1;
				$totals['elements']  = (int) ( $totals['elements'] ?? 0 ) + $count;
			}
			$cursor = $post_id;
			++$processed;
		}
		update_option( self::REPAIR_CURSOR, $cursor, false );
		update_option( self::REPAIR_TOTALS, $totals, false );
		if ( $step['done'] ) {
			update_option( self::REPAIRED, '1', false );
			delete_option( self::REPAIR_CURSOR );
		}
		return $step;
	}

	/** Documentos con copia original que la pasada de recuperación en curso aún no revisó (todos si ya terminó). */
	public static function repair_remaining() {
		$backups = self::backups();
		if ( '1' === (string) get_option( self::REPAIRED, '' ) ) {
			return count( $backups );
		}
		$cursor = (int) get_option( self::REPAIR_CURSOR, 0 );
		$left   = 0;
		foreach ( $backups as $post_id ) {
			$left += $post_id > $cursor ? 1 : 0;
		}
		return $left;
	}

	/** Aviso único al terminar la recuperación automática, si cambió algo. */
	public static function repair_notice() {
		if ( '1' !== (string) get_option( self::REPAIRED, '' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$totals = get_option( self::REPAIR_TOTALS, null );
		if ( ! is_array( $totals ) ) {
			return;
		}
		delete_option( self::REPAIR_TOTALS );
		if ( empty( $totals['documents'] ) ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( 'DIGITALÍSIMO Migración recuperó reglas de visibilidad perdidas en %d documentos (%d elementos). Vacía la caché de página si la hay.', (int) $totals['documents'], (int) $totals['elements'] ) ) . '</p></div>';
	}

	/**
	 * Recupera en todos los documentos con copia original (WP-CLI); una pasada completa da por terminada la automática.
	 * Con $limit, sólo un lote desde el inicio.
	 *
	 * @return array{documents:int, elements:int}
	 */
	public static function repair_all( $limit = 0 ) {
		$totals = array( 'documents' => 0, 'elements' => 0 );
		$cutoff = (int) get_option( self::REPAIR_CUTOFF, 0 );
		$seen   = 0;
		foreach ( self::backups() as $post_id ) {
			if ( $limit && $seen++ >= $limit ) {
				break;
			}
			$count = self::repair_conditions( $post_id, $cutoff );
			if ( $count ) {
				$totals['documents']++;
				$totals['elements'] += $count;
			}
		}
		if ( ! $limit ) {
			update_option( self::REPAIRED, '1', false );
			delete_option( self::REPAIR_CURSOR );
		}
		return $totals;
	}

	/**
	 * Devuelve a un documento convertido las reglas de visibilidad que su copia original tenía y él ya no. Con $cutoff,
	 * sólo documentos convertidos antes de ese momento (los posteriores ya se convirtieron bien); el texto de la copia
	 * se inspecciona antes de decodificar nada.
	 */
	public static function repair_conditions( $post_id, $cutoff = 0 ) {
		$raw = (string) get_metadata( 'post', $post_id, self::BACKUP, true );
		if ( '' === $raw || false === strpos( $raw, 'ep_display_conditions_enable' ) ) {
			return 0;
		}
		$backup = json_decode( $raw, true );
		if ( ! is_array( $backup ) || ! isset( $backup['data'] ) || ! is_string( $backup['data'] ) ) {
			return 0;
		}
		if ( $cutoff && (int) ( $backup['time'] ?? 0 ) >= $cutoff ) {
			return 0;
		}
		$original = json_decode( $backup['data'], true );
		$current  = self::data( $post_id );
		if ( ! is_array( $original ) || ! $current ) {
			return 0;
		}
		list( $repaired, $count ) = self::repair_conditions_data( $current, $original );
		if ( $count ) {
			update_metadata( 'post', $post_id, '_elementor_data', wp_slash( wp_json_encode( $repaired ) ) );
			self::clear_css( $post_id );
		}
		return $count;
	}

	/**
	 * Reparación pura: cada elemento del original con reglas de Element Pack cuyo homólogo (mismo id) en el documento
	 * actual no tiene ninguna clave `digitalisimo_visibility_*` recibe las reglas convertidas.
	 *
	 * @return array{0:array,1:int} datos reparados y elementos tocados.
	 */
	public static function repair_conditions_data( array $current, array $original ) {
		$with_rules = array();
		self::collect_rules( $original, $with_rules );
		if ( ! $with_rules ) {
			return array( $current, 0 );
		}
		$count = 0;
		return array( self::inject_rules( $current, $with_rules, $count ), $count );
	}

	private static function collect_rules( array $elements, array &$out ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			$id       = (string) ( $element['id'] ?? '' );
			// También sin filas: con el interruptor encendido y «Mostrar», Element Pack no pintaba el elemento.
			if ( '' !== $id && 'yes' === ( $settings['ep_display_conditions_enable'] ?? '' ) ) {
				$out[ $id ] = Native::visibility_settings( $settings );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::collect_rules( $element['elements'], $out );
			}
		}
	}

	private static function inject_rules( array $elements, array $with_rules, &$count ) {
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$id       = (string) ( $element['id'] ?? '' );
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			if ( '' !== $id && isset( $with_rules[ $id ] ) && ! self::has_own_rules( $settings ) ) {
				$element['settings'] = $with_rules[ $id ] + $settings;
				++$count;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::inject_rules( $element['elements'], $with_rules, $count );
			}
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	private static function has_own_rules( array $settings ) {
		foreach ( array_keys( $settings ) as $key ) {
			if ( 0 === strpos( (string) $key, 'digitalisimo_visibility_' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function menu() {
		add_management_page( 'Migrar Element Pack', 'Migrar Element Pack', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
	}

	public static function network_menu() {
		add_submenu_page( 'settings.php', 'Migrar Element Pack', 'Migrar Element Pack', 'manage_network_options', self::PAGE, array( __CLASS__, 'network_page' ) );
	}

	/** Motivo y alternativa de un widget de Element Pack sin adaptador (vacío si lo tiene). */
	public static function reason( $type ) {
		static $list = null;
		if ( null === $list ) {
			$file = __DIR__ . '/unsupported.php';
			$list = is_file( $file ) ? (array) require $file : array();
		}
		return (string) ( $list[ $type ] ?? '' );
	}

	/** Estado legible de un widget del inventario. */
	public static function status( $type, $has_map ) {
		if ( $has_map ) {
			return 'Compatible: se muestra con ' . Translator::target( $type );
		}
		$reason = self::reason( $type );
		return 'Sin adaptador: no se mostrará sin Element Pack.' . ( '' !== $reason ? ' ' . $reason : '' );
	}

	/** IDs de documentos del sitio (no revisiones) que contienen widgets o ajustes de Element Pack. */
	public static function documents( $limit = 0 ) {
		return self::query_documents( false, $limit );
	}

	/**
	 * Revisiones y autoguardados con contenido heredado. No entran en los lotes: Elementor conserva en cada revisión lo
	 * guardado en su momento, y WordPress redirige al padre las escrituras de meta hechas con update_post_meta() sobre
	 * una revisión. Sirven para detectar páginas sobrescritas por conversiones anteriores (overwritten_parents()).
	 */
	public static function legacy_revisions() {
		return self::query_documents( true );
	}

	private static function query_documents( $revisions, $limit = 0 ) {
		global $wpdb;
		$needles = array( '"widgetType":"bdt-', '"widgetType":"fooevents-calendar"', '"widgetType":"lightbox"', '"ep_', '"element_pack_', '"bdt_', '"_mask_shape":"shape-', '"shape_divider_top":"ep-', '"shape_divider_bottom":"ep-', '"image_mask_' );
		$sql     = "SELECT DISTINCT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_elementor_data' AND p.post_type " . ( $revisions ? '=' : '<>' ) . " 'revision' AND ( " . implode( ' OR ', array_fill( 0, count( $needles ), 'm.meta_value LIKE %s' ) ) . ' ) ORDER BY m.post_id';
		if ( $limit ) {
			$sql .= ' LIMIT ' . absint( $limit );
		}
		$like = array();
		foreach ( $needles as $needle ) {
			$like[] = '%' . $wpdb->esc_like( $needle ) . '%';
		}
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $like ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function backups() {
		global $wpdb;
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY post_id", self::BACKUP ) ) );
	}

	/** Recuento por widget: elementos, documentos y si tiene mapa. */
	public static function inventory() {
		$rows = array();
		foreach ( self::documents() as $post_id ) {
			$data = self::data( $post_id );
			$seen = array();
			self::walk( $data, static function ( $element ) use ( &$rows, &$seen, $post_id ) {
				$type = (string) ( $element['widgetType'] ?? '' );
				if ( ! Translator::is_legacy_id( $type ) ) {
					return $element;
				}
				$rows[ $type ] = $rows[ $type ] ?? array( 'elements' => 0, 'documents' => 0, 'map' => (bool) Translator::map( $type ) );
				$rows[ $type ]['elements']++;
				if ( empty( $seen[ $type ] ) ) {
					$rows[ $type ]['documents']++;
					$seen[ $type ] = true;
				}
				return $element;
			} );
		}
		ksort( $rows );
		return $rows;
	}

	/** Elementos (de cualquier tipo) con máscaras o separadores de Element Pack, y figuras que ya no tienen archivo. */
	public static function figures() {
		$out = array( 'elements' => 0, 'documents' => 0, 'missing' => array() );
		foreach ( self::documents() as $post_id ) {
			$count = self::count_figures( self::data( $post_id ), $out['missing'] );
			$out['elements'] += $count;
			$out['documents'] += $count ? 1 : 0;
		}
		$out['missing'] = array_values( array_unique( $out['missing'] ) );
		sort( $out['missing'] );
		return $out;
	}

	private static function count_figures( array $elements, array &$missing ) {
		$count = 0;
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( Masks::is_legacy( $element ) ) {
				++$count;
				$missing = array_merge( $missing, Masks::missing( $element ) );
			}
			$count += self::count_figures( (array) ( $element['elements'] ?? array() ), $missing );
		}
		return $count;
	}

	/** Elementos del documento indicado (una revisión lee los suyos, no los del padre). */
	private static function data( $post_id ) {
		return self::decode( get_metadata( 'post', $post_id, '_elementor_data', true ) );
	}

	private static function decode( $raw ) {
		$data = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
		return is_array( $data ) ? $data : array();
	}

	private static function walk( array $elements, callable $callback ) {
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( 'widget' === ( $element['elType'] ?? '' ) ) {
				$element = $callback( $element );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::walk( $element['elements'], $callback );
			}
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	/**
	 * Conversión en memoria de los elementos de un documento, sin guardar.
	 *
	 * @return array{0:array,1:int} elementos convertidos y número de cambios.
	 */
	public static function convert_data( array $data, $post_id ) {
		// Los widgets (y con ellos los adaptadores) se registran la primera vez que Elementor los pide.
		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->widgets_manager ) ) {
			\Elementor\Plugin::$instance->widgets_manager->get_widget_types();
		}
		$changes = 0;
		foreach ( $data as $index => $element ) {
			if ( is_array( $element ) ) {
				list( $data[ $index ], $count ) = Native::element( $element, $post_id );
				$changes += $count;
			}
		}
		return array( $data, $changes );
	}

	/**
	 * Convierte un documento al formato propio y guarda una copia del original la primera vez. Las escrituras van al id
	 * indicado (update_metadata), nunca al padre de una revisión como haría update_post_meta().
	 *
	 * @return array{native:int} número de elementos convertidos.
	 */
	public static function convert_document( $post_id ) {
		$raw = get_metadata( 'post', $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array( 'native' => 0 );
		}
		list( $data, $changes ) = self::convert_data( self::decode( $raw ), $post_id );
		if ( ! $changes ) {
			return array( 'native' => 0 );
		}
		if ( '' === (string) get_metadata( 'post', $post_id, self::BACKUP, true ) ) {
			add_metadata( 'post', $post_id, self::BACKUP, wp_slash( wp_json_encode( array( 'time' => time(), 'data' => $raw ) ) ), true );
		}
		update_metadata( 'post', $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		self::clear_css( $post_id );
		return array( 'native' => $changes );
	}

	public static function revert_document( $post_id ) {
		$backup = json_decode( (string) get_metadata( 'post', $post_id, self::BACKUP, true ), true );
		if ( ! is_array( $backup ) || ! isset( $backup['data'] ) || ! is_string( $backup['data'] ) ) {
			return false;
		}
		update_metadata( 'post', $post_id, '_elementor_data', wp_slash( $backup['data'] ) );
		delete_metadata( 'post', $post_id, self::BACKUP );
		self::clear_css( $post_id );
		return true;
	}

	private static function clear_css( $post_id ) {
		delete_metadata( 'post', $post_id, '_elementor_css' );
		delete_metadata( 'post', $post_id, '_elementor_element_cache' );
		if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			\Elementor\Core\Files\CSS\Post::create( $post_id )->delete();
		}
	}

	/**
	 * Páginas cuyo contenido actual es la conversión de una de sus revisiones. Hasta 1.0.13 las revisiones entraban en
	 * los lotes y, como update_post_meta() escribe en el padre cuando recibe una revisión, convertir una revisión antigua
	 * o un autoguardado dejaba ese contenido en la página. La comparación es por valor (la conversión es determinista) y
	 * se recuerda por hash para no reconvertir en cada carga. La última revisión guardada no cuenta: su conversión es
	 * justamente el contenido correcto.
	 *
	 * @return array<int, array{revision:int, date:string, autosave:bool}> por página, la revisión que la sobrescribió.
	 */
	public static function overwritten_parents() {
		$out   = array();
		$cache = (array) get_option( self::REVISION_CHECK, array() );
		$dirty = false;
		foreach ( self::legacy_revisions() as $revision_id ) {
			$revision  = get_post( $revision_id );
			$parent_id = $revision ? (int) $revision->post_parent : 0;
			if ( ! $parent_id || $revision_id === self::latest_revision( $parent_id ) ) {
				continue;
			}
			$raw_parent   = (string) get_metadata( 'post', $parent_id, '_elementor_data', true );
			$raw_revision = (string) get_metadata( 'post', $revision_id, '_elementor_data', true );
			if ( '' === $raw_parent || '' === $raw_revision || ( isset( $cache['ack'][ $parent_id ] ) && $cache['ack'][ $parent_id ] === md5( $raw_parent ) ) ) {
				continue;
			}
			$key = md5( $raw_parent ) . ':' . md5( $raw_revision );
			if ( ! isset( $cache[ $revision_id ]['key'] ) || $cache[ $revision_id ]['key'] !== $key ) {
				list( $converted, $changes ) = self::convert_data( self::decode( $raw_revision ), $revision_id );
				$cache[ $revision_id ] = array( 'key' => $key, 'same' => $changes > 0 && self::decode( wp_json_encode( $converted ) ) === self::decode( $raw_parent ) );
				$dirty                 = true;
			}
			if ( ! empty( $cache[ $revision_id ]['same'] ) ) {
				$out[ $parent_id ] = array( 'revision' => $revision_id, 'date' => (string) $revision->post_modified, 'autosave' => (bool) wp_is_post_autosave( $revision_id ) );
			}
		}
		if ( $dirty ) {
			update_option( self::REVISION_CHECK, $cache, false );
		}
		return $out;
	}

	/** Última revisión guardada de una página (no un autoguardado), o 0. */
	public static function latest_revision( $parent_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'revision' AND post_name NOT LIKE %s ORDER BY ID DESC LIMIT 1", $parent_id, '%' . $wpdb->esc_like( '-autosave-v' ) . '%' ) );
	}

	/**
	 * Devuelve a una página sobrescrita su última versión guardada en el editor, convertida (Elementor crea una revisión
	 * en cada guardado), y esa versión pasa a ser su copia original si era heredada. Sin revisión guardada (las plantillas
	 * de Elementor sólo tienen autoguardados) usa la copia original de la página, que se conserva, salvo que esa copia sea
	 * la de la revisión que la pisó.
	 *
	 * @return string 'revision', 'backup' o '' si no hay de dónde recuperar.
	 */
	public static function recover_from_revision( $parent_id, $revision_id = 0 ) {
		list( $source, $raw ) = self::recovery_source( $parent_id, $revision_id );
		if ( '' === $raw ) {
			return '';
		}
		list( $converted, $changes ) = self::convert_data( self::decode( $raw ), $parent_id );
		update_metadata( 'post', $parent_id, '_elementor_data', wp_slash( wp_json_encode( $converted ) ) );
		if ( 'revision' === $source && $changes ) {
			update_metadata( 'post', $parent_id, self::BACKUP, wp_slash( wp_json_encode( array( 'time' => time(), 'data' => $raw ) ) ) );
		}
		self::clear_css( $parent_id );
		return $source;
	}

	/**
	 * De dónde puede recuperarse una página sobrescrita: su última revisión guardada o, si no la hay, su copia original
	 * cuando no es la de la revisión que la pisó.
	 *
	 * @return array{0:string,1:string} origen ('revision', 'backup' o '') y datos en bruto.
	 */
	public static function recovery_source( $parent_id, $revision_id = 0 ) {
		$latest = self::latest_revision( $parent_id );
		$raw    = $latest ? (string) get_metadata( 'post', $latest, '_elementor_data', true ) : '';
		if ( '' !== $raw ) {
			return array( 'revision', $raw );
		}
		$backup = json_decode( (string) get_metadata( 'post', $parent_id, self::BACKUP, true ), true );
		$raw    = is_array( $backup ) && isset( $backup['data'] ) && is_string( $backup['data'] ) ? $backup['data'] : '';
		if ( '' !== $raw && $revision_id && self::decode( $raw ) === self::decode( get_metadata( 'post', $revision_id, '_elementor_data', true ) ) ) {
			$raw = '';
		}
		return array( '' !== $raw ? 'backup' : '', $raw );
	}

	/** Da por revisada una página sobrescrita con su contenido actual: deja de listarse mientras ese contenido no cambie. */
	public static function acknowledge( $parent_id ) {
		$cache                          = (array) get_option( self::REVISION_CHECK, array() );
		$cache['ack'][ (int) $parent_id ] = md5( (string) get_metadata( 'post', $parent_id, '_elementor_data', true ) );
		update_option( self::REVISION_CHECK, $cache, false );
	}

	/**
	 * Clasifica los documentos con contenido heredado: «convertible» si la conversión aún cambiaría algo y «blocked» si
	 * sólo conserva widgets sin adaptador, que se quedan tal cual (sin Element Pack no se muestran) y por eso los lotes
	 * los saltan en vez de repetirlos.
	 *
	 * @return array{convertible:int[], blocked:int[]}
	 */
	public static function classify() {
		$groups = array( 'convertible' => array(), 'blocked' => array() );
		foreach ( self::documents() as $post_id ) {
			$legacy      = false;
			$convertible = false;
			foreach ( self::data( $post_id ) as $element ) {
				if ( ! is_array( $element ) ) {
					continue;
				}
				if ( Native::is_convertible( $element ) ) {
					$convertible = true;
					break;
				}
				$legacy = $legacy || Native::is_legacy( $element );
			}
			if ( $convertible ) {
				$groups['convertible'][] = $post_id;
			} elseif ( $legacy ) {
				$groups['blocked'][] = $post_id;
			}
		}
		return $groups;
	}

	/** Documentos que la conversión aún cambiaría: los que toman los lotes. */
	public static function convertible() {
		return self::classify()['convertible'];
	}

	/** Documentos que aún contienen widgets o ajustes heredados, convertibles o no. */
	public static function pending() {
		$groups  = self::classify();
		$pending = array_merge( $groups['convertible'], $groups['blocked'] );
		sort( $pending );
		return $pending;
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No autorizado.', 403 );
		}
		check_admin_referer( self::ACTION . '_' . get_current_blog_id() );
		$task = sanitize_key( wp_unslash( $_POST['task'] ?? '' ) );
		$args = array( 'page' => self::PAGE, 'done' => $task, 'count' => 0, 'native' => 0 );
		if ( 'convert' === $task && ! Translator::element_pack_active() ) {
			foreach ( array_slice( self::convertible(), 0, self::BATCH ) as $post_id ) {
				$result = self::convert_document( $post_id );
				if ( ! empty( $result['native'] ) ) {
					$args['native'] += (int) $result['native'];
					++$args['count'];
				}
			}
			$groups          = self::classify();
			$args['left']    = count( $groups['convertible'] );
			$args['blocked'] = count( $groups['blocked'] );
		} elseif ( 'revert' === $task ) {
			foreach ( array_slice( self::backups(), 0, self::BATCH ) as $post_id ) {
				$args['count'] += self::revert_document( $post_id ) ? 1 : 0;
			}
		} elseif ( 'repair' === $task ) {
			$step           = self::repair_step( self::BATCH, true );
			$args['count']  = $step['documents'];
			$args['native'] = $step['elements'];
			$args['left']   = $step['remaining'];
		} elseif ( 'recover' === $task ) {
			foreach ( array_slice( self::overwritten_parents(), 0, self::BATCH, true ) as $parent_id => $info ) {
				$args['count'] += '' !== self::recover_from_revision( $parent_id, $info['revision'] ) ? 1 : 0;
			}
			$args['left'] = count( self::overwritten_parents() );
		} elseif ( 'acknowledge' === $task ) {
			$post_id = absint( $_POST['post_id'] ?? 0 );
			if ( $post_id ) {
				self::acknowledge( $post_id );
				$args['count'] = 1;
			}
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
		exit;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$inventory   = self::inventory();
		$groups      = self::classify();
		$convertible = count( $groups['convertible'] );
		$blocked     = count( $groups['blocked'] );
		$backups     = count( self::backups() );
		$repaired    = '1' === (string) get_option( self::REPAIRED, '' );
		$to_review   = self::repair_remaining();
		$overwritten = self::overwritten_parents();
		$active      = Translator::element_pack_active();
		echo '<div class="wrap"><h1>Migrar Element Pack</h1>';
		echo '<p>Mientras este plugin esté activo, las páginas creadas con Element Pack se siguen viendo sin él. La conversión deja cada documento sólo con widgets y ajustes de DIGITALÍSIMO Elements (los estilos que sólo existían en Element Pack pasan al CSS personalizado del elemento) y guarda una copia del original para poder restaurarlo. Sólo afecta a este sitio.</p>';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sólo muestra el resultado de la acción.
		if ( isset( $_GET['done'] ) ) {
			$done  = sanitize_key( wp_unslash( $_GET['done'] ) );
			$count = absint( $_GET['count'] ?? 0 );
			$items = absint( $_GET['native'] ?? 0 );
			$left  = absint( $_GET['left'] ?? 0 );
			if ( 'convert' === $done ) {
				$msg = sprintf( 'Se convirtieron %d documentos (%d elementos): ya sólo usan widgets y ajustes propios.', $count, $items ) . ' ' . ( $left ? sprintf( 'Quedan %d por convertir.', $left ) : 'No queda nada por convertir.' );
				if ( absint( $_GET['blocked'] ?? 0 ) ) {
					$msg .= ' ' . sprintf( '%d documentos conservan sólo widgets sin adaptador y no cambian.', absint( $_GET['blocked'] ) );
				}
			} elseif ( 'repair' === $done ) {
				$msg = sprintf( 'Se recuperaron reglas de visibilidad en %d documentos (%d elementos).', $count, $items ) . ' ' . ( $left ? sprintf( 'Quedan %d documentos con copia por revisar.', $left ) : 'Revisión completa: todos los documentos con copia están revisados.' );
			} elseif ( 'recover' === $done ) {
				$msg = sprintf( 'Se recuperaron %d páginas desde su última revisión guardada o su copia original.', $count ) . ( $left ? ' ' . sprintf( 'Quedan %d; la lista indica por qué.', $left ) : '' );
			} elseif ( 'acknowledge' === $done ) {
				$msg = $count ? 'Página dada por revisada: deja de listarse mientras su contenido no cambie.' : 'No se indicó ninguna página.';
			} else {
				$msg = sprintf( 'Se restauraron %d documentos a su versión original.', $count );
			}
			echo '<div class="notice notice-success"><p>' . esc_html( $msg ) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( $active ) {
			echo '<div class="notice notice-warning"><p>Element Pack sigue activo en este sitio: desactívalo (sin desinstalarlo) antes de convertir, porque sus controles ocupan los mismos nombres. Mientras esté activo, sus widgets se muestran con su propio código.</p></div>';
		}
		echo '<table class="widefat striped" style="max-width:860px"><thead><tr><th>Widget de Element Pack</th><th>Elementos</th><th>Documentos</th><th>Estado</th></tr></thead><tbody>';
		if ( ! $inventory ) {
			echo '<tr><td colspan="4">Este sitio no usa widgets de Element Pack.</td></tr>';
		}
		foreach ( $inventory as $type => $row ) {
			$status = self::status( $type, $row['map'] );
			echo '<tr><td><code>' . esc_html( $type ) . '</code></td><td>' . absint( $row['elements'] ) . '</td><td>' . absint( $row['documents'] ) . '</td><td>' . esc_html( $status ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$figures = self::figures();
		if ( $figures['elements'] ) {
			echo '<p>' . esc_html( sprintf( 'Máscaras y separadores con figuras de Element Pack: %d elementos en %d documentos. Al convertir pasan a copias propias guardadas en los archivos de este sitio.', $figures['elements'], $figures['documents'] ) ) . '</p>';
		}
		if ( $figures['missing'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( 'No se encontró el archivo de: %s. Mantén Element Pack instalado (desactivado) hasta convertir; sin archivo, la conversión retira esa máscara o separador para que la imagen siga visible.', implode( ', ', $figures['missing'] ) ) ) . '</p></div>';
		}
		echo '<p>' . esc_html( sprintf( 'Documentos pendientes de convertir: %d. Documentos que sólo conservan widgets sin adaptador: %d. Documentos con copia original: %d.', $convertible, $blocked, $backups ) ) . '</p>';
		if ( $blocked ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( '%d documentos no cambian al convertir porque sólo les quedan widgets sin adaptador (en la tabla, «Sin adaptador»): sin Element Pack esos widgets no se muestran. Sustitúyelos en el editor por la alternativa que indica la tabla, o déjalos; los lotes los saltan.', $blocked ) ) . '</p></div>';
		}
		if ( $backups ) {
			echo '<p>' . esc_html( $repaired ? sprintf( 'Reglas de visibilidad: revisadas en los %d documentos con copia original.', $backups ) : sprintf( 'Reglas de visibilidad: %d documentos con copia original por revisar (la revisión también avanza sola al navegar por la administración).', $to_review ) ) . '</p>';
		}
		$recoverable = 0;
		if ( $overwritten ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( sprintf( 'Hasta Migración 1.0.13, convertir una revisión escribía el resultado en la página a la que pertenece: %d páginas tienen ahora el contenido de una revisión anterior o de un autoguardado. «Recuperar» vuelve a convertir la última versión guardada en el editor de cada una o, si no tiene revisiones guardadas (las plantillas sólo tienen autoguardados), su copia original; revísalas después.', count( $overwritten ) ) ) . '</p><ul style="list-style:disc;margin-left:20px">';
			foreach ( $overwritten as $parent_id => $info ) {
				$title  = get_the_title( $parent_id );
				$source = self::recovery_source( $parent_id, $info['revision'] )[0];
				$recoverable += '' !== $source ? 1 : 0;
				$how = 'revision' === $source ? 'se recuperará desde su última revisión guardada' : ( 'backup' === $source ? 'sin revisión guardada: se recuperará desde su copia original' : 'sin revisión guardada ni copia original distinta: esa versión es la única conocida; revísala en el editor y, si está bien, dala por revisada' );
				echo '<li><a href="' . esc_url( admin_url( 'post.php?post=' . (int) $parent_id . '&action=elementor' ) ) . '">' . esc_html( '' !== $title ? $title : '#' . $parent_id ) . '</a> · ' . esc_html( sprintf( $info['autosave'] ? 'sobrescrita con el autoguardado del %s' : 'sobrescrita con la revisión del %s', $info['date'] ) ) . ' · ' . esc_html( $how );
				if ( '' === $source ) {
					echo ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
					wp_nonce_field( self::ACTION . '_' . get_current_blog_id() );
					echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="task" value="acknowledge"><input type="hidden" name="post_id" value="' . (int) $parent_id . '">';
					submit_button( 'Dar por revisada', 'link', 'submit', false );
					echo '</form>';
				}
				echo '</li>';
			}
			echo '</ul></div>';
		}
		if ( ! $convertible && ! $blocked && ! $inventory && ! $overwritten ) {
			echo '<div class="notice notice-success inline"><p>Este sitio ya no tiene contenido heredado. Cuando todos los sitios estén así y revisados, puedes desinstalar Element Pack y DIGITALÍSIMO Migración.</p></div>';
		}
		$buttons = array(
			'convert' => array( 'Convertir el siguiente lote', $active ? 0 : $convertible, 'button-primary' ),
			'recover' => array( 'Recuperar las páginas sobrescritas', $recoverable, 'button-primary' ),
			'repair'  => array( $repaired ? 'Revisar de nuevo las reglas de visibilidad' : 'Recuperar reglas de visibilidad perdidas', $to_review, 'button-secondary' ),
			'revert'  => array( 'Restaurar el siguiente lote al original', $backups, 'button-secondary' ),
		);
		foreach ( $buttons as $task => $button ) {
			if ( ! $button[1] ) {
				continue;
			}
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px">';
			wp_nonce_field( self::ACTION . '_' . get_current_blog_id() );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="task" value="' . esc_attr( $task ) . '">';
			submit_button( $button[0] . ' (' . min( self::BATCH, $button[1] ) . ')', $button[2], 'submit', false );
			echo '</form>';
		}
		echo '</div>';
	}

	/** Resumen por sitio de la red: sólo lectura, con enlace a la herramienta de cada sitio. */
	public static function network_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
		$sites = get_sites( array( 'number' => 500, 'fields' => 'ids' ) );
		echo '<div class="wrap"><h1>Migrar Element Pack en la red</h1>';
		echo '<p>Estado de los widgets de Element Pack en cada sitio. Esta pantalla sólo informa: la conversión de documentos se lanza desde la herramienta de cada sitio y nunca afecta a otro.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Sitio</th><th>Elementos compatibles</th><th>Sin adaptador</th><th>Documentos pendientes de convertir</th><th></th></tr></thead><tbody>';
		$total_missing = 0;
		foreach ( $sites as $site_id ) {
			switch_to_blog( (int) $site_id );
			$compatible = 0;
			$missing    = array();
			foreach ( self::inventory() as $type => $row ) {
				if ( $row['map'] ) {
					$compatible += $row['elements'];
				} else {
					$missing[] = $type . ' (' . $row['elements'] . ')';
				}
			}
			$groups  = self::classify();
			$pending = count( $groups['convertible'] ) . ( $groups['blocked'] ? ' (+' . count( $groups['blocked'] ) . ' sólo con widgets sin adaptador)' : '' );
			$name    = get_bloginfo( 'name' );
			$url     = home_url( '/' );
			$tool    = admin_url( 'tools.php?page=' . self::PAGE );
			restore_current_blog();
			$total_missing += count( $missing );
			echo '<tr><td><strong>' . esc_html( $name ) . '</strong><br><code>' . esc_html( $url ) . '</code></td><td>' . absint( $compatible ) . '</td><td>' . ( $missing ? esc_html( implode( ', ', $missing ) ) : '—' ) . '</td><td>' . esc_html( $pending ) . '</td><td><a href="' . esc_url( $tool ) . '">Abrir la herramienta del sitio</a></td></tr>';
		}
		echo '</tbody></table>';
		echo '<p>' . esc_html( $total_missing ? 'Hay widgets sin adaptador: la herramienta de cada sitio explica el motivo y la alternativa de cada uno.' : 'Ningún sitio usa widgets de Element Pack sin adaptador.' ) . '</p>';
		echo '</div>';
	}
}
