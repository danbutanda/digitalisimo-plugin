<?php
/**
 * Utilidades comunes de kit-roundtrip (etapa K, §13.3): las cargan setup-a.php, setup-b.php, mu-kit.php y
 * signature.php dentro del sitio de Playground. Sin dependencias de K: el arnés no puede medir el importador con su
 * propio código.
 *
 * Carpetas (las monta kit-roundtrip.sh):
 * - /wordpress/wp-content/probe         carpeta del sitio en $OUT (a/, b/ o net/): resultados y registros.
 * - /wordpress/wp-content/digi-shared   $OUT/shared: casos, kits generados, medios remotos, exportaciones y wp-cli.
 */

if ( ! defined( 'DIGI_K_PROBE' ) ) {
	define( 'DIGI_K_PROBE', '/wordpress/wp-content/probe' );
	define( 'DIGI_K_SHARED', '/wordpress/wp-content/digi-shared' );
}

/** Opciones del arnés que escribe kit-roundtrip.sh en probe/options.json (site, woo, slow, core, token…). */
function digi_k_options() {
	static $options = null;
	if ( null === $options ) {
		$options = json_decode( (string) @file_get_contents( DIGI_K_PROBE . '/options.json' ), true );
		$options = is_array( $options ) ? $options : array();
	}
	return $options;
}

function digi_k_fail( $message ) {
	file_put_contents( DIGI_K_PROBE . '/setup-error.txt', $message . "\n", FILE_APPEND );
}

function digi_k_write( $name, $data ) {
	file_put_contents( DIGI_K_PROBE . '/' . $name, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}

function digi_k_read_json( $file ) {
	$data = json_decode( (string) @file_get_contents( $file ), true );
	return is_array( $data ) ? $data : array();
}

/**
 * PNG de un color, determinista y sin GD (filas sin filtro, zlib de PHP con nivel fijo).
 *
 * @param int   $w   Ancho.
 * @param int   $h   Alto.
 * @param array $rgb Color.
 */
function digi_k_png( $w, $h, array $rgb ) {
	$row = "\0" . str_repeat( chr( $rgb[0] ) . chr( $rgb[1] ) . chr( $rgb[2] ), $w );
	$raw = str_repeat( $row, $h );
	$chunk = static function ( $type, $data ) {
		return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) );
	};
	return "\x89PNG\r\n\x1a\n" . $chunk( 'IHDR', pack( 'NNCCCCC', $w, $h, 8, 2, 0, 0, 0 ) ) . $chunk( 'IDAT', gzcompress( $raw, 9 ) ) . $chunk( 'IEND', '' );
}

/** Bytes de un medio de cases.json, con la firma que reconoce finfo (el kit comprueba el tipo real). */
function digi_k_media_bytes( array $spec ) {
	if ( isset( $spec['png'] ) ) {
		return array( digi_k_png( (int) ( $spec['w'] ?? 8 ), (int) ( $spec['h'] ?? 8 ), $spec['png'] ), 'png', 'image/png' );
	}
	if ( isset( $spec['svg'] ) ) {
		return array( (string) $spec['svg'], 'svg', 'image/svg+xml' );
	}
	if ( isset( $spec['svg_file'] ) ) {
		// SVG sintético de tests/sources/kits/media/ (kit-roundtrip.sh lo copia a digi-shared/media), en la forma que deja
		// el saneador del núcleo: K lo vuelve a sanear en B y la copia de máscaras (masks/<sha8>.svg) debe coincidir.
		$svg = (string) @file_get_contents( DIGI_K_SHARED . '/media/' . basename( (string) $spec['svg_file'] ) );
		if ( '' !== $svg && class_exists( '\\Elementor\\Core\\Utils\\Svg\\Svg_Sanitizer' ) ) {
			$clean = ( new \Elementor\Core\Utils\Svg\Svg_Sanitizer() )->sanitize( $svg );
			$svg   = is_string( $clean ) && '' !== $clean ? $clean : $svg;
		}
		return array( $svg, 'svg', 'image/svg+xml' );
	}
	if ( isset( $spec['csv'] ) ) {
		return array( (string) $spec['csv'], 'csv', 'text/csv' );
	}
	if ( isset( $spec['pdf'] ) ) {
		$text = preg_replace( '/[^A-Za-z0-9 ]/', '', (string) $spec['pdf'] );
		$body = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 100]/Contents 4 0 R>>endobj\n4 0 obj<</Length " . ( 26 + strlen( $text ) ) . ">>stream\nBT /F1 12 Tf 10 50 Td (" . $text . ") Tj ET\nendstream endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
		return array( $body, 'pdf', 'application/pdf' );
	}
	if ( ! empty( $spec['mp3'] ) ) {
		// Cabecera ID3v2.3 vacía y tramas MPEG-1 capa III de silencio.
		$frame = "\xFF\xFB\x90\x64" . str_repeat( "\0", 413 );
		return array( "ID3\x03\x00\x00\x00\x00\x00\x00" . str_repeat( $frame, 8 ), 'mp3', 'audio/mpeg' );
	}
	if ( ! empty( $spec['mp4'] ) ) {
		$ftyp = pack( 'N', 24 ) . 'ftypisom' . pack( 'N', 512 ) . 'isommp41';
		$free = pack( 'N', 16 ) . 'free' . str_repeat( "\0", 8 );
		return array( $ftyp . $free, 'mp4', 'video/mp4' );
	}
	if ( ! empty( $spec['woff2'] ) ) {
		// Cabecera WOFF2 (firma wOF2, sfnt TrueType) con el resto a cero: suficiente para el tipo real.
		$body = 'wOF2' . pack( 'N', 0x00010000 ) . pack( 'N', 96 ) . pack( 'n', 1 ) . pack( 'n', 0 ) . pack( 'N', 0 ) . pack( 'N', 0 ) . pack( 'n', 1 ) . pack( 'n', 0 ) . str_repeat( "\0", 68 );
		return array( $body, 'woff2', 'font/woff2' );
	}
	return array( '', 'bin', 'application/octet-stream' );
}

