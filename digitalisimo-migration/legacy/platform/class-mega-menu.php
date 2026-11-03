<?php
namespace Digitalisimo\Elements\Legacy\Platform;

defined( 'ABSPATH' ) || exit;

/**
 * Mega Menu de Element Pack (§3.D.5 y V.11).
 *
 * El widget `bdt-mega-menu` se convierte (mapa del frente B) en el widget Menú con el mismo menú de WordPress: la
 * navegación se conserva. Lo que no tiene equivalente son los desplegables con contenido: el contenido de cada uno
 * (`ep_megamenu_content`, ya convertido por la etapa `convert`, que no filtra el tipo) se copia aquí a una plantilla de
 * biblioteca «Mega menú: <ítem> (<menú>)», `container` o `section` según su raíz, sin condiciones ni efecto en el
 * front, y el informe de cada documento con el widget dice cómo rehacerlo (`plat.mega.rebuild`). Nunca se construye el
 * mega menú anidado. Los originales no se tocan.
 *
 * Datos del origen: la opción `_ep_mega_menu_options` (`_ep_mega_menu_settings` → `menu_location_<term_id>` →
 * `ep_megamenu_enabled`), la meta JSON `_ep_mega_menu_item_settings` de cada ítem (`menu_enable`) y el contenido
 * vinculado por título `bdt-ep-megamenu-content-<ID del ítem>`.
 */
final class Mega_Menu {
	const OPTION       = '_ep_mega_menu_options';
	const ITEM_META    = '_ep_mega_menu_item_settings';
	const CONTENT_TYPE = 'ep_megamenu_content';
	const CONTENT      = 'bdt-ep-megamenu-content-';
	const KIND         = 'mega';
	const WIDGET       = 'bdt-mega-menu';

	/** @var array<int, array<string, int>> contenidos por título, por sitio. */
	private static $titles = array();

	/** Ítems con desplegable activado en menús habilitados: `[ { item, menu, title, menu_name, content, settings } ]`. */
	public static function items(): array {
		$options  = get_option( self::OPTION, array() );
		$settings = is_array( $options ) && is_array( $options['_ep_mega_menu_settings'] ?? null ) ? $options['_ep_mega_menu_settings'] : array();
		$out      = array();
		foreach ( $settings as $key => $menu_settings ) {
			if ( ! preg_match( '/^menu_location_(\d+)$/', (string) $key, $m ) || ! is_array( $menu_settings ) || ! self::on( $menu_settings['ep_megamenu_enabled'] ?? '' ) ) {
				continue;
			}
			$menu = get_term( (int) $m[1], 'nav_menu' );
			if ( ! $menu || is_wp_error( $menu ) ) {
				continue;
			}
			foreach ( (array) wp_get_nav_menu_items( (int) $menu->term_id, array( 'post_status' => 'publish' ) ) as $item ) {
				$item_id = (int) ( $item->ID ?? 0 );
				$meta    = json_decode( (string) get_post_meta( $item_id, self::ITEM_META, true ), true );
				if ( ! $item_id || ! is_array( $meta ) || ! self::on( $meta['menu_enable'] ?? '' ) ) {
					continue;
				}
				$out[] = array(
					'item'      => $item_id,
					'menu'      => (int) $menu->term_id,
					'slug'      => (string) $menu->slug,
					'title'     => Inventory::plain( (string) ( $item->title ?? '' ), 80 ),
					'menu_name' => Inventory::plain( (string) $menu->name, 80 ),
					'content'   => self::content( $item_id ),
					'settings'  => $meta,
				);
			}
		}
		return $out;
	}

	private static function on( $value ): bool {
		return in_array( $value, array( 1, '1', true, 'on', 'yes' ), true );
	}

	/** Contenido vinculado a un ítem (publicado), o 0. */
	private static function content( int $item_id ): int {
		$blog = (int) get_current_blog_id();
		if ( ! isset( self::$titles[ $blog ] ) ) {
			// Como el origen: el primero publicado con ese título (se leen una vez por petición y sitio).
			$by_title = array();
			$ids      = get_posts(
				array(
					'post_type'        => self::CONTENT_TYPE,
					'post_status'      => 'publish',
					'numberposts'      => 2000,
					'fields'           => 'ids',
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'suppress_filters' => true,
				)
			);
			foreach ( (array) $ids as $id ) {
				$post  = get_post( (int) $id );
				$title = $post ? (string) $post->post_title : '';
				if ( '' !== $title && ! isset( $by_title[ $title ] ) ) {
					$by_title[ $title ] = (int) $id;
				}
			}
			self::$titles[ $blog ] = $by_title;
		}
		return self::$titles[ $blog ][ self::CONTENT . $item_id ] ?? 0;
	}

