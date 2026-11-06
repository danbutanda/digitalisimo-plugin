<?php
/**
 * F8-D17 · imágenes de ejemplo de Element Pack copiadas a la biblioteca de medios (DIGITALÍSIMO Migración).
 *
 * `Demo_Media` es común a Prime Slider y a Element Pack: cada URL de la carpeta de un Element Pack (Lite o Pro, instalado
 * o no) que llega a un widget convertido pasa a una copia en Medios, nunca a la carpeta del plugin. Los valores por
 * defecto de los mapas la piden con `{{ep_assets}}` (Animated Card: la lata `coco-can.svg`, no la imagen de ejemplo del
 * constructor). Cubre: la URL de los valores por defecto (instalado, cargado y ausente), la conversión con la subida sin
 * filtrar apagada y encendida (SVG saneado), deduplicación, modo puro (análisis y editor), URL guardadas en repetidores,
 * rutas fuera de `assets/images/`, plugin borrado, el modo restringido de 1.0.20, el informe y la reversión. Un valor por
 * defecto que no se copia (SVG con la subida sin filtrar apagada, que es lo habitual, o sin Element Pack instalado) se
 * queda con la imagen de ejemplo del constructor, la de la conversión anterior, nunca vacío (`core.demo.placeholder`).
 *
 * Con php-wasm sale con 0 aunque falle: se juzga por la línea final (y por «Fatal error», «Uncaught» o «FALLA»).
 */

namespace Elementor\Core\Utils\Svg {
	if ( ! class_exists( Svg_Sanitizer::class, false ) ) {
		/** Saneador SVG mínimo (el del núcleo no se carga en la prueba): quita scripts y manejadores de eventos. */
		final class Svg_Sanitizer {
			public function sanitize( $content ) {
				$content = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $content );
				return preg_replace( '#\son[a-z]+\s*=\s*"[^"]*"#i', '', (string) $content );
			}
		}
	}
}

namespace Elementor {
	if ( ! class_exists( Utils::class, false ) ) {
		/** Imagen de ejemplo del constructor (el núcleo no se carga en la prueba). */
		final class Utils {
			public static function get_placeholder_image_src() {
				return 'https://s1.test/wp-content/plugins/elementor/assets/images/placeholder.png';
			}
		}
	}
}

namespace {
	/* ---------------------------------------------------------------- Dobles de medios */

