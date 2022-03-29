<?php

namespace Drupal\mass_media\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\mass_media\Controller\MassMediaDownloadController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class MassMediaRouteSubscriber.
 *
 * Listens to the dynamic route events.
 */
class MassMediaRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('media_entity_download.download')) {
      $route->setDefault('_controller', MassMediaDownloadController::class);
    }
  }

}
