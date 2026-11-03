<?php
/**
 * F8 · frente E: conversión de Prime Slider estático (familias A, B, C, D, H, I, J, K y M) en DIGITALÍSIMO Migración.
 *
 * Cubre V.10 y §3.E.0-§3.E.3, §3.E.5 y §3.E.6:
 * - los 38 fixtures sintéticos `tests/legacy/prime-slider-*.json` de E (valores por defecto y editados): destino de cada
 *   familia, ID del original conservado, contenedores `isInner`/`isLocked`, filas = hijos, IDs únicos, sin rastro del
 *   proveedor (hosts de `vendors.json`, vídeos de `vendorVideos`, carpeta del plugin) y nunca `_skin`, `_title` ni
 *   `_digitalisimo_legacy` en el principal;
 * - valores por defecto del widget registrado (Prime Slider cargado) frente a los estáticos de B, con `{{ps_assets}}`
 *   y la traducción `__t` por el MO de `WP_LANG_DIR/plugins/`;
 * - lista blanca de claves comunes, hermanos (redes sin el proveedor y «Scroll Down»), contador, efectos, reproducción
 *   automática, alturas, partes ocultas, familia C decidida antes de las imágenes, respaldo `slides` y sin destino;
 * - imágenes de demostración: PNG y SVG (subida sin filtrar apagada y encendida, SVG saneado antes de escribirse),
 *   deduplicación, registro `pending` → `done`, temporal fuera de `uploads` y borrado, sin tamaños intermedios, y rutas
 *   hostiles (`../`, `%2e`, doble codificación, enlace simbólico, `.txt`, > 5 MB, fuera de `assets/images/`, plugin
 *   borrado); modo puro sin copias y reversión;
 * - valores CSS maliciosos (Css_Value) y descripción para el análisis previo.
 *
 * Se ejecuta con php-wasm (`node …/php-wasm-cli tests/migration-prime-slider.php`): sale con 0 aunque haya un error
 * fatal, así que se juzga por la línea final.
 */

namespace Elementor\Core\Utils\Svg {
	if ( ! class_exists( Svg_Sanitizer::class, false ) ) {
		/** Saneador SVG mínimo (el del núcleo no se carga en la prueba): quita scripts y manejadores de eventos. */
		final class Svg_Sanitizer {
			public function sanitize( $content ) {
				$GLOBALS['ps_sanitized'] = ( $GLOBALS['ps_sanitized'] ?? 0 ) + 1;
				$content = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $content );
				return preg_replace( '#\son[a-z]+\s*=\s*"[^"]*"#i', '', (string) $content );
			}
		}
	}
}

namespace Digitalisimo\Elements\Import\Kits {
	if ( ! class_exists( Preserve::class, false ) ) {
		/** Marcador de K con la misma forma (`class-preserve.php:538-558`). */
		final class Preserve {
			public static function marker( array $element, string $reason = 'unknown', string $source = 'kit' ): array {
				return array( 'id' => (string) ( $element['id'] ?? 'm' ), 'elType' => 'widget', 'widgetType' => 'digitalisimo-unconverted', 'settings' => array( '_digitalisimo_unconverted' => array( 'v' => 1, 'type' => 'widget/' . ( $element['widgetType'] ?? '' ), 'reason' => $reason, 'source' => $source, 'element' => $element ) ), 'elements' => array() );
			}
			public static function info( $marker ): ?array {
				return is_array( $marker['settings']['_digitalisimo_unconverted'] ?? null ) ? $marker['settings']['_digitalisimo_unconverted'] : null;
			}
		}
	}
}

namespace {
	$GLOBALS['ps_locale'] = 'es_MX';

	/* ---------------------------------------------------------------- Dobles de medios y de idioma */

