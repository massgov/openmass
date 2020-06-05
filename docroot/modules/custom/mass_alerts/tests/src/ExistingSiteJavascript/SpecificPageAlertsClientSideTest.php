<?php

namespace Drupal\Tests\mass_alerts\ExistingSiteJavascript;

use Drupal\paragraphs\Entity\Paragraph;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;

/**
 * Test 'on specific page' Alerts.
 */
class SpecificPageAlertsClientSideTest extends ExistingSiteWebDriverTestBase {

  private $orgPageId;
  private $newsPageId;
  private $eventPageId;
  private $orgPageTitle = 'My Org Page';
  private $newsPageTitle = 'My News Page';
  private $eventPageTitle = 'My Event Page';
  private $alertPageTitle = 'My Alert Page';
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
    ]);
    $this->newsPageId = $this->getNodeByTitle($this->newsPageTitle)->id();

    $this->createNode([
      'type' => 'event',
      'title' => $this->eventPageTitle,
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $this->eventPageId = $this->getNodeByTitle($this->eventPageTitle)->id();

    $this->createNode([
      'type' => 'alert',
      'title' => $this->alertPageTitle,
      'field_alert_display' => 'specific_target_pages',
      'moderation_state' => 'published',
      'status' => 1,
      'field_alert' => Paragraph::create([
        'type' => 'emergency_alert',
        'field_emergency_alert_message' => $this->alertMessage,
      ]),
      'field_target_page' => [
        ['target_id' => $this->orgPageId],
        ['target_id' => $this->newsPageId],
        ['target_id' => $this->eventPageId],
      ],
    ]);
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

    $this->drupalGet('node/' . $this->eventPageId);
    $assert_session->pageTextContains($this->eventPageTitle);
    $assert_session->waitForElement('css', '.ma__header-alert__message');
    $assert_session->pageTextContains($this->alertMessage);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    parent::tearDown();
    // Zero out any remaining references to prevent memory leaks.
    $this->orgPageId = NULL;
    $this->newsPageId = NULL;
    $this->eventPageId = NULL;
    $this->orgPageTitle = NULL;
    $this->newsPageTitle = NULL;
    $this->eventPageTitle = NULL;
    $this->alertPageTitle = NULL;
    $this->alertMessage = NULL;
  }

}
