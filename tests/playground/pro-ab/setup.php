<?php
/**
 * Segundo paso del blueprint de pro-ab.sh (el constructor ya está cargado). Por cada caso de `cases.json`:
 *
 * - página A `pro-ab-<caso>-a`: un contenedor con el widget de PRO (`widget`), sus ajustes guardados (`settings`) y,
 *   en los anidados, sus hijos (`elements`); las imágenes de prueba (`{"$image": n}`, `{"$gallery": [n, …]}`,
 *   `"$image-url:n"`) se crean una vez con GD (`digi_ab_image()`) y los menús de WordPress (`{"$menu": "nombre"}`, el
 *   slug de un menú con submenús), con `digi_ab_menu()`; una plantilla de bucle con tres entradas (`"$loop:nombre"`),
 *   con `digi_ab_loop()`, y elementos antes del widget (`before`: disparadores, con `{id}` y `"$action:…"`), con
 *   `digi_ab_before()`;
 * - página B `pro-ab-<caso>-b`: el mismo árbol convertido con `Converter::tree( $data, $log, true )` (mapas en borrador
 *   incluidos); el `id` del widget es el mismo en A y en B;
 * - las dos con la plantilla «Lienzo» (sin cabecera ni pie del tema: el foco con teclado llega antes al widget) y su CSS
 *   ya generado (`Post::create( $id )->update()`).
 *
 * Deja en `conversion.json` lo que run.cjs necesita: páginas, widget de destino y si está registrado, estado del mapa,
 * sus `drop` y `approx` (los motivos que un caso puede citar con `code`), el registro de la conversión con los códigos
 * del informe y los ajustes de B. Con `map-override.php` (variable MAP del arnés) fija ese mapa con `Registry::set()`
 * antes de convertir: sólo para depurar la mecánica sin el mapa real.
 *
 * C9: `"$template:<clave>"` en unos ajustes da el ID de una plantilla de la biblioteca (tipo `section`, publicada) creada
 * con los elementos de `templates.<clave>` del caso (el widget Plantilla). Un caso con `widget: global` y `global` (el
 * widget que guarda la plantilla: `widgetType` y `settings`) crea una plantilla de widget global: A tiene la instancia
 * (`widgetType: global`, `templateID`) y B, la instancia sustituida por su contenido (`Convert\Globals`) y convertida.
 */
require '/wordpress/wp-load.php';

const DIGI_AB_DIR = '/wordpress/wp-content/probe';

function digi_ab_fail( $message ) {
	file_put_contents( DIGI_AB_DIR . '/setup-error.txt', $message . "\n", FILE_APPEND );
}

/** ID de elemento determinista de 7 caracteres. */
function digi_ab_eid( $seed ) {
	return substr( md5( 'pro-ab-' . $seed ), 0, 7 );
}

/** Ajustes vacíos como objeto (`{}`), como los guarda el editor, a cualquier profundidad del árbol. */
function digi_ab_objects( array $elements ) {
	foreach ( $elements as $i => $element ) {
		$element['settings'] = empty( $element['settings'] ) ? (object) array() : $element['settings'];
		$element['elements'] = digi_ab_objects( isset( $element['elements'] ) && is_array( $element['elements'] ) ? $element['elements'] : array() );
		$elements[ $i ]      = $element;
	}
	return $elements;
}

/**
 * Guarda el árbol de una página (crea la página si no existe) y genera su CSS. Devuelve [ID, estado del CSS].
 * Con `$doc` (C8: `floating-buttons` o `floating-bars`, el `doc` del caso) es un documento de botones flotantes del
 * constructor (tipo de entrada `e-floating-buttons`, plantilla `floating-buttons`) que se ve en su propia dirección.
 */
function digi_ab_page( $slug, array $tree, $doc = '' ) {
	$type     = '' !== $doc ? 'e-floating-buttons' : 'page';
	$existing = get_page_by_path( $slug, OBJECT, $type );
	$id       = $existing ? (int) $existing->ID : (int) wp_insert_post( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ) );
	if ( ! $id ) {
		digi_ab_fail( "No se pudo crear la página $slug" );
		return array( 0, '' );
	}
	update_post_meta( $id, '_wp_page_template', 'elementor_canvas' );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', '' !== $doc ? 'floating-buttons' : 'wp-page' );
	if ( '' !== $doc ) {
		update_post_meta( $id, '_elementor_floating_elements_type', $doc );
	}
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( digi_ab_objects( $tree ) ) ) );
	$css = \Elementor\Core\Files\CSS\Post::create( $id );
	$css->update();
	return array( $id, (string) $css->get_meta( 'status' ) );
}

/**
 * Adjunto de prueba n (1–12): PNG de 1200 × 800 con un color y un número propios, creado una vez con GD. Devuelve
 * `[ID, URL]`.
 */
function digi_ab_image( $n ) {
	static $cache = array();
	$n = max( 1, min( 12, (int) $n ) );
	if ( isset( $cache[ $n ] ) ) {
		return $cache[ $n ];
	}
	$slug     = 'pro-ab-image-' . $n;
	$existing = get_posts( array( 'post_type' => 'attachment', 'name' => $slug, 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $existing ) {
		return $cache[ $n ] = array( (int) $existing[0], (string) wp_get_attachment_url( (int) $existing[0] ) );
	}
	$colors = array( array( 36, 94, 255 ), array( 230, 57, 70 ), array( 42, 157, 143 ), array( 233, 196, 106 ), array( 38, 70, 83 ), array( 244, 162, 97 ), array( 131, 56, 236 ), array( 6, 214, 160 ), array( 17, 138, 178 ), array( 239, 71, 111 ), array( 255, 209, 102 ), array( 7, 59, 76 ) );
	$im     = imagecreatetruecolor( 1200, 800 );
	list( $r, $g, $b ) = $colors[ $n - 1 ];
	imagefill( $im, 0, 0, imagecolorallocate( $im, $r, $g, $b ) );
	$light = imagecolorallocate( $im, 255, 255, 255 );
	imagefilledrectangle( $im, 100 + 60 * $n, 120, 500 + 60 * $n, 680, $light );
	imagefilledellipse( $im, 980, 220, 260, 260, imagecolorallocate( $im, 0, 0, 0 ) );
	for ( $i = 0; $i < $n; $i++ ) {
		imagefilledrectangle( $im, 140 + 70 * $i, 720, 190 + 70 * $i, 770, $light );
	}
	ob_start();
	imagepng( $im );
	$png = (string) ob_get_clean();
	imagedestroy( $im );
	$upload = wp_upload_bits( $slug . '.png', null, $png );
	if ( ! empty( $upload['error'] ) ) {
		digi_ab_fail( "No se pudo crear la imagen de prueba $n: {$upload['error']}" );
		return $cache[ $n ] = array( 0, '' );
	}
	$id = (int) wp_insert_attachment( array( 'post_title' => "Imagen de prueba $n", 'post_name' => $slug, 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $upload['file'] );
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
	update_post_meta( $id, '_wp_attachment_image_alt', "Texto alternativo $n" );
	return $cache[ $n ] = array( $id, (string) wp_get_attachment_url( $id ) );
}

/**
 * Menú de WordPress de prueba (`{"$menu": "nombre"}`, C3): crea una vez el menú `pro-ab-<nombre>` y devuelve su slug.
 * Estructura fija, con submenús de dos niveles, un padre sin destino («#») y un ancla: Inicio, Servicios (Diseño,
 * Desarrollo web › Tiendas en línea, Aplicaciones), Equipo («#» › Personas, Valores), Blog y Contacto (`#contacto`).
 * Los destinos son páginas del sitio que no existen: el A/B no navega.
 */
function digi_ab_menu( $name ) {
	static $cache = array();
	$name = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $name ) );
	$slug = 'pro-ab-' . ( '' === $name ? 'menu' : $name );
	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}
	$menu = wp_get_nav_menu_object( $slug );
	if ( $menu ) {
		return $cache[ $slug ] = (string) $menu->slug;
	}
	$id = wp_create_nav_menu( $slug );
	if ( is_wp_error( $id ) ) {
		digi_ab_fail( "No se pudo crear el menú de prueba $slug: " . $id->get_error_message() );
		return $cache[ $slug ] = '';
	}
	$add = static function ( $title, $url, $parent = 0 ) use ( $id ) {
		return (int) wp_update_nav_menu_item( $id, 0, array( 'menu-item-title' => $title, 'menu-item-url' => $url, 'menu-item-status' => 'publish', 'menu-item-type' => 'custom', 'menu-item-parent-id' => $parent ) );
	};
	$add( 'Inicio', home_url( '/pro-ab-inicio/' ) );
	$services = $add( 'Servicios', home_url( '/pro-ab-servicios/' ) );
	$add( 'Diseño', home_url( '/pro-ab-diseno/' ), $services );
	$dev = $add( 'Desarrollo web', home_url( '/pro-ab-desarrollo/' ), $services );
	$add( 'Tiendas en línea', home_url( '/pro-ab-tiendas/' ), $dev );
	$add( 'Aplicaciones', home_url( '/pro-ab-aplicaciones/' ), $services );
	$team = $add( 'Equipo', '#' );
	$add( 'Personas', home_url( '/pro-ab-personas/' ), $team );
	$add( 'Valores', home_url( '/pro-ab-valores/' ), $team );
	$add( 'Blog', home_url( '/pro-ab-blog/' ) );
	$add( 'Contacto', '#contacto' );
	return $cache[ $slug ] = (string) wp_get_nav_menu_object( $id )->slug;
}

/**
 * Entradas de prueba (C5): las 9 primeras se crean una vez, con fecha fija (orden estable), categorías «Noticias»,
 * «Guías» y «Casos» (una cada tres), etiquetas «par»/«impar», extracto e imagen destacada (`digi_ab_image()`). Se
 * borra «¡Hola, mundo!» para que las cuentas sean exactas. Devuelve los IDs de las `$n` primeras (`{"$posts": n}`).
 */
