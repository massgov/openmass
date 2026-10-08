<?php

namespace Drupal\Tests\mass_fields\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Tests searching, filtering and reporting by SME / Content Owner.
 *
 * Covers the ticket asking teams to find pages and documents by owner, and
 * to have the owner available in the internal reports and their exports.
 */
class SmeContentOwnerReportingTest extends MassExistingSiteBase {

  use MediaCreationTrait;

  private const COLUMN_LABEL = 'Subject Matter Expert / Content Owner';
  private const FILTER_ID = 'field_sme_content_owner_target_id';

  /**
   * The owner whose content the tests look for.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  private TermInterface $owner;

  /**
   * An unrelated owner, used to prove the filter actually narrows results.
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
    $suffix = $this->randomMachineName(8);
    $this->owner = $this->createTerm($vocabulary, ['name' => 'Reporting Owner ' . $suffix, 'langcode' => 'en']);
    $this->otherOwner = $this->createTerm($vocabulary, ['name' => 'Unrelated Owner ' . $suffix, 'langcode' => 'en']);
    $this->drupalLogin($this->createUser([], NULL, TRUE));
  }

  /**
   * Pages can be found by their owner, and others are filtered out.
   */
  public function testPagesCanBeSearchedByOwner(): void {
    $mine = $this->createOwnedNode($this->owner);
    $theirs = $this->createOwnedNode($this->otherOwner);

    $this->visit('/admin/advsearch/page?' . self::FILTER_ID . '=' . urlencode('"' . $this->owner->label() . '"'));

    $this->assertEquals(200, $this->getSession()->getStatusCode());
    $this->assertSession()->pageTextContains($mine->label());
    $this->assertSession()->pageTextNotContains($theirs->label());
  }

  /**
   * Documents can be found by their owner too.
   */
  public function testDocumentsCanBeSearchedByOwner(): void {
    $mine = $this->createOwnedDocument($this->owner);
    $theirs = $this->createOwnedDocument($this->otherOwner);

    $this->visit('/admin/ma-dash/documents-advanced-search?' . self::FILTER_ID . '=' . urlencode('"' . $this->owner->label() . '"'));

    $this->assertEquals(200, $this->getSession()->getStatusCode());
    $this->assertSession()->pageTextContains($mine->label());
    $this->assertSession()->pageTextNotContains($theirs->label());
  }

  /**
   * The owner column is on the report page itself, not only in its export.
   *
   * @dataProvider reportPageProvider
   */
  public function testReportPagesShowTheOwnerColumn(string $path): void {
    $this->visit($path);

    $this->assertEquals(200, $this->getSession()->getStatusCode(), "$path did not load.");
    $this->assertSession()->pageTextContains(self::COLUMN_LABEL);
  }

  /**
   * Report pages carrying the owner column.
   */
  public static function reportPageProvider(): array {
    return [
      'advanced search' => ['/admin/advsearch/page'],
      'documents advanced search' => ['/admin/ma-dash/documents-advanced-search'],
      'content performance' => ['/admin/content/performance'],
      'all documents' => ['/admin/ma-dash/documents'],
      'editoria11y pages' => ['/admin/reports/editoria11y/export/pages'],
      'accessibility report for authors' => ['/admin/ma-dash/report/accessibility-report-for-authors'],
    ];
  }

  /**
   * Every report that has to carry the owner exposes a filter for it.
   *
   * @dataProvider filteredViewProvider
   */
  public function testReportsExposeAnOwnerFilter(string $view_id, string $display_id): void {
    $view = \Drupal::service('entity_type.manager')->getStorage('view')->load($view_id);
    $this->assertNotNull($view, "The $view_id view is missing.");

    $filters = $this->displayOption($view, $display_id, 'filters');
    $this->assertArrayHasKey(self::FILTER_ID, $filters, "No owner filter on $view_id:$display_id.");
    $this->assertTrue((bool) $filters[self::FILTER_ID]['exposed'], "The owner filter on $view_id:$display_id is not exposed.");
    $this->assertSame(self::COLUMN_LABEL, $filters[self::FILTER_ID]['expose']['label']);
  }

  /**
   * Views whose users must be able to filter by owner.
   */
  public static function filteredViewProvider(): array {
    return [
      'advanced search' => ['advancedsearch', 'page_1'],
      'documents advanced search' => ['adv_srch_documents', 'page_1'],
      'content performance' => ['content_performance', 'page_1'],
      'all documents' => ['all_documents', 'page_1'],
      'editoria11y pages' => ['ed11y_export', 'pages'],
      'accessibility report for authors' => ['accessibility_report_for_authors', 'page_1'],
    ];
  }

  /**
   * The CSV exports carry the owner as well, for offline routing of work.
   *
   * @dataProvider exportDisplayProvider
   */
  public function testExportsCarryTheOwnerColumn(string $view_id, string $display_id): void {
    $view = \Drupal::service('entity_type.manager')->getStorage('view')->load($view_id);
    $this->assertNotNull($view, "The $view_id view is missing.");

    $fields = $this->displayOption($view, $display_id, 'fields');
    $this->assertArrayHasKey('field_sme_content_owner', $fields, "No owner column in the $view_id:$display_id export.");
    $this->assertSame(self::COLUMN_LABEL, $fields['field_sme_content_owner']['label']);
  }

  /**
   * Exports that must include the owner.
   */
  public static function exportDisplayProvider(): array {
    return [
      'advanced search export' => ['advancedsearch', 'data_export_1'],
      'documents advanced search export' => ['adv_srch_documents', 'data_export_1'],
      'content performance export' => ['content_performance', 'data_export_1'],
      'editoria11y pages export' => ['ed11y_export', 'data_export_pages'],
    ];
  }

  /**
   * Reads a display option, falling back to the default display.
   */
  private function displayOption($view, string $display_id, string $key): array {
    $display = $view->getDisplay($display_id);
    if (isset($display['display_options'][$key])) {
      return $display['display_options'][$key];
    }
    return $view->getDisplay('default')['display_options'][$key] ?? [];
  }

  /**
   * Creates a published page owned by the given term.
   */
  private function createOwnedNode(TermInterface $owner): NodeInterface {
    return $this->createNode([
      'type' => 'advisory',
      'title' => 'Owned page ' . $this->randomMachineName(10),
      'field_sme_content_owner' => [$owner->id()],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

  /**
   * Creates a published document owned by the given term.
   */
  private function createOwnedDocument(TermInterface $owner): MediaInterface {
    $destination = 'public://' . uniqid('sme-owner-', TRUE) . '.txt';
    $file = File::create(['uri' => $destination]);
    $file->setPermanent();
    $file->save();
    file_put_contents(\Drupal::service('file_system')->realpath($destination), 'Owner reporting fixture.');

    return $this->createMedia([
      'title' => 'Owned document ' . $this->randomMachineName(10),
      'bundle' => 'document',
      'field_upload_file' => ['target_id' => $file->id()],
      'field_sme_content_owner' => [$owner->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

}
