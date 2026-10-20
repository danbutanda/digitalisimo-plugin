<?php
/*
 * Respaldo de la plantilla de página «lienzo» mientras DIGITALÍSIMO Elements no está activo: sólo el contenido,
 * sin cabecera ni pie. No se declara como plantilla de página: la elige el constructor, nunca el editor de WordPress.
 */
defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<?php digitalisimo_theme_viewport(); ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'digitalisimo-canvas' ); ?>>
<?php
wp_body_open();
digitalisimo_theme_skip_target_printed( true );
?>
<main id="content" class="site-main">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>
<?php wp_footer(); ?>
</body>
</html>
