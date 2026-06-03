(function(){
  function getLpRichTextEditor() {
    var textarea = document.querySelector('.ui-dialog textarea[data-ckeditor5-id]');
    if (!textarea) { return null; }
    return Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
  }

  function getSelectedWidgetDom(editor) {
    var selected = editor.model.document.selection.getSelectedElement();
    if (!selected || selected.name !== 'massInlineMessage') {
      return null;
    }
    var viewElement = editor.editing.mapper.toViewElement(selected);
    if (!viewElement) {
      return null;
    }
    return editor.editing.view.domConverter.mapViewToDom(viewElement);
  }

  function getWidgetToolbarAlignment() {
    var editor = getLpRichTextEditor();
    if (!editor) {
      return { ok: false, reason: 'no-editor' };
    }
    var widget = getSelectedWidgetDom(editor);
    var balloon = null;
    var panels = document.querySelectorAll('.ck-body-wrapper .ck-balloon-panel.ck-balloon-panel_visible');
    for (var p = 0; p < panels.length; p++) {
      if (panels[p].classList.contains('ck-powered-by-balloon')) {
        continue;
      }
      if (panels[p].classList.contains('ck-toolbar-container')
        || panels[p].querySelector('.ck-button[data-cke-tooltip-text="Edit"]')) {
        balloon = panels[p];
        break;
      }
    }
    if (!widget || !balloon) {
      return { ok: false, hasWidget: !!widget, hasBalloon: !!balloon };
    }
    var wr = widget.getBoundingClientRect();
    var br = balloon.getBoundingClientRect();
    var overlap = Math.min(wr.right, br.right) - Math.max(wr.left, br.left);
    var balloonAbove = br.bottom <= wr.top + 12;
    var closeVertically = Math.abs(br.bottom - wr.top) < 140;
    var notAtModalTop = br.top > 40;
    return {
      ok: overlap > 20 && balloonAbove && closeVertically && notAtModalTop,
      overlap: overlap,
      balloonTop: br.top,
      widgetTop: wr.top
    };
  }

  function findMassInlineMessageModel(editor) {
    var roots = Array.from(editor.model.document.getRoots());
    for (var r = 0; r < roots.length; r++) {
      var children = Array.from(roots[r].getChildren());
      for (var c = 0; c < children.length; c++) {
        if (children[c].name === 'massInlineMessage') {
          return children[c];
        }
      }
    }
    return null;
  }

  function selectMessageBoxWidget() {
    var editor = getLpRichTextEditor();
    if (!editor) { return false; }
    var modelElement = findMassInlineMessageModel(editor);
    if (!modelElement) { return false; }
    editor.model.change(function(writer) {
      writer.setSelection(modelElement, 'on');
    });
    editor.editing.view.focus();
    editor.ui.update();
    var selected = editor.model.document.selection.getSelectedElement();
    return !!(selected && selected.name === 'massInlineMessage');
  }

  function clickWidgetEditButton() {
    var buttons = document.querySelectorAll('.ck-body-wrapper .ck-toolbar .ck-button');
    for (var i = 0; i < buttons.length; i++) {
      var tip = (buttons[i].getAttribute('data-cke-tooltip-text') || buttons[i].getAttribute('aria-label') || '').toLowerCase();
      if (tip === 'edit') {
        buttons[i].click();
        return true;
      }
    }
    return false;
  }

  window.__massInlineMessageLpToolbarTest = {
    getWidgetToolbarAlignment: getWidgetToolbarAlignment,
    selectMessageBoxWidget: selectMessageBoxWidget,
    clickWidgetEditButton: clickWidgetEditButton
  };
  return true;
})();
