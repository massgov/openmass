<?php

namespace Drupal\Tests\mass_content\ExistingSite;

use Drupal\taxonomy\Entity\Vocabulary;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;

/**
 * Verifies the Collection options of the Collection Search paragraph.
 *
 * @group mass_content
 */
class CollectionReferenceSelectionTest extends MassExistingSiteBase {

  use TaxonomyCreationTrait;

  /**
   * Options list top-level Collections, labeled with the name only.
   *
   * The selection runs the entity_reference display of the
   * collection_top_level_terms View, whose default display holds the columns
   * of the admin table. The option labels must not include those columns.
   */
  public function testOptionsUseCollectionNameOnly(): void {
    $vocabulary = Vocabulary::load('collections');
    $token = $this->randomMachineName();
    $name = 'Reference collection ' . $token;
    $collection = $this->createTerm($vocabulary, [
      'name' => $name,
      'field_url_name' => 'reference-collection-' . strtolower($token),
    ]);
    $this->createTerm($vocabulary, [
      'name' => 'Reference topic ' . $token,
      'parent' => [['target_id' => $collection->id()]],
    ]);

    $author = $this->createUser();
    $author->addRole('author');
    $author->activate();
    $author->save();

    $field = $this->container->get('entity_field.manager')
      ->getFieldDefinitions('paragraph', 'collection_search')['field_collection'];
    $account_switcher = $this->container->get('account_switcher');
    $account_switcher->switchTo($author);
    try {
      $options = $this->container->get('plugin.manager.entity_reference_selection')
        ->getSelectionHandler($field)
        ->getReferenceableEntities($token, 'CONTAINS');
    }
    finally {
      $account_switcher->switchBack();
    }

    $this->assertSame([(int) $collection->id()], array_map('intval', array_keys($options['collections'] ?? [])),
      'Only the top-level Collection is offered, not its topic.');
    $this->assertSame($name, trim(strip_tags((string) $options['collections'][$collection->id()])));
  }

}
