<?php
/**
 * Firma de datos de un sitio para kit-roundtrip.sh (A1, A4, A7, CV-8, CV-15).
 *
 * Todo ID se escribe como lo que nombra, nunca como número: `post:<tipo>:<slug>`, `term:<tax>:<slug>`,
 * `user:<login>` y `media:<nombre>:<sha1 del archivo>`. Un ID que no resuelve es `unresolved:<id>`: como B desplaza sus
 * autoincrementos antes de importar (setup-b.php), un ID del origen sin traducir nunca coincide por azar. Las URL del
 * sitio pasan a `{{home}}`, `{{uploads}}` o `{{files}}`; si queda el host del origen (parámetro `origin`), se lista.
 *
 * Las rutas que llevan referencias salen de refs.json de A (las anotó setup-a.php al resolver cases.json) y, para los
 * documentos que A no creó (kits de terceros), de una lista fija de claves de §9.2: la firma no usa el registro de K.
 */

require_once __DIR__ . '/lib.php';

/** Claves de ajustes que guardan IDs aunque refs.json no las nombre, con su tipo (§9.2). */
function digi_k_reference_keys() {
	return array(
		'template_id'              => 'template',
		'digitalisimo_template_id' => 'template',
		'anywhere_id'              => 'post',
		'popup'                    => 'template',
		'post_id'                  => 'post',
		'rule_post_value'          => 'post',
		'rule_products_in_cart_value' => 'post',
		'rule_custom_page_id'      => 'post',
		'rule_user_value'          => 'user',
		'digitalisimo_author_user' => 'user',
		'rule_categories_in_cart_value' => 'term',
		'rule_tags_in_cart_value'  => 'term',
		'rule_current_category_page_value' => 'term',
		'rule_single_product_category_value' => 'term',
		'navbar'                   => 'menu',
		'nav_menu'                 => 'menu',
		'attachment_id'            => 'attachment',
		'thumbnail_id'             => 'attachment',
		'users'                    => 'user',
		'items'                    => 'post',
	);
}

/**
 * Claves de condiciones del motor nativo que guardan IDs, por condición (F7a): dentro de `e_display_conditions` la
 * misma clave (`pages`) puede ser una lista de IDs o el centinela `digitalisimo_f7a`, así que se nombra por condición.
 */
function digi_k_condition_reference_keys() {
	return array(
		'digitalisimo_page_visit'       => array( 'pages' => 'post' ),
		'digitalisimo_product_stock_of' => array( 'products' => 'post' ),
	);
}

final class Digi_K_Signature {
	/** @var string */
	private $home;
	/** @var string */
	private $uploads;
	/** @var string */
	private $uploads_dir;
	/** @var string host del origen que no debería quedar */
	private $origin;
	/** @var array refs.json de A indexado por documento y elemento */
	private $refs = array();
	/** @var array */
	public $origin_urls = array();
	/** @var array */
	public $unresolved = array();
	/** @var array */
	public $integrity = array();
	/** @var array */
	public $used_files = array();
	/** @var array caché de medios por ID */
	private $media = array();

	public function __construct( $origin, array $refs ) {
		$this->home        = untrailingslashit( home_url() );
		$up                = wp_upload_dir( null, false );
		$this->uploads     = untrailingslashit( $up['baseurl'] );
		$this->uploads_dir = untrailingslashit( $up['basedir'] );
		$this->origin      = strtolower( (string) $origin );
		foreach ( $refs as $ref ) {
			$this->refs[ $ref['doc'] ][ (string) $ref['element'] ][] = $ref;
		}
	}

