
jQuery(document).ready(function($) {

    // Remove WooCommerce admin notices from SPS pages
    function removeWooCommerceNotices() {
        $('.sps-admin-wrap .error, .sps-admin-wrap .notice, .sps-admin-wrap .notice-error, .sps-admin-wrap .notice-warning, .sps-admin-wrap .notice-info, .sps-admin-wrap .notice-success, .sps-admin-wrap .updated').remove();
    }
    
    // Remove notices on page load
    removeWooCommerceNotices();
    
    // Remove notices that might be added dynamically
    setInterval(removeWooCommerceNotices, 1000);

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
    
    // Função para exibir resultados de múltiplas APIs
    function displayResults(separateData, combinedData) {
        // Limpar tabelas existentes
        $('#sps-separate-results').empty();
        $('#sps-combined-results').empty();
        $('#sps-comparison-results').empty();
        $('.sps-best-option-info').remove();
        
        console.log('separateData', separateData);
        console.log('combinedData', combinedData);
        
        // Função para normalizar dados de preço
        function normalizePrice(price, source) {
            return {
                source: source,
                shipping_carrier: price.shipping_carrier || price.carrier || 'N/A',
                modal: price.modal || price.service || 'N/A',
                price: price.price || 0,
                delivery_time: price.delivery_time || 'N/A'
            };
        }
        
        // Array para armazenar todas as opções de frete
        let allOptions = [];
        
        // Processar resultados separados
        if (separateData) {
            // Combinar resultados de todas as APIs
            let allSeparateResults = [];
            
            if (separateData.central && separateData.central.prices) {
                separateData.central.prices.forEach(price => {
                    allSeparateResults.push(normalizePrice(price, 'Central do Frete'));
                });
            }
            
            if (separateData.frenet && separateData.frenet.prices) {
                separateData.frenet.prices.forEach(price => {
                    allSeparateResults.push(normalizePrice(price, 'Frenet'));
                });
            }
            
            // Ordenar por preço
            allSeparateResults.sort((a, b) => a.price - b.price);
            
            // Adicionar ao array de todas as opções
            allSeparateResults.forEach(option => {
                allOptions.push({
                    ...option,
                    type: 'separate'
                });
            });
            
            // Exibir resultados separados
            allSeparateResults.forEach((price, index) => {
                const row = `
                    <tr data-price="${price.price}" data-carrier="${price.shipping_carrier}" data-source="${price.source}" data-delivery="${price.delivery_time}" data-type="separate">
                        <td>${price.source}</td>
                        <td>${price.shipping_carrier}</td>
                        <td>${price.modal}</td>
                        <td>R$ ${price.price.toFixed(2)}</td>
                        <td>${price.delivery_time} dias</td>
                    </tr>
                `;
                $('#sps-separate-results').append(row);
            });
            
            if (allSeparateResults.length === 0) {
                $('#sps-separate-results').append('<tr><td colspan="5">Nenhum resultado encontrado</td></tr>');
            }
        }
        
        // Processar resultados combinados
        if (combinedData) {
            let allCombinedResults = [];
            
            if (combinedData.central && combinedData.central.prices) {
                combinedData.central.prices.forEach(price => {
                    allCombinedResults.push(normalizePrice(price, 'Central do Frete'));
                });
            }
            
            if (combinedData.frenet && combinedData.frenet.prices) {
                combinedData.frenet.prices.forEach(price => {
                    allCombinedResults.push(normalizePrice(price, 'Frenet'));
                });
            }
            
            // Ordenar por preço
            allCombinedResults.sort((a, b) => a.price - b.price);
            
            // Adicionar ao array de todas as opções
            allCombinedResults.forEach(option => {
                allOptions.push({
                    ...option,
                    type: 'combined'
                });
            });
            
            // Exibir resultados combinados
            allCombinedResults.forEach((price, index) => {
                const row = `
                    <tr data-price="${price.price}" data-carrier="${price.shipping_carrier}" data-source="${price.source}" data-delivery="${price.delivery_time}" data-type="combined">
                        <td>${price.source}</td>
                        <td>${price.shipping_carrier}</td>
                        <td>${price.modal}</td>
                        <td>R$ ${price.price.toFixed(2)}</td>
                        <td>${price.delivery_time} dias</td>
                    </tr>
                `;
                $('#sps-combined-results').append(row);
            });
            
            if (allCombinedResults.length === 0) {
                $('#sps-combined-results').append('<tr><td colspan="5">Nenhum resultado encontrado</td></tr>');
            }
        }
        
        // Processar comparação (se ambos os dados estiverem disponíveis)
        if (separateData && combinedData) {
            // Coletar todos os preços separados
            let allSeparatePrices = [];
            if (separateData.central && separateData.central.prices) {
                separateData.central.prices.forEach(price => {
                    allSeparatePrices.push({
                        ...price,
                        source: 'Central do Frete'
                    });
                });
            }
            if (separateData.frenet && separateData.frenet.prices) {
                separateData.frenet.prices.forEach(price => {
                    allSeparatePrices.push({
                        ...price,
                        source: 'Frenet'
                    });
                });
            }
            
            // Coletar todos os preços combinados
            let allCombinedPrices = [];
            if (combinedData.central && combinedData.central.prices) {
                combinedData.central.prices.forEach(price => {
                    allCombinedPrices.push({
                        ...price,
                        source: 'Central do Frete'
                    });
                });
            }
            if (combinedData.frenet && combinedData.frenet.prices) {
                combinedData.frenet.prices.forEach(price => {
                    allCombinedPrices.push({
                        ...price,
                        source: 'Frenet'
                    });
                });
            }
            
            // Ordenar ambos por preço
            allSeparatePrices.sort((a, b) => a.price - b.price);
            allCombinedPrices.sort((a, b) => a.price - b.price);
            
            // Criar comparações para cada combinação
            const maxLength = Math.max(allSeparatePrices.length, allCombinedPrices.length);
            
            for (let i = 0; i < maxLength; i++) {
                const separatePrice = allSeparatePrices[i];
                const combinedPrice = allCombinedPrices[i];
                
                if (separatePrice && combinedPrice) {
                    const difference = separatePrice.price - combinedPrice.price;
                    const percentageEconomy = ((difference / separatePrice.price) * 100).toFixed(2);
                    
                    const comparisonRow = `
                        <tr>
                            <td>${separatePrice.source} - ${separatePrice.shipping_carrier || separatePrice.carrier || 'N/A'}</td>
                            <td>R$ ${separatePrice.price.toFixed(2)}</td>
                            <td>R$ ${combinedPrice.price.toFixed(2)}</td>
                            <td>R$ ${Math.abs(difference).toFixed(2)}</td>
                            <td class="${difference > 0 ? 'positive-economy' : ''}">
                                ${Math.abs(percentageEconomy)}% ${difference > 0 ? '(Economia)' : '(Mais caro)'}
                            </td>
                        </tr>
                    `;
                    $('#sps-comparison-results').append(comparisonRow);
                } else if (separatePrice && !combinedPrice) {
                    // Só tem preço separado
                    const comparisonRow = `
                        <tr>
                            <td>${separatePrice.source} - ${separatePrice.shipping_carrier || separatePrice.carrier || 'N/A'}</td>
                            <td>R$ ${separatePrice.price.toFixed(2)}</td>
                            <td>-</td>
                            <td>-</td>
                            <td>Sem opção combinada</td>
                        </tr>
                    `;
                    $('#sps-comparison-results').append(comparisonRow);
                } else if (!separatePrice && combinedPrice) {
                    // Só tem preço combinado
                    const comparisonRow = `
                        <tr>
                            <td>-</td>
                            <td>-</td>
                            <td>R$ ${combinedPrice.price.toFixed(2)}</td>
                            <td>-</td>
                            <td>Sem opção separada</td>
                        </tr>
                    `;
                    $('#sps-comparison-results').append(comparisonRow);
                }
            }
            
            // Adicionar linha de resumo com os melhores preços
            if (allSeparatePrices.length > 0 && allCombinedPrices.length > 0) {
                const bestSeparate = allSeparatePrices[0]; // Já ordenado por preço
                const bestCombined = allCombinedPrices[0]; // Já ordenado por preço
                
                const difference = bestSeparate.price - bestCombined.price;
                const percentageEconomy = ((difference / bestSeparate.price) * 100).toFixed(2);
                
                const summaryRow = `
                    <tr style="background-color: #f0f0f0; font-weight: bold; border-top: 2px solid #ddd;">
                        <td><strong>MELHOR OPÇÃO GERAL</strong></td>
                        <td><strong>R$ ${bestSeparate.price.toFixed(2)}</strong></td>
                        <td><strong>R$ ${bestCombined.price.toFixed(2)}</strong></td>
                        <td><strong>R$ ${Math.abs(difference).toFixed(2)}</strong></td>
                        <td class="${difference > 0 ? 'positive-economy' : ''}" style="font-weight: bold;">
                            <strong>${Math.abs(percentageEconomy)}% ${difference > 0 ? '(ECONOMIA)' : '(MAIS CARO)'}</strong>
                        </td>
                    </tr>
                `;
                $('#sps-comparison-results').append(summaryRow);
            }
        }
        
        // Determinar e exibir a melhor opção geral
        if (allOptions.length > 0) {
            // Ordenar todas as opções por preço
            allOptions.sort((a, b) => a.price - b.price);
            const bestOption = allOptions[0];
            
            // Destacar a melhor opção na tabela
            highlightBestOption(bestOption);
            
            // Exibir seção fixa da melhor opção
            showBestOptionInfo(bestOption);
        }
    }
    
        // Função para exibir seção fixa da melhor opção de frete
    function showBestOptionInfo(bestOption, targetContainer = '.sps-simulation-success') {
        // Remover informações anteriores da melhor opção
        $('.sps-best-option-info').remove();
        
        // Construir HTML da seção fixa
        const infoHtml = `
            <div class="sps-best-option-info">
                <div class="sps-info-header">
                    <span class="dashicons dashicons-awards"></span>
                    🏆 MELHOR OPÇÃO DE FRETE
                </div>
                <div class="sps-info-content">
                    <div class="sps-info-item">
                        <div class="sps-info-label">Transportadora</div>
                        <div class="sps-info-value">${bestOption.shipping_carrier}</div>
                    </div>
                    <div class="sps-info-item">
                        <div class="sps-info-label">Preço</div>
                        <div class="sps-info-value">R$ ${bestOption.price.toFixed(2)}</div>
                    </div>
                    <div class="sps-info-item">
                        <div class="sps-info-label">Prazo</div>
                        <div class="sps-info-value">${bestOption.delivery_time} dias</div>
                    </div>
                    <div class="sps-info-item">
                        <div class="sps-info-label">Fonte</div>
                        <div class="sps-info-value">${bestOption.source}</div>
                    </div>
                </div>
                <div class="sps-info-description">
                    Melhor custo-benefício entre todas as opções disponíveis
                </div>
            </div>
        `;
        
        // Inserir a seção após o elemento com id="after-this"
        if ($('#after-this').length > 0) {
            $('#after-this').after(infoHtml);
        } else if ($(targetContainer).length > 0) {
            $(targetContainer).after(infoHtml);
        } else {
            // Fallback: inserir após o primeiro h3 encontrado
            $('h3:contains("Resultados da Simulação")').first().after(infoHtml);
        }
        
        // Mostrar a seção com animação
        $('.sps-best-option-info').fadeIn(500);
    }
    
    // Função para exibir o popup da melhor opção
    function showBestOptionPopup(bestOption) {
        // Remover popup anterior se existir
        $('.sps-best-option-popup').remove();
        
        const popupHtml = `
            <div class="sps-best-option-popup auto-hide">
                <button class="sps-popup-close" onclick="$(this).parent().remove()">&times;</button>
                <div class="sps-popup-header">
                    <span class="dashicons dashicons-awards"></span>
                    MELHOR OPÇÃO DE FRETE
                </div>
                <div class="sps-popup-content">
                    <div class="sps-popup-carrier">
                        ${bestOption.shipping_carrier} (${bestOption.source})
                    </div>
                    <div class="sps-popup-price">
                        Preço: R$ ${bestOption.price.toFixed(2)}
                    </div>
                    <div class="sps-popup-delivery">
                        Prazo: ${bestOption.delivery_time} dias
                    </div>
                    <div class="sps-popup-description">
                        Melhor custo-benefício entre todas as opções
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(popupHtml);
        
        // Auto-remover após 4 segundos
        setTimeout(() => {
            $('.sps-best-option-popup').remove();
        }, 4000);
    }
    
    // Função para destacar a melhor opção na tabela
    function highlightBestOption(bestOption) {
        // Encontrar e destacar a linha correspondente
        const targetTable = bestOption.type === 'separate' ? '#sps-separate-results' : '#sps-combined-results';
        
        $(`${targetTable} tr`).each(function() {
            const row = $(this);
            const rowPrice = parseFloat(row.data('price'));
            const rowCarrier = row.data('carrier');
            const rowSource = row.data('source');
            
            if (rowPrice === bestOption.price && 
                rowCarrier === bestOption.shipping_carrier && 
                rowSource === bestOption.source) {
                row.addClass('sps-best-option-row');
                return false; // Break the loop
            }
        });
    }
    
    // Função para processar resultados de grupos (simulação de grupos)
    function displayGroupResults(response) {
        // Limpar resultados anteriores
        $('#sps-group-stacked-results').empty();
        $('.sps-best-option-info').remove();
        
        let allGroupOptions = [];
        
        // Processar resultados diretos da API
        if (response.data && response.data.prices && Array.isArray(response.data.prices)) {
            const prices = response.data.prices;
            
            if (prices.length > 0) {
                // Normalizar e ordenar por preço
                const normalizedPrices = prices.map(price => ({
                    shipping_carrier: price.shipping_carrier || 'Desconhecido',
                    price: parseFloat(price.price) || 0,
                    delivery_time: price.delivery_time || '-',
                    modal: price.modal || price.service_type || 'Padrão',
                    source: 'API'
                })).sort((a, b) => a.price - b.price);
                
                allGroupOptions = normalizedPrices;
                
                // Adicionar cada preço à tabela
                normalizedPrices.forEach((price, index) => {
                    const isFirst = index === 0;
                    const rowClass = isFirst ? 'sps-best-option-row' : '';
                    const row = `
                        <tr class="${rowClass}" data-price="${price.price}" data-carrier="${price.shipping_carrier}" data-source="${price.source}" data-delivery="${price.delivery_time}">
                            <td>${price.shipping_carrier}</td>
                            <td>R$ ${price.price.toFixed(2)}</td>
                            <td>${price.delivery_time}</td>
                            <td>${price.modal}</td>
                        </tr>
                    `;
                    $('#sps-group-stacked-results').append(row);
                });
            } else {
                $('#sps-group-stacked-results').html('<tr><td colspan="4">Nenhuma cotação encontrada para este grupo.</td></tr>');
            }
        }
        // Processar resultados no formato de quotes
        else if (response.data.quotes && response.data.quotes.length > 0) {
            const quotes = response.data.quotes.sort((a, b) => parseFloat(a.price) - parseFloat(b.price));
            allGroupOptions = quotes.map(quote => ({
                shipping_carrier: quote.carrier,
                price: parseFloat(quote.price),
                delivery_time: quote.delivery_time,
                modal: quote.service,
                source: quote.source || 'API'
            }));
            
            quotes.forEach((quote, index) => {
                const sourceLabel = quote.source ? ' (' + quote.source + ')' : '';
                const isFirst = index === 0;
                const rowClass = isFirst ? 'sps-best-option-row' : '';
                const row = `
                    <tr class="${rowClass}" data-price="${quote.price}" data-carrier="${quote.carrier}" data-source="${quote.source || 'API'}" data-delivery="${quote.delivery_time}">
                        <td>${quote.carrier}${sourceLabel}</td>
                        <td>R$ ${parseFloat(quote.price).toFixed(2)}</td>
                        <td>${quote.delivery_time}</td>
                        <td>${quote.service}</td>
                    </tr>
                `;
                $('#sps-group-stacked-results').append(row);
            });
        } else {
            $('#sps-group-stacked-results').html('<tr><td colspan="4">Nenhuma cotação encontrada para este grupo.</td></tr>');
        }
        
        // Exibir informações da melhor opção para grupos
        if (allGroupOptions.length > 0) {
            const bestGroupOption = allGroupOptions[0]; // Já ordenado por preço
            
            // Exibir informações fixas da melhor opção
            showBestOptionInfo(bestGroupOption, '.sps-group-simulation-success');
        }
    }
});