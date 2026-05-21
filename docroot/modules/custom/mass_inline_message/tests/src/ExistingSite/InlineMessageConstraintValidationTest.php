<?php

namespace Drupal\Tests\mass_inline_message\ExistingSite;

use Drupal\mass_inline_message\Plugin\Validation\Constraint\InlineMessageConstraint;
use Drupal\mass_content_moderation\MassModeration;
use MassGov\Dtt\MassExistingSiteBase;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Tests InlineMessageConstraint validation on rich text markup.
 */
class InlineMessageConstraintValidationTest extends MassExistingSiteBase {

  /**
   * Validates markup against InlineMessageConstraint.
   */
  private function validateMarkup(string $markup): ConstraintViolationListInterface {
    $validator = \Drupal::service('validation.basic_recursive_validator_factory')->createValidator();
    return $validator->validate($markup, new InlineMessageConstraint());
  }

  /**
   * Tests missing title triggers a violation.
   */
  public function testMissingTitle(): void {
    $violations = $this->validateMarkup('<mass-inline-message data-title="" data-type="info"></mass-inline-message>');
    $this->assertGreaterThan(0, $violations->count());
    $this->assertStringContainsString('title is required', (string) $violations->get(0)->getMessage());
  }

  /**
   * Tests title over 60 characters triggers a violation.
   */
  public function testTitleTooLong(): void {
    $title = str_repeat('a', 61);
    $markup = '<mass-inline-message data-title="' . $title . '" data-type="info"></mass-inline-message>';
    $violations = $this->validateMarkup($markup);
    $this->assertGreaterThan(0, $violations->count());
    $this->assertStringContainsString('60 characters', (string) $violations->get(0)->getMessage());
  }

  /**
   * Tests invalid type triggers a violation.
   */
  public function testInvalidType(): void {
    $markup = '<mass-inline-message data-title="Test" data-type="alert"></mass-inline-message>';
    $violations = $this->validateMarkup($markup);
    $this->assertGreaterThan(0, $violations->count());
    $this->assertStringContainsString('Informational or Alert', (string) $violations->get(0)->getMessage());
  }

  /**
   * Tests body over 300 plain-text characters triggers a violation.
   */
  public function testBodyTooLong(): void {
    $body = '<p>' . str_repeat('x', 301) . '</p>';
    $markup = '<mass-inline-message data-title="Test" data-type="info">' . $body . '</mass-inline-message>';
    $violations = $this->validateMarkup($markup);
    $this->assertGreaterThan(0, $violations->count());
    $this->assertStringContainsString('300 characters', (string) $violations->get(0)->getMessage());
  }

  /**
   * Tests allowed tags with attributes do not trigger a violation.
   */
  public function testAllowedTagsWithAttributes(): void {
    $markup = '<mass-inline-message data-title="Test" data-type="info"><p class="intro">See <a href="/test">link</a>.</p></mass-inline-message>';
    $violations = $this->validateMarkup($markup);
    $this->assertCount(0, $violations);
  }

  /**
   * Tests CKEditor-exported div wrapper markup passes validation.
   */
  public function testCkeditorDivWrapperMarkup(): void {
    $markup = '<p>Intro text.</p><mass-inline-message data-title="Pay online" data-type="warning"><div><p>Pay at <a href="/pay">mass.gov/pay</a>.</p></div></mass-inline-message>';
    $violations = $this->validateMarkup($markup);
    $this->assertCount(0, $violations, (string) $violations);
  }

  /**
   * Tests span tags inside body are rejected.
   */
  public function testDisallowedTag(): void {
    $markup = '<mass-inline-message data-title="Test" data-type="info"><p>Intro</p><table><tr><td>Table</td></tr></table></mass-inline-message>';
    $violations = $this->validateMarkup($markup);
    $this->assertGreaterThan(0, $violations->count());
    $this->assertStringContainsString('may only use these HTML tags', (string) $violations->get(0)->getMessage());
  }

  /**
   * Tests valid markup produces no violations.
   */
  public function testValidMessage(): void {
    $markup = '<mass-inline-message data-title="Valid" data-type="warning"><p>Short body.</p></mass-inline-message>';
    $violations = $this->validateMarkup($markup);
    $this->assertCount(0, $violations);
  }

  /**
   * Tests empty overview text without message boxes has no violations.
   */
  public function testEmptyOverviewHasNoViolations(): void {
    $violations = $this->validateMarkup('');
    $this->assertCount(0, $violations);
  }

  /**
   * Tests saving an info_details overview field with a message box passes validation.
   */
  public function testInfoDetailsOverviewFieldSaveWorkflow(): void {
    $overview = '<p>Overview intro paragraph.</p>'
      . '<mass-inline-message data-title="Payment options" data-type="info">'
      . '<div><p>Visit <a href="https://www.mass.gov/pay">mass.gov/pay</a> to pay online.</p></div>'
      . '</mass-inline-message>';

    $violations = $this->validateMarkup($overview);
    $this->assertCount(0, $violations, (string) $violations);

    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Message box overview test ' . $this->randomMachineName(8),
      'field_info_detail_overview' => [
        'value' => $overview,
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
