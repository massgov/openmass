<?php

namespace Drupal\mass_inline_message\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates mass-inline-message elements in rich text fields.
 *
 * @Constraint(
 *   id = "InlineMessageConstraint",
 *   label = @Translation("Inline message constraint", context = "Validation"),
 * )
 */
class InlineMessageConstraint extends Constraint {

  public $missingTitleMessage = 'Message box title is required.';

  public $titleTooLongMessage = 'Message box title must be @count characters or fewer.';

  public $invalidTypeMessage = 'Message box type must be Informational or Alert.';

  public $bodyTooLongMessage = 'Message box text must be @count characters or fewer (plain text, not including HTML).';

}
