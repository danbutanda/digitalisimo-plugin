<?php
namespace Digitalisimo\Elements\Legacy\Platform;

defined( 'ABSPATH' ) || exit;

/**
 * Inventario de la plataforma de Element Pack que no se convierte (frente H, §3.D.8 y §3.D.9) y utilidades comunes de
 * los pasos de plataforma del frente H.
 *
 * Sólo informa, nunca copia ni borra:
 * - contraseñas de aplicación «Element Pack AI — …» de los usuarios miembros del sitio (nombre, fechas; nunca el
 *   valor ni el identificador), con el enlace a su perfil del sitio en curso (V.7);
 * - nombres de las claves de servicios guardadas (nunca sus valores), la autorización de Google y los ajustes de IA;
 * - shortcodes `[ep_*]` en el contenido y en los widgets de texto;
 * - copias dentro de la carpeta del constructor, tablas y carpetas residuales, desplazamiento suave y WPML.
 *
 * Utilidades: estado de la tarea (quién la lanzó y qué plugins estaban activos al empezar), informe y registro con
 * llamadas protegidas (el informe y el registro son del frente A), y la retirada de lo creado al revertir.
 */
final class Inventory {
	const JOB = 'digitalisimo_migration_job';
	/** Meta con la procedencia de cada entrada creada por la migración: `<tipo>:<origen>` (reanudar sin duplicar). */
	const TAG = '_digitalisimo_migration_source';
	/** Contraseñas de aplicación de la IA de Element Pack. */
	const APP_PASSWORD = '/^Element Pack AI\b/i';
	/** Documentos listados con shortcodes: el resto se cuenta. */
	const MAX_SHORTCODE_DOCS = 100;

	/* ================================================================ Utilidades comunes */

	/** Tarea del sitio en curso (vacía si no hay). */
	public static function job(): array {
		$job = function_exists( 'get_option' ) ? get_option( self::JOB, array() ) : array();
		return is_array( $job ) ? $job : array();
	}

	/** Usuario que lanzó la tarea (o la de la red): 0 si no se sabe (WP-CLI sin `--user`). */
	public static function job_user(): int {
		return max( 0, (int) ( self::job()['user'] ?? 0 ) );
	}

