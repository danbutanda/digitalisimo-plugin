<?php
defined( 'ABSPATH' ) || exit;

/** Motor IA compartido por los módulos Digitalisimo. */
class Digitalisimo_AI {
	const OPTION = 'digitalisimo_ai_options';
	const LOG_OPTION = 'digitalisimo_ai_usage_log';
	/** Lo que muestra un campo de clave con algo guardado (pedido del propietario del 2026-11-22: «deben salir los *****»). Enviada tal cual, conserva la clave; el secreto nunca se imprime. */
	const MASK = '••••••••••••';

	/**
	 * Modelo recomendado por proveedor: se usa cuando un proveedor está activo con su clave y ningún perfil lo eligió
	 * (caso real del propietario, 2026-11-22: activó sólo Anthropic y el Asistente decía «No hay ninguna IA configurada»,
	 * porque los perfiles de fábrica apuntan a OpenAI). Se cambia en «Perfiles de uso».
	 */
	const DEFAULT_MODELS = array( 'openai' => 'gpt-4o-mini', 'anthropic' => 'claude-sonnet-5-5', 'google' => 'gemini-2.5-flash', 'deepseek' => 'deepseek-chat', 'xai' => 'grok-3-mini' );

	/** El modelo recomendado de un proveedor ('' si no se conoce). */
	public static function default_model( $id ) { return (string) apply_filters( 'digitalisimo_ai_default_model', self::DEFAULT_MODELS[ $id ] ?? '', $id ); }

	/** Proveedores activos con clave, en el orden en que se intentan: [ id => modelo ]. */
	public static function usable_providers( $o = null ) {
		$o = is_array( $o ) ? $o : self::get(); $out = array();
		foreach ( (array) ( $o['providers'] ?? array() ) as $id => $p ) { $id = sanitize_key( (string) $id ); if ( ! empty( $p['enabled'] ) && ! empty( $p['key'] ) && '' !== self::default_model( $id ) ) $out[ $id ] = self::default_model( $id ); }
		return $out;
	}

	/**
	 * Orden de proveedores de un perfil, con el modelo de cada uno: primero el del perfil (con su modelo), luego su
	 * alternativa y por último cualquier otro proveedor activo con clave (con su modelo recomendado). Así un perfil
	 * que apunta a un proveedor sin clave sigue funcionando con el que sí está configurado.
	 */
	private static function provider_order( $profile_data, $o ) {
		$order = array(); $main = sanitize_key( (string) ( $profile_data['provider'] ?? '' ) ); $model = trim( (string) ( $profile_data['model'] ?? '' ) );
		if ( '' !== $main && 'auto' !== $main ) $order[ $main ] = '' !== $model ? $model : self::default_model( $main );
		$fallback = sanitize_key( (string) ( $profile_data['fallback'] ?? '' ) );
		if ( '' !== $fallback && ! isset( $order[ $fallback ] ) ) $order[ $fallback ] = self::default_model( $fallback );
		foreach ( self::usable_providers( $o ) as $id => $default ) if ( ! isset( $order[ $id ] ) ) $order[ $id ] = $default;
		return array_filter( $order, 'strlen' );
	}

