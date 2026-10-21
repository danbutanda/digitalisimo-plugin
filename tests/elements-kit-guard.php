<?php
/**
 * Kits (etapa K, §13.1 «guard»): guardián del ZIP, normalizador y conversión de Template Kits (frente A).
 *
 * Sin WordPress ni el constructor: ZIP construidos byte a byte (rutas hostiles, enlaces simbólicos, tamaños falsos
 * en el directorio central, relaciones de compresión) y con ZipArchive. Comprueba:
 *  1. Rechazos: `..`, rutas absolutas, `\`, NUL, enlaces simbólicos, 5 001 entradas, relación 101:1, JSON de
 *     32 MB + 1 y de profundidad 257, ejecutables y `.htaccess` en cualquier lugar, dos niveles de ZIP anidado, un ZIP
 *     interior con traversal o ejecutable, un tamaño falso, XML con DOCTYPE/ENTITY o de más de 64 MB, JSON con tipos
 *     erróneos y claves inválidas en files.json y media.json (CR-3, CR-19, CV-22).
 *  2. Admisiones: `TEMPLATES/A.JSON`, documentación de marketplace fuera del kit, subcarpeta, `__MACOSX`, 5 000
 *     entradas, relación 100:1 y un kit grande (4 999 entradas, 31 MB).
 *  3. Normalizador: pedidos al análisis, identidad fuera, `metadata` (objeto, array, protegidas y URL), miniaturas
 *     sin archivo y tipos de documento desconocidos; el ZIP limpio sólo lleva json y xml.
 *  4. Template Kit de Envato sintético: manifiesto 3.0, globales, condiciones reducidas con la lista completa en
 *     theme-builder.json (claves numéricas), tipo desconocido → section y metadata limpia (CC-21).
 *  5. Medios: un sha256 distinto se descarta; un SVG con <script> se sanea.
 *
 * Lanza una excepción si falta ZipArchive: nunca «SKIP» (CV-30).
 */

namespace Elementor {
	class Plugin {
		public static $instance;
	}
}

namespace Elementor\Core {
	/** Tipos de documento registrados para el análisis y la conversión de Envato. */
	class Kit_Guard_Documents {
		public function get_document_type( $type, $fallback = true ) {
			$known = array( 'header', 'footer', 'page', 'section', 'wp-page', 'popup', 'single-post', 'archive' );
			return in_array( $type, $known, true ) ? 'Elementor\Core\Kit_Guard_Doc_' . str_replace( '-', '_', $type ) : ( $fallback ? 'Elementor\Core\Kit_Guard_Doc_section' : false );
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );

	if ( ! class_exists( 'ZipArchive' ) ) {
		throw new RuntimeException( 'elements-kit-guard: falta la extensión ZipArchive (CV-30: nunca se omite).' );
	}

	$GLOBALS['kit_filters'] = array();
	function apply_filters( $hook, $value, ...$args ) {
		return isset( $GLOBALS['kit_filters'][ $hook ] ) ? call_user_func( $GLOBALS['kit_filters'][ $hook ], $value, ...$args ) : $value;
	}
	function home_url() {
		return 'https://b.test';
	}
	function is_protected_meta( $key, $type = '' ) {
		return '' !== $key && '_' === $key[0];
	}
	function is_serialized( $data ) {
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		if ( 'N;' === $data ) {
			return true;
		}
		return strlen( $data ) >= 4 && ':' === $data[1] && in_array( substr( $data, -1 ), array( ';', '}' ), true ) && in_array( $data[0], array( 'a', 'O', 's', 'b', 'i', 'd', 'C' ), true );
	}
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
	function get_temp_dir() {
		return rtrim( sys_get_temp_dir(), '/' ) . '/';
	}
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
	function post_type_exists( $type ) {
		return in_array( $type, array( 'page', 'post', 'nav_menu_item', 'e-floating-buttons' ), true );
	}
	function taxonomy_exists( $taxonomy ) {
		return in_array( $taxonomy, array( 'category', 'post_tag', 'nav_menu' ), true );
	}
	function get_allowed_mime_types() {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
			'mp4'          => 'video/mp4',
			'pdf'          => 'application/pdf',
		);
	}

	\Elementor\Plugin::$instance            = new stdClass();
	\Elementor\Plugin::$instance->documents = new \Elementor\Core\Kit_Guard_Documents();

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-import/class-module.php';
	\Digitalisimo\Elements\Import\Module::register_autoloader();

	use Digitalisimo\Elements\Import\Kits\Zip_Guard;
	use Digitalisimo\Elements\Import\Kits\Normalizer;
	use Digitalisimo\Elements\Import\Kits\Inspection;

	$check = function ( $ok, $message ) {
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	};

	$dir = sys_get_temp_dir() . '/digi-kit-guard-' . getmypid() . '-' . mt_rand();
	mkdir( $dir, 0700, true );
	$counter = 0;