function digi_ab_posts( $n ) {
	static $ids = null;
	if ( null === $ids ) {
		$ids      = array();
		$existing = get_posts( array( 'post_type' => 'post', 'name' => 'entrada-de-prueba-1', 'numberposts' => 1, 'fields' => 'ids' ) );
		if ( ! $existing ) {
			$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
			if ( $hello ) {
				wp_delete_post( $hello->ID, true );
			}
			$cats = array();
			foreach ( array( 'Noticias', 'Guías', 'Casos' ) as $name ) {
				$term   = term_exists( $name, 'category' ) ?: wp_insert_term( $name, 'category' );
				$cats[] = (int) $term['term_id'];
			}
			for ( $i = 1; $i <= 9; $i++ ) {
				$id = (int) wp_insert_post(
					array(
						'post_type'     => 'post',
						'post_status'   => 'publish',
						'post_title'    => "Entrada de prueba $i",
						'post_name'     => "entrada-de-prueba-$i",
						'post_excerpt'  => "Extracto breve de la entrada de prueba $i.",
						'post_content'  => "Contenido de la entrada de prueba $i.",
						'post_date'     => sprintf( '2026-01-%02d 10:00:00', $i ),
						'post_category' => array( $cats[ ( $i - 1 ) % 3 ] ),
						'tags_input'    => array( 0 === $i % 2 ? 'par' : 'impar' ),
					)
				);
				set_post_thumbnail( $id, digi_ab_image( $i )[0] );
			}
		}
		for ( $i = 1; $i <= 9; $i++ ) {
			$post  = get_page_by_path( "entrada-de-prueba-$i", OBJECT, 'post' );
			$ids[] = $post ? (int) $post->ID : 0;
		}
	}
	return array_slice( $ids, 0, max( 0, min( 9, (int) $n ) ) );
}

/**
 * Consulta de archivo de un caso (C5, frente I: Entradas del archivo): `"archive": {variables de WP_Query}`. Las páginas
 * A y B del caso listan esa consulta por el filtro que PRO y el widget propio aplican a la consulta del archivo
 * (`elementor/theme/posts_archive/query_posts/query_vars`), con la página actual (`/N/` del singular) como `paged`.
 * Lo aplica el mu-plugin `digi-ab-archive.php`, que lee la opción `digi_ab_archive` (página → variables).
 */
function digi_ab_archive( $slug, array $vars ) {
	$map = (array) get_option( 'digi_ab_archive', array() );
	$map[ "pro-ab-$slug-a" ] = $vars;
	$map[ "pro-ab-$slug-b" ] = $vars;
	update_option( 'digi_ab_archive', $map, false );
	if ( ! is_file( WPMU_PLUGIN_DIR . '/digi-ab-archive.php' ) ) {
		file_put_contents(
			WPMU_PLUGIN_DIR . '/digi-ab-archive.php',
			"<?php\n// pro-ab.sh (setup.php, `archive` de un caso): consulta de archivo de las páginas A y B del caso.\nadd_filter( 'elementor/theme/posts_archive/query_posts/query_vars', static function ( \$vars ) {\n\t\$map  = (array) get_option( 'digi_ab_archive', array() );\n\t\$page = get_queried_object();\n\tif ( ! \$page || empty( \$page->post_name ) || ! isset( \$map[ \$page->post_name ] ) ) {\n\t\treturn \$vars;\n\t}\n\treturn (array) \$map[ \$page->post_name ] + array( 'paged' => max( 1, (int) get_query_var( 'page' ) ) );\n} );\n"
		);
	}
}

/** Etiqueta dinámica guardada como la guarda el editor. */
function digi_ab_tag( $name, array $settings = array() ) {
	return '[elementor-tag id="' . substr( md5( $name . wp_json_encode( $settings ) ), 0, 7 ) . '" name="' . $name . '" settings="' . rawurlencode( wp_json_encode( (object) $settings ) ) . '"]';
}

/**
 * Plantilla de Elemento de bucle de prueba (C5, frente J), creada una vez por nombre y publicada; devuelve su ID como
 * cadena (así la guarda el control de plantilla). `{"$loop_item": "card"}`: contenedor con la imagen destacada, el
 * título enlazado y el extracto (widgets del núcleo con etiquetas dinámicas, sin widgets de PRO: A y B pintan la misma
 * plantilla). `"alt"`: la misma tarjeta con otro fondo (plantillas alternas). `"title"`: sólo el título enlazado
 * (carruseles compactos). `"term"`: el nombre del término (etiqueta «Título del archivo», que lee el término del bucle).
 */
function digi_ab_loop_item( $preset ) {
	static $cache = array();
	$preset = in_array( $preset, array( 'card', 'alt', 'title', 'term' ), true ) ? $preset : 'card';
	if ( isset( $cache[ $preset ] ) ) {
		return $cache[ $preset ];
	}
	$slug     = 'pro-ab-loop-' . $preset;
	$existing = get_posts( array( 'post_type' => 'elementor_library', 'name' => $slug, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $existing ) {
		return $cache[ $preset ] = (string) $existing[0];
	}
	$title   = array(
		'id'         => digi_ab_eid( $slug . '-title' ),
		'elType'     => 'widget',
		'widgetType' => 'heading',
		'settings'   => array(
			'title'       => 'Título',
			'header_size' => 'h3',
			'title_color' => '#1f2124',
			'__dynamic__' => array(
				'title' => digi_ab_tag( 'term' === $preset ? 'archive-title' : 'post-title', 'term' === $preset ? array( 'include_context' => '' ) : array() ),
				'link'  => digi_ab_tag( 'post-url' ),
			),
		),
		'elements'   => array(),
	);
	if ( 'term' === $preset ) {
		unset( $title['settings']['__dynamic__']['link'] );
	}
	$widgets = array( $title );
	if ( ! in_array( $preset, array( 'title', 'term' ), true ) ) {
		array_unshift(
			$widgets,
			array(
				'id'         => digi_ab_eid( $slug . '-image' ),
				'elType'     => 'widget',
				'widgetType' => 'image',
				'settings'   => array(
					'image_size'  => 'medium',
					'__dynamic__' => array( 'image' => digi_ab_tag( 'post-featured-image' ) ),
				),
				'elements'   => array(),
			)
		);
		$widgets[] = array(
			'id'         => digi_ab_eid( $slug . '-excerpt' ),
			'elType'     => 'widget',
			'widgetType' => 'text-editor',
			'settings'   => array(
				'editor'      => 'Extracto',
				'__dynamic__' => array( 'editor' => digi_ab_tag( 'post-excerpt', array( 'max_length' => '' ) ) ),
			),
			'elements'   => array(),
		);
	}
	$tree = array(
		array(
			'id'       => digi_ab_eid( $slug . '-box' ),
			'elType'   => 'container',
			'isInner'  => false,
			'settings' => array(
				'flex_direction'        => 'column',
				'background_background' => 'classic',
				'background_color'      => 'alt' === $preset ? '#fdebd0' : '#f1f3f5',
				'padding'               => array( 'unit' => 'px', 'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'isLinked' => true ),
				'flex_gap'              => array( 'unit' => 'px', 'size' => 10, 'column' => '10', 'row' => '10' ),
			),
			'elements' => $widgets,
		),
	);
	$id = (int) wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Elemento de bucle de prueba ' . $preset, 'post_name' => $slug ) );
	if ( ! $id ) {
		digi_ab_fail( "No se pudo crear la plantilla de bucle $preset" );
		return $cache[ $preset ] = '';
	}
	wp_set_object_terms( $id, 'loop-item', 'elementor_library_type' );
	update_post_meta( $id, '_elementor_template_type', 'loop-item' );
	update_post_meta( $id, '_elementor_source', 'post' );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( digi_ab_objects( $tree ) ) ) );
	return $cache[ $preset ] = (string) $id;
}

/**
 * Animación Lottie de prueba (frente C de C2): JSON de 400 × 300, 50 fotogramas a 25 por segundo, con un rectángulo azul
 * que cruza de izquierda a derecha y un círculo rojo que crece; las dos formas se ven desde el primer fotograma. Se
 * guarda una vez como adjunto `application/json` (sin pasar por la lista de tipos de subida). Devuelve `[ID, URL]`.
 */
function digi_ab_lottie() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$slug     = 'pro-ab-lottie';
	$existing = get_posts( array( 'post_type' => 'attachment', 'name' => $slug, 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $existing ) {
		return $cache = array( (int) $existing[0], (string) wp_get_attachment_url( (int) $existing[0] ) );
	}
	$tr    = array( 'ty' => 'tr', 'p' => array( 'a' => 0, 'k' => array( 0, 0 ) ), 'a' => array( 'a' => 0, 'k' => array( 0, 0 ) ), 's' => array( 'a' => 0, 'k' => array( 100, 100 ) ), 'r' => array( 'a' => 0, 'k' => 0 ), 'o' => array( 'a' => 0, 'k' => 100 ), 'sk' => array( 'a' => 0, 'k' => 0 ), 'sa' => array( 'a' => 0, 'k' => 0 ) );
	$ease  = static function ( $from, $to, $t0, $t1 ) {
		$n = count( $from );
		return array( 'a' => 1, 'k' => array( array( 'i' => array( 'x' => array_fill( 0, $n, 0.5 ), 'y' => array_fill( 0, $n, 0.5 ) ), 'o' => array( 'x' => array_fill( 0, $n, 0.5 ), 'y' => array_fill( 0, $n, 0.5 ) ), 't' => $t0, 's' => $from, 'e' => $to ), array( 't' => $t1 ) ) );
	};
	$layer = static function ( $ind, $name, $shape, $color, array $ks ) use ( $tr ) {
		return array(
			'ddd' => 0, 'ind' => $ind, 'ty' => 4, 'nm' => $name, 'sr' => 1, 'ao' => 0, 'ip' => 0, 'op' => 50, 'st' => 0, 'bm' => 0,
			'ks'     => $ks + array( 'o' => array( 'a' => 0, 'k' => 100 ), 'r' => array( 'a' => 0, 'k' => 0 ), 'a' => array( 'a' => 0, 'k' => array( 0, 0, 0 ) ), 's' => array( 'a' => 0, 'k' => array( 100, 100, 100 ) ) ),
			'shapes' => array( array( 'ty' => 'gr', 'nm' => $name, 'it' => array( $shape, array( 'ty' => 'fl', 'c' => array( 'a' => 0, 'k' => $color ), 'o' => array( 'a' => 0, 'k' => 100 ), 'r' => 1 ), $tr ) ) ),
		);
	};
	$json = wp_json_encode(
		array(
			'v' => '5.5.7', 'fr' => 25, 'ip' => 0, 'op' => 50, 'w' => 400, 'h' => 300, 'nm' => 'pro-ab', 'ddd' => 0, 'assets' => array(),
			'layers' => array(
				$layer( 1, 'rect', array( 'ty' => 'rc', 'd' => 1, 's' => array( 'a' => 0, 'k' => array( 120, 120 ) ), 'p' => array( 'a' => 0, 'k' => array( 0, 0 ) ), 'r' => array( 'a' => 0, 'k' => 16 ) ), array( 0.141, 0.369, 1, 1 ), array( 'p' => $ease( array( 90, 150, 0 ), array( 310, 150, 0 ), 0, 50 ) ) ),
				$layer( 2, 'dot', array( 'ty' => 'el', 'd' => 1, 's' => array( 'a' => 0, 'k' => array( 240, 240 ) ), 'p' => array( 'a' => 0, 'k' => array( 0, 0 ) ) ), array( 0.902, 0.224, 0.275, 1 ), array( 'p' => array( 'a' => 0, 'k' => array( 200, 150, 0 ) ), 's' => $ease( array( 30, 30, 100 ), array( 100, 100, 100 ), 0, 50 ) ) ),
			),
		)
	);
	$upload = wp_upload_bits( $slug . '.json', null, (string) $json );
	if ( ! empty( $upload['error'] ) ) {
		// `wp_upload_bits()` respeta la lista de tipos de subida: sin JSON en ella, se escribe a mano en la misma carpeta.
		$dir    = wp_upload_dir();
		$file   = trailingslashit( $dir['path'] ) . $slug . '.json';
		$upload = false !== file_put_contents( $file, (string) $json ) ? array( 'file' => $file, 'error' => false ) : $upload;
	}
	if ( ! empty( $upload['error'] ) ) {
		digi_ab_fail( "No se pudo crear la animación Lottie de prueba: {$upload['error']}" );
		return $cache = array( 0, '' );
	}
	$id = (int) wp_insert_attachment( array( 'post_title' => 'Animación de prueba', 'post_excerpt' => 'Leyenda de la animación', 'post_name' => $slug, 'post_mime_type' => 'application/json', 'post_status' => 'inherit' ), $upload['file'] );
	return $cache = array( $id, (string) wp_get_attachment_url( $id ) );
}

