<?php

use Drupal\acquia_purge\Hash;
use Drupal\mass_utility\DebugCachability;

class MassCachingContext extends \Behat\MinkExtension\Context\RawMinkContext {

  const TAG_HEADER = 'X-Drupal-Cache-Tags';
  const DYNAMIC_CACHE_HEADER = 'X-Drupal-Dynamic-Cache';

  /**
   * Request cache debugging headers from the backend.
   *
   * @BeforeStep
   */
  public function requestDebugCachabilityHeaders(): void {
    (new DebugCachability())->requestDebugCachabilityHeaders($this->getSession());
  }

  /**
   * Reset the database cache tag invalidators "seen" cache.
   *
   * The database invalidator only allows a tag to be invalidated
   * once per request. Over the course of a Behat test, that means
   * a tag will only ever be cleared once.  This messes with some
   * of our cache tests, because tags we expect to get cleared don't
   * get cleared.
   *
   * Step around the problem by hard resetting the invalidated tags
   * on each step.  This shouldn't be a problem, since each step
   * simulates a single web request.
   *
   * @BeforeStep
   */
  public function resetInvalidations() {
    $invalidator = \Drupal::service('cache_tags.invalidator.checksum');
    $property = new ReflectionProperty(get_class($invalidator), 'invalidatedTags');
    $property->setAccessible(TRUE);
    $property->setValue($invalidator, []);
  }

  /**
   * @Then the ":tag" cache tag should not be used
   */
  public function assertCacheTagNotPresent($tag) {
    $this->assertHasHeader(self::TAG_HEADER);
    $tags = $this->getSession()->getResponseHeader(self::TAG_HEADER);
    $tags = explode(' ', $tags);
    if(array_search($tag, $tags) !== FALSE) {
      throw new RuntimeException(sprintf('%s tag was found', $tag));
    }
  }

  /**
   * @Then the ":tag" cache tag should be used
   */
  public function assertTagPresent($tag) {
    $this->assertHasHeader(self::TAG_HEADER);
    $tags = $this->getSession()->getResponseHeader(self::TAG_HEADER);
    $tags = explode(' ', $tags);
    if(array_search($tag, $tags) === FALSE) {
      throw new RuntimeException(sprintf('%s tag was not found', $tag));
    }
  }

  public function assertHasHeader($header_name) {
    $header = $this->getSession()->getResponseHeader($header_name);
    if ($header === NULL) {
      throw new \RuntimeException(sprintf('Missing %s header', $header_name));
    }
  }

  /**
   * @Then the page should be dynamically cacheable
   *
   * The page is considered dynamically cacheable if the X-Drupal-Dynamic-Cache
   * header says "HIT" or "MISS."  It is not cacheable if the header is
   * "UNCACHEABLE."
   */
  public function assertIsDynamicallyCacheable() {
    $this->assertHasHeader(self::DYNAMIC_CACHE_HEADER);
    $value = $this->getSession()->getResponseHeader(self::DYNAMIC_CACHE_HEADER);
    if (!in_array($value, ['HIT', 'MISS'])) {
      $message = sprintf('Page is not dynamically cacheable. Got: %s', $value);
      throw new \RuntimeException($message);
    }
  }

  /**
   * @Then the page should be dynamically cached
   */
  public function assertIsDynamicallyCached() {
    $this->assertHasHeader(self::DYNAMIC_CACHE_HEADER);
    $value = $this->getSession()->getResponseHeader(self::DYNAMIC_CACHE_HEADER);
    if($value !== 'HIT') {
      $message = sprintf('Page is not dynamically cacheable. Got: %s', $value);
      throw new \RuntimeException($message);
    }
  }

  /**
   * @Then a :type purge should be issued for :pattern
   */
  public function assertPurgeIssued($type, $pattern) {
    /** @var \Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface $queue */
    $queue = \Drupal::service('purge.queue');
    $page = 1;
    $regex = '/' . strtr($pattern, ['/' => '\/']) . '/';
    while($results = $queue->selectPage($page)) {
      $page++;
      foreach($results as $item) {
        if($item->getType() === $type && preg_match($regex, $item->getExpression())) {
          return TRUE;
        }
      }
    }
    throw new \Exception(sprintf('No %s invalidation was found matching %s', $type, $pattern));
  }

  /**
   * @Given the purge queue is empty
   */
  public function purgePurge() {
    /** @var \Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface $queue */
    $queue = \Drupal::service('purge.queue');
    $queue->emptyQueue();
  }
}
