<?php

namespace Drupal\mass_content\Entity\Bundle\media;

use Drupal\mass_content_moderation\MassModerationAwareInterface;
use Drupal\mass_content_moderation\MassModerationTrait;
use Drupal\mass_fields\MassCollectionTrait;
use Drupal\mass_fields\MassOrganizationsTrait;
use Drupal\mass_fields\MassSearchTrait;
use Drupal\mass_fields\MassTranslationsTrait;
use Drupal\media\Entity\Media;

/**
 * A base bundle class for media entities.
 */
abstract class MediaBundle extends Media implements MassModerationAwareInterface {
  use MassModerationTrait;
  use MassSearchTrait;
  use MassCollectionTrait;
  use MassOrganizationsTrait;
  use MassTranslationsTrait;

  const FIELD_NAME_ENGLISH_VERSION = 'field_media_english_version';

}
