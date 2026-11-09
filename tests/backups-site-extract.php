<?php
/**
 * «Extraer como WordPress individual»: un sitio de una red sale como paquete restaurable en una instalación individual
 * (alcance `extracted-site` del manifiesto v3) por el motor de trabajos, y se restaura en un WordPress individual con
 * otra URL y otro prefijo con el motor de reubicación de E1.
 *
 * Red por subdirectorios (prefijo wp_) con tres sitios. El sitio 2 (red.test/sub2) tiene contenido de Elements que
 * enlaza sus medios (uploads/sites/2), usuarios en varios roles, un usuario sólo de él, autores que no son miembros,
 * ajustes de red heredados de SEO, Tools, Elements, Hosting e IA, plugins activos en el sitio y en la red, y un tema
 * hijo. Se comprueba qué lleva el paquete (tablas, usuarios y metas filtrados, roles, medios, plugins, ajustes
 * materializados, secretos, informe), quién puede extraer (sólo la red, sólo el superadministrador, alcance explícito),
 * que el alcance se acepta sólo en un WordPress individual y que el heredado `site` sigue rechazado, y la restauración:
 * contraseñas y roles, medios en uploads/, plugins activos, ajustes y ningún rastro del dominio de la red salvo lo
 * declarado, conservando la configuración de Backups del destino.
 */
$root = sys_get_temp_dir() . '/digitalisimo-extract-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-site-extract/env.php';
$S = 'Digitalisimo_Backups';
$X = 'Digitalisimo_Backups_Site_Extract';
$db = $GLOBALS['wpdb'];
$public = $root . '/public';
$store = $root . '/private';

/* ------------------------------------------------------------------ La red */

function digi_network_files( $public ) {
	$files = array(
		'wp-config.php' => "<?php define( 'MULTISITE', true ); define( 'DB_PASSWORD', 'clave-de-la-red' );\n",
		// Configuración repartida en otros archivos de la raíz (patrón habitual): nunca viaja (H2).
		'wp-config-local.php' => "<?php define( 'DB_PASSWORD', 'clave-db-red' );\n",
		'wp-config-production.php' => "<?php define( 'DIGITALISIMO_BACKUPS_KEY', 'clave-maestra-red' );\n",
		'wp-salt.php' => "<?php define( 'NONCE_SALT', 'sal-nonce-red' );\n",
		'wp-custom.php' => "<?php // otro archivo de la red\n",
		'.env' => "SECRETO=env-de-la-red\n",
		'.htaccess' => "# reglas de la red\n",
		'robots.txt' => "User-agent: *\n",
		'index.php' => "<?php // index\n",
		'wp-load.php' => "<?php // load\n",
		'wp-settings.php' => "<?php // settings\n",
		'wp-cron.php' => "<?php // cron\n",
		'wp-admin/index.php' => "<?php // admin\n",
		'wp-includes/version.php' => "<?php \$wp_version = '6.8';\n",
		'otra-app/index.php' => "<?php // otra aplicación\n",
		'wp-content/index.php' => "<?php // Silence\n",
		'wp-content/sunrise.php' => "<?php // dominios de la red\n",
		'wp-content/object-cache.php' => "<?php // caché de objetos\n",
		'wp-content/cache/page.html' => '<html>caché de la red</html>',
		'wp-content/mu-plugins/mu.php' => "<?php // obligatorio\n",
		'wp-content/languages/es_MX.mo' => 'traducción',
		'wp-content/plugins/index.php' => "<?php // Silence\n",
		'wp-content/plugins/digitalisimo-backups/digitalisimo-backups.php' => "<?php /* Plugin Name: DIGITALÍSIMO Backups */\n",
		'wp-content/plugins/digitalisimo-elements/pro-elements.php' => "<?php /* Plugin Name: Elements */\n",
		'wp-content/plugins/digitalisimo-seo/digitalisimo-integrations.php' => "<?php /* Plugin Name: SEO */\n",
		'wp-content/plugins/digitalisimo-tools/digitalisimo-tools.php' => "<?php /* Plugin Name: Tools */\n",
		'wp-content/plugins/digitalisimo.chatbot/digitalisimo-chatbot.php' => "<?php /* Plugin Name: IA Tools */\n",
		'wp-content/plugins/digitalisimo-hosting/digitalisimo-hosting.php' => "<?php /* Plugin Name: Hosting (inactivo en el sitio 2) */\n",
		'wp-content/plugins/red-plugin/red-plugin.php' => "<?php /* Plugin Name: De la red */\n",
		'wp-content/plugins/otro-plugin/otro-plugin.php' => "<?php /* Plugin Name: Del sitio 3 */\n",
		'wp-content/plugins/hello.php' => "<?php /* Plugin Name: Hello */\n",
		'wp-content/themes/index.php' => "<?php // Silence\n",
		'wp-content/themes/tema/style.css' => "/* Theme Name: Tema */\n",
		'wp-content/themes/tema-hijo/style.css' => "/* Theme Name: Tema hijo\nTemplate: tema */\n",
		'wp-content/themes/otro-tema/style.css' => "/* Theme Name: Otro */\n",
		'wp-content/uploads/2026/01/principal.jpg' => 'medio del sitio principal',
		'wp-content/uploads/sites/2/2026/11/foto.jpg' => str_repeat( "\xff\xd8\xff\xe0JFIF", 512 ),
		'wp-content/uploads/sites/2/elementor/css/post-5.css' => '.elementor-5{background:url(https://red.test/sub2/wp-content/uploads/sites/2/2026/11/foto.jpg)}',
		'wp-content/uploads/sites/2/digitalisimo-elements/figura.svg' => '<svg/>',
		'wp-content/uploads/sites/3/2026/11/tres.jpg' => 'medio del sitio 3',
	);
	foreach ( $files as $name => $content ) digi_write( $public . '/' . $name, $content );
}
digi_network_files( $public );

