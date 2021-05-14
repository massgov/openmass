<?php

namespace Drupal\mass_site_map;

use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapWriter;

/**
 * Class MassSiteMapGenerator.
 *
 * @package Drupal\mass_site_map
 */
class MassSiteMapGenerator extends SitemapWriter {

  /**
   * Generates and returns a sitemap chunk.
   *
   * @param array $links
   *   All links with their multilingual versions and settings.
   *
   * @return string
   *   Sitemap chunk
   */
  protected function generateSitemapChunk(array $links) {
    $this->openMemory();
    $this->setIndent(TRUE);
    $this->startDocument(self::XML_VERSION, self::ENCODING);
    $this->writeComment(self::GENERATED_BY);
    $this->startElement('urlset');

    // Add attributes to document.
    if (!$this->isHreflangSitemap()) {
      unset(self::$attributes['xmlns:xhtml']);
    }
    $this->moduleHandler->alter('simple_sitemap_attributes', self::$attributes);
    foreach (self::$attributes as $name => $value) {
      $this->writeAttribute($name, $value);
    }

    // Add URLs to document.
    $this->moduleHandler->alter('simple_sitemap_links', $links);
    foreach ($links as $link) {

      // Add each translation variant URL as location to the sitemap.
      $this->startElement('url');
      $this->writeElement('loc', $link['url']);

      // If more than one language is enabled, add all translation variant URLs
      // as alternate links to this location turning the sitemap into a hreflang
      // sitemap.
      if (isset($link['alternate_urls']) && $this->isHreflangSitemap()) {
        foreach ($link['alternate_urls'] as $language_id => $alternate_url) {
          $this->startElement('xhtml:link');
          $this->writeAttribute('rel', 'alternate');
          $this->writeAttribute('hreflang', $language_id);
          $this->writeAttribute('href', $alternate_url);
          $this->endElement();
        }
      }

      // Add lastmod if any.
      if (isset($link['lastmod'])) {
        $this->writeElement('lastmod', $link['lastmod']);
      }

      // Add changefreq if any.
      if (isset($link['changefreq'])) {
        $this->writeElement('changefreq', $link['changefreq']);
      }

      // Add priority if any.
      if (isset($link['priority'])) {
        $this->writeElement('priority', $link['priority']);
      }

      // Add images if any.
      if (!empty($link['images'])) {
        foreach ($link['images'] as $image) {
          $this->startElement('image:image');
          $this->writeElement('image:loc', $image['path']);
          $this->endElement();
        }
      }

      // PageMap support.
      if (!empty($link['pagemap'])) {
        $this->startElement('PageMap');
        $this->writeAttribute('xmlns', 'http://www.google.com/schemas/sitemap-pagemap/1.0');
        if (!empty($link['pagemap']['metatags'])) {
          $this->startElement('DataObject');
          $this->writeAttribute('type', 'metatags');
          foreach ($link['pagemap']['metatags'] as $tag) {
            $this->startElement('Attribute');
            $this->writeAttribute('name', $tag['name']);
            $this->text($tag['value']);
            $this->endElement();
          }
          $this->endElement();
        }
        $this->endElement();
      }

      $this->endElement();
    }
    $this->endElement();
    $this->endDocument();

    return $this->outputMemory();
  }

}
