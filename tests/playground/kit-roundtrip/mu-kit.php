<?php
/**
 * mu-plugin de kit-roundtrip.sh: registro de fatales, duración de cada petición REST (A13), cron apagado y el
 * controlador `?digi-kit=<op>&token=<token>` que usa run.cjs. El token sale de probe/options.json (lo escribe el
 * arnés); sin él, el controlador no responde.
 *
 * Operaciones: ping, snapshot (A6, A8, A9, A10), signature (A1, A4), sessions, report, clash y clash-v4 (R4), content
 * (B con contenido, R5), users (R9), option, kit, unconverted y logs.
 */

if ( ! defined( 'DIGI_K_PROBE' ) ) {
	define( 'DIGI_K_PROBE', '/wordpress/wp-content/probe' );
	define( 'DIGI_K_SHARED', '/wordpress/wp-content/digi-shared' );
}
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}

register_shutdown_function(
	static function () {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
			file_put_contents( DIGI_K_PROBE . '/fatal.log', ( $_SERVER['REQUEST_URI'] ?? 'cli' ) . "\n" . $error['message'] . ' @ ' . $error['file'] . ':' . $error['line'] . "\n\n", FILE_APPEND );
		}
		if ( isset( $GLOBALS['digi_k_rest_start'] ) ) {
			$entry = array(
				'route'  => (string) ( $GLOBALS['digi_k_rest_route'] ?? '' ),
				'method' => (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ),
				'ms'     => (int) round( ( microtime( true ) - $GLOBALS['digi_k_rest_start'] ) * 1000 ),
				'status' => (int) ( $GLOBALS['digi_k_rest_status'] ?? http_response_code() ),
				'runner' => (string) ( $GLOBALS['digi_k_rest_runner'] ?? '' ),
				'label'  => (string) ( $_SERVER['HTTP_X_DIGI_K_LABEL'] ?? '' ),
			);
			file_put_contents( DIGI_K_PROBE . '/rest.jsonl', json_encode( $entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n", FILE_APPEND );
		}
	}
);

// Duración de cada petición REST, desde que WordPress la despacha hasta el final del proceso (A13: ≤ 25 s).
add_filter(
	'rest_pre_dispatch',
	static function ( $result, $server, $request ) {
		if ( ! isset( $GLOBALS['digi_k_rest_start'] ) ) {
			$GLOBALS['digi_k_rest_start']  = (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) );
			$GLOBALS['digi_k_rest_route']  = $request->get_route();
			$GLOBALS['digi_k_rest_runner'] = (string) $request->get_param( 'runner' );
		}
		return $result;
	},
	-1000,
	3
);
add_filter(
	'rest_post_dispatch',
	static function ( $response ) {
		if ( $response instanceof WP_HTTP_Response ) {
			$GLOBALS['digi_k_rest_status'] = $response->get_status();
		}
		return $response;
	},
	PHP_INT_MAX
);

/** Opciones que cambian solas y nunca cuentan en las instantáneas (A6): transitorios, cron y sesiones. */
function digi_k_volatile_option( $name ) {
	return (bool) preg_match( '/^(_site)?_transient_|^cron$|^elementor_import_sessions$|^elementor_revert_sessions$|^elementor_import_revert_sessions$|^digitalisimo_elements_kit_session_|^digitalisimo_elements_unconverted$|^digi_k_/', $name );
}

/**
 * Cachés derivadas que el constructor reescribe enteras (con otro orden) o borra para regenerarlas: la instantánea
 * guarda su contenido decodificado y ordenado en `caches`, y compare.mjs las compara por contenido (igual, o
 * sólo con entradas nuevas en «agregar»). Vacía equivale a ausente.
 */
const DIGI_K_CACHES = array( 'elementor_pro_theme_builder_conditions', 'elementor_custom_icon_sets_config' );

/** Valor canónico de una caché: arrays asociativos por clave y listas de escalares ordenadas. */
function digi_k_canon( $value ) {
	if ( is_object( $value ) ) {
		$value = get_object_vars( $value );
	}
	if ( ! is_array( $value ) ) {
		return is_scalar( $value ) || null === $value ? $value : gettype( $value );
	}
	$value = array_map( 'digi_k_canon', $value );
	if ( array_is_list( $value ) ) {
		if ( ! array_filter( $value, 'is_array' ) ) {
			sort( $value );
		}
		return $value;
	}
	ksort( $value );
	return $value;
}

