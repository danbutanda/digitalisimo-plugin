<?php
/*
 * Respaldo de la plantilla de página «ancho completo» mientras DIGITALÍSIMO Elements no está activo: cabecera y
 * pie del tema y el contenido sin título. No se declara como plantilla de página: la elige el constructor.
 */
defined( 'ABSPATH' ) || exit;
get_header();
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
<?php
get_footer();
