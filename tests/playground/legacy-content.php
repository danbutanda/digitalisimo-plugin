<?php
/**
 * Contenido de prueba que depende de plugins reales instalados con EXTRA_PLUGINS, creado en una
 * petición posterior a su activación. Guarda en `digi_ab_markers` los IDs que los casos usan como
 * marcadores (`__BBP_FORUM__`, `__BBP_TOPIC__`, `__BBP_REPLY__`, `__BBP_TAG__`, `__WC_PRODUCT__`, `__ACF_IMAGE_1__`, `__ACF_IMAGE_2__`).
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
	if ( function_exists( 'acf_import_field_group' ) && empty( $markers['__ACF_IMAGE_1__'] ) ) {
		// Grupo de prueba de los widgets ACF: un repetidor con texto, imagen y enlace, y una galería, en páginas.
		acf_import_field_group( array(
			'key'      => 'group_digi_ab',
			'title'    => 'Digi A/B',
			'fields'   => array(
				array( 'key' => 'field_digi_ab_faq', 'label' => 'Preguntas', 'name' => 'digi_ab_faq', 'type' => 'repeater', 'sub_fields' => array(
					array( 'key' => 'field_digi_ab_q', 'label' => 'Pregunta', 'name' => 'question', 'type' => 'text' ),
					array( 'key' => 'field_digi_ab_a', 'label' => 'Respuesta', 'name' => 'answer', 'type' => 'textarea', 'new_lines' => '' ),
					array( 'key' => 'field_digi_ab_img', 'label' => 'Foto', 'name' => 'photo', 'type' => 'image', 'return_format' => 'array' ),
					array( 'key' => 'field_digi_ab_link', 'label' => 'Destino', 'name' => 'target', 'type' => 'url' ),
				) ),
				array( 'key' => 'field_digi_ab_gallery', 'label' => 'Galería', 'name' => 'digi_ab_gallery', 'type' => 'gallery', 'return_format' => 'array' ),
			),
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ) ),
		) );
		foreach ( array( 1, 2 ) as $n ) {
			$markers[ '__ACF_IMAGE_' . $n . '__' ] = (string) wp_insert_attachment( array( 'post_title' => 'Imagen ACF ' . $n, 'post_mime_type' => 'image/png', 'guid' => home_url( '/wp-content/uploads/digi-ab-acf-' . $n . '.png' ) ), 'digi-ab-acf-' . $n . '.png' );
			update_post_meta( (int) $markers[ '__ACF_IMAGE_' . $n . '__' ], '_wp_attachment_image_alt', 'Imagen ACF ' . $n );
		}
	}
	update_option( 'digi_ab_markers', $markers );
	restore_current_blog();
}
