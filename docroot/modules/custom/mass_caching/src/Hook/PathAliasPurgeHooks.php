<?php

declare(strict_types=1);

namespace Drupal\mass_caching\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mass_caching\ManualPurger;
use Drupal\path_alias\PathAliasInterface;

/**
 * Hook implementations for path alias purge invalidations.
 */
class PathAliasPurgeHooks {

  /**
   * Constructs a PathAliasPurgeHooks object.
   */
  public function __construct(
    protected ManualPurger $manualPurger,
  ) {}

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
   */
  #[Hook('path_alias_update')]
  public function pathAliasUpdate(PathAliasInterface $path): void {
    if ($path->getAlias() !== $path->original->getAlias()) {
      $this->manualPurger->purgePath($path->getAlias());
    }
  }

}
