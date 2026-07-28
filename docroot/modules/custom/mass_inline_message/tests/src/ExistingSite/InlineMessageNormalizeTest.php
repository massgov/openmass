<?php

namespace Drupal\Tests\mass_inline_message\ExistingSite;

use Drupal\mass_inline_message\MessageBoxBody;

/**
 * Tests message body HTML normalization.
 */
class InlineMessageNormalizeTest extends MassInlineMessageExistingSiteTestBase {

  /**
   * Tests a single CKEditor div wrapper is unwrapped.
   */
  public function testUnwrapsCkeditorDivWrapper(): void {
    $input = '<div><p>Pay at <a href="/pay">mass.gov/pay</a>.</p></div>';
    $expected = '<p>Pay at <a href="/pay">mass.gov/pay</a>.</p>';
    $this->assertSame($expected, MessageBoxBody::normalize($input));
  }

  /**
   * Tests empty body normalizes to empty string.
   */
  public function testEmptyBody(): void {
    $this->assertSame('', MessageBoxBody::normalize(''));
    $this->assertSame('', MessageBoxBody::normalize('   '));
  }

  /**
   * Tests CKEditor empty paragraph markup is not considered renderable content.
   */
  public function testEmptyCkeditorMarkupIsNotRenderable(): void {
    $this->assertFalse(MessageBoxBody::hasRenderableContent('<p></p>'));
    $this->assertFalse(MessageBoxBody::hasRenderableContent('<p><br></p>'));
    $this->assertFalse(MessageBoxBody::hasRenderableContent('<p>&nbsp;</p>'));
    $this->assertFalse(MessageBoxBody::hasRenderableContent('&nbsp;'));
    $this->assertSame('', MessageBoxBody::normalize('<div><p>&nbsp;</p></div>'));
    $this->assertTrue(MessageBoxBody::hasRenderableContent('<p>Text</p>'));
  }

  /**
   * Tests rich embedded markup is preserved for later text-format filtering.
   */
  public function testPreservesEmbeddedMarkup(): void {
    $input = '<p>OK</p><drupal-media data-entity-type="media" data-entity-uuid="abc"></drupal-media>';
    $normalized = MessageBoxBody::normalize($input);
    $this->assertStringContainsString('<p>OK</p>', $normalized);
    $this->assertStringContainsString('<drupal-media', $normalized);
  }

  /**
   * Tests image and embed-only bodies are preserved (no plain text).
   */
  public function testPreservesImageAndEmbedOnlyBodies(): void {
    $cases = [
      '<img src="/sites/default/files/chart.jpg" alt="Chart">',
      '<figure class="image"><img src="/sites/default/files/chart.jpg" alt="Chart"></figure>',
      '<drupal-media data-entity-type="media" data-entity-uuid="abc"></drupal-media>',
      '<drupal-entity data-entity-type="media" data-entity-uuid="abc" data-embed-button="media_entity_download"></drupal-entity>',
      '<p><img src="/sites/default/files/chart.jpg" alt="Chart"></p>',
    ];
    foreach ($cases as $html) {
      $this->assertTrue(MessageBoxBody::hasRenderableContent($html), $html);
      $this->assertNotSame('', MessageBoxBody::normalize($html), $html);
    }
  }

  /**
   * Tests dialog save path retains image markup through message_box_body filters.
   */
  public function testMessageBoxBodyFormatPreservesImageMarkup(): void {
    $html = '<p>Caption</p><drupal-entity data-entity-type="media" data-entity-uuid="abc" data-embed-button="media_entity_download"></drupal-entity>';
    $normalized = MessageBoxBody::normalize($html);
    $filtered = check_markup($normalized, MessageBoxBody::FORMAT_ID);
    $this->assertStringContainsString('Caption', $filtered);
    $this->assertStringContainsString('drupal-entity', $filtered);
  }

}
