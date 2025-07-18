<?php
/**
 * The template for displaying the dynamic calculations element for the builder mode
 *
 * This template can be overridden by copying it to yourtheme/tm-extra-product-options/tm-dynamic.php
 *
 * NOTE that we may need to update template files and you
 * (the plugin or theme developer) will need to copy the new files
 * to your theme or plugin to maintain compatibility.
 *
 * @author  ThemeComplete
 * @package Extra Product Options/Templates
 * @version 6.4.3
 */

defined( 'ABSPATH' ) || exit;
if ( isset( $class_label, $element_id, $name, $fieldtype, $rules, $original_rules, $rules_type, $operation_mode, $hide, $result_label, $formula, $calculation_type ) ) :
	$class_label      = (string) $class_label;
	$element_id       = (string) $element_id;
	$name             = (string) $name;
	$fieldtype        = (string) $fieldtype;
	$rules            = (string) $rules;
	$original_rules   = (string) $original_rules;
	$rules_type       = (string) $rules_type;
	$operation_mode   = (string) $operation_mode;
	$hide             = (string) $hide;
	$result_label     = (string) $result_label;
	$formula          = (string) $formula;
	$calculation_type = (string) $calculation_type;

	if ( ! isset( $textbeforeprice ) ) {
		$textbeforeprice = '';
	}
	if ( ! isset( $textafterprice ) ) {
		$textafterprice = '';
	}
	?>
<li class="tmcp-field-wrap"><div class="tmcp-field-wrap-inner">
	<label class="tc-col tm-epo-field-label<?php echo esc_attr( $class_label ); ?>" for="<?php echo esc_attr( $element_id ); ?>">
		<?php
		$input_args = [
			'nodiv'      => 1,
			'default'    => 1,
			'type'       => 'input',
			'input_type' => 'hidden',
			'tags'       => [
				'id'                    => $element_id,
				'name'                  => $name,
				'value'                 => '1',
				'class'                 => $fieldtype . ' tm-epo-field tmcp-dynamic tmcp-textfield ' . str_replace( '_', '-', $operation_mode ),
				'data-price'            => '',
				'data-rules'            => $rules,
				'data-original-rules'   => $original_rules,
				'data-rulestype'        => $rules_type,
				'data-formula'          => $formula,
				'data-calculation-type' => $calculation_type,
			],
		];
		if ( ! empty( $tax_obj ) ) {
			$input_args['tags']['data-tax-obj'] = $tax_obj;
		}
		if ( THEMECOMPLETE_EPO()->associated_per_product_pricing === 0 ) {
			$input_args['tags']['data-no-price'] = true;
		}

		if ( 'dynamic_product_price' !== $operation_mode && 'override_product_price' !== $operation_mode ) {
			$input_args['tags']['disabled'] = 'disabled';
		}

		if ( ! empty( $tm_element_settings['result_as_price'] ) ) {
			$input_args['tags']['class'] .= ' result-as-price';
		}

		$input_args = apply_filters(
			'wc_element_input_args',
			$input_args,
			isset( $tm_element_settings ) && isset( $tm_element_settings['type'] ) ? $tm_element_settings['type'] : '',
			isset( $args ) ? $args : [],
		);

		THEMECOMPLETE_EPO_HTML()->create_field( $input_args, true );

		if ( empty( $hide ) && '' !== $result_label ) {
			echo apply_filters( 'wc_epo_kses', wp_kses_post( $result_label ), $result_label, false ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
		</label>
	<?php require THEMECOMPLETE_EPO_TEMPLATE_PATH . '_price.php'; ?>
	<?php if ( empty( $hide ) && ( 'calculation' === $operation_mode || 'change_product_weight' === $operation_mode ) ) : ?>
	<span class="tc-col-auto tc-result-wrap">
		<?php if ( '' !== $textbeforeprice ) : ?>
		<span class="before-amount"><?php echo apply_filters( 'wc_epo_kses', esc_html( $textbeforeprice ), $textbeforeprice ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<?php endif; ?>
		<span class="tc-result"></span>
		<?php if ( '' !== $textafterprice ) : ?>
			<span class="after-amount"><?php echo apply_filters( 'wc_epo_kses', esc_html( $textafterprice ), $textafterprice ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<?php endif; ?>
		</span>
	</span>
	<?php endif; ?>
	<?php do_action( 'tm_after_element', isset( $tm_element_settings ) ? $tm_element_settings : [] ); ?>
</div></li>
	<?php
endif;
