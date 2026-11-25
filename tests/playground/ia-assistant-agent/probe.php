<?php
/**
 * Sonda del ensayo «el Asistente IA como agente» (mu-plugin, sólo en Playground).
 * - Sesión del conductor: `?digi_ias=login&token=…` inicia la sesión de admin (cookie) y `?digi_ias=nonces` devuelve
 *   los dos nonces del Asistente; el conductor habla después con la REST del Asistente como el navegador.
 * - Cada petición a api.anthropic.com queda resumida en digi_ias_seen (modelo, cuántos mensajes y resultados, y si
 *   lo que dijo la persona sigue en ella: «purificador», «7500», «fin de mes»), sin la clave.
 * - Modo simulado: la sonda contesta por api.anthropic.com con el formato real de la API (tool_use / text) y guioniza
 *   la conversación del caso plomerito. Modo real: deja pasar la petición a Anthropic.
 * - Una Ability de prueba que escribe sin ser borrador (digi-ias/apply-offer: pone el precio de oferta del producto)
 *   para comprobar la confirmación escrita («sí hazlo»).
 * - `?digi_ias=state` devuelve lo creado (páginas en borrador con su texto, marcadores, enlaces y WhatsApp), el
 *   producto, lo visto por el modelo y la bitácora; `?digi_ias=wipe` borra la clave del proveedor.
 * Cualquier otro destino externo queda sin red. Un aviso de PHP de IA Tools queda en /digi-ias/php-errors.log.
 */
defined( 'ABSPATH' ) || exit;
const DIGI_IAS_DIR = '/digi-ias';

add_filter( 'pre_wp_mail', '__return_true' );
set_error_handler(
	static function ( $no, $message, $file ) {
		if ( false !== strpos( (string) $file, 'digitalisimo.chatbot' ) ) {
			file_put_contents( DIGI_IAS_DIR . '/php-errors.log', $no . ' ' . $message . ' @ ' . $file . "\n", FILE_APPEND );
		}
		return false;
	}
);

