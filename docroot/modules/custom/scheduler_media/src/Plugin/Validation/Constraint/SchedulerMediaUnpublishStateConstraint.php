<?php

namespace Drupal\scheduler_media\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;

/**
 * Validates unpublish state values.
 *
 * @Constraint(
 *   id = "SchedulerMediaUnpublishState",
 *   label = @Translation("Scheduler media unpublish state", context = "Validation"),
 *   type = "entity:media"
 * )
 */
class SchedulerMediaUnpublishStateConstraint extends CompositeConstraintBase {

  /**
   * Message shown when publish_on is not the future.
   *
   * @var string
   */
  public $messageUnpublishStateNotValid = "The 'unpublish state' must be a valid moderation state transition.";

  /**
   * {@inheritdoc}
   */
  public function coversFields() {
    return ['unpublish_state'];
  }

}