/**
 * Crea (o devuelve) un adjunto con su archivo en uploads, sus tamaños si es imagen y la meta alt.
 *
 * @param string $name Nombre del medio en cases.json.
 * @param array  $spec Descripción del medio.
 */
function digi_k_attachment( $name, array $spec ) {
	$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'name' => 'digi-k-' . $name, 'posts_per_page' => 1, 'fields' => 'ids' ) );
	if ( $existing ) {
		return (int) $existing[0];
	}
	list( $bytes, $ext, $mime ) = digi_k_media_bytes( $spec );
	// El arnés sube como lo haría un administrador con los tipos que el constructor habilita (SVG, fuentes, CSV).
	$allow = static function ( $mimes ) {
		return $mimes + array( 'svg' => 'image/svg+xml', 'woff2' => 'font/woff2', 'csv' => 'text/csv' );
	};
	add_filter( 'upload_mimes', $allow, 99 );
	$upload = wp_upload_bits( 'digi-k-' . $name . '.' . $ext, null, $bytes );
	remove_filter( 'upload_mimes', $allow, 99 );
	if ( ! empty( $upload['error'] ) ) {
		digi_k_fail( "No se pudo subir el medio {$name}: " . $upload['error'] );
		return 0;
	}
	$id = wp_insert_attachment(
		array(
			'post_mime_type' => $mime,
			'post_title'     => (string) ( $spec['title'] ?? $name ),
			'post_name'      => 'digi-k-' . $name,
			'post_status'    => 'inherit',
		),
		$upload['file']
	);
	if ( ! $id || is_wp_error( $id ) ) {
		digi_k_fail( "No se pudo crear el adjunto {$name}" );
		return 0;
	}
	if ( 0 === strpos( $mime, 'image/' ) && 'image/svg+xml' !== $mime ) {
		$meta = wp_generate_attachment_metadata( $id, $upload['file'] );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( $id, $meta );
		}
	}
	if ( ! empty( $spec['alt'] ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $spec['alt'] );
	}
	return (int) $id;
}

/**
 * Contexto de resolución de fichas de cases.json: IDs ya creados y referencias anotadas para signature.php.
 */
final class Digi_K_Refs {
	/** @var array<string,int> medio => ID */
	public $media = array();
	/** @var array lista de { doc, element, path, kind, token } */
	public $refs = array();
	/** @var string clave del documento que se está resolviendo */
	public $doc = '';
	/** @var string ID del elemento que se está resolviendo */
	public $element = '';
	/** @var array fichas que no se pudieron resolver */
	public $missing = array();

