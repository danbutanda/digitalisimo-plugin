<?php
defined( 'ABSPATH' ) || exit;

/**
 * Imágenes responsive para cualquier origen: WordPress, Elementor, Elementor
 * Pro, tema y plugins.
 *
 * No reduce, convierte ni reemplaza archivos: sólo completa el HTML de cada
 * <img> con lo que la Biblioteca de Medios ya tiene registrado (srcset, sizes,
 * width, height, loading, decoding y, como mucho una vez por página,
 * fetchpriority). El navegador elige el archivo según viewport, DPR y layout.
 * Una imagen que no se puede vincular con seguridad a un adjunto del sitio se
 * deja exactamente como estaba.
 *
 * Funciona sobre el HTML final de la página, porque es el único punto donde
 * convergen todos los orígenes. Elementor aporta además su layout (columnas,
 * contenedores, ancho en caja) para un sizes más preciso que «100vw».
 */
class Digitalisimo_Integrations_Performance_Images {
	const GENERATION = 'digitalisimo_performance_image_generation';
	const URL_MAP    = 'digitalisimo_performance_image_map';
	const MARKER     = 'data-digitalisimo-sizes';
	/** Las primeras imágenes vinculadas de la página no se difieren: suelen estar en el primer viewport. */
	const ABOVE_FOLD = 3;
	/** Ancho mínimo para considerar una imagen candidata a LCP. */
	const LCP_MIN_WIDTH = 300;

