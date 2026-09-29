<?php

namespace Drupal\mass_jsonapi\EventSubscriber;

use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Mass JSONAPI event subscriber.
 */
class MassJsonapiSubscriber implements EventSubscriberInterface {

  /**
   * The internal-only field kept out of the API.
   */
  private const INTERNAL_FIELD = 'field_sme_content_owner';

  /**
   * The vocabulary behind that field, also internal.
   */
  private const INTERNAL_VOCABULARY = 'sme_owner';

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
   * Keeps internal editorial fields out of the public API.
   *
   * The SME / content owner names staff members and is for internal tracking
   * only, so neither the reference on content nor the vocabulary listing every
   * owner may be served to anonymous API clients.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent $event
   *   The resource type build event.
   */
  public function onResourceTypeBuild(ResourceTypeBuildEvent $event) {
    if ($event->getResourceTypeName() === 'taxonomy_term--' . self::INTERNAL_VOCABULARY) {
      $event->disableResourceType();
      return;
    }

    foreach ($event->getFields() as $field) {
      if ($field->getInternalName() === self::INTERNAL_FIELD) {
        $event->disableField($field);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onKernelResponse'],
      ResourceTypeBuildEvents::BUILD => ['onResourceTypeBuild'],
    ];
  }

}
