<?php
/**
 * Classe para funcionalidades públicas
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Google_Feed_Public {
    
    /**
     * Construtor
     */
    public function __construct() {
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'template_redirect'));
    }
    
    /**
     * Adicionar regras de reescrita
     */
    public function add_rewrite_rules() {
        // Regra para feed com token
        add_rewrite_rule('^feed-([a-zA-Z0-9]+)\.xml$', 'index.php?wc_google_feed=1&feed_token=$matches[1]', 'top');
        
        // Manter a regra antiga para compatibilidade
        add_rewrite_rule('^feed\.xml$', 'index.php?wc_google_feed=1', 'top');
    }
    
    /**
     * Adicionar variáveis de query
     */
    public function add_query_vars($vars) {
        $vars[] = 'wc_google_feed';
        $vars[] = 'feed_token';
        return $vars;
    }
    
    /**
     * Interceptar requisições do feed
     */
    public function template_redirect() {
        if (get_query_var('wc_google_feed')) {
            $requested_token = get_query_var('feed_token');
            $stored_token = get_option('wc_google_feed_security_token', '');
            
            // Se há token armazenado, validar
            if (!empty($stored_token)) {
                if (empty($requested_token) || $requested_token !== $stored_token) {
                    wp_die('Acesso negado', 'Erro 403', array('response' => 403));
                }
            }
            
            $feed = new WC_Google_Feed_XML();
            $feed->generate_feed();
            exit;
        }
    }
}
