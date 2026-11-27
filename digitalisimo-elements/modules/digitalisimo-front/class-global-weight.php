<?php
namespace Digitalisimo\Elements\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Aviso de plantillas globales pesadas (B1 de la auditoría de digitalisimo.lat, 2026-11-24): el pie 9353, que incrusta el
 * contenedor 13280, metía unas 1 350 palabras y ocho H2 de la portada en cada URL, y nadie lo veía al editar.
 *
 * Qué es global: una plantilla del Theme Builder con una condición que la aplica a todo el sitio (`include/general`) o a
 * todas las entradas y páginas (`include/singular`) o a todos los archivos (`include/archive`), según la caché de
 * condiciones del propio Theme Builder (`elementor_pro_theme_builder_conditions`). Las exclusiones no se restan: el
 * aviso habla de lo que se repite «en casi todas las páginas».
 *
 * Qué se mide: el HTML que la plantilla pinta (con lo que incrusta: plantillas, contenedores), con el renderizador del
 * constructor: palabras de texto visible en el HTML, H1 y H2. Es pesada (`heavy`) con `LIMITS` (filtro
 * `digitalisimo_elements_global_weight_limits`): al menos un H1, tres H2 o 250 palabras. Una plantilla pesada nunca se
 * cambia: sólo se avisa.
 *
 * Cuándo: **nunca en el frente**. Un guardado de una plantilla del constructor (`elementor/document/after_save`), un
 * cambio de las condiciones, borrar o restaurar una plantilla, o importar un kit programan un único evento de WP-Cron
 * (`HOOK`) que lo calcula todo en segundo plano; la administración lo programa si nunca se calculó o tiene más de
 * `MAX_AGE`; «Analizar ahora» en la pantalla «Rendimiento» lo calcula en esa petición. El resultado queda en `OPTION`
 * (sin carga automática).
 *
 * Dónde se avisa:
 * - en el editor, al abrir una plantilla pesada o una plantilla que una pesada incrusta (aviso fijo del editor);
 * - en «Rendimiento» (sección «Plantillas que se repiten en cada página»), con la tabla y el botón;
 * - en el «Análisis SEO» de cada contenido, si DIGITALÍSIMO SEO está activo (`digitalisimo_seo_analysis_notes`), como
 *   nota informativa que no cambia la puntuación.
 */
final class Global_Weight {
	const OPTION  = 'digitalisimo_elements_global_weight';
	const HOOK    = 'digitalisimo_elements_global_weight';
	const ACTION  = 'digitalisimo_elements_global_weight_scan';
	const MAX_AGE = 604800;

	/** Condiciones del Theme Builder que aplican la plantilla en todo el sitio o en todo un tipo de página. */
	const GLOBAL_CONDITIONS = array( 'include/general', 'include/singular', 'include/archive' );

	/** Umbrales: una plantilla global es pesada con al menos esto. */
	const LIMITS = array(
		'h1'    => 1,
		'h2'    => 3,
		'words' => 250,
	);

	const CONDITIONS_OPTION = 'elementor_pro_theme_builder_conditions';

	/** @var bool */
	private static $initialized = false;

