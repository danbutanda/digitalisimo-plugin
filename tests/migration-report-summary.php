<?php
/**
 * Informe final corto de «Migrar a DIGITALÍSIMO» (pedido del propietario del 2026-11-19: «el plugin Migración sigue
 * arrojando este tipo de reporte al terminar de migrar»). Con un registro sintético parecido al de un sitio real ya
 * migrado por una versión anterior (decenas de widgets de PRO por documento, widgets globales de varias plantillas,
 * ajustes recuperados de la copia con su línea «pasan tal cual», ajustes inertes, widgets de WooCommerce sin
 * WooCommerce, extensiones de Element Pack encendidas en una página, restos de Element Pack en el sitio y un tema hijo
 * de Hello que se queda), comprueba:
 * - una línea por tipo de widget con su total y sus documentos, el widget global en una, lo recuperado en una por clave
 *   y lo inerte en una sola;
 * - sin duplicados ni contradicciones («no había nada que migrar» con conversiones, dos «Migración completada», las dos
 *   líneas del mismo ajuste, el recuento de PRO que repite lo que ya explica el informe, el tema que «pasa» y «se queda»);
 * - «Necesita tu atención» arriba, con la acción y el enlace al editor de cada documento (WooCommerce en una entrada);
 * - el detalle plegado (`<details>`) y, dentro, el detalle por documento en otro desplegable;
 * - el análisis previo plegado al terminar (y abierto antes);
 * - la parte visible sin desplegar en menos de 40 líneas;
 * - el mismo resumen en WP-CLI (sin enlaces ni detalle por documento) y en la red.
 */
namespace {
	require __DIR__ . '/fixtures/f8-wp.php';
	f8_load_migration( array( 'translator', 'context', 'report', 'native', 'migration', 'retire', 'aliases', 'runner' ) );
	require_once dirname( __DIR__ ) . '/digitalisimo-migration/includes/class-full.php';
	require_once dirname( __DIR__ ) . '/digitalisimo-migration/includes/class-screen.php';
	use Digitalisimo\Elements\Legacy\Migration;
	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Runner;
	use Digitalisimo\Migration\Full;
	use Digitalisimo\Migration\Screen;

	// El tema del sitio, de sus opciones (el doble de WordPress de f8 no los trae).
	if ( ! function_exists( 'get_template' ) ) {
		function get_template() {
			return (string) get_option( 'template', '' );
		}
	}
	if ( ! function_exists( 'get_stylesheet' ) ) {
		function get_stylesheet() {
			return (string) get_option( 'stylesheet', '' );
		}
	}

