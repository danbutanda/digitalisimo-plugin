<?php
defined( 'ABSPATH' ) || exit;

/** Motor IA compartido por los módulos Digitalisimo. */
class Digitalisimo_AI {
	const OPTION = 'digitalisimo_ai_options';
	const LOG_OPTION = 'digitalisimo_ai_usage_log';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_shortcode( 'digitalisimo_chatbot', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'content_metabox' ) );
	}

	private static function defaults() {
		return array(
			'inherit_network' => 0, 'chat_enabled' => 0, 'chat_profile' => 'chatbot', 'context_posts' => 5,
			'knowledge_enabled' => 0, 'knowledge_auto_index' => 1, 'knowledge_results' => 4, 'knowledge_embeddings_enabled' => 0, 'knowledge_embeddings_model' => 'text-embedding-3-small', 'knowledge_semantic_rerank' => 0,
			'image_size' => '1536x1024', 'image_style' => 'natural', 'image_quality' => 'medium', 'mcp_featured_image_enabled' => 0, 'log_retention' => 30,
			'editorial_voice' => '', 'editorial_context' => '', 'editorial_cta' => '', 'editorial_method' => '',
			'providers' => array(),
			'profiles' => array(
				'chatbot' => array( 'provider' => 'openai', 'model' => 'gpt-4o-mini', 'fallback' => '' ),
				'content' => array( 'provider' => 'openai', 'model' => 'gpt-4o-mini', 'fallback' => '' ),
				'seo' => array( 'provider' => 'openai', 'model' => 'gpt-4o-mini', 'fallback' => '' ),
				'ecommerce' => array( 'provider' => 'openai', 'model' => 'gpt-4o-mini', 'fallback' => '' ),
				'automation' => array( 'provider' => 'openai', 'model' => 'gpt-4o-mini', 'fallback' => '' ),
				'images' => array( 'provider' => 'openai', 'model' => 'gpt-image-1', 'fallback' => '' ),
			),
		);
	}

	/** Los sitios nuevos de una red heredan IA hasta que el administrador elige personalizarlos. */
	private static function local_options() { $options = (array) get_option( self::OPTION, array() ); if ( is_multisite() && ! array_key_exists( 'inherit_network', $options ) ) $options['inherit_network'] = 1; return $options; }
	public static function get( $key = null ) {
		$local = self::local_options();
		$raw = $local;
		if ( is_multisite() && ! empty( $local['inherit_network'] ) ) $raw = (array) get_site_option( self::OPTION, array() );
		$value = wp_parse_args( $raw, self::defaults() );
		return null === $key ? $value : ( $value[ $key ] ?? null );
	}
	private static function origin() { return is_multisite() && ! empty( self::local_options()['inherit_network'] ) ? 'Configuración de red' : 'Este sitio'; }

	public static function menu() { add_submenu_page( null, 'Digitalisimo IA Tools', 'IA Tools', 'manage_options', 'digitalisimo-ai', array( __CLASS__, 'page' ) ); }

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$saved = isset( $_POST['digitalisimo_ai_save'] ) && check_admin_referer( 'digitalisimo_ai_save' );
		if ( $saved ) self::save_site();
		$tab = sanitize_key( $_GET['tab'] ?? 'general' );
		$tabs = array( 'general' => 'General', 'providers' => 'Proveedores', 'profiles' => 'Perfiles de uso', 'writing' => 'Redacción', 'chat' => 'Chatbot', 'knowledge' => 'Conocimiento del sitio', 'images' => 'Imágenes', 'advanced' => 'Avanzado' );
		$o = self::get();
		echo '<div class="wrap"><h1>Digitalisimo IA Tools</h1><p>Herramientas de IA compartidas para chatbot, contenido, SEO, ecommerce, conocimiento e imágenes. Generan bajo demanda y nunca publican ni modifican contenido automáticamente.</p>';
		if ( $saved ) echo '<div class="notice notice-success is-dismissible"><p>Configuración guardada.</p></div>';
		if ( is_multisite() ) echo '<div class="notice notice-info inline"><p><strong>Valor efectivo:</strong> ' . esc_html( self::origin() ) . '. Puedes elegir heredar los ajustes completos de la red o usar los de este sitio.</p></div>';
		echo '<h2 class="nav-tab-wrapper">'; foreach ( $tabs as $id => $name ) echo '<a class="nav-tab ' . ( $id === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-ai&tab=' . $id ) ) . '">' . esc_html( $name ) . '</a>'; echo '</h2><form method="post">'; wp_nonce_field( 'digitalisimo_ai_save' );
		if ( 'general' === $tab ) {
		if ( is_multisite() ) self::check( 'inherit_network', 'Heredar toda la configuración de IA de la red', self::local_options() );
			self::check( 'chat_enabled', 'Activar Digitalisimo IA Tools', $o ); self::check( 'knowledge_enabled', 'Activar base de conocimiento del sitio', $o ); self::check( 'mcp_featured_image_enabled', 'Permitir que Codex suba imágenes destacadas mediante MCP', $o );
			echo '<p>Perfiles disponibles: chatbot, creación de contenido, SEO, ecommerce, automatizaciones e imágenes.</p>';
		} elseif ( 'providers' === $tab ) self::providers( $o );
		elseif ( 'profiles' === $tab ) self::profiles( $o );
		elseif ( 'writing' === $tab ) { echo '<h3>Redacción editorial</h3><p>Este método guía la creación interna, el publisher y los clientes MCP. SEO conserva las señales técnicas; IA Tools administra la voz, el CTA, el chat editorial y los borradores.</p>'; Digitalisimo_AI_Content_Playbook::fields(); }
		elseif ( 'chat' === $tab ) { self::check( 'chat_enabled', 'Activar chatbot público', $o ); self::input( 'context_posts', 'Entradas públicas como contexto', $o, 'number' ); echo '<p><code>[digitalisimo_chatbot]</code> añade el chat donde tú lo decidas. No registra prompts ni direcciones IP.</p>'; }
		elseif ( 'knowledge' === $tab ) { self::knowledge_fields( $o ); }
			elseif ( 'images' === $tab ) { self::image_fields( $o ); }
		else { self::input( 'log_retention', 'Retención de métricas (días)', $o, 'number' ); echo '<p>Los registros contienen fecha, perfil, proveedor y resultado. No se guardan claves, prompts, respuestas, IPs ni conversaciones.</p>'; }
		submit_button( 'Guardar configuración' ); echo '</form></div>';
	}

	public static function network_page() {
		if ( ! current_user_can( 'manage_network_options' ) || ! is_super_admin() ) return;
		if ( isset( $_POST['digitalisimo_ai_network_save'] ) && check_admin_referer( 'digitalisimo_ai_network_save' ) ) self::save_network();
		$o = wp_parse_args( (array) get_site_option( self::OPTION, array() ), self::defaults() );
		echo '<div class="wrap"><h1>Digitalisimo · IA Tools de red</h1><p>Estos valores son dinámicos: todos los sitios que elijan heredarlos reflejarán los cambios sin copiar credenciales.</p>'; if ( isset( $_POST['digitalisimo_ai_network_save'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Configuración de red guardada.</p></div>'; echo '<form method="post">'; wp_nonce_field( 'digitalisimo_ai_network_save' );
			self::check( 'chat_enabled', 'Activar motor IA por defecto', $o ); self::check( 'mcp_featured_image_enabled', 'Permitir imágenes destacadas por MCP', $o ); self::check( 'knowledge_enabled', 'Activar base de conocimiento por defecto', $o ); self::check( 'knowledge_embeddings_enabled', 'Permitir embeddings opcionales de OpenAI', $o ); self::check( 'knowledge_semantic_rerank', 'Reordenar conocimiento con similitud semántica', $o ); self::input( 'knowledge_embeddings_model', 'Modelo de embeddings', $o ); self::input( 'knowledge_results', 'Resultados de conocimiento por consulta', $o, 'number' ); self::input( 'context_posts', 'Entradas públicas como contexto', $o, 'number' ); echo '<h2>Imágenes</h2>'; self::image_fields( $o ); echo '<h2>Redacción editorial</h2>'; Digitalisimo_AI_Content_Playbook::fields( true ); self::providers( $o ); self::profiles( $o ); self::input( 'log_retention', 'Retención de métricas (días)', $o, 'number' );
		submit_button( 'Guardar configuración de red', 'primary', 'digitalisimo_ai_network_save' ); echo '</form></div>';
	}

	private static function check( $key, $label, $o ) { echo '<p><label><input type="hidden" name="ai[' . esc_attr( $key ) . ']" value="0"><input type="checkbox" name="ai[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $o[ $key ] ), true, false ) . '> <strong>' . esc_html( $label ) . '</strong></label></p>'; }
	private static function input( $key, $label, $o, $type = 'text' ) { echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input class="regular-text" type="' . esc_attr( $type ) . '" name="ai[' . esc_attr( $key ) . ']" value="' . esc_attr( $o[ $key ] ?? '' ) . '"></label></p>'; }
	private static function knowledge_fields( $o ) {
		self::check( 'knowledge_enabled', 'Activar base de conocimiento', $o );
		self::check( 'knowledge_auto_index', 'Actualizar al guardar contenido público', $o );
		self::input( 'knowledge_results', 'Fuentes relevantes por consulta', $o, 'number' );
		self::check( 'knowledge_embeddings_enabled', 'Activar embeddings opcionales de OpenAI', $o ); self::check( 'knowledge_semantic_rerank', 'Reordenar resultados con similitud semántica', $o ); self::input( 'knowledge_embeddings_model', 'Modelo de embeddings', $o );
		$count = class_exists( 'Digitalisimo_AI_Knowledge' ) ? Digitalisimo_AI_Knowledge::count() : 0;
		echo '<p><strong>Estado:</strong> ' . esc_html( $count ) . ' contenidos públicos indexados.</p><p>Digitalisimo usa una recuperación contextual local (RAG): toma sólo contenido público, no guarda prompts ni conversaciones, y no entrena modelos externos ni modifica contenido.</p>';
		echo '<p><a class="button button-secondary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=digitalisimo_ai_knowledge_rebuild' ), 'digitalisimo_ai_knowledge_rebuild' ) ) . '">Reconstruir base de conocimiento</a></p>';
		if ( isset( $_GET['rebuilt'] ) ) echo '<div class="notice notice-success inline"><p>Base de conocimiento reconstruida.</p></div>';
		echo '<p class="description">Al generar embeddings se enviará una representación del contenido público indexado a OpenAI. Es opcional, manual y puede generar coste; no equivale a entrenar un modelo. El reordenamiento semántico, si se activa, envía sólo la consulta a OpenAI y guarda su representación en caché durante una hora.</p><p><a class="button button-secondary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=digitalisimo_ai_knowledge_embeddings' ), 'digitalisimo_ai_knowledge_embeddings' ) ) . '">Generar embeddings (máx. 20)</a></p>';
		if ( isset( $_GET['embeddings'] ) ) { $result = (array) get_option( 'digitalisimo_ai_knowledge_embeddings_result', array() ); echo '<div class="notice ' . ( ! empty( $result['ok'] ) ? 'notice-success' : 'notice-warning' ) . ' inline"><p>' . esc_html( $result['message'] ?? '' ) . '</p></div>'; }
		$sources = class_exists( 'Digitalisimo_AI_Knowledge' ) ? Digitalisimo_AI_Knowledge::sources() : array();
		echo '<h2>Fuentes indexadas</h2><p class="description">Vista administrativa del índice local. No muestra el contenido completo ni lo envía a proveedores de IA.</p><table class="widefat striped"><thead><tr><th>Título</th><th>Tipo</th><th>Última modificación</th><th>URL</th></tr></thead><tbody>';
		if ( ! $sources ) echo '<tr><td colspan="4">Aún no hay contenido indexado. Activa la base y usa Reconstruir.</td></tr>';
		foreach ( array_slice( $sources, 0, 100 ) as $source ) echo '<tr><td>' . esc_html( $source['title'] ?? '' ) . '</td><td>' . esc_html( $source['type'] ?? '' ) . '</td><td>' . esc_html( ! empty( $source['modified'] ) ? mysql2date( 'Y-m-d H:i', $source['modified'] ) : '' ) . '</td><td><a href="' . esc_url( $source['url'] ?? '' ) . '" target="_blank" rel="noopener">Ver contenido</a></td></tr>';
		echo '</tbody></table>';
	}
	/** Configuración visible de imagen: el perfil también permanece disponible en Perfiles de uso. */
	private static function image_fields( $o ) {
		$profile = (array) ( $o['profiles']['images'] ?? array() );
		$model = $profile['model'] ?? 'gpt-image-1';
		$quality = $o['image_quality'] ?? 'medium';
		echo '<h3>Imágenes de artículos</h3><p>El modelo se configura aquí para no tener que buscarlo entre los perfiles. La imagen se genera a 1536 × 1024 y se entrega como WebP horizontal 16:9 de 1536 × 864 px.</p>';
		echo '<p><label><strong>Modelo de generación</strong><br><input type="hidden" name="profiles[images][provider]" value="openai"><input type="hidden" name="profiles[images][fallback]" value="' . esc_attr( $profile['fallback'] ?? '' ) . '"><select name="profiles[images][model]">';
		foreach ( array( 'gpt-image-1.5' => 'GPT Image 1.5 · mejor seguimiento de instrucciones', 'gpt-image-1' => 'GPT Image 1 · calidad estable', 'gpt-image-1-mini' => 'GPT Image 1 Mini · menor costo', 'gpt-image-2' => 'GPT Image 2', 'gpt-image-2.5-flare' => 'GPT Image 2.5 Flare · rápida', 'gpt-image-2.5-sunburst' => 'GPT Image 2.5 Sunburst · máxima precisión' ) as $id => $label ) echo '<option value="' . esc_attr( $id ) . '" ' . selected( $model, $id, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label></p><p><label><strong>Calidad de generación</strong><br><select name="ai[image_quality]">';
		foreach ( array( 'low' => 'Baja · menor costo', 'medium' => 'Media · recomendada', 'high' => 'Alta', 'xhigh' => 'Muy alta · sólo modelos 2.5', 'max' => 'Máxima · sólo modelos 2.5' ) as $id => $label ) echo '<option value="' . esc_attr( $id ) . '" ' . selected( $quality, $id, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label></p>';
		self::input( 'image_style', 'Estilo visual predeterminado', $o );
		echo '<p>Se registra el modelo, calidad, tamaño y costo estimado en USD de cada imagen nueva. El historial conserva miniatura, artículo relacionado y total acumulado.</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-ai-images' ) ) . '">Abrir imágenes y costos</a></p>';
	}
	private static function providers( $o ) {
		$names = array( 'openai' => 'OpenAI / GPT', 'anthropic' => 'Anthropic / Claude', 'google' => 'Google / Gemini', 'deepseek' => 'DeepSeek', 'xai' => 'xAI / Grok' );
		foreach ( $names as $id => $name ) { $p = $o['providers'][ $id ] ?? array(); $has_key = ! empty( $p['key'] ); echo '<fieldset style="margin:18px 0;padding:16px;border:1px solid #ccd6e5"><legend><strong>' . esc_html( $name ) . '</strong></legend><label><input type="hidden" name="providers[' . esc_attr( $id ) . '][enabled]" value="0"><input type="checkbox" name="providers[' . esc_attr( $id ) . '][enabled]" value="1" ' . checked( ! empty( $p['enabled'] ), true, false ) . '> Activar</label><p><input class="regular-text" type="password" name="providers[' . esc_attr( $id ) . '][key]" autocomplete="new-password" placeholder="' . esc_attr( $has_key ? '••••••••••••' : 'API Key' ) . '"> <span class="digitalisimo-secret ' . ( $has_key ? 'is-set' : 'is-empty' ) . '">' . esc_html( $has_key ? 'Guardada · escribe una nueva sólo si quieres reemplazarla' : 'Sin clave configurada' ) . '</span></p><p><label>URL compatible <input class="regular-text" type="url" name="providers[' . esc_attr( $id ) . '][url]" value="' . esc_attr( $p['url'] ?? '' ) . '" placeholder="Opcional"></label></p></fieldset>'; }
	}
	private static function profiles( $o ) { foreach ( $o['profiles'] as $id => $p ) { echo '<p><strong>' . esc_html( ucfirst( $id ) ) . '</strong><br><label>Proveedor <select name="profiles[' . esc_attr( $id ) . '][provider]">'; foreach ( array( 'openai', 'anthropic', 'google', 'deepseek', 'xai' ) as $provider ) echo '<option value="' . esc_attr( $provider ) . '" ' . selected( $p['provider'], $provider, false ) . '>' . esc_html( $provider ) . '</option>'; echo '</select></label> <label>Modelo <input name="profiles[' . esc_attr( $id ) . '][model]" value="' . esc_attr( $p['model'] ) . '"></label> <label>Alternativa <select name="profiles[' . esc_attr( $id ) . '][fallback]"><option value="">Sin alternativa</option>'; foreach ( array( 'openai', 'anthropic', 'google', 'deepseek', 'xai' ) as $provider ) echo '<option value="' . esc_attr( $provider ) . '" ' . selected( $p['fallback'], $provider, false ) . '>' . esc_html( $provider ) . '</option>'; echo '</select></label></p>'; } }

	private static function sanitize_values( $base ) {
		$in = (array) wp_unslash( $_POST['ai'] ?? array() );
		$o = array_merge( $base, array_map( 'sanitize_text_field', $in ) );
		foreach ( array( 'chat_enabled', 'inherit_network', 'knowledge_enabled', 'knowledge_auto_index', 'knowledge_embeddings_enabled', 'knowledge_semantic_rerank', 'mcp_featured_image_enabled' ) as $key ) if ( array_key_exists( $key, $in ) ) $o[ $key ] = empty( $in[ $key ] ) ? 0 : 1;
		if ( isset( $in['image_quality'] ) ) $o['image_quality'] = in_array( $in['image_quality'], array( 'low', 'medium', 'high', 'xhigh', 'max' ), true ) ? $in['image_quality'] : 'medium';
		if ( isset( $_POST['providers'] ) ) { $o['providers'] = $base['providers'] ?? array(); foreach ( (array) $_POST['providers'] as $id => $p ) { $id = sanitize_key( $id ); $o['providers'][ $id ]['enabled'] = ! empty( $p['enabled'] ); $o['providers'][ $id ]['url'] = esc_url_raw( $p['url'] ?? '' ); if ( ! empty( $p['key'] ) ) $o['providers'][ $id ]['key'] = sanitize_text_field( $p['key'] ); } }
		if ( isset( $_POST['profiles'] ) ) { $o['profiles'] = $base['profiles'] ?? self::defaults()['profiles']; foreach ( (array) $_POST['profiles'] as $id => $p ) $o['profiles'][ sanitize_key( $id ) ] = array( 'provider' => sanitize_key( $p['provider'] ?? 'openai' ), 'model' => sanitize_text_field( $p['model'] ?? '' ), 'fallback' => sanitize_key( $p['fallback'] ?? '' ) ); }
		return $o;
	}
	private static function save_site() { $base = wp_parse_args( self::local_options(), self::defaults() ); update_option( self::OPTION, self::sanitize_values( $base ), false ); }
	private static function save_network() { $base = wp_parse_args( (array) get_site_option( self::OPTION, array() ), self::defaults() ); update_site_option( self::OPTION, self::sanitize_values( $base ) ); }

	public static function routes() {
		register_rest_route( 'digitalisimo-ai/v1', '/chat', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'chat' ), 'permission_callback' => '__return_true', 'args' => array( 'message' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ) ) );
		register_rest_route( 'digitalisimo-ai/v1', '/complete', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'complete_api' ), 'permission_callback' => function() { return current_user_can( 'edit_posts' ); } ) );
		register_rest_route( 'digitalisimo-ai/v1', '/knowledge/search', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'knowledge_search_api' ), 'permission_callback' => function() { return current_user_can( 'edit_posts' ); }, 'args' => array( 'q' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ) ) );
		register_rest_route( 'digitalisimo-ai/v1', '/knowledge/sources', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'knowledge_sources_api' ), 'permission_callback' => function() { return current_user_can( 'edit_posts' ); } ) );
	}

	public static function complete( $profile, $prompt, $context = '' ) {
		$o = self::get(); $profile_data = $o['profiles'][ $profile ] ?? null; if ( ! $profile_data ) return new WP_Error( 'profile', 'Perfil no configurado' );
		$order = array_unique( array_filter( array( $profile_data['provider'], $profile_data['fallback'] ) ) ); $last_error = 'Ningún proveedor activo pudo responder.';
		foreach ( $order as $id ) {
			$provider = $o['providers'][ $id ] ?? array(); if ( empty( $provider['enabled'] ) || empty( $provider['key'] ) ) continue;
			$system = 'Eres el asistente de este sitio. Responde con precisión usando solamente el contexto proporcionado. No inventes datos. ' . $context; $url = $provider['url'] ?? ''; $headers = array( 'Content-Type' => 'application/json' ); $body = array();
			if ( in_array( $id, array( 'openai', 'deepseek', 'xai' ), true ) ) { $urls = array( 'openai' => 'https://api.openai.com/v1/chat/completions', 'deepseek' => 'https://api.deepseek.com/v1/chat/completions', 'xai' => 'https://api.x.ai/v1/chat/completions' ); $url = $url ?: $urls[ $id ]; $headers['Authorization'] = 'Bearer ' . $provider['key']; $body = array( 'model' => $profile_data['model'], 'messages' => array( array( 'role' => 'system', 'content' => $system ), array( 'role' => 'user', 'content' => $prompt ) ), 'temperature' => 0.4 ); }
			elseif ( 'anthropic' === $id ) { $url = $url ?: 'https://api.anthropic.com/v1/messages'; $headers['x-api-key'] = $provider['key']; $headers['anthropic-version'] = '2023-06-01'; $body = array( 'model' => $profile_data['model'], 'max_tokens' => 1200, 'system' => $system, 'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ) ); }
			elseif ( 'google' === $id ) { $url = $url ?: 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $profile_data['model'] ) . ':generateContent?key=' . rawurlencode( $provider['key'] ); $body = array( 'contents' => array( array( 'parts' => array( array( 'text' => $system . "\n\n" . $prompt ) ) ) ) ); } else continue;
			$response = wp_remote_post( $url, array( 'timeout' => 35, 'headers' => $headers, 'body' => wp_json_encode( $body ) ) ); $code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
			if ( is_wp_error( $response ) || $code < 200 || $code >= 300 ) { $last_error = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code; self::log( $profile, $id, false, $code ); continue; }
			$data = json_decode( wp_remote_retrieve_body( $response ), true ); $text = $data['choices'][0]['message']['content'] ?? $data['content'][0]['text'] ?? $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
			if ( trim( $text ) ) { self::log( $profile, $id, true, $code ); return trim( $text ); } $last_error = 'Respuesta vacía'; self::log( $profile, $id, false, $code );
		}
		return new WP_Error( 'provider', 'No fue posible obtener una respuesta: ' . $last_error );
	}

	private static function log( $profile, $provider, $success, $status ) { $logs = (array) get_option( self::LOG_OPTION, array() ); $logs[] = array( 'time' => current_time( 'mysql' ), 'profile' => sanitize_key( $profile ), 'provider' => sanitize_key( $provider ), 'success' => (bool) $success, 'status' => absint( $status ) ); $days = max( 1, absint( self::get( 'log_retention' ) ?: 30 ) ); $cutoff = time() - ( $days * DAY_IN_SECONDS ); $logs = array_values( array_filter( $logs, function( $row ) use ( $cutoff ) { return strtotime( $row['time'] ?? '') >= $cutoff; } ) ); update_option( self::LOG_OPTION, array_slice( $logs, -500 ), false ); }

	public static function chat( $request ) {
		if ( ! self::get( 'chat_enabled' ) ) return new WP_Error( 'disabled', 'Chatbot desactivado', array( 'status' => 403 ) ); $message = trim( (string) $request['message'] ); if ( strlen( $message ) > 1200 ) return new WP_Error( 'message_length', 'El mensaje es demasiado largo.', array( 'status' => 400 ) );
		$key = 'digitalisimo_ai_rate_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' ); $count = (int) get_transient( $key ); if ( $count >= 12 ) return new WP_Error( 'rate', 'Intenta más tarde.', array( 'status' => 429 ) ); set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		$posts = get_posts( array( 'post_status' => 'publish', 'posts_per_page' => max( 1, min( 10, (int) self::get( 'context_posts' ) ) ) ) ); $context = 'Contexto público del sitio:'; foreach ( $posts as $post ) $context .= "\n" . $post->post_title . ': ' . wp_trim_words( wp_strip_all_tags( $post->post_content ), 70 ); $context = apply_filters( 'digitalisimo_ai_context', $context, 'chatbot', $request );
		$answer = self::complete( 'chatbot', $message, $context ); return is_wp_error( $answer ) ? $answer : rest_ensure_response( array( 'answer' => $answer ) );
	}
	public static function complete_api( $request ) { $profile = sanitize_key( $request->get_param( 'profile' ) ?: 'content' ); $prompt = sanitize_textarea_field( $request->get_param( 'prompt' ) ); if ( ! $prompt ) return new WP_Error( 'prompt', 'Escribe una instrucción.', array( 'status' => 400 ) ); $context = class_exists( 'Digitalisimo_AI_Knowledge' ) ? Digitalisimo_AI_Knowledge::context( $prompt, (int) self::get( 'knowledge_results' ) ?: 4 ) : ''; $answer = self::complete( $profile, $prompt, $context ); return is_wp_error( $answer ) ? $answer : rest_ensure_response( array( 'answer' => $answer ) ); }
	public static function knowledge_search_api( $request ) { if ( ! class_exists( 'Digitalisimo_AI_Knowledge' ) ) return new WP_Error( 'knowledge', 'Base de conocimiento no disponible.', array( 'status' => 404 ) ); return rest_ensure_response( Digitalisimo_AI_Knowledge::search( $request->get_param( 'q' ), (int) self::get( 'knowledge_results' ) ?: 4 ) ); }
	public static function knowledge_sources_api() { return rest_ensure_response( class_exists( 'Digitalisimo_AI_Knowledge' ) ? Digitalisimo_AI_Knowledge::sources() : array() ); }

	public static function assets() { if ( ! self::get( 'chat_enabled' ) ) return; wp_register_script( 'digitalisimo-chatbot', DIGITALISIMO_CHATBOT_URL . 'assets/chat.js', array(), DIGITALISIMO_CHATBOT_VERSION, true ); wp_localize_script( 'digitalisimo-chatbot', 'digitalisimoChatbot', array( 'url' => rest_url( 'digitalisimo-ai/v1/chat' ) ) ); }
	public static function shortcode() { wp_enqueue_script( 'digitalisimo-chatbot' ); return '<div class="digitalisimo-chatbot"><div class="digitalisimo-chatbot-log" aria-live="polite"></div><form><input required maxlength="1200" placeholder="Escribe tu pregunta"><button>Enviar</button></form></div>'; }
	public static function content_metabox() { foreach ( get_post_types( array( 'public' => true ), 'names' ) as $type ) add_meta_box( 'digitalisimo-ai-assistant', 'Digitalisimo IA Tools', array( __CLASS__, 'metabox_html' ), $type, 'side', 'default' ); }
	public static function metabox_html() {
		$endpoint = esc_url_raw( rest_url( 'digitalisimo-ai/v1/complete' ) ); $nonce = wp_create_nonce( 'wp_rest' );
		echo '<p>Asistente editorial bajo demanda. Revisa y aplica cualquier resultado manualmente.</p><p><select id="digitalisimo-ai-profile"><option value="content">Contenido</option><option value="seo">SEO</option><option value="ecommerce">Ecommerce</option><option value="automation">Automatización</option></select></p><p><textarea id="digitalisimo-ai-prompt" class="widefat" rows="4" maxlength="2000" placeholder="Ej. Propón una introducción clara para este contenido."></textarea></p><p><button type="button" class="button" id="digitalisimo-ai-complete">Generar propuesta</button></p><div id="digitalisimo-ai-answer" class="notice inline" style="display:none;padding:8px"></div>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("digitalisimo-ai-complete"),p=document.getElementById("digitalisimo-ai-prompt"),r=document.getElementById("digitalisimo-ai-answer"),s=document.getElementById("digitalisimo-ai-profile");if(!b)return;b.addEventListener("click",function(){if(!p.value.trim())return;b.disabled=true;r.style.display="block";r.textContent="Generando…";fetch("' . esc_js( $endpoint ) . '",{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":"' . esc_js( $nonce ) . '"},body:JSON.stringify({profile:s.value,prompt:p.value})}).then(function(x){return x.json();}).then(function(x){r.textContent=x.answer||x.message||"No fue posible generar la propuesta.";}).catch(function(){r.textContent="Error de conexión.";}).finally(function(){b.disabled=false;});});});</script>';
	}
}
