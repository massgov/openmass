<?php

namespace Drush\Commands;

use Drupal\Core\Cache\Cache;
use Drush\Utils\StringUtils;

class CacheBackportCommands extends DrushCommands {

  /**
   * Invalidate by cache tags.
   *
   * Note: This is a temporary backport of a Drush 10 feature.
   * Remove when we go to Drush 10.
   *
   * @command cache:tags
   * @param string $tags A comma delimited list of cache tags to clear.
   * @aliases ct
   * @bootstrap full
   * @usage drush cache:tag node:12,user:4
   *   Purge content associated with two cache tags.
   */
  public function tags($tags)
  {
    $tags = StringUtils::csvToArray($tags);
    Cache::invalidateTags($tags);
    $this->logger()->success(dt("Invalidated tag(s): !list.", ['!list' => implode(' ', $tags)]));
  }

}
