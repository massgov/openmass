<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_org_access\ExistingSiteJavascript;

use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Verifies the JS that augments Owner Groups from Organizations.
 *
 * The user_organization terms curated on an org_page's own
 * field_content_organization are added to a content entity's
 * field_content_organization when the author adds that org_page to
 * field_organizations, and removed again if the org_page is removed before
 * save. Covered across every node bundle
 * that carries both fields and media.document; one additional case
 * exercises real autocomplete typing with a dropdown click on
 * info_details so we know the end-to-end UI flow stays wired up.
 */
class OogAugmentFromOrganizationsTest extends OogAugmentTestBase {

  /**
   * Adding then removing an org_page syncs the mapped term into and out of OOG.
   *
   * @dataProvider entityProvider
   */
  public function testAddAndRemoveOrgPageSyncsMappedTerm(string $entityType, string $bundle): void {
    $context = $this->setupEditForm($entityType, $bundle);

    $session = $this->getSession();
    $page = $session->getPage();

    // The widget may live on field_organizations OR on a bundle-specific
    // ref field (field_binder_ref_organization, field_decision_ref_organization,
    // field_person_ref_org) that mass_validation later unions into
    // field_organizations. If none of those inputs is rendered, the
    // feature has no UI to drive on this bundle.
    $orgInputSelectors = '\'input[name^="field_organizations["][name$="[target_id]"], '
      . 'input[name^="field_binder_ref_organization["][name$="[target_id]"], '
      . 'input[name^="field_decision_ref_organization["][name$="[target_id]"], '
      . 'input[name^="field_person_ref_org["][name$="[target_id]"]\'';
    $hasOrgInput = $session->evaluateScript(
      'document.querySelector(' . $orgInputSelectors . ') !== null'
    );
    if (!$hasOrgInput) {
      $this->markTestSkipped(sprintf(
        '%s:%s does not render any editable organization widget.',
        $entityType,
        $bundle
      ));
    }

    // Wipe any pre-existing organization rows across all listened fields
    // so we start from a known baseline.
    $session->executeScript(
      'document.querySelectorAll(' . $orgInputSelectors . ').forEach(function(i){i.value="";});'
    );

    $orgValue = sprintf('%s (%d) - Organization', $context['orgPage']->label(), $context['orgPage']->id());
    $session->executeScript(sprintf(
      '(function(){var i=%s; i.value=%s;})();',
      self::ORG_INPUT_JS,
      json_encode($orgValue)
    ));

    $tidTag = sprintf('(%d)', $context['term']->id());
    $appeared = $page->waitFor(8, function () use ($session, $tidTag) {
      return strpos((string) $session->evaluateScript(self::OOG_INPUT_JS), $tidTag) !== FALSE;
    });
    $this->assertTrue(
      $appeared,
      sprintf(
        'Mapped term %s should appear in OOG within 8s for %s:%s.',
        $tidTag,
        $entityType,
        $bundle
      )
    );

    // REMOVE: clear the org input; auto-added term should leave.
    $session->executeScript(sprintf(
      '(function(){var i=%s; i.value="";})();',
      self::ORG_INPUT_JS
    ));

    $disappeared = $page->waitFor(8, function () use ($session, $tidTag) {
      return strpos((string) $session->evaluateScript(self::OOG_INPUT_JS), $tidTag) === FALSE;
    });
    $this->assertTrue(
      $disappeared,
      sprintf(
        'Mapped term %s should leave OOG within 8s for %s:%s.',
        $tidTag,
        $entityType,
        $bundle
      )
    );
  }

