<?php

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\Core\Database\Database;

class MassSitemapContext extends RawDrupalContext {

  /**
   * @Then the sitemap should contain an entry for :url
   */
  public function assertSitemapEntry($url) {
    $db = Database::getConnection();
    $query = $db->select('simple_sitemap');
    $query->fields('simple_sitemap');
    $query->condition('sitemap_string', "%{$url}%", 'LIKE');
    $links = $query->execute()->fetchAll();

    if(count($links) === 0) {
      throw new \RuntimeException(sprintf('No sitemap entry found for %s', $url));
    }
  }

  /**
   * @Then the sitemap should not contain an entry for :url
   */
  public function assertNoSitemapEntry($url) {
    $db = Database::getConnection();
    $query = $db->select('simple_sitemap');
    $query->fields('simple_sitemap');
    $query->condition('sitemap_string', "%{$url}%", 'LIKE');
    $links = $query->execute()->fetchAll();

    if(count($links) > 0) {
      throw new \RuntimeException(sprintf('Sitemap entries found for %s', $url));
    }
  }

}