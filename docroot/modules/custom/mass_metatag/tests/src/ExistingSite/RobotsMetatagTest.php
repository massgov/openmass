<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Drupal\Core\Cache\Cache;
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
    $this->assertStringContainsString('noindex', $content);
    $this->assertStringContainsString('follow', $content);
  }

  /**
   * Test overflow pages have robots metatag.
   */
  public function testOverflowPagesHaveRobotsMetatag() {

    $service_page = $this->createNode([
      'type' => 'service_page',
      'title' => 'Robots Service Page',
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $session = $this->getSession();

    // Check robots metatag on resources page.
    $session->visit($service_page->toUrl()->toString() . '/resources');
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Page loads');
    $content = $session->getPage()->find('xpath', '//meta[@name="robots"]')->getAttribute('content');
    $this->assertStringContainsString('noindex', $content);
    $this->assertStringContainsString('follow', $content);

    // Check the regular service node does NOT have a robots metatag.
    $session->visit($service_page->toUrl()->toString());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Page loads');
    $robots_metatag = $session->getPage()->find('xpath', '//meta[@name="robots"]');
    $this->assertNull($robots_metatag);

    // Now edit that service node so it is excluded from index.
    $service_page->set('search_nosnippet', FALSE)->set('search', TRUE)->save();
    $session->visit($service_page->toUrl()->toString());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Page loads');
    $robots_metatag = $session->getPage()->find('xpath', '//meta[@name="robots"]');
    $this->assertNotNull($robots_metatag);
    $content = $robots_metatag->getAttribute('content');
    $this->assertStringContainsString('noindex', $content);
    $this->assertStringContainsString('nofollow', $content);
    $this->assertStringNotContainsString('nosnippet', $content);

    // Now edit that service node so it doesn't show a snippet.
    $service_page->set('search_nosnippet', TRUE)->set('search', FALSE)->save();
    // Cache::invalidateTags(['rendered']);
    $session->visit($service_page->toUrl()->toString());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Page loads');
    $robots_metatag = $session->getPage()->find('xpath', '//meta[@name="robots"]');
    $this->assertNotNull($robots_metatag);
    $content = $robots_metatag->getAttribute('content');
    $this->assertStringNotContainsString('noindex', $content);
    $this->assertStringContainsString('nosnippet', $content);
  }

}
