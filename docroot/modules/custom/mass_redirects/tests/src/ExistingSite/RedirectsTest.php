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
    $this->getCurrentPage()->pressButton('Add redirects');
    $this->assertSession()->pageTextContains('Redirected');
    foreach ($this->sourcePaths as $source_path) {
      $this->drupalGet($source_path);
      $this->assertSession()->addressEquals($this->orgNodeTarget->toUrl()->toString());
      $this->assertEquals(200, $session->getStatusCode());
    }

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
   * Unpublished pages can redirect, then reclaim their URL on republish.
   */
  public function testUnpublishedRedirectAndRepublish(): void {
    $page = $this->createNode([
      'type' => 'service_page',
      'title' => $this->randomMachineName(),
      'field_service_lede' => 'Redirect test page.',
      'field_organizations' => [$this->orgNode],
      'field_primary_parent' => [['target_id' => $this->orgNode->id()]],
      'status' => 1,
      MassModeration::FIELD_NAME => MassModeration::PUBLISHED,
    ]);
    $originalUrl = $page->toUrl()->toString();
    $this->drupalGet($page->toUrl('edit-form', ['path_processing' => FALSE]));
    $this->getCurrentPage()->selectFieldOption('moderation_state[0][state]', MassModeration::UNPUBLISHED);
    $this->getCurrentPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Redirect this unpublished page');
    $this->assertSession()->elementAttributeContains('css', '.messages--warning a', 'href', '/node/' . $page->id() . '/redirects');
    $page = Node::load($page->id());
    $this->assertSame(MassModeration::UNPUBLISHED, $page->getModerationState()->getString());

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

    $this->drupalGet($page->toUrl('edit-form', ['path_processing' => FALSE]));
    $this->getCurrentPage()->selectFieldOption('moderation_state[0][state]', MassModeration::TRASH);
    $this->getCurrentPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Redirect this trashed page');
    $this->drupalGet($page->toUrl('redirects', ['path_processing' => FALSE]));
    $this->assertSession()->statusCodeEquals(200);
  }

}