	/**
	 * Clave estable de una entrada: tipo y título en forma de slug. El importador 3.0 crea las páginas y plantillas
	 * desde su título (no conserva post_name), así que el título es lo que coincide entre A y B.
	 */
	public static function post_key( $post ) {
		static $seen = array();
		$name = '' !== trim( (string) $post->post_title ) ? sanitize_title( $post->post_title ) : (string) $post->post_name;
		$key  = $post->post_type . ':' . $name;
		// Dos entradas con el mismo título (R4: B ya tenía «Sección del kit»): la de ID menor conserva la clave y la otra
		// lleva su slug, para no comparar un documento con otro.
		if ( ! isset( $seen[ $key ] ) ) {
			$first = get_posts( array( 'post_type' => $post->post_type, 'title' => $post->post_title, 'post_status' => 'any', 'posts_per_page' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids', 'suppress_filters' => true ) );
			$seen[ $key ] = $first ? (int) $first[0] : (int) $post->ID;
		}
		return $seen[ $key ] === (int) $post->ID || '' === trim( (string) $post->post_title ) ? $key : $key . '~' . $post->post_name;
	}

	/** Nombre estable de un adjunto: su archivo sin sufijo numérico y el sha1 de su contenido. */
	public function media( $id ) {
		$id = (int) $id;
		if ( ! $id ) {
			return '';
		}
		if ( isset( $this->media[ $id ] ) ) {
			return $this->media[ $id ];
		}
		$file = get_attached_file( $id );
		if ( ! $file || ! is_file( $file ) || 'attachment' !== get_post_type( $id ) ) {
			return $this->media[ $id ] = $this->unresolved( 'attachment', $id );
		}
		$base = preg_replace( '/-\d+(\.[a-z0-9]+)$/i', '$1', basename( $file ) );
		return $this->media[ $id ] = 'media:' . $base . ':' . self::file_sha1( $file );
	}

	/**
	 * sha1 de un archivo. Un SVG se compara en forma canónica (C14N, sin espacio entre etiquetas): al importarlo, el
	 * saneador del núcleo lo vuelve a serializar (CC-17; `<circle/>` pasa a `<circle></circle>`) sin cambiar el dibujo.
	 */
	public static function file_sha1( $file ) {
		if ( 'svg' !== strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
			return sha1_file( $file );
		}
		$svg = (string) file_get_contents( $file );
		$doc = new DOMDocument();
		if ( @$doc->loadXML( $svg, LIBXML_NONET ) && $doc->documentElement ) {
			$svg = (string) $doc->documentElement->C14N(); // Sin declaración y con cada elemento vacío en su forma larga.
		}
		return sha1( preg_replace( '/>\s+</', '><', trim( $svg ) ) );
	}

	private function unresolved( $kind, $id ) {
		$this->unresolved[] = $kind . ':' . $id;
		return 'unresolved:' . $id;
	}

	/** Un ID como lo que nombra. */
	public function sym( $kind, $id ) {
		if ( is_array( $id ) ) {
			return array_map( function ( $one ) use ( $kind ) {
				return $this->sym( $kind, $one );
			}, $id );
		}
		if ( '' === $id || null === $id || false === $id || ( is_string( $id ) && ! preg_match( '/^-?\d+$/', $id ) ) ) {
			return $id;
		}
		$id = (int) $id;
		if ( $id <= 0 ) {
			return $id;
		}
		switch ( $kind ) {
			case 'post':
			case 'template':
				$post = get_post( $id );
				if ( ! $post || ( 'template' === $kind && 'elementor_library' !== $post->post_type ) ) {
					return $this->unresolved( $kind, $id );
				}
				return 'post:' . self::post_key( $post );
			case 'term':
			case 'menu':
				$term = get_term( $id );
				if ( ! $term || is_wp_error( $term ) ) {
					return $this->unresolved( $kind, $id );
				}
				return 'term:' . $term->taxonomy . ':' . $term->slug;
			case 'user':
				// Los usuarios nunca viajan en un kit: en B, una referencia a un usuario es el valor de cierre (§5.5).
				$user = get_user_by( 'id', $id );
				return $user ? 'user:' . $user->user_login : 'user:sin-destino';
			case 'attachment':
				return $this->media( $id );
		}
		return $id;
	}

	/** Una URL del sitio como ficha estable; un archivo de uploads como su medio si es un adjunto. */
	public function url( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}
		if ( 0 === strpos( $value, $this->uploads . '/' ) ) {
			$relative = substr( $value, strlen( $this->uploads ) + 1 );
			if ( preg_match( '#^digitalisimo-elements/((?:shapes|masks)/[a-z0-9-]+\.svg)#', $relative, $m ) ) {
				$this->used_files[ $m[1] ] = true;
				return '{{files}}/' . $relative;
			}
			$clean = preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', strtok( $relative, '?#' ) );
			$id    = attachment_url_to_postid( $this->uploads . '/' . $clean );
			if ( $id ) {
				return $this->media( $id ) . ( $clean !== strtok( $relative, '?#' ) ? '@tamaño' : '' );
			}
			return '{{uploads}}/' . $relative;
		}
		return str_replace( array( $this->uploads, $this->home ), array( '{{uploads}}', '{{home}}' ), $value );
	}

	/** Normaliza un texto libre: URL del sitio, archivos propios y host del origen. */
	public function text( $value, $where ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}
		if ( preg_match_all( '#' . preg_quote( $this->uploads, '#' ) . '/digitalisimo-elements/((?:shapes|masks)/[a-z0-9-]+\.svg)#', $value, $m ) ) {
			foreach ( $m[1] as $file ) {
				$this->used_files[ $file ] = true;
			}
		}
		// Copia saneada de un SVG del sitio de una máscara o un patrón (F7a): el control oculto guarda la ruta relativa.
		if ( preg_match( '#^digitalisimo-elements/(masks/[a-z0-9]+(?:-[a-z0-9]+)*\.svg)$#', $value, $m ) ) {
			$this->used_files[ $m[1] ] = true;
		}
		$value = preg_replace_callback(
			'#' . preg_quote( $this->uploads, '#' ) . '/[^\s"\'()<>]+#',
			function ( $match ) {
				return $this->url( $match[0] );
			},
			$value
		);
		$value = str_replace( $this->home, '{{home}}', $value );
		if ( '' !== $this->origin && false !== stripos( $value, $this->origin ) ) {
			// Cada URL del origen que queda se anota aparte (origin_urls, que known.json declara una a una como
			// «url-de-A:<dónde>») y no repite la diferencia en el documento: §9.3 no reescribe el HTML libre.
			$this->origin_urls[] = $where;
			$value               = preg_replace( '#https?://' . preg_quote( $this->origin, '#' ) . '(?=[/"\'\s?\#]|$)#i', '{{home}}', $value );
		}
		return $value;
	}

