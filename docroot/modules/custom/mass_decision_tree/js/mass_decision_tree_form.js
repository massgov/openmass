/**
 * @file
 * Extends Drupal object with mass custom js objects
 *
 * Custom js for the decision tree admin form, allows tabledrag to be collapsed
 * and expanded, adds attributes for orphans and yes/no responses.
 *
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.MassDecisionTreeForm = {
    attach: function (context, settings) {
      // tableDrag is required and we should be on a decision tree node.
      if (typeof Drupal.tableDrag === 'undefined' || typeof Drupal.tableDrag.decisionTree === 'undefined') {
        return;
      }
      if (typeof drupalSettings.detached === 'undefined') {
        drupalSettings.detached = [];
      }

      // Unbind / rebind click event to branch & conclusion titles.
      $('a.decision-tree-form-title', context).unbind('click').click(function (e) {
        e.preventDefault();
        var $self = $(this);
        var id = $self.attr('id');
        var $currentRow = $self.closest('tr');
        var depth = $currentRow.find('.js-indentation').length;
        // If there are detached children, put them back in DOM.
        if (id in drupalSettings.detached && drupalSettings.detached[id].length > 0) {
          drupalSettings.detached[id].reverse();
          drupalSettings.detached[id].forEach(function (t) {
            $currentRow.after(t);
            // Parent may have moved since these were detached, adjust depths.
            var childDepth = t.find('.js-indentation').length;
            if (childDepth !== (depth + 1)) {
              var indentDiff = childDepth - (depth + 1);
              var i;
              // We remove a tab per diff if it is positive.
              if (indentDiff > 0) {
                for (i = 0; i < indentDiff; i++) {
                  t.find('.js-indentation:first-of-type').remove();
                }
              }
              // If diff is negative, we add tab per diff.
              else {
                indentDiff = Math.abs(indentDiff);
                for (i = 0; i < indentDiff; i++) {
                  t.find('td:first-of-type').prepend(Drupal.theme('tableDragIndentation'));
                }
              }
            }
            // Label yes/no to catch newly displayed rows.
            massLabelYesNo($self.closest('tbody'));
          });
          drupalSettings.detached.splice(id, 1);
        }
        // Otherwise, let's recursively detach all descendants.
        else {
          if (typeof drupalSettings.detached[id] === 'undefined') {
            drupalSettings.detached[id] = [];
          }
          massHideChildren(id, drupalSettings.detached, $self.closest('tbody'));
        }
      });

      // Detaches children of a row and stores them in drupalSettings.
      function massHideChildren(id, detached, $tbody) {
        $tbody.find('tr[data-parent="' + id + '"]').each(function () {
          var $self = $(this);
          var thisId = $self.find('a.decision-tree-form-title').attr('id');
          detached[id].push($self.detach());
          if (typeof detached[thisId] === 'undefined') {
            detached[thisId] = [];
          }
          massHideChildren(thisId, detached, $tbody);
        });
      }

      function massLabelOrphans() {
        $('table#decisionTree tr.draggable', context).each(function () {
          var $self = $(this);
          // Remove any existing orphan labels before we begin.
          $self.removeClass('branch-orphan');
          var depth = $self.find('.js-indentation').length;
          var id = $self.find('a.decision-tree-form-title').attr('id');
          var type = $self.data('type');
          if (type === 'branch') {
            // Check if next tr is child, if not label as orphan.
            var nextDepth = $self.next('tr').find('.js-indentation').length;
            if ((nextDepth <= depth || !nextDepth) && typeof drupalSettings.detached[id] === 'undefined') {
              $self.addClass('branch-orphan');
            }
          }
        });
      }

      // Below we are hooking into Drupal core's tabledrag functionality.
      var tableDrag = Drupal.tableDrag.decisionTree;

      // Extending the stub function core provides for reacting to row drops.
      tableDrag.onDrop = function () {
        var dragObject = this;
        var $row = $(dragObject.rowObject.element);
        var depth = $row.find('.js-indentation').length;
        // Cycle through rows now previous and find the parent.
        $row.prevAll('tr').each(function () {
          var $self = $(this);
          var rowId = $self.find('a.decision-tree-form-title').attr('id');
          var rowDepth = $self.find('.js-indentation').length;
          // First previous row with depth one less than this row is its parent.
          if (rowDepth === (depth - 1)) {
            $row.attr('data-parent', rowId);
            return false;
          }
        });
        // Cycle through and label orphans.
        massLabelOrphans();
      };
      // Initial labeling on attach.
      massLabelOrphans();
    }
  };
})(jQuery, Drupal);
