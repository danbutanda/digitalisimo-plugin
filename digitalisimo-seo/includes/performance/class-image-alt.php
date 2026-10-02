<?php
defined( 'ABSPATH' ) || exit;

/**
 * Texto alternativo en la salida frontend.
 *
 * - Un ALT manual nunca se sobrescribe; la Biblioteca de Medios nunca se modifica.
 * - Decorativa (clasificada por el administrador) → alt="".
 * - ALT idéntico a un figcaption visible → alt="" en la página, porque el
 *   caption ya lo dice; el valor guardado no cambia.
 * - Sin ALT: se construye con el contexto de esa imagen, por prioridad: título
 *   útil del adjunto, nombre limpio del archivo, título del widget, H2/H3
 *   cercano y contenido de la página.
 * - La palabra clave de apoyo nunca es el ALT de todas las imágenes: en modo
 *   Contextual se añade como mucho una vez por página y sólo si comparte
 *   vocabulario con la imagen; en Sólo fallback, únicamente cuando no hay
 *   ningún otro contexto.
 *
 * Las decisiones son puras: `apply()` recibe el contexto ya reunido por el
 * buffer de imágenes y el estado de la página, y devuelve el ALT y su estado.
 */
class Digitalisimo_Integrations_Image_Alt {
	const MODES = array( 'off', 'fallback', 'contextual' );

	const MANUAL    = 'MANUAL';
	const GENERATED = 'GENERADO';
	const REPEATED  = 'REPETIDO';
	const CAPTION   = 'IGUAL A CAPTION';
	const DECORATIVE = 'DECORATIVO';
	const EMPTY_OK  = 'VACÍO CORRECTO';
	const GENERIC   = 'POSIBLEMENTE GENÉRICO';
	const MISSING   = 'SIN ALT';

