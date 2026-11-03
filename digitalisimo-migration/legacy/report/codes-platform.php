<?php
/**
 * Códigos del informe de la plataforma de Element Pack (frente H): fuentes, iconos, Mega Menu, kit, código global,
 * barra lateral, muestras de variación, etiquetas dinámicas e inventario (contraseñas de aplicación, claves, residuos).
 *
 * `section` ∈ converted|approx|created|dropped|review; sólo `review` deja asuntos pendientes. `private`: el `name` del
 * detalle sólo lo ve un superadministrador en una red (o quien gestiona el sitio fuera de ella). Ningún texto lleva
 * valores de ajustes, claves ni contraseñas.
 */
defined( 'ABSPATH' ) || exit;

return array(
	/* ------------------------------------------------------------------ Fuentes */
	'plat.font.created'      => array( 'section' => 'created', 'label' => __( 'Fuente propia creada con la misma familia (los documentos no cambian)', 'digitalisimo-migration' ) ),
	'plat.font.otf'          => array( 'section' => 'approx', 'label' => __( 'Archivo OpenType usado como TrueType: la fuente propia no tiene hueco para OTF', 'digitalisimo-migration' ) ),
	'plat.font.file_missing' => array( 'section' => 'approx', 'label' => __( 'Archivo de una variante sin adjunto en Medios: se omitió ese formato', 'digitalisimo-migration' ) ),
	'plat.font.conflict'     => array( 'section' => 'review', 'label' => __( 'Ya existía una fuente propia con esa familia: no se creó otra; comprueba que sus archivos sean los mismos', 'digitalisimo-migration' ) ),
	'plat.font.unsafe'       => array( 'section' => 'review', 'label' => __( 'Nombre de familia con caracteres que no admite CSS: la fuente no se creó', 'digitalisimo-migration' ) ),
	'plat.font.renamed'      => array( 'section' => 'review', 'label' => __( 'WordPress cambió el nombre de la familia al guardarla: revisa la tipografía de los documentos', 'digitalisimo-migration' ) ),
	'plat.font.empty'        => array( 'section' => 'review', 'label' => __( 'Fuente sin ningún archivo utilizable: no se creó', 'digitalisimo-migration' ) ),
	'plat.font.failed'       => array( 'section' => 'review', 'label' => __( 'No se pudo crear la fuente propia', 'digitalisimo-migration' ) ),
	'plat.font.reverted'     => array( 'section' => 'converted', 'label' => __( 'Fuente creada por la migración retirada al revertir', 'digitalisimo-migration' ) ),

	/* ------------------------------------------------------------------ Iconos */
	'plat.icons.created'       => array( 'section' => 'created', 'label' => __( 'Conjunto de iconos propio creado con los mismos archivos', 'digitalisimo-migration' ) ),
	'plat.icons.renamed'       => array( 'section' => 'created', 'label' => __( 'Ya había un conjunto con ese nombre: el nuevo lleva un sufijo y el contenido se reescribe para usarlo', 'digitalisimo-migration' ) ),
	'plat.icons.skipped_file'  => array( 'section' => 'approx', 'label' => __( 'Archivos del conjunto que no se copiaron (enlaces, tipos no admitidos o fuera de la carpeta)', 'digitalisimo-migration' ) ),
	'plat.icons.missing_files' => array( 'section' => 'review', 'label' => __( 'No se encontraron los archivos del conjunto de iconos: no se creó', 'digitalisimo-migration' ) ),
	'plat.icons.unsafe'        => array( 'section' => 'review', 'label' => __( 'El conjunto de iconos tiene archivos que no superan la comprobación de seguridad: no se creó', 'digitalisimo-migration' ) ),
	'plat.icons.conflict'      => array( 'section' => 'review', 'label' => __( 'No se encontró un nombre libre para el conjunto de iconos: no se creó', 'digitalisimo-migration' ) ),
	'plat.icons.no_config'     => array( 'section' => 'review', 'label' => __( 'Conjunto de iconos sin configuración legible: no se creó', 'digitalisimo-migration' ) ),
	'plat.icons.rewritten'     => array( 'section' => 'converted', 'label' => __( 'Iconos de un conjunto propio apuntados al conjunto nuevo', 'digitalisimo-migration' ) ),
	'plat.icons.duotone'       => array( 'section' => 'approx', 'label' => __( 'Iconos de dos tonos: se ven con un solo color', 'digitalisimo-migration' ) ),
	'plat.icons.unknown'       => array( 'section' => 'review', 'label' => __( 'Iconos de un conjunto propio que ya no existe: se verán vacíos', 'digitalisimo-migration' ), 'review_if_published' => true ),
	'plat.icons.reverted'      => array( 'section' => 'converted', 'label' => __( 'Conjunto de iconos creado por la migración retirado al revertir', 'digitalisimo-migration' ) ),

	/* ------------------------------------------------------------------ Mega Menu */
	'plat.mega.template' => array( 'section' => 'created', 'label' => __( 'Plantilla creada con el contenido de un desplegable del mega menú (sin condiciones)', 'digitalisimo-migration' ) ),
	'plat.mega.rebuild'  => array( 'section' => 'review', 'label' => __( 'Mega menú convertido al widget Menú con el mismo menú: la navegación se conserva; los desplegables con contenido hay que rehacerlos con el widget Menú y, en cada desplegable, un widget Plantilla con la plantilla indicada', 'digitalisimo-migration' ) ),
	'plat.mega.lost'     => array( 'section' => 'dropped', 'label' => __( 'Ajustes del mega menú sin equivalente (ancho, insignia, icono o carga diferida del desplegable)', 'digitalisimo-migration' ) ),
	'plat.mega.empty'    => array( 'section' => 'dropped', 'label' => __( 'Desplegable del mega menú activado sin contenido: no se creó plantilla', 'digitalisimo-migration' ) ),
	'plat.mega.failed'   => array( 'section' => 'review', 'label' => __( 'No se pudo crear la plantilla de un desplegable del mega menú', 'digitalisimo-migration' ) ),
	'plat.mega.reverted' => array( 'section' => 'converted', 'label' => __( 'Plantilla del mega menú retirada al revertir', 'digitalisimo-migration' ) ),

	/* ------------------------------------------------------------------ Kit y ajustes de página */
	'plat.kit.values'              => array( 'section' => 'dropped', 'label' => __( 'Ajustes globales de Element Pack (caja de luz, botón de cierre, barra, navegación e información emergente) retirados del kit: sin el plugin no tenían efecto y la caja de luz del sitio sigue como está', 'digitalisimo-migration' ) ),
	'plat.kit.context_menu'        => array( 'section' => 'dropped', 'label' => __( 'Menú contextual de Element Pack retirado del kit: sin equivalente', 'digitalisimo-migration' ) ),
	'plat.kit.context_menu_styles' => array( 'section' => 'dropped', 'label' => __( 'Estilos del menú contextual sin prefijo que quedan guardados en el kit sin efecto', 'digitalisimo-migration' ) ),
	'plat.kit.page_values'         => array( 'section' => 'dropped', 'label' => __( 'Ajustes globales de Element Pack guardados en la página (versiones anteriores) retirados: no tenían efecto', 'digitalisimo-migration' ) ),
	'plat.kit.reverted'            => array( 'section' => 'converted', 'label' => __( 'Ajustes del kit restaurados al revertir', 'digitalisimo-migration' ) ),

	/* ------------------------------------------------------------------ Código global */
	'plat.code.css_published' => array( 'section' => 'created', 'label' => __( 'CSS global convertido en Código personalizado: se publica en cuanto el plugin anterior deja de estar cargado', 'digitalisimo-migration' ) ),
	'plat.code.css_draft'     => array( 'section' => 'review', 'label' => __( 'CSS global convertido en Código personalizado en borrador: revísalo y publícalo', 'digitalisimo-migration' ) ),
	'plat.code.css_denied'    => array( 'section' => 'review', 'label' => __( 'CSS global que no se publicó: al publicarlo, quien lanzó la migración ya no podía publicar código sin filtrar o el código guardado no superó la comprobación de seguridad; sigue en borrador', 'digitalisimo-migration' ) ),
	'plat.code.exclusions'    => array( 'section' => 'review', 'label' => __( 'Páginas excluidas del código global sin una condición equivalente: el fragmento quedó en borrador', 'digitalisimo-migration' ) ),
	'plat.code.inactive'      => array( 'section' => 'review', 'label' => __( 'Código global de un plugin que no estaba activo: queda en borrador para no cambiar el sitio', 'digitalisimo-migration' ) ),
	'plat.code.js'            => array( 'section' => 'review', 'label' => __( 'JavaScript global copiado a un borrador de Código personalizado: no se ejecuta hasta que alguien lo revise y lo publique', 'digitalisimo-migration' ) ),
	'plat.code.js_lost'       => array( 'section' => 'review', 'label' => __( 'JavaScript global que deja de ejecutarse al migrar: sólo puede copiarlo a un borrador quien puede publicar código sin filtrar', 'digitalisimo-migration' ) ),
	'plat.code.reverted'      => array( 'section' => 'converted', 'label' => __( 'Fragmento de código creado por la migración retirado al revertir', 'digitalisimo-migration' ) ),

	/* ------------------------------------------------------------------ Barra lateral */
	'plat.sidebar'          => array( 'section' => 'converted', 'label' => __( 'Widget «Plantilla» de la barra lateral sustituido por el widget Biblioteca con la misma plantilla', 'digitalisimo-migration' ) ),
	'plat.sidebar.inactive' => array( 'section' => 'review', 'label' => __( 'Widget «Plantilla» de la barra lateral sin sustituir: Element Pack no estaba activo y no se mostraba', 'digitalisimo-migration' ) ),
	'plat.sidebar.reverted' => array( 'section' => 'converted', 'label' => __( 'Barra lateral restaurada al revertir', 'digitalisimo-migration' ) ),

	/* ------------------------------------------------------------------ Muestras de variación */
	'plat.swatch.attribute'     => array( 'section' => 'converted', 'label' => __( 'Atributo con muestras de variación convertido a las muestras propias', 'digitalisimo-migration' ) ),
	'plat.swatch.term'          => array( 'section' => 'converted', 'label' => __( 'Colores e imágenes de términos copiados a los de WooCommerce', 'digitalisimo-migration' ) ),
	'plat.swatch.kept'          => array( 'section' => 'approx', 'label' => __( 'El atributo o el término ya tenía muestras propias: no se cambiaron', 'digitalisimo-migration' ) ),
	'plat.swatch.shape'         => array( 'section' => 'approx', 'label' => __( 'Forma cuadrada de las muestras convertida en esquinas redondeadas', 'digitalisimo-migration' ) ),
	'plat.swatch.size'          => array( 'section' => 'approx', 'label' => __( 'Tamaño de las muestras ajustado al intervalo admitido (24–64 px)', 'digitalisimo-migration' ) ),
	'plat.swatch.color_invalid' => array( 'section' => 'dropped', 'label' => __( 'Colores de muestra que no son hexadecimales: no se copiaron', 'digitalisimo-migration' ) ),
	'plat.swatch.label_lost'    => array( 'section' => 'dropped', 'label' => __( 'Etiquetas de muestra distintas del nombre del término: se muestra el nombre', 'digitalisimo-migration' ) ),
	'plat.swatch.products'      => array( 'section' => 'dropped', 'label' => __( 'Ajustes de muestras por producto sin equivalente (las muestras propias se configuran por atributo)', 'digitalisimo-migration' ) ),
	'plat.swatch.options'       => array( 'section' => 'dropped', 'label' => __( 'Opciones globales de las muestras sin equivalente (información emergente, botones automáticos o etiqueta seleccionada)', 'digitalisimo-migration' ) ),
	'plat.swatch.disabled'      => array( 'section' => 'dropped', 'label' => __( 'Muestras de variación configuradas pero apagadas: no se convirtieron', 'digitalisimo-migration' ) ),
	'plat.swatch.other_plugin'  => array( 'section' => 'review', 'label' => __( 'Hay otro plugin de muestras activo: las muestras no se convirtieron', 'digitalisimo-migration' ) ),
	'plat.swatch.limit'         => array( 'section' => 'review', 'label' => __( 'Se alcanzó el máximo de atributos con muestras: el resto se ve como desplegable', 'digitalisimo-migration' ) ),
	'plat.swatch.no_target'     => array( 'section' => 'review', 'label' => __( 'Las muestras propias no están disponibles (WooCommerce o el módulo de DIGITALÍSIMO Elements): no se convirtieron', 'digitalisimo-migration' ) ),
	'plat.swatch.inactive'      => array( 'section' => 'review', 'label' => __( 'Muestras de variación sin convertir: Element Pack no estaba activo y no se mostraban', 'digitalisimo-migration' ) ),
	'plat.swatch.reverted'      => array( 'section' => 'converted', 'label' => __( 'Muestras creadas por la migración retiradas al revertir', 'digitalisimo-migration' ) ),

	/* ------------------------------------------------------------------ Etiquetas dinámicas */
	'plat.tags.converted' => array( 'section' => 'converted', 'label' => __( 'Etiquetas dinámicas convertidas a sus equivalentes exactas', 'digitalisimo-migration' ) ),
	'plat.tags.dropped'   => array( 'section' => 'dropped', 'label' => __( 'Etiquetas dinámicas sin equivalente exacto: se quitaron y queda el valor fijo del campo', 'digitalisimo-migration' ), 'review_if_published' => true ),

	/* ------------------------------------------------------------------ Inventario */
	'plat.app_passwords'      => array( 'section' => 'review', 'label' => __( 'Contraseñas de aplicación creadas para la IA de Element Pack: siguen dando acceso aunque el plugin se desactive; revócalas desde el perfil del usuario si ya no se usan', 'digitalisimo-migration' ), 'private' => true ),
	'plat.api_keys'           => array( 'section' => 'dropped', 'label' => __( 'Claves de servicios guardadas por Element Pack (sólo los nombres; no se copian ni se muestran)', 'digitalisimo-migration' ) ),
	'plat.api_oauth'          => array( 'section' => 'dropped', 'label' => __( 'Autorización de Google guardada por Element Pack: no se copia', 'digitalisimo-migration' ) ),
	'plat.ai_settings'        => array( 'section' => 'dropped', 'label' => __( 'Ajustes de la IA de Element Pack: sin equivalente', 'digitalisimo-migration' ) ),
	'plat.ai_snapshots'       => array( 'section' => 'dropped', 'label' => __( 'Copias para deshacer de la IA de Element Pack guardadas en documentos: quedan sin uso', 'digitalisimo-migration' ) ),
	'plat.shortcodes'         => array( 'section' => 'review', 'label' => __( 'Shortcodes de Element Pack en el contenido: sin el plugin se verá el texto literal', 'digitalisimo-migration' ) ),
	'plat.shortcodes.widgets' => array( 'section' => 'review', 'label' => __( 'Shortcodes de Element Pack en widgets de texto o HTML: sin el plugin se verá el texto literal', 'digitalisimo-migration' ) ),
	'plat.core_files'         => array( 'section' => 'review', 'label' => __( 'Element Pack copió archivos dentro de la carpeta del constructor: rompen su huella; reinstala o actualiza DIGITALÍSIMO Elements para restaurarla', 'digitalisimo-migration' ) ),
	'plat.residue'            => array( 'section' => 'dropped', 'label' => __( 'Datos que el plugin anterior dejó en el sitio y la migración no borra', 'digitalisimo-migration' ) ),
	'plat.smooth_scroll'      => array( 'section' => 'dropped', 'label' => __( 'Desplazamiento suave de Element Pack: se pierde', 'digitalisimo-migration' ) ),
	'plat.wpml'               => array( 'section' => 'dropped', 'label' => __( 'WPML activo: las copias creadas por la migración no quedan vinculadas a sus traducciones', 'digitalisimo-migration' ) ),
);
