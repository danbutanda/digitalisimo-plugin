<?php
/** Element Pack Pro 9.9.1 `bdt-post-comments` → `post-comments`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'post-comments',
	'classes'   => array(),
	'defaults'  => array(),
);
