<?php
defined( 'ABSPATH' ) || exit;
/** Método editorial único consumible por WordPress, API y clientes MCP. */
class Digitalisimo_Integrations_Content_Playbook {
	private static function resource() { static $profile = null; if ( null !== $profile ) return $profile; $file = DIGITALISIMO_INTEGRATIONS_DIR . 'resources/editorial-profile.json'; $data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array(); $profile = is_array( $data ) ? $data : array(); return $profile; }
	public static function defaults() { $p = self::resource(); return array(
		'content_editorial_voice' => $p['voice'] ?? '', 'content_editorial_context' => $p['context'] ?? '', 'content_editorial_cta' => $p['cta'] ?? '', 'content_editorial_method' => $p['method'] ?? '',
	); }
	public static function version() { return (string) ( self::resource()['version'] ?? 'sin-versión' ); }

	public static function profile() { $out = array( 'id' => self::resource()['id'] ?? 'digitalisimo-editorial', 'version' => self::version() ); foreach ( self::defaults() as $key => $value ) $out[ str_replace( 'content_editorial_', '', $key ) ] = (string) Digitalisimo_Integrations_Settings::get( $key, $value ); return $out; }
	public static function prompt( $primary, $secondary = array(), $title = '' ) { $p = self::profile(); return "Método editorial DIGITALISIMO:\nVoz: {$p['voice']}\nContexto: {$p['context']}\nCTA: {$p['cta']}\nReglas: {$p['method']}\n\nKeyword principal: {$primary}\nKeywords secundarias: " . implode( ', ', array_map( 'sanitize_text_field', (array) $secondary ) ) . "\nTítulo sugerido: {$title}\n\nDevuelve JSON válido con title, seo_title, meta_description (120-160 caracteres), secondary_keywords y content (HTML sin H1)."; }
	public static function generate( $primary, $secondary = array(), $title = '' ) { if ( ! class_exists( 'Digitalisimo_AI' ) ) return new WP_Error( 'digitalisimo_ai_missing', 'Activa Digitalisimo IA Tools y configura un proveedor para generar contenido.' ); $answer = Digitalisimo_AI::complete( 'content', self::prompt( $primary, $secondary, $title ), 'Genera solamente el JSON solicitado; usa datos proporcionados y no inventes servicios, resultados ni enlaces.' ); if ( is_wp_error( $answer ) ) return $answer; $data = json_decode( trim( preg_replace( '/^```(?:json)?|```$/m', '', $answer ) ), true ); if ( ! is_array( $data ) || empty( $data['content'] ) || empty( $data['title'] ) || empty( $data['meta_description'] ) ) return new WP_Error( 'digitalisimo_ai_format', 'La IA no devolvió un borrador estructurado válido.' ); $data['content'] = preg_replace( '#^\s*<h1\b[^>]*>.*?</h1>\s*#is', '', wp_kses_post( $data['content'] ), 1 ); return $data; }
}
