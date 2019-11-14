<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;

/**
 * Checks that the submitted value has Downloads or Pages field.
 *
 * @Constraint(
 *   id = "BinderDownloadsOrPages",
 *   label = @Translation("Required Downloads or Pages field", context = "Validation"),
 * )
 */
class BinderDownloadsOrPagesConstraint extends CompositeConstraintBase {

  /**
   * Message shown when a binder node has neither downloads or pages.
   *
   * @var string
   */
  public $message = "You must provide either a Downloads file or Pages section.";

  /**
   * {@inheritdoc}
   */
  public function coversFields() {
    return [
      'field_binder_pages',
      'field_downloads',
    ];
  }

}
