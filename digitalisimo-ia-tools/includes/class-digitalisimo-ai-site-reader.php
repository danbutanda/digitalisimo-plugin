<?php
defined( 'ABSPATH' ) || exit;

/**
 * Leer el sitio como una persona (docs/ASSISTANT.md): dos Abilities de sólo lectura de IA Tools, para el Asistente y
 * para el MCP del sitio. Caso plomerito (2026-11-23): el Asistente respondió «con mis herramientas no puedo ver el
 * texto de los widgets» y no encontró el WhatsApp ni las políticas que el sitio ya publicaba.
 *
 * - `digitalisimo-ia/read-page-text`: el TEXTO legible de una entrada, página, producto o plantilla (por ID, URL del
 *   sitio o slug): el de los widgets de Elementor (también los atómicos v4 y los repetidores) o el de los bloques, sin
 *   HTML, recortado, con sus enlaces (texto y destino) y los teléfonos o WhatsApp que lleva.
 * - `digitalisimo-ia/site-contact`: los datos de contacto que el sitio ya publica: WhatsApp (enlaces wa.me,
 *   api.whatsapp.com y whatsapp://), teléfonos (tel: y números escritos en cabecera, pie y página de contacto), correos
 *   (mailto:), dirección (WooCommerce y SEO Local de DIGITALÍSIMO SEO, con su teléfono) y las páginas reales de
 *   privacidad, términos, reembolso, contacto, carrito, pago y tienda. Busca en la portada, las cabeceras y pies de
 *   Elementor, los menús, los widgets y las páginas publicadas más recientes.
 *
 * Las dos: `edit_posts`, anotadas de sólo lectura (el Asistente las ejecuta sin confirmar), públicas para MCP y
 * ofrecidas también por el servidor MCP de DIGITALÍSIMO Elements. Lo que no es público (borradores, privadas) sólo se
 * lee con `edit_post` sobre ese documento; las protegidas con contraseña, igual.
 */
final class Digitalisimo_AI_Site_Reader {
	const READ_TEXT = 'digitalisimo-ia/read-page-text';
	const CONTACT   = 'digitalisimo-ia/site-contact';
	const MAX_TEXT  = 60000;
	const DEFAULT   = 20000;
	const SCAN      = 40;
	/** Claves de ajustes de Elementor que nunca son texto visible. */
	const SKIP_KEYS = '/^(_.*|css|custom_css|html_tag|header_size|align.*|layout|content_width|gap|structure|link_to|icon|selected_icon|image_size|thumbnail|animation.*|motion_fx.*|background.*|border.*|typography.*|flex_.*|.*_(color|size|width|height|position|align|alignment|spacing|margin|padding|radius|shadow|type|style|view|skin|effect|id|unit|gap|ratio|opacity|transition|duration|delay|offset|direction|wrap|justify|overflow|display|family|weight|index|icon|library)(_[a-z0-9]+)?|.*_(tablet|mobile|widescreen|laptop|hover)|id|classes|element_id|hover_animation|entrance_animation|template_id|view|skin|tag|shape|ratio|aspect_ratio|position|width|height|unit|sizes|size|library)$/i';

