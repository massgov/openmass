<?php

namespace Drupal\Tests\mass_content\ExistingSiteJavascript;

use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Topic Page Description visibility tests.
 */
class TopicPageDescriptionTest extends ExistingSiteSelenium2DriverTestBase {

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

    // Visit the node page again and check
    // if the short description is NOT rendered.
    $this->drupalGet($node->toUrl()->toString());
    $this->assertSession()->elementNotExists('css', '.pre-content .ma__page-header__content .ma__page-header__description');
  }

}
