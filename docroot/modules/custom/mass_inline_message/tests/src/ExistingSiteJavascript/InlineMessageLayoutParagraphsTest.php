<?php

namespace Drupal\Tests\mass_inline_message\ExistingSiteJavascript;

use Drupal\Tests\mass_inline_message\Traits\InlineMessageLayoutParagraphsTestTrait;

/**
 * Minimal Layout Paragraphs browser smoke tests for Message box.
 */
class InlineMessageLayoutParagraphsTest extends MassInlineMessageJavascriptTestBase {

  use InlineMessageLayoutParagraphsTestTrait;

  /**
   * Inserts a Message box in LP Rich text and verifies editor markup.
   */
  public function testMessageBoxInsertInLayoutParagraphRichText(): void {
    $admin = $this->createAdministrator();
    $title = 'LP section alert ' . $this->randomMachineName(6);
    $service_page = $this->createEmptyServicePage([
      'uid' => $admin->id(),
      'title' => 'Message box LP insert test ' . $this->randomMachineName(8),
    ]);

    $this->drupalLogin($admin);
    $this->drupalGet($service_page->toUrl()->toString() . '/edit');
    $this->openServiceRichTextEditorInLayoutParagraph();

    $this->fireMessageBoxToolbarInLayoutParagraph();
    $this->waitForMessageBoxDialogOpen();
    $this->fillMessageBoxDialogTitle($title);
    $this->clickMessageBoxDialogSave();
    $this->waitForMessageBoxDialogClosed();
    $this->assertMessageBoxSaveDidNotRedirectToDialogRoute();

    $editor_data = $this->getLayoutParagraphRichTextEditorData();
    $this->assertStringContainsString('data-title="' . $title . '"', $editor_data);
    $this->assertStringContainsString('<mass-inline-message', $editor_data);
  }

  /**
   * Entity embed can open on top of Message box inside Layout Paragraphs Rich text.
   */
  public function testEntityEmbedOpensInsideMessageBoxInLayoutParagraph(): void {
    $admin = $this->createAdministrator();
    $this->drupalLogin($admin);
    $service_page = $this->createEmptyServicePage([
      'uid' => $admin->id(),
      'title' => 'Message box LP embed test ' . $this->randomMachineName(8),
    ]);

    $this->drupalGet($service_page->toUrl()->toString() . '/edit');
    $this->openServiceRichTextEditorInLayoutParagraph();

    $this->fireMessageBoxToolbarInLayoutParagraph();
    $this->waitForMessageBoxDialogOpen();
    $this->fillMessageBoxDialogTitle('LP embed test');
    $this->setMessageBoxDialogBodyHtml('<p>Text before image.</p>');
    $this->waitForMessageBoxBodyEditor();

    $this->clickMessageBoxBodyEmbedToolbarButton();
    $this->waitForEntityEmbedDialogOpen();

    $session = $this->inlineMessageSession();
    $has_embed_dialog = (bool) $session->evaluateScript(
      "document.querySelector('form.entity-embed-dialog') !== null
        || document.querySelector('input[name^=\"entity_browser_select[\"]') !== null",
    );
    $this->assertTrue($has_embed_dialog, 'Entity embed dialog should open above the Message box in LP.');

    $this->assertStringNotContainsString('<mass-inline-message', $this->getLayoutParagraphRichTextEditorData());
    $this->assertNotNull(
      $session->getPage()->find('css', '#mass-inline-message-dialog-form'),
      'Message box dialog should remain open while entity embed is active.',
    );
  }

}
