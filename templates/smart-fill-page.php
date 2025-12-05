<?php
/**
 * Template da página de preenchimento inteligente
 */

if (!defined('ABSPATH')) {
    exit;
}

// Processar limpeza de histórico
if (isset($_POST['clear_history']) && wp_verify_nonce($_POST['wc_google_feed_smart_fill_nonce'], 'wc_google_feed_smart_fill')) {
    delete_option('wc_google_feed_rules_history');
    echo '<div class="notice notice-success"><p>' . __('Histórico limpo com sucesso!', 'wc-google-feed') . '</p></div>';
}

// Processar importação de regras
if (isset($_POST['import_rules']) && wp_verify_nonce($_POST['wc_google_feed_smart_fill_nonce'], 'wc_google_feed_smart_fill')) {
    $import_json = sanitize_textarea_field($_POST['import_json']);
    $imported_rules = json_decode(stripslashes($import_json), true);
    
    if (is_array($imported_rules)) {
        update_option('wc_google_feed_smart_rules', $imported_rules);
        echo '<div class="notice notice-success"><p>' . __('Regras importadas com sucesso!', 'wc-google-feed') . '</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>' . __('Erro ao importar regras. JSON inválido.', 'wc-google-feed') . '</p></div>';
    }
}

// Processar salvamento de regras
if (isset($_POST['save_rules']) && wp_verify_nonce($_POST['wc_google_feed_smart_fill_nonce'], 'wc_google_feed_smart_fill')) {
    $rules = array();
    
    if (!empty($_POST['rules']) && is_array($_POST['rules'])) {
        foreach ($_POST['rules'] as $rule) {
            if (!empty($rule['keyword']) || !empty($rule['wc_category'])) {
                $rules[] = array(
                    'type' => sanitize_text_field($rule['type'] ?? 'name'),
                    'keyword' => sanitize_text_field($rule['keyword'] ?? ''),
                    'wc_category' => absint($rule['wc_category'] ?? 0),
                    'category' => sanitize_text_field($rule['category'] ?? ''),
                    'gender' => sanitize_text_field($rule['gender'] ?? ''),
                    'age_group' => sanitize_text_field($rule['age_group'] ?? ''),
                );
            }
        }
    }
    
    // Salvar configuração de agendamento
    $auto_apply = isset($_POST['auto_apply_rules']) ? 1 : 0;
    update_option('wc_google_feed_auto_apply_rules', $auto_apply);
    
    update_option('wc_google_feed_smart_rules', $rules);
    echo '<div class="notice notice-success"><p>' . __('Regras salvas com sucesso!', 'wc-google-feed') . '</p></div>';
}

// Carregar regras existentes
$rules = get_option('wc_google_feed_smart_rules', array());
$auto_apply = get_option('wc_google_feed_auto_apply_rules', 0);

// Carregar histórico
$history = get_option('wc_google_feed_rules_history', array());

// Carregar categorias do Google
$google_categories = WC_Google_Feed_Taxonomy::get_categories();

// Carregar categorias do WooCommerce
$wc_categories = get_terms(array(
    'taxonomy' => 'product_cat',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC'
));
?>

