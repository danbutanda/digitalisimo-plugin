<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cliente de IA propio del módulo SEO.
 *
 * Permite usar los proveedores configurados en SEO sin depender del módulo de
 * chatbot. Si el módulo AI está activo se delega en él para no duplicar
 * credenciales; en caso contrario se usan los proveedores locales.
 */
class Digitalisimo_SEO_AI_Client {

	/** Endpoints predeterminados por proveedor compatible con la API de OpenAI. */
	private static function default_url( $id ) {
		$urls = array(
			'openai'   => 'https://api.openai.com/v1/chat/completions',
			'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
			'xai'      => 'https://api.x.ai/v1/chat/completions',
		);
		return $urls[ $id ] ?? '';
	}

	/** Proveedor configurado y utilizable, o array vacío. */
	private static function provider( $id ) {
		$providers = (array) Digitalisimo_Integrations_Settings::get( 'ai_providers' );
		$provider  = (array) ( $providers[ $id ] ?? array() );
		return ( ! empty( $provider['enabled'] ) && ! empty( $provider['key'] ) ) ? $provider : array();
	}

	/** Indica si SEO puede resolver por su cuenta una petición de IA. */
	public static function available() {
		foreach ( array_keys( Digitalisimo_Integrations_Settings::ai_providers() ) as $id ) if ( self::provider( $id ) ) return true;
		return false;
	}

	/**
	 * Ejecuta una petición con el proveedor principal y, si falla, con la alternativa.
	 *
	 * @return string|WP_Error Texto de la respuesta o error descriptivo.
	 */
	public static function complete( $prompt, $system = '', $max_tokens = 200 ) {
		$order = array_values( array_unique( array_filter( array(
			sanitize_key( (string) Digitalisimo_Integrations_Settings::get( 'ai_profile_provider' ) ),
			sanitize_key( (string) Digitalisimo_Integrations_Settings::get( 'ai_profile_fallback' ) ),
		) ) ) );
		if ( ! $order ) return new WP_Error( 'digitalisimo_ai_provider', __( 'No hay proveedor de IA seleccionado.', 'digitalisimo-integrations' ) );

		$model = (string) Digitalisimo_Integrations_Settings::get( 'ai_profile_model' );
		$last  = __( 'Ningún proveedor de IA configurado respondió.', 'digitalisimo-integrations' );

		foreach ( $order as $id ) {
			$provider = self::provider( $id );
			if ( ! $provider ) continue;
			$result = self::request( $id, $provider, $model, $prompt, $system, $max_tokens );
			if ( ! is_wp_error( $result ) ) return $result;
			$last = $result->get_error_message();
		}
		return new WP_Error( 'digitalisimo_ai_failed', $last );
	}

	/** Construye y envía la petición según el dialecto de cada proveedor. */
	private static function request( $id, $provider, $model, $prompt, $system, $max_tokens ) {
		$url     = ! empty( $provider['url'] ) ? $provider['url'] : self::default_url( $id );
		$headers = array( 'Content-Type' => 'application/json' );

		if ( 'anthropic' === $id ) {
			$url = ! empty( $provider['url'] ) ? $provider['url'] : 'https://api.anthropic.com/v1/messages';
			$headers['x-api-key']         = $provider['key'];
			$headers['anthropic-version'] = '2023-06-01';
			$body = array( 'model' => $model, 'max_tokens' => $max_tokens, 'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ) );
			if ( $system ) $body['system'] = $system;
		} elseif ( 'google' === $id ) {
			$base = ! empty( $provider['url'] ) ? $provider['url'] : 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent';
			$url  = add_query_arg( 'key', rawurlencode( $provider['key'] ), $base );
			$body = array( 'contents' => array( array( 'parts' => array( array( 'text' => trim( $system . "\n\n" . $prompt ) ) ) ) ) );
		} else {
			if ( ! $url ) return new WP_Error( 'digitalisimo_ai_url', __( 'El proveedor necesita una URL compatible.', 'digitalisimo-integrations' ) );
			$headers['Authorization'] = 'Bearer ' . $provider['key'];
			$messages = array();
			if ( $system ) $messages[] = array( 'role' => 'system', 'content' => $system );
			$messages[] = array( 'role' => 'user', 'content' => $prompt );
			$body = array( 'model' => $model, 'messages' => $messages, 'temperature' => 0, 'max_tokens' => $max_tokens );
		}

		$response = wp_remote_post( $url, array( 'timeout' => 25, 'headers' => $headers, 'body' => wp_json_encode( $body ) ) );
		if ( is_wp_error( $response ) ) return $response;

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) return new WP_Error( 'digitalisimo_ai_http', sprintf( /* translators: 1: proveedor, 2: código HTTP */ __( '%1$s devolvió HTTP %2$d.', 'digitalisimo-integrations' ), $id, (int) $code ) );

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = $data['choices'][0]['message']['content'] ?? $data['content'][0]['text'] ?? $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
		$text = trim( (string) $text );
		return $text ? $text : new WP_Error( 'digitalisimo_ai_empty', __( 'El proveedor devolvió una respuesta vacía.', 'digitalisimo-integrations' ) );
	}
}
