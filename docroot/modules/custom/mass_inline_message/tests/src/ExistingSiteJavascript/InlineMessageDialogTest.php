<?php

namespace Drupal\Tests\mass_inline_message\ExistingSiteJavascript;

use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Verifies Message box dialog opens from CKEditor toolbar.
 */
class InlineMessageDialogTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Tests the dialog form route renders expected fields.
   */
  public function testMessageBoxDialogFormRoute(): void {
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);
    $this->drupalGet('/mass-inline-message/dialog/basic_html');
    $session = $this->getSession();
    $session->wait(5000, "document.querySelector('input[name=\"attributes[data-title]\"]') !== null");
    $title_count = $session->evaluateScript(
      "document.querySelectorAll('input[name=\"attributes[data-title]\"]').length"
    );
    $warning_count = $session->evaluateScript(
      "document.querySelectorAll('input[name=\"attributes[data-type]\"][value=\"warning\"]').length"
    );
    $cancel_count = $session->evaluateScript(
      "document.querySelectorAll('form.mass-inline-message-dialog .dialog-cancel, .ui-dialog-buttonpane .dialog-cancel').length"
    );
    $this->assertSame(1, (int) $title_count, 'Message title field should appear on dialog route.');
    $this->assertGreaterThanOrEqual(1, (int) $warning_count, 'Alert message type option should appear on dialog route.');
    $this->assertGreaterThanOrEqual(1, (int) $cancel_count, 'Cancel button should appear on dialog route.');
  }

  /**
   * Tests the Message box toolbar button opens the configuration dialog.
   */
  public function testMessageBoxDialogOpens(): void {
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate();
    $admin->save();
    $this->drupalLogin($admin);

    $node = $this->createNode([
      'type' => 'page',
      'title' => 'Message box dialog test ' . $this->randomMachineName(8),
      'body' => [
        'value' => '<p>Initial body.</p>',
        'format' => 'basic_html',
      ],
      'status' => 1,
    ]);
    $node->save();

    $this->drupalGet('node/' . $node->id() . '/edit');
    $session = $this->getSession();

    $session->wait(10000, "(function(){
      var buttons = document.querySelectorAll('.ck-toolbar .ck-button');
      for (var i = 0; i < buttons.length; i++) {
        var label = (buttons[i].innerText || buttons[i].getAttribute('aria-label') || '').toLowerCase();
        if (label.indexOf('message box') !== -1) { return true; }
      }
      return false;
    })()");

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

    $session->wait(20000, "(function(){
      return document.querySelector('#mass-inline-message-dialog-form input[name=\"attributes[data-title]\"]')
        || document.querySelector('.ui-dialog input[name=\"attributes[data-title]\"]');
    })()");
    $page = $session->getPage();
    $title_field = $page->find('css', '#mass-inline-message-dialog-form input[name="attributes[data-title]"]')
      ?: $page->find('css', '.ui-dialog input[name="attributes[data-title]"]');
    $this->assertNotNull($title_field, 'Message title field should appear in dialog.');
    $warning_radio = $page->find('css', '#mass-inline-message-dialog-form input[name="attributes[data-type]"][value="warning"]')
      ?: $page->find('css', '.ui-dialog input[name="attributes[data-type]"][value="warning"]');
    $this->assertNotNull($warning_radio, 'Alert message type option should appear in dialog.');
    $cancel_button = $page->find('css', '.ui-dialog-buttonpane .dialog-cancel')
      ?: $page->find('css', 'form.mass-inline-message-dialog .dialog-cancel');
    $this->assertNotNull($cancel_button, 'Cancel button should appear in dialog.');
  }

}
