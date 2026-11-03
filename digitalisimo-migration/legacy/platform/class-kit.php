<?php
namespace Digitalisimo\Elements\Legacy\Platform;

defined( 'ABSPATH' ) || exit;

/**
 * Pestaña del kit de Element Pack y sus ajustes heredados en las páginas (§3.D.6, F8-D22).
 *
 * - **Kit (10.0.1):** los valores de `element_pack_lightbox_*`, `_cb_*`, `_toolbar_*`, `_navigation_*` y
 *   `_global_tooltip_*` se informan (sólo cuántos y de qué grupo, nunca su valor) y se retiran del
 *   `_elementor_page_settings` del kit activo. No se copian a la caja de luz del núcleo: ya rige todas las del sitio.
 * - **Menú contextual:** `ep_context_menu_*` y `menus` (éste sólo junto a `ep_context_menu_enable`) se informan y se
 *   retiran; los `menu_*` sin prefijo sólo se informan.
 * - **Documentos (9.9.1):** las mismas claves en páginas y plantillas, con `clean_page()`, que llama la conversión del
 *   documento (frente A) y escribe ella su copia `_digitalisimo_ep_backup_page`.
 *
 * El respaldo del kit va en su propia meta `_digitalisimo_ep_backup_page`, escrita antes de cambiar nada y sólo la
 * primera vez. Las metas se escriben con `wp_slash()`: `update_metadata()` quita una barra invertida de cada cadena del
 * array, y el kit guarda CSS (`content:"\f105"`) y textos con barras.
 */
final class Kit {
	const BACKUP = '_digitalisimo_ep_backup_page';
	const KIND   = 'kit';
	/** Claves de la pestaña del kit, por grupo informado. */
	const GROUPS = array(
		'lightbox'   => '/^element_pack_(lightbox_|cb_|toolbar_|navigation_)/',
		'tooltip'    => '/^element_pack_global_tooltip_/',
		'context'    => '/^ep_context_menu_/',
	);
	const MENU_STYLES = '/^menu_/';

	/**
	 * Claves que se retiran de unos ajustes y su grupo: `key => group`. `menus` sólo con el menú contextual presente.
	 */
	public static function legacy_keys( array $settings ): array {
		$out = array();
		foreach ( $settings as $key => $value ) {
			foreach ( self::GROUPS as $group => $pattern ) {
				if ( preg_match( $pattern, (string) $key ) ) {
					$out[ (string) $key ] = $group;
					break;
				}
			}
		}
		if ( array_key_exists( 'ep_context_menu_enable', $settings ) && array_key_exists( 'menus', $settings ) ) {
			$out['menus'] = 'context';
		}
		return $out;
	}

