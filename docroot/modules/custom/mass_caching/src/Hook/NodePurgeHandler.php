<?php

declare(strict_types=1);

namespace Drupal\mass_caching\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\mass_caching\ManualPurger;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;

/**
 * Queues URL purge invalidations for public-facing node saves.
 */
class NodePurgeHandler {

  /**
   * The manual purger.
   *
   * @var \Drupal\mass_caching\ManualPurger
   */
  protected ManualPurger $manualPurger;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected AliasManagerInterface $aliasManager;

  /**
   * Constructs a NodePurgeHandler object.
   */
  public function __construct(
    ManualPurger $manual_purger,
    AliasManagerInterface $alias_manager,
  ) {
    $this->manualPurger = $manual_purger;
    $this->aliasManager = $alias_manager;
  }

  /**
   * Purge URL paths on entity update.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return void
   *   This method does not return any value.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  #[Hook('entity_update', order: new OrderAfter(modules: ['pathauto']))]
  public function entityUpdate(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface) {
      $this->purgeNode($entity);
    }
  }

  /**
   * Purge URL paths on entity insert.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return void
   *   This method does not return any value.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  #[Hook('entity_insert', order: new OrderAfter(modules: ['pathauto']))]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface) {
      $this->purgeNode($entity);
    }
  }

  /**
   * Purge URL paths for public-facing node saves.
   *
   * This intentionally complements tag-based purging: Akamai's configured
   * purger handles URL invalidations, so the public node saves need explicit
   * URL items.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node entity.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function purgeNode(NodeInterface $node): void {
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
   * Determine if a node save should purge public Akamai URLs.
   *
   * Draft-only saves should not evict public cache entries. Published edits and
   * unpublishes should, because the public response either changed or
   * disappeared.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node entity.
   *
   * @return bool
   *   TRUE when the save affects the public/default revision.
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
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node entity.
   *
   * @return string|null
   *   The current alias, or NULL when there is not one.
   */
  private function getCurrentAlias(NodeInterface $node): ?string {
    $internal_path = '/node/' . $node->id();
    $alias = $this->aliasManager->getAliasByPath($internal_path);
    return $alias !== $internal_path ? $alias : NULL;
  }

  /**
   * Get a path field alias from the in-memory node object.
   *
   * This captures the previous alias from $node->getOriginal() during alias changes.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node entity.
   *
   * @return string|null
   *   The path alias, or NULL when unavailable.
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
