<?php

namespace Drupal\Tests\mass_utility\ExistingSite;

use Drupal\Core\Form\FormState;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;

/**
 * Verifies UserDefaults service + entity_prepare_form hook.
 *
 * Covers the "Default organizations" and "Default labels" user fields,
 * the new-entity pre-fill (nodes and media.document), and the
 * permission-group fallback for media.
 *
 * @group mass_utility
 */
class UserDefaultsTest extends MassExistingSiteBase {

  use TaxonomyCreationTrait;

  private NodeInterface $orgPageA;
  private NodeInterface $orgPageB;
  private TermInterface $termA;
  private UserInterface $userA;

  protected function setUp(): void {
    parent::setUp();

    $this->orgPageA = $this->createNode([
      'type' => 'org_page',
      'title' => 'Default Test Org A ' . $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $this->orgPageB = $this->createNode([
      'type' => 'org_page',
      'title' => 'Default Test Org B ' . $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $vocab = Vocabulary::load('user_organization');
    $this->termA = $this->createTerm($vocab, [
      'name' => 'Default Test Term A ' . $this->randomMachineName(),
      'field_state_organization' => $this->orgPageA->id(),
    ]);

    $this->userA = $this->createUser();
    $this->userA->addRole('editor');
    $this->userA->set('field_user_org', $this->termA->id());
    $this->userA->activate();
    $this->userA->save();
  }

  /**
   * Authors can edit default org/label fields but not field_user_org.
   */
  public function testAuthorCanEditDefaultFieldsOnOwnProfile(): void {
    $author = $this->createUser();
    $author->addRole('author');
    $author->activate();
    $author->save();

    $this->assertTrue(
      $author->get('field_default_organizations')->access('edit', $author),
      'Author must be able to edit default organizations on their own profile.'
    );
    $this->assertTrue(
      $author->get('field_default_labels')->access('edit', $author),
      'Author must be able to edit default labels on their own profile.'
    );
    $this->assertFalse(
      $author->get('field_user_org')->access('edit', $author),
      'Author must not edit permission groups on their own profile.'
    );
  }

  /**
   * New entity form pre-fills field_organizations from default organizations.
   */
  public function testNewNodePrefillUsesDefaultOrganizations(): void {
    $this->userA->set('field_default_organizations', $this->orgPageA->id());
    $this->userA->save();
    \Drupal::currentUser()->setAccount($this->userA);

    $entity = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'info_details',
      'title' => 'Defaults prefill ' . $this->randomMachineName(),
    ]);
    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($entity);
    $form_state = (new FormState())->setFormObject($form_object);
    \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $entity = $form_object->getEntity();

    $org_nids = array_filter(array_column(
      $entity->get('field_organizations')->getValue(),
      'target_id'
    ));
    $this->assertEquals(
      [(string) $this->orgPageA->id()],
      array_map('strval', $org_nids),
      'field_organizations is pre-filled from user default organizations.'
    );
  }

  /**
   * New pages must NOT fall back to the creator's permission-group org.
   *
   * The mirror of testNewMediaPrefillFallsBackToPermissionGroups: media may
   * fall back, but pages may not. userA has a permission group that maps to
   * orgPageA, yet with no default organizations set a new node must leave
   * field_organizations empty so the author chooses the org explicitly.
   */
  public function testNewNodePrefillDoesNotFallBackToPermissionGroups(): void {
    $this->userA->set('field_default_organizations', []);
    $this->userA->save();
    \Drupal::currentUser()->setAccount($this->userA);

    $entity = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'info_details',
      'title' => 'No node fallback ' . $this->randomMachineName(),
    ]);
    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($entity);
    $form_state = (new FormState())->setFormObject($form_object);
    \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $entity = $form_object->getEntity();

    $this->assertEmpty(
      array_filter(array_column(
        $entity->get('field_organizations')->getValue(),
        'target_id'
      )),
      'New pages must not fall back to the permission-group org of the creator.'
    );
  }

