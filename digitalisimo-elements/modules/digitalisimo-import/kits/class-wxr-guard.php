<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Guardas del WXR del kit (§5.4, §8.2, V-7, V-18, V-20, V-21, CV-1).
 *
 * El importador WXR del núcleo añade con `add_post_meta` todas las metas salvo tres, hace `maybe_unserialize` de lo
 * que trae y no deduplica nada. K, sólo dentro del contexto:
 * - quita de **toda** entrada (también de los elementos de menú, que sólo ve `wp_import_post_data_raw`) los
 *   serializados con objetos y la lista negra de §8.2: un `_elementor_data` en el WXR sería contenido sin kses;
 * - reescribe el prefijo `home` del origen en `_menu_item_url` y en `post_content` (CC-6);
 * - quita correo e IP de los comentarios;
 * - reutiliza lo que el mismo kit ya importó sin cambios y deja su par viejo → nuevo para los menús (CV-1);
 * - anota `_digitalisimo_import_origin` en lo que crea.
 * La misma lista negra se aplica al exportar (`wxr_export_skip_*`).
 */
final class Wxr_Guard {
	/** @var array|null Entrada del WXR en curso: `{ id, type, url, hash }`. */
	private static $current = null;

	/**
	 * Nombre de una meta para el informe: las internas del constructor (`_elementor_*`) se nombran por lo que son, sin
	 * su prefijo, que la pantalla «Kits» mostraría tal cual.
	 */
	public static function public_key( string $key ): string {
		if ( 0 === strpos( $key, '_elementor' ) ) {
			return 'datos internos del constructor';
		}
		return substr( $key, 0, 64 );
	}

	/** ¿Está la meta en la lista negra (§8.2)? La única lista es `Export\Meta_Blacklist`, también al exportar. */
	public static function blacklisted( string $key ): bool {
		return Export\Meta_Blacklist::blocks( $key );
	}

	/** `wxr_export_skip_postmeta` y `wxr_export_skip_termmeta` (10). */
	public static function skip_meta( $skip, $key = '' ) {
		return $skip || ( is_string( $key ) && self::blacklisted( $key ) );
	}

