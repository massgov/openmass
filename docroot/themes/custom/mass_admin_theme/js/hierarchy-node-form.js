
jQuery(document).ready(function ($) {
  'use strict';
  // Caching elements and setting vars.
  var $form = $('form[id^=node][id$="-entity-hierarchy-reorder-form"]');
  var $table = $('#edit-children', $form);
  var parentId = jQuery('tr.hierarchy-row', $table).eq(0).find('.child-parent').val();

  // Checks original table rows as not loaded yet.
  $('tr.hierarchy-row--parent', $table).data('loaded', false);

  function checkParents() {
    // Removes parent and expanded classes from rows
    // that already had loaded its children, or from
    // rows that became parent due to drag and drop of
    // another row.
    $('.hierarchy-row--parent', $table).each(function (i, e) {
      var $tr = $(e);
      if ($tr.data('loaded') === false) {
        return;
      }
      else if ($tr.data('loaded') === true) {
        $tr.addClass('hierarchy-row--expanded');
      }
      $tr.removeClass('hierarchy-row--parent');
    });

    // Defines parents that were created by drag and drop
    // or parents with already loaded children.
    $('.child-parent', $table).each(function (key, value) {
      var parentID = $(value).val();
      var $parent = $('[data-drupal-selector=edit-children-' + parentID + ']', $table);
      $parent.addClass('hierarchy-row--parent');
    });

    // Likely, we have created expand/collapse controls,
    // hence we need to attach events on them.
    applyEventsToHierarchyControls();
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

  function loadTrChildren($control, $tr) {
    // Mark this TR as it already loaded its children.
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
    $temp.load(childrenHref + ' #edit-children tbody tr.hierarchy-row', function () {
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
        .filter('.hierarchy-row--parent', $table).data('loaded', false)
        // Attach events to new rows.
        .find('.hierarchy-row-controls div').click(toggleRowClickEvent);

      // Attach behaviors to new rows.
      $('.' + justAppendedClass, $table).each(function (index, elem) {
        // The only to insert rows on a draggable table.
        // Requires patching tabledrag.js.
        Drupal.tableDrag.prototype.makeDraggable.bind(jQuery('#edit-children').data('tableDragObject'), elem)();
        $(elem).find('td:nth-child(3)').addClass('tabledrag-hide');
        $(elem).find('td:nth-child(6)').addClass('tabledrag-hide');
        Drupal.attachBehaviors(elem, drupalSettings);
      }).removeClass(justAppendedClass);

      Drupal.tableDrag.prototype.showColumns();
      Drupal.tableDrag.prototype.hideColumns();
    });

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
    loadTrChildren($control, $tr);
  }

  // Iterates to find the parent (if any) for a given row.
  function getParentFromRow($row) {
    var level = $row.find('.js-indentation').length;

    do {
      var $rowAbove = $row.prev();
      if (!$rowAbove.length) {
        return true;
      }
      var rowAboveLevel = $rowAbove.find('.js-indentation').length;
      if (rowAboveLevel >= level) {
        $row = $row.prev();
        continue;
      }

      return $rowAbove;
    } while (true);  // eslint-disable-line
  }

  // Checks if row can be a child for parentRow.
  function isParentCorrect($row, $parentRow) {
    var parentBundle = $parentRow.find('td [data-bundle]').data('bundle');
    var rowBundle = $row.find('td [data-bundle]').data('bundle');
    var allowedBundles = drupalSettings.mass_hierarchy_parent_bundle_info[rowBundle];
    return typeof allowedBundles[parentBundle] != 'undefined';
  }

  // Checks if there are rows with errors and toggles a warning message.
  function parentChildRelationshipChecker() {
    var rowsWithErrorsCount = $table.find('tr.hierarchy-row--is-wrong').length;

    if (!rowsWithErrorsCount) {
      $('#hierarchy-node-wrong-message').remove();
      return true;
    }

    var messageBox =
      '<div' +
        'id="hierarchy-node-wrong-message"' +
        'role="contentinfo"' +
        'aria-label="Status message"' +
        'class="messages messages--warning">' +
          '<h2 class="visually-hidden">Status message</h2>' +
          'One or more children have a parent with a content type that isn\'t allowed.' +
          'Please choose a parent that has an allowed content type.' +
          'For content type limits see our' +
          '<a href="/">knowledge base article</a>.' +
      '</div>';

    if ($('#hierarchy-node-wrong-message').length === 0) {
      $('.region-highlighted').append(messageBox);
    }

    return false;
  }

  // Things do on drag events.
  function doOnDrag(event) {
    var $rowHandle = $(event.target, $table);
    var $row = $($rowHandle).closest('tr');
    var $parentRow = getParentFromRow($row);

    var ok = true;
    if ($parentRow !== true) {
      ok = isParentCorrect($row, $parentRow);
    }
    $row.toggleClass('hierarchy-row--is-wrong', !ok);
    parentChildRelationshipChecker();

    if ($rowHandle.hasClass('tabledrag-handle') || $rowHandle.hasClass('handle')) {
      checkParents();
    }
  }

  // Things to do on submit (and before submit).
  function doOnSubmit() {
    if (!parentChildRelationshipChecker()) {
      return false;
    }
    setParentOnFirstLevel();
  }

  // Centralized way to apply expand/collapse events once.
  function applyEventsToHierarchyControls() {
    $('tr.hierarchy-row', $table)
      .once('hierarchy-expand-collapse')
      .find('.hierarchy-row-controls')
      .click(toggleRowClickEvent);
  }

  applyEventsToHierarchyControls();

  $(document).on('touchend mouseup pointerup', doOnDrag);
  $form.submit(doOnSubmit);
});
