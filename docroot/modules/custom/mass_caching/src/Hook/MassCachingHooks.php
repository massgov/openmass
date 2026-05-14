<?php

declare(strict_types=1);

namespace Drupal\mass_caching\Hook;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileInterface;
use Drupal\mass_caching\AkamaiPurger;
use Drupal\mass_caching\ManualPurger;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\path_alias\PathAliasInterface;
use Drupal\redirect\Entity\Redirect;

/**
 * Hook implementations for mass_caching.
 */
class MassCachingHooks {

  /**
   * Constructs a MassCachingHooks object.
   */
  public function __construct(
    protected StateInterface $state,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected StreamWrapperManagerInterface $streamWrapperManager,
    protected ManualPurger $manualPurger,
    protected AliasManagerInterface $aliasManager,
  ) {}

  /**
   * Acquia Purger and Akamai need conditional disable, so we use plugin alter.
   *
   * This alter is added by a patch to purge module. See
   * https://www.drupal.org/project/purge/issues/2757155#comment-14335663.
   *
   * @param array $definitions
   *   All plugin definitions.
   */
  #[Hook('purge_purgers_alter')]
  public function purgePurgersAlter(array &$definitions): void {
    if ($this->state->get('mass_caching.purger', FALSE)) {
      // For now, we enable this purger via State. When disabled, the standard
      // Akamai purger is active.
      if (isset($definitions['akamai'])) {
        $definitions['akamai']['class'] = AkamaiPurger::class;
      }
    }

    // Change handled types depending on env.
    $purger_enabled = [
      'akamai' => FALSE,
      'acquia_purge' => FALSE,
    ];

    $env = getenv('AH_SITE_ENVIRONMENT');
    if ($env) {
      // We are in an Acquia env.
      $purger_enabled['acquia_purge'] = TRUE;
      if (in_array($env, ['test', 'prod'], TRUE)) {
        $purger_enabled['akamai'] = TRUE;
      }
    }

    if ($purger = getenv('MASS_PURGERS')) {
      // Force the specified purger to on. Used for local testing.
      if (array_key_exists($purger, $purger_enabled)) {
        $purger_enabled[$purger] = TRUE;
      }
    }

    foreach ($purger_enabled as $name => $enabled) {
      // We need to run queue invalidations during testing so that tests pass
      // like AutomatedPurgingTest.
      if (!$enabled && isset($definitions[$name]) && !defined('PHPUNIT_COMPOSER_INSTALL')) {
        // To disable a purger, make it capable of an operation we don't use.
        // It can't be an empty array as we get ValueError in
        // \Drupal\purge\Plugin\Purge\Purger\CapacityTracker::getTimeHintTotal.
        // Purge bug reported at:
        // https://www.drupal.org/project/purge/issues/3298855
        $definitions[$name]['types'] = ['everything'];
      }
    }
  }

  /**
   * Purge the file's path when a file is created, updated, deleted, or moved.
   */
  #[Hook('file_insert')]
  #[Hook('file_update')]
  #[Hook('file_delete')]
  #[Hook('file_move')]
  public function purgeFile(FileInterface $file, ?FileInterface $source = NULL): void {
    if ($this->uriIsPrivate($file)) {
      return;
    }
    // Must purge the file on all domains/schemes, so we use a path purge here,
    // which is converted to a URL.
    $absolute = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
    $relative = $this->fileUrlGenerator->transformRelative($absolute);
    $this->manualPurger->purgePath($relative);
  }

  /**
   * Purges new aliases as they are created.
   *
   * Without this, it's possible to create content that lives at a URL that's
   * already cached with a 404. We choose to implement this at the path level
   * so we can avoid clearing paths for any content that isn't aliased.
   * Technically, this leaves a gap in our purging where the internal path could
   * be stuck with a 404, but it's extremely unlikely that this will happen or
   * matter if it does.
   */
  #[Hook('path_alias_insert')]
  public function pathAliasInsert(PathAliasInterface $path): void {
    $this->manualPurger->purgePath($path->getAlias());
  }

  /**
   * Purges aliases when they change.
   *
   * @see \Drupal\mass_caching\Hook\MassCachingHooks::pathAliasInsert()
   */
  #[Hook('path_alias_update')]
  public function pathAliasUpdate(PathAliasInterface $path): void {
    if ($path->getAlias() !== $path->original->getAlias()) {
      $this->manualPurger->purgePath($path->getAlias());
    }
  }

  /**
   * Purge media's download path and redirect's source URL when entities update.
   *
   * This runs after pathauto's entity hooks so media have aliased URLs before
   * we attempt to clear them.
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
   * Disable caching of local tasks.
   *
   * @see https://massgov.atlassian.net/browse/DP-33081
   */
  #[Hook('block_view_alter')]
  public function blockViewAlter(array &$build, BlockPluginInterface $block): void {
    if ($build['#id'] === 'mass_theme_tabs') {
      $build['#cache']['max-age'] = 0;
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

  /**
   * Determine whether a file uri is private.
   */
  protected function uriIsPrivate(FileInterface $file): bool {
    $destination_scheme = $this->streamWrapperManager::getScheme($file->getFileUri());
    return $destination_scheme === 'private';
  }

}
