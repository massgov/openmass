<?php

namespace Drupal\Tests\mass_content\ExistingSite;

use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountInterface;
use Drupal\mass_content\Hook\CollectionTermHooks;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;

/**
 * Verifies what the Collection edit form does after Save.
 *
 * @group mass_content
 */
class CollectionTermSaveRedirectTest extends MassExistingSiteBase {

  use TaxonomyCreationTrait;

  /**
   * Users who cannot see the raw term page land on the Collection page.
   *
   * Core redirects to the term page, which Rabbit Hole answers with a 404
   * unless the user has "rabbit hole bypass taxonomy_term".
   */
  public function testRedirectToCollectionPage(): void {
    $url_name = 'save-redirect-' . strtolower($this->randomMachineName());
    $collection = $this->createTerm(Vocabulary::load('collections'), [
      'name' => 'Save redirect collection ' . $this->randomMachineName(),
      'field_url_name' => $url_name,
    ]);

    $form = $this->buildCollectionForm($collection, $this->createUser(['access content', 'edit top-level collection terms']));
    $handlers = $form['actions']['submit']['#submit'];
    $redirect = [CollectionTermHooks::class, 'collectionTermRedirect'];
    $this->assertContains($redirect, $handlers);
    // It must run after ::save, which sets the core redirect.
    $this->assertGreaterThan(
      array_search('::save', $handlers, TRUE),
      array_search($redirect, $handlers, TRUE)
    );

    $form_state = $this->submitRedirectHandler($collection, $this->createUser(['access content', 'edit top-level collection terms']));
    $this->assertSame('/collections/' . $url_name, $form_state->getRedirect()->toString());

    $form_state = $this->submitRedirectHandler($collection, $this->createUser(['access content', 'rabbit hole bypass taxonomy_term']));
    $this->assertNull($form_state->getRedirect(), 'Users who can see the term page keep the core redirect.');
  }

  /**
   * Unpublished Collections keep the core behavior.
   *
   * Their Collection page is not found for users who cannot view them.
   */
  public function testNoRedirectForUnpublishedCollection(): void {
    $collection = $this->createTerm(Vocabulary::load('collections'), [
      'name' => 'Save redirect unpublished ' . $this->randomMachineName(),
      'field_url_name' => 'save-redirect-unpublished-' . strtolower($this->randomMachineName()),
      'status' => 0,
    ]);

    $form_state = $this->submitRedirectHandler($collection, $this->createUser(['access content', 'edit top-level collection terms']));
    $this->assertNull($form_state->getRedirect());
  }

  /**
   * Topics keep the core redirect; the handler covers top-level Collections.
   */
  public function testNoRedirectForTopics(): void {
    $vocabulary = Vocabulary::load('collections');
    $collection = $this->createTerm($vocabulary, [
      'name' => 'Save redirect collection ' . $this->randomMachineName(),
      'field_url_name' => 'save-redirect-' . strtolower($this->randomMachineName()),
    ]);
    $topic = $this->createTerm($vocabulary, [
      'name' => 'Save redirect topic ' . $this->randomMachineName(),
      'field_url_name' => 'save-redirect-topic-' . strtolower($this->randomMachineName()),
      'parent' => [['target_id' => $collection->id()]],
    ]);

    $form_state = $this->submitRedirectHandler($topic, $this->createUser(['access content', 'edit top-level collection terms']));
    $this->assertNull($form_state->getRedirect());
  }

  /**
   * The core update message is replaced with a Collection-specific one.
   *
   * The core message links to the term page, which is not found for editors.
   * Running the real TermForm::save() keeps this tied to core's wording.
   */
  public function testCoreUpdateMessageIsReplaced(): void {
    $collection = $this->createTerm(Vocabulary::load('collections'), [
      'name' => 'Save message collection ' . $this->randomMachineName(),
      'field_url_name' => 'save-message-' . strtolower($this->randomMachineName()),
    ]);
    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('taxonomy_term', 'default')
      ->setEntity($collection);
    $form_state = (new FormState())->setFormObject($form_object);
    $form_state->setValue('parent', [0]);
    $form = [];
    $messenger = $this->container->get('messenger');
    $messenger->deleteAll();
    $messenger->addStatus('Unrelated message');

    $form_object->save($form, $form_state);
    CollectionTermHooks::collectionTermSaved($form, $form_state);

    $this->assertSame(
      ['Unrelated message', 'Collection updated.'],
      array_map('strval', $messenger->deleteByType('status'))
    );
  }

  /**
   * Builds the collection term edit form as the given user.
   */
  private function buildCollectionForm(TermInterface $term, AccountInterface $account): array {
    $account_switcher = $this->container->get('account_switcher');
    $account_switcher->switchTo($account);
    try {
      $form_object = $this->container->get('entity_type.manager')
        ->getFormObject('taxonomy_term', 'default')
        ->setEntity($term);
      $form_state = (new FormState())->setFormObject($form_object);
      return $this->container->get('form_builder')->buildForm($form_object, $form_state);
    }
    finally {
      $account_switcher->switchBack();
    }
  }

  /**
   * Runs the redirect submit handler as the given user.
   */
  private function submitRedirectHandler(TermInterface $term, AccountInterface $account): FormState {
    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('taxonomy_term', 'default')
      ->setEntity($term);
    $form_state = (new FormState())->setFormObject($form_object);
    $form = [];
    $account_switcher = $this->container->get('account_switcher');
    $account_switcher->switchTo($account);
    try {
      CollectionTermHooks::collectionTermRedirect($form, $form_state);
    }
    finally {
      $account_switcher->switchBack();
    }
    return $form_state;
  }

}
