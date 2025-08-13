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
        
        $('.cdp-price-display').after(confirmButton);
        
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
        
        // Remover container de dimensões confirmadas se existir
        $('.cdp-dimension-summary').remove();
        
        updateConfirmationStatus();
        updateAddToCartButton(false);
        
        clearTimeout(calculateTimeout);
        calculateTimeout = setTimeout(calculatePrice, 300);
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
            // Remover o elemento de status de confirmação
            $('#cdp-confirmation-status').remove();
            // $status.html(`
            //     <span class="cdp-status-icon">✅</span>
            //     <span class="cdp-status-text">Dimensões confirmadas! Você pode adicionar ao carrinho.</span>
            // `);
            // $status.addClass('confirmed');
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
        // Remover qualquer resumo existente antes de criar um novo
        $('.cdp-dimension-summary').remove();
        
        const pluginUrl = cdp_ajax.pluginUrl || '';
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
        const widthDiff = Math.max(0, width - baseWidth);
        const heightDiff = Math.max(0, height - baseHeight);
        const lengthDiff = Math.max(0, length - baseLength);
        
        const totalDiffCm = widthDiff + heightDiff + lengthDiff;
        const priceIncrease = (basePrice * pricePerCm / 100) * totalDiffCm;
        
        return basePrice + priceIncrease;
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
    
    // Adicionar modal para editar dimensões no carrinho
    function addCartEditModal() {
        const modalHtml = `
            <div id="cdp-cart-edit-modal" class="cdp-modal" style="display: none;">
                <div class="cdp-modal-content">
                    <div class="cdp-modal-header">
                        <h3>Editar Dimensões</h3>
                        <span class="cdp-modal-close">&times;</span>
                    </div>
                    <div class="cdp-modal-body">
                        <div class="cdp-dimension-field">
                            <label for="cdp-cart-width">Largura (cm):</label>
                            <input type="number" id="cdp-cart-width" step="0.01" min="0">
                            <div class="cdp-dimension-limits"></div>
                        </div>
                        <div class="cdp-dimension-field">
                            <label for="cdp-cart-height">Altura (cm):</label>
                            <input type="number" id="cdp-cart-height" step="0.01" min="0">
                            <div class="cdp-dimension-limits"></div>
                        </div>
                        <div class="cdp-dimension-field">
                            <label for="cdp-cart-length">Comprimento (cm):</label>
                            <input type="number" id="cdp-cart-length" step="0.01" min="0">
                            <div class="cdp-dimension-limits"></div>
                        </div>
                        <div class="cdp-modal-price">
                            <strong>Novo Preço: <span id="cdp-modal-price">-</span></strong>
                        </div>
                        <div class="cdp-modal-error" style="display: none;"></div>
                    </div>
                    <div class="cdp-modal-footer">
                        <button type="button" class="button" id="cdp-modal-cancel">Cancelar</button>
                        <button type="button" class="button button-primary" id="cdp-modal-save">Salvar Alterações</button>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        
        // Event listeners do modal
        $('.cdp-modal-close, #cdp-modal-cancel').on('click', closeCartEditModal);
        $('#cdp-modal-save').on('click', saveCartDimensions);
        
        // Fechar modal clicando fora
        $('#cdp-cart-edit-modal').on('click', function(e) {
            if (e.target === this) {
                closeCartEditModal();
            }
        });
        
        // Event listeners para inputs
        $('#cdp-cart-width, #cdp-cart-height, #cdp-cart-length').on('input', calculateModalPrice);
    }
    
    // Abrir modal de edição no carrinho
    function openCartEditModal(cartKey, productId, currentDimensions) {
        // Buscar dados do produto
        $.ajax({
            url: cdp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cdp_get_product_data',
                nonce: cdp_ajax.nonce,
                product_id: productId
            },
            success: function(response) {
                if (response.success) {
                    const productData = response.data;
                    
                    // Configurar inputs
                    $('#cdp-cart-width').attr('min', productData.base_width).attr('max', productData.max_width).val(currentDimensions.width);
                    $('#cdp-cart-height').attr('min', productData.base_height).attr('max', productData.max_height).val(currentDimensions.height);
                    $('#cdp-cart-length').attr('min', productData.base_length).attr('max', productData.max_length).val(currentDimensions.length);
                    
                    // Mostrar limites
                    $('#cdp-cart-width').siblings('.cdp-dimension-limits').text(`Min: ${productData.base_width} | Max: ${productData.max_width}`);
                    $('#cdp-cart-height').siblings('.cdp-dimension-limits').text(`Min: ${productData.base_height} | Max: ${productData.max_height}`);
                    $('#cdp-cart-length').siblings('.cdp-dimension-limits').text(`Min: ${productData.base_length} | Max: ${productData.max_length}`);
                    
                    // Armazenar dados para uso posterior
                    $('#cdp-cart-edit-modal').data('cart-key', cartKey).data('product-data', productData);
                    
                    // Calcular preço inicial
                    calculateModalPrice();
                    
                    // Mostrar modal
                    $('#cdp-cart-edit-modal').show();
                }
            }
        });
    }
    
    // Fechar modal
    function closeCartEditModal() {
        $('#cdp-cart-edit-modal').hide();
        $('.cdp-modal-error').hide();
        $('#cdp-cart-width, #cdp-cart-height, #cdp-cart-length').removeClass('error');
    }
    
    // Calcular preço no modal
    function calculateModalPrice() {
        const width = parseFloat($('#cdp-cart-width').val()) || 0;
        const height = parseFloat($('#cdp-cart-height').val()) || 0;
        const length = parseFloat($('#cdp-cart-length').val()) || 0;
        const productData = $('#cdp-cart-edit-modal').data('product-data');
        
        if (!productData) return;
        
        // Validar dimensões
        let isValid = true;
        if (width < productData.base_width || width > productData.max_width) {
            $('#cdp-cart-width').addClass('error');
            isValid = false;
        } else {
            $('#cdp-cart-width').removeClass('error');
        }
        
        if (height < productData.base_height || height > productData.max_height) {
            $('#cdp-cart-height').addClass('error');
            isValid = false;
        } else {
            $('#cdp-cart-height').removeClass('error');
        }
        
        if (length < productData.base_length || length > productData.max_length) {
            $('#cdp-cart-length').addClass('error');
            isValid = false;
        } else {
            $('#cdp-cart-length').removeClass('error');
        }
        
        if (isValid) {
            // Calcular novo preço
            const widthDiff = Math.max(0, width - productData.base_width);
            const heightDiff = Math.max(0, height - productData.base_height);
            const lengthDiff = Math.max(0, length - productData.base_length);
            const totalDiffCm = widthDiff + heightDiff + lengthDiff;
            const priceIncrease = (productData.base_price * productData.price_per_cm / 100) * totalDiffCm;
            const newPrice = productData.base_price + priceIncrease;
            
            const formattedPrice = new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(newPrice);
            
            $('#cdp-modal-price').text(formattedPrice);
            $('#cdp-modal-save').prop('disabled', false);
        } else {
            $('#cdp-modal-price').text('Dimensões inválidas');
            $('#cdp-modal-save').prop('disabled', true);
        }
    }
    
    // Salvar dimensões do carrinho
    function saveCartDimensions() {
        const cartKey = $('#cdp-cart-edit-modal').data('cart-key');
        const width = parseFloat($('#cdp-cart-width').val());
        const height = parseFloat($('#cdp-cart-height').val());
        const length = parseFloat($('#cdp-cart-length').val());
        
        $('#cdp-modal-save').prop('disabled', true).text('Salvando...');
        
        $.ajax({
            url: cdp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cdp_update_cart_dimensions',
                nonce: cdp_ajax.nonce,
                cart_key: cartKey,
                width: width,
                height: height,
                length: length
            },
            success: function(response) {
                if (response.success) {
                    // Fechar modal
                    closeCartEditModal();
                    
                    // Recarregar página do carrinho para atualizar tudo
                    window.location.reload();
                } else {
                    $('.cdp-modal-error').text(response.data || 'Erro ao atualizar dimensões').show();
                }
            },
            error: function() {
                $('.cdp-modal-error').text('Erro de conexão').show();
            },
            complete: function() {
                $('#cdp-modal-save').prop('disabled', false).text('Salvar Alterações');
            }
        });
    }
    
    // Event listener para botões de editar no carrinho
    $(document).on('click', '.cdp-edit-cart-dimensions', function(e) {
        e.preventDefault();
        
        const cartKey = $(this).data('cart-key');
        const productId = $(this).data('product-id');
        
        // Extrair dimensões atuais do texto exibido
        const dimensionText = $(this).closest('tr').find('.wc-item-meta').text();
        const matches = dimensionText.match(/([\d,]+)\s*x\s*([\d,]+)\s*x\s*([\d,]+)/);
        
        if (matches) {
            const currentDimensions = {
                width: parseFloat(matches[1].replace(',', '.')),
                height: parseFloat(matches[2].replace(',', '.')),
                length: parseFloat(matches[3].replace(',', '.'))
            };
            
            openCartEditModal(cartKey, productId, currentDimensions);
        }
    });
    
    // Inicializar modal quando a página carregar
    if ($('body').hasClass('woocommerce-cart')) {
        addCartEditModal();
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
            border: none;
            border-radius: 5px;
            background: none !important;
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
        
        /* Estilos do Modal */
        .cdp-modal {
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .cdp-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            border: none;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .cdp-modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cdp-modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .cdp-modal-close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .cdp-modal-close:hover {
            color: #000;
        }
        
        .cdp-modal-body {
            padding: 20px;
        }
        
        .cdp-dimension-field {
            margin-bottom: 15px;
        }
        
        .cdp-dimension-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .cdp-dimension-field input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .cdp-dimension-limits {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }
        
        .cdp-modal-price {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
            text-align: center;
            font-size: 16px;
        }
        
        .cdp-modal-error {
            margin: 10px 0;
            padding: 10px;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
        }
        
        .cdp-modal-footer {
            padding: 20px;
            border-top: 1px solid #eee;
            text-align: right;
        }
        
        .cdp-modal-footer .button {
            margin-left: 10px;
        }
        
        .cdp-edit-cart-dimensions {
            color: #0073aa;
            text-decoration: none;
            font-size: 12px;
        }
        
        .cdp-edit-cart-dimensions:hover {
            text-decoration: underline;
        }
    `).appendTo('head');
});