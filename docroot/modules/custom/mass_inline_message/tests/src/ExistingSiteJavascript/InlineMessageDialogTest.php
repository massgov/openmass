<?php

namespace Drupal\Tests\mass_inline_message\ExistingSiteJavascript;

/**
 * Verifies Message box dialog opens from CKEditor toolbar.
 */
class InlineMessageDialogTest extends MassInlineMessageJavascriptTestBase {

  /**
   * Tests the dialog form route renders expected fields.
   */
  public function testMessageBoxDialogFormRoute(): void {
    $this->drupalLogin($this->createAdministrator());
    $this->drupalGet('/mass-inline-message/dialog/basic_html');
    $session = $this->inlineMessageSession();
    $session->wait(5000, "document.querySelector('input[name=\"attributes[data-title]\"]') !== null");
    $session->wait(
      15000,
      "document.querySelector('textarea[name=\"body[value]\"][data-ckeditor5-id]') !== null",
    );
    $title_count = $session->evaluateScript(
      "document.querySelectorAll('input[name=\"attributes[data-title]\"]').length",
    );
    $warning_count = $session->evaluateScript(
      "document.querySelectorAll('input[name=\"attributes[data-type]\"][value=\"warning\"]').length",
    );
    $cancel_count = $session->evaluateScript(
      "document.querySelectorAll('form.mass-inline-message-dialog-form .dialog-cancel, .ui-dialog-buttonpane .dialog-cancel').length",
    );
    $body_editor_count = $session->evaluateScript(
      "document.querySelectorAll('textarea[name=\"body[value]\"][data-ckeditor5-id]').length",
    );
    $this->assertSame(1, (int) $title_count, 'Message title field should appear on dialog route.');
    $this->assertSame(1, (int) $body_editor_count, 'Message text should use the parent text format editor.');
    $this->assertGreaterThanOrEqual(1, (int) $warning_count, 'Alert message type option should appear on dialog route.');
    $this->assertGreaterThanOrEqual(1, (int) $cancel_count, 'Cancel button should appear on dialog route.');
  }

  /**
   * Tests the Message box toolbar button opens the configuration dialog.
   */
  public function testMessageBoxDialogOpens(): void {
    $this->drupalLogin($this->createAdministrator());
    $node_id = $this->createBasicPageWithBody();
    $this->drupalGet('node/' . $node_id . '/edit');
    $session = $this->inlineMessageSession();

    $session->wait(10000, "(function(){
      var buttons = document.querySelectorAll('.ck-toolbar .ck-button');
      for (var i = 0; i < buttons.length; i++) {
        var label = (buttons[i].innerText || buttons[i].getAttribute('aria-label') || '').toLowerCase();
        if (label.indexOf('message box') !== -1) { return true; }
      }
      return false;
    })()");

    $this->waitForBodyFieldEditor();
    $this->fireMessageBoxToolbarButton(self::BODY_FIELD_EDITOR_SELECTOR);
    $this->waitForMessageBoxDialogOpen();

    $page = $session->getPage();
    $title_field = $page->find('css', '#mass-inline-message-dialog-form input[name="attributes[data-title]"]')
      ?: $page->find('css', '.ui-dialog input[name="attributes[data-title]"]');
    $this->assertNotNull($title_field, 'Message title field should appear in dialog.');
    $warning_radio = $page->find('css', '#mass-inline-message-dialog-form input[name="attributes[data-type]"][value="warning"]')
      ?: $page->find('css', '.ui-dialog input[name="attributes[data-type]"][value="warning"]');
    $this->assertNotNull($warning_radio, 'Alert message type option should appear in dialog.');
    $cancel_button = $page->find('css', '.ui-dialog-buttonpane .dialog-cancel')
      ?: $page->find('css', 'form.mass-inline-message-dialog-form .dialog-cancel');
    $this->assertNotNull($cancel_button, 'Cancel button should appear in dialog.');
  }

}
