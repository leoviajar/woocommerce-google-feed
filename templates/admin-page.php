<?php
/**
 * Template da página admin
 */

if (!defined("ABSPATH")) {
    exit;
}

// Processar salvamento das configurações
if (isset($_POST["save_settings"]) && wp_verify_nonce($_POST["wc_google_feed_settings_nonce"], "wc_google_feed_settings")) {
    if (current_user_can("manage_woocommerce")) {
        $variation_mode = sanitize_text_field($_POST["variation_mode"]);
        update_option("wc_google_feed_variation_mode", $variation_mode);

        $image_mode = sanitize_text_field($_POST["image_mode"]);
        update_option("wc_google_feed_image_mode", $image_mode);
        
        // Salvar opção de variantes no título
        $title_variants = sanitize_text_field($_POST["title_variants"]);
        update_option("wc_google_feed_title_variants", $title_variants);
        
        // Salvar token de segurança
        if (isset($_POST["security_token"]) && !empty($_POST["security_token"])) {
            $security_token = sanitize_text_field($_POST["security_token"]);
            update_option("wc_google_feed_security_token", $security_token);
        }
        
        echo "<div class=\"notice notice-success\"><p>" . __("Configurações salvas com sucesso!", "wc-google-feed") . "</p></div>";
    }
}

// Gerar token inicial se não existir
if (empty(get_option("wc_google_feed_security_token"))) {
    $initial_token = wp_generate_password(20, false, false);
    update_option("wc_google_feed_security_token", $initial_token);
}

$admin = new WC_Google_Feed_Admin();
$total_products = wp_count_posts("product")->publish;
$products_with_category = $admin->count_products_with_google_category();

// Contagem nativa de variantes usando funções do WordPress
$total_variations = wp_count_posts("product_variation")->publish;
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card">
        <h2><?php _e("Feed XML de Produtos", "wc-google-feed"); ?></h2>
        <p><?php _e("Seu feed XML está disponível em:", "wc-google-feed"); ?></p>
        <?php 
        $token = get_option("wc_google_feed_security_token", "");
        $feed_url = $token ? home_url("/feed-" . $token . ".xml") : home_url("/feed.xml");
        ?>
        <p><strong><a href="<?php echo $feed_url; ?>" target="_blank"><?php echo $feed_url; ?></a></strong></p>
        
        <h3><?php _e("Estatísticas", "wc-google-feed"); ?></h3>
        <p><?php printf(__("Total de produtos: %d", "wc-google-feed"), $total_products); ?></p>
        <p><?php printf(__("Total de variantes: %d", "wc-google-feed"), $total_variations); ?></p>
        <p><?php printf(__("Produtos com categoria do Google: %d", "wc-google-feed"), $products_with_category); ?></p>
        <p><?php printf(__("Produtos sem categoria: %d", "wc-google-feed"), $total_products - $products_with_category); ?></p>
        
        <h3><?php _e("Configurações do Feed", "wc-google-feed"); ?></h3>
        <form method="post" action="">
            <?php wp_nonce_field("wc_google_feed_settings", "wc_google_feed_settings_nonce"); ?>
            
            <div>
                <div>
                    <h4><?php _e("Exibição de Variações", "wc-google-feed"); ?></h4>
                    <?php $variation_mode = get_option("wc_google_feed_variation_mode", "parent_only"); ?>
                    <select name="variation_mode">
                        <option value="parent_only" <?php selected($variation_mode, "parent_only"); ?>><?php _e("Uma variante", "wc-google-feed"); ?></option>
                        <option value="all_variations" <?php selected($variation_mode, "all_variations"); ?>><?php _e("Todas as variantes", "wc-google-feed"); ?></option>
                    </select>
                    <p class="description" style="margin-top: 5px;"><?php _e("Escolha como as variações de produtos devem aparecer no feed XML.", "wc-google-feed"); ?></p>
                </div>

                <div style="margin-bottom: 20px;">
                    <h4><?php _e("Exibição de Imagens", "wc-google-feed"); ?></h4>
                    <?php $image_mode = get_option("wc_google_feed_image_mode", "first_image_only"); ?>
                    <select name="image_mode">
                        <option value="first_image_only" <?php selected($image_mode, "first_image_only"); ?>><?php _e("Apenas a primeira imagem", "wc-google-feed"); ?></option>
                        <option value="all_images" <?php selected($image_mode, "all_images"); ?>><?php _e("Todas as imagens", "wc-google-feed"); ?></option>
                    </select>
                    <p class="description" style="margin-top: 5px;"><?php _e("Escolha se o feed deve incluir apenas a primeira imagem do produto ou todas as imagens da galeria.", "wc-google-feed"); ?></p>
                </div>

                <div style="margin-bottom: 20px;">
                    <h4><?php _e("Variantes no Título", "wc-google-feed"); ?></h4>
                    <?php $title_variants = get_option("wc_google_feed_title_variants", "disabled"); ?>
                    <select name="title_variants">
                        <option value="disabled" <?php selected($title_variants, "disabled"); ?>><?php _e("Desabilitado", "wc-google-feed"); ?></option>
                        <option value="color_size" <?php selected($title_variants, "color_size"); ?>><?php _e("Adicionar Cor e Tamanho", "wc-google-feed"); ?></option>
                        <option value="color_only" <?php selected($title_variants, "color_only"); ?>><?php _e("Apenas Cor", "wc-google-feed"); ?></option>
                        <option value="size_only" <?php selected($title_variants, "size_only"); ?>><?php _e("Apenas Tamanho", "wc-google-feed"); ?></option>
                    </select>
                    <p class="description" style="margin-top: 5px;"><?php _e("Adiciona cor e/ou tamanho ao título do produto. Ex: 'Camiseta Básica, Preto - G'. Recomendado pelo Google para variações.", "wc-google-feed"); ?></p>
                </div>

                <div style="margin-bottom: 20px;">
                    <h4><?php _e("Token de Segurança", "wc-google-feed"); ?></h4>
                    <?php $current_token = get_option("wc_google_feed_security_token", ""); ?>
                    <div style="display: flex; align-items: center;">
                        <input type="text" name="security_token" id="security_token_input" value="<?php echo esc_attr($current_token); ?>" readonly style="width: 300px; margin-right: 10px; background: #f0f0f0; cursor: not-allowed;">
                        <button type="button" id="generate-token" class="button"><?php _e("Gerar Novo Token", "wc-google-feed"); ?></button>
                    </div>
                    <p class="description" style="margin-top: 5px;"><?php _e("Token de segurança gerado automaticamente. Clique em 'Gerar Novo Token' apenas se precisar invalidar o link atual.", "wc-google-feed"); ?></p>
                </div>
            </div>
            
            <p class="submit">
                <input type="submit" name="save_settings" class="button-primary" value="<?php _e("Salvar Configurações", "wc-google-feed"); ?>">
            </p>
        </form>
    </div>
</div>
