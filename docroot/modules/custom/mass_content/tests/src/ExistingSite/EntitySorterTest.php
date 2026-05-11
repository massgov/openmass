<?php

namespace Drupal\Tests\mass_content\ExistingSite;

use Drupal\mass_content\EntitySorter;
use Drupal\node\Entity\Node;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Tests EntitySorter.
 *
 * @group existing-site
 */
class EntitySorterTest extends MassExistingSiteBase {

  use MediaCreationTrait;

  private array $entitiesForSorting;
  private array $expectedIndexesAsc;
  private array $expectedIndexesDesc;

  /**
   * The class to test.
   *
   * @var \Drupal\mass_content\EntitySorter
   */
  private $entitySorter;

  /**
   * Create the user.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entitySorter = new EntitySorter();
    $this->entitiesForSorting = $this->createEntitiesForSorting();
    $this->expectedIndexesAsc = [1, 7, 8, 11, 5, 3, 4, 9, 12, 6, 10, 2, 13];
  }

  /**
   * Generates a title with a number to make it easy to check the sort resuts.
   */
  private function generateTitle($title) {
    static $cont = 0;
    $cont++;
    return "$cont $title";
  }

  /**
   * EntitySorter expects entities to be wrapped.
   */
  private function wrapEntity($entity) {
    $object = new \stdClass();
    $object->entity = $entity;
    return $object;
  }

  /**
   * Creates a node with a specified created value.
   */
  private function createNodeWithSpecificCreatedValue($type, $date) {
    $title = $this->generateTitle(__FUNCTION__ . ' - ' . $date);
    $date = \strtotime($date);

    $entity = $this->createNode([
      'title' => $title,
      'type' => $type,
      'created' => $date,
      'status' => 1,
    ]);
    return $this->wrapEntity($entity);
  }

  /**
   * Creates a node with a specified field_date_published value.
   */
  private function createNodeWithFieldDatePublished($type, $date) {
    $entity = $this->createNode([
      'title' => $this->generateTitle(__FUNCTION__ . ' - ' . $date),
      'type' => $type,
      'field_date_published' => $date,
      'status' => 1,
    ]);
    return $this->wrapEntity($entity);
  }

  /**
   * Creates a node with a specified field_info_details_last_updated value.
   */
  private function createNodeWithFieldInfoDetailsLastUpdated($type, $date) {

    $entity = $this->createNode([
      'title' => $this->generateTitle(__FUNCTION__ . ' - ' . $date),
      'type' => $type,
      'field_info_details_last_updated' => $date,
      'status' => 1,
    ]);
    return $this->wrapEntity($entity);
  }

  /**
   * Creates a media with a specified field_start_date value.
   */
  private function createMediaWithFieldStartDate($start_date) {

    $title = $this->generateTitle(__FUNCTION__ . ' - ' . $start_date);

    $entity = $this->createMedia([
      'field_title' => $title,
      'title' => $title,
      'bundle' => 'document',
      'field_upload_file' => [
        'target_id' => 777,
      ],
      'field_start_date' => $start_date,
      'status' => 1,
      'moderation_state' => 'published',
    ]);

    return $this->wrapEntity($entity);
  }

  /**
   * Gets the indexes from the created entities (based on the title).
   */
  private function getIndexesFromEntities($entities) {
    $indexes = [];
    foreach ($entities as $entity) {
      $title = $entity->entity instanceof Node ?
        $entity->entity->label() : $entity->entity->title;
      $indexes[] = explode(' ', $title)[0];
    }
    return $indexes;
  }

  /**
   * Creates multiple entities with date, unsorted.
   */
  private function createEntitiesForSorting() {
    static $entities = [];
    if ($entities) {
      return $entities;
    }

    $entities = [];

    $entities[] = $this->createNodeWithSpecificCreatedValue('curated_list', '2000-07-07');
    $entities[] = $this->createNodeWithSpecificCreatedValue('curated_list', '2025-12-06');
    $entities[] = $this->createNodeWithSpecificCreatedValue('curated_list', '2013-06-01');

    $entities[] = $this->createNodeWithFieldInfoDetailsLastUpdated('info_details', '2014-03-09');
    $entities[] = $this->createNodeWithFieldInfoDetailsLastUpdated('info_details', '2012-12-02');
    $entities[] = $this->createNodeWithFieldInfoDetailsLastUpdated('info_details', '2020-01-28');

    $entities[] = $this->createMediaWithFieldStartDate('2010-02-04');
    $entities[] = $this->createMediaWithFieldStartDate('2011-03-24');
    $entities[] = $this->createMediaWithFieldStartDate('2015-12-19');

    $entities[] = $this->createNodeWithFieldDatePublished('advisory', '2021-05-25');
    $entities[] = $this->createNodeWithFieldDatePublished('binder', '2012-02-12');
    $entities[] = $this->createNodeWithFieldDatePublished('decision', '2017-04-13');
    $entities[] = $this->createNodeWithFieldDatePublished('rules', '2099-04-01');

    return $entities;
  }

  /**
   * Ensure sorting ASC works as expected.
   */
  public function testSortAsc() {
    $this->entitySorter->sortEntities($this->entitiesForSorting, 'asc');
    $indexes = $this->getIndexesFromEntities($this->entitiesForSorting);
    $this->assertEquals($indexes, $this->expectedIndexesAsc, "Sort ASC is not correct");
  }

  /**
   * Ensure sorting DESC works as expected.
   */
  public function testSortDesc() {
    $this->entitySorter->sortEntities($this->entitiesForSorting, 'desc');
    $indexes = $this->getIndexesFromEntities($this->entitiesForSorting);
    $this->assertEquals($indexes, array_reverse($this->expectedIndexesAsc), "Sort DESC is not correct");
  }

  /**
   * Ensure date sorts preserves the order if dates are the same.
   */
  public function testOrderIsPreservedWhenDatesAreEqual() {
    $same_date = '2022-01-01';

    $this->entitiesForSorting = [];
    $this->entitiesForSorting[] = $this->createMediaWithFieldStartDate($same_date);
    $this->entitiesForSorting[] = $this->createNodeWithSpecificCreatedValue('curated_list', $same_date);
    $this->entitiesForSorting[] = $this->createNodeWithFieldDatePublished('advisory', $same_date);

    $this->entitySorter->sortEntities($this->entitiesForSorting, 'asc');
    $indexes = $this->getIndexesFromEntities($this->entitiesForSorting);

    $this->assertEquals($indexes, [14, 15, 16], "Order is not preserved when dates are equal");
  }

}
