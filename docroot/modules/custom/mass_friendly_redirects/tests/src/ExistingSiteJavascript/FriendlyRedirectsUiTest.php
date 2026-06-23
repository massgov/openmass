<?php

namespace Drupal\Tests\mass_friendly_redirects\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\redirect\Entity\Redirect;
use Drupal\user\Entity\Role;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\UserInterface;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Real user interaction tests for friendly redirects.
 */
class FriendlyRedirectsUiTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Creates and returns a user with editor permissions.
   */
  private function createEditorUser(): UserInterface {
    $editor_role = Role::load('editor');
    $this->assertNotNull($editor_role, 'Editor role exists.');
    $this->assertTrue($editor_role->hasPermission('manage friendly redirects'), 'Editor role has "manage friendly redirects" permission.');
    $content_team_role = Role::load('content_team');
    $this->assertNotNull($content_team_role, 'Content team role exists.');
    $this->assertTrue($content_team_role->hasPermission('manage friendly redirects'), 'Content team role has "manage friendly redirects" permission.');

    $user = $this->createUser();
    $user->addRole('editor');
    $user->addRole('content_team');
    $user->activate();
    $user->save();
    return $user;
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
    $this->markEntityForCleanup($term);
    return $term;
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
   * Creates a redirect entity.
   */
  private function createRedirect(string $sourcePath, string $targetUri, int $statusCode = 301): Redirect {
    $redirect = Redirect::create();
    $redirect->setSource($sourcePath);
    $redirect->setRedirect($targetUri);
    $redirect->setStatusCode($statusCode);
    $redirect->setLanguage('en');
    $redirect->save();
    $this->markEntityForCleanup($redirect);
    return $redirect;
  }

  /**
   * Opens the Friendly URLs details element in the form.
   */
  private function openFriendlyUrlsSection(): void {
    $this->getSession()->executeScript("
      (function () {
        var pageInfoTab = document.querySelector('.horizontal-tab-button a[href=\"#edit-group-page-info\"]');
        if (pageInfoTab) {
          pageInfoTab.click();
        }
        var summary = document.querySelector('[data-drupal-selector=\"edit-mass-friendly-redirects\"] > summary');
        if (summary) {
          summary.click();
        }
        var details = document.querySelector('[data-drupal-selector=\"edit-mass-friendly-redirects\"]');
        if (details) {
          details.setAttribute('open', 'open');
        }
      })();
    ");
    $this->getSession()->wait(1000);
  }

  /**
   * Asserts editor sees the Friendly URLs UI.
   */
  public function testEditorSeesFriendlyRedirectUi(): void {
    $editor = $this->createEditorUser();
    $this->drupalLogin($editor);

    $this->createPrefixTerm($this->friendlyToken('masshealth'));

    $node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Friendly Redirect JS Visibility',
      'status' => 1,
      MassModeration::FIELD_NAME => MassModeration::PUBLISHED,
    ]);

    $this->visit($node->toUrl()->toString() . '/edit');
    $this->openFriendlyUrlsSection();

    $assert = $this->assertSession();
    $assert->elementExists('css', '[data-drupal-selector="edit-mass-friendly-redirects"]');
    $assert->fieldExists('mass_friendly_redirects[suffix]');
    $assert->buttonExists('Add Friendly URL');
    $assert->elementNotExists('css', '[data-drupal-selector="edit-path-redirect"]');
  }

  /**
   * Asserts existing friendly redirect appears in editor UI.
   */
  public function testEditorSeesExistingFriendlyRedirectRow(): void {
    $editor = $this->createEditorUser();
    $this->drupalLogin($editor);

    $prefix = $this->friendlyToken('dor');
    $this->createPrefixTerm($prefix);

    $node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Friendly Redirect JS Existing Row',
      'status' => 1,
      MassModeration::FIELD_NAME => MassModeration::PUBLISHED,
    ]);

    $suffix = $this->friendlyToken('file');
    $source = $prefix . '/' . $suffix;
    $this->createRedirect($source, 'node/' . $node->id(), 301);

    $this->visit($node->toUrl()->toString() . '/edit');
    $this->openFriendlyUrlsSection();

    $this->assertSession()->pageTextContains('/' . $source);
    $this->assertSession()->buttonNotExists('Add Friendly URL');
  }

  /**
   * Asserts editor can add a new friendly redirect via UI.
   */
  public function testEditorCanAddFriendlyRedirect(): void {
    $editor = $this->createEditorUser();
    $this->drupalLogin($editor);

    $prefix = $this->friendlyToken('masshealth');
    $prefix_term = $this->createPrefixTerm($prefix);
    $suffix = $this->friendlyToken('vaccine');
    $source = $prefix . '/' . $suffix;

    $node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Friendly Redirect JS Add New',
      'status' => 1,
      MassModeration::FIELD_NAME => MassModeration::PUBLISHED,
    ]);

    $this->visit($node->toUrl()->toString() . '/edit');
    $this->openFriendlyUrlsSection();

    // The prefix uses select2; set the underlying select directly.
    $this->getSession()->executeScript(
      "var el = document.querySelector('select[name=\"mass_friendly_redirects[prefix]\"]'); if (el) { el.value = '" . $prefix_term->id() . "'; el.dispatchEvent(new Event('change', {bubbles: true})); }"
    );
    $this->getSession()->getPage()->fillField('mass_friendly_redirects[suffix]', $suffix);
    $this->getSession()->getPage()->pressButton('Add Friendly URL');

    $this->getSession()->wait(
      5000,
      "document.body.innerText.indexOf('/" . $source . "') !== -1"
    );

    $this->assertSession()->pageTextContains('/' . $source);
    $ids = \Drupal::entityTypeManager()->getStorage('redirect')->getQuery()
      ->accessCheck(FALSE)
      ->condition('redirect_source__path', $source)
      ->execute();
    $this->assertNotEmpty($ids, 'Redirect entity is created for the friendly source path.');
  }

}