	/*
	 * El bloque de cadenas de f8-wp.php va entero tras un solo `function_exists( 'wp_strip_all_tags' )`: para tener un
	 * `wp_kses_post` que quite los manejadores de eventos se declara aquí, antes de incluirlo, y cada función sólo si
	 * nadie la declaró antes (así no choca con ninguna otra definición).
	 */
	if ( ! function_exists( 'wp_strip_all_tags' ) ) {
		function wp_strip_all_tags( $text, $remove_breaks = false ) {
			$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
			$text = strip_tags( $text );
			return trim( $remove_breaks ? preg_replace( '/[\r\n\t ]+/', ' ', $text ) : $text );
		}
	}
	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( $key ) {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
		}
	}
	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $text ) {
			return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) );
		}
	}
	if ( ! function_exists( 'remove_accents_f8' ) ) {
		function remove_accents_f8( $text ) {
			return strtr( $text, array( 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n' ) );
		}
	}
	if ( ! function_exists( 'sanitize_title' ) ) {
		function sanitize_title( $title ) {
			$title = strtolower( remove_accents_f8( strip_tags( (string) $title ) ) );
			$title = preg_replace( '/[^a-z0-9_\-]+/', '-', $title );
			return trim( preg_replace( '/-+/', '-', $title ), '-' );
		}
	}
	if ( ! function_exists( 'sanitize_file_name' ) ) {
		function sanitize_file_name( $name ) {
			$name = preg_replace( '/[^A-Za-z0-9._\-]+/', '-', remove_accents_f8( (string) $name ) );
			return trim( $name, '.-' );
		}
	}
	if ( ! function_exists( 'wp_kses_post' ) ) {
		/** Como el kses de WordPress en lo que importa aquí: sin scripts, estilos, manejadores de eventos ni `javascript:`. */
		function wp_kses_post( $text ) {
			$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
			$text = preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $text );
			return preg_replace( '/(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*/i', '$1=$2', $text );
		}
	}
	function determine_locale() {
		return $GLOBALS['ps_locale'];
	}
	function wp_tempnam( $name = '' ) {
		$dir = sys_get_temp_dir() . '/ps-e-tmp';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}
		$file                   = tempnam( $dir, 'ps' );
		$GLOBALS['ps_tmp'][]    = $file;
		return $file;
	}
	function wp_check_filetype_and_ext( $file, $filename, $mimes = null ) {
		$ext  = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$type = is_array( $mimes ) ? ( $mimes[ $ext ] ?? false ) : false;
		if ( $type && 'svg' !== $ext ) {
			$info = @getimagesize( $file );
			if ( ! $info || $info['mime'] !== $type ) {
				return array( 'ext' => false, 'type' => false, 'proper_filename' => false );
			}
		}
		if ( 'svg' === $ext && false === stripos( (string) file_get_contents( $file ), '<svg' ) ) {
			return array( 'ext' => false, 'type' => false, 'proper_filename' => false );
		}
		return array( 'ext' => $type ? $ext : false, 'type' => $type, 'proper_filename' => false );
	}
	/** Inserción: mueve el temporal a `uploads`, crea el adjunto y anota los filtros activos en ese momento. */
	function media_handle_sideload( $file_array, $post_id = 0, $desc = null ) {
		$tmp   = (string) $file_array['tmp_name'];
		$name  = basename( (string) $file_array['name'] );
		$up    = wp_upload_dir();
		$sizes = apply_filters( 'intermediate_image_sizes_advanced', array( 'thumbnail' => array( 'width' => 150 ) ) );
		$mimes = apply_filters( 'upload_mimes', array( 'png' => 'image/png', 'jpg|jpeg' => 'image/jpeg' ) );
		$ext   = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$registry = '\\Digitalisimo\\Elements\\Legacy\\Platform\\Registry';
		$pending  = class_exists( $registry ) && in_array( 'pending', array_column( (array) $registry::all( 'demo' ), 'state' ), true );
		$GLOBALS['ps_sideloads'][] = array( 'tmp' => $tmp, 'name' => $name, 'sizes' => $sizes, 'svg_allowed' => isset( $mimes['svg'] ), 'content' => (string) file_get_contents( $tmp ), 'pending' => $pending );
		if ( 'svg' === $ext && ! isset( $mimes['svg'] ) ) {
			return new WP_Error( 'type', 'no permitido' );
		}
		$dest = $up['path'] . '/' . wp_unique_name( $up['path'], $name );
		rename( $tmp, $dest );
		$id = f8_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => $name, 'post_mime_type' => 'svg' === $ext ? 'image/svg+xml' : 'image/' . $ext ), array( '_wp_attached_file' => basename( $dest ) ) );
		return $id;
	}
	function wp_unique_name( $dir, $name ) {
		$base = pathinfo( $name, PATHINFO_FILENAME );
		$ext  = pathinfo( $name, PATHINFO_EXTENSION );
		$try  = $name;
		for ( $i = 1; file_exists( $dir . '/' . $try ); $i++ ) {
			$try = $base . '-' . $i . '.' . $ext;
		}
		return $try;
	}
	function wp_get_attachment_url( $id ) {
		$file = get_post_meta( $id, '_wp_attached_file', true );
		return $file ? wp_upload_dir()['baseurl'] . '/' . $file : false;
	}
	function get_attached_file( $id ) {
		$file = get_post_meta( $id, '_wp_attached_file', true );
		return $file ? wp_upload_dir()['basedir'] . '/' . $file : false;
	}

	require __DIR__ . '/fixtures/f8-wp.php';

	if ( ! class_exists( 'MO', false ) ) {
		/** MO mínimo: el archivo de la prueba es JSON (`original` => `traducción`). */
		class MO {
			private $entries = array();
			public function import_from_file( $file ) {
				$data          = json_decode( (string) file_get_contents( $file ), true );
				$this->entries = is_array( $data ) ? $data : array();
				return is_array( $data );
			}
			public function translate( $text ) {
				return $this->entries[ $text ] ?? $text;
			}
		}
	}

	f8_reset();
	f8_load_migration( array( 'translator', 'context', 'report', 'native' ) );
	require_once dirname( __DIR__ ) . '/digitalisimo-migration/legacy/prime-slider/class-converter.php';

	use Digitalisimo\Elements\Legacy\Context;
	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Prime_Slider\Builder;
	use Digitalisimo\Elements\Legacy\Prime_Slider\Converter;
	use Digitalisimo\Elements\Legacy\Prime_Slider\Defaults;
	use Digitalisimo\Elements\Legacy\Prime_Slider\Demo_Media;
	use Digitalisimo\Elements\Legacy\Prime_Slider\Maps;
	use Digitalisimo\Elements\Legacy\Prime_Slider\Sanitizer;
	use Digitalisimo\Elements\Legacy\Prime_Slider\Static_Families;

	$check = 'f8_check';

	/* ---------------------------------------------------------------- Sistema de archivos simulado */

	function ps_rm( $dir ) {
		if ( is_link( $dir ) || is_file( $dir ) ) {
			unlink( $dir );
			return;
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) as $item ) {
			if ( '.' !== $item && '..' !== $item ) {
				ps_rm( $dir . '/' . $item );
			}
		}
		rmdir( $dir );
	}
	ps_rm( WP_CONTENT_DIR );
	ps_rm( sys_get_temp_dir() . '/ps-e-tmp' );
	$lite_dir = WP_PLUGIN_DIR . '/bdthemes-prime-slider-lite';
	$images   = $lite_dir . '/assets/images';
	mkdir( $images . '/gallery', 0777, true );
	mkdir( $lite_dir . '/assets/js', 0777, true );
	mkdir( WP_LANG_DIR . '/plugins', 0777, true );
	file_put_contents( $lite_dir . '/bdthemes-prime-slider.php', "<?php\n" );
	/** PNG mínimo de 2 × 2 de un color (cada uno distinto: su sha256 también), sin GD: cabecera, datos y fin. */
	function ps_png( $file, $r, $g, $b ) {
		$chunk = static function ( $type, $data ) {
			return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) );
		};
		$row  = "\0" . str_repeat( chr( $r ) . chr( $g ) . chr( $b ), 2 );
		$png  = "\x89PNG\r\n\x1a\n" . $chunk( 'IHDR', pack( 'NNCCCCC', 2, 2, 8, 2, 0, 0, 0 ) ) . $chunk( 'IDAT', gzcompress( $row . $row ) ) . $chunk( 'IEND', '' );
		file_put_contents( $file, $png );
	}
	for ( $i = 1; $i <= 6; $i++ ) {
		ps_png( $images . '/gallery/item-' . $i . '.png', 10 * $i, 20, 30 );
	}
	file_put_contents( $images . '/gallery/item-1.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)" viewBox="0 0 2 2"><script>alert(2)</script><rect width="2" height="2"/></svg>' );
	ps_png( $lite_dir . '/assets/js/fuera.png', 1, 2, 3 );
	ps_png( WP_CONTENT_DIR . '/secreto.png', 4, 5, 6 );
	symlink( WP_CONTENT_DIR . '/secreto.png', $images . '/gallery/enlace.png' );
	file_put_contents( $images . '/falsa.png', 'no soy una imagen' );
	file_put_contents( $images . '/nota.txt', 'texto' );
	$big = fopen( $images . '/grande.png', 'w' );
	fwrite( $big, "\x89PNG\r\n\x1a\n" . str_repeat( "\0", 5 * 1048576 ) );
	fclose( $big );
	f8_plugin( 'bdthemes-prime-slider-lite/bdthemes-prime-slider.php', array( 'Name' => 'Prime Slider', 'Version' => '4.7.0' ), 'site' );
	$assets = plugins_url( 'assets/', $lite_dir . '/bdthemes-prime-slider.php' );
	$check( false !== strpos( $assets, '/plugins/bdthemes-prime-slider-lite/assets/' ), 'URL de los recursos de Lite' );

	/* ---------------------------------------------------------------- Proveedor (de vendors.json, nunca copiado) */

	$vendors = json_decode( file_get_contents( __DIR__ . '/playground/brand-audit/vendors.json' ), true );
	$vendor  = '';
	foreach ( $vendors['hosts'] as $host ) {
		$label = explode( '.', $host )[0];
		foreach ( $vendors['originalSlugs'] as $slug ) {
			if ( 0 === strpos( $slug, $label . '-' ) ) {
				$vendor = $label;
			}
		}
	}
	$check( '' !== $vendor, 'nombre del proveedor deducido de vendors.json' );
	$video = end( $vendors['vendorVideos'] );

	/** Expande los marcadores de los fixtures. */
	function ps_expand( $value ) {
		global $assets, $vendor, $video;
		if ( is_array( $value ) ) {
			return array_map( 'ps_expand', $value );
		}
		if ( ! is_string( $value ) ) {
			return $value;
		}
		$value = str_replace( array( '{{ps_assets}}', '{{demo_video}}', '{{demo_video_file}}' ), array( $assets, 'https://youtu.be/' . $video, '//media.example.test/demo/sample.mp4' ), $value );
		return preg_replace_callback( '/\{\{vendor_profile:([a-z]+)\}\}/', static function ( $m ) use ( $vendor ) {
			return 'http://www.' . $m[1] . '.com/' . $vendor . '/';
		}, $value );
	}

	/** ¿Sin rastro del proveedor? Devuelve lo que encuentra ('' si nada). */
	function ps_trace( $data, $allow_css = false ) {
		global $vendors, $vendor;
		$json = json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( $allow_css ) {
			$json = preg_replace( '/"custom_css":"(?:[^"\\\\]|\\\\.)*"/', '"custom_css":""', $json );
		}
		foreach ( $vendors['hosts'] as $host ) {
			if ( false !== stripos( $json, $host ) ) {
				return $host;
			}
		}
		foreach ( $vendors['vendorVideos'] as $id ) {
			if ( false !== strpos( $json, $id ) ) {
				return $id;
			}
		}
		foreach ( array( '/plugins/bdthemes-prime-slider', $vendor, 'prime-slider-', '_digitalisimo_legacy', 'media.example.test/demo' ) as $needle ) {
			if ( false !== stripos( $json, $needle ) ) {
				return $needle;
			}
		}
		return '';
	}

	/** Convierte un widget en modo `run` y devuelve [resultado, códigos del informe]. */
	function ps_run( $widget, array $settings, $parent = 'container', $post = 0, $id = 'a1b2c3d', $mode = 'run' ) {
		Report::begin( 'editor' === $mode ? 'editor' : 'run' );
		Report::clear();
		Context::begin( $post, $mode );
		try {
			$out = Converter::element( array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $widget, 'settings' => $settings, 'elements' => array() ), $post, $parent );
		} finally {
			Context::end();
		}
		$codes = array();
		foreach ( Converter::$last as $code ) {
			$codes[ $code[0] ][] = $code[1];
		}
		return array( $out, $codes );
	}

	/**
	 * Problemas de estructura de un resultado, como los vería el editor (segunda vuelta de J, P0.C2): IDs no vacíos y
	 * únicos, `elType` conocido, `widgetType` en los widgets, `settings` objeto (array asociativo o vacío) y `elements`
	 * lista; los hijos del slider anidado, contenedores; `isInner` sólo en contenedores.
	 *
	 * @return string[]
	 */
	function ps_structure( $out ) {
		$problems = array();
		$seen     = array();
		$walk     = static function ( array $elements, string $parent ) use ( &$walk, &$problems, &$seen ) {
			if ( array_keys( $elements ) !== range( 0, count( $elements ) - 1 ) && $elements ) {
				$problems[] = 'elements no es una lista bajo ' . $parent;
			}
			foreach ( $elements as $element ) {
				$id   = is_array( $element ) ? (string) ( $element['id'] ?? '' ) : '';
				$type = is_array( $element ) ? (string) ( $element['elType'] ?? '' ) : '';
				if ( '' === $id ) {
					$problems[] = 'elemento sin id bajo ' . $parent;
				} elseif ( isset( $seen[ $id ] ) ) {
					$problems[] = 'id repetido ' . $id;
				}
				$seen[ $id ] = true;
				if ( ! in_array( $type, array( 'widget', 'container', 'section', 'column' ), true ) ) {
					$problems[] = 'elType «' . $type . '» en ' . $id;
				}
				if ( 'widget' === $type && '' === (string) ( $element['widgetType'] ?? '' ) ) {
					$problems[] = 'widget sin widgetType ' . $id;
				}
				$settings = $element['settings'] ?? null;
				if ( ! is_array( $settings ) || ( $settings && array_keys( $settings ) === range( 0, count( $settings ) - 1 ) ) ) {
					$problems[] = 'settings no es un objeto en ' . $id;
				}
				if ( ! isset( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
					$problems[] = 'elements falta en ' . $id;
				}
				if ( ! empty( $element['isInner'] ) && 'container' !== $type && 'section' !== $type ) {
					$problems[] = 'isInner en un ' . $type . ' ' . $id;
				}
				if ( 'digitalisimo-nested-slider' === ( $element['widgetType'] ?? '' ) ) {
					foreach ( (array) ( $element['elements'] ?? array() ) as $child ) {
						if ( 'container' !== ( $child['elType'] ?? '' ) ) {
							$problems[] = 'hijo del slider anidado que no es un contenedor en ' . $id;
						}
					}
				}
				$walk( (array) ( $element['elements'] ?? array() ), $id );
			}
		};
		if ( ! is_array( $out ) ) {
			return array( 'sin resultado' );
		}
		$walk( '__fragment' === ( $out['elType'] ?? '' ) ? (array) $out['elements'] : array( $out ), 'raíz' );
		return $problems;
	}

	/** Elemento principal y hermanos de un resultado. */
	function ps_parts( $out, $id = 'a1b2c3d' ) {
		$parts = '__fragment' === ( $out['elType'] ?? '' ) ? $out['elements'] : array( $out );
		$main  = null;
		foreach ( $parts as $part ) {
			if ( ( $part['id'] ?? '' ) === $id ) {
				$main = $part;
			}
		}
		return array( $main, $parts );
	}

	/** Todos los IDs del árbol. */
	function ps_ids( array $element, array &$ids ) {
		if ( isset( $element['id'] ) ) {
			$ids[] = $element['id'];
		}
		foreach ( (array) ( $element['elements'] ?? array() ) as $child ) {
			ps_ids( $child, $ids );
		}
		foreach ( (array) ( $element['settings'] ?? array() ) as $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $row ) {
					if ( is_array( $row ) && isset( $row['_id'] ) && ! isset( $row['elType'] ) ) {
						$ids[] = 'r:' . $row['_id'];
					}
				}
			}
		}
	}

	/** Widgets del árbol, por tipo. */
	function ps_widgets( array $element, $type ) {
		$out = array();
		if ( ( $element['widgetType'] ?? '' ) === $type ) {
			$out[] = $element;
		}
		foreach ( (array) ( $element['elements'] ?? array() ) as $child ) {
			$out = array_merge( $out, ps_widgets( $child, $type ) );
		}
		return $out;
	}

	/* ================================================================= 1. Los 38 fixtures de E */

	$empty = sys_get_temp_dir() . '/ps-e-empty';
	ps_rm( $empty );
	mkdir( $empty );
	$b_dir = dirname( __DIR__ ) . '/digitalisimo-migration/legacy/prime-slider/defaults';
	$with_b = is_dir( $b_dir ) && glob( $b_dir . '/*.php' );
	Defaults::use_dir( $with_b ? $b_dir : $empty );
	$post = f8_post( array( 'post_title' => 'Portada <b>principal</b>' ) );
	$files = glob( __DIR__ . '/legacy/prime-slider-*.json' );
	$e_fixtures = 0;
	foreach ( $files as $file ) {
		$name = Maps::get( basename( $file, '.json' ) );
		if ( $name && in_array( $name['family'], array( 'E', 'F', 'G', 'L' ), true ) ) {
			continue; // Fixtures del frente F (familias E, F, G y L): su prueba es migration-prime-slider-dynamic.php.
		}
		$fixture = json_decode( file_get_contents( $file ), true );
		$check( is_array( $fixture ) && isset( $fixture['_widget'], $fixture['_family'], $fixture['defaults'], $fixture['custom'], $fixture['styled'] ), basename( $file ) . ': fixture con _widget, _family, defaults, custom y styled' );
		$widget = $fixture['_widget'];
		$family = $fixture['_family'];
		$check( basename( $file, '.json' ) === $widget, basename( $file ) . ': nombre = widget' );
		++$e_fixtures;
		$map = Maps::get( $widget );
		$check( is_array( $map ) && $map['family'] === $family, $widget . ': mapa de la familia ' . $family );
		$check( false === strpos( json_encode( $fixture ), $vendor ) && false === strpos( json_encode( $fixture ), '/plugins/' ), $widget . ': el fixture no trae nada del proveedor ni rutas de plugins' );
		foreach ( array( 'defaults', 'custom', 'styled' ) as $variant ) {
			$label    = $widget . ' (' . $variant . ')';
			$settings = ps_expand( $fixture[ $variant ] );
			Demo_Media::reset();
			list( $out, $codes ) = ps_run( $widget, $settings, 'container', $post );
			if ( 'M' === $family ) {
				$check( is_array( $out ) && 'digitalisimo-unconverted' === $out['widgetType'] && 'migration' === $out['settings']['_digitalisimo_unconverted']['source'], $label . ': descarte declarado en su marcador (source migration)' );
				$check( isset( $codes['ps.dropped.remote'] ), $label . ': ps.dropped.remote' );
				$check( $settings === $out['settings']['_digitalisimo_unconverted']['element']['settings'], $label . ': el marcador conserva el original' );
				continue;
			}
			$check( is_array( $out ), $label . ': convertido' );
			$structure = ps_structure( $out );
			$check( ! $structure, $label . ': estructura que el editor admite (ids únicos, settings objeto, elements lista): ' . implode( '; ', $structure ) );
			list( $main, $parts ) = ps_parts( $out );
			$check( is_array( $main ), $label . ': el principal conserva el ID del original' );
			$expect = array(
				'A' => Builder::NESTED,
				'B' => Builder::NESTED,
				'C' => 'digitalisimo-logos',
				'D' => Builder::NESTED,
				'I' => Builder::NESTED,
				'J' => 'digitalisimo-fancy-tabs',
				'K' => null,
			)[ $family ] ?? null;
			if ( 'H' === $family ) {
				$filled  = Defaults::fill( $widget, $settings );
				$visible = false;
				foreach ( array( 'title', 'text' ) as $part ) {
					$key     = $map['show'][ $part ] ?? '';
					$visible = $visible || ( isset( $map['fields'][ $part ] ) && ( '' === $key || ! array_key_exists( $key, $filled ) || 'yes' === $filled[ $key ] ) );
				}
				$expect = $visible ? 'digitalisimo-logos' : 'media-carousel';
			}
			if ( 'K' === $family ) {
				$check( 'container' === $main['elType'] && true === ( $main['isInner'] ?? null ), $label . ': en un contenedor, un contenedor anidado' );
				$check( count( $main['elements'] ) === count( $settings['slides'] ), $label . ': un contenedor por fila' );
				$check( 'vh' === $main['elements'][0]['settings']['min_height']['unit'] && 100 === $main['elements'][0]['settings']['min_height']['size'], $label . ': cada fila ocupa 100 vh' );
			} else {
				$check( $expect === ( $main['widgetType'] ?? '' ), $label . ': destino ' . $expect . ' (es ' . ( $main['widgetType'] ?? '?' ) . ')' );
			}
			$check( '' === ps_trace( $out, 'custom' === $variant ), $label . ': sin rastro del proveedor (' . ps_trace( $out, 'custom' === $variant ) . ')' );
			$s = (array) $main['settings'];
			$check( ! isset( $s['_skin'] ) && ! isset( $s['_title'] ) && ! isset( $s['_digitalisimo_legacy'] ), $label . ': nunca _skin, _title ni _digitalisimo_legacy en el principal' );
			$ids = array();
			foreach ( $parts as $part ) {
				ps_ids( $part, $ids );
			}
			$check( count( $ids ) === count( array_unique( $ids ) ), $label . ': IDs únicos' );
			$new = array_filter( $ids, static function ( $id ) {
				return 'a1b2c3d' !== $id && 0 !== strpos( $id, 'r:' );
			} );
			$check( count( $new ) === count( preg_grep( '/^[0-9a-f]{7}$/', $new ) ), $label . ': IDs nuevos de 7 cifras hexadecimales' );
			if ( Builder::NESTED === ( $main['widgetType'] ?? '' ) ) {
				$check( count( $main['settings']['slides'] ) === count( $main['elements'] ) && count( $main['elements'] ) > 0, $label . ': tantas filas como contenedores' );
				$inner = true;
				foreach ( $main['elements'] as $child ) {
					$inner = $inner && 'container' === $child['elType'] && true === ( $child['isInner'] ?? null ) && true === ( $child['isLocked'] ?? null ) && 'full' === $child['settings']['content_width'];
				}
				$check( $inner, $label . ': contenedores hijos con isInner, isLocked y ancho completo' );
				$check( in_array( $main['settings']['effect'], array( 'slide', 'fade', 'scale' ), true ) && in_array( $main['settings']['navigation'], array( 'both', 'arrows', 'dots' ), true ), $label . ': efecto y navegación de las listas cerradas' );
			}
			$check( isset( $codes['ps.approx'] ) || isset( $codes['ps.converted'] ), $label . ': informe convertido o aproximado' );
			if ( 'custom' === $variant && 'container' === $main['elType'] ) {
				$check( 'portada' === ( $s['_element_id'] ?? '' ) && 'hero' === ( $s['css_classes'] ?? '' ) && isset( $s['margin'] ) && ! isset( $s['_margin'] ), $label . ': claves comunes con los nombres del contenedor' );
			} elseif ( 'custom' === $variant ) {
				$check( 'portada' === ( $s['_element_id'] ?? '' ) && 'hero' === ( $s['_css_classes'] ?? '' ) && isset( $s['_margin'] ), $label . ': claves comunes en el principal' );
			}
			if ( 'styled' === $variant ) {
				$json = json_encode( $out );
				if ( in_array( $family, array( 'A', 'B', 'D', 'I' ), true ) ) {
					$check( '#abcdef' === ( $s['nav_color'] ?? '' ) && 40 === ( $s['nav_size']['size'] ?? 0 ), $label . ': color y tamaño de las flechas → el slider' );
				}
				if ( isset( $map['fields']['title'] ) && in_array( $family, array( 'A', 'B', 'I' ), true ) ) {
					$heads = ps_widgets( $main, 'heading' );
					$found = false;
					foreach ( $heads as $head ) {
						$found = $found || ( '#123456' === ( $head['settings']['title_color'] ?? '' ) && 42 === ( $head['settings']['typography_font_size']['size'] ?? 0 ) );
					}
					$check( $found, $label . ': color y tipografía del título → heading' );
				}
				$check( false === strpos( $json, 'zz_estilo_sin_equivalente' ), $label . ': lo que no tiene equivalente no pasa al destino' );
			}
			if ( 'custom' === $variant ) {
				$check( isset( $codes['ps.review.custom_css'] ) && false !== strpos( (string) $s['custom_css'], '.bdt-slideshow-item' ), $label . ': CSS propio añadido y marcado para revisar' );
				$check( ! isset( $s['nav_color'] ) && false === strpos( json_encode( $out ), 'display:none' ), $label . ': color malicioso de las flechas descartado' );
				foreach ( $parts as $part ) {
					if ( $part !== $main ) {
						$check( ! isset( $part['settings']['_element_id'] ) && ! isset( $part['settings']['_margin'] ) && 'hidden-mobile' === ( $part['settings']['hide_mobile'] ?? '' ), $label . ': hermano con la visibilidad del original y sin su ID ni sus márgenes' );
					}
				}
			}
		}
	}
	$check( 38 === $e_fixtures, '38 fixtures de las familias de E (hay ' . $e_fixtures . ')' );

	/* ================================================================= 2. Valores por defecto estáticos (C9) */

	$defaults_dir = sys_get_temp_dir() . '/ps-e-defaults';
	ps_rm( $defaults_dir );
	mkdir( $defaults_dir );
	$general_defaults = array(
		'id'        => 'prime-slider-general',
		'plugin'    => 'lite',
		'version'   => '4.7.0',
		'skins'     => array( 'slide', 'crelly', 'meteor' ),
		'controls'  => array(
			'show_title'           => 'yes',
			'show_sub_title'       => 'yes',
			'show_excerpt'         => 'yes',
			'show_button_text'     => 'yes',
			'show_social_icon'     => 'yes',
			'show_scroll_button'   => 'yes',
			'show_navigation_arrows' => 'yes',
			'show_navigation_dots' => 'yes',
			'title_html_tag'       => 'h1',
			'sub_title_html_tag'   => 'h4',
			'slider_animations'    => 'fade',
			'autoplay'             => 'yes',
			'autoplay_interval'    => 7000,
			'pause_on_hover'       => '',
			'finite'               => 'yes',
			'scroll_button_text'   => array( '__t' => 'Scroll Down', 'd' => 'bdthemes-prime-slider-lite' ),
			'section_id'           => 'my-header',
			'overlay'              => 'none',
			'blend_type'           => 'multiply',
			'title_color'          => '',
		),
		'repeaters' => array(
			'slides'           => array(
				'fields' => array(
					'background'        => 'color',
					'color'             => '#193d4c',
					'excerpt'           => 'Texto de relleno por defecto.',
					'slide_button_text' => array( '__t' => 'Read More', 'd' => 'bdthemes-prime-slider-lite' ),
					'button_link'       => array( 'url' => '#', 'is_external' => '', 'nofollow' => '' ),
					'image'             => array( 'url' => '{{ps_assets}}images/gallery/item-4.png' ),
					'video_link'        => '//media.example.test/demo/sample.mp4',
				),
				'rows'   => array(
					array( 'title' => 'Uno' ),
					array( 'title' => 'Dos' ),
					array( 'title' => 'Tres' ),
				),
			),
			'social_link_list' => array(
				'fields' => array(),
				'rows'   => array(),
			),
		),
		'styles'    => array(
			'title_color'      => array( 'type' => 'color', 'selectors' => array() ),
			'title_typography' => array( 'type' => 'typography', 'selectors' => array() ),
			'shape_background_color' => array( 'type' => 'color', 'selectors' => array() ),
		),
	);
	file_put_contents( $defaults_dir . '/prime-slider-general.php', "<?php\nreturn " . var_export( $general_defaults, true ) . ";\n" );
	file_put_contents( $defaults_dir . '/coddle.php', "<?php\nreturn " . var_export( array( 'id' => 'prime-slider-coddle', 'controls' => array( 'show_title' => 'yes', 'show_navigation_fraction' => 'yes', 'show_navigation_arrows' => 'yes', 'swiper_effect' => 'slide', 'autoplay' => 'yes', 'autoplay_speed' => 5000, 'loop' => 'yes', 'speed' => array( 'size' => 900 ) ), 'repeaters' => array( 'slides' => array( 'fields' => array( 'lightbox_link' => array( 'url' => 'https://media.example.test/demo-lightbox' ), 'text' => 'Texto por defecto' ), 'rows' => array( array( 'title' => 'A' ), array( 'title' => 'B' ) ) ) ) ), true ) . ";\n" );
	file_put_contents( $defaults_dir . '/prime-slider-elysium.php', "<?php\nreturn " . var_export( array( 'id' => 'prime-slider-elysium', 'controls' => array( 'show_title' => 'yes', 'show_text' => 'yes', 'show_navigation_arrows' => 'yes', 'slides_per_view' => array( 'size' => 1.8 ) ), 'repeaters' => array( 'slides' => array( 'fields' => array( 'image' => array( 'url' => '{{ps_assets}}images/gallery/item-5.png' ) ), 'rows' => array( array( 'title' => 'E1' ), array( 'title' => 'E2' ) ) ) ) ), true ) . ";\n" );
	Defaults::use_dir( $defaults_dir );
	Defaults::reset();

	// Sin MO instalado: el texto por defecto queda literal y se informa.
	Demo_Media::reset();
	list( $out, $codes ) = ps_run( 'prime-slider-general', array(), 'container', $post );
	list( $main ) = ps_parts( $out );
	$check( 'static' === Defaults::$last && isset( $codes['ps.defaults.static'] ) && isset( $codes['ps.defaults.static'][0]['note'] ), 'estáticos de B con aviso de textos sin traducción' );
	$check( 3 === count( $main['elements'] ) && 'Uno' === $main['settings']['slides'][0]['slide_title'], 'repetidor ausente → sus filas por defecto' );
	$headings = ps_widgets( $main['elements'][0], 'heading' );
	$check( 1 === count( $headings ) && 'Uno' === $headings[0]['settings']['title'] && 'h1' === $headings[0]['settings']['header_size'], 'título por defecto en h1' );
	$check( isset( $codes['ps.h1'] ), 'h1 conservado e informado' );
	$check( 'fade' === $main['settings']['effect'] && 'yes' === $main['settings']['autoplay'] && 7000 === $main['settings']['autoplay_delay'] && 'yes' === $main['settings']['loop'] && '' === $main['settings']['pause_on_hover'], 'efecto, reproducción automática, intervalo, bucle y pausa por defecto' );
	$check( 'both' === $main['settings']['navigation'], 'flechas y puntos por defecto' );
	$check( '#193d4c' === ( $main['elements'][0]['settings']['background_color'] ?? '' ) && 'classic' === $main['elements'][0]['settings']['background_background'], 'fondo de color por defecto de cada fila' );
	$check( ! ps_widgets( $main['elements'][0], 'button' ), 'botón «#» por defecto → sin botón (sólo con texto y enlace)' );
	$check( 'Texto de relleno por defecto.' === ps_widgets( $main['elements'][0], 'text-editor' )[0]['settings']['editor'], 'campo ausente de la fila → su valor por defecto' );
	$check( 'selector .digi-nslider__track{aspect-ratio:16/9;min-height:480px;max-width:100%}' === $main['settings']['custom_css'], 'proporción 16:9 y altura mínima del widget en el CSS del slider, sin desbordar el ancho (max-width, P0.C3)' );
	$check( 'Portada principal' === $main['settings']['carousel_label'], 'nombre accesible ← título del documento, sin etiquetas' );
	$check( ! is_array( $out ) || '__fragment' !== $out['elType'], 'sin redes ni «my-header»: sin hermanos' );
	$check( '' === ps_trace( $out ), 'estáticos: sin rastro del proveedor' );

	// Con MO: el texto por defecto se traduce.
	file_put_contents( WP_LANG_DIR . '/plugins/bdthemes-prime-slider-lite-es_MX.mo', json_encode( array( 'Read More' => 'Leer más', 'Scroll Down' => 'Bajar' ) ) );
	Defaults::reset();
	list( $out, $codes ) = ps_run( 'prime-slider-general', array( 'slides' => array( array( '_id' => 'x1', 'title' => 'T', 'button_link' => array( 'url' => '/tienda/' ) ) ), 'section_id' => 'contacto' ), 'container', $post );
	list( $main, $parts ) = ps_parts( $out );
	$buttons = ps_widgets( $main, 'button' );
	$check( 1 === count( $buttons ) && 'Leer más' === $buttons[0]['settings']['text'] && '/tienda/' === $buttons[0]['settings']['link']['url'], '__t traducido con el MO del plugin en WP_LANG_DIR/plugins' );
	$check( ! isset( $codes['ps.defaults.static'][0]['note'] ), 'con traducción: sin el aviso de textos literales' );
	$scroll = ps_widgets( $parts[1] ?? array(), 'digitalisimo-scroll-button' );
	$check( 1 === count( $scroll ) && 'Bajar' === $scroll[0]['settings']['scroll_button_text'] && 'contacto' === $scroll[0]['settings']['section_id'], '«Scroll Down» visible con su sección → hermano traducido' );
	$check( 'en_US' === ( $GLOBALS['ps_locale'] = 'en_US' ) && 'en_US' === determine_locale(), 'idioma cambiado a inglés' );
	Defaults::reset();
	list( $out ) = ps_run( 'prime-slider-general', array( 'slides' => array( array( '_id' => 'x1', 'title' => 'T', 'button_link' => array( 'url' => '/t/' ) ) ) ), 'container', $post );
	$check( 'Read More' === ps_widgets( ps_parts( $out )[0], 'button' )[0]['settings']['text'], 'en inglés el texto queda tal cual' );
	$GLOBALS['ps_locale'] = 'es_MX';
	Defaults::reset();

	// {{ps_assets}}: con Lite instalado, la imagen por defecto de la carpeta del plugin se copia a Medios.
	Demo_Media::reset();
	list( $out, $codes ) = ps_run( 'prime-slider-general', array( 'slides' => array( array( '_id' => 'y1', 'title' => 'Con imagen', 'background' => 'image' ) ) ), 'container', $post );
	list( $main ) = ps_parts( $out );
	$bg = $main['elements'][0]['settings']['background_image'] ?? array();
	$check( ! empty( $bg['id'] ) && false !== strpos( $bg['url'], '/uploads/' ) && false === strpos( $bg['url'], '/plugins/' ), '{{ps_assets}} → copia en Medios con {id, url}' );
	$check( isset( $codes['ps.demo.copied'] ) && 1 === $codes['ps.demo.copied'][0]['count'], 'ps.demo.copied con el recuento' );
	$check( 'cover' === $main['elements'][0]['settings']['background_size'] && 'classic' === $main['elements'][0]['settings']['background_background'], 'fondo de imagen cubre el contenedor' );

	// Sin Lite instalado: {{ps_assets}} se vacía.
	$plugins_backup = $GLOBALS['wp']['plugins'];
	$GLOBALS['wp']['plugins'] = array();
	Defaults::reset();
	Demo_Media::reset();
	list( $out ) = ps_run( 'prime-slider-general', array( 'slides' => array( array( '_id' => 'y1', 'title' => 'Sin plugin', 'background' => 'image' ) ) ), 'container', $post );
	list( $main ) = ps_parts( $out );
	$check( empty( $main['elements'][0]['settings']['background_image'] ) && '' === ps_trace( $out ), 'sin Lite instalado: {{ps_assets}} vacío y sin imagen' );
	$GLOBALS['wp']['plugins'] = $plugins_backup;
	Defaults::reset();

	// Vídeo de demostración por la URL completa del valor por defecto; fila editada con YouTube del proveedor.
	list( $out, $codes ) = ps_run( 'prime-slider-general', array( 'slides' => array(
		array( '_id' => 'v1', 'title' => 'Vídeo', 'background' => 'video', 'video_link' => '//media.example.test/demo/sample.mp4' ),
		array( '_id' => 'v2', 'title' => 'YouTube', 'background' => 'youtube', 'youtube_link' => 'https://www.youtube.com/watch?v=' . $video ),
		array( '_id' => 'v3', 'title' => 'Propio', 'background' => 'video', 'video_link' => 'https://videos.example.test/mio.mp4' ),
	) ), 'container', $post );
	list( $main ) = ps_parts( $out );
	$check( ! isset( $main['elements'][0]['settings']['background_video_link'] ) && ! isset( $main['elements'][1]['settings']['background_video_link'] ), 'vídeos de demostración (por la URL del valor por defecto y por la huella) → sin fondo de vídeo' );
	$check( 'video' === $main['elements'][2]['settings']['background_background'] && 'https://videos.example.test/mio.mp4' === $main['elements'][2]['settings']['background_video_link'], 'vídeo propio → fondo de vídeo del contenedor' );
	$check( 3 === $codes['ps.vendor_removed'][0]['count'], 'ps.vendor_removed con el recuento (también el vídeo por defecto que rellenó la fila de YouTube)' );
	$check( '' === ps_trace( $out ), 'vídeos: sin rastro del proveedor' );

	// Estilos sin equivalente y estilos que pasan a los hijos.
	list( $out, $codes ) = ps_run( 'prime-slider-general', array(
		'slides'                       => array( array( '_id' => 's1', 'title' => 'T', 'sub_title' => 'S', 'excerpt' => 'E' ) ),
		'title_color'                  => '#112233',
		'title_typography_typography'  => 'custom',
		'title_typography_font_size'   => array( 'unit' => 'px', 'size' => 40 ),
		'sub_title_color'              => 'var(--e-global-color-accent)',
		'excerpt_color'                => 'rgb(1, 2, 3)',
		'shape_background_color'       => '#445566',
		'__globals__'                  => array( 'title_color' => 'globals/colors?id=primary' ),
	), 'container', $post );
	list( $main ) = ps_parts( $out );
	$h = ps_widgets( $main, 'heading' );
	$check( 'S' === $h[0]['settings']['title'] && 'var(--e-global-color-accent)' === $h[0]['settings']['title_color'] && 'h4' === $h[0]['settings']['header_size'], 'estilo del subtítulo → su encabezado' );
	$check( '#112233' === $h[1]['settings']['title_color'] && 40 === $h[1]['settings']['typography_font_size']['size'] && 'custom' === $h[1]['settings']['typography_typography'], 'color y tipografía del título → heading' );
	$check( 'globals/colors?id=primary' === $h[1]['settings']['__globals__']['title_color'], 'global del color del título conservado' );
	$check( 'rgb(1, 2, 3)' === ps_widgets( $main, 'text-editor' )[0]['settings']['text_color'], 'color del extracto → text-editor' );
	$check( 1 === ( $codes['ps.loss.styles'][0]['count'] ?? 0 ), 'estilo sin equivalente contado (ps.loss.styles)' );
	$check( 1 === count( $codes['ps.loss.feature'] ) && 6 === $codes['ps.loss.feature'][0]['count'] && false !== strpos( $codes['ps.loss.feature'][0]['note'], 'paralaje del contenido; ' ), 'diferencias declaradas en una sola anotación con su lista' );

	/* ================================================================= 3. Widget registrado con Prime Slider cargado */

	// (Al final de la prueba: define BDTPS_CORE_VER y el núcleo simulado.)

	/* ================================================================= 4. Lista blanca de claves comunes */

	$from = array(
		'_margin' => array( 'top' => '1' ), '_margin_tablet' => array( 'top' => '2' ), '_padding' => array(), '_z_index' => 3, '_element_width' => 'initial',
		'_element_custom_width' => array( 'size' => 50 ), '_flex_size' => 'grow', '_position' => 'absolute', '_offset_x' => array( 'size' => 1 ),
		'_background_background' => 'classic', '_border_border' => 'solid', '_box_shadow_box_shadow_type' => 'yes', '_animation' => 'fadeIn',
		'animation_duration' => 'fast', '_transform_rotateZ_effect' => array( 'size' => 5 ), 'hide_desktop' => 'hidden-desktop', 'hide_tablet' => '',
		'hide_mobile' => 'hidden-mobile', '_attributes' => 'data-x|1', '_mask_switch' => 'yes', 'motion_fx_motion_fx_scrolling' => 'yes',
		'sticky' => 'top', 'sticky_on' => array( 'desktop' ), 'custom_css' => 'selector{color:red}', '_element_id' => 'mi-id', '_css_classes' => 'mi-clase',
		'_skin' => 'crelly', '_title' => 'Navegador', '_digitalisimo_legacy' => 'x', 'title_color' => '#fff', '_wrapper_link' => 'x', 'slides' => array(),
		'__globals__' => array( '_background_color' => 'globals/colors?id=a', 'title_color' => 'globals/colors?id=b', '_skin' => 'x' ),
		'__dynamic__' => array( '_attributes' => '[elementor-tag]', 'title' => '[x]' ),
	);
	$main_common = Builder::common( $from, true );
	$sib_common  = Builder::common( $from, false );
	foreach ( array( '_margin', '_margin_tablet', '_padding', '_z_index', '_element_width', '_element_custom_width', '_flex_size', '_position', '_offset_x', '_background_background', '_border_border', '_box_shadow_box_shadow_type', '_animation', 'animation_duration', '_transform_rotateZ_effect', 'hide_desktop', 'hide_tablet', 'hide_mobile', '_attributes', '_mask_switch', 'motion_fx_motion_fx_scrolling', 'sticky', 'sticky_on', 'custom_css' ) as $key ) {
		$check( array_key_exists( $key, $main_common ) && array_key_exists( $key, $sib_common ), 'lista blanca: ' . $key );
	}
	$check( 'mi-id' === $main_common['_element_id'] && 'mi-clase' === $main_common['_css_classes'] && ! isset( $sib_common['_element_id'] ) && ! isset( $sib_common['_css_classes'] ), '_element_id y _css_classes sólo en el principal' );
	foreach ( array( '_skin', '_title', '_digitalisimo_legacy', 'title_color', '_wrapper_link', 'slides' ) as $key ) {
		$check( ! array_key_exists( $key, $main_common ), 'fuera de la lista blanca: ' . $key );
	}
	$check( array( '_background_color' => 'globals/colors?id=a' ) === $main_common['__globals__'] && array( '_attributes' => '[elementor-tag]' ) === $main_common['__dynamic__'], '__globals__ y __dynamic__ sólo de las claves de la lista' );

	/* ================================================================= 5. Hermanos: redes y «Scroll Down» */

	list( $out, $codes ) = ps_run( 'prime-slider-general', ps_expand( array(
		'slides'             => array( array( '_id' => 'r1', 'title' => 'Redes' ) ),
		'show_social_icon'   => 'yes',
		'social_link_list'   => array(
			array( '_id' => 'a', 'social_icon_link' => array( 'url' => '{{vendor_profile:facebook}}' ), 'social_icon' => array( 'value' => 'fab fa-facebook-f', 'library' => 'fa-brands' ) ),
			array( '_id' => 'b', 'social_icon_link' => array( 'url' => 'https://es-la.facebook.com/' . $vendor ) ),
			array( '_id' => 'c', 'social_icon_link' => array( 'url' => 'https://www.linkedin.com/company/' . $vendor . '/' ) ),
			array( '_id' => 'd', 'social_icon_link' => array( 'url' => 'https://' . $vendor . '.com/contacto' ) ),
			array( '_id' => 'e', 'social_icon_link' => array( 'url' => 'https://instagram.com/mi-tienda' ) ),
			array( '_id' => 'f', 'social_icon_link' => array( 'url' => 'https://x.com/mitienda' ), 'social_link_title' => 'X' ),
		),
		'show_scroll_button' => 'yes',
		'section_id'         => '#servicios',
		'scroll_button_text' => 'Bajar',
		'duration'           => array( 'size' => 800, 'unit' => 'px' ),
	) ), 'container', $post );
	list( $main, $parts ) = ps_parts( $out );
	$check( '__fragment' === $out['elType'] && 3 === count( $parts ) && $parts[0] === $main, 'fragmento: slider, redes y botón de desplazamiento' );
	$social = $parts[1];
	$check( 'social-icons' === $social['widgetType'] && 2 === count( $social['settings']['social_icon_list'] ), 'redes: sólo las que no son del proveedor (subdominio, /company/ y su dominio, fuera)' );
	$check( 'fab fa-instagram' === $social['settings']['social_icon_list'][0]['social_icon']['value'] && 'fab fa-x-twitter' === $social['settings']['social_icon_list'][1]['social_icon']['value'], 'icono deducido del dominio cuando la fila no tiene' );
	$check( 'digitalisimo-scroll-button' === $parts[2]['widgetType'] && 'servicios' === $parts[2]['settings']['section_id'] && 800 === $parts[2]['settings']['duration']['size'], '«Scroll Down» → botón de desplazamiento con la sección sin #' );
	$check( isset( $codes['ps.siblings'] ) && 5 === $codes['ps.vendor_removed'][0]['count'], 'hermanos informados; 4 enlaces del proveedor y el vídeo por defecto retirados' );
	$check( '' === ps_trace( $out ), 'redes: sin rastro del proveedor' );
	list( $out ) = ps_run( 'prime-slider-general', array( 'slides' => array( array( '_id' => 'r1', 'title' => 'x' ) ), 'show_scroll_button' => 'yes', 'section_id' => 'my-header', 'show_social_icon' => '', 'social_link_list' => array( array( 'social_icon_link' => array( 'url' => 'https://instagram.com/mia' ) ) ) ), 'container', $post );
	$check( Builder::NESTED === $out['widgetType'], '«my-header» por defecto y redes ocultas → sin hermanos' );
	list( $out ) = ps_run( 'prime-slider-general', array( 'slides' => array( array( '_id' => 'r1', 'title' => 'x' ) ), 'show_scroll_button' => 'yes', 'section_id' => 'a"><script>' ), 'container', $post );
	$check( Builder::NESTED === $out['widgetType'], 'ID de sección no válido → sin botón de desplazamiento' );
	// En el editor (pureza): sólo el principal.
	list( $out ) = ps_run( 'prime-slider-general', array( 'slides' => array( array( '_id' => 'r1', 'title' => 'x' ) ), 'show_scroll_button' => 'yes', 'section_id' => 'contacto' ), 'container', $post, 'a1b2c3d', 'editor' );
	$check( Builder::NESTED === $out['widgetType'] && 'a1b2c3d' === $out['id'], 'editor: sólo el principal, sin fragmento' );

	/* ================================================================= 6. Ajustes del slider */

	$cases = array(
		array( 'prime-slider-general', array( 'slider_animations' => 'push' ), 'slide' ),
		array( 'prime-slider-general', array( 'slider_animations' => 'scale' ), 'scale' ),
		array( 'prime-slider-general', array( 'slider_animations' => 'fade' ), 'fade' ),
		array( 'prime-slider-omatic', array( 'swiper_effect' => 'cube' ), 'slide' ),
		array( 'prime-slider-omatic', array( 'swiper_effect' => 'coverflow' ), 'slide' ),
		array( 'prime-slider-titanic', array( 'swiper_effect' => 'cards' ), 'fade' ),
		array( 'prime-slider-coddle', array( 'swiper_effect' => 'gl' ), 'fade' ),
		array( 'prime-slider-escape', array( 'swiper_effect' => 'creative' ), 'fade' ),
		array( 'prime-slider-fortune', array( 'swiper_effect' => 'fade' ), 'fade' ),
		array( 'prime-slider-isolate', array( 'slider_animations' => 'scale' ), 'fade' ),
	);
	foreach ( $cases as $case ) {
		list( $out, $codes ) = ps_run( $case[0], $case[1] + array( 'slides' => array( array( '_id' => 'e1', 'title' => 'x' ), array( '_id' => 'e2', 'title' => 'y' ) ) ), 'container', $post );
		$check( $case[2] === $out['settings']['effect'], $case[0] . ' ' . json_encode( $case[1] ) . ' → ' . $case[2] );
	}
	list( $out, $codes ) = ps_run( 'prime-slider-omatic', array( 'swiper_effect' => 'cube', 'speed' => array( 'size' => 700 ) ), 'container', $post );
	$check( isset( $codes['ps.loss.effect'] ) && ! isset( $out['settings']['speed'] ), 'cubo → deslizar, informado; sin velocidad con deslizar' );
	list( $out ) = ps_run( 'prime-slider-titanic', array( 'swiper_effect' => 'fade', 'speed' => array( 'size' => 9000 ) ), 'container', $post );
	$check( 3000 === $out['settings']['speed'], 'velocidad acotada a 3000 ms con fundido' );

	list( $out, $codes ) = ps_run( 'prime-slider-general', array( 'autoplay' => 'yes', 'autoplay_interval' => 500, 'pause_on_hover' => 'yes', 'finite' => '' ), 'container', $post );
	$check( 2000 === $out['settings']['autoplay_delay'] && isset( $codes['ps.loss.delay_clamped'] ), 'intervalo de 500 ms → 2000, informado' );
	$check( 'yes' === $out['settings']['pause_on_hover'] && '' === $out['settings']['loop'], 'pausa al pasar y sin bucle (finite vacío)' );
	list( $out ) = ps_run( 'prime-slider-general', array( 'autoplay' => 'yes', 'autoplay_interval' => 60000 ), 'container', $post );
	$check( 20000 === $out['settings']['autoplay_delay'], 'intervalo de 60 s → 20 s' );
	list( $out ) = ps_run( 'prime-slider-avatar', array( 'autoplay' => 'no', 'autoplay_speed' => 4000, 'loop' => 'yes' ), 'container', $post );
	$check( '' === $out['settings']['autoplay'] && 4000 === $out['settings']['autoplay_delay'] && 'yes' === $out['settings']['loop'] && 'slide' === $out['settings']['effect'], 'avatar: «no» cuenta como apagado; bucle y deslizar' );

	list( $out ) = ps_run( 'prime-slider-coddle', array( 'show_navigation_fraction' => 'yes', 'show_navigation_arrows' => '', 'show_navigation_dots' => '' ), 'container', $post );
	$check( 'yes' === $out['settings']['counter'] && 'arrows' === $out['settings']['navigation'], 'fracción → contador; sin puntos → flechas' );
	list( $out ) = ps_run( 'prime-slider-coddle', array( 'show_navigation_fraction' => 'yes', 'show_navigation_arrows' => 'yes', 'show_navigation_dots' => 'yes' ), 'container', $post );
	$check( 'yes' === $out['settings']['counter'] && 'both' === $out['settings']['navigation'], 'fracción con flechas y puntos → contador y ambos' );
	list( $out ) = ps_run( 'prime-slider-escape', array( 'show_navigation_fraction' => '', 'show_navigation_arrows' => 'yes' ), 'container', $post );
	$check( ! isset( $out['settings']['counter'] ) && 'arrows' === $out['settings']['navigation'], 'sin fracción no hay contador (el control nace sin valor)' );
	list( $out, $codes ) = ps_run( 'prime-slider-sequester', array( 'show_navigation_arrows' => '', 'show_navigation_dots' => '', 'slides' => array( array( '_id' => 'n1', 'title' => 'a' ), array( '_id' => 'n2', 'title' => 'b' ) ) ), 'container', $post );
	$check( 'dots' === $out['settings']['navigation'] && isset( $codes['ps.loss.nav_none'] ), 'sin flechas ni puntos → puntos, informado' );
	list( $out ) = ps_run( 'prime-slider-custom', array( 'show_navigation_arrows' => 'yes', 'show_navigation_dots' => 'yes', 'arrows_prev_position' => 'center-left', 'arrows_next_position' => 'center-right' ), 'container', $post );
	$check( 'sides' === $out['settings']['arrows_position'], 'flechas centradas a los lados → sides' );
	list( $out ) = ps_run( 'prime-slider-custom', array( 'show_navigation_arrows' => 'yes', 'arrows_prev_position' => 'bottom-left', 'arrows_next_position' => 'center-right' ), 'container', $post );
	$check( ! isset( $out['settings']['arrows_position'] ), 'flechas en otra posición → barra (el valor por defecto del destino)' );

	list( $out ) = ps_run( 'prime-slider-dragon', array( 'enable_height' => 'yes', 'viewport_height' => array( 'unit' => 'vh', 'size' => 70 ), 'viewport_height_tablet' => array( 'unit' => 'px', 'size' => 500 ), 'viewport_height_mobile' => array( 'unit' => 'bogus', 'size' => 300 ) ), 'container', $post );
	$check( 70 === $out['settings']['slider_height']['size'] && 'vh' === $out['settings']['slider_height']['unit'] && 500 === $out['settings']['slider_height_tablet']['size'] && 'px' === $out['settings']['slider_height_mobile']['unit'] && ! isset( $out['settings']['custom_css'] ), 'altura en vh responsiva (unidad desconocida → px), sin proporción' );
	list( $out ) = ps_run( 'prime-slider-dragon', array( 'slider_size_ratio' => array( 'width' => '4', 'height' => '3' ), 'slider_min_height' => array( 'size' => 320 ) ), 'container', $post );
	$check( 'selector .digi-nslider__track{aspect-ratio:4/3;min-height:320px;max-width:100%}' === $out['settings']['custom_css'], 'proporción y altura mínima guardadas → CSS del slider' );
	list( $out ) = ps_run( 'prime-slider-dragon', array( 'slider_size_ratio' => array( 'width' => '4;}body{x', 'height' => '3' ) ), 'container', $post );
	$check( 'selector .digi-nslider__track{aspect-ratio:4/3;min-height:440px;max-width:100%}' === $out['settings']['custom_css'], 'proporción con texto malicioso → número entero; altura mínima del widget (440)' );
	list( $out ) = ps_run( 'prime-slider-sniper', array( 'item_height' => array( 'unit' => 'px', 'size' => 600 ), 'item_height_mobile' => array( 'unit' => 'px', 'size' => 300 ) ), 'container', $post );
	$check( 600 === $out['settings']['slider_height']['size'] && 300 === $out['settings']['slider_height_mobile']['size'], 'item_height → slider_height responsivo' );

	list( $out ) = ps_run( 'prime-slider-general', array(
		'arrows_color'                  => '#ff0000',
		'arrows_background'             => 'red;}body{display:none',
		'title_color'                   => 'expression(alert(1))',
		'sub_title_color'               => 'url(https://example.test/x.png)',
		'title_typography_font_family'  => 'Roboto;}</style><script>',
		'slides'                        => array( array( '_id' => 'm1', 'title' => 'T', 'sub_title' => 'S' ) ),
	), 'container', $post );
	$json = json_encode( $out );
	$check( '#ff0000' === $out['settings']['nav_color'] && ! isset( $out['settings']['nav_background'] ), 'color de flechas válido pasa; fondo malicioso no' );
	list( $out ) = ps_run( 'prime-slider-general', array( 'title_typography_font_size' => array( 'unit' => 'px', 'size' => '1;}body{display:none' ), 'title_typography_font_weight' => '700', 'excerpt_typography_line_height' => array( 'unit' => 'em', 'size' => 1.5 ), 'slides' => array( array( '_id' => 'm1', 'title' => '<img src=x onerror=alert(1)><b>Hola</b>', 'excerpt' => '<p onclick="x()">Texto</p><script>alert(3)</script>' ) ) ), 'container', $post );
	$h = ps_widgets( $out, 'heading' );
	$check( ! isset( $h[0]['settings']['typography_font_size'] ) && '700' === $h[0]['settings']['typography_font_weight'] && 1.5 === ps_widgets( $out, 'text-editor' )[0]['settings']['typography_line_height']['size'], 'medidas con texto malicioso descartadas; las válidas pasan' );
	$json = json_encode( $out );
	$check( false === stripos( $json, 'onerror' ) && false === stripos( $json, 'onclick' ) && false === stripos( $json, '<script' ) && false !== strpos( $json, '<b>Hola<\/b>' ), 'textos con el HTML de una entrada (sin manejadores ni scripts)' );
	$check( false === strpos( $json, 'expression(' ) && false === strpos( $json, 'url(' ) && false === strpos( $json, '<script' ), 'colores y tipografías maliciosos descartados (Css_Value)' );

	/* ================================================================= 7. Partes ocultas y alineación */

	list( $out ) = ps_run( 'prime-slider-general', array(
		'show_title'        => '',
		'show_sub_title'    => 'yes',
		'content_alignment' => 'right',
		'text_hide_on'      => array( 'mobile' ),
		'slides'            => array( array( '_id' => 'o1', 'title' => 'Oculto', 'sub_title' => 'Visible', 'excerpt' => 'Texto', 'slide_button_text' => 'Ir', 'button_link' => array( 'url' => '/ir/' ) ) ),
	), 'container', $post );
	$child = $out['elements'][0];
	$h     = ps_widgets( $child, 'heading' );
	$check( 'hidden-desktop' === $h[1]['settings']['hide_desktop'] && 'hidden-tablet' === $h[1]['settings']['hide_tablet'] && 'hidden-mobile' === $h[1]['settings']['hide_mobile'], 'título oculto en origen: se genera igual, oculto' );
	$check( ! isset( $h[0]['settings']['hide_desktop'] ), 'subtítulo visible sin claves de ocultar' );
	$check( 'hidden-mobile' === ps_widgets( $child, 'text-editor' )[0]['settings']['hide_mobile'] && ! isset( ps_widgets( $child, 'text-editor' )[0]['settings']['hide_desktop'] ), 'text_hide_on → ocultar sólo en ese dispositivo' );
	$check( 'flex-end' === $child['settings']['flex_align_items'] && 'end' === $h[0]['settings']['align'] && 'right' === ps_widgets( $child, 'button' )[0]['settings']['align'], 'alineación a la derecha → contenedor, encabezados (end) y botón (right)' );
	$check( array( 'Visible', 'Oculto', 'Texto', 'Ir' ) === array( $h[0]['settings']['title'], $h[1]['settings']['title'], ps_widgets( $child, 'text-editor' )[0]['settings']['editor'], ps_widgets( $child, 'button' )[0]['settings']['text'] ), 'orden de origen: subtítulo, título, texto y botón' );
	$check( 'Diapositiva n.º 1' === $child['settings']['_title'] && 'column' === $child['settings']['flex_direction'] && 'center' === $child['settings']['flex_justify_content'], 'contenedor: título, columna y centrado' );

	list( $out ) = ps_run( 'prime-slider-general', array( 'overlay' => 'blend', 'overlay_color' => '#00000080', 'blend_type' => 'screen', 'slides' => array( array( '_id' => 'b1', 'title' => 't' ) ) ), 'container', $post );
	$c = $out['elements'][0]['settings'];
	$check( 'classic' === $c['background_overlay_background'] && '#00000080' === $c['background_overlay_color'] && 'screen' === $c['overlay_blend_mode'], 'superposición con mezcla → superposición del contenedor' );
	list( $out ) = ps_run( 'prime-slider-general', array( 'overlay' => 'blend', 'overlay_color' => '#000', 'blend_type' => 'evil;}', 'slides' => array( array( '_id' => 'b1', 'title' => 't' ) ) ), 'container', $post );
	$check( ! isset( $out['elements'][0]['settings']['overlay_blend_mode'] ), 'modo de mezcla fuera de la lista → sin mezcla' );

	list( $out ) = ps_run( 'prime-slider-isolate', array( 'show_play_button' => 'yes', 'slides' => array(
		array( '_id' => 'p1', 'title' => 'Vídeo', 'image_link_type' => 'youtube', 'lightbox_link' => array( 'url' => 'https://www.youtube.com/watch?v=propio123' ), 'image' => array( 'url' => 'https://s1.test/wp-content/uploads/a.jpg', 'id' => 5 ) ),
		array( '_id' => 'p2', 'title' => 'Mapa', 'image_link_type' => 'google_map', 'image_link_google_map' => array( 'url' => 'https://maps.example.test/mi-local' ) ),
		array( '_id' => 'p3', 'title' => 'Demo', 'image_link_type' => 'youtube', 'lightbox_link' => array( 'url' => 'https://youtu.be/' . $video ) ),
	) ), 'container', $post );
	$b0 = ps_widgets( $out['elements'][0], 'button' );
	$check( 1 === count( $b0 ) && 'Reproducir el vídeo' === $b0[0]['settings']['text'] && 'fas fa-play' === $b0[0]['settings']['selected_icon']['value'], 'botón de reproducción → botón con texto e icono' );
	$check( 'Ver el mapa' === ps_widgets( $out['elements'][1], 'button' )[0]['settings']['text'], 'mapa → «Ver el mapa»' );
	$check( ! ps_widgets( $out['elements'][2], 'button' ), 'vídeo de demostración → sin botón' );
	$check( 1 === count( ps_widgets( $out['elements'][0], 'image' ) ) && 5 === ps_widgets( $out['elements'][0], 'image' )[0]['settings']['image']['id'], 'isolate: la imagen delante, como widget Imagen' );

	/* ================================================================= 8. Familia C: destino antes de las imágenes (C1-29) */

	Demo_Media::reset();
	$elysium = ps_expand( array( 'slides' => array(
		array( '_id' => 'e1', 'title' => 'Uno', 'image' => array( 'url' => '{{ps_assets}}images/gallery/item-6.png' ), 'text' => '<p>Hola <b>mundo</b></p>', 'title_link' => array( 'url' => '/uno/' ) ),
		array( '_id' => 'e2', 'title' => 'Dos', 'image' => array( 'url' => '{{ps_assets}}images/gallery/no-existe.png' ) ),
	) ) );
	list( $out, $codes ) = ps_run( 'prime-slider-elysium', $elysium, 'container', $post, 'a1b2c3d', 'editor' );
	$check( 'digitalisimo-logos' === $out['widgetType'], 'familia C: logotipos aunque las imágenes no se copien (decisión antes de resolverlas)' );
	$check( '' === $out['settings']['items'][0]['image']['url'] && '' === $out['settings']['items'][1]['image']['url'], 'editor: no copia (imágenes vacías)' );
	$check( 0 === Demo_Media::$copied, 'editor: ninguna copia' );
	list( $out, $codes ) = ps_run( 'prime-slider-elysium', $elysium, 'container', $post );
	$items = $out['settings']['items'];
	$check( 'carousel' === $out['settings']['layout'] && '2' === $out['settings']['columns'], 'carrusel de logotipos; 1,8 por vista → 2 columnas' );
	$check( 'Uno' === $items[0]['name'] && 'Hola mundo' === $items[0]['description'] && '/uno/' === $items[0]['link']['url'], 'nombre ← título, descripción ← texto plano, enlace ← enlace del título' );
	$check( ! empty( $items[0]['image']['id'] ) && '' === $items[1]['image']['url'] && isset( $codes['ps.demo.skipped'] ), 'imagen copiada; la que no existe queda vacía y se informa' );
	list( $out ) = ps_run( 'prime-slider-tango', array( 'columns' => '3', 'columns_tablet' => '2', 'columns_mobile' => '9', 'item_gap' => array( 'size' => 40 ), 'slides' => array( array( '_id' => 't1', 'title' => 'a', 'image' => array( 'url' => 'https://s1.test/wp-content/uploads/a.jpg', 'id' => 1 ) ) ) ), 'container', $post );
	$check( '3' === $out['settings']['columns'] && '2' === $out['settings']['columns_tablet'] && '6' === $out['settings']['columns_mobile'] && 40 === $out['settings']['gap']['size'], 'columnas responsivas (acotadas a 6) y separación' );
	list( $out ) = ps_run( 'prime-slider-tango', array( 'slides' => array( array( '_id' => 't1', 'title' => 'a', 'image' => array( 'url' => 'https://s1.test/wp-content/uploads/a.jpg', 'id' => 1 ) ), array( '_id' => 't2', 'title' => 'b' ) ) ), 'container', $post );
	$check( Builder::NESTED === $out['widgetType'] && 2 === count( $out['elements'] ), 'una fila sin imagen → slider anidado de una por vista' );

	/* ================================================================= 9. Familias H, J, K y D */

	list( $out ) = ps_run( 'prime-slider-panorama', array( 'show_title' => '', 'show_text' => '', 'autoplay' => '', 'loop' => 'yes', 'slides' => array( array( '_id' => 'p1', 'slide_image' => array( 'url' => 'https://s1.test/wp-content/uploads/p.jpg', 'id' => 9 ), 'slide_title' => 'No visible', 'slide_link' => array( 'url' => '/p/' ) ) ) ), 'container', $post );
	$check( 'media-carousel' === $out['widgetType'] && 'coverflow' === $out['settings']['skin'] && 9 === $out['settings']['slides'][0]['image']['id'] && 'custom' === $out['settings']['slides'][0]['image_link_to_type'] && '' === $out['settings']['autoplay'] && 'yes' === $out['settings']['loop'], 'panorama sin textos visibles → carrusel multimedia coverflow con enlace; reproducción apagada escrita' );
	list( $out ) = ps_run( 'prime-slider-cards-stack', ps_expand( array( 'slides' => array( array( '_id' => 'c1', 'image_front' => array( 'url' => 'https://s1.test/wp-content/uploads/f.jpg', 'id' => 7 ), 'image_back' => array( 'url' => '{{ps_assets}}images/gallery/item-3.png' ) ) ) ) ), 'container', $post );
	$check( 'media-carousel' === $out['widgetType'] && 'carousel' === $out['settings']['skin'] && false === strpos( json_encode( $out ), 'item-3' ), 'cards-stack: cara delantera; la trasera ni se copia' );
	list( $out ) = ps_run( 'prime-slider-pandora', array( 'hover_active_item' => '2', 'items' => array( array( '_id' => 'p1', 'title' => 'Uno', 'sub_title' => 'Sub', 'image' => array( 'url' => 'https://s1.test/wp-content/uploads/u.jpg', 'id' => 3 ), 'title_link' => array( 'url' => '/u/' ) ), array( '_id' => 'p2', 'title' => 'Dos' ) ) ), 'container', $post );
	$tabs = $out['settings']['tabs'];
	$check( 'digitalisimo-fancy-tabs' === $out['widgetType'] && 'Uno' === $tabs[0]['tab_title'] && 'Sub' === $tabs[0]['tab_sub_title'] && 'none' === $tabs[0]['icon_type'], 'pandora → pestañas: título y subtítulo' );
	$check( false !== strpos( $tabs[0]['tab_content'], '<img src="https://s1.test/wp-content/uploads/u.jpg" alt="" class="wp-image-3" />' ) && '/u/' === $tabs[0]['button_link']['url'] && 'yes' === ( $tabs[1]['active'] ?? '' ), 'panel con la imagen, botón al enlace y pestaña activa' );
	list( $out ) = ps_run( 'prime-slider-pagepiling', array( 'slides' => array( array( '_id' => 'g1', 'title' => 'A', 'slide_image' => array( 'url' => 'https://s1.test/wp-content/uploads/g.jpg', 'id' => 4 ) ) ) ), 'column', $post );
	$check( Builder::NESTED === $out['widgetType'] && 4 === $out['elements'][0]['settings']['background_image']['id'], 'pantalla completa en una columna → slider anidado' );
	list( $out ) = ps_run( 'prime-slider-multiscroll', array( 'slides' => array( array( '_id' => 'm1', 'title' => 'Izq', 'slide_image' => array( 'url' => 'https://s1.test/wp-content/uploads/m.jpg', 'id' => 8 ), 'left_background' => 'color', 'left_background_color' => '#123', 'right_background' => 'image', 'right_background_image' => array( 'url' => 'https://s1.test/wp-content/uploads/r.jpg', 'id' => 6 ) ) ) ), 'container', $post );
	$row = $out['elements'][0];
	$check( 'row' === $row['settings']['flex_direction'] && 2 === count( $row['elements'] ) && '#123' === $row['elements'][0]['settings']['background_color'] && 6 === $row['elements'][1]['settings']['background_image']['id'], 'multiscroll: dos mitades en fila con sus fondos' );
	$check( 'Izq' === ps_widgets( $row['elements'][0], 'heading' )[0]['settings']['title'] && 8 === ps_widgets( $row['elements'][1], 'image' )[0]['settings']['image']['id'], 'texto a la izquierda e imagen a la derecha' );
	list( $out, $codes ) = ps_run( 'prime-slider-custom', array( 'sliders' => array( array( '_id' => 'k1', 'tab_title' => 'Uno', 'template_id' => '1234' ), array( '_id' => 'k2', 'tab_title' => 'Dos' ) ) ), 'container', $post );
	$check( 'template' === ps_widgets( $out['elements'][0], 'template' )[0]['widgetType'] && '1234' === ps_widgets( $out['elements'][0], 'template' )[0]['settings']['template_id'], 'custom: un widget Plantilla por contenedor' );
	$check( ! ps_widgets( $out['elements'][1], 'template' ) && isset( $codes['ps.review.no_template'] ) && 'Uno' === $out['settings']['slides'][0]['slide_title'], 'fila sin plantilla → contenedor vacío, para revisar; título de la fila' );
	$check( ! ps_widgets( $out['elements'][0], 'heading' ), 'el título de la pestaña no se pinta (sólo nombra la diapositiva)' );
	list( $out ) = ps_run( 'prime-slider-reveal', array( 'show_head_static_content' => 'yes', 'head_static_content_title' => 'Fijo', 'head_static_content_text' => '<p>Intro</p>', 'slides' => array( array( '_id' => 'v1', 'title' => 'x', 'image_caption' => 'pie' ) ) ), 'container', $post );
	list( $main, $parts ) = ps_parts( $out );
	$check( 'heading' === $parts[0]['widgetType'] && 'Fijo' === $parts[0]['settings']['title'] && 'text-editor' === $parts[1]['widgetType'] && $parts[2] === $main, 'reveal: contenido fijo antes del slider' );
	list( $out ) = ps_run( 'prime-slider-motion', array( 'info_button_text' => 'Saber más', 'list' => array( array( '_id' => 'l1', 'list_title' => 'M', 'list_content' => 'C', 'button_link' => array( 'url' => '/m/' ), 'list_image' => array( 'url' => 'https://s1.test/wp-content/uploads/l.jpg', 'id' => 2 ) ) ) ), 'container', $post );
	$check( 'Saber más' === ps_widgets( $out['elements'][0], 'button' )[0]['settings']['text'] && 'h3' === ps_widgets( $out['elements'][0], 'heading' )[0]['settings']['header_size'], 'motion: texto del botón del widget y título h3' );
	list( $out ) = ps_run( 'prime-slider-turnar', ps_expand( array( 'turnar_center_image' => array( 'url' => '{{ps_assets}}images/gallery/item-2.png' ), 'turnar_items' => array( array( '_id' => 't1', 'turnar_title' => 'x' ) ) ) ), 'container', $post );
	$check( false === strpos( json_encode( $out ), 'item-2' ) && false === strpos( json_encode( $out ), 'turnar' ), 'turnar: la imagen central no se copia ni se traslada' );

	/* ================================================================= 10. Respaldo `slides` y sin destino */

	Builder::$simulate = array( Builder::NESTED => false );
	list( $out, $codes ) = ps_run( 'prime-slider-general', array( 'kenburns_animation' => 'yes', 'kenburns_reverse' => 'yes', 'autoplay' => 'yes', 'autoplay_interval' => 6000, 'slides' => array(
		array( '_id' => 'f1', 'title' => 'Uno', 'sub_title' => 'Sub', 'excerpt' => '<p>T</p>', 'slide_button_text' => 'Ir', 'button_link' => array( 'url' => '/ir/' ), 'background' => 'image', 'image' => array( 'url' => 'https://s1.test/wp-content/uploads/k.jpg', 'id' => 11 ) ),
		array( '_id' => 'f2', 'title' => 'Dos', 'background' => 'video', 'video_link' => 'https://videos.example.test/v.mp4' ),
	) ), 'container', $post );
	$slides = $out['settings']['slides'];
	$check( 'slides' === $out['widgetType'] && 'a1b2c3d' === $out['id'] && isset( $codes['ps.fallback.slides'] ), 'sin slider anidado → Diapositivas (respaldo), informado' );
	$check( 'Uno' === $slides[0]['heading'] && '<p>Sub</p><p>T</p>' === $slides[0]['description'] && 'Ir' === $slides[0]['button_text'] && '/ir/' === $slides[0]['link']['url'] && 11 === $slides[0]['background_image']['id'], 'fila → diapositiva con título, subtítulo y texto, botón y fondo' );
	$check( 'yes' === $slides[0]['background_ken_burns'] && 'out' === $slides[0]['zoom_direction'] && ! isset( $codes['ps.dropped.kenburns'] ), 'Ken Burns se conserva en el respaldo' );
	$check( isset( $codes['ps.loss.video_background'] ) && 'yes' === $out['settings']['autoplay'] && 6000 === $out['settings']['autoplay_speed'] && 'fade' === $out['settings']['transition'] && 'h1' === $out['settings']['slides_title_tag'], 'vídeo perdido para revisar; reproducción, transición y etiqueta' );
	list( $out ) = ps_run( 'prime-slider-custom', array( 'sliders' => array( array( '_id' => 'k1', 'template_id' => '77' ) ) ), 'container', $post );
	$check( 77 === $out['settings']['slides'][0]['digitalisimo_template_id'] && 'yes' === $out['settings']['digitalisimo_legacy_templates'], 'respaldo con plantillas por el rasgo digitalisimo_template_id' );
	Builder::$simulate = array( Builder::NESTED => false, 'slides' => false );
	list( $out, $codes ) = ps_run( 'prime-slider-general', array( 'slides' => array( array( '_id' => 'z', 'title' => 'z' ) ) ), 'container', $post );
	$check( 'digitalisimo-unconverted' === ( $out['widgetType'] ?? '' ) && 'migration' === $out['settings']['_digitalisimo_unconverted']['source'] && isset( $codes['ps.blocked.no_target'] ), 'sin slider anidado ni Diapositivas y Prime Slider sin cargar → marcador y ps.blocked.no_target' );
	list( $out ) = ps_run( 'prime-slider-general', array(), 'container', $post, 'a1b2c3d', 'editor' );
	$check( null === $out, 'sin destino en el editor → null (el marcador se queda)' );
	Builder::$simulate = array( 'container' => false );
	list( $out ) = ps_run( 'prime-slider-pagepiling', array( 'slides' => array( array( '_id' => 'g1', 'title' => 'A' ) ) ), 'container', $post );
	$check( 'slides' === $out['widgetType'], 'pantalla completa sin contenedores → Diapositivas' );
	Builder::$simulate = array( 'digitalisimo-logos' => false );
	list( $out ) = ps_run( 'prime-slider-tango', array( 'slides' => array( array( '_id' => 't1', 'title' => 'a', 'image' => array( 'url' => 'https://s1.test/wp-content/uploads/a.jpg', 'id' => 1 ) ) ) ), 'container', $post );
	$check( Builder::NESTED === $out['widgetType'], 'familia C sin Logotipos → slider anidado' );
	Builder::$simulate = array( 'digitalisimo-fancy-tabs' => false );
	list( $out, $codes ) = ps_run( 'prime-slider-pandora', array(), 'container', $post );
	$check( 'digitalisimo-unconverted' === ( $out['widgetType'] ?? '' ) && isset( $codes['ps.blocked.no_target'] ), 'paneles sin Pestañas destacadas → sin destino, en su marcador' );
	Builder::reset();

	/* ================================================================= 11. Extensiones y descartes */

	list( $out, $codes ) = ps_run( 'prime-slider-dragon', array( 'reveal_effects_enable' => 'yes', 'animation_status' => 'yes', 'kenburns_animation' => 'yes', 'ep_display_conditions_enable' => 'yes', 'element_pack_wrapper_link' => array( 'url' => '/x/' ) ), 'container', $post );
	$check( isset( $codes['ps.dropped.reveal'], $codes['ps.dropped.advanced_animation'], $codes['ps.dropped.kenburns'] ), 'revelado, animación avanzada y Ken Burns descartados e informados' );
	$check( ! isset( $out['settings']['reveal_effects_enable'] ) && ! isset( $out['settings']['animation_status'] ), 'claves de las extensiones de PS fuera del destino' );
	$check( ! isset( $out['settings']['ep_display_conditions_enable'] ) && ! isset( $out['settings']['element_pack_wrapper_link'] ), 'extensiones de Element Pack del widget: Native las convierte en el principal (sin claves heredadas)' );
	$check( '/x/' === ( $out['settings']['digitalisimo_element_link']['url'] ?? '' ) && array_key_exists( 'digitalisimo_visibility_enable', $out['settings'] ), 'enlace del elemento y visibilidad en sus ajustes propios' );
	list( $out, $codes ) = ps_run( 'prime-slider-mercury', array(), 'container', $post );
	$check( ! isset( $codes['ps.approx'] ), 'familia de F: E no la decide ni la informa como aproximada' );
	list( $out, $codes ) = ps_run( 'prime-slider-remote-thumbs', array( 'remote_id' => '#s' ), 'container', $post, 'a1b2c3d', 'editor' );
	$check( null === $out, 'descarte en el editor → null (el marcador se queda)' );
	list( $out, $codes ) = ps_run( 'prime-slider-no-existe', array(), 'container', $post );
	$check( 'digitalisimo-unconverted' === ( $out['widgetType'] ?? '' ) && isset( $codes['ps.blocked.no_target'] ), 'widget de Prime Slider sin mapa → marcador, sin destino' );
	$check( null === Converter::element( array( 'elType' => 'widget', 'widgetType' => 'bdt-slider', 'settings' => array() ), 1, '' ) && null === Converter::element( array( 'elType' => 'container', 'widgetType' => 'prime-slider-general' ), 1, '' ), 'sólo widgets prime-slider-*' );

	/* ================================================================= 12. Imágenes de demostración */

	$up       = wp_upload_dir();
	$registry = '\\Digitalisimo\\Elements\\Legacy\\Platform\\Registry';
	$reg      = static function () use ( $registry ) {
		return class_exists( $registry ) ? (array) $registry::all( 'demo' ) : array();
	};
	Demo_Media::reset();
	$GLOBALS['ps_sideloads'] = array();
	$GLOBALS['ps_tmp']       = array();
	ps_png( $images . '/gallery/unica.png', 200, 100, 50 );
	$url = $assets . 'images/gallery/unica.png';
	Context::begin( $post, 'run' );
	$a = Demo_Media::resolve( 'prime-slider-general', array( 'image' => array( 'url' => $url, 'alt' => 'A' ), 'list' => array( array( 'image' => array( 'url' => $url ) ) ), 'texto' => $url ) );
	Context::end();
	$id = $a['image']['id'];
	$check( $id > 0 && 'A' === $a['image']['alt'] && $a['list'][0]['image']['id'] === $id && $a['texto'] === $a['image']['url'], 'misma imagen en tres sitios → un adjunto (deduplicada), MEDIA y cadena' );
	$check( 1 === Demo_Media::$copied && 2 === Demo_Media::$reused && 1 === count( $GLOBALS['ps_sideloads'] ), 'una sola copia; dos reutilizadas' );
	$side = $GLOBALS['ps_sideloads'][0];
	$check( array() === $side['sizes'] && false === $side['svg_allowed'], 'sin tamaños intermedios durante la inserción; SVG no admitido para un PNG' );
	$check( ! class_exists( '\\Digitalisimo\\Elements\\Legacy\\Platform\\Registry' ) || true === $side['pending'], 'fila demo en pending antes de insertar' );
	$check( 0 !== strpos( $side['tmp'], $up['basedir'] ) && ! file_exists( $side['tmp'] ), 'temporal fuera de uploads y borrado' );
	$check( hash_file( 'sha256', $images . '/gallery/unica.png' ) === f8_meta( $id, Demo_Media::META ), 'meta _digitalisimo_migration_demo = sha256 del original' );
	$rows = $reg();
	$sha  = hash_file( 'sha256', $images . '/gallery/unica.png' );
	$check( ! class_exists( $registry ) || ( 'done' === ( $rows[ $sha ]['state'] ?? '' ) && $id === (int) $rows[ $sha ]['dest'] && hash_file( 'sha256', get_attached_file( $id ) ) === $rows[ $sha ]['hash'] ), 'registro demo: done con el adjunto y la huella del archivo copiado' );
	$check( ! has_filter( 'intermediate_image_sizes_advanced' ) && ! has_filter( 'upload_mimes' ), 'los filtros de la inserción se retiran' );
	// Otra petición: reutiliza la copia por el registro o la meta.
	Demo_Media::reset();
	Context::begin( $post, 'run' );
	$b = Demo_Media::resolve( 'prime-slider-general', array( 'image' => array( 'url' => $url ) ) );
	Context::end();
	$check( $id === $b['image']['id'] && 0 === Demo_Media::$copied && 1 === count( $GLOBALS['ps_sideloads'] ), 'otra petición reutiliza la copia del sitio' );
	// Modo puro: reutiliza lo ya copiado y no copia lo nuevo.
	Demo_Media::reset();
	Context::begin( $post, 'editor' );
	ps_png( $images . '/gallery/nueva.png', 7, 77, 177 );
	$c = Demo_Media::resolve( 'prime-slider-general', array( 'a' => array( 'url' => $url ), 'b' => array( 'url' => $assets . 'images/gallery/nueva.png' ) ) );
	Context::end();
	$check( $id === $c['a']['id'] && '' === $c['b']['url'] && 1 === count( $GLOBALS['ps_sideloads'] ), 'editor: reutiliza lo copiado y deja vacío lo demás, sin copiar' );

	// Rutas hostiles y archivos rechazados.
	$hostile = array(
		'../ (fuera de la base)'         => $assets . 'images/../js/fuera.png',
		'%2e%2e codificado'              => $assets . 'images/%2e%2e/js/fuera.png',
		'doble codificación'             => $assets . 'images/%252e%252e/js/fuera.png',
		'barra codificada'               => $assets . 'images%2f..%2fjs%2ffuera.png',
		'enlace simbólico'               => $assets . 'images/gallery/enlace.png',
		'.txt'                           => $assets . 'images/nota.txt',
		'> 5 MB'                         => $assets . 'images/grande.png',
		'falso PNG'                      => $assets . 'images/falsa.png',
		'fuera de assets/images'         => $assets . 'js/fuera.png',
		'archivo ausente'                => $assets . 'images/gallery/nada.png',
		'carpeta ajena con el mismo final' => 'https://s1.test/wp-content/plugins/otro-plugin/assets/images/gallery/item-1.png',
		'barra invertida'                => $assets . 'images/gallery\\item-1.png',
		'archivo oculto'                 => $assets . 'images/.htaccess.png',
	);
	foreach ( $hostile as $name => $bad ) {
		Demo_Media::reset();
		Context::begin( $post, 'run' );
		$r = Demo_Media::resolve( 'prime-slider-general', array( 'image' => array( 'url' => $bad, 'id' => 99 ) ) );
		Context::end();
		$foreign = 'carpeta ajena con el mismo final' === $name;
		$check( $foreign ? $bad === $r['image']['url'] : ( '' === $r['image']['url'] && '' === $r['image']['id'] && 1 === Demo_Media::$skipped ), 'ruta hostil: ' . $name );
	}
	$check( 1 === count( $GLOBALS['ps_sideloads'] ), 'ninguna ruta hostil llegó a insertarse' );
	$check( null === Demo_Media::locate( $assets . 'images/gallery/enlace.png' ) && null !== Demo_Media::locate( $assets . 'images/gallery/item-3.png' ), 'locate(): enlace simbólico rechazado, archivo normal aceptado' );

	// SVG: con la subida sin filtrar apagada, vacío; encendida, saneado antes de escribirse.
	Demo_Media::reset();
	Context::begin( $post, 'run' );
	$s1 = Demo_Media::resolve( 'prime-slider-general', array( 'image' => array( 'url' => $assets . 'images/gallery/item-1.svg' ) ) );
	Context::end();
	$check( '' === $s1['image']['url'] && 1 === Demo_Media::$skipped && 1 === count( $GLOBALS['ps_sideloads'] ), 'SVG sin la subida sin filtrar → vacío, sin copiar' );
	update_option( 'elementor_unfiltered_files_upload', '1' );
	Demo_Media::reset();
	Context::begin( $post, 'run' );
	$s2 = Demo_Media::resolve( 'prime-slider-general', array( 'image' => array( 'url' => $assets . 'images/gallery/item-1.svg' ) ) );
	Context::end();
	$svg_side = end( $GLOBALS['ps_sideloads'] );
	$check( $s2['image']['id'] > 0 && 2 === count( $GLOBALS['ps_sideloads'] ) && true === $svg_side['svg_allowed'], 'SVG con la subida sin filtrar → copiado, con el tipo SVG sólo durante la inserción' );
	$check( false === stripos( $svg_side['content'], '<script' ) && false === stripos( $svg_side['content'], 'onload' ) && false === stripos( (string) file_get_contents( get_attached_file( $s2['image']['id'] ) ), '<script' ), 'SVG saneado antes de escribirse en uploads' );
	$check( ! has_filter( 'upload_mimes' ), 'el tipo SVG se retira tras la inserción' );
	update_option( 'elementor_unfiltered_files_upload', '' );

	// Plugin borrado: la URL con la carpeta conocida sigue reconociéndose y se vacía.
	$GLOBALS['wp']['plugins'] = array();
	Defaults::reset();
	Demo_Media::reset();
	Context::begin( $post, 'run' );
	$gone = Demo_Media::resolve( 'prime-slider-general', array( 'image' => array( 'url' => $assets . 'images/gallery/item-5.png' ) ) );
	Context::end();
	$check( '' === $gone['image']['url'] && 1 === Demo_Media::$skipped, 'plugin ya borrado → vacío' );
	$check( Demo_Media::is_plugin_url( $assets . 'images/x.png' ) && Demo_Media::is_plugin_url( 'https://s1.test/wp-content/plugins/bdthemes-prime-slider/assets/images/x.png' ) && ! Demo_Media::is_plugin_url( 'https://s1.test/wp-content/plugins/otro/assets/images/x.png' ), 'carpetas de Prime Slider (Lite y Pro) reconocidas aunque no estén instaladas' );
	$GLOBALS['wp']['plugins'] = $plugins_backup;
	Defaults::reset();

	// Reversión: sin papelera de medios, la copia se queda; el registro se olvida.
	if ( class_exists( $registry ) ) {
		$before = count( $reg() );
		$codes  = Demo_Media::revert();
		$check( $before >= 2 && array() === $reg(), 'revert(): el registro demo se olvida' );
		$check( count( $codes ) === $before && 'ps.demo.revert_kept' === $codes[0][0] && ! in_array( array( 1, $id ), $GLOBALS['wp']['trashed'], true ), 'sin MEDIA_TRASH las copias se quedan en la biblioteca (nunca se borran)' );
	}

	/* ================================================================= 13. Descripción para el análisis previo */

	$doc = array(
		array( 'id' => 's1', 'elType' => 'container', 'elements' => array(
			array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'prime-slider-general', 'settings' => array( 'reveal_effects_enable' => 'yes' ) ),
			array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'prime-slider-custom', 'settings' => array() ),
			array( 'id' => 'w3', 'elType' => 'widget', 'widgetType' => 'prime-slider-remote-arrows', 'settings' => array() ),
			array( 'id' => 'w4', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array() ),
		) ),
	);
	$d = Converter::describe( $doc );
	$check( 1 === ( $d['ps.approx'] ?? 0 ) && 1 === ( $d['ps.converted'] ?? 0 ) && 1 === ( $d['ps.dropped.remote'] ?? 0 ) && 1 === ( $d['ps.dropped.reveal'] ?? 0 ), 'describe(): aproximado, convertido, descarte y extensión' );
	Builder::$simulate = array( Builder::NESTED => false );
	$d = Converter::describe( $doc );
	$check( 2 === ( $d['ps.fallback.slides'] ?? 0 ), 'describe(): respaldo previsto sin slider anidado' );
	Builder::reset();
	$check( array() === Converter::describe( array( 'elType' => 'widget', 'widgetType' => 'heading' ) ), 'describe(): nada sin Prime Slider' );
	$d = Converter::describe( array( 'elType' => 'widget', 'widgetType' => 'prime-slider-blog', 'settings' => array() ) );
	$check( 1 === array_sum( $d ) && 0 === strpos( (string) key( $d ), 'ps.dyn.' ) && ! isset( $d['ps.approx'] ), 'describe(): las familias de F, con el código de Dynamic::describe() (' . key( $d ) . ')' );

	/* ================================================================= 14. Informe */

	Report::begin( 'run' );
	Report::clear();
	Context::begin( $post, 'run' );
	Converter::element( array( 'id' => 'r1r2r3r', 'elType' => 'widget', 'widgetType' => 'prime-slider-dragon', 'settings' => array( 'slides' => array( array( '_id' => 'q', 'title' => '<img src=x onerror=alert(1)>' ) ) ) ), $post, 'container' );
	Context::end();
	$report = Report::take();
	$codes  = array_column( $report['docs'][ $post ]['items'] ?? array(), 'code' );
	$check( in_array( 'ps.approx', $codes, true ) && 'prime-slider-dragon' === $report['docs'][ $post ]['items'][0]['detail']['widget'] && 'r1r2r3r' === $report['docs'][ $post ]['items'][0]['detail']['element'], 'informe: códigos del documento con el widget y el elemento' );
	$catalog = Report::catalog();
	$missing = array();
	foreach ( array( 'converter', 'static', 'demo-media', 'builder', 'defaults', 'sanitizer' ) as $part ) {
		$file = dirname( __DIR__ ) . '/digitalisimo-migration/legacy/prime-slider/class-' . $part . '.php';
		preg_match_all( "/'(ps\.[a-z_.]+)'/", file_get_contents( $file ), $m );
		foreach ( $m[1] as $code ) {
			if ( 0 !== strpos( $code, 'ps.dyn.' ) && ! isset( $catalog[ $code ] ) ) {
				$missing[] = $code;
			}
		}
	}
	foreach ( Maps::all() as $map ) {
		if ( isset( $map['drop_code'] ) && ! isset( $catalog[ $map['drop_code'] ] ) ) {
			$missing[] = $map['drop_code'];
		}
	}
	$check( ! $missing, 'cada código ps. usado está en el catálogo (' . implode( ', ', array_unique( $missing ) ) . ')' );
	$ps_codes = array_filter( array_keys( $catalog ), static function ( $c ) {
		return 0 === strpos( $c, 'ps.' ) && 0 !== strpos( $c, 'ps.dyn.' );
	} );
	$labels = '';
	foreach ( $ps_codes as $code ) {
		$labels .= $catalog[ $code ]['label'];
	}
	$check( count( $ps_codes ) >= 25 && '' === ps_trace( array( $labels ) ) && 'review' === $catalog['ps.blocked.no_target']['section'] && ! empty( $catalog['ps.demo.skipped']['review_if_published'] ), 'catálogo ps.: textos sin el proveedor; sin destino para revisar; ps.demo.skipped revisable si está publicado' );

	/* ================================================================= 3 (bis). Widget registrado con Prime Slider cargado */

	define( 'BDTPS_CORE_VER', '4.7.0' );
	eval( 'namespace Elementor; class Plugin { public static $instance; public $widgets_manager; public $experiments; }' );
	final class PS_Fake_Widget {
		public function get_controls() {
			return array(
				'section_content'  => array( 'type' => 'section' ),
				'show_title'       => array( 'name' => 'show_title', 'type' => 'switcher', 'default' => 'yes' ),
				'title_html_tag'   => array( 'name' => 'title_html_tag', 'type' => 'select', 'default' => 'h3' ),
				'autoplay'         => array( 'name' => 'autoplay', 'type' => 'switcher', 'default' => '' ),
				'slider_animations'=> array( 'name' => 'slider_animations', 'type' => 'select', 'default' => 'scale' ),
				'title_color'      => array( 'name' => 'title_color', 'type' => 'color', 'default' => '', 'tab' => 'style' ),
				'note'             => array( 'name' => 'note', 'type' => 'raw_html', 'default' => 'x' ),
				'slides'           => array(
					'name'    => 'slides',
					'type'    => 'repeater',
					'fields'  => array(
						'title'             => array( 'name' => 'title', 'type' => 'text', 'default' => '' ),
						'slide_button_text' => array( 'name' => 'slide_button_text', 'type' => 'text', 'default' => 'Leer (registrado)' ),
						'button_link'       => array( 'name' => 'button_link', 'type' => 'url', 'default' => array( 'url' => '/registrado/', 'is_external' => '' ) ),
					),
					'default' => array( array( 'title' => 'R1' ), array( 'title' => 'R2' ) ),
				),
			);
		}
	}
	final class PS_Fake_Manager {
		public function get_widget_types( $name = null ) {
			return 'prime-slider-general' === $name ? new PS_Fake_Widget() : ( in_array( $name, array( 'digitalisimo-nested-slider', 'slides', 'heading', 'button' ), true ) ? new stdClass() : null );
		}
	}
	\Elementor\Plugin::$instance                  = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->widgets_manager = new PS_Fake_Manager();
	Defaults::reset();
	list( $out, $codes ) = ps_run( 'prime-slider-general', array( 'slides' => array( array( '_id' => 'g1', 'title' => 'Guardado' ) ), 'show_title' => 'yes' ), 'container', $post );
	$check( 'registered' === Defaults::$last && ! isset( $codes['ps.defaults.static'] ), 'con Prime Slider cargado: valores del widget registrado, sin aviso de estáticos' );
	$check( 'scale' === $out['settings']['effect'] && '' === $out['settings']['autoplay'], 'controles del widget registrado (versión instalada) frente a los estáticos' );
	$b = ps_widgets( $out['elements'][0], 'button' );
	$check( 1 === count( $b ) && 'Leer (registrado)' === $b[0]['settings']['text'] && '/registrado/' === $b[0]['settings']['link']['url'] && 'h3' === ps_widgets( $out['elements'][0], 'heading' )[0]['settings']['header_size'], 'campos de la fila con los valores del widget registrado' );
	list( $out ) = ps_run( 'prime-slider-general', array(), 'container', $post );
	$check( 2 === count( $out['elements'] ) && 'R2' === $out['settings']['slides'][1]['slide_title'], 'repetidor ausente → filas por defecto del widget registrado' );
	list( $out, $codes ) = ps_run( 'prime-slider-coddle', array(), 'container', $post );
	$check( 'static' === Defaults::$last && isset( $codes['ps.defaults.static'] ), 'widget no registrado (Pro inactivo) → estáticos de B' );
	Builder::$simulate = array( Builder::NESTED => false, 'slides' => false );
	list( $out, $codes ) = ps_run( 'prime-slider-general', array(), 'container', $post );
	$check( null === $out && isset( $codes['ps.blocked.no_target'] ), 'sin destino con Prime Slider cargado → null: el widget se queda y sigue viéndose' );
	Builder::reset();

	ps_rm( WP_CONTENT_DIR );
	ps_rm( $defaults_dir );
	ps_rm( $empty );
	ps_rm( sys_get_temp_dir() . '/ps-e-tmp' );
	f8_done( 'migration-prime-slider.php' . ( $with_b ? '' : ' (sin los valores estáticos de B en el repaso de los 38)' ), 260 );
}
