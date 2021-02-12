<?php

namespace Drupal\mass_admin_pages\EventSubscriber;

use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\Core\PageCache\ResponsePolicyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\EventSubscriber\FinishResponseSubscriber;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Response subscriber to add cookies.
 */
class CookieSubscriber extends FinishResponseSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(LanguageManagerInterface $language_manager, ConfigFactoryInterface $config_factory, RequestPolicyInterface $request_policy, ResponsePolicyInterface $response_policy, CacheContextsManager $cache_contexts_manager, $http_response_debug_cacheability_headers = FALSE) {
    parent::__construct($language_manager, $config_factory, $request_policy, $response_policy, $cache_contexts_manager, $http_response_debug_cacheability_headers);
  }

  /**
   * {@inheritdoc}
   */
  public function onAllResponds(FilterResponseEvent $event) {
    parent::onAllResponds($event);
    // Set cookie to stick file uploads to one server when on admin pages to
    // address issues with uploading files via the WYSIWYG.
    // See https://support.acquia.com/hc/en-us/articles/360004147834-Pinning-to-a-web-server-without-using-the-hosts-file#defineacookie
    if (\Drupal::service('router.admin_context')->isAdminRoute()) {
      $response = $event->getResponse();
      $server_name = explode('.', gethostname());
      $cookie = new Cookie('ah_app_server', rawurlencode($server_name[0]), REQUEST_TIME + 86400, '/');
      $response->headers->setCookie($cookie);
    }
  }

}
