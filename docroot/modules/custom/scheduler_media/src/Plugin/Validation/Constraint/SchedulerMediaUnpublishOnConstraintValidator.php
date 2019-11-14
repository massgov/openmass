<?php

namespace Drupal\scheduler_media\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the SchedulerMediaUnpublishOn constraint.
 */
class SchedulerMediaUnpublishOnConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    $scheduler_unpublish_required = \Drupal::config('scheduler_media.settings')->get('default_unpublish_required');
    $scheduler_unpublish_past_date = \Drupal::entityTypeManager()->getStorage('media_type')
      ->load($entity->getEntity()->bundle())
      ->getThirdPartySetting('scheduler_media', 'publish_past_date', $scheduler_unpublish_required);
    $publish_on = $entity->getEntity()->publish_on->value;
    $media_unpublish_on = $entity->value;
    $status = $entity->getEntity()->status->value;

    // When the 'required unpublishing' option is enabled the #required form
    // attribute cannot set in every case. However a value must be entered if
    // also setting a publish-on date.
    if ($scheduler_unpublish_required && !empty($publish_on) && empty($media_unpublish_on)) {
      $this->context->buildViolation($constraint->messageUnpublishOnRequiredIfPublishOnEntered)
        ->atPath('unpublish_on')
        ->addViolation();
    }

    // Similar to the above scenario, the unpublish-on date must be entered if
    // the content is being published directly.
    if ($scheduler_unpublish_required && $status && empty($media_unpublish_on)) {
      $this->context->buildViolation($constraint->messageUnpublishOnRequiredIfPublishing)
        ->atPath('unpublish_on')
        ->addViolation();
    }

    // Check that the unpublish-on date is in the future. Unlike the publish-on
    // field, there is no option to use a past date, as this is not relevant for
    // unpublshing. The date must ALWAYS be in the future if it is entered.
    if ($media_unpublish_on && $media_unpublish_on < REQUEST_TIME) {
      $this->context->buildViolation($constraint->messageUnpublishOnDateNotInFuture)
        ->atPath('unpublish_on')
        ->addViolation();
    }

    // If both dates are entered then the unpublish-on date must be later than
    // the publish-on date.
    if (!empty($publish_on) && !empty($media_unpublish_on) && $media_unpublish_on < $publish_on) {
      $this->context->buildViolation($constraint->messageUnpublishOnTooEarly)
        ->atPath('unpublish_on')
        ->addViolation();
    }
  }

}
