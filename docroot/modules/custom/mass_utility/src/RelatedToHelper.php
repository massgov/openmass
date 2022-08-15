<?php

namespace Drupal\mass_utility;

/**
 * This class encapsulates the logic of determining related nodes.
 *
 * Specifically, it replaces some views that we were using for the same thing.
 * Using direct queries offers better performance, more granular control over
 * cache tags, and less fragility.
 */
class RelatedToHelper {

  /**
   * Get a list of topics that reference a given node.
   *
   * Executes at most 2 queries (plus entity loading):
   *   Select cards that reference the node.
   *   Select the nodes the cards belong to.
   *
   * No substring matching is used in this method.
   *
   * @param int $nid
   *   The ID of the node to check for relations to.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of related topics.
   */
  public static function getRelatedTopicsByCardLinks($nid) {
    $route = sprintf('entity:node/%d', $nid);
    $paragraphQuery = \Drupal::entityQuery('paragraph');
    $paragraphQuery->condition('field_content_card_link_cards.uri', $route);

    if ($pids = $paragraphQuery->execute()) {
      $topicQuery = \Drupal::entityQuery('node');
      $topicQuery->condition('field_topic_content_cards.target_id', $pids, 'IN');
      $topicQuery->sort('created', 'DESC');
      $topicQuery->condition('status', 1);

      $nids = $topicQuery->execute();
      return \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    }

    return [];
  }

  /**
   * Gets related services based on values in the "Featured Tasks" field.
   *
   * @param int $nid
   *   The ID of the node to check for relations to.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Related entities.
   */
  public static function getRelatedServicesByActionLinks($nid) {
    $route = sprintf('entity:node/%d', $nid);
    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    // To separate the query into two to improve the performance on the how-to pages.
    $que1 = (clone $query)->condition('field_service_ref_actions.uri', $route)->execute();
    $que2 = (clone $query)->condition('field_service_ref_actions_2.uri', $route)->execute();
    $nids = array_merge($que1, $que2);

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);

    return $nodes;
  }

  /**
   * Get a list of all service pages that reference a page via guide links.
   *
   * @param int $nid
   *   The ID of the node to check for relations to.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of related nodes.
   */
  public static function getRelatedServicePagesByGuideLinks($nid) {
    $query = \Drupal::entityQuery('node');
    $query->condition('field_service_ref_guide_page_1.target_id', $nid);
    $query->condition('status', 1);
    $nids = $query->execute();

    return \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
  }

  /**
   * Get a list of all locations that reference a page via location details.
   *
   * @param int $nid
   *   The ID of the node to check for relations to.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of related nodes.
   */
  public static function getRelatedLocationByLocationDetail($nid) {
    $paragraphQuery = \Drupal::entityQuery('paragraph');
    $paragraphQuery->condition('field_ref_location_details_page.target_id', $nid);

    if ($pids = $paragraphQuery->execute()) {
      $query = \Drupal::entityQuery('node');
      $query->condition('field_location_activity_detail.target_id', $pids, 'IN');
      $query->condition('status', 1);
      $query->sort('created', 'DESC');

      $nids = $query->execute();
      return \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    }
    else {
      return [];
    }
  }

  /**
   * Get a list of all nodes that reference a page via locations fields.
   *
   * @param int $nid
   *   The ID of the node to check for relations to.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of related nodes.
   */
  public static function getRelatedLocationsByLocations($nid) {
    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);

    // This query performs poorly if we execute it as one query with an OR
    // condition.
    $res1 = (clone $query)->condition('field_service_ref_locations.target_id', $nid)->execute();
    $res2 = (clone $query)->condition('field_org_ref_locations.target_id', $nid)->execute();
    $nids = array_merge($res1, $res2);

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    // Sort by created, DESC.
    usort($nodes, function ($a, $b) {
      return ($a->getCreatedTime() < $b->getCreatedTime()) ? 1 : -1;
    });
    return $nodes;
  }

  /**
   * Get a list of all services that reference a page via eligiblity field.
   *
   * @param int $nid
   *   The ID of the node to check for relations to.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of related nodes.
   */
  public static function getRelatedServicesByEligibility($nid) {
    $query = \Drupal::entityQuery('node');
    $query->condition('field_service_eligibility_info.target_id', $nid);
    $query->condition('status', 1);
    $nids = $query->execute();

    return \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
  }

}