	public static function definitions() {
		$annotations = array( 'readonly' => true, 'destructive' => false, 'idempotent' => true );
		$meta        = array( 'show_in_rest' => true, 'annotations' => $annotations, 'mcp' => array( 'public' => true, 'type' => 'tool' ) );
		return array(
			self::READ_TEXT => array(
				'label'               => 'Leer el texto de una página',
				'description'         => 'Devuelve el texto legible de una entrada, página, producto o plantilla de este sitio, tal como lo lee una persona: el de los widgets de Elementor (títulos, textos, botones, listas, preguntas, testimonios) o el de los bloques del editor, sin HTML. Trae también sus enlaces (texto y destino) y los teléfonos y WhatsApp que aparecen. Busca por id, por url (de este sitio) o por slug. Úsala para saber qué dice una página antes de escribir sobre ella, o para verificar una página que acabas de crear. Sólo lee.',
				'category'            => Digitalisimo_AI_Assistant_Ability::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(
						'id'        => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'ID de la entrada, página, producto o plantilla.' ),
						'url'       => array( 'type' => 'string', 'maxLength' => 2000, 'description' => 'URL de una página de este sitio.' ),
						'slug'      => array( 'type' => 'string', 'maxLength' => 200, 'description' => 'Slug (nombre en la URL) de una página o entrada.' ),
						'max_chars' => array( 'type' => 'integer', 'minimum' => 500, 'maximum' => self::MAX_TEXT, 'description' => 'Tope de caracteres del texto (por defecto 20000).' ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'read_text' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
				'meta'                => $meta,
			),
			self::CONTACT   => array(
				'label'               => 'Datos de contacto del sitio',
				'description'         => 'Encuentra los datos de contacto que el sitio YA publica, para usarlos sin pedírselos a la persona: WhatsApp (enlaces wa.me o api.whatsapp.com), teléfonos, correos y dirección (de las páginas, la cabecera y el pie, los menús, los widgets, WooCommerce y DIGITALÍSIMO SEO Local), y las páginas reales de privacidad, términos, reembolso y devoluciones, contacto, carrito, pago y tienda (con su URL). Cada dato dice dónde se encontró. «El número de este sitio» es el WhatsApp o teléfono que devuelve. Sólo lee.',
				'category'            => Digitalisimo_AI_Assistant_Ability::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, 'contact' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
				'meta'                => $meta,
			),
		);
	}

	public static function register() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		foreach ( self::definitions() as $name => $definition ) {
			if ( ! function_exists( 'wp_has_ability' ) || ! wp_has_ability( $name ) ) {
				wp_register_ability( $name, $definition );
			}
		}
	}

	public static function permission( $input = null ) {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/* ------------------------------------------------------------------ */
	/* Texto de una página.                                                 */
	/* ------------------------------------------------------------------ */

	public static function read_text( $input = null ) {
		$input = is_array( $input ) ? $input : array();
		$post  = self::find( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$max     = max( 500, min( self::MAX_TEXT, (int) ( $input['max_chars'] ?? self::DEFAULT ) ) );
		$content = self::content( $post );
		$text    = $content['text'];
		$length  = self::length( $text );
		$phones  = self::phones_in( $text, $content['links'], 'página ' . $post->ID );
		return array(
			'id'        => (int) $post->ID,
			'title'     => (string) get_the_title( $post ),
			'type'      => (string) $post->post_type,
			'status'    => (string) $post->post_status,
			'url'       => (string) get_permalink( $post ),
			'source'    => $content['source'],
			'text'      => $length > $max ? Digitalisimo_AI_Assistant_Tools::clip( $text, $max ) : $text,
			'length'    => $length,
			'truncated' => $length > $max,
			'links'     => array_slice( $content['links'], 0, 80 ),
			'whatsapp'  => $phones['whatsapp'],
			'phones'    => $phones['phones'],
		);
	}

	/** La entrada pedida, si existe, no es interna y quien pregunta la puede leer. */
	private static function find( array $input ) {
		$id = absint( $input['id'] ?? 0 );
		if ( ! $id && ! empty( $input['url'] ) && function_exists( 'url_to_postid' ) ) {
			$url  = esc_url_raw( (string) $input['url'] );
			$home = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			if ( '' !== $url && (string) wp_parse_url( $url, PHP_URL_HOST ) !== $home ) {
				return new WP_Error( 'digitalisimo_reader_foreign', 'Esa URL no es de este sitio.', array( 'status' => 400 ) );
			}
			$id = (int) url_to_postid( $url );
			if ( ! $id && rtrim( $url, '/' ) === rtrim( home_url( '/' ), '/' ) ) {
				$id = (int) get_option( 'page_on_front' );
			}
		}
		if ( ! $id && ! empty( $input['slug'] ) ) {
			$found = get_posts( array( 'name' => sanitize_title( (string) $input['slug'] ), 'post_type' => 'any', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => 1, 'suppress_filters' => true ) );
			$id    = $found ? (int) $found[0]->ID : 0;
		}
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || ! self::readable_type( (string) $post->post_type ) ) {
			// Sólo contenido que se ve en el sitio (entradas, páginas, productos…) y las plantillas de Elementor: nunca
			// cupones, formularios recibidos, pedidos ni otros tipos internos, aunque se tenga edit_posts.
			return new WP_Error( 'digitalisimo_reader_missing', 'No encontré esa página en el sitio.', array( 'status' => 404 ) );
		}
		$public = 'publish' === $post->post_status && '' === (string) ( $post->post_password ?? '' );
		if ( ! $public && ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'digitalisimo_reader_forbidden', 'No tienes permiso para leer esa página.', array( 'status' => 403 ) );
		}
		return $post;
	}

