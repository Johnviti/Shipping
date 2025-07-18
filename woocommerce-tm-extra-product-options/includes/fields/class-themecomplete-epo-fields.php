<?php
/**
 * Field class
 *
 * @package Extra Product Options/Fields
 * @version 6.4
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field class
 *
 * This class acts as the base class
 * for the various field options.
 *
 * @package Extra Product Options/Fields
 * @version 6.4
 */
class THEMECOMPLETE_EPO_FIELDS {

	/**
	 * The product id
	 *
	 * @var float|null
	 */
	public $product_id;

	/**
	 * The element array
	 *
	 * @var array<mixed>
	 */
	public $element = [];

	/**
	 * Array of element arguments to save
	 *
	 * @var array<mixed>|null
	 */
	public $order_saved_element;

	/**
	 * The posted element name
	 *
	 * @var string|false
	 */
	public $attribute = false;

	/**
	 * The quantity name of the element
	 *
	 * @var string|null
	 */
	public $attribute_quantity;

	/**
	 * The posted element quantity
	 *
	 * @var float|array<mixed>|false
	 */
	public $posted_attribute_quantity = false;

	/**
	 * The array key of the posted element values array
	 *
	 * @var integer
	 */
	public $key_id = 0;

	/**
	 * The array key for the values of the posted element values array
	 *
	 * @var integer
	 */
	public $keyvalue_id = 0;

	/**
	 * The posted element value
	 *
	 * @var string|array<mixed>
	 */
	public $key = '';

	/**
	 * If the product has pricing, true or false
	 *
	 * @var boolean|null
	 */
	public $per_product_pricing;

	/**
	 * The product price
	 *
	 * @var float|false
	 */
	public $cpf_product_price = false;

	/**
	 * The variation id
	 *
	 * @var integer|false
	 */
	public $variation_id = false;

	/**
	 * The posted data
	 *
	 * @var array<mixed>
	 */
	public $post_data = [];

	/**
	 * Holds builder element type
	 *
	 * @var mixed
	 */
	public $holder;

	/**
	 * Holds builder element fee type
	 *
	 * @var mixed
	 */
	public $holder_cart_fees;

	/**
	 * The posted fields array
	 *
	 * @var array<mixed>
	 */
	public $epo_post_fields = [];

	/**
	 * The current loop
	 *
	 * @var integer|false
	 */
	public $loop = false;

	/**
	 * The form prefix
	 *
	 * @var string|null
	 */
	public $form_prefix;

	/**
	 * The posted html names of the normal elements
	 *
	 * @var array<mixed>
	 */
	public $tmcp_attributes = [];

	/**
	 * The posted html names of the fee elements
	 *
	 * @var array<mixed>
	 */
	public $tmcp_attributes_fee = [];

	/**
	 * The html names of the elements
	 *
	 * @var array<mixed>
	 */
	public $field_names = [];

	/**
	 * If the element has a repeater
	 *
	 * @var mixed
	 */
	public $repeater = false;

	/**
	 * Custom help data
	 * This is used in places where we need to add a custom property
	 *
	 * @var array<mixed>
	 */
	public $data_store = [];

	/**
	 * If all variables are setup
	 *
	 * @var boolean
	 */
	private $setup = false;