/**
 * Sustituye en unos ajustes las imágenes de prueba: `{"$image": n}`, `{"$gallery": [n, …]}` y `"$image-url:n"`; la
 * animación Lottie de prueba: `{"$lottie": 1}` (adjunto) y `"$lottie-url"` (su URL); las entradas de prueba
 * (`{"$posts": n}`, ver `digi_ab_posts()`) y la plantilla de Elemento de bucle (`{"$loop_item": "card"}`, ver
 * `digi_ab_loop_item()`).
 */
function digi_ab_tokens( $value ) {
	if ( is_string( $value ) && preg_match( '/^\$loop:([a-z0-9-]+)$/', $value, $m ) ) {
		return (string) digi_ab_loop( $m[1] );
	}
	if ( is_string( $value ) && preg_match( '/^\$template:([a-z0-9-]+)$/', $value, $m ) ) {
		return (string) digi_ab_template( $m[1], (array) ( $GLOBALS['digi_ab_templates'][ $m[1] ] ?? array() ) );
	}
	if ( is_string( $value ) && preg_match( '/^\$image-url:(\d+)$/', $value, $m ) ) {
		return digi_ab_image( (int) $m[1] )[1];
	}
	// `"$fixture:clave"`: el ID (texto) de un dato de prueba del contexto (`digi_ab_fixtures()`: post, previous, next, bare,
	// page, author, category, tag…), p. ej. la entrada elegida de un widget con origen «Personalizado» (frente H de C4).
	if ( is_string( $value ) && preg_match( '/^\$fixture:([a-z]+)$/', $value, $m ) ) {
		return (string) ( digi_ab_fixtures()[ $m[1] ] ?? '' );
	}
	// `"$woo:clave"`: el ID (texto) de un dato de prueba de WooCommerce (`digi_ab_woo_fixtures()`: simple, variable,
	// sale, outofstock, reviews, category, subcategory, other_category, tag, customer, order…). C7.
	if ( is_string( $value ) && preg_match( '/^\$woo:([a-z_]+)$/', $value, $m ) ) {
		return (string) ( digi_ab_woo_fixtures()[ $m[1] ] ?? '' );
	}
	if ( '$lottie-url' === $value ) {
		return digi_ab_lottie()[1];
	}
	if ( ! is_array( $value ) ) {
		return $value;
	}
	// `{"$woo-ids": ["simple", "sale"]}`: la lista de IDs (texto) de esos datos de prueba (control de consulta múltiple).
	if ( array( '$woo-ids' ) === array_keys( $value ) ) {
		$ids = digi_ab_woo_fixtures();
		return array_values( array_map( static function ( $key ) use ( $ids ) {
			return (string) ( $ids[ (string) $key ] ?? '' );
		}, (array) $value['$woo-ids'] ) );
	}
	if ( array( '$posts' ) === array_keys( $value ) ) {
		return array_map( 'strval', digi_ab_posts( (int) $value['$posts'] ) );
	}
	if ( array( '$loop_item' ) === array_keys( $value ) ) {
		return digi_ab_loop_item( (string) $value['$loop_item'] );
	}
	if ( array( '$lottie' ) === array_keys( $value ) ) {
		list( $id, $url ) = digi_ab_lottie();
		return array( 'id' => $id, 'url' => $url, 'alt' => '', 'source' => 'library', 'size' => '' );
	}
	if ( array( '$image' ) === array_keys( $value ) ) {
		list( $id, $url ) = digi_ab_image( (int) $value['$image'] );
		return array( 'id' => $id, 'url' => $url, 'alt' => '', 'source' => 'library', 'size' => '' );
	}
	if ( array( '$menu' ) === array_keys( $value ) ) {
		return digi_ab_menu( (string) $value['$menu'] );
	}
	if ( array( '$gallery' ) === array_keys( $value ) ) {
		$out = array();
		foreach ( (array) $value['$gallery'] as $n ) {
			list( $id, $url ) = digi_ab_image( (int) $n );
			$out[] = array( 'id' => $id, 'url' => $url );
		}
		return $out;
	}
	foreach ( $value as $key => $item ) {
		$value[ $key ] = digi_ab_tokens( $item );
	}
	return $value;
}

/**
 * Plantilla de la biblioteca `pro-ab-tpl-<clave>` (C9): publicada, con esos elementos (IDs deterministas e imágenes de
 * prueba) y su CSS. `$type` es su tipo (`section`, o `widget` para un widget global). Devuelve su ID.
 */
function digi_ab_template( $key, array $elements, $type = 'section' ) {
	$slug     = 'pro-ab-tpl-' . $key;
	$existing = get_page_by_path( $slug, OBJECT, 'elementor_library' );
	$id       = $existing ? (int) $existing->ID : (int) wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ) );
	if ( ! $id ) {
		digi_ab_fail( "No se pudo crear la plantilla $slug" );
		return 0;
	}
	$elements = digi_ab_children( $elements, 'tpl-' . $key );
	foreach ( $elements as $i => $element ) {
		$elements[ $i ]['isInner'] = false;
	}
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', $type );
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	if ( 'widget' === $type && isset( $elements[0]['widgetType'] ) ) {
		update_post_meta( $id, '_elementor_template_widget_type', (string) $elements[0]['widgetType'] );
	}
	if ( function_exists( 'wp_set_object_terms' ) ) {
		wp_set_object_terms( $id, $type, 'elementor_library_type' );
	}
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( digi_ab_objects( $elements ) ) ) );
	\Elementor\Core\Files\CSS\Post::create( $id )->update();
	return $id;
}

/** Hijos de un widget anidado (`elements` del caso), con IDs deterministas donde falten y las imágenes de prueba. */
function digi_ab_children( array $elements, $seed ) {
	foreach ( $elements as $i => $element ) {
		$element['id']       = isset( $element['id'] ) ? (string) $element['id'] : digi_ab_eid( $seed . '-' . $i );
		$element['isInner']  = $element['isInner'] ?? true;
		$element['settings'] = digi_ab_tokens( (array) ( $element['settings'] ?? array() ) );
		$element['elements'] = digi_ab_children( (array) ( $element['elements'] ?? array() ), $seed . '-' . $i );
		$elements[ $i ]      = $element;
	}
	return $elements;
}

/**
 * Plantilla de bucle de prueba (`"$loop:nombre"`, C3 · frente F: resultados en vivo de la Búsqueda): crea una vez tres
 * entradas publicadas («Mesa azul», «Mesa roja», «Mesa verde») y la plantilla `pro-ab-loop-<nombre>` (tipo `loop-item`)
 * con un encabezado que enlaza la entrada con su título (etiquetas dinámicas del núcleo de funciones avanzadas), y
 * devuelve su ID.
 */
function digi_ab_loop( $name ) {
	static $cache = array();
	$slug = 'pro-ab-loop-' . preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $name ) );
	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}
	foreach ( array( 'azul', 'roja', 'verde' ) as $color ) {
		if ( ! get_page_by_path( 'mesa-' . $color, OBJECT, 'post' ) ) {
			wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Mesa ' . $color, 'post_name' => 'mesa-' . $color, 'post_content' => 'Una mesa ' . $color . '.' ) );
		}
	}
	$existing = get_page_by_path( $slug, OBJECT, 'elementor_library' );
	$id       = $existing ? (int) $existing->ID : (int) wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ) );
	if ( ! $id ) {
		digi_ab_fail( "No se pudo crear la plantilla de bucle $slug" );
		return $cache[ $slug ] = 0;
	}
	$data = array(
		array(
			'id'       => digi_ab_eid( $slug . '-c' ),
			'elType'   => 'container',
			'isInner'  => false,
			'settings' => (object) array( 'content_width' => 'full', 'padding' => array( 'unit' => 'px', 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'isLinked' => true ) ),
			'elements' => array(
				array(
					'id'         => digi_ab_eid( $slug . '-h' ),
					'elType'     => 'widget',
					'widgetType' => 'heading',
					'settings'   => array(
						'title'       => 'Título',
						'header_size' => 'h3',
						'__dynamic__' => array(
							'title' => '[elementor-tag id="' . digi_ab_eid( $slug . '-t' ) . '" name="post-title" settings="%7B%7D"]',
							'link'  => '[elementor-tag id="' . digi_ab_eid( $slug . '-u' ) . '" name="post-url" settings="%7B%7D"]',
						),
					),
					'elements'   => array(),
				),
			),
		),
	);
	update_post_meta( $id, '_elementor_template_type', 'loop-item' );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	wp_set_object_terms( $id, 'loop-item', 'elementor_library_type' );
	\Elementor\Core\Files\CSS\Post::create( $id )->update();
	return $cache[ $slug ] = $id;
}

/**
 * Elementos antes del widget en el contenedor (`before` del caso, C3 · frente F: los disparadores de un panel). En sus
 * cadenas, `{id}` es el `id` del widget del caso y `"$action:<acción>"` el enlace de acción del núcleo
 * (`#elementor-action:action=<acción>&settings=<base64 de {id, displayMode}>`, codificado tras la almohadilla como lo
 * imprime el núcleo: `esc_url()` vacía la forma sin codificar) que lo nombra.
 */
