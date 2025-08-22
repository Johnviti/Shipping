jQuery(document).ready(function($) {
    'use strict';
    
    // Elementos
    const $dimensionInputs = $('#cdp_custom_width, #cdp_custom_height, #cdp_custom_length');
    const $priceDisplay = $('#cdp-calculated-price');
    const $errorMessage = $('#cdp-error-message');
    const $dimensionSelector = $('.cdp-dimension-selector');
    const $addToCartButton = $('.single_add_to_cart_button');
    
    // Dados do produto
    const productId = $('#cdp-product-id').val();
    const basePrice = parseFloat($('#cdp-base-price').val());
    const pricePerCm = parseFloat($('#cdp-price-per-cm').val());
    const baseWidth = parseFloat($('#cdp-base-width').val());
    const baseHeight = parseFloat($('#cdp-base-height').val());
    const baseLength = parseFloat($('#cdp-base-length').val());
    
    // Dados dos pacotes (se disponíveis)
    let packagesData = [];
    if (typeof cdp_packages_data !== 'undefined') {
        packagesData = cdp_packages_data;
    }
    
    // Estado das dimensões
    let currentDimensions = {
        width: baseWidth,
        height: baseHeight,
        length: baseLength,
        confirmed: false
    };
    
    // Debounce para otimizar performance
    let calculateTimeout;
    
    // Inicializar interface
    initializeDimensionInterface();
    
    /**
     * Inicializar interface de dimensões
     */
    function initializeDimensionInterface() {
        if ($dimensionSelector.length === 0) return;
        
        // Adicionar botão de confirmação
        addConfirmationButton();
        
        // Event listeners
        $dimensionInputs.on('input change', onDimensionChange);
        
        // Interceptar submit do formulário
        $('form.cart').on('submit', onFormSubmit);
        
        // Calcular preço inicial
        calculatePrice();
    }
    
    /**
     * Adicionar botão de confirmação
     */
    function addConfirmationButton() {
        const confirmButton = `
            <div class="cdp-confirmation-section">
                <button type="button" id="cdp-confirm-dimensions" class="button alt" disabled>
                    Confirmar Dimensões
                </button>
                <div class="cdp-confirmation-status" id="cdp-confirmation-status">
                    <span class="cdp-status-icon">⚠️</span>
                    <span class="cdp-status-text">Ajuste as dimensões e confirme para adicionar ao carrinho</span>
                </div>
            </div>
        `;
        
        $dimensionSelector.after(confirmButton);
        
        // Event listener para confirmação
        $('#cdp-confirm-dimensions').on('click', confirmDimensions);
        
        // Desabilitar botão de adicionar ao carrinho inicialmente
        updateAddToCartButton(false);
    }
    
    /**
     * Quando dimensões mudam
     */
    function onDimensionChange() {
        currentDimensions.confirmed = false;
        updateConfirmationStatus();
        
        clearTimeout(calculateTimeout);
        calculateTimeout = setTimeout(function() {
            calculatePrice();
            updatePackagesPreview();
        }, 300);
    }
    
    /**
     * Confirmar dimensões
     */
    function confirmDimensions() {
        if (!validateDimensions()) {
            return;
        }
        
        currentDimensions.width = parseFloat($('#cdp_custom_width').val());
        currentDimensions.height = parseFloat($('#cdp_custom_height').val());
        currentDimensions.length = parseFloat($('#cdp_custom_length').val());
        currentDimensions.confirmed = true;
        
        updateConfirmationStatus();
        updateAddToCartButton(true);
        
        // Mostrar resumo das dimensões confirmadas
        showDimensionSummary();
    }
    
    /**
     * Atualizar status de confirmação
     */
    function updateConfirmationStatus() {
        const $confirmButton = $('#cdp-confirm-dimensions');
        const $status = $('#cdp-confirmation-status');
        
        if (currentDimensions.confirmed) {
            $confirmButton.prop('disabled', true).text('Dimensões Confirmadas ✓');
            $status.html(`
                <span class="cdp-status-icon">✅</span>
                <span class="cdp-status-text">Dimensões confirmadas! Você pode adicionar ao carrinho.</span>
            `);
            $status.addClass('confirmed');
        } else {
            const isValid = validateDimensions(false);
            $confirmButton.prop('disabled', !isValid);
            
            if (isValid) {
                $confirmButton.text('Confirmar Dimensões');
                $status.html(`
                    <span class="cdp-status-icon">⚠️</span>
                    <span class="cdp-status-text">Clique em "Confirmar Dimensões" para prosseguir</span>
                `);
            } else {
                $confirmButton.text('Corrigir Dimensões');
                $status.html(`
                    <span class="cdp-status-icon">❌</span>
                    <span class="cdp-status-text">Corrija as dimensões antes de confirmar</span>
                `);
            }
            $status.removeClass('confirmed');
        }
    }
    
    /**
     * Mostrar resumo das dimensões
     */
    function showDimensionSummary() {
        const summary = `
            <div class="cdp-dimension-summary">
                <h4>Dimensões Confirmadas:</h4>
                <ul>
                    <li>Largura: ${currentDimensions.width} cm</li>
                    <li>Altura: ${currentDimensions.height} cm</li>
                    <li>Comprimento: ${currentDimensions.length} cm</li>
                </ul>
                <button type="button" id="cdp-edit-dimensions" class="button">Editar Dimensões</button>
            </div>
        `;
        
        $('.cdp-confirmation-section').after(summary);
        
        // Event listener para editar
        $('#cdp-edit-dimensions').on('click', function() {
            currentDimensions.confirmed = false;
            $('.cdp-dimension-summary').remove();
            updateConfirmationStatus();
            updateAddToCartButton(false);
        });
    }
    
    /**
     * Atualizar botão de adicionar ao carrinho
     */
    function updateAddToCartButton(enabled) {
        if (enabled) {
            $addToCartButton.prop('disabled', false)
                .removeClass('disabled')
                .text('Adicionar ao carrinho');
        } else {
            $addToCartButton.prop('disabled', true)
                .addClass('disabled')
                .text('Confirme as dimensões primeiro');
        }
    }
    
    /**
     * Interceptar submit do formulário
     */
    function onFormSubmit(e) {
        if ($dimensionSelector.length === 0) return;
        
        if (!currentDimensions.confirmed) {
            e.preventDefault();
            showError('Por favor, confirme as dimensões antes de adicionar ao carrinho.');
            return false;
        }
        
        // Adicionar campos hidden com as dimensões confirmadas
        addDimensionFieldsToForm($(this));
    }
    
    /**
     * Adicionar campos de dimensões ao formulário
     */
    function addDimensionFieldsToForm($form) {
        // Remover campos existentes
        $form.find('input[name^="cdp_custom_"]').remove();
        
        // Adicionar campos com dimensões confirmadas
        $form.append(`<input type="hidden" name="cdp_custom_width" value="${currentDimensions.width}">`);
        $form.append(`<input type="hidden" name="cdp_custom_height" value="${currentDimensions.height}">`);
        $form.append(`<input type="hidden" name="cdp_custom_length" value="${currentDimensions.length}">`);
        $form.append(`<input type="hidden" name="cdp_dimensions_confirmed" value="1">`);
        
        console.log('CDP: Dimensões confirmadas adicionadas ao formulário:', currentDimensions);
    }
    
    /**
     * Calcular preço com base nas dimensões
     */
    function calculatePrice() {
        const width = parseFloat($('#cdp_custom_width').val()) || 0;
        const height = parseFloat($('#cdp_custom_height').val()) || 0;
        const length = parseFloat($('#cdp_custom_length').val()) || 0;
        
        // Validar dimensões
        if (!validateDimensions(false)) {
            return;
        }
        
        // Mostrar loading
        showLoading(true);
        hideError();
        
        // Calcular localmente primeiro
        const localPrice = calculateLocalPrice(width, height, length);
        updatePriceDisplay(localPrice);
        
        // Verificar via AJAX
        $.ajax({
            url: cdp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cdp_calculate_price',
                nonce: cdp_ajax.nonce,
                product_id: productId,
                width: width,
                height: height,
                length: length
            },
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    updatePriceDisplay(response.data.price);
                    updateConfirmationStatus();
                } else {
                    showError(response.data || cdp_ajax.messages.error);
                }
            },
            error: function() {
                showLoading(false);
                showError(cdp_ajax.messages.error);
            }
        });
    }
    
    /**
     * Calcular preço localmente
     */
    function calculateLocalPrice(width, height, length) {
        // Cálculo baseado em fator de volume
        const volumeOriginal = baseWidth * baseHeight * baseLength;
        const volumeNovo = width * height * length;
        
        // Evitar divisão por zero
        if (volumeOriginal <= 0) {
            return basePrice;
        }
        
        const fator = volumeNovo / volumeOriginal;
        const precoNovo = basePrice * fator;
        
        return precoNovo;
    }
    
    /**
     * Validar dimensões
     */
    function validateDimensions(showErrors = true) {
        const width = parseFloat($('#cdp_custom_width').val()) || 0;
        const height = parseFloat($('#cdp_custom_height').val()) || 0;
        const length = parseFloat($('#cdp_custom_length').val()) || 0;
        
        const $widthInput = $('#cdp_custom_width');
        const $heightInput = $('#cdp_custom_height');
        const $lengthInput = $('#cdp_custom_length');
        
        let isValid = true;
        let errorMessage = '';
        
        // Validar largura
        if (width < parseFloat($widthInput.attr('min')) || width > parseFloat($widthInput.attr('max'))) {
            isValid = false;
            errorMessage = 'Largura fora dos limites permitidos.';
            $widthInput.addClass('error');
        } else {
            $widthInput.removeClass('error');
        }
        
        // Validar altura
        if (height < parseFloat($heightInput.attr('min')) || height > parseFloat($heightInput.attr('max'))) {
            isValid = false;
            errorMessage = 'Altura fora dos limites permitidos.';
            $heightInput.addClass('error');
        } else {
            $heightInput.removeClass('error');
        }
        
        // Validar comprimento
        if (length < parseFloat($lengthInput.attr('min')) || length > parseFloat($lengthInput.attr('max'))) {
            isValid = false;
            errorMessage = 'Comprimento fora dos limites permitidos.';
            $lengthInput.addClass('error');
        } else {
            $lengthInput.removeClass('error');
        }
        
        if (!isValid && showErrors) {
            showError(errorMessage);
        } else if (isValid) {
            hideError();
        }
        
        return isValid;
    }
    
    /**
     * Atualizar exibição do preço
     */
    function updatePriceDisplay(price) {
        const formattedPrice = new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(price);
        
        $priceDisplay.html(formattedPrice);
        
        // Atualizar preço principal do WooCommerce
        $('.woocommerce-Price-amount').first().html(formattedPrice);
    }
    
    /**
     * Mostrar/ocultar loading
     */
    function showLoading(show) {
        if (show) {
            $dimensionSelector.addClass('cdp-loading');
            $priceDisplay.html(cdp_ajax.messages.calculating);
        } else {
            $dimensionSelector.removeClass('cdp-loading');
        }
    }
    
    /**
     * Mostrar erro
     */
    function showError(message) {
        $errorMessage.html(message).show();
    }
    
    /**
     * Ocultar erro
     */
    function hideError() {
        $errorMessage.hide();
    }
    
    /**
     * Atualizar prévia dos pacotes escalados
     */
    function updatePackagesPreview() {
        if (packagesData.length === 0) return;
        
        const width = parseFloat($('#cdp_custom_width').val()) || baseWidth;
        const height = parseFloat($('#cdp_custom_height').val()) || baseHeight;
        const length = parseFloat($('#cdp_custom_length').val()) || baseLength;
        
        // Calcular escalas
        const scales = calculateDimensionScales(width, height, length);
        
        // Aplicar escalas aos pacotes
        const scaledPackages = applyScalesToPackages(packagesData, scales);
        
        // Exibir prévia
        displayPackagesPreview(scaledPackages, scales);
    }
    
    /**
     * Calcular escalas das dimensões
     */
    function calculateDimensionScales(customWidth, customHeight, customLength) {
        return {
            w: baseWidth > 0 ? customWidth / baseWidth : 1,
            h: baseHeight > 0 ? customHeight / baseHeight : 1,
            l: baseLength > 0 ? customLength / baseLength : 1
        };
    }
    
    /**
     * Aplicar escalas aos pacotes
     */
    function applyScalesToPackages(packages, scales) {
        return packages.map(function(pkg) {
            const scaledPkg = {
                ...pkg,
                original: {
                    width: pkg.width,
                    height: pkg.height,
                    length: pkg.length,
                    weight: pkg.weight
                },
                scaled: {
                    width: Math.round(pkg.width * scales.w * 100) / 100,
                    height: Math.round(pkg.height * scales.h * 100) / 100,
                    length: Math.round(pkg.length * scales.l * 100) / 100,
                    weight: Math.round(pkg.weight * scales.w * scales.h * scales.l * 1000) / 1000
                },
                clamped: {
                    width: false,
                    height: false,
                    length: false
                }
            };
            
            // Aplicar clamps se houver limites definidos
            if (pkg.min_width && scaledPkg.scaled.width < pkg.min_width) {
                scaledPkg.scaled.width = pkg.min_width;
                scaledPkg.clamped.width = true;
            }
            if (pkg.max_width && scaledPkg.scaled.width > pkg.max_width) {
                scaledPkg.scaled.width = pkg.max_width;
                scaledPkg.clamped.width = true;
            }
            
            if (pkg.min_height && scaledPkg.scaled.height < pkg.min_height) {
                scaledPkg.scaled.height = pkg.min_height;
                scaledPkg.clamped.height = true;
            }
            if (pkg.max_height && scaledPkg.scaled.height > pkg.max_height) {
                scaledPkg.scaled.height = pkg.max_height;
                scaledPkg.clamped.height = true;
            }
            
            if (pkg.min_length && scaledPkg.scaled.length < pkg.min_length) {
                scaledPkg.scaled.length = pkg.min_length;
                scaledPkg.clamped.length = true;
            }
            if (pkg.max_length && scaledPkg.scaled.length > pkg.max_length) {
                scaledPkg.scaled.length = pkg.max_length;
                scaledPkg.clamped.length = true;
            }
            
            return scaledPkg;
        });
    }
    
    /**
     * Exibir prévia dos pacotes
     */
    function displayPackagesPreview(scaledPackages, scales) {
        let $previewContainer = $('#cdp-packages-preview');
        
        if ($previewContainer.length === 0) {
            $previewContainer = $('<div id="cdp-packages-preview" class="cdp-packages-preview"></div>');
            $dimensionSelector.after($previewContainer);
        }
        
        if (scaledPackages.length === 0) {
            $previewContainer.hide();
            return;
        }
        
        let html = '<h4>Prévia dos Pacotes Escalados:</h4>';
        html += '<div class="cdp-scales-info">';
        html += `<p><strong>Escalas aplicadas:</strong> Largura: ${scales.w.toFixed(2)}x, Altura: ${scales.h.toFixed(2)}x, Comprimento: ${scales.l.toFixed(2)}x</p>`;
        html += '</div>';
        
        html += '<div class="cdp-packages-list">';
        
        scaledPackages.forEach(function(pkg, index) {
            html += `<div class="cdp-package-item">`;
            html += `<h5>${pkg.name || 'Pacote ' + (index + 1)}</h5>`;
            html += '<div class="cdp-package-dimensions">';
            
            // Largura
            html += '<div class="cdp-dimension-row">';
            html += `<span class="cdp-dimension-label">Largura:</span>`;
            html += `<span class="cdp-dimension-original">${pkg.original.width} cm</span>`;
            html += `<span class="cdp-dimension-arrow">→</span>`;
            html += `<span class="cdp-dimension-scaled ${pkg.clamped.width ? 'clamped' : ''}">${pkg.scaled.width} cm</span>`;
            if (pkg.clamped.width) {
                html += `<span class="cdp-clamp-indicator" title="Valor limitado pelos mínimos/máximos">⚠️</span>`;
            }
            html += '</div>';
            
            // Altura
            html += '<div class="cdp-dimension-row">';
            html += `<span class="cdp-dimension-label">Altura:</span>`;
            html += `<span class="cdp-dimension-original">${pkg.original.height} cm</span>`;
            html += `<span class="cdp-dimension-arrow">→</span>`;
            html += `<span class="cdp-dimension-scaled ${pkg.clamped.height ? 'clamped' : ''}">${pkg.scaled.height} cm</span>`;
            if (pkg.clamped.height) {
                html += `<span class="cdp-clamp-indicator" title="Valor limitado pelos mínimos/máximos">⚠️</span>`;
            }
            html += '</div>';
            
            // Comprimento
            html += '<div class="cdp-dimension-row">';
            html += `<span class="cdp-dimension-label">Comprimento:</span>`;
            html += `<span class="cdp-dimension-original">${pkg.original.length} cm</span>`;
            html += `<span class="cdp-dimension-arrow">→</span>`;
            html += `<span class="cdp-dimension-scaled ${pkg.clamped.length ? 'clamped' : ''}">${pkg.scaled.length} cm</span>`;
            if (pkg.clamped.length) {
                html += `<span class="cdp-clamp-indicator" title="Valor limitado pelos mínimos/máximos">⚠️</span>`;
            }
            html += '</div>';
            
            // Peso
            html += '<div class="cdp-dimension-row">';
            html += `<span class="cdp-dimension-label">Peso:</span>`;
            html += `<span class="cdp-dimension-original">${pkg.original.weight} kg</span>`;
            html += `<span class="cdp-dimension-arrow">→</span>`;
            html += `<span class="cdp-dimension-scaled">${pkg.scaled.weight} kg</span>`;
            html += '</div>';
            
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        
        $previewContainer.html(html).show();
    }
    
    // Adicionar estilos CSS
    $('<style>').prop('type', 'text/css').html(`
        .cdp-dimension-field input.error {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
        }
        
        .cdp-confirmation-section {
            margin: 20px 0;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        
        .cdp-confirmation-status {
            margin-top: 10px;
            padding: 10px;
            border-radius: 3px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
        }
        
        .cdp-confirmation-status.confirmed {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .cdp-status-icon {
            margin-right: 8px;
        }
        
        .cdp-dimension-summary {
            margin: 15px 0;
            padding: 15px;
            background: #e8f5e8;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
        }
        
        .cdp-dimension-summary h4 {
            margin: 0 0 10px 0;
            color: #155724;
        }
        
        .cdp-dimension-summary ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .single_add_to_cart_button.disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .cdp-packages-preview {
            margin: 20px 0;
            padding: 15px;
            border: 2px solid #e3f2fd;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .cdp-packages-preview h4 {
            margin: 0 0 15px 0;
            color: #1976d2;
            font-size: 16px;
        }
        
        .cdp-scales-info {
            margin-bottom: 15px;
            padding: 10px;
            background: #e8f4fd;
            border-radius: 4px;
            border-left: 4px solid #2196f3;
        }
        
        .cdp-scales-info p {
            margin: 0;
            font-size: 14px;
            color: #1565c0;
        }
        
        .cdp-package-item {
            margin-bottom: 15px;
            padding: 12px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }
        
        .cdp-package-item:last-child {
            margin-bottom: 0;
        }
        
        .cdp-package-item h5 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 14px;
            font-weight: 600;
        }
        
        .cdp-dimension-row {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .cdp-dimension-row:last-child {
            margin-bottom: 0;
        }
        
        .cdp-dimension-label {
            min-width: 80px;
            font-weight: 500;
            color: #6c757d;
        }
        
        .cdp-dimension-original {
            min-width: 60px;
            color: #6c757d;
            text-align: right;
        }
        
        .cdp-dimension-arrow {
            margin: 0 8px;
            color: #28a745;
            font-weight: bold;
        }
        
        .cdp-dimension-scaled {
            min-width: 60px;
            font-weight: 600;
            color: #28a745;
        }
        
        .cdp-dimension-scaled.clamped {
            color: #fd7e14;
            font-weight: bold;
        }
        
        .cdp-clamp-indicator {
            margin-left: 5px;
            font-size: 12px;
            cursor: help;
        }
    `).appendTo('head');
});