  /**
   * A manual OOG term (not added by JS) survives removal of an org_page.
   *
   * Uses info_details since the manual-term scenario is bundle-agnostic.
   */
  public function testManualOogTermSurvivesOrgPageRemoval(): void {
    $manualTerm = $this->createTerm(
      Vocabulary::load('user_organization'),
      ['name' => 'Manual OOG Term ' . $this->randomMachineName(6)]
    );
    $context = $this->setupEditForm('node', 'info_details', [
      'field_content_organization' => [['target_id' => $manualTerm->id()]],
    ]);

    $session = $this->getSession();
    $page = $session->getPage();

    $manualTag = sprintf('(%d)', $manualTerm->id());
    $autoTag = sprintf('(%d)', $context['term']->id());

    $orgValue = sprintf('%s (%d) - Organization', $context['orgPage']->label(), $context['orgPage']->id());
    $session->executeScript(sprintf(
      '(function(){var i=%s; i.value=%s;})();',
      self::ORG_INPUT_JS,
      json_encode($orgValue)
    ));
    $page->waitFor(8, function () use ($session, $autoTag) {
      return strpos((string) $session->evaluateScript(self::OOG_INPUT_JS), $autoTag) !== FALSE;
    });
    $session->executeScript(sprintf(
      '(function(){var i=%s; i.value="";})();',
      self::ORG_INPUT_JS
    ));
    $page->waitFor(8, function () use ($session, $autoTag) {
      return strpos((string) $session->evaluateScript(self::OOG_INPUT_JS), $autoTag) === FALSE;
    });

    $finalOog = (string) $session->evaluateScript(self::OOG_INPUT_JS);
    $this->assertStringContainsString(
      $manualTag,
      $finalOog,
      'Manual OOG term must survive an add+remove cycle of an unrelated org_page.'
    );
    $this->assertStringNotContainsString(
      $autoTag,
      $finalOog,
      'Auto-added term must be gone after the org_page that brought it in is removed.'
    );
  }

  /**
   * Initial-render sync augments OOG from pre-filled organizations.
   *
   * Covers the path where the organization field arrives already
   * populated (e.g. mass_utility's user defaults on /node/add/* or
   * legacy content): JS must fetch the mapped terms and append them
   * to Permission Groups on first sync, not only on subsequent user
   * edits. Runs against every distinct organization-field flavor
   * (field_organizations on common bundles + the bundle-specific
   * refs on binder / decision / person).
   *
   * @dataProvider organizationFieldProvider
   */
  public function testInitialSyncAugmentsFromPreFilledOrganizations(string $entityType, string $bundle, string $orgField): void {
    $context = $this->setupEditForm($entityType, $bundle);
    $context['entity']->set($orgField, [['target_id' => $context['orgPage']->id()]]);
    $context['entity']->set('field_content_organization', []);
    $context['entity']->setSyncing(TRUE);
    $context['entity']->save();
    $this->drupalGet(sprintf('%s/%d/edit', $entityType, $context['entity']->id()));

    $session = $this->getSession();
    $page = $session->getPage();
    $session->executeScript(
      'document.querySelectorAll("details").forEach(function(d){d.setAttribute("open","open");});'
    );

    $tidTag = sprintf('(%d)', $context['term']->id());
    $appeared = $page->waitFor(8, function () use ($session, $tidTag) {
      return strpos((string) $session->evaluateScript(self::OOG_INPUT_JS), $tidTag) !== FALSE;
    });
    $this->assertTrue(
      $appeared,
      sprintf(
        'Initial JS sync must add mapped term %s for pre-filled %s on %s:%s.',
        $tidTag,
        $orgField,
        $entityType,
        $bundle
      )
    );
  }

  /**
   * Bundles × organization field name for the initial-sync test.
   */
  public static function organizationFieldProvider(): array {
    return [
      'node:info_details (field_organizations)' => ['node', 'info_details', 'field_organizations'],
      'node:news (field_organizations)' => ['node', 'news', 'field_organizations'],
      'node:binder (field_binder_ref_organization)' => ['node', 'binder', 'field_binder_ref_organization'],
      'node:decision (field_decision_ref_organization)' => ['node', 'decision', 'field_decision_ref_organization'],
      'node:person (field_person_ref_org)' => ['node', 'person', 'field_person_ref_org'],
      'media:document (field_organizations)' => ['media', 'document', 'field_organizations'],
    ];
  }

  /**
   * Removing an org drops its mapped term even if it pre-existed in OOG.
   *
   * Per spec, removing an organization removes the related Permission Group
   * (and parents). So a term that maps to the removed org_page must be
   * dropped from OOG even when it was already there (e.g. from drush
   * backfill) — not only when this session added it.
   */
  public function testRemovingOrgDropsItsMappedTermEvenIfPreExisting(): void {
    $context = $this->setupEditForm('node', 'info_details', [
      'field_organizations' => [],
    ]);
    // Pre-load: org_page is in field_organizations, and the same term it
    // maps to is already in OOG (e.g. set by drush moab).
    $context['entity']->set('field_organizations', [['target_id' => $context['orgPage']->id()]]);
    $context['entity']->set('field_content_organization', [['target_id' => $context['term']->id()]]);
    $context['entity']->setSyncing(TRUE);
    $context['entity']->save();
    $this->drupalGet('node/' . $context['entity']->id() . '/edit');

    $session = $this->getSession();
    $page = $session->getPage();
    $session->executeScript(
      'document.querySelectorAll("details").forEach(function(d){d.setAttribute("open","open");});'
    );

    // Give the initial sync a chance to record the org's mapped set.
    sleep(2);

    // Remove the organization — its related Permission Group must drop.
    $session->executeScript(sprintf(
      '(function(){var i=%s; i.value=""; i.dispatchEvent(new Event("change",{bubbles:true}));})();',
      self::ORG_INPUT_JS
    ));

    $tidTag = sprintf('(%d)', $context['term']->id());
    $dropped = $page->waitFor(8, function () use ($session, $tidTag) {
      return strpos((string) $session->evaluateScript(self::OOG_INPUT_JS), $tidTag) === FALSE;
    });
    $this->assertTrue(
      $dropped,
      'A term mapped to the removed org must be dropped from OOG, even if it pre-existed.'
    );
  }

