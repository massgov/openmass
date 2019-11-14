<?php

namespace Drupal\mass_alerts\Subscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Cache manipulation subscriber for JSON alerts endpoint.
 */
class AlertsJSONAPISubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // Run before DynamicPageCacheSubscriber, which saves tags.
      KernelEvents::RESPONSE => ['convertResponse', 200],
    ];
  }

  /**
   * Strip the node_list tag off of the jsonapi/node/alert collection response.
   */
  public function convertResponse(FilterResponseEvent $event) {
    $request = $event->getRequest();
    if ($request->attributes->get('_route') === 'jsonapi.node--alert.collection') {
      $response = $event->getResponse();
      if ($response instanceof CacheableResponseInterface) {
        $metadata = $response->getCacheableMetadata();
        $tags = array_filter($metadata->getCacheTags(), function ($tag) {
          return $tag !== 'node_list' && $tag !== 'handy_cache_tags:node:alert';
        });
        $tags[] = 'handy_cache_tags:node:alert';
        $metadata->setCacheTags($tags);
      }
    }
  }

}
