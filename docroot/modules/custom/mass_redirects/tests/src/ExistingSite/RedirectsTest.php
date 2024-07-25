<?php

namespace Drupal\Tests\mass_redirects\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\mass_redirects\Form\MoveRedirectsForm;
use Drupal\node\Entity\Node;
use Drupal\redirect\Entity\Redirect;
use Drupal\user\Entity\User;
use MassGov\Dtt\MassExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * Test Redirects.
 */
class RedirectsTest extends MassExistingSiteBase {

  use LoginTrait;

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

    $user1 = User::create(['name' => $this->randomMachineName()]);
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
   * Check that the our tab shows only on Trashed nodes, and works as designed.
   */
  public function testRedirects() {
    $session = $this->getSession();

    // Test outbound.
    $this->visit($this->orgNode->toUrl()->toString());
    $this->assertEquals(200, $session->getStatusCode());
    $url = $this->orgNode->toUrl('redirects')->toString();
    $session->visit($url);
    $this->assertEquals(403, $session->getStatusCode());
    $this->orgNode->setUnpublished()->set(MassModeration::FIELD_NAME, MassModeration::TRASH)->save();
    // Reload to get new state.
    $this->orgNode = Node::load($this->orgNode->id());
    $state = $this->orgNode->getModerationState()->getString();
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

}
