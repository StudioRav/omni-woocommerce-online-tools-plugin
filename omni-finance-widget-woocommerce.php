<?php
/*
 * Plugin Name: Omni Capital Retail Finance Payment Gateway for Woocommerce
 * Plugin URI: https://www.omnicapitalretailfinance.co.uk/
 * Description: Allow your customers to pay by finance via Omni Capital Retail Finance on Woocommerce
 * Author: Omni Capital Retail Finance
 * Author URI: https://www.omnicapitalretailfinance.co.uk/
 * Version: 1.4
 */


/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'ocrf_add_gateway_class' );
function ocrf_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Ocrf_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'ocrf_init_gateway_class' );
function ocrf_init_gateway_class() {

	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	// If we made it this far, then include our Gateway Class
	class WC_Ocrf_Gateway extends WC_Payment_Gateway {

 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {

			$this->id = 'omni_finance'; // payment gateway plugin ID
			$this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = true; // in case you need a custom credit card form
			$this->method_title = 'Pay by Finance - Omni Capital Retail Finance';
			$this->method_description = 'Omni Capital Retail Finance for WooCommerce'; // will be displayed on the options page
    	$this->order_button_text = __('Pay by Finance', 'woocommerce');
			// gateways can support subscriptions, refunds, saved payment methods,
			// but in this tutorial we begin with simple payments
			$this->supports = array(
				'products'
			);

			// Method with all the options fields
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->environment = $this->get_option( 'environment' );
			$this->template_id = $this->get_option( 'template_id' );

			/** SOF code to add min order and country restriction **/
			$this->enabled_uk_only = $this->get_option( 'uk_only' );
			$this->min_order_value = $this->get_option( 'min_amount' );
			if(is_checkout()){
				global $woocommerce;
				// Will get you cart object
				$cart_total = $woocommerce->cart->total;
				$customer_country = $woocommerce->customer->get_billing_country();
				if($cart_total < $this->min_order_value){
				    $this->enabled = false;
				}
				if($this->enabled_uk_only && $customer_country != 'GB'){
				    $this->enabled = false;
				}
			}
			/** EOF code to add min order and country restriction **/

			// This action hook saves the settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			// We need custom JavaScript to obtain a token
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

			$this->notify_url = home_url('/wc-api/omni_finance');

	        // So let's attach to the api url you create in notify_url - the hook it fires
			add_action('woocommerce_api_omni_finance', array( $this, 'webhook' ) );
		}

		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
		public function init_form_fields(){

			$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable Omni Capital Retail Finance Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'Pay by Finance - Omni Capital Retail Finance',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay by Finance via our super-cool payment gateway.',
				),
				'environment' => array(
					'title'       => 'Test mode',
					'label'       => 'Enable Test Mode',
					'type'        => 'checkbox',
					'description' => 'Place the payment gateway in test mode.',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'template_id' => array(
					'title'       => 'Template ID',
					'type'        => 'text',
					'description' => 'Widget shown on Product and Cart page',
					'default'     => '',
				),
        'min_amount' => array(
            'title'       => 'Min Order Value',
            'type'        => 'text',
            'description' => 'Activate this payment method only on orders above this value',
            'default'     => '0',
            'desc_tip'    => true,
        ),
        'uk_only' => array(
            'title'       => 'Restrict to UK orders only',
            'label'       => 'Restrict to UK orders only',
            'type'        => 'checkbox',
            'description' => '',
            'default'     => 'no'
        )
			);
		}

		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
			echo $this->description;

			$template_id = $this->template_id;
			$gateway_enabled = $this->enabled;

			if($gateway_enabled == 'yes' && $template_id != '') {
				global $woocommerce;
				$total_order = floatval(preg_replace('#[^\d.]#', '', $woocommerce->cart->total));
				$total_order = number_format((float) $total_order, 2, '.', '');

				include 'omni-helper.php';

			}
		}

		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
		public function payment_scripts() {

		}

		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields() {



		}

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment($order_id)
		{
			global $woocommerce;

			if (session_status() === PHP_SESSION_NONE) {
			    session_start();
			}
			$_SESSION['omni-order-id'] = $order_id;

			$customer_order = new WC_Order($order_id);
			// $customer_order->update_status('on-hold', 'Order placed on hold pending credit check with Omni Capital Retail Finance.');

			$order = wc_get_order($order_id);
			$pay_page    = $order->get_checkout_payment_url(false);
			$thanks_page = $order->get_checkout_order_received_url();

			$financeCode = $_POST['financeOption'];
			$deposit_percentage = $_POST['Finance_Deposit'];

			// Get this Order's information so that we know
	        // who to charge and how much
			setcookie('omni_order_id', $order_id, time() + 3600, '/');

			//test/live env
			$environment = "FALSE" ;
			if($this->environment){
					$environment = ($this->environment == "yes") ? 'TRUE' : 'FALSE';
			}
			// Decide which URL to post to
			$environment_url = ("FALSE" == $environment) ? 'https://onlinetools.omnicapital.co.uk/api/omni/createLoanApplication' : 'https://test.onlinetools.omnicapital.co.uk/api/omni/createLoanApplication';

			// Work out deposit amount based on order total
			$total_order = floatval(preg_replace('#[^\d.]#', '', $woocommerce->cart->total));
			$order_deposit = $total_order * $deposit_percentage / 100;
			$order_deposit = number_format((float) $order_deposit, 2, '.', '');

			// Order name should be all the products in the order
			$items = $customer_order->get_items();
			$order_name = '';
			foreach ($items as $item) {
				$order_name .= $item['name'] . '(' . $item['qty'] . ')' . ', ';
			}

			$payload = array(
				'order_id' => $order_id,
				'order_name' => $order_name,
				'price' => number_format((float) $total_order, 2, '.', ''),
				'finance_option_code' => $financeCode,
				'deposit_amount' => $order_deposit,
				'templateId' => $this->template_id,
			);

			// Arguments to post variables properly
			$args3 = array(
				'body' => $payload,
				'data_format' => 'body',
				'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
				'timeout' => '5',
				'redirection' => '5',
				'httpversion' => '1.0',
				'blocking' => true,
				'headers' => array(),
				'cookies' => array(),
				'sslverify' => FALSE
			);

			/* Stop trash order as per instructions */
			// wp_trash_post($order_id);

			// Gets the page response from the post to the variable
			$response = wp_remote_post($environment_url, $args3);

			$redirecturl = '';

			//throw new Exception(__($response->get_error_message(), 'finance-gateway'));

			// If error from response output
			if (is_wp_error($response))
				{
					$customer_order->update_status('cancelled', 'There was a problem redirecting to the payment gateway. If this problem persists please contact Customer Services.');
					throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'finance-gateway'));
				}

			if (empty($response['body']))
				throw new Exception(__('No response back.', 'finance-gateway'));

			$customer_order->update_status('on-hold', 'Pending credit check with Omni Capital Retail Finance.');

			return array(
				'result' => 'success',
				'redirect' => $response['body']
			);
		}

		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		 public function webhook() {
 		    global $woocommerce;

 		    if(isset($_REQUEST['omni_status'])) {

 		        if(isset($_COOKIE['omni_order_id'])) {
 		            $order = wc_get_order($_COOKIE['omni_order_id']);

 		            if(isset($order)) {
						/* Sometimes Webhook not return us wc-ocrf_awaiting_fu status in $_REQUEST['status'] then $_REQUEST['omni_status'] handle the process */
 		                if($_REQUEST['omni_status'] == 'accepted') {
 		                    // $order->update_status( 'processing' );

 		                }

 		                $thanks_page = $order->get_checkout_order_received_url();

 		                /* Sometimes Webhook not return us wc-ocrf_chkdeclind status in $_REQUEST['status'] then $_REQUEST['omni_status'] handle the process */
 		                if($_REQUEST['omni_status'] == 'declined') {
 		                    // $customer_order->update_status('cancelled');
 		                    $cart_url = wc_get_page_permalink('cart');
 		                    wc_add_notice( sprintf( __( "Unfortunately your application for finance was not successful. Please use another payment method.", "" ) ) ,'error' );
 		                    /* wp_delete_post($_COOKIE['omni_order_id']); */
 		                    wp_safe_redirect($cart_url);
 		                    /* setcookie('omni_declined', $_COOKIE['omni_order_id'], time() + 3600, '/'); */
 		                } else {
 		                    wp_safe_redirect($thanks_page);
 		                }
 		            //we show appropriate message in the hooks below
 		            } else {
						/* In case not getting order or order missing then handle */
 		                // $pay_page    = $order->get_checkout_payment_url(false);
 		                $notice = 'No order found. Please place an order to proceed.';
 		                $notice_type = 'error';
 		                $pay_page    = wc_get_endpoint_url( 'order-pay', '', wc_get_checkout_url() );
 		                wc_add_notice($notice, $notice_type);
 		                wp_safe_redirect($pay_page);
 		            }
 		        } else {
					/* In case not getting omni_order_id from cookie then handle */
 		            // $pay_page    = $order->get_checkout_payment_url(false);
 		            $notice = 'No order found. Please place an order to proceed.';
 		            $notice_type = 'error';
 		            $pay_page    = wc_get_endpoint_url( 'order-pay', '', wc_get_checkout_url() );
 		            wc_add_notice($notice, $notice_type);
 		            wp_safe_redirect($pay_page);
 		        }
 		    } else {
 		        $response = $_REQUEST;
 		        $restockFlag = $response['restockFlag'];
 		        $get_order_id = $response['orderId'];
						$credit_id = $response['creditRequestID'];

 		        $order = wc_get_order($get_order_id);
 		        if(isset($order)) {
 		            $prevStatus = $order->get_status();
 		            if ($restockFlag == "true" && !in_array($prevStatus, array('ocrf_cancelled', 'ocrf_refunded', 'ocrf_chkdeclind'))) {
 		                foreach ($order->get_items() as $item_id => $item) {
 		                    $product = $item->get_product();
 		                    $qty = $item->get_quantity(); // Get the item quantity
 		                    wc_update_product_stock($product, $qty, 'increase');
 		                }
 		            }

								$current_credit_id = get_post_meta($get_order_id, 'omni_credit_id');
								if(empty($current_credit_id)){
									update_post_meta($get_order_id, 'omni_credit_id', $credit_id);
									$order->add_order_note('Credit Request ID: '.$credit_id);
								}

 		            /* As we get CSN/IPN from Omniport we add status update to the order as comment */
 		            $omni_order_stuses = omni_order_stuses_callback();
 		            $note = '';
 		            foreach ($omni_order_stuses as $slug => $label) {
						/* If status matched with OCRF statues */
            if($response['status'] == $slug) {
                /* The text for the note */
                $note = __($label);

 		          /* if OCRF_AWAITING_FULFILMENT change status to PROCESSING
							*   Webhook return us wc-ocrf_awaiting_fu status in $_REQUEST['status'] then handler
							* */
							if($response['status'] == 'wc-ocrf_awaiting_fu') {
								// $order->add_order_note($note);
                $order->update_status('processing', 'Deposit for finance application successsfully taken. Order has now been confirmed.');

                /* Get the WC_Email_New_Order object */
                $email_new_order = WC()->mailer()->get_emails()['WC_Email_New_Order'];
                $email_new_order1 = WC()->mailer()->get_emails()['WC_Email_Customer_Processing_Order'];
                /* Sending the new Order email notification for an $order_id (order ID) */
                $email_new_order->trigger($get_order_id);
                $email_new_order1->trigger($get_order_id);

								$thanks_page = $order->get_checkout_order_received_url();
								wp_safe_redirect($thanks_page);
              }
 		          /* if OCRF_CREDIT_CHECK_DECLINED change status to CANCELLED
							*  Webhook return us wc-ocrf_chkdeclind status in $_REQUEST['status'] then handler
							* */
 		          if($response['status'] == 'wc-ocrf_chkdeclind') {
								$order->update_status('failed', 'Unfortunately the finance application was declined.');
								/* Add the note
			 		            if(isset($note)) {
			 		                $order->add_order_note($note);
			 		            }*/

								$cart_url = wc_get_page_permalink('cart');
	 		                    wc_add_notice( sprintf( __( "Unfortunately your application for finance was not successful. Please use another payment method.", "" ) ) ,'error' );
								wp_safe_redirect($cart_url);
							}
							/* if OCRF - SIGN DOCUMENTS change status to ON HOLD
							*  Webhook return us wc-ocrf_sign_doc status in $_REQUEST['status'] then handler
							* */
							if($response['status'] == 'wc-ocrf_sign_doc') {
								$order->update_status('on-hold');
								/* Add the note
											if(isset($note)) {
													$order->add_order_note($note);
											}*/
							}
							/* if OCRF_CREDIT_CHECK_REFERRED change status to ON HOLD
							*  Webhook return us wc-ocrf_checkrffred status in $_REQUEST['status'] then handler
							* */
 		          if($response['status'] == 'wc-ocrf_checkrffred') {
								$order->update_status('on-hold', 'Loan application has been referred - Decision to be confirmed shortly.');
								/* Add the note
			 		            if(isset($note)) {
			 		                $order->add_order_note($note);
			 		            }*/
							}
							/* if OCRF_FINANCE_OFFER_WITHDRAWN change status to FAILED
							*  Webhook return us wc-ocrf_withdrawn status in $_REQUEST['status'] then handler
							* */
 		          if($response['status'] == 'wc-ocrf_withdrawn') {
								$order->update_status('failed', 'The finance application has been withdrawn.');
								/* Add the note
			 		            if(isset($note)) {
			 		                $order->add_order_note($note);
			 		            }*/
							}
							/* if OCRF_ORDER_CANCELLED change status to FAILED
							*  Webhook return us wc-ocrf_cancelled status in $_REQUEST['status'] then handler
							* */
							if($response['status'] == 'wc-ocrf_cancelled') {
								$order->update_status('failed', 'The finance application has been cancelled.');
								/* Add the note
											if(isset($note)) {
													$order->add_order_note($note);
											}*/
							}
 		         }
 		        }
 		            /* Add the note for all OCRF webhook response */
 		            if(isset($note)) {
 		                $order->add_order_note($note);
 		            }
 		        }
 		    }
 		}
	}	//class ends here

	/* function my_function() {
		$payment_method = $_POST['payment_method'];
		if($payment_method == 'omni_finance'){
		}
	}
	add_action( "woocommerce_checkout_process", "my_function"); */

	function omni_order_stuses_callback(){

		$omni_order_stuses = array(
			'wc-ocrf_checkrffred' 		=> 'OCRF - CREDIT CHECK REFERRED',
			'wc-ocrf_fulfilled' 		=> 'OCRF - ORDER FULFILLED',
			'wc-ocrf_awaiting_fu' 		=> 'OCRF - AWAITING FULFILMENT',
			'wc-ocrf_cust_actin'  		=> 'OCRF - CUSTOMER ACTION REQUIRED',
			'wc-ocrf_refunded' 			=> 'OCRF - ORDER REFUNDED',
			'wc-ocrf_complete' 			=> 'OCRF - COMPLETE',
			'wc-ocrf_withdrawn' 		=> 'OCRF - FINANCE OFFER WITHDRAWN',
			'wc-ocrf_sign_doc' 			=> 'OCRF - SIGN DOCUMENTS',
			'wc-ocrf_chkdeclind' 		=> 'OCRF - CREDIT CHECK DECLINED',
			'wc-ocrf_cancelled' 		=> 'OCRF - ORDER CANCELLED',
			'wc-ocrf_approved' 			=> 'OCRF - APPROVED',
		);

		return $omni_order_stuses;
	}

	$omni_order_stuses = omni_order_stuses_callback();

	foreach ($omni_order_stuses as $slug => $label) {

		register_post_status( $slug, array(
			'label'                     => $label,
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>' ),
		) );
	}

	$filters_priority = PHP_INT_MAX;

	add_filter( 'wc_order_statuses', 'add_omni_order_statuses', $filters_priority );

	function add_omni_order_statuses( $order_statuses ) {

		$omni_order_stuses = omni_order_stuses_callback();

		foreach ($omni_order_stuses as $slug => $label) {

			$order_statuses[$slug] = _x( $label, 'WooCommerce Order status', 'text_domain' );
		}
		return $order_statuses;
	}
}	//init function ends here

