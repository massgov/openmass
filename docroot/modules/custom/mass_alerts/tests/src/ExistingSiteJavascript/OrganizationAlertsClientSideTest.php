<?php

namespace Drupal\Tests\mass_alerts\ExistingSiteJavascript;

use Drupal\paragraphs\Entity\Paragraph;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test By Organization Alerts.
 */
class OrganizationAlertsClientSideTest extends ExistingSiteBase {

  private $org;
  private $orgPageId;
  private $alertMessage = 'hello world';
  private $news;
  private $alert;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->markTestSkipped('see notes in testNewsPageHasAlert');
    parent::setUp();

    $this->org = $this->createNode([
      'type' => 'org_page',
      'title' => 'My Org Page',
      'status' => 1,
      'moderation_state' => 'published',
    ]);

    $this->orgPageId = $this->getNodeByTitle('My Org Page')->id();

    $this->news = $this->createNode([
      'type' => 'news',
      'title' => 'My News Page',
      'status' => 1,
      'moderation_state' => 'published',
      'field_organizations' => $this->orgPageId,
    ]);

    $this->alert = $this->createNode([
      'type' => 'alert',
      'title' => 'My Alert Page',
      'field_alert_display' => 'by_organization',
      'moderation_state' => 'published',
      'status' => 1,
      'field_alert' => Paragraph::create([
        'type' => 'emergency_alert',
        'field_emergency_alert_message' => $this->alertMessage,
      ]),
      'field_target_orgs_para_ref' => Paragraph::create([
        'type' => 'target_organizations',
        'field_target_content_ref' => $this->orgPageId,
        // 'field_target_content_ref' => ['target_id' => $this->org_page_id],.
      ]),
    ]);
  }

  /**
   * Test pages work.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testPagesWork() {
    $this->markTestSkipped('see notes in testNewsPageHasAlert');

    $assert_session = $this->assertSession();

    $this->drupalGet('node/' . $this->orgPageId);
    $assert_session->pageTextContains('My Org Page');

    $news_id = $this->getNodeByTitle('My News Page')->id();
    $this->drupalGet('node/' . $news_id);
    $assert_session->pageTextContains('My News Page');

    $alert_id = $this->getNodeByTitle('My Alert Page')->id();
    $this->drupalGet('node/' . $alert_id);
    $assert_session->pageTextContains('My Alert Page');
    $assert_session->pageTextContains($this->alertMessage);
  }

  /**
   * Test News Page Has Alert.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testNewsPageHasAlert() {
    $this->markTestSkipped('not working, see notes below.');

    $assert_session = $this->assertSession();
    $news_id = $this->getNodeByTitle('My News Page')->id();
    $this->drupalGet('node/' . $news_id);
    $assert_session->pageTextContains('My Org Page');

    // Alert node data is not being added to the JSON response so this
    // line is failing. Why? We know the alert node is saving. Is this a
    // caching problem? Am I missing required fields or something?
    $assert_session->pageTextContains($this->alertMessage);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    parent::tearDown();
    // Zero out any remaining references to prevent memory leaks.
    $this->org = NULL;
    $this->orgPageId = NULL;
    $this->alertMessage = NULL;
    $this->alert = NULL;
    $this->news = NULL;
  }

}