function digi_ab_before( array $elements, $slug, $id ) {
	$place = static function ( $value ) use ( &$place, $id ) {
		if ( is_string( $value ) && preg_match( '/^\$action:([a-z_]+):([a-z]+)$/', $value, $m ) ) {
			return '#elementor-action' . rawurlencode( ':action=' . $m[1] . ':' . $m[2] . '&settings=' . base64_encode( wp_json_encode( array( 'id' => $id, 'displayMode' => $m[2] ) ) ) );
		}
		if ( '$self' === $value ) {
			return $id; // C5: el ID del widget medido (un filtro que apunta al bucle del caso), como en `digi_ab_siblings()`.
		}
		if ( is_string( $value ) ) {
			return str_replace( '{id}', $id, $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $place( $item );
			}
		}
		return $value;
	};
	// Los mismos IDs, `isInner` y fichas de prueba que `digi_ab_children()` (los `before` de C2 pasan por aquí), y además
	// `{id}` y `"$action:…"` a cualquier profundidad.
	$fill = static function ( array $elements ) use ( &$fill, $place ) {
		foreach ( $elements as $i => $element ) {
			$element['settings'] = $place( $element['settings'] );
			$element['elements'] = $fill( $element['elements'] );
			$elements[ $i ]      = $element;
		}
		return $elements;
	};
	$elements = $fill( digi_ab_children( $elements, $slug . '-before' ) );
	foreach ( $elements as $i => $element ) {
		$elements[ $i ]['elType'] = $element['elType'] ?? 'widget';
	}
	return $elements;
}

/**
 * Hermanos del widget del caso en su contenedor (`before` y `after` del caso, C2 y C5): elementos completos (`widgetType`,
 * `settings`…) con IDs deterministas donde falten, las imágenes y plantillas de prueba y la cadena `"$self"` cambiada
 * por el ID del widget del caso (un filtro que apunta al bucle medido). Se convierten en B como el resto del árbol.
 */
function digi_ab_siblings( array $elements, $seed, $self ) {
	$swap = static function ( $value ) use ( &$swap, $self ) {
		if ( '$self' === $value ) {
			return $self;
		}
		return is_array( $value ) ? array_map( $swap, $value ) : $value;
	};
	foreach ( $elements as $i => $element ) {
		$element['id']       = isset( $element['id'] ) ? (string) $element['id'] : digi_ab_eid( $seed . '-s' . $i );
		$element['elType']   = $element['elType'] ?? 'widget';
		$element['isInner']  = $element['isInner'] ?? false;
		$element['settings'] = $swap( digi_ab_tokens( (array) ( $element['settings'] ?? array() ) ) );
		$element['elements'] = digi_ab_children( (array) ( $element['elements'] ?? array() ), $seed . '-s' . $i );
		$elements[ $i ]      = $element;
	}
	return $elements;
}

/** El elemento con ese id en un árbol (el widget del caso, entre sus hermanos `before`/`after`). */
function digi_ab_find( array $elements, $id ) {
	foreach ( $elements as $element ) {
		if ( isset( $element['id'] ) && $id === $element['id'] ) {
			return $element;
		}
		$found = digi_ab_find( (array) ( $element['elements'] ?? array() ), $id );
		if ( null !== $found ) {
			return $found;
		}
	}
	return null;
}

/** `drop` y `approx` de un mapa (y de sus repetidores, con `repetidor.clave`): motivos que un caso puede citar. */
function digi_ab_reasons( array $map ) {
	$out = array( 'drop' => array(), 'approx' => array() );
	foreach ( array( 'drop', 'approx' ) as $kind ) {
		foreach ( (array) ( $map[ $kind ] ?? array() ) as $key => $reason ) {
			$out[ $kind ][ (string) $key ] = (string) $reason;
		}
		foreach ( (array) ( $map['repeaters'] ?? array() ) as $repeater => $spec ) {
			foreach ( (array) ( $spec[ $kind ] ?? array() ) as $key => $reason ) {
				$out[ $kind ][ $repeater . '.' . $key ] = (string) $reason;
			}
		}
	}
	return $out;
}

/**
 * Contexto de los widgets de tema (C4, `docs/pro/c4.md` §3): datos de prueba fijos, creados una vez y sólo si algún
 * caso de la corrida trae `context`. Una autora con biografía, una categoría y una etiqueta con descripción, la entrada
 * completa «Entrada de prueba del A/B» (`entrada-ab`: extracto escrito, contenido con bloques, imagen destacada con
 * leyenda, categoría, etiqueta, comentarios abiertos con dos comentarios aprobados y una respuesta) entre una anterior
 * (`entrada-anterior-ab`) y una siguiente (`entrada-siguiente-ab`), una entrada sin extras (`entrada-sin-extras-ab`:
 * sin extracto, imagen, etiqueta ni comentarios) y una página (`pagina-ab`). Fechas fijas de marzo de 2024, así la
 * anterior y la siguiente no dependen del momento de la corrida. El logotipo del Personalizador es la imagen de prueba 12
 * y la imagen destacada, la 11 (con leyenda); la 10 es el avatar de todos los usuarios en estas páginas. Devuelve los ID.
 */
