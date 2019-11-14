<?php

namespace Drupal\scheduler_media\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;

/**
 * Validates unpublish on values.
 *
 * @Constraint(
 *   id = "SchedulerMediaUnpublishOn",
 *   label = @Translation("Scheduler media unpublish on", context = "Validation"),
 *   type = "entity:media"
 * )
 */
class SchedulerMediaUnpublishOnConstraint extends CompositeConstraintBase {

  /**
   * Message shown when unpublish_on is missing but publish_on has been entered.
   *
   * @var string
   */
  public $messageUnpublishOnRequiredIfPublishOnEntered = "If you set a 'publish on' date then you must also set an 'unpublish on' date.";

  /**
   * Message shown when unpublish_on is missing but node is published directly.
   *
   * @var string
   */
  public $messageUnpublishOnRequiredIfPublishing = "Either you must set an 'unpublish on' date or save this node as unpublished.";

  /**
   * Message shown when unpublish_on is not in the future.
   *
   * @var string
   */
  public $messageUnpublishOnDateNotInFuture = "The 'unpublish on' date must be in the future.";

  /**
   * Message shown when unpublish date is not later than the publish date.
   *
   * @var string
   */
  public $messageUnpublishOnTooEarly = "The 'unpublish on' date must be later than the 'publish on' date.";

  /**
   * {@inheritdoc}
   */
  public function coversFields() {
    return ['unpublish_on'];
  }

}
