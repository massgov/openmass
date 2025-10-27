<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_friendly_redirects\ExistingSite;

use weitzman\DrupalTestTraits\DrupalTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\redirect\Entity\Redirect;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * @group mass_friendly_redirects
 */
final class FriendlyRedirectsUiTest extends ExistingSiteSelenium2DriverTestBase {

  use DrupalTrait;

  protected ?User $editor = NULL;
  protected ?Node $node = NULL;

  protected function setUp(): void {
    parent::setUp();

    // Ensure prefixes vocab exists (installed via config); create sample terms.
    $this->ensurePrefix('qag');
    $this->ensurePrefix('dor');

    // Create a simple node to test against.
    $this->node = Node::create([
      'type' => 'page',
      'title' => 'Friendly Redirects Test Node',
      'status' => 1,
    ]);
    $this->node->save();

    // Create an "editor" who can manage friendly redirects (no redirect admin).
    $this->editor = $this->createUser(['manage friendly redirects', 'access content']);
    $this->drupalLogin($this->editor);
  }

  public function testAjaxAddCreatesRedirectAndUpdatesTable(): void {
    $this->drupalGet($this->node->toUrl('edit-form'));

    // Select prefix + enter suffix.
    $this->assertSession()->selectExists('mass_friendly_redirects[prefix]')
      ->selectOption($this->getTermTidByName('qag'));
    $this->assertSession()->fieldExists('mass_friendly_redirects[suffix]')
      ->setValue('testxyz');

    // Click AJAX button.
    $this->getSession()->getPage()->pressButton('Add URL Redirect');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Table should include the new source (filtered view).
    $this->assertSession()->pageTextContains('/qag/testxyz');

    // Verify entity exists in DB and points at our node.
    $ids = \Drupal::entityTypeManager()->getStorage('redirect')->getQuery()
      ->condition('redirect_source__path', 'qag/testxyz')
      ->execute();
    $this->assertNotEmpty($ids, 'Redirect entity created.');
    /** @var \Drupal\redirect\Entity\Redirect $r */
    $r = Redirect::load(reset($ids));
    $this->assertSame('node/' . $this->node->id(), $r->get('redirect_redirect')->first()->getString());
    $this->assertSame(301, (int) $r->getStatusCode());
  }

  public function testDuplicateShowsLinkAndPreventsDuplicate(): void {
    // Pre-create a redirect so we can hit the duplicate path.
    $this->makeRedirect('qag/dup', $this->node->id());

    $this->drupalGet($this->node->toUrl('edit-form'));
    $this->assertSession()->selectExists('mass_friendly_redirects[prefix]')
      ->selectOption($this->getTermTidByName('qag'));
    $this->assertSession()->fieldExists('mass_friendly_redirects[suffix]')
      ->setValue('dup');

    $this->getSession()->getPage()->pressButton('Add URL Redirect');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Field-level error appears …
    $this->assertSession()->pageTextContains('already exists');
    // …and messenger includes a clickable link.
    $this->assertSession()->elementExists('css', '.messages--error a');

    // Still only one entity in storage.
    $ids = \Drupal::entityTypeManager()->getStorage('redirect')->getQuery()
      ->condition('redirect_source__path', 'qag/dup')
      ->execute();
    $this->assertCount(1, $ids);
  }

  public function testPrefixFilterHidesNonPrefixedRedirects(): void {
    // Create a same-target redirect that should NOT appear (no approved prefix).
    $this->makeRedirect('some/other/path', $this->node->id());

    $this->drupalGet($this->node->toUrl('edit-form'));
    // Table should NOT include that source.
    $this->assertSession()->pageTextNotContains('/some/other/path');
  }

  // ---------- Helpers ---------- //

  private function ensurePrefix(string $name): void {
    $exists = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()
      ->condition('vid', 'friendly_url_prefixes')
      ->condition('name', $name)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    if ($exists) {
      return;
    }
    Term::create([
      'vid' => 'friendly_url_prefixes',
      'name' => $name,
      'status' => 1,
    ])->save();
  }

  private function getTermTidByName(string $name): string {
    $tids = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()
      ->condition('vid', 'friendly_url_prefixes')
      ->condition('name', $name)
      ->accessCheck(FALSE)
      ->execute();
    $this->assertNotEmpty($tids, "Prefix term $name exists.");
    return (string) reset($tids);
  }

  private function makeRedirect(string $sourceNoSlash, int $nid): Redirect {
    $r = Redirect::create();
    $r->setSource($sourceNoSlash, []);
    $r->setRedirect('node/' . $nid);
    $r->setStatusCode(301);
    $r->save();
    return $r;
  }
}
