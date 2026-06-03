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
   * Tests rich embedded markup is preserved for later text-format filtering.
   */
  public function testPreservesEmbeddedMarkup(): void {
    $input = '<p>OK</p><drupal-media data-entity-type="media" data-entity-uuid="abc"></drupal-media>';
    $normalized = MessageBoxBody::normalize($input);
    $this->assertStringContainsString('<p>OK</p>', $normalized);
    $this->assertStringContainsString('<drupal-media', $normalized);
  }

}