/** Firma de un árbol de archivos: ruta relativa → tamaño y sha1 (sha1 sólo hasta 4 MB por archivo). */
function digi_k_tree( $dir, $skip = '' ) {
	$out = array();
	if ( ! is_dir( $dir ) ) {
		return $out;
	}
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $info ) {
		if ( ! $info->isFile() ) {
			continue;
		}
		$relative = ltrim( substr( $info->getPathname(), strlen( $dir ) ), '/' );
		if ( '' !== $skip && preg_match( $skip, $relative ) ) {
			continue;
		}
		$out[ $relative ] = $info->getSize() . ':' . ( $info->getSize() <= 4194304 ? sha1_file( $info->getPathname() ) : 'grande' );
	}
	ksort( $out );
	return $out;
}

/** Firma corta de un árbol grande (plugins, temas): número de archivos y sha1 de la lista de rutas y tamaños. */
function digi_k_tree_digest( $dir ) {
	$out = array();
	foreach ( (array) glob( trailingslashit( $dir ) . '*' ) as $entry ) {
		$name = basename( $entry );
		if ( is_file( $entry ) ) {
			$out[ $name ] = filesize( $entry ) . ':' . sha1_file( $entry );
			continue;
		}
		$list = array();
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $entry, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $info ) {
			if ( $info->isFile() ) {
				$list[] = substr( $info->getPathname(), strlen( $entry ) ) . ':' . $info->getSize();
			}
		}
		sort( $list );
		$out[ $name ] = count( $list ) . ':' . sha1( implode( "\n", $list ) );
	}
	ksort( $out );
	return $out;
}

/** Instantánea de un sitio: filas por tabla (sin transitorios ni sesiones), todas las opciones y uploads. */
function digi_k_snapshot_site() {
	global $wpdb;
	$snap   = array( 'blog' => get_current_blog_id(), 'tables' => array(), 'options' => array(), 'caches' => array() );
	$tables = (array) $wpdb->get_col( 'SHOW TABLES' );
	if ( ! $tables ) {
		$tables = array_values( $wpdb->tables( 'blog' ) );
	}
	$prefix = $wpdb->get_blog_prefix();
	// Las metas del kit activo van aparte (`kit_meta`): en «agregar» cambian a propósito (§9.4, B1) y se comparan
	// campo a campo; en las demás comprobaciones (A8, A9, A10) se exigen iguales.
	$kit_id            = (int) get_option( 'elementor_active_kit' );
	$snap['kit_meta']  = array();
	foreach ( $tables as $table ) {
		if ( 0 !== strpos( $table, $prefix ) || ( is_multisite() && 1 === get_current_blog_id() && preg_match( '/^' . preg_quote( $wpdb->base_prefix, '/' ) . '\d+_/', $table ) ) ) {
			continue;
		}
		$short = substr( $table, strlen( $prefix ) );
		if ( in_array( $short, array( 'users', 'usermeta', 'site', 'sitemeta', 'blogs', 'blogmeta', 'signups', 'registration_log' ), true ) && is_multisite() ) {
			continue;
		}
		$rows = (array) $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$hashes = array();
		foreach ( $rows as $row ) {
			if ( 'options' === $short ) {
				if ( digi_k_volatile_option( (string) $row['option_name'] ) ) {
					continue;
				}
				if ( in_array( (string) $row['option_name'], DIGI_K_CACHES, true ) ) {
					$cache = maybe_unserialize( (string) $row['option_value'] );
					if ( ! empty( $cache ) ) {
						$snap['caches'][ $row['option_name'] ] = digi_k_canon( $cache );
					}
					continue;
				}
				$snap['options'][ $row['option_name'] ] = strlen( (string) $row['option_value'] ) <= 300 ? (string) $row['option_value'] : 'md5:' . md5( (string) $row['option_value'] );
				continue;
			}
			if ( 'usermeta' === $short && preg_match( '/session_tokens|_dashboard_quick_press_last_post_id|digitalisimo_elements_kit_notice/', (string) ( $row['meta_key'] ?? '' ) ) ) {
				continue;
			}
			if ( 'postmeta' === $short && $kit_id && (int) $row['post_id'] === $kit_id ) {
				// Por contenido (serializado o JSON, ordenado), sin las marcas que el constructor reescribe en cada
				// guardado del kit (versión con que se guardó, índice de uso de clases globales, bloqueo de edición).
				if ( ! preg_match( '/^(_elementor_version|_elementor_pro_version|_elementor_global_class_usage_indexed(_preview)?|_edit_lock|_edit_last)$/', (string) $row['meta_key'] ) ) {
					$value = maybe_unserialize( (string) $row['meta_value'] );
					if ( is_string( $value ) && '' !== $value && ( '{' === $value[0] || '[' === $value[0] ) ) {
						$json  = json_decode( $value, true );
						$value = null === $json ? $value : $json;
					}
					$snap['kit_meta'][ (string) $row['meta_key'] ] = md5( (string) wp_json_encode( digi_k_canon( $value ) ) );
				}
				continue;
			}
			// La fila del kit en posts: su fecha de modificación cambia con cualquier guardado del kit (también al
			// añadir globales en «agregar», B1); el resto de la fila va con las metas del kit.
			if ( 'posts' === $short && $kit_id && (int) $row['ID'] === $kit_id ) {
				unset( $row['post_modified'], $row['post_modified_gmt'] );
				$snap['kit_meta']['(fila de posts)'] = md5( (string) wp_json_encode( $row ) );
				continue;
			}
			// El número de entradas de un término (count) es un contador derivado: cambia con cada entrada nueva que
			// se asigna a un término que ya existía. Las asignaciones nuevas ya cuentan en term_relationships.
			if ( 'term_taxonomy' === $short ) {
				unset( $row['count'] );
			}
			$key            = (string) reset( $row );
			$hashes[ $key ] = md5( wp_json_encode( $row ) );
		}
		ksort( $hashes );
		$snap['tables'][ $short ] = array( 'rows' => count( $hashes ), 'digest' => md5( wp_json_encode( $hashes ) ), 'hashes' => count( $hashes ) <= 5000 ? $hashes : array() );
	}
	ksort( $snap['options'] );
	// Sesiones con la limpieza de temporales de K programada (§5.3: evento único a 24 h con el ID de sesión).
	$snap['kit_cleanup'] = array();
	foreach ( (array) _get_cron_array() as $events ) {
		foreach ( (array) ( $events['digitalisimo_elements_kits_cleanup'] ?? array() ) as $event ) {
			$snap['kit_cleanup'][] = (string) ( $event['args'][0] ?? '' );
		}
	}
	sort( $snap['kit_cleanup'] );
	$uploads         = wp_upload_dir( null, false );
	$snap['uploads'] = digi_k_tree( $uploads['basedir'], is_multisite() && 1 === get_current_blog_id() ? '#^sites/#' : '' );
	return $snap;
}

