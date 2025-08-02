<?php
/**
 * Classe para meta box dos produtos
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Google_Feed_Meta_Box {
    
    /**
     * Construtor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'));
    }
    
    /**
     * Adicionar meta box
     */
    public function add_meta_box() {
        add_meta_box(
            'wc-google-feed-category',
            __('Categoria do Google', 'wc-google-feed'),
            array($this, 'meta_box_callback'),
            'product',
            'side',
            'default'
        );
    }
    
    /**
     * Callback do meta box
     */
    public function meta_box_callback($post) {
        // Nonce para segurança
        wp_nonce_field('wc_google_feed_meta_box', 'wc_google_feed_meta_box_nonce');
        
        // Valor atual
        $current_category = get_post_meta($post->ID, '_google_product_category', true);
        
        $current_gender = get_post_meta($post->ID, '_google_product_gender', true);
        $current_age_group = get_post_meta($post->ID, '_google_product_age_group', true);
        $current_material = get_post_meta($post->ID, '_google_product_material', true);
        $current_pattern = get_post_meta($post->ID, '_google_product_pattern', true);
        
        // Carregar categorias do Google
        $google_categories = WC_Google_Feed_Taxonomy::get_categories();
        
        include WC_GOOGLE_FEED_PLUGIN_DIR . 'templates/meta-box.php';
    }
    
    /**
     * Salvar meta box
     */
    public function save_meta_box($post_id) {
        // Verificar nonce
        if (!isset($_POST['wc_google_feed_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['wc_google_feed_meta_box_nonce'], 'wc_google_feed_meta_box')) {
            return;
        }
        
        // Verificar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verificar permissões
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Salvar categoria
        if (isset($_POST['google_product_category'])) {
            $category = sanitize_text_field($_POST['google_product_category']);
            update_post_meta($post_id, '_google_product_category', $category);
        }
        
        // Salvar gênero
        if (isset($_POST['google_product_gender'])) {
            $gender = sanitize_text_field($_POST['google_product_gender']);
            update_post_meta($post_id, '_google_product_gender', $gender);
        }
        
        // Salvar faixa etária
        if (isset($_POST['google_product_age_group'])) {
            $age_group = sanitize_text_field($_POST['google_product_age_group']);
            update_post_meta($post_id, '_google_product_age_group', $age_group);
        }

        // Salvar material
        if (isset($_POST["google_product_material"])) {
            $material = sanitize_text_field($_POST["google_product_material"]);
            update_post_meta($post_id, "_google_product_material", $material);
        }

        // Salvar pattern
        if (isset($_POST["google_product_pattern"])) {
            $pattern = sanitize_text_field($_POST["google_product_pattern"]);
            update_post_meta($post_id, "_google_product_pattern", $pattern);
        }
    }
}

