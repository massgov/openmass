<?php

namespace Drupal\mass_caching\EventSubscriber;

use Drupal\acquia_purge\AcquiaCloud\Hash;
use Drupal\akamai\Event\AkamaiHeaderEvents;
use Drupal\akamai\EventSubscriber\CacheableResponseSubscriber;
use Drupal\Core\Cache\CacheableResponseInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Adds hashed Akamai cache tag headers on cacheable responses.
 */
class AkamaiCacheableResponseSubscriber extends CacheableResponseSubscriber {

  /**
   * {@inheritdoc}
   */
  public function onRespond(ResponseEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }

    $response = $event->getResponse();
    $config = $this->configFactory->get('akamai.settings');
    $header = $config->get('edge_cache_tag_header');

    // Send headers if response is cacheable and the setting is enabled.
    if ($header && $response instanceof CacheableResponseInterface) {
      $tags = $response->getCacheableMetadata()->getCacheTags();
      $blacklist = $config->get('edge_cache_tag_header_blacklist');
      $blacklist = is_array($blacklist) ? $blacklist : [];
      $tags = array_filter($tags, function ($tag) use ($blacklist) {
        foreach ($blacklist as $prefix) {
          if (strpos($tag, $prefix) !== FALSE) {
            return FALSE;
          }
        }
        return TRUE;
      });

      // Instantiate our event.
      $event = new AkamaiHeaderEvents($tags);
      $this->eventDispatcher->dispatch($event, AkamaiHeaderEvents::HEADER_CREATION);
      $tags = $event->data;

      $response->headers->set('Edge-Cache-Tag', implode(',', Hash::cacheTags($tags)));
    }
  }

}
