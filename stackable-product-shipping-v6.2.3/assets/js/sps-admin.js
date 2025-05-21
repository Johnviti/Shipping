
jQuery(document).ready(function($){
    function initSelect2(el){
        el.select2({
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function (params) { return { action: 'sps_search_products', term: params.term }; },
                processResults: function (data) { return { results: data }; },
                cache: true
            },
            placeholder: 'Selecione um produto',
            minimumInputLength: 2
        });
    }

    // Initialize existing selects
    initSelect2($('.sps-product-select'));

    // Add new row
    $(document).on('click', '#sps-add-product', function(e){
        e.preventDefault();
        var row = '<tr class="sps-product-row">'+
                  '<td><select name="sps_product_id[]" class="sps-product-select" style="width:100%"></select></td>'+
                  '<td><input type="number" name="sps_product_quantity[]" class="small-text" value="1" min="1" required></td>'+
                  '<td><button type="button" class="button sps-remove-product"><span class="dashicons dashicons-trash"></span></button></td>'+
                  '</tr>';
        $('#sps-products-table tbody').append(row);
        initSelect2($('#sps-products-table tbody tr:last .sps-product-select'));
    });

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
