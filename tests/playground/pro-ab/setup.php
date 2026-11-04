<?php
/**
 * Segundo paso del blueprint de pro-ab.sh (el constructor ya está cargado). Por cada caso de `cases.json`:
 *
 * - página A `pro-ab-<caso>-a`: un contenedor con el widget de PRO (`widget`), sus ajustes guardados (`settings`) y,
 *   en los anidados, sus hijos (`elements`); las imágenes de prueba (`{"$image": n}`, `{"$gallery": [n, …]}`,
 *   `"$image-url:n"`) se crean una vez con GD (`digi_ab_image()`) y los menús de WordPress (`{"$menu": "nombre"}`, el
 *   slug de un menú con submenús), con `digi_ab_menu()`;
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

/** Sustituye en unos ajustes las imágenes de prueba: `{"$image": n}`, `{"$gallery": [n, …]}` y `"$image-url:n"`. */
function digi_ab_tokens( $value ) {
	if ( is_string( $value ) && preg_match( '/^\$image-url:(\d+)$/', $value, $m ) ) {
		return digi_ab_image( (int) $m[1] )[1];
	}
	if ( ! is_array( $value ) ) {
		return $value;
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
			'elements' => array(
				array(
					'id'         => $id,
					'elType'     => 'widget',
					'widgetType' => $source,
					'settings'   => digi_ab_tokens( (array) ( $case['settings'] ?? array() ) ),
					'elements'   => digi_ab_children( (array) ( $case['elements'] ?? array() ), $slug ),
				),
			),
		),
	);
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
		'settings_b' => $b_tree[0]['elements'][0]['settings'] ?? array(),
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