	/**
	 * Lo escrito en un campo de clave, sin la máscara: una clave real nunca lleva «•», así que se quitan todos (quien hace
	 * clic en el campo y pega la clave nueva la deja pegada a la máscara). Vacío = conservar la guardada.
	 */
	public static function unmask( $raw ) {
		return sanitize_text_field( str_replace( '•', '', (string) wp_unslash( $raw ) ) );
	}

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_shortcode( 'digitalisimo_chatbot', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'content_metabox' ) );
		add_filter( 'redirect_post_location', array( __CLASS__, 'metabox_saved' ), PHP_INT_MAX );
	}

	/**
	 * El editor de bloques guarda los metaboxes heredados (los de IA Tools y los de otros plugins) con un POST aparte a
	 * post.php?meta-box-loader=1 y descarta la respuesta. WordPress contesta con una redirección al editor que fetch
	 * sigue: el servidor volvía a pintar el editor completo en cada guardado. Los datos ya se guardaron (edit_post):
	 * para esa petición basta un 200 vacío. El editor clásico no lleva meta-box-loader.
	 */
	public static function metabox_saved( $location ) {
		if ( empty( $_GET['meta-box-loader'] ) || 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || ! is_admin() ) return $location; // phpcs:ignore WordPress.Security.NonceVerification -- post.php ya comprobó el nonce del guardado.
		if ( ! headers_sent() ) { status_header( 200 ); nocache_headers(); header( 'Content-Type: text/plain; charset=utf-8' ); }
		exit;
	}

	private static function defaults() {
		return array(
			'inherit_network' => 0, 'chat_enabled' => 0, 'chat_profile' => 'chatbot', 'context_posts' => 5,
			'knowledge_enabled' => 0, 'knowledge_auto_index' => 1, 'knowledge_results' => 4, 'knowledge_embeddings_enabled' => 0, 'knowledge_embeddings_model' => 'text-embedding-3-small', 'knowledge_semantic_rerank' => 0,
			'image_size' => '1536x1024', 'image_style' => 'natural', 'image_quality' => 'medium', 'mcp_featured_image_enabled' => 0, 'log_retention' => 30, 'assistant_rate_minute' => 10, 'assistant_daily_calls' => 300, 'assistant_concurrency' => 1,
			'editorial_voice' => '', 'editorial_context' => '', 'editorial_cta' => '', 'editorial_method' => '',
			'providers' => array(),
			'profiles' => array(
				'chatbot' => array( 'provider' => 'auto', 'model' => '', 'fallback' => '' ),
				'content' => array( 'provider' => 'auto', 'model' => '', 'fallback' => '' ),
				'seo' => array( 'provider' => 'auto', 'model' => '', 'fallback' => '' ),
				'ecommerce' => array( 'provider' => 'auto', 'model' => '', 'fallback' => '' ),
				'automation' => array( 'provider' => 'auto', 'model' => '', 'fallback' => '' ),
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

	public static function menu() { add_submenu_page( null, 'DIGITALÍSIMO IA Tools', 'IA Tools', 'manage_options', 'digitalisimo-ai', array( __CLASS__, 'page' ) ); }

	public static function page() { self::render( false ); }
	public static function network_page() { self::render( true ); }

	/**
	 * Un solo dibujante para el sitio y la red (paridad del 2026-11-22: eran dos pantallas escritas por separado y ya
	 * divergían; la del sitio no guardaba y la de red mostraba la redacción editorial del sitio principal). Cambian sólo el
	 * almacén (opción del sitio u opción de red), la capacidad, el nonce y lo que es propio de cada sitio (herencia, índice
	 * de conocimiento e historial de imágenes). Un sitio que hereda la red ve los valores de la red sin poder editarlos:
	 * antes podía escribirlos, se guardaban en el sitio y la pantalla volvía a mostrar los de la red, como si se borraran.
	 */
	private static function render( $network ) {
		if ( $network ? ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) || ! is_super_admin() ) : ! current_user_can( 'manage_options' ) ) return;
		$action = $network ? 'digitalisimo_ai_network_save' : 'digitalisimo_ai_save';
		$saved = isset( $_POST[ $action ] ) && check_admin_referer( $action );
		if ( $saved ) { if ( $network ) self::save_network(); else self::save_site(); }
		$tabs = array( 'general' => 'General', 'providers' => 'Proveedores', 'profiles' => 'Perfiles de uso', 'writing' => 'Redacción', 'chat' => 'Chatbot', 'knowledge' => 'Conocimiento del sitio', 'images' => 'Imágenes', 'advanced' => 'Avanzado' );
		$tab = sanitize_key( $_GET['tab'] ?? 'general' ); if ( ! isset( $tabs[ $tab ] ) ) $tab = 'general';
		$inherits = ! $network && is_multisite() && ! empty( self::local_options()['inherit_network'] );
		$o = $network ? wp_parse_args( (array) get_site_option( self::OPTION, array() ), self::defaults() ) : self::get();
		$base = $network ? network_admin_url( 'admin.php?page=digitalisimo-network-ai' ) : admin_url( 'admin.php?page=digitalisimo-ai' );
		echo '<div class="wrap"><h1>' . ( $network ? 'DIGITALÍSIMO IA Tools · Red' : 'DIGITALÍSIMO IA Tools' ) . '</h1><hr class="wp-header-end"><p>Herramientas de IA compartidas para chatbot, contenido, SEO, ecommerce, conocimiento e imágenes. Generan bajo demanda y nunca publican ni modifican contenido automáticamente.</p>';
		if ( $saved ) echo '<div class="notice notice-success is-dismissible"><p>' . ( $network ? 'Configuración de red guardada.' : 'Configuración guardada.' ) . '</p></div>';
		if ( $network ) echo '<div class="notice notice-info inline"><p>Valores de red: los sitios que heredan la configuración de IA los usan al momento, sin copiar credenciales. Los sitios que la personalizan conservan la suya.</p></div>';
		elseif ( is_multisite() ) echo '<div class="notice notice-info inline"><p><strong>Valor efectivo:</strong> ' . esc_html( self::origin() ) . '. Puedes elegir heredar los ajustes completos de la red o usar los de este sitio.</p></div>';
		if ( $inherits ) echo '<div class="notice notice-warning inline"><p><strong>Este sitio hereda la configuración de IA de la red:</strong> ves los valores de la red (también sus claves) y no se cambian desde aquí. Para usar una configuración y claves propias, desmarca «Heredar toda la configuración de IA de la red» en General y guarda.</p></div>';
		echo '<h2 class="nav-tab-wrapper">'; foreach ( $tabs as $id => $name ) echo '<a class="nav-tab ' . ( $id === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $base . '&tab=' . $id ) . '">' . esc_html( $name ) . '</a>'; echo '</h2><form method="post">'; wp_nonce_field( $action );
		if ( 'general' === $tab && ! $network && is_multisite() ) { self::check( 'inherit_network', 'Heredar toda la configuración de IA de la red', self::local_options() ); echo '<p class="description">Desmarcada, este sitio usa su propia configuración y sus propias claves: las de la red no se copian.</p>'; }
		// Los controles deshabilitados no se envían: un sitio que hereda sólo puede cambiar la herencia.
		if ( $inherits ) echo '<fieldset disabled>';
		self::tab_fields( $tab, $o, $network );
		if ( $inherits ) echo '</fieldset>';
		// El botón envía el campo que activa el guardado (sin él, «Guardar» no guardaba nada: caso real del 2026-11-22).
		if ( ! $inherits || 'general' === $tab ) submit_button( $network ? 'Guardar configuración de red' : 'Guardar configuración', 'primary', $action );
		echo '</form></div>';
	}

	/** Campos de cada pestaña, los mismos en el sitio y en la red; sólo lo que pertenece a un sitio queda fuera de la red. */
	private static function tab_fields( $tab, $o, $network ) {
		if ( 'general' === $tab ) {
			self::check( 'chat_enabled', 'Activar DIGITALÍSIMO IA Tools', $o ); self::check( 'knowledge_enabled', 'Activar base de conocimiento del sitio', $o ); self::check( 'mcp_featured_image_enabled', 'Permitir que Codex suba imágenes destacadas mediante MCP', $o );
			echo '<p>Perfiles disponibles: chatbot, creación de contenido, SEO, ecommerce, automatizaciones e imágenes.</p>';
		} elseif ( 'providers' === $tab ) self::providers( $o );
		elseif ( 'profiles' === $tab ) self::profiles( $o );
		elseif ( 'writing' === $tab ) { echo '<h3>Redacción editorial</h3><p>Este método guía la creación interna, el publisher y los clientes MCP. SEO conserva las señales técnicas; IA Tools administra la voz, el CTA, el chat editorial y los borradores.</p>'; Digitalisimo_AI_Content_Playbook::fields( $network, $o ); }
		elseif ( 'chat' === $tab ) { self::check( 'chat_enabled', 'Activar chatbot público', $o ); self::input( 'context_posts', 'Entradas públicas como contexto', $o, 'number' ); echo '<p><code>[digitalisimo_chatbot]</code> añade el chat donde tú lo decidas. No registra prompts ni direcciones IP.</p>'; }
		elseif ( 'knowledge' === $tab ) self::knowledge_fields( $o, $network );
		elseif ( 'images' === $tab ) self::image_fields( $o, $network );
		else { self::input( 'log_retention', 'Retención de métricas (días)', $o, 'number' ); echo '<p>Los registros contienen fecha, perfil, proveedor y resultado. No se guardan claves, prompts, respuestas, IPs ni conversaciones.</p>'; self::assistant_fields( $o ); }
	}

	/** Límites del Asistente IA por persona (los mismos campos en el sitio y en la red). */
	public static function assistant_fields( $o ) {
		echo '<h3>Asistente IA</h3><p>Límites por persona para no agotar la clave ni el servidor. 0 en mensajes o llamadas = sin tope.</p>';
		self::input( 'assistant_rate_minute', 'Mensajes por minuto', $o, 'number' );
		self::input( 'assistant_daily_calls', 'Llamadas a la IA por día', $o, 'number' );
		self::input( 'assistant_concurrency', 'Peticiones a la vez', $o, 'number' );
	}
	private static function check( $key, $label, $o ) { echo '<p><label><input type="hidden" name="ai[' . esc_attr( $key ) . ']" value="0"><input type="checkbox" name="ai[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $o[ $key ] ), true, false ) . '> <strong>' . esc_html( $label ) . '</strong></label></p>'; }
	private static function input( $key, $label, $o, $type = 'text' ) { echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input class="regular-text" type="' . esc_attr( $type ) . '" name="ai[' . esc_attr( $key ) . ']" value="' . esc_attr( $o[ $key ] ?? '' ) . '"></label></p>'; }
	private static function knowledge_fields( $o, $network = false ) {
		self::check( 'knowledge_enabled', 'Activar base de conocimiento', $o );
		self::check( 'knowledge_auto_index', 'Actualizar al guardar contenido público', $o );
		self::input( 'knowledge_results', 'Fuentes relevantes por consulta', $o, 'number' );
		self::check( 'knowledge_embeddings_enabled', 'Activar embeddings opcionales de OpenAI', $o ); self::check( 'knowledge_semantic_rerank', 'Reordenar resultados con similitud semántica', $o ); self::input( 'knowledge_embeddings_model', 'Modelo de embeddings', $o );
		// El índice es de cada sitio: la red sólo define los valores que heredan.
		if ( $network ) { echo '<p class="description">Cada sitio indexa sólo su propio contenido público y lo reconstruye desde su propia pantalla.</p>'; return; }
		$count = class_exists( 'Digitalisimo_AI_Knowledge' ) ? Digitalisimo_AI_Knowledge::count() : 0;
		echo '<p><strong>Estado:</strong> ' . esc_html( $count ) . ' contenidos públicos indexados.</p><p>Digitalisimo usa una recuperación contextual local (RAG): toma sólo contenido público, no guarda prompts ni conversaciones, y no entrena modelos externos ni modifica contenido.</p>';
		echo '<p><a class="button button-secondary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=digitalisimo_ai_knowledge_rebuild' ), 'digitalisimo_ai_knowledge_rebuild' ) ) . '">Reconstruir base de conocimiento</a></p>';
		if ( isset( $_GET['rebuilt'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Base de conocimiento reconstruida.</p></div>';
		echo '<p class="description">Al generar embeddings se enviará una representación del contenido público indexado a OpenAI. Es opcional, manual y puede generar coste; no equivale a entrenar un modelo. El reordenamiento semántico, si se activa, envía sólo la consulta a OpenAI y guarda su representación en caché durante una hora.</p><p><a class="button button-secondary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=digitalisimo_ai_knowledge_embeddings' ), 'digitalisimo_ai_knowledge_embeddings' ) ) . '">Generar embeddings (máx. 20)</a></p>';
		if ( isset( $_GET['embeddings'] ) ) { $result = (array) get_option( 'digitalisimo_ai_knowledge_embeddings_result', array() ); echo '<div class="notice ' . ( ! empty( $result['ok'] ) ? 'notice-success' : 'notice-warning' ) . ' is-dismissible"><p>' . esc_html( $result['message'] ?? '' ) . '</p></div>'; }
		$sources = class_exists( 'Digitalisimo_AI_Knowledge' ) ? Digitalisimo_AI_Knowledge::sources() : array();
		echo '<h2>Fuentes indexadas</h2><p class="description">Vista administrativa del índice local. No muestra el contenido completo ni lo envía a proveedores de IA.</p><table class="widefat striped"><thead><tr><th>Título</th><th>Tipo</th><th>Última modificación</th><th>URL</th></tr></thead><tbody>';
		if ( ! $sources ) echo '<tr><td colspan="4">Aún no hay contenido indexado. Activa la base y usa Reconstruir.</td></tr>';
		foreach ( array_slice( $sources, 0, 100 ) as $source ) echo '<tr><td>' . esc_html( $source['title'] ?? '' ) . '</td><td>' . esc_html( $source['type'] ?? '' ) . '</td><td>' . esc_html( ! empty( $source['modified'] ) ? mysql2date( 'Y-m-d H:i', $source['modified'] ) : '' ) . '</td><td><a href="' . esc_url( $source['url'] ?? '' ) . '" target="_blank" rel="noopener">Ver contenido</a></td></tr>';
		echo '</tbody></table>';
	}
	/** Configuración visible de imagen: el perfil también permanece disponible en Perfiles de uso. */
	private static function image_fields( $o, $network = false ) {
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
		echo '<p>Se registra el modelo, calidad, tamaño y costo estimado en USD de cada imagen nueva. El historial conserva miniatura, artículo relacionado y total acumulado' . ( $network ? ' en cada sitio.</p>' : '.</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-ai-images' ) ) . '">Abrir imágenes y costos</a></p>' );
	}
	private static function providers( $o ) {
		$names = array( 'openai' => 'OpenAI / GPT', 'anthropic' => 'Anthropic / Claude', 'google' => 'Google / Gemini', 'deepseek' => 'DeepSeek', 'xai' => 'xAI / Grok' );
		// Un sitio que personaliza no recibe las claves de la red: si la red tiene una, se le pide la suya.
		$network_keys = is_multisite() && ! ( function_exists( 'is_network_admin' ) && is_network_admin() ) && empty( self::local_options()['inherit_network'] ) ? (array) ( ( (array) get_site_option( self::OPTION, array() ) )['providers'] ?? array() ) : array();
		foreach ( $names as $id => $name ) { $p = $o['providers'][ $id ] ?? array(); $has_key = ! empty( $p['key'] ); if ( ! $has_key && ! empty( $network_keys[ $id ]['key'] ) ) echo '<p class="notice notice-info inline">' . esc_html( $name ) . ': la red tiene su clave, pero no se copia a los sitios que personalizan. Escribe la clave de este sitio.</p>'; echo '<fieldset style="margin:18px 0;padding:16px;border:1px solid #ccd6e5"><legend><strong>' . esc_html( $name ) . '</strong></legend><label><input type="hidden" name="providers[' . esc_attr( $id ) . '][enabled]" value="0"><input type="checkbox" name="providers[' . esc_attr( $id ) . '][enabled]" value="1" ' . checked( ! empty( $p['enabled'] ), true, false ) . '> Activar</label><p><input class="regular-text" type="password" name="providers[' . esc_attr( $id ) . '][key]" autocomplete="new-password" value="' . esc_attr( $has_key ? self::MASK : '' ) . '" onfocus="this.select()" placeholder="API Key"> <span class="digitalisimo-secret ' . ( $has_key ? 'is-set' : 'is-empty' ) . '">' . esc_html( $has_key ? 'Guardada · escribe una nueva sólo si quieres reemplazarla' : 'Sin clave configurada' ) . '</span>' . ( $has_key && '' !== self::default_model( $id ) ? ' <span class="description">' . esc_html( sprintf( 'Si ningún perfil lo elige, se usa con %s (cámbialo en Perfiles de uso).', self::default_model( $id ) ) ) . '</span>' : '' ) . '' . ( $has_key ? ' <label><input type="checkbox" name="providers[' . esc_attr( $id ) . '][remove]" value="1"> Quitar la clave</label>' : '' ) . '</p><p><label>URL compatible <input class="regular-text" type="url" name="providers[' . esc_attr( $id ) . '][url]" value="' . esc_attr( $p['url'] ?? '' ) . '" placeholder="Opcional"></label></p></fieldset>'; }
	}
	private static function profiles( $o ) { foreach ( $o['profiles'] as $id => $p ) { echo '<p><strong>' . esc_html( ucfirst( $id ) ) . '</strong><br><label>Proveedor <select name="profiles[' . esc_attr( $id ) . '][provider]">'; if ( 'images' !== $id ) echo '<option value="auto" ' . selected( in_array( $p['provider'], array( 'auto', '' ), true ), true, false ) . '>Auto (lo que esté configurado en Proveedores)</option>'; foreach ( array( 'openai', 'anthropic', 'google', 'deepseek', 'xai' ) as $provider ) echo '<option value="' . esc_attr( $provider ) . '" ' . selected( $p['provider'], $provider, false ) . '>' . esc_html( $provider ) . '</option>'; echo '</select></label> <label>Modelo <input name="profiles[' . esc_attr( $id ) . '][model]" value="' . esc_attr( $p['model'] ) . '" placeholder="El recomendado"></label> <label>Alternativa <select name="profiles[' . esc_attr( $id ) . '][fallback]"><option value="">Sin alternativa</option>'; foreach ( array( 'openai', 'anthropic', 'google', 'deepseek', 'xai' ) as $provider ) echo '<option value="' . esc_attr( $provider ) . '" ' . selected( $p['fallback'], $provider, false ) . '>' . esc_html( $provider ) . '</option>'; echo '</select></label></p>'; } }

	private static function sanitize_values( $base ) {
		$in = (array) wp_unslash( $_POST['ai'] ?? array() );
		$o = array_merge( $base, array_map( 'sanitize_text_field', $in ) );
		// La redacción editorial son textareas: sanitize_text_field() juntaba sus líneas en una sola.
		foreach ( array( 'editorial_voice', 'editorial_context', 'editorial_cta', 'editorial_method' ) as $key ) if ( isset( $in[ $key ] ) ) $o[ $key ] = sanitize_textarea_field( $in[ $key ] );
		foreach ( array( 'chat_enabled', 'inherit_network', 'knowledge_enabled', 'knowledge_auto_index', 'knowledge_embeddings_enabled', 'knowledge_semantic_rerank', 'mcp_featured_image_enabled' ) as $key ) if ( array_key_exists( $key, $in ) ) $o[ $key ] = empty( $in[ $key ] ) ? 0 : 1;
		if ( isset( $in['image_quality'] ) ) $o['image_quality'] = in_array( $in['image_quality'], array( 'low', 'medium', 'high', 'xhigh', 'max' ), true ) ? $in['image_quality'] : 'medium';
		if ( isset( $_POST['providers'] ) ) { $o['providers'] = $base['providers'] ?? array(); foreach ( (array) $_POST['providers'] as $id => $p ) { $id = sanitize_key( $id ); $o['providers'][ $id ]['enabled'] = ! empty( $p['enabled'] ); $o['providers'][ $id ]['url'] = esc_url_raw( $p['url'] ?? '' ); if ( ! empty( $p['remove'] ) ) $o['providers'][ $id ]['key'] = ''; elseif ( '' !== ( $new_key = self::unmask( $p['key'] ?? '' ) ) ) $o['providers'][ $id ]['key'] = $new_key; } }
		if ( isset( $_POST['profiles'] ) ) { $o['profiles'] = $base['profiles'] ?? self::defaults()['profiles']; foreach ( (array) wp_unslash( $_POST['profiles'] ) as $id => $p ) $o['profiles'][ sanitize_key( $id ) ] = array( 'provider' => ( sanitize_key( $p['provider'] ?? 'auto' ) ?: 'auto' ), 'model' => sanitize_text_field( $p['model'] ?? '' ), 'fallback' => sanitize_key( $p['fallback'] ?? '' ) ); }
		return $o;
	}
	private static function save_site() {
		$local = self::local_options(); $base = wp_parse_args( $local, self::defaults() );
		// Dejar de heredar conserva lo que se veía (los valores de la red; los campos no viajan porque se pintan
		// deshabilitados), igual que en SEO, pero nunca copia secretos: las claves quedan las propias del sitio, o vacías.
		$in = (array) wp_unslash( $_POST['ai'] ?? array() );
		if ( is_multisite() && ! empty( $local['inherit_network'] ) && array_key_exists( 'inherit_network', $in ) && empty( $in['inherit_network'] ) ) {
			$own = (array) ( $base['providers'] ?? array() );
			$base = wp_parse_args( (array) get_site_option( self::OPTION, array() ), self::defaults() );
			foreach ( (array) $base['providers'] as $id => $provider ) $base['providers'][ $id ]['key'] = (string) ( $own[ $id ]['key'] ?? '' );
			foreach ( $own as $id => $provider ) if ( ! isset( $base['providers'][ $id ] ) ) $base['providers'][ $id ] = $provider;
		}
		update_option( self::OPTION, self::sanitize_values( $base ), false );
	}
	private static function save_network() { $base = wp_parse_args( (array) get_site_option( self::OPTION, array() ), self::defaults() ); update_site_option( self::OPTION, self::sanitize_values( $base ) ); }

	public static function routes() {
		register_rest_route( 'digitalisimo-ai/v1', '/chat', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'chat' ), 'permission_callback' => '__return_true', 'args' => array( 'message' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ) ) );
		register_rest_route( 'digitalisimo-ai/v1', '/complete', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'complete_api' ), 'permission_callback' => function() { return current_user_can( 'edit_posts' ); } ) );
		register_rest_route( 'digitalisimo-ai/v1', '/knowledge/search', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'knowledge_search_api' ), 'permission_callback' => function() { return current_user_can( 'edit_posts' ); }, 'args' => array( 'q' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ) ) );
		register_rest_route( 'digitalisimo-ai/v1', '/knowledge/sources', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'knowledge_sources_api' ), 'permission_callback' => function() { return current_user_can( 'edit_posts' ); } ) );
	}

	public static function complete( $profile, $prompt, $context = '' ) {
		$o = self::get(); $profile_data = $o['profiles'][ $profile ] ?? null; if ( ! $profile_data ) return new WP_Error( 'profile', 'Perfil no configurado' );
		$order = self::provider_order( $profile_data, $o ); $last_error = 'Ningún proveedor activo pudo responder.';
		foreach ( $order as $id => $model ) {
			$provider = $o['providers'][ $id ] ?? array(); if ( empty( $provider['enabled'] ) || empty( $provider['key'] ) ) continue;
			$system = 'Eres el asistente de este sitio. Responde con precisión usando solamente el contexto proporcionado. No inventes datos. ' . $context; $url = $provider['url'] ?? ''; $headers = array( 'Content-Type' => 'application/json' ); $body = array();
			if ( in_array( $id, array( 'openai', 'deepseek', 'xai' ), true ) ) { $urls = array( 'openai' => 'https://api.openai.com/v1/chat/completions', 'deepseek' => 'https://api.deepseek.com/v1/chat/completions', 'xai' => 'https://api.x.ai/v1/chat/completions' ); $url = $url ?: $urls[ $id ]; $headers['Authorization'] = 'Bearer ' . $provider['key']; $body = array( 'model' => $model, 'messages' => array( array( 'role' => 'system', 'content' => $system ), array( 'role' => 'user', 'content' => $prompt ) ), 'temperature' => 0.4 ); }
			elseif ( 'anthropic' === $id ) { $url = $url ?: 'https://api.anthropic.com/v1/messages'; $headers['x-api-key'] = $provider['key']; $headers['anthropic-version'] = '2023-06-01'; $body = array( 'model' => $model, 'max_tokens' => 1200, 'system' => $system, 'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ) ); }
			elseif ( 'google' === $id ) { $url = $url ?: 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $provider['key'] ); $body = array( 'contents' => array( array( 'parts' => array( array( 'text' => $system . "\n\n" . $prompt ) ) ) ) ); } else continue;
			$response = wp_remote_post( $url, array( 'timeout' => 35, 'headers' => $headers, 'body' => wp_json_encode( $body ) ) ); $code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
			if ( is_wp_error( $response ) || $code < 200 || $code >= 300 ) { $last_error = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code; self::log( $profile, $id, false, $code ); continue; }
			$data = json_decode( wp_remote_retrieve_body( $response ), true ); $text = $data['choices'][0]['message']['content'] ?? $data['content'][0]['text'] ?? $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
			if ( trim( $text ) ) { self::log( $profile, $id, true, $code ); return trim( $text ); } $last_error = 'Respuesta vacía'; self::log( $profile, $id, false, $code );
		}
		return new WP_Error( 'provider', 'No fue posible obtener una respuesta: ' . $last_error );
	}

	public static function log( $profile, $provider, $success, $status ) { $logs = (array) get_option( self::LOG_OPTION, array() ); $logs[] = array( 'time' => current_time( 'mysql' ), 'profile' => sanitize_key( $profile ), 'provider' => sanitize_key( $provider ), 'success' => (bool) $success, 'status' => absint( $status ) ); $days = max( 1, absint( self::get( 'log_retention' ) ?: 30 ) ); $cutoff = time() - ( $days * DAY_IN_SECONDS ); $logs = array_values( array_filter( $logs, function( $row ) use ( $cutoff ) { return strtotime( $row['time'] ?? '') >= $cutoff; } ) ); update_option( self::LOG_OPTION, array_slice( $logs, -500 ), false ); }

	public static function chat( $request ) {
		if ( ! self::get( 'chat_enabled' ) ) return new WP_Error( 'disabled', 'Chatbot desactivado', array( 'status' => 403 ) ); $message = trim( (string) $request['message'] ); if ( strlen( $message ) > 1200 ) return new WP_Error( 'message_length', 'El mensaje es demasiado largo.', array( 'status' => 400 ) );
		$key = 'digitalisimo_ai_rate_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' ); $count = (int) get_transient( $key ); if ( $count >= 12 ) return new WP_Error( 'rate', 'Intenta más tarde.', array( 'status' => 429 ) ); set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		$posts = get_posts( array( 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => max( 1, min( 10, (int) self::get( 'context_posts' ) ) ) ) ); $context = 'Contexto público del sitio:'; foreach ( $posts as $post ) $context .= "\n" . $post->post_title . ': ' . wp_trim_words( wp_strip_all_tags( $post->post_content ), 70 ); $context = apply_filters( 'digitalisimo_ai_context', $context, 'chatbot', $request );
		// El visitante sólo recibe un aviso genérico: el código del proveedor queda en el registro de uso, no en el front.
		$answer = self::complete( 'chatbot', $message, $context ); $answer = is_wp_error( $answer ) ? '' : self::plain_text( $answer );
		if ( '' === $answer ) return new WP_Error( 'provider', 'No fue posible responder en este momento.', array( 'status' => 502 ) );
		return rest_ensure_response( array( 'answer' => $answer, 'format' => 'text' ) );
	}
	/**
	 * La respuesta del modelo es texto, nunca HTML confiable: puede traer etiquetas copiadas del contenido o inducidas por
	 * prompt injection. Se quitan etiquetas (con el contenido de script y style) y caracteres de control, y se conservan los
	 * saltos de línea; el navegador la pinta con nodos de texto.
	 */
	public static function plain_text( $text ) { $text = wp_strip_all_tags( str_replace( array( "\r\n", "\r" ), "\n", (string) $text ) ); return trim( (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text ) ); }
	public static function complete_api( $request ) { $profile = sanitize_key( $request->get_param( 'profile' ) ?: 'content' ); $prompt = sanitize_textarea_field( $request->get_param( 'prompt' ) ); if ( ! $prompt ) return new WP_Error( 'prompt', 'Escribe una instrucción.', array( 'status' => 400 ) ); $context = class_exists( 'Digitalisimo_AI_Knowledge' ) ? Digitalisimo_AI_Knowledge::context( $prompt, (int) self::get( 'knowledge_results' ) ?: 4 ) : ''; $answer = self::complete( $profile, $prompt, $context ); return is_wp_error( $answer ) ? $answer : rest_ensure_response( array( 'answer' => $answer ) ); }
	public static function knowledge_search_api( $request ) { if ( ! class_exists( 'Digitalisimo_AI_Knowledge' ) ) return new WP_Error( 'knowledge', 'Base de conocimiento no disponible.', array( 'status' => 404 ) ); return rest_ensure_response( Digitalisimo_AI_Knowledge::search( $request->get_param( 'q' ), (int) self::get( 'knowledge_results' ) ?: 4 ) ); }
	public static function knowledge_sources_api() { return rest_ensure_response( class_exists( 'Digitalisimo_AI_Knowledge' ) ? Digitalisimo_AI_Knowledge::sources() : array() ); }

	public static function assets() { if ( ! self::get( 'chat_enabled' ) ) return; wp_register_script( 'digitalisimo-chatbot', DIGITALISIMO_CHATBOT_URL . 'assets/chat.js', array(), DIGITALISIMO_CHATBOT_VERSION, true ); wp_localize_script( 'digitalisimo-chatbot', 'digitalisimoChatbot', array( 'url' => rest_url( 'digitalisimo-ai/v1/chat' ) ) ); }
	public static function shortcode() { wp_enqueue_script( 'digitalisimo-chatbot' ); return '<div class="digitalisimo-chatbot"><div class="digitalisimo-chatbot-log" aria-live="polite"></div><form><input required maxlength="1200" placeholder="Escribe tu pregunta"><button>Enviar</button></form></div>'; }
	public static function content_metabox() { foreach ( get_post_types( array( 'public' => true ), 'names' ) as $type ) add_meta_box( 'digitalisimo-ai-assistant', 'DIGITALÍSIMO IA Tools', array( __CLASS__, 'metabox_html' ), $type, 'side', 'default' ); }
	public static function metabox_html() {
		$endpoint = esc_url_raw( rest_url( 'digitalisimo-ai/v1/complete' ) ); $nonce = wp_create_nonce( 'wp_rest' );
		echo '<p>Asistente editorial bajo demanda. Revisa y aplica cualquier resultado manualmente.</p><p><select id="digitalisimo-ai-profile"><option value="content">Contenido</option><option value="seo">SEO</option><option value="ecommerce">Ecommerce</option><option value="automation">Automatización</option></select></p><p><textarea id="digitalisimo-ai-prompt" class="widefat" rows="4" maxlength="2000" placeholder="Ej. Propón una introducción clara para este contenido."></textarea></p><p><button type="button" class="button" id="digitalisimo-ai-complete">Generar propuesta</button></p><div id="digitalisimo-ai-answer" class="notice inline" style="display:none;padding:8px"></div>';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("digitalisimo-ai-complete"),p=document.getElementById("digitalisimo-ai-prompt"),r=document.getElementById("digitalisimo-ai-answer"),s=document.getElementById("digitalisimo-ai-profile");if(!b)return;b.addEventListener("click",function(){if(!p.value.trim())return;b.disabled=true;r.style.display="block";r.textContent="Generando…";fetch("' . esc_js( $endpoint ) . '",{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":"' . esc_js( $nonce ) . '"},body:JSON.stringify({profile:s.value,prompt:p.value})}).then(function(x){return x.json();}).then(function(x){r.textContent=x.answer||x.message||"No fue posible generar la propuesta.";}).catch(function(){r.textContent="Error de conexión.";}).finally(function(){b.disabled=false;});});});</script>';
	}
}
