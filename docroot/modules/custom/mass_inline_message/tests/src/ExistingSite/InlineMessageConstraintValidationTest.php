<?php

namespace Drupal\Tests\mass_inline_message\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;

/**
 * Tests InlineMessageConstraint validation on rich text markup.
 */
class InlineMessageConstraintValidationTest extends MassInlineMessageExistingSiteTestBase {

  /**
   * Tests missing title triggers a violation.
   */
  public function testMissingTitle(): void {
    $violations = $this->validateInlineMessageMarkup('<mass-inline-message data-title="" data-type="info"></mass-inline-message>');
    $this->assertGreaterThan(0, $violations->count());
    $this->assertStringContainsString('title is required', (string) $violations->get(0)->getMessage());
  }

  /**
   * Tests title over 60 characters triggers a violation.
   */
  public function testTitleTooLong(): void {
    $title = str_repeat('a', 61);
    $markup = '<mass-inline-message data-title="' . $title . '" data-type="info"></mass-inline-message>';
    $violations = $this->validateInlineMessageMarkup($markup);
    $this->assertGreaterThan(0, $violations->count());
    $this->assertStringContainsString('60 characters', (string) $violations->get(0)->getMessage());
  }

  /**
   * Tests invalid type triggers a violation.
   */
  public function testInvalidType(): void {
    $markup = '<mass-inline-message data-title="Test" data-type="alert"></mass-inline-message>';
    $violations = $this->validateInlineMessageMarkup($markup);
    $this->assertGreaterThan(0, $violations->count());
    $this->assertStringContainsString('Informational or Alert', (string) $violations->get(0)->getMessage());
  }

  /**
   * Tests body over 300 plain-text characters triggers a violation.
   */
  public function testBodyTooLong(): void {
    $body = '<p>' . str_repeat('x', 301) . '</p>';
    $markup = '<mass-inline-message data-title="Test" data-type="info">' . $body . '</mass-inline-message>';
    $violations = $this->validateInlineMessageMarkup($markup);
    $this->assertGreaterThan(0, $violations->count());
    $this->assertStringContainsString('300 characters', (string) $violations->get(0)->getMessage());
  }

  /**
   * Tests rich body HTML with attributes does not trigger a violation.
   */
  public function testBodyHtmlWithAttributes(): void {
    $markup = '<mass-inline-message data-title="Test" data-type="info"><p class="intro">See <a href="/test">link</a>.</p></mass-inline-message>';
    $violations = $this->validateInlineMessageMarkup($markup);
    $this->assertCount(0, $violations);
  }

  /**
   * Tests CKEditor-exported div wrapper markup passes validation.
   */
  public function testCkeditorDivWrapperMarkup(): void {
    $markup = '<p>Intro text.</p><mass-inline-message data-title="Pay online" data-type="warning"><div><p>Pay at <a href="/pay">mass.gov/pay</a>.</p></div></mass-inline-message>';
    $violations = $this->validateInlineMessageMarkup($markup);
    $this->assertCount(0, $violations, (string) $violations);
  }

  /**
   * Tests valid markup produces no violations.
   */
  public function testValidMessage(): void {
    $markup = '<mass-inline-message data-title="Valid" data-type="warning"><p>Short body.</p></mass-inline-message>';
    $violations = $this->validateInlineMessageMarkup($markup);
    $this->assertCount(0, $violations);
  }

  /**
   * Tests empty overview text without message boxes has no violations.
   */
  public function testEmptyOverviewHasNoViolations(): void {
    $violations = $this->validateInlineMessageMarkup('');
    $this->assertCount(0, $violations);
  }

  /**
   * Tests saving an info_details overview field with a message box passes validation.
   */
  public function testInfoDetailsOverviewFieldSaveWorkflow(): void {
    $violations = $this->validateInlineMessageMarkup(self::OVERVIEW_WITH_MESSAGE_BOX);
    $this->assertCount(0, $violations, (string) $violations);

    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Message box overview test ' . $this->randomMachineName(8),
      'field_info_detail_overview' => [
        'value' => self::OVERVIEW_WITH_MESSAGE_BOX,
        'format' => 'basic_html',
      ],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $node->save();
    $this->markEntityForCleanup($node);

    $stored = $node->get('field_info_detail_overview')->value;
    $this->assertStringContainsString('<mass-inline-message', $stored);
    $this->assertStringContainsString('Payment options', $stored);
  }

}
