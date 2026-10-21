<?php
// Aplica la transición de F-front (modules/digitalisimo-front/class-transition.php) durante la preparación, como la
// primera visita de un sitio que actualiza: si la dejara a WP-Cron, su vaciado de CSS y cachés correría mientras Chrome
// recorre las páginas y borraría los archivos de uploads/elementor/css/ que una página ya pintada está pidiendo.
require '/wordpress/wp-load.php';
$transition = '\Digitalisimo\Elements\Front\Transition';
if ( class_exists( $transition ) && method_exists( $transition, 'run_now' ) ) {
	file_put_contents( '/wordpress/wp-content/probe/transition.json', wp_json_encode( $transition::run_now() ) );
}