	/**
	 * ¿Estaba activo al empezar algún miembro de un grupo (`element-pack` o `prime-slider`), en cualquier ámbito? `null`
	 * si la tarea no lo anotó (fuera de una tarea).
	 */
	public static function group_active_at_start( string $group ): ?bool {
		$plugins = self::job()['plugins'] ?? null;
		if ( ! is_array( $plugins ) || ! $plugins ) {
			return null;
		}
		foreach ( $plugins as $file => $info ) {
			if ( is_array( $info ) && $group === self::group_of( (string) $file, $info ) && ( ! empty( $info['site'] ) || ! empty( $info['network'] ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** Grupo de un plugin anotado por la tarea: el que declara `Retire::PLUGINS` o, si no, por archivo y nombre. */
	private static function group_of( string $file, array $info ): string {
		if ( isset( $info['group'] ) && is_string( $info['group'] ) && '' !== $info['group'] ) {
			return $info['group'];
		}
		$base  = basename( $file );
		$label = (string) ( $info['label'] ?? '' );
		if ( ! empty( $info['element_pack'] ) || 0 === strpos( $base, 'bdthemes-element-pack' ) || preg_match( '/^Element Pack\b/i', $label ) ) {
			return 'element-pack';
		}
		if ( 0 === strpos( $base, 'bdthemes-prime-slider' ) || preg_match( '/^Prime Slider\b/i', $label ) ) {
			return 'prime-slider';
		}
		return '';
	}

	/** ¿Está Element Pack cargado en esta petición? */
	public static function ep_loaded(): bool {
		if ( class_exists( '\\Digitalisimo\\Elements\\Legacy\\Translator', false ) ) {
			return (bool) \Digitalisimo\Elements\Legacy\Translator::element_pack_active();
		}
		return defined( 'BDTEP_VER' );
	}

	/** ¿Está Prime Slider cargado en esta petición? */
	public static function ps_loaded(): bool {
		return defined( 'BDTPS_CORE_VER' );
	}

	/** ¿Estaba Element Pack activo al empezar (V.0.13)? Fuera de una tarea, si está cargado ahora. */
	public static function ep_was_active(): bool {
		$active = self::group_active_at_start( 'element-pack' );
		return null === $active ? self::ep_loaded() : $active;
	}

	/** ¿Estaba Prime Slider activo al empezar? Fuera de una tarea, si está cargado ahora. */
	public static function ps_was_active(): bool {
		$active = self::group_active_at_start( 'prime-slider' );
		return null === $active ? self::ps_loaded() : $active;
	}

	/** ¿Se acabó el tiempo de esta petición? */
	public static function late( float $deadline ): bool {
		return microtime( true ) >= $deadline;
	}

	/** Un código del sitio en el informe (sin informe cargado, nada). */
	public static function site_code( string $code, array $detail = array() ): void {
		if ( class_exists( '\\Digitalisimo\\Elements\\Legacy\\Report', false ) ) {
			\Digitalisimo\Elements\Legacy\Report::site( $code, $detail );
		}
	}

	/** Un código de un documento en el informe. */
	public static function doc_code( int $post_id, string $code, array $detail = array() ): void {
		if ( $post_id > 0 && class_exists( '\\Digitalisimo\\Elements\\Legacy\\Report', false ) ) {
			\Digitalisimo\Elements\Legacy\Report::add( $post_id, $code, $detail );
		}
	}

	/** Documento que convierte el contexto en curso (0 fuera de una conversión). */
	public static function context_post(): int {
		return class_exists( '\\Digitalisimo\\Elements\\Legacy\\Context', false ) ? \Digitalisimo\Elements\Legacy\Context::post_id() : 0;
	}

	/** ¿Conversión pura (editor o análisis)? */
	public static function pure(): bool {
		return class_exists( '\\Digitalisimo\\Elements\\Legacy\\Context', false ) && \Digitalisimo\Elements\Legacy\Context::pure();
	}

	private static function registry(): bool {
		return class_exists( __NAMESPACE__ . '\\Registry' );
	}

	/** Fila del registro (`{ dest, hash, state, extra }`) o null. */
	public static function reg_get( string $kind, string $source ): ?array {
		if ( ! self::registry() ) {
			return null;
		}
		$row = Registry::get( $kind, $source );
		return is_array( $row ) ? $row : null;
	}

	public static function reg_put( string $kind, string $source, array $row ): void {
		if ( self::registry() ) {
			Registry::put( $kind, $source, $row + array( 'dest' => 0, 'hash' => '', 'state' => 'pending', 'extra' => array() ) );
		}
	}

	/** Todas las filas de un tipo: origen => fila. */
	public static function reg_all( string $kind ): array {
		return self::registry() ? (array) Registry::all( $kind ) : array();
	}

	public static function reg_forget( string $kind, string $source ): void {
		if ( self::registry() && method_exists( __NAMESPACE__ . '\\Registry', 'forget' ) ) {
			Registry::forget( $kind, $source );
		}
	}

	/**
	 * Entrada ya creada por la migración para un origen (`<tipo>:<origen>`), aunque la petición muriera antes de
	 * anotarla en el registro. 0 si no hay.
	 */
	public static function created( string $post_type, string $tag ): int {
		$ids = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts'      => 1,
				'fields'           => 'ids',
				'meta_key'         => self::TAG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $tag, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/** ¿Existe una entrada (no en la papelera)? */
	public static function exists( int $id ): bool {
		if ( $id <= 0 ) {
			return false;
		}
		$post = get_post( $id );
		return $post && 'trash' !== ( $post->post_status ?? '' );
	}

	/**
	 * Retira lo creado por la migración: a la papelera si `EMPTY_TRASH_DAYS` lo permite; si no, a borrador (nunca un
	 * borrado definitivo, V.8). Devuelve `trash` o `draft`.
	 */
	public static function retire_post( int $id ): string {
		$days = defined( 'EMPTY_TRASH_DAYS' ) ? (int) EMPTY_TRASH_DAYS : 30;
		if ( $days > 0 && function_exists( 'wp_trash_post' ) && wp_trash_post( $id ) ) {
			return 'trash';
		}
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);
		return 'draft';
	}

	/** Texto plano y corto para el detalle de un código. */
	public static function plain( $text, int $max = 100 ): string {
		$text = trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', strip_tags( (string) $text ) ) );
		if ( function_exists( 'mb_substr' ) && mb_strlen( $text, 'UTF-8' ) > $max ) {
			return mb_substr( $text, 0, $max - 1, 'UTF-8' ) . '…';
		}
		return $text;
	}

	/** IDs de documentos (no revisiones) cuyo `_elementor_data` o copia original contienen un texto. */
	public static function documents_with( string $needle, bool $backup = false, int $limit = 500 ): array {
		global $wpdb;
		$keys = $backup ? "( '_elementor_data', '_digitalisimo_ep_backup' )" : "( '_elementor_data' )";
		$sql  = "SELECT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key IN {$keys} AND p.post_type <> 'revision' AND m.meta_value LIKE %s ORDER BY m.post_id LIMIT " . absint( $limit );
		$ids  = (array) $wpdb->get_col( $wpdb->prepare( $sql, '%' . $wpdb->esc_like( $needle ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/* ================================================================ Paso de plataforma */

	/** Análisis previo: lo mismo que `finish()`, sin escribir nada. */
	public static function analyze(): array {
		return self::collect();
	}

	/** Etapa `platform`: añade al informe lo que no se convierte. Siempre termina en una petición. */
	public static function finish( array &$cursor, float $deadline ): bool {
		foreach ( self::collect() as $pair ) {
			if ( isset( $pair[2] ) && $pair[2] > 0 ) {
				self::doc_code( (int) $pair[2], $pair[0], $pair[1] );
			} else {
				self::site_code( $pair[0], $pair[1] );
			}
		}
		$cursor['done'] = 1;
		return true;
	}

	/** Nada que revertir: sólo informa. */
	public static function revert( bool $force = false ): array {
		return array();
	}

	/**
	 * Lo que se informa: `[ code, detail, post_id? ]`. Sin escrituras.
	 *
	 * @return array<int, array{0:string, 1:array, 2?:int}>
	 */
	public static function collect(): array {
		$out = array();
		foreach ( self::app_passwords() as $row ) {
			$out[] = array(
				'plat.app_passwords',
				array(
					'name'  => $row['user'] . ': ' . implode( ', ', $row['names'] ),
					'id'    => $row['user_id'],
					'count' => count( $row['names'] ),
					'note'  => self::dates_note( $row ),
				),
			);
		}
		$keys = self::api_key_names();
		if ( $keys ) {
			$out[] = array( 'plat.api_keys', array( 'count' => count( $keys ), 'note' => implode( ', ', $keys ) ) );
		}
		if ( self::filled( get_option( 'element_pack_google_oauth_data', '' ) ) ) {
			$out[] = array( 'plat.api_oauth', array() );
		}
		if ( self::filled( get_option( 'element_pack_ai_settings', '' ) ) ) {
			$out[] = array( 'plat.ai_settings', array() );
		}
		$snapshots = self::meta_count( '_ep_ai_undo_snapshot' );
		if ( $snapshots ) {
			$out[] = array( 'plat.ai_snapshots', array( 'count' => $snapshots ) );
		}
		foreach ( self::shortcode_documents() as $post_id ) {
			$out[] = array( 'plat.shortcodes', array(), $post_id );
		}
		$widgets = self::shortcode_widgets();
		if ( $widgets ) {
			$out[] = array( 'plat.shortcodes.widgets', array( 'count' => $widgets ) );
		}
		$core = array();
		foreach ( array( 'element_pack_shape_divider_copied' => __( 'separadores', 'digitalisimo-migration' ), 'element_pack_mask_shapes_copied' => __( 'máscaras', 'digitalisimo-migration' ) ) as $option => $label ) {
			if ( self::filled( get_option( $option, '' ) ) ) {
				$core[] = $label;
			}
		}
		if ( $core ) {
			$out[] = array( 'plat.core_files', array( 'note' => implode( ', ', $core ) ) );
		}
		$other = get_option( 'element_pack_other_settings', array() );
		if ( is_array( $other ) && 'on' === ( $other['smooth-scroller'] ?? '' ) ) {
			$out[] = array( 'plat.smooth_scroll', array() );
		}
		$residue = self::residue();
		if ( $residue ) {
			$out[] = array( 'plat.residue', array( 'count' => count( $residue ), 'note' => implode( ', ', $residue ) ) );
		}
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			$out[] = array( 'plat.wpml', array() );
		}
		return $out;
	}

	private static function filled( $value ): bool {
		if ( is_array( $value ) ) {
			return (bool) array_filter( $value, array( __CLASS__, 'filled' ) );
		}
		return '' !== trim( (string) $value ) && 'off' !== (string) $value;
	}

	private static function dates_note( array $row ): string {
		$fmt   = static function ( $time ) {
			return $time ? gmdate( 'Y-m-d', (int) $time ) : '—';
		};
		$parts = array();
		foreach ( $row['items'] as $item ) {
			$parts[] = sprintf( __( 'creada %1$s, último uso %2$s', 'digitalisimo-migration' ), $fmt( $item['created'] ), $fmt( $item['last_used'] ) );
		}
		return self::plain( implode( '; ', $parts ), 200 );
	}

	/**
	 * Contraseñas de aplicación de la IA de Element Pack de los usuarios miembros de este sitio: `{ user_id, user,
	 * names[], items[{created, last_used}] }`. Nunca lleva el valor, el UUID ni la IP.
	 */
	public static function app_passwords(): array {
		$args = array(
			'meta_key' => '_application_passwords', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'fields'   => array( 'ID', 'display_name' ),
			'number'   => 500,
		);
		if ( is_multisite() ) {
			$args['blog_id'] = get_current_blog_id();
		}
		$out = array();
		foreach ( (array) get_users( $args ) as $user ) {
			$user_id = (int) ( is_object( $user ) ? $user->ID : $user );
			if ( is_multisite() && function_exists( 'is_user_member_of_blog' ) && ! is_user_member_of_blog( $user_id, get_current_blog_id() ) ) {
				continue;
			}
			$row = self::user_app_passwords( $user_id, is_object( $user ) ? (string) $user->display_name : '' );
			if ( $row ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	private static function user_app_passwords( int $user_id, string $display ): ?array {
		$all   = get_user_meta( $user_id, '_application_passwords', true );
		$names = array();
		$items = array();
		foreach ( is_array( $all ) ? $all : array() as $item ) {
			$name = is_array( $item ) ? (string) ( $item['name'] ?? '' ) : '';
			if ( ! preg_match( self::APP_PASSWORD, $name ) ) {
				continue;
			}
			$names[] = self::plain( $name, 80 );
			$items[] = array( 'created' => (int) ( $item['created'] ?? 0 ), 'last_used' => (int) ( $item['last_used'] ?? 0 ) );
		}
		if ( ! $names ) {
			return null;
		}
		$user = self::plain( $display, 60 );
		return array( 'user_id' => $user_id, 'user' => '' !== $user ? $user : '#' . $user_id, 'names' => $names, 'items' => $items );
	}

	/**
	 * Para la pantalla de red: las contraseñas de cada usuario una sola vez, aunque sea miembro de varios sitios
	 * (V.7, C1-34). Sólo para un superadministrador.
	 */
	public static function network_app_passwords(): array {
		if ( ! is_multisite() || ! is_super_admin() ) {
			return array();
		}
		$out = array();
		foreach ( (array) get_users( array( 'blog_id' => 0, 'meta_key' => '_application_passwords', 'fields' => array( 'ID', 'display_name' ), 'number' => 1000 ) ) as $user ) { // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$user_id = (int) ( is_object( $user ) ? $user->ID : $user );
			if ( isset( $out[ $user_id ] ) ) {
				continue;
			}
			$row = self::user_app_passwords( $user_id, is_object( $user ) ? (string) $user->display_name : '' );
			if ( $row ) {
				$out[ $user_id ] = $row;
			}
		}
		return array_values( $out );
	}

	/** Enlace a la sección de contraseñas de aplicación del perfil, en el sitio en curso o en la red. */
	public static function profile_link( int $user_id, string $where = 'site' ): string {
		$path = 'user-edit.php?user_id=' . $user_id . '#application-passwords-section';
		return 'network' === $where ? network_admin_url( $path ) : admin_url( $path );
	}

	/**
	 * Lista HTML de las contraseñas (cada campo escapado). En una red, los nombres sólo los ve un superadministrador;
	 * el administrador del sitio ve el recuento (V.7).
	 */
	public static function app_passwords_html( array $rows, string $where = 'site' ): string {
		if ( ! $rows ) {
			return '';
		}
		$names = is_multisite() ? is_super_admin() : current_user_can( 'list_users' );
		if ( ! $names ) {
			$count = 0;
			foreach ( $rows as $row ) {
				$count += count( (array) $row['names'] );
			}
			return '<p class="digi-migrate__app-passwords">' . esc_html( sprintf( __( 'Contraseñas de aplicación de la IA de Element Pack en este sitio: %d. Pide a quien administra la red que las revise.', 'digitalisimo-migration' ), $count ) ) . '</p>';
		}
		$link = 'network' === $where || ( is_multisite() && is_super_admin() ) ? 'network' : 'site';
		$html = '<ul class="digi-migrate__app-passwords">';
		foreach ( $rows as $row ) {
			$html .= '<li><a href="' . esc_url( self::profile_link( (int) $row['user_id'], $link ) ) . '">' . esc_html( (string) $row['user'] ) . '</a>: ' . esc_html( implode( ', ', (array) $row['names'] ) ) . ' <span class="digi-migrate__detail">(' . esc_html( self::dates_note( $row ) ) . ')</span></li>';
		}
		return $html . '</ul>';
	}

	/** Nombres (nunca valores) de las claves no vacías de los servicios de Element Pack. */
	public static function api_key_names(): array {
		$settings = get_option( 'element_pack_api_settings', array() );
		$names    = array();
		foreach ( is_array( $settings ) ? $settings : array() as $key => $value ) {
			$key = (string) preg_replace( '/[^a-z0-9_]/', '', strtolower( strip_tags( (string) $key ) ) );
			if ( '' !== $key && self::filled( $value ) ) {
				$names[] = $key;
			}
		}
		sort( $names );
		return array_slice( array_values( array_unique( $names ) ), 0, 30 );
	}

	private static function meta_count( string $key ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", $key ) );
	}

	/** Documentos con shortcodes `[ep_*]` en el contenido (no revisiones ni papelera), con tope. */
	public static function shortcode_documents(): array {
		global $wpdb;
		$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ( 'revision', 'nav_menu_item' ) AND post_status NOT IN ( 'trash', 'auto-draft' ) AND post_content LIKE %s ORDER BY ID LIMIT " . self::MAX_SHORTCODE_DOCS;
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, '%' . $wpdb->esc_like( '[ep_' ) . '%' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Widgets de texto o HTML con shortcodes `[ep_*]`. */
	private static function shortcode_widgets(): int {
		$count = 0;
		foreach ( array( 'widget_text', 'widget_custom_html' ) as $option ) {
			foreach ( (array) get_option( $option, array() ) as $instance ) {
				if ( is_array( $instance ) && ( false !== strpos( (string) ( $instance['text'] ?? '' ), '[ep_' ) || false !== strpos( (string) ( $instance['content'] ?? '' ), '[ep_' ) ) ) {
					++$count;
				}
			}
		}
		return $count;
	}

	/** Restos que la migración no borra: sólo sus nombres. */
	private static function residue(): array {
		global $wpdb;
		$out = array();
		$like = $wpdb->esc_like( $wpdb->prefix . 'ep_template_library_' ) . '%';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ) ) {
			$out[] = __( 'tablas de la biblioteca de plantillas', 'digitalisimo-migration' );
		}
		$uploads = wp_upload_dir( null, false );
		$base    = empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ? trailingslashit( (string) $uploads['basedir'] ) : '';
		if ( '' !== $base ) {
			foreach ( array( 'element-pack' => __( 'archivos en uploads/element-pack', 'digitalisimo-migration' ), 'element-pack-backups' => __( 'copias en uploads/element-pack-backups', 'digitalisimo-migration' ), 'prime-slider/sets' => __( 'archivos en uploads/prime-slider/sets', 'digitalisimo-migration' ) ) as $dir => $label ) {
				if ( is_dir( $base . $dir ) ) {
					$out[] = $label;
				}
			}
		}
		if ( self::filled( get_option( 'element_pack_license_key', '' ) ) ) {
			$out[] = __( 'licencia', 'digitalisimo-migration' );
		}
		if ( self::filled( get_option( 'ep_white_label_title', '' ) ) || self::filled( get_option( 'element_pack_white_label', '' ) ) ) {
			$out[] = __( 'marca blanca', 'digitalisimo-migration' );
		}
		$rooten = get_posts( array( 'post_type' => 'bdt-custom-template', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => true ) );
		if ( $rooten ) {
			$out[] = __( 'plantillas del tema Rooten', 'digitalisimo-migration' );
		}
		return $out;
	}
}
