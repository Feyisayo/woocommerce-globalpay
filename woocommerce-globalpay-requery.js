jQuery(document).ready(function($) {
  jQuery('a.tips[href=#]').each (function (i, e) {
    order_id = jQuery(e).parent().parent().parent().attr('id').split('-')[1];
    
    jQuery(e).bind (
      'click',
      {the_order_id: order_id},
      function (event) {
        event.data.action = 'requery';
        // event.data.the_order_id is already order_id
        jQuery.post(ajax_object.ajax_url, event.data, function(response) {
          parent_row = jQuery(e).parent().parent().parent();
          
          if ('' != response) {
            // remove all buttons apart from the requery button
            parent_row.find('td.order_actions p a[href!=#]').remove();
          }
          
          switch (response) {
            case '':
              alert ("An error occurred while looking up this order's payment information");
              break;
            
            case 'completed':
              parent_row.find('mark').attr('class', 'processing tips')
              parent_row.find('mark').html('processing')
              parent_row.find('td.order_actions p').prepend (
                ajax_object.view_html_template.replace ( 
                  'ORDER_ID', event.data.the_order_id
                )
              )
              parent_row.find('td.order_actions p').prepend (
                ajax_object.complete_html_template.replace ( 
                  'ORDER_ID', event.data.the_order_id
                )
              )              
              break;
            
            case 'on-hold':
              parent_row.find('mark').attr('class', 'on-hold tips')
              parent_row.find('mark').html('on-hold')
              parent_row.find('td.order_actions p').prepend (
                ajax_object.view_html_template.replace ( 
                  'ORDER_ID', event.data.the_order_id
                )
              )
              parent_row.find('td.order_actions p').prepend (
                ajax_object.complete_html_template.replace ( 
                  'ORDER_ID', event.data.the_order_id
                )
              )
              parent_row.find('td.order_actions p').prepend (
                ajax_object.processing_html_template.replace ( 
                  'ORDER_ID', event.data.the_order_id
                )
              )
              break;
            
            case 'failed':
              parent_row.find('mark').attr('class', 'failed tips')
              parent_row.find('mark').html('failed')
              parent_row.find('td.order_actions p').prepend (
                ajax_object.view_html_template.replace (
                  'ORDER_ID', event.data.the_order_id
                )
              )
              break;
            
          }
        });
      }
    );
    
  })
});
