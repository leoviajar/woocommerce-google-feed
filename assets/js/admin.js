/**
 * WooCommerce Google Feed - Admin JavaScript
 * 
 * @package WC_Google_Feed
 * @version 1.1.0
 */

(function($) {
    'use strict';

    /**
     * Configurações globais (injetadas via wp_localize_script)
     */
    var wcGoogleFeed = window.wcGoogleFeedAdmin || {};
    
    // Garantir que i18n existe
    wcGoogleFeed.i18n = wcGoogleFeed.i18n || {};

    /**
     * Inicialização
     */
    $(document).ready(function() {
        // Página de configurações
        initSettingsPage();
        
        // Página de produtos (listagem)
        if ($('.wc-google-feed-products-table').length) {
            initProductsPage();
        }
        
        // Página de Smart Fill
        if ($('#smart-fill-form').length) {
            initSmartFillPage();
        }
        
        // Página de edição de produto individual
        if (wcGoogleFeed.isProductEdit && $('#google_product_category').length) {
            initProductEditPage();
        }
    });

    /* ==========================================================================
       Página de Edição de Produto Individual
       ========================================================================== */

    function initProductEditPage() {
        $('#google_product_category').select2({
            placeholder: wcGoogleFeed.i18n.searchCategory || 'Buscar categoria...',
            allowClear: true
        });
    }

    /* ==========================================================================
       Página de Configurações
       ========================================================================== */

    function initSettingsPage() {
        var $generateButton = $('#generate-token');
        
        if (!$generateButton.length) return;
        
        $generateButton.on('click', function() {
            // Confirmar antes de gerar novo token
            if (!confirm(wcGoogleFeed.i18n.confirmNewToken || 'Atenção: Gerar um novo token irá invalidar o link atual do feed. Deseja continuar?')) {
                return;
            }
            
            // Gerar token aleatório mais seguro
            var token = '';
            var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            for (var i = 0; i < 20; i++) {
                token += characters.charAt(Math.floor(Math.random() * characters.length));
            }
            
            // Atualizar o campo de input
            var $tokenInput = $('#security_token_input');
            if ($tokenInput.length) {
                $tokenInput.removeAttr('readonly');
                $tokenInput.val(token);
                $tokenInput.attr('readonly', 'readonly');
            }
            
            // Atualizar a URL exibida na página
            $('a[href*="/feed"]').each(function() {
                var $link = $(this);
                if ($link.attr('href').indexOf('/feed') !== -1) {
                    var baseUrl = $link.attr('href').split('/feed')[0];
                    var newUrl = baseUrl + '/feed-' + token + '.xml';
                    $link.attr('href', newUrl).text(newUrl);
                }
            });
        });
    }

    /* ==========================================================================
       Página de Produtos
       ========================================================================== */

    function initProductsPage() {
        // Inicializar Select2 para categoria do Google (com AJAX)
        $('.google-category-select').select2({
            placeholder: wcGoogleFeed.i18n.searchCategory || 'Buscar categoria...',
            allowClear: true,
            width: '100%',
            minimumInputLength: 2,
            language: getSelect2Language(),
            ajax: {
                url: wcGoogleFeed.ajaxUrl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'wc_google_feed_search_categories',
                        q: params.term,
                        nonce: wcGoogleFeed.nonce
                    };
                },
                processResults: function(data) {
                    return data;
                },
                cache: true
            }
        });
        
        // Inicializar Select2 para selects simples
        $('.google-select').select2({
            placeholder: wcGoogleFeed.i18n.select || '-- Selecione --',
            allowClear: true,
            width: '100%',
            minimumResultsForSearch: -1
        });
        
        // Inicializar Select2 para selects pequenos
        $('.google-select-small').select2({
            placeholder: wcGoogleFeed.i18n.select || '-- Selecione --',
            allowClear: true,
            width: '180px',
            minimumResultsForSearch: -1
        });
        
        // Mostrar tabela com transição
        $('.wc-google-feed-products-table').addClass('loaded');
        $('.google-feed-loading').hide();
    }

    /* ==========================================================================
       Página de Preenchimento Inteligente (Smart Fill)
       ========================================================================== */

    function initSmartFillPage() {
        var ruleIndex = $('.rule-item').length;
        
        // Navegação por tabs
        initTabs();
        
        // Inicializar Select2 nas regras existentes
        initSelect2InContainer($('#rules-container'));
        
        // Mostrar container com transição
        $('#rules-container').addClass('loaded');
        $('.smart-fill-loading').hide();
        
        // Event handlers
        initRuleTypeToggle();
        initAddRule();
        initRemoveRule();
        initTestRule();
        initApplyRules();
        initExportImport();
        
        /**
         * Navegação por tabs
         */
        function initTabs() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').hide();
                $('#tab-' + tab).show();
            });
        }
        
        /**
         * Alternar campos baseado no tipo de regra
         */
        function initRuleTypeToggle() {
            $(document).on('change', '.rule-type-select', function() {
                var $rule = $(this).closest('.rule-item');
                var type = $(this).val();
                
                if (type === 'category') {
                    $rule.find('.rule-name-field').hide();
                    $rule.find('.rule-category-field').show();
                } else {
                    $rule.find('.rule-name-field').show();
                    $rule.find('.rule-category-field').hide();
                }
            });
        }
        
        /**
         * Adicionar nova regra
         */
        function initAddRule() {
            $('#add-rule').on('click', function() {
                var template = $('#rule-template').html();
                template = template.replace(/{{INDEX}}/g, ruleIndex);
                template = template.replace(/{{NUMBER}}/g, ruleIndex + 1);
                
                var $newRule = $(template);
                $('#rules-container').append($newRule);
                
                initSelect2InContainer($newRule);
                ruleIndex++;
                
                updateRuleNumbers();
            });
        }
        
        /**
         * Remover regra
         */
        function initRemoveRule() {
            $(document).on('click', '.rule-remove', function() {
                $(this).closest('.rule-item').remove();
                updateRuleNumbers();
            });
        }
        
        /**
         * Atualizar números das regras
         */
        function updateRuleNumbers() {
            $('.rule-item').each(function(index) {
                $(this).find('.rule-title').text((wcGoogleFeed.i18n.rule || 'Regra') + ' #' + (index + 1));
            });
        }
        
        /**
         * Testar regra
         */
        function initTestRule() {
            $(document).on('click', '.rule-test', function() {
                var $btn = $(this);
                var $rule = $btn.closest('.rule-item');
                var $preview = $rule.find('.rule-preview');
                
                var type = $rule.find('.rule-type-select').val();
                var keyword = $rule.find('.rule-keyword').val();
                var wc_category = $rule.find('.wc-category-select').val();
                
                $btn.prop('disabled', true);
                
                $.ajax({
                    url: wcGoogleFeed.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wc_google_feed_test_rule',
                        nonce: wcGoogleFeed.nonce,
                        type: type,
                        keyword: keyword,
                        wc_category: wc_category
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        
                        if (response.success) {
                            var html = '<span class="preview-count">' + response.data.count + ' ' + (wcGoogleFeed.i18n.productsMatch || 'produto(s) correspondem a esta regra') + '</span>';
                            if (response.data.products && response.data.products.length > 0) {
                                html += '<div class="preview-products">' + (wcGoogleFeed.i18n.examples || 'Exemplos:') + '<ul>';
                                response.data.products.forEach(function(name) {
                                    html += '<li>' + name + '</li>';
                                });
                                html += '</ul></div>';
                            }
                            $preview.html(html).show();
                        } else {
                            $preview.html('<span style="color:#721c24;">' + response.data.message + '</span>').show();
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false);
                        $preview.html('<span style="color:#721c24;">' + (wcGoogleFeed.i18n.errorTestRule || 'Erro ao testar regra.') + '</span>').show();
                    }
                });
            });
        }
        
        /**
         * Aplicar regras
         */
        function initApplyRules() {
            $('#apply-rules').on('click', function() {
                if (!confirm(wcGoogleFeed.i18n.confirmApplyRules || 'ATENÇÃO: Esta ação irá sobrescrever os dados de TODOS os produtos que correspondem às regras, incluindo os que já possuem categoria definida.\n\nDeseja continuar?')) {
                    return;
                }
                
                var $btn = $(this);
                var $result = $('#apply-result');
                
                $btn.prop('disabled', true).find('.dashicons').addClass('spin');
                $result.hide();
                
                $.ajax({
                    url: wcGoogleFeed.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wc_google_feed_apply_rules',
                        nonce: wcGoogleFeed.nonce
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
                        
                        if (response.success) {
                            var html = '<strong>' + response.data.message + '</strong>';
                            if (response.data.products && response.data.products.length > 0) {
                                html += '<br><br>' + (wcGoogleFeed.i18n.updatedExamples || 'Exemplos de produtos atualizados:') + '<ul>';
                                response.data.products.forEach(function(name) {
                                    html += '<li>' + name + '</li>';
                                });
                                html += '</ul>';
                            }
                            $result.removeClass('error').addClass('success').html(html).show();
                        } else {
                            $result.removeClass('success').addClass('error').html(response.data.message).show();
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
                        $result.removeClass('success').addClass('error').html(wcGoogleFeed.i18n.errorProcess || 'Erro ao processar requisição.').show();
                    }
                });
            });
        }
        
        /**
         * Export/Import
         */
        function initExportImport() {
            // Copiar para área de transferência
            $('#copy-export').on('click', function() {
                var $textarea = $('#export-json');
                $textarea.select();
                document.execCommand('copy');
                alert(wcGoogleFeed.i18n.copied || 'Copiado para a área de transferência!');
            });
            
            // Baixar como arquivo
            $('#download-export').on('click', function() {
                var json = $('#export-json').val();
                var blob = new Blob([json], {type: 'application/json'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'google-feed-rules.json';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
        }
    }

    /* ==========================================================================
       Funções Auxiliares
       ========================================================================== */

    /**
     * Inicializar Select2 em um container específico
     */
    function initSelect2InContainer(container) {
        // Categoria do Google (com AJAX)
        container.find('.google-category-select').select2({
            placeholder: wcGoogleFeed.i18n.searchCategory || 'Buscar categoria...',
            allowClear: true,
            width: '100%',
            minimumInputLength: 2,
            language: getSelect2Language(),
            ajax: {
                url: wcGoogleFeed.ajaxUrl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'wc_google_feed_search_categories',
                        q: params.term,
                        nonce: wcGoogleFeed.nonce
                    };
                },
                processResults: function(data) {
                    return data;
                },
                cache: true
            }
        });
        
        // Selects simples (categoria WC)
        container.find('.google-select, .wc-category-select').select2({
            placeholder: wcGoogleFeed.i18n.select || '-- Selecione --',
            allowClear: true,
            width: '100%',
            minimumResultsForSearch: 10
        });
        
        // Selects pequenos (gênero, faixa etária)
        container.find('.google-select-small').select2({
            placeholder: wcGoogleFeed.i18n.select || '-- Selecione --',
            allowClear: true,
            width: '180px',
            minimumResultsForSearch: -1
        });
        
        // Tipo de regra
        container.find('.rule-type-select').select2({
            minimumResultsForSearch: -1,
            width: '250px'
        });
    }

    /**
     * Obter configuração de idioma para Select2
     */
    function getSelect2Language() {
        return {
            inputTooShort: function() {
                return wcGoogleFeed.i18n.inputTooShort || 'Digite pelo menos 2 caracteres...';
            },
            searching: function() {
                return wcGoogleFeed.i18n.searching || 'Buscando...';
            },
            noResults: function() {
                return wcGoogleFeed.i18n.noResults || 'Nenhum resultado encontrado';
            }
        };
    }

})(jQuery);
