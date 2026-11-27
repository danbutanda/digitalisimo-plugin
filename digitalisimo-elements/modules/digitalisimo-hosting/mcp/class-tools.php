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
		'settings'       => 'Ajustes de DIGITALÍSIMO Hosting de ESTE sitio (Digitalisimo → Hosting): dominios, compra de hosting en WooCommerce y el buscador de dominios. Sin IDs ni «|»: los productos de dominio y de hosting se DETECTAN de lo marcado en el editor de cada producto (pestaña «DIGITALÍSIMO Hosting») o con la herramienta digitalisimo-hosting-domain-product. action get: devuelve todos los ajustes (settings), qué secretos están puestos (secrets_set: nunca su valor), si las cuentas de Namecheap y Dynadot están configuradas (namecheap.configured, dynadot.configured), registrars (el registrador predeterminado, el de cada extensión que se vende y cuáles tienen cuenta), qué se puede escribir (writable) y qué no (read_only), domains (lo detectado: tld, kind register|transfer, product_id, name, price; el dominio siempre es anual), hosting_products (product_id, name, plan de Webuzo, period: Mensual, Trimestral, Semestral, Anual, Bianual o Trianual), duplicates (misma extensión y acción en dos productos: se usa el primero; avisa al dueño), incomplete y tld_list (de dónde sale la lista de extensiones válidas). action update: data {"clave": valor} sólo con las claves que cambian; se COMBINA con lo guardado (lo que no envías no cambia), con la misma validación del formulario, sólo en este sitio (nunca en la red); devuelve los ajustes resultantes, changed y adjusted. Claves que se pueden escribir: ask_domain (pedir «Dominio nuevo / uno que ya tengo» antes de pagar), require_domain_hosting, replace_hosting, simplify_checkout, form_selector, domain_field, extension_field, webuzo_user_prefix, pricing_enabled, pricing_rounding ("10" | "9" | "end9" | "none"), registrar (predeterminado: "namecheap" | "dynadot"), registrar_by_tld (objeto por extensión: {".mx": "dynadot"}; se combina por extensión y "default" la regresa al predeterminado) y los del buscador: finder_order (lista: [".com", ".mx", ".com.mx"], las populares primero), finder_requirements (objeto por extensión: {".mx": "Requisito: …"}; "" quita el aviso) y finder_upsell (lista de product_id de hosting_products para «Se compran juntos»; [] = todos los planes). Las extensiones se validan contra la lista de los registradores. Ya no existen domain_products, hosting_product_ids, hosting_category_ids ni finder_upsell_ids: enviarlas da un error que explica qué usar. Las cuentas de Namecheap y Dynadot (la clave de Dynadot nunca se lee ni se escribe por aquí), el servidor y la clave de Webuzo, los nameservers, el token de Banxico, el interruptor del módulo (enabled), la venta automática (provision_enabled, own_domain_auto), los días de gracia (grace_days) y los porcentajes de la fórmula de precios (pricing_cushion, pricing_margin, pricing_fee, pricing_vat, pricing_min_margin: el freno contra vender con pérdida) sólo se leen: una persona las cambia en el escritorio. Una clave desconocida, retirada o de sólo lectura detiene todo sin guardar nada. action log: bitácora de escrituras de estas herramientas (fecha, usuario, acción, claves cambiadas, resultado; nunca valores).',
		'domain-product' => 'Marca un producto de WooCommerce como Dominio de una extensión en DIGITALÍSIMO Hosting (lo mismo que elegir «Dominio» en la pestaña «DIGITALÍSIMO Hosting» de su editor): el buscador, el carrito y la venta automática lo detectan solos, sin IDs en los ajustes. El dominio siempre es anual. action list: lo detectado (domains, hosting_products, duplicates, incomplete). action mark: product_id y tld (".com", ".net", ".mx", ".com.mx"…; se valida contra la lista de los registradores); kind "register" (registro, por defecto) o "transfer" (el carrito pide el dominio y su código EPP; tld "*" o vacío = cualquier extensión). Dos productos con la misma extensión y acción: se usa el primero y queda en duplicates. action unmark: deja el producto en «Ninguno». Devuelve el producto (type, tld, kind, billing) y lo detectado.',
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
				// update, mark y unmark cambian ajustes de la tienda: el cliente debe pedir confirmación.
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
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