	public function post_id( $type, $slug ) {
		$found = get_posts( array( 'post_type' => $type, 'name' => $slug, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
		return $found ? (int) $found[0] : 0;
	}

	public function template_id( $slug ) {
		return $this->post_id( 'elementor_library', $slug );
	}

	public function term_id( $taxonomy, $slug ) {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		return $term ? (int) $term->term_id : 0;
	}

	public function user_id( $login ) {
		$user = get_user_by( 'login', $login );
		return $user ? (int) $user->ID : 0;
	}

	public function media_id( $name ) {
		return (int) ( $this->media[ $name ] ?? 0 );
	}

	/** Anota una referencia para la firma (ruta dentro de los ajustes del elemento). */
	public function note( $path, $kind, $token ) {
		$this->refs[] = array( 'doc' => $this->doc, 'element' => $this->element, 'path' => $path, 'kind' => $kind, 'token' => $token );
	}

	/** Resuelve una ficha «@…»; devuelve array( valor, tipo ) o null si no es ficha. */
	public function token( $value ) {
		if ( ! is_string( $value ) || '' === $value || '@' !== $value[0] || ! preg_match( '/^@([a-z-]+):(.+)$/', $value, $m ) ) {
			return null;
		}
		list( , $kind, $arg ) = $m;
		$int  = '-int' === substr( $kind, -4 );
		$base = $int ? substr( $kind, 0, -4 ) : $kind;
		$id   = 0;
		switch ( $base ) {
			case 'media':
				$id = $this->media_id( $arg );
				return array( $id ? array( 'id' => $id, 'url' => (string) wp_get_attachment_url( $id ) ) : array( 'id' => '', 'url' => '' ), 'attachment' );
			case 'media-url':
				$id = $this->media_id( $arg );
				return array( $id ? (string) wp_get_attachment_url( $id ) : '', 'media-url' );
			case 'media-ids':
				$ids = array();
				foreach ( explode( ',', $arg ) as $name ) {
					$ids[] = $this->media_id( trim( $name ) );
				}
				return array( implode( ',', $ids ), 'attachment-list' );
			case 'media-id':
				$id = $this->media_id( $arg );
				return array( $int ? $id : (string) $id, 'attachment' );
			case 'post':
				list( $type, $slug ) = array_pad( explode( ':', $arg, 2 ), 2, '' );
				$id = $this->post_id( $type, $slug );
				break;
			case 'template':
				$id = $this->template_id( $arg );
				break;
			case 'term':
				list( $taxonomy, $slug ) = array_pad( explode( ':', $arg, 2 ), 2, '' );
				$id = $this->term_id( $taxonomy, $slug );
				break;
			case 'menu':
				$id = $this->term_id( 'nav_menu', $arg );
				break;
			case 'user':
				$id = $this->user_id( $arg );
				break;
			case 'url':
				return array( home_url( $arg ), 'url' );
			default:
				$this->missing[] = $value;
				return array( $value, 'desconocida' );
		}
		if ( ! $id ) {
			$this->missing[] = $value;
		}
		$kinds = array( 'post' => 'post', 'template' => 'template', 'term' => 'term', 'menu' => 'menu', 'user' => 'user' );
		return array( $int ? $id : (string) $id, $kinds[ $base ] );
	}

	/** Sustituye {{home}}, {{uploads}} y las fichas «@…» dentro de un texto (JSON incrustado o etiquetas dinámicas). */
	public function inline( $text, $path ) {
		$uploads = wp_upload_dir( null, false );
		$text    = str_replace( array( '{{home}}', '{{uploads}}' ), array( untrailingslashit( home_url() ), untrailingslashit( $uploads['baseurl'] ) ), $text );
		return preg_replace_callback(
			'/@([a-z-]+):([A-Za-z0-9_:.-]+)/',
			function ( $m ) use ( $path ) {
				$resolved = $this->token( $m[0] );
				if ( null === $resolved || is_array( $resolved[0] ) ) {
					return $m[0];
				}
				$this->note( $path, 'inline:' . $resolved[1], $m[0] );
				return (string) $resolved[0];
			},
			$text
		);
	}

	/** Resuelve recursivamente un valor de ajustes; $path es la ruta con puntos. */
	public function resolve( $value, $path = '' ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $item ) {
				$out[ $key ] = $this->resolve( $item, '' === $path ? (string) $key : $path . '.' . $key );
			}
			return $out;
		}
		$resolved = $this->token( $value );
		if ( null !== $resolved ) {
			$this->note( $path, $resolved[1], $value );
			return $resolved[0];
		}
		if ( is_string( $value ) && ( false !== strpos( $value, '{{' ) || false !== strpos( $value, '@' ) ) ) {
			return $this->inline( $value, $path );
		}
		return $value;
	}

	/** Resuelve un árbol de elementos, anotando las referencias por elemento. */
	public function elements( array $elements ) {
		$out = array();
		foreach ( $elements as $element ) {
			$this->element      = (string) ( $element['id'] ?? '' );
			$element['settings'] = $this->resolve( (array) ( $element['settings'] ?? array() ) );
			$element['elements'] = $this->elements( (array) ( $element['elements'] ?? array() ) );
			$out[]              = $element;
		}
		$this->element = '';
		return $out;
	}
}

