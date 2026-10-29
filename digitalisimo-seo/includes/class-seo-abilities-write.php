<?php
defined( 'ABSPATH' ) || exit;

/**
 * Abilities de escritura para agentes de IA (RM15 de docs/SEO_RANKMATH_OBJECTIVE.md).
 *
 * Sólo con `seo_abilities_write` encendido (opt-in, con paridad). Cada una exige el permiso de
 * WordPress del objeto (`edit_post` del contenido; `manage_options` para redirecciones), sanea
 * igual que el editor y deja rastro: cada cambio de un contenido guarda los valores anteriores en
 * su historial (los 20 últimos), y `revert-post-seo` deshace el último. Nunca publican, borran ni
 * cambian el texto, el estado o el enlace de un contenido.
 */
class Digitalisimo_Integrations_SEO_Abilities_Write {
	const HISTORY = 'digitalisimo_seo_history';
	const KEEP    = 20;
	/** Campo de la ability => tipo de saneado. */
	const FIELDS  = array( 'title' => 'template', 'description' => 'template_long', 'keywords' => 'keywords', 'canonical' => 'url', 'noindex' => 'flag', 'nofollow' => 'flag', 'social_title' => 'template', 'social_description' => 'template_long', 'breadcrumb_title' => 'text' );

	public static function init() {
		if ( ! function_exists( 'wp_register_ability' ) ) return;
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register' ) );
	}

	public static function enabled() { return (bool) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_abilities_write', 0 ); }

	/** Nombre => array( etiqueta, descripción, entrada, ejecutor, permiso, anotaciones ). */
	public static function definitions() {
		$props = array( 'post_id' => array( 'type' => 'integer', 'description' => 'ID del contenido.' ) );
		foreach ( self::FIELDS as $field => $type ) $props[ $field ] = 'flag' === $type ? array( 'type' => 'boolean' ) : array( 'type' => 'string', 'maxLength' => 'template_long' === $type ? 1000 : 300 );
		$props['title']['description']       = 'Título SEO. Admite variables como %title% %sep% %sitename%. Cadena vacía vuelve a la plantilla.';
		$props['description']['description'] = 'Meta descripción. Cadena vacía vuelve a la plantilla.';
		$props['keywords']['description']    = 'Keywords separadas por comas; la primera es la principal.';
		return array(
			'update-post-seo'    => array( 'Cambiar los metadatos SEO de un contenido', 'Cambia sólo los campos enviados (título SEO, descripción, keywords, canonical, noindex, nofollow, datos sociales, título de miga). Guarda los valores anteriores en el historial del contenido para poder deshacerlo. No cambia el texto, el estado ni el enlace del contenido.', array( 'type' => 'object', 'required' => array( 'post_id' ), 'properties' => $props, 'additionalProperties' => false ), array( __CLASS__, 'update_post_seo' ), array( __CLASS__, 'can_edit' ), array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ),
			'revert-post-seo'    => array( 'Deshacer el último cambio SEO de un contenido', 'Restaura los valores anteriores al último cambio registrado en el historial del contenido (hecho por una ability).', array( 'type' => 'object', 'required' => array( 'post_id' ), 'properties' => array( 'post_id' => $props['post_id'] ), 'additionalProperties' => false ), array( __CLASS__, 'revert_post_seo' ), array( __CLASS__, 'can_edit' ), array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ) ),
			'create-redirection' => array( 'Crear una redirección', 'Crea una redirección del sitio (301 por defecto). Si ya existe una con el mismo origen exacto, devuelve esa sin crear otra. Rechaza bucles y expresiones regulares inválidas.', array( 'type' => 'object', 'required' => array( 'source' ), 'properties' => array( 'source' => array( 'type' => 'string', 'maxLength' => 500, 'description' => 'Ruta de origen, como /pagina-anterior/.' ), 'target' => array( 'type' => 'string', 'maxLength' => 2000, 'description' => 'Ruta del sitio o URL http(s). Vacío sólo con 410 o 451.' ), 'code' => array( 'type' => 'integer', 'enum' => array( 301, 302, 307, 410, 451 ) ), 'comparison' => array( 'type' => 'string', 'enum' => array( 'exact', 'start', 'end', 'contains', 'regex' ) ) ), 'additionalProperties' => false ), array( __CLASS__, 'create_redirection' ), array( __CLASS__, 'can_manage' ), array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ),
		);
	}

	public static function register() {
		foreach ( self::definitions() as $name => $d ) {
			wp_register_ability( Digitalisimo_Integrations_SEO_Abilities::CATEGORY . '/' . $name, array(
				'label'               => $d[0],
				'description'         => $d[1],
				'category'            => Digitalisimo_Integrations_SEO_Abilities::CATEGORY,
				'input_schema'        => $d[2],
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => $d[3],
				'permission_callback' => $d[4],
				'meta'                => array( 'show_in_rest' => true, 'annotations' => $d[5], 'mcp' => array( 'public' => true ) ),
			) );
		}
	}

	public static function can_edit( $input = array() ) { return self::enabled() && Digitalisimo_Integrations_SEO_Abilities::can_edit( $input ); }
	public static function can_manage() { return self::enabled() && current_user_can( 'manage_options' ); }

	private static function post( $input ) {
		$post = get_post( absint( $input['post_id'] ?? 0 ) );
		return $post && in_array( $post->post_type, Digitalisimo_Integrations_Editorial::content_types(), true ) && 'trash' !== $post->post_status ? $post : null;
	}

