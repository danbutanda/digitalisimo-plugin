<?php
namespace Digitalisimo\Tools;
defined( 'ABSPATH' ) || exit;

/**
 * Ajustes del sitio que no son SEO: ruta privada de acceso, redirección de
 * adjuntos y bloqueo del desplazamiento horizontal accidental en móvil.
 *
 * La red define los valores que heredan los sitios y cada sitio puede
 * personalizarlos. Antes vivían en DIGITALÍSIMO SEO: la primera lectura importa
 * esos valores (incluida la herencia de cada sitio) para que la ruta de acceso
 * y lo demás sigan iguales al actualizar.
 */
final class Site_Options {
	const SITE     = 'digitalisimo_tools_site_options';
	const NETWORK  = 'digitalisimo_tools_site_defaults';
	const PAGE     = 'digitalisimo-tools-elementor-templates';
	const ACTION   = 'digitalisimo_tools_save_site_options';
	const LEGACY   = 'digitalisimo_integrations_options';
	const INHERITS = 'digitalisimo_seo_network_inherit';
	/** Clave en Tools → clave que usaba DIGITALÍSIMO SEO. */
	const LEGACY_KEYS = array( 'login_slug' => 'seo_hide_login_slug', 'redirect_attachments' => 'seo_redirect_attachments', 'mobile_scroll' => 'seo_mobile_prevent_horizontal_scroll' );
	const DEFAULTS    = array( 'login_slug' => '', 'redirect_attachments' => 1, 'mobile_scroll' => 0 );