	/**
	 * Class Constructor
	 *
	 * @param integer|false     $product_id The product id.
	 * @param array<mixed>      $element The element array.
	 * @param boolean|false     $per_product_pricing If the product has pricing, true or false.
	 * @param float|false       $cpf_product_price The product price.
	 * @param integer|false     $variation_id The variation id.
	 * @param array<mixed>|null $post_data The posted data.
	 * @since 1.0
	 */
	public function __construct( $product_id = false, $element = [], $per_product_pricing = false, $cpf_product_price = false, $variation_id = false, $post_data = null ) {
		// @phpstan-ignore-next-line
		if ( is_null( $post_data ) && isset( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$post_data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		if ( empty( $post_data ) && isset( $_REQUEST['tcajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_data = wp_unslash( $_REQUEST ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! is_array( $post_data ) ) {
			$post_data = [];
		}
		$this->post_data = apply_filters( 'wc_epo_get_default_value_post_data', $post_data, $element );

		if ( false !== $product_id ) {
			$this->product_id          = $product_id;
			$this->element             = $element;
			$this->order_saved_element = [
				'type'       => $element['type'],
				'rules'      => $element['rules'],
				'rules_type' => $element['rules_type'],
				'_'          => [ 'price_type' => false ],
			];
			$this->per_product_pricing = $per_product_pricing;
			$this->cpf_product_price   = $cpf_product_price;
			$this->variation_id        = $variation_id;
			$this->holder              = THEMECOMPLETE_EPO()->tm_builder_elements[ $this->element['type'] ]->type;
			$this->holder_cart_fees    = THEMECOMPLETE_EPO()->tm_builder_elements[ $this->element['type'] ]->fee_type;
			$this->setup               = true;
		}
	}

	/**
	 * Check if all variables are setup
	 *
	 * @return boolean
	 * @since 1.0
	 */
	public function is_setup() {
		return $this->setup;
	}

	/**
	 * Gets the saved value
	 *
	 * @param array<mixed> $element The element array.
	 * @param string       $name The name of the element array key to fetch.
	 * @param mixed        $default_value The default value.
	 * @param string       $prefix The value prefix.
	 * @return mixed
	 * @since 6.0
	 */
	public function get_value( $element = [], $name = '', $default_value = '', $prefix = '' ) {
		$value = isset( $element[ $name ] ) ? $element[ $name ] : $default_value;

		if ( ! is_array( $value ) ) {
			$value = $prefix . $value;
		}

		$value = apply_filters( 'wc_epo_fetch_' . esc_attr( $name ), $value, $element );

		return $value;
	}

	/**
	 * Gets the saved value
	 *
	 * @param array<mixed> $element The element array.
	 * @param string       $name The name of the element array key to fetch.
	 * @param string       $default_value The default value.
	 * @return mixed
	 * @since 6.0
	 */
	public function get_value_no_empty( $element = [], $name = '', $default_value = '' ) {
		$value = isset( $element[ $name ] ) && ! empty( $element[ $name ] ) ? $element[ $name ] : $default_value;
		$value = apply_filters( 'wc_epo_fetch_' . esc_attr( $name ), $value, $element );
		return $value;
	}

	/**
	 * Gets the default value for display
	 *
	 * @param array<mixed>   $element The element array.
	 * @param array<mixed>   $args Array of arguments.
	 * @param boolean|string $use_default_value if the default element value should be fetched.
	 * @param string|false   $alt_value The value to return if everythign else fail.
	 * @return mixed
	 * @since 6.0
	 */
	public function get_default_value( $element = [], $args = [], $use_default_value = true, $alt_value = false ) {
		$this->post_data   = apply_filters( 'wc_epo_get_default_value_post_data', $this->post_data, $element );
		$get_default_value = '';
		if ( 'no' === THEMECOMPLETE_EPO_DATA_STORE()->get( 'tm_epo_global_reset_options_after_add' ) && isset( $this->post_data[ $args['posted_name'] ] ) ) {
			// Data is already unslashed.
			$get_default_value = $this->post_data[ $args['posted_name'] ];
		} elseif ( empty( $this->post_data ) && isset( $_REQUEST[ $args['posted_name'] ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$get_default_value = wp_unslash( $_REQUEST[ $args['posted_name'] ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} elseif ( $use_default_value ) {
			$default_value = $this->get_value( $element, 'default_value', '' );
			if ( 'notempty' === $use_default_value ) {
				if ( '' !== $default_value ) {
					$get_default_value = $default_value;
				} elseif ( false !== $alt_value ) {
					$get_default_value = $alt_value;
				}
			} elseif ( true === $use_default_value ) {
				$get_default_value = $default_value;
			}
		} elseif ( false !== $alt_value ) {
			$get_default_value = $alt_value;
		}
		$get_default_value = apply_filters( 'wc_epo_get_default_value', $get_default_value, $element, isset( $args['value'] ) ? $args['value'] : '' );
		if ( is_array( $get_default_value ) ) {

			if ( isset( $args['get_posted_key'] ) && isset( $get_default_value[ $args['get_posted_key'] ] ) ) {
				$get_default_value = $get_default_value[ $args['get_posted_key'] ];
			} else {
				$get_default_value = $get_default_value[0];
			}
		}
		return $get_default_value;
	}

	/**
	 * Return price per currency array
	 *
	 * @param float|array<mixed>|false $attribute_quantity The element quantity.
	 * @return false|array<mixed>
	 * @since 1.0
	 */
	public function fill_currencies( $attribute_quantity = 1 ) {
		$price_per_currencies = isset( $this->element['price_per_currencies'] ) ? $this->element['price_per_currencies'] : [];
		$price_per_currency   = [];
		$current_currency     = themecomplete_get_woocommerce_currency();
		foreach ( $price_per_currencies as $currency => $price_rule ) {
			$copy_element                         = $this->element;
			$copy_element['price_rules_original'] = $copy_element['price_rules'];
			$copy_element['price_rules']          = $price_rule;
			$currency_price                       = THEMECOMPLETE_EPO()->calculate_price( $this->post_data, $copy_element, $this->key, $this->attribute, $attribute_quantity, $this->key_id, $this->keyvalue_id, $this->per_product_pricing, $this->cpf_product_price, $this->variation_id, '', $currency, $current_currency, $price_per_currencies );
			$price_per_currency[ $currency ]      = $currency_price;
		}
		return $price_per_currency;
	}

	/**
	 * Display field array
	 *
	 * @param array<mixed> $element The element array.
	 * @param array<mixed> $args Array of arguments.
	 * @return array<mixed>
	 * @since 1.0
	 */
	public function display_field( $element = [], $args = [] ) { // phpcs:ignore
		return [];
	}

	/**
	 * Pre display field actions
	 *
	 * @param array<mixed> $element The element array.
	 * @param array<mixed> $args Array of arguments.
	 * @return void
	 * @since 1.0
	 */
	public function display_field_pre( $element = [], $args = [] ) {
	}

	/**
	 * Field pre validation
	 *
	 * @param array<mixed>  $epo_post_fields The posted fields array.
	 * @param array<mixed>  $element The element array.
	 * @param integer|false $loop The current loop.
	 * @param string        $form_prefix The form prefix.
	 * @return array<mixed>
	 * @since 1.0
	 */
	final public function validate_field( $epo_post_fields = [], $element = [], $loop = false, $form_prefix = '' ) {
		$this->epo_post_fields     = $epo_post_fields;
		$this->element             = $element;
		$this->loop                = $loop;
		$this->form_prefix         = $form_prefix;
		$this->tmcp_attributes     = THEMECOMPLETE_EPO()->get_post_names( $element['options'], $element['type'], $loop, $form_prefix, '', $element );
		$this->tmcp_attributes_fee = THEMECOMPLETE_EPO()->get_post_names( $element['options'], $element['type'], $loop, $form_prefix, THEMECOMPLETE_EPO()->cart_fee_name, $element );

		$is_fee = false;
		if ( isset( $this->element['is_cart_fee'] ) ) {
			if ( is_array( $this->element['is_cart_fee'] ) && isset( $this->element['is_cart_fee'][ $loop ] ) ) {
				$is_fee = $this->element['is_cart_fee'][ $loop ];
			} elseif ( ! is_array( $this->element['is_cart_fee'] ) ) {
				$is_fee = $this->element['is_cart_fee'];
			}
		}

		if ( $is_fee ) {
			$this->field_names = $this->tmcp_attributes_fee;
		} else {
			$this->field_names = $this->tmcp_attributes;
			$this->field_names = apply_filters( 'wc_epo_validate_field_field_names', $this->field_names, $this, $element, $loop, $form_prefix );
		}

		return $this->validate();
	}

	/**
	 * Field validation
	 *
	 * @return array<mixed>
	 * @since 1.0
	 */
	public function validate() {
		return [
			'passed'  => true,
			'message' => false,
		];
	}

	/**
	 * Function to filter the result data
	 *
	 * @param array<mixed> $ret The data result.
	 * @param string       $type 'normal' or 'fee'.
	 * @return array<mixed>
	 * @since 6.4.3
	 */
	public function result( $ret = [], $type = '' ) {
		return apply_filters( 'wc_epo_save_cart_item_data', $ret, $type, $this );
	}

	/**
	 * Add field data to cart
	 *
	 * @param string|false        $attribute The posted element name.
	 * @param string|array<mixed> $key The posted element value.
	 * @param integer             $key_id The array key of the posted element values array.
	 * @param integer             $keyvalue_id The array key for the values of the posted element values array.
	 * @return false|array<mixed>
	 * @since 1.0
	 */
	final public function add_cart_item_data( $attribute = false, $key = '', $key_id = 0, $keyvalue_id = 0 ) {
		if ( ! $this->setup ) {
			return false;
		}
		$this->key_id             = $key_id;
		$this->keyvalue_id        = $keyvalue_id;
		$this->attribute          = $attribute;
		$this->attribute_quantity = $attribute . '_quantity';
		$this->key                = $key;

		$attribute_quantity = isset( $this->post_data[ $this->attribute_quantity ] ) ? $this->post_data[ $this->attribute_quantity ] : 1;
		if ( is_array( $attribute_quantity ) && isset( $attribute_quantity[ $this->key_id ] ) ) {
			$attribute_quantity = $attribute_quantity[ $this->key_id ];
			if ( is_array( $attribute_quantity ) && isset( $attribute_quantity[ $this->keyvalue_id ] ) ) {
				$attribute_quantity = $attribute_quantity[ $this->keyvalue_id ];
			}
		}
		$this->posted_attribute_quantity = floatval( $attribute_quantity );

		$string = $this->key;

		if ( ! is_array( $string ) ) {
			$pattern = '/^(.+?_\d+)-\d+$/';

			if ( preg_match( $pattern, $string, $matches ) ) {
				$this->key = $matches[1];
			}
		}

		$ret = false;
		if ( 'single' === $this->holder || 'multipleallsingle' === $this->holder ) {
			$ret = $this->add_cart_item_data_single();
		} elseif ( 'multiple' === $this->holder || 'multipleall' === $this->holder || 'multiplesingle' === $this->holder || 'singlemultiple' === $this->holder ) {
			$ret = $this->add_cart_item_data_multiple();
		}

		if ( false !== $ret ) {

			$_price_type = THEMECOMPLETE_EPO()->get_element_price_type( '', $this->element, $this->key, $this->per_product_pricing, $this->variation_id );
			if ( is_array( $this->order_saved_element ) ) {
				$this->order_saved_element['_'] = [ 'price_type' => $_price_type ];
			}
			if ( 'math' === $_price_type ) {
				$_price = THEMECOMPLETE_EPO()->get_element_price( 0, $_price_type, $this->element, $this->key, $this->per_product_pricing, $this->variation_id );
				if ( is_array( $_price ) ) {
					$_price = $_price['price'];
				}
				if ( str_contains( $_price, '{quantity}' ) || str_contains( $_price, '{product_price}' ) || str_contains( $_price, '{dynamic_product_price}' ) || THEMECOMPLETE_EPO()->has_math_special_variable( $_price ) ) {
					$ret['price_formula'] = $_price;
				}

				if ( 'change_product_weight' === $this->element['mode'] ) {
					$this_key = $this->key;
					if ( is_array( $this_key ) ) {
						$this_key = '0';
					}
					$ret['weight'] = THEMECOMPLETE_EPO()->calculate_math_price( $_price, $this->post_data, $this->element, $this_key, $this->attribute, $this->posted_attribute_quantity, $this->key_id, $this->keyvalue_id, $this->per_product_pricing, $this->cpf_product_price, $this->variation_id );
				}
			}
			if ( false !== $this->repeater ) {
				$ret['repeater'] = $this->repeater;
			}
			$ret['key_id']      = $this->key_id;
			$ret['keyvalue_id'] = $this->keyvalue_id;

			return $this->result( $ret, 'normal' );

		}

		return false;
	}

	/**
	 * Add field data to cart (fees)
	 *
	 * @param string|false $attribute The posted element name.
	 * @param string       $key The posted element value.
	 * @param integer      $key_id The array key of the posted element values array.
	 * @param integer      $keyvalue_id The array key for the values of the posted element values array.
	 * @return false|array<mixed>
	 * @since 1.0
	 */
	final public function add_cart_item_data_cart_fees( $attribute = false, $key = '', $key_id = 0, $keyvalue_id = 0 ) {
		if ( ! $this->setup ) {
			return false;
		}
		$this->key_id             = $key_id;
		$this->keyvalue_id        = $keyvalue_id;
		$this->attribute          = $attribute;
		$this->attribute_quantity = $attribute . '_quantity';
		$this->key                = $key;

		$attribute_quantity = isset( $this->post_data[ $this->attribute_quantity ] ) ? $this->post_data[ $this->attribute_quantity ] : 1;
		if ( is_array( $attribute_quantity ) && isset( $attribute_quantity[ $this->key_id ] ) ) {
			$attribute_quantity = $attribute_quantity[ $this->key_id ];
			if ( is_array( $attribute_quantity ) && isset( $attribute_quantity[ $this->keyvalue_id ] ) ) {
				$attribute_quantity = $attribute_quantity[ $this->keyvalue_id ];
			}
		}
		$this->posted_attribute_quantity = $attribute_quantity;

		$ret = false;
		if ( 'single' === $this->holder_cart_fees ) {
			$ret = $this->add_cart_item_data_cart_fees_single();
		} elseif ( 'multiple' === $this->holder_cart_fees ) {
			$ret = $this->add_cart_item_data_cart_fees_multiple();
		}

		if ( false !== $ret ) {

			$_price_type = THEMECOMPLETE_EPO()->get_element_price_type( '', $this->element, $this->key, $this->per_product_pricing, $this->variation_id );
			if ( is_array( $this->order_saved_element ) ) {
				$this->order_saved_element['_'] = [ 'price_type' => $_price_type ];
			}
			if ( 'math' === $_price_type ) {
				$_price = THEMECOMPLETE_EPO()->get_element_price( 0, $_price_type, $this->element, $this->key, $this->per_product_pricing, $this->variation_id );
				if ( is_array( $_price ) ) {
					$_price = $_price['price'];
				}
				if ( str_contains( $_price, '{quantity}' ) || str_contains( $_price, '{product_price}' ) || str_contains( $_price, '{dynamic_product_price}' ) || THEMECOMPLETE_EPO()->has_math_special_variable( $_price ) ) {
					$ret['price_formula'] = $_price;
				}
			}
			if ( false !== $this->repeater ) {
				$ret['repeater'] = $this->repeater;
			}
			$ret['key_id']      = $this->key_id;
			$ret['keyvalue_id'] = $this->keyvalue_id;
			return $this->result( $ret, 'fee' );

		}

		return false;
	}

	/**
	 * Add field data to cart (single type fields)
	 *
	 * @return false|array<mixed>
	 * @since 1.0
	 */
	public function add_cart_item_data_single() {
		if ( ! $this->setup ) {
			return false;
		}
		if ( '' !== $this->key ) {

			$_price = THEMECOMPLETE_EPO()->calculate_price( $this->post_data, $this->element, $this->key, $this->attribute, $this->posted_attribute_quantity, $this->key_id, $this->keyvalue_id, $this->per_product_pricing, $this->cpf_product_price, $this->variation_id );

			$ret = [
				'mode'                             => 'builder',
				'cssclass'                         => $this->element['class'],
				'hidelabelincart'                  => $this->element['hide_element_label_in_cart'],
				'hidevalueincart'                  => $this->element['hide_element_value_in_cart'],
				'hidelabelinorder'                 => $this->element['hide_element_label_in_order'],
				'hidevalueinorder'                 => $this->element['hide_element_value_in_order'],
				'shippingmethodsenable'            => $this->element['shipping_methods_enable'],
				'shippingmethodsenablelogicrules'  => $this->element['shipping_methods_enable_logicrules'],
				'shippingmethodsdisable'           => $this->element['shipping_methods_disable'],
				'shippingmethodsdisablelogicrules' => $this->element['shipping_methods_disable_logicrules'],
				'element'                          => $this->order_saved_element,
				'name'                             => $this->element['label'],
				'value'                            => $this->key,
				'post_name'                        => $this->attribute,
				'price'                            => $_price,
				'section'                          => $this->element['uniqid'],
				'section_label'                    => $this->element['label'],
				'percentcurrenttotal'              => isset( $this->post_data[ $this->attribute . '_hidden' ] ) ? 1 : 0,
				'fixedcurrenttotal'                => isset( $this->post_data[ $this->attribute . '_hiddenfixed' ] ) ? 1 : 0,
				'currencies'                       => isset( $this->element['currencies'] ) ? $this->element['currencies'] : [],
				'price_per_currency'               => $this->fill_currencies( $this->posted_attribute_quantity ),
				'quantity'                         => $this->posted_attribute_quantity,
				'quantity_selector'                => isset( $this->element['quantity'] ) ? $this->element['quantity'] : '',
			];
			if ( 'dynamic' === $this->element['type'] ) {
				$ret['dynamic'] = $this->element['mode'];
			}
			$ret['dnmpbq'] = $this->element['dnmpbq'];

			return apply_filters(
				'wc_epo_add_cart_item_data_single',
				$ret,
				$this
			);
		}

		return false;
	}

	/**
	 * Add field data to cart (multiple type fields)
	 *
	 * @return false|array<mixed>
	 * @since 1.0
	 */
	public function add_cart_item_data_multiple() {
		if ( ! $this->setup ) {
			return false;
		}

		// multiple support.
		$value  = false;
		$prices = false;
		if ( is_array( $this->key ) ) {
			$value   = [];
			$prices  = [];
			$thiskey = $this->key;
			foreach ( $this->key as $thiskeyvalue ) {
				$thiskey                 = esc_attr( $thiskeyvalue );
				$value[]                 = $this->element['options'][ $thiskey ];
				$prices[ $thiskeyvalue ] = THEMECOMPLETE_EPO()->calculate_price( $this->post_data, $this->element, $thiskeyvalue, $this->attribute, $this->posted_attribute_quantity, $this->key_id, $this->keyvalue_id, $this->per_product_pricing, $this->cpf_product_price, $this->variation_id );

			}
		} elseif ( isset( $this->element['options'][ esc_attr( $this->key ) ] ) ) {
			$thiskey = esc_attr( $this->key );
			$value   = $this->element['options'][ $thiskey ];
		}
		if ( false !== $value ) {
			$field_counter = array_search( $this->key, $this->element['option_values'], true );
			$_price        = THEMECOMPLETE_EPO()->calculate_price( $this->post_data, $this->element, $this->key, $this->attribute, $this->posted_attribute_quantity, $this->key_id, $this->keyvalue_id, $this->per_product_pricing, $this->cpf_product_price, $this->variation_id );
			$use_images    = ( 'image' === $this->element['replacement_mode'] ) ? ( 'center' === $this->element['swatch_position'] ? 'images' : $this->element['swatch_position'] ) : '';
			if ( $use_images ) {
				$_image_key = $field_counter;
				if ( false === $_image_key ) {
					$_image_key = false;
				}
			} else {
				$_image_key = false;
			}

			$use_colors = ( 'color' === $this->element['replacement_mode'] ) ? ( 'center' === $this->element['swatch_position'] ? 'color' : $this->element['swatch_position'] ) : '';

			if ( $use_colors ) {
				$_color_key = $field_counter;
				if ( false === $_color_key ) {
					$_color_key = false;
				}
			} else {
				$_color_key = false;
			}

			$changes_product_image = ! empty( $this->element['changes_product_image'] ) ? $this->element['changes_product_image'] : '';
			if ( $changes_product_image ) {
				$c_image_key = $field_counter;
				if ( false === $c_image_key ) {
					$c_image_key = false;
				}
			} else {
				$c_image_key = false;
			}

			$ret = [
				'mode'                             => 'builder',
				'cssclass'                         => $this->element['class'],
				'hidelabelincart'                  => $this->element['hide_element_label_in_cart'],
				'hidevalueincart'                  => $this->element['hide_element_value_in_cart'],
				'hidelabelinorder'                 => $this->element['hide_element_label_in_order'],
				'hidevalueinorder'                 => $this->element['hide_element_value_in_order'],
				'shippingmethodsenable'            => $this->element['shipping_methods_enable'],
				'shippingmethodsenablelogicrules'  => $this->element['shipping_methods_enable_logicrules'],
				'shippingmethodsdisable'           => $this->element['shipping_methods_disable'],
				'shippingmethodsdisablelogicrules' => $this->element['shipping_methods_disable_logicrules'],
				'element'                          => $this->order_saved_element,
				'name'                             => $this->element['label'],
				'value'                            => $value,
				'post_name'                        => $this->attribute,
				'price'                            => $_price,
				'section'                          => $this->element['uniqid'],
				'section_label'                    => $this->element['label'],
				'percentcurrenttotal'              => isset( $this->post_data[ $this->attribute . '_hidden' ] ) ? 1 : 0,
				'fixedcurrenttotal'                => isset( $this->post_data[ $this->attribute . '_hiddenfixed' ] ) ? 1 : 0,
				'currencies'                       => isset( $this->element['currencies'] ) ? $this->element['currencies'] : [],
				'price_per_currency'               => $this->fill_currencies( $this->posted_attribute_quantity ),
				'quantity'                         => $this->posted_attribute_quantity,
				'quantity_selector'                => isset( $this->element['quantity'] ) ? $this->element['quantity'] : '',
				'multiple'                         => '1',
				'key'                              => isset( $thiskey ) ? $thiskey : '',
				'use_images'                       => $use_images,
				'use_colors'                       => $use_colors,
				'changes_product_image'            => $changes_product_image,
				'imagesp'                          => ( false !== $c_image_key && isset( $this->element['imagesp'][ $c_image_key ] ) ) ? $this->element['imagesp'][ $c_image_key ] : '',
				'images'                           => ( false !== $_image_key && isset( $this->element['images'][ $_image_key ] ) ) ? $this->element['images'][ $_image_key ] : '',
				'imagesc'                          => ( false !== $_image_key && isset( $this->element['imagesc'][ $_image_key ] ) && ! empty( $this->element['imagesc'][ $_image_key ] ) ) ? $this->element['imagesc'][ $_image_key ] : '',
				'color'                            => ( false !== $_color_key && isset( $this->element['color'][ $_color_key ] ) ) ? ( empty( $this->element['color'][ $_color_key ] ) ? 'transparent' : $this->element['color'][ $_color_key ] ) : '',
			];

			if ( is_array( $this->key ) ) {
				$ret['original_key'] = $this->key;
			}
			if ( false !== $prices ) {
				$ret['prices'] = $prices;
			}

			if ( 'checkbox' === $this->element['type'] ) {
				$ret['dnmpbq'] = isset( $this->element['is_dnmpbq_multiple'] ) && isset( $this->element['is_dnmpbq_multiple'][ $field_counter ] ) ? $this->element['is_dnmpbq_multiple'][ $field_counter ] : '';
			} else {
				$ret['dnmpbq'] = $this->element['dnmpbq'];
			}

			return apply_filters(
				'wc_epo_add_cart_item_data_multiple',
				$ret,
				$this
			);
		}

		return false;
	}

	/**
	 * Add field data to cart (fees single)
	 *
	 * @return false|array<mixed>
	 * @since 1.0
	 */
	public function add_cart_item_data_cart_fees_single() {
		if ( ! $this->setup ) {
			return false;
		}
		if ( '' !== $this->key ) {
			$_price = THEMECOMPLETE_EPO()->calculate_price( $this->post_data, $this->element, $this->key, $this->attribute, $this->posted_attribute_quantity, $this->key_id, $this->keyvalue_id, $this->per_product_pricing, $this->cpf_product_price, $this->variation_id );
			$ret    = [
				'mode'                             => 'builder',
				'cssclass'                         => $this->element['class'],
				'include_tax_for_fee_price_type'   => $this->element['include_tax_for_fee_price_type'],
				'tax_class_for_fee_price_type'     => $this->element['tax_class_for_fee_price_type'],
				'hidelabelincart'                  => $this->element['hide_element_label_in_cart'],
				'hidevalueincart'                  => $this->element['hide_element_value_in_cart'],
				'hidelabelinorder'                 => $this->element['hide_element_label_in_order'],
				'hidevalueinorder'                 => $this->element['hide_element_value_in_order'],
				'shippingmethodsenable'            => $this->element['shipping_methods_enable'],
				'shippingmethodsenablelogicrules'  => $this->element['shipping_methods_enable_logicrules'],
				'shippingmethodsdisable'           => $this->element['shipping_methods_disable'],
				'shippingmethodsdisablelogicrules' => $this->element['shipping_methods_disable_logicrules'],
				'element'                          => $this->order_saved_element,
				'name'                             => $this->element['label'],
				'value'                            => $this->key,
				'post_name'                        => $this->attribute,
				'price'                            => THEMECOMPLETE_EPO_CART()->calculate_fee_price( $_price, $this->product_id, $this->element ),
				'section'                          => $this->element['uniqid'],
				'section_label'                    => $this->element['label'],
				'percentcurrenttotal'              => 0,
				'fixedcurrenttotal'                => 0,
				'currencies'                       => isset( $this->element['currencies'] ) ? $this->element['currencies'] : [],
				'price_per_currency'               => $this->fill_currencies( $this->posted_attribute_quantity ),
				'quantity'                         => $this->posted_attribute_quantity,
				'quantity_selector'                => isset( $this->element['quantity'] ) ? $this->element['quantity'] : '',
				'cart_fees'                        => 'single',
			];
			return apply_filters(
				'wc_epo_add_cart_item_data_cart_fees_single',
				$ret,
				$this
			);
		}

		return false;
	}

	/**
	 * Add field data to cart (fees multiple)
	 *
	 * @return false|array<mixed>
	 * @since 1.0
	 */
	public function add_cart_item_data_cart_fees_multiple() {
		if ( ! $this->setup ) {
			return false;
		}
		if ( empty( $this->key ) ) {
			return false;
		}
		// select placeholder check.
		if ( ! is_array( $this->key ) && isset( $this->element['options'][ esc_attr( $this->key ) ] ) ) {
			$_price = THEMECOMPLETE_EPO()->calculate_price( $this->post_data, $this->element, $this->key, $this->attribute, $this->posted_attribute_quantity, $this->key_id, $this->keyvalue_id, $this->per_product_pricing, $this->cpf_product_price, $this->variation_id );

			$use_images = ( 'image' === $this->element['replacement_mode'] ) ? ( 'center' === $this->element['swatch_position'] ? 'images' : $this->element['swatch_position'] ) : '';

			if ( $use_images ) {
				$_image_key = array_search( $this->key, $this->element['option_values'], true );
				if ( false === $_image_key ) {
					$_image_key = false;
				}
			} else {
				$_image_key = false;
			}

			$use_colors = ( 'color' === $this->element['replacement_mode'] ) ? ( 'center' === $this->element['swatch_position'] ? 'color' : $this->element['swatch_position'] ) : '';

			if ( $use_colors ) {
				$_color_key = array_search( $this->key, $this->element['option_values'], true );
				if ( false === $_color_key ) {
					$_color_key = false;
				}
			} else {
				$_color_key = false;
			}

			$ret = [
				'mode'                             => 'builder',
				'cssclass'                         => $this->element['class'],
				'include_tax_for_fee_price_type'   => $this->element['include_tax_for_fee_price_type'],
				'tax_class_for_fee_price_type'     => $this->element['tax_class_for_fee_price_type'],
				'hidelabelincart'                  => $this->element['hide_element_label_in_cart'],
				'hidevalueincart'                  => $this->element['hide_element_value_in_cart'],
				'hidelabelinorder'                 => $this->element['hide_element_label_in_order'],
				'hidevalueinorder'                 => $this->element['hide_element_value_in_order'],
				'shippingmethodsenable'            => $this->element['shipping_methods_enable'],
				'shippingmethodsenablelogicrules'  => $this->element['shipping_methods_enable_logicrules'],
				'shippingmethodsdisable'           => $this->element['shipping_methods_disable'],
				'shippingmethodsdisablelogicrules' => $this->element['shipping_methods_disable_logicrules'],
				'element'                          => $this->order_saved_element,
				'name'                             => $this->element['label'],
				'value'                            => $this->element['options'][ esc_attr( $this->key ) ],
				'post_name'                        => $this->attribute,
				'price'                            => THEMECOMPLETE_EPO_CART()->calculate_fee_price( $_price, $this->product_id, $this->element ),
				'section'                          => $this->element['uniqid'],
				'section_label'                    => $this->element['label'],
				'percentcurrenttotal'              => 0,
				'fixedcurrenttotal'                => 0,
				'currencies'                       => isset( $this->element['currencies'] ) ? $this->element['currencies'] : [],
				'price_per_currency'               => $this->fill_currencies( $this->posted_attribute_quantity ),
				'quantity'                         => $this->posted_attribute_quantity,
				'quantity_selector'                => isset( $this->element['quantity'] ) ? $this->element['quantity'] : '',
				'cart_fees'                        => 'multiple',
				'key'                              => esc_attr( $this->key ),
				'use_images'                       => $use_images,
				'use_colors'                       => $use_colors,
				'color'                            => ( false !== $_color_key && isset( $this->element['color'][ $_color_key ] ) ) ? ( empty( $this->element['color'][ $_color_key ] ) ? 'transparent' : $this->element['color'][ $_color_key ] ) : '',
				'changes_product_image'            => ! empty( $this->element['changes_product_image'] ) ? $this->element['changes_product_image'] : '',
				'images'                           => ( false !== $_image_key && isset( $this->element['images'][ $_image_key ] ) ) ? $this->element['images'][ $_image_key ] : '',
				'imagesc'                          => ( false !== $_image_key && isset( $this->element['imagesc'][ $_image_key ] ) && ! empty( $this->element['imagesc'][ $_image_key ] ) ) ? $this->element['imagesc'][ $_image_key ] : '',
				'imagesp'                          => ( false !== $_image_key && isset( $this->element['imagesp'][ $_image_key ] ) ) ? $this->element['imagesp'][ $_image_key ] : '',
			];

			return apply_filters(
				'wc_epo_add_cart_item_data_cart_fees_multiple',
				$ret,
				$this
			);
		}

		return false;
	}
}
