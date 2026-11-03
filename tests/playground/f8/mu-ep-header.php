<?php
/**
 * Plugin Name: Cabecera simulada del origen (sólo Playground, f8.sh)
 * Description: mu-plugin de tests/playground/f8.sh (F8, frente J). Nunca forma parte de un plugin.
 *
 * Imitación *clean-room* del comportamiento documentado del Theme Builder del origen (research-ep.md §2.1.1–§2.1.5, sólo
 * formatos y reglas descritos con palabras propias; ni una línea de su código). Sirve para P2, P3 y P9: con el plugin
 * falso «Element Pack Pro» activo (sólo define BDTEP_VER), este archivo hace lo que el origen haría con la cabecera y el
 * pie, para que el arnés pueda exigir **exactamente una** cabecera y un pie en cada petición mientras «Migrar» avanza:
 *
 * - registra el tipo `bdt-template-builder` (con el origen desactivado, el tipo deja de existir, como en un sitio real);
 * - en cada petición del front elige la cabecera y el pie: plantillas publicadas cuyo tipo es `themes|header` o
 *   `themes|footer`, **consultadas por ID descendente**, activas por su opción `_bdthemes_builder_<tipo>__<ID>` o por la
 *   heredada `_bdthemes_builder_<tipo>`, y con condiciones que coinciden (al menos una fila `include` y ninguna
 *   `exclude`; sin filas, «todo el sitio»). Cada coincidencia sustituye a la anterior: **gana la última**, es decir, la
 *   más antigua;
 * - pinta `<header data-ep-fake="ID">` al abrir `body` y `<footer data-ep-fake-footer="ID">` antes de `wp_footer`.
 *
 * Es más estricto que el origen: éste descarta el `header.php` del tema, así que una copia publicada por error quedaría
 * oculta; aquí se pintan las dos y el sondeo lo detecta.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'digi_f8_ep_loaded' ) ) {
	/** ¿Está «cargado» el origen? Lo decide el plugin falso, como el real, con su constante. */
	function digi_f8_ep_loaded() {
		return defined( 'BDTEP_VER' );
	}
}

add_action(
	'init',
	static function () {
		if ( ! digi_f8_ep_loaded() || post_type_exists( 'bdt-template-builder' ) ) {
			return;
		}
		register_post_type(
			'bdt-template-builder',
			array(
				'label'               => 'Plantillas simuladas',
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'exclude_from_search' => true,
				'supports'            => array( 'title', 'editor', 'elementor' ),
				'capability_type'     => 'post',
				'rewrite'             => false,
			)
		);
	},
	0
);

/** Filas de condiciones de una plantilla (JSON; si falta, la fila heredada de las claves sueltas; si no, todo el sitio). */
function digi_f8_ep_rows( $id ) {
	$rows = json_decode( (string) get_post_meta( $id, '_bdthemes_builder_template_conditions', true ), true );
	if ( is_array( $rows ) && $rows ) {
		return $rows;
	}
	$a = (string) get_post_meta( $id, '_bdthemes_builder_template_condition_a', true );
	return array(
		array(
			'polarity'              => 'include',
			'condition_a'           => '' === $a ? 'entire_site' : $a,
			'condition_singular'    => (string) get_post_meta( $id, '_bdthemes_builder_template_condition_singular', true ),
			'condition_archive'     => (string) get_post_meta( $id, '_bdthemes_builder_template_condition_archive', true ),
			'condition_woo'         => (string) get_post_meta( $id, '_bdthemes_builder_template_condition_woo', true ),
			'condition_singular_id' => (string) get_post_meta( $id, '_bdthemes_builder_template_condition_singular_id', true ),
		),
	);
}

