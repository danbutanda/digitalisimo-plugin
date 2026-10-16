<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-translator.php';
require_once __DIR__ . '/class-native.php';
// Las pantallas usan la tarea de Migrar, la retirada de plugins y los alias (sin efectos al cargarse).
require_once __DIR__ . '/class-runner.php';
require_once __DIR__ . '/class-retire.php';
require_once __DIR__ . '/class-aliases.php';

/**
 * Migración de documentos de Element Pack en el sitio actual.
 *
 * Herramientas → Migrar a DIGITALÍSIMO Elements tiene un solo botón, «Migrar», que lanza la tarea completa del
 * sitio (Runner): desactiva los plugins sustituidos, convierte por lotes cada documento al formato propio (`Native`:
 * widgets de DIGITALÍSIMO Elements o de PRO Elements, rasgos propios y CSS personalizado del elemento, sin
 * identificadores de Element Pack), actualiza los widgets fusionados y deja el informe. Antes de tocar un documento
 * guarda su `_elementor_data` original (`wp digitalisimo-migration --revert` lo restaura). Nunca toca otros sitios
 * de la red: en Multisite, Red → Ajustes → Migrar a DIGITALÍSIMO Elements recorre todos los sitios, y lo dice.
 * Esta clase conserva el inventario, la conversión documento a documento y las recuperaciones de versiones antiguas
 * que la tarea encadena.
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
	 * (nunca en AJAX, REST ni cron, y sólo para quien administra el sitio) hasta terminar; Migrar continúa la misma
	 * pasada si aún no terminó; `wp digitalisimo-migration --repair` la hace entera.
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
		add_management_page( 'Migrar a DIGITALÍSIMO Elements', 'Migrar a DIGITALÍSIMO Elements', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
	}

	public static function network_menu() {
		add_submenu_page( 'settings.php', 'Migrar a DIGITALÍSIMO Elements', 'Migrar a DIGITALÍSIMO Elements', 'manage_network_options', self::PAGE, array( __CLASS__, 'network_page' ) );
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
	 * Revisiones y autoguardados con contenido heredado. Sirven para detectar páginas sobrescritas por conversiones
	 * anteriores (overwritten_parents()); después, Migrar los convierte cada uno en su propio registro (convert_document()
	 * escribe con update_metadata(), nunca en el padre como haría update_post_meta() sobre una revisión), salvo los que
	 * delatan una página sobrescrita que no pudo recuperarse.
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

	/** Cachés de Elementor del documento: su CSS, el HTML de sus elementos y la lista de recursos que encola. */
	public static function clear_css( $post_id ) {
		delete_metadata( 'post', $post_id, '_elementor_css' );
		delete_metadata( 'post', $post_id, '_elementor_element_cache' );
		delete_metadata( 'post', $post_id, '_elementor_page_assets' );
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
		$out = array();
		foreach ( self::legacy_revisions() as $revision_id ) {
			$info = self::overwritten_by( $revision_id );
			if ( $info ) {
				$out[ $info['parent'] ] = $info;
			}
		}
		return $out;
	}

	/**
	 * ¿Sobrescribió esta revisión a su página? La comprobación de overwritten_parents() para una sola revisión, con la
	 * caché guardada en cuanto cambia: Migrar la recorre por lotes con plazo y una petición cortada no pierde lo hecho.
	 *
	 * @return array{parent:int, revision:int, date:string, autosave:bool}|null
	 */
	public static function overwritten_by( $revision_id ) {
		$revision  = get_post( $revision_id );
		$parent_id = $revision ? (int) $revision->post_parent : 0;
		if ( ! $parent_id || (int) $revision_id === self::latest_revision( $parent_id ) ) {
			return null;
		}
		$cache        = (array) get_option( self::REVISION_CHECK, array() );
		$raw_parent   = (string) get_metadata( 'post', $parent_id, '_elementor_data', true );
		$raw_revision = (string) get_metadata( 'post', $revision_id, '_elementor_data', true );
		if ( '' === $raw_parent || '' === $raw_revision || ( isset( $cache['ack'][ $parent_id ] ) && $cache['ack'][ $parent_id ] === md5( $raw_parent ) ) ) {
			return null;
		}
		$key = md5( $raw_parent ) . ':' . md5( $raw_revision );
		if ( ! isset( $cache[ $revision_id ]['key'] ) || $cache[ $revision_id ]['key'] !== $key ) {
			list( $converted, $changes ) = self::convert_data( self::decode( $raw_revision ), $revision_id );
			$parent                      = self::decode( $raw_parent );
			// Una página cuyos alias ya pasaron a su widget fusionado sigue reconociéndose: la comparación se hace con los
			// alias convertidos en los dos lados.
			if ( Aliases::map() ) {
				$converted = Aliases::convert_data( $converted )[0];
				$parent    = Aliases::convert_data( $parent )[0];
			}
			$cache[ $revision_id ] = array( 'key' => $key, 'same' => $changes > 0 && self::decode( wp_json_encode( $converted ) ) === self::decode( wp_json_encode( $parent ) ) );
			update_option( self::REVISION_CHECK, $cache, false );
		}
		return empty( $cache[ $revision_id ]['same'] ) ? null : array( 'parent' => $parent_id, 'revision' => (int) $revision_id, 'date' => (string) $revision->post_modified, 'autosave' => (bool) wp_is_post_autosave( $revision_id ) );
	}

	/**
	 * Revisiones heredadas que pueden haber sobrescrito su página: sólo las conversiones de Migración 1.0.13 o anterior
	 * lo hacían, y esas conversiones dejaban la copia original en la página. Las demás no se comprueban.
	 *
	 * @return int[]
	 */
	public static function recover_candidates() {
		$revisions = self::legacy_revisions();
		if ( ! $revisions ) {
			return array();
		}
		$backups = array_flip( self::backups() );
		$out     = array();
		foreach ( $revisions as $revision_id ) {
			$revision = get_post( $revision_id );
			if ( $revision && isset( $backups[ (int) $revision->post_parent ] ) ) {
				$out[] = (int) $revision_id;
			}
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

	/** Dar por revisada una página sobrescrita sin origen del que recuperarla (enlaces de versiones anteriores). */
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No autorizado.', 403 );
		}
		check_admin_referer( self::ACTION . '_' . get_current_blog_id() );
		$task = sanitize_key( wp_unslash( $_POST['task'] ?? '' ) );
		$args = array( 'page' => self::PAGE, 'done' => $task, 'count' => 0 );
		if ( 'acknowledge' === $task ) {
			$post_id = absint( $_POST['post_id'] ?? 0 );
			if ( $post_id ) {
				self::acknowledge( $post_id );
				$args['count'] = 1;
			}
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
		exit;
	}

	private static function assets() {
		if ( function_exists( 'wp_enqueue_script' ) && defined( 'DIGITALISIMO_MIGRATION_FILE' ) ) {
			wp_enqueue_script( 'digitalisimo-migration-migrate', plugins_url( 'assets/js/migrate.js', DIGITALISIMO_MIGRATION_FILE ), array(), DIGITALISIMO_MIGRATION_VERSION, true );
		}
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		Runner::load();
		$state   = Runner::state();
		$running = $state && empty( $state['done'] );
		echo '<div class="wrap digi-migrate-wrap"><h1>Migrar a DIGITALÍSIMO Elements</h1>';
		echo '<p>Un solo paso. <strong>Migrar</strong> desactiva en este sitio los plugins que DIGITALÍSIMO Elements sustituye (Elementor, Elementor Pro, PRO Elements y Element Pack), convierte al formato propio el contenido de Element Pack y el de los widgets fusionados, vacía las cachés y deja aquí el informe. Guarda una copia de cada documento antes de tocarlo y no borra ningún plugin: los deja desactivados para que los elimines desde Plugins cuando hayas comprobado el sitio. Si cierras esta pantalla, la migración sigue sola y se reanuda al volver. Sólo afecta a este sitio' . ( is_multisite() ? '; Red → Ajustes → Migrar a DIGITALÍSIMO Elements hace lo mismo en todos los sitios de la red' : '' ) . '.</p>';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sólo muestra el resultado de la acción.
		if ( isset( $_GET['done'] ) && 'acknowledge' === sanitize_key( wp_unslash( $_GET['done'] ) ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html( absint( $_GET['count'] ?? 0 ) ? 'Página dada por revisada: deja de listarse mientras su contenido no cambie.' : 'No se indicó ninguna página.' ) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		self::assets();
		echo '<div id="digi-migrate" class="digi-migrate" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( Runner::AJAX . '_' . get_current_blog_id() ) ) . '" data-network="0" data-running="' . ( $running ? '1' : '0' ) . '">';
		echo '<p><button type="button" class="button button-primary button-hero digi-migrate__button"' . ( $running ? ' disabled' : '' ) . '>Migrar</button></p>';
		echo '<div class="digi-migrate__status">' . Runner::render( $running ? $state : Runner::report() ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ya escapado por Runner.
		echo '</div>';
		echo '<h2>Qué hay en este sitio</h2>';
		self::site_summary();
		echo '</div>';
	}

	/** Estado del sitio antes de migrar: plugins sustituidos, inventario de Element Pack, figuras y recuentos. */
	private static function site_summary() {
		$status = Retire::status();
		$parts  = array();
		foreach ( $status as $info ) {
			$parts[] = $info['label'] . ' (' . ( $info['network'] ? 'activo en toda la red' : ( $info['site'] ? 'activo en este sitio' : 'desactivado' ) ) . ')';
		}
		echo '<p>' . esc_html( 'Plugins que DIGITALÍSIMO Elements sustituye: ' . ( $parts ? implode( ', ', $parts ) . '.' : 'ninguno instalado.' ) ) . '</p>';
		if ( Translator::element_pack_active() ) {
			echo '<div class="notice notice-warning inline"><p>Element Pack está activo: Migrar lo desactiva (sin borrarlo) antes de convertir, porque sus controles ocupan los mismos nombres. Mientras esté activo, sus widgets se muestran con su propio código.</p></div>';
		}
		$inventory = self::inventory();
		echo '<table class="widefat striped" style="max-width:860px"><thead><tr><th>Widget de Element Pack</th><th>Elementos</th><th>Documentos</th><th>Estado</th></tr></thead><tbody>';
		if ( ! $inventory ) {
			echo '<tr><td colspan="4">Este sitio no usa widgets de Element Pack.</td></tr>';
		}
		foreach ( $inventory as $type => $row ) {
			echo '<tr><td><code>' . esc_html( $type ) . '</code></td><td>' . absint( $row['elements'] ) . '</td><td>' . absint( $row['documents'] ) . '</td><td>' . esc_html( self::status( $type, $row['map'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$figures = self::figures();
		if ( $figures['elements'] ) {
			echo '<p>' . esc_html( sprintf( 'Máscaras y separadores con figuras de Element Pack: %d elementos en %d documentos. Al convertir pasan a copias propias guardadas en los archivos de este sitio.', $figures['elements'], $figures['documents'] ) ) . '</p>';
		}
		if ( $figures['missing'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( 'No se encontró el archivo de: %s. Mantén Element Pack instalado (desactivado) hasta migrar; sin archivo, la conversión retira esa máscara o separador para que la imagen siga visible.', implode( ', ', $figures['missing'] ) ) ) . '</p></div>';
		}
		$groups    = self::classify();
		$revisions = count( self::legacy_revisions() );
		$aliases   = Aliases::map() ? count( Aliases::documents() ) : count( Aliases::documents_with( Aliases::KNOWN ) );
		echo '<p>' . esc_html( sprintf( 'Documentos con contenido de Element Pack: %d por convertir y %d que sólo conservan widgets sin equivalente. Revisiones y autoguardados con contenido de Element Pack: %d. Documentos con widgets fusionados por actualizar: %d. Documentos con copia original: %d.', count( $groups['convertible'] ), count( $groups['blocked'] ), $revisions, $aliases, count( self::backups() ) ) ) . '</p>';
		if ( $groups['blocked'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( '%d documentos no cambian al migrar porque sólo les quedan widgets sin equivalente (en la tabla, «Sin adaptador»): sin Element Pack esos widgets no se muestran. Sustitúyelos en el editor por la alternativa que indica la tabla, o acepta que desaparezcan.', count( $groups['blocked'] ) ) ) . '</p></div>';
		}
		$active = array_filter( $status, static function ( $info ) {
			return $info['site'] || $info['network'];
		} );
		if ( ! $inventory && ! $revisions && ! $aliases && ! $active ) {
			echo '<div class="notice notice-success inline"><p>Este sitio ya no tiene contenido heredado ni plugins sustituidos activos. Cuando lo hayas comprobado, puedes desinstalar Element Pack, Elementor, Elementor Pro y DIGITALÍSIMO Migración.</p></div>';
		}
	}

	/** Pantalla de red: un botón que migra todos los sitios (y lo dice) y el estado de cada uno. */
	public static function network_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
		Runner::load();
		$job     = Runner::network_state() ? Runner::network_summary() : null;
		$running = $job && empty( $job['done'] );
		$sites   = get_sites( array( 'number' => 500, 'fields' => 'ids' ) );
		echo '<div class="wrap digi-migrate-wrap"><h1>Migrar a DIGITALÍSIMO Elements en la red</h1>';
		echo '<p>Un solo paso para toda la red. <strong>Migrar toda la red</strong> desactiva en toda la red los plugins que DIGITALÍSIMO Elements sustituye (Elementor, Elementor Pro, PRO Elements y Element Pack) y después hace en cada uno de los ' . count( $sites ) . ' sitios lo mismo que el botón de su herramienta: desactiva los que tenga activos por sitio, convierte su contenido de Element Pack y sus widgets fusionados, vacía sus cachés y deja su informe en Herramientas → Migrar a DIGITALÍSIMO Elements. <strong>Modifica todos los sitios de la red.</strong> No borra ningún plugin: cuando hayas comprobado los sitios, los eliminas desde Red → Plugins.</p>';
		self::assets();
		echo '<div id="digi-migrate" class="digi-migrate" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( Runner::AJAX . '_network' ) ) . '" data-network="1" data-running="' . ( $running ? '1' : '0' ) . '">';
		echo '<p><button type="button" class="button button-primary button-hero digi-migrate__button"' . ( $running ? ' disabled' : '' ) . '>Migrar toda la red</button></p>';
		echo '<div class="digi-migrate__status">' . Runner::render_network( $job ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ya escapado por Runner.
		echo '</div>';
		echo '<h2>Estado de cada sitio</h2>';
		echo '<table class="widefat striped"><thead><tr><th>Sitio</th><th>Elementos compatibles</th><th>Sin adaptador</th><th>Documentos pendientes de convertir</th><th>Widgets fusionados</th><th>Plugins sustituidos activos en el sitio</th><th>Última migración</th><th></th></tr></thead><tbody>';
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
			$aliases = Aliases::map() ? count( Aliases::documents() ) : count( Aliases::documents_with( Aliases::KNOWN ) );
			$plugins = array();
			foreach ( Retire::status() as $info ) {
				if ( $info['site'] ) {
					$plugins[] = $info['label'];
				}
			}
			$report = Runner::report();
			$state  = Runner::state();
			$last   = $state && empty( $state['done'] ) ? 'En marcha' : ( $report && ! empty( $report['finished'] ) ? wp_date( 'd/m/Y H:i', (int) $report['finished'] ) : 'Nunca' );
			$name   = get_bloginfo( 'name' );
			$url    = home_url( '/' );
			$tool   = admin_url( 'tools.php?page=' . self::PAGE );
			restore_current_blog();
			$total_missing += count( $missing );
			echo '<tr><td><strong>' . esc_html( $name ) . '</strong><br><code>' . esc_html( $url ) . '</code></td><td>' . absint( $compatible ) . '</td><td>' . ( $missing ? esc_html( implode( ', ', $missing ) ) : '—' ) . '</td><td>' . esc_html( $pending ) . '</td><td>' . absint( $aliases ) . '</td><td>' . esc_html( $plugins ? implode( ', ', $plugins ) : '—' ) . '</td><td>' . esc_html( $last ) . '</td><td><a href="' . esc_url( $tool ) . '">Abrir la herramienta del sitio</a></td></tr>';
		}
		echo '</tbody></table>';
		$network_active = array();
		foreach ( Retire::status() as $info ) {
			if ( $info['network'] ) {
				$network_active[] = $info['label'];
			}
		}
		echo '<p>' . esc_html( ( $network_active ? 'Activos en toda la red: ' . implode( ', ', $network_active ) . '. ' : '' ) . ( $total_missing ? 'Hay widgets sin adaptador: la herramienta de cada sitio explica el motivo y la alternativa de cada uno.' : 'Ningún sitio usa widgets de Element Pack sin adaptador.' ) ) . '</p>';
		echo '</div>';
	}
}
