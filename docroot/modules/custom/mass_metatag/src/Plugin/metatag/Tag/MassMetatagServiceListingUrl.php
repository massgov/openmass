<?php

namespace Drupal\mass_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'mg_service_listing_url' meta tag.
 *
 * @MetatagTag(
 *   id = "mass_metatag_service_listing_url",
 *   label = @Translation("mg_service_listing_url"),
 *   description = @Translation("The URL to a listing of services."),
 *   name = "mg_service_listing_url",
 *   group = "mass_metatag",
 *   weight = -1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class MassMetatagServiceListingUrl extends MetaNameBase {

}