	/** Vacía la caché de la petición (pruebas). */
	public static function flush(): void {
		self::$titles = array();
	}

	/** Elementos guardados de un documento. */
	private static function data( int $post_id ): array {
		$raw  = get_metadata( 'post', $post_id, '_elementor_data', true );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		return is_array( $data ) ? $data : array();
	}

	/** Lo perdido de un ítem: ancho, insignia, icono o carga diferida. */
	private static function lost( array $settings ): array {
		$out = array();
		if ( ! empty( $settings['menu_width_type'] ) && 'default' !== $settings['menu_width_type'] ) {
			$out[] = __( 'ancho', 'digitalisimo-migration' );
		}
		if ( '' !== trim( (string) ( $settings['menu_badge_label'] ?? '' ) ) ) {
			$out[] = __( 'insignia', 'digitalisimo-migration' );
		}
		if ( ! empty( $settings['megamenu_icon'] ) ) {
			$out[] = __( 'icono', 'digitalisimo-migration' );
		}
		if ( self::on( $settings['megamenu_ajax_load'] ?? '' ) ) {
			$out[] = __( 'carga diferida', 'digitalisimo-migration' );
		}
		return $out;
	}

	/** Análisis previo: plantillas que se crearán y desplegables vacíos. */
	public static function analyze(): array {
		$out = array();
		foreach ( self::items() as $item ) {
			$name = sprintf( '%1$s (%2$s)', $item['title'], $item['menu_name'] );
			if ( ! $item['content'] || ! self::data( $item['content'] ) ) {
				$out[] = array( 'plat.mega.empty', array( 'name' => $name ) );
			} elseif ( ! self::done( $item['item'] ) ) {
				$out[] = array( 'plat.mega.template', array( 'name' => $name ) );
			}
		}
		return $out;
	}

	private static function done( int $item_id ): bool {
		$row = Inventory::reg_get( self::KIND, (string) $item_id );
		return $row && in_array( $row['state'] ?? '', array( 'done', 'draft' ), true ) && Inventory::exists( (int) ( $row['dest'] ?? 0 ) );
	}

	public static function prepare( array &$cursor, float $deadline ): bool {
		return true;
	}

	/**
	 * Etapa `platform` (el contenido ya está convertido): una plantilla por desplegable con contenido y el informe de
	 * cada documento con el widget.
	 */
	public static function finish( array &$cursor, float $deadline ): bool {
		$items = self::items();
		if ( empty( $cursor['templates'] ) ) {
			foreach ( $items as $item ) {
				if ( $item['item'] <= (int) ( $cursor['after'] ?? 0 ) ) {
					continue;
				}
				if ( Inventory::late( $deadline ) ) {
					return false;
				}
				self::template( $item );
				$cursor['after'] = $item['item'];
			}
			$cursor['templates'] = 1;
		}
		self::report_documents( $items );
		return true;
	}

	/** Crea la plantilla de un desplegable (si tiene contenido y aún no existe). */
	private static function template( array $item ): int {
		$name = sprintf( '%1$s (%2$s)', $item['title'], $item['menu_name'] );
		if ( self::done( $item['item'] ) ) {
			return (int) Inventory::reg_get( self::KIND, (string) $item['item'] )['dest'];
		}
		$data = $item['content'] ? self::data( $item['content'] ) : array();
		if ( ! $data ) {
			Inventory::site_code( 'plat.mega.empty', array( 'name' => $name ) );
			return 0;
		}
		$tag  = self::KIND . ':' . $item['item'];
		$dest = Inventory::created( 'elementor_library', $tag );
		if ( ! $dest ) {
			if ( ! class_exists( __NAMESPACE__ . '\\Documents' ) ) {
				Inventory::site_code( 'plat.mega.failed', array( 'name' => $name ) );
				return 0;
			}
			$type = 'container' === ( $data[0]['elType'] ?? '' ) ? 'container' : 'section';
			Inventory::reg_put( self::KIND, (string) $item['item'], array( 'state' => 'pending' ) );
			$dest = (int) Documents::create(
				$type,
				array( 'post_title' => sprintf( __( 'Mega menú: %1$s (%2$s)', 'digitalisimo-migration' ), $item['title'], $item['menu_name'] ) ),
				array(
					'_elementor_data'      => (string) get_metadata( 'post', $item['content'], '_elementor_data', true ),
					'_elementor_edit_mode' => 'builder',
					'_elementor_version'   => (string) get_metadata( 'post', $item['content'], '_elementor_version', true ),
					Inventory::TAG         => $tag,
				),
				array( 'origin' => 'migration', 'part' => 'mega-menu', 'source' => $item['item'] )
			);
			if ( ! $dest ) {
				Inventory::site_code( 'plat.mega.failed', array( 'name' => $name ) );
				return 0;
			}
		}
		// Sin condiciones no tiene efecto en el front: publicada, el widget Plantilla puede elegirla.
		if ( 'publish' !== get_post_status( $dest ) ) {
			wp_publish_post( $dest );
		}
		Inventory::reg_put(
			self::KIND,
			(string) $item['item'],
			array(
				'dest'  => $dest,
				'hash'  => self::hash( $dest ),
				'state' => 'done',
				'extra' => array( 'menu' => $item['menu'] ),
			)
		);
		Inventory::site_code( 'plat.mega.template', array( 'name' => $name, 'id' => $dest ) );
		return $dest;
	}

