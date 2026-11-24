<?php
namespace Digitalisimo\Elements\Sales\Mcp;

use Digitalisimo\Elements\Sales\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Herramientas del kit de ventas en el servidor MCP de DIGITALÍSIMO Elements: IDs, textos para el modelo, esquemas de
 * entrada cerrados y anotaciones. Declarativo y puro, para que las pruebas lo lean tal cual.
 *
 * - `digitalisimo-sales/list-templates` (lectura): el catálogo, la tabla por caso y, con `template`, sus placeholders
 *   y sus enlaces internos.
 * - `digitalisimo-sales/create-page` (escritura): una página en borrador desde una plantilla, terminada (`finish`), o la
 *   landing de un producto de la tienda (`product_id` y `offer`). Nunca publica.
 * - `digitalisimo-sales/guide` (lectura): la guía de venta.
 * Las tres exigen `edit_pages`; `publish_pages` nunca se consulta ni se usa.
 */
final class Tools {
	const PREFIX   = 'digitalisimo-sales/';
	const CATEGORY = 'digitalisimo-sales';
	const FLOOR    = 'edit_pages';

	const TOOLS = array( 'list-templates', 'create-page', 'guide' );

	const LABELS = array(
		'list-templates' => 'Kit de ventas: catálogo de plantillas',
		'create-page'    => 'Kit de ventas: crear una página en borrador',
		'guide'          => 'Kit de ventas: guía de venta',
	);

