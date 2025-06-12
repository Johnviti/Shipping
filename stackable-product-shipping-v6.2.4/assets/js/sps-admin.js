
jQuery(document).ready(function($){

    // Remove row
    $(document).on('click', '.sps-remove-product', function(){
        $(this).closest('tr').remove();
    });

    // Search in groups page
    $('#sps-groups-search').on('input', function(){
        var val = $(this).val().toLowerCase();
        $('#sps-groups-table tbody tr').each(function(){
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(val)!==-1);
        });
    });

    // Confirm deletion of saved group
    $('#sps-groups-table').on('click', '.sps-delete', function(e){
        if(!confirm('Tem certeza que deseja excluir este grupo?')) {
            e.preventDefault();
            return false;
        }
    });
});


jQuery(document).ready(function($) {
    // Abrir modal de simulação para grupo
    $('.sps-simulate-group').on('click', function(e) {
        e.preventDefault();
        var groupId = $(this).data('group-id');
        $('#sps-group-simulation-modal').data('group-id', groupId).show();
    });
    
    // Fechar modal
    $('.sps-modal-close').on('click', function() {
        $(this).closest('.sps-modal').hide();
    });
    
    // Executar simulação para grupo
    $('#sps-run-group-simulation').on('click', function() {
        var groupId = $('#sps-group-simulation-modal').data('group-id');
        var origin = $('#sps-group-simulation-origin').val();
        var destination = $('#sps-group-simulation-destination').val();
        
        if (!origin || !destination) {
            alert('Por favor, preencha os CEPs de origem e destino.');
            return;
        }
        
        $('.sps-simulation-loading').show();
        $('.sps-simulation-error, .sps-simulation-success').hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sps_simulate_group_shipping',
                group_id: groupId,
                origin: origin,
                destination: destination,
                nonce: sps_admin_ajax.simulate_shipping_nonce
            },
            success: function(response) {
                $('.sps-simulation-loading').hide();
                
                if (response.success) {
                    $('.sps-simulation-success').show();
                    
                    // Preencher tabela com resultados
                    var html = '';
                    if (response.data.prices && response.data.prices.length > 0) {
                        $.each(response.data.prices, function(i, item) {
                            html += '<tr>';
                            html += '<td>' + item.shipping_carrier + '</td>';
                            html += '<td>R$ ' + parseFloat(item.price).toFixed(2) + '</td>';
                            html += '<td>' + item.delivery_time + '</td>';
                            html += '<td>' + (item.service_type || '-') + '</td>';
                            html += '</tr>';
                        });
                    } else {
                        html = '<tr><td colspan="4">Nenhuma transportadora disponível para esta rota.</td></tr>';
                    }
                    
                    $('#sps-group-simulation-results').html(html);
                } else {
                    $('.sps-simulation-error').show();
                    $('.sps-error-message').text(response.data.message || 'Erro desconhecido');
                }
            },
            error: function(xhr, status, error) {
                $('.sps-simulation-loading').hide();
                $('.sps-simulation-error').show();
                $('.sps-error-message').text('Erro ao comunicar com o servidor: ' + error);
            }
        });
    });
});

