<?php

namespace Drupal\Tests\mass_inline_message\Traits;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;

/**
 * Selenium helpers for Message box inside Layout Paragraphs Rich text modals.
 */
trait InlineMessageLayoutParagraphsTestTrait {

  /**
   * Textarea selector for Rich text inside an open LP dialog.
   */
  protected const LP_RICH_TEXT_EDITOR_SELECTOR = '.ui-dialog textarea[data-ckeditor5-id]';

  /**
   * Creates an empty service page for Layout Paragraphs UI setup.
   */
  protected function createEmptyServicePage(array $overrides = []): NodeInterface {
    $defaults = [
      'type' => 'service_page',
      'title' => 'Message box LP ' . $this->randomMachineName(8),
      'moderation_state' => MassModeration::PUBLISHED,
    ];

    return $this->createNode(array_merge($defaults, $overrides));
  }

  /**
   * Opens the Content tab on a service page edit form.
   */
  protected function openLayoutParagraphsContentTab(): void {
    $page = $this->inlineMessageSession()->getPage();
    $session = $this->inlineMessageSession();

    $session->executeScript(
      "document.querySelector('a[href^=\"#edit-group-content\"]').scrollIntoView({block: 'center'});",
    );
    $page->find('css', '.horizontal-tab-button a[href="#edit-group-content"]')->click();
    $session->wait(
      self::JS_WAIT_DEFAULT,
      "document.querySelector('[data-drupal-selector^=\"edit-field-service-sections\"]') !== null",
    );
  }

  /**
   * Waits until the LP Rich text modal and CKEditor are ready.
   */
  protected function waitForLayoutParagraphRichTextEditor(): void {
    $session = $this->inlineMessageSession();
    $ready = $session->wait(
      self::JS_WAIT_LONG,
      "(function(){
        var title = document.querySelector('.ui-dialog .ui-dialog-title');
        if (!title || title.textContent.toLowerCase().indexOf('rich text') === -1) {
          return false;
        }
        return document.querySelector('.ui-dialog .ck-editor [contenteditable=true]') !== null;
      })()",
    );
    $this->assertNotEmpty($ready, 'Layout Paragraphs Rich text editor did not open.');
  }

  /**
   * Adds Service Section + Rich text via the LP builder, then opens Rich text.
   */
  protected function openServiceRichTextEditorInLayoutParagraph(): void {
    $page = $this->inlineMessageSession()->getPage();
    $session = $this->inlineMessageSession();

    $this->openLayoutParagraphsContentTab();

    $addSection = $page->find(
      'css',
      '[data-drupal-selector="edit-field-service-sections-layout-paragraphs-builder"] a.lpb-btn.use-ajax.center.js-lpb-ui[href*="/choose-component"]',
    );
    $this->assertNotNull($addSection, 'Layout Paragraphs add section button not found.');
    $addSection->click();

    $session->wait(self::JS_WAIT_DEFAULT, "document.querySelector('.ui-dialog.lpb-dialog') !== null");
    $session->executeScript(
      "(function(){
        var el = document.querySelector('.ui-dialog .ui-dialog-content a.use-ajax[href*=\"/insert/service_section\"]');
        if (el) {
          try { el.scrollIntoView({block: 'center'}); } catch (e) {}
          el.click();
        }
      })();",
    );

    $session->wait(
      self::JS_WAIT_DEFAULT,
      "document.querySelector('.ui-dialog .ui-dialog-title') && document.querySelector('.ui-dialog .ui-dialog-title').textContent.indexOf('Create new Service Section') !== -1",
    );
    $this->clickLayoutParagraphDialogSave();
    $session->wait(self::JS_WAIT_DEFAULT, "document.querySelector('.ui-dialog.lpb-dialog') === null");

    $session->wait(
      self::JS_WAIT_DEFAULT,
      "document.querySelector('.layout.layout--onecol-mass-service-section .js-lpb-region.layout__region--content a.lpb-btn--add.use-ajax[href*=\"choose-component?parent_uuid\"]') !== null",
    );
    $session->executeScript(
      "(function(){
        var el = document.querySelector('.layout.layout--onecol-mass-service-section .js-lpb-region.layout__region--content a.lpb-btn--add.use-ajax[href*=\"choose-component?parent_uuid\"]');
        if (el) {
          try { el.scrollIntoView({block: 'center'}); } catch (e) {}
          el.click();
        }
      })();",
    );

    $session->wait(
      self::JS_WAIT_DEFAULT,
      "document.querySelector('.ui-dialog .lpb-component-list__item.type-service_rich_text a.use-ajax') !== null",
    );
    $session->executeScript(
      "(function(){
        var el = document.querySelector('.ui-dialog .lpb-component-list__item.type-service_rich_text a.use-ajax');
        if (el) {
          try { el.scrollIntoView({block: 'center'}); } catch (e) {}
          el.click();
        }
      })();",
    );

    $this->waitForLayoutParagraphRichTextEditor();
  }

  /**
   * Clicks the LPB Save button in the active Layout Paragraphs dialog.
   */
  protected function clickLayoutParagraphDialogSave(): void {
    $this->inlineMessageSession()->executeScript(
      "(function(){
        var el = document.querySelector('.ui-dialog .ui-dialog-buttonpane button.lpb-btn--save');
        if (el) {
          try { el.scrollIntoView({block: 'center'}); } catch (e) {}
          el.click();
        }
      })();",
    );
  }

  /**
   * Fires the Message box toolbar button in the LP Rich text CKEditor.
   */
  protected function fireMessageBoxToolbarInLayoutParagraph(): void {
    $this->fireMessageBoxToolbarButton(self::LP_RICH_TEXT_EDITOR_SELECTOR);
  }

  /**
   * Returns CKEditor data from the Rich text field inside the LP modal.
   */
  protected function getLayoutParagraphRichTextEditorData(): string {
    return $this->getCkeditorData(self::LP_RICH_TEXT_EDITOR_SELECTOR);
  }

  /**
   * Opens the first top-level Rich text paragraph on a service page edit form.
   */
  protected function openTopLevelServiceRichTextEditorInLayoutParagraph(): void {
    $this->openLayoutParagraphsContentTab();
    $this->inlineMessageSession()->executeScript(
      "(function(){
        var edit = document.querySelector('.lpb-component.type-service_rich_text a.lpb-edit.use-ajax')
          || document.querySelector('a.lpb-edit.use-ajax[href*=\"/edit/\"]');
        if (edit) {
          try { edit.scrollIntoView({block: 'center'}); } catch (e) {}
          edit.click();
        }
      })();",
    );
    $this->waitForLayoutParagraphRichTextEditor();
  }

}