	const DESCRIPTIONS = array(
		'list-templates' => 'Catálogo del kit de ventas de DIGITALÍSIMO Elements: ocho plantillas de página (01 venta completa, 02 venta corta, 03 captación, 04 gracias por registrarte, 05 carrito, 06 oferta única después de la compra, 07 gracias por tu compra y 08 bloques de prueba social), para qué sirve cada una, cuándo usarla, el embudo y la tabla «qué landing armar según el caso». Cada plantilla trae sus bloques en el orden de la guía (claves para omit_blocks de create-page: se pueden quitar, nunca reordenar; los obligatorios, required: true, no se quitan: la promesa, la oferta, la garantía, las preguntas frecuentes, el bloque whatsapp, el pie y el formulario; price: true dice en qué bloques puede ir un precio). Con template (el id de una plantilla) devuelve además sus [placeholders] (el texto exacto, cuántas veces aparece y su tipo: texto, precio, dato_real, cierre o enlace) y sus enlaces internos (las páginas del sitio a las que llevan sus botones, que se crean aparte). Sólo lee. Antes de llenar una página, lee también digitalisimo-sales/guide.',
		'create-page'    => 'Crea una página EN BORRADOR del sitio con el kit de ventas, TERMINADA: nunca la publica (publicar es de una persona, después de revisarla) y nunca deja [placeholders] a la vista. PROMO DE UN PRODUCTO DE LA TIENDA (el camino normal para una oferta, Black Friday, Buen Fin, liquidación…): pasa product_id y offer (price: el precio de oferta; regular_price sólo si no es el del producto; ends: «AAAA-MM-DD HH:MM» o «AAAA-MM-DD», el fin real de la oferta en la hora del sitio; label: el nombre de la promoción) y, si quieres, title; nada más. La herramienta lee el producto y el sitio y arma la landing de producto (venta corta sin bloques de curso): nombre, descripción, beneficios (las listas de la descripción), características (atributos, peso, medidas), imágenes (destacada y galería), reseñas aprobadas si las hay, precio normal tachado, precio de oferta, ahorro, botón que agrega el producto al carrito y lleva a pagar, cuenta regresiva con ends, garantía si el producto la dice (o la política de reembolso del sitio), preguntas frecuentes con los pagos y envíos activos, el logo del sitio, el número del bloque whatsapp que el sitio ya publica (enlaces wa.me) y enlaces a las páginas legales reales. Para otras páginas (cursos, captación, gracias, carrito): template (id de list-templates), title, replacements (objeto «[placeholder]» → texto, o lista de textos, uno por aparición en la plantilla entera y en su orden, también con omit_blocks: los de un bloque quitado se descartan; el texto exacto y cuántas veces aparece salen de list-templates con template), whatsapp (sólo dígitos con la clave del país, p. ej. 5218711234567; sin él se usa el que el sitio ya publica en sus enlaces wa.me, y si no hay, el botón va a la página de contacto o se quita) omit_blocks (claves de bloques que no aplican, de list-templates: se quitan enteros y lo demás queda en el orden de la guía; nunca se reordena ni se quitan los obligatorios, required: true en list-templates: la promesa, la oferta, la garantía, las preguntas frecuentes, el bloque whatsapp, el pie y el formulario, en las plantillas que los tienen; las pruebas sociales sí se quitan) y, sólo en las plantillas con fecha de cierre (close_date: true en list-templates: venta-completa, venta-corta y carrito), close_date («AAAA-MM-DD HH:MM» en la hora del sitio: una fecha de cierre REAL y futura; con ella, la venta completa y la venta corta llevan arriba la barra fija de cierre con la cuenta regresiva). Al terminar (finish, encendido por defecto): lo que quede sin dato se quita (el elemento, o el bloque si es opcional) y lo que falte en un bloque obligatorio se informa en pending con el bloque y el campo; [Marca] y [año] salen del sitio; las imágenes de muestra del logo llevan el logo del sitio; los enlaces a privacidad, términos, reembolso, carrito y pago van a las páginas reales del sitio o se quitan. Reglas: llena con datos que te dio el usuario o que están en el sitio (tienda, páginas, ajustes); nunca inventes testimonios, reseñas, nombres, cifras de clientes, casos, fechas ni urgencia falsa: si no hay dato, no lo pongas. Los precios (tipo precio) van sin signo ni moneda: sólo la cifra, p. ej. 1,997; la plantilla ya dice $ y MXN. Ningún valor puede llevar una URL de otro sitio: los enlaces del kit son del propio sitio, y videos, grupo y los perfiles de los iconos de redes de la 04 y la 07 (tipo enlace) se ponen en el editor. El precio va sólo en la oferta: un valor con $, MXN, pesos o una cifra con centavos sólo se acepta en los bloques con price: true de list-templates (la oferta, el cierre que la repite, los caminos, «Cuánto vale para ti», el carrito y la oferta única); en la portada, los bonus y los demás bloques se rechaza. Los placeholders de cierre (tipo cierre) sólo se llenan con close_date; sin close_date se quitan las barras de cierre (01 y 02) y el contador del carrito. Regenera todos los identificadores y guarda la página como el editor, con su CSS. Con post_id (un borrador del kit, el id de una respuesta anterior) regenera ese mismo borrador en lugar de crear otro. Devuelve el ID, el estado (draft), la URL de edición y la de vista previa (con la sesión de WordPress iniciada), filled_from (qué se llenó y de dónde), pending (lo que falta, con su bloque y campo), removed, review (lo que una persona debe revisar, p. ej. si la tienda cobra otro precio que la landing) y las páginas del sitio que faltan.',
		'guide'          => 'Guía de venta del kit de DIGITALÍSIMO, en Markdown: reglas (borrador, nada inventado, urgencia sólo real, precios en MXN, llamado a la acción por WhatsApp, enlaces del propio sitio, el orden de los bloques no se cambia), embudo y plantillas, qué landing armar según el caso, el orden de una página de venta en 26 bloques (se puede quitar un bloque, pero no cambiar el orden; la promesa, la oferta, la garantía, las preguntas frecuentes, el bloque whatsapp, el pie y el formulario no se quitan), la portada en una pantalla y sin video, el precio sólo en la oferta, 15 fórmulas de redacción, las páginas una por una y la lista «antes de publicar». section elige una parte; sin ella, toda. Sólo lee.',
	);

	public static function id( string $tool ): string {
		return self::PREFIX . $tool;
	}

