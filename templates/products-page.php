<?php
/**
 * Template da página de produtos com thumbnail e paginação
 */

if (!defined('ABSPATH')) {
    exit;
}

// Processar salvamento se houver
if (isset($_POST['save_products']) && wp_verify_nonce($_POST['wc_google_feed_products_nonce'], 'wc_google_feed_products')) {
    $admin = new WC_Google_Feed_Admin();
    $admin->save_products_data();
    echo '<div class="notice notice-success"><p>' . __('Produtos atualizados com sucesso!', 'wc-google-feed') . '</p></div>';
}

// Paginação
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20; // 20 produtos por página
$offset = ($current_page - 1) * $per_page;

// Buscar produtos com paginação, busca e filtro de atributos
$search_query = isset($_GET["s"]) ? sanitize_text_field($_GET["s"]) : "";
$filter_attributes = isset($_GET["filter_attributes"]) ? sanitize_text_field($_GET["filter_attributes"]) : "all";

$args = array(
    "post_type" => "product",
    "post_status" => "publish",
    "posts_per_page" => $per_page,
    "offset" => $offset,
    "orderby" => "title",
    "order" => "ASC",
    "s" => $search_query // Adiciona o termo de busca
);

$total_args = array(
    "post_type" => "product",
    "post_status" => "publish",
    "posts_per_page" => -1,
    "s" => $search_query // Adiciona o termo de busca para a contagem total
);

if ($filter_attributes === "missing_attributes") {
    $meta_query = array(
        "relation" => "OR",
        array(
            "key" => "_google_product_category",
            "compare" => "NOT EXISTS"
        ),
        array(
            "key" => "_google_product_category",
            "value" => "",
            "compare" => "="
        ),
        array(
            "key" => "_google_product_gender",
            "compare" => "NOT EXISTS"
        ),
        array(
            "key" => "_google_product_gender",
            "value" => "",
            "compare" => "="
        ),
        array(
            "key" => "_google_product_age_group",
            "compare" => "NOT EXISTS"
        ),
        array(
            "key" => "_google_product_age_group",
            "value" => "",
            "compare" => "="
        ),
    );
    $args["meta_query"] = $meta_query;
    $total_args["meta_query"] = $meta_query;
}

$produtos = get_posts($args);
$total_produtos = count(get_posts($total_args));
$total_pages = ceil($total_produtos / $per_page);

// Carregar categorias do Google
$google_categories = WC_Google_Feed_Taxonomy::get_categories();
?>

