<?php
defined( 'ABSPATH' ) || exit;

/**
 * Auditor de Schema en SEO → Auditoría. Descarga el HTML final de una URL del
 * sitio, lee todos sus JSON-LD y los pasa por las mismas reglas que usan
 * Schema.org Validator y la prueba de resultados enriquecidos de Google. Pide
 * además la versión sin unificar para mostrar qué publicaban el tema y los
 * plugins y qué se corrigió. Sólo informa: no modifica contenido.
 */
class Digitalisimo_Integrations_Schema_Audit {
	const ACTION  = 'digitalisimo_schema_audit';
	const PREVIEW = 'digitalisimo_schema_preview';
	const OPTION  = 'digitalisimo_schema_audits';
	const KEEP    = 10;
	const ANCHOR  = 'digitalisimo-schema-audit';

	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'run' ) );
		add_action( 'wp_ajax_' . self::PREVIEW, array( __CLASS__, 'preview' ) );
	}

	/** El mismo sitio: el auditor nunca descarga direcciones ajenas. */
	public static function same_site( $url ) {
		$home = wp_parse_url( home_url( '/' ) );
		$part = wp_parse_url( (string) $url );
		if ( empty( $part['host'] ) || ! in_array( $part['scheme'] ?? '', array( 'http', 'https' ), true ) ) return false;
		if ( strtolower( $part['host'] ) !== strtolower( $home['host'] ?? '' ) ) return false;
		$base = rtrim( $home['path'] ?? '', '/' );
		return '' === $base || 0 === strpos( rtrim( $part['path'] ?? '/', '/' ) . '/', $base . '/' );
	}

	/** GET sin caché; repite sin verificar certificado sólo ante errores de certificado del loopback. */
	private static function fetch( $url ) {
		$args     = array( 'timeout' => 15, 'redirection' => 3, 'user-agent' => 'Digitalisimo Schema Audit/1.0', 'headers' => array( 'Cache-Control' => 'no-cache' ) );
		$response = wp_remote_get( $url, $args );
		$note     = '';
		if ( is_wp_error( $response ) && Digitalisimo_Integrations_LLMS::certificate_error( $response->get_error_message() ) ) {
			$note     = 'El servidor se conecta consigo mismo a través de un proxy con otro certificado; se descargó sin verificarlo (sólo lectura de una página pública de este sitio).';
			$response = wp_remote_get( $url, $args + array( 'sslverify' => false ) );
		}
		if ( is_wp_error( $response ) ) return array( 'code' => 0, 'body' => '', 'error' => $response->get_error_message(), 'note' => $note );
		return array( 'code' => (int) wp_remote_retrieve_response_code( $response ), 'body' => (string) wp_remote_retrieve_body( $response ), 'error' => '', 'note' => $note );
	}

	/** Contexto de la URL: qué debería tener según el sitio y su contenido. */
	private static function context_for( $url ) {
		$site = Digitalisimo_Integrations_Schema_Graph::site();
		$home = untrailingslashit( $url ) === untrailingslashit( home_url( '/' ) );
		$id   = $home ? (int) get_option( 'page_on_front' ) : url_to_postid( $url );
		$post = $id ? get_post( $id ) : null;
		$page = $post ? Digitalisimo_Integrations_Schema_Graph::post_page( $post ) : array( 'kind' => $home ? 'home' : 'archive', 'home' => $home, 'url' => $url, 'breadcrumbs' => array(), 'entity' => null );
		if ( $home ) { $page['kind'] = 'home'; $page['home'] = true; $page['breadcrumbs'] = array(); $page['entity'] = null; }
		return array( 'site' => $site, 'page' => $page, 'ctx' => array( 'url' => $url, 'home' => $home, 'expected' => Digitalisimo_Integrations_Schema_Graph::expected( $site, $page ) ) );
	}

	public static function audit( $url ) {
		$context = self::context_for( $url );
		$final   = self::fetch( $url );
		$record  = array( 'url' => $url, 'time' => time(), 'code' => $final['code'], 'error' => $final['error'], 'note' => $final['note'] );
		if ( $final['code'] < 200 || $final['code'] >= 300 ) {
			$record['report'] = null;
			return $record;
		}
		$blocks           = Digitalisimo_Integrations_Schema_Rules::blocks( $final['body'] );
		$record['report'] = Digitalisimo_Integrations_Schema_Rules::analyze_blocks( $blocks, Digitalisimo_Integrations_Schema_Rules::visible_text( $final['body'] ), $context['ctx'] );
		$record['graph']  = array();
		foreach ( $blocks as $block ) if ( is_array( $block['data'] ) ) $record['graph'][] = $block['data'];
		// La misma URL sin unificar: lo que publicaban el tema y los plugins.
		$token = wp_generate_password( 20, false );
		set_transient( Digitalisimo_Integrations_Schema_Graph::RAW_TOKEN, $token, 120 );
		$raw = self::fetch( add_query_arg( Digitalisimo_Integrations_Schema_Graph::RAW_QUERY, $token, $url ) );
		delete_transient( Digitalisimo_Integrations_Schema_Graph::RAW_TOKEN );
		if ( 200 === $raw['code'] ) {
			$raw_blocks    = Digitalisimo_Integrations_Schema_Rules::blocks( $raw['body'] );
			$raw_report    = Digitalisimo_Integrations_Schema_Rules::analyze_blocks( $raw_blocks, Digitalisimo_Integrations_Schema_Rules::visible_text( $raw['body'] ), $context['ctx'] );
			list( , $log ) = Digitalisimo_Integrations_Schema_Graph::unify( $raw['body'], $context['page'] );
			$record['raw'] = array( 'blocks' => count( $raw_blocks ), 'status' => $raw_report['status'], 'errors' => count( wp_list_filter( $raw_report['issues'], array( 'status' => Digitalisimo_Integrations_Schema_Rules::ERROR ) ) ), 'warnings' => count( wp_list_filter( $raw_report['issues'], array( 'status' => Digitalisimo_Integrations_Schema_Rules::WARNING ) ) ), 'log' => $log );
		}
		return $record;
	}

	public static function run() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( self::ACTION ) ) wp_die( 'No autorizado.' );
		$url = esc_url_raw( wp_unslash( $_POST['schema_url'] ?? '' ) );
		if ( '' !== trim( (string) ( $_POST['schema_custom_url'] ?? '' ) ) ) $url = esc_url_raw( wp_unslash( $_POST['schema_custom_url'] ) );
		$status = 'done';
		if ( ! self::same_site( $url ) ) $status = 'foreign';
		else {
			$all = (array) get_option( self::OPTION, array() );
			unset( $all[ $url ] );
			$all[ $url ] = self::audit( $url );
			update_option( self::OPTION, array_slice( $all, -self::KEEP, null, true ), false );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'digitalisimo-seo-audit', 'schema_url' => rawurlencode( $url ), 'schema_status' => $status ), admin_url( 'admin.php' ) ) . '#' . self::ANCHOR );
		exit;
	}

	/** Páginas principales del sitio para elegir sin escribir la URL. */
	private static function candidates() {
		$urls  = array( home_url( '/' ) => 'Portada' );
		$types = array_values( array_diff( Digitalisimo_Integrations_Editorial::content_types(), array( 'attachment' ) ) );
		foreach ( get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => 40, 'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => true ) ) as $post ) {
			$link = get_permalink( $post );
			if ( ! isset( $urls[ $link ] ) ) $urls[ $link ] = wp_strip_all_tags( get_the_title( $post ) ) . ' (' . $post->post_type . ')';
		}
		return $urls;
	}

	private static function badge( $status, $label = null ) {
		return Digitalisimo_Integrations_Quality_Audit::badge( $status, $label );
	}

	/** Enlaces a las herramientas oficiales con la URL ya cargada. */
	private static function external_tests( $url ) {
		return '<a class="button" href="' . esc_url( 'https://validator.schema.org/#url=' . rawurlencode( $url ) ) . '" target="_blank" rel="noopener">Abrir en Schema.org Validator</a> <a class="button" href="' . esc_url( 'https://search.google.com/test/rich-results?url=' . rawurlencode( $url ) ) . '" target="_blank" rel="noopener">Abrir en Google Rich Results</a>';
	}

	/** Tablas de un informe; compartidas por el auditor y la vista previa del editor. */
	public static function report_html( $report ) {
		$html = '<h4>Entidades</h4><table class="widefat striped"><thead><tr><th>Tipo</th><th>@id</th><th>Nombre</th></tr></thead><tbody>';
		foreach ( $report['entities'] as $e ) $html .= '<tr><td><code>' . esc_html( implode( ' + ', $e['types'] ) ?: '(sin @type)' ) . '</code></td><td><code>' . esc_html( $e['id'] ?: '—' ) . '</code></td><td>' . esc_html( $e['name'] ?: '—' ) . '</td></tr>';
		if ( ! $report['entities'] ) $html .= '<tr><td colspan="3">No se encontró ningún JSON-LD.</td></tr>';
		$html .= '</tbody></table>';
		if ( $report['rich'] ) {
			$html .= '<h4>Función de los schemas publicados</h4><p class="description">«Potencial» indica que el marcado cumple las reglas conocidas; Google decide si muestra un resultado enriquecido.</p><table class="widefat striped"><thead><tr><th>Tipo</th><th>Clase</th><th>Función</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>';
			foreach ( $report['rich'] as $r ) $html .= '<tr><td><code>' . esc_html( $r['type'] ) . '</code></td><td>' . esc_html( $r['category'] ?? 'Semántico' ) . '</td><td>' . esc_html( $r['feature'] ) . '</td><td>' . self::badge( $r['status'], Digitalisimo_Integrations_Schema_Rules::OK === $r['status'] ? 'POTENCIAL' : ( Digitalisimo_Integrations_Schema_Rules::NA === $r['status'] ? 'VÁLIDO' : 'NO APTO' ) ) . '</td><td>' . esc_html( $r['detail'] ) . '</td></tr>';
			$html .= '</tbody></table>';
		}
		if ( $report['missing'] ) {
			$html .= '<h4>Faltantes</h4><table class="widefat striped"><thead><tr><th>Estado</th><th>Tipo</th><th>Por qué</th></tr></thead><tbody>';
			foreach ( $report['missing'] as $m ) $html .= '<tr><td>' . self::badge( $m['status'] ) . '</td><td><code>' . esc_html( $m['type'] ) . '</code></td><td>' . esc_html( $m['why'] ) . '</td></tr>';
			$html .= '</tbody></table>';
		}
		$html .= '<h4>Qué corregir</h4>';
		if ( ! $report['issues'] ) return $html . '<p>' . self::badge( Digitalisimo_Integrations_Schema_Rules::OK ) . ' Sin errores ni advertencias.</p>';
		$order = array( Digitalisimo_Integrations_Schema_Rules::ERROR => 0, Digitalisimo_Integrations_Schema_Rules::WARNING => 1 );
		$issues = $report['issues'];
		usort( $issues, function( $a, $b ) use ( $order ) { return ( $order[ $a['status'] ] ?? 2 ) <=> ( $order[ $b['status'] ] ?? 2 ); } );
		$html .= '<table class="widefat striped"><thead><tr><th>Estado</th><th>Dónde</th><th>Detalle</th></tr></thead><tbody>';
		foreach ( $issues as $i ) $html .= '<tr><td>' . self::badge( $i['status'] ) . '</td><td><code>' . esc_html( $i['where'] ) . '</code></td><td>' . esc_html( $i['message'] ) . '</td></tr>';
		return $html . '</tbody></table>';
	}

	public static function render() {
		$all      = (array) get_option( self::OPTION, array() );
		$selected = isset( $_GET['schema_url'] ) ? esc_url_raw( rawurldecode( wp_unslash( $_GET['schema_url'] ) ) ) : '';
		echo '<div id="' . self::ANCHOR . '"><h3>Auditor de Schema</h3><p>Descarga el HTML final de una URL de este sitio y revisa todos sus JSON-LD: tipos y propiedades contra el vocabulario oficial de Schema.org, requisitos de resultados enriquecidos de Google, @id repetidos o contradictorios, entidades duplicadas y schemas faltantes. Sólo informa; no modifica el contenido.</p>';
		if ( 'foreign' === ( $_GET['schema_status'] ?? '' ) ) echo '<div class="notice notice-error inline"><p>Sólo se pueden auditar direcciones de este sitio.</p></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . self::ACTION . '">';
		wp_nonce_field( self::ACTION );
		echo '<p><label>Página <select name="schema_url">';
		foreach ( self::candidates() as $url => $label ) echo '<option value="' . esc_attr( $url ) . '" ' . selected( $selected, $url, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label> <label>u otra URL de este sitio <input type="url" class="regular-text" name="schema_custom_url" placeholder="' . esc_attr( home_url( '/…' ) ) . '"></label> ';
		submit_button( 'Auditar schema', 'primary', 'submit', false );
		echo '</p></form>';
		if ( ! $all ) { echo '</div>'; return; }
		echo '<table class="widefat striped"><thead><tr><th>URL</th><th>Estado</th><th>Bloques</th><th>Errores</th><th>Advertencias</th><th>Revisada</th></tr></thead><tbody>';
		foreach ( array_reverse( $all, true ) as $url => $record ) {
			$report = $record['report'] ?? null;
			$link   = add_query_arg( array( 'page' => 'digitalisimo-seo-audit', 'schema_url' => rawurlencode( $url ) ), admin_url( 'admin.php' ) ) . '#' . self::ANCHOR;
			$errors = $report ? count( wp_list_filter( array_merge( $report['issues'], $report['missing'] ), array( 'status' => Digitalisimo_Integrations_Schema_Rules::ERROR ) ) ) : 0;
			$warns  = $report ? count( wp_list_filter( array_merge( $report['issues'], $report['missing'] ), array( 'status' => Digitalisimo_Integrations_Schema_Rules::WARNING ) ) ) : 0;
			echo '<tr><td><a href="' . esc_url( $link ) . '">' . esc_html( $url ) . '</a></td><td>' . ( $report ? self::badge( $report['status'] ) : self::badge( Digitalisimo_Integrations_Schema_Rules::ERROR, 'HTTP ' . (int) $record['code'] ) ) . '</td><td>' . ( $report ? (int) $report['blocks'] : '—' ) . '</td><td>' . (int) $errors . '</td><td>' . (int) $warns . '</td><td>' . esc_html( wp_date( 'Y-m-d H:i', $record['time'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$record = $all[ $selected ] ?? end( $all );
		echo '<h3>' . esc_html( $record['url'] ) . '</h3><p>' . self::external_tests( $record['url'] ) . '</p>';
		if ( $record['note'] ) echo '<p class="description">' . esc_html( $record['note'] ) . '</p>';
		if ( empty( $record['report'] ) ) { echo '<p>' . self::badge( Digitalisimo_Integrations_Schema_Rules::ERROR, 'HTTP ' . (int) $record['code'] ) . ' ' . esc_html( $record['error'] ?: 'La URL no respondió con 200.' ) . '</p></div>'; return; }
		if ( ! empty( $record['raw'] ) ) {
			$raw = $record['raw'];
			echo '<h4>Antes de unificar</h4><p>El tema y los plugins publicaban <strong>' . (int) $raw['blocks'] . '</strong> bloque(s) JSON-LD con ' . (int) $raw['errors'] . ' error(es) y ' . (int) $raw['warnings'] . ' advertencia(s). ' . self::badge( $raw['status'] ) . '</p>';
			if ( $raw['log'] ) { echo '<ul style="list-style:disc;padding-left:20px">'; foreach ( $raw['log'] as $line ) echo '<li>' . esc_html( $line ) . '</li>'; echo '</ul>'; }
		}
		echo '<h4>Publicado ahora</h4><p>' . self::badge( $record['report']['status'] ) . ' ' . (int) $record['report']['blocks'] . ' bloque(s) JSON-LD en el HTML final.</p>';
		echo self::report_html( $record['report'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- report_html escapa cada valor.
		if ( ! empty( $record['graph'] ) ) echo '<details><summary><strong>JSON-LD publicado</strong></summary><pre style="max-height:420px;overflow:auto;white-space:pre-wrap;background:#fff;border:1px solid #dcdcde;padding:12px">' . esc_html( Digitalisimo_Integrations_Schema_Graph::json( 1 === count( $record['graph'] ) ? $record['graph'][0] : $record['graph'], true ) ) . '</pre></details>';
		echo '</div>';
	}

	/** Botón del editor: prueba el grafo de este contenido antes de publicarlo. */
	public static function editor_button( $post ) {
		echo '<div class="digitalisimo-snippet-wide digitalisimo-schema-preview"><button type="button" class="button" data-schema-preview="' . esc_attr( $post->ID ) . '" data-nonce="' . esc_attr( wp_create_nonce( self::PREVIEW . $post->ID ) ) . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '">Probar schema</button><small> Valida el grafo de este contenido con los datos guardados, aunque todavía no esté publicado.</small><div data-schema-preview-result></div></div>';
	}

	public static function preview() {
		$id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! check_ajax_referer( self::PREVIEW . $id, 'nonce', false ) ) wp_send_json_error( 'No autorizado.', 403 );
		$post = get_post( $id );
		$site = Digitalisimo_Integrations_Schema_Graph::site();
		$page = Digitalisimo_Integrations_Schema_Graph::post_page( $post );
		$visible = Digitalisimo_Integrations_Schema_Rules::normalize( apply_filters( 'the_content', $post->post_content ) );
		list( $graph, $log ) = Digitalisimo_Integrations_Schema_Graph::graph_for( $site, $page, $id, $visible );
		$doc    = array( '@context' => 'https://schema.org', '@graph' => $graph );
		$report = Digitalisimo_Integrations_Schema_Rules::analyze_graph( $doc, array( 'url' => $page['url'], 'home' => ! empty( $page['home'] ), 'expected' => Digitalisimo_Integrations_Schema_Graph::expected( $site, $page ) ), $visible );
		$html   = '<p>' . self::badge( $report['status'] ) . ' Grafo de Digitalísimo para este contenido. Las FAQ u otros schemas que añadan el tema o los plugins se integran al publicarse; revísalos después en SEO → Auditoría.</p>';
		if ( $log ) { $html .= '<ul style="list-style:disc;padding-left:20px">'; foreach ( $log as $line ) $html .= '<li>' . esc_html( $line ) . '</li>'; $html .= '</ul>'; }
		$html .= self::report_html( $report );
		$html .= '<p><strong>JSON-LD</strong> · cópialo en Schema.org Validator o en Rich Results («Código») para probarlo antes de publicar.</p><textarea readonly rows="10" class="large-text code" onclick="this.select()">' . esc_textarea( Digitalisimo_Integrations_Schema_Graph::json( $doc, true ) ) . '</textarea>';
		$html .= '<p><a class="button" href="https://validator.schema.org/" target="_blank" rel="noopener">Schema.org Validator</a> <a class="button" href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">Google Rich Results</a></p>';
		wp_send_json_success( $html );
	}
}
