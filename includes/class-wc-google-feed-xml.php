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
     *
     * @param WC_Product $product O objeto do produto.
     * @param string     $attribute_key A chave do atributo (ex: 'pa_tamanho', 'attribute_tamanho').
     * @param string     $attribute_value O valor do atributo (slug ou valor cru).
     * @return string O nome de exibição do atributo ou o valor original como fallback.
     */
    private function get_attribute_pretty_name($product, $attribute_key, $attribute_value) {
        $pretty_name = $attribute_value; // Fallback para o valor cru

        // Log de entrada para depuração
        error_log(sprintf('DEBUG: get_attribute_pretty_name - Key: %s, Value: %s', $attribute_key, $attribute_value));

        // Limpa a chave do atributo para obter o nome da taxonomia
        $taxonomy_name = str_replace('attribute_', '', $attribute_key);

        // Tenta obter o termo da taxonomia global pelo slug
        if (taxonomy_exists($taxonomy_name)) {
            error_log(sprintf('DEBUG: taxonomy_exists(%s) is true.', $taxonomy_name));
            $term = get_term_by('slug', $attribute_value, $taxonomy_name);
            if ($term && !is_wp_error($term)) {
                error_log(sprintf('DEBUG: Found term by slug: %s', $term->name));
                return $term->name;
            } else {
                error_log(sprintf('DEBUG: Term not found by slug for taxonomy %s, value %s. Error: %s', $taxonomy_name, $attribute_value, is_wp_error($term) ? $term->get_error_message() : 'Not found'));
                // Se o slug não funcionar, tenta buscar pelo ID do termo (se o valor for numérico)
                if (is_numeric($attribute_value)) {
                    error_log(sprintf('DEBUG: Value %s is numeric, trying to get term by ID.', $attribute_value));
                    $term = get_term_by('id', (int)$attribute_value, $taxonomy_name);
                    if ($term && !is_wp_error($term)) {
                        error_log(sprintf('DEBUG: Found term by ID: %s', $term->name));
                        return $term->name;
                    } else {
                        error_log(sprintf('DEBUG: Term not found by ID for taxonomy %s, value %s. Error: %s', $taxonomy_name, $attribute_value, is_wp_error($term) ? $term->get_error_message() : 'Not found'));
                    }
                }
                // Tenta buscar pelo nome (para casos onde o valor já é o nome)
                error_log(sprintf('DEBUG: Trying to get term by name for taxonomy %s, value %s.', $taxonomy_name, $attribute_value));
                $term = get_term_by('name', $attribute_value, $taxonomy_name);
                if ($term && !is_wp_error($term)) {
                    error_log(sprintf('DEBUG: Found term by name: %s', $term->name));
                    return $term->name;
                } else {
                    error_log(sprintf('DEBUG: Term not found by name for taxonomy %s, value %s. Error: %s', $taxonomy_name, $attribute_value, is_wp_error($term) ? $term->get_error_message() : 'Not found'));
                }
            }
        } else {
            error_log(sprintf('DEBUG: taxonomy_exists(%s) is false.', $taxonomy_name));
        }

        // Para atributos personalizados (não taxonomias globais)
        // Pega todos os atributos do produto (incluindo os personalizados)
        $product_attributes = $product->get_attributes();
        error_log(sprintf('DEBUG: Product attributes: %s', print_r(array_keys($product_attributes), true)));

        // Verifica se a chave do atributo existe nos atributos do produto
        if (isset($product_attributes[$attribute_key])) {
            error_log(sprintf('DEBUG: Attribute key %s found in product attributes.', $attribute_key));
            $attribute_obj = $product_attributes[$attribute_key];
            
            // Se o atributo é uma taxonomia (pode ser global ou personalizada)
            if ($attribute_obj->is_taxonomy()) {
                error_log(sprintf('DEBUG: Attribute %s is a taxonomy. Name: %s', $attribute_key, $attribute_obj->get_name()));
                $term = get_term_by('slug', $attribute_value, $attribute_obj->get_name());
                if ($term && !is_wp_error($term)) {
                    error_log(sprintf('DEBUG: Found term by slug for product taxonomy: %s', $term->name));
                    return $term->name;
                } else {
                    error_log(sprintf('DEBUG: Term not found by slug for product taxonomy %s, value %s. Error: %s', $attribute_obj->get_name(), $attribute_value, is_wp_error($term) ? $term->get_error_message() : 'Not found'));
                    // Tenta buscar pelo ID do termo para atributos de taxonomia
                    if (is_numeric($attribute_value)) {
                        error_log(sprintf('DEBUG: Value %s is numeric, trying to get term by ID for product taxonomy.', $attribute_value));
                        $term = get_term_by('id', (int)$attribute_value, $attribute_obj->get_name());
                        if ($term && !is_wp_error($term)) {
                            error_log(sprintf('DEBUG: Found term by ID for product taxonomy: %s', $term->name));
                            return $term->name;
                        } else {
                            error_log(sprintf('DEBUG: Term not found by ID for product taxonomy %s, value %s. Error: %s', $attribute_obj->get_name(), $attribute_value, is_wp_error($term) ? $term->get_error_message() : 'Not found'));
                        }
                    }
                    // Tenta buscar pelo nome
                    error_log(sprintf('DEBUG: Trying to get term by name for product taxonomy %s, value %s.', $attribute_obj->get_name(), $attribute_value));
                    $term = get_term_by('name', $attribute_value, $attribute_obj->get_name());
                    if ($term && !is_wp_error($term)) {
                        error_log(sprintf('DEBUG: Found term by name for product taxonomy: %s', $term->name));
                        return $term->name;
                    } else {
                        error_log(sprintf('DEBUG: Term not found by name for product taxonomy %s, value %s. Error: %s', $attribute_obj->get_name(), $attribute_value, is_wp_error($term) ? $term->get_error_message() : 'Not found'));
                    }
                }
            } else {
                // Se for um atributo personalizado (não taxonomia), o valor já é o nome
                // ou o valor cru que deve ser usado diretamente.
                error_log(sprintf('DEBUG: Attribute %s is NOT a taxonomy. Returning original value: %s', $attribute_key, $attribute_value));
                return $attribute_value;
            }
        } else {
            error_log(sprintf('DEBUG: Attribute key %s NOT found in product attributes.', $attribute_key));
        }

        // Se todas as tentativas falharem, retorna o valor original
        error_log(sprintf('DEBUG: All attempts failed for %s. Returning fallback: %s', $attribute_key, $pretty_name));
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
        echo '<link>' . $this->safe_xml_escape(home_url( )) . '</link>' . "\n";
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
        
        $dados = $this->get_product_data($produto);
        
        echo '<item>' . "\n";
        echo '<title>' . $this->safe_xml_escape($dados['titulo']) . '</title>' . "\n";
        echo '<link>' . $this->safe_xml_escape($dados['link']) . '</link>' . "\n";
        echo '<description>' . $this->safe_xml_escape($dados['descricao']) . '</description>' . "\n";
        
        // Lógica para g:id e g:item_group_id
        if ($produto->is_type('variation')) {
            // Se for uma variação, o ID é o da variação e item_group_id é o do produto pai
            echo '<g:id>' . $produto->get_id() . '</g:id>' . "\n";
            echo '<g:item_group_id>' . $produto->get_parent_id() . '</g:item_group_id>' . "\n";
        } else {
            // Se for um produto simples ou o produto pai em modo 'parent_only'
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
        
        if ($dados['stock_quantity']) {
            echo '<g:quantity>' . $dados['stock_quantity'] . '</g:quantity>' . "\n";
        }

        $variation_mode = get_option("wc_google_feed_variation_mode", "parent_only");

        if ($produto->is_type('variation')) {
            $attributes = $produto->get_variation_attributes();

            foreach ($attributes as $key => $value) {
                if (empty($value)) continue;

                $pretty_name = $this->get_attribute_pretty_name($produto, $key, $value);

                if (stripos($key, 'tamanho') !== false) {
                    $dados['size'] = $pretty_name;
                } elseif (stripos($key, 'cor') !== false) {
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

                if (stripos($key, 'tamanho') !== false) {
                    $dados['size'] = $pretty_name;
                } elseif (stripos($key, 'cor') !== false) {
                    $dados['color'] = $pretty_name;
                }
            }
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
        
        if ($dados['imagem_url']) {
            echo '<g:image_link>' . $this->safe_xml_escape($dados['imagem_url']) . '</g:image_link>' . "\n";
        }

        $image_mode = get_option("wc_google_feed_image_mode", "first_image_only");

        if ($image_mode === "all_images") {
            $additional_images = $this->get_product_gallery_image_urls($produto);
            foreach ($additional_images as $additional_image_url) {
                echo '<g:additional_image_link>' . $this->safe_xml_escape($additional_image_url) . '</g:additional_image_link>' . "\n";
            }
        }
        
        echo '</item>' . "\n";
    }
    
    /**
     * Obter dados do produto
     */
    private function get_product_data($produto) {
        $dados = array();
        
        $dados['titulo'] = $produto->get_name() ? $produto->get_name() : '';
        $dados["descricao"] = wp_strip_all_tags($produto->get_description());

        $dados['link'] = get_permalink($produto->get_id());
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
        $dados['sku'] = $produto->get_sku() ? $produto->get_sku() : '';
        $dados['status_estoque'] = $produto->get_stock_status();
        
        $stock_quantity = get_post_meta($produto->get_id(), "woodmart_total_stock_quantity", true);
        $dados['stock_quantity'] = $stock_quantity ? $stock_quantity : '';
        
        $dados['gtin'] = $this->get_product_gtin($produto);
        
        $parent_id = $produto->get_parent_id();
        $search_id = $parent_id ? $parent_id : $produto->get_id();
        
        $dados['google_category'] = get_post_meta($search_id, '_google_product_category', true);
        $dados['gender'] = get_post_meta($search_id, '_google_product_gender', true);
        $dados['age_group'] = get_post_meta($search_id, '_google_product_age_group', true);
        $dados['material'] = get_post_meta($search_id, '_google_product_material', true);
        $dados['pattern'] = get_post_meta($search_id, '_google_product_pattern', true);
        $dados['imagem_url'] = $this->get_product_image_url($produto);
        $dados['categoria_principal'] = $this->get_product_category($produto);
        
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
        if (!empty($categorias)) {
            return $categorias[0]->name;
        }
        return '';
    }

    /**
     * Obter URLs das imagens da galeria do produto
     */
    private function get_product_gallery_image_urls($produto) {
        $image_urls = array();
        $gallery_image_ids = $produto->get_gallery_image_ids();

        // Se for uma variação e não tiver imagens de galeria próprias, tenta pegar do produto pai
        if ($produto->is_type("variation") && empty($gallery_image_ids)) {
            $parent_product = wc_get_product($produto->get_parent_id());
            if ($parent_product) {
                $gallery_image_ids = $parent_product->get_gallery_image_ids();
            }
        }

        if (!empty($gallery_image_ids)) {
            foreach ($gallery_image_ids as $image_id) {
                $image_urls[] = wp_get_attachment_image_url($image_id, "full");
            }
        }
        return $image_urls;
    }
}