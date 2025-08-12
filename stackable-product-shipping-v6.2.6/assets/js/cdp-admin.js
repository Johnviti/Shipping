jQuery(document).ready(function($) {
    'use strict';
    
    // Elementos
    const $enabledCheckbox = $('input[name="cdp_enabled"]');
    const $dimensionFields = $('.cdp-field-group');
    const $baseDimensionInputs = $('#cdp_base_width, #cdp_base_height, #cdp_base_length');
    const $maxDimensionInputs = $('#cdp_max_width, #cdp_max_height, #cdp_max_length');
    const $pricePerCmInput = $('#cdp_price_per_cm');
    
    // Inicializar
    toggleDimensionFields();
    
    // Event listeners
    $enabledCheckbox.on('change', toggleDimensionFields);
    $baseDimensionInputs.on('input', validateBaseDimensions);
    $maxDimensionInputs.on('input', validateMaxDimensions);
    $pricePerCmInput.on('input', validatePricePerCm);
    
    /**
     * Alternar visibilidade dos campos de dimensão
     */
    function toggleDimensionFields() {
        if ($enabledCheckbox.is(':checked')) {
            $dimensionFields.slideDown(300);
        } else {
            $dimensionFields.slideUp(300);
        }
    }
    
    /**
     * Validar dimensões base
     */
    function validateBaseDimensions() {
        $baseDimensionInputs.each(function() {
            const $input = $(this);
            const value = parseFloat($input.val()) || 0;
            
            if (value <= 0) {
                $input.addClass('error');
                showFieldError($input, 'Valor deve ser maior que zero');
            } else {
                $input.removeClass('error');
                hideFieldError($input);
                
                // Atualizar valor mínimo do campo máximo correspondente
                const fieldName = $input.attr('name').replace('cdp_base_', '');
                const $maxField = $('#cdp_max_' + fieldName);
                $maxField.attr('min', value);
                
                // Validar se o valor máximo ainda é válido
                if (parseFloat($maxField.val()) < value) {
                    $maxField.val(value);
                }
            }
        });
    }
    
    /**
     * Validar dimensões máximas
     */
    function validateMaxDimensions() {
        $maxDimensionInputs.each(function() {
            const $input = $(this);
            const value = parseFloat($input.val()) || 0;
            const fieldName = $input.attr('name').replace('cdp_max_', '');
            const $baseField = $('#cdp_base_' + fieldName);
            const baseValue = parseFloat($baseField.val()) || 0;
            
            if (value < baseValue) {
                $input.addClass('error');
                showFieldError($input, 'Valor deve ser maior ou igual à dimensão base');
            } else {
                $input.removeClass('error');
                hideFieldError($input);
            }
        });
    }
    
    /**
     * Validar preço por cm
     */
    function validatePricePerCm() {
        const value = parseFloat($pricePerCmInput.val()) || 0;
        
        if (value < 0) {
            $pricePerCmInput.addClass('error');
            showFieldError($pricePerCmInput, 'Valor não pode ser negativo');
        } else if (value > 100) {
            $pricePerCmInput.addClass('error');
            showFieldError($pricePerCmInput, 'Valor não pode ser maior que 100%');
        } else {
            $pricePerCmInput.removeClass('error');
            hideFieldError($pricePerCmInput);
        }
    }
    
    /**
     * Mostrar erro no campo
     */
    function showFieldError($field, message) {
        const $fieldContainer = $field.closest('.cdp-field');
        let $errorElement = $fieldContainer.find('.cdp-field-error');
        
        if ($errorElement.length === 0) {
            $errorElement = $('<div class="cdp-field-error"></div>');
            $fieldContainer.append($errorElement);
        }
        
        $errorElement.text(message).show();
    }
    
    /**
     * Ocultar erro no campo
     */
    function hideFieldError($field) {
        const $fieldContainer = $field.closest('.cdp-field');
        $fieldContainer.find('.cdp-field-error').hide();
    }
    
    // Adicionar estilos para validação
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .cdp-field input.error {
                border-color: #dc3545 !important;
                box-shadow: 0 0 0 1px #dc3545 !important;
            }
            .cdp-field-error {
                color: #dc3545;
                font-size: 12px;
                margin-top: 5px;
                display: none;
            }
        `)
        .appendTo('head');
});