<?php

namespace Drupal\mass_nav\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {

    if ($route = $collection->get('view.subtopics.subtopic_reorder')) {
      $route->setOption('_admin_route', TRUE);
    }
    if ($route = $collection->get('view.ordered_topics.topic_reorder')) {
      $route->setOption('_admin_route', TRUE);
    }
    if ($route = $collection->get('view.news_curated_list.curated_news')) {
      $route->addRequirements([
        'arg_0' => '\d+',
      ]);
    }
  }

}
