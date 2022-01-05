<?php

namespace Drupal\mass_validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\mass_validation\Information\MassChildEntityWarningBuilder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if an entity has a parent before unpublishing.
 */
class UnpublishParentConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if (!isset($entity)) {
      return;
    }

    $moderation_state = $entity->get('moderation_state')->value;
    $unpublished_states = ['unpublished', 'trash'];
    if (in_array($moderation_state, $unpublished_states)) {
      if ($children = \Drupal::service('class_resolver')
        ->getInstanceFromDefinition(MassChildEntityWarningBuilder::class)
        ->buildChildEntityWarnings($entity, TRUE)) {
          foreach ($children as $child) {
          $items = $child->getList()['#items'];
          if (!empty($items)) {
            $items_string = implode(', ', $items);
            $message = new PluralTranslatableMarkup(
              count($items),
              $constraint->message . '1 published child: ' . $items_string,
              $constraint->message . '@count published children: ' . $items_string);
            $this->context->addViolation($message);
          }
        }
      }
    }
  }

}