	/** Valor en una ruta con puntos («tabs.1.template_id»), por referencia. */
	private static function &at( array &$data, $path ) {
		$null = null;
		$node = &$data;
		foreach ( explode( '.', $path ) as $key ) {
			if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
				return $null;
			}
			$node = &$node[ $key ];
		}
		return $node;
	}

	/** Aplica una referencia anotada en refs.json. */
	private function apply_ref( array &$settings, array $ref ) {
		if ( 0 === strpos( $ref['kind'], 'inline:' ) || 'url' === $ref['kind'] || 'desconocida' === $ref['kind'] ) {
			return;
		}
		$value = &self::at( $settings, $ref['path'] );
		if ( null === $value ) {
			return;
		}
		switch ( $ref['kind'] ) {
			case 'attachment':
				if ( is_array( $value ) ) {
					$value['id'] = $this->sym( 'attachment', $value['id'] ?? '' );
					unset( $value['url'] );
				} else {
					$value = $this->sym( 'attachment', $value );
				}
				break;
			case 'attachment-list':
				$value = $this->sym( 'attachment', array_filter( explode( ',', (string) $value ), 'strlen' ) );
				break;
			case 'media-url':
				$value = $this->url( $value );
				break;
			default:
				$value = $this->sym( $ref['kind'], $value );
		}
	}

	/** Etiqueta dinámica «[elementor-tag … settings="…"]» como estructura, con sus IDs nombrados. */
	private function dynamic( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/\[elementor-tag id="([^"]*)" name="([^"]*)" settings="([^"]*)"\]/', $value, $m ) ) {
			return $value;
		}
		$settings = json_decode( urldecode( $m[3] ), true );
		$settings = is_array( $settings ) ? $this->generic( $settings ) : $m[3];
		return array( 'tag' => $m[2], 'id' => $m[1], 'settings' => $settings );
	}

	/** Las claves de §9.2 que llevan IDs, también en datos que A no creó. */
	public function generic( array $settings, $doc = '' ) {
		$keys = digi_k_reference_keys();
		foreach ( $settings as $key => &$value ) {
			// Un valor de medio ({url, id, …}) de un control que refs.json no anotó (un kit de terceros con elementos
			// que A no crea, como `section`): el ID es de un adjunto.
			if ( is_array( $value ) && isset( $value['url'] ) && array_key_exists( 'id', $value ) && ! isset( $value['elType'] )
				&& ! array_diff( array_keys( $value ), array( 'url', 'id', 'size', 'alt', 'source', 'title' ) )
				&& ( is_int( $value['id'] ) || ( is_string( $value['id'] ) && preg_match( '/^\d+$/', $value['id'] ) ) ) ) {
				$value['id'] = $this->sym( 'attachment', $value['id'] );
			}
			if ( is_array( $value ) ) {
				// Sólo una lista de valores sueltos es una lista de IDs; un repetidor («items» de una línea de tiempo) no.
				$scalars = array_values( $value ) === $value && ! array_filter( $value, 'is_array' );
				$value   = isset( $keys[ $key ] ) && $scalars ? $this->sym( $keys[ $key ], $value ) : $this->generic( $value, $doc );
				continue;
			}
			if ( isset( $keys[ $key ] ) && ( is_int( $value ) || ( is_string( $value ) && preg_match( '/^\d+$/', $value ) ) ) ) {
				$value = $this->sym( $keys[ $key ], $value );
			} elseif ( 'ids' === $key && is_string( $value ) && preg_match( '/^\d+(,\d+)*$/', $value ) ) {
				$value = $this->sym( 'attachment', explode( ',', $value ) );
			} elseif ( is_string( $value ) ) {
				$value = $this->text( $value, $doc . ' · ' . $key );
			}
		}
		return $settings;
	}

	/** Ajustes de un elemento normalizados. */
	private function settings( array $settings, $doc, $element_id, array $ids ) {
		foreach ( (array) ( $this->refs[ $doc ][ $element_id ] ?? array() ) as $ref ) {
			$this->apply_ref( $settings, $ref );
		}
		if ( isset( $settings['e_display_conditions'] ) && is_array( $settings['e_display_conditions'] ) ) {
			$decoded = json_decode( (string) ( $settings['e_display_conditions'][0] ?? '[]' ), true );
			$settings['e_display_conditions'] = is_array( $decoded ) ? $this->generic( $this->condition_refs( $decoded ), $doc ) : $settings['e_display_conditions'];
		}
		if ( isset( $settings['__dynamic__'] ) && is_array( $settings['__dynamic__'] ) ) {
			foreach ( $settings['__dynamic__'] as $key => $tag ) {
				$settings['__dynamic__'][ $key ] = $this->dynamic( $tag );
			}
		}
		if ( isset( $settings['selected_element'] ) && '' !== $settings['selected_element'] && ! in_array( (string) $settings['selected_element'], $ids, true ) ) {
			$this->integrity[] = "{$doc} · {$element_id}: selected_element apunta a {$settings['selected_element']}, que no existe en el documento";
		}
		foreach ( array( 'shape_divider_top', 'shape_divider_bottom' ) as $key ) {
			if ( ! empty( $settings[ $key ] ) && 0 === strpos( (string) $settings[ $key ], 'digi-' ) ) {
				$slug = substr( (string) $settings[ $key ], 5 );
				$this->used_files[ 'shapes/' . $slug . '.svg' ] = true;
				if ( is_file( $this->uploads_dir . '/digitalisimo-elements/shapes/' . $slug . '-negative.svg' ) ) {
					$this->used_files[ 'shapes/' . $slug . '-negative.svg' ] = true;
				}
			}
		}
		return $this->generic( $settings, $doc . ' · ' . $element_id );
	}

	/** Grupos de `e_display_conditions` con los IDs de las condiciones de F7a nombrados (digi_k_condition_reference_keys). */
	private function condition_refs( array $groups ) {
		$keys = digi_k_condition_reference_keys();
		foreach ( $groups as $g => $group ) {
			foreach ( is_array( $group ) ? $group : array() as $c => $condition ) {
				$name = is_array( $condition ) ? (string) ( $condition['condition'] ?? '' ) : '';
				foreach ( $keys[ $name ] ?? array() as $key => $kind ) {
					if ( isset( $condition[ $key ] ) && is_array( $condition[ $key ] ) ) {
						$groups[ $g ][ $c ][ $key ] = $this->sym( $kind, array_values( $condition[ $key ] ) );
					}
				}
			}
		}
		return $groups;
	}

	/** IDs de elemento de un árbol. */
	private static function element_ids( array $elements, array &$ids ) {
		foreach ( $elements as $element ) {
			$ids[] = (string) ( $element['id'] ?? '' );
			self::element_ids( (array) ( $element['elements'] ?? array() ), $ids );
		}
	}

	/** Recuento por tipo de un árbol guardado dentro de un marcador. */
	private static function count_types( array $elements, array &$counts ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$type            = (string) ( $element['widgetType'] ?? $element['elType'] ?? '' );
			$counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1;
			self::count_types( (array) ( $element['elements'] ?? array() ), $counts );
		}
	}

	/** Árbol de elementos normalizado, con sus IDs y el recuento por tipo (un marcador cuenta como su tipo original). */
	public function elements( array $elements, $doc, array $ids, array &$counts ) {
		$out = array();
		foreach ( $elements as $element ) {
			$type = (string) ( $element['widgetType'] ?? $element['elType'] ?? '' );
			if ( 'digitalisimo-unconverted' === $type ) {
				// El marcador cuenta como su tipo original y, con él, todo lo que guarda dentro (nada se pierde, A4).
				$original = (array) ( $element['settings']['digitalisimo_unconverted']['element'] ?? array() );
				$type     = (string) ( $original['widgetType'] ?? $original['elType'] ?? 'marcador' );
				self::count_types( (array) ( $original['elements'] ?? array() ), $counts );
			}
			// El contenedor (o sección y columna) con el que se coloca un marcador donde el núcleo no admite un widget
			// es del importador, no del kit: no cuenta.
			if ( empty( $element['settings']['digitalisimo_unconverted_wrapper'] ) ) {
				$counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1;
			}
			$id              = (string) ( $element['id'] ?? '' );
			$row             = array( 'id' => $id, 'elType' => $element['elType'] ?? '' );
			if ( isset( $element['widgetType'] ) ) {
				$row['widgetType'] = $element['widgetType'];
			}
			if ( isset( $element['templateID'] ) ) {
				$row['templateID'] = $this->sym( 'template', $element['templateID'] );
			}
			foreach ( array( 'isInner', 'version' ) as $key ) {
				if ( isset( $element[ $key ] ) ) {
					$row[ $key ] = $element[ $key ];
				}
			}
			$row['settings'] = $this->settings( (array) ( $element['settings'] ?? array() ), $doc, $id, $ids );
			$row['elements'] = $this->elements( (array) ( $element['elements'] ?? array() ), $doc, $ids, $counts );
			$out[]           = $row;
		}
		return $out;
	}

	/** Un documento del constructor. */
	public function document( WP_Post $post ) {
		$doc  = self::post_key( $post );
		$path = (string) wp_make_link_relative( (string) get_permalink( $post ) );
		$data = json_decode( (string) get_post_meta( $post->ID, '_elementor_data', true ), true );
		$data = is_array( $data ) ? $data : array();
		$ids  = array();
		self::element_ids( $data, $ids );
		$counts   = array();
		$settings = (array) get_post_meta( $post->ID, '_elementor_page_settings', true );
		foreach ( (array) ( $this->refs[ $doc ][''] ?? array() ) as $ref ) {
			if ( 0 === strpos( $ref['path'], 'page_settings.' ) ) {
				$this->apply_ref( $settings, array( 'path' => substr( $ref['path'], 14 ) ) + $ref );
			}
		}
		$out = array(
			'path'     => $path,
			'slug'     => $post->post_name,
			'doc_type' => (string) get_post_meta( $post->ID, '_elementor_template_type', true ),
			'status'   => $post->post_status,
			'title'    => $post->post_title,
			'parent'   => $post->post_parent ? $this->sym( 'post', $post->post_parent ) : '',
			// Sin plantilla y «default» son lo mismo: el importador escribe «default» donde el origen no tenía nada.
			'template' => '' === (string) get_post_meta( $post->ID, '_wp_page_template', true ) ? 'default' : (string) get_post_meta( $post->ID, '_wp_page_template', true ),
			'data'     => $this->elements( $data, $doc, $ids, $counts ),
			'settings' => $this->generic( $settings, $doc . ' · ajustes' ),
			'counts'   => $counts,
			'terms'    => $this->terms_of( $post ),
		);
		$conditions = get_post_meta( $post->ID, '_elementor_conditions', true );
		if ( $conditions ) {
			$out['conditions'] = $this->conditions( (array) $conditions );
		}
		$popup = get_post_meta( $post->ID, '_elementor_popup_display_settings', true );
		if ( $popup ) {
			$out['popup'] = $this->generic( (array) $popup, $doc . ' · popup' );
		}
		$out['digitalisimo'] = $this->own_meta( $post->ID, $doc );
		$out['meta']         = $this->public_meta( $post->ID, $doc );
		$thumb                = (int) get_post_thumbnail_id( $post );
		if ( $thumb ) {
			$out['thumbnail'] = $this->media( $thumb );
		}
		return $out;
	}

	/** Condiciones del Theme Builder con sus `sub_id` nombrados según el `sub_name`. */
	public function conditions( array $conditions ) {
		$out = array();
		foreach ( $conditions as $condition ) {
			$parts = explode( '/', (string) $condition );
			if ( isset( $parts[3] ) && '' !== $parts[3] ) {
				$sub   = $parts[2];
				$kind  = preg_match( '/by_author$|^author$/', $sub ) ? 'user' : ( preg_match( '/^(category|post_tag|product_cat|product_tag|in_|.*_children$|.*_cat$|.*_tag$)/', $sub ) && ! preg_match( '/^child_of$/', $sub ) ? 'term' : 'post' );
				$parts[3] = $this->sym( $kind, $parts[3] );
			}
			$out[] = implode( '/', $parts );
		}
		return $out;
	}

	/** Metas propias `_digitalisimo_*` salvo la procedencia, que sólo existe en B (CV-15). */
	public function own_meta( $post_id, $doc ) {
		$out = array();
		foreach ( (array) get_post_meta( $post_id ) as $key => $values ) {
			if ( 0 !== strpos( $key, '_digitalisimo_' ) || '_digitalisimo_import_origin' === $key ) {
				continue;
			}
			$value       = maybe_unserialize( $values[0] );
			$out[ $key ] = is_array( $value ) ? $this->generic( $value, $doc . ' · ' . $key ) : $this->text( (string) $value, $doc . ' · ' . $key );
		}
		ksort( $out );
		return $out;
	}

	/** Metas públicas (sin guion bajo) de una entrada: las que trae `metadata` de un documento del kit. */
	public function public_meta( $post_id, $doc ) {
		$out = array();
		foreach ( (array) get_post_meta( $post_id ) as $key => $values ) {
			if ( '' === $key || '_' === $key[0] ) {
				continue;
			}
			$value       = maybe_unserialize( $values[0] );
			$out[ $key ] = is_array( $value ) ? $this->generic( $value, $doc . ' · ' . $key ) : $this->text( (string) $value, $doc . ' · ' . $key );
		}
		ksort( $out );
		return $out;
	}

	public function terms_of( WP_Post $post ) {
		$out = array();
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			if ( in_array( $taxonomy, array( 'elementor_library_type', 'elementor_library_category', 'post_format' ), true ) ) {
				continue;
			}
			$slugs = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $slugs ) && $slugs ) {
				sort( $slugs );
				$out[ $taxonomy ] = $slugs;
			}
		}
		return $out;
	}
}

