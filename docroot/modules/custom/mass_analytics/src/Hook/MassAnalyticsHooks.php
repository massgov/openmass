<?php

namespace Drupal\mass_analytics\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\mass_analytics\Controller\MassAnalyticsController;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MassAnalyticsHooks {

  public function __construct(
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Point the "Analytics" local task on nodes directly at the Power BI report
   * and open it in a new tab, so users skip the intermediate Drupal page.
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(array &$data, $route_name): void {
    if (!isset($data['tabs'][0]['mass_analytics.analytics'])) {
      return;
    }
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface) {
      return;
    }
    if (!in_array($node->bundle(), MassAnalyticsController::BUNDLES, TRUE)) {
      return;
    }
    $data['tabs'][0]['mass_analytics.analytics']['#link']['url'] = Url::fromUri(
      MassAnalyticsController::reportUrl($node),
      ['attributes' => ['target' => '_blank', 'rel' => 'noopener']]
    );
  }

}
