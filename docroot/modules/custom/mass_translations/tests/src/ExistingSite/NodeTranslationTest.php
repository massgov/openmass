<?php

namespace Drupal\Tests\mass_translations\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\mass_content_moderation\MassModeration;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Service Details translation tests.
 */
class NodeTranslationTest extends MassExistingSiteBase {

  private $editor;
  private $orgNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->createUser();
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
      'type' => 'info_details',
      'title' => 'Test Info Details',
      'field_organizations' => [$this->orgNode],
      'moderation_state' => MassModeration::PUBLISHED,
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
      'type' => 'info_details',
      'title' => 'Test Service Details Translation',
      'langcode' => $langcode,
      'field_english_version' => [$node],
      'field_organizations' => [$this->orgNode],
      'moderation_state' => MassModeration::PUBLISHED,
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
    $this->assertEquals($element, $entity->toUrl()->setOption('language', $entity->language())->setAbsolute()->toString());
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
  protected function tearDown(): void {
    parent::tearDown();
    $this->editor = NULL;
    $this->orgNode = NULL;
  }

}
