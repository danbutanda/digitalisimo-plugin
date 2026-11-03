<?php
/**
 * Códigos del informe de Prime Slider dinámico (frente F; §3.E.4, V.10): entradas, productos y eventos (familias E, F,
 * G y L), con su Loop Item generado.
 *
 * `section` ∈ converted|approx|created|dropped|review; sólo `review` deja asuntos pendientes. `review_if_published`: el
 * elemento pasa a «Pendiente de revisar» si el documento está publicado (un cambio visible en el sitio). Ningún texto
 * lleva valores de ajustes ni enlaces.
 */
defined( 'ABSPATH' ) || exit;

return array(
	/* ------------------------------------------------------------------ Destinos */
	'ps.dyn.loop'              => array( 'section' => 'approx', 'label' => __( 'Carrusel de Prime Slider convertido al carrusel de bucle (Loop Carousel) con un elemento de bucle generado', 'digitalisimo-migration' ) ),
	'ps.dyn.events'            => array( 'section' => 'approx', 'label' => __( 'Calendario de eventos convertido al carrusel de bucle sobre los eventos de The Events Calendar', 'digitalisimo-migration' ) ),
	'ps.dyn.products_carousel' => array( 'section' => 'approx', 'label' => __( 'Productos convertidos al widget Productos en carrusel, con las partes que mostraba el original', 'digitalisimo-migration' ) ),
	'ps.dyn.hotspot'           => array( 'section' => 'approx', 'label' => __( 'Diapositivas con puntos de producto convertidas al slider anidado con el widget Puntos de interés', 'digitalisimo-migration' ) ),
	'ps.dyn.social'            => array( 'section' => 'approx', 'label' => __( 'Redes sociales del slider como widget de iconos sociales junto al carrusel (ya no van superpuestas)', 'digitalisimo-migration' ) ),
	'ps.dyn.scroll'            => array( 'section' => 'approx', 'label' => __( 'Botón «Scroll Down» del slider como botón de desplazamiento junto al carrusel (ya no va superpuesto)', 'digitalisimo-migration' ) ),

	/* ------------------------------------------------------------------ Respaldos */
	'ps.dyn.fallback_posts'    => array( 'section' => 'approx', 'review_if_published' => true, 'label' => __( 'Sin elemento de bucle posible (contenedores o widgets del creador de temas no disponibles): las entradas se muestran en la rejilla del widget Entradas, sin carrusel', 'digitalisimo-migration' ) ),
	'ps.dyn.fallback_products' => array( 'section' => 'approx', 'review_if_published' => true, 'label' => __( 'Sin elemento de bucle de productos posible: los productos se muestran en el widget Productos en carrusel', 'digitalisimo-migration' ) ),
	'ps.dyn.hotspot_fragment'  => array( 'section' => 'approx', 'review_if_published' => true, 'label' => __( 'Sin el slider anidado (experimento de elementos anidados apagado): las tres imágenes con puntos se muestran una tras otra', 'digitalisimo-migration' ) ),

	/* ------------------------------------------------------------------ Creado */
	'ps.dyn.loop_created'      => array( 'section' => 'created', 'label' => __( 'Elemento de bucle (Loop Item) creado en la biblioteca de plantillas para los carruseles migrados', 'digitalisimo-migration' ) ),

	/* ------------------------------------------------------------------ Diferencias de consulta */
	'ps.dyn.related'           => array( 'section' => 'approx', 'label' => __( 'Entradas relacionadas: el original las buscaba por autor; ahora se relacionan por categorías y etiquetas', 'digitalisimo-migration' ) ),
	'ps.dyn.orderby'           => array( 'section' => 'approx', 'label' => __( 'Orden de productos sin equivalente (por ID, autor o comentarios): ahora se ordenan por fecha', 'digitalisimo-migration' ) ),
	'ps.dyn.query_source'      => array( 'section' => 'approx', 'label' => __( 'El widget de productos consultaba otro tipo de contenido: el destino sólo muestra productos', 'digitalisimo-migration' ) ),
	'ps.dyn.query_authors'     => array( 'section' => 'approx', 'label' => __( 'El filtro por autores no existe en la consulta de productos: se omitió', 'digitalisimo-migration' ) ),
	'ps.dyn.query_date'        => array( 'section' => 'approx', 'label' => __( 'El filtro por fecha no existe en la consulta de productos: se omitió', 'digitalisimo-migration' ) ),
	'ps.dyn.nav_none'          => array( 'section' => 'approx', 'label' => __( 'El carrusel no tenía flechas ni puntos: se añadió una navegación para poder cambiar de diapositiva sin arrastrar', 'digitalisimo-migration' ) ),
	'ps.dyn.h1'                => array( 'section' => 'approx', 'label' => __( 'El título de cada diapositiva conserva su etiqueta h1: la página tendrá varios h1, como antes', 'digitalisimo-migration' ) ),

	/* ------------------------------------------------------------------ Sin equivalente */
	'ps.dyn.events_unavailable'   => array( 'section' => 'dropped', 'label' => __( 'Calendario de eventos sin The Events Calendar activo: el widget queda sin convertir, con su original guardado', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.event_dates'     => array( 'section' => 'dropped', 'label' => __( 'Fechas del evento, cuenta atrás, lugar y formas decorativas sin equivalente; el orden por fecha del evento pasa a fecha de publicación', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.effect'          => array( 'section' => 'dropped', 'label' => __( 'Efecto de transición sin equivalente: el carrusel de bucle se desplaza', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.effect_material' => array( 'section' => 'dropped', 'label' => __( 'Efecto Material sin equivalente (biblioteca con licencia que no se puede redistribuir)', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.skin'            => array( 'section' => 'dropped', 'label' => __( 'Diseño propio de la variante (skin) del original sin equivalente', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.thumbs'          => array( 'section' => 'dropped', 'label' => __( 'Miniaturas de navegación sin equivalente', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.avatar'          => array( 'section' => 'dropped', 'label' => __( 'Avatar del autor sin equivalente', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.featured'        => array( 'section' => 'dropped', 'label' => __( 'Franja de entradas destacadas sin equivalente', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.layout'          => array( 'section' => 'dropped', 'label' => __( 'Composición de la diapositiva (imagen y texto en columnas o invertidos) sin equivalente: el texto va sobre la imagen', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.share'           => array( 'section' => 'dropped', 'label' => __( 'Botones de compartir sin equivalente', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.modal'           => array( 'section' => 'dropped', 'label' => __( 'Ventana modal con la entrada sin equivalente', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.centered'        => array( 'section' => 'dropped', 'label' => __( 'Diapositivas centradas sin equivalente', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.wrapper_link'    => array( 'section' => 'dropped', 'label' => __( 'Enlace de toda la diapositiva sin equivalente: enlazan el título y el botón', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.count'           => array( 'section' => 'dropped', 'label' => __( 'Contador de diapositivas sin equivalente', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.glass'           => array( 'section' => 'dropped', 'label' => __( 'Efecto de vidrio sin equivalente', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.circular'        => array( 'section' => 'dropped', 'label' => __( 'Disposición circular animada sin equivalente: los productos se muestran en fichas', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.expand'          => array( 'section' => 'dropped', 'label' => __( 'Paneles que se expanden sin equivalente: los productos se muestran en fichas', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.tooltip'         => array( 'section' => 'dropped', 'label' => __( 'Animación y flecha del globo de los puntos sin equivalente; el globo se abre al pulsar', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.dots'            => array( 'section' => 'dropped', 'label' => __( 'Los puntos de navegación no existen en el carrusel de productos: quedan las flechas', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.autoplay'        => array( 'section' => 'dropped', 'label' => __( 'Reproducción automática sin equivalente en el carrusel de productos', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.product_form'    => array( 'section' => 'dropped', 'label' => __( 'El botón de compra de las fichas no lleva el selector de cantidad del formulario del producto', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.product_filters' => array( 'section' => 'dropped', 'label' => __( 'Ocultar productos gratuitos o agotados sin equivalente en la consulta (WooCommerce oculta los agotados con su propio ajuste)', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.featured_only'   => array( 'section' => 'dropped', 'label' => __( '«Sólo entradas con imagen destacada» sin equivalente en la consulta', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.social'          => array( 'section' => 'dropped', 'label' => __( 'Redes sociales del slider sin widget de iconos sociales disponible: no se trasladaron', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.scroll'          => array( 'section' => 'dropped', 'label' => __( 'Botón «Scroll Down» sin widget de desplazamiento disponible: no se trasladó', 'digitalisimo-migration' ) ),
	'ps.dyn.loss.styles'          => array( 'section' => 'dropped', 'label' => __( 'Ajustes de estilo del original sin equivalente en el destino (recuento)', 'digitalisimo-migration' ) ),

	/* ------------------------------------------------------------------ Pendiente de revisar */
	'ps.dyn.blocked'           => array( 'section' => 'review', 'label' => __( 'Ni el destino ni su respaldo están disponibles en este sitio: el widget queda sin convertir, con su original guardado', 'digitalisimo-migration' ) ),
	'ps.dyn.no_woocommerce'    => array( 'section' => 'review', 'label' => __( 'WooCommerce no está activo: el widget de productos queda sin convertir, con su original guardado', 'digitalisimo-migration' ) ),
	'ps.dyn.loop_missing'      => array( 'section' => 'review', 'label' => __( 'No se pudo crear el elemento de bucle: se usó el respaldo', 'digitalisimo-migration' ) ),
	'ps.dyn.loop_failed'       => array( 'section' => 'review', 'label' => __( 'El elemento de bucle creado no se guardó íntegro: quedó en borrador y no se usa', 'digitalisimo-migration' ) ),
	'ps.dyn.events_terms'      => array( 'section' => 'review', 'label' => __( 'Ninguna de las categorías de eventos elegidas existe: el carrusel no muestra eventos, como el original', 'digitalisimo-migration' ) ),
	'ps.dyn.query_id'          => array( 'section' => 'review', 'label' => __( 'La consulta tenía un ID de consulta: el código que la filtraba debe engancharse ahora a la acción de consulta del constructor con el mismo ID', 'digitalisimo-migration' ) ),
	'ps.dyn.hotspot_no_image'  => array( 'section' => 'review', 'label' => __( 'Diapositivas de puntos sin imagen: el widget Puntos de interés no muestra nada sin imagen', 'digitalisimo-migration' ) ),
	'ps.dyn.review.custom_css' => array( 'section' => 'review', 'label' => __( 'El CSS personalizado del widget nombra clases del original, que ya no existen', 'digitalisimo-migration' ) ),

	/* ------------------------------------------------------------------ Reversión */
	'ps.dyn.revert_loop'       => array( 'section' => 'converted', 'label' => __( 'Elemento de bucle creado por la migración retirado al revertir', 'digitalisimo-migration' ) ),
	'ps.dyn.revert_skipped'    => array( 'section' => 'review', 'label' => __( 'Elemento de bucle editado después de la migración: no se retiró al revertir', 'digitalisimo-migration' ) ),
);
