<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use weitzman\DrupalTestTraits\ExistingSiteBase;
use Drupal\file\Entity\File;
use weitzman\DrupalTestTraits\Entity\MediaCreationTrait;

/**
 * Robots metatag tests.
 */
class RobotsMetatagTest extends ExistingSiteBase {

  use MediaCreationTrait;

  /**
   * Test media entity has robots metatag.
   */
  public function testMediaEntityHasRobotsMetatag() {

    file_put_contents('public://llama-robots-test.txt', 'Test');
    $file = File::create([
      'uri' => 'public://llama-robots-test.txt',
    ]);
    $media = $this->createMedia([
      'title' => 'Robot Llama',
      'bundle' => 'document',
      'field_upload_file' => [$file],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    array_pop($this->cleanupEntities);

    $session = $this->getSession();
    $session->visit('/media/' . $media->id());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Media entity is loadable');
    $content = $session->getPage()->find('xpath', '//meta[@name="robots"]')->getAttribute('content');
    $this->assertContains('noindex', $content);
    $this->assertContains('follow', $content);
  }

}
