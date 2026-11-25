<?php
namespace Digitalisimo\Elements\Sales\Mcp;

use Digitalisimo\Elements\Sales\Brand;
use Digitalisimo\Elements\Sales\Builder;
use Digitalisimo\Elements\Sales\Catalog;
use Digitalisimo\Elements\Sales\Pages;
use Digitalisimo\Elements\Sales\Placeholders;
use Digitalisimo\Elements\Sales\Product;
use Digitalisimo\Elements\Sales\Site;
use Digitalisimo\Elements\Sales\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Ejecuta las herramientas del kit de ventas: permiso (también aquí, no sólo en `WP_Ability`), entrada cerrada y la
 * acción. Sin telemetría; nada sale del sitio.
 */
final class Executor {
	/** El permiso: `edit_pages` (crear borradores). `publish_pages` nunca se consulta. @return true|\WP_Error */
	public static function permission( string $tool, $input = null ) {
		unset( $input );
		if ( '' === Tools::tool_of( Tools::id( $tool ) ) ) {
			return self::forbidden();
		}
		return current_user_can( Tools::FLOOR ) ? true : self::forbidden();
	}

	private static function forbidden(): \WP_Error {
		return new \WP_Error(
			'digitalisimo_sales_forbidden',
			'Tu usuario no tiene permiso para esto: el kit de ventas exige poder editar páginas («edit_pages»).',
			array( 'status' => 403 )
		);
	}

	/**
	 * @param string        $tool   Herramienta.
	 * @param mixed         $input  Entrada del cliente.
	 * @param callable|null $create Para las pruebas: `function( $title, $elements )` en lugar de `Pages::create()`.
	 * @param array         $deps   Para las pruebas: `site` (en lugar de `Site::read()`), `product` (`function( $id )`
	 *                              en lugar de `Product::read()`), `regenerable` (en lugar de `Pages::regenerable()`),
	 *                              `brand` (en lugar de `Brand::read()`), `catalog` (`function( $category )` en lugar de
	 *                              `Store::catalog()`) y `docs` (en lugar de `Brand::design_docs()`).
	 * @return array|\WP_Error
	 */
	public static function run( string $tool, $input, $create = null, array $deps = array() ) {
		$allowed = self::permission( $tool, $input );
		if ( true !== $allowed ) {
			return $allowed;
		}
		$input   = is_array( $input ) ? $input : array();
		$schema  = Tools::input_schema( $tool );
		$unknown = array_diff( array_keys( $input ), array_keys( $schema['properties'] ) );
		if ( $unknown ) {
			return self::invalid( sprintf( 'Parámetros desconocidos: %s. Admite: %s.', implode( ', ', $unknown ), implode( ', ', array_keys( $schema['properties'] ) ) ) );
		}
		foreach ( $schema['required'] ?? array() as $key ) {
			if ( ! isset( $input[ $key ] ) || '' === $input[ $key ] ) {
				return self::invalid( sprintf( 'Falta %s.', $key ) );
			}
		}
		if ( isset( $input['template'] ) && ! in_array( $input['template'], Catalog::ids(), true ) ) {
			return self::invalid( sprintf( 'template debe ser uno de: %s.', implode( ', ', Catalog::ids() ) ) );
		}
		switch ( $tool ) {
			case 'list-templates':
				return self::list_templates( (string) ( $input['template'] ?? '' ) );
			case 'guide':
				return self::guide( (string) ( $input['section'] ?? '' ) );
			default:
				return self::create_page( $input, is_callable( $create ) ? $create : array( Pages::class, 'create' ), $deps );
		}
	}

	private static function invalid( string $message ): \WP_Error {
		return new \WP_Error( 'digitalisimo_sales_invalid', $message, array( 'status' => 400 ) );
	}

