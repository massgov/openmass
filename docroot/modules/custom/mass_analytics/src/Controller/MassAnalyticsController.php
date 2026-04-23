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
    $iframe_url = 'https://app.powerbigov.us/reportEmbed?reportId=5180080b-8681-424d-a679-c45fe3037bf6&autoAuth=true&ctid=3e861d16-48b7-4a0e-9806-8c04d81b7b2a&filter=aggregated_node_analytics%2FnodeId+eq+' . $node->id() . '&filterPaneEnabled=false&navContentPaneEnabled=false';

    return [
      '#theme' => 'mass_analytics_iframe',
      '#config' => $iframe_url,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
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
