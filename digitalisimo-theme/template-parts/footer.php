<?php
/** Pie de respaldo (sin plantilla del constructor): menú «menu-2». */
defined( 'ABSPATH' ) || exit;

$digitalisimo_footer_menu = wp_nav_menu(
	array(
		'theme_location' => 'menu-2',
		'fallback_cb'    => false,
		'container'      => false,
		'echo'           => false,
	)
);
?>
<footer id="site-footer" class="site-footer">
	<?php if ( $digitalisimo_footer_menu ) : ?>
		<nav class="site-navigation" aria-label="<?php echo esc_attr__( 'Footer menu', 'digitalisimo-theme' ); ?>">
			<?php
			// wp_nav_menu() ya escapa su salida.
			echo $digitalisimo_footer_menu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</nav>
	<?php endif; ?>
</footer>
