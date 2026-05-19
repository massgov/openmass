<?php

declare(strict_types=1);

namespace Drupal\Tests\mass_org_access\ExistingSiteJavascript;

use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Verifies the JS that augments Owner Groups from Organizations.
 *
 * The user_organization term whose field_state_organization points at
 * an org_page is added to field_content_organization when the author
 * adds that org_page to field_organizations, and removed again if the
 * org_page is removed before save.
 */
class OogAugmentFromOrganizationsTest extends ExistingSiteSelenium2DriverTestBase {

  use TaxonomyCreationTrait;

  private const OOG_INPUT_JS = 'document.querySelector(\'input[name="field_content_organization[target_id]"]\').value || ""';

  private const ORG_INPUT_JS = 'document.querySelector(\'input[name="field_organizations[0][target_id]"]\')';

  protected function setUp(): void {
    parent::setUp();
    \Drupal::state()->delete('mass_org_access.enforce');
  }

  /**
   * Adds an org_page, asserts the mapped term lands in OOG; removes it, asserts it leaves.
   */
  public function testAddAndRemoveOrgPageSyncsMappedTerm(): void {
    $orgPage = $this->createNode([
      'type' => 'org_page',
      'title' => 'OOG Augment OrgPage ' . $this->randomMachineName(6),
      'status' => 1,
    ]);

    $term = $this->createTerm(
      \Drupal\taxonomy\Entity\Vocabulary::load('user_organization'),
      [
        'name' => 'OOG Augment Term ' . $this->randomMachineName(6),
        'field_state_organization' => $orgPage->id(),
      ]
    );

    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'OOG Augment Info ' . $this->randomMachineName(6),
      'status' => 1,
    ]);

    $user = $this->createUser(['bypass node access']);
    $user->addRole('administrator');
    $user->activate();
    $user->save();

    $this->drupalLogin($user);
    $this->drupalGet('node/' . $node->id() . '/edit');

    $session = $this->getSession();
    $page = $session->getPage();

    // Open every <details> so the org/oog inputs are interactable.
    $session->executeScript(
      'document.querySelectorAll("details").forEach(function(d){d.setAttribute("open","open");});'
    );

    // ADD: write the org_page label into field_organizations[0].
    // Mimic Drupal entity-autocomplete output: "Label (NID) - Bundle".
    $orgValue = sprintf('%s (%d) - Organization', $orgPage->label(), $orgPage->id());
    $session->executeScript(sprintf(
      '(function(){var i=%s; i.value=%s; i.dispatchEvent(new Event("change",{bubbles:true}));})();',
      self::ORG_INPUT_JS,
      json_encode($orgValue)
    ));

    $tidTag = sprintf('(%d)', $term->id());
    $appeared = $page->waitFor(5, function () use ($session, $tidTag) {
      return strpos((string) $session->evaluateScript(self::OOG_INPUT_JS), $tidTag) !== FALSE;
    });
    $this->assertTrue(
      $appeared,
      sprintf('Mapped term %s should appear in OOG within 5s of adding org_page.', $tidTag)
    );

    // REMOVE: clear the org input; the auto-added term should leave.
    $session->executeScript(sprintf(
      '(function(){var i=%s; i.value=""; i.dispatchEvent(new Event("change",{bubbles:true}));})();',
      self::ORG_INPUT_JS
    ));

    $disappeared = $page->waitFor(5, function () use ($session, $tidTag) {
      return strpos((string) $session->evaluateScript(self::OOG_INPUT_JS), $tidTag) === FALSE;
    });
    $this->assertTrue(
      $disappeared,
      sprintf('Mapped term %s should leave OOG within 5s of removing org_page.', $tidTag)
    );
  }

  /**
   * A manual OOG term (not added by JS) survives removal of the org_page.
   */
  public function testManualOogTermSurvivesOrgPageRemoval(): void {
    $orgPage = $this->createNode([
      'type' => 'org_page',
      'title' => 'OOG Manual OrgPage ' . $this->randomMachineName(6),
      'status' => 1,
    ]);
    $autoTerm = $this->createTerm(
      \Drupal\taxonomy\Entity\Vocabulary::load('user_organization'),
      [
        'name' => 'Auto Term ' . $this->randomMachineName(6),
        'field_state_organization' => $orgPage->id(),
      ]
    );
    $manualTerm = $this->createTerm(
      \Drupal\taxonomy\Entity\Vocabulary::load('user_organization'),
      ['name' => 'Manual Term ' . $this->randomMachineName(6)]
    );
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'OOG Manual Info ' . $this->randomMachineName(6),
      'status' => 1,
      'field_content_organization' => [['target_id' => $manualTerm->id()]],
    ]);

    $user = $this->createUser(['bypass node access']);
    $user->addRole('administrator');
    $user->activate();
    $user->save();

    $this->drupalLogin($user);
    $this->drupalGet('node/' . $node->id() . '/edit');

    $session = $this->getSession();
    $page = $session->getPage();
    $session->executeScript(
      'document.querySelectorAll("details").forEach(function(d){d.setAttribute("open","open");});'
    );

    $manualTag = sprintf('(%d)', $manualTerm->id());
    $autoTag = sprintf('(%d)', $autoTerm->id());

    // ADD then REMOVE the org_page.
    // Mimic Drupal entity-autocomplete output: "Label (NID) - Bundle".
    $orgValue = sprintf('%s (%d) - Organization', $orgPage->label(), $orgPage->id());
    $session->executeScript(sprintf(
      '(function(){var i=%s; i.value=%s; i.dispatchEvent(new Event("change",{bubbles:true}));})();',
      self::ORG_INPUT_JS,
      json_encode($orgValue)
    ));
    $page->waitFor(5, function () use ($session, $autoTag) {
      return strpos((string) $session->evaluateScript(self::OOG_INPUT_JS), $autoTag) !== FALSE;
    });
    $session->executeScript(sprintf(
      '(function(){var i=%s; i.value=""; i.dispatchEvent(new Event("change",{bubbles:true}));})();',
      self::ORG_INPUT_JS
    ));
    $page->waitFor(5, function () use ($session, $autoTag) {
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

}