/** Firma completa del sitio actual. */
function digi_k_signature() {
	$origin = sanitize_text_field( wp_unslash( $_GET['origin'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$refs   = digi_k_read_json( DIGI_K_SHARED . '/refs-a.json' );
	$sig    = new Digi_K_Signature( $origin, $refs );
	$out    = array( 'documents' => array(), 'posts' => array(), 'snippets' => array(), 'attachments' => array(), 'terms' => array(), 'menus' => array(), 'counts' => array( 'doc_type' => array(), 'taxonomy' => array(), 'elements' => array() ) );

	$types = array_values( array_diff( get_post_types( array(), 'names' ), array( 'revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_global_styles', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_font_family', 'wp_font_face', 'custom_css', 'attachment' ) ) );
	// También la papelera: R8 cuenta lo que la reversión manda allí (D16); `any` la deja fuera.
	$posts = get_posts( array( 'post_type' => $types, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
	$kit   = (int) get_option( 'elementor_active_kit' );
	foreach ( $posts as $post ) {
		$key = Digi_K_Signature::post_key( $post );
		if ( $post->ID === $kit ) {
			continue;
		}
		if ( get_post_meta( $post->ID, '_elementor_data', true ) || get_post_meta( $post->ID, '_elementor_template_type', true ) ) {
			$out['documents'][ $key ] = $sig->document( $post );
			$type                     = $out['documents'][ $key ]['doc_type'];
			$out['counts']['doc_type'][ $type ] = ( $out['counts']['doc_type'][ $type ] ?? 0 ) + 1;
			foreach ( $out['documents'][ $key ]['counts'] as $element => $n ) {
				$out['counts']['elements'][ $element ] = ( $out['counts']['elements'][ $element ] ?? 0 ) + $n;
			}
			continue;
		}
		if ( 'elementor_snippet' === $post->post_type ) {
			$conditions = get_post_meta( $post->ID, '_elementor_conditions', true );
			$out['snippets'][ $post->post_title ] = array(
				'status'     => $post->post_status,
				'location'   => (string) get_post_meta( $post->ID, '_elementor_location', true ),
				'priority'   => (string) get_post_meta( $post->ID, '_elementor_priority', true ),
				'conditions' => $conditions ? $sig->conditions( (array) $conditions ) : array(),
				'pending'    => (array) get_post_meta( $post->ID, '_digitalisimo_import_conditions', true ),
			);
			continue;
		}
		$meta     = array();
		$elementor = array();
		foreach ( (array) get_post_meta( $post->ID ) as $meta_key => $values ) {
			if ( 0 === strpos( $meta_key, '_elementor_' ) && '_elementor_import_session_id' !== $meta_key ) {
				$elementor[] = $meta_key;
			}
			// Fuentes e iconos se comparan en sus propias secciones (sus metas llevan IDs de adjuntos).
			if ( preg_match( '/^_(edit_lock|edit_last|wp_old_slug|thumbnail_id|elementor_|digitalisimo_import_origin|wp_page_template$)|^_wp_trash_meta_|^elementor_(font_files|font_face|custom_icon_set_config)$/', $meta_key ) ) {
				continue;
			}
			$value             = maybe_unserialize( $values[0] );
			$meta[ $meta_key ] = is_array( $value ) ? $sig->generic( $value, $key . ' · ' . $meta_key ) : $sig->text( (string) $value, $key . ' · ' . $meta_key );
		}
		ksort( $meta );
		$out['posts'][ $key ] = array(
			'status'    => $post->post_status,
			'title'     => $post->post_title,
			// La fecha viaja en el WXR (<wp:post_date>); los tipos del constructor (fuentes, iconos, fragmentos) llegan
			// por sus propios runners, sin fecha.
			'date'      => 0 === strpos( $post->post_type, 'elementor_' ) ? '' : $post->post_date,
			'content'   => $sig->text( $post->post_content, $key . ' · contenido' ),
			'excerpt'   => $post->post_excerpt,
			'parent'    => $post->post_parent ? $sig->sym( 'post', $post->post_parent ) : '',
			'terms'     => $sig->terms_of( $post ),
			'thumbnail' => get_post_thumbnail_id( $post ) ? $sig->media( get_post_thumbnail_id( $post ) ) : '',
			'meta'      => $meta,
			'password'  => $post->post_password,
			'comments'  => array_map(
				static function ( $c ) {
					return array( 'author' => $c->comment_author, 'email' => $c->comment_author_email, 'ip' => $c->comment_author_IP );
				},
				get_comments( array( 'post_id' => $post->ID ) )
			),
			'elementor_meta' => $elementor,
		);
		$out['counts']['doc_type'][ 'post_type:' . $post->post_type ] = ( $out['counts']['doc_type'][ 'post_type:' . $post->post_type ] ?? 0 ) + 1;
	}
	foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => -1 ) ) as $attachment ) {
		$name = $sig->media( $attachment->ID );
		$out['attachments'][ $name ] = array( 'title' => $attachment->post_title, 'mime' => $attachment->post_mime_type, 'alt' => (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) );
	}
	ksort( $out['attachments'] );

	// Términos con sus metas (parte term-meta) y menús con sus elementos.
	foreach ( get_taxonomies( array(), 'names' ) as $taxonomy ) {
		if ( in_array( $taxonomy, array( 'elementor_library_type', 'elementor_library_category', 'post_format', 'wp_theme', 'wp_template_part_area', 'link_category', 'elementor_font_type' ), true ) ) {
			continue;
		}
		foreach ( get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) as $term ) {
			if ( 'category' === $taxonomy && 'uncategorized' === $term->slug ) {
				continue;
			}
			$key  = $taxonomy . ':' . $term->slug;
			$meta = array();
			foreach ( (array) get_term_meta( $term->term_id ) as $meta_key => $values ) {
				if ( in_array( $meta_key, array( '_digitalisimo_import_origin', '_elementor_import_session_id' ), true ) ) {
					continue;
				}
				$value             = maybe_unserialize( $values[0] );
				$meta[ $meta_key ] = 'thumbnail_id' === $meta_key ? $sig->media( $value ) : ( is_array( $value ) ? $sig->generic( $value, $key ) : $sig->text( (string) $value, $key ) );
			}
			ksort( $meta );
			$out['terms'][ $key ]                        = array( 'name' => $term->name, 'parent' => $term->parent ? $sig->sym( 'term', $term->parent ) : '', 'meta' => $meta );
			$out['counts']['taxonomy'][ $taxonomy ] = ( $out['counts']['taxonomy'][ $taxonomy ] ?? 0 ) + 1;
			if ( 'nav_menu' === $taxonomy ) {
				$items = array();
				foreach ( (array) wp_get_nav_menu_items( $term->term_id ) as $item ) {
					$items[] = array(
						'title'  => $item->title,
						'type'   => $item->type,
						'object' => 'custom' === $item->type ? '' : $sig->sym( 'taxonomy' === $item->type ? 'term' : 'post', $item->object_id ),
						'url'    => 'custom' === $item->type ? $sig->text( $item->url, $key . ' · ' . $item->title ) : '',
						'parent' => (int) $item->menu_item_parent ? 'sí' : '',
						'classes'        => array_values( array_filter( (array) $item->classes, 'is_string' ) ),
						'classes_object' => false !== strpos( (string) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT meta_value FROM {$GLOBALS['wpdb']->postmeta} WHERE post_id = %d AND meta_key = '_menu_item_classes'", $item->ID ) ), 'O:' ),
					);
				}
				$out['menus'][ $term->slug ] = array( 'name' => $term->name, 'items' => $items );
			}
		}
	}
	ksort( $out['terms'] );

	// Kit activo: ajustes (sin la identidad del sitio, que nunca viaja), valores por defecto, metas propias y v4.
	if ( $kit ) {
		$settings = (array) get_post_meta( $kit, '_elementor_page_settings', true );
		foreach ( array( 'site_name', 'site_description', 'site_logo', 'site_favicon' ) as $identity ) {
			unset( $settings[ $identity ] );
		}
		foreach ( array( 'body_background_image', 'body_background_image_tablet', 'body_background_image_mobile' ) as $media_key ) {
			if ( isset( $settings[ $media_key ]['id'] ) ) {
				$settings[ $media_key ] = array( 'id' => $sig->media( $settings[ $media_key ]['id'] ) );
			}
		}
		$defaults = json_decode( (string) get_post_meta( $kit, '_elementor_elements_default_values', true ), true );
		$out['kit'] = array(
			'settings' => $sig->generic( $settings, 'kit' ),
			'defaults' => is_array( $defaults ) ? $sig->generic( $defaults, 'kit · defaults' ) : array(),
			'own'      => $sig->own_meta( $kit, 'kit' ),
			'v4'       => array(
				'variables' => get_post_meta( $kit, '_elementor_global_variables', true ),
				'classes'   => get_post_meta( $kit, '_elementor_global_classes', true ),
			),
		);
	}

	// Fuentes e iconos propios (sus archivos por sha256, y ninguna URL externa).
	foreach ( get_posts( array( 'post_type' => 'elementor_font', 'post_status' => 'any', 'posts_per_page' => -1 ) ) as $font ) {
		$faces = array();
		foreach ( (array) get_post_meta( $font->ID, 'elementor_font_files', true ) as $face ) {
			$row = array( 'weight' => $face['font_weight'] ?? '', 'style' => $face['font_style'] ?? '' );
			foreach ( array( 'woff', 'woff2', 'ttf', 'svg', 'eot' ) as $format ) {
				if ( ! empty( $face[ $format ]['id'] ) ) {
					$row[ $format ] = $sig->media( $face[ $format ]['id'] );
				} elseif ( ! empty( $face[ $format ]['url'] ) ) {
					$row[ $format ] = $sig->url( $face[ $format ]['url'] );
				}
			}
			$faces[] = $row;
		}
		$css                              = (string) get_post_meta( $font->ID, 'elementor_font_face', true );
		$out['fonts'][ $font->post_title ] = array( 'status' => $font->post_status, 'faces' => $faces, 'face_css' => $sig->text( $css, 'fuente ' . $font->post_title ) );
	}
	foreach ( get_posts( array( 'post_type' => 'elementor_icons', 'post_status' => 'any', 'posts_per_page' => -1 ) ) as $set ) {
		$config = json_decode( (string) get_post_meta( $set->ID, 'elementor_custom_icon_set_config', true ), true );
		$config = is_array( $config ) ? $config : array();
		foreach ( array( 'url', 'fetchJson' ) as $key ) {
			if ( isset( $config[ $key ] ) && is_string( $config[ $key ] ) ) {
				$config[ $key ] = $sig->text( $config[ $key ], 'iconos ' . $set->post_title );
			}
		}
		unset( $config['custom_icon_post_id'] );
		$out['icons'][ $set->post_title ] = array( 'status' => $set->post_status, 'config' => $config );
	}

	// Presets de estilo del sitio (F7a): la opción entera, con `hash`, `updated` e `inert` (R1 los compara tal cual).
	$presets = get_option( 'digitalisimo_elements_presets', array() );
	$out['presets'] = array();
	foreach ( (array) ( is_array( $presets ) ? ( $presets['presets'] ?? array() ) : array() ) as $slug => $preset ) {
		$out['presets'][ $slug ] = is_array( $preset ) ? $sig->generic( $preset, 'presets · ' . $slug ) : $preset;
	}
	ksort( $out['presets'] );
	// Índices derivados de F7a (páginas rastreadas por «Visitó la página» y prioridad manual del Theme Builder), con
	// sus IDs nombrados: tras importar deben apuntar a los documentos de este sitio.
	$tracked = get_option( 'digitalisimo_elements_tracked_pages', null );
	$out['indexes'] = array( 'tracked_pages' => null, 'tb_priority' => null );
	if ( is_array( $tracked ) ) {
		$docs = array();
		foreach ( (array) ( $tracked['docs'] ?? array() ) as $doc_id => $pages ) {
			$name          = $sig->sym( 'post', (int) $doc_id );
			$list          = $sig->sym( 'post', array_values( array_map( 'intval', (array) $pages ) ) );
			sort( $list );
			$docs[ $name ] = $list;
		}
		ksort( $docs );
		$union = $sig->sym( 'post', array_values( array_map( 'intval', (array) ( $tracked['union'] ?? array() ) ) ) );
		sort( $union );
		$out['indexes']['tracked_pages'] = array( 'docs' => $docs, 'union' => $union );
	}
	$priority = get_option( 'digitalisimo_elements_tb_priority', null );
	if ( is_array( $priority ) ) {
		$named = array();
		foreach ( $priority as $template_id => $number ) {
			$named[ $sig->sym( 'template', (int) $template_id ) ] = (int) $number;
		}
		ksort( $named );
		$out['indexes']['tb_priority'] = $named;
	}

	$out['options'] = array(
		'show_on_front'  => get_option( 'show_on_front' ),
		'page_on_front'  => $sig->sym( 'post', get_option( 'page_on_front' ) ),
		'page_for_posts' => $sig->sym( 'post', get_option( 'page_for_posts' ) ),
	);
	foreach ( array( 'shop', 'cart', 'checkout', 'myaccount' ) as $page ) {
		if ( false !== get_option( 'woocommerce_' . $page . '_page_id', false ) ) {
			$out['options'][ 'woocommerce_' . $page . '_page_id' ] = $sig->sym( 'post', get_option( 'woocommerce_' . $page . '_page_id' ) );
		}
	}

	// Sólo los archivos usados de uploads/digitalisimo-elements/ (nunca tmp/), con su sha256.
	$up    = wp_upload_dir( null, false );
	$files = array();
	foreach ( array_keys( $sig->used_files ) as $relative ) {
		$file               = $up['basedir'] . '/digitalisimo-elements/' . $relative;
		$files[ $relative ] = is_file( $file ) ? hash_file( 'sha256', $file ) : 'falta';
	}
	ksort( $files );
	$out['files']       = $files;
	$out['unresolved']  = array_values( array_unique( $sig->unresolved ) );
	$out['integrity']   = array_values( array_unique( $sig->integrity ) );
	$out['origin_urls'] = array_values( array_unique( $sig->origin_urls ) );
	ksort( $out['documents'] );
	ksort( $out['posts'] );
	return $out;
}
