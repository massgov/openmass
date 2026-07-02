<?php

namespace Drupal\Tests\mass_inline_message\Traits;

use Behat\Mink\Session;

/**
 * Selenium helpers for Message box CKEditor and dialog UI tests.
 */
trait InlineMessageJavascriptTestTrait {

  /**
   * Default body field CKEditor textarea selector on node edit forms.
   */
  protected const BODY_FIELD_EDITOR_SELECTOR = '[name="body[0][value]"][data-ckeditor5-id]';

  /**
   * Default max wait for CKEditor / dialog UI (milliseconds).
   */
  protected const JS_WAIT_DEFAULT = 10000;

  /**
   * Longer max wait for LP modal stacks (milliseconds).
   */
  protected const JS_WAIT_LONG = 12000;

  /**
   * Returns the active Mink session.
   */
  protected function inlineMessageSession(): Session {
    return $this->getSession();
  }

  /**
   * Waits until the body field CKEditor instance is present.
   */
  protected function waitForBodyFieldEditor(string $selector = self::BODY_FIELD_EDITOR_SELECTOR): void {
    $this->inlineMessageSession()->wait(
      self::JS_WAIT_DEFAULT,
      'document.querySelector(' . json_encode($selector) . ') !== null',
    );
  }

  /**
   * Waits until the Message box configuration dialog title field is visible.
   */
  protected function waitForMessageBoxDialogOpen(): void {
    $opened = $this->inlineMessageSession()->wait(
      self::JS_WAIT_LONG,
      "(function(){
        var input = document.querySelector('#mass-inline-message-dialog-form input[name=\"attributes[data-title]\"]')
          || document.querySelector('.ui-dialog input[name=\"attributes[data-title]\"]');
        if (!input) { return false; }
        var rect = input.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
      })()",
    );
    $this->assertNotEmpty($opened, 'Message box dialog title field did not become visible.');
  }

  /**
   * Sets the Message box dialog title (Mink fillField is unreliable in nested modals).
   */
  protected function fillMessageBoxDialogTitle(string $title): void {
    $this->inlineMessageSession()->executeScript(
      "(function(){
        var input = document.querySelector('#mass-inline-message-dialog-form input[name=\"attributes[data-title]\"]')
          || document.querySelector('.ui-dialog input[name=\"attributes[data-title]\"]');
        if (!input) { return; }
        input.focus();
        input.value = " . json_encode($title) . ";
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      })();",
    );
  }

  /**
   * Waits until the Message box dialog form is gone and save did not redirect.
   */
  protected function waitForMessageBoxDialogClosed(): void {
    $this->inlineMessageSession()->wait(
      self::JS_WAIT_LONG,
      "(function(){
        if (window.location.href.indexOf('/mass-inline-message/dialog/') !== -1) {
          return false;
        }
        return document.querySelector('#mass-inline-message-dialog-form') === null;
      })()",
    );
  }

  /**
   * Waits until the Message box body CKEditor is ready inside the dialog.
   */
  protected function waitForMessageBoxBodyEditor(): void {
    $this->inlineMessageSession()->wait(
      self::JS_WAIT_DEFAULT,
      "document.querySelector('#mass-inline-message-dialog-form textarea[name=\"body[value]\"][data-ckeditor5-id]') !== null",
    );
  }

  /**
   * Waits until entity embed or file browser UI is visible.
   */
  protected function waitForEntityEmbedDialogOpen(): void {
    $this->inlineMessageSession()->wait(
      self::JS_WAIT_LONG,
      "(function(){
        return document.querySelector('form.entity-embed-dialog') !== null
          || document.querySelector('input[name^=\"entity_browser_select[\"]') !== null;
      })()",
    );
  }

  /**
   * Asserts Message box dialog save stayed in the parent edit Ajax flow.
   */
  protected function assertMessageBoxSaveDidNotRedirectToDialogRoute(): void {
    $url = $this->inlineMessageSession()->getCurrentUrl();
    $this->assertStringNotContainsString('/mass-inline-message/dialog/', $url);
  }

  /**
   * Simulates entity embed dialog save (must not insert an empty Message box).
   */
  protected function triggerEntityEmbedEditorDialogSave(): void {
    $this->inlineMessageSession()->executeScript(
      "(function(){
        if (!window.jQuery) { return; }
        window.jQuery(window).trigger('editor:dialogsave', [{
          attributes: {
            'data-entity-type': 'media',
            'data-entity-uuid': '00000000-0000-0000-0000-000000000001',
            'data-embed-button': 'media_entity_download'
          }
        }]);
      })();",
    );
  }

  /**
   * Clicks the embed/media toolbar button in the Message box body CKEditor.
   */
  protected function clickMessageBoxBodyEmbedToolbarButton(): void {
    $this->inlineMessageSession()->executeScript(
      "(function(){
        var textarea = document.querySelector('#mass-inline-message-dialog-form textarea[name=\"body[value]\"]')
          || document.querySelector('.ui-dialog textarea[name=\"body[value]\"]');
        if (!textarea || !window.Drupal || !Drupal.CKEditor5Instances) { return; }
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        if (!editor) { return; }
        var toolbarItems = ['file_browser', 'mediaEntityDownload'];
        for (var i = 0; i < toolbarItems.length; i++) {
          try {
            var button = editor.ui.componentFactory.create(toolbarItems[i]);
            if (button) {
              button.fire('execute');
              return;
            }
          }
          catch (e) {
            // Try the next toolbar item.
          }
        }
        var form = document.querySelector('#mass-inline-message-dialog-form');
        var dialog = form ? form.closest('.ui-dialog') : null;
        if (!dialog) { return; }
        var selectors = [
          '.ck-toolbar button[aria-label*=\"image\" i]',
          '.ck-toolbar button[aria-label*=\"embed\" i]',
          '.ck-toolbar button[aria-label*=\"document\" i]',
        ];
        for (var s = 0; s < selectors.length; s++) {
          var domButton = dialog.querySelector(selectors[s]);
          if (domButton) {
            domButton.click();
            return;
          }
        }
      })();",
    );
  }

  /**
   * Sets HTML in the Message box dialog body CKEditor and syncs the textarea.
   */
  protected function setMessageBoxDialogBodyHtml(string $html): void {
    $this->inlineMessageSession()->executeScript(
      "(function(){
        var textarea = document.querySelector('#mass-inline-message-dialog-form textarea[name=\"body[value]\"]')
          || document.querySelector('.ui-dialog textarea[name=\"body[value]\"]');
        if (!textarea) { return; }
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        if (editor) {
          editor.setData(" . json_encode($html) . ");
          editor.updateSourceElement();
        }
      })();",
    );
  }

  protected function clickMessageBoxDialogSave(): void {
    $this->inlineMessageSession()->executeScript(
      "(function(){
        var form = document.querySelector('#mass-inline-message-dialog-form');
        if (!form) { return; }
        var dialog = form.closest('.ui-dialog');
        if (!dialog) { return; }
        var paneButtons = dialog.querySelectorAll('.ui-dialog-buttonpane button, .ui-dialog-buttonpane input[type=\"submit\"]');
        for (var i = 0; i < paneButtons.length; i++) {
          var label = (paneButtons[i].value || paneButtons[i].textContent || '').trim().toLowerCase();
          if (label === 'save') {
            paneButtons[i].click();
            return;
          }
        }
        var fallback = form.querySelector('.form-actions input[type=\"submit\"], .form-actions button[type=\"submit\"]');
        if (fallback) { fallback.click(); }
      })();",
    );
  }

  /**
   * Returns CKEditor getData() for a textarea matched by CSS selector.
   */
  protected function getCkeditorData(string $textareaSelector): string {
    return (string) $this->inlineMessageSession()->evaluateScript(
      "(function(){
        var textarea = document.querySelector(" . json_encode($textareaSelector) . ");
        if (!textarea || !Drupal.CKEditor5Instances.has(textarea.getAttribute('data-ckeditor5-id'))) {
          return '';
        }
        return Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id')).getData();
      })();",
    );
  }

  /**
   * Sets CKEditor data for a textarea matched by CSS selector.
   */
  protected function setCkeditorData(string $textareaSelector, string $html): string {
    return (string) $this->inlineMessageSession()->evaluateScript(
      "(function(){
        var textarea = document.querySelector(" . json_encode($textareaSelector) . ");
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        editor.setData(" . json_encode($html) . ");
        return editor.getData();
      })();",
    );
  }

  /**
   * Fires the Message box toolbar button on a CKEditor instance.
   */
  protected function fireMessageBoxToolbarButton(string $textareaSelector): void {
    $this->inlineMessageSession()->executeScript(
      "(function(){
        var textarea = document.querySelector(" . json_encode($textareaSelector) . ");
        if (!textarea) { return; }
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        if (!editor) { return; }
        editor.model.change(function(writer) {
          var root = editor.model.document.getRoot();
          writer.setSelection(writer.createPositionAt(root, 'end'));
        });
        editor.editing.view.focus();
        var button = editor.ui.componentFactory.create('messageBox');
        if (button) {
          button.fire('execute');
        }
      })();",
    );
  }

  /**
   * Creates a basic page node with a basic_html body for CKEditor tests.
   */
  protected function createBasicPageWithBody(): int {
    $node = $this->createNode([
      'type' => 'page',
      'title' => 'Message box test ' . $this->randomMachineName(8),
      'body' => [
        'value' => '<p>Initial body.</p>',
        'format' => 'basic_html',
      ],
      'status' => 1,
    ]);
    $node->save();
    return (int) $node->id();
  }

}
