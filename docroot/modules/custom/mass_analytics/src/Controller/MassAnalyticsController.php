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

  const SCOPE = [
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
   * State service object.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new MassRouteIframeController object.
   */
  public function __construct(Token $token, StateInterface $state) {
    $this->token = $token;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('token'),
      $container->get('state')
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
    $looker_studio_url = $this->state->get('mass_analytics.looker_studio_url', '') . '?params=%7B"nodeId":[node:nid]%7D';
    $config_url = $this->token->replace($looker_studio_url, ['node' => $node]);
    return [
      '#theme' => 'route_iframe',
      '#config' => $config_url,
      '#iframe_height' => 2200,
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
  public function access(NodeInterface $node) {
    $access = FALSE;
    $node->bundle();
    if (in_array($node->bundle(), self::SCOPE)) {
      $access = TRUE;
    }
    return AccessResult::allowedIf($access);
  }

}
