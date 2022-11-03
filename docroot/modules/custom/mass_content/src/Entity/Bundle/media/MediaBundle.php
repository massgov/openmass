<?php

namespace Drupal\mass_content\Entity\Bundle\media;

use Drupal\mass_content_moderation\MassModerationAwareInterface;
use Drupal\mass_content_moderation\MassModerationTrait;
use Drupal\mass_fields\MassSearchTrait;
use Drupal\media\Entity\Media;

/**
 * A base bundle class for media entities.
 */
abstract class MediaBundle extends Media implements MassModerationAwareInterface {
  use MassModerationTrait;
  use MassSearchTrait;

}
