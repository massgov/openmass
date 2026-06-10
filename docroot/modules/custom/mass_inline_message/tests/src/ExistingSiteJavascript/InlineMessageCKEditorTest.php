<?php

namespace Drupal\Tests\mass_inline_message\ExistingSiteJavascript;

/**
 * Message box in a node body field (insert, save, edit).
 */
class InlineMessageCKEditorTest extends MassInlineMessageJavascriptTestBase {

  /**
   * Inserts via toolbar dialog, saves the node, and checks front-end output.
   */
  public function testInsertMessageBoxViaToolbar(): void {
    $this->drupalLogin($this->createAdministrator());
    $node_id = $this->createBasicPageWithBody();
    $this->drupalGet('node/' . $node_id . '/edit');
    $session = $this->inlineMessageSession();

    $session->wait(10000, "document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]') !== null");
    $session->executeScript(
      "(function(){
        var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
        var editor = Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id'));
        editor.model.change(function(writer) {
          writer.setSelection(writer.createPositionAt(editor.model.document.getRoot(), 'end'));
        });
        editor.editing.view.focus();
      })();",
    );

    $this->fireMessageBoxToolbarButton(self::BODY_FIELD_EDITOR_SELECTOR);
    $this->waitForMessageBoxDialogOpen();

    $page = $session->getPage();
    $page->fillField('attributes[data-title]', 'Test alert title');
    $warning_radio = $page->find('css', '#mass-inline-message-dialog-form input[name="attributes[data-type]"][value="warning"]')
      ?: $page->find('css', '.ui-dialog input[name="attributes[data-type]"][value="warning"]');
    $this->assertNotNull($warning_radio);
    $warning_radio->click();

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
      })();",
    );

    $this->clickMessageBoxDialogSave();
    $this->waitForMessageBoxDialogClosed();
    $this->assertMessageBoxSaveDidNotRedirectToDialogRoute();

    $session->wait(15000, "(function(){
      var textarea = document.querySelector('[name=\"body[0][value]\"][data-ckeditor5-id]');
      return textarea && Drupal.CKEditor5Instances.get(textarea.getAttribute('data-ckeditor5-id')).getData().indexOf('data-title=\"Test alert title\"') !== -1;
    })()");

    $editor_data = $this->getCkeditorData(self::BODY_FIELD_EDITOR_SELECTOR);
    $this->assertStringContainsString('data-title="Test alert title"', $editor_data);
    $this->assertStringContainsString('data-type="warning"', $editor_data);

    $page->pressButton('Save');
    $session->wait(5000);

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node_id);
    $body = $node->get('body')->value;
    $this->assertStringContainsString('<mass-inline-message', $body);

    $this->drupalGet('node/' . $node_id);
    $content = $session->getPage()->getContent();
    $this->assertStringContainsString('ma__inline-message--warning', $content);
    $this->assertStringContainsString('Test alert title', $content);
  }

  /**
   * Edits an existing widget via the Edit toolbar control.
   */

  /**
   * Entity embed save inside the dialog must not insert an empty Message box.
   */
  public function testEntityEmbedDialogSaveDoesNotInsertEmptyMessageBox(): void {
    $this->drupalLogin($this->createAdministrator());
    $node_id = $this->createBasicPageWithBody();
    $this->drupalGet('node/' . $node_id . '/edit');
    $this->waitForBodyFieldEditor();

    $this->fireMessageBoxToolbarButton(self::BODY_FIELD_EDITOR_SELECTOR);
    $this->waitForMessageBoxDialogOpen();

    $session = $this->inlineMessageSession();
    $session->getPage()->fillField('attributes[data-title]', 'Chart message');
    $this->setMessageBoxDialogBodyHtml('<p>Intro text before image.</p><img src="/sites/default/files/chart.jpg" alt="Chart">');

    $this->triggerEntityEmbedEditorDialogSave();

    $editor_data = $this->getCkeditorData(self::BODY_FIELD_EDITOR_SELECTOR);
    $this->assertStringNotContainsString('<mass-inline-message', $editor_data);

    $this->clickMessageBoxDialogSave();
    $this->waitForMessageBoxDialogClosed();

    $editor_data = $this->getCkeditorData(self::BODY_FIELD_EDITOR_SELECTOR);
    $this->assertStringContainsString('data-title="Chart message"', $editor_data);
    $this->assertStringContainsString('Intro text before image', $editor_data);
    $this->assertMatchesRegularExpression('/<img\b/i', $editor_data);
  }

  /**
   * Saves a Message box whose body contains only image markup (no plain text).
   */
  public function testSaveMessageBoxWithImageBodyViaDialog(): void {
    $this->drupalLogin($this->createAdministrator());
    $node_id = $this->createBasicPageWithBody();
    $this->drupalGet('node/' . $node_id . '/edit');
    $this->waitForBodyFieldEditor();

    $image_body = '<p>Energy chart</p><img src="/sites/default/files/chart.jpg" alt="Energy chart" width="200" height="100">';

    $this->fireMessageBoxToolbarButton(self::BODY_FIELD_EDITOR_SELECTOR);
    $this->waitForMessageBoxDialogOpen();

    $session = $this->inlineMessageSession();
    $session->getPage()->fillField('attributes[data-title]', 'Chart message');
    $this->setMessageBoxDialogBodyHtml($image_body);
    $this->clickMessageBoxDialogSave();
    $this->waitForMessageBoxDialogClosed();

    $editor_data = $this->getCkeditorData(self::BODY_FIELD_EDITOR_SELECTOR);
    $this->assertStringContainsString('data-title="Chart message"', $editor_data);
    $this->assertStringContainsString('<mass-inline-message', $editor_data);
    $this->assertStringContainsString('Energy chart', $editor_data);
    $this->assertMatchesRegularExpression('/<img\b/i', $editor_data);

    $session->getPage()->pressButton('Save');
    $session->wait(5000);

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node_id);
    $stored = $node->get('body')->value;
    $this->assertStringContainsString('Chart message', $stored);
    $this->assertMatchesRegularExpression('/<img\b/i', $stored);
  }

  public function testEditMessageBoxViaWidgetToolbar(): void {
    $this->drupalLogin($this->createAdministrator());
    $node_id = $this->createBasicPageWithBody();
    $this->drupalGet('node/' . $node_id . '/edit');
    $this->waitForBodyFieldEditor();

    $this->insertMessageBoxAtEnd(
      self::BODY_FIELD_EDITOR_SELECTOR,
      'Original title',
      'info',
      '<p>Original body.</p>',
    );

    $session = $this->inlineMessageSession();
    $session->wait(10000, "document.querySelector('.ck-content .ma__inline-message') !== null");

    $this->assertTrue($this->selectFirstMessageBoxWidget(self::BODY_FIELD_EDITOR_SELECTOR));
    $session->wait(5000, "(function(){
      var buttons = document.querySelectorAll('.ck-body-wrapper .ck-toolbar .ck-button');
      for (var i = 0; i < buttons.length; i++) {
        var tip = (buttons[i].getAttribute('data-cke-tooltip-text') || buttons[i].getAttribute('aria-label') || '').toLowerCase();
        if (tip === 'edit') { return true; }
      }
      return false;
    })()");

    $this->clickMessageBoxWidgetEditButton();
    $this->waitForMessageBoxDialogOpen();

    $session->getPage()->fillField('attributes[data-title]', 'Updated title');
    $this->clickMessageBoxDialogSave();
    $this->waitForMessageBoxDialogClosed();

    $editor_data = $this->getCkeditorData(self::BODY_FIELD_EDITOR_SELECTOR);
    $this->assertStringContainsString('data-title="Updated title"', $editor_data);
    $this->assertStringContainsString('Original body', $editor_data);
  }

}
