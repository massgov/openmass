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

    $request = $event->getRequest();
    $route_name = (string) $request->attributes->get('_route');

    // Routes that participate in admin upload/Batch/AJAX flows where stickiness is needed.
    // - dropzonejs.upload: WYSIWYG/Dropzone uploads
    // - system.batch_page.*: Batch API polling during long operations (e.g. VBO ZIP)
    // - system.ajax / views.ajax: AJAX ops triggered by Views, VBO, etc.
    $pinworthy_routes = [
      'dropzonejs.upload',
      'system.batch_page.html',
      'system.batch_page.json',
      'system.ajax',
      'views.ajax',
    ];

    // Pin on any admin route, or on one of the explicit routes above.
    $should_pin = $this->routerAdminContext->isAdminRoute() || in_array($route_name, $pinworthy_routes, TRUE);

    if (!$should_pin) {
      return;
    }

    // Set cookie to stick file uploads to one server when on admin pages to
    // address issues with uploading files via the WYSIWYG.
    // See https://support.acquia.com/hc/en-us/articles/360004147834-Pinning-to-a-web-server-without-using-the-hosts-file#defineacookie
    $response = $event->getResponse();
    $server_name = explode('.', gethostname());
    $cookie = Cookie::create('ah_app_server', rawurlencode($server_name[0]), $this->time->getRequestTime() + 86400, '/');
    $response->headers->setCookie($cookie);
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
