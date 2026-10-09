<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use MassGov\Dtt\MassExistingSiteSelenium2DriverTestBase;

/**
 * Topic Page Description visibility tests.
 */
class TopicPageDescriptionTest extends MassExistingSiteSelenium2DriverTestBase {

  /**
   * Test that short description rendering.
   */
  public function testShortDescriptionVisibility() {
    // Create a node with the checkbox checked.
    $node = $this->createNode([
      'type' => 'topic_page',
      'title' => 'Test Topic Page',
      'field_display_short_description' => TRUE,
      'field_topic_lede' => $this->randomString(),
      'moderation_state' => 'published',
    ]);

    // Visit the node page and check if the short description is rendered.
    $this->drupalGet($node->toUrl()->toString());
    $this->assertSession()->elementExists('css', '.pre-content .ma__page-header__content .ma__page-header__description');

    // Update the node to uncheck the checkbox.
    $node->set('field_display_short_description', FALSE);
    $node->save();

    // Reload the node page and check if the short description is NOT
    // rendered. A second visit to the same URL would show the copy Chrome
    // keeps in its HTTP cache (anonymous pages are sent with max-age), while
    // a reload revalidates it with Drupal.
    $this->getSession()->reload();
    $this->assertSession()->elementNotExists('css', '.pre-content .ma__page-header__content .ma__page-header__description');
  }

}
