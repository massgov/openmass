<?php

namespace Drupal\Tests\mass_validation\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests that SME / content owner names end with an organization in brackets.
 */
class SmeContentOwnerNameFormatTest extends MassExistingSiteBase {

  private const MESSAGE = 'needs an organization abbreviation in parentheses';

  /**
   * Names content practice expects to accept or reject.
   */
  public static function nameProvider(): array {
    return [
      'name and agency' => ['Dima Storozhuk (EOTSS)', TRUE],
      'team and agency' => ['legal and privacy team (OCABR)', TRUE],
      'free text in parentheses' => ['Nancy Sullivan (OCABR legal)', TRUE],
      'trailing space' => ['Jane Doe (DPH) ', TRUE],
      'no parentheses' => ['Jane Doe', FALSE],
      'empty parentheses' => ['Jane Doe ()', FALSE],
      'parentheses not at the end' => ['Jane (DPH) Doe', FALSE],
    ];
  }

  /**
   * The vocabulary enforces the format on the term form.
   *
   * @dataProvider nameProvider
   */
  public function testTermNameFormat(string $name, bool $valid): void {
    $term = Term::create(['vid' => 'sme_owner', 'name' => $name, 'langcode' => 'en']);

    $this->assertSame($valid, !$this->hasFormatViolation($term->get('name')->validate()), "Unexpected result for '$name'.");
  }

  /**
   * Owners created from the content form are held to the same format.
   *
   * @dataProvider nameProvider
   */
  public function testNewOwnerFromContentForm(string $name, bool $valid): void {
    $node = $this->createAdvisory();
    $node->set('field_sme_content_owner', [
      ['entity' => Term::create(['vid' => 'sme_owner', 'name' => $name, 'langcode' => 'en'])],
    ]);

    $this->assertSame($valid, !$this->hasFormatViolation($node->get('field_sme_content_owner')->validate()), "Unexpected result for '$name'.");
  }

  /**
   * An existing owner never blocks saving content, whatever its name.
   *
   * Otherwise an editor touching an unrelated page would be stopped by an
   * entry someone else created before the rule existed.
   */
  public function testExistingOwnerIsNotRechecked(): void {
    $legacy = $this->createTerm(Vocabulary::load('sme_owner'), [
      'name' => 'Legacy Owner Without Agency ' . $this->randomMachineName(6),
      'langcode' => 'en',
    ]);
    $node = $this->createAdvisory();
    $node->set('field_sme_content_owner', [$legacy->id()]);

    $this->assertFalse($this->hasFormatViolation($node->get('field_sme_content_owner')->validate()));
  }

  /**
   * Other vocabularies are not affected.
   */
  public function testOtherVocabulariesAreUntouched(): void {
    $term = Term::create(['vid' => 'user_organization', 'name' => 'Plain name', 'langcode' => 'en']);

    $this->assertFalse($this->hasFormatViolation($term->get('name')->validate()));
  }

  /**
   * Whether a violation list contains the name format message.
   */
  private function hasFormatViolation($violations): bool {
    foreach ($violations as $violation) {
      if (str_contains(strip_tags((string) $violation->getMessage()), self::MESSAGE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Creates a published advisory.
   */
  private function createAdvisory() {
    return $this->createNode([
      'type' => 'advisory',
      'title' => 'Owner name format ' . $this->randomMachineName(8),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

}
