<?php
/**
 * Preparación del ensayo «la landing con la marca del sitio» (ia-assistant-marca.sh, caso plomerito 3 del 2026-11-25):
 * una tienda INVENTADA (ningún dato de un sitio real: el repositorio es público) con una marca propia que no se parece a
 * la de DIGITALÍSIMO: morado #7B2CBF y naranja #FF8C1A, texto #2B2B2B, Montserrat en los títulos y Lato en el texto.
 *   - el tema DIGITALÍSIMO y una cabecera y un pie hechos con Elements (constructor de temas, en todo el sitio): la
 *     cabecera morada con el nombre y un botón naranja; el pie con cuatro garantías con icono (como las del caso), el
 *     WhatsApp de la tienda y las políticas;
 *   - CASE=kit: el kit del sitio con esos colores y tipografías, y la cabecera y el pie apuntando a sus globales;
 *     CASE=fabrica: el kit con los valores de fábrica (azul claro, verde, Roboto) y la cabecera y el pie con los colores
 *     y las fuentes escritos a mano (lo que pasa en muchos sitios reales);
 *   - 12 productos con foto, en tres categorías con imagen, precios de $189 a $2,890;
 *   - transferencia y pago contra entrega, envío a domicilio y envío gratis desde $1,500, devoluciones en 30 días;
 *   - Anthropic con la clave que el arnés deja en /digi-ias/k (se lee, se guarda en la opción y el archivo se borra).
 * Deja /digi-ias/ready.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$dir = '/digi-ias';
try {
	$mode = trim( (string) @file_get_contents( $dir . '/mode.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$case = trim( (string) @file_get_contents( $dir . '/case.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$case = in_array( $case, array( 'kit', 'fabrica', 'producto', 'negro', 'digitalisimo' ), true ) ? $case : 'kit';
	// producto: el mismo sitio del caso kit, con la landing de un solo producto.
	$key  = trim( (string) @file_get_contents( $dir . '/k' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	@unlink( $dir . '/k' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( 'real' !== $mode || strlen( $key ) < 20 ) {
		throw new RuntimeException( 'El ensayo «marca» sólo corre con la API real y su clave.' );
	}
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-ias-no-autoupdate.php', "<?php\nadd_filter( 'automatic_updater_disabled', '__return_true', PHP_INT_MAX );\nadd_filter( 'auto_update_plugin', '__return_false', PHP_INT_MAX );\n" );
	update_option( 'timezone_string', 'America/Mexico_City' );
	update_option( 'permalink_structure', '/%postname%/' );
	update_option( 'blogname', 'Ferretería La Tuerca Morada' );
	update_option( 'blogdescription', 'Herramientas, pinturas e iluminación para tu casa' );
	foreach ( array( 'woocommerce/woocommerce.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php' ) as $plugin ) {
		$done = activate_plugin( $plugin );
		if ( is_wp_error( $done ) ) {
			throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $done->get_error_message() );
		}
	}
	if ( wp_get_theme( 'digitalisimo-theme' )->exists() ) {
		switch_theme( 'digitalisimo-theme' );
	}
	if ( class_exists( 'WC_Install' ) ) {
		WC_Install::install();
		WC_Install::create_pages();
	}
	update_option( 'elementor_mcp_enabled', true );
	update_option( 'elementor_onboarded', true );
	update_option( 'digitalisimo_elements_woo_mcp', array( 'mode' => 'write', 'own' => array( 'mode' ) ), false );
	update_option( 'woocommerce_currency', 'MXN' );
	update_option( 'woocommerce_store_address', 'Calle Ficticia 45' );
	update_option( 'woocommerce_store_city', 'Saltillo' );
	update_option( 'woocommerce_store_postcode', '25000' );
	update_option( 'woocommerce_default_country', 'MX:COA' );
	update_option( 'woocommerce_bacs_settings', array( 'enabled' => 'yes', 'title' => 'Transferencia bancaria', 'description' => 'Paga por transferencia.' ) );
	update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Pago contra entrega', 'description' => 'Pagas al recibir.' ) );
	if ( class_exists( 'WC_Shipping_Zone' ) ) {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'México' );
		$zone->add_location( 'MX', 'country' );
		$zone->save();
		$zone->add_shipping_method( 'flat_rate' );
		$free = $zone->add_shipping_method( 'free_shipping' );
		update_option( 'woocommerce_free_shipping_' . $free . '_settings', array( 'title' => 'Envío gratis', 'requires' => 'min_amount', 'min_amount' => '1500' ) );
	}

	// La marca inventada (o, en «negro», una en negro y gris; en «digitalisimo», la paleta DIGITALÍSIMO como la de
	// digitalisimo.lat: entonces esos colores SON los del sitio).
	$purple = '#7B2CBF';
	$orange = '#FF8C1A';
	$ink    = '#2B2B2B';
	if ( 'negro' === $case ) {
		$purple = '#000000';
		$orange = '#333333';
		$ink    = '#1A1A1A';
	} elseif ( 'digitalisimo' === $case ) {
		$purple = '#1592DA';
		$orange = '#00A86B';
		$ink    = '#1F252C';
	}
	$kit_case = 'fabrica' === $case ? 'fabrica' : 'kit';

	// El kit activo (lo crea aquí si el núcleo aún no lo hizo).
	$kit = (int) get_option( 'elementor_active_kit' );
	if ( $kit <= 0 || ! get_post( $kit ) ) {
		$kit = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Kit predeterminado' ) );
		update_post_meta( $kit, '_elementor_template_type', 'kit' );
		update_post_meta( $kit, '_elementor_edit_mode', 'builder' );
		update_option( 'elementor_active_kit', $kit );
	}
	$kit_settings = 'kit' === $kit_case ? array(
		'system_colors'     => array(
			array( '_id' => 'primary', 'title' => 'Morado', 'color' => $purple ),
			array( '_id' => 'secondary', 'title' => 'Gris', 'color' => '#5A5A5A' ),
			array( '_id' => 'text', 'title' => 'Texto', 'color' => $ink ),
			array( '_id' => 'accent', 'title' => 'Naranja', 'color' => $orange ),
		),
		'custom_colors'     => array( array( '_id' => 'a1b2c3d', 'title' => 'Lila claro', 'color' => '#F3ECFA' ) ),
		'system_typography' => array(
			array( '_id' => 'primary', 'title' => 'Títulos', 'typography_typography' => 'custom', 'typography_font_family' => 'Montserrat', 'typography_font_weight' => '700' ),
			array( '_id' => 'secondary', 'title' => 'Subtítulos', 'typography_typography' => 'custom', 'typography_font_family' => 'Montserrat', 'typography_font_weight' => '600' ),
			array( '_id' => 'text', 'title' => 'Texto', 'typography_typography' => 'custom', 'typography_font_family' => 'Lato', 'typography_font_weight' => '400' ),
			array( '_id' => 'accent', 'title' => 'Botones', 'typography_typography' => 'custom', 'typography_font_family' => 'Lato', 'typography_font_weight' => '700' ),
		),
	) : array(
		// Los valores de fábrica del kit (Global_Colors y Global_Typography del núcleo).
		'system_colors'     => array(
			array( '_id' => 'primary', 'title' => 'Primary', 'color' => '#6EC1E4' ),
			array( '_id' => 'secondary', 'title' => 'Secondary', 'color' => '#54595F' ),
			array( '_id' => 'text', 'title' => 'Text', 'color' => '#7A7A7A' ),
			array( '_id' => 'accent', 'title' => 'Accent', 'color' => '#61CE70' ),
		),
		'system_typography' => array(
			array( '_id' => 'primary', 'title' => 'Primary', 'typography_typography' => 'custom', 'typography_font_family' => 'Roboto', 'typography_font_weight' => '600' ),
			array( '_id' => 'secondary', 'title' => 'Secondary', 'typography_typography' => 'custom', 'typography_font_family' => 'Roboto Slab', 'typography_font_weight' => '400' ),
			array( '_id' => 'text', 'title' => 'Text', 'typography_typography' => 'custom', 'typography_font_family' => 'Roboto', 'typography_font_weight' => '400' ),
			array( '_id' => 'accent', 'title' => 'Accent', 'typography_typography' => 'custom', 'typography_font_family' => 'Roboto', 'typography_font_weight' => '500' ),
		),
	);
	update_post_meta( $kit, '_elementor_page_settings', $kit_settings );
	delete_post_meta( $kit, '_elementor_css' );

	// Un color: el global del kit (CASE=kit) o escrito a mano (CASE=fabrica).
	$color = function ( array $s, $key, $hex, $global ) use ( $kit_case ) {
		if ( 'kit' === $kit_case && '' !== $global ) {
			$s['__globals__'][ $key ] = 'globals/colors?id=' . $global;
			$s[ $key ]                = '';
		} else {
			$s[ $key ] = $hex;
		}
		return $s;
	};
	$font = function ( array $s, $prefix, $family, $global ) use ( $kit_case ) {
		if ( 'kit' === $kit_case ) {
			$s['__globals__'][ $prefix . '_typography' ] = 'globals/typography?id=' . $global;
		} else {
			$s[ $prefix . '_typography' ]  = 'custom';
			$s[ $prefix . '_font_family' ] = $family;
		}
		return $s;
	};
	$n   = 0;
	$id  = function () use ( &$n ) {
		return substr( md5( 'marca' . ( ++$n ) ), 0, 7 );
	};
	$w   = function ( $type, array $s ) use ( $id ) {
		return array( 'id' => $id(), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $s, 'elements' => array() );
	};
	$box = function ( array $s, array $children ) use ( $id ) {
		return array( 'id' => $id(), 'elType' => 'container', 'settings' => $s, 'elements' => $children, 'isInner' => false );
	};

	// Páginas reales: políticas y contacto.
	$page = function ( $title, $slug, $content ) {
		$found = get_page_by_path( $slug );
		if ( $found ) {
			wp_update_post( array( 'ID' => $found->ID, 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $content ) );
			return (int) $found->ID;
		}
		return (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ) );
	};
	$privacy = $page( 'Aviso de privacidad', 'aviso-de-privacidad', '<p>La Tuerca Morada protege tus datos personales.</p>' );
	$terms   = $page( 'Términos y condiciones', 'terminos-y-condiciones', '<p>Condiciones de venta de La Tuerca Morada.</p>' );
	$refund  = $page( 'Devoluciones', 'devoluciones', '<p>Tienes 30 días para devolver tu producto sin uso y con su empaque. Te regresamos tu dinero en 5 días hábiles.</p>' );
	update_option( 'wp_page_for_privacy_policy', $privacy );
	update_option( 'woocommerce_terms_page_id', $terms );
	update_option( 'woocommerce_refund_returns_page_id', $refund );

	// Cabecera: morada, con el nombre en blanco y un botón naranja a la tienda.
	$shop   = (int) wc_get_page_id( 'shop' );
	$header = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Cabecera' ) );
	update_post_meta( $header, '_elementor_template_type', 'header' );
	update_post_meta( $header, '_elementor_edit_mode', 'builder' );
	update_post_meta( $header, '_elementor_conditions', array( 'include/general' ) );
	$title  = $font( $color( array( 'title' => 'La Tuerca Morada', 'header_size' => 'h2', 'title_color' => '#FFFFFF', 'typography_font_size' => array( 'unit' => 'px', 'size' => 28, 'sizes' => array() ) ), 'title_color', '#FFFFFF', '' ), 'typography', 'Montserrat', 'primary' );
	$button = $font( $color( $color( array( 'text' => 'Ver la tienda', 'link' => array( 'url' => get_permalink( $shop ) ), 'button_text_color' => $ink ), 'background_color', $orange, 'accent' ), 'button_text_color', $ink, 'text' ), 'typography', 'Lato', 'accent' );
	// La cabecera es FIJA (sticky del constructor), como en muchos sitios: la barra de cierre no debe taparla.
	$hdata  = array( $box( $color( array( 'sticky' => 'top', 'sticky_on' => array( 'desktop', 'tablet', 'mobile' ), 'z_index' => 100, 'background_background' => 'classic', 'flex_direction' => 'row', 'flex_justify_content' => 'space-between', 'flex_align_items' => 'center', 'padding' => array( 'unit' => 'px', 'top' => '18', 'right' => '24', 'bottom' => '18', 'left' => '24', 'isLinked' => false ) ), 'background_color', $purple, 'primary' ), array( $w( 'heading', $title ), $w( 'button', $button ) ) ) );
	update_post_meta( $header, '_elementor_data', wp_slash( wp_json_encode( $hdata ) ) );

	// Pie: cuatro garantías con icono morado, el WhatsApp y las políticas.
	$footer = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Pie' ) );
	update_post_meta( $footer, '_elementor_template_type', 'footer' );
	update_post_meta( $footer, '_elementor_edit_mode', 'builder' );
	update_post_meta( $footer, '_elementor_conditions', array( 'include/general' ) );
	$guarantee = function ( $icon, $t, $d ) use ( $w, $color, $font, $purple, $ink ) {
		$s = array( 'selected_icon' => array( 'value' => $icon, 'library' => 'fa-solid' ), 'title_text' => $t, 'description_text' => $d, 'position' => 'left' );
		$s = $color( $color( $color( $s, 'primary_color', $purple, 'primary' ), 'title_color', $ink, 'text' ), 'description_color', $ink, 'text' );
		return $w( 'icon-box', $font( $font( $s, 'title_typography', 'Montserrat', 'primary' ), 'description_typography', 'Lato', 'text' ) );
	};
	$fdata = array(
		$box(
			array( 'background_background' => 'classic', 'background_color' => '#F3ECFA', 'flex_direction' => 'row', 'padding' => array( 'unit' => 'px', 'top' => '40', 'right' => '24', 'bottom' => '40', 'left' => '24', 'isLinked' => false ) ),
			array(
				$guarantee( 'fas fa-award', 'Marcas originales', 'Herramientas con garantía del fabricante.' ),
				$guarantee( 'fas fa-undo', 'Devoluciones en 30 días', 'Si no es lo que esperabas, te regresamos tu dinero.' ),
				$guarantee( 'fas fa-truck', 'Envíos a todo México', 'Gratis en compras desde $1,500.' ),
				$guarantee( 'fas fa-lock', 'Pago 100% seguro', 'Tus pagos están protegidos.' ),
			)
		),
		$box(
			$color( array( 'background_background' => 'classic', 'flex_direction' => 'column', 'padding' => array( 'unit' => 'px', 'top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'isLinked' => false ) ), 'background_color', $purple, 'primary' ),
			array(
				$w( 'text-editor', $font( $color( array( 'editor' => '<p>La Tuerca Morada · <a href="https://wa.me/5218440000123">WhatsApp 844 000 0123</a> · <a href="' . esc_url( get_permalink( $privacy ) ) . '">Aviso de privacidad</a> · <a href="' . esc_url( get_permalink( $refund ) ) . '">Devoluciones</a></p>', 'text_color' => '#FFFFFF', 'link_color' => '#FFFFFF' ), 'text_color', '#FFFFFF', '' ), 'typography', 'Lato', 'text' ) ),
			)
		),
	);
	update_post_meta( $footer, '_elementor_data', wp_slash( wp_json_encode( $fdata ) ) );
	update_option( 'elementor_pro_theme_builder_conditions', array( 'header' => array( $header => array( 'include/general' ) ), 'footer' => array( $footer => array( 'include/general' ) ) ) );

	// Imágenes (generadas aquí: nada se descarga).
	$image = function ( $name, array $rgb, $title ) {
		$upload = wp_upload_dir();
		$file   = trailingslashit( $upload['path'] ) . $name . '.png';
		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$im = imagecreatetruecolor( 600, 600 );
			imagefill( $im, 0, 0, imagecolorallocate( $im, 245, 245, 245 ) );
			imagefilledellipse( $im, 300, 300, 380, 380, imagecolorallocate( $im, $rgb[0], $rgb[1], $rgb[2] ) );
			imagefilledrectangle( $im, 260, 120, 340, 480, imagecolorallocate( $im, 60, 60, 60 ) );
			imagepng( $im, $file );
			imagedestroy( $im );
		} else {
			file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) );
		}
		$aid = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $title, 'post_status' => 'inherit' ), $file );
		wp_update_attachment_metadata( $aid, wp_generate_attachment_metadata( $aid, $file ) );
		update_post_meta( $aid, '_wp_attachment_image_alt', $title );
		return (int) $aid;
	};
	$cats = array();
	foreach ( array( 'Herramientas' => array( 120, 120, 130 ), 'Pinturas' => array( 200, 80, 60 ), 'Iluminación' => array( 230, 190, 60 ) ) as $cat => $rgb ) {
		$term = wp_insert_term( $cat, 'product_cat' );
		$tid  = is_wp_error( $term ) ? (int) $term->get_error_data() : (int) $term['term_id'];
		update_term_meta( $tid, 'thumbnail_id', $image( 'cat-' . sanitize_title( $cat ), $rgb, $cat ) );
		$cats[ $cat ] = $tid;
	}
	$catalog = array(
		array( 'Taladro inalámbrico 20 V', 2890, 'Herramientas' ),
		array( 'Juego de desarmadores 12 piezas', 349, 'Herramientas' ),
		array( 'Martillo de uña 16 oz', 189, 'Herramientas' ),
		array( 'Pinza de presión 10 pulgadas', 259, 'Herramientas' ),
		array( 'Pintura vinílica blanca 19 L', 1490, 'Pinturas' ),
		array( 'Esmalte azul marino 4 L', 690, 'Pinturas' ),
		array( 'Rodillo profesional 9 pulgadas', 219, 'Pinturas' ),
		array( 'Impermeabilizante 5 años 19 L', 1990, 'Pinturas' ),
		array( 'Foco LED 12 W luz cálida (4 piezas)', 239, 'Iluminación' ),
		array( 'Lámpara solar para jardín', 459, 'Iluminación' ),
		array( 'Reflector LED 50 W', 529, 'Iluminación' ),
		array( 'Tira LED 5 m con control', 389, 'Iluminación' ),
	);
	$products = array();
	foreach ( $catalog as $i => $row ) {
		$p = new WC_Product_Simple();
		$p->set_name( $row[0] );
		$p->set_regular_price( (string) $row[1] );
		$p->set_short_description( 'Calidad para tu casa: ' . $row[0] . '.' );
		$p->set_category_ids( array( $cats[ $row[2] ] ) );
		$p->set_image_id( $image( 'producto-' . ( $i + 1 ), array( 40 + 15 * $i, 90 + 10 * $i, 200 - 12 * $i ), $row[0] ) );
		$p->set_status( 'publish' );
		$products[] = $p->save();
	}

	$ai = array(
		'providers'             => array( 'anthropic' => array( 'enabled' => 1, 'key' => $key, 'url' => '' ) ),
		'assistant_rate_minute' => 0,
		'assistant_daily_calls' => 0,
		'assistant_concurrency' => 1,
	);
	update_option( 'digitalisimo_ai_options', $ai, false );
	$key   = '';
	$admin = get_user_by( 'login', 'admin' );
	file_put_contents(
		$dir . '/ready.json',
		wp_json_encode(
			array(
				'mode'      => $mode,
				'scenario'  => 'marca',
				'case'      => $case,
				'product'   => (int) $products[0],
				'products'  => $products,
				'brand'     => array( 'primary' => $purple, 'accent' => $orange, 'text' => $ink, 'heading_font' => 'Montserrat', 'body_font' => 'Lato' ),
				'catalog'   => array_map( static function ( $row ) { return array( 'name' => $row[0], 'price' => $row[1] ); }, $catalog ),
				'kit'       => $kit,
				'header'    => (int) $header,
				'footer'    => (int) $footer,
				'shop'      => get_permalink( $shop ),
				'pages'     => array( 'privacidad' => $privacy, 'terminos' => $terms, 'reembolso' => $refund ),
				'admin'     => (int) $admin->ID,
				'whatsapp'  => '5218440000123',
				'theme'     => get_stylesheet(),
				'last_id'   => (int) $GLOBALS['wpdb']->get_var( "SELECT MAX(ID) FROM {$GLOBALS['wpdb']->posts}" ),
				'wp'        => $GLOBALS['wp_version'],
				'php'       => PHP_VERSION,
				'woo'       => defined( 'WC_VERSION' ) ? WC_VERSION : '',
				'site_tz'   => wp_timezone_string(),
			)
		)
	);
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
