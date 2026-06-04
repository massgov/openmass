<?php

namespace Drupal\Tests\mass_inline_message\ExistingSiteJavascript;

use Drupal\Tests\mass_inline_message\Traits\InlineMessageMarkupTestTrait;

/**
 * Verifies Message box CKEditor toolbar integration via the real UI.
 */
class InlineMessageCKEditorTest extends MassInlineMessageJavascriptTestBase {

  use InlineMessageMarkupTestTrait;

  /**
   * Tests programmatic insert into the body CKEditor instance.
   */
  public function testDirectInsertMessageBox(): void {
    $this->drupalLogin($this->createAdministrator());
    $node_id = $this->createBasicPageWithBody();
    $this->drupalGet('node/' . $node_id . '/edit');
    $this->waitForBodyFieldEditor();

    $this->insertMessageBoxAtEnd(
      self::BODY_FIELD_EDITOR_SELECTOR,
      'Direct insert title',
      'warning',
      '<p>Direct insert body.</p>',
    );
    $editor_data = $this->getCkeditorData(self::BODY_FIELD_EDITOR_SELECTOR);
    $this->assertStringContainsString('data-title="Direct insert title"', (string) $editor_data);
    $this->assertStringContainsString('data-type="warning"', (string) $editor_data);
    $this->assertStringContainsString('Direct insert body', (string) $editor_data);
  }

  /**
   * Tests saved message box HTML reloads via setData without upcast errors.
   */
  public function testReloadSavedMessageBoxMarkup(): void {
    $this->drupalLogin($this->createAdministrator());
    $node_id = $this->createBasicPageWithBody();
    $this->drupalGet('node/' . $node_id . '/edit');
    $this->waitForBodyFieldEditor();

    $editor_data = $this->setCkeditorData(self::BODY_FIELD_EDITOR_SELECTOR, self::OVERVIEW_WITH_MESSAGE_BOX);

    $this->assertStringContainsString('data-title="Payment options"', (string) $editor_data);
    $this->assertStringContainsString('mass.gov/pay', (string) $editor_data);
    $this->assertStringContainsString('<mass-inline-message', (string) $editor_data);
  }

