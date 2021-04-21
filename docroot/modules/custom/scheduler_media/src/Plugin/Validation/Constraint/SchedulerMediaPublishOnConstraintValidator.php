<?php

namespace Drupal\scheduler_media\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the SchedulerMediaPublishOn constraint.
 */
class SchedulerMediaPublishOnConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    $media_publish_on = $entity->value;
    $default_publish_past_date = \Drupal::config('scheduler_media.settings')->get('default_publish_past_date');
    $scheduler_publish_past_date = \Drupal::entityTypeManager()->getStorage('media_type')
      ->load($entity->getEntity()->bundle())
      ->getThirdPartySetting('scheduler_media', 'publish_past_date', $default_publish_past_date);

    if ($media_publish_on && $scheduler_publish_past_date == 'error' && $media_publish_on < \Drupal::time()->getRequestTime()) {
      $this->context->buildViolation($constraint->messagePublishOnDateNotInFuture)
        ->atPath('publish_on')
        ->addViolation();
    }
  }

}