	/** Tipos que se leen: los visibles en el sitio (`is_post_type_viewable`) y las plantillas de Elementor (cabecera, pie). */
	public static function readable_type( $type ) {
		if ( in_array( $type, array( 'elementor_library' ), true ) ) {
			return true;
		}
		$object = function_exists( 'get_post_type_object' ) ? get_post_type_object( $type ) : null;
		return $object && function_exists( 'is_post_type_viewable' ) && is_post_type_viewable( $object );
	}

	/**
	 * `text`, `links` y `source` de un documento: de Elementor si lo construye Elementor (`_elementor_data`), si no del
	 * contenido (bloques renderizados sin shortcodes). Nada se ejecuta en el frontal.
	 */
	public static function content( $post ) {
		$data = get_post_meta( $post->ID, '_elementor_data', true );
		$data = is_string( $data ) && '' !== $data ? json_decode( $data, true ) : ( is_array( $data ) ? $data : null );
		if ( is_array( $data ) && $data ) {
			$lines = array();
			$links = array();
			self::walk( $data, $lines, $links, 0 );
			return array( 'text' => self::join( $lines ), 'links' => self::unique_links( $links ), 'source' => 'elementor' );
		}
		$html = (string) ( $post->post_content ?? '' );
		if ( function_exists( 'do_blocks' ) && function_exists( 'has_blocks' ) && has_blocks( $html ) ) {
			$html = do_blocks( $html );
		}
		if ( function_exists( 'strip_shortcodes' ) ) {
			$html = strip_shortcodes( $html );
		}
		$links = self::links_in_html( $html );
		return array( 'text' => self::html_text( $html ), 'links' => $links, 'source' => 'content' );
	}

