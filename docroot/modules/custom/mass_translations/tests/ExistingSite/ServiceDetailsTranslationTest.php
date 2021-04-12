<?php

namespace Drupal\Tests\mass_metatag\ExistingSite;

use Behat\Mink\Element\DocumentElement;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageManager;
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
    $langcodes = \Drupal::languageManager()->getLanguages();
    unset($langcodes['en']);
    $langcode = array_rand($langcodes);
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
    $translation = $this->createNode([
      'type' => 'service_details',
      'title' => 'Test Service Details',
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
   * Retrieve the href value for translated link.
   */
  public function testHasTranslationLink() {
    $this->loggedInUser = TRUE;
    $entity = $this->getContent();
    $this->drupalGet($entity->toUrl());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Entity page was loadable');
    $page = $this->getSession()->getPage();

    if ($element = $page->find('css', 'link[hreflang="' . $entity->language()->getId() . '"]')) {
      return $element->getAttribute('href');
    }
    throw new \Exception(sprintf('No translation link was found'));
  }

  /**
   * Retrieve the href value for default translated link.
   */
  public function testHasDefaultTranslationLink() {
    $this->loggedInUser = TRUE;
    $entity = $this->getContent();
    $this->drupalGet($entity->toUrl());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Entity page was loadable');
    $page = $this->getSession()->getPage();

    if ($element = $page->find('css', 'link[hreflang="x-default"]')) {
      return $element->getAttribute('href');
    }
    throw new \Exception(sprintf('No translation link was found'));
  }

}
