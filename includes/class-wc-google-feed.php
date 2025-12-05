<?php
/**
 * Classe principal do plugin WooCommerce Google Feed
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Google_Feed {
    
    /**
     * Instância única do plugin
     */
    private static $instance = null;
    
    /**
     * Obter instância única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Inicializar plugin
     */
    public function init() {
        // Verificar se WooCommerce está ativo
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Carregar textdomain
        add_action('init', array($this, 'load_textdomain'));
        
        // Incluir arquivos necessários
        $this->includes();
        
        // Inicializar hooks
        $this->init_hooks();
    }
    
    /**
     * Verificar se WooCommerce está ativo
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Aviso de WooCommerce ausente
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('WooCommerce Google Feed requer o WooCommerce para funcionar.', 'wc-google-feed');
        echo '</p></div>';
    }
    
    /**
     * Carregar textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('wc-google-feed', false, dirname(plugin_basename(WC_GOOGLE_FEED_PLUGIN_FILE)) . '/languages');
    }
    
    /**
     * Incluir arquivos necessários
     */
    private function includes() {
        require_once WC_GOOGLE_FEED_PLUGIN_DIR . 'includes/class-wc-google-feed-taxonomy.php';
        require_once WC_GOOGLE_FEED_PLUGIN_DIR . 'includes/class-wc-google-feed-xml.php';
        require_once WC_GOOGLE_FEED_PLUGIN_DIR . 'admin/class-wc-google-feed-admin.php';
        require_once WC_GOOGLE_FEED_PLUGIN_DIR . 'admin/class-wc-google-feed-meta-box.php';
        require_once WC_GOOGLE_FEED_PLUGIN_DIR . 'public/class-wc-google-feed-public.php';
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Inicializar classes
        new WC_Google_Feed_Admin();
        new WC_Google_Feed_Meta_Box();
        new WC_Google_Feed_Public();
        
        // Nota: Rewrite rules e handlers do feed estão em WC_Google_Feed_Public
    }
    
    /**
     * Ativação do plugin
     */
    public function activate() {
        // Adicionar rewrite rules via classe Public
        $public = new WC_Google_Feed_Public();
        $public->add_rewrite_rules();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Desativação do plugin
     */
    public function deactivate() {
        // Limpar cache
        delete_transient('wc_google_feed_categories');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

