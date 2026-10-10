<?php
/**
 * Contenido de prueba que depende de plugins reales instalados con EXTRA_PLUGINS, creado en una
 * petición posterior a su activación. Guarda en `digi_ab_markers` los IDs que los casos usan como
 * marcadores (`__BBP_FORUM__`, `__BBP_TOPIC__`, `__BBP_REPLY__`, `__BBP_TAG__`).
 */
require '/wordpress/wp-load.php';

foreach ( array( 1, 2 ) as $blog ) {
	switch_to_blog( $blog );
	$markers = (array) get_option( 'digi_ab_markers', array() );
	if ( function_exists( 'bbp_insert_forum' ) && empty( $markers['__BBP_FORUM__'] ) ) {
		$forum = bbp_insert_forum( array( 'post_title' => 'Foro A/B', 'post_content' => 'Foro para comparar.' ) );
		$topic = bbp_insert_topic( array( 'post_parent' => $forum, 'post_title' => 'Tema A/B', 'post_content' => 'Tema para comparar.' ), array( 'forum_id' => $forum ) );
		$reply = bbp_insert_reply( array( 'post_parent' => $topic, 'post_title' => 'Respuesta A/B', 'post_content' => 'Respuesta para comparar.' ), array( 'forum_id' => $forum, 'topic_id' => $topic ) );
		$terms = wp_set_object_terms( $topic, array( 'etiqueta-ab' ), bbp_get_topic_tag_tax_id() );
		$markers['__BBP_FORUM__'] = (string) $forum;
		$markers['__BBP_TOPIC__'] = (string) $topic;
		$markers['__BBP_REPLY__'] = (string) $reply;
		$markers['__BBP_TAG__']   = is_array( $terms ) && $terms ? (string) $terms[0] : '';
	}
	update_option( 'digi_ab_markers', $markers );
	restore_current_blog();
}
