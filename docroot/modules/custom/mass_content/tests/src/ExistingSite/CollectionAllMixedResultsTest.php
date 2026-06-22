<?php

namespace Drupal\Tests\mass_content\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\taxonomy\Entity\Vocabulary;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Tests collection_all view with mixed node and media results.
 *
 * @group existing-site
 */
class CollectionAllMixedResultsTest extends MassExistingSiteBase {

  use MediaCreationTrait;

  /**
   * Tests that collection pages render both nodes and media documents.
   */
  public function testMixedNodeAndMediaResultsOnCollectionAllView() {
    $term_name = $this->randomMachineName();
    $url = 'test-mixed-' . time();
    $node_title = 'CollectionAllMixedNode ' . $this->randomMachineName();
    $media_title = 'CollectionAllMixedMedia ' . $this->randomMachineName();

    $collection_term = $this->createTerm(Vocabulary::load('collections'), [
      'name' => $term_name,
      'field_url_name' => $url,
    ]);

    $this->createNode([
      'type' => 'service_page',
      'title' => $node_title,
      'field_collections' => $collection_term->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->createDocumentMedia($media_title, $collection_term->id());

    $this->drupalGet('/collections/' . $url);
    $this->assertEquals(200, $this->getSession()->getStatusCode());
    $this->assertSession()->pageTextContains($node_title);
    $this->assertSession()->pageTextContains($media_title);
  }

  /**
   * Creates a published document media item in a collection.
   */
  private function createDocumentMedia(string $title, int $collection_tid): void {
    $destination = 'public://' . $this->randomMachineName(12) . '.txt';
    $file = File::create([
      'uri' => $destination,
    ]);
    $file->setPermanent();
    $file->save();

    $src = 'core/tests/Drupal/Tests/Component/FileCache/Fixtures/llama-23.txt';
    \Drupal::service('file_system')->copy($src, $destination, TRUE);

    $this->createMedia([
      'bundle' => 'document',
      'title' => $title,
      'field_title' => $title,
      'field_collections' => $collection_tid,
      'field_upload_file' => [
        'target_id' => $file->id(),
      ],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

}
