<?php

namespace Drupal\Tests\mass_translations\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;
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
  public function getContent($bundle = 'info_details'): ContentEntityInterface {
    $node = $this->createNode([
      'type' => $bundle,
      'title' => 'Test ' . $bundle,
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
   * Data provider for testCanTranslateContent test.
   *
   * Return an array of roles and bundles to test.
   * Only content types which support translations are tested.
   *
   * @return array
   *   An array of roles and bundles to test.
   */
  public function canTranslateContentDataProvider(): array {
    return [
      ['news', ['administrator', 'editor']],
      ['info_details', ['administrator', 'editor']],
      ['form_page', ['administrator', 'editor']],
      ['curated_list', ['administrator', 'editor']],
      ['campaign_landing', ['administrator', 'editor']],
      ['alert', ['administrator', 'editor']],
      ['how_to_page', ['administrator', 'editor']],
      ['service_page', ['administrator', 'editor']],
    ];
  }

  /**
   * Test access to mass_translations.controller_translations router.
   *
   * Tests whether translation tab of a specific translatable bundle can
   * be accessed by users with specified roles.
   *
   * @param string $bundle
   *   The content type bundle to test.
   * @param array $roles
   *   An array of user roles to test translation permissions against.
   *
   * @dataProvider canTranslateContentDataProvider
   */
  public function testCanAccessTranslationsTab(string $bundle, array $roles): void {
    foreach ($roles as $role) {
      $user = $this->createUser();
      $user->addRole($role);
      $user->activate();
      $user->save();
      $this->drupalLogin($user);

      $entity = $this->getContent($bundle);
      $translation = $this->getTranslation($entity);
      $url = Url::fromRoute('mass_translations.controller_translations', ['node' => $entity->id()]);
      $this->drupalGet($url->toString());
      $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Translations page was not loadable.');
      $page = $this->getSession()->getPage();
      $element = $page->findLink($translation->label());
      $this->assertNotNull($element, 'Could not find the link to the original node on the translations page');
      $element = $page->findLink($entity->label());
      $this->assertNotNull($element, 'Could not find the link to related translation node.');
    }
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
