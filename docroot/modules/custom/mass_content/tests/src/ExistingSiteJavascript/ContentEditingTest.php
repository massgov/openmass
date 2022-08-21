<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;
use weitzman\DrupalTestTraits\ScreenShotTrait;
use weitzman\LoginTrait\LoginTrait;

/**
 * Ensures editor can save the nodes.
 */
class ContentEditingTest extends ExistingSiteSelenium2DriverTestBase {

  use LoginTrait;
  use ScreenShotTrait;

  const QAG_PATHS = [
    "/audit/qag-binderaudit",
    "/report/qag-binderreport",
    "/qagcampaign-landing-with-solid-color-key-message-header",
    "/lists/qag-curatedlist",
    "/mandate/qag-decisionmandate",
    "/decision-tree/qag-decision-tree",
    "/event/qag-event-general-past-2018-07-24t124500-0400-2018-07-24t134500-0400",
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
   * Creates an editor, saves it and returns it.
   */
  private function createEditor() {
    $editor = User::create(['name' => $this->randomMachineName()]);
    $editor->addRole('editor');
    $editor->activate();
    $editor->save();
    return $editor;
  }

  /**
   * Tests saving the content.
   */
  public function testSaveContent() {
    $this->drupalLogin($this->createEditor());
    $paths = self::QAG_PATHS;
    foreach ($paths as $path) {
      // Edit the node.
      $this->drupalGet($path . '/edit');
      $this->captureScreenshot();
      $this->getCurrentPage()->findButton('Save')->click();
      $this->captureScreenshot();
      $this->assertSession()->addressEquals($path);
    }
  }

}