	/**
	 * `wp_import_posts` (10): deduplicación (CV-1). Quita lo que el mismo kit ya importó con el mismo ID de origen y
	 * sin cambios, y guarda su par viejo → nuevo.
	 */
	public static function posts( $posts ) {
		if ( ! is_array( $posts ) ) {
			return $posts;
		}
		$kept  = array();
		$pairs = array();
		$types = array();
		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) || empty( $post['post_id'] ) || empty( $post['post_type'] ) ) {
				$kept[] = $post;
				continue;
			}
			$clean = self::sanitize( $post, false );
			$hash  = Session::wxr_hash( $clean );
			$type  = (string) $post['post_type'];
			$found = apply_filters( Module::FILTER_EXISTING_DOCUMENT, null, $type, (int) $post['post_id'], array( 'hash' => $hash ) );
			if ( is_int( $found ) && $found > 0 ) {
				$pairs[ (int) $post['post_id'] ]          = $found;
				$types[ $type ][ (int) $post['post_id'] ] = $found;
				continue;
			}
			$kept[] = $post;
		}
		if ( $pairs ) {
			Context::note(
				'wxr',
				array(
					'pairs' => $pairs,
					'types' => $types,
				)
			);
			Context::report( 'wxr.reused', array( 'count' => count( $pairs ) ) );
		}
		return $kept;
	}

	/**
	 * `wp_import_post_data_raw` (10): el filtro principal (V-21). Lo ven todas las entradas, también los menús.
	 */
	public static function raw( $post ) {
		if ( ! is_array( $post ) ) {
			return $post;
		}
		$post          = self::sanitize( $post, true );
		self::$current = array(
			'id'   => isset( $post['post_id'] ) ? (int) $post['post_id'] : 0,
			'type' => isset( $post['post_type'] ) ? (string) $post['post_type'] : '',
			'url'  => ! empty( $post['attachment_url'] ) ? (string) $post['attachment_url'] : ( isset( $post['guid'] ) ? (string) $post['guid'] : '' ),
			'hash' => Session::wxr_hash( $post ),
		);
		return $post;
	}

	/**
	 * Quita metas de la lista negra y serializados con objetos, en la entrada y en sus comentarios, y reescribe el
	 * prefijo `home` en `_menu_item_url`.
	 *
	 * @param array $post   Entrada del WXR.
	 * @param bool  $report ¿Anotar lo quitado?
	 */
	private static function sanitize( array $post, bool $report ): array {
		$origin = self::origin();
		if ( isset( $post['postmeta'] ) && is_array( $post['postmeta'] ) ) {
			$meta = array();
			foreach ( $post['postmeta'] as $item ) {
				$key = is_array( $item ) && isset( $item['key'] ) ? (string) $item['key'] : '';
				if ( '' === $key || self::blacklisted( $key ) ) {
					if ( $report ) {
						Context::report( 'wxr.blacklist', array( 'key' => self::public_key( $key ) ), Part_Context::WARNING );
					}
					continue;
				}
				$value = isset( $item['value'] ) ? $item['value'] : '';
				if ( ! self::safe_value( $value ) ) {
					if ( $report ) {
						Context::report( 'wxr.object', array( 'key' => self::public_key( $key ) ), Part_Context::WARNING );
					}
					continue;
				}
				if ( '_menu_item_url' === $key && is_string( $value ) ) {
					$item['value'] = Normalizer::rewrite_urls( $value, $origin );
				}
				$meta[] = $item;
			}
			$post['postmeta'] = $meta;
		}
		if ( isset( $post['comments'] ) && is_array( $post['comments'] ) ) {
			foreach ( $post['comments'] as $index => $comment ) {
				if ( ! is_array( $comment ) ) {
					continue;
				}
				$post['comments'][ $index ] = self::anonymize_comment( $comment );
			}
		}
		return $post;
	}

	/** ¿Se puede guardar el valor? Un serializado con objetos nunca. */
	public static function safe_value( $value ): bool {
		if ( is_string( $value ) && Normalizer::is_serialized( $value ) ) {
			return null !== Normalizer::unserialize( $value );
		}
		return ! Normalizer::has_object( $value );
	}

	/** Comentario sin correo ni IP y sin metas con objetos. */
	private static function anonymize_comment( array $comment ): array {
		$comment['comment_author_email'] = '';
		$comment['comment_author_IP']    = '';
		if ( isset( $comment['commentmeta'] ) && is_array( $comment['commentmeta'] ) ) {
			$comment['commentmeta'] = array_values(
				array_filter(
					$comment['commentmeta'],
					function ( $meta ) {
						return is_array( $meta ) && isset( $meta['key'] ) && self::safe_value( $meta['value'] ?? '' );
					}
				)
			);
		}
		return $comment;
	}

	/**
	 * `wp_import_post_data_processed` (10): en «agregar», el estado de D8; en los dos modos, el prefijo `home` del
	 * origen en `post_content` (CC-6).
	 */
	public static function processed( $postdata, $post = array() ) {
		if ( ! is_array( $postdata ) ) {
			return $postdata;
		}
		$type = isset( $postdata['post_type'] ) ? (string) $postdata['post_type'] : '';
		if ( isset( $postdata['post_status'] ) && in_array( $postdata['post_status'], array( 'publish', 'future' ), true ) && Context::should_draft( $type, '' ) ) {
			$postdata['post_status'] = 'draft';
		}
		if ( isset( $postdata['post_content'] ) && is_string( $postdata['post_content'] ) && '' !== $postdata['post_content'] ) {
			$postdata['post_content'] = self::rewrite_content( $postdata['post_content'] );
		}
		// El importador del núcleo no pasa las fechas del WXR (`wp-import.php`, `$postdata`): sin ellas, cada entrada
		// llegaría con la fecha de la importación y el orden de un blog o de un bucle cambiaría.
		foreach ( array( 'post_date', 'post_date_gmt' ) as $field ) {
			$date = is_array( $post ) && isset( $post[ $field ] ) && is_string( $post[ $field ] ) ? $post[ $field ] : '';
			if ( ! isset( $postdata[ $field ] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date ) && '0000-00-00 00:00:00' !== $date ) {
				$postdata[ $field ] = $date;
			}
		}
		return $postdata;
	}

	/** Cambia el prefijo `home` (y `site`) del origen por `home_url()` dentro de un texto. */
	public static function rewrite_content( string $content ): string {
		$origin = self::origin();
		$home   = rtrim( (string) home_url(), '/' );
		foreach ( array_unique( array_filter( array( $origin['home'] ?? '', $origin['site'] ?? '' ) ) ) as $prefix ) {
			$prefix = rtrim( (string) $prefix, '/' );
			if ( '' === $prefix || $prefix === $home ) {
				continue;
			}
			$content = (string) preg_replace( '#' . preg_quote( $prefix, '#' ) . '(?=[/"\'?\#\s<)]|$)#', $home, $content );
		}
		return $content;
	}

	/** `wp_import_post_meta` (10): segunda comprobación de objetos y de la lista negra. */
	public static function post_meta( $meta, $post_id = 0, $post = array() ) {
		return self::filter_meta( $meta );
	}

	/** `wp_import_term_meta` (10): la lista negra y los objetos en las metas de término. */
	public static function term_meta( $meta, $term_id = 0, $term = array() ) {
		return self::filter_meta( $meta );
	}

	private static function filter_meta( $meta ) {
		if ( ! is_array( $meta ) ) {
			return $meta;
		}
		return array_values(
			array_filter(
				$meta,
				function ( $item ) {
					return is_array( $item ) && isset( $item['key'] ) && ! self::blacklisted( (string) $item['key'] ) && self::safe_value( $item['value'] ?? '' );
				}
			)
		);
	}

	/** `wp_import_post_comments` (10): comentarios sin correo ni IP. */
	public static function comments( $comments, $post_id = 0, $post = array() ) {
		if ( ! is_array( $comments ) ) {
			return $comments;
		}
		foreach ( $comments as $index => $comment ) {
			if ( is_array( $comment ) ) {
				$comments[ $index ] = self::anonymize_comment( $comment );
			}
		}
		return $comments;
	}

	/** `wp_import_insert_post` (10): procedencia de lo que crea el WXR. */
	public static function inserted( $post_id, $original_id = 0, $postdata = array(), $post = array() ): void {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || null === self::$current || (int) $original_id !== self::$current['id'] ) {
			return;
		}
		Context::created( $post_id, self::$current['type'], (int) $original_id, self::$current['hash'] );
		self::$current = null;
	}

	/** `add_attachment` (10): procedencia de los adjuntos del WXR y su URL vieja → ID nuevo. */
	public static function attachment( $post_id ): void {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || null === self::$current || 'attachment' !== self::$current['type'] ) {
			return;
		}
		Context::created( $post_id, 'attachment', self::$current['id'], self::$current['hash'] );
		if ( '' !== self::$current['url'] ) {
			Context::note( 'wxr', array( 'attachments' => array( self::$current['url'] => $post_id ) ) );
		}
		self::$current = null;
	}

	/** `wp_update_nav_menu_item` (10): procedencia de los elementos de menú del WXR. */
	public static function menu_item( $menu_id, $menu_item_db_id = 0, $args = array() ): void {
		$item = (int) $menu_item_db_id;
		if ( $item <= 0 || null === self::$current || 'nav_menu_item' !== self::$current['type'] ) {
			return;
		}
		Context::created( $item, 'nav_menu_item', self::$current['id'], self::$current['hash'] );
		self::$current = null;
	}

	/** `import_end`: la entrada en curso no sobrevive al WXR. */
	public static function end(): void {
		self::$current = null;
	}

	/**
	 * `…/import/wp-content/query-args/customization` (20): los pares de lo reutilizado pasan al importador, para que
	 * los menús que apuntan a ello sigan resolviendo (los elementos de menú se importan al final).
	 */
	public static function query_args( $args, $post_type = '', $customization = null ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}
		$pairs = self::pairs();
		if ( $pairs ) {
			$args['posts'] = ( isset( $args['posts'] ) && is_array( $args['posts'] ) ? $args['posts'] : array() ) + $pairs;
		}
		return $args;
	}

	/** Pares viejo → nuevo de lo que la deduplicación reutilizó en la sesión. */
	public static function pairs(): array {
		$pairs  = array();
		$record = Context::record();
		if ( null !== $record && isset( $record['wxr']['pairs'] ) && is_array( $record['wxr']['pairs'] ) ) {
			$pairs = $record['wxr']['pairs'];
		}
		$notes = Context::notes();
		if ( isset( $notes['wxr']['pairs'] ) && is_array( $notes['wxr']['pairs'] ) ) {
			$pairs = $notes['wxr']['pairs'] + $pairs;
		}
		return array_map( 'intval', $pairs );
	}

	/** Pares reutilizados de un tipo de entrada (para que el resumen del runner y el mapa del núcleo los cuenten). */
	public static function pairs_for( string $type ): array {
		$pairs  = array();
		$record = Context::record();
		if ( null !== $record && isset( $record['wxr']['types'][ $type ] ) && is_array( $record['wxr']['types'][ $type ] ) ) {
			$pairs = $record['wxr']['types'][ $type ];
		}
		$notes = Context::notes();
		if ( isset( $notes['wxr']['types'][ $type ] ) && is_array( $notes['wxr']['types'][ $type ] ) ) {
			$pairs = $notes['wxr']['types'][ $type ] + $pairs;
		}
		return array_map( 'intval', $pairs );
	}

	/** Prefijos del origen de la sesión activa. */
	private static function origin(): array {
		$record = Context::record();
		return null !== $record && isset( $record['origin'] ) && is_array( $record['origin'] ) ? $record['origin'] : array();
	}
}
