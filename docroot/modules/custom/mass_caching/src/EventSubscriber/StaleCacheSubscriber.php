<?php

namespace Drupal\mass_caching\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\EventSubscriber\FinishResponseSubscriber;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\Core\PageCache\ResponsePolicyInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Response subscriber to add stale cache headers.
 */
class StaleCacheSubscriber extends FinishResponseSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(LanguageManagerInterface $language_manager, ConfigFactoryInterface $config_factory, RequestPolicyInterface $request_policy, ResponsePolicyInterface $response_policy, CacheContextsManager $cache_contexts_manager, $http_response_debug_cacheability_headers = FALSE) {
    parent::__construct($language_manager, $config_factory, $request_policy, $response_policy, $cache_contexts_manager, $http_response_debug_cacheability_headers);
  }

  /**
   * {@inheritdoc}
   */
  protected function setResponseCacheable(Response $response, Request $request) {
    parent::setResponseCacheable($response, $request);
    $response->headers->addCacheControlDirective('stale-if-error', 604800);
    $response->headers->addCacheControlDirective('stale-while-revalidate', 604800);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onRespond', 17];
    return $events;
  }

}
