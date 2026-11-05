<?php

namespace Drupal\Tests\mass_content\ExistingSite;

use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;

/**
 * Verifies where the Collection edit form sends users after Save.
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

    $form = $this->buildCollectionForm($collection, $this->createUser(['edit top-level collection terms']));
    $this->assertContains('mass_content_collection_term_redirect', $form['actions']['submit']['#submit']);

    $form_state = $this->submitRedirectHandler($collection, $this->createUser(['edit top-level collection terms']));
    $this->assertSame('/collections/' . $url_name, $form_state->getRedirect()->toString());

    $form_state = $this->submitRedirectHandler($collection, $this->createUser(['rabbit hole bypass taxonomy_term']));
    $this->assertNull($form_state->getRedirect(), 'Users who can see the term page keep the core redirect.');
  }

  /**
   * Topics have no Collection page, so the core redirect stays.
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

    $form_state = $this->submitRedirectHandler($topic, $this->createUser(['edit top-level collection terms']));
    $this->assertNull($form_state->getRedirect());
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
      mass_content_collection_term_redirect($form, $form_state);
    }
    finally {
      $account_switcher->switchBack();
    }
    return $form_state;
  }

}
