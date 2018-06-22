(function ($) {

  'use strict';

  Drupal.behaviors.drupalContentSyncForm = {
    drawPreviewSelect: function(context, initially) {
      var table = $('#edit-sync-entities tbody', context);

      $('tr', table).each(function () {
        var tr = $(this);
        var has_preview_mode = +$('[name*="has_preview_mode"]', tr).val();
        var sync_import = $('[name*="sync_import"]', tr).val();
        var cloned_import = $('[name*="cloned_import"]', tr).val();

        if (undefined === has_preview_mode) {
          return;
        }

        var behavior_default_excluded = false;
        var behavior_default_table = false;
        var behavior_default_preview_mode = false;
        var behavior_enabled_excluded = false;
        var behavior_enabled_table = false;
        var behavior_enabled_preview_mode = false;

        var is_si_aut_ci_dis = 'automatically' === sync_import && 'disabled' === cloned_import;
        var is_ci_aut_si_dis = 'automatically' === cloned_import && 'disabled' === sync_import;
        var is_ci_aut_si_aut = 'automatically' === cloned_import && 'automatically' === sync_import;
        var is_ci_dis_si_dis = 'disabled' === cloned_import && 'disabled' === sync_import;

        if ('disabled' === cloned_import && 'disabled' === sync_import) {
          behavior_default_excluded = true;
        }

        if (is_si_aut_ci_dis || is_ci_aut_si_dis || is_ci_aut_si_aut || is_ci_dis_si_dis) {
          behavior_enabled_excluded = true;
        }

        if ('disabled' !== sync_import || 'disabled' !== cloned_import) {
          behavior_enabled_table = true;
        }

        if (!has_preview_mode && ('disabled' !== sync_import || 'disabled' !== cloned_import)) {
          behavior_default_table = true;
        }

        if (has_preview_mode && ('disabled' !== sync_import || 'disabled' !== cloned_import)) {
          behavior_default_preview_mode = true;
          behavior_enabled_preview_mode = true;
        }

        var preview_select = $('select[name*="preview"]', tr);
        var preview_excluded = preview_select.find('option[value="excluded"]');
        var preview_table = preview_select.find('option[value="table"]');
        var preview_preview_mode = preview_select.find('option[value="preview_mode"]');

        if (!behavior_enabled_excluded) {
          preview_excluded.remove();
        }
        else if (!preview_excluded.length) {
          preview_select.prepend('<option value="excluded">Excluded</option>');
          preview_excluded = preview_select.find('option[value="excluded"]');
        }

        if (!behavior_enabled_table) {
          preview_table.remove();
        }
        else if (!preview_table.length) {
          preview_select.append('<option value="table">Table</option>');
          preview_table = preview_select.find('option[value="table"]');
        }

        if (!behavior_enabled_preview_mode) {
          preview_preview_mode.remove();
        }
        else if (!preview_preview_mode.length) {
          preview_select.append('<option value="preview_mode">Preview mode</option>');
          preview_preview_mode = preview_select.find('option[value="preview_mode"]');
        }

        if (!initially) {
          if (behavior_default_excluded) {
            preview_excluded.attr('selected', true);
          }
          else if (behavior_default_table) {
            preview_table.attr('selected', true);
          }
          else if (behavior_default_preview_mode) {
            preview_preview_mode.attr('selected', true);
          }
        }

        var is_show_hint = !has_preview_mode && 'table' === preview_select.val();

        if (is_show_hint && !$('.preview-hint', tr).length) {
          var hint = 'You can enable "drupal_content_sync_preview" display mode to this bundle so that enabled "Preview mode"';

          preview_select.after('<small class="preview-hint" style="width:200px; display: block;">' + hint + '</small>');
        }
        else if(!is_show_hint && $('.preview-hint', tr).length) {
          $('.preview-hint', tr).remove();
        }
      });
    },
    hideRowIfInactive: function(context) {
      $('[data-drupal-selector="edit-sync-entities"] tbody > tr', context).each(function (i,el) {
        var $tr = $(el);
        var $handler = $('[name*="handler"]', $tr);
        var handler_val = $handler.val();

        if (undefined === handler_val) {
          return;
        }

        if ('ignore' === handler_val) {
          $($handler).closest('td')
            .nextAll('td')
            .hide();
        } else {
          $($handler).closest('td')
            .nextAll('td')
            .show();
        }
      });
    },
    attach: function (context, settings) {
      Drupal.behaviors.drupalContentSyncForm.drawPreviewSelect(context, true);
      Drupal.behaviors.drupalContentSyncForm.hideRowIfInactive(context);

      $(document)
        .once('sync_import_change')
        .on('change', 'select[name*="sync_import"]', function() {
          Drupal.behaviors.drupalContentSyncForm.drawPreviewSelect(context, false);
      });

      $(document)
        .once('cloned_import_change')
        .on('change', 'select[name*="cloned_import"]', function() {
          Drupal.behaviors.drupalContentSyncForm.drawPreviewSelect(context, false);
        });

      $(document)
        .once('preview_change')
        .on('change', 'select[name*="preview"]', function() {
          Drupal.behaviors.drupalContentSyncForm.drawPreviewSelect(context, false);
        });
    }
  }

})(jQuery, drupalSettings);