<div class="wrap">
    <h1><?php _e('Preenchimento Inteligente', 'wc-google-feed'); ?></h1>
    
    <p class="description">
        <?php _e('Crie regras para preencher automaticamente a categoria, gênero e faixa etária dos produtos.', 'wc-google-feed'); ?>
        <br>
        <?php _e('Você pode criar regras baseadas no <strong>nome do produto</strong> ou na <strong>categoria do WooCommerce</strong>.', 'wc-google-feed'); ?>
    </p>
    
    <!-- Tabs -->
    <h2 class="nav-tab-wrapper">
        <a href="#tab-rules" class="nav-tab nav-tab-active" data-tab="rules"><?php _e('Regras', 'wc-google-feed'); ?></a>
        <a href="#tab-history" class="nav-tab" data-tab="history"><?php _e('Histórico', 'wc-google-feed'); ?></a>
        <a href="#tab-import-export" class="nav-tab" data-tab="import-export"><?php _e('Importar/Exportar', 'wc-google-feed'); ?></a>
    </h2>
    
    <!-- Tab: Regras -->
    <div id="tab-rules" class="tab-content active">
        <form method="post" action="" id="smart-fill-form">
            <?php wp_nonce_field('wc_google_feed_smart_fill', 'wc_google_feed_smart_fill_nonce'); ?>
            
            <!-- Loading indicator -->
            <div class="google-feed-loading smart-fill-loading">
                <span class="spinner is-active"></span>
                <p><?php _e('Carregando regras...', 'wc-google-feed'); ?></p>
            </div>
            
            <div id="rules-container">
                <?php if (empty($rules)): ?>
                    <!-- Template de regra vazia -->
                    <div class="rule-item" data-index="0">
                        <div class="rule-header">
                            <span class="rule-title"><?php _e('Regra', 'wc-google-feed'); ?> #1</span>
                            <div class="rule-actions">
                                <button type="button" class="button button-small rule-test" title="<?php _e('Testar regra', 'wc-google-feed'); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('Testar', 'wc-google-feed'); ?>
                                </button>
                                <button type="button" class="button-link rule-remove" title="<?php _e('Remover regra', 'wc-google-feed'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                        <div class="rule-preview" style="display:none;"></div>
                        <table class="form-table rule-fields">
                            <tr>
                                <th scope="row">
                                    <label><?php _e('Tipo de Regra', 'wc-google-feed'); ?></label>
                                </th>
                                <td>
                                    <select name="rules[0][type]" class="rule-type-select">
                                        <option value="name"><?php _e('Por nome do produto', 'wc-google-feed'); ?></option>
                                        <option value="category"><?php _e('Por categoria WooCommerce', 'wc-google-feed'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr class="rule-name-field">
                                <th scope="row">
                                    <label><?php _e('Nome começa com', 'wc-google-feed'); ?></label>
                                </th>
                                <td>
                                    <input type="text" name="rules[0][keyword]" class="rule-keyword" style="width: 100%; max-width: 400px;" placeholder="<?php _e('Ex: Vestido feminino', 'wc-google-feed'); ?>">
                                    <p class="description"><?php _e('Produtos cujo nome começa com esta palavra-chave.', 'wc-google-feed'); ?></p>
                                </td>
                            </tr>
                            <tr class="rule-category-field" style="display:none;">
                                <th scope="row">
                                    <label><?php _e('Categoria WooCommerce', 'wc-google-feed'); ?></label>
                                </th>
                                <td>
                                    <select name="rules[0][wc_category]" class="wc-category-select" style="width: 100%; max-width: 400px;">
                                        <option value=""><?php _e('-- Selecione --', 'wc-google-feed'); ?></option>
                                        <?php foreach ($wc_categories as $cat): ?>
                                            <option value="<?php echo esc_attr($cat->term_id); ?>">
                                                <?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php _e('Categoria do Google', 'wc-google-feed'); ?></label>
                                </th>
                                <td>
                                    <select name="rules[0][category]" class="google-category-select" style="width: 100%; max-width: 400px;">
                                        <option value=""><?php _e('-- Selecione --', 'wc-google-feed'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php _e('Gênero', 'wc-google-feed'); ?></label>
                                </th>
                                <td>
                                    <select name="rules[0][gender]" class="google-select-small">
                                        <option value=""><?php _e('-- Não alterar --', 'wc-google-feed'); ?></option>
                                        <option value="male"><?php _e('Masculino', 'wc-google-feed'); ?></option>
                                        <option value="female"><?php _e('Feminino', 'wc-google-feed'); ?></option>
                                        <option value="unisex"><?php _e('Unissex', 'wc-google-feed'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php _e('Faixa Etária', 'wc-google-feed'); ?></label>
                                </th>
                                <td>
                                    <select name="rules[0][age_group]" class="google-select-small">
                                        <option value=""><?php _e('-- Não alterar --', 'wc-google-feed'); ?></option>
                                        <option value="newborn"><?php _e('Recém-nascido', 'wc-google-feed'); ?></option>
                                        <option value="infant"><?php _e('Bebê', 'wc-google-feed'); ?></option>
                                        <option value="toddler"><?php _e('Criança pequena', 'wc-google-feed'); ?></option>
                                        <option value="kids"><?php _e('Criança', 'wc-google-feed'); ?></option>
                                        <option value="adult"><?php _e('Adulto', 'wc-google-feed'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php else: ?>
                    <?php foreach ($rules as $index => $rule): ?>
                        <div class="rule-item" data-index="<?php echo $index; ?>">
                            <div class="rule-header">
                                <span class="rule-title"><?php printf(__('Regra #%d', 'wc-google-feed'), $index + 1); ?></span>
                                <div class="rule-actions">
                                    <button type="button" class="button button-small rule-test" title="<?php _e('Testar regra', 'wc-google-feed'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                        <?php _e('Testar', 'wc-google-feed'); ?>
                                    </button>
                                    <button type="button" class="button-link rule-remove" title="<?php _e('Remover regra', 'wc-google-feed'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="rule-preview" style="display:none;"></div>
                            <table class="form-table rule-fields">
                                <tr>
                                    <th scope="row">
                                        <label><?php _e('Tipo de Regra', 'wc-google-feed'); ?></label>
                                    </th>
                                    <td>
                                        <select name="rules[<?php echo $index; ?>][type]" class="rule-type-select">
                                            <option value="name" <?php selected($rule['type'] ?? 'name', 'name'); ?>><?php _e('Por nome do produto', 'wc-google-feed'); ?></option>
                                            <option value="category" <?php selected($rule['type'] ?? 'name', 'category'); ?>><?php _e('Por categoria WooCommerce', 'wc-google-feed'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="rule-name-field" <?php if (($rule['type'] ?? 'name') === 'category') echo 'style="display:none;"'; ?>>
                                    <th scope="row">
                                        <label><?php _e('Nome começa com', 'wc-google-feed'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="rules[<?php echo $index; ?>][keyword]" class="rule-keyword" style="width: 100%; max-width: 400px;" value="<?php echo esc_attr($rule['keyword'] ?? ''); ?>" placeholder="<?php _e('Ex: Vestido feminino', 'wc-google-feed'); ?>">
                                        <p class="description"><?php _e('Produtos cujo nome começa com esta palavra-chave.', 'wc-google-feed'); ?></p>
                                    </td>
                                </tr>
                                <tr class="rule-category-field" <?php if (($rule['type'] ?? 'name') !== 'category') echo 'style="display:none;"'; ?>>
                                    <th scope="row">
                                        <label><?php _e('Categoria WooCommerce', 'wc-google-feed'); ?></label>
                                    </th>
                                    <td>
                                        <select name="rules[<?php echo $index; ?>][wc_category]" class="wc-category-select" style="width: 100%; max-width: 400px;">
                                            <option value=""><?php _e('-- Selecione --', 'wc-google-feed'); ?></option>
                                            <?php foreach ($wc_categories as $cat): ?>
                                                <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($rule['wc_category'] ?? '', $cat->term_id); ?>>
                                                    <?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label><?php _e('Categoria do Google', 'wc-google-feed'); ?></label>
                                    </th>
                                    <td>
                                        <select name="rules[<?php echo $index; ?>][category]" class="google-category-select" style="width: 100%; max-width: 400px;">
                                            <option value=""><?php _e('-- Selecione --', 'wc-google-feed'); ?></option>
                                            <?php if (!empty($rule['category']) && isset($google_categories[$rule['category']])): ?>
                                                <option value="<?php echo esc_attr($rule['category']); ?>" selected>
                                                    <?php echo esc_html($google_categories[$rule['category']]); ?>
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label><?php _e('Gênero', 'wc-google-feed'); ?></label>
                                    </th>
                                    <td>
                                        <select name="rules[<?php echo $index; ?>][gender]" class="google-select-small">
                                            <option value=""><?php _e('-- Não alterar --', 'wc-google-feed'); ?></option>
                                            <option value="male" <?php selected($rule['gender'] ?? '', 'male'); ?>><?php _e('Masculino', 'wc-google-feed'); ?></option>
                                            <option value="female" <?php selected($rule['gender'] ?? '', 'female'); ?>><?php _e('Feminino', 'wc-google-feed'); ?></option>
                                            <option value="unisex" <?php selected($rule['gender'] ?? '', 'unisex'); ?>><?php _e('Unissex', 'wc-google-feed'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label><?php _e('Faixa Etária', 'wc-google-feed'); ?></label>
                                    </th>
                                    <td>
                                        <select name="rules[<?php echo $index; ?>][age_group]" class="google-select-small">
                                            <option value=""><?php _e('-- Não alterar --', 'wc-google-feed'); ?></option>
                                            <option value="newborn" <?php selected($rule['age_group'] ?? '', 'newborn'); ?>><?php _e('Recém-nascido', 'wc-google-feed'); ?></option>
                                            <option value="infant" <?php selected($rule['age_group'] ?? '', 'infant'); ?>><?php _e('Bebê', 'wc-google-feed'); ?></option>
                                            <option value="toddler" <?php selected($rule['age_group'] ?? '', 'toddler'); ?>><?php _e('Criança pequena', 'wc-google-feed'); ?></option>
                                            <option value="kids" <?php selected($rule['age_group'] ?? '', 'kids'); ?>><?php _e('Criança', 'wc-google-feed'); ?></option>
                                            <option value="adult" <?php selected($rule['age_group'] ?? '', 'adult'); ?>><?php _e('Adulto', 'wc-google-feed'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <p>
                <button type="button" id="add-rule" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span>
                    <?php _e('Adicionar Regra', 'wc-google-feed'); ?>
                </button>
            </p>
            
            <hr>
            
            <!-- Agendamento automático -->
            <h3><?php _e('Agendamento Automático', 'wc-google-feed'); ?></h3>
            <p>
                <label>
                    <input type="checkbox" name="auto_apply_rules" value="1" <?php checked($auto_apply, 1); ?>>
                    <?php _e('Aplicar regras automaticamente quando novos produtos forem criados', 'wc-google-feed'); ?>
                </label>
            </p>
            
            <hr>
            
            <p class="submit">
                <input type="submit" name="save_rules" class="button button-primary" value="<?php _e('Salvar Regras', 'wc-google-feed'); ?>">
                <button type="button" id="apply-rules" class="button button-secondary">
                    <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                    <?php _e('Aplicar em Produtos', 'wc-google-feed'); ?>
                </button>
            </p>
        </form>
        
        <div id="apply-result" style="display: none;"></div>
    </div>
    
    <!-- Tab: Histórico -->
    <div id="tab-history" class="tab-content" style="display:none;">
        <h3><?php _e('Histórico de Aplicações', 'wc-google-feed'); ?></h3>
        
        <?php if (empty($history)): ?>
            <p class="description"><?php _e('Nenhuma aplicação de regras registrada ainda.', 'wc-google-feed'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Data/Hora', 'wc-google-feed'); ?></th>
                        <th><?php _e('Produtos Atualizados', 'wc-google-feed'); ?></th>
                        <th><?php _e('Regras Aplicadas', 'wc-google-feed'); ?></th>
                        <th><?php _e('Tipo', 'wc-google-feed'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($history) as $entry): ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n('d/m/Y H:i:s', $entry['timestamp'])); ?></td>
                            <td><strong><?php echo esc_html($entry['updated']); ?></strong></td>
                            <td><?php echo esc_html($entry['rules_count']); ?></td>
                            <td>
                                <?php if (($entry['type'] ?? 'manual') === 'auto'): ?>
                                    <span class="dashicons dashicons-clock" title="<?php _e('Automático', 'wc-google-feed'); ?>"></span>
                                    <?php _e('Automático', 'wc-google-feed'); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-admin-users" title="<?php _e('Manual', 'wc-google-feed'); ?>"></span>
                                    <?php _e('Manual', 'wc-google-feed'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p style="margin-top: 15px;">
                <form method="post" action="" style="display: inline;">
                    <?php wp_nonce_field('wc_google_feed_smart_fill', 'wc_google_feed_smart_fill_nonce'); ?>
                    <input type="hidden" name="clear_history" value="1">
                    <button type="submit" class="button button-secondary" onclick="return confirm('<?php _e('Tem certeza que deseja limpar o histórico?', 'wc-google-feed'); ?>');">
                        <?php _e('Limpar Histórico', 'wc-google-feed'); ?>
                    </button>
                </form>
            </p>
        <?php endif; ?>
    </div>
    
    <!-- Tab: Importar/Exportar -->
    <div id="tab-import-export" class="tab-content" style="display:none;">
        <div class="import-export-container">
            <div class="export-section">
                <h3><?php _e('Exportar Regras', 'wc-google-feed'); ?></h3>
                <p class="description"><?php _e('Copie o JSON abaixo para fazer backup das suas regras ou usar em outra loja.', 'wc-google-feed'); ?></p>
                <textarea id="export-json" class="large-text code" rows="10" readonly><?php echo esc_textarea(json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
                <p>
                    <button type="button" id="copy-export" class="button button-secondary">
                        <span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span>
                        <?php _e('Copiar para Área de Transferência', 'wc-google-feed'); ?>
                    </button>
                    <button type="button" id="download-export" class="button button-secondary">
                        <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                        <?php _e('Baixar como Arquivo', 'wc-google-feed'); ?>
                    </button>
                </p>
            </div>
            
            <hr>
            
            <div class="import-section">
                <h3><?php _e('Importar Regras', 'wc-google-feed'); ?></h3>
                <p class="description"><?php _e('Cole o JSON das regras que deseja importar. ATENÇÃO: Isso substituirá todas as regras existentes!', 'wc-google-feed'); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field('wc_google_feed_smart_fill', 'wc_google_feed_smart_fill_nonce'); ?>
                    <textarea name="import_json" id="import-json" class="large-text code" rows="10" placeholder="<?php _e('Cole o JSON aqui...', 'wc-google-feed'); ?>"></textarea>
                    <p>
                        <input type="submit" name="import_rules" class="button button-primary" value="<?php _e('Importar Regras', 'wc-google-feed'); ?>" onclick="return confirm('<?php _e('ATENÇÃO: Isso substituirá todas as regras existentes. Deseja continuar?', 'wc-google-feed'); ?>');">
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Template para nova regra (usado pelo JavaScript) -->
<script type="text/template" id="rule-template">
    <div class="rule-item" data-index="{{INDEX}}">
        <div class="rule-header">
            <span class="rule-title"><?php _e('Regra', 'wc-google-feed'); ?> #{{NUMBER}}</span>
            <div class="rule-actions">
                <button type="button" class="button button-small rule-test" title="<?php _e('Testar regra', 'wc-google-feed'); ?>">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e('Testar', 'wc-google-feed'); ?>
                </button>
                <button type="button" class="button-link rule-remove" title="<?php _e('Remover regra', 'wc-google-feed'); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
        <div class="rule-preview" style="display:none;"></div>
        <table class="form-table rule-fields">
            <tr>
                <th scope="row">
                    <label><?php _e('Tipo de Regra', 'wc-google-feed'); ?></label>
                </th>
                <td>
                    <select name="rules[{{INDEX}}][type]" class="rule-type-select">
                        <option value="name"><?php _e('Por nome do produto', 'wc-google-feed'); ?></option>
                        <option value="category"><?php _e('Por categoria WooCommerce', 'wc-google-feed'); ?></option>
                    </select>
                </td>
            </tr>
            <tr class="rule-name-field">
                <th scope="row">
                    <label><?php _e('Nome começa com', 'wc-google-feed'); ?></label>
                </th>
                <td>
                    <input type="text" name="rules[{{INDEX}}][keyword]" class="rule-keyword" style="width: 100%; max-width: 400px;" placeholder="<?php _e('Ex: Vestido feminino', 'wc-google-feed'); ?>">
                    <p class="description"><?php _e('Produtos cujo nome começa com esta palavra-chave.', 'wc-google-feed'); ?></p>
                </td>
            </tr>
            <tr class="rule-category-field" style="display:none;">
                <th scope="row">
                    <label><?php _e('Categoria WooCommerce', 'wc-google-feed'); ?></label>
                </th>
                <td>
                    <select name="rules[{{INDEX}}][wc_category]" class="wc-category-select" style="width: 100%; max-width: 400px;">
                        <option value=""><?php _e('-- Selecione --', 'wc-google-feed'); ?></option>
                        <?php foreach ($wc_categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat->term_id); ?>">
                                <?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php _e('Categoria do Google', 'wc-google-feed'); ?></label>
                </th>
                <td>
                    <select name="rules[{{INDEX}}][category]" class="google-category-select" style="width: 100%; max-width: 400px;">
                        <option value=""><?php _e('-- Selecione --', 'wc-google-feed'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php _e('Gênero', 'wc-google-feed'); ?></label>
                </th>
                <td>
                    <select name="rules[{{INDEX}}][gender]" class="google-select-small">
                        <option value=""><?php _e('-- Não alterar --', 'wc-google-feed'); ?></option>
                        <option value="male"><?php _e('Masculino', 'wc-google-feed'); ?></option>
                        <option value="female"><?php _e('Feminino', 'wc-google-feed'); ?></option>
                        <option value="unisex"><?php _e('Unissex', 'wc-google-feed'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label><?php _e('Faixa Etária', 'wc-google-feed'); ?></label>
                </th>
                <td>
                    <select name="rules[{{INDEX}}][age_group]" class="google-select-small">
                        <option value=""><?php _e('-- Não alterar --', 'wc-google-feed'); ?></option>
                        <option value="newborn"><?php _e('Recém-nascido', 'wc-google-feed'); ?></option>
                        <option value="infant"><?php _e('Bebê', 'wc-google-feed'); ?></option>
                        <option value="toddler"><?php _e('Criança pequena', 'wc-google-feed'); ?></option>
                        <option value="kids"><?php _e('Criança', 'wc-google-feed'); ?></option>
                        <option value="adult"><?php _e('Adulto', 'wc-google-feed'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
    </div>
</script>
