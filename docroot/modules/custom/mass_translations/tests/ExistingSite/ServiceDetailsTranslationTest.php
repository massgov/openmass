<?php

namespace Drupal\Tests\mass_translations\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Service Details translation tests.
 */
class ServiceDetailsTranslationTest extends ExistingSiteBase {

  use LoginTrait;

  private $editor;
  private $orgNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = User::create(['name' => $this->randomMachineName()]);
    $user->addRole('editor');
    $user->activate();
    $user->save();
    $this->editor = $user;

    $this->orgNode = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => 'published',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getContent(): ContentEntityInterface {
    $node = $this->createNode([
      'type' => 'service_details',
      'title' => 'Test Service Details',
      'field_service_detail_lede' => [
        'value' => 'Test Lede',
        'format' => 'basic_html',
      ],
      'field_organizations' => [$this->orgNode],
      'moderation_state' => 'published',
    ]);

    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslation($node): ContentEntityInterface {
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
      'field_organizations' => [$this->orgNode],
      'moderation_state' => 'published',
    ]);

    return $translation;
  }

  /**
   * Check for the default link value and tab on translated content.
   */
  public function testHasDefaultTranslationLink() {
    $this->drupalLogin($this->editor);
    $entity = $this->getContent();
    $translation = $this->getTranslation($entity);
    $this->drupalGet($translation->toUrl());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Entity page was loadable');
    $page = $this->getSession()->getPage();
    $element = $page->find('css', 'link[hreflang="x-default"]')->getAttribute('href');
    $this->assertEqual($element, $entity->toUrl()->setOption('language', $entity->language())->setAbsolute()->toString());
    $tabs = $page->find('css', '.primary-tabs')->getText();
    $this->assertStringContainsString('Translations', $tabs, 'No Translations tab was found');
  }

  /**
   * Check for the translated hreflang value and tab on non-translated content.
   */
  public function testHasTranslationLink() {
    $this->drupalLogin($this->editor);
    $entity = $this->getContent();
    $translation = $this->getTranslation($entity);
    $this->drupalGet($entity->toUrl());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Entity page was loadable');
    $page = $this->getSession()->getPage();
    $element = $page->find('css', 'link[hreflang="' . $translation->language()->getId() . '"]');
    $this->assertNotEmpty($element, 'No hreflang value found for translation on the English page');
    $tabs = $page->find('css', '.primary-tabs')->getText();
    $this->assertStringContainsString('Translations', $tabs, 'No Translations tab was found');
  }

  /**
   * Check that the page has an aliased canonical URL.
   */
  public function testHasAliasedCanonicalLink() {
    $this->drupalLogin($this->editor);
    $entity = $this->getContent();
    $translation = $this->getTranslation($entity);
    $this->drupalGet($translation->toUrl());
    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Entity page was loadable');
    $page = $this->getSession()->getPage();
    $element = $page->find('css', 'link[rel="canonical"]')->getText();
    $this->assertStringNotContainsString('/node/', $element, 'Unaliased canonical URL found on translated page');
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    parent::tearDown();
    $this->editor = NULL;
    $this->orgNode = NULL;
  }

}
