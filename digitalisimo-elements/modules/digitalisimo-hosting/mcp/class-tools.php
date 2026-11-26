<?php
namespace Digitalisimo\Elements\Hosting\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Herramientas de DIGITALÍSIMO Hosting en el servidor MCP de DIGITALÍSIMO Elements (pedido del propietario del
 * 2026-11-26: el agente que arma las landings por MCP configura Hosting sin el formulario). Declarativo y puro.
 *
 * - `digitalisimo-hosting/settings` (`manage_options`): get devuelve todos los ajustes de Hosting de este sitio sin
 *   ningún secreto (de las claves y de la cuenta de Namecheap sólo si están puestas); update COMBINA con lo guardado
 *   (nunca reemplaza lo que no llega) con la validación del formulario, escribe sólo la opción del sitio (nunca la de
 *   la red) y devuelve el resultado; log lee la bitácora de escrituras (sin valores ni datos personales).
 * - `digitalisimo-hosting/domain-product` (`manage_woocommerce` o `manage_options`): list, mark y unmark de la extensión
 *   de un producto de dominio (registro o transferencia).
 * La validación y el guardado viven en DIGITALÍSIMO Hosting (`Digitalisimo_Hosting::mcp_update()`,
 * `Digitalisimo_Hosting_Products::mark()`): aquí sólo el contrato MCP, el permiso y la bitácora.
 */
final class Tools {
	const PREFIX   = 'digitalisimo-hosting/';
	const CATEGORY = 'digitalisimo-hosting';

	const TOOLS = array( 'settings', 'domain-product' );

	const ACTIONS = array(
		'settings'       => array( 'get', 'update', 'log' ),
		'domain-product' => array( 'list', 'mark', 'unmark' ),
	);

	const WRITE_ACTIONS = array( 'update', 'mark', 'unmark' );

	const LABELS = array(
		'settings'       => 'Hosting: ajustes del sitio',
		'domain-product' => 'Hosting: producto de dominio por extensión',
	);

	const DESCRIPTIONS = array(
		'settings'       => 'Ajustes de DIGITALÍSIMO Hosting de ESTE sitio (Digitalisimo → Hosting): dominios, productos por extensión, compra de hosting en WooCommerce y el buscador de dominios. action get: devuelve todos los ajustes (settings), qué secretos están puestos (secrets_set: nunca su valor), si la cuenta de Namecheap está configurada, qué se puede escribir (writable) y qué no (read_only), y domain_products (qué producto de WooCommerce vende cada extensión, de dónde salió —config, meta o sku— y qué extensiones no tienen producto: con ellas el buscador no las ofrece). action update: data {"clave": valor} sólo con las claves que cambian; se COMBINA con lo guardado (lo que no envías no cambia), con la misma validación del formulario, sólo en este sitio (nunca en la red); devuelve los ajustes resultantes, changed (claves que cambiaron) y adjusted (las que la validación guardó distinto de lo enviado, con su valor final). Claves que se pueden escribir: enabled, domain_products (una línea por extensión: ".com|123\n.net|\n.mx|456"; sin ID se usa el producto marcado con esa extensión o con el SKU dominio-com), hosting_product_ids y hosting_category_ids (IDs separados por comas), ask_domain (pedir «Dominio nuevo / uno que ya tengo» antes de pagar), require_domain_hosting, replace_hosting, simplify_checkout, form_selector, domain_field, extension_field, webuzo_user_prefix, grace_days y la fórmula de precios (pricing_enabled, pricing_cushion, pricing_margin, pricing_fee, pricing_vat, pricing_min_margin, pricing_rounding "10" | "9" | "end9" | "none"). La cuenta de Namecheap, el servidor y la clave de Webuzo, los nameservers, el token de Banxico y las casillas de venta automática (provision_enabled, own_domain_auto) sólo se leen: una persona las cambia en el escritorio. Una clave desconocida o de sólo lectura detiene todo sin guardar nada. action log: bitácora de escrituras de estas herramientas (fecha, usuario, acción, claves cambiadas, resultado; nunca valores).',
		'domain-product' => 'Marca qué producto de WooCommerce vende cada extensión de dominio en DIGITALÍSIMO Hosting, para que el buscador y el carrito lo encuentren aunque «Productos por extensión» no traiga su ID. action list: los productos marcados y la resolución actual. action mark: product_id y tld (".com", ".net", ".mx", ".com.mx"…); kind "register" (registro, por defecto) o "transfer" (producto de transferencia: el carrito pide el dominio y su código EPP; tld "*" o vacío = cualquier extensión). action unmark: quita la marca de product_id. Una línea de «Productos por extensión» con ID manda sobre la marca. Devuelve el producto con su marca y la resolución resultante.',
	);

