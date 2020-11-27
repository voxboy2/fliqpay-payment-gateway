<?php
/*
 * Plugin Name:fFiqpay Payment Gateway
 * Plugin URI: https://fliqpay.com/
 * Description: accept crypto payments on your store.
 * Author: Efe Stephen Ebieroma
 * Author URI: http://efeone.com
 * Version: 1.1
 *
*/

if (!defined('ABSPATH')) {
    exit;
  }
  add_filter('woocommerce_payment_gateways', 'fliqpay_add_gateway_class');
  
  /*≈
   *This action hook enables the settings link in the settings page
   */
  add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'fliqpay_plugin_action_links');
  function fliqpay_plugin_action_links($links)
  {
    
    $settings_link = array(
      'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=fliqpay') . '" title="' . 'View Fliqpay WooCommerce Settings' . '">' . 'Settings' . '</a>'
    );
    
    return array_merge($settings_link, $links);
    
  }
  
  

  
  function fliqpay_add_gateway_class($gateways)
  {
    $gateways[] = 'WC_Fliqpay_Gateway'; // your class name is here
    return $gateways;
  }
  
  /*
   * The class itself, please note that it is inside plugins_loaded action hook
   */
  add_action('plugins_loaded', 'fliqpay_init_gateway_class');

  
  
  
  
  function fliqpay_init_gateway_class()
  {
    
    
    class WC_Fliqpay_Gateway extends WC_Payment_Gateway
    {
      
      /**
       * Class constructor
       */
      public function __construct()
      {
        
        $this->id                 = 'fliqpay'; // payment gateway plugin ID
        $this->icon               = ''; // URL of the icon that will be displayed on checkout page near your gateway name
        $this->has_fields         = true; // in case you need a custom credit card form
        $this->method_title       = 'Fliqpay Gateway';
        $this->method_description = sprintf('Fliqpay provides a payment platform that enables local and global businesses accept and disburse payments quickly and seamlessly while saving time and money using either bank transfers or credit card payments, <a href="%1$s" target="_blank">Sign up on fliqpay.com</a>  to  <a href="%2$s" target="_blank">get your API keys</a>','https://fliqpay.com','https://app.fliqpay.com/settings/others'); // will be displayed on the options page
        
        // gateways can support subscriptions, refunds, saved payment methods,
        $this->supports = array(
          'products'
        );
        
        // Method with all the options fields
        $this->init_form_fields();
        
        // Load the settings.
        $this->init_settings();
        
        //Get settings values
        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled     = $this->get_option('enabled');
        $this->business_key  = $this->get_option('business_key');
        $this->callbackUrl = $this->get_option('callbackUrl');

        
        
        //===HOOKS=====
        
        // This action hook saves the settings
        add_action('wp_enqueue_scripts', array(
          $this,
          'payment_scripts'
        ));
        
        add_action('admin_enqueue_scripts', array(
          $this,
          'admin_scripts'
        ));
        
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
          $this,
          'process_admin_options'
        ));
        
        add_action('woocommerce_receipt_' . $this->id, array(
          $this,
          'receipt_page'
        ));
        // Payment listener/API hook.
          add_action( 'woocommerce_api_wc_fliqpay_gateway', array( $this, 'complete_fliqpay_transaction' ) );


        

      }
      

    
        public function admin_notices() {
  
          if ( $this->enabled == 'no' ) {
              return;
          }
  
          // Check required fields.
          if ( ! ( $this->business_key ) ) {
              echo '<div class="error"><p>' . sprintf( 'Please enter your fliqpay merchant details <a href="%s">here</a> to be able to use the fliqpay WooCommerce plugin.', admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fliqpay' ) ) . '</p></div>';
              return;
          }
  
      }
      
      
      /**
       * Initialise Gateway Settings Form Fields.
       */
      public function init_form_fields()
      {
        
        $this->form_fields = array(
          'enabled' => array(
            'title' => 'Enable/Disable',
            'label' => 'Enable Fliqpay Gateway',
            'type' => 'checkbox',
            'description' => 'Enable Fliqpay as a payment option on the checkout page',
            'default' => 'no',
            'desc_tip' => true
          ),
          'title' => array(
            'title' => 'Title',
            'type' => 'text',
            'description' => 'This controls the payment method title which the user sees during checkout.',
            'default' => 'pay with crypto currency',
            'desc_tip' => true
          ),
          'description' => array(
            'title' => 'Description',
            'type' => 'textarea',
            'description' => 'This controls the payment method description which the user sees during checkout.',
            'default' => 'Initialize payment with your credit or debit cards'
          ),
          'business_key' => array(
            'title' => 'business Key',
            'description' => 'Enter your business key here',
            'type' => 'text'
          ),

          'callbackUrl' => array(
            'title' => 'callbackUrl',
            'description' => 'Enter your call back url here',
            'type' => 'text'
          )
        );
        
        
      }
      
      /** 
      * There are no payment fields for korapay, but we want to show the description if set. 
      **/ 
      public function payment_fields()
      {
        if ( $this->description ) {
              echo wpautop( wptexturize( $this->description ) );
          }
        
      }
      
      /*
       * Outputs scripts used for korapay payment
       */
      public function payment_scripts()
      {

        
        if (!is_checkout_pay_page()) {
          return;
        }
        
        
        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ('no' === $this->enabled) {
          return;
        }
        
        // no reason to enqueue JavaScript if API keys are not set
        if (empty($this->business_key)){
          return;
        }
        
        //This is our order details
        $order_key = urldecode($_GET['key']);
        $order_id  = absint(get_query_var('order-pay'));
        $order     = wc_get_order($order_id);
  
        
        //Load Jquery
        wp_enqueue_script('jquery');
        
        
        //Load The  file
        wp_enqueue_script('init-js', plugins_url('./assets/js/init.js', __FILE__));
        
        $fliqpay_params = array(
          'Key' => $this->business_key,
          // 'callbackUrl' => $this->callbackUrl,
          'redirect_url' => $this->get_return_url($order)
        );
  
        if (is_checkout_pay_page() && get_query_var('order-pay')) {
          $email  = method_exists($order, 'get_billing_email') ? $order->get_billing_email() : $order->billing_email;
          $amount = $order->get_total();
          
          $first_name = method_exists($order, 'get_billing_first_name') ? $order->get_billing_first_name() : $order->billing_first_name;
          $last_name  = method_exists($order, 'get_billing_last_name') ? $order->get_billing_last_name() : $order->billing_last_name;
          
          $fliqpay_params['email']  = $email;
          $fliqpay_params['amount'] = $amount;
          $fliqpay_params['name']   = $first_name . ' ' . $last_name;
          $fliqpay_params['orderId']=$order_id;
        }
        
        // we localize 
        wp_localize_script('init-js', 'fliqpay_params', $fliqpay_params);
        
        // wp_enqueue_script('wc_korapay');
      }
      
      /*
       * Fields validation
       */
      public function validate_fields()
      {
        
        
        
        
      }
      
          public function admin_scripts() {
  
          if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
              return;
          }
  
  
          $fliqpay_admin_params = array(
              'plugin_url' => WC_FLIQPAY_URL,
          );
  
          wp_enqueue_script( 'wc_fliqpay_admin',  plugins_url('assets/js/admin.js', __FILE__), array());
  
          wp_localize_script( 'wc_fliqpay_admin', 'wc_fliqpay_admin_params', $fliqpay_admin_params );
  
      }
      /*
       * We're processing the payments here
       */
      public function process_payment($order_id)
      {
        
        global $woocommerce;
        
        
        $order = new WC_Order($order_id);
        
        // we received the order
        // Redirect to the payment page
        return array(
          'result' => 'success',
          'redirect' => $order->get_checkout_payment_url(true)
        );
      }
      
      
      public function receipt_page($order_id)
      {
        
        $order = wc_get_order($order_id);
        $url = WC()->api_request_url('WC_FLIQPAY_GATEWAY');

          
        $fliqpay_params = array(
          'key' => $this->business_key,
          'callbackUrl' => $this->callbackUrl,
          'redirect_url' => $this->get_return_url($order)
        );





        $email  = method_exists($order, 'get_billing_email') ? $order->get_billing_email() : $order->billing_email;
          $amount = $order->get_total();
          
          $first_name = method_exists($order, 'get_billing_first_name') ? $order->get_billing_first_name() : $order->billing_first_name;
          $last_name  = method_exists($order, 'get_billing_last_name') ? $order->get_billing_last_name() : $order->billing_last_name;
          
          $fliqpay_params['email']  = $email;
          $fliqpay_params['amount'] = $amount;
          $fliqpay_params['name']   = $first_name . ' ' . $last_name;
          $fliqpay_params['orderId']=$order_id;
        
        echo '<p>' . 'Thank you for your order, please click the button below to pay with Fliqpay.' . '</p>';
        
        // echo '<div id="fliqpay_form"><form id="order_review" method="post" action="' . WC()->api_request_url( 'WC_Fliqpay_Gateway' ) . '"></form><button class="button alt" id="fliqpay-payment-button">' . 'Pay Now' . '</button> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . 'Cancel order &amp; restore cart' . '</a></div>';
        
         echo '<div id="fliqpay_form"><form id="order_review" method="post" action="' . WC()->api_request_url( 'WC_Fliqpay_Gateway' ) . '"></form>
         
         

        <!--Form Option-->
<form action="http://stagingapi.flq.rocks/i/paymentButton/">

    <input name="businessKey" value="' . $fliqpay_params['key'] . '" hidden />
  	<input name="name" value="" hidden />
  	<input name="description" value="" hidden />
  	<input name="currency" value="NGN" hidden />
    <input name="customerName" value="' . $fliqpay_params['name'] . '" hidden />
    <input name="customerEmail" value="' . $fliqpay_params['email'] . '" hidden />
    <input name="orderId" value="' . $fliqpay_params['orderId'] . '" hidden />
    <input name="amount" type="number" value="' . $fliqpay_params['amount'] . '" hidden />
    <input name="isAmountFixed" value="true" hidden />
  	<input name="useCurrenciesInWalletSettings" value="true" hidden />
    <input name="acceptedCurrencies" value="" hidden />
    <input name="settlementDestination" value="fliqpay_wallet" hidden />
    <button type="submit">Pay with Fliqpay</button>
    
</form> 


         
         
         
         
         
         <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . 'Cancel order &amp; restore cart' . '</a></div>';


      }
     

      public function complete_fliqpay_transaction() {
        $order = wc_get_order( $_GET['id'] );
        $order->payment_complete();
        $order->reduce_order_stock();
       
        update_option('webhook_debug', $_GET);
      }

   

     








  }

  }
  
  
  
  
  
  
  