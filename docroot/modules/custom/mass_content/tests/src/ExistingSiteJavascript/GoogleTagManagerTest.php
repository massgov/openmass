<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Ensures GTM is generated properly.
 */
class GoogleTagManagerTest extends ExistingSiteSelenium2DriverTestBase {

  const QAG_PATHS = [
    "/audit/qag-binderaudit",
    "/report/qag-binderreport",
    "/qagcampaign-landing-with-solid-color-key-message-header",
    "/lists/qag-curatedlist",
    "/mandate/qag-decisionmandate",
    "/decision-tree/qag-decision-tree",
    "/executive-orders/no-1-qag-executiveorder",
    "/forms/qag-formwithfileuploads",
    "/guides/qag-guide",
    "/how-to/qag-request-help-with-a-computer-problem",
    "/info-details/qag-info-detail-with-landing-page-features",
    "/location-details/qag-locationdetails",
    "/locations/qag-locationgeneral1",
    "/news/qag-newsnews",
    "/orgs/qag-executive-office-of-technology-services-and-security",
    "/regulations/900-CMR-2-qag-regulation-title",
    "/person/qag-person-boardmember-role",
    "/trial-court-rules/qag-rulesofcourt",
    "/service-details/qag-servicedetails",
    "/qag-service1",
    "/topics/qag-topicpage1",
  ];

  /**
   * Tests GTM on Homepage.
   */
  public function testGoogleTagManagerOnHomepage() {

    $this->drupalGet('<front>');
    $this->assertSession()->elementExists('css', 'script[src="https://www.googletagmanager.com/gtm.js?id=GTM-MPHNMQ"]');
    $data_layer = $this->getSession()->evaluateScript('window.dataLayer.length > 0');
    $this->assertTrue($data_layer, 'DataLayer is missing on the Homepage.');

  }

  /**
   * Tests GTM on random published page.
   */
  public function testGoogleTagManagerOnRandomPage() {
    $paths = self::QAG_PATHS;
    $path = $paths[array_rand($paths)];
    $this->drupalGet($path);
    $this->assertSession()->elementExists('css', 'script[src="https://www.googletagmanager.com/gtm.js?id=GTM-MPHNMQ"]');
    $data_layer = $this->getSession()->evaluateScript('window.dataLayer.length > 0');
    $this->assertTrue($data_layer, 'DataLayer is missing on the Page.');
  }

}
