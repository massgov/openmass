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
   * Creates a service page with a Service Section containing a Rich text paragraph.
   */
  protected function createServicePageWithRichTextInSection(array $overrides = []): NodeInterface {
    $paragraph_storage = $this->container->get('entity_type.manager')->getStorage('paragraph');

    $rich_text = $paragraph_storage->create([
      'type' => 'service_rich_text',
      'field_section_body' => [
        'value' => '<p>Initial rich text.</p>',
        'format' => 'basic_html',
      ],
    ]);
    $rich_text->save();

    $service_section = $paragraph_storage->create([
      'type' => 'service_section',
      'field_service_section_heading' => 'Test section',
      'field_section_style' => 'simple',
      'field_hide_heading' => 0,
      'field_service_section_content' => [
        [
          'target_id' => $rich_text->id(),
          'target_revision_id' => $rich_text->getRevisionId(),
        ],
      ],
    ]);
    $service_section->save();

    $defaults = [
      'type' => 'service_page',
      'title' => 'Message box LP ' . $this->randomMachineName(8),
      'moderation_state' => MassModeration::PUBLISHED,
      'field_service_sections' => [
        [
          'target_id' => $service_section->id(),
          'target_revision_id' => $service_section->getRevisionId(),
        ],
      ],
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
      "document.querySelector('.lpb-component') !== null || document.querySelector('[data-drupal-selector^=\"edit-field-service-sections\"]') !== null",
    );
  }

  /**
   * Waits until the LP Rich text modal and CKEditor are ready.
   */
  protected function waitForLayoutParagraphRichTextEditor(): void {
    $session = $this->inlineMessageSession();
    $session->wait(
      self::JS_WAIT_DEFAULT,
      "document.querySelector('.ui-dialog .ui-dialog-title') && document.querySelector('.ui-dialog .ui-dialog-title').textContent.toLowerCase().indexOf('rich text') !== -1",
    );
    $session->wait(
      self::JS_WAIT_DEFAULT,
      "document.querySelector('.ui-dialog .ck-editor [contenteditable=true]') !== null",
    );
  }

  /**
   * Opens Rich text inside a pre-created Service Section.
   */
  protected function openServiceRichTextEditorInLayoutParagraph(): void {
    $this->openLayoutParagraphsContentTab();
    $this->inlineMessageSession()->executeScript(
      "(function(){
        var edit = document.querySelector('.layout.layout--onecol-mass-service-section .lpb-component.type-service_rich_text a.lpb-edit.use-ajax')
          || document.querySelector('.lpb-component.type-service_rich_text a.lpb-edit.use-ajax');
        if (edit) {
          try { edit.scrollIntoView({block: 'center'}); } catch (e) {}
          edit.click();
        }
      })();",
    );
    $this->waitForLayoutParagraphRichTextEditor();
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