  /**
   * New node form pre-fills default labels from the user profile.
   */
  public function testNewNodePrefillDefaultLabels(): void {
    $vocab = Vocabulary::load('label');
    $label = $this->createTerm($vocab, [
      'name' => 'Default label ' . $this->randomMachineName(),
    ]);
    $this->userA->set('field_default_organizations', $this->orgPageA->id());
    $this->userA->set('field_default_labels', $label->id());
    $this->userA->save();
    \Drupal::currentUser()->setAccount($this->userA);

    $entity = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'info_details',
      'title' => 'Labels prefill ' . $this->randomMachineName(),
    ]);
    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($entity);
    $form_state = (new FormState())->setFormObject($form_object);
    \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $entity = $form_object->getEntity();

    $label_tids = array_filter(array_column(
      $entity->get('field_reusable_label')->getValue(),
      'target_id'
    ));
    $this->assertContains(
      (string) $label->id(),
      array_map('strval', $label_tids),
      'field_reusable_label is pre-filled from user default labels.'
    );
  }

  /**
   * New media uses default organizations when set on the user profile.
   */
  public function testNewMediaPrefillUsesUserDefaultOrganizations(): void {
    $this->userA->set('field_default_organizations', $this->orgPageB->id());
    $this->userA->save();
    \Drupal::currentUser()->setAccount($this->userA);

    $media = \Drupal::entityTypeManager()->getStorage('media')->create([
      'bundle' => 'document',
      'name' => 'Media defaults ' . $this->randomMachineName(),
    ]);
    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('media', 'default')
      ->setEntity($media);
    $form_state = (new FormState())->setFormObject($form_object);
    \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $media = $form_object->getEntity();

    $org_nids = array_filter(array_column(
      $media->get('field_organizations')->getValue(),
      'target_id'
    ));
    $this->assertEquals(
      [(string) $this->orgPageB->id()],
      array_map('strval', $org_nids),
      'New media must use profile default organizations.'
    );
  }

  /**
   * New media falls back to permission-group org when defaults are empty.
   */
  public function testNewMediaPrefillFallsBackToPermissionGroups(): void {
    $this->userA->set('field_default_organizations', []);
    $this->userA->save();
    \Drupal::currentUser()->setAccount($this->userA);

    $media = \Drupal::entityTypeManager()->getStorage('media')->create([
      'bundle' => 'document',
      'name' => 'Media fallback ' . $this->randomMachineName(),
    ]);
    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('media', 'default')
      ->setEntity($media);
    $form_state = (new FormState())->setFormObject($form_object);
    \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $media = $form_object->getEntity();

    $org_nids = array_filter(array_column(
      $media->get('field_organizations')->getValue(),
      'target_id'
    ));
    $this->assertEquals(
      [(string) $this->orgPageA->id()],
      array_map('strval', $org_nids),
      'New media must fall back to org_page from permission groups.'
    );
  }

  /**
   * Existing content edit forms must not apply user defaults.
   */
  public function testExistingNodeEditDoesNotApplyDefaults(): void {
    $vocab = Vocabulary::load('label');
    $label = $this->createTerm($vocab, [
      'name' => 'Unused default ' . $this->randomMachineName(),
    ]);
    $this->userA->set('field_default_organizations', $this->orgPageA->id());
    $this->userA->set('field_default_labels', $label->id());
    $this->userA->save();
    \Drupal::currentUser()->setAccount($this->userA);

    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Existing no defaults ' . $this->randomMachineName(),
      'field_organizations' => [$this->orgPageB->id()],
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    $node->set('field_reusable_label', []);
    $node->save();

    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($node);
    $form_state = (new FormState())->setFormObject($form_object);
    \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $node = $form_object->getEntity();

    $org_nids = array_filter(array_column(
      $node->get('field_organizations')->getValue(),
      'target_id'
    ));
    $this->assertEquals(
      [(string) $this->orgPageB->id()],
      array_map('strval', $org_nids),
      'Edit form must not replace existing organizations with defaults.'
    );
    $this->assertEmpty(
      array_filter(array_column($node->get('field_reusable_label')->getValue(), 'target_id')),
      'Edit form must not inject default labels.'
    );
  }

}
