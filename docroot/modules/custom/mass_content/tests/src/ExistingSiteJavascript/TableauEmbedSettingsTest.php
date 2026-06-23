<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\paragraphs\Entity\Paragraph;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Tests the Tableau embed settings (DP-47145) on node edit forms.
 *
 * Covers every form path where a tableau_embed paragraph can be edited:
 * - org_page: field_organization_sections > org_section_long_form >
 *   field_section_long_form_content (classic nested paragraphs widget,
 *   handled by the field widget hooks in TableauEmbedFormHooks).
 * - service_page: field_service_sections (layout paragraphs builder).
 * - info_details: field_info_details_sections (layout paragraphs builder).
 * Both builder forms are handled by the
 * form_layout_paragraphs_component_form_alter hook.
 */
class TableauEmbedSettingsTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Creates an administrator user and logs in.
   */
  private function loginAsAdmin(): void {
    $user = $this->createUser();
    $user->addRole('administrator');
    $user->activate();
    $user->save();
    $this->drupalLogin($user);
  }

  /**
   * Creates a tableau_embed paragraph.
   */
  private function createTableauParagraph(array $values = []): Paragraph {
    $paragraph = Paragraph::create($values + [
      'type' => 'tableau_embed',
      'field_tableau_embed_type' => 'connected_apps',
      'field_url' => ['uri' => 'https://prod-useast-b.online.tableau.com/#/site/test/views/Test/Dashboard'],
      'field_tableau_url_token' => ['uri' => 'https://localhost/tableau-test-token.json'],
      'field_tabl_administrative_title' => 'Tableau settings test viz',
    ]);
    $paragraph->save();
    $this->markEntityForCleanup($paragraph);
    return $paragraph;
  }

  /**
   * Wraps a paragraph into an org_section_long_form section paragraph.
   */
  private function createSectionParagraph(Paragraph $child): Paragraph {
    $section = Paragraph::create([
      'type' => 'org_section_long_form',
      'field_section_long_form_content' => [['entity' => $child]],
    ]);
    $section->save();
    $this->markEntityForCleanup($section);
    return $section;
  }

  /**
   * Creates a service_page node hosting a tableau_embed paragraph.
   */
  private function createServicePageWithTableau(Paragraph $tableau) {
    $org = $this->createNode([
      'type' => 'org_page',
      'title' => 'Tableau Test Org',
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
    return $this->createNode([
      'type' => 'service_page',
      'title' => 'Tableau Settings Service Page',
      'field_service_lede' => 'Tableau settings test lede',
      'field_organizations' => [$org->id()],
      'field_primary_parent' => [$org->id()],
      'field_service_sections' => [['entity' => $tableau]],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);
  }

  /**
   * Opens the Content horizontal tab and expands all collapsed details.
   */
  private function openContentTabAndDetails(): void {
    $this->getSession()->executeScript("
      document.querySelectorAll('details:not([open])').forEach(function (d) { d.open = true; });
      var tab = document.querySelector('.horizontal-tab-button a[href=\"#edit-group-content\"]');
      if (tab) { tab.click(); }
      document.querySelectorAll('details:not([open])').forEach(function (d) { d.open = true; });
    ");
    $this->getSession()->wait(500);
  }

  /**
   * Finds a tableau setting select by field name (classic widget or modal).
   */
  private function findTableauSelect(string $field_name) {
    $element = $this->getSession()->getPage()->find('css', 'select[name*="' . $field_name . '"]');
    $this->assertNotNull($element, $field_name . ' select is present on the form.');
    return $element;
  }

  /**
   * Selects a value and waits for #states to react.
   */
  private function selectAndWait($select, string $value): void {
    $select->selectOption($value);
    $this->getSession()->wait(500);
  }

  /**
   * Asserts visibility of the three Tableau settings fields.
   */
  private function assertSettingsVisibility(bool $toolbar, bool $data_details, bool $share_options, string $scenario): void {
    $this->assertSame($toolbar, $this->findTableauSelect('field_tableau_toolbar')->isVisible(), "Toolbar visibility ($scenario)");
    $this->assertSame($data_details, $this->findTableauSelect('field_tableau_data_details')->isVisible(), "Data details visibility ($scenario)");
    $this->assertSame($share_options, $this->findTableauSelect('field_tableau_share_options')->isVisible(), "Share options visibility ($scenario)");
  }

  /**
   * Runs the full #states scenario against the currently loaded form.
   *
   * Expects the tableau_embed subform fields to be present and the embed
   * type to start at "default".
   */
  private function assertStatesScenario(): void {
    $embed_type = $this->findTableauSelect('field_tableau_embed_type');
    $toolbar = $this->findTableauSelect('field_tableau_toolbar');

    // Default embed type: all three settings are hidden.
    $this->assertSettingsVisibility(FALSE, FALSE, FALSE, 'embed type = default');

    // Connected Apps with no toolbar value: only the Toolbar select shows.
    // Data details and Share options are toolbar buttons, so they stay
    // hidden until the toolbar itself is displayed.
    $this->selectAndWait($embed_type, 'connected_apps');
    $this->assertSettingsVisibility(TRUE, FALSE, FALSE, 'connected apps, toolbar empty');

    $this->selectAndWait($toolbar, 'hidden');
    $this->assertSettingsVisibility(TRUE, FALSE, FALSE, 'toolbar = hidden');

    $this->selectAndWait($toolbar, 'bottom');
    $this->assertSettingsVisibility(TRUE, TRUE, TRUE, 'toolbar = bottom');

    $this->selectAndWait($toolbar, 'top');
    $this->assertSettingsVisibility(TRUE, TRUE, TRUE, 'toolbar = top');

    // Back to the default embed type: everything hides again.
    $this->selectAndWait($embed_type, 'default');
    $this->assertSettingsVisibility(FALSE, FALSE, FALSE, 'back to embed type = default');
  }

  /**
   * Opens the layout paragraphs edit dialog for the given paragraph.
   */
  private function openLayoutParagraphsEditDialog(Paragraph $paragraph): void {
    $session = $this->getSession();
    $edit_selector = 'a.lpb-edit[href*="/edit/' . $paragraph->uuid() . '"]';
    $session->wait(10000, "document.querySelector('$edit_selector') !== null");
    $this->assertNotNull($this->getSession()->getPage()->find('css', $edit_selector), 'Layout paragraphs edit control is present.');
    $session->executeScript("(function(){var el=document.querySelector('$edit_selector');if(el){try{el.scrollIntoView({block:'center'});}catch(e){} el.click();}})();");
    $session->wait(10000, "document.querySelector('.ui-dialog select[name*=\"field_tableau_embed_type\"]') !== null");
    $this->assertNotNull($this->getSession()->getPage()->find('css', '.ui-dialog select[name*="field_tableau_embed_type"]'), 'Tableau component edit dialog opened.');
  }

  /**
   * States on the org_page form (classic nested paragraphs widget).
   */
  public function testOrgPageClassicWidgetStates(): void {
    $this->loginAsAdmin();

    $tableau = $this->createTableauParagraph(['field_tableau_embed_type' => 'default']);
    $section = $this->createSectionParagraph($tableau);
    $node = $this->createNode([
      'type' => 'org_page',
      'title' => 'Tableau Settings Org Page',
      'field_organization_sections' => [['entity' => $section]],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->openContentTabAndDetails();
    $this->assertStatesScenario();
  }

  /**
   * States in the service_page layout paragraphs component dialog.
   */
  public function testServicePageLayoutBuilderStates(): void {
    $this->loginAsAdmin();

    $tableau = $this->createTableauParagraph(['field_tableau_embed_type' => 'default']);
    $node = $this->createServicePageWithTableau($tableau);

    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->openContentTabAndDetails();
    $this->openLayoutParagraphsEditDialog($tableau);
    $this->assertStatesScenario();
  }

  /**
   * States in the info_details layout paragraphs component dialog.
   */
  public function testInfoDetailsLayoutBuilderStates(): void {
    $this->loginAsAdmin();

    $tableau = $this->createTableauParagraph(['field_tableau_embed_type' => 'default']);
    $node = $this->createNode([
      'type' => 'info_details',
      'title' => 'Tableau Settings Info Details',
      'field_info_details_sections' => [['entity' => $tableau]],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->openContentTabAndDetails();
    $this->openLayoutParagraphsEditDialog($tableau);
    $this->assertStatesScenario();
  }

  /**
   * Editing the settings through the node form changes the rendered embed.
   */
  public function testToolbarSettingChangesRenderedOutput(): void {
    $this->loginAsAdmin();

    $tableau = $this->createTableauParagraph();
    $node = $this->createServicePageWithTableau($tableau);
    $assert = $this->assertSession();

    // With no explicit settings the toolbar falls back to hidden and no
    // custom parameters are emitted.
    $this->drupalGet('node/' . $node->id());
    $placeholder = $assert->elementExists('css', '.ma_tableau_placeholder');
    $this->assertSame('hidden', $placeholder->getAttribute('data-toolbar'));
    $this->assertNull($placeholder->getAttribute('data-data-details'));
    $this->assertNull($placeholder->getAttribute('data-share-options'));

    // Change the settings through the node edit form.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->openContentTabAndDetails();
    $this->openLayoutParagraphsEditDialog($tableau);

    $this->selectAndWait($this->findTableauSelect('field_tableau_toolbar'), 'bottom');
    $this->selectAndWait($this->findTableauSelect('field_tableau_data_details'), 'hide');
    $this->selectAndWait($this->findTableauSelect('field_tableau_share_options'), 'hide');

    // Save the component dialog, then the node form.
    $session = $this->getSession();
    $session->executeScript("(function(){var el=document.querySelector('.ui-dialog .ui-dialog-buttonpane button.lpb-btn--save');if(el){try{el.scrollIntoView({block:'center'});}catch(e){} el.click();}})();");
    $session->wait(10000, "document.querySelector('.ui-dialog.lpb-dialog') === null");
    $dialog = $this->getSession()->getPage()->find('css', '.ui-dialog.lpb-dialog');
    if ($dialog !== NULL) {
      $this->fail('Component dialog did not close after Save. Dialog text: ' . $dialog->getText());
    }
    // Keep the node published so the canonical page shows the new revision.
    $moderation_state = $this->getSession()->getPage()->find('css', 'select[name="moderation_state[0][state]"]');
    if ($moderation_state !== NULL) {
      $moderation_state->selectOption('published');
    }
    $session->executeScript("(function(){var el=document.getElementById('edit-submit');if(el){try{el.scrollIntoView({block:'center'});}catch(e){} el.click();}})();");
    // The node form is gone once the save redirect completes.
    $session->wait(15000, "document.getElementById('edit-submit') === null");
    if ($this->getSession()->getPage()->find('css', '#edit-submit') !== NULL) {
      $messages = $this->getSession()->getPage()->find('css', '[data-drupal-messages]');
      $this->fail('Node form did not submit. Messages: ' . ($messages ? $messages->getText() : '(none)') . ' URL: ' . $session->getCurrentUrl());
    }
    $storage = \Drupal::entityTypeManager()->getStorage('paragraph');
    $storage->resetCache([$tableau->id()]);
    $saved = $storage->load($tableau->id());
    $this->assertSame('bottom', $saved->get('field_tableau_toolbar')->value, 'Toolbar value persisted on the paragraph.');

    // The rendered embed now reflects the chosen settings.
    $this->drupalGet('node/' . $node->id());
    $placeholder = $assert->elementExists('css', '.ma_tableau_placeholder');
    $this->assertSame('bottom', $placeholder->getAttribute('data-toolbar'));
    $this->assertSame('hide', $placeholder->getAttribute('data-data-details'));
    $this->assertSame('hide', $placeholder->getAttribute('data-share-options'));
  }

}
