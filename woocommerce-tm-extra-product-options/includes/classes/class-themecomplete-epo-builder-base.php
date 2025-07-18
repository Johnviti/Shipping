<?php
/**
 * Extra Product Options Builder class
 *
 * @package Extra Product Options/Classes
 * @version 6.4
 */

defined( 'ABSPATH' ) || exit;

/**
 * Extra Product Options Builder class
 *
 * @package Extra Product Options/Classes
 * @version 6.4
 */
final class THEMECOMPLETE_EPO_BUILDER_Base {

	/**
	 * The single instance of the class
	 *
	 * @var THEMECOMPLETE_EPO_BUILDER_Base|null
	 * @since 1.0
	 */
	protected static $instance = null;

	/**
	 * Internal element names
	 *
	 * @var array<mixed>
	 */
	public $internal_element_names = [];

	/**
	 * All elements
	 *
	 * @var array<mixed>
	 */
	public $all_elements = [];

	/**
	 * Extra settings for all addons
	 *
	 * @var array<mixed>
	 */
	public $extra_addon_properties = [];

	/**
	 * Extra settings for multiple type addons
	 *
	 * @var array<mixed>
	 */
	public $extra_multiple_options = [];

	/**
	 * Addon option attributes
	 *
	 * @var array<mixed>
	 */
	public $addons_attributes = [];

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @return THEMECOMPLETE_EPO_BUILDER_Base
	 * @since 1.0
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
		// Set internal element names.
		$this->set_internal_element_names();

		// Set internal element custom properties.
		$this->set_internal_element_custom_properties();

		// Extra settings for all addons.
		$this->extra_addon_properties = apply_filters( 'wc_epo_extra_addon_properties', $this->extra_addon_properties );

		$this->extra_addon_properties = $this->parse_extra_addon_properties();

		// Extra settings for multiple type addons.
		$this->extra_multiple_options = apply_filters( 'wc_epo_extra_multiple_choices', $this->extra_multiple_options );

		// Init internal elements.
		$this->init_internal_elements();