function digi_ab_fixtures() {
	static $ids = null;
	if ( null !== $ids ) {
		return $ids;
	}
	$ids  = array();
	$user = get_user_by( 'login', 'autora-ab' );
	$uid  = $user ? (int) $user->ID : (int) wp_insert_user( array( 'user_login' => 'autora-ab', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'autora-ab@example.org', 'display_name' => 'Ana Autora', 'first_name' => 'Ana', 'last_name' => 'Autora', 'nickname' => 'Ana Autora', 'description' => 'Biografía de la autora de prueba: escribe sobre diseño y accesibilidad.', 'user_url' => home_url( '/sobre-ana/' ), 'role' => 'author' ) );
	$ids['author'] = $uid;
	$term = static function ( $name, $slug, $taxonomy, $description ) {
		$found = get_term_by( 'slug', $slug, $taxonomy );
		if ( $found ) {
			return (int) $found->term_id;
		}
		$made = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug, 'description' => $description ) );
		return is_wp_error( $made ) ? 0 : (int) $made['term_id'];
	};
	$ids['category'] = $term( 'Categoría de prueba', 'categoria-ab', 'category', 'Descripción de la categoría de prueba.' );
	$ids['tag']      = $term( 'Etiqueta de prueba', 'etiqueta-ab', 'post_tag', 'Descripción de la etiqueta de prueba.' );
	$post = static function ( $slug, array $data ) use ( $uid ) {
		$found = get_page_by_path( $slug, OBJECT, $data['post_type'] ?? 'post' );
		if ( $found ) {
			return (int) $found->ID;
		}
		return (int) wp_insert_post( array_merge( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_name' => $slug, 'post_author' => $uid ), $data ) );
	};
	$content = "<!-- wp:paragraph -->\n<p>Primer párrafo de la entrada de prueba, con un <a href=\"" . esc_url( home_url( '/pagina-ab/' ) ) . "\">enlace interno</a> y texto en <strong>negrita</strong>.</p>\n<!-- /wp:paragraph -->\n\n"
		. "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Un subtítulo del contenido</h2>\n<!-- /wp:heading -->\n\n"
		. "<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item -->\n<li>Primer punto de la lista</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>Segundo punto de la lista</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->\n\n"
		. "<!-- wp:paragraph -->\n<p>Último párrafo de la entrada de prueba.</p>\n<!-- /wp:paragraph -->";
	$ids['previous'] = $post( 'entrada-anterior-ab', array( 'post_title' => 'Entrada anterior de prueba', 'post_content' => '<p>Contenido de la entrada anterior.</p>', 'post_date' => '2024-03-10 10:00:00', 'post_category' => array( $ids['category'] ) ) );
	$ids['post']     = $post( 'entrada-ab', array( 'post_title' => 'Entrada de prueba del A/B', 'post_excerpt' => 'Extracto escrito a mano de la entrada de prueba.', 'post_content' => $content, 'post_date' => '2024-03-15 10:00:00', 'post_category' => array( $ids['category'] ), 'tags_input' => array( 'etiqueta-ab' ), 'comment_status' => 'open' ) );
	$ids['next']     = $post( 'entrada-siguiente-ab', array( 'post_title' => 'Entrada siguiente de prueba', 'post_content' => '<p>Contenido de la entrada siguiente.</p>', 'post_date' => '2024-03-20 10:00:00', 'post_category' => array( $ids['category'] ) ) );
	$ids['bare']     = $post( 'entrada-sin-extras-ab', array( 'post_title' => 'Entrada sin extras', 'post_content' => "<!-- wp:paragraph -->\n<p>Contenido de una entrada sin extracto escrito ni imagen destacada: el extracto sale de este texto, que es lo bastante largo para que WordPress lo recorte en cincuenta y cinco palabras y le añada su marca de continuación al final, igual en los dos lados de la comparación, sin depender de nada más que del contenido de la entrada, que sigue un poco más para pasar el límite de palabras del extracto automático de WordPress.</p>\n<!-- /wp:paragraph -->", 'post_date' => '2024-03-05 10:00:00', 'comment_status' => 'closed' ) );
	$ids['page']     = $post( 'pagina-ab', array( 'post_type' => 'page', 'post_title' => 'Página de prueba', 'post_content' => '<p>Contenido de la página de prueba.</p>' ) );
	if ( ! get_post_thumbnail_id( $ids['post'] ) ) {
		$image = digi_ab_image( 11 );
		wp_update_post( array( 'ID' => $image[0], 'post_excerpt' => 'Leyenda de la imagen destacada' ) );
		set_post_thumbnail( $ids['post'], $image[0] );
	}
	// Logotipo del sitio (Personalizador): la imagen de prueba 12. La 10 es el avatar de todos los usuarios.
	if ( ! get_theme_mod( 'custom_logo' ) ) {
		set_theme_mod( 'custom_logo', digi_ab_image( 12 )[0] );
	}
	$ids['logo'] = (int) get_theme_mod( 'custom_logo' );
	if ( ! get_comments( array( 'post_id' => $ids['post'], 'count' => true ) ) ) {
		$first = (int) wp_insert_comment( array( 'comment_post_ID' => $ids['post'], 'comment_author' => 'Lectora de prueba', 'comment_author_email' => 'lectora-ab@example.org', 'comment_content' => 'Primer comentario de prueba.', 'comment_date' => '2024-03-16 09:00:00', 'comment_approved' => 1 ) );
		wp_insert_comment( array( 'comment_post_ID' => $ids['post'], 'comment_author' => 'Ana Autora', 'comment_author_email' => 'autora-ab@example.org', 'user_id' => $uid, 'comment_parent' => $first, 'comment_content' => 'Respuesta de la autora al primer comentario.', 'comment_date' => '2024-03-16 11:00:00', 'comment_approved' => 1 ) );
		wp_insert_comment( array( 'comment_post_ID' => $ids['post'], 'comment_author' => 'Lector de prueba', 'comment_author_email' => 'lector-ab@example.org', 'comment_content' => 'Segundo comentario de prueba.', 'comment_date' => '2024-03-17 09:00:00', 'comment_approved' => 1 ) );
	}
	return $ids;
}

/** ¿Hay WooCommerce en esta corrida? (pro-ab.sh lo monta y activate.php lo activa si algún caso lo pide). */
function digi_ab_woo_active() {
	return function_exists( 'WC' ) && class_exists( 'WC_Product_Simple' );
}

/**
 * Tienda de prueba de WooCommerce (C7, `docs/pro/c7.md` «A/B con WooCommerce»): datos fijos, creados una vez y sólo si
 * algún caso de la corrida trae `woo` o un contexto de tienda. Tienda en España y euros, sin impuestos, «Tarifa plana»
 * de 5 € en la zona «Resto del mundo» y «Contra reembolso» como único pago (fuera de línea, sin pasarela ni red).
 *
 * - Categorías de producto con descripción e imagen: `categoria-producto-ab` (imagen 8), su hija
 *   `subcategoria-producto-ab` y `otra-categoria-ab` (imagen 9); etiqueta `etiqueta-producto-ab`.
 * - Productos (fechas fijas de febrero de 2024; «los más recientes» salen en este orden):
 *   `simple` «Producto simple de prueba» (25 €, SKU, existencias 12, peso y medidas, descripción y extracto, imagen 1 y
 *   galería 2 y 3, ventas dirigidas `sale` y `reviews`, ventas cruzadas `reviews`, categoría y etiqueta);
 *   `variable` «Producto variable de prueba» (atributo propio «Talla» S/M/L, 20–22 €, imagen 7, categoría y etiqueta);
 *   `sale` «Producto en oferta de prueba» (40 € → 30 €, imagen 4); `outofstock` «Producto agotado de prueba» (15 €,
 *   sin existencias, imagen 5); `reviews` «Producto con reseñas de prueba» (50 €, imagen 6, tres reseñas de 5, 4 y 4
 *   estrellas, valoración 4,33).
 * - Clienta `cliente-ab` («Carla Cliente», rol `customer`, direcciones de facturación y envío) y su pedido `order`
 *   (procesando, 18-03-2024: 2 × simple, 1 × oferta, tarifa plana, contra reembolso).
 * - Carrito de prueba (`"cart": "items"`, lo llena el mu-plugin en cada petición): 2 × simple, 1 × variable (M) y
 *   1 × oferta. Los productos relacionados no se barajan en las páginas del A/B (igual en A y en B).
 *
 * Devuelve los ID (`simple`, `variable`, `variation_m`, `sale`, `outofstock`, `reviews`, `category`, `subcategory`,
 * `other_category`, `tag`, `customer`, `order`, `order_key`) o un arreglo vacío sin WooCommerce.
 */
function digi_ab_woo_fixtures() {
	static $ids = null;
	if ( null !== $ids ) {
		return $ids;
	}
	$ids = array();
	if ( ! digi_ab_woo_active() ) {
		digi_ab_fail( 'Un caso pide WooCommerce y no está cargado (pro-ab.sh lo monta con WOO=1 o con «woo» en el caso)' );
		return $ids;
	}
	$saved = get_option( 'digi_ab_woo_fixtures' );
	if ( is_array( $saved ) && ! empty( $saved['simple'] ) && wc_get_product( (int) $saved['simple'] ) ) {
		return $ids = $saved;
	}
	foreach ( array(
		'woocommerce_coming_soon'                    => 'no',
		'woocommerce_store_pages_only'               => 'no',
		'woocommerce_default_country'                => 'ES:M',
		'woocommerce_store_address'                  => 'Calle de Prueba 1',
		'woocommerce_store_city'                     => 'Madrid',
		'woocommerce_store_postcode'                 => '28001',
		'woocommerce_currency'                       => 'EUR',
		'woocommerce_currency_pos'                   => 'right_space',
		'woocommerce_price_thousand_sep'             => '.',
		'woocommerce_price_decimal_sep'              => ',',
		'woocommerce_price_num_decimals'             => '2',
		'woocommerce_calc_taxes'                     => 'no',
		'woocommerce_enable_coupons'                 => 'yes',
		'woocommerce_enable_guest_checkout'          => 'yes',
		'woocommerce_enable_reviews'                 => 'yes',
		'woocommerce_enable_review_rating'           => 'yes',
		'woocommerce_review_rating_verification_required' => 'no',
		'woocommerce_default_customer_address'       => 'base',
		'woocommerce_allowed_countries'              => 'all',
		'woocommerce_shipping_cost_requires_address' => 'no',
		'woocommerce_cart_redirect_after_add'        => 'no',
		'woocommerce_enable_ajax_add_to_cart'        => 'yes',
		'woocommerce_hide_out_of_stock_items'        => 'no',
		'woocommerce_manage_stock'                   => 'yes',
		// Sin reserva de existencias: su consulta (`NOW() + INTERVAL`) no funciona sobre la base SQLite de Playground.
		'woocommerce_hold_stock_minutes'             => '',
		'woocommerce_allow_tracking'                 => 'no',
		'woocommerce_show_marketplace_suggestions'   => 'no',
		'woocommerce_newly_installed'                => 'no',
		'woocommerce_onboarding_profile'             => array( 'skipped' => true ),
	) as $name => $value ) {
		update_option( $name, $value );
	}
	update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Contra reembolso', 'description' => 'Pago al recibir el pedido (prueba).', 'instructions' => 'Pago al recibir el pedido.', 'enable_for_methods' => array(), 'enable_for_virtual' => 'yes' ) );
	update_option( 'woocommerce_gateway_order', array( 'cod' => 0 ) );
	// «Tarifa plana» de 5 € en la zona «Resto del mundo» (la 0): vale para cualquier dirección.
	$zone = new WC_Shipping_Zone( 0 );
	if ( ! $zone->get_shipping_methods() ) {
		$instance = (int) $zone->add_shipping_method( 'flat_rate' );
		update_option( 'woocommerce_flat_rate_' . $instance . '_settings', array( 'title' => 'Tarifa plana', 'tax_status' => 'none', 'cost' => '5' ) );
	}

	$term = static function ( $name, $slug, $taxonomy, $description, $parent = 0 ) {
		$found = get_term_by( 'slug', $slug, $taxonomy );
		if ( $found ) {
			return (int) $found->term_id;
		}
		$made = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug, 'description' => $description, 'parent' => $parent ) );
		if ( is_wp_error( $made ) ) {
			digi_ab_fail( "WooCommerce: no se pudo crear el término $slug: " . $made->get_error_message() );
			return 0;
		}
		return (int) $made['term_id'];
	};
	$ids['category']       = $term( 'Categoría de productos de prueba', 'categoria-producto-ab', 'product_cat', 'Descripción de la categoría de productos de prueba.' );
	$ids['subcategory']    = $term( 'Subcategoría de prueba', 'subcategoria-producto-ab', 'product_cat', 'Descripción de la subcategoría de prueba.', $ids['category'] );
	$ids['other_category'] = $term( 'Otra categoría de prueba', 'otra-categoria-ab', 'product_cat', 'Descripción de otra categoría de prueba.' );
	$ids['tag']            = $term( 'Etiqueta de productos de prueba', 'etiqueta-producto-ab', 'product_tag', 'Descripción de la etiqueta de productos de prueba.' );
	update_term_meta( $ids['category'], 'thumbnail_id', digi_ab_image( 8 )[0] );
	update_term_meta( $ids['other_category'], 'thumbnail_id', digi_ab_image( 9 )[0] );

	$product = static function ( $product, $slug, $name, $date, array $cats, $image ) {
		$product->set_name( $name );
		$product->set_slug( $slug );
		$product->set_status( 'publish' );
		$product->set_category_ids( $cats );
		$product->set_date_created( $date );
		$product->set_short_description( 'Extracto de ' . lcfirst( $name ) . '.' );
		$product->set_description( '<p>Descripción completa de ' . lcfirst( $name ) . ', con un párrafo de texto.</p>' );
		$product->set_image_id( digi_ab_image( $image )[0] );
		return $product;
	};
	$simple = $product( new WC_Product_Simple(), 'producto-simple-ab', 'Producto simple de prueba', '2024-02-10 10:00:00', array( $ids['category'] ), 1 );
	$simple->set_regular_price( '25' );
	$simple->set_sku( 'AB-SIMPLE' );
	$simple->set_manage_stock( true );
	$simple->set_stock_quantity( 12 );
	$simple->set_weight( '1.5' );
	$simple->set_length( '10' );
	$simple->set_width( '20' );
	$simple->set_height( '30' );
	$simple->set_tag_ids( array( $ids['tag'] ) );
	$simple->set_gallery_image_ids( array( digi_ab_image( 2 )[0], digi_ab_image( 3 )[0] ) );
	$simple->set_purchase_note( 'Nota de compra del producto simple.' );
	$ids['simple'] = (int) $simple->save();

	$talla = new WC_Product_Attribute();
	$talla->set_id( 0 );
	$talla->set_name( 'Talla' );
	$talla->set_options( array( 'S', 'M', 'L' ) );
	$talla->set_position( 0 );
	$talla->set_visible( true );
	$talla->set_variation( true );
	$variable = $product( new WC_Product_Variable(), 'producto-variable-ab', 'Producto variable de prueba', '2024-02-09 10:00:00', array( $ids['category'] ), 7 );
	$variable->set_sku( 'AB-VARIABLE' );
	$variable->set_attributes( array( $talla ) );
	$variable->set_tag_ids( array( $ids['tag'] ) );
	$ids['variable'] = (int) $variable->save();
	$price = 20;
	foreach ( array( 'S', 'M', 'L' ) as $size ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $ids['variable'] );
		$variation->set_attributes( array( 'talla' => $size ) );
		$variation->set_regular_price( (string) $price++ );
		$variation->set_sku( 'AB-VARIABLE-' . $size );
		$variation->set_status( 'publish' );
		$saved_id = (int) $variation->save();
		if ( 'M' === $size ) {
			$ids['variation_m'] = $saved_id;
		}
	}
	WC_Product_Variable::sync( $ids['variable'] );
	wc_delete_product_transients( $ids['variable'] );

	$sale = $product( new WC_Product_Simple(), 'producto-oferta-ab', 'Producto en oferta de prueba', '2024-02-08 10:00:00', array( $ids['category'], $ids['other_category'] ), 4 );
	$sale->set_regular_price( '40' );
	$sale->set_sale_price( '30' );
	$sale->set_sku( 'AB-OFERTA' );
	$ids['sale'] = (int) $sale->save();

	$out = $product( new WC_Product_Simple(), 'producto-agotado-ab', 'Producto agotado de prueba', '2024-02-07 10:00:00', array( $ids['category'] ), 5 );
	$out->set_regular_price( '15' );
	$out->set_sku( 'AB-AGOTADO' );
	$out->set_stock_status( 'outofstock' );
	$ids['outofstock'] = (int) $out->save();

	$reviews = $product( new WC_Product_Simple(), 'producto-resenas-ab', 'Producto con reseñas de prueba', '2024-02-06 10:00:00', array( $ids['subcategory'], $ids['other_category'] ), 6 );
	$reviews->set_regular_price( '50' );
	$reviews->set_sku( 'AB-RESENAS' );
	$reviews->set_reviews_allowed( true );
	$ids['reviews'] = (int) $reviews->save();
	foreach ( array( array( 'Lectora de prueba', 5, 'Muy buen producto de prueba.', '2024-02-20 09:00:00' ), array( 'Lector de prueba', 4, 'Cumple lo que promete.', '2024-02-21 09:00:00' ), array( 'Otra lectora', 4, 'Llegó a tiempo.', '2024-02-22 09:00:00' ) ) as $n => $review ) {
		$comment = (int) wp_insert_comment( array( 'comment_post_ID' => $ids['reviews'], 'comment_author' => $review[0], 'comment_author_email' => 'resena-' . $n . '@example.org', 'comment_content' => $review[2], 'comment_date' => $review[3], 'comment_approved' => 1, 'comment_type' => 'review' ) );
		update_comment_meta( $comment, 'rating', $review[1] );
		update_comment_meta( $comment, 'verified', 0 );
	}
	WC_Comments::clear_transients( $ids['reviews'] );

	// Ventas dirigidas y cruzadas del producto simple.
	$simple = wc_get_product( $ids['simple'] );
	$simple->set_upsell_ids( array( $ids['sale'], $ids['reviews'] ) );
	$simple->set_cross_sell_ids( array( $ids['reviews'] ) );
	$simple->save();

	// Clienta con direcciones.
	$user = get_user_by( 'login', 'cliente-ab' );
	$uid  = $user ? (int) $user->ID : (int) wp_insert_user( array( 'user_login' => 'cliente-ab', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'cliente-ab@example.org', 'display_name' => 'Carla Cliente', 'first_name' => 'Carla', 'last_name' => 'Cliente', 'nickname' => 'Carla Cliente', 'role' => 'customer' ) );
	$ids['customer'] = $uid;
	$customer        = new WC_Customer( $uid );
	$address         = array( 'first_name' => 'Carla', 'last_name' => 'Cliente', 'company' => '', 'address_1' => 'Calle de la Clienta 5', 'address_2' => '', 'city' => 'Madrid', 'state' => 'M', 'postcode' => '28002', 'country' => 'ES' );
	foreach ( $address as $field => $value ) {
		$customer->{"set_billing_$field"}( $value );
		$customer->{"set_shipping_$field"}( $value );
	}
	$customer->set_billing_email( 'cliente-ab@example.org' );
	$customer->set_billing_phone( '600000000' );
	$customer->save();

	// Pedido de la clienta.
	$order = wc_create_order( array( 'customer_id' => $uid ) );
	if ( is_wp_error( $order ) ) {
		digi_ab_fail( 'WooCommerce: no se pudo crear el pedido: ' . $order->get_error_message() );
	} else {
		$order->add_product( wc_get_product( $ids['simple'] ), 2 );
		$order->add_product( wc_get_product( $ids['sale'] ), 1 );
		$order->set_address( $address + array( 'email' => 'cliente-ab@example.org', 'phone' => '600000000' ), 'billing' );
		$order->set_address( $address, 'shipping' );
		$shipping = new WC_Order_Item_Shipping();
		$shipping->set_method_title( 'Tarifa plana' );
		$shipping->set_method_id( 'flat_rate' );
		$shipping->set_total( '5' );
		$order->add_item( $shipping );
		$order->set_payment_method( 'cod' );
		$order->set_payment_method_title( 'Contra reembolso' );
		$order->set_customer_note( 'Nota de la clienta en el pedido.' );
		$order->calculate_totals( false );
		$order->set_date_created( '2024-03-18 10:00:00' );
		$order->set_status( 'processing' );
		$ids['order']     = (int) $order->save();
		$ids['order_key'] = (string) $order->get_order_key();
	}
	update_option( 'digi_ab_woo_fixtures', $ids, false );
	return $ids;
}

