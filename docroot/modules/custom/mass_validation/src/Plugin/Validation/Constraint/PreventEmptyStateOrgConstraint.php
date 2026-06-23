<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

/**
 * @file
 * Contains PreventEmptyStageOrgConstraint class.
 */

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;

/**
 * Prevent nodes from having empty state orgs.
 *
 * @Constraint(
 *   id = "PreventEmptyStateOrg",
 *   label = @Translation("Prevent nodes from being created if Signees has no state organization.", context = "Validation"),
 *   type = "entity:node"
 * )
 */
class PreventEmptyStateOrgConstraint extends CompositeConstraintBase {

  /**
   * The default violation message.
   *
   * @var string
   */
  public const MESSAGE = 'You must enter at least one State Organization.';

  /**
   * Message shown when a node has no state org.
   *
   * @var string
   */
  public $message = self::MESSAGE;

  /**
   * {@inheritdoc}
   */
  public function coversFields() {
    return [
      'field_news_signees',
    ];
  }

}
