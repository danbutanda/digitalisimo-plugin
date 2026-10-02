<?php
defined( 'ABSPATH' ) || exit;

/**
 * Auditoría de calidad frontend: enlaces, encabezados, nombres accesibles, alt,
 * objetivos táctiles, contraste, imágenes medidas y CSS diferible por página.
 *
 * Un administrador inicia la auditoría; la URL pública se abre en su navegador
 * como la ve un visitante sin sesión, se recorre en escritorio y en una vista
 * móvil de 390 px, y `assets/quality-audit.js` envía lo medido. Las reglas de
 * `Digitalisimo_Integrations_Quality_Rules` deciden el estado de cada hallazgo.
 *
 * Sólo se corrige lo determinista y pedido por el administrador: alt="" en las
 * imágenes que él clasifica como decorativas y, si lo activa, un área táctil
 * mínima en los componentes que él enumera. Colores, encabezados, textos de
 * enlace y destinos sólo se informan.
 *
 * Cada resultado pertenece a un sitio (opción propia del blog) y a una URL, y
 * guarda la versión de configuración con la que se midió.
 */
class Digitalisimo_Integrations_Quality_Audit {
	const QUERY  = 'digitalisimo_quality_probe';
	const FRAME  = 'digitalisimo_quality_frame';
	const INDEX  = 'digitalisimo_quality_audits';
	const RECORD = 'digitalisimo_quality_audit_';
	const ROLES  = 'digitalisimo_image_roles';
	const AJAX   = 'digitalisimo_quality_result';
	const KEEP   = 10;
	const TTL    = 600;
	/** Componentes que la protección táctil cubre por defecto: no tienen ::after propio. */
	const DEFAULT_TOUCH = ".elementor-social-icon\n.swiper-pagination-bullet";

	private static $probe      = null;
	private static $token      = '';
	private static $collecting = false;
	private static $styles     = array();
	private static $widgets    = array();
	private static $stored     = false;

