<?php

namespace Drush\Commands;

use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Symfony\Component\Finder\Glob;

/**
 * Memcache debugging commands.
 */
class MemcacheCommands extends DrushCommands {

  /**
   * @return \Memcached
   */
  private function getMemcached() {
    /** @var \Drupal\memcache\DrupalMemcacheFactory $factory */
    $factory = \Drupal::service('memcache.factory');

    $driver = $factory->get('_dummy_');

    $reflection = new \ReflectionObject($driver);
    $property = $reflection->getProperty('memcache');
    $property->setAccessible(TRUE);
    return $property->getValue($driver);
  }

  /**
   * Show memcache information/statistics.
   *
   * @bootstrap full
   * @command memcache:stats
   * @aliases memst
   * @usage drush memcache:stats
   *   Display the data for the cache id "hook_info" from the "bootstrap" bin.
   *
   * @return RowsOfFields
   */
  public function memcacheInfo(array $options = ['format' => 'table']) {
    $return = new RowsOfFields([]);
    foreach($this->getMemcached()->getStats() as $server => $stats) {
      foreach($stats as $key => $value) {
        $return[$key]['name'] = $key;
        $return[$key][$server] = $value;
      }
    }

    return $return;
  }

  /**
   * Show memcache health.
   *
   * @bootstrap full
   * @command memcache:health
   * @aliases memh
   * @usage drush memcache:health
   *   Show health statistics.
   *
   * @field-labels
   *   get_set: Get/Set Ratio
   *   hit_miss: Hit/Miss Ratio
   *   usage: Usage
   *
   * @return PropertyList
   */
  public function memcacheHealth() {
    $stats = $this->getMemcached()->getStats();
    $gets = $this->sumStat('cmd_get', $stats);
    $sets = $this->sumStat('cmd_set', $stats);
    $hits = $this->sumStat('get_hits', $stats);
    $misses = $this->sumStat('get_misses', $stats);
    $totalMem = $this->sumStat('limit_maxbytes', $stats);
    $usedMem = $this->sumStat('bytes', $stats);
    return new PropertyList([
      'get_set' => $this->formatPct($sets, ($sets + $gets)),
      'hit_miss' => $this->formatPct($hits, ($hits + $misses)),
      'usage' => $this->formatPct($usedMem, $totalMem),
    ]);
  }

  /**
   * Search for keys in memcache.
   *
   * This is a "raw" search - it will return data from all bins and all prefixes.
   * You need to know what prefix and bin you want to look in.
   *
   * @bootstrap full
   * @command memcache:keys
   * @option limit The number of keys to show.
   * @aliases memk
   * @usage drush memcache:keys
   *   List all keys.
   * @usage drush memcache:keys 'foo*'
   *   List all keys starting with 'foo'
   *
   * @param string $pattern Glob pattern to match keys to.
   *
   * @return PropertyList
   */
  public function listKeys(string $pattern = '*', array $options = ['format' => 'table', 'limit' => -1]) {
    $memcached = $this->getMemcached();
    return new PropertyList($this->fetchKeys($memcached, $pattern, $options['limit']));
  }

  /**
   * Calculate the size of a set of keys.
   *
   * Returns keys, sorted by size.
   *
   * @bootstrap full
   * @command memcache:sizes
   * @option limit The number of keys to show.
   * @aliases memsizes
   * @usage drush memcache:sizes
   *   Find the size of all keys in the bucket.
   * @usage drush memcache:sizes 'foo*'
   *   Find the size of all keys in the bucket starting with 'foo'
   *
   * @param string $pattern Glob pattern to match keys to.
   *
   * @return PropertyList
   *   Keys, sorted by size.
   */
  public function sizes(string $pattern = '*', array $options = ['format' => 'table', 'limit' => -1]) {
    $memcache = $this->getMemcached();
    $return = [];
    foreach($this->fetchKeys($memcache, $pattern) as $key) {
      $item = $memcache->get($key);
      $size = strlen(serialize($item->data));
      $return[] = [
        'key' => $key,
        'cid' => $item->cid,
        'raw' => $size,
        'formatted' => drush_format_size($size),
      ];
    }
    uasort($return, function($a, $b) {
      if($a['raw'] === $b['raw']) {
        return 0;
      }
      return ($a['raw'] < $b['raw'] ? 1 : -1);
    });

    return new RowsOfFields($options['limit'] ? array_slice($return, 0, $options['limit']) : $return);
  }

  /**
   * Lookup a CID for a memcache key.
   *
   * @bootstrap full
   * @command memcache:cid
   * @aliases memcg
   * @usage drush memcache:cid d28d11c1c29cd781fb6dc030eeae99a546069015
   *   Print the CID of the key 'd28d11c1c29cd781fb6dc030eeae99a546069015'.
   *
   * @param string $key Memcache key.
   *
   * @return string
   *   The found item's CID.
   */
  public function cid(string $key) {
    $memcache = $this->getMemcached();
    if ($item = $memcache->get($key)) {
      return $item->cid;
    }
    throw new \Exception(sprintf('No item found with key "%s"', $key));
  }

  private function fetchKeys(\Memcached $memcached, $pattern, $limit = -1) {
    if ($limit === -1) {
      $keys = $memcached->getAllKeys();
    }
    else {
      $keys = array_slice($memcached->getAllKeys(), 0, $limit);
    }
    if ($pattern !== '*') {
      $regexp = Glob::toRegex($pattern, FALSE);
      $this->logger()->debug('Checking for keys matching "{regex}"', ['regex' => $regexp]);
      $keys = array_filter($keys, function ($key) use ($regexp) {
        return preg_match($regexp, $key);
      });
    }
    return $keys;
  }

  private function sumStat($name, $stats) {
    return array_reduce($stats, function ($val, $hostStats) use ($name) {
      return $val + $hostStats[$name];
    }, 0);
  }

  private function formatPct(float $numerator, float $denominator) {
    return sprintf('%s%%', $denominator > 0 ? round($numerator / $denominator * 100, 1) : 0);
  }
}

