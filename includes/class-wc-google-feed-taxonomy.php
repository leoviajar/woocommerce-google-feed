<?php
/**
 * Classe para gerenciar a taxonomia do Google
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Google_Feed_Taxonomy {
    
    /**
     * Obter categorias do Google
     */
    public static function get_categories() {
        // Cache das categorias
        $categories = get_transient('wc_google_feed_categories');
        
        if (false === $categories) {
            $categories = array();
            
            // Ler arquivo de taxonomia
            $taxonomy_file = WC_GOOGLE_FEED_PLUGIN_DIR . 'taxonomy-google.txt';
            
            if (file_exists($taxonomy_file)) {
                $lines = file($taxonomy_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                
                foreach ($lines as $line) {
                    // Formato: "ID - Categoria > Subcategoria"
                    if (preg_match('/^(\d+)\s*-\s*(.+)$/', trim($line), $matches)) {
                        $id = $matches[1];
                        $name = $matches[2];
                        $categories[$id] = $id . ' - ' . $name;
                    }
                }
                
                // Cache por 1 hora
                set_transient('wc_google_feed_categories', $categories, HOUR_IN_SECONDS);
            }
        }
        
        return $categories;
    }
    
    /**
     * Buscar categoria por ID
     */
    public static function get_category_by_id($id) {
        $categories = self::get_categories();
        return isset($categories[$id]) ? $categories[$id] : '';
    }
    
    /**
     * Limpar cache das categorias
     */
    public static function clear_cache() {
        delete_transient('wc_google_feed_categories');
    }
}
