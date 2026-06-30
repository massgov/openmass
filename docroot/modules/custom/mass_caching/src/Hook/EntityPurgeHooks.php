<?php

declare(strict_types=1);

namespace Drupal\mass_caching\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\mass_caching\ManualPurger;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\redirect\Entity\Redirect;

/**
 * Hook implementations for entity purge invalidations.
 */
class EntityPurgeHooks {

  /**
   * Constructs an EntityPurgeHooks object.
   */
  public function __construct(
    protected ManualPurger $manualPurger,
    protected AliasManagerInterface $aliasManager,
  ) {}

  /**
   * Purge URL paths when entities are inserted or updated.
   *
   * This runs after pathauto's entity hooks so that aliases are available
   * before we attempt to clear them.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  #[Hook('entity_update', order: new OrderAfter(modules: ['pathauto']))]
  #[Hook('entity_insert', order: new OrderAfter(modules: ['pathauto']))]
  public function purgeEntityInsertOrUpdate(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface) {
      $this->purgeNode($entity);
    }
    elseif ($entity instanceof MediaInterface) {
      $this->purgeMedia($entity);
    }
    elseif ($entity instanceof Redirect) {
      $this->purgeRedirect($entity);
    }
  }

  /**
   * Purge URL paths for public-facing node saves.
   *
   * This intentionally complements tag-based purging: Akamai's configured
   * purger handles URL invalidations, so the public node saves need explicit
   * URL items.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function purgeNode(NodeInterface $node): void {
    if (!$this->shouldPurge($node)) {
      return;
    }

    $paths = ['/node/' . $node->id()];
    $paths[] = $this->getCurrentAlias($node);
    $paths[] = $this->getPathFieldAlias($node);

    if (!empty($node->getOriginal()) && $node->getOriginal() instanceof NodeInterface) {
      $paths[] = $this->getPathFieldAlias($node->getOriginal());
    }

    foreach (array_unique(array_filter($paths)) as $path) {
      $this->manualPurger->purgePath($path);
    }
  }

  /**
   * Purge paths for media entities.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function purgeMedia(MediaInterface $entity): void {
    $paths[] = '/media/' . $entity->id() . '/download';
    $paths[] = $entity->toUrl()->toString() . '/download';
    // array_unique() because the entity provided URL for unaliased media will
    // be the same as /media/123/download.
    foreach (array_unique($paths) as $path) {
      $this->manualPurger->purgePath($path);
    }
  }

  /**
   * Purge source path for redirect entities.
   */
  protected function purgeRedirect(Redirect $entity): void {
    $this->manualPurger->purgePath($entity->getSourceUrl());
  }

  /**
   * Determine if a node save should purge public Akamai URLs.
   *
   * Draft-only saves should not evict public cache entries. Published edits and
   * unpublishes should, because the public response either changed or
   * disappeared.
   */
  private function shouldPurge(NodeInterface $node): bool {
    if (!$node->isDefaultRevision()) {
      return FALSE;
    }

    if ($node->isPublished()) {
      return TRUE;
    }

    return !empty($node->getOriginal())
      && $node->getOriginal() instanceof NodeInterface
      && $node->getOriginal()->isPublished();
  }

  /**
   * Get the node's current canonical alias.
   */
  private function getCurrentAlias(NodeInterface $node): ?string {
    $internal_path = '/node/' . $node->id();
    $alias = $this->aliasManager->getAliasByPath($internal_path);
    return $alias !== $internal_path ? $alias : NULL;
  }

  /**
   * Get a path field alias from the in-memory node object.
   *
   * This captures the previous alias from $node->getOriginal() during alias
   * changes.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function getPathFieldAlias(NodeInterface $node): ?string {
    if (!$node->hasField('path') || $node->get('path')->isEmpty()) {
      return NULL;
    }

    $value = $node->get('path')->first()->getValue();
    if (empty($value['alias']) || !is_string($value['alias'])) {
      return NULL;
    }

    return str_starts_with($value['alias'], '/') ? $value['alias'] : NULL;
  }

}
