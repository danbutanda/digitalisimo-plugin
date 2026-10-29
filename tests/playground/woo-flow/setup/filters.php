<?php
/**
 * Preparación del grupo `filters` de woo-flow.sh (W9 y W10; F4-Woo, V.8 y V.16.4), en la petición `?digi_wf=setup` de
 * cada sitio, con WooCommerce cargado y el usuario 1. Idempotente.
 *
 * - **Términos:** la categoría `digi-wf-calzado` con tres hijas (`digi-wf-botas`, `digi-wf-sandalias` y
 *   `digi-wf-deportivas`: dos hijas del mismo padre con «O» pasan por `Hierarchy_Or_Query`), la etiqueta
 *   `digi-wf-filtros` (la base de los bucles y de la tienda nativa con la que se comparan) y el atributo global
 *   `pa_talla-zapato` (con guion: B3) con `eu-38`, `eu-40` y `eu-42`.
 * - **Productos:** ninguno. W0.4 cuenta siete publicados justo después de la preparación; los ocho del calzado los crea
 *   y publica la operación `filters_catalog` (ops/filters.php) al empezar W9 o W10, junto con la regeneración de la
 *   tabla de atributos.
 * - **Plantilla de bucle** `digi-wf-flt-item` (fuente «Productos»): un encabezado con el título.
 * - **Páginas:**
 *   - `digi-wf-filtros`: filtro de precio (destino Loop Grid, «Al cambiar»), filtro de atributo (casillas, «O»), filtro
 *     de taxonomía de PRO (`product_cat`, varios con «O», con hijas) y el Loop Grid `wfloop1` (productos de la
 *     etiqueta); detrás, un segundo bucle `wfloop4` con sólo el filtro de PRO (`wftax04`): sin filtros F4-Woo, el
 *     camino de PRO;
 *   - `digi-wf-filtros-orden`: filtro de precio y el Loop Grid `wfloop2` ordenado por precio;
 *   - `digi-wf-filtros-pro`: sólo el filtro de PRO y el bucle `wfloop3` (una página sin F4-Woo).
 * - **Plantilla de archivo** `digi-wf-flt-archivo` («Archivo de productos», condición: el archivo de
 *   `digi-wf-calzado`): filtros de precio y de atributo con destino «Tienda», el widget Productos de PRO con «Consulta
 *   actual» y paginación, y un Loop Grid `wfloop5` con «Consulta actual» (W10).
 *
 * Devuelve `$out['filters']` con las páginas (ID y URL), los términos, las URL de los archivos y los IDs de los widgets.
 */

defined( 'ABSPATH' ) || exit;

