<?php
defined( 'ABSPATH' ) || exit;

/**
 * Abilities de escritura para agentes de IA (RM15 de docs/SEO_RANKMATH_OBJECTIVE.md).
 *
 * Para los clientes MCP externos, el permiso va por partes (tanda B de la auditoría SEO, B5): títulos y descripciones,
 * schema y datos de marca, sitemap e indexación, y redirecciones, cada una con su interruptor (opt-in, con paridad) y su
 * registro de cambios (`LOG`). Antes era un solo interruptor, `seo_abilities_write`: mientras un sitio o la red no
 * guarden las partes, cada parte vale lo que valía ese interruptor (`part_enabled()`), y `Settings::migrate_ability_parts()`
 * lo copia a las cuatro la primera vez que se abre el escritorio, para que el formulario muestre lo que rige.
 * El Asistente IA del escritorio (IA Tools) no necesita los interruptores: cada cambio suyo lo confirma la persona en
 * pantalla y queda en su bitácora, así que, dentro de su contexto (`Digitalisimo_AI_Assistant_Context`, que no se puede
 * abrir desde una petición REST o MCP externa), basta la capacidad del usuario (caso plomerito 4, 2026-11-26). Los
 * interruptores gobiernan a los clientes MCP externos. Cada ability exige el permiso de WordPress del objeto
 * (`edit_post` del contenido; `manage_options` para redirecciones), sanea igual que el editor y deja rastro: cada cambio
 * de un contenido guarda los valores anteriores en su historial (los 20 últimos), y `revert-post-seo` deshace el último.
 * Nunca publican, borran ni cambian el texto, el estado o el enlace de un contenido.
 */
class Digitalisimo_Integrations_SEO_Abilities_Write {
	const HISTORY = 'digitalisimo_seo_history';
	const KEEP    = 20;
	/** Campo de la ability => tipo de saneado. */
	const FIELDS  = array( 'title' => 'template', 'description' => 'template_long', 'keywords' => 'keywords', 'canonical' => 'url', 'noindex' => 'flag', 'nofollow' => 'flag', 'social_title' => 'template', 'social_description' => 'template_long', 'breadcrumb_title' => 'text', 'schema_type' => 'schema_type' );
	/** El interruptor único de antes: sólo cuenta para las partes que el sitio o la red aún no guardaron. */
	const LEGACY = 'seo_abilities_write';
	/** Parte del permiso => array( opción, nombre visible ). */
	const PARTS = array(
		'meta'      => array( 'seo_abilities_write_meta', 'Títulos y descripciones' ),
		'schema'    => array( 'seo_abilities_write_schema', 'Schema y datos de marca' ),
		'index'     => array( 'seo_abilities_write_index', 'Sitemap e indexación' ),
		'redirects' => array( 'seo_abilities_write_redirects', 'Redirecciones' ),
	);
	/** Campo de update-post-seo => parte que lo gobierna. */
	const FIELD_PART = array( 'title' => 'meta', 'description' => 'meta', 'keywords' => 'meta', 'social_title' => 'meta', 'social_description' => 'meta', 'breadcrumb_title' => 'meta', 'schema_type' => 'schema', 'canonical' => 'index', 'noindex' => 'index', 'nofollow' => 'index' );
	/** Tipos de schema que el editor deja elegir en cada contenido ('' = automático). */
	const SCHEMA_TYPES = array( '', 'Article', 'WebPage', 'FAQPage', 'Service', 'Product', 'LocalBusiness', 'Person' );
	/** Registro de cambios de las abilities de escritura (sin autoload): las últimas LOG_KEEP entradas, con su parte. */
	const LOG      = 'digitalisimo_seo_ability_log';
	const LOG_KEEP = 200;

	public static function init() {
		if ( ! function_exists( 'wp_register_ability' ) ) return;
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register' ) );
	}

	/** ¿Alguna parte abierta para los clientes MCP externos? */
	public static function enabled() {
		foreach ( array_keys( self::PARTS ) as $part ) if ( self::part_enabled( $part ) ) return true;
		return false;
	}

	/** Opciones de las cuatro partes. */
	public static function part_keys() { return array_values( array_map( function( $p ) { return $p[0]; }, self::PARTS ) ); }