/** ¿Coincide una fila con la petición? Subconjunto de las reglas descritas; un valor desconocido nunca coincide. */
function digi_f8_ep_row_matches( array $row ) {
	$a    = (string) ( $row['condition_a'] ?? '' );
	$ids  = array_filter( array_map( 'intval', explode( ',', (string) ( $row['condition_singular_id'] ?? '' ) ) ) );
	$here = (int) get_queried_object_id();
	$woo  = function_exists( 'is_woocommerce' ) && is_woocommerce();
	switch ( $a ) {
		case 'entire_site':
			return true;
		case 'singular':
			$sub = (string) ( $row['condition_singular'] ?? '' );
			$sub = '' === $sub ? 'all' : $sub;
			switch ( $sub ) {
				case 'all':
					return ( is_singular() && ! is_embed() ) || is_404();
				case 'front_page':
					return is_front_page();
				case 'all_pages':
					return is_page();
				case 'all_posts':
					return is_singular( 'post' );
				case 'not_found404':
				case '404page':
					return is_404();
				case 'selective':
					return is_singular() && in_array( $here, $ids, true );
			}
			return 0 === strpos( $sub, 'pt_' ) && is_singular( substr( $sub, 3 ) );
		case 'archive':
			$sub = (string) ( $row['condition_archive'] ?? '' );
			$sub = '' === $sub ? 'all' : $sub;
			if ( 'all' === $sub ) {
				return ( is_archive() || is_home() || is_search() ) && ! $woo;
			}
			if ( 'search' === $sub ) {
				return is_search();
			}
			if ( 'category' === $sub ) {
				return is_category( $ids ? $ids : '' );
			}
			return false;
		case 'woocommerce':
			if ( ! function_exists( 'is_woocommerce' ) ) {
				return false;
			}
			$sub = (string) ( $row['condition_woo'] ?? '' );
			$sub = '' === $sub ? 'all' : $sub;
			$map = array(
				'all'      => $woo,
				'shop'     => function_exists( 'is_shop' ) && is_shop(),
				'cart'     => function_exists( 'is_cart' ) && is_cart(),
				'checkout' => function_exists( 'is_checkout' ) && is_checkout() && ! ( function_exists( 'is_order_received_page' ) && is_order_received_page() ),
			);
			return ! empty( $map[ $sub ] );
	}
	return false;
}

/** ¿Aplica la plantilla? Al menos una `include` que coincide y ninguna `exclude` que coincida. */
function digi_f8_ep_applies( $id ) {
	$include = false;
	foreach ( digi_f8_ep_rows( $id ) as $row ) {
		$hit = digi_f8_ep_row_matches( (array) $row );
		if ( 'exclude' === ( $row['polarity'] ?? 'include' ) ) {
			if ( $hit ) {
				return false;
			}
		} elseif ( $hit ) {
			$include = true;
		}
	}
	return $include;
}

/** ID de la cabecera o del pie que pintaría el origen en esta petición (0 si ninguno). */
function digi_f8_ep_location( $type ) {
	global $wpdb;
	// Consulta directa por estado publicado e ID descendente, como la del origen: no pasa por get_post_status.
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_bdthemes_builder_template_type' WHERE p.post_type = 'bdt-template-builder' AND p.post_status = 'publish' AND m.meta_value = %s ORDER BY p.ID DESC", 'themes|' . $type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$legacy = get_option( '_bdthemes_builder_themes|' . $type, '' );
	$chosen = 0;
	foreach ( array_map( 'intval', (array) $ids ) as $id ) {
		$active = (string) get_option( '_bdthemes_builder_themes|' . $type . '__' . $id, '' ) === (string) $id || '' !== (string) $legacy;
		if ( $active && digi_f8_ep_applies( $id ) ) {
			$chosen = $id; // la última coincidencia gana.
		}
	}
	return $chosen;
}

/** ¿Petición del front que el origen atendería? */
function digi_f8_ep_front() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() || is_embed() ) {
		return false;
	}
	return 'elementor_canvas' !== get_page_template_slug( (int) get_queried_object_id() );
}

add_action(
	'wp_body_open',
	static function () {
		if ( ! digi_f8_ep_loaded() || ! digi_f8_ep_front() ) {
			return;
		}
		$id = digi_f8_ep_location( 'header' );
		if ( $id ) {
			echo '<header data-ep-fake="' . (int) $id . '" class="digi-ep-fake"><p>' . esc_html( get_the_title( $id ) ) . '</p></header>';
		}
	},
	1
);

add_action(
	'wp_footer',
	static function () {
		if ( ! digi_f8_ep_loaded() || ! digi_f8_ep_front() ) {
			return;
		}
		$id = digi_f8_ep_location( 'footer' );
		if ( $id ) {
			echo '<footer data-ep-fake-footer="' . (int) $id . '" class="digi-ep-fake"><p>' . esc_html( get_the_title( $id ) ) . '</p></footer>';
		}
	},
	1
);
