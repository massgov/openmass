<?php

namespace Drupal\Tests\mass_fields\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\views\Views;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Tests the directory page listing the defined SMEs / content owners.
 */
class SmeContentOwnerDirectoryTest extends MassExistingSiteBase {

  use MediaCreationTrait;

  private const PATH = '/admin/reports/subject-matter-experts-content-owners';
  private const VIEW_ID = 'rpt_sme_content_owners';

  /**
   * An owner carrying both a page and a document.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  private TermInterface $busyOwner;

  /**
   * An owner carrying nothing, which the directory still has to list.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  private TermInterface $idleOwner;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $vocabulary = Vocabulary::load('sme_owner');
    $suffix = $this->randomMachineName(8);
    $this->busyOwner = $this->createTerm($vocabulary, ['name' => 'Directory Busy Owner & Services #' . $suffix, 'langcode' => 'en']);
    $this->idleOwner = $this->createTerm($vocabulary, ['name' => 'Directory Idle Owner ' . $suffix, 'langcode' => 'en']);
  }

  /**
   * The directory lists every owner with its page and document counts.
   */
  public function testOwnersAreListedWithTheirContentCounts(): void {
    $this->createOwnedNode();
    $this->createOwnedNode();
    $this->createOwnedDocument();

    $rows = $this->directoryRows();

    $this->assertArrayHasKey($this->busyOwner->label(), $rows, 'An owner with content is missing from the directory.');
    $this->assertArrayHasKey($this->idleOwner->label(), $rows, 'An owner without content must still be listed, so unused entries can be spotted.');

    $this->assertSame(2, $rows[$this->busyOwner->label()]['pages']);
    $this->assertSame(1, $rows[$this->busyOwner->label()]['documents'], 'Pages and documents must be counted separately, not multiplied by each other.');
    $this->assertSame(0, $rows[$this->idleOwner->label()]['pages']);
    $this->assertSame(0, $rows[$this->idleOwner->label()]['documents']);
  }

  /**
   * Only users with Mass dashboard access reach the directory.
   */
  public function testDirectoryRequiresMassDashboardPermission(): void {
    $this->assertDirectoryIsClosed('anonymous visitors');

    $this->drupalLogin($this->createUser());
    $this->assertDirectoryIsClosed('an authenticated user without Mass dashboard access');

    $this->drupalLogin($this->createUser(['use mass dashboard']));
    $this->visit(self::PATH);
    $this->assertEquals(200, $this->getSession()->getStatusCode());
    $this->assertSession()->pageTextContains($this->busyOwner->label());
  }

  /**
   * Asserts the current session cannot read the directory.
   *
   * Drupal answers an unreachable admin route with either 403 or 404; what
   * matters is that the owner names are not served.
   */
  private function assertDirectoryIsClosed(string $who): void {
    $this->visit(self::PATH);

    $this->assertContains(
      $this->getSession()->getStatusCode(),
      [403, 404],
      "The directory is readable by $who."
    );
    $this->assertStringNotContainsString(
      $this->busyOwner->label(),
      $this->getSession()->getPage()->getContent(),
      "Owner names are served to $who."
    );
  }

  /**
   * The directory is no longer served from its old public path.
   */
  public function testDirectoryIsNotPublishedPublicly(): void {
    $this->visit('/subject-matter-experts-content-owners');

    $this->assertEquals(404, $this->getSession()->getStatusCode(), 'The public directory path must be gone.');
  }

  /**
   * Page and document counts link to searches filtered by the stable term ID.
   */
  public function testOwnerCountsLinkToTheFilteredSearch(): void {
    $node = $this->createOwnedNode();
    $document = $this->createOwnedDocument();
    $this->drupalLogin($this->createUser([], NULL, TRUE));
    $this->visit(self::PATH);

    $row = $this->findDirectoryRow($this->busyOwner->label());
    $this->assertNotNull($row, 'The owner is missing from the directory.');

    $links = [
      'page count' => [$row->find('css', '.views-field-nid a'), $node->label()],
      'document count' => [$row->find('css', '.views-field-mid a'), $document->label()],
    ];

    foreach ($links as $description => [$link, $expected_label]) {
      $this->assertNotNull($link, "The $description is not a link.");

      $query = [];
      parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY) ?? '', $query);
      $this->assertSame(
        'Content owner (' . $this->busyOwner->id() . ')',
        $query['field_sme_content_owner_target_id'] ?? NULL,
        "The $description does not filter by the stable taxonomy term ID."
      );

      $this->getSession()->visit($this->getAbsoluteUrl($link->getAttribute('href')));
      $this->assertEquals(200, $this->getSession()->getStatusCode());
      $this->assertSession()->pageTextContains($expected_label);
      $this->visit(self::PATH);
      $row = $this->findDirectoryRow($this->busyOwner->label());
    }
  }

  /**
   * Finds an owner row in the rendered directory table.
   */
  private function findDirectoryRow(string $owner_name) {
    foreach ($this->getSession()->getPage()->findAll('css', 'tbody tr') as $row) {
      if (str_contains($row->getText(), $owner_name)) {
        return $row;
      }
    }

    return NULL;
  }

  /**
   * Reads the directory as a map of owner name to its counts.
   */
  private function directoryRows(): array {
    $view = Views::getView(self::VIEW_ID);
    $view->setDisplay('page_1');
    $view->setItemsPerPage(0);
    $view->preExecute();
    $view->execute();

    $rows = [];
    foreach ($view->result as $row) {
      $name = (string) $view->field['name']->getValue($row);
      $rows[$name] = [
        'pages' => (int) $view->field['nid']->getValue($row),
        'documents' => (int) $view->field['mid']->getValue($row),
      ];
    }
    $view->destroy();

    return $rows;
  }

  /**
   * Creates a published page owned by the busy owner.
   */
  private function createOwnedNode() {
    return $this->createNode([
      'type' => 'advisory',
      'title' => 'Directory page ' . $this->randomMachineName(10),
      'field_sme_content_owner' => [$this->busyOwner->id()],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

  /**
   * Creates a published document owned by the busy owner.
   */
  private function createOwnedDocument() {
    $destination = 'public://' . uniqid('sme-directory-', TRUE) . '.txt';
    $file = File::create(['uri' => $destination]);
    $file->setPermanent();
    $file->save();
    file_put_contents(\Drupal::service('file_system')->realpath($destination), 'Directory fixture.');

    return $this->createMedia([
      'title' => 'Directory document ' . $this->randomMachineName(10),
      'bundle' => 'document',
      'field_upload_file' => ['target_id' => $file->id()],
      'field_sme_content_owner' => [$this->busyOwner->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

}