$cap = function( array $roles ) { return serialize( array_fill_keys( $roles, true ) ); };
$roles = serialize( array( 'administrator' => array( 'name' => 'Administrator', 'capabilities' => array( 'manage_options' => true ) ), 'editor' => array( 'name' => 'Editor', 'capabilities' => array( 'edit_others_posts' => true ) ), 'author' => array( 'name' => 'Author', 'capabilities' => array( 'publish_posts' => true ) ), 'customer' => array( 'name' => 'Cliente', 'capabilities' => array( 'read' => true ) ) ) );
$options = array( 'option_id', 'option_name', 'option_value', 'autoload' );
$posts = array( 'ID', 'post_author', 'post_title', 'post_content', 'post_type', 'guid' );
$db->create( 'wp_blogs', array( 'blog_id', 'site_id', 'domain', 'path' ), array( array( 'blog_id' => 1, 'site_id' => 1, 'domain' => 'red.test', 'path' => '/' ), array( 'blog_id' => 2, 'site_id' => 1, 'domain' => 'red.test', 'path' => '/sub2/' ), array( 'blog_id' => 3, 'site_id' => 1, 'domain' => 'red.test', 'path' => '/sub3/' ) ) );
$db->create( 'wp_site', array( 'id', 'domain', 'path' ), array( array( 'id' => 1, 'domain' => 'red.test', 'path' => '/' ) ) );
$db->create( 'wp_sitemeta', array( 'meta_id', 'site_id', 'meta_key', 'meta_value' ), array(
	array( 'site_id' => 1, 'meta_key' => 'site_admins', 'meta_value' => serialize( array( 'red', 'otro' ) ) ),
	array( 'site_id' => 1, 'meta_key' => 'active_sitewide_plugins', 'meta_value' => serialize( array( 'digitalisimo-backups/digitalisimo-backups.php' => 1, 'red-plugin/red-plugin.php' => 1, 'digitalisimo-tools/digitalisimo-tools.php' => 1 ) ) ),
	array( 'site_id' => 1, 'meta_key' => 'subdomain_install', 'meta_value' => '0' ),
	array( 'site_id' => 1, 'meta_key' => 'siteurl', 'meta_value' => 'https://red.test/' ),
	array( 'site_id' => 1, 'meta_key' => 'digitalisimo_integrations_options', 'meta_value' => serialize( array( 'seo_title_sep' => '-', 'seo_robots_rules' => 'Disallow: /privado/', 'seo_site_name' => 'RED', 'openai_api_key' => 'sk-red-0001', 'ipinfo_tokens' => '', 'seo_ai_crawler_rules' => wp_json_encode( array( 'gptbot' => 'disallow', 'ccbot' => 'allow' ) ), 'seo_verify_google' => 'google-verif-red', 'perf_ga4_id' => 'G-RED0001', 'ai_providers' => array( 'openai' => array( 'enabled' => 1, 'key' => 'sk-proj-RED', 'url' => '' ) ), 'seo_future_setting' => 'ajuste-nuevo-de-seo' ) ) ),
	array( 'site_id' => 1, 'meta_key' => 'digitalisimo_tools_site_defaults', 'meta_value' => serialize( array( 'login_slug' => 'acceso-red', 'mobile_scroll' => 1, 'redirect_attachments' => 1 ) ) ),
	array( 'site_id' => 1, 'meta_key' => 'digitalisimo_elements_front_defaults', 'meta_value' => serialize( array( 'svg_icons' => 1, 'lazy_media' => 0 ) ) ),
	array( 'site_id' => 1, 'meta_key' => 'digitalisimo_elements_google_places_key', 'meta_value' => 'g-places-red' ),
	array( 'site_id' => 1, 'meta_key' => 'digitalisimo_ai_options', 'meta_value' => serialize( array( 'chat_enabled' => 1, 'providers' => array( 'openai' => array( 'enabled' => 1, 'key' => 'sk-ai-red' ) ) ) ) ),
	array( 'site_id' => 1, 'meta_key' => 'digitalisimo_hosting_options', 'meta_value' => serialize( array( 'enabled' => 1, 'api_key' => 'nc-red' ) ) ),
	array( 'site_id' => 1, 'meta_key' => 'otro_plugin_de_red', 'meta_value' => 'x' ),
	array( 'site_id' => 1, 'meta_key' => 'main_site', 'meta_value' => '1' ),
	array( 'site_id' => 1, 'meta_key' => 'nonce_salt', 'meta_value' => 'sal-generada-por-wordpress' ),
	array( 'site_id' => 1, 'meta_key' => '_site_transient_algo', 'meta_value' => 'y' ),
	array( 'site_id' => 1, 'meta_key' => 'digitalisimo_releases_last_known', 'meta_value' => 'z' ),
) );
$users = array( 'ID', 'user_login', 'user_pass', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'user_activation_key', 'user_status', 'display_name', 'spam', 'deleted' );
$user = function( $id, $login, $email ) { return array( 'ID' => $id, 'user_login' => $login, 'user_pass' => '$wp$2y$10$hash-de-' . $login, 'user_nicename' => $login, 'user_email' => $email, 'user_url' => '', 'user_registered' => '2026-01-01 00:00:00', 'user_activation_key' => 'activacion-' . $login, 'user_status' => 0, 'display_name' => ucfirst( $login ), 'spam' => 0, 'deleted' => 0 ); };
// 8 y 9, bloqueados en la red (deleted y spam): fuera de ella WordPress no lo comprueba (H3).
$db->create( 'wp_users', $users, array( $user( 1, 'red', 'red@red.test' ), $user( 2, 'editora', 'editora@red.test' ), $user( 3, 'solo2', 'solo2@cliente.test' ), $user( 4, 'solo3', 'solo3@otro.test' ), $user( 5, 'otro', 'otro@red.test' ), $user( 6, 'exautor', 'exautor@red.test' ), $user( 7, 'cliente', 'cliente@cliente.test' ), array( 'deleted' => 1 ) + $user( 8, 'bloqueado', 'bloqueado@red.test' ), array( 'spam' => 1 ) + $user( 9, 'spammer', 'spammer@red.test' ) ) );
$meta = array();
$add = function( $id, $key, $value ) use ( &$meta ) { $meta[] = array( 'user_id' => $id, 'meta_key' => $key, 'meta_value' => $value ); };
$add( 1, 'wp_capabilities', $cap( array( 'administrator' ) ) ); $add( 1, 'wp_user_level', '10' ); $add( 1, 'first_name', 'Red' ); $add( 1, 'primary_blog', '1' ); $add( 1, 'source_domain', 'red.test' ); $add( 1, 'wp_2_user-settings', 'libraryContent=browse' );
$add( 2, 'wp_capabilities', $cap( array( 'subscriber' ) ) ); $add( 2, 'wp_user_level', '0' ); $add( 2, 'wp_2_capabilities', $cap( array( 'editor' ) ) ); $add( 2, 'wp_2_user_level', '7' ); $add( 2, 'wp_3_capabilities', $cap( array( 'author' ) ) ); $add( 2, 'wp_3_user_level', '2' );
$add( 2, 'wp_2_user-settings', 'editor=html' ); $add( 2, 'wp_user-settings', 'editor=tinymce' ); $add( 2, 'wp_persisted_preferences', 'principal' ); $add( 2, 'wp_2_persisted_preferences', 'sub2' ); $add( 2, 'wp_2fa_enabled_methods', 'totp' ); $add( 2, 'wp_plugin_pref', 'del principal' ); $add( 2, 'wp_2_plugin_pref', 'del sitio 2' ); $add( 2, 'first_name', 'Editora' ); $add( 2, 'session_tokens', serialize( array( 'abc' => array( 'expiration' => 1 ) ) ) ); $add( 2, 'primary_blog', '2' ); $add( 2, 'source_domain', 'red.test' );
$add( 3, 'wp_2_capabilities', $cap( array( 'author' ) ) ); $add( 3, 'wp_2_user_level', '2' ); $add( 3, '_application_passwords', serialize( array( array( 'uuid' => 'u-1', 'password' => '$hash-app' ) ) ) ); $add( 3, 'primary_blog', '2' ); $add( 3, 'wp_plugin_pref', 'global de un plugin' );
$add( 4, 'wp_3_capabilities', $cap( array( 'administrator' ) ) ); $add( 4, 'wp_3_user_level', '10' ); $add( 4, 'first_name', 'Tres' );
$add( 5, 'wp_capabilities', $cap( array( 'administrator' ) ) ); $add( 5, 'first_name', 'Otro' );
$add( 6, 'wp_capabilities', $cap( array( 'subscriber' ) ) ); $add( 6, 'first_name', 'Ex' ); $add( 6, 'session_tokens', serialize( array( 'zzz' => array() ) ) ); $add( 6, '_application_passwords', serialize( array( array( 'uuid' => 'u-6' ) ) ) );
$add( 7, 'wp_2_capabilities', $cap( array( 'customer' ) ) ); $add( 7, 'wp_2_user_level', '0' );
$add( 6, 'billing_phone', '+52 55 0000 0000' ); $add( 6, '_two_factor_totp_key', 'JBSWY3DPEHPK3PXP' ); $add( 6, 'nickname', 'Ex' );
$add( 8, 'wp_2_capabilities', $cap( array( 'author' ) ) ); $add( 8, 'wp_2_user_level', '2' );
$add( 9, 'wp_2_capabilities', $cap( array( 'subscriber' ) ) ); $add( 9, 'wp_2_user_level', '0' );
$db->create( 'wp_usermeta', array( 'umeta_id', 'user_id', 'meta_key', 'meta_value' ), $meta );
// Sitio principal.
$db->create( 'wp_options', $options, array(
	array( 'option_name' => 'siteurl', 'option_value' => 'https://red.test', 'autoload' => 'yes' ),
	array( 'option_name' => 'home', 'option_value' => 'https://red.test', 'autoload' => 'yes' ),
	array( 'option_name' => 'admin_email', 'option_value' => 'red@red.test', 'autoload' => 'yes' ),
	array( 'option_name' => 'wp_user_roles', 'option_value' => $roles, 'autoload' => 'yes' ),
	array( 'option_name' => 'active_plugins', 'option_value' => serialize( array() ), 'autoload' => 'yes' ),
	array( 'option_name' => Digitalisimo_Backups::OPTION, 'option_value' => serialize( array( 'retention' => '100', 'sftp_host' => 'sftp.red.test' ) ), 'autoload' => 'no' ),
) );
$db->create( 'wp_posts', $posts, array( array( 'ID' => 1, 'post_author' => 1, 'post_title' => 'Portada de la red', 'post_content' => 'Red', 'post_type' => 'page', 'guid' => 'https://red.test/?p=1' ) ) );
$db->create( 'wp_postmeta', array( 'meta_id', 'post_id', 'meta_key', 'meta_value' ) );
$db->create( 'wp_comments', array( 'comment_ID', 'comment_post_ID', 'user_id', 'comment_content' ) );
$db->create( 'wp_2fa_policies', array( 'id', 'policy' ), array( array( 'policy' => 'red' ) ) );
$db->create( 'wp_global_plugin_log', array( 'id', 'entry' ), array( array( 'entry' => 'de toda la red' ) ) );
// Sitio 2: el que se extrae.
$elements = wp_json_encode( array( array( 'elType' => 'widget', 'settings' => array( 'image' => array( 'url' => 'https://red.test/sub2/wp-content/uploads/sites/2/2026/11/foto.jpg' ), 'link' => array( 'url' => 'https://red.test/sub2/contacto/' ) ) ) ) );
$db->create( 'wp_2_options', $options, array(
	array( 'option_name' => 'siteurl', 'option_value' => 'https://red.test/sub2', 'autoload' => 'yes' ),
	array( 'option_name' => 'home', 'option_value' => 'https://red.test/sub2', 'autoload' => 'yes' ),
	array( 'option_name' => 'blogname', 'option_value' => 'Sitio dos', 'autoload' => 'yes' ),
	array( 'option_name' => 'admin_email', 'option_value' => 'red@red.test', 'autoload' => 'yes' ),
	array( 'option_name' => 'wp_2_user_roles', 'option_value' => $roles, 'autoload' => 'yes' ),
	array( 'option_name' => 'active_plugins', 'option_value' => serialize( array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php' ) ), 'autoload' => 'yes' ),
	array( 'option_name' => 'stylesheet', 'option_value' => 'tema-hijo', 'autoload' => 'yes' ),
	array( 'option_name' => 'template', 'option_value' => 'tema', 'autoload' => 'yes' ),
	array( 'option_name' => 'upload_path', 'option_value' => '', 'autoload' => 'yes' ),
	array( 'option_name' => 'digitalisimo_backups_policies', 'option_value' => serialize( array( array( 'id' => 'monthly-full' ) ) ), 'autoload' => 'no' ),
	array( 'option_name' => 'digitalisimo_integrations_options', 'option_value' => serialize( array( 'seo_site_name' => 'Sub dos', 'seo_title_sep' => '|', 'ipinfo_tokens' => 'token-viejo-del-sitio', 'seo_ai_crawler_rules' => wp_json_encode( array( 'gptbot' => 'inherit', 'ccbot' => 'disallow', 'bingbot' => 'inherit' ) ) ) ), 'autoload' => 'yes' ),
	array( 'option_name' => 'digitalisimo_seo_network_inherit', 'option_value' => serialize( array( 'seo_title_sep' => 0, 'seo_ai_crawler_rules' => 0 ) ), 'autoload' => 'yes' ),
	array( 'option_name' => 'digitalisimo_tools_site_options', 'option_value' => serialize( array( 'values' => array( 'redirect_attachments' => 0 ), 'inherit' => array( 'redirect_attachments' => false ) ) ), 'autoload' => 'yes' ),
	array( 'option_name' => 'digitalisimo_elements_front', 'option_value' => serialize( array( 'lazy_media' => true, 'svg_icons' => false, 'own' => array( 'lazy_media' ) ) ), 'autoload' => 'yes' ),
	// Hosting hereda de la red, pero no está activo en el sitio: no recibe ajustes de la red (B3).
	array( 'option_name' => 'digitalisimo_hosting_options', 'option_value' => serialize( array( 'inherit_network' => 1, 'enabled' => 0 ) ), 'autoload' => 'yes' ),
) );
$db->create( 'wp_2_posts', $posts, array(
	array( 'ID' => 5, 'post_author' => 2, 'post_title' => 'Inicio', 'post_content' => '<img src="https://red.test/sub2/wp-content/uploads/sites/2/2026/11/foto.jpg"> <a href="https://red.test/sub2/contacto/">Contacto</a> <a href="https://red.test/">La red</a> <img src="https://red.test/wp-content/uploads/sites/2/2026/11/foto.jpg">', 'post_type' => 'page', 'guid' => 'https://red.test/sub2/?page_id=5' ),
	array( 'ID' => 6, 'post_author' => 6, 'post_title' => 'De un exautor', 'post_content' => 'Texto', 'post_type' => 'post', 'guid' => 'https://red.test/sub2/?p=6' ),
	array( 'ID' => 7, 'post_author' => 3, 'post_title' => 'foto', 'post_content' => '', 'post_type' => 'attachment', 'guid' => 'https://red.test/sub2/wp-content/uploads/sites/2/2026/11/foto.jpg' ),
	// El superadministrador «otro» corrigió una entrada como soporte: WordPress guarda la revisión a su nombre (H1).
	array( 'ID' => 50, 'post_author' => 5, 'post_title' => 'Inicio', 'post_content' => 'corrección', 'post_type' => 'revision', 'guid' => 'https://red.test/sub2/?p=50' ),
) );
$db->create( 'wp_2_postmeta', array( 'meta_id', 'post_id', 'meta_key', 'meta_value' ), array(
	array( 'post_id' => 5, 'meta_key' => '_elementor_data', 'meta_value' => $elements ),
	array( 'post_id' => 5, 'meta_key' => '_elementor_css', 'meta_value' => serialize( array( 'status' => 'file', 'url' => 'https://red.test/sub2/wp-content/uploads/sites/2/elementor/css/post-5.css' ) ) ),
	array( 'post_id' => 7, 'meta_key' => '_wp_attached_file', 'meta_value' => '2026/11/foto.jpg' ),
	array( 'post_id' => 7, 'meta_key' => '_wp_attachment_metadata', 'meta_value' => serialize( array( 'file' => '2026/11/foto.jpg', 'path' => WP_CONTENT_DIR . '/uploads/sites/2/2026/11/foto.jpg' ) ) ),
) );
$db->create( 'wp_2_comments', array( 'comment_ID', 'comment_post_ID', 'user_id', 'comment_content' ), array( array( 'comment_post_ID' => 6, 'user_id' => 7, 'comment_content' => 'Comentario de cliente' ), array( 'comment_post_ID' => 6, 'user_id' => 5, 'comment_content' => 'Respuesta de soporte' ), array( 'comment_post_ID' => 6, 'user_id' => 9, 'comment_content' => 'Spam' ) ) );
// Sitio 3: no viaja.
$db->create( 'wp_3_options', $options, array( array( 'option_name' => 'siteurl', 'option_value' => 'https://red.test/sub3', 'autoload' => 'yes' ), array( 'option_name' => 'home', 'option_value' => 'https://red.test/sub3', 'autoload' => 'yes' ), array( 'option_name' => 'active_plugins', 'option_value' => serialize( array( 'otro-plugin/otro-plugin.php' ) ), 'autoload' => 'yes' ) ) );
$db->create( 'wp_3_posts', $posts, array( array( 'ID' => 1, 'post_author' => 4, 'post_title' => 'Tres', 'post_content' => 'secreto del sitio tres', 'post_type' => 'post', 'guid' => 'https://red.test/sub3/?p=1' ) ) );
$db->create( 'wp_3_postmeta', array( 'meta_id', 'post_id', 'meta_key', 'meta_value' ) );
$GLOBALS['digi']['sites'] = array( 1 => array( 'domain' => 'red.test', 'path' => '/' ), 2 => array( 'domain' => 'red.test', 'path' => '/sub2/' ), 3 => array( 'domain' => 'red.test', 'path' => '/sub3/' ) );

/** WP-CLI mínimo, sin la constante WP_CLI (que haría pasar cualquier comprobación de permisos de esta prueba). */
class Digi_Cli_Exit extends Exception {}
class WP_CLI {
	public static $output = array();
	public static function success( $message ) { self::$output[] = 'Success: ' . $message; }
	public static function line( $message = '' ) { self::$output[] = (string) $message; }
	public static function log( $message ) { self::$output[] = (string) $message; }
	public static function warning( $message ) { self::$output[] = 'Warning: ' . $message; }
	public static function error( $message ) { self::$output[] = 'Error: ' . $message; throw new Digi_Cli_Exit( (string) $message ); }
}
require_once __DIR__ . '/../digitalisimo-backups/includes/class-backups-cli.php';
function digi_wp_extract( array $args, array $assoc ) {
	WP_CLI::$output = array();
	try { ( new Digitalisimo_Backups_Cli() )->extract( $args, $assoc ); return array( 0, WP_CLI::$output ); } catch ( Digi_Cli_Exit $e ) { return array( 1, WP_CLI::$output ); }
}
/** Ajustes de Backups con los secretos descifrados: la única lectura del plugin (settings()). */
function digi_settings() {
	$read = new ReflectionMethod( 'Digitalisimo_Backups', 'settings' );
	if ( PHP_VERSION_ID < 80100 ) $read->setAccessible( true );
	return $read->invoke( null );
}
/** Entradas del ZIP y su contenido. */
function digi_zip( $path ) {
	$zip = new ZipArchive();
	digi_assert( true === $zip->open( $path ), 'No se pudo abrir el paquete ' . basename( $path ) );
	$out = array();
	for ( $i = 0; $i < $zip->numFiles; $i++ ) $out[ $zip->getNameIndex( $i ) ] = $zip->getFromIndex( $i );
	$zip->close();
	return $out;
}
/** Filas del SQL de un paquete, por tabla, leídas como lo haría la importación. */
function digi_sql_rows( $sql ) {
	$out = array();
	foreach ( explode( ";\n", $sql ) as $statement ) {
		if ( ! preg_match( '/^INSERT INTO `([^`]+)` \((.*?)\) VALUES \((.*)\)$/s', trim( $statement ), $m ) ) continue;
		$columns = array_map( function( $c ) { return trim( $c, '` ' ); }, explode( ',', $m[2] ) );
		$out[ $m[1] ][] = array_combine( $columns, Digi_Extract_DB::values( $m[3] ) );
	}
	return $out;
}
function digi_meta_keys( array $rows, $user ) { $keys = array(); foreach ( $rows as $row ) if ( (int) $row['user_id'] === $user ) $keys[ $row['meta_key'] ] = $row['meta_value']; ksort( $keys ); return $keys; }
function digi_option( array $rows, $name ) { foreach ( $rows as $row ) if ( $row['option_name'] === $name ) return maybe_unserialize( $row['option_value'] ); return null; }
function digi_extract( $site, array $args = array() ) {
	$queued = Digitalisimo_Backups::service_extract( $site, $args + array( 'network' => true ) );
	digi_assert( ! is_wp_error( $queued ), 'No se encoló la extracción: ' . ( is_wp_error( $queued ) ? $queued->get_error_message() : '' ) );
	$done = digi_drive( $queued['id'] );
	digi_assert( 'success' === $done['status'], 'La extracción no terminó: ' . json_encode( $done ) );
	return $done;
}

try {
	/* ---- Quién puede extraer y con qué alcance ---- */
	// Lo que ve quien administra la red: la ruta REST y la acción de la pantalla de red existen y exigen el alcance de red.
	$rest = digi_rest( 'POST', 'digitalisimo-backups/v1/sites/2/extract', array() );
	digi_assert( 400 === $rest[0] && 'digitalisimo_backups_scope' === ( $rest[1]['code'] ?? '' ), 'REST no ofrece extraer un sitio de la red con el alcance explícito: ' . json_encode( $rest ) );
	digi_assert( ! empty( $GLOBALS['digi']['actions']['admin_post_digitalisimo_backups_extract'] ), 'La pantalla de red no tiene la acción «Extraer como WordPress individual».' );
	$error = $S::service_extract( 2, array() );
	digi_assert( is_wp_error( $error ) && 400 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'alcance de red' ), 'Se extrajo sin el alcance de red explícito.' );
	$GLOBALS['digi']['blog'] = 2;
	$error = $S::service_extract( 2, array( 'network' => true ) );
	digi_assert( is_wp_error( $error ) && 'digitalisimo_backups_main_site' === $error->get_error_code(), 'Un subsitio pudo extraer un sitio.' );
	$GLOBALS['digi']['blog'] = 1;
	$GLOBALS['digi']['super_admin'] = false;
	$error = $S::service_extract( 2, array( 'network' => true ) );
	digi_assert( is_wp_error( $error ) && 403 === $error->get_error_data()['status'], 'Alguien que no es superadministrador extrajo un sitio.' );
	$GLOBALS['digi']['super_admin'] = true;
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	$error = $S::service_extract( 2, array( 'network' => true ) );
	digi_assert( is_wp_error( $error ) && 403 === $error->get_error_data()['status'], 'Un administrador de sitio extrajo un sitio de la red.' );
	$GLOBALS['digi']['caps'] = array( 'manage_network_options' => true );
	$error = $S::service_extract( 9, array( 'network' => true ) );
	digi_assert( is_wp_error( $error ) && 404 === $error->get_error_data()['status'], 'Se aceptó un sitio que no existe.' );
	$error = $S::service_extract( 2, array( 'network' => true, 'super_admins' => 'todos' ) );
	digi_assert( is_wp_error( $error ) && 400 === $error->get_error_data()['status'], 'Se aceptó una opción de superadministradores inválida.' );
	digi_assert( array() === Digitalisimo_Backups_Jobs::all(), 'Un intento rechazado dejó un trabajo.' );

	/* ---- Extraer el sitio 2 (por defecto: ningún superadministrador que no es miembro, sin las claves de la red) ---- */
	$db->queries = array();
	$queued = $S::service_extract( 2, array( 'network' => true, 'via' => 'rest' ) );
	digi_assert( ! is_wp_error( $queued ) && 'queued' === $queued['status'] && 'extracted-site' === $queued['scope'] && 2 === $queued['site'] && false === $queued['encrypt'], 'La extracción no se encoló con su alcance: ' . json_encode( $queued ) );
	digi_assert( 'Extracción de red.test/sub2 como WordPress individual' === $queued['origin_label'], 'La tarjeta «Trabajos» no dice qué se extrae: ' . $queued['origin_label'] );
	$duplicate = $S::service_extract( 2, array( 'network' => true ) );
	digi_assert( is_wp_error( $duplicate ) && 409 === $duplicate->get_error_data()['status'], 'Se duplicó la extracción del mismo sitio.' );
	$done = digi_drive( $queued['id'] );
	digi_assert( 'success' === $done['status'] && 1 === preg_match( '/^extracted-site-red-test-sub2-.*\.zip$/', $done['file'] ), 'La extracción no terminó con su paquete: ' . json_encode( $done ) );
	// En una red grande no se recorren wp_users ni wp_usermeta enteras ni las metas de todos los sitios (P5).
	foreach ( $db->queries as $query ) {
		digi_assert( ! preg_match( '/^SELECT \* FROM `wp_(?:users|usermeta)`(?: ORDER BY|$)/', $query ), 'La exportación leyó una tabla de usuarios entera: ' . $query );
		digi_assert( ! preg_match( '/^SELECT DISTINCT meta_key FROM `wp_usermeta` WHERE meta_key LIKE/', $query ), 'La exportación leyó las metas de todos los usuarios de la red: ' . $query );
	}
	$row = null;
	foreach ( $S::service_backups() as $item ) if ( $item['file'] === $done['file'] ) $row = $item;
	digi_assert( $row && 'extracted-site' === $row['scope'] && false === $row['restorable'] && 'red.test/sub2' === $row['site'], 'El historial no describe el paquete como un sitio extraído: ' . json_encode( $row ) );
	// Lo que no viajó, por nombre, sólo en el historial de la red (B4).
	$history_row = null;
	foreach ( (array) get_option( $S::HISTORY, array() ) as $item ) if ( $item['file'] === $done['file'] ) $history_row = $item;
	digi_assert( in_array( 'otra-app', $history_row['extract']['excluded']['root'] ?? array(), true ) && in_array( 'otro-plugin', $history_row['extract']['excluded']['plugins'] ?? array(), true ) && in_array( 'wp_global_plugin_log', (array) ( $history_row['extract']['excluded_tables'] ?? array() ), true ), 'El historial de la red no guarda lo que no viajó: ' . json_encode( $history_row['extract'] ?? null ) );
	$package = $store . '/' . $done['file'];
	$entries = digi_zip( $package );
	$manifest = json_decode( $entries['manifest.json'], true );
	digi_assert( 'extracted-site' === $manifest['scope'] && false === $manifest['multisite'] && 'wp_2_' === $manifest['table_prefix'] && 'https://red.test/sub2' === $manifest['home_url'] && ! isset( $manifest['auth'] ), 'El manifiesto no es el de un sitio individual sin firma de la red: ' . json_encode( array_intersect_key( $manifest, array_flip( array( 'scope', 'multisite', 'table_prefix', 'home_url', 'auth' ) ) ) ) );
	digi_assert( 'https://red.test/sub2' === $manifest['origin']['home'] && 'wp_2_' === $manifest['origin']['prefix'] && false === $manifest['origin']['multisite'] && WP_CONTENT_DIR . '/uploads/sites/2' === $manifest['origin']['uploads_dir'], 'El origen del manifiesto no describe el sitio extraído: ' . json_encode( $manifest['origin'] ) );
	$tables = $manifest['database_tables'];
	sort( $tables );
	digi_assert( array( 'wp_2_comments', 'wp_2_options', 'wp_2_postmeta', 'wp_2_posts', 'wp_2_usermeta', 'wp_2_users' ) === $tables, 'Las tablas del paquete no son las del sitio: ' . json_encode( $tables ) );

	// Archivos por lista blanca: núcleo, plugins activos (del sitio y de la red), tema y padre, mu-plugins, idiomas y los medios en uploads/.
	$names = array_keys( $entries );
	foreach ( array( 'wordpress/wp-load.php', 'wordpress/wp-cron.php', 'wordpress/index.php', 'wordpress/wp-admin/index.php', 'wordpress/wp-content/index.php', 'wordpress/wp-content/mu-plugins/mu.php', 'wordpress/wp-content/languages/es_MX.mo', 'wordpress/wp-content/plugins/index.php', 'wordpress/wp-content/plugins/digitalisimo-backups/digitalisimo-backups.php', 'wordpress/wp-content/plugins/digitalisimo-elements/pro-elements.php', 'wordpress/wp-content/plugins/digitalisimo-seo/digitalisimo-integrations.php', 'wordpress/wp-content/plugins/digitalisimo-tools/digitalisimo-tools.php', 'wordpress/wp-content/plugins/digitalisimo.chatbot/digitalisimo-chatbot.php', 'wordpress/wp-content/plugins/red-plugin/red-plugin.php', 'wordpress/wp-content/themes/tema/style.css', 'wordpress/wp-content/themes/tema-hijo/style.css', 'wordpress/wp-content/uploads/2026/11/foto.jpg', 'wordpress/wp-content/uploads/elementor/css/post-5.css', 'wordpress/wp-content/uploads/digitalisimo-elements/figura.svg' ) as $expected ) digi_assert( in_array( $expected, $names, true ), 'Falta ' . $expected . ' en el paquete.' );
	foreach ( $names as $name ) {
		// Nada de configuración de la red (H2), drop-ins (P3), otras aplicaciones, plugins o temas que el sitio no usa ni medios de otros sitios.
		digi_assert( ! preg_match( '#^wordpress/(?:wp-config[^/]*\.php|wp-salt\.php|wp-custom\.php|\.env|\.htaccess|robots\.txt|otra-app/|wp-content/(?:sunrise|object-cache|db|advanced-cache)\.php|wp-content/cache/|wp-content/uploads/sites/|wp-content/uploads/2026/01/|wp-content/plugins/(?:otro-plugin|hello\.php|digitalisimo-hosting)|wp-content/themes/otro-tema)#', $name ), 'El paquete lleva algo que no es del sitio: ' . $name );
	}
	digi_assert( $entries['wordpress/wp-content/uploads/2026/11/foto.jpg'] === file_get_contents( $public . '/wp-content/uploads/sites/2/2026/11/foto.jpg' ), 'El medio del sitio no es el mismo archivo.' );
	foreach ( $entries as $name => $content ) foreach ( array( 'clave-de-la-red', 'clave-db-red', 'clave-maestra-red', 'sal-nonce-red', 'env-de-la-red' ) as $secret ) digi_assert( false === strpos( (string) $content, $secret ), 'El paquete lleva configuración de la red («' . $secret . '» en ' . $name . ').' );
	// El manifiesto dice lo que viaja y conviene revisar (mu-plugins) y los drop-ins que no; de lo demás, sólo recuentos (B4, P3, P4).
	digi_assert( array( 'mu.php' ) === $manifest['extract']['files']['mu_plugins'] && array( 'object-cache.php', 'sunrise.php' ) === $manifest['extract']['files']['drop_ins'] && 2 === $manifest['extract']['excluded_tables'], 'El informe no lista mu-plugins y drop-ins, o no cuenta lo que no viajó: ' . json_encode( $manifest['extract']['files'] ) );
	foreach ( array( 'otra-app', 'otro-plugin', 'otro-tema', 'wp_global_plugin_log', 'wp-config', '.env', 'hello.php' ) as $leak ) digi_assert( false === strpos( $entries['manifest.json'], $leak ), 'El manifiesto nombra algo de la red que no viaja: ' . $leak );

	// Base de datos: usuarios y metas del sitio, roles, plugins y ajustes.
	$sql = $entries['database.sql'];
	digi_assert( false !== strpos( $sql, 'CREATE TABLE `wp_2_users`' ) && false !== strpos( $sql, 'CREATE TABLE `wp_2_usermeta`' ) && false === strpos( $sql, '`wp_users`' ) && false === strpos( $sql, '`wp_options`' ), 'Las tablas de usuarios no llevan el prefijo del sitio.' );
	foreach ( array( 'secreto del sitio tres', 'sk-red-0001', 'sk-ai-red', 'sk-proj-RED', 'google-verif-red', 'G-RED0001', 'ajuste-nuevo-de-seo', 'g-places-red', 'nc-red', 'hash-de-solo3', 'hash-de-red', 'hash-de-otro', 'hash-de-exautor', 'hash-de-bloqueado', 'hash-de-spammer', 'otro@red.test', 'exautor@red.test', 'JBSWY3DPEHPK3PXP', '+52 55', 'wp_3_', 'source_domain', 'sftp.red.test' ) as $absent ) digi_assert( false === strpos( $sql, $absent ), 'El SQL del paquete lleva «' . $absent . '».' );
	$rows = digi_sql_rows( $sql );
	$ids = array_map( 'intval', array_column( $rows['wp_2_users'], 'ID' ) );
	sort( $ids );
	// Miembros 2, 3 y 7; autores sin cuenta 5 (superadministrador que sólo editó y comentó: H1), 6 (retirado) y 9 (spam: H3).
	// No viajan 1 (superadministrador que no es miembro), 4 (otro sitio) ni 8 (deleted en la red, sin contenido).
	digi_assert( array( 2, 3, 5, 6, 7, 9 ) === $ids, 'Los usuarios del paquete no son los del sitio: ' . json_encode( $ids ) );
	foreach ( $rows['wp_2_users'] as $u ) {
		if ( in_array( (int) $u['ID'], array( 5, 6, 9 ), true ) ) digi_assert( '' === $u['user_pass'] && '' === $u['user_activation_key'] && '' === $u['user_email'] && '' === $u['user_url'] && '' !== $u['display_name'], 'Un autor sin cuenta en el sitio se llevó su contraseña o sus datos: ' . json_encode( $u ) );
		else digi_assert( '$wp$2y$10$hash-de-' . $u['user_login'] === $u['user_pass'] && '0' === (string) $u['spam'], 'El usuario ' . $u['user_login'] . ' no conserva su contraseña y todos sus datos.' );
	}
	$um = $rows['wp_2_usermeta'];
	digi_assert( array( '_application_passwords', 'wp_2_capabilities', 'wp_2_user_level', 'wp_plugin_pref' ) === array_keys( digi_meta_keys( $um, 3 ) ), 'Las metas del miembro no son las suyas: ' . json_encode( array_keys( digi_meta_keys( $um, 3 ) ) ) );
	$editora = digi_meta_keys( $um, 2 );
	digi_assert( array( 'first_name', 'session_tokens', 'wp_2_capabilities', 'wp_2_persisted_preferences', 'wp_2_plugin_pref', 'wp_2_user-settings', 'wp_2_user_level', 'wp_2fa_enabled_methods' ) === array_keys( $editora ), 'Las metas de la editora no se filtraron bien: ' . json_encode( array_keys( $editora ) ) );
	digi_assert( array( 'editor' => true ) === maybe_unserialize( $editora['wp_2_capabilities'] ) && '7' === $editora['wp_2_user_level'], 'La editora no conserva su rol del sitio.' );
	// Autores sin cuenta: sólo el perfil público, nunca un rol, el segundo factor ni la facturación de otra tienda (H5).
	digi_assert( array( 'first_name', 'nickname' ) === array_keys( digi_meta_keys( $um, 6 ) ) && array( 'first_name' ) === array_keys( digi_meta_keys( $um, 5 ) ) && array() === array_keys( digi_meta_keys( $um, 9 ) ), 'Un autor sin cuenta en el sitio se llevó metas que no son de su autoría: ' . json_encode( array( digi_meta_keys( $um, 5 ), digi_meta_keys( $um, 6 ), digi_meta_keys( $um, 9 ) ) ) );
	digi_assert( array( 'customer' => true ) === maybe_unserialize( digi_meta_keys( $um, 7 )['wp_2_capabilities'] ), 'El cliente no conserva su rol personalizado.' );
	digi_assert( count( array_unique( array_column( $um, 'umeta_id' ) ) ) === count( $um ), 'Dos metas comparten identificador.' );
	$opts = $rows['wp_2_options'];
	digi_assert( is_array( digi_option( $opts, 'wp_2_user_roles' ) ) && isset( digi_option( $opts, 'wp_2_user_roles' )['customer'] ), 'Los roles del sitio no viajan con su clave.' );
	digi_assert( array( 'digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo-tools/digitalisimo-tools.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'red-plugin/red-plugin.php' ) === digi_option( $opts, 'active_plugins' ), 'Los plugins activos no son los del sitio y de la red: ' . json_encode( digi_option( $opts, 'active_plugins' ) ) );
	digi_assert( null === digi_option( $opts, 'digitalisimo_backups_policies' ), 'La configuración de Backups de la red viajó en el sitio.' );
	// Ajustes de red por lista blanca: lo de la cuenta de la red sólo con network_secrets y lo desconocido no se copia (P6).
	$seo = digi_option( $opts, 'digitalisimo_integrations_options' );
	digi_assert( '|' === $seo['seo_title_sep'] && 'Sub dos' === $seo['seo_site_name'] && 'Disallow: /privado/' === $seo['seo_robots_rules'] && '' === $seo['openai_api_key'] && '' === $seo['ipinfo_tokens'] && '' === $seo['seo_verify_google'] && '' === $seo['perf_ga4_id'] && array( 'enabled' => 1, 'key' => '', 'url' => '' ) === $seo['ai_providers']['openai'] && ! isset( $seo['seo_future_setting'] ), 'SEO no quedó con la resolución sitio → red por lista blanca: ' . json_encode( $seo ) );
	digi_assert( array( 'gptbot' => 'disallow', 'ccbot' => 'disallow' ) === json_decode( $seo['seo_ai_crawler_rules'], true ), 'Las reglas de rastreadores heredadas no se resolvieron con las de la red: ' . $seo['seo_ai_crawler_rules'] );
	digi_assert( array( 'values' => array( 'redirect_attachments' => 0, 'login_slug' => 'acceso-red', 'mobile_scroll' => 1 ), 'inherit' => array( 'redirect_attachments' => false ) ) === digi_option( $opts, 'digitalisimo_tools_site_options' ), 'Tools (activo en la red) no materializó los ajustes de red: ' . json_encode( digi_option( $opts, 'digitalisimo_tools_site_options' ) ) );
	$front = digi_option( $opts, 'digitalisimo_elements_front' );
	digi_assert( true === $front['lazy_media'] && true === $front['svg_icons'], 'Elements no materializó sus interruptores de red: ' . json_encode( $front ) );
	$ai = digi_option( $opts, 'digitalisimo_ai_options' );
	digi_assert( 1 === $ai['chat_enabled'] && '' === $ai['providers']['openai']['key'] && 1 === $ai['providers']['openai']['enabled'], 'IA no heredó la red sin su clave: ' . json_encode( $ai ) );
	// Hosting hereda pero no está activo en el sitio: no recibe nada de la red (B3).
	digi_assert( array( 'inherit_network' => 1, 'enabled' => 0 ) === digi_option( $opts, 'digitalisimo_hosting_options' ) && null === digi_option( $opts, 'digitalisimo_elements_google_places_key' ), 'Se escribieron ajustes de la red en un plugin que el sitio no tiene activo: ' . json_encode( digi_option( $opts, 'digitalisimo_hosting_options' ) ) );

	// Informe del paquete: sin valores ni datos personales.
	$report = $manifest['extract'];
	digi_assert( array( 'policy' => 'none', 'total' => 6, 'members' => 3, 'super_admins' => 0, 'authors' => 3, 'super_admins_left' => 2, 'blocked' => 2 ) === $report['users'], 'El informe de usuarios no cuadra: ' . json_encode( $report['users'] ) );
	$withheld = $report['settings']['secrets_withheld'];
	sort( $withheld );
	digi_assert( array( 'digitalisimo_ai_options:providers.openai.key', 'digitalisimo_elements_google_places_key', 'digitalisimo_integrations_options:ai_providers', 'digitalisimo_integrations_options:openai_api_key', 'digitalisimo_integrations_options:perf_ga4_id', 'digitalisimo_integrations_options:seo_verify_google' ) === $withheld, 'El informe no nombra las claves y cuentas de la red que no viajaron: ' . json_encode( $withheld ) );
	digi_assert( array( 'digitalisimo_integrations_options:seo_future_setting' ) === $report['settings']['not_copied'] && array( 'hosting' ) === $report['settings']['inactive'], 'El informe no dice qué no se copió por desconocido o por plugin inactivo: ' . json_encode( $report['settings'] ) );
	digi_assert( array( 'otro_plugin_de_red' ) === $report['network_options'], 'El informe no lista los ajustes de red de otros plugins: ' . json_encode( $report['network_options'] ) );
	digi_assert( false === strpos( $entries['manifest.json'], 'editora@' ) && false === strpos( $entries['manifest.json'], 'sk-' ), 'El manifiesto lleva datos personales o secretos.' );
	// La URL que da WordPress al trabajo (que corre en el principal) es la del principal; la del propio sitio va como alias.
	digi_assert( array( 'https://red.test/sub2/wp-content/uploads/sites/2' ) === $report['uploads']['aliases'] && 'https://red.test/wp-content/uploads/sites/2' === $report['uploads']['baseurl'], 'El informe no lleva las dos URL de los medios del sitio: ' . json_encode( $report['uploads'] ) );
	$summary = $S::service_verify( $done['file'] );
	digi_assert( ! is_wp_error( $summary ) && 'extracted-site' === $summary['scope'] && false === $summary['compatible'] && 'red.test/sub2' === $summary['extract']['site'] && 2 === $summary['extract']['blocked_users'] && array( 'mu.php' ) === $summary['extract']['mu_plugins'], 'verify no describe el sitio extraído: ' . json_encode( is_wp_error( $summary ) ? $summary->get_error_message() : $summary ) );
	digi_assert( false !== strpos( implode( ' ', $summary['problems'] ), 'WordPress individual' ), 'verify no dice que se restaura en un WordPress individual.' );

	// En la red no se restaura (antes de tocar nada).
	$copies = count( glob( $store . '/safety-before-restore-*' ) ?: array() );
	$rejected = $S::service_restore( $done['file'], array( 'confirm' => 'RESTAURAR', 'network' => true ) );
	digi_assert( is_wp_error( $rejected ) && 422 === $rejected->get_error_data()['status'] && $copies === count( glob( $store . '/safety-before-restore-*' ) ?: array() ), 'La red aceptó restaurar un sitio extraído: ' . json_encode( is_wp_error( $rejected ) ? $rejected->get_error_message() : $rejected ) );

	/* ---- Superadministradores: «managing» sólo por el correo de administración; «all», todos ---- */
	$managing = digi_extract( 2, array( 'super_admins' => 'managing' ) );
	$managing_rows = digi_sql_rows( digi_zip( $store . '/' . $managing['file'] )['database.sql'] );
	$red = digi_meta_keys( $managing_rows['wp_2_usermeta'], 1 );
	$otro = digi_meta_keys( $managing_rows['wp_2_usermeta'], 5 );
	digi_assert( array( 'administrator' => true ) === maybe_unserialize( $red['wp_2_capabilities'] ?? '' ) && '10' === $red['wp_2_user_level'], 'Con «managing», quien tiene el correo de administración del sitio no entró como administrador: ' . json_encode( $red ) );
	digi_assert( ! isset( $otro['wp_2_capabilities'] ) && false === strpos( digi_zip( $store . '/' . $managing['file'] )['database.sql'], 'hash-de-otro' ), 'Con «managing», un superadministrador que sólo editó o comentó entró como administrador o con su contraseña (H1).' );
	digi_assert( 'admin' !== ( $X::users( array( 2 ), array( 5 => 'otro@red.test' ), array( 2, 5 ), array( 'duena@cliente.test' ), 'managing' )[5] ?? '' ), 'Escribir o comentar cuenta como administrar el sitio.' );
	$all = digi_extract( 2, array( 'super_admins' => 'all', 'network_secrets' => true ) );
	$all_rows = digi_sql_rows( digi_zip( $store . '/' . $all['file'] )['database.sql'] );
	$ids = array_map( 'intval', array_column( $all_rows['wp_2_users'], 'ID' ) );
	sort( $ids );
	digi_assert( array( 1, 2, 3, 5, 6, 7, 9 ) === $ids && array( 'administrator' => true ) === maybe_unserialize( digi_meta_keys( $all_rows['wp_2_usermeta'], 5 )['wp_2_capabilities'] ), 'Con «all», todos los superadministradores no entraron como administradores (los bloqueados nunca): ' . json_encode( $ids ) );
	$all_seo = digi_option( $all_rows['wp_2_options'], 'digitalisimo_integrations_options' );
	digi_assert( 'sk-red-0001' === $all_seo['openai_api_key'] && 'sk-proj-RED' === $all_seo['ai_providers']['openai']['key'] && 'google-verif-red' === $all_seo['seo_verify_google'] && ! isset( $all_seo['seo_future_setting'] ) && 'g-places-red' === digi_option( $all_rows['wp_2_options'], 'digitalisimo_elements_google_places_key' ) && 'sk-ai-red' === digi_option( $all_rows['wp_2_options'], 'digitalisimo_ai_options' )['providers']['openai']['key'], 'Con las claves de la red pedidas, no viajaron (o viajó lo desconocido).' );

	/* ---- Nombres de tema y de plugin que salen de su carpeta (H4) ---- */
	$set_option = function( $table, $name, $value ) use ( $db ) { foreach ( $db->tables[ $table ]['rows'] as $i => $r ) if ( $r['option_name'] === $name ) $db->tables[ $table ]['rows'][ $i ]['option_value'] = $value; };
	$set_option( 'wp_2_options', 'stylesheet', '..' );
	$set_option( 'wp_2_options', 'active_plugins', serialize( array( 'digitalisimo-elements/pro-elements.php', './x.php', '../otro-plugin/otro-plugin.php' ) ) );
	$odd = digi_extract( 2 );
	$odd_entries = digi_zip( $store . '/' . $odd['file'] );
	foreach ( array_keys( $odd_entries ) as $name ) digi_assert( ! preg_match( '#(?:^|/)\.{1,2}(?:/|$)|uploads/sites/3|otro-plugin|otro-tema|cache/page#', $name ), 'Un nombre de tema o de plugin sacó al paquete algo de fuera de su carpeta: ' . $name );
	$odd_manifest = json_decode( $odd_entries['manifest.json'], true );
	digi_assert( in_array( '..', $odd_manifest['extract']['files']['rejected'], true ) && in_array( './x.php', $odd_manifest['extract']['files']['rejected'], true ), 'El informe no dice qué nombres se rechazaron: ' . json_encode( $odd_manifest['extract']['files'] ) );
	$set_option( 'wp_2_options', 'stylesheet', 'tema-hijo' );
	$set_option( 'wp_2_options', 'active_plugins', serialize( array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php' ) ) );
	foreach ( array( 'tema', 'tema-hijo', 'mi.plugin', 'a_b-c' ) as $ok ) digi_assert( $X::safe_name( $ok ), 'Se rechazó un nombre válido: ' . $ok );
	foreach ( array( '.', '..', '.oculto', 'a/b', '', 'a\\b' ) as $bad ) digi_assert( ! $X::safe_name( $bad ), 'Se aceptó un nombre que sale de su carpeta: ' . $bad );

	/* ---- Frase propia del paquete: nunca la clave de la red (P1); la cabecera dice lo que es (B2) ---- */
	update_site_option( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => true ) );
	$error = $S::service_extract( 2, array( 'network' => true ) );
	digi_assert( is_wp_error( $error ) && 400 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'frase propia' ), 'Con el cifrado de la red activo se extrajo sin frase propia: ' . json_encode( is_wp_error( $error ) ? $error->get_error_message() : $error ) );
	$error = $S::service_extract( 2, array( 'network' => true, 'secret' => 'corta' ) );
	digi_assert( is_wp_error( $error ) && 400 === $error->get_error_data()['status'], 'Se aceptó una frase de menos de 16 caracteres.' );
	$phrase = 'frase del cliente <nuevo> & "propia"';
	$sealed = digi_extract( 2, array( 'secret' => $phrase ) );
	digi_assert( '.dgbk' === substr( $sealed['file'], -5 ), 'El sitio extraído con frase no salió cifrado: ' . $sealed['file'] );
	$header = Digitalisimo_Backups_Encrypted_File::read_header( $store . '/' . $sealed['file'] )['header'];
	digi_assert( 'extracted-site' === $header['scope'] && false === $header['multisite'] && 'passphrase' === $header['key']['source'], 'La cabecera del paquete cifrado no dice lo que es: ' . json_encode( array_intersect_key( $header, array_flip( array( 'scope', 'multisite', 'key' ) ) ) ) );
	digi_assert( array() === ( glob( $store . '/.extract-key-*' ) ?: array() ), 'La clave del paquete se quedó en el almacén después de registrarlo.' );
	digi_assert( false === Digitalisimo_Backups_Keys::resolve_by_id( $header['key']['id'] ) && false === strpos( (string) json_encode( get_site_option( Digitalisimo_Backups_Keys::KEYRING ) ), $header['key']['id'] ), 'La clave del paquete quedó en el llavero de la red.' );
	update_site_option( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => false ) );
	$sealed_file = $sealed['file'];

	/* ---- Red antigua con ms_files_rewriting: las URL /files/ pasan a uploads/ (P2) ---- */
	update_site_option( 'ms_files_rewriting', 1 );
	switch_to_blog( 2 ); update_option( 'fileupload_url', 'https://red.test/sub2/files' ); restore_current_blog();
	$legacy_aliases = $X::upload_aliases( 2, array( 'basedir' => WP_CONTENT_DIR . '/blogs.dir/2/files', 'baseurl' => 'https://red.test/wp-content/blogs.dir/2/files' ) );
	digi_assert( in_array( 'https://red.test/sub2/files', $legacy_aliases, true ), 'Con ms_files_rewriting, la URL /files/ del sitio no va al plan: ' . json_encode( $legacy_aliases ) );
	update_site_option( 'ms_files_rewriting', 0 );
	switch_to_blog( 2 ); delete_option( 'fileupload_url' ); restore_current_blog();

	/* ---- El sitio principal: sus tablas (sin las globales ni las de los demás sitios) y sus medios sin uploads/sites/ ---- */
	digi_assert( array( 'wp_2fa_policies', 'wp_comments', 'wp_global_plugin_log', 'wp_options', 'wp_postmeta', 'wp_posts' ) === $X::site_tables( 1 ), 'Las tablas del sitio principal no son las suyas: ' . json_encode( $X::site_tables( 1 ) ) );
	$main = digi_extract( 1, array( 'super_admins' => 'none' ) );
	$main_entries = digi_zip( $store . '/' . $main['file'] );
	$main_manifest = json_decode( $main_entries['manifest.json'], true );
	$main_tables = $main_manifest['database_tables'];
	sort( $main_tables );
	digi_assert( 'wp_' === $main_manifest['table_prefix'] && array( 'wp_2fa_policies', 'wp_comments', 'wp_global_plugin_log', 'wp_options', 'wp_postmeta', 'wp_posts', 'wp_usermeta', 'wp_users' ) === $main_tables, 'El paquete del sitio principal no lleva sus tablas: ' . json_encode( $main_tables ) );
	digi_assert( isset( $main_entries['wordpress/wp-content/uploads/2026/01/principal.jpg'] ) && ! preg_grep( '#^wordpress/wp-content/uploads/sites/#', array_keys( $main_entries ) ), 'Los medios del sitio principal no son los suyos.' );
	$main_rows = digi_sql_rows( $main_entries['database.sql'] );
	$main_ids = array_map( 'intval', array_column( $main_rows['wp_users'], 'ID' ) );
	sort( $main_ids );
	digi_assert( array( 1, 2, 5, 6 ) === $main_ids && array( 'subscriber' => true ) === maybe_unserialize( digi_meta_keys( $main_rows['wp_usermeta'], 2 )['wp_capabilities'] ) && ! isset( digi_meta_keys( $main_rows['wp_usermeta'], 2 )['wp_2_capabilities'] ), 'Los usuarios del sitio principal no son los suyos: ' . json_encode( $main_ids ) );

	/* ---- REST y la pantalla de red ---- */
	$rest = digi_rest( 'POST', 'digitalisimo-backups/v1/sites/3/extract', array() );
	digi_assert( 400 === $rest[0], 'REST extrajo sin network=true: ' . json_encode( $rest ) );
	$rest = digi_rest( 'POST', 'digitalisimo-backups/v1/sites/3/extract', array( 'network' => true, 'super_admins' => 'none' ) );
	digi_assert( 202 === $rest[0] && 'extracted-site' === $rest[1]['scope'] && 3 === $rest[1]['site'], 'REST no encoló la extracción: ' . json_encode( $rest ) );
	digi_assert( 'success' === digi_drive( $rest[1]['id'] )['status'], 'La extracción por REST no terminó.' );
	list( $code, $out ) = digi_wp_extract( array( '3' ), array( 'super-admins' => 'none', 'porcelain' => true ) );
	digi_assert( 1 === $code && false !== strpos( implode( ' ', $out ), 'alcance de red' ), 'WP-CLI extrajo sin --network: ' . json_encode( $out ) );
	list( $code, $out ) = digi_wp_extract( array( '3' ), array( 'network' => true, 'super-admins' => 'none', 'porcelain' => true ) );
	digi_assert( 0 === $code && 1 === preg_match( '/^extracted-site-red-test-sub3-\d{8}-\d{6}-[0-9a-f]{8}\.zip$/', (string) end( $out ) ), 'WP-CLI no terminó la extracción con su paquete: ' . json_encode( $out ) );
	$query = digi_admin_post( array( $S, 'extract_request' ), $X::ACTION, array( 'site_id' => '2' ) );
	digi_assert( 'error' === $query['backup'] && false !== strpos( $query['message'], 'administración de la red' ), 'La pantalla de un sitio pudo extraer: ' . json_encode( $query ) );
	$query = digi_admin_post( array( $S, 'extract_request' ), $X::ACTION, array( 'site_id' => '2', 'digitalisimo_backups_network' => '1', 'super_admins' => 'managing' ) );
	digi_assert( 'ok' === $query['backup'], 'La pantalla de red no encoló la extracción: ' . json_encode( $query ) );
	$active = Digitalisimo_Backups_Jobs::active();
	digi_assert( 1 === count( $active ) && 'admin-post' === $active[0]['spec']['origin']['via'] && 'extracted-site' === $active[0]['spec']['scope'], 'La pantalla no encoló por el caso de uso.' );
	digi_drive( $active[0]['id'] );
	ob_start();
	$panel = new ReflectionMethod( $S, 'extract_panel' );
	if ( PHP_VERSION_ID < 80100 ) $panel->setAccessible( true );
	$panel->invoke( null, array( 'destination' => 'local' ) );
	$html = ob_get_clean();
	digi_assert( false !== strpos( $html, 'name="digitalisimo_backups_network" value="1"' ) && false !== strpos( $html, 'red.test/sub2 (ID 2)' ) && false !== strpos( $html, 'value="none" selected' ) && false !== strpos( $html, 'name="extract_secret"' ) && false !== strpos( $html, 'mu.php' ) && false !== strpos( $html, 'object-cache.php' ), 'La tarjeta de la red no ofrece los sitios, el alcance de red, la frase propia o el aviso de mu-plugins y drop-ins: ' . $html );

	/* ---- Restaurar en un WordPress individual recién instalado, con otra URL y otro prefijo ---- */
	$extracted = $done['file'];
	$GLOBALS['digi']['multisite'] = false;
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	foreach ( scandir( $public ) as $name ) if ( '.' !== $name && '..' !== $name ) digi_rmtree( $public . '/' . $name );
	foreach ( array( 'wp-config.php' => "<?php // destino\n", 'index.php' => "<?php // index\n", 'wp-load.php' => '<?php', 'wp-settings.php' => '<?php', 'wp-admin/index.php' => '<?php', 'wp-includes/version.php' => '<?php', 'wp-content/plugins/digitalisimo-backups/digitalisimo-backups.php' => "<?php /* Plugin Name: DIGITALÍSIMO Backups */\n", 'wp-content/themes/twentytwentysix/style.css' => '/* destino */' ) as $name => $content ) digi_write( $public . '/' . $name, $content );
	$db->tables = array();
	$db->base_prefix = $db->prefix = 'dst_';
	// El destino viene de 1.0.60: su contraseña de SFTP sigue en claro en los ajustes (B1).
	$saved = array( 'retention' => '9', 'sftp_host' => 'sftp.destino.test', 'sftp_password' => 'clave-sftp-destino' );
	$db->create( 'dst_options', $options, array(
		array( 'option_name' => 'siteurl', 'option_value' => 'https://destino.test:8443', 'autoload' => 'yes' ),
		array( 'option_name' => 'home', 'option_value' => 'https://destino.test:8443', 'autoload' => 'yes' ),
		array( 'option_name' => 'dst_user_roles', 'option_value' => $roles, 'autoload' => 'yes' ),
		array( 'option_name' => 'active_plugins', 'option_value' => serialize( array( 'digitalisimo-backups/digitalisimo-backups.php' ) ), 'autoload' => 'yes' ),
		array( 'option_name' => Digitalisimo_Backups::OPTION, 'option_value' => serialize( $saved ), 'autoload' => 'no' ),
		array( 'option_name' => 'digitalisimo_backups_policies', 'option_value' => serialize( array( array( 'id' => 'del-destino', 'type' => 'full', 'enabled' => true ) ) ), 'autoload' => 'no' ),
	) );
	$db->create( 'dst_users', $users, array( $user( 1, 'destino', 'admin@destino.test' ) ) );
	$db->create( 'dst_usermeta', array( 'umeta_id', 'user_id', 'meta_key', 'meta_value' ), array( array( 'user_id' => 1, 'meta_key' => 'dst_capabilities', 'meta_value' => $cap( array( 'administrator' ) ) ) ) );
	$db->create( 'dst_posts', $posts, array( array( 'ID' => 1, 'post_author' => 1, 'post_title' => 'Hola mundo', 'post_content' => '', 'post_type' => 'post', 'guid' => 'https://destino.test:8443/?p=1' ) ) );
	$db->create( 'dst_postmeta', array( 'meta_id', 'post_id', 'meta_key', 'meta_value' ) );
	// En otro servidor el paquete llega como un archivo suelto del almacén (sin fila en el historial: sin firma).
	$check = $S::service_verify( $extracted );
	digi_assert( ! is_wp_error( $check ) && true === $check['compatible'] && 'RESTAURAR SIN FIRMA' === $check['confirmation'], 'Un WordPress individual no acepta el sitio extraído: ' . json_encode( is_wp_error( $check ) ? $check->get_error_message() : $check ) );
	digi_assert( false !== strpos( $check['relocation']['text'], 'https://red.test/sub2' ) && false !== strpos( $check['relocation']['text'], 'wp_2_ → dst_' ), 'verify no dice qué se reescribirá: ' . json_encode( $check['relocation'] ) );
	$restored = $S::service_restore( $extracted, array( 'confirm' => 'RESTAURAR SIN FIRMA' ) );
	digi_assert( ! is_wp_error( $restored ) && true === $restored['restored'], 'La restauración del sitio extraído falló: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : '' ) );
	digi_assert( false !== strpos( $restored['notice'], 'red.test/sub2' ) && false !== strpos( $restored['notice'], 'contraseña' ), 'La restauración no dice con qué cuentas se entra: ' . $restored['notice'] );
	digi_assert( ! isset( $db->tables['wp_2_options'] ) && ! isset( $db->tables['wp_2_users'] ) && isset( $db->tables['dst_users'] ), 'Las tablas no quedaron con el prefijo del destino: ' . json_encode( array_keys( $db->tables ) ) );
	// Usuarios: contraseña y rol, con el prefijo del destino; la cuenta del destino ya no existe.
	$logins = array();
	foreach ( $db->rows( 'dst_users' ) as $u ) $logins[ $u['user_login'] ] = $u['user_pass'];
	ksort( $logins );
	digi_assert( array( 'cliente' => '$wp$2y$10$hash-de-cliente', 'editora' => '$wp$2y$10$hash-de-editora', 'exautor' => '', 'otro' => '', 'solo2' => '$wp$2y$10$hash-de-solo2', 'spammer' => '' ) === $logins, 'Los usuarios restaurados no son los del sitio con su contraseña: ' . json_encode( $logins ) );
	$dum = $db->rows( 'dst_usermeta' );
	digi_assert( array( 'editor' => true ) === maybe_unserialize( digi_meta_keys( $dum, 2 )['dst_capabilities'] ) && '7' === digi_meta_keys( $dum, 2 )['dst_user_level'] && array( 'author' => true ) === maybe_unserialize( digi_meta_keys( $dum, 3 )['dst_capabilities'] ) && ! isset( digi_meta_keys( $dum, 5 )['dst_capabilities'] ), 'Los roles no quedaron con el prefijo del destino: ' . json_encode( digi_meta_keys( $dum, 2 ) ) );
	foreach ( $dum as $m ) digi_assert( 0 !== strpos( $m['meta_key'], 'wp_2_' ), 'Quedó una meta con el prefijo de la red: ' . $m['meta_key'] );
	digi_assert( is_array( get_option( 'dst_user_roles' ) ) && isset( get_option( 'dst_user_roles' )['customer'] ) && false === get_option( 'wp_2_user_roles' ), 'Los roles del sitio no quedaron en dst_user_roles.' );
	// Direcciones: las del destino, también los medios (de uploads/sites/2 a uploads/), en serializados y en JSON de Elements.
	digi_assert( 'https://destino.test:8443' === get_option( 'home' ) && 'https://destino.test:8443' === get_option( 'siteurl' ), 'El sitio no quedó con la URL del destino.' );
	$page = null;
	foreach ( $db->rows( 'dst_posts' ) as $p ) if ( 5 === (int) $p['ID'] ) $page = $p;
	digi_assert( $page && 2 === substr_count( $page['post_content'], 'https://destino.test:8443/wp-content/uploads/2026/11/foto.jpg' ) && false !== strpos( $page['post_content'], 'https://destino.test:8443/contacto/' ), 'El contenido no quedó con las URL del destino: ' . ( $page['post_content'] ?? '' ) );
	digi_assert( 'https://red.test/sub2/?page_id=5' === $page['guid'], 'Se cambió la columna guid.' );
	$elementor = null;
	foreach ( $db->rows( 'dst_postmeta' ) as $pm ) if ( '_elementor_data' === $pm['meta_key'] ) $elementor = json_decode( $pm['meta_value'], true );
	digi_assert( 'https://destino.test:8443/wp-content/uploads/2026/11/foto.jpg' === $elementor[0]['settings']['image']['url'], 'El contenido de Elements no quedó con los medios del destino: ' . json_encode( $elementor ) );
	foreach ( $db->rows( 'dst_postmeta' ) as $pm ) {
		digi_assert( '_elementor_css' !== $pm['meta_key'], 'El CSS generado de Elements no se vació.' );
		if ( '_wp_attachment_metadata' === $pm['meta_key'] ) digi_assert( WP_CONTENT_DIR . '/uploads/2026/11/foto.jpg' === maybe_unserialize( $pm['meta_value'] )['path'], 'La ruta de un medio no pasó a uploads/: ' . $pm['meta_value'] );
	}
	// Ningún rastro de la red salvo lo declarado: guid, correos y el enlace a la portada de la red.
	foreach ( $db->tables as $name => $table ) foreach ( $table['rows'] as $r ) foreach ( $r as $column => $value ) {
		// El informe de la reubicación nombra el origen a propósito (qué se reescribió).
		if ( 'guid' === $column || null === $value || Digitalisimo_Backups::LAST_RELOCATION === ( $r['option_name'] ?? '' ) ) continue;
		$value = str_replace( array( '@red.test', '"https://red.test/"' ), '', (string) $value );
		digi_assert( false === strpos( $value, 'red.test/sub2' ) && false === strpos( $value, 'uploads/sites/2' ) && false === strpos( $value, '/sites/2' ), 'Quedó el sitio de la red en ' . $name . '.' . $column . ': ' . substr( $value, 0, 160 ) );
	}
	// Archivos, plugins y tema.
	digi_assert( is_file( $public . '/wp-content/uploads/2026/11/foto.jpg' ) && ! is_dir( $public . '/wp-content/uploads/sites' ) && is_file( $public . '/wp-content/themes/tema-hijo/style.css' ) && is_file( $public . '/wp-content/plugins/red-plugin/red-plugin.php' ), 'Los archivos del sitio no quedaron en su lugar.' );
	digi_assert( "<?php // destino\n" === file_get_contents( $public . '/wp-config.php' ), 'La restauración tocó el wp-config.php del destino.' );
	digi_assert( array( 'digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo-tools/digitalisimo-tools.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'red-plugin/red-plugin.php' ) === get_option( 'active_plugins' ) && 'tema-hijo' === get_option( 'stylesheet' ), 'Los plugins o el tema no quedaron activos.' );
	digi_assert( 'acceso-red' === get_option( 'digitalisimo_tools_site_options' )['values']['login_slug'], 'Los ajustes materializados no llegaron.' );
	// La configuración de Backups es la del destino; su contraseña de SFTP, que estaba en claro, quedó en el almacén
	// cifrado y no se perdió; el historial conserva la copia previa.
	$destination_settings = get_option( Digitalisimo_Backups::OPTION );
	digi_assert( '9' === $destination_settings['retention'] && 'sftp.destino.test' === $destination_settings['sftp_host'] && ! isset( $destination_settings['sftp_password'] ) && 'del-destino' === get_option( 'digitalisimo_backups_policies' )[0]['id'], 'Se perdió la configuración de Backups del destino: ' . json_encode( $destination_settings ) );
	digi_assert( 'clave-sftp-destino' === ( digi_settings()['sftp_password'] ?? '' ), 'Se perdió la contraseña de SFTP del destino: ' . json_encode( array( get_option( Digitalisimo_Backups_Credentials::OPTION ), get_option( Digitalisimo_Backups::OPTION ) ) ) );
	$history = (array) get_option( Digitalisimo_Backups::HISTORY, array() );
	digi_assert( 1 === count( $history ) && 'pre-restore' === $history[0]['policy'] && is_file( $store . '/' . $history[0]['file'] ), 'El historial del destino no conserva su copia previa.' );

	// Una restauración interrumpida tras el SQL: el diario devuelve la configuración del destino con sus credenciales (B1).
	$journal = new ReflectionMethod( $S, 'write_restore_journal' );
	$recover = new ReflectionMethod( $S, 'recover_interrupted_restore' );
	if ( PHP_VERSION_ID < 80100 ) { $journal->setAccessible( true ); $recover->setAccessible( true ); }
	$journal->invoke( null, array(), $X::journal_snapshot( $X::destination_snapshot() ) );
	$journal_text = (string) file_get_contents( $store . '/' . Digitalisimo_Backups::RESTORE_JOURNAL );
	digi_assert( false === strpos( $journal_text, 'clave-sftp-destino' ) && false !== strpos( $journal_text, Digitalisimo_Backups_Credentials::OPTION ), 'El diario lleva un secreto en claro o no lleva el almacén cifrado del destino.' );
	foreach ( array( Digitalisimo_Backups::OPTION, Digitalisimo_Backups_Credentials::OPTION, 'digitalisimo_backups_policies' ) as $gone ) delete_option( $gone );
	$recover->invoke( null );
	digi_assert( 'clave-sftp-destino' === ( digi_settings()['sftp_password'] ?? '' ) && '9' === get_option( Digitalisimo_Backups::OPTION )['retention'] && 'del-destino' === get_option( 'digitalisimo_backups_policies' )[0]['id'], 'La recuperación de una restauración interrumpida perdió las credenciales o la configuración del destino.' );

	// El paquete cifrado con su frase se abre en el destino sólo con esa frase (nunca hizo falta la clave de la red) (P1).
	$need = $S::service_verify( $sealed_file );
	digi_assert( is_wp_error( $need ) && 'digitalisimo_backups_secret_required' === $need->get_error_code(), 'El paquete cifrado se abrió sin su frase.' );
	$opened = $S::service_verify( $sealed_file, array( 'secret' => $phrase ) );
	digi_assert( ! is_wp_error( $opened ) && 'signed' === $opened['trust'] && 'dgbk' === $opened['container'] && true === $opened['compatible'] && 'red.test/sub2' === $opened['extract']['site'] && 'RESTAURAR' === $opened['confirmation'], 'El paquete cifrado no se abre con su frase en el destino: ' . json_encode( is_wp_error( $opened ) ? $opened->get_error_message() : $opened ) );

	// Con «conservar las direcciones del respaldo», los medios pasan a la carpeta de un sitio individual con la dirección
	// del sitio en la red (sus archivos están ya en uploads/), y no a la del destino.
	$keep = Digitalisimo_Backups_Relocate::plan( Digitalisimo_Backups_Relocate::origin_of( $manifest ), Digitalisimo_Backups_Relocate::describe(), array( 'addresses' => 'backup', 'tables' => $manifest['database_tables'] ) );
	$X::adjust_plan( $keep, $manifest );
	$urls = $keep->pairs()['urls'];
	digi_assert( 'https://red.test/sub2/wp-content/uploads' === ( $urls['https://red.test/wp-content/uploads/sites/2'] ?? '' ) && 'https://red.test/sub2/wp-content/uploads' === ( $urls['https://red.test/sub2/wp-content/uploads/sites/2'] ?? '' ) && ! isset( $urls['https://red.test/sub2'] ), 'Conservando las direcciones, los medios no pasan a uploads/ del sitio: ' . json_encode( $urls ) );
	// El alcance heredado `site` (create_site de la v2) sigue rechazado en un WordPress individual.
	$legacy = $store . '/digitalisimo-site-2-red-test-full-20260101-000000.zip';
	$zip = new ZipArchive();
	$zip->open( $legacy, ZipArchive::CREATE );
	$zip->addFromString( 'database.sql', "SET NAMES utf8mb4;\n" );
	$zip->addFromString( 'migration.json', wp_json_encode( array( 'scope' => 'site', 'type' => 'full' ) ) );
	$zip->addFromString( 'wordpress/wp-load.php', '<?php' );
	$zip->close();
	$error = $S::service_restore( basename( $legacy ), array( 'confirm' => 'RESTAURAR SIN FIRMA' ) );
	digi_assert( is_wp_error( $error ) && 422 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'subsitio' ), 'Se aceptó el alcance heredado site: ' . json_encode( is_wp_error( $error ) ? $error->get_error_message() : $error ) );

	echo "ok · extraer como WordPress individual: paquete del sitio 2, usuarios y metas, roles, medios, plugins, ajustes de red, permisos y restauración en otra URL y otro prefijo\n";
} finally {
	digi_rmtree( $root );
}
