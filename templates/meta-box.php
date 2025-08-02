<?php
/**
 * Template do meta box
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<p>
    <label for="google_product_category"><?php _e('Selecione a categoria do Google:', 'wc-google-feed'); ?></label>
    <select name="google_product_category" id="google_product_category" style="width: 100%;">
        <option value=""><?php _e('-- Selecione uma categoria --', 'wc-google-feed'); ?></option>
        <?php foreach ($google_categories as $id => $name): ?>
            <option value="<?php echo esc_attr($id); ?>" <?php selected($current_category, $id); ?>>
                <?php echo esc_html($name); ?>
            </option>
        <?php endforeach; ?>
    </select>
</p>

<p>
    <label for="google_product_gender"><?php _e('Gênero:', 'wc-google-feed'); ?></label>
    <select name="google_product_gender" id="google_product_gender" style="width: 100%;">
        <option value=""><?php _e('-- Selecione o gênero --', 'wc-google-feed'); ?></option>
        <option value="male" <?php selected($current_gender, 'male'); ?>><?php _e('Masculino', 'wc-google-feed'); ?></option>
        <option value="female" <?php selected($current_gender, 'female'); ?>><?php _e('Feminino', 'wc-google-feed'); ?></option>
        <option value="unisex" <?php selected($current_gender, 'unisex'); ?>><?php _e('Unissex', 'wc-google-feed'); ?></option>
    </select>
</p>

<p>
    <label for="google_product_age_group"><?php _e('Faixa etária:', 'wc-google-feed'); ?></label>
    <select name="google_product_age_group" id="google_product_age_group" style="width: 100%;">
        <option value=""><?php _e('-- Selecione a faixa etária --', 'wc-google-feed'); ?></option>
        <option value="newborn" <?php selected($current_age_group, 'newborn'); ?>><?php _e('Recém-nascido (0-3 meses)', 'wc-google-feed'); ?></option>
        <option value="infant" <?php selected($current_age_group, 'infant'); ?>><?php _e('Bebê (3-12 meses)', 'wc-google-feed'); ?></option>
        <option value="toddler" <?php selected($current_age_group, 'toddler'); ?>><?php _e('Criança pequena (1-5 anos)', 'wc-google-feed'); ?></option>
        <option value="kids" <?php selected($current_age_group, 'kids'); ?>><?php _e('Criança (5-13 anos)', 'wc-google-feed'); ?></option>
        <option value="adult" <?php selected($current_age_group, 'adult'); ?>><?php _e('Adulto (13+ anos)', 'wc-google-feed'); ?></option>
    </select>
</p>

<p>
    <label for="google_product_material"><?php _e('Material:', 'wc-google-feed'); ?></label>
    <input type="text" id="google_product_material" name="google_product_material" value="<?php echo esc_attr($current_material); ?>" style="width: 100%;">
</p>

<p>
    <label for="google_product_pattern"><?php _e('Pattern:', 'wc-google-feed'); ?></label>
    <input type="text" id="google_product_pattern" name="google_product_pattern" value="<?php echo esc_attr($current_pattern); ?>" style="width: 100%;">
</p>

<p class="description">
    <?php _e('Esta categoria será incluída no feed XML como g:google_product_category', 'wc-google-feed'); ?>
</p>

