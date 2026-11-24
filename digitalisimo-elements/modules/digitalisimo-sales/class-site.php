<?php
namespace Digitalisimo\Elements\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * Lo que el kit de ventas lee del propio sitio para terminar una página sin inventar nada: el nombre y el logo, el número
 * de WhatsApp que el sitio ya publica, la página de contacto, las páginas legales y de la tienda que existen de verdad,
 * la moneda, los medios de pago y los envíos activos.
 *
 * Sólo lee (nunca escribe) y cada función de WordPress o de la tienda se comprueba antes de usarla: sin tienda, sin
 * logo o sin páginas, el dato queda vacío y la página se termina sin él. `read()` arma el arreglo que usan `Finisher` y
 * `Product`; las partes puras (`whatsapp_numbers()`, `pick_whatsapp()`) se prueban sin WordPress.
 */
final class Site {
	/** Rutas del kit → cómo se buscan en el sitio (`kind`) y las direcciones probables de la página (`slugs`). */
	const LINKS = array(
		'/aviso-de-privacidad/'    => array(
			'kind'  => 'privacy',
			'slugs' => array( 'aviso-de-privacidad', 'politica-de-privacidad', 'privacidad', 'privacy-policy' ),
		),
		'/terminos-y-condiciones/' => array(
			'kind'  => 'terms',
			'slugs' => array( 'terminos-y-condiciones', 'terminos', 'terms-and-conditions', 'terms' ),
		),
		'/politica-de-reembolso/'  => array(
			'kind'  => 'refund',
			'slugs' => array( 'politica-de-reembolso', 'politica-de-devoluciones', 'politica-de-reembolsos', 'devoluciones', 'reembolsos', 'cambios-y-devoluciones', 'refund_returns', 'refund-policy' ),
		),
		'/carrito/'                => array(
			'kind'  => 'cart',
			'slugs' => array( 'carrito', 'cart' ),
		),
		'/pago/'                   => array(
			'kind'  => 'checkout',
			'slugs' => array( 'pago', 'finalizar-compra', 'checkout' ),
		),
		'/gracias-registro/'       => array(
			'kind'  => 'page',
			'slugs' => array( 'gracias-registro' ),
		),
		'/gracias-compra/'         => array(
			'kind'  => 'page',
			'slugs' => array( 'gracias-compra' ),
		),
		'/acceso/'                 => array(
			'kind'  => 'page',
			'slugs' => array( 'acceso' ),
		),
	);

	/** Métodos de envío que no llevan el producto a nadie: recoger en tienda. */
	const PICKUP = array( 'local_pickup', 'pickup_location' );

	/** Direcciones probables de la página de contacto. */
	const CONTACT_SLUGS = array( 'contacto', 'contactanos', 'contactenos', 'contact', 'contact-us' );

	/** Un enlace de WhatsApp: `wa.me/<número>`, `api.whatsapp.com/send?phone=<número>` o `whatsapp://send?phone=…`. */
	const WHATSAPP_LINK = '~(?:wa\.me/|whatsapp\.com/send/?\?(?:[^"\'\s<>]*?&(?:amp;)?)?phone=|whatsapp://send/?\?(?:[^"\'\s<>]*?&(?:amp;)?)?phone=)(?:\+|%2B)?([0-9]{8,15})(?![0-9])~i';

	/** El botón flotante o los iconos de contacto del constructor guardan el número aparte de la plataforma. */
	const WHATSAPP_FIELD = '~"(chat_button|contact_icon)_platform"\s*:\s*"whatsapp"[^{}]*?"\1_number"\s*:\s*"\+?([0-9][0-9 ()-]{6,22}[0-9])"|"(chat_button|contact_icon)_number"\s*:\s*"\+?([0-9][0-9 ()-]{6,22}[0-9])"[^{}]*?"\3_platform"\s*:\s*"whatsapp"~i';