/** Instantánea completa: el sitio actual (o cada sitio de la red), sitemeta y los árboles de plugins y temas. */
function digi_k_snapshot() {
	global $wpdb;
	$snap = array( 'time' => time(), 'sites' => array() );
	if ( is_multisite() ) {
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 50 ) ) as $blog_id ) {
			switch_to_blog( $blog_id );
			$snap['sites'][ $blog_id ] = digi_k_snapshot_site();
			restore_current_blog();
		}
		$meta = array();
		foreach ( (array) $wpdb->get_results( "SELECT meta_key, meta_value FROM {$wpdb->sitemeta}", ARRAY_A ) as $row ) {
			if ( preg_match( '/^_site_transient_/', $row['meta_key'] ) ) {
				continue;
			}
			$meta[ $row['meta_key'] ] = md5( (string) $row['meta_value'] );
		}
		ksort( $meta );
		$snap['sitemeta'] = $meta;
	} else {
		$snap['sites'][ get_current_blog_id() ] = digi_k_snapshot_site();
	}
	$snap['plugins'] = digi_k_tree_digest( WP_PLUGIN_DIR );
	$snap['themes']  = digi_k_tree_digest( get_theme_root() );
	$snap['mu']      = digi_k_tree_digest( WPMU_PLUGIN_DIR );
	return $snap;
}