/**
 * Contextos de tienda (C7): `{"product": "simple" | "variable" | "sale" | "outofstock" | "reviews"}` (la ficha de ese
 * producto, con `switch_to_query` como la vista previa de una plantilla de producto), `"shop"` (el archivo de
 * productos), `{"archive": "product_cat" | "product_subcat" | "product_tag"}`, y las páginas de WooCommerce `"cart"`,
 * `"checkout"`, `"account"` y `{"order": "received"}` (la página del caso hace de carrito, de pago, de «Mi cuenta» o de
 * pedido recibido: `woocommerce_get_<página>_page_id` devuelve su ID). Devuelve `[consulta o null, descripción, estado]`,
 * con el estado de la tienda que aplica el mu-plugin: `role` (cart, checkout, myaccount), `cart` (items/empty),
 * `user` (ID de quien entra) y `order` (pedido recibido). `null` si no es un contexto de tienda.
 */
function digi_ab_woo_context( $context ) {
	if ( in_array( $context, array( 'shop', 'cart', 'checkout', 'account' ), true ) ) {
		$context = array( $context => 'default' );
	}
	if ( ! is_array( $context ) || 1 !== count( $context ) ) {
		return null;
	}
	$kind = (string) key( $context );
	$what = (string) current( $context );
	if ( ! in_array( $kind, array( 'product', 'shop', 'cart', 'checkout', 'account', 'order' ), true ) && ! ( 'archive' === $kind && 0 === strpos( $what, 'product_' ) ) ) {
		return null;
	}
	$ids = digi_ab_woo_fixtures();
	if ( ! $ids ) {
		return null;
	}
	if ( 'product' === $kind && in_array( $what, array( 'simple', 'variable', 'sale', 'outofstock', 'reviews' ), true ) ) {
		return array( array( 'p' => $ids[ $what ], 'post_type' => 'product' ), "producto $what", array() );
	}
	if ( 'shop' === $kind ) {
		return array( array( 'post_type' => 'product' ), 'tienda', array() );
	}
	$archives = array( 'product_cat' => array( 'product_cat' => 'categoria-producto-ab' ), 'product_subcat' => array( 'product_cat' => 'subcategoria-producto-ab' ), 'product_tag' => array( 'product_tag' => 'etiqueta-producto-ab' ) );
	if ( 'archive' === $kind && isset( $archives[ $what ] ) ) {
		return array( $archives[ $what ], "archivo $what", array() );
	}
	if ( 'cart' === $kind ) {
		return array( null, 'carrito', array( 'role' => 'cart', 'cart' => 'items' ) );
	}
	if ( 'checkout' === $kind ) {
		return array( null, 'pago', array( 'role' => 'checkout', 'cart' => 'items' ) );
	}
	if ( 'account' === $kind ) {
		return array( null, 'mi cuenta', array( 'role' => 'myaccount', 'user' => 'customer' ) );
	}
	if ( 'order' === $kind && 'received' === $what && ! empty( $ids['order'] ) ) {
		return array( null, 'pedido recibido', array( 'role' => 'checkout', 'user' => 'customer', 'order' => $ids['order'], 'order_key' => $ids['order_key'] ) );
	}
	return null;
}

/**
 * Estado de tienda de una página del A/B: el del contexto más el campo `woo` del caso (objeto, opcional): `cart`
 * (`items` o `empty`), `user` (`customer` o `guest`), `endpoint` (un punto de «Mi cuenta»: `orders`, `view-order`,
 * `downloads`, `edit-address`, `edit-account`, `payment-methods`; `view-order` muestra el pedido de prueba) y `notices`
 * (`true`: un aviso de éxito, uno de información y uno de error en cada petición). `user` se traduce al ID.
 */
function digi_ab_woo_state( array $state, $woo ) {
	if ( is_array( $woo ) ) {
		foreach ( array( 'cart', 'user', 'endpoint', 'notices' ) as $key ) {
			if ( array_key_exists( $key, $woo ) ) {
				$state[ $key ] = $woo[ $key ];
			}
		}
	}
	if ( isset( $state['user'] ) ) {
		$state['user'] = 'customer' === $state['user'] ? (int) ( digi_ab_woo_fixtures()['customer'] ?? 0 ) : 0;
	}
	if ( isset( $state['endpoint'] ) && 'view-order' === $state['endpoint'] ) {
		$state['endpoint_value'] = (string) ( digi_ab_woo_fixtures()['order'] ?? '' );
	}
	return $state;
}

/**
 * Consulta del contexto de un caso (`context`): `"post"` o `{"post": "full"}` (la entrada completa), `{"post":
 * "bare"}` (la entrada sin extras), `"page"` (la página de prueba) o `{"archive": "category" | "tag" | "author" |
 * "date" | "search"}`. Las dos páginas del caso pintan sus elementos con esta consulta (mu-plugin `digi-ab-context.php`,
 * `Plugin::$instance->db->switch_to_query()`, como la vista previa del Theme Builder), así que A y B se miden en el
 * mismo contexto. Devuelve `[consulta, descripción]` o `null` si el valor no es válido.
 */
function digi_ab_context( $context ) {
	if ( 'post' === $context || 'page' === $context ) {
		$context = array( $context => 'full' );
	}
	if ( ! is_array( $context ) || 1 !== count( $context ) ) {
		return null;
	}
	$ids  = digi_ab_fixtures();
	$kind = (string) key( $context );
	$what = (string) current( $context );
	if ( 'post' === $kind && in_array( $what, array( 'full', 'bare' ), true ) ) {
		return array( array( 'p' => $ids[ 'full' === $what ? 'post' : 'bare' ] ), "entrada $what" );
	}
	if ( 'page' === $kind && 'full' === $what ) {
		return array( array( 'page_id' => $ids['page'] ), 'página' );
	}
	$archives = array( 'category' => array( 'cat' => $ids['category'] ), 'tag' => array( 'tag_id' => $ids['tag'] ), 'author' => array( 'author' => $ids['author'] ), 'date' => array( 'year' => 2024, 'monthnum' => 3 ), 'search' => array( 's' => 'prueba' ) );
	if ( 'archive' === $kind && isset( $archives[ $what ] ) ) {
		return array( $archives[ $what ], "archivo $what" );
	}
	return null;
}

