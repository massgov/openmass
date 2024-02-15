<?php

namespace Drupal\Tests\mass_utility\ExistingSite;

use MassGov\Dtt\MassExistingSiteBase;

/**
 * Tests url aliases of unpublished and published content.
 */
class ContentUrlTest extends MassExistingSiteBase {

  /**
   * New unpublished content gets url alias with special string suffixed to it.
   */
  public function testTitleBasedNewUnpublishedContentUrl() {
    // Unpublished content should get their regular URL alias based on the
    // configured pattern, but along with a "---unpublished" string suffix.
    // Test against different content types.
    $path_pattern_substring_by_type = [
      "decision" => "",
      "news" => "/news/",
      "service_page" => "",
      "advisory" => "",
      "how_to_page" => "/how-to/",
      "curated_list" => "/lists/",
      "event" => "/event/",
      "location" => "/locations/",
      "info_details" => "/info-details/",
      "guide_page" => "/guides/",
      "org_page" => "/orgs/",
      "topic_page" => "/topics/",
      "form_page" => "/forms/",
      "location_details" => "/location-details/",
      "alert" => "/alerts/",
      "action" => "",
      "stacked_layout" => "",
      "decision_tree" => "/decision-tree/",
    ];
    // NOTE: For some content types, like regulation, non-title fields are used
    // to generate the path alias. We cover such types in a separate test.
    // In this test we only cover the above content types that are configured
    // to have title based path alias patterns
    // like "/somefoo/[node:title]" or "/[node:title]".
    // @todo https://jira.state.ma.us/browse/DP-8960.
    foreach ($path_pattern_substring_by_type as $type => $path_pattern_substring) {
      $node = $this->createNode([
        'type' => $type,
        'title' => '_QA Test Content to Check URL Alias For ' . $type,
        'status' => 0,
      ]);

      $langcode = $node->language()->getId();
      $source = '/' . $node->toUrl()->getInternalPath();
      $path_alias = \Drupal::service('path_alias.manager')->getAliasByPath($source, $langcode);

      if (!empty($path_pattern_substring)) {
        $this->assertStringContainsString($path_pattern_substring, $path_alias);
      }
      else {
        $this->assertStringStartsWith("/qa-", $path_alias);
      }
      $this->assertStringContainsString("---unpublished", $path_alias);
    }
  }

  /**
   * Published content gets expected pattern based url alias.
   */
  public function testTitleBasedPublishedContentUrl() {
    // Published content should get their regular URL alias based on the
    // configured pattern, WITHOUT any "---unpublished" string in it.
    // Test against different content types.
    $path_pattern_substring_by_type = [
      "decision" => "",
      "news" => "/news/",
      "service_page" => "",
      "advisory" => "",
      "how_to_page" => "/how-to/",
      "curated_list" => "/lists/",
      "event" => "/event/",
      "location" => "/locations/",
      "info_details" => "/info-details/",
      "guide_page" => "/guides/",
      "org_page" => "/orgs/",
      "topic_page" => "/topics/",
      "form_page" => "/forms/",
      "location_details" => "/location-details/",
      "alert" => "/alerts/",
      "action" => "",
      "stacked_layout" => "",
      "decision_tree" => "/decision-tree/",
    ];
    // NOTE: For some content types, like regulation, non-title fields are used
    // to generate the path alias. We cover such types in a separate test.
    // In this test we only cover the above content types that are configured
    // to have title based path alias patterns
    // like "/somefoo/[node:title]" or "/[node:title]".
    // @todo https://jira.state.ma.us/browse/DP-8960.
    foreach ($path_pattern_substring_by_type as $type => $path_pattern_substring) {
      $node = $this->createNode([
        'type' => $type,
        'title' => '_QA Test Content to Check URL Alias For ' . $type,
        'moderation_state' => 'published',
      ]);

      // By default this creates published content.
      $langcode = $node->language()->getId();
      $source = '/' . $node->toUrl()->getInternalPath();
      $path_alias = \Drupal::service('path_alias.manager')->getAliasByPath($source, $langcode);

      // For patterns like "/how-to/some-how-to-page-title".
      if (!empty($path_pattern_substring)) {
        $this->assertStringContainsString($path_pattern_substring, $path_alias);
      }
      // For patterns like "/some-advisory-page-title" where path alias is
      // directly made of content title and does not depend on content type.
      else {
        $this->assertStringStartsWith("/qa-", $path_alias);
      }
      $this->assertStringNotContainsString("---unpublished", $path_alias);
    }
  }

}