	/**
	 * ¿Está abierta esta parte para los clientes MCP externos? Con la herencia de siempre (sitio → red). Una parte que el
	 * sitio (y, si la hereda, la red) aún no guardó vale lo que valía el interruptor único: así, quien lo tenía encendido
	 * conserva las cuatro encendidas y quien lo tenía apagado, las cuatro apagadas, aunque nadie haya abierto el escritorio
	 * desde la actualización (un cliente MCP llega por REST).
	 */
	public static function part_enabled( $part ) {
		if ( ! isset( self::PARTS[ $part ] ) ) return false;
		$key = self::PARTS[ $part ][0];
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$network = (array) get_site_option( Digitalisimo_Integrations_Settings::OPTION, array() );
			$inherit = (array) get_option( 'digitalisimo_seo_network_inherit', array() );
			// Un sitio que aún no eligió nada para la parte hereda (o no) como heredaba el interruptor único.
			$inherits = array_key_exists( $key, $inherit ) ? ! empty( $inherit[ $key ] ) : ( ! array_key_exists( self::LEGACY, $inherit ) || ! empty( $inherit[ self::LEGACY ] ) );
			if ( $inherits && array_key_exists( $key, $network ) ) return ! empty( $network[ $key ] );
			if ( $inherits && array_key_exists( self::LEGACY, $network ) ) return ! empty( $network[ self::LEGACY ] );
		}
		$site = (array) get_option( Digitalisimo_Integrations_Settings::OPTION, array() );
		if ( array_key_exists( $key, $site ) ) return ! empty( $site[ $key ] );
		return (bool) Digitalisimo_Integrations_SEO_Resolver::option( self::LEGACY, 0 );
	}

	/** Parte de cada campo enviado (sin post_id). */
	public static function parts_of( array $fields ) {
		$parts = array();
		foreach ( $fields as $field ) if ( isset( self::FIELD_PART[ $field ] ) ) $parts[ self::FIELD_PART[ $field ] ] = true;
		return array_keys( $parts );
	}

	/** Partes cerradas para esta llamada. Para el Asistente del escritorio (su contexto abierto para $ability) ninguna. */
	public static function closed_parts( array $parts, $ability ) {
		if ( '' !== self::assistant( array( $ability ) ) ) return array();
		return array_values( array_filter( $parts, function( $part ) { return ! self::part_enabled( $part ); } ) );
	}

	private static function closed_error( array $closed ) {
		$names = array_map( function( $part ) { return '«' . self::PARTS[ $part ][1] . '»'; }, $closed );
		return new WP_Error( 'digitalisimo_seo_part_closed', 'Este sitio no permite a los clientes MCP cambiar ' . implode( ' ni ', $names ) . '. Se enciende en Digitalisimo → SEO → Indexación → Agentes de IA. No se cambió nada.', array( 'status' => 403, 'parts' => $closed ) );
	}

	/** Nombre => array( etiqueta, descripción, entrada, ejecutor, permiso, anotaciones ). */
	public static function definitions() {
		$props = array( 'post_id' => array( 'type' => 'integer', 'description' => 'ID del contenido.' ) );
		foreach ( self::FIELDS as $field => $type ) $props[ $field ] = 'flag' === $type ? array( 'type' => 'boolean' ) : array( 'type' => 'string', 'maxLength' => 'template_long' === $type ? 1000 : 300 );
		$props['title']['description']       = 'Título SEO. Admite variables como %title% %sep% %sitename%. Cadena vacía vuelve a la plantilla.';
		$props['description']['description'] = 'Meta descripción. Cadena vacía vuelve a la plantilla.';
		$props['keywords']['description']    = 'Keywords separadas por comas; la primera es la principal.';
		$props['schema_type']                = array( 'type' => 'string', 'enum' => self::SCHEMA_TYPES, 'description' => 'Tipo de schema del contenido; cadena vacía vuelve al automático.' );
		return array(
			'update-post-seo'    => array( 'Cambiar los metadatos SEO de un contenido', 'Cambia sólo los campos enviados (título SEO, descripción, keywords, canonical, noindex, nofollow, datos sociales, título de miga, tipo de schema). Cada campo pide su parte del permiso del sitio (títulos y descripciones; schema; sitemap e indexación): si una está cerrada no se cambia nada. Guarda los valores anteriores en el historial del contenido para poder deshacerlo. No cambia el texto, el estado ni el enlace del contenido.', array( 'type' => 'object', 'required' => array( 'post_id' ), 'properties' => $props, 'additionalProperties' => false ), array( __CLASS__, 'update_post_seo' ), array( __CLASS__, 'can_edit' ), array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ),
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

	/**
	 * Cambiar o deshacer metadatos: alguna de sus partes abierta (o el Asistente) y editar ese contenido. Cada campo
	 * enviado se comprueba después contra su parte: si una está cerrada, no se cambia nada.
	 */
	public static function can_edit( $input = array() ) { return self::open( array( 'meta', 'schema', 'index' ), array( 'update-post-seo', 'revert-post-seo' ) ) && Digitalisimo_Integrations_SEO_Abilities::can_edit( $input ); }
	public static function can_manage() { return self::open( array( 'redirects' ), array( 'create-redirection' ) ) && current_user_can( 'manage_options' ); }

	/** Una de las partes encendida o, sin ellas, una de estas abilities llamada desde el Asistente IA del escritorio. */
	private static function open( array $parts, array $names ) {
		foreach ( $parts as $part ) if ( self::part_enabled( $part ) ) return true;
		return '' !== self::assistant( $names );
	}

	/** La ability de `$names` que está ejecutando el Asistente IA del escritorio ahora ('' si ninguna). */
	public static function assistant( array $names = array( 'update-post-seo', 'revert-post-seo', 'create-redirection' ) ) {
		$context = 'Digitalisimo_AI_Assistant_Context';
		if ( ! class_exists( $context, false ) || ! method_exists( $context, 'allows' ) ) return '';
		foreach ( $names as $name ) if ( call_user_func( array( $context, 'allows' ), Digitalisimo_Integrations_SEO_Abilities::CATEGORY . '/' . $name ) ) return $name;
		return '';
	}

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
			case 'schema_type':
				// Igual que el selector del editor: un tipo fuera de la lista no se escribe.
				$value = sanitize_text_field( (string) $value );
				return in_array( $value, self::SCHEMA_TYPES, true ) ? $value : null;
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
		// Cada campo enviado pide su parte: con una cerrada no se cambia nada (ni lo de las partes abiertas).
		$closed = self::closed_parts( self::parts_of( array_keys( array_intersect_key( (array) $input, self::FIELDS ) ) ), 'update-post-seo' );
		if ( $closed ) return self::closed_error( $closed );
		$current = array();
		foreach ( array_keys( self::FIELDS ) as $field ) $current[ $field ] = (string) get_post_meta( $post->ID, 'digitalisimo_seo_' . $field, true );
		$changes = self::changes( $current, (array) $input );
		foreach ( $changes as $field => $pair ) self::write( $post->ID, $field, $pair[1] );
		if ( $changes ) {
			$history   = get_post_meta( $post->ID, self::HISTORY, true );
			$history   = is_array( $history ) ? $history : array();
			$history[] = array( 'time' => time(), 'user' => get_current_user_id(), 'via' => '' !== self::assistant( array( 'update-post-seo' ) ) ? 'assistant' : 'ability', 'before' => array_map( function( $pair ) { return $pair[0]; }, $changes ) );
			update_post_meta( $post->ID, self::HISTORY, array_slice( $history, -self::KEEP ) );
			self::log( 'update-post-seo', 'post', $post->ID, $changes );
		}
		$seo = Digitalisimo_Integrations_SEO_Abilities::get_post_seo( array( 'post_id' => $post->ID ) );
		return array( 'post_id' => $post->ID, 'changed' => array_keys( $changes ), 'seo' => $seo );
	}

	public static function revert_post_seo( $input ) {
		$post = self::post( $input );
		if ( ! $post ) return new WP_Error( 'digitalisimo_seo_not_found', 'No hay un contenido con SEO con ese ID.' );
		$history = get_post_meta( $post->ID, self::HISTORY, true );
		if ( ! is_array( $history ) || ! $history ) return new WP_Error( 'digitalisimo_seo_no_history', 'Este contenido no tiene cambios que deshacer.' );
		$last   = end( $history );
		$before = array_intersect_key( (array) ( $last['before'] ?? array() ), self::FIELDS );
		$closed = self::closed_parts( self::parts_of( array_keys( $before ) ), 'revert-post-seo' );
		if ( $closed ) return self::closed_error( $closed );
		array_pop( $history );
		$changes = array();
		foreach ( $before as $field => $value ) {
			$changes[ $field ] = array( (string) get_post_meta( $post->ID, 'digitalisimo_seo_' . $field, true ), (string) $value );
			self::write( $post->ID, $field, (string) $value );
		}
		$history ? update_post_meta( $post->ID, self::HISTORY, $history ) : delete_post_meta( $post->ID, self::HISTORY );
		if ( $changes ) self::log( 'revert-post-seo', 'post', $post->ID, $changes );
		return array( 'post_id' => $post->ID, 'restored' => array_keys( (array) ( $last['before'] ?? array() ) ), 'remaining' => count( $history ) );
	}

	/**
	 * Registro de cambios por parte: una entrada por parte tocada, con quién, por dónde (Asistente o cliente MCP), sobre
	 * qué y los valores de antes y de después (recortados). Sin autoload: sólo lo lee la pantalla de Indexación.
	 *
	 * @param array $changes Campo => array( anterior, nuevo ).
	 */
	public static function log( $ability, $object, $id, array $changes ) {
		$by_part = array();
		foreach ( $changes as $field => $pair ) {
			$part = 'create-redirection' === $ability ? 'redirects' : ( self::FIELD_PART[ $field ] ?? '' );
			if ( '' === $part ) continue;
			$clip = function( $v ) { $v = (string) $v; return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, 160, 'UTF-8' ) : substr( $v, 0, 160 ); };
			$by_part[ $part ][ $field ] = array( $clip( $pair[0] ), $clip( $pair[1] ) );
		}
		if ( ! $by_part ) return;
		$log = get_option( self::LOG, array() );
		$log = is_array( $log ) ? $log : array();
		$via = '' !== self::assistant( array( $ability ) ) ? 'assistant' : 'mcp';
		foreach ( $by_part as $part => $fields ) $log[] = array( 'time' => time(), 'user' => (int) get_current_user_id(), 'via' => $via, 'part' => $part, 'ability' => $ability, 'object' => $object, 'id' => (int) $id, 'fields' => $fields );
		update_option( self::LOG, array_slice( $log, -self::LOG_KEEP ), false );
	}

	/** Entradas del registro de una parte, de la más reciente a la más antigua. */
	public static function log_entries( $part, $limit = 10 ) {
		$log = get_option( self::LOG, array() );
		$log = is_array( $log ) ? array_reverse( $log ) : array();
		return array_slice( array_values( array_filter( $log, function( $row ) use ( $part ) { return is_array( $row ) && ( $row['part'] ?? '' ) === $part; } ) ), 0, (int) $limit );
	}

	/** Registro bajo los interruptores (sólo en el sitio: los cambios son de cada sitio). */
	public static function render_log() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		echo '<tr><th scope="row">Registro de cambios</th><td><p class="description">Lo último que cambiaron las abilities de escritura en este sitio, por parte: quién, desde dónde (el Asistente del escritorio o un cliente MCP externo) y qué había antes.</p>';
		foreach ( self::PARTS as $part => $def ) {
			$rows = self::log_entries( $part );
			echo '<h4 style="margin:12px 0 4px">' . esc_html( $def[1] ) . ' · ' . ( self::part_enabled( $part ) ? 'abierta a clientes MCP' : 'cerrada a clientes MCP' ) . '</h4>';
			if ( ! $rows ) { echo '<p class="description">Sin cambios registrados.</p>'; continue; }
			echo '<table class="widefat striped"><thead><tr><th>Fecha</th><th>Quién</th><th>Vía</th><th>Sobre</th><th>Cambio</th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$user  = (int) ( $row['user'] ?? 0 ) ? get_userdata( (int) $row['user'] ) : null;
				$what  = 'post' === ( $row['object'] ?? '' ) ? ( get_the_title( (int) $row['id'] ) ?: '#' . (int) $row['id'] ) : 'Redirección #' . (int) ( $row['id'] ?? 0 );
				$parts = array();
				foreach ( (array) ( $row['fields'] ?? array() ) as $field => $pair ) $parts[] = $field . ': «' . (string) ( $pair[0] ?? '' ) . '» → «' . (string) ( $pair[1] ?? '' ) . '»';
				echo '<tr><td>' . esc_html( wp_date( 'Y-m-d H:i', (int) ( $row['time'] ?? 0 ) ) ) . '</td><td>' . esc_html( $user ? $user->display_name : '—' ) . '</td><td>' . esc_html( 'assistant' === ( $row['via'] ?? '' ) ? 'Asistente' : 'Cliente MCP' ) . '</td><td>' . esc_html( $what ) . '</td><td>' . esc_html( implode( ' · ', $parts ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</td></tr>';
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
		self::log( 'create-redirection', 'redirection', (int) $id, array( 'source' => array( '', $source['pattern'] ), 'target' => array( '', (string) ( $input['target'] ?? '' ) ), 'code' => array( '', (string) $code ) ) );
		return array( 'id' => (int) $id, 'created' => true );
	}
}
