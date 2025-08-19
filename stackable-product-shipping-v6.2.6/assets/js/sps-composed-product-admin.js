/**
 * Admin JavaScript for Composed Products
 */
(function($) {
    'use strict';
    
    var childRowIndex = 0;
    
    $(document).ready(function() {
        initComposedProductAdmin();
    });
    
    function initComposedProductAdmin() {
        // Initialize child row counter
        childRowIndex = $('#sps-children-container .sps-child-row').length;
        
        // Product type change handler
        $('#sps_product_type').on('change', function() {
            toggleComposedSettings();
        });
        
        // Add child product handler
        $('#sps-add-child').on('click', function(e) {
            e.preventDefault();
            addChildRow();
        });
        
        // Remove child product handler (delegated)
        $(document).on('click', '.sps-remove-child', function(e) {
            e.preventDefault();
            removeChildRow($(this));
        });
        
        // Child product change handler (delegated)
        $(document).on('change', '.sps-child-product, input[name*="[quantity]"]', function() {
            updateDerivedInfo();
        });
        
        // Composition policy change handler
        $('#sps_composition_policy').on('change', function() {
            updateDerivedInfo();
        });
        
        // Initial setup
        toggleComposedSettings();
        updateDerivedInfo();
    }
    
    function toggleComposedSettings() {
        var productType = $('#sps_product_type').val();
        var $composedSettings = $('#sps-composed-settings');
        
        if (productType === 'composed') {
            $composedSettings.show();
            updateDerivedInfo();
        } else {
            $composedSettings.hide();
        }
    }
    
    function addChildRow() {
        var template = $('#sps-child-row-template').html();
        var newRow = template.replace(/{{INDEX}}/g, childRowIndex);
        
        $('#sps-children-container').append(newRow);
        childRowIndex++;
        
        updateDerivedInfo();
    }
    
    function removeChildRow($button) {
        $button.closest('.sps-child-row').remove();
        updateDerivedInfo();
    }
    
    function updateDerivedInfo() {
        var productType = $('#sps_product_type').val();
        if (productType !== 'composed') {
            return;
        }
        
        var children = collectChildrenData();
        var compositionPolicy = $('#sps_composition_policy').val();
        
        if (children.length === 0) {
            $('#sps-derived-dimensions').html('<p><em>' + 'Adicione produtos filhos para ver as dimensões derivadas.' + '</em></p>');
            return;
        }
        
        // Calculate derived dimensions via AJAX
        $.ajax({
            url: spsComposedProduct.ajax_url,
            type: 'POST',
            data: {
                action: 'sps_calculate_derived_dimensions',
                nonce: spsComposedProduct.nonce,
                children: children,
                composition_policy: compositionPolicy
            },
            success: function(response) {
                if (response.success) {
                    displayDerivedInfo(response.data);
                } else {
                    $('#sps-derived-dimensions').html('<p class="error">' + (response.data || 'Erro ao calcular dimensões.') + '</p>');
                }
            },
            error: function() {
                $('#sps-derived-dimensions').html('<p class="error">Erro na comunicação com o servidor.</p>');
            }
        });
    }
    
    function collectChildrenData() {
        var children = [];
        
        $('#sps-children-container .sps-child-row').each(function() {
            var $row = $(this);
            var productId = $row.find('.sps-child-product').val();
            var quantity = $row.find('input[name*="[quantity]"]').val();
            
            if (productId && quantity && quantity > 0) {
                children.push({
                    product_id: parseInt(productId),
                    quantity: parseInt(quantity)
                });
            }
        });
        
        return children;
    }
    
    function displayDerivedInfo(data) {
        var html = '<table class="widefat">';
        html += '<thead><tr><th>Propriedade</th><th>Valor</th></tr></thead>';
        html += '<tbody>';
        
        html += '<tr><td><strong>Peso Total</strong></td><td>' + formatWeight(data.weight) + '</td></tr>';
        html += '<tr><td><strong>Largura</strong></td><td>' + formatDimension(data.width) + '</td></tr>';
        html += '<tr><td><strong>Altura</strong></td><td>' + formatDimension(data.height) + '</td></tr>';
        html += '<tr><td><strong>Comprimento</strong></td><td>' + formatDimension(data.length) + '</td></tr>';
        html += '<tr><td><strong>Volume Total</strong></td><td>' + formatVolume(data.volume) + '</td></tr>';
        
        html += '</tbody></table>';
        
        if (data.children_info && data.children_info.length > 0) {
            html += '<h5>Detalhes dos Produtos Filhos:</h5>';
            html += '<table class="widefat">';
            html += '<thead><tr><th>Produto</th><th>Qtd</th><th>Peso Unit.</th><th>Dimensões</th><th>Volume Unit.</th></tr></thead>';
            html += '<tbody>';
            
            data.children_info.forEach(function(child) {
                html += '<tr>';
                html += '<td>' + escapeHtml(child.name) + '</td>';
                html += '<td>' + child.quantity + '</td>';
                html += '<td>' + formatWeight(child.weight) + '</td>';
                html += '<td>' + formatDimension(child.width) + ' × ' + formatDimension(child.height) + ' × ' + formatDimension(child.length) + '</td>';
                html += '<td>' + formatVolume(child.volume) + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
        }
        
        $('#sps-derived-dimensions').html(html);
    }
    
    function formatWeight(weight) {
        return parseFloat(weight).toFixed(2) + ' kg';
    }
    
    function formatDimension(dimension) {
        return parseFloat(dimension).toFixed(2) + ' cm';
    }
    
    function formatVolume(volume) {
        return parseFloat(volume).toFixed(2) + ' cm³';
    }
    
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
})(jQuery);