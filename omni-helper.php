<?php
$template_id = $this->template_id;
global $woocommerce;
$total_order1 = floatval(preg_replace('#[^\d.]#', '', $woocommerce->cart->total));
$total_order1 = number_format((float) $total_order1, 2, '.', '');
$billcountry = WC()->customer->get_billing_country();

if( empty($billcountry) ){
    $package = WC()->billing->get_packages()[0];
    if( ! isset($package['destination']['country']) ) return $passed;
    $billcountry = $package['destination']['country'];
}

$environmentscript2 = "FALSE" ;
if($this->environment){
    $environmentscript2 = ($this->environment == "yes") ? 'TRUE' : 'FALSE';
}
// Decide which URL to post to
$environmentscript_url2 = ("FALSE" == $environmentscript2) ? 'https://onlinetools.omnicapital.co.uk/static/js/widgets.js?cachebuster=' : 'https://test.onlinetools.omnicapital.co.uk/static/js/widgets.js?cachebuster=';
 ?>
<div id="ocrf_widget"></div>
<script>
	var scriptElement = document.getElementById('finWidgetScript');
	if(scriptElement) {
		console.log('Reloading OCRF Widget');
		scriptElement.remove()
		var s = document.createElement( 'script' );
		s.setAttribute( 'src', "<?= $environmentscript_url2; ?>" + new Date().getTime());
		s.setAttribute( 'id', "finWidgetScript" );
		s.setAttribute( 'data-config', "{'name': 'w1', 'templateId' : '<?php echo $template_id; ?>', 'price': '<?php echo $total_order; ?>', 'config': {'targetElementId': 'ocrf_widget'}, 'checkout': 'true'}" );
		document.body.appendChild( s );
	} else {
		console.log('Loading OCRF Widget');
		setTimeout(function() {
			var s = document.createElement( 'script' );
			s.setAttribute( 'src', "<?= $environmentscript_url2; ?>" + new Date().getTime());
			s.setAttribute( 'id', "finWidgetScript" );
			s.setAttribute( 'data-config', "{'name': 'w1', 'templateId' : '<?php echo $template_id; ?>', 'price': '<?php echo $total_order; ?>', 'config': {'targetElementId': 'ocrf_widget'}, 'checkout': 'true'}" );
			document.body.appendChild( s );
		}, 1500)
	}
</script>
<input type="hidden" name="financeOption" id="financeOption" >
<input type="hidden" name="Finance_Deposit" id="Finance_Deposit" >
<script>
jQuery('form.checkout').on('checkout_place_order', function(event) {
	if(jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() == 'omni_finance') {
		if(jQuery("#widget-product-id").length) {
			let wid = jQuery("#widget-product-id").html();
			let depositPecentage = Number(jQuery('#widget-deposit').html().split('%')[0]);
			jQuery("#financeOption").val(wid);
			jQuery("#Finance_Deposit").val(depositPecentage);
			// console.log('wid is '+wid);
			// console.log('deposit is '+depositPecentage);
		} else {
			// alert('Please select a Product');
			e.preventDefault();
		}
	} else {
		console.log('checkout');
	}
});


</script>
