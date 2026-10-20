<?php
/** Lista de comentarios y formulario, con el mismo marcado que el tema del que venían los sitios. */
defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}

if ( ! post_type_supports( get_post_type(), 'comments' ) ) {
	return;
}

if ( ! have_comments() && ! comments_open() ) {
	return;
}

if ( comments_open() && get_option( 'thread_comments' ) ) {
	wp_enqueue_script( 'comment-reply' );
}
?>
<section id="comments" class="comments-area">

	<?php if ( have_comments() ) : ?>
		<h2 class="title-comments">
			<?php
			$digitalisimo_comments = get_comments_number();
			if ( '1' === (string) $digitalisimo_comments ) {
				echo esc_html_x( 'One Response', 'comments title', 'digitalisimo-theme' );
			} else {
				printf(
					esc_html(
						/* translators: %s: Number of comments. */
						_nx( '%s Response', '%s Responses', (int) $digitalisimo_comments, 'comments title', 'digitalisimo-theme' )
					),
					esc_html( number_format_i18n( $digitalisimo_comments ) )
				);
			}
			?>
		</h2>

		<?php the_comments_navigation(); ?>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 42,
				)
			);
			?>
		</ol>

		<?php the_comments_navigation(); ?>

	<?php endif; ?>

	<?php
	comment_form(
		array(
			'title_reply_before' => '<h2 id="reply-title" class="comment-reply-title">',
			'title_reply_after'  => '</h2>',
		)
	);
	?>

</section>
