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
   * Tests dialog button-pane Save inserts a Message box in top-level Rich text.
   *
   * Mirrors service pages like About DOER where Rich text is not nested in a
   * Service Section.
   */
  public function testMessageBoxInsertViaDialogButtonPaneInTopLevelRichText(): void {
    $user = $this->createContentEditor();
    $title = 'Top-level LP alert ' . $this->randomMachineName(6);
    $rich_text = $this->container->get('entity_type.manager')
      ->getStorage('paragraph')
      ->create([
        'type' => 'service_rich_text',
        'field_section_body' => [
          'value' => '<p>Initial rich text.</p>',
          'format' => 'basic_html',
        ],
      ]);
    $rich_text->save();
    $service_page = $this->createNode([
      'type' => 'service_page',
      'title' => 'Message box top-level LP ' . $this->randomMachineName(8),
      'uid' => $user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
      'field_service_sections' => [
        [
          'target_id' => $rich_text->id(),
          'target_revision_id' => $rich_text->getRevisionId(),
        ],
      ],
    ]);

    $this->drupalLogin($user);
    $this->visit($service_page->toUrl()->toString() . '/edit');
    $this->openTopLevelServiceRichTextEditorInLayoutParagraph();
    $page = $this->inlineMessageSession()->getPage();

    $this->fireMessageBoxToolbarInLayoutParagraph();
    $this->waitForMessageBoxDialogOpen();

    $page->fillField('attributes[data-title]', $title);
    $this->clickMessageBoxDialogSave();
    $this->waitForMessageBoxDialogClosed();
    $this->assertMessageBoxSaveDidNotRedirectToDialogRoute();

    $editor_data = $this->getLayoutParagraphRichTextEditorData();
    $this->assertStringContainsString('data-title="' . $title . '"', $editor_data);
    $this->assertStringContainsString('<mass-inline-message', $editor_data);

    $this->clickLayoutParagraphDialogSave();
    $this->inlineMessageSession()->wait(
      10000,
      "document.querySelector('.ui-dialog .ui-dialog-title') === null || document.querySelector('.ui-dialog .ui-dialog-title').textContent.toLowerCase().indexOf('rich text') === -1",
    );
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

    $this->assertTrue($session->evaluateScript('window.__massInlineMessageLpToolbarTest.selectMessageBoxWidget()'));
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