register_deactivation_hook( __FILE__, 'omni_capital_deactivation_callback' );

function omni_capital_deactivation_callback(){

	function ocrf_remove_order_statuses( $wc_statuses_arr ){

		$omni_order_stuses = omni_order_stuses_callback();

		foreach ($omni_order_stuses as $slug => $label) {

			if( isset( $wc_statuses_arr[$slug] ) ) {
				unset( $wc_statuses_arr[$slug] );
			}
		}
	}
	add_filter( 'wc_order_statuses', 'ocrf_remove_order_statuses' );
}

// add_action( 'woocommerce_before_add_to_cart_button', 'add_content_before_addtocart_button_func' );
add_action( 'woocommerce_single_product_summary', 'add_content_before_addtocart_button_func', 15 );

/*
 * Content above "Add to cart" Button.
 */
function add_content_before_addtocart_button_func() {
	$_product = wc_get_product( get_the_ID() );
	$prod_price = 0;
	if( $_product->is_type( 'simple' ) ){
	  // a simple product
		$prod_price = $_product->get_price();
	} elseif( $_product->is_type( 'variable' ) ){
	  // a variable product
		$prod_price = $_product->get_variation_price();
	}

	$omni_settings = get_option( 'woocommerce_omni_finance_settings');
	$template_id = $omni_settings['template_id'];
	$gateway_enabled = $omni_settings['enabled'];
	$test = $omni_settings['environment'];
	$environmentscript = "FALSE" ;
	if($test){
			$environmentscript = ($test == "yes") ? 'TRUE' : 'FALSE';
	}
	// Decide which URL to post to
	$environmentscript_url = ("FALSE" == $environmentscript) ? 'https://onlinetools.omnicapital.co.uk/static/js/widgets.js' : 'https://test.onlinetools.omnicapital.co.uk/static/js/widgets.js';

	if($gateway_enabled == 'yes' && $template_id != '') {
		echo "<meta http-equiv='Content-Type' content='text/html; charset=utf-8' />
		<div id='root123'></div>
		<script src='".$environmentscript_url."' id='finWidgetScript' data-config=\"{'name': 'w1', 'templateId' : '".$template_id."', 'price': '".$prod_price."', 'config': {'targetElementId': 'root123'}}\"></script>
		<script>
			jQuery(document).ready(function($) {

				jQuery( '.variations_form' ).on( 'woocommerce_variation_select_change', function () {
				// alert(21);
				} );

				// Fired when the user selects all the required dropdowns / attributes
				// and a final variation is selected / shown
				jQuery( '.single_variation_wrap' ).on( 'show_variation', function ( e, variation ) {
					// alert(variation.display_price);
					let input = document.getElementById('curPrice');
					let lastValue = input.value;
					input.value = variation.display_price;
					let event = new Event('input', { bubbles: true });
					event.simulated = true;
					let tracker = input._valueTracker;
					if (tracker) {
						tracker.setValue(lastValue);
					}
					input.dispatchEvent(event);
				} );

			});
		</script>";
	}
}

