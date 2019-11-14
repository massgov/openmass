<?php

namespace Drupal\mass_site_map;

use Drupal\simple_sitemap\SitemapGenerator;

/**
 * Class MassSiteMapGenerator.
 *
 * @package Drupal\mass_site_map
 */
class MassSiteMapGenerator extends SitemapGenerator {

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
    $this->writer->openMemory();
    $this->writer->setIndent(TRUE);
    $this->writer->startDocument(self::XML_VERSION, self::ENCODING);
    $this->writer->writeComment(self::GENERATED_BY);
    $this->writer->startElement('urlset');

    // Add attributes to document.
    if (!$this->isHreflangSitemap()) {
      unset(self::$attributes['xmlns:xhtml']);
    }
    $this->moduleHandler->alter('simple_sitemap_attributes', self::$attributes);
    foreach (self::$attributes as $name => $value) {
      $this->writer->writeAttribute($name, $value);
    }

    // Add URLs to document.
    $this->moduleHandler->alter('simple_sitemap_links', $links);
    foreach ($links as $link) {

      // Add each translation variant URL as location to the sitemap.
      $this->writer->startElement('url');
      $this->writer->writeElement('loc', $link['url']);

      // If more than one language is enabled, add all translation variant URLs
      // as alternate links to this location turning the sitemap into a hreflang
      // sitemap.
      if (isset($link['alternate_urls']) && $this->isHreflangSitemap()) {
        foreach ($link['alternate_urls'] as $language_id => $alternate_url) {
          $this->writer->startElement('xhtml:link');
          $this->writer->writeAttribute('rel', 'alternate');
          $this->writer->writeAttribute('hreflang', $language_id);
          $this->writer->writeAttribute('href', $alternate_url);
          $this->writer->endElement();
        }
      }

      // Add lastmod if any.
      if (isset($link['lastmod'])) {
        $this->writer->writeElement('lastmod', $link['lastmod']);
      }

      // Add changefreq if any.
      if (isset($link['changefreq'])) {
        $this->writer->writeElement('changefreq', $link['changefreq']);
      }

      // Add priority if any.
      if (isset($link['priority'])) {
        $this->writer->writeElement('priority', $link['priority']);
      }

      // Add images if any.
      if (!empty($link['images'])) {
        foreach ($link['images'] as $image) {
          $this->writer->startElement('image:image');
          $this->writer->writeElement('image:loc', $image['path']);
          $this->writer->endElement();
        }
      }

      // PageMap support.
      if (!empty($link['pagemap'])) {
        $this->writer->startElement('PageMap');
        $this->writer->writeAttribute('xmlns', 'http://www.google.com/schemas/sitemap-pagemap/1.0');
        if (!empty($link['pagemap']['metatags'])) {
          $this->writer->startElement('DataObject');
          $this->writer->writeAttribute('type', 'metatags');
          foreach ($link['pagemap']['metatags'] as $tag) {
            $this->writer->startElement('Attribute');
            $this->writer->writeAttribute('name', $tag['name']);
            $this->writer->text($tag['value']);
            $this->writer->endElement();
          }
          $this->writer->endElement();
        }
        $this->writer->endElement();
      }

      $this->writer->endElement();
    }
    $this->writer->endElement();
    $this->writer->endDocument();

    return $this->writer->outputMemory();
  }

}