	/** Recorre elementos de Elementor (clásicos y atómicos v4): textos y enlaces de sus ajustes, en orden. */
	private static function walk( $nodes, array &$lines, array &$links, $depth ) {
		if ( ! is_array( $nodes ) || $depth > 40 ) {
			return;
		}
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( is_array( $node['settings'] ?? null ) ) {
				self::settings( $node['settings'], $lines, $links, '', 0 );
			}
			if ( ! empty( $node['elements'] ) ) {
				self::walk( $node['elements'], $lines, $links, $depth + 1 );
			}
		}
	}

	private static function settings( $value, array &$lines, array &$links, $key, $depth ) {
		if ( $depth > 12 ) {
			return;
		}
		if ( is_array( $value ) ) {
			// Ajustes atómicos v4: {"$$type": "...", "value": ...}.
			if ( isset( $value['$$type'] ) && array_key_exists( 'value', $value ) ) {
				self::settings( $value['value'], $lines, $links, $key, $depth + 1 );
				return;
			}
			// Un enlace clásico: {"url": "...", "is_external": ...}; con su texto si el widget lo trae al lado.
			if ( isset( $value['url'] ) && is_string( $value['url'] ) && ! isset( $value['id'] ) ) {
				self::link( $value['url'], '', $links );
				return;
			}
			foreach ( $value as $k => $v ) {
				$k = is_string( $k ) ? $k : $key;
				if ( is_string( $k ) && '' !== $k && preg_match( self::SKIP_KEYS, $k ) && ! in_array( $k, array( 'title_text', 'description_text' ), true ) ) {
					continue;
				}
				self::settings( $v, $lines, $links, $k, $depth + 1 );
			}
			return;
		}
		if ( ! is_string( $value ) ) {
			return;
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return;
		}
		if ( preg_match( '#^(https?://|tel:|mailto:|whatsapp://|wa\.me/|/)#i', $value ) && ! preg_match( '/\s/', $value ) ) {
			self::link( $value, '', $links );
			return;
		}
		if ( preg_match( '/^(#[0-9a-f]{3,8}|rgba?\([\d\s.,%]+\)|(fa[srbld]?|eicon|e-icon)[ -][a-z0-9 -]+)$/i', $value ) ) {
			return; // Colores e iconos, no texto.
		}
		if ( preg_match( '/^(-?[\d.]+(px|em|rem|%|vh|vw)?|[a-z]+(-[a-z]+)*|yes|no|true|false|\{\{.*\}\})$/i', $value ) && ! preg_match( '/\s/', $value ) && ! preg_match( '/[A-ZÁÉÍÓÚÑ]/u', $value ) ) {
			return; // Valores de diseño o de control (medidas, palabras clave), no texto.
		}
		foreach ( self::links_in_html( $value ) as $found ) {
			$links[] = $found;
		}
		$text = self::html_text( $value );
		if ( '' !== $text ) {
			$lines[] = $text;
		}
	}

	private static function link( $url, $text, array &$links ) {
		$url = trim( (string) $url );
		if ( '' === $url || '#' === $url ) {
			return;
		}
		$links[] = array( 'text' => Digitalisimo_AI_Assistant_Tools::clip( trim( (string) $text ), 120 ), 'url' => Digitalisimo_AI_Assistant_Tools::clip( $url, 500 ) );
	}

	/** Los enlaces (texto y destino) de un trozo de HTML. */
	public static function links_in_html( $html ) {
		$out = array();
		if ( preg_match_all( '#<a\s[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>#is', (string) $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $a ) {
				self::link( html_entity_decode( $a[2], ENT_QUOTES, 'UTF-8' ), self::html_text( $a[3] ), $out );
			}
		}
		return self::unique_links( $out );
	}

	private static function unique_links( array $links ) {
		$seen = array();
		$out  = array();
		foreach ( $links as $link ) {
			$key = $link['url'] . '|' . $link['text'];
			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$out[]        = $link;
			}
		}
		return $out;
	}

	/** HTML a texto: los bloques y saltos como líneas, sin etiquetas ni entidades. */
	public static function html_text( $html ) {
		$html = preg_replace( '@<(script|style|noscript|svg)[^>]*?>.*?</\1>@si', ' ', (string) $html );
		$html = preg_replace( '#<(br|/p|/div|/li|/h[1-6]|/tr|/section|/article|/blockquote|/figcaption)\b[^>]*>#i', "\n", (string) $html );
		$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( "/[ \t\x{00A0}]+/u", ' ', (string) $text );
		$text = preg_replace( "/ *\n[ \n]*/", "\n", (string) $text );
		return trim( (string) $text );
	}

	private static function join( array $lines ) {
		$out  = array();
		$prev = null;
		foreach ( $lines as $line ) {
			if ( $line !== $prev ) {
				$out[] = $line;
			}
			$prev = $line;
		}
		return trim( implode( "\n", $out ) );
	}

	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text );
	}

	/* ------------------------------------------------------------------ */
	/* Contacto del sitio.                                                  */
	/* ------------------------------------------------------------------ */

	public static function contact( $input = null ) {
		$found = array( 'whatsapp' => array(), 'phones' => array(), 'emails' => array() );
		foreach ( self::sources() as $source ) {
			$hits = self::phones_in( $source['text'], $source['links'], $source['where'], ! empty( $source['plain'] ) );
			foreach ( array( 'whatsapp', 'phones' ) as $kind ) {
				foreach ( $hits[ $kind ] as $hit ) {
					self::add( $found[ $kind ], $hit );
				}
			}
			foreach ( self::emails_in( $source['text'], $source['links'] ) as $email ) {
				self::add( $found['emails'], array( 'email' => $email, 'source' => $source['where'] ), 'email' );
			}
		}
		$address = self::address();
		if ( '' !== $address['phone'] ) {
			$digits = preg_replace( '/\D+/', '', $address['phone'] );
			self::add( $found['phones'], array( 'number' => $digits, 'display' => $address['phone'], 'source' => 'DIGITALÍSIMO SEO · Negocio local' ) );
		}
		if ( '' !== $address['email'] ) {
			self::add( $found['emails'], array( 'email' => $address['email'], 'source' => $address['email_source'] ), 'email' );
		}
		$whatsapp = $found['whatsapp'];
		return array(
			'site'      => array( 'name' => (string) get_bloginfo( 'name' ), 'url' => home_url( '/' ) ),
			'whatsapp'  => $whatsapp,
			'phones'    => $found['phones'],
			'emails'    => $found['emails'],
			'address'   => $address['text'],
			'pages'     => self::pages(),
			'principal' => $whatsapp ? array( 'whatsapp' => $whatsapp[0]['number'], 'url' => 'https://wa.me/' . $whatsapp[0]['number'], 'source' => $whatsapp[0]['source'] ) : ( $found['phones'] ? array( 'telefono' => $found['phones'][0]['number'], 'source' => $found['phones'][0]['source'] ) : null ),
			'note'      => $whatsapp || $found['phones'] ? 'Datos publicados por el propio sitio: úsalos tal cual (el primero de cada lista es el que más se repite).' : 'El sitio no publica ningún WhatsApp ni teléfono en las páginas, menús, cabecera o pie revisados.',
		);
	}

	/** Lo que se revisa, de lo más probable (pie, cabecera, contacto, portada) a lo menos. */
	private static function sources() {
		$out   = array();
		$posts = array();
		// Cabeceras, pies y plantillas de Elementor (constructor de temas y kits).
		foreach ( get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'posts_per_page' => self::SCAN, 'suppress_filters' => true, 'orderby' => 'modified', 'order' => 'DESC' ) ) as $post ) {
			$type    = (string) get_post_meta( $post->ID, '_elementor_template_type', true );
			$posts[] = array( $post, in_array( $type, array( 'footer', 'header' ), true ) ? ( 'footer' === $type ? 'pie' : 'cabecera' ) : 'plantilla', in_array( $type, array( 'footer', 'header' ), true ) );
		}
		$front = (int) get_option( 'page_on_front' );
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => self::SCAN, 'suppress_filters' => true, 'orderby' => 'modified', 'order' => 'DESC' ) );
		foreach ( $pages as $post ) {
			$contact = (bool) preg_match( '/contact|whats|ubica|sucursal/i', (string) ( $post->post_name ?? '' ) . ' ' . (string) $post->post_title );
			$posts[] = array( $post, (int) $post->ID === $front ? 'portada' : ( $contact ? 'página de contacto' : 'página' ), (int) $post->ID === $front || $contact );
		}
		foreach ( $posts as $item ) {
			list( $post, $kind, $plain ) = $item;
			if ( '' !== (string) ( $post->post_password ?? '' ) ) {
				continue;
			}
			$content = self::content( $post );
			$out[]   = array( 'text' => $content['text'], 'links' => $content['links'], 'where' => $kind . ' «' . get_the_title( $post ) . '» (ID ' . $post->ID . ')', 'plain' => $plain );
		}
		// Menús.
		if ( function_exists( 'wp_get_nav_menus' ) && function_exists( 'wp_get_nav_menu_items' ) ) {
			foreach ( (array) wp_get_nav_menus() as $menu ) {
				$links = array();
				foreach ( (array) wp_get_nav_menu_items( $menu ) as $item ) {
					if ( is_object( $item ) && ! empty( $item->url ) ) {
						$links[] = array( 'text' => (string) ( $item->title ?? '' ), 'url' => (string) $item->url );
					}
				}
				$out[] = array( 'text' => '', 'links' => $links, 'where' => 'menú «' . ( is_object( $menu ) ? $menu->name : '' ) . '»', 'plain' => false );
			}
		}
		// Widgets clásicos y de bloques (pie y barras laterales de muchos temas).
		foreach ( array( 'widget_text', 'widget_custom_html', 'widget_block' ) as $option ) {
			foreach ( (array) get_option( $option, array() ) as $widget ) {
				$html = is_array( $widget ) ? (string) ( $widget['text'] ?? $widget['content'] ?? '' ) : '';
				if ( '' !== $html ) {
					$out[] = array( 'text' => self::html_text( $html ), 'links' => self::links_in_html( $html ), 'where' => 'widget', 'plain' => true );
				}
			}
		}
		return $out;
	}

	/**
	 * WhatsApp y teléfonos de un texto y sus enlaces: wa.me/<n>, api.whatsapp.com/send?phone=<n>, whatsapp://send?phone,
	 * tel:<n> y, sólo donde se espera (`$plain`: pie, cabecera, contacto, portada, widgets), números escritos de 10 a 13
	 * dígitos. Cada uno con dónde se encontró.
	 */
	public static function phones_in( $text, array $links, $where, $plain = true ) {
		$out = array( 'whatsapp' => array(), 'phones' => array() );
		$all = array();
		foreach ( $links as $link ) {
			$all[] = (string) ( $link['url'] ?? '' );
		}
		$all[] = (string) $text;
		foreach ( $all as $haystack ) {
			$haystack = rawurldecode( $haystack );
			if ( preg_match_all( '#(?:wa\.me/|api\.whatsapp\.com/send/?\?(?:[^"\s]*&)?phone=|web\.whatsapp\.com/send/?\?(?:[^"\s]*&)?phone=|whatsapp://send/?\?(?:[^"\s]*&)?phone=)\+?(\d{8,15})#i', $haystack, $m ) ) {
				foreach ( $m[1] as $number ) {
					self::add( $out['whatsapp'], array( 'number' => $number, 'url' => 'https://wa.me/' . $number, 'source' => $where ) );
				}
			}
			if ( preg_match_all( '#tel:\s*([+\d][\d\s().-]{6,20}\d)#i', $haystack, $m ) ) {
				foreach ( $m[1] as $number ) {
					self::add( $out['phones'], array( 'number' => preg_replace( '/\D+/', '', $number ), 'display' => trim( $number ), 'source' => $where ) );
				}
			}
		}
		if ( $plain && preg_match_all( '/(?<![\d\/=])(\+?\d[\d \t().-]{8,18}\d)(?![\d\/])/', (string) $text, $m ) ) {
			foreach ( $m[1] as $number ) {
				$digits = preg_replace( '/\D+/', '', $number );
				if ( strlen( $digits ) >= 10 && strlen( $digits ) <= 13 && ! preg_match( '/^(19|20)\d{2}\D/', trim( $number ) ) ) {
					self::add( $out['phones'], array( 'number' => $digits, 'display' => trim( $number ), 'source' => $where . ' (texto)' ) );
				}
			}
		}
		return $out;
	}

	private static function emails_in( $text, array $links ) {
		$out = array();
		foreach ( $links as $link ) {
			if ( preg_match( '/^mailto:([^?]+)/i', (string) ( $link['url'] ?? '' ), $m ) && is_email( rawurldecode( $m[1] ) ) ) {
				$out[] = strtolower( rawurldecode( $m[1] ) );
			}
		}
		if ( preg_match_all( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', (string) $text, $m ) ) {
			foreach ( $m[0] as $email ) {
				if ( is_email( $email ) ) {
					$out[] = strtolower( $email );
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** Agrega sin repetir (por número o correo) y cuenta cuántas veces aparece, para ordenar por lo más repetido. */
	private static function add( array &$list, array $item, $key = 'number' ) {
		foreach ( $list as $i => $existing ) {
			if ( $existing[ $key ] === $item[ $key ] ) {
				$list[ $i ]['times'] = (int) ( $existing['times'] ?? 1 ) + 1;
				usort(
					$list,
					function ( $a, $b ) {
						return (int) ( $b['times'] ?? 1 ) - (int) ( $a['times'] ?? 1 );
					}
				);
				return;
			}
		}
		$item['times'] = 1;
		$list[]        = $item;
	}

	/** Dirección, teléfono y correo de WooCommerce y de DIGITALÍSIMO SEO (Negocio local), si están. */
	private static function address() {
		$parts = array();
		foreach ( array( 'woocommerce_store_address', 'woocommerce_store_address_2', 'woocommerce_store_city', 'woocommerce_store_postcode' ) as $option ) {
			$value = trim( (string) get_option( $option, '' ) );
			if ( '' !== $value ) {
				$parts[] = $value;
			}
		}
		$country = trim( (string) get_option( 'woocommerce_default_country', '' ) );
		$text    = $parts ? implode( ', ', $parts ) . ( '' !== $country ? ' (' . $country . ')' : '' ) : '';
		$phone   = '';
		$email   = '';
		$source  = '';
		if ( class_exists( 'Digitalisimo_Integrations_SEO_Resolver' ) && method_exists( 'Digitalisimo_Integrations_SEO_Resolver', 'option' ) ) {
			$seo = array();
			foreach ( array( 'seo_local_address', 'seo_local_city', 'seo_local_region', 'seo_local_postal' ) as $key ) {
				$value = trim( (string) Digitalisimo_Integrations_SEO_Resolver::option( $key, '' ) );
				if ( '' !== $value ) {
					$seo[] = $value;
				}
			}
			if ( '' === $text && $seo ) {
				$text = implode( ', ', $seo );
			}
			$email  = trim( (string) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_email', '' ) );
			$source = 'DIGITALÍSIMO SEO · Organización';
			if ( class_exists( 'Digitalisimo_Integrations_Local_Business' ) && method_exists( 'Digitalisimo_Integrations_Local_Business', 'phone' ) ) {
				$phone = trim( (string) Digitalisimo_Integrations_Local_Business::phone() );
			}
		}
		if ( '' === $email ) {
			$email  = trim( (string) get_option( 'woocommerce_email_from_address', '' ) );
			$source = 'WooCommerce · remitente de los correos de la tienda';
		}
		return array( 'text' => $text, 'phone' => $phone, 'email' => is_email( $email ) ? $email : '', 'email_source' => $source );
	}

	/** Las páginas reales (publicadas) de políticas, contacto y tienda, con su URL. */
	public static function pages() {
		$out = array();
		$set = function ( $key, $id, $how ) use ( &$out ) {
			$id   = (int) $id;
			$post = $id > 0 ? get_post( $id ) : null;
			if ( isset( $out[ $key ] ) || ! $post || 'publish' !== $post->post_status ) {
				return;
			}
			$out[ $key ] = array( 'id' => $id, 'title' => (string) get_the_title( $post ), 'url' => (string) get_permalink( $post ), 'source' => $how );
		};
		$set( 'privacidad', (int) get_option( 'wp_page_for_privacy_policy' ), 'Ajustes → Privacidad' );
		if ( function_exists( 'wc_terms_and_conditions_page_id' ) ) {
			$set( 'terminos', (int) wc_terms_and_conditions_page_id(), 'WooCommerce → términos' );
		}
		$set( 'terminos', (int) get_option( 'woocommerce_terms_page_id' ), 'WooCommerce → términos' );
		$set( 'reembolso', (int) get_option( 'woocommerce_refund_returns_page_id' ), 'WooCommerce → reembolsos y devoluciones' );
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'carrito' => 'cart', 'pago' => 'checkout', 'tienda' => 'shop', 'mi_cuenta' => 'myaccount' ) as $key => $page ) {
				$set( $key, (int) wc_get_page_id( $page ), 'WooCommerce → ' . $page );
			}
		}
		$search = array(
			'privacidad' => '/privacidad|privacy|aviso[- ]de[- ]privacidad/iu',
			'terminos'   => '/t[ée]rminos|condiciones|terms/iu',
			'reembolso'  => '/reembolso|devoluci|refund|returns|garant[íi]a/iu',
			'envios'     => '/env[íi]os?|shipping/iu',
			'contacto'   => '/contacto|contact/iu',
		);
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 200, 'suppress_filters' => true, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
		foreach ( $search as $key => $pattern ) {
			foreach ( $pages as $page ) {
				if ( preg_match( $pattern, (string) $page->post_title . ' ' . (string) ( $page->post_name ?? '' ) ) ) {
					$set( $key, (int) $page->ID, 'título de la página' );
				}
			}
		}
		return $out;
	}
}
