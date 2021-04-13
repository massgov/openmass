<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\file\Entity\File;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Service Details translation tests.
 */
class DocumentTranslationTest extends ExistingSiteBase {

  use MediaCreationTrait;

  /**
   * {@inheritdoc}
   */
  public function getMedia(): EntityInterface {
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
    ]);
    // Create a "Llama" media item.
    file_put_contents('public://llama-43.txt', 'Test');
    $file = File::create([
      'uri' => 'public://llama-43.txt',
    ]);

    $media = $this->createMedia([
      'bundle' => 'document',
      'title' => 'Test Document',
      'field_organizations' => [$org_node],
      'field_upload_file' => [$file],
      'moderation_state' => 'published',
    ]);

    $langcodes = \Drupal::languageManager()->getLanguages();
    unset($langcodes['en']);
    $langcode = array_rand($langcodes);
    $translation = $this->createMedia([
      'bundle' => 'document',
      'title' => 'Test Document',
      'field_organizations' => [$org_node],
      'field_upload_file' => [$file],
      'field_media_english_version' => [$media],
      'moderation_state' => 'published',
      'langcode' => $langcode,
    ]);

    return $translation;
  }

  /**
   * Retrieve the href value for translated link.
   */
  public function testHasTranslation() {
    $this->loggedInUser = TRUE;
    $entity = $this->getMedia();
    $this->drupalGet($entity->toUrl());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Entity page was loadable');
    $page = $this->getSession()->getPage();
    $element = $page->find('css', '.ma__listing-table__container')->getText();
    $this->assertContains($entity->language()->getName(), $element);
  }

}
