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
 * - `digitalisimo-sales/create-page` (escritura): una página en borrador desde una plantilla. Nunca publica.
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
		'list-templates' => 'Catálogo del kit de ventas de DIGITALÍSIMO Elements: ocho plantillas de página (01 venta completa, 02 venta corta, 03 captación, 04 gracias por registrarte, 05 carrito, 06 oferta única después de la compra, 07 gracias por tu compra y 08 bloques de prueba social), para qué sirve cada una, cuándo usarla, el embudo y la tabla «qué landing armar según el caso». Cada plantilla trae sus bloques en el orden de la guía (claves para omit_blocks de create-page: se pueden quitar, nunca reordenar). Con template (el id de una plantilla) devuelve además sus [placeholders] (el texto exacto, cuántas veces aparece y su tipo: texto, precio, dato_real, cierre o enlace) y sus enlaces internos (las páginas del sitio a las que llevan sus botones, que se crean aparte). Sólo lee. Antes de llenar una página, lee también digitalisimo-sales/guide.',
		'create-page'    => 'Crea una página EN BORRADOR del sitio con una plantilla del kit de ventas; nunca la publica (publicar es de una persona, después de revisarla). Elige sola la variante: contenedores si el sitio los tiene activos, secciones clásicas si no. Entrada: template (id de list-templates), title, replacements (objeto «[placeholder]» → texto, o lista de textos, uno por aparición en la plantilla entera y en su orden, también con omit_blocks: los de un bloque quitado se descartan; el texto exacto y cuántas veces aparece salen de list-templates con template), whatsapp (sólo dígitos con la clave del país, p. ej. 5218711234567: los botones van a https://wa.me/<dígitos>) omit_blocks (claves de bloques que no aplican, de list-templates: se quitan enteros y lo demás queda en el orden de la guía; nunca se reordena ni se quita el pie) y, sólo en las plantillas con contador (venta-completa y carrito), close_date («AAAA-MM-DD HH:MM» en la hora del sitio: una fecha de cierre REAL y futura). Reglas: llena sólo con datos que te dio el usuario; nunca inventes testimonios, nombres, cifras, casos ni fechas: si no hay dato, deja el placeholder (queda pendiente y se informa). Los precios (tipo precio) van sin signo ni moneda: sólo la cifra, p. ej. 1,997; la plantilla ya dice $ y MXN. Ningún valor puede llevar una URL de otro sitio: los enlaces del kit son del propio sitio, y videos, grupo y redes (tipo enlace) se ponen en el editor. Los placeholders de cierre (tipo cierre) sólo se llenan con close_date; sin close_date se quitan la barra de cierre y el contador del carrito. Regenera todos los identificadores y guarda la página como el editor, con su CSS. Devuelve el ID, el estado (draft), la URL de edición, la URL de vista previa (con la sesión de WordPress iniciada), los placeholders pendientes, los datos reales que una persona debe comprobar y las páginas del sitio que faltan crear.',
		'guide'          => 'Guía de venta del kit de DIGITALÍSIMO, en Markdown: reglas (borrador, nada inventado, urgencia sólo real, precios en MXN, llamado a la acción por WhatsApp, enlaces del propio sitio, el orden de los bloques no se cambia), embudo y plantillas, qué landing armar según el caso, el orden de una página de venta en 26 bloques (se puede quitar un bloque, pero no cambiar el orden), 15 fórmulas de redacción, las páginas una por una y la lista «antes de publicar». section elige una parte; sin ella, toda. Sólo lee.',
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

	/** Esquema de entrada cerrado (`additionalProperties: false`, nunca `post_id`). */
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
						'description'          => 'Objeto «[placeholder]» → texto (todas sus apariciones) o lista de textos: uno por aparición en la plantilla entera, en el orden y con el total de list-templates, también con omit_blocks (los de un bloque quitado se descartan). Si faltan, las demás apariciones quedan pendientes; los que sobran o se descartan se informan en ignored. Sólo datos que dio el usuario; un placeholder sin dato se omite.',
					),
					'omit_blocks'  => array(
						'type'        => 'array',
						'maxItems'    => 40,
						'uniqueItems' => true,
						'items'       => array(
							'type'    => 'string',
							'pattern' => '^[a-z0-9-]{1,40}$',
						),
						'description' => 'Claves de los bloques que no aplican (list-templates con template). Se quitan enteros; lo demás queda en el orden de la guía. El pie no se quita.',
					),
					'whatsapp'     => array(
						'type'        => 'string',
						'pattern'     => '^[0-9]{8,15}$',
						'description' => 'Número de WhatsApp del sitio: sólo dígitos, con la clave del país (México: 52). Sin él, los botones conservan el número de muestra y se informa.',
					),
					'close_date'   => array(
						'type'        => 'string',
						'pattern'     => '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$',
						'description' => 'Sólo en venta-completa y carrito, y sólo si el cierre es real: «AAAA-MM-DD HH:MM» en la hora del sitio, futura. Llena los contadores y [día de cierre], [mes de cierre] y [hora de cierre].',
					),
				);
				$required   = array( 'template', 'title' );
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