	function wp_tempnam( $name = '' ) {
		$dir = sys_get_temp_dir() . '/ep-demo-tmp';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}
		return tempnam( $dir, 'ep' );
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
	/** Inserción: mueve el temporal a `uploads` y crea el adjunto; anota lo que se insertó. */
	function media_handle_sideload( $file_array, $post_id = 0, $desc = null ) {
		$tmp   = (string) $file_array['tmp_name'];
		$name  = basename( (string) $file_array['name'] );
		$up    = wp_upload_dir();
		$mimes = apply_filters( 'upload_mimes', array( 'png' => 'image/png' ) );
		$ext   = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$GLOBALS['ep_sideloads'][] = array( 'name' => $name, 'content' => (string) file_get_contents( $tmp ), 'svg_allowed' => isset( $mimes['svg'] ) );
		if ( 'svg' === $ext && ! isset( $mimes['svg'] ) ) {
			return new WP_Error( 'type', 'no permitido' );
		}
		if ( ! is_dir( $up['path'] ) ) {
			mkdir( $up['path'], 0777, true );
		}
		$dest = $up['path'] . '/' . $name;
		for ( $i = 1; file_exists( $dest ); $i++ ) {
			$dest = $up['path'] . '/' . pathinfo( $name, PATHINFO_FILENAME ) . '-' . $i . '.' . $ext;
		}
		rename( $tmp, $dest );
		return f8_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => $name, 'post_mime_type' => 'svg' === $ext ? 'image/svg+xml' : 'image/' . $ext ), array( '_wp_attached_file' => basename( $dest ) ) );
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
	f8_reset();
	f8_load_migration( array( 'translator', 'context', 'report', 'native' ) );
	require_once dirname( __DIR__ ) . '/digitalisimo-migration/legacy/prime-slider/class-converter.php';

	use Digitalisimo\Elements\Legacy\Context;
	use Digitalisimo\Elements\Legacy\Demo_Media;
	use Digitalisimo\Elements\Legacy\Native;
	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Translator;

	$check = 'f8_check';

	/* ---------------------------------------------------------------- Element Pack Lite simulado */

	function ep_rm( $dir ) {
		if ( is_link( $dir ) || is_file( $dir ) ) {
			unlink( $dir );
			return;
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) as $item ) {
			if ( '.' !== $item && '..' !== $item ) {
				ep_rm( $dir . '/' . $item );
			}
		}
		rmdir( $dir );
	}
	ep_rm( WP_CONTENT_DIR );
	ep_rm( sys_get_temp_dir() . '/ep-demo-tmp' );
	$lite   = WP_PLUGIN_DIR . '/bdthemes-element-pack-lite';
	$images = $lite . '/assets/images';
	mkdir( $images . '/gallery', 0777, true );
	mkdir( $lite . '/assets/js', 0777, true );
	file_put_contents( $lite . '/bdthemes-element-pack-lite.php', "<?php\n" );
	$can = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 233 430" onload="alert(1)"><script>alert(2)</script><rect width="233" height="430" fill="#eee"/></svg>';
	file_put_contents( $images . '/coco-can.svg', $can );
	/** PNG mínimo de 2 × 2 de un color, sin GD. */
	function ep_png( $file, $r, $g, $b ) {
		$chunk = static function ( $type, $data ) {
			return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) );
		};
		$row = "\0" . str_repeat( chr( $r ) . chr( $g ) . chr( $b ), 2 );
		file_put_contents( $file, "\x89PNG\r\n\x1a\n" . $chunk( 'IHDR', pack( 'NNCCCCC', 2, 2, 8, 2, 0, 0, 0 ) ) . $chunk( 'IDAT', gzcompress( $row . $row ) ) . $chunk( 'IEND', '' ) );
	}
	ep_png( $images . '/gallery/item-1.png', 10, 20, 30 );
	ep_png( $lite . '/assets/js/fuera.png', 1, 2, 3 );
	f8_plugin( 'bdthemes-element-pack-lite/bdthemes-element-pack-lite.php', array( 'Name' => 'Element Pack Lite', 'Version' => '10.0.1' ) );
	$assets = plugins_url( 'assets/', $lite . '/bdthemes-element-pack-lite.php' );
	$up     = wp_upload_dir();
	$post   = f8_post( array( 'post_type' => 'page', 'post_title' => 'Tarjetas' ) );
	$GLOBALS['ep_sideloads'] = array();

	$card = static function ( array $settings = array(), string $id = 'c1' ) {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'bdt-animated-card', 'settings' => $settings, 'elements' => array() );
	};
	/** Convierte en un modo y devuelve [ajustes convertidos, códigos del informe]. */
	$convert = static function ( array $element, string $mode = 'run' ) use ( $post ) {
		Demo_Media::reset();
		Report::begin( 'run' === $mode ? 'run' : 'analysis' );
		Context::begin( $post, $mode );
		list( $out ) = Native::element( $element, $post );
		Context::end();
		$report = Report::take();
		$codes  = array();
		foreach ( (array) ( $report['docs'][ $post ]['items'] ?? array() ) as $item ) {
			$codes[ (string) $item['code'] ] = (array) ( $item['detail'] ?? array() );
		}
		return array( (array) $out['settings'], $codes );
	};

	/* ---------------------------------------------------------------- Valores por defecto con `{{ep_assets}}` */

	$check( Demo_Media::class === get_parent_class( '\\Digitalisimo\\Elements\\Legacy\\Prime_Slider\\Demo_Media' ), 'Prime_Slider\\Demo_Media es la clase común, con su nombre de siempre' );
	$check( array( 'bdthemes-element-pack-lite' ) === array_column( Demo_Media::installed( 'ep' ), 'slug' ) && $assets === Demo_Media::ep_assets_url(), 'Element Pack instalado (inactivo): su carpeta assets/' );
	$t = Translator::translate( 'bdt-animated-card', array() );
	$check( $assets . 'images/coco-can.svg' === $t['image']['url'] && 'digitalisimo-fancy-card' === Translator::target( 'bdt-animated-card' ), 'Animated Card sin imagen guardada: la lata de Element Pack (no la imagen de ejemplo del constructor)' );
	$check( array() === $GLOBALS['ep_sideloads'], 'traducir (adaptador) no copia nada' );
	$saved = Translator::translate( 'bdt-animated-card', array( 'image' => array( 'id' => 7, 'url' => 'https://s1.test/wp-content/uploads/mia.png' ) ) );
	$check( 'https://s1.test/wp-content/uploads/mia.png' === $saved['image']['url'] && 7 === $saved['image']['id'], 'una imagen guardada manda' );
	$backup = $GLOBALS['wp']['plugins'];
	$GLOBALS['wp']['plugins'] = array();
	$none = Translator::translate( 'bdt-animated-card', array() );
	$placeholder = \Elementor\Utils::get_placeholder_image_src();
	$check( '' === Demo_Media::ep_assets_url() && $placeholder === $none['image']['url'], 'sin Element Pack instalado: la imagen de ejemplo del constructor, como antes (nunca vacía ni «images/coco-can.svg» suelto)' );
	$GLOBALS['wp']['plugins'] = $backup;
	$check( 'x' === Demo_Media::expand( 'x' ) && array( 'a' => array( 'url' => $assets . 'i.png' ) ) === Demo_Media::expand( array( 'a' => array( 'url' => '{{ep_assets}}i.png' ) ) ), 'expand(): sólo el marcador, también anidado' );

	/* ---------------------------------------------------------------- Conversión */

	// Análisis (puro): nada se copia y la imagen queda vacía, informada.
	list( $s, $codes ) = $convert( $card(), 'analysis' );
	$check( $placeholder === $s['image']['url'] && array() === $GLOBALS['ep_sideloads'] && isset( $codes['core.demo.placeholder'] ) && ! isset( $codes['core.demo.skipped'] ), 'análisis: sin copias; la imagen de ejemplo del constructor, informada' );
	// Subida sin filtrar apagada (lo habitual): un SVG no se copia (K-D15). La tarjeta no se queda sin imagen, que es lo
	// que hacía antes la conversión de esta rama: vuelve a la imagen de ejemplo del constructor, la de Migración 1.0.25.
	list( $s, $codes ) = $convert( $card() );
	$check( $placeholder === $s['image']['url'] && '' === $s['image']['id'] && array() === $GLOBALS['ep_sideloads'] && 1 === (int) ( $codes['core.demo.placeholder']['count'] ?? 0 ) && 'bdt-animated-card' === ( $codes['core.demo.placeholder']['widget'] ?? '' ) && ! isset( $codes['core.demo.skipped'] ), 'SVG sin la subida sin filtrar → la imagen de ejemplo del constructor (nunca vacía), sin copiar, core.demo.placeholder' );
	list( $s ) = $convert( $card( array(), 'c7' ), 'editor' );
	$check( $placeholder === $s['image']['url'] && array() === $GLOBALS['ep_sideloads'], 'editor (puro) sin copia: la imagen de ejemplo del constructor' );
	// Una URL guardada que no se copia no es un valor por defecto: se vacía (F8-D17), como las de Prime Slider.
	list( $s, $codes ) = $convert( $card( array( 'image' => array( 'url' => $assets . 'images/coco-can.svg?v=1', 'id' => '' ) ) ) );
	$check( '' === $s['image']['url'] && 1 === (int) ( $codes['core.demo.skipped']['count'] ?? 0 ) && ! isset( $codes['core.demo.placeholder'] ), 'URL guardada sin copia: vacía e informada (core.demo.skipped)' );
	// Encendida: el análisis (antes del clic) no copia, pero informa la copia prevista, nunca una imagen perdida.
	update_option( 'elementor_unfiltered_files_upload', '1' );
	list( $s, $codes ) = $convert( $card(), 'analysis' );
	$check( '' === $s['image']['url'] && array() === $GLOBALS['ep_sideloads'] && 1 === (int) ( $codes['core.demo.copied']['count'] ?? 0 ) && ! isset( $codes['core.demo.skipped'] ), 'análisis con la subida encendida: sin copias; core.demo.copied previsto, sin core.demo.skipped' );
	// Copia saneada en Medios.
	list( $s, $codes ) = $convert( $card() );
	$id = (int) ( $s['image']['id'] ?? 0 );
	$check( $id > 0 && 0 === strpos( $s['image']['url'], $up['baseurl'] . '/' ) && 'coco-can.svg' === basename( $s['image']['url'] ) && false === strpos( json_encode( $s ), '/plugins/' ), 'la lata pasa a Medios ({id, url} en uploads), sin rastro de la carpeta del plugin' );
	$check( 1 === count( $GLOBALS['ep_sideloads'] ) && true === $GLOBALS['ep_sideloads'][0]['svg_allowed'] && false === stripos( $GLOBALS['ep_sideloads'][0]['content'], '<script' ) && false === stripos( $GLOBALS['ep_sideloads'][0]['content'], 'onload' ), 'SVG saneado antes de escribirse; el tipo SVG sólo durante la inserción' );
	$check( 1 === (int) ( $codes['core.demo.copied']['count'] ?? 0 ) && ! isset( $codes['core.demo.skipped'] ), 'core.demo.copied con el recuento' );
	$check( hash_file( 'sha256', $images . '/coco-can.svg' ) === f8_meta( $id, Demo_Media::META ), 'meta _digitalisimo_migration_demo = sha256 del original' );
	$registry = '\\Digitalisimo\\Elements\\Legacy\\Platform\\Registry';
	if ( class_exists( $registry ) ) {
		$row = $registry::get( 'demo', hash_file( 'sha256', $images . '/coco-can.svg' ) );
		$check( 'done' === ( $row['state'] ?? '' ) && $id === (int) $row['dest'] && 'ep' === ( $row['extra']['family'] ?? '' ), 'registro demo: done, con el adjunto y la familia ep' );
	}
	// Otra tarjeta (y otra petición): reutiliza la copia.
	list( $s, $codes ) = $convert( $card( array(), 'c2' ) );
	$check( $id === (int) $s['image']['id'] && 1 === count( $GLOBALS['ep_sideloads'] ) && ! isset( $codes['core.demo.copied'] ), 'deduplicada: otra tarjeta reutiliza la copia' );
	list( $s ) = $convert( $card(), 'editor' );
	$check( $id === (int) $s['image']['id'] && 1 === count( $GLOBALS['ep_sideloads'] ), 'editor (puro): reutiliza lo ya copiado' );
	// Otro sitio de la red en la misma petición (legacy-ab, una tarea de WP-CLI): su propia copia, nunca el adjunto del
	// primero, que en este sitio no existe (antes la caché por petición lo devolvía y la imagen se vaciaba).
	switch_to_blog( 2 );
	update_option( 'elementor_unfiltered_files_upload', '1' );
	$post2 = f8_post( array( 'post_type' => 'page', 'post_title' => 'Tarjetas del sitio 2' ) );
	Report::begin( 'run' );
	Context::begin( $post2, 'run' );
	list( $out2 ) = Native::element( $card( array(), 'c5' ), $post2 );
	Context::end();
	Report::take();
	$id2 = (int) ( $out2['settings']['image']['id'] ?? 0 );
	$check( $id2 > 0 && 'coco-can.svg' === basename( (string) $out2['settings']['image']['url'] ) && 2 === count( $GLOBALS['ep_sideloads'] ) && 'attachment' === ( get_post( $id2 )->post_type ?? '' ), 'otro sitio en la misma petición: su propia copia en su biblioteca' );
	restore_current_blog();
	list( $s ) = $convert( $card( array(), 'c6' ), 'editor' );
	$check( $id === (int) $s['image']['id'] && 2 === count( $GLOBALS['ep_sideloads'] ), 'de vuelta en el primer sitio: su copia de siempre' );
	array_pop( $GLOBALS['ep_sideloads'] );
	// Un convertidor que envuelve el widget (Prime Slider) lee sus propios recuentos después: la conversión no los toca.
	Demo_Media::$copied  = 3;
	Demo_Media::$skipped = 2;
	Report::begin( 'run' );
	Context::begin( $post, 'run' );
	Native::element( $card( array(), 'c4' ), $post );
	Context::end();
	Report::take();
	$check( 3 === Demo_Media::$copied && 2 === Demo_Media::$skipped && 0 === Demo_Media::$planned, 'los recuentos de Demo_Media vuelven a los de antes de convertir el widget' );
	Demo_Media::reset();
	// URL guardada de la carpeta del plugin (también en filas y textos), una de fuera de assets/images y la del constructor.
	$placeholder = 'https://s1.test/wp-content/plugins/elementor/assets/images/placeholder.png';
	list( $s, $codes ) = $convert( $card( array( 'image' => array( 'url' => $assets . 'images/gallery/item-1.png', 'id' => '' ), 'title_text' => 'Servicio' ) ) );
	$check( (int) $s['image']['id'] > 0 && $id !== (int) $s['image']['id'] && 'item-1.png' === basename( $s['image']['url'] ) && 2 === count( $GLOBALS['ep_sideloads'] ), 'una URL guardada de la carpeta de Element Pack también se copia' );
	$r = Demo_Media::resolve( 'bdt-x', array( 'rows' => array( array( 'image' => array( 'url' => $assets . 'images/gallery/item-1.png' ) ) ), 'js' => array( 'url' => $assets . 'js/fuera.png' ), 'ph' => array( 'url' => $placeholder ) ) );
	$check( (int) $r['rows'][0]['image']['id'] > 0 && '' === $r['js']['url'] && $placeholder === $r['ph']['url'], 'filas: copiadas; fuera de assets/images: vacía; la imagen del constructor no se toca' );
	// Un texto que nombra la carpeta en medio (HTML del editor, CSS) no es una URL de demostración: nunca se vacía.
	$html = '<p>Lata: <img src="' . $assets . 'images/coco-can.svg" alt=""></p>';
	$css  = 'selector{background:url(' . $assets . 'images/gallery/item-1.png)}';
	$link = array( 'url' => $assets . 'images/gallery/item-1.png extra' );
	$r    = Demo_Media::resolve( 'bdt-x', array( 'description' => $html, 'custom_css' => $css, 'link' => $link ) );
	$check( $html === $r['description'] && $css === $r['custom_css'] && $link === $r['link'] && ! Demo_Media::is_plugin_url( $html ) && Demo_Media::is_plugin_url( '//s1.test' . parse_url( $assets, PHP_URL_PATH ) . 'images/x.png' ), 'un HTML, un CSS o un texto con la carpeta en medio quedan intactos; una URL sin esquema sí se reconoce' );
	// Restringido (documento migrado con 1.0.20): conserva su conversión, la de 1.0.20, cuyo mapa ponía la imagen de ejemplo
	// del constructor (`$placeholder_url`); nunca la URL de la carpeta del plugin, que da 404 en cuanto se borra.
	$sideloads          = count( $GLOBALS['ep_sideloads'] );
	Native::$restricted = true;
	list( $s, $codes ) = $convert( $card( array(), 'c3' ) );
	$t                  = Translator::translate( 'bdt-animated-card', array() );
	Native::$restricted = false;
	$legacy_placeholder = class_exists( '\Elementor\Utils' ) ? (string) \Elementor\Utils::get_placeholder_image_src() : '';
	$check( $legacy_placeholder === $s['image']['url'] && $legacy_placeholder === $t['image']['url'] && false === strpos( json_encode( $s ), 'bdthemes-element-pack' ) && $sideloads === count( $GLOBALS['ep_sideloads'] ) && ! isset( $codes['core.demo.copied'] ) && ! isset( $codes['core.demo.skipped'] ), 'modo restringido: la conversión de 1.0.20 (imagen de ejemplo del constructor), sin copias ni la carpeta del plugin' );
	// Plugin borrado: la carpeta conocida sigue reconociéndose y se vacía.
	$GLOBALS['wp']['plugins'] = array();
	$check( Demo_Media::is_plugin_url( 'https://s1.test/wp-content/plugins/bdthemes-element-pack/assets/images/x.png' ) && Demo_Media::is_plugin_url( $assets . 'images/x.png' ) && ! Demo_Media::is_plugin_url( 'https://s1.test/wp-content/plugins/element-pack-otro/assets/images/x.png' ), 'carpetas de Element Pack (Lite y Pro) reconocidas aunque no estén instaladas' );
	list( $s, $codes ) = $convert( $card( array( 'image' => array( 'url' => $assets . 'images/gallery/otra.png' ) ) ) );
	$check( '' === $s['image']['url'] && isset( $codes['core.demo.skipped'] ), 'plugin borrado → vacía e informada' );
	$GLOBALS['wp']['plugins'] = $backup;
	update_option( 'elementor_unfiltered_files_upload', '' );

	// Fuera del escritorio (WP-Cron, la petición del arnés) no hay archivos de wp-admin cargados: cada uno se carga por su
	// propia función, también image.php, que media_handle_sideload() necesita (wp_read_image_metadata()).
	$source = (string) file_get_contents( dirname( __DIR__ ) . '/digitalisimo-migration/legacy/class-demo-media.php' );
	$check( false !== strpos( $source, "array( 'file' => 'wp_tempnam', 'media' => 'media_handle_sideload', 'image' => 'wp_read_image_metadata' )" ) && false === strpos( $source, "if ( ! function_exists( 'media_handle_sideload' ) && defined( 'ABSPATH' )" ), 'file.php, media.php e image.php se cargan cada uno por su función (antes, image.php nunca tras media.php)' );

	/* ---------------------------------------------------------------- Informe y reversión */

	$catalog = Report::catalog();
	$check( isset( $catalog['core.demo.copied'], $catalog['core.demo.skipped'], $catalog['core.demo.placeholder'], $catalog['core.demo.reverted'], $catalog['core.demo.revert_kept'] ) && ! empty( $catalog['core.demo.skipped']['review_if_published'] ) && ! empty( $catalog['core.demo.placeholder']['review_if_published'] ), 'códigos core.demo.* en el catálogo; vaciada o con la imagen de ejemplo, revisable si está publicada' );
	if ( class_exists( $registry ) ) {
		$codes = Demo_Media::revert();
		$check( array() === (array) $registry::all( 'demo' ) && count( $codes ) >= 2 && array( 'core.demo.revert_kept' ) === array_values( array_unique( array_column( $codes, 0 ) ) ), 'revert(): sin papelera de medios las copias de Element Pack se quedan (core.demo.revert_kept) y el registro se olvida' );
	}

	// Element Pack cargado: manda su BDTEP_ASSETS_URL.
	define( 'BDTEP_ASSETS_URL', 'https://s1.test/wp-content/plugins/bdthemes-element-pack/assets/' );
	$check( BDTEP_ASSETS_URL === Demo_Media::ep_assets_url() && BDTEP_ASSETS_URL . 'images/coco-can.svg' === Translator::translate( 'bdt-animated-card', array() )['image']['url'], 'con Element Pack cargado, su BDTEP_ASSETS_URL' );

	ep_rm( sys_get_temp_dir() . '/ep-demo-tmp' );
	f8_done( 'DIGITALÍSIMO Migración: imágenes de ejemplo de Element Pack en Medios', 32 );
}