add_action(
	'init',
	static function () {
		if ( empty( $_GET['digi-kit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$opts  = json_decode( (string) @file_get_contents( DIGI_K_PROBE . '/options.json' ), true ) ?: array();
		$token = (string) ( $_GET['token'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $opts['token'] ) || ! hash_equals( (string) $opts['token'], $token ) ) {
			status_header( 403 );
			exit;
		}
		$op    = sanitize_key( wp_unslash( $_GET['digi-kit'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$label = preg_replace( '/[^a-z0-9_-]/i', '', (string) ( $_GET['label'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$blog  = absint( $_GET['blog'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $blog && is_multisite() ) {
			switch_to_blog( $blog );
		}
		require_once __DIR__ . '/lib.php';
		$out = array( 'op' => $op );
		switch ( $op ) {
			case 'ping':
				$out += array(
					'ready'  => (int) get_option( 'digi_k_ready' ),
					'core'   => defined( 'DIGITALISIMO_ELEMENTS_CORE' ) ? 'bundled' : ( defined( 'ELEMENTOR_VERSION' ) ? 'plugin' : 'none' ),
					'k'      => class_exists( '\Digitalisimo\Elements\Import\Module' ),
					'cli'    => class_exists( '\Digitalisimo\Elements\Import\Kits\Cli' ),
					'page'   => class_exists( '\Digitalisimo\Elements\Import\Kits\Report_Page' ),
					'sapi'   => PHP_SAPI,
					'errors' => (string) @file_get_contents( DIGI_K_PROBE . '/setup-error.txt' ),
				);
				break;
			case 'snapshot':
				digi_k_write( 'snap-' . $label . '.json', digi_k_snapshot() );
				$out['file'] = 'snap-' . $label . '.json';
				break;
			case 'signature':
				require_once __DIR__ . '/signature.php';
				digi_k_write( 'sig-' . $label . '.json', digi_k_signature() );
				$out['file'] = 'sig-' . $label . '.json';
				break;
			case 'sessions':
				$out['import'] = get_option( 'elementor_import_sessions', array() );
				$out['revert'] = get_option( 'elementor_revert_sessions', get_option( 'elementor_import_revert_sessions', array() ) );
				$out['k']      = array();
				global $wpdb;
				foreach ( (array) $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'digitalisimo\\_elements\\_kit\\_session\\_%'" ) as $name ) {
					$out['k'][ $name ] = get_option( $name );
				}
				break;
			case 'report':
				$session        = sanitize_text_field( wp_unslash( $_GET['session'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$out['session'] = get_option( 'digitalisimo_elements_kit_session_' . $session );
				break;
			case 'clash':
			case 'clash-v4':
			case 'content':
			case 'users':
			case 'woo-empty':
				$GLOBALS['digi_k_setup_b_mode'] = $op;
				require __DIR__ . '/setup-b.php';
				$out['errors'] = (string) @file_get_contents( DIGI_K_PROBE . '/setup-error.txt' );
				$out['ids']    = digi_k_read_json( DIGI_K_PROBE . '/ids-' . $op . '.json' );
				break;
			case 'find':
				// ¿Tiene el sitio el dato que not-exported.json dice que nunca viaja? (para que la comprobación no sea vacía).
				global $wpdb;
				$kind  = sanitize_key( wp_unslash( $_GET['kind'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$value = (string) wp_unslash( $_GET['value'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$like  = '%' . $wpdb->esc_like( $value ) . '%';
				switch ( $kind ) {
					case 'opcion':
						$out['found'] = false !== get_option( $value, false );
						break;
					case 'meta':
						$out['found'] = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", $value ) );
						break;
					case 'meta_contiene':
						$out['found'] = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $like ) );
						break;
					case 'usuario':
						$out['found'] = (bool) get_user_by( 'login', $value );
						break;
					case 'comentario':
						$out['found'] = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_author_email = %s", $value ) );
						break;
					case 'contrasena':
						$out['found'] = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_password = %s", $value ) );
						break;
					default:
						$out['found'] = null;
				}
				break;
			case 'review':
				// Documentos de la sesión marcados «revisar antes de publicar» (A14), como claves de la firma.
				$record = get_option( 'digitalisimo_elements_kit_session_' . sanitize_text_field( wp_unslash( $_GET['session'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$ids    = array();
				foreach ( array( 'review', 'review_ids' ) as $field ) {
					foreach ( (array) ( is_array( $record ) && isset( $record[ $field ] ) ? $record[ $field ] : array() ) as $key => $item ) {
						$ids[] = (int) ( is_array( $item ) ? ( $item['post'] ?? $item['id'] ?? $key ) : $item );
					}
				}
				$out['keys'] = array();
				foreach ( array_unique( array_filter( $ids ) ) as $id ) {
					$post = get_post( $id );
					if ( $post ) {
						$out['keys'][] = $post->post_type . ':' . $post->post_name;
					}
				}
				break;
			case 'option':
				$out['value'] = get_option( sanitize_key( wp_unslash( $_GET['name'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			case 'kit':
				$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
				$out += array( 'id' => (int) $kit->get_id(), 'settings' => $kit->get_settings(), 'defaults' => $kit->get_json_meta( '_elementor_elements_default_values' ), 'meta' => get_post_meta( $kit->get_id() ) );
				break;
			case 'woo':
				// Estado de WooCommerce de F4-Woo (WOO=1): el de la firma (atributos, muestras y tabla de búsqueda, con
				// nombres) más los IDs de los atributos, los SKU y precios de las variaciones y su número por estado.
				require_once __DIR__ . '/signature.php';
				if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
					$out['active'] = false;
					break;
				}
				$sig            = new Digi_K_Signature( '', array() );
				$out['active']  = true;
				$out['state']   = digi_k_woo_state( $sig );
				$out['visual']  = get_option( 'woocommerce_feature_wc_visual_attribute_enabled', '' );
				$out['types']   = array_keys( (array) wc_get_attribute_types() );
				$out['ids']     = array();
				foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) {
					$out['ids'][ (string) $attribute->attribute_name ] = (int) $attribute->attribute_id;
				}
				$out['variations'] = array();
				$out['by_status']  = array();
				foreach ( get_posts( array( 'post_type' => 'product_variation', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
					$variation = wc_get_product( $post->ID );
					$parent    = get_post( $post->post_parent );
					$out['by_status'][ $post->post_status ] = ( $out['by_status'][ $post->post_status ] ?? 0 ) + 1;
					$out['variations'][ Digi_K_Signature::post_key( $post ) ] = array(
						'status'     => $post->post_status,
						'parent'     => $parent ? Digi_K_Signature::post_key( $parent ) : '',
						'parent_status' => $parent ? $parent->post_status : '',
						'sku'        => $variation ? (string) $variation->get_sku( 'edit' ) : '',
						'price'      => $variation ? (string) $variation->get_regular_price( 'edit' ) : '',
						'attributes' => $variation ? (array) $variation->get_attributes( 'edit' ) : array(),
						'image'      => $variation && $variation->get_image_id( 'edit' ) ? $sig->media( $variation->get_image_id( 'edit' ) ) : '',
					);
				}
				$out['products'] = array();
				foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
					$product = wc_get_product( $post->ID );
					$out['products'][ Digi_K_Signature::post_key( $post ) ] = array(
						'status' => $post->post_status,
						'type'   => $product ? $product->get_type() : '',
						'sku'    => $product ? (string) $product->get_sku( 'edit' ) : '',
						'path'   => (string) wp_make_link_relative( (string) get_permalink( $post ) ),
						'session' => (string) get_post_meta( $post->ID, '_elementor_import_session_id', true ),
					);
				}
				break;
			case 'woo-edit':
				// R8 (F4-Woo, V.11.1 y V.11.2): lo que un administrador cambia después de importar y la reversión no pisa.
				$changed = array();
				$id      = function_exists( 'wc_attribute_taxonomy_id_by_name' ) ? (int) wc_attribute_taxonomy_id_by_name( 'talla-zapato' ) : 0;
				if ( $id ) {
					$attribute = wc_get_attribute( $id );
					$result    = wc_update_attribute( $id, array( 'name' => 'Talla editada', 'slug' => 'talla-zapato', 'type' => $attribute->type, 'order_by' => $attribute->order_by, 'has_archives' => $attribute->has_archives ) );
					$changed['attribute'] = is_wp_error( $result ) ? $result->get_error_message() : (int) $result;
				}
				$variation_id = function_exists( 'wc_get_product_id_by_sku' ) ? (int) wc_get_product_id_by_sku( 'DIGI-K-CC-ROJO' ) : 0;
				if ( $variation_id ) {
					$variation = wc_get_product( $variation_id );
					$variation->set_regular_price( '21' );
					$changed['variation'] = (int) $variation->save();
				}
				$out['changed'] = $changed;
				break;
			case 'unconverted':
				$out['count'] = (int) get_option( 'digitalisimo_elements_unconverted' );
				break;
			case 'logs':
				foreach ( array( 'net.jsonl', 'rest.jsonl' ) as $file ) {
					$out[ $file ] = (string) @file_get_contents( DIGI_K_PROBE . '/' . $file );
				}
				break;
			default:
				status_header( 400 );
				$out['error'] = 'operación desconocida';
		}
		if ( $blog && is_multisite() ) {
			restore_current_blog();
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
		exit;
	},
	PHP_INT_MAX
);
