<?php

declare(strict_types=1);

namespace Drupal\mass_media\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Plugin\Field\FieldFormatter\GenericFileFormatter;

/**
 * Allows text tracks to be displayed as ordinary downloads if needed.
 */
#[FieldFormatter(
  id: 'mass_media_video_track',
  label: new TranslatableMarkup('Video text track download'),
  field_types: ['mass_media_video_track'],
)]
final class VideoTrackFormatter extends GenericFileFormatter {}
