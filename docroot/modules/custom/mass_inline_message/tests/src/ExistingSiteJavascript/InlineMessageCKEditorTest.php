<?php

namespace Drupal\Tests\mass_inline_message\ExistingSiteJavascript;

/**
 * Minimal browser smoke tests for Message box in a node body field.
 *
 * Rendering, normalization, and validation are covered by ExistingSite tests.
 */
class InlineMessageCKEditorTest extends MassInlineMessageJavascriptTestBase {

  /**
   * Inserts a Message box via the toolbar dialog and verifies editor markup.
   */
  public function testInsertMessageBoxViaToolbar(): void {
    $this->drupalLogin($this->createAdministrator());
    $node_id = $this->createBasicPageWithBody();
    $this->drupalGet('node/' . $node_id . '/edit');
    $session = $this->inlineMessageSession();

    $this->waitForBodyFieldEditor();
    $this->fireMessageBoxToolbarButton(self::BODY_FIELD_EDITOR_SELECTOR);
    $this->waitForMessageBoxDialogOpen();

    $page = $session->getPage();
    $this->fillMessageBoxDialogTitle('Test alert title');
    $warning_radio = $page->find('css', '#mass-inline-message-dialog-form input[name="attributes[data-type]"][value="warning"]')
      ?: $page->find('css', '.ui-dialog input[name="attributes[data-type]"][value="warning"]');
    $this->assertNotNull($warning_radio);
    $warning_radio->click();
    $this->setMessageBoxDialogBodyHtml('<p>Test alert body text.</p>');

    $this->clickMessageBoxDialogSave();
    $this->waitForMessageBoxDialogClosed();
    $this->assertMessageBoxSaveDidNotRedirectToDialogRoute();

    $editor_data = $this->getCkeditorData(self::BODY_FIELD_EDITOR_SELECTOR);
    $this->assertStringContainsString('data-title="Test alert title"', $editor_data);
    $this->assertStringContainsString('data-type="warning"', $editor_data);
    $this->assertStringContainsString('<mass-inline-message', $editor_data);
    $this->assertStringContainsString('Test alert body text', $editor_data);
  }

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

    $this->fillMessageBoxDialogTitle('Chart message');
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

}
