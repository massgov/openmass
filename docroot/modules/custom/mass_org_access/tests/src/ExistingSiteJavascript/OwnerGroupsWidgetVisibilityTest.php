<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_org_access\ExistingSiteJavascript;

use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Verifies the Organization Owner Groups widget visibility per role.
 *
 * Any role with edit access to the host entity must see the
 * `field_content_organization` widget on the add form — there is no
 * extra field-level guard. The enforcement switch state does not
 * change widget visibility.
 */
class OwnerGroupsWidgetVisibilityTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * CSS selector for the autocomplete input the widget submits.
   */
  private const WIDGET_INPUT_SELECTOR = '[name^="field_content_organization[target_id]"]';

  /**
   * CSS selector for the read-only display rendered by the widget alter.
   */
  private const WIDGET_DISPLAY_SELECTOR = '.oog-readonly-display';

  /**
   * Whether the test stored a previous enforcement value to restore.
   */
  private ?bool $previousEnforce;

  protected function setUp(): void {
    parent::setUp();
    $state = \Drupal::state();
    $this->previousEnforce = $state->get('mass_org_access.enforce');
    $state->delete('mass_org_access.enforce');
  }

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

    if ($expectedVisible) {
      $this->openPageInfoTab();
      $this->assertWidgetVisible(sprintf('node/%s as %s', $bundle, $role));
    }
    else {
      $this->assertWidgetAbsent(sprintf('node/%s as %s', $bundle, $role));
    }
  }

  /**
   * Widget visibility per role on the media.document add form (no tabs).
   *
   * @dataProvider mediaDocumentVisibilityProvider
   */
  public function testWidgetVisibilityOnMediaDocumentAddForm(string $role, bool $expectedVisible): void {
    $user = $this->createRoleUser($role);
    $this->drupalLogin($user);
    $this->drupalGet('media/add/document');

    if ($expectedVisible) {
      $this->assertWidgetVisible(sprintf('media/document as %s', $role));
    }
    else {
      $this->assertWidgetAbsent(sprintf('media/document as %s', $role));
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
   * Asserts the widget input + read-only display are present and visible.
   */
  private function assertWidgetVisible(string $context): void {
    $page = $this->getSession()->getPage();
    $input = $page->find('css', self::WIDGET_INPUT_SELECTOR);
    $display = $page->find('css', self::WIDGET_DISPLAY_SELECTOR);
    $this->assertNotNull(
      $input,
      sprintf('Expected Owner Groups input in DOM on %s.', $context)
    );
    $this->assertNotNull(
      $display,
      sprintf('Expected Owner Groups read-only display in DOM on %s.', $context)
    );
    $this->assertTrue(
      $display->isVisible(),
      sprintf('Owner Groups read-only display must be visible on %s.', $context)
    );
  }

  /**
   * Asserts no Owner Groups widget anywhere in the rendered form.
   */
  private function assertWidgetAbsent(string $context): void {
    $page = $this->getSession()->getPage();
    $this->assertNull(
      $page->find('css', self::WIDGET_INPUT_SELECTOR),
      sprintf('Owner Groups input must be absent on %s.', $context)
    );
    $this->assertNull(
      $page->find('css', self::WIDGET_DISPLAY_SELECTOR),
      sprintf('Owner Groups read-only display must be absent on %s.', $context)
    );
  }

  /**
   * Bundles × roles for the node add form.
   *
   * 28 bundles × 3 roles = 84 cases. Widget is visible to every role
   * with edit access to the host entity.
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
    $roles = [
      'administrator' => TRUE,
      'content_team' => TRUE,
      'editor' => TRUE,
    ];
    $cases = [];
    foreach ($bundles as $bundle) {
      foreach ($roles as $role => $expected_visible) {
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
   * Roles for the media.document add form (no Page Info tab).
   */
  public static function mediaDocumentVisibilityProvider(): array {
    return [
      'administrator-visible' => ['administrator', TRUE],
      'content_team-visible' => ['content_team', TRUE],
      'editor-visible' => ['editor', TRUE],
    ];
  }

}
