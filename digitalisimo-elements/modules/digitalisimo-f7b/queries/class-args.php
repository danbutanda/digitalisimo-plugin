<?php
namespace Digitalisimo\Elements\F7b\Queries;

use ElementorPro\Modules\QueryControl\Classes\Elementor_Post_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Argumentos de **selección** de una consulta guardada, construidos con el mismo código que el grupo de consulta de PRO
 * (`modules/query-control/classes/elementor-post-query.php`): esta clase sólo extiende su constructor de argumentos
 * para leer los ajustes de la consulta en lugar de los de un widget, y llama a sus métodos en el mismo orden que
 * `get_query_args()` cuando el origen no es «Selección manual» (`:131-145`). Así una consulta guardada da los mismos
 * resultados que el grupo de PRO con los mismos ajustes (criterio 5) sin copiar su lógica.
 *
 * Lo que no hace aquí (y sí PRO en el widget): la paginación (`set_pagination_args()`: `posts_per_page` e
 * `ignore_sticky_posts` son del widget, C1-5) y el filtro `elementor/query/query_args` (lo aplica `Runtime` dentro de
 * ese mismo filtro, a prioridad 5).
 *
 * Fallar cerrado donde PRO amplía: si la consulta incluye «por términos» y ninguno de sus `term_taxonomy_id` existe ya,
 * PRO no añade ningún `tax_query` y saldrían todas las entradas; aquí `closed()` lo marca y `Runtime` devuelve vacío.
 * Lo mismo con «por autores» sin autores (sólo pasa con datos de un kit o de otra versión: el saneado del guardado
 * quita esas opciones vacías).
 */
final class Args extends Elementor_Post_Query {
	const PREFIX = 'digi_q_';

	/** Claves de selección que aporta una consulta guardada (C1-5) y que se quitan de lo que traía el widget. */
	const SELECTION_KEYS = array(
		'post_type',
		'post__in',
		'post__not_in',
		'tax_query',
		'author',
		'author_name',
		'author__in',
		'author__not_in',
		'date_query',
		'orderby',
		'order',
		'meta_query',
		'meta_key',
		'meta_value',
		'post_parent',
		'post_parent__in',
		'post_parent__not_in',
		'cat',
		'category_name',
		'category__in',
		'tag',
		'tag_id',
		'tag__in',
		'p',
		'page_id',
		'name',
		'pagename',
		's',
	);

	/** @var bool|null ¿Resolvió algún término la inclusión «por términos»? `null`: no se pidió. */
	private $included_terms = null;

	/**
	 * @param array $settings Ajustes ya saneados por `Store::sanitize_settings()`, sin prefijo.
	 */
	public function __construct( array $settings ) {
		$this->widget     = null;
		$this->prefix     = self::PREFIX;
		$this->query_args = array();
		$prefixed         = array();
		foreach ( $settings as $key => $value ) {
			$prefixed[ self::PREFIX . $key ] = $value;
		}
		$this->widget_settings = function_exists( 'wp_parse_args' ) ? wp_parse_args( $prefixed, $this->get_query_defaults() ) : $prefixed + $this->get_query_defaults();
	}

	/**
	 * Argumentos de selección de unos ajustes saneados.
	 *
	 * @return array `{ args, closed }`: `closed` es verdadero si la consulta debe devolver vacío (inclusión imposible).
	 */
	public static function build( array $settings ): array {
		$builder = new self( $settings );
		$builder->set_common_args();
		$builder->set_order_args();
		$builder->set_post_exclude_args();
		$builder->set_avoid_duplicates();
		$builder->set_terms_args();
		$builder->set_author_args();
		$builder->set_date_args();

		$args = $builder->query_args;
		unset( $args['post_status'] );
		return array(
			'args'   => $args,
			'closed' => $builder->closed( $settings ),
		);
	}

	/**
	 * Registra si la inclusión por términos resolvió alguno (el grupo de PRO omite los que ya no existen).
	 *
	 * @param array $terms   Taxonomía => IDs que sí existen.
	 * @param bool  $exclude ¿Es la exclusión?
	 */
	protected function insert_tax_query( $terms, $exclude ) {
		if ( ! $exclude ) {
			$this->included_terms = ! empty( $terms );
		}
		parent::insert_tax_query( $terms, $exclude );
	}

	private function closed( array $settings ): bool {
		$include = isset( $settings['include'] ) && is_array( $settings['include'] ) ? $settings['include'] : array();
		if ( in_array( 'terms', $include, true ) && ( empty( $settings['include_term_ids'] ) || false === $this->included_terms ) ) {
			return true;
		}
		return in_array( 'authors', $include, true ) && empty( $settings['include_authors'] );
	}
}
