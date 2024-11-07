<?php

namespace Drupal\mass_utility\EventSubscriber;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheableResponseInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class MassUtilityCommands.
 *
 * @package Drupal\mass_utility\Commands
 */
class ErrorPageCachingSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Run before the DynamicPageCacheSubscriber (100)
      // so our manipulations get saved in the page cache.
      KernelEvents::RESPONSE => ['manipulateResponse', 105],
    ];
  }

  /**
   * On response, strip certain contexts from 403/404 responses.
   *
   * This greatly increases cacheability.  When the 403/404 pages
   * have the url or url.path context, they are rebuilt for every
   * single unique 403/404 URL.  By removing these contexts, they
   * are stored once and reused every time a 403/404 exception is
   * raised.
   *
   * The tradeoff here is that we lose the ability to show different
   * 403/404 pages depending on the URL it's accessed on.
   */
  public function manipulateResponse(ResponseEvent $event) {
    if ($this->eventIsHtmlExceptionResponse($event)) {
      $response = $event->getResponse();
      if ($response instanceof CacheableResponseInterface) {
        $contexts = $response->getCacheableMetadata()->getCacheContexts();
        $contexts = array_diff($contexts, ['url', 'url.path']);
        $response->getCacheableMetadata()->setCacheContexts($contexts);
        // Force max lifetime to permanent - it is now, but this has been
        // flaky for us in the past due to block placement.
        $response->getCacheableMetadata()->setCacheMaxAge(CacheBackendInterface::CACHE_PERMANENT);
      }
    }
  }

  /**
   * Check that an event is one we care about.
   *
   * We care about events that are subrequests, originate from
   * an exception, and have an HTML request format.
   */
  protected function eventIsHtmlExceptionResponse(ResponseEvent $event) {
    return $event->getRequestType() === HttpKernelInterface::SUB_REQUEST
      && $event->getRequest()->attributes->has('exception')
      && in_array($event->getRequest()->query->getDigits('_exception_statuscode'), [403, 404])
      && $event->getRequest()->getRequestFormat() === 'html';
  }

}
