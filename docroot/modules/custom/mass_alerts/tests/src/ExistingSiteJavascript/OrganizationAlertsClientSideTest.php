<?php

namespace Drupal\Tests\mass_alerts\ExistingSiteJavascript;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\paragraphs\Entity\Paragraph;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;

/**
 * Test 'By Organization' Alerts.
 */
class OrganizationAlertsClientSideTest extends ExistingSiteWebDriverTestBase {

  /**
   * Test pages have organization alert displaying.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testPagesHaveOrgAlert() {

    $alert_message = $this->randomMachineName();

    $org_node = $this->createNode([
      'type' => 'org_page',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    $news_node = $this->createNode([
      'type' => 'news',
      'title' => $this->randomMachineName(),
      'status' => 1,
      'moderation_state' => MassModeration::PUBLISHED,
      'field_organizations' => ['target_id' => $org_node->id()],
    ]);

    $this->createNode([
      'type' => 'alert',
      'title' => $this->randomMachineName(),
      'field_alert_display' => 'by_organization',
      'moderation_state' => MassModeration::PUBLISHED,
      'status' => 1,
      'field_alert' => Paragraph::create([
        'type' => 'emergency_alert',
        'field_emergency_alert_message' => $alert_message,
      ]),
      'field_target_organization' => ['target_id' => $org_node->id()],
    ]);

    $assert_session = $this->assertSession();

    $this->drupalGet('node/' . $org_node->id());
    $assert_session->pageTextContains($org_node->getTitle());
    $assert_session->waitForElement('css', '.ma__header-alert__message', 60000);
    $assert_session->pageTextContains($alert_message);

    $this->drupalGet('node/' . $news_node->id());
    $assert_session->pageTextContains($news_node->getTitle());
    $assert_session->waitForElement('css', '.ma__header-alert__message', 60000);
    $assert_session->pageTextContains($alert_message);
  }

}
