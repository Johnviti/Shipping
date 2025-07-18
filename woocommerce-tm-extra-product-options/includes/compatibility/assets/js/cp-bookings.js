( function( document, $ ) {
	'use strict';

	var TMEPOBOOKINGSJS;
	var bookingFields;
	var retrigger = 0;
	var prevPrice = 0;

	function calculateProductPrice( price, totalsHolder ) {
		var bookingPrice;
		var form = totalsHolder.data( 'tm_for_cart' );
		var cost;
		if ( form ) {
			cost = form.find( '.wc-bookings-booking-cost' );
			if ( cost.length ) {
				bookingPrice = parseFloat( cost.attr( 'data-raw-price' ) );
				if ( ! isNaN( bookingPrice ) && ! bookingPrice.isNaN ) {
					price = bookingPrice;
				} else {
					price = false;
				}
			}
		}

		return price;
	}

	// document ready
	$( function() {
		if ( ! $.epoAPI ) {
			return;
		}

		TMEPOBOOKINGSJS = window.TMEPOBOOKINGSJS || null;

		if ( ! TMEPOBOOKINGSJS ) {
			return;
		}

		bookingFields = $( '.wc-bookings-booking-form' ).find( 'input, select:not("#wc-bookings-form-start-time, #wc-bookings-form-end-time")' );

		if ( TMEPOBOOKINGSJS.wc_bookings_add_options_display_cost === 'yes' ) {
			bookingFields
				.on( 'change.tcbookings', function() {
					var isFilledOrChecked = false;
					var epos;
					var $this = $( this );

					if ( $this.val() === '' || $( '.wc-bookings-booking-cost' ).attr( 'data-raw-price' ) === '' ) {
						return;
					}

					epos = $( '.tm-epo-field.tcenabled' );

					// Check for filled text inputs, textareas, and selects
					epos.filter( 'input[type="text"], textarea, select' ).each( function( index, el ) {
						if ( $( el ).val() ) {
							isFilledOrChecked = true;
							return false;
						}
					} );

					// Check for checked checkboxes and radio buttons if not already found
					if ( ! isFilledOrChecked ) {
						epos.filter( 'input[type="checkbox"], input[type="radio"]' ).each( function( index, el ) {
							if ( $( el ).is( ':checked' ) ) {
								isFilledOrChecked = true;
								return false;
							}
						} );
					}
					if ( isFilledOrChecked ) {
						retrigger = 1;
						$( '.wc-bookings-booking-cost' ).addClass( 'tc-hidden' );
						$( 'form.cart' ).block( { message: null, overlayCSS: { background: '#fff', opacity: 0.6 } } );
					}
				} );
			$( '.tm-epo-field' )
				.on( 'change.tcbookings', function() {
					$( 'body' ).data( 'tobechanged', 1 );
				} );
			$( window ).on( 'tcEpoAfterCalculateTotals', function( e, data ) {
				var f = data.epo.form;
				var t;
				var b = bookingFields.first();
				var bd = $( 'body' ).data( 'tobechanged' );
				var isEmptyCalendarSelection = ! f.find( "[name='wc_bookings_field_start_date_day']" ).val() &&	! f.find( '#wc_bookings_field_start_date' ).val();
				var required_fields = f.find( 'input.required_for_calculation' );
				var filled = true;
				$.each( required_fields, function( index, field ) {
					var value = $( field ).val();
					if ( ! value ) {
						filled = false;
					}
				} );
				if ( isEmptyCalendarSelection || ! filled ) {
					return;
				}
				t = f.find( '#tcbookingaddonscost' );
				if ( ! t.length ) {
					f.append( '<input type="hidden" id="tcbookingaddonscost" name="tcbookingaddonscost" value="' + data.totalsObject.options_total_price + '">' );
				} else {
					t.val( data.totalsObject.options_total_price );
				}

				if ( bd && ( ( $.tmempty( data.totalsObject.options_total_price ) === false && prevPrice !== data.totalsObject.options_total_price ) || prevPrice !== data.totalsObject.options_total_price ) ) {
					prevPrice = data.totalsObject.options_total_price;
					f.block( { message: null, overlayCSS: { background: '#fff', opacity: 0.6 } } );
					b.trigger( 'change' );
				}
				$( 'body' ).removeData( 'tobechanged' );
			} );
		}
		$( document ).ajaxSuccess( function( event, request, settings ) {
			var parsedUrl = $.epoAPI.util.parseParams( settings.data );
			var n;
			if ( parsedUrl.action === 'wc_bookings_calculate_costs' || parsedUrl.action === 'wc_bookings_get_blocks' ) {
				n = $.epoAPI.util.parseJSON( request.responseText );
				$( 'form.cart' ).find( '.wc-bookings-booking-cost' ).attr( 'data-raw-price', n.raw_price );
				$( 'form.cart' ).trigger( {
					type: 'tm-epo-update'
				} );
				if ( parsedUrl.action === 'wc_bookings_get_blocks' ) {
					$( 'form.cart' ).unblock();
					$( '.wc-bookings-booking-cost' ).removeClass( 'tc-hidden' );
					retrigger = 0;
					return;
				}
				if ( retrigger ) {
					bookingFields.first().trigger( 'change' );
					retrigger = 0;
				} else {
					$( 'form.cart' ).unblock();
					$( '.wc-bookings-booking-cost' ).removeClass( 'tc-hidden' );
				}
			}
		} );
		$( document.body ).on( 'wc_booking_form_changed', function() {
			$( 'form.cart' ).trigger( {
				type: 'tm-epo-update',
				norules: 1
			} );
		} );

		function adjustTotal( total ) {
			var options_multiplier = 0;
			var found = false;
			var duration;
			var hasPersons;

			if ( TMEPOBOOKINGSJS.wc_booking_block_qty_multiplier === '1' ) {
				duration = parseInt( $( 'input#wc_bookings_field_duration, input.wc_bookings_field_duration' ).first().val(), 10 );
				if ( ! isNaN( duration ) && duration !== 0 ) {
					options_multiplier = options_multiplier + duration;
					found = true;
				}
			}

			if ( TMEPOBOOKINGSJS.wc_booking_person_qty_multiplier === '1' ) {
				found = false;
				hasPersons = $( '[id^=wc_bookings_field_persons]' );
				if ( hasPersons.length ) {
					options_multiplier = options_multiplier + hasPersons.toArray().reduce(
						function( sum, element ) {
							if ( isNaN( sum ) ) {
								sum = 0;
							}
							return sum + Number( element.value );
						}, 0
					);
					found = true;
				}
			}
			if ( found ) {
				total = total * options_multiplier;
			}

			return total;
		}

		function qtyElementForRepeaterQuantity( qty ) {
			var hasPersons = $( '[id^=wc_bookings_field_persons]' );
			if ( hasPersons.length ) {
				qty = hasPersons;
			}
			return qty;
		}

		function qtyElementForRepeaterQuantityPrevValue( val ) {
			var hasPersons = $( '[id^=wc_bookings_field_persons]' );
			if ( hasPersons.length ) {
				val = hasPersons.toArray().reduce(
					function( sum, element ) {
						if ( isNaN( sum ) ) {
							sum = 0;
						}
						return sum + Number( element.value );
					}, 0
				);
			}
			hasPersons.data( 'tm-prev-value', val );
			return val;
		}

		function qtyElementForRepeaterQuantityValue( val ) {
			var hasPersons = $( '[id^=wc_bookings_field_persons]' );
			if ( hasPersons.length ) {
				val = hasPersons.toArray().reduce(
					function( sum, element ) {
						if ( isNaN( sum ) ) {
							sum = 0;
						}
						return sum + Number( element.value );
					}, 0
				);
			}
			return val;
		}

		function getCurrentQty( qty ) {
			qty = 1;
			return qty;
		}

		$.epoAPI.addFilter( 'tc_getCurrentQty', getCurrentQty, 10, 1 );
		$.epoAPI.addFilter( 'tcAdjustTotal', adjustTotal, 10, 1 );
		$.epoAPI.addFilter( 'tcAdjustOriginalTotal', adjustTotal, 10, 1 );
		$.epoAPI.addFilter( 'tc_calculate_product_price', calculateProductPrice, 10, 2 );
		$.epoAPI.addFilter( 'tc_calculate_product_regular_price', calculateProductPrice, 10, 2 );
		$.epoAPI.addFilter( 'qtyElementForRepeaterQuantity', qtyElementForRepeaterQuantity, 10, 1 );
		$.epoAPI.addFilter( 'qtyElementForRepeaterQuantity_tm-prev-value', qtyElementForRepeaterQuantityPrevValue, 10, 1 );
		$.epoAPI.addFilter( 'qtyElementForRepeaterQuantityValue', qtyElementForRepeaterQuantityValue, 10, 1 );
	} );
}( document, window.jQuery ) );
