<?php
defined( 'ABSPATH' ) || exit;

/** Complementa llms.txt con recursos públicos e indexables del sitio. */
class Digitalisimo_Integrations_SEO_AI_LLMS_Tools {
	const OPTION = 'digitalisimo_integrations_options';
	public static function init() { add_filter( 'digitalisimo_seo_ai_llms_content', array( __CLASS__, 'automatic_resources' ), 20 ); add_action( 'admin_menu', array( __CLASS__, 'menu' ), 34 ); add_action( 'wp_ajax_digitalisimo_llms_search', array( __CLASS__, 'search' ) ); }
	public static function menu() { add_submenu_page( 'digitalisimo', 'SEO AI · llms.txt', 'SEO AI · llms.txt', 'manage_options', 'digitalisimo-seo-ai-llms', array( __CLASS__, 'page' ) ); }
	public static function automatic_resources( $content ) {
		$selected = array_filter( preg_split( '/\r\n|\r|\n/', (string) Digitalisimo_Integrations_Settings::get( 'seo_ai_llms_urls', '' ) ) );
		if ( $selected ) return $content;
		$posts = get_posts( array( 'post_type' => get_post_types( array( 'public' => true ), 'names' ), 'post_status' => 'publish', 'posts_per_page' => 30, 'orderby' => 'modified', 'order' => 'DESC' ) );
		if ( ! $posts ) return $content;
		$content .= "\n\n## Recursos públicos\n";
		foreach ( $posts as $post ) { $robots = (array) get_post_meta( $post->ID, 'digitalisimo_seo_robots', true ); if ( in_array( 'noindex', $robots, true ) ) continue; $content .= '- [' . $post->post_title . '](' . get_permalink( $post ) . ")\n"; }
		return $content;
	}
	public static function search() {
		check_ajax_referer( 'digitalisimo_llms_search', 'nonce' ); if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'No autorizado.' ), 403 );
		$query = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); $posts = get_posts( array( 's' => $query, 'post_type' => get_post_types( array( 'public' => true ), 'names' ), 'post_status' => 'publish', 'posts_per_page' => 15, 'orderby' => 'modified', 'order' => 'DESC' ) ); $items = array();
		foreach ( $posts as $post ) { $robots = (array) get_post_meta( $post->ID, 'digitalisimo_seo_robots', true ); if ( in_array( 'noindex', $robots, true ) ) continue; $items[] = array( 'title' => get_the_title( $post ), 'url' => get_permalink( $post ), 'modified' => get_the_modified_date( 'Y-m-d', $post ) ); }
		wp_send_json_success( $items );
	}
	private static function endpoint_status( $endpoint ) {
		$response = wp_remote_get( $endpoint, array( 'timeout' => 8, 'redirection' => 2, 'user-agent' => 'Digitalisimo llms.txt Check/1.0' ) );
		if ( is_wp_error( $response ) ) return array( false, $response->get_error_message() );
		$status = (int) wp_remote_retrieve_response_code( $response ); return array( $status >= 200 && $status < 300, 'HTTP ' . $status );
	}
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) return; $option = (array) get_option( self::OPTION, array() ); $notice = '';
		if ( isset( $_POST['digitalisimo_llms_save'] ) && check_admin_referer( 'digitalisimo_llms_save' ) ) { $option['seo_ai_llms_enabled'] = empty( $_POST['enabled'] ) ? 0 : 1; $mode = sanitize_key( wp_unslash( $_POST['mode'] ?? 'automatic' ) ); $option['seo_ai_llms_mode'] = in_array( $mode, array( 'automatic', 'manual', 'hybrid' ), true ) ? $mode : 'automatic'; $urls = array_filter( array_map( 'esc_url_raw', preg_split( '/\r\n|\r|\n/', (string) wp_unslash( $_POST['urls'] ?? '' ) ) ) ); $option['seo_ai_llms_urls'] = implode( "\n", array_unique( $urls ) ); $option['seo_ai_llms_manual'] = sanitize_textarea_field( wp_unslash( $_POST['manual'] ?? '' ) ); update_option( self::OPTION, $option, false ); update_option( 'digitalisimo_seo_ai_llms_regenerated', time(), false ); $notice = 'Configuración y contenido regenerados.'; }
		$urls = (string) ( $option['seo_ai_llms_urls'] ?? '' ); $endpoint = home_url( '/llms.txt' ); $preview = Digitalisimo_Integrations_SEO_AI::llms_content(); $http = self::endpoint_status( $endpoint );
		echo '<div class="wrap"><h1>SEO AI · llms.txt</h1><p>Es un recurso opcional para facilitar la lectura técnica; no es requisito SEO ni garantía de visibilidad.</p>'; if ( $notice ) echo '<div class="notice notice-success inline"><p>' . esc_html( $notice ) . '</p></div>';
		echo '<form method="post">'; wp_nonce_field( 'digitalisimo_llms_save' ); echo '<p><label><input type="checkbox" name="enabled" value="1" ' . checked( ! empty( $option['seo_ai_llms_enabled'] ), true, false ) . '> Activar llms.txt</label></p><p><label>Modo <select name="mode">'; foreach ( array( 'automatic' => 'Automático', 'manual' => 'Manual', 'hybrid' => 'Híbrido' ) as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $option['seo_ai_llms_mode'] ?? 'automatic', $value, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label></p><h2>Seleccionar recursos</h2><p><input class="regular-text" id="digitalisimo-llms-query" placeholder="Buscar páginas, servicios o recursos"> <button type="button" class="button" id="digitalisimo-llms-search">Buscar</button></p><ul id="digitalisimo-llms-results"></ul><p><label><strong>URLs seleccionadas</strong><br><textarea class="large-text code" rows="6" id="digitalisimo-llms-urls" name="urls" placeholder="Una URL por línea">' . esc_textarea( $urls ) . '</textarea></label></p><p><label><strong>Contenido manual</strong><br><textarea class="large-text code" rows="6" name="manual">' . esc_textarea( $option['seo_ai_llms_manual'] ?? '' ) . '</textarea></label></p>'; submit_button( 'Guardar y regenerar', 'primary', 'digitalisimo_llms_save' ); echo '</form>';
		echo '<h2>Vista previa</h2><p>URL: <a href="' . esc_url( $endpoint ) . '" target="_blank" rel="noopener">' . esc_html( $endpoint ) . '</a> · Estado observable: <strong>' . ( $http[0] ? '✓ ' : '⚠ ' ) . esc_html( $http[1] ) . '</strong> · Última regeneración: ' . esc_html( get_option( 'digitalisimo_seo_ai_llms_regenerated' ) ? date_i18n( 'Y-m-d H:i', (int) get_option( 'digitalisimo_seo_ai_llms_regenerated' ) ) : 'Nunca' ) . '</p><p class="description">La comprobación se ejecuta sólo al abrir esta pantalla. Un CDN, WAF o firewall puede producir un resultado distinto fuera de WordPress.</p><pre style="max-height:320px;overflow:auto;white-space:pre-wrap">' . esc_html( $preview ) . '</pre>';
		$nonce = wp_create_nonce( 'digitalisimo_llms_search' ); echo '<script>(function(){var q=document.getElementById("digitalisimo-llms-query"),b=document.getElementById("digitalisimo-llms-search"),r=document.getElementById("digitalisimo-llms-results"),u=document.getElementById("digitalisimo-llms-urls");b.addEventListener("click",function(){fetch(ajaxurl+"?action=digitalisimo_llms_search&nonce=' . esc_js( $nonce ) . '&q="+encodeURIComponent(q.value)).then(function(x){return x.json()}).then(function(x){r.innerHTML="";(x.data||[]).forEach(function(i){var a=document.createElement("button");a.type="button";a.className="button";a.textContent="Añadir: "+i.title;a.onclick=function(){var v=u.value.trim()?u.value.trim().split(/\\n/):[];if(v.indexOf(i.url)<0)v.push(i.url);u.value=v.join("\\n")};var li=document.createElement("li");li.appendChild(a);li.appendChild(document.createTextNode(" · "+i.modified));r.appendChild(li)})})})})();</script></div>';
	}
}
