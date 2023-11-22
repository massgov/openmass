<?php

namespace Drupal\mass_analytics\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

class MassAnalyticsController extends ControllerBase {

  const BUNDLES = [
    'binder',
    'curated_list',
    'org_page',
    'service_page',
    'topic_page',
    'guide_page',
    'how_to_page',
    'info_details',
    'location',
    'location_details',
    'service_details',
    'form_page',
    'advisory',
    'news',
    'event',
    'campaign_landing',
    'decision',
    'executive_order',
    'regulation',
  ];

  /**
   * Build the iframe page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The upcasted node object.
   *
   * @return array
   *   The iframe render array or a no match message render array.
   */
  public function build(NodeInterface $node): array {
    $config = $this->config('mass_analytics.settings');
    if (!empty($config->get('looker_studio_url'))) {
      $iframe_url = $config->get('looker_studio_url') . '?params=%7B"nodeId":' . $node->id() . ',"nodeId2":' . $node->id() . '%7D';
      return [
        '#theme' => 'mass_analytics_iframe',
        '#config' => $iframe_url,
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }
    return [];
  }

  /**
   * Access callback for the page.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Return true if the node is one of the list.
   */
  public function access(NodeInterface $node): AccessResult {
    return AccessResult::allowedIf(in_array($node->bundle(), self::BUNDLES));
  }

}