	/** ¿Está DIGITALÍSIMO Hosting cargado con la API que usan estas herramientas? */
	public static function hosting_ready(): bool {
		return class_exists( 'Digitalisimo_Hosting', false ) && method_exists( 'Digitalisimo_Hosting', 'mcp_settings' ) && method_exists( 'Digitalisimo_Hosting', 'mcp_update' ) && class_exists( 'Digitalisimo_Hosting_Products', false ) && method_exists( 'Digitalisimo_Hosting_Products', 'mark' );
	}

	public static function id( string $tool ): string {
		return self::PREFIX . $tool;
	}

	public static function tool_of( string $id ): string {
		if ( 0 !== strpos( $id, self::PREFIX ) ) {
			return '';
		}
		$tool = substr( $id, strlen( self::PREFIX ) );
		return in_array( $tool, self::TOOLS, true ) ? $tool : '';
	}

	public static function is_write( string $action ): bool {
		return in_array( $action, self::WRITE_ACTIONS, true );
	}

	/** Capacidades: basta una de la lista. */
	public static function capabilities( string $tool ): array {
		return 'settings' === $tool ? array( 'manage_options' ) : array( 'manage_woocommerce', 'manage_options' );
	}

	public static function definition( string $tool ): array {
		return array(
			'label'         => self::LABELS[ $tool ],
			'description'   => self::DESCRIPTIONS[ $tool ],
			'category'      => self::CATEGORY,
			'input_schema'  => self::input_schema( $tool ),
			'output_schema' => array( 'type' => 'object' ),
			'meta'          => array(
				// update y mark cambian ajustes (combinan, no borran nada más); unmark quita una marca.
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => false,
				'mcp'          => array(
					'public' => false,
					'type'   => 'tool',
				),
			),
		);
	}

	public static function input_schema( string $tool ): array {
		$properties = array(
			'action' => array(
				'type'        => 'string',
				'enum'        => self::ACTIONS[ $tool ],
				'description' => 'settings' === $tool ? 'get lee, update combina data con lo guardado, log lee la bitácora.' : 'list lee, mark marca product_id con tld, unmark le quita la marca.',
			),
		);
		if ( 'settings' === $tool ) {
			$properties['data']     = array(
				'type'        => 'object',
				'description' => 'Sólo en update: las claves que cambian y su valor nuevo (las demás no cambian).',
			);
			$properties['page']     = array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Sólo en log.' );
			$properties['per_page'] = array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'Sólo en log (20 por defecto).' );
		} else {
			$properties['product_id'] = array( 'type' => 'integer', 'minimum' => 1, 'description' => 'ID del producto de WooCommerce (mark y unmark).' );
			$properties['tld']        = array( 'type' => 'string', 'maxLength' => 64, 'description' => 'Extensión con su punto: ".com", ".mx", ".com.mx"; en una transferencia "*" = cualquiera (mark).' );
			$properties['kind']       = array( 'type' => 'string', 'enum' => array( 'register', 'transfer' ), 'description' => 'register (registro, por defecto) o transfer (mark).' );
		}
		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => array( 'action' ),
			'additionalProperties' => false,
		);
	}
}
