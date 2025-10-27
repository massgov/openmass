<?php

namespace Drupal\mass_views\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Normalizes and validates lat/lng query params early in the request.
 */
final class GeoRequestSanitizer implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    // Early enough to affect Views query building.
    return [KernelEvents::REQUEST => ['onRequest', 28]];
  }

  public function onRequest(RequestEvent $event): void {
    // Only for master requests.
    if (!$event->isMainRequest()) {
      return;
    }

    $req = $event->getRequest();
    $q = $req->query;

    // Only apply to the Locations view.
    $route_name = $req->attributes->get('_route');
    if ($route_name !== 'view.locations.page') {
      return;
    }

    // Remove lat/lng if empty or invalid.
    if ($q->has('lat')) {
      $lat = $q->get('lat');
      if (!is_numeric($lat) || $lat < -90 || $lat > 90) {
        $q->remove('lat');
      }
    }

    if ($q->has('lng')) {
      $lng = $q->get('lng');
      if (!is_numeric($lng) || $lng < -180 || $lng > 180) {
        $q->remove('lng');
      }
    }
  }

}