	/** Al cargar el archivo: la ruta privada debe decidirse antes de `plugins_loaded`. */
	public static function boot() {
		Hide_Login::boot();
	}

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'attachment_redirect' ) );
		add_action( 'wp_head', array( __CLASS__, 'mobile_scroll_css' ), 0 );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'save' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Valores
	 * ------------------------------------------------------------------ */

	/** Valores de red; la primera vez se importan de SEO. */
	public static function network_values() {
		if ( ! is_multisite() ) return self::DEFAULTS;
		$stored = get_site_option( self::NETWORK, false );
		if ( false === $stored ) {
			$stored = self::import( (array) get_site_option( self::LEGACY, array() ) );
			update_site_option( self::NETWORK, $stored );
		}
		return array_merge( self::DEFAULTS, array_intersect_key( (array) $stored, self::DEFAULTS ) );
	}

	/** array( values, inherit ) del sitio actual; la primera vez se importa de SEO. */
	public static function site_data() {
		$stored = get_option( self::SITE, false );
		if ( false === $stored ) {
			$stored = self::import_site( (array) get_option( self::LEGACY, array() ), (array) get_option( self::INHERITS, array() ), is_multisite() ? (array) get_site_option( self::LEGACY, array() ) : array() );
			update_option( self::SITE, $stored, true );
		}
		return array( 'values' => array_intersect_key( (array) ( $stored['values'] ?? array() ), self::DEFAULTS ), 'inherit' => (array) ( $stored['inherit'] ?? array() ) );
	}

	/** Las claves de SEO presentes en $legacy, con su nombre de Tools. */
	public static function import( $legacy ) {
		$out = array();
		foreach ( self::LEGACY_KEYS as $key => $old ) if ( array_key_exists( $old, $legacy ) ) $out[ $key ] = $legacy[ $old ];
		return $out;
	}

	/**
	 * Reproduce la resolución que hacía SEO: heredaba salvo que el sitio lo
	 * desmarcara, pero si la red no tenía la clave usaba el valor del sitio.
	 * En ese caso el sitio queda personalizado para no perder su valor.
	 */
	public static function import_site( $legacy, $inherit_map, $network_legacy ) {
		$values  = self::import( $legacy );
		$inherit = array();
		foreach ( self::LEGACY_KEYS as $key => $old ) {
			$inherits = ! array_key_exists( $old, $inherit_map ) || ! empty( $inherit_map[ $old ] );
			if ( $inherits && ! array_key_exists( $old, $network_legacy ) && array_key_exists( $key, $values ) ) $inherits = false;
			$inherit[ $key ] = $inherits;
		}
		return array( 'values' => $values, 'inherit' => $inherit );
	}

	public static function inherits( $key ) {
		if ( ! is_multisite() ) return false;
		$data = self::site_data();
		return ! array_key_exists( $key, $data['inherit'] ) || ! empty( $data['inherit'][ $key ] );
	}

	/** contenido → sitio → red → default. */
	public static function get( $key ) {
		if ( ! array_key_exists( $key, self::DEFAULTS ) ) return null;
		if ( self::inherits( $key ) ) return self::network_values()[ $key ];
		$data = self::site_data();
		return array_key_exists( $key, $data['values'] ) ? $data['values'][ $key ] : self::DEFAULTS[ $key ];
	}

	/* ------------------------------------------------------------------ *
	 * Funciones públicas
	 * ------------------------------------------------------------------ */

	public static function attachment_redirect() {
		if ( ! is_attachment() || ! self::get( 'redirect_attachments' ) ) return;
		$parent = wp_get_post_parent_id( get_queried_object_id() );
		if ( $parent ) { wp_safe_redirect( get_permalink( $parent ), 301 ); exit; }
	}

	public static function mobile_scroll_css() {
		if ( is_admin() || is_feed() || ! self::get( 'mobile_scroll' ) ) return;
		echo '<style id="digitalisimo-mobile-horizontal-scroll">@media (max-width:767px){html,body{width:100%;max-width:100%;overflow-x:clip;overscroll-behavior-x:none}body{touch-action:pan-y pinch-zoom}.swiper,.swiper-wrapper,.elementor-swiper,.elementor-image-carousel-wrapper,.elementor-slides-wrapper,.elementor-main-swiper{touch-action:pan-x pan-y pinch-zoom}}</style>' . "\n";
	}

	/* ------------------------------------------------------------------ *
	 * Administración
	 * ------------------------------------------------------------------ */

	private static function fields() {
		return array(
			'login_slug'           => array( 'Ruta privada de acceso', 'text', 'Una palabra o guiones (por ejemplo acceso-equipo). Con una ruta, wp-login.php, /wp-admin, /admin y /dashboard dejan de responder sin sesión; con la sesión iniciada /wp-admin funciona igual. Vacío conserva el acceso estándar. Si pierdes la ruta, añade define( \'DIGITALISIMO_HIDE_LOGIN_DISABLE\', true ); a wp-config.php.' ),
			'redirect_attachments' => array( 'Redirigir adjuntos a su contenido padre', 'checkbox', 'Las páginas de adjunto envían con 301 a la entrada o página donde se subió el archivo.' ),
			'mobile_scroll'        => array( 'Bloquear desplazamiento horizontal accidental en móvil', 'checkbox', 'Evita el pequeño movimiento lateral provocado por gestos diagonales en dispositivos táctiles, sin afectar el scroll vertical ni los sliders o carruseles horizontales.' ),
		);
	}

	public static function page( $network ) {
		if ( ! current_user_can( $network ? 'manage_network_options' : 'manage_options' ) ) return;
		$notice = sanitize_key( $_GET['site_options'] ?? '' );
		if ( 'saved' === $notice ) echo '<div class="notice notice-success inline"><p>Ajustes guardados.</p></div>';
		$error = get_transient( 'digitalisimo_tools_site_options_error_' . get_current_user_id() );
		if ( $error ) { delete_transient( 'digitalisimo_tools_site_options_error_' . get_current_user_id() ); echo '<div class="notice notice-error inline"><p>' . esc_html( $error ) . '</p></div>'; }
		echo '<h2>Ajustes del sitio</h2><p>' . ( $network ? 'Valores que heredan todos los sitios de la red. Cada sitio puede personalizarlos en su propio DIGITALÍSIMO Tools.' : 'Acceso al escritorio y comportamiento general del sitio.' ) . '</p>';
		$slug = Hide_Login::slug();
		if ( ! $network && $slug ) echo '<div class="notice notice-info inline"><p>Acceso activo en <code>' . esc_html( Hide_Login::login_url() ) . '</code>. Guarda esta URL.</p></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . self::ACTION . '"><input type="hidden" name="scope" value="' . ( $network ? 'network' : 'site' ) . '">';
		wp_nonce_field( self::ACTION );
		echo '<table class="form-table" role="presentation">';
		$network_values = self::network_values();
		foreach ( self::fields() as $key => $field ) {
			list( $label, $type, $help ) = $field;
			$inherit = ! $network && self::inherits( $key );
			$value   = $network ? $network_values[ $key ] : self::get( $key );
			$name    = 'values[' . $key . ']';
			echo '<tr><th scope="row"><label for="dt_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
			if ( 'checkbox' === $type ) echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0"><label><input type="checkbox" id="dt_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $value ), true, false ) . ( $inherit ? ' disabled' : '' ) . '> Activar</label>';
			else echo '<input class="regular-text" type="text" id="dt_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $inherit ? ' disabled' : '' ) . '>';
			echo '<p class="description">' . esc_html( $help ) . '</p>';
			// El checkbox de herencia sólo existe en cada sitio: la red define lo que todos heredan.
			if ( is_multisite() && ! $network ) echo '<p><input type="hidden" name="inherit[' . esc_attr( $key ) . ']" value="0"><label><input type="checkbox" class="digitalisimo-tools-inherit" data-field="dt_' . esc_attr( $key ) . '" name="inherit[' . esc_attr( $key ) . ']" value="1" ' . checked( $inherit, true, false ) . '> Heredar de la red</label></p>';
			echo '</td></tr>';
		}
		echo '</table>';
		submit_button( $network ? 'Guardar valores de red' : 'Guardar ajustes' );
		echo '</form><script>document.querySelectorAll(".digitalisimo-tools-inherit").forEach(function(box){box.addEventListener("change",function(){var f=document.getElementById(box.dataset.field);if(f)f.disabled=box.checked;});});</script>';
	}

	/** Sanitiza un valor según su clave; devuelve array( valor, error ). */
	public static function sanitize( $key, $value, $current ) {
		if ( 'login_slug' === $key ) return Hide_Login::sanitize_option( is_scalar( $value ) ? (string) $value : '', $current );
		return array( empty( $value ) ? 0 : 1, '' );
	}

	public static function save() {
		$network = is_multisite() && 'network' === sanitize_key( wp_unslash( $_POST['scope'] ?? '' ) );
		if ( ! current_user_can( $network ? 'manage_network_options' : 'manage_options' ) ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION );
		$input   = (array) wp_unslash( $_POST['values'] ?? array() );
		$errors  = array();
		if ( $network ) {
			$values = self::network_values();
			foreach ( self::DEFAULTS as $key => $default ) {
				if ( ! array_key_exists( $key, $input ) ) continue;
				list( $values[ $key ], $error ) = self::sanitize( $key, $input[ $key ], $values[ $key ] );
				if ( $error ) $errors[] = $error;
			}
			update_site_option( self::NETWORK, $values );
		} else {
			$data      = self::site_data();
			$inherit   = (array) wp_unslash( $_POST['inherit'] ?? array() );
			foreach ( self::DEFAULTS as $key => $default ) {
				if ( is_multisite() ) $data['inherit'][ $key ] = ! empty( $inherit[ $key ] );
				if ( ! array_key_exists( $key, $input ) ) continue;
				$current = array_key_exists( $key, $data['values'] ) ? $data['values'][ $key ] : $default;
				list( $data['values'][ $key ], $error ) = self::sanitize( $key, $input[ $key ], $current );
				if ( $error ) $errors[] = $error;
			}
			update_option( self::SITE, $data, true );
		}
		if ( $errors ) set_transient( 'digitalisimo_tools_site_options_error_' . get_current_user_id(), implode( ' ', $errors ), 60 );
		$base = $network ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' );
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'tab' => 'site', 'site_options' => 'saved' ), $base ) );
		exit;
	}
}
