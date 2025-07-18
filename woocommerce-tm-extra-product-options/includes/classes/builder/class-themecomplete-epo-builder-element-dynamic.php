<?php
/**
 * Dynamic Calculations Element
 *
 * @package Extra Product Options/Classes/Builder
 * @version 6.4.3
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic Calculations Element
 *
 * @package Extra Product Options/Classes/Builder
 * @version 6.4.3
 */
class THEMECOMPLETE_EPO_BUILDER_ELEMENT_DYNAMIC extends THEMECOMPLETE_EPO_BUILDER_ELEMENT {

	/**
	 * Class Constructor
	 *
	 * @param string $name The element name.
	 * @since 6.4.3
	 */
	public function __construct( $name = '' ) {
		$this->element_name     = $name;
		$this->is_addon         = false;
		$this->namespace        = $this->elements_namespace;
		$this->name             = esc_html__( 'Dynamic Calculations', 'woocommerce-tm-extra-product-options' );
		$this->description      = '';
		$this->width            = 'w100';
		$this->width_display    = '100%';
		$this->icon             = 'tcfa-calculator';
		$this->is_post          = 'post';
		$this->type             = 'single';
		$this->post_name_prefix = 'dynamic';
		$this->fee_type         = 'single';
		$this->tags             = 'price content dynamic';
		$this->show_on_backend  = true;
	}

	/**
	 * Initialize element properties
	 *
	 * @since 6.4.3
	 * @return void
	 */
	public function set_properties() {
		$this->properties = $this->add_element(
			$this->element_name,
			[
				'enabled',
				[
					'id'          => 'dynamic_mode',
					'wpmldisable' => 1,
					'default'     => 'calculation',
					'type'        => 'select',
					'tags'        => [
						'class' => 'dynamic-mode',
						'id'    => 'builder_dynamic_mode',
						'name'  => 'tm_meta[tmfbuilder][dynamic_mode][]',
					],
					'options'     => [
						[
							'text'  => esc_html__( 'Calculation result', 'woocommerce-tm-extra-product-options' ),
							'value' => 'calculation',
						],
						[
							'text'  => esc_html__( 'Override product price', 'woocommerce-tm-extra-product-options' ),
							'value' => 'override_product_price',
						],
						[
							'text'  => esc_html__( 'Set native product price', 'woocommerce-tm-extra-product-options' ),
							'value' => 'dynamic_product_price',
						],
						[
							'text'  => esc_html__( 'Change product weight', 'woocommerce-tm-extra-product-options' ),
							'value' => 'change_product_weight',
						],
					],
					'label'       => esc_html__( 'Action mode', 'woocommerce-tm-extra-product-options' ),
					'desc'        => esc_html__( 'Choose the desired action mode for this element.', 'woocommerce-tm-extra-product-options' ),
				],
				[
					'id'          => 'dynamic_hide',
					'wpmldisable' => 1,
					'default'     => '1',
					'type'        => 'checkbox',
					'tags'        => [
						'value' => '1',
						'class' => 'dynamic-hide',
						'id'    => 'builder_dynamic_hide',
						'name'  => 'tm_meta[tmfbuilder][dynamic_hide][]',
					],
					'label'       => esc_html__( 'Hide calculation result', 'woocommerce-tm-extra-product-options' ),
					'desc'        => esc_html__( 'When activated, the plugin hides the outcome of the calculation.', 'woocommerce-tm-extra-product-options' ),
					'required'    => [
						'.dynamic-mode' => [
							'operator' => 'isnot',
							'value'    => 'dynamic_product_price',
						],
					],
				],
				[
					'id'          => 'dynamic_result_as_price',
					'wpmldisable' => 1,
					'default'     => '',
					'type'        => 'checkbox',
					'tags'        => [
						'value' => '1',
						'class' => 'dynamic-result-as-price',
						'id'    => 'builder_dynamic_result_as_price',
						'name'  => 'tm_meta[tmfbuilder][dynamic_result_as_price][]',
					],
					'label'       => esc_html__( 'Display calculation result as price', 'woocommerce-tm-extra-product-options' ),
					'desc'        => esc_html__( 'When activated, the the outcome of the calculation is displayed as a price.', 'woocommerce-tm-extra-product-options' ),
					'required'    => [
						'.dynamic-mode' => [
							'operator' => 'is',
							'value'    => 'calculation',
						],
						'.dynamic-hide' => [
							'operator' => 'isnot',
							'value'    => '1',
						],
					],
				],
				'price_type_dynamic',
				'lookuptable',
				[
					'price',
					[
						'label' => esc_html__( 'Formula', 'woocommerce-tm-extra-product-options' ),
						'desc'  => esc_html__( 'Enter the formula for the calculation.', 'woocommerce-tm-extra-product-options' ),
					],
				],
				[
					'id'       => 'dynamic_result_label',
					'default'  => '',
					'type'     => 'text',
					'tags'     => [
						'class' => 't',
						'id'    => 'builder_dynamic_result_label',
						'name'  => 'tm_meta[tmfbuilder][dynamic_result_label][]',
						'value' => '',
					],
					'label'    => esc_html__( 'Result Display Label', 'woocommerce-tm-extra-product-options' ),
					'desc'     => esc_html__( 'Enter the text to show next to the calculation result on the page.', 'woocommerce-tm-extra-product-options' ),
					'required' => [
						'.dynamic-hide' => [
							'operator' => 'isnot',
							'value'    => '1',
						],
						'.dynamic-mode' => [
							'operator' => 'isnot',
							'value'    => 'dynamic_product_price',
						],
					],
				],
				[
					'text_before_price',
					[
						'label'    => esc_html__( 'Text before result', 'woocommerce-tm-extra-product-options' ),
						'desc'     => esc_html__( 'Enter a text to display before the calculation result or leave it blank for no text.', 'woocommerce-tm-extra-product-options' ),
						'required' => [
							'.dynamic-hide' => [
								'operator' => 'isnot',
								'value'    => '1',
							],
							'.dynamic-mode' => [
								'operator' => 'isnot',
								'value'    => 'dynamic_product_price',
							],
						],
					],
				],
				[
					'text_after_price',
					[
						'label'    => esc_html__( 'Text after result', 'woocommerce-tm-extra-product-options' ),
						'desc'     => esc_html__( 'Enter a text to display after the calculation result or leave it blank for no text.', 'woocommerce-tm-extra-product-options' ),
						'required' => [
							'.dynamic-hide' => [
								'operator' => 'isnot',
								'value'    => '1',
							],
							'.dynamic-mode' => [
								'operator' => 'isnot',
								'value'    => 'dynamic_product_price',
							],
						],
					],
				],
			],
			false,
			[
				'label_options'        => 1,
				'general_options'      => 1,
				'conditional_logic'    => 1,
				'css_settings'         => 0,
				'woocommerce_settings' => 1,
				'repeater_settings'    => 0,
				'action_settings'      => 1,
			],
		);
	}
}
