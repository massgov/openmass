<?php

namespace Drupal\Tests\mass_translations\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Service Details translation tests.
 */
class ServiceDetailsTranslationTest extends ExistingSiteBase {

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
    ]);
    $node = $this->createNode([
      'type' => 'service_details',
      'title' => 'Test Service Details',
      'field_service_detail_lede' => [
        'value' => 'Test Lede',
        'format' => 'basic_html',
      ],
      'field_organizations' => [$org_node],
      'moderation_state' => 'published',
    ]);

    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslation($node): ContentEntityInterface {
    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Test Org Page',
    ]);
    $langcodes = \Drupal::languageManager()->getLanguages();
    unset($langcodes['en']);
    $langcode = array_rand($langcodes);
    $translation = $this->createNode([
      'type' => 'service_details',
      'title' => 'Test Service Details Translation',
      'langcode' => $langcode,
      'field_english_version' => [$node],
      'field_service_detail_lede' => [
        'value' => 'Test Lede',
        'format' => 'basic_html',
      ],
      'field_organizations' => [$org_node],
      'moderation_state' => 'published',
    ]);

    return $translation;
  }

  /**
   * Check for the default link value on translated content.
   */
  public function testHasDefaultTranslationLink() {
    $this->loggedInUser = TRUE;
    $entity = $this->getContent();
    $translation = $this->getTranslation($entity);
    $this->drupalGet($translation->toUrl());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Entity page was loadable');
    $page = $this->getSession()->getPage();
    $element = $page->find('css', 'link[hreflang="x-default"]')->getAttribute('href');
    $this->assertEqual($element, $entity->toUrl()->setOption('language', $entity->language())->setAbsolute()->toString());
  }

  /**
   * Check for the translated hreflang value on non-translated content.
   */
  public function testHasTranslationLink() {
    $this->loggedInUser = TRUE;
    $entity = $this->getContent();
    $translation = $this->getTranslation($entity);
    $this->drupalGet($entity->toUrl());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Entity page was loadable');
    $page = $this->getSession()->getPage();
    $element = $page->find('css', 'link[hreflang="' . $translation->language()->getId() . '"]');
    $this->assertNotEmpty($element, 'No hreflang value found for translation on the English page.');
  }

}
