<?php

namespace Drupal\Tests\mass_friendly_redirects\ExistingSite;

use Drupal\Core\Form\FormState;
use Drupal\Core\Entity\EntityInterface;
use Drupal\mass_content_moderation\MassModeration;
use Drupal\mass_friendly_redirects\Form\NodeFriendlyRedirectsAlterer;
use Drupal\mass_friendly_redirects\Form\PrefixTermFormAlterer;
use Drupal\node\NodeInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\UserInterface;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests friendly redirects behavior.
 *
 * @group existing-site
 */
class FriendlyRedirectsTest extends MassExistingSiteBase {

  /**
   * Creates and returns a user with editor permissions.
   */
  private function createEditorUser(): UserInterface {
    $user = $this->createUser();
    $user->addRole('editor');
    $user->activate();
    $user->save();
    return $user;
  }

  /**
   * Creates and returns an admin-level user.
   */
  private function createAdminUser(): UserInterface {
    $user = $this->createUser();
    $user->addRole('administrator');
    $user->activate();
    $user->save();
    return $user;
  }

  /**
   * Sets the current account for service-based permission checks.
   */
  private function useCurrentUser(UserInterface $user): void {
    \Drupal::currentUser()->setAccount($user);
  }

  /**
   * Ensures the prefixes vocabulary exists.
   */
  private function ensurePrefixesVocabulary(): void {
    if (!Vocabulary::load('friendly_url_prefixes')) {
      Vocabulary::create([
        'vid' => 'friendly_url_prefixes',
        'name' => 'Friendly URL Prefixes',
      ])->save();
    }
  }

  /**
   * Creates an allowed friendly redirect prefix term.
   */
  private function createPrefixTerm(string $label): Term {
    $this->ensurePrefixesVocabulary();

    $term = Term::create([
      'vid' => 'friendly_url_prefixes',
      'name' => $label,
      'status' => 1,
    ]);
    $term->save();
    $this->cleanupEntities[] = $term;
    return $term;
  }

  /**
   * Creates an org page node for redirect target tests.
   */
  private function createOrgPageNode(string $title): NodeInterface {
    return $this->createNode([
      'type' => 'org_page',
      'title' => $title,
      'status' => 1,
      MassModeration::FIELD_NAME => MassModeration::PUBLISHED,
    ]);
  }

  /**
   * Creates a redirect entity.
   */
  private function createRedirect(string $sourcePath, string $targetUri, int $statusCode = 301): Redirect {
    $redirect = Redirect::create();
    $redirect->setSource($sourcePath);
    $redirect->setRedirect($targetUri);
    $redirect->setStatusCode($statusCode);
    $redirect->setLanguage('en');
    $redirect->save();
    $this->cleanupEntities[] = $redirect;
    return $redirect;
  }

  /**
   * Builds form state with a form object that provides getEntity().
   */
  private function buildFormStateForEntity(EntityInterface $entity, array $friendlyValues = []): FormState {
    $form_state = new FormState();
    $form_object = \Drupal::service('entity_type.manager')
      ->getFormObject($entity->getEntityTypeId(), 'default')
      ->setEntity($entity);

    $form_state->setFormObject($form_object);
    if ($friendlyValues !== []) {
      $form_state->setValue('mass_friendly_redirects', $friendlyValues);
    }
    return $form_state;
  }

  /**
   * Creates a safe lowercase token.
   */
  private function friendlyToken(string $prefix = 'token'): string {
    $token = strtolower($prefix . '-' . $this->randomMachineName());
    $token = str_replace('_', '-', $token);
    $token = preg_replace('/[^a-z0-9\-]/', '-', $token) ?? 'token';
    $token = trim($token, '-');
    return $token ?: 'token';
  }

  /**
   * Tests creating a friendly redirect and forcing 301.
   */
  public function testCreatesFriendlyRedirectWith301(): void {
    $editor = $this->createEditorUser();
    $this->useCurrentUser($editor);

    $prefix = $this->createPrefixTerm($this->friendlyToken('masshealth'));
    $node = $this->createOrgPageNode('Friendly Redirect Target');

    $suffix = $this->friendlyToken('vaccine');
    $form_state = $this->buildFormStateForEntity($node, [
      'prefix' => (string) $prefix->id(),
      'suffix' => $suffix,
    ]);
    $form = [];

    NodeFriendlyRedirectsAlterer::validate($form, $form_state);
    $this->assertSame([], $form_state->getErrors());

    NodeFriendlyRedirectsAlterer::submit($form, $form_state);

    $source = $prefix->label() . '/' . $suffix;
    $ids = \Drupal::entityTypeManager()->getStorage('redirect')->getQuery()
      ->accessCheck(FALSE)
      ->condition('redirect_source__path', $source)
      ->execute();
    $this->assertNotEmpty($ids);

    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = \Drupal::entityTypeManager()->getStorage('redirect')->load(reset($ids));
    $this->assertInstanceOf(Redirect::class, $redirect);
    $item = $redirect->get('redirect_redirect')->first();
    $uri = $item && isset($item->uri) ? (string) $item->uri : '';
    $this->assertContains($uri, ['node/' . $node->id(), 'internal:/node/' . $node->id()]);
    $this->assertSame(301, (int) $redirect->getStatusCode());
  }