<div class="wrap">
    <h1><?php _e('Configurar Produtos - Google Feed', 'wc-google-feed'); ?></h1>
    
    <form method="get" action="" class="search-form">
        <input type="hidden" name="page" value="wc-google-feed-products">
        <div class="filter-controls" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; margin-top: 20px">
            <div class="search-box" style="display: flex; align-items: center; gap: 5px;">
                <label for="product-search-input"><?php _e("Buscar produto:", "wc-google-feed"); ?></label>
                <input type="search" id="product-search-input" name="s" value="<?php echo isset($_GET["s"]) ? esc_attr($_GET["s"]) : ""; ?>" style="width: 200px;">
                <?php submit_button(__("Buscar Produto", "wc-google-feed"), "button", "", false, array("id" => "search-submit")); ?>
            </div>

            <div class="filter-actions" style="display: flex; align-items: center; gap: 5px;">
                <label for="filter-by-attributes"><?php _e("Filtrar por atributos:", "wc-google-feed"); ?></label>
                <select name="filter_attributes" id="filter-by-attributes" style="width: 200px;">
                    <option value="all" <?php selected(isset($_GET["filter_attributes"]) ? $_GET["filter_attributes"] : "", "all"); ?>><?php _e("Todos os produtos", "wc-google-feed"); ?></option>
                    <option value="missing_attributes" <?php selected(isset($_GET["filter_attributes"]) ? $_GET["filter_attributes"] : "", "missing_attributes"); ?>><?php _e("Produtos sem atributos", "wc-google-feed"); ?></option>
                </select>
                <?php submit_button(__("Filtrar", "wc-google-feed"), "button", "", false, array("id" => "filter-submit")); ?>
            </div>

            <p><?php printf(__('Total de produtos: %d | Página %d de %d', 'wc-google-feed'), $total_produtos, $current_page, $total_pages); ?></p>
        </div>
    </form>
    
    <!-- Loading indicator -->
    <div class="google-feed-loading">
        <span class="spinner is-active"></span>
        <p><?php _e('Carregando produtos...', 'wc-google-feed'); ?></p>
    </div>
    
    <form method="post" action="">
        <?php wp_nonce_field('wc_google_feed_products', 'wc_google_feed_products_nonce'); ?>
        
        <table class="wp-list-table widefat fixed striped wc-google-feed-products-table">
            <thead>
                <tr>
                    <th style="width: 60px;"><?php _e('Imagem', 'wc-google-feed'); ?></th>
                    <th style="width: 25%;"><?php _e('Produto', 'wc-google-feed'); ?></th>
                    <th style="width: 25%;"><?php _e('Categoria do Google', 'wc-google-feed'); ?></th>
                    <th style="width: 20%;"><?php _e('Gênero', 'wc-google-feed'); ?></th>
                    <th style="width: 25%;"><?php _e('Faixa Etária', 'wc-google-feed'); ?></th>
                    <th style="width: 20%;"><?php _e("Material", "wc-google-feed"); ?></th>
                    <th style="width: 20%;"><?php _e("Pattern", "wc-google-feed"); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produtos as $produto_post): 
                    $produto = wc_get_product($produto_post->ID);
                    if (!$produto) continue;
                    
                    $current_category = get_post_meta($produto_post->ID, '_google_product_category', true);
                    $current_gender = get_post_meta($produto_post->ID, '_google_product_gender', true);
                    $current_age_group = get_post_meta($produto_post->ID, '_google_product_age_group', true);
                    
                    // Thumbnail
                    $thumbnail = $produto->get_image('thumbnail');
                    if (empty($thumbnail)) {
                        $thumbnail = '<div style="width:50px;height:50px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:10px;color:#999;">Sem imagem</div>';
                    }
                ?>
                <tr>
                    <td class="thumbnail-cell">
                        <?php echo $thumbnail; ?>
                    </td>
                    <td class="product-info">
                        <strong><?php echo esc_html($produto->get_name()); ?></strong>
                        <small>ID: <?php echo $produto_post->ID; ?> | <?php echo $produto->get_price_html(); ?></small>
                    </td>
                    <td>
                        <select name="products[<?php echo $produto_post->ID; ?>][category]" class="google-category-select" style="width: 100%;">
                            <option value=""><?php _e('-- Selecione --', 'wc-google-feed'); ?></option>
                            <?php if (!empty($current_category) && isset($google_categories[$current_category])): ?>
                                <option value="<?php echo esc_attr($current_category); ?>" selected>
                                    <?php echo esc_html($google_categories[$current_category]); ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </td>
                    <td>
                        <select name="products[<?php echo $produto_post->ID; ?>][gender]" class="google-select" style="width: 100%;">
                            <option value=""><?php _e('-- Selecione --', 'wc-google-feed'); ?></option>
                            <option value="male" <?php selected($current_gender, 'male'); ?>><?php _e('Masculino', 'wc-google-feed'); ?></option>
                            <option value="female" <?php selected($current_gender, 'female'); ?>><?php _e('Feminino', 'wc-google-feed'); ?></option>
                            <option value="unisex" <?php selected($current_gender, 'unisex'); ?>><?php _e('Unissex', 'wc-google-feed'); ?></option>
                        </select>
                    </td>
                    <td>
                        <select name="products[<?php echo $produto_post->ID; ?>][age_group]" class="google-select" style="width: 100%;">
                            <option value=""><?php _e('-- Selecione --', 'wc-google-feed'); ?></option>
                            <option value="newborn" <?php selected($current_age_group, 'newborn'); ?>><?php _e('Recém-nascido', 'wc-google-feed'); ?></option>
                            <option value="infant" <?php selected($current_age_group, 'infant'); ?>><?php _e('Bebê', 'wc-google-feed'); ?></option>
                            <option value="toddler" <?php selected($current_age_group, 'toddler'); ?>><?php _e('Criança pequena', 'wc-google-feed'); ?></option>
                            <option value="kids" <?php selected($current_age_group, 'kids'); ?>><?php _e('Criança', 'wc-google-feed'); ?></option>
                            <option value="adult" <?php selected($current_age_group, 'adult'); ?>><?php _e('Adulto', 'wc-google-feed'); ?></option>
                        </select>
                    </td>
                    <td>
                        <?php $current_material = get_post_meta($produto_post->ID, '_google_product_material', true); ?>
                        <input type="text" name="products[<?php echo $produto_post->ID; ?>][material]" value="<?php echo esc_attr($current_material); ?>" class="google-input" placeholder="<?php _e('Ex: Algodão', 'wc-google-feed'); ?>" style="width: 100%;">
                    </td>
                    <td>
                        <?php $current_pattern = get_post_meta($produto_post->ID, '_google_product_pattern', true); ?>
                        <input type="text" name="products[<?php echo $produto_post->ID; ?>][pattern]" value="<?php echo esc_attr($current_pattern); ?>" class="google-input" placeholder="<?php _e('Ex: Listrado', 'wc-google-feed'); ?>" style="width: 100%;">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p class="submit">
            <input type="submit" name="save_products" class="button-primary" value="<?php _e('Salvar Alterações', 'wc-google-feed'); ?>">
        </p>
    </form>
    
    <!-- Paginação -->
    <?php if ($total_pages > 1): ?>
    <div class="tablenav">
        <div class="tablenav-pages">
            <?php
            $page_links = paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $current_page
            ));
            echo $page_links;
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>