	private static function list_templates( string $id ): array {
		$manifest  = Catalog::manifest();
		$templates = array();
		foreach ( $manifest['templates'] ?? array() as $meta ) {
			if ( '' !== $id && $id !== $meta['id'] ) {
				continue;
			}
			$entry = array(
				'id'             => $meta['id'],
				'number'         => $meta['number'],
				'name'           => $meta['name'],
				'purpose'        => $meta['purpose'],
				'use_when'       => $meta['use_when'],
				'measures'       => $meta['measures'],
				'sections'       => $meta['sections'],
				'widgets'        => $meta['widgets'],
				'has_countdown'  => $meta['has_countdown'],
				'has_form'       => $meta['has_form'],
				'internal_links' => $meta['internal_links'],
				'blocks'         => array_map(
					static function ( $block ) {
						return array(
							'key'          => $block['key'],
							'name'         => $block['name'],
							'required'     => (bool) $block['required'],
							'required_for' => array_values( (array) ( $block['required_for'] ?? array() ) ),
							'price'        => ! empty( $block['price_values'] ),
						);
					},
					(array) ( $meta['blocks'] ?? array() )
				),
			);
			$data  = Catalog::load( $meta['id'], 'sections' );
			$found = $data ? Placeholders::summary( $data['content'] ) : array();
			$entry['placeholders_count'] = count( $found );
			$entry['close_date']         = Builder::takes_close_date( $meta, $found );
			if ( '' !== $id ) {
				$list = array();
				foreach ( $found as $text => $info ) {
					$list[] = array(
						'placeholder' => (string) $text,
						'count'       => $info['count'],
						'kind'        => $info['kind'],
					);
				}
				$entry['placeholders'] = $list;
				if ( $meta['has_countdown'] ) {
					$entry['urgency'] = sprintf( 'Sin close_date, create-page quita %d bloques que sólo sirven con un cierre real (barra de cierre, aviso o contador).', count( $meta['urgency'] ) );
				}
			}
			$templates[] = $entry;
		}
		$out = array(
			'ok'        => true,
			'tool'      => 'list-templates',
			'variant'   => Catalog::variant(),
			'funnel'    => $manifest['funnel'] ?? '',
			'templates' => $templates,
			'rules'     => 'Para la promo de un producto de la tienda usa create-page con product_id y offer: arma la landing con los datos reales del producto y del sitio, sin llenar placeholders. Las páginas se crean en borrador y terminadas (ningún placeholder a la vista); nada se inventa (testimonios, reseñas, cifras y fechas sólo reales: del usuario o del sitio); urgencia sólo con un cierre real; precios en MXN y sólo en los bloques con price: true (la oferta, el cierre que la repite, los caminos, «Cuánto vale para ti», el carrito y la oferta única): un valor con precio en otro bloque se rechaza; llamado a la acción por WhatsApp; enlaces del propio sitio; los bloques se pueden quitar, nunca reordenar, salvo los obligatorios (required: true): la promesa, la oferta, la garantía, las preguntas frecuentes, el bloque whatsapp, el pie y el formulario no se quitan; las pruebas sociales sí. Detalle: digitalisimo-sales/guide.',
			'required'  => $manifest['required_kinds'] ?? array(),
		);
		if ( '' === $id ) {
			$out['cases']  = $manifest['cases'] ?? array();
			$out['notice'] = 'Pide list-templates con template=<id> para ver sus placeholders exactos antes de create-page.';
		}
		return $out;
	}

	private static function guide( string $section ) {
		$text = Catalog::guide( $section );
		if ( '' === $text ) {
			return new \WP_Error( 'digitalisimo_sales_guide', 'La guía no está disponible.', array( 'status' => 404 ) );
		}
		return array(
			'ok'       => true,
			'tool'     => 'guide',
			'section'  => '' === $section ? 'todo' : $section,
			'mimeType' => 'text/markdown',
			'content'  => $text,
		);
	}

