jQuery(document).ready(function($) {
  jQuery('a.icon-wooglobalpay-webfont').each (function (i, e) {
    order_id = $(e).text();

    jQuery(e).bind ('click', {the_order_id: order_id}, function (event) {
        jQuery.blockUI({ message: '<h3>Updating from GlobalPay. Please wait.....</h3>'});
        event.data.action = 'requery';
        // event.data.the_order_id is already order_id
        jQuery.post(globalpay_ajax_object.ajax_url, event.data, function(response) {
          r_message = '';
          jQuery.unblockUI();
          if ('' == response) {
            r_message = '<h3>An unexpected error has occured. Please try again later</h3>';
          } else {
            r_message = '<h3>The order status is ' + response + '</h3>';
          }
          jQuery.blockUI({ message: r_message});
          setTimeout(jQuery.unblockUI, 2000);
          // reload the window if no lookup error occurred
          if ('' != response) {
            window.location.reload();
          }
        });
      }
    );
    
  })
});
