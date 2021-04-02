<?php

namespace Drupal\mass_scheduled_transitions\Plugin\Validation\Constraint;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\mass_content_moderation\MassModeration;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Promo Page Unpublish constraint.
 */
class PromoPageUnpublishConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($item_list, Constraint $constraint) {

    /** @var \Drupal\node\Entity\Node $entity */
    $entity = $item_list->getEntity();

    // We don't care about drafts.
    if ($entity->bundle() !== 'campaign_landing' || !$entity->isPublished()) {
      return;
    }

    // Add 14 months to today date.
    $future = new DrupalDateTime("now +14 months");
    $transitions = mass_scheduled_transitions_loadByHostEntity($entity);
    $fail = TRUE;
    foreach ($transitions as $transition) {
      // If the unpublished date is greater than 14 months then validation error is displayed.
      // getTimestamp() is needed because of https://www.drupal.org/project/drupal/issues/3058010.
      if ($transition->getTransitionDate()->getTimestamp() < $future->getTimestamp() && $transition->getState() == 'unpublished') {
        $fail = FALSE;
        break;
      }
    }
    if ($fail) {
      $this->context->buildViolation($constraint->errorMessage)
        // @DCG The path depends on entity type. It can be title, name, etc.
        ->atPath('moderation_state')
        ->addViolation();
    }
  }

}
