<?php
/** Una petición REST sin sesión: /wp/v2/posts (sin tocar) y después el índice / (sin caché). */
define( 'REST_REQUEST', true );
require '/wordpress/wp-load.php';
wp_set_current_user( 0 );
$server = rest_get_server();
$run = function ( $route ) use ( $server ) {
	$GLOBALS['wp']->query_vars['rest_route'] = $route;
	$request  = new WP_REST_Request( 'GET', $route );
	$response = apply_filters( 'rest_post_dispatch', rest_ensure_response( $server->dispatch( $request ) ), $server, $request );
	return array( 'status' => $response->get_status(), 'headers' => $response->get_headers(), 'donotcache' => defined( 'DONOTCACHEPAGE' ), 'nocache' => $GLOBALS['digi_ph_nocache'] ?? array() );
};
$out = array( 'posts' => $run( '/wp/v2/posts' ), 'noroute' => null, 'index' => null );
$out['noroute'] = $run( '/digi-nada/v1/x' );
$out['index'] = $run( '/' );
file_put_contents( '/digi-ph/rest.json', wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
