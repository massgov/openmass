<?php

namespace Drupal\mass_translations\Routing;

use Drupal\content_translation\Routing\ContentTranslationRouteSubscriber as ContentTranslationRouteSubscriberBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\mass_translations\Controller\ContentTranslationController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for entity translation routes.
 */
class ContentTranslationRouteSubscriber extends ContentTranslationRouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('entity.node.content_translation_overview');
    if ($route instanceof Route) {
      $negotiator = \Drupal::service('language_negotiator');
      $primary_negotiation_method = $negotiator->getPrimaryNegotiationMethod(LanguageInterface::TYPE_CONTENT);
      if ($primary_negotiation_method === 'language-session') {
        $route->setDefault('_controller', ContentTranslationController::class . '::overview');
        $collection->add('entity.node.content_translation_overview', $route);;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    // Should run after AdminRouteSubscriber so the routes can inherit admin
    // status of the edit routes on entities. Therefore priority -210.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -220];
    return $events;
  }

}
