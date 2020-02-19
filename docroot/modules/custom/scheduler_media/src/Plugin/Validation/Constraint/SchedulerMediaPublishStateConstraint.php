<?php

namespace Drupal\scheduler_media\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;

/**
 * Validates publish state values.
 *
 * @Constraint(
 *   id = "SchedulerMediaPublishState",
 *   label = @Translation("Scheduler media publish state", context = "Validation"),
 *   type = "entity:media"
 * )
 */
class SchedulerMediaPublishStateConstraint extends CompositeConstraintBase {

  /**
   * Message shown when publish_on is not the future.
   *
   * @var string
   */
  public $messagePublishStateNotValid = "The 'publish state' must be a valid moderation state transition.";

  /**
   * {@inheritdoc}
   */
  public function coversFields() {
    return ['publish_state'];
  }

}
