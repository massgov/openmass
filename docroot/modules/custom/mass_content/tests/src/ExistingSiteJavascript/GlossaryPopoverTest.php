<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Topic Page Description visibility tests.
 */
class GlossaryPopoverTest extends ExistingSiteSelenium2DriverTestBase {

  /**
   * Test paths for content types that support glossaries.
   *
   * @var array
   */
  const QAG_PATHS = [
    "/memorandum/qag-advisory",
    "/report/qag-binderreport",
    "/lists/qag-curated-list2-people",
    "/mandate/qag-decisionmandate",
    "/executive-orders/no-1-qag-executiveorder",
    "/forms/qag-formwithoutfileuploads",
    "/how-to/qag-request-help-with-a-computer-problem",
    "/info-details/qag-info-detail-with-landing-page-features",
    "/locations/qag-locationgeneral1",
    "/news/qag-newsnews",
    // TODO: orgs are failing this test for unrelated reasons.
    // "/orgs/qag-digital-services",
    "/regulations/900-CMR-2-qag-regulation-title",
    "/trial-court-rules/qag-rulesofcourt",
    "/qag-service1"
  ];

  /**
   * Glossary node.
   *
   * @var \Drupal\node\NodeInterface
   */
  private $glossary;

  /**
   * A term to be defined.
   *
   * @var string
   */
  private $term = "Lorem";

  /**
   * The definition for a term.
   *
   * @var string
   */
  private $definition = "Ipsum";

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->glossary = $this->createNode([
      'type' => 'glossary',
      'title' => 'Test Glossary',
      'field_terms' => [
        [
          'key' => $this->term,
          'value' => $this->definition,
        ],
      ],
      'moderation_state' => 'published',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    // Zero out any remaining references to prevent memory leaks.
    $this->glossary = NULL;
  }

  private function createEditor() {
    $editor = $this->createUser();
    $editor->addRole('editor');
    $editor->activate();
    $editor->save();
    return $editor;
  }

  /**
   * Test that glossary nodes are redirected to the home page for anonymous users.
   */
  public function testGlossaryRabbitHole() {
    $url = $this->glossary->toUrl()->toString();
    $this->drupalGet($url);
    $this->assertSession()->addressEquals('/');
    $this->capturePageContent();
  }

  /**
   * Test that editors are able to reference glossaries on supported node types.
   */
  public function testGlossaryReferenceInContent() {
    $this->drupalLogin($this->createEditor());
    $paths = self::QAG_PATHS;
    $session = $this->getSession();
    foreach ($paths as $path) {
      // Edit the node.
      $this->drupalGet($path . '/edit');
      $this->assertSession()->addressEquals($path . '/edit');
      $page = $session->getPage();
      $page->findField('edit-field-glossaries-0-target-id')->setValue($this->glossary->label() . ' (' . $this->glossary->id() . ') - Glossary');
      $page->pressButton('Save');
      $this->capturePageContent('after-save');
      $this->assertSession()->addressEquals($path);

      // Ensure the popup template is present.
      $popupTemplate = $page->find('css', '#glossary-popup-template');
      $this->assertNotNull($popupTemplate, 'Popup template not found on page ' . $path);

      // Ensure glossaries are in the drupalSettings json
      $drupalSettings = $page->find('css', '[data-drupal-selector="drupal-settings-json"]');
      $this->assertNotNull($drupalSettings, 'drupalSettings element not found');
      $this->assertStringContainsString('glossaries', $drupalSettings->getOuterHtml(), 'Glossaries not found in drupalSettings on page ' . $path);
    }

  }

  /**
   * Test that definitions from glossaries are displayed in popovers.
   */
  public function testGlossaryPopover() {

    $node = $this->createNode([
      'type' => 'service_page',
      'title' => 'Test Service Page',
      'field_service_body' => "Test definition popover " . $this->term,
      'moderation_state' => 'published',
    ]);

    $node->set('field_glossaries', $this->glossary);
    $node->save();

    $this->drupalGet($node->toUrl()->toString());
    $page = $this->getSession()->getPage();

    // The page should load with a <template id="glossary-popup-template"> and drupal settings JSON.
    $this->assertSession()->elementExists('css', '#glossary-popup-template');
    $this->assertSession()->elementExists('css', '[data-drupal-selector="drupal-settings-json"]');

    // Ensure the popup has been injected.
    $page->waitFor(10, function () use ($page) {
      $hasTemplate = $page->find('css', '#glossary-popup-template');
      return $hasTemplate !== NULL;
    });

    // Activate the popover and ensure it has the expected definition.
    $trigger = $page->find('css', '.popover__trigger');
    $dialog = $page->find('css', '.popover__dialog');

    $trigger->click();
    $this->assertTrue($dialog->isVisible());
    $this->assertSession()->elementTextContains('css', '.popover__dialog', $this->definition);
  }

  /**
   * Test that popovers do not add extra space.
   */
  public function testGlossaryPopoverInjection() {

    $node = $this->createNode([
      'type' => 'service_page',
      'title' => 'Test Service Page',
      'field_service_body' => "Test definition popover \"" . $this->term . "\"",
      'moderation_state' => 'published',
    ]);

    $node->set('field_glossaries', $this->glossary);
    $node->save();

    $this->drupalGet($node->toUrl()->toString());
    $page = $this->getSession()->getPage();

    // The page should load with a <template id="glossary-popup-template"> and drupal settings JSON.
    $this->assertSession()->elementExists('css', '#glossary-popup-template');
    $this->assertSession()->elementExists('css', '[data-drupal-selector="drupal-settings-json"]');

    // Ensure the popup has been injected.
    $page->waitFor(10, function () use ($page) {
      $hasTemplate = $page->find('css', '#glossary-popup-template');
      return $hasTemplate !== NULL;
    });

    // Ensure text contains "Lorem".
    $popover = $page->find('css', '.popover');
    $popover_parent = $popover->getParent();
    $popover_markup = $popover->getOuterHtml();
    $parent_markup = $popover_parent->getOuterHtml();
    $this->assertStringContainsString("\"" . $popover_markup . "\"", $parent_markup);
  }

  /**
   * Test that popovers do not add extra space.
   */
  public function testGlossaryPopoverPunctuationDifferences() {

    $apostrophe = "'";
    $single_quote = "ʼ";

    $glossary = $this->createNode([
      'type' => 'glossary',
      'title' => 'Test Glossary',
      'field_terms' => [
        [
          'key' => "Clerk" . $single_quote . "s Office",
          'value' => $this->definition,
        ],
      ],
      'moderation_state' => 'published',
    ]);

    $node = $this->createNode([
      'type' => 'service_page',
      'title' => 'Test Service Page',
      'field_service_body' => "Test definition popover Clerk" . $apostrophe . "s Office",
      'moderation_state' => 'published',
    ]);

    $node->set('field_glossaries', $glossary);
    $node->save();

    $this->drupalGet($node->toUrl()->toString());
    $page = $this->getSession()->getPage();

    // The page should load with a <template id="glossary-popup-template"> and drupal settings JSON.
    $this->assertSession()->elementExists('css', '#glossary-popup-template');
    $this->assertSession()->elementExists('css', '[data-drupal-selector="drupal-settings-json"]');

    // Ensure the popup has been injected.
    $page->waitFor(10, function () use ($page) {
      $hasTemplate = $page->find('css', '#glossary-popup-template');
      return $hasTemplate !== NULL;
    });
    $trigger = $page->find('css', '.popover__trigger');
    $dialog = $page->find('css', '.popover__dialog');
    $this->assertNotNull($trigger);
    $this->assertNotNull($dialog);
  }

}