return static function ( array $out, callable $fail ): array {
	$note = static function ( string $message ) use ( $fail ) {
		$fail( 'filters: ' . $message );
	};
	$f = array();
	if ( ! defined( 'ELEMENTOR_VERSION' ) || ! function_exists( 'wc_create_attribute' ) ) {
		$note( 'faltan el constructor o WooCommerce' );
		$out['filters'] = $f;
		return $out;
	}

	// Términos: categoría con tres hijas y etiqueta.
	$term = static function ( string $taxonomy, string $slug, string $name, int $parent = 0 ) use ( $note ): array {
		$found = get_term_by( 'slug', $slug, $taxonomy );
		if ( $found ) {
			return array( 'id' => (int) $found->term_id, 'tt' => (int) $found->term_taxonomy_id );
		}
		$made = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug, 'parent' => $parent ) );
		if ( is_wp_error( $made ) ) {
			$note( "término $taxonomy/$slug: " . $made->get_error_message() );
			return array( 'id' => 0, 'tt' => 0 );
		}
		return array( 'id' => (int) $made['term_id'], 'tt' => (int) $made['term_taxonomy_id'] );
	};
	$cats           = array();
	$cats['calzado'] = $term( 'product_cat', 'digi-wf-calzado', 'Calzado de prueba' );
	foreach ( array( 'botas' => 'Botas de prueba', 'sandalias' => 'Sandalias de prueba', 'deportivas' => 'Deportivas de prueba' ) as $key => $name ) {
		$cats[ $key ] = $term( 'product_cat', 'digi-wf-' . $key, $name, $cats['calzado']['id'] );
	}
	$tag = $term( 'product_tag', 'digi-wf-filtros', 'Filtros de prueba' );

	// Atributo global con guion en el nombre (B3) y sus términos.
	$taxonomy = 'pa_talla-zapato';
	if ( ! wc_attribute_taxonomy_id_by_name( $taxonomy ) ) {
		$created = wc_create_attribute( array( 'name' => 'Talla de zapato', 'slug' => 'talla-zapato', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ) );
		if ( is_wp_error( $created ) ) {
			$note( 'atributo talla-zapato: ' . $created->get_error_message() );
		}
	}
	// WooCommerce registra las taxonomías de los atributos en `init`: la recién creada aún no existe en esta petición.
	if ( ! taxonomy_exists( $taxonomy ) ) {
		register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => false, 'rewrite' => false ) );
	}
	$sizes = array();
	foreach ( array( 'eu-38' => '38', 'eu-40' => '40', 'eu-42' => '42' ) as $slug => $name ) {
		$sizes[ $slug ] = $term( $taxonomy, $slug, $name )['id'];
	}

	// Plantilla de bucle de productos: un encabezado con el título.
	$library = static function ( string $slug, string $title, string $type, array $tree, array $meta = array() ) use ( $note ): int {
		$found = get_posts( array( 'post_type' => 'elementor_library', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
		$id    = $found ? (int) $found[0] : (int) wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ) );
		if ( ! $id ) {
			$note( "no se pudo crear la plantilla $slug" );
			return 0;
		}
		update_post_meta( $id, '_elementor_template_type', $type );
		wp_set_object_terms( $id, $type, 'elementor_library_type' );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		delete_post_meta( $id, '_elementor_css' );
		return $id;
	};
	$widget = static function ( string $id, string $type, array $settings ): array {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	};
	$box    = static function ( string $id, array $children ): array {
		return array( 'id' => $id, 'elType' => 'container', 'isInner' => false, 'settings' => array( 'flex_direction' => 'column' ), 'elements' => $children );
	};
	$item = $library(
		'digi-wf-flt-item',
		'Ficha de filtros (woo-flow)',
		'loop-item',
		array( $box( 'wfitemc', array( $widget( 'wfitemh', 'heading', array( 'title' => 'Producto', 'header_size' => 'h3', '__dynamic__' => array( 'title' => '[elementor-tag id="wfitemt" name="post-title" settings="%7B%7D"]' ) ) ) ) ) ),
		array( '_elementor_source' => 'product' )
	);

	// Bucles de productos de la etiqueta `digi-wf-filtros`.
	$loop = static function ( string $id, array $extra = array() ) use ( $widget, $item, $tag ): array {
		return $widget(
			$id,
			'loop-grid',
			array_merge(
				array(
					'_skin'                          => 'product',
					'template_id'                    => (string) $item,
					'posts_per_page'                 => 20,
					'columns'                        => 4,
					'product_query_post_type'        => 'product',
					'product_query_include'          => array( 'terms' ),
					'product_query_include_term_ids' => array( (string) $tag['tt'] ),
				),
				$extra
			)
		);
	};
	$price = static function ( string $id, string $loop_id, array $extra = array() ) use ( $widget ): array {
		return $widget( $id, 'digitalisimo-woo-price-filter', array_merge( array( 'destination' => 'loop', 'selected_element' => $loop_id, 'bounds' => 'fixed', 'bound_min' => 0, 'bound_max' => 200, 'step' => 1, 'display' => 'both', 'apply' => 'change' ), $extra ) );
	};
	$attribute = static function ( string $id, string $loop_id, array $extra = array() ) use ( $widget, $taxonomy ): array {
		return $widget( $id, 'digitalisimo-woo-attribute-filter', array_merge( array( 'destination' => 'loop', 'selected_element' => $loop_id, 'attribute' => $taxonomy, 'display' => 'checkboxes', 'selection' => 'multiple', 'logic' => 'OR', 'hide_empty' => '' ), $extra ) );
	};
	$pro = static function ( string $id, string $loop_id ) use ( $widget ): array {
		return $widget( $id, 'taxonomy-filter', array( 'selected_element' => $loop_id, 'taxonomy' => 'product_cat', 'multiple_selection' => 'yes', 'logical_combination' => 'OR', 'show_child_taxonomy' => 'yes', 'child_taxonomy_depth' => '1', 'show_first_item' => 'yes' ) );
	};
	$page = static function ( string $slug, string $title, array $tree ) use ( $note ): array {
		$found = get_page_by_path( $slug );
		$id    = $found ? (int) $found->ID : (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => '' ) );
		if ( ! $id ) {
			$note( "no se pudo crear la página $slug" );
			return array( 'id' => 0, 'url' => '' );
		}
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
		delete_post_meta( $id, '_elementor_css' );
		return array( 'id' => $id, 'url' => (string) get_permalink( $id ) );
	};

	$f['pages'] = array(
		'main'  => $page(
			'digi-wf-filtros',
			'Filtros del Loop Grid',
			array(
				$box( 'wfcont1', array( $price( 'wfprice', 'wfloop1' ), $attribute( 'wfattr1', 'wfloop1' ), $pro( 'wftax01', 'wfloop1' ), $loop( 'wfloop1' ) ) ),
				$box( 'wfcont4', array( $pro( 'wftax04', 'wfloop4' ), $loop( 'wfloop4' ) ) ),
			)
		),
		'order' => $page(
			'digi-wf-filtros-orden',
			'Filtros con orden por precio',
			array( $box( 'wfcont2', array( $price( 'wfprc02', 'wfloop2' ), $loop( 'wfloop2', array( 'product_query_orderby' => 'price', 'product_query_order' => 'asc' ) ) ) ) )
		),
		'pro'   => $page(
			'digi-wf-filtros-pro',
			'Filtro de PRO sin F4-Woo',
			array( $box( 'wfcont3', array( $pro( 'wftax03', 'wfloop3' ), $loop( 'wfloop3' ) ) ) )
		),
	);

	// W10: plantilla «Archivo de productos» aplicada sólo al archivo de `digi-wf-calzado`.
	$archive = $library(
		'digi-wf-flt-archivo',
		'Archivo de calzado (woo-flow)',
		'product-archive',
		array(
			$box(
				'wfcont5',
				array(
					$price( 'wfprc05', '', array( 'destination' => 'shop', 'apply' => 'button' ) ),
					$attribute( 'wfatt05', '', array( 'destination' => 'shop' ) ),
					$widget( 'wfprod5', 'woocommerce-products', array( 'query_post_type' => 'current_query', 'paginate' => 'yes', 'allow_order' => '', 'show_result_count' => '', 'columns' => 4, 'rows' => 4 ) ),
					$loop( 'wfloop5', array( 'product_query_post_type' => 'current_query', 'product_query_include' => array(), 'product_query_include_term_ids' => array() ) ),
				)
			),
		)
	);
	$conditions = class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ? \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager() : null;
	if ( $archive && $conditions && $cats['calzado']['id'] ) {
		$conditions->save_conditions( $archive, array( array( 'type' => 'include', 'name' => 'product_archive', 'sub_name' => 'product_cat', 'sub_id' => (string) $cats['calzado']['id'] ) ) );
		$conditions->get_cache()->regenerate();
	} else {
		$note( 'no se pudo condicionar la plantilla de archivo (Theme Builder ausente)' );
	}

	$f['item']     = $item;
	$f['archive']  = $archive;
	$f['cats']     = $cats;
	$f['tag']      = $tag;
	$f['sizes']    = $sizes;
	$f['taxonomy'] = $taxonomy;
	$f['links']    = array();
	foreach ( array( 'tag' => array( (int) $tag['id'], 'product_tag' ), 'calzado' => array( (int) $cats['calzado']['id'], 'product_cat' ) ) as $key => $data ) {
		$link             = $data[0] ? get_term_link( $data[0], $data[1] ) : '';
		$f['links'][ $key ] = is_string( $link ) ? $link : '';
		if ( '' === $f['links'][ $key ] ) {
			$note( "sin enlace del archivo $key" );
		}
	}
	$out['filters'] = $f;
	return $out;
};