function digi_ias_mode() {
	static $mode = null;
	if ( null === $mode ) {
		$mode = trim( (string) @file_get_contents( DIGI_IAS_DIR . '/mode.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
	return $mode;
}

function digi_ias_reply( array $content, $stop = 'end_turn' ) {
	return array( 'headers' => array(), 'body' => wp_json_encode( array( 'id' => 'msg_ias', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5', 'content' => $content, 'stop_reason' => $stop ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
}

function digi_ias_tools( array $calls, $text = '' ) {
	$content = '' !== $text ? array( array( 'type' => 'text', 'text' => $text ) ) : array();
	foreach ( $calls as $i => $call ) {
		$content[] = array( 'type' => 'tool_use', 'id' => 'toolu_' . substr( md5( microtime() . $i . $call[0] ), 0, 16 ), 'name' => $call[0], 'input' => $call[1] ? $call[1] : new stdClass() );
	}
	return digi_ias_reply( $content, 'tool_use' );
}

/** Lo que dijo la persona (textos de los mensajes user) y el último turno: sus llamadas y resultados. */
function digi_ias_read( array $body ) {
	$users = array();
	$names = array();
	$turn  = array();
	foreach ( (array) $body['messages'] as $m ) {
		foreach ( (array) $m['content'] as $block ) {
			$type = $block['type'] ?? '';
			if ( 'user' === $m['role'] && 'text' === $type ) {
				$users[] = (string) $block['text'];
				$turn    = array();
			} elseif ( 'tool_use' === $type ) {
				$names[ $block['id'] ] = $block['name'];
			} elseif ( 'tool_result' === $type ) {
				$turn[] = array( 'name' => $names[ $block['tool_use_id'] ] ?? '', 'content' => is_string( $block['content'] ) ? $block['content'] : wp_json_encode( $block['content'] ) );
			}
		}
	}
	$last = end( $body['messages'] );
	$tail = end( $last['content'] );
	return array( 'users' => $users, 'last_user' => (string) end( $users ), 'turn' => $turn, 'after_tools' => 'tool_result' === ( $tail['type'] ?? '' ) );
}

/** El ID que aparece en un resultado (primer «"id":N» o «"post_id":N»). */
function digi_ias_id( $content, $key = 'id' ) {
	return preg_match( '/"' . $key . '"\s*:\s*(\d+)/', (string) $content, $m ) ? (int) $m[1] : 0;
}

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
		$home = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( 'api.anthropic.com' !== $host ) {
			return in_array( $host, array( $home, '127.0.0.1', 'localhost' ), true ) ? $pre : new WP_Error( 'digi_ias_offline', 'Sin red en el ensayo.' );
		}
		$body = json_decode( (string) $args['body'], true );
		$read = digi_ias_read( $body );
		$all  = strtolower( implode( "\n", $read['users'] ) );
		$seen = get_option( 'digi_ias_seen', array() );
		$seen['requests'][] = array(
			'model'      => (string) ( $body['model'] ?? '' ),
			'messages'   => count( (array) $body['messages'] ),
			'results'    => substr_count( (string) $args['body'], '"tool_result"' ),
			'bytes'      => strlen( (string) $args['body'] ),
			'last_user'  => mb_substr( $read['last_user'], 0, 120 ),
			'purificador' => false !== strpos( $all, 'purificador' ),
			'precio'     => false !== strpos( $all, '7500' ),
			'fin_de_mes' => false !== strpos( $all, 'fin de mes' ),
			'cache'      => isset( $body['cache_control'] ),
			'tools'      => count( (array) ( $body['tools'] ?? array() ) ),
		);
		update_option( 'digi_ias_seen', $seen, false );
		if ( 'real' === digi_ias_mode() ) {
			return $pre; // A Anthropic de verdad.
		}
		return digi_ias_script( $read, $seen );
	},
	10,
	3
);

/** El modelo simulado: la conversación del caso plomerito, paso a paso. */
function digi_ias_script( array $read, array $seen ) {
	$user  = strtolower( remove_accents( $read['last_user'] ) );
	$names = array_column( $read['turn'], 'name' );
	$find  = function ( $name ) use ( $read ) {
		foreach ( array_reverse( $read['turn'] ) as $r ) {
			if ( $r['name'] === $name ) {
				return $r['content'];
			}
		}
		return '';
	};
	if ( false !== strpos( $user, 'en que puedes ayudarme' ) ) {
		return digi_ias_reply( array( array( 'type' => 'text', 'text' => 'Puedo crear landings, revisar productos, SEO y más.' ) ) );
	}
	if ( false !== strpos( $user, 'impact5000' ) ) {
		return $read['after_tools'] ? digi_ias_reply( array( array( 'type' => 'text', 'text' => 'No encontré Impact5000 en tu tienda. ¿Para qué producto es la promoción?' ) ) ) : digi_ias_tools( array( array( 'digitalisimo-woo-products', array( 'action' => 'list', 'query' => array( 'search' => 'impact5000' ) ) ) ) );
	}
	if ( false !== strpos( $user, 'purificador alcalinizador' ) ) {
		if ( ! $read['after_tools'] ) {
			return digi_ias_tools( array( array( 'digitalisimo-woo-products', array( 'action' => 'list', 'query' => array( 'search' => 'purificador' ) ) ) ) );
		}
		$id = digi_ias_id( $find( 'digitalisimo-woo-products' ) );
		return digi_ias_reply( array( array( 'type' => 'text', 'text' => 'Encontré el Purificador y Alcalinizador de Agua sobre tarja Negro ROTOPLAS (ID ' . $id . ', $10,000). Lo dejo de $10,000 a $7,500.' ) ) );
	}
	if ( false !== strpos( $user, 'creala, la oferta' ) ) {
		if ( ! $read['after_tools'] ) {
			$calls = array( array( 'digitalisimo-ia-site-contact', array() ), array( 'digitalisimo-ia-read-page-text', array( 'slug' => 'aviso-de-privacidad' ) ), array( 'digitalisimo-ia-read-page-text', array( 'slug' => 'reembolsos-y-devoluciones' ) ), array( 'digitalisimo-woo-products', array( 'action' => 'list', 'query' => array( 'search' => 'purificador' ) ) ) );
			while ( count( $calls ) < 19 ) {
				$calls[] = array( 0 === count( $calls ) % 2 ? 'digitalisimo-sales-guide' : 'digitalisimo-sales-list-templates', array() );
			}
			return digi_ias_tools( $calls, 'Reviso el sitio: contacto, políticas, producto y la guía.' );
		}
		$lastname = (string) end( $names );
		if ( 'digi-ias-apply-offer' === $lastname ) {
			return digi_ias_reply( array( array( 'type' => 'text', 'text' => 'Listo: el precio de oferta quedó aplicado hasta fin de mes.' ) ) );
		}
		if ( 'digitalisimo-sales-create-page' === $lastname ) {
			$id = digi_ias_id( $find( 'digitalisimo-sales-create-page' ) );
			return digi_ias_tools( array( array( 'digitalisimo-ia-read-page-text', array( 'id' => $id ) ) ), 'Verifico lo creado.' );
		}
		if ( 'digitalisimo-ia-read-page-text' === $lastname && in_array( 'digitalisimo-sales-create-page', $names, true ) ) {
			$draft = digi_ias_id( $find( 'digitalisimo-ia-read-page-text' ) );
			return digi_ias_tools( array( array( 'digi-ias-apply-offer', array( 'sale_price' => '7500', 'until' => wp_date( 'Y-m-t' ) . ' 23:59', 'draft' => $draft ) ) ), 'Creé el borrador. ¿Aplico también el precio de oferta de $7,500 en la tienda hasta el fin de mes?' );
		}
		$contact = $find( 'digitalisimo-ia-site-contact' );
		$seen    = get_option( 'digi_ias_seen', array() );
		$seen['contact_has_whatsapp'] = false !== strpos( $contact, '5218711234567' );
		$seen['memory_after_19']      = end( $seen['requests'] );
		update_option( 'digi_ias_seen', $seen, false );
		return digi_ias_tools( array( array( 'digitalisimo-sales-create-page', array( 'template' => 'venta-corta', 'title' => 'Oferta de fin de mes · Purificador ROTOPLAS', 'whatsapp' => $seen['contact_has_whatsapp'] ? '5218711234567' : '5210000000000' ) ) ), 'Creo la landing en borrador con el producto, el precio de oferta y el WhatsApp del sitio.' );
	}
	if ( false !== strpos( $user, 'si hazlo' ) || ( $read['after_tools'] && in_array( 'digi-ias-apply-offer', $names, true ) ) ) {
		return digi_ias_reply( array( array( 'type' => 'text', 'text' => 'Listo: el precio de oferta quedó aplicado hasta fin de mes.' ) ) );
	}
	return digi_ias_reply( array( array( 'type' => 'text', 'text' => 'Respuesta simulada.' ) ) );
}

add_action(
	'wp_abilities_api_categories_init',
	static function () {
		wp_register_ability_category( 'digi-ias', array( 'label' => 'Ensayo del Asistente como agente', 'description' => 'Abilities de prueba.' ) );
	}
);
add_action(
	'wp_abilities_api_init',
	static function () {
		wp_register_ability(
			'digi-ias/apply-offer',
			array(
				'label'               => 'Aplicar el precio de oferta del producto del ensayo',
				'description'         => 'Pone el precio de oferta (sale_price) del producto del ensayo. Cambia la tienda: pide confirmación.',
				'category'            => 'digi-ias',
				'input_schema'        => array( 'type' => 'object', 'properties' => array( 'sale_price' => array( 'type' => 'string' ), 'until' => array( 'type' => 'string' ), 'draft' => array( 'type' => 'integer' ) ), 'required' => array( 'sale_price' ) ),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => static function () { return current_user_can( 'edit_products' ); },
				'execute_callback'    => static function ( $in ) {
					$ready   = json_decode( (string) file_get_contents( DIGI_IAS_DIR . '/ready.json' ), true );
					$product = wc_get_product( (int) $ready['product'] );
					$product->set_sale_price( (string) $in['sale_price'] );
					$product->save();
					return array( 'product' => $product->get_id(), 'sale_price' => $product->get_sale_price() );
				},
				'meta'                => array( 'show_in_rest' => true, 'annotations' => array( 'readonly' => false, 'destructive' => false ), 'mcp' => array( 'public' => true, 'type' => 'tool' ) ),
			)
		);
	}
);

add_action(
	'wp_loaded',
	static function () {
		if ( ! isset( $_GET['digi_ias'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$token = trim( (string) @file_get_contents( DIGI_IAS_DIR . '/token.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( '' === $token || ! hash_equals( $token, (string) ( $_GET['token'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
			wp_send_json( array( 'error' => 'token' ), 403 );
		}
		$what  = sanitize_key( (string) $_GET['digi_ias'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ready = json_decode( (string) @file_get_contents( DIGI_IAS_DIR . '/ready.json' ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( 'login' === $what ) {
			wp_set_auth_cookie( (int) $ready['admin'], false );
			wp_send_json( array( 'ok' => true ) );
		}
		if ( 'nonces' === $what ) {
			wp_send_json( array( 'user' => get_current_user_id(), 'rest' => wp_create_nonce( 'wp_rest' ), 'assistant' => wp_create_nonce( Digitalisimo_AI_Assistant::NONCE ), 'model' => ( Digitalisimo_AI_Assistant_Model::target( 'auto' )['model'] ?? '' ) ) );
		}
		// Como el dueño: publica la landing (para el turno «a mi landing publicada…» del ensayo «mostrar»).
		if ( 'publish' === $what ) {
			$id = absint( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$ok = $id && 'page' === get_post_type( $id ) && ! is_wp_error( wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) ) );
			wp_send_json( array( 'published' => $ok, 'status' => get_post_status( $id ) ) );
		}
		if ( 'wipe' === $what ) {
			$o = get_option( 'digitalisimo_ai_options', array() );
			$o['providers']['anthropic']['key'] = '';
			update_option( 'digitalisimo_ai_options', $o, false );
			wp_send_json( array( 'wiped' => true ) );
		}
		// state: lo creado por el Asistente y lo visto por el modelo (un error se devuelve como JSON, no como pantalla).
		try {
		$known = array_map( 'intval', array_values( (array) $ready['pages'] ) );
		$pages = get_posts( array( 'post_type' => array( 'page', 'post', 'elementor_library' ), 'post_status' => array( 'draft', 'pending', 'private', 'publish' ), 'posts_per_page' => 50, 'orderby' => 'ID', 'order' => 'DESC', 'suppress_filters' => true ) );
		$made  = array();
		$fake  = '/\[[^\]\n]{2,80}\]|lorem ipsum|5210000000000|52XXXXXXXXXX|tu (n[uú]mero|logo|nombre)|nombre del producto|precio aqu[ií]/iu';
		foreach ( $pages as $post ) {
			if ( (int) $post->ID <= (int) ( $ready['last_id'] ?? 0 ) || in_array( (int) $post->ID, $known, true ) || (int) $post->ID === (int) $ready['footer'] || 'draft' !== $post->post_status ) {
				continue;
			}
			$content = Digitalisimo_AI_Site_Reader::content( $post );
			$raw     = (string) get_post_meta( $post->ID, '_elementor_data', true ) . ' ' . (string) $post->post_content;
			preg_match_all( $fake, $content['text'], $visible );
			$wa = array();
			$broken = array();
			foreach ( $content['links'] as $link ) {
				if ( preg_match( '#wa\.me/(\d+)#', $link['url'], $m ) ) {
					$wa[] = $m[1];
				}
				$host = (string) wp_parse_url( $link['url'], PHP_URL_HOST );
				if ( 0 === strpos( $link['url'], '/' ) || $host === (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) {
					$path = trim( (string) wp_parse_url( $link['url'], PHP_URL_PATH ), '/' );
					if ( '' !== $path && 0 !== strpos( $path, 'wp-content/' ) && ! preg_match( '/\.[a-z0-9]{2,5}$/i', $path ) && ! url_to_postid( $link['url'] ) && ! get_page_by_path( $path ) && ! get_page_by_path( $path, OBJECT, 'product' ) ) {
						$broken[] = $link['url'];
					}
				}
			}
			$made[] = array(
				'id'           => (int) $post->ID,
				'title'        => (string) $post->post_title,
				'type'         => (string) $post->post_type,
				'status'       => (string) $post->post_status,
				'preview'      => add_query_arg( array( 'page_id' => $post->ID, 'preview' => 'true' ), home_url( '/' ) ),
				'text'         => mb_substr( $content['text'], 0, 20000 ),
				'placeholders' => array_values( array_unique( $visible[0] ) ),
				'raw_brackets' => preg_match_all( '/\[[A-ZÁÉÍÓÚÑ][^\]\n]{2,80}\]/u', $raw ),
				'whatsapp'     => array_values( array_unique( $wa ) ),
				'broken'       => array_values( array_unique( $broken ) ),
				'links'        => array_slice( $content['links'], 0, 60 ),
				'product'      => (bool) preg_match( '/purificador|rotoplas/i', $content['text'] ),
				// La fecha que guarda el contador (en la zona del sitio) y si el borrador lo marcó el kit o el Asistente.
				'due'          => preg_match_all( '/"due_date"\s*:\s*"([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2})/', $raw, $due ) ? array_values( array_unique( $due[1] ) ) : array(),
				'kit'          => is_array( get_post_meta( $post->ID, '_digitalisimo_sales_kit', true ) ),
				'assistant'    => (int) get_post_meta( $post->ID, '_digitalisimo_ai_assistant_draft', true ),
				'price'        => (bool) preg_match( '/7[,.]?500/', $content['text'] ),
			);
		}
		$product = wc_get_product( (int) $ready['product'] );
		wp_send_json(
			array(
				'made'    => $made,
				'product' => array( 'id' => $product->get_id(), 'regular' => $product->get_regular_price(), 'sale' => $product->get_sale_price(), 'modified' => (string) $product->get_date_modified() ),
				'site_tz' => wp_timezone_string(),
				// Todas las páginas nuevas (también las publicadas), con la huella de su contenido.
				'pages'   => array_values( array_map( static function ( $p ) { return array( 'id' => (int) $p->ID, 'status' => (string) $p->post_status, 'title' => (string) $p->post_title, 'hash' => md5( (string) get_post_meta( $p->ID, '_elementor_data', true ) ) ); }, array_filter( get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'posts_per_page' => 50, 'suppress_filters' => true ) ), static function ( $p ) use ( $ready ) { return (int) $p->ID > (int) ( $ready['last_id'] ?? 0 ); } ) ) ),
				'seen'    => get_option( 'digi_ias_seen', array() ),
				'log'     => get_option( 'digitalisimo_ai_assistant_log', array() ),
				'key_set' => '' !== (string) ( get_option( 'digitalisimo_ai_options', array() )['providers']['anthropic']['key'] ?? '' ),
				// needs_review() con el wp_kses_post() real de WordPress: las evasiones van a la tarjeta, el texto normal no.
				'review'  => array_map(
					static function ( $html ) {
						return Digitalisimo_AI_Assistant_Tools::needs_review( is_array( $html ) ? $html : array( 'post_id' => 1, 'settings' => array( 'html' => $html ) ) );
					},
					array(
						'evade-entity'  => '<a href="java&#115;cript:alert(1)">x</a>',
						'evade-tab'     => "<a href=\"java\tscript:alert(1)\">x</a>",
						'evade-colon'   => '<a href="javascript&colon;alert(1)">x</a>',
						'evade-meta'    => '<meta http-equiv="refresh" content="0;url=//x.test">',
						'evade-base'    => '<base href="//x.test/">',
						'evade-style'   => '<style>@import url(//x.test/a.css)</style>',
						'plain-text'    => 'Oferta de fin de mes',
						'plain-html'    => '<p>Hasta el <strong>30 de noviembre</strong></p>',
						// Una composición real de build-composition (XML e-* con configuration-id, & y <br/>): no.
						'plain-composition' => array( 'post_id' => 1, 'xml_structure' => '<e-flexbox configuration-id="hero"><e-heading configuration-id="title"></e-heading><e-paragraph configuration-id="p1"></e-paragraph></e-flexbox>', 'element_config' => array( 'title' => array( 'title' => 'Oferta & Black Friday' ), 'p1' => array( 'paragraph' => 'Antes <s>$10,000</s><br/>hoy <strong>$7,500</strong>' ), 'btn' => array( 'link' => array( 'destination' => 'https://wa.me/5218711234567' ) ) ), 'style' => array( 'hero' => 'padding:40px 20px; &:hover{color:#fff}' ) ),
						'evade-destination' => array( 'post_id' => 1, 'xml_structure' => '<e-button configuration-id="btn"></e-button>', 'element_config' => array( 'btn' => array( 'link' => array( 'destination' => 'java&#115;cript:alert(1)' ) ) ) ),
					)
				),
			),
			null,
			JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
		);
		} catch ( Throwable $e ) {
			wp_send_json( array( 'error' => $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() ) );
		}
	},
	1
);