	/** Partes de un nombre de archivo que no describen nada. */
	const NOISE = array( 'img', 'image', 'imagen', 'imagenes', 'dsc', 'dscn', 'dscf', 'pxl', 'photo', 'screenshot', 'captura', 'whatsapp', 'copia', 'copy', 'final', 'min', 'scaled', 'edited', 'editada', 'nuevo', 'nueva', 'new', 'hd', 'fhd', 'uhd', 'jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'svg', 'at', 'mobile', 'desktop', 'movil', 'version', 'rotated', 'cropped', 'resized', 'unnamed', 'default', 'placeholder', 'hero', 'slide', 'slider', 'banner', 'header', 'cover', 'thumbnail', 'thumb', 'comprimida', 'comprimido', 'optimizada', 'optimizado', 'optimized', 'compressed' );
	/** Nombres de archivo de fondos y adornos: su ALT correcto es vacío. */
	const DECOR = array( 'fondo', 'fondos', 'background', 'bg', 'pattern', 'patron', 'shape', 'divider', 'separador', 'overlay', 'textura', 'texture', 'decoracion', 'decorativo', 'decorativa', 'ornamento' );
	/** Variantes de un logo propio: «Logo-solo-blanco» es el logo del sitio. */
	const VARIANTS = array( 'solo', 'blanco', 'blanca', 'negro', 'negra', 'white', 'black', 'color', 'colores', 'horizontal', 'vertical', 'mini', 'small', 'footer', 'header', 'principal', 'main', 'oficial', 'alt', 'transparente', 'transparent', 'dark', 'light', 'claro', 'oscuro', 'icono', 'icon', 'isotipo', 'sitio', 'site', 'web' );
	/** Siglas que un nombre de archivo en minúsculas pierde. */
	const ACRONYMS = array( 'seo', 'sem', 'ai', 'ia', 'ux', 'ui', 'crm', 'erp', 'api', 'pdf', 'pos', 'b2b', 'b2c', 'cta', 'kpi', 'roi', 'url', 'html', 'css', 'php', 'sms', 'qr', 'gps', 'tv', 'pyme', 'pymes' );
	const LOGO_WORDS = array( 'logo', 'logos', 'logotipo', 'logotype', 'isotipo', 'imagotipo', 'brand' );
	/** Palabras que en un nombre de archivo indican objeto, no persona. */
	const THINGS = array( 'grafico', 'grafica', 'banner', 'fondo', 'icono', 'mapa', 'equipo', 'oficina', 'producto', 'servicio', 'servicios', 'portada', 'hero', 'slide', 'mockup', 'infografia', 'diagrama', 'mercado', 'marketing', 'diseno', 'web', 'tienda', 'cliente', 'clientes', 'proyecto', 'evento', 'curso', 'blog', 'post', 'galeria', 'foto', 'video' );
	/** Nombres de pila frecuentes: «Nombre-Apellido» sólo es una persona si empieza por uno. */
	const FIRST_NAMES = array( 'adriana', 'adrian', 'alberto', 'alejandra', 'alejandro', 'alfredo', 'alicia', 'ana', 'andrea', 'andres', 'angel', 'angelica', 'antonio', 'armando', 'arturo', 'beatriz', 'carla', 'carlos', 'carmen', 'carolina', 'cesar', 'claudia', 'cristina', 'daniel', 'daniela', 'david', 'diana', 'diego', 'eduardo', 'elena', 'emilio', 'enrique', 'erika', 'ernesto', 'eva', 'fernanda', 'fernando', 'francisco', 'gabriel', 'gabriela', 'gerardo', 'guadalupe', 'guillermo', 'gustavo', 'hector', 'hugo', 'ignacio', 'isabel', 'ivan', 'jaime', 'javier', 'jesus', 'jorge', 'jose', 'josefina', 'juan', 'julia', 'julio', 'karen', 'karla', 'laura', 'leticia', 'lorena', 'lucia', 'luis', 'luisa', 'manuel', 'marco', 'marcos', 'margarita', 'maria', 'mariana', 'mario', 'marta', 'martin', 'martha', 'miguel', 'monica', 'natalia', 'norma', 'oscar', 'pablo', 'patricia', 'paola', 'pedro', 'rafael', 'raul', 'ricardo', 'roberto', 'rocio', 'rodrigo', 'rosa', 'ruben', 'samuel', 'sandra', 'santiago', 'sara', 'sergio', 'silvia', 'sofia', 'susana', 'teresa', 'valeria', 'veronica', 'victor', 'ximena', 'yolanda', 'alan', 'saul', 'raul', 'adan', 'ruth', 'gloria', 'irma', 'alma', 'olga', 'ana', 'abril', 'regina', 'renata', 'emiliano', 'mateo', 'leonardo', 'sebastian', 'nicolas', 'joel', 'omar', 'felipe', 'hugo', 'john', 'james', 'michael', 'robert', 'william', 'mary', 'jennifer', 'linda', 'sarah', 'emily', 'anna', 'peter', 'paul', 'mark', 'thomas', 'richard', 'joseph', 'charles', 'chris', 'laura', 'lisa', 'kevin', 'brian' );
	const STOPWORDS = array( 'para', 'como', 'desde', 'hasta', 'entre', 'sobre', 'with', 'from', 'that', 'this', 'your', 'nuestro', 'nuestra', 'mejor', 'mejores', 'the', 'and', 'los', 'las', 'del', 'una', 'uno', 'por', 'con', 'sin', 'que' );
	const GENERIC_ALTS = array( 'imagen', 'image', 'foto', 'fotografia', 'photo', 'picture', 'img', 'logo', 'banner', 'icono', 'icon', 'grafico', 'sin titulo', 'untitled', 'default', 'placeholder', 'captura', 'slide', 'hero', 'fondo' );
	/** Tildes frecuentes que un nombre de archivo pierde. */
	const ACCENTS = array( 'grafico' => 'gráfico', 'graficos' => 'gráficos', 'grafica' => 'gráfica', 'graficas' => 'gráficas', 'analisis' => 'análisis', 'mexico' => 'México', 'vinculacion' => 'vinculación', 'torreon' => 'Torreón', 'nucleo' => 'núcleo', 'tecnologico' => 'tecnológico', 'basico' => 'básico', 'dias' => 'días', 'pagina' => 'página', 'saldana' => 'Saldaña', 'penoles' => 'Peñoles', 'espanol' => 'español', 'diseno' => 'diseño', 'disenos' => 'diseños', 'pagina' => 'página', 'paginas' => 'páginas', 'tecnologia' => 'tecnología', 'informacion' => 'información', 'comunicacion' => 'comunicación', 'publicacion' => 'publicación', 'optimizacion' => 'optimización', 'educacion' => 'educación', 'administracion' => 'administración', 'ubicacion' => 'ubicación', 'camara' => 'cámara', 'telefono' => 'teléfono', 'musica' => 'música', 'economia' => 'economía', 'logistica' => 'logística', 'credito' => 'crédito', 'estadistica' => 'estadística', 'estadisticas' => 'estadísticas', 'metrica' => 'métrica', 'metricas' => 'métricas', 'publico' => 'público', 'tecnico' => 'técnico', 'numero' => 'número', 'catalogo' => 'catálogo', 'articulo' => 'artículo', 'campana' => 'campaña', 'nino' => 'niño', 'ninos' => 'niños', 'menu' => 'menú', 'electronico' => 'electrónico', 'fotografia' => 'fotografía', 'estrategico' => 'estratégico', 'automatizacion' => 'automatización', 'creacion' => 'creación', 'inversion' => 'inversión', 'promocion' => 'promoción', 'solucion' => 'solución', 'soluciones' => 'soluciones', 'atencion' => 'atención', 'cafe' => 'café', 'dia' => 'día', 'guia' => 'guía', 'galeria' => 'galería', 'infografia' => 'infografía' );

	public static function sanitize_mode( $value ) {
		return in_array( $value, self::MODES, true ) ? $value : 'contextual';
	}

	public static function sanitize_keyword( $value ) {
		$value = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $value ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 80 ) : substr( $value, 0, 80 );
	}

