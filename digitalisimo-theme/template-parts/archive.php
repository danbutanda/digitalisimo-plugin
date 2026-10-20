<?php
/** Archivo, blog o taxonomía sin plantilla «archive» del constructor. */
defined( 'ABSPATH' ) || exit;

digitalisimo_theme_skip_target_printed( true );
?>
<main id="content" class="site-main">

	<?php do_action( 'digitalisimo_before_main_content' ); ?>

	<?php if ( digitalisimo_theme_show_title() ) : ?>
		<div class="page-header">
			<?php
			the_archive_title( '<h1 class="entry-title">', '</h1>' );
			the_archive_description( '<p class="archive-description">', '</p>' );
			?>
		</div>
	<?php endif; ?>

	<div class="page-content">
		<?php
		while ( have_posts() ) {
			the_post();
			$digitalisimo_link = get_permalink();
			?>
			<article class="post">
				<?php
				do_action( 'digitalisimo_before_entry_title' );
				printf( '<h2 class="%s"><a href="%s">%s</a></h2>', 'entry-title', esc_url( $digitalisimo_link ), wp_kses_post( get_the_title() ) );
				do_action( 'digitalisimo_after_entry_title' );
				if ( has_post_thumbnail() ) {
					printf( '<a href="%s">%s</a>', esc_url( $digitalisimo_link ), get_the_post_thumbnail( null, 'large' ) );
				}
				the_excerpt();
				?>
			</article>
		<?php } ?>
	</div>

	<?php
	global $wp_query;
	if ( $wp_query->max_num_pages > 1 ) :
		$digitalisimo_prev = is_rtl() ? '&rarr;' : '&larr;';
		$digitalisimo_next = is_rtl() ? '&larr;' : '&rarr;';
		?>
		<nav class="pagination">
			<div class="nav-previous"><?php
				/* translators: %s: HTML entity for arrow character. */
				previous_posts_link( sprintf( esc_html__( '%s Previous', 'digitalisimo-theme' ), sprintf( '<span class="meta-nav">%s</span>', $digitalisimo_prev ) ) );
			?></div>
			<div class="nav-next"><?php
				/* translators: %s: HTML entity for arrow character. */
				next_posts_link( sprintf( esc_html__( 'Next %s', 'digitalisimo-theme' ), sprintf( '<span class="meta-nav">%s</span>', $digitalisimo_next ) ) );
			?></div>
		</nav>
	<?php endif; ?>

	<?php do_action( 'digitalisimo_after_main_content' ); ?>

</main>