	/** ¿Tiene valor? (Lo vacío no se informa, pero también se retira.) */
	private static function filled( $value ): bool {
		// Un control con unidad (deslizador, dimensiones) sin medida está vacío aunque guarde la unidad.
		if ( is_array( $value ) && array_key_exists( 'unit', $value ) ) {
			foreach ( array( 'size', 'top', 'right', 'bottom', 'left' ) as $key ) {
				if ( '' !== trim( (string) ( is_scalar( $value[ $key ] ?? '' ) ? ( $value[ $key ] ?? '' ) : '' ) ) ) {
					return true;
				}
			}
			return false;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( self::filled( $item ) ) {
					return true;
				}
			}
			return false;
		}
		return '' !== trim( (string) $value );
	}

	/** Recuento de valores no vacíos por grupo. */
	private static function counts( array $settings, array $keys ): array {
		$out = array();
		foreach ( $keys as $key => $group ) {
			if ( self::filled( $settings[ $key ] ?? '' ) ) {
				$out[ $group ] = ( $out[ $group ] ?? 0 ) + 1;
			}
		}
		return $out;
	}

	/** `menu_*` sin prefijo con valor (estilos del menú contextual): sólo se informan. */
	private static function menu_styles( array $settings ): int {
		if ( ! array_key_exists( 'ep_context_menu_enable', $settings ) ) {
			return 0;
		}
		$count = 0;
		foreach ( $settings as $key => $value ) {
			if ( preg_match( self::MENU_STYLES, (string) $key ) && self::filled( $value ) ) {
				++$count;
			}
		}
		return $count;
	}

	/** Códigos de unos ajustes: `[ [code, detail], … ]`. */
	private static function codes( array $settings, array $keys, string $values_code ): array {
		$out    = array();
		$counts = self::counts( $settings, $keys );
		$values = ( $counts['lightbox'] ?? 0 ) + ( $counts['tooltip'] ?? 0 );
		if ( $values ) {
			$groups = array();
			if ( ! empty( $counts['lightbox'] ) ) {
				$groups[] = __( 'caja de luz', 'digitalisimo-migration' );
			}
			if ( ! empty( $counts['tooltip'] ) ) {
				$groups[] = __( 'información emergente', 'digitalisimo-migration' );
			}
			$out[] = array( $values_code, array( 'count' => $values, 'note' => implode( ', ', $groups ) ) );
		}
		if ( ! empty( $counts['context'] ) ) {
			$out[] = array( 'plat.kit.context_menu', array( 'count' => $counts['context'] ) );
		}
		$styles = self::menu_styles( $settings );
		if ( $styles ) {
			$out[] = array( 'plat.kit.context_menu_styles', array( 'count' => $styles ) );
		}
		return $out;
	}

	/**
	 * Ajustes de página de un documento sin las claves globales de Element Pack (9.9.1). Pura: no escribe; la copia
	 * `_digitalisimo_ep_backup_page` la escribe quien guarda. El kit no pasa por aquí (lo trata `finish()`).
	 */
	public static function clean_page( array $ps, int $post_id ): array {
		if ( $post_id > 0 && 'kit' === (string) get_post_meta( $post_id, '_elementor_template_type', true ) ) {
			return $ps;
		}
		$keys = self::legacy_keys( $ps );
		if ( ! $keys ) {
			return $ps;
		}
		foreach ( self::codes( $ps, $keys, 'plat.kit.page_values' ) as $pair ) {
			Inventory::doc_code( $post_id, $pair[0], $pair[1] );
		}
		return array_diff_key( $ps, $keys );
	}

	/** Kit activo del sitio, o 0. */
	public static function kit_id(): int {
		$id   = (int) get_option( 'elementor_active_kit', 0 );
		$post = $id ? get_post( $id ) : null;
		return $post && 'trash' !== ( $post->post_status ?? '' ) ? $id : 0;
	}

	private static function settings( int $id ): array {
		$settings = get_post_meta( $id, '_elementor_page_settings', true );
		return is_array( $settings ) ? $settings : array();
	}

	public static function analyze(): array {
		$id = self::kit_id();
		if ( ! $id ) {
			return array();
		}
		$settings = self::settings( $id );
		$keys     = self::legacy_keys( $settings );
		return $keys ? self::codes( $settings, $keys, 'plat.kit.values' ) : array();
	}

	public static function prepare( array &$cursor, float $deadline ): bool {
		return true;
	}

	/** Etapa `platform`: retira las claves del kit activo, con respaldo previo. */
	public static function finish( array &$cursor, float $deadline ): bool {
		$id = self::kit_id();
		if ( ! $id ) {
			return true;
		}
		$settings = self::settings( $id );
		$keys     = self::legacy_keys( $settings );
		$clean    = array_diff_key( $settings, $keys );
		$icons    = Icons::rewrite_settings( $clean );
		$tags     = Dynamic_Tags::rewrite_settings( $icons );
		if ( ! $keys && $tags === $settings ) {
			return true;
		}
		$own = '' === (string) get_post_meta( $id, self::BACKUP, true );
		if ( $own ) {
			add_post_meta( $id, self::BACKUP, wp_slash( (string) wp_json_encode( array( 'time' => time(), 'data' => $settings ) ) ), true );
		}
		$previous  = Inventory::reg_get( self::KIND, (string) $id );
		$rewritten = (array) ( $previous['extra']['rewritten'] ?? array() );
		foreach ( $tags as $key => $value ) {
			if ( array_key_exists( $key, $clean ) && $clean[ $key ] !== $value ) {
				$rewritten[ (string) $key ] = md5( (string) wp_json_encode( $value ) );
			}
		}
		update_post_meta( $id, '_elementor_page_settings', wp_slash( $tags ) );
		delete_post_meta( $id, '_elementor_css' );
		Inventory::reg_put(
			self::KIND,
			(string) $id,
			array(
				'dest'  => $id,
				'hash'  => md5( (string) wp_json_encode( $tags ) ),
				'state' => 'done',
				'extra' => array(
					'keys' => array_values( array_unique( array_merge( (array) ( $previous['extra']['keys'] ?? array() ), array_keys( $keys ) ) ) ),
					'own'       => $own || ! empty( $previous['extra']['own'] ),
					'rewritten' => $rewritten,
				),
			)
		);
		foreach ( self::codes( $settings, $keys, 'plat.kit.values' ) as $pair ) {
			Inventory::site_code( $pair[0], $pair[1] );
		}
		return true;
	}

	/**
	 * `--revert`: por clave, sólo lo que retiró la migración y hoy no existe (lo añadido o editado después se queda).
	 */
	public static function revert( bool $force = false ): array {
		$out = array();
		foreach ( Inventory::reg_all( self::KIND ) as $source => $row ) {
			$id     = (int) $source;
			$backup = json_decode( (string) get_post_meta( $id, self::BACKUP, true ), true );
			$data   = is_array( $backup ) && is_array( $backup['data'] ?? null ) ? $backup['data'] : null;
			if ( ! Inventory::exists( $id ) || null === $data ) {
				Inventory::reg_forget( self::KIND, (string) $source );
				continue;
			}
			$current  = self::settings( $id );
			$restored = 0;
			foreach ( (array) ( $row['extra']['keys'] ?? array() ) as $key ) {
				if ( array_key_exists( $key, $data ) && ! array_key_exists( $key, $current ) ) {
					$current[ $key ] = $data[ $key ];
					++$restored;
				}
			}
			// Iconos o etiquetas reescritos: vuelven si nadie los cambió después.
			foreach ( (array) ( $row['extra']['rewritten'] ?? array() ) as $key => $hash ) {
				if ( array_key_exists( $key, $data ) && array_key_exists( $key, $current ) && md5( (string) wp_json_encode( $current[ $key ] ) ) === $hash ) {
					$current[ $key ] = $data[ $key ];
					++$restored;
				}
			}
			if ( $restored ) {
				update_post_meta( $id, '_elementor_page_settings', wp_slash( $current ) );
				delete_post_meta( $id, '_elementor_css' );
			}
			if ( ! empty( $row['extra']['own'] ) ) {
				delete_post_meta( $id, self::BACKUP );
			}
			Inventory::reg_forget( self::KIND, (string) $source );
			$out[] = array( 'plat.kit.reverted', array( 'id' => $id, 'count' => $restored ) );
		}
		return $out;
	}
}
