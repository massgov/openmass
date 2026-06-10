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
   * Entity embed can open on top of Message box inside Layout Paragraphs Rich text.
   */
  public function testEntityEmbedOpensInsideMessageBoxInLayoutParagraph(): void {
    $admin = $this->createAdministrator();
    $this->drupalLogin($admin);
    $service_page = $this->createNode([
      'type' => 'service_page',
      'title' => 'Message box LP embed test ' . $this->randomMachineName(8),
      'uid' => $admin->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->visit($service_page->toUrl()->toString() . '/edit');
    $this->openServiceRichTextEditorInLayoutParagraph();
    $session = $this->inlineMessageSession();

    $this->fireMessageBoxToolbarInLayoutParagraph();
    $this->waitForMessageBoxDialogOpen();
    $session->getPage()->fillField('attributes[data-title]', 'LP embed test');
    $this->setMessageBoxDialogBodyHtml('<p>Text before image.</p>');
    $session->wait(
      10000,
      "document.querySelector('#mass-inline-message-dialog-form textarea[name=\"body[value]\"][data-ckeditor5-id]') !== null",
    );

    $this->clickMessageBoxBodyEmbedToolbarButton();
    $session->wait(
      20000,
      "(function(){
        return document.querySelector('form.entity-embed-dialog') !== null
          || document.querySelector('input[name^=\"entity_browser_select[\"]') !== null;
      })()",
    );

    $has_embed_dialog = (bool) $session->evaluateScript(
      "document.querySelector('form.entity-embed-dialog') !== null
        || document.querySelector('input[name^=\"entity_browser_select[\"]') !== null",
    );
    $this->assertTrue($has_embed_dialog, 'Entity embed dialog should open above the Message box in LP.');

    $lp_editor_data = $this->getLayoutParagraphRichTextEditorData();
    $this->assertStringNotContainsString('<mass-inline-message', $lp_editor_data);

    $this->assertNotNull(
      $session->getPage()->find('css', '#mass-inline-message-dialog-form'),
      'Message box dialog should remain open while entity embed is active.',
    );
  }

  /**
   * Text and image body survives Message box save inside Layout Paragraphs.
   */
  public function testMessageBoxWithImageBodyInLayoutParagraph(): void {
    $admin = $this->createAdministrator();
    $this->drupalLogin($admin);
    $title = 'LP image body ' . $this->randomMachineName(6);
    $service_page = $this->createNode([
      'type' => 'service_page',
      'title' => 'Message box LP image test ' . $this->randomMachineName(8),
      'uid' => $admin->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->visit($service_page->toUrl()->toString() . '/edit');
    $this->openServiceRichTextEditorInLayoutParagraph();

    $this->fireMessageBoxToolbarInLayoutParagraph();
    $this->waitForMessageBoxDialogOpen();

    $this->inlineMessageSession()->getPage()->fillField('attributes[data-title]', $title);
    $this->setMessageBoxDialogBodyHtml(
      '<p>Intro text.</p><img src="/sites/default/files/chart.jpg" alt="Chart" width="200" height="100">',
    );
    $this->triggerEntityEmbedEditorDialogSave();
    $this->clickMessageBoxDialogSave();
    $this->waitForMessageBoxDialogClosed();

    $editor_data = $this->getLayoutParagraphRichTextEditorData();
    $this->assertStringContainsString('data-title="' . $title . '"', $editor_data);
    $this->assertStringContainsString('Intro text', $editor_data);
    $this->assertMatchesRegularExpression('/<img\b/i', $editor_data);
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
