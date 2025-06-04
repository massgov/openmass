<?php

namespace Drupal\Tests\mass_translations\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\user\UserInterface;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Service Details translation tests.
 */
class ServiceCardsTranslationTest extends MassExistingSiteBase {

  protected static array $uncacheableDynamicPagePatterns = [
    'admin/.*',
    '/*edit.*',
    'user/logout.*',
    'node/.*/translations',
    'user/reset/.*',
  ];

//  private $editor;
//  private $orgNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

//    $user = $this->createUser();
//    $user->addRole('editor');
//    $user->activate();
//    $user->save();
//    $this->editor = $user;

//    $this->orgNode = $this->createNode([
//      'type' => 'org_page',
//      'title' => $this->randomMachineName(),
//      'status' => 1,
//      'moderation_state' => 'published',
//    ]);
  }

  private function getUser(string $role): UserInterface {
    $user = $this->createUser();
    $user->addRole($role);
    $user->activate();
    $user->save();
    return $user;
  }

  /**
   * {@inheritdoc}
   */
  public function getContent($bundle = 'api_service_card'): ContentEntityInterface {
    $node = $this->createNode([
      'type' => $bundle,
      'title' => 'Test ' . $bundle,
      'field_api_serv_card_description' => $this->getRandomGenerator()->string(30),
      'field_api_serv_card_machine_name' => $this->getRandomGenerator()->string(5),
      'field_api_srv_card_tenant' => 'personal',
      'field_environment' => 'production',
      'field_api_serv_card_link' => 'https://' . $this->getRandomGenerator()->string(3) . '.com',
      'field_api_srv_card_status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function createTranslation(ContentEntityInterface $node): ContentEntityInterface {
    $langcodes = \Drupal::languageManager()->getLanguages();
    unset($langcodes['en']);
    $langcode = array_rand($langcodes);
//    dump($node);
    $translation = $node->addTranslation($langcode, [
        'title' => $node->label() . ' Translation ' . $langcode,
        'field_api_serv_card_description' => $node->field_api_serv_card_description->value . ' Translation ' . $langcode
      ],
    );


    return $translation;
  }

  /**
   * Check for the default link value and tab on translated content.
   */
//  public function testHasDefaultTranslationLink() {
//    $this->drupalLogin($this->editor);
//    $entity = $this->getContent();
//    $translation = $this->createTranslation($entity);
//    $this->drupalGet($translation->toUrl());
//    $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Entity page was loadable');
//    $page = $this->getSession()->getPage();
//    $element = $page->find('css', 'link[hreflang="x-default"]')->getAttribute('href');
//    $this->assertEquals($element, $entity->toUrl()->setOption('language', $entity->language())->setAbsolute()->toString());
//    $tabs = $page->find('css', '.primary-tabs')->getText();
//    $this->assertStringContainsString('Translations', $tabs, 'No Translations tab was found');
//  }

  /**
   * Check for the translated hreflang value and tab on non-translated content.
   *
   * @dataProvider canTranslateContentDataProvider
   */
  public function testHasTranslationLink(string $bundle, array $roles): void {
    foreach ($roles as $role) {
      $this->drupalLogin($this->getUser($role));
      $entity = $this->getContent();

      $this->drupalGet($entity->toUrl());

      $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Service Card Translation page was not loadable');
      $page = $this->getSession()->getPage();

      $tabs = $page->find('css', '.primary-tabs')->getText();
      $this->assertStringContainsString('Translate', $tabs, 'No "Translate" tab was found');

      $translation = $this->createTranslation($entity);
      $this->drupalGet($translation->toUrl());

      $tabs = $page->find('css', '.primary-tabs')->getText();
      $this->assertStringContainsString('Translate', $tabs, 'No "Translate" tab was found');
    }
  }

  /**
   * Data provider for testCanTranslateContent test.
   *
   * Return an array of roles and bundles to test.
   * Only content types which support translations are tested.
   *
   * @return array
   *   An array of roles and bundles to test.
   *
   */
  public function canTranslateContentDataProvider(): array {
    return [
      ['api_service_card', ['administrator', 'mmg_editor']],
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
   * @return void
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

      $url = $entity->toUrl('drupal:content-translation-overview');
      $this->drupalGet($url->toString());
      $this->assertEquals(200, $this->getSession()->getStatusCode(), 'Translations page was not loadable.');
      $page = $this->getSession()->getPage();
      $this->assertTrue($page->hasContent('English (Original language)'), 'Could not find the row with english node.');
      $element = $page->findLink($entity->label());
      $this->assertNotNull($element, 'Could not find the link to related translation node.');
    }
  }

  /**
   * {@inheritdoc}
   */
//  protected function tearDown(): void {
//    parent::tearDown();
//    $this->editor = NULL;
//  }

}