	/**
	 * Palabra clave de apoyo a partir de la antigua plantilla «Alt predeterminado»:
	 * se conserva su texto fijo y se descartan las variables.
	 */
	public static function legacy_keyword( $template ) {
		$text = preg_replace( '/%[a-z_]+%/i', ' ', (string) $template );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ), " \t\n\r\0\x0B|-–—·:,;" );
		return self::sanitize_keyword( $text );
	}

	public static function enabled() {
		return (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_alt_optimize' );
	}

	/* ------------------------------------------------------------------ *
	 * Texto
	 * ------------------------------------------------------------------ */

	private static function lower( $value ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $value, 'UTF-8' ) : strtolower( (string) $value );
	}

	private static function length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value, 'UTF-8' ) : strlen( (string) $value );
	}

	private static function ucfirst( $value ) {
		if ( '' === $value || ! function_exists( 'mb_substr' ) ) return ucfirst( $value );
		return mb_strtoupper( mb_substr( $value, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $value, 1, null, 'UTF-8' );
	}

	/** Sin mayúsculas, tildes ni puntuación: para comparar textos. */
	public static function normalize( $value ) {
		$value = strtr( self::lower( trim( (string) $value ) ), array( 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ò' => 'o' ) );
		return trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value ) );
	}

	public static function plain( $value, $max = 100 ) {
		$value = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( (string) $value ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( self::length( $value ) <= $max ) return $value;
		$cut = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max, 'UTF-8' ) : substr( $value, 0, $max );
		return rtrim( preg_replace( '/\s+\S*$/u', '', $cut ) );
	}

	private static function words( $value ) {
		$words = array();
		foreach ( explode( ' ', self::normalize( $value ) ) as $word ) if ( self::length( $word ) >= 4 && ! in_array( $word, self::STOPWORDS, true ) ) $words[] = $word;
		return $words;
	}

	private static function stem( $word ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 5, 'UTF-8' ) : substr( $word, 0, 5 );
	}

	/** ¿La palabra clave comparte vocabulario con el contexto de la imagen? */
	public static function relevant( $keyword, $context ) {
		$context = array_map( array( __CLASS__, 'stem' ), self::words( $context ) );
		foreach ( self::words( $keyword ) as $word ) if ( in_array( self::stem( $word ), $context, true ) ) return true;
		return false;
	}

	/**
	 * Nombre de archivo o título de adjunto en texto legible: sin extensión,
	 * tamaño, sufijos de WordPress, prefijos de cámara ni hashes.
	 *
	 * Un nombre escrito con Mayúsculas Iniciales («Red-ESR-Laguna») conserva
	 * sus mayúsculas: suelen ser nombres propios. Uno en minúsculas se escribe
	 * como frase, con sus tildes y siglas recuperadas.
	 *
	 * @return array text, logo (dice «logo»), site_logo (logo sin marca: el del
	 *               sitio), person (Nombre Apellido), decorative (fondo o adorno).
	 */
	public static function clean( $name ) {
		$raw = (string) preg_replace( '/[?#].*$/', '', basename( str_replace( '\\', '/', (string) $name ) ) );
		$raw = rawurldecode( preg_replace( '/\.(?:jpe?g|png|gif|webp|avif|svg|bmp|tiff?|heic)$/i', '', $raw ) );
		$raw = preg_replace( '/(?:-\d+x\d+|-scaled|-rotated|-e\d{10,}|@\dx)+$/i', '', $raw );
		$empty  = array( 'text' => '', 'logo' => false, 'site_logo' => false, 'person' => false, 'decorative' => false );
		if ( in_array( self::normalize( $raw ), array( 'sin titulo', 'untitled', 'sin nombre' ), true ) ) return $empty;
		$tokens = array_values( array_filter( preg_split( '/[\s_\-.+,()]+/u', $raw ), 'strlen' ) );
		$logo = false;
		$decorative = false;
		$kept = array();
		foreach ( $tokens as $token ) {
			$key = self::normalize( $token );
			if ( in_array( $key, self::LOGO_WORDS, true ) ) { $logo = true; continue; }
			if ( in_array( $key, self::DECOR, true ) ) $decorative = true;
			if ( preg_match( '/^\d+$/', $key ) ) {
				// «1000» forma parte del nombre; «1», «2024» o «20240501» no.
				if ( strlen( $key ) >= 3 && strlen( $key ) <= 5 && ! preg_match( '/^(?:19|20)\d\d$/', $key ) ) $kept[] = $token;
				continue;
			}
			if ( in_array( $key, self::NOISE, true ) || preg_match( '/^v\d+$/', $key ) || ( preg_match( '/\d/', $key ) && preg_match( '/^[a-z0-9]{6,}$/', $key ) && ! preg_match( '/^[a-z]+\d{1,2}$/', $key ) ) || ( self::length( $key ) < 2 && ! in_array( $key, array( 'y', 'e', 'o', 'u', 'a' ), true ) ) ) continue;
			$kept[] = $token;
		}
		$meaningful = array_values( array_filter( $kept, function( $token ) { return (bool) preg_match( '/\p{L}{2,}/u', $token ); } ) );
		$variants   = array_filter( $meaningful, function( $token ) { return ! in_array( self::normalize( $token ), self::VARIANTS, true ); } );
		$site_logo  = $logo && ! $variants;
		if ( ! $meaningful || ! preg_grep( '/\p{L}{3,}/u', $meaningful ) ) return array( 'logo' => $logo, 'site_logo' => $site_logo, 'decorative' => $decorative ) + $empty;
		$letters = array_values( array_filter( $kept, function( $token ) { return (bool) preg_match( '/^\p{L}/u', $token ); } ) );
		$titled  = $letters && count( $letters ) === count( preg_grep( '/^\p{Lu}/u', $letters ) );
		$shout   = $letters && count( $letters ) === count( preg_grep( '/^[\p{Lu}\d]+$/u', $letters ) );
		$person  = ! $logo && ! $shout && count( $kept ) >= 2 && count( $kept ) <= 4 && count( $kept ) === count( $tokens ) && in_array( self::normalize( $kept[0] ), self::FIRST_NAMES, true ) && $titled;
		$words = array();
		foreach ( $kept as $i => $token ) {
			$key = self::normalize( $token );
			if ( isset( self::ACCENTS[ $key ] ) ) {
				$word = self::ACCENTS[ $key ];
				$token = $titled && ! $shout ? self::ucfirst( $word ) : $word;
			} elseif ( in_array( $key, self::ACRONYMS, true ) ) {
				$token = strtoupper( $key );
			} elseif ( ! $titled || $shout ) {
				$token = self::lower( $token );
			}
			$words[] = $token;
		}
		return array( 'text' => self::plain( self::ucfirst( implode( ' ', $words ) ), 100 ), 'logo' => $logo, 'site_logo' => $site_logo, 'person' => $person, 'decorative' => $decorative );
	}

	/** ¿El texto ya dice casi todo lo que dice la descripción? (mitad o más de sus palabras). */
	public static function covers( $text, $description ) {
		$words = self::words( $description );
		if ( ! $words ) return true;
		$have = array_map( array( __CLASS__, 'stem' ), self::words( $text ) );
		$hits = count( array_filter( $words, function( $word ) use ( $have ) { return in_array( self::stem( $word ), $have, true ); } ) );
		return $hits / count( $words ) >= 0.5;
	}

	/** ALT que no describe la imagen concreta. */
	public static function generic( $alt, $page = array() ) {
		$key = self::normalize( $alt );
		if ( '' === $key ) return false;
		if ( in_array( $key, self::GENERIC_ALTS, true ) ) return true;
		foreach ( array( 'site', 'keyword' ) as $name ) if ( ! empty( $page[ $name ] ) && self::normalize( $page[ $name ] ) === $key ) return true;
		$alt = trim( (string) $alt );
		return (bool) preg_match( '/\.(?:jpe?g|png|gif|webp|avif|svg)$/i', $alt ) || ( strlen( $alt ) > 12 && ! preg_match( '/\s/', $alt ) && preg_match( '/[-_]/', $alt ) );
	}

	/* ------------------------------------------------------------------ *
	 * Generación
	 * ------------------------------------------------------------------ */

	/** Estado inicial de una página. */
	public static function page( $site, $description, $keyword, $mode, $title ) {
		return array( 'site' => (string) $site, 'description' => (string) $description, 'keyword' => self::sanitize_keyword( $keyword ), 'mode' => self::sanitize_mode( $mode ), 'title' => self::plain( $title, 100 ), 'heading' => '', 'used' => array(), 'by_id' => array(), 'keyword_used' => 0 );
	}

	private static function used( $alt, $page ) {
		return isset( $page['used'][ self::normalize( $alt ) ] );
	}

	/** «Nombre del sitio - descripción», sin repetir lo que el nombre ya dice. */
	public static function site_alt( $page ) {
		$description = trim( (string) $page['description'] );
		return self::plain( $page['site'] . ( '' !== $description && ! self::covers( $page['site'], $description ) ? ' - ' . $description : '' ), 125 );
	}

	/** Palabras de la palabra clave que el ALT aún no contiene. */
	private static function missing_words( $keyword, $alt ) {
		return array_values( array_filter( preg_split( '/\s+/u', $keyword ), function( $word ) use ( $alt ) {
			$key = self::normalize( $word );
			return '' !== $key && false === strpos( ' ' . self::normalize( $alt ) . ' ', ' ' . $key . ' ' );
		} ) );
	}

	/**
	 * ALT contextual para una imagen sin ALT.
	 *
	 * @param array $ctx  title (del adjunto), file, widget_title, main_logo.
	 * @param array $page Estado de la página (se actualiza el uso de la palabra clave).
	 * @return array alt, source. Un alt vacío con source es una decisión: alt="".
	 */
	public static function generate( $ctx, &$page ) {
		$title = self::clean( $ctx['title'] ?? '' );
		$file  = self::clean( $ctx['file'] ?? '' );
		if ( ( ! empty( $ctx['main_logo'] ) || $title['site_logo'] || $file['site_logo'] ) && '' !== $page['site'] ) return array( 'alt' => self::site_alt( $page ), 'source' => 'Logo del sitio' );
		// Fondos y adornos: alt="" es lo correcto para un lector de pantalla.
		if ( $file['decorative'] || $title['decorative'] ) return array( 'alt' => '', 'source' => 'Fondo o adorno (por su nombre)' );
		// Un título que repite el nombre del archivo no aporta nada nuevo.
		if ( '' !== $title['text'] && self::normalize( $title['text'] ) === self::normalize( $file['text'] ) ) $title['text'] = '';
		$candidates = array(
			array( 'Título del adjunto', $title['text'], $title ),
			array( 'Nombre del archivo', $file['text'], $file ),
			array( 'Título del widget', self::plain( $ctx['widget_title'] ?? '' ), null ),
			array( 'Encabezado cercano', self::plain( $page['heading'] ?? '' ), null ),
			array( 'Contenido de la página', self::plain( $page['title'] ?? '' ), null ),
		);
		$chosen = null;
		foreach ( $candidates as $candidate ) {
			if ( '' === $candidate[1] ) continue;
			if ( ! $chosen ) $chosen = $candidate;
			if ( ! self::used( $candidate[1], $page ) ) { $chosen = $candidate; break; }
		}
		$keyword = $page['keyword'];
		if ( ! $chosen ) {
			if ( 'off' !== $page['mode'] && '' !== $keyword && ! $page['keyword_used'] ) { ++$page['keyword_used']; return array( 'alt' => $keyword, 'source' => 'Palabra clave de apoyo (sin otro contexto)' ); }
			return array( 'alt' => '', 'source' => '' );
		}
		$alt  = $chosen[1];
		$info = $chosen[2] ?: array( 'logo' => $title['logo'] || $file['logo'], 'person' => false );
		if ( 'contextual' === $page['mode'] && empty( $info['logo'] ) ) {
			if ( ! empty( $info['person'] ) && '' !== $page['site'] && false === stripos( self::normalize( $alt ), self::normalize( $page['site'] ) ) ) {
				$alt .= ' de ' . $page['site'];
			} elseif ( '' !== $keyword && ! $page['keyword_used'] ) {
				$missing = self::missing_words( $keyword, $alt );
				$clean   = $missing && count( $missing ) <= 2 && count( $missing ) === count( array_filter( $missing, function( $word ) { return self::length( $word ) >= 4 && ! in_array( self::normalize( $word ), self::STOPWORDS, true ); } ) );
				if ( $clean && self::relevant( $keyword, $alt ) ) {
					// Comparte palabras con la imagen y sólo faltan una o dos: se completa sin repetir.
					$alt .= ' ' . self::lower( implode( ' ', $missing ) );
					++$page['keyword_used'];
				} elseif ( $missing && ! self::relevant( $keyword, $alt ) && count( preg_split( '/\s+/u', $keyword ) ) <= 4 && self::relevant( $keyword, ( $ctx['widget_title'] ?? '' ) . ' ' . ( $page['heading'] ?? '' ) ) ) {
					$alt .= ' – ' . $keyword;
					++$page['keyword_used'];
				}
				// En cualquier otro caso la palabra clave no encaja con esta imagen: no se fuerza.
			}
		}
		return array( 'alt' => self::plain( $alt, 125 ), 'source' => $chosen[0] );
	}

	/** El ALT manual es el nombre del archivo tal cual («fondo-olas-1»). */
	private static function is_filename( $alt, $file ) {
		$stem = preg_replace( '/(?:-\d+x\d+|-scaled)+$/i', '', preg_replace( '/\.[a-z0-9]+$/i', '', basename( (string) $file ) ) );
		return '' !== $stem && self::normalize( $stem ) === self::normalize( $alt ) && (bool) preg_match( '/[-_]/', $alt );
	}

	/**
	 * Decide el ALT publicado de una imagen.
	 *
	 * @param array $attrs Atributos de la etiqueta tal como llegó.
	 * @param array $ctx   attachment (ID o 0), meta_alt, role, caption, skip_empty,
	 *                     title, file, widget_title, main_logo.
	 * @param array $page  Estado de la página.
	 * @return array alt (null = no cambiar), final, state, source.
	 */
	public static function apply( $attrs, $ctx, &$page ) {
		$has   = array_key_exists( 'alt', $attrs );
		$alt   = trim( (string) ( $attrs['alt'] ?? '' ) );
		$id    = (int) ( $ctx['attachment'] ?? 0 );
		$caption = self::normalize( $ctx['caption'] ?? '' );
		if ( 'decorative' === ( $ctx['role'] ?? '' ) ) return array( 'alt' => '' === $alt && $has ? null : '', 'final' => '', 'state' => self::DECORATIVE, 'source' => 'Clasificada como decorativa' );

		$manual = '' !== $alt ? $alt : ( $id ? trim( (string) ( $ctx['meta_alt'] ?? '' ) ) : '' );
		if ( '' !== $manual ) {
			if ( '' !== $caption && self::normalize( $manual ) === $caption ) return array( 'alt' => '', 'final' => '', 'state' => self::CAPTION, 'source' => 'El caption visible ya lo describe' );
			$state = self::generic( $manual, $page ) || self::is_filename( $manual, $ctx['file'] ?? '' ) ? self::GENERIC : ( self::used( $manual, $page ) ? self::REPEATED : self::MANUAL );
			$page['used'][ self::normalize( $manual ) ] = true;
			if ( $id && ! isset( $page['by_id'][ $id ] ) ) $page['by_id'][ $id ] = $manual;
			return array( 'alt' => $manual === $alt ? null : $manual, 'final' => $manual, 'state' => $state, 'source' => 'Manual' );
		}
		if ( ! empty( $ctx['skip_empty'] ) ) return array( 'alt' => $has ? null : '', 'final' => '', 'state' => self::EMPTY_OK, 'source' => 'Icono o píxel' );
		// alt="" en una imagen ajena a la Biblioteca es una decisión de quien la puso.
		if ( ! $id && $has ) return array( 'alt' => null, 'final' => '', 'state' => self::EMPTY_OK, 'source' => 'Vacío en origen' );
		// La misma imagen repetida en la página dice lo mismo en todas sus apariciones.
		if ( $id && isset( $page['by_id'][ $id ] ) ) {
			$same = $page['by_id'][ $id ];
			if ( '' !== $caption && self::normalize( $same ) === $caption ) return array( 'alt' => $has ? null : '', 'final' => '', 'state' => self::EMPTY_OK, 'source' => 'El caption visible ya lo describe' );
			return array( 'alt' => $same === $alt ? null : $same, 'final' => $same, 'state' => '' === $same ? self::EMPTY_OK : self::REPEATED, 'source' => 'Misma imagen en esta página' );
		}

		$generated = self::generate( $ctx, $page );
		if ( '' === $generated['alt'] ) {
			if ( '' === $generated['source'] ) return array( 'alt' => null, 'final' => $has ? '' : null, 'state' => self::MISSING, 'source' => '' );
			if ( $id ) $page['by_id'][ $id ] = '';
			return array( 'alt' => $has ? null : '', 'final' => '', 'state' => self::EMPTY_OK, 'source' => $generated['source'] );
		}
		if ( '' !== $caption && self::normalize( $generated['alt'] ) === $caption ) return array( 'alt' => $has ? null : '', 'final' => '', 'state' => self::EMPTY_OK, 'source' => 'El caption visible ya lo describe' );
		$state = self::used( $generated['alt'], $page ) ? self::REPEATED : self::GENERATED;
		$page['used'][ self::normalize( $generated['alt'] ) ] = true;
		if ( $id ) $page['by_id'][ $id ] = $generated['alt'];
		return array( 'alt' => $generated['alt'], 'final' => $generated['alt'], 'state' => $state, 'source' => $generated['source'] );
	}

	/* ------------------------------------------------------------------ *
	 * Contexto en el HTML
	 * ------------------------------------------------------------------ */

	/** figcaption de la misma <figure> que la imagen, si la hay. */
	public static function caption( $html, $offset, $length ) {
		$before = substr( $html, max( 0, $offset - 3000 ), min( $offset, 3000 ) );
		$open   = strripos( $before, '<figure' );
		if ( false === $open || false !== stripos( substr( $before, $open ), '</figure' ) ) return '';
		$after = substr( $html, $offset + $length, 3000 );
		$close = stripos( $after, '</figure' );
		if ( false === $close ) return '';
		$inside = substr( $after, 0, $close );
		if ( false !== stripos( $inside, '<img' ) || ! preg_match( '~<figcaption\b[^>]*>(.*?)</figcaption~is', $inside, $m ) ) return '';
		return self::plain( $m[1], 300 );
	}

	/** Título del mismo widget (Image Box, Call to Action, Flip Box, testimonio) tras la imagen. */
	public static function widget_title( $html, $offset, $length ) {
		$after = substr( $html, $offset + $length, 2500 );
		$stop  = stripos( $after, '<img' );
		if ( false !== $stop ) $after = substr( $after, 0, $stop );
		if ( preg_match( '~class\s*=\s*["\'][^"\']*\b(?:elementor-image-box-title|elementor-cta__title|elementor-flip-box__layer__title|elementor-testimonial__name|elementor-testimonial-name|elementor-team-member__name)\b[^"\']*["\'][^>]*>(.*?)</(?:h[1-6]|div|p|span|a)\s*>~is', $after, $m ) ) return self::plain( $m[1] );
		return '';
	}

	/** Logo del sitio: clase custom-logo o widget Site Logo de Elementor. */
	public static function main_logo( $html, $offset, $attrs, $logo_id, $id ) {
		if ( $logo_id && (int) $logo_id === (int) $id ) return true;
		if ( preg_match( '/\bcustom-logo\b/', (string) ( $attrs['class'] ?? '' ) ) ) return true;
		$before = substr( $html, max( 0, $offset - 1500 ), min( $offset, 1500 ) );
		if ( ! preg_match_all( '/\belementor-widget-(?!container\b)([a-z0-9-]+)/', $before, $m ) ) return false;
		return 'theme-site-logo' === end( $m[1] );
	}
}
