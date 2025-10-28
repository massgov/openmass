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

    // Validate and sanitize lat/lng using helper.
    if ($q->has('lat')) {
      $lat = $this->sanitizeCoordinate($q->get('lat'), -90, 90);
      if ($lat === null) {
        $q->remove('lat');
      }
      else {
        $q->set('lat', $lat);
      }
    }

    if ($q->has('lng')) {
      $lng = $this->sanitizeCoordinate($q->get('lng'), -180, 180);
      if ($lng === null) {
        $q->remove('lng');
      }
      else {
        $q->set('lng', $lng);
      }
    }
  }

  /**
   * Sanitize a coordinate by trimming spaces/quotes and validating range.
   *
   * @return string|null
   *   Returns normalized numeric string if valid, or NULL if invalid.
   */
  private function sanitizeCoordinate($value, float $min, float $max): ?string {
    if (is_string($value)) {
      $value = trim($value);
      $value = trim($value, "'\""); // remove quotes
    }
    if (!is_numeric($value)) {
      return NULL;
    }
    $value = (float) $value;
    if ($value < $min || $value > $max) {
      return NULL;
    }
    return (string) $value;
  }

}
