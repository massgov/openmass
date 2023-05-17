<?php

namespace Drupal\mass_route_iframes\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\NodeInterface;
use Drupal\token\Token;

/**
 * Class MassRouteIframeController.
 *
 * @package Drupal\mass_route_iframes\Controller
 */
class MassRouteIframeController extends ControllerBase {

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
      $container->get('token'),
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

    $config_url = $this->token->replace('//lookerstudio.google.com/embed/reporting/7c31eece-2eb6-446b-a4f0-185ba8b8f398/page/A63OD?params=%7B"nodeId":[node:nid]%7D', ['node' => $node]);
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
