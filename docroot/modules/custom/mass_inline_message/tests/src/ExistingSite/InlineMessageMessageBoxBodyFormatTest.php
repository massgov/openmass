<?php

namespace Drupal\Tests\mass_inline_message\ExistingSite;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\mass_inline_message\MessageBoxBody;

/**
 * Tests the Message box body text format configuration.
 */
class InlineMessageMessageBoxBodyFormatTest extends MassInlineMessageExistingSiteTestBase {

  /**
   * Tests the format exists and excludes nested message box rendering.
   */
  public function testMessageBoxBodyFormatConfiguration(): void {
    $format = FilterFormat::load(MessageBoxBody::FORMAT_ID);
    $this->assertNotNull($format);
    $this->assertArrayNotHasKey('filter_mass_inline_message', $format->get('filters') ?? []);

    $allowed_html = $format->filters('filter_html')->getConfiguration()['settings']['allowed_html'] ?? '';
    $this->assertStringNotContainsString('blockquote', $allowed_html);
    $this->assertStringNotContainsString('<h2', $allowed_html);
    $this->assertStringNotContainsString('mass-inline-message', $allowed_html);
    $this->assertStringContainsString('<th', $allowed_html);
    $this->assertStringContainsString('<thead', $allowed_html);

    $editor = Editor::load(MessageBoxBody::FORMAT_ID);
    $this->assertNotNull($editor);
    $toolbar_items = $editor->getSettings()['toolbar']['items'] ?? [];
    $this->assertNotContains('blockQuote', $toolbar_items);
    $this->assertNotContains('messageBox', $toolbar_items);
    $this->assertNotContains('heading', $toolbar_items);
    $this->assertContains('insertTable', $toolbar_items);
  }

  /**
   * Tests disallowed tags are stripped from message box body HTML.
   */
  public function testMessageBoxBodyFormatStripsDisallowedTags(): void {
    $html = '<blockquote><p>Quoted</p></blockquote><h2>Heading</h2><p>Allowed.</p>';
    $filtered = check_markup($html, MessageBoxBody::FORMAT_ID);
    $this->assertStringNotContainsString('blockquote', $filtered);
    $this->assertStringNotContainsString('<h2', $filtered);
    $this->assertStringContainsString('Allowed.', $filtered);
  }

  /**
   * Tests nested message box markup is not preserved in the body format.
   */
  public function testMessageBoxBodyFormatStripsNestedMessageBoxTags(): void {
    $html = '<mass-inline-message data-title="Inner" data-type="warning"><p>Nested</p></mass-inline-message><p>Outer.</p>';
    $filtered = check_markup($html, MessageBoxBody::FORMAT_ID);
    $this->assertStringNotContainsString('mass-inline-message', $filtered);
    $this->assertStringContainsString('Outer.', $filtered);
  }

}
