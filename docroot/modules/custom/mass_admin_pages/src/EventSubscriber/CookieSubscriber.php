<?php

namespace Drupal\mass_admin_pages\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\EventSubscriber\FinishResponseSubscriber;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response subscriber to add cookies.
 */
class CookieSubscriber extends FinishResponseSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  protected function setResponseNotCacheable(Response $response, Request $request) {
    parent::setResponseNotCacheable($response, $request);

    $server_name = explode('.', gethostname());
    if (\Drupal::currentUser()->isAuthenticated()) {
      $response->headers->clearCookie('ah_app_server', '/');

      // Set cookie to stick file uploads to one server when on edit pages to
      // address issues with uploading files via the WYSIWYG.
      // See https://support.acquia.com/hc/en-us/articles/360004147834-Pinning-to-a-web-server-without-using-the-hosts-file#defineacookie
      if ($request->attributes->get('_route') == 'entity.node.edit_form') {
        $cookie = new Cookie('ah_app_server', rawurlencode($server_name[0]), REQUEST_TIME + 86400, '/');
        $response->headers->setCookie($cookie);
      }
    }
  }

}