  /**
   * Tests that uppercase suffixes are rejected.
   */
  public function testRejectsUppercaseSuffix(): void {
    $editor = $this->createEditorUser();
    $this->useCurrentUser($editor);

    $prefix = $this->createPrefixTerm($this->friendlyToken('dor'));
    $node = $this->createOrgPageNode('Uppercase Validation Target');
    $suffix = 'FluShot';

    $form_state = $this->buildFormStateForEntity($node, [
      'prefix' => (string) $prefix->id(),
      'suffix' => $suffix,
    ]);
    $form = [];

    NodeFriendlyRedirectsAlterer::validate($form, $form_state);
    $errors = $form_state->getErrors();

    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('must be entered in lowercase', implode(' ', array_map('strval', $errors)));
  }

  /**
   * Tests that invalid suffix characters are rejected.
   */
  public function testRejectsInvalidSuffixCharacters(): void {
    $editor = $this->createEditorUser();
    $this->useCurrentUser($editor);

    $prefix = $this->createPrefixTerm($this->friendlyToken('ago'));
    $node = $this->createOrgPageNode('Invalid Chars Validation Target');

    $form_state = $this->buildFormStateForEntity($node, [
      'prefix' => (string) $prefix->id(),
      'suffix' => 'vaccine?bad',
    ]);
    $form = [];

    NodeFriendlyRedirectsAlterer::validate($form, $form_state);
    $errors = $form_state->getErrors();

    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('Only lowercase letters, numbers, slashes, and hyphens are allowed', implode(' ', array_map('strval', $errors)));
  }

  /**
   * Tests duplicate source path handling.
   */
  public function testRejectsDuplicateSourcePath(): void {
    $editor = $this->createEditorUser();
    $this->useCurrentUser($editor);

    $prefixLabel = $this->friendlyToken('masshealth');
    $prefix = $this->createPrefixTerm($prefixLabel);
    $existingTarget = $this->createOrgPageNode('Existing Target');
    $newTarget = $this->createOrgPageNode('New Target');
    $suffix = $this->friendlyToken('dup');
    $source = $prefix->label() . '/' . $suffix;

    $existingRedirect = $this->createRedirect($source, 'node/' . $existingTarget->id(), 301);

    $form_state = $this->buildFormStateForEntity($newTarget, [
      'prefix' => (string) $prefix->id(),
      'suffix' => $suffix,
    ]);
    $form = [];

    NodeFriendlyRedirectsAlterer::validate($form, $form_state);
    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('already exists', implode(' ', array_map('strval', $errors)));

    /** @var \Drupal\redirect\Entity\Redirect $reloaded */
    $reloaded = Redirect::load($existingRedirect->id());
    $this->assertInstanceOf(Redirect::class, $reloaded);
    $item = $reloaded->get('redirect_redirect')->first();
    $uri = $item && isset($item->uri) ? (string) $item->uri : '';
    $this->assertContains($uri, ['node/' . $existingTarget->id(), 'internal:/node/' . $existingTarget->id()]);
  }

  /**
   * Tests editor-friendly view is prefix-scoped and default redirects hidden.
   */
  public function testEditorSeesOnlyAllowedFriendlyRedirects(): void {
    $editor = $this->createEditorUser();
    $this->useCurrentUser($editor);

    $allowedPrefix = $this->friendlyToken('allowed');
    $this->createPrefixTerm($allowedPrefix);

    $node = $this->createOrgPageNode('Editor Visibility Node');
    $allowedWithSegment = $this->createRedirect($allowedPrefix . '/' . $this->friendlyToken('segment'), 'node/' . $node->id(), 301);
    $this->createRedirect($allowedPrefix, 'node/' . $node->id(), 301);
    $this->createRedirect($this->friendlyToken('unknown') . '/' . $this->friendlyToken('segment'), 'node/' . $node->id(), 301);

    $alterer = \Drupal::service('mass_friendly_redirects.node_form_alterer');
    $form = [
      'path' => [
        'redirect' => [
          '#access' => TRUE,
        ],
      ],
      'actions' => [
        'submit' => [],
      ],
    ];
    $form_state = $this->buildFormStateForEntity($node);

    $alterer->alter($form, $form_state);

    $this->assertFalse((bool) $form['path']['redirect']['#access']);
    $this->assertArrayHasKey($allowedWithSegment->id(), $form['mass_friendly_redirects']['existing']);
    $this->assertCount(1, array_filter(array_keys($form['mass_friendly_redirects']['existing']), 'is_int'));
  }

  /**
   * Tests admins keep stock redirects UI visible.
   */
  public function testAdminKeepsDefaultRedirectUi(): void {
    $admin = $this->createAdminUser();
    $this->useCurrentUser($admin);

    $node = $this->createOrgPageNode('Admin Visibility Node');
    $alterer = \Drupal::service('mass_friendly_redirects.node_form_alterer');
    $form = [
      'path' => [
        'redirect' => [
          '#access' => TRUE,
        ],
      ],
      'actions' => [
        'submit' => [],
      ],
    ];
    $form_state = $this->buildFormStateForEntity($node);

    $alterer->alter($form, $form_state);

    $this->assertTrue((bool) $form['path']['redirect']['#access']);
  }

  /**
   * Tests prefix term validation rejects uppercase labels.
   */
  public function testPrefixTermValidationRejectsUppercase(): void {
    $this->ensurePrefixesVocabulary();
    $term = Term::create([
      'vid' => 'friendly_url_prefixes',
      'name' => 'MassHealth',
    ]);

    $form_state = $this->buildFormStateForEntity($term);
    $form_state->setValue('name', [['value' => 'MassHealth']]);
    $form = [];

    PrefixTermFormAlterer::validatePrefixLabel($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('Prefix must be lowercase', implode(' ', array_map('strval', $errors)));
  }

}