add_action( 'woocommerce_proceed_to_checkout', 'show_finance_after_cart' );
// add_action( 'woocommerce_after_cart', 'show_finance_after_cart' );
/*
 * Content above "Add to cart" Button.
 */
function show_finance_after_cart() {
	global $woocommerce;
	$total_order = floatval(preg_replace('#[^\d.]#', '', $woocommerce->cart->total));
	$total_order = number_format((float) $total_order, 2, '.', '');
	$omni_settings = get_option( 'woocommerce_omni_finance_settings');
	$template_id = $omni_settings['template_id'];
	$gateway_enabled = $omni_settings['enabled'];
	$test = $omni_settings['environment'];
	if($test){
			$environmentscript = ($test == "yes") ? 'TRUE' : 'FALSE';
	}
	$environmentscript_url = ("FALSE" == $environmentscript) ? 'https://onlinetools.omnicapital.co.uk/static/js/widgets.js' : 'https://test.onlinetools.omnicapital.co.uk/static/js/widgets.js';

	if($gateway_enabled == 'yes' && $template_id != '') {
		echo "<meta http-equiv='Content-Type' content='text/html; charset=utf-8' />
		<div id='root123'></div>
		<script src='".$environmentscript_url."' id='finWidgetScript' data-config=\"{'name': 'w1', 'templateId' : '".$template_id."', 'price': '".$total_order."', 'config': {'targetElementId': 'root123'}}\"></script>";
	}
}