/**
 * mu-plugin del contexto: en las páginas de `digi_ab_context` (opción), cada árbol de elementos se pinta con su consulta;
 * con tienda (C7), además, el estado de WooCommerce de la página (`woo`): página que hace de carrito, de pago o de «Mi
 * cuenta», carrito lleno o vacío, avisos, pedido recibido y punto de «Mi cuenta». Quien entra (`digi_ab_woo_users`, por
 * ruta de la página) se decide en `determine_current_user`, también en las peticiones `?wc-ajax=` que salen de esa página
 * (la revisión del pedido del pago); sin barra de administración.
 */
function digi_ab_context_plugin() {
	$code = <<<'PHP'
<?php
// pro-ab.sh (setup.php, `context` de un caso): la página A o B de un caso con contexto pinta sus elementos con la
// consulta del contexto, como la vista previa del Theme Builder (`switch_to_query`), desde el primer elemento de nivel
// superior hasta que termina. El avatar de cualquier usuario es una imagen local (sin peticiones a terceros).
// C7: el estado de tienda de la página (`woo`) y la persona que entra en ella (`digi_ab_woo_users`).
add_action( 'wp', static function () {
	$map = get_option( 'digi_ab_context' );
	$id  = (int) get_queried_object_id();
	if ( ! is_array( $map ) || ! $id || empty( $map[ $id ] ) || ! class_exists( '\Elementor\Plugin' ) ) {
		return;
	}
	$vars  = (array) ( $map[ $id ]['vars'] ?? array() );
	$depth = 0;
	if ( $vars ) {
		add_action( 'elementor/frontend/before_render', static function () use ( $vars, &$depth ) {
			if ( 0 === $depth++ ) {
				\Elementor\Plugin::$instance->db->switch_to_query( $vars, true );
			}
		}, -1000 );
		add_action( 'elementor/frontend/after_render', static function () use ( &$depth ) {
			if ( 0 === --$depth ) {
				\Elementor\Plugin::$instance->db->restore_current_query();
			}
		}, 1000 );
	}
	$avatar = (string) ( $map[ $id ]['avatar'] ?? '' );
	if ( '' !== $avatar ) {
		add_filter( 'pre_get_avatar_data', static function ( $args ) use ( $avatar ) {
			$args['url'] = $avatar;
			$args['found_avatar'] = true;
			return $args;
		} );
	}
	$woo = (array) ( $map[ $id ]['woo'] ?? array() );
	if ( ! $woo || ! function_exists( 'WC' ) ) {
		return;
	}
	if ( ! empty( $woo['role'] ) ) {
		add_filter( 'woocommerce_get_' . $woo['role'] . '_page_id', static function () use ( $id ) {
			return $id;
		} );
	}
	$fixtures = (array) get_option( 'digi_ab_woo_fixtures', array() );
	if ( ! empty( $woo['cart'] ) && WC()->cart ) {
		WC()->cart->empty_cart();
		if ( 'items' === $woo['cart'] ) {
			WC()->cart->add_to_cart( (int) $fixtures['simple'], 2 );
			WC()->cart->add_to_cart( (int) $fixtures['variable'], 1, (int) $fixtures['variation_m'], array( 'attribute_talla' => 'M' ) );
			WC()->cart->add_to_cart( (int) $fixtures['sale'], 1 );
			WC()->cart->calculate_totals();
		}
	}
	if ( ! empty( $woo['notices'] ) ) {
		wc_add_notice( 'Aviso de éxito de prueba.', 'success' );
		wc_add_notice( 'Aviso informativo de prueba.', 'notice' );
		wc_add_notice( 'Aviso de error de prueba.', 'error' );
	}
	global $wp;
	if ( ! empty( $woo['order'] ) ) {
		$wp->query_vars['order-received'] = (string) $woo['order'];
		$_GET['key']                      = (string) ( $woo['order_key'] ?? '' );
	}
	if ( ! empty( $woo['endpoint'] ) ) {
		$vars_wc = WC()->query->get_query_vars();
		$endpoint_var = $vars_wc[ $woo['endpoint'] ] ?? $woo['endpoint'];
		$wp->query_vars[ $endpoint_var ] = (string) ( $woo['endpoint_value'] ?? '' );
		// Como una petición real a `/mi-cuenta/<punto>/`: la consulta principal también lo trae (el widget de cuenta de
		// PRO y el propio leen el punto de `$wp_query->query`).
		$GLOBALS['wp_query']->query[ $endpoint_var ]      = $wp->query_vars[ $endpoint_var ];
		$GLOBALS['wp_query']->query_vars[ $endpoint_var ] = $wp->query_vars[ $endpoint_var ];
	}
} );
add_filter( 'determine_current_user', static function ( $user ) {
	$users = get_option( 'digi_ab_woo_users' );
	if ( ! is_array( $users ) || ! $users ) {
		return $user;
	}
	$from = isset( $_GET['wc-ajax'] ) ? ( $_SERVER['HTTP_REFERER'] ?? '' ) : ( $_SERVER['REQUEST_URI'] ?? '' );
	$path = rtrim( (string) parse_url( (string) $from, PHP_URL_PATH ), '/' );
	return isset( $users[ $path ] ) ? (int) $users[ $path ] : $user;
}, 30 );
if ( get_option( 'digi_ab_woo_fixtures' ) ) {
	add_filter( 'show_admin_bar', '__return_false' );
	// Los relacionados no se barajan en el A/B: A y B piden la misma lista.
	add_filter( 'woocommerce_product_related_posts_shuffle', '__return_false' );
	// Tampoco las ventas dirigidas ni las cruzadas, que WooCommerce ordena al azar por defecto (`rand`): por título.
	add_filter( 'woocommerce_upsells_orderby', static function () { return 'title'; } );
	add_filter( 'woocommerce_cross_sells_orderby', static function () { return 'title'; } );
	// Ni los relacionados de la plantilla de la ficha de WooCommerce (`woocommerce_output_related_products()` pide `rand`).
	add_filter( 'woocommerce_output_related_products_args', static function ( $args ) { $args['orderby'] = 'title'; $args['order'] = 'asc'; return $args; } );
	// Con un solo proceso de PHP, la primera petición de fragmentos del carrito puede tardar más de los 5 s que espera
	// `wc-cart-fragments` y el navegador la corta (igual en A y en B): 30 s en el A/B.
	add_filter( 'woocommerce_get_script_data', static function ( $params, $handle ) {
		if ( 'wc-cart-fragments' === $handle && is_array( $params ) ) {
			$params['request_timeout'] = 30000;
		}
		return $params;
	}, 10, 2 );
}
PHP;
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-ab-context.php', $code );
}

if ( ! class_exists( '\\Elementor\\Plugin' ) || ! class_exists( '\\Digitalisimo\\Elements\\Convert\\Converter' ) ) {
	digi_ab_fail( 'El constructor o el módulo de conversión (Digitalisimo\\Elements\\Convert) no están cargados' );
	return;
}
$cases = json_decode( (string) file_get_contents( DIGI_AB_DIR . '/cases.json' ), true );
if ( ! is_array( $cases ) || empty( $cases['cases'] ) ) {
	digi_ab_fail( 'cases.json no es válido o no tiene casos' );
	return;
}
$only = array_filter( array_map( 'trim', explode( ',', (string) @file_get_contents( DIGI_AB_DIR . '/only.txt' ) ) ) );

$override = '';
if ( is_file( DIGI_AB_DIR . '/map-override.php' ) ) {
	$map = require DIGI_AB_DIR . '/map-override.php';
	if ( ! is_array( $map ) || empty( $map['source'] ) || empty( $map['target'] ) ) {
		digi_ab_fail( 'MAP no devuelve un mapa con source y target' );
		return;
	}
	\Digitalisimo\Elements\Convert\Registry::set( (string) $map['source'], $map );
	$override = (string) $map['source'];
}

// C8: los documentos de botones flotantes no admiten plantilla de página (el tema pintaría su entrada, comentarios y
// navegación detrás del bloque fijo, distinta en A y en B): se ven con la plantilla «Lienzo» del constructor.
if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}
file_put_contents( WPMU_PLUGIN_DIR . '/digi-ab-floating-canvas.php', "<?php\nadd_filter( 'template_include', static function ( \$t ) { return is_singular( 'e-floating-buttons' ) && defined( 'ELEMENTOR_PATH' ) && is_file( ELEMENTOR_PATH . 'modules/page-templates/templates/canvas.php' ) ? ELEMENTOR_PATH . 'modules/page-templates/templates/canvas.php' : \$t; }, 99 );\n" );

