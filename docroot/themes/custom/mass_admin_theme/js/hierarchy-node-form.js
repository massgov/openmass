jQuery(document).ready(function ($) {
  // Caching elements and setting vars.
  let $form = $('form#node-topic-page-entity-hierarchy-reorder-form');
  let $table = $('#edit-children', $form);
  let parentId = jQuery('tr.hierarchy-row', $table).eq(0).find('.child-parent').val();

  // Checks original table rows as not loaded yet.
  $('tr.hierarchy-row--parent', $table).data('loaded', false);

  function checkParents() {
    // Removes parent and expanded classes from rows
    // that already had loaded its children, or from
    // rows that became parent due to drag and drop of
    // another row.
    $('.hierarchy-row--parent', $table).each(function (i, e) {
      let $tr = $(e);
      if ($tr.data('loaded') === false) {
        return;
      } else if ($tr.data('loaded') === true) {
        $tr.addClass('hierarchy-row--expanded');
      }
      $tr.removeClass('hierarchy-row--parent');
    });

    // Defines parents that were created by drag and drop
    // or parents with already loaded children.
    $('.child-parent', $table).each(function (key, value) {
      let parentID = $(value).val();
      let $parent = $('[data-drupal-selector=edit-children-'+ parentID +']', $table);
      $parent.addClass('hierarchy-row--parent');
    });
  };

  // Hack to ensure the parent level is set correctly.
  function setParentOnFirstLevel() {
    $('tr td:first-child', $table).each(function (i, e) {
      let level = $(e).find('.js-indentation').length;
      if (level===0) {
        $(e).parent().find('.child-parent').val(parentId);
      }
    });
  };

  function toggleTrChildren($tr) {
    $tr.toggleClass('hierarchy-row--expanded');

    let $child = $tr.next();
    let level = $tr.find('.indentation').length;

    while (true) {
      let childLevel = $child.find('.indentation').length;

      if (level < childLevel) {
        $child.toggle();
        $child = $child.next();
      } else {
        break;
      }
    }
  }

  function loadTrChildren($control, $tr) {
    // Mark this TR as it already loaded its children.
    $tr.data('loaded', true);
    $tr.toggleClass('hierarchy-row--expanded');
    // The link tells us the route to fetch its children.
    let href = $control.closest('td').find('a.menu-item__link').attr('href');
    let childrenHref = href + '/children';

    let $temp = $("<div></div>");
    // Get the children of the TR.
    $temp.load(childrenHref + ' #edit-children tbody tr.hierarchy-row', function () {
      let level = $tr.find('.indentation').length;
      let indentationHTML = '<div class="js-indentation indentation">&nbsp;</div>';
      let childrenIndentation = indentationHTML.repeat(level + 1);
      let justAppendedClass = 'hierarchy-row--just-appended';

      $temp.find('td:first-child').prepend(childrenIndentation);
      // Mark just appended rows.
      $temp.find('tr').addClass(justAppendedClass);

      // Insert children on table.
      $tr.after($temp.html());
      let $rowsJustAppended = $('.' + justAppendedClass, $table);
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
        Drupal.attachBehaviors(elem, drupalSettings)
      }).removeClass(justAppendedClass);

      Drupal.tableDrag.prototype.showColumns();
      Drupal.tableDrag.prototype.hideColumns();
    });

  }

  // Expands/collapses children in a row when clicked.
  function toggleRowClickEvent(event) {
    let $control = $(event.target);
    let $tr = $control.closest('tr');

    // If this TR has already loaded its children
    // or if the TR doesn't need to load children.
    if ($tr.data('loaded') || typeof $tr.data('loaded') == 'undefined') {
      toggleTrChildren($tr);
      return;
    }
    // Loading children asynchronously.
    loadTrChildren($control, $tr);
  }

  $('tr .hierarchy-row-controls div', $table).click(toggleRowClickEvent);

  $(document).on('touchend mouseup pointerup', (event) => {
    let $rowHandle = $(event.target, $table);
    if ($rowHandle.hasClass('tabledrag-handle') || $rowHandle.hasClass('handle')) {
      checkParents();
    }
  });

  $form.submit(setParentOnFirstLevel);
});
