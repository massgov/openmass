<?php

namespace Drupal\Tests\mass_content\ExistingSite;

use Drupal\mass_content_moderation\MassModeration;
use Drupal\taxonomy\Entity\Vocabulary;
use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests collection_all view empty text.
 *
 * When a collection term page shows no results, it should display the empty
 * text defined in field_additional_no_items_found value from the root term.
 *
 * @group existing-site
 */
class EmptyResultsCollectionTermTest extends MassExistingSiteBase {

  /**
   * Tests the empty text for the collection_all view.
   */
  public function testEmptyTextOnCollectionAllView() {

    // Create one new term in collections.
    $term_name = $this->randomMachineName();
    $url = 'test_type-' . time();
    $empty_text = 'Random empty text here ' . $term_name;

    $parent_term = $this->createTerm(Vocabulary::load('collections'), [
      'name' => $term_name,
      'field_url_name' => $url,
      'field_additional_no_items_found' => 'Random empty text here ' . $term_name,
    ]);

    // Test empty text is shown on empty results.
    $this->drupalGet('/collections/' . $url);
    $this->assertSession()->pageTextContains($empty_text);

    // Adding one node, to show one result.
    $this->createNode([
      'type' => 'service_page',
      'title' => $this->randomMachineName(),
      'field_collections' => $parent_term->id(),
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    // There should be 1 result. The empty text should not exist.
    $this->drupalGet('/collections/' . $url);
    $this->assertSession()->pageTextNotContains($empty_text);

    // New child term.
    $child_term_name = $this->randomMachineName();
    $child_term = $this->createTerm(Vocabulary::load('collections'), [
      'name' => $child_term_name,
      'parent' => $parent_term->id(),
    ]);

    // The empty text should be visible when filtering using the new term.
    $this->drupalGet('/collections/' . $url, ['query' => ['topicid' => $child_term->id()]]);
    $this->assertSession()->pageTextContains($empty_text);

    // Adding one result to the child term.
    $this->createNode([
      'type' => 'service_page',
      'title' => $this->randomMachineName(),
      'field_collections' => [$parent_term->id(), $child_term->id()],
      'moderation_state' => MassModeration::PUBLISHED,
    ]);

    // No empty text should be visible because there should be 1 result.
    $this->drupalGet('/collections/' . $url, ['query' => ['topicid' => $child_term->id()]]);
    $this->assertSession()->pageTextNotContains($empty_text);
  }

}