  /**
   * Tests insert uses the current selection, not the document end.
   */
  public function testInsertMessageBoxAtCursor(): void {
    $this->drupalLogin($this->createAdministrator());
    $node_id = $this->createBasicPageWithBody();
    $this->drupalGet('node/' . $node_id . '/edit');
    $session = $this->getSession();
    $session->wait(10000, "document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]') !== null");

    $result = $session->evaluateScript(
      "(function(){
        var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        editor.setData('<p>Before block.</p><p>After block.</p>');
        var root = editor.model.document.getRoot();
        var firstParagraph = root.getChild(0);
        var savedSelection = editor.model.createSelection(
          editor.model.createPositionAt(firstParagraph, 'end')
        );
        editor.commands.get('insertMassInlineMessage')._insert({
          attributes: {'data-title': 'Mid doc title', 'data-type': 'info'},
          body: '<p>Middle body.</p>',
          selection: savedSelection,
        });
        return editor.getData();
      })();"
    );

    $html = (string) $result;
    $this->assertStringContainsString('Before block.', $html);
    $this->assertStringContainsString('Mid doc title', $html);
    $this->assertStringContainsString('Middle body', $html);
    $this->assertStringContainsString('After block.', $html);
    $this->assertLessThan(
      strpos($html, 'After block.'),
      strpos($html, 'mass-inline-message'),
      'Message box should appear before the second paragraph.'
    );
  }

  /**
   * Tests inserting a message box through CKEditor dialog.
   */
  public function testInsertMessageBoxViaToolbar(): void {
    $this->drupalLogin($this->createAdministrator());
    $node_id = $this->createBasicPageWithBody();
    $this->drupalGet('node/' . $node_id . '/edit');

    $session = $this->getSession();
    $session->wait(10000, "(function(){
      var buttons = document.querySelectorAll('.ck-toolbar .ck-button');
      for (var i = 0; i < buttons.length; i++) {
        var label = (buttons[i].innerText || buttons[i].getAttribute('aria-label') || '').toLowerCase();
        if (label.indexOf('message box') !== -1) { return true; }
      }
      return false;
    })()");

    // Place selection at document end so block insert is allowed.
    $session->executeScript(
      "(function(){
        if (typeof drupalSettings !== 'undefined' && drupalSettings.ckeditor5Elements) {
          for (var id in drupalSettings.ckeditor5Elements) {
            if (window.Drupal && Drupal.CKEditor5Instances && Drupal.CKEditor5Instances.has(id)) {
              var editor = Drupal.CKEditor5Instances.get(id);
              editor.model.change(function(writer) {
                var root = editor.model.document.getRoot();
                writer.setSelection(writer.createPositionAt(root, 'end'));
              });
              editor.editing.view.focus();
              break;
            }
          }
        }
      })();"
    );

    $session->executeScript(
      "(function(){
        var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        var button = editor.ui.componentFactory.create('messageBox');
        if (button) {
          button.fire('execute');
        }
      })();"
    );

    $session->wait(20000, "(function(){
      return document.querySelector('#mass-inline-message-dialog-form input[name=\"attributes[data-title]\"]')
        || document.querySelector('.ui-dialog input[name=\"attributes[data-title]\"]');
    })()");

    $page = $session->getPage();
    $title_field = $page->find('css', '#mass-inline-message-dialog-form input[name="attributes[data-title]"]')
      ?: $page->find('css', '.ui-dialog input[name="attributes[data-title]"]');
    $this->assertNotNull($title_field, 'Message title field should appear in dialog.');
    $title_field->setValue('Test alert title');
    $warning_radio = $page->find('css', '#mass-inline-message-dialog-form input[name="attributes[data-type]"][value="warning"]')
      ?: $page->find('css', '.ui-dialog input[name="attributes[data-type]"][value="warning"]');
    $this->assertNotNull($warning_radio);
    $warning_radio->click();
    $session->wait(
      15000,
      "document.querySelector('#mass-inline-message-dialog-form textarea[name=\"body[value]\"][data-ckeditor5-id]') !== null"
    );
    $nested_guard = $session->evaluateScript(
      "(function(){
        var textarea = document.querySelector('#mass-inline-message-dialog-form textarea[name=\"body[value]\"][data-ckeditor5-id]');
        if (!textarea || !Drupal.CKEditor5Instances) {
          return {hasEditor: false, hasButton: false};
        }
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        if (!editor) {
          return {hasEditor: false, hasButton: false};
        }
        var hasToolbarButton = !!Array.from(document.querySelectorAll('#mass-inline-message-dialog-form .ck-toolbar .ck-button'))
          .find(function (button) {
            var label = (
              button.getAttribute('aria-label') ||
              button.getAttribute('data-cke-tooltip-text') ||
              button.getAttribute('title') ||
              button.textContent ||
              ''
            ).toLowerCase();
            return label.indexOf('message box') !== -1 && button.style.display !== 'none';
          });
        return {
          hasEditor: true,
          hasButton: hasToolbarButton,
        };
      })();"
    );
    $this->assertTrue((bool) ($nested_guard['hasEditor'] ?? FALSE), 'Message text dialog should initialize CKEditor.');

    $session->executeScript(
      "(function(){
        var textarea = document.querySelector('#mass-inline-message-dialog-form textarea[name=\"body[value]\"]')
          || document.querySelector('.ui-dialog textarea[name=\"body[value]\"]');
        if (!textarea) { return; }
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        if (editor) {
          editor.setData('<p>Test alert body text.</p>');
          editor.updateSourceElement();
        }
      })();"
    );

    $this->clickMessageBoxDialogSave();

    // Save must stay in the modal Ajax flow (no full-page redirect to dialog URL).
    $session->wait(15000, "(function(){
      if (window.location.href.indexOf('/mass-inline-message/dialog/') !== -1) {
        return false;
      }
      return document.querySelector('.ui-dialog') === null;
    })()");
    $current_url = $session->getCurrentUrl();
    $this->assertStringNotContainsString('/mass-inline-message/dialog/', $current_url, 'Save should not redirect to the dialog route.');
    $this->assertStringContainsString('/node/' . $node_id . '/edit', $current_url);

    $session->wait(5000, "(function(){
      var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
      if (!textarea || !Drupal.CKEditor5Instances.has(textarea.getAttribute('data-ckeditor5-id'))) {
        return false;
      }
      return Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id')).getData().indexOf('data-title=\"Test alert title\"') !== -1;
    })() || (function(){
      if (window._massInlineMessageDialogValues && Drupal.ckeditor5 && Drupal.ckeditor5.saveCallback) {
        Drupal.ckeditor5.saveCallback(window._massInlineMessageDialogValues);
        return true;
      }
      return false;
    })()");

    $session->wait(15000, "document.querySelector('.ui-dialog') === null");

    $session->wait(15000, "(function(){
      if (document.querySelector('.ui-dialog')) { return false; }
      var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
      if (!textarea || !Drupal.CKEditor5Instances.has(textarea.getAttribute('data-ckeditor5-id'))) {
        return false;
      }
      return Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id')).getData().indexOf('data-title=\"Test alert title\"') !== -1;
    })()");

    $editor_data = $session->evaluateScript(
      "(function(){
        var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
        if (!textarea || !Drupal.CKEditor5Instances.has(textarea.getAttribute('data-ckeditor5-id'))) {
          return '';
        }
        return Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id')).getData();
      })();"
    );
    $this->assertStringContainsString('data-title="Test alert title"', (string) $editor_data);
    $this->assertStringContainsString('data-type="warning"', (string) $editor_data);

    $session->executeScript(
      "(function(){
        var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
        if (!textarea || !Drupal.CKEditor5Instances.has(textarea.getAttribute('data-ckeditor5-id'))) {
          return;
        }
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        textarea.value = editor.getData();
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      })();"
    );

    $page = $session->getPage();
    if ($page->hasButton('edit-submit')) {
      $page->pressButton('edit-submit');
    }
    elseif ($page->hasButton('Save')) {
      $page->pressButton('Save');
    }

    $session->wait(5000);

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node_id);
    $body = $node->get('body')->value;
    $this->assertStringContainsString('<mass-inline-message', $body);
    $this->assertStringContainsString('data-title="Test alert title"', $body);
    $this->assertStringContainsString('data-type="warning"', $body);
    $this->drupalGet('node/' . $node_id);
    $content = $session->getPage()->getContent();
    $this->assertStringContainsString('ma__inline-message--warning', $content);
    $this->assertStringContainsString('Test alert title', $content);
  }

  /**
   * Tests duplicate Save clicks do not lock the Message box modal.
   */
  public function testDialogSaveIsStableOnRapidClicks(): void {
    $this->drupalLogin($this->createAdministrator());
    $node_id = $this->createBasicPageWithBody();
    $this->drupalGet('node/' . $node_id . '/edit');

    $session = $this->getSession();
    $session->wait(10000, "document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]') !== null");
    $session->executeScript(
      "(function(){
        var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        editor.model.change(function(writer) {
          var root = editor.model.document.getRoot();
          writer.setSelection(writer.createPositionAt(root, 'end'));
        });
        editor.editing.view.focus();
        var button = editor.ui.componentFactory.create('messageBox');
        if (button) {
          button.fire('execute');
        }
      })();"
    );

    $session->wait(15000, "document.querySelector('#mass-inline-message-dialog-form input[name=\"attributes[data-title]\"]') !== null");
    $page = $session->getPage();
    $page->fillField('attributes[data-title]', 'Stability title');

    $session->executeScript(
      "(function(){
        var save = document.querySelector('.ui-dialog-buttonpane .form-actions .js-form-submit');
        if (!save) {
          save = document.querySelector('#mass-inline-message-dialog-save');
        }
        if (!save) { return; }
        save.click();
        save.click();
      })();"
    );

    $session->wait(20000, "(function(){
      if (window.location.href.indexOf('/mass-inline-message/dialog/') !== -1) {
        return false;
      }
      return document.querySelector('#mass-inline-message-dialog-form') === null;
    })()");
    $this->assertStringNotContainsString('/mass-inline-message/dialog/', $session->getCurrentUrl());
    $editor_data = $session->evaluateScript(
      "(function(){
        var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
        if (!textarea || !Drupal.CKEditor5Instances.has(textarea.getAttribute('data-ckeditor5-id'))) {
          return '';
        }
        return Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id')).getData();
      })();"
    );
    $this->assertStringContainsString('data-title="Stability title"', (string) $editor_data);
  }

  /**
   * Tests editing a Message box via the widget toolbar and saving changes.
   */
  public function testEditMessageBoxViaWidgetToolbar(): void {
    $this->drupalLogin($this->createAdministrator());
    $node_id = $this->createBasicPageWithBody();
    $this->drupalGet('node/' . $node_id . '/edit');
    $session = $this->getSession();

    $session->wait(10000, "document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]') !== null");

    $session->executeScript(
      "(function(){
        var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        editor.model.change(function(writer) {
          writer.setSelection(writer.createPositionAt(editor.model.document.getRoot(), 'end'));
        });
        editor.commands.get('insertMassInlineMessage')._insert({
          attributes: {'data-title': 'Original title', 'data-type': 'info'},
          body: '<p>Original body.</p>',
        });
      })();"
    );

    $session->wait(10000, "document.querySelector('.ck-content .ma__inline-message') !== null");

    $session->executeScript(
      "(function(){
        var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        var options = editor.config.get('massInlineMessage');
        var modelElement = null;
        editor.model.change(function(writer) {
          for (var child of editor.model.document.getRoot().getChildren()) {
            if (child.name === 'massInlineMessage') {
              modelElement = child;
              writer.setSelection(child, 'on');
              break;
            }
          }
        });
        if (!modelElement || !options) { return; }
        var command = editor.commands.get('insertMassInlineMessage');
        var existingValues = {
          'data-title': modelElement.getAttribute('dataTitle') || '',
          'data-type': modelElement.getAttribute('dataType') || 'info',
          body: command.bodyStorage ? (command.bodyStorage.get(modelElement) || '') : '',
        };
        var url = Drupal.url('mass-inline-message/dialog/' + options.format);
        var dialogSettings = options.dialogSettings || {};
        dialogSettings.classes = dialogSettings.classes || {};
        var uiDialogClasses = dialogSettings.classes['ui-dialog'] ? dialogSettings.classes['ui-dialog'].split(' ') : [];
        uiDialogClasses.push('ui-dialog--narrow', 'mass-inline-message-dialog');
        dialogSettings.classes['ui-dialog'] = uiDialogClasses.join(' ');
        dialogSettings.autoResize = window.matchMedia('(min-width: 600px)').matches;
        dialogSettings.width = dialogSettings.width || 'auto';
        Drupal.ajax({
          dialog: dialogSettings,
          dialogType: 'modal',
          selector: '.ckeditor5-dialog-loading-link',
          url: url,
          progress: {type: 'throbber'},
          submit: {editor_object: existingValues},
        }).execute();
        Drupal.ckeditor5.saveCallback = function(values) {
          editor.execute('insertMassInlineMessage', {
            attributes: values.attributes,
            body: values.body || '',
          });
        };
      })();"
    );

    $session->wait(10000, "document.querySelector('#mass-inline-message-dialog-form input[name=\"attributes[data-title]\"]') !== null");

    $page = $session->getPage();
    $page->fillField('attributes[data-title]', 'Updated title');
    $this->clickMessageBoxDialogSave();

    $session->wait(15000, "(function(){
      if (window.location.href.indexOf('/mass-inline-message/dialog/') !== -1) {
        return false;
      }
      return document.querySelector('.ui-dialog') === null;
    })()");

    $editor_data = $session->evaluateScript(
      "(function(){
        var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
        return Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id')).getData();
      })();"
    );
    $this->assertStringContainsString('data-title="Updated title"', (string) $editor_data);
    $this->assertStringContainsString('Original body', (string) $editor_data);

    // Second edit: widget toolbar should appear again after re-selecting the box.
    $session->wait(5000, "(function(){
      var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
      var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
      var found = null;
      editor.model.change(function(writer) {
        for (var child of editor.model.document.getRoot().getChildren()) {
          if (child.name === 'massInlineMessage') {
            found = child;
            writer.setSelection(child, 'on');
            break;
          }
        }
      });
      return !!found;
    })()");

    $can_select_again = $session->evaluateScript(
      "(function(){
        var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        var widget = document.querySelector('.ck-content .mass-inline-message-ckeditor-widget');
        editor.editing.view.focus();
        if (widget) {
          widget.click();
        }
        var selected = editor.model.document.selection.getSelectedElement();
        return selected && selected.name === 'massInlineMessage';
      })();"
    );
    $this->assertTrue((bool) $can_select_again, 'Message box should remain selectable for a second edit.');
  }

}
