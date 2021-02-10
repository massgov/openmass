<?php

namespace Drupal\mass_admin_pages\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\EventSubscriber\FinishResponseSubscriber;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\Core\PageCache\ResponsePolicyInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

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
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['checkForContentEditPage'];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function checkForContentEditPage(FilterResponseEvent $event) {
    $server_name = explode('.', gethostname());
    if (\Drupal::currentUser()->isAuthenticated()) {
      if ($event->getRequest()->attributes->get('_route') == 'entity.node.edit_form') {
        $cookie = new Cookie('ah_app_server', rawurlencode($server_name[0]), REQUEST_TIME + 86400, '/');
        $event->getResponse()->headers->setCookie($cookie);
      }
      else {
        $cookie = new Cookie('ah_app_server', '', REQUEST_TIME - 3600, '/');
        $event->getResponse()->headers->setCookie($cookie);
      }
    }
  }

}
