<?php
/**
 * Plugin Name: Digitalisimo IA Tools
 * Description: Herramientas de IA para chatbot, contenido, SEO, ecommerce, conocimiento e imágenes.
 * Version: 1.0.30
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */
defined( 'ABSPATH' ) || exit;
define( 'DIGITALISIMO_CHATBOT_VERSION', '1.0.30' ); define( 'DIGITALISIMO_CHATBOT_FILE', __FILE__ ); define( 'DIGITALISIMO_CHATBOT_URL', plugin_dir_url( __FILE__ ) );
require_once __DIR__ . '/includes/class-digitalisimo-core.php'; require_once __DIR__ . '/includes/class-digitalisimo-ai.php'; require_once __DIR__ . '/includes/class-digitalisimo-ai-knowledge.php'; require_once __DIR__ . '/includes/class-digitalisimo-ai-images.php'; require_once __DIR__ . '/includes/class-digitalisimo-ai-article-chat.php'; require_once __DIR__ . '/includes/class-digitalisimo-ai-content-playbook.php'; require_once __DIR__ . '/includes/class-digitalisimo-ai-content-publisher.php'; require_once __DIR__ . '/includes/class-content-automation.php'; require_once __DIR__ . '/includes/class-digitalisimo-mcp-articles.php'; require_once __DIR__ . '/includes/class-digitalisimo-updater.php';
add_action( 'plugins_loaded', function() { Digitalisimo_Core::boot( 'ai', 'IA Tools', array( 'Digitalisimo_AI', 'page' ), array( 'Digitalisimo_AI', 'network_page' ) ); Digitalisimo_Updater::register( DIGITALISIMO_CHATBOT_FILE, DIGITALISIMO_CHATBOT_VERSION, 'digitalisimo-ia-tools' ); Digitalisimo_AI::init(); Digitalisimo_AI_Knowledge::init(); Digitalisimo_AI_Images::init(); Digitalisimo_AI_Article_Chat::init(); Digitalisimo_AI_Content_Publisher::init(); Digitalisimo_Content_Automation::init(); Digitalisimo_MCP_Articles::init(); } );
