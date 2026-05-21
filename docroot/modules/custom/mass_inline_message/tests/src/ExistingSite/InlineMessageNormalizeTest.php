<?php

namespace Drupal\Tests\mass_inline_message\ExistingSite;

use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests message body HTML normalization helpers.
 */
class InlineMessageNormalizeTest extends MassExistingSiteBase {

  /**
   * Tests a single CKEditor div wrapper is unwrapped.
   */
  public function testUnwrapsCkeditorDivWrapper(): void {
    $input = '<div><p>Pay at <a href="/pay">mass.gov/pay</a>.</p></div>';
    $expected = '<p>Pay at <a href="/pay">mass.gov/pay</a>.</p>';
    $this->assertSame($expected, mass_inline_message_normalize_body_html($input));
  }

  /**
   * Tests empty body normalizes to empty string.
   */
  public function testEmptyBody(): void {
    $this->assertSame('', mass_inline_message_normalize_body_html(''));
    $this->assertSame('', mass_inline_message_normalize_body_html('   '));
  }

  /**
   * Tests disallowed tags are stripped during normalization.
   */
  public function testStripsDisallowedTags(): void {
    $input = '<p>OK</p><script>alert(1)</script>';
    $normalized = mass_inline_message_normalize_body_html($input);
    $this->assertStringContainsString('<p>OK</p>', $normalized);
    $this->assertStringNotContainsString('<script', $normalized);
  }

}
