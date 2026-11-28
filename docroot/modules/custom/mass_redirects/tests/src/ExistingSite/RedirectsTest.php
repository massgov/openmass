<?php

namespace Drupal\Tests\mass_redirects\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\mass_redirects\Form\MoveRedirectsForm;
use Drupal\node\Entity\Node;
use Drupal\redirect\Entity\Redirect;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Test Redirects.
 */
class RedirectsTest extends MassExistingSiteBase {

  private $editor;
  private $orgNode;
  private $orgNodeTarget;
  private $sourcePaths;

  /**
   * Un-cacheable dynamic page patterns.
   */
  protected static array $uncacheableDynamicPagePatterns = [
    'orgs/*',
  ];

  /**
   * Create an editor and a node with url redirects.
   */
  protected function setUp(): void {
    parent::setUp();

    $user1 = $this->createUser(['bypass org access']);
    $user1->addRole('editor');
    $user1->activate();
    $user1->save();
    $this->drupalLogin($user1);

    $this->orgNode = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      MassModeration::FIELD_NAME => MassModeration::PUBLISHED,
    ]);
    $id = $this->orgNode->id();

    $this->orgNodeTarget = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      MassModeration::FIELD_NAME => MassModeration::PUBLISHED,
    ]);

    $this->sourcePaths = [$this->randomMachineName(), $this->randomMachineName()];
    foreach ($this->sourcePaths as $source_path) {
      $redirect = Redirect::create();
      $redirect->setRedirect("node/$id");
      $redirect->setSource($source_path);
      $redirect->setLanguage($this->orgNode->language()->getId());
      $redirect->setStatusCode(\Drupal::config('redirect.settings')->get('default_status_code'));
      $redirect->save();
      $this->cleanupEntities[] = $redirect;
    }
  }

  /**
   * Check that the Redirects tab shows on trashed nodes.
   */
  public function testRedirects() {
    $session = $this->getSession();

    // Test outbound.
    $this->visit($this->orgNode->toUrl()->toString());
    $this->assertEquals(200, $session->getStatusCode());
    $url = $this->orgNode->toUrl('redirects')->toString();
    $session->visit($url);
    $this->assertEquals(404, $session->getStatusCode());
    $this->orgNode->setUnpublished()->set(MassModeration::FIELD_NAME, MassModeration::TRASH)->save();
    // Reload to get new state.
    $this->orgNode = Node::load($this->orgNode->id());
    $this->assertSame(MassModeration::TRASH, $this->orgNode->getModerationState()->getString());
    $url = $this->orgNode->toUrl('redirects')->toString();
    $session->visit($url);
    $this->assertEquals(200, $session->getStatusCode());
    $this->getCurrentPage()->fillField('target', $this->orgNodeTarget->label() . ' (' . $this->orgNodeTarget->id() . ')');
    foreach ($this->sourcePaths as $source_path) {
      $this->assertSession()->pageTextNotContains($source_path);
    }
    $this->getCurrentPage()->pressButton('Add redirect');
    $this->assertSession()->pageTextContains('Redirected');
    foreach ($this->sourcePaths as $source_path) {
      $redirects = \Drupal::entityTypeManager()->getStorage('redirect')->loadByProperties(['redirect_source__path' => $source_path]);
      $this->assertSame('internal:/node/' . $this->orgNode->id(), reset($redirects)->getRedirect()['uri']);
      $this->drupalGet($source_path);
      $this->assertSession()->addressEquals($this->orgNodeTarget->toUrl()->toString());
      $this->assertEquals(200, $session->getStatusCode());
    }
    $this->visit('/' . $this->sourcePaths[0] . '?campaign=redirect-test');
    $this->assertSession()->addressEquals($this->orgNodeTarget->toUrl()->toString() . '?campaign=redirect-test');

    // Test Inbound.
    $session->visit($url);
    $msg = 'currently points to ' . $this->orgNodeTarget->toUrl()->toString();
    $this->assertSession()->pageTextContains($msg);
    $this->getCurrentPage()->pressButton('Remove redirect');
    $this->assertSession()->pageTextContains('Removed redirect.');
    $path = MoveRedirectsForm::shortenUrl($this->orgNode);
    $this->drupalGet($path);
    $this->assertSession()->addressEquals($this->orgNode->toUrl()->toString());
    $this->assertEquals(200, $session->getStatusCode());
  }

  /**
   * Pages preserve incoming redirects and reclaim their URL on republish.
   *
   * @dataProvider redirectStateProvider
   */
  public function testRedirectAndRepublish(string $state, string $state_label): void {
    $page = $this->createNode([
      'type' => 'service_page',
      'title' => $this->randomMachineName(),
      'field_service_lede' => 'Redirect test page.',
      'field_organizations' => [$this->orgNode],
      'field_primary_parent' => [['target_id' => $this->orgNode->id()]],
      'status' => 1,
      MassModeration::FIELD_NAME => MassModeration::PUBLISHED,
    ]);
    $oldUrl = $page->toUrl()->toString();
    $page->setTitle($this->randomMachineName())->save();
    $originalUrl = $page->toUrl()->toString();
    $vanity = Redirect::create();
    $vanity->setSource($this->randomMachineName());
    $vanity->set('redirect_redirect', ['uri' => 'entity:node/' . $page->id()]);
    $vanity->setLanguage($page->language()->getId());
    $vanity->setStatusCode(301);
    $vanity->save();
    $this->cleanupEntities[] = $vanity;
    $incomingPaths = [ltrim($oldUrl, '/'), $vanity->getSource()['path']];
    $redirectStorage = \Drupal::entityTypeManager()->getStorage('redirect');
    $incomingRedirects = [];
    foreach ($incomingPaths as $path) {
      $redirects = $redirectStorage->loadByProperties(['redirect_source__path' => $path]);
      $this->assertNotEmpty($redirects);
      $incomingRedirects[$path] = reset($redirects)->getRedirect();
    }
    $this->drupalGet($page->toUrl('edit-form', ['path_processing' => FALSE]));
    $this->getCurrentPage()->selectFieldOption('moderation_state[0][state]', $state);
    $this->getCurrentPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Redirect this ' . $state_label . ' page');
    $this->assertSession()->elementAttributeContains('css', '.messages--warning a', 'href', '/node/' . $page->id() . '/redirects');
    $page = Node::load($page->id());
    $this->assertSame($state, $page->getModerationState()->getString());
    foreach ($incomingPaths as $path) {
      $this->drupalGet($path);
      $this->assertSession()->addressEquals($originalUrl . '---unpublished');
    }

    $this->drupalGet($page->toUrl('redirects', ['path_processing' => FALSE]));
    $this->assertSession()->statusCodeEquals(200);
    $this->getCurrentPage()->fillField('target', $this->orgNodeTarget->label() . ' (' . $this->orgNodeTarget->id() . ')');
    $this->getCurrentPage()->pressButton('Add redirect');

    $source = ltrim($originalUrl, '/');
    $redirects = \Drupal::entityTypeManager()->getStorage('redirect')->loadByProperties(['redirect_source__path' => $source]);
    $this->assertNotEmpty($redirects, 'The original page URL should redirect while unpublished.');
    $this->assertSame('internal:/node/' . $this->orgNodeTarget->id(), reset($redirects)->getRedirect()['uri']);
    $this->drupalGet($originalUrl);
    $this->assertSession()->addressEquals($this->orgNodeTarget->toUrl()->toString());
    $redirectStorage->resetCache();
    foreach ($incomingPaths as $path) {
      $redirects = $redirectStorage->loadByProperties(['redirect_source__path' => $path]);
      $this->assertSame($incomingRedirects[$path], reset($redirects)->getRedirect());
      $this->drupalGet($path);
      $this->assertSession()->addressEquals($this->orgNodeTarget->toUrl()->toString());
    }

    $this->drupalGet($page->toUrl('edit-form', ['path_processing' => FALSE]));
    $this->getCurrentPage()->selectFieldOption('moderation_state[0][state]', MassModeration::PUBLISHED);
    $this->getCurrentPage()->pressButton('Save');

    $page = Node::load($page->id());
    $this->assertSame(MassModeration::PUBLISHED, $page->getModerationState()->getString());
    $redirects = \Drupal::entityTypeManager()->getStorage('redirect')->loadByProperties(['redirect_source__path' => $source]);
    $this->assertEmpty($redirects, 'Republishing should remove the redirect that shadows the restored URL.');
    $this->drupalGet($originalUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals($originalUrl);
    $redirectStorage->resetCache();
    foreach ($incomingPaths as $path) {
      $redirects = $redirectStorage->loadByProperties(['redirect_source__path' => $path]);
      $this->assertNotEmpty($redirects);
      $this->assertSame($incomingRedirects[$path], reset($redirects)->getRedirect());
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->addressEquals($originalUrl);
    }

    $this->drupalGet($page->toUrl('edit-form', ['path_processing' => FALSE]));
    $this->getCurrentPage()->selectFieldOption('moderation_state[0][state]', MassModeration::TRASH);
    $this->getCurrentPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Redirect this trashed page');
    $this->drupalGet($page->toUrl('redirects', ['path_processing' => FALSE]));
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * States that allow a page's URL to be redirected.
   */
  public static function redirectStateProvider(): array {
    return [
      'unpublished' => [MassModeration::UNPUBLISHED, 'unpublished'],
      'trashed' => [MassModeration::TRASH, 'trashed'],
    ];
  }

}
