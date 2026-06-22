<?php

namespace Drupal\mass_caching;

use Drupal\acquia_purge\AcquiaCloud\Hash;
use Drupal\akamai\Event\AkamaiPurgeEvents;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;

/**
 * Hashes Akamai tag-purge requests to match the hashed Edge-Cache-Tag header.
 *
 * The Edge-Cache-Tag response header is hashed by AkamaiHeaderCreationSubscriber
 * (acquia_purge Hash::cacheTags()). Akamai purges by exact string match, so the
 * tag string sent in a Fast Purge request must equal the hashed value stored in
 * the header. The stock AkamaiTagPurger only formats the tag, so it would send
 * unhashed strings (e.g. "node_123") that never match the hashed header value
 * (e.g. "sq46"). This purger hashes the raw tag the same way the header does.
 *
 * Swapped in via mass_caching_purge_purgers_alter().
 */
class AkamaiTagPurger extends \Drupal\akamai\Plugin\Purge\Purger\AkamaiTagPurger {

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    $tags_to_clear = [];
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      // Hash the raw tag so it matches the hashed Edge-Cache-Tag header. A
      // 4-char base32 hash is already Akamai-safe, so no formatting is needed.
      $tag = self::hashTag($invalidation->getExpression());
      // Remove duplicate entries.
      $tags_to_clear[$tag] = $tag;
    }
    // Change it to a normal array so the JSON conversion goes as expected.
    $tags_to_clear = array_values($tags_to_clear);
    // Set invalidation type to tag.
    $this->client->setType('tag');

    // Instantiate event and alter tags with subscribers.
    $event = new AkamaiPurgeEvents($tags_to_clear);
    $this->eventDispatcher->dispatch($event, AkamaiPurgeEvents::PURGE_CREATION);
    $tags_to_clear = $event->data;

    // Purge tags.
    $result = $this->client->purgeTags($tags_to_clear);
    $invalidation_state = InvalidationInterface::SUCCEEDED;
    if (!$result) {
      $invalidation_state = InvalidationInterface::FAILED;
    }
    // If we hit the rate limit, keep this in the queue to run again later.
    if (is_object($result) && ($result->getStatusCode() === 429)) {
      $invalidation_state = InvalidationInterface::PROCESSING;
    }
    // Set Invalidation status.
    foreach ($invalidations as $invalidation) {
      $invalidation->setState($invalidation_state);
    }
  }

  /**
   * Hashes a raw cache tag exactly as the Edge-Cache-Tag header does.
   *
   * @param string $tag
   *   The raw Drupal cache tag, e.g. "node:123".
   *
   * @return string
   *   The hashed tag, e.g. "sq46", matching AkamaiHeaderCreationSubscriber.
   */
  public static function hashTag(string $tag): string {
    return Hash::cacheTags([$tag])[0];
  }

}