		if ( is_admin() ) {
			THEMECOMPLETE_EPO_ADMIN_BUILDER();
		}
	}

	/**
	 * Parse extra addon properties
	 *
	 * @since 6.4.3
	 * @return array<mixed>
	 */
	public function parse_extra_addon_properties() {
		$data = [
			'settings'   => [],
			'extra_tabs' => [],
		];

		$extra_addon_properties = $this->extra_addon_properties;
		foreach ( $extra_addon_properties as $key => $value ) {
			if ( isset( $value['id'] ) ) {
				if ( isset( $value['tab_data'] ) && isset( $value['header_data'] ) ) {
					$data['extra_tabs'][ $value['id'] ] = [
						'type'        => $value['type'],
						'tab_data'    => $value['tab_data'],
						'header_data' => $value['header_data'],
					];
				} elseif ( isset( $value['field'] ) ) {
					$data['settings'][] = $value;
				}
			}
		}

		return $data;
	}

	/**
	 * Set internal element names
	 *
	 * @return void
	 * @since 6.0
	 */
	public function set_internal_element_names() {
		global $pagenow;

		$this->internal_element_names = [
			'header',
			'divider',
			'date',
			'time',
			'range',
			'color',
			'textarea',
			'textfield',
			'upload',
			'multiple_file_upload',
			'selectbox',
			'selectboxmultiple',
			'radiobuttons',
			'checkboxes',
			'variations',
			'product',
			'template',
			'dynamic',
			'fake_product',
		];

		if ( ( 'post.php' === $pagenow && isset( $_GET['post'] ) ) || ( 'post-new.php' === $pagenow && isset( $_GET['post_type'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : get_post_type( absint( wp_unslash( $_GET['post'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( THEMECOMPLETE_EPO_TEMPLATE_POST_TYPE === $post_type ) {
				$key = array_search( 'template', $this->internal_element_names, true );
				if ( false !== $key ) {
					unset( $this->internal_element_names[ $key ] );
				}
			}
		}

		$this->internal_element_names = apply_filters( 'wc_epo_internal_element_names', $this->internal_element_names );
	}

	/**
	 * Set internal element custom properties
	 *
	 * @return void
	 * @since 6.4.4
	 */
	public function set_internal_element_custom_properties() {
		// Extra settings for all addons.
		$this->extra_addon_properties[] = [
			'id'    => 'weight',
			'index' => 0,
			'type'  => [
				'date',
				'time',
				'range',
				'color',
				'textarea',
				'textfield',
				'upload',
				'multiple_file_upload',
				'selectbox',
				'selectboxmultiple',
				'radiobuttons',
				'checkboxes',
			],
			'tab'   => 'woocommerce_settings',
			'field' => [
				'wpmldisable' => 1,
				'default'     => '',
				'type'        => 'number',
				'tags'        => [
					'value' => '',
				],
				'label'       => esc_html__( 'Weight', 'woocommerce-tm-extra-product-options' ),
				'desc'        => esc_html__( 'Enter the weight to add to the product if this addon is selected.', 'woocommerce-tm-extra-product-options' ),
			],
		];

		// Extra settings for multiple type addons.
		$this->extra_multiple_options[] = [
			'name'        => 'weight',
			'label'       => __( 'Weight', 'woocommerce-tm-extra-product-options' ),
			'admin_class' => 'tc-cell-display',
			'type'        => [ 'selectbox', 'radiobuttons', 'checkboxes' ],
			'field'       => [
				'wpmldisable' => 1,
				'default'     => '',
				'type'        => 'number',
				'tags'        => [
					'class' => 't tm_option_display',
					'value' => '',
				],
				'label'       => __( 'Weight', 'woocommerce-tm-extra-product-options' ),
			],
		];
	}

	/**
	 * Holds all the elements types.
	 *
	 * @return void
	 * @since 6.0
	 * @access private
	 */
	private function init_internal_elements() {
		foreach ( $this->internal_element_names as $class_name ) {
			$class                             = 'THEMECOMPLETE_EPO_BUILDER_ELEMENT_' . strtoupper( $class_name );
			$this->all_elements[ $class_name ] = new $class( $class_name );
		}

		$this->all_elements = apply_filters( 'wc_epo_builder_element_settings', $this->all_elements );

		do_action( 'wc_epo_builder_after_element_settings', $this->all_elements );
	}

	/**
	 * Get all elements
	 *
	 * @return mixed
	 * @since 1.0
	 */
	public function get_elements() {
		return $this->all_elements;
	}

	/**
	 * Get custom properties
	 *
	 * @param array<mixed> $builder Element builder array.
	 * @param string       $_prefix Element prefix.
	 * @param array<mixed> $_counter Counter array.
	 * @param array<mixed> $_elements The saved element types array.
	 * @param integer      $k0 Current section counter.
	 * @param array<mixed> $current_builder The current element builder array.
	 * @param integer      $current_counter The current element counter.
	 * @param string       $current_element The current element.
	 * @return array<mixed>
	 * @since 1.0
	 */
	public function get_custom_properties( $builder, $_prefix, $_counter, $_elements, $k0, $current_builder, $current_counter, $current_element ) {
		$p = [];
		foreach ( $this->addons_attributes as $key => $value ) {
			$p[ $value ] = THEMECOMPLETE_EPO()->get_builder_element( $_prefix . $value, $builder, $current_builder, $current_counter, '', $current_element );
		}

		return $p;
	}

	/**
	 * Remove prefix
	 *
	 * @param string $str The string to remove the prefix from.
	 * @param string $prefix The prefix to remove from the string.
	 * @since 6.0
	 * @return string
	 */
	public function remove_prefix( $str = '', $prefix = '' ) {
		if ( substr( $str, 0, tc_strlen( $prefix ) ) === $prefix ) {
			$str = substr( $str, tc_strlen( $prefix ) );
		}

		return $str;
	}

	/**
	 * Register addons
	 * This function is only used by external addon plugins.
	 *
	 * @param array<mixed> $args Array of arguments.
	 * @return void
	 * @since 1.0
	 */
	public function register_addon( $args = [] ) {
		if ( isset( $args['namespace'] )
			&& isset( $args['name'] )
			&& isset( $args['options'] )
			&& isset( $args['settings'] )
			&& is_array( $args['settings'] ) ) {

			$this->set_elements( $args );

		}
	}

	/**
	 * Set elements
	 * Extends the internal $all_elements variable.
	 *
	 * @param array<mixed> $args Array of arguments.
	 * @return void
	 * @since 1.0
	 */
	private function set_elements( $args = [] ) {
		$name    = $args['name'];
		$options = apply_filters( 'wc_epo_set_elements_options', $args['options'], $args );

		if ( ! empty( $name ) && is_array( $options ) ) {
			$is_addon           = true;
			$addon              = new THEMECOMPLETE_EPO_BUILDER_ELEMENT_ADDON( $options, $args );
			$this->all_elements = array_merge( [ $name => $addon ], $this->all_elements );
			$settings           = $args['settings'];
			foreach ( $settings as $key => $value ) {
				if ( is_array( $value ) && count( $value ) > 2 ) {
					if ( isset( $value['id'] ) ) {
						$this->addons_attributes[] = $this->remove_prefix( $value['id'], $name . '_' );
					}
				} elseif ( is_array( $value ) && 1 === count( $value ) && isset( $value['_multiple_values'] ) ) {
					foreach ( $value['_multiple_values'] as $mkey => $mvalue ) {
						$this->addons_attributes[] = $this->remove_prefix( $mvalue['id'], $name . '_' );
					}
				} else {
					if ( is_array( $value ) && 2 === count( $value ) ) {
						$args  = $value[1];
						$value = $value[0];
					}

					$method = apply_filters( 'wc_epo_add_element_method', 'add_setting_' . $value, $key, $value, $name, $settings, $is_addon );

					$class_to_use = apply_filters( 'wc_epo_add_element_class', THEMECOMPLETE_EPO_ADMIN_BUILDER(), $key, $value, $name, $settings, $is_addon );

					if ( is_callable( [ $class_to_use, $method ] ) ) {
						if ( $args ) {
							$_value = $class_to_use->$method( $name, $args );
						} else {
							$_value = $class_to_use->$method( $name );
						}

						if ( isset( $_value['_multiple_values'] ) ) {
							foreach ( $_value['_multiple_values'] as $mkey => $mvalue ) {
								$this->addons_attributes[] = $this->remove_prefix( $mvalue['id'], $name . '_' );
							}
						} else {
							$this->addons_attributes[] = $this->remove_prefix( $_value['id'], $name . '_' );
						}
					}
				}
			}
		}
	}
}