	/**
	 * ZIP escrito byte a byte. Cada entrada: name, data, method (0 o 8), size y comp (tamaños declarados en el
	 * directorio central; por defecto los reales), attr (atributos externos) y raw (bytes reales a escribir).
	 */
	$zip_raw = function ( array $entries ) use ( $dir, &$counter ) {
		$local   = '';
		$central = '';
		$offset  = 0;
		foreach ( $entries as $entry ) {
			$name   = $entry['name'];
			$data   = isset( $entry['data'] ) ? $entry['data'] : '';
			$method = isset( $entry['method'] ) ? $entry['method'] : 0;
			$raw    = 8 === $method ? gzdeflate( $data ) : $data;
			if ( isset( $entry['raw'] ) ) {
				$raw = $entry['raw'];
			}
			$crc     = crc32( $data );
			$size    = isset( $entry['size'] ) ? $entry['size'] : strlen( $data );
			$comp    = isset( $entry['comp'] ) ? $entry['comp'] : strlen( $raw );
			$attr    = isset( $entry['attr'] ) ? $entry['attr'] : ( 0100644 << 16 );
			$head    = pack( 'VvvvvvVVVvv', 0x04034b50, 20, 0, $method, 0, 0x0021, $crc, $comp, $size, strlen( $name ), 0 ) . $name;
			$local  .= $head . $raw;
			$central .= pack( 'VvvvvvvVVVvvvvvVV', 0x02014b50, ( 3 << 8 ) | 20, 20, 0, $method, 0, 0x0021, $crc, $comp, $size, strlen( $name ), 0, 0, 0, 0, $attr, $offset ) . $name;
			$offset += strlen( $head ) + strlen( $raw );
		}
		$file = $dir . '/raw-' . ( ++$counter ) . '.zip';
		file_put_contents( $file, $local . $central . pack( 'VvvvvVVv', 0x06054b50, 0, 0, count( $entries ), count( $entries ), strlen( $central ), $offset, 0 ) );
		return $file;
	};

	/** ZIP con ZipArchive (DEFLATE): ruta => contenido. */
	$zip_files = function ( array $files ) use ( $dir, &$counter ) {
		$file = $dir . '/kit-' . ( ++$counter ) . '.zip';
		$zip  = new ZipArchive();
		$zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		foreach ( $files as $path => $content ) {
			$zip->addFromString( $path, is_string( $content ) ? $content : json_encode( $content, JSON_UNESCAPED_SLASHES ) );
		}
		$zip->close();
		return $file;
	};

	$rejects = function ( $file, $needle, $label, ?array $limits = null ) use ( $check ) {
		try {
			$inspection = Zip_Guard::inspect( $file, $limits );
			$inspection->cleanup();
		} catch ( RuntimeException $error ) {
			$check( '' === $needle || false !== stripos( $error->getMessage(), $needle ), "{$label}: se rechazó, pero con otro motivo: " . $error->getMessage() );
			return $error->getMessage();
		}
		throw new RuntimeException( "{$label}: debía rechazarse." );
	};
	$accepts = function ( $file, $label, ?array $limits = null ) {
		try {
			return Zip_Guard::inspect( $file, $limits );
		} catch ( RuntimeException $error ) {
			throw new RuntimeException( "{$label}: debía admitirse, pero: " . $error->getMessage() );
		}
	};

	$manifest = array(
		'name'    => 'kit',
		'title'   => 'Kit',
		'version' => '3.0',
	);
	$m        = json_encode( $manifest );
	$sha      = str_repeat( 'a', 64 );