// add_action( 'wp_footer', 'footer_hook' );
function footer_hook() {

}

// add_action( 'wp_enqueue_scripts', 'my_custom_script_load' );
function my_custom_script_load(){
//   wp_enqueue_script( 'my-custom-script', get_stylesheet_directory_uri() . '/custom-scripts', array( 'jquery' ) );
}

// add_action( 'woocommerce_thankyou', 'view_order_and_thankyou_page', 20 );
function view_order_and_thankyou_page( $order_id ){

	if($_COOKIE['omni_declined'] == $_COOKIE['omni_order_id']) {

	}

}

add_filter( 'woocommerce_endpoint_order-received_title', 'thank_you_title' );
function thank_you_title( $old_title )	{
	$order_id = wc_get_order_id_by_order_key( $_GET['key'] );
	$order = wc_get_order( $order_id );
	$notice_type = 'Error';

	if(isset($order)) {
		$orderStatus = $order->get_status();

		if ($orderStatus == "ocrf_approved") {
			$notice_type = 'success';
		} else if ($orderStatus == "ocrf_checkrffred") {
			$notice_type = 'notice';
		} else if ($orderStatus == "ocrf_chkdeclind") {
			$notice_type = 'notice';
		} else if ($orderStatus == "ocrf_cancelled") {
			$notice_type = 'notice';
		}
	}

	return $notice_type;
}

add_filter( 'woocommerce_thankyou_order_received_text', 'thank_you_text', 20, 2 );
function thank_you_text( $thank_you_title, $order )	{
	$notice = 'No order found. Please place an order to proceed.';

	if(isset($order)) {

		if(isset($order)) {
			$orderStatus = $order->get_status();

			if ($orderStatus == "ocrf_approved") {
				$notice = 'Success! Your finance application was approved!';
			} else if ($orderStatus == "ocrf_checkrffred") {
				$notice = 'Your order has temporarily been put on hold whilst we wait for a decision on your application.';
			} else if ($orderStatus == "ocrf_chkdeclind") {
				$notice = 'Sorry! Your finance application was declined. Please re-order and choose a new payment option or contact the store for further assistance.';
			} else if ($orderStatus == "ocrf_cancelled") {
				$notice = 'Cancelled.';
			} else {
                $notice = 'Thank you for order';
				//$notice = 'Your application is in status '.$orderStatus;
			}
		}
	}

	return $notice;
}