	/** Herramienta de un ID de ability, o '' si no es una de éstas. */
	public static function tool_of( string $id ): string {
		if ( 0 !== strpos( $id, self::PREFIX ) ) {
			return '';
		}
		$tool = substr( $id, strlen( self::PREFIX ) );
		return in_array( $tool, self::TOOLS, true ) ? $tool : '';
	}

	public static function is_readonly( string $tool ): bool {
		return 'create-page' !== $tool;
	}

	/** `label`, `description`, `category`, `input_schema`, `output_schema` y `meta` de una herramienta. */
	public static function definition( string $tool ): array {
		$readonly = self::is_readonly( $tool );
		return array(
			'label'         => self::LABELS[ $tool ],
			'description'   => self::DESCRIPTIONS[ $tool ],
			'category'      => self::CATEGORY,
			'input_schema'  => self::input_schema( $tool ),
			'output_schema' => array( 'type' => 'object' ),
			'meta'          => array(
				// create-page crea un borrador nuevo: no cambia ni borra nada que exista.
				'annotations'  => array(
					'readonly'    => $readonly,
					'destructive' => false,
					'idempotent'  => $readonly,
				),
				// Sólo en el servidor MCP de DIGITALÍSIMO Elements: ni en la REST de abilities ni en el servidor genérico.
				'show_in_rest' => false,
				'mcp'          => array(
					'public' => false,
					'type'   => 'tool',
				),
			),
		);
	}

