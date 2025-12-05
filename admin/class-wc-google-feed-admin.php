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
        add_action('wp_ajax_wc_google_feed_search_categories', array($this, 'ajax_search_categories'));
        add_action('wp_ajax_wc_google_feed_apply_rules', array($this, 'ajax_apply_rules'));
        add_action('wp_ajax_wc_google_feed_test_rule', array($this, 'ajax_test_rule'));
        
        // Hook para aplicar regras automaticamente em novos produtos
        add_action('woocommerce_new_product', array($this, 'auto_apply_rules_to_product'));
        add_action('woocommerce_update_product', array($this, 'auto_apply_rules_to_product'));
    }
    
    /**
     * AJAX para buscar categorias do Google
     */
    public function ajax_search_categories() {
        check_ajax_referer('wc_google_feed_ajax', 'nonce');
        
        $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $categories = WC_Google_Feed_Taxonomy::get_categories();
        
        $results = array();
        $count = 0;
        $max_results = 50; // Limitar resultados para performance
        
        foreach ($categories as $id => $name) {
            if (empty($search) || stripos($name, $search) !== false || strpos($id, $search) !== false) {
                $results[] = array(
                    'id' => $id,
                    'text' => $name
                );
                $count++;
                if ($count >= $max_results) break;
            }
        }
        
        wp_send_json(array('results' => $results));
    }
    
    /**
     * AJAX para aplicar regras de preenchimento inteligente
     */
    public function ajax_apply_rules() {
        check_ajax_referer('wc_google_feed_ajax', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'wc-google-feed')));
        }
        
        $rules = get_option('wc_google_feed_smart_rules', array());
        
        if (empty($rules)) {
            wp_send_json_error(array('message' => __('Nenhuma regra configurada.', 'wc-google-feed')));
        }
        
        $result = $this->apply_rules_to_products($rules);
        
        // Salvar no histórico
        $this->save_rules_history($result['updated'], count($rules), 'manual');
        
        wp_send_json_success(array(
            'message' => sprintf(
                __('%d produto(s) atualizado(s) com sucesso!', 'wc-google-feed'),
                $result['updated']
            ),
            'updated' => $result['updated'],
            'products' => array_slice($result['products'], 0, 10)
        ));
    }
    
    /**
     * AJAX para testar uma regra específica
     */
    public function ajax_test_rule() {
        check_ajax_referer('wc_google_feed_ajax', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'wc-google-feed')));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'name');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $wc_category = absint($_POST['wc_category'] ?? 0);
        
        if ($type === 'name' && empty($keyword)) {
            wp_send_json_error(array('message' => __('Palavra-chave não informada.', 'wc-google-feed')));
        }
        
        if ($type === 'category' && empty($wc_category)) {
            wp_send_json_error(array('message' => __('Categoria não selecionada.', 'wc-google-feed')));
        }
        
        // Buscar produtos que correspondem à regra
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        if ($type === 'category') {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $wc_category
                )
            );
        }
        
        $products = get_posts($args);
        $matched = array();
        
        foreach ($products as $product_id) {
            $product_name = get_the_title($product_id);
            
            if ($type === 'name') {
                // Match no início do nome (case insensitive)
                if (stripos($product_name, $keyword) === 0) {
                    $matched[] = $product_name;
                }
            } else {
                // Tipo categoria - todos os produtos da categoria correspondem
                $matched[] = $product_name;
            }
        }
        
        wp_send_json_success(array(
            'count' => count($matched),
            'products' => array_slice($matched, 0, 10)
        ));
    }
    
    /**
     * Aplicar regras a todos os produtos
     */
    private function apply_rules_to_products($rules, $product_ids = null) {
        // Ordenar regras: primeiro por tipo (categoria > nome), depois por especificidade
        usort($rules, function($a, $b) {
            // Regras de categoria têm prioridade
            $type_a = ($a['type'] ?? 'name') === 'category' ? 1 : 0;
            $type_b = ($b['type'] ?? 'name') === 'category' ? 1 : 0;
            
            if ($type_a !== $type_b) {
                return $type_b - $type_a;
            }
            
            // Para regras de nome, mais palavras = mais específico
            $words_a = str_word_count($a['keyword'] ?? '');
            $words_b = str_word_count($b['keyword'] ?? '');
            return $words_b - $words_a;
        });
        
        // Buscar produtos
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        if ($product_ids !== null) {
            $args['post__in'] = (array) $product_ids;
        }
        
        $products = get_posts($args);
        
        $updated = 0;
        $products_updated = array();
        
        foreach ($products as $product_id) {
            $product_name = get_the_title($product_id);
            $product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
            
            foreach ($rules as $rule) {
                $matched = false;
                $type = $rule['type'] ?? 'name';
                
                if ($type === 'category') {
                    // Match por categoria WooCommerce
                    $wc_category = $rule['wc_category'] ?? 0;
                    if ($wc_category && in_array($wc_category, $product_categories)) {
                        $matched = true;
                    }
                } else {
                    // Match por nome (início)
                    $keyword = $rule['keyword'] ?? '';
                    if ($keyword && stripos($product_name, $keyword) === 0) {
                        $matched = true;
                    }
                }
                
                if ($matched) {
                    $changed = false;
                    
                    if (!empty($rule['category'])) {
                        update_post_meta($product_id, '_google_product_category', $rule['category']);
                        $changed = true;
                    }
                    
                    if (!empty($rule['gender'])) {
                        update_post_meta($product_id, '_google_product_gender', $rule['gender']);
                        $changed = true;
                    }
                    
                    if (!empty($rule['age_group'])) {
                        update_post_meta($product_id, '_google_product_age_group', $rule['age_group']);
                        $changed = true;
                    }
                    
                    if ($changed) {
                        $updated++;
                        $products_updated[] = $product_name;
                    }
                    
                    break; // Regra mais específica já aplicada
                }
            }
        }
        
        return array(
            'updated' => $updated,
            'products' => $products_updated
        );
    }
    
    /**
     * Aplicar regras automaticamente em novos produtos
     */
    public function auto_apply_rules_to_product($product_id) {
        $auto_apply = get_option('wc_google_feed_auto_apply_rules', 0);
        
        if (!$auto_apply) {
            return;
        }
        
        $rules = get_option('wc_google_feed_smart_rules', array());
        
        if (empty($rules)) {
            return;
        }
        
        $result = $this->apply_rules_to_products($rules, $product_id);
        
        if ($result['updated'] > 0) {
            $this->save_rules_history($result['updated'], count($rules), 'auto');
        }
    }
    
    /**
     * Salvar histórico de aplicação de regras
     */
    private function save_rules_history($updated, $rules_count, $type = 'manual') {
        $history = get_option('wc_google_feed_rules_history', array());
        
        $history[] = array(
            'timestamp' => time(),
            'updated' => $updated,
            'rules_count' => $rules_count,
            'type' => $type
        );
        
        // Manter apenas os últimos 50 registros
        if (count($history) > 50) {
            $history = array_slice($history, -50);
        }
        
        update_option('wc_google_feed_rules_history', $history);
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
        
        // Submenu Preenchimento Inteligente
        add_submenu_page(
            'wc-google-feed',
            __('Preenchimento Inteligente', 'wc-google-feed'),
            __('Preenchimento Inteligente', 'wc-google-feed'),
            'manage_woocommerce',
            'wc-google-feed-smart-fill',
            array($this, 'smart_fill_page')
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
     * Página de preenchimento inteligente
     */
    public function smart_fill_page() {
        include WC_GOOGLE_FEED_PLUGIN_DIR . 'templates/smart-fill-page.php';
    }
    
    /**
     * Enqueue scripts admin
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        // Verificar se estamos em uma página do plugin
        $is_plugin_page = (
            strpos($hook, 'wc-google-feed') !== false ||
            strpos($hook, 'google-feed') !== false
        );
        
        // CSS e JS para todas as páginas do plugin
        if ($is_plugin_page) {
            // WooCommerce registra select2 como 'selectWoo'
            wp_enqueue_script('selectWoo');
            wp_enqueue_style('select2');
            
            // CSS do plugin
            wp_enqueue_style(
                'wc-google-feed-admin',
                WC_GOOGLE_FEED_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                WC_GOOGLE_FEED_VERSION
            );
        
            // JavaScript do plugin
            wp_enqueue_script(
                'wc-google-feed-admin',
                WC_GOOGLE_FEED_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'selectWoo'),
                WC_GOOGLE_FEED_VERSION,
                true
            );
            
            // Passar variáveis para o JavaScript
            wp_localize_script('wc-google-feed-admin', 'wcGoogleFeedAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_google_feed_ajax'),
                'i18n' => array(
                    'confirmNewToken' => __('Atenção: Gerar um novo token irá invalidar o link atual do feed. Deseja continuar?', 'wc-google-feed'),
                    'searchCategory' => __('Buscar categoria...', 'wc-google-feed'),
                    'select' => __('-- Selecione --', 'wc-google-feed'),
                    'inputTooShort' => __('Digite pelo menos 2 caracteres...', 'wc-google-feed'),
                    'searching' => __('Buscando...', 'wc-google-feed'),
                    'noResults' => __('Nenhum resultado encontrado', 'wc-google-feed'),
                    'rule' => __('Regra', 'wc-google-feed'),
                    'productsMatch' => __('produto(s) correspondem a esta regra', 'wc-google-feed'),
                    'examples' => __('Exemplos:', 'wc-google-feed'),
                    'errorTestRule' => __('Erro ao testar regra.', 'wc-google-feed'),
                    'confirmApplyRules' => __('ATENÇÃO: Esta ação irá sobrescrever os dados de TODOS os produtos que correspondem às regras, incluindo os que já possuem categoria definida.\n\nDeseja continuar?', 'wc-google-feed'),
                    'updatedExamples' => __('Exemplos de produtos atualizados:', 'wc-google-feed'),
                    'errorProcess' => __('Erro ao processar requisição.', 'wc-google-feed'),
                    'copied' => __('Copiado para a área de transferência!', 'wc-google-feed'),
                )
            ));
        }
        
        // Scripts específicos para produtos (edição individual)
        if ($post_type === 'product') {
            wp_enqueue_script('select2');
            wp_enqueue_style('select2');
            
            // Carregar o JS do plugin para a página de produto também
            wp_enqueue_script(
                'wc-google-feed-admin',
                WC_GOOGLE_FEED_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'select2'),
                WC_GOOGLE_FEED_VERSION,
                true
            );
            
            wp_localize_script('wc-google-feed-admin', 'wcGoogleFeedAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_google_feed_ajax'),
                'isProductEdit' => true,
                'i18n' => array(
                    'searchCategory' => __('Buscar categoria...', 'wc-google-feed'),
                )
            ));
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

