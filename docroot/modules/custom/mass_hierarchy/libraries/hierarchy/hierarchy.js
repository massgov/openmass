(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.massHierarchy = {
    attach: function (context, settings) {
      // Caching elements and setting vars.
      var $form = $('form[id^=node][id$="-entity-hierarchy-reorder-form"]', context);
      var $table = $('#edit-children', $form);
      var parentId = jQuery('tr.hierarchy-row', $table).eq(0).find('.child-parent').val();
      var wrongBundleMessageId = 'hierarchy-node-wrong-bundle-message';
      var hierarchyMessagesClass = 'messages--hierarchy';

      // Checks original table rows as not loaded yet.
      $('tr.hierarchy-row--parent', $table).data('loaded', false);

      // Loads children async on TRs that are getting a new
      // element dragged as child to them.
      function loadAsyncChildrenWhenChildIsDraggedInto($tr) {
        var $parentRow = getParentFromRow($tr);

        if (!$parentRow) {
          return;
        }

        if ($parentRow.data('loaded') === false) {
          loadTrChildren($parentRow);
          return;
        }
        else if ($parentRow.data('loaded') === true) {
          // If this TR has loaded its children already
          // then we need to ensure that, when something is
          // dragged as its child, its direct children are
          // expanded as well.
          $parentRow.addClass('hierarchy-row--expanded');
          var children = getChildrenFromTr($parentRow);
          children.forEach(function (tr) {
            $(tr).show();
          });
        }
      }

      // Removes parent class on TRs without children
      // that will not load async, or from TRs that already
      // loaded its async elements and do not have children
      // with the current ordering.
      function removeParentClassIfRowIsNotParent() {
        $('.hierarchy-row--parent', $table).each(function (i, tr) {
          var $tr = $(tr);

          if (!$tr.next().length) {
            return;
          }

          var indentation = $tr.find('.indentation').length;
          var nextIndentation = $tr.next().find('.indentation').length;

          // Continue if next item is not a child.
          if (nextIndentation > indentation) {
            return;
          }

          // TR with data loaded false can have children if clicked.
          // TR with undefined data loaded can't have children.
          if ($tr.data('loaded') === false) {
            return;
          }

          $tr.removeClass('hierarchy-row--parent');
        });

      }

      // Gets the direct children from a TR.
      function getChildrenFromTr($tr) {
        var parentLevel = $tr.find('.indentation').length;
        var $nextTr = $tr.next();
        var nextLevel = $nextTr.find('.indentation').length;
        var children = [];
        do {

          if (nextLevel - parentLevel === 1) {
            children.push($nextTr);
            $nextTr = $nextTr.next();
            nextLevel = $nextTr.find('.indentation').length;
          }
          else {
            return children;
          }

        } while (true);  // eslint-disable-line
      }

      // Children can be dragged into TRs that are not shown as parents,
      // so we need to add the parent class when that happens.
      function addParentClassOnRowsWithChildren() {
        var $childIds = $('input.child-id', $table);
        // Defines parents that were created by drag and drop
        // or parents with already loaded children.
        $('.child-parent', $table).each(function (key, value) {
          var parentID = $(value).val();
          var $parentChildId = $childIds.filter('[data-drupal-selector=edit-children-' + parentID + '-id]');
          var $parent = $parentChildId.closest('tr');
          $parent.toggleClass('hierarchy-row--expanded', $parent.next().filter(':visible').length > 0);
          $parent.addClass('hierarchy-row--parent');
        });
      }

      // Hack to ensure the parent level is set correctly.
      function setParentOnFirstLevel() {
        $('tr td:first-child', $table).each(function (i, e) {
          var level = $(e).find('.js-indentation').length;
          if (level === 0) {
            $(e).parent().find('.child-parent').val(parentId);
          }
        });
      }

      // Expands or collapses TR's children.
      function toggleTrChildren($tr) {
        $tr.toggleClass('hierarchy-row--expanded');

        var $child = $tr.next();
        var level = $tr.find('.indentation').length;
        var directChildrenShouldBeVisible = $tr.hasClass('hierarchy-row--expanded');

        while (true) { // eslint-disable-line
          var childLevel = $child.find('.indentation').length;

          // Whether we are expanding or collapsing,
          // the direct children should not be expanded,
          // and deeper items should be hidden.
          $child.removeClass('hierarchy-row--expanded');
          if (childLevel - level > 1) {
            $child.hide();
          }

          // Direct children should follow the parent expanded/collapsed state.
          if (childLevel - level === 1) {
            $child.toggle(directChildrenShouldBeVisible);
          }

          // Let's check the next one if it is a children.
          if (level < childLevel) {
            $child = $child.next();
          }
          else {
            // Not a child? Bye.
            break;
          }
        }
      }

      // Appends loaded children, attaches events, and massages its HTML.
      function manageLoadedChildren($tr, $temp) {
        // Remove loading.
        $tr.find('.hierarchy-row--loading').remove();
        var level = $tr.find('.indentation').length;
        var indentationHTML = '<div class="js-indentation indentation">&nbsp;</div>';
        var childrenIndentation = indentationHTML.repeat(level + 1);
        var justAppendedClass = 'hierarchy-row--just-appended';

        $temp.find('td:first-child').prepend(childrenIndentation);
        // Mark just appended rows.
        $temp.find('tr').addClass(justAppendedClass);

        // Insert children on table.
        $tr.after($temp.html());
        var $rowsJustAppended = $('.' + justAppendedClass, $table);
        // Mark new parent rows as "children not loaded yet".
        $rowsJustAppended
          .filter('.hierarchy-row--parent', $table).data('loaded', false);

        // Attach behaviors to new rows.
        $('.' + justAppendedClass, $table).each(function (index, elem) {
          // The only to insert rows on a draggable table.
          // Requires patching tabledrag.js.
          Drupal.tableDrag.prototype.makeDraggable.bind(jQuery('#edit-children').data('tableDragObject'), elem)();
          $(elem).find('td:nth-child(3)').addClass('tabledrag-hide');
          $(elem).find('td:nth-child(6)').addClass('tabledrag-hide');
          Drupal.attachBehaviors(elem, drupalSettings);
        }).removeClass(justAppendedClass);

        applyEventsToHierarchyControls();

        Drupal.tableDrag.prototype.showColumns();
        Drupal.tableDrag.prototype.hideColumns();
      }

      // Loads TR's children asynchronously and attaches events to them.
      function loadTrChildren($tr) {
        // Mark this TR as it already loaded its children.
        var $control = $tr.find('.hierarchy-row-controls');
        $tr.data('loaded', true);
        $tr.toggleClass('hierarchy-row--expanded');
        // The link tells us the route to fetch its children.
        var href = $control.closest('td').find('a.menu-item__link').attr('href');
        var childrenHref = href + '/children';
        // Loading message.
        $control.closest('td').append('<div class="hierarchy-row--loading">LOADING</div>');
        // Temporary div to load content.
        var $temp = $('<div></div>');
        // Get the children of the TR.
        $temp.load(childrenHref + ' #edit-children tbody tr.hierarchy-row', manageLoadedChildren.bind(this, $tr, $temp));
      }

      // Expands/collapses children in a row when clicked.
      function toggleRowClickEvent(event) {
        var $control = $(event.target);
        var $tr = $control.closest('tr');

        // If this TR has already loaded its children
        // or if the TR doesn't need to load children.
        if ($tr.data('loaded') || typeof $tr.data('loaded') == 'undefined') {
          toggleTrChildren($tr);
          return;
        }
        // Loading children asynchronously.
        loadTrChildren($tr);
      }

      // Iterates to find the parent (if any) for a given row.
      function getParentFromRow($row) {
        var level = $row.find('.js-indentation').length;

        do {
          var $rowAbove = $row.prev();
          if (!$rowAbove.length) {
            return false;
          }
          var rowAboveLevel = $rowAbove.find('.js-indentation').length;
          if (rowAboveLevel >= level) {
            $row = $row.prev();
            continue;
          }
          return $rowAbove;
        } while (true);  // eslint-disable-line
      }

      // Shows an alert when there is a message saying there are wrong
      // parent/child relationship.
      function showAlertDueToWrongBundles() {
        var wrongBundleAlertId = 'hierarchy-node-wrong-bundle-alert';

        var alertBox =
          '<div ' +
          ' id="' +
          wrongBundleAlertId +
          '" ' +
          ' role="contentinfo" ' +
          ' aria-label="Alert" ' +
          ' class="messages messages--error"> ' +
          ' <h2 class="ma__visually-hidden">Status message</h2> ' +
          ' <div>' +
          ' Moving this page to a parent of this content type is not allowed. ' +
          ' Please move the row in red to a different content type. ' +
          ' See knowledge base for more information about what types are allowed.' +
          ' </div>' +
          ' <div class="form-actions">' +
          '   <div class="button" id="hierarchyDismissAlert">Got it</div>' +
          ' </div>' +
          '</div>';

        var alertBoxWrapper =
          '<div ' +
          ' id="' +
          wrongBundleAlertId +
          '--wrapper" ' +
          ' role="contentinfo" ' +
          ' >' +
          ' <h2 class="ma__visually-hidden">Status message</h2> ' +
          alertBox +
          '</div>';

        // Remove once data and class on already alerted rows
        // that currently are not wrong.
        $table.find('tr.hierarchy-row--alerted')
          .not('.hierarchy-row--parent-bundle-is-wrong')
          .removeData('jqueryOnceHierarchyAlert')
          .removeClass('hierarchy-row--alerted');

        if ($('#' + wrongBundleMessageId).length === 0) {
          return;
        }

        // Count new rows with errors.
        var newRowsWithErrors = $(once('hierarchyAlert', 'form[id^=node][id$="-entity-hierarchy-reorder-form"] #edit-children tr.hierarchy-row--parent-bundle-is-wrong', context));
        if (!newRowsWithErrors.length) {
          return;
        }
        else {
          newRowsWithErrors.each(function () {
            $(this).addClass('hierarchy-row--alerted');
          });
        }

        $('main').before(alertBoxWrapper);
        $('#hierarchyDismissAlert').click(function () {
          $('#' + wrongBundleAlertId).parent().remove();
        });
      }

      // Abstraction to check for an error and toggle an error message.
      function checkForErrorsAndMessage($tr, checkerFn, errorClass, wrongMessageId, message) {
        var $parentRow = getParentFromRow($tr);
        var ok = true;
        if ($parentRow) {
          ok = checkerFn($tr, $parentRow);
        }
        $tr.toggleClass(errorClass, !ok);
        var rowsWithErrorsCount = $table.find('tr.' + errorClass).length;

        if (!rowsWithErrorsCount) {
          $('#' + wrongMessageId).remove();
          return true;
        }

        var messageBox =
          '<div ' +
          ' id="' +
          wrongMessageId +
          '" ' +
          ' role="contentinfo" ' +
          ' aria-label="Status message" ' +
          ' class="messages messages--error ' +
          hierarchyMessagesClass +
          '"> ' +
          ' <h2 class="ma__visually-hidden">Status message</h2> ' +
          message +
          '</div>';

        // Only create one wrong bundle message.
        if ($('#' + wrongMessageId).length === 0) {
          $table.before(messageBox);
        }
        return false;
      }

      // Checks if row can be a child for parentRow.
      function isParentBundleCorrect($row, $parentRow) {
        var parentBundle = $parentRow.find('td [data-bundle]').data('bundle');
        var rowBundle = $row.find('td [data-bundle]').data('bundle');
        var allowedBundles = drupalSettings.mass_hierarchy_parent_bundle_info[rowBundle];
        return typeof allowedBundles[parentBundle] != 'undefined';
      }

      // Checks if there are unallowed parent/child relationships,
      // if any, shows a warning message.
      function parentChildRelationshipChecker($tr) {
        return checkForErrorsAndMessage(
          $tr,
          isParentBundleCorrect,
          'hierarchy-row--parent-bundle-is-wrong',
          wrongBundleMessageId,
          ' One or more children have a parent with a content type that isn\'t allowed. ' +
          ' Please choose a parent that has an allowed content type. ' +
          ' For content type limits see our ' +
          ' <a href="/allowedparents_for_contenttypes">knowledge base article</a>. '
        );
      }

      // Enables or disables submit button state if hierarchy messages are found.
      function updateSubmitButtonState() {
        $form.find('#edit-submit').attr('disabled', $form.find('.' + hierarchyMessagesClass).length > 0);
      }

      // Things do on drag events.
      function doOnDrag(event) {
        var $tr = $(event.target).closest('tr');
        parentChildRelationshipChecker($tr);
        showAlertDueToWrongBundles();
        loadAsyncChildrenWhenChildIsDraggedInto($tr);
        removeParentClassIfRowIsNotParent();
        addParentClassOnRowsWithChildren();
        applyEventsToHierarchyControls();
        updateSubmitButtonState();
      }

      // Things to do on submit (and before submit).
      function doOnSubmit() {
        if ($('#' + wrongBundleMessageId).length > 0) {
          return false;
        }
        setParentOnFirstLevel();
      }

      // Centralized way to apply expand/collapse events once.
      function applyEventsToHierarchyControls() {
        var $elements = $(once('hierarchy-expand-collapse', 'form[id^=node][id$="-entity-hierarchy-reorder-form"] #edit-children tr.hierarchy-row', context));
        $elements.each(function () {
          $(this).find('.hierarchy-row-controls')
            .click(toggleRowClickEvent);
        });
      }

      applyEventsToHierarchyControls();
      $(document).on('touchend mouseup pointerup', doOnDrag);
      $form.submit(doOnSubmit);
    }
  };
})(jQuery, Drupal);
