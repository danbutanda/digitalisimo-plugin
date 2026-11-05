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

/** Guarda el árbol de una página (crea la página si no existe) y genera su CSS. Devuelve [ID, estado del CSS]. */
function digi_ab_page( $slug, array $tree ) {
	$existing = get_page_by_path( $slug );
	$id       = $existing ? (int) $existing->ID : (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ) );
	if ( ! $id ) {
		digi_ab_fail( "No se pudo crear la página $slug" );
		return array( 0, '' );
	}
	update_post_meta( $id, '_wp_page_template', 'elementor_canvas' );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'wp-page' );
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
 * Sustituye en unos ajustes las imágenes de prueba: `{"$image": n}`, `{"$gallery": [n, …]}` y `"$image-url:n"`; y la
 * animación Lottie de prueba: `{"$lottie": 1}` (adjunto) y `"$lottie-url"` (su URL).
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
	if ( '$lottie-url' === $value ) {
		return digi_ab_lottie()[1];
	}
	if ( ! is_array( $value ) ) {
		return $value;
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

/** mu-plugin del contexto: en las páginas de `digi_ab_context` (opción), cada árbol de elementos se pinta con su consulta. */
function digi_ab_context_plugin() {
	$code = <<<'PHP'
<?php
// pro-ab.sh (setup.php, `context` de un caso): la página A o B de un caso con contexto pinta sus elementos con la
// consulta del contexto, como la vista previa del Theme Builder (`switch_to_query`), desde el primer elemento de nivel
// superior hasta que termina. El avatar de cualquier usuario es una imagen local (sin peticiones a terceros).
add_action( 'wp', static function () {
	$map = get_option( 'digi_ab_context' );
	$id  = (int) get_queried_object_id();
	if ( ! is_array( $map ) || ! $id || empty( $map[ $id ] ) || ! class_exists( '\Elementor\Plugin' ) ) {
		return;
	}
	$vars  = (array) $map[ $id ]['vars'];
	$depth = 0;
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
	$avatar = (string) ( $map[ $id ]['avatar'] ?? '' );
	if ( '' !== $avatar ) {
		add_filter( 'pre_get_avatar_data', static function ( $args ) use ( $avatar ) {
			$args['url'] = $avatar;
			$args['found_avatar'] = true;
			return $args;
		} );
	}
} );
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

$widgets = \Elementor\Plugin::$instance->widgets_manager;
$out     = array( 'elementor' => ELEMENTOR_VERSION, 'override' => $override, 'cases' => array() );
$contexts = array();
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
	$GLOBALS['digi_ab_templates'] = (array) ( $case['templates'] ?? array() );
	$tree   = array(
		array(
			'id'       => digi_ab_eid( $slug . '-c' ),
			'elType'   => 'container',
			'isInner'  => false,
			'settings' => (array) ( $cases['container'] ?? array() ),
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
				)
			),
		),
	);
	// `before` y `after` del caso (C2): elementos hermanos del widget en el mismo contenedor (los encabezados que lee una
	// tabla de contenido, por ejemplo), con IDs deterministas e imágenes de prueba; no son de PRO y no se convierten.
	// `before` ya lo coloca `digi_ab_before()` (C3) al construir el árbol.
	if ( ! empty( $case['after'] ) ) {
		$tree[0]['elements'] = array_merge( $tree[0]['elements'], digi_ab_children( (array) $case['after'], $slug . '-after' ) );
	}
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
	} elseif ( count( $log ) !== 1 ) {
		$entry['error'] = 'La conversión no dejó un registro por el widget del caso (' . count( $log ) . ')';
	}
	list( $entry['a'], $entry['css_a'] ) = digi_ab_page( "pro-ab-$slug-a", $tree );
	list( $entry['b'], $entry['css_b'] ) = digi_ab_page( "pro-ab-$slug-b", $b_tree );
	if ( isset( $case['context'] ) ) {
		$context = digi_ab_context( $case['context'] );
		if ( null === $context ) {
			$entry['error'] = 'context no válido: ' . wp_json_encode( $case['context'] );
		} else {
			$contexts[ $entry['a'] ] = $contexts[ $entry['b'] ] = array( 'vars' => $context[0], 'avatar' => digi_ab_image( 10 )[1] );
			$entry['context']        = array( 'query' => $context[0], 'label' => $context[1] );
		}
	}
	$out['cases'][ $slug ] = $entry;
}
// Contexto de los casos que lo piden (`context`): la consulta de cada página A y B y el mu-plugin que la aplica.
if ( $contexts ) {
	update_option( 'digi_ab_context', $contexts, false );
	digi_ab_context_plugin();
	$out['fixtures'] = digi_ab_fixtures();
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
