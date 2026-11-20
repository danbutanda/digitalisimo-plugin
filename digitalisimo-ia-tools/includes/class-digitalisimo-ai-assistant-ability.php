<?php
defined( 'ABSPATH' ) || exit;

/**
 * «Abrir el Asistente IA» como función del MCP (docs/ASSISTANT.md): la Ability `digitalisimo-ia/open-assistant`.
 *
 * Devuelve la URL del Asistente en este sitio (en una red, la del sitio del MCP), opcionalmente con una conversación
 * nueva y un mensaje inicial que queda escrito en el cuadro, nunca enviado: abrir un enlace no puede hacer nada por la
 * persona. Sólo lee, con el permiso de quien usa el Asistente (`edit_posts`).
 *
 * Se expone igual que las demás herramientas de los plugins: pública para MCP (`meta.mcp.public`, el servidor genérico
 * del adaptador) y, con el MCP de DIGITALÍSIMO Elements activo, en su servidor `/wp-json/elementor/mcp` por el filtro
 * `elementor/mcp/server/tools` (allí se llama `digitalisimo-ia-open-assistant`). Sin la Abilities API (WordPress 6.9)
 * no se registra nada.
 */
final class Digitalisimo_AI_Assistant_Ability {
	const NAME     = 'digitalisimo-ia/open-assistant';
	const CATEGORY = 'digitalisimo-ia';

	public static function init() {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register' ) );
		add_filter( 'elementor/mcp/server/tools', array( __CLASS__, 'server_tools' ) );
	}

	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) || ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::CATEGORY ) ) ) {
			return;
		}
		wp_register_ability_category( self::CATEGORY, array( 'label' => 'DIGITALÍSIMO IA Tools', 'description' => 'El Asistente IA del escritorio de WordPress de este sitio.' ) );
	}

	public static function register() {
		if ( ! function_exists( 'wp_register_ability' ) || ( function_exists( 'wp_has_ability' ) && wp_has_ability( self::NAME ) ) ) {
			return;
		}
		wp_register_ability( self::NAME, self::definition() );
	}

	public static function definition() {
		return array(
			'label'               => 'Abrir el Asistente IA',
			'description'         => 'Devuelve el enlace del Asistente IA del escritorio de WordPress de este sitio para abrirlo en el navegador con la sesión de la persona. Con new_conversation empieza una conversación nueva; con message deja ese texto escrito en el cuadro del Asistente (la persona lo revisa y lo envía: nunca se envía solo). No cambia nada del sitio.',
			'category'            => self::CATEGORY,
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => array(),
				'properties'           => array(
					'new_conversation' => array( 'type' => 'boolean', 'description' => 'Abrir con una conversación nueva (por defecto, la última).' ),
					'message'          => array( 'type' => 'string', 'maxLength' => Digitalisimo_AI_Assistant::MAX_PREFILL, 'description' => 'Mensaje inicial que queda escrito, sin enviar (abre una conversación nueva).' ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'url'               => array( 'type' => 'string' ),
					'site'              => array( 'type' => 'string' ),
					'new_conversation'  => array( 'type' => 'boolean' ),
					'message_prefilled' => array( 'type' => 'boolean' ),
					'note'              => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => array( __CLASS__, 'execute' ),
			'permission_callback' => array( __CLASS__, 'permission' ),
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				'mcp'          => array( 'public' => true, 'type' => 'tool' ),
			),
		);
	}

	public static function permission( $input = null ) {
		return is_user_logged_in() && current_user_can( Digitalisimo_AI_Assistant::cap() );
	}

	public static function execute( $input = null ) {
		$input   = is_array( $input ) ? $input : array();
		$message = Digitalisimo_AI_Assistant_Tools::clip( trim( sanitize_textarea_field( (string) ( $input['message'] ?? '' ) ) ), Digitalisimo_AI_Assistant::MAX_PREFILL );
		$new     = ! empty( $input['new_conversation'] ) || '' !== $message;
		$args    = array( 'page' => Digitalisimo_AI_Assistant::PAGE );
		if ( $new ) {
			$args['new'] = '1';
		}
		if ( '' !== $message ) {
			$args['prompt'] = rawurlencode( $message );
		}
		return array(
			'url'               => add_query_arg( $args, admin_url( 'admin.php' ) ),
			'site'              => home_url( '/' ),
			'new_conversation'  => $new,
			'message_prefilled' => '' !== $message,
			'note'              => '' !== $message ? 'Abre el enlace con tu sesión de WordPress: el mensaje queda escrito en el Asistente, sin enviarse.' : 'Abre el enlace con tu sesión de WordPress.',
		);
	}

	/** El servidor MCP de DIGITALÍSIMO Elements la ofrece junto a las suyas. */
	public static function server_tools( $tools ) {
		$tools = is_array( $tools ) ? $tools : array();
		if ( function_exists( 'wp_register_ability' ) && ! in_array( self::NAME, $tools, true ) ) {
			$tools[] = self::NAME;
		}
		return $tools;
	}
}
