<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<?php digitalisimo_theme_viewport(); ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
wp_body_open();
digitalisimo_theme_skip_link();
do_action( 'digitalisimo_before_header' );
do_action( 'digitalisimo_header' ); // 5: plantilla del constructor; 10: respaldo (retirado si hubo plantilla).
do_action( 'digitalisimo_after_header' );
