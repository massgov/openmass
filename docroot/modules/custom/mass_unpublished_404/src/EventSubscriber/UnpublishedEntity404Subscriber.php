<?php

namespace Drupal\mass_unpublished_404\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\mass_content_moderation\MassModeration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Route;

/**
 * Converts 403s to 404s for unpublished content entities (anonymous only).
 *
 * Content Moderation is respected first; if not present, falls back to status.
 */
final class UnpublishedEntity404Subscriber extends HttpExceptionSubscriberBase implements EventSubscriberInterface {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly ?ModerationInformationInterface $moderationInfo = NULL,
  ) {}

  /**
   * Run late so other access checks can determine 403 first.
   */
  protected static function getPriority() {
    return 1000;
  }

  /**
   * We only care about HTML requests.
   */
  protected function getHandledFormats() {
    return ['html'];
  }

  /**
   * Convert 403 to 404.
   *
   * Convert for anonymous users on canonical entity routes
   * when the routed content entity is effectively unpublished.
   */
  public function on403(ExceptionEvent $event): void {
    // Only main requests.
    if (!$event->isMainRequest()) {
      return;
    }

    if ($this->currentUser->isAuthenticated()) {
      return;
    }

    $request = $event->getRequest();

    // Only apply for canonical node or media routes (based on route params).
    $node = $request->attributes->get('node');
    $media = $request->attributes->get('media');
    if (!$node && !$media) {
      return;
    }

    // Skip admin routes defensively when available.
    $route = $request->attributes->get('_route_object');
    if ($route instanceof Route && $route->getOption('_admin_route')) {
      return;
    }

    foreach ($request->attributes->all() as $value) {
      if ($value instanceof ContentEntityInterface) {
        if ($this->isEffectivelyUnpublished($value)) {
          $event->setThrowable(new NotFoundHttpException('Page not found.', $event->getThrowable()));
          return;
        }
      }
    }
  }

  /**
   * True if the routed entity should be treated as unpublished.
   *
   * Priority:
   *  1) Content Moderation published-state flag for the entity's current state.
   *  2) EntityPublishedInterface::isPublished().
   *  3) Boolean `status` field (per-revision).
   */
  private function isEffectivelyUnpublished(ContentEntityInterface $entity): bool {
    // 1) Content Moderation: use the workflow's published-state flag.
    if ($this->moderationInfo && $this->moderationInfo->isModeratedEntity($entity)) {
      if ($entity->hasField('moderation_state') && !$entity->get('moderation_state')->isEmpty()) {
        $state = (string) $entity->get('moderation_state')->value;
        // Treat as unpublished unless it is explicitly the published state.
        return $state !== MassModeration::PUBLISHED;
      }
      // Moderated but no state value: treat as unpublished (defensive default).
      return TRUE;
    }

    // 2) Generic published interface.
    if ($entity instanceof EntityPublishedInterface) {
      return !$entity->isPublished();
    }

    // 3) Fallback to boolean `status` field if present.
    if ($entity->hasField('status') && !$entity->get('status')->isEmpty()) {
      return !$entity->get('status')->value;
    }

    // No publish concept: do not flip to 404.
    return FALSE;
  }

}
