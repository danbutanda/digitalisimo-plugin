<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-translator.php';

/**
 * Migración de documentos de Element Pack en el sitio actual.
 *
 * Herramientas → Migrar Element Pack muestra qué widgets `bdt-*` usan las páginas del sitio y
 * convierte por lotes los que tienen mapa verificado. Antes de tocar un documento guarda su
 * `_elementor_data` original y permite revertirlo. Un elemento pasa al widget `digitalisimo-*`
 * sólo si todos sus ajustes traducidos existen allí; si conserva estilos que sólo ofrece el
 * adaptador, se queda en él con los ajustes ya traducidos. Nunca toca otros sitios de la red.
 */
final class Migration {
	const PAGE   = 'digitalisimo-ep-migration';
	const ACTION = 'digitalisimo_ep_migration';
	const BACKUP = '_digitalisimo_ep_backup';
	const BATCH  = 20;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	public static function menu() {
		add_management_page( 'Migrar Element Pack', 'Migrar Element Pack', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
	}

	/** IDs de documentos del sitio que contienen widgets de Element Pack. */
	public static function documents( $limit = 0 ) {
		global $wpdb;
		$sql = "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND ( meta_value LIKE %s OR meta_value LIKE %s ) ORDER BY post_id";
		if ( $limit ) {
			$sql .= ' LIMIT ' . absint( $limit );
		}
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, '%' . $wpdb->esc_like( '"widgetType":"bdt-' ) . '%', '%' . $wpdb->esc_like( '"widgetType":"fooevents-calendar"' ) . '%' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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

	private static function data( $post_id ) {
		$raw  = get_post_meta( $post_id, '_elementor_data', true );
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
	 * Traduce un elemento. Devuelve el elemento y si pasó al widget propio.
	 *
	 * @return array{0:array,1:string} elemento y estado: native, adapter o skip.
	 */
	public static function convert_element( array $element, $target_controls = null ) {
		$type = (string) ( $element['widgetType'] ?? '' );
		$map  = Translator::map( $type );
		if ( ! $map ) {
			return array( $element, 'skip' );
		}
		$settings = Translator::translate( $type, is_array( $element['settings'] ?? null ) ? $element['settings'] : array() );
		$controls = null !== $target_controls ? $target_controls : self::target_controls( $map['target'] );
		$native   = is_array( $controls );
		foreach ( array_keys( $settings ) as $key ) {
			if ( Translator::MARKER === $key || '__dynamic__' === $key || '__globals__' === $key || 0 === strpos( (string) $key, '_' ) ) {
				continue;
			}
			if ( ! $native || ! isset( $controls[ $key ] ) ) {
				$native = false;
				break;
			}
		}
		if ( $native ) {
			unset( $settings[ Translator::MARKER ] );
			$element['widgetType'] = $map['target'];
		}
		$element['settings'] = $settings;
		return array( $element, $native ? 'native' : 'adapter' );
	}

	/** Controles del widget propio (incluidas variantes responsivas), o null si no está registrado. */
	private static function target_controls( $target ) {
		static $cache = array();
		if ( ! array_key_exists( $target, $cache ) ) {
			$widget           = class_exists( '\Elementor\Plugin' ) ? \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $target ) : null;
			$cache[ $target ] = $widget ? (array) $widget->get_controls() : null;
		}
		return $cache[ $target ];
	}

	/** Convierte un documento y guarda una copia del original la primera vez. */
	public static function convert_document( $post_id ) {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$stats = array( 'native' => 0, 'adapter' => 0, 'skip' => 0 );
		$data  = self::walk( self::data( $post_id ), static function ( $element ) use ( &$stats ) {
			if ( ! Translator::is_legacy_id( $element['widgetType'] ?? '' ) || isset( $element['settings'][ Translator::MARKER ] ) ) {
				return $element;
			}
			list( $element, $state ) = self::convert_element( $element );
			$stats[ $state ]++;
			return $element;
		} );
		if ( ! $stats['native'] && ! $stats['adapter'] ) {
			return $stats;
		}
		if ( '' === (string) get_post_meta( $post_id, self::BACKUP, true ) ) {
			add_post_meta( $post_id, self::BACKUP, wp_slash( wp_json_encode( array( 'time' => time(), 'data' => $raw ) ) ), true );
		}
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		self::clear_css( $post_id );
		return $stats;
	}

	public static function revert_document( $post_id ) {
		$backup = json_decode( (string) get_post_meta( $post_id, self::BACKUP, true ), true );
		if ( ! is_array( $backup ) || ! isset( $backup['data'] ) || ! is_string( $backup['data'] ) ) {
			return false;
		}
		update_post_meta( $post_id, '_elementor_data', wp_slash( $backup['data'] ) );
		delete_post_meta( $post_id, self::BACKUP );
		self::clear_css( $post_id );
		return true;
	}

	private static function clear_css( $post_id ) {
		delete_post_meta( $post_id, '_elementor_css' );
		delete_post_meta( $post_id, '_elementor_element_cache' );
		if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			\Elementor\Core\Files\CSS\Post::create( $post_id )->delete();
		}
	}

	/** Documentos con widgets de Element Pack aún sin traducir. */
	private static function pending() {
		$pending = array();
		foreach ( self::documents() as $post_id ) {
			$found = false;
			self::walk( self::data( $post_id ), static function ( $element ) use ( &$found ) {
				$type = (string) ( $element['widgetType'] ?? '' );
				if ( Translator::is_legacy_id( $type ) && Translator::map( $type ) && ! isset( $element['settings'][ Translator::MARKER ] ) ) {
					$found = true;
				}
				return $element;
			} );
			if ( $found ) {
				$pending[] = $post_id;
			}
		}
		return $pending;
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No autorizado.', 403 );
		}
		check_admin_referer( self::ACTION . '_' . get_current_blog_id() );
		$task  = sanitize_key( wp_unslash( $_POST['task'] ?? '' ) );
		$count = 0;
		$stats = array( 'native' => 0, 'adapter' => 0 );
		if ( 'convert' === $task ) {
			foreach ( array_slice( self::pending(), 0, self::BATCH ) as $post_id ) {
				$result = self::convert_document( $post_id );
				$stats['native']  += $result['native'] ?? 0;
				$stats['adapter'] += $result['adapter'] ?? 0;
				$count++;
			}
		} elseif ( 'revert' === $task ) {
			foreach ( array_slice( self::backups(), 0, self::BATCH ) as $post_id ) {
				$count += self::revert_document( $post_id ) ? 1 : 0;
			}
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'done' => $task, 'count' => $count, 'native' => $stats['native'], 'adapter' => $stats['adapter'] ), admin_url( 'tools.php' ) ) );
		exit;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$inventory = self::inventory();
		$pending   = count( self::pending() );
		$backups   = count( self::backups() );
		$active    = Translator::element_pack_active();
		echo '<div class="wrap"><h1>Migrar Element Pack</h1>';
		echo '<p>Las páginas creadas con Element Pack siguen funcionando sin él gracias a los adaptadores de DIGITALÍSIMO Elements. Esta herramienta guarda esos documentos ya traducidos al formato propio: antes conserva una copia del original para poder revertir. Sólo afecta a este sitio.</p>';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sólo muestra el resultado de la acción.
		if ( isset( $_GET['done'] ) ) {
			$done = sanitize_key( wp_unslash( $_GET['done'] ) );
			$msg  = 'convert' === $done
				? sprintf( 'Se tradujeron %d documentos: %d elementos pasaron al widget propio y %d siguen en su adaptador porque conservan estilos de Element Pack.', absint( $_GET['count'] ?? 0 ), absint( $_GET['native'] ?? 0 ), absint( $_GET['adapter'] ?? 0 ) )
				: sprintf( 'Se restauraron %d documentos a su versión original.', absint( $_GET['count'] ?? 0 ) );
			echo '<div class="notice notice-success"><p>' . esc_html( $msg ) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( $active ) {
			echo '<div class="notice notice-warning"><p>Element Pack sigue activo en este sitio: mientras lo esté, sus widgets se muestran con su propio código y los adaptadores no intervienen.</p></div>';
		}
		echo '<table class="widefat striped" style="max-width:860px"><thead><tr><th>Widget de Element Pack</th><th>Elementos</th><th>Documentos</th><th>Estado</th></tr></thead><tbody>';
		if ( ! $inventory ) {
			echo '<tr><td colspan="4">Este sitio no usa widgets de Element Pack.</td></tr>';
		}
		foreach ( $inventory as $type => $row ) {
			$target = $row['map'] ? Translator::target( $type ) : '';
			$status = $row['map'] ? 'Compatible: se muestra con ' . $target : 'Sin adaptador todavía: no se mostrará sin Element Pack';
			echo '<tr><td><code>' . esc_html( $type ) . '</code></td><td>' . absint( $row['elements'] ) . '</td><td>' . absint( $row['documents'] ) . '</td><td>' . esc_html( $status ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p>' . esc_html( sprintf( 'Documentos pendientes de traducir: %d. Documentos con copia original: %d.', $pending, $backups ) ) . '</p>';
		foreach ( array( 'convert' => array( 'Traducir el siguiente lote', $pending, 'button-primary' ), 'revert' => array( 'Restaurar el siguiente lote al original', $backups, 'button-secondary' ) ) as $task => $button ) {
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
}
