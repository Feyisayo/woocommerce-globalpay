<?php
/**
 * Plugin Name: Woocommerce GlobalPay
 * Plugin URI:  http://twitter.com/Feyisayo
 * Description: Allows payments to be made to a Woocommerce shop via GlobalPay
 * Author:      Feyisayo Akinboboye
 * Author URI:  http://twitter.com/Feyisayo
 * Version:     3.3
 */

function woocommerce_globalpay_init () {
  // The GlobalPay plugin should only be loaded if Woocommerce is installed.
  if (!class_exists('WC_Payment_Gateway')) {
    return;
  }

  class WC_GlobalPay extends WC_Payment_Gateway {
    /**
     * Array that will hold payment information.
     *
     * The response from GlobalPay's transaction lookup webservice is in XML.
     * So SimpleXML is used to manipulate it but eventually it will be made
     * into a single-dimension array so that it can stored as part of the
     * order meta information. This variable needs to declared at class level
     * at the function that converts the simpleXML object into an array is
     * recursive.
     *
     * @var      array
     * @access   private
     */
    private $paymentInfo = array();

    /**
     * Contstructor.
     */
    public function __construct() {
      $this->id = 'globalpay';
      $this->icon = apply_filters('woocommerce_globalpay_icon', plugins_url('/images/globalpay_logo.png', __FILE__));
      $this->has_fields = FALSE;
      $this->liveurl = 'https://www.globalpay.com.ng/Paymentgatewaycapture.aspx';
      $this->testurl = 'https://demo.globalpay.com.ng/globalpay_demo/paymentgatewaycapture.aspx';
      $this->method_title = __('GlobalPay', 'woocommerce');

      // Load the form fields.
      $this->init_form_fields();

      // Load the settings.
      $this->init_settings();

      // Define user set variables.
      $this->title = $this->settings['title'];
      $this->description = $this->settings['description'];
      $this->testmode = $this->settings['testmode'];
      $this->debug = $this->settings['debug'];
      $this->thanks_message = $this->settings['thanks_message'];
      $this->error_message = $this->settings['error_message'];
      $this->feedback_message = '';
      $this->merchant_id = $this->settings['merchant_id'];
      $this->webservice_user = $this->settings['webservice_user'];
      $this->webservice_password = $this->settings['webservice_password'];

      // Actions.
      add_action('woocommerce_receipt_globalpay', array(&$this, 'receipt_page'));
      add_action('woocommerce_thankyou_' . $this->id, array(&$this, 'thankyou_page'));
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

      // Logs.
      if ('yes' == $this->debug) {
        $this->log = new WC_Logger();
      }

      if (!$this->is_valid_for_use()) {
        $this->enabled = FALSE;
      }
    }

    function is_valid_for_use() {
      if (!in_array(get_option('woocommerce_currency'), array('NGN'))) {
        return FALSE;
      } else {
        return TRUE;
      }
    }

    function admin_options() {
      echo '<h3>' . __('GlobalPay', 'woocommerce') . '</h3>';
      echo '<p>' . __('GlobalPay works by sending the user to GlobalPay to enter their payment information.', 'woocommerce') . '</p>';
      echo '<table class="form-table">';

      if ($this->is_valid_for_use()) {
        $this->generate_settings_html();
      } else {
        echo '<div class="inline error"><p><strong>' . __('Gateway Disabled', 'woocommerce') . '</strong>: ' . __('GlobalPay does not support your store currency.', 'woocommerce') . '</p></div>';
      }

      echo '</table>';
    }

    function init_form_fields() {
      $this->form_fields = array(
        'enabled' => array(
          'title' => __('Enable/Disable', 'woocommerce'),
          'type' => 'checkbox',
          'label' => __('Enable GlobalPay', 'woocommerce'),
          'default' => 'yes'
        ),
        'title' => array(
          'title' => __('Title', 'woocommerce'),
          'type' => 'text',
          'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
          'default' => __('GlobalPay', 'woocommerce')
        ),
        'description' => array(
          'title' => __('Description', 'woocommerce'),
          'type' => 'textarea',
          'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
          'default' => __('Pay via GlobalPay', 'woocommerce')
        ),
        'merchant_id' => array(
          'title' => __('GlobalPay Merchant ID', 'woocommerce'),
          'type' => 'text',
          'description' => __('Merchant ID given to you by GlobalPay', 'woocommerce'),
          'default' => __('', 'woocommerce')
        ),
        'thanks_message' => array(
          'title' => __('Thanks message', 'woocommerce'),
          'type' => 'textarea',
          'description' => __('The message to show on a successful payment', 'woocommerce'),
          'default' => __('Thank you. Your payment was successful. Your order is status is now <strong>processing</strong>', 'woocommerce')
        ),
        'error_message' => array(
          'title' => __('Failure message', 'woocommerce'),
          'type' => 'textarea',
          'description' => __('The message to show when a payment has failed', 'woocommerce'),
          'default' => __('Sorry. Your payment was not successful', 'woocommerce')
        ),
        'testmode' => array(
          'title' => __('GlobalPay Test Mode', 'woocommerce'),
          'type' => 'checkbox',
          'label' => __('Enable GlobalPay Test Mode', 'woocommerce'),
          'default' => 'yes'
        ),
        'webservice_user' => array(
          'title' => __('GlobalPay webservice user ID', 'woocommerce'),
          'type' => 'text',
          'description' => __('The user ID for the GlobalPay transaction lookup service', 'woocommerce'),
          'label' => __('GlobalPay webservice user ID', 'woocommerce')
        ),
        'webservice_password' => array(
          'title' => __('GlobalPay webservice password', 'woocommerce'),
          'type' => 'text',
          'description' => __('The password for the GlobalPay transaction lookup service', 'woocommerce'),
          'label' => __('GlobalPay webservice user ID', 'woocommerce')
        ),
        'debug' => array(
          'title' => __('Debug', 'woocommerce'),
          'type' => 'checkbox',
          'label' => __('Enable logging (<code>wc-logs/globalpay.txt</code>)', 'woocommerce'),
          'default' => 'no'
        )
      );
    }

    function get_globalpay_args ($order) {
      $txn_ref = get_current_user_id() . '-' . $order->id . '-' . time();
      update_post_meta($order->id, 'merch_txnref', $txn_ref);
      update_post_meta($order->id, '_transaction_id', $txn_ref);

      $order_total = $order->get_total();

      if ($this->debug == 'yes') {
        $this->log->add($this->id, 'Generating payment form for order #' . $order->id . '.');
      }

      $name = '';
      if ($order->billing_first_name || $order->billing_last_name) {
        $name = trim($order->billing_first_name . ' ' . $order->billing_last_name);
      } else {
        $name = __('Guest', 'woocommerce');
      }

      $globalpay_args = array(
        'merchantid' => $this->merchant_id,
        'amount' => $order_total,
        'currency' => 'NGN',
        'merch_txnref' => $txn_ref,
        'name' => $name,
        'names' => $name,
        'email_address' => $order->billing_email,
        'phone_number' => $order->billing_phone
      );

      return apply_filters('woocommerce_globalpay_args', $globalpay_args);
    }

    function generate_globalpay_form ($order_id) {
      // It appears some themes call the checkout page twice thus causing the
      // transaction reference to be generated twice. Below we shall prevent
      // it by ensuring the referrer has no URL arguments.
      $query_args = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
      if (0 != substr_count($query_args, 'key') || 0 != substr_count($query_args, 'order')) {
        if ('yes' == $this->debug) {
          $this->log->add($this->id, __FUNCTION__ . ' aborted because of possible duplicate call');
        }
        return;
      }

      $order = new WC_Order($order_id);
      $globalpay_args = $this->get_globalpay_args($order);
      $globalpay_args_array = array();

      $globalpay_adr = $this->liveurl;
      if ($this->testmode == 'yes') {
        $globalpay_adr = $this->testurl;
      }

      foreach ($globalpay_args as $key => $value) {
        $globalpay_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
      }

      wc_enqueue_js('
        jQuery("body").block({
          message: "' . __('Thank you for your order. We are now redirecting you to GlobalPay to make payment.', 'woocommerce') . '",
          overlayCSS: {
            background: "#fff",
            opacity: 0.6
          },
          css: {
            padding:        20,
            textAlign:      "center",
            color:          "#555",
            border:         "3px solid #aaa",
            backgroundColor:"#fff",
            cursor:         "wait",
            lineHeight:  "32px"
          }
        });
        jQuery("#submit_globalpay_payment_form").click();
      ');

      $form = '<form action="' . esc_url($globalpay_adr) . '" method="post" id="globalpay_payment_form">'
        . implode('', $globalpay_args_array)
        . '<input type="submit" class="button-alt" id="submit_globalpay_payment_form" value="' . __('Pay via GlobalPay', 'woocommerce') . '" />'
        . '<a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'woocommerce') . '</a>'
        . '</form>';

      if ('yes' == $this->debug) {
        $this->log->add($this->id,
          'User redirected to GlobalPay with the following:' . "\r\n"
          . 'GlobalPay URL: ' . $globalpay_adr . "\r\n"
          . print_r($globalpay_args, TRUE));
      }

      // Place the order ID and redirect URL in the session. To be used when the
      // user is redirected back from GlobalPay.
      $_SESSION['globalpay_order_id'] = $order_id;
      $_SESSION['globalpay_redirect_url'] = $this->get_return_url($order);

      return $form;
    }

    function check_transaction_on_user_return() {
      @ob_clean();
      global $woocommerce;

      if ('yes' == $this->debug) {
        $this->log->add($this->id,
          'Transaction details received on user return from GlobalPay:' . "\r\n"
          . print_r($_GET, TRUE));
      }

      if (!isset($_SESSION['globalpay_order_id'])) {
        wp_redirect(home_url());
        exit;
      }
      $order_id = $_SESSION['globalpay_order_id'];
      unset($_SESSION['globalpay_order_id']);

      $order = new WC_Order((int) $order_id);
      // $merch_txnref = get_post_meta($order->id, 'merch_txnref', true);
      if (!$order) {
        // @todo: notify user and admin of this ie order not found
      }

      $_GET['key'] = $order->order_key;
      $_GET['order'] = $order->id;

      $this->get_transaction_status($order);
      foreach ($this->paymentInfo as $k => $v) {
        if ('status' != $k) {
          update_post_meta((int) $order_id, $k, $v);
        }
      }

      if ('completed' == $this->paymentInfo['status']) {
        // Payment completed.
        $order->add_order_note(__('Payment completed', 'woocommerce'));
        $order->payment_complete();
        $woocommerce->cart->empty_cart();

        if ($this->debug == 'yes') {
          $this->log->add($this->id, 'Payment complete.');
        }

        update_post_meta((int) $order_id, 'Payment Method', $this->method_title);

        $this->send_mail_successful_payment(
          $order->id,
          $order->get_total(),
          $this->paymentInfo['txnref']
        );
        wc_add_notice('Payment successful', 'success');
      } elseif ('failed' == $this->paymentInfo['status']) {
        $error_code = $this->paymentInfo['payment_status_description'];
        $order->add_order_note(__('Payment Failed - ' . $error_code, 'woocommerce'));
        $order->update_status('failed');
        wc_add_notice('Transaction Failed: ' . $error_code, 'error');
      } elseif ('on-hold' == $this->paymentInfo['status']) {
        $order->update_status('on-hold', sprintf(__('Payment pending: %s', 'woocommerce'), 'Amount discrepancy'));
        $error_code = 'Amount discrepancy';
        // Notify admin of discrepancy in amount.
        $this->send_mail_discrepancy_in_payment(
          $order->id,
          $order->get_total(),
          $this->paymentInfo['amount']
        );
        wc_add_notice('Order on hold: ' . $error_code, 'error');
      } else if (FALSE == $this->paymentInfo['status']) {
        $order->update_status('on-hold', sprintf(__('Payment pending: %s', 'woocommerce'), $error));
        $order->add_order_note(__('Payment on-hold - ' . $error, 'woocommerce'));
        $order->update_status('on-hold');
        $this->send_mail_payment_info_pending(
          $order->id,
          $order->billing_first_name . ' ' . $order->billing_last_name
        );
        wc_add_notice('There was an error while looking up the details of your payment information. A sales person has been notified', 'error');
      }
    }

    function thankyou_page($order_id) {
      $order = new WC_Order($order_id);
      // All elements of $order_payment_info are arrays. So when getting the
      // value get the value at the end of the array which should represent
      // the most current value.
      $order_payment_info = get_post_meta($order_id);
      if ('completed' == $order->status || 'processing' == $order->status) {
        $this->feedback_message = $this->thanks_message
          . '<br/>Below are the details of your payment transaction:'
          . '<br/><strong>Transaction reference:</strong> ' . end($order_payment_info['merch_txnref'])
          . '<br/><strong>Customer name:</strong> ' . end($order_payment_info['name'])
          . '<br/><strong>Amount paid:</strong> '
            . number_format(end($order_payment_info['amount']), 2)
          . '<br/><strong>Currency:</strong> ' . end($order_payment_info['currency'])
          . '<br/><strong>Payment Channel:</strong> ' . end($order_payment_info['channel'])
          . '<br/><strong>GlobalPay reference:</strong> ' . end($order_payment_info['txnref'])
          . '<br/><strong>Transaction status description:</strong> ' . end($order_payment_info['payment_status_description']);
      } else if ('failed' == $order->status) {
        $this->feedback_message = $this->error_message
          . '<br/>Below are the details of your payment transaction:'
          . '<br/><strong>Transaction reference:</strong> ' . end($order_payment_info['merch_txnref'])
          . '<br/><strong>Customer name:</strong> ' . end($order_payment_info['name'])
          . '<br/><strong>Amount paid:</strong> '
            . number_format(end($order_payment_info['amount']), 2)
          . '<br/><strong>Currency:</strong> ' . end($order_payment_info['currency'])
          . '<br/><strong>Payment Channel:</strong> ' . end($order_payment_info['channel'])
          . '<br/><strong>GlobalPay reference:</strong> ' . end($order_payment_info['txnref'])
          . '<br/><strong>Transaction status description:</strong> ' . end($order_payment_info['payment_status_description']);
      } else if ('on-hold' == $order->status && TRUE == $order_payment_info['amount_discrepancy']) {
        $this->feedback_message = 'Your payment was successful. '
          . 'However there was a discrepancy in the amount paid.'
          . '<br/>The order amount is <strong>NGN' . number_format($order->get_total(), 2) . '</strong>'
          . '<br/>while the actual amount paid is <strong>NGN' . number_format(end($order_payment_info['amount']), 2) . '</strong>'
          . '<br/> A sales person has already been notified of this.<br/>'
          . '<br/>Below are the details of your payment transaction:'
          . '<br/><strong>Transaction reference:</strong> ' . end($order_payment_info['merch_txnref'])
          . '<br/><strong>Customer name:</strong> ' . end($order_payment_info['name'])
          . '<br/><strong>Amount paid:</strong> '
            . number_format(end($order_payment_info['amount']), 2)
          . '<br/><strong>Currency:</strong> ' . end($order_payment_info['currency'])
          . '<br/><strong>Payment Channel:</strong> ' . end($order_payment_info['channel'])
          . '<br/><strong>GlobalPay reference:</strong> ' . end($order_payment_info['txnref'])
          . '<br/><strong>Transaction status description:</strong> ' . end($order_payment_info['payment_status_description']);
      }

      echo wpautop($this->feedback_message);
    }

    function process_payment($order_id) {
      $order = new WC_Order($order_id);
      return array(
        'result' => 'success',
        'redirect' => $order->get_checkout_payment_url(true)
      );
    }

    function receipt_page($order) {
      echo '<p>'.__('Thank you for your order, please click the button below to pay with GlobalPay.', 'woocommerce').'</p>';

      echo $this->generate_globalpay_form($order);
    }

    /**
     * Converts an object into an array.
     *
     * A recursive function used to convert an object (in this case a SimpleXML
     * object) into an array.
     *
     * @see http://brian.moonspot.net/2008/06/03/stupid-php-tricks-normalizing-simplexml-data/
     *
     * @param object $obj
     *   Object to be converted
     *
     * @return array
     */
    private function make_array($obj) {
      $arr = (array) $obj;

      if (!empty($arr)) {
        foreach ($arr as $key => $value) {
          if (!is_scalar($value)) {
            $arr[$key] = $this->make_array($value);
          }
        }
      }
      return $arr;
    }

    /**
     * Function to be used as callback for array_walk_recursive.
     */
    function fill_out_payment_info($value, $key) {
      $this->paymentInfo[$key] = $value;
    }

   /**
    * Looks up transaction information from GlobalPay's lookup webservice.
    *
    * If the lookup is successful it fills out the class-level array
    * $paymentInfo with the details. Otherwise it set $paymentInfo to FALSE.
    * When filled out $paymentInfo should like so:
    *   'status' => string 'completed' (length=10)
    *   'txnref' => string '9113339143629597' (length=16)
    *   'channel' => string 'mastercard' (length=10)
    *   'amount' => string '2000' (length=4)
    *   'payment_date' => string '12/5/2013 2:36:29 PM' (length=20)
    *   'payment_status' => string 'successful' (length=10)
    *   'names' => string 'John Doe' (length=19)
    *   'acct_desc' => string 'False' (length=5)
    *   'acct_desc_order' => string '0' (length=1)
    *   'hidden' => string 'False' (length=5)
    *   'xpath_field' => string '0' (length=1)
    *   'currency' => string 'NGN' (length=3)
    *   'email_address' => string 'john.doe@example.com' (length=29)
    *   'phone_number' => string '1234567890' (length=10)
    *   'merch_txnref' => string 'ref-1234567897' (length=14)
    *   'payment_status_description' => string 'Transaction Successful - Approved' (length=33)
    *
    * @param stdClass $order
    *
    * @return void
    */
    function get_transaction_status ($order) {
      require_once 'lib/nusoap.php';
      // Clear previous payment info.
      $this->paymentInfo = array();

      if ('yes' == $this->testmode) {
        $endpoint = 'https://demo.globalpay.com.ng/GlobalpayWebService_demo/service.asmx?wsdl';
        $namespace = 'https://www.eazypaynigeria.com/globalpay_demo/';
        $soap_action = 'https://www.eazypaynigeria.com/globalpay_demo/getTransactions';
      } else {
        $endpoint = 'https://www.globalpay.com.ng/globalpaywebservice/service.asmx?wsdl';
        $namespace = 'https://www.eazypaynigeria.com/globalpay/';
        $soap_action = 'https://www.eazypaynigeria.com/globalpay/getTransactions';
      }
      $soap_client = new nusoap_client($endpoint, TRUE);
      if ($soap_client->getError()) {
        $this->paymentInfo = FALSE;
        return;
      }

      $params = array(
        'merch_txnref' => get_post_meta($order->id, 'merch_txnref', TRUE),
        'channel' => '',
        'merchantID' => $this->merchant_id,
        'start_date' => '',
        'end_date' => '',
        'uid' => $this->webservice_user,
        'pwd' => $this->webservice_password,
        'payment_status' => ''
      );

      $this->log->add($this->id, 'Connecting to GlobalPay at ' . $endpoint);

      // Connect.
      $result = $soap_client->call(
        'getTransactions',
        array('parameters' => $params),
        $namespace,
        $soap_action,
        FALSE,
        TRUE
      );

      if ($soap_client->fault) {
        $this->log->add($this->id, 'Error looking transaction\n' . print_r($result, TRUE));
        $this->paymentInfo = FALSE;
        return;
      }

      $err = $soap_client->getError();
      if ($err) {
        $this->log->add($this->id, 'Error looking transaction\n' . print_r($err, TRUE));
        $this->paymentInfo = FALSE;
        return;
      }

      // Add all $xml's properties to $this->paymentInfo
      $xml = simplexml_load_string($result['getTransactionsResult']);
      $xml_arr = $this->make_array($xml);
      array_walk_recursive($xml_arr, array($this, 'fill_out_payment_info'));

      $this->paymentInfo['amount_discrepancy'] = FALSE;
      if ('successful' == $xml->record->payment_status) {
        // If there is an amount discrepancy flag it.
        if ($this->paymentInfo['amount'] != $order->get_total()) {
          $this->paymentInfo['amount_discrepancy'] = TRUE;
          $this->paymentInfo['status'] = 'on-hold';
        } else {
          $this->paymentInfo['status'] = 'completed';
        }
      } else {
        $this->paymentInfo['status'] = 'failed';
      }

      // Work-around for name/names API field issue from Globalpay.
      if (isset($this->paymentInfo['name'])) {
        $this->paymentInfo['names'] = $this->paymentInfo['name'];
      } else {
        $this->paymentInfo['name'] = $this->paymentInfo['names'];
      }

      if ('yes' == $this->debug) {
        $this->log->add($this->id, 'Response dump from GlobalPay ' . print_r($result, TRUE));
      }
    }

    /**
     * Used by Orders interface to update payment information via AJAX.
     *
     * @param int $order_id
     *
     * @return mixed
     *   'processing', 'failed' or 'on-hold' if the update was successful
     *   FALSE otherwise
     */
    function ajax_update_paymentInfo ($order_id) {
      $order = new WC_Order((int) $order_id);
      $merch_txnref = get_post_meta($order->id, 'merch_txnref', TRUE);
      if (!$merch_txnref) {
        return FALSE;
      }

      $this->get_transaction_status($order);

      if (FALSE == $this->paymentInfo) {
        return FALSE;
      }

      // Update the order information with the response from ISW.
      foreach ($this->paymentInfo as $k => $v) {
        if ('status' != $k){
          update_post_meta((int)$order->id, $k, $v);
        }
      }

      if ('completed' == $this->paymentInfo['status']) {
        // Payment completed.
        $order->add_order_note( __('Payment completed', 'woocommerce') );
        $order->payment_complete();

        if ('yes' == $this->debug) {
          $this->log->add($this->id, 'Payment complete.');
        }

        update_post_meta((int) $order->id, 'Payment Method', 'GlobalPay');

        return 'completed';
      } elseif ('on-hold' == $this->paymentInfo['status']) {
        $order->update_status(
          'on-hold',
          sprintf(
            __('Payment pending: %s', 'woocommerce'),
            $this->paymentInfo['ResponseDescription']
          )
        );

        return 'on-hold';
      } elseif ('failed' == $this->paymentInfo['status']) {
        $error_code = $this->paymentInfo['ResponseDescription'];

        $order->add_order_note(__('Payment Failed - ' . $error_code, 'woocommerce'));
        $order->update_status('failed');

        return 'failed';
      }
    }

    function send_mail_discrepancy_in_payment ($order_id, $expected_amount, $amount_paid) {
      $order = new WC_Order($order_id);
      $currency_symbol = $order->get_order_currency();
      $orders_page = admin_url() . 'edit.php?post_type=shop_order';
      $site_name = get_bloginfo('name');
      $to = get_bloginfo('admin_email');
      $subject = "Discrepancy in payment for Order #$order_id";
      $expected_amount = number_format($expected_amount, 2);
      $amount_paid = number_format($amount_paid, 2);
      $name_of_user = $order->billing_first_name . ' ' . $order->billing_last_name;
      $message = <<<HTML
      <html>
    <head>
      <title>Pending payment transaction</title>
    </head>
    <body>
      <p>
        There was a discrepancy in payment transaction for order <a href = "$orders_page"><strong>$order_id</strong></a> by customer <strong>$name_of_user</strong> via GlobalPay</p>
      <p>
        The expected amount is <strong>$currency_symbol$expected_amount</strong> while the actual paid amount is <strong>$currency_symbol$amount_paid</strong></p>
      <p>
        This message was auto-generated by the GlobalPay payment plugin for Woocommerce at $site_name</p>
    </body>
    </html>
HTML;

      $headers[] = 'From: ' . $site_name . " <$to>";
      $headers[] = 'Content-type: text/html';

      wp_mail($to, $subject, $message, $headers);
    }

    function send_mail_payment_info_pending ($order_id, $customer) {
      $orders_page = admin_url() . 'edit.php?post_type=shop_order';
      $site_name = get_bloginfo('name');
      $to = get_bloginfo('admin_email');
      $subject = "Order #$order_id payment information pending";
      $message = <<<HTML
      <html>
    <head>
      <title>Pending payment transaction</title>
    </head>
    <body>
      <h1>
        Pending payment transaction</h1>
      <p>
        There was a problem looking up the details of the payment transaction for order <a href = "$orders_page"><strong>$order_id</strong></a> by customer <strong>$customer</strong></p>
      <p>
        You can resolve this by going to the orders page of Woocommerce and clicking the button labeled &quot;R&quot; beside the order.</p>
      <p>
        This message was auto-generated by the GlobalPay payment plugin for Woocommerce at $site_name</p>
    </body>
    </html>
HTML;

      $headers[] = 'From: ' . $site_name . " <$to>";
      $headers[] = 'Content-type: text/html';

      wp_mail($to, $subject, $message, $headers);
    }

    function send_mail_successful_payment ($order_id, $amount, $globalpay_ref) {
      $order = new WC_Order($order_id);
      if (!$order->billing_email) {
        return;
      }
      $currency_symbol = $order->get_order_currency();
      $site_name = get_bloginfo('name');
      $name_of_user = $order->billing_first_name . ' ' . $order->billing_last_name;
      $amount = number_format($amount, 2);

      $to = $order->billing_email;
      $subject = "Successful payment for order #$order_id";
      $message = <<<HTML
    <html>
    <head>
      <title>Payment successful</title>
    </head>
    <body>
      <h1>
        Payment successful</h1>
      <p>
        Dear $name_of_user,</p>
      <p>
        This is to inform you that your payment of <strong>$currency_symbol$amount</strong> for order <strong>$order_id</strong> via GlobalPay was successful.</p>
      <p>
        Your GlobalPay payment reference is <strong>$globalpay_ref</strong></p>
      <p>
        Thanks for purchasing at $site_name</p>
    </body>
    </html>

HTML;

      $headers[] = 'From: ' . $site_name . ' <' . get_bloginfo('admin_email') . '>';
      $headers[] = 'Content-type: text/html';

      wp_mail($to, $subject, $message, $headers);
    }

  }

  /**
   * Add the gateway to WooCommerce.
   */
  function add_globalpay_gateway($methods) {
    $methods[] = 'WC_GlobalPay';
    return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'add_globalpay_gateway');
}

// This is called when the user is redirected from GlobalPay. It redirects the
// user the "Order received" page.
add_action('template_redirect', 'globalpay_check_response');
function globalpay_check_response() {
  global $wp_query;
  if (isset($wp_query->query['globalpay-transaction-response'])
    && isset($_SESSION['globalpay_redirect_url'])) {

    $r = $_SESSION['globalpay_redirect_url'];
    unset($_SESSION['globalpay_redirect_url']);
    wp_redirect($r);
    exit;
  }
}

// This is called when the user is being redirected to the "Order received"
// page.
add_action('init', 'globalpay_check_transaction_on_user_return');
function globalpay_check_transaction_on_user_return () {
  // Ensure that $_SESSION['globalpay_redirect_url'] is NOT set as this shows
  // that the function globalpay_check_response() has been previously called.
  if (isset($_SESSION['globalpay_order_id'])
    && !isset($_SESSION['globalpay_redirect_url'])) {

    $wc_globalpay = new WC_GlobalPay();
    $wc_globalpay->check_transaction_on_user_return();
  }
}

add_filter('woocommerce_admin_order_actions', 'add_globalpay_requery_button', 10, 2);
function add_globalpay_requery_button ($actions, $the_order) {
  // Do this only for GlobalPay-based payments that are not successful.
  $wc_globalpay = new WC_GlobalPay();
  if ($the_order->payment_method != $wc_globalpay->id
      || $the_order->status == 'processing'
      || $the_order->status == 'completed') {
    return $actions;
  }

  $actions['requery'] = array(
    'url'     => '#',
    'name'     => __('Update order ' . $the_order->id, 'woocommerce-globalpay'),
    'action' => 'icon-wooglobalpay-webfont'
  );

  return $actions;
}

add_action('admin_enqueue_scripts', 'add_globalpay_requery_js');
function add_globalpay_requery_js ($hook) {
  if ('edit.php' != $hook) {
    return;
  }

  wp_enqueue_script(
    'globalpay-ajax-script',
    plugins_url('woocommerce-globalpay-requery.js', __FILE__),
    array('jquery')
  );

  $admin_url = admin_url();
  $processing_html_template = '<a class="button tips processing" href="' . $admin_url . '/admin-ajax.php?action=woocommerce-mark-order-processing&amp;order_id=ORDER_ID">Processing</a>';
  $complete_html_template = '<a class="button tips complete" href="' . $admin_url . '/admin-ajax.php?action=woocommerce-mark-order-complete&amp;order_id=ORDER_ID">Complete</a>';
  $view_html_template = '<a class="button tips view" href="' . $admin_url . '/post.php?post=ORDER_ID&amp;action=edit">View</a>';

  wp_localize_script(
    'globalpay-ajax-script', 'globalpay_ajax_object', array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'processing_html_template' => $processing_html_template,
      'complete_html_template' => $complete_html_template,
      'view_html_template' => $view_html_template
    )
  );
}

