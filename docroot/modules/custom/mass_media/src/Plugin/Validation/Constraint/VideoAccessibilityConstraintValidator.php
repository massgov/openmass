<?php

declare(strict_types=1);

namespace Drupal\mass_media\Plugin\Validation\Constraint;

use Drupal\mass_media\RemoteVideoUrl;
use Drupal\media\MediaInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates provider compatibility without making remote requests.
 */
final class VideoAccessibilityConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!$value instanceof MediaInterface || $value->bundle() !== 'media_video') {
      return;
    }
    $main = RemoteVideoUrl::parse((string) $value->get('field_media_video_embed_field')->value);
    foreach (['field_video_audio_description', 'field_video_sign_language'] as $field) {
      if (!$value->hasField($field) || $value->get($field)->isEmpty()) {
        continue;
      }
      $alternate = RemoteVideoUrl::parse((string) $value->get($field)->uri);
      $valid = $alternate && $main && $alternate['provider'] === $main['provider'];
      if ($field === 'field_video_sign_language') {
        $valid = $valid && $main['provider'] === 'youtube';
      }
      if (!$valid) {
        $this->context->buildViolation($field === 'field_video_sign_language' ? $constraint->signProvider : $constraint->descriptionProvider)
          ->atPath($field . '.0.uri')
          ->addViolation();
      }
    }
  }

}
