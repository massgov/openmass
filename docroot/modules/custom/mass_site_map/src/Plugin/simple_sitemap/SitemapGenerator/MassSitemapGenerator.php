<?php

namespace Drupal\mass_site_map\Plugin\simple_sitemap\SitemapGenerator;

use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\DefaultSitemapGenerator;

/**
 * Overriding class to add custom tags to the sitemap.
 *
 * @SitemapGenerator(
 *   id = "mass",
 *   label = @Translation("Mass Default sitemap generator"),
 *   description = @Translation("Generates a standard conform hreflang sitemap of your content."),
 * )
 */
class MassSitemapGenerator extends DefaultSitemapGenerator {

  /**
   * Adds a URL element to the sitemap.
   *
   * @param array $url_data
   *   The array of properties for this URL.
   */
  protected function addUrl(array $url_data): void {
    $this->writer->writeElement('loc', $url_data['url']);

    // If more than one language is enabled, add all translation variant URLs
    // as alternate links to this link turning the sitemap into a hreflang
    // sitemap.
    if (isset($url_data['alternate_urls']) && $this->sitemap->isMultilingual()) {
      $this->addAlternateUrls($url_data['alternate_urls']);
    }

    // Add lastmod if any.
    if (isset($url_data['lastmod'])) {
      $this->writer->writeElement('lastmod', $url_data['lastmod']);
    }

    // Add changefreq if any.
    if (isset($url_data['changefreq'])) {
      $this->writer->writeElement('changefreq', $url_data['changefreq']);
    }

    // Add priority if any.
    if (isset($url_data['priority'])) {
      $this->writer->writeElement('priority', $url_data['priority']);
    }

    // Add images if any.
    if (!empty($url_data['images'])) {
      foreach ($url_data['images'] as $image) {
        $this->writer->startElement('image:image');
        $this->writer->writeElement('image:loc', $image['path']);
        if (strlen($image['title']) > 0) {
          $this->writer->writeElement('image:title', $image['title']);
        }
        if (strlen($image['alt']) > 0) {
          $this->writer->writeElement('image:caption', $image['alt']);
        }
        $this->writer->endElement();
      }
    }

    // PageMap support.
    if (!empty($url_data['pagemap'])) {
      $this->writer->startElement('PageMap');
      $this->writer->writeAttribute('xmlns', 'http://www.google.com/schemas/sitemap-pagemap/1.0');
      if (!empty($url_data['pagemap']['metatags'])) {
        $this->writer->startElement('DataObject');
        $this->writer->writeAttribute('type', 'metatags');
        foreach ($url_data['pagemap']['metatags'] as $tag) {
          $this->writer->startElement('Attribute');
          $this->writer->writeAttribute('name', $tag['name']);
          $this->writer->text($tag['value']);
          $this->writer->endElement();
        }
        $this->writer->endElement();
      }
      $this->writer->endElement();
    }
  }

}
