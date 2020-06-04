<?php

namespace Drupal\Tests\mass_alerts\ExistingSiteJavascript;

use Drupal\paragraphs\Entity\Paragraph;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;

/**
 * Test 'By Organization' Alerts.
 */
class OrganizationAlertsClientSideTest extends ExistingSiteWebDriverTestBase {

  private $alertPageId;
  private $orgPageId;
  private $newsPageId;
  private $alertPageTitle = 'My Alert Page';
  private $orgPageTitle = 'My Org Page';
  private $newsPageTitle = 'My News Page';
  private $alertMessage = 'Hello World';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->createNode([
      'type' => 'org_page',
      'title' => $this->orgPageTitle,
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $this->orgPageId = $this->getNodeByTitle($this->orgPageTitle)->id();

    $this->createNode([
      'type' => 'news',
      'title' => $this->newsPageTitle,
      'status' => 1,
      'moderation_state' => 'published',
      'field_organizations' => ['target_id' => $this->orgPageId],
    ]);
    $this->newsPageId = $this->getNodeByTitle($this->newsPageTitle)->id();

    $this->createNode([
      'type' => 'alert',
      'title' => $this->alertPageTitle,
      'field_alert_display' => 'by_organization',
      'moderation_state' => 'published',
      'status' => 1,
      'field_alert' => Paragraph::create([
        'type' => 'emergency_alert',
        'field_emergency_alert_message' => $this->alertMessage,
      ]),
      'field_target_organization' => ['target_id' => $this->orgPageId],
    ]);
    $this->alertPageId = $this->getNodeByTitle($this->alertPageTitle)->id();
  }

  /**
   * Test pages work.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testPagesWork() {
    $assert_session = $this->assertSession();

    $this->drupalGet('node/' . $this->alertPageId);
    $assert_session->pageTextContains($this->alertPageTitle);
    $assert_session->pageTextContains($this->alertMessage);

    $this->drupalGet('node/' . $this->orgPageId);
    $assert_session->pageTextContains($this->orgPageTitle);

    $this->drupalGet('node/' . $this->newsPageId);
    $assert_session->pageTextContains($this->newsPageTitle);
  }

  /**
   * Test pages have organization alert displaying.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testPagesHaveOrgAlert() {
    $assert_session = $this->assertSession();

    $this->drupalGet('node/' . $this->orgPageId);
    $assert_session->pageTextContains($this->orgPageTitle);
    $assert_session->waitForElement('css', '.ma__header-alert__message');
    $assert_session->pageTextContains($this->alertMessage);

    $this->drupalGet('node/' . $this->newsPageId);
    $assert_session->pageTextContains($this->newsPageTitle);
    $assert_session->waitForElement('css', '.ma__header-alert__message');
    $assert_session->pageTextContains($this->alertMessage);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    parent::tearDown();
    // Zero out any remaining references to prevent memory leaks.
    $this->alertPageId = NULL;
    $this->orgPageId = NULL;
    $this->newsPageId = NULL;
    $this->alertPageTitle = NULL;
    $this->orgPageTitle = NULL;
    $this->newsPageTitle = NULL;
    $this->alertMessage = NULL;
  }

}
