( function( $ ) {
	'use strict';

	$( window ).on( 'tc-epo-after-update', function( e, o ) {
		if ( o && o.data && o.epo && o.totals_holder ) {
			$( document ).trigger( 'yith_wapo_product_price_updated', [ o.epo.product_total_price - o.epo.cart_fee_options_total_price ] );
		}
	} );
}( window.jQuery ) );