	try {
		// ── 1. Rechazos ─────────────────────────────────────────────────────────────────────────────────────────
		$base = array( array( 'name' => 'manifest.json', 'data' => $m ) );
		foreach ( array( '../x.json' => 'ruta no admitida', '/abs.json' => 'ruta no admitida', 'a\\b.json' => 'ruta no admitida', 'a/./b.json' => 'ruta no admitida', "nul\0x.json" => 'ruta no admitida', 'C:/x.json' => 'ruta no admitida' ) as $name => $reason ) {
			$rejects( $zip_raw( array_merge( $base, array( array( 'name' => $name, 'data' => '{}' ) ) ) ), $reason, 'Ruta ' . json_encode( $name ) );
		}
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'templates/1.json', 'data' => '/etc/passwd', 'attr' => ( 0120777 << 16 ) ) ) ) ), 'enlace simbólico', 'Enlace simbólico' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'Templates/1.json', 'data' => '{}' ), array( 'name' => 'templates/1.json', 'data' => '{}' ) ) ) ), 'repite', 'Nombre repetido sin distinguir mayúsculas' );

		$many = function ( $total ) use ( $m ) {
			$entries = array( array( 'name' => 'kit/manifest.json', 'data' => $m ) );
			for ( $i = 1; $i < $total; $i++ ) {
				$entries[] = array(
					'name' => 'docs/' . $i . '.txt',
					'data' => 'x',
				);
			}
			return $entries;
		};
		$rejects( $zip_raw( $many( 5001 ) ), '5000', '5 001 entradas' );
		$five = $accepts( $zip_raw( $many( 5000 ) ), '5 000 entradas' );
		$check( 'kit/' === $five->prefix() && 4999 === $five->ignored() && array( 'manifest.json' ) === $five->paths(), 'Con 5 000 entradas, la documentación fuera del kit se ignora y no se extrae.' );
		$five->cleanup();

		$media = 'digitalisimo/media/' . $sha . '.png';
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => $media, 'raw' => random_bytes( 20000 ), 'method' => 8, 'size' => 2020000, 'comp' => 20000 ) ) ) ), 'anómala', 'Relación 101:1' );
		$accepts( $zip_raw( array_merge( $base, array( array( 'name' => $media, 'raw' => random_bytes( 20000 ), 'method' => 8, 'size' => 2000000, 'comp' => 20000 ) ) ) ), 'Relación 100:1' )->cleanup();
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => $media, 'raw' => 'x', 'method' => 8, 'size' => 3000000, 'comp' => 0 ) ) ) ), 'anómala', 'Más de 1 MB sin tamaño comprimido' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'templates/1.json', 'raw' => '{}', 'size' => 33554433, 'comp' => 33554433 ) ) ) ), 'más de 32 MB', 'JSON de 32 MB + 1' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'wp-content/page/page.xml', 'raw' => '<x/>', 'size' => 67108865, 'comp' => 67108865 ) ) ) ), 'más de 64 MB', 'XML de 64 MB + 1' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'big.json', 'raw' => 'x', 'size' => 536870913, 'comp' => 536870913 ) ) ) ), 'descomprimido', 'Más de 512 MB descomprimidos' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'extra.json', 'data' => str_repeat( '[', 257 ) . str_repeat( ']', 257 ) ) ) ) ), 'profundo', 'JSON de profundidad 257' );
		// json_decode cuenta también el nivel de los valores: 255 listas anidadas son profundidad 256, el máximo.
		$accepts( $zip_raw( array_merge( $base, array( array( 'name' => 'extra.json', 'data' => str_repeat( '[', 255 ) . str_repeat( ']', 255 ) ) ) ) ), 'JSON de profundidad 256' )->cleanup();
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'extra.json', 'data' => '{"a":1', ) ) ) ), 'no es un JSON válido', 'JSON roto' );
		$rejects( $zip_raw( array( array( 'name' => 'manifest.json', 'data' => '"texto"' ) ) ), 'objeto o una lista', 'Manifiesto escalar' );

		foreach ( array( 'x.php', 'x.PHTML', 'docs/.htaccess', '__MACOSX/.htaccess', 'otro/archivo.phar', 'kit/script.sh', 'carpeta/.user.ini' ) as $name ) {
			$rejects( $zip_raw( array_merge( $base, array( array( 'name' => $name, 'data' => '<?php echo 1;' ) ) ) ), 'ejecutable', "Ejecutable {$name}" );
		}

		$rejects( $zip_raw( array( array( 'name' => 'manifest.json', 'data' => $m, 'size' => 5 ) ) ), 'tamaño real', 'Tamaño falso en el directorio central' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'wp-content/page/page.xml', 'data' => '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY a "b">]><rss/>' ) ) ) ), 'DOCTYPE', 'XML con DOCTYPE' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'wp-content/page/page.xml', 'data' => '<?xml version="1.0"?><rss><!ENTITY x SYSTEM "file:///etc/passwd"></rss>' ) ) ) ), 'DOCTYPE', 'XML con ENTITY' );
		$long = '<?xml version="1.0"?><rss>' . str_repeat( 'a', 1048570 ) . '<!DOCTYPE x></rss>';
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'wp-content/page/page.xml', 'data' => $long ) ) ) ), 'DOCTYPE', 'XML con DOCTYPE entre dos bloques de lectura' );

		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'templates/1.json', 'data' => '{"content":{"a":1}}' ) ) ) ), 'lista de elementos', 'content que no es una lista' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'templates/1.json', 'data' => '{"content":[{"settings":{}}]}' ) ) ) ), 'elType', 'Elemento sin elType' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'content/page/2.json', 'data' => '{"content":[{"elType":"container","elements":{"x":1}}]}' ) ) ) ), 'elements debe ser una lista', 'Hijos que no son una lista' );
		$rejects( $zip_raw( array( array( 'name' => 'manifest.json', 'data' => '{"templates":"x"}' ) ) ), 'templates debe ser un objeto', 'Manifiesto con templates escalar' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'site-settings.json', 'data' => '{"settings":"x"}' ) ) ) ), 'settings debe ser un objeto', 'Ajustes escalares' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'digitalisimo/files.json', 'data' => '{"../x.svg":{"sha256":"' . $sha . '"}}' ) ) ) ), 'clave inválida', 'Clave inválida en files.json' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'digitalisimo/files.json', 'data' => '{"shapes/Ola.svg":{"sha256":"' . $sha . '"}}' ) ) ) ), 'clave inválida', 'Mayúsculas en files.json' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'digitalisimo/media.json', 'data' => '{"https://a.test/x.png":{"file":"../x.png","sha256":"' . $sha . '"}}' ) ) ) ), 'media.json', 'Archivo inválido en media.json' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'digitalisimo/theme-builder.json', 'data' => '{"abc":{}}' ) ) ) ), 'clave inválida', 'Clave inválida en theme-builder.json' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'digitalisimo/urls.json', 'data' => '{"page":"/x"}' ) ) ) ), 'clave inválida', 'Clave inválida en urls.json' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'digitalisimo/media/x.png', 'data' => 'x' ) ) ) ), 'no admitido', 'Archivo de medios sin sha256 por nombre' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'digitalisimo/files/shapes/ola.png', 'data' => 'x' ) ) ) ), 'no admitido', 'Forma con otra extensión' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'digitalisimo/Parte.json', 'data' => '{}' ) ) ) ), 'no admitido', 'Parte con mayúsculas' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'templates/1.html', 'data' => '<p>' ) ) ) ), 'no admitido', 'HTML dentro del kit' );
		$rejects( $zip_raw( array_merge( $base, array( array( 'name' => 'templates/x.png', 'data' => 'x' ) ) ) ), 'no admitido', 'Imagen fuera de la raíz y de screenshots/' );
		$rejects( $zip_raw( array( array( 'name' => 'leeme.txt', 'data' => 'x' ) ) ), 'no es un kit', 'ZIP sin manifiesto' );
		$rejects( $zip_raw( array( array( 'name' => 'a/manifest.json', 'data' => $m ), array( 'name' => 'b/manifest.json', 'data' => $m ) ) ), 'más de un kit', 'Dos kits en un ZIP' );

		$inner_ok   = file_get_contents( $zip_raw( array( array( 'name' => 'manifest.json', 'data' => $m ) ) ) );
		$inner_bad  = file_get_contents( $zip_raw( array( array( 'name' => 'manifest.json', 'data' => $m ), array( 'name' => '../../evil.json', 'data' => '{}' ) ) ) );
		$inner_php  = file_get_contents( $zip_raw( array( array( 'name' => 'manifest.json', 'data' => $m ), array( 'name' => 'shell.php', 'data' => '<?php' ) ) ) );
		$level_two  = file_get_contents( $zip_raw( array( array( 'name' => 'kit.zip', 'data' => $inner_ok ) ) ) );
		$rejects( $zip_raw( array( array( 'name' => 'paquete/kit.zip', 'data' => $level_two ) ) ), 'más de un nivel', 'Dos niveles de ZIP anidado' );
		$rejects( $zip_raw( array( array( 'name' => 'kit.zip', 'data' => $inner_bad ) ) ), 'ruta no admitida', 'ZIP interior con traversal' );
		$rejects( $zip_raw( array( array( 'name' => 'kit.zip', 'data' => $inner_php ) ) ), 'ejecutable', 'ZIP interior con un ejecutable' );
		$rejects( $zip_raw( array( array( 'name' => 'uno.zip', 'data' => $inner_ok ), array( 'name' => 'dos.zip', 'data' => $inner_ok ) ) ), 'no es un kit', 'Dos ZIP interiores' );

		// ── 2. Admisiones ───────────────────────────────────────────────────────────────────────────────────────
		$upper = $accepts( $zip_raw( array( array( 'name' => 'MANIFEST.JSON', 'data' => $m ), array( 'name' => 'TEMPLATES/A.JSON', 'data' => '{"content":[]}' ) ) ), 'TEMPLATES/A.JSON' );
		$check( $upper->has( 'manifest.json' ) && $upper->has( 'templates/A.json' ), 'Una carpeta conocida y la extensión se leen en minúsculas: TEMPLATES/A.JSON → templates/A.json.' );
		$upper->cleanup();
		$market = $accepts(
			$zip_raw(
				array(
					array( 'name' => 'Documentacion/index.html', 'data' => '<html></html>' ),
					array( 'name' => 'Documentacion/js/app.js', 'data' => 'alert(1)' ),
					array( 'name' => '__MACOSX/mi-kit/._manifest.json', 'data' => 'x' ),
					array( 'name' => 'mi-kit/manifest.json', 'data' => $m ),
					array( 'name' => 'mi-kit/templates/1.json', 'data' => '{"content":[{"id":"a","elType":"section","elements":[]}]}' ),
					array( 'name' => 'mi-kit/screenshots/1.jpg', 'data' => 'jpg' ),
					array( 'name' => 'mi-kit/captura.png', 'data' => 'png' ),
					array( 'name' => 'mi-kit/.DS_Store', 'data' => 'x' ),
				)
			),
			'ZIP de marketplace con documentación fuera del kit'
		);
		$check( 'mi-kit/' === $market->prefix() && array( 'manifest.json', 'templates/1.json' ) === $market->paths() && 4 === $market->ignored(), 'Del marketplace sólo cuentan los JSON del kit: la documentación y las capturas se ignoran (y __MACOSX y los ocultos ni se cuentan).' );
		$market->cleanup();
		$nested = $accepts( $zip_raw( array( array( 'name' => 'leeme.txt', 'data' => 'x' ), array( 'name' => 'kit.zip', 'data' => $inner_ok ) ) ), 'Un ZIP interior con manifiesto' );
		$check( $nested->nested() && $nested->has( 'manifest.json' ) && is_file( $nested->zip() ), 'El ZIP interior se lee desde una copia temporal.' );
		$copy = $nested->zip();
		$nested->cleanup();
		$check( ! is_file( $copy ), 'La copia temporal del ZIP interior se borra al terminar.' );

		$big = array(
			array( 'name' => 'kit/manifest.json', 'data' => $m ),
			array( 'name' => 'kit/templates/1.json', 'data' => '{"content":[]}' ),
			array( 'name' => 'kit/digitalisimo/media/' . $sha . '.mp4', 'raw' => 'x', 'size' => 31 * 1048576, 'comp' => 31 * 1048576 ),
		);
		for ( $i = 3; $i < 4999; $i++ ) {
			$big[] = array(
				'name' => 'docs/' . $i . '.txt',
				'data' => 'x',
			);
		}
		$large = $accepts( $zip_raw( $big ), 'Kit legítimo grande (4 999 entradas, 31 MB)' );
		$check( $large->bytes() >= 31 * 1048576, 'El kit grande cuenta sus bytes declarados.' );
		$large->cleanup();

		$limits = Zip_Guard::limits();
		$check( 5000 === $limits['entries'] && 100 === $limits['ratio'] && 33554432 === $limits['json'] && 67108864 === $limits['xml'] && 536870912 === $limits['unzipped'] && 1 === $limits['nesting'] && 20 === $limits['request_network'], 'Límites de D13.' );
		$GLOBALS['kit_filters']['digitalisimo_elements_kit_guard_limits'] = function ( $limits ) {
			$limits['entries'] = 3;
			$limits['ratio']   = -5;
			return $limits;
		};
		$filtered = Zip_Guard::limits();
		$check( 3 === $filtered['entries'] && 100 === $filtered['ratio'], 'El filtro de límites cambia lo que pide con números positivos y descarta el resto.' );
		$rejects( $zip_raw( $many( 4 ) ), '3', 'Límite de entradas del filtro' );
		unset( $GLOBALS['kit_filters']['digitalisimo_elements_kit_guard_limits'] );

		// ── 3. Normalizador ─────────────────────────────────────────────────────────────────────────────────────
		$png      = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' );
		$png_sha  = hash( 'sha256', $png );
		$svg      = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1" onload="alert(2)"/></svg>';
		$svg_sha  = hash( 'sha256', $svg );
		$fake_sha = hash( 'sha256', 'otro contenido' );
		$uploads  = 'https://a.test/wp-content/uploads';
		$own      = array(
			'name'          => 'propio',
			'title'         => 'Kit propio',
			'version'       => '3.0',
			'site'          => 'https://a.test',
			'thumbnail'     => $uploads . '/sin-archivo.png',
			'site-settings' => array(
				'theme'        => true,
				'experiments'  => true,
				'globalColors' => true,
			),
			'plugins'       => array(
				array(
					'name'   => 'PRO Elements',
					'plugin' => 'pro-elements/pro-elements',
				),
				array(
					'name'   => 'WooCommerce',
					'plugin' => 'woocommerce/woocommerce',
				),
			),
			'templates'     => array(
				'10'      => array(
					'title'     => 'Sección',
					'doc_type'  => 'section',
					'thumbnail' => $uploads . '/img.png',
					'source'    => '../../templates/10.json',
				),
				'11'      => array(
					'title'    => 'Rara',
					'doc_type' => 'acme-doc',
				),
				'../evil' => array(
					'title'    => 'Mala',
					'doc_type' => 'section',
				),
				'12'      => array(
					'title'    => 'Sin archivo',
					'doc_type' => 'section',
				),
			),
			'content'       => array(
				'page'    => array(
					'20' => array(
						'title'    => 'Inicio',
						'doc_type' => 'wp-page',
					),
				),
				'acme_cpt' => array(
					'30' => array(
						'title'    => 'Del tipo que falta',
						'doc_type' => 'wp-post',
					),
				),
			),
			'digitalisimo'  => array(
				'v'       => 1,
				'home'    => 'https://a.test',
				'site'    => 'https://a.test',
				'uploads' => $uploads,
				'kit'     => sha1( 'https://a.test|propio' ),
			),
		);
		$kit_file = $zip_files(
			array(
				'manifest.json'                                   => $own,
				'site-settings.json'                              => array(
					'settings'    => array(
						'site_name'        => 'A',
						'site_description' => 'Sitio A',
						'site_logo'        => array( 'url' => $uploads . '/logo.png' ),
						'site_favicon'     => array( 'url' => $uploads . '/favicon.png' ),
						'custom_colors'    => array(
							array(
								'_id'   => 'c1',
								'title' => 'Uno',
								'color' => '#111111',
							),
						),
					),
					'theme'       => array( 'slug' => 'tema-x' ),
					'experiments' => array( 'container' => array( 'state' => 'active' ) ),
				),
				'templates/10.json'                               => array(
					'content'  => array(
						array(
							'id'       => 's1',
							'elType'   => 'section',
							'elements' => array(
								array(
									'id'         => 'w1',
									'elType'     => 'widget',
									'widgetType' => 'acme-hero',
									'settings'   => array(
										'link'                  => array( 'url' => 'https://otro.test/x' ),
										'typography_font_family' => 'Roboto',
									),
								),
							),
						),
					),
					'metadata' => array(
						'objeto'    => 'O:8:"stdClass":1:{s:1:"a";s:1:"b";}',
						'anidado'   => 'a:1:{s:1:"x";O:8:"stdClass":0:{}}',
						'lista'     => 'a:1:{s:1:"k";s:1:"v";}',
						'_elementor_data' => '[]',
						'enlace'    => 'https://a.test/pagina/',
						'externo'   => 'https://otro.test/',
						'falso'     => 'b:0;',
					),
				),
				'templates/11.json'                               => array( 'content' => array() ),
				'templates/evil.json'                             => array( 'content' => array() ),
				'content/page/20.json'                            => array( 'content' => array( array( 'id' => 'p1', 'elType' => 'container', 'elements' => array() ) ) ),
				'content/acme_cpt/30.json'                        => array( 'content' => array() ),
				'digitalisimo/media.json'                         => array(
					$uploads . '/img.png'  => array(
						'file'   => $png_sha . '.png',
						'sha256' => $png_sha,
						'mime'   => 'image/png',
					),
					$uploads . '/x.svg'    => array(
						'file'   => $svg_sha . '.svg',
						'sha256' => $svg_sha,
					),
					$uploads . '/otra.png' => array(
						'file'   => $fake_sha . '.png',
						'sha256' => $fake_sha,
					),
				),
				'digitalisimo/media/' . $png_sha . '.png'         => $png,
				'digitalisimo/media/' . $svg_sha . '.svg'         => $svg,
				'digitalisimo/media/' . $fake_sha . '.png'        => $png,
				'wp-content/page/page.xml'                        => '<?xml version="1.0"?><rss></rss>',
			)
		);
		$inspection = Zip_Guard::inspect( $kit_file );
		$normalized = Normalizer::run( $inspection );
		$manifest   = $normalized->manifest();
		$requests   = $normalized->requests();
		$analysis   = $normalized->analysis();
		$check( Normalizer::FORMAT_KIT3 === $normalized->format() && sha1( 'https://a.test|propio' ) === $normalized->kit(), 'Un kit propio 3.0 conserva su formato y su huella.' );
		$check( array( 'format' => '3.0', 'kit' => sha1( 'https://a.test|propio' ), 'home' => 'https://a.test', 'site' => 'https://a.test', 'uploads' => $uploads ) === $normalized->origin(), 'Origen: home, site y uploads del manifiesto.' );
		$check( true === $requests['theme'] && array( 'container' => 'active' ) === $requests['experiments'] && array( 'WooCommerce' ) === $requests['plugins'] && 1 === $requests['builder_plugins'], 'Los pedidos van al análisis y los plugins del constructor no se nombran (CR-18).' );
		$check( false === $manifest['site-settings']['theme'] && false === $manifest['site-settings']['experiments'] && true === $manifest['site-settings']['globalColors'], 'El manifiesto ya no pide tema ni experimentos (D3).' );
		$site = $normalized->json( 'site-settings.json' );
		$check( ! isset( $site['settings']['site_name'], $site['settings']['site_description'], $site['settings']['site_logo'], $site['settings']['site_favicon'], $site['theme'], $site['experiments'] ) && isset( $site['settings']['custom_colors'] ), 'La identidad del sitio, el tema y los experimentos no viajan al importador.' );
		$meta = $normalized->json( 'templates/10.json' )['metadata'];
		$check( ! isset( $meta['objeto'] ) && ! isset( $meta['anidado'] ) && ! isset( $meta['_elementor_data'] ), 'metadata: los serializados con objetos y las metas protegidas se descartan.' );
		$check( array( 'k' => 'v' ) === $meta['lista'] && false === $meta['falso'] && 'https://b.test/pagina/' === $meta['enlace'] && 'https://otro.test/' === $meta['externo'], 'metadata: un array serializado queda como array y el prefijo del origen pasa a home_url().' );
		$check( 2 === $analysis['hostile']['metadata.object'] && 1 === $analysis['hostile']['metadata.protected'] && 1 <= $analysis['hostile']['manifest.template'], 'Lo hostil del kit se cuenta en el análisis.' );
		$check( '' === $manifest['thumbnail'] && $uploads . '/img.png' === $manifest['templates']['10']['thumbnail'] && 'templates/10.json' !== $manifest['templates']['10']['source'] && '10.json' === $manifest['templates']['10']['source'], 'Miniaturas sólo si vienen en el paquete; source reducido a su nombre.' );
		$check( array( '10', '11' ) === array_map( 'strval', array_keys( $manifest['templates'] ) ), 'Una clave del manifiesto insegura o sin archivo se descarta.' );
		$check( 1 === $analysis['unknown_documents']['acme-doc'] && ! isset( $analysis['unknown_documents']['section'] ) && 1 === $analysis['missing_post_types']['acme_cpt'], 'El análisis cuenta tipos de documento y de entrada que el sitio no tiene (CC-4).' );
		$check( 1 === $analysis['widgets']['acme-hero'] && array( 'Roboto' ) === $analysis['google_fonts'] && array( 'otro.test' => 2 ) === $analysis['hosts'], 'El análisis cuenta widgets, fuentes de Google y hosts externos (ni el origen ni este sitio).' );
		$check( 3 === $analysis['media']['embedded'] && 1 === $analysis['blocked_types']['svg'], 'Medios del paquete y tipos que el sitio bloquea (SVG sin la subida sin filtrar, D15).' );

		$clean_zip = $dir . '/limpio.zip';
		$normalized->write_zip( $clean_zip );
		$clean = new ZipArchive();
		$clean->open( $clean_zip );
		$names = array();
		for ( $i = 0; $i < $clean->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$names[] = $clean->getNameIndex( $i );
		}
		sort( $names );
		$check( array( 'content/acme_cpt/30.json', 'content/page/20.json', 'digitalisimo/media.json', 'manifest.json', 'site-settings.json', 'templates/10.json', 'templates/11.json', 'templates/evil.json', 'wp-content/page/page.xml' ) === $names, 'El ZIP limpio sólo lleva json y xml: ' . implode( ', ', $names ) );
		$written = json_decode( $clean->getFromName( 'manifest.json' ), true );
		$check( false === $written['site-settings']['theme'] && ! isset( $written['templates']['../evil'] ), 'El ZIP limpio lleva el manifiesto normalizado.' );
		$clean->close();

		// ── 5. Medios: sha256 y SVG ─────────────────────────────────────────────────────────────────────────────
		$staged = $dir . '/staged';
		mkdir( $staged );
		$stage = $normalized->stage( $staged );
		$check( 2 === $stage['embedded'] && 1 === $stage['rejected']['sha256'] && 1 === $stage['svg_sanitized'], 'Un sha256 distinto se descarta y el SVG se sanea: ' . json_encode( $stage ) );
		$check( is_file( $staged . '/media/' . $png_sha . '.png' ) && ! is_file( $staged . '/media/' . $fake_sha . '.png' ), 'Sólo se prepara lo que coincide con su sha256.' );
		$clean_svg = file_get_contents( $staged . '/media/' . $svg_sha . '.svg' );
		$check( false === stripos( $clean_svg, '<script' ) && false === stripos( $clean_svg, 'onload' ) && false !== stripos( $clean_svg, '<rect' ), 'El SVG queda sin scripts ni manejadores.' );
		$staged_media = json_decode( file_get_contents( $staged . '/media.json' ), true );
		$check( array( $uploads . '/img.png', $uploads . '/x.svg' ) === array_keys( $staged_media ) && 'image/png' === $staged_media[ $uploads . '/img.png' ]['mime'], 'media.json preparado sólo con lo que pasó la comprobación.' );
		$inspection->cleanup();

		// ── 4. Template Kit de Envato sintético ─────────────────────────────────────────────────────────────────
		$envato = $zip_files(
			array(
				'manifest.json'          => array(
					'manifest_version' => '1.0.0',
					'title'            => 'Kit Sintético',
					'page_builder'     => 'elementor',
					'required_plugins' => array(
						array(
							'name'    => 'Elementor',
							'version' => '3.20',
							'file'    => 'elementor/elementor.php',
						),
						array(
							'name'    => 'Formularios Acme',
							'version' => '1.0',
							'file'    => 'acme-forms/acme-forms.php',
						),
					),
					'templates'        => array(
						array(
							'name'     => 'Estilos globales',
							'source'   => 'templates/global.json',
							'type'     => 'section',
							'metadata' => array( 'template_type' => 'global-styles' ),
						),
						array(
							'name'     => 'Cabecera',
							'source'   => 'templates/header.json',
							'type'     => 'header',
							'metadata' => array(
								'template_type'            => 'section-header',
								'elementor_pro_conditions' => array( 'include/general', 'exclude/singular/page/12' ),
								'include_in_zip'           => '1',
							),
						),
						array(
							'name'     => 'Bloque raro',
							'source'   => 'templates/rara.json',
							'type'     => 'acme-type',
							'metadata' => array( 'template_type' => 'acme' ),
						),
					),
				),
				'templates/global.json'  => array(
					'content'       => array(),
					'page_settings' => array(
						'site_name'     => 'Autor',
						'system_colors' => array(
							array(
								'_id'   => 'primary',
								'title' => 'Primario',
								'color' => '#ff0000',
							),
						),
					),
				),
				'templates/header.json'  => array(
					'content'       => array( array( 'id' => 'h1', 'elType' => 'container', 'elements' => array() ) ),
					'page_settings' => array(),
					'metadata'      => array(
						'template_type'            => 'section-header',
						'elementor_pro_conditions' => array( 'include/general', 'exclude/singular/page/12' ),
					),
				),
				'templates/rara.json'    => array(
					'content'  => array( array( 'id' => 'r1', 'elType' => 'section', 'elements' => array() ) ),
					'metadata' => array( 'template_type' => 'acme' ),
				),
				'screenshots/header.jpg' => 'jpg',
			)
		);
		$inspection = Zip_Guard::inspect( $envato );
		$converted  = Normalizer::run( $inspection );
		$manifest   = $converted->manifest();
		$check( Normalizer::FORMAT_ENVATO === $converted->format() && $converted->converted(), 'Un Template Kit se reconoce y se convierte (D1: siempre «agregar»).' );
		$check( '3.0' === $manifest['version'] && ! isset( $manifest['manifest_version'] ), 'El manifiesto convertido es 3.0.' );
		$check( sha1( 'Kit Sintético|global.json,header.json,rara.json' ) === $converted->kit(), 'Huella de un Template Kit: título y fuentes ordenadas (§9.6).' );
		$global = $converted->json( 'site-settings.json' );
		$check( isset( $global['settings']['system_colors'] ) && ! isset( $global['settings']['site_name'] ), 'Los estilos globales pasan a site-settings.json, sin identidad.' );
		$keys = array_map( 'strval', array_keys( $manifest['templates'] ) );
		$check( 2 === count( $keys ) && array() === array_filter( $keys, function ( $key ) {
			return ! ctype_digit( $key );
		} ), 'Las plantillas convertidas reciben claves numéricas (§5.2): ' . implode( ', ', $keys ) );
		$header = null;
		$rare   = null;
		foreach ( $manifest['templates'] as $key => $item ) {
			if ( 'header' === $item['doc_type'] ) {
				$header = (string) $key;
			} elseif ( 'section' === $item['doc_type'] ) {
				$rare = (string) $key;
			}
		}
		$check( null !== $header && null !== $rare && 'acme-type' === $manifest['templates'][ $rare ]['original_type'], 'Un doc_type desconocido entra como section con su tipo original (CC-4).' );
		$check( 1 === count( $manifest['templates'][ $header ]['conditions'] ), 'conditions del manifiesto: sólo la primera include sin sub_id.' );
		$builder = $converted->json( 'digitalisimo/theme-builder.json' );
		$check( isset( $builder[ $header ] ) && 2 === count( $builder[ $header ]['conditions'] ) && 1 === count( $builder ), 'La lista completa de condiciones va a theme-builder.json con la clave de la plantilla (V-24).' );
		foreach ( $keys as $key ) {
			$file = $converted->json( 'templates/' . $key . '.json' );
			$check( is_array( $file ) && array() === $file['metadata'], "La plantilla {$key} llega con metadata limpia (CC-21)." );
		}
		$requests = $converted->requests();
		$check( array( 'Formularios Acme' ) === $requests['plugins'] && 1 === $requests['builder_plugins'], 'required_plugins pasa al análisis sin nombrar el constructor.' );
		$inspection->cleanup();

		$check( array( 'manifest.json' => array( 'templates' => array( '7' => array( 'doc_type' => 'x' ), 'a' => array( 'doc_type' => 'y' ) ) ), 'templates/7.json' => array(), 'templates/a.json' => array( 'k' => 1 ), 'digitalisimo/theme-builder.json' => array( 'a' => array( 'c' => 1 ) ) ) !== Normalizer::renumber( array( 'manifest.json' => array( 'templates' => array( '7' => array( 'doc_type' => 'x' ), 'a' => array( 'doc_type' => 'y' ) ) ), 'templates/7.json' => array(), 'templates/a.json' => array( 'k' => 1 ), 'digitalisimo/theme-builder.json' => array( 'a' => array( 'c' => 1 ) ) ) ), 'renumber() cambia las claves no numéricas.' );
		$renumbered = Normalizer::renumber( array( 'manifest.json' => array( 'templates' => array( '7' => array( 'doc_type' => 'x' ), 'a' => array( 'doc_type' => 'y' ) ) ), 'templates/7.json' => array(), 'templates/a.json' => array( 'k' => 1 ), 'digitalisimo/theme-builder.json' => array( 'a' => array( 'c' => 1 ) ) ) );
		$check( isset( $renumbered['templates/7.json'], $renumbered['templates/8.json'] ) && ! isset( $renumbered['templates/a.json'] ) && array( 'k' => 1 ) === $renumbered['templates/8.json'] && array( 8 => array( 'c' => 1 ) ) === $renumbered['digitalisimo/theme-builder.json'], 'renumber(): una clave numérica no cambia y las demás siguen desde la mayor.' );
	} finally {
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		rmdir( $dir );
	}

	echo "Kits: guardián del ZIP, normalizador, conversión de Template Kits y medios preparados validados (D13, CR-3, CR-19, CV-22, CC-4, CC-21).\n";
}