	private static function create_page( array $input, callable $create, array $deps ) {
		$finish     = ! array_key_exists( 'finish', $input ) || ! empty( $input['finish'] );
		$regenerate = $input['post_id'] ?? 0;
		if ( ! is_int( $regenerate ) && ! ( is_string( $regenerate ) && ctype_digit( $regenerate ) ) || (int) $regenerate < 0 ) {
			return self::invalid( 'post_id debe ser el ID (número) de un borrador que creó el kit.' );
		}
		$regenerate = (int) $regenerate;
		if ( $regenerate > 0 ) {
			// Antes de armar nada: sólo un borrador del kit de este sitio, editable y sin publicar.
			$ok = isset( $deps['regenerable'] ) && is_callable( $deps['regenerable'] ) ? call_user_func( $deps['regenerable'], $regenerate ) : Pages::regenerable( $regenerate );
			if ( true !== $ok ) {
				return $ok;
			}
		}
		$layout = ! empty( $input['blank_page'] ) ? 'blank' : 'site';
		if ( isset( $input['store_offer'] ) ) {
			foreach ( array( 'product_id', 'offer', 'template', 'replacements', 'omit_blocks', 'close_date' ) as $key ) {
				if ( isset( $input[ $key ] ) ) {
					return self::invalid( sprintf( 'store_offer (oferta de toda la tienda o de una categoría) va sin %s: la landing se arma sola con los productos reales.', $key ) );
				}
			}
			return self::store_offer( $input, $create, $deps, $regenerate, $layout );
		}
		$product_id = $input['product_id'] ?? null;
		if ( null !== $product_id && ( ! is_int( $product_id ) && ! ( is_string( $product_id ) && ctype_digit( $product_id ) ) || (int) $product_id <= 0 ) ) {
			return self::invalid( 'product_id debe ser el ID (número) de un producto de la tienda.' );
		}
		if ( null === $product_id && isset( $input['offer'] ) ) {
			return self::invalid( 'offer va con product_id: es la oferta de un producto de la tienda.' );
		}
		if ( null !== $product_id && isset( $input['template'] ) && Product::TEMPLATE !== $input['template'] ) {
			return self::invalid( sprintf( 'Con product_id la plantilla es %s (la landing de producto); quita template o usa %s.', Product::TEMPLATE, Product::TEMPLATE ) );
		}
		if ( null === $product_id && ( ! isset( $input['template'] ) || '' === $input['template'] ) ) {
			return self::invalid( 'Falta template (o product_id, para la landing de un producto de la tienda).' );
		}
		if ( null !== $product_id && array_key_exists( 'finish', $input ) && empty( $input['finish'] ) ) {
			return self::invalid( 'La landing de un producto siempre sale terminada (finish no se puede apagar con product_id).' );
		}
		$id      = null !== $product_id ? Product::TEMPLATE : (string) $input['template'];
		$meta    = Catalog::meta( $id );
		$variant = Catalog::variant();
		$data    = $meta ? Catalog::load( $id, $variant ) : null;
		if ( ! $data ) {
			return new \WP_Error( 'digitalisimo_sales_template', sprintf( 'No se pudo leer la plantilla %s (%s).', $id, $variant ), array( 'status' => 500 ) );
		}
		$env  = array(
			'site_host' => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			// Las fechas del dueño (ends, close_date) van en la hora de la tienda; el contador se guarda en la del sitio.
			'timezone'      => Site::sales_timezone(),
			'site_timezone' => wp_timezone(),
			'now'       => time(),
		);
		$site = isset( $deps['site'] ) && is_array( $deps['site'] ) ? $deps['site'] : Site::read();

		$replacements = $input['replacements'] ?? array();
		$omit         = $input['omit_blocks'] ?? array();
		$close_date   = $input['close_date'] ?? '';
		$product      = null;
		$landing      = null;
		if ( null !== $product_id ) {
			$product = isset( $deps['product'] ) && is_callable( $deps['product'] ) ? call_user_func( $deps['product'], (int) $product_id ) : Product::read( (int) $product_id );
			if ( is_wp_error( $product ) ) {
				return $product;
			}
			$offer = Product::offer( $product, $input['offer'] ?? null, $env );
			if ( is_wp_error( $offer ) ) {
				return $offer;
			}
			$landing = Product::page( $data, $product, $offer, $site );
			if ( is_wp_error( $landing ) ) {
				return $landing;
			}
			if ( ! is_array( $replacements ) || ! is_array( $omit ) ) {
				return self::invalid( 'replacements debe ser un objeto y omit_blocks una lista.' );
			}
			$data         = $landing['template'];
			$finish       = true;
			$replacements = $replacements + $landing['replacements'];
			$omit         = array_values( array_unique( array_merge( $omit, $landing['omit'] ) ) );
			if ( '' === $close_date ) {
				$close_date = $landing['close_date'];
			}
		}
		$title = isset( $input['title'] ) && is_string( $input['title'] ) ? trim( sanitize_text_field( $input['title'] ) ) : '';
		if ( '' === $title && $product ) {
			$title = trim( ( '' !== $offer['label'] ? $offer['label'] . ': ' : '' ) . sanitize_text_field( $product['name'] ) );
		}
		if ( '' === $title ) {
			return self::invalid( isset( $input['title'] ) ? 'title debe ser un texto.' : 'Falta title.' );
		}

		// WhatsApp: el que se pidió o, si no, el que el sitio ya publica (enlaces wa.me de menús, cabecera, pie, páginas).
		$whatsapp        = isset( $input['whatsapp'] ) ? $input['whatsapp'] : '';
		$whatsapp_source = '' !== $whatsapp ? 'el número que se pidió' : '';
		if ( '' === $whatsapp && '' !== (string) ( $site['whatsapp'] ?? '' ) ) {
			$whatsapp        = (string) $site['whatsapp'];
			$whatsapp_source = (string) $site['whatsapp_source'];
		}
		$built = Builder::build(
			$data,
			$meta,
			array(
				'replacements' => $replacements,
				'omit_blocks'  => $omit,
				'whatsapp'     => $whatsapp,
				'close_date'   => $close_date,
				'finish'       => $finish,
			),
			$env + array( 'site' => $site )
		);
		if ( is_wp_error( $built ) ) {
			return $built;
		}
		// Los colores, las tipografías y los botones del sitio, nunca los de DIGITALÍSIMO (caso plomerito 3).
		$brand             = self::brand( $deps );
		$built['elements'] = Brand::apply( $built['elements'], $brand );
		$page              = call_user_func( $create, $title, $built['elements'], $regenerate, $id, $layout );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		$fin     = (array) ( $built['finish'] ?? array() );
		$missing = $finish ? (array) ( $fin['missing_pages'] ?? array() ) : null;
		$links   = array();
		foreach ( $meta['internal_links'] as $link ) {
			if ( '/' === $link['path'][0] && ( null === $missing || in_array( $link['path'], $missing, true ) ) ) {
				$links[] = $link;
			}
		}
		$filled_from = array();
		if ( $landing ) {
			$filled_from = $landing['filled'];
		}
		foreach ( (array) ( $fin['filled'] ?? array() ) as $item ) {
			$filled_from[] = $item;
		}
		if ( '' !== $whatsapp && ! $built['whatsapp_pending'] ) {
			$filled_from[] = array(
				'field'  => 'whatsapp',
				'source' => $whatsapp_source,
			);
		}
		if ( 'site' === ( $fin['logo'] ?? null ) ) {
			$filled_from[] = array(
				'field'  => 'logo',
				'source' => 'el logo del sitio',
			);
		}
		$pending = $finish ? array_merge( $landing ? $landing['pending'] : array(), (array) ( $fin['pending'] ?? array() ) ) : $built['pending'];
		$review  = array();
		if ( $built['real_data'] ) {
			$review[] = 'Comprueba que estos datos son reales y tienen permiso antes de publicar: ' . implode( ', ', $built['real_data'] ) . '.';
		}
		if ( $finish ) {
			$required = array_filter(
				$pending,
				static function ( $item ) {
					return ! empty( $item['required'] );
				}
			);
			if ( $required ) {
				$review[] = sprintf( 'Faltan datos en bloques obligatorios (%s): no se inventaron; dalos o escríbelos en el editor antes de publicar.', implode( '; ', array_unique( array_map( static function ( $item ) { return $item['block'] . ': ' . $item['missing']; }, $required ) ) ) );
			}
			if ( $missing ) {
				$review[] = 'El sitio no tiene estas páginas y sus enlaces se quitaron: ' . implode( ', ', $missing ) . '.';
			}
			if ( 'contact' === ( $fin['whatsapp'] ?? null ) ) {
				$review[] = 'El sitio no publica un número de WhatsApp: el botón lleva a la página de contacto.';
			} elseif ( 'removed' === ( $fin['whatsapp'] ?? null ) ) {
				$review[] = 'El sitio no publica un número de WhatsApp ni tiene página de contacto: se quitó el botón; pasa whatsapp para ponerlo.';
			}
		} else {
			if ( $built['pending_count'] ) {
				$review[] = sprintf( 'Quedan %d placeholders sin llenar (%d apariciones): llénalos o borra su bloque antes de publicar.', count( $built['pending'] ), $built['pending_count'] );
			}
			if ( $built['whatsapp_pending'] ) {
				$review[] = 'Los botones de WhatsApp conservan el número de muestra: pasa whatsapp o cámbialo en el editor.';
			}
			if ( $built['event_logo'] ) {
				$review[] = 'La barra fija de cierre lleva la imagen de muestra del logo del evento: pon el logo del evento en el editor o borra esa imagen; si no, se ve en cada pantalla.';
			}
		}
		if ( $built['removed_urgency'] ) {
			$review[] = 'Sin fecha de cierre real se quitaron la barra de cierre o el contador; los textos que nombran el cierre ' . ( $finish ? 'se quitaron.' : 'quedan pendientes.' );
		}
		if ( $built['broken_anchors'] ) {
			$review[] = 'Hay botones que llevan a un bloque que ya no está (#' . implode( ', #', $built['broken_anchors'] ) . '): cámbialos o bórralos en el editor.';
		}
		if ( $built['social_pending'] ) {
			$review[] = sprintf( 'Faltan los enlaces de las redes: %d iconos de redes llevan el enlace de muestra %s, que abre la misma página. Pon en el editor el de cada perfil del sitio (el icono → Enlace, y «Abrir en una nueva ventana» si lo quieres) o quita los que no uses; por aquí no entra ninguna URL de otro sitio.', $built['social_pending'], Builder::PROFILE_SAMPLE );
		}
		$candidates = (array) ( $site['whatsapp_candidates'] ?? array() );
		if ( count( $candidates ) > 1 && '' === (string) ( $input['whatsapp'] ?? '' ) && ! $built['whatsapp_pending'] ) {
			$list = array();
			foreach ( array_slice( $candidates, 0, 4 ) as $candidate ) {
				$list[] = sprintf( '%s (%s)', $candidate['whatsapp'], implode( ', ', array_slice( (array) $candidate['sources'], 0, 3 ) ) );
			}
			$review[] = sprintf( 'El sitio publica más de un número para el bloque whatsapp: %s. Se usó %s; si es otro, pásalo en whatsapp.', implode( '; ', $list ), $whatsapp );
		}
		if ( $landing ) {
			foreach ( $landing['warnings'] as $warning ) {
				$review[] = $warning;
			}
		}
		$check = self::check( $built['elements'], $brand, $page, $layout, $landing ? (string) $landing['buy_url'] : '' );
		if ( ! $landing && $check['course_texts'] && function_exists( 'WC' ) ) {
			$review[] = 'Esta plantilla es para cursos y programas: para una oferta de la tienda usa store_offer (toda la tienda o una categoría) o product_id (un producto), que arman la landing con los productos reales y sin textos de cursos.';
		}
		$out = array(
			'ok'               => true,
			'tool'             => 'create-page',
			'id'               => (int) $page['id'],
			'status'           => (string) $page['status'],
			'template'         => $id,
			'variant'          => $variant,
			'title'            => $title,
			'edit_url'         => (string) $page['edit_url'],
			'preview_url'      => (string) $page['preview_url'],
			'regenerated'      => ! empty( $page['regenerated'] ),
			'finished'         => $finish,
			'filled'           => $built['filled'],
			'filled_keys'      => $built['filled_keys'],
			'filled_from'      => $filled_from,
			'ignored'          => $built['ignored'],
			'pending'          => array_values( $pending ),
			'pending_count'    => $finish ? count( $pending ) : $built['pending_count'],
			'placeholders_left' => $finish ? $built['pending'] : null,
			'real_data'        => $built['real_data'],
			'whatsapp'         => $built['whatsapp_pending'] ? '' : (string) $whatsapp,
			'whatsapp_source'  => $built['whatsapp_pending'] ? '' : $whatsapp_source,
			'whatsapp_candidates' => $candidates,
			'whatsapp_pending' => $built['whatsapp_pending'],
			'close_date'       => $built['close_date'],
			'removed_urgency'  => $built['removed_urgency'],
			'omitted_blocks'   => $built['omitted_blocks'],
			'removed'          => $finish ? (array) ( $fin['removed'] ?? array() ) : array(),
			'links'            => $finish ? (array) ( $fin['links'] ?? array() ) : array(),
			'broken_anchors'   => $built['broken_anchors'],
			'social_pending'   => $built['social_pending'],
			'event_logo'       => $built['event_logo'],
			'pages_to_create'  => $links,
			'review'           => $review,
			'brand'            => Brand::summary( $brand ),
			'look'             => $check['look'],
			'check'            => $check,
			'llm_instructions' => $finish
				? 'La página quedó en BORRADOR y terminada (sin placeholders a la vista). Repórtalo corto: la vista previa (' . (string) $page['preview_url'] . ') y la edición, de dónde salieron los datos (filled_from) y, si hay, lo pendiente (pending) y lo de review. No digas que faltan datos que ya están en filled_from. Publicarla le toca a una persona.'
				: 'La página quedó en BORRADOR. Muestra al usuario la vista previa (' . (string) $page['preview_url'] . ') y lo que falta revisar; publicarla le toca a una persona.',
		);
		if ( $product ) {
			$out['product'] = array(
				'id'      => (int) $product['id'],
				'name'    => (string) $product['name'],
				'price'   => Product::money( (float) $offer['price'] ),
				'regular' => null !== $offer['regular'] ? Product::money( (float) $offer['regular'] ) : null,
				'ends'    => '' !== $offer['ends'] ? $offer['ends'] : null,
				'label'   => $offer['label'],
				'buy_url' => $landing['buy_url'],
			);
		}
		return $out;
	}

