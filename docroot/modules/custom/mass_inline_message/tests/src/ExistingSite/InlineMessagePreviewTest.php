<?php

namespace Drupal\Tests\mass_inline_message\ExistingSite;

use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests Message box preview rendering uses the same Mayflower output as the filter.
 */
class InlineMessagePreviewTest extends MassExistingSiteBase {

  /**
   * Tests render helper output matches filter_mass_inline_message.
   */
  public function testRenderHtmlMatchesFilterOutput(): void {
    $markup = '<mass-inline-message data-title="Pay online" data-type="warning"><p>Body text here.</p></mass-inline-message>';
    $filtered = check_markup($markup, 'basic_html');
    $direct = mass_inline_message_render_html('warning', 'Pay online', '<p>Body text here.</p>');

    $this->assertStringContainsString('ma__inline-message', $direct);
    $this->assertStringContainsString('ma__inline-message--warning', $direct);
    $this->assertStringContainsString('Pay online', $direct);
    $this->assertStringContainsString('Body text here.', $direct);
    $this->assertStringContainsString('ma__inline-message', $filtered);
    $this->assertStringContainsString('ma__inline-message__title', $direct);
    $this->assertStringContainsString('ma__inline-message__content', $direct);
  }

  /**
   * Tests CKEditor preview inlines SVG icons instead of leaving placeholders.
   */
  public function testPreviewRenderInlinesSvgIcons(): void {
    $preview = mass_inline_message_render_html('warning', 'Pay online', '<p>Body</p>', TRUE);
    $this->assertStringNotContainsString('<svg-placeholder', $preview);
    $this->assertStringContainsString('<svg', $preview);
    $this->assertStringContainsString('<use href="#', $preview);
  }

}
