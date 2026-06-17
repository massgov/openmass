<?php

declare(strict_types=1);

namespace Drupal\mass_redirect_normalizer\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mass_redirect_normalizer\RedirectLinkQueueEnqueuer;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * OOP hooks for mass redirect normalizer.
 */
final class RedirectNormalizerHooks {

  /**
   * Constructs hook handlers.
   */
  public function __construct(
    private readonly RedirectLinkQueueEnqueuer $enqueuer,
  ) {}

  /**
   * Enqueues node/paragraph entities for async redirect normalization.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface && !$entity instanceof ParagraphInterface) {
      return;
    }

    // Avoid re-enqueueing while the queue worker saves normalized entities.
    if (!empty($_ENV['MASS_REDIRECT_NORMALIZER_QUEUE_PROCESSING'])) {
      return;
    }

    $this->enqueuer->enqueueEntity($entity, 'presave');
  }

}
