<?php

namespace Drupal\mass_jsonapi\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Mass JSONAPI event subscriber.
 */
class MassJsonapiSubscriber implements EventSubscriberInterface {

  /**
   * Constructs event subscriber.
   */
  public function __construct() {}

  /**
   * Change JSONAPI responses to application/json type.
   *
   * Acquia does not cache 'application/vnd.api+json' responses, and we were
   * unable to coax it into doing so via .htaccess. After careful consideration,
   * changing content type is most reliable workaround. See
   * https://www.drupal.org/project/jsonapi/issues/2843744.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   Response event.
   */
  public function onKernelResponse(ResponseEvent $event) {
    $response = $event->getResponse();
    if ($response->headers->get('Content-Type') == 'application/vnd.api+json') {
      $response->headers->set('Content-Type', 'application/json');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onKernelResponse'],
    ];
  }

}
