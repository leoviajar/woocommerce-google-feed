<?php
/**
 * Plugin Name: WooCommerce Feed XML
 * Description: Plugin para gerar feed XML de produtos WooCommerce com categorias do Google Shopping
 * Version: 1.0.2
 * Author: Leonardo
 * License: GPL v2 or later
 * Text Domain: wc-google-feed
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.2
 * WC tested up to: 8.5
 */

require 'plugin-update-checker/plugin-update-checker.php'; 
use YahnisElsts\PluginUpdateChecker\v5\PucFactory; 

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/leoviajar/woocommerce-google-feed',
    __FILE__,
    'woocommerce-google-feed.php'
);


// Prevenir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes do plugin
define('WC_GOOGLE_FEED_VERSION', '1.0.0');
define('WC_GOOGLE_FEED_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_GOOGLE_FEED_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_GOOGLE_FEED_PLUGIN_FILE', __FILE__);

// Declarar compatibilidade com HPOS
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Incluir arquivos necessários
require_once WC_GOOGLE_FEED_PLUGIN_DIR . 'includes/class-wc-google-feed.php';

/**
 * Inicializar o plugin
 */
function wc_google_feed_init() {
    WC_Google_Feed::get_instance();
}
add_action('plugins_loaded', 'wc_google_feed_init');

/**
 * Ativação do plugin
 */
register_activation_hook(__FILE__, function() {
    if (class_exists('WC_Google_Feed')) {
        WC_Google_Feed::get_instance()->activate();
    }
});

/**
 * Desativação do plugin
 */
register_deactivation_hook(__FILE__, function() {
    if (class_exists('WC_Google_Feed')) {
        WC_Google_Feed::get_instance()->deactivate();
    }
});

