<?php
/**
 * Contenido de prueba que depende de plugins reales instalados con EXTRA_PLUGINS, creado en una
 * petición posterior a su activación. Guarda en `digi_ab_markers` los IDs que los casos usan como
 * marcadores (`__BBP_FORUM__`, `__BBP_TOPIC__`, `__BBP_REPLY__`, `__BBP_TAG__`, `__WC_PRODUCT__`).
 */
require '/wordpress/wp-load.php';
wp_set_current_user( 1 );

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
	if ( class_exists( 'WC_Product_Simple' ) && empty( $markers['__WC_PRODUCT__'] ) ) {
		// WooCommerce sólo instala sus tablas en el sitio donde se activa: cada sitio de la red se instala aquí.
		if ( class_exists( 'WC_Install' ) && ! get_option( 'woocommerce_version' ) ) {
			WC_Install::install();
		}
		$cat = wp_insert_term( 'Ropa A/B', 'product_cat' );
		foreach ( array( array( 'Camisa A/B', '25', '' ), array( 'Gorra A/B', '15', '12' ), array( 'Taza A/B', '9', '' ), array( 'Libro A/B', '30', '' ) ) as $i => $row ) {
			$product = new WC_Product_Simple();
			$product->set_name( $row[0] );
			$product->set_status( 'publish' );
			$product->set_regular_price( $row[1] );
			if ( '' !== $row[2] ) {
				$product->set_sale_price( $row[2] );
			}
			$product->set_short_description( 'Descripción corta de ' . $row[0] . '.' );
			$product->set_description( 'Contenido completo de ' . $row[0] . ' con varias palabras de prueba.' );
			$product->set_featured( 0 === $i );
			$product->set_date_created( time() - $i * HOUR_IN_SECONDS );
			if ( ! is_wp_error( $cat ) ) {
				$product->set_category_ids( array( (int) $cat['term_id'] ) );
			}
			$id = $product->save();
			if ( 0 === $i ) {
				$markers['__WC_PRODUCT__'] = (string) $id;
			}
		}
	}
	update_option( 'digi_ab_markers', $markers );
	restore_current_blog();
}
