<?php

namespace Drupal\mass_analytics\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\NodeInterface;
use Drupal\token\Token;

/**
 * Class MassAnalyticsController.
 *
 * @package Drupal\mass_analytics\Controller
 */
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
  ];

  /**
   * Drupal\token\Token definition.
   *
   * @var \Drupal\token\Token
   */
  protected $token;

  /**
   * Constructs a new MassRouteIframeController object.
   */
  public function __construct(Token $token) {
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('token')
    );
  }

  /**
   * Build the iframe page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The upcasted node object.
   *
   * @return array
   *   The iframe render array or a no match message render array.
   */
  public function build(NodeInterface $node) {
    $config = $this->config('mass_analytics.settings');
    if (!empty($config->get('looker_studio_url'))) {
      $looker_studio_url = $config->get('looker_studio_url') . '?params=%7B"nodeId":[node:nid]%7D';
      $iframe_url = $this->token->replace($looker_studio_url, ['node' => $node]);
      return [
        '#theme' => 'route_iframe',
        '#config' => $iframe_url,
        '#iframe_height' => 2200,
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
  public function access(NodeInterface $node) {
    return AccessResult::allowedIf(in_array($node->bundle(), self::BUNDLES));
  }

}