	private static $active   = false;
	private static $stack    = array();
	private static $report   = array();
	private static $map      = null;
	private static $map_dirty = false;
	private static $resolved = array();

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'start' ), 1 );
		add_action( 'elementor/frontend/before_render', array( __CLASS__, 'enter' ) );
		add_action( 'elementor/frontend/after_render', array( __CLASS__, 'leave' ) );
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'annotate_widget' ), 20, 2 );
		add_action( 'elementor/element/parse_css', array( __CLASS__, 'background_css' ), 20, 2 );
		add_filter( 'digitalisimo_performance_probe_html', array( __CLASS__, 'probe_html' ) );
		add_filter( 'digitalisimo_performance_probe_image_report', array( __CLASS__, 'probe_report' ) );
		add_action( 'shutdown', array( __CLASS__, 'save_map' ), 20 );
		foreach ( array( 'delete_attachment', 'edit_attachment', 'wp_update_attachment_metadata' ) as $hook ) add_action( $hook, array( __CLASS__, 'invalidate' ) );
		add_action( 'update_option_' . Digitalisimo_Integrations_Settings::OPTION, array( __CLASS__, 'settings_changed' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ *
	 * Ajustes
	 * ------------------------------------------------------------------ */

	public static function sanitize_mode( $value ) {
		return in_array( $value, array( 'safe', 'strict' ), true ) ? $value : 'safe';
	}

	/** Una regla por línea: .clase, #id, [atributo], id:123 o un fragmento de URL. */
	public static function sanitize_exclusions( $value ) {
		$rules = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $value ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || strlen( $line ) > 200 ) continue;
			if ( preg_match( '/^\.[A-Za-z0-9_-]+$/', $line ) || preg_match( '/^#[A-Za-z0-9_-]+$/', $line ) || preg_match( '/^\[[A-Za-z0-9_:-]+\]$/', $line ) || preg_match( '/^id:\d+$/i', $line ) ) $rules[ strtolower( $line ) ] = $line;
			elseif ( preg_match( '~^[A-Za-z0-9._/:%?=&+-]+$~', $line ) ) $rules[ $line ] = $line;
			if ( count( $rules ) >= 100 ) break;
		}
		return implode( "\n", array_values( $rules ) );
	}

	/** Entero positivo escrito como texto. No depende de la extensión ctype, ausente en algunos hosts. */
	private static function digits( $value ) {
		return (bool) preg_match( '/^\d+$/', trim( (string) $value ) );
	}

	private static function option( $key ) {
		return Digitalisimo_Integrations_SEO_Resolver::option( $key );
	}

	private static function enabled( $key ) {
		return (bool) self::option( 'perf_img_enabled' ) && (bool) self::option( $key );
	}

	/* ------------------------------------------------------------------ *
	 * Buffer de la página
	 * ------------------------------------------------------------------ */

	/** Sólo páginas HTML públicas; nunca administración, editor, vistas previas, REST, feeds ni AJAX. */
	public static function start() {
		if ( ! self::option( 'perf_img_enabled' ) || ! Digitalisimo_Integrations_Performance_Manager::frontend_safe() ) return;
		if ( is_feed() || is_embed() || is_robots() || is_trackback() || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) return;
		self::$active = true;
		ob_start( array( __CLASS__, 'buffer' ) );
	}

	/** Un fallo nunca rompe la página: se devuelve el HTML original. */
	public static function buffer( $html, $phase = 0 ) {
		if ( $phase & PHP_OUTPUT_HANDLER_CLEAN ) return $html;
		try {
			return self::is_html_response( $html ) ? self::process( $html ) : $html;
		} catch ( Throwable $error ) {
			return $html;
		}
	}

	private static function is_html_response( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<img' ) ) return false;
		if ( ! preg_match( '/<html[\s>]/i', $html ) || preg_match( '/<html[^>]*\s(?:amp|⚡)[\s>=]/i', $html ) ) return false;
		if ( function_exists( 'headers_list' ) ) {
			foreach ( headers_list() as $header ) {
				if ( 0 === stripos( $header, 'content-type:' ) && false === stripos( $header, 'text/html' ) ) return false;
			}
		}
		return true;
	}

	public static function probe_html( $html ) {
		return self::$active && self::is_html_response( $html ) ? self::process( $html ) : $html;
	}

	public static function probe_report( $report ) {
		return self::$active ? self::$report : $report;
	}

	/* ------------------------------------------------------------------ *
	 * Procesado del HTML
	 * ------------------------------------------------------------------ */

	/**
	 * Recorre los <img> en orden de documento. Los bloques donde un <img> no es
	 * una imagen visible (script, style, noscript, template, textarea, svg) o
	 * donde otro mecanismo elige el archivo (picture) se apartan antes y se
	 * restauran intactos después.
	 */
	public static function process( $html ) {
		self::$report = array();
		$masked = array();
		$html   = preg_replace_callback( '~<(script|style|noscript|template|textarea|svg|picture)\b.*?</\1\s*>~is', function( $match ) use ( &$masked ) {
			$token = '<!--digitalisimo-mask-' . count( $masked ) . '-->';
			$masked[ $token ] = $match[0];
			return $token;
		}, (string) $html );
		if ( ! is_string( $html ) ) return implode( '', $masked );

		$state = array(
			'index'       => 0,
			'high_used'   => (bool) preg_match( '/<img\b[^>]*\bfetchpriority\s*=\s*["\']?high/i', $html ),
			'lcp_done'    => false,
			'exclusions'  => self::exclusion_rules(),
			'mode'        => self::sanitize_mode( self::option( 'perf_img_mode' ) ),
		);
		$processed = preg_replace_callback( '/<img\b[^>]*>/i', function( $match ) use ( &$state ) {
			return self::process_tag( $match[0], $state );
		}, $html );
		if ( ! is_string( $processed ) ) $processed = $html;
		return $masked ? strtr( $processed, $masked ) : $processed;
	}

	/** Atributos de una etiqueta <img>: valores entre comillas, sin comillas o booleanos. */
	public static function attributes( $tag ) {
		$attributes = array();
		if ( preg_match_all( '/\s([^\s=\/>"\']+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+)))?/', (string) $tag, $found, PREG_SET_ORDER ) ) {
			foreach ( $found as $item ) {
				$name = strtolower( $item[1] );
				if ( isset( $attributes[ $name ] ) ) continue;
				$value = $item[2] ?? '';
				if ( '' === $value && isset( $item[3] ) && '' !== $item[3] ) $value = $item[3];
				if ( '' === $value && isset( $item[4] ) ) $value = $item[4];
				$attributes[ $name ] = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
			}
		}
		return $attributes;
	}

	/** Añade un atributo al final de la etiqueta, respetando el cierre «/>». */
	private static function add_attribute( $tag, $name, $value ) {
		$attribute = ' ' . $name . '="' . esc_attr( $value ) . '"';
		return preg_replace( '~\s*(/?)>$~', $attribute . '$1>', $tag, 1 );
	}

	private static function remove_attribute( $tag, $name ) {
		return preg_replace( '/\s' . preg_quote( $name, '/' ) . '(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>"\']+))?(?=[\s\/>])/i', '', $tag, 1 );
	}

	private static function replace_attribute( $tag, $name, $value ) {
		return self::add_attribute( self::remove_attribute( $tag, $name ), $name, $value );
	}

	/**
	 * @param string $tag   Etiqueta original.
	 * @param array  $state Estado de la página: orden, LCP y fetchpriority usados.
	 */
	private static function process_tag( $tag, &$state ) {
		$attrs  = self::attributes( $tag );
		$src    = (string) ( $attrs['src'] ?? '' );
		$marker = $attrs[ self::MARKER ] ?? '';
		$clean  = '' !== $marker ? self::remove_attribute( $tag, self::MARKER ) : $tag;
		$entry  = array( 'src' => $src, 'attachment_id' => 0, 'states' => array(), 'reason' => '' );

		// Swiper y lazysizes cargan desde data-src y también aplican data-srcset y
		// data-sizes: se completan sin tocar su mecanismo.
		$deferred = self::deferred_source( $attrs );
		if ( '' !== $deferred ) return self::process_deferred( $clean, $attrs, $deferred, $marker, $entry, $state );

		$exclusion = self::excluded( $attrs, $state['exclusions'] );
		if ( $exclusion ) return self::finish( $clean, $attrs, $entry, 'EXCLUIDA', $exclusion );

		$id = self::resolve_attachment( $attrs );
		if ( ! $id ) return self::finish( $clean, $attrs, $entry, 'SIN ATTACHMENT', 'No se vinculó con un adjunto de la Biblioteca de Medios de este sitio: se conserva sin cambios.' );
		$entry['attachment_id'] = $id;
		if ( in_array( 'id:' . $id, array_map( 'strtolower', $state['exclusions'] ), true ) ) return self::finish( $clean, $attrs, $entry, 'EXCLUIDA', 'Exclusión manual: id:' . $id );

		$data = self::attachment_data( $id, $src );
		if ( ! $data ) return self::finish( $clean, $attrs, $entry, 'ERROR', 'El archivo no coincide con los tamaños registrados del adjunto.' );
		$entry['original'] = $data['full'];
		$entry['file']     = array( $data['width'], $data['height'] );

		$out        = $clean;
		$changed    = false;
		$responsive = false;
		$had     = ! empty( $attrs['srcset'] ) && ! empty( $attrs['sizes'] ) && ! empty( $attrs['width'] ) && ! empty( $attrs['height'] );

		// srcset: sólo variantes que ya existen, con la proporción de la imagen.
		$srcset = (string) ( $attrs['srcset'] ?? '' );
		if ( '' === $srcset && self::enabled( 'perf_img_srcset' ) ) {
			if ( $data['srcset'] ) { $out = self::add_attribute( $out, 'srcset', $data['srcset'] ); $srcset = $data['srcset']; $changed = $responsive = true; }
			else $entry['states'][] = 'SIN VARIANTES';
		}
		// sizes: se conserva el existente; si no, layout de Elementor y, en su defecto, el de WordPress.
		if ( '' !== $srcset && empty( $attrs['sizes'] ) && self::enabled( 'perf_img_sizes' ) ) {
			$sizes = '' !== $marker ? $marker : $data['sizes'];
			if ( $sizes ) { $out = self::add_attribute( $out, 'sizes', $sizes ); $attrs['sizes'] = $sizes; $changed = $responsive = true; }
		}
		// width/height reservan el espacio; nunca se tocan estilos.
		if ( self::enabled( 'perf_img_dimensions' ) ) {
			$width  = trim( (string) ( $attrs['width'] ?? '' ) );
			$height = trim( (string) ( $attrs['height'] ?? '' ) );
			if ( '' === $width && '' === $height ) {
				$out = self::add_attribute( self::add_attribute( $out, 'width', $data['width'] ), 'height', $data['height'] );
				$attrs['width'] = $data['width'];
				$changed = true;
			} elseif ( '' === $height && self::digits( $width ) && $data['width'] ) {
				$out = self::add_attribute( $out, 'height', (string) max( 1, (int) round( (int) $width * $data['height'] / $data['width'] ) ) );
				$changed = true;
			} elseif ( '' === $width && self::digits( $height ) && $data['height'] ) {
				$out = self::add_attribute( $out, 'width', (string) max( 1, (int) round( (int) $height * $data['width'] / $data['height'] ) ) );
				$attrs['width'] = (string) round( (int) $height * $data['width'] / $data['height'] );
				$changed = true;
			}
		}

		if ( self::enabled( 'perf_img_lazy' ) ) $out = self::prioritize( $out, $attrs, $data, $state, $entry );
		// Añadir sólo width/height o lazy no hace responsive a una imagen sin variantes.
		$primary = $responsive ? 'RESPONSIVE' : ( in_array( 'SIN VARIANTES', $entry['states'], true ) ? 'SIN VARIANTES' : 'YA OPTIMIZADA' );
		if ( $changed && ! $responsive ) $entry['reason'] = 'Se añadieron width/height para reservar espacio.';
		$entry['states'] = array_values( array_diff( $entry['states'], array( $primary ) ) );
		return self::finish( $out, self::attributes( $out ), $entry, $primary, '' );
	}

	/** URL diferida de una imagen de Swiper (swiper-lazy) o lazysizes (lazyload). */
	public static function deferred_source( $attrs ) {
		$source = trim( (string) ( $attrs['data-src'] ?? '' ) );
		if ( '' === $source || isset( $attrs['data-srcset'] ) ) return '';
		$class = ' ' . strtolower( preg_replace( '/\s+/', ' ', (string) ( $attrs['class'] ?? '' ) ) ) . ' ';
		return false !== strpos( $class, ' swiper-lazy ' ) || false !== strpos( $class, ' lazyload ' ) ? $source : '';
	}

	/**
	 * Imagen con carga diferida propia: sólo se añaden data-srcset y data-sizes.
	 * No se añaden width, height, loading ni fetchpriority: su librería decide
	 * cuándo cargarla y Elementor muestra su propio indicador mientras tanto.
	 */
	private static function process_deferred( $tag, $attrs, $source, $marker, $entry, &$state ) {
		$entry['src'] = $source;
		$probe        = array( 'src' => $source, 'class' => (string) ( $attrs['class'] ?? '' ), 'id' => (string) ( $attrs['id'] ?? '' ) ) + $attrs;
		unset( $probe['data-src'] );
		$manual = self::manual_exclusion( $probe, $state['exclusions'] );
		if ( $manual ) return self::finish( $tag, $attrs, $entry, 'EXCLUIDA', $manual );
		if ( preg_match( '~^(?:data|blob):~i', $source ) || preg_match( '~\.svgz?(?:[?#]|$)~i', $source ) ) return self::finish( $tag, $attrs, $entry, 'EXCLUIDA', 'Imagen embebida o SVG.' );
		$id = self::resolve_attachment( $probe );
		if ( ! $id ) return self::finish( $tag, $attrs, $entry, 'SIN ATTACHMENT', 'Carga diferida de Swiper/lazysizes sin adjunto de este sitio: se conserva sin cambios.' );
		$entry['attachment_id'] = $id;
		if ( in_array( 'id:' . $id, array_map( 'strtolower', $state['exclusions'] ), true ) ) return self::finish( $tag, $attrs, $entry, 'EXCLUIDA', 'Exclusión manual: id:' . $id );
		$data = self::attachment_data( $id, $source );
		if ( ! $data ) return self::finish( $tag, $attrs, $entry, 'ERROR', 'El archivo no coincide con los tamaños registrados del adjunto.' );
		$entry['original'] = $data['full'];
		$entry['file']     = array( $data['width'], $data['height'] );
		$entry['states'][] = 'LAZY';
		$entry['reason']   = 'Carga diferida de Swiper/lazysizes: se completan data-srcset y data-sizes.';
		if ( ! self::enabled( 'perf_img_srcset' ) ) return self::finish( $tag, $attrs, $entry, 'YA OPTIMIZADA', '' );
		if ( ! $data['srcset'] ) return self::finish( $tag, $attrs, $entry, 'SIN VARIANTES', '' );
		$out = self::add_attribute( $tag, 'data-srcset', $data['srcset'] );
		$sizes = '';
		if ( self::enabled( 'perf_img_sizes' ) && empty( $attrs['data-sizes'] ) ) {
			$sizes = '' !== $marker ? $marker : $data['sizes'];
			if ( $sizes ) $out = self::add_attribute( $out, 'data-sizes', $sizes );
		}
		$report = self::attributes( $out );
		$report['srcset']  = $report['data-srcset'] ?? '';
		$report['sizes']   = $report['data-sizes'] ?? '';
		$report['loading'] = 'lazy';
		return self::finish( $out, $report, $entry, 'RESPONSIVE', '' );
	}

	/**
	 * Carga por prioridad en orden de documento: logo y primeras imágenes sin
	 * diferir, una única candidata a LCP con fetchpriority, y el resto lazy.
	 * Un loading o fetchpriority puesto por WordPress, Elementor o el tema se
	 * respeta; sólo el modo estricto quita el lazy a la candidata a LCP.
	 */
	private static function prioritize( $out, $attrs, $data, &$state, &$entry ) {
		$haystack = strtolower( ( $attrs['class'] ?? '' ) . ' ' . ( $attrs['id'] ?? '' ) . ' ' . ( $attrs['alt'] ?? '' ) . ' ' . basename( (string) ( $attrs['src'] ?? '' ) ) );
		// «logo» en la clase o el id identifica el logo del sitio; en el nombre o el
		// alt, sólo al principio de la página: los logos de clientes no son críticos.
		$marked   = strtolower( ( $attrs['class'] ?? '' ) . ' ' . ( $attrs['id'] ?? '' ) );
		$logo     = false !== strpos( $marked, 'logo' ) || ( false !== strpos( $haystack, 'logo' ) && $state['index'] < self::ABOVE_FOLD );
		$rendered = self::digits( (string) ( $attrs['width'] ?? '' ) ) ? (int) $attrs['width'] : (int) $data['width'];
		$loading  = strtolower( (string) ( $attrs['loading'] ?? '' ) );
		// Banderas, sellos o miniaturas de la cabecera no cuentan para el cupo del
		// primer viewport: si lo hicieran, el hero que va después quedaría diferido.
		$small    = ! $logo && $rendered > 0 && $rendered < 150;
		$index    = $state['index'];
		if ( ! $logo && ! $small ) ++$state['index'];

		if ( $logo || $index < self::ABOVE_FOLD ) {
			$entry['states'][] = 'CRÍTICA';
			// Quien ya declaró su prioridad (p. ej. una ola decorativa en «low») no ocupa el puesto de LCP.
			$candidate = ! $logo && ! $state['lcp_done'] && $rendered >= self::LCP_MIN_WIDTH && empty( $attrs['fetchpriority'] );
			if ( $candidate ) {
				$state['lcp_done'] = true;
				if ( 'lazy' === $loading && 'strict' === $state['mode'] ) { $out = self::replace_attribute( $out, 'loading', 'eager' ); $loading = 'eager'; }
				if ( ! $state['high_used'] && 'lazy' !== $loading && empty( $attrs['fetchpriority'] ) ) {
					$out = self::add_attribute( $out, 'fetchpriority', 'high' );
					$state['high_used'] = true;
				}
			}
			return $out;
		}
		if ( '' === $loading ) { $out = self::add_attribute( $out, 'loading', 'lazy' ); $loading = 'lazy'; }
		if ( empty( $attrs['decoding'] ) ) $out = self::add_attribute( $out, 'decoding', 'async' );
		if ( 'lazy' === $loading ) $entry['states'][] = 'LAZY';
		return $out;
	}

	private static function finish( $tag, $attrs, $entry, $primary, $reason ) {
		if ( count( self::$report ) < 300 ) {
			self::$report[] = array_merge( $entry, array(
				'status'        => $primary,
				'states'        => array_values( array_unique( array_merge( array( $primary ), $entry['states'] ) ) ),
				'reason'        => $reason ?: $entry['reason'],
				'srcset'        => (string) ( $attrs['srcset'] ?? '' ),
				'sizes'         => (string) ( $attrs['sizes'] ?? '' ),
				'width'         => (string) ( $attrs['width'] ?? '' ),
				'height'        => (string) ( $attrs['height'] ?? '' ),
				'loading'       => (string) ( $attrs['loading'] ?? '' ),
				'fetchpriority' => (string) ( $attrs['fetchpriority'] ?? '' ),
				'decoding'      => (string) ( $attrs['decoding'] ?? '' ),
			) );
		}
		return $tag;
	}

	/* ------------------------------------------------------------------ *
	 * Exclusiones
	 * ------------------------------------------------------------------ */

	private static function exclusion_rules() {
		$rules = array();
		foreach ( explode( "\n", self::sanitize_exclusions( self::option( 'perf_img_exclusions' ) ) ) as $line ) if ( '' !== $line ) $rules[] = $line;
		return $rules;
	}

	/** @return string Motivo de exclusión, o cadena vacía si la imagen se puede tratar. */
	public static function excluded( $attrs, $rules ) {
		$src   = trim( (string) ( $attrs['src'] ?? '' ) );
		$class = ' ' . strtolower( preg_replace( '/\s+/', ' ', (string) ( $attrs['class'] ?? '' ) ) ) . ' ';
		foreach ( array( 'data-src', 'data-srcset', 'data-lazy-src', 'data-lazy-srcset', 'data-original' ) as $name ) if ( isset( $attrs[ $name ] ) ) return 'Usa otro sistema de carga diferida (' . $name . ').';
		if ( '' === $src ) return 'Sin src: la gestiona otro mecanismo de carga.';
		if ( preg_match( '~^(?:data|blob):~i', $src ) ) return 'Imagen embebida (data:/blob:).';
		if ( preg_match( '~\.svgz?(?:[?#]|$)~i', $src ) ) return 'SVG: no tiene variantes de tamaño.';
		foreach ( array( 'data-no-optimize', 'data-skip-lazy', 'data-no-lazy', 'data-digitalisimo-skip' ) as $name ) if ( isset( $attrs[ $name ] ) ) return 'Marcada como excluida (' . $name . ').';
		foreach ( array( 'swiper-lazy', 'lazyload', 'lazyloaded', 'skip-lazy', 'no-lazyload', 'emoji', 'wp-smiley', 'avatar' ) as $name ) if ( false !== strpos( $class, ' ' . $name . ' ' ) ) return 'Clase reservada (' . $name . ').';
		if ( preg_match( '/captcha|recaptcha|hcaptcha/i', $src . $class ) ) return 'CAPTCHA.';
		if ( preg_match( '/(?:^|[\s_-])icons?(?:$|[\s_-])/i', trim( $class ) ) ) return 'Icono.';
		$width  = (string) ( $attrs['width'] ?? '' );
		$height = (string) ( $attrs['height'] ?? '' );
		if ( ( self::digits( $width ) && (int) $width <= 2 ) || ( self::digits( $height ) && (int) $height <= 2 ) ) return 'Píxel de seguimiento.';
		return self::manual_exclusion( $attrs, $rules );
	}

	/** Lista manual: .clase, #id, [atributo] o fragmento de URL. id:N se comprueba al resolver. */
	private static function manual_exclusion( $attrs, $rules ) {
		$src   = trim( (string) ( $attrs['src'] ?? '' ) );
		$class = ' ' . strtolower( preg_replace( '/\s+/', ' ', (string) ( $attrs['class'] ?? '' ) ) ) . ' ';
		foreach ( (array) $rules as $rule ) {
			if ( '.' === $rule[0] && false !== strpos( $class, ' ' . strtolower( substr( $rule, 1 ) ) . ' ' ) ) return 'Exclusión manual: ' . $rule;
			if ( '#' === $rule[0] && strtolower( (string) ( $attrs['id'] ?? '' ) ) === strtolower( substr( $rule, 1 ) ) ) return 'Exclusión manual: ' . $rule;
			if ( '[' === $rule[0] && isset( $attrs[ strtolower( trim( $rule, '[]' ) ) ] ) ) return 'Exclusión manual: ' . $rule;
			if ( 0 === stripos( $rule, 'id:' ) ) continue; // Se comprueba al resolver el adjunto.
			if ( ! in_array( $rule[0], array( '.', '#', '[' ), true ) && false !== stripos( $src, $rule ) ) return 'Exclusión manual: ' . $rule;
		}
		return '';
	}

	/* ------------------------------------------------------------------ *
	 * Adjuntos
	 * ------------------------------------------------------------------ */

	/** Sólo URLs del directorio de uploads de este sitio; las externas nunca se vinculan. */
	private static function uploads_path( $src ) {
		$uploads = wp_upload_dir( null, false );
		$base    = (string) ( $uploads['baseurl'] ?? '' );
		if ( '' === $base ) return '';
		$src = strtok( html_entity_decode( (string) $src, ENT_QUOTES, 'UTF-8' ), '?#' );
		if ( 0 === strpos( $src, '//' ) ) $src = ( is_ssl() ? 'https:' : 'http:' ) . $src;
		$path_base = (string) wp_parse_url( $base, PHP_URL_PATH );
		if ( preg_match( '~^https?://~i', $src ) ) {
			$src_host  = strtolower( (string) wp_parse_url( $src, PHP_URL_HOST ) );
			$base_host = strtolower( (string) wp_parse_url( $base, PHP_URL_HOST ) );
			if ( $src_host !== $base_host ) return '';
			$src = (string) wp_parse_url( $src, PHP_URL_PATH );
		}
		if ( '' === $path_base || 0 !== strpos( $src, trailingslashit( $path_base ) ) ) return '';
		$relative = ltrim( substr( $src, strlen( trailingslashit( $path_base ) ) ), '/' );
		return false !== strpos( $relative, '..' ) ? '' : rawurldecode( $relative );
	}

	/**
	 * Adjunto de una <img>: primero la clase wp-image-ID que ponen WordPress y
	 * Elementor; si no, la URL dentro de uploads. Un ID declarado sólo cuenta si
	 * su archivo coincide con el src, para no vincular una imagen ajena.
	 */
	public static function resolve_attachment( $attrs ) {
		$src      = (string) ( $attrs['src'] ?? '' );
		$relative = self::uploads_path( $src );
		if ( '' === $relative ) return 0;
		if ( preg_match( '/\bwp-image-(\d+)\b/', (string) ( $attrs['class'] ?? '' ), $m ) && self::belongs( (int) $m[1], $relative ) ) return (int) $m[1];
		return self::id_for_path( $relative );
	}

	/** ¿El archivo pedido es el original o uno de los tamaños registrados del adjunto? */
	private static function belongs( $id, $relative ) {
		$meta = wp_get_attachment_metadata( $id );
		if ( ! is_array( $meta ) || empty( $meta['file'] ) ) return false;
		$dir   = dirname( $meta['file'] );
		$dir   = '.' === $dir ? '' : trailingslashit( $dir );
		$files = array( $meta['file'] );
		if ( ! empty( $meta['original_image'] ) ) $files[] = $dir . $meta['original_image'];
		foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) if ( ! empty( $size['file'] ) ) $files[] = $dir . $size['file'];
		return in_array( $relative, $files, true );
	}

	/**
	 * Un tamaño intermedio («-1024x683») o la copia «-scaled» se buscan por su
	 * original. El resultado, también el negativo, se guarda por sitio: la
	 * consulta no se repite en cada visita.
	 */
	private static function id_for_path( $relative ) {
		$key = md5( $relative );
		if ( isset( self::$resolved[ $key ] ) ) return self::$resolved[ $key ];
		$cached = self::map_get( $key );
		if ( null !== $cached ) return self::$resolved[ $key ] = (int) $cached;
		$id = 0;
		$base = wp_upload_dir( null, false )['baseurl'] ?? '';
		$candidates = array_unique( array(
			$relative,
			preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $relative ),
			preg_replace( '/-scaled(\.[a-z0-9]+)$/i', '$1', preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $relative ) ),
		) );
		foreach ( $candidates as $candidate ) {
			$found = (int) attachment_url_to_postid( trailingslashit( $base ) . $candidate );
			if ( ! $found ) {
				$scaled = preg_replace( '/(\.[a-z0-9]+)$/i', '-scaled$1', $candidate );
				$found  = (int) attachment_url_to_postid( trailingslashit( $base ) . $scaled );
			}
			if ( $found && self::belongs( $found, $relative ) ) { $id = $found; break; }
		}
		self::map_set( $key, $id );
		return self::$resolved[ $key ] = $id;
	}

	/**
	 * Datos derivados del adjunto para el archivo pedido: dimensiones, srcset y
	 * sizes de WordPress. Se cachean por sitio, adjunto, archivo y generación.
	 */
	private static function attachment_data( $id, $src ) {
		$relative = self::uploads_path( $src );
		$name     = 'img2_' . $id . '_' . self::generation() . '_' . md5( $relative );
		$found    = false;
		$cached   = class_exists( 'Digitalisimo_Integrations_Performance_Cache' ) ? Digitalisimo_Integrations_Performance_Cache::get( $name, $found ) : null;
		if ( $found && is_array( $cached ) ) return $cached ?: null;

		$data = null;
		$meta = wp_get_attachment_metadata( $id );
		$mime = (string) get_post_mime_type( $id );
		if ( is_array( $meta ) && ! empty( $meta['file'] ) && ! empty( $meta['width'] ) && 0 === strpos( $mime, 'image/' ) && 'image/svg+xml' !== $mime && self::belongs( $id, $relative ) ) {
			$dir  = dirname( $meta['file'] );
			$dir  = '.' === $dir ? '' : trailingslashit( $dir );
			$size = array( (int) $meta['width'], (int) $meta['height'] );
			if ( ! empty( $meta['original_image'] ) && $relative === $dir . $meta['original_image'] ) {
				$path = get_attached_file( $id, true );
				$original = $path ? wp_getimagesize( dirname( $path ) . '/' . $meta['original_image'] ) : false;
				if ( $original ) $size = array( (int) $original[0], (int) $original[1] );
			}
			foreach ( (array) ( $meta['sizes'] ?? array() ) as $variant ) {
				if ( ! empty( $variant['file'] ) && $relative === $dir . $variant['file'] ) { $size = array( (int) $variant['width'], (int) $variant['height'] ); break; }
			}
			$url    = trailingslashit( wp_upload_dir( null, false )['baseurl'] ?? '' ) . $relative;
			$srcset = self::cap_srcset( wp_calculate_image_srcset( $size, $url, $meta, $id ), $size[0] );
			$data = array(
				'width'  => $size[0],
				'height' => $size[1],
				'full'   => array( (int) $meta['width'], (int) $meta['height'] ),
				'srcset' => $srcset,
				'sizes'  => (string) wp_calculate_image_sizes( $size, $url, $meta, $id ),
			);
		}
		if ( class_exists( 'Digitalisimo_Integrations_Performance_Cache' ) ) Digitalisimo_Integrations_Performance_Cache::set( $name, $data ?: array(), 3600 );
		return $data;
	}

	/**
	 * WordPress ofrece también tamaños mayores que el elegido. Si el diseñador
	 * eligió 768 px, un teléfono de DPR 3 descargaría 1536: más peso que hoy.
	 * Nunca se ofrece un archivo mayor que el del src; con «Full» no cambia nada.
	 */
	public static function cap_srcset( $srcset, $width ) {
		if ( ! is_string( $srcset ) || '' === $srcset ) return '';
		$kept = array();
		foreach ( array_map( 'trim', explode( ',', $srcset ) ) as $candidate ) {
			if ( ! preg_match( '/\s(\d+)w$/', $candidate, $m ) ) return ''; // Un descriptor que no es de ancho no se puede acotar.
			if ( (int) $m[1] <= (int) $width ) $kept[] = $candidate;
		}
		return count( $kept ) >= 2 ? implode( ', ', $kept ) : '';
	}

	/* ------------------------------------------------------------------ *
	 * Caché de URL → adjunto
	 * ------------------------------------------------------------------ */

	private static function generation() {
		return max( 1, (int) get_option( self::GENERATION, 1 ) );
	}

	/** Cambiar un adjunto invalida todo lo derivado del sitio, nunca el de otros sitios. */
	public static function invalidate() {
		update_option( self::GENERATION, self::generation() + 1, true );
		self::$resolved = array();
		self::$map      = null;
	}

	/**
	 * Con Redis/Memcached se usa la caché de objetos; sin ella, un mapa acotado
	 * por sitio que se lee una vez por petición y sólo se escribe si cambió.
	 */
	private static function map_get( $key ) {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			$found = false;
			$value = Digitalisimo_Integrations_Performance_Cache::get( 'imgurl_' . self::generation() . '_' . $key, $found );
			return $found ? (int) $value : null;
		}
		self::load_map();
		return array_key_exists( $key, self::$map['ids'] ) ? (int) self::$map['ids'][ $key ] : null;
	}

	private static function map_set( $key, $id ) {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			Digitalisimo_Integrations_Performance_Cache::set( 'imgurl_' . self::generation() . '_' . $key, (int) $id, 3600 );
			return;
		}
		self::load_map();
		self::$map['ids'][ $key ] = (int) $id;
		if ( count( self::$map['ids'] ) > 2000 ) self::$map['ids'] = array_slice( self::$map['ids'], -2000, null, true );
		self::$map_dirty = true;
	}

	private static function load_map() {
		if ( null !== self::$map ) return;
		$map = get_option( self::URL_MAP, array() );
		self::$map = is_array( $map ) && ( $map['generation'] ?? 0 ) === self::generation() && is_array( $map['ids'] ?? null ) ? $map : array( 'generation' => self::generation(), 'ids' => array() );
	}

	public static function save_map() {
		if ( ! self::$map_dirty || ! is_array( self::$map ) ) return;
		update_option( self::URL_MAP, self::$map, false );
		self::$map_dirty = false;
	}

	/* ------------------------------------------------------------------ *
	 * Layout de Elementor → sizes
	 * ------------------------------------------------------------------ */

	private static function size_value( $setting ) {
		if ( is_array( $setting ) ) return array( (float) ( $setting['size'] ?? 0 ), (string) ( $setting['unit'] ?? 'px' ) );
		return is_numeric( $setting ) ? array( (float) $setting, '%' ) : array( 0.0, '' );
	}

	/** Ancho en caja por defecto del Kit (1140 px en Elementor). */
	private static function kit_width() {
		static $width = null;
		if ( null !== $width ) return $width;
		$width = 1140;
		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->kits_manager ) && method_exists( \Elementor\Plugin::$instance->kits_manager, 'get_active_kit_for_frontend' ) ) {
			$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit_for_frontend();
			if ( is_object( $kit ) && method_exists( $kit, 'get_settings_for_display' ) ) {
				list( $size, $unit ) = self::size_value( $kit->get_settings_for_display( 'container_width' ) );
				if ( $size > 0 && 'px' === $unit ) $width = (int) $size;
			}
		}
		return $width;
	}

	/** Breakpoints activos de Elementor; 767/1024 si no hay Elementor. */
	public static function breakpoints() {
		static $points = null;
		if ( null !== $points ) return $points;
		$points = array( 'mobile' => 767, 'tablet' => 1024 );
		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->breakpoints ) && method_exists( \Elementor\Plugin::$instance->breakpoints, 'get_active_breakpoints' ) ) {
			foreach ( array( 'mobile', 'tablet' ) as $device ) {
				$breakpoint = \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints()[ $device ] ?? null;
				if ( is_object( $breakpoint ) && method_exists( $breakpoint, 'get_value' ) && (int) $breakpoint->get_value() > 0 ) $points[ $device ] = (int) $breakpoint->get_value();
			}
		}
		return $points;
	}

	/**
	 * Ancho relativo y tope en px de un elemento Elementor, sólo con datos
	 * explícitos. Un hijo de un contenedor en fila sin ancho propio es
	 * desconocido: se trata como 100 % para no subestimar.
	 */
	public static function layout_entry( $type, $settings, $parent_row = false ) {
		$entry = array( 'fraction' => 1.0, 'cap' => null, 'row' => false );
		$settings = (array) $settings;
		if ( 'column' === $type ) {
			$size = is_numeric( $settings['_inline_size'] ?? null ) && (float) $settings['_inline_size'] > 0 ? (float) $settings['_inline_size'] : (float) ( $settings['_column_size'] ?? 100 );
			if ( $size > 0 && $size <= 100 ) $entry['fraction'] = $size / 100;
		} elseif ( 'section' === $type ) {
			if ( 'full_width' !== ( $settings['layout'] ?? 'boxed' ) ) {
				list( $size, $unit ) = self::size_value( $settings['content_width'] ?? null );
				$entry['cap'] = $size > 0 && 'px' === $unit ? $size : self::kit_width();
			}
		} elseif ( 'container' === $type ) {
			if ( 'boxed' === ( $settings['content_width'] ?? '' ) ) {
				list( $size, $unit ) = self::size_value( $settings['boxed_width'] ?? null );
				$entry['cap'] = $size > 0 && 'px' === $unit ? $size : self::kit_width();
			}
			list( $width, $unit ) = self::size_value( $settings['width'] ?? null );
			if ( $width > 0 && '%' === $unit && $width <= 100 ) $entry['fraction'] = $width / 100;
			elseif ( $width > 0 && 'px' === $unit ) $entry['cap'] = null === $entry['cap'] ? $width : min( $entry['cap'], $width );
			$entry['row'] = in_array( (string) ( $settings['flex_direction'] ?? '' ), array( 'row', 'row-reverse' ), true );
		}
		return $entry;
	}

	/**
	 * sizes a partir de la pila de layout. Seguro: hasta tablet se declara
	 * 100vw. Estricto: sólo hasta móvil. Nunca devuelve menos que el ancho real.
	 */
	public static function sizes_from_stack( $stack, $mode, $breakpoints = null ) {
		$breakpoints = $breakpoints ?: self::breakpoints();
		$fraction = 1.0;
		$cap      = null;
		foreach ( (array) $stack as $entry ) {
			$fraction *= (float) $entry['fraction'];
			if ( null !== $cap ) $cap *= (float) $entry['fraction'];
			if ( null !== $entry['cap'] ) $cap = null === $cap ? (float) $entry['cap'] : min( $cap, (float) $entry['cap'] );
		}
		if ( $fraction >= 0.999 && null === $cap ) return '';
		$limit  = 'strict' === $mode ? $breakpoints['mobile'] : $breakpoints['tablet'];
		$vw     = min( 100, (int) ceil( $fraction * 100 ) );
		// A ancho completo dentro de una caja: 100vw hasta el ancho de la caja y luego la caja.
		if ( $vw >= 100 && null !== $cap ) return '(max-width: ' . (int) ceil( $cap ) . 'px) 100vw, ' . (int) ceil( $cap ) . 'px';
		$parts  = array( '(max-width: ' . $limit . 'px) 100vw' );
		if ( null === $cap ) $parts[] = $vw . 'vw';
		else {
			$cap   = (int) ceil( $cap );
			$until = $fraction > 0 ? (int) ceil( $cap / $fraction ) : 0;
			if ( $until > $limit ) $parts[] = '(max-width: ' . $until . 'px) ' . $vw . 'vw';
			$parts[] = $cap . 'px';
		}
		return implode( ', ', $parts );
	}

	public static function enter( $element ) {
		if ( ! self::$active || ! is_object( $element ) || ! method_exists( $element, 'get_type' ) ) return;
		$type     = $element->get_type();
		$settings = method_exists( $element, 'get_settings_for_display' ) ? (array) $element->get_settings_for_display() : array();
		$parent   = end( self::$stack );
		$entry    = self::layout_entry( $type, $settings );
		// En un contenedor en fila, un hijo sin ancho propio reparte espacio de forma desconocida.
		if ( $parent && ! empty( $parent['row'] ) && 'widget' === $type ) $entry['unknown'] = true;
		if ( $parent && ! empty( $parent['row'] ) && 'container' === $type && 1.0 === $entry['fraction'] ) $entry['unknown'] = true;
		self::$stack[] = $entry;
	}

	public static function leave( $element ) {
		if ( self::$active && self::$stack ) array_pop( self::$stack );
	}

	/** Marca los <img> del widget con el sizes de su layout; el pase final lo aplica. */
	public static function annotate_widget( $content, $widget ) {
		if ( ! self::$active || ! self::option( 'perf_img_sizes' ) || ! is_string( $content ) || false === stripos( $content, '<img' ) ) return $content;
		foreach ( self::$stack as $entry ) if ( ! empty( $entry['unknown'] ) ) return $content;
		$sizes = self::sizes_from_stack( self::$stack, self::sanitize_mode( self::option( 'perf_img_mode' ) ) );
		if ( '' === $sizes ) return $content;
		$annotated = preg_replace_callback( '/<img\b[^>]*>/i', function( $match ) use ( $sizes ) {
			$attrs = self::attributes( $match[0] );
			return isset( $attrs['sizes'] ) || isset( $attrs[ self::MARKER ] ) ? $match[0] : self::add_attribute( $match[0], self::MARKER, $sizes );
		}, $content );
		return is_string( $annotated ) ? $annotated : $content;
	}

	/* ------------------------------------------------------------------ *
	 * Fondos de Elementor (opcional)
	 * ------------------------------------------------------------------ */

	/**
	 * Variante para un ancho de viewport y DPR máximos: la más pequeña que
	 * cubre viewport × DPR. Si ninguna alcanza, ninguna: se queda el original.
	 */
	public static function background_variant( $candidates, $viewport, $dpr ) {
		$need = (int) ceil( $viewport * $dpr );
		$best = null;
		foreach ( (array) $candidates as $candidate ) {
			if ( (int) $candidate['width'] >= $need && ( ! $best || (int) $candidate['width'] < (int) $best['width'] ) ) $best = $candidate;
		}
		return $best;
	}

	/**
	 * Reglas responsive para un fondo. Con background-size «cover» el archivo
	 * necesario depende del alto del elemento, que no se conoce: no se toca.
	 * Las variantes sólo se usan con DPR ≤ 2; las pantallas de más densidad
	 * conservan el original.
	 */
	public static function background_rules( $selector, $original_width, $candidates, $size_setting, $breakpoints ) {
		if ( 'cover' === strtolower( (string) $size_setting ) ) return '';
		$css = '';
		foreach ( array( 'tablet', 'mobile' ) as $device ) {
			$viewport = (int) $breakpoints[ $device ];
			$variant  = self::background_variant( $candidates, $viewport, 2 );
			if ( ! $variant || (int) $variant['width'] >= (int) $original_width ) continue;
			$css .= '@media (max-width:' . $viewport . 'px) and (max-resolution:2dppx){' . $selector . '{background-image:url("' . esc_url_raw( $variant['url'] ) . '")}}';
		}
		return $css;
	}

	/** Añade reglas al CSS que Elementor genera para cada elemento con fondo. */
	public static function background_css( $post_css, $element ) {
		if ( ! self::enabled( 'perf_img_backgrounds' ) || ! is_object( $element ) || ! method_exists( $element, 'get_settings_for_display' ) || ! is_object( $post_css ) || ! method_exists( $post_css, 'get_stylesheet' ) ) return;
		$settings = (array) $element->get_settings_for_display();
		foreach ( array( 'background_', '_background_' ) as $prefix ) {
			$image = $settings[ $prefix . 'image' ] ?? array();
			$id    = (int) ( $image['id'] ?? 0 );
			// El diseñador ya eligió otra imagen para tablet o móvil: se respeta.
			if ( ! $id || ! empty( $settings[ $prefix . 'image_tablet' ]['url'] ) || ! empty( $settings[ $prefix . 'image_mobile' ]['url'] ) ) continue;
			$meta = wp_get_attachment_metadata( $id );
			if ( ! is_array( $meta ) || empty( $meta['width'] ) ) continue;
			$candidates = array();
			foreach ( (array) ( $meta['sizes'] ?? array() ) as $size => $variant ) {
				$url = wp_get_attachment_image_url( $id, $size );
				if ( $url && ! empty( $variant['width'] ) ) $candidates[] = array( 'width' => (int) $variant['width'], 'url' => $url );
			}
			$selector = method_exists( $post_css, 'get_element_unique_selector' ) ? $post_css->get_element_unique_selector( $element ) : '';
			$css = $selector ? self::background_rules( $selector, (int) $meta['width'], $candidates, $settings[ $prefix . 'size' ] ?? '', self::breakpoints() ) : '';
			if ( $css && method_exists( $post_css->get_stylesheet(), 'add_raw_css' ) ) $post_css->get_stylesheet()->add_raw_css( $css );
		}
	}

	/** El CSS de Elementor está cacheado en archivos: cambiar el ajuste exige regenerarlo. */
	public static function settings_changed( $old, $new ) {
		$before = ! empty( $old['perf_img_backgrounds'] ) && ! empty( $old['perf_img_enabled'] );
		$after  = ! empty( $new['perf_img_backgrounds'] ) && ! empty( $new['perf_img_enabled'] );
		if ( $before === $after || ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance->files_manager ) ) return;
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}

	/* ------------------------------------------------------------------ *
	 * Rendimiento → Imágenes: auditoría y debug
	 * ------------------------------------------------------------------ */

	/** Resumen de una captura: lo que pide el punto 17 del objetivo. */
	public static function audit( $report ) {
		$audit = array( 'total' => 0, 'srcset' => 0, 'no_srcset' => 0, 'no_dimensions' => 0, 'lazy' => 0, 'eager' => 0, 'high' => 0, 'oversized' => 0 );
		foreach ( (array) $report as $row ) {
			++$audit['total'];
			if ( '' !== (string) ( $row['srcset'] ?? '' ) ) ++$audit['srcset']; else ++$audit['no_srcset'];
			if ( '' === (string) ( $row['width'] ?? '' ) || '' === (string) ( $row['height'] ?? '' ) ) ++$audit['no_dimensions'];
			if ( 'lazy' === strtolower( (string) ( $row['loading'] ?? '' ) ) ) ++$audit['lazy']; else ++$audit['eager'];
			if ( 'high' === strtolower( (string) ( $row['fetchpriority'] ?? '' ) ) ) ++$audit['high'];
			if ( self::oversized( $row ) ) ++$audit['oversized'];
		}
		return $audit;
	}

	/**
	 * Sin srcset y con un archivo de más del doble del ancho declarado, o de más
	 * de 2048 px: el móvil descarga de más. Sólo se informa; no se cambia nada
	 * si no hay una variante segura.
	 */
	public static function oversized( $row ) {
		if ( '' !== (string) ( $row['srcset'] ?? '' ) || empty( $row['file'][0] ) ) return false;
		$file  = (int) $row['file'][0];
		$width = (string) ( $row['width'] ?? '' );
		return $file > 2048 || ( self::digits( $width ) && (int) $width > 0 && $file > 2 * (int) $width );
	}

	/** El ancho que pintará la imagen en un móvil de 390 px y en escritorio de 1440 px, según sizes. */
	public static function estimate( $sizes, $viewport ) {
		foreach ( array_map( 'trim', explode( ',', (string) $sizes ) ) as $part ) {
			if ( '' === $part || 0 === stripos( $part, 'auto' ) ) continue;
			if ( preg_match( '/^\(max-width:\s*(\d+)px\)\s*(.+)$/i', $part, $m ) ) {
				if ( $viewport > (int) $m[1] ) continue;
				$part = $m[2];
			} elseif ( '(' === $part[0] ) return null;
			if ( preg_match( '/^(\d+(?:\.\d+)?)vw$/i', $part, $m ) ) return (int) ceil( $viewport * (float) $m[1] / 100 );
			if ( preg_match( '/^(\d+(?:\.\d+)?)px$/i', $part, $m ) ) return (int) $m[1];
			return null;
		}
		return null;
	}

	public static function render( $network ) {
		$site_id  = $network ? absint( $_GET['site_id'] ?? get_current_blog_id() ) : get_current_blog_id();
		if ( $network && ! get_site( $site_id ) ) $site_id = get_current_blog_id();
		$captured = get_transient( 'digitalisimo_perf_result_' . get_current_user_id() );
		if ( ! Digitalisimo_Integrations_Asset_Diagnostics::capture_belongs_to_site( $captured, $site_id ) ) $captured = array();
		$report = (array) ( $captured['image_report'] ?? array() );
		echo '<h2>Auditoría de imágenes</h2>';
		if ( empty( $captured['url'] ) ) { echo '<p>Sin captura de este sitio. Analiza una URL en «Diagnóstico de assets» y vuelve aquí: la auditoría muestra el HTML que recibe un visitante.</p>'; return; }
		echo '<p>Página: <a href="' . esc_url( $captured['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $captured['url'] ) . '</a></p>';
		if ( ! $report ) { echo '<p>La optimización de imágenes estaba apagada para esta captura, o la página no tiene imágenes. Actívala, guarda y vuelve a analizar la URL.</p>'; return; }
		$audit  = self::audit( $report );
		$labels = array( 'total' => 'Imágenes totales', 'srcset' => 'Con srcset', 'no_srcset' => 'Sin srcset', 'no_dimensions' => 'Sin width/height', 'lazy' => 'Lazy', 'eager' => 'Eager', 'high' => 'fetchpriority alto', 'oversized' => 'Potencialmente sobredimensionadas' );
		echo '<table class="widefat striped" style="max-width:560px"><tbody>';
		foreach ( $labels as $key => $label ) echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $audit[ $key ] ) . '</td></tr>';
		echo '</tbody></table>';
		if ( $audit['high'] > 1 ) echo '<div class="notice notice-warning inline"><p>Hay más de una imagen con fetchpriority alto: compiten entre sí y con fuentes y CSS críticos.</p></div>';
		echo '<h3>Detalle por imagen</h3><p class="description">«Renderizado estimado» se calcula con el sizes publicado para un móvil de 390 px y un escritorio de 1440 px; el navegador multiplica por su DPR al elegir del srcset.</p>';
		echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Estado</th><th>Adjunto</th><th>URL</th><th>Original</th><th>Renderizado estimado</th><th>srcset</th><th>sizes</th><th>width × height</th><th>loading</th><th>fetchpriority</th></tr></thead><tbody>';
		foreach ( $report as $row ) {
			$states   = implode( ' · ', (array) ( $row['states'] ?? array( $row['status'] ?? '' ) ) );
			$original = ! empty( $row['original'] ) ? (int) $row['original'][0] . ' × ' . (int) $row['original'][1] : '—';
			$mobile   = self::estimate( $row['sizes'] ?? '', 390 );
			$desktop  = self::estimate( $row['sizes'] ?? '', 1440 );
			$estimate = '' === (string) ( $row['sizes'] ?? '' ) ? '—' : ( null === $mobile ? '?' : $mobile . ' px' ) . ' / ' . ( null === $desktop ? '?' : $desktop . ' px' );
			$srcset   = '' === (string) ( $row['srcset'] ?? '' ) ? '—' : ( count( explode( ',', $row['srcset'] ) ) . ' variantes' );
			if ( self::oversized( $row ) ) $states .= ' · SOBREDIMENSIONADA';
			echo '<tr><td>' . esc_html( $states ) . ( ! empty( $row['reason'] ) ? '<br><span class="description">' . esc_html( $row['reason'] ) . '</span>' : '' ) . '</td><td>' . esc_html( ! empty( $row['attachment_id'] ) ? '#' . (int) $row['attachment_id'] : '—' ) . '</td><td><code style="word-break:break-all">' . esc_html( $row['src'] ?? '' ) . '</code></td><td>' . esc_html( $original ) . '</td><td>' . esc_html( $estimate ) . '</td><td title="' . esc_attr( $row['srcset'] ?? '' ) . '">' . esc_html( $srcset ) . '</td><td><code>' . esc_html( ( $row['sizes'] ?? '' ) ?: '—' ) . '</code></td><td>' . esc_html( ( ( $row['width'] ?? '' ) ?: '—' ) . ' × ' . ( ( $row['height'] ?? '' ) ?: '—' ) ) . '</td><td>' . esc_html( ( $row['loading'] ?? '' ) ?: '—' ) . '</td><td>' . esc_html( ( $row['fetchpriority'] ?? '' ) ?: '—' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
