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
      10000,
      'document.querySelector(' . json_encode($selector) . ') !== null',
    );
  }

  /**
   * Waits until the Message box configuration dialog title field is visible.
   */
  protected function waitForMessageBoxDialogOpen(): void {
    $this->inlineMessageSession()->wait(
      20000,
      "(function(){
        return document.querySelector('#mass-inline-message-dialog-form input[name=\"attributes[data-title]\"]')
          || document.querySelector('.ui-dialog input[name=\"attributes[data-title]\"]');
      })()",
    );
  }

  /**
   * Waits until the Message box dialog form is gone and save did not redirect.
   */
  protected function waitForMessageBoxDialogClosed(): void {
    $this->inlineMessageSession()->wait(
      20000,
      "(function(){
        if (window.location.href.indexOf('/mass-inline-message/dialog/') !== -1) {
          return false;
        }
        return document.querySelector('#mass-inline-message-dialog-form') === null;
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
   * Clicks Save in the Message box configuration dialog only.
   */
  protected function clickMessageBoxDialogSave(): void {
    $this->inlineMessageSession()->executeScript(
      "(function(){
        var form = document.querySelector('#mass-inline-message-dialog-form');
        if (!form) { return; }
        var dialog = form.closest('.ui-dialog');
        if (!dialog) { return; }
        var buttons = dialog.querySelectorAll('.ui-dialog-buttonpane .form-actions .js-form-submit, .ui-dialog-buttonpane .form-actions input[type=\"submit\"]');
        for (var i = 0; i < buttons.length; i++) {
          var label = (buttons[i].value || buttons[i].textContent || '').trim().toLowerCase();
          if (label === 'save') {
            buttons[i].click();
            return;
          }
        }
        var fallback = form.querySelector('input[type=\"submit\"], button[type=\"submit\"]');
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
   * Programmatically inserts a message box at the document end.
   */
  protected function insertMessageBoxAtEnd(string $textareaSelector, string $title, string $type, string $bodyHtml): void {
    $this->inlineMessageSession()->executeScript(
      "(function(){
        var textarea = document.querySelector(" . json_encode($textareaSelector) . ");
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        editor.model.change(function(writer) {
          writer.setSelection(writer.createPositionAt(editor.model.document.getRoot(), 'end'));
        });
        editor.commands.get('insertMassInlineMessage')._insert({
          attributes: {'data-title': " . json_encode($title) . ", 'data-type': " . json_encode($type) . "},
          body: " . json_encode($bodyHtml) . ",
        });
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
