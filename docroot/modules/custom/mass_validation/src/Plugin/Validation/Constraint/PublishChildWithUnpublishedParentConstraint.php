<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Prevents a child to be published if its parent is unpublished.
 *
 * @Constraint(
 *   id = "PublishChildWithUnpublishedParent",
 *   label = @Translation("Prevents a child to be published if its parent is unpublished", context = "Validation")
 * )
 */
class PublishChildWithUnpublishedParentConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public const MESSAGE = 'This content cannot be published because its parent is not published. Publish the parent first, or choose a different published parent.';

}
