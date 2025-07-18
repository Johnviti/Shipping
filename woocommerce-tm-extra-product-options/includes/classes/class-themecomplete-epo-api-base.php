<?php
/**
 * Extra Product Options API class
 *
 * @package Extra Product Options/Classes
 * @version 6.4
 */

defined( 'ABSPATH' ) || exit;

/**
 * Extra Product Options API class
 *
 * @package Extra Product Options/Classes
 * @version 6.4
 */
final class THEMECOMPLETE_EPO_API_Base {

	/**
	 * Cache for the has_options method
	 *
	 * @var array<mixed>
	 */
	private $cpf = [];

	/**
	 * The single instance of the class
	 *
	 * @var THEMECOMPLETE_EPO_API_Base|null
	 * @since 1.0
	 */
	protected static $instance = null;

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @since 1.0
	 * @return THEMECOMPLETE_EPO_API_Base
	 * @static
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class Constructor
	 *
	 * @since 1.0
	 */
	public function __construct() {
	}

	/**
	 * Returns a list of image extensions
	 *
	 * @return array<string>
	 */
	public function get_image_extensions() {
		return [ 'jpg', 'jpeg', 'jpe', 'gif', 'png', 'bmp', 'webp', 'tiff', 'tif', 'svg' ];
	}

	/**
	 * Checks if the product with id=$product_id has options
	 *
	 * @param mixed $product_id The product id.
	 *
	 * @return array<mixed>|boolean
	 */
	public function has_options( $product_id = 0 ) {
		if ( $product_id && 0 !== $product_id && is_numeric( $product_id ) ) {
			$post_id = $product_id;
		} else {
			$post_id = get_the_ID();
		}

		$post_id = absint( $post_id );

		if ( ! empty( $this->cpf[ $post_id ] ) ) {
			return $this->cpf[ $post_id ];
		}

		$has_epo         = false;
		$cpf_price_array = THEMECOMPLETE_EPO()->get_product_tm_epos( $post_id, '', false, true );

		if ( $cpf_price_array ) {
			$global_price_array = $cpf_price_array['global'];
			$local_price_array  = $cpf_price_array['local'];
			if ( empty( $global_price_array ) && empty( $local_price_array ) ) {
				return false;
			}

			$has_epo = [];
			if ( ! empty( $global_price_array ) ) {
				if ( isset( $cpf_price_array['raw_epos'] ) && is_array( $cpf_price_array['raw_epos'] ) ) {
					if ( 1 === count( $cpf_price_array['raw_epos'] ) && 'variations' === join( '', $cpf_price_array['raw_epos'] ) ) {
						$has_epo['variations'] = true;
					} else {
						if ( in_array( 'variations', $cpf_price_array['raw_epos'], true ) ) {
							$has_epo['variations'] = true;
						}
						$has_epo['global'] = true;
					}
				}
			}
			if ( ! empty( $local_price_array ) ) {
				$has_epo['local'] = true;
			}

			$has_epo['variations_disabled'] = $cpf_price_array['variations_disabled'];

		}

		$this->cpf[ $post_id ] = $has_epo;

		return $has_epo;
	}