	/** @var callable|null Para las pruebas: `function( int $id ): ?string` en lugar del renderizador del constructor. */
	public static $renderer = null;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_action( self::HOOK, array( __CLASS__, 'compute' ) );
		add_action( 'elementor/document/after_save', array( __CLASS__, 'document_saved' ), 20, 1 );
		add_action( 'update_option_' . self::CONDITIONS_OPTION, array( __CLASS__, 'schedule' ), 10, 0 );
		add_action( 'add_option_' . self::CONDITIONS_OPTION, array( __CLASS__, 'schedule' ), 10, 0 );
		add_action( 'trashed_post', array( __CLASS__, 'post_changed' ), 10, 1 );
		add_action( 'untrashed_post', array( __CLASS__, 'post_changed' ), 10, 1 );
		add_action( 'deleted_post', array( __CLASS__, 'post_changed' ), 10, 1 );
		add_action( 'digitalisimo_elements_kit_imported', array( __CLASS__, 'schedule' ), 10, 0 );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_scan' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue_editor' ) );
		add_filter( 'digitalisimo_seo_analysis_notes', array( __CLASS__, 'seo_notes' ), 10, 2 );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Cuándo se calcula.

	/** Un guardado del constructor: sólo una plantilla (`elementor_library`) cambia lo que se repite. */
	public static function document_saved( $document ): void {
		$id = is_object( $document ) && method_exists( $document, 'get_main_id' ) ? (int) $document->get_main_id() : 0;
		if ( $id > 0 && 'elementor_library' === get_post_type( $id ) ) {
			self::schedule();
		}
	}

	/** Borrar, mandar a la papelera o restaurar una plantilla. */
	public static function post_changed( $post_id ): void {
		if ( 'elementor_library' === get_post_type( (int) $post_id ) ) {
			self::schedule();
		}
	}

	/** Un solo evento pendiente, en segundo plano. */
	public static function schedule(): void {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::HOOK );
		}
	}

	/** En la administración: si nunca se calculó o el cálculo es viejo, se programa (no se calcula aquí). */
	public static function admin_init(): void {
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}
		$data = self::data();
		if ( ! $data['computed'] || ( time() - $data['computed'] ) > self::MAX_AGE ) {
			self::schedule();
		}
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Cálculo.

	/** Plantillas globales publicadas: ID => ubicación (header, footer, single…). */
	public static function global_templates(): array {
		$conditions = get_option( self::CONDITIONS_OPTION, array() );
		$out        = array();
		if ( ! is_array( $conditions ) ) {
			return $out;
		}
		foreach ( $conditions as $location => $templates ) {
			if ( ! is_array( $templates ) ) {
				continue;
			}
			foreach ( $templates as $id => $list ) {
				$id = (int) $id;
				if ( $id <= 0 || ! is_array( $list ) || ! array_intersect( self::GLOBAL_CONDITIONS, array_map( 'strval', $list ) ) ) {
					continue;
				}
				if ( 'publish' !== get_post_status( $id ) ) {
					continue;
				}
				$out[ $id ] = sanitize_key( (string) $location );
			}
		}
		return $out;
	}

	/**
	 * Mide el HTML de una plantilla: palabras de texto visible, H1, H2 y los primeros títulos (60 caracteres) de cada uno.
	 *
	 * @return array{words:int,h1:int,h2:int,headings:string[]}
	 */
	public static function measure( string $html ): array {
		$html = (string) preg_replace( '#<(script|style|template|noscript|svg)\b.*?</\1\s*>#is', ' ', $html );
		$html = (string) preg_replace( '#<!--.*?-->#s', ' ', $html );
		preg_match_all( '#<(h[12])\b[^>]*>(.*?)</\1\s*>#is', $html, $found, PREG_SET_ORDER );
		$headings = array();
		foreach ( $found as $match ) {
			$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( $match[2] ), ENT_QUOTES, 'UTF-8' ) ) );
			if ( count( $headings ) < 6 ) {
				$headings[] = strtoupper( $match[1] ) . ': ' . ( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 60, 'UTF-8' ) : substr( $text, 0, 60 ) );
			}
		}
		$text = html_entity_decode( strip_tags( (string) preg_replace( '/<[^>]+>/', ' $0 ', $html ) ), ENT_QUOTES, 'UTF-8' );
		return array(
			'words'    => (int) preg_match_all( '/[\p{L}\p{N}]+/u', $text ),
			'h1'       => (int) preg_match_all( '/<h1\b/i', $html ),
			'h2'       => (int) preg_match_all( '/<h2\b/i', $html ),
			'headings' => $headings,
		);
	}

	/** Umbrales en vigor (filtrables), enteros ≥ 1. */
	public static function limits(): array {
		$limits = function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_elements_global_weight_limits', self::LIMITS ) : self::LIMITS;
		$out    = array();
		foreach ( self::LIMITS as $key => $default ) {
			$out[ $key ] = is_array( $limits ) && isset( $limits[ $key ] ) && is_numeric( $limits[ $key ] ) ? max( 1, (int) $limits[ $key ] ) : $default;
		}
		return $out;
	}

	/** Motivos por los que una medida es pesada (vacío: no lo es). */
	public static function reasons( array $m ): array {
		$limits  = self::limits();
		$reasons = array();
		if ( (int) ( $m['h1'] ?? 0 ) >= $limits['h1'] ) {
			$reasons[] = sprintf( '%d H1', (int) $m['h1'] );
		}
		if ( (int) ( $m['h2'] ?? 0 ) >= $limits['h2'] ) {
			$reasons[] = sprintf( '%d H2', (int) $m['h2'] );
		}
		if ( (int) ( $m['words'] ?? 0 ) >= $limits['words'] ) {
			$reasons[] = sprintf( 'unas %s palabras', number_format( (int) $m['words'], 0, ',', ' ' ) );
		}
		return $reasons;
	}

	/** IDs de plantillas que incrusta un documento (widgets Plantilla, propios o de PRO, por `template_id`). */
	public static function embedded( int $id, int $depth = 0 ): array {
		if ( $depth > 4 || ! function_exists( 'get_post_meta' ) ) {
			return array();
		}
		$raw = get_post_meta( $id, '_elementor_data', true );
		$raw = is_array( $raw ) ? (string) wp_json_encode( $raw ) : (string) $raw;
		if ( ! preg_match_all( '/"template_id"\s*:\s*"?(\d+)"?/', $raw, $found ) ) {
			return array();
		}
		$ids = array();
		foreach ( array_unique( array_map( 'intval', $found[1] ) ) as $child ) {
			if ( $child > 0 && $child !== $id ) {
				$ids[] = $child;
				$ids   = array_merge( $ids, self::embedded( $child, $depth + 1 ) );
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/** HTML de una plantilla con el renderizador del constructor (fuera del frente), o null si no se pudo. */
	public static function render( int $id ): ?string {
		if ( is_callable( self::$renderer ) ) {
			$html = call_user_func( self::$renderer, $id );
			return is_string( $html ) ? $html : null;
		}
		if ( ! class_exists( '\Elementor\Plugin', false ) || ! isset( \Elementor\Plugin::$instance->frontend ) || ! is_object( \Elementor\Plugin::$instance->frontend ) ) {
			return null;
		}
		$level = ob_get_level();
		try {
			ob_start();
			$html  = \Elementor\Plugin::$instance->frontend->get_builder_content( $id, false );
			$extra = (string) ob_get_clean();
			return ( is_string( $html ) ? $html : '' ) . $extra;
		} catch ( \Throwable $e ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			return null;
		}
	}

	/**
	 * Calcula todo (WP-Cron o «Analizar ahora») y lo guarda: por plantilla global, título, ubicación, medidas, motivos
	 * y lo que incrusta, pintadas como en la portada (`front_context()`).
	 */
	public static function compute(): array {
		$templates = array();
		$restore   = self::front_context();
		try {
			$templates = self::measure_all();
		} finally {
			$restore();
		}
		$data = array(
			'computed'  => time(),
			'limits'    => self::limits(),
			'templates' => $templates,
		);
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $data, '', false );
		} else {
			update_option( self::OPTION, $data, false );
		}
		return $data;
	}

	/**
	 * Pinta las plantillas como en la portada (la página de inicio o, si no hay, la última página publicada): sin una
	 * consulta, el título dinámico de la página sale vacío y el Encabezado no se pinta, y un H1 que se repite en cada
	 * página no se vería. Devuelve la función que deja todo como estaba.
	 */
	public static function front_context(): callable {
		$noop = static function () {};
		if ( ! class_exists( '\WP_Query' ) || ! function_exists( 'get_option' ) || ! function_exists( 'get_posts' ) ) {
			return $noop;
		}
		$id = (int) get_option( 'page_on_front' );
		if ( $id <= 0 || 'publish' !== get_post_status( $id ) ) {
			$latest = get_posts(
				array(
					'post_type'        => 'page',
					'post_status'      => 'publish',
					'numberposts'      => 1,
					'fields'           => 'ids',
					'orderby'          => 'date',
					'order'            => 'DESC',
					'suppress_filters' => true,
				)
			);
			$id = $latest ? (int) $latest[0] : 0;
		}
		if ( $id <= 0 ) {
			return $noop;
		}
		$saved = array(
			'wp_query'     => $GLOBALS['wp_query'] ?? null,
			'wp_the_query' => $GLOBALS['wp_the_query'] ?? null,
			'post'         => $GLOBALS['post'] ?? null,
		);
		$query                   = new \WP_Query( array( 'page_id' => $id ) );
		$GLOBALS['wp_query']     = $query;
		$GLOBALS['wp_the_query'] = $query;
		if ( $query->have_posts() ) {
			$query->the_post();
		}
		return static function () use ( $saved ) {
			foreach ( $saved as $key => $value ) {
				$GLOBALS[ $key ] = $value;
			}
			if ( $saved['post'] instanceof \WP_Post && function_exists( 'setup_postdata' ) ) {
				setup_postdata( $saved['post'] );
			}
		};
	}

	/** Mide cada plantilla global (ver `compute()`). */
	private static function measure_all(): array {
		$templates = array();
		foreach ( self::global_templates() as $id => $location ) {
			$html  = self::render( $id );
			$entry = array(
				'title'    => function_exists( 'get_the_title' ) ? wp_strip_all_tags( (string) get_the_title( $id ) ) : '',
				'location' => $location,
				'embeds'   => self::embedded( $id ),
				'error'    => null === $html,
			);
			$measure          = null === $html ? array( 'words' => 0, 'h1' => 0, 'h2' => 0, 'headings' => array() ) : self::measure( $html );
			$entry            = array_merge( $entry, $measure );
			$entry['reasons'] = null === $html ? array() : self::reasons( $measure );
			$templates[ $id ] = $entry;
		}
		return $templates;
	}

	/** Lo guardado, normalizado. */
	public static function data(): array {
		$raw = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'computed'  => isset( $raw['computed'] ) ? (int) $raw['computed'] : 0,
			'templates' => isset( $raw['templates'] ) && is_array( $raw['templates'] ) ? $raw['templates'] : array(),
		);
	}

	/** Plantillas globales pesadas: ID => entrada. */
	public static function heavy(): array {
		return array_filter(
			self::data()['templates'],
			static function ( $entry ) {
				return is_array( $entry ) && ! empty( $entry['reasons'] );
			}
		);
	}

	/** Aviso de una plantilla pesada en una frase. */
	public static function message( int $id, array $entry ): string {
		$where = array(
			'header' => 'La cabecera',
			'footer' => 'El pie',
		);
		$what = $where[ $entry['location'] ?? '' ] ?? 'La plantilla';
		return sprintf(
			'%s «%s» (ID %d) se aplica a todo el sitio y repite en cada página %s. Los buscadores lo ven como contenido duplicado y como títulos de cada página: deja en las plantillas globales sólo navegación, contacto y avisos legales, y pasa el resto a la página que le corresponde.',
			$what,
			(string) ( $entry['title'] ?? '' ),
			$id,
			implode( ', ', (array) ( $entry['reasons'] ?? array() ) )
		);
	}

	/** Avisos para un documento abierto en el editor: el suyo si es pesada y el de cada pesada que lo incrusta. */
	public static function messages_for( int $id ): array {
		$out = array();
		foreach ( self::heavy() as $global => $entry ) {
			$global = (int) $global;
			if ( $global === $id ) {
				$out[] = self::message( $global, $entry );
			} elseif ( in_array( $id, array_map( 'intval', (array) ( $entry['embeds'] ?? array() ) ), true ) ) {
				$out[] = sprintf( 'Esta plantilla va dentro de «%s» (ID %d), que se repite en cada página: %s', (string) ( $entry['title'] ?? '' ), $global, self::message( $global, $entry ) );
			}
		}
		return $out;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Avisos.

	/** Editor: aviso fijo al abrir una plantilla pesada o incrustada en una pesada. Sólo avisa. */
	public static function enqueue_editor(): void {
		$id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo lee qué documento se abre.
		if ( $id <= 0 || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		$messages = self::messages_for( $id );
		if ( ! $messages ) {
			return;
		}
		// El aviso del editor puede pintar HTML: el texto va escapado (el título de una plantilla lo escribe quien edita).
		$script = 'window.digitalisimoGlobalWeight=' . wp_json_encode( array( 'messages' => array_values( array_map( 'esc_html', $messages ) ) ) ) . ';'
			. '(function(w){function show(){var e=w.elementor,c=w.digitalisimoGlobalWeight;if(!e||!e.notifications||!c||c.shown){return;}c.shown=1;'
			. 'c.messages.forEach(function(m){e.notifications.showToast({message:m,sticky:true});});}'
			. 'function bind(){var e=w.elementor,c=w.digitalisimoGlobalWeight;if(!e||!e.on||c.bound){return;}c.bound=1;e.on("preview:loaded",show);}'
			. 'if(w.jQuery){w.jQuery(w).on("elementor/init",bind);}bind();})(window);';
		wp_add_inline_script( 'elementor-editor', $script, 'after' );
	}

	/** «Análisis SEO» de DIGITALÍSIMO SEO: una nota informativa por plantilla global pesada (no cambia la puntuación). */
	public static function seo_notes( $notes, $post = null ) {
		$notes = is_array( $notes ) ? $notes : array();
		foreach ( self::heavy() as $id => $entry ) {
			$notes[] = array(
				'id'     => 'elements_global_template_' . (int) $id,
				'label'  => 'Plantilla que se repite en cada página',
				'status' => 'info',
				'detail' => self::message( (int) $id, $entry ),
			);
		}
		return $notes;
	}

	/** Sección de la pantalla «Rendimiento» del sitio. */
	public static function render_section(): void {
		$data = self::data();
		echo '<h2 id="digitalisimo-global-weight">' . esc_html( 'Plantillas que se repiten en cada página' ) . '</h2>';
		echo '<p>' . esc_html( 'Cabeceras, pies y otras plantillas del Theme Builder que se aplican a todo el sitio. Si una tiene mucho texto o títulos H1 o H2, ese contenido se repite en cada URL: los buscadores lo ven como duplicado y como títulos de cada página. Sólo se avisa: nada se cambia. Se calcula en segundo plano al guardar una plantilla o cambiar sus condiciones.' ) . '</p>';
		if ( ! $data['computed'] ) {
			echo '<p class="description">' . esc_html( 'Aún no se ha analizado este sitio.' ) . '</p>';
		} elseif ( ! $data['templates'] ) {
			echo '<p class="description">' . esc_html( 'Ninguna plantilla del Theme Builder se aplica a todo el sitio.' ) . '</p>';
		} else {
			echo '<table class="widefat striped" style="max-width:960px"><thead><tr><th>' . esc_html( 'Plantilla' ) . '</th><th>' . esc_html( 'Palabras' ) . '</th><th>H1</th><th>H2</th><th>' . esc_html( 'Estado' ) . '</th></tr></thead><tbody>';
			foreach ( $data['templates'] as $id => $entry ) {
				$id     = (int) $id;
				$status = ! empty( $entry['error'] ) ? 'No se pudo analizar' : ( ! empty( $entry['reasons'] ) ? 'Pesada: ' . implode( ', ', (array) $entry['reasons'] ) : 'Ligera' );
				$link   = function_exists( 'get_edit_post_link' ) ? (string) get_edit_post_link( $id ) : '';
				$name   = (string) ( $entry['title'] ?? '' ) . ' (ID ' . $id . ', ' . (string) ( $entry['location'] ?? '' ) . ')';
				echo '<tr><td>' . ( '' !== $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ) ) . '</td>';
				echo '<td>' . esc_html( (string) (int) ( $entry['words'] ?? 0 ) ) . '</td><td>' . esc_html( (string) (int) ( $entry['h1'] ?? 0 ) ) . '</td><td>' . esc_html( (string) (int) ( $entry['h2'] ?? 0 ) ) . '</td><td>' . esc_html( $status ) . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p class="description">' . esc_html( 'Último análisis: ' . ( function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i', $data['computed'] ) : gmdate( 'Y-m-d H:i', $data['computed'] ) ) . '.' ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		wp_nonce_field( self::ACTION . '|' . ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 ) );
		echo '<p><button type="submit" class="button">' . esc_html( 'Analizar ahora' ) . '</button></p></form>';
	}

	/** «Analizar ahora»: `manage_options`, sólo POST y con nonce; vuelve a la sección. */
	public static function handle_scan(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'No tienes permiso para esto.' ), '', array( 'response' => 403 ) );
		}
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) || ! wp_verify_nonce( $nonce, self::ACTION . '|' . get_current_blog_id() ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sólo se compara.
			wp_die( esc_html( 'El formulario caducó. Vuelve a la pantalla e inténtalo de nuevo.' ), '', array( 'response' => 403 ) );
		}
		self::compute();
		wp_safe_redirect( admin_url( 'admin.php?page=digitalisimo-elements-front' ) . '#digitalisimo-global-weight' );
		exit;
	}
}
