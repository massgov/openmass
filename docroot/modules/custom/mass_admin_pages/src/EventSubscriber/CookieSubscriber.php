<?php

namespace Drupal\mass_admin_pages\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to add cookies.
 */
class CookieSubscriber implements EventSubscriberInterface {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  protected $routerAdminContext;

  /**
   * {@inheritdoc}
   */
  public function __construct(TimeInterface $time, $routerAdminContext) {
    $this->time = $time;
    $this->routerAdminContext = $routerAdminContext;
  }

  /**
   * {@inheritdoc}
   */
  public function onKernelResponse(ResponseEvent $event) {
    // Set cookie to stick file uploads to one server when on admin pages to
    // address issues with uploading files via the WYSIWYG.
    // See https://support.acquia.com/hc/en-us/articles/360004147834-Pinning-to-a-web-server-without-using-the-hosts-file#defineacookie
    if ($this->routerAdminContext->isAdminRoute()) {
      $response = $event->getResponse();
      $server_name = explode('.', gethostname());
      $cookie = Cookie::create('ah_app_server', rawurlencode($server_name[0]), $this->time->getRequestTime() + 86400, '/');
      $response->headers->setCookie($cookie);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Act after the finish ResponseSubscriber.
    $events[KernelEvents::RESPONSE][] = ['onKernelResponse'];
    return $events;
  }

}
