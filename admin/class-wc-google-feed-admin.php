<?php
/**
 * Classe para funcionalidades do admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Google_Feed_Admin {
    
    /**
     * Construtor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Adicionar menu admin
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Google Feed', 'wc-google-feed'),
            __('Google Feed', 'wc-google-feed'),
            'manage_woocommerce',
            'wc-google-feed', // Usar um slug estático para o menu
            array($this, 'admin_page'),
            'dashicons-rss',
            56
        );
        
        // Submenu Produtos
        add_submenu_page(
            'wc-google-feed',
            __('Produtos', 'wc-google-feed'),
            __('Produtos', 'wc-google-feed'),
            'manage_woocommerce',
            'wc-google-feed-products',
            array($this, 'products_page')
        );
    }
    
    /**
     * Página admin principal
     */
    public function admin_page() {
        include WC_GOOGLE_FEED_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    /**
     * Página de produtos
     */
    public function products_page() {
        include WC_GOOGLE_FEED_PLUGIN_DIR . 'templates/products-page.php';
    }
    
    /**
     * Enqueue scripts admin
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        // CSS para todas as páginas do plugin
        if (strpos($hook, 'wc-google-feed') !== false) {
            wp_enqueue_style(
                'wc-google-feed-admin',
                WC_GOOGLE_FEED_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                WC_GOOGLE_FEED_VERSION
            );
        
            // JavaScript para a página principal
            wp_enqueue_script(
                'wc-google-feed-admin',
                WC_GOOGLE_FEED_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                WC_GOOGLE_FEED_VERSION,
                true
            );
        }
        
        // Scripts específicos para produtos
        if ($post_type === 'product') {
            wp_enqueue_script('select2');
            wp_enqueue_style('select2');
            
            wp_add_inline_script('select2', '
                jQuery(document).ready(function($) {
                    $("#google_product_category").select2({
                        placeholder: "' . __('Buscar categoria...', 'wc-google-feed') . '",
                        allowClear: true
                    });
                });
            ');
        }
    }
    
    /**
     * Contar produtos com categoria do Google
     */
    public function count_products_with_google_category() {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT post_id) 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = %s 
            AND pm.meta_value != ''
            AND p.post_type = 'product'
            AND p.post_status = 'publish'
        ", '_google_product_category'));
        
        return intval($count);
    }
    
    /**
     * Salvar dados dos produtos em massa
     */
    public function save_products_data() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        if (!isset($_POST['products']) || !is_array($_POST['products'])) {
            return;
        }
        
        foreach ($_POST['products'] as $product_id => $data) {
            $product_id = intval($product_id);
            
            if ($product_id <= 0) {
                continue;
            }
            
            // Salvar categoria do Google
            if (isset($data['category'])) {
                $category = sanitize_text_field($data['category']);
                update_post_meta($product_id, '_google_product_category', $category);
            }
            
            // Salvar gênero
            if (isset($data['gender'])) {
                $gender = sanitize_text_field($data['gender']);
                update_post_meta($product_id, '_google_product_gender', $gender);
            }
            
            // Salvar faixa etária
            if (isset($data['age_group'])) {
                $age_group = sanitize_text_field($data['age_group']);
                update_post_meta($product_id, '_google_product_age_group', $age_group);
            }

            // Salvar material
            if (isset($data["material"])) {
                $material = sanitize_text_field($data["material"]);
                update_post_meta($product_id, "_google_product_material", $material);
            }

            // Salvar pattern
            if (isset($data["pattern"])) {
                $pattern = sanitize_text_field($data["pattern"]);
                update_post_meta($product_id, "_google_product_pattern", $pattern);
            }
        }
    }
}

