<?php

namespace Drupal\mass_tome\EventSubscriber;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\Routing\Routes;
use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\EventSubscriber\RoutePathSubscriber as RoutePathSubscriberBase;

/**
 * Overrides the class provided by Tome Static
 */
class RoutePathSubscriber extends RoutePathSubscriberBase {

  /**
   * Adds
   *  - is_admin check.
   *  - skips JSON API routes
   *  - skips redirects
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   */
  public function collectPaths(CollectPathsEvent $event) {
    $admin_context = \Drupal::service('router.admin_context');
    $language_none = $this->languageManager
      ->getLanguage(LanguageInterface::LANGCODE_NOT_APPLICABLE);
    foreach ($this->routeProvider->getAllRoutes() as $route_name => $route) {
      try {
        if ($admin_context->isAdminRoute($route) || $route->hasDefault(Routes::JSON_API_ROUTE_FLAG_KEY)) {
          continue;
        }
        $url = Url::fromRoute($route_name, [], [
          'language' => $language_none,
        ]);
        $path = $url->toString();
        if ($path && $url->access()) {
          $event->addPath(parse_url($path, PHP_URL_PATH));
        }
      }
      catch (\Exception $e) {
      }
    }
  }

}
