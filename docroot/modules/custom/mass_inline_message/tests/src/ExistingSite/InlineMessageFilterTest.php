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
   * Tests title-only stored markup does not render an empty content region.
   */
  public function testFilterOmitsBodyWhenOnlyNbsp(): void {
    $html = '<mass-inline-message data-title="Alert" data-type="warning"><p>&nbsp;</p></mass-inline-message>';
    $filtered = check_markup($html, 'basic_html');
    $this->assertStringContainsString('Alert', $filtered);
    $this->assertStringNotContainsString('ma__inline-message__content', $filtered);
    $this->assertStringNotContainsString('&nbsp;', $filtered);
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

  /**
   * Tests disallowed body markup is stripped when the message box is rendered.
   */
  public function testFilterStripsDisallowedBodyMarkup(): void {
    $html = '<mass-inline-message data-title="Note" data-type="info"><blockquote><p>Quote</p></blockquote><h2>Title</h2><p>Body.</p></mass-inline-message>';
    $filtered = check_markup($html, 'basic_html');
    $this->assertStringContainsString('Body.', $filtered);
    $this->assertStringNotContainsString('blockquote', $filtered);
    $this->assertStringNotContainsString('<h2', $filtered);
  }

  /**
   * Tests nested message box tags in body are stripped on display.
   */

  /**
   * Tests news body paragraph cleanup does not strip SVG path elements.
   */
  public function testNewsBodyParagraphCleanupPreservesSvgPath(): void {
    $html = '<symbol id="icon"><path d="M1 2"/></symbol><p lang="es">Text</p>';
    $cleaned = preg_replace_callback('/<\s*p\b([^>]*)>/i', function (array $m) {
      $attrs = $m[1];
      if (preg_match('/\s+lang=(["\'])([^"\']*)\1/', $attrs, $lang_match)) {
        return '<p lang="' . $lang_match[2] . '">';
      }
      return '<p>';
    }, $html);
    $this->assertStringContainsString('<path d="M1 2"/>', $cleaned);
    $this->assertStringContainsString('<p lang="es">', $cleaned);
  }

  public function testFilterStripsNestedMessageBoxInBody(): void {
    $html = '<mass-inline-message data-title="Outer" data-type="info"><mass-inline-message data-title="Inner" data-type="warning"><p>Nested</p></mass-inline-message><p>Outer body.</p></mass-inline-message>';
    $filtered = check_markup($html, 'basic_html');
    $this->assertStringContainsString('Outer body.', $filtered);
    $this->assertStringNotContainsString('<mass-inline-message', $filtered);
    $this->assertStringNotContainsString('ma__inline-message--warning', $filtered);
  }

}
