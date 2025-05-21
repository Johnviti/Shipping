jQuery(function($) {
    // Add carrier logos to shipping methods
    function addCarrierLogos() {
        $('ul#shipping_method li').each(function() {
            var $method = $(this);
            var methodId = $method.find('input').val();
            
            // Check if it's a Central do Frete method
            if (methodId && methodId.indexOf('central_do_frete') === 0) {
                // Get carrier data from data attribute
                var $label = $method.find('label');
                var carrierId = $label.data('carrier-id');
                var carrierLogo = $label.data('carrier-logo');
                var carrierName = $label.data('carrier-name');
                var deliveryTime = $label.data('delivery-time');
                
                // Add logo if not already added
                if (carrierLogo && $method.find('.central-do-frete-carrier-logo').length === 0) {
                    $label.prepend('<img src="' + carrierLogo + '" class="central-do-frete-carrier-logo" alt="' + carrierName + '" />');
                }
                
                // Add delivery time info if not already added
                if (deliveryTime && $method.find('.central-do-frete-carrier-info').length === 0) {
                    $label.append('<div class="central-do-frete-carrier-info">' +
                        '<span class="central-do-frete-delivery-time">' + deliveryTime + ' dias úteis</span>' +
                    '</div>');
                }
            }
        });
    }
    
    // Run on page load
    addCarrierLogos();
    
    // Run when shipping methods are updated
    $(document.body).on('updated_shipping_method', function() {
        addCarrierLogos();
    });
    
    // Run when checkout is updated
    $(document.body).on('updated_checkout', function() {
        addCarrierLogos();
    });
});