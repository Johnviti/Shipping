jQuery(document).ready(function($) {
    let packageIndex = $('#cdp-packages-list .cdp-package-row').length;
    
    // Adicionar novo pacote
    $('#cdp-add-package').on('click', function(e) {
        e.preventDefault();
        
        const newPackageHtml = `
            <div class="cdp-package-row" data-index="${packageIndex}">
                <h4>Pacote ${packageIndex + 1}</h4>
                <input type="hidden" name="cdp_package_ids[${packageIndex}]" value="">
                
                <div class="cdp-package-fields">
                    <div class="cdp-package-field">
                        <label>Nome do Pacote</label>
                        <input type="text" 
                               name="cdp_package_names[${packageIndex}]" 
                               value="Pacote ${packageIndex + 1}" 
                               placeholder="Ex: Pacote Principal, Acessórios, etc.">
                    </div>
                    
                    <div class="cdp-package-field">
                        <label>Largura (cm)</label>
                        <input type="number" 
                               name="cdp_package_widths[${packageIndex}]" 
                               value="" 
                               step="0.01" 
                               min="0" 
                               placeholder="0.00">
                    </div>
                    
                    <div class="cdp-package-field">
                        <label>Altura (cm)</label>
                        <input type="number" 
                               name="cdp_package_heights[${packageIndex}]" 
                               value="" 
                               step="0.01" 
                               min="0" 
                               placeholder="0.00">
                    </div>
                    
                    <div class="cdp-package-field">
                        <label>Comprimento (cm)</label>
                        <input type="number" 
                               name="cdp_package_lengths[${packageIndex}]" 
                               value="" 
                               step="0.01" 
                               min="0" 
                               placeholder="0.00">
                    </div>
                    
                    <div class="cdp-package-field">
                        <label>Peso (kg)</label>
                        <input type="number" 
                               name="cdp_package_weights[${packageIndex}]" 
                               value="" 
                               step="0.001" 
                               min="0" 
                               placeholder="0.000">
                    </div>
                    
                    <div class="cdp-package-field">
                        <a href="#" class="cdp-remove-package" title="Remover Pacote">
                            <span class="dashicons dashicons-trash"></span>
                        </a>
                    </div>
                </div>
            </div>
        `;
        
        $('#cdp-packages-list').append(newPackageHtml);
        packageIndex++;
        
        // Reindexar títulos dos pacotes
        reindexPackages();
    });
    
    // Remover pacote
    $(document).on('click', '.cdp-remove-package', function(e) {
        e.preventDefault();
        
        if ($('#cdp-packages-list .cdp-package-row').length > 1) {
            $(this).closest('.cdp-package-row').remove();
            reindexPackages();
        } else {
            alert('Deve haver pelo menos um pacote configurado.');
        }
    });
    
    // Função para reindexar pacotes
    function reindexPackages() {
        $('#cdp-packages-list .cdp-package-row').each(function(index) {
            const $row = $(this);
            
            // Atualizar título
            $row.find('h4').text('Pacote ' + (index + 1));
            
            // Atualizar atributos data-index
            $row.attr('data-index', index);
            
            // Atualizar nomes dos campos
            $row.find('input[name^="cdp_package_ids"]').attr('name', `cdp_package_ids[${index}]`);
            $row.find('input[name^="cdp_package_names"]').attr('name', `cdp_package_names[${index}]`);
            $row.find('input[name^="cdp_package_widths"]').attr('name', `cdp_package_widths[${index}]`);
            $row.find('input[name^="cdp_package_heights"]').attr('name', `cdp_package_heights[${index}]`);
            $row.find('input[name^="cdp_package_lengths"]').attr('name', `cdp_package_lengths[${index}]`);
            $row.find('input[name^="cdp_package_weights"]').attr('name', `cdp_package_weights[${index}]`);
            
            // Atualizar placeholder do nome se estiver vazio
            const $nameInput = $row.find('input[name^="cdp_package_names"]');
            if (!$nameInput.val() || $nameInput.val().match(/^Pacote \d+$/)) {
                $nameInput.val('Pacote ' + (index + 1));
            }
            
            // Mostrar/ocultar botão de remover
            const $removeBtn = $row.find('.cdp-remove-package');
            if (index === 0) {
                $removeBtn.hide();
            } else {
                $removeBtn.show();
            }
        });
        
        // Atualizar packageIndex
        packageIndex = $('#cdp-packages-list .cdp-package-row').length;
    }
    
    // Validação antes de salvar
    $('form#post').on('submit', function(e) {
        let hasValidPackage = false;
        let hasInvalidPackage = false;
        
        $('#cdp-packages-list .cdp-package-row').each(function() {
            const $row = $(this);
            const width = parseFloat($row.find('input[name^="cdp_package_widths"]').val()) || 0;
            const height = parseFloat($row.find('input[name^="cdp_package_heights"]').val()) || 0;
            const length = parseFloat($row.find('input[name^="cdp_package_lengths"]').val()) || 0;
            const weight = parseFloat($row.find('input[name^="cdp_package_weights"]').val()) || 0;
            
            if (width > 0 || height > 0 || length > 0 || weight > 0) {
                hasValidPackage = true;
                
                // Verificar se todas as dimensões estão preenchidas quando pelo menos uma está
                if ((width > 0 || height > 0 || length > 0) && (width <= 0 || height <= 0 || length <= 0)) {
                    hasInvalidPackage = true;
                    $row.find('input[name^="cdp_package_widths"], input[name^="cdp_package_heights"], input[name^="cdp_package_lengths"]')
                        .css('border-color', '#dc3232');
                }
            }
        });
        
        if (hasInvalidPackage) {
            e.preventDefault();
            alert('Por favor, preencha todas as dimensões (largura, altura e comprimento) para cada pacote que possui pelo menos uma dimensão preenchida.');
            return false;
        }
    });
    
    // Remover destaque de erro ao digitar
    $(document).on('input', 'input[name^="cdp_package_widths"], input[name^="cdp_package_heights"], input[name^="cdp_package_lengths"]', function() {
        $(this).css('border-color', '');
    });
    
    // Inicializar com reindexação
    reindexPackages();
});