	/**
	 * Checks if the array for has_options has options
	 *
	 * @param mixed $epos Options array.
	 *
	 * @return boolean
	 */
	public function is_valid_options( $epos = false ) {
		if ( false !== $epos && is_array( $epos ) && ( isset( $epos['global'] ) || isset( $epos['local'] ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if the array for has_options has options or visible styled variations
	 *
	 * @param mixed $epos Options array.
	 *
	 * @return boolean
	 */
	public function is_valid_options_or_variations( $epos = false ) {
		if ( false !== $epos && is_array( $epos ) && ( isset( $epos['global'] ) || isset( $epos['local'] ) || ( ! empty( $epos['variations'] ) && empty( $epos['variations_disabled'] ) ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns all saved options (this must be used after the 'woocommerce_init' hook) *
	 *
	 * @param integer|WC_Order|WC_Order_Item $obj The Order id or Order object or Order item object.
	 * @param string                         $option_id The option unique id or 'all'.
	 * @param string                         $return_type The type of data to return.
	 * @return array|boolean|mixed
	 */
	public function get_saved_addons_from_order( $obj, $option_id = '', $return_type = 'array' ) {
		// Check if 'woocommerce_init' hook has been executed.
		if ( 0 >= did_action( 'woocommerce_init' ) ) {
			return false;
		}

		$order_id = false;
		$order    = false;

		if ( $obj && 0 !== $obj && is_numeric( $obj ) ) {
			$order_id = $obj;
		} elseif ( $obj instanceof WC_Order ) {
			$order_id = $obj->get_id();
		} elseif ( $obj instanceof WC_Order_Item ) {
			$order    = $obj->get_order();
			$order_id = $order->get_id();
		}

		if ( is_bool( $order ) ) {
			$order = wc_get_order( $order_id );
			if ( is_bool( $order ) ) {
				return false;
			}
		}

		$order_currency = $order->get_currency();
		$mt_prefix      = $order_currency;

		/**
		 * Line items
		 *
		 * @var WC_Order_Item_Product[] $line_items
		 */
		$line_items = apply_filters( 'wc_epo_order_line_items', $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', 'line_item' ) ), $order );
		$all_epos   = [];

		foreach ( $line_items as $item_id => $item ) {
			$_product    = themecomplete_get_product_from_item( $item, $order );
			$item_meta   = themecomplete_get_order_item_meta( $item_id, '', false );
			$order_taxes = $order->get_taxes();

			$check_box_html = ( version_compare( WC()->version, '2.6', '>=' ) ) ? '' : '<td class="check-column">&nbsp;</td>';

			$has_epo = is_array( $item_meta )
					&& isset( $item_meta['_tmcartepo_data'] )
					&& isset( $item_meta['_tmcartepo_data'][0] )
					&& isset( $item_meta['_tm_epo'] );

			$has_fee = is_array( $item_meta )
					&& isset( $item_meta['_tmcartfee_data'] )
					&& isset( $item_meta['_tmcartfee_data'][0] );

			$wpml_translation_by_id = [];
			if ( $has_epo || $has_fee ) {
				$current_product_id  = $item->get_product_id();
				$original_product_id = intval( THEMECOMPLETE_EPO_WPML()->get_original_id( $current_product_id, 'product' ) );
				if ( THEMECOMPLETE_EPO_WPML()->get_lang() === THEMECOMPLETE_EPO_WPML()->get_default_lang() && $original_product_id !== $current_product_id ) {
					$current_product_id = $original_product_id;
				}
				$wpml_translation_by_id = THEMECOMPLETE_EPO_WPML()->get_wpml_translation_by_id( $current_product_id );
			}

			if ( $has_epo ) {
				$epos = themecomplete_maybe_unserialize( $item_meta['_tmcartepo_data'][0] );

				$all_epos[ $item_id ] = $this->get_epo_data_from_order(
					$epos,
					$option_id,
					[
						'mt_prefix'              => $mt_prefix,
						'wpml_translation_by_id' => $wpml_translation_by_id,
						'item'                   => $item,
						'item_meta'              => $item_meta,
						'product'                => $_product,
					],
				);
			}

			if ( $has_fee ) {
				$epos = themecomplete_maybe_unserialize( $item_meta['_tmcartfee_data'][0] );
				if ( isset( $epos[0] ) ) {
					$epos = $epos[0];
				} else {
					$epos = false;
				}

				$all_epos[ $item_id ] = $this->get_epo_data_from_order(
					$epos,
					$option_id,
					[
						'mt_prefix'              => $mt_prefix,
						'wpml_translation_by_id' => $wpml_translation_by_id,
						'item'                   => $item,
						'item_meta'              => $item_meta,
						'product'                => $_product,
					],
				);
			}
		}

		if ( 'json' === $return_type ) {
			$all_epos = wp_json_encode( $all_epos );
		} elseif ( 'array_multi' === $return_type ) {
			$all_epos = array_map(
				function ( $entry ) {
					return ( $entry[ key( $entry ) ] );
				},
				$all_epos
			);
		}

		return $all_epos;
	}

	/**
	 * Parse epo item data in order
	 *
	 * @param array<mixed> $epo The epo item.
	 * @param array<mixed> $args Array of arguments.
	 * @param boolean      $restrictions If true, restricts output based on the
	 *                     control panel settings.
	 * @return array<mixed>
	 * @since 6.4.3
	 */
	public function parse_epo_order_data( $epo = [], $args = [], $restrictions = true ) {
		// Don't display dynamic calculation elements.
		if ( isset( $epo['dynamic'] ) ) {
			$epo['price']    = '';
			$epo['quantity'] = '';

			if ( 'hidden' === $epo['hidelabelinorder'] && 'hidden' === $epo['hidevalueinorder'] ) {
				return [];
			}
		}

		$wpml_translation_by_id = [];
		if ( isset( $args['wpml_translation_by_id'] ) ) {
			$wpml_translation_by_id = $args['wpml_translation_by_id'];
		}

		$item = false;
		if ( isset( $args['item'] ) ) {
			$item = $args['item'];
		}

		$item_id = false;
		if ( isset( $args['item_id'] ) ) {
			$item_id = $args['item_id'];
		} elseif ( false !== $item ) {
			$item_id = $item->get_id();
		}

		$item_meta = [];
		if ( isset( $args['item_meta'] ) ) {
			$item_meta = $args['item_meta'];
		} elseif ( false !== $item_id ) {
			$item_meta = themecomplete_get_order_item_meta( $item_id, '', false );
		}

		$product_id = false;
		if ( isset( $args['product_id'] ) ) {
			$product_id = $args['product_id'];
		} elseif ( false !== $item ) {
			$product_id = $item->get_product_id();
		}

		$order = false;
		if ( isset( $args['order'] ) ) {
			$order = $args['order'];
		} elseif ( false !== $item ) {
			$order = $item->get_order();
		}

		$order_currency = false;
		if ( isset( $args['order_currency'] ) ) {
			$order_currency = $args['order_currency'];
		} elseif ( false !== $order ) {
			$order_currency = $order->get_currency();
		}

		$currency_arg = [];
		if ( isset( $args['currency'] ) ) {
			$currency_arg = $args['currency'];
		} elseif ( false !== $order_currency ) {
			$currency_arg = [ 'currency' => $order_currency ];
		}

		$mt_prefix = '';
		if ( isset( $args['mt_prefix'] ) ) {
			$mt_prefix = $args['mt_prefix'];
		} elseif ( false !== $order_currency ) {
			$mt_prefix = $order_currency;
		}

		$product = false;
		if ( isset( $args['product'] ) ) {
			$product = $args['product'];
		} else {
			$product = themecomplete_get_product_from_item( $item, $order );
		}

		if ( $epo && is_array( $epo ) && isset( $epo['section'] ) ) {
			// Quantity check.
			if ( ! isset( $epo['quantity'] ) ) {
				$epo['quantity'] = 1;
			}
			if ( '' !== $epo['quantity'] ) {
				if ( $epo['quantity'] < 1 ) {
					$epo['quantity'] = 1;
				}
				$epo['quantity'] = floatval( $epo['quantity'] );
			}

			// Price check.
			// Price should already be in the converted currency.
			// The below code is for reference only!
			/*$new_currency = false;
			if ( isset( $epo['price_per_currency'] ) && isset( $mt_prefix ) ) {
				$_current_currency_prices = $epo['price_per_currency'];
				if ( '' !== $mt_prefix
					&& '' !== $_current_currency_prices
					&& is_array( $_current_currency_prices )
					&& isset( $_current_currency_prices[ $mt_prefix ] )
					&& '' !== $_current_currency_prices[ $mt_prefix ]
				) {
					$new_currency = true;
					$epo['price'] = $_current_currency_prices[ $mt_prefix ];
				}
			}
			if ( ! $new_currency ) {
				$type = THEMECOMPLETE_EPO()->get_saved_element_price_type( $epo );

				$epo['price'] = apply_filters( 'wc_epo_get_current_currency_price', $epo['price'], $type, null, $order_currency );
			}*/
			if ( '' !== $epo['price'] ) {
				$epo['price'] = floatval( $epo['price'] );
			}

			// Name (label) check.
			if ( isset( $wpml_translation_by_id[ $epo['section'] ] ) ) {
				$epo['name'] = $wpml_translation_by_id[ $epo['section'] ];
			}
			if ( isset( $epo['repeater'] ) && isset( $epo['key_id'] ) ) {
				$epo['name'] = (int) ( $epo['key_id'] + 1 ) . '. ' . $epo['name'];
			}
			// Normal (local) mode.
			if ( ! isset( $epo['price_per_currency'] ) && taxonomy_exists( $epo['name'] ) ) {
				$epo['name'] = wc_attribute_label( $epo['name'] );
			}
			$epo['name'] = apply_filters( 'tm_translate', $epo['name'] );

			// Value check.
			$epo['edit_value'] = $epo['value'];
			if ( isset( $wpml_translation_by_id[ 'options_' . $epo['section'] ] )
				&& is_array( $wpml_translation_by_id[ 'options_' . $epo['section'] ] )
				&& ! empty( $epo['multiple'] )
				&& ! empty( $epo['key'] )
			) {
				$pos = strrpos( $epo['key'], '_' );
				if ( false !== $pos ) {
					$av = array_values( $wpml_translation_by_id[ 'options_' . $epo['section'] ] );
					if ( isset( $av[ (int) substr( $epo['key'], $pos + 1 ) ] ) ) {
						$epo['value'] = $av[ (int) substr( $epo['key'], $pos + 1 ) ];
					}
				}
			}
			$original_value = $epo['value'];
			$epo['value']   = apply_filters( 'tm_translate', $epo['value'] );

			$override = isset( $epo['element'] ) && isset( $epo['element']['type'] ) && ( 'upload' === $epo['element']['type'] || 'multiple_file_upload' === $epo['element']['type'] );
			if ( $restrictions && empty( $args['backend_order'] ) ) {
				$epo['value'] = THEMECOMPLETE_EPO_ORDER()->display_meta_value( $epo['value'], $override, 'order' );
			}
			$epo['value'] = apply_filters( 'wc_epo_enable_shortocde', $epo['value'], $epo['value'], false );

			if ( ! empty( $epo['multiple_values'] ) ) {
				$display_value_array = explode( $epo['multiple_values'], $epo['value'] );
				$display_value       = '';
				foreach ( $display_value_array as $d => $dv ) {
					$display_value .= '<span class="cpf-data-on-cart"><span class="cpf-data-value">' . $dv . '</span></span>';
				}
				$epo['value'] = $display_value;
			}

			if ( isset( $epo['element'] ) && isset( $epo['element']['type'] ) && ( 'upload' === $epo['element']['type'] || 'multiple_file_upload' === $epo['element']['type'] ) ) {
				if ( ! $restrictions || ( 'no' === THEMECOMPLETE_EPO_DATA_STORE()->get( 'tm_epo_show_hide_uploaded_file_url_order' ) && 'yes' === THEMECOMPLETE_EPO_DATA_STORE()->get( 'tm_epo_show_upload_image_replacement_order' ) ) ) {
					add_filter( 'upload_mimes', [ THEMECOMPLETE_EPO(), 'upload_mimes_trick' ] );
					$check = wp_check_filetype( $original_value );
					remove_filter( 'upload_mimes', [ THEMECOMPLETE_EPO(), 'upload_mimes_trick' ] );
					if ( ! empty( $check['ext'] ) ) {
						$image_exts = $this->get_image_extensions();
						if ( in_array( $check['ext'], $image_exts, true ) ) {

							global $pagenow, $post;
							$is_edit_order_screen = false;
							if ( is_admin() ) {
								if ( 'post.php' === $pagenow && isset( $post->post_type ) ) {
									if ( 'shop_order' === $post->post_type || 'shop_order_placehold' === $post->post_type ) {
										$is_edit_order_screen = true;
									}
								}
								if ( 'admin.php' === $pagenow && isset( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
									$id_post = get_post( absint( wp_unslash( $_GET['id'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
									if ( 'shop_order' === $id_post->post_type || 'shop_order_placehold' === $id_post->post_type ) {
										$is_edit_order_screen = true;
									}
								}
							}

							$style = '';
							if ( ! $is_edit_order_screen && ! is_checkout() ) {
								$style = apply_filters( 'wc_epo_img_on_order_style', ' style="vertical-align: middle;padding: 10px 0 0 10px;max-width: 50px;height: auto;background: none;ouline: none;border: none;box-shadow: none;"' );
							}

							$display_other_value_only = '';
							if ( $is_edit_order_screen || is_checkout() ) {
								$display_other_value_only = '<span class="cpf-img-on-order">';
							}
							// The | character is used to implode files on \includes\fields\class-themecomplete-epo-fields-multiple-file-upload.php .
							$files = explode( '|', $original_value );
							foreach ( $files as $file ) {
								if ( $is_edit_order_screen || is_checkout() ) {
									$display_other_value_only .= '<a download href="' . esc_url( $file ) . '">';
								}
								$display_other_value_only .= '<img' . apply_filters( 'wc_epo_img_on_order_applied_style', $style ) . ' alt="' . esc_attr( wp_strip_all_tags( $epo['name'] ) ) . '" class="attachment-shop_thumbnail wp-post-image epo-option-image epo-upload-image" src="' . apply_filters( 'tm_image_url', $file ) . '"></a>';
								if ( $is_edit_order_screen || is_checkout() ) {
									$display_other_value_only .= '</a>';
								}
							}

							if ( $is_edit_order_screen || is_checkout() ) {
								$display_other_value_only .= '</span>';
							}

							$display_value = $display_other_value_only;

							$epo['value'] = $display_value;
						}
					}
				} elseif ( 'yes' === THEMECOMPLETE_EPO_DATA_STORE()->get( 'tm_epo_show_hide_uploaded_file_url_order' ) ) {
					$files = explode( '|', $original_value );

					$display_other_value_only = [];
					foreach ( $files as $file ) {
						$file_value = THEMECOMPLETE_EPO_ORDER()->display_meta_value( apply_filters( 'tm_image_url', $file ), 0, 'order' );
						if ( 1 < count( $files ) ) {
							$display_other_value_only[] = '<span class="cpf-data-on-cart"><span class="cpf-data-value">' . $file_value . '</span></span>';
						} else {
							$display_other_value_only[] = $file_value;
						}
					}
					$display_other_value_only = implode( '', $display_other_value_only );

					$display_value = $display_other_value_only;

					$epo['value'] = $display_value;
				} elseif ( 'no' === THEMECOMPLETE_EPO_DATA_STORE()->get( 'tm_epo_show_hide_uploaded_file_url_order' ) ) {
					$files = explode( '|', $original_value );

					$display_other_value_only = [];
					foreach ( $files as $file ) {
						$file_value = THEMECOMPLETE_EPO_ORDER()->display_meta_value( apply_filters( 'tm_image_url', $file ), 2, 'order' );
						if ( 1 < count( $files ) ) {
							$display_other_value_only[] = '<span class="cpf-data-on-cart"><span class="cpf-data-value">' . $file_value . '</span></span>';
						} else {
							$display_other_value_only[] = $file_value;
						}
					}
					$display_other_value_only = implode( '', $display_other_value_only );

					$display_value = $display_other_value_only;

					$epo['value'] = $display_value;
				}
			}

			if ( empty( $args['backend_order'] ) ) {
				$epovalue = '';
				if ( isset( $product_id ) && ( ! $restrictions || 'no' === THEMECOMPLETE_EPO_DATA_STORE()->get( 'tm_epo_hide_options_prices_in_cart' ) && ! empty( $epo['price'] ) ) ) {
					$_product   = wc_get_product( $product_id );
					$price      = $epo['price'] / $epo['quantity'];
					$tax_string = '';

					// This check is need in case the product is deleted.
					if ( $_product ) {
						$taxable = $_product->is_taxable();
						// Taxable.
						if ( $taxable ) {
							$tax_display_cart = get_option( 'woocommerce_tax_display_cart' );

							if ( 'excl' === $tax_display_cart ) {
								if ( $order && themecomplete_order_get_att( $order, 'cart_tax' ) > 0 && wc_prices_include_tax() ) {
									$tax_string = ' <small>' . apply_filters( 'wc_epo_ex_tax_or_vat_string', WC()->countries->ex_tax_or_vat() ) . '</small>';
								}
								if ( (float) 0 !== floatval( $price ) ) {
									$price = (float) themecomplete_get_price_excluding_tax(
										$_product,
										[
											'qty'   => 10000,
											'price' => $price,
										]
									) / 10000;
								}
							} else {
								if ( $order && themecomplete_order_get_att( $order, 'cart_tax' ) > 0 && ! wc_prices_include_tax() ) {
									$tax_string = ' <small>' . apply_filters( 'inc_tax_or_vat', WC()->countries->inc_tax_or_vat() ) . '</small>';
								}
								if ( (float) 0 !== floatval( $price ) ) {
									$price = (float) themecomplete_get_price_including_tax(
										$_product,
										[
											'qty'   => 10000,
											'price' => $price,
										]
									) / 10000;
								}
							}
						}
					}
					$epovalue .= ' ' . ( ( apply_filters( 'epo_can_show_order_price', true, $item_meta ) ) ? ( wc_price( $price, $currency_arg ) . $tax_string ) : '' );
				}
				if ( ( 'yes' === THEMECOMPLETE_EPO_DATA_STORE()->get( 'tm_epo_global_quantity_one' ) && ! empty( $epo['quantity_selector'] ) ) || $epo['quantity'] > 1 ) {
					$epovalue .= ' &times; ' . $epo['quantity'];
				}

				$epovalue = apply_filters( 'wc_epo_price_qty_in_order', $epovalue, $epo['price'], $epo, $item, $item_id, $order );

				if ( '' !== $epovalue && ! is_array( $epo['value'] ) && ( ( ! empty( $epo['hidevalueinorder'] ) && 'price' === $epo['hidevalueinorder'] ) || empty( $epo['hidevalueinorder'] ) ) ) {
					$epo['value'] .= ' <small>' . $epovalue . '</small>';
				}
			}

			$epo['value'] = THEMECOMPLETE_EPO_HELPER()->entity_decode( $epo['value'] );
			$epo['value'] = THEMECOMPLETE_EPO_HELPER()->recursive_implode( $epo['value'], THEMECOMPLETE_EPO_DATA_STORE()->get( 'tm_epo_multiple_separator_cart_text' ) );

			if ( $restrictions && 'yes' === THEMECOMPLETE_EPO_DATA_STORE()->get( 'tm_epo_strip_html_from_emails' ) ) {
				$epo['value'] = wp_strip_all_tags( $epo['value'] );
			} elseif ( ! $restrictions || 'no' === THEMECOMPLETE_EPO_DATA_STORE()->get( 'tm_epo_strip_html_from_emails' ) ) {
				if ( ! empty( $epo['use_images'] ) && ! empty( $epo['images'] ) && ( ! $restrictions || 'yes' === THEMECOMPLETE_EPO_DATA_STORE()->get( 'tm_epo_show_image_replacement' ) ) ) {
					$display_value = '<span class="cpf-img-on-order"><img alt="'
									. esc_attr( wp_strip_all_tags( $epo['name'] ) ) . '" class="attachment-shop_thumbnail wp-post-image epo-option-image" src="'
									. apply_filters( 'tm_image_url', $epo['images'] )
									. '" /></span>';
					$epo['value']  = $display_value . $epo['value'];
				} elseif ( ! empty( $epo['use_colors'] ) && ! empty( $epo['color'] ) && ( ! $restrictions || 'yes' === THEMECOMPLETE_EPO_DATA_STORE()->get( 'tm_epo_show_image_replacement' ) ) ) {
					$color_hex     = $epo['color'];
					$color_no_hash = $epo['color'];
					if ( 'transparent' !== $color_hex ) {
						$color_hex     = themecomplete_sanitize_hex_color( $color_hex );
						$color_no_hash = themecomplete_sanitize_hex_color_no_hash( $color_no_hash );
					}
					$display_value = '<span class="cpf-colors-on-cart"><span class="cpf-color-on-cart backgroundcolor'
									. esc_attr( (string) $color_no_hash ) . '"></span> '
									. '</span>';
					$epo['value']  = $display_value . $epo['value'];
					THEMECOMPLETE_EPO_DISPLAY()->add_inline_style( '.backgroundcolor' . esc_attr( (string) $color_no_hash ) . '{background-color:' . esc_attr( (string) $color_hex ) . ';}' );
				}
			}

			if ( isset( $epo['element'] ) && 'textarea' === $epo['element']['type'] ) {
				$epo_value = trim( $epo['value'] );
				$epo_value = str_replace( [ "\r\n", "\r" ], "\n", $epo_value );
				$epo_value = preg_replace( "/\n\n+/u", "\n\n", $epo_value );
				$epo_value = array_map( 'wc_clean', explode( "\n", $epo_value ) );
				$epo_value = array_map(
					function ( $value ) {
						if ( is_array( $value ) ) {
							$value = $value[0];
						}
						return strval( $value );
					},
					$epo_value
				);
				$epo_value = implode( "\r\n", $epo_value );

				$epo['value'] = $epo_value;
			}

			if ( false !== $item && ! empty( $item_meta ) ) {
				$is_dnmpbq = isset( $epo['dnmpbq'] ) ? $epo['dnmpbq'] : '';
				if ( empty( $is_dnmpbq ) && '' !== $epo['quantity'] ) {
					$epo_quantity = sprintf( '%s <small>(%s &times; %s)</small>', $epo['quantity'] * (float) $item_meta['_qty'][0], $epo['quantity'], (float) $item_meta['_qty'][0] );
				} else {
					$epo_quantity = $epo['quantity'];
				}
				$epo_quantity         = apply_filters( 'wc_epo_html_tm_epo_order_item_epo_quantity', $epo_quantity, $epo['quantity'], $item, $product );
				$epo['quantity_html'] = $epo_quantity;
			}
		}

		return $epo;
	}

	/**
	 * Return all epo data that is saved on the order
	 *
	 * @param array<mixed> $epos The saved epo data taken from an order.
	 * @param string       $option_id  The option unique id or 'all'.
	 * @param array<mixed> $args Array of extra arguments.
	 * @return array<mixed>
	 */
	public function get_epo_data_from_order( $epos = [], $option_id = 'all', $args = [] ) {
		$all_epos = [];
		if ( $epos && is_array( $epos ) ) {
			if ( isset( $args['item'] ) ) {
				$item = $args['item'];
			}
			if ( isset( $args['item_meta'] ) ) {
				$item_meta = $args['item_meta'];
			}
			if ( isset( $args['product'] ) ) {
				$_product = $args['product'];
			}
			foreach ( $epos as $key => $epo ) {
				if ( $epo && is_array( $epo ) ) {
					if ( $epo['section'] !== $option_id && 'all' !== $option_id ) {
						continue;
					}
					$epo = $this->parse_epo_order_data(
						$epo,
						$args,
					);

					$all_epos[ $key ] = $epo;
				}
			}
		}

		return $all_epos;
	}

	/**
	 * This is mainly used for the WP all Export plugin as a custom function
	 *
	 * @param array<mixed> $epos The saved epo data taken from an order.
	 * @param string       $option_id  The option unique id or 'all'.
	 * @param string       $output_format How the data should be returned.
	 * @param string       $mt_prefix The order currency.
	 * @param integer      $product_id The product if associated with the saved $epos if known.
	 * @return array<mixed>|string|false
	 */
	public function get_epos( $epos = [], $option_id = 'all', $output_format = 'array', $mt_prefix = '', $product_id = 0 ) {
		$epos = themecomplete_maybe_unserialize( $epos );

		$wpml_translation_by_id = [];

		if ( ! empty( $product_id ) ) {
			$current_product_id  = $product_id;
			$original_product_id = intval( THEMECOMPLETE_EPO_WPML()->get_original_id( $current_product_id, 'product' ) );
			if ( THEMECOMPLETE_EPO_WPML()->get_lang() === THEMECOMPLETE_EPO_WPML()->get_default_lang() && $original_product_id !== $current_product_id ) {
				$current_product_id = $original_product_id;
			}
			$wpml_translation_by_id = THEMECOMPLETE_EPO_WPML()->get_wpml_translation_by_id( $current_product_id );
		}

		$all_epos = $this->get_epo_data_from_order(
			$epos,
			$option_id,
			[
				'mt_prefix'              => $mt_prefix,
				'wpml_translation_by_id' => $wpml_translation_by_id,
			],
		);

		if ( 'json' === $output_format ) {
			$all_epos = wp_json_encode( $all_epos );
		} elseif ( 'implode' === $output_format ) {
			$all_epos = THEMECOMPLETE_EPO_HELPER()->recursive_implode( $all_epos, ', ', ':' );
		} elseif ( 'implode_space' === $output_format ) {
			$all_epos = THEMECOMPLETE_EPO_HELPER()->recursive_implode( $all_epos, ' ', ':' );
		}

		return $all_epos;
	}

	/**
	 * Returns all saved options (this must be used after the 'woocommerce_init' hook) *
	 *
	 * @param integer $order_id The Order id.
	 *
	 * @return array|boolean|mixed
	 * @deprecated 6.4.3 Keep for backward compatibility. Use get_saved_addons_from_order() instead.
	 */
	public function get_all_options( $order_id ) {
		return $this->get_saved_addons_from_order( $order_id );
	}

	/**
	 * Undocumented function
	 *
	 * @param mixed        $item The order item.
	 * @param array<mixed> $item_meta The item meta.
	 * @param string       $option_id  The option unique id or 'all'.
	 * @param mixed        $_product The product object.
	 * @param string       $mt_prefix The order currency.
	 * @param array<mixed> $wpml_translation_by_id The translated options values.
	 * @return array<mixed>
	 * @deprecated 6.4.2 Keep for backward compatibility. Use get_epo_data_from_order() instead.
	 */
	public function get_epos_data( $item = [], $item_meta = [], $option_id = 'all', $_product = false, $mt_prefix = '', $wpml_translation_by_id = [] ) {
		$epos = themecomplete_maybe_unserialize( $item_meta['_tmcartepo_data'][0] );
		$args = [
			'item'      => $item,
			'item_meta' => $item_meta,
			'product'   => $_product,
		];
		return $this->get_epo_data_from_order(
			$epos,
			$option_id,
			[
				'mt_prefix'              => $mt_prefix,
				'wpml_translation_by_id' => $wpml_translation_by_id,
				'item'                   => $item,
				'item_meta'              => $item_meta,
				'product'                => $_product,
			],
		);
	}

	/**
	 * Returns a saved option
	 * (this must be used after the 'woocommerce_init' hook)
	 *
	 * @param integer $order_id The Order id.
	 * @param string  $option_id The option unique id or 'all'.
	 * @param string  $return_type The type of data to return.
	 * @return array|boolean|mixed
	 * @deprecated 6.4.3 Keep for backward compatibility. Use get_saved_addons_from_order() instead.
	 */
	public function get_option( $order_id, $option_id = '', $return_type = 'array' ) {
		return $this->get_saved_addons_from_order( $order_id, $option_id, $return_type );
	}
}
