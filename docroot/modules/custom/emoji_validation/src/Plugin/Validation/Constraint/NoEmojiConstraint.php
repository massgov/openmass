<?php

namespace Drupal\emoji_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * No emoji validation constraint.
 *
 * @Constraint(
 *   id = "NoEmojiConstraint",
 *   label = @Translation("No emoji validation", context = "Validation"),
 *   type = "string"
 * )
 */
class NoEmojiConstraint extends Constraint {

  /**
   * The error message to display when emojis are found.
   *
   * @var string
   */
  public $message = 'Emoji icons are not allowed in text fields. Please remove prior to saving.';

}
