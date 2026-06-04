<?php

namespace Drupal\Tests\mass_inline_message\Traits;

/**
 * Selenium helpers for Message box inside Layout Paragraphs Rich text modals.
 */
trait InlineMessageLayoutParagraphsTestTrait {

  /**
   * Textarea selector for Rich text inside an open LP dialog.
   */
  protected const LP_RICH_TEXT_EDITOR_SELECTOR = '.ui-dialog textarea[data-ckeditor5-id]';

  /**
   * Loads browser test helpers for LP widget toolbar alignment checks.
   */
  protected function attachLayoutParagraphToolbarTestHelpers(): void {
    $script = file_get_contents(__DIR__ . '/../ExistingSiteJavascript/scripts/lp-widget-toolbar-test.js');
    $this->inlineMessageSession()->executeScript($script);
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
   * Opens Content tab and adds Service Section + Rich text LP components.
   */
  protected function openServiceRichTextEditorInLayoutParagraph(): void {
    $page = $this->inlineMessageSession()->getPage();
    $session = $this->inlineMessageSession();

    $session->executeScript(
      "document.querySelector('a[href^=\"#edit-group-content\"]').scrollIntoView({block: 'center'});",
    );
    $page->find('css', '.horizontal-tab-button a[href="#edit-group-content"]')->click();

    $session->wait(1500);
    $addSection = $page->find(
      'css',
      '[data-drupal-selector="edit-field-service-sections-layout-paragraphs-builder"] a.lpb-btn.use-ajax.center.js-lpb-ui[href*="/choose-component"]',
    );
    $this->assertNotNull($addSection);
    $addSection->click();

    $session->wait(5000, "document.querySelector('.ui-dialog.lpb-dialog') !== null");
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
      8000,
      "document.querySelector('.ui-dialog .ui-dialog-title') && document.querySelector('.ui-dialog .ui-dialog-title').textContent.indexOf('Create new Service Section') !== -1",
    );
    $this->clickLayoutParagraphDialogSave();
    $session->wait(8000, "document.querySelector('.ui-dialog.lpb-dialog') === null");

    $session->wait(
      8000,
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

    $session->wait(3000, "document.querySelector('.ui-dialog .lpb-component-list__item.type-service_rich_text a.use-ajax') !== null");
    $session->executeScript(
      "(function(){
        var el = document.querySelector('.ui-dialog .lpb-component-list__item.type-service_rich_text a.use-ajax');
        if (el) {
          try { el.scrollIntoView({block: 'center'}); } catch (e) {}
          el.click();
        }
      })();",
    );

    $session->wait(
      10000,
      "document.querySelector('.ui-dialog .ui-dialog-title') && document.querySelector('.ui-dialog .ui-dialog-title').textContent.toLowerCase().indexOf('rich text') !== -1",
    );

    $session->wait(
      15000,
      "document.querySelector('.ui-dialog .ck-editor [contenteditable=true]') !== null",
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

}
