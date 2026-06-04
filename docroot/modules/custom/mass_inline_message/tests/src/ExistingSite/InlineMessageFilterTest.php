<?php

namespace Drupal\Tests\mass_inline_message\ExistingSite;

/**
 * Tests filter_mass_inline_message renders Mayflower markup.
 */
class InlineMessageFilterTest extends MassInlineMessageExistingSiteTestBase {

  /**
   * Tests warning message box is rendered with Mayflower classes.
   */
  public function testFilterRendersInlineMessage(): void {
    $html = '<mass-inline-message data-title="Pay online" data-type="warning"><p>Body text here.</p></mass-inline-message>';
    $filtered = check_markup($html, 'basic_html');
    $this->assertStringContainsString('ma__inline-message', $filtered);
    $this->assertStringContainsString('ma__inline-message--warning', $filtered);
    $this->assertStringContainsString('ma__inline-message__title', $filtered);
    $this->assertStringContainsString('Pay online', $filtered);
    $this->assertStringContainsString('Body text here.', $filtered);
    $this->assertStringNotContainsString('<mass-inline-message', $filtered);
  }

  /**
   * Tests info message box renders without warning modifier.
   */
  public function testFilterRendersInfoType(): void {
    $html = '<mass-inline-message data-title="Note" data-type="info"></mass-inline-message>';
    $filtered = check_markup($html, 'basic_html');
    $this->assertStringContainsString('ma__inline-message', $filtered);
    $this->assertStringNotContainsString('ma__inline-message--warning', $filtered);
    $this->assertStringContainsString('Note', $filtered);
  }

}