	/** La identidad del sitio (`Brand::read()`, o la de las pruebas). */
	private static function brand( array $deps ): array {
		return isset( $deps['brand'] ) && is_array( $deps['brand'] ) ? $deps['brand'] : Brand::read();
	}

	/**
	 * Lo que una persona vería, medido sobre lo que se guardó: colores y tipografías (`look`, con los globales del kit
	 * resueltos), cabecera y pie del sitio, a dónde lleva el botón principal, cuántos productos y si queda algún texto de
	 * cursos. El Asistente afirma algo («ya usa tus colores») sólo si esto lo confirma.
	 */
	private static function check( array $elements, array $brand, array $page, string $layout, string $main_url ): array {
		$kit_colors = array();
		foreach ( (array) ( $brand['kit_colors'] ?? array() ) as $id => $c ) {
			$kit_colors[ $id ] = is_array( $c ) ? (string) ( $c['color'] ?? '' ) : (string) $c;
		}
		$look    = Brand::look( $elements, $brand, $kit_colors );
		$buttons = array();
		self::buttons( $elements, $buttons );
		$main    = '' !== $main_url ? $main_url : ( $buttons ? $buttons[0] : '' );
		$cart    = '' !== $main && preg_match( '#/(carrito|cart)/?(\?|$)#i', (string) wp_parse_url( $main, PHP_URL_PATH ) . ( false !== strpos( $main, '?' ) ? '?' : '' ) ) && false === strpos( $main, 'add-to-cart=' );
		$header  = 'blank' !== $layout && 'elementor_canvas' !== (string) ( $page['layout'] ?? 'elementor_header_footer' );
		return array(
			'look'               => $look,
			'site_header_footer' => $header,
			'main_button'        => $main,
			'main_button_cart'   => $cart,
			'course_texts'       => Store::course_texts( $elements ),
			// Sin identidad en el sitio (ni kit propio, ni colores en su cabecera, pie o logo) no hay nada que corregir.
			'ok'                 => ( $look['matches_site'] || ! $look['site_identity'] ) && ! $look['digitalisimo'] && $header && ! $cart && ! Store::course_texts( $elements ),
		);
	}

