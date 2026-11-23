<?php
/**
 * Sonda del ensayo ia-provider-body (mu-plugin): `GET /wp-json/digi-pb/v1/body?provider=anthropic|openai|google`, con
 * la cabecera X-Digi-PB igual a /digi-pb/token.txt, manda como la administradora «Que puedes hacer por mi en este
 * sitio web ?» por la ruta real del Asistente (POST digitalisimo-ai/v1/assistant/message) y devuelve el cuerpo EXACTO
 * que salió hacia el proveedor (capturado en pre_http_request, que contesta como el proveedor sin tocar la red),
 * los esquemas originales de cada ability del catálogo y los eventos del Asistente. `fail=<n>` hace que el proveedor
 * responda 400 las primeras n veces con el mensaje de error real de su API sobre la primera herramienta.
 */
defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'digi-pb/v1',
			'/body',
			array(
				'methods'             => 'GET',
				'permission_callback' => function ( $request ) {
					$token = trim( (string) @file_get_contents( '/digi-pb/token.txt' ) ); // phpcs:ignore
					return '' !== $token && hash_equals( $token, (string) $request->get_header( 'x-digi-pb' ) );
				},
				'callback'            => 'digi_pb_body',
			)
		);
	}
);

function digi_pb_body( $request ) {
	$provider = sanitize_key( (string) $request->get_param( 'provider' ) ) ?: 'anthropic';
	$fail     = (int) $request->get_param( 'fail' );
	$admin    = get_user_by( 'login', 'admin' );
	wp_set_current_user( $admin->ID );
	$o                                       = get_option( 'digitalisimo_ai_options', array() );
	$o['providers'][ $provider ]['enabled'] = 1;
	$o['providers'][ $provider ]['key']     = 'sk-pb-FAKEKEY-' . $provider . '-0123456789abcdef';
	update_option( 'digitalisimo_ai_options', $o, false );
	$sent = array();
	add_filter(
		'pre_http_request',
		function ( $pre, $args, $url ) use ( &$sent, &$fail, $provider ) {
			if ( ! preg_match( '#^https://(api\.anthropic\.com|api\.openai\.com|generativelanguage\.googleapis\.com)/#', $url ) ) {
				return $pre;
			}
			// Sin decodificar a arreglos: un {} vacío tiene que seguir siendo {} en la respuesta.
			$body   = json_decode( (string) $args['body'] );
			$sent[] = array( 'url' => $url, 'body' => $body );
			if ( $fail > 0 ) {
				$fail--;
				$index = 0;
				$msg   = 'anthropic' === $provider ? array( 'type' => 'error', 'error' => array( 'type' => 'invalid_request_error', 'message' => 'tools.' . $index . '.custom.input_schema: JSON schema is invalid (simulated)' ) ) : array( 'error' => array( 'message' => 'Invalid schema for function (simulated)', 'type' => 'invalid_request_error', 'param' => 'tools[' . $index . '].function.parameters' ) );
				return array( 'headers' => array(), 'body' => wp_json_encode( $msg ), 'response' => array( 'code' => 400, 'message' => 'Bad Request' ), 'cookies' => array(), 'filename' => null );
			}
			if ( 'anthropic' === $provider ) {
				$reply = array( 'content' => array( array( 'type' => 'text', 'text' => 'Respuesta simulada.' ) ), 'stop_reason' => 'end_turn' );
			} elseif ( 'google' === $provider ) {
				$reply = array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => 'Respuesta simulada.' ) ) ), 'finishReason' => 'STOP' ) ) );
			} else {
				$reply = array( 'choices' => array( array( 'message' => array( 'role' => 'assistant', 'content' => 'Respuesta simulada.' ), 'finish_reason' => 'stop' ) ) );
			}
			return array( 'headers' => array(), 'body' => wp_json_encode( $reply ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
		},
		10,
		3
	);
	$req = new WP_REST_Request( 'POST', '/digitalisimo-ai/v1/assistant/message' );
	$req->set_param( 'message', 'Que puedes hacer por mi en este sitio web ?' );
	$req->set_param( 'provider', $provider );
	$req->set_header( Digitalisimo_AI_Assistant::HEADER, wp_create_nonce( Digitalisimo_AI_Assistant::NONCE ) );
	$res     = rest_do_request( $req );
	$schemas = array();
	foreach ( Digitalisimo_AI_Assistant_Tools::catalog() as $name => $entry ) {
		$schemas[ $name ] = array( 'tool' => $entry['tool'], 'schema' => $entry['schema'] );
	}
	return array(
		'provider' => $provider,
		'status'   => $res->get_status(),
		'events'   => $res->get_data(),
		'sent'     => $sent,
		'schemas'  => $schemas,
	);
}
