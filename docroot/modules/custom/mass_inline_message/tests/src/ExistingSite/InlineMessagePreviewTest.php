<?php

namespace Drupal\Tests\mass_inline_message\ExistingSite;

/**
 * Tests Message box preview rendering uses the same Mayflower output as the filter.
 */
class InlineMessagePreviewTest extends MassInlineMessageExistingSiteTestBase {

  /**
   * Tests render helper output matches filter_mass_inline_message.
   */
  public function testRenderHtmlMatchesFilterOutput(): void {
    $renderer = $this->messageBoxRenderer();

    $markup = '<mass-inline-message data-title="Pay online" data-type="warning"><p>Body text here.</p></mass-inline-message>';
    $filtered = check_markup($markup, 'basic_html');
    $direct = $renderer->renderHtml('warning', 'Pay online', '<p>Body text here.</p>');

    $this->assertStringContainsString('ma__inline-message', $direct);
    $this->assertStringContainsString('ma__inline-message--warning', $direct);
    $this->assertStringContainsString('Pay online', $direct);
    $this->assertStringContainsString('Body text here.', $direct);
    $this->assertStringContainsString('ma__inline-message', $filtered);
    $this->assertStringContainsString('ma__inline-message__title', $direct);
    $this->assertStringContainsString('ma__inline-message__content', $direct);
  }

  /**
   * Tests title-only message boxes omit the Mayflower rich-text content region.
   */
  public function testEmptyBodyOmitsRichTextRegion(): void {
    $renderer = $this->messageBoxRenderer();

    $title_only = $renderer->renderHtml('info', 'Title only', NULL);
    $this->assertStringContainsString('Title only', $title_only);
    $this->assertStringNotContainsString('ma__inline-message__content', $title_only);

    $ckeditor_empty = $renderer->renderHtml('info', 'Title only', '<p></p>');
    $this->assertStringNotContainsString('ma__inline-message__content', $ckeditor_empty);

    $nbsp_only = $renderer->renderHtml('info', 'Title only', '<p>&nbsp;</p>');
    $this->assertStringNotContainsString('ma__inline-message__content', $nbsp_only);
  }

  /**
   * Tests CKEditor preview inlines SVG icons instead of leaving placeholders.
   */
  public function testPreviewRenderInlinesSvgIcons(): void {
    $preview = $this->messageBoxRenderer()->renderHtml('warning', 'Pay online', '<p>Body</p>', TRUE);
    $this->assertStringNotContainsString('<svg-placeholder', $preview);
    $this->assertStringContainsString('<svg', $preview);
    $this->assertStringContainsString('<use href="#', $preview);
  }

}
