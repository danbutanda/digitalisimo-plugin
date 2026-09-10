<?php
/**
 * Plugin Name: Digitalisimo Chatbot
 * Description: IA modular para chatbot, contenido, SEO, ecommerce e imágenes.
 * Version: 1.0.12
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */
defined( 'ABSPATH' ) || exit;
define( 'DIGITALISIMO_CHATBOT_VERSION', '1.0.12' ); define( 'DIGITALISIMO_CHATBOT_URL', plugin_dir_url( __FILE__ ) );
require_once __DIR__ . '/includes/class-digitalisimo-core.php'; require_once __DIR__ . '/includes/class-digitalisimo-ai.php'; require_once __DIR__ . '/includes/class-digitalisimo-ai-knowledge.php'; require_once __DIR__ . '/includes/class-digitalisimo-ai-images.php'; require_once __DIR__ . '/includes/class-content-automation.php'; require_once __DIR__ . '/includes/class-chatbot-updater.php';
add_action( 'plugins_loaded', function() { Digitalisimo_Core::boot( 'ai', 'AI y Chatbot', array( 'Digitalisimo_AI', 'page' ), array( 'Digitalisimo_AI', 'network_page' ) ); Digitalisimo_Chatbot_Updater::init(); Digitalisimo_AI::init(); Digitalisimo_AI_Knowledge::init(); Digitalisimo_AI_Images::init(); Digitalisimo_Content_Automation::init(); } );