jQuery(document).ready(function($) {
    // Abrir modal de simulação

    $('.sps-simulate-shipping-link').on('click', function(e) {
        e.preventDefault();
        $('#sps-shipping-simulation-modal').show();
    });
    
    // Fechar modal
    $('.sps-modal-close').on('click', function() {
        $('.sps-modal').hide();
    });
    
    // Fechar modal ao clicar fora
    $(window).on('click', function(e) {
        if ($(e.target).is('.sps-modal')) {
            $('.sps-modal').hide();
        }
    });

    $('#sps-simulate-shipping').on('click', function(e) {
        e.preventDefault();
        $('#sps-shipping-simulation-modal').show();
    });
    
    // Fechar modal
    $('.sps-modal-close').on('click', function() {
        $('#sps-shipping-simulation-modal').hide();
    });
    
    // Fechar modal ao clicar fora
    $(window).on('click', function(e) {
        if ($(e.target).is('.sps-modal')) {
            $('.sps-modal').hide();
        }
    });
    
    // Alternar entre abas de resultados
    $('.sps-simulation-tabs .sps-tab-button').on('click', function() {
        $('.sps-simulation-tabs .sps-tab-button').removeClass('active');
        $(this).addClass('active');
        
        $('.sps-tab-content').hide();
        $('#sps-tab-' + $(this).data('tab')).show();
    });
    
    // Alternar entre abas de entrada
    $('.sps-input-tabs .sps-tab-button').on('click', function() {
        $('.sps-input-tabs .sps-tab-button').removeClass('active');
        $(this).addClass('active');
        
        $('.sps-input-content').hide();
        $('#sps-input-' + $(this).data('input-tab')).show();
    });
    
    // Adicionar produto individual
    $('#sps-add-ind-product').on('click', function() {
        const newRow = `
            <tr class="sps-individual-product-row">
                <td><input type="number" class="sps-ind-quantity" value="1" min="1"></td>
                <td><input type="number" class="sps-ind-width" value="10" min="1" step="0.01"></td>
                <td><input type="number" class="sps-ind-height" value="10" min="1" step="0.01"></td>
                <td><input type="number" class="sps-ind-length" value="10" min="1" step="0.01"></td>
                <td><input type="number" class="sps-ind-weight" value="0.5" min="0.01" step="0.01"></td>
                <td><button type="button" class="button sps-remove-ind-product">Remover</button></td>
            </tr>
        `;
        $('#sps-individual-products-table tbody').append(newRow);
    });
    
    // Remover produto individual
    $(document).on('click', '.sps-remove-ind-product', function() {
        if ($('.sps-individual-product-row').length > 1) {
            $(this).closest('tr').remove();
        } else {
            alert('É necessário pelo menos um produto.');
        }
    });
    
    // Executar simulação
    $('#sps-run-simulation').on('click', function() {
        // Validar campos
        const origin = $('#sps-simulation-origin').val().replace(/\D/g, '');
        const destination = $('#sps-simulation-destination').val().replace(/\D/g, '');
        const value = parseFloat($('#sps-simulation-value').val()) || 100;

        console.log(`Test: ${origin}, ${destination}, ${value}`);
        
        if (!origin || !destination) {
            alert('Por favor, preencha os CEPs de origem e destino.');
            return;
        }
        
        // Mostrar loading
        $('#sps-simulation-results').show();
        $('.sps-simulation-loading').show();
        $('.sps-simulation-error, .sps-simulation-success').hide();
        
        // Preparar volumes para pacote empilhado
        const stackedVolume = {
            quantity: parseInt($('#sps-stacked-quantity').val()) || 3,
            width: parseFloat($('#sps-stacked-width').val()) || 20,
            height: parseFloat($('#sps-stacked-height').val()) || 20,
            length: parseFloat($('#sps-stacked-length').val()) || 20,
            weight: parseFloat($('#sps-stacked-weight').val()) || 1
        };
        
        // Preparar volumes para produtos individuais
        const individualVolumes = [];
        $('.sps-individual-product-row').each(function() {
            const row = $(this);
            individualVolumes.push({
                quantity: parseInt(row.find('.sps-ind-quantity').val()) || 4,
                width: parseFloat(row.find('.sps-ind-width').val()) || 10,
                height: parseFloat(row.find('.sps-ind-height').val()) || 10,
                length: parseFloat(row.find('.sps-ind-length').val()) || 10,
                weight: parseFloat(row.find('.sps-ind-weight').val()) || 0.5
            });
        });

        console.log('individualVolumes', individualVolumes);
        console.log('JSON.stringify(individualVolumes)', JSON.stringify(individualVolumes));
        console.log('Nonce value:', sps_admin_ajax.simulate_shipping_nonce);
        
        // Fazer requisições AJAX para produtos separados e combinados
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sps_simulate_shipping',
                origin: origin,
                destination: destination,
                cargo_types: ['28'],
                value: value,
                separate: true,
                volumes: JSON.stringify(individualVolumes),
                nonce: sps_admin_ajax.simulate_shipping_nonce
            },
            dataType: 'json',
            beforeSend: function() {
                console.log('Sending separate request with data:', this.data);
            },
            success: function(separateResponse) {
                console.log('Separate response:', separateResponse);
                // Fazer segunda requisição para pacote combinado
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sps_simulate_shipping',
                        origin: origin,
                        destination: destination,
                        cargo_types: ['28'],
                        value: value,
                        separate: false,
                        volumes: JSON.stringify([stackedVolume]),
                        nonce: sps_admin_ajax.simulate_shipping_nonce
                    },
                    dataType: 'json',
                    beforeSend: function() {
                        console.log('Sending combined request with data:', {
                            origin: origin,
                            destination: destination,
                            value: value,
                            volumes: [stackedVolume]
                        });
                    },
                    success: function(combinedResponse) {
                        console.log('Combined response:', combinedResponse);
                        $('.sps-simulation-loading').hide();
                        
                        if (separateResponse.success && combinedResponse.success) {
                            $('.sps-simulation-success').show();
                            
                            // Preencher tabelas com resultados
                            displayResults(separateResponse.data, combinedResponse.data);
                        } else {
                            $('.sps-simulation-error').show();
                            $('.sps-error-message').text(
                                (separateResponse.data && separateResponse.data.message) || 
                                (combinedResponse.data && combinedResponse.data.message) || 
                                'Erro ao consultar a API.'
                            );
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Combined request error:', xhr, status, error);
                        $('.sps-simulation-loading').hide();
                        $('.sps-simulation-error').show();
                        $('.sps-error-message').text('Erro ao comunicar com o servidor: ' + error);
                    }
                });
            },
            error: function(xhr, status, error) {
                console.error('Separate request error:', xhr, status, error);
                $('.sps-simulation-loading').hide();
                $('.sps-simulation-error').show();
                $('.sps-error-message').text('Erro ao comunicar com o servidor: ' + error);
            }
        });
    });
    
    // Função para exibir os resultados nas tabelas
    function displayResults(separateData, combinedData) {
        const $separateResults = $('#sps-separate-results');
        const $combinedResults = $('#sps-combined-results');
        const $comparisonResults = $('#sps-comparison-results');
        
        $separateResults.empty();
        $combinedResults.empty();
        $comparisonResults.empty();
        
        // Preencher tabela de produtos separados
        if (separateData.prices && separateData.prices.length > 0) {
            separateData.prices.forEach(function(price) {
                $separateResults.append(`
                    <tr>
                        <td>${price.shipping_carrier}</td>
                        <td>R$ ${price.price.toFixed(2)}</td>
                        <td>${price.delivery_time}</td>
                        <td>${price.service_type || 'Padrão'}</td>
                    </tr>
                `);
            });
        } else {
            $separateResults.append('<tr><td colspan="4">Nenhum resultado encontrado</td></tr>');
        }
        
        // Preencher tabela de pacote combinado
        if (combinedData.prices && combinedData.prices.length > 0) {
            combinedData.prices.forEach(function(price) {
                $combinedResults.append(`
                    <tr>
                        <td>${price.shipping_carrier}</td>
                        <td>R$ ${price.price.toFixed(2)}</td>
                        <td>${price.delivery_time}</td>
                        <td>${price.service_type || 'Padrão'}</td>
                    </tr>
                `);
            });
        } else {
            $combinedResults.append('<tr><td colspan="4">Nenhum resultado encontrado</td></tr>');
        }
        
        // Preencher tabela de comparação
        const carriers = new Set();
        const priceMap = {};
        
        if (separateData.prices) {
            separateData.prices.forEach(function(price) {
                carriers.add(price.shipping_carrier);
                if (!priceMap[price.shipping_carrier]) {
                    priceMap[price.shipping_carrier] = {};
                }
                priceMap[price.shipping_carrier].separate = price.price;
            });
        }
        
        if (combinedData.prices) {
            combinedData.prices.forEach(function(price) {
                carriers.add(price.shipping_carrier);
                if (!priceMap[price.shipping_carrier]) {
                    priceMap[price.shipping_carrier] = {};
                }
                priceMap[price.shipping_carrier].combined = price.price;
            });
        }
        
        carriers.forEach(function(carrier) {
            const separatePrice = priceMap[carrier].separate || 0;
            const combinedPrice = priceMap[carrier].combined || 0;
            
            if (separatePrice && combinedPrice) {
                const economy = separatePrice - combinedPrice;
                const economyPercent = (economy / separatePrice) * 100;
                const economyClass = economy > 0 ? 'sps-economy-positive' : 'sps-economy-negative';
                
                $comparisonResults.append(`
                    <tr>
                        <td>${carrier}</td>
                        <td>R$ ${separatePrice.toFixed(2)}</td>
                        <td>R$ ${combinedPrice.toFixed(2)}</td>
                        <td class="${economyClass}">R$ ${economy.toFixed(2)}</td>
                        <td class="${economyClass}">${economyPercent.toFixed(2)}%</td>
                    </tr>
                `);
            }
        });
        
        if ($comparisonResults.children().length === 0) {
            $comparisonResults.append('<tr><td colspan="5">Não há dados suficientes para comparação</td></tr>');
        }
    }
});