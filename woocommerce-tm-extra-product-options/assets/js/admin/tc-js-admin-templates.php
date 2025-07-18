<?php
/**
 * The admin javascript-based template for displayed javascript generated html code
 *
 * NOTE that this file is not meant to be overriden
 *
 * @see     https://codex.wordpress.org/Javascript_Reference/wp.template
 * @author  ThemeComplete
 * @package Extra Product Options/Templates
 * @version 6.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<script class="tm-hidden" type="text/template" id="tmpl-tc-floatbox">
	<div class="header">
		<h3>{{{ data.title }}}</h3>
		<# if (data.uniqid){ #>
			<span data-uniqid="{{{ data.uniqid }}}" class="tm-element-uniqid">{{{ data.uniqidtext }}}{{{ data.uniqid }}}</span>
			<# } #>
	</div>
	<div id="{{{ data.id }}}" class="float-editbox">{{{ data.html }}}</div>
	<div class="footer">
		<div class="inner">
		<button type="button" class="tc-button floatbox-cancel">{{{ data.cancel }}}</button>
		<button type="button" class="tc tc-button floatbox-update">{{{ data.update }}}</button>
		</div>
	</div>
</script>
<script class="tm-hidden" type="text/template" id="tmpl-tc-floatbox-edit">
	<div class="header">
		<h3>{{{ data.title }}}</h3>
	</div>
	<div id="{{{ data.id }}}" class="float-editbox">{{{ data.html }}}</div>
	<div class="footer">
		<div class="inner">
			<button type="button" class="tc-button floatbox-edit-cancel">{{{ data.cancel }}}</button>
			<button type="button" class="tc tc-button floatbox-edit-update">{{{ data.update }}}</button>
		</div>
	</div>
</script>
<script class="tm-hidden" type="text/template" id="tmpl-tc-floatbox-import">
	<div class="header">
		<h3>{{{ data.title }}}</h3>
	</div>
	<div id="{{{ data.id }}}" class="float-editbox">{{{ data.html }}}</div>
	<div class="footer">
		<div class="inner">
			<button type="button" class="tc-button floatbox-cancel">{{{ data.cancel }}}</button>
		</div>
	</div>
</script>
<script class="tm-hidden" type="text/template" id="tmpl-tc-constant-template">
	<div class="constantrow">
		<div class="constant-name-container">
			<div class="constant-label-wrap constant-name-text{{{ data.labelnameclass }}}">
				<label for="constant-name{{{ data.id }}}">{{{ data.labelname }}}</label>
				<input id="constant-name{{{ data.id }}}" type="text" value="{{{ data.constantname }}}" class="constant-name">
			</div>
		</div>
		<div class="constant-value-container">
			<div class="constant-value-wrap">
				<div class="constant-label-wrap constant-value-text{{{ data.labelvalueclass }}}">
					<label for="constant-value{{{ data.id }}}">{{{ data.labelvalue }}}</label>
					<input id="constant-value{{{ data.id }}}" type="text" value="{{{ data.constantvalue }}}" class="constant-value">
				</div>
				<div class="constant-value-delete">
					<div class="tc-constant-delete">
						<button type="button" class="tmicon tcfa tcfa-times delete"></button>
					</div>
				</div>
			</div>
		</div>
		<div class="constant-add-container">
			<button type="button" class="tmicon tcfa tcfa-plus add tc-add-constant"></button>
		</div>
	</div>
</script>
