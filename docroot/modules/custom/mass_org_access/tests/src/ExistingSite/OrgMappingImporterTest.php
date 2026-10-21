<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_org_access\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\mass_org_access\Form\OrgMappingImportForm;
use Drupal\mass_org_access\OrgMappingImporter;
use Drupal\taxonomy\Entity\Vocabulary;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;

/**
 * Verifies the CSV org_page → Permission Group mapping importer.
 *
 * @group mass_org_access
 */
class OrgMappingImporterTest extends MassExistingSiteBase {

  use TaxonomyCreationTrait;

  /**
   * Returns the importer service under test.
   */
  private function importer(): OrgMappingImporter {
    return \Drupal::service('mass_org_access.mapping_importer');
  }

  /**
   * Writes CSV contents to a temp file and returns its path.
   */
  private function writeCsv(string $contents): string {
    $path = tempnam(sys_get_temp_dir(), 'oog_csv_');
    file_put_contents($path, $contents);
    return $path;
  }

  /**
   * Parse reads rows under the header, groups by node, and counts junk.
   */
  public function testParseGroupsRowsByNodeAndCountsJunk(): void {
    $path = $this->writeCsv("nodeid,termid\n10,20\n10,21\n30,40\nbad,row\n\n");
    $result = $this->importer()->parse($path);
    unlink($path);

    $this->assertTrue($result['valid_header']);
    $this->assertEqualsCanonicalizing([20, 21], $result['mappings'][10]);
    $this->assertSame([40], $result['mappings'][30]);
    $this->assertSame(1, $result['invalid'], 'Only the non-numeric data row is counted.');
    $this->assertCount(1, $result['samples']);
  }

  /**
   * Parse rejects a file whose header is not "nodeid,termid".
   */
  public function testParseRejectsWrongHeader(): void {
    $path = $this->writeCsv("Title,Author,Date\nHello,Joe,2025-01-30\n12,34\n");
    $result = $this->importer()->parse($path);
    unlink($path);

    $this->assertFalse($result['valid_header'], 'A non-matching header must be rejected.');
    $this->assertEmpty($result['mappings'], 'No mappings come from a wrong-header file.');
  }

  /**
   * Apply expands ancestors and replaces the org_page's prior value.
   */
  public function testApplyExpandsAncestorsAndReplaces(): void {
    $vocab = Vocabulary::load('user_organization');
    $parent = $this->createTerm($vocab, ['name' => 'Parent ' . $this->randomMachineName()]);
    $child = $this->createTerm($vocab, [
      'name' => 'Child ' . $this->randomMachineName(),
      'parent' => [$parent->id()],
    ]);
    $stale = $this->createTerm($vocab, ['name' => 'Stale ' . $this->randomMachineName()]);
    $org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Import Org ' . $this->randomMachineName(),
      'field_content_organization' => [$stale->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $result = $this->importer()->apply((int) $org->id(), [(int) $child->id()]);
    $this->assertSame(OrgMappingImporter::IMPORTED, $result['status']);
    $this->assertSame(['published'], $result['revisions'], 'A published-only org page is written once.');

    $org = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($org->id());
    $tids = array_map('intval', array_column(
      $org->get('field_content_organization')->getValue(),
      'target_id'
    ));
    $this->assertEqualsCanonicalizing(
      [(int) $child->id(), (int) $parent->id()],
      $tids,
      'Apply stores the mapped term plus its ancestor.'
    );
    $this->assertNotContains(
      (int) $stale->id(),
      $tids,
      'Apply replaces the org_page prior Permission Groups value.'
    );
  }

  /**
   * With force off, an org_page that already has Permission Groups is skipped.
   */
  public function testApplySkipsExistingWhenNotForced(): void {
    $vocab = Vocabulary::load('user_organization');
    $existing = $this->createTerm($vocab, ['name' => 'Existing ' . $this->randomMachineName()]);
    $new = $this->createTerm($vocab, ['name' => 'New ' . $this->randomMachineName()]);
    $org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Has PG ' . $this->randomMachineName(),
      'field_content_organization' => [$existing->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $result = $this->importer()->apply((int) $org->id(), [(int) $new->id()], FALSE);

    $this->assertSame(OrgMappingImporter::SKIPPED, $result['status']);
    $this->assertStringContainsString('already has Permission Groups', $result['reason']);

    $org = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($org->id());
    $tids = array_map('intval', array_column(
      $org->get('field_content_organization')->getValue(),
      'target_id'
    ));
    $this->assertSame([(int) $existing->id()], $tids, 'The existing value is preserved.');
    $this->assertNotContains((int) $new->id(), $tids);
  }

  /**
   * Apply refuses a node that is not an organization page.
   */
  public function testApplyRejectsNonOrgPage(): void {
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Not an org ' . $this->randomMachineName(),
    ]);
    $term = $this->createTerm(
      Vocabulary::load('user_organization'),
      ['name' => 'Term ' . $this->randomMachineName()]
    );

    $result = $this->importer()->apply((int) $node->id(), [(int) $term->id()]);

    $this->assertSame(OrgMappingImporter::SKIPPED, $result['status']);
    $this->assertStringContainsString('not an organization page', $result['reason']);
    $this->assertTrue(
      $node->get('field_content_organization')->isEmpty(),
      'A non-org_page node must be left untouched.'
    );
  }

