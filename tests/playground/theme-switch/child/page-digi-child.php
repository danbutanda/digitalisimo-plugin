<?php
/**
 * Template Name: Plantilla del hijo
 *
 * Plantilla de página que sólo existe en el hijo sintético (S4).
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="content" class="site-main digi-child-template">
	<?php
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	?>
</main>
<?php
get_footer();