	/** Huella del contenido de una plantilla creada. */
	public static function hash( int $id ): string {
		if ( class_exists( __NAMESPACE__ . '\\Documents' ) && method_exists( __NAMESPACE__ . '\\Documents', 'hash' ) ) {
			return (string) Documents::hash( $id );
		}
		return md5( (string) get_metadata( 'post', $id, '_elementor_data', true ) );
	}

	/** Por documento con el widget (hoy o en su copia original): el menú, sus plantillas y lo perdido. */
	private static function report_documents( array $items ): void {
		$by_menu = array();
		foreach ( $items as $item ) {
			$row  = Inventory::reg_get( self::KIND, (string) $item['item'] );
			$slug = $item['slug'];
			$info = $by_menu[ $slug ] ?? array( 'templates' => array(), 'lost' => array() );

			$info['lost'] = array_merge( $info['lost'], self::lost( $item['settings'] ) );
			if ( $row && ! empty( $row['dest'] ) ) {
				$info['templates'][] = (int) $row['dest'];
			}
			$by_menu[ $slug ] = $info;
		}
		foreach ( self::widget_documents() as $post_id => $slugs ) {
			foreach ( $slugs as $slug ) {
				$info  = $by_menu[ $slug ] ?? array( 'templates' => array(), 'lost' => array() );
				$count = count( $info['templates'] );
				Inventory::doc_code(
					$post_id,
					'plat.mega.rebuild',
					array(
						'name'  => '' !== $slug ? $slug : __( 'sin menú', 'digitalisimo-migration' ),
						'count' => $count,
						'note'  => $count ? Inventory::plain( sprintf( __( 'Plantillas: %s', 'digitalisimo-migration' ), implode( ', ', $info['templates'] ) ), 200 ) : '',
					)
				);
				if ( $info['lost'] ) {
					Inventory::doc_code( $post_id, 'plat.mega.lost', array( 'name' => $slug, 'note' => implode( ', ', array_unique( $info['lost'] ) ) ) );
				}
			}
		}
	}

	/** Documentos con el widget, hoy o en su copia original: `post_id => [ slug del menú, … ]`. */
	public static function widget_documents(): array {
		$out = array();
		foreach ( Inventory::documents_with( self::WIDGET, true ) as $post_id ) {
			$slugs = array();
			$data  = self::data( $post_id );
			$copy  = json_decode( (string) get_metadata( 'post', $post_id, '_digitalisimo_ep_backup', true ), true );
			if ( is_array( $copy ) && is_string( $copy['data'] ?? null ) ) {
				$original = json_decode( $copy['data'], true );
				$data     = is_array( $original ) ? $original : $data;
			}
			self::collect_slugs( $data, $slugs );
			if ( $slugs ) {
				$out[ $post_id ] = array_values( array_unique( $slugs ) );
			}
		}
		return $out;
	}

	private static function collect_slugs( array $elements, array &$slugs ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( self::WIDGET === ( $element['widgetType'] ?? '' ) ) {
				$slugs[] = Inventory::plain( (string) ( $element['settings']['navbar'] ?? '' ), 60 );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::collect_slugs( $element['elements'], $slugs );
			}
		}
	}

	/** `--revert`: retira las plantillas creadas que nadie cambió. */
	public static function revert( bool $force = false ): array {
		$out = array();
		foreach ( Inventory::reg_all( self::KIND ) as $source => $row ) {
			$dest = (int) ( $row['dest'] ?? 0 );
			if ( ! Inventory::exists( $dest ) ) {
				Inventory::reg_forget( self::KIND, (string) $source );
				continue;
			}
			if ( ! $force && self::hash( $dest ) !== (string) ( $row['hash'] ?? '' ) ) {
				$out[] = array( 'core.revert_skipped', array( 'type' => 'elementor_library', 'id' => $dest ) );
				continue;
			}
			Inventory::retire_post( $dest );
			Inventory::reg_forget( self::KIND, (string) $source );
			$out[] = array( 'plat.mega.reverted', array( 'id' => $dest ) );
		}
		return $out;
	}
}