	public static function init() {
		add_action( 'admin_post_digitalisimo_quality_audit', array( __CLASS__, 'run' ) );
		add_action( 'admin_post_digitalisimo_quality_complete', array( __CLASS__, 'complete' ) );
		add_action( 'admin_post_digitalisimo_quality_css_add', array( __CLASS__, 'css_add' ) );
		add_action( 'admin_post_digitalisimo_quality_image_role', array( __CLASS__, 'image_role' ) );
		add_action( 'wp_ajax_' . self::AJAX, array( __CLASS__, 'receive' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX, array( __CLASS__, 'receive' ) );
		add_action( 'template_redirect', array( __CLASS__, 'begin' ), 0 );
		add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'capture_widget' ) );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'capture_styles' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'touch_css' ), 22 );
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'attachment_attributes' ), 20, 2 );
		add_filter( 'wp_content_img_tag', array( __CLASS__, 'content_image' ), 20, 3 );
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'media_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( __CLASS__, 'media_save' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ *
	 * Ajustes
	 * ------------------------------------------------------------------ */

	/** Selectores simples: clases, ids, etiquetas y combinadores de descendencia. Nunca llaves ni HTML. */
	public static function sanitize_selectors( $value ) {
		$kept = array();
		foreach ( preg_split( '/\r\n|\r|\n|,/', (string) $value ) as $line ) {
			$line = trim( preg_replace( '/\s+/', ' ', $line ) );
			if ( '' === $line || strlen( $line ) > 120 || ! preg_match( '/^[A-Za-z0-9_.#> -]+$/', $line ) || ! preg_match( '/[A-Za-z]/', $line ) ) continue;
			$kept[ $line ] = true;
			if ( count( $kept ) >= 20 ) break;
		}
		return implode( "\n", array_keys( $kept ) );
	}

	/**
	 * Protección táctil opcional. Agranda el área que recibe el toque con un
	 * ::after centrado; el control conserva su tamaño visual y su posición.
	 * :where() deja especificidad cero: cualquier position del diseño gana.
	 */
	public static function touch_rules( $selectors ) {
		$list = array_filter( explode( "\n", self::sanitize_selectors( $selectors ) ) );
		if ( ! $list ) return '';
		$group = implode( ',', $list );
		return ':where(' . $group . '){position:relative}:where(' . $group . ')::after{content:"";position:absolute;left:50%;top:50%;width:max(100%,24px);height:max(100%,24px);transform:translate(-50%,-50%)}';
	}

	public static function touch_css() {
		if ( ! Digitalisimo_Integrations_Performance_Manager::frontend_safe() || ! Digitalisimo_Integrations_SEO_Resolver::option( 'perf_touch_guard' ) ) return;
		$css = self::touch_rules( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_touch_selectors' ) );
		if ( $css ) echo '<style id="digitalisimo-touch-targets">' . $css . '</style>' . "\n";
	}

	/** Versión de la configuración que afecta lo medido: cambia si cambia cualquiera de estos valores. */
	public static function config_version() {
		$values = array();
		foreach ( array( 'perf_img_enabled', 'perf_img_srcset', 'perf_img_sizes', 'perf_img_dimensions', 'perf_img_lazy', 'perf_img_mode', 'perf_img_exclusions', 'perf_css_defer', 'perf_css_defer_handles', 'perf_js_jquery_mode', 'perf_touch_guard', 'perf_touch_selectors', 'perf_font_guard_mode', 'seo_ai_llms_enabled', 'seo_ai_llms_mode' ) as $key ) $values[ $key ] = Digitalisimo_Integrations_SEO_Resolver::option( $key );
		$values['roles'] = self::roles();
		return substr( md5( DIGITALISIMO_INTEGRATIONS_VERSION . '|' . wp_json_encode( $values ) ), 0, 12 );
	}

	/* ------------------------------------------------------------------ *
	 * Imágenes decorativas (clasificación manual)
	 * ------------------------------------------------------------------ */

	public static function roles() {
		$roles = array();
		foreach ( (array) get_option( self::ROLES, array() ) as $id => $role ) if ( (int) $id > 0 && in_array( $role, array( 'informative', 'decorative' ), true ) ) $roles[ (int) $id ] = $role;
		return $roles;
	}

	public static function role( $id ) {
		$roles = self::roles();
		return $roles[ (int) $id ] ?? '';
	}

	public static function has_decorative() {
		return in_array( 'decorative', self::roles(), true );
	}

	public static function set_role( $id, $role ) {
		$roles = self::roles();
		$id    = (int) $id;
		if ( in_array( $role, array( 'informative', 'decorative' ), true ) ) $roles[ $id ] = $role;
		else unset( $roles[ $id ] );
		if ( count( $roles ) > 5000 ) $roles = array_slice( $roles, -5000, null, true );
		update_option( self::ROLES, $roles, true );
	}

	public static function attachment_attributes( $attr, $attachment ) {
		if ( is_object( $attachment ) && 'decorative' === self::role( $attachment->ID ) ) $attr['alt'] = '';
		return $attr;
	}

	public static function content_image( $tag, $context, $attachment_id ) {
		if ( ! $attachment_id || 'decorative' !== self::role( $attachment_id ) ) return $tag;
		return self::decorate( $tag );
	}

	/** alt="" en una etiqueta <img>, sin tocar ningún otro atributo. */
	public static function decorate( $tag ) {
		$tag = preg_replace( '/\salt\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>"\']+)/i', '', (string) $tag, 1 );
		return preg_replace( '~\s*(/?)>$~', ' alt=""$1>', $tag, 1 );
	}

	public static function media_field( $fields, $post ) {
		if ( ! wp_attachment_is_image( $post->ID ) ) return $fields;
		$role = self::role( $post->ID );
		$html = '<select name="attachments[' . (int) $post->ID . '][digitalisimo_role]">';
		foreach ( array( '' => 'Automático (usa su alt)', 'informative' => 'Informativa', 'decorative' => 'Decorativa · alt vacío' ) as $value => $label ) $html .= '<option value="' . esc_attr( $value ) . '" ' . selected( $role, $value, false ) . '>' . esc_html( $label ) . '</option>';
		$html .= '</select>';
		$fields['digitalisimo_role'] = array( 'label' => 'Rol de la imagen', 'input' => 'html', 'html' => $html, 'helps' => 'Decorativa publica alt="" en la web sin borrar el texto alternativo guardado.' );
		return $fields;
	}

	public static function media_save( $post, $attachment ) {
		if ( isset( $attachment['digitalisimo_role'] ) && ! empty( $post['ID'] ) && current_user_can( 'edit_post', (int) $post['ID'] ) ) self::set_role( (int) $post['ID'], sanitize_key( $attachment['digitalisimo_role'] ) );
		return $post;
	}

	/* ------------------------------------------------------------------ *
	 * Captura
	 * ------------------------------------------------------------------ */

	public static function run() {
		$network = ! empty( $_POST['network_context'] );
		if ( $network ? ! is_multisite() || ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		check_admin_referer( 'digitalisimo_quality_audit' );
		$url     = esc_url_raw( trim( (string) wp_unslash( $_POST['url'] ?? '' ) ) );
		$site_id = Digitalisimo_Integrations_Asset_Diagnostics::site_for_url( $url, $network );
		if ( ! $site_id ) {
			self::notice( 'La URL debe ser una página pública de este sitio' . ( $network ? ' o de un sitio de la red' : '' ) . '.' );
			wp_safe_redirect( self::section_url( $network, 'audit', $network ? absint( $_POST['site_id'] ?? 0 ) : 0 ) );
			exit;
		}
		$token = strtolower( wp_generate_password( 32, false, false ) );
		set_site_transient( 'digitalisimo_quality_probe_' . $token, array(
			'site_id' => $site_id, 'user_id' => get_current_user_id(), 'network' => $network, 'url' => $url,
			'complete_url' => add_query_arg( array( 'action' => 'digitalisimo_quality_complete', 'token' => $token ), admin_url( 'admin-post.php' ) ),
		), self::TTL );
		// La URL ya pertenece al sitio elegido; el navegador del administrador hace la medición.
		wp_redirect( add_query_arg( self::QUERY, $token, $url ) );
		exit;
	}

	private static function probe( $token ) {
		$probe = $token ? get_site_transient( 'digitalisimo_quality_probe_' . $token ) : false;
		return is_array( $probe ) && (int) ( $probe['site_id'] ?? 0 ) === get_current_blog_id() && empty( $probe['done'] ) ? $probe : null;
	}

	/** La página se pinta como la ve un visitante: sin sesión ni barra de administración. */
	public static function begin() {
		$token = sanitize_key( $_GET[ self::QUERY ] ?? '' );
		if ( ! $token || is_admin() || is_feed() || is_preview() ) return;
		$probe = self::probe( $token );
		if ( ! $probe ) return;
		self::$probe = $probe;
		self::$token = $token;
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow' );
			header( 'Referrer-Policy: no-referrer' );
		}
		if ( function_exists( 'wp_set_current_user' ) ) wp_set_current_user( 0 );
		add_filter( 'show_admin_bar', '__return_false', PHP_INT_MAX );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'drop_admin_bar' ), PHP_INT_MAX );
		if ( ! empty( $_GET[ self::FRAME ] ) ) return; // La vista móvil sólo se pinta; la mide la página principal.
		self::$collecting = true;
		ob_start( array( __CLASS__, 'inject' ) );
	}

	/** La barra ya se inicializó antes de la captura: se retiran sus recursos. */
	public static function drop_admin_bar() {
		if ( ! self::$probe ) return;
		wp_dequeue_script( 'admin-bar' );
		wp_dequeue_style( 'admin-bar' );
		remove_action( 'wp_body_open', 'wp_admin_bar_render', 0 );
		remove_action( 'wp_footer', 'wp_admin_bar_render', 1000 );
	}

	public static function capture_widget( $widget ) {
		if ( ! self::$collecting || ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) return;
		$styles = method_exists( $widget, 'get_style_depends' ) ? (array) $widget->get_style_depends() : array();
		self::$widgets[ (string) $widget->get_name() ] = array_values( array_map( 'strval', $styles ) );
	}

	public static function capture_styles() {
		if ( ! self::$collecting ) return;
		$registry = wp_styles();
		foreach ( (array) $registry->done as $handle ) {
			$asset = $registry->registered[ $handle ] ?? null;
			if ( $asset && is_string( $asset->src ) && '' !== $asset->src ) self::$styles[ $handle ] = $asset->src;
		}
	}

	/** Widget de Elementor dueño de una hoja: por su handle o por las dependencias declaradas. */
	public static function style_widget( $handle, $widgets ) {
		$canonical = Digitalisimo_Integrations_Performance_CSS::canonical_handle( $handle );
		if ( preg_match( '/^widget-([a-z0-9-]+)$/', $canonical, $m ) ) return $m[1];
		foreach ( (array) $widgets as $name => $styles ) if ( in_array( $handle, (array) $styles, true ) ) return (string) $name;
		return '';
	}

	/** El HTML final (ya procesado por imágenes y JavaScript) recibe el colector antes de </body>. */
	public static function inject( $html, $phase = 0 ) {
		if ( ! self::$collecting || ! is_string( $html ) ) return $html;
		$position = strripos( $html, '</body>' );
		if ( false === $position ) return $html;
		try {
			$widgets = array();
			foreach ( array_keys( self::$styles ) as $handle ) {
				$name = self::style_widget( $handle, self::$widgets );
				if ( '' !== $name ) $widgets[ $name ] = true;
			}
			if ( ! self::$stored ) {
				self::$stored = true;
				set_site_transient( 'digitalisimo_quality_server_' . self::$token, array(
					'styles' => self::$styles, 'widgets' => self::$widgets,
					'image_report' => apply_filters( 'digitalisimo_performance_probe_image_report', array() ),
				), self::TTL );
			}
			$config = array(
				'endpoint' => admin_url( 'admin-ajax.php' ), 'action' => self::AJAX, 'token' => self::$token,
				'complete' => self::$probe['complete_url'], 'widgets' => array_keys( $widgets ),
				'frame' => add_query_arg( array( self::QUERY => self::$token, self::FRAME => 1 ), self::$probe['url'] ),
			);
			$tag = '<script id="digitalisimo-quality-audit" src="' . esc_url( DIGITALISIMO_INTEGRATIONS_URL . 'assets/quality-audit.js?ver=' . DIGITALISIMO_INTEGRATIONS_VERSION ) . '" data-config="' . esc_attr( base64_encode( wp_json_encode( $config ) ) ) . '"></script>';
			return substr_replace( $html, $tag, $position, 0 );
		} catch ( Throwable $error ) {
			return $html;
		}
	}

	/** Recibe la medición del navegador. El token de un solo uso es la autorización. */
	public static function receive() {
		$token = sanitize_key( $_POST['token'] ?? '' );
		$probe = self::probe( $token );
		if ( ! $probe ) wp_send_json_error( array( 'message' => 'La auditoría caducó.' ), 403 );
		$raw = (string) wp_unslash( $_POST['payload'] ?? '' );
		if ( strlen( $raw ) > 3 * MB_IN_BYTES ) wp_send_json_error( array( 'message' => 'Medición demasiado grande.' ), 413 );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) wp_send_json_error( array( 'message' => 'Medición inválida.' ), 400 );
		$server = get_site_transient( 'digitalisimo_quality_server_' . $token );
		self::save( self::build( $probe['url'], self::sanitize_payload( $data ), is_array( $server ) ? $server : array() ) );
		$probe['done'] = 1;
		set_site_transient( 'digitalisimo_quality_probe_' . $token, $probe, self::TTL );
		delete_site_transient( 'digitalisimo_quality_server_' . $token );
		wp_send_json_success();
	}

	public static function complete() {
		$token = sanitize_key( $_GET['token'] ?? '' );
		$probe = $token ? get_site_transient( 'digitalisimo_quality_probe_' . $token ) : false;
		if ( ! is_array( $probe ) || (int) ( $probe['user_id'] ?? 0 ) !== get_current_user_id() ) wp_die( 'La auditoría caducó o pertenece a otro usuario.' );
		$network = ! empty( $probe['network'] );
		if ( $network ? ! is_multisite() || ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		if ( empty( $probe['done'] ) ) self::notice( 'El navegador no envió la medición. Vuelve a intentarlo y espera a que la página regrese sola a esta pantalla.' );
		delete_site_transient( 'digitalisimo_quality_probe_' . $token );
		wp_safe_redirect( self::section_url( $network, 'audit', (int) $probe['site_id'], (string) $probe['url'] ) );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Saneado y evaluación
	 * ------------------------------------------------------------------ */

	private static function str( $value, $max ) {
		if ( ! is_scalar( $value ) ) return '';
		$value = function_exists( 'wp_check_invalid_utf8' ) ? wp_check_invalid_utf8( (string) $value ) : (string) $value;
		return Digitalisimo_Integrations_Quality_Rules::text( preg_replace( '/[\x00-\x1F\x7F]/', ' ', $value ), $max );
	}

	private static function cast( $value, $type ) {
		if ( 'bool' === $type ) return ! empty( $value ) && 'false' !== $value;
		if ( 'nullbool' === $type ) return null === $value ? null : ( ! empty( $value ) && 'false' !== $value );
		if ( 'int' === $type ) return (int) $value;
		if ( 'float' === $type ) return is_numeric( $value ) ? round( (float) $value, 2 ) : 0.0;
		if ( 'unit' === $type ) return is_numeric( $value ) ? max( 0.0, min( 1.0, round( (float) $value, 2 ) ) ) : 1.0; // Opacidad: sin dato, opaco.
		if ( 'pair' === $type ) { $value = (array) $value; return array( is_numeric( $value[0] ?? null ) ? round( (float) $value[0], 1 ) : 0.0, is_numeric( $value[1] ?? null ) ? round( (float) $value[1], 1 ) : 0.0 ); }
		if ( is_string( $type ) && 0 === strpos( $type, 'null:' ) ) return null === $value ? null : self::str( $value, (int) substr( $type, 5 ) );
		return self::str( $value, (int) $type );
	}

	private static function rows( $list, $max, $schema ) {
		$out = array();
		foreach ( array_slice( is_array( $list ) ? $list : array(), 0, $max ) as $item ) {
			if ( ! is_array( $item ) ) continue;
			$row = array();
			foreach ( $schema as $key => $type ) $row[ $key ] = self::cast( $item[ $key ] ?? null, $type );
			$out[] = $row;
		}
		return $out;
	}

	private static function view( $raw, $full ) {
		if ( ! is_array( $raw ) ) return array();
		if ( ! empty( $raw['error'] ) ) return array( 'error' => self::str( $raw['error'], 200 ) );
		$viewport = (array) ( $raw['viewport'] ?? array() );
		$view = array(
			'viewport' => array( 'w' => (int) ( $viewport['w'] ?? 0 ), 'h' => (int) ( $viewport['h'] ?? 0 ), 'dpr' => max( 0.5, min( 5.0, (float) ( $viewport['dpr'] ?? 1 ) ) ) ),
			'headings' => self::rows( $raw['headings'] ?? array(), 300, array( 'level' => 'int', 'text' => 300, 'selector' => 200, 'widget' => 60, 'visible' => 'bool', 'image' => 'bool' ) ),
			'images'   => self::rows( $raw['images'] ?? array(), 300, array( 'src' => 500, 'cls' => 300, 'natural' => 'pair', 'rect' => 'pair', 'fit' => 20, 'srcset' => 1000, 'sizes' => 300, 'width' => 10, 'height' => 10, 'loading' => 10, 'fetchpriority' => 10, 'decoding' => 10, 'visible' => 'bool', 'selector' => 200, 'widget' => 60, 'alt' => 'null:300', 'role' => 20, 'aria_hidden' => 'bool', 'caption' => 300, 'link_text' => 300 ) ),
			'targets'  => self::rows( $raw['targets'] ?? array(), 500, array( 'x' => 'float', 'y' => 'float', 'w' => 'float', 'h' => 'float', 'inline' => 'bool', 'name' => 80, 'selector' => 200, 'widget' => 60 ) ),
			'css'      => array(),
		);
		foreach ( array_slice( (array) ( $raw['css'] ?? array() ), 0, 150, true ) as $name => $state ) {
			if ( ! preg_match( '/^[a-z0-9-]{1,80}$/', (string) $name ) || ! is_array( $state ) ) continue;
			$view['css'][ $name ] = array( 'present' => ! empty( $state['present'] ), 'above' => ! empty( $state['above'] ), 'count' => (int) ( $state['count'] ?? 0 ) );
		}
		if ( $full ) {
			$view['links'] = self::rows( $raw['links'] ?? array(), 600, array( 'href' => 'null:500', 'resolved' => 500, 'name' => 200, 'icon' => 'bool', 'selector' => 200, 'widget' => 60, 'visible' => 'bool', 'submenu' => 'bool', 'social' => 'bool', 'button' => 'bool', 'target_exists' => 'nullbool' ) );
			$view['texts'] = self::rows( $raw['texts'] ?? array(), 600, array( 'text' => 80, 'fg' => 60, 'bg' => 'null:60', 'opacity' => 'unit', 'size' => 'float', 'weight' => 'int', 'selector' => 200, 'widget' => 60 ) );
		}
		return $view;
	}

	public static function sanitize_payload( $data ) {
		return array(
			'desktop' => self::view( $data['desktop'] ?? null, true ),
			'mobile'  => self::view( $data['mobile'] ?? null, false ),
			'error'   => self::str( $data['error'] ?? '', 200 ),
		);
	}

	/** Filas de imágenes medidas: tamaño descargado frente a pintado, y dimensiones declaradas. */
	private static function image_rows( $images, $viewport, $dpr ) {
		$rows = array();
		foreach ( (array) $images as $image ) {
			if ( empty( $image['visible'] ) || empty( $image['natural'][0] ) ) continue;
			$verdict = Digitalisimo_Integrations_Quality_Rules::image_issue( $image, $dpr );
			if ( ! $verdict ) continue;
			if ( Digitalisimo_Integrations_Quality_Rules::OK === $verdict['status'] && ( '' === $image['width'] || '' === $image['height'] ) ) $verdict = array( 'status' => Digitalisimo_Integrations_Quality_Rules::WARNING, 'issue' => 'Sin width/height: el navegador no reserva su espacio antes de cargarla.' ) + $verdict;
			$rows[] = array(
				'viewport' => $viewport, 'status' => $verdict['status'], 'issue' => $verdict['issue'], 'src' => $image['src'],
				'natural' => (int) $image['natural'][0] . ' × ' . (int) $image['natural'][1], 'rect' => round( $image['rect'][0] ) . ' × ' . round( $image['rect'][1] ),
				'dpr' => $dpr, 'needed' => $verdict['needed'], 'candidate' => $verdict['candidate'], 'sizes' => $image['sizes'], 'srcset' => $image['srcset'],
				'width' => $image['width'], 'height' => $image['height'], 'loading' => $image['loading'], 'fetchpriority' => $image['fetchpriority'], 'decoding' => $image['decoding'],
				'selector' => $image['selector'], 'widget' => $image['widget'],
			);
		}
		return $rows;
	}

	private static function css_rows( $server, $desktop, $mobile ) {
		$selected = array_filter( explode( "\n", Digitalisimo_Integrations_Performance_CSS::sanitize_handles( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_css_defer_handles' ) ) ) );
		$enabled  = (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'perf_css_defer' );
		$rows = array();
		foreach ( (array) ( $server['styles'] ?? array() ) as $handle => $src ) {
			$widget    = self::style_widget( (string) $handle, $server['widgets'] ?? array() );
			$canonical = Digitalisimo_Integrations_Performance_CSS::canonical_handle( $handle );
			$d = '' !== $widget && isset( $desktop['css'][ $widget ] ) ? $desktop['css'][ $widget ] : null;
			$m = '' !== $widget && isset( $mobile['css'][ $widget ] ) ? $mobile['css'][ $widget ] : null;
			$row = array(
				'handle' => (string) $handle, 'src' => (string) $src, 'widget' => $widget,
				'present' => ( $d && $d['present'] ) || ( $m && $m['present'] ),
				'above_desktop' => $d ? $d['above'] : null, 'above_mobile' => $m ? $m['above'] : null,
				'eligible' => Digitalisimo_Integrations_Performance_CSS::eligible( $handle, $src ), 'selected' => in_array( $canonical, $selected, true ), 'enabled' => $enabled,
			);
			$rows[] = $row + Digitalisimo_Integrations_Quality_Rules::css_verdict( $row );
		}
		return $rows;
	}

	/** Une la medición y la captura del servidor en un registro evaluado. */
	public static function build( $url, $payload, $server ) {
		$desktop = (array) ( $payload['desktop'] ?? array() );
		$mobile  = (array) ( $payload['mobile'] ?? array() );
		$has_mobile = ! empty( $mobile ) && empty( $mobile['error'] );
		$roles = self::roles();
		foreach ( $desktop['images'] ?? array() as $i => $image ) {
			$id = Digitalisimo_Integrations_Performance_Images::resolve_attachment( array( 'src' => $image['src'], 'class' => $image['cls'] ) );
			$desktop['images'][ $i ]['attachment_id']  = $id;
			$desktop['images'][ $i ]['classification'] = $roles[ $id ] ?? '';
		}
		$contrast   = Digitalisimo_Integrations_Quality_Rules::contrast_issues( $desktop['texts'] ?? array() );
		$touch_view = $has_mobile ? 'Móvil 390 px' : 'Escritorio';
		$touch      = Digitalisimo_Integrations_Quality_Rules::touch_issues( $has_mobile ? $mobile['targets'] : ( $desktop['targets'] ?? array() ) );
		foreach ( $touch as &$row ) $row['viewport'] = $touch_view;
		unset( $row );
		$desktop_dpr = (float) ( $desktop['viewport']['dpr'] ?? 1 );
		$findings = array(
			'links'    => Digitalisimo_Integrations_Quality_Rules::link_issues( $desktop['links'] ?? array() ),
			'names'    => Digitalisimo_Integrations_Quality_Rules::name_issues( $desktop['links'] ?? array() ),
			'headings' => Digitalisimo_Integrations_Quality_Rules::heading_issues( $desktop['headings'] ?? array() ),
			'headings_mobile' => $has_mobile ? Digitalisimo_Integrations_Quality_Rules::heading_issues( $mobile['headings'] ) : array(),
			'alt'      => Digitalisimo_Integrations_Quality_Rules::alt_issues( $desktop['images'] ?? array() ),
			'touch'    => $touch,
			'contrast' => $contrast['rows'],
			// En móvil se supone DPR 3: sólo se advierte lo que sobra incluso para esas pantallas.
			'images'   => array_merge( self::image_rows( $desktop['images'] ?? array(), 'Escritorio', $desktop_dpr ), $has_mobile ? self::image_rows( $mobile['images'], 'Móvil 390 px', max( 3.0, (float) $mobile['viewport']['dpr'] ) ) : array() ),
			'css'      => self::css_rows( $server, $desktop, $has_mobile ? $mobile : array() ),
		);
		return array(
			'url' => $url, 'time' => time(), 'blog_id' => get_current_blog_id(), 'config' => self::config_version(),
			'measured' => ! empty( $desktop ) && empty( $desktop['error'] ),
			'error' => (string) ( $payload['error'] ?? '' ),
			'viewport' => $desktop['viewport'] ?? array(), 'mobile' => array( 'ok' => $has_mobile, 'error' => (string) ( $mobile['error'] ?? ( $mobile ? '' : 'Sin medición móvil.' ) ), 'viewport' => $mobile['viewport'] ?? array() ),
			'findings' => $findings,
			'tree' => array( 'desktop' => Digitalisimo_Integrations_Quality_Rules::heading_tree( $desktop['headings'] ?? array() ), 'mobile' => $has_mobile ? Digitalisimo_Integrations_Quality_Rules::heading_tree( $mobile['headings'] ) : array() ),
			'totals' => array( 'links' => count( $desktop['links'] ?? array() ), 'headings' => count( $desktop['headings'] ?? array() ), 'images' => count( $desktop['images'] ?? array() ), 'targets' => count( $has_mobile ? $mobile['targets'] : ( $desktop['targets'] ?? array() ) ), 'contrast_checked' => $contrast['checked'], 'contrast_unknown' => $contrast['unknown'] ),
			'image_report' => array_slice( (array) ( $server['image_report'] ?? array() ), 0, 300 ),
		);
	}

	/* ------------------------------------------------------------------ *
	 * Almacenamiento por sitio y URL
	 * ------------------------------------------------------------------ */

	public static function index() {
		$index = array();
		foreach ( (array) get_option( self::INDEX, array() ) as $hash => $item ) if ( is_array( $item ) && ! empty( $item['url'] ) ) $index[ $hash ] = array( 'url' => (string) $item['url'], 'time' => (int) ( $item['time'] ?? 0 ) );
		uasort( $index, function( $a, $b ) { return $b['time'] <=> $a['time']; } );
		return $index;
	}

	public static function save( $record ) {
		$hash = md5( $record['url'] );
		update_option( self::RECORD . $hash, $record, false );
		$index = self::index();
		$index[ $hash ] = array( 'url' => $record['url'], 'time' => $record['time'] );
		uasort( $index, function( $a, $b ) { return $b['time'] <=> $a['time']; } );
		foreach ( array_slice( array_keys( $index ), self::KEEP ) as $old ) { delete_option( self::RECORD . $old ); unset( $index[ $old ] ); }
		update_option( self::INDEX, $index, false );
		Digitalisimo_Integrations_Performance_Cache::delete( 'quality_' . $hash );
	}

	public static function get( $url ) {
		$hash   = md5( (string) $url );
		$found  = false;
		$cached = Digitalisimo_Integrations_Performance_Cache::get( 'quality_' . $hash, $found );
		if ( $found && is_array( $cached ) ) return $cached ?: null;
		$record = get_option( self::RECORD . $hash, array() );
		$record = is_array( $record ) && ! empty( $record['url'] ) ? $record : array();
		Digitalisimo_Integrations_Performance_Cache::set( 'quality_' . $hash, $record, 3600 );
		return $record ?: null;
	}

	/* ------------------------------------------------------------------ *
	 * Acciones del administrador
	 * ------------------------------------------------------------------ */

	private static function action_context() {
		$network = ! empty( $_POST['network_context'] );
		if ( $network ? ! is_multisite() || ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		$site_id = absint( $_POST['site_id'] ?? 0 );
		if ( ! $site_id || ( $network ? ! get_site( $site_id ) : $site_id !== get_current_blog_id() ) ) wp_die( 'Sitio inválido.' );
		return array( $network, $site_id, esc_url_raw( (string) wp_unslash( $_POST['url'] ?? '' ) ) );
	}

	/** Añade un handle a la lista diferible de ESTE sitio; la lista de red no cambia. */
	public static function css_add() {
		list( $network, $site_id, $url ) = self::action_context();
		check_admin_referer( 'digitalisimo_quality_css_' . $site_id );
		$handle = Digitalisimo_Integrations_Performance_CSS::canonical_handle( sanitize_key( $_POST['handle'] ?? '' ) );
		self::in_site( $site_id, function() use ( $handle ) {
			$next = Digitalisimo_Integrations_Performance_CSS::sanitize_handles( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_css_defer_handles' ) . "\n" . $handle );
			if ( ! in_array( $handle, explode( "\n", $next ), true ) ) { self::notice( 'El handle ' . $handle . ' no es apto para diferir o la lista ya tiene 20 entradas.' ); return; }
			$site = (array) get_option( Digitalisimo_Integrations_Settings::OPTION, array() );
			$site['perf_css_defer_handles'] = $next;
			update_option( Digitalisimo_Integrations_Settings::OPTION, $site );
			if ( is_multisite() ) {
				$inherit = (array) get_option( 'digitalisimo_seo_network_inherit', array() );
				$inherit['perf_css_defer_handles'] = 0;
				update_option( 'digitalisimo_seo_network_inherit', $inherit, false );
			}
			self::notice( $handle . ' se añadió a la lista diferible de este sitio. Se aplica cuando «Diferir CSS de widgets» está activo; vuelve a auditar para comprobarlo.' );
		} );
		wp_safe_redirect( self::section_url( $network, 'css', $site_id, $url ) );
		exit;
	}

	public static function image_role() {
		list( $network, $site_id, $url ) = self::action_context();
		check_admin_referer( 'digitalisimo_quality_role_' . $site_id );
		$id   = absint( $_POST['attachment_id'] ?? 0 );
		$role = sanitize_key( $_POST['role'] ?? '' );
		self::in_site( $site_id, function() use ( $id, $role ) {
			if ( ! $id || 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) { self::notice( 'Adjunto no válido.' ); return; }
			self::set_role( $id, $role );
			self::notice( 'Imagen #' . $id . ': ' . ( 'decorative' === $role ? 'decorativa, se publica con alt="".' : ( 'informative' === $role ? 'informativa, conserva su alt.' : 'clasificación automática.' ) ) . ' Si usas caché de página, púrgala.' );
		} );
		wp_safe_redirect( self::section_url( $network, 'a11y', $site_id, $url ) );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Administración
	 * ------------------------------------------------------------------ */

	private static function notice( $message ) {
		set_transient( 'digitalisimo_quality_notice_' . get_current_user_id(), $message, 10 * MINUTE_IN_SECONDS );
	}

	private static function show_notice() {
		$message = get_transient( 'digitalisimo_quality_notice_' . get_current_user_id() );
		if ( ! $message ) return;
		delete_transient( 'digitalisimo_quality_notice_' . get_current_user_id() );
		echo '<div class="notice notice-info inline"><p>' . esc_html( $message ) . '</p></div>';
	}

	public static function section_url( $network, $section, $site_id = 0, $url = '' ) {
		$target = $network ? network_admin_url( 'admin.php?page=digitalisimo-network-performance&section=' . $section ) : admin_url( 'admin.php?page=digitalisimo-performance&section=' . $section );
		if ( $network && $site_id ) $target = add_query_arg( 'site_id', (int) $site_id, $target );
		return $url ? add_query_arg( 'performance_url', rawurlencode( $url ), $target ) : $target;
	}

	private static function in_site( $site_id, $callback ) {
		$switch = is_multisite() && (int) $site_id !== get_current_blog_id();
		if ( $switch ) switch_to_blog( (int) $site_id );
		try { return $callback(); }
		finally { if ( $switch ) restore_current_blog(); }
	}

	private static function context( $network ) {
		$site_id = $network ? absint( $_GET['site_id'] ?? get_current_blog_id() ) : get_current_blog_id();
		if ( $network && ! get_site( $site_id ) ) $site_id = get_current_blog_id();
		$url = esc_url_raw( (string) wp_unslash( $_GET['performance_url'] ?? '' ) );
		if ( $url && Digitalisimo_Integrations_Asset_Diagnostics::site_for_url( $url, $network ) !== $site_id ) $url = '';
		$index = self::in_site( $site_id, array( __CLASS__, 'index' ) );
		if ( ! $url && $index ) $url = reset( $index )['url'];
		if ( ! $url ) $url = get_home_url( $site_id, '/' );
		$record = self::in_site( $site_id, function() use ( $url ) { return self::get( $url ); } );
		$stale  = $record && self::in_site( $site_id, array( __CLASS__, 'config_version' ) ) !== ( $record['config'] ?? '' );
		return array( 'network' => $network, 'site_id' => $site_id, 'url' => $url, 'index' => $index, 'record' => $record, 'stale' => $stale );
	}

	public static function badge( $status ) {
		$colors = array( 'OK' => '#00a32a', 'ADVERTENCIA' => '#dba617', 'ERROR' => '#d63638', 'NO APLICABLE' => '#787c82' );
		return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap;background:' . esc_attr( $colors[ $status ] ?? '#787c82' ) . '">' . esc_html( $status ) . '</span>';
	}

	private static function header( $ctx, $title, $description ) {
		self::show_notice();
		echo '<h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $description ) . '</p>';
		if ( ! $ctx['record'] ) { echo '<p><em>Sin auditoría para <a href="' . esc_url( $ctx['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $ctx['url'] ) . '</a>. Ejecútala en la pestaña <a href="' . esc_url( self::section_url( $ctx['network'], 'audit', $ctx['site_id'], $ctx['url'] ) ) . '">Auditoría</a>.</em></p>'; return false; }
		$record = $ctx['record'];
		echo '<p>Página: <a href="' . esc_url( $record['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $record['url'] ) . '</a> · Auditada: ' . esc_html( date_i18n( 'Y-m-d H:i', (int) $record['time'] ) ) . ' · Escritorio ' . (int) ( $record['viewport']['w'] ?? 0 ) . ' px, DPR ' . esc_html( (string) ( $record['viewport']['dpr'] ?? 1 ) ) . '</p>';
		if ( $ctx['stale'] ) echo '<div class="notice notice-warning inline"><p>La configuración cambió después de esta auditoría: vuelve a ejecutarla para ver el estado actual.</p></div>';
		if ( ! empty( $record['error'] ) ) echo '<div class="notice notice-warning inline"><p>' . esc_html( $record['error'] ) . '</p></div>';
		return true;
	}

	private static function table( $columns, $rows, $empty ) {
		if ( ! $rows ) { echo '<p>' . self::badge( 'OK' ) . ' ' . esc_html( $empty ) . '</p>'; return; }
		echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr>';
		foreach ( $columns as $label ) echo '<th>' . esc_html( $label ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( array_keys( $columns ) as $key ) {
				$value = $row[ $key ] ?? '';
				if ( 'status' === $key ) echo '<td>' . self::badge( (string) $value ) . '</td>';
				elseif ( in_array( $key, array( 'selector', 'href', 'src', 'sizes' ), true ) ) echo '<td><code style="word-break:break-all">' . esc_html( '' === (string) $value ? '—' : (string) $value ) . '</code></td>';
				else echo '<td>' . esc_html( is_bool( $value ) ? ( $value ? 'Sí' : 'No' ) : ( null === $value || '' === $value ? '—' : (string) $value ) ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function filter( $rows ) {
		return array_values( array_filter( (array) $rows, function( $row ) { return in_array( $row['status'] ?? '', array( Digitalisimo_Integrations_Quality_Rules::ERROR, Digitalisimo_Integrations_Quality_Rules::WARNING ), true ); } ) );
	}

	/** Rendimiento y calidad → Auditoría: lanzar, elegir URL y resumen. */
	public static function render_audit( $network ) {
		$ctx = self::context( $network );
		self::show_notice();
		echo '<h2>Auditoría frontend</h2><p>Abre la URL en tu navegador como la ve un visitante sin sesión, la recorre para que carguen las imágenes diferidas y la mide en escritorio y en una vista móvil de 390 px. Tarda entre 15 y 40 segundos y vuelve sola a esta pantalla. Sólo informa: no cambia colores, encabezados, textos ni destinos.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'digitalisimo_quality_audit' );
		echo '<input type="hidden" name="action" value="digitalisimo_quality_audit"><input type="hidden" name="network_context" value="' . ( $network ? '1' : '0' ) . '"><input type="hidden" name="site_id" value="' . (int) $ctx['site_id'] . '">';
		echo '<label for="digitalisimo_quality_url">URL pública a auditar</label> <input class="regular-text" type="url" id="digitalisimo_quality_url" name="url" value="' . esc_attr( $ctx['url'] ) . '" required> ';
		submit_button( 'Auditar URL', 'primary', 'submit', false );
		echo '</form>';
		if ( $ctx['index'] ) {
			echo '<p>Auditorías recientes de este sitio: ';
			$links = array();
			foreach ( $ctx['index'] as $item ) $links[] = '<a href="' . esc_url( self::section_url( $network, 'audit', $ctx['site_id'], $item['url'] ) ) . '"' . ( $item['url'] === $ctx['url'] ? ' style="font-weight:600"' : '' ) . '>' . esc_html( wp_parse_url( $item['url'], PHP_URL_PATH ) ?: '/' ) . '</a>';
			echo implode( ' · ', $links ) . '</p>';
		}
		$record = $ctx['record'];
		if ( ! $record ) { echo '<p><em>Sin auditoría para esta URL todavía.</em></p>'; return; }
		echo '<p>Página: <a href="' . esc_url( $record['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $record['url'] ) . '</a> · ' . esc_html( date_i18n( 'Y-m-d H:i', (int) $record['time'] ) ) . '</p>';
		if ( $ctx['stale'] ) echo '<div class="notice notice-warning inline"><p>La configuración cambió después de esta auditoría: vuelve a ejecutarla para ver el estado actual.</p></div>';
		if ( ! empty( $record['error'] ) ) echo '<div class="notice notice-warning inline"><p>' . esc_html( $record['error'] ) . '</p></div>';
		if ( empty( $record['mobile']['ok'] ) ) echo '<div class="notice notice-warning inline"><p>Vista móvil: ' . esc_html( $record['mobile']['error'] ?: 'sin medición.' ) . ' Los objetivos táctiles se midieron en escritorio.</p></div>';
		$findings = (array) $record['findings'];
		$findings['headings'] = array_merge( (array) $findings['headings'], self::filter( $findings['headings_mobile'] ?? array() ) );
		$findings['measured'] = ! empty( $record['measured'] );
		$llms    = self::in_site( $ctx['site_id'], array( 'Digitalisimo_Integrations_LLMS', 'status' ) );
		$summary = Digitalisimo_Integrations_Quality_Rules::summarize( $findings, $llms );
		$targets = array( 'performance' => 'images', 'a11y' => 'a11y', 'seo' => 'seo', 'images' => 'images', 'llms' => 'agents', 'links' => 'seo', 'headings' => 'seo' );
		echo '<h3>Resumen del sitio</h3><p class="description">Problemas verificables que detecta el plugin; no reproduce la puntuación de Lighthouse.</p><table class="widefat striped" style="max-width:900px"><thead><tr><th>Área</th><th>Estado</th><th>Errores</th><th>Advertencias</th><th>Qué revisa</th></tr></thead><tbody>';
		foreach ( $summary as $key => $row ) echo '<tr><td><a href="' . esc_url( self::section_url( $network, $targets[ $key ], $ctx['site_id'], $ctx['url'] ) ) . '">' . esc_html( $row['label'] ) . '</a></td><td>' . self::badge( $row['status'] ) . '</td><td>' . (int) $row['errors'] . '</td><td>' . (int) $row['warnings'] . '</td><td>' . esc_html( $row['note'] ) . '</td></tr>';
		echo '</tbody></table>';
		$totals = (array) ( $record['totals'] ?? array() );
		echo '<p class="description">Analizados: ' . (int) ( $totals['links'] ?? 0 ) . ' enlaces, ' . (int) ( $totals['headings'] ?? 0 ) . ' encabezados, ' . (int) ( $totals['images'] ?? 0 ) . ' imágenes, ' . (int) ( $totals['targets'] ?? 0 ) . ' controles y ' . (int) ( $totals['contrast_checked'] ?? 0 ) . ' combinaciones de color.</p>';
	}

	/** Rendimiento y calidad → Accesibilidad. */
	public static function render_a11y( $network ) {
		$ctx = self::context( $network );
		if ( ! self::header( $ctx, 'Accesibilidad', 'Nombres de enlaces, texto alternativo, objetivos táctiles y contraste. Nada se corrige solo: sólo las imágenes que clasifiques como decorativas se publican con alt="".' ) ) return;
		$f = (array) $ctx['record']['findings'];
		echo '<h3>Nombres accesibles de enlaces</h3><p class="description">Muestra el nombre que oye un lector de pantalla y su destino, para corregirlo en el widget. No se sobrescribe ningún texto.</p>';
		self::table( array( 'status' => 'Estado', 'issue' => 'Problema', 'name' => 'Nombre accesible', 'href' => 'Destino', 'selector' => 'Selector', 'widget' => 'Widget' ), $f['names'] ?? array(), 'Todos los enlaces visibles tienen un nombre accesible coherente.' );

		echo '<h3>Texto alternativo</h3><p class="description">Clasifica una imagen como decorativa para publicarla con alt="" (el alt guardado en la Biblioteca no se borra). También puedes hacerlo desde la Biblioteca de Medios.</p>';
		$rows = (array) ( $f['alt'] ?? array() );
		if ( ! $rows ) echo '<p>' . self::badge( 'OK' ) . ' Sin problemas de alt en las imágenes visibles.</p>';
		else {
			echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Estado</th><th>Problema</th><th>Imagen</th><th>alt publicado</th><th>Adjunto</th><th>Clasificación</th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				echo '<tr><td>' . self::badge( $row['status'] ) . '</td><td>' . esc_html( $row['issue'] ) . '</td><td><code style="word-break:break-all">' . esc_html( $row['src'] ) . '</code><br><small>' . esc_html( $row['selector'] ) . '</small></td><td>' . esc_html( null === $row['alt'] ? '(sin atributo)' : ( '' === $row['alt'] ? '(vacío)' : $row['alt'] ) ) . '</td><td>' . ( $row['attachment_id'] ? '#' . (int) $row['attachment_id'] : '—' ) . '</td><td>';
				if ( $row['attachment_id'] ) self::role_form( $ctx, (int) $row['attachment_id'], self::in_site( $ctx['site_id'], function() use ( $row ) { return self::role( $row['attachment_id'] ); } ) );
				else echo 'Sin adjunto de este sitio';
				echo '</td></tr>';
			}
			echo '</tbody></table></div>';
		}

		echo '<h3>Objetivos táctiles</h3><p class="description">WCAG 2.5.8: 24 × 24 px como mínimo, o separación suficiente. Los enlaces dentro de un párrafo están exentos. Medido en: ' . esc_html( $ctx['record']['findings']['touch'][0]['viewport'] ?? ( ! empty( $ctx['record']['mobile']['ok'] ) ? 'Móvil 390 px' : 'Escritorio' ) ) . '.</p>';
		self::table( array( 'status' => 'Estado', 'issue' => 'Problema', 'name' => 'Control', 'size' => 'Tamaño', 'gap' => 'Distancia al más cercano (px)', 'selector' => 'Selector', 'widget' => 'Widget' ), $f['touch'] ?? array(), 'Todos los controles alcanzan el tamaño o la separación mínima.' );

		$totals = (array) ( $ctx['record']['totals'] ?? array() );
		echo '<h3>Contraste</h3><p class="description">Colores computados por el navegador, incluida la opacidad. ' . (int) ( $totals['contrast_checked'] ?? 0 ) . ' combinaciones revisadas; ' . (int) ( $totals['contrast_unknown'] ?? 0 ) . ' textos sobre imagen, degradado o vídeo requieren revisión visual. El color pertenece al diseño: no se cambia.</p>';
		self::table( array( 'status' => 'Estado', 'text' => 'Texto', 'color' => 'Color', 'background' => 'Fondo', 'ratio' => 'Ratio', 'expected' => 'Esperado', 'level' => 'Nivel', 'selector' => 'Selector' ), $f['contrast'] ?? array(), 'Todos los textos revisados alcanzan AA.' );
	}

	private static function role_form( $ctx, $id, $current ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:4px;align-items:center">';
		wp_nonce_field( 'digitalisimo_quality_role_' . $ctx['site_id'] );
		echo '<input type="hidden" name="action" value="digitalisimo_quality_image_role"><input type="hidden" name="network_context" value="' . ( $ctx['network'] ? '1' : '0' ) . '"><input type="hidden" name="site_id" value="' . (int) $ctx['site_id'] . '"><input type="hidden" name="url" value="' . esc_attr( $ctx['url'] ) . '"><input type="hidden" name="attachment_id" value="' . (int) $id . '"><select name="role">';
		foreach ( array( '' => 'Automático', 'informative' => 'Informativa', 'decorative' => 'Decorativa' ) as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select><button class="button button-small">Guardar</button></form>';
	}

	/** Rendimiento y calidad → SEO técnico: rastreabilidad de enlaces y jerarquía. */
	public static function render_seo( $network ) {
		$ctx = self::context( $network );
		if ( ! self::header( $ctx, 'SEO técnico', 'Enlaces que un buscador no puede seguir y jerarquía de encabezados. No se inventan URLs ni se cambian etiquetas: Elementor permite elegir la etiqueta HTML de cada título y la decisión es del diseñador.' ) ) return;
		$f = (array) $ctx['record']['findings'];
		echo '<h3>Enlaces</h3>';
		self::table( array( 'status' => 'Estado', 'issue' => 'Problema', 'kind' => 'Tipo', 'name' => 'Texto accesible', 'href' => 'href', 'selector' => 'Selector', 'widget' => 'Widget', 'hidden' => 'Oculto' ), $f['links'] ?? array(), 'Todos los enlaces tienen un destino válido.' );
		echo '<h3>Encabezados · escritorio</h3>';
		self::table( array( 'status' => 'Estado', 'issue' => 'Problema', 'text' => 'Texto', 'selector' => 'Selector', 'widget' => 'Widget' ), $f['headings'] ?? array(), 'Jerarquía correcta.' );
		echo '<pre style="background:#fff;border:1px solid #dcdcde;padding:12px;max-height:420px;overflow:auto">' . esc_html( implode( "\n", (array) ( $ctx['record']['tree']['desktop'] ?? array() ) ) ?: 'Sin encabezados visibles.' ) . '</pre>';
		$mobile = (array) ( $ctx['record']['tree']['mobile'] ?? array() );
		if ( $mobile && $mobile !== (array) $ctx['record']['tree']['desktop'] ) {
			echo '<h3>Encabezados · móvil</h3><p class="description">Difiere del escritorio: hay encabezados ocultos o distintos por dispositivo.</p>';
			self::table( array( 'status' => 'Estado', 'issue' => 'Problema', 'text' => 'Texto', 'selector' => 'Selector', 'widget' => 'Widget' ), $f['headings_mobile'] ?? array(), 'Jerarquía correcta.' );
			echo '<pre style="background:#fff;border:1px solid #dcdcde;padding:12px;max-height:420px;overflow:auto">' . esc_html( implode( "\n", $mobile ) ) . '</pre>';
		}
	}

	/** Rendimiento y calidad → Agentes IA: estado y validación de llms.txt. */
	public static function render_agents( $network ) {
		$ctx    = self::context( $network );
		$status = self::in_site( $ctx['site_id'], array( 'Digitalisimo_Integrations_LLMS', 'status' ) );
		$url    = self::in_site( $ctx['site_id'], array( 'Digitalisimo_Integrations_LLMS', 'endpoint' ) );
		$state  = ! $status['enabled'] ? 'NO APLICABLE' : ( ! $status['valid'] ? 'ERROR' : ( $status['fallback'] ? 'ADVERTENCIA' : 'OK' ) );
		$modes  = array( 'automatic' => 'Automático', 'manual' => 'Manual', 'hybrid' => 'Híbrido' );
		echo '<h2>llms.txt</h2><p>Markdown opcional que resume el sitio para agentes de IA. Se genera con el nombre, la descripción, home_url() y las páginas públicas e indexables de cada sitio, y se valida antes de servirse. No es requisito SEO ni garantiza visibilidad o citación.</p>';
		echo '<table class="widefat striped" style="max-width:900px"><tbody><tr><th>Estado</th><td>' . self::badge( $state ) . ' ' . esc_html( $status['enabled'] ? '' : 'Desactivado en este sitio.' ) . '</td></tr><tr><th>URL</th><td><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a></td></tr><tr><th>Modo</th><td>' . esc_html( $modes[ $status['mode'] ] ?? $status['mode'] ) . '</td></tr><tr><th>Enlaces válidos</th><td>' . (int) $status['links'] . '</td></tr>';
		if ( $status['errors'] ) echo '<tr><th>' . ( $status['fallback'] ? 'Contenido manual rechazado' : 'Errores' ) . '</th><td><ul style="margin:0">' . implode( '', array_map( function( $e ) { return '<li>' . esc_html( $e ) . '</li>'; }, $status['errors'] ) ) . '</ul>' . ( $status['fallback'] ? '<p>Se sirve el contenido automático hasta que el manual sea válido.</p>' : '' ) . '</td></tr>';
		echo '</tbody></table><h3>Vista previa</h3><pre style="background:#fff;border:1px solid #dcdcde;padding:12px;max-height:420px;overflow:auto;white-space:pre-wrap">' . esc_html( substr( (string) $status['content'], 0, 8000 ) ) . '</pre>';
	}

	/** Bajo Imágenes: lo que el navegador descargó frente a lo que pintó. */
	public static function render_images( $network ) {
		$ctx = self::context( $network );
		echo '<h2>Medición en navegador</h2>';
		if ( ! $ctx['record'] ) { echo '<p>Ejecuta una <a href="' . esc_url( self::section_url( $network, 'audit', $ctx['site_id'], $ctx['url'] ) ) . '">Auditoría</a> para comparar el archivo descargado (naturalWidth) con el tamaño pintado × DPR. En móvil se supone DPR 3.</p>'; return; }
		echo '<p>Auditoría de <a href="' . esc_url( $ctx['record']['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $ctx['record']['url'] ) . '</a>. Una advertencia de variante menor se corrige con sizes, nunca cambiando el src.</p>';
		$rows = (array) ( $ctx['record']['findings']['images'] ?? array() );
		$show = isset( $_GET['all_images'] ) ? $rows : self::filter( $rows );
		if ( count( $show ) !== count( $rows ) ) echo '<p><a href="' . esc_url( add_query_arg( 'all_images', 1 ) ) . '">Ver las ' . count( $rows ) . ' imágenes medidas</a></p>';
		self::table( array( 'status' => 'Estado', 'viewport' => 'Vista', 'issue' => 'Detalle', 'src' => 'Archivo', 'natural' => 'Descargado', 'rect' => 'Pintado', 'dpr' => 'DPR', 'needed' => 'Necesario', 'candidate' => 'Variante suficiente', 'sizes' => 'sizes', 'srcset' => 'srcset', 'width' => 'width', 'height' => 'height', 'loading' => 'loading', 'fetchpriority' => 'fetchpriority', 'decoding' => 'decoding' ), $show, 'Ninguna imagen visible descarga más de lo que necesita.' );
	}

	/** Bajo CSS: hojas de la página, su widget y si diferirlas es seguro. */
	public static function render_css( $network ) {
		$ctx = self::context( $network );
		echo '<h2>CSS por página</h2><p>La lista diferible global no se amplía sola. Desde aquí puedes añadir a la lista de este sitio un handle que la auditoría mostró seguro: el widget aparece sólo debajo del primer viewport en escritorio y en móvil.</p>';
		if ( ! $ctx['record'] ) { echo '<p>Ejecuta una <a href="' . esc_url( self::section_url( $network, 'audit', $ctx['site_id'], $ctx['url'] ) ) . '">Auditoría</a> para ver las hojas de esta página.</p>'; return; }
		echo '<p>Auditoría de <a href="' . esc_url( $ctx['record']['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $ctx['record']['url'] ) . '</a>.</p>';
		$rows = (array) ( $ctx['record']['findings']['css'] ?? array() );
		if ( ! $rows ) { echo '<p>No se capturaron hojas CSS.</p>'; return; }
		echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Estado</th><th>Handle</th><th>Archivo</th><th>Widget</th><th>En la página</th><th>Primer viewport</th><th>Estado actual</th><th>Diferir</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$fold = null === $row['above_desktop'] && null === $row['above_mobile'] ? '—' : 'Escritorio: ' . ( $row['above_desktop'] ? 'sí' : 'no' ) . ' · Móvil: ' . ( null === $row['above_mobile'] ? '—' : ( $row['above_mobile'] ? 'sí' : 'no' ) );
			echo '<tr><td>' . self::badge( $row['status'] ) . '</td><td><code>' . esc_html( $row['handle'] ) . '</code></td><td><code style="word-break:break-all">' . esc_html( $row['src'] ) . '</code></td><td>' . esc_html( $row['widget'] ?: '—' ) . '</td><td>' . esc_html( '' === $row['widget'] ? '—' : ( $row['present'] ? 'Sí' : 'No' ) ) . '</td><td>' . esc_html( $fold ) . '</td><td>' . esc_html( $row['state'] ) . '</td><td>' . esc_html( ( $row['safe'] ? 'Seguro · ' : 'No seguro · ' ) . $row['reason'] );
			if ( $row['safe'] && ! $row['selected'] ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:4px">';
				wp_nonce_field( 'digitalisimo_quality_css_' . $ctx['site_id'] );
				echo '<input type="hidden" name="action" value="digitalisimo_quality_css_add"><input type="hidden" name="network_context" value="' . ( $network ? '1' : '0' ) . '"><input type="hidden" name="site_id" value="' . (int) $ctx['site_id'] . '"><input type="hidden" name="url" value="' . esc_attr( $ctx['url'] ) . '"><input type="hidden" name="handle" value="' . esc_attr( $row['handle'] ) . '"><button class="button button-small">Añadir a la lista de este sitio</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
