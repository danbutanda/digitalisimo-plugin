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
	if ( is_string( $value ) && preg_match( '/^\$image-url:(\d+)$/', $value, $m ) ) {
		return digi_ab_image( (int) $m[1] )[1];
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
	$b_tree = \Digitalisimo\Elements\Convert\Converter::tree( $tree, $log, true );
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
	$out['cases'][ $slug ] = $entry;
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
