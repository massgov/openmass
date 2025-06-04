<?php

namespace src\ExistingSite;

use Drupal\Component\Serialization\Json;
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
//    'jsonapi/node/api_service_card.*',
  ];

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
  private function getContent($bundle = 'api_service_card'): ContentEntityInterface {
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
   * Retrieves and creates a translation for the given content entity.
   *
   * This method adds a new translation to the provided content entity
   * in a randomly selected language (excluding English) with updated
   * title and description fields. The created translation is then saved
   * and returned.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $node
   *   The content entity for which a translation will be created.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The newly created translation of the given content entity.
   */
  private function getTranslation(ContentEntityInterface $node): ContentEntityInterface {
    $langcodes = \Drupal::languageManager()->getLanguages();
    unset($langcodes['en']);
    $langcode = array_rand($langcodes);
    $translation = $node->addTranslation($langcode, [
        'title' => $node->label() . ' Translation ' . $langcode,
        'field_api_serv_card_description' => $node->field_api_serv_card_description->value . ' Translation ' . $langcode
      ],
    );

    $translation->save();

    return $translation;
  }

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

      $translation = $this->getTranslation($entity);
      $this->drupalGet($translation->toUrl());

      $tabs = $page->find('css', '.primary-tabs')->getText();
      $this->assertStringContainsString('Translate', $tabs, 'No "Translate" tab was found');
    }
  }

  /**
   * Tests that the API correctly returns data for both the original node and its translations.
   *
   * @return void
   */
  public function testApiHasData(): void {

    $entity = $this->getContent();

    $options = [
      'query' => [
        'language_content_entity' => $entity->language()->getId(),
        'filter[id]' => $entity->uuid(),
      ],
    ];

    $api_url = Url::fromUserInput('/jsonapi/node/api_service_card');

    $payload = $this->drupalGet($api_url->toString(), $options);

    $decode_data = Json::decode($payload);
    $this->assertIsArray($decode_data, 'Incorrect response format');

    $this->assertEquals($decode_data['data'][0]['attributes']['title'], $entity->label(), 'API does not return correct title for the original node');;

    $translation = $this->getTranslation($entity);
    $options['query']['language_content_entity'] = $translation->language()->getId();
    $options['query']['filter[langcode]'] = $translation->language()->getId();

    $payload = $this->drupalGet($api_url->toString(), $options);

    $decode_data = Json::decode($payload);
    $this->assertIsArray($decode_data, 'Incorrect response format');

    $this->assertEquals($decode_data['data'][0]['attributes']['title'], $translation->label(), 'API does not return correct title for the translation');;
  }

  /**
   * Data provider for testCanTranslateContent test.
   *
   * Return an array of roles and bundles to test.
   * Only content types which support core translations are tested.
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
   * Test access to drupal:content-translation-overview router.
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

}
