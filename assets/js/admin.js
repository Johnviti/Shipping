/**
 * Scripts da página de administração do plugin WooCommerce Stackable Shipping
 */
jQuery(document).ready(function($) {
    // Mostrar/esconder explicações detalhadas quando solicitado
    $('.show-explanation').on('click', function(e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        $('#' + targetId).slideToggle();
        $(this).toggleClass('explanation-open');
        
        if ($(this).hasClass('explanation-open')) {
            $(this).text($(this).data('hide-text'));
        } else {
            $(this).text($(this).data('show-text'));
        }
    });
    
    // Futuras funcionalidades JavaScript serão adicionadas aqui
}); 