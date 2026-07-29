<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_org_access\ExistingSiteJavascript;

use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Verifies Permission Groups widget visibility per role and bundle.
 *
 * Release 1 rule: the Permission Groups field (field_content_organization)
 * is shown only to administrators (the `view permission groups field`
 * permission, which Content Administrators do NOT have) on every bundle, plus
 * anyone editing an Organization page. For everyone else — Content
 * Administrators, editors, authors — on every other bundle the field is
 * hidden, but it stays in the form (in a `.oog-hidden-from-author` wrapper) so
 * its value still derives from Organization(s) and saves. So "hidden" means
 * present-but-not-visible, never removed.
 */
class OwnerGroupsWidgetVisibilityTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * CSS selector for the input the widget submits (stays in the DOM).
   */
  private const WIDGET_INPUT_SELECTOR = '[name^="field_content_organization[target_id]"]';

  /**
   * CSS selector for the read-only display rendered by the widget alter.
   */
  private const WIDGET_DISPLAY_SELECTOR = '.oog-readonly-display';

  /**
   * CSS selector for the wrapper that hides the field from authors/editors.
   */
  private const HIDDEN_WRAPPER_SELECTOR = '.oog-hidden-from-author';

  /**
   * Roles that may see/manage Permission Groups on every bundle.
   *
   * Only administrators — Content Administrators are intentionally excluded.
   */
  private const MANAGER_ROLES = ['administrator'];

  /**
   * Whether the test stored a previous enforcement value to restore.
   */
  private ?bool $previousEnforce;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $state = \Drupal::state();
    $this->previousEnforce = $state->get('mass_org_access.enforce');
    $state->delete('mass_org_access.enforce');
    // Debug mode is gated by a URL secret these tests never pass, so the
    // hidden-from-author assertions stay deterministic with no extra setup.
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->previousEnforce !== NULL) {
      \Drupal::state()->set('mass_org_access.enforce', $this->previousEnforce);
    }
    parent::tearDown();
  }

  /**
   * Widget visibility per bundle and role on the node add form.
   *
   * @dataProvider nodeWidgetVisibilityProvider
   */
  public function testWidgetVisibilityOnNodeAddForm(string $bundle, string $role, bool $expectedVisible): void {
    $user = $this->createRoleUser($role);
    $this->drupalLogin($user);
    $this->drupalGet('node/add/' . $bundle);
    $this->openPageInfoTab();

    $context = sprintf('node/%s as %s', $bundle, $role);
    if ($expectedVisible) {
      $this->assertFieldVisible($context);
    }
    else {
      $this->assertFieldHiddenButPresent($context);
    }
  }

  /**
   * Widget visibility per role on the media.document add form (no tabs).
   *
   * Media has no Organization page bundle, so only the manager roles see it.
   *
   * @dataProvider mediaDocumentVisibilityProvider
   */
  public function testWidgetVisibilityOnMediaDocumentAddForm(string $role, bool $expectedVisible): void {
    $user = $this->createRoleUser($role);
    $this->drupalLogin($user);
    $this->drupalGet('media/add/document');

    $context = sprintf('media/document as %s', $role);
    if ($expectedVisible) {
      $this->assertFieldVisible($context);
    }
    else {
      $this->assertFieldHiddenButPresent($context);
    }
  }

  /**
   * Builds a user with the requested role plus enough perms to reach add forms.
   *
   * `bypass node access` + `administer media` + `create document media`
   * unblock GET on /node/add/* and /media/add/document regardless of
   * the test role's bundle-specific permissions.
   */
  private function createRoleUser(string $role) {
    $user = $this->createUser([
      'bypass node access',
      'administer media',
      'create document media',
    ], 'oog_vis_' . $role . '_' . $this->randomMachineName(6));
    $user->addRole($role);
    $user->activate();
    $user->save();
    return $user;
  }

  /**
   * Opens the Page Info field group + every <details> so the widget shows.
   */
  private function openPageInfoTab(): void {
    $this->getSession()->executeScript("
      (function () {
        var pageInfoTab = document.querySelector('.horizontal-tab-button a[href=\"#edit-group-page-info\"]');
        if (pageInfoTab) {
          pageInfoTab.click();
        }
        document.querySelectorAll('details').forEach(function (d) {
          d.setAttribute('open', 'open');
        });
      })();
    ");
  }

  /**
   * Asserts the field is present, not wrapped for hiding, and visible.
   */
  private function assertFieldVisible(string $context): void {
    $page = $this->getSession()->getPage();
    $input = $page->find('css', self::WIDGET_INPUT_SELECTOR);
    $display = $page->find('css', self::WIDGET_DISPLAY_SELECTOR);
    $this->assertNotNull(
      $input,
      sprintf('Expected Permission Groups input in DOM on %s.', $context)
    );
    $this->assertNotNull(
      $display,
      sprintf('Expected Permission Groups display in DOM on %s.', $context)
    );
    $this->assertNull(
      $page->find('css', self::HIDDEN_WRAPPER_SELECTOR),
      sprintf('Permission Groups must NOT be wrapped for hiding on %s.', $context)
    );
    $this->assertTrue(
      $display->isVisible(),
      sprintf('Permission Groups display must be visible on %s.', $context)
    );
  }

  /**
   * Asserts the field is hidden from the user but still present for submit.
   *
   * The input stays in the DOM (so the JS-derived value posts and the
   * org-taxonomy permission data is kept populated) but the field sits in
   * the `.oog-hidden-from-author` wrapper and is not visible.
   */
  private function assertFieldHiddenButPresent(string $context): void {
    $page = $this->getSession()->getPage();
    $input = $page->find('css', self::WIDGET_INPUT_SELECTOR);
    $display = $page->find('css', self::WIDGET_DISPLAY_SELECTOR);
    $this->assertNotNull(
      $input,
      sprintf('Permission Groups input must stay in the DOM (for JS + submit) on %s.', $context)
    );
    $this->assertNotNull(
      $page->find('css', self::HIDDEN_WRAPPER_SELECTOR),
      sprintf('Permission Groups must be wrapped in the hide wrapper on %s.', $context)
    );
    $this->assertNotNull(
      $display,
      sprintf('Permission Groups display element should still render on %s.', $context)
    );
    $this->assertFalse(
      $display->isVisible(),
      sprintf('Permission Groups must not be visible to the user on %s.', $context)
    );
  }

  /**
   * Bundles × roles for the node add form.
   *
   * Visible when the role is a manager (administrator) OR the bundle is the
   * Organization page. 28 bundles × 4 roles = 112 cases.
   */
  public static function nodeWidgetVisibilityProvider(): array {
    $bundles = [
      'action',
      'advisory',
      'alert',
      'binder',
      'campaign_landing',
      'contact_information',
      'curated_list',
      'decision',
      'decision_tree',
      'decision_tree_branch',
      'decision_tree_conclusion',
      'event',
      'external_data_resource',
      'fee',
      'form_page',
      'glossary',
      'guide_page',
      'how_to_page',
      'info_details',
      'location',
      'location_details',
      'news',
      'org_page',
      'person',
      'regulation',
      'rules',
      'service_page',
      'topic_page',
    ];
    $roles = ['administrator', 'content_team', 'editor', 'author'];
    $cases = [];
    foreach ($bundles as $bundle) {
      foreach ($roles as $role) {
        $expected_visible = in_array($role, self::MANAGER_ROLES, TRUE)
          || $bundle === 'org_page';
        $key = sprintf(
          '%s-%s-%s',
          $bundle,
          $role,
          $expected_visible ? 'visible' : 'hidden'
        );
        $cases[$key] = [$bundle, $role, $expected_visible];
      }
    }
    return $cases;
  }

  /**
   * Roles for the media.document add form (no Organization page bundle).
   *
   * Only administrators see it; Content Administrators are now excluded.
   */
  public static function mediaDocumentVisibilityProvider(): array {
    return [
      'administrator-visible' => ['administrator', TRUE],
      'content_team-hidden' => ['content_team', FALSE],
      'editor-hidden' => ['editor', FALSE],
      'author-hidden' => ['author', FALSE],
    ];
  }

}
