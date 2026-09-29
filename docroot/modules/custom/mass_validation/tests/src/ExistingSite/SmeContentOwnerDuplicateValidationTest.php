<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests that an SME / content owner cannot be attached twice to one entity.
 */
class SmeContentOwnerDuplicateValidationTest extends MassExistingSiteBase {

  private const DUPLICATE_MESSAGE = 'has been entered multiple times';

  /**
   * First owner used by the tests.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  private TermInterface $owner;

  /**
   * Second, distinct owner used by the tests.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  private TermInterface $otherOwner;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $vocabulary = Vocabulary::load('sme_owner');
    $this->owner = $this->createTerm($vocabulary, ['name' => 'Duplicate Validation Owner', 'langcode' => 'en']);
    $this->otherOwner = $this->createTerm($vocabulary, ['name' => 'Duplicate Validation Other Owner', 'langcode' => 'en']);
  }

  /**
   * The same owner twice is rejected, and the message names the owner.
   */
  public function testRepeatedOwnerIsRejected(): void {
    $node = $this->createOwnedNode([$this->owner->id(), $this->owner->id()]);

    $violations = $node->get('field_sme_content_owner')->validate();

    $this->assertCount(2, $violations, 'Both positions holding the repeated owner are flagged.');
    $this->assertStringContainsString(self::DUPLICATE_MESSAGE, strip_tags((string) $violations->get(0)->getMessage()));
    $this->assertStringContainsString($this->owner->label(), strip_tags((string) $violations->get(0)->getMessage()));
  }

  /**
   * A repeat is caught even when the two entries are not next to each other.
   */
  public function testRepeatedOwnerIsRejectedWhenNotAdjacent(): void {
    $node = $this->createOwnedNode([
      $this->owner->id(),
      $this->otherOwner->id(),
      $this->owner->id(),
    ]);

    $violations = $node->get('field_sme_content_owner')->validate();

    $this->assertCount(2, $violations);
    $this->assertSame('0.target_id', $violations->get(0)->getPropertyPath());
    $this->assertSame('2.target_id', $violations->get(1)->getPropertyPath());
  }

  /**
   * Several distinct owners remain allowed, as the ticket requires.
   */
  public function testDistinctOwnersAreAllowed(): void {
    $node = $this->createOwnedNode([$this->owner->id(), $this->otherOwner->id()]);

    $this->assertCount(0, $node->get('field_sme_content_owner')->validate());
  }

  /**
   * An empty field and a single owner stay valid.
   */
  public function testSingleAndEmptyValuesAreAllowed(): void {
    $node = $this->createOwnedNode([$this->owner->id()]);
    $this->assertCount(0, $node->get('field_sme_content_owner')->validate());

    $node->set('field_sme_content_owner', []);
    $this->assertCount(0, $node->get('field_sme_content_owner')->validate());
  }

  /**
   * Documents carry the same rule as pages.
   */
  public function testDocumentsCarryTheConstraint(): void {
    $definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('media', 'document');

    $this->assertArrayHasKey('field_sme_content_owner', $definitions);
    $this->assertContains(
      'MassDuplicateReference',
      array_keys($definitions['field_sme_content_owner']->getConstraints()),
      'The document field carries the duplicate check, not only nodes.'
    );
  }

  /**
   * Two owners cannot share a name, so authors do not create near duplicates.
   */
  public function testVocabularyRejectsDuplicateName(): void {
    $clone = Term::create([
      'vid' => 'sme_owner',
      'name' => $this->owner->label(),
      'langcode' => 'en',
    ]);

    $violations = $clone->validate();
    $messages = array_map(fn($violation) => strip_tags((string) $violation->getMessage()), iterator_to_array($violations));

    $this->assertNotEmpty($violations, 'A second owner with an existing name should be rejected.');
    $this->assertStringContainsString('already exists', implode(' ', $messages));
  }

  /**
   * A differently named owner is still allowed.
   */
  public function testVocabularyAllowsDistinctName(): void {
    $fresh = Term::create([
      'vid' => 'sme_owner',
      'name' => 'Duplicate Validation Owner ' . $this->randomMachineName(8),
      'langcode' => 'en',
    ]);

    $this->assertCount(0, $fresh->validate());
  }

  /**
   * Creates an advisory carrying the given owner term ids.
   */
  private function createOwnedNode(array $term_ids) {
    return $this->createNode([
      'type' => 'advisory',
      'title' => 'Duplicate owner validation',
      'field_sme_content_owner' => $term_ids,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

}
