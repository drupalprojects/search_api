/**
 * @file
 * Attaches administration-specific behavior to the Search API formatters form.
 */

(function ($) {

  "use strict";

  Drupal.behaviors.searchApiIndexFormatter = {
    attach: function (context, settings) {
      $('.search-api-status-wrapper input.form-checkbox', context).once('search-api-status', function () {
        var $checkbox = $(this);
        // Retrieve the tabledrag row belonging to this processor.
        var $row = $('#' + $checkbox.attr('id').replace(/-status$/, '-weight'), context).closest('tr');
        // Retrieve the vertical tab belonging to this processor.
        var $tab = $('#' + $checkbox.attr('id').replace(/-status$/, '-settings'), context).data('verticalTab');

        // Bind click handler to this checkbox to conditionally show and hide the
        // filter's tableDrag row and vertical tab pane.
        $checkbox.bind('click.searchApiUpdate', function () {
          if ($checkbox.is(':checked')) {
            $('#edit-processors-order').show();
            $('.tabledrag-toggle-weight-wrapper').show();
            $row.show();
            if ($tab) {
              $tab.tabShow().updateSummary();
            }
          }
          else {
            var $enabled_processors = $('.search-api-status-wrapper input.form-checkbox:checked').length;

            if (!$enabled_processors) {
              $('#edit-processors-order').hide();
              $('.tabledrag-toggle-weight-wrapper').hide();
            }

            $row.hide();
            if ($tab) {
              $tab.tabHide().updateSummary();
            }
          }
          // Restripe table after toggling visibility of table row.
          Drupal.tableDrag['edit-processors-order'].restripeTable();
        });

        // Attach summary for configurable items (only for screen-readers).
        if ($tab) {
          $tab.details.drupalSetSummary(function () {
            return $checkbox.is(':checked') ? Drupal.t('Enabled') : Drupal.t('Disabled');
          });
        }

        // Trigger our bound click handler to update elements to initial state.
        $checkbox.triggerHandler('click.searchApiUpdate');
      });
    }
  };

})(jQuery);
