<?php
/** Cabecera de respaldo (sin plantilla del constructor): marca, lema y menú «menu-1». */
defined( 'ABSPATH' ) || exit;

$digitalisimo_site_name   = get_bloginfo( 'name' );
$digitalisimo_tagline     = get_bloginfo( 'description', 'display' );
$digitalisimo_header_menu = wp_nav_menu(
	array(
		'theme_location' => 'menu-1',
		'fallback_cb'    => false,
		'container'      => false,
		'echo'           => false,
	)
);
?>

<header id="site-header" class="site-header">

	<div class="site-branding">
		<?php
		if ( has_custom_logo() ) {
			the_custom_logo();
		} elseif ( $digitalisimo_site_name ) {
			?>
			<div class="site-title">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr__( 'Home', 'digitalisimo-theme' ); ?>" rel="home">
					<?php echo esc_html( $digitalisimo_site_name ); ?>
				</a>
			</div>
			<?php if ( $digitalisimo_tagline ) : ?>
			<p class="site-description">
				<?php echo esc_html( $digitalisimo_tagline ); ?>
			</p>
			<?php endif; ?>
		<?php } ?>
	</div>

	<?php if ( $digitalisimo_header_menu ) : ?>
		<nav class="site-navigation" aria-label="<?php echo esc_attr__( 'Main menu', 'digitalisimo-theme' ); ?>">
			<?php
			// wp_nav_menu() ya escapa su salida.
			echo $digitalisimo_header_menu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</nav>
	<?php endif; ?>
</header>
