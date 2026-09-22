<?php
defined( 'ABSPATH' ) || exit;

/** Generación contextual de imágenes para artículos; nunca publica contenido. */
class Digitalisimo_AI_Images {
	const API_SIZE = '1536x1024';
	const OUTPUT_WIDTH = 1536;
	const OUTPUT_HEIGHT = 864;
	const WEBP_QUALITY = 82;
	const META_GENERATED = '_digitalisimo_ai_generated';
	const META_MODEL = '_digitalisimo_ai_image_model';
	const META_QUALITY = '_digitalisimo_ai_image_quality';
	const META_SIZE = '_digitalisimo_ai_image_size';
	const META_COST = '_digitalisimo_ai_image_cost_usd';
	const META_COST_BASIS = '_digitalisimo_ai_image_cost_basis';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'post_metabox' ) );
	}

	public static function menu() {
		add_submenu_page( null, 'IA · Imágenes de artículos', 'IA · Imágenes de artículos', 'upload_files', 'digitalisimo-ai-images', array( __CLASS__, 'page' ) );
	}

	public static function post_metabox() {
		foreach ( get_post_types( array( 'public' => true, 'show_ui' => true ), 'names' ) as $type ) add_meta_box( 'digitalisimo-ai-article-image', 'Imagen SEO del artículo', array( __CLASS__, 'post_metabox_html' ), $type, 'side', 'default' );
	}

	public static function routes() {
		register_rest_route( 'digitalisimo-ai/v1', '/image', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'generate' ), 'permission_callback' => function() { return current_user_can( 'upload_files' ); }, 'args' => array(
			'prompt' => array( 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field' ), 'post_id' => array( 'required' => false, 'sanitize_callback' => 'absint' ), 'image_title' => array( 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ), 'image_alt' => array( 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
		) ) );
	}

	public static function generate( $request ) {
		$all = Digitalisimo_AI::get(); $profile = $all['profiles']['images'] ?? array(); $provider = $all['providers'][ $profile['provider'] ?? '' ] ?? array();
		if ( 'openai' !== ( $profile['provider'] ?? '' ) || empty( $provider['enabled'] ) || empty( $provider['key'] ) ) return new WP_Error( 'image_provider', 'La generación de imágenes requiere un perfil OpenAI activo.', array( 'status' => 400 ) );
		$post_id = absint( $request->get_param( 'post_id' ) ); $post = $post_id ? get_post( $post_id ) : null;
		if ( $post_id && ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) ) return new WP_Error( 'image_post', 'No puedes editar el artículo indicado.', array( 'status' => 403 ) );
		$model = sanitize_text_field( $profile['model'] ?? 'gpt-image-1' ) ?: 'gpt-image-1';
		$quality = self::quality_for_model( $model, sanitize_key( $all['image_quality'] ?? 'medium' ) );
		$context = self::article_context( $post ); $prompt = sanitize_textarea_field( (string) $request->get_param( 'prompt' ) ); if ( ! $prompt ) $prompt = self::default_prompt( sanitize_text_field( $all['image_style'] ?? 'natural' ) ); if ( $context ) $prompt .= "\n\nBrief editorial del artículo:\n" . $context;
		$response = wp_remote_post( $provider['url'] ?: 'https://api.openai.com/v1/images/generations', array( 'timeout' => 120, 'headers' => array( 'Authorization' => 'Bearer ' . $provider['key'], 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'model' => $model, 'prompt' => $prompt, 'size' => self::API_SIZE, 'quality' => $quality ) ) ) );
		if ( is_wp_error( $response ) ) return $response; $code = wp_remote_retrieve_response_code( $response ); $body = json_decode( wp_remote_retrieve_body( $response ), true ); if ( $code < 200 || $code >= 300 ) return new WP_Error( 'image_provider_response', sanitize_text_field( $body['error']['message'] ?? 'El proveedor de imágenes devolvió un error.' ), array( 'status' => 502 ) );
		$url = $body['data'][0]['url'] ?? ''; $base64 = $body['data'][0]['b64_json'] ?? ''; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
		$title = sanitize_text_field( $request->get_param( 'image_title' ) ?: ( $post ? $post->post_title : wp_trim_words( $prompt, 10, '' ) ) ); $alt = sanitize_text_field( $request->get_param( 'image_alt' ) ?: $title );
		if ( $base64 ) { $bytes = base64_decode( $base64, true ); if ( false === $bytes ) return new WP_Error( 'image_response', 'El proveedor devolvió una imagen inválida.', array( 'status' => 502 ) ); $upload = wp_upload_bits( 'digitalisimo-ai-' . time() . '.png', null, $bytes ); if ( $upload['error'] ) return new WP_Error( 'image_upload', $upload['error'] ); $attachment = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $title, 'post_status' => 'inherit' ), $upload['file'], $post_id ); $file = $upload['file']; } elseif ( $url ) { $attachment = media_sideload_image( esc_url_raw( $url ), $post_id, $title, 'id' ); $file = $attachment && ! is_wp_error( $attachment ) ? get_attached_file( $attachment ) : ''; } else return new WP_Error( 'image_response', 'El proveedor no devolvió una imagen.', array( 'status' => 502 ) );
		if ( is_wp_error( $attachment ) ) return $attachment;
		$optimized = self::optimize_to_webp( $attachment, $file );
		if ( is_wp_error( $optimized ) ) { wp_delete_attachment( $attachment, true ); return $optimized; }
		$cost = self::estimate_cost( $model, $quality, $prompt, (array) ( $body['usage'] ?? array() ) );
		wp_update_post( array( 'ID' => $attachment, 'post_title' => $title, 'post_name' => sanitize_title( $title ), 'post_mime_type' => 'image/webp' ) ); update_post_meta( $attachment, '_wp_attachment_image_alt', $alt ); update_post_meta( $attachment, self::META_GENERATED, '1' ); update_post_meta( $attachment, '_digitalisimo_image_format', 'webp' ); update_post_meta( $attachment, self::META_MODEL, $model ); update_post_meta( $attachment, self::META_QUALITY, $quality ); update_post_meta( $attachment, self::META_SIZE, self::API_SIZE ); if ( null !== $cost['amount'] ) update_post_meta( $attachment, self::META_COST, (string) $cost['amount'] ); update_post_meta( $attachment, self::META_COST_BASIS, $cost['basis'] ); if ( $post_id ) { set_post_thumbnail( $post_id, $attachment ); if ( $attachment !== (int) get_post_thumbnail_id( $post_id ) ) return new WP_Error( 'image_featured', 'La imagen se creó, pero WordPress no pudo asignarla como destacada.', array( 'status' => 500 ) ); } $size = wp_get_attachment_image_src( $attachment, 'full' );
		return rest_ensure_response( array( 'attachment_id' => $attachment, 'post_id' => $post_id, 'url' => wp_get_attachment_url( $attachment ), 'title' => $title, 'alt' => $alt, 'mime_type' => 'image/webp', 'format' => 'webp', 'quality' => self::WEBP_QUALITY, 'image_quality' => $quality, 'model' => $model, 'cost_usd' => $cost['amount'], 'cost_basis' => $cost['basis'], 'width' => (int) ( $size[1] ?? self::OUTPUT_WIDTH ), 'height' => (int) ( $size[2] ?? self::OUTPUT_HEIGHT ) ) );
	}

	private static function quality_for_model( $model, $quality ) { $quality = in_array( $quality, array( 'low', 'medium', 'high', 'xhigh', 'max' ), true ) ? $quality : 'medium'; return 0 === strpos( $model, 'gpt-image-2.5-' ) || ! in_array( $quality, array( 'xhigh', 'max' ), true ) ? $quality : 'high'; }
	/** Estimación transparente: imágenes desde texto, sin imágenes de referencia. */
	private static function estimate_cost( $model, $quality, $prompt, $usage ) {
		$fixed = array(
			'gpt-image-1' => array( 'low' => 0.016, 'medium' => 0.063, 'high' => 0.25 ),
			'gpt-image-1.5' => array( 'low' => 0.013, 'medium' => 0.05, 'high' => 0.20 ),
			'gpt-image-1-mini' => array( 'low' => 0.006, 'medium' => 0.015, 'high' => 0.052 ),
			'gpt-image-2' => array( 'low' => 0.005, 'medium' => 0.041, 'high' => 0.165 ),
		);
		if ( isset( $fixed[ $model ][ $quality ] ) ) {
			$prompt_tokens = max( 1, (int) ceil( strlen( $prompt ) / 4 ) );
			$text_rate = 'gpt-image-1-mini' === $model ? 2 : 5;
			return array( 'amount' => round( $fixed[ $model ][ $quality ] + ( $prompt_tokens * $text_rate / 1000000 ), 6 ), 'basis' => 'Estimación de salida y prompt de texto.' );
		}
		if ( 0 === strpos( $model, 'gpt-image-2.5-' ) ) {
			$output = (int) ( $usage['output_tokens_details']['image_tokens'] ?? $usage['image_tokens'] ?? 0 );
			$input = (int) ( $usage['input_tokens_details']['text_tokens'] ?? $usage['input_tokens'] ?? 0 );
			if ( $output ) return array( 'amount' => round( ( $output * 30 + $input * 5 ) / 1000000, 6 ), 'basis' => 'Estimación desde el uso devuelto por OpenAI.' );
		}
		return array( 'amount' => null, 'basis' => 'El proveedor no devolvió datos suficientes para estimar el costo.' );
	}

	private static function article_context( $post ) { if ( ! $post ) return ''; $keywords = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $post->ID, 'digitalisimo_seo_keywords', true ) ) ) ); return 'Título: ' . $post->post_title . '. Keyword principal: ' . ( $keywords[0] ?? '' ) . '. Keywords secundarias: ' . implode( ', ', array_slice( $keywords, 1 ) ) . '. Contenido: ' . wp_trim_words( wp_strip_all_tags( $post->post_content ), 130, '' ); }
	private static function default_prompt( $style ) { return 'Actúa como director de arte editorial. Crea una imagen hero premium, horizontal 16:9, para la imagen destacada de un artículo de Digitalísimo. Construye una historia visual clara a partir del brief: un solo sujeto o escena principal, profundidad, iluminación cuidada, composición sofisticada, detalles reales y espacio negativo equilibrado. Estilo solicitado: ' . $style . '. La imagen debe verse pensada para este tema, no como foto de stock. No uses texto, letras, logotipos, marcas de agua, tableros con datos ilegibles, collage, iconos flotantes, neón excesivo ni personas genéricas mirando una laptop. Evita manos deformes y elementos duplicados.'; }
	private static function optimize_to_webp( $attachment, $file ) {
		$editor = wp_get_image_editor( $file ); if ( is_wp_error( $editor ) ) return new WP_Error( 'image_webp', 'El servidor no puede procesar la imagen para WebP.', array( 'status' => 500 ) );
		$size = $editor->get_size(); $width = (int) ( $size['width'] ?? 0 ); $height = (int) ( $size['height'] ?? 0 ); if ( ! $width || ! $height ) return new WP_Error( 'image_dimensions', 'La imagen generada no tiene dimensiones válidas.', array( 'status' => 502 ) );
		$ratio = self::OUTPUT_WIDTH / self::OUTPUT_HEIGHT; if ( $width / $height > $ratio ) { $crop_height = $height; $crop_width = (int) round( $height * $ratio ); $x = (int) floor( ( $width - $crop_width ) / 2 ); $y = 0; } else { $crop_width = $width; $crop_height = (int) round( $width / $ratio ); $x = 0; $y = (int) floor( ( $height - $crop_height ) / 2 ); }
		$cropped = $editor->crop( $x, $y, $crop_width, $crop_height, self::OUTPUT_WIDTH, self::OUTPUT_HEIGHT ); if ( is_wp_error( $cropped ) ) return $cropped;
		$editor->set_quality( self::WEBP_QUALITY ); $webp = preg_replace( '/\.[^.]+$/', '.webp', $file ); $saved = $editor->save( $webp, 'image/webp' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || 'image/webp' !== ( $saved['mime-type'] ?? '' ) ) return new WP_Error( 'image_webp', 'El servidor no tiene soporte WebP para optimizar esta imagen.', array( 'status' => 500 ) );
		if ( $file !== $saved['path'] && file_exists( $file ) ) wp_delete_file( $file ); update_attached_file( $attachment, $saved['path'] ); wp_update_attachment_metadata( $attachment, wp_generate_attachment_metadata( $attachment, $saved['path'] ) ); return $saved['path'];
	}

	public static function post_metabox_html( $post ) { $keywords = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $post->ID, 'digitalisimo_seo_keywords', true ) ) ) ); $endpoint = esc_url_raw( rest_url( 'digitalisimo-ai/v1/image' ) ); $nonce = wp_create_nonce( 'wp_rest' ); echo '<div id="digitalisimo-ai-article-image-tool"><p class="description">Genera una imagen WebP 16:9 contextual y asígnala como destacada.</p><p><label><strong>Texto alternativo</strong><br><input id="digitalisimo-ai-article-alt" class="widefat" value="' . esc_attr( $keywords[0] ?? $post->post_title ) . '"></label></p><p><button type="button" class="button button-primary" id="digitalisimo-ai-article-image-button" data-post-id="' . absint( $post->ID ) . '" data-keyword="' . esc_attr( $keywords[0] ?? '' ) . '">Generar imagen destacada con IA</button> <span id="digitalisimo-ai-article-image-status"></span></p><div id="digitalisimo-ai-article-image-result"></div></div>'; self::script( $endpoint, $nonce, true ); }
	public static function page() {
		if ( ! current_user_can( 'upload_files' ) ) return;
		$profiles = (array) Digitalisimo_AI::get( 'profiles' );
		$profile = (array) ( $profiles['images'] ?? array() );
		$model = $profile['model'] ?? 'gpt-image-1';
		$quality = Digitalisimo_AI::get( 'image_quality' ) ?: 'medium';
		echo '<div class="wrap"><h1>Digitalísimo IA Tools · Imágenes de artículos</h1><p>Genera imágenes destacadas contextualizadas por título, keyword y contenido. Todas terminan como WebP optimizado, horizontal 1536 × 864 px (16:9), y quedan en la Biblioteca para revisión.</p><div class="notice notice-info inline"><p><strong>Modelo activo:</strong> ' . esc_html( $model ) . ' · <strong>Calidad:</strong> ' . esc_html( $quality ) . ' · <a href="' . esc_url( admin_url( 'admin.php?page=digitalisimo-ai&tab=images' ) ) . '">Cambiar modelo y calidad</a></p></div><p><label for="digitalisimo-ai-image-post"><strong>ID de entrada opcional</strong></label><br><input id="digitalisimo-ai-image-post" class="small-text" type="number" min="0" placeholder="Ej. 1622"></p><p><label for="digitalisimo-ai-image-prompt"><strong>Instrucción adicional opcional</strong></label><br><textarea id="digitalisimo-ai-image-prompt" class="large-text" rows="5" maxlength="2000" placeholder="El contexto del artículo se añadirá automáticamente."></textarea></p><p><label for="digitalisimo-ai-image-alt"><strong>Texto alternativo</strong><br><input id="digitalisimo-ai-image-alt" class="large-text" maxlength="180" placeholder="Descripción SEO natural de la imagen"></label></p><p><button type="button" class="button button-primary" id="digitalisimo-ai-image-generate">Generar imagen</button> <span id="digitalisimo-ai-image-status"></span></p><div id="digitalisimo-ai-image-result"></div>';
		self::history();
		echo '</div>';
		self::script( esc_url_raw( rest_url( 'digitalisimo-ai/v1/image' ) ), wp_create_nonce( 'wp_rest' ), false );
	}
	private static function history() {
		$images = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 100, 'meta_key' => self::META_GENERATED, 'meta_value' => '1', 'orderby' => 'date', 'order' => 'DESC' ) );
		$total = 0.0; $known = 0;
		foreach ( $images as $image ) { $cost = get_post_meta( $image->ID, self::META_COST, true ); if ( '' !== $cost && is_numeric( $cost ) ) { $total += (float) $cost; $known++; } }
		echo '<hr><h2>Historial y costo estimado</h2><p><strong>Total registrado:</strong> US$ ' . esc_html( number_format_i18n( $total, 4 ) ) . ' · ' . esc_html( $known ) . ' de ' . esc_html( count( $images ) ) . ' imágenes con costo estimado. Las imágenes creadas antes de activar este registro se muestran sin costo porque WordPress no conserva el uso facturado por la API.</p><p class="description">El costo es una estimación en USD para una generación desde texto. El cargo final de OpenAI puede variar si el proveedor informa tokens distintos.</p><table class="widefat striped"><thead><tr><th>Imagen</th><th>Título</th><th>Modelo</th><th>Calidad / tamaño</th><th>Costo estimado</th><th>Artículo</th><th>Fecha</th></tr></thead><tbody>';
		if ( ! $images ) echo '<tr><td colspan="7">Aún no hay imágenes generadas desde IA Tools.</td></tr>';
		foreach ( $images as $image ) {
			$cost = get_post_meta( $image->ID, self::META_COST, true ); $model = get_post_meta( $image->ID, self::META_MODEL, true ); $quality = get_post_meta( $image->ID, self::META_QUALITY, true ); $size = get_post_meta( $image->ID, self::META_SIZE, true ); $parent = $image->post_parent ? get_post( $image->post_parent ) : null;
			$thumb = wp_get_attachment_image( $image->ID, array( 96, 64 ), false, array( 'style' => 'width:96px;height:64px;object-fit:cover;border-radius:6px;' ) );
			echo '<tr><td><a href="' . esc_url( wp_get_attachment_url( $image->ID ) ) . '" target="_blank" rel="noopener">' . $thumb . '</a></td><td><a href="' . esc_url( get_edit_post_link( $image->ID ) ) . '">' . esc_html( $image->post_title ) . '</a></td><td>' . esc_html( $model ?: 'Sin registro previo' ) . '</td><td>' . esc_html( trim( $quality . ( $size ? ' · ' . $size : '' ), ' ·' ) ?: 'Sin registro previo' ) . '</td><td>' . ( '' !== $cost && is_numeric( $cost ) ? 'US$ ' . esc_html( number_format_i18n( (float) $cost, 4 ) ) : 'Sin registro previo' ) . '</td><td>' . ( $parent ? '<a href="' . esc_url( get_edit_post_link( $parent->ID ) ) . '">' . esc_html( $parent->post_title ) . '</a>' : '—' ) . '</td><td>' . esc_html( mysql2date( 'Y-m-d H:i', $image->post_date ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	private static function script( $endpoint, $nonce, $article ) {
		$button = $article ? 'digitalisimo-ai-article-image-button' : 'digitalisimo-ai-image-generate'; $status = $article ? 'digitalisimo-ai-article-image-status' : 'digitalisimo-ai-image-status'; $result = $article ? 'digitalisimo-ai-article-image-result' : 'digitalisimo-ai-image-result'; $post = $article ? 'b.dataset.postId' : '(document.getElementById("digitalisimo-ai-image-post")||{}).value||0'; $alt = $article ? 'document.getElementById("digitalisimo-ai-article-alt").value' : '(document.getElementById("digitalisimo-ai-image-alt")||{}).value||""'; $prompt = $article ? '""' : '(document.getElementById("digitalisimo-ai-image-prompt")||{}).value||""';
		$placement = $article ? 'var placeTool=function(){var tool=document.getElementById("digitalisimo-ai-article-image-tool"),featured=document.querySelector("#postimagediv .inside");if(!tool||!featured)return false;if(tool.parentNode!==featured)featured.appendChild(tool);var box=document.getElementById("digitalisimo-ai-article-image");if(box)box.style.display="none";return true;};if(!placeTool()){var observer=new MutationObserver(function(){if(placeTool())observer.disconnect();});observer.observe(document.body,{childList:true,subtree:true});setTimeout(function(){observer.disconnect();},15000);}' : '';
		echo '<script>document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("' . esc_js( $button ) . '"),s=document.getElementById("' . esc_js( $status ) . '"),r=document.getElementById("' . esc_js( $result ) . '");' . $placement . 'if(!b)return;b.addEventListener("click",function(){var post=' . $post . ',alt=' . $alt . ',prompt=' . $prompt . ';b.disabled=true;s.textContent="Generando imagen destacada…";fetch("' . esc_js( $endpoint ) . '",{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":"' . esc_js( $nonce ) . '"},body:JSON.stringify({post_id:post,prompt:prompt,image_alt:alt})}).then(function(x){return x.json()}).then(function(x){if(x.url){var cost=(typeof x.cost_usd==="number")?" · costo estimado US$ "+x.cost_usd.toFixed(4):"";s.textContent="Imagen destacada lista en WebP."+cost;r.innerHTML="<p><a href=\""+x.url+"\" target=\"_blank\" rel=\"noopener\"><img style=\"max-width:640px;height:auto\" src=\""+x.url+"\" alt=\"Imagen generada\"></a></p>"}else s.textContent=x.message||"No fue posible generar la imagen."}).catch(function(){s.textContent="Error de conexión."}).finally(function(){b.disabled=false})})})</script>';
	}
}
