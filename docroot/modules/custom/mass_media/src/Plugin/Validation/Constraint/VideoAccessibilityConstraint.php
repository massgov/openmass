<?php

declare(strict_types=1);

namespace Drupal\mass_media\Plugin\Validation\Constraint;

use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that alternate videos are compatible with the primary video.
 */
#[Constraint(
  id: 'MassMediaVideoAccessibility',
  label: new TranslatableMarkup('Video accessibility sources', [], ['context' => 'Validation']),
  type: 'entity:media',
)]
final class VideoAccessibilityConstraint extends SymfonyConstraint {

  public string $descriptionProvider = 'Use an audio-described video hosted on the same provider as the main video (YouTube or Vimeo).';

  public string $signProvider = 'Synchronized sign language requires both the main video and the interpretation video to be hosted on YouTube.';

}
