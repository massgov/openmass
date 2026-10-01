<?php

namespace Drupal\Tests\mass_fields\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests that the SME / Content Owner stays internal on published content.
 *
 * The ticket is explicit: the field must not render to the public, and must
 * not appear in the metadata of a published page.
 */
class SmeContentOwnerPrivacyTest extends MassExistingSiteBase {

  /**
   * The owner attached to the content under test.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  private TermInterface $owner;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->owner = $this->createTerm(Vocabulary::load('sme_owner'), [
      'name' => 'Zebra Privacy Owner ' . $this->randomMachineName(8),
      'langcode' => 'en',
    ]);
  }

  /**
   * Published pages of every kind keep the owner out of the markup.
   *
   * @dataProvider publicBundleProvider
   */
  public function testOwnerIsAbsentFromThePublishedPage(string $bundle): void {
    $node = $this->createNode([
      'type' => $bundle,
      'title' => 'Owner privacy ' . $bundle,
      'field_sme_content_owner' => [$this->owner->id()],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->visit($node->toUrl()->toString());
    $this->assertEquals(200, $this->getSession()->getStatusCode());

    $html = $this->getSession()->getPage()->getContent();
    $this->assertStringNotContainsString($this->owner->label(), $html, "The owner name is rendered on a published $bundle.");
    $this->assertStringNotContainsString('Subject Matter Expert', $html, "The field label is rendered on a published $bundle.");
  }

  /**
   * The owner never reaches the metadata of a published page.
   *
   * @dataProvider publicBundleProvider
   */
  public function testOwnerIsAbsentFromTheMetadata(string $bundle): void {
    $node = $this->createNode([
      'type' => $bundle,
      'title' => 'Owner metadata ' . $bundle,
      'field_sme_content_owner' => [$this->owner->id()],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->visit($node->toUrl()->toString());
    $this->assertEquals(200, $this->getSession()->getStatusCode());

    foreach ($this->getSession()->getPage()->findAll('css', 'meta') as $meta) {
      $this->assertStringNotContainsString(
        $this->owner->label(),
        $meta->getOuterHtml(),
        "The owner name leaked into a meta tag on a published $bundle."
      );
    }

    foreach ($this->getSession()->getPage()->findAll('css', 'script[type="application/ld+json"]') as $script) {
      $this->assertStringNotContainsString(
        $this->owner->label(),
        $script->getText(),
        "The owner name leaked into structured data on a published $bundle."
      );
    }
  }

  /**
   * The public API does not carry the field on content.
   */
  public function testFieldIsNotExposedInTheApi(): void {
    $node = $this->createNode([
      'type' => 'advisory',
      'title' => 'Owner api ' . $this->randomMachineName(8),
      'field_sme_content_owner' => [$this->owner->id()],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->visit('/jsonapi/node/advisory/' . $node->uuid());
    $this->assertEquals(200, $this->getSession()->getStatusCode());

    $document = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIsArray($document, 'The API did not return a document.');
    $exposed = array_merge(
      array_keys($document['data']['attributes'] ?? []),
      array_keys($document['data']['relationships'] ?? [])
    );
    $this->assertNotContains('field_sme_content_owner', $exposed, 'The owner field is served by the public API.');
  }

  /**
   * The public API does not list the owners either.
   */
  public function testOwnerVocabularyIsNotExposedInTheApi(): void {
    $this->visit('/jsonapi/taxonomy_term/sme_owner');

    $this->assertEquals(404, $this->getSession()->getStatusCode(), 'The owner vocabulary is browsable through the public API.');
    $this->assertStringNotContainsString($this->owner->label(), $this->getSession()->getPage()->getContent());
  }

  /**
   * The term page itself is not browsable either.
   */
  public function testOwnerTermPageIsNotPublic(): void {
    $this->visit('/taxonomy/term/' . $this->owner->id());

    $this->assertEquals(404, $this->getSession()->getStatusCode(), 'Owner terms must not have a public page.');
  }

  /**
   * Content types whose published pages are checked.
   */
  public static function publicBundleProvider(): array {
    return [
      'advisory' => ['advisory'],
      'info_details' => ['info_details'],
      'news' => ['news'],
      'service_page' => ['service_page'],
      'org_page' => ['org_page'],
    ];
  }

}
