<?php
/** Página no encontrada sin plantilla del constructor. */
defined( 'ABSPATH' ) || exit;

digitalisimo_theme_skip_target_printed( true );
?>
<main id="content" class="site-main">

	<?php do_action( 'digitalisimo_before_main_content' ); ?>

	<?php if ( digitalisimo_theme_show_title() ) : ?>
		<div class="page-header">
			<h1 class="entry-title"><?php echo esc_html__( 'The page can&rsquo;t be found.', 'digitalisimo-theme' ); ?></h1>
		</div>
	<?php endif; ?>

	<div class="page-content">
		<p><?php echo esc_html__( 'It looks like nothing was found at this location.', 'digitalisimo-theme' ); ?></p>
	</div>

	<?php do_action( 'digitalisimo_after_main_content' ); ?>

</main>
