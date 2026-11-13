<?php
namespace Digitalisimo\Elements\Sales\Mcp;

use Digitalisimo\Elements\Sales\Builder;
use Digitalisimo\Elements\Sales\Catalog;
use Digitalisimo\Elements\Sales\Pages;
use Digitalisimo\Elements\Sales\Placeholders;

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
	 * @return array|\WP_Error
	 */
	public static function run( string $tool, $input, $create = null ) {
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
				return self::create_page( $input, is_callable( $create ) ? $create : array( Pages::class, 'create' ) );
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
							'key'      => $block['key'],
							'name'     => $block['name'],
							'required' => (bool) $block['required'],
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
			'rules'     => 'Las páginas se crean en borrador; nada se inventa (testimonios, cifras y fechas sólo reales); urgencia sólo con un cierre real; precios en MXN; llamado a la acción por WhatsApp; enlaces del propio sitio; los bloques se pueden quitar, nunca reordenar. Detalle: digitalisimo-sales/guide.',
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

	private static function create_page( array $input, callable $create ) {
		$id      = (string) $input['template'];
		$meta    = Catalog::meta( $id );
		$variant = Catalog::variant();
		$data    = $meta ? Catalog::load( $id, $variant ) : null;
		if ( ! $data ) {
			return new \WP_Error( 'digitalisimo_sales_template', sprintf( 'No se pudo leer la plantilla %s (%s).', $id, $variant ), array( 'status' => 500 ) );
		}
		$title = is_string( $input['title'] ) ? trim( sanitize_text_field( $input['title'] ) ) : '';
		if ( '' === $title ) {
			return self::invalid( 'title debe ser un texto.' );
		}
		$built = Builder::build(
			$data,
			$meta,
			array(
				'replacements' => $input['replacements'] ?? array(),
				'omit_blocks'  => $input['omit_blocks'] ?? array(),
				'whatsapp'     => $input['whatsapp'] ?? '',
				'close_date'   => $input['close_date'] ?? '',
			),
			array(
				'site_host' => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
				'timezone'  => wp_timezone(),
				'now'       => time(),
			)
		);
		if ( is_wp_error( $built ) ) {
			return $built;
		}
		$page = call_user_func( $create, $title, $built['elements'] );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		$links = array();
		foreach ( $meta['internal_links'] as $link ) {
			if ( '/' === $link['path'][0] ) {
				$links[] = $link;
			}
		}
		$review = array();
		if ( $built['real_data'] ) {
			$review[] = 'Comprueba que estos datos son reales y tienen permiso antes de publicar: ' . implode( ', ', $built['real_data'] ) . '.';
		}
		if ( $built['pending_count'] ) {
			$review[] = sprintf( 'Quedan %d placeholders sin llenar (%d apariciones): llénalos o borra su bloque antes de publicar.', count( $built['pending'] ), $built['pending_count'] );
		}
		if ( $built['whatsapp_pending'] ) {
			$review[] = 'Los botones de WhatsApp conservan el número de muestra: pasa whatsapp o cámbialo en el editor.';
		}
		if ( $built['removed_urgency'] ) {
			$review[] = 'Sin fecha de cierre real se quitaron la barra de cierre o el contador; los textos que nombran el cierre quedan pendientes.';
		}
		if ( $built['broken_anchors'] ) {
			$review[] = 'Hay botones que llevan a un bloque que ya no está (#' . implode( ', #', $built['broken_anchors'] ) . '): cámbialos o bórralos en el editor.';
		}
		return array(
			'ok'               => true,
			'tool'             => 'create-page',
			'id'               => (int) $page['id'],
			'status'           => (string) $page['status'],
			'template'         => $id,
			'variant'          => $variant,
			'title'            => $title,
			'edit_url'         => (string) $page['edit_url'],
			'preview_url'      => (string) $page['preview_url'],
			'filled'           => $built['filled'],
			'filled_keys'      => $built['filled_keys'],
			'ignored'          => $built['ignored'],
			'pending'          => $built['pending'],
			'pending_count'    => $built['pending_count'],
			'real_data'        => $built['real_data'],
			'whatsapp_pending' => $built['whatsapp_pending'],
			'close_date'       => $built['close_date'],
			'removed_urgency'  => $built['removed_urgency'],
			'omitted_blocks'   => $built['omitted_blocks'],
			'broken_anchors'   => $built['broken_anchors'],
			'pages_to_create'  => $links,
			'review'           => $review,
			'llm_instructions' => 'La página quedó en BORRADOR. Muestra al usuario la vista previa (' . (string) $page['preview_url'] . ') y lo que falta revisar; publicarla le toca a una persona.',
		);
	}
}