  /**
   * The import form survives the serialize/restore cycle managed_file triggers.
   *
   * Regression: the managed_file element caches the form, so it is serialized
   * and rebuilt on submit. readonly injected services break
   * DependencySerializationTrait, crashing submit with "must not be accessed
   * before initialization". This asserts the services are restored.
   */
  public function testImportFormSurvivesSerialization(): void {
    $form = OrgMappingImportForm::create(\Drupal::getContainer());
    // Trusted input: we serialize a form object we just built, to reproduce the
    // managed_file cache round-trip.
    // phpcs:ignore DrupalPractice.FunctionCalls.InsecureUnserialize
    $restored = unserialize(serialize($form));
    $property = new \ReflectionProperty($restored, 'importer');
    $this->assertInstanceOf(
      OrgMappingImporter::class,
      $property->getValue($restored),
      'Injected services must be restored after the form is cached and rebuilt.'
    );
  }

  /**
   * Apply ignores invalid term IDs but still stores the valid ones.
   */
  public function testApplyIgnoresInvalidTermIds(): void {
    $valid = $this->createTerm(
      Vocabulary::load('user_organization'),
      ['name' => 'Valid ' . $this->randomMachineName()]
    );
    $org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Import Org ' . $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $result = $this->importer()->apply((int) $org->id(), [(int) $valid->id(), 999999]);

    $this->assertSame(OrgMappingImporter::IMPORTED, $result['status']);
    $this->assertContains(999999, $result['invalid_tids']);
    $org = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($org->id());
    $tids = array_map('intval', array_column(
      $org->get('field_content_organization')->getValue(),
      'target_id'
    ));
    $this->assertContains((int) $valid->id(), $tids);
  }

  /**
   * Apply writes Permission Groups to both the published and draft revisions.
   *
   * Mirrors the drush moab forward-draft handling: edit access is checked
   * against the latest revision, so a pending draft must also be populated.
   */
  public function testApplyWritesToForwardDraft(): void {
    $term = $this->createTerm(
      Vocabulary::load('user_organization'),
      ['name' => 'Draft Term ' . $this->randomMachineName()]
    );
    $org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Draft Org ' . $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $published_vid = $org->getRevisionId();

    // Create a forward (unpublished) draft revision.
    $org->set('moderation_state', MassModeration::DRAFT);
    $org->setNewRevision(TRUE);
    $org->save();
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $draft_vid = $storage->getLatestRevisionId($org->id());
    $this->assertNotEquals($published_vid, $draft_vid, 'A forward draft revision exists.');

    $result = $this->importer()->apply((int) $org->id(), [(int) $term->id()]);
    $this->assertEqualsCanonicalizing(['published', 'draft'], $result['revisions']);

    foreach ([$published_vid, $draft_vid] as $vid) {
      $revision = $storage->loadRevision($vid);
      $tids = array_map('intval', array_column(
        $revision->get('field_content_organization')->getValue(),
        'target_id'
      ));
      $this->assertContains((int) $term->id(), $tids, "Revision $vid must carry the term.");
    }
  }

  /**
   * The log records imported nodes, skipped nodes, and a summary.
   */
  public function testLogRecordsImportedAndSkipped(): void {
    $importer = $this->importer();
    $uri = $importer->startLog('joe.csv', ['invalid' => 1, 'samples' => ['Row 4: "abc,12".']]);
    $this->assertNotSame('', $uri);

    $importer->logResult($uri, [
      'nid' => 10,
      'status' => OrgMappingImporter::IMPORTED,
      'tids' => [55, 66],
      'invalid_tids' => [],
      'revisions' => ['published', 'draft'],
    ]);
    $importer->logResult($uri, [
      'nid' => 20,
      'status' => OrgMappingImporter::SKIPPED,
      'reason' => 'not an organization page',
    ]);
    $importer->finishLog($uri, 1, 1);

    $log = file_get_contents($uri);
    unlink($uri);

    $this->assertStringContainsString('Source file: joe.csv', $log);
    $this->assertStringContainsString('Row 4: "abc,12".', $log);
    $this->assertStringContainsString('Node 10: IMPORTED terms [55, 66] to published + draft', $log);
    $this->assertStringContainsString('Node 20: SKIPPED — not an organization page', $log);
    $this->assertStringContainsString('Organization pages updated: 1', $log);
  }

}