	/** Valor saneado de un campo, igual que el editor. Pura salvo los saneadores de WordPress. */
	public static function clean( $field, $value ) {
		switch ( self::FIELDS[ $field ] ?? '' ) {
			case 'template': return Digitalisimo_Integrations_Settings::sanitize_template( $value );
			case 'template_long': return Digitalisimo_Integrations_Settings::sanitize_template( $value, true );
			case 'url': return esc_url_raw( (string) $value, array( 'http', 'https' ) );
			case 'flag': return $value ? '1' : '';
			case 'keywords': return implode( ',', array_slice( array_values( array_unique( array_filter( array_map( 'trim', explode( ',', sanitize_text_field( (string) $value ) ) ) ) ) ), 0, 5 ) );
			case 'text': return sanitize_text_field( (string) $value );
		}
		return null;
	}

	/**
	 * Cambios reales: campo => array( anterior, nuevo ). Lo que no cambia no se escribe ni se registra.
	 *
	 * @param array $current Campo => valor guardado.
	 * @param array $input   Entrada de la ability.
	 */
	public static function changes( array $current, array $input ) {
		$out = array();
		foreach ( self::FIELDS as $field => $type ) {
			if ( ! array_key_exists( $field, $input ) ) continue;
			$new = self::clean( $field, $input[ $field ] );
			$old = (string) ( $current[ $field ] ?? '' );
			if ( null !== $new && $new !== $old ) $out[ $field ] = array( $old, $new );
		}
		return $out;
	}

	public static function update_post_seo( $input ) {
		$post = self::post( $input );
		if ( ! $post ) return new WP_Error( 'digitalisimo_seo_not_found', 'No hay un contenido con SEO con ese ID.' );
		$current = array();
		foreach ( array_keys( self::FIELDS ) as $field ) $current[ $field ] = (string) get_post_meta( $post->ID, 'digitalisimo_seo_' . $field, true );
		$changes = self::changes( $current, (array) $input );
		foreach ( $changes as $field => $pair ) self::write( $post->ID, $field, $pair[1] );
		if ( $changes ) {
			$history   = get_post_meta( $post->ID, self::HISTORY, true );
			$history   = is_array( $history ) ? $history : array();
			$history[] = array( 'time' => time(), 'user' => get_current_user_id(), 'via' => 'ability', 'before' => array_map( function( $pair ) { return $pair[0]; }, $changes ) );
			update_post_meta( $post->ID, self::HISTORY, array_slice( $history, -self::KEEP ) );
		}
		$seo = Digitalisimo_Integrations_SEO_Abilities::get_post_seo( array( 'post_id' => $post->ID ) );
		return array( 'post_id' => $post->ID, 'changed' => array_keys( $changes ), 'seo' => $seo );
	}

	public static function revert_post_seo( $input ) {
		$post = self::post( $input );
		if ( ! $post ) return new WP_Error( 'digitalisimo_seo_not_found', 'No hay un contenido con SEO con ese ID.' );
		$history = get_post_meta( $post->ID, self::HISTORY, true );
		if ( ! is_array( $history ) || ! $history ) return new WP_Error( 'digitalisimo_seo_no_history', 'Este contenido no tiene cambios que deshacer.' );
		$last = array_pop( $history );
		foreach ( (array) ( $last['before'] ?? array() ) as $field => $value ) if ( isset( self::FIELDS[ $field ] ) ) self::write( $post->ID, $field, (string) $value );
		$history ? update_post_meta( $post->ID, self::HISTORY, $history ) : delete_post_meta( $post->ID, self::HISTORY );
		return array( 'post_id' => $post->ID, 'restored' => array_keys( (array) ( $last['before'] ?? array() ) ), 'remaining' => count( $history ) );
	}

	private static function write( $post_id, $field, $value ) {
		if ( '' === $value ) delete_post_meta( $post_id, 'digitalisimo_seo_' . $field );
		else update_post_meta( $post_id, 'digitalisimo_seo_' . $field, $value );
	}

	public static function create_redirection( $input ) {
		$comparison = (string) ( $input['comparison'] ?? 'exact' );
		$code       = (int) ( $input['code'] ?? Digitalisimo_Integrations_Redirections::sanitize_code( Digitalisimo_Integrations_SEO_Resolver::option( 'seo_redirect_default_code', 301 ) ) );
		$source     = Digitalisimo_Integrations_Redirections::sanitize_source( array( 'pattern' => (string) ( $input['source'] ?? '' ), 'comparison' => $comparison ), (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		if ( ! $source ) return new WP_Error( 'digitalisimo_seo_source', 'El origen está vacío o no es válido.' );
		if ( 'exact' === $source['comparison'] ) {
			global $wpdb;
			Digitalisimo_Integrations_Redirections::maybe_install();
			foreach ( (array) $wpdb->get_results( 'SELECT id, sources FROM ' . Digitalisimo_Integrations_Redirections::table() . " WHERE status IN ('active','inactive')", ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				foreach ( (array) json_decode( (string) $row['sources'], true ) as $existing ) if ( is_array( $existing ) && Digitalisimo_Integrations_Redirections::signature( $existing ) === Digitalisimo_Integrations_Redirections::signature( $source ) ) return array( 'id' => (int) $row['id'], 'created' => false );
			}
		}
		$id = Digitalisimo_Integrations_Redirections::save( array( 'sources' => array( $source ), 'url_to' => (string) ( $input['target'] ?? '' ), 'header_code' => $code, 'origin' => 'ability' ) );
		if ( is_wp_error( $id ) ) return $id;
		return array( 'id' => (int) $id, 'created' => true );
	}
}
