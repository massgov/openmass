<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_views\ExistingSite;

use Drupal\Core\File\FileExists;
use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Tests the media Organization Views filter.
 *
 * @group existing-site
 */
class OrgFilterMediaTest extends MassExistingSiteBase {

  use MediaCreationTrait;

  /**
   * Two organizations return the union of their documents, each listed once.
   */
  public function testMultipleOrganizationsReturnEachDocumentOnce(): void {
    $prefix = 'DP-48656-' . $this->randomMachineName(8);
    $org_a = $this->createOrgPage($prefix . ' org a');
    $org_b = $this->createOrgPage($prefix . ' org b');
    $decoy_org = $this->createOrgPage($prefix . ' decoy org');

    $doc_a = $this->createDocument($prefix . ' doc a', [$org_a]);
    $doc_b = $this->createDocument($prefix . ' doc b', [$org_b]);
    $doc_both = $this->createDocument($prefix . ' doc both', [$org_a, $org_b]);
    $doc_decoy = $this->createDocument($prefix . ' doc decoy', [$decoy_org]);

    $view = $this->allDocumentsView([$org_a, $org_b]);
    $view->execute();
    $mids = $this->resultMids($view);

    $this->assertContains((int) $doc_a->id(), $mids);
    $this->assertContains((int) $doc_b->id(), $mids);
    $this->assertNotContains((int) $doc_decoy->id(), $mids);
    $this->assertCount(
      1,
      array_keys($mids, (int) $doc_both->id(), TRUE),
      'A document tagged with both selected organizations must be listed once.'
    );
  }

  /**
   * Loads all_documents page_1 with the Organization filter preset.
   *
   * @param \Drupal\node\NodeInterface[] $orgs
   *   The organizations to filter on.
   */
  private function allDocumentsView(array $orgs): ViewExecutable {
    $view = Views::getView('all_documents');
    $this->assertNotNull($view);
    $view->setDisplay('page_1');
    // The input-required exposed forms skip the query when no exposed input
    // is present; this test sets the filter value directly instead.
    $view->display_handler->setOption('exposed_form', ['type' => 'basic', 'options' => []]);
    $view->setItemsPerPage(0);
    $view->initHandlers();
    $this->assertArrayHasKey('media_org_filter', $view->filter);
    $view->filter['media_org_filter']->options['exposed'] = FALSE;
    $view->filter['media_org_filter']->value = array_map(
      fn (NodeInterface $org) => ['target_id' => $org->id()],
      $orgs
    );
    return $view;
  }

  /**
   * Creates a published org_page.
   */
  private function createOrgPage(string $title): NodeInterface {
    return $this->createNode([
      'type' => 'org_page',
      'title' => $title,
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

  /**
   * Creates a published document tagged with the given organizations.
   *
   * @param \Drupal\node\NodeInterface[] $orgs
   *   The organizations to tag the document with.
   */
  private function createDocument(string $title, array $orgs): MediaInterface {
    $destination = 'public://dp-48656-' . $this->randomMachineName() . '.txt';
    \Drupal::service('file_system')->saveData('test', $destination, FileExists::Replace);
    $file = File::create(['uri' => $destination]);
    $file->setPermanent();
    $file->save();
    $this->markEntityForCleanup($file);

    return $this->createMedia([
      'bundle' => 'document',
      'title' => $title,
      'field_title' => $title,
      'field_upload_file' => ['target_id' => $file->id()],
      'field_organizations' => array_map(
        fn (NodeInterface $org) => ['target_id' => $org->id()],
        $orgs
      ),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

  /**
   * Media ids present in a Views result set, duplicates included.
   *
   * @return int[]
   *   The mids.
   */
  private function resultMids(ViewExecutable $view): array {
    return array_map(fn ($row) => (int) $row->mid, $view->result);
  }

}
