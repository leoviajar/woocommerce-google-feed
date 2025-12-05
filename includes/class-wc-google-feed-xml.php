<?php
/**
 * Classe para gerar o feed XML
 */

if (!defined("ABSPATH")) {
    exit;
}

class WC_Google_Feed_XML {
    
    /**
     * Escape seguro para XML
     */
    private function safe_xml_escape($value) {
        if (empty($value)) {
            return "";
        }
        
        // Remover caracteres de controle inválidos em XML 1.0
        $value = preg_replace("/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/", "", $value);
        
        // Escape de entidades XML
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, "UTF-8");
    }

    /**
     * Obtém o nome de exibição de um atributo de produto.
     */
    private function get_attribute_pretty_name($product, $attribute_key, $attribute_value) {
        $pretty_name = $attribute_value;

        $taxonomy_name = str_replace('attribute_', '', $attribute_key);

        if (taxonomy_exists($taxonomy_name)) {
            $term = get_term_by('slug', $attribute_value, $taxonomy_name);
            if ($term && !is_wp_error($term)) {
                return $term->name;
            }
            
            if (is_numeric($attribute_value)) {
                $term = get_term_by('id', (int)$attribute_value, $taxonomy_name);
                if ($term && !is_wp_error($term)) {
                    return $term->name;
                }
            }
            
            $term = get_term_by('name', $attribute_value, $taxonomy_name);
            if ($term && !is_wp_error($term)) {
                return $term->name;
            }
        }

        $product_attributes = $product->get_attributes();

        if (isset($product_attributes[$attribute_key])) {
            $attribute_obj = $product_attributes[$attribute_key];
            
            if ($attribute_obj->is_taxonomy()) {
                $term = get_term_by('slug', $attribute_value, $attribute_obj->get_name());
                if ($term && !is_wp_error($term)) {
                    return $term->name;
                }
                
                if (is_numeric($attribute_value)) {
                    $term = get_term_by('id', (int)$attribute_value, $attribute_obj->get_name());
                    if ($term && !is_wp_error($term)) {
                        return $term->name;
                    }
                }
                
                $term = get_term_by('name', $attribute_value, $attribute_obj->get_name());
                if ($term && !is_wp_error($term)) {
                    return $term->name;
                }
            } else {
                return $attribute_value;
            }
        }

        return $pretty_name;
    }

    /**
     * Gerar feed XML
     */
    public function generate_feed() {
        if (!class_exists("WooCommerce")) {
            wp_die("WooCommerce não está ativo");
        }
        
        ob_clean();
        header("Content-Type: application/xml; charset=utf-8");
        
        $produtos = $this->get_products();
        
        $this->output_xml_header();
        
        foreach ($produtos as $produto_post) {
            $this->output_product_item($produto_post);
        }
        
        $this->output_xml_footer();
    }
    
    /**
     * Buscar produtos
     */
    private function get_products() {
        $variation_mode = get_option("wc_google_feed_variation_mode", "parent_only");
        $products = array();
        
        switch ($variation_mode) {
            case "parent_only":
                $args = array(
                    "post_type" => "product",
                    "post_status" => "publish",
                    "posts_per_page" => -1,
                    "meta_query" => array(
                        array(
                            "key" => "_stock_status",
                            "value" => "instock",
                            "compare" => "="
                        )
                    )
                );
                $products = get_posts($args);
                break;
                
            case "first_variation":
                $parent_products = get_posts(array(
                    "post_type" => "product",
                    "post_status" => "publish",
                    "posts_per_page" => -1,
                    "meta_query" => array(
                        array(
                            "key" => "_stock_status",
                            "value" => "instock",
                            "compare" => "="
                        )
                    )
                ));
                
                foreach ($parent_products as $parent) {
                    $product = wc_get_product($parent->ID);
                    if ($product && $product->is_type("variable")) {
                        $variations = $product->get_children();
                        if (!empty($variations)) {
                            $first_variation = get_post($variations[0]);
                            if ($first_variation) {
                                $products[] = $first_variation;
                            }
                        }
                    } else {
                        $products[] = $parent;
                    }
                }
                break;
                
            case "all_variations":
                $parent_products = get_posts(array(
                    "post_type" => "product",
                    "post_status" => "publish",
                    "posts_per_page" => -1,
                    "meta_query" => array(
                        array(
                            "key" => "_stock_status",
                            "value" => "instock",
                            "compare" => "="
                        )
                    )
                ));
                
                foreach ($parent_products as $parent) {
                    $product = wc_get_product($parent->ID);
                    if ($product && $product->is_type("variable")) {
                        $variations = $product->get_children();
                        foreach ($variations as $variation_id) {
                            $variation_post = get_post($variation_id);
                            if ($variation_post) {
                                $products[] = $variation_post;
                            }
                        }
                    } else {
                        $products[] = $parent;
                    }
                }
                break;
        }
        
        return $products;
    }
    
    /**
     * Cabeçalho do XML
     */
    private function output_xml_header() {
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
        echo '<channel>' . "\n";
        echo '<link>' . $this->safe_xml_escape(home_url()) . '</link>' . "\n";
        echo '<title>' . $this->safe_xml_escape(get_bloginfo('name')) . '</title>' . "\n";
        echo '<description>' . $this->safe_xml_escape(get_bloginfo('description')) . '</description>' . "\n";
    }
    
    /**
     * Rodapé do XML
     */
    private function output_xml_footer() {
        echo '</channel>' . "\n";
        echo '</rss>' . "\n";
    }
    
    /**
     * Item do produto no XML
     */
    private function output_product_item($produto_post) {
        $produto = wc_get_product($produto_post->ID);
        
        if (!$produto) {
            return;
        }
        
        // Detectar se é variação
        $is_variation = $produto->is_type('variation');
        $parent_id = $produto->get_parent_id();
        $parent_product = null;
        
        if ($is_variation && $parent_id) {
            $parent_product = wc_get_product($parent_id);
        }
        
        $dados = $this->get_product_data($produto, $parent_product, $is_variation, $parent_id);
        
        // Processar atributos de cor e tamanho ANTES de montar o título
        if ($is_variation) {
            $attributes = $produto->get_variation_attributes();

            foreach ($attributes as $key => $value) {
                if (empty($value)) continue;

                $pretty_name = $this->get_attribute_pretty_name($produto, $key, $value);

                if (stripos($key, 'tamanho') !== false || stripos($key, 'size') !== false) {
                    $dados['size'] = $pretty_name;
                } elseif (stripos($key, 'cor') !== false || stripos($key, 'color') !== false) {
                    $dados['color'] = $pretty_name;
                }
            }
        } elseif ($produto->is_type('variable')) {
            $attributes = $produto->get_attributes();

            foreach ($attributes as $key => $attribute_obj) {
                if (!$attribute_obj->get_variation()) continue;

                $options = $attribute_obj->get_options();
                if (empty($options)) continue;

                $value = $options[0];
                $pretty_name = $this->get_attribute_pretty_name($produto, $key, $value);

                if (stripos($key, 'tamanho') !== false || stripos($key, 'size') !== false) {
                    $dados['size'] = $pretty_name;
                } elseif (stripos($key, 'cor') !== false || stripos($key, 'color') !== false) {
                    $dados['color'] = $pretty_name;
                }
            }
        }
        
        // Montar título com variantes se configurado
        $titulo = $dados['titulo'];
        $title_variants = get_option("wc_google_feed_title_variants", "disabled");
        
        if ($title_variants !== "disabled") {
            $variant_parts = array();
            
            // Adicionar cor se disponível e configurado
            if (in_array($title_variants, array("color_size", "color_only")) && !empty($dados['color'])) {
                $variant_parts[] = $dados['color'];
            }
            
            // Adicionar tamanho se disponível e configurado
            if (in_array($title_variants, array("color_size", "size_only")) && !empty($dados['size'])) {
                $variant_parts[] = $dados['size'];
            }
            
            // Formatar: "Título, Cor - Tamanho" ou "Título, Cor" ou "Título - Tamanho"
            if (!empty($variant_parts)) {
                if (count($variant_parts) === 2) {
                    $titulo .= ', ' . $variant_parts[0] . ' - ' . $variant_parts[1];
                } elseif ($title_variants === "color_only" || ($title_variants === "color_size" && !empty($dados['color']) && empty($dados['size']))) {
                    $titulo .= ', ' . $variant_parts[0];
                } else {
                    $titulo .= ' - ' . $variant_parts[0];
                }
            }
        }
        
        echo '<item>' . "\n";
        echo '<title>' . $this->safe_xml_escape($titulo) . '</title>' . "\n";
        echo '<link>' . $this->safe_xml_escape($dados['link']) . '</link>' . "\n";
        echo '<description>' . $this->safe_xml_escape($dados['descricao']) . '</description>' . "\n";
        
        if ($is_variation) {
            echo '<g:id>' . $produto->get_id() . '</g:id>' . "\n";
            echo '<g:item_group_id>' . $parent_id . '</g:item_group_id>' . "\n";
        } else {
            echo '<g:id>' . $produto->get_id() . '</g:id>' . "\n";
        }
        
        if ($dados['preco_regular']) {
            echo '<g:price>' . $dados['preco_regular'] . ' BRL</g:price>' . "\n";
        }
        
        if (!empty($dados['preco_promocional']) && !empty($dados['preco_regular']) && 
            $dados['preco_promocional'] != $dados['preco_regular']) {
            echo '<g:sale_price>' . $dados['preco_promocional'] . ' BRL</g:sale_price>' . "\n";
        }
        
        echo '<g:availability>' . ($dados['status_estoque'] === 'instock' ? 'in stock' : 'out of stock') . '</g:availability>' . "\n";
        echo '<g:condition>new</g:condition>' . "\n";
        echo '<g:brand>' . $this->safe_xml_escape(get_bloginfo('name')) . '</g:brand>' . "\n";
        
        if (!empty($dados['stock_quantity'])) {
            echo '<g:quantity>' . $dados['stock_quantity'] . '</g:quantity>' . "\n";
        }
        
        if (!empty($dados["color"])) {
            echo "<g:color>" . $this->safe_xml_escape($dados["color"]) . "</g:color>" . "\n";
        }

        if (!empty($dados["size"])) {
            echo "<g:size>" . $this->safe_xml_escape($dados["size"]) . "</g:size>" . "\n";
        }
        
        if (!empty($dados["gtin"])) {
            echo '<g:gtin>' . $this->safe_xml_escape($dados['gtin']) . '</g:gtin>' . "\n";
        }
        
        if (!empty($dados['sku'])) {
            echo '<g:mpn>' . $this->safe_xml_escape($dados['sku']) . '</g:mpn>' . "\n";
        }
        
        if (!empty($dados['google_category'])) {
            echo '<g:google_product_category>' . $this->safe_xml_escape($dados['google_category']) . '</g:google_product_category>' . "\n";
        }
        
        if (!empty($dados['gender'])) {
            echo '<g:gender>' . $this->safe_xml_escape($dados['gender']) . '</g:gender>' . "\n";
        }
        
        if (!empty($dados['age_group'])) {
            echo '<g:age_group>' . $this->safe_xml_escape($dados['age_group']) . '</g:age_group>' . "\n";
        }

        if (!empty($dados['material'])) {
            echo "<g:material>" . $this->safe_xml_escape($dados['material']) . "</g:material>" . "\n";
        }

        if (!empty($dados['pattern'])) {
            echo "<g:pattern>" . $this->safe_xml_escape($dados['pattern']) . "</g:pattern>" . "\n";
        }
        
        if (!empty($dados['categoria_principal'])) {
            echo '<g:product_type>' . $this->safe_xml_escape($dados['categoria_principal']) . '</g:product_type>' . "\n";
        }
        
        if (!empty($dados['imagem_url'])) {
            echo '<g:image_link>' . $this->safe_xml_escape($dados['imagem_url']) . '</g:image_link>' . "\n";
        }

        $image_mode = get_option("wc_google_feed_image_mode", "first_image_only");

        if ($image_mode === "all_images") {
            $additional_images = $this->get_product_gallery_image_urls($produto, $parent_product);
            foreach ($additional_images as $additional_image_url) {
                echo '<g:additional_image_link>' . $this->safe_xml_escape($additional_image_url) . '</g:additional_image_link>' . "\n";
            }
        }
        
        echo '</item>' . "\n";
    }
    
    /**
     * Obter dados do produto
     */
    private function get_product_data($produto, $parent_product, $is_variation, $parent_id) {
        $dados = array();
        
        // ID para buscar metadados (sempre do pai se for variação)
        $search_id = $parent_id ? $parent_id : $produto->get_id();
        
        // Título
        $dados['titulo'] = $produto->get_name() ? $produto->get_name() : '';
        
        // Descrição: SEMPRE pegar do pai se for variação
        if ($is_variation && $parent_product) {
            $dados["descricao"] = wp_strip_all_tags($parent_product->get_description());
        } else {
            $dados["descricao"] = wp_strip_all_tags($produto->get_description());
        }

        // Link
        $dados['link'] = get_permalink($produto->get_id());
        
        // Preços
        $dados['preco'] = $produto->get_price() ? $produto->get_price() : '';
        $dados['preco_regular'] = $produto->get_regular_price() ? $produto->get_regular_price() : '';
        $dados['preco_promocional'] = $produto->get_sale_price() ? $produto->get_sale_price() : '';
        
        if ($produto->is_type('variable')) {
            $prices = $produto->get_variation_prices(true);
            if (!empty($prices['price'])) {
                $dados['preco'] = min($prices['price']);
            }
            if (!empty($prices['regular_price'])) {
                $dados['preco_regular'] = min($prices['regular_price']);
            }
            if (!empty($prices['sale_price'])) {
                $dados['preco_promocional'] = min($prices['sale_price']);
            }
        }
        
        // SKU
        $dados['sku'] = $produto->get_sku() ? $produto->get_sku() : '';
        
        // Status do estoque
        $dados['status_estoque'] = $produto->get_stock_status();
        
        // Quantidade: SEMPRE pegar do pai se for variação
        if ($is_variation && $parent_id) {
            $stock_quantity = get_post_meta($parent_id, "woodmart_total_stock_quantity", true);
        } else {
            $stock_quantity = get_post_meta($produto->get_id(), "woodmart_total_stock_quantity", true);
        }
        $dados['stock_quantity'] = !empty($stock_quantity) ? $stock_quantity : '';
        
        // GTIN: Verificar na variação, se vazio, buscar no pai
        $gtin = $this->get_product_gtin($produto);
        if (empty($gtin) && $parent_product) {
            $gtin = $this->get_product_gtin($parent_product);
        }
        $dados['gtin'] = $gtin;
        
        // Metadados do Google (sempre do pai se for variação)
        $dados['google_category'] = get_post_meta($search_id, '_google_product_category', true);
        $dados['gender'] = get_post_meta($search_id, '_google_product_gender', true);
        $dados['age_group'] = get_post_meta($search_id, '_google_product_age_group', true);
        $dados['material'] = get_post_meta($search_id, '_google_product_material', true);
        $dados['pattern'] = get_post_meta($search_id, '_google_product_pattern', true);
        
        // Imagem
        $dados['imagem_url'] = $this->get_product_image_url($produto);
        
        // Categoria: SEMPRE pegar do pai se for variação
        if ($is_variation && $parent_id) {
            $categorias = wp_get_post_terms($parent_id, 'product_cat');
        } else {
            $categorias = wp_get_post_terms($produto->get_id(), 'product_cat');
        }
        $dados['categoria_principal'] = !empty($categorias) && !is_wp_error($categorias) ? $categorias[0]->name : '';
        
        return $dados;
    }
    
    /**
     * Obter GTIN do produto
     */
    private function get_product_gtin($produto) {
        $gtin = '';
        
        if (method_exists($produto, 'get_gtin')) {
            $gtin = $produto->get_gtin();
        }
        
        if (empty($gtin)) {
            $gtin_fields = array('_global_unique_id', '_gtin', '_upc', '_ean', '_isbn', 'gtin', 'upc', 'ean', 'isbn');
            foreach ($gtin_fields as $field) {
                $gtin = get_post_meta($produto->get_id(), $field, true);
                if (!empty($gtin)) {
                    break;
                }
            }
        }
        
        return $gtin;
    }
    
    /**
     * Obter URL da imagem do produto
     */
    private function get_product_image_url($produto) {
        $imagem_id = $produto->get_image_id();
        if ($imagem_id) {
            return wp_get_attachment_image_url($imagem_id, 'full');
        }
        return '';
    }
    
    /**
     * Obter categoria principal do produto
     */
    private function get_product_category($produto) {
        $categorias = wp_get_post_terms($produto->get_id(), 'product_cat');
        if (!empty($categorias) && !is_wp_error($categorias)) {
            return $categorias[0]->name;
        }
        return '';
    }
    
    /**
     * Obter categoria principal do produto por ID
     */
    private function get_product_category_by_id($product_id) {
        $categorias = wp_get_post_terms($product_id, 'product_cat');
        if (!empty($categorias) && !is_wp_error($categorias)) {
            return $categorias[0]->name;
        }
        return '';
    }

    /**
     * Obter URLs das imagens da galeria do produto
     * Google Shopping aceita no máximo 10 imagens adicionais
     */
    private function get_product_gallery_image_urls($produto, $parent_product = null) {
        $image_urls = array();
        $gallery_image_ids = $produto->get_gallery_image_ids();

        // Se não tiver imagens de galeria próprias, pega do produto pai
        if (empty($gallery_image_ids) && $parent_product) {
            $gallery_image_ids = $parent_product->get_gallery_image_ids();
        }

        if (!empty($gallery_image_ids)) {
            // Limitar a 10 imagens (limite do Google Shopping)
            $gallery_image_ids = array_slice($gallery_image_ids, 0, 10);
            
            foreach ($gallery_image_ids as $image_id) {
                $url = wp_get_attachment_image_url($image_id, "full");
                if ($url) {
                    $image_urls[] = $url;
                }
            }
        }
        return $image_urls;
    }
}
