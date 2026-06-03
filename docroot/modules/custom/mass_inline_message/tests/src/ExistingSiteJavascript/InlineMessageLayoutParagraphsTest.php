<?php

namespace Drupal\Tests\mass_inline_message\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\Tests\mass_inline_message\Traits\InlineMessageLayoutParagraphsTestTrait;

/**
 * Verifies Message box works in Layout Paragraphs Rich text on service pages.
 */
class InlineMessageLayoutParagraphsTest extends MassInlineMessageJavascriptTestBase {

  use InlineMessageLayoutParagraphsTestTrait;

  /**
   * Tests Message box dialog opens from Rich text inside a Service Section LP modal.
   */
  public function testMessageBoxDialogOpensInLayoutParagraphRichText(): void {
    $user = $this->createContentEditor();
    $service_page = $this->createNode([
      'type' => 'service_page',
      'title' => 'Message box LP dialog test ' . $this->randomMachineName(8),
      'uid' => $user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalLogin($user);
    $this->visit($service_page->toUrl()->toString() . '/edit');
    $this->openServiceRichTextEditorInLayoutParagraph();
    $session = $this->inlineMessageSession();

    $this->fireMessageBoxToolbarInLayoutParagraph();

    $session->wait(
      20000,
      "(function(){
        var dialogs = document.querySelectorAll('.ui-dialog');
        for (var i = 0; i < dialogs.length; i++) {
          if (dialogs[i].querySelector('#mass-inline-message-dialog-form input[name=\"attributes[data-title]\"]')) {
            return true;
          }
        }
        return false;
      })()",
    );

    $title_field = $session->getPage()->find('css', '#mass-inline-message-dialog-form input[name="attributes[data-title]"]')
      ?: $session->getPage()->find('css', '.ui-dialog input[name="attributes[data-title]"]');
    $this->assertNotNull($title_field, 'Message title field should appear in nested Message box dialog.');

    $dialog_count = (int) $session->evaluateScript(
      'document.querySelectorAll(\'.ui-dialog\').length',
    );
    $this->assertGreaterThanOrEqual(2, $dialog_count, 'Message box dialog should open on top of the Layout Paragraphs modal.');
  }

  /**
   * Tests inserting a Message box via dialog save stays in the LP Ajax flow.
   */
  public function testMessageBoxInsertAndSaveInLayoutParagraphRichText(): void {
    $user = $this->createContentEditor();
    $title = 'LP section alert ' . $this->randomMachineName(6);
    $service_page = $this->createNode([
      'type' => 'service_page',
      'title' => 'Message box LP insert test ' . $this->randomMachineName(8),
      'uid' => $user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalLogin($user);
    $this->visit($service_page->toUrl()->toString() . '/edit');
    $this->openServiceRichTextEditorInLayoutParagraph();
    $session = $this->inlineMessageSession();
    $page = $session->getPage();

    $this->fireMessageBoxToolbarInLayoutParagraph();
    $this->waitForMessageBoxDialogOpen();

    $page->fillField('attributes[data-title]', $title);
    $warning_radio = $page->find('css', '#mass-inline-message-dialog-form input[name="attributes[data-type]"][value="warning"]')
      ?: $page->find('css', '.ui-dialog input[name="attributes[data-type]"][value="warning"]');
    $this->assertNotNull($warning_radio);
    $warning_radio->click();

    $this->clickMessageBoxDialogSave();
    $this->waitForMessageBoxDialogClosed();
    $this->assertMessageBoxSaveDidNotRedirectToDialogRoute();
    $this->assertStringContainsString('/edit', $session->getCurrentUrl());

    $session->wait(5000, "document.querySelector('.ui-dialog .ck-editor') !== null");
    $rich_text_title = $session->evaluateScript(
      "document.querySelector('.ui-dialog .ui-dialog-title') ? document.querySelector('.ui-dialog .ui-dialog-title').textContent : ''",
    );
    $this->assertStringContainsStringIgnoringCase('rich text', (string) $rich_text_title);

    $editor_data = $this->getLayoutParagraphRichTextEditorData();
    $this->assertStringContainsString('data-title="' . $title . '"', $editor_data);
    $this->assertStringContainsString('data-type="warning"', $editor_data);
    $this->assertStringContainsString('<mass-inline-message', $editor_data);
  }

  /**
   * Widget toolbar stays above the Message box after a second edit in LP Rich text.
   */
  public function testMessageBoxWidgetToolbarPlacementAfterSecondEditInLayoutParagraph(): void {
    $user = $this->createContentEditor();
    $title = 'LP toolbar placement ' . $this->randomMachineName(6);
    $service_page = $this->createNode([
      'type' => 'service_page',
      'title' => 'Message box LP toolbar test ' . $this->randomMachineName(8),
      'uid' => $user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalLogin($user);
    $this->visit($service_page->toUrl()->toString() . '/edit');
    $this->openServiceRichTextEditorInLayoutParagraph();
    $session = $this->inlineMessageSession();
    $page = $session->getPage();

    $this->insertMessageBoxAtEnd(
      self::LP_RICH_TEXT_EDITOR_SELECTOR,
      $title,
      'info',
      '<p>LP toolbar test body.</p>',
    );

    $session->wait(10000, "document.querySelector('.ui-dialog .ck-content .mass-inline-message-ckeditor-widget') !== null");
    $this->attachLayoutParagraphToolbarTestHelpers();

    $this->assertTrue(
      (bool) $session->evaluateScript('window.__massInlineMessageLpToolbarTest.selectMessageBoxWidget()'),
      'Message box widget should be selectable in LP Rich text.',
    );

    $session->wait(10000, "(function(){
      var t = window.__massInlineMessageLpToolbarTest;
      if (!t || !t.selectMessageBoxWidget()) { return false; }
      return t.clickWidgetEditButton();
    })()");

    $this->waitForMessageBoxDialogOpen();

    $updated_title = $title . ' updated';
    $page->fillField('attributes[data-title]', $updated_title);
    $this->clickMessageBoxDialogSave();

    $session->wait(
      20000,
      "(function(){
        return document.querySelector('#mass-inline-message-dialog-form') === null
          && document.querySelector('.ui-dialog .ck-editor') !== null;
      })()",
    );

    $this->assertTrue(
      (bool) $session->evaluateScript('window.__massInlineMessageLpToolbarTest.selectMessageBoxWidget()'),
      'Message box widget should be selectable again after the first edit.',
    );

    $session->wait(10000, "(function(){
      var t = window.__massInlineMessageLpToolbarTest;
      if (!t || !t.selectMessageBoxWidget()) { return false; }
      return t.clickWidgetEditButton();
    })()");

    $this->waitForMessageBoxDialogOpen();

    $second_alignment = $session->evaluateScript('window.__massInlineMessageLpToolbarTest.getWidgetToolbarAlignment()');
    if (!empty($second_alignment['hasBalloon'])) {
      $this->assertTrue(
        (bool) ($second_alignment['ok'] ?? FALSE),
        'Widget toolbar should align above the Message box on second selection: ' . json_encode($second_alignment),
      );
    }

    $editor_data = $this->getLayoutParagraphRichTextEditorData();
    $this->assertStringContainsString('data-title="' . $updated_title . '"', $editor_data);
  }

}