/**
 * Crea un documento del constructor y lo guarda con Document::save() (como el editor), o lo reutiliza.
 *
 * @param string $type      Tipo de documento (wp-page, wp-post, section, header…).
 * @param string $post_type Tipo de entrada.
 * @param string $slug      Slug.
 * @param string $title     Título.
 * @param array  $elements  Elementos ya resueltos.
 * @param array  $settings  Ajustes del documento ya resueltos.
 * @param array  $post      Campos extra de la entrada (post_parent, post_author…).
 */
function digi_k_document( $type, $post_type, $slug, $title, array $elements, array $settings = array(), array $post = array() ) {
	$id = digi_k_reserve( $type, $post_type, $slug, $title, $post );
	if ( ! $id ) {
		return 0;
	}
	$document = \Elementor\Plugin::$instance->documents->get( $id, false );
	// Loop::save() no devuelve nada: sólo false es un fallo.
	if ( ! $document || false === $document->save( array( 'elements' => $elements, 'settings' => $settings ) ) ) {
		digi_k_fail( "Document::save() falló en {$post_type}:{$slug}" );
	}
	return $id;
}

/** Crea un documento vacío (o devuelve el que ya existe con ese slug) para guardarlo después. */
function digi_k_reserve( $type, $post_type, $slug, $title, array $post = array() ) {
	$found = get_posts( array( 'post_type' => $post_type, 'name' => $slug, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
	if ( $found ) {
		return (int) $found[0];
	}
	$document = \Elementor\Plugin::$instance->documents->create( $type, array( 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish', 'post_type' => $post_type ) + $post );
	if ( ! $document || is_wp_error( $document ) ) {
		digi_k_fail( "No se pudo crear el documento {$post_type}:{$slug} ({$type})" );
		return 0;
	}
	$id = (int) $document->get_main_id();
	wp_update_post( array( 'ID' => $id, 'post_name' => $slug, 'post_title' => $title, 'post_status' => 'publish' ) + $post );
	return $id;
}

/**
 * Guarda las condiciones del Theme Builder («include/singular/page/12») con el gestor de PRO.
 *
 * @param int   $id         Plantilla.
 * @param array $conditions Condiciones ya resueltas.
 */
function digi_k_conditions( $id, array $conditions ) {
	if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
		digi_k_fail( 'El Theme Builder no está cargado: no se guardaron condiciones' );
		return;
	}
	$rows = array();
	foreach ( $conditions as $condition ) {
		$rows[] = array_pad( explode( '/', $condition ), 4, '' );
	}
	\ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->save_conditions( $id, array_map(
		static function ( $row ) {
			return array( 'type' => $row[0], 'name' => $row[1], 'sub_name' => $row[2], 'sub_id' => $row[3] );
		},
		$rows
	) );
}

/** Ajustes de un caso de widget: «base» de front-budget más «settings» (__IMAGE__ → imagen-a). */
function digi_k_widget_settings( array $case, array $budget ) {
	$settings = array();
	if ( ! empty( $case['base'] ) && preg_match( '/^front-budget:(.+)$/', $case['base'], $m ) ) {
		foreach ( (array) ( $budget[ $m[1] ]['widgets'] ?? array() ) as $widget ) {
			$settings = (array) ( $widget['settings'] ?? array() );
			break;
		}
		$json     = str_replace( array( '"__IMAGE__"', '__SITE__' ), array( '"@media:imagen-a"', '{{home}}' ), wp_json_encode( $settings ) );
		$settings = (array) json_decode( $json, true );
	}
	return array_replace( $settings, (array) ( $case['settings'] ?? array() ) );
}

/** ID de elemento determinista de 7 hex. */
function digi_k_eid( $prefix, $seed ) {
	return $prefix . substr( md5( $seed ), 0, 6 );
}

/** Borra el contenido que trae WordPress (entrada, página, privacidad y comentario), como en un sitio vacío (CV-15). */
function digi_k_clear_defaults() {
	foreach ( array( array( 'hello-world', 'post' ), array( 'sample-page', 'page' ), array( 'privacy-policy', 'page' ) ) as $default ) {
		$post = get_page_by_path( $default[0], OBJECT, $default[1] );
		if ( $post ) {
			wp_delete_post( $post->ID, true );
		}
	}
	foreach ( get_comments( array( 'fields' => 'ids' ) ) as $comment ) {
		wp_delete_comment( $comment, true );
	}
	update_option( 'wp_page_for_privacy_policy', 0 );
}

/** Registro de errores fatales y de la duración de cada petición REST (mu-plugin). */
function digi_k_install_mu( $site ) {
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
		mkdir( WPMU_PLUGIN_DIR, 0777, true );
	}
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-k.php', '<?php require "' . DIGI_K_PROBE . '/mu-kit.php";' );
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-k-net.php', '<?php require "' . DIGI_K_PROBE . '/net-sim.php";' );
}