	/** Los destinos de los botones, en orden. */
	private static function buttons( array $elements, array &$out ): void {
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			if ( 'button' === ( $el['widgetType'] ?? '' ) && is_string( $el['settings']['link']['url'] ?? null ) && '' !== $el['settings']['link']['url'] && '#' !== $el['settings']['link']['url'][0] ) {
				$out[] = $el['settings']['link']['url'];
			}
			self::buttons( (array) ( $el['elements'] ?? array() ), $out );
		}
	}

	/** La landing de una oferta de tienda: toda la tienda o una categoría (`Store`). */
	private static function store_offer( array $input, callable $create, array $deps, int $regenerate, string $layout ) {
		$env   = array(
			'timezone'      => Site::sales_timezone(),
			'site_timezone' => wp_timezone(),
			'now'           => time(),
			'variant'       => Catalog::variant(),
		);
		$offer = Store::offer( $input['store_offer'], $env );
		if ( is_wp_error( $offer ) ) {
			return $offer;
		}
		$catalog = isset( $deps['catalog'] ) && is_callable( $deps['catalog'] ) ? call_user_func( $deps['catalog'], $offer['category'] ) : Store::catalog( $offer['category'] );
		if ( is_wp_error( $catalog ) ) {
			return $catalog;
		}
		$site = isset( $deps['site'] ) && is_array( $deps['site'] ) ? $deps['site'] : Site::read();
		if ( '' !== (string) ( $input['whatsapp'] ?? '' ) ) {
			$site['whatsapp'] = (string) $input['whatsapp'];
		}
		$docs = isset( $deps['docs'] ) && is_array( $deps['docs'] ) ? $deps['docs'] : Brand::design_docs();
		if ( ! isset( $site['benefits'] ) ) {
			$site['benefits'] = Store::benefits( $docs );
		}
		if ( ! isset( $site['refund_line'] ) ) {
			$site['refund_line'] = Store::refund_line( (string) ( $site['links']['/politica-de-reembolso/'] ?? '' ) );
		}
		$landing = Store::page( $offer, $catalog, $site, $env );
		$brand    = self::brand( $deps );
		$elements = Brand::apply( $landing['elements'], $brand );
		$title   = isset( $input['title'] ) && is_string( $input['title'] ) ? trim( sanitize_text_field( $input['title'] ) ) : '';
		$title   = '' !== $title ? $title : $landing['title'];
		$page    = call_user_func( $create, $title, $elements, $regenerate, 'tienda', $layout );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		$check  = self::check( $elements, $brand, $page, $layout, $landing['button_url'] );
		$check['products'] = $landing['shown'];
		$review = $landing['warnings'];
		if ( ! $check['look']['site_identity'] ) {
			$review[] = 'El sitio no tiene colores de marca propios (su kit tiene los de fábrica y su cabecera, su pie y su logo no los dicen): la página va en tonos neutros. No digas que usa los colores del sitio.';
		} elseif ( ! $check['look']['matches_site'] ) {
			$review[] = 'Los colores o las tipografías de la página no coinciden con los del sitio: revisa look.';
		}
		return array(
			'ok'               => true,
			'tool'             => 'create-page',
			'id'               => (int) $page['id'],
			'status'           => (string) $page['status'],
			'template'         => 'tienda',
			'variant'          => $env['variant'],
			'title'            => $title,
			'edit_url'         => (string) $page['edit_url'],
			'preview_url'      => (string) $page['preview_url'],
			'regenerated'      => ! empty( $page['regenerated'] ),
			'finished'         => true,
			'filled_from'      => $landing['filled'],
			'pending'          => array(),
			'pending_count'    => 0,
			'store_offer'      => array(
				'percent'        => $offer['percent'],
				'scope'          => 'category' === $catalog['scope'] ? 'la categoría ' . $catalog['scope_name'] : 'toda la tienda',
				'products_shown' => $landing['shown'],
				'products_total' => (int) $catalog['total'],
				'best_sellers'   => (bool) $catalog['top'],
				'note'           => (int) $catalog['total'] > $landing['shown'] && ! $catalog['top'] ? 'La tienda tiene más productos que los que caben: se muestran algunos y el botón lleva a todos.' : '',
				'button_url'     => $landing['button_url'],
				'ends'           => '' !== $offer['ends'] ? $offer['ends'] : null,
			),
			'whatsapp'         => (string) ( $site['whatsapp'] ?? '' ),
			'review'           => $review,
			'brand'            => Brand::summary( $brand ),
			'look'             => $check['look'],
			'check'            => $check,
			'llm_instructions' => 'La landing quedó en BORRADOR y terminada, con los colores, las tipografías, la cabecera y el pie del sitio (check y look lo miden; afirma sólo lo que confirman). Repórtalo corto, en lenguaje llano: el enlace para verla y el de editarla y, en una frase, lo de review (la tienda sigue cobrando el precio normal: ofrécelo como pregunta, no lo hagas). Para cambiarla, vuelve a llamar a create-page con post_id y store_offer (con headline o subtitle si piden otro texto). Publicarla le toca a una persona.',
		);
	}
}
