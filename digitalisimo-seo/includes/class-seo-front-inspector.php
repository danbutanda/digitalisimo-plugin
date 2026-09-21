<?php
defined( 'ABSPATH' ) || exit;

/** Lee una URL pública propia y presenta las señales SEO que realmente devolvió el front. */
class Digitalisimo_Integrations_SEO_Front_Inspector {
	private static function same_site_url( $url ) {
		$parts = wp_parse_url( $url ); $home = wp_parse_url( home_url( '/' ) );
		return ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) && empty( $parts['user'] ) && empty( $parts['pass'] ) && isset( $home['host'] ) && strtolower( $parts['host'] ) === strtolower( $home['host'] ) && in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true );
	}
	private static function resolve_url( $base, $location ) {
		$location = trim( html_entity_decode( (string) $location, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		if ( preg_match( '#^https?://#i', $location ) ) return $location;
		$parts = wp_parse_url( $base ); $origin = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' ) . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
		if ( 0 === strpos( $location, '//' ) ) return ( $parts['scheme'] ?? 'https' ) . ':' . $location;
		if ( 0 === strpos( $location, '/' ) ) return $origin . $location;
		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		return $origin . trailingslashit( dirname( $path ) ) . $location;
	}
	private static function fetch( $url ) {
		$current = $url; $redirects = array(); $insecure_tls = false;
		for ( $step = 0; $step < 6; $step++ ) {
			if ( ! self::same_site_url( $current ) ) return new WP_Error( 'digitalisimo_front_url', 'La URL y sus redirecciones deben permanecer en este mismo sitio.' );
			$response = wp_safe_remote_get( $current, array( 'timeout' => 15, 'redirection' => 0, 'limit_response_size' => 1048576, 'user-agent' => 'Digitalisimo Front Inspector/1.0', 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
			if ( is_wp_error( $response ) && ( false !== stripos( $response->get_error_message(), 'cURL error 60' ) || false !== stripos( $response->get_error_message(), 'certificate' ) ) ) {
				$response = wp_safe_remote_get( $current, array( 'timeout' => 15, 'redirection' => 0, 'limit_response_size' => 1048576, 'sslverify' => false, 'user-agent' => 'Digitalisimo Front Inspector/1.0', 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
				$insecure_tls = ! is_wp_error( $response );
			}
			if ( is_wp_error( $response ) ) return $response;
			$code = (int) wp_remote_retrieve_response_code( $response ); $location = wp_remote_retrieve_header( $response, 'location' );
			if ( $code >= 300 && $code < 400 && $location ) { $next = self::resolve_url( $current, $location ); $redirects[] = array( 'code' => $code, 'from' => $current, 'to' => $next ); $current = $next; continue; }
			return array( 'url' => $current, 'response' => $response, 'redirects' => $redirects, 'insecure_tls' => $insecure_tls );
		}
		return new WP_Error( 'digitalisimo_front_redirect', 'La URL superó el máximo de cinco redirecciones.' );
	}
	private static function head( $html ) { return preg_match( '#<head\b[^>]*>(.*?)</head\s*>#is', $html, $match ) ? $match[1] : ''; }
	private static function attributes( $tag ) {
		$attrs = array(); preg_match_all( '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/s', $tag, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) $attrs[ strtolower( $match[1] ) ] = html_entity_decode( $match[2] !== '' ? $match[2] : ( $match[3] !== '' ? $match[3] : $match[4] ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		return $attrs;
	}
	private static function tags( $html, $name ) { preg_match_all( '#<' . preg_quote( $name, '#' ) . '\b[^>]*>#is', $html, $matches ); return $matches[0]; }
	private static function meta( $head ) {
		$meta = array(); foreach ( self::tags( $head, 'meta' ) as $tag ) { $attrs = self::attributes( $tag ); $key = strtolower( $attrs['name'] ?? ( $attrs['property'] ?? ( $attrs['http-equiv'] ?? '' ) ) ); if ( $key ) { if ( ! isset( $meta[ $key ] ) ) $meta[ $key ] = array(); $meta[ $key ][] = $attrs['content'] ?? ''; } } return $meta;
	}
	private static function links( $head ) {
		$links = array(); foreach ( self::tags( $head, 'link' ) as $tag ) { $attrs = self::attributes( $tag ); if ( ! empty( $attrs['rel'] ) ) $links[] = $attrs; } return $links;
	}
	private static function schema_types( $value, &$types ) {
		if ( ! is_array( $value ) ) return;
		if ( ! empty( $value['@type'] ) ) foreach ( (array) $value['@type'] as $type ) $types[] = (string) $type;
		foreach ( $value as $item ) self::schema_types( $item, $types );
	}
	private static function schema_ids( $value, &$ids ) {
		if ( ! is_array( $value ) ) return;
		// Un @id sin @type es una referencia a una entidad, no una segunda definición.
		if ( ! empty( $value['@id'] ) && ! empty( $value['@type'] ) ) $ids[] = (string) $value['@id'];
		foreach ( $value as $item ) self::schema_ids( $item, $ids );
	}
	private static function schemas( $head ) {
		$out = array(); preg_match_all( '#<script\b[^>]*type\s*=\s*(["\'])application/ld\+json\1[^>]*>(.*?)</script\s*>#is', $head, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) { $raw = trim( html_entity_decode( $match[2], ENT_NOQUOTES, get_bloginfo( 'charset' ) ) ); $data = json_decode( $raw, true ); $types = array(); $ids = array(); if ( JSON_ERROR_NONE === json_last_error() ) { self::schema_types( $data, $types ); self::schema_ids( $data, $ids ); } $out[] = array( 'valid' => JSON_ERROR_NONE === json_last_error(), 'error' => json_last_error_msg(), 'types' => array_values( array_unique( array_filter( $types ) ) ), 'ids' => array_values( array_filter( $ids ) ) ); }
		return $out;
	}
	private static function values( $items ) { return array_values( array_filter( array_map( 'trim', (array) $items ), 'strlen' ) ); }
	private static function item( $label, $values, $empty = 'No detectado' ) { echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . ( $values ? esc_html( implode( "\n", $values ) ) : '<span class="digitalisimo-front-missing">' . esc_html( $empty ) . '</span>' ) . '</td></tr>'; }
	private static function configured( $key ) { return (bool) Digitalisimo_Integrations_Settings::get( $key ); }
	public static function render() {
		$url = home_url( '/' ); $report = null; $error = '';
		if ( isset( $_POST['digitalisimo_front_inspect'] ) && check_admin_referer( 'digitalisimo_front_inspect' ) ) {
			$url = esc_url_raw( wp_unslash( $_POST['digitalisimo_front_url'] ?? '' ) );
			if ( ! self::same_site_url( $url ) ) $error = 'Escribe una URL pública de este mismo sitio.';
			else { $fetched = self::fetch( $url ); if ( is_wp_error( $fetched ) ) $error = $fetched->get_error_message(); else $report = $fetched; }
		}
		echo '<section class="digitalisimo-front-inspector"><h2>SEO Front</h2><p>Lee la respuesta pública y anónima de una URL de este sitio. Muestra las señales que están realmente en el HTML; no cambia contenido ni configuración.</p><form method="post" class="digitalisimo-front-form">'; wp_nonce_field( 'digitalisimo_front_inspect' ); echo '<label for="digitalisimo_front_url">URL pública</label><input id="digitalisimo_front_url" class="large-text" name="digitalisimo_front_url" type="url" required value="' . esc_attr( $url ) . '">'; submit_button( 'Inspeccionar front', 'primary', 'digitalisimo_front_inspect', false ); echo '</form>';
		if ( $error ) { echo '<div class="notice notice-error inline"><p>' . esc_html( $error ) . '</p></div></section>'; return; }
		if ( ! $report ) { echo '<p class="description">Selecciona una página, entrada o producto publicado para revisar title, metas, social, canonical, robots y Schema tal como salen al front.</p></section>'; return; }
		$response = $report['response']; $body = (string) wp_remote_retrieve_body( $response ); $head = self::head( $body ); $meta = self::meta( $head ); $links = self::links( $head ); $schemas = self::schemas( $head ); $canonicals = array(); $hreflangs = array(); $pagination = array();
		if ( ! empty( $report['insecure_tls'] ) ) echo '<div class="notice notice-warning inline"><p>El servidor que ejecuta WordPress no pudo validar el certificado SSL de este mismo dominio y se consultó únicamente esta URL propia sin esa validación. El informe se generó; revisa la resolución DNS, SNI o cadena de certificados desde el hosting. Esto no confirma por sí solo un fallo para visitantes externos.</p></div>';
		foreach ( $links as $link ) { $rels = preg_split( '/\s+/', strtolower( $link['rel'] ) ); if ( in_array( 'canonical', $rels, true ) && ! empty( $link['href'] ) ) $canonicals[] = $link['href']; if ( in_array( 'alternate', $rels, true ) && ! empty( $link['hreflang'] ) && ! empty( $link['href'] ) ) $hreflangs[] = $link['hreflang'] . ': ' . $link['href']; if ( ( in_array( 'next', $rels, true ) || in_array( 'prev', $rels, true ) ) && ! empty( $link['href'] ) ) $pagination[] = implode( ' ', $rels ) . ': ' . $link['href']; }
		preg_match_all( '#<title\b[^>]*>(.*?)</title\s*>#is', $head, $titles ); $titles = array_map( function( $title ) { return trim( wp_strip_all_tags( html_entity_decode( $title, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) ); }, $titles[1] ?? array() ); $charsets = array(); foreach ( self::tags( $head, 'meta' ) as $tag ) { $attrs = self::attributes( $tag ); if ( ! empty( $attrs['charset'] ) ) $charsets[] = $attrs['charset']; }
		preg_match( '#<html\b[^>]*>#i', $body, $html_tag ); $html_attrs = ! empty( $html_tag[0] ) ? self::attributes( $html_tag[0] ) : array(); $headings = array(); preg_match_all( '#<h([1-6])\b[^>]*>(.*?)</h\1\s*>#is', $body, $heading_matches, PREG_SET_ORDER ); foreach ( $heading_matches as $heading ) { $text = trim( wp_strip_all_tags( html_entity_decode( $heading[2], ENT_QUOTES, get_bloginfo( 'charset' ) ) ) ); if ( $text ) $headings[] = array( 'level' => 'H' . $heading[1], 'text' => $text ); } $h1_count = count( array_filter( $headings, function( $heading ) { return 'H1' === $heading['level']; } ) ); $images = self::tags( $body, 'img' ); $missing_alt = 0; foreach ( $images as $image ) { $attrs = self::attributes( $image ); if ( ! array_key_exists( 'alt', $attrs ) ) $missing_alt++; } $anchors = self::tags( $body, 'a' ); $internal_links = 0; $external_links = 0; $home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ); foreach ( $anchors as $anchor ) { $attrs = self::attributes( $anchor ); $href = trim( $attrs['href'] ?? '' ); if ( ! $href || 0 === strpos( $href, '#' ) || preg_match( '#^(mailto:|tel:|javascript:)#i', $href ) ) continue; $host = strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) ); if ( ! $host || $host === $home_host ) $internal_links++; else $external_links++; }
		$schema_types = array(); $schema_ids = array(); $invalid_schema = 0; foreach ( $schemas as $schema ) { $schema_types = array_merge( $schema_types, $schema['types'] ); $schema_ids = array_merge( $schema_ids, $schema['ids'] ); if ( ! $schema['valid'] ) $invalid_schema++; } $schema_types = array_values( array_unique( $schema_types ) ); $duplicate_schema_ids = array_keys( array_filter( array_count_values( $schema_ids ), function( $count ) { return $count > 1; } ) );
		echo '<h3>Respuesta pública</h3><table class="widefat striped digitalisimo-front-table"><tbody>'; self::item( 'Estado HTTP', array( 'HTTP ' . (int) wp_remote_retrieve_response_code( $response ) . ' · ' . $report['url'] ) ); self::item( 'Redirecciones', array_map( function( $redirect ) { return 'HTTP ' . $redirect['code'] . ': ' . $redirect['from'] . ' → ' . $redirect['to']; }, $report['redirects'] ), 'Sin redirecciones' ); self::item( 'Content-Type', self::values( array( wp_remote_retrieve_header( $response, 'content-type' ) ) ) ); self::item( 'X-Robots-Tag', self::values( array( wp_remote_retrieve_header( $response, 'x-robots-tag' ) ) ), 'No enviado como encabezado HTTP' ); echo '</tbody></table>';
		echo '<h3>SEO en el HTML</h3><table class="widefat striped digitalisimo-front-table"><tbody>'; self::item( 'Idioma HTML', self::values( array( $html_attrs['lang'] ?? '' ) ) ); self::item( 'Charset', self::values( $charsets ) ); self::item( 'Viewport', self::values( $meta['viewport'] ?? array() ) ); self::item( 'Title (' . count( $titles ) . ')', self::values( $titles ) ); self::item( 'Meta description (' . count( $meta['description'] ?? array() ) . ')', self::values( $meta['description'] ?? array() ) ); self::item( 'Meta keywords (' . count( $meta['keywords'] ?? array() ) . ')', self::values( $meta['keywords'] ?? array() ) ); self::item( 'Canonical (' . count( $canonicals ) . ')', self::values( $canonicals ) ); self::item( 'Robots', self::values( array_merge( $meta['robots'] ?? array(), $meta['googlebot'] ?? array() ) ) ); self::item( 'Hreflang', self::values( $hreflangs ) ); self::item( 'Paginación', self::values( $pagination ) ); echo '</tbody></table>';
		echo '<h3>Índice de encabezados</h3><p class="description">Titulares detectados en el cuerpo publicado, en el mismo orden que recibe el visitante.</p><table class="widefat striped digitalisimo-front-table"><thead><tr><th>Tipo</th><th>Título</th></tr></thead><tbody>'; foreach ( $headings as $heading ) echo '<tr><td><strong>' . esc_html( $heading['level'] ) . '</strong></td><td>' . esc_html( $heading['text'] ) . '</td></tr>'; if ( ! $headings ) echo '<tr><td colspan="2">No se detectaron encabezados H1–H6.</td></tr>'; echo '</tbody></table>';
		echo '<h3>Contenido y enlaces</h3><table class="widefat striped digitalisimo-front-table"><tbody>'; self::item( 'Imágenes', array( count( $images ) . ' detectadas; ' . $missing_alt . ' sin atributo alt' ) ); self::item( 'Enlaces', array( $internal_links . ' internos; ' . $external_links . ' externos' ) ); self::item( 'Verificación de propiedad', self::values( array_merge( $meta['google-site-verification'] ?? array(), $meta['msvalidate.01'] ?? array(), $meta['facebook-domain-verification'] ?? array() ) ) ); echo '</tbody></table>';
		echo '<h3>Social publicado</h3><table class="widefat striped digitalisimo-front-table"><tbody>'; self::item( 'Open Graph', self::values( array_merge( $meta['og:title'] ?? array(), $meta['og:description'] ?? array(), $meta['og:image'] ?? array(), $meta['og:url'] ?? array(), $meta['og:type'] ?? array() ) ) ); self::item( 'X / Twitter', self::values( array_merge( $meta['twitter:card'] ?? array(), $meta['twitter:title'] ?? array(), $meta['twitter:description'] ?? array(), $meta['twitter:image'] ?? array() ) ) ); echo '</tbody></table>';
		echo '<h3>Schema JSON-LD</h3><table class="widefat striped digitalisimo-front-table"><tbody>'; self::item( 'Bloques detectados', array( count( $schemas ) . ' bloque(s); ' . $invalid_schema . ' con JSON inválido' ) ); self::item( 'Tipos detectados', self::values( $schema_types ) ); self::item( 'IDs repetidos', self::values( $duplicate_schema_ids ), 'No se detectaron IDs de Schema duplicados' ); echo '</tbody></table>';
		$warnings = array(); if ( 1 !== count( $titles ) ) $warnings[] = 'Debe haber un solo title; se detectaron ' . count( $titles ) . '.'; if ( 1 !== $h1_count ) $warnings[] = 'Se recomienda un solo H1; se detectaron ' . $h1_count . '.'; if ( count( $meta['description'] ?? array() ) > 1 ) $warnings[] = 'Hay más de una meta description.'; if ( count( $canonicals ) > 1 ) $warnings[] = 'Hay más de una URL canonical.'; if ( $invalid_schema ) $warnings[] = 'Hay ' . $invalid_schema . ' bloque(s) Schema con JSON inválido.'; if ( $duplicate_schema_ids ) $warnings[] = 'Hay IDs de Schema repetidos; otro plugin o el tema puede estar emitiendo la misma entidad.'; if ( $missing_alt ) $warnings[] = 'Hay ' . $missing_alt . ' imágenes sin atributo alt.'; if ( self::configured( 'seo_open_graph' ) && empty( $meta['og:title'] ) ) $warnings[] = 'Open Graph está activo en Digitalisimo, pero og:title no se detectó en el front.'; if ( self::configured( 'seo_schema_enabled' ) && ! $schemas ) $warnings[] = 'Schema está activo en Digitalisimo, pero no se detectó JSON-LD en el front.';
		echo '<h3>Contraste con Digitalisimo</h3><ul class="digitalisimo-front-warnings">'; if ( $warnings ) foreach ( $warnings as $warning ) echo '<li>⚠ ' . esc_html( $warning ) . '</li>'; else echo '<li>✓ No se detectaron duplicados en title, description o canonical, y las señales activas de Digitalisimo se reflejan en esta respuesta.</li>'; echo '</ul><details><summary><strong>Ver el &lt;head&gt; recibido</strong></summary><pre class="digitalisimo-front-head">' . esc_html( $head ) . '</pre></details></section>';
	}
}
