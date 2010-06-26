// $Id$

/**
 * Hides irrelevant form elements.
 * 
 * Copied from searchlight.admin.js.
 */
(function ($) {
  Drupal.behaviors.search_api = {
	attach: function(context) {
      $('.search_api-service-select:not(.search_api-processed)',
          context).each(function() {
        $(this).change(function() {
          var value = $(this).val();
          $('.search_api-service-settings').hide();
          $('.search_api-service-' + value).show();
        });
        $(this).change();
      }).addClass('search_api-processed');
    }
  };
})(jQuery);
