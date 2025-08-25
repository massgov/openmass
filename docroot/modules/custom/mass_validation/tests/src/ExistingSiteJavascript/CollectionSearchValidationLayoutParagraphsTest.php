<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\node\Entity\Node;
use Exception;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;
use weitzman\DrupalTestTraits\ScreenShotTrait;

/**
 * Tests "All Content" view at admin/content.
 */
class CollectionSearchValidationLayoutParagraphsTest extends ExistingSiteSelenium2DriverTestBase {

  use ScreenShotTrait;
  /**
   * The user to log in and test the functionality.
   *
   * @var \Drupal\user\Entity\User
   */
  private $user;

  /**
   * Create the user.
   */
  protected function setUp(): void {
    parent::setUp();
    $user = $this->createUser();
    // Content Administrators also have the Editor role.
    $user->addRole('content_team');
    $user->addRole('editor');
    $user->activate();
    $user->save();
    $this->user = $user;
  }

  /**
   * Assert that the Custom Search paragraph validation works properly on service page nodes.
   */
  public function testServicePageCollectionSearchValidation() {
    $org_node = $this->createNode([
      'type' => 'service_page',
      'title' => 'Test Service Page ',
      'uid' => $this->user->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $this->drupalLogin($this->user);

    $this->visit($org_node->toUrl()->toString() . '/edit');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('edit-moderation-state-0-state', 'Unpublished');

    $this->getSession()->executeScript("
  document.querySelector('a[href^=\"#edit-group-content\"]').scrollIntoView({block: 'center'});
");
    $page->find('css', '.horizontal-tab-button a[href="#edit-group-content"]')->click();

    // Assert that the LPB "Add section" anchor exists and is visible (CSS selector).
    // We key off the LPB builder container and the choose-component href.
    $this->getSession()->wait(1500);
    $addSection = $page->find('css', '[data-drupal-selector="edit-field-service-sections-layout-paragraphs-builder"] a.lpb-btn.use-ajax.center.js-lpb-ui[href*="/choose-component"]');
    $this->assertNotNull($addSection, '"Add section" anchor is present after opening Content tab.');
    $this->assertTrue($addSection->isVisible(), '"Add section" anchor is visible.');

    $addSection->click();

    // Give the modal some time to open fully.
    $this->getSession()->wait(3000, "document.querySelector('.ui-dialog.lpb-dialog.ui-widget.ui-widget-content.ui-front') !== null");


    $keyMsg = $page->find('css', '.ui-dialog .ui-dialog-content a:contains("Key Message Section")');
    $serviceSection = $page->find('css', '.ui-dialog .ui-dialog-content a:contains("Service Section")');
    $this->assertNotNull($keyMsg, 'Key Message Section option present.');
    $this->assertTrue($keyMsg->isVisible(), 'Key Message Section link is visible.');
    $this->assertNotNull($serviceSection, 'Service Section option present.');
    $this->assertTrue($serviceSection->isVisible(), 'Service Section link is visible.');


    // Click the "Service Section" link inside the chooser modal (CSS only).
    $serviceLink = $page->find('css', '.ui-dialog .ui-dialog-content a.use-ajax[href*="/insert/service_section"]');
    $this->assertNotNull($serviceLink, 'Service Section link found (chooser modal).');

    // Ensure it is in view and click (JS fallback avoids toolbar/overlay interception).
    $this->getSession()->executeScript(
      "(function(){var el=document.querySelector('.ui-dialog .ui-dialog-content a.use-ajax[href*=\"/insert/service_section\"]');if(el){try{el.scrollIntoView({block:\"center\"});}catch(e){} el.click();}})();"
    );

    // Wait for the new modal to load and title to update to "Create new Service Section".
    $this->getSession()->wait(
      5000,
      "document.querySelector('.ui-dialog .ui-dialog-title') && document.querySelector('.ui-dialog .ui-dialog-title').textContent.indexOf('Create new Service Section') !== -1"
    );

    // Verify the new modal is present and visible.
    $newModal = $page->find('css', 'div.ui-dialog.lpb-dialog.ui-widget.ui-widget-content.ui-front');
    $this->assertNotNull($newModal, 'Create Service Section modal opened.');
    $this->assertTrue($newModal->isVisible(), 'Create Service Section modal is visible.');

    // Verify the title text.
    $newTitle = $page->find('css', '.ui-dialog .ui-dialog-title');
    $this->assertNotNull($newTitle, 'Create Service Section modal title element present.');
    $this->assertStringContainsString('Create new Service Section', $newTitle->getText());

    // Click the Save button in the "Create new Service Section" modal.
    // Use CSS-only selector based on LPB class names.
    $this->getSession()->wait(2000, "document.querySelector('.ui-dialog .ui-dialog-buttonpane button.lpb-btn--save') !== null");
    $saveBtn = $page->find('css', '.ui-dialog .ui-dialog-buttonpane button.lpb-btn--save');
    $this->assertNotNull($saveBtn, 'Save button present in Create Service Section modal.');
    $this->assertTrue($saveBtn->isVisible(), 'Save button is visible.');

    // Ensure button is in view and click with JS to avoid overlay interceptions.
    $this->getSession()->executeScript(
      "(function(){var el=document.querySelector('.ui-dialog .ui-dialog-buttonpane button.lpb-btn--save'); if(el){ try{el.scrollIntoView({block:'center'});}catch(e){} el.click(); }})();"
    );

    // Wait for the modal to close after saving (AJAX request completes).
    $this->getSession()->wait(8000, "document.querySelector('.ui-dialog.lpb-dialog.ui-widget.ui-widget-content.ui-front') === null");

    // Sanity check: modal should be gone.
    $stillOpen = $page->find('css', 'div.ui-dialog.lpb-dialog.ui-widget.ui-widget-content.ui-front');
    $this->assertNull($stillOpen, 'Create Service Section modal closed after Save.');

    // === Open the region-level Add section and choose "Custom Search" ===
    // Wait for the region Add section button inside the content region.
    $this->getSession()->wait(4000, "document.querySelector('.layout.layout--onecol-mass-service-section .js-lpb-region.layout__region--content a.lpb-btn--add.use-ajax.center.js-lpb-ui[href*=\"choose-component?parent_uuid\"]') !== null");

    $regionAddBtn = $page->find('css', '.layout.layout--onecol-mass-service-section .js-lpb-region.layout__region--content a.lpb-btn--add.use-ajax.center.js-lpb-ui[href*="choose-component?parent_uuid"]');
    $this->assertNotNull($regionAddBtn, 'Region Add section button is present.');
    $this->assertTrue($regionAddBtn->isVisible(), 'Region Add section button is visible.');

    // Scroll into view and click via JS to avoid overlay interception.
    $this->getSession()->executeScript(
      "(function(){var el=document.querySelector('.layout.layout--onecol-mass-service-section .js-lpb-region.layout__region--content a.lpb-btn--add.use-ajax.center.js-lpb-ui[href*=\"choose-component?parent_uuid\"]'); if(el){ try{el.scrollIntoView({block:'center'});}catch(e){} el.click(); }})();"
    );

    // Wait for the component chooser to be visible again.
    $this->getSession()->wait(5000, "document.querySelector('.ui-dialog.lpb-dialog.ui-widget.ui-widget-content.ui-front .lpb-component-list') !== null");

    // Assert the "Custom Search" option exists.
    $customSearch = $page->find('css', '.ui-dialog .lpb-component-list__item.type-collection_search a.use-ajax');
    $this->assertNotNull($customSearch, 'Custom Search option is present in the chooser.');
    $this->assertTrue($customSearch->isVisible(), 'Custom Search option is visible.');

    // Click Custom Search.
    $this->getSession()->executeScript(
      "(function(){var el=document.querySelector('.ui-dialog .lpb-component-list__item.type-collection_search a.use-ajax'); if(el){ try{el.scrollIntoView({block:'center'});}catch(e){} el.click(); }})();"
    );

    // Wait for the Custom Search configuration modal to appear (title usually contains the paragraph label).
    $this->getSession()->wait(6000, "document.querySelector('.ui-dialog .ui-dialog-title') && document.querySelector('.ui-dialog .ui-dialog-title').textContent.toLowerCase().indexOf('custom search') !== -1");

    $csModalTitle = $page->find('css', '.ui-dialog .ui-dialog-title');
    $this->assertNotNull($csModalTitle, 'Custom Search modal title present.');
    $this->assertStringContainsStringIgnoringCase('custom search', $csModalTitle->getText());
    $page->selectFieldOption('field_search_type', 'External search destination (using query string)');
    $this->getSession()->wait(2000, "document.querySelector('.ui-dialog .ui-dialog-buttonpane button.lpb-btn--save') !== null");
    $saveBtn = $page->find('css', '.ui-dialog .ui-dialog-buttonpane button.lpb-btn--save');
    $this->assertNotNull($saveBtn, 'Save button present in Create Service Section modal.');
    $this->assertTrue($saveBtn->isVisible(), 'Save button is visible.');

    // Ensure button is in view and click with JS to avoid overlay interceptions.
    $this->getSession()->executeScript(
      "(function(){var el=document.querySelector('.ui-dialog .ui-dialog-buttonpane button.lpb-btn--save'); if(el){ try{el.scrollIntoView({block:'center'});}catch(e){} el.click(); }})();"
    );
    // Wait for server-side validation and the error message list to render.
    $this->getSession()->wait(
      6000,
      "document.querySelector('.ui-dialog .messages-list__item.messages.messages--error') !== null"
    );

    // Assert the error container exists in the dialog.
    $errorBox = $page->find('css', '.ui-dialog .messages-list__item.messages.messages--error');
    $this->assertNotNull($errorBox, 'Validation error box is present in the Custom Search modal.');

    // For External search type, expect links for: Search heading, Search site URL, Name for query parameter.
    $linkSearchHeading = $page->find('css', '.ui-dialog .messages-list__item.messages.messages--error a[href^="#edit-field-search-heading"]');
    // Be tolerant to minor id variations; match by fragment contains.
    $linkSearchSiteUrl = $page->find('css', '.ui-dialog .messages-list__item.messages.messages--error a[href*="search-site-url"]');
    $linkQueryParam    = $page->find('css', '.ui-dialog .messages-list__item.messages.messages--error a[href*="query-parameter"], .ui-dialog .messages-list__item.messages.messages--error a[href*="query-param"]');

    $this->assertNotNull($linkSearchHeading, 'Validation link for "Search heading" present.');
    $this->assertNotNull($linkSearchSiteUrl, 'Validation link for "Search site URL" present.');
    $this->assertNotNull($linkQueryParam, 'Validation link for "Name for query parameter" present.');

    $this->assertTrue($linkSearchHeading->isVisible(), '"Search heading" link is visible.');
    $this->assertTrue($linkSearchSiteUrl->isVisible(), '"Search site URL" link is visible.');
    $this->assertTrue($linkQueryParam->isVisible(), '"Name for query parameter" link is visible.');

    $page->selectFieldOption('field_search_type', 'Collection');
    $this->getSession()->wait(2000, "document.querySelector('.ui-dialog .ui-dialog-buttonpane button.lpb-btn--save') !== null");
    $saveBtn = $page->find('css', '.ui-dialog .ui-dialog-buttonpane button.lpb-btn--save');
    $this->assertNotNull($saveBtn, 'Save button present in Create Service Section modal.');
    $this->assertTrue($saveBtn->isVisible(), 'Save button is visible.');

    // Ensure button is in view and click with JS to avoid overlay interceptions.
    $this->getSession()->executeScript(
      "(function(){var el=document.querySelector('.ui-dialog .ui-dialog-buttonpane button.lpb-btn--save'); if(el){ try{el.scrollIntoView({block:'center'});}catch(e){} el.click(); }})();"
    );
    // Wait for server-side validation and the error message list to render.
    $this->getSession()->wait(
      6000,
      "document.querySelector('.ui-dialog .messages-list__item.messages.messages--error') !== null"
    );

    // Assert the error container exists in the dialog.
    $errorBox = $page->find('css', '.ui-dialog .messages-list__item.messages.messages--error');
    $this->assertNotNull($errorBox, 'Validation error box is present in the Custom Search modal.');

    // The Claro theme renders comma-separated links to invalid fields.
    // Assert both anchors exist: Search heading + Collection.
    $linkCollection    = $page->find('css', '.ui-dialog .messages-list__item.messages.messages--error a[href^="#edit-field-collection"]');
    $this->assertNull($linkCollection, 'Validation link for "Collection" present.');
    $this->captureScreenshot();
  }

}
