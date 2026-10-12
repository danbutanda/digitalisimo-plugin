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

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
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

	/** IDs de documentos del sitio que contienen widgets de Element Pack. */
	public static function documents( $limit = 0 ) {
		global $wpdb;
		$needles = array( '"widgetType":"bdt-', '"widgetType":"fooevents-calendar"', '"widgetType":"lightbox"', '"ep_', '"element_pack_', '"bdt_' );
		$sql     = "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND ( " . implode( ' OR ', array_fill( 0, count( $needles ), 'meta_value LIKE %s' ) ) . ' ) ORDER BY post_id';
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
	 * Convierte un documento al formato propio y guarda una copia del original la primera vez.
	 *
	 * @return array{native:int} número de elementos convertidos.
	 */
	public static function convert_document( $post_id ) {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array( 'native' => 0 );
		}
		// Los widgets (y con ellos los adaptadores) se registran la primera vez que Elementor los pide.
		if ( class_exists( '\\Elementor\\Plugin' ) ) {
			\Elementor\Plugin::$instance->widgets_manager->get_widget_types();
		}
		$data    = self::data( $post_id );
		$changes = 0;
		foreach ( $data as $index => $element ) {
			if ( is_array( $element ) ) {
				list( $data[ $index ], $count ) = Native::element( $element, $post_id );
				$changes += $count;
			}
		}
		if ( ! $changes ) {
			return array( 'native' => 0 );
		}
		if ( '' === (string) get_post_meta( $post_id, self::BACKUP, true ) ) {
			add_post_meta( $post_id, self::BACKUP, wp_slash( wp_json_encode( array( 'time' => time(), 'data' => $raw ) ) ), true );
		}
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		self::clear_css( $post_id );
		return array( 'native' => $changes );
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

	/** Documentos que aún contienen widgets o ajustes heredados. */
	public static function pending() {
		$pending = array();
		foreach ( self::documents() as $post_id ) {
			foreach ( self::data( $post_id ) as $element ) {
				if ( is_array( $element ) && Native::is_legacy( $element ) ) {
					$pending[] = $post_id;
					break;
				}
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
		$stats = array( 'native' => 0 );
		if ( 'convert' === $task && ! Translator::element_pack_active() ) {
			foreach ( array_slice( self::pending(), 0, self::BATCH ) as $post_id ) {
				$result = self::convert_document( $post_id );
				$stats['native'] += $result['native'] ?? 0;
				$count++;
			}
		} elseif ( 'revert' === $task ) {
			foreach ( array_slice( self::backups(), 0, self::BATCH ) as $post_id ) {
				$count += self::revert_document( $post_id ) ? 1 : 0;
			}
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'done' => $task, 'count' => $count, 'native' => $stats['native'] ), admin_url( 'tools.php' ) ) );
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
		echo '<p>Mientras este plugin esté activo, las páginas creadas con Element Pack se siguen viendo sin él. La conversión deja cada documento sólo con widgets y ajustes de DIGITALÍSIMO Elements (los estilos que sólo existían en Element Pack pasan al CSS personalizado del elemento) y guarda una copia del original para poder restaurarlo. Sólo afecta a este sitio.</p>';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sólo muestra el resultado de la acción.
		if ( isset( $_GET['done'] ) ) {
			$done = sanitize_key( wp_unslash( $_GET['done'] ) );
			$msg  = 'convert' === $done
				? sprintf( 'Se convirtieron %d documentos (%d elementos): ya sólo usan widgets y ajustes propios.', absint( $_GET['count'] ?? 0 ), absint( $_GET['native'] ?? 0 ) )
				: sprintf( 'Se restauraron %d documentos a su versión original.', absint( $_GET['count'] ?? 0 ) );
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
		echo '<p>' . esc_html( sprintf( 'Documentos pendientes de convertir: %d. Documentos con copia original: %d.', $pending, $backups ) ) . '</p>';
		if ( ! $pending && ! $inventory ) {
			echo '<div class="notice notice-success inline"><p>Este sitio ya no tiene contenido heredado. Cuando todos los sitios estén así y revisados, puedes desinstalar Element Pack y DIGITALÍSIMO Migración.</p></div>';
		}
		foreach ( array( 'convert' => array( 'Convertir el siguiente lote', $active ? 0 : $pending, 'button-primary' ), 'revert' => array( 'Restaurar el siguiente lote al original', $backups, 'button-secondary' ) ) as $task => $button ) {
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
			$pending = count( self::pending() );
			$name    = get_bloginfo( 'name' );
			$url     = home_url( '/' );
			$tool    = admin_url( 'tools.php?page=' . self::PAGE );
			restore_current_blog();
			$total_missing += count( $missing );
			echo '<tr><td><strong>' . esc_html( $name ) . '</strong><br><code>' . esc_html( $url ) . '</code></td><td>' . absint( $compatible ) . '</td><td>' . ( $missing ? esc_html( implode( ', ', $missing ) ) : '—' ) . '</td><td>' . absint( $pending ) . '</td><td><a href="' . esc_url( $tool ) . '">Abrir la herramienta del sitio</a></td></tr>';
		}
		echo '</tbody></table>';
		echo '<p>' . esc_html( $total_missing ? 'Hay widgets sin adaptador: la herramienta de cada sitio explica el motivo y la alternativa de cada uno.' : 'Ningún sitio usa widgets de Element Pack sin adaptador.' ) . '</p>';
		echo '</div>';
	}
}