	/** Esquema de entrada cerrado (`additionalProperties: false`; `post_id` sólo en create-page, para regenerar un borrador del kit). */
	public static function input_schema( string $tool ): array {
		$templates = Catalog::ids();
		switch ( $tool ) {
			case 'list-templates':
				$properties = array(
					'template' => array(
						'type'        => 'string',
						'enum'        => $templates,
						'description' => 'Id de una plantilla: devuelve también sus placeholders y sus enlaces internos.',
					),
				);
				$required   = array();
				break;
			case 'guide':
				$properties = array(
					'section' => array(
						'type'        => 'string',
						'enum'        => array_keys( Catalog::GUIDE_SECTIONS ),
						'description' => 'Parte de la guía: reglas, plantillas (embudo), casos, orden (los 26 bloques), formulas, paginas o antes-de-publicar. Sin ella, toda.',
					),
				);
				$required   = array();
				break;
			default:
				$properties = array(
					'template'     => array(
						'type'        => 'string',
						'enum'        => $templates,
						'description' => 'Id de la plantilla (list-templates).',
					),
					'title'        => array(
						'type'        => 'string',
						'minLength'   => 1,
						'maxLength'   => 200,
						'description' => 'Título de la página nueva (texto plano).',
					),
					'replacements' => array(
						'type'                 => 'object',
						'maxProperties'        => 400,
						'additionalProperties' => array(
							'anyOf' => array(
								array(
									'type'      => 'string',
									'maxLength' => 3000,
								),
								array(
									'type'     => 'array',
									'maxItems' => 100,
									'items'    => array(
										'type'      => 'string',
										'maxLength' => 3000,
									),
								),
							),
						),
						'description'          => 'Objeto «[placeholder]» → texto (todas sus apariciones) o lista de textos: uno por aparición en la plantilla entera, en el orden y con el total de list-templates, también con omit_blocks (los de un bloque quitado se descartan). Si faltan, las demás apariciones se terminan (finish: se quita lo que no tiene dato) o quedan pendientes (finish: false); los que sobran o se descartan se informan en ignored. Datos que dio el usuario o que están en el sitio (tienda, páginas, ajustes); nunca testimonios, reseñas, cifras de clientes ni urgencia inventados. Con product_id casi nunca hace falta.',
					),
					'omit_blocks'  => array(
						'type'        => 'array',
						'maxItems'    => 40,
						'uniqueItems' => true,
						'items'       => array(
							'type'    => 'string',
							'pattern' => '^[a-z0-9-]{1,40}$',
						),
						'description' => 'Claves de los bloques que no aplican (list-templates con template). Se quitan enteros; lo demás queda en el orden de la guía. No se quitan los obligatorios (required: true en list-templates): la promesa, la oferta, la garantía, las preguntas frecuentes, el bloque whatsapp, el pie y el formulario, en las plantillas que los tienen; si se piden, un error dice cuáles. Las pruebas sociales sí se quitan.',
					),
					'whatsapp'     => array(
						'type'        => 'string',
						'pattern'     => '^[0-9]{8,15}$',
						'description' => 'Número de WhatsApp del sitio: sólo dígitos, con la clave del país (México: 52). Sin él se usa el que el sitio ya publica (enlaces wa.me o del botón de chat en menús, cabecera, pie y páginas); si el sitio no tiene, el botón lleva a la página de contacto o se quita (con finish: false, conserva el número de muestra y se informa).',
					),
					'close_date'   => array(
						'type'        => 'string',
						'pattern'     => '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$',
						'description' => 'Sólo en las plantillas con fecha de cierre (venta-completa, venta-corta y carrito; close_date en list-templates) y sólo si el cierre es real: «AAAA-MM-DD HH:MM» en la hora del sitio, futura. Llena los contadores que haya y [día de cierre], [mes de cierre] y [hora de cierre]. Con product_id va en offer.ends.',
					),
					'product_id'   => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'ID de un producto publicado de la tienda (digitalisimo-woo/products lo da): arma la landing de ese producto con sus datos reales (plantilla venta-corta sin bloques de curso; template sobra). Es el camino normal para la promo de un producto.',
					),
					'offer'        => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'price'         => array(
								'type'        => array( 'string', 'number' ),
								'description' => 'Precio de oferta, sólo la cifra (7500 o 7,500). Sin él, el precio rebajado del producto, o su precio.',
							),
							'regular_price' => array(
								'type'        => array( 'string', 'number' ),
								'description' => 'Precio normal tachado, sólo si no es el precio normal del producto en la tienda.',
							),
							'ends'          => array(
								'type'        => 'string',
								'pattern'     => '^[0-9]{4}-[0-9]{2}-[0-9]{2}( [0-9]{2}:[0-9]{2})?$',
								'description' => 'Fin REAL de la oferta, en la hora del sitio: «AAAA-MM-DD HH:MM» o «AAAA-MM-DD» (hasta las 23:59), p. ej. «de hoy a fin de mes» = el último día del mes. Pone la barra fija con la cuenta regresiva. Sin él, la fecha de fin de la rebaja del producto, si la tiene; si no, sin contador.',
							),
							'label'         => array(
								'type'        => 'string',
								'maxLength'   => 80,
								'description' => 'Nombre de la promoción, p. ej. «Black Friday» o «Buen Fin»: va debajo del nombre del producto en la portada y en el título de la página.',
							),
						),
						'description'          => 'La oferta del producto (sólo con product_id).',
					),
					'post_id'      => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Opcional: el ID de un borrador que creó el kit (el id de una respuesta anterior de create-page) para REGENERARLO en el mismo borrador, con la misma entrada completa, en lugar de crear otro. Sólo un borrador del kit de este sitio que tu usuario puede editar; una página publicada, programada, privada, en revisión o que no creó el kit da error y no se toca. Sin post_id se crea un borrador nuevo.',
					),
					'finish'       => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Terminar la página (por defecto, sí): ningún [placeholder] a la vista (lo que no tiene dato se quita; lo obligatorio que falta va en pending), enlaces a las páginas reales del sitio, el logo del sitio y sin el número de muestra. false deja la plantilla con sus placeholders para llenarla a mano en el editor (sólo si la persona lo pide). Con product_id siempre es true.',
					),
				);
				$required   = array();
		}
		$schema = array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		);
		if ( $required ) {
			$schema['required'] = $required;
		}
		return $schema;
	}
}