	/**
	 * Los números de WhatsApp de un texto (HTML, JSON del constructor con `\/`, una URL), en orden y con repetidos.
	 *
	 * @return string[] Sólo dígitos, de 8 a 15.
	 */
	public static function whatsapp_numbers( string $text ): array {
		$text = str_replace( array( '\\/', '&#038;', '&amp;' ), array( '/', '&', '&' ), $text );
		$out  = array();
		if ( preg_match_all( self::WHATSAPP_LINK, $text, $all ) ) {
			foreach ( $all[1] as $digits ) {
				$out[] = $digits;
			}
		}
		if ( false !== stripos( $text, 'whatsapp' ) && preg_match_all( self::WHATSAPP_FIELD, $text, $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $m ) {
				$digits = (string) preg_replace( '/[^0-9]/', '', '' !== ( $m[2] ?? '' ) ? $m[2] : ( $m[4] ?? '' ) );
				if ( strlen( $digits ) >= 8 && strlen( $digits ) <= 15 ) {
					$out[] = $digits;
				}
			}
		}
		return $out;
	}

	/**
	 * El número que más pesa entre los hallazgos: lista de [texto, peso, origen]. Cada origen (una plantilla, un menú, una
	 * página) cuenta una sola vez por número, aunque lo repita: un pie con tres botones al mismo número no le gana a la
	 * cabecera por tener más botones. Devuelve [dígitos, origen, candidatos]: los candidatos (dígitos, peso y orígenes)
	 * de mayor a menor, para avisar si hay más de un número en el sitio. ['', '', []] si no hay ninguno.
	 */
	public static function pick_whatsapp( array $found ): array {
		$score  = array();
		$source = array();
		$seen   = array();
		foreach ( $found as $item ) {
			list( $text, $weight, $where ) = $item;
			foreach ( array_unique( self::whatsapp_numbers( (string) $text ) ) as $digits ) {
				$key = $digits . '|' . $where;
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ]        = true;
				$score[ $digits ]    = ( $score[ $digits ] ?? 0 ) + (int) $weight;
				$source[ $digits ][] = (string) $where;
			}
		}
		if ( ! $score ) {
			return array( '', '', array() );
		}
		arsort( $score );
		$candidates = array();
		foreach ( $score as $digits => $weight ) {
			$candidates[] = array(
				'whatsapp' => (string) $digits,
				'weight'   => $weight,
				'sources'  => $source[ $digits ],
			);
		}
		$digits = (string) array_key_first( $score );
		return array( $digits, $source[ $digits ][0], $candidates );
	}

	/** ¿Una plantilla del constructor se muestra en el sitio? (tiene condiciones de visualización: cabecera, pie…) */
	public static function in_use( $conditions ): bool {
		if ( is_string( $conditions ) && '' !== $conditions ) {
			$conditions = function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $conditions ) : @unserialize( $conditions, array( 'allowed_classes' => false ) ); // phpcs:ignore
		}
		return is_array( $conditions ) && (bool) array_filter( $conditions );
	}

	/** Cómo se nombra la hora del sitio en un texto: «hora del centro de México», «hora de Tijuana», «hora UTC»… */
	public static function timezone_label( string $tz ): string {
		$names = array(
			'America/Mexico_City'    => 'hora del centro de México',
			'America/Monterrey'      => 'hora del centro de México',
			'America/Merida'         => 'hora del centro de México',
			'America/Bahia_Banderas' => 'hora del centro de México',
			'America/Matamoros'      => 'hora de Matamoros',
			'America/Tijuana'        => 'hora de Tijuana',
			'America/Cancun'         => 'hora de Cancún',
			'America/Hermosillo'     => 'hora de Sonora',
			'America/Mazatlan'       => 'hora del Pacífico mexicano',
			'America/Chihuahua'      => 'hora de Chihuahua',
			'America/Ciudad_Juarez'  => 'hora de Ciudad Juárez',
			'America/Ojinaga'        => 'hora de Ojinaga',
		);
		$tz = trim( $tz );
		if ( isset( $names[ $tz ] ) ) {
			return $names[ $tz ];
		}
		if ( '' === $tz || 'UTC' === strtoupper( $tz ) || preg_match( '/^[+-]?0?0:?00$/', $tz ) ) {
			return 'hora UTC';
		}
		if ( preg_match( '/^[+-][0-9]{1,2}(:?[0-9]{2})?$/', $tz ) ) {
			return 'hora UTC' . $tz;
		}
		$city = substr( (string) strrchr( '/' . $tz, '/' ), 1 );
		return 'hora de ' . str_replace( '_', ' ', $city );
	}

	/**
	 * Lo que se sabe del sitio. Claves: `name`, `logo` (['id', 'url'] o null), `whatsapp` (dígitos o ''),
	 * `whatsapp_source`, `contact_url`, `links` (ruta del kit → URL real o ''), `currency`, `currency_symbol`,
	 * `timezone`, `gateways` (títulos de los medios de pago activos), `shipping` (['title', 'method', 'free'] de los
	 * envíos activos, sin recoger en tienda) y `whatsapp_candidates` (todos los números hallados).
	 */
	public static function read(): array {
		$site = array(
			'name'            => function_exists( 'get_bloginfo' ) ? trim( wp_strip_all_tags_safe( (string) get_bloginfo( 'name' ) ) ) : '',
			'logo'            => self::logo(),
			'whatsapp'        => '',
			'whatsapp_source' => '',
			'contact_url'     => self::page_url_by_slugs( self::CONTACT_SLUGS ),
			'links'           => array(),
			'currency'        => function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : 'MXN',
			'currency_symbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( (string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '$',
			'timezone'        => function_exists( 'wp_timezone_string' ) ? (string) wp_timezone_string() : '',
			'gateways'        => self::gateways(),
			'shipping'        => self::shipping(),
		);
		if ( '' === $site['currency'] ) {
			$site['currency'] = 'MXN';
		}
		foreach ( self::LINKS as $path => $how ) {
			$site['links'][ $path ] = self::link( $how['kind'], $how['slugs'] );
		}
		list( $site['whatsapp'], $site['whatsapp_source'], $site['whatsapp_candidates'] ) = self::pick_whatsapp( self::whatsapp_sources() );
		return $site;
	}

	/** El logo del sitio (el del tema o, si no hay, el icono del sitio): ['id' => int, 'url' => string] o null. */
	private static function logo(): ?array {
		if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
			return null;
		}
		$ids = array();
		if ( function_exists( 'get_theme_mod' ) ) {
			$ids[] = (int) get_theme_mod( 'custom_logo' );
		}
		if ( function_exists( 'get_option' ) ) {
			$ids[] = (int) get_option( 'site_icon' );
		}
		foreach ( $ids as $id ) {
			if ( $id > 0 ) {
				$url = (string) wp_get_attachment_image_url( $id, 'full' );
				if ( '' !== $url ) {
					return array(
						'id'  => $id,
						'url' => $url,
					);
				}
			}
		}
		return null;
	}

	/** La URL de una página publicada, o ''. */
	private static function published_url( int $id ): string {
		if ( $id <= 0 || ! function_exists( 'get_post_status' ) || 'publish' !== get_post_status( $id ) || ! function_exists( 'get_permalink' ) ) {
			return '';
		}
		return (string) get_permalink( $id );
	}

	/** La primera página publicada con una de esas direcciones, o ''. */
	private static function page_url_by_slugs( array $slugs ): string {
		if ( ! function_exists( 'get_page_by_path' ) ) {
			return '';
		}
		foreach ( $slugs as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page && isset( $page->ID ) ) {
				$url = self::published_url( (int) $page->ID );
				if ( '' !== $url ) {
					return $url;
				}
			}
		}
		return '';
	}

	/** La URL real de una ruta del kit (la página que el sitio declara para eso o la de su dirección), o ''. */
	private static function link( string $kind, array $slugs ): string {
		$id = 0;
		switch ( $kind ) {
			case 'privacy':
				$id = function_exists( 'get_option' ) ? (int) get_option( 'wp_page_for_privacy_policy' ) : 0;
				break;
			case 'terms':
				$id = function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : ( function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'terms' ) : 0 );
				break;
			case 'refund':
				$id = function_exists( 'get_option' ) ? (int) get_option( 'woocommerce_refund_returns_page_id' ) : 0;
				break;
			case 'cart':
			case 'checkout':
				$id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( $kind ) : 0;
				break;
		}
		$url = self::published_url( $id );
		return '' !== $url ? $url : self::page_url_by_slugs( $slugs );
	}

	/** Títulos de los medios de pago activos de la tienda. */
	private static function gateways(): array {
		if ( ! function_exists( 'WC' ) ) {
			return array();
		}
		$out = array();
		try {
			$wc       = WC();
			$gateways = $wc && method_exists( $wc, 'payment_gateways' ) ? $wc->payment_gateways()->payment_gateways() : array();
			foreach ( (array) $gateways as $gateway ) {
				if ( is_object( $gateway ) && 'yes' === ( $gateway->enabled ?? '' ) && method_exists( $gateway, 'get_title' ) ) {
					$title = trim( wp_strip_all_tags_safe( (string) $gateway->get_title() ) );
					if ( '' !== $title ) {
						$out[] = $title;
					}
				}
			}
		} catch ( \Throwable $e ) {
			return array();
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Los métodos de envío activos (todas las zonas), sin los de recoger en tienda: ['title', 'method', 'zone', 'free',
	 * 'condition']. `free` sólo con un envío gratis sin condiciones; uno que pide un mínimo o un cupón lleva su
	 * condición («en compras desde $1,500», «con cupón»). Cada método de cada zona por separado (no se juntan por título).
	 */
	private static function shipping(): array {
		if ( ! class_exists( '\WC_Shipping_Zones' ) ) {
			return array();
		}
		$out = array();
		try {
			$zones   = (array) \WC_Shipping_Zones::get_zones();
			$zones[] = array(
				'zone_id'          => 0,
				'shipping_methods' => ( new \WC_Shipping_Zone( 0 ) )->get_shipping_methods( true ),
			);
			foreach ( $zones as $zone ) {
				foreach ( (array) ( $zone['shipping_methods'] ?? array() ) as $method ) {
					if ( ! is_object( $method ) || 'yes' !== ( $method->enabled ?? '' ) || ! method_exists( $method, 'get_title' ) ) {
						continue;
					}
					$id    = (string) ( $method->id ?? '' );
					$title = trim( wp_strip_all_tags_safe( (string) $method->get_title() ) );
					if ( '' === $title || in_array( $id, self::PICKUP, true ) || preg_match( '/recoge|recolec|pickup|en tienda|en sucursal/iu', $title ) ) {
						continue;
					}
					$condition = 'free_shipping' === $id ? self::free_condition( (string) ( $method->requires ?? '' ), (string) ( $method->min_amount ?? '' ) ) : '';
					$out[]     = array(
						'title'     => $title,
						'method'    => $id,
						'zone'      => (int) ( $zone['zone_id'] ?? ( $zone['id'] ?? 0 ) ),
						'free'      => 'free_shipping' === $id && '' === $condition,
						'condition' => $condition,
					);
				}
			}
		} catch ( \Throwable $e ) {
			return array();
		}
		return $out;
	}

	/**
	 * La condición de un envío gratis (`requires` del método de la tienda: '', `min_amount`, `coupon`, `either` o
	 * `both`), o '' si no tiene: «en compras desde $1,500», «con cupón», «en compras desde $1,500 o con cupón»…
	 */
	public static function free_condition( string $requires, string $min_amount ): string {
		$amount = is_numeric( str_replace( ',', '', $min_amount ) ) ? (float) str_replace( ',', '', $min_amount ) : 0.0;
		$symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( (string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '$';
		$since  = $amount > 0 ? 'en compras desde ' . $symbol . number_format( $amount, abs( $amount - round( $amount ) ) < 0.005 ? 0 : 2, '.', ',' ) : 'con un monto mínimo de compra';
		switch ( $requires ) {
			case '':
				return '';
			case 'min_amount':
				return $since;
			case 'coupon':
				return 'con cupón';
			case 'either':
				return $since . ' o con cupón';
			case 'both':
				return $since . ' y con cupón';
		}
		return 'con condiciones';
	}

	/**
	 * Dónde puede estar el WhatsApp del sitio: ajustes de los botones de chat más comunes (peso 5), menús (3), plantillas
	 * de cabecera, pie y botón flotante del constructor (3) y páginas y entradas publicadas (1). Lista de [texto, peso,
	 * origen].
	 */
	private static function whatsapp_sources(): array {
		$found = array();
		// Los botones de chat más comunes, sólo si su plugin está activo (sus ajustes quedan al desactivarlo).
		if ( function_exists( 'get_option' ) ) {
			if ( defined( 'JOINCHAT_VERSION' ) || class_exists( '\Joinchat' ) ) {
				$chat = get_option( 'joinchat' );
				if ( is_array( $chat ) && ! empty( $chat['telephone'] ) ) {
					$found[] = array( 'wa.me/' . preg_replace( '/[^0-9]/', '', (string) $chat['telephone'] ), 5, 'el botón de chat del sitio' );
				}
			}
			if ( defined( 'HT_CTC_VERSION' ) ) {
				$ctc = get_option( 'ht_ctc_chat_options' );
				if ( is_array( $ctc ) && ! empty( $ctc['number'] ) ) {
					$found[] = array( 'wa.me/' . preg_replace( '/[^0-9]/', '', (string) $ctc['number'] ), 5, 'el botón de chat del sitio' );
				}
			}
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! isset( $wpdb->postmeta, $wpdb->posts ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return $found;
		}
		$likes = array( '%wa.me%', '%whatsapp.com%', '%whatsapp:%', '%"whatsapp"%' );
		$where = implode( ' OR ', array_fill( 0, count( $likes ), 'm.meta_value LIKE %s' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		// Primero la cabecera, el pie, el botón flotante y los menús, en su propia consulta: muchas páginas editadas hace
		// poco no los dejan fuera del límite.
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_type, p.post_title, m.meta_key, m.meta_value, c.meta_value AS conditions FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = '_elementor_conditions' WHERE p.post_status = 'publish' AND p.post_password = '' AND p.post_type IN ('elementor_library', 'e-floating-buttons', 'nav_menu_item') AND m.meta_key IN ('_elementor_data', '_menu_item_url') AND ( $where ) ORDER BY p.post_modified DESC LIMIT 200",
				...$likes
			),
			ARRAY_A
		);
		$rows = array_merge(
			$rows,
			(array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_type, p.post_title, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_status = 'publish' AND p.post_password = '' AND p.post_type NOT IN ('elementor_library', 'e-floating-buttons', 'nav_menu_item', 'revision') AND m.meta_key = '_elementor_data' AND ( $where ) ORDER BY p.post_modified DESC LIMIT 40",
					...$likes
				),
				ARRAY_A
			)
		);
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_type, post_title, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_password = '' AND post_type IN ('page', 'post', 'wp_block', 'wp_template_part', 'wp_navigation') AND ( post_content LIKE %s OR post_content LIKE %s ) ORDER BY post_modified DESC LIMIT 30",
				'%wa.me%',
				'%whatsapp.com%'
			),
			ARRAY_A
		);
		// phpcs:enable
		foreach ( (array) $rows as $row ) {
			$type = (string) ( $row['post_type'] ?? '' );
			if ( '_menu_item_url' === ( $row['meta_key'] ?? '' ) ) {
				$found[] = array( (string) $row['meta_value'], 3, 'el menú del sitio' ); // Todos los menús son un solo origen.
			} elseif ( 'e-floating-buttons' === $type || ( 'elementor_library' === $type && self::in_use( $row['conditions'] ?? null ) ) ) {
				// Una parte del sitio en uso: el botón flotante, o una cabecera o un pie con condiciones de visualización.
				$found[] = array( (string) $row['meta_value'], 4, sprintf( 'la plantilla «%s» del sitio (n.º %d: cabecera, pie o botón)', (string) $row['post_title'], (int) $row['ID'] ) );
			} else {
				$found[] = array( (string) $row['meta_value'], 1, sprintf( 'elementor_library' === $type ? 'la plantilla guardada «%s» (%d, sin uso en el sitio)' : 'la página «%s» (%d)', (string) $row['post_title'], (int) $row['ID'] ) );
			}
		}
		foreach ( (array) $posts as $row ) {
			$weight  = in_array( (string) ( $row['post_type'] ?? '' ), array( 'wp_template_part', 'wp_navigation' ), true ) ? 3 : 1;
			$found[] = array( (string) $row['post_content'], $weight, sprintf( 'la página «%s» (%d)', (string) $row['post_title'], (int) $row['ID'] ) );
		}
		return $found;
	}
}

/** `wp_strip_all_tags()` si existe; si no, `strip_tags()`. */
function wp_strip_all_tags_safe( string $text ): string {
	return function_exists( 'wp_strip_all_tags' ) ? (string) wp_strip_all_tags( $text ) : strip_tags( $text );
}