	/** El texto que se ve sin desplegar nada: cada `<details>` cerrado se queda en su `<summary>` (también anidados). */
	function summary_visible( $html ) {
		$parts  = preg_split( '#(<details\b[^>]*>|</details>)#', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out    = '';
		$hidden = 0;
		$fresh  = false;
		foreach ( $parts as $part ) {
			if ( 1 === preg_match( '#^<details\b#', $part ) ) {
				if ( $hidden ) {
					++$hidden;
				} elseif ( 1 !== preg_match( '#\sopen[\s>=]#', $part ) ) {
					$hidden = 1;
					$fresh  = true;
				}
				continue;
			}
			if ( '</details>' === $part ) {
				$hidden = max( 0, $hidden - 1 );
				continue;
			}
			if ( $hidden ) {
				if ( $fresh && preg_match( '#^\s*<summary>(.*?)</summary>#s', $part, $m ) ) {
					$out .= '<p>' . $m[1] . '</p>';
				}
				$fresh = false;
				continue;
			}
			$out .= $part;
		}
		return Full::text( $out );
	}

	/** El informe sin el detalle por documento (lo agregado y lo visible). */
	function summary_aggregate( $html ) {
		$html = (string) $html;
		while ( false !== ( $at = strpos( $html, '<details class="digi-migrate__docs">' ) ) ) {
			$html = substr( $html, 0, $at ) . substr( $html, strpos( $html, '</details>', $at ) + 10 );
		}
		return $html;
	}

	/** Líneas no vacías de un texto. */
	function summary_lines( $text ) {
		return array_values( array_filter( array_map( 'trim', explode( "\n", (string) $text ) ), 'strlen' ) );
	}


	/** «1 en 1 documento», «12 en 9 documentos». */
	function summary_in( $count, array $docs ) {
		return $count . ' en ' . count( $docs ) . ( 1 === count( $docs ) ? ' documento' : ' documentos' );
	}

	f8_reset();
	\Digitalisimo\Elements\Legacy\Platform\Registry::flush_cache();
	\Digitalisimo\Elements\Legacy\Aliases::set_map( array() );
	Report::reset_catalog();

	/* ================================================================ El registro sintético */
	$expect = array( 'widgets' => array(), 'globals' => array( 'count' => 0, 'docs' => array(), 'templates' => array() ), 'recovered' => array() );
	$ids    = array();
	$doc    = static function ( $title ) use ( &$ids ) {
		$ids[ $title ] = f8_post( array( 'post_title' => $title, 'post_status' => 'publish', 'post_type' => 'page' ) );
		return $ids[ $title ];
	};
	$conv   = static function ( $id, $widget, $target, $count ) use ( &$expect ) {
		Report::add( $id, 'pro.converted', array( 'widget' => $widget, 'target' => $target, 'count' => $count ) );
		if ( 'global' === $widget ) {
			$expect['globals']['count']       += $count;
			$expect['globals']['docs'][ $id ]  = true;
			return;
		}
		$key                                 = $widget . ' → ' . $target;
		$expect['widgets'][ $key ]['count']  = ( $expect['widgets'][ $key ]['count'] ?? 0 ) + $count;
		$expect['widgets'][ $key ]['docs'][ $id ] = true;
	};
	$global = static function ( $id, $template, $count ) use ( &$expect ) {
		Report::add( $id, 'pro.global', array( 'widget' => 'global', 'target' => 'digitalisimo-global', 'name' => 'templateID', 'count' => $count, 'note' => sprintf( 'Instancia de la plantilla %d pasada al widget global propio: sigue vinculada a su plantilla.', $template ) ) );
		$expect['globals']['templates'][ $template ] = true;
	};
	$recover = static function ( $id, $widget, $target, $key, $count ) use ( &$expect ) {
		Report::add( $id, 'pro.recovered', array( 'widget' => $widget, 'target' => $target, 'name' => $key, 'count' => $count ) );
		$expect['recovered'][ $key ]['count']       = ( $expect['recovered'][ $key ]['count'] ?? 0 ) + $count;
		$expect['recovered'][ $key ]['docs'][ $id ] = true;
	};
	// Lo que la copia describe como «pasa tal cual»: con un recuperado de la misma clave, es el mismo ajuste.
	$inert = static function ( $id, $widget, $target, $key, $count ) {
		Report::add( $id, 'pro.inert', array( 'widget' => $widget, 'target' => $target, 'name' => $key, 'count' => $count ) );
	};

	Report::clear();
	Report::begin( 'run' );
	$id = $doc( 'Ocultar plugins con código' );
	$recover( $id, 'blockquote', 'digitalisimo-blockquote', 'display_condition_list', 1 );
	$recover( $id, 'code-highlight', 'digitalisimo-code-highlight', 'display_condition_list', 3 );
	$conv( $id, 'blockquote', 'digitalisimo-blockquote', 1 );
	$conv( $id, 'code-highlight', 'digitalisimo-code-highlight', 3 );
	$inert( $id, 'blockquote', 'digitalisimo-blockquote', 'display_condition_list', 1 );
	$inert( $id, 'code-highlight', 'digitalisimo-code-highlight', 'display_condition_list', 3 );
	$id = $doc( 'Qué es una página web' );
	$recover( $id, 'template', 'digitalisimo-template', 'display_condition_list', 1 );
	$conv( $id, 'template', 'digitalisimo-template', 1 );
	$inert( $id, 'template', 'digitalisimo-template', 'display_condition_list', 1 );
	$id = $doc( 'Optimización web' );
	$conv( $id, 'global', 'digitalisimo-global', 1 );
	$conv( $id, 'breadcrumbs', 'digitalisimo-yoast-breadcrumbs', 1 );
	$global( $id, 543, 1 );
	$id = $doc( 'Ayuda digital' );
	$conv( $id, 'global', 'digitalisimo-global', 1 );
	$global( $id, 5715, 1 );
	$id = $doc( 'Tabla de contenidos' );
	$conv( $id, 'table-of-contents', 'digitalisimo-table-of-contents', 1 );
	// Un ajuste inerte sin recuperar: va a la línea de lo que se deja tal cual.
	$inert( $id, 'table-of-contents', 'digitalisimo-table-of-contents', '_element_custom_width', 1 );
	$id = $doc( 'Reseñas' );
	$conv( $id, 'reviews', 'digitalisimo-reviews', 1 );
	$id = $doc( 'Formulario' );
	$recover( $id, 'form', 'digitalisimo-form', 'form_fields.dynamic', 4 );
	$conv( $id, 'form', 'digitalisimo-form', 1 );
	$inert( $id, 'form', 'digitalisimo-form', 'form_fields.dynamic', 4 );
	$id = $doc( 'Blog de ejemplo' );
	$recover( $id, 'posts', 'digitalisimo-posts', 'content_width', 1 );
	$conv( $id, 'posts', 'digitalisimo-posts', 1 );
	$inert( $id, 'posts', 'digitalisimo-posts', 'content_width', 1 );
	$id = $doc( 'Curso de comercio electrónico' );
	$conv( $id, 'global', 'digitalisimo-global', 4 );
	$conv( $id, 'animated-headline', 'digitalisimo-animated-headline', 4 );
	$conv( $id, 'blockquote', 'digitalisimo-blockquote', 1 );
	$global( $id, 6178, 3 );
	$global( $id, 5736, 1 );
	$id = $doc( 'Curso en la ciudad' );
	$conv( $id, 'global', 'digitalisimo-global', 7 );
	$conv( $id, 'animated-headline', 'digitalisimo-animated-headline', 5 );
	$conv( $id, 'countdown', 'digitalisimo-countdown', 1 );
	$conv( $id, 'blockquote', 'digitalisimo-blockquote', 1 );
	$conv( $id, 'price-table', 'digitalisimo-price-table', 1 );
	$global( $id, 6178, 6 );
	$global( $id, 5736, 1 );
	$id = $doc( 'Plantilla de aterrizaje' );
	foreach ( array( 'breadcrumbs' => 'digitalisimo-yoast-breadcrumbs', 'table-of-contents' => 'digitalisimo-table-of-contents', 'blockquote' => 'digitalisimo-blockquote', 'reviews' => 'digitalisimo-reviews' ) as $widget => $target ) {
		$conv( $id, $widget, $target, 1 );
	}
	$id = $doc( 'Emprende' );
	$conv( $id, 'gallery', 'digitalisimo-gallery', 1 );
	$conv( $id, 'countdown', 'digitalisimo-countdown', 1 );
	$conv( $id, 'blockquote', 'digitalisimo-blockquote', 1 );
	$conv( $id, 'animated-headline', 'digitalisimo-animated-headline', 2 );
	$id = $doc( 'Agencia' );
	foreach ( array( 'blockquote' => 'digitalisimo-blockquote', 'reviews' => 'digitalisimo-reviews', 'table-of-contents' => 'digitalisimo-table-of-contents' ) as $widget => $target ) {
		$conv( $id, $widget, $target, 1 );
	}
	$id = $doc( 'Páginas de archivo' );
	$conv( $id, 'search-form', 'digitalisimo-search-form', 1 );
	$conv( $id, 'archive-posts', 'digitalisimo-archive-posts', 1 );
	$id = $doc( 'Pie de página' );
	$recover( $id, 'blockquote', 'digitalisimo-blockquote', 'content_width', 1 );
	$conv( $id, 'global', 'digitalisimo-global', 24 );
	$conv( $id, 'template', 'digitalisimo-template', 1 );
	$conv( $id, 'blockquote', 'digitalisimo-blockquote', 1 );
	foreach ( array( 5736 => 1, 10124 => 1, 543 => 2, 9922 => 19, 5669 => 1 ) as $template => $count ) {
		$global( $id, $template, $count );
	}
	$inert( $id, 'blockquote', 'digitalisimo-blockquote', 'content_width', 1 );
	$id = $doc( 'Cabecera' );
	$conv( $id, 'nav-menu', 'digitalisimo-nav-menu', 1 );
	Report::add( $id, 'pro.target_dependency', array( 'widget' => 'wc-add-to-cart', 'target' => 'digitalisimo-woo-add-to-cart', 'name' => 'WooCommerce' ) );
	$id = $doc( 'Blog' );
	$conv( $id, 'search-form', 'digitalisimo-search-form', 1 );
	$conv( $id, 'posts', 'digitalisimo-posts', 1 );
	$id = $doc( 'Pie' );
	$conv( $id, 'form', 'digitalisimo-form', 1 );
	$id = $doc( 'Entrada individual' );
	foreach ( array( 'author-box' => 'digitalisimo-author-box', 'theme-post-content' => 'digitalisimo-post-content', 'posts' => 'digitalisimo-posts' ) as $widget => $target ) {
		$recover( $id, $widget, $target, 'content_width', 1 );
	}
	foreach ( array( 'author-box' => 'digitalisimo-author-box', 'theme-post-content' => 'digitalisimo-post-content', 'posts' => 'digitalisimo-posts' ) as $widget => $target ) {
		$conv( $id, $widget, $target, 1 );
	}
	foreach ( array( 'author-box' => 'digitalisimo-author-box', 'theme-post-content' => 'digitalisimo-post-content', 'posts' => 'digitalisimo-posts' ) as $widget => $target ) {
		$inert( $id, $widget, $target, 'content_width', 1 );
	}
	foreach ( array( 'Inicio', 'Proyecto', 'Servicio' ) as $title ) {
		$conv( $doc( $title ), 'posts', 'digitalisimo-posts', 1 );
	}
	$id = $doc( 'Contacto' );
	$recover( $id, 'form', 'digitalisimo-form', 'form_fields.dynamic', 2 );
	$conv( $id, 'form', 'digitalisimo-form', 1 );
	$inert( $id, 'form', 'digitalisimo-form', 'form_fields.dynamic', 2 );
	foreach ( array( 'Páginas web' => 10120, 'Diseño de páginas' => 10120, 'Servicios web' => 10120 ) as $title => $template ) {
		$id = $doc( $title );
		$conv( $id, 'global', 'digitalisimo-global', 1 );
		$global( $id, $template, 1 );
	}
	// La portada: extensiones de Element Pack encendidas que ya no se aplican (lo pendiente va primero) y más anotaciones
	// de las que caben (las dos últimas, lo inerte, no caben: el informe las cuenta aparte).
	$id = $doc( 'Portada' );
	Report::add( $id, 'core.residual', array( 'element' => 'container #4498947', 'name' => 'Capas de imágenes y colores con paralaje, Sección fija al desplazar' ) );
	Report::add( $id, 'ext.disabled', array( 'count' => 1 ) );
	$recover( $id, 'blockquote', 'digitalisimo-blockquote', 'content_width', 1 );
	$recover( $id, 'posts', 'digitalisimo-posts', 'content_width', 1 );
	$conv( $id, 'global', 'digitalisimo-global', 47 );
	$conv( $id, 'blockquote', 'digitalisimo-blockquote', 1 );
	$conv( $id, 'posts', 'digitalisimo-posts', 1 );
	foreach ( array( 10135 => 1, 10138 => 1, 5736 => 1, 10124 => 1, 543 => 2 ) as $template => $count ) {
		$global( $id, $template, $count );
	}
	$inert( $id, 'blockquote', 'digitalisimo-blockquote', 'content_width', 1 );
	$inert( $id, 'posts', 'digitalisimo-posts', 'content_width', 1 );
	$id = $doc( 'Curso en vivo' );
	$conv( $id, 'animated-headline', 'digitalisimo-animated-headline', 4 );
	$conv( $id, 'countdown', 'digitalisimo-countdown', 1 );
	$conv( $id, 'blockquote', 'digitalisimo-blockquote', 1 );
	$id = $doc( 'WhatsApp' );
	$recover( $id, 'contact-buttons-var-1', 'digitalisimo-contact-buttons', 'content_width', 1 );
	$conv( $id, 'contact-buttons-var-1', 'digitalisimo-contact-buttons', 1 );
	$inert( $id, 'contact-buttons-var-1', 'digitalisimo-contact-buttons', 'content_width', 1 );
	$id = $doc( 'Oferta' );
	$conv( $id, 'animated-headline', 'digitalisimo-animated-headline', 1 );
	$conv( $id, 'countdown', 'digitalisimo-countdown', 1 );
	$id = $doc( 'Carrito' );
	Report::add( $id, 'pro.target_dependency', array( 'widget' => 'woocommerce-cart', 'target' => 'digitalisimo-woo-cart', 'name' => 'WooCommerce' ) );
	Report::site( 'core.residual_inert' );
	Report::site( 'plat.residue', array( 'count' => 2, 'note' => 'archivos en uploads/element-pack, copias en uploads/element-pack-backups' ) );
	Report::site( 'plat.mega.template', array( 'name' => 'Servicios (Mega Menu)' ) );
	Report::flush();
	$stored = Report::stored();
	f8_check( 2 === (int) $stored['docs'][ $ids['Portada'] ]['more'], 'La portada tiene más anotaciones de las que caben: ' . (int) $stored['docs'][ $ids['Portada'] ]['more'] );

	// La tarea de contenido de esta pasada: nada nuevo (todo venía de una pasada anterior), un documento de PRO sin
	// convertir (el carrito) que ya explica el informe.
	$content = array(
		'stage'    => 'finish',
		'done'     => true,
		'finished' => 1763577960,
		'counts'   => array(),
		'warnings' => array(),
		'report'   => array( 'f8' => array( 'v' => 1 ) + Report::summary( $stored ), 'plugins' => array(), 'pro_left' => 1 ),
	);
	update_option( Runner::REPORT, $content );
	$full = array(
		'stage'     => 'finish',
		'done'      => true,
		'network'   => false,
		'failed'    => '',
		'error'     => '',
		'backup'    => array( 'ok' => true, 'file' => 'digitalisimo-backup-ejemplo-20261119-200445-62ba61f8.zip', 'size' => 304911974, 'sha256' => str_repeat( 'a7', 32 ) ),
		'installed' => array(),
		'activated' => array(),
		'imports'   => array( 1 => array( 'seo_rankmath' => array( 'done' => true, 'count' => 194 ) ) ),
		'plan'      => array( 'rows' => array( 'rank-math' => array( 'label' => 'Rank Math SEO y Rank Math SEO PRO', 'by' => 'digitalisimo-seo', 'import' => 'seo_rankmath', 'files' => array( 'seo-by-rank-math/rank-math.php' => true ), 'active' => true ) ) ),
		'retired'   => array( 'rank-math' ),
		'deleted'   => array( 'rank-math' => 'Rank Math SEO y Rank Math SEO PRO' ),
		'kept'      => array(),
		'theme'     => array( 1 => array( 'name' => 'Hello Elementor Child', 'switched' => false, 'reason' => 'El sitio usa un tema hijo de Hello («Hello Elementor Child»): su CSS y su PHP no se trasladan solos (el PHP va al editor de fragmentos de DIGITALÍSIMO Tools). Se queda; cámbialo en Apariencia cuando los hayas pasado.' ) ),
		'verify'    => array(
			array( 'site' => 1, 'url' => 'https://ejemplo.test/', 'ok' => true, 'reason' => '' ),
			array( 'site' => 1, 'url' => 'https://ejemplo.test/blog/', 'ok' => true, 'reason' => '' ),
			array( 'site' => 1, 'url' => 'https://ejemplo.test/chat/', 'ok' => null, 'reason' => 'Antes de migrar no tenía texto visible que comparar.' ),
		),
		'notes'     => array(),
		'content'   => array( 'started' => true ),
	);
	$html = Full::render( false, $full );
	$agg  = summary_aggregate( $html );

	/* ================================================================ Líneas agregadas y sus totales */
	foreach ( $expect['widgets'] as $key => $row ) {
		$line = '<li>' . esc_html( $key . ': ' . summary_in( $row['count'], $row['docs'] ) ) . '</li>';
		f8_check( 1 === substr_count( $agg, $line ), 'Una sola línea por tipo de widget, con su total: ' . $key . ' (' . summary_in( $row['count'], $row['docs'] ) . ')' );
	}
	$total = 0;
	$docs  = array();
	foreach ( $expect['widgets'] as $row ) {
		$total += $row['count'];
		$docs  += $row['docs'];
	}
	f8_check( str_contains( $agg, 'con el mismo contenido, enlaces e imágenes: ' . summary_in( $total, $docs ) . '.' ), 'Y el total de los widgets de PRO: ' . summary_in( $total, $docs ) );
	f8_check( 1 === substr_count( $agg, 'Widgets globales: ' . $expect['globals']['count'] . ' instancias en ' . count( $expect['globals']['docs'] ) . ' documentos' ) && str_contains( $agg, count( $expect['globals']['templates'] ) . ' plantillas' ), 'El widget global en una línea, con sus instancias y sus plantillas: ' . $expect['globals']['count'] . ' en ' . count( $expect['globals']['docs'] ) . ' documentos, ' . count( $expect['globals']['templates'] ) . ' plantillas.' );
	f8_check( false === strpos( $agg, 'global → digitalisimo-global' ), 'El widget global no se repite como un tipo más.' );
	foreach ( $expect['recovered'] as $key => $row ) {
		f8_check( 1 === substr_count( $agg, '<li>' . esc_html( $key . ': ' . summary_in( $row['count'], $row['docs'] ) ) . ' (' ), 'Lo recuperado de la copia, una línea por clave: ' . $key . ' (' . summary_in( $row['count'], $row['docs'] ) . ')' );
	}
	$catalog = Report::catalog();
	foreach ( array( 'pro.recovered', 'pro.inert', 'pro.global', 'pro.converted' ) as $code ) {
		f8_check( false === strpos( $agg, esc_html( $catalog[ $code ]['label'] ) ), 'Fuera del detalle por documento, la línea de «' . $code . '» no se repite por documento.' );
	}
	f8_check( 1 === substr_count( $agg, 'Se dejan tal cual, sin efecto en lo que se ve:' ), 'Lo inerte, en una sola línea.' );
	preg_match( '#Se dejan tal cual, sin efecto en lo que se ve:[^<]*#', $agg, $m );
	$inert_line = $m[0] ?? '';
	f8_check( 'Se dejan tal cual, sin efecto en lo que se ve: ajustes guardados que no son del widget de PRO en 1 documento (_element_custom_width); ajustes de página de Element Pack que guardaban documentos migrados con una versión anterior.' === $inert_line, 'La línea de lo inerte, entera: ' . $inert_line );
	f8_check( str_contains( $inert_line, '_element_custom_width' ) && str_contains( $inert_line, 'ajustes de página de Element Pack' ) && false === strpos( $inert_line, 'display_condition_list' ) && false === strpos( $inert_line, 'content_width' ), 'Lo que pasa tal cual y ya está como recuperado no vuelve a salir como inerte: ' . $inert_line );
	f8_check( str_contains( $agg, 'Datos que el plugin anterior dejó en el sitio y la migración no borra (2 en el sitio) — archivos en uploads/element-pack, copias en uploads/element-pack-backups.' ), 'Lo del sitio, con su cantidad y su nota.' );
	f8_check( str_contains( $agg, 'Plantilla creada con el contenido de un desplegable del mega menú' ) && str_contains( $agg, 'Servicios (Mega Menu)' ), 'Lo creado, con su nombre.' );
	f8_check( str_contains( $agg, 'Además: 2 anotaciones más' ) && 1 === substr_count( $html, 'Además:' ), 'Lo que no cupo se dice una vez.' );

	/* ================================================================ Sin duplicados ni contradicciones */
	f8_check( false === stripos( $html, 'había nada que' ), 'Con conversiones, nada dice «no había nada que migrar».' );
	f8_check( 1 === substr_count( $html, 'Migración completada' ), 'Un solo «Migración completada»: ' . substr_count( $html, 'Migración completada' ) );
	f8_check( str_contains( $html, 'Ya estaba convertido en una pasada anterior' ) && str_contains( $html, $total . ' widgets de PRO y ' . $expect['globals']['count'] . ' instancias de widgets globales en ' ), 'El contenido dice lo que ya es de DIGITALÍSIMO Elements y que esta pasada no tenía nada nuevo.' );
	f8_check( false === strpos( $html, 'Quedan 1 documentos con widgets de PRO' ), 'El recuento de documentos de PRO sin convertir no repite lo que ya explica «Necesita tu atención».' );
	f8_check( 1 === substr_count( $html, 'Necesita tu atención' ) && 0 === substr_count( $html, 'Pendiente de revisar' ), '«Necesita tu atención» sale una vez y no se repite como «Pendiente de revisar».' );
	f8_check( false === strpos( $html, 'pasan al tema propio' ), 'Con el tema hijo que se queda, nada dice que algo pase al tema propio.' );
	$text = Full::text( $agg );
	$seen = array();
	$dupe = array();
	foreach ( summary_lines( $text ) as $line ) {
		if ( isset( $seen[ $line ] ) && strlen( $line ) > 20 ) {
			$dupe[] = $line;
		}
		$seen[ $line ] = true;
	}
	f8_check( ! $dupe, 'Ninguna línea se repite fuera del detalle por documento: ' . implode( ' | ', $dupe ) );

	/* ================================================================ Lo pendiente, arriba, con su acción y su enlace */
	$attention = strpos( $html, 'Necesita tu atención' );
	$detail    = strpos( $html, '<details class="digi-migrate__detail">' );
	f8_check( false !== $attention && false !== $detail && $attention < $detail && strpos( $html, 'Portada y páginas' ) < $attention, '«Necesita tu atención» va justo después del resultado y antes del detalle.' );
	preg_match( '#<ul class="digi-migrate__attention"[^>]*>(.*?)</ul>#s', $html, $m );
	$items = isset( $m[1] ) ? explode( '</li><li>', substr( $m[1], 4, -5 ) ) : array();
	f8_check( 2 === count( $items ), 'Dos asuntos: las extensiones de Element Pack y WooCommerce (' . count( $items ) . ').' );
	f8_check( str_starts_with( $items[0] ?? '', 'Widgets de PRO sin convertir porque su widget propio necesita WooCommerce' ) && str_starts_with( $items[1] ?? '', 'Ajustes de extensiones de Element Pack que ya no se aplican' ), 'Cada asunto empieza por lo que hay que hacer.' );
	$woo = implode( '', array_filter( $items, static function ( $item ) {
		return str_contains( $item, 'WooCommerce' );
	} ) );
	f8_check( str_contains( $woo, 'post.php?post=' . $ids['Carrito'] . '&amp;action=elementor' ) && str_contains( $woo, 'post.php?post=' . $ids['Cabecera'] . '&amp;action=elementor' ) && str_contains( $woo, 'si activas WooCommerce, vuelve a pulsar Migrar' ) && str_contains( $woo, 'woocommerce-cart' ) && str_contains( $woo, 'wc-add-to-cart' ), 'WooCommerce, en una entrada con la acción y el enlace al editor de cada documento.' );
	$ep = implode( '', array_filter( $items, static function ( $item ) {
		return str_contains( $item, 'Element Pack' );
	} ) );
	f8_check( str_contains( $ep, 'post.php?post=' . $ids['Portada'] . '&amp;action=elementor' ) && str_contains( $ep, 'Sección fija al desplazar' ) && str_contains( $ep, 'container #4498947' ) && str_contains( $ep, 'rehazlas con las opciones de DIGITALÍSIMO Elements' ), 'Las extensiones encendidas, con la acción, el elemento y el enlace al editor.' );

	/* ================================================================ Detalle plegado y tope de lo visible */
	f8_check( 1 === preg_match( '#<details class="digi-migrate__detail"><summary>Detalle</summary>.*<details class="digi-migrate__docs"><summary>Por documento</summary>#s', $html ), 'El detalle está plegado y, dentro, el detalle por documento en otro desplegable.' );
	f8_check( str_contains( $html, esc_html( $catalog['pro.recovered']['label'] ) ), 'El detalle por documento sigue disponible dentro de su desplegable.' );
	$visible = summary_lines( summary_visible( $html ) );
	if ( getenv( 'SUMMARY_DUMP' ) ) {
		echo "--- visible\n", implode( "\n", $visible ), "\n--- cli\n", Full::text( Full::render( false, $full, 'cli' ) ), "\n";
	}
	f8_check( count( $visible ) < 40, 'Lo visible sin desplegar cabe en menos de 40 líneas: ' . count( $visible ) );
	f8_check( count( $visible ) < 20, 'Y en este caso, en menos de 20: ' . count( $visible ) . "\n" . implode( "\n", $visible ) );
	f8_check( str_contains( implode( "\n", $visible ), 'Responden igual: 2 de 3. Sin comparar: https://ejemplo.test/chat/' ), 'La portada dice cuántas responden igual y cuál no se pudo comparar: ' . implode( ' | ', preg_grep( '/Portada y/', $visible ) ) );
	$was = summary_lines( Full::text( $html ) );
	f8_check( count( $was ) > count( $visible ) + 30, 'Desplegado, el detalle sigue completo (' . count( $was ) . ' líneas).' );

	/* ================================================================ WP-CLI: el mismo resumen */
	$cli = Full::text( Full::render( false, $full, 'cli' ) );
	f8_check( false === strpos( $cli, '<' ) && str_contains( $cli, 'Necesita tu atención' ) && str_contains( $cli, 'blockquote → digitalisimo-blockquote: ' ) && str_contains( $cli, 'El detalle por documento está en la pantalla' ), 'WP-CLI da el mismo resumen, sin enlaces.' );
	f8_check( false === strpos( $cli, $catalog['pro.recovered']['label'] ) && false === strpos( $cli, 'Por documento' ), 'Sin el detalle por documento.' );
	f8_check( count( summary_lines( $cli ) ) < 80 && strpos( $cli, 'Necesita tu atención' ) < strpos( $cli, 'Convertido' ), 'Corto, y lo pendiente antes del detalle: ' . count( summary_lines( $cli ) ) . ' líneas.' );
	f8_check( str_contains( $cli, 'Respaldo · digitalisimo-backup-ejemplo' ), 'Con el respaldo arriba.' );

	/* ================================================================ El informe de contenido solo (un sitio de una red) */
	$solo = Runner::render( $content );
	f8_check( 1 === substr_count( $solo, 'Migración completada' ) && str_contains( $solo, 'Necesita tu atención' ) && str_contains( $solo, '<details class="digi-migrate__detail"><summary>Detalle</summary>' ) && false === stripos( $solo, 'había nada que' ), 'Solo, el informe del contenido tiene la misma forma.' );
	f8_check( count( summary_lines( summary_visible( $solo ) ) ) < 15, 'Y también se lee de un vistazo: ' . count( summary_lines( summary_visible( $solo ) ) ) );
	f8_check( false === strpos( $solo, 'Este sitio ya no depende' ), 'Con asuntos pendientes no dice que el sitio ya no depende de nada.' );
	$solo_cli = Full::text( Runner::render( $content, 'cli' ) );
	f8_check( false === strpos( $solo_cli, 'Por documento' ) && false === strpos( $solo_cli, $catalog['pro.recovered']['label'] ) && str_contains( $solo_cli, 'blockquote → digitalisimo-blockquote: ' ), 'En WP-CLI (un sitio de una red), el mismo resumen sin el detalle por documento.' );
	// Una primera pasada: dice lo que hizo.
	$first = $content;
	$first['counts'] = array( 'pro' => 21, 'pro_elements' => 118, 'revisions' => 2 );
	$parts = Runner::report_parts( $first );
	f8_check( str_contains( $parts['summary'], 'En esta pasada: 21 documentos con widgets de PRO convertidos (118 widgets); 2 revisiones' ) && false === strpos( $parts['summary'], 'Ya estaba convertido' ), 'Una pasada que convierte dice lo que hizo: ' . $parts['summary'] );
	f8_check( str_contains( $parts['detail'], 'Documentos con widgets de PRO convertidos a widgets propios: 21 (118 widgets).' ), 'Y el detalle lo cuenta entero.' );
	// Los plugins desactivados: el informe solo dice si pueden borrarse; dentro de la tarea completa, no (ella los borra).
	$off = $content;
	$off['report']['plugins'] = array( 'elementor/elementor.php' => array( 'label' => 'Elementor', 'site' => false, 'network' => false ) );
	f8_check( str_contains( Runner::render( $off ), 'Desactivados y listos para borrarse desde Plugins cuando hayas comprobado el sitio: Elementor.' ) && '' === Runner::report_parts( $off, 'site', 4 )['plugins'], 'Lo que puede borrarse lo dice el informe solo, nunca dentro del de la tarea completa (que dice lo que borró).' );
	// Sin nada: lo dice en una línea, y nada pide atención.
	Report::clear();
	$empty = array( 'stage' => 'finish', 'done' => true, 'finished' => 1, 'counts' => array(), 'warnings' => array(), 'report' => array( 'f8' => array( 'v' => 1 ), 'plugins' => array() ) );
	$none  = Runner::render( $empty );
	f8_check( str_contains( $none, 'No había nada que convertir en el contenido.' ) && str_contains( $none, 'Nada necesita tu atención.' ) && false === strpos( $none, '<details' ), 'Sin nada: una línea, «Nada necesita tu atención» y sin desplegables vacíos.' );

	/* ================================================================ La red: el mismo resumen */
	$job = array(
		'started'  => 1,
		'done'     => true,
		'finished' => 2,
		'sites'    => array( 1, 2 ),
		'skipped'  => array(),
		'deactivated' => array( 'Element Pack Pro' ),
		'warnings' => array(),
		'status'   => array(
			1 => array( 'status' => 'done', 'counts' => array( 'converted' => 2 ), 'pending' => true, 'f8' => array( 'review' => 1 ), 'name' => 'Sitio uno', 'url' => 'https://s1.test/', 'tool' => 'https://s1.test/wp-admin/tools.php?page=digitalisimo-ep-migration' ),
			2 => array( 'status' => 'done', 'counts' => array( 'converted' => 1 ), 'pending' => false, 'f8' => array(), 'name' => 'Sitio dos', 'url' => 'https://s2.test/', 'tool' => 'https://s2.test/wp-admin/tools.php?page=digitalisimo-ep-migration' ),
		),
	);
	$net = Runner::render_network( $job );
	f8_check( str_contains( $net, 'Contenido migrado en 2 de 2 sitios; 1 con asuntos por revisar.' ) && str_contains( $net, 'Necesita tu atención' ) && str_contains( $net, 'href="https://s1.test/wp-admin/tools.php?page=digitalisimo-ep-migration"' ), 'En la red: el resultado en una frase y los sitios con asuntos enlazados a su informe.' );
	f8_check( strpos( $net, 'Necesita tu atención' ) < strpos( $net, '<details class="digi-migrate__detail">' ) && strpos( $net, '<details class="digi-migrate__detail">' ) < strpos( $net, 'digi-migrate__sites' ), 'Y la tabla de cada sitio, plegada en el detalle.' );
	f8_check( 1 === substr_count( $net, 'Migración de la red completada' ) && count( summary_lines( summary_visible( $net ) ) ) < 15, 'Una vez, y corto.' );
	$net_cli = Full::text( Runner::render_network( $job, 'cli' ) );
	f8_check( false === strpos( $net_cli, 'href' ) && str_contains( $net_cli, 'Sitio uno (https://s1.test/)' ), 'En WP-CLI, la red sin enlaces.' );

	/* ================================================================ El análisis previo, plegado al terminar */
	$closed = Screen::analysis_open( true );
	$open   = Screen::analysis_open( false );
	f8_check( str_starts_with( $closed, '<details class="digi-migrate__before" data-done-label=' ) && false === strpos( $closed, ' open' ) && str_contains( $closed, '<summary>Ver el análisis del sitio' ), 'Terminada la migración, el análisis queda plegado bajo «Ver el análisis…».' );
	f8_check( str_contains( $open, ' open ' ) && str_contains( $open, '<summary>Análisis del sitio: lo que hará Migrar</summary>' ), 'Antes de migrar, abierto.' );
	f8_check( ! Screen::finished( false ), 'Sin informe, no hay nada terminado.' );
	update_option( Full::LAST, $full );
	f8_check( Screen::finished( false ), 'Con el informe de una migración terminada, sí.' );
	update_option( Full::JOB, array( 'stage' => 'content', 'done' => false ) );
	f8_check( ! Screen::finished( false ), 'Con otra en marcha, el análisis se queda abierto.' );
	delete_option( Full::JOB );
	// La pantalla entera: el informe arriba; el plan, lo que hay en el sitio y el análisis del contenido, plegados.
	update_option( 'template', 'hello-elementor' );
	update_option( 'stylesheet', 'hello-elementor-child' );
	ob_start();
	Migration::page();
	$screen = ob_get_clean();
	$report_at = strpos( $screen, 'Migración completada.' );
	$fold_at   = strpos( $screen, '<details class="digi-migrate__before" data-done-label=' );
	f8_check( false !== $report_at && false !== $fold_at && $report_at < $fold_at, 'En la pantalla, el informe va antes del análisis plegado.' );
	foreach ( array( '<h2>Lo que hará Migrar</h2>', '<h2>Qué hay en este sitio</h2>', 'digi-migrate__analysis' ) as $needle ) {
		f8_check( false !== strpos( $screen, $needle ) && strpos( $screen, $needle ) > $fold_at, 'Plegado bajo el informe: ' . $needle );
	}
	f8_check( 1 === substr_count( $screen, 'Lo que hará Migrar</h' ) && false === strpos( summary_visible( $screen ), 'Qué hay en este sitio' ), '«Lo que hará Migrar» no se repite y nada del análisis se ve sin desplegar.' );
	f8_check( false === strpos( $screen, 'pasan al tema propio' ) && str_contains( $screen, 'Se queda: es un tema hijo de Hello' ), 'La fila del tema hijo no sugiere que algo pase al tema propio.' );
	// El análisis del contenido terminado: lo pendiente arriba, lo demás agregado, y su encabezado no repite el del plan.
	$analysis = Migration::analysis_html( array( 'done' => true, 'processed' => 1, 'total' => 1, 'report' => $stored, 'pro' => null ) );
	f8_check( str_starts_with( $analysis, '<h3>Lo que hará Migrar en el contenido</h3><h4>Pendiente de revisar</h4>' ) && str_contains( $analysis, 'si activas WooCommerce' ) && str_contains( $analysis, '<details class="digi-migrate__docs">' ) && false === strpos( summary_aggregate( $analysis ), esc_html( $catalog['pro.converted']['label'] ) ), 'El análisis previo usa el mismo resumen agregado, con su propio encabezado.' );
	update_option( 'stylesheet', 'hello-elementor' );
	$plan = Screen::plan_html( false );
	f8_check( str_contains( $plan, 'pasan al tema propio' ) && str_contains( $plan, 'Tema DIGITALÍSIMO' ), 'Con Hello sin hijo, el plan sí dice lo que pasa al tema propio.' );

	f8_done( 'DIGITALÍSIMO Migración: informe final corto', 60 );
}