add_action('wp_ajax_globalpay_requery', 'globalpay_requery_callback');
function globalpay_requery_callback () {
  $wc_globalpay = new WC_GlobalPay();
  $status = $wc_globalpay->ajax_update_paymentInfo($_POST['the_order_id']);
  if (FALSE === $status) {
    $status = '';
  }
  echo $status;
  die();
}

add_action('admin_enqueue_scripts', 'globalpay_add_custom_css');
function globalpay_add_custom_css() {
  wp_register_style('globalpay-css', plugins_url('/css/styles.css', __FILE__));
  wp_enqueue_style('globalpay-css');
}

// Start a PHP session as WP does not use it.
add_action('init', 'globalpay_start_session', 1);
function globalpay_start_session(){
  if (!session_id()) {
    session_start();
  }
}

// End the PHP session when the user logs in or logs out.
add_action('wp_logout', 'globalpay_end_session');
add_action('wp_login', 'globalpay_end_session');
function globalpay_end_session(){
  if (session_id()) {
    session_destroy();
  }
}

// Clear all existing re-write rules when this plugin is activated.
register_activation_hook(__FILE__, 'globalpay_activate');
function globalpay_activate() {
  flush_rewrite_rules();
}

// Register our custom redirect URL.
add_action('init', 'globalpay_add_endpoint');
function globalpay_add_endpoint() {
  add_rewrite_endpoint('globalpay-transaction-response', EP_ALL);
}
add_filter('query_vars', 'globalpay_add_query_var');
function globalpay_add_query_var($vars) {
  $vars[] = 'globalpay-transaction-response';
  return $vars;
}

// Add Naira to the currency list.
add_filter('woocommerce_currencies', 'globalpay_add_ngn_currency');
function globalpay_add_ngn_currency($currencies) {
  $currencies['NGN'] = __('Nigerian Naira', 'woocommerce');
  return $currencies;
}

add_filter('woocommerce_currency_symbol', 'globalpay_add_ngn_currency_symbol', 10, 2);
function globalpay_add_ngn_currency_symbol($currency_symbol, $currency) {
  switch ($currency) {
    case 'NGN':
      $currency_symbol = '&#8358;';
      break;
  }

  return $currency_symbol;
}
add_filter('plugins_loaded', 'woocommerce_globalpay_init');