$widgets = \Elementor\Plugin::$instance->widgets_manager;
// Widgets de PRO que PRO no registra (C7: `woocommerce-category-image`, clase en `modules/woocommerce/widgets/` fuera de
// `WIDGET_NAME_CLASS_NAME_MAP`): un caso con `"register_pro": {"file": "<ruta en el plugin>", "class": "<clase>"}` lo
// registra aquí y, en cada petición, con el mu-plugin `digi-ab-register.php`, para que A pinte el widget de PRO tal cual.
$digi_ab_register = array();
foreach ( $cases['cases'] as $digi_ab_slug => $digi_ab_case ) {
	$digi_ab_chosen = $only ? in_array( $digi_ab_slug, $only, true ) : empty( $digi_ab_case['pending'] );
	if ( $digi_ab_chosen && ! empty( $digi_ab_case['register_pro']['file'] ) && ! empty( $digi_ab_case['register_pro']['class'] ) && preg_match( '/^[a-z0-9\/_-]+\.php$/', (string) $digi_ab_case['register_pro']['file'] ) ) {
		$digi_ab_register[ (string) $digi_ab_case['register_pro']['class'] ] = (string) $digi_ab_case['register_pro']['file'];
	}
}
if ( $digi_ab_register ) {
	$digi_ab_code = "<?php\n// pro-ab.sh (setup.php, `register_pro` de un caso): registra widgets de PRO que PRO no registra.\nadd_action( 'elementor/widgets/register', static function ( \$manager ) {\n\tforeach ( " . var_export( $digi_ab_register, true ) . " as \$class => \$file ) {\n\t\trequire_once WP_PLUGIN_DIR . '/digitalisimo-elements/' . \$file;\n\t\t\$name = ( new \\ReflectionClass( \$class ) )->newInstanceWithoutConstructor()->get_name();\n\t\tif ( ! \$manager->get_widget_types( \$name ) ) {\n\t\t\t\$manager->register( new \$class() );\n\t\t}\n\t}\n}, 99 );\n";
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-ab-register.php', $digi_ab_code );
	$widgets->get_widget_types(); // Carga los widgets del núcleo (la Imagen, de la que heredan).
	foreach ( $digi_ab_register as $digi_ab_class => $digi_ab_file ) {
		require_once WP_PLUGIN_DIR . '/digitalisimo-elements/' . $digi_ab_file;
		$digi_ab_widget = new $digi_ab_class();
		if ( ! $widgets->get_widget_types( $digi_ab_widget->get_name() ) ) {
			$widgets->register( $digi_ab_widget );
		}
	}
}
$out     = array( 'elementor' => ELEMENTOR_VERSION, 'override' => $override, 'cases' => array() );
$contexts   = array();
$c4_context = false;
$woo_used   = false;
$woo_users  = array();
foreach ( $cases['cases'] as $slug => $case ) {
	if ( $only ? ! in_array( $slug, $only, true ) : ! empty( $case['pending'] ) ) {
		continue;
	}
	if ( ! preg_match( '/^[a-z0-9-]+$/', (string) $slug ) ) {
		digi_ab_fail( "Nombre de caso no válido: $slug" );
		continue;
	}
	$source = (string) ( $case['widget'] ?? '' );
	$id     = digi_ab_eid( $slug );
	if ( ! empty( $case['posts'] ) ) {
		digi_ab_posts( (int) $case['posts'] );
	}
	$GLOBALS['digi_ab_templates'] = (array) ( $case['templates'] ?? array() );
	$tree   = array(
		array(
			'id'       => digi_ab_eid( $slug . '-c' ),
			'elType'   => 'container',
			'isInner'  => false,
			// `container` del caso se suma al común y gana en sus claves: C7 (frente M) añade la clase `woocommerce` de la ficha y
			// C8, `content_width: boxed` (los botones flotantes de PRO buscan el `.e-con-inner` de un contenedor encuadrado).
			'settings' => array_merge( (array) ( $cases['container'] ?? array() ), (array) ( $case['container'] ?? array() ) ),
			'elements' => array_merge(
				digi_ab_before( (array) ( $case['before'] ?? array() ), $slug, $id ),
				array(
					array(
						'id'         => $id,
						'elType'     => 'widget',
						'widgetType' => $source,
						'settings'   => digi_ab_tokens( (array) ( $case['settings'] ?? array() ) ),
						'elements'   => digi_ab_children( (array) ( $case['elements'] ?? array() ), $slug ),
					),
				),
				digi_ab_siblings( (array) ( $case['after'] ?? array() ), $slug . '-after', $id )
			),
		),
	);
	$map    = \Digitalisimo\Elements\Convert\Registry::map( $source, true );
	$target = null !== $map ? (string) $map['target'] : '';
	$log    = array();
	if ( 'global' === $source && ! empty( $case['global'] ) && class_exists( '\\Digitalisimo\\Elements\\Convert\\Globals' ) ) {
		// C9: A con la instancia del widget global (y la copia de ajustes que guarda PRO); B, sustituida y convertida.
		$content  = (array) $case['global'];
		$template = digi_ab_template( $slug, array( array( 'id' => digi_ab_eid( $slug . '-g' ), 'elType' => 'widget', 'widgetType' => (string) ( $content['widgetType'] ?? '' ), 'settings' => (array) ( $content['settings'] ?? array() ), 'elements' => array() ) ), 'widget' );
		$tree[0]['elements'][0]['templateID'] = $template;
		$tree[0]['elements'][0]['settings']   = digi_ab_tokens( (array) ( $content['settings'] ?? array() ) );
		$globals  = array();
		$expanded = \Digitalisimo\Elements\Convert\Globals::tree(
			$tree,
			static function ( $tid ) {
				$raw = json_decode( (string) get_post_meta( (int) $tid, '_elementor_data', true ), true );
				return is_array( $raw ) ? $raw : null;
			},
			$globals
		);
		$b_tree = \Digitalisimo\Elements\Convert\Converter::tree( $expanded, $log, true );
		$log    = array_merge( $globals, $log );
		$target = (string) ( $globals[0]['target'] ?? '' );
		$map    = array( 'status' => 'ready', 'target' => $target );
		if ( 1 !== count( $globals ) || empty( $globals[0]['expanded'] ) ) {
			digi_ab_fail( "$slug: la instancia del widget global no se sustituyó" );
		}
		$after  = digi_ab_find( $b_tree, $id );
		$target = (string) ( $after['widgetType'] ?? $target );
		$log    = array( array( 'source' => 'global', 'target' => $target, 'element' => $id, 'codes' => array_merge( ...array_map( static function ( $row ) { return (array) ( $row['codes'] ?? array() ); }, $log ) ) ) );
	} else {
		$b_tree = \Digitalisimo\Elements\Convert\Converter::tree( $tree, $log, true );
	}
	$entry  = array(
		'widget'     => $source,
		'element'    => $id,
		'pro'        => \Digitalisimo\Elements\Convert\Catalog::is_pro( $source ),
		'map'        => null !== $map,
		'status'     => null !== $map ? (string) ( $map['status'] ?? 'draft' ) : '',
		'target'     => $target,
		'registered' => array(
			'a' => null !== $widgets->get_widget_types( $source ),
			'b' => '' !== $target && null !== $widgets->get_widget_types( $target ),
		),
		'reasons'    => null !== $map ? digi_ab_reasons( $map ) : array( 'drop' => array(), 'approx' => array() ),
		'log'        => $log,
		'settings_b' => digi_ab_find( $b_tree, $id )['settings'] ?? array(),
		'error'      => '',
	);
	if ( null === $map ) {
		$entry['error'] = "No hay mapa (ni en borrador) para «{$source}»: B sería igual que A";
	} elseif ( ! $entry['registered']['b'] ) {
		$entry['error'] = "El widget de destino «{$target}» no está registrado: B no pintaría nada";
	} elseif ( count( wp_list_filter( $log, array( 'element' => $id ) ) ) !== 1 ) {
		$entry['error'] = 'La conversión no dejó un registro por el widget del caso (' . count( $log ) . ')';
	}
	$doc = in_array( (string) ( $case['doc'] ?? '' ), array( 'floating-buttons', 'floating-bars' ), true ) ? (string) $case['doc'] : '';
	list( $entry['a'], $entry['css_a'] ) = digi_ab_page( "pro-ab-$slug-a", $tree, $doc );
	list( $entry['b'], $entry['css_b'] ) = digi_ab_page( "pro-ab-$slug-b", $b_tree, $doc );
	if ( '' !== $doc ) {
		// Ruta de cada documento (run.cjs la usa en lugar de /pro-ab-<caso>-<lado>/).
		$entry['urls'] = array(
			'a' => (string) wp_make_link_relative( get_permalink( $entry['a'] ) ),
			'b' => (string) wp_make_link_relative( get_permalink( $entry['b'] ) ),
		);
	}
	// Contexto (C4) y tienda (C7): la consulta de la página y, con WooCommerce, su estado (`woo`). Un widget de tienda
	// de PRO (`woocommerce-*`, `wc-*`) siempre mide con la tienda de prueba.
	$woo_used = $woo_used || (bool) preg_match( '/^(woocommerce-|wc-)/', $source );
	$context  = null;
	if ( isset( $case['context'] ) ) {
		$context = digi_ab_woo_context( $case['context'] );
		if ( null === $context ) {
			$context = digi_ab_context( $case['context'] );
			$c4_context = $c4_context || null !== $context;
		}
		if ( null === $context ) {
			$entry['error'] = 'context no válido: ' . wp_json_encode( $case['context'] );
		}
	}
	$woo_state = array();
	if ( ! empty( $case['woo'] ) || ( null !== $context && isset( $context[2] ) ) ) {
		$woo_used  = true;
		$woo_state = digi_ab_woo_state( null !== $context && isset( $context[2] ) ? $context[2] : array(), $case['woo'] ?? true );
		if ( ! empty( $woo_state['user'] ) ) {
			$woo_users[ '/pro-ab-' . $slug . '-a' ] = $woo_users[ '/pro-ab-' . $slug . '-b' ] = (int) $woo_state['user'];
		}
	}
	if ( null !== $context || $woo_state ) {
		$contexts[ $entry['a'] ] = $contexts[ $entry['b'] ] = array( 'vars' => null !== $context ? $context[0] : array(), 'avatar' => digi_ab_image( 10 )[1], 'woo' => $woo_state );
		$entry['context']        = array( 'query' => null !== $context ? $context[0] : null, 'label' => null !== $context ? $context[1] : 'tienda' );
		if ( $woo_state ) {
			$entry['context']['woo'] = $woo_state;
		}
	}
	if ( isset( $case['archive'] ) ) {
		digi_ab_archive( $slug, (array) $case['archive'] );
	}
	$out['cases'][ $slug ] = $entry;
}
// Contexto de los casos que lo piden (`context`): la consulta de cada página A y B y el mu-plugin que la aplica.
if ( $contexts || $woo_used ) {
	update_option( 'digi_ab_context', $contexts, false );
	update_option( 'digi_ab_woo_users', $woo_users, false );
	digi_ab_context_plugin();
	if ( $c4_context ) {
		$out['fixtures'] = digi_ab_fixtures();
	}
}
// Tienda de prueba (C7): los ID de los datos de WooCommerce, para quien lea conversion.json.
if ( $woo_used ) {
	$out['woo'] = digi_ab_woo_fixtures();
}
if ( ! $out['cases'] ) {
	digi_ab_fail( 'Ningún caso que medir (CASES no coincide con cases.json)' );
}
// Ajustes de «Rendimiento» de un sitio recién instalado (la puerta de PRO entre ellos), aplicados ya: su transición
// corre por WP-Cron, que aquí no se dispara desde las visitas. Sin la puerta, B cargaría los paquetes de PRO igual que A.
$transition = '\\Digitalisimo\\Elements\\Front\\Transition';
if ( class_exists( $transition ) ) {
	for ( $i = 0; $i < 10 && $transition::pending(); $i++ ) {
		$transition::run_now();
	}
	$out['front'] = $transition::applied();
	$out['front_pending'] = $transition::pending();
} else {
	$out['front'] = array();
}
// Página que el arnés consulta para saber que el sitio está listo.
if ( ! get_page_by_path( 'pro-ab-ready' ) ) {
	wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'pro-ab-ready', 'post_name' => 'pro-ab-ready', 'post_content' => 'listo' ) );
}
flush_rewrite_rules( false );
delete_transient( 'doing_cron' );
file_put_contents( DIGI_AB_DIR . '/conversion.json', wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
file_put_contents( DIGI_AB_DIR . '/ready.json', wp_json_encode( array( 'cases' => count( $out['cases'] ) ) ) );