  /**
   * Real-typing flow on info_details (type a substring, click dropdown).
   *
   * Uses an existing org_page (rather than a freshly created one) because
   * Drupal's view-based entity autocomplete returns cached/indexed
   * results and may not surface a brand-new node within the test window.
   */
  public function testAutocompleteRealTypingPicksFirstSuggestion(): void {
    [$orgPage, $term] = $this->pickPublishedOrgPageWithMappedTerm();

    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'OOG Augment Typing ' . $this->randomMachineName(6),
      'status' => 1,
    ]);
    // Use editor role — admins bypass the endpoint access check; an
    // editor exercises the path real authors take.
    $user = $this->createUser(['bypass node access']);
    $user->addRole('editor');
    $user->activate();
    $user->save();
    $this->drupalLogin($user);
    $this->drupalGet('node/' . $node->id() . '/edit');

    $session = $this->getSession();
    $page = $session->getPage();
    $session->executeScript(
      'document.querySelectorAll("details").forEach(function(d){d.setAttribute("open","open");});'
    );

    // Clear any baseline organization rows.
    $session->executeScript(
      'document.querySelectorAll(\'input[name^="field_organizations["][name$="[target_id]"]\').forEach(function(i){i.value="";});'
    );

    // Native keystrokes via Mink: setValue() on Selenium2 driver issues
    // real key events that Drupal's autocomplete keyup listener picks
    // up, unlike dispatchEvent(KeyboardEvent) which jQuery silently
    // ignores.
    // Use a slightly trimmed needle so the match is unambiguous but
    // doesn't perfectly equal the label (autocomplete sometimes hides
    // exact matches). We keep enough characters to be specific.
    $fullLabel = $orgPage->label();
    $needle = mb_substr($fullLabel, 0, max(8, mb_strlen($fullLabel) - 3));
    $context = ['term' => $term];
    $orgField = $page->find('css', 'input[name="field_organizations[0][target_id]"]');
    $this->assertNotNull($orgField, 'field_organizations[0] input must exist.');
    $orgField->focus();
    $orgField->setValue($needle);
    // setValue() in Selenium2 sets the value but does not always emit
    // the keyup that jQuery UI autocomplete listens for — force the
    // search through the public widget API.
    $session->executeScript(sprintf(
      '(function(){var $i = jQuery(%s); if ($i.autocomplete) {$i.focus(); $i.autocomplete("search", %s);}})();',
      json_encode('input[name="field_organizations[0][target_id]"]'),
      json_encode($needle)
    ));

    // Drupal autocomplete debounces ~300ms then fires AJAX. Wait until
    // a dropdown item matching our org_page label appears.
    $matchScript = sprintf(
      'Array.from(document.querySelectorAll(".ui-autocomplete li")).filter(function(li){return li.textContent.indexOf(%s) !== -1;}).length',
      json_encode($needle)
    );
    $hasDropdown = $page->waitFor(15, function () use ($session, $matchScript) {
      return (int) $session->evaluateScript($matchScript) > 0;
    });
    if (!$hasDropdown) {
      // Diagnostic: dump what the page actually has so we know why.
      $diag = $session->evaluateScript(
        '({inputValue: document.querySelector(\'input[name="field_organizations[0][target_id]"]\')?.value, dropdownCount: document.querySelectorAll(".ui-autocomplete").length, anyItems: document.querySelectorAll(".ui-autocomplete li").length, allLiTexts: Array.from(document.querySelectorAll(".ui-autocomplete li")).slice(0,5).map(function(l){return l.textContent.slice(0,80);})})'
      );
      $this->fail(sprintf(
        'Autocomplete suggestion containing "%s" not found in 10s. Diag: %s',
        $needle,
        json_encode($diag)
      ));
    }

    // Click the matching suggestion — jQuery UI writes the picked value
    // to the input via .val() (no native events), our polling fallback
    // catches the change.
    $session->executeScript(sprintf(
      'Array.from(document.querySelectorAll(".ui-autocomplete li")).find(function(li){return li.textContent.indexOf(%s) !== -1;})?.querySelector("a, .ui-menu-item-wrapper")?.click();',
      json_encode($needle)
    ));

    $tidTag = sprintf('(%d)', $context['term']->id());
    $appeared = $page->waitFor(8, function () use ($session, $tidTag) {
      return strpos((string) $session->evaluateScript(self::OOG_INPUT_JS), $tidTag) !== FALSE;
    });
    $this->assertTrue(
      $appeared,
      sprintf('Mapped term %s should appear in OOG after autocomplete pick.', $tidTag)
    );
  }

  /**
   * Real-typing flow on the media.document ADD form.
   *
   * Reproduces the path where authors create a new document and pick
   * an organization via the autocomplete dropdown — the typed value
   * arrives via jQuery UI's .val() (no native events), and our
   * polling fallback must catch it and augment Permission Groups.
   */
  public function testAutocompleteOnMediaAddPopulatesPermissionGroups(): void {
    [$orgPage, $term] = $this->pickPublishedOrgPageWithMappedTerm();

    // Editor role is the one that triggers the real-world bug — admins
    // bypass everything, so we have to log in as a regular editor to
    // exercise the endpoint access path that authors actually use.
    $user = $this->createUser(['bypass node access', 'administer media', 'create document media']);
    $user->addRole('editor');
    $user->activate();
    $user->save();
    $this->drupalLogin($user);
    $this->drupalGet('media/add/document');

    $session = $this->getSession();
    $page = $session->getPage();
    $session->executeScript(
      'document.querySelectorAll("details").forEach(function(d){d.setAttribute("open","open");});'
    );

    // Clear any baseline rows so the diff is clean — mass_utility may
    // have pre-filled field_organizations[0] from the user's own org,
    // which the JS then mirrors into the Permission Groups field.
    $session->executeScript(
      'document.querySelectorAll(\'input[name^="field_organizations["][name$="[target_id]"]\').forEach(function(i){i.value="";});'
      . ' var oog=document.querySelector(\'input[name="field_content_organization[target_id]"]\'); if (oog) { oog.value=""; }'
    );

    $fullLabel = $orgPage->label();
    $needle = mb_substr($fullLabel, 0, max(8, mb_strlen($fullLabel) - 3));
    $orgField = $page->find('css', 'input[name="field_organizations[0][target_id]"]');
    $this->assertNotNull($orgField, 'field_organizations[0] input must exist on media add form.');
    $orgField->focus();
    $orgField->setValue($needle);
    $session->executeScript(sprintf(
      '(function(){var $i = jQuery(%s); if ($i.autocomplete) {$i.focus(); $i.autocomplete("search", %s);}})();',
      json_encode('input[name="field_organizations[0][target_id]"]'),
      json_encode($needle)
    ));

    $matchScript = sprintf(
      'Array.from(document.querySelectorAll(".ui-autocomplete li")).filter(function(li){return li.textContent.indexOf(%s) !== -1;}).length',
      json_encode($needle)
    );
    $hasDropdown = $page->waitFor(15, function () use ($session, $matchScript) {
      return (int) $session->evaluateScript($matchScript) > 0;
    });
    $this->assertTrue(
      $hasDropdown,
      sprintf('Autocomplete dropdown must surface "%s" on media add form.', $needle)
    );
    $session->executeScript(sprintf(
      'Array.from(document.querySelectorAll(".ui-autocomplete li")).find(function(li){return li.textContent.indexOf(%s) !== -1;})?.querySelector("a, .ui-menu-item-wrapper")?.click();',
      json_encode($needle)
    ));

    $tidTag = sprintf('(%d)', $term->id());
    $appeared = $page->waitFor(8, function () use ($session, $tidTag) {
      return strpos((string) $session->evaluateScript(self::OOG_INPUT_JS), $tidTag) !== FALSE;
    });
    $this->assertTrue(
      $appeared,
      sprintf('Mapped term %s should appear in Permission Groups after autocomplete pick on media add form.', $tidTag)
    );
  }

}
