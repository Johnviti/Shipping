<?php
/**
 * Compatibility class
 *
 * @package Extra Product Options/Compatibility
 * @version 6.4
 */

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility class
 *
 * This class is responsible for providing compatibility with
 * various plugins
 *
 * @package Extra Product Options/Compatibility
 * @version 6.4
 */
final class THEMECOMPLETE_EPO_CP_Plugins {

	/**
	 * The single instance of the class
	 *
	 * @var THEMECOMPLETE_EPO_CP_Plugins|null
	 * @since 6.0
	 */
	protected static $instance = null;

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @return THEMECOMPLETE_EPO_CP_Plugins
	 * @since 6.0
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
	 * @since 6.0
	 */
	public function __construct() {
		add_action( 'wp', [ $this, 'add_compatibility' ] );
		add_action( 'plugins_loaded', [ $this, 'add_compatibility_plugins_loaded' ] );
	}

	/**
	 * Add compatibility hooks and filters
	 *
	 * @return void
	 * @since 6.0
	 */
	public function add_compatibility() {
		if ( defined( 'YITH_WC_Min_Max_Qty' ) ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'yith_wc_min_max_qty_wp_enqueue_scripts' ], 4 );
		}
	}

	/**
	 * Add compatibility hooks and filters
	 *
	 * @return void
	 * @since 7.4.1.0
	 */
	public function add_compatibility_plugins_loaded() {
		add_action( 'init', [ $this, 'yith_deposits' ], 1000 );
	}

	/**
	 * Yith min max quantities
	 *
	 * @return void
	 * @since 6.0
	 */
	public function yith_wc_min_max_qty_wp_enqueue_scripts() {
		if ( THEMECOMPLETE_EPO()->can_load_scripts() ) {
			wp_enqueue_script( 'themecomplete-comp-yith-wc-min-max-qty', THEMECOMPLETE_EPO_COMPATIBILITY_URL . 'assets/js/cp-yith-wc-min-max-qty.js', [ 'jquery' ], THEMECOMPLETE_EPO_VERSION, true );
		}
	}

	/**
	 * Yith Deposits
	 *
	 * @return void
	 * @since 7.4.1
	 */
	public function yith_deposits_wp_enqueue_scripts() {
		if ( THEMECOMPLETE_EPO()->can_load_scripts() ) {
			wp_enqueue_script( 'themecomplete-comp-yith-deposits', THEMECOMPLETE_EPO_COMPATIBILITY_URL . 'assets/js/cp-yith-deposits.js', [ 'jquery' ], THEMECOMPLETE_EPO_VERSION, true );
		}
	}

	/**
	 * Yith Deposits
	 *
	 * @return void
	 * @since 7.4.1
	 */
	public function yith_deposits() {
		if ( class_exists( 'YITH_WCDP_Cart' ) ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'yith_deposits_wp_enqueue_scripts' ], 4 );
			$class_name = 'YITH_WCDP_Cart';
			$method     = 'update_cart_item';

			$callback = [ $class_name, $method ];

			if ( has_filter( 'woocommerce_add_cart_item', $callback ) ) {
				remove_filter( 'woocommerce_add_cart_item', $callback, 30 );
				add_filter( 'wc_epo_adjusted_cart_item', [ $this, 'yith_deposits_wc_epo_adjusted_cart_item' ] );
			}

			$method   = 'update_cart_item_from_session';
			$callback = [ $class_name, $method ];
			if ( has_filter( 'woocommerce_get_cart_item_from_session', $callback ) ) {
				remove_filter( 'woocommerce_get_cart_item_from_session', $callback, 110 );
				add_filter( 'wc_epo_get_cart_item_from_session', [ $this, 'yith_deposits_wc_epo_get_cart_item_from_session' ], 10, 2 );
			}
		}
	}

	/**
	 * Modifies the cart item
	 *
	 * @param array<mixed> $cart_item The cart item.
	 * @return array<mixed>
	 * @since 7.4.1
	 */
	public function yith_deposits_wc_epo_adjusted_cart_item( $cart_item = [] ) {
		$cart_item = YITH_WCDP_Cart::update_cart_item( $cart_item );
		return $cart_item;
	}

	/**
	 * Gets the cart from session.
	 *
	 * @param array<mixed> $cart_item The cart item.
	 * @param array<mixed> $values Cart item values.
	 * @return array<mixed>
	 * @since 7.4.1
	 */
	public function yith_deposits_wc_epo_get_cart_item_from_session( $cart_item = [], $values = [] ) {
		$cart_item = YITH_WCDP_Cart::update_cart_item_from_session( $cart_item, $values );
		return $cart_item;
	}
}
