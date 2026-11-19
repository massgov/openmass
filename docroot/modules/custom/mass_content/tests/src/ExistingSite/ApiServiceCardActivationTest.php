<?php

namespace Drupal\Tests\mass_content\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Core\Form\FormState;
use Drupal\mass_content\Hook\ApiServiceCardFormHooks;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;
use MassGov\Dtt\MassExistingSiteBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Service publication, dashboard visibility and APC are independent controls.
 */
#[Group('mass_content')]
class ApiServiceCardActivationTest extends MassExistingSiteBase {

  private const APC_FIELD = 'field_api_srv_card_apc_enabled';

  /**
   * New service cards must be explicitly activated by an editor.
   */
  public function testNewCardsDefaultToInactive(): void {
    $card = $this->createNode(['type' => 'api_service_card']);
    $this->assertFalse($card->isPublished());
    $this->assertSame('Active?', $card->getFieldDefinition('status')->getLabel());
    $this->assertTrue($card->hasField(self::APC_FIELD));
    $this->assertSame('0', (string) $card->get(self::APC_FIELD)->value);
    $this->assertFalse($card->getFieldDefinition(self::APC_FIELD)->isTranslatable());
  }

  /**
   * Saving a card must honor status without changing its activation flags.
   */
  public function testPublicationCanBeChangedIndependently(): void {
    $card = $this->createCard(TRUE, FALSE);
    $this->assertTrue($card->isPublished());
    $card->set('moderation_state', MassModeration::UNPUBLISHED)->save();
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$card->id()]);
    $card = $storage->load($card->id());
    $this->assertFalse($card->isPublished());
    $this->assertFalse((bool) $card->get('field_api_srv_card_status')->value);
    $this->assertFalse((bool) $card->get(self::APC_FIELD)->value);
    $card->set('moderation_state', MassModeration::PUBLISHED)->save();
    $storage->resetCache([$card->id()]);
    $this->assertTrue($storage->load($card->id())->isPublished());
  }

  /**
   * Anonymous API clients receive active cards even when dashboard-hidden.
   */
  public function testJsonApiUsesPublicationStatus(): void {
    $card = $this->createCard(TRUE, FALSE);
    $data = $this->getApiCard($card);
    $this->assertCount(1, $data);
    $this->assertTrue($data[0]['attributes']['status']);
    $this->assertFalse($data[0]['attributes']['field_api_srv_card_status']);
    $this->assertFalse($data[0]['attributes'][self::APC_FIELD]);
    $card->set(self::APC_FIELD, 1)->save();
    $this->assertTrue($this->getApiCard($card)[0]['attributes'][self::APC_FIELD]);
    $card->set('moderation_state', MassModeration::UNPUBLISHED)->save();
    $this->assertSame([], $this->getApiCard($card));
    $this->drupalGet('/jsonapi/node/api_service_card/' . $card->uuid());
    $this->assertSame(404, $this->getSession()->getStatusCode());
  }

  /**
   * The non-translatable APC field is shared with translated API responses.
   */
  public function testTranslationSharesProfileCollectionFlag(): void {
    $card = $this->createCard(TRUE, TRUE);
    $card->addTranslation('es', [
      'title' => 'Servicio de prueba',
      'field_api_serv_card_description' => ['value' => 'Descripción de prueba', 'format' => 'plain_text'],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $card->save();
    $this->assertFalse($this->getApiCard($card, 'es')[0]['attributes'][self::APC_FIELD]);
    $card->set(self::APC_FIELD, 1)->save();
    $this->assertTrue($this->getApiCard($card, 'es')[0]['attributes'][self::APC_FIELD]);
    $this->assertTrue($this->getApiCard($card)[0]['attributes'][self::APC_FIELD]);
    $card->set('moderation_state', MassModeration::UNPUBLISHED)->save();
    $this->assertSame([], $this->getApiCard($card));
    $card->getTranslation('es')->set('moderation_state', MassModeration::UNPUBLISHED)->save();
    $this->assertSame([], $this->getApiCard($card, 'es'));
  }

  /**
   * Active listing filters follow publication rather than dashboard visibility.
   */
  public function testListingFiltersByPublicationStatus(): void {
    $active = $this->createCard(TRUE, FALSE);
    $inactive = $this->createCard(FALSE, TRUE);
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate()->save();
    $this->drupalLogin($admin);
    foreach ([1 => $active, 0 => $inactive] as $status => $expected) {
      $this->drupalGet('/admin/ma-dash/api-service-cards', [
        'query' => [
          'field_environment_value' => 'All',
          'field_api_srv_card_tenant_value' => 'All',
          'title' => 'DP-49259 test',
          'status' => (string) $status,
        ],
      ]);
      $this->assertSame(200, $this->getSession()->getStatusCode());
      $this->assertSession()->pageTextContains($expected->label());
      $opposite = $status ? $inactive : $active;
      $this->assertSession()->pageTextNotContains($opposite->label());
    }
  }

  /**
   * Deploy initialization fills empty APC values and preserves explicit values.
   */
  public function testExistingCardsReceiveFalseWithoutOverwritingEnabledCards(): void {
    \Drupal::moduleHandler()->loadInclude('mass_content', 'deploy.php');
    $this->assertTrue(function_exists('mass_content_deploy_service_card_apc_defaults'));
    $empty = $this->createCard(TRUE, FALSE);
    $empty->addTranslation('es', [
      'title' => 'Servicio existente',
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $empty->save();
    // Simulate a card that predates field installation.
    \Drupal::database()->delete('node__field_api_srv_card_apc_enabled')
      ->condition('entity_id', $empty->id())
      ->execute();
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$empty->id()]);
    $legacy = \Drupal::entityTypeManager()->getStorage('node')->load($empty->id());
    $this->assertTrue($legacy->get(self::APC_FIELD)->isEmpty());
    $enabled = $this->createCard(FALSE, TRUE);
    $enabled->set(self::APC_FIELD, 1)->save();
    $sandbox = ['current' => (int) $empty->id() - 1, 'progress' => 0, 'max' => 2];
    do {
      mass_content_deploy_service_card_apc_defaults($sandbox);
    } while ($sandbox['#finished'] < 1);
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$empty->id(), $enabled->id()]);
    $empty = $storage->load($empty->id());
    $enabled = $storage->load($enabled->id());
    $this->assertSame('0', (string) $empty->get(self::APC_FIELD)->value);
    $this->assertSame('0', (string) $empty->getTranslation('es')->get(self::APC_FIELD)->value);
    $this->assertTrue($empty->isPublished());
    $this->assertFalse((bool) $empty->get('field_api_srv_card_status')->value);
    $this->assertSame('1', (string) $enabled->get(self::APC_FIELD)->value);
    $this->assertFalse($enabled->isPublished());
    $sandbox = ['current' => (int) $empty->id() - 1, 'progress' => 0, 'max' => 2];
    mass_content_deploy_service_card_apc_defaults($sandbox);
    $storage->resetCache([$enabled->id()]);
    $this->assertSame('1', (string) $storage->load($enabled->id())->get(self::APC_FIELD)->value);
  }

  /**
   * MMG editors retain publication control above the service configuration tabs.
   */
  public function testMmgEditorCanUseActiveCheckboxAboveTabs(): void {
    $editor = $this->createUser();
    $editor->addRole('mmg_editor');
    $editor->activate()->save();
    $card = $this->createNode(['type' => 'api_service_card']);
    $this->assertFalse($card->get('status')->access('edit', $editor));
    $other = $this->createNode(['type' => 'info_details']);
    $this->assertFalse($other->get('status')->access('edit', $editor));
    $this->drupalLogin($editor);
    $this->drupalGet('/node/add/api_service_card');
    $this->assertSession()->fieldExists('Active?');
    $html = $this->getSession()->getPage()->getHtml();
    $status_position = strpos($html, 'name="status[value]"');
    $tabs_position = strpos($html, 'id="edit-group-card-meta"');
    $this->assertNotFalse($status_position);
    $this->assertNotFalse($tabs_position);
    $this->assertLessThan($tabs_position, $status_position);
  }

  /**
   * The editor checkbox publishes and unpublishes through the existing workflow.
   */
  public function testActiveCheckboxUsesExistingModeration(): void {
    $this->assertTrue(\Drupal::service('content_moderation.moderation_information')->isModeratedEntity($this->createNode(['type' => 'api_service_card'])));
    $editor = $this->createUser();
    $editor->addRole('mmg_editor');
    $editor->activate()->save();
    $card = $this->createCard(FALSE, TRUE);
    $this->drupalLogin($editor);
    $this->drupalGet($card->toUrl('edit-form'));
    $this->getSession()->getPage()->checkField('status[value]');
    $this->getSession()->getPage()->pressButton('Save');
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$card->id()]);
    $card = $storage->load($card->id());
    $this->assertTrue($card->isPublished());
    $this->assertSame('published', $card->get('moderation_state')->value);
    $this->assertTrue((bool) $card->get('field_api_srv_card_status')->value);
    $this->assertFalse((bool) $card->get(self::APC_FIELD)->value);
    $this->drupalGet($card->toUrl('edit-form'));
    $this->getSession()->getPage()->uncheckField('status[value]');
    $this->getSession()->getPage()->pressButton('Save');
    $storage->resetCache([$card->id()]);
    $card = $storage->load($card->id());
    $this->assertFalse($card->isPublished());
    $this->assertSame('unpublished', $card->get('moderation_state')->value);
    $this->drupalLogout();
    $this->assertSame([], $this->getApiCard($card));
  }

  /**
   * Checking Active cannot bypass existing workflow transition permissions.
   */
  public function testActiveCheckboxRetainsTransitionValidation(): void {
    $editor = $this->createUser();
    $card = $this->createCard(TRUE, TRUE);
    $form_object = \Drupal::entityTypeManager()->getFormObject('node', 'edit')->setEntity($card);
    $form_state = (new FormState())->setFormObject($form_object);
    $element = ['#value' => 0, '#parents' => ['status', 'value']];
    $switcher = \Drupal::service('account_switcher');
    $switcher->switchTo($editor);
    try {
      $callback = \Drupal::service('callable_resolver')->getCallableFromDefinition(ApiServiceCardFormHooks::class . '::validateActive');
      $callback($element, $form_state);
    }
    finally {
      $switcher->switchBack();
    }
    $this->assertStringContainsString('You do not have permission to change the Active status.', implode(' ', $form_state->getErrors()));
    $this->assertFalse($form_state->hasValue('moderation_state'));
    $this->assertTrue($card->isPublished());
    $this->assertSame(MassModeration::PUBLISHED, $card->get('moderation_state')->value);
  }

  /**
   * Editors need the existing transition permission even on a submitted form.
   */
  public function testEditorWithoutTransitionsCannotChangeActiveThroughForm(): void {
    $permissions = array_diff(Role::load('mmg_editor')->getPermissions(), [
      'use api_cards transition publish',
      'use api_cards transition unpublish',
    ]);
    $permissions[] = 'view own unpublished content';
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    foreach ([TRUE, FALSE] as $published) {
      $allowed = $published ? 'publish' : 'unpublish';
      $editor = $this->createUser([...array_values($permissions), 'use api_cards transition ' . $allowed]);
      $editor->activate()->save();
      $this->drupalLogin($editor);
      $card = $this->createCard($published, TRUE);
      $card->setOwner($editor)->save();
      $this->drupalGet($card->toUrl('edit-form'));

      $this->assertSame(200, $this->getSession()->getStatusCode());
      $this->getSession()->getPage()->findField('status[value]')->setValue(!$published);
      $this->getSession()->getPage()->pressButton('Save');
      $this->assertSession()->pageTextContains('You do not have permission to change the Active status.');
      $storage->resetCache([$card->id()]);
      $saved = $storage->load($card->id());
      $this->assertSame($published, $saved->isPublished());
      $this->assertSame($published ? MassModeration::PUBLISHED : MassModeration::UNPUBLISHED, $saved->get('moderation_state')->value);
    }
  }

  /**
   * The translated checkbox changes publication only for that translation.
   */
  public function testTranslatedActiveCheckboxPreservesOriginal(): void {
    $editor = $this->createUser();
    $editor->addRole('mmg_editor');
    $editor->activate()->save();
    $card = $this->createCard(TRUE, TRUE);
    $card->addTranslation('es', [
      'title' => 'Servicio de prueba',
      'field_api_serv_card_description' => ['value' => 'Descripción de prueba', 'format' => 'plain_text'],
      'moderation_state' => MassModeration::PUBLISHED,
      'status' => 1,
    ]);
    $card->save();
    $this->drupalLogin($editor);
    $this->drupalGet($card->getTranslation('es')->toUrl('edit-form'), ['query' => ['language_content_entity' => 'es']]);
    $this->assertSession()->fieldValueEquals('title[0][value]', 'Servicio de prueba');
    $this->getSession()->getPage()->uncheckField('status[value]');
    $this->getSession()->getPage()->pressButton('Save');
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$card->id()]);
    $card = $storage->load($card->id());
    $this->assertTrue($card->isPublished());
    $this->assertFalse($card->getTranslation('es')->isPublished(), $this->getSession()->getPage()->getText());
    $this->assertSame(MassModeration::UNPUBLISHED, $card->getTranslation('es')->get('moderation_state')->value);
  }

  /**
   * Restoring a revision from before APC existed must still expose a boolean.
   */
  public function testRevertingLegacyRevisionKeepsProfileCollectionBoolean(): void {
    $card = $this->createCard(TRUE, TRUE);
    $card->set(self::APC_FIELD, NULL)->save();
    $legacy_revision_id = $card->getRevisionId();
    $card->setNewRevision(TRUE);
    $card->set(self::APC_FIELD, 1)->save();
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $legacy = $storage->loadRevision($legacy_revision_id);
    // Historical revisions predate the field and retain an empty field value.
    $legacy->set(self::APC_FIELD, NULL);
    $restored = $storage->createRevision($legacy);
    $restored->save();
    $this->assertFalse($this->getApiCard($restored)[0]['attributes'][self::APC_FIELD]);
  }

  /**
   * Other content types retain their existing moderation controls.
   */
  public function testOtherContentKeepsItsModerationControls(): void {
    $admin = $this->createUser();
    $admin->addRole('administrator');
    $admin->activate()->save();
    $this->drupalLogin($admin);
    foreach (['info_details', 'news'] as $bundle) {
      $this->drupalGet('/node/add/' . $bundle);
      $this->assertSame(200, $this->getSession()->getStatusCode());
      $this->assertSession()->fieldExists('moderation_state[0][state]');
      $this->assertSession()->fieldNotExists('status[value]');
      $this->assertSession()->fieldNotExists(self::APC_FIELD . '[value]');
    }
  }

  /**
   * Creates a complete, tracked card with explicit publication and visibility.
   */
  private function createCard(bool $published, bool $visible): NodeInterface {
    return $this->createNode([
      'type' => 'api_service_card',
      'title' => 'DP-49259 test ' . $this->randomMachineName(),
      'status' => (int) $published,
      'moderation_state' => $published ? MassModeration::PUBLISHED : MassModeration::UNPUBLISHED,
      'field_api_serv_card_description' => ['value' => 'Test description', 'format' => 'plain_text'],
      'field_api_serv_card_machine_name' => strtolower($this->randomMachineName()),
      'field_api_serv_card_link' => ['uri' => 'https://example.com/service', 'title' => ''],
      'field_api_srv_card_tenant' => 'personal',
      'field_environment' => 'non_production',
      'field_api_srv_card_status' => (int) $visible,
    ]);
  }

  /**
   * Requests one card using the public JSON:API collection endpoint.
   */
  private function getApiCard(NodeInterface $card, string $langcode = 'en'): array {
    $payload = $this->drupalGet(Url::fromUserInput('/jsonapi/node/api_service_card')->toString(), [
      'query' => [
        'filter[id]' => $card->uuid(),
        'language_content_entity' => $langcode,
        'filter[langcode]' => $langcode,
      ],
    ]);
    $this->assertSame(200, $this->getSession()->getStatusCode());
    return Json::decode($payload)['data'];
  }

}
