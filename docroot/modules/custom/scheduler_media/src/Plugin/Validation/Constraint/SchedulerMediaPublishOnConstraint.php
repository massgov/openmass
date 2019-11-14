<?php

namespace Drupal\scheduler_media\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;

/**
 * Validates publish on values.
 *
 * @Constraint(
 *   id = "SchedulerMediaPublishOn",
 *   label = @Translation("Scheduler media publish on", context = "Validation"),
 *   type = "entity:media"
 * )
 */
class SchedulerMediaPublishOnConstraint extends CompositeConstraintBase {

  /**
   * Message shown when publish_on is not the future.
   *
   * @var string
   */
  public $messagePublishOnDateNotInFuture = "The 'publish on' date must be in the future.";

  /**
   * {@inheritdoc}
   */
  public function coversFields() {
    return ['publish_on'];
  }

}
