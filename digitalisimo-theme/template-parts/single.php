<?php
/** Entrada, página o tipo de contenido sin plantilla «single» del constructor. */
defined( 'ABSPATH' ) || exit;

while ( have_posts() ) :
	the_post();
	digitalisimo_theme_skip_target_printed( true );
	?>

<main id="content" <?php post_class( 'site-main' ); ?>>

	<?php do_action( 'digitalisimo_before_main_content' ); ?>

	<?php do_action( 'digitalisimo_before_entry_title' ); ?>
	<?php if ( digitalisimo_theme_show_title() ) : ?>
		<div class="page-header">
			<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
		</div>
	<?php endif; ?>
	<?php do_action( 'digitalisimo_after_entry_title' ); ?>

	<div class="page-content">
		<?php the_content(); ?>

		<?php wp_link_pages(); ?>

		<?php if ( has_tag() ) : ?>
		<div class="post-tags">
			<?php the_tags( '<span class="tag-links">' . esc_html__( 'Tagged ', 'digitalisimo-theme' ), ', ', '</span>' ); ?>
		</div>
		<?php endif; ?>
	</div>

	<?php do_action( 'digitalisimo_after_entry_content' ); ?>

	<?php do_action( 'digitalisimo_before_comments' ); ?>
	<?php comments_template(); ?>

	<?php do_action( 'digitalisimo_after_main_content' ); ?>

</main>

	<?php
endwhile;
