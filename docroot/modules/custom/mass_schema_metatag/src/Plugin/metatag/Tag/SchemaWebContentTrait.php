<?php

namespace Drupal\mass_schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaCreativeWorkTrait;

/**
 * Schema.org WebContent trait.
 */
trait SchemaWebContentTrait {

  use SchemaCreativeWorkTrait;

  /**
   * All object types.
   *
   * @return array
   *   An array of all possible object types.
   */
  public static function creativeWorkObjects() {
    return [
      'WebContent',
      'WebPage',
      'WebPageElement',
      'WebSite',
    ];
  }

